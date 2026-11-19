<?php
require_once __DIR__ . '/includes/init.php';

if (!empty($_SESSION['user_id'])) {
    redirect_by_role();
} else {
    header('Location: /GreenCycle/auth/login.php');
    exit;
}
