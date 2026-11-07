<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/settings.php - System Configuration & Official Letterhead (Kop Surat) Settings 2.0 (Consistent Full-Width Layout)
if (!isAdmin()) {
    die("Akses Ditolak.");
}

$success = '';
$error = '';
$active_tab = $_POST['active_tab'] ?? ($_GET['tab'] ?? 'kop');

// -------------------------------------------------------------
// AJAX Action: Test Telegram Bot Connection
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_telegram_bot') {
    header('Content-Type: application/json');
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Sesi kedaluwarsa atau token CSRF tidak valid.']);
        exit;
    }

    $token = trim($_POST['token'] ?? '');
    if (empty($token)) {
        $token = getSetting('telegram_bot_token', '');
    }

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Token bot Telegram masih kosong. Silakan masukkan token terlebih dahulu.']);
        exit;
    }

    $url = "https://api.telegram.org/bot{$token}/getMe";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['success' => false, 'message' => 'Gagal menghubungi server Telegram: ' . $curlErr]);
        exit;
    }

    $data = json_decode($response, true);
    if ($data && !empty($data['ok'])) {
        $username = $data['result']['username'] ?? 'Bot';
        $firstName = $data['result']['first_name'] ?? 'Bot';
        echo json_encode([
            'success' => true,
            'message' => "Bot Aktif (@{$username} - {$firstName})",
            'bot_username' => '@' . $username
        ]);
        exit;
    }

    $desc = $data['description'] ?? 'Token bot Telegram tidak valid atau ditolak oleh server.';
    echo json_encode(['success' => false, 'message' => $desc]);
    exit;
}

// -------------------------------------------------------------
// AJAX Action: Send Test SMTP Email
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_smtp_email') {
    header('Content-Type: application/json');
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Sesi kedaluwarsa atau token CSRF tidak valid.']);
        exit;
    }

    $recipient = trim($_POST['recipient'] ?? '');
    if (empty($recipient) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Alamat email penerima tidak valid.']);
        exit;
    }

    $appName = getSetting('app_name', 'TaskFlow Pro');
    $subject = "Uji Coba Konfigurasi SMTP - " . $appName;
    $message = "Halo Administrator,\n\nIni adalah email uji coba untuk memverifikasi handshake dan konfigurasi server SMTP di platform " . $appName . ".\n\nJika Anda membaca pesan ini, berarti sistem email notifikasi Anda telah beroperasi dengan sempurna.\n\nWaktu pengujian: " . date('d F Y, H:i:s') . " WIB.";

    $sent = sendEmail($recipient, $subject, $message);
    if ($sent) {
        echo json_encode(['success' => true, 'message' => "Email uji coba berhasil dikirim ke {$recipient}!"]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengirim email. Periksa host, port, kredensial SMTP, atau sertifikat TLS/SSL server Anda.']);
        exit;
    }
}

// -------------------------------------------------------------
// Standard Settings Save Handler
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
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
                'smtp_crypto'        => trim($_POST['smtp_crypto'] ?? 'tls'),
                'smtp_user'          => trim($_POST['smtp_user'] ?? ''),
                'smtp_pass'          => trim($_POST['smtp_pass'] ?? ''),
                'smtp_from'          => trim($_POST['smtp_from'] ?? ''),
                'notif_time'         => $_POST['notif_time'] ?? '08',
                'app_url'            => trim($_POST['app_url'] ?? ''),
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
                $success = "Pengaturan sistem dan identitas kop surat berhasil disimpan!";
            }
        } catch (Exception $e) {
            $error = "Gagal menyimpan pengaturan: " . $e->getMessage();
        }
    }
}

// Read current settings
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
$smtp_crypto = getSetting('smtp_crypto', 'tls');
$adminEmail = $_SESSION['email'] ?? 'admin@perusahaan.com';
?>

