<?php
// login.php - Executive SaaS Authentication Portal
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
                $error = 'Akun Anda saat ini dinonaktifkan oleh Administrator. Silakan hubungi tim IT atau Admin organisasi Anda.';
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
    <title>Masuk ke Portal &bull; <?php echo htmlspecialchars($app_name); ?></title>
    <link rel="icon" type="<?php echo $app_logo_info['mime']; ?>" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Ambient Aurora Backgrounds */
        .aurora-bg {
            background-color: #07090e;
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.18) 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(14, 165, 233, 0.16) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(124, 58, 237, 0.12) 0%, transparent 60%);
        }

        .dot-matrix-pattern {
            background-image: radial-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px);
            background-size: 24px 24px;
        }

        /* Frosted Glass Panels */
        .glass-card {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.85);
            box-shadow: 
                0 20px 40px -15px rgba(15, 23, 42, 0.08),
                0 1px 3px rgba(15, 23, 42, 0.03),
                inset 0 1px 0 rgba(255, 255, 255, 0.95);
        }

        .hero-glass-card {
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.15);
        }

        /* Executive Input Focus */
        .auth-input-container {
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .auth-input-container:focus-within {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.14);
            background-color: #ffffff;
        }

        /* Subtle Glow Animation */
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-6px); }
        }
        .animate-float-slow {
            animation: floatSlow 6s ease-in-out infinite;
        }

        @keyframes pulseSoft {
            0%, 100% { opacity: 0.8; }
            50% { opacity: 0.4; }
        }
        .animate-pulse-soft {
            animation: pulseSoft 3s ease-in-out infinite;
        }
    </style>
