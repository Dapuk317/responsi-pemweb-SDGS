<?php
/*
|--------------------------------------------------------------------------
| DATABASE CONFIGURATION
|--------------------------------------------------------------------------
| Project : GreenCycle
| Database: greencycle
| Driver  : MySQLi
|--------------------------------------------------------------------------
*/

// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'greencycle');

// Mengaktifkan error reporting MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    // Membuat koneksi database
    $conn = new mysqli(
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME
    );

    // Mengatur charset database
    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {

    // Pesan umum untuk pengguna
    error_log($e->getMessage());

    die("Koneksi database gagal. Silakan periksa konfigurasi database.");
}
?>
