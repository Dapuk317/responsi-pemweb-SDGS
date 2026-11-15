<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer']);

// Mengambil input pencarian.
$search = trim((string) ($_GET['q'] ?? ''));

// Mengambil kategori yang dipilih.
$category_id = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
);

if (
    $category_id === false ||
    $category_id === null ||
    $category_id < 0
) {
    $category_id = 0;
}

// Membatasi panjang pencarian.
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

$categories = [];
$products = [];
$error = '';

// Mengatur URL gambar produk.
function catalog_image_url($photo)
{
    $photo = trim((string) $photo);

    if ($photo === '') {
        return '';
    }

    // URL eksternal hanya menggunakan HTTPS.
    if (filter_var($photo, FILTER_VALIDATE_URL)) {
        $scheme = strtolower(
            (string) parse_url($photo, PHP_URL_SCHEME)
        );

        return $scheme === 'https' ? $photo : '';
    }

    // Mencegah path traversal.
    if (
        str_contains($photo, '..') ||
        str_contains($photo, "\0")
    ) {
        return '';
    }

    $photo = preg_replace(
        '#^/?GreenCycle/#i',
        '',
        $photo
    );

    return '/GreenCycle/' . ltrim($photo, '/');
}

try {

    // Mengambil kategori produk.
    $categoryQuery = $conn->prepare(
        "SELECT id, category_name
         FROM categories
         ORDER BY category_name ASC"
    );

    $categoryQuery->execute();

    $categoryResult = $categoryQuery->get_result();

    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }

    $categoryQuery->close();

    // Mengambil produk aktif dari toko dan pengguna aktif.
    $sql = "
        SELECT
            p.id,
            p.product_name,
            p.description,
            p.material_info,
            p.price,
            p.stock,
            p.product_photo,
            c.category_name,
            s.store_name

        FROM products p

        INNER JOIN categories c
            ON p.category_id = c.id

        INNER JOIN stores s
            ON p.store_id = s.id

        INNER JOIN users u
            ON s.user_id = u.id

        WHERE
            p.status = 'active'
            AND s.status = 'active'
            AND u.status = 'active'

            AND (? = '' OR p.product_name LIKE ?)

            AND (? = 0 OR p.category_id = ?)

        ORDER BY p.created_at DESC

        LIMIT 24
    ";

    $stmt = $conn->prepare($sql);

    $searchLike = '%' . $search . '%';

    $stmt->bind_param(
        'ssii',
        $search,
        $searchLike,
        $category_id,
        $category_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    $stmt->close();

} catch (mysqli_sql_exception $exception) {

    error_log(
        'GreenCycle catalog query error: ' .
        $exception->getMessage()
    );

    $error = 'Terjadi kesalahan saat mengambil data produk. Periksa koneksi database dan struktur tabel.';
}

$totalProducts = count($products);

