<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Proteksi role customer
require_role(['customer']);

$userId = (int)$_SESSION['user_id'];
$customerName = $_SESSION['name'] ?? 'Pelanggan GreenCycle';
$customerEmail = $_SESSION['email'] ?? '';

$flash = $_SESSION['flash'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash'], $_SESSION['flash_error']);

// 1. Data Profil Customer
$customer = null;
$uStmt = $conn->prepare("
    SELECT id, name, email, phone, address, profile_photo, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
");
$uStmt->bind_param("i", $userId);
$uStmt->execute();
$customer = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

// 2. Statistik Pesanan Customer
$stats = [
    'total_orders' => 0,
    'active_orders' => 0,
    'completed_orders' => 0,
    'total_spent' => 0,
    'items_rescued' => 0,
    'cart_count' => 0
];

$oStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN status IN ('pending', 'processing', 'shipped') THEN 1 ELSE 0 END), 0) AS active_orders,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_orders,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS total_spent
    FROM orders
    WHERE user_id = ?
");
$oStmt->bind_param("i", $userId);
$oStmt->execute();
$oRes = $oStmt->get_result()->fetch_assoc();
if ($oRes) {
    $stats['total_orders'] = (int)$oRes['total_orders'];
    $stats['active_orders'] = (int)$oRes['active_orders'];
    $stats['completed_orders'] = (int)$oRes['completed_orders'];
    $stats['total_spent'] = (float)$oRes['total_spent'];
}
$oStmt->close();

// 3. Dampak Lingkungan (Eco-Impact: Produk Daur Ulang yang Didukung)
$ecoStmt = $conn->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) AS items_rescued
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id = ? AND o.status = 'completed'
");
$ecoStmt->bind_param("i", $userId);
$ecoStmt->execute();
$ecoRes = $ecoStmt->get_result()->fetch_assoc();
if ($ecoRes) {
    $stats['items_rescued'] = (int)$ecoRes['items_rescued'];
}
$ecoStmt->close();

