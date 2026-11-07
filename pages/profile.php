<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/profile.php - Edit Profile
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = $success = '';

$stmt = $pdo->prepare("SELECT email, display_name, profile_photo, telegram_chat_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

// Ensure profile_photo column exists
try {
    $check = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_photo'");
    $check->execute();
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER role");
    }
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $display_name = trim($_POST['display_name']);
        $email        = trim($_POST['email']);
        $telegram_chat_id = trim($_POST['telegram_chat_id']);

        if (empty($display_name) || empty($email)) {
            $error = 'Semua field harus diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Email tidak valid.';
        } else {
            $profilePhotoName = $user['profile_photo'] ?? null;

            if (!empty($_FILES['profile_photo']['name'])) {
                $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $fileType     = mime_content_type($_FILES['profile_photo']['tmp_name']);
                $maxSize      = 2 * 1024 * 1024;

                if (!isset($allowedTypes[$fileType])) {
                    $error = 'Format foto tidak didukung. Gunakan JPG/PNG/WEBP.';
                } elseif ($_FILES['profile_photo']['size'] > $maxSize) {
                    $error = 'Ukuran foto terlalu besar. Maksimal 2MB.';
                } else {
                    $ext      = $allowedTypes[$fileType];
                    $uploadsDir = __DIR__ . '/../uploads/avatars';
                    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
                    $fileName    = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    $destination = $uploadsDir . '/' . $fileName;
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
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
                $stmt = $pdo->prepare("UPDATE users SET display_name = ?, email = ?, profile_photo = ?, telegram_chat_id = ? WHERE id = ?");
                if ($stmt->execute([$display_name, $email, $profilePhotoName, $telegram_chat_id, $user_id])) {
                    $_SESSION['display_name']  = $display_name;
                    $_SESSION['email']         = $email;
                    $_SESSION['profile_photo'] = $profilePhotoName;
                    $success = 'Profil berhasil diperbarui!';
                    $user['display_name']  = $display_name;
                    $user['email']         = $email;
                    $user['profile_photo'] = $profilePhotoName;
                    $user['telegram_chat_id'] = $telegram_chat_id;
                } else {
                    $error = 'Gagal update profil.';
                }
            }
        }
    } else {
        $error = 'CSRF token tidak valid.';
    }
}

// Telegram Test Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_telegram'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $chat_id = $user['telegram_chat_id'] ?? '';
        if (empty($chat_id)) {
            $error = 'Atur Chat ID Anda terlebih dahulu.';
        } else {
            $msg = "<b>🚀 TaskFlow Pro</b>\n\nHalo <b>" . $user['display_name'] . "</b>!\nIni adalah pesan uji coba dari sistem TaskFlow. Koneksi Telegram Anda berhasil dikonfigurasi.";
            if (sendTelegramNotif($chat_id, $msg)) {
                $success = 'Pesan uji coba telah dikirim ke Telegram Anda!';
            } else {
                $error = 'Gagal mengirim pesan. Pastikan Bot Token sudah benar di Pengaturan Sistem dan Anda sudah menekan /start pada bot.';
            }
        }
    }
}

$currentPhoto = $user['profile_photo'] ?? null;
$hasPhoto     = $currentPhoto && file_exists(__DIR__ . '/../uploads/avatars/' . $currentPhoto);
$avatarUrl    = $hasPhoto ? 'uploads/avatars/' . $currentPhoto : getAvatarDataUri($user['display_name'], 80);
$initials     = strtoupper(substr($user['display_name'] ?? 'U', 0, 2));
?>

