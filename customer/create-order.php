<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

// Mengarahkan ke modul checkout utama marketplace
header('Location: /GreenCycle/pages/checkout.php');
exit;
