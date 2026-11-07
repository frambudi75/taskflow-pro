<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/dashboard.php - Executive Glassmorphic Dashboard
$user_id = (int)$_SESSION['user_id'];

// Get statistics (with caching for 10 minutes)
$cache_key_stats = "dashboard_stats_user_" . $user_id;
$dashboard_stats = getCache($cache_key_stats);

if ($dashboard_stats === null) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_todos FROM todos WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_todos = (int)$stmt->fetch()['total_todos'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_todos FROM todos WHERE user_id = ? AND completed = 1");
    $stmt->execute([$user_id]);
    $completed_todos = (int)$stmt->fetch()['completed_todos'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_notes FROM notes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_notes = (int)$stmt->fetch()['total_notes'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_docs FROM documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_docs = (int)$stmt->fetch()['total_docs'];

    $dashboard_stats = [
        'total_todos'     => $total_todos,
        'completed_todos' => $completed_todos,
        'total_notes'     => $total_notes,
        'total_docs'      => $total_docs,
    ];
    setCache($cache_key_stats, $dashboard_stats, 600);
} else {
    $total_todos     = (int)$dashboard_stats['total_todos'];
    $completed_todos = (int)$dashboard_stats['completed_todos'];
    $total_notes     = (int)$dashboard_stats['total_notes'];
    $total_docs      = (int)$dashboard_stats['total_docs'];
}

// Recent todos (next 7 days)
$stmt = $pdo->prepare("SELECT * FROM todos WHERE user_id = ? AND todo_date >= CURDATE() AND todo_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY todo_date ASC LIMIT 5");
$stmt->execute([$user_id]);
$upcoming_todos = $stmt->fetchAll();

// Recent notes
$stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY updated_at DESC LIMIT 2");
$stmt->execute([$user_id]);
$recent_notes = $stmt->fetchAll();

// Recent documents
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY upload_date DESC LIMIT 3");
$stmt->execute([$user_id]);
$recent_docs = $stmt->fetchAll();

// Recent activities (Latest 5 events across the system)
$stmt = $pdo->query("SELECT a.*, u.display_name, u.profile_photo FROM activities a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5");
$dash_recent_activities = $stmt->fetchAll();

// Admin stats
$admin_stats = null;
if (isAdmin()) {
    $cache_key_admin  = "dashboard_admin_stats";
    $admin_stats_cache = getCache($cache_key_admin);
    if ($admin_stats_cache === null) {
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $total_users = (int)$stmt->fetch()['total_users'];
        $stmt = $pdo->query("SELECT COUNT(*) as admin_users FROM users WHERE role = 'administrator'");
        $admin_users = (int)$stmt->fetch()['admin_users'];
        $stmt = $pdo->query("SELECT COUNT(*) as total_system_todos FROM todos");
        $total_system_todos = (int)$stmt->fetch()['total_system_todos'];
        $stmt = $pdo->query("SELECT COUNT(*) as total_system_notes FROM notes");
        $total_system_notes = (int)$stmt->fetch()['total_system_notes'];
        $stmt = $pdo->query("SELECT COUNT(*) as total_system_docs FROM documents");
        $total_system_docs = (int)$stmt->fetch()['total_system_docs'];
        $admin_stats = [
            'total_users'          => $total_users,
            'admin_users'          => $admin_users,
            'total_system_todos'   => $total_system_todos,
            'total_system_notes'   => $total_system_notes,
            'total_system_docs'    => $total_system_docs,
        ];
        setCache($cache_key_admin, $admin_stats, 900);
    } else {
        $admin_stats = $admin_stats_cache;
    }
}

// Avatar
$profilePhoto = $_SESSION['profile_photo'] ?? null;
$avatarSrc = $profilePhoto && file_exists(__DIR__ . '/../uploads/avatars/' . $profilePhoto)
    ? 'uploads/avatars/' . $profilePhoto
    : getAvatarDataUri($_SESSION['display_name'] ?? 'User', 48);

$pct      = $total_todos > 0 ? round(($completed_todos / $total_todos) * 100) : 0;
$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Selamat Pagi' : ($hour < 17 ? 'Selamat Siang' : 'Selamat Malam');

// Get 7-Day Trend: Productivity (Todos)
$productivity_data   = [];
$labels              = [];
$day_names           = ['Sun'=>'Min','Mon'=>'Sen','Tue'=>'Sel','Wed'=>'Rab','Thu'=>'Kam','Fri'=>'Jum','Sat'=>'Sab'];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dShort = date('D', strtotime($date));
    $dayLabel = ($day_names[$dShort] ?? $dShort) . ' (' . date('d/m', strtotime($date)) . ')';
    $labels[] = $dayLabel;

    // Completed Todos
    $stmtT = $pdo->prepare("SELECT COUNT(*) as count FROM todos WHERE user_id = ? AND completed = 1 AND todo_date = ?");
    $stmtT->execute([$user_id, $date]);
    $productivity_data[] = (int)($stmtT->fetch()['count'] ?? 0);
}

// Helper relative time
if (!function_exists('dashTimeAgo')) {
    function dashTimeAgo($datetime) {
        if (!$datetime) return '-';
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'Baru saja';
        if ($diff < 3600) return round($diff / 60) . 'm lalu';
        if ($diff < 86400) return round($diff / 3600) . 'j lalu';
        if ($diff < 172800) return 'Kemarin';
        return date('d/m', $time);
    }
}
?>

<style>
@keyframes barFill {
    from { width: 0 !important; }
}

/* Glassmorphism surface with specular highlight */
.glass-surface {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05), inset 0 1px 0 rgba(255, 255, 255, 1);
    border-radius: 20px;
    position: relative;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.dark .glass-surface {
    background: rgba(15, 23, 42, 0.82);
    border-color: rgba(30, 41, 59, 0.85);
    box-shadow: 0 6px 24px -2px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.06);
}

