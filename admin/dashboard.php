<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Proteksi role admin
require_role(['admin']);

$adminName = $_SESSION['name'] ?? 'Administrator';
$adminEmail = $_SESSION['email'] ?? '';

// Inisialisasi variabel statistik
$stats = [
    'total_users' => 0,
    'total_sellers' => 0,
    'total_customers' => 0,
    'total_stores' => 0,
    'active_stores' => 0,
    'total_products' => 0,
    'out_of_stock' => 0,
    'total_orders' => 0,
    'total_revenue' => 0,
];

$orderStatusCounts = [
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$categoryStats = [];
$recentOrders = [];
$recentStores = [];
$recentUsers = [];

try {
    // 1. Metrik Pengguna
    $userQuery = $conn->query("
        SELECT 
            COUNT(*) AS total_users,
            SUM(CASE WHEN role = 'seller' THEN 1 ELSE 0 END) AS total_sellers,
            SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) AS total_customers
        FROM users
    ");
    if ($uRow = $userQuery->fetch_assoc()) {
        $stats['total_users'] = (int)$uRow['total_users'];
        $stats['total_sellers'] = (int)$uRow['total_sellers'];
        $stats['total_customers'] = (int)$uRow['total_customers'];
    }

    // 2. Metrik Toko
    $storeQuery = $conn->query("
        SELECT 
            COUNT(*) AS total_stores,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_stores
        FROM stores
    ");
    if ($sRow = $storeQuery->fetch_assoc()) {
        $stats['total_stores'] = (int)$sRow['total_stores'];
        $stats['active_stores'] = (int)$sRow['active_stores'];
    }

    // 3. Metrik Produk
    $prodQuery = $conn->query("
        SELECT 
            COUNT(*) AS total_products,
            SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock
        FROM products
        WHERE status = 'active'
    ");
    if ($pRow = $prodQuery->fetch_assoc()) {
        $stats['total_products'] = (int)$pRow['total_products'];
        $stats['out_of_stock'] = (int)$pRow['out_of_stock'];
    }

    // 4. Metrik Pesanan & Pendapatan
    $orderQuery = $conn->query("
        SELECT 
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS total_revenue
        FROM orders
    ");
    if ($oRow = $orderQuery->fetch_assoc()) {
        $stats['total_orders'] = (int)$oRow['total_orders'];
        $stats['total_revenue'] = (float)$oRow['total_revenue'];
    }

    // 5. Status Pesanan (untuk grafik bar interaktif)
    $statusQuery = $conn->query("
        SELECT status, COUNT(*) AS count
        FROM orders
        GROUP BY status
    ");
    while ($st = $statusQuery->fetch_assoc()) {
        if (isset($orderStatusCounts[$st['status']])) {
            $orderStatusCounts[$st['status']] = (int)$st['count'];
        }
    }

    // 6. Kategori Terpopuler
    $catQuery = $conn->query("
        SELECT c.category_name, COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        GROUP BY c.id, c.category_name
        ORDER BY product_count DESC
        LIMIT 5
    ");
    $categoryStats = $catQuery->fetch_all(MYSQLI_ASSOC);

    // 7. Pesanan Terkini
    $recentOrdersQuery = $conn->query("
        SELECT 
            o.id,
            o.order_code,
            o.order_date,
            o.total_amount,
            o.status,
            o.shipping_address,
            u.name AS customer_name,
            s.store_name
        FROM orders o
        INNER JOIN users u ON o.user_id = u.id
        INNER JOIN stores s ON o.store_id = s.id
        ORDER BY o.order_date DESC
        LIMIT 8
    ");
    $recentOrders = $recentOrdersQuery->fetch_all(MYSQLI_ASSOC);

    // 8. Toko Terkini
    $recentStoresQuery = $conn->query("
        SELECT 
            s.id,
            s.store_name,
            s.status,
            s.phone,
            s.created_at,
            u.name AS owner_name
        FROM stores s
        INNER JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
        LIMIT 6
    ");
    $recentStores = $recentStoresQuery->fetch_all(MYSQLI_ASSOC);

    // 9. Pengguna Terkini
    $recentUsersQuery = $conn->query("
        SELECT id, name, email, role, status, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $recentUsers = $recentUsersQuery->fetch_all(MYSQLI_ASSOC);

} catch (mysqli_sql_exception $e) {
    error_log("Admin dashboard query error: " . $e->getMessage());
}

// Menghitung bar chart scaling
$maxOrderStatus = max(1, max(array_values($orderStatusCounts)));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrator - GreenCycle</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
</head>
<body>

<div class="gc-app">
    <!-- Backdrop Mobile -->
    <div class="gc-sidebar-backdrop" id="gcSidebarBackdrop"></div>

    <!-- SIDEBAR NAVIGASI ADMIN -->
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
            <button class="gc-sidebar-close" id="gcSidebarClose" aria-label="Tutup Menu">&times;</button>
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
            <a href="/GreenCycle/admin/dashboard.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard Overview
            </a>

            <div class="gc-nav-group-title">Transaksi & Pesanan</div>
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
                <span class="gc-nav-badge"><?= $stats['total_users'] ?></span>
            </a>

            <a href="/GreenCycle/admin/stores/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                Kelola Toko Mitra
                <span class="gc-nav-badge"><?= $stats['total_stores'] ?></span>
            </a>

            <a href="/GreenCycle/admin/categories/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                Kelola Kategori
            </a>

            <div class="gc-nav-group-title">Akses Cepat</div>
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
                    Logout Admin
                </button>
            </form>
        </div>
    </aside>

    <!-- KONTEN UTAMA ADMIN -->
    <main class="gc-main">
        <!-- Topbar -->
        <header class="gc-topbar">
            <div class="gc-topbar-left">
                <button class="gc-menu-toggle" id="gcMenuToggle" aria-label="Buka Menu">☰</button>
                <div class="gc-topbar-title">
                    <span>Admin Control Center</span>
                </div>
            </div>

            <div class="gc-topbar-right">
                <div class="gc-topbar-clock" id="gcLiveClock">
                    <!-- Realtime Clock via JS -->
                </div>
                <div class="gc-topbar-actions">
                    <a href="/GreenCycle/pages/home.php" class="gc-topbar-btn" target="_blank">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        Marketplace
                    </a>
                </div>
            </div>
        </header>

        <!-- Body Dashboard -->
        <div class="gc-content">
            <!-- Hero Greeting Admin -->
            <div class="gc-hero-banner">
                <div class="gc-hero-info">
                    <div class="gc-hero-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                        Sistem Monitoring Terpadu
                    </div>
                    <h1 class="gc-hero-title">Halo, <?= e($adminName) ?>!</h1>
                    <p class="gc-hero-subtitle">Pantau pertumbuhan marketplace, aktivitas toko daur ulang mitra, dan transaksi pengguna secara real-time untuk mendukung keberlanjutan SDG 8 & SDG 12.</p>
                </div>
                <div class="gc-hero-actions">
                    <a href="/GreenCycle/admin/users/index.php" class="gc-btn gc-btn-accent">
                        + Kelola Pengguna
                    </a>
                    <a href="/GreenCycle/admin/categories/create.php" class="gc-btn gc-btn-secondary">
                        + Tambah Kategori
                    </a>
                </div>
            </div>

            <!-- STATISTIK KARTU (METRICS) -->
            <div class="gc-stats-grid">
                <!-- Card Total Pendapatan Selesai -->
                <div class="gc-stat-card theme-emerald">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Total Omset Sukses</div>
                            <div class="gc-stat-value gc-count-up" data-count="<?= $stats['total_revenue'] ?>" data-currency="idr">
                                Rp <?= number_format($stats['total_revenue'], 0, ',', '.') ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span>Dari pesanan selesai</span>
                        <span class="gc-stat-trend up">Transaksi Valid</span>
                    </div>
                </div>

                <!-- Card Total Pesanan -->
                <div class="gc-stat-card theme-blue">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Total Pesanan</div>
                            <div class="gc-stat-value gc-count-up" data-count="<?= $stats['total_orders'] ?>">
                                <?= $stats['total_orders'] ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span>Pending: <?= $orderStatusCounts['pending'] ?> | Dikirim: <?= $orderStatusCounts['shipped'] ?></span>
                        <span class="gc-stat-trend up">Aktif</span>
                    </div>
                </div>

                <!-- Card Pengguna -->
                <div class="gc-stat-card theme-purple">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Total Pengguna</div>
                            <div class="gc-stat-value gc-count-up" data-count="<?= $stats['total_users'] ?>">
                                <?= $stats['total_users'] ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span><?= $stats['total_sellers'] ?> Seller • <?= $stats['total_customers'] ?> Customer</span>
                        <span class="gc-stat-trend neutral">Terdaftar</span>
                    </div>
                </div>

                <!-- Card Toko & Produk -->
                <div class="gc-stat-card theme-green">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Produk Daur Ulang</div>
                            <div class="gc-stat-value gc-count-up" data-count="<?= $stats['total_products'] ?>">
                                <?= $stats['total_products'] ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span>Dari <?= $stats['active_stores'] ?> Toko Aktif</span>
                        <span class="gc-stat-trend up">Katalog Siap</span>
                    </div>
                </div>
            </div>

            <!-- GRAFIK & ANALITIK INTERAKTIF NATIVE (NO FRAMEWORKS) -->
            <div class="gc-grid-2">
                <!-- Bar Chart Native Status Pesanan -->
                <div class="gc-panel">
                    <div class="gc-panel-header">
                        <div class="gc-panel-title">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/></svg>
                            Distribusi Status Pesanan Daur Ulang
                        </div>
                        <span style="font-size: 12.5px; color: var(--gc-text-muted);">Real-time Database Metric</span>
                    </div>
                    <div class="gc-panel-body gc-chart-container">
                        <div class="gc-bar-chart">
                            <!-- Pending -->
                            <?php $pctPending = ($orderStatusCounts['pending'] / $maxOrderStatus) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $pctPending) ?>%; background: linear-gradient(180deg, #f59e0b, #d97706);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusCounts['pending'] ?> Pesanan (Menunggu)</div>
                                </div>
                                <div class="gc-bar-label">Pending</div>
                            </div>

                            <!-- Processing -->
                            <?php $pctProc = ($orderStatusCounts['processing'] / $maxOrderStatus) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $pctProc) ?>%; background: linear-gradient(180deg, #3b82f6, #1d4ed8);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusCounts['processing'] ?> Pesanan (Diproses)</div>
                                </div>
                                <div class="gc-bar-label">Diproses</div>
                            </div>

                            <!-- Shipped -->
                            <?php $pctShip = ($orderStatusCounts['shipped'] / $maxOrderStatus) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $pctShip) ?>%; background: linear-gradient(180deg, #8b5cf6, #6d28d9);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusCounts['shipped'] ?> Pesanan (Dikirim)</div>
                                </div>
                                <div class="gc-bar-label">Dikirim</div>
                            </div>

                            <!-- Completed -->
                            <?php $pctComp = ($orderStatusCounts['completed'] / $maxOrderStatus) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $pctComp) ?>%; background: linear-gradient(180deg, #10b981, #059669);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusCounts['completed'] ?> Pesanan (Selesai)</div>
                                </div>
                                <div class="gc-bar-label">Selesai</div>
                            </div>

                            <!-- Cancelled -->
                            <?php $pctCanc = ($orderStatusCounts['cancelled'] / $maxOrderStatus) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $pctCanc) ?>%; background: linear-gradient(180deg, #ef4444, #b91c1c);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusCounts['cancelled'] ?> Pesanan (Dibatalkan)</div>
                                </div>
                                <div class="gc-bar-label">Batal</div>
                            </div>
                        </div>

                        <div class="gc-chart-legend">
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#d97706;"></span> Pending: <?= $orderStatusCounts['pending'] ?></div>
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#2563eb;"></span> Diproses: <?= $orderStatusCounts['processing'] ?></div>
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#7c3aed;"></span> Dikirim: <?= $orderStatusCounts['shipped'] ?></div>
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#059669;"></span> Selesai: <?= $orderStatusCounts['completed'] ?></div>
                        </div>
                    </div>
                </div>

                <!-- Kategori Daur Ulang Terpopuler -->
                <div class="gc-panel">
                    <div class="gc-panel-header">
                        <div class="gc-panel-title">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>
                            Kategori Produk Daur Ulang
                        </div>
                        <a href="/GreenCycle/admin/categories/index.php" class="gc-btn gc-btn-sm gc-btn-secondary">Kelola</a>
                    </div>
                    <div class="gc-panel-body">
                        <?php if (empty($categoryStats)): ?>
                            <p style="color: var(--gc-text-muted); text-align: center; padding: 20px;">Belum ada data kategori.</p>
                        <?php else: ?>
                            <?php 
                                $maxCatProd = max(1, max(array_map(fn($c) => (int)$c['product_count'], $categoryStats)));
                            ?>
                            <div class="gc-progress-list">
                                <?php foreach ($categoryStats as $cat): ?>
                                    <?php $pctCat = round(($cat['product_count'] / $maxCatProd) * 100); ?>
                                    <div class="gc-progress-item">
                                        <div class="gc-progress-header">
                                            <span><?= e($cat['category_name']) ?></span>
                                            <span style="color: var(--gc-primary); font-weight:700;"><?= $cat['product_count'] ?> Produk</span>
                                        </div>
                                        <div class="gc-progress-bar">
                                            <div class="gc-progress-fill" style="width: <?= max(5, $pctCat) ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- TABBED DATA LISTS DENGAN LIVE SEARCH INTERAKTIF -->
            <div class="gc-panel">
                <div class="gc-tabs-header">
                    <button class="gc-tab-btn active" data-tab="tabOrders">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/></svg>
                        Pesanan Masuk Terbaru
                    </button>
                    <button class="gc-tab-btn" data-tab="tabStores">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                        Toko Mitra Terdaftar
                    </button>
                    <button class="gc-tab-btn" data-tab="tabUsers">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Pengguna Platform
                    </button>
                </div>

                <!-- TAB 1: PESANAN TERBARU -->
                <div class="gc-tab-pane active" id="tabOrders">
                    <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light);" class="gc-filter-bar">
                        <div class="gc-search-box">
                            <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" placeholder="Cari kode order, pembeli, toko..." data-table-search="#tableAdminOrders">
                        </div>
                        <select class="gc-select-filter" data-table-status="#tableAdminOrders">
                            <option value="all">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="gc-table-responsive">
                        <table class="gc-table" id="tableAdminOrders">
                            <thead>
                                <tr>
                                    <th>Kode Order</th>
                                    <th>Pembeli</th>
                                    <th>Toko Penjual</th>
                                    <th>Waktu Pemesanan</th>
                                    <th>Total Nilai</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentOrders)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 36px; color: var(--gc-text-muted);">
                                            Belum ada transaksi pemesanan dalam database.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr data-status="<?= e($order['status']) ?>">
                                            <td>
                                                <strong style="color: var(--gc-primary); font-family: monospace; font-size: 14px;">
                                                    #<?= e($order['order_code']) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <div class="gc-table-user-cell">
                                                    <div class="gc-table-avatar">
                                                        <?= strtoupper(substr($order['customer_name'] ?? 'C', 0, 1)) ?>
                                                    </div>
                                                    <span><?= e($order['customer_name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= e($order['store_name']) ?></strong>
                                            </td>
                                            <td style="color: var(--gc-text-muted); font-size: 12.5px;">
                                                <?= date('d M Y, H:i', strtotime($order['order_date'])) ?> WIB
                                            </td>
                                            <td>
                                                <strong style="color: var(--gc-forest);">
                                                    Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="gc-badge badge-<?= e($order['status']) ?>">
                                                    <?= e($order['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: TOKO MITRA -->
                <div class="gc-tab-pane" id="tabStores">
                    <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light); display: flex; justify-content: space-between; align-items: center;" class="gc-filter-bar">
                        <div class="gc-search-box">
                            <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" placeholder="Cari nama toko atau pemilik..." data-table-search="#tableAdminStores">
                        </div>
                        <a href="/GreenCycle/admin/stores/index.php" class="gc-btn gc-btn-primary gc-btn-sm">
                            Buka Kelola Toko &rarr;
                        </a>
                    </div>

                    <div class="gc-table-responsive">
                        <table class="gc-table" id="tableAdminStores">
                            <thead>
                                <tr>
                                    <th>Nama Toko</th>
                                    <th>Pemilik (Seller)</th>
                                    <th>No. Telepon</th>
                                    <th>Tanggal Bergabung</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentStores)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:36px; color:var(--gc-text-muted);">
                                            Belum ada data toko seller.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentStores as $st): ?>
                                        <tr data-status="<?= e($st['status']) ?>">
                                            <td>
                                                <strong><?= e($st['store_name']) ?></strong>
                                            </td>
                                            <td><?= e($st['owner_name']) ?></td>
                                            <td><?= e($st['phone'] ?? '-') ?></td>
                                            <td><?= date('d M Y', strtotime($st['created_at'])) ?></td>
                                            <td>
                                                <span class="gc-badge badge-<?= e($st['status']) ?>">
                                                    <?= e($st['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="/GreenCycle/admin/stores/index.php" class="gc-btn gc-btn-sm gc-btn-secondary">
                                                    Kelola
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: PENGGUNA PLATFORM -->
                <div class="gc-tab-pane" id="tabUsers">
                    <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light); display: flex; justify-content: space-between; align-items: center;" class="gc-filter-bar">
                        <div class="gc-search-box">
                            <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" placeholder="Cari nama atau email..." data-table-search="#tableAdminUsers">
                        </div>
                        <a href="/GreenCycle/admin/users/index.php" class="gc-btn gc-btn-primary gc-btn-sm">
                            Buka Kelola Pengguna &rarr;
                        </a>
                    </div>

                    <div class="gc-table-responsive">
                        <table class="gc-table" id="tableAdminUsers">
                            <thead>
                                <tr>
                                    <th>Nama Pengguna</th>
                                    <th>Email</th>
                                    <th>Role Akses</th>
                                    <th>Status Akun</th>
                                    <th>Terdaftar Pada</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentUsers)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:36px; color:var(--gc-text-muted);">
                                            Belum ada data pengguna.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentUsers as $usr): ?>
                                        <tr data-status="<?= e($usr['status']) ?>">
                                            <td>
                                                <div class="gc-table-user-cell">
                                                    <div class="gc-table-avatar">
                                                        <?= strtoupper(substr($usr['name'] ?? 'U', 0, 1)) ?>
                                                    </div>
                                                    <strong><?= e($usr['name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= e($usr['email']) ?></td>
                                            <td>
                                                <span class="gc-badge badge-<?= e($usr['role']) ?>">
                                                    <?= e($usr['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="gc-badge badge-<?= e($usr['status']) ?>">
                                                    <?= e($usr['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d M Y', strtotime($usr['created_at'])) ?></td>
                                            <td>
                                                <a href="/GreenCycle/admin/users/index.php" class="gc-btn gc-btn-sm gc-btn-secondary">
                                                    Kelola
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
