# 📘 Dokumentasi Resmi TaskFlow Pro v3.2.1

Selamat datang di dokumentasi resmi **TaskFlow Pro v3.2.1**. Dokumen ini merangkum arsitektur teknis, panduan modul fitur, integrasi sistem, serta praktik terbaik operasional platform.

---

## 🏗️ Struktur Direktori Proyek

```text
taskflow/
├── config.php                 # Inisialisasi basis data (PDO), helper global, routing & APP_VERSION
├── index.php                  # Single Entrypoint Router, Layout Master, Command Palette & PWA Registration
├── login.php                  # Portal Autentikasi Eksekutif (Aurora Ambient Glass)
├── serve_file.php             # Endpoint pengunduhan & streaming berkas berotentikasi aman
├── notif_harian.php           # Runner cron job notifikasi berkala (Telegram & SMTP)
├── manifest.json              # Web App Manifest untuk dukungan Progressive Web App (PWA)
├── sw.js                      # Service Worker caching & background sync
├── database.sql               # Skema struktur basis data MariaDB/MySQL
├── CHANGELOG.md               # Catatan riwayat versi & rilis
├── README.md                  # Panduan umum repositori
│
├── pages/                     # Modul Antarmuka Pengguna (100% Full-Width)
│   ├── dashboard.php          # Dasbor KPI metrik & tren produktivitas 7 hari (Chart.js)
│   ├── calendar.php           # Kalender kerja & manajemen tugas per tanggal
│   ├── notes.php              # Catatan kerja visual dengan label & tag interaktif
│   ├── documents.php          # Pusat Dokumen 2.0 (Multi-select, ZIP, Drag-Drop, Cleaner)
│   ├── projects.php           # Papan Kanban proyek (Todo, In Progress, Done) & paginasi
│   ├── activities.php         # Audit log real-time & cetak laporan resmi ber-header instansi
│   ├── admin.php              # Manajemen pengguna, kuota penyimpanan, drawer & popover aksi
│   ├── settings.php           # Pengaturan sistem 2-kolom, Identitas & Logo, Bot Telegram & SMTP Test
│   ├── backup.php             # Pencadangan basis data & berkas sekali klik
│   ├── cache.php              # Optimasi cache, file temporer & analitik disk
│   ├── profile.php            # Profil & Keamanan 2-kolom master-detail, Telegram Chat ID
│   └── reset-password.php     # Modul pembaruan kredensial kata sandi
│
├── docs/                      # Dokumentasi Teknis Pendukung
│   ├── DOCUMENTATION.md       # Spesifikasi arsitektur & panduan modul fitur
│   ├── INSTALLATION.md        # Panduan instalasi server (Nginx/Apache/Docker)
│   ├── troubleshooting.md     # Panduan diagnosa kendala umum & perbaikan
│   └── FLOWCHART.md           # Diagram alur proses kerja sistem
│
└── uploads/                   # Repositori berkas fisik terisolasi
    ├── avatars/               # Foto profil pengguna
    ├── settings/              # Berkas logo instansi resmi
    └── [user_id]/             # Berkas dokumen terpisah per pengguna
```

---

## 🌟 Modul & Fitur Utama

### 1. 📂 Pusat Manajemen Dokumen 2.0
* **Multi-Select & Bulk Actions**:
  * Pengguna dapat memilih beberapa dokumen sekaligus melalui kotak centang pada tampilan *Grid* maupun *Tabel*.
  * Bar aksi melayang (*Floating Bar*) otomatis muncul saat ada berkas yang dipilih, menyediakan tombol:
    * **Unduh ZIP**: Mengompres semua berkas terpilih menjadi satu berkas `.zip` instan menggunakan `ZipArchive`.
    * **Hapus Terpilih**: Konfirmasi dan hapus massal berkas fisik dan data database secara aman.
* **Pemindai & Pembersih Berkas Rusak (*Broken Records Cleaner*)**:
  * Sistem memindai basis data secara otomatis untuk menemukan entri yang berkas fisiknya tidak ada di server.
  * Menampilkan banner notifikasi oranye dengan tombol *"Bersihkan Sekarang"* untuk menjaga sinkronisasi database.
* **Drag & Drop Interaktif**:
  * Pengguna dapat menarik berkas dari desktop komputer langsung ke halaman. *Overlay dropzone* visual akan menyambut dan langsung memuat berkas ke modal unggah.
* **Proteksi Keamanan Path Berkas**:
  * Pengecekan fisik berkas sebelum merender tautan unduh/pratinjau gambar, mengeliminasi error HTTP 403 akibat path kosong.

---

