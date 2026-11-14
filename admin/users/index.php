<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['admin']);

$keyword = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$limit = 10;
$offset = ($page - 1) * $limit;

$search = '%' . $keyword . '%';

// Menghitung jumlah pengguna.
$count = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE name LIKE ? OR email LIKE ?"
);

$count->bind_param("ss", $search, $search);
$count->execute();

$total_users = (int)$count->get_result()->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_users / $limit));

// Mengambil data pengguna.
$stmt = $conn->prepare(
    "SELECT id, name, email, phone, role, status, created_at
     FROM users
     WHERE name LIKE ? OR email LIKE ?
     ORDER BY id DESC
     LIMIT ? OFFSET ?"
);

$stmt->bind_param("ssii", $search, $search, $limit, $offset);
$stmt->execute();

$users = $stmt->get_result();

// Pesan hasil tindakan.
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - GreenCycle</title>
</head>
<body>

<h1>Kelola Pengguna GreenCycle</h1>

<p>
    Selamat datang, <?= e($_SESSION['name']) ?>.
</p>

<?php if ($flash): ?>
    <p style="color:green;">
        <?= e($flash) ?>
    </p>
<?php endif; ?>

<p>
    <a href="/GreenCycle/admin/dashboard.php">
        Kembali ke Dashboard
    </a>
</p>

<hr>

<h3>Daftar Pengguna</h3>

<form method="GET">
    <input
        type="text"
        name="q"
        placeholder="Cari nama atau email..."
        value="<?= e($keyword) ?>"
    >

    <button type="submit">Cari</button>

    <a href="index.php">Reset</a>
</form>

<p>
    Total pengguna ditemukan: <?= $total_users ?>
</p>

<table border="1" cellpadding="10" cellspacing="0">
    <thead>
        <tr>
            <th>No.</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Telepon</th>
            <th>Role</th>
            <th>Status</th>
            <th>Tanggal Daftar</th>
            <th>Aksi</th>
        </tr>
    </thead>

    <tbody>

    <?php if ($users->num_rows > 0): ?>

        <?php $no = $offset + 1; ?>

        <?php while ($user = $users->fetch_assoc()): ?>

            <tr>
                <td><?= $no++ ?></td>

                <td><?= e($user['name']) ?></td>

                <td><?= e($user['email']) ?></td>

                <td><?= e($user['phone'] ?? '-') ?></td>

                <td><?= e(ucfirst($user['role'])) ?></td>

                <td>
                    <?= e(ucfirst($user['status'])) ?>
                </td>

                <td>
                    <?= e($user['created_at']) ?>
                </td>

                <td>
                    <?php if ((int)$user['id'] === (int)$_SESSION['user_id']): ?>

                        <strong>Akun sendiri</strong>

                    <?php else: ?>

                        <form
                            action="update_status.php"
                            method="POST"
                            onsubmit="return confirm('Yakin ingin mengubah status akun ini?')"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e(csrf_token()) ?>"
                            >

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= (int)$user['id'] ?>"
                            >

                            <?php if ($user['status'] === 'active'): ?>

                                <input
                                    type="hidden"
                                    name="status"
                                    value="inactive"
                                >

                                <button type="submit">
                                    Nonaktifkan
                                </button>

                            <?php else: ?>

                                <input
                                    type="hidden"
                                    name="status"
                                    value="active"
                                >

                                <button type="submit">
                                    Aktifkan
                                </button>

                            <?php endif; ?>
                        </form>

                    <?php endif; ?>
                </td>
            </tr>

        <?php endwhile; ?>

    <?php else: ?>

        <tr>
            <td colspan="8">Data pengguna tidak ditemukan.</td>
        </tr>

    <?php endif; ?>

    </tbody>
</table>

<br>

<!-- Navigasi halaman -->
<?php if ($page > 1): ?>
    <a href="?<?= e(http_build_query([
        'q' => $keyword,
        'page' => $page - 1
    ])) ?>">Sebelumnya</a>
<?php endif; ?>

<span>
    Halaman <?= $page ?> dari <?= $total_pages ?>
</span>

<?php if ($page < $total_pages): ?>
    <a href="?<?= e(http_build_query([
        'q' => $keyword,
        'page' => $page + 1
    ])) ?>">Selanjutnya</a>
<?php endif; ?>

<hr>

<form action="/GreenCycle/auth/logout.php" method="POST">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= e(csrf_token()) ?>"
    >

    <button type="submit">Logout</button>
</form>

</body>
</html>
