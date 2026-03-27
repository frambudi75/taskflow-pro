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

<div class="p-10 max-w-7xl mx-auto">
    <header class="mb-12">
        <h2 class="text-4xl font-black text-stone-900 tracking-tight mb-2">Cache Management</h2>
        <p class="text-stone-500 font-medium">Kelola cache sistem untuk performa optimal.</p>
    </header>

    <?php if ($message): ?>
        <div class="mb-8 p-4 bg-blue-50 border border-blue-100 text-blue-700 rounded-2xl text-sm font-bold">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Cache Controls -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        <!-- Clear All Cache -->
        <div class="bg-white rounded-[2.5rem] border border-stone-200 shadow-xl p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </div>
                <div>
                    <h3 class="text-lg font-black text-stone-900">Hapus Semua Cache</h3>
                    <p class="text-xs text-stone-500">Bersihkan seluruh sistem cache</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="clear_all_cache" value="1">
                <button type="submit" class="w-full bg-red-600 text-white py-3 rounded-2xl font-bold hover:bg-red-700 transition-all">
                    Clear All Cache
                </button>
            </form>
        </div>

        <!-- Clear Database Cache -->
        <div class="bg-white rounded-[2.5rem] border border-stone-200 shadow-xl p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div>
                    <h3 class="text-lg font-black text-stone-900">Hapus Query Cache</h3>
                    <p class="text-xs text-stone-500">Cache query database saja</p>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="clear_db_cache" value="1">
                <button type="submit" class="w-full bg-amber-600 text-white py-3 rounded-2xl font-bold hover:bg-amber-700 transition-all">
                    Clear Query Cache
                </button>
            </form>
        </div>
    </div>

    <!-- Cache Statistics -->
    <div class="bg-gradient-to-br from-stone-50 to-stone-100 rounded-[2.5rem] border border-stone-200 shadow-xl p-10 mb-12">
        <h3 class="text-2xl font-black text-stone-900 mb-8">Statistik Cache</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Cache Files -->
            <div class="bg-white rounded-2xl p-6 border border-stone-100">
                <div class="text-4xl font-black text-blue-600 mb-2"><?php echo $cache_count; ?></div>
                <p class="text-stone-600 font-bold">Cache Files</p>
                <p class="text-xs text-stone-500 mt-2">File cache aktif</p>
            </div>

            <!-- Cache Size -->
            <div class="bg-white rounded-2xl p-6 border border-stone-100">
                <div class="text-4xl font-black text-green-600 mb-2"><?php echo formatSize($cache_size); ?></div>
                <p class="text-stone-600 font-bold">Ukuran Cache</p>
                <p class="text-xs text-stone-500 mt-2">Total memori cache</p>
            </div>

            <!-- Cache Enabled -->
            <div class="bg-white rounded-2xl p-6 border border-stone-100">
                <div class="text-lg font-bold text-emerald-600 mb-2">
                    <?php echo CACHE_ENABLED ? '✓ Aktif' : '✗ Nonaktif'; ?>
                </div>
                <p class="text-stone-600 font-bold">Status Cache</p>
                <p class="text-xs text-stone-500 mt-2">TTL: <?php echo CACHE_TTL_DEFAULT / 60; ?> menit</p>
            </div>
        </div>

        <!-- Recent Cache Files -->
        <?php if (!empty($cache_items)): ?>
            <div class="mt-8 pt-8 border-t border-stone-200">
                <h4 class="font-bold text-stone-900 mb-4">File Cache Terbaru (25 paling baru):</h4>
                <div class="bg-white rounded-2xl overflow-hidden border border-stone-100">
                    <table class="w-full text-sm">
                        <thead class="bg-stone-50 border-b border-stone-100">
                            <tr>
                                <th class="px-6 py-3 text-left font-bold text-stone-900">File</th>
                                <th class="px-6 py-3 text-left font-bold text-stone-900">Ukuran</th>
                                <th class="px-6 py-3 text-left font-bold text-stone-900">Dimodifikasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            <?php foreach (array_slice($cache_items, 0, 25) as $item): ?>
                                <tr class="hover:bg-stone-50">
                                    <td class="px-6 py-3 text-stone-600 font-mono text-xs"><?php echo htmlspecialchars($item['file']); ?></td>
                                    <td class="px-6 py-3 text-stone-600"><?php echo formatSize($item['size']); ?></td>
                                    <td class="px-6 py-3 text-stone-600"><?php echo date('d/m/Y H:i:s', $item['modified']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="mt-8 p-6 bg-white rounded-2xl border border-stone-100 text-center text-stone-500">
                Tidak ada file cache saat ini
            </div>
        <?php endif; ?>
    </div>

    <!-- Cache Info -->
    <div class="bg-blue-50 rounded-[2.5rem] border border-blue-200 p-8">
        <h4 class="font-black text-blue-900 mb-4">ℹ️ Informasi Cache</h4>
        <ul class="text-sm text-blue-900 space-y-2">
            <li><strong>Sistem Cache:</strong> File-based caching dengan serialisasi</li>
            <li><strong>TTL Default:</strong> <?php echo CACHE_TTL_DEFAULT / 60; ?> menit (dapat disesuaikan per query)</li>
            <li><strong>Lokasi Cache:</strong> <code class="bg-blue-100 px-2 py-1 rounded">cache/</code></li>
            <li><strong>Otomatis Cleanup:</strong> Cache expired dihapus saat diakses atau diperbarui</li>
            <li><strong>Penggunaan:</strong> Dashboard, statistik, dan query frequently-accessed dioptimalkan dengan cache</li>
        </ul>
    </div>
</div>