<div class="p-4 md:p-8 w-full space-y-6">
    
    <!-- Executive Compact Header -->
    <div class="rounded-3xl p-6 md:p-7 bg-white/76 backdrop-blur-xl border border-white/85 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Administrasi Platform
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Pengaturan Sistem</h1>
            <p class="text-slate-500 text-xs md:text-sm font-medium max-w-xl leading-relaxed">
                Konfigurasi identitas organisasi, kop surat, notifikasi telegram, dan parameter server email SMTP.
            </p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($success): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl font-bold text-xs flex items-center gap-3 shadow-xs animate-fade-in">
            <div class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl font-bold text-xs flex items-center gap-3 shadow-xs animate-fade-in">
            <div class="w-6 h-6 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation Bar -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar" role="tablist">
        <button type="button" onclick="switchTab('kop')" class="setting-tab px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 flex-shrink-0 <?php echo $active_tab === 'kop' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-white border border-slate-200/90 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 shadow-2xs'; ?>" data-tab="kop" role="tab">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-3"/></svg>
            <span>Kop Surat &amp; Logo</span>
        </button>
        <button type="button" onclick="switchTab('umum')" class="setting-tab px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 flex-shrink-0 <?php echo $active_tab === 'umum' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-white border border-slate-200/90 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 shadow-2xs'; ?>" data-tab="umum" role="tab">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <span>Umum</span>
        </button>
        <button type="button" onclick="switchTab('notif')" class="setting-tab px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 flex-shrink-0 <?php echo $active_tab === 'notif' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-white border border-slate-200/90 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 shadow-2xs'; ?>" data-tab="notif" role="tab">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span>Notifikasi</span>
        </button>
        <button type="button" onclick="switchTab('email')" class="setting-tab px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 flex-shrink-0 <?php echo $active_tab === 'email' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'bg-white border border-slate-200/90 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 shadow-2xs'; ?>" data-tab="email" role="tab">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span>Email SMTP</span>
        </button>
    </div>

    <!-- Main Settings Form Card (100% Full-Width Across All Tabs) -->
    <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/90 shadow-sm overflow-hidden min-h-[560px] flex flex-col justify-between">
        <form method="POST" action="index.php?page=settings" enctype="multipart/form-data" data-no-ajax="true" id="settingsForm" class="p-6 md:p-8 flex-1 flex flex-col justify-between">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="active_tab" id="activeTabInput" value="<?php echo htmlspecialchars($active_tab); ?>">
            <input type="hidden" name="delete_logo" id="deleteLogoInput" value="0">
            
            <div class="space-y-6 flex-1 flex flex-col justify-between">

                <!-- ============================================================== -->
                <!-- TAB 1: KOP SURAT & LOGO PERUSAHAAN                             -->
                <!-- ============================================================== -->
                <div id="pane-kop" class="tab-pane <?php echo $active_tab === 'kop' ? '' : 'hidden'; ?> space-y-6">
                    <div class="p-4 bg-indigo-50/70 border border-indigo-100 rounded-2xl flex items-start gap-3.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-3"/></svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-black text-indigo-950">Identitas Resmi &amp; Kop Surat</h4>
                            <p class="text-[11px] text-indigo-700 leading-relaxed font-medium mt-0.5">Informasi dan logo di bawah ini digunakan sebagai kop resmi pada seluruh dokumen cetak, laporan, dan header aplikasi.</p>
                        </div>
                    </div>

                    <!-- Logo Upload & Detail Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                        <!-- Logo Upload Column -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Logo Resmi Perusahaan</label>
                            <div class="p-5 border-2 border-dashed border-slate-200 rounded-2xl text-center bg-slate-50/80 flex flex-col items-center justify-center min-h-[190px] transition-all hover:border-indigo-400">
                                <?php if ($logo_file_exists): ?>
                                    <img src="<?php echo htmlspecialchars($company_logo); ?>?v=<?php echo time(); ?>" id="logoPreview" alt="Logo Perusahaan" class="max-h-24 max-w-full object-contain mb-3 drop-shadow-sm">
                                    <div id="logoPlaceholder" class="w-16 h-16 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center font-black text-2xl mb-2 hidden">🏢</div>
                                <?php else: ?>
                                    <div id="logoPlaceholder" class="w-16 h-16 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center font-black text-2xl mb-2 border border-indigo-200">🏢</div>
                                    <img id="logoPreview" src="" alt="Preview" class="max-h-24 max-w-full object-contain mb-3 hidden">
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-2 mt-2">
                                    <label class="cursor-pointer px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-extrabold shadow-sm shadow-indigo-600/30 transition-all active:scale-95 inline-flex items-center gap-1.5">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        <span>Pilih File Logo</span>
                                        <input type="file" name="company_logo" id="logoFileInput" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" onchange="previewLogo(this)">
                                    </label>
                                    <?php if ($logo_file_exists): ?>
                                        <button type="button" onclick="confirmRemoveLogo()" class="px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-extrabold transition-all border border-rose-200 inline-flex items-center gap-1" title="Hapus Logo">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            <span>Hapus</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[10px] text-slate-400 font-semibold mt-2.5 leading-tight">Format didukung: PNG transparan, JPG, WebP, SVG.<br>Rekomendasi ukuran: 512×512px.</span>
                            </div>
                        </div>

                        <!-- Company Details Form -->
                        <div class="md:col-span-2 space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Perusahaan / Instansi</label>
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="Contoh: PT. INOVASI TEKNOLOGI NUSANTARA"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Bidang Usaha / Tagline</label>
                                <input type="text" name="company_tagline" value="<?php echo htmlspecialchars($company_tagline); ?>" placeholder="Contoh: General Contractor &amp; Information Technology Services"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Alamat Lengkap Kantor / Workshop</label>
                                <textarea name="company_address" rows="2" placeholder="Contoh: Jl. Jenderal Sudirman No. 45, Gedung Menara Sentral Lt. 8, Jakarta Selatan 12190"
                                          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all leading-relaxed"><?php echo htmlspecialchars($company_address); ?></textarea>
                            </div>

                            <!-- Contact Rows (Clean 2-Column Split) -->
                            <div class="space-y-3 pt-1">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">No. Telepon / Hotline</label>
                                        <input type="text" name="company_phone" value="<?php echo htmlspecialchars($company_phone); ?>" placeholder="(021) 555-0199 / 0812-3456-7890"
                                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Email Resmi Perusahaan</label>
                                        <input type="email" name="company_email" value="<?php echo htmlspecialchars($company_email); ?>" placeholder="info@perusahaan.co.id"
                                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Website Resmi (URL)</label>
                                    <input type="text" name="company_website" value="<?php echo htmlspecialchars($company_website); ?>" placeholder="www.perusahaan.co.id"
                                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-medium text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 2: UMUM (Consistent Form Grid)                            -->
                <!-- ============================================================== -->
                <div id="pane-umum" class="tab-pane <?php echo $active_tab === 'umum' ? '' : 'hidden'; ?> space-y-6">
                    <div class="p-4 bg-indigo-50/70 border border-indigo-100 rounded-2xl flex items-start gap-3.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-black text-indigo-950">Pengaturan Dasar Aplikasi</h4>
                            <p class="text-[11px] text-indigo-700 leading-relaxed font-medium mt-0.5">Konfigurasi nama aplikasi publik, URL instalasi sistem, dan kebijakan pendaftaran akun mandiri.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Nama Aplikasi -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Aplikasi</label>
                            <input type="text" name="app_name" value="<?php echo htmlspecialchars($app_name); ?>" required
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                            <p class="mt-1.5 text-[11px] text-slate-400 font-medium leading-relaxed">
                                Tampil pada navigasi, header halaman login, tab peramban, dan footer email.
                            </p>
                        </div>

                        <!-- URL Situs -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">URL Situs (App URL)</label>
                            <input type="url" name="app_url" value="<?php echo htmlspecialchars(getSetting('app_url')); ?>" placeholder="Contoh: http://localhost/taskflow"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                            <p class="mt-1.5 text-[11px] text-slate-400 font-medium leading-relaxed">
                                Alamat dasar untuk tautan berbagi berkas publik dan callback webhook eksternal.
                            </p>
                        </div>
                    </div>

                    <!-- Pendaftaran Mandiri Card -->
                    <div class="pt-2">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Kebijakan Akun Pengguna</label>
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 hover:bg-slate-100/60 transition-colors flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0 border border-indigo-100">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                </div>
                                <div>
                                    <div class="text-xs font-extrabold text-slate-900">Izinkan Pendaftaran Pengguna Mandiri</div>
                                    <p class="text-[11px] text-slate-400 font-medium">Bila diaktifkan, pengunjung umum dapat mendaftar akun baru melalui form portal.</p>
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" name="allow_registration" value="1" <?php echo $allow_reg ? 'checked' : ''; ?> class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 3: NOTIFIKASI (Consistent Form Grid + Live Token Verifier) -->
                <!-- ============================================================== -->
                <div id="pane-notif" class="tab-pane <?php echo $active_tab === 'notif' ? '' : 'hidden'; ?> space-y-6">
                    <div class="p-4 bg-indigo-50/70 border border-indigo-100 rounded-2xl flex items-start gap-3.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-black text-indigo-950">Integrasi Notifikasi Telegram &amp; Jadwal Rekap</h4>
                            <p class="text-[11px] text-indigo-700 leading-relaxed font-medium mt-0.5">Konfigurasi token bot Telegram untuk pengiriman notifikasi instan serta jadwal rekapitulasi harian ke seluruh pengguna.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Bot Token Telegram -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Bot Token Telegram</label>
                                <a href="https://t.me/BotFather" target="_blank" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors">Buat Bot via @BotFather ↗</a>
                            </div>
                            <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <input type="password" name="telegram_bot_token" id="tgTokenInput" value="<?php echo htmlspecialchars($tg_token); ?>" placeholder="123456789:ABCDEF..."
                                       class="w-full bg-transparent text-xs font-mono font-bold text-slate-900 outline-none placeholder:text-slate-400 pr-10">
                                
                                <button type="button" onclick="toggleTgTokenVisibility()" class="absolute right-3 text-slate-400 hover:text-slate-700 transition-colors" title="Lihat/Sembunyikan Token">
                                    <svg id="tgEyeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-400 font-medium leading-relaxed">
                                Kunci API resmi bot Telegram untuk mengirimkan pengingat tugas otomatis.
                            </p>
                        </div>

                        <!-- Jadwal Pengiriman -->
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Jadwal Rekap Harian (WIB)</label>
                            <select name="notif_time" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all cursor-pointer">
                                <?php 
                                $currentTime = getSetting('notif_time', '08');
                                for ($i = 0; $i < 24; $i++): 
                                    $h = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                                ?>
                                    <option value="<?php echo $h; ?>" <?php echo $currentTime == $h ? 'selected' : ''; ?>>
                                        Pukul <?php echo $h; ?>:00 WIB <?php echo ($h == '08' ? '(Default Pagi)' : ''); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="text-[11px] text-slate-400 font-medium leading-relaxed">
                                Waktu rutin server mengirimkan ringkasan agenda kerja harian ke seluruh tim.
                            </p>
                        </div>
                    </div>

                    <!-- Bot Verification Action Box -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-indigo-600 flex items-center justify-center flex-shrink-0 shadow-2xs">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 2L2 8.66l10 2.66 2.66 10L22 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-800">Verifikasi Koneksi API Bot Telegram</div>
                                <div class="text-[11px] text-slate-400">Pastikan bot aktif dan siap merespons sebelum menyimpan token.</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span id="botStatusBadge" class="hidden text-xs font-bold px-3 py-1 rounded-xl border"></span>
                            <button type="button" onclick="testTelegramBot()" id="btnTestBot"
                                    class="px-4 py-2 rounded-xl bg-white hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all border border-slate-200 flex items-center gap-1.5 active:scale-95 shadow-2xs">
                                <svg id="botTestIcon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" class="text-indigo-600"><polyline points="20 6 9 17 4 12"/></svg>
                                <span id="botTestText">Tes Koneksi Bot</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- TAB 4: EMAIL SMTP (Consistent Form Grid + Encryption + Test)   -->
                <!-- ============================================================== -->
                <div id="pane-email" class="tab-pane <?php echo $active_tab === 'email' ? '' : 'hidden'; ?> space-y-6">
                    <div class="p-4 bg-indigo-50/70 border border-indigo-100 rounded-2xl flex items-start gap-3.5">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </div>
                        <div>
                            <h4 class="text-xs font-black text-indigo-950">Konfigurasi Pengiriman Email SMTP</h4>
                            <p class="text-[11px] text-indigo-700 leading-relaxed font-medium mt-0.5">Pengaturan server surat elektronik terenkripsi untuk notifikasi akun, tugas baru, dan layanan reset kata sandi.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- From Email -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">From Email (Nama Pengirim)</label>
                            <input type="email" name="smtp_from" value="<?php echo htmlspecialchars(getSetting('smtp_from')); ?>" placeholder="noreply@domain.com"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                            <p class="mt-1.5 text-[11px] text-slate-400 font-medium">Alamat surat resmi yang tertera sebagai pengirim notifikasi.</p>
                        </div>

                        <!-- Enkripsi Keamanan -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Enkripsi Keamanan (Security)</label>
                            <select name="smtp_crypto" id="smtpCryptoSelect" onchange="autoSyncPort(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all cursor-pointer">
                                <option value="tls" <?php echo $smtp_crypto === 'tls' ? 'selected' : ''; ?>>TLS (STARTTLS - Port 587, Rekomendasi)</option>
                                <option value="ssl" <?php echo $smtp_crypto === 'ssl' ? 'selected' : ''; ?>>SSL (SMTPS - Port 465)</option>
                                <option value="none" <?php echo $smtp_crypto === 'none' ? 'selected' : ''; ?>>None (Tanpa Enkripsi - Port 25)</option>
                            </select>
                            <p class="mt-1.5 text-[11px] text-slate-400 font-medium">Memilih opsi ini akan otomatis menyesuaikan port default.</p>
                        </div>

                        <!-- SMTP Host -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">SMTP Host Server</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host')); ?>" placeholder="smtp.gmail.com atau mail.perusahaan.com"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                        </div>

                        <!-- SMTP Port -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">SMTP Port</label>
                            <input type="text" name="smtp_port" id="smtpPortInput" value="<?php echo htmlspecialchars(getSetting('smtp_port', '587')); ?>" placeholder="587"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                        </div>

                        <!-- SMTP User -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">SMTP User / Akun Email</label>
                            <input type="text" name="smtp_user" value="<?php echo htmlspecialchars(getSetting('smtp_user')); ?>" placeholder="user@domain.com"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition-all">
                        </div>

                        <!-- SMTP Password -->
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">SMTP Password / App Password</label>
                            <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <input type="password" name="smtp_pass" id="smtpPassInput" value="<?php echo htmlspecialchars(getSetting('smtp_pass')); ?>" placeholder="••••••••"
                                       class="w-full bg-transparent text-xs font-mono font-bold text-slate-800 outline-none pr-8">
                                <button type="button" onclick="togglePassVisibility()" class="absolute right-3 text-slate-400 hover:text-slate-700 transition-colors">
                                    <svg id="smtpEyeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Kirim Email Uji Coba Action Card -->
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-indigo-600 flex items-center justify-center flex-shrink-0 shadow-2xs">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-800">Uji Coba Handshake Pengiriman Email</div>
                                <div class="text-[11px] text-slate-400">Kirim email percobaan untuk memverifikasi kredensial dan enkripsi server sebelum digunakan.</div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 pt-1">
                            <input type="email" id="testEmailRecipient" value="<?php echo htmlspecialchars($adminEmail); ?>" placeholder="email@tujuan-tes.com"
                                   class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
                            <button type="button" onclick="sendTestEmail()" id="btnSendTestEmail"
                                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5 active:scale-95 shadow-sm shadow-indigo-600/25 flex-shrink-0">
                                <svg id="emailTestIcon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                <span id="emailTestText">Kirim Email Uji Coba</span>
                            </button>
                        </div>
                        <span id="smtpStatusBadge" class="hidden text-xs font-bold px-3 py-1.5 rounded-xl border block w-fit"></span>
                    </div>
                </div>

                <!-- Global Action Footer Section -->
                <div class="pt-6 mt-auto border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="submit" name="save_settings" value="1" class="px-7 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs shadow-md shadow-indigo-600/30 transition-all active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 flex items-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>Simpan Semua Pengaturan</span>
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
        t.className = 'setting-tab px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 flex-shrink-0 bg-white border border-slate-200/90 text-slate-600 hover:text-indigo-600 hover:bg-slate-50 shadow-2xs';
    });
    
    const activeBtn = document.querySelector(`[data-tab="${tab}"]`);
    if (activeBtn) {
        activeBtn.className = 'setting-tab px-5 py-2.5 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center gap-2 flex-shrink-0 bg-indigo-600 text-white shadow-md shadow-indigo-600/30';
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

function confirmRemoveLogo() {
    if (confirm('Hapus logo perusahaan yang tersimpan dan kembali ke logo bawaan sistem?')) {
        document.getElementById('deleteLogoInput').value = '1';
        const preview = document.getElementById('logoPreview');
        const placeholder = document.getElementById('logoPlaceholder');
        if (preview) preview.classList.add('hidden');
        if (placeholder) placeholder.classList.remove('hidden');
        // Submit immediately to apply removal
        document.getElementById('settingsForm').submit();
    }
}

function togglePassVisibility() {
    const input = document.getElementById('smtpPassInput');
    const icon = document.getElementById('smtpEyeIcon');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        input.type = 'password';
        if (icon) icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

function toggleTgTokenVisibility() {
    const input = document.getElementById('tgTokenInput');
    const icon = document.getElementById('tgEyeIcon');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        input.type = 'password';
        if (icon) icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}

function autoSyncPort(crypto) {
    const portInput = document.getElementById('smtpPortInput');
    if (!portInput) return;
    if (crypto === 'ssl') {
        portInput.value = '465';
    } else if (crypto === 'none') {
        portInput.value = '25';
    } else {
        portInput.value = '587';
    }
}

// -------------------------------------------------------------
// Interactive Telegram Bot Verification
// -------------------------------------------------------------
function testTelegramBot() {
    const tokenInput = document.getElementById('tgTokenInput');
    const badge = document.getElementById('botStatusBadge');
    const btn = document.getElementById('btnTestBot');
    const textEl = document.getElementById('botTestText');
    const tokenVal = tokenInput ? tokenInput.value.trim() : '';

    if (!tokenVal) {
        badge.className = 'text-xs font-bold px-3 py-1 rounded-xl border bg-rose-50 text-rose-700 border-rose-200 block';
        badge.textContent = 'Token bot Telegram belum diisi!';
        badge.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    textEl.textContent = 'Menguji...';
    badge.className = 'text-xs font-bold px-3 py-1 rounded-xl border bg-slate-50 text-slate-600 border-slate-200 block';
    badge.textContent = 'Menghubungkan ke API Telegram...';
    badge.classList.remove('hidden');

    const formData = new FormData();
    formData.append('action', 'test_telegram_bot');
    formData.append('token', tokenVal);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

    fetch('index.php?page=settings', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        textEl.textContent = 'Tes Koneksi Bot';
        if (data.success) {
            badge.className = 'text-xs font-bold px-3 py-1 rounded-xl border bg-emerald-50 text-emerald-700 border-emerald-200 block';
            badge.textContent = '✓ ' + data.message;
        } else {
            badge.className = 'text-xs font-bold px-3 py-1 rounded-xl border bg-rose-50 text-rose-700 border-rose-200 block';
            badge.textContent = '✗ ' + (data.message || 'Token tidak valid');
        }
    })
    .catch(err => {
        btn.disabled = false;
        textEl.textContent = 'Tes Koneksi Bot';
        badge.className = 'text-xs font-bold px-3 py-1 rounded-xl border bg-rose-50 text-rose-700 border-rose-200 block';
        badge.textContent = '✗ Gagal menghubungi server: ' + err;
    });
}

// -------------------------------------------------------------
// Interactive SMTP Test Email Sending
// -------------------------------------------------------------
function sendTestEmail() {
    const emailInput = document.getElementById('testEmailRecipient');
    const badge = document.getElementById('smtpStatusBadge');
    const btn = document.getElementById('btnSendTestEmail');
    const textEl = document.getElementById('emailTestText');
    const recipient = emailInput ? emailInput.value.trim() : '';

    if (!recipient) {
        badge.className = 'text-xs font-bold px-3 py-1.5 rounded-xl border bg-rose-50 text-rose-700 border-rose-200 block w-fit';
        badge.textContent = 'Masukkan alamat email penerima tes!';
        badge.classList.remove('hidden');
        return;
    }

    btn.disabled = true;
    textEl.textContent = 'Mengirim...';
    badge.className = 'text-xs font-bold px-3 py-1.5 rounded-xl border bg-slate-50 text-slate-600 border-slate-200 block w-fit';
    badge.textContent = 'Menghubungkan ke SMTP Server...';
    badge.classList.remove('hidden');

    const formData = new FormData();
    formData.append('action', 'test_smtp_email');
    formData.append('recipient', recipient);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

    fetch('index.php?page=settings', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        textEl.textContent = 'Kirim Email Uji Coba';
        if (data.success) {
            badge.className = 'text-xs font-bold px-3 py-1.5 rounded-xl border bg-emerald-50 text-emerald-700 border-emerald-200 block w-fit';
            badge.textContent = '✓ ' + data.message;
        } else {
            badge.className = 'text-xs font-bold px-3 py-1.5 rounded-xl border bg-rose-50 text-rose-700 border-rose-200 block w-fit';
            badge.textContent = '✗ ' + (data.message || 'Gagal mengirim email tes');
        }
    })
    .catch(err => {
        btn.disabled = false;
        textEl.textContent = 'Kirim Email Uji Coba';
        badge.className = 'text-xs font-bold px-3 py-1.5 rounded-xl border bg-rose-50 text-rose-700 border-rose-200 block w-fit';
        badge.textContent = '✗ Kesalahan jaringan: ' + err;
    });
}
</script>