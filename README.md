# Sistem Manajemen Keuangan UMC (Unisbank Media Center)

## Deskripsi
Aplikasi berbasis web dengan keamanan ketat yang digunakan untuk mencatat, mengelola, dan memantau arus keuangan organisasi UKM Unisbank Media Center (UMC). Sistem ini memfasilitasi pencatatan pembayaran uang kas rutin anggota serta rekapitulasi dana masuk dan dana keluar dari setiap kegiatan maupun operasional UMC secara transparan, akuntabel, dan terstruktur.

## Anggota Kelompok

| No | Nama Lengkap | NIM | Akun GitHub | Peran Awal |
| :--- | :--- | :--- | :--- | :--- |
| 1 | Mikhael Putra Wijaya | 23.01.53.0001 | [github.com/MikhaelPutraProject](https://github.com/MikhaelPutraProject) | Repository Owner |
| 2 | Imam Pandu Samputa | 23.01.53.0025 | [github.com/imam-pandu](https://github.com/imam-pandu) *(Sesuaikan)* | Collaborator |
| 3 | Putri Della Jasiska | 23.01.53.0011 | [github.com/putri-della](https://github.com/putri-della) *(Sesuaikan)* | Collaborator |

## Tujuan Project
Menyelesaikan permasalahan pencatatan keuangan UKM UMC yang sebelumnya masih dilakukan secara manual atau tersebar di berbagai spreadsheet, sehingga:
- Mempermudah **Bendahara** dalam melacak status pembayaran uang kas per anggota serta mencatat arus kas masuk/keluar secara aman.
- Mempermudah **Ketua UKM** dalam memantau kondisi saldo, memverifikasi riwayat transaksi, dan mengaudit laporan keuangan secara *real-time*.
- Menerapkan standar keamanan aplikasi web modern (proteksi SQL Injection, CSRF, XSS, Session Hijacking, dan Brute-Force Login).

## Fitur Awal & Hak Akses (RBAC)
1. **Autentikasi Keamanan Tinggi:**
   - Password hashing menggunakan algoritma **Bcrypt** (`password_hash`).
   - Proteksi **Brute-Force / Rate Limiting** (kunci sementara setelah 5x gagal login).
   - Regenerasi Session ID otomatis (`session_regenerate_id`) & proteksi **CSRF Token**.
2. **Role-Based Access Control (RBAC):**
   - **Bendahara (`bendahara`):** Memiliki akses penuh untuk melihat dashboard, menginput pembayaran uang kas anggota, mencatat pemasukan/pengeluaran, serta menghapus/mengelola data transaksi.
   - **Ketua UKM (`ketua`):** Memiliki akses monitoring (*View & Audit Only*) untuk memantau dashboard saldo, memeriksa riwayat uang kas anggota, dan memvalidasi laporan arus kas tanpa risiko mengubah data secara tidak sengaja.
3. **Manajemen Uang Kas Anggota:** Pencatatan iuran kas rutin per anggota per bulan.
4. **Pencatatan Arus Kas (Cash In & Cash Out):** Input dana masuk (kas kampus, sponsor, dll.) dan dana keluar (operasional, alat media, event).
5. **Dashboard Keuangan Real-Time:** Ringkasan total pemasukan, total pengeluaran, total uang kas anggota, dan saldo akhir.

## Technology Stack
- **Backend:** PHP 8.x (Native dengan PDO Prepared Statements)
- **Frontend:** HTML5, CSS3, Bootstrap 5
- **Database:** MySQL / MariaDB
- **Security Standards:** PDO Parameter Binding, CSRF Token Validation, XSS Output Escaping, Bcrypt Hashing, HTTP Security Headers
- **Version Control & Collaboration:** Git & GitHub
- **DevOps & MLOps Tools (Tahap Lanjutan):** GitHub Actions (CI/CD), Docker

## Project Roadmap
- [x] Membuat repository kelompok
- [x] Membuat dokumentasi awal (`README.md`) dan struktur direktori
- [x] Merancang skema database (`docs/database.sql`)
- [x] Mengembangkan sistem autentikasi aman & Role-Based Access Control (Bendahara & Ketua UKM)
- [x] Mengembangkan modul Dashboard, Uang Kas Anggota, dan Arus Kas
- [ ] Implementasi pipeline CI/CD dan containerization dengan Docker
