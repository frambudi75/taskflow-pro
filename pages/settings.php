<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
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
                'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
                'telegram_bot_token' => $_POST['telegram_bot_token'],
                'smtp_host'   => $_POST['smtp_host'] ?? '',
                'smtp_port'   => $_POST['smtp_port'] ?? '587',
                'smtp_user'   => $_POST['smtp_user'] ?? '',
                'smtp_pass'   => $_POST['smtp_pass'] ?? '',
                'smtp_from'   => $_POST['smtp_from'] ?? '',
                'notif_time'  => $_POST['notif_time'] ?? '08',
                'app_url'     => $_POST['app_url'] ?? '',
                'enable_chat' => isset($_POST['enable_chat']) ? '1' : '0'
            ];

            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
            foreach ($updates as $key => $value) {
                // Remove password update if empty
                if ($key === 'smtp_pass' && empty($value)) continue;
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
$tg_token = getSetting('telegram_bot_token', '');
?>

<div class="p-5 md:p-8 max-w-3xl mx-auto">
    <!-- HERO HEADER -->
    <div class="rounded-3xl p-7 md:p-10 mb-8 relative overflow-hidden"
         style="background: linear-gradient(135deg, #065f46 0%, #064e3b 100%)">
        <div class="absolute inset-0 opacity-10"
             style="background-image: url('data:image/svg+xml,%3Csvg width=\"20\" height=\"20\" viewBox=\"0 0 20 20\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.1\" fill-rule=\"evenodd\"%3E%3Ccircle cx=\"3\" cy=\"3\" r=\"3\"/%3E%3Ccircle cx=\"13\" cy=\"13\" r=\"3\"/%3E%3C/g%3E%3C/svg%3E');"></div>
        <div class="relative z-10">
            <div class="text-emerald-300 text-[10px] font-black uppercase tracking-widest mb-2 opacity-80">System Administration</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">⚙️ Pengaturan Sistem</h1>
            <p class="text-emerald-100/60 text-sm font-medium">Konfigurasi global untuk aplikasi TaskFlow Anda.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-slate-800 border border-emerald-100 dark:border-slate-700 text-emerald-700 dark:text-emerald-400 rounded-2xl font-bold text-sm flex items-center gap-3">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="flex items-center gap-2 mb-8 bg-slate-200/50 dark:bg-slate-800/80 p-1.5 rounded-2xl w-fit">
        <button onclick="switchTab('umum')" class="setting-tab px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all active-tab bg-emerald-600 text-white shadow-lg shadow-emerald-600/20" data-tab="umum">🏢 Umum</button>
        <button onclick="switchTab('notif')" class="setting-tab px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400" data-tab="notif">🤖 Notifikasi</button>
        <button onclick="switchTab('email')" class="setting-tab px-6 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400" data-tab="email">📧 Email</button>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-[2rem] border border-slate-100 dark:border-slate-800 shadow-sm overflow-hidden">
        <form method="POST" action="index.php?page=settings" class="p-8">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="space-y-8">
                <!-- Tab: Umum -->
                <div id="pane-umum" class="tab-pane space-y-8">
                    <!-- App Name -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">Nama Aplikasi</label>
                        <input type="text" name="app_name" value="<?php echo htmlspecialchars($app_name); ?>" required
                               class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl px-5 py-4 text-sm font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all outline-none">
                        <p class="mt-2 text-[11px] text-slate-400 px-1 italic">Nama ini akan muncul di sidebar dan tab browser.</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">URL Situs</label>
                        <input type="url" name="app_url" value="<?php echo htmlspecialchars(getSetting('app_url')); ?>" 
                               placeholder="Contoh: https://taskflow.overheat.my.id"
                               class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl px-5 py-4 text-sm font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all outline-none">
                    </div>

                    <!-- Feature Toggle -->
                    <div class="p-6 bg-slate-50 dark:bg-slate-800/30 rounded-3xl border border-slate-100 dark:border-slate-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-2xl flex items-center justify-center bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 1 1-7.6-10.6 8.38 8.38 0 0 1 3.8.9L22 4l-2 5z"/></svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black text-slate-900 dark:text-white">Fitur Chat Internal</h4>
                                    <p class="text-[11px] text-slate-400 font-medium tracking-tight">Aktifkan atau nonaktifkan fitur pesan antar user.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="enable_chat" class="sr-only peer" <?php echo getSetting('enable_chat', '1') === '1' ? 'checked' : ''; ?>>
                                <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600 rounded-full"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Tab: Notif -->
                <div id="pane-notif" class="tab-pane hidden space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Bot Token Telegram</label>
                        <input type="password" name="telegram_bot_token" value="<?php echo htmlspecialchars($tg_token); ?>" 
                                placeholder="Contoh: 123456789:ABCDEF..."
                                class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500/20 outline-none">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Jadwal Pengiriman (Jam)</label>
                        <select name="notif_time" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500/20 outline-none appearance-none">
                            <?php 
                            $currentTime = getSetting('notif_time', '08');
                            for ($i = 0; $i < 24; $i++): 
                                $h = str_pad($i, 2, '0', STR_PAD_LEFT);
                            ?>
                                <option value="<?php echo $h; ?>" <?php echo $currentTime == $h ? 'selected' : ''; ?>>
                                    Pukul <?php echo $h; ?>:00 WIB
                                </option>
                            <?php endfor; ?>
                        </select>
                        <div class="mt-3 flex items-start gap-2 bg-amber-50 dark:bg-slate-800 p-3 rounded-xl border border-amber-200 dark:border-slate-700">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" class="mt-0.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <p class="text-[10px] text-amber-800 dark:text-amber-400 leading-normal font-medium">Pesan dikirim otomatis setiap hari. Pastikan Cron Job aktif di aaPanel.</p>
                        </div>
                    </div>
                </div>

                <!-- Tab: Email -->
                <div id="pane-email" class="tab-pane hidden space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">From Email (Pengirim)</label>
                            <input type="email" name="smtp_from" value="<?php echo htmlspecialchars(getSetting('smtp_from')); ?>" placeholder="noreply@domain.com"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host')); ?>" placeholder="smtp.gmail.com"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">SMTP Port</label>
                            <input type="text" name="smtp_port" value="<?php echo htmlspecialchars(getSetting('smtp_port')); ?>" placeholder="587"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">SMTP User (Email)</label>
                            <input type="text" name="smtp_user" value="<?php echo htmlspecialchars(getSetting('smtp_user')); ?>"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500/20 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">SMTP Password</label>
                            <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars(getSetting('smtp_pass')); ?>"
                                    class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-emerald-500/20 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Footer Section -->
                <div class="pt-6 border-t border-slate-50 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="submit" name="save_settings" class="px-8 py-3.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-sm shadow-lg shadow-emerald-500/20 transition-all active:scale-95">
                        Simpan Semua Pengaturan
                    </button>
                </div>
            </div>
        </form>
    </div>

<script>
function switchTab(tab) {
    // Hide all panes
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    // Show target pane
    document.getElementById('pane-' + tab).classList.remove('hidden');
    
    // Update tab buttons style
    document.querySelectorAll('.setting-tab').forEach(t => {
        t.classList.remove('bg-emerald-600', 'text-white', 'shadow-lg', 'shadow-emerald-600/20');
        t.classList.add('text-slate-600', 'dark:text-slate-400');
    });
    
    const activeBtn = document.querySelector(`[data-tab="${tab}"]`);
    activeBtn.classList.remove('text-slate-600', 'dark:text-slate-400');
    activeBtn.classList.add('bg-emerald-600', 'text-white', 'shadow-lg', 'shadow-emerald-600/20');
}
</script>

    <!-- Info Card -->
    <div class="mt-8 p-6 bg-emerald-50 dark:bg-slate-800 border border-emerald-200 dark:border-slate-700 rounded-3xl">
        <div class="flex gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-slate-700 flex items-center justify-center flex-shrink-0 text-emerald-600 dark:text-emerald-400">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            </div>
            <div>
                <h5 class="text-sm font-black text-emerald-900 dark:text-emerald-400 mb-1">Tips Administrasi</h5>
                <p class="text-xs text-emerald-800 dark:text-slate-300 leading-relaxed font-semibold">
                    Gunakan pengaturan ini untuk mempersonalisasi platform. Nama aplikasi akan langsung terupdate di seluruh halaman setelah Anda menekan tombol simpan.
                </p>
            </div>
        </div>
    </div>
</div>
