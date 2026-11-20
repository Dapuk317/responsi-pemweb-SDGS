# GreenCycle

GreenCycle adalah aplikasi marketplace produk daur ulang yang mempertemukan penjual produk ramah lingkungan dengan konsumen. Aplikasi ini dikembangkan menggunakan PHP Native dan MySQL untuk mendukung pemasaran produk hasil pemanfaatan kembali material bekas.

## 1. Latar Belakang dan Tujuan Proyek

### Latar Belakang

Sampah dan limbah dari berbagai aktivitas manusia dapat menimbulkan permasalahan lingkungan apabila tidak dikelola dengan baik. Salah satu upaya yang dapat dilakukan adalah memanfaatkan kembali material bekas menjadi produk yang memiliki nilai guna dan nilai ekonomi.

Namun, produsen atau pelaku usaha produk daur ulang membutuhkan media untuk memperkenalkan dan memasarkan produknya kepada masyarakat. Di sisi lain, konsumen membutuhkan akses untuk menemukan produk yang menggunakan bahan daur ulang.

GreenCycle dikembangkan sebagai marketplace yang mempertemukan penjual dan pembeli produk daur ulang melalui satu platform digital.

### Tujuan

- Menyediakan platform pemasaran produk hasil daur ulang.
- Membantu penjual atau UMKM mengelola toko dan produk secara digital.
- Memudahkan konsumen mencari, memesan, dan memberikan ulasan terhadap produk.
- Mendorong pemanfaatan kembali material bekas melalui kegiatan jual beli produk daur ulang.

## 2. Keterkaitan dengan Sustainable Development Goals (SDGs)

### SDG 8 – Decent Work and Economic Growth

GreenCycle mendukung pertumbuhan ekonomi dengan menyediakan sarana pemasaran digital bagi penjual atau UMKM produk daur ulang.

Bentuk dukungan melalui fitur aplikasi:
- Registrasi akun penjual.
- Pembuatan dan pengelolaan toko.
- Pengelolaan produk, harga, dan stok.
- Pengelolaan pesanan dari pelanggan.
- Fasilitas transaksi jual beli.

### SDG 12 – Responsible Consumption and Production

GreenCycle mendorong konsumsi dan produksi yang lebih bertanggung jawab dengan menyediakan katalog produk yang memanfaatkan material bekas atau bahan daur ulang.

Bentuk dukungan melalui fitur aplikasi:
- Katalog produk daur ulang.
- Informasi bahan atau material produk.
- Pengelompokan produk berdasarkan kategori.
- Akses bagi konsumen untuk menemukan dan membeli produk hasil pemanfaatan kembali material.

> Catatan: keterkaitan tersebut menjelaskan kontribusi konsep dan fitur GreenCycle terhadap SDGs. Dampak nyata seperti pengurangan limbah atau peningkatan pendapatan penjual memerlukan pengukuran tersendiri.

## 3. Fitur dan Modul yang Diimplementasikan

GreenCycle memiliki beberapa modul yang saling terintegrasi:

| No. | Modul | Fitur |
|---|---|---|
| 1 | Autentikasi | Registrasi, login, logout, dan pengelolaan sesi pengguna. |
| 2 | Manajemen Pengguna | Pengelolaan profil, role, dan status akun pengguna. |
| 3 | Manajemen Toko | Pembuatan dan pengaturan informasi toko oleh seller. |
| 4 | Manajemen Produk | Menambah, melihat, mengubah, dan menghapus produk serta mengelola stok dan foto produk. |
| 5 | Manajemen Kategori | Menambah, melihat, mengubah, dan menghapus kategori produk oleh admin. |
| 6 | Katalog Produk | Menampilkan produk, pencarian, kategori, dan detail produk. |
| 7 | Keranjang Belanja | Menambahkan produk ke keranjang, mengubah kuantitas, dan menghapus produk. |
| 8 | Pemesanan dan Checkout | Mengisi informasi pengiriman dan membuat pesanan dari keranjang. |
| 9 | Pembayaran | Memilih metode pembayaran, mengunggah bukti pembayaran, dan memeriksa status pembayaran. |
| 10 | Manajemen Pesanan | Melihat pesanan dan memperbarui status pesanan sesuai hak akses. |
| 11 | Ulasan Produk | Memberikan rating dan komentar pada produk yang telah dibeli dan diselesaikan. |
| 12 | Dashboard | Menyediakan dashboard berbeda untuk admin, seller, dan customer. |

### Hak Akses Pengguna

