<?php
// src/index.php
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/database.php';

// Hanya mengizinkan role 'bendahara' dan 'ketua'
require_role(['bendahara', 'ketua']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$pesan_sukses = '';
$pesan_error = '';

// Hanya Bendahara yang diizinkan menginput atau menghapus data transaksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['bendahara']);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $pesan_error = 'Validasi keamanan CSRF gagal. Silakan coba lagi.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        // 1. Input Pembayaran Uang Kas Anggota
        if ($aksi === 'tambah_kas') {
            $nim = trim($_POST['nim_anggota'] ?? '');
            $nama = trim($_POST['nama_anggota'] ?? '');
            $bulan = trim($_POST['bulan_tagihan'] ?? '');
            $nominal = filter_var($_POST['nominal'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tanggal = trim($_POST['tanggal_bayar'] ?? '');
            $ket = trim($_POST['keterangan'] ?? '-');

            if (!preg_match('/^[0-9.]{8,20}$/', $nim)) {
                $pesan_error = 'Format NIM tidak valid (hanya angka dan titik).';
            } elseif ($nama === '' || strlen($nama) > 100) {
                $pesan_error = 'Nama anggota wajib diisi (maks 100 karakter).';
            } elseif ($nominal === false || $nominal <= 0) {
                $pesan_error = 'Nominal uang kas harus lebih besar dari Rp 0.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                $pesan_error = 'Format tanggal bayar tidak valid.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO uang_kas (nim_anggota, nama_anggota, bulan_tagihan, nominal, tanggal_bayar, keterangan, created_by) VALUES (:nim, :nama, :bulan, :nominal, :tgl, :ket, :uid)");
                $stmt->execute([
                    'nim'     => $nim,
                    'nama'    => $nama,
                    'bulan'   => $bulan,
                    'nominal' => $nominal,
                    'tgl'     => $tanggal,
                    'ket'     => $ket,
                    'uid'     => $user_id
                ]);
                $pesan_sukses = 'Pembayaran uang kas anggota berhasil dicatat.';
            }
        }

        // 2. Input Transaksi Umum (Uang Masuk / Uang Keluar UMC)
        elseif ($aksi === 'tambah_transaksi') {
            $jenis = $_POST['jenis'] ?? '';
            $kategori = trim($_POST['kategori'] ?? '');
            $nominal = filter_var($_POST['nominal'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tanggal = trim($_POST['tanggal'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if (!in_array($jenis, ['masuk', 'keluar'], true)) {
                $pesan_error = 'Jenis transaksi tidak valid.';
            } elseif ($kategori === '' || $deskripsi === '') {
                $pesan_error = 'Kategori dan deskripsi wajib diisi.';
            } elseif ($nominal === false || $nominal <= 0) {
                $pesan_error = 'Nominal transaksi harus lebih dari Rp 0.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                $pesan_error = 'Format tanggal transaksi tidak valid.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO transaksi_umum (jenis, kategori, nominal, tanggal, deskripsi, created_by) VALUES (:jenis, :kat, :nominal, :tgl, :desk, :uid)");
                $stmt->execute([
                    'jenis'   => $jenis,
                    'kat'     => $kategori,
                    'nominal' => $nominal,
                    'tgl'     => $tanggal,
                    'desk'    => $deskripsi,
                    'uid'     => $user_id
                ]);
                $pesan_sukses = 'Transaksi keuangan UMC berhasil disimpan.';
            }
        }
    }
}

// Hitung Ringkasan Saldo Keuangan UMC
$total_kas = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM uang_kas")->fetchColumn();
$total_masuk = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM transaksi_umum WHERE jenis = 'masuk'")->fetchColumn();
$total_keluar = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM transaksi_umum WHERE jenis = 'keluar'")->fetchColumn();
$saldo_akhir = ($total_kas + $total_masuk) - $total_keluar;

// Ambil Data Riwayat Uang Kas & Transaksi
$daftar_kas = $pdo->query("SELECT * FROM uang_kas ORDER BY tanggal_bayar DESC, id DESC LIMIT 20")->fetchAll();
$daftar_transaksi = $pdo->query("SELECT * FROM transaksi_umum ORDER BY tanggal DESC, id DESC LIMIT 20")->fetchAll();

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan UMC - Unisbank Media Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">UMC Keuangan</a>
        <div class="d-flex align-items-center text-white">
            <span class="me-3 small">
                Halo, <strong><?= e($_SESSION['nama_lengkap']) ?></strong>
                <span class="badge bg-light text-primary ms-1 text-uppercase"><?= e($role) ?></span>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-light">Keluar</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($pesan_sukses !== ''): ?>
        <div class="alert alert-success"><?= e($pesan_sukses) ?></div>
    <?php endif; ?>
    <?php if ($pesan_error !== ''): ?>
        <div class="alert alert-danger"><?= e($pesan_error) ?></div>
    <?php endif; ?>

    <!-- Ringkasan Kartu Saldo -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Total Uang Kas Anggota</small>
                    <h5 class="fw-bold text-primary mt-1 mb-0">Rp <?= number_format($total_kas, 0, ',', '.') ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Total Uang Masuk Lainnya</small>
                    <h5 class="fw-bold text-success mt-1 mb-0">Rp <?= number_format($total_masuk, 0, ',', '.') ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted">Total Uang Keluar</small>
                    <h5 class="fw-bold text-danger mt-1 mb-0">Rp <?= number_format($total_keluar, 0, ',', '.') ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <small class="text-white-50">Saldo Akhir UMC</small>
                    <h5 class="fw-bold mt-1 mb-0">Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Input Khusus Bendahara -->
    <?php if ($role === 'bendahara'): ?>
    <div class="row g-4 mb-4">
        <!-- Form Uang Kas Anggota -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold text-primary">Input Pembayaran Uang Kas Anggota</div>
                <div class="card-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="aksi" value="tambah_kas">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small">NIM Anggota</label>
                                <input type="text" name="nim_anggota" class="form-control form-control-sm" placeholder="23.01.53.xxxx" required>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small">Nama Anggota</label>
                                <input type="text" name="nama_anggota" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Bulan Kas</label>
                                <select name="bulan_tagihan" class="form-select form-select-sm" required>
                                    <option value="Januari">Januari</option>
                                    <option value="Februari">Februari</option>
                                    <option value="Maret">Maret</option>
                                    <option value="April">April</option>
                                    <option value="Mei">Mei</option>
                                    <option value="Juni">Juni</option>
                                    <option value="Juli">Juli</option>
                                    <option value="Agustus">Agustus</option>
                                    <option value="September">September</option>
                                    <option value="Oktober">Oktober</option>
                                    <option value="November">November</option>
                                    <option value="Desember">Desember</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Nominal (Rp)</label>
                                <input type="number" name="nominal" class="form-control form-control-sm" min="1000" step="500" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Tanggal Bayar</label>
                                <input type="date" name="tanggal_bayar" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Keterangan</label>
                                <input type="text" name="keterangan" class="form-control form-control-sm" placeholder="Lunas / Transfer / Tunai">
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-sm btn-primary w-100">Simpan Uang Kas</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Form Uang Masuk & Keluar Umum -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold text-success">Input Arus Kas (Uang Masuk / Uang Keluar)</div>
                <div class="card-body">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="aksi" value="tambah_transaksi">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small">Jenis Transaksi</label>
                                <select name="jenis" class="form-select form-select-sm" required>
                                    <option value="masuk">Uang Masuk (+)</option>
                                    <option value="keluar">Uang Keluar (-)</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small">Kategori</label>
                                <input type="text" name="kategori" class="form-control form-control-sm" placeholder="Dana Kampus / Sewa Alat / Konsumsi" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Nominal (Rp)</label>
                                <input type="number" name="nominal" class="form-control form-control-sm" min="1000" step="500" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Tanggal Transaksi</label>
                                <input type="date" name="tanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Deskripsi Lengkap</label>
                                <input type="text" name="deskripsi" class="form-control form-control-sm" placeholder="Contoh: Pembelian baterai kamera & memori SD Card" required>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-sm btn-success w-100">Simpan Transaksi</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info py-2 small mb-4">
        <strong>Mode Ketua UKM (Monitoring & Audit):</strong> Anda memiliki akses penuh untuk memantau seluruh laporan uang kas dan transaksi keluar/masuk. Penginputan transaksi baru dikelola oleh Bendahara.
    </div>
    <?php endif; ?>

    <!-- Tabel Riwayat Data -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Riwayat Pembayaran Uang Kas Anggota</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Anggota</th>
                                <th>Bulan</th>
                                <th class="text-end">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_kas)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data uang kas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daftar_kas as $kas): ?>
                                <tr>
                                    <td class="small"><?= e($kas['tanggal_bayar']) ?></td>
                                    <td>
                                        <div class="fw-semibold small"><?= e($kas['nama_anggota']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($kas['nim_anggota']) ?></div>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= e($kas['bulan_tagihan']) ?></span></td>
                                    <td class="text-end fw-semibold text-primary">Rp <?= number_format((float)$kas['nominal'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">Riwayat Uang Masuk & Keluar UMC</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Kategori & Deskripsi</th>
                                <th class="text-end">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_transaksi)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada transaksi umum.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daftar_transaksi as $trx): ?>
                                <tr>
                                    <td class="small"><?= e($trx['tanggal']) ?></td>
                                    <td>
                                        <?php if ($trx['jenis'] === 'masuk'): ?>
                                            <span class="badge bg-success">Masuk</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Keluar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small"><?= e($trx['kategori']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= e($trx['deskripsi']) ?></div>
                                    </td>
                                    <td class="text-end fw-semibold <?= $trx['jenis'] === 'masuk' ? 'text-success' : 'text-danger' ?>">
                                        <?= $trx['jenis'] === 'masuk' ? '+' : '-' ?> Rp <?= number_format((float)$trx['nominal'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
