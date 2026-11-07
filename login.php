<?php
// login.php - Modern & Secure Executive Authentication Portal
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . route('dashboard'));
    exit;
}

$error = '';
$app_name = getSetting('app_name', 'TaskFlow Pro');
$company_name = getSetting('company_name', $app_name);
$company_tagline = getSetting('company_tagline', 'Manajemen Tugas, Dokumen & Kolaborasi Tim');
$app_logo_info = getAppLogoInfo();
$logo_valid = $app_logo_info['is_custom'];
$company_logo = $app_logo_info['url'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Silakan masukkan alamat email dan kata sandi Anda.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (($user['status'] ?? 'active') === 'inactive') {
                $error = 'Akun Anda saat ini dinonaktifkan oleh Administrator. Hubungi administrator organisasi Anda.';
            } else {
                // Security hardening: Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                $_SESSION['user_id']       = (int)$user['id'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['display_name']  = $user['display_name'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
                
                // Log successful login
                logActivity('Pengguna berhasil masuk ke sistem', 'auth', $user['id']);

                header('Location: ' . route('dashboard'));
                exit;
            }
        } else {
            $error = 'Email atau kata sandi yang Anda masukkan salah. Periksa kembali kredensial Anda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Akun &bull; <?php echo htmlspecialchars($app_name); ?></title>
    <link rel="icon" type="<?php echo $app_logo_info['mime']; ?>" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Unified Ambient Glow Background */
        .ambient-canvas {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(circle at 50% 0%, rgba(99, 102, 241, 0.12) 0%, transparent 50%),
                radial-gradient(circle at 85% 90%, rgba(14, 165, 233, 0.08) 0%, transparent 45%),
                radial-gradient(circle at 15% 90%, rgba(124, 58, 237, 0.08) 0%, transparent 45%);
        }

        .auth-glass-card {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.95);
            box-shadow: 
                0 20px 40px -15px rgba(15, 23, 42, 0.06),
                0 1px 3px rgba(15, 23, 42, 0.03),
                inset 0 1px 0 #ffffff;
        }

        .auth-input {
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .auth-input:focus-within {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
            background-color: #ffffff;
        }
    </style>
</head>
<body class="h-full ambient-canvas text-slate-900 flex flex-col justify-between min-h-screen p-4 sm:p-6 selection:bg-indigo-500 selection:text-white">

    <!-- Top Navigation Brand Bar -->
    <header class="w-full max-w-5xl mx-auto py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <?php if ($logo_valid): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Logo" class="h-9 max-w-[140px] object-contain">
            <?php else: ?>
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-md shadow-indigo-600/25">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
            <?php endif; ?>
            <span class="text-slate-900 font-extrabold text-base tracking-tight"><?php echo htmlspecialchars($company_name); ?></span>
        </div>

        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/80 border border-slate-200/80 text-[11px] font-bold text-slate-600 shadow-2xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Server Aktif</span>
        </div>
    </header>

    <!-- Center Login Card -->
    <main class="w-full max-w-[420px] mx-auto my-auto py-6">
        <div class="auth-glass-card rounded-3xl p-7 sm:p-9 border border-slate-200/90 shadow-xl">
            
            <!-- Header Identity -->
            <div class="text-center mb-7">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 border border-indigo-100 flex items-center justify-center mx-auto mb-3.5 shadow-2xs">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Masuk ke Akun</h1>
                <p class="text-slate-500 text-xs font-medium mt-1">
                    Silakan masukkan email dan kata sandi Anda untuk melanjutkan
                </p>
            </div>

            <!-- Error Banner -->
            <?php if ($error): ?>
                <div class="mb-5 p-3.5 rounded-2xl bg-rose-50 border border-rose-200 flex items-start gap-2.5 text-rose-800 text-xs font-semibold shadow-xs">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" class="text-rose-600 flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div class="leading-relaxed"><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" class="space-y-4" id="mainLoginForm">
                
                <!-- Email Input -->
                <div>
                    <label for="emailInput" class="block text-[11px] font-black text-slate-700 uppercase tracking-wider mb-1.5">Alamat Email</label>
                    <div class="auth-input relative flex items-center bg-slate-50/80 border border-slate-200 rounded-xl overflow-hidden px-3.5 py-2.5">
                        <span class="text-slate-400 mr-2.5 flex-shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input
                            type="email"
                            name="email"
                            id="emailInput"
                            required
                            placeholder="nama@perusahaan.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            class="w-full bg-transparent text-xs sm:text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400"
                            autocomplete="email"
                        >
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="pwInput" class="block text-[11px] font-black text-slate-700 uppercase tracking-wider">Kata Sandi</label>
                        <button type="button" onclick="openForgotPwModal()" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
                            Lupa kata sandi?
                        </button>
                    </div>
                    <div class="auth-input relative flex items-center bg-slate-50/80 border border-slate-200 rounded-xl overflow-hidden px-3.5 py-2.5">
                        <span class="text-slate-400 mr-2.5 flex-shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input
                            type="password"
                            name="password"
                            id="pwInput"
                            required
                            placeholder="••••••••"
                            class="w-full bg-transparent text-xs sm:text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400 pr-9"
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            onclick="togglePassword()"
                            class="absolute right-2.5 p-1 text-slate-400 hover:text-indigo-600 transition-colors focus:outline-none"
                            aria-label="Tampilkan atau sembunyikan password"
                        >
                            <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="rememberMe" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20 transition-all">
                        <span class="text-xs font-semibold text-slate-600">Ingat sesi di peramban ini</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" id="submitBtn" 
                            class="w-full py-3 px-5 rounded-xl text-white font-extrabold text-xs sm:text-sm shadow-md shadow-indigo-600/25 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 transition-all active:scale-[0.98]">
                        <svg id="submitIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        <span id="submitText">Masuk ke Sistem</span>
                    </button>
                </div>
            </form>

        </div>
    </main>

    <!-- Bottom Security & Copyright Footer -->
    <footer class="w-full max-w-5xl mx-auto py-4 text-center text-xs text-slate-400 flex flex-col sm:flex-row items-center justify-between gap-2">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?>. Hak Cipta Dilindungi.</p>
        <div class="flex items-center gap-1.5 text-[11px] text-slate-500">
            <svg class="w-3.5 h-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Koneksi Aman Terenkripsi TLS</span>
        </div>
    </footer>

    <!-- Forgot Password Modal -->
    <div id="forgotPwModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 text-center" onclick="event.stopPropagation()">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3.5 border border-indigo-100">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h3 class="text-base font-black text-slate-900 mb-1.5">Lupa Kata Sandi?</h3>
            <p class="text-xs text-slate-500 leading-relaxed mb-6 font-medium">
                Untuk alasan keamanan, reset kata sandi dilakukan secara terpusat oleh Administrator. Silakan hubungi Administrator Anda untuk mereset kata sandi akun Anda ke sandi bawaan (<code class="bg-slate-100 text-slate-800 px-1.5 py-0.5 rounded font-mono font-bold">password123</code>).
            </p>
            <button type="button" onclick="closeForgotPwModal()" 
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-transform active:scale-95 shadow-sm">
                Mengerti &amp; Tutup
            </button>
        </div>
    </div>

    <!-- Script Controllers -->
    <script>
    function togglePassword() {
        const pw = document.getElementById('pwInput');
        const icon = document.getElementById('eyeIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
        } else {
            pw.type = 'password';
            icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }
    }

    function openForgotPwModal() {
        const m = document.getElementById('forgotPwModal');
        if (m) {
            m.classList.remove('hidden');
            m.style.display = 'flex';
        }
    }

    function closeForgotPwModal() {
        const m = document.getElementById('forgotPwModal');
        if (m) {
            m.classList.add('hidden');
            m.style.display = 'none';
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeForgotPwModal();
    });

    const loginForm = document.getElementById('mainLoginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            const txt = document.getElementById('submitText');
            const icon = document.getElementById('submitIcon');
            if (btn && txt) {
                btn.style.pointerEvents = 'none';
                btn.classList.add('opacity-85');
                txt.innerText = 'Memverifikasi akun...';
                if (icon) {
                    icon.outerHTML = '<svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
                }
            }
        });
    }
    </script>
</body>
</html>
