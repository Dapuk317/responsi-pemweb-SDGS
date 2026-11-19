<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Mengambil ID pengguna yang sedang login.
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Mengambil ID produk dari URL.
$product_id = (int)($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header('Location: products.php');
    exit;
}

// Token keamanan form.
if (empty($_SESSION['cart_csrf_token'])) {
    $_SESSION['cart_csrf_token'] = bin2hex(random_bytes(32));
}

$cart_message = '';
$cart_error = '';

// Mengambil detail produk.
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.product_name,
        p.description,
        p.material_info,
        p.price,
        p.stock,
        p.product_photo,
        c.category_name,
        s.store_name,
        s.address AS store_address
    FROM products p
    INNER JOIN categories c ON p.category_id = c.id
    INNER JOIN stores s ON p.store_id = s.id
    INNER JOIN users u ON s.user_id = u.id
    WHERE p.id = ?
      AND p.status = 'active'
      AND s.status = 'active'
      AND u.status = 'active'
    LIMIT 1
");

$stmt->bind_param("i", $product_id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();
$not_found = !$product;


// ======================================
// MENGAMBIL DATA ULASAN PRODUK
// ======================================

$review_count = 0;
$average_rating = 0;
$reviews = [];
$eligible_order_item_id = null;

// Mengambil rata-rata rating dan jumlah ulasan.
if (!$not_found) {

    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_reviews,
            COALESCE(AVG(rating), 0) AS average_rating
        FROM reviews
        WHERE product_id = ?
    ");

    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $review_summary = $stmt->get_result()->fetch_assoc();

    $review_count = (int) $review_summary['total_reviews'];
    $average_rating = (float) $review_summary['average_rating'];

    // Mengambil daftar ulasan beserta nama pelanggan.
    $stmt = $conn->prepare("
        SELECT
            r.rating,
            r.comment,
            r.created_at,
            u.name AS reviewer_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");

    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($review = $result->fetch_assoc()) {
        $reviews[] = $review;
    }

    // Memeriksa apakah pelanggan memiliki pesanan selesai
    // yang produknya belum pernah diulas.
    if ($user_id > 0) {

        $stmt = $conn->prepare("
            SELECT oi.id
            FROM order_items oi
            INNER JOIN orders o
                ON oi.order_id = o.id
            LEFT JOIN reviews r
                ON r.order_item_id = oi.id
            WHERE oi.product_id = ?
              AND o.user_id = ?
              AND o.status = 'completed'
              AND r.id IS NULL
            ORDER BY o.order_date DESC
            LIMIT 1
        ");

        $stmt->bind_param(
            "ii",
            $product_id,
            $user_id
        );

        $stmt->execute();

        $eligible_item = $stmt->get_result()->fetch_assoc();

        if ($eligible_item) {
            $eligible_order_item_id = (int) $eligible_item['id'];
        }
    }
}

// Notifikasi ulasan.
$review_flash = $_SESSION['review_flash'] ?? '';
unset($_SESSION['review_flash']);

// Memproses penambahan produk ke keranjang.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$not_found) {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['cart_csrf_token'], $token)
    ) {
        $cart_error = 'Permintaan tidak valid. Silakan coba kembali.';

    } else {

        $quantity = filter_input(
            INPUT_POST,
            'quantity',
            FILTER_VALIDATE_INT
        );

        if ($user_id <= 0) {
            $cart_error = 'Silakan login kembali.';

        } elseif (
            $quantity === false ||
            $quantity === null ||
            $quantity < 1
        ) {
            $cart_error = 'Jumlah produk tidak valid.';

        } else {

            try {

                $conn->begin_transaction();

                // Memeriksa stok terbaru.
                $check = $conn->prepare("
                    SELECT stock
                    FROM products
                    WHERE id = ?
                      AND status = 'active'
                    FOR UPDATE
                ");

                $check->bind_param("i", $product_id);
                $check->execute();

                $current_product = $check->get_result()->fetch_assoc();

                if (!$current_product) {
                    throw new Exception(
                        'Produk sudah tidak tersedia.'
                    );
                }

                $stock = (int)$current_product['stock'];

                if ($stock <= 0) {
                    throw new Exception('Stok produk sudah habis.');
                }

                // Mencari keranjang pengguna.
                $cart_stmt = $conn->prepare("
                    SELECT id
                    FROM carts
                    WHERE user_id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $cart_stmt->bind_param("i", $user_id);
                $cart_stmt->execute();

                $cart = $cart_stmt->get_result()->fetch_assoc();

                // Membuat keranjang jika belum tersedia.
                if (!$cart) {

                    $create_cart = $conn->prepare("
                        INSERT INTO carts (user_id)
                        VALUES (?)
                    ");

                    $create_cart->bind_param("i", $user_id);
                    $create_cart->execute();

                    $cart_id = (int)$conn->insert_id;

                } else {

                    $cart_id = (int)$cart['id'];
                }

                // Memeriksa apakah produk sudah ada.
                $item_stmt = $conn->prepare("
                    SELECT id, quantity
                    FROM cart_items
                    WHERE cart_id = ?
                      AND product_id = ?
                    LIMIT 1
                ");

                $item_stmt->bind_param(
                    "ii",
                    $cart_id,
                    $product_id
                );

                $item_stmt->execute();

                $item = $item_stmt->get_result()->fetch_assoc();

                if ($item) {

                    // Menambahkan jumlah produk yang sudah ada.
                    $new_quantity =
                        (int)$item['quantity'] + $quantity;

                    if ($new_quantity > $stock) {
                        throw new Exception(
                            'Jumlah melebihi stok yang tersedia.'
                        );
                    }

                    $update = $conn->prepare("
                        UPDATE cart_items
                        SET quantity = ?
                        WHERE id = ?
                    ");

                    $update->bind_param(
                        "ii",
                        $new_quantity,
                        $item['id']
                    );

                    $update->execute();

                } else {

                    if ($quantity > $stock) {
                        throw new Exception(
                            'Jumlah melebihi stok yang tersedia.'
                        );
                    }

                    // Menambahkan produk baru.
                    $insert = $conn->prepare("
                        INSERT INTO cart_items
                            (cart_id, product_id, quantity)
                        VALUES (?, ?, ?)
                    ");

                    $insert->bind_param(
                        "iii",
                        $cart_id,
                        $product_id,
                        $quantity
                    );

                    $insert->execute();
                }

                // Memperbarui waktu keranjang.
                $update_cart = $conn->prepare("
                    UPDATE carts
                    SET updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

                $update_cart->bind_param("i", $cart_id);
                $update_cart->execute();

                $conn->commit();

                // Menghindari pengiriman ulang form.
                header(
                    'Location: product-detail.php?id=' .
                    $product_id . '&cart=added'
                );

                exit;

            } catch (Throwable $e) {

                $conn->rollback();

                $cart_error = $e->getMessage();
            }
        }
    }
}

// Notifikasi berhasil.
if (($_GET['cart'] ?? '') === 'added') {
    $cart_message = 'Produk berhasil ditambahkan ke keranjang!';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= $not_found ? 'Produk Tidak Ditemukan' : e($product['product_name']) ?>
        - GreenCycle
    </title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>

        .detail-container {
            max-width: 1100px;
            margin: 50px auto;
            padding: 20px;
        }

        .detail-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: white;
            padding: 30px;
            border-radius: 15px;
        }

        .detail-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 12px;
        }

        .detail-info h1 {
            font-size: 30px;
            margin-bottom: 15px;
        }

        .detail-price {
            font-size: 26px;
            font-weight: bold;
            color: #198754;
            margin: 20px 0;
        }

        .detail-info p {
            margin: 12px 0;
        }

        .back-button {
            display: inline-block;
            margin-bottom: 25px;
            color: #198754;
        }

        .cart-form {
            margin-top: 25px;
            padding: 20px;
            background: #f5f9f3;
            border-radius: 12px;
        }

        .cart-form label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .cart-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .cart-button {
            display: block;
            width: 100%;
            padding: 14px;
            background: #198754;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 15px;
            text-align: center;
            text-decoration: none;
        }

        .cart-button:hover {
            background: #146c43;
        }

        .cart-button:disabled {
            background: #999;
            cursor: not-allowed;
        }

        .view-cart-button {
            display: block;
            width: 100%;
            padding: 14px;
            margin-top: 12px;
            background: white;
            color: #198754;
            border: 2px solid #198754;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
        }

        .view-cart-button:hover {
            background: #eaf5ed;
        }

        .cart-success {
            padding: 14px;
            margin-bottom: 20px;
            background: #d1e7dd;
            color: #0f5132;
            border-radius: 8px;
        }

        .cart-error {
            padding: 14px;
            margin-bottom: 20px;
            background: #f8d7da;
            color: #842029;
            border-radius: 8px;
        }

        /* ULASAN PRODUK */

        .review-section {
            margin-top: 35px;
            padding: 30px;
            background: white;
            border-radius: 15px;
        }

        .review-section h2 {
            color: #145c3c;
            margin-bottom: 20px;
        }

        .review-summary {
            padding: 20px;
            background: #f1f8f1;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .review-average {
            font-size: 32px;
            font-weight: bold;
            color: #198754;
        }

        .review-stars {
            color: #f5a623;
            font-size: 22px;
            letter-spacing: 2px;
        }

        .review-item {
            padding: 18px 0;
            border-bottom: 1px solid #ddd;
        }

        .review-item p {
            margin: 10px 0;
            line-height: 1.6;
        }

        .review-form {
            margin-top: 25px;
            padding: 22px;
            background: #f5f9f3;
            border-radius: 12px;
        }

        .review-form label {
            display: block;
            font-weight: bold;
            margin: 15px 0 8px;
        }

        .review-form select,
        .review-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
        }

        .review-form textarea {
            min-height: 120px;
            resize: vertical;
        }

        .review-submit {
            width: 100%;
            padding: 13px;
            margin-top: 15px;
            background: #198754;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .review-submit:hover {
            background: #146c43;
        }

        .review-notice {
            padding: 15px;
            background: #eaf5ed;
            border-radius: 8px;
            margin-top: 20px;
        }

        @media (max-width: 700px) {

            .detail-content {
                grid-template-columns: 1fr;
            }

            .detail-image {
                height: 280px;
            }
        }

    </style>

</head>

<body>

<div class="detail-container">

    <a href="products.php" class="back-button">
        &larr; Kembali ke Katalog
    </a>

    <?php if ($not_found): ?>

        <h1>Produk Tidak Ditemukan</h1>

        <p>
            Produk mungkin sudah dihapus atau tidak tersedia.
        </p>

        <a href="products.php">
            Kembali ke Katalog Produk
        </a>

    <?php else: ?>

        <div class="detail-content">

            <div>

                <?php if (!empty($product['product_photo'])): ?>

                    <img
                        src="<?= e($product['product_photo']) ?>"
                        alt="<?= e($product['product_name']) ?>"
                        class="detail-image"
                    >

                <?php else: ?>

                    <div class="detail-image"
                         style="display:flex;
                                align-items:center;
                                justify-content:center;
                                background:#eee;">

                        Foto produk belum tersedia.

                    </div>

                <?php endif; ?>

            </div>

            <div class="detail-info">

                <h1>
                    <?= e($product['product_name']) ?>
                </h1>

                <p>
                    Kategori:
                    <strong>
                        <?= e($product['category_name']) ?>
                    </strong>
                </p>

                <div class="detail-price">

                    Rp <?= number_format(
                        (float)$product['price'],
                        0,
                        ',',
                        '.'
                    ) ?>

                </div>

                <p>
                    Stok tersedia:
                    <strong>
                        <?= (int)$product['stock'] ?>
                    </strong>
                </p>

                <hr>

                <h3>Deskripsi Produk</h3>

                <p>
                    <?= nl2br(e($product['description'] ?? '-')) ?>
                </p>

                <h3>Informasi Material</h3>

                <p>
                    <?= nl2br(e($product['material_info'] ?? '-')) ?>
                </p>

                <hr>

                <h3>Informasi Penjual</h3>

                <p>
                    Nama Toko:
                    <?= e($product['store_name']) ?>
                </p>

                <p>
                    Alamat:
                    <?= e($product['store_address'] ?? '-') ?>
                </p>

                <hr>

                <!-- Notifikasi berhasil -->
                <?php if ($cart_message): ?>

                    <div class="cart-success">
                        <?= e($cart_message) ?>
                    </div>

                <?php endif; ?>

                <!-- Notifikasi gagal -->
                <?php if ($cart_error): ?>

                    <div class="cart-error">
                        <?= e($cart_error) ?>
                    </div>

                <?php endif; ?>

                <!-- Form tambah ke keranjang -->
                <form method="POST" class="cart-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['cart_csrf_token']) ?>"
                    >

                    <label for="quantity">
                        Jumlah Produk
                    </label>

                    <input
                        type="number"
                        id="quantity"
                        name="quantity"
                        min="1"
                        max="<?= (int)$product['stock'] ?>"
                        value="1"
                        required
                        <?= (int)$product['stock'] <= 0 ? 'disabled' : '' ?>
                    >

                    <button
                        type="submit"
                        class="cart-button"
                        <?= (int)$product['stock'] <= 0 ? 'disabled' : '' ?>
                    >

                        <?= (int)$product['stock'] <= 0
                            ? 'Stok Habis'
                            : 'Tambah ke Keranjang' ?>

                    </button>

                    <!-- Tombol menuju halaman keranjang -->
                    <a href="cart.php" class="view-cart-button">
                        Lihat Keranjang Belanja
                    </a>

                </form>

            </div>

        </div>

        <!-- ==================================
             ULASAN PRODUK
        =================================== -->

        <section class="review-section" id="reviews">

            <h2>Ulasan Pelanggan</h2>

            <div class="review-summary">

                <div class="review-average">
                    <?= number_format($average_rating, 1) ?> / 5
                </div>

                <div class="review-stars">
                    <?php
                    $rounded_rating = (int) round($average_rating);
                    echo str_repeat('★', $rounded_rating);
                    echo str_repeat('☆', 5 - $rounded_rating);
                    ?>
                </div>

                <p>
                    <?= $review_count ?> ulasan pelanggan
                </p>

            </div>

            <?php if ($review_flash): ?>

                <div class="review-notice">
                    <?= e($review_flash) ?>
                </div>

            <?php endif; ?>

            <h3>Daftar Ulasan</h3>

            <?php if (empty($reviews)): ?>

                <p>
                    Belum ada ulasan untuk produk ini.
                    Jadilah pelanggan pertama yang memberikan ulasan.
                </p>

            <?php else: ?>

                <?php foreach ($reviews as $review): ?>

                    <div class="review-item">

                        <strong>
                            <?= e($review['reviewer_name'] ?? 'Pelanggan GreenCycle') ?>
                        </strong>

                        <div class="review-stars">

                            <?= str_repeat(
                                '★',
                                (int) $review['rating']
                            ) ?>

                            <?= str_repeat(
                                '☆',
                                5 - (int) $review['rating']
                            ) ?>

                        </div>

                        <p>
                            <?= nl2br(e($review['comment'] ?? '')) ?>
                        </p>

                        <small>
                            <?= date(
                                'd-m-Y',
                                strtotime($review['created_at'])
                            ) ?>
                        </small>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

            <hr>

            <h3>Berikan Ulasan</h3>

            <?php if ($user_id <= 0): ?>

                <p>
                    Silakan login untuk memberikan ulasan.
                </p>

            <?php elseif ($eligible_order_item_id): ?>

                <form
                    action="/GreenCycle/customer/submit-review.php"
                    method="POST"
                    class="review-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?= (int) $product_id ?>"
                    >

                    <input
                        type="hidden"
                        name="order_item_id"
                        value="<?= (int) $eligible_order_item_id ?>"
                    >

                    <label for="rating">
                        Penilaian Produk
                    </label>

                    <select
                        name="rating"
                        id="rating"
                        required
                    >
                        <option value="">Pilih Rating</option>
                        <option value="5">★★★★★ - Sangat Baik</option>
                        <option value="4">★★★★ - Baik</option>
                        <option value="3">★★★ - Cukup</option>
                        <option value="2">★★ - Kurang</option>
                        <option value="1">★ - Buruk</option>
                    </select>

                    <label for="comment">
                        Komentar
                    </label>

                    <textarea
                        name="comment"
                        id="comment"
                        placeholder="Bagaimana pengalaman kamu dengan produk ini?"
                        maxlength="2000"
                    ></textarea>

                    <button
                        type="submit"
                        class="review-submit"
                    >
                        Kirim Ulasan
                    </button>

                </form>

            <?php else: ?>

                <div class="review-notice">
                    Kamu dapat memberikan ulasan setelah
                    pesanan produk ini berstatus selesai.
                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

</body>
</html>