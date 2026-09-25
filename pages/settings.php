<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/settings.php - System Configuration & Official Letterhead (Kop Surat) Settings
if (!isAdmin()) {
    die("Akses Ditolak.");
}

$success = '';
$error = '';
$active_tab = $_POST['active_tab'] ?? ($_GET['tab'] ?? 'kop');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRF($_POST['csrf_token'])) {
        $error = "Sesi telah kedaluwarsa atau token keamanan tidak valid. Silakan coba lagi.";
    } else {
        try {
            $updates = [
                'app_name'           => trim($_POST['app_name'] ?? 'TaskFlow Pro'),
                'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
                'telegram_bot_token' => trim($_POST['telegram_bot_token'] ?? ''),
                'smtp_host'          => trim($_POST['smtp_host'] ?? ''),
                'smtp_port'          => trim($_POST['smtp_port'] ?? '587'),
                'smtp_user'          => trim($_POST['smtp_user'] ?? ''),
                'smtp_pass'          => trim($_POST['smtp_pass'] ?? ''),
                'smtp_from'          => trim($_POST['smtp_from'] ?? ''),
                'notif_time'         => $_POST['notif_time'] ?? '08',
                'app_url'            => trim($_POST['app_url'] ?? ''),
                'enable_chat'        => isset($_POST['enable_chat']) ? '1' : '0',
                'company_name'       => trim($_POST['company_name'] ?? ''),
                'company_tagline'    => trim($_POST['company_tagline'] ?? ''),
                'company_address'    => trim($_POST['company_address'] ?? ''),
                'company_phone'      => trim($_POST['company_phone'] ?? ''),
                'company_email'      => trim($_POST['company_email'] ?? ''),
                'company_website'    => trim($_POST['company_website'] ?? '')
            ];

            // Handle Delete Logo Request
            if (!empty($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
                $oldLogo = getSetting('company_logo');
                if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
                    @unlink(__DIR__ . '/../' . $oldLogo);
                }
                $updates['company_logo'] = '';
                $updates['app_logo'] = '';
            }

            // Handle Company Logo Upload
            if (!empty($_FILES['company_logo']['name'])) {
                if ($_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['company_logo']['tmp_name'];
                    $orig_name = $_FILES['company_logo']['name'];
                    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'])) {
                        $ext = 'png';
                    }

                    $upload_dir = __DIR__ . '/../uploads/settings';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0777, true);
                    }

                    $mime_type = 'image/png';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo) {
                            $mime_type = finfo_file($finfo, $tmp_name) ?: 'image/png';
                            finfo_close($finfo);
                        }
                    } elseif (function_exists('mime_content_type')) {
                        $mime_type = mime_content_type($tmp_name) ?: 'image/png';
                    }

                    $filename = 'company_logo_' . time() . '.' . $ext;
                    $full_target = $upload_dir . '/' . $filename;
                    $rel_target  = 'uploads/settings/' . $filename;

                    if ($ext === 'svg' || strpos($mime_type, 'svg') !== false) {
                        if (@move_uploaded_file($tmp_name, $full_target)) {
                            $updates['company_logo'] = $rel_target;
                            $updates['app_logo'] = $rel_target;
                        }
                    } else {
                        $opt = optimizeAndSaveFile($tmp_name, $full_target, $mime_type, 800, 95);
                        if (!empty($opt['success'])) {
                            $updates['company_logo'] = $rel_target;
                            $updates['app_logo'] = $rel_target;
                        } elseif (@move_uploaded_file($tmp_name, $full_target)) {
                            $updates['company_logo'] = $rel_target;
                            $updates['app_logo'] = $rel_target;
                        } else {
                            $error = "Gagal memproses file logo ke folder penyimpanan.";
                        }
                    }
                } else {
                    $error = "Gagal mengunggah file logo (Kode: " . $_FILES['company_logo']['error'] . ").";
                }
            }

            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
            foreach ($updates as $key => $value) {
                if ($key === 'smtp_pass' && empty($value)) continue;
                $stmt->execute([$key, $value, $value]);
            }

            logActivity("Memperbarui pengaturan sistem & kop surat", "settings");
            clearCache();
            if (empty($error)) {
                $success = "Pengaturan berhasil disimpan dan Kop Surat telah diperbarui!";
            }
        } catch (Exception $e) {
            $error = "Gagal menyimpan pengaturan: " . $e->getMessage();
        }
    }
}