.dash-stat-card {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(226, 232, 240, 0.85);
    border-radius: 20px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 14px;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 1);
}
.dark .dash-stat-card {
    background: rgba(15, 23, 42, 0.82);
    border-color: rgba(30, 41, 59, 0.85);
    box-shadow: 0 6px 20px -2px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.05);
}
.dash-stat-card:hover { 
    transform: translateY(-3px); 
    border-color: #cbd5e1;
    box-shadow: 0 14px 28px -4px rgba(15, 23, 42, 0.09); 
}
.dark .dash-stat-card:hover {
    border-color: #334155;
    box-shadow: 0 14px 28px -4px rgba(0, 0, 0, 0.45);
}

.dash-stat-card .stat-icon {
    transition: transform 0.25s ease;
}
.dash-stat-card:hover .stat-icon {
    transform: scale(1.08);
}

.d-section-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 22px; border-bottom: 1px solid rgba(226, 232, 240, 0.8);
}
.dark .d-section-head { border-color: rgba(30, 41, 59, 0.8); }
.d-section-head h3 { font-size: 13.5px; font-weight: 800; color: #0f172a; margin: 0; }
.dark .d-section-head h3 { color: #f8fafc; }
.d-section-head a { 
    font-size: 12px; font-weight: 700; color: #4f46e5; text-decoration: none; 
    transition: all 0.2s;
}
.dark .d-section-head a { color: #818cf8; }
.d-section-head a:hover { 
    color: #4338ca; transform: translateX(2px);
}

.d-row {
    display: flex; align-items: center; gap: 13px;
    padding: 13px 20px; border-bottom: 1px solid rgba(241, 245, 249, 0.9);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.dark .d-row { border-color: rgba(30, 41, 59, 0.7); }
.d-row:last-child { border-bottom: none; }
.d-row:hover { 
    background: rgba(248, 250, 252, 0.8); 
    transform: translateX(3px);
}
.dark .d-row:hover { 
    background: rgba(255, 255, 255, 0.03); 
}

.pb-bg { 
    background: rgba(241, 245, 249, 0.9); 
    border-radius: 100px; height: 5px; margin-top: 6px; overflow: hidden;
}
.dark .pb-bg { background: #1e293b; }
.pb-fill { 
    height: 5px; border-radius: 100px; 
    transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    animation: barFill 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.chart-container { position: relative; height: 300px; width: 100%; padding: 12px 18px 18px; }
</style>

<div class="p-5 md:p-8 w-full max-w-[1600px] mx-auto">

    <!-- WELCOME / DASHBOARD HERO BANNER (Executive Night-Sky Gradient with Ambient Glow) -->
    <div class="mb-7 p-6 md:p-8 rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 border border-indigo-900/40 shadow-xl relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <!-- Ambient radial highlights inside banner -->
        <div class="absolute -top-20 -right-20 w-80 h-80 bg-indigo-500/25 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-20 left-1/4 w-64 h-64 bg-purple-500/15 rounded-full blur-2xl pointer-events-none"></div>
        
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-indigo-200 text-xs font-extrabold uppercase tracking-widest mb-3">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <?php echo date('l, d F Y'); ?>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-white leading-tight tracking-tight mb-2">
                <?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?> 👋
            </h1>
            <p class="text-slate-300 text-sm font-medium max-w-xl leading-relaxed">
                <?php echo ($total_todos - $completed_todos) > 0
                    ? ($total_todos - $completed_todos) . ' tugas masih menunggu diselesaikan hari ini. Tetap fokus dan pertahankan ritme produktif Anda!'
                    : 'Luar biasa! Seluruh target harian Anda telah tercapai dengan sukses.'; ?>
            </p>
        </div>

        <div class="relative z-10 flex items-center gap-3.5 self-start md:self-center flex-shrink-0 bg-white/5 backdrop-blur-md p-2.5 pr-4 rounded-2xl border border-white/10">
            <img src="<?php echo $avatarSrc; ?>" alt="Avatar Pengguna"
                 class="w-12 h-12 rounded-xl object-cover border-2 border-indigo-400/40 shadow-md">
            <div class="text-left">
                <div class="text-white font-extrabold text-sm tracking-wide leading-tight"><?php echo htmlspecialchars($_SESSION['display_name'] ?? ''); ?></div>
                <div class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-indigo-500/30 text-indigo-200 border border-indigo-400/20">
                    <?php echo $_SESSION['role'] ?? 'MEMBER'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- STAT CARDS (Refined Executive Frosted Glass with Colored Hairline Tops) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-5 mb-7">
        
        <!-- CARD 1: TUGAS -->
        <div class="dash-stat-card border-t-[3px] border-t-indigo-500">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Tugas</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
            </div>
            <div>
                <div class="flex items-baseline justify-between">
                    <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $total_todos; ?></div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">🎯 Aktif</span>
                </div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2"><?php echo $completed_todos; ?> selesai · <?php echo $pct; ?>%</div>
            </div>
            <div class="pb-bg"><div class="pb-fill bg-indigo-500" style="width:<?php echo $pct; ?>%"></div></div>
        </div>

        <!-- CARD 2: PROGRESS -->
        <div class="dash-stat-card border-t-[3px] border-t-emerald-500">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Penyelesaian</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-100 dark:border-emerald-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
            </div>
            <div>
                <div class="flex items-baseline justify-between">
                    <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $pct; ?>%</div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/40">✓ On Track</span>
                </div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2"><?php echo $completed_todos; ?> dari <?php echo $total_todos; ?> tercapai</div>
            </div>
            <div class="pb-bg"><div class="pb-fill bg-emerald-500" style="width:<?php echo $pct; ?>%"></div></div>
        </div>

        <!-- CARD 3: CATATAN -->
        <div class="dash-stat-card border-t-[3px] border-t-violet-500">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Catatan &amp; Ide</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center border border-violet-100 dark:border-violet-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                </div>
            </div>
            <div>
                <div class="flex items-baseline justify-between">
                    <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $total_notes; ?></div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-50 dark:bg-violet-950/50 text-violet-700 dark:text-violet-300 border border-violet-200/60 dark:border-violet-800/40">📝 Catatan</span>
                </div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2">Dokumentasi tersimpan</div>
            </div>
            <div class="pb-bg"><div class="pb-fill bg-violet-500" style="width:<?php echo min(100, $total_notes * 20); ?>%"></div></div>
        </div>

        <!-- CARD 4: DOKUMEN -->
        <div class="dash-stat-card border-t-[3px] border-t-sky-500">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dokumen Arsip</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
            </div>
            <div>
                <div class="flex items-baseline justify-between">
                    <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $total_docs; ?></div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-300 border border-sky-200/60 dark:border-sky-800/40">🔒 Storage</span>
                </div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2">Berkas terkompresi &amp; aman</div>
            </div>
            <div class="pb-bg"><div class="pb-fill bg-sky-500" style="width:<?php echo min(100, $total_docs * 20); ?>%"></div></div>
        </div>

    </div>

    <!-- MAIN GRID (8 Cols Left, 4 Cols Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT COLUMN (8 Cols) -->
        <div class="lg:col-span-8 flex flex-col gap-6">

            <!-- Productivity Chart -->
            <div class="glass-surface overflow-hidden">
                <div class="d-section-head flex-wrap gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-900/50">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Grafik Tren Produktivitas</h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium mt-0.5">Tren penyelesaian tugas dalam 7 hari terakhir</p>
                        </div>
                    </div>
                    <span class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60">
                        7 Hari Terakhir
                    </span>
                </div>
                <div class="chart-container">
                    <canvas id="productivityChart"></canvas>
                </div>
            </div>

            <!-- Upcoming Tasks (7 Days) -->
            <div class="glass-surface overflow-hidden">
                <div class="d-section-head">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-900/50">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Tugas Mendatang (7 Hari ke Depan)</h3>
                    </div>
                    <a href="?page=calendar" class="p-1.5 flex items-center gap-1 font-bold text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">Lihat Kalender &rarr;</a>
                </div>
                <?php if (empty($upcoming_todos)): ?>
                <div class="p-10 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto mb-3 text-slate-400">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <p class="text-slate-600 dark:text-slate-300 font-bold text-xs mb-2">Tidak ada tugas terjadwal untuk 7 hari ke depan.</p>
                    <a href="?page=calendar" class="text-indigo-600 dark:text-indigo-400 font-extrabold text-xs hover:underline">+ Buat Tugas Baru</a>
                </div>
                <?php else: ?>
                    <?php foreach($upcoming_todos as $todo):
                        $dl = (int)((strtotime($todo['todo_date']) - strtotime('today')) / 86400);
                        if ($dl === 0)      $badge = ['Hari Ini','text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/50 border-red-200/60 dark:border-red-900/50'];
                        elseif ($dl === 1)  $badge = ['Besok','text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/50 border-amber-200/60 dark:border-amber-900/50'];
                        else               $badge = [$dl.' hari lagi','text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/50 border-indigo-200/60 dark:border-indigo-900/50'];
                    ?>
                    <div class="d-row">
                        <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center border <?php echo $todo['completed'] ? 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-300 text-emerald-600' : 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-400'; ?>">
                            <?php if ($todo['completed']): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else: ?>
                                <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-extrabold text-[13px] text-slate-900 dark:text-white truncate <?php echo $todo['completed'] ? 'line-through opacity-60' : ''; ?>">
                                <?php echo htmlspecialchars($todo['title']); ?>
                            </p>
                            <p class="text-slate-500 dark:text-slate-400 text-[11px] font-semibold mt-0.5">
                                <?php echo date('d M Y', strtotime($todo['todo_date'])); ?>
                            </p>
                        </div>
                        <span class="text-[10px] font-black px-2.5 py-1 rounded-lg border <?php echo $badge[1]; ?>">
                            <?php echo $badge[0]; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Notes -->
            <div class="glass-surface overflow-hidden">
                <div class="d-section-head">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center border border-violet-100 dark:border-violet-900/50">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Catatan &amp; Ide Terbaru</h3>
                    </div>
                    <a href="?page=notes" class="p-1.5 flex items-center gap-1 font-bold text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-700">Buka Semua &rarr;</a>
                </div>
                <?php if (empty($recent_notes)): ?>
                <div class="p-8 text-center">
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold">Belum ada catatan tersimpan.
                        <a href="?page=notes" class="text-indigo-600 dark:text-indigo-400 underline font-bold ml-1">Mulai Tulis</a>
                    </p>
                </div>
                <?php else: ?>
                <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <?php foreach($recent_notes as $note): ?>
                    <a href="?page=notes" class="group flex flex-col justify-between p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white/60 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-md transition-all duration-200">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    <?php echo date('d M Y', strtotime($note['updated_at'])); ?>
                                </span>
                                <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors line-clamp-1 mb-1.5">
                                <?php echo htmlspecialchars($note['title']); ?>
                            </h4>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                <?php echo htmlspecialchars(substr($note['content'], 0, 90)); ?>
                            </p>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                            <span>Buka Catatan</span>
                            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT COLUMN (4 Cols) -->
        <div class="lg:col-span-4 flex flex-col gap-6">

            <!-- Quick Actions -->
            <div class="glass-surface overflow-hidden">
                <div class="d-section-head">
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Aksi Cepat</h3>
                </div>
                <div class="p-3.5 flex flex-col gap-2.5">
                    <a href="?page=calendar" class="group flex items-center justify-between p-3 rounded-xl border border-slate-200/70 dark:border-slate-800 bg-white/50 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div>
                                <div class="text-[13px] font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Buat Tugas Baru</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">Atur jadwal dan deadline</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    <a href="?page=notes" class="group flex items-center justify-between p-3 rounded-xl border border-slate-200/70 dark:border-slate-800 bg-white/50 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-violet-50 dark:bg-violet-950/60 border border-violet-100 dark:border-violet-900/50 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <div class="text-[13px] font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Tulis Catatan</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">Simpan ide dan dokumen</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    <a href="?page=documents" class="group flex items-center justify-between p-3 rounded-xl border border-slate-200/70 dark:border-slate-800 bg-white/50 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-950/60 border border-sky-100 dark:border-sky-900/50 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-[13px] font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Upload Dokumen</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">Simpan berkas dan bagikan tautan</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>

            <!-- AKTIVITAS TERKINI (Live Activity Feed Widget) -->
            <div class="glass-surface overflow-hidden">
                <div class="d-section-head">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Aktivitas Terkini</h3>
                    </div>
                    <a href="?page=activities" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 flex items-center gap-1">
                        Semua Log &rarr;
                    </a>
                </div>
                <?php if (empty($dash_recent_activities)): ?>
                <div class="p-6 text-center text-slate-500 text-xs">
                    Belum ada riwayat aktivitas terbaru.
                </div>
                <?php else: ?>
                <div class="divide-y divide-slate-100 dark:divide-slate-800/80">
                    <?php foreach($dash_recent_activities as $act): 
                        $tType = strtolower($act['target_type'] ?? '');
                        if (str_contains($tType, 'auth') || str_contains($tType, 'login')) {
                            $badgeColor = 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200/50';
                            $icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>';
                        } elseif (str_contains($tType, 'doc') || str_contains($tType, 'file')) {
                            $badgeColor = 'bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 border-sky-200/50';
                            $icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>';
                        } elseif (str_contains($tType, 'todo') || str_contains($tType, 'task')) {
                            $badgeColor = 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200/50';
                            $icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
                        } else {
                            $badgeColor = 'bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border-indigo-200/50';
                            $icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                        }
                    ?>
                    <div class="p-3.5 flex items-start gap-3 hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                        <div class="w-7 h-7 rounded-lg border <?php echo $badgeColor; ?> flex items-center justify-center shrink-0 mt-0.5">
                            <?php echo $icon; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[12px] font-bold text-slate-800 dark:text-slate-200 line-clamp-1 leading-snug">
                                <?php echo htmlspecialchars($act['action']); ?>
                            </p>
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-slate-500 dark:text-slate-400 font-semibold">
                                <span><?php echo htmlspecialchars($act['display_name'] ?? 'Sistem'); ?></span>
                                <span>•</span>
                                <span><?php echo dashTimeAgo($act['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Docs -->
            <div class="glass-surface overflow-hidden">
                <div class="d-section-head">
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Dokumen Terbaru</h3>
                    <a href="?page=documents" class="p-1 flex items-center">Lihat Semua</a>
                </div>
                <?php if (empty($recent_docs)): ?>
                <div class="p-6 text-center">
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-semibold">Belum ada dokumen tersimpan.</p>
                </div>
                <?php else: ?>
                    <?php foreach($recent_docs as $doc): ?>
                    <div class="d-row">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 border border-sky-100 dark:border-sky-900/50 text-sky-600 dark:text-sky-400 flex-shrink-0 flex items-center justify-center">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-extrabold text-slate-900 dark:text-white text-[12.5px] truncate"><?php echo htmlspecialchars($doc['name']); ?></p>
                            <p class="text-slate-500 dark:text-slate-400 text-[11px] font-semibold mt-0.5"><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Admin Stats -->
            <?php if ($admin_stats): ?>
            <div class="bg-gradient-to-br from-slate-900 to-indigo-950 text-white rounded-3xl p-6 relative overflow-hidden border border-indigo-900/50 shadow-xl">
                <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="relative z-10">
                    <h3 class="text-white text-sm font-extrabold mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-indigo-500/20 border border-indigo-400/20 flex items-center justify-center text-indigo-300">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        Statistik TaskFlow Pro
                    </h3>
                    <?php foreach([
                        ['Total Pengguna Terdaftar', $admin_stats['total_users']],
                        ['Administrator',           $admin_stats['admin_users']],
                        ['Total Tugas Sistem',      $admin_stats['total_system_todos']],
                        ['Total Catatan Tim',       $admin_stats['total_system_notes']],
                        ['Total File Dokumen',      $admin_stats['total_system_docs']],
                    ] as $r): ?>
                    <div class="flex justify-between items-center py-2.5 border-b border-white/10 text-xs">
                        <span class="text-slate-300 font-semibold"><?php echo $r[0]; ?></span>
                        <span class="text-white font-black text-sm"><?php echo $r[1]; ?></span>
                    </div>
                    <?php endforeach; ?>
                    <a href="?page=admin"
                       class="mt-5 min-h-[42px] flex items-center justify-center rounded-xl font-bold text-xs text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                        Kelola Pengguna Sistem &rarr;
                    </a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
(function() {
    var labelsData = <?php echo json_encode($labels); ?>;
    var todosData = <?php echo json_encode($productivity_data); ?>;

    var chartCanvas = document.getElementById('productivityChart');
    if (!chartCanvas) return;
    var ctx = chartCanvas.getContext('2d');
    
    if (window.taskflowProductivityChart) {
        window.taskflowProductivityChart.destroy();
    }

    var grad = ctx.createLinearGradient(0, 0, 0, 260);
    grad.addColorStop(0, 'rgba(99, 102, 241, 0.30)');
    grad.addColorStop(1, 'rgba(99, 102, 241, 0.00)');

    window.taskflowProductivityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labelsData,
            datasets: [
                {
                    label: 'Tugas Selesai',
                    data: todosData,
                    borderColor: '#4f46e5',
                    backgroundColor: grad,
                    borderWidth: 2.8,
                    tension: 0.38,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4f46e5',
                    pointBorderWidth: 2.5,
                    pointRadius: 4.5,
                    pointHoverRadius: 7,
                    pointHoverBackgroundColor: '#4f46e5',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 14,
                        font: { size: 12, weight: '700' },
                        color: '#475569'
                    }
                },
                tooltip: {
                    padding: 10,
                    cornerRadius: 10,
                    boxPadding: 6
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: '#64748b',
                        font: { size: 11, weight: '600' }
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.6)'
                    }
                },
                x: {
                    ticks: {
                        color: '#475569',
                        font: { size: 11, weight: '600' }
                    },
                    grid: { display: false }
                }
            }
        }
    });
})();
</script>
