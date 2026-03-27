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
<div class="p-10 max-w-4xl mx-auto">
    <header class="mb-12">
        <h2 class="text-4xl font-black text-stone-900 tracking-tight mb-2">Backup Sistem</h2>
        <p class="text-stone-500 font-medium">Ekspor data database dan file sistem ke format ZIP atau SQL.</p>
    </header>

    <?php if ($message): ?>
        <div class="mb-8 p-4 bg-red-50 border border-red-100 text-red-700 rounded-2xl text-sm font-bold">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Backup SQL -->
        <div class="bg-white rounded-[2.5rem] border border-stone-200 shadow-xl p-10 text-center">
            <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-3xl flex items-center justify-center mx-auto mb-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            </div>
            <h3 class="text-2xl font-black text-stone-900 mb-4">Backup Database</h3>
            <p class="text-stone-500 mb-10 max-w-md mx-auto text-sm">
                Ekspor semua data dari tabel Users, Todos, Notes, dan Documents ke dalam file SQL.
            </p>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="run_backup" class="bg-blue-600 text-white px-8 py-4 rounded-2xl font-black hover:bg-blue-700 transition-all shadow-xl shadow-blue-200 flex items-center gap-3 mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Unduh Backup SQL
                </button>
            </form>
        </div>

        <!-- Backup ZIP Lengkap -->
        <div class="bg-white rounded-[2.5rem] border border-stone-200 shadow-xl p-10 text-center">
            <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-3xl flex items-center justify-center mx-auto mb-8">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            </div>
            <h3 class="text-2xl font-black text-stone-900 mb-4">Backup Lengkap</h3>
            <p class="text-stone-500 mb-10 max-w-md mx-auto text-sm">
                Ekspor database SQL + semua file upload + konfigurasi sistem dalam format ZIP.
            </p>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <button type="submit" name="run_full_backup" class="bg-emerald-600 text-white px-8 py-4 rounded-2xl font-black hover:bg-emerald-700 transition-all shadow-xl shadow-emerald-200 flex items-center gap-3 mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Unduh Backup ZIP
                </button>
            </form>
        </div>
    </div>

    <div class="mt-10 p-6 bg-stone-100 rounded-2xl border border-stone-200">
        <h4 class="text-xs font-black text-stone-400 uppercase tracking-widest mb-3">Informasi Backup</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h5 class="text-sm font-bold text-stone-700 mb-2">Backup SQL</h5>
                <ul class="text-xs text-stone-500 space-y-1 list-disc list-inside">
                    <li>Hanya data database</li>
                    <li>File berisi hash password</li>
                    <li>Cepat dan ringan</li>
                </ul>
            </div>
            <div>
                <h5 class="text-sm font-bold text-stone-700 mb-2">Backup ZIP Lengkap</h5>
                <ul class="text-xs text-stone-500 space-y-1 list-disc list-inside">
                    <li>Database + file upload + config</li>
                    <li>File asli dokumen tersimpan</li>
                    <li>Ukuran lebih besar</li>
                </ul>
            </div>
        </div>
        <div class="mt-4 pt-4 border-t border-stone-200">
            <h5 class="text-xs font-black text-stone-400 uppercase tracking-widest mb-2">Keamanan</h5>
            <ul class="text-xs text-stone-500 space-y-1 list-disc list-inside">
                <li>Simpan file backup di tempat aman dan terenkripsi</li>
                <li>Hanya administrator yang memiliki akses ke fitur ini</li>
                <li>Backup ZIP berisi file sensitif, jaga kerahasiaannya</li>
            </ul>
        </div>
    </div>
</div>