$app_name = getSetting('app_name', 'TaskFlow Pro');
$company_name = getSetting('company_name', $app_name);
$company_tagline = getSetting('company_tagline', 'General Contractor & Information Technology Services');
$company_address = getSetting('company_address', 'Jl. Jenderal Sudirman No. 45, Gedung Menara Sentral Lt. 8, Jakarta Selatan 12190');
$company_phone = getSetting('company_phone', '(021) 555-0199 / 0812-3456-7890');
$company_email = getSetting('company_email', 'info@perusahaan.co.id');
$company_website = getSetting('company_website', 'www.perusahaan.co.id');
$company_logo = getSetting('company_logo', '');
if (empty($company_logo)) {
    $company_logo = getSetting('app_logo', '');
}

$logo_file_exists = false;
if (!empty($company_logo)) {
    if (file_exists(__DIR__ . '/../' . $company_logo) || file_exists($company_logo)) {
        $logo_file_exists = true;
    }
}

$allow_reg = getSetting('allow_registration', '1') === '1';
$tg_token = getSetting('telegram_bot_token', '');
?>

<div class="p-5 md:p-8 max-w-4xl mx-auto">
    <!-- Hero Header -->
    <div class="rounded-3xl p-7 md:p-10 mb-8 relative overflow-hidden bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 text-white shadow-lg border border-slate-800">
        <div class="relative z-10">
            <div class="text-indigo-400 text-[10px] font-black uppercase tracking-widest mb-2">Administrasi Platform</div>
            <h1 class="text-2xl md:text-3xl font-black leading-tight mb-1">Pengaturan Sistem &amp; Kop Surat</h1>
            <p class="text-slate-300 text-xs md:text-sm font-medium">Konfigurasi identitas perusahaan, logo resmi, notifikasi, dan preferensi aplikasi.</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-50 dark:bg-slate-800 border border-emerald-200 dark:border-slate-700 text-emerald-800 dark:text-emerald-300 rounded-2xl font-bold text-xs flex items-center gap-3 shadow-sm animate-fade-in">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mb-6 p-4 bg-rose-50 dark:bg-slate-800 border border-rose-200 dark:border-slate-700 text-rose-800 dark:text-rose-300 rounded-2xl font-bold text-xs flex items-center gap-3 shadow-sm animate-fade-in">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="flex flex-wrap items-center gap-2 mb-6 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl w-fit" role="tablist">
        <button type="button" onclick="switchTab('kop')" class="setting-tab px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all <?php echo $active_tab === 'kop' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400'; ?> focus:outline-none" data-tab="kop" role="tab">🏢 Kop Surat &amp; Logo</button>
        <button type="button" onclick="switchTab('umum')" class="setting-tab px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all <?php echo $active_tab === 'umum' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400'; ?> focus:outline-none" data-tab="umum" role="tab">⚙️ Umum</button>
        <button type="button" onclick="switchTab('notif')" class="setting-tab px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all <?php echo $active_tab === 'notif' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400'; ?> focus:outline-none" data-tab="notif" role="tab">🔔 Notifikasi</button>
        <button type="button" onclick="switchTab('email')" class="setting-tab px-5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all <?php echo $active_tab === 'email' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400'; ?> focus:outline-none" data-tab="email" role="tab">✉️ Email SMTP</button>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <form method="POST" action="index.php?page=settings" enctype="multipart/form-data" novalidate class="p-6 md:p-8">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">
            <input type="hidden" name="delete_logo" id="deleteLogoInput" value="0">
            
            <div class="space-y-6">
                <!-- Tab: Kop Surat & Logo Perusahaan (Feature #1) -->
                <div id="pane-kop" class="tab-pane <?php echo $active_tab === 'kop' ? '' : 'hidden'; ?> space-y-6">
                    <div class="p-4 bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900 rounded-2xl flex items-start gap-3">
                        <span class="text-xl">🏢</span>
                        <div>
                            <h4 class="text-xs font-extrabold text-indigo-900 dark:text-indigo-300">Identitas Kop Surat Cetak Resmi</h4>
                            <p class="text-[11px] text-indigo-700 dark:text-indigo-400 leading-relaxed font-medium">Informasi dan logo di bawah ini akan otomatis muncul pada bagian atas (**Kop Surat**) semua lembar laporan harian kerja saat dicetak atau diunduh sebagai PDF.</p>
                        </div>
                    </div>

                    <!-- Logo Upload & Preview -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Logo Resmi Perusahaan</label>
                            <div class="p-4 border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl text-center bg-slate-50 dark:bg-slate-800/50 flex flex-col items-center justify-center min-h-[170px]">
                                <?php if ($logo_file_exists): ?>
                                    <img src="<?php echo htmlspecialchars($company_logo); ?>?v=<?php echo time(); ?>" id="logoPreview" alt="Logo Perusahaan" class="max-h-24 max-w-full object-contain mb-3 drop-shadow-sm">
                                    <div id="logoPlaceholder" class="w-16 h-16 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center font-black text-2xl mb-2 hidden">🏢</div>
                                <?php else: ?>
                                    <div id="logoPlaceholder" class="w-16 h-16 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center font-black text-2xl mb-2">🏢</div>
                                    <img id="logoPreview" src="" alt="Preview" class="max-h-24 max-w-full object-contain mb-3 hidden">
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-2 mt-1">
                                    <label class="cursor-pointer px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold shadow-sm transition-transform active:scale-95 inline-block">
                                        Pilih File Logo
                                        <input type="file" name="company_logo" id="logoFileInput" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" onchange="previewLogo(this)">
                                    </label>
                                    <?php if ($logo_file_exists): ?>
                                        <button type="button" onclick="removeLogo()" class="px-2.5 py-1.5 rounded-xl bg-rose-100 hover:bg-rose-200 text-rose-700 text-[11px] font-bold transition-all" title="Hapus Logo">
                                            Hapus
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[9px] text-slate-400 mt-2">PNG, JPG, WebP, SVG (Transparan disarankan)</span>
                            </div>
                        </div>

                        <div class="md:col-span-2 space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Perusahaan / Instansi</label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="Contoh: PT. INOVASI TEKNOLOGI NUSANTARA"
                                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Bidang Usaha / Tagline</label>
                                <input type="text" name="company_tagline" value="<?php echo htmlspecialchars($company_tagline); ?>" placeholder="Contoh: General Contractor &amp; Information Technology Services"
                                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Alamat Lengkap Kantor / Workshop</label>
                                <textarea name="company_address" rows="2" placeholder="Contoh: Jl. Jenderal Sudirman No. 45, Gedung Menara Sentral Lt. 8, Jakarta Selatan 12190"
                                          class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none"><?php echo htmlspecialchars($company_address); ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">No. Telepon / Hotline</label>
                                    <input type="text" name="company_phone" value="<?php echo htmlspecialchars($company_phone); ?>" placeholder="(021) 555-0199"
                                           class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Email Resmi</label>
                                    <input type="email" name="company_email" value="<?php echo htmlspecialchars($company_email); ?>" placeholder="info@perusahaan.co.id"
                                           class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Website</label>
                                    <input type="text" name="company_website" value="<?php echo htmlspecialchars($company_website); ?>" placeholder="www.perusahaan.co.id"
                                           class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-medium text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Umum -->
                <div id="pane-umum" class="tab-pane <?php echo $active_tab === 'umum' ? '' : 'hidden'; ?> space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Aplikasi</label>
                        <input type="text" name="app_name" value="<?php echo htmlspecialchars($app_name); ?>"
                               class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                        <p class="mt-1 text-[10px] text-slate-400">Nama ini akan muncul di sidebar dan tab browser.</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">URL Situs (Opsional)</label>
                        <input type="url" name="app_url" value="<?php echo htmlspecialchars(getSetting('app_url')); ?>" placeholder="Contoh: http://localhost/taskflow"
                               class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>

                    <div class="p-5 bg-slate-50 dark:bg-slate-800/60 rounded-2xl border border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-indigo-100 text-indigo-600">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 1 1-7.6-10.6 8.38 8.38 0 0 1 3.8.9L22 4l-2 5z"/></svg>
                            </div>
                            <div>
                                <h4 class="text-xs font-extrabold text-slate-900 dark:text-white">Fitur Chat Tim Internal</h4>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">Aktifkan atau nonaktifkan fitur kirim pesan antar pengguna.</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enable_chat" class="sr-only peer" <?php echo getSetting('enable_chat', '1') === '1' ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 rounded-full"></div>
                        </label>
                    </div>
                </div>

                <!-- Tab: Notif -->
                <div id="pane-notif" class="tab-pane <?php echo $active_tab === 'notif' ? '' : 'hidden'; ?> space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Bot Token Telegram</label>
                        <input type="password" name="telegram_bot_token" value="<?php echo htmlspecialchars($tg_token); ?>" placeholder="123456789:ABCDEF..."
                               class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-mono text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Jadwal Pengiriman Notifikasi (Jam)</label>
                        <select name="notif_time" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                            <?php 
                            $currentTime = getSetting('notif_time', '08');
                            for ($i = 0; $i < 24; $i++): 
                                $h = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                            ?>
                                <option value="<?php echo $h; ?>" <?php echo $currentTime == $h ? 'selected' : ''; ?>>
                                    Pukul <?php echo $h; ?>:00 WIB
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Tab: Email -->
                <div id="pane-email" class="tab-pane <?php echo $active_tab === 'email' ? '' : 'hidden'; ?> space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">From Email (Pengirim)</label>
                            <input type="email" name="smtp_from" value="<?php echo htmlspecialchars(getSetting('smtp_from')); ?>" placeholder="noreply@domain.com"
                                   class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host')); ?>" placeholder="smtp.gmail.com"
                                   class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">SMTP Port</label>
                            <input type="text" name="smtp_port" value="<?php echo htmlspecialchars(getSetting('smtp_port')); ?>" placeholder="587"
                                   class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">SMTP User</label>
                            <input type="text" name="smtp_user" value="<?php echo htmlspecialchars(getSetting('smtp_user')); ?>"
                                   class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">SMTP Password</label>
                            <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars(getSetting('smtp_pass')); ?>"
                                   class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>
                </div>

                <!-- Footer Section -->
                <div class="pt-5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                    <button type="submit" name="save_settings" value="1" class="px-7 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-sm transition-all active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 flex items-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan Semua Pengaturan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    const targetPane = document.getElementById('pane-' + tab);
    if (targetPane) targetPane.classList.remove('hidden');
    
    document.querySelectorAll('.setting-tab').forEach(t => {
        t.classList.remove('bg-indigo-600', 'text-white', 'shadow-sm');
        t.classList.add('text-slate-600', 'dark:text-slate-400');
    });
    
    const activeBtn = document.querySelector(`[data-tab="${tab}"]`);
    if (activeBtn) {
        activeBtn.classList.remove('text-slate-600', 'dark:text-slate-400');
        activeBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-sm');
    }

    const tabInput = document.getElementById('activeTabInput');
    if (tabInput) tabInput.value = tab;
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('logoPreview');
            const placeholder = document.getElementById('logoPlaceholder');
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            if (placeholder) placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }
}

function removeLogo() {
    if (confirm('Hapus logo perusahaan yang tersimpan?')) {
        document.getElementById('deleteLogoInput').value = '1';
        const preview = document.getElementById('logoPreview');
        const placeholder = document.getElementById('logoPlaceholder');
        if (preview) preview.classList.add('hidden');
        if (placeholder) placeholder.classList.remove('hidden');
    }
}
</script>
