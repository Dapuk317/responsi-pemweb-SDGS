<?php
require_once __DIR__ . '/../includes/auth_check.php';

require_role(['customer']);

$user_id = (int) $_SESSION['user_id'];
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$order_id || $order_id <= 0) {
    header('Location: /GreenCycle/pages/order-history.php');
    exit;
}

// Mengambil detail pesanan milik customer.
$stmt = $conn->prepare("
    SELECT
        o.id,
        o.order_code,
        o.order_date,
        o.shipping_address,
        o.phone,
        o.subtotal,
        o.shipping_cost,
        o.total_amount,
        t.payment_method,
        o.status,
        o.notes
    FROM orders o
    LEFT JOIN transactions t ON t.order_id = o.id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

// Mengambil produk dan ulasan yang sudah diberikan.
$stmt = $conn->prepare("
    SELECT
        oi.id AS order_item_id,
        oi.product_id,
        oi.product_name,
        oi.product_photo,
        oi.price,
        oi.quantity,
        oi.subtotal,
        r.id AS review_id,
        r.rating AS review_rating,
        r.comment AS review_comment,
        r.created_at AS review_created_at
    FROM order_items oi
    LEFT JOIN reviews r
        ON r.order_item_id = oi.id
       AND r.user_id = ?
       AND r.product_id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$stmt->bind_param("ii", $user_id, $order_id);
$stmt->execute();

$result = $stmt->get_result();

$items = [];

while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

$stmt->close();

// Status pesanan.
$status_labels = [
    'pending' => 'Menunggu Pembayaran',
    'processing' => 'Sedang Diproses',
    'shipped' => 'Sedang Dikirim',
    'completed' => 'Selesai'
];

$status_colors = [
    'pending' => '#f0ad4e',
    'processing' => '#0d6efd',
    'shipped' => '#6f42c1',
    'completed' => '#198754'
];

$status = $order['status'];

$status_label = $status_labels[$status] ?? ucfirst($status);
$status_color = $status_colors[$status] ?? '#6c757d';

function rupiah($amount)
{
    return 'Rp ' . number_format(
        (float) $amount,
        0,
        ',',
        '.'
    );
}

$review_message = '';

if (isset($_GET['review'])) {
    if ($_GET['review'] === 'success') {
        $review_message = 'Ulasan berhasil dikirim. Terima kasih atas penilaianmu!';
    } elseif ($_GET['review'] === 'duplicate') {
        $review_message = 'Kamu sudah memberikan ulasan untuk produk ini.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Detail Pesanan - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">

    <style>

        .detail-container {
            max-width: 950px;
            margin: 40px auto;
            padding: 20px;
        }

        .detail-card {
            background: white;
            border: 1px solid #e5ebe3;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
        }

        .detail-card h2 {
            color: #145c3b;
            margin-bottom: 20px;
        }

        .order-status {
            display: inline-block;
            padding: 8px 15px;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        .product-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .product-info {
            flex: 1;
        }

        .product-info strong {
            display: block;
            margin-bottom: 5px;
        }

        .product-info small {
            color: #777;
        }

        .product-price {
            text-align: right;
            font-weight: bold;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin: 12px 0;
        }

        .grand-total {
            color: #198754;
            font-size: 23px;
            font-weight: bold;
        }

        .back-button {
            display: inline-block;
            background: #198754;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .info-row {
            margin: 13px 0;
            line-height: 1.6;
        }

        .info-row strong {
            display: block;
            color: #145c3b;
        }

        .review-box {
            background: #f7faf6;
            border: 1px solid #dce9dc;
            border-radius: 10px;
            padding: 18px;
            margin-top: 15px;
        }

        .review-box h3 {
            color: #145c3b;
            margin-top: 0;
        }

        .review-stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
            margin: 12px 0;
        }

        .review-stars input {
            display: none;
        }

        .review-stars label {
            font-size: 32px;
            color: #ccc;
            cursor: pointer;
        }

        .review-stars input:checked ~ label,
        .review-stars label:hover,
        .review-stars label:hover ~ label {
            color: #f5b301;
        }

        .review-textarea {
            width: 100%;
            min-height: 90px;
            padding: 12px;
            border: 1px solid #dce5dc;
            border-radius: 8px;
            box-sizing: border-box;
            resize: vertical;
            font-family: inherit;
        }

        .review-submit {
            background: #198754;
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 12px;
        }

        .review-submit:hover {
            background: #146c43;
        }

        .review-success {
            background: #e8f5e9;
            color: #176b3a;
            padding: 14px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .review-display {
            background: #f0f7ed;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }

        .review-display .stars {
            color: #e6a500;
            font-size: 22px;
        }

        .review-pending {
            color: #777;
            font-size: 14px;
            margin-top: 12px;
        }

        @media(max-width: 600px) {
            .product-row {
                align-items: flex-start;
            }

            .product-price {
                font-size: 13px;
            }
        }

    </style>

</head>

<body>

<main class="detail-container">

    <h1>Detail Pesanan</h1>

    <p>
        Informasi lengkap transaksi GreenCycle.
    </p>

    <?php if ($review_message): ?>
        <div class="review-success">
            <?= e($review_message) ?>
        </div>
    <?php endif; ?>

    <!-- INFORMASI PESANAN -->

    <div class="detail-card">

        <h2>Informasi Pesanan</h2>

        <div class="info-row">
            <strong>Kode Pesanan</strong>
            <?= e($order['order_code']) ?>
        </div>

        <div class="info-row">
            <strong>Tanggal Pesanan</strong>
            <?= e(date(
                'd-m-Y H:i',
                strtotime($order['order_date'])
            )) ?>
        </div>

        <div class="info-row">
            <strong>Status Pesanan</strong>

            <span class="order-status"
                  style="background:<?= e($status_color) ?>">

                <?= e($status_label) ?>

            </span>
        </div>

        <div class="info-row">
            <strong>Metode Pembayaran</strong>
            <?= e(($order['payment_method'] ?? '') === 'bank_transfer' ? 'Transfer Bank' : (($order['payment_method'] ?? '') === 'cash_on_delivery' ? 'COD' : '-')) ?>
        </div>

        <?php if ($order['status'] === 'shipped'): ?>
            <div style="margin-top: 15px; padding: 15px; background: #eef7e6; border-radius: 8px; border: 1px solid #c7e6a7;">
                <p style="margin: 0 0 10px; font-weight: bold; color: #145c3b;">Paket Anda sedang dalam perjalanan. Jika barang daur ulang sudah Anda terima dengan baik, silakan konfirmasi di bawah ini:</p>
                <form action="/GreenCycle/customer/confirm-order.php" method="POST" onsubmit="return confirm('Konfirmasi bahwa barang pesanan daur ulang sudah Anda terima?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <input type="hidden" name="redirect_url" value="/GreenCycle/pages/order-detail.php?id=<?= (int)$order['id'] ?>">
                    <button type="submit" style="background: #176b43; color: white; border: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; cursor: pointer;">
                        ✓ Konfirmasi Pesanan Diterima
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </div>


    <!-- PRODUK DAN ULASAN -->

    <div class="detail-card">

        <h2>Produk yang Dibeli</h2>

        <?php if (empty($items)): ?>

            <p>Detail produk tidak ditemukan.</p>

        <?php else: ?>

            <?php foreach ($items as $item): ?>

                <div class="product-row">

                    <div class="product-info">

                        <strong>
                            <?= e($item['product_name']) ?>
                        </strong>

                        <small>
                            <?= rupiah($item['price']) ?>
                            x <?= (int) $item['quantity'] ?>
                        </small>

                    </div>

                    <div class="product-price">

                        <?= rupiah($item['subtotal']) ?>

                    </div>

                </div>

                <?php if ($status === 'completed'): ?>

                    <?php if (!empty($item['review_id'])): ?>

                        <!-- ULASAN SUDAH DIBERIKAN -->

                        <div class="review-display">

                            <strong>Ulasan Kamu</strong>

                            <div class="stars">
                                <?= str_repeat(
                                    '★',
                                    (int) $item['review_rating']
                                ) ?><?= str_repeat(
                                    '☆',
                                    5 - (int) $item['review_rating']
                                ) ?>
                            </div>

                            <p>
                                <?= nl2br(e(
                                    $item['review_comment'] ?? ''
                                )) ?>
                            </p>

                            <small>
                                Dikirim:
                                <?= e(date(
                                    'd-m-Y H:i',
                                    strtotime($item['review_created_at'])
                                )) ?>
                            </small>

                        </div>

                    <?php else: ?>

                        <!-- FORMULIR ULASAN -->

                        <div class="review-box">

                            <h3>Berikan Ulasan Produk</h3>

                            <p>
                                Bagaimana pengalamanmu membeli
                                <?= e($item['product_name']) ?>?
                            </p>

                            <form
                                action="/GreenCycle/customer/submit-review.php"
                                method="POST"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e(csrf_token()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="order_item_id"
                                    value="<?= (int) $item['order_item_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?= (int) $order_id ?>"
                                >

                                <label>
                                    <strong>Berikan Rating:</strong>
                                </label>

                                <div class="review-stars">

                                    <?php for ($i = 5; $i >= 1; $i--): ?>

                                        <input
                                            type="radio"
                                            id="star<?= $i ?>-<?= (int) $item['order_item_id'] ?>"
                                            name="rating"
                                            value="<?= $i ?>"
                                            required
                                        >

                                        <label
                                            for="star<?= $i ?>-<?= (int) $item['order_item_id'] ?>"
                                            title="<?= $i ?> bintang"
                                        >★</label>

                                    <?php endfor; ?>

                                </div>

                                <label>
                                    <strong>Komentar (Opsional):</strong>
                                </label>

                                <textarea
                                    name="comment"
                                    class="review-textarea"
                                    placeholder="Ceritakan pengalamanmu menggunakan produk ini..."
                                ></textarea>

                                <button
                                    type="submit"
                                    class="review-submit"
                                >
                                    Kirim Ulasan
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <p class="review-pending">
                        Ulasan dapat diberikan setelah pesanan selesai.
                    </p>

                <?php endif; ?>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <!-- INFORMASI PENGIRIMAN -->

    <div class="detail-card">

        <h2>Informasi Pengiriman</h2>

        <div class="info-row">
            <strong>Nomor Telepon</strong>
            <?= e($order['phone']) ?>
        </div>

        <div class="info-row">
            <strong>Alamat Pengiriman</strong>
            <?= nl2br(e($order['shipping_address'])) ?>
        </div>

        <div class="info-row">
            <strong>Catatan Pesanan</strong>
            <?= nl2br(e($order['notes'] ?? '-')) ?>
        </div>

    </div>


    <!-- RINGKASAN PEMBAYARAN -->

    <div class="detail-card">

        <h2>Ringkasan Pembayaran</h2>

        <div class="summary-row">
            <span>Subtotal Produk</span>
            <strong><?= rupiah($order['subtotal']) ?></strong>
        </div>

        <div class="summary-row">
            <span>Ongkos Kirim</span>
            <strong><?= rupiah($order['shipping_cost']) ?></strong>
        </div>

        <hr>

        <div class="summary-row">

            <strong>Total Pembayaran</strong>

            <span class="grand-total">
                <?= rupiah($order['total_amount']) ?>
            </span>

        </div>

    </div>


    <a href="/GreenCycle/pages/order-history.php"
       class="back-button">

        &larr; Kembali ke Riwayat Pesanan

    </a>

    <?php if (
        $order['status'] === 'pending' &&
        $order['payment_method'] === 'bank_transfer'
    ): ?>

        <a href="/GreenCycle/pages/payment.php?code=<?= urlencode($order['order_code']) ?>"
           class="back-button"
           style="background:#0d6efd;margin-left:5px;">

            Lihat Instruksi Pembayaran

        </a>

    <?php endif; ?>

</main>

</body>
</html>