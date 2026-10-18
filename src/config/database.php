<?php
// src/config/database.php
// Koneksi Database Menggunakan PDO dengan Prepared Statements Ketat

$db_host = 'localhost';
$db_name = 'db_umc_keuangan';
$db_user = 'root';
$db_pass = ''; // Sesuaikan dengan password database lokal Anda

$dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Wajib false agar menggunakan native prepared statement (Anti SQL Injection)
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Terjadi gangguan koneksi ke server database. Silakan hubungi administrator.");
}
