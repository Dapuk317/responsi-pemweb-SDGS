<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

verify_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION['flash'] = 'ID kategori tidak valid.';
    header('Location: index.php');
    exit;
}

try {
    $stmt = $conn->prepare(
        "DELETE FROM categories WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['flash'] = 'Kategori berhasil dihapus.';
    } else {
        $_SESSION['flash'] = 'Kategori tidak ditemukan.';
    }

} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1451) {
        $_SESSION['flash'] =
            'Kategori tidak bisa dihapus karena masih digunakan oleh produk.';
    } else {
        error_log($e->getMessage());
        $_SESSION['flash'] = 'Gagal menghapus kategori.';
    }
}

header('Location: index.php');
exit;