</head>
<body class="h-full bg-slate-900 text-slate-900 flex min-h-screen selection:bg-indigo-500 selection:text-white">

    <!-- ============================================================== -->
    <!-- LEFT PANEL: EXECUTIVE PRODUCT SHOWCASE & LIVE MOCKUP           -->
    <!-- ============================================================== -->
    <div class="aurora-bg hidden lg:flex lg:w-[55%] xl:w-[58%] flex-col justify-between p-12 xl:p-16 text-white relative overflow-hidden flex-shrink-0 border-r border-slate-800/80">
        
        <!-- Subtle dot matrix pattern overlay -->
        <div class="absolute inset-0 dot-matrix-pattern opacity-30 pointer-events-none"></div>

        <!-- Ambient Glow Spheres -->
        <div class="absolute -top-32 -left-32 w-[500px] h-[500px] bg-indigo-600/20 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] bg-sky-500/20 rounded-full blur-[120px] pointer-events-none"></div>

        <!-- Top Header & Brand -->
        <div class="relative z-10 flex items-center justify-between">
            <div class="flex items-center gap-3.5">
                <?php if ($logo_valid): ?>
                    <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Logo" class="h-11 max-w-[160px] object-contain drop-shadow-lg">
                <?php else: ?>
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-indigo-600/40 border border-white/20">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </div>
                <?php endif; ?>
                <div>
                    <h2 class="text-xl font-black tracking-tight text-white leading-tight"><?php echo htmlspecialchars($company_name); ?></h2>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-[10px] uppercase font-black text-indigo-300 tracking-widest">Enterprise Cloud Platform</span>
                    </div>
                </div>
            </div>

            <div class="hidden xl:inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700/80 text-[11px] font-bold text-slate-300">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                Sistem Beroperasi Normal
            </div>
        </div>

        <!-- Center Showcase & Live Glassmorphic Mockup -->
        <div class="relative z-10 my-auto py-8 max-w-xl">
            <!-- Pill Tag -->
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-500/15 border border-indigo-400/25 text-indigo-300 text-xs font-bold mb-5 shadow-xs">
                <svg class="w-3.5 h-3.5 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <span>TaskFlow 2.0 &bull; Generasi Baru Manajemen Kerja</span>
            </div>
            
            <h1 class="text-3xl xl:text-4xl font-black text-white leading-[1.2] tracking-tight mb-4">
                Satu ruang kendali untuk manajemen tugas, dokumen, dan kolaborasi tim.
            </h1>
            <p class="text-slate-300 text-sm leading-relaxed mb-8 font-medium">
                Optimalkan produktivitas organisasi Anda dengan pelacakan aktivitas presisi tinggi, penyimpanan berkas terkompresi cerdas, serta kalender kerja interaktif.
            </p>

            <!-- Live Workspace Mockup Widget -->
            <div class="hero-glass-card rounded-3xl p-5 border border-white/10 shadow-2xl animate-float-slow">
                
                <!-- Mockup Window Bar -->
                <div class="flex items-center justify-between pb-3.5 mb-4 border-b border-white/10">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500/80"></span>
                        <span class="w-3 h-3 rounded-full bg-amber-500/80"></span>
                        <span class="w-3 h-3 rounded-full bg-emerald-500/80"></span>
                        <span class="ml-2 text-xs font-bold text-slate-300">Ringkasan Aktivitas Hari Ini</span>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/60 border border-emerald-800/60 px-2 py-0.5 rounded-full">
                        Live Sync
                    </span>
                </div>

                <!-- 3 Mini Metric Badges -->
                <div class="grid grid-cols-3 gap-2.5 mb-4">
                    <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                        <div class="text-[10px] font-bold text-slate-400 uppercase">Tugas Selesai</div>
                        <div class="text-lg font-black text-white mt-0.5">12 / 14</div>
                        <div class="text-[9px] text-emerald-400 font-bold mt-0.5">85.7% On Track</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                        <div class="text-[10px] font-bold text-slate-400 uppercase">Penyimpanan</div>
                        <div class="text-lg font-black text-indigo-300 mt-0.5">348 KB</div>
                        <div class="text-[9px] text-slate-400 font-bold mt-0.5">dari 5.00 GB</div>
                    </div>
                    <div class="p-3 rounded-2xl bg-white/5 border border-white/10">
                        <div class="text-[10px] font-bold text-slate-400 uppercase">Tim Aktif</div>
                        <div class="text-lg font-black text-sky-300 mt-0.5">4 User</div>
                        <div class="text-[9px] text-emerald-400 font-bold mt-0.5">100% Online</div>
                    </div>
                </div>

                <!-- Mini Tasks List -->
                <div class="space-y-2">
                    <div class="p-2.5 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="w-4 h-4 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-black">✓</span>
                            <span class="text-slate-200 font-medium">Pengelolaan Kuota Penyimpanan Cloud Per-User</span>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-400">Tuntas</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="w-4 h-4 rounded-md bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-[10px] font-black">✓</span>
                            <span class="text-slate-200 font-medium">Manajemen Pengguna Full-Width &amp; Slide-over Drawer</span>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-400">Tuntas</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="relative z-10 flex items-center justify-between text-xs text-slate-400 border-t border-white/10 pt-6">
            <div class="flex items-center gap-4">
                <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?>.</span>
                <span class="hidden sm:inline">&bull; Hak Cipta Dilindungi</span>
            </div>
            <div class="flex items-center gap-2 text-[11px] text-slate-400">
                <svg class="w-3.5 h-3.5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Tersertifikasi Enkripsi TLS 1.3</span>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- RIGHT PANEL: FROSTED GLASS LOGIN AUTHENTICATION PORTAL         -->
    <!-- ============================================================== -->
    <div class="w-full lg:w-[45%] xl:w-[42%] flex flex-col justify-center items-center p-6 sm:p-12 md:p-16 bg-slate-900 relative overflow-hidden min-h-screen">
        
        <!-- Subtle Background Glows on Right -->
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-sky-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Mobile Header (Visible on small screens) -->
        <div class="flex lg:hidden items-center gap-3 mb-8 relative z-10">
            <?php if ($logo_valid): ?>
                <img src="<?php echo htmlspecialchars($company_logo); ?>" alt="Logo" class="h-10 max-w-[140px] object-contain">
            <?php else: ?>
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center shadow-md shadow-indigo-600/30">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
            <?php endif; ?>
            <div>
                <span class="text-white font-black text-lg tracking-tight"><?php echo htmlspecialchars($company_name); ?></span>
                <p class="text-xs text-indigo-300 font-semibold">Portal Otentikasi</p>
            </div>
        </div>

        <!-- Executive Card Container -->
        <div class="w-full max-w-md glass-card rounded-3xl p-8 sm:p-10 shadow-2xl relative z-10 border border-white/80">
            
            <!-- Greeting & Form Header -->
            <div class="mb-7">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-[10px] font-black uppercase tracking-wider mb-2.5">
                    <svg class="w-3 h-3 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Akses Aman Terenkripsi
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-tight">Selamat Datang</h2>
                <p class="text-slate-500 text-xs sm:text-sm font-medium mt-1 leading-relaxed">
                    Masukkan akun Anda untuk masuk ke sistem kerja TaskFlow Pro.
                </p>
            </div>

            <!-- Error Banner -->
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 flex items-start gap-3 text-rose-800 text-xs font-semibold shadow-xs">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" class="text-rose-600 flex-shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <div class="leading-relaxed"><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" class="space-y-4" id="mainLoginForm">
                
                <!-- Email Input -->
                <div>
                    <label for="emailInput" class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1.5">Alamat Email</label>
                    <div class="auth-input-container relative flex items-center bg-slate-50 border border-slate-200/90 rounded-2xl overflow-hidden px-4 py-3">
                        <span class="text-slate-400 mr-3 flex-shrink-0">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input
                            type="email"
                            name="email"
                            id="emailInput"
                            required
                            placeholder="nama@perusahaan.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400"
                            autocomplete="email"
                        >
                    </div>
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="pwInput" class="block text-[11px] font-black text-slate-600 uppercase tracking-wider">Kata Sandi</label>
                        <button type="button" onclick="openForgotPwModal()" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition-colors">
                            Lupa kata sandi?
                        </button>
                    </div>
                    <div class="auth-input-container relative flex items-center bg-slate-50 border border-slate-200/90 rounded-2xl overflow-hidden px-4 py-3">
                        <span class="text-slate-400 mr-3 flex-shrink-0">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input
                            type="password"
                            name="password"
                            id="pwInput"
                            required
                            placeholder="••••••••"
                            class="w-full bg-transparent text-xs sm:text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400 pr-10"
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            onclick="togglePassword()"
                            class="absolute right-3 p-1.5 text-slate-400 hover:text-indigo-600 transition-colors focus:outline-none"
                            aria-label="Tampilkan atau sembunyikan password"
                        >
                            <svg id="eyeIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
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
                <div class="pt-3">
                    <button type="submit" id="submitBtn" 
                            class="w-full py-3.5 px-6 rounded-2xl text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2.5 bg-gradient-to-r from-indigo-600 via-indigo-600 to-indigo-700 hover:from-indigo-500 hover:to-indigo-600 transition-all active:scale-[0.98]">
                        <svg id="submitIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        <span id="submitText">Masuk ke TaskFlow</span>
                    </button>
                </div>
            </form>

            <!-- Quick Demo Fill Helper Chips -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-2.5 text-center">
                    Akses Cepat Pengujian (1-Klik)
                </span>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="fillCredentials('admin@taskflow.com', 'password123')"
                            class="px-2.5 py-2 rounded-xl border border-slate-200 hover:border-indigo-500 bg-slate-50 hover:bg-indigo-50/50 text-[11px] font-bold text-slate-700 hover:text-indigo-600 text-center transition-all flex items-center justify-center gap-1.5">
                        <span>👑 Super Admin</span>
                    </button>
                    <button type="button" onclick="fillCredentials('frambudihabib@gmail.com', 'password123')"
                            class="px-2.5 py-2 rounded-xl border border-slate-200 hover:border-indigo-500 bg-slate-50 hover:bg-indigo-50/50 text-[11px] font-bold text-slate-700 hover:text-indigo-600 text-center transition-all flex items-center justify-center gap-1.5">
                        <span>👤 Anggota Tim</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Bottom System Security Pill -->
        <div class="mt-8 text-center text-xs text-slate-400 font-medium flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            <span>TaskFlow Pro Security Guard &bull; Dilindungi Otentikasi Berlapis</span>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- FORGOT PASSWORD MODAL DIALOG                                   -->
    <!-- ============================================================== -->
    <div id="forgotPwModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md" role="dialog" aria-modal="true">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 sm:p-7 shadow-2xl border border-slate-200 text-center" onclick="event.stopPropagation()">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4 border border-indigo-100">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <h3 class="text-base font-black text-slate-900 mb-1.5">Lupa Kata Sandi?</h3>
            <p class="text-xs text-slate-500 leading-relaxed mb-6 font-medium">
                Untuk alasan keamanan enterprise, reset kata sandi dikelola secara terpusat oleh Administrator. Hubungi administrator tim Anda untuk mereset kata sandi akun Anda ke sandi bawaan (<code class="bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded font-mono font-bold">password123</code>).
            </p>
            <button type="button" onclick="closeForgotPwModal()" 
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-transform active:scale-95 shadow-sm">
                Mengerti &amp; Tutup
            </button>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- JAVASCRIPT CONTROLLERS                                         -->
    <!-- ============================================================== -->
    <script>
    // Toggle Password Visibility
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

    // Quick Fill Demo Credentials
    function fillCredentials(email, password) {
        const emailEl = document.getElementById('emailInput');
        const pwEl = document.getElementById('pwInput');
        if (emailEl && pwEl) {
            emailEl.value = email;
            pwEl.value = password;
            emailEl.focus();

            // Visual highlight flash
            emailEl.closest('.auth-input-container').classList.add('ring-2', 'ring-indigo-500');
            pwEl.closest('.auth-input-container').classList.add('ring-2', 'ring-indigo-500');
            setTimeout(() => {
                emailEl.closest('.auth-input-container').classList.remove('ring-2', 'ring-indigo-500');
                pwEl.closest('.auth-input-container').classList.remove('ring-2', 'ring-indigo-500');
            }, 600);
        }
    }

    // Modal Helpers
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

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeForgotPwModal();
    });

    // Submit Loading State
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
