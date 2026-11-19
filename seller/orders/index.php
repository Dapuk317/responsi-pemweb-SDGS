<?php
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(['seller']);

$userId = (int)$_SESSION['user_id'];
$sellerName = $_SESSION['name'] ?? 'Penjual';

// Ambil info toko seller
$stmt = $conn->prepare("SELECT id, store_name FROM stores WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

$storeId = $store ? (int)$store['id'] : 0;
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$orders = [];
if ($storeId > 0) {
    $oStmt = $conn->prepare("
        SELECT 
            o.id,
            o.order_code,
            o.order_date,
            o.shipping_address,
            o.subtotal,
            o.shipping_cost,
            o.total_amount,
            o.status,
            o.notes,
            u.name AS customer_name,
            u.phone AS customer_phone,
            u.email AS customer_email,
            t.payment_method,
            t.payment_status
        FROM orders o
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN transactions t ON t.order_id = o.id
        WHERE o.store_id = ?
        ORDER BY o.order_date DESC
    ");
    $oStmt->bind_param("i", $storeId);
    $oStmt->execute();
    $orders = $oStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $oStmt->close();

    // Ambil item produk untuk setiap pesanan
    foreach ($orders as &$ord) {
        $iStmt = $conn->prepare("
            SELECT product_name, price, quantity, subtotal, product_photo
            FROM order_items
            WHERE order_id = ?
        ");
        $iStmt->bind_param("i", $ord['id']);
        $iStmt->execute();
        $ord['items'] = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $iStmt->close();
    }
    unset($ord);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan Masuk - <?= e($store['store_name'] ?? 'Toko Saya') ?> | GreenCycle</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
    <style>
        .gc-order-card {
            background: var(--gc-surface);
            border: 1px solid var(--gc-border);
            border-radius: var(--gc-radius-lg);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--gc-shadow-sm);
        }
        .gc-order-card-header {
            padding: 16px 20px;
            background: var(--gc-surface-alt);
            border-bottom: 1px solid var(--gc-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .gc-order-card-body {
            padding: 20px;
        }
        .gc-order-items-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin: 14px 0;
            border-top: 1px solid var(--gc-border-light);
            border-bottom: 1px solid var(--gc-border-light);
            padding: 12px 0;
        }
        .gc-order-card-footer {
            padding: 14px 20px;
            background: #ffffff;
            border-top: 1px solid var(--gc-border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
    </style>
</head>
<body>

<div class="gc-app">
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
            <button class="gc-sidebar-close" id="gcSidebarClose">&times;</button>
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
            <a href="/GreenCycle/seller/dashboard.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard Toko
            </a>
            <a href="/GreenCycle/seller/products/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                Produk Daur Ulang
            </a>
            <a href="/GreenCycle/seller/orders/index.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Pesanan Masuk
                <span class="gc-nav-badge"><?= count($orders) ?></span>
            </a>
            <a href="/GreenCycle/seller/store/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1zm-9 4H6v-4h6v4z"/></svg>
                Pengaturan Toko
            </a>
        </nav>

        <div class="gc-sidebar-footer">
            <form action="/GreenCycle/auth/logout.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="gc-btn-logout-sidebar">Logout</button>
            </form>
        </div>
    </aside>

    <main class="gc-main">
        <header class="gc-topbar">
            <div class="gc-topbar-left">
                <button class="gc-menu-toggle" id="gcMenuToggle">☰</button>
                <div class="gc-topbar-title">
                    <span>Arus Pesanan Masuk Toko</span>
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

            <div class="gc-hero-banner" style="background: linear-gradient(135deg, #10482d 0%, #176b43 100%); margin-bottom: 24px;">
                <div class="gc-hero-info">
                    <h2 class="gc-hero-title" style="font-size: 22px;">Proses Pesanan Pelanggan</h2>
                    <p class="gc-hero-subtitle">Perbarui status pengiriman produk daur ulang Anda secara berkala agar pembeli dapat melacak paket dengan nyaman.</p>
                </div>
            </div>

            <?php if (empty($orders)): ?>
                <div class="gc-panel">
                    <div style="text-align: center; padding: 48px; color: var(--gc-text-muted);">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 12px; color: var(--gc-primary);"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                        <h3>Belum Ada Pesanan Masuk</h3>
                        <p style="margin-top: 6px;">Saat pelanggan membeli produk daur ulang dari tokomu, daftarnya akan muncul di sini.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $ord): ?>
                    <div class="gc-order-card">
                        <div class="gc-order-card-header">
                            <div>
                                <strong style="font-size: 15px; color: var(--gc-primary); font-family: monospace;">
                                    #<?= e($ord['order_code']) ?>
                                </strong>
                                <span style="color: var(--gc-text-muted); font-size: 12px; margin-left: 10px;">
                                    <?= date('d M Y, H:i', strtotime($ord['order_date'])) ?> WIB
                                </span>
                            </div>
                            <div>
                                <span class="gc-badge badge-<?= e($ord['status']) ?>">
                                    Status: <?= e($ord['status']) ?>
                                </span>
                                <?php if (!empty($ord['payment_status'])): ?>
                                    <span class="gc-badge badge-<?= $ord['payment_status'] === 'verified' ? 'completed' : 'pending' ?>" style="margin-left: 6px;">
                                        Bayar: <?= e($ord['payment_status']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="gc-order-card-body">
                            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px;">
                                <div>
                                    <h4 style="font-size: 13.5px; color: var(--gc-forest); margin-bottom: 8px;">Daftar Barang Pesanan:</h4>
                                    <div class="gc-order-items-list">
                                        <?php if (!empty($ord['items'])): ?>
                                            <?php foreach ($ord['items'] as $it): ?>
                                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                                                    <div>
                                                        <strong><?= e($it['product_name']) ?></strong>
                                                        <span style="color: var(--gc-text-muted);"> × <?= (int)$it['quantity'] ?> unit</span>
                                                    </div>
                                                    <div style="font-weight: 700; color: var(--gc-primary);">
                                                        Rp <?= number_format($it['subtotal'], 0, ',', '.') ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($ord['notes'])): ?>
                                        <div style="font-size: 12.5px; color: var(--gc-text-muted); background: var(--gc-surface-alt); padding: 8px 12px; border-radius: 6px;">
                                            <strong>Catatan Pembeli:</strong> "<?= e($ord['notes']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div style="background: var(--gc-surface-alt); padding: 14px; border-radius: var(--gc-radius-md); font-size: 12.5px; border: 1px solid var(--gc-border-light);">
                                    <h4 style="font-size: 13px; color: var(--gc-forest); margin-bottom: 8px;">Tujuan Pengiriman:</h4>
                                    <div><strong><?= e($ord['customer_name']) ?></strong> (<?= e($ord['customer_phone'] ?? '-') ?>)</div>
                                    <div style="color: var(--gc-text-muted); margin-top: 4px; line-height: 1.4;"><?= e($ord['shipping_address']) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="gc-order-card-footer">
                            <div>
                                <span style="font-size: 12.5px; color: var(--gc-text-muted);">Total Tagihan Pesanan:</span>
                                <strong style="font-size: 17px; color: var(--gc-forest); margin-left: 6px;">
                                    Rp <?= number_format($ord['total_amount'], 0, ',', '.') ?>
                                </strong>
                            </div>

                            <!-- Tombol Aksi Status Pesanan -->
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if ($ord['status'] === 'pending'): ?>
                                    <form action="/GreenCycle/seller/orders/update_status.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                        <input type="hidden" name="new_status" value="processing">
                                        <button type="submit" class="gc-btn gc-btn-sm gc-btn-primary">
                                            ✓ Proses Pesanan
                                        </button>
                                    </form>
                                <?php elseif ($ord['status'] === 'processing'): ?>
                                    <form action="/GreenCycle/seller/orders/update_status.php" method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                        <input type="hidden" name="new_status" value="shipped">
                                        <button type="submit" class="gc-btn gc-btn-sm gc-btn-accent">
                                            🚚 Kirim Barang ke Kurir
                                        </button>
                                    </form>
                                <?php elseif ($ord['status'] === 'shipped'): ?>
                                    <span style="font-size: 12.5px; color: #7c3aed; font-weight: 600;">
                                        Menunggu konfirmasi penerimaan pembeli...
                                    </span>
                                <?php elseif ($ord['status'] === 'completed'): ?>
                                    <span style="font-size: 12.5px; color: #059669; font-weight: 700;">
                                        ✓ Pesanan Telah Selesai
                                    </span>
                                <?php elseif ($ord['status'] === 'cancelled'): ?>
                                    <span style="font-size: 12.5px; color: #dc2626; font-weight: 600;">
                                        ✕ Pesanan Dibatalkan
                                    </span>
                                <?php endif; ?>

                                <?php if ($ord['status'] === 'pending'): ?>
                                    <form action="/GreenCycle/seller/orders/update_status.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan pesanan ini?');" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                        <input type="hidden" name="new_status" value="cancelled">
                                        <button type="submit" class="gc-btn gc-btn-sm" style="background: rgba(220, 38, 38, 0.1); color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.2);">
                                            Batalkan
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
