# Sistem Manajemen Keuangan UMC (Unisbank Media Center)

## 🌐 Live Demo Aplikasi
Aplikasi ini telah di-*deploy* dan dapat diakses langsung secara online melalui tautan berikut:
👉 **[https://umcproject.web.id/umc-keuangan](https://umcproject.web.id/umc-keuangan)**

### 🔐 Akun Akses Sistem (Login Credentials)
Gunakan akun berikut untuk menguji coba fitur berdasarkan hak akses (*Role-Based Access Control*):

| Peran Pengguna | Username | Password | Hak Akses Utama |
| :--- | :--- | :--- | :--- |
| **Bendahara UMC** | `bendahara` | `PasswordUMC#2026` | **Full Access (CRUD):** Input/Edit/Hapus Uang Kas & Arus Kas, Export Excel, Backup Database SQL |
| **Ketua UKM UMC** | `ketua` | `PasswordUMC#2026` | **Monitoring & Audit:** Melihat saldo *real-time*, filter & pencarian data, lihat detail, dan Export Excel |

---

## Deskripsi
Aplikasi berbasis web dengan keamanan ketat dan antarmuka modern (*Dark Glassmorphism UI*) yang digunakan untuk mencatat, mengelola, dan memantau arus keuangan organisasi UKM Unisbank Media Center (UMC). Sistem ini memfasilitasi pencatatan pembayaran uang kas rutin anggota serta rekapitulasi dana masuk dan dana keluar dari setiap kegiatan maupun operasional UMC secara transparan, akuntabel, dan terstruktur.

## Anggota Kelompok

| No | Nama Lengkap | NIM | Akun GitHub | Peran Awal |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Mikhael Putra Wijaya | 23.01.53.0001 | [github.com/MikhaelPutraProject](https://github.com/MikhaelPutraProject) | Repository Owner |
| 2 | Imam Pandu Samputa | 23.01.53.0025 | [github.com/imam-pandu](https://github.com/imampandu) | Collaborator |
| 3 | Putri Della Jasiska | 23.01.53.0011 | [github.com/putri-della](https://github.com/putri--della) | Collaborator |

## Tujuan Project
Menyelesaikan permasalahan pencatatan keuangan UKM UMC yang sebelumnya masih dilakukan secara manual atau tersebar di berbagai spreadsheet, sehingga:
- Mempermudah **Bendahara** dalam melacak status pembayaran uang kas per anggota serta mencatat arus kas masuk/keluar secara aman.
- Mempermudah **Ketua UKM** dalam memantau kondisi saldo, memverifikasi riwayat transaksi, dan mengaudit laporan keuangan secara *real-time*.
- Menerapkan standar keamanan aplikasi web modern (proteksi SQL Injection, CSRF, XSS, Session Hijacking, dan Brute-Force Login).

## Fitur Utama & Keamanan
1. **Autentikasi Keamanan Tinggi:**
   - Password hashing menggunakan algoritma **Bcrypt** (`password_hash`) & fitur *Show/Hide Password* (ikon mata).
   - Proteksi **Brute-Force / Rate Limiting** (kunci otomatis 15 menit setelah 5x gagal login).
   - Regenerasi Session ID otomatis (`session_regenerate_id`), timeout sesi 30 menit, & proteksi **CSRF Token**.
2. **Role-Based Access Control (RBAC):** Pembagian hak akses ketat antara **Bendahara** dan **Ketua UKM**.
3. **Custom Interactive Modal Pop-Up & Toast Notification:** Seluruh aksi tambah data, edit data, lihat detail lengkap, konfirmasi hapus, dan keluar sistem menggunakan pop-up modal kustom bawaan web (tanpa *alert/confirm* bawaan browser).
4. **Pencarian, Filter Multi-Kriteria & Full Data View:** Pencarian instan berdasarkan nama/NIM/kategori, filter per bulan dan jenis arus kas, serta opsi menampilkan seluruh baris data.
5. **Export Laporan Excel/CSV & Backup Database SQL:** Unduh rekapitulasi keuangan dalam format `.csv` (kompatibel penuh dengan Microsoft Excel) dan fitur pencadangan database `.sql` sekali klik.

## Technology Stack
- **Backend:** PHP 8.x (Native dengan PDO Prepared Statements)
- **Frontend:** HTML5, CSS3, Bootstrap 5.3, Bootstrap Icons, Plus Jakarta Sans Font
- **Database:** MySQL / MariaDB
- **Security Standards:** PDO Parameter Binding, CSRF Token Validation, XSS Output Escaping, Bcrypt Hashing, HTTP Security Headers (CSP, X-Frame-Options)
- **Version Control & Collaboration:** Git & GitHub
- **DevOps & MLOps Tools (Tahap Lanjutan):** GitHub Actions (CI/CD), Docker

## Project Roadmap
- [x] Membuat repository kelompok
- [x] Membuat dokumentasi awal (`README.md`) dan struktur direktori
- [x] Merancang skema database (`docs/database.sql`)
- [x] Mengembangkan sistem autentikasi aman & Role-Based Access Control (Bendahara & Ketua UKM)
- [x] Mengembangkan fitur CRUD lengkap dengan Modal Pop-up, Filter, Export Excel, dan Backup DB
- [x] Melakukan deployment ke domain produksi (`https://umcproject.web.id/umc-keuangan`)
- [ ] Implementasi pipeline CI/CD otomatis dan containerization dengan Docker