$customerName = $_SESSION['name'] ?? 'Customer';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="description"
          content="Temukan produk daur ulang kreatif dan ramah lingkungan di GreenCycle.">

    <title>Katalog Produk - GreenCycle</title>

    <link rel="stylesheet"
          href="/GreenCycle/assets/css/style.css">

    <style>

        .catalog-page {
            background: #f8f9f3;
            min-height: 70vh;
            padding: 64px 0 84px;
        }

        .catalog-wrap {
            width: min(1100px, calc(100% - 40px));
            margin: 0 auto;
        }

        .catalog-heading {
            text-align: center;
            max-width: 760px;
            margin: 0 auto 38px;
        }

        .catalog-heading .section-label {
            display: inline-block;
            margin-bottom: 14px;
        }

        .catalog-heading h1 {
            color: #164b35;
            font-size: clamp(30px, 4vw, 42px);
            line-height: 1.2;
            margin: 0 0 14px;
        }

        .catalog-heading h1 span {
            color: #55a653;
        }

        .catalog-heading p {
            color: #718078;
            line-height: 1.7;
            margin: 0;
        }

        /* SEARCH DAN FILTER */

        .catalog-filter {
            background: #fff;
            border: 1px solid #e6ece3;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 54px;
            box-shadow: 0 10px 30px rgba(20,65,45,.06);
        }

        .catalog-filter form {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) 210px auto auto;
            gap: 10px;
            align-items: center;
        }

        .catalog-filter input,
        .catalog-filter select {
            width: 100%;
            min-height: 46px;
            border: 1px solid #e1e9df;
            border-radius: 10px;
            background: #fff;
            color: #244c39;
            padding: 0 14px;
            font: inherit;
        }

        .catalog-filter button,
        .catalog-reset {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            padding: 0 20px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .catalog-filter button {
            border: 0;
            background: #19734d;
            color: #fff;
        }

        .catalog-filter button:hover {
            background: #125b3b;
        }

        .catalog-reset {
            border: 1px solid #dce7dc;
            color: #246044;
            background: #fff;
        }

        /* JUDUL HASIL */

        .catalog-results-head {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 20px;
            margin-bottom: 22px;
        }

        .catalog-results-head h2 {
            color: #164b35;
            margin: 0 0 6px;
            font-size: 25px;
        }

        .catalog-results-head p {
            color: #718078;
            margin: 0;
        }

        .catalog-count {
            white-space: nowrap;
            background: #eaf4e8;
            color: #19734d;
            padding: 10px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
        }

        /* GRID PRODUK */

        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .catalog-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e7ece5;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(20,65,45,.05);
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .catalog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 14px 30px rgba(20,65,45,.10);
        }

        .catalog-card-image {
            height: 220px;
            background: #edf3e9;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .catalog-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .catalog-image-placeholder {
            color: #26744d;
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: center;
            font-size: 38px;
        }

        .catalog-image-placeholder small {
            font-size: 12px;
        }

        .catalog-card-category {
            position: absolute;
            left: 14px;
            top: 14px;
            background: #fff;
            color: #19734d;
            border-radius: 30px;
            padding: 7px 11px;
            font-size: 11px;
            font-weight: 700;
        }

        .catalog-card-info {
            padding: 18px;
        }

        .catalog-card-info h3 {
            color: #164b35;
            font-size: 18px;
            margin: 0 0 8px;
        }

        .catalog-card-info p {
            color: #78847d;
            font-size: 13px;
            line-height: 1.6;
            margin: 5px 0;
        }

        .catalog-card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 12px;
            border-top: 1px solid #edf0eb;
            padding-top: 14px;
            margin-top: 14px;
        }

        .catalog-price small {
            display: block;
            color: #87928b;
            font-size: 11px;
            margin-bottom: 4px;
        }

        .catalog-price strong {
            color: #19734d;
            font-size: 17px;
        }

        .catalog-stock {
            color: #78847d;
            font-size: 12px;
        }

        /* TOMBOL DETAIL PRODUK */

        .catalog-detail-link {
            display: block;
            width: 100%;
            margin-top: 16px;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            background: #19734d;
            color: #fff;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            transition: background .2s ease;
        }

        .catalog-detail-link:hover {
            background: #125b3b;
            color: #fff;
        }

        /* PRODUK KOSONG */

        .catalog-empty {
            min-height: 270px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 35px;
            border: 1px solid #e4eadd;
            border-radius: 16px;
            background: #fff;
        }

        .catalog-empty-icon {
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #edf5e9;
            color: #19734d;
            font-size: 30px;
            margin-bottom: 18px;
        }

        .catalog-empty h3 {
            color: #164b35;
            margin: 0 0 8px;
        }

        .catalog-empty p {
            color: #718078;
            max-width: 500px;
            line-height: 1.6;
            margin: 0;
        }

        .catalog-limit-note {
            color: #87928b;
            font-size: 12px;
            text-align: center;
            margin-top: 22px;
        }

        /* FOOTER */

        .catalog-footer {
            background: #124331;
            color: #fff;
            padding: 34px 20px;
            text-align: center;
        }

        .catalog-footer p {
            margin: 6px 0;
            color: #d4e3d9;
            font-size: 13px;
        }

        /* RESPONSIVE */

        @media (max-width: 800px) {

            .catalog-filter form {
                grid-template-columns: 1fr 1fr;
            }

            .catalog-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

        }

        @media (max-width: 560px) {

            .catalog-page {
                padding: 42px 0 60px;
            }

            .catalog-filter form {
                grid-template-columns: 1fr;
            }

            .catalog-grid {
                grid-template-columns: 1fr;
            }

            .catalog-results-head {
                align-items: start;
                flex-direction: column;
            }

            .catalog-card-image {
                height: 230px;
            }

        }

    </style>