// 4. Data Keranjang Belanja Aktif
$cartItems = [];
$cartTotal = 0;
$cStmt = $conn->prepare("
    SELECT 
        ci.id AS item_id,
        ci.quantity,
        p.id AS product_id,
        p.product_name,
        p.price,
        p.product_photo,
        s.store_name
    FROM carts c
    INNER JOIN cart_items ci ON c.id = ci.cart_id
    INNER JOIN products p ON ci.product_id = p.id
    INNER JOIN stores s ON p.store_id = s.id
    WHERE c.user_id = ?
    ORDER BY ci.id DESC
");
$cStmt->bind_param("i", $userId);
$cStmt->execute();
$cRes = $cStmt->get_result();
while ($item = $cRes->fetch_assoc()) {
    $cartItems[] = $item;
    $cartTotal += ((float)$item['price'] * (int)$item['quantity']);
}
$cStmt->close();
$stats['cart_count'] = count($cartItems);

// 5. Pesanan Terkini Customer
$orders = [];
$recentOrdersStmt = $conn->prepare("
    SELECT 
        o.id,
        o.order_code,
        o.order_date,
        o.total_amount,
        o.status,
        s.store_name,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
    FROM orders o
    INNER JOIN stores s ON o.store_id = s.id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
    LIMIT 8
");
$recentOrdersStmt->bind_param("i", $userId);
$recentOrdersStmt->execute();
$orders = $recentOrdersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentOrdersStmt->close();

// 6. Pesanan Aktif Terbaru untuk Tracker Stepper
$trackedOrder = null;
if (!empty($orders)) {
    // Cari pesanan aktif pertama, atau pesanan paling akhir jika tidak ada yang aktif
    foreach ($orders as $ord) {
        if (in_array($ord['status'], ['pending', 'processing', 'shipped'])) {
            $trackedOrder = $ord;
            break;
        }
    }
    if (!$trackedOrder) {
        $trackedOrder = $orders[0];
    }
}

// 7. Riwayat Ulasan Customer
$reviews = [];
$revStmt = $conn->prepare("
    SELECT 
        r.id,
        r.rating,
        r.comment,
        r.created_at,
        p.product_name,
        p.product_photo
    FROM reviews r
    INNER JOIN products p ON r.product_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$revStmt->bind_param("i", $userId);
$revStmt->execute();
$reviews = $revStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$revStmt->close();

// 8. Rekomendasi Produk Daur Ulang Pilihan
$recommendedProducts = [];
$recQuery = $conn->query("
    SELECT 
        p.id,
        p.product_name,
        p.price,
        p.product_photo,
        c.category_name,
        s.store_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.id
    INNER JOIN stores s ON p.store_id = s.id
    WHERE p.status = 'active' AND p.stock > 0
    ORDER BY p.id DESC
    LIMIT 4
");
if ($recQuery) {
    $recommendedProducts = $recQuery->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Customer - <?= e($customerName) ?> | GreenCycle</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
    <style>
        /* Specific Customer Header & Top Nav */
        .gc-customer-header {
            background: #ffffff;
            border-bottom: 1px solid var(--gc-border);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--gc-shadow-xs);
        }

        .gc-customer-nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .gc-customer-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .gc-c-link {
            font-size: 14px;
            font-weight: 600;
            color: var(--gc-text-muted);
            padding: 8px 12px;
            border-radius: var(--gc-radius-md);
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .gc-c-link:hover, .gc-c-link.active {
            color: var(--gc-primary);
            background: var(--gc-accent-soft);
        }

        .gc-customer-container {
            max-width: 1200px;
            margin: 28px auto;
            padding: 0 24px;
        }

        @media (max-width: 820px) {
            .gc-customer-links {
                display: none;
            }
            .gc-mobile-nav-show {
                display: flex !important;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS CUSTOMER -->
    <header class="gc-customer-header">
        <div class="gc-customer-nav-container">
            <!-- LOGO MARKETPLACE - TIDAK DIUBAH -->
            <a href="/GreenCycle/pages/home.php" class="gc-sidebar-logo" style="color: var(--gc-forest);">
                <img
                    src="/GreenCycle/assets/images/greencycle-logo.png"
                    alt="GreenCycle Logo"
                    style="height: 50px; width: auto;"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';"
                >
                <span class="brand-fallback" style="display:none; font-size:24px; font-weight:800; color:var(--gc-forest); letter-spacing:-1px;">
                    Green<span style="color:var(--gc-accent);">Cycle</span>
                </span>
            </a>

            <!-- Menu Tautan Customer -->
            <nav class="gc-customer-links">
                <a href="/GreenCycle/pages/home.php" class="gc-c-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    Beranda
                </a>
                <a href="/GreenCycle/pages/products.php" class="gc-c-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>
                    Katalog Daur Ulang
                </a>
                <a href="/GreenCycle/pages/cart.php" class="gc-c-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    Keranjang
                    <?php if ($stats['cart_count'] > 0): ?>
                        <span class="gc-badge badge-pending" style="padding: 2px 7px; font-size: 11px;">
                            <?= $stats['cart_count'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="/GreenCycle/pages/order-history.php" class="gc-c-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                    Riwayat Belanja
                </a>
                <a href="/GreenCycle/customer/dashboard.php" class="gc-c-link active">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Dashboard Saya
                </a>
            </nav>

            <!-- User Info & Logout -->
            <div style="display: flex; align-items: center; gap: 14px;">
                <div class="gc-table-user-cell">
                    <div class="gc-table-avatar" style="background: linear-gradient(135deg, var(--gc-accent), var(--gc-primary)); color: #fff;">
                        <?= strtoupper(substr($customerName, 0, 1)) ?>
                    </div>
                    <span style="font-weight: 600; font-size: 13.5px;" class="gc-mobile-hide">
                        <?= e($customerName) ?>
                    </span>
                </div>

                <form action="/GreenCycle/auth/logout.php" method="POST" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="gc-btn gc-btn-sm gc-btn-secondary" title="Keluar dari akun">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- KONTEN DASHBOARD CUSTOMER -->
    <main class="gc-customer-container">

        <?php if ($flash): ?>
            <div class="gc-alert gc-alert-success">
                <span><?= e($flash) ?></span>
                <button type="button" class="gc-alert-close">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="gc-alert gc-alert-error">
                <span><?= e($flash_error) ?></span>
                <button type="button" class="gc-alert-close">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Banner Eco-Shopper -->
        <div class="gc-hero-banner" style="background: linear-gradient(135deg, var(--gc-primary) 0%, var(--gc-forest) 100%);">
            <div class="gc-hero-info">
                <div class="gc-hero-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                    Sahabat Bumi GreenCycle • SDG 12
                </div>
                <h1 class="gc-hero-title">Halo, <?= e($customerName) ?>!</h1>
                <p class="gc-hero-subtitle">Terima kasih telah menjadi bagian dari gerakan ekonomi sirkular. Setiap produk daur ulang yang kamu beli menyelamatkan material berharga dari tempat pembuangan akhir.</p>
            </div>
            <div class="gc-hero-actions">
                <a href="/GreenCycle/pages/products.php" class="gc-btn gc-btn-accent">
                    Jelajahi Produk Daur Ulang &rarr;
                </a>
            </div>
        </div>

        <!-- ECO-IMPACT CARD -->
        <div class="gc-eco-card">
            <div class="gc-eco-leaf-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 8C8 10 5.9 16.17 3.82 21.34l1.89.66.95-2.3c.48.17.98.3 1.34.3C19 20 22 3 22 3c-1 2-8 2.25-13 3.25S2 11.5 2 13.5s1.75 3.75 1.75 3.75C7 8 17 8 17 8z"/></svg>
            </div>
            <div class="gc-eco-text" style="flex: 1;">
                <h4>Kontribusi Daur Ulang Kamu</h4>
                <p>
                    Kamu telah menyelamatkan <strong><?= $stats['items_rescued'] ?> barang daur ulang</strong> melalui pesanan yang telah diselesaikan. Mari terus berbelanja secara bertanggung jawab!
                </p>
            </div>
            <a href="/GreenCycle/pages/products.php" class="gc-btn gc-btn-sm gc-btn-primary" style="white-space: nowrap;">
                Dukung Lebih Banyak Produk
            </a>
        </div>

        <!-- STAT CARDS CUSTOMER -->
        <div class="gc-stats-grid">
            <div class="gc-stat-card theme-blue">
                <div class="gc-stat-top">
                    <div>
                        <div class="gc-stat-label">Pesanan Berjalan</div>
                        <div class="gc-stat-value gc-count-up" data-count="<?= $stats['active_orders'] ?>">
                            <?= $stats['active_orders'] ?>
                        </div>
                    </div>
                    <div class="gc-stat-icon-wrapper">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                    </div>
                </div>
                <div class="gc-stat-footer">
                    <span>Sedang dikirim / diproses</span>
                    <span class="gc-stat-trend up">Aktif</span>
                </div>
            </div>

            <div class="gc-stat-card theme-emerald">
                <div class="gc-stat-top">
                    <div>
                        <div class="gc-stat-label">Pesanan Sukses</div>
                        <div class="gc-stat-value gc-count-up" data-count="<?= $stats['completed_orders'] ?>">
                            <?= $stats['completed_orders'] ?>
                        </div>
                    </div>
                    <div class="gc-stat-icon-wrapper">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
                    </div>
                </div>
                <div class="gc-stat-footer">
                    <span>Total Belanja: Rp <?= number_format($stats['total_spent'], 0, ',', '.') ?></span>
                    <span class="gc-stat-trend up">Selesai</span>
                </div>
            </div>

            <div class="gc-stat-card theme-amber">
                <div class="gc-stat-top">
                    <div>
                        <div class="gc-stat-label">Keranjang Belanja</div>
                        <div class="gc-stat-value gc-count-up" data-count="<?= $stats['cart_count'] ?>">
                            <?= $stats['cart_count'] ?>
                        </div>
                    </div>
                    <div class="gc-stat-icon-wrapper">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    </div>
                </div>
                <div class="gc-stat-footer">
                    <span>Total: Rp <?= number_format($cartTotal, 0, ',', '.') ?></span>
                    <a href="/GreenCycle/pages/cart.php" style="font-weight:700;">Checkout &rarr;</a>
                </div>
            </div>

            <div class="gc-stat-card theme-purple">
                <div class="gc-stat-top">
                    <div>
                        <div class="gc-stat-label">Ulasan Diberikan</div>
                        <div class="gc-stat-value gc-count-up" data-count="<?= count($reviews) ?>">
                            <?= count($reviews) ?>
                        </div>
                    </div>
                    <div class="gc-stat-icon-wrapper">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    </div>
                </div>
                <div class="gc-stat-footer">
                    <span>Membantu penjual daur ulang</span>
                    <span class="gc-stat-trend up">Apresiasi</span>
                </div>
            </div>
        </div>

        <!-- PELACAK STATUS PESANAN INTERAKTIF (STEPPER) -->
        <?php if ($trackedOrder): ?>
            <?php 
                $stVal = $trackedOrder['status'];
                $steps = [
                    'pending' => 1,
                    'processing' => 2,
                    'shipped' => 3,
                    'completed' => 4
                ];
                $currentStepNum = $steps[$stVal] ?? 1;
            ?>
            <div class="gc-panel">
                <div class="gc-panel-header">
                    <div class="gc-panel-title">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                        Pelacak Status Pesanan Terkini (#<?= e($trackedOrder['order_code']) ?>)
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="gc-badge badge-<?= e($stVal) ?>"><?= e($stVal) ?></span>
                        <?php if ($stVal === 'shipped'): ?>
                            <form action="/GreenCycle/customer/confirm-order.php" method="POST" style="margin: 0;" onsubmit="return confirm('Konfirmasi bahwa barang daur ulang sudah Anda terima dengan baik?');">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="order_id" value="<?= $trackedOrder['id'] ?>">
                                <button type="submit" class="gc-btn gc-btn-sm gc-btn-accent">
                                    ✓ Konfirmasi Diterima
                                </button>
                            </form>
                        <?php endif; ?>
                        <a href="/GreenCycle/pages/order-detail.php?id=<?= $trackedOrder['id'] ?>" class="gc-btn gc-btn-sm gc-btn-secondary">
                            Lihat Detail Pesanan &rarr;
                        </a>
                    </div>
                </div>
                <div class="gc-panel-body">
                    <?php if ($stVal === 'cancelled'): ?>
                        <div class="gc-alert gc-alert-error" style="margin-bottom: 0;">
                            Pesanan ini telah dibatalkan. Silakan hubungi penjual atau periksa riwayat transaksi Anda.
                        </div>
                    <?php else: ?>
                        <div class="gc-stepper">
                            <!-- Step 1: Menunggu -->
                            <div class="gc-step-item <?= $currentStepNum > 1 ? 'completed' : ($currentStepNum === 1 ? 'active' : '') ?>">
                                <div class="gc-step-circle">1</div>
                                <div class="gc-step-title">Menunggu Verifikasi</div>
                            </div>

                            <!-- Step 2: Diproses -->
                            <div class="gc-step-item <?= $currentStepNum > 2 ? 'completed' : ($currentStepNum === 2 ? 'active' : '') ?>">
                                <div class="gc-step-circle">2</div>
                                <div class="gc-step-title">Diproses Toko</div>
                            </div>

                            <!-- Step 3: Dikirim -->
                            <div class="gc-step-item <?= $currentStepNum > 3 ? 'completed' : ($currentStepNum === 3 ? 'active' : '') ?>">
                                <div class="gc-step-circle">3</div>
                                <div class="gc-step-title">Sedang Dikirim</div>
                            </div>

                            <!-- Step 4: Selesai -->
                            <div class="gc-step-item <?= $currentStepNum >= 4 ? 'completed active' : '' ?>">
                                <div class="gc-step-circle">4</div>
                                <div class="gc-step-title">Sampai & Selesai</div>
                            </div>
                        </div>

                        <div style="background: var(--gc-surface-alt); border-radius: var(--gc-radius-md); padding: 14px 18px; margin-top: 16px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                            <div>
                                Toko: <strong><?= e($trackedOrder['store_name']) ?></strong> • Total: <strong style="color: var(--gc-primary);">Rp <?= number_format($trackedOrder['total_amount'], 0, ',', '.') ?></strong>
                            </div>
                            <span style="color: var(--gc-text-muted);">
                                Dipesan pada <?= date('d M Y, H:i', strtotime($trackedOrder['order_date'])) ?> WIB
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- TABBED PANELS CUSTOMER -->
        <div class="gc-panel">
            <div class="gc-tabs-header">
                <button class="gc-tab-btn active" data-tab="tabCustOrders">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/></svg>
                    Pesanan Saya
                </button>
                <button class="gc-tab-btn" data-tab="tabCustCart">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
                    Keranjang Belanja (<?= $stats['cart_count'] ?>)
                </button>
                <button class="gc-tab-btn" data-tab="tabCustReviews">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    Ulasan Saya
                </button>
                <button class="gc-tab-btn" data-tab="tabCustProfile">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Informasi Akun & Alamat
                </button>
            </div>

            <!-- TAB 1: PESANAN SAYA -->
            <div class="gc-tab-pane active" id="tabCustOrders">
                <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light);" class="gc-filter-bar">
                    <div class="gc-search-box">
                        <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Cari kode pesanan atau nama toko..." data-table-search="#tableCustomerOrders">
                    </div>
                    <select class="gc-select-filter" data-table-status="#tableCustomerOrders">
                        <option value="all">Semua Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="gc-table-responsive">
                    <table class="gc-table" id="tableCustomerOrders">
                        <thead>
                            <tr>
                                <th>Kode Order</th>
                                <th>Toko Mitra</th>
                                <th>Tanggal</th>
                                <th>Total Belanja</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:36px; color:var(--gc-text-muted);">
                                        Kamu belum pernah membuat pesanan di GreenCycle.
                                        <br><br>
                                        <a href="/GreenCycle/pages/products.php" class="gc-btn gc-btn-sm gc-btn-primary">
                                            Mulai Belanja Daur Ulang &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $ord): ?>
                                    <tr data-status="<?= e($ord['status']) ?>">
                                        <td>
                                            <strong style="color: var(--gc-primary); font-family: monospace; font-size: 14px;">
                                                #<?= e($ord['order_code']) ?>
                                            </strong>
                                        </td>
                                        <td><strong><?= e($ord['store_name']) ?></strong></td>
                                        <td style="color: var(--gc-text-muted); font-size: 12.5px;">
                                            <?= date('d M Y, H:i', strtotime($ord['order_date'])) ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--gc-forest);">
                                                Rp <?= number_format($ord['total_amount'], 0, ',', '.') ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="gc-badge badge-<?= e($ord['status']) ?>">
                                                <?= e($ord['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px; align-items: center;">
                                                <a href="/GreenCycle/pages/order-detail.php?id=<?= $ord['id'] ?>" class="gc-btn gc-btn-sm gc-btn-secondary">
                                                    Detail
                                                </a>
                                                <?php if ($ord['status'] === 'shipped'): ?>
                                                    <form action="/GreenCycle/customer/confirm-order.php" method="POST" style="margin: 0;" onsubmit="return confirm('Konfirmasi bahwa barang daur ulang sudah Anda terima?');">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                                        <button type="submit" class="gc-btn gc-btn-sm gc-btn-accent" title="Konfirmasi barang sampai">
                                                            ✓ Diterima
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 2: KERANJANG BELANJA -->
            <div class="gc-tab-pane" id="tabCustCart">
                <div style="padding: 24px;">
                    <?php if (empty($cartItems)): ?>
                        <div style="text-align:center; padding: 36px; color: var(--gc-text-muted);">
                            <p style="margin-bottom: 16px;">Keranjang belanja daur ulangmu masih kosong.</p>
                            <a href="/GreenCycle/pages/products.php" class="gc-btn gc-btn-primary">
                                Jelajahi Katalog Sekarang
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <?php foreach ($cartItems as $cItem): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; border: 1px solid var(--gc-border); border-radius: var(--gc-radius-md); padding: 14px; background: var(--gc-surface-alt); flex-wrap: wrap; gap: 12px;">
                                    <div style="display: flex; align-items: center; gap: 14px;">
                                        <?php if (!empty($cItem['product_photo'])): ?>
                                            <img src="/GreenCycle/uploads/<?= e($cItem['product_photo']) ?>" alt="<?= e($cItem['product_name']) ?>" class="gc-product-thumb" onerror="this.style.display='none';">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= e($cItem['product_name']) ?></strong>
                                            <div style="font-size: 12px; color: var(--gc-text-muted);">
                                                Toko: <?= e($cItem['store_name']) ?> • Qty: <?= (int)$cItem['quantity'] ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 18px;">
                                        <div style="font-weight: 700; color: var(--gc-primary); font-size: 15px;">
                                            Rp <?= number_format((float)$cItem['price'] * (int)$cItem['quantity'], 0, ',', '.') ?>
                                        </div>
                                        <a href="/GreenCycle/pages/cart.php" class="gc-btn gc-btn-sm gc-btn-secondary">
                                            Buka Keranjang
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 18px; padding-top: 16px; border-top: 2px solid var(--gc-border);">
                                <div>
                                    <span style="color: var(--gc-text-muted);">Total Tagihan Keranjang:</span>
                                    <div style="font-size: 20px; font-weight: 800; color: var(--gc-forest);">
                                        Rp <?= number_format($cartTotal, 0, ',', '.') ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <a href="/GreenCycle/pages/cart.php" class="gc-btn gc-btn-secondary">
                                        Kelola Keranjang
                                    </a>
                                    <a href="/GreenCycle/pages/checkout.php" class="gc-btn gc-btn-primary">
                                        Checkout Sekarang &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: ULASAN SAYA -->
            <div class="gc-tab-pane" id="tabCustReviews">
                <div style="padding: 24px;">
                    <?php if (empty($reviews)): ?>
                        <p style="text-align:center; padding: 28px; color: var(--gc-text-muted);">
                            Kamu belum memberikan ulasan untuk produk yang pernah dibeli.
                        </p>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <?php foreach ($reviews as $rv): ?>
                                <div style="background: var(--gc-surface-alt); border: 1px solid var(--gc-border); border-radius: var(--gc-radius-md); padding: 16px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <strong><?= e($rv['product_name']) ?></strong>
                                        <div style="color: #f59e0b; font-size: 15px;">
                                            <?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?>
                                        </div>
                                    </div>
                                    <p style="font-size: 13.5px; color: var(--gc-text); line-height: 1.5;">
                                        "<?= e($rv['comment'] ?? 'Tidak ada komentar.') ?>"
                                    </p>
                                    <div style="font-size: 11.5px; color: var(--gc-text-muted); margin-top: 8px;">
                                        Diulas pada <?= date('d M Y, H:i', strtotime($rv['created_at'])) ?> WIB
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 4: INFORMASI AKUN & ALAMAT -->
            <div class="gc-tab-pane" id="tabCustProfile">
                <div style="padding: 24px; max-width: 650px;">
                    <form action="/GreenCycle/customer/update-profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="profileName">Nama Lengkap *</label>
                            <input type="text" id="profileName" name="name" class="gc-input" style="padding-left: 14px;" value="<?= e($customer['name'] ?? $customerName) ?>" required>
                        </div>

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="profileEmail">Alamat Email (Akun)</label>
                            <input type="email" id="profileEmail" class="gc-input" style="padding-left: 14px; background: #eaeeea; cursor: not-allowed;" value="<?= e($customer['email'] ?? $customerEmail) ?>" disabled>
                            <small style="color: var(--gc-text-muted); font-size: 11.5px; margin-top: 4px; display: block;">Email digunakan untuk identitas login akun.</small>
                        </div>

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="profilePhone">Nomor Telepon / WhatsApp</label>
                            <input type="text" id="profilePhone" name="phone" class="gc-input" style="padding-left: 14px;" value="<?= e($customer['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                        </div>

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="profileAddress">Alamat Pengiriman Utama</label>
                            <textarea id="profileAddress" name="address" class="gc-input" style="padding: 10px 14px; height: 95px; resize: vertical;" placeholder="Tuliskan jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota/kabupaten, dan kode pos..."><?= e($customer['address'] ?? '') ?></textarea>
                        </div>

                        <div style="display: flex; gap: 12px; align-items: center; margin-top: 20px;">
                            <button type="submit" class="gc-btn gc-btn-primary">
                                Simpan Perubahan Profil
                            </button>
                            <a href="/GreenCycle/pages/products.php" class="gc-btn gc-btn-secondary">
                                Belanja Daur Ulang
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- REKOMENDASI PRODUK HIJAU PILIHAN -->
        <?php if (!empty($recommendedProducts)): ?>
            <div style="margin-top: 36px; margin-bottom: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                    <div>
                        <h3 style="font-size: 19px; font-weight: 800; color: var(--gc-forest);">Rekomendasi Produk Daur Ulang</h3>
                        <p style="font-size: 13px; color: var(--gc-text-muted);">Pilihan produk terbaik untuk gaya hidup ramah lingkungan</p>
                    </div>
                    <a href="/GreenCycle/pages/products.php" class="gc-btn gc-btn-secondary gc-btn-sm">
                        Lihat Semua Katalog &rarr;
                    </a>
                </div>

                <div class="gc-product-grid">
                    <?php foreach ($recommendedProducts as $rp): ?>
                        <div class="gc-product-card">
                            <?php if (!empty($rp['product_photo'])): ?>
                                <img src="/GreenCycle/uploads/<?= e($rp['product_photo']) ?>" alt="<?= e($rp['product_name']) ?>" class="gc-product-img" onerror="this.src='/GreenCycle/assets/images/greencycle-logo.png'; this.style.objectFit='contain';">
                            <?php else: ?>
                                <div class="gc-product-img" style="display:flex; align-items:center; justify-content:center; background:var(--gc-surface-alt); color:var(--gc-text-light);">
                                    ♻ GreenCycle
                                </div>
                            <?php endif; ?>
                            <div class="gc-product-body">
                                <span class="gc-product-cat"><?= e($rp['category_name']) ?></span>
                                <h4 class="gc-product-title"><?= e($rp['product_name']) ?></h4>
                                <div style="font-size: 11.5px; color: var(--gc-text-muted); margin-bottom: 8px;">
                                    Toko: <?= e($rp['store_name']) ?>
                                </div>
                                <div class="gc-product-price">
                                    Rp <?= number_format($rp['price'], 0, ',', '.') ?>
                                </div>
                                <div style="margin-top: 12px;">
                                    <a href="/GreenCycle/pages/product-detail.php?id=<?= $rp['id'] ?>" class="gc-btn gc-btn-sm gc-btn-primary" style="width: 100%;">
                                        Lihat Produk
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <footer style="background: #ffffff; border-top: 1px solid var(--gc-border); padding: 24px; text-align: center; color: var(--gc-text-muted); font-size: 13px; margin-top: 50px;">
        &copy; <?= date('Y') ?> GreenCycle Marketplace. Mendukung SDG 8 (Pekerjaan Layak) & SDG 12 (Konsumsi Bertanggung Jawab).
    </footer>

    <script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
