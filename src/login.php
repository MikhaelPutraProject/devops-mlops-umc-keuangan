<?php
// src/login.php
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/database.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan (CSRF) tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = 'Format username tidak valid.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, nama_lengkap, password_hash, role, failed_attempts, locked_until FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                // Cek apakah akun sedang terkunci (Brute-Force Protection)
                if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                    $sisa_menit = ceil((strtotime($user['locked_until']) - time()) / 60);
                    $error = "Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$sisa_menit} menit.";
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Login berhasil -> Reset failed_attempts & Regenerate Session ID
                    $resetStmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id");
                    $resetStmt->execute(['id' => $user['id']]);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();

                    header("Location: index.php");
                    exit;
                } else {
                    // Password salah -> Tambah hitungan gagal
                    $attempts = (int)$user['failed_attempts'] + 1;
                    $locked_until = ($attempts >= 5) ? date('Y-m-d H:i:s', time() + (15 * 60)) : null;

                    $failStmt = $pdo->prepare("UPDATE users SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id");
                    $failStmt->execute([
                        'attempts' => $attempts,
                        'locked'   => $locked_until,
                        'id'       => $user['id']
                    ]);

                    $error = ($attempts >= 5)
                        ? 'Percobaan gagal mencapai batas maksimal (5x). Akun dikunci selama 15 menit.'
                        : 'Username atau password yang Anda masukkan salah.';
                }
            } else {
                $error = 'Username atau password yang Anda masukkan salah.';
            }
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Keuangan UMC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold text-primary mb-1">UMC Keuangan</h4>
                        <p class="text-muted small mb-0">Unisbank Media Center</p>
                    </div>

                    <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'timeout'): ?>
                        <div class="alert alert-warning py-2 small">Sesi Anda telah berakhir karena tidak aktif. Silakan login kembali.</div>
                    <?php endif; ?>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="login.php" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" required maxlength="30" placeholder="bendahara / ketua">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" required placeholder="Masukkan password">
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">Masuk ke Sistem</button>
                    </form>
                </div>
                <div class="card-footer bg-white text-center py-2 border-0">
                    <small class="text-muted">Akses terbatas: Ketua UKM & Bendahara UMC</small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
