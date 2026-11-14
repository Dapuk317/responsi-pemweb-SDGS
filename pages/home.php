<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Customer - GreenCycle</title>
</head>
<body>

    <h1>Selamat Datang di GreenCycle!</h1>

    <p>
        Halo, <?= e($_SESSION['name']) ?>.
    </p>

    <p>
        Kamu berhasil login sebagai customer.
    </p>

    <p>
        Email: <?= e($_SESSION['email']) ?>
    </p>

    <p>
        Role: <?= e($_SESSION['role']) ?>
    </p>

    <hr>

    <h3>Menu Customer</h3>

    <p>Fitur katalog, keranjang, dan pesanan akan dikembangkan berikutnya.</p>

    <form action="/GreenCycle/auth/logout.php" method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= e(csrf_token()) ?>">
        <button type="submit">Logout</button>
    </form>

</body>
</html>
