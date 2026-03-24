# 📝 TaskFlow Pro - Latest Changelog (Update Mar 2026)

Berikut adalah daftar pembaruan besar yang telah diimplementasikan untuk membuat **TaskFlow Pro** makin juara:

### 🚀 Fitur Baru: Sistem Chat Real-Time (Internal Messenger)
Aplikasi kini memiliki sistem pesan antar pengguna yang sangat lengkap dan fungsional.
- **WhatsApp Style UI**: Desain balon chat dengan wallpaper doodle (background pattern) dan mode gelap yang nyaman di mata.
- **Lampiran File & Gambar**: Bisa kirim dokumen, foto, atau gambar langsung di dalam chat dengan preview instan.
- **Read Receipts (Centang Biru)**: Ikon centang satu (terkirim) dan centang dua biru (sudah dibaca) persis aplikasi chat modern.
- **Pesan Terakhir di Sidebar**: Daftar kontak di sebelah kiri menampilkan potongan pesan terakhir dan waktu pengirimannya.
- **Forward (Teruskan) Pesan**: Bisa meneruskan pesan (teks/file) ke rekan kerja lain hanya dengan satu klik.
- **Auto-Link Parsing**: Link URL (seperti `https://...`) otomatis menjadi link biru yang bisa langsung diklik.
- **Manajemen Pesan**: Fitur hapus satu per satu pesan atau bersihkan seluruh riwayat obrolan (Clear History).
- **Badge Notifikasi Sidebar**: Angka merah di sidebar "Pesan" yang update otomatis saat ada pesan baru.

### 🔔 Sistem Notifikasi & Audio
- **Desktop Push Notifications**: Muncul popup notifikasi di pojok sistem operasi (Windows/Mac) walau tab sedang tidak aktif.
- **Notification Sound (Ding)**: Suara notifikasi yang lembut saat pesan baru masuk agar kamu langsung tahu tanpa melihat layar.
- **Daily Notification System**: Notifikasi harian (Email & Telegram) sisa tugas yang bisa diatur jam pengirimannya.

### 🎨 UI/UX & Performa
- **Optimasi Mobile**: Seluruh layout (termasuk Chat & Pengaturan) sudah dirombak total agar 100% responsif di HP/Tablet.
- **Sync Foto Profil**: Seluruh elemen chat (Sidebar, Header, Modal) sudah sinkron dengan foto profil user yang diunggah.
- **Performance Polling**: Sistem polling AJAX yang efisien untuk mengecek pesan baru tanpa membebani server secara berat.

### 🛠️ Keamanan & Database
- **Tabel `messages`**: Penambahan tabel baru di database untuk manajemen chat terpusat.
- **URL Encryption**: Menyiapkan pondasi enkripsi URL untuk keamanan dokumen sensitif (Tahap Awal).
- **Git Integration**: Seluruh perubahan sudah di-*push* ke branch utama (main) di GitHub.

---
**Status Saat Ini:** Seluruh fitur dalam kondisi **Stabil** dan **Ready for Production**.
