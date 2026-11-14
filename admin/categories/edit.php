<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('ID kategori tidak valid.');
}

$stmt = $conn->prepare(
    "SELECT id, category_name, description
     FROM categories WHERE id = ? LIMIT 1"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$category = $stmt->get_result()->fetch_assoc();

if (!$category) {
    http_response_code(404);
    exit('Kategori tidak ditemukan.');
}

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
            $update = $conn->prepare(
                "UPDATE categories
                 SET category_name = ?, description = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "ssi",
                $category_name,
                $description,
                $id
            );

            $update->execute();

            $_SESSION['flash'] = 'Kategori berhasil diperbarui.';
            header('Location: index.php');
            exit;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $error = 'Nama kategori sudah digunakan.';
            } else {
                error_log($e->getMessage());
                $error = 'Gagal memperbarui kategori.';
            }
        }
    }

    $category['category_name'] = $category_name;
    $category['description'] = $description;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Kategori - GreenCycle</title>
</head>
<body>

<h1>Edit Kategori</h1>

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
           value="<?= e($category['category_name']) ?>">
    <br><br>

    <label>Deskripsi</label><br>
    <textarea name="description" rows="5"><?= e($category['description']) ?></textarea>
    <br><br>

    <button type="submit">Simpan Perubahan</button>
</form>

</body>
</html>
