# TaskFlow Pro: Enterprise Cloud Platform

![Version](https://img.shields.io/badge/version-3.2.1-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-indigo)
![Tailwind](https://img.shields.io/badge/Styling-Tailwind_CSS-sky)
![PWA](https://img.shields.io/badge/PWA-Ready-purple)
![Production](https://img.shields.io/badge/Status-Production_Ready-emerald)

**TaskFlow Pro** adalah ruang kendali modern terpadu untuk manajemen tugas, dokumen terkompresi cerdas, kalender kerja, audit log real-time, dan kolaborasi tim profesional dengan antarmuka *frosted glass* 100% *full-width*.

---

## 🚀 Fitur Utama & Keunggulan

- **📂 Pusat Dokumen 2.0 & Aksi Massal (*Bulk Actions*)**:
  - Seleksi ganda multi-dokumen dengan *Floating Multi-Action Bar*.
  - Unduh dokumen terpilih sekaligus dalam satu berkas `.zip` via native `ZipArchive`.
  - Pemindai & pembersih otomatis entri dokumen rusak/tanpa berkas fisik (*Broken Records Cleaner*).
  - Unggah berkas interaktif via *Drag-and-Drop fullscreen overlay*.
  - Kompresi berkas otomatis dan kontrol kuota penyimpanan per-pengguna.
- **📱 Dukungan Progressive Web App (PWA)**:
  - Dilengkapi `manifest.json` dan Service Worker `sw.js` untuk caching cerdas.
  - Dapat diinstal langsung ke layar utama ponsel (*Add to Home Screen*) seperti aplikasi native mobile di Android dan iOS.
- **🔍 Navigasi Global Command Palette (`Ctrl + K` / `Cmd + K`)**:
  - Akses cepat pencarian halaman dan aksi penting dari mana saja dengan navigasi keyboard (`ArrowDown`, `ArrowUp`, `Enter`, `Esc`).
- **📄 Ekspor & Cetak Rekap Laporan Resmi Ber-Header Perusahaan**:
  - Rekap audit aktivitas otomatis dilengkapi identitas resmi perusahaan (Logo, Nama Instansi, Alamat, Hotline, Email, Website) dan blok validasi tanda tangan administrator.
- **📌 Papan Kanban & Kalender Produktivitas**:
  - Alur kerja 3 kolom (*Todo*, *In Progress*, *Done*) dengan paginasi interaktif dan sinkronisasi instan.
- **👤 Profil & Keamanan 2.0**:
  - Tata letak *Master-Detail 2-Kolom* bersih dengan tab *Informasi Profil* dan *Keamanan / Password*.
  - Ganti avatar langsung pada foto profil.
  - Integrasi Telegram Chat ID dengan indikator status koneksi dan tombol tes notifikasi.
- **⚙️ Pengaturan Sistem Terpadu**:
  - Tampilan baris 2-kolom ala GitHub/Vercel settings.
  - Verifikasi koneksi Telegram Bot Token instan.
  - Pilihan enkripsi SMTP (TLS / SSL / None) dan fitur *Kirim Email Uji Coba*.
- **🔐 Portal Autentikasi Eksekutif**:
  - Tampilan *ambient canvas* modern dengan *toggle password visibility* dan alur proteksi sesi anti-session fixation.
- **📐 100% Full-Width Cohesive Layout**:
  - Standarisasi tata letak di seluruh 11 halaman sistem (`w-full space-y-6 p-4 md:p-8`), bebas pergeseran visual saat berpindah tab.

---

## 🛠️ Spesifikasi Teknis

- **Bahasa & Runtime**: PHP 8.0+ (Native PDO, ZipArchive, cURL)
- **Basis Data**: MariaDB / MySQL
- **CSS Framework**: Tailwind CSS (JIT & Dark Mode Support)
- **Komponen Visual**: Chart.js, Google Fonts (Plus Jakarta Sans & Inter)
- **Protokol Email**: PHPMailer dengan enkripsi TLS/SSL
- **Integrasi Webhook**: Telegram Bot API
- **Standar PWA**: Web App Manifest v1 & Service Worker Cache API

---

## 📦 Panduan Instalasi Singkat

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/frambudi75/taskflow-pro.git
   cd taskflow-pro
   ```
2. **Konfigurasi Basis Data**:
   - Impor skema basis data `database.sql` ke MySQL / MariaDB Anda.
   - Buka berkas `config.php` dan sesuaikan kredensial koneksi:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'taskflow_db');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```
3. **Pengaturan Sistem**:
   - Masuk ke portal sistem menggunakan akun Administrator.
   - Buka menu **Pengaturan Sistem** untuk mengunggah logo perusahaan, mengisi identitas resmi organisasi, menyetel token bot Telegram, dan mengonfigurasi email SMTP.
4. **Otomatisasi Cron Notifikasi**:
   - Jalankan cron job pada server untuk pengiriman ringkasan harian terjadwal:
     ```bash
     0 * * * * php /path/to/taskflow/notif_harian.php
     ```

---

## 📚 Dokumentasi & Catatan Rilis

- [📘 Panduan Lengkap Arsitektur & Fitur](docs/DOCUMENTATION.md)
- [📝 Catatan Rilis Lengkap (CHANGELOG)](CHANGELOG.md)
- [🔧 Panduan Troubleshooting & Masalah Umum](docs/troubleshooting.md)
- [🚀 Panduan Instalasi Server & Docker](docs/INSTALLATION.md)

---

## 📄 Lisensi

&copy; 2026 **TaskFlow Pro**. Dikembangkan oleh **Habib Frambudi**. Hak Cipta Dilindungi.
