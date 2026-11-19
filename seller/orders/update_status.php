<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['seller']);
$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare('SELECT id FROM stores WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$store) { header('Location: /GreenCycle/seller/dashboard.php'); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $transitions = ['pending' => ['processing', 'cancelled'], 'processing' => ['shipped']];
    if ($orderId > 0 && in_array($newStatus, ['processing', 'shipped', 'cancelled'], true)) {
        try {
            $conn->begin_transaction();
            $storeId = (int)$store['id'];
            $stmt = $conn->prepare('SELECT status FROM orders WHERE id = ? AND store_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $orderId, $storeId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$order || !in_array($newStatus, $transitions[$order['status']] ?? [], true)) {
                throw new RuntimeException('Perubahan status pesanan tidak diizinkan.');
            }
            if ($newStatus === 'cancelled') {
                $txCancel = $conn->prepare("UPDATE transactions SET payment_status = 'rejected' WHERE order_id = ? AND payment_status = 'pending'");
                $txCancel->bind_param('i', $orderId);
                $txCancel->execute();
                $txCancel->close();
                $restore = $conn->prepare('UPDATE products p INNER JOIN order_items oi ON oi.product_id = p.id SET p.stock = p.stock + oi.quantity WHERE oi.order_id = ?');
                $restore->bind_param('i', $orderId);
                $restore->execute();
                $restore->close();
            }
            $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ? AND store_id = ?');
            $stmt->bind_param('sii', $newStatus, $orderId, $storeId);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            $_SESSION['flash'] = 'Status pesanan berhasil diperbarui.';
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Error update order status: ' . $e->getMessage());
            $_SESSION['flash'] = $e instanceof RuntimeException ? $e->getMessage() : 'Gagal memperbarui status pesanan.';
        }
    }
}
header('Location: /GreenCycle/seller/orders/index.php');
exit;
