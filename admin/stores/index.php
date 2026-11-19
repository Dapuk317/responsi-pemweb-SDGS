<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$adminName = $_SESSION['name'] ?? 'Admin';
$keyword = trim($_GET['q'] ?? '');
$search = '%' . $keyword . '%';

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.store_name,
        s.description,
        s.address,
        s.phone,
        s.status,
        s.created_at,
        u.name AS owner_name,
        u.email AS owner_email
    FROM stores s
    INNER JOIN users u ON s.user_id = u.id
    WHERE s.store_name LIKE ?
       OR u.name LIKE ?
       OR u.email LIKE ?
    ORDER BY s.id DESC
");
$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();
$stores = $stmt->get_result();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Toko Mitra - GreenCycle Admin</title>
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
            <div class="gc-nav-group-title">Utama</div>
            <a href="/GreenCycle/admin/dashboard.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard Overview
            </a>

            <div class="gc-nav-group-title">Transaksi & Pembayaran</div>
            <a href="/GreenCycle/admin/transactions/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                Verifikasi Pembayaran
            </a>
            <a href="/GreenCycle/admin/orders/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Monitoring Pesanan
            </a>

            <div class="gc-nav-group-title">Kelola Marketplace</div>
            <a href="/GreenCycle/admin/users/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                Kelola Pengguna
            </a>
            <a href="/GreenCycle/admin/stores/index.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                Kelola Toko Mitra
            </a>
            <a href="/GreenCycle/admin/categories/index.php" class="gc-nav-item">
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
                    <span>Kelola Toko Mitra Penjual</span>
                </div>
            </div>
            <div class="gc-topbar-right">
                <div class="gc-topbar-clock" id="gcLiveClock"></div>
            </div>
        </header>

        <div class="gc-content">
            <?php if ($flash): ?>
                <div class="gc-alert gc-alert-success">
                    <span><?= e($flash) ?></span>
                    <button type="button" class="gc-alert-close">&times;</button>
                </div>
            <?php endif; ?>

            <div class="gc-panel">
                <div class="gc-panel-header">
                    <div class="gc-panel-title">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                        Daftar Toko Mitra Daur Ulang (<?= $stores->num_rows ?>)
                    </div>
                </div>

                <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light);" class="gc-filter-bar">
                    <form method="GET" style="display: flex; gap: 10px; width: 100%; max-width: 500px;">
                        <div class="gc-search-box" style="flex: 1;">
                            <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" name="q" placeholder="Cari nama toko atau pemilik..." value="<?= e($keyword) ?>">
                        </div>
                        <button type="submit" class="gc-btn gc-btn-primary gc-btn-sm">Cari</button>
                        <?php if ($keyword): ?>
                            <a href="index.php" class="gc-btn gc-btn-secondary gc-btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="gc-table-responsive">
                    <table class="gc-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Toko</th>
                                <th>Pemilik Toko</th>
                                <th>Email</th>
                                <th>No. Telepon</th>
                                <th>Alamat Operasional</th>
                                <th>Status</th>
                                <th>Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stores->num_rows === 0): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--gc-text-muted);">
                                        Belum ada toko yang cocok dengan pencarian.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; ?>
                                <?php while ($st = $stores->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><strong><?= e($st['store_name']) ?></strong></td>
                                        <td><?= e($st['owner_name']) ?></td>
                                        <td><?= e($st['owner_email']) ?></td>
                                        <td><?= e($st['phone'] ?? '-') ?></td>
                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= e($st['address'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <span class="gc-badge badge-<?= e($st['status']) ?>">
                                                <?= e($st['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form action="update_status.php" method="POST" onsubmit="return confirm('Ubah status operasional toko ini?');" style="margin: 0;">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="store_id" value="<?= (int)$st['id'] ?>">
                                                <?php if ($st['status'] === 'active'): ?>
                                                    <input type="hidden" name="status" value="inactive">
                                                    <button type="submit" class="gc-btn gc-btn-sm" style="background: rgba(220, 38, 38, 0.1); color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.2);">
                                                        Nonaktifkan
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" class="gc-btn gc-btn-sm gc-btn-primary">
                                                        Aktifkan
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
