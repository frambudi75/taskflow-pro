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
                'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
                'telegram_bot_token' => $_POST['telegram_bot_token'],
                'smtp_host'   => $_POST['smtp_host'] ?? '',
                'smtp_port'   => $_POST['smtp_port'] ?? '587',
                'smtp_user'   => $_POST['smtp_user'] ?? '',
                'smtp_pass'   => $_POST['smtp_pass'] ?? '',
                'smtp_from'   => $_POST['smtp_from'] ?? '',
                'notif_time'  => $_POST['notif_time'] ?? '08',
                'app_url'     => $_POST['app_url'] ?? ''
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

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">URL Situs (Penting untuk Notifikasi)</label>
                    <input type="url" name="app_url" value="<?php echo htmlspecialchars(getSetting('app_url')); ?>" 
                           placeholder="Contoh: https://taskflow.overheat.my.id"
                           class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 rounded-2xl px-5 py-4 text-sm font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all outline-none">
                    <p class="mt-2 text-[11px] text-slate-400 px-1 italic font-medium">Gunakan protokol lengkap (seperti <b>https://</b>) agar link di email & telegram bisa diklik.</p>
                </div>

                <!-- Telegram Config -->
                <div class="p-6 bg-slate-50 dark:bg-slate-800/30 rounded-3xl border border-slate-100 dark:border-slate-800">
                    <div class="mb-10">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center bg-sky-100 dark:bg-sky-900/30 text-sky-600">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L2 8.66l10 2.66 2.66 10L22 2z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900 dark:text-white">Telegram Integration</h4>
                                <p class="text-[11px] text-slate-400 font-medium tracking-tight">Kirim notifikasi sistem langsung ke bot Telegram.</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Bot Token</label>
                                <input type="password" name="telegram_bot_token" value="<?php echo htmlspecialchars($tg_token); ?>" 
                                       placeholder="Contoh: 123456789:ABCDEF..."
                                       class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-sky-500/20 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 px-1">Jadwal Pengiriman (Jam)</label>
                                <select name="notif_time" class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-sky-500/20 outline-none appearance-none">
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
                                <div class="mt-3 flex items-start gap-2 bg-amber-50/50 dark:bg-amber-900/10 p-3 rounded-xl border border-amber-100/50 dark:border-amber-800/50">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" class="mt-0.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <p class="text-[10px] text-amber-700 dark:text-amber-400 leading-normal">
                                        Pesan akan dikirim otomatis setiap hari pada jam yang dipilih. Pastikan Cron Job di aaPanel sudah aktif.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex items-start gap-2 bg-sky-50/50 dark:bg-sky-900/10 p-3 rounded-xl border border-sky-100/50 dark:border-sky-800/50">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2.5" class="mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    <p class="text-[10px] text-sky-700 dark:text-sky-400 leading-normal">
                                        Dapatkan token Anda dengan membuat bot di <a href="https://t.me/botfather" target="_blank" class="font-bold underline hover:text-sky-800">@BotFather</a>. Pastikan bot sudah di-<em>start</em> oleh user agar bisa menerima pesan.
                                    </p>
                                </div>
                        </div>
                    </div>

                    <!-- SMTP Config -->
                    <div class="mb-10">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900 dark:text-white">Email (SMTP)</h4>
                                <p class="text-[11px] text-slate-400 font-medium tracking-tight">Konfigurasi Pengiriman Email</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">SMTP Host</label>
                                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host')); ?>" placeholder="smtp.gmail.com"
                                       class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">SMTP Port</label>
                                <input type="text" name="smtp_port" value="<?php echo htmlspecialchars(getSetting('smtp_port')); ?>" placeholder="587"
                                       class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">SMTP User (Email)</label>
                                <input type="text" name="smtp_user" value="<?php echo htmlspecialchars(getSetting('smtp_user')); ?>"
                                       class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">SMTP Password</label>
                                <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars(getSetting('smtp_pass')); ?>"
                                       class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">From Email (Pengirim)</label>
                                <input type="email" name="smtp_from" value="<?php echo htmlspecialchars(getSetting('smtp_from')); ?>" placeholder="noreply@domain.com"
                                       class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 outline-none">
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="<?php echo route('test-mail'); ?>" target="_blank"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-50 text-indigo-600 text-[11px] font-bold hover:bg-indigo-100 transition-all border border-indigo-100">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                                Test Kirim Email Sekarang
                            </a>
                        </div>
                    </div>
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
