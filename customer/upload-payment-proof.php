<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: /GreenCycle/pages/order-history.php');
    exit;
}
verify_csrf();
$userId = (int)$_SESSION['user_id'];
$orderCode = trim($_POST['order_code'] ?? '');

if ($orderCode === '' || empty($_FILES['payment_proof'])) {
    $_SESSION['flash_error'] = 'Pilih bukti pembayaran terlebih dahulu.';
    header('Location: /GreenCycle/pages/order-history.php');
    exit;
}
$file = $_FILES['payment_proof'];
if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > 3 * 1024 * 1024) {
    $_SESSION['flash_error'] = 'File gagal diunggah atau ukurannya melebihi 3MB.';
    header('Location: /GreenCycle/pages/payment.php?code=' . urlencode($orderCode));
    exit;
}
$mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($mimeMap[$mime])) {
    $_SESSION['flash_error'] = 'Bukti pembayaran harus berupa JPG, PNG, atau WEBP.';
    header('Location: /GreenCycle/pages/payment.php?code=' . urlencode($orderCode));
    exit;
}

try {
    $stmt = $conn->prepare("SELECT o.id, t.id AS transaction_id, t.payment_status FROM orders o INNER JOIN transactions t ON t.order_id = o.id WHERE o.order_code = ? AND o.user_id = ? LIMIT 1");
    $stmt->bind_param('si', $orderCode, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order || $order['payment_status'] !== 'pending') {
        throw new RuntimeException('Pesanan tidak ditemukan atau pembayaran sudah diproses.');
    }

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Folder penyimpanan bukti tidak tersedia.');
    }
    $fileName = 'proof_' . $order['id'] . '_' . bin2hex(random_bytes(12)) . '.' . $mimeMap[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        throw new RuntimeException('Bukti pembayaran gagal disimpan.');
    }

    $update = $conn->prepare("UPDATE transactions SET payment_proof = ?, payment_date = NOW() WHERE id = ? AND payment_status = 'pending'");
    $update->bind_param('si', $fileName, $order['transaction_id']);
    $update->execute();
    if ($update->affected_rows !== 1) {
        @unlink($uploadDir . $fileName);
        throw new RuntimeException('Bukti pembayaran tidak dapat diperbarui.');
    }
    $update->close();
    $_SESSION['flash'] = 'Bukti pembayaran berhasil diunggah dan menunggu verifikasi admin.';
} catch (Throwable $e) {
    error_log('Payment proof upload: ' . $e->getMessage());
    $_SESSION['flash_error'] = $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan saat mengunggah bukti pembayaran.';
}
header('Location: /GreenCycle/pages/payment.php?code=' . urlencode($orderCode));
exit;
