<?php
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(['admin']);

$adminName = $_SESSION['name'] ?? 'Admin';
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

$transactions = [];
try {
    $q = $conn->query("
        SELECT 
            t.id,
            t.order_id,
            t.payment_method,
            t.payment_status,
            t.payment_proof,
            t.payment_date,
            t.verified_at,
            t.created_at,
            o.order_code,
            o.total_amount,
            o.status AS order_status,
            u.name AS customer_name,
            u.email AS customer_email,
            COALESCE(s.store_name, 'Toko tidak ditemukan') AS store_name
        FROM transactions t
        INNER JOIN orders o ON t.order_id = o.id
        INNER JOIN users u ON o.user_id = u.id
        LEFT JOIN stores s ON o.store_id = s.id
        ORDER BY t.id DESC
    ");
    if ($q) {
        $transactions = $q->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    error_log("Transactions query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran - GreenCycle Admin</title>
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
            <a href="/GreenCycle/admin/transactions/index.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                Verifikasi Pembayaran
                <span class="gc-nav-badge"><?= count($transactions) ?></span>
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
            <a href="/GreenCycle/admin/stores/index.php" class="gc-nav-item">
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
                    <span>Verifikasi Pembayaran Pesanan</span>
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
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                        Antrean Verifikasi Transaksi Masuk
                    </div>
                    <span style="font-size: 13px; color: var(--gc-text-muted);">Tinjau bukti transfer & setujui pesanan</span>
                </div>

                <div style="padding: 16px 24px; border-bottom: 1px solid var(--gc-border-light);" class="gc-filter-bar">
                    <div class="gc-search-box">
                        <svg class="gc-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" placeholder="Cari kode order, pembeli, toko..." data-table-search="#tableAdminTx">
                    </div>
                    <select class="gc-select-filter" data-table-status="#tableAdminTx">
                        <option value="all">Semua Status Bayar</option>
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <div class="gc-table-responsive">
                    <table class="gc-table" id="tableAdminTx">
                        <thead>
                            <tr>
                                <th>Kode Order</th>
                                <th>Pembeli</th>
                                <th>Toko Tujuan</th>
                                <th>Metode Bayar</th>
                                <th>Total Tagihan</th>
                                <th>Bukti Transfer</th>
                                <th>Status Pembayaran</th>
                                <th>Aksi Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: var(--gc-text-muted);">
                                        Belum ada data transaksi pembayaran yang tercatat dalam database.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr data-status="<?= e($tx['payment_status']) ?>">
                                        <td>
                                            <strong style="color: var(--gc-primary); font-family: monospace;">
                                                #<?= e($tx['order_code']) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <div><strong><?= e($tx['customer_name']) ?></strong></div>
                                            <div style="font-size: 11.5px; color: var(--gc-text-muted);"><?= e($tx['customer_email']) ?></div>
                                        </td>
                                        <td><strong><?= e($tx['store_name']) ?></strong></td>
                                        <td>
                                            <span style="font-size: 12px; text-transform: uppercase; font-weight: 600;">
                                                <?= str_replace('_', ' ', $tx['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color: var(--gc-forest);">
                                                Rp <?= number_format($tx['total_amount'], 0, ',', '.') ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($tx['payment_proof'])): ?>
                                                <a href="/GreenCycle/uploads/<?= e($tx['payment_proof']) ?>" target="_blank" class="gc-btn gc-btn-sm gc-btn-secondary" style="font-size: 11.5px;">
                                                    📷 Lihat Bukti
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--gc-text-light); font-size: 12px;">Tanpa Bukti</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="gc-badge badge-<?= $tx['payment_status'] === 'verified' ? 'completed' : ($tx['payment_status'] === 'rejected' ? 'cancelled' : 'pending') ?>">
                                                <?= e($tx['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($tx['payment_status'] === 'pending'): ?>
                                                <div style="display: flex; gap: 6px;">
                                                    <form action="/GreenCycle/admin/transactions/verify.php" method="POST" style="margin: 0;">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
                                                        <input type="hidden" name="order_id" value="<?= $tx['order_id'] ?>">
                                                        <input type="hidden" name="action" value="verify">
                                                        <button type="submit" class="gc-btn gc-btn-sm gc-btn-primary" title="Verifikasi Pembayaran">
                                                            ✓ Terima
                                                        </button>
                                                    </form>
                                                    <form action="/GreenCycle/admin/transactions/verify.php" method="POST" onsubmit="return confirm('Tolak bukti pembayaran ini?');" style="margin: 0;">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                        <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
                                                        <input type="hidden" name="order_id" value="<?= $tx['order_id'] ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="gc-btn gc-btn-sm" style="background: rgba(220, 38, 38, 0.1); color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.2);" title="Tolak Pembayaran">
                                                            ✕ Tolak
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 12px; color: var(--gc-text-muted);">Sudah Ditinjau</span>
                                            <?php endif; ?>
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
