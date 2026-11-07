<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/profile.php - Profil & Keamanan 2.0 (Executive Master-Detail Split & Tabbed Settings)
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
$active_tab = $_POST['active_tab'] ?? ($_GET['tab'] ?? 'profile');

// Ensure profile_photo column exists safely
try {
    $check = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_photo'");
    $check->execute();
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER role");
    }
} catch (Exception $e) {}

// Fetch user data with aggregated storage usage
$stmt = $pdo->prepare("
    SELECT 
        u.*, 
        COALESCE(SUM(d.file_size), 0) AS used_bytes 
    FROM users u 
    LEFT JOIN documents d ON d.user_id = u.id 
    WHERE u.id = ? 
    GROUP BY u.id
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// -------------------------------------------------------------
// Handle Profile Info Update (Name, Email, Telegram, Photo)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $display_name     = trim($_POST['display_name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
        $active_tab       = 'profile';

        if (empty($display_name) || empty($email)) {
            $error = 'Nama lengkap dan alamat email tidak boleh kosong.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format alamat email tidak valid.';
        } else {
            // Check if email is already taken by another user
            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkEmail->execute([$email, $user_id]);
            if ($checkEmail->fetch()) {
                $error = 'Alamat email tersebut sudah digunakan oleh akun lain.';
            } else {
                $profilePhotoName = $user['profile_photo'] ?? null;

                // Handle Avatar Upload if provided
                if (!empty($_FILES['profile_photo']['name'])) {
                    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    $fileTmp      = $_FILES['profile_photo']['tmp_name'];
                    $fileType     = mime_content_type($fileTmp);
                    $maxSize      = 2 * 1024 * 1024; // 2MB

                    if (!isset($allowedTypes[$fileType])) {
                        $error = 'Format foto tidak didukung. Harap gunakan format JPG, PNG, atau WEBP.';
                    } elseif ($_FILES['profile_photo']['size'] > $maxSize) {
                        $error = 'Ukuran berkas foto terlalu besar. Maksimal 2MB.';
                    } else {
                        $ext         = $allowedTypes[$fileType];
                        $uploadsDir  = __DIR__ . '/../uploads/avatars';
                        if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                        $fileName    = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                        $destination = $uploadsDir . '/' . $fileName;

                        if (move_uploaded_file($fileTmp, $destination)) {
                            // Delete old avatar if custom
                            if ($profilePhotoName && file_exists($uploadsDir . '/' . $profilePhotoName)) {
                                @unlink($uploadsDir . '/' . $profilePhotoName);
                            }
                            $profilePhotoName = $fileName;
                        } else {
                            $error = 'Gagal mengunggah foto profil.';
                        }
                    }
                }

                if (!$error) {
                    $stmtUpdate = $pdo->prepare("UPDATE users SET display_name = ?, email = ?, profile_photo = ?, telegram_chat_id = ? WHERE id = ?");
                    if ($stmtUpdate->execute([$display_name, $email, $profilePhotoName, $telegram_chat_id, $user_id])) {
                        $_SESSION['display_name']  = $display_name;
                        $_SESSION['email']         = $email;
                        $_SESSION['profile_photo'] = $profilePhotoName;

                        $user['display_name']      = $display_name;
                        $user['email']             = $email;
                        $user['profile_photo']     = $profilePhotoName;
                        $user['telegram_chat_id']  = $telegram_chat_id;

                        $success = 'Informasi profil Anda berhasil diperbarui!';
                        logActivity('Memperbarui informasi profil akun', 'profil', $user_id);
                    } else {
                        $error = 'Gagal menyimpan pembaruan profil ke basis data.';
                    }
                }
            }
        }
    } else {
        $error = 'CSRF token tidak valid. Silakan muat ulang halaman.';
    }
}

// -------------------------------------------------------------
// Handle Password Update (Inside Security Tab)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $active_tab   = 'security';
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_pass, $user['password'])) {
            $error = 'Kata sandi saat ini yang Anda masukkan salah.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'Kata sandi baru harus memiliki panjang minimal 6 karakter.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Konfirmasi kata sandi baru tidak cocok.';
        } else {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmtPw = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmtPw->execute([$hashed_pass, $user_id])) {
                $success = 'Kata sandi akun Anda berhasil diperbarui!';
                logActivity('Memperbarui kata sandi profil', 'auth', $user_id);
            } else {
                $error = 'Gagal memperbarui kata sandi akun.';
            }
        }
    } else {
        $error = 'CSRF token tidak valid. Silakan muat ulang halaman.';
    }
}

