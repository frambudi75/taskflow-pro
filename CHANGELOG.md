# Changelog

Semua perubahan dan pembaruan penting pada proyek **TaskFlow Pro** didokumentasikan dalam berkas ini.

Format pencatatan mengikuti pedoman [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan mematuhi [Semantic Versioning](https://semver.org/).

---

## [3.2.0] - 2026-11-07

### ✨ Ditambahkan (Added)
- **Aksi Massal Dokumen (*Bulk Actions*)**:
  - Dukungan seleksi *multi-select* checkbox pada tampilan *Grid* dan *Tabel*.
  - *Floating Multi-Action Bar* melayang di bagian bawah layar saat 1 atau lebih dokumen dipilih.
  - Fitur **Unduh ZIP Massal** menggunakan ekstensi native PHP `ZipArchive`.
  - Fitur **Hapus Massal Terpilih** dengan dialog konfirmasi aman.
- **Pembersih Berkas Rusak (*Broken Records Cleaner*)**:
  - Pemindai otomatis entri basis data tanpa berkas fisik di server (*orphan records*).
  - Kartu notifikasi interaktif dengan tombol *"Bersihkan Sekarang"* untuk sinkronisasi database.
- **Drag & Drop Berkas Langsung ke Layar**:
  - *Fullscreen overlay dropzone* dengan animasi interaktif saat berkas ditarik dari desktop komputer.
  - Otomatis membuka modal unggah dan mempopulasikan judul dokumen dari nama berkas asli.
- **Dukungan Progressive Web App (PWA)**:
  - Berkas `manifest.json` lengkap dengan konfigurasi tema `#6366f1` dan ikon aplikasi.
  - Service Worker `sw.js` dengan strategi *Network-first* untuk halaman dinamis dan *Cache-first* untuk aset statis.
  - Dukungan instalasi *"Add to Home Screen"* untuk browser mobile di iOS dan Android.
- **Pencarian Cepat / Command Palette (`Ctrl + K` / `Cmd + K`)**:
  - Pintasan tombol pencarian global di *sidebar desktop* dan *header mobile*.
  - Navigasi keyboard penuh (`ArrowDown`, `ArrowUp`, `Enter`, `Escape`) tanpa perlu mouse.
- **Ekspor & Cetak Rekap Laporan Resmi Ber-KOP Surat**:
  - Tombol *"Cetak Rekap Laporan"* di halaman Audit Aktivitas (`pages/activities.php`).
  - Integrasi KOP Surat resmi perusahaan (Logo, Nama PT, Alamat, Hotline, Email, Website dari pengaturan).
  - Kolom tanda tangan resmi penanggung jawab untuk pengarsipan enterprise.

### 🛡️ Diperbaiki (Fixed)
- **Pencegahan Error HTTP 403 Forbidden di Dokumen**:
  - Menambahkan validasi `file_exists` fisik sebelum merender tag `<img src="serve_file.php?file=">` pada berkas bertipe gambar dengan path kosong.
  - Menghindari peramban mengirim permintaan kosong ke `serve_file.php`.
  - Proteksi parameter kosong di `serve_file.php` dengan status `400 Bad Request`.
- **Standarisasi Tata Letak 100% Full-Width**:
  - Menyamakan lebar kontainer seluruh 11 halaman (`w-full space-y-6 p-4 md:p-8`) untuk mencegah pergeseran layout saat berpindah menu atau sub-tab.

---

## [3.1.0] - 2026-11-07

### ✨ Ditambahkan (Added)
- **Restrukturisasi Profil & Keamanan 2.0**:
  - Tata letak *Master-Detail 2-Kolom* yang bersih dan ergonomis.
  - Navigasi tab terpisah: *Informasi Profil* dan *Keamanan & Password*.
  - Upload avatar langsung pada foto profil dengan pratinjau instan.
  - Integrasi Telegram Chat ID dengan indikator status koneksi dan tombol tes notifikasi bot.
- **Penyempurnaan Pengaturan Sistem**:
  - Format 2-Kolom pengaturan ala GitHub/Vercel untuk mengurangi ruang putih kosong.
  - Tombol verifikasi koneksi Telegram Bot Token langsung ke API Telegram.
  - Pengaturan enkripsi SMTP (TLS / SSL / None) dan tombol *Kirim Email Uji Coba*.
- **Portal Autentikasi Eksekutif (Login)**:
  - Desain *ambient mesh canvas* terpadu dengan efek kaca *frosted glass*.
  - Pembersihan tombol akun demo/dummy.
  - Fitur toggle sembunyikan/tampilkan kata sandi.
  - Alur reset kata sandi terpusat melalui Administrator.
- **Paginasi Interaktif (Kanban & Aktivitas)**:
  - Paginasi *client-side* responsif dengan pilihan jumlah data per halaman (10, 15, 25, 50).
  - Sinkronisasi instan dengan filter pencarian dan tab navigasi.

---

## [3.0.0] - 2026-09-25

### ✨ Ditambahkan (Added)
- Rilis arsitektur modern TaskFlow Pro 3.0 dengan dukungan kompresi cerdas berkas, kuota per-pengguna, dan audit log real-time.
- Sistem otentikasi berbasis session hardening dan proteksi CSRF token.
- Integrasi bot Telegram dan pengiriman email otomatis via SMTP PHPMailer.
