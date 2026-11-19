<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$userId = (int)$_SESSION['user_id'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        $_SESSION['flash_error'] = "Nama lengkap wajib diisi.";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE users
                SET name = ?, phone = ?, address = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssi", $name, $phone, $address, $userId);
            $stmt->execute();
            $stmt->close();

            $_SESSION['name'] = $name;
            $_SESSION['flash'] = "Informasi profil dan alamat pengiriman berhasil diperbarui!";
        } catch (mysqli_sql_exception $e) {
            error_log("Error update customer profile: " . $e->getMessage());
            $_SESSION['flash_error'] = "Gagal memperbarui profil pengguna.";
        }
    }
}

header('Location: /GreenCycle/customer/dashboard.php');
exit;
