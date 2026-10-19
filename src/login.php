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
                if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
                    $sisa_menit = ceil((strtotime($user['locked_until']) - time()) / 60);
                    $error = "Akun dikunci sementara karena 5x gagal login. Coba lagi dalam {$sisa_menit} menit.";
                } elseif (password_verify($password, $user['password_hash'])) {
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
                    $attempts = (int)$user['failed_attempts'] + 1;
                    $locked_until = ($attempts >= 5) ? date('Y-m-d H:i:s', time() + (15 * 60)) : null;

                    $failStmt = $pdo->prepare("UPDATE users SET failed_attempts = :attempts, locked_until = :locked WHERE id = :id");
                    $failStmt->execute([
                        'attempts' => $attempts,
                        'locked'   => $locked_until,
                        'id'       => $user['id']
                    ]);

                    $error = ($attempts >= 5)
                        ? 'Percobaan gagal mencapai batas maksimal (5x). Akun dikunci 15 menit.'
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
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — UMC Finance Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #090d16;
            background-image: 
                radial-gradient(at 15% 15%, rgba(99, 102, 241, 0.28) 0px, transparent 50%),
                radial-gradient(at 85% 85%, rgba(236, 72, 153, 0.22) 0px, transparent 50%);
            min-height: 100vh;
            color: #f8fafc;
        }
        .glass-card {
            background: rgba(17, 24, 39, 0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }
        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #a5b4fc;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .text-gradient {
            background: linear-gradient(135deg, #818cf8 0%, #c084fc 50%, #f472b6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-control {
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 12px 16px;
            color: #fff;
        }
        .form-control:focus {
            background: rgba(15, 23, 42, 0.95);
            border-color: #818cf8;
            box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.2);
            color: #fff;
        }
        .btn-neon {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            border: none;
            border-radius: 14px;
            padding: 13px;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.5);
        }
        .btn-neon:hover {
            transform: translateY(-2px);
            color: #fff;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
    <div class="col-12 col-sm-10 col-md-7 col-lg-4" style="max-width: 430px;">
        <div class="glass-card p-4 p-sm-5">
            <div class="text-center mb-4">
                <div class="brand-pill mb-3">
                    <i class="bi bi-shield-lock-fill"></i> SECURE WORKSPACE
                </div>
                <h2 class="fw-extrabold mb-1" style="font-weight: 800;">
                    UMC <span class="text-gradient">Finance.</span>
                </h2>
                <p class="text-secondary small mb-0">Unisbank Media Center Cashflow Portal</p>
            </div>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'timeout'): ?>
                <div class="alert alert-warning border-0 rounded-4 py-2 small mb-3">Sesi berakhir. Silakan login ulang.</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger border-0 rounded-4 py-2 small mb-3"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-secondary" style="border-radius: 14px 0 0 14px;">
                            <i class="bi bi-person-badge"></i>
                        </span>
                        <input type="text" name="username" class="form-control" style="border-radius: 0 14px 14px 0;" required placeholder="bendahara / ketua">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold text-secondary">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-secondary" style="border-radius: 14px 0 0 14px;">
                            <i class="bi bi-key"></i>
                        </span>
                        <input type="password" name="password" id="passwordInput" class="form-control border-end-0" required placeholder="••••••••">
                        <button class="btn btn-outline-secondary border-start-0 border-secondary border-opacity-25 text-secondary" type="button" id="togglePassword" style="border-radius: 0 14px 14px 0;">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-neon w-100">
                    Masuk ke Dashboard <i class="bi bi-arrow-right-short fs-5 align-middle"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Fitur Ikon Mata Show/Hide Password
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.classList.toggle('bi-eye');
            eyeIcon.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>