// -------------------------------------------------------------
// Handle Telegram Test Notification
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_telegram'])) {
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $active_tab = 'profile';
        $chat_id    = trim($_POST['telegram_chat_id'] ?? ($user['telegram_chat_id'] ?? ''));

        if (empty($chat_id)) {
            $error = 'Masukkan atau simpan Chat ID Telegram Anda terlebih dahulu sebelum menguji.';
        } else {
            $msg = "<b>🚀 TaskFlow Pro Notifikasi</b>\n\nHalo <b>" . htmlspecialchars($user['display_name']) . "</b>!\nIni adalah pesan uji coba dari sistem TaskFlow Pro. Koneksi notifikasi Telegram Anda berhasil terhubung!";
            if (sendTelegramNotif($chat_id, $msg)) {
                $success = 'Pesan uji coba telah berhasil dikirim ke bot Telegram Anda!';
            } else {
                $error = 'Gagal mengirim notifikasi Telegram. Pastikan Bot Token sudah disetel di Pengaturan Sistem dan Anda telah menekan /start pada bot Telegram.';
            }
        }
    }
}

// Storage Calculations
$usedBytes   = (int)($user['used_bytes'] ?? 0);
$quotaBytes  = (int)($user['storage_quota'] ?? 5368709120);
$isUnlimited = ($quotaBytes === 0);
$quotaLabel  = $isUnlimited ? 'Tanpa Batas' : formatSize($quotaBytes);
$usedLabel   = formatSize($usedBytes);
$pct         = $isUnlimited ? 0 : round(($usedBytes / max(1, $quotaBytes)) * 100, 1);
$barColor    = $pct >= 90 ? 'bg-rose-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500');

// Avatar info
$currentPhoto = $user['profile_photo'] ?? null;
$hasPhoto     = $currentPhoto && file_exists(__DIR__ . '/../uploads/avatars/' . $currentPhoto);
$avatarUrl    = $hasPhoto ? 'uploads/avatars/' . $currentPhoto : getAvatarDataUri($user['display_name'], 120);
$initials     = strtoupper(substr($user['display_name'] ?? 'U', 0, 2));
$joinedDate   = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : date('M Y');
$isActive     = ($user['status'] ?? 'active') === 'active';
$isAdmin      = ($user['role'] ?? '') === 'administrator';
?>

