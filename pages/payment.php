<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$user_id = (int) $_SESSION['user_id'];

// Mengambil kode pesanan dari URL.
$order_code = trim($_GET['code'] ?? '');

if ($order_code === '') {
    header('Location: /GreenCycle/pages/order-history.php');
    exit;
}

// Mengambil pesanan milik customer yang sedang login.
$stmt = $conn->prepare("
    SELECT
        o.id, o.order_code, o.subtotal, o.shipping_cost, o.total_amount,
        t.payment_method, t.payment_status, t.payment_proof, o.status, o.created_at
    FROM orders o
    INNER JOIN transactions t ON t.order_id = o.id
    WHERE o.order_code = ? AND o.user_id = ?
    LIMIT 1
");

$stmt->bind_param("si", $order_code, $user_id);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

// Data rekening percobaan.
$bank_name = 'BCA';
$account_number = '0000000000';
$account_holder = 'GreenCycle (Simulasi)';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Pembayaran - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">

    <style>

        .payment-container {
            max-width: 750px;
            margin: 50px auto;
            padding: 20px;
        }

        .payment-card {
            background: white;
            border: 1px solid #e5ebe3;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
        }

        .payment-card h2 {
            color: #145c3b;
            margin-bottom: 20px;
        }

        .bank-box {
            background: #edf6ec;
            padding: 22px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .bank-name {
            font-size: 22px;
            font-weight: bold;
            color: #145c3b;
        }

        .account-number {
            font-size: 27px;
            font-weight: bold;
            letter-spacing: 2px;
            margin: 15px 0;
        }

        .payment-total {
            color: #198754;
            font-size: 27px;
            font-weight: bold;
        }

        .simulation-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.6;
        }

        .payment-button {
            display: inline-block;
            padding: 13px 22px;
            background: #198754;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 15px;
        }

        .payment-button:hover {
            background: #146c43;
        }

    </style>

</head>

<body>

<main class="payment-container">

    <h1>Instruksi Pembayaran</h1>

    <p>
        Silakan periksa informasi pembayaran pesanan GreenCycle.
    </p>
    <?php if (!empty($_SESSION['flash'])): ?>
        <p role="status" style="padding:12px;background:#e8f5e9;color:#176b43;border-radius:8px;"><?= e($_SESSION['flash']) ?></p>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <p role="alert" style="padding:12px;background:#ffebee;color:#b42318;border-radius:8px;"><?= e($_SESSION['flash_error']) ?></p>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="payment-card">

        <h2>Detail Pesanan</h2>

        <p>
            <strong>Kode Pesanan:</strong>
            <?= e($order['order_code']) ?>
        </p>

        <p>
            <strong>Tanggal Pesanan:</strong>
            <?= e($order['created_at']) ?>
        </p>

        <p>
            <strong>Metode Pembayaran:</strong>
            Transfer Bank
        </p>

        <p>
            <strong>Status Pesanan:</strong>
            <?= e(ucfirst($order['status'])) ?>
        </p>

        <hr>

        <p>Total Pembayaran:</p>

        <div class="payment-total">
            Rp <?= number_format(
                (float)$order['total_amount'],
                0,
                ',',
                '.'
            ) ?>
        </div>

    </div>


    <div class="payment-card">

        <h2>Transfer ke Rekening Berikut</h2>

        <div class="bank-box">

            <div class="bank-name">
                <?= e($bank_name) ?>
            </div>

            <p>Nomor Rekening:</p>

            <div class="account-number">
                <?= e($account_number) ?>
            </div>

            <p>
                Atas Nama:
                <strong><?= e($account_holder) ?></strong>
            </p>

        </div>

        <div class="simulation-warning">

            <strong>Perhatian: Rekening Simulasi</strong>

            <p>
                Nomor rekening ini hanya digunakan untuk
                pengujian dan pengembangan website GreenCycle.
                Jangan melakukan transfer uang sungguhan.
            </p>

        </div>

    </div>


    <div class="payment-card">

        <h2>Langkah Pembayaran</h2>

        <ol>
            <li>Periksa total pembayaran.</li>
            <li>Gunakan informasi rekening simulasi di atas.</li>
            <li>Unggah bukti transfer untuk diperiksa oleh admin.</li>
            <li>Jangan mengirim uang sungguhan karena rekening ini hanya simulasi.</li>
        </ol>

        <?php if ($order['payment_status'] === 'pending'): ?>
            <?php if (empty($order['payment_proof'])): ?>
                <form action="/GreenCycle/customer/upload-payment-proof.php" method="POST" enctype="multipart/form-data" style="margin-top:20px;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="order_code" value="<?= e($order['order_code']) ?>">
                    <label for="paymentProof"><strong>Unggah Bukti Pembayaran (JPG/PNG/WEBP, maks. 3MB)</strong></label><br>
                    <input id="paymentProof" type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp" required style="display:block;margin:12px 0;">
                    <button type="submit" class="payment-button" style="border:0;cursor:pointer;">Kirim Bukti Pembayaran</button>
                </form>
            <?php else: ?>
                <p><strong>Bukti pembayaran sudah diunggah.</strong> Status: menunggu verifikasi admin.</p>
            <?php endif; ?>
        <?php elseif ($order['payment_status'] === 'verified'): ?>
            <p><strong>Pembayaran telah diverifikasi.</strong></p>
        <?php else: ?>
            <p><strong>Pembayaran ditolak.</strong> Silakan hubungi admin.</p>
        <?php endif; ?>

        <a href="/GreenCycle/pages/products.php" class="payment-button">Kembali ke Katalog</a>

    </div>

</main>

</body>
</html>