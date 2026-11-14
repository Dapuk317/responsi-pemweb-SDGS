<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$result = $conn->query(
    "SELECT c.id, c.category_name, c.description, c.created_at,
            COUNT(p.id) AS total_products
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.category_name, c.description, c.created_at
     ORDER BY c.id DESC"
);

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - GreenCycle</title>
</head>
<body>

<h1>Kelola Kategori Produk</h1>

<p>Selamat datang, <?= e($_SESSION['name']) ?>.</p>

<p>
    <a href="/GreenCycle/admin/dashboard.php">Dashboard Admin</a>
</p>

<?php if ($flash): ?>
    <p><?= e($flash) ?></p>
<?php endif; ?>

<p>
    <a href="create.php">+ Tambah Kategori</a>
</p>

<table border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>No.</th>
            <th>Nama Kategori</th>
            <th>Deskripsi</th>
            <th>Jumlah Produk</th>
            <th>Aksi</th>
        </tr>
    </thead>

    <tbody>
    <?php if ($result->num_rows > 0): ?>
        <?php $no = 1; ?>

        <?php while ($category = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= e($category['category_name']) ?></td>
                <td><?= e($category['description'] ?: '-') ?></td>
                <td><?= (int)$category['total_products'] ?></td>
                <td>
                    <a href="edit.php?id=<?= (int)$category['id'] ?>">
                        Edit
                    </a>

                    |

                    <form action="delete.php" method="POST"
                          style="display:inline"
                          onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">

                        <input type="hidden" name="csrf_token"
                               value="<?= e(csrf_token()) ?>">

                        <input type="hidden" name="id"
                               value="<?= (int)$category['id'] ?>">

                        <button type="submit">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>

    <?php else: ?>
        <tr>
            <td colspan="5">Belum ada kategori.</td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

</body>
</html>
