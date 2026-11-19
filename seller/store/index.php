<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['seller']);

$userId = (int) $_SESSION['user_id'];
$sellerName = $_SESSION['name'] ?? 'Penjual GreenCycle';
$error = '';
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

function getSellerStore(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare("SELECT id, store_name, description, address, phone, logo, status, created_at FROM stores WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $store ?: null;
}

$store = getSellerStore($conn, $userId);

$form = [
    'store_name' => $store['store_name'] ?? '',
    'description' => $store['description'] ?? '',
    'address' => $store['address'] ?? '',
    'phone' => $store['phone'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $form = [
        'store_name' => trim($_POST['store_name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
    ];

    if ($form['store_name'] === '') {
        $error = 'Nama toko wajib diisi.';
    } elseif (mb_strlen($form['store_name']) > 150) {
        $error = 'Nama toko maksimal 150 karakter.';
    } elseif (mb_strlen($form['phone']) > 20) {
        $error = 'Nomor telepon maksimal 20 karakter.';
    }

    $newLogo = null;
    if (!$error && isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['store_logo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload logo gagal. Silakan coba kembali.';
        } elseif ($file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
            $error = 'Ukuran logo maksimal 2 MB.';
        } else {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset($allowed[$mime]) || getimagesize($file['tmp_name']) === false) {
                $error = 'Logo harus berupa gambar JPG, PNG, atau WEBP.';
            } else {
                $uploadDir = __DIR__ . '/../../uploads/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    $error = 'Folder upload tidak dapat dibuat.';
                } else {
                    $newLogo = 'store_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
                    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newLogo)) {
                        $newLogo = null;
                        $error = 'Logo gagal disimpan.';
                    }
                }
            }
        }
    }

    if (!$error) {
        try {
            if ($store) {
                if ($newLogo !== null) {
                    $stmt = $conn->prepare("UPDATE stores SET store_name = ?, description = ?, address = ?, phone = ?, logo = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param('sssssii', $form['store_name'], $form['description'], $form['address'], $form['phone'], $newLogo, $store['id'], $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE stores SET store_name = ?, description = ?, address = ?, phone = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param('ssssii', $form['store_name'], $form['description'], $form['address'], $form['phone'], $store['id'], $userId);
                }
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash'] = 'Pengaturan toko berhasil diperbarui.';
            } else {
                $stmt = $conn->prepare("INSERT INTO stores (user_id, store_name, description, address, phone, logo) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssss', $userId, $form['store_name'], $form['description'], $form['address'], $form['phone'], $newLogo);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash'] = 'Profil toko berhasil dibuat.';
            }
            header('Location: index.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            error_log('Seller store settings: ' . $e->getMessage());
            $error = 'Gagal menyimpan pengaturan toko. Periksa koneksi database lalu coba lagi.';
        }
    }
}

$store = getSellerStore($conn, $userId);
$storeName = $store['store_name'] ?? $sellerName;
$initials = strtoupper(substr($storeName, 0, 2));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Toko | GreenCycle</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
    <style>
        .store-settings-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(260px,.85fr);gap:22px;align-items:start}
        .store-card{background:var(--gc-surface);border:1px solid var(--gc-border);border-radius:var(--gc-radius-lg);box-shadow:var(--gc-shadow-sm);overflow:hidden;margin-bottom:20px}
        .store-card-head{padding:20px 24px;border-bottom:1px solid var(--gc-border-light)}
        .store-card-head h2{font-size:16px;margin:0 0 5px;color:var(--gc-text);font-weight:750}
        .store-card-head p{margin:0;color:var(--gc-text-muted);font-size:13px}
        .store-card-body{padding:22px 24px}
        .store-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}
        .store-form-group{margin-bottom:18px}
        .store-form-group.full{grid-column:1/-1}
        .store-form-group label{display:block;font-size:13px;font-weight:650;color:var(--gc-text);margin-bottom:7px}
        .store-form-group input,.store-form-group textarea{width:100%;box-sizing:border-box;padding:12px 13px;border:1px solid var(--gc-border);border-radius:10px;background:var(--gc-surface-alt);font:inherit;font-size:13px;color:var(--gc-text);transition:.2s}
        .store-form-group input:focus,.store-form-group textarea:focus{outline:none;border-color:var(--gc-primary);background:#fff;box-shadow:0 0 0 3px rgba(23,107,67,.1)}
        .store-form-group textarea{resize:vertical;min-height:105px}
        .store-help{font-size:11px;color:var(--gc-text-muted);margin-top:6px;display:block}
        .store-profile{text-align:center;padding:26px 22px}
        .store-logo{width:92px;height:92px;border-radius:22px;background:#e8f5ed;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;overflow:hidden;color:#176b43;font-size:25px;font-weight:800}
        .store-logo img{width:100%;height:100%;object-fit:cover}
        .store-profile h3{font-size:17px;margin:0 0 7px;color:var(--gc-text)}
        .store-profile p{font-size:12px;color:var(--gc-text-muted);margin:0 0 14px;line-height:1.6;overflow-wrap:anywhere}
        .store-status{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border-radius:30px;font-size:11px;font-weight:700;background:#e8f7ee;color:#167343}
        .store-status.inactive{background:#fff4e5;color:#a15c00}
        .store-info-list{border-top:1px solid var(--gc-border-light);text-align:left;padding-top:16px;margin-top:18px}
        .store-info-row{display:flex;gap:12px;justify-content:space-between;padding:9px 0;font-size:12px;border-bottom:1px solid var(--gc-border-light)}
        .store-info-row span:first-child{color:var(--gc-text-muted)}.store-info-row span:last-child{font-weight:600;text-align:right;overflow-wrap:anywhere;max-width:58%}
        .store-logo-upload{display:flex;align-items:center;gap:15px;padding:14px;border:1px dashed var(--gc-border);border-radius:12px;background:var(--gc-surface-alt)}
        .store-logo-upload input{max-width:100%;border:0;padding:0;background:transparent}
        .store-actions{display:flex;justify-content:flex-end;gap:10px;padding-top:6px}
        @media(max-width:850px){.store-settings-grid{grid-template-columns:1fr}.store-profile{padding:22px}}
        @media(max-width:560px){.store-form-grid{grid-template-columns:1fr}.store-form-group.full{grid-column:auto}.store-card-head,.store-card-body{padding:17px}.store-actions{flex-direction:column}.store-actions .gc-btn{width:100%;justify-content:center}}
    </style>
</head>
<body>
<div class="gc-app">
    <div class="gc-sidebar-backdrop" id="gcSidebarBackdrop"></div>
    <aside class="gc-sidebar" id="gcSidebar">
        <div class="gc-sidebar-header">
            <a href="/GreenCycle/seller/dashboard.php" class="gc-sidebar-logo">
                <img src="/GreenCycle/assets/images/greencycle-logo.png" alt="GreenCycle Logo" onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
                <span class="brand-fallback" style="display:none;font-size:20px;font-weight:800;color:#fff">Green<span style="color:var(--gc-accent)">Cycle</span></span>
            </a>
            <button class="gc-sidebar-close" id="gcSidebarClose" aria-label="Tutup Menu">&times;</button>
        </div>
        <div class="gc-sidebar-role-badge">
            <div class="gc-role-avatar" style="background:linear-gradient(135deg,#10b981,#047857)"><?= e($initials) ?></div>
            <div class="gc-role-info"><div class="gc-role-name"><?= e($storeName) ?></div><div class="gc-role-tag" style="color:#34d399">Mitra Seller Daur Ulang</div></div>
        </div>
        <nav class="gc-sidebar-nav">
            <div class="gc-nav-group-title">Menu Toko</div>
            <a href="/GreenCycle/seller/dashboard.php" class="gc-nav-item"><svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>Dashboard Toko</a>
            <a href="/GreenCycle/seller/products/index.php" class="gc-nav-item"><svg viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>Produk Daur Ulang</a>
            <a href="/GreenCycle/seller/orders/index.php" class="gc-nav-item"><svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM7 11h10v2H7z"/></svg>Pesanan Masuk</a>
            <a href="/GreenCycle/seller/store/index.php" class="gc-nav-item active"><svg viewBox="0 0 24 24"><path d="M20 4H4v2h16V4zm1 10v-2l-1-5H4l-1 5v2h1v6h10v-6h4v6h2v-6h1z"/></svg>Pengaturan Toko</a>
            <div class="gc-nav-group-title">Marketplace</div>
            <a href="/GreenCycle/pages/home.php" class="gc-nav-item" target="_blank"><svg viewBox="0 0 24 24"><path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/></svg>Lihat Marketplace</a>
        </nav>
        <div class="gc-sidebar-footer"><form action="/GreenCycle/auth/logout.php" method="POST"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit" class="gc-btn-logout-sidebar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>Logout Seller</button></form></div>
    </aside>

    <main class="gc-main">
        <header class="gc-topbar">
            <div class="gc-topbar-left"><button class="gc-menu-toggle" id="gcMenuToggle" aria-label="Buka Menu">☰</button><div class="gc-topbar-title"><span>Pengaturan Toko</span></div></div>
            <div class="gc-topbar-right"><div class="gc-topbar-clock" id="gcLiveClock"></div><a href="/GreenCycle/seller/dashboard.php" class="gc-btn gc-btn-secondary gc-btn-sm">Kembali ke Dashboard</a></div>
        </header>

        <div class="gc-content">
            <div style="margin-bottom:22px"><h1 style="font-size:23px;font-weight:800;color:var(--gc-forest);margin:0 0 6px">Pengaturan Toko</h1><p style="font-size:13px;color:var(--gc-text-muted);margin:0">Kelola identitas dan informasi toko yang ditampilkan kepada pelanggan GreenCycle.</p></div>
            <?php if ($success): ?><div class="gc-alert gc-alert-success"><span><?= e($success) ?></span><button type="button" class="gc-alert-close">&times;</button></div><?php endif; ?>
            <?php if ($error): ?><div class="gc-alert gc-alert-error"><span><?= e($error) ?></span><button type="button" class="gc-alert-close">&times;</button></div><?php endif; ?>

            <div class="store-settings-grid">
                <section class="store-card">
                    <div class="store-card-head"><h2>Informasi Toko</h2><p>Pastikan informasi toko selalu benar dan mudah dikenali pelanggan.</p></div>
                    <div class="store-card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="store-form-grid">
                                <div class="store-form-group full"><label for="store_name">Nama Toko <span style="color:#dc2626">*</span></label><input id="store_name" type="text" name="store_name" maxlength="150" required value="<?= e($form['store_name']) ?>" placeholder="Contoh: Mely Store"></div>
                                <div class="store-form-group full"><label for="description">Deskripsi Toko</label><textarea id="description" name="description" rows="4" placeholder="Ceritakan produk dan keunggulan tokomu..."><?= e($form['description']) ?></textarea><small class="store-help">Deskripsi singkat yang membantu pelanggan mengenal tokomu.</small></div>
                                <div class="store-form-group full"><label for="address">Alamat Toko</label><textarea id="address" name="address" rows="3" placeholder="Masukkan alamat lengkap toko"><?= e($form['address']) ?></textarea></div>
                                <div class="store-form-group"><label for="phone">Nomor Telepon Toko</label><input id="phone" type="tel" name="phone" maxlength="20" value="<?= e($form['phone']) ?>" placeholder="08xxxxxxxxxx"></div>
                                <div class="store-form-group"><label for="store_logo">Logo Toko</label><div class="store-logo-upload"><input id="store_logo" type="file" name="store_logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div><small class="store-help">JPG, PNG, WEBP. Maksimal 2 MB.</small></div>
                            </div>
                            <div class="store-actions"><a href="/GreenCycle/seller/dashboard.php" class="gc-btn gc-btn-secondary">Batal</a><button type="submit" class="gc-btn gc-btn-primary">Simpan Pengaturan</button></div>
                        </form>
                    </div>
                </section>

                <aside>
                    <section class="store-card">
                        <div class="store-card-head"><h2>Pratinjau Toko</h2><p>Tampilan ringkas identitas tokomu.</p></div>
                        <div class="store-profile">
                            <div class="store-logo"><?php if (!empty($store['logo'])): ?><img src="/GreenCycle/uploads/<?= e($store['logo']) ?>" alt="Logo <?= e($storeName) ?>"><?php else: ?><?= e($initials) ?><?php endif; ?></div>
                            <h3><?= e($store['store_name'] ?? 'Nama Toko Anda') ?></h3>
                            <p><?= e($store['description'] ?? 'Deskripsi toko belum diisi.') ?></p>
                            <?php if ($store): ?><span class="store-status <?= ($store['status'] ?? '') === 'active' ? '' : 'inactive' ?>"><span>●</span><?= e(ucfirst($store['status'])) ?></span><?php else: ?><span class="store-status inactive">Profil belum dibuat</span><?php endif; ?>
                            <div class="store-info-list">
                                <div class="store-info-row"><span>Alamat</span><span><?= e($store['address'] ?? '-') ?: '-' ?></span></div>
                                <div class="store-info-row"><span>Telepon</span><span><?= e($store['phone'] ?? '-') ?: '-' ?></span></div>
                                <div class="store-info-row"><span>Bergabung</span><span><?= !empty($store['created_at']) ? date('d M Y', strtotime($store['created_at'])) : '-' ?></span></div>
                            </div>
                        </div>
                    </section>
                    <div class="gc-alert" style="background:#effaf3;border:1px solid #ccebd7;color:#176b43"><strong>Tips toko</strong><br><span style="font-size:12px;line-height:1.6">Gunakan nama yang mudah diingat, deskripsi yang jelas, serta alamat dan nomor telepon yang aktif agar pelanggan lebih percaya.</span></div>
                </aside>
            </div>
        </div>
    </main>
</div>
<script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
