<?php
// src/index.php
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/database.php';

require_role(['bendahara', 'ketua']);

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$pesan_sukses = '';
$pesan_error = '';

// 1. FITUR EXPORT EXCEL / CSV (Bisa untuk Bendahara & Ketua)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Keuangan_UMC_' . date('Y-m-d_His') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM agar rapi di Microsoft Excel

    fputcsv($output, ['LAPORAN KEUANGAN UMC (UNISBANK MEDIA CENTER)']);
    fputcsv($output, ['Tanggal Unduh:', date('d-m-Y H:i:s')]);
    fputcsv($output, []);

    fputcsv($output, ['=== A. DATA PEMBAYARAN UANG KAS ANGGOTA ===']);
    fputcsv($output, ['No', 'Tanggal Bayar', 'NIM Anggota', 'Nama Anggota', 'Bulan Tagihan', 'Nominal (Rp)', 'Keterangan']);
    $stmtKasExp = $pdo->query("SELECT * FROM uang_kas ORDER BY tanggal_bayar DESC, id DESC");
    $no = 1;
    while ($r = $stmtKasExp->fetch()) {
        fputcsv($output, [$no++, $r['tanggal_bayar'], $r['nim_anggota'], $r['nama_anggota'], $r['bulan_tagihan'], $r['nominal'], $r['keterangan']]);
    }

    fputcsv($output, []);
    fputcsv($output, ['=== B. DATA ARUS KAS (UANG MASUK & UANG KELUAR) ===']);
    fputcsv($output, ['No', 'Tanggal Transaksi', 'Jenis Arus', 'Kategori', 'Nominal (Rp)', 'Deskripsi Lengkap']);
    $stmtTrxExp = $pdo->query("SELECT * FROM transaksi_umum ORDER BY tanggal DESC, id DESC");
    $no2 = 1;
    while ($r = $stmtTrxExp->fetch()) {
        fputcsv($output, [$no2++, $r['tanggal'], strtoupper($r['jenis']), $r['kategori'], $r['nominal'], $r['deskripsi']]);
    }
    fclose($output);
    exit;
}

// 2. FITUR BACKUP DATABASE SQL (Khusus Bendahara)
if (isset($_GET['backup']) && $_GET['backup'] === 'sql' && $role === 'bendahara') {
    $tables = ['users', 'uang_kas', 'transaksi_umum'];
    $sqlScript = "-- BACKUP DATABASE SISTEM KEUANGAN UMC\n-- Digenerate pada: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        $query = $pdo->query("SELECT * FROM `$table`");
        $numFields = $query->columnCount();
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        $createRow = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sqlScript .= $createRow[1] . ";\n\n";

        while ($row = $query->fetch(PDO::FETCH_NUM)) {
            $sqlScript .= "INSERT INTO `$table` VALUES(";
            $values = [];
            for ($j = 0; $j < $numFields; $j++) {
                if ($row[$j] === null) {
                    $values[] = "NULL";
                } else {
                    $escaped = addslashes((string)$row[$j]);
                    $escaped = str_replace("\n", "\\n", $escaped);
                    $values[] = '"' . $escaped . '"';
                }
            }
            $sqlScript .= implode(',', $values) . ");\n";
        }
        $sqlScript .= "\n";
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename=Backup_DB_UMC_' . date('Y-m-d_His') . '.sql');
    echo $sqlScript;
    exit;
}

