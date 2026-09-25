<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/reset-password.php
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = $success = '';

$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validateCSRF($_POST['csrf_token'])) {
        $current_pass = $_POST['current_password'];
        $new_pass     = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (!password_verify($current_pass, $user['password'])) {
            $error = 'Password saat ini salah.';
        } elseif (strlen($new_pass) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_pass, $user_id])) {
                $success = 'Password berhasil diubah!';
            } else {
                $error = 'Gagal mengubah password.';
            }
        }
    } else {
        $error = 'CSRF token tidak valid.';
    }
}

// Strength helper
$strength_rules = [
    'min8'    => ['label' => 'Min. 8 karakter',     'pattern' => '.{8,}'],
    'upper'   => ['label' => 'Huruf kapital',        'pattern' => '[A-Z]'],
    'number'  => ['label' => 'Angka',                'pattern' => '[0-9]'],
    'special' => ['label' => 'Karakter spesial',     'pattern' => '[!@#$%^&*]'],
];
?>

<div class="p-5 md:p-8 max-w-5xl mx-auto">

    <!-- Hero Header -->
    <div class="rounded-3xl p-7 md:p-10 mb-7 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5 bg-gradient-to-br from-slate-950 via-purple-950 to-indigo-950">
        <div class="relative z-10">
            <div class="text-violet-300 text-[10px] font-black uppercase tracking-widest mb-2">Keamanan Akun</div>
            <h1 class="text-2xl md:text-3xl font-black text-white leading-tight mb-1">Ubah Password</h1>
            <p class="text-violet-200 text-sm font-medium">Perbarui kata sandi akun Anda dengan aman.</p>
        </div>
        <div class="relative z-10 flex-shrink-0">
            <a href="?page=profile"
               class="flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold text-white transition-all bg-white/10 hover:bg-white/15 border border-white/20 focus:outline-none focus:ring-2 focus:ring-purple-400">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Kembali ke Profil
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

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left: Tips & info -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-[2rem] border border-slate-100 p-7 shadow-sm">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-5 bg-purple-600 text-white">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h3 class="font-black text-slate-900 text-base mb-1">Tips Password Kuat</h3>
                <p class="text-slate-500 text-xs font-medium mb-6">Buat password yang sulit ditebak untuk melindungi akun Anda.</p>
                <div class="space-y-3">
                    <?php foreach($strength_rules as $key => $rule): ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50">
                        <div id="rule_<?php echo $key; ?>" class="w-5 h-5 rounded-lg flex items-center justify-center flex-shrink-0 transition-all bg-slate-200">
                            <svg id="rule_icon_<?php echo $key; ?>" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="3"><line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/></svg>
                        </div>
                        <span class="text-xs font-bold text-slate-600 transition-colors" id="rule_label_<?php echo $key; ?>"><?php echo $rule['label']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Strength bar -->
                <div class="mt-6">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Kekuatan Password</span>
                        <span id="strengthLabel" class="text-[10px] font-black text-slate-400 uppercase tracking-widest">-</span>
                    </div>
                    <div class="grid grid-cols-4 gap-1">
                        <?php for($i=0;$i<4;$i++): ?>
                        <div id="str_<?php echo $i; ?>" class="h-1.5 rounded-full transition-all duration-300 bg-slate-200"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Form -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-[2rem] border border-slate-100 p-7 md:p-9 shadow-sm">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center bg-purple-600 text-white">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-900">Ganti Password</h3>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Keamanan Akun</p>
                    </div>
                </div>

                <form method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Current Password -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Password Saat Ini</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input type="password" name="current_password" id="currentPw" required
                                   placeholder="Password lama"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-12 py-4 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all">
                            <button type="button" aria-label="Tampilkan atau sembunyikan password saat ini" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition-colors focus:outline-none"
                                    onclick="togglePw('currentPw',this)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- New Password -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Password Baru</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                            </span>
                            <input type="password" name="new_password" id="newPw" required
                                   placeholder="Min. 6 karakter"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-12 py-4 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all"
                                   oninput="checkStrength(this.value)">
                            <button type="button" aria-label="Tampilkan atau sembunyikan password baru" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition-colors focus:outline-none"
                                    onclick="togglePw('newPw',this)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Konfirmasi Password</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            </span>
                            <input type="password" name="confirm_password" id="confirmPw" required
                                   placeholder="Ulangi password baru"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-11 pr-12 py-4 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-purple-500 transition-all"
                                   oninput="checkMatch()">
                            <button type="button" aria-label="Tampilkan atau sembunyikan konfirmasi password" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition-colors focus:outline-none"
                                    onclick="togglePw('confirmPw',this)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        <p id="matchMsg" class="text-xs font-bold mt-2 hidden"></p>
                    </div>

                    <button type="submit"
                            class="w-full py-4 rounded-2xl font-bold text-white transition-all active:scale-95 flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Simpan Password Baru
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    window.togglePw = function(id, btn) {
        const inp = document.getElementById(id);
        if (!inp) return;
        const showing = inp.type === 'text';
        inp.type = showing ? 'password' : 'text';
        btn.querySelector('svg').innerHTML = showing
            ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'
            : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';
    };

    const rules = {
        min8:    { pattern: /.{8,}/,     el: 'rule_min8'    },
        upper:   { pattern: /[A-Z]/,     el: 'rule_upper'   },
        number:  { pattern: /[0-9]/,     el: 'rule_number'  },
        special: { pattern: /[!@#$%^&*]/,el: 'rule_special' },
    };

    const strColors  = ['#dc2626','#ea580c','#ca8a04','#16a34a'];
    const strLabels  = ['Lemah','Cukup','Bagus','Kuat'];

    window.checkStrength = function(val) {
        let score = 0;
        for (const [key, rule] of Object.entries(rules)) {
            const pass = rule.pattern.test(val);
            const box  = document.getElementById(rule.el);
            const icon = document.getElementById('rule_icon_' + key);
            const lbl  = document.getElementById('rule_label_' + key);
            if (box && icon && lbl) {
                if (pass) {
                    box.style.background  = '#dcfce7';
                    icon.setAttribute('stroke','#16a34a');
                    icon.innerHTML = '<polyline points="20 6 9 17 4 12"/>';
                    lbl.style.color = '#15803d';
                    score++;
                } else {
                    box.style.background  = '#e2e8f0';
                    icon.setAttribute('stroke','#94a3b8');
                    icon.innerHTML = '<line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/>';
                    lbl.style.color = '#64748b';
                }
            }
        }
        for (let i = 0; i < 4; i++) {
            const bar = document.getElementById('str_' + i);
            if (bar) {
                bar.style.background = i < score ? strColors[score - 1] : '#e2e8f0';
            }
        }
        const lblEl = document.getElementById('strengthLabel');
        if (lblEl) {
            lblEl.textContent = score > 0 ? strLabels[score - 1] : '-';
            lblEl.style.color = score > 0 ? strColors[score - 1] : '#94a3b8';
        }
    };

    window.checkMatch = function() {
        const nwEl = document.getElementById('newPw');
        const cfEl = document.getElementById('confirmPw');
        const msg = document.getElementById('matchMsg');
        if (!nwEl || !cfEl || !msg) return;
        const nw = nwEl.value;
        const cf = cfEl.value;
        if (!cf) { msg.classList.add('hidden'); return; }
        msg.classList.remove('hidden');
        if (nw === cf) {
            msg.textContent = '✓ Password cocok';
            msg.style.color = '#16a34a';
        } else {
            msg.textContent = '✗ Password tidak cocok';
            msg.style.color = '#dc2626';
        }
    };
})();
</script>
