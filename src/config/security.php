<?php
// src/config/security.php
// Konfigurasi Keamanan Session & Fungsi Proteksi Input/Output

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Header Keamanan HTTP
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com;");

// Generate CSRF Token
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validasi CSRF Token
function verify_csrf_token(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitasi Output (Anti-XSS)
function e($string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

// Cek Autentikasi Login & Session Timeout (30 Menit)
function require_login(): void {
    $timeout = 1800; // 30 menit
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        header("Location: login.php?pesan=timeout");
        exit;
    }
    $_SESSION['last_activity'] = time();

    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header("Location: login.php");
        exit;
    }
}

// Cek Otorisasi Role
function require_role(array $allowed_roles): void {
    require_login();
    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        http_response_code(403);
        die("<h3>403 Forbidden - Akses Ditolak</h3><p>Anda tidak memiliki izin untuk melakukan aksi ini.</p><a href='index.php'>Kembali ke Dashboard</a>");
    }
}