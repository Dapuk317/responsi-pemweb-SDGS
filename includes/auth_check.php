<?php
require_once __DIR__ . '/init.php';

function require_login()
{
    global $conn;

    if (empty($_SESSION['user_id'])) {
        header('Location: /GreenCycle/auth/login.php');
        exit;
    }

    // Periksa kembali status akun di database.
    $stmt = $conn->prepare(
        "SELECT status FROM users WHERE id = ? LIMIT 1"
    );

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();

        header('Location: /GreenCycle/auth/login.php');
        exit;
    }
}

function require_role($allowed_roles)
{
    require_login();

    if (!in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
        http_response_code(403);
        exit('403 - Kamu tidak memiliki akses ke halaman ini.');
    }
}
