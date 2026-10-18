<?php
// src/setup_users.php
// Jalankan file ini satu kali di browser (http://localhost/.../src/setup_users.php)
// untuk memastikan akun Bendahara & Ketua UKM memiliki password hash Bcrypt yang valid.
require_once __DIR__ . '/config/database.php';

$password_default = 'PasswordUMC#2026';
$hash = password_hash($password_default, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, failed_attempts = 0, locked_until = NULL WHERE username IN ('bendahara', 'ketua')");
$stmt->execute(['hash' => $hash]);

echo "<h3>Setup Akun Berhasil!</h3>";
echo "<p>Password untuk akun <b>bendahara</b> dan <b>ketua</b> telah disetel ke: <code>" . htmlspecialchars($password_default) . "</code></p>";
echo "<p>Demi keamanan, segera <b>hapus file setup_users.php</b> ini setelah dijalankan.</p>";
echo "<a href='login.php'>Ke Halaman Login</a>";
