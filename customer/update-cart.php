<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

verify_csrf();

$user_id = (int) $_SESSION['user_id'];
$item_id = (int) ($_POST['item_id'] ?? 0);
$quantity = (int) ($_POST['quantity'] ?? 0);

try {
    if ($item_id < 1 || $quantity < 1) {
        throw new Exception('Jumlah produk tidak valid.');
    }

    $stmt = $conn->prepare(
        "SELECT ci.id, p.stock
         FROM cart_items ci
         INNER JOIN carts c ON ci.cart_id = c.id
         INNER JOIN products p ON ci.product_id = p.id
         WHERE ci.id = ? AND c.user_id = ?
         LIMIT 1"
    );

    $stmt->bind_param("ii", $item_id, $user_id);
    $stmt->execute();

    $item = $stmt->get_result()->fetch_assoc();

    if (!$item) {
        throw new Exception('Item keranjang tidak ditemukan.');
    }

    if ($quantity > (int) $item['stock']) {
        throw new Exception('Jumlah melebihi stok tersedia.');
    }

    $stmt = $conn->prepare(
        "UPDATE cart_items
         SET quantity = ?
         WHERE id = ?"
    );

    $stmt->bind_param("ii", $quantity, $item_id);
    $stmt->execute();

    $_SESSION['flash'] = 'Jumlah produk berhasil diperbarui.';

} catch (Throwable $e) {
    $_SESSION['flash'] = $e->getMessage();
}

header('Location: cart.php');
exit;