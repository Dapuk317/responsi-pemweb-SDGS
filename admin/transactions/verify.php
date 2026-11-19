<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$redirect = '/GreenCycle/admin/transactions/index.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

verify_csrf();

$txId = filter_input(INPUT_POST, 'tx_id', FILTER_VALIDATE_INT) ?: 0;
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
$action = $_POST['action'] ?? '';

if ($txId < 1 || $orderId < 1 || !in_array($action, ['verify', 'reject'], true)) {
    $_SESSION['flash'] = 'Permintaan verifikasi tidak valid.';
    header('Location: ' . $redirect);
    exit;
}

try {
    $conn->begin_transaction();

    // Kunci transaksi dan pesanan agar tidak diproses bersamaan.
    $stmt = $conn->prepare(
        "SELECT id, order_id, payment_status, payment_proof
         FROM transactions
         WHERE id = ? AND order_id = ?
         LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('ii', $txId, $orderId);
    $stmt->execute();
    $transaction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare(
        "SELECT id, status FROM orders WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$transaction || !$order) {
        throw new RuntimeException('Transaksi atau pesanan tidak ditemukan. Muat ulang halaman dan coba lagi.');
    }
    if ($transaction['payment_status'] !== 'pending') {
        throw new RuntimeException('Pembayaran ini sudah pernah diproses.');
    }

    if ($action === 'verify') {
        if (empty($transaction['payment_proof'])) {
            throw new RuntimeException('Bukti pembayaran belum diunggah oleh customer.');
        }
        if (!in_array($order['status'], ['pending', 'processing'], true)) {
            throw new RuntimeException('Pesanan tidak dapat diverifikasi karena statusnya ' . $order['status'] . '.');
        }

        $stmt = $conn->prepare(
            "UPDATE transactions
             SET payment_status = 'verified', verified_at = NOW()
             WHERE id = ? AND order_id = ? AND payment_status = 'pending'"
        );
        $stmt->bind_param('ii', $txId, $orderId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Status pembayaran gagal diperbarui.');
        }
        $stmt->close();

        // Pesanan yang masih pending diteruskan ke seller.
        // Jika sudah processing, jangan menggagalkan verifikasi pembayaran.
        if ($order['status'] === 'pending') {
            $stmt = $conn->prepare(
                "UPDATE orders SET status = 'processing' WHERE id = ? AND status = 'pending'"
            );
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('Status pesanan gagal diperbarui.');
            }
            $stmt->close();
        }

        $_SESSION['flash'] = 'Pembayaran berhasil diverifikasi.';
    } else {
        // Penolakan hanya diperbolehkan sebelum pesanan diproses seller.
        if ($order['status'] !== 'pending') {
            throw new RuntimeException('Pesanan sudah diproses sehingga pembayaran tidak dapat ditolak dari halaman ini.');
        }

        $stmt = $conn->prepare(
            "UPDATE transactions
             SET payment_status = 'rejected'
             WHERE id = ? AND order_id = ? AND payment_status = 'pending'"
        );
        $stmt->bind_param('ii', $txId, $orderId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Status pembayaran gagal diperbarui.');
        }
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE orders SET status = 'cancelled' WHERE id = ? AND status = 'pending'"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('Pesanan gagal dibatalkan.');
        }
        $stmt->close();

        // Kembalikan stok hanya ketika pesanan benar-benar dibatalkan.
        $stmt = $conn->prepare(
            "UPDATE products p
             INNER JOIN order_items oi ON oi.product_id = p.id
             SET p.stock = p.stock + oi.quantity
             WHERE oi.order_id = ?"
        );
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();

        $_SESSION['flash'] = 'Pembayaran ditolak, pesanan dibatalkan, dan stok dikembalikan.';
    }

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // Abaikan jika transaksi database sudah tidak aktif.
    }
    error_log('Admin payment verification error: ' . $e->getMessage());
    $_SESSION['flash'] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Terjadi kesalahan database saat memproses pembayaran. Periksa log PHP/MySQL.';
}

header('Location: ' . $redirect);
exit;
