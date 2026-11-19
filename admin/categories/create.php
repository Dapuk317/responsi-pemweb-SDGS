<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$adminName = $_SESSION['name'] ?? 'Admin';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($category_name === '') {
        $error = 'Nama kategori wajib diisi.';
    } elseif (mb_strlen($category_name) > 100) {
        $error = 'Nama kategori maksimal 100 karakter.';
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO categories (category_name, description)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ss", $category_name, $description);
            $stmt->execute();
            $stmt->close();

            $_SESSION['flash'] = 'Kategori berhasil ditambahkan.';
            header('Location: index.php');
            exit;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $error = 'Nama kategori tersebut sudah ada dalam sistem.';
            } else {
                error_log($e->getMessage());
                $error = 'Gagal menambahkan kategori produk.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kategori - GreenCycle Admin</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
</head>
<body>

<div class="gc-app">
    <div class="gc-sidebar-backdrop" id="gcSidebarBackdrop"></div>

    <aside class="gc-sidebar" id="gcSidebar">
        <div class="gc-sidebar-header">
            <!-- LOGO MARKETPLACE - TIDAK DIUBAH -->
            <a href="/GreenCycle/admin/dashboard.php" class="gc-sidebar-logo">
                <img
                    src="/GreenCycle/assets/images/greencycle-logo.png"
                    alt="GreenCycle Logo"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';"
                >
                <span class="brand-fallback" style="display:none; font-size:20px; font-weight:800; color:#fff;">
                    Green<span style="color:var(--gc-accent);">Cycle</span>
                </span>
            </a>
            <button class="gc-sidebar-close" id="gcSidebarClose">&times;</button>
        </div>

        <div class="gc-sidebar-role-badge">
            <div class="gc-role-avatar">AD</div>
            <div class="gc-role-info">
                <div class="gc-role-name"><?= e($adminName) ?></div>
                <div class="gc-role-tag">Super Administrator</div>
            </div>
        </div>

        <nav class="gc-sidebar-nav">
            <a href="/GreenCycle/admin/dashboard.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard Overview
            </a>
            <a href="/GreenCycle/admin/transactions/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                Verifikasi Pembayaran
            </a>
            <a href="/GreenCycle/admin/orders/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Monitoring Pesanan
            </a>
            <a href="/GreenCycle/admin/users/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Kelola Pengguna
            </a>
            <a href="/GreenCycle/admin/stores/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                Kelola Toko Mitra
            </a>
            <a href="/GreenCycle/admin/categories/index.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                Kelola Kategori
            </a>
        </nav>

        <div class="gc-sidebar-footer">
            <form action="/GreenCycle/auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="gc-btn-logout-sidebar">Logout Admin</button>
            </form>
        </div>
    </aside>

    <main class="gc-main">
        <header class="gc-topbar">
            <div class="gc-topbar-left">
                <button class="gc-menu-toggle" id="gcMenuToggle">☰</button>
                <div class="gc-topbar-title">
                    <span>Tambah Kategori Produk</span>
                </div>
            </div>
            <div class="gc-topbar-right">
                <a href="index.php" class="gc-btn gc-btn-secondary gc-btn-sm">&larr; Kembali</a>
            </div>
        </header>

        <div class="gc-content">
            <?php if ($error): ?>
                <div class="gc-alert gc-alert-error">
                    <span><?= e($error) ?></span>
                    <button type="button" class="gc-alert-close">&times;</button>
                </div>
            <?php endif; ?>

            <div class="gc-panel" style="max-width: 600px; margin: 0 auto;">
                <div class="gc-panel-header">
                    <div class="gc-panel-title">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                        Formulir Kategori Baru
                    </div>
                </div>

                <div class="gc-panel-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="catName">Nama Kategori *</label>
                            <input type="text" id="catName" name="category_name" class="gc-input" style="padding-left: 14px;" placeholder="Contoh: Kerajinan Tekstil Bekas" required value="<?= e($_POST['category_name'] ?? '') ?>">
                        </div>

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="catDesc">Deskripsi Kategori</label>
                            <textarea id="catDesc" name="description" class="gc-input" style="padding: 10px 14px; height: 90px; resize: vertical;" placeholder="Penjelasan tentang jenis barang daur ulang dalam kategori ini..."><?= e($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                            <a href="index.php" class="gc-btn gc-btn-secondary">Batal</a>
                            <button type="submit" class="gc-btn gc-btn-primary">Simpan Kategori</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
