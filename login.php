<?php
// login.php - Modern & Secure Authentication Portal
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Silakan masukkan email dan password.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Security hardening: Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id']       = (int)$user['id'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['display_name']  = $user['display_name'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
            
            // Log successful login
            logActivity('Pengguna berhasil login', 'auth', $user['id']);

            header('Location: ' . route('dashboard'));
            exit;
        } else {
            $error = 'Email atau password yang Anda masukkan salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk – <?php echo htmlspecialchars($app_name); ?></title>
    <link rel="icon" type="<?php echo $app_logo_info['mime']; ?>" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }
        
        .brand-gradient {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
        }
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .input-box {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .input-box:focus-within {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
            background-color: #ffffff;
        }

        .btn-submit {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            transition: all 0.25s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
        }
        .btn-submit:active {
            transform: translateY(0);
        }
    </style>
</head>
<body class="h-full bg-slate-50 text-slate-900 flex min-h-screen">

    <!-- LEFT PANEL: Hero Brand & Feature Showcase -->
    <div class="brand-gradient hidden lg:flex lg:w-1/2 flex-col justify-between p-12 xl:p-16 text-white relative overflow-hidden flex-shrink-0">
        <!-- Subtle Decorative Background Circles -->
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-sky-600/15 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Top Header / Logo -->
        <div class="relative z-10">
            <div class="flex items-center gap-3.5">
                <?php if ($logo_valid): ?>
                    <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Logo" class="h-10 max-w-[140px] object-contain drop-shadow-md">
                <?php else: ?>
                    <div class="w-11 h-11 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-600/30">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </div>
                <?php endif; ?>
                <div>
                    <h2 class="text-xl font-extrabold tracking-tight text-white"><?php echo htmlspecialchars($company_name); ?></h2>
                    <span class="text-[10px] uppercase font-bold text-indigo-300 tracking-wider">Enterprise Workspace</span>
                </div>
            </div>
        </div>

        <!-- Center Showcase & Product Highlights -->
        <div class="relative z-10 max-w-lg py-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 text-xs font-bold mb-6">
                <span class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></span>
                TaskFlow Workspace Pro
            </div>
            
            <h1 class="text-3xl xl:text-4xl font-extrabold text-white leading-tight tracking-tight mb-4">
                Kelola pekerjaan, dokumen &amp; kolaborasi tim secara terintegrasi.
            </h1>
            <p class="text-slate-300 text-sm xl:text-base leading-relaxed mb-8">
                Platform produktivitas lengkap untuk pelacakan tugas, manajemen proyek, arsip dokumen, serta komunikasi kerja.
            </p>

            <!-- Productivity Feature Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="glass-panel p-4 rounded-2xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-xl bg-indigo-500/20 flex items-center justify-center text-indigo-300 flex-shrink-0 mt-0.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white">Proyek &amp; Kanban</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Alur kerja terstruktur dengan visual board.</p>
                    </div>
                </div>

                <div class="glass-panel p-4 rounded-2xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-xl bg-sky-500/20 flex items-center justify-center text-sky-300 flex-shrink-0 mt-0.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white">Kalender &amp; Deadline</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Jadwal tugas &amp; monitoring aktivitas harian.</p>
                    </div>
                </div>

                <div class="glass-panel p-4 rounded-2xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 flex-shrink-0 mt-0.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white">Arsip &amp; Berbagi Dokumen</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Penyimpanan file aman terpusat.</p>
                    </div>
                </div>

                <div class="glass-panel p-4 rounded-2xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-300 flex-shrink-0 mt-0.5">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-white">Akumulasi Jam Kerja</h4>
                        <p class="text-[11px] text-slate-400 mt-0.5">Grafik produktivitas &amp; metrik visual.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="relative z-10 flex items-center justify-between text-xs text-slate-400 border-t border-white/10 pt-6">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?>. Hak Cipta Dilindungi.</span>
            <span class="text-indigo-400 font-semibold">v2.5 Pro</span>
        </div>
    </div>

    <!-- RIGHT PANEL: Login Form -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-6 sm:p-12 md:p-16 bg-slate-50">
        
        <!-- Mobile Header (Visible on small screens) -->
        <div class="flex lg:hidden items-center gap-3 mb-8">
            <?php if ($logo_valid): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Logo" class="h-9 max-w-[120px] object-contain">
            <?php else: ?>
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center shadow-md shadow-indigo-600/20">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
            <?php endif; ?>
            <span class="text-slate-900 font-extrabold text-lg tracking-tight"><?php echo htmlspecialchars($company_name); ?></span>
        </div>

        <div class="w-full max-w-md bg-white rounded-3xl p-8 sm:p-10 border border-slate-200 shadow-xl shadow-slate-200/50">
            
            <div class="mb-7">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight">Selamat Datang Kembali</h2>
                <p class="text-slate-500 text-xs sm:text-sm font-medium mt-1">Masukkan kredensial akun Anda untuk mengakses TaskFlow.</p>
            </div>

            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 flex items-start gap-3 text-rose-800 text-xs font-semibold animate-shake">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" class="text-rose-600 flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="space-y-5">
                <!-- Email Input -->
                <div>
                    <label for="emailInput" class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-2">Email Pengguna</label>
                    <div class="input-box relative flex items-center bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden px-4 py-3">
                        <span class="text-slate-400 mr-3 flex-shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
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
                    <div class="flex items-center justify-between mb-2">
                        <label for="pwInput" class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">Kata Sandi</label>
                    </div>
                    <div class="input-box relative flex items-center bg-slate-50 border border-slate-200 rounded-2xl overflow-hidden px-4 py-3">
                        <span class="text-slate-400 mr-3 flex-shrink-0">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input
                            type="password"
                            name="password"
                            id="pwInput"
                            required
                            placeholder="••••••••"
                            class="w-full bg-transparent text-xs sm:text-sm font-semibold text-slate-900 outline-none placeholder:text-slate-400 pr-10"
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            onclick="togglePassword()"
                            class="absolute right-3 p-1.5 text-slate-400 hover:text-indigo-600 transition-colors focus:outline-none"
                            aria-label="Tampilkan atau sembunyikan password"
                        >
                            <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="pt-2">
                    <button type="submit" id="submitBtn" class="btn-submit w-full py-3.5 px-6 rounded-2xl text-white font-extrabold text-sm shadow-md flex items-center justify-center gap-2.5 focus:outline-none focus:ring-4 focus:ring-indigo-500/20 active:scale-95 transition-all">
                        <svg id="submitIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        <span id="submitText">Masuk ke Aplikasi</span>
                    </button>
                </div>
            </form>

            </div>

        <p class="mt-8 text-center text-xs text-slate-400">
            TaskFlow Pro &bull; Secure Authentication System
        </p>
    </div>

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

    const loginForm = document.querySelector('form');
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

