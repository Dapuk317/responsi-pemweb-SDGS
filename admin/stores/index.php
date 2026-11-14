<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$keyword = trim($_GET['q'] ?? '');
$search = '%' . $keyword . '%';

$stmt = $conn->prepare(
    "SELECT
        s.id,
        s.store_name,
        s.description,
        s.address,
        s.phone,
        s.status,
        s.created_at,
        u.name AS owner_name,
        u.email AS owner_email
     FROM stores s
     INNER JOIN users u ON s.user_id = u.id
     WHERE s.store_name LIKE ?
        OR u.name LIKE ?
        OR u.email LIKE ?
     ORDER BY s.id DESC"
);

$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();

$stores = $stmt->get_result();

$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Toko - GreenCycle</title>
</head>
<body>

<h1>Kelola Seluruh Toko</h1>

<p>
    <a href="/GreenCycle/admin/dashboard.php">
        Kembali ke Dashboard Admin
    </a>
</p>

<?php if ($flash): ?>
    <p><?= e($flash) ?></p>
<?php endif; ?>

<h3>Daftar Toko GreenCycle</h3>

<form method="GET">
    <input type="text" name="q"
           placeholder="Cari nama toko atau pemilik..."
           value="<?= e($keyword) ?>">

    <button type="submit">Cari</button>
    <a href="index.php">Reset</a>
</form>

<br>

<table border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>No.</th>
            <th>Nama Toko</th>
            <th>Pemilik</th>
            <th>Email Pemilik</th>
            <th>Telepon</th>
            <th>Alamat</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
    </thead>

    <tbody>

    <?php if ($stores->num_rows > 0): ?>

        <?php $no = 1; ?>

        <?php while ($store = $stores->fetch_assoc()): ?>

            <tr>
                <td><?= $no++ ?></td>

                <td><?= e($store['store_name']) ?></td>

                <td><?= e($store['owner_name']) ?></td>

                <td><?= e($store['owner_email']) ?></td>

                <td><?= e($store['phone'] ?: '-') ?></td>

                <td><?= e($store['address'] ?: '-') ?></td>

                <td><?= e(ucfirst($store['status'])) ?></td>

                <td>
                    <form action="update_status.php"
                          method="POST"
                          onsubmit="return confirm('Yakin ingin mengubah status toko ini?')">

                        <input type="hidden" name="csrf_token"
                               value="<?= e(csrf_token()) ?>">

                        <input type="hidden" name="store_id"
                               value="<?= (int)$store['id'] ?>">

                        <?php if ($store['status'] === 'active'): ?>

                            <input type="hidden" name="status"
                                   value="inactive">

                            <button type="submit">
                                Nonaktifkan
                            </button>

                        <?php else: ?>

                            <input type="hidden" name="status"
                                   value="active">

                            <button type="submit">
                                Aktifkan
                            </button>

                        <?php endif; ?>

                    </form>
                </td>
            </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="8">Belum ada toko yang ditemukan.</td>
        </tr>

    <?php endif; ?>

    </tbody>
</table>

</body>
</html>