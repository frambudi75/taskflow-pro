<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/backup.php
if (!isAdmin()) {
    die("Akses Ditolak");
}

$message = '';

if (isset($_POST['run_backup'])) {
    if (!validateCSRF($_POST['csrf_token'])) {
        $message = "CSRF Token Invalid";
    } else {
        try {
            // Export SQL menggunakan PHP
            $tables = ['users', 'todos', 'notes', 'documents'];
            $sql_dump = "-- TaskFlow Pro Backup\n";
            $sql_dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT * FROM $table");
                $rows = $stmt->fetchAll();
                
                $sql_dump .= "-- Table: $table\n";
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $values = array_values($row);
                    $values = array_map(function($v) use ($pdo) {
                        return is_null($v) ? 'NULL' : $pdo->quote($v);
                    }, $values);
                    
                    $sql_dump .= "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql_dump .= "\n";
            }

            $filename = 'backup_' . date('Ymd_His') . '.sql';
            
            // Output sebagai download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $filename);
            echo $sql_dump;
            exit;
        } catch (Exception $e) {
            $message = "Gagal membuat backup: " . $e->getMessage();
        }
    }
}

if (isset($_POST['run_full_backup'])) {
    if (!validateCSRF($_POST['csrf_token'])) {
        $message = "CSRF Token Invalid";
    } else {
        try {
            $timestamp = date('Ymd_His');
            $zip_filename = 'backup_full_' . $timestamp . '.zip';
            $temp_dir = sys_get_temp_dir() . '/taskflow_backup_' . $timestamp;
            
            // Buat direktori temporary
            if (!mkdir($temp_dir, 0755, true)) {
                throw new Exception("Gagal membuat direktori temporary");
            }
            
            // Export SQL
            $tables = ['users', 'todos', 'notes', 'documents'];
            $sql_dump = "-- TaskFlow Pro Full Backup\n";
            $sql_dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT * FROM $table");
                $rows = $stmt->fetchAll();
                
                $sql_dump .= "-- Table: $table\n";
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $values = array_values($row);
                    $values = array_map(function($v) use ($pdo) {
                        return is_null($v) ? 'NULL' : $pdo->quote($v);
                    }, $values);
                    
                    $sql_dump .= "INSERT INTO $table (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql_dump .= "\n";
            }
            
            // Simpan SQL ke file temporary
            $sql_file = $temp_dir . '/database_backup.sql';
            file_put_contents($sql_file, $sql_dump);
            
            // Copy folder uploads jika ada
            $uploads_dir = __DIR__ . '/../uploads';
            if (is_dir($uploads_dir)) {
                $uploads_backup = $temp_dir . '/uploads';
                if (!mkdir($uploads_backup, 0755, true)) {
                    throw new Exception("Gagal membuat folder uploads backup");
                }
                
                // Copy recursive
                function copy_recursive($src, $dst) {
                    $dir = opendir($src);
                    @mkdir($dst);
                    while (false !== ($file = readdir($dir))) {
                        if (($file != '.') && ($file != '..')) {
                            if (is_dir($src . '/' . $file)) {
                                copy_recursive($src . '/' . $file, $dst . '/' . $file);
                            } else {
                                copy($src . '/' . $file, $dst . '/' . $file);
                            }
                        }
                    }
                    closedir($dir);
                }
                
                copy_recursive($uploads_dir, $uploads_backup);
            }
            
            // Copy file konfigurasi penting
            $config_files = ['config.php', 'database.sql', 'firebase-applet-config.json'];
            foreach ($config_files as $config_file) {
                $src = __DIR__ . '/../' . $config_file;
                if (file_exists($src)) {
                    copy($src, $temp_dir . '/' . $config_file);
                }
            }
            
            // Buat ZIP
            $zip = new ZipArchive();
            if ($zip->open($temp_dir . '/' . $zip_filename, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Gagal membuat file ZIP");
            }
            
            // Tambahkan file ke ZIP
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($temp_dir) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            
            $zip->close();
            
            // Output ZIP sebagai download
            $zip_path = $temp_dir . '/' . $zip_filename;
            if (file_exists($zip_path)) {
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename=' . $zip_filename);
                header('Content-Length: ' . filesize($zip_path));
                readfile($zip_path);
                
                // Cleanup
                function delete_recursive($dir) {
                    if (is_dir($dir)) {
                        $files = array_diff(scandir($dir), array('.', '..'));
                        foreach ($files as $file) {
                            (is_dir("$dir/$file")) ? delete_recursive("$dir/$file") : unlink("$dir/$file");
                        }
                        rmdir($dir);
                    }
                }
                
                delete_recursive($temp_dir);
                exit;
            } else {
                throw new Exception("File ZIP tidak ditemukan");
            }
            
        } catch (Exception $e) {
            $message = "Gagal membuat backup lengkap: " . $e->getMessage();
        }
    }
}
?>
<div class="p-4 md:p-8 w-full space-y-6">
    <!-- Executive Glassmorphic Header -->
    <div class="rounded-3xl p-6 md:p-7 bg-white/76 backdrop-blur-xl border border-white/85 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Pemulihan &amp; Backup
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Backup Sistem</h1>
            <p class="text-slate-500 text-xs md:text-sm font-medium">Ekspor data database dan berkas fisik ke format SQL atau ZIP terkompresi.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 text-red-700 dark:text-red-300 rounded-2xl text-sm font-bold">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Backup SQL -->
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-sm p-7 md:p-8 text-center flex flex-col">
            <div class="w-14 h-14 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-sm">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Backup Database (SQL)</h3>
            <p class="text-slate-500 dark:text-slate-400 mb-6 text-xs leading-relaxed">
                Ekspor semua struktur dan record data dari tabel Users, Todos, Notes, dan Documents ke berkas SQL murni.
            </p>
            
            <form method="POST" class="mt-auto">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="run_backup" class="w-full bg-slate-900 hover:bg-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 text-white py-3 rounded-xl font-bold text-sm transition-all shadow-sm flex items-center justify-center gap-2 active:scale-95">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Unduh File SQL
                </button>
            </form>
        </div>

        <!-- Backup ZIP Lengkap -->
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-sm p-7 md:p-8 text-center flex flex-col">
            <div class="w-14 h-14 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-sm">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Full Backup (ZIP)</h3>
            <p class="text-slate-500 dark:text-slate-400 mb-6 text-xs leading-relaxed">
                Ekspor basis data SQL, seluruh file fisik dokumen upload, serta file konfigurasi ke dalam arsip ZIP lengkap.
            </p>
            
            <form method="POST" class="mt-auto">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="run_full_backup" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-bold text-sm transition-all shadow-sm flex items-center justify-center gap-2 active:scale-95">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Unduh Arsip ZIP Lengkap
                </button>
            </form>
        </div>
    </div>

    <div class="mt-8 p-6 bg-white/60 dark:bg-slate-900/40 rounded-2xl border border-slate-200/80 dark:border-slate-800">
        <h4 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Informasi Backup &amp; Keamanan</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs text-slate-600 dark:text-slate-400">
            <div>
                <h5 class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-1.5">Backup Database (SQL)</h5>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Hanya tabel basis data dan struktur relasi</li>
                    <li>Menyimpan hash password terenkripsi</li>
                    <li>Ukuran file ringan &amp; cepat dibuat</li>
                </ul>
            </div>
            <div>
                <h5 class="text-xs font-bold text-slate-800 dark:text-slate-200 mb-1.5">Full Backup (ZIP)</h5>
                <ul class="space-y-1 list-disc list-inside">
                    <li>Database SQL + seluruh folder uploads</li>
                    <li>Menyimpan dokumen dan avatar pengguna</li>
                    <li>Dapat digunakan untuk pemulihan total server</li>
                </ul>
            </div>
        </div>
    </div>
</div>
