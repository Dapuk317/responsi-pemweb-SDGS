<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

verify_csrf();

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';

if (!$user_id || !in_array($status, ['active', 'inactive'], true)) {
    $_SESSION['flash'] = 'Data permintaan tidak valid.';
    header('Location: index.php');
    exit;
}

// Admin tidak boleh menonaktifkan akun sendiri.
if ($user_id === (int)$_SESSION['user_id']) {
    $_SESSION['flash'] = 'Kamu tidak dapat mengubah status akun sendiri.';
    header('Location: index.php');
    exit;
}

try {
    // Pastikan pengguna yang dituju memang tersedia.
    $check = $conn->prepare(
        "SELECT id FROM users WHERE id = ? LIMIT 1"
    );

    $check->bind_param("i", $user_id);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        $_SESSION['flash'] = 'Pengguna tidak ditemukan.';
    } else {
        $update = $conn->prepare(
            "UPDATE users SET status = ? WHERE id = ?"
        );

        $update->bind_param("si", $status, $user_id);
        $update->execute();

        $_SESSION['flash'] = 'Status pengguna berhasil diperbarui.';
    }

} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    $_SESSION['flash'] = 'Terjadi kesalahan saat memperbarui status.';
}

header('Location: index.php');
exit;