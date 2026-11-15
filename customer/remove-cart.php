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

$stmt = $conn->prepare(
    "DELETE ci FROM cart_items ci
     INNER JOIN carts c ON ci.cart_id = c.id
     WHERE ci.id = ? AND c.user_id = ?"
);

$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $_SESSION['flash'] = 'Produk berhasil dihapus dari keranjang.';
} else {
    $_SESSION['flash'] = 'Produk tidak ditemukan.';
}

header('Location: cart.php');
exit;