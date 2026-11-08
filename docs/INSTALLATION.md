# 🚀 Panduan Instalasi – TaskFlow Pro (Professional Edition)

TaskFlow Pro dirancang untuk kemudahan satu klik, namun beberapa konfigurasi sistem tambahan diperlukan agar fitur premium berjalan sempurna.

---

## 📋 Prasyarat Sistem
-   **PHP**: 8.0 atau lebih tinggi (Direkomendasikan 8.1+)
-   **Ext PHP**: `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo`
-   **Database**: MySQL 5.7+ atau MariaDB 10.3+
-   **SMTP Server**: Dibutuhkan untuk fitur notifikasi email (Host, Port, User, Pass).

---

## 🛠️ Langkah-langkah Instalasi

### 1. Database & Config
1.  Buat database baru di MySQL (misalnya `taskflow_db`).
2.  Impor file `database.sql` (jika tersedia) atau gunakan file skema terbaru.
3.  Buka `config.php`:
    ```php
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'nama_database_anda');
    define('DB_USER', 'user_anda');
    define('DB_PASS', 'password_anda');
    ```
4.  Pastikan `baseUrl()` sudah mengarah ke root atau subfolder yang benar.

### 2. Notifikasi Telegram (Bot)
1.  Buka Telegram dan cari **@BotFather**.
2.  Gunakan `/newbot` untuk membuat bot baru dan dapatkan **Token API**.
3.  Setelah bot dibuat, cari bot Anda di Telegram dan tekan **START**.
4.  Masukkan token ini ke menu **Pengaturan (Settings)** di dashboard TaskFlow Pro.

### 3. Notifikasi Email (SMTP)
1.  Pastikan Anda memiliki detail SMTP dari layanan email (seperti Gmail, Titan Mail, atau Corporate Email).
2.  Konfigurasikan di menu **Pengaturan**:
    *   **SMTP Host**: `smtp.domainanda.com`
    *   **SMTP Port**: `465` (SSL) atau `587` (TLS)
    *   **SMTP User/Pass**: Email & Password asli Anda.
3.  Klik tombol **"Test Kirim Email Sekarang"** untuk memverifikasi.

---

## ⚙️ Integrasi aaPanel & Cron Job (Sangat Penting)

Agar notifikasi harian terkirim otomatis, Anda **WAJIB** mendaftarkan script pengiriman ke sistem Cron.

### Menambahkan Cron Job di aaPanel:
1.  Masuk ke menu **Cron** di panel aaPanel Anda.
2.  **Type of Task**: Shell Script.
3.  **Name of Task**: `TaskFlow Daily Notif`.
4.  **Execution cycle**: **Hour** (Setiap 1 Jam).
5.  **Minute**: `00` (Tepat di menit ke-0).
6.  **Script content**:
    ```bash
    php /www/wwwroot/domain-anda/notif_harian.php
    ```
    *(Ganti `/www/wwwroot/domain-anda/` dengan path folder project Anda yang sebenarnya).*

---

## 🐳 Panduan Menjalankan & Memperbarui via Docker

TaskFlow Pro sudah dilengkapi dengan konfigurasi `Dockerfile`, `docker-compose.yml`, dan `docker-entrypoint.sh` siap pakai untuk lingkungan container / cloud.

### 1. Menjalankan Pertama Kali:
```bash
docker compose up -d --build
```
Aplikasi langsung dapat diakses di peramban pada port 2026:
`http://localhost:2026` atau `http://IP-SERVER:2026`

### 2. Cara Update Aplikasi di Docker:
Setiap kali ada pembaruan kode di repositori, cukup jalankan:
```bash
# 1. Ambil pembaruan kode terbaru
git pull origin main

# 2. Rebuild dan restart container secara seamless
docker compose up -d --build
```

### 3. Keamanan Data (Persistence):
* **Basis Data**: Disimpan secara persisten pada volume `db_data`, sehingga data akun, tugas, dan histori **tidak akan terhapus** saat container di-restart atau di-build ulang.
* **Berkas Fisik (Uploads)**: Dimounting langsung dari folder host `./uploads`, menjamin seluruh dokumen aman tersimpan.

---

## 🔒 Keamanan (Security)
-   **Folder Uploads**: Pastikan folder `/uploads/` dan subfoldernya memiliki izin tulis (`chmod 755`).
-   **File Config**: Jangan membagikan file `config.php` ke siapa pun.
-   **SSL**: Sangat direkomendasikan menjalankan TaskFlow Pro menggunakan HTTPS (SSL) melalui fitur One-Click SSL di aaPanel.

---

© 2026 **TaskFlow Pro**. Dikembangkan oleh **Habib Frambudi**.
