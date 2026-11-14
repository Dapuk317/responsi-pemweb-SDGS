<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $category_name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($category_name === '') {
        $error = 'Nama kategori wajib diisi.';
    } elseif (mb_strlen($category_name) > 100) {
        $error = 'Nama kategori maksimal 100 karakter.';
    } else {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO categories (category_name, description)
                 VALUES (?, ?)"
            );

            $stmt->bind_param(
                "ss",
                $category_name,
                $description
            );

            $stmt->execute();

            $_SESSION['flash'] = 'Kategori berhasil ditambahkan.';
            header('Location: index.php');
            exit;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $error = 'Nama kategori tersebut sudah tersedia.';
            } else {
                error_log($e->getMessage());
                $error = 'Gagal menambahkan kategori.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Kategori - GreenCycle</title>
</head>
<body>

<h1>Tambah Kategori</h1>

<p><a href="index.php">Kembali ke Daftar Kategori</a></p>

<?php if ($error): ?>
    <p style="color:red;"><?= e($error) ?></p>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token"
           value="<?= e(csrf_token()) ?>">

    <label>Nama Kategori</label><br>
    <input type="text" name="category_name"
           maxlength="100" required
           value="<?= e($_POST['category_name'] ?? '') ?>">
    <br><br>

    <label>Deskripsi</label><br>
    <textarea name="description" rows="5"><?= e($_POST['description'] ?? '') ?></textarea>
    <br><br>

    <button type="submit">Simpan Kategori</button>
</form>

</body>
</html>
