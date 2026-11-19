<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Proteksi role seller
require_role(['seller']);

$userId = (int)$_SESSION['user_id'];
$sellerName = $_SESSION['name'] ?? 'Penjual GreenCycle';
$sellerEmail = $_SESSION['email'] ?? '';

// Ambil informasi toko milik seller
$store = null;
$storeId = 0;

$stmt = $conn->prepare("
    SELECT id, store_name, description, address, phone, logo, status, created_at
    FROM stores
    WHERE user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($store) {
    $storeId = (int)$store['id'];
}

// Inisialisasi statistik seller
$sellerStats = [
    'total_products' => 0,
    'out_of_stock' => 0,
    'total_stock_units' => 0,
    'total_orders' => 0,
    'pending_orders' => 0,
    'completed_revenue' => 0,
    'average_rating' => 0,
    'total_reviews' => 0
];

$orderStatusSeller = [
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$storeProducts = [];
$storeOrders = [];
$storeReviews = [];

if ($storeId > 0) {
    // 1. Statistik Produk Toko
    $pStmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_products,
            COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
            COALESCE(SUM(stock), 0) AS total_stock_units
        FROM products
        WHERE store_id = ?
    ");
    $pStmt->bind_param("i", $storeId);
    $pStmt->execute();
    $pRes = $pStmt->get_result()->fetch_assoc();
    if ($pRes) {
        $sellerStats['total_products'] = (int)$pRes['total_products'];
        $sellerStats['out_of_stock'] = (int)$pRes['out_of_stock'];
        $sellerStats['total_stock_units'] = (int)$pRes['total_stock_units'];
    }
    $pStmt->close();

    // 2. Statistik Pesanan & Omset Toko
    $oStmt = $conn->prepare("
        SELECT 
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_orders,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS completed_revenue
        FROM orders
        WHERE store_id = ?
    ");
    $oStmt->bind_param("i", $storeId);
    $oStmt->execute();
    $oRes = $oStmt->get_result()->fetch_assoc();
    if ($oRes) {
        $sellerStats['total_orders'] = (int)$oRes['total_orders'];
        $sellerStats['pending_orders'] = (int)$oRes['pending_orders'];
        $sellerStats['completed_revenue'] = (float)$oRes['completed_revenue'];
    }
    $oStmt->close();

    // 3. Distribusi Status Pesanan Toko
    $osStmt = $conn->prepare("
        SELECT status, COUNT(*) AS count
        FROM orders
        WHERE store_id = ?
        GROUP BY status
    ");
    $osStmt->bind_param("i", $storeId);
    $osStmt->execute();
    $osRes = $osStmt->get_result();
    while ($r = $osRes->fetch_assoc()) {
        if (isset($orderStatusSeller[$r['status']])) {
            $orderStatusSeller[$r['status']] = (int)$r['count'];
        }
    }
    $osStmt->close();

    // 4. Rating & Ulasan Toko
    $rStmt = $conn->prepare("
        SELECT 
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            COUNT(r.id) AS total_reviews
        FROM reviews r
        INNER JOIN products p ON r.product_id = p.id
        WHERE p.store_id = ?
    ");
    $rStmt->bind_param("i", $storeId);
    $rStmt->execute();
    $rRes = $rStmt->get_result()->fetch_assoc();
    if ($rRes) {
        $sellerStats['average_rating'] = round((float)$rRes['avg_rating'], 1);
        $sellerStats['total_reviews'] = (int)$rRes['total_reviews'];
    }
    $rStmt->close();

    // 5. Daftar Produk Toko
    $prodListStmt = $conn->prepare("
        SELECT 
            p.id,
            p.product_name,
            p.price,
            p.stock,
            p.product_photo,
            p.status,
            c.category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.store_id = ?
        ORDER BY p.id DESC
        LIMIT 10
    ");
    $prodListStmt->bind_param("i", $storeId);
    $prodListStmt->execute();
    $storeProducts = $prodListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $prodListStmt->close();

    // 6. Daftar Pesanan Toko
    $orderListStmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_code,
            o.order_date,
            o.total_amount,
            o.status,
            o.shipping_address,
            u.name AS customer_name
        FROM orders o
        INNER JOIN users u ON o.user_id = u.id
        WHERE o.store_id = ?
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $orderListStmt->bind_param("i", $storeId);
    $orderListStmt->execute();
    $storeOrders = $orderListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $orderListStmt->close();

    // 7. Daftar Ulasan Terbaru untuk Produk Toko
    $revListStmt = $conn->prepare("
        SELECT 
            r.id,
            r.rating,
            r.comment,
            r.created_at,
            p.product_name,
            u.name AS reviewer_name
        FROM reviews r
        INNER JOIN products p ON r.product_id = p.id
        INNER JOIN users u ON r.user_id = u.id
        WHERE p.store_id = ?
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $revListStmt->bind_param("i", $storeId);
    $revListStmt->execute();
    $storeReviews = $revListStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $revListStmt->close();
}

$maxSellerOrderCount = max(1, max(array_values($orderStatusSeller)));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Seller - <?= e($store['store_name'] ?? 'Toko Saya') ?> | GreenCycle</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
</head>
<body>

<div class="gc-app">
    <!-- Backdrop Mobile -->
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
            <a href="/GreenCycle/seller/dashboard.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard Toko
            </a>

            <a href="/GreenCycle/seller/products/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                Kelola Produk Toko
                <span class="gc-nav-badge"><?= $sellerStats['total_products'] ?></span>
            </a>

            <a href="/GreenCycle/seller/orders/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Pesanan Masuk
                <span class="gc-nav-badge"><?= $sellerStats['total_orders'] ?></span>
            </a>

            <a href="/GreenCycle/seller/store/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                Pengaturan Toko
            </a>

            <div class="gc-nav-group-title">Marketplace</div>
            <a href="/GreenCycle/pages/home.php" class="gc-nav-item" target="_blank">
                <svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>
                Etalase Marketplace
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

    <!-- KONTEN UTAMA SELLER -->
    <main class="gc-main">
        <!-- Topbar -->
        <header class="gc-topbar">
            <div class="gc-topbar-left">
                <button class="gc-menu-toggle" id="gcMenuToggle" aria-label="Buka Menu">☰</button>
                <div class="gc-topbar-title">
                    <span>Seller Studio GreenCycle</span>
                </div>
            </div>

            <div class="gc-topbar-right">
                <div class="gc-topbar-clock" id="gcLiveClock"></div>
                <div class="gc-topbar-actions">
                    <a href="/GreenCycle/seller/store/index.php" class="gc-topbar-btn">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                        Edit Toko
                    </a>
                </div>
            </div>
        </header>

        <div class="gc-content">
            <?php if (!$store): ?>
                <!-- Alert jika toko belum diinisialisasi -->
                <div class="gc-alert gc-alert-info">
                    <div>
                        <strong>Profil Toko Belum Lengkap!</strong>
                        Silakan atur nama dan alamat tokomu agar produk daur ulangmu dapat tayang di marketplace.
                    </div>
                    <a href="/GreenCycle/seller/store/index.php" class="gc-btn gc-btn-sm gc-btn-primary">Atur Toko</a>
                </div>
            <?php endif; ?>

            <!-- Hero Greeting Seller -->
            <div class="gc-hero-banner" style="background: linear-gradient(135deg, #10482d 0%, #176b43 100%);">
                <div class="gc-hero-info">
                    <div class="gc-hero-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                        Mitra Ekonomi Sirkular (SDG 8 & 12)
                    </div>
                    <h1 class="gc-hero-title"><?= e($store['store_name'] ?? 'Toko Anda') ?></h1>
                    <p class="gc-hero-subtitle">Kelola produk daur ulang kreatif, pantau stok persediaan, dan proses pesanan dari pelanggan secara cepat dan efisien.</p>
                </div>
                <div class="gc-hero-actions">
                    <a href="/GreenCycle/seller/products/create.php" class="gc-btn gc-btn-accent">
                        + Tambah Produk Baru
                    </a>
                    <a href="/GreenCycle/seller/orders/index.php" class="gc-btn gc-btn-secondary">
                        Lihat Pesanan Masuk
                    </a>
                </div>
            </div>

            <!-- METRICS KARTU STATISTIK SELLER -->
            <div class="gc-stats-grid">
                <!-- Pendapatan Toko -->
                <div class="gc-stat-card theme-emerald">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Omset Pesanan Sukses</div>
                            <div class="gc-stat-value gc-count-up" data-count="<?= $sellerStats['completed_revenue'] ?>" data-currency="idr">
                                Rp <?= number_format($sellerStats['completed_revenue'], 0, ',', '.') ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span>Dari pesanan completed</span>
                        <span class="gc-stat-trend up">Penjualan Bersih</span>
                    </div>
                </div>

                <!-- Total Pesanan Masuk -->
                <div class="gc-stat-card theme-blue">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Total Pesanan Toko</div>
                            <div class="gc-stat-value gc-count-up" data-count="<?= $sellerStats['total_orders'] ?>">
                                <?= $sellerStats['total_orders'] ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span>Pending: <?= $orderStatusSeller['pending'] ?> | Kirim: <?= $orderStatusSeller['shipped'] ?></span>
                        <span class="gc-stat-trend neutral"><?= $sellerStats['pending_orders'] ?> Perlu Proses</span>
                    </div>
                </div>

                <!-- Total Produk & Stok -->
                <div class="gc-stat-card theme-green">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Katalog Produk</div>
                            <div class="gc-stat-value gc-count-up" data-count="<?= $sellerStats['total_products'] ?>">
                                <?= $sellerStats['total_products'] ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span>Total Stok: <?= $sellerStats['total_stock_units'] ?> unit</span>
                        <span class="gc-stat-trend <?= $sellerStats['out_of_stock'] > 0 ? 'cancelled' : 'up' ?>">
                            <?= $sellerStats['out_of_stock'] ?> Stok Habis
                        </span>
                    </div>
                </div>

                <!-- Rating Kepuasan Pelanggan -->
                <div class="gc-stat-card theme-amber">
                    <div class="gc-stat-top">
                        <div>
                            <div class="gc-stat-label">Rating Toko</div>
                            <div class="gc-stat-value">
                                ★ <?= $sellerStats['average_rating'] > 0 ? number_format($sellerStats['average_rating'], 1) : '-' ?>
                            </div>
                        </div>
                        <div class="gc-stat-icon-wrapper">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        </div>
                    </div>
                    <div class="gc-stat-footer">
                        <span>Total <?= $sellerStats['total_reviews'] ?> Ulasan Daur Ulang</span>
                        <span class="gc-stat-trend up">Kepuasan Pembeli</span>
                    </div>
                </div>
            </div>

            <!-- GRAFIK STATUS PESANAN TOKO & RINGKASAN -->
            <div class="gc-grid-2">
                <!-- Bar Chart Native Pesanan Toko -->
                <div class="gc-panel">
                    <div class="gc-panel-header">
                        <div class="gc-panel-title">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/></svg>
                            Arus Pesanan Toko Anda
                        </div>
                        <span style="font-size: 12.5px; color: var(--gc-text-muted);">Status Pesanan Masuk</span>
                    </div>
                    <div class="gc-panel-body gc-chart-container">
                        <div class="gc-bar-chart">
                            <!-- Pending -->
                            <?php $sPctPending = ($orderStatusSeller['pending'] / $maxSellerOrderCount) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $sPctPending) ?>%; background: linear-gradient(180deg, #f59e0b, #d97706);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusSeller['pending'] ?> Pesanan Pending</div>
                                </div>
                                <div class="gc-bar-label">Pending</div>
                            </div>

                            <!-- Processing -->
                            <?php $sPctProc = ($orderStatusSeller['processing'] / $maxSellerOrderCount) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $sPctProc) ?>%; background: linear-gradient(180deg, #3b82f6, #1d4ed8);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusSeller['processing'] ?> Sedang Diproses</div>
                                </div>
                                <div class="gc-bar-label">Diproses</div>
                            </div>

                            <!-- Shipped -->
                            <?php $sPctShip = ($orderStatusSeller['shipped'] / $maxSellerOrderCount) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $sPctShip) ?>%; background: linear-gradient(180deg, #8b5cf6, #6d28d9);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusSeller['shipped'] ?> Sedang Dikirim</div>
                                </div>
                                <div class="gc-bar-label">Dikirim</div>
                            </div>

                            <!-- Completed -->
                            <?php $sPctComp = ($orderStatusSeller['completed'] / $maxSellerOrderCount) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $sPctComp) ?>%; background: linear-gradient(180deg, #10b981, #059669);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusSeller['completed'] ?> Selesai Diterima</div>
                                </div>
                                <div class="gc-bar-label">Selesai</div>
                            </div>

                            <!-- Cancelled -->
                            <?php $sPctCanc = ($orderStatusSeller['cancelled'] / $maxSellerOrderCount) * 100; ?>
                            <div class="gc-bar-col">
                                <div class="gc-bar-track">
                                    <div class="gc-bar-fill" style="height: <?= max(6, $sPctCanc) ?>%; background: linear-gradient(180deg, #ef4444, #b91c1c);"></div>
                                    <div class="gc-bar-tooltip"><?= $orderStatusSeller['cancelled'] ?> Dibatalkan</div>
                                </div>
                                <div class="gc-bar-label">Batal</div>
                            </div>
                        </div>

                        <div class="gc-chart-legend">
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#d97706;"></span> Menunggu Pembayaran</div>
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#2563eb;"></span> Diproses</div>
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#7c3aed;"></span> Dikirim</div>
                            <div class="gc-legend-item"><span class="gc-legend-dot" style="background:#059669;"></span> Selesai</div>
                        </div>
                    </div>
                </div>

                <!-- Info Profil Toko Cepat -->
                <div class="gc-panel">
                    <div class="gc-panel-header">
                        <div class="gc-panel-title">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                            Identitas Toko Mitra
                        </div>
                        <span class="gc-badge badge-<?= e($store['status'] ?? 'active') ?>">
                            <?= e($store['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    <div class="gc-panel-body">
                        <p style="font-size: 13.5px; color: var(--gc-text-muted); margin-bottom: 16px;">
                            <?= e(!empty($store['description']) ? $store['description'] : 'Belum ada deskripsi toko.') ?>
                        </p>

                        <div style="display: flex; flex-direction: column; gap: 10px; font-size: 13px;">
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--gc-border-light); padding-bottom: 8px;">
                                <span style="color: var(--gc-text-muted);">Kontak Telepon:</span>
                                <strong><?= e($store['phone'] ?? '-') ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--gc-border-light); padding-bottom: 8px;">
                                <span style="color: var(--gc-text-muted);">Alamat Pengiriman:</span>
                                <strong style="text-align: right; max-width: 220px;"><?= e($store['address'] ?? '-') ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-bottom: 4px;">
                                <span style="color: var(--gc-text-muted);">Bergabung:</span>
                                <strong><?= isset($store['created_at']) ? date('d M Y', strtotime($store['created_at'])) : '-' ?></strong>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <a href="/GreenCycle/seller/store/index.php" class="gc-btn gc-btn-secondary" style="width: 100%;">
                                Perbarui Data Toko
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABBED DATA SELLER: PESANAN MASUK & KATALOG PRODUK TOKO -->
            <div class="gc-panel">
                <div class="gc-tabs-header">
                    <button class="gc-tab-btn active" data-tab="tabSellerOrders">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/></svg>
                        Pesanan Masuk Toko
                    </button>
                    <button class="gc-tab-btn" data-tab="tabSellerProducts">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                        Katalog Produk Toko
                    </button>
                    <button class="gc-tab-btn" data-tab="tabSellerReviews">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        Ulasan Pembeli Terkini
                    </button>
                </div>

                <!-- TAB 1: PESANAN MASUK TOKO -->
                <div class="gc-tab-pane active" id="tabSellerOrders">
                    <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light); display: flex; justify-content: space-between; align-items: center;" class="gc-filter-bar">
                        <div style="display: flex; gap: 10px; flex: 1;">
                            <div class="gc-search-box">
                                <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <input type="text" placeholder="Cari kode order atau nama pembeli..." data-table-search="#tableSellerOrders">
                            </div>
                            <select class="gc-select-filter" data-table-status="#tableSellerOrders">
                                <option value="all">Semua Status</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <a href="/GreenCycle/seller/orders/index.php" class="gc-btn gc-btn-secondary gc-btn-sm">
                            Buka Manajemen Pesanan &rarr;
                        </a>
                    </div>

                    <div class="gc-table-responsive">
                        <table class="gc-table" id="tableSellerOrders">
                            <thead>
                                <tr>
                                    <th>Kode Order</th>
                                    <th>Nama Pembeli</th>
                                    <th>Tanggal Masuk</th>
                                    <th>Alamat Tujuan</th>
                                    <th>Total Tagihan</th>
                                    <th>Status Pesanan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($storeOrders)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding:36px; color:var(--gc-text-muted);">
                                            Belum ada pesanan yang masuk untuk toko Anda.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($storeOrders as $so): ?>
                                        <tr data-status="<?= e($so['status']) ?>">
                                            <td>
                                                <strong style="color: var(--gc-primary); font-family: monospace; font-size: 14px;">
                                                    #<?= e($so['order_code']) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <div class="gc-table-user-cell">
                                                    <div class="gc-table-avatar">
                                                        <?= strtoupper(substr($so['customer_name'] ?? 'C', 0, 1)) ?>
                                                    </div>
                                                    <span><?= e($so['customer_name']) ?></span>
                                                </div>
                                            </td>
                                            <td style="color: var(--gc-text-muted); font-size: 12.5px;">
                                                <?= date('d M Y, H:i', strtotime($so['order_date'])) ?>
                                            </td>
                                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <?= e($so['shipping_address']) ?>
                                            </td>
                                            <td>
                                                <strong style="color: var(--gc-forest);">
                                                    Rp <?= number_format($so['total_amount'], 0, ',', '.') ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="gc-badge badge-<?= e($so['status']) ?>">
                                                    <?= e($so['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: KATALOG PRODUK TOKO -->
                <div class="gc-tab-pane" id="tabSellerProducts">
                    <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light); display: flex; justify-content: space-between; align-items: center;" class="gc-filter-bar">
                        <div class="gc-search-box">
                            <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                            <input type="text" placeholder="Cari nama produk daur ulang..." data-table-search="#tableSellerProducts">
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <a href="/GreenCycle/seller/products/create.php" class="gc-btn gc-btn-primary gc-btn-sm">
                                + Tambah Produk
                            </a>
                            <a href="/GreenCycle/seller/products/index.php" class="gc-btn gc-btn-secondary gc-btn-sm">
                                Kelola Seluruh Produk &rarr;
                            </a>
                        </div>
                    </div>

                    <div class="gc-table-responsive">
                        <table class="gc-table" id="tableSellerProducts">
                            <thead>
                                <tr>
                                    <th>Produk Daur Ulang</th>
                                    <th>Kategori</th>
                                    <th>Harga Satuan</th>
                                    <th>Stok Tersedia</th>
                                    <th>Status Produk</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($storeProducts)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:36px; color:var(--gc-text-muted);">
                                            Belum ada produk yang ditambahkan di toko ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($storeProducts as $sp): ?>
                                        <tr data-status="<?= e($sp['status']) ?>">
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <?php if (!empty($sp['product_photo'])): ?>
                                                        <img src="/GreenCycle/uploads/<?= e($sp['product_photo']) ?>" alt="<?= e($sp['product_name']) ?>" class="gc-product-thumb" onerror="this.style.display='none';">
                                                    <?php endif; ?>
                                                    <strong><?= e($sp['product_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= e($sp['category_name'] ?? 'Umum') ?></td>
                                            <td>
                                                <strong style="color: var(--gc-primary);">
                                                    Rp <?= number_format($sp['price'], 0, ',', '.') ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?php if ($sp['stock'] <= 0): ?>
                                                    <span class="gc-badge badge-cancelled">Habis (0)</span>
                                                <?php elseif ($sp['stock'] <= 5): ?>
                                                    <span class="gc-badge badge-pending">Menipis (<?= $sp['stock'] ?>)</span>
                                                <?php else: ?>
                                                    <span class="gc-badge badge-completed">Tersedia (<?= $sp['stock'] ?>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="gc-badge badge-<?= e($sp['status']) ?>">
                                                    <?= e($sp['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: ULASAN PEMBELI -->
                <div class="gc-tab-pane" id="tabSellerReviews">
                    <div style="padding: 24px;">
                        <?php if (empty($storeReviews)): ?>
                            <p style="text-align:center; padding: 24px; color: var(--gc-text-muted);">
                                Belum ada ulasan untuk produk daur ulang dari tokomu.
                            </p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 16px;">
                                <?php foreach ($storeReviews as $rev): ?>
                                    <div style="background: var(--gc-surface-alt); border: 1px solid var(--gc-border); border-radius: var(--gc-radius-md); padding: 16px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <div>
                                                <strong><?= e($rev['reviewer_name']) ?></strong>
                                                <span style="color: var(--gc-text-muted); font-size: 12px; margin-left: 6px;">
                                                    untuk <em><?= e($rev['product_name']) ?></em>
                                                </span>
                                            </div>
                                            <div style="color: #f59e0b; font-size: 15px; font-weight: bold;">
                                                <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                                            </div>
                                        </div>
                                        <p style="font-size: 13.5px; color: var(--gc-text); line-height: 1.5;">
                                            "<?= e($rev['comment'] ?? 'Tidak ada komentar tertulis.') ?>"
                                        </p>
                                        <div style="font-size: 11.5px; color: var(--gc-text-muted); margin-top: 8px;">
                                            <?= date('d M Y, H:i', strtotime($rev['created_at'])) ?> WIB
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
