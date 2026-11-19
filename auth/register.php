<?php
require_once __DIR__ . '/../includes/init.php';

if (!empty($_SESSION['user_id'])) {
    redirect_by_role();
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = $_POST['role'] ?? 'customer';
    $store_name = trim($_POST['store_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Nama, email, dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!in_array($role, ['customer', 'seller'], true)) {
        $error = 'Pilihan jenis akun tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif ($role === 'seller' && $store_name === '') {
        $error = 'Nama toko wajib diisi untuk akun seller.';
    } else {
        try {
            $check = $conn->prepare(
                "SELECT id FROM users WHERE email = ? LIMIT 1"
            );
            $check->bind_param("s", $email);
            $check->execute();
            $existing = $check->get_result();

            if ($existing->num_rows > 0) {
                $error = 'Email sudah terdaftar. Gunakan email lain.';
            } else {
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO users 
                        (name, email, password, phone, address, role)
                        VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "ssssss",
                        $name,
                        $email,
                        $hashed_password,
                        $phone,
                        $address,
                        $role
                    );

                    $stmt->execute();
                    $user_id = $conn->insert_id;

                    if ($role === 'seller') {
                        $store = $conn->prepare(
                            "INSERT INTO stores 
                            (user_id, store_name, description, address, phone)
                            VALUES (?, ?, '', ?, ?)"
                        );

                        $store->bind_param(
                            "isss",
                            $user_id,
                            $store_name,
                            $address,
                            $phone
                        );

                        $store->execute();
                    }

                    $conn->commit();

                    header(
                        'Location: /GreenCycle/auth/login.php?registered=1'
                    );
                    exit;

                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = 'Registrasi gagal. Silakan coba kembali.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - GreenCycle Marketplace</title>
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
    <style>
        .gc-reg-card {
            width: 100%;
            max-width: 580px;
            background: #ffffff;
            border-radius: var(--gc-radius-xl);
            box-shadow: 0 20px 50px rgba(13, 56, 34, 0.12);
            border: 1px solid var(--gc-border);
            padding: 40px 36px;
            margin: 30px auto;
        }
        .gc-grid-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) {
            .gc-grid-fields {
                grid-template-columns: 1fr;
            }
            .gc-reg-card {
                padding: 28px 20px;
            }
        }
    </style>
</head>
<body class="gc-auth-page">

    <div class="gc-reg-card">
        <div class="gc-auth-header">
            <!-- LOGO MARKETPLACE - TIDAK DIUBAH -->
            <a href="/GreenCycle/pages/home.php" class="gc-auth-logo">
                <img
                    src="/GreenCycle/assets/images/greencycle-logo.png"
                    alt="GreenCycle Logo"
                    class="brand-logo"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';"
                >
                <span class="brand-fallback" style="display:none; font-size:26px; font-weight:800; color:#123f2b; letter-spacing:-1px;">
                    Green<span style="color:#78b82e;">Cycle</span>
                </span>
            </a>
            <h1 class="gc-auth-title">Daftar Akun Baru</h1>
            <p class="gc-auth-subtitle">Bergabunglah bersama komunitas gaya hidup daur ulang dan ekonomi sirkular</p>
        </div>

        <?php if ($error): ?>
            <div class="gc-alert gc-alert-error">
                <span><?= e($error) ?></span>
                <button type="button" class="gc-alert-close" aria-label="Tutup">&times;</button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="gc-grid-fields">
                <div class="gc-form-group">
                    <label class="gc-form-label" for="regName">Nama Lengkap *</label>
                    <input type="text" id="regName" name="name" class="gc-input" style="padding-left: 14px;" required placeholder="Nama Anda" value="<?= e($_POST['name'] ?? '') ?>">
                </div>

                <div class="gc-form-group">
                    <label class="gc-form-label" for="regEmail">Alamat Email *</label>
                    <input type="email" id="regEmail" name="email" class="gc-input" style="padding-left: 14px;" required placeholder="nama@email.com" value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="gc-grid-fields">
                <div class="gc-form-group">
                    <label class="gc-form-label" for="regPhone">Nomor Telepon / WA</label>
                    <input type="text" id="regPhone" name="phone" class="gc-input" style="padding-left: 14px;" placeholder="08xxxxxxxxxx" value="<?= e($_POST['phone'] ?? '') ?>">
                </div>

                <div class="gc-form-group">
                    <label class="gc-form-label" for="regRole">Mendaftar Sebagai *</label>
                    <select name="role" id="regRole" class="gc-input" style="padding-left: 14px; cursor: pointer;" onchange="toggleStoreField()" required>
                        <option value="customer" <?= ($_POST['role'] ?? '') === 'customer' ? 'selected' : '' ?>>
                            🛍 Pembeli (Customer)
                        </option>
                        <option value="seller" <?= ($_POST['role'] ?? '') === 'seller' ? 'selected' : '' ?>>
                            🏪 Penjual (Seller Toko)
                        </option>
                    </select>
                </div>
            </div>

            <div class="gc-form-group" id="storeField" style="display: none;">
                <label class="gc-form-label" for="regStoreName">Nama Toko Daur Ulang *</label>
                <input type="text" id="regStoreName" name="store_name" class="gc-input" style="padding-left: 14px;" placeholder="Contoh: EcoCraft Studio" value="<?= e($_POST['store_name'] ?? '') ?>">
            </div>

            <div class="gc-form-group">
                <label class="gc-form-label" for="regAddress">Alamat Lengkap</label>
                <textarea id="regAddress" name="address" class="gc-input" style="padding: 10px 14px; height: 75px; resize: vertical;" placeholder="Alamat pengiriman barang..."><?= e($_POST['address'] ?? '') ?></textarea>
            </div>

            <div class="gc-grid-fields">
                <div class="gc-form-group">
                    <label class="gc-form-label" for="regPw">Password (Min. 8 karakter) *</label>
                    <div class="gc-input-wrapper">
                        <input type="password" id="regPw" name="password" class="gc-input" style="padding-left: 14px;" required minlength="8" placeholder="••••••••">
                        <button type="button" class="gc-input-toggle-pw" data-target="regPw" title="Tampilkan password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>

                <div class="gc-form-group">
                    <label class="gc-form-label" for="regConfirmPw">Konfirmasi Password *</label>
                    <div class="gc-input-wrapper">
                        <input type="password" id="regConfirmPw" name="confirm_password" class="gc-input" style="padding-left: 14px;" required minlength="8" placeholder="••••••••">
                        <button type="button" class="gc-input-toggle-pw" data-target="regConfirmPw" title="Tampilkan password">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" class="gc-btn gc-btn-primary" style="width: 100%; padding: 13px; font-size: 15px; margin-top: 8px;">
                Daftar Akun Sekarang
            </button>
        </form>

        <div class="gc-auth-footer">
            <p>Sudah memiliki akun?
                <a href="login.php" style="font-weight: 700; color: var(--gc-primary);">Masuk di Sini</a>
            </p>
        </div>
    </div>

    <script src="/GreenCycle/assets/js/dashboard.js"></script>
    <script>
        function toggleStoreField() {
            const roleSelect = document.getElementById('regRole');
            const storeField = document.getElementById('storeField');
            const storeInput = document.getElementById('regStoreName');
            if (roleSelect && storeField && storeInput) {
                const isSeller = roleSelect.value === 'seller';
                storeField.style.display = isSeller ? 'block' : 'none';
                storeInput.required = isSeller;
            }
        }
        toggleStoreField();
    </script>
</body>
</html>
