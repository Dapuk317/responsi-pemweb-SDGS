
-- ==========================================
-- DATABASE: GREENCYCLE
-- Marketplace Produk Daur Ulang
-- SDG 8 & SDG 12
-- ==========================================

CREATE DATABASE IF NOT EXISTS greencycle
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE greencycle;

-- ==========================================
-- 1. TABEL USERS
-- Menyimpan akun admin, seller, customer
-- ==========================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    profile_photo VARCHAR(255),
    role ENUM('admin', 'seller', 'customer')
        NOT NULL DEFAULT 'customer',
    status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==========================================
-- 2. TABEL STORES
-- Menyimpan informasi toko penjual
-- ==========================================

CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    store_name VARCHAR(150) NOT NULL,
    description TEXT,
    address TEXT,
    phone VARCHAR(20),
    logo VARCHAR(255),
    status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_stores_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 3. TABEL CATEGORIES
-- Menyimpan kategori produk
-- ==========================================

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==========================================
-- 4. TABEL PRODUCTS
-- Menyimpan produk daur ulang
-- ==========================================

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    category_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    description TEXT,
    material_info TEXT,
    price DECIMAL(12,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    product_photo VARCHAR(255),
    status ENUM('active', 'inactive')
        NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_products_store
        FOREIGN KEY (store_id)
        REFERENCES stores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id)
        REFERENCES categories(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CHECK (price >= 0),
    CHECK (stock >= 0),

    INDEX idx_product_name (product_name),
    INDEX idx_product_category (category_id),
    INDEX idx_product_status (status)
) ENGINE=InnoDB;

-- ==========================================
-- 5. TABEL CARTS
-- Keranjang masing-masing pembeli
-- ==========================================

CREATE TABLE carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_carts_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 6. TABEL CART_ITEMS
-- Detail produk dalam keranjang
-- ==========================================

CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_cart_items_cart
        FOREIGN KEY (cart_id)
        REFERENCES carts(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_cart_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY unique_cart_product (cart_id, product_id),
    CHECK (quantity > 0)
) ENGINE=InnoDB;

-- ==========================================
-- 7. TABEL ORDERS
-- Menyimpan data pemesanan
-- ==========================================

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(50) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    store_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    shipping_address TEXT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,

    status ENUM(
        'pending',
        'processing',
        'shipped',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',

    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_orders_store
        FOREIGN KEY (store_id)
        REFERENCES stores(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CHECK (subtotal >= 0),
    CHECK (shipping_cost >= 0),
    CHECK (total_amount >= 0),

    INDEX idx_order_user (user_id),
    INDEX idx_order_store (store_id),
    INDEX idx_order_status (status)
) ENGINE=InnoDB;

-- ==========================================
-- 8. TABEL ORDER_ITEMS
-- Detail barang dalam setiap pesanan
-- ==========================================

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL,
    product_name VARCHAR(150) NOT NULL,
    product_photo VARCHAR(255),
    price DECIMAL(12,2) NOT NULL,
    quantity INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CHECK (price >= 0),
    CHECK (quantity > 0),
    CHECK (subtotal >= 0),

    INDEX idx_order_items_product (product_id)
) ENGINE=InnoDB;

-- ==========================================
-- 9. TABEL TRANSACTIONS
-- Menyimpan informasi pembayaran
-- ==========================================

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,

    payment_method ENUM(
        'bank_transfer',
        'cash_on_delivery'
    ) NOT NULL DEFAULT 'bank_transfer',

    payment_status ENUM(
        'pending',
        'verified',
        'rejected'
    ) NOT NULL DEFAULT 'pending',

    payment_proof VARCHAR(255),
    payment_date DATETIME NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_transactions_order
        FOREIGN KEY (order_id)
        REFERENCES orders(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ==========================================
-- 10. TABEL REVIEWS
-- Ulasan produk dari pembeli
-- ==========================================

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    order_item_id INT NOT NULL UNIQUE,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_reviews_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_reviews_product
        FOREIGN KEY (product_id)
        REFERENCES products(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_reviews_order_item
        FOREIGN KEY (order_item_id)
        REFERENCES order_items(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ==========================================
-- SELESAI MEMBUAT 10 TABEL
-- ==========================================


-- ==========================================
-- DATA AWAL KATEGORI PRODUK
-- ==========================================

INSERT INTO categories (category_name, description)
VALUES
(
    'Fashion Daur Ulang',
    'Produk fashion yang dibuat menggunakan bahan daur ulang.'
),
(
    'Kerajinan Tangan',
    'Produk kerajinan kreatif dari bahan bekas yang dimanfaatkan kembali.'
),
(
    'Dekorasi Rumah',
    'Produk dekorasi rumah yang berasal dari material daur ulang.'
),
(
    'Produk Kertas',
    'Produk berbahan kertas daur ulang seperti buku dan alat tulis.'
),
(
    'Aksesori',
    'Berbagai aksesori yang dibuat dari bahan bekas atau material daur ulang.'
),
(
    'Peralatan Rumah Tangga',
    'Peralatan rumah tangga hasil pemanfaatan kembali material bekas.'
);