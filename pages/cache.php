<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/cache.php - Cache Management Panel

if (!isAdmin()) {
    die("Akses Ditolak");
}

$message = '';

// Clear all cache
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_cache'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        clearCache();
        $message = "✓ Semua cache berhasil dihapus.";
    } else {
        $message = "CSRF Token Invalid";
    }
}

// Clear database query cache
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_db_cache'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        clearCacheByPattern('query_');
        $message = "✓ Cache query database berhasil dihapus.";
    } else {
        $message = "CSRF Token Invalid";
    }
}

// Get cache statistics
$cache_dir = __DIR__ . '/../cache';
$cache_files = file_exists($cache_dir) ? glob($cache_dir . '/*.cache') : [];
$cache_size = 0;
$cache_count = 0;
$cache_items = [];

foreach ($cache_files as $file) {
    $size = filesize($file);
    $cache_size += $size;
    $cache_count++;
    $cache_items[] = [
        'file' => basename($file),
        'size' => $size,
        'modified' => filemtime($file)
    ];
}

// Sort by modified date (newest first)
usort($cache_items, function($a, $b) {
    return $b['modified'] - $a['modified'];
});
?>

<div class="p-4 md:p-8 w-full space-y-6">
    <!-- Executive Glassmorphic Header -->
    <div class="rounded-3xl p-6 md:p-7 bg-white/76 backdrop-blur-xl border border-white/85 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Performa &amp; Memori
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Manajemen Cache</h1>
            <p class="text-slate-500 text-xs md:text-sm font-medium">Pantau file cache, status TTL, dan bersihkan memori sementara sistem.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-6 p-4 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 rounded-2xl text-sm font-bold">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Cache Controls -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-7">
        <!-- Clear All Cache -->
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-sm p-6 sm:p-7">
            <div class="flex items-center gap-4 mb-5">
                <div class="w-11 h-11 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-xl flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Hapus Semua Cache</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Bersihkan seluruh sistem cache sementara</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="clear_all_cache" value="1">
                <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 text-white py-2.5 rounded-xl font-bold text-xs transition-all active:scale-95 shadow-sm">
                    Bersihkan Semua Cache
                </button>
            </form>
        </div>

        <!-- Clear Database Cache -->
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-sm p-6 sm:p-7">
            <div class="flex items-center gap-4 mb-5">
                <div class="w-11 h-11 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 rounded-xl flex items-center justify-center">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Hapus Query Cache</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Hanya bersihkan cache query database</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="clear_db_cache" value="1">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-xl font-bold text-xs transition-all active:scale-95 shadow-sm">
                    Bersihkan Query Cache
                </button>
            </form>
        </div>
    </div>

    <!-- Cache Statistics -->
    <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 shadow-sm p-6 sm:p-8 mb-7">
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-5">Statistik Cache</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
            <!-- Total Cache Files -->
            <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700">
                <div class="text-2xl font-black text-slate-900 dark:text-white mb-1"><?php echo $cache_count; ?></div>
                <p class="text-slate-800 dark:text-slate-200 font-bold text-xs">File Aktif</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Jumlah file cache</p>
            </div>

            <!-- Cache Size -->
            <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700">
                <div class="text-2xl font-black text-slate-900 dark:text-white mb-1"><?php echo formatSize($cache_size); ?></div>
                <p class="text-slate-800 dark:text-slate-200 font-bold text-xs">Ukuran Cache</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Total memori digunakan</p>
            </div>

            <!-- Cache Enabled -->
            <div class="bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-700">
                <div class="text-base font-bold text-indigo-600 dark:text-indigo-400 mb-1">
                    <?php echo CACHE_ENABLED ? '✓ Aktif' : '✗ Nonaktif'; ?>
                </div>
                <p class="text-slate-800 dark:text-slate-200 font-bold text-xs">Status Sistem</p>
                <p class="text-[11px] text-slate-400 mt-0.5">TTL: <?php echo CACHE_TTL_DEFAULT / 60; ?> menit</p>
            </div>
        </div>

        <!-- Recent Cache Files -->
        <?php if (!empty($cache_items)): ?>
            <div class="pt-5 border-t border-slate-100 dark:border-slate-800">
                <h4 class="font-bold text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">File Cache Terbaru (25 paling baru):</h4>
                <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3 text-left font-bold text-slate-700 dark:text-slate-300">File</th>
                                <th class="px-5 py-3 text-left font-bold text-slate-700 dark:text-slate-300">Ukuran</th>
                                <th class="px-5 py-3 text-left font-bold text-slate-700 dark:text-slate-300">Dimodifikasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <?php foreach (array_slice($cache_items, 0, 25) as $item): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td class="px-5 py-3 text-slate-700 dark:text-slate-300 font-mono text-[11px]"><?php echo htmlspecialchars($item['file']); ?></td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-400"><?php echo formatSize($item['size']); ?></td>
                                    <td class="px-5 py-3 text-slate-600 dark:text-slate-400"><?php echo date('d/m/Y H:i:s', $item['modified']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="mt-4 p-5 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700 text-center text-slate-400 text-xs">
                Tidak ada file cache saat ini
            </div>
        <?php endif; ?>
    </div>

    <!-- Cache Info -->
    <div class="bg-white/60 dark:bg-slate-900/40 rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6">
        <h4 class="font-bold text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2.5">Arsitektur Caching</h4>
        <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-1.5 list-disc list-inside">
            <li><strong>Sistem Cache:</strong> File-based caching dengan serialisasi aman</li>
            <li><strong>TTL Default:</strong> <?php echo CACHE_TTL_DEFAULT / 60; ?> menit (disesuaikan otomatis per query)</li>
            <li><strong>Otomatis Cleanup:</strong> Cache kedaluwarsa dibersihkan saat diakses atau diperbarui</li>
        </ul>
    </div>
</div>
