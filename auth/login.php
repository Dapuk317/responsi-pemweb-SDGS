<?php
require_once __DIR__ . '/../includes/init.php';

if (!empty($_SESSION['user_id'])) {
    redirect_by_role();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $error = 'Email atau password salah.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Akun kamu tidak aktif. Hubungi administrator.';
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Perbarui hash jika algoritma password berubah.
                if (password_needs_rehash(
                    $user['password'],
                    PASSWORD_DEFAULT
                )) {
                    $new_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $update = $conn->prepare(
                        "UPDATE users SET password = ? WHERE id = ?"
                    );
                    $update->bind_param(
                        "si",
                        $new_hash,
                        $user['id']
                    );
                    $update->execute();
                }

                redirect_by_role();
            }
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
            $error = 'Terjadi kesalahan. Silakan coba kembali.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GreenCycle</title>
</head>
<body>

<h2>Login GreenCycle</h2>

<?php if (isset($_GET['registered'])): ?>
    <p style="color:green;">
        Registrasi berhasil. Silakan login.
    </p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color:red;"><?= e($error) ?></p>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token"
           value="<?= e(csrf_token()) ?>">

    <label>Email</label><br>
    <input type="email" name="email" required
           value="<?= e($_POST['email'] ?? '') ?>"><br><br>

    <label>Password</label><br>
    <input type="password" name="password" required><br><br>

    <button type="submit">Login</button>
</form>

<p>Belum punya akun?
    <a href="register.php">Daftar sekarang</a>
</p>

</body>
</html>