// 3. PROSES TAMBAH, EDIT, & HAPUS DATA (Khusus Bendahara)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['bendahara']);

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $pesan_error = 'Token keamanan CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $aksi = $_POST['aksi'] ?? '';

        // A. Tambah Uang Kas Anggota
        if ($aksi === 'tambah_kas') {
            $nim = trim($_POST['nim_anggota'] ?? '');
            $nama = trim($_POST['nama_anggota'] ?? '');
            $bulan = trim($_POST['bulan_tagihan'] ?? '');
            $nominal = filter_var($_POST['nominal'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tanggal = trim($_POST['tanggal_bayar'] ?? '');
            $ket = trim($_POST['keterangan'] ?? '-');

            if (!preg_match('/^[0-9.]{8,25}$/', $nim)) {
                $pesan_error = 'Format NIM tidak valid (gunakan angka dan titik).';
            } elseif ($nama === '' || $nominal === false || $nominal <= 0) {
                $pesan_error = 'Nama anggota dan nominal kas wajib diisi dengan benar.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO uang_kas (nim_anggota, nama_anggota, bulan_tagihan, nominal, tanggal_bayar, keterangan, created_by) VALUES (:nim, :nama, :bulan, :nominal, :tgl, :ket, :uid)");
                $stmt->execute([
                    'nim' => $nim, 'nama' => $nama, 'bulan' => $bulan,
                    'nominal' => $nominal, 'tgl' => $tanggal, 'ket' => ($ket ?: 'Lunas'), 'uid' => $user_id
                ]);
                $pesan_sukses = 'Data pembayaran uang kas anggota berhasil ditambahkan!';
            }
        }

        // B. Edit Uang Kas Anggota
        elseif ($aksi === 'edit_kas') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $nim = trim($_POST['nim_anggota'] ?? '');
            $nama = trim($_POST['nama_anggota'] ?? '');
            $bulan = trim($_POST['bulan_tagihan'] ?? '');
            $nominal = filter_var($_POST['nominal'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tanggal = trim($_POST['tanggal_bayar'] ?? '');
            $ket = trim($_POST['keterangan'] ?? '-');

            if (!$id || !preg_match('/^[0-9.]{8,25}$/', $nim) || $nama === '' || $nominal <= 0) {
                $pesan_error = 'Data perubahan uang kas tidak valid.';
            } else {
                $stmt = $pdo->prepare("UPDATE uang_kas SET nim_anggota = :nim, nama_anggota = :nama, bulan_tagihan = :bulan, nominal = :nominal, tanggal_bayar = :tgl, keterangan = :ket WHERE id = :id");
                $stmt->execute([
                    'nim' => $nim, 'nama' => $nama, 'bulan' => $bulan,
                    'nominal' => $nominal, 'tgl' => $tanggal, 'ket' => $ket, 'id' => $id
                ]);
                $pesan_sukses = 'Data uang kas anggota berhasil diperbarui!';
            }
        }

        // C. Tambah Transaksi Arus Kas Umum
        elseif ($aksi === 'tambah_transaksi') {
            $jenis = $_POST['jenis'] ?? '';
            $kategori = trim($_POST['kategori'] ?? '');
            $nominal = filter_var($_POST['nominal'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tanggal = trim($_POST['tanggal'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if (!in_array($jenis, ['masuk', 'keluar'], true) || $kategori === '' || $nominal <= 0) {
                $pesan_error = 'Data transaksi arus kas tidak lengkap atau tidak valid.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO transaksi_umum (jenis, kategori, nominal, tanggal, deskripsi, created_by) VALUES (:jenis, :kat, :nominal, :tgl, :desk, :uid)");
                $stmt->execute([
                    'jenis' => $jenis, 'kat' => $kategori, 'nominal' => $nominal,
                    'tgl' => $tanggal, 'desk' => $deskripsi, 'uid' => $user_id
                ]);
                $pesan_sukses = 'Transaksi arus kas UMC berhasil disimpan!';
            }
        }

        // D. Edit Transaksi Arus Kas Umum
        elseif ($aksi === 'edit_transaksi') {
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $jenis = $_POST['jenis'] ?? '';
            $kategori = trim($_POST['kategori'] ?? '');
            $nominal = filter_var($_POST['nominal'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tanggal = trim($_POST['tanggal'] ?? '');
            $deskripsi = trim($_POST['deskripsi'] ?? '');

            if (!$id || !in_array($jenis, ['masuk', 'keluar'], true) || $kategori === '' || $nominal <= 0) {
                $pesan_error = 'Data perubahan transaksi tidak valid.';
            } else {
                $stmt = $pdo->prepare("UPDATE transaksi_umum SET jenis = :jenis, kategori = :kat, nominal = :nominal, tanggal = :tgl, deskripsi = :desk WHERE id = :id");
                $stmt->execute([
                    'jenis' => $jenis, 'kat' => $kategori, 'nominal' => $nominal,
                    'tgl' => $tanggal, 'desk' => $deskripsi, 'id' => $id
                ]);
                $pesan_sukses = 'Data transaksi arus kas berhasil diperbarui!';
            }
        }

        // E. Hapus Data (Kas / Transaksi)
        elseif ($aksi === 'hapus_data') {
            $tipe = $_POST['tipe'] ?? '';
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            if ($id && $tipe === 'kas') {
                $stmt = $pdo->prepare("DELETE FROM uang_kas WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $pesan_sukses = 'Data uang kas anggota berhasil dihapus secara permanen.';
            } elseif ($id && $tipe === 'transaksi') {
                $stmt = $pdo->prepare("DELETE FROM transaksi_umum WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $pesan_sukses = 'Data transaksi umum berhasil dihapus secara permanen.';
            }
        }
    }
}

// 4. HITUNG RINGKASAN SALDO UTAMA
$total_kas = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM uang_kas")->fetchColumn();
$total_masuk = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM transaksi_umum WHERE jenis = 'masuk'")->fetchColumn();
$total_keluar = (float) $pdo->query("SELECT COALESCE(SUM(nominal), 0) FROM transaksi_umum WHERE jenis = 'keluar'")->fetchColumn();
$saldo_akhir = ($total_kas + $total_masuk) - $total_keluar;

// 5. PENCARIAN, FILTER, & TAMPILKAN FULL DATA (Bebas Error 500 PDO)
$keyword = trim($_GET['q'] ?? '');
$filter_bulan = trim($_GET['bulan'] ?? '');
$filter_jenis = trim($_GET['jenis'] ?? '');
$limit_view = isset($_GET['limit']) && $_GET['limit'] === 'all' ? 'all' : '50';

// Query Uang Kas (menggunakan parameter unik :q1, :q2, :q3 agar kompatibel penuh dengan PDO)
$sqlKas = "SELECT * FROM uang_kas WHERE (nama_anggota LIKE :q1 OR nim_anggota LIKE :q2 OR keterangan LIKE :q3)";
$paramsKas = [
    'q1' => "%$keyword%",
    'q2' => "%$keyword%",
    'q3' => "%$keyword%"
];
if ($filter_bulan !== '') {
    $sqlKas .= " AND bulan_tagihan = :bulan";
    $paramsKas['bulan'] = $filter_bulan;
}
$sqlKas .= " ORDER BY tanggal_bayar DESC, id DESC";
if ($limit_view !== 'all') {
    $sqlKas .= " LIMIT 50";
}
$stmtKas = $pdo->prepare($sqlKas);
$stmtKas->execute($paramsKas);
$daftar_kas = $stmtKas->fetchAll();

// Query Transaksi Umum (menggunakan parameter unik :t1, :t2)
$sqlTrx = "SELECT * FROM transaksi_umum WHERE (kategori LIKE :t1 OR deskripsi LIKE :t2)";
$paramsTrx = [
    't1' => "%$keyword%",
    't2' => "%$keyword%"
];
if (in_array($filter_jenis, ['masuk', 'keluar'], true)) {
    $sqlTrx .= " AND jenis = :jenis";
    $paramsTrx['jenis'] = $filter_jenis;
}
$sqlTrx .= " ORDER BY tanggal DESC, id DESC";
if ($limit_view !== 'all') {
    $sqlTrx .= " LIMIT 50";
}
$stmtTrx = $pdo->prepare($sqlTrx);
$stmtTrx->execute($paramsTrx);
$daftar_transaksi = $stmtTrx->fetchAll();

$csrf_token = generate_csrf_token();
$list_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UMC Finance Hub — Sistem Manajemen Keuangan Gen Z</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #070b14;
            background-image: 
                radial-gradient(at 10% 10%, rgba(99, 102, 241, 0.22) 0px, transparent 45%),
                radial-gradient(at 90% 15%, rgba(236, 72, 153, 0.16) 0px, transparent 45%),
                radial-gradient(at 50% 90%, rgba(16, 185, 129, 0.12) 0px, transparent 50%);
            min-height: 100vh;
            color: #f1f5f9;
        }
        .navbar-glass {
            background: rgba(15, 23, 42, 0.82);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .text-gradient {
            background: linear-gradient(135deg, #818cf8 0%, #c084fc 50%, #f472b6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .bento-card {
            background: rgba(17, 24, 39, 0.72);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .bento-card:hover {
            border-color: rgba(129, 140, 248, 0.28);
        }
        .bento-hero {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 52%, #db2777 100%);
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(124, 58, 237, 0.5);
        }
        .icon-box {
            width: 44px; height: 44px; border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem;
        }
        .form-control, .form-select {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 14px;
            padding: 10px 14px;
            color: #f8fafc;
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            background: #0f172a;
            border-color: #818cf8;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.22);
        }
        .btn-neon {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            border: none;
            border-radius: 14px;
            padding: 10px 20px;
            font-weight: 700;
            color: #fff;
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.45);
            transition: all 0.2s ease;
        }
        .btn-neon:hover {
            transform: translateY(-2px);
            color: #fff;
            box-shadow: 0 14px 25px -5px rgba(236, 72, 153, 0.55);
        }
        .modal-content {
            background: rgba(15, 23, 42, 0.96);
            backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 28px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.85);
        }
        .table-custom {
            --bs-table-bg: transparent;
            --bs-table-color: #e2e8f0;
            --bs-table-border-color: rgba(255, 255, 255, 0.06);
        }
        .pill-role {
            background: rgba(129, 140, 248, 0.15);
            border: 1px solid rgba(129, 140, 248, 0.35);
            color: #a5b4fc;
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        /* Custom Web Notification Modal / Toast */
        .custom-toast-backdrop {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 2050;
            max-width: 400px;
            animation: slideInUp 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes slideInUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-glass sticky-top py-3">
    <div class="container">
        <a class="navbar-brand fw-bold fs-4 text-white d-flex align-items-center gap-2" href="index.php">
            <span class="icon-box bg-primary bg-opacity-25 text-info" style="width:38px;height:38px;font-size:1.1rem;">
                <i class="bi bi-wallet2"></i>
            </span>
            <span>UMC <span class="text-gradient">Finance.</span></span>
        </a>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="d-none d-md-inline text-secondary small me-1">
                Halo, <strong class="text-white"><?= e($_SESSION['nama_lengkap']) ?></strong>
            </span>
            <span class="pill-role text-uppercase me-2">
                <i class="bi bi-stars me-1"></i><?= e($role) ?>
            </span>

            <a href="index.php?export=excel" class="btn btn-sm btn-outline-success rounded-pill px-3">
                <i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Download Excel
            </a>

            <?php if ($role === 'bendahara'): ?>
                <a href="index.php?backup=sql" class="btn btn-sm btn-outline-info rounded-pill px-3">
                    <i class="bi bi-database-down me-1"></i> Backup DB
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalLogout">
                <i class="bi bi-power me-1"></i> Keluar
            </button>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- Bento Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bento-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-secondary small fw-semibold">Total Uang Kas Anggota</span>
                    <div class="icon-box bg-info bg-opacity-10 text-info"><i class="bi bi-people-fill"></i></div>
                </div>
                <h4 class="fw-bold mb-1 text-white">Rp <?= number_format($total_kas, 0, ',', '.') ?></h4>
                <span class="badge bg-info bg-opacity-10 text-info rounded-pill small"><?= count($daftar_kas) ?> Data Ditampilkan</span>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bento-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-secondary small fw-semibold">Total Uang Masuk Lain</span>
                    <div class="icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-arrow-down-left-circle-fill"></i></div>
                </div>
                <h4 class="fw-bold mb-1 text-success">Rp <?= number_format($total_masuk, 0, ',', '.') ?></h4>
                <span class="badge bg-success bg-opacity-10 text-success rounded-pill small">Dana Kampus / Sponsor</span>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bento-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-secondary small fw-semibold">Total Uang Keluar</span>
                    <div class="icon-box bg-danger bg-opacity-10 text-danger"><i class="bi bi-arrow-up-right-circle-fill"></i></div>
                </div>
                <h4 class="fw-bold mb-1 text-danger">Rp <?= number_format($total_keluar, 0, ',', '.') ?></h4>
                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill small">Operasional & Alat UMC</span>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bento-hero p-4 h-100 text-white">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="text-white text-opacity-75 small fw-semibold">Saldo Akhir Aktif UMC</span>
                    <div class="icon-box bg-white bg-opacity-25 text-white"><i class="bi bi-safe2-fill"></i></div>
                </div>
                <h3 class="fw-extrabold mb-1" style="font-weight:800;">Rp <?= number_format($saldo_akhir, 0, ',', '.') ?></h3>
                <span class="badge bg-black bg-opacity-25 text-white rounded-pill small">
                    <i class="bi bi-shield-check text-warning me-1"></i>Terverifikasi Sistem
                </span>
            </div>
        </div>
    </div>

    <!-- Bar Pencarian, Filter Lengkap, & Tombol Pop-up Tambah Data -->
    <div class="bento-card p-3 p-md-4 mb-4">
        <form method="GET" action="index.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-secondary border-opacity-25 text-secondary" style="border-radius:14px 0 0 14px;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="q" class="form-control" style="border-radius:0 14px 14px 0;" placeholder="Cari nama, NIM, kategori..." value="<?= e($keyword) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="bulan" class="form-select">
                    <option value="">📅 Semua Bulan Kas</option>
                    <?php foreach ($list_bulan as $b): ?>
                        <option value="<?= $b ?>" <?= $filter_bulan === $b ? 'selected' : '' ?>><?= $b ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="jenis" class="form-select">
                    <option value="">🔄 Semua Arus Kas</option>
                    <option value="masuk" <?= $filter_jenis === 'masuk' ? 'selected' : '' ?>>🟢 Uang Masuk</option>
                    <option value="keluar" <?= $filter_jenis === 'keluar' ? 'selected' : '' ?>>🔴 Uang Keluar</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="limit" class="form-select">
                    <option value="50" <?= $limit_view === '50' ? 'selected' : '' ?>>📋 Tampil 50 Data</option>
                    <option value="all" <?= $limit_view === 'all' ? 'selected' : '' ?>>🌐 Lihat Full Semua Data</option>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <button type="submit" class="btn btn-outline-light w-100 rounded-4 py-2" title="Terapkan Filter">
                    <i class="bi bi-funnel-fill"></i>
                </button>
            </div>
            <div class="col-12 col-md-2">
                <?php if ($role === 'bendahara'): ?>
                    <button type="button" class="btn btn-neon w-100" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="bi bi-plus-circle-fill me-1"></i> Tambah Data
                    </button>
                <?php else: ?>
                    <a href="index.php" class="btn btn-outline-secondary w-100 rounded-4 py-2">Reset Filter</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tabel Data Uang Kas & Arus Kas -->
    <div class="row g-4">
        <!-- Tabel Uang Kas Anggota -->
        <div class="col-lg-6">
            <div class="bento-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-white">
                        <i class="bi bi-person-vcard-fill text-info me-2"></i>Data Pembayaran Uang Kas
                    </h6>
                    <span class="badge bg-info bg-opacity-10 text-info rounded-pill"><?= count($daftar_kas) ?> Baris</span>
                </div>
                <div class="table-responsive" style="max-height: 460px; overflow-y: auto;">
                    <table class="table table-custom table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small">
                                <th>Anggota</th>
                                <th>Bulan</th>
                                <th class="text-end">Nominal</th>
                                <th class="text-end">Opsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_kas)): ?>
                                <tr><td colspan="4" class="text-center text-secondary py-5">Belum ada data uang kas yang sesuai filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daftar_kas as $kas): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-white small"><?= e($kas['nama_anggota']) ?></div>
                                        <div class="text-secondary" style="font-size: 0.74rem;">
                                            <?= e($kas['nim_anggota']) ?> • <?= e($kas['tanggal_bayar']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-25 text-info rounded-pill px-2"><?= e($kas['bulan_tagihan']) ?></span>
                                    </td>
                                    <td class="text-end fw-bold text-info">+Rp <?= number_format((float)$kas['nominal'], 0, ',', '.') ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Tombol Detail Pop-up -->
                                            <button type="button" class="btn btn-outline-light border-0"
                                                onclick="bukaDetail('Uang Kas Anggota', '<?= e($kas['nama_anggota']) ?> (<?= e($kas['nim_anggota']) ?>)', 'Bulan Tagihan: <?= e($kas['bulan_tagihan']) ?>', 'Rp <?= number_format((float)$kas['nominal'], 0, ',', '.') ?>', '<?= e($kas['tanggal_bayar']) ?>', '<?= e($kas['keterangan']) ?>')"
                                                title="Lihat Detail Lengkap">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($role === 'bendahara'): ?>
                                            <!-- Tombol Edit Pop-up -->
                                            <button type="button" class="btn btn-outline-warning border-0"
                                                onclick="bukaEditKas('<?= (int)$kas['id'] ?>', '<?= e($kas['nim_anggota']) ?>', '<?= e($kas['nama_anggota']) ?>', '<?= e($kas['bulan_tagihan']) ?>', '<?= (float)$kas['nominal'] ?>', '<?= e($kas['tanggal_bayar']) ?>', '<?= e($kas['keterangan']) ?>')"
                                                title="Edit Data">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <!-- Tombol Hapus Pop-up Kustom -->
                                            <button type="button" class="btn btn-outline-danger border-0"
                                                onclick="bukaHapus('kas', '<?= (int)$kas['id'] ?>', 'Uang Kas: <?= e($kas['nama_anggota']) ?> (<?= e($kas['bulan_tagihan']) ?>)')"
                                                title="Hapus Data">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tabel Transaksi Arus Kas Umum -->
        <div class="col-lg-6">
            <div class="bento-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-white">
                        <i class="bi bi-bar-chart-steps text-success me-2"></i>Data Arus Kas (Uang Masuk & Keluar)
                    </h6>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill"><?= count($daftar_transaksi) ?> Baris</span>
                </div>
                <div class="table-responsive" style="max-height: 460px; overflow-y: auto;">
                    <table class="table table-custom table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-secondary small">
                                <th>Arus</th>
                                <th>Kategori & Deskripsi</th>
                                <th class="text-end">Nominal</th>
                                <th class="text-end">Opsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daftar_transaksi)): ?>
                                <tr><td colspan="4" class="text-center text-secondary py-5">Belum ada transaksi yang sesuai filter.</td></tr>
                            <?php else: ?>
                                <?php foreach ($daftar_transaksi as $trx): ?>
                                <tr>
                                    <td>
                                        <?php if ($trx['jenis'] === 'masuk'): ?>
                                            <span class="badge bg-success bg-opacity-25 text-success rounded-pill px-2">MASUK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-25 text-danger rounded-pill px-2">KELUAR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-white small"><?= e($trx['kategori']) ?></div>
                                        <div class="text-secondary text-truncate" style="max-width: 190px; font-size: 0.74rem;">
                                            <?= e($trx['tanggal']) ?> • <?= e($trx['deskripsi']) ?>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold <?= $trx['jenis'] === 'masuk' ? 'text-success' : 'text-danger' ?>">
                                        <?= $trx['jenis'] === 'masuk' ? '+' : '-' ?>Rp <?= number_format((float)$trx['nominal'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Tombol Detail Pop-up -->
                                            <button type="button" class="btn btn-outline-light border-0"
                                                onclick="bukaDetail('Transaksi Arus Kas (<?= strtoupper(e($trx['jenis'])) ?>)', '<?= e($trx['kategori']) ?>', 'Jenis: Uang <?= ucfirst(e($trx['jenis'])) ?>', 'Rp <?= number_format((float)$trx['nominal'], 0, ',', '.') ?>', '<?= e($trx['tanggal']) ?>', '<?= e($trx['deskripsi']) ?>')"
                                                title="Lihat Detail Lengkap">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($role === 'bendahara'): ?>
                                            <!-- Tombol Edit Pop-up -->
                                            <button type="button" class="btn btn-outline-warning border-0"
                                                onclick="bukaEditTrx('<?= (int)$trx['id'] ?>', '<?= e($trx['jenis']) ?>', '<?= e($trx['kategori']) ?>', '<?= (float)$trx['nominal'] ?>', '<?= e($trx['tanggal']) ?>', '<?= e($trx['deskripsi']) ?>')"
                                                title="Edit Transaksi">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <!-- Tombol Hapus Pop-up Kustom -->
                                            <button type="button" class="btn btn-outline-danger border-0"
                                                onclick="bukaHapus('transaksi', '<?= (int)$trx['id'] ?>', 'Transaksi: <?= e($trx['kategori']) ?>')"
                                                title="Hapus Transaksi">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
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

<!-- ========================================================================= -->
<!-- SEMUA MODAL POP-UP KUSTOM (TANPA ALERT / CONFIRM BAWAAN BROWSER SAMA SEKALI) -->
<!-- ========================================================================= -->

<!-- 1. Modal Pop-up Lihat Detail Lengkap -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-info" id="detKategoriHeader">Detail Data</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 rounded-4 bg-dark bg-opacity-50 border border-secondary border-opacity-25">
                    <div class="small text-secondary mb-1">Nama / Judul Utama</div>
                    <h5 class="fw-bold text-white mb-3" id="detJudul">-</h5>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="small text-secondary">Informasi</div>
                            <div class="fw-semibold text-light small" id="detSub">-</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-secondary">Tanggal Tercatat</div>
                            <div class="fw-semibold text-light small" id="detTanggal">-</div>
                        </div>
                    </div>

                    <div class="small text-secondary">Nominal Transaksi</div>
                    <h4 class="fw-extrabold text-gradient mb-3" id="detNominal">Rp 0</h4>

                    <div class="small text-secondary">Deskripsi / Keterangan Lengkap</div>
                    <div class="text-white small mt-1" id="detKet">-</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-light rounded-pill px-4 w-100" data-bs-dismiss="modal">Tutup Detail</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. Modal Pop-up Konfirmasi Logout Kustom -->
<div class="modal fade" id="modalLogout" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content p-3 text-center">
            <div class="modal-body">
                <div class="icon-box bg-danger bg-opacity-10 text-danger mx-auto mb-3" style="width:56px;height:56px;font-size:1.6rem;">
                    <i class="bi bi-power"></i>
                </div>
                <h5 class="fw-bold text-white">Keluar dari Sistem?</h5>
                <p class="text-secondary small mb-4">Sesi keamanan Anda akan ditutup. Anda harus login kembali untuk masuk.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill w-50" data-bs-dismiss="modal">Batal</button>
                    <a href="logout.php" class="btn btn-danger rounded-pill w-50 fw-semibold">Ya, Keluar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($role === 'bendahara'): ?>
<!-- 3. Modal Pop-up Tambah Data Baru (Tab Uang Kas & Arus Kas) -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content p-3">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-info me-2"></i>Tambah Data Keuangan UMC</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills mb-4 gap-2" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active rounded-pill px-4" data-bs-toggle="pill" data-bs-target="#tabTambahKas" type="button">
                            <i class="bi bi-people-fill me-1"></i> Uang Kas Anggota
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill px-4" data-bs-toggle="pill" data-bs-target="#tabTambahTrx" type="button">
                            <i class="bi bi-arrow-left-right me-1"></i> Arus Kas (Masuk / Keluar)
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Tab Tambah Kas -->
                    <div class="tab-pane fade show active" id="tabTambahKas">
                        <form method="POST" action="index.php">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                            <input type="hidden" name="aksi" value="tambah_kas">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small text-secondary">NIM Anggota</label>
                                    <input type="text" name="nim_anggota" class="form-control" placeholder="23.01.53.xxxx" required>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small text-secondary">Nama Lengkap Anggota</label>
                                    <input type="text" name="nama_anggota" class="form-control" placeholder="Contoh: Mikhael Putra Wijaya" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-secondary">Bulan Kas</label>
                                    <select name="bulan_tagihan" class="form-select" required>
                                        <?php foreach ($list_bulan as $b): ?>
                                            <option value="<?= $b ?>"><?= $b ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-secondary">Nominal (Rp)</label>
                                    <input type="number" name="nominal" class="form-control" min="1000" step="500" placeholder="15000" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-secondary">Tanggal Bayar</label>
                                    <input type="date" name="tanggal_bayar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-secondary">Keterangan Pembayaran</label>
                                    <input type="text" name="keterangan" class="form-control" placeholder="Lunas via QRIS / Transfer BCA / Tunai">
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-neon w-100">Simpan Data Uang Kas</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Tab Tambah Transaksi -->
                    <div class="tab-pane fade" id="tabTambahTrx">
                        <form method="POST" action="index.php">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                            <input type="hidden" name="aksi" value="tambah_transaksi">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small text-secondary">Jenis Arus Kas</label>
                                    <select name="jenis" class="form-select" required>
                                        <option value="masuk">🟢 Uang Masuk (+)</option>
                                        <option value="keluar">🔴 Uang Keluar (-)</option>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small text-secondary">Kategori Transaksi</label>
                                    <input type="text" name="kategori" class="form-control" placeholder="Dana Kemahasiswaan / Sewa Kamera / Konsumsi" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Nominal (Rp)</label>
                                    <input type="number" name="nominal" class="form-control" min="1000" step="500" placeholder="100000" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Tanggal Transaksi</label>
                                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-secondary">Deskripsi Lengkap</label>
                                    <input type="text" name="deskripsi" class="form-control" placeholder="Rincian keperluan dana masuk atau keluar..." required>
                                </div>
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-neon w-100">Simpan Arus Kas UMC</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. Modal Pop-up Edit Uang Kas Anggota -->
<div class="modal fade" id="modalEditKas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-pencil-square me-2"></i>Edit Data Uang Kas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="aksi" value="edit_kas">
                    <input type="hidden" name="id" id="editKasId">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small text-secondary">NIM Anggota</label>
                            <input type="text" name="nim_anggota" id="editKasNim" class="form-control" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-secondary">Nama Anggota</label>
                            <input type="text" name="nama_anggota" id="editKasNama" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Bulan Kas</label>
                            <select name="bulan_tagihan" id="editKasBulan" class="form-select" required>
                                <?php foreach ($list_bulan as $b): ?>
                                    <option value="<?= $b ?>"><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Nominal (Rp)</label>
                            <input type="number" name="nominal" id="editKasNominal" class="form-control" min="1000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Tanggal Bayar</label>
                            <input type="date" name="tanggal_bayar" id="editKasTanggal" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Keterangan</label>
                            <input type="text" name="keterangan" id="editKasKet" class="form-control">
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-warning w-100 fw-bold rounded-3">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 5. Modal Pop-up Edit Transaksi Arus Kas -->
<div class="modal fade" id="modalEditTrx" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-pencil-square me-2"></i>Edit Transaksi Arus Kas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="aksi" value="edit_transaksi">
                    <input type="hidden" name="id" id="editTrxId">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small text-secondary">Jenis Arus</label>
                            <select name="jenis" id="editTrxJenis" class="form-select" required>
                                <option value="masuk">🟢 Uang Masuk (+)</option>
                                <option value="keluar">🔴 Uang Keluar (-)</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-secondary">Kategori</label>
                            <input type="text" name="kategori" id="editTrxKategori" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Nominal (Rp)</label>
                            <input type="number" name="nominal" id="editTrxNominal" class="form-control" min="1000" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-secondary">Tanggal</label>
                            <input type="date" name="tanggal" id="editTrxTanggal" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-secondary">Deskripsi Lengkap</label>
                            <input type="text" name="deskripsi" id="editTrxDeskripsi" class="form-control" required>
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-warning w-100 fw-bold rounded-3">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 6. Modal Pop-up Konfirmasi Hapus Kustom (Pengganti confirm() Browser) -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content p-3 text-center">
            <div class="modal-body">
                <div class="icon-box bg-danger bg-opacity-10 text-danger mx-auto mb-3" style="width:60px;height:60px;font-size:1.8rem;">
                    <i class="bi bi-trash3-fill"></i>
                </div>
                <h5 class="fw-bold text-white">Hapus Data Ini?</h5>
                <p class="text-secondary small mb-2" id="hapusInfoText">-</p>
                <p class="text-danger small mb-4">Tindakan ini tidak dapat dibatalkan.</p>
                <form method="POST" action="index.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="aksi" value="hapus_data">
                    <input type="hidden" name="tipe" id="hapusTipe">
                    <input type="hidden" name="id" id="hapusId">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-pill w-50" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger rounded-pill w-50 fw-bold">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 7. Pop-up Notifikasi Kustom Web (Sukses / Error) -->
<?php if ($pesan_sukses !== '' || $pesan_error !== ''): ?>
<div class="custom-toast-backdrop" id="customWebToast">
    <div class="bento-card p-3 border <?= $pesan_sukses !== '' ? 'border-success' : 'border-danger' ?> shadow-lg d-flex align-items-center gap-3">
        <div class="icon-box <?= $pesan_sukses !== '' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
            <i class="bi <?= $pesan_sukses !== '' ? 'bi-check2-all' : 'bi-shield-exclamation' ?>"></i>
        </div>
        <div class="flex-grow-1">
            <div class="fw-bold small <?= $pesan_sukses !== '' ? 'text-success' : 'text-danger' ?>">
                <?= $pesan_sukses !== '' ? 'Berhasil!' : 'Peringatan Keamanan' ?>
            </div>
            <div class="text-white small"><?= e($pesan_sukses ?: $pesan_error) ?></div>
        </div>
        <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('customWebToast').remove()"></button>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Fungsi Buka Pop-up Detail Lengkap
    function bukaDetail(kategoriHeader, judul, sub, nominal, tanggal, ket) {
        document.getElementById('detKategoriHeader').textContent = kategoriHeader;
        document.getElementById('detJudul').textContent = judul;
        document.getElementById('detSub').textContent = sub;
        document.getElementById('detNominal').textContent = nominal;
        document.getElementById('detTanggal').textContent = tanggal;
        document.getElementById('detKet').textContent = ket || '-';
        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    // Fungsi Buka Pop-up Edit Kas
    function bukaEditKas(id, nim, nama, bulan, nominal, tanggal, ket) {
        document.getElementById('editKasId').value = id;
        document.getElementById('editKasNim').value = nim;
        document.getElementById('editKasNama').value = nama;
        document.getElementById('editKasBulan').value = bulan;
        document.getElementById('editKasNominal').value = nominal;
        document.getElementById('editKasTanggal').value = tanggal;
        document.getElementById('editKasKet').value = ket;
        new bootstrap.Modal(document.getElementById('modalEditKas')).show();
    }

    // Fungsi Buka Pop-up Edit Transaksi
    function bukaEditTrx(id, jenis, kategori, nominal, tanggal, deskripsi) {
        document.getElementById('editTrxId').value = id;
        document.getElementById('editTrxJenis').value = jenis;
        document.getElementById('editTrxKategori').value = kategori;
        document.getElementById('editTrxNominal').value = nominal;
        document.getElementById('editTrxTanggal').value = tanggal;
        document.getElementById('editTrxDeskripsi').value = deskripsi;
        new bootstrap.Modal(document.getElementById('modalEditTrx')).show();
    }

    // Fungsi Buka Pop-up Konfirmasi Hapus Kustom (Tanpa confirm() bawaan browser)
    function bukaHapus(tipe, id, infoText) {
        document.getElementById('hapusTipe').value = tipe;
        document.getElementById('hapusId').value = id;
        document.getElementById('hapusInfoText').textContent = infoText;
        new bootstrap.Modal(document.getElementById('modalHapus')).show();
    }

    // Otomatis sembunyikan notifikasi toast setelah 4.5 detik
    setTimeout(() => {
        const toast = document.getElementById('customWebToast');
        if (toast) toast.remove();
    }, 4500);
</script>
</body>
</html>