<?php
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(['seller']);

$userId = (int)$_SESSION['user_id'];

// Ambil info toko seller
$stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$store) {
    header('Location: /GreenCycle/seller/dashboard.php');
    exit;
}

$storeId = (int)$store['id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $productId = (int)($_POST['id'] ?? 0);

    if ($productId > 0) {
        try {
            // Coba hapus langsung
            $del = $conn->prepare("DELETE FROM products WHERE id = ? AND store_id = ?");
            $del->bind_param("ii", $productId, $storeId);
            $del->execute();
            $del->close();

            $_SESSION['flash'] = 'Produk berhasil dihapus dari katalog.';
        } catch (mysqli_sql_exception $e) {
            // Jika ada riwayat transaksi yang mengunci, ubah status menjadi inactive
            $update = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ? AND store_id = ?");
            $update->bind_param("ii", $productId, $storeId);
            $update->execute();
            $update->close();

            $_SESSION['flash'] = 'Produk memiliki riwayat transaksi, status produk dinonaktifkan (arsip).';
        }
    }
}

header('Location: /GreenCycle/seller/products/index.php');
exit;
