<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Hanya admin yang boleh masuk.
require_role(['admin']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - GreenCycle</title>
</head>
<body>

    <h1>Dashboard Admin GreenCycle</h1>

    <h2>
        Selamat datang, <?= e($_SESSION['name']) ?>!
    </h2>

    <p>Kamu berhasil login sebagai administrator.</p>

    <hr>

    <h3>Informasi Admin</h3>

    <p>Nama: <?= e($_SESSION['name']) ?></p>
    <p>Email: <?= e($_SESSION['email']) ?></p>
    <p>Role: <?= e($_SESSION['role']) ?></p>

    <hr>

    <h3>Menu Administrator</h3>

    <ul>
        <li>Dashboard Statistik</li>
        <li><a href="/GreenCycle/admin/users/index.php">Kelola Pengguna</a></li>
        <li>Kelola Seller</li>
        <li><a href="/GreenCycle/admin/stores/index.php">Kelola Toko</a></li>
        <li><a href="/GreenCycle/admin/categories/index.php">Kelola Kategori</a></li>
        <li>Kelola Produk</li>
        <li>Kelola Pesanan</li>
        <li>Verifikasi Pembayaran</li>
        <li>Kelola Ulasan</li>
    </ul>

    <p>Fitur administrasi akan kita kembangkan pada tahap berikutnya.</p>

    <hr>

    <form action="/GreenCycle/auth/logout.php" method="POST">
        <input type="hidden" name="csrf_token"
               value="<?= e(csrf_token()) ?>">
        <button type="submit">Logout</button>
    </form>

</body>
</html>