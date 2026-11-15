<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_role(['customer']);

$categories = [];
$products = [];
$dataError = false;
$customerName = $_SESSION['name'] ?? 'Customer';

try {
    $categoryQuery = $conn->query(
        "SELECT id, category_name
         FROM categories
         ORDER BY category_name ASC
         LIMIT 6"
    );
    $categories = $categoryQuery->fetch_all(MYSQLI_ASSOC);

    $productQuery = $conn->query(
        "SELECT
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
         INNER JOIN categories c ON p.category_id = c.id
         INNER JOIN stores s ON p.store_id = s.id
         INNER JOIN users u ON s.user_id = u.id
         WHERE p.status = 'active'
           AND s.status = 'active'
           AND u.status = 'active'
         ORDER BY p.created_at DESC
         LIMIT 8"
    );
    $products = $productQuery->fetch_all(MYSQLI_ASSOC);

} catch (mysqli_sql_exception $e) {
    error_log('GreenCycle home query error: ' . $e->getMessage());
    $dataError = true;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GreenCycle - Marketplace Produk Daur Ulang</title>

    <meta name="description" content="Temukan produk daur ulang berkualitas di GreenCycle. Belanja lebih bijak dan dukung ekonomi berkelanjutan.">

    <link rel="stylesheet" href="/GreenCycle/assets/css/style.css">
</head>

<body>

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

            <a href="/GreenCycle/pages/home.php" class="nav-link active">
                Beranda
            </a>

            <a href="/GreenCycle/pages/home.php#kategori" class="nav-link">
                Kategori
            </a>

            <a href="/GreenCycle/pages/products.php" class="nav-link">
                Produk
            </a>

            <a href="/GreenCycle/pages/home.php#tentang" class="nav-link">
                Tentang Kami
            </a>

            <div class="nav-user">

                <span class="user-name">
                    Halo, <?= e($customerName) ?>
                </span>

                <form action="/GreenCycle/auth/logout.php" method="POST">
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

<main>

    <!-- HERO SECTION -->

    <section class="hero">

        <div class="container hero-container">

            <div class="hero-content">

                <span class="hero-label">
                    ♻ Sustainable Shopping Platform
                </span>

                <h1>
                    Berikan Barang
                    <span>Kesempatan Kedua.</span>
                </h1>

                <p>
                    Temukan berbagai produk kreatif hasil daur ulang
                    yang unik, berkualitas, dan ramah lingkungan.
                    Belanja lebih bijak bersama GreenCycle.
                </p>

                <div class="hero-actions">

                    <a
                        href="/GreenCycle/pages/products.php"
                        class="btn-primary"
                    >
                        Jelajahi Produk →
                    </a>

                    <a
                        href="/GreenCycle/pages/home.php#tentang"
                        class="btn-secondary"
                    >
                        Kenali GreenCycle
                    </a>

                </div>

                <div class="hero-stat">

                    <div>
                        <strong>SDG 8</strong>
                        <span>Ekonomi Berkelanjutan</span>
                    </div>

                    <div>
                        <strong>SDG 12</strong>
                        <span>Konsumsi Bertanggung Jawab</span>
                    </div>

                </div>

            </div>

            <div class="hero-visual">

                <div class="hero-image-box">

                    <div class="hero-image-decoration">
                        ♻
                    </div>

                    <div class="hero-image-text">
                        <span>GREEN LIVING</span>

                        <h2>
                            Small Choices,<br>
                            Big Impact.
                        </h2>

                        <p>Choose sustainable products.</p>
                    </div>

                </div>

                <div class="floating-card">

                    <span class="floating-icon">🌱</span>

                    <div>
                        <strong>Shop Sustainably</strong>
                        <small>Belanja untuk masa depan.</small>
                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- KEUNGGULAN -->

    <section class="benefits">

        <div class="container benefits-grid">

            <div class="benefit-item">
                <span class="benefit-icon">♻</span>

                <div>
                    <h3>Produk Daur Ulang</h3>
                    <p>Produk kreatif dari bahan yang dimanfaatkan kembali.</p>
                </div>
            </div>

            <div class="benefit-item">
                <span class="benefit-icon">🌱</span>

                <div>
                    <h3>Ramah Lingkungan</h3>
                    <p>Mendorong gaya hidup yang lebih berkelanjutan.</p>
                </div>
            </div>

            <div class="benefit-item">
                <span class="benefit-icon">🤝</span>

                <div>
                    <h3>Dukung UMKM</h3>
                    <p>Membantu pengrajin memperluas pasar produknya.</p>
                </div>
            </div>

        </div>

    </section>


    <!-- KATEGORI -->

    <section class="section category-section" id="kategori">

        <div class="container">

            <div class="section-heading">

                <div>
                    <span class="section-label">
                        EXPLORE GREEN LIVING
                    </span>

                    <h2>Jelajahi Kategori</h2>

                    <p>
                        Temukan berbagai produk kreatif dari
                        bahan yang dimanfaatkan kembali.
                    </p>
                </div>

            </div>

            <div class="category-grid">

                <?php if ($categories): ?>

                    <?php foreach ($categories as $category): ?>

                        <a
                            href="/GreenCycle/pages/products.php?category=<?= (int) $category['id'] ?>"
                            class="category-card"
                        >

                            <span class="category-icon">♻</span>

                            <h3>
                                <?= e($category['category_name']) ?>
                            </h3>

                            <span class="category-arrow">→</span>

                        </a>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p class="empty-message">
                        Belum ada kategori produk.
                    </p>

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- PRODUK -->

    <section class="section product-section" id="produk">

        <div class="container">

            <div class="section-heading product-heading">

                <div>
                    <span class="section-label">OUR COLLECTION</span>

                    <h2>Produk Pilihan</h2>

                    <p>
                        Produk daur ulang pilihan dari para
                        pengrajin dan pelaku usaha.
                    </p>
                </div>

                <span class="product-count">
                    <?= count($products) ?> Produk
                </span>

            </div>

            <?php if ($dataError): ?>

                <div class="empty-products">
                    <h3>Data produk belum dapat ditampilkan</h3>

                    <p>
                        Silakan muat ulang halaman atau periksa
                        koneksi database.
                    </p>
                </div>

            <?php elseif ($products): ?>

                <div class="product-grid">

                    <?php foreach ($products as $product): ?>

                        <article class="product-card">

                            <div class="product-image">

                                <?php if (!empty($product['product_photo'])): ?>

                                    <img
                                        src="<?= e($product['product_photo']) ?>"
                                        alt="<?= e($product['product_name']) ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="product-placeholder">
                                        <span>♻</span>
                                        <small>GreenCycle Product</small>
                                    </div>

                                <?php endif; ?>

                                <span class="product-badge">
                                    Recycled Product
                                </span>

                            </div>

                            <div class="product-info">

                                <span class="product-category">
                                    <?= e($product['category_name']) ?>
                                </span>

                                <h3>
                                    <?= e($product['product_name']) ?>
                                </h3>

                                <p class="product-store">
                                    <?= e($product['store_name']) ?>
                                </p>

                                <div class="product-bottom">

                                    <strong>
                                        Rp <?= number_format(
                                            (float) $product['price'],
                                            0,
                                            ',',
                                            '.'
                                        ) ?>
                                    </strong>

                                    <span class="product-stock">
                                        Stok: <?= (int) $product['stock'] ?>
                                    </span>

                                </div>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

                <div
                    class="hero-actions"
                    style="justify-content:center; margin-top:32px;"
                >

                    <a
                        href="/GreenCycle/pages/products.php"
                        class="btn-primary"
                    >
                        Lihat Semua Produk →
                    </a>

                </div>

            <?php else: ?>

                <div class="empty-products">

                    <div class="empty-icon">♻</div>

                    <h3>Produk Segera Hadir!</h3>

                    <p>
                        Saat ini belum ada produk aktif yang tersedia.
                        Nantikan produk menarik dari para penjual GreenCycle.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- TENTANG KAMI -->

    <section class="about-section" id="tentang">

        <div class="container about-container">

            <div class="about-content">

                <span class="section-label">
                    ABOUT GREENCYCLE
                </span>

                <h2>
                    Bersama Menciptakan
                    <span>Masa Depan Lebih Hijau.</span>
                </h2>

                <p>
                    GreenCycle merupakan platform jual beli
                    produk daur ulang yang menghubungkan pengrajin
                    dan pelaku UMKM dengan masyarakat.

                    Kami percaya bahwa barang bekas dapat memiliki
                    nilai baru melalui kreativitas dan pemanfaatan
                    yang bertanggung jawab.
                </p>

                <a
                    href="/GreenCycle/pages/products.php"
                    class="btn-light"
                >
                    Temukan Produk →
                </a>

            </div>

            <div class="sdg-container">

                <div class="sdg-card sdg-eight">

                    <strong>8</strong>

                    <h3>
                        Decent Work and Economic Growth
                    </h3>

                    <p>
                        Mendukung peluang usaha dan pertumbuhan
                        ekonomi pelaku produk daur ulang.
                    </p>

                </div>

                <div class="sdg-card sdg-twelve">

                    <strong>12</strong>

                    <h3>
                        Responsible Consumption and Production
                    </h3>

                    <p>
                        Mendorong konsumsi dan produksi
                        yang bertanggung jawab.
                    </p>

                </div>

            </div>

        </div>

    </section>

</main>


<!-- FOOTER -->

<footer class="footer">

    <div class="container footer-container">

        <div class="footer-brand">

            <h2>Green<span>Cycle</span></h2>

            <p>
                Give Waste a Second Life.
                Belanja bijak untuk bumi yang lebih baik.
            </p>

        </div>

        <div class="footer-info">

            <h3>GreenCycle</h3>

            <a href="/GreenCycle/pages/home.php">Beranda</a>

            <a href="/GreenCycle/pages/home.php#kategori">Kategori</a>

            <a href="/GreenCycle/pages/products.php">Produk</a>

            <a href="/GreenCycle/pages/home.php#tentang">Tentang Kami</a>

        </div>

        <div class="footer-info">

            <h3>Komitmen Kami</h3>

            <p>SDG 8 – Pertumbuhan Ekonomi</p>

            <p>SDG 12 – Konsumsi Berkelanjutan</p>

        </div>

    </div>

    <div class="footer-bottom">

        <p>
            © <?= date('Y') ?> GreenCycle. All Rights Reserved.
        </p>

    </div>

</footer>

<script src="/GreenCycle/assets/js/main.js"></script>

</body>
</html>