### 2. ⚡ Audit Log & Cetak Rekap Resmi Ber-Header Instansi
* **Timeline Aktivitas Real-Time**:
  * Mencatat semua kejadian penting: login, upload berkas, pembuatan tugas, perubahan pengaturan, dan pembagian dokumen.
  * Dilengkapi tombol tab segmented (*Semua*, *Tugas/Berkas*, *Sistem*) dan paginasi interaktif (10, 15, 25, 50 data per halaman).
* **Cetak Rekap Laporan Resmi (`window.print()`)**:
  * Tombol *"Cetak Rekap Laporan"* memformat dokumen siap cetak atau simpan ke PDF.
  * Dilengkapi identitas resmi perusahaan (Logo, Nama PT, Alamat, Kontak, Website) yang diambil otomatis dari pengaturan.
  * Dilengkapi blok tanda tangan validasi administrator untuk arsip audit enterprise.

---

### 3. 🔍 Global Command Palette (`Ctrl + K` / `Cmd + K`)
* **Pintasan Cepat Tanpa Mouse**:
  * Tekan `Ctrl + K` (atau `Cmd + K` di macOS) di halaman mana pun untuk memunculkan modal pencarian instan.
  * Navigasi daftar perintah dengan `ArrowDown` dan `ArrowUp`, lalu tekan `Enter` untuk membuka halaman atau aksi.
  * Tombol pintasan juga disematkan di sidebar desktop dan header mobile agar mudah diakses.

---

### 4. 📱 Progressive Web App (PWA) & Dukungan Mobile
* **Instalasi Mandiri (*Add to Home Screen*)**:
  * Melalui konfigurasi `manifest.json`, pengguna browser ponsel (Chrome di Android, Safari di iOS) dapat menginstal TaskFlow Pro ke beranda ponsel.
  * Aplikasi berjalan dalam mode layar penuh (*standalone display*) tanpa address bar browser.
* **Service Worker Cerdas (`sw.js`)**:
  * Menggunakan strategi *Network-first* untuk data dinamis dan *Cache-first* untuk font serta aset antarmuka.

---

### 5. 👤 Profil & Keamanan 2.0 (Master-Detail)
* **Tata Letak 2-Kolom Ergonomis**:
  * **Kolom Kiri**: Kartu identitas pengguna, foto profil dengan tombol ganti foto langsung, status akun, dan statistik kuota disk.
  * **Kolom Kanan**: Pengaturan ber-tab: *Informasi Profil* dan *Keamanan & Password*.
* **Integrasi Telegram Chat ID**:
  * Menghubungkan ID Telegram pengguna untuk menerima notifikasi penugasan tugas secara langsung.
  * Dilengkapi tombol *Tes Notifikasi Telegram* untuk menguji responsivitas bot.

---

### 6. ⚙️ Pengaturan Sistem & Verifikasi Server
* **Layout 2-Kolom GitHub/Vercel-style**:
  * Mencegah ruang kosong horizontal yang berlebihan pada layar lebar.
* **Identitas & Logo Perusahaan**:
  * Input Nama Perusahaan, Tagline, Alamat Resmi, Telepon/Hotline, Email Resmi, dan Website yang terintegrasi langsung ke dokumen cetak resmi.
* **Verifikasi Koneksi Bot Telegram**:
  * Tombol sekunder di samping token untuk mengetes keabsahan token bot ke API Telegram secara real-time.
* **Uji Coba Email SMTP**:
  * Pemilihan enkripsi port (TLS / SSL / None) dan tombol *Kirim Email Uji Coba* untuk memastikan konfigurasi mail server berfungsi sebelum dipakai sistem.

---

### 7. 📐 Standarisasi 100% Full-Width Cohesive Layout
* Seluruh 11 halaman sistem menggunakan kelas kontainer terpadu: `w-full space-y-6 p-4 md:p-8`.
* Transisi antar tab atau submenu berjalan mulus tanpa pergeseran lebar atau efek layar melompat.

---

## 🔐 Standar Keamanan Sistem

1. **Session Hardening**: Regenerasi ID sesi (`session_regenerate_id(true)`) saat login berhasil untuk mencegah serangan *Session Fixation*.
2. **CSRF Protection**: Token kriptografi acak (`generateCSRF()` & `validateCSRF()`) divalidasi pada setiap mutasi data (POST).
3. **Directory Traversal Prevention**: Berkas yang diakses melalui `serve_file.php` divalidasi agar selalu berada dalam lingkup folder `uploads/` yang diizinkan dan memiliki izin kepemilikan/berbagi yang sah.
4. **Input Sanitization**: Seluruh input teks disanitasi menggunakan `htmlspecialchars()` dan query basis data selalu menggunakan *Prepared Statements (PDO)*.

---

&copy; 2026 **TaskFlow Pro**. Arsitektur & Dokumentasi disusun oleh **Habib Frambudi**.