</head>

<body>

<!-- NAVBAR -->

<header class="navbar">

    <div class="container nav-container">

        <a href="/GreenCycle/pages/home.php" class="brand">

            <img
                src="/GreenCycle/assets/images/greencycle-logo.png"
                alt="GreenCycle"
                class="brand-logo"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';"
            >

            <span class="brand-fallback">
                Green<span>Cycle</span>
            </span>

        </a>

        <button
            class="menu-toggle"
            id="menuToggle"
            type="button"
            aria-label="Buka menu"
            aria-expanded="false"
        >
            ☰
        </button>

        <nav class="nav-menu" id="navMenu">

            <a href="/GreenCycle/pages/home.php" class="nav-link">
                Beranda
            </a>

            <a href="/GreenCycle/pages/home.php#kategori" class="nav-link">
                Kategori
            </a>

            <a href="/GreenCycle/pages/products.php"
               class="nav-link active">
                Produk
            </a>

            <a href="/GreenCycle/pages/cart.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                Keranjang
            </a>

            <a href="/GreenCycle/pages/order-history.php"
                class="nav-link">
                <i class="fas fa-history"></i>
                Riwayat Pesanan
            </a>

            <a href="/GreenCycle/pages/home.php#tentang"
               class="nav-link">
                Tentang Kami
            </a>

            <div class="nav-user">

                <span class="user-name">
                    Halo, <?= e($customerName) ?>
                </span>

                <form action="/GreenCycle/auth/logout.php"
                      method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrf_token()) ?>"
                    >

                    <button type="submit" class="logout-btn">
                        Logout
                    </button>

                </form>

            </div>

        </nav>

    </div>

</header>


<!-- KONTEN UTAMA -->

