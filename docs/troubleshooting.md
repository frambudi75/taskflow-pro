# Troubleshooting & FAQ

## Masalah Umum

### ❌ "Email atau password salah"
```
Cause: Database kosong / hash salah
Fix: 
1. phpMyAdmin → Check users table
2. Import ulang database.sql
3. Default: admin@taskflow.com/admin123
```

### ❌ "Koneksi Database Gagal"
```
config.php error
Fix:
1. XAMPP MySQL running?
2. DB taskflow_db exists?
3. root password kosong? ($pass = '')
```

### ❌ Halaman putih (White Screen)
```
PHP error
Fix:
.htaccess atau php.ini:
display_errors = On
error_reporting = E_ALL
```

### ❌ File upload gagal
```
uploads/ permission
Fix: chmod 777 uploads/
Max file: php.ini upload_max_filesize = 10M
```

### ❌ Slow loading
```
Cache full / DB tidak optimized
Fix:
?page=cache → Clear all
php auto_optimize.php
```

## Error Logs
```
logs/error.log - PHP errors
logs/optimize.log - DB maintenance
logs/backup.log - Backup history
```

## Performance Tips
```
1. Enable cache (?page=cache)
2. Weekly: php auto_optimize.php
3. Index tables regularly
4. Compress images before upload
5. Use SSD storage
```

## Browser Issues
| Browser | Issue | Fix |
|---------|-------|-----|
| Safari iOS | Scroll lag | `-webkit-overflow-scrolling: touch` |
| Chrome Android | Gradient glitch | Use solid fallback |
| Firefox Mobile | Flex gaps | `gap-4 md:gap-6` |

## Backup Recovery
```
1. Restore SQL via phpMyAdmin
2. Extract ZIP → copy uploads/
3. Clear cache (?page=cache)
4. Test login
```

**Kembali ke:** [Home](/docs/index.md)
