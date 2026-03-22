# Panduan Panel Admin

## Akses Admin
```
Login admin@taskflow.com/admin123
Sidebar → ⚙️ Administrasi
```

## Manajemen User (?page=admin)

### Tambah User
```
1. Fill: Nama, Email, Password, Role
2. Click "Daftarkan User"
3. Password hashed automatically
```

### Reset Password User
```
1. Click ikon reset (🗝️) di table
2. Modal: Input password baru
3. Submit → Password diupdate
```

### Hapus User
```
1. Click ikon trash (🗑️)
2. Confirm dialog
3. CASCADE delete todos/notes/docs
```

## Backup System (?page=backup)

### Jenis Backup
| Tipe | Format | Isi | Ukuran |
|------|--------|-----|--------|
| SQL | .sql | Database | ~50KB |
| Full | .zip | Files + DB | ~10MB+ |

### Auto Backup Schedule
```bash
# cron job (Linux)
0 3 * * 0 php backup.sh

# Windows Task Scheduler
php C:\xampp\htdocs\taskflow\backup.sh
```

## Cache Management (?page=cache)

### Cache Stats
```
Total Files: N
Total Size: XX MB
Hit Rate: XX%
Last Clear: DATE
```

### Clear Options
- **All Cache** - Hapus semua
- **Query Cache** - Hanya DB queries
- **Session Cache** - User sessions

## Optimasi Database
```
Command: php auto_optimize.php
Cron: 0 2 * * * php auto_optimize.php
Log: logs/optimize.log
```

## Security Best Practices
```
✅ CSRF tokens active
✅ Password hash bcrypt
✅ File upload validated
✅ SQL prepared statements
✅ Session secure
```

**Maintenance:** [Optimasi](/docs/maintenance.md)
**Troubleshooting:** [Masalah Umum](/docs/troubleshooting.md)
