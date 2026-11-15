<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$user_id = (int) $_SESSION['user_id'];

$errors = [];
$successOrders = $_SESSION['last_orders'] ?? [];
unset($_SESSION['last_orders']);

// Mengambil data pengguna.
$stmt = $conn->prepare("
    SELECT name, phone, address
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    exit('Data pengguna tidak ditemukan.');
}

// Mengambil keranjang pengguna.
$stmt = $conn->prepare("
    SELECT id
    FROM carts
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart = $stmt->get_result()->fetch_assoc();

$cart_id = $cart ? (int) $cart['id'] : 0;

// Fungsi mengambil produk keranjang.
function getCheckoutItems($conn, $cart_id, $lock = false)
{
    $sql = "
        SELECT
            ci.id AS item_id,
            ci.quantity,
            p.id AS product_id,
            p.store_id,
            p.product_name,
            p.product_photo,
            p.price,
            p.stock,
            p.status AS product_status,
            s.status AS store_status,
            s.store_name
        FROM cart_items ci
        INNER JOIN products p
            ON ci.product_id = p.id
        INNER JOIN stores s
            ON p.store_id = s.id
        WHERE ci.cart_id = ?
        ORDER BY p.store_id, ci.id
    ";

    if ($lock) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Mengambil isi keranjang.
$items = $cart_id > 0
    ? getCheckoutItems($conn, $cart_id)
    : [];

$total = 0;
$ready = !empty($items);

foreach ($items as $item) {
    $subtotal = (float) $item['price'] * (int) $item['quantity'];
    $total += $subtotal;

    if (
        $item['product_status'] !== 'active' ||
        $item['store_status'] !== 'active' ||
        (int) $item['stock'] < (int) $item['quantity']
    ) {
        $ready = false;
    }
}

// Memproses pesanan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf();

    $recipient = trim($_POST['recipient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['shipping_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($recipient === '' || mb_strlen($recipient) > 100) {
        $errors[] = 'Nama penerima wajib diisi (maksimal 100 karakter).';
    }

    if (
        $phone === '' ||
        !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)
    ) {
        $errors[] = 'Nomor telepon tidak valid.';
    }

    if ($address === '' || mb_strlen($address) > 2000) {
        $errors[] = 'Alamat pengiriman wajib diisi (maksimal 2000 karakter).';
    }

    if (mb_strlen($notes) > 1000) {
        $errors[] = 'Catatan maksimal 1000 karakter.';
    }

    if (!$errors && $cart_id <= 0) {
        $errors[] = 'Keranjang belanja tidak ditemukan.';
    }

    if (!$errors) {

        try {

            $conn->begin_transaction();

            // Mengunci dan memeriksa ulang produk serta stok.
            $items = getCheckoutItems($conn, $cart_id, true);

            if (empty($items)) {
                throw new Exception('Keranjang belanja kosong.');
            }

            // Mengelompokkan produk berdasarkan toko.
            $groupedItems = [];

            foreach ($items as $item) {

                if (
                    $item['product_status'] !== 'active' ||
                    $item['store_status'] !== 'active'
                ) {
                    throw new Exception(
                        'Ada produk atau toko yang sudah tidak aktif.'
                    );
                }

                if (
                    (int) $item['quantity'] <= 0 ||
                    (int) $item['stock'] < (int) $item['quantity']
                ) {
                    throw new Exception(
                        'Stok produk ' . $item['product_name'] .
                        ' tidak mencukupi.'
                    );
                }

                $store_id = (int) $item['store_id'];

                $groupedItems[$store_id][] = $item;
            }

            $createdOrders = [];

            // Membuat pesanan untuk setiap toko.
            foreach ($groupedItems as $store_id => $storeItems) {

                $subtotal = 0;

                foreach ($storeItems as $item) {
                    $subtotal +=
                        (float) $item['price'] *
                        (int) $item['quantity'];
                }

                // Ongkos kirim sementara Rp0.
                $shipping_cost = 0;
                $total_amount = $subtotal + $shipping_cost;

                // Menyimpan nama penerima bersama alamat.
                $shipping_address =
                    "Nama Penerima: " . $recipient .
                    "\nAlamat: " . $address;

                // Metode pembayaran sementara transfer bank.
                $payment_method = 'transfer';

                // Membuat kode pesanan unik.
                $order_code =
                    'GC-' . date('YmdHis') . '-' .
                    strtoupper(bin2hex(random_bytes(4)));

                // Menyimpan pesanan utama.
                $stmt = $conn->prepare("
                    INSERT INTO orders (
                        order_code,
                        user_id,
                        store_id,
                        shipping_address,
                        phone,
                        subtotal,
                        shipping_cost,
                        payment_method,
                        total_amount,
                        notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "siissddsds",
                    $order_code,
                    $user_id,
                    $store_id,
                    $shipping_address,
                    $phone,
                    $subtotal,
                    $shipping_cost,
                    $payment_method,
                    $total_amount,
                    $notes
                );

                $stmt->execute();

                $order_id = (int) $conn->insert_id;

                // Menyimpan rincian produk pesanan.
                foreach ($storeItems as $item) {

                    $product_id = (int) $item['product_id'];
                    $product_name = $item['product_name'];
                    $product_photo = $item['product_photo'];
                    $price = (float) $item['price'];
                    $quantity = (int) $item['quantity'];

                    $item_subtotal = $price * $quantity;

                    $stmt = $conn->prepare("
                        INSERT INTO order_items (
                            order_id,
                            product_id,
                            product_name,
                            product_photo,
                            price,
                            quantity,
                            subtotal
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->bind_param(
                        "iissdid",
                        $order_id,
                        $product_id,
                        $product_name,
                        $product_photo,
                        $price,
                        $quantity,
                        $item_subtotal
                    );

                    $stmt->execute();

                    // Mengurangi stok secara aman.
                    $stmt = $conn->prepare("
                        UPDATE products
                        SET stock = stock - ?
                        WHERE id = ?
                          AND stock >= ?
                          AND status = 'active'
                    ");

                    $stmt->bind_param(
                        "iii",
                        $quantity,
                        $product_id,
                        $quantity
                    );

                    $stmt->execute();

                    if ($stmt->affected_rows !== 1) {
                        throw new Exception(
                            'Gagal memperbarui stok produk.'
                        );
                    }
                }

                $createdOrders[] = [
                    'order_code' => $order_code,
                    'total_amount' => $total_amount
                ];
            }

            // Mengosongkan keranjang setelah semua pesanan tersimpan.
            $stmt = $conn->prepare("
                DELETE FROM cart_items
                WHERE cart_id = ?
            ");

            $stmt->bind_param("i", $cart_id);
            $stmt->execute();

            $conn->commit();

            // Menyimpan informasi pesanan untuk halaman konfirmasi.
            $_SESSION['last_orders'] = $createdOrders;

            header('Location: /GreenCycle/pages/order-success.php');
            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $errors[] = $e->getMessage();
        }
    }
}

// Menghitung ulang data tampilan setelah POST gagal.
$items = $cart_id > 0
    ? getCheckoutItems($conn, $cart_id)
    : [];

$total = 0;
$ready = !empty($items);

foreach ($items as $item) {

    $total +=
        (float) $item['price'] *
        (int) $item['quantity'];

    if (
        $item['product_status'] !== 'active' ||
        $item['store_status'] !== 'active' ||
        (int) $item['stock'] < (int) $item['quantity']
    ) {
        $ready = false;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">

    <style>
        .checkout-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 25px;
        }

        .checkout-card {
            background: white;
            border: 1px solid #e5ebe3;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .checkout-card h2 {
            color: #145c3b;
            margin-bottom: 20px;
        }

        .checkout-form label {
            display: block;
            font-weight: 600;
            margin: 15px 0 7px;
        }

        .checkout-form input,
        .checkout-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce5dc;
            border-radius: 8px;
            font-size: 15px;
        }

        .payment-option {
            background: #edf6ec;
            border: 1px solid #b9d9bd;
            padding: 15px;
            border-radius: 10px;
        }

        .checkout-button {
            width: 100%;
            padding: 14px;
            margin-top: 20px;
            border: none;
            border-radius: 9px;
            background: #198754;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .checkout-button:hover {
            background: #146c43;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .checkout-total {
            color: #198754;
            font-size: 23px;
            font-weight: bold;
        }

        .error-message {
            padding: 12px;
            background: #fff0f0;
            color: #b42318;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .success-message {
            padding: 20px;
            background: #edf8ed;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media(max-width: 700px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<main class="checkout-container">

    <h1>Checkout Pesanan</h1>

    <p>Lengkapi informasi pengiriman sebelum membuat pesanan.</p>

    <p>
        <a href="/GreenCycle/pages/cart.php">
            &larr; Kembali ke Keranjang
        </a>
    </p>

    <?php if (isset($_GET['success']) && !empty($successOrders)): ?>

        <div class="success-message">

            <h2>Pesanan Berhasil Dibuat!</h2>

            <p>
                Terima kasih telah berbelanja di GreenCycle.
            </p>

            <?php foreach ($successOrders as $order): ?>

                <hr>

                <p>
                    <strong>Kode Pesanan:</strong>
                    <?= e($order['order_code']) ?>
                </p>

                <p>
                    <strong>Total:</strong>
                    Rp <?= number_format(
                        $order['total_amount'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </p>

            <?php endforeach; ?>

            <p>
                Metode pembayaran:
                <strong>Transfer Bank</strong>
            </p>

            <p>
                Silakan hubungi admin untuk memperoleh
                informasi rekening dan proses verifikasi pembayaran.
            </p>

            <a href="/GreenCycle/pages/products.php">
                Kembali Belanja
            </a>

        </div>

    <?php elseif (empty($items)): ?>

        <div class="checkout-card">
            <h2>Keranjang Kosong</h2>

            <p>Silakan pilih produk terlebih dahulu.</p>

            <a href="/GreenCycle/pages/products.php">
                Lihat Produk
            </a>
        </div>

    <?php else: ?>

        <?php foreach ($errors as $error): ?>

            <div class="error-message">
                <?= e($error) ?>
            </div>

        <?php endforeach; ?>

        <div class="checkout-grid">

            <section class="checkout-card">

                <h2>Informasi Pengiriman</h2>

                <form method="POST"
                      action="checkout.php"
                      class="checkout-form">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= e(csrf_token()) ?>">

                    <label>Nama Penerima</label>

                    <input
                        type="text"
                        name="recipient_name"
                        maxlength="100"
                        value="<?= e($_POST['recipient_name'] ?? $user['name'] ?? '') ?>"
                        required
                    >

                    <label>Nomor Telepon</label>

                    <input
                        type="tel"
                        name="phone"
                        maxlength="20"
                        value="<?= e($_POST['phone'] ?? $user['phone'] ?? '') ?>"
                        required
                    >

                    <label>Alamat Pengiriman</label>

                    <textarea
                        name="shipping_address"
                        rows="4"
                        maxlength="2000"
                        required
                    ><?= e($_POST['shipping_address'] ?? $user['address'] ?? '') ?></textarea>

                    <label>Catatan Pesanan (Opsional)</label>

                    <textarea
                        name="notes"
                        rows="3"
                        maxlength="1000"
                        placeholder="Contoh: Mohon dikemas dengan aman."
                    ><?= e($_POST['notes'] ?? '') ?></textarea>

                    <label>Metode Pembayaran</label>

                    <div class="payment-option">

                        <strong>Transfer Bank</strong>

                        <p>
                            Pembayaran dilakukan melalui transfer
                            bank dan akan diverifikasi secara manual
                            oleh admin.
                        </p>

                        <input
                            type="hidden"
                            name="payment_method"
                            value="transfer"
                        >

                    </div>

                    <?php if (!$ready): ?>

                        <p class="error-message">
                            Ada produk yang stoknya tidak mencukupi
                            atau sudah tidak aktif.
                            Periksa keranjang sebelum melanjutkan.
                        </p>

                    <?php endif; ?>

                    <button
                        type="submit"
                        class="checkout-button"
                        <?= !$ready ? 'disabled' : '' ?>
                    >
                        Buat Pesanan
                    </button>

                </form>

            </section>

            <aside class="checkout-card">

                <h2>Ringkasan Belanja</h2>

                <?php foreach ($items as $item): ?>

                    <div class="order-item">

                        <div>
                            <strong>
                                <?= e($item['product_name']) ?>
                            </strong>

                            <p>
                                <?= (int) $item['quantity'] ?> x
                                Rp <?= number_format(
                                    (float) $item['price'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </p>

                            <small>
                                Toko: <?= e($item['store_name']) ?>
                            </small>
                        </div>

                        <strong>
                            Rp <?= number_format(
                                (float) $item['price'] *
                                (int) $item['quantity'],
                                0,
                                ',',
                                '.'
                            ) ?>
                        </strong>

                    </div>

                <?php endforeach; ?>

                <hr>

                <p>
                    Subtotal:
                    <strong>
                        Rp <?= number_format($total, 0, ',', '.') ?>
                    </strong>
                </p>

                <p>
                    Ongkos Kirim:
                    <strong>Rp 0</strong>
                </p>

                <hr>

                <p>Total Pembayaran</p>

                <div class="checkout-total">
                    Rp <?= number_format($total, 0, ',', '.') ?>
                </div>

            </aside>

        </div>

    <?php endif; ?>

</main>

</body>
</html>