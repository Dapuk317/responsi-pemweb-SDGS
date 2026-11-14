<?php
require_once __DIR__ . '/../includes/init.php';

if (!empty($_SESSION['user_id'])) {
    redirect_by_role();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    <title>Daftar Akun - GreenCycle</title>
</head>
<body>

<h2>Daftar GreenCycle</h2>

<?php if ($error): ?>
    <p style="color:red;"><?= e($error) ?></p>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token"
           value="<?= e(csrf_token()) ?>">

    <label>Nama lengkap</label><br>
    <input type="text" name="name" required
           value="<?= e($_POST['name'] ?? '') ?>"><br><br>

    <label>Email</label><br>
    <input type="email" name="email" required
           value="<?= e($_POST['email'] ?? '') ?>"><br><br>

    <label>Nomor telepon</label><br>
    <input type="text" name="phone"
           value="<?= e($_POST['phone'] ?? '') ?>"><br><br>

    <label>Alamat</label><br>
    <textarea name="address"><?= e($_POST['address'] ?? '') ?></textarea><br><br>

    <label>Jenis akun</label><br>
    <select name="role" id="role" onchange="toggleStore()" required>
        <option value="customer"
            <?= ($_POST['role'] ?? '') === 'customer' ? 'selected' : '' ?>>
            Customer (Pembeli)
        </option>
        <option value="seller"
            <?= ($_POST['role'] ?? '') === 'seller' ? 'selected' : '' ?>>
            Seller (Penjual)
        </option>
    </select><br><br>

    <div id="storeField" style="display:none;">
        <label>Nama toko</label><br>
        <input type="text" name="store_name"
               value="<?= e($_POST['store_name'] ?? '') ?>"><br><br>
    </div>

    <label>Password (minimal 8 karakter)</label><br>
    <input type="password" name="password" required minlength="8"><br><br>

    <label>Konfirmasi password</label><br>
    <input type="password" name="confirm_password"
           required minlength="8"><br><br>

    <button type="submit">Daftar</button>
</form>

<p>Sudah punya akun?
    <a href="login.php">Login di sini</a>
</p>

<script>
function toggleStore() {
    const role = document.getElementById('role').value;
    const field = document.getElementById('storeField');
    const input = field.querySelector('input');

    field.style.display = role === 'seller' ? 'block' : 'none';
    input.required = role === 'seller';
}

toggleStore();
</script>

</body>
</html>
