# Changelog

Semua perubahan dan pembaruan penting pada proyek **TaskFlow Pro** didokumentasikan dalam berkas ini.

Format pencatatan mengikuti pedoman [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan mematuhi [Semantic Versioning](https://semver.org/).

---

## [3.4.0] - 2026-11-18

### 🌓 Sistem Dark Mode Paripurna, Tombol Alih Tema Header, & Navigasi Melayang Mobile
- **Implementasi Dark Mode Menyeluruh & Paripurna (100% Cohesive Dark Theme)**:
  - Menyempurnakan palet warna gelap Midnight Navy (`#0B1120`, `#0F172A`, `#1E293B`) di seluruh halaman modul tanpa silau atau teks tersembunyi:
    - **Manajemen Dokumen (`pages/documents.php`)**: Kartu berkas grid, baris tabel dokumen, filter kategori, dropzone unggah, serta modal unggah, berbagi publik berbatas waktu, dan penampil pratinjau.
    - **Papan Kanban Proyek (`pages/projects.php`)**: Jalur kolom status (*kanban lane*), kartu tugas seret-lepas (*task card*), tombol aksi cepat, pagination kolom, dan modal tambah tugas.
    - **Aktivitas & Log Jejak Audit (`pages/activities.php`)**: Garis waktu real-time (*audit timeline*), pembatas tanggal (*date dividers*), lencana pengguna, filter tab, dan pagination.
    - **Dasbor & Kalender (`pages/dashboard.php`, `pages/calendar.php`)**: Konsistensi kartu metrik ringkasan, grafik tren, dan matriks tanggal.
  - Skrip anti-kedip (*Anti-FOUC*) di `<head>` untuk deteksi tema instan dari `localStorage` sebelum perenderan awal dokumen.
- **Tombol Alih Tema Langsung di Top Header (`index.php`)**:
  - Tombol toggle tema satu klik (ikon Matahari ☀️ / Bulan 🌙) di samping lencana profil pengguna.
  - Integrasi aksi alih tema langsung dari Command Palette (`Ctrl + K`).
- **Bilah Navigasi Melayang untuk Perangkat Bergerak (*Mobile Floating Bottom Bar*)**:
  - Bilah navigasi kapsul mengambang (*floating pill bar*) di bagian bawah layar ponsel (`< md`) untuk akses cepat satu jempol ke Dasbor, Kalender, Catatan, Dokumen, dan Proyek.
- **Pembaruan Service Worker & Mitigasi Kesalahan Konsol Jaringan (`sw.js`)**:
  - Peningkatan cache PWA ke `taskflow-pwa-v3.4.0`.
  - Pembatasan mutlak pencegatan *same-origin* sehingga tidak mengintersepsi skrip pihak ketiga (Cloudflare Web Analytics, beacon, ad-blocker) dan selalu mengembalikan objek `Response` valid tanpa melempar galat Promise.

---

## [3.3.3] - 2026-11-18

### 💎 Penyempurnaan Navigasi Sidebar, Eliminasi Tautan Kuota, & Pengerasan Stabilitas PWA
- **Penyempurnaan Visual Navigasi Sidebar Kiri (`index.php`)**:
  - Menghilangkan takik putih mengambang yang canggung (`.nav-item::before` dan `.nav-item.active::before`) pada menu navigasi aktif.
  - Memodernisasi *pill button* navigasi dengan radius sudut halus (`rounded-xl`), transisi lembut saat disorot (`bg-white/12`, `translateX(3px)`), dan status aktif bersih berkilau (`bg-white/22`, `font-weight: 700`, bayangan lembut).
  - Menghapus baris tombol redundan *Kelola Kuota* dan tombol logout di bagian dasar sidebar lengkung agar kartu *Storage Details* tampil proporsional, lapang, dan rapi sesuai desain referensi Goodle Drive.
- **Pengerasan Service Worker & Pencegahan Galat Jaringan PWA (`sw.js`)**:
  - Menerapkan aturan pencegahan ketat *same-origin only* pada `sw.js`: Service Worker tidak akan pernah mengintersepsi domain pihak ketiga (seperti Cloudflare Insights, beacon analitik, atau CDN eksternal).
  - Mengabaikan berkas PHP dinamis dan permintaan bernavigasi untuk mencegah interferensi jaringan.
  - Memastikan seluruh *fallback* tangkapan galat selalu mengembalikan objek `Response` valid, meniadakan peringatan konsol `TypeError: Failed to convert value to 'Response'` dan `network error response: the promise was rejected`.
- **Deklarasi Fungsi Modal Global Universal (`index.php`, `pages/documents.php`)**:
  - Mendeklarasikan fungsi `openUploadModal` dan `closeUploadModal` secara top-level pada `window` dan cakupan global sejak tag `<head>` untuk mencegah `ReferenceError: openUploadModal is not defined`.
- **Pembersihan Modul Kalender (`pages/calendar.php`)**:
  - Menghapus duplikasi ikon plus pada tombol `Buat Agenda Cepat` di header kalender.

---

## [3.3.2] - 2026-11-18

### 📅 Harmonisasi Tema Goodle Drive pada Kalender & Catatan Kerja
- **Penyelarasan Desain Modul Kalender Produktivitas (`pages/calendar.php`)**:
  - Menghilangkan seluruh garis strip atas ungu kaku (`border-t-[3px] border-t-indigo-600`) dan gradien gelap pada kontainer halaman.
  - Memodernisasi *Header Section* menjadi kartu putih bersih (`bg-white rounded-2xl border border-slate-200/80 shadow-xs`) dengan lencana aksen biru dan tombol utama `+ Buat Agenda Cepat` royal blue (`#1E5CE6`).
  - Menyelaraskan seluruh 4 Kartu Metrik Ringkasan Bulanan (*Total Tugas*, *Selesai*, *Dalam Proses*, *Belum Mulai*) dengan tipografi angka dominan, visual status modern, dan elevasi halus.
  - Memperbarui matriks kalender dan panel agenda harian dengan penanda sel aktif (*selected cell*) bergaris batas biru royal (`#1E5CE6`), tombol navigasi bulan modern, dan tombol cepat *Hari Ini*.
- **Penyelarasan Desain Modul Catatan & Ide Kerja (`pages/notes.php`)**:
  - Memperbarui *Header Banner* dokumentasi dengan bingkai putih bersih, tipografi tegas, dan tombol aksi `+ Catatan Baru` royal blue (`#1E5CE6`).
  - Menyeragamkan sistem filter kategori (*Chip Filters*) dan input pencarian dengan *focus ring* royal blue.
  - Menyelaraskan palet warna aksen kartu catatan dan pemilih warna swatch modal ke tema royal blue `#1E5CE6`.
  - Memperbarui dialog modal tambah dan edit catatan dengan sudut membulat modern (`rounded-2xl`) dan tombol simpan bertema seragam.
- **Peningkatan Cache PWA**:
  - Memperbarui identitas cache Service Worker ke `taskflow-pwa-v3.3.2`.

---

## [3.3.1] - 2026-11-18

### 🖥️ Tata Letak 100% Fullscreen Edge-to-Edge & Harmonisasi Konten Dasbor
- **Tata Letak Penuh Tanpa Batas (*100% Edge-to-Edge Fullscreen*) (`index.php`)**:
  - Mengubah kerangka aplikasi dari model kotak tengah mengambang (*floating windowed*) menjadi tampilan penuh *100% screen width & height* tanpa margin luar.
  - Header utama membentang penuh di bagian atas (*full edge-to-edge width*) dengan pembatas halus (*subtle border-b*).
  - *Curved Royal Blue Sidebar* menempel langsung di tepi kiri layar dengan sudut kurva atas-kanan khas (*border-top-right-radius: 46px*) ala Goodle Drive.
  - Area kerja (*main workspace*) mengisi seluruh sisa layar secara optimal, memberikan ruang kerja yang luas dan leluasa bagi seluruh modul.
- **Harmonisasi Konten & Kartu Dasbor (`pages/dashboard.php`)**:
  - Memperbarui kartu ucapan (*Greeting Banner*) dengan latar putih bersih, tipografi royal blue, dan **Widget Cincin Progres Melingkar SVG (*Circular Progress Ring*)** yang presisi menampilkan persentase target hari ini serta rasio tugas tuntas.
  - Menyelaraskan seluruh 4 Kartu KPI Utama (*Stat Cards*) dengan tipografi angka dominan berukuran besar (*dominant numbers*), ikon halus, dan baris progres modern tanpa garis strip atas kaku.
  - Menyeragamkan seluruh tautan, tombol aksi cepat, dan pintasan sistem ke palet warna royal blue `#1E5CE6` dan `#1749B8`.
- **Perbaikan Stabilitas Service Worker & Pendaftaran Modal Global (`sw.js`, `index.php`)**:
  - Menambahkan pengecualian navigasi (`event.request.mode === 'navigate'`) pada `sw.js` agar Service Worker tidak mengintersepsi permintaan navigasi HTML/PHP, mencegah potensi respon galat 503.
  - Memastikan seluruh *fallback* pada `event.respondWith` selalu mengembalikan objek `Response` valid untuk meniadakan `TypeError: Failed to convert value to 'Response'`.
  - Mendaftarkan fungsi `openUploadModal` sejak awal di tag `<head>` pada `index.php` untuk mencegah galat `ReferenceError: openUploadModal is not defined` saat tombol diakses instan.
  - Memperbarui identitas cache Service Worker ke `taskflow-pwa-v3.3.1`.

---

## [3.3.0] - 2026-11-18

### 💎 Desain Antarmuka Cloud Storage Generasi Baru (Goodle Drive UI Integration)
- **Implementasi Desain Antarmuka Goodle Drive Cloud Storage (`index.php`)**:
  - Mengadopsi arsitektur visual modern berbasis **Goodle Drive UI** (`https://github.com/LALUARIFTA/goodle_drive_cloud_storage_ui.git`):
    - Kanvas luar bergradasi *royal blue radial* (`#235EE7`, `#326FF5`, `#1A4EC9`) dengan *App Frame* putih mengambang bersudut kurva elegan (`rounded-[34px]`) dan elevasi bayangan mewah (`shadow-[0_32px_70px_-15px_rgba(10,29,77,0.45)]`).
    - *Top Header Bar* putih bersih: Logo geometris bersegi, kolom pencarian kapsul (*pill search*) terintegrasi Command Palette (`Ctrl + K`), lonceng notifikasi interaktif dengan riwayat audit log, tombol bantuan pintasan (`?`), roda gigi pengaturan, dan *profile pill* pengguna.
    - *Curved Royal Blue Sidebar*: Bilah sisi biru melengkung khas dengan *border-top-right-radius: 54px*, tombol utama kapsul putih `+ Buat Cepat`, dan panel metrik *STORAGE DETAILS* ganda (status kapasitas penyimpanan dan jumlah dokumen aktif dengan visual baris progres lembut).
- **Penyesuaian Penuh dengan Seluruh Menu Sistem TaskFlow Pro**:
  - Menu navigasi diselaraskan 100% dengan alur kerja yang ada:
    - **Dasbor** (`dashboard`)
    - **Kalender** (`calendar`)
    - **Catatan** (`notes`)
    - **Dokumen** (`documents`)
    - **Proyek (Kanban)** (`projects`) dengan lencana jumlah tugas aktif
    - **Aktivitas** (`activities`)
    - **Profil Saya** (`profile`)
    - Sub-menu Administrasi: **Pengguna** (`admin`), **Cadangan Data** (`backup`), **Optimasi Cache** (`cache`), **Pengaturan Sistem** (`settings`).
- **Pembaruan Modul Dokumen 3.0 (`pages/documents.php`)**:
  - Transformasi kartu metrik atas menjadi **Kartu Folder Tab Siluet Berlekuk S-Curve** (`.folder-card-wrapper`, `.folder-tab`, `.folder-tab-curve`, `.folder-body`):
    - Kartu 1 (Aktif): Royal Blue Folder Tab Dokumen Saya.
    - Kartu 2: Tab Folder Kapasitas Penyimpanan & Bar Kuota Terpakai.
    - Kartu 3: Tab Folder Kolaborasi & Dokumen Bersama.
    - Kartu 4: Kartu Pratinjau Dokumen Terkini (*Quick Doc Card*) dengan indikator modifikasi waktu nyata.
  - Header halaman dilengkapi tombol cepat unggah berkas kotak biru membulat (`w-10 h-10 rounded-[11px] bg-[#1E5CE6]`) dan pengalih tata letak Grid/Tabel.

---

## [3.2.11] - 2026-11-18

### 🧹 Kebersihan Konsol Pengembang (*Zero Warning Console*) & Analisis RUM Edge
- **Peredam Peringatan Tailwind CDN (*Console Warning Filter*)**:
  - Menambahkan penyaring peringatan proaktif sebelum pemuatan pustaka `cdn.tailwindcss.com` di `index.php` dan `login.php`.
  - Mengeliminasi peringatan bawaan `cdn.tailwindcss.com should not be used in production` dari jendela *DevTools Console* agar konsol tetap bersih dan mudah diaudit.
- **Pembaruan Service Worker v3.2.11 (`sw.js`)**:
  - Memperbarui identitas cache ke `taskflow-pwa-v3.2.11` dan memastikan pembaruan instan (*seamless lifecycle*).
- **Panduan Pengelolaan Injeksi Skrip Edge Cloudflare**:
  - Mengidentifikasi sumber rekaman `net::ERR_ADDRESS_INVALID` sebagai skrip analitik otomatis (*Cloudflare Web Analytics / RUM*) yang diinjeksi oleh jaringan Cloudflare pada domain produksi, bukan dari basis kode lokal.

---

## [3.2.10] - 2026-11-18

### 🛡️ Optimasi PWA & Pembaruan Service Worker Instan (Zero Stale Worker & Clean Ad-Blocker Handling)
- **Registrasi Service Worker Berversi (*Cache-Busting Registration*)**:
  - Memperbarui pendaftaran Service Worker di `index.php` menjadi `sw.js?v=<?php echo APP_VERSION; ?>` dengan pemanggilan eksplisit `reg.update()`.
  - Memaksa peramban (browser) segera memeriksa, mengunduh, dan mengaktifkan Service Worker terbaru tanpa tertahan di cache lokal peramban.
- **Arsitektur Service Worker Presisi (`sw.js`)**:
  - Service Worker sekarang berfokus penuh dan eksklusif pada caching aset statis lokal (`.svg`, `.png`, `.jpg`, `.css`, web fonts).
  - Melewati (*bypass*) seluruh permintaan pihak ketiga (seperti `static.cloudflareinsights.com/beacon.min.js`), mencegah kegagalan promise fetch ketika beacon Cloudflare diblokir oleh ekstensi Ad-Blocker (uBlock, Brave Shields, dll).
  - Melepaskan kendali penanganan rute PHP/HTML dinamis langsung ke peramban tanpa `event.respondWith()`, menjamin navigasi halaman dan autentikasi sesi selalu 100% mulus tanpa interupsi offline palsu.
  - Memperbarui identitas cache ke `taskflow-pwa-v3.2.10` disertai `self.skipWaiting()` dan `self.clients.claim()` untuk pengambilalihan instan.

---

## [3.2.9] - 2026-11-18

### 🛡️ Perbaikan Kritis & Stabilitas Sistem (Fix Fatal Error & CSRF Generator)
- **Mengatasi HTTP 503 & Fatal Error di Halaman Dokumen**:
  - Memperbaiki `Fatal error: Call to undefined function generateCSRF()` pada form pembersih berkas lama (*broken records cleaner*) dan form *bulk actions* di `pages/documents.php`.
  - Mendaftarkan fungsi helper `generateCSRF()` di `config.php` yang menjamin ketersediaan token CSRF sesi secara aman ke seluruh modul sistem.
  - Mengganti seluruh pemanggilan `generateCSRF()` di `pages/documents.php` dengan `$_SESSION['csrf_token']`.
  - Mengeliminasi penyebab `503 Service Unavailable` saat membuka halaman dokumen atau mengklik tombol unggah.

---

## [3.2.8] - 2026-11-18

### 🐛 Perbaikan Bug & Ketahanan Sistem (Service Worker & Modal Stability)
- **Perbaikan Service Worker PWA (`sw.js`)**:
  - Mengatasi error `Uncaught (in promise) TypeError: Failed to convert value to 'Response'` dan fetch rejection.
  - Membatasi penanganan fetch hanya untuk *same-origin* dan Google Fonts, sehingga tidak mencegat script analitik/beacon pihak ketiga (seperti Cloudflare Insights `static.cloudflareinsights.com/beacon.min.js`) yang diblokir oleh ad-blocker.
  - Menjamin pengembalian objek `Response` valid (HTTP 503 fallback) pada seluruh kegagalan jaringan atau kondisi offline, mencegah penolakan promise di peramban.
  - Memperbarui versi cache ke `taskflow-pwa-v3.2.8`.
- **Perbaikan Global Safe Modal Binding (`index.php` & `pages/documents.php`)**:
  - Mengatasi `ReferenceError: openUploadModal is not defined` dengan mendaftarkan fungsi modal secara global pada `window` di `index.php` dan di blok awal `pages/documents.php`.
  - Menambahkan dukungan navigasi otomatis dengan parameter `openUpload=1` atau hash `#uploadSection` saat tombol aksi cepat modal diklik dari halaman lain.

---

## [3.2.7] - 2026-11-08

### 💎 Desain & Antarmuka (Executive Glassmorphism Kalender Produktivitas)
- **Implementasi Efek Glassmorphism Menyeluruh pada Modul Kalender (`pages/calendar.php`)**:
  - Transformasi panel kanvas dan kartu metrik statistik ke sistem `cal-glass-panel` & `cal-glass-card` dengan *backdrop-blur* 16px, saturasi 180%, dan *specular edge highlight* halus.
  - Header kalender bergradasi permukaan lembut (*frosted glass canvas*) dengan aksen garis atas indigo (`border-t-[3px] border-t-indigo-600`).
  - Redesain navigasi kalender, selector bulan, tombol *Hari Ini*, dan *view switcher* menjadi kapsul kaca (*glass pills*) berbayang mikro.
  - Penyempurnaan seleksi sel tanggal aktif (*is-selected*) dengan ring indigo lembut dan elevasi transparan, menggantikan garis kaku sebelumnya.
  - Panel rincian agenda harian dipercantik dengan formulir input kaca (*frosted glass input*), tab filter interaktif dengan dukungan dark mode, dan kartu agenda beraksen status dinamis.

---

## [3.2.6] - 2026-11-08

### 🎨 Desain & UI/UX (Overhaul Progress Widget & Balanced Dashboard)
- **Redesain Menyeluruh Widget Progres Dashboard**:
  - Mengeliminasi lingkaran SVG sempit dengan teks terjepit, digantikan dengan kartu metrik linear modern (`Target Hari Ini` dengan rasio `X/Y` dan indikator persentase yang lega).
  - Penambahan progress bar linier hijau emerald yang halus (*silky micro progress bar*) dengan transisi dinamis.
  - Penyesuaian visual header dengan gradien lembut permukaan (*warm executive tint*) dan indikator status avatar aktif.
  - Penataan ulang konsistensi kartu metrik statistik (mengganti emoji tak konsisten `📝` dan `🔒` dengan indikator dot berwarna yang elegan dan rapi).

---

## [3.2.5] - 2026-11-08

### 🎨 Desain & Kerajinan UI (Polished Executive Header)
- **Penyempurnaan Proporsi & Visual Anchor Header Dashboard**:
  - Penambahan avatar pengguna sebagai jangkar visual identitas (*visual identity anchor*).
  - Aksen garis atas indigo (`border-t-[3px] border-t-indigo-600`) yang selaras sempurna dengan ritme kartu metrik dashboard.
  - Lencana tanggal beraksen indigo lembut (*soft indigo badge*) dengan tipografi yang lebih tegas dan rapi.
  - Peningkatan kontras tipografi dan penyempurnaan tombol aksi cepat `+ Buat Tugas Cepat`.

---

## [3.2.4] - 2026-11-08

### 🛡️ Pembersihan Anti-Slop (Purified Human Craft)
- **Eliminasi Total Pola AI-Slop pada Dashboard**:
  - Menghapus seluruh gradien gelap ungu-hitam, *radial blur orbs*, dan *geometric blueprint grid overlay* (mematuhi R-01 & R-07).
  - Mengganti banner gelap menjadi kartu eksekutif bersih (*clean crisp executive panel*) yang 100% selaras dengan sistem desain TaskFlow Pro (mematuhi C-3 & R-21).
  - Menghilangkan emoji dekoratif `👋` pada judul utama (mematuhi R-04 & R-36).
  - Menghilangkan teks nama bergradasi (*gradient text*) dan efek pendar cahaya (*drop-shadow glow* pada SVG).
  - Menghapus badge kapsul dengan *pulsing green dot* dan kartu metrik pengisi (*filler pills*).
  - Menyederhanakan tombol aksi utama menjadi tombol fungsional solid yang tenang dan percaya diri (mematuhi C-1 & C-2).

---

## [3.2.3] - 2026-11-08

### 🎨 Desain & UI/UX (Design Overhaul)
- **Executive SaaS Dashboard Hero Banner Redesign**:
  - Penambahan tekstur *ambient canvas* dengan pencahayaan radial *multi-layer* (indigo, fuchsia, emerald) dan *geometric blueprint grid pattern*.
  - Integrasi avatar pengguna berbingkai cincin gradasi dengan indikator status aktif (*online status pill*).
  - Lokalisasi kalender resmi berbahasa Indonesia (`Minggu, 08 November 2026`) menggantikan format standar bahasa Inggris.
  - Salam dinamis 4 waktu (*Selamat Pagi, Siang, Sore, Malam*) dengan nama pengguna bergradasi tipografi modern.
  - Widget metrik penyeimbang di area tengah (*Rasio Capaian* & *Agenda Tersisa*) untuk menghilangkan ruang kosong pada layar monitor lebar.
  - Cincin progres sirkular berbasis *frosted glass* dengan gradasi stroke SVG (`#34d399` ke `#38bdf8`) dan efek pendar halus (*drop-shadow glow*).
  - Penambahan tombol aksi cepat sekunder ke papan Kanban di samping tombol *+ Buat Tugas Cepat*.

---

## [3.2.2] - 2026-11-08

### ✨ Ditambahkan (Added)
- **Suite Micro-Interactions & Fluid Animations**:
  - Animasi transisi halus saat berpindah halaman (*page entrance fade & slide-up* dengan kurva cubic-bezier).
  - Modal pop animation (`.animate-modal-pop`) untuk seluruh dialog modal (Upload Dokumen, Berbagi Dokumen, Pratinjau Dokumen, Command Palette, dan Modal Lupa Kata Sandi).
  - Spring physics pada notifikasi toast (`.animate-toast-spring`) untuk umpan balik interaksi yang responsif dan luwes.
  - Efek pantul taktil (*check bounce*) saat interaksi pemilihan kotak centang (*checkbox*).
  - Respons tekan tombol (*tactile click feedback*) dengan skala interaktif `scale(0.97)` pada semua tombol aktif.
  - Efek melayang halus (*hover elevation & soft shadow*) pada kartu interaktif.

### 🛡️ Diperbaiki & Diperketat (Hardened)
- **Hardening Endpoint Streaming Berkas (`serve_file.php`)**:
  - Penanganan permintaan kosong atau akses langsung di peramban dengan pengalihan otomatis (*graceful redirect*) ke halaman Dokumen, mencegah tampilan error 403/400.
  - Pengalihan otomatis pengguna yang belum terotentikasi ke halaman masuk (*login page*) saat mengakses berkas melalui dokumen peramban.

---

## [3.2.1] - 2026-11-08

### 🎨 Disempurnakan (Refined)
- **Penyelarasan Istilah Tab Pengaturan**:
  - Menghapus penyebutan istilah *"Kop Surat"* pada menu Pengaturan Sistem dan mengubah label tab menjadi *"Identitas & Logo"*.
  - Menyederhanakan deskripsi banner dan kartu menjadi *"Identitas & Logo Perusahaan"* agar selaras dengan fungsi platform.
- **Optimasi Lingkungan Docker**:
  - Menambahkan berkas `.dockerignore` untuk meningkatkan kecepatan build container.
  - Memperbarui panduan pembaruan mandiri (*seamless update*) di dokumentasi instalasi.

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
