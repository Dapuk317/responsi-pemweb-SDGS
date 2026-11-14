<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['seller']);

$user_id = (int) $_SESSION['user_id'];
$error = '';
$success = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Ambil data toko milik seller yang sedang login.
$stmt = $conn->prepare(
    "SELECT id, store_name, description, address, phone, status
     FROM stores
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$store = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $store_name = trim($_POST['store_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($store_name === '') {
        $error = 'Nama toko wajib diisi.';
    } elseif (mb_strlen($store_name) > 150) {
        $error = 'Nama toko maksimal 150 karakter.';
    } else {
        try {
            if ($store) {
                // Seller hanya memperbarui toko miliknya sendiri.
                $update = $conn->prepare(
                    "UPDATE stores
                     SET store_name = ?, description = ?,
                         address = ?, phone = ?
                     WHERE id = ? AND user_id = ?"
                );

                $update->bind_param(
                    "ssssii",
                    $store_name,
                    $description,
                    $address,
                    $phone,
                    $store['id'],
                    $user_id
                );

                $update->execute();

                $_SESSION['flash'] = 'Informasi toko berhasil diperbarui.';
            } else {
                // Membuat profil toko jika belum ada.
                $insert = $conn->prepare(
                    "INSERT INTO stores
                     (user_id, store_name, description, address, phone)
                     VALUES (?, ?, ?, ?, ?)"
                );

                $insert->bind_param(
                    "issss",
                    $user_id,
                    $store_name,
                    $description,
                    $address,
                    $phone
                );

                $insert->execute();

                $_SESSION['flash'] = 'Profil toko berhasil dibuat.';
            }

            header('Location: index.php');
            exit;

        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = 'Gagal menyimpan informasi toko. Silakan coba lagi.';
        }
    }

    // Pertahankan input jika validasi atau penyimpanan gagal.
    $store = array_merge($store ?? [], [
        'store_name' => $store_name,
        'description' => $description,
        'address' => $address,
        'phone' => $phone
    ]);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Toko - GreenCycle</title>
</head>
<body>

<h1>Kelola Toko Saya</h1>

<p>Halo, <?= e($_SESSION['name']) ?>!</p>

<p>
    <a href="/GreenCycle/seller/dashboard.php">
        Kembali ke Dashboard Seller
    </a>
</p>

<?php if ($success): ?>
    <p style="color:green;"><?= e($success) ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color:red;"><?= e($error) ?></p>
<?php endif; ?>

<?php if ($store): ?>

    <h3>Informasi Toko</h3>

    <p>
        Status toko:
        <strong><?= e(ucfirst($store['status'])) ?></strong>
    </p>

<?php else: ?>

    <h3>Buat Profil Toko</h3>
    <p>Kamu belum memiliki profil toko. Isi formulir berikut.</p>

<?php endif; ?>

<form method="POST">

    <input type="hidden" name="csrf_token"
           value="<?= e(csrf_token()) ?>">

    <label>Nama Toko</label><br>
    <input type="text" name="store_name"
           maxlength="150" required
           value="<?= e($store['store_name'] ?? '') ?>">
    <br><br>

    <label>Deskripsi Toko</label><br>
    <textarea name="description" rows="5"><?= e($store['description'] ?? '') ?></textarea>
    <br><br>

    <label>Alamat Toko</label><br>
    <textarea name="address" rows="4"><?= e($store['address'] ?? '') ?></textarea>
    <br><br>

    <label>Nomor Telepon Toko</label><br>
    <input type="text" name="phone"
           value="<?= e($store['phone'] ?? '') ?>">
    <br><br>

    <button type="submit">
        <?= $store ? 'Simpan Perubahan' : 'Buat Toko' ?>
    </button>

</form>

</body>
</html>