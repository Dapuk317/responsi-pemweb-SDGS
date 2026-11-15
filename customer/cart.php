<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$user_id = (int) $_SESSION['user_id'];

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Mengambil keranjang pengguna.
$stmt = $conn->prepare(
    "SELECT id FROM carts
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$cart = $stmt->get_result()->fetch_assoc();

$items = [];
$total = 0;

if ($cart) {
    $cart_id = (int) $cart['id'];

    $stmt = $conn->prepare(
        "SELECT
            ci.id AS item_id,
            ci.quantity,
            p.id AS product_id,
            p.product_name,
            p.price,
            p.stock,
            p.product_photo,
            p.status
         FROM cart_items ci
         INNER JOIN products p
            ON ci.product_id = p.id
         WHERE ci.cart_id = ?
         ORDER BY ci.id DESC"
    );

    $stmt->bind_param("i", $cart_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($item = $result->fetch_assoc()) {
        $items[] = $item;
        $total += (float) $item['price'] * (int) $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Keranjang Belanja - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">
</head>
<body>

<main style="max-width:1000px;margin:40px auto;padding:20px;">

    <h1>Keranjang Belanja</h1>

    <p>
        <a href="/GreenCycle/pages/products.php">
            Kembali ke Katalog Produk
        </a>
    </p>

    <?php if ($flash): ?>
        <p style="color:green;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>

    <?php if (empty($items)): ?>

        <h3>Keranjang masih kosong</h3>

        <p>
            Silakan pilih produk terlebih dahulu.
        </p>

        <a href="/GreenCycle/pages/products.php">
            Mulai Belanja
        </a>

    <?php else: ?>

        <table border="1"
               cellpadding="12"
               cellspacing="0"
               width="100%">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($items as $index => $item): ?>

                <tr>
                    <td><?= $index + 1 ?></td>

                    <td>
                        <?= e($item['product_name']) ?>
                    </td>

                    <td>
                        Rp <?= number_format(
                            (float) $item['price'],
                            0,
                            ',',
                            '.'
                        ) ?>
                    </td>

                    <td>
                        <form action="update-cart.php"
                              method="POST">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= e(csrf_token()) ?>">

                            <input type="hidden"
                                   name="item_id"
                                   value="<?= (int) $item['item_id'] ?>">

                            <input type="number"
                                   name="quantity"
                                   value="<?= (int) $item['quantity'] ?>"
                                   min="1"
                                   max="<?= (int) $item['stock'] ?>"
                                   required>

                            <button type="submit">
                                Perbarui
                            </button>
                        </form>
                    </td>

                    <td>
                        Rp <?= number_format(
                            (float) $item['price'] *
                            (int) $item['quantity'],
                            0,
                            ',',
                            '.'
                        ) ?>
                    </td>

                    <td>
                        <?php if (
                            $item['status'] === 'active' &&
                            $item['stock'] >= $item['quantity']
                        ): ?>
                            Tersedia
                        <?php else: ?>
                            Tidak tersedia
                        <?php endif; ?>
                    </td>

                    <td>
                        <form action="remove-cart.php"
                              method="POST"
                              onsubmit="return confirm('Hapus produk dari keranjang?')">

                            <input type="hidden"
                                   name="csrf_token"
                                   value="<?= e(csrf_token()) ?>">

                            <input type="hidden"
                                   name="item_id"
                                   value="<?= (int) $item['item_id'] ?>">

                            <button type="submit">
                                Hapus
                            </button>
                        </form>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <h2>
            Total Belanja:
            Rp <?= number_format($total, 0, ',', '.') ?>
        </h2>

        <?php
        $ready = true;

        foreach ($items as $item) {
            if (
                $item['status'] !== 'active' ||
                $item['stock'] < $item['quantity']
            ) {
                $ready = false;
                break;
            }
        }
        ?>

        <?php if ($ready): ?>
            <p>
                <a href="/GreenCycle/pages/checkout.php">
                    Lanjut ke Checkout
                </a>
            </p>
        <?php else: ?>
            <p style="color:red;">
                Periksa produk yang tidak tersedia sebelum checkout.
            </p>
        <?php endif; ?>

    <?php endif; ?>

</main>

</body>
</html>