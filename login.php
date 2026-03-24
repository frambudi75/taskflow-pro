<?php
// login.php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: ' . route('dashboard'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['email']        = $user['email'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['profile_photo'] = $user['profile_photo'] ?? null;
        header('Location: ' . route('dashboard'));
        exit;
    } else {
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – TaskFlow Pro</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; margin: 0; }

        /* ===== LEFT PANEL ===== */
        .left-panel {
            background: linear-gradient(145deg, #0f172a 0%, #1e1b4b 40%, #312e81 70%, #4c1d95 100%);
            position: relative;
            overflow: hidden;
        }

        /* Animated mesh gradient blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.35;
            animation: drift 14s ease-in-out infinite;
        }
        .blob-1 { width: 350px; height: 350px; background: #6366f1; top: -80px; left: -80px; animation-delay: 0s; }
        .blob-2 { width: 280px; height: 280px; background: #8b5cf6; bottom: 50px; right: -60px; animation-delay: -5s; }
        .blob-3 { width: 200px; height: 200px; background: #06b6d4; top: 50%; left: 50%; animation-delay: -9s; }

        @keyframes drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33%       { transform: translate(30px, -30px) scale(1.08); }
            66%       { transform: translate(-20px, 20px) scale(0.95); }
        }

        /* Grid pattern overlay */
        .grid-overlay {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
        }

        /* Floating feature pills */
        .feature-pill {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 100px;
            padding: 10px 18px;
            color: rgba(255,255,255,0.85);
            font-size: 13px; font-weight: 600;
            backdrop-filter: blur(8px);
            transition: all 0.2s;
        }
        .feature-pill:hover {
            background: rgba(255,255,255,0.12);
            transform: translateX(4px);
        }
        .pill-dot { width: 8px; height: 8px; border-radius: 50%; }

        /* ===== RIGHT PANEL / Form ===== */
        .form-input {
            width: 100%;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 18px;
            font-size: 14px;
            font-family: Inter, sans-serif;
            font-weight: 500;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
        }
        .form-input::placeholder { color: #94a3b8; font-weight: 400; }
        .form-input:focus {
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.08);
        }
        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; pointer-events: none;
        }
        .form-input.has-icon { padding-left: 46px; }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            border: none;
            border-radius: 14px;
            padding: 15px;
            font-size: 15px;
            font-weight: 700;
            font-family: Inter, sans-serif;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 8px 24px rgba(99,102,241,0.35);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            box-shadow: 0 12px 32px rgba(99,102,241,0.45);
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0); }

        /* Show/hide password toggle */
        .toggle-pw {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #94a3b8;
            padding: 4px; border-radius: 8px; transition: color 0.2s;
        }
        .toggle-pw:hover { color: #6366f1; }

        /* Responsive: stack on mobile */
        @media (max-width: 767px) {
            .left-panel { display: none !important; }
            .right-panel { min-height: 100vh; }
        }
    </style>
</head>
<body class="min-h-screen flex">

    <!-- ===== LEFT PANEL — Branding ===== -->
    <div class="left-panel hidden md:flex flex-col justify-between p-12 w-[48%] flex-shrink-0">
        <!-- Animated blobs -->
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="grid-overlay"></div>

        <!-- Brand top -->
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-11 h-11 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center backdrop-blur-sm">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <span class="text-white font-black text-xl tracking-tight">TaskFlow</span>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full text-indigo-300 bg-indigo-500/20 border border-indigo-400/20">PRO</span>
            </div>
        </div>

        <!-- Center content -->
        <div class="relative z-10 flex-1 flex flex-col justify-center">
            <div class="mb-3">
                <span class="text-indigo-300 text-sm font-bold uppercase tracking-widest">Sistem Manajemen</span>
            </div>
            <h1 class="text-5xl font-black text-white leading-tight mb-6">
                Kelola semua<br>
                <span class="text-transparent bg-clip-text" style="background-image:linear-gradient(90deg,#a5b4fc,#67e8f9)">tugas & ide Anda</span><br>
                dalam satu tempat.
            </h1>
            <p class="text-slate-400 text-base leading-relaxed mb-10 max-w-sm">
                Platform produktivitas terpadu dengan kalender, catatan, dan manajemen dokumen yang intuitif.
            </p>

            <!-- Feature pills -->
            <div class="flex flex-col gap-3">
                <div class="feature-pill">
                    <span class="pill-dot" style="background:#34d399"></span>
                    Kalender & Manajemen Tugas
                </div>
                <div class="feature-pill">
                    <span class="pill-dot" style="background:#60a5fa"></span>
                    Catatan & Arsip Dokumen
                </div>
                <div class="feature-pill">
                    <span class="pill-dot" style="background:#f472b6"></span>
                    Dashboard Analitik Real-Time
                </div>
            </div>
        </div>

        <!-- Bottom tag -->
        <div class="relative z-10 text-slate-500 text-xs font-medium">
            © 2026 TaskFlow · Dibuat oleh Habib Frambudi
        </div>
    </div>

    <!-- ===== RIGHT PANEL — Form ===== -->
    <div class="right-panel flex-1 flex flex-col items-center justify-center px-6 py-12 bg-white">

        <!-- Mobile brand (only shown on mobile since left panel is hidden) -->
        <div class="flex md:hidden items-center gap-3 mb-10">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <span class="text-slate-900 font-black text-xl">TaskFlow Pro</span>
        </div>

        <div class="w-full max-w-sm">
            <!-- Heading -->
            <div class="mb-8">
                <h2 class="text-3xl font-black text-slate-900 mb-2">Selamat datang 👋</h2>
                <p class="text-slate-500 font-medium text-sm">Masuk untuk melanjutkan ke dashboard Anda.</p>
            </div>

            <!-- Error Alert -->
            <?php if ($error): ?>
            <div class="mb-6 flex items-center gap-3 bg-red-50 border border-red-100 rounded-2xl px-4 py-3">
                <div class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
                <p class="text-red-600 text-sm font-semibold"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" class="space-y-5">
                <!-- Email -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Email</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <input
                            type="email" name="email" required
                            placeholder="nama@email.com"
                            class="form-input has-icon"
                            autocomplete="email"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Password</label>
                    <div class="input-wrap">
                        <span class="input-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input
                            type="password" name="password" id="pwInput" required
                            placeholder="••••••••"
                            class="form-input has-icon"
                            autocomplete="current-password"
                            style="padding-right:46px"
                        >
                        <button type="button" class="toggle-pw" onclick="togglePw()" title="Tampilkan password">
                            <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <div class="pt-1">
                    <button type="submit" class="btn-login">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                        Masuk ke Sistem
                    </button>
                </div>
            </form>

            <!-- Divider & hint -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-slate-400 text-xs font-medium">
                    Lupa password? Hubungi administrator sistem.
                </p>
            </div>
        </div>
    </div>

</body>
<script>
function togglePw() {
    var inp  = document.getElementById('pwInput');
    var icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    } else {
        inp.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }
}
</script>
</html>
