# TaskFlow Pro: Professional Edition

![Version](https://img.shields.io/badge/version-3.1.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-indigo)
![Tailwind](https://img.shields.io/badge/Styling-Tailwind_CSS-sky)
![Production](https://img.shields.io/badge/Status-Production_Ready-emerald)

TaskFlow Pro adalah aplikasi manajemen produktivitas terpadu untuk mengelola tugas, catatan, kalender kerja, dan arsip dokumen dalam satu tempat kerja terstruktur.

---

## Fitur Utama

- **Notifikasi Terjadwal**: Ringkasan tugas harian otomatis dikirim ke Telegram dan Email (SMTP) sesuai jadwal yang ditentukan.
- **Pengaturan Jadwal Notifikasi**: Atur jam pengiriman notifikasi (00:00 - 23:00) langsung dari panel pengaturan sistem.
- **Tata Letak Responsif**: Tampilan antarmuka optimal untuk perangkat mobile dan desktop.
- **Manajemen Dokumen**: Simpan, pratinjau, dan kelola dokumen dengan sistem penyimpanan file terisolasi.
- **Analitik Produktivitas**: Pantau kemajuan penyelesaian tugas melalui grafik mingguan.
- **Multi-User & Hak Akses**: Manajemen peran akun Administrator dan User standar dengan kontrol akses terpisah.
- **Pencadangan Sistem**: Ekspor database dan file pendukung kapan saja melalui fitur backup sekali klik.

---

## Spesifikasi Teknis

- **Backend**: PHP 8.0+ (Native PDO)
- **Database**: MariaDB / MySQL
- **CSS**: Tailwind CSS
- **Email**: PHPMailer (dukungan SMTP)
- **Notifikasi**: Telegram Bot API
- **Komponen Visual**: Chart.js, Google Fonts (Inter)

---

## Panduan Instalasi Singkat

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/frambudi75/taskflow-pro.git
   ```
2. **Konfigurasi Database**:
   - Impor `database.sql` ke database MySQL Anda.
   - Sesuaikan `DB_HOST`, `DB_NAME`, `DB_USER`, dan `DB_PASS` di file `config.php`.
3. **Pengaturan Telegram & SMTP**:
   - Masuk dengan akun Administrator.
   - Buka menu Pengaturan.
   - Masukkan token bot Telegram dan konfigurasi SMTP (Host, Port, User, Password).
4. **Jalankan Cron Job Notifikasi**:
   Tambahkan cron job pada server Anda untuk pemeriksaan berkala:
   ```bash
   0 * * * * php /path/to/taskflow/notif_harian.php
   ```

---

## Dokumentasi Tambahan

Untuk petunjuk penggunaan lebih detail:
- [Panduan Fitur Lengkap](docs/DOCUMENTATION.md)
- [Troubleshooting & Debugging](docs/troubleshooting.md)
- [Panduan Instalasi Lengkap](docs/INSTALLATION.md)

---

## Lisensi

(c) 2026 TaskFlow Pro. Dikembangkan oleh Habib Frambudi.
