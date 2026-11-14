<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Hanya seller yang boleh masuk.
require_role(['seller']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Seller - GreenCycle</title>
</head>
<body>

    <h1>Dashboard Seller GreenCycle</h1>

    <h2>
        Selamat datang, <?= e($_SESSION['name']) ?>!
    </h2>

    <p>Kamu berhasil login sebagai seller.</p>

    <hr>

    <h3>Informasi Akun</h3>

    <p>Nama: <?= e($_SESSION['name']) ?></p>
    <p>Email: <?= e($_SESSION['email']) ?></p>
    <p>Role: <?= e($_SESSION['role']) ?></p>

    <hr>

    <h3>Menu Seller</h3>
    <ul>
        <li><a href="/GreenCycle/seller/store/index.php">Kelola Toko Saya</a></li>
        <li>Tambah Produk</li>
        <li>Kelola Produk</li>
        <li>Kelola Pesanan</li>
        <li>Laporan Penjualan</li>
    </ul>

    <p>Fitur-fitur tersebut akan kita kembangkan pada tahap berikutnya.</p>

    <hr>

    <form action="/GreenCycle/auth/logout.php" method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= e(csrf_token()) ?>">
        <button type="submit">Logout</button>
    </form>

</body>
</html>
