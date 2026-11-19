<?php
require_once __DIR__ . '/../../includes/auth_check.php';

// Hanya seller yang boleh masuk
require_role(['seller']);

$userId = (int)$_SESSION['user_id'];
$sellerName = $_SESSION['name'] ?? 'Penjual';

// Ambil info toko
$stmt = $conn->prepare("SELECT id, store_name, status FROM stores WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

$storeId = $store ? (int)$store['id'] : 0;
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$products = [];
if ($storeId > 0) {
    $pStmt = $conn->prepare("
        SELECT 
            p.id,
            p.product_name,
            p.material_info,
            p.price,
            p.stock,
            p.product_photo,
            p.status,
            p.created_at,
            c.category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.store_id = ?
        ORDER BY p.id DESC
    ");
    $pStmt->bind_param("i", $storeId);
    $pStmt->execute();
    $products = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pStmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk Toko - <?= e($store['store_name'] ?? 'Seller') ?> | GreenCycle</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
</head>
<body>

<div class="gc-app">
    <!-- Mobile Backdrop -->
    <div class="gc-sidebar-backdrop" id="gcSidebarBackdrop"></div>

    <!-- SIDEBAR SELLER -->
    <aside class="gc-sidebar" id="gcSidebar">
        <div class="gc-sidebar-header">
            <!-- LOGO MARKETPLACE - TIDAK DIUBAH -->
            <a href="/GreenCycle/seller/dashboard.php" class="gc-sidebar-logo">
                <img
                    src="/GreenCycle/assets/images/greencycle-logo.png"
                    alt="GreenCycle Logo"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';"
                >
                <span class="brand-fallback" style="display:none; font-size:20px; font-weight:800; color:#fff;">
                    Green<span style="color:var(--gc-accent);">Cycle</span>
                </span>
            </a>
            <button class="gc-sidebar-close" id="gcSidebarClose" aria-label="Tutup Menu">&times;</button>
        </div>

        <div class="gc-sidebar-role-badge">
            <div class="gc-role-avatar" style="background: linear-gradient(135deg, #10b981, #047857);">
                <?= strtoupper(substr($store['store_name'] ?? $sellerName, 0, 2)) ?>
            </div>
            <div class="gc-role-info">
                <div class="gc-role-name"><?= e($store['store_name'] ?? $sellerName) ?></div>
                <div class="gc-role-tag" style="color: #34d399;">Mitra Seller Daur Ulang</div>
            </div>
        </div>

        <nav class="gc-sidebar-nav">
            <div class="gc-nav-group-title">Menu Toko</div>
            <a href="/GreenCycle/seller/dashboard.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard Toko
            </a>

            <a href="/GreenCycle/seller/products/index.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                Produk Daur Ulang
                <span class="gc-nav-badge"><?= count($products) ?></span>
            </a>

            <a href="/GreenCycle/seller/orders/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Pesanan Masuk
            </a>

            <a href="/GreenCycle/seller/store/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                Pengaturan Toko
            </a>

            <div class="gc-nav-group-title">Marketplace</div>
            <a href="/GreenCycle/pages/home.php" class="gc-nav-item" target="_blank">
                <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                Lihat Marketplace
            </a>
        </nav>

        <div class="gc-sidebar-footer">
            <form action="/GreenCycle/auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="gc-btn-logout-sidebar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout Seller
                </button>
            </form>
        </div>
    </aside>

    <!-- KONTEN UTAMA -->
    <main class="gc-main">
        <header class="gc-topbar">
            <div class="gc-topbar-left">
                <button class="gc-menu-toggle" id="gcMenuToggle" aria-label="Buka Menu">☰</button>
                <div class="gc-topbar-title">
                    <span>Manajemen Produk Daur Ulang</span>
                </div>
            </div>
            <div class="gc-topbar-right">
                <div class="gc-topbar-clock" id="gcLiveClock"></div>
                <a href="/GreenCycle/seller/products/create.php" class="gc-btn gc-btn-primary gc-btn-sm">
                    + Tambah Produk Baru
                </a>
            </div>
        </header>

        <div class="gc-content">
            <?php if ($flash): ?>
                <div class="gc-alert gc-alert-success">
                    <span><?= e($flash) ?></span>
                    <button type="button" class="gc-alert-close">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!$store): ?>
                <div class="gc-alert gc-alert-error">
                    <span>Anda belum memiliki profil toko aktif. Silakan lengkapi profil toko terlebih dahulu.</span>
                    <a href="/GreenCycle/seller/store/index.php" class="gc-btn gc-btn-sm gc-btn-primary">Atur Toko</a>
                </div>
            <?php endif; ?>

            <div class="gc-panel">
                <div class="gc-panel-header">
                    <div class="gc-panel-title">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                        Daftar Produk Toko Saya (<?= count($products) ?>)
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a href="/GreenCycle/seller/products/create.php" class="gc-btn gc-btn-primary gc-btn-sm">
                            + Tambah Produk
                        </a>
                    </div>
                </div>

                <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light);" class="gc-filter-bar">
                    <div class="gc-search-box">
                        <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Cari nama produk atau material..." data-table-search="#tableSellerProductList">
                    </div>
                    <select class="gc-select-filter" data-table-status="#tableSellerProductList">
                        <option value="all">Semua Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="gc-table-responsive">
                    <table class="gc-table" id="tableSellerProductList">
                        <thead>
                            <tr>
                                <th>Produk Daur Ulang</th>
                                <th>Kategori</th>
                                <th>Material Daur Ulang</th>
                                <th>Harga Jual</th>
                                <th>Stok</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: var(--gc-text-muted);">
                                        Belum ada produk yang ditambahkan ke toko ini.
                                        <br><br>
                                        <a href="/GreenCycle/seller/products/create.php" class="gc-btn gc-btn-primary gc-btn-sm">
                                            + Tambah Produk Pertama
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <tr data-status="<?= e($p['status']) ?>">
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <?php if (!empty($p['product_photo'])): ?>
                                                    <img src="/GreenCycle/uploads/<?= e($p['product_photo']) ?>" alt="<?= e($p['product_name']) ?>" class="gc-product-thumb" onerror="this.style.display='none';">
                                                <?php else: ?>
                                                    <div class="gc-product-thumb" style="display:flex; align-items:center; justify-content:center; color:var(--gc-text-light); font-size:11px;">
                                                        ♻ No Pic
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?= e($p['product_name']) ?></strong>
                                                    <div style="font-size: 11.5px; color: var(--gc-text-muted);">
                                                        ID: #<?= $p['id'] ?> • <?= date('d M Y', strtotime($p['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= e($p['category_name'] ?? 'Umum') ?></td>
                                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; color: var(--gc-text-muted);">
                                            <?= e(!empty($p['material_info']) ? $p['material_info'] : '-') ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--gc-primary);">
                                                Rp <?= number_format($p['price'], 0, ',', '.') ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($p['stock'] <= 0): ?>
                                                <span class="gc-badge badge-cancelled">Habis (0)</span>
                                            <?php elseif ($p['stock'] <= 5): ?>
                                                <span class="gc-badge badge-pending">Menipis (<?= $p['stock'] ?>)</span>
                                            <?php else: ?>
                                                <span class="gc-badge badge-completed"><?= $p['stock'] ?> unit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="gc-badge badge-<?= e($p['status']) ?>">
                                                <?= e($p['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <a href="/GreenCycle/seller/products/edit.php?id=<?= $p['id'] ?>" class="gc-btn gc-btn-sm gc-btn-secondary" title="Edit Produk">
                                                    Edit
                                                </a>
                                                <form action="/GreenCycle/seller/products/delete.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus atau menonaktifkan produk ini?');" style="margin: 0;">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="gc-btn gc-btn-sm" style="background: rgba(220, 38, 38, 0.1); color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.2);" title="Hapus Produk">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
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
