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

if (!$store) {
    header('Location: /GreenCycle/seller/dashboard.php');
    exit;
}

$storeId = (int)$store['id'];
$productId = (int)($_GET['id'] ?? 0);

// Ambil data produk
$pStmt = $conn->prepare("
    SELECT id, category_id, product_name, description, material_info, price, stock, product_photo, status
    FROM products
    WHERE id = ? AND store_id = ?
    LIMIT 1
");
$pStmt->bind_param("ii", $productId, $storeId);
$pStmt->execute();
$product = $pStmt->get_result()->fetch_assoc();
$pStmt->close();

if (!$product) {
    $_SESSION['flash'] = 'Produk tidak ditemukan atau bukan milik toko Anda.';
    header('Location: /GreenCycle/seller/products/index.php');
    exit;
}

$error = '';

// Ambil kategori
$categories = [];
$catQuery = $conn->query("SELECT id, category_name FROM categories ORDER BY category_name ASC");
if ($catQuery) {
    $categories = $catQuery->fetch_all(MYSQLI_ASSOC);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $product_name = trim($_POST['product_name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $material_info = trim($_POST['material_info'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

    if ($product_name === '') {
        $error = 'Nama produk wajib diisi.';
    } elseif ($category_id <= 0) {
        $error = 'Pilih salah satu kategori produk daur ulang.';
    } elseif ($price < 0) {
        $error = 'Harga produk tidak boleh negatif.';
    } elseif ($stock < 0) {
        $error = 'Jumlah stok produk minimal 0.';
    } else {
        $photoFileName = $product['product_photo'];

        // Cek jika ada unggahan foto baru
        if (isset($_FILES['product_photo']) && $_FILES['product_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['product_photo'];
            $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!isset($allowedMimes[$mime]) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $error = 'File harus berupa gambar JPG, PNG, atau WEBP yang valid.';
            } elseif ($file['size'] <= 0 || $file['size'] > 3 * 1024 * 1024) {
                $error = 'Ukuran file foto produk harus lebih dari 0 dan maksimal 3MB.';
            } else {
                $ext = $allowedMimes[$mime];
                $newFileName = 'prod_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $uploadDir = __DIR__ . '/../../uploads/';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) $error = 'Folder upload tidak dapat dibuat.';
                
                if (!$error && move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
                    $photoFileName = $newFileName;
                } else {
                    $error = 'Gagal menyimpan foto produk baru.';
                }
            }
        }

        if (!$error) {
            try {
                $update = $conn->prepare("
                    UPDATE products
                    SET category_id = ?, product_name = ?, description = ?, material_info = ?, price = ?, stock = ?, product_photo = ?, status = ?
                    WHERE id = ? AND store_id = ?
                ");
                $update->bind_param(
                    "isssdsssii",
                    $category_id,
                    $product_name,
                    $description,
                    $material_info,
                    $price,
                    $stock,
                    $photoFileName,
                    $status,
                    $productId,
                    $storeId
                );
                $update->execute();
                $update->close();

                $_SESSION['flash'] = 'Produk daur ulang berhasil diperbarui!';
                header('Location: /GreenCycle/seller/products/index.php');
                exit;

            } catch (mysqli_sql_exception $e) {
                error_log("Error update product: " . $e->getMessage());
                $error = 'Terjadi kesalahan basis data saat memperbarui produk.';
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
    <title>Edit Produk - <?= e($product['product_name']) ?> | GreenCycle</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
</head>
<body>

<div class="gc-app">
    <div class="gc-sidebar-backdrop" id="gcSidebarBackdrop"></div>

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
                <?= strtoupper(substr($store['store_name'], 0, 2)) ?>
            </div>
            <div class="gc-role-info">
                <div class="gc-role-name"><?= e($store['store_name']) ?></div>
                <div class="gc-role-tag" style="color: #34d399;">Mitra Seller Daur Ulang</div>
            </div>
        </div>

        <nav class="gc-sidebar-nav">
            <a href="/GreenCycle/seller/dashboard.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                Dashboard Toko
            </a>
            <a href="/GreenCycle/seller/products/index.php" class="gc-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11zM7 13h10v8H7z"/></svg>
                Produk Daur Ulang
            </a>
            <a href="/GreenCycle/seller/orders/index.php" class="gc-nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-2 10h-4v4h-2v-4H7v-2h4V7h2v4h4v2z"/></svg>
                Pesanan Masuk
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
                    <span>Edit Produk: <?= e($product['product_name']) ?></span>
                </div>
            </div>
            <div class="gc-topbar-right">
                <a href="/GreenCycle/seller/products/index.php" class="gc-btn gc-btn-secondary gc-btn-sm">
                    &larr; Kembali ke Daftar
                </a>
            </div>
        </header>

        <div class="gc-content">
            <?php if ($error): ?>
                <div class="gc-alert gc-alert-error">
                    <span><?= e($error) ?></span>
                    <button type="button" class="gc-alert-close">&times;</button>
                </div>
            <?php endif; ?>

            <div class="gc-panel" style="max-width: 800px; margin: 0 auto;">
                <div class="gc-panel-header">
                    <div class="gc-panel-title">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        Edit Rincian Produk
                    </div>
                </div>

                <div class="gc-panel-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="prodName">Nama Produk *</label>
                            <input type="text" id="prodName" name="product_name" class="gc-input" style="padding-left: 14px;" required value="<?= e($_POST['product_name'] ?? $product['product_name']) ?>">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="gc-form-group">
                                <label class="gc-form-label" for="prodCat">Kategori *</label>
                                <select id="prodCat" name="category_id" class="gc-input" style="padding-left: 14px; cursor: pointer;" required>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= (int)($_POST['category_id'] ?? $product['category_id']) === (int)$c['id'] ? 'selected' : '' ?>>
                                            <?= e($c['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="gc-form-group">
                                <label class="gc-form-label" for="prodStatus">Status Produk</label>
                                <select id="prodStatus" name="status" class="gc-input" style="padding-left: 14px; cursor: pointer;">
                                    <option value="active" <?= ($_POST['status'] ?? $product['status']) === 'active' ? 'selected' : '' ?>>Aktif (Tampil di Marketplace)</option>
                                    <option value="inactive" <?= ($_POST['status'] ?? $product['status']) === 'inactive' ? 'selected' : '' ?>>Nonaktif (Draft Toko)</option>
                                </select>
                            </div>
                        </div>

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="prodMaterial">Informasi Material Daur Ulang (Eco-Impact)</label>
                            <input type="text" id="prodMaterial" name="material_info" class="gc-input" style="padding-left: 14px;" value="<?= e($_POST['material_info'] ?? $product['material_info']) ?>">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="gc-form-group">
                                <label class="gc-form-label" for="prodPrice">Harga Satuan (Rp) *</label>
                                <input type="number" id="prodPrice" name="price" class="gc-input" style="padding-left: 14px;" min="0" step="1000" required value="<?= e($_POST['price'] ?? $product['price']) ?>">
                            </div>

                            <div class="gc-form-group">
                                <label class="gc-form-label" for="prodStock">Jumlah Stok Unit *</label>
                                <input type="number" id="prodStock" name="stock" class="gc-input" style="padding-left: 14px;" min="0" required value="<?= e($_POST['stock'] ?? $product['stock']) ?>">
                            </div>
                        </div>

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="prodDesc">Deskripsi Lengkap Produk</label>
                            <textarea id="prodDesc" name="description" class="gc-input" style="padding: 10px 14px; height: 100px; resize: vertical;"><?= e($_POST['description'] ?? $product['description']) ?></textarea>
                        </div>

                        <div class="gc-form-group">
                            <label class="gc-form-label" for="prodPhoto">Ganti Foto Produk (Kosongkan jika tidak ingin mengubah)</label>
                            <?php if (!empty($product['product_photo'])): ?>
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                                    <img src="/GreenCycle/uploads/<?= e($product['product_photo']) ?>" alt="Foto Sekarang" class="gc-product-thumb" style="width: 60px; height: 60px;">
                                    <span style="font-size: 12px; color: var(--gc-text-muted);">Foto saat ini: <?= e($product['product_photo']) ?></span>
                                </div>
                            <?php endif; ?>
                            <input type="file" id="prodPhoto" name="product_photo" accept="image/jpeg,image/png,image/webp" class="gc-input" style="padding: 9px 14px;">
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; border-top: 1px solid var(--gc-border-light); padding-top: 18px;">
                            <a href="/GreenCycle/seller/products/index.php" class="gc-btn gc-btn-secondary">
                                Batal
                            </a>
                            <button type="submit" class="gc-btn gc-btn-primary">
                                Simpan Perubahan
                            </button>
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