- **Admin:** mengelola pengguna, kategori, toko, pesanan, dan verifikasi transaksi.
- **Seller:** mengelola toko, produk, stok, serta pesanan yang masuk ke tokonya.
- **Customer:** melihat katalog, mengelola keranjang, melakukan checkout, mengelola pembayaran, melihat riwayat pesanan, dan memberikan ulasan.

## 4. Teknologi yang Digunakan

| Teknologi | Kegunaan |
|---|---|
| PHP Native | Logika aplikasi dan pemrosesan sisi server. |
| MySQL / MariaDB | Penyimpanan dan pengelolaan data. |
| MySQLi | Koneksi dan komunikasi PHP dengan database. |
| HTML5 | Struktur halaman aplikasi. |
| CSS3 | Tampilan dan tata letak aplikasi. |
| JavaScript | Interaksi pada sisi pengguna. |
| Apache | Web server lokal melalui XAMPP. |
| Git dan GitHub | Version control dan penyimpanan source code proyek. |

## 5. Instalasi dan Konfigurasi

### Persyaratan

- XAMPP atau lingkungan server lokal sejenis.
- PHP 8.0 atau lebih baru.
- MySQL atau MariaDB.
- Browser web.
- Ekstensi PHP `mysqli`, `fileinfo`, dan `mbstring` aktif.

### Langkah Instalasi

1. Unduh atau clone repository GreenCycle.
2. Letakkan folder proyek bernama `GreenCycle` di dalam direktori `htdocs` XAMPP.

   Contoh lokasi:

   ```text
   C:\xampp\htdocs\GreenCycle
   ```

3. Jalankan Apache dan MySQL melalui XAMPP Control Panel.
4. Buka phpMyAdmin melalui `http://localhost/phpmyadmin`.
5. Buat database dengan nama:

   ```sql
   greencycle
   ```

6. Pilih database `greencycle`, lalu import file `greencycle.sql` yang tersedia di folder proyek.
7. Periksa konfigurasi database pada file `config/database.php`.

   Konfigurasi bawaan:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'greencycle');
   ```

8. Sesuaikan `DB_USER` dan `DB_PASS` jika konfigurasi MySQL lokal berbeda.
9. Pastikan folder `uploads` dapat ditulisi oleh web server karena digunakan untuk menyimpan foto produk, logo toko, dan bukti pembayaran.

## 6. Panduan Menjalankan Aplikasi

1. Pastikan Apache dan MySQL dalam keadaan aktif.
2. Pastikan database `greencycle` sudah berhasil di-import.
3. Buka browser dan akses:

   ```text
   http://localhost/GreenCycle/
   ```

4. Pengguna yang belum memiliki akun dapat memilih menu registrasi.
5. Pilih jenis akun customer atau seller saat registrasi.
6. Seller dapat mengatur toko dan menambahkan produk setelah berhasil masuk.
7. Customer dapat melihat katalog, memasukkan produk ke keranjang, dan melakukan checkout.
8. Customer dapat mengunggah bukti pembayaran melalui halaman pembayaran.
9. Admin dapat memverifikasi pembayaran dan mengelola data melalui dashboard admin.

### Menyiapkan Akun Admin

Form registrasi publik menyediakan role customer dan seller. Untuk membuat akun admin pada instalasi lokal:

1. Daftarkan akun terlebih dahulu melalui halaman registrasi.
2. Buka phpMyAdmin dan jalankan query berikut dengan alamat email akun yang telah didaftarkan:

   ```sql
   UPDATE users
   SET role = 'admin'
   WHERE email = 'email-admin@example.com';
   ```

3. Login kembali menggunakan akun tersebut agar diarahkan ke dashboard admin.

### Catatan Penggunaan

- Jangan membagikan password atau kredensial database ke repository publik.
- Ubah konfigurasi database sesuai lingkungan masing-masing.
- File `test_connection.php` hanya digunakan untuk pemeriksaan koneksi database.
- Ongkos kirim pada implementasi saat ini masih menggunakan nilai sementara Rp0.
- Pembayaran pada aplikasi menggunakan alur unggah bukti dan verifikasi admin, bukan integrasi payment gateway otomatis.

---

## Struktur Direktori

```text
GreenCycle/
├── admin/
│   ├── categories/
│   ├── orders/
│   ├── stores/
│   ├── transactions/
│   ├── users/
│   └── dashboard.php
├── assets/
│   ├── css/
│   ├── images/
│   └── js/
├── auth/
│   ├── login.php
│   ├── logout.php
│   └── register.php
├── config/
│   └── database.php
├── customer/
├── includes/
├── pages/
├── seller/
├── uploads/
├── greencycle.sql
└── index.php
```