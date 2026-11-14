<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

verify_csrf();

$store_id = filter_input(
    INPUT_POST,
    'store_id',
    FILTER_VALIDATE_INT
);

$status = $_POST['status'] ?? '';

if (!$store_id || !in_array($status, ['active', 'inactive'], true)) {
    $_SESSION['flash'] = 'Data permintaan tidak valid.';
    header('Location: index.php');
    exit;
}

try {
    $stmt = $conn->prepare(
        "UPDATE stores SET status = ? WHERE id = ?"
    );

    $stmt->bind_param("si", $status, $store_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['flash'] = 'Status toko berhasil diperbarui.';
    } else {
        // Periksa apakah toko memang ada atau statusnya sudah sama.
        $check = $conn->prepare(
            "SELECT id FROM stores WHERE id = ? LIMIT 1"
        );

        $check->bind_param("i", $store_id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $_SESSION['flash'] = 'Status toko tidak berubah karena sudah sesuai.';
        } else {
            $_SESSION['flash'] = 'Toko tidak ditemukan.';
        }
    }

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    $_SESSION['flash'] = 'Gagal memperbarui status toko.';
}

header('Location: index.php');
exit;