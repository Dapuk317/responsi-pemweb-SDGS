<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /GreenCycle/pages/products.php');
    exit;
}

verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$product_id = (int) ($_POST['product_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));

try {
    $conn->begin_transaction();

    // Periksa produk.
    $stmt = $conn->prepare(
        "SELECT id, stock, status
         FROM products
         WHERE id = ?
         LIMIT 1"
    );
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $product = $stmt->get_result()->fetch_assoc();

    if (!$product || $product['status'] !== 'active') {
        throw new Exception('Produk tidak tersedia.');
    }

    if ($product['stock'] < 1) {
        throw new Exception('Stok produk habis.');
    }

    if ($quantity > $product['stock']) {
        throw new Exception('Jumlah melebihi stok produk.');
    }

    // Cari keranjang milik pengguna.
    $stmt = $conn->prepare(
        "SELECT id FROM carts
         WHERE user_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $cart = $stmt->get_result()->fetch_assoc();

    // Buat keranjang jika belum ada.
    if (!$cart) {
        $stmt = $conn->prepare(
            "INSERT INTO carts (user_id)
             VALUES (?)"
        );
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $cart_id = $conn->insert_id;
    } else {
        $cart_id = (int) $cart['id'];
    }

    // Periksa apakah produk sudah ada di keranjang.
    $stmt = $conn->prepare(
        "SELECT id, quantity FROM cart_items
         WHERE cart_id = ? AND product_id = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $cart_id, $product_id);
    $stmt->execute();

    $item = $stmt->get_result()->fetch_assoc();

    if ($item) {
        $new_quantity = (int) $item['quantity'] + $quantity;

        if ($new_quantity > (int) $product['stock']) {
            throw new Exception('Jumlah produk melebihi stok yang tersedia.');
        }

        $stmt = $conn->prepare(
            "UPDATE cart_items
             SET quantity = ?
             WHERE id = ? AND cart_id = ?"
        );
        $stmt->bind_param(
            "iii",
            $new_quantity,
            $item['id'],
            $cart_id
        );
        $stmt->execute();

    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cart_items
             (cart_id, product_id, quantity)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param(
            "iii",
            $cart_id,
            $product_id,
            $quantity
        );
        $stmt->execute();
    }

    $conn->commit();

    $_SESSION['flash'] = 'Produk berhasil ditambahkan ke keranjang.';

} catch (Throwable $e) {
    $conn->rollback();

    $_SESSION['flash'] = $e->getMessage();
}

header('Location: /GreenCycle/customer/cart.php');
exit;