<main class="catalog-page">

    <div class="catalog-wrap">

        <!-- JUDUL HALAMAN -->

        <section class="catalog-heading">

            <span class="section-label">
                GREEN LIVING MARKETPLACE
            </span>

            <h1>
                Temukan Produk
                <span>Ramah Lingkungan</span>
            </h1>

            <p>
                Jelajahi berbagai produk kreatif hasil daur ulang.
                Pilih produk favoritmu dan dukung gaya hidup
                yang lebih berkelanjutan.
            </p>

        </section>


        <!-- PENCARIAN DAN FILTER -->

        <section class="catalog-filter">

            <form
                action="/GreenCycle/pages/products.php"
                method="GET"
            >

                <input
                    type="search"
                    name="q"
                    placeholder="Cari produk yang kamu inginkan..."
                    value="<?= e($search) ?>"
                    maxlength="100"
                    aria-label="Cari produk"
                >

                <select name="category"
                        aria-label="Pilih kategori">

                    <option value="0">
                        Semua Kategori
                    </option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= $category_id === (int) $category['id']
                                ? 'selected'
                                : '' ?>
                        >
                            <?= e($category['category_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <button type="submit">
                    Cari Produk
                </button>

                <?php if ($search !== '' || $category_id > 0): ?>

                    <a
                        class="catalog-reset"
                        href="/GreenCycle/pages/products.php"
                    >
                        Reset
                    </a>

                <?php endif; ?>

            </form>

        </section>


        <!-- DAFTAR PRODUK -->

        <section class="catalog-results">

            <div class="catalog-results-head">

                <div>

                    <h2>Jelajahi Produk</h2>

                    <p>
                        Temukan produk pilihan dari para pengrajin lokal.
                    </p>

                </div>

                <span class="catalog-count">
                    <?= $totalProducts ?> produk ditemukan
                </span>

            </div>


            <?php if ($error !== ''): ?>

                <div class="catalog-empty">

                    <h3>Produk belum dapat ditampilkan</h3>

                    <p>
                        <?= e($error) ?>
                    </p>

                </div>

            <?php elseif ($totalProducts > 0): ?>

                <div class="catalog-grid">

                    <?php foreach ($products as $product): ?>

                        <?php
                        $imageUrl = catalog_image_url(
                            $product['product_photo']
                        );
                        ?>

                        <article class="catalog-card">

                            <!-- GAMBAR PRODUK -->

                            <div class="catalog-card-image">

                                <?php if ($imageUrl !== ''): ?>

                                    <img
                                        src="<?= e($imageUrl) ?>"
                                        alt="<?= e($product['product_name']) ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="catalog-image-placeholder">

                                        <span>♻</span>

                                        <small>
                                            GreenCycle Product
                                        </small>

                                    </div>

                                <?php endif; ?>

                                <span class="catalog-card-category">
                                    <?= e($product['category_name']) ?>
                                </span>

                            </div>


                            <!-- INFORMASI PRODUK -->

                            <div class="catalog-card-info">

                                <h3>
                                    <?= e($product['product_name']) ?>
                                </h3>

                                <p>
                                    Toko:
                                    <?= e($product['store_name']) ?>
                                </p>

                                <?php if (!empty($product['description'])): ?>

                                    <p>
                                        <?= e(
                                            mb_strimwidth(
                                                (string) $product['description'],
                                                0,
                                                100,
                                                '...'
                                            )
                                        ) ?>
                                    </p>

                                <?php endif; ?>


                                <!-- HARGA DAN STOK -->

                                <div class="catalog-card-bottom">

                                    <div class="catalog-price">

                                        <small>Harga</small>

                                        <strong>
                                            Rp <?= number_format(
                                                (float) $product['price'],
                                                0,
                                                ',',
                                                '.'
                                            ) ?>
                                        </strong>

                                    </div>

                                    <span class="catalog-stock">
                                        Stok:
                                        <?= (int) $product['stock'] ?>
                                    </span>

                                </div>


                                <!-- TOMBOL DETAIL PRODUK -->

                                <a
                                    href="/GreenCycle/pages/product-detail.php?id=<?= (int) $product['id'] ?>"
                                    class="catalog-detail-link"
                                    aria-label="Lihat detail <?= e($product['product_name']) ?>"
                                >
                                    Lihat Detail Produk
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

                <p class="catalog-limit-note">
                    Menampilkan maksimal 24 produk terbaru.
                </p>

            <?php else: ?>

                <div class="catalog-empty">

                    <div class="catalog-empty-icon">
                        ♻
                    </div>

                    <h3>
                        <?= $search !== '' || $category_id > 0
                            ? 'Produk tidak ditemukan'
                            : 'Belum Ada Produk' ?>
                    </h3>

                    <p>

                        <?php if ($search !== '' || $category_id > 0): ?>

                            Tidak ditemukan produk yang sesuai
                            dengan filter kamu. Coba kata kunci
                            atau kategori lain.

                        <?php else: ?>

                            Produk GreenCycle akan segera hadir.
                            Silakan kunjungi kembali nanti.

                        <?php endif; ?>

                    </p>

                </div>

            <?php endif; ?>

        </section>

    </div>

</main>


<!-- FOOTER -->

<footer class="catalog-footer">

    <strong>GreenCycle</strong>

    <p>
        Belanja bijak, dukung pengrajin lokal,
        dan jaga lingkungan bersama.
    </p>

    <p>
        © <?= date('Y') ?> GreenCycle.
        Mendukung SDG 8 dan SDG 12.
    </p>

</footer>


<!-- JAVASCRIPT -->

<script src="/GreenCycle/assets/js/main.js"></script>

</body>
</html>