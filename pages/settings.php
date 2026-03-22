<?php
// pages/settings.php - System Configuration
if (!isAdmin()) {
    die("Akses Ditolak.");
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        try {
            $updates = [
                'app_name' => $_POST['app_name'],
                'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0'
            ];

            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
            foreach ($updates as $key => $value) {
                $stmt->execute([$key, $value, $value]);
            }

            logActivity("Memperbarui pengaturan sistem", "settings");
            clearCache(); // Clear stats/cache
            $success = "Pengaturan berhasil disimpan!";
        } catch (Exception $e) {
            $error = "Gagal menyimpan: " . $e->getMessage();
        }
    }
}

$app_name = getSetting('app_name', 'TaskFlow Pro');
$allow_reg = getSetting('allow_registration', '1') === '1';
?>

<div class="p-5 md:p-8 max-w-3xl mx-auto">
    <!-- HERO HEADER -->
    <div class="rounded-3xl p-7 md:p-10 mb-8 relative overflow-hidden"
         style="background: linear-gradient(135deg,#334155 0%,#0f172a 100%)">
        <div class="absolute inset-0 opacity-20"
             style="background-image: url('data:image/svg+xml,%3Csvg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%239C92AC\" fill-opacity=\"0.2\" fill-rule=\"evenodd\"%3E%3Ccircle cx=\"3\" cy=\"3\" r=\"3\"/%3E%3Ccircle cx=\"13\" cy=\"13\" r=\"3\"/%3E%3C/g%3E%3C/svg%3E');"></div>
        <div class="relative z-10">
            <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2">System Administration</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">⚙️ Pengaturan Sistem</h1>
            <p class="text-slate-400 text-sm font-medium">Konfigurasi global untuk aplikasi TaskFlow Anda.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400 rounded-2xl font-bold text-sm flex items-center gap-3">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-100 dark:border-slate-800 shadow-sm overflow-hidden">
        <form method="POST" action="index.php?page=settings" class="p-8">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="space-y-8">
                <!-- App Name -->
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">Nama Aplikasi</label>
                    <input type="text" name="app_name" value="<?php echo htmlspecialchars($app_name); ?>" required
                           class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl px-5 py-4 text-sm font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none">
                    <p class="mt-2 text-[11px] text-slate-400 px-1 italic">Nama ini akan muncul di sidebar dan tab browser.</p>
                </div>

                <!-- Registration Toggle -->
                <div class="flex items-center justify-between p-5 bg-slate-50 dark:bg-slate-800/30 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <div>
                        <h4 class="text-sm font-black text-slate-900 dark:text-white mb-1">Izinkan Registrasi</h4>
                        <p class="text-[11px] text-slate-400 font-medium">Buka akses bagi pengguna baru untuk mendaftar akun.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="allow_registration" class="sr-only peer" <?php echo $allow_reg ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <!-- Footer Section -->
                <div class="pt-6 border-t border-slate-50 dark:border-slate-800 flex items-center justify-end gap-4">
                    <button type="reset" class="px-6 py-3 rounded-xl text-sm font-black text-slate-400 hover:text-slate-600 transition-colors">Reset</button>
                    <button type="submit" name="save_settings" class="px-8 py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-black text-sm shadow-lg shadow-indigo-500/20 transition-all active:scale-95">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Info Card -->
    <div class="mt-8 p-6 bg-blue-50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800 rounded-3xl">
        <div class="flex gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0 text-blue-600">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </div>
            <div>
                <h5 class="text-sm font-black text-blue-900 dark:text-blue-300 mb-1">Tips Administrasi</h5>
                <p class="text-xs text-blue-700 dark:text-blue-400/70 leading-relaxed font-medium">
                    Gunakan pengaturan ini untuk mempersonalisasi platform. Nama aplikasi akan langsung terupdate di seluruh halaman setelah Anda menekan tombol simpan.
                </p>
            </div>
        </div>
    </div>
</div>
