<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Mencegah output HTML berbahaya.
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Membuat token CSRF.
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Memeriksa token CSRF.
 */
function verify_csrf()
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Permintaan tidak valid. Silakan muat ulang halaman.');
    }
}

/**
 * Mengarahkan pengguna ke halaman berdasarkan role.
 */
function redirect_by_role()
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header('Location: /GreenCycle/auth/login.php');
        exit;
    }

    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: /GreenCycle/admin/dashboard.php');
            exit;

        case 'seller':
            header('Location: /GreenCycle/seller/dashboard.php');
            exit;

        case 'customer':
            header('Location: /GreenCycle/pages/home.php');
            exit;

        default:
            session_unset();
            session_destroy();
            header('Location: /GreenCycle/auth/login.php');
            exit;
    }
}