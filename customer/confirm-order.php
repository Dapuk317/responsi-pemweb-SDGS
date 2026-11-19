<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$userId = (int)$_SESSION['user_id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($orderId > 0) {
        try {
            $stmt = $conn->prepare("
                UPDATE orders
                SET status = 'completed'
                WHERE id = ? AND user_id = ? AND status = 'shipped'
            ");
            $stmt->bind_param("ii", $orderId, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['flash'] = "Terima kasih! Pesanan telah selesai dan Anda berkontribusi menyelamatkan barang daur ulang.";
        } catch (mysqli_sql_exception $e) {
            error_log("Error confirm order: " . $e->getMessage());
            $_SESSION['flash'] = "Gagal mengonfirmasi pesanan.";
        }
    }
}

$redirect = $_POST['redirect_url'] ?? '/GreenCycle/customer/dashboard.php';
$allowedRedirects = ['/GreenCycle/customer/dashboard.php', '/GreenCycle/pages/order-history.php'];
if (is_string($redirect) && preg_match('#^/GreenCycle/pages/order-detail\.php\?id=[0-9]+$#', $redirect)) $allowedRedirects[] = $redirect;
if (!in_array($redirect, $allowedRedirects, true)) $redirect = '/GreenCycle/customer/dashboard.php';
header('Location: ' . $redirect);
exit;
