<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Membuat token keamanan CSRF.
if (empty($_SESSION['cart_csrf_token'])) {
    $_SESSION['cart_csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Mengambil keranjang milik customer.
$cart_stmt = $conn->prepare("
    SELECT id
    FROM carts
    WHERE user_id = ?
    LIMIT 1
");

$cart_stmt->bind_param("i", $user_id);
$cart_stmt->execute();

$cart = $cart_stmt->get_result()->fetch_assoc();

$cart_id = $cart ? (int)$cart['id'] : 0;


// ==========================================
// MEMPROSES UPDATE DAN HAPUS KERANJANG
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['cart_csrf_token'], $token)
    ) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';

    } elseif ($cart_id <= 0) {

        $error = 'Keranjang belum tersedia.';

    } else {

        $action = $_POST['action'] ?? '';

        $item_id = filter_input(
            INPUT_POST,
            'item_id',
            FILTER_VALIDATE_INT
        );

        if (
            $item_id === false ||
            $item_id === null ||
            $item_id <= 0
        ) {
            $error = 'Data produk tidak valid.';

        } else {

            try {

                // Memastikan produk milik keranjang customer.
                $check = $conn->prepare("
                    SELECT
                        ci.id,
                        p.stock,
                        p.status AS product_status
                    FROM cart_items ci
                    INNER JOIN products p
                        ON ci.product_id = p.id
                    WHERE ci.id = ?
                      AND ci.cart_id = ?
                    LIMIT 1
                ");

                $check->bind_param(
                    "ii",
                    $item_id,
                    $cart_id
                );

                $check->execute();

                $item = $check->get_result()->fetch_assoc();

                if (!$item) {
                    throw new Exception(
                        'Produk tidak ditemukan dalam keranjang.'
                    );
                }

                // Memperbarui jumlah produk.
                if ($action === 'update') {

                    $quantity = filter_input(
                        INPUT_POST,
                        'quantity',
                        FILTER_VALIDATE_INT
                    );

                    if (
                        $quantity === false ||
                        $quantity === null ||
                        $quantity < 1
                    ) {
                        throw new Exception(
                            'Jumlah produk tidak valid.'
                        );
                    }

                    if ($item['product_status'] !== 'active') {
                        throw new Exception(
                            'Produk sudah tidak aktif.'
                        );
                    }

                    if ($quantity > (int)$item['stock']) {
                        throw new Exception(
                            'Jumlah melebihi stok yang tersedia.'
                        );
                    }

                    $update = $conn->prepare("
                        UPDATE cart_items
                        SET quantity = ?
                        WHERE id = ?
                          AND cart_id = ?
                    ");

                    $update->bind_param(
                        "iii",
                        $quantity,
                        $item_id,
                        $cart_id
                    );

                    $update->execute();

                    $message = 'Jumlah produk berhasil diperbarui.';

                // Menghapus produk dari keranjang.
                } elseif ($action === 'delete') {

                    $delete = $conn->prepare("
                        DELETE FROM cart_items
                        WHERE id = ?
                          AND cart_id = ?
                    ");

                    $delete->bind_param(
                        "ii",
                        $item_id,
                        $cart_id
                    );

                    $delete->execute();

                    $message = 'Produk berhasil dihapus dari keranjang.';

                } else {

                    throw new Exception(
                        'Aksi tidak dikenal.'
                    );
                }

                // Memperbarui waktu perubahan keranjang.
                $update_cart = $conn->prepare("
                    UPDATE carts
                    SET updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $update_cart->bind_param("i", $cart_id);
                $update_cart->execute();

            } catch (Throwable $e) {

                $error = $e->getMessage();
            }
        }
    }
}


// ==========================================
// MENGAMBIL DATA PRODUK DALAM KERANJANG
// ==========================================

$items = [];
$total = 0;

if ($cart_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.quantity,
            p.id AS product_id,
            p.product_name,
            p.price,
            p.stock,
            p.product_photo,
            p.status AS product_status,
            s.store_name
        FROM cart_items ci
        INNER JOIN products p
            ON ci.product_id = p.id
        INNER JOIN stores s
            ON p.store_id = s.id
        WHERE ci.cart_id = ?
        ORDER BY ci.created_at DESC
    ");

    $stmt->bind_param("i", $cart_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $row['subtotal'] =
            (float)$row['price'] * (int)$row['quantity'];

        $total += $row['subtotal'];

        $items[] = $row;
    }
}


// ==========================================
// MEMERIKSA KELAYAKAN CHECKOUT
// ==========================================

$ready = !empty($items);

foreach ($items as $item) {

    if (
        $item['product_status'] !== 'active' ||
        (int)$item['stock'] <= 0 ||
        (int)$item['stock'] < (int)$item['quantity']
    ) {
        $ready = false;
        break;
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Keranjang Belanja - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">

    <style>

        .cart-container {
            max-width: 1100px;
            margin: 50px auto;
            padding: 20px;
        }

        .cart-container h1 {
            color: #14532d;
            margin-bottom: 10px;
        }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 25px;
        }

        .cart-table th,
        .cart-table td {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .cart-table th {
            background: #eaf4e8;
            color: #14532d;
        }

        .cart-image {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
        }

        .quantity-input {
            width: 65px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .cart-button {
            padding: 9px 14px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .update-button {
            background: #198754;
            color: white;
        }

        .delete-button {
            background: #dc3545;
            color: white;
        }

        .total-box {
            background: #f1f8ef;
            padding: 25px;
            margin-top: 25px;
            border-radius: 12px;
            text-align: right;
        }

        .total-price {
            color: #198754;
            font-size: 25px;
            font-weight: bold;
        }

        .empty-cart {
            background: white;
            padding: 60px 20px;
            text-align: center;
            border-radius: 12px;
            margin-top: 25px;
        }

        .success-message {
            background: #d1e7dd;
            color: #0f5132;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .error-message {
            background: #f8d7da;
            color: #842029;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #198754;
        }

        .checkout-button {
            display: inline-block;
            background: #198754;
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 15px;
            transition: 0.2s;
        }

        .checkout-button:hover {
            background: #146c43;
        }

        @media(max-width: 700px) {

            .cart-container {
                padding: 12px;
            }

            .cart-table {
                font-size: 12px;
            }

            .cart-table th,
            .cart-table td {
                padding: 8px;
            }

            .cart-image {
                width: 45px;
                height: 45px;
            }

        }

    </style>

</head>

<body>

<div class="cart-container">

    <h1>Keranjang Belanja</h1>

    <p>
        Periksa produk pilihanmu sebelum melanjutkan pembelian.
    </p>

    <a href="/GreenCycle/pages/products.php"
       class="back-link">

        &larr; Kembali ke Katalog

    </a>


    <?php if ($message): ?>

        <div class="success-message">
            <?= e($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="error-message">
            <?= e($error) ?>
        </div>

    <?php endif; ?>


    <?php if (empty($items)): ?>

        <div class="empty-cart">

            <h2>Keranjang Masih Kosong</h2>

            <p>
                Kamu belum menambahkan produk ke keranjang.
            </p>

            <a href="/GreenCycle/pages/products.php">
                Mulai Belanja
            </a>

        </div>

    <?php else: ?>


        <table class="cart-table">

            <thead>

                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                    <th>Aksi</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($items as $item): ?>

                    <tr>

                        <td>

                            <?php if (!empty($item['product_photo'])): ?>

                                <img
                                    src="<?= e($item['product_photo']) ?>"
                                    class="cart-image"
                                    alt="<?= e($item['product_name']) ?>"
                                >

                            <?php endif; ?>

                            <br>

                            <strong>
                                <?= e($item['product_name']) ?>
                            </strong>

                            <br>

                            <small>
                                Toko: <?= e($item['store_name']) ?>
                            </small>

                            <?php if (
                                $item['product_status'] !== 'active'
                            ): ?>

                                <br>

                                <small style="color:red;">
                                    Produk tidak aktif
                                </small>

                            <?php elseif (
                                (int)$item['stock'] < (int)$item['quantity']
                            ): ?>

                                <br>

                                <small style="color:red;">
                                    Stok tidak mencukupi
                                </small>

                            <?php endif; ?>

                        </td>


                        <td>

                            Rp <?= number_format(
                                (float)$item['price'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </td>


                        <td>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['cart_csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="item_id"
                                    value="<?= (int)$item['cart_item_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="update"
                                >

                                <input
                                    type="number"
                                    name="quantity"
                                    class="quantity-input"
                                    min="1"
                                    max="<?= max(1, (int)$item['stock']) ?>"
                                    value="<?= (int)$item['quantity'] ?>"
                                    required
                                >

                                <button
                                    type="submit"
                                    class="cart-button update-button"
                                >
                                    Update
                                </button>

                            </form>

                        </td>


                        <td>

                            Rp <?= number_format(
                                $item['subtotal'],
                                0,
                                ',',
                                '.'
                            ) ?>

                        </td>


                        <td>

                            <form
                                method="POST"
                                onsubmit="return confirm('Hapus produk ini dari keranjang?')"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['cart_csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="item_id"
                                    value="<?= (int)$item['cart_item_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <button
                                    type="submit"
                                    class="cart-button delete-button"
                                >
                                    Hapus
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>


        <div class="total-box">

            <h3>Total Belanja</h3>

            <div class="total-price">

                Rp <?= number_format(
                    $total,
                    0,
                    ',',
                    '.'
                ) ?>

            </div>

            <p>
                Total <?= count($items) ?> jenis produk.
            </p>


            <?php if ($ready): ?>

                <a
                    href="/GreenCycle/pages/checkout.php"
                    class="checkout-button"
                >
                    Lanjut ke Checkout &rarr;
                </a>

            <?php else: ?>

                <p style="color:red;">
                    Ada produk yang tidak aktif atau stoknya
                    tidak mencukupi. Silakan periksa keranjang.
                </p>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>

</body>
</html>