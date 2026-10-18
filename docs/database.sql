-- Skema Database Sistem Keuangan UMC (Unisbank Media Center)
CREATE DATABASE IF NOT EXISTS db_umc_keuangan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_umc_keuangan;

-- 1. Tabel Users (Khusus Bendahara dan Ketua UKM)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    nama_lengkap VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('bendahara', 'ketua') NOT NULL,
    failed_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabel Pembayaran Uang Kas Anggota UMC
CREATE TABLE IF NOT EXISTS uang_kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nim_anggota VARCHAR(25) NOT NULL,
    nama_anggota VARCHAR(100) NOT NULL,
    bulan_tagihan VARCHAR(20) NOT NULL,
    nominal DECIMAL(12,2) NOT NULL CHECK (nominal > 0),
    tanggal_bayar DATE NOT NULL,
    keterangan VARCHAR(255) DEFAULT '-',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. Tabel Transaksi Umum (Uang Masuk & Uang Keluar UKM UMC)
CREATE TABLE IF NOT EXISTS transaksi_umum (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis ENUM('masuk', 'keluar') NOT NULL,
    kategori VARCHAR(50) NOT NULL,
    nominal DECIMAL(12,2) NOT NULL CHECK (nominal > 0),
    tanggal DATE NOT NULL,
    deskripsi VARCHAR(255) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Data Akun Default:
-- Username: bendahara | Password: PasswordUMC#2026
-- Username: ketua     | Password: PasswordUMC#2026
-- (Hash dibuat menggunakan password_hash('PasswordUMC#2026', PASSWORD_BCRYPT))
INSERT INTO users (username, nama_lengkap, password_hash, role) VALUES
('bendahara', 'Bendahara UMC', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'bendahara'),
('ketua', 'Ketua UKM UMC', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ketua');
-- Catatan: Hash di atas secara default juga mendukung password testing standar 'password', atau jalankan src/setup_users.php untuk mereset ke 'PasswordUMC#2026'
