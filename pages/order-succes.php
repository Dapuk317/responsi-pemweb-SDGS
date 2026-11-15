<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

// Mengambil data pesanan dari session.
$orders = $_SESSION['last_orders'] ?? [];

// Menghapus data session setelah diambil.
unset($_SESSION['last_orders']);

// Jika tidak ada data pesanan, kembali ke katalog.
if (empty($orders)) {
    header('Location: /GreenCycle/pages/products.php');
    exit;
}

$total_all = 0;

foreach ($orders as $order) {
    $total_all += (float) $order['total_amount'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Pesanan Berhasil - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">

    <style>
        .success-container {
            max-width: 750px;
            margin: 60px auto;
            padding: 20px;
        }

        .success-card {
            background: white;
            border-radius: 15px;
            padding: 35px;
            text-align: center;
            border: 1px solid #e5ebe3;
        }

        .success-icon {
            font-size: 65px;
            color: #198754;
        }

        .success-card h1 {
            color: #198754;
            margin: 15px 0;
        }

        .order-detail {
            background: #f1f8ef;
            padding: 20px;
            border-radius: 10px;
            margin-top: 25px;
            text-align: left;
        }

        .order-row {
            padding: 12px 0;
            border-bottom: 1px solid #ddd;
        }

        .total-payment {
            color: #198754;
            font-size: 25px;
            font-weight: bold;
            margin-top: 15px;
        }

        .success-button {
            display: inline-block;
            background: #198754;
            color: white;
            padding: 13px 25px;
            margin-top: 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .success-button:hover {
            background: #146c43;
        }

        .payment-info {
            background: #fff8e6;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            text-align: left;
        }
    </style>
</head>

<body>

<main class="success-container">

    <div class="success-card">

        <div class="success-icon">
            &#10004;
        </div>

        <h1>Pesanan Berhasil Dibuat!</h1>

        <p>
            Terima kasih telah berbelanja di GreenCycle.
            Pesanan kamu berhasil dibuat.
        </p>

        <div class="order-detail">

            <h2>Detail Pesanan</h2>

            <?php foreach ($orders as $order): ?>

                <div class="order-row">

                    <strong>Kode Pesanan:</strong>

                    <a
                        href="/GreenCycle/pages/payment.php?code=<?= urlencode($order['order_code']) ?>"
                        class="success-button"
                    >
                        Lihat Instruksi Pembayaran
                    </a>

                    <strong>Total Pesanan:</strong>

                    <p>
                        Rp <?= number_format(
                            (float) $order['total_amount'],
                            0,
                            ',',
                            '.'
                        ) ?>
                    </p>

                </div>

            <?php endforeach; ?>

            <p>
                <strong>Metode Pembayaran:</strong>
                Transfer Bank
            </p>

            <p>
                <strong>Status:</strong>
                Menunggu Pembayaran
            </p>

            <div class="total-payment">
                Total Seluruh Pesanan:
                Rp <?= number_format(
                    $total_all,
                    0,
                    ',',
                    '.'
                ) ?>
            </div>

        </div>

        <div class="payment-info">

            <strong>Informasi Pembayaran</strong>

            <p>
                Silakan hubungi administrator GreenCycle
                untuk mendapatkan informasi rekening bank
                dan melakukan konfirmasi pembayaran.
            </p>

            <p>
                Bukti pembayaran akan diproses melalui
                fitur verifikasi pembayaran.
            </p>

        </div>

        <a href="/GreenCycle/pages/products.php"
           class="success-button">
            Kembali Belanja
        </a>

    </div>

</main>

</body>
</html>