<div class="p-5 md:p-8 max-w-5xl mx-auto">

    <!-- Executive Glassmorphic Header -->
    <div class="rounded-3xl p-6 md:p-8 mb-7 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div class="flex items-center gap-4">
            <!-- Avatar preview in hero -->
            <div class="w-14 h-14 rounded-2xl overflow-hidden flex-shrink-0 border-2 border-slate-200 dark:border-slate-700 shadow-sm">
                <img id="avatarPreview" src="<?php echo $avatarUrl; ?>" alt="Avatar" class="w-full h-full object-cover">
            </div>
            <div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider mb-1">
                    Akun Saya
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white leading-tight"><?php echo htmlspecialchars($user['display_name']); ?></h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>
        <div class="flex-shrink-0">
            <a href="?page=reset-password"
               class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-all shadow-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Ubah Password
            </a>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
    <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-100 rounded-2xl px-5 py-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-red-700 text-sm font-bold"><?php echo htmlspecialchars($error); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-100 rounded-2xl px-5 py-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <p class="text-emerald-800 text-sm font-bold"><?php echo htmlspecialchars($success); ?></p>
    </div>
    <?php endif; ?>

    <!-- ===== MAIN CARD ===== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT: Avatar section -->
        <div class="lg:col-span-4">
            <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 p-7 text-center shadow-sm">
                <!-- Big avatar display -->
                <div class="relative inline-block mb-5">
                    <div class="w-28 h-28 rounded-3xl overflow-hidden border-4 border-slate-100 dark:border-slate-800 shadow-md mx-auto">
                        <img id="avatarBig" src="<?php echo $avatarUrl; ?>" alt="Avatar" class="w-full h-full object-cover">
                    </div>
                    <!-- Camera badge -->
                    <label for="photoInput" class="absolute -bottom-2 -right-2 w-9 h-9 rounded-xl flex items-center justify-center cursor-pointer transition-all hover:scale-105 active:scale-95 bg-indigo-600 hover:bg-indigo-700 text-white shadow-md">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </label>
                </div>

                <h3 class="font-extrabold text-slate-900 dark:text-white text-lg"><?php echo htmlspecialchars($user['display_name']); ?></h3>
                <p class="text-slate-500 dark:text-slate-400 text-xs font-medium mt-0.5 mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="inline-block text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                    <?php echo $_SESSION['role'] ?? 'user'; ?>
                </span>

                <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 text-left space-y-2.5">
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Email Terdaftar</div>
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Nama Lengkap</div>
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate"><?php echo htmlspecialchars($user['display_name']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Form -->
        <div class="lg:col-span-8">
            <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 p-6 md:p-8 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-900 dark:text-white text-base">Edit Informasi</h3>
                        <p class="text-slate-400 text-xs">Perbarui data profil &amp; notifikasi Anda</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <!-- Photo upload -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Foto Profil</label>
                        <label for="photoInput"
                               class="flex items-center gap-4 p-4 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 hover:border-indigo-400 dark:hover:border-indigo-500/60 bg-slate-50/70 dark:bg-slate-800/40 cursor-pointer transition-all"
                               id="photoDropZone">
                            <div class="w-10 h-10 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 flex items-center justify-center flex-shrink-0">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            </div>
                            <div>
                                <div class="text-slate-800 dark:text-slate-200 font-bold text-sm">Klik untuk ganti foto</div>
                                <div class="text-slate-400 text-xs">JPG, PNG, WEBP · Maksimal 2MB</div>
                            </div>
                            <input type="file" name="profile_photo" id="photoInput" accept="image/png,image/jpeg,image/webp" class="hidden"
                                   onchange="previewPhoto(this)">
                        </label>
                        <p id="photoName" class="text-xs text-indigo-600 dark:text-indigo-400 font-bold mt-2 hidden"></p>
                    </div>

                    <!-- Display Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                        <input type="text" name="display_name"
                                value="<?php echo htmlspecialchars($user['display_name']); ?>" required
                                class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Email</label>
                        <input type="email" name="email"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required
                                class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                    </div>

                    <!-- Telegram Chat ID -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Telegram Chat ID</label>
                            <a href="https://t.me/userinfobot" target="_blank" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">Dapatkan ID Anda ↗</a>
                        </div>
                        <input type="text" name="telegram_chat_id"
                                value="<?php echo htmlspecialchars($user['telegram_chat_id'] ?? ''); ?>"
                                placeholder="Contoh: 12345678"
                                class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">Untuk menerima notifikasi sistem di Telegram secara langsung.</p>
                    </div>

                    <button type="submit"
                            class="w-full py-3 rounded-xl font-bold text-white transition-all active:scale-95 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-sm text-sm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan Perubahan
                    </button>
                    
                    <?php if (!empty($user['telegram_chat_id'])): ?>
                    <button type="submit" name="test_telegram"
                            class="w-full py-2.5 rounded-xl font-bold text-xs text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition-all flex items-center justify-center gap-2 mt-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L2 8.66l10 2.66 2.66 10L22 2z"/></svg>
                        Test Notifikasi Telegram
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Email</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </span>
                            <input type="email" name="email"
                                    value="<?php echo htmlspecialchars($user['email']); ?>" required
                                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-5 py-4 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all">
                        </div>
                    </div>

                    <!-- Telegram Chat ID -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">Telegram Chat ID</label>
                            <a href="https://t.me/userinfobot" target="_blank" class="text-[9px] font-bold text-sky-600 hover:underline">Dapatkan ID Anda ↗</a>
                        </div>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L2 8.66l10 2.66 2.66 10L22 2z"/></svg>
                            </span>
                            <input type="text" name="telegram_chat_id"
                                    value="<?php echo htmlspecialchars($user['telegram_chat_id'] ?? ''); ?>"
                                    placeholder="Contoh: 12345678"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-5 py-4 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 transition-all">
                        </div>
                        <p class="text-[10px] text-slate-500 mt-2">Untuk menerima notifikasi sistem di Telegram secara langsung.</p>
                    </div>
                    <button type="submit"
                            class="w-full py-4 rounded-2xl font-bold text-white transition-all active:scale-95 flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan Perubahan
                    </button>
                    
                    <?php if (!empty($user['telegram_chat_id'])): ?>
                    <button type="submit" name="test_telegram"
                            class="w-full py-3 rounded-xl font-bold text-sky-600 border-2 border-sky-100 hover:bg-sky-50 transition-all active:scale-95 flex items-center justify-center gap-2 mt-2">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L2 8.66l10 2.66 2.66 10L22 2z"/></svg>
                        Test Notifikasi Telegram
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function previewPhoto(input) {
    const label = document.getElementById('photoName');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
            document.getElementById('avatarBig').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
        label.textContent = '✓ ' + input.files[0].name;
        label.classList.remove('hidden');
        document.getElementById('photoDropZone').style.borderColor = '#0ea5e9';
        document.getElementById('photoDropZone').style.background = '#f0f9ff';
    }
}
</script>
