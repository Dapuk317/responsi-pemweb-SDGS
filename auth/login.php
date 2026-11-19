<?php
require_once __DIR__ . '/../includes/init.php';

if (!empty($_SESSION['user_id'])) {
    redirect_by_role();
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT id, name, email, password, role, status
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (
                !$user ||
                !password_verify($password, $user['password'])
            ) {
                $error = 'Email atau password yang Anda masukkan salah.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Akun Anda sedang nonaktif. Silakan hubungi tim administrator.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Perbarui hash jika diperlukan
                if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->bind_param("si", $new_hash, $user['id']);
                    $update->execute();
                }

                redirect_by_role();
            }
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GreenCycle Marketplace Daur Ulang</title>
    <meta name="description" content="Masuk ke GreenCycle untuk mendukung ekonomi sirkular dan transaksi produk daur ulang berkualitas.">
    <link rel="stylesheet" href="/GreenCycle/assets/css/dashboard.css">
    <style>
        .gc-auth-split {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            max-width: 1000px;
            width: 100%;
            background: #ffffff;
            border-radius: var(--gc-radius-xl);
            box-shadow: 0 20px 50px rgba(13, 56, 34, 0.12);
            border: 1px solid var(--gc-border);
            overflow: hidden;
        }

        .gc-auth-banner-side {
            background: linear-gradient(145deg, var(--gc-forest) 0%, var(--gc-primary) 100%);
            color: #ffffff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .gc-auth-banner-side::after {
            content: "";
            position: absolute;
            right: -60px;
            bottom: -60px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(120, 184, 46, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        .gc-auth-deco-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--gc-radius-full);
            font-size: 12px;
            font-weight: 600;
            color: var(--gc-accent-soft);
            width: fit-content;
        }

        .gc-auth-banner-main h2 {
            font-size: 30px;
            font-weight: 800;
            line-height: 1.25;
            margin: 20px 0 14px;
            letter-spacing: -0.5px;
        }

        .gc-auth-banner-main h2 span {
            color: var(--gc-accent);
        }

        .gc-auth-banner-main p {
            font-size: 14.5px;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
        }

        .gc-auth-sdg-pills {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .gc-sdg-tag {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: var(--gc-radius-sm);
            font-size: 11.5px;
            font-weight: 700;
        }

        .gc-auth-banner-footer {
            font-size: 12.5px;
            color: rgba(255, 255, 255, 0.7);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding-top: 20px;
        }

        .gc-auth-form-side {
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        @media (max-width: 860px) {
            .gc-auth-split {
                grid-template-columns: 1fr;
                max-width: 480px;
            }
            .gc-auth-banner-side {
                display: none;
            }
            .gc-auth-form-side {
                padding: 36px 28px;
            }
        }
    </style>
</head>
<body class="gc-auth-page">

    <div class="gc-auth-split">
        <!-- Sisi Banner Edukatif Eco-Friendly -->
        <div class="gc-auth-banner-side">
            <div>
                <span class="gc-auth-deco-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L9.5 8H14.5L12 2Z"></path>
                        <path d="M4 14L8 14L6 9L4 14Z"></path>
                        <path d="M16 14L20 14L18 9L16 14Z"></path>
                        <circle cx="12" cy="16" r="5"></circle>
                    </svg>
                    SDG 8 & SDG 12 Aligned
                </span>

                <div class="gc-auth-banner-main">
                    <h2>Memberi Nilai Baru Pada <span>Material Bekas.</span></h2>
                    <p>Selamat datang di platform marketplace produk daur ulang terpercaya. Masuk untuk mengelola tokomu, memantau pesanan, atau menjelajahi ribuan produk kreatif ramah lingkungan.</p>

                    <div class="gc-auth-sdg-pills">
                        <div class="gc-sdg-tag">🌱 SDG 8: Pekerjaan Layak</div>
                        <div class="gc-sdg-tag">♻ SDG 12: Konsumsi Bertanggung Jawab</div>
                    </div>
                </div>
            </div>

            <div class="gc-auth-banner-footer">
                &copy; <?= date('Y') ?> GreenCycle Indonesia. Mendukung Gaya Hidup Sirkular & Lestari.
            </div>
        </div>

        <!-- Sisi Form Login Interaktif -->
        <div class="gc-auth-form-side">
            <div class="gc-auth-header">
                <!-- LOGO MARKETPLACE - TIDAK DIUBAH -->
                <a href="/GreenCycle/pages/home.php" class="gc-auth-logo" title="Kembali ke Beranda GreenCycle">
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

                <h1 class="gc-auth-title">Masuk ke Akun</h1>
                <p class="gc-auth-subtitle">Silakan isi kredensial untuk mengakses dashboard Anda</p>
            </div>

            <!-- Pesan Flash Sukses Registrasi -->
            <?php if (isset($_GET['registered'])): ?>
                <div class="gc-alert gc-alert-success">
                    <span>
                        <strong>Registrasi Berhasil!</strong> Akun Anda sudah siap. Silakan login sekarang.
                    </span>
                    <button type="button" class="gc-alert-close" aria-label="Tutup">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Pesan Flash Error Login -->
            <?php if ($error): ?>
                <div class="gc-alert gc-alert-error">
                    <span><?= e($error) ?></span>
                    <button type="button" class="gc-alert-close" aria-label="Tutup">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Role Guidance & Helper Tabs -->
            <div class="gc-role-hints">
                <div class="gc-role-hints-title">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                    </svg>
                    Akses Dashboard:
                </div>
                <div class="gc-role-hint-pills">
                    <span class="gc-role-pill active" data-role="customer">🛍 Customer</span>
                    <span class="gc-role-pill" data-role="seller">🏪 Seller Toko</span>
                    <span class="gc-role-pill" data-role="admin">⚙ Admin</span>
                </div>
                <div id="roleHelperDesc" style="margin-top: 8px; font-size: 11.5px; color: var(--gc-text-muted);">
                    Login satu pintu untuk semua jenis akun (Customer, Seller, Admin) otomatis diarahkan ke dashboard masing-masing.
                </div>
            </div>

            <form method="POST" action="" id="loginForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <!-- Field Email -->
                <div class="gc-form-group">
                    <label class="gc-form-label" for="loginEmail">Alamat Email</label>
                    <div class="gc-input-wrapper">
                        <svg class="gc-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <input
                            type="email"
                            id="loginEmail"
                            name="email"
                            class="gc-input"
                            placeholder="nama@email.com"
                            required
                            autofocus
                            value="<?= e($_POST['email'] ?? '') ?>"
                        >
                    </div>
                </div>

                <!-- Field Password -->
                <div class="gc-form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 7px;">
                        <label class="gc-form-label" for="loginPassword" style="margin-bottom: 0;">Password</label>
                    </div>
                    <div class="gc-input-wrapper">
                        <svg class="gc-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            class="gc-input"
                            placeholder="Masukkan password Anda"
                            required
                        >
                        <button
                            type="button"
                            class="gc-input-toggle-pw"
                            data-target="loginPassword"
                            title="Tampilkan password"
                            aria-label="Tampilkan atau sembunyikan password"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" class="gc-btn gc-btn-primary" style="width: 100%; padding: 13px; font-size: 14.5px; margin-top: 6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    Masuk ke Dashboard
                </button>
            </form>

            <div class="gc-auth-footer">
                <p>Belum memiliki akun GreenCycle?
                    <a href="register.php" style="font-weight: 700; color: var(--gc-primary);">Daftar Sekarang</a>
                </p>
                <p style="margin-top: 10px;">
                    <a href="/GreenCycle/pages/home.php" style="font-size: 12.5px; color: var(--gc-text-muted);">
                        &larr; Kembali ke Katalog Marketplace
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="/GreenCycle/assets/js/dashboard.js"></script>
</body>
</html>
