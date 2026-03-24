# 🚀 TaskFlow Pro — Professional Edition

![Version](https://img.shields.io/badge/version-3.1.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-indigo)
![Tailwind](https://img.shields.io/badge/Styling-Tailwind_CSS-sky)
![Production](https://img.shields.io/badge/Status-Production_Ready-emerald)

**TaskFlow Pro** adalah solusi manajemen produktivitas premium yang dirancang untuk efisiensi tinggi. Dengan antarmuka modern, sistem notifikasi cerdas, dan fitur manajemen arsip yang aman, TaskFlow Pro membantu Anda tetap terorganisir di mana saja.

---

## 🌟 Fitur Utama (Highlight)

-   **⚡ Smart Notifications**: Ringkasan tugas harian otomatis dikirim ke **Telegram** dan **Email (SMTP Premium)** sesuai jadwal yang Anda atur.
-   **📅 Daily Recap Scheduler**: Atur jam pengiriman notifikasi (00:00 - 23:00) langsung dari Dashboard Sistem.
-   **📱 100% Responsive Layout**: Sidebar drawer modern yang optimal untuk perangkat Mobile (HP) dan Desktop.
-   **📁 Secure Document Management**: Simpan, pratinjau (Preview), dan kelola dokumen secara aman dengan sistem penghapusan file otomatis dari server.
-   **📊 Productivity Analytics**: Pantau kemajuan tugas Anda melalui grafik interaktif (Chart.js).
-   **🛡️ Multi-User & Role**: Dukungan untuk akun Administrator dan User biasa dengan hak akses terkontrol.
-   **🔄 System Backup**: Amankan data Anda kapan saja dengan fitur ekspor database & file konfigurasi dalam satu klik.

---

## 🛠️ Tech Stack
-   **Core**: PHP 8.0+ (Native & High Performance)
-   **Database**: MariaDB / MySQL (PDO Wrapper)
-   **Styling**: Tailwind CSS (Latest)
-   **Email Engine**: PHPMailer (SMTP Support)
-   **Notifications**: Telegram Bot API (via cURL)
-   **UI Library**: Chart.js, Lucide Icons, Google Fonts (Inter)

---

## 🚀 Instalasi & Setup Cepat

1.  **Clone Repositori**:
    ```bash
    git clone https://github.com/frambudi75/taskflow-pro.git
    ```
2.  **Konfigurasi Database**:
    -   Impor `database.sql` ke MySQL Anda.
    -   Sesuaikan `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS` di file `config.php`.
3.  **Setup SMTP & Telegram**:
    -   Masuk sebagai Administrator.
    -   Buka menu **Pengaturan (Settings)**.
    -   Masukkan Token Bot Telegram dan detail SMTP (Host, Port, User, Pass).
4.  **Aktifkan Cron Job**:
    Agar notifikasi berjalan otomatis, tambahkan Cron Job di aaPanel/Server Anda:
    ```bash
    # Jalankan setiap jam untuk mengecek jadwal pengiriman
    0 * * * * php /www/wwwroot/domain-anda/notif_harian.php
    ```

---

## 📖 Dokumentasi Lanjut
Untuk panduan lebih mendalam, silakan baca file di folder `docs`:
-   [📘 Panduan Fitur Lengkap](docs/DOCUMENTATION.md)
-   [🛠️ Troubleshooting & Debugging](docs/troubleshooting.md)
-   [🚀 Guide Instalasi Detail](docs/INSTALLATION.md)

---

## 🤝 Kontribusi & Lisensi
Proyek ini bersifat open-source. Silakan ajukan *Pull Request* jika Anda memiliki ide pengembangan yang lebih hebat!

© 2026 **TaskFlow Pro**. Dikembangkan dengan ❤️ oleh **Habib Frambudi**.