<div class="p-4 md:p-8 w-full space-y-6">

    <!-- Executive Header (No Redundant Avatar) -->
    <div class="rounded-3xl p-6 md:p-7 bg-white/76 backdrop-blur-xl border border-white/85 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Profil &amp; Pengaturan Akun
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Pengaturan Profil &amp; Keamanan</h1>
            <p class="text-slate-500 text-xs md:text-sm font-medium leading-relaxed max-w-xl">
                Kelola informasi identitas akun, kredensial keamanan, dan preferensi notifikasi Anda secara terpadu.
            </p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($error): ?>
    <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 flex items-start gap-3 text-rose-800 text-xs font-bold shadow-xs">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" class="text-rose-600 flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div class="leading-relaxed"><?php echo htmlspecialchars($error); ?></div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-start gap-3 text-emerald-800 text-xs font-bold shadow-xs">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-emerald-600 flex-shrink-0 mt-0.5"><polyline points="20 6 9 17 4 12"/></svg>
        <div class="leading-relaxed"><?php echo htmlspecialchars($success); ?></div>
    </div>
    <?php endif; ?>

    <!-- Master-Detail 2-Column Standard -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ============================================================== -->
        <!-- KOLOM KIRI: KARTU IDENTITAS (Col Span 4)                       -->
        <!-- ============================================================== -->
        <div class="lg:col-span-4">
            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/90 p-6 md:p-7 text-center shadow-xs">
                
                <!-- Avatar with Interactive Camera Overlay -->
                <div class="relative inline-block mb-3.5">
                    <div class="w-28 h-28 rounded-3xl overflow-hidden border-4 border-slate-100 shadow-md mx-auto relative group bg-slate-100">
                        <img id="avatarBig" src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($user['display_name']); ?>" class="w-full h-full object-cover">
                        
                        <!-- Hover Overlay to trigger file upload -->
                        <label for="avatarFileInput" class="absolute inset-0 bg-slate-950/45 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center cursor-pointer text-white backdrop-blur-2xs">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <span class="text-[10px] font-bold mt-1">Ubah Foto</span>
                        </label>
                    </div>

                    <!-- Permanent Camera Badge Button -->
                    <label for="avatarFileInput" title="Klik untuk mengganti foto profil"
                           class="absolute -bottom-1 -right-1 w-9 h-9 rounded-xl flex items-center justify-center cursor-pointer transition-all hover:scale-105 active:scale-95 bg-indigo-600 hover:bg-indigo-700 text-white shadow-md border-2 border-white">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </label>
                </div>

                <!-- Hidden file input linked to profileForm -->
                <input type="file" name="profile_photo" id="avatarFileInput" accept="image/png,image/jpeg,image/webp" class="hidden" form="profileForm" onchange="previewPhoto(this)">

                <!-- Selected Photo Pill Indicator -->
                <div id="photoChosenBadge" class="hidden mb-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 border border-indigo-200 text-indigo-700">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span id="photoChosenName">Foto baru dipilih</span>
                    </span>
                </div>

                <!-- Name & Role Badge -->
                <h2 class="font-black text-slate-900 text-lg tracking-tight leading-snug"><?php echo htmlspecialchars($user['display_name']); ?></h2>
                <div class="mt-2">
                    <?php if ($isAdmin): ?>
                    <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/80 shadow-2xs">
                        👑 Administrator
                    </span>
                    <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-3 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs">
                        👤 Anggota Tim
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Compact Metadata List -->
                <div class="mt-6 pt-5 border-t border-slate-100 space-y-2.5 text-left">
                    
                    <!-- Status Akun -->
                    <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50/80 border border-slate-100">
                        <span class="text-xs font-bold text-slate-500">Status Akun</span>
                        <?php if ($isActive): ?>
                        <span class="inline-flex items-center gap-1.5 text-xs font-extrabold text-emerald-700">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Aktif
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 text-xs font-extrabold text-rose-600">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            Nonaktif
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Tanggal Bergabung -->
                    <div class="flex items-center justify-between p-3 rounded-2xl bg-slate-50/80 border border-slate-100">
                        <span class="text-xs font-bold text-slate-500">Bergabung Sejak</span>
                        <span class="text-xs font-extrabold text-slate-800"><?php echo htmlspecialchars($joinedDate); ?></span>
                    </div>

                    <!-- Kuota Penyimpanan Cloud -->
                    <div class="p-3.5 rounded-2xl bg-slate-50/80 border border-slate-100 space-y-2">
                        <div class="flex items-center justify-between text-xs font-bold">
                            <span class="text-slate-500">Penyimpanan Dokumen</span>
                            <span class="text-slate-900"><?php echo $usedLabel; ?> <span class="text-slate-400 font-normal">/ <?php echo $quotaLabel; ?></span></span>
                        </div>
                        <?php if (!$isUnlimited): ?>
                        <div class="w-full bg-slate-200/80 rounded-full h-1.5 overflow-hidden">
                            <div class="<?php echo $barColor; ?> h-1.5 rounded-full transition-all duration-500" style="width: <?php echo min(100, $pct); ?>%"></div>
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 font-bold">
                            <span>Terpakai</span>
                            <span><?php echo $pct; ?>%</span>
                        </div>
                        <?php else: ?>
                        <div class="text-[10px] text-indigo-600 font-extrabold flex items-center gap-1">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                            <span>Kuota Cloud Tanpa Batas</span>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- KOLOM KANAN: PENGATURAN BER-TAB (Col Span 8)                    -->
        <!-- ============================================================== -->
        <div class="lg:col-span-8">
            <div class="bg-white/80 backdrop-blur-md rounded-3xl border border-white/90 p-6 md:p-8 shadow-xs">
                
                <!-- Tab Navigation Switcher -->
                <div class="flex items-center gap-2 p-1 bg-slate-100 border border-slate-200/90 rounded-2xl mb-6">
                    <button type="button" onclick="switchProfileTab('profile')" id="tab-btn-profile" 
                            class="flex-1 py-2.5 px-4 rounded-xl text-xs font-extrabold transition-all flex items-center justify-center gap-2 <?php echo ($active_tab === 'profile') ? 'bg-white text-slate-900 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-900 font-bold'; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span>Informasi Profil</span>
                    </button>
                    <button type="button" onclick="switchProfileTab('security')" id="tab-btn-security" 
                            class="flex-1 py-2.5 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 <?php echo ($active_tab === 'security') ? 'bg-white text-slate-900 shadow-2xs font-black' : 'text-slate-500 hover:text-slate-900 font-bold'; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <span>Keamanan &amp; Kata Sandi</span>
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- PANE 1: INFORMASI PROFIL                   -->
                <!-- ========================================== -->
                <div id="tab-pane-profile" class="<?php echo ($active_tab === 'profile') ? 'block' : 'hidden'; ?>">
                    
                    <form method="POST" enctype="multipart/form-data" id="profileForm" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="active_tab" value="profile">

                        <!-- Input Nama Lengkap -->
                        <div>
                            <label class="block text-[11px] font-black text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                            <div class="relative flex items-center bg-slate-50 border border-slate-200/90 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <span class="text-slate-400 mr-2.5 flex-shrink-0">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <input type="text" name="display_name" required
                                       value="<?php echo htmlspecialchars($user['display_name']); ?>"
                                       placeholder="Nama Lengkap Anda"
                                       class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400">
                            </div>
                        </div>

                        <!-- Input Alamat Email -->
                        <div>
                            <label class="block text-[11px] font-black text-slate-700 uppercase tracking-wider mb-1.5">Alamat Email</label>
                            <div class="relative flex items-center bg-slate-50 border border-slate-200/90 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <span class="text-slate-400 mr-2.5 flex-shrink-0">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                </span>
                                <input type="email" name="email" required
                                       value="<?php echo htmlspecialchars($user['email']); ?>"
                                       placeholder="nama@perusahaan.com"
                                       class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400">
                            </div>
                        </div>

                        <!-- Input Telegram Chat ID -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-2">
                                    <label class="block text-[11px] font-black text-slate-700 uppercase tracking-wider">Telegram Chat ID</label>
                                    <?php if (!empty($user['telegram_chat_id'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Terkoneksi
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-500 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Belum Terhubung
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <a href="https://t.me/userinfobot" target="_blank" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
                                    Cara Mendapatkan ID ↗
                                </a>
                            </div>

                            <div class="relative flex items-center bg-slate-50 border border-slate-200/90 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <span class="text-slate-400 mr-2.5 flex-shrink-0">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L2 8.66l10 2.66 2.66 10L22 2z"/></svg>
                                </span>
                                <input type="text" name="telegram_chat_id" id="telegramChatInput"
                                       value="<?php echo htmlspecialchars($user['telegram_chat_id'] ?? ''); ?>"
                                       placeholder="Contoh: 6244477500"
                                       class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400 font-mono">
                            </div>
                            <p class="text-[11px] text-slate-400 font-medium mt-1.5 leading-normal">
                                Notifikasi instan mengenai tugas baru, tenggat waktu, dan aktivitas sistem akan dikirimkan langsung ke Telegram Anda.
                            </p>
                        </div>

                        <!-- Form Actions: Primary Solid & Ghost Secondary -->
                        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <!-- Tombol Utama: Simpan Perubahan -->
                            <button type="submit" name="update_profile" value="1"
                                    class="px-6 py-3 rounded-xl font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/25 transition-all active:scale-95 flex items-center justify-center gap-2 text-xs sm:text-sm">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                <span>Simpan Perubahan</span>
                            </button>

                            <!-- Tombol Ghost / Sekunder: Test Telegram -->
                            <button type="submit" name="test_telegram" value="1"
                                    class="px-5 py-3 rounded-xl font-bold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200/90 hover:border-slate-300 transition-all active:scale-95 flex items-center justify-center gap-2 text-xs sm:text-sm shadow-2xs">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-indigo-600"><path d="M22 2L2 8.66l10 2.66 2.66 10L22 2z"/></svg>
                                <span>Kirim Tes Notifikasi</span>
                            </button>
                        </div>

                    </form>
                </div>

                <!-- ========================================== -->
                <!-- PANE 2: KEAMANAN & KATA SANDI              -->
                <!-- ========================================== -->
                <div id="tab-pane-security" class="<?php echo ($active_tab === 'security') ? 'block' : 'hidden'; ?>">
                    
                    <form method="POST" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="update_password" value="1">
                        <input type="hidden" name="active_tab" value="security">

                        <!-- Kata Sandi Saat Ini -->
                        <div>
                            <label class="block text-[11px] font-black text-slate-700 uppercase tracking-wider mb-1.5">Kata Sandi Saat Ini</label>
                            <div class="relative flex items-center bg-slate-50 border border-slate-200/90 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <span class="text-slate-400 mr-2.5 flex-shrink-0">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input type="password" name="current_password" id="curPwInput" required
                                       placeholder="••••••••"
                                       class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400 pr-8">
                                <button type="button" onclick="togglePwVisibility('curPwInput', 'curEyeIcon')" class="absolute right-3 text-slate-400 hover:text-slate-700 transition-colors">
                                    <svg id="curEyeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Kata Sandi Baru -->
                        <div>
                            <label class="block text-[11px] font-black text-slate-700 uppercase tracking-wider mb-1.5">Kata Sandi Baru</label>
                            <div class="relative flex items-center bg-slate-50 border border-slate-200/90 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <span class="text-slate-400 mr-2.5 flex-shrink-0">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input type="password" name="new_password" id="newPwInput" required minlength="6"
                                       placeholder="Minimal 6 karakter"
                                       class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400 pr-8">
                                <button type="button" onclick="togglePwVisibility('newPwInput', 'newEyeIcon')" class="absolute right-3 text-slate-400 hover:text-slate-700 transition-colors">
                                    <svg id="newEyeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Konfirmasi Kata Sandi Baru -->
                        <div>
                            <label class="block text-[11px] font-black text-slate-700 uppercase tracking-wider mb-1.5">Konfirmasi Kata Sandi Baru</label>
                            <div class="relative flex items-center bg-slate-50 border border-slate-200/90 rounded-xl px-3.5 py-2.5 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500/20 focus-within:bg-white transition-all">
                                <span class="text-slate-400 mr-2.5 flex-shrink-0">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input type="password" name="confirm_password" id="confPwInput" required minlength="6"
                                       placeholder="Ulangi kata sandi baru"
                                       class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400 pr-8">
                                <button type="button" onclick="togglePwVisibility('confPwInput', 'confEyeIcon')" class="absolute right-3 text-slate-400 hover:text-slate-700 transition-colors">
                                    <svg id="confEyeIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Security Tips Box -->
                        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-start gap-2.5 text-xs text-slate-500">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" class="text-indigo-600 flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div class="leading-relaxed font-medium">
                                Gunakan kata sandi minimal 6 karakter dengan kombinasi huruf besar, angka, dan simbol untuk keamanan maksimal akun Anda.
                            </div>
                        </div>

                        <!-- Action Submit -->
                        <div class="pt-3 border-t border-slate-100">
                            <button type="submit" name="update_password" value="1"
                                    class="w-full sm:w-auto px-6 py-3 rounded-xl font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/25 transition-all active:scale-95 flex items-center justify-center gap-2 text-xs sm:text-sm">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Perbarui Kata Sandi</span>
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>

    </div>

</div>

<!-- Tab & Preview Scripts -->
<script>
function switchProfileTab(tab) {
    const btnProf = document.getElementById('tab-btn-profile');
    const btnSec  = document.getElementById('tab-btn-security');
    const paneProf = document.getElementById('tab-pane-profile');
    const paneSec  = document.getElementById('tab-pane-security');

    if (tab === 'profile') {
        btnProf.className = "flex-1 py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2 bg-white text-slate-900 shadow-2xs font-black";
        btnSec.className  = "flex-1 py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2 text-slate-500 hover:text-slate-900 font-bold";
        paneProf.classList.remove('hidden');
        paneSec.classList.add('hidden');
    } else {
        btnSec.className  = "flex-1 py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2 bg-white text-slate-900 shadow-2xs font-black";
        btnProf.className = "flex-1 py-2.5 px-4 rounded-xl text-xs transition-all flex items-center justify-center gap-2 text-slate-500 hover:text-slate-900 font-bold";
        paneSec.classList.remove('hidden');
        paneProf.classList.add('hidden');
    }
}

function previewPhoto(input) {
    const badge = document.getElementById('photoChosenBadge');
    const nameEl = document.getElementById('photoChosenName');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
            const big = document.getElementById('avatarBig');
            if (big) big.src = e.target.result;
        };
        reader.readAsDataURL(file);

        if (badge && nameEl) {
            nameEl.textContent = '✓ ' + file.name + ' (klik Simpan)';
            badge.classList.remove('hidden');
        }
    }
}

function togglePwVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;

    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
</script>