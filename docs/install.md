# Instalasi TaskFlow Pro

## Persyaratan Sistem

| Komponen | Minimum | Dianjurkan |
|----------|---------|------------|
| PHP | 7.4 | 8.1+ |
| MySQL | 5.7 | 8.0+ |
| Web Server | Apache 2.4 | Nginx 1.20+ |
| Memory | 128MB | 512MB+ |
| Disk | 50MB | 1GB+ |

## Langkah Instalasi

### 1. Download & Extract
```
1. Download ZIP proyek
2. Extract ke folder web server (htdocs/taskflow)
3. Pastikan permissions: 755 folders, 644 files
```

### 2. Setup XAMPP (Recommended)
```
1. Download XAMPP → Install
2. Start Apache + MySQL dari XAMPP Control Panel
3. Verifikasi: http://localhost/phpmyadmin
```

### 3. Buat Database
```sql
-- phpMyAdmin atau MySQL CLI
CREATE DATABASE taskflow_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Import Schema
```
phpMyAdmin → Import → database.sql
atau CLI:
mysql -u root -p taskflow_db < database.sql
```

### 5. Konfigurasi (config.php)
```php
$host = 'localhost';
$db   = 'taskflow_db'; 
$user = 'root';
$pass = ''; // Kosong untuk XAMPP default
```

### 6. Test Instalasi
```
URL: http://localhost/taskflow/login.php
Email: admin@taskflow.com
Password: admin123

Ganti password setelah login pertama!
```

## Struktur Folder
```
taskflow/
├── docs/           # Dokumentasi
├── pages/          # Halaman aplikasi
├── uploads/        # Files user (chmod 777)
├── cache/          # Cache files
├── logs/           # Maintenance logs
├── config.php      # Konfigurasi
├── database.sql    # Schema DB
├── index.php       # Main app
└── README.md       # GitHub
```

## Verifikasi Instalasi
```
✅ Login berhasil
✅ Dashboard load
✅ Sidebar responsive
✅ Admin panel accessible
✅ File upload works
✅ Database backup OK
```

**Troubleshooting?** [Lihat Troubleshooting](/docs/troubleshooting.md)

**Selanjutnya:** [Penggunaan Dasar](/docs/usage.md)
