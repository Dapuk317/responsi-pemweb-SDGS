<?php

require_once __DIR__ . '/config/database.php';

echo "<h2>GreenCycle Database Connection</h2>";

if ($conn->ping()) {

    echo "<p style='color: green;'>
        Koneksi database berhasil!
    </p>";

    echo "<p>Database: " . DB_NAME . "</p>";

    // Menghitung jumlah tabel
    $result = $conn->query("SHOW TABLES");

    echo "<h3>Daftar Tabel Database:</h3>";

    echo "<ul>";

    while ($row = $result->fetch_array()) {
        echo "<li>" .
            htmlspecialchars($row[0]) .
            "</li>";
    }

    echo "</ul>";

} else {

    echo "<p>Koneksi database gagal!</p>";

}

?>
