<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$user_id = (int) $_SESSION['user_id'];

// Mengambil seluruh riwayat pesanan customer.
$stmt = $conn->prepare("
    SELECT
        o.id,
        o.order_code,
        o.order_date,
        o.subtotal,
        o.shipping_cost,
        o.total_amount,
        o.payment_method,
        o.status,
        COUNT(oi.id) AS total_items
    FROM orders o
    LEFT JOIN order_items oi
        ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY
        o.id,
        o.order_code,
        o.order_date,
        o.subtotal,
        o.shipping_cost,
        o.total_amount,
        o.payment_method,
        o.status
    ORDER BY o.order_date DESC, o.id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

// Label status pesanan.
function order_status_label($status)
{
    $labels = [
        'pending' => 'Menunggu Pembayaran',
        'processing' => 'Sedang Diproses',
        'shipped' => 'Sedang Dikirim',
        'complete' => 'Selesai'
    ];

    return $labels[$status] ?? ucfirst($status);
}

// Warna status.
function order_status_color($status)
{
    $colors = [
        'pending' => '#f0ad4e',
        'processing' => '#0d6efd',
        'shipped' => '#6f42c1',
        'complete' => '#198754'
    ];

    return $colors[$status] ?? '#6c757d';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Riwayat Pesanan - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">

    <style>

        .history-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 20px;
        }

        .history-container h1 {
            color: #145c3b;
            margin-bottom: 10px;
        }

        .history-card {
            background: white;
            border: 1px solid #e5ebe3;
            border-radius: 12px;
            padding: 22px;
            margin: 20px 0;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .order-code {
            font-weight: bold;
            color: #145c3b;
        }

        .status {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: bold;
        }

        .history-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 20px 0;
        }

        .info-label {
            color: #777;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .info-value {
            font-weight: bold;
        }

        .history-total {
            color: #198754;
            font-size: 21px;
            font-weight: bold;
        }

        .detail-button {
            display: inline-block;
            padding: 11px 20px;
            background: #198754;
            color: white;
            text-decoration: none;
            border-radius: 7px;
            font-weight: bold;
        }

        .empty-history {
            text-align: center;
            background: #f1f7ef;
            padding: 45px 20px;
            border-radius: 12px;
            margin-top: 25px;
        }

        .empty-history a {
            display: inline-block;
            margin-top: 15px;
            color: white;
            background: #198754;
            padding: 12px 22px;
            border-radius: 7px;
            text-decoration: none;
        }

        @media (max-width: 650px) {
            .history-info {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

    </style>

</head>

<body>

<main class="history-container">

    <h1>Riwayat Pesanan</h1>

    <p>
        Lihat seluruh transaksi dan status pesanan
        yang pernah kamu lakukan di GreenCycle.
    </p>

    <p>
        <a href="/GreenCycle/pages/products.php">
            &larr; Kembali ke Katalog
        </a>
    </p>

    <?php if (empty($orders)): ?>

        <div class="empty-history">

            <h2>Belum Ada Pesanan</h2>

            <p>
                Kamu belum memiliki riwayat pembelian.
            </p>

            <a href="/GreenCycle/pages/products.php">
                Mulai Belanja
            </a>

        </div>

    <?php else: ?>

        <?php foreach ($orders as $order): ?>

            <div class="history-card">

                <div class="history-header">

                    <div>

                        <div class="order-code">
                            <?= e($order['order_code']) ?>
                        </div>

                        <small>
                            <?= e(date(
                                'd-m-Y H:i',
                                strtotime($order['order_date'])
                            )) ?>
                        </small>

                    </div>

                    <span class="status"
                          style="background: <?= e(
                              order_status_color($order['status'])
                          ) ?>">

                        <?= e(
                            order_status_label($order['status'])
                        ) ?>

                    </span>

                </div>

                <hr>

                <div class="history-info">

                    <div>

                        <div class="info-label">
                            Jumlah Produk
                        </div>

                        <div class="info-value">
                            <?= (int) $order['total_items'] ?>
                            item
                        </div>

                    </div>

                    <div>

                        <div class="info-label">
                            Metode Pembayaran
                        </div>

                        <div class="info-value">
                            <?= e($order['payment_method']) ?>
                        </div>

                    </div>

                    <div>

                        <div class="info-label">
                            Total Pembayaran
                        </div>

                        <div class="history-total">
                            Rp <?= number_format(
                                (float) $order['total_amount'],
                                0,
                                ',',
                                '.'
                            ) ?>
                        </div>

                    </div>

                </div>

                <a class="detail-button"
                   href="/GreenCycle/pages/order-detail.php?id=<?= (int) $order['id'] ?>">

                    Lihat Detail Pesanan

                </a>

                <?php if (
                    $order['status'] === 'pending' &&
                    $order['payment_method'] === 'transfer'
                ): ?>

                    <a class="detail-button"
                       href="/GreenCycle/pages/payment.php?code=<?= urlencode($order['order_code']) ?>"
                       style="background:#0d6efd;margin-left:5px;">

                        Instruksi Pembayaran

                    </a>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

</body>
</html>