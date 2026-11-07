<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/dashboard.php - Main Dashboard with Work Hours & Daily Report Reminder
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
$stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY updated_at DESC LIMIT 3");
$stmt->execute([$user_id]);
$recent_notes = $stmt->fetchAll();

// Recent documents
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY upload_date DESC LIMIT 3");
$stmt->execute([$user_id]);
$recent_docs = $stmt->fetchAll();

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
    : getAvatarDataUri($_SESSION['display_name'] ?? 'User', 44);

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
?>

<style>
@keyframes barFill {
    from { width: 0 !important; }
}

.dash-stat {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: relative;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
}
.dark .dash-stat {
    background: #0f172a;
    border-color: #1e293b;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}
.dash-stat:hover { 
    transform: translateY(-3px); 
    border-color: #cbd5e1;
    box-shadow: 0 12px 26px -4px rgba(15, 23, 42, 0.08); 
}
.dark .dash-stat:hover {
    border-color: #334155;
    box-shadow: 0 12px 26px -4px rgba(0, 0, 0, 0.4);
}

.stat-icon {
    transition: transform 0.25s ease;
}
.dash-stat:hover .stat-icon {
    transform: scale(1.08);
}

.d-section {
    background: #ffffff; border: 1px solid #e2e8f0;
    border-radius: 20px; overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
    transition: all 0.25s ease;
}
.dark .d-section {
    background: #0f172a; border-color: #1e293b;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}
.d-section:hover {
    border-color: #cbd5e1;
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.06);
}
.dark .d-section:hover {
    border-color: #334155;
    box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.4);
}

.d-section-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 24px; border-bottom: 1px solid #e2e8f0;
}
.dark .d-section-head { border-color: #1e293b; }
.d-section-head h3 { font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; }
.dark .d-section-head h3 { color: #f8fafc; }
.d-section-head a { 
    font-size: 12px; font-weight: 700; color: #4f46e5; text-decoration: none; 
    transition: all 0.2s;
}
.d-section-head a:hover { 
    color: #4338ca; transform: translateX(2px);
}

.d-row {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 24px; border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.dark .d-row { border-color: #1e293b; }
.d-row:last-child { border-bottom: none; }
.d-row:hover { 
    background: #f8fafc; 
    transform: translateX(4px);
}
.dark .d-row:hover { 
    background: rgba(255, 255, 255, 0.02); 
}

.q-btn {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-radius: 16px;
    text-decoration: none; 
    background: #ffffff;
    border: 1px solid #e2e8f0;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.dark .q-btn {
    background: #1e293b;
    border-color: #334155;
}
.q-btn:hover { 
    border-color: #cbd5e1;
    background: #f8fafc;
    transform: translateX(4px); 
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
}
.dark .q-btn:hover {
    border-color: #475569;
    background: #273549;
}
.q-btn:active {
    transform: scale(0.99);
}

.pb-bg { 
    background: #f1f5f9; 
    border-radius: 100px; height: 5px; margin-top: 4px; overflow: hidden;
}
.dark .pb-bg { background: #1e293b; }
.pb-fill { 
    height: 5px; border-radius: 100px; background: #4f46e5; 
    transition: width 0.8s cubic-bezier(0.16, 1, 0.3, 1);
    animation: barFill 0.9s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Chart specific override */
.chart-container { position: relative; height: 320px; width: 100%; padding: 14px 20px 20px; }
</style>

<div class="p-5 md:p-8 w-full max-w-[1600px] mx-auto">

    <!-- WELCOME / DASHBOARD HEADER (Airy Executive Style) -->
    <div class="mb-7 p-6 md:p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-extrabold uppercase tracking-widest mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <?php echo date('l, d F Y'); ?>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white leading-tight tracking-tight mb-1.5">
                <?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">
                <?php echo ($total_todos - $completed_todos) > 0
                    ? ($total_todos - $completed_todos) . ' tugas menunggu untuk diselesaikan hari ini. Terus produktif!'
                    : 'Semua tugas telah diselesaikan dengan sangat baik.'; ?>
            </p>
        </div>
        <div class="flex items-center gap-3.5 self-start md:self-center flex-shrink-0">
            <div class="text-right hidden sm:block">
                <div class="text-slate-900 dark:text-white font-extrabold text-sm tracking-wide"><?php echo htmlspecialchars($_SESSION['display_name'] ?? ''); ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-xs font-bold uppercase tracking-wider"><?php echo $_SESSION['role'] ?? ''; ?></div>
            </div>
            <img src="<?php echo $avatarSrc; ?>" alt="Avatar Pengguna"
                 class="w-12 h-12 rounded-2xl object-cover border-2 border-slate-200 dark:border-slate-700 shadow-sm">
        </div>
    </div>

    <!-- STAT CARDS (Executive Clean 4-Columns) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-5 mb-7">
        <!-- TUGAS -->
        <div class="dash-stat">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Tugas</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center border border-slate-200/60 dark:border-slate-700/60">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
            </div>
            <div>
                <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $total_todos; ?></div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2"><?php echo $completed_todos; ?> selesai · <?php echo $pct; ?>%</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo $pct; ?>%"></div></div>
        </div>

        <!-- PROGRESS -->
        <div class="dash-stat">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Penyelesaian</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center border border-slate-200/60 dark:border-slate-700/60">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
            </div>
            <div>
                <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $pct; ?>%</div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2"><?php echo $completed_todos; ?> dari <?php echo $total_todos; ?> selesai</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo $pct; ?>%"></div></div>
        </div>

        <!-- CATATAN -->
        <div class="dash-stat">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Catatan</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center border border-slate-200/60 dark:border-slate-700/60">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                </div>
            </div>
            <div>
                <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $total_notes; ?></div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2">Ide &amp; Dokumentasi</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo min(100, $total_notes * 10); ?>%"></div></div>
        </div>

        <!-- DOKUMEN -->
        <div class="dash-stat">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dokumen</span>
                <div class="stat-icon w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center border border-slate-200/60 dark:border-slate-700/60">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
            </div>
            <div>
                <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $total_docs; ?></div>
                <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 mt-2">Berkas Tersimpan</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo min(100, $total_docs * 10); ?>%"></div></div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT (8 Cols) -->
        <div class="lg:col-span-8 flex flex-col gap-6">

            <!-- Productivity Chart -->
            <div class="d-section">
                <div class="d-section-head flex-wrap gap-2">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Grafik Produktivitas Tugas</h3>
                        <p class="text-xs text-slate-600 dark:text-slate-300 font-medium mt-0.5">Tren 7 hari penyelesaian tugas</p>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="productivityChart"></canvas>
                </div>
            </div>

            <!-- Upcoming Tasks -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Tugas Mendatang (7 Hari ke Depan)</h3>
                    <a href="?page=calendar" class="p-2 min-h-[44px] flex items-center">Lihat Semua</a>
                </div>
                <?php if (empty($upcoming_todos)): ?>
                <div style="padding:40px;text-align:center">
                    <div style="width:52px;height:52px;border-radius:16px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <p style="color:#475569;font-weight:700;font-size:13px;margin:0 0 10px">Tidak ada tugas dalam 7 hari mendatang.</p>
                    <a href="?page=calendar" style="color:#4f46e5;font-weight:800;font-size:13px;text-decoration:none">+ Buat Tugas Baru</a>
                </div>
                <?php else: ?>
                    <?php foreach($upcoming_todos as $todo):
                        $dl = (int)((strtotime($todo['todo_date']) - strtotime('today')) / 86400);
                        if ($dl === 0)      $badge = ['Hari Ini','#991b1b','#fee2e2'];
                        elseif ($dl === 1)  $badge = ['Besok','#92400e','#fef3c7'];
                        else               $badge = [$dl.' hari lagi','#3730a3','#e0e7ff'];
                    ?>
                    <div class="d-row">
                        <div style="width:36px;height:36px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
                                     background:<?php echo $todo['completed'] ? '#dcfce7' : '#f8fafc'; ?>;
                                     border:1.5px solid <?php echo $todo['completed'] ? '#86efac' : '#cbd5e1'; ?>">
                            <?php if ($todo['completed']): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else: ?>
                                <div style="width:8px;height:8px;border-radius:50%;background:#64748b"></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <p style="font-weight:800;color:#0f172a;font-size:13.5px;margin:0;<?php echo $todo['completed'] ? 'text-decoration:line-through;opacity:0.6' : ''; ?>"
                               class="truncate"><?php echo htmlspecialchars($todo['title']); ?></p>
                            <p style="color:#64748b;font-size:12px;font-weight:600;margin:2px 0 0"><?php echo date('d M Y', strtotime($todo['todo_date'])); ?></p>
                        </div>
                        <span style="font-size:11px;font-weight:800;padding:4px 10px;border-radius:8px;flex-shrink:0;color:<?php echo $badge[1]; ?>;background:<?php echo $badge[2]; ?>"><?php echo $badge[0]; ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Notes -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Catatan &amp; Ide Terbaru</h3>
                    <a href="?page=notes" class="p-2 min-h-[44px] flex items-center">Lihat Semua</a>
                </div>
                <?php if (empty($recent_notes)): ?>
                <div style="padding:28px;text-align:center">
                    <p style="color:#475569;font-size:13px;font-weight:700;margin:0">Belum ada catatan.
                        <a href="?page=notes" style="color:#4f46e5;text-decoration:underline;font-weight:800;margin-left:4px">Tulis catatan</a>
                    </p>
                </div>
                <?php else: ?>
                <div class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <?php foreach($recent_notes as $note): ?>
                    <a href="?page=notes" class="group flex flex-col justify-between p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-sm transition-all duration-200">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    <?php echo date('d M Y', strtotime($note['updated_at'])); ?>
                                </span>
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500/80"></span>
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

        <!-- RIGHT (4 Cols) -->
        <div class="lg:col-span-4 flex flex-col gap-6">

            <!-- Quick Actions -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Aksi Cepat</h3>
                </div>
                <div class="p-3.5 flex flex-col gap-2.5">
                    <a href="?page=calendar" class="group flex items-center justify-between p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 flex items-center justify-center shrink-0 group-hover:border-indigo-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors shadow-sm">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div>
                                <div class="text-[13px] font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Buat Tugas Baru</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">Atur jadwal dan deadline</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    <a href="?page=notes" class="group flex items-center justify-between p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 flex items-center justify-center shrink-0 group-hover:border-indigo-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors shadow-sm">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <div class="text-[13px] font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Tulis Catatan</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">Simpan ide dan dokumen</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    <a href="?page=documents" class="group flex items-center justify-between p-3 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 hover:bg-white dark:hover:bg-slate-800 hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:shadow-sm transition-all duration-200">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 flex items-center justify-center shrink-0 group-hover:border-indigo-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors shadow-sm">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div>
                                <div class="text-[13px] font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">Upload Dokumen</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">Simpan file arsip</div>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 group-hover:text-indigo-500 group-hover:translate-x-0.5 transition-all" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>
            </div>

            <!-- Recent Docs -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Dokumen Terbaru</h3>
                    <a href="?page=documents" class="p-2 min-h-[44px] flex items-center">Lihat Semua</a>
                </div>
                <?php if (empty($recent_docs)): ?>
                <div style="padding:24px;text-align:center">
                    <p style="color:#475569;font-size:13px;font-weight:700;margin:0">Belum ada dokumen tersimpan.</p>
                </div>
                <?php else: ?>
                    <?php foreach($recent_docs as $doc): ?>
                    <div class="d-row">
                        <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 text-slate-700 dark:text-slate-300 flex-shrink-0 flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div style="flex:1;min-width:0">
                            <p style="font-weight:800;color:#0f172a;font-size:13px;margin:0" class="truncate dark:text-white"><?php echo htmlspecialchars($doc['name']); ?></p>
                            <p style="color:#64748b;font-size:12px;font-weight:600;margin:2px 0 0"><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Admin Stats -->
            <?php if ($admin_stats): ?>
            <div class="bg-slate-900 text-white rounded-3xl p-6 relative overflow-hidden border border-slate-800 shadow-lg">
                <div>
                    <h3 class="text-white text-sm font-extrabold mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-300">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        Statistik Sistem TaskFlow
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
                       class="mt-5 min-h-[44px] flex items-center justify-center rounded-xl font-bold text-xs text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-md shadow-indigo-600/30">
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

    var grad = ctx.createLinearGradient(0, 0, 0, 280);
    grad.addColorStop(0, 'rgba(99, 102, 241, 0.35)');
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
                    borderWidth: 3,
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
                        boxWidth: 14,
                        padding: 16,
                        font: { size: 12, weight: '700' },
                        color: '#334155'
                    }
                },
                tooltip: {
                    padding: 12,
                    cornerRadius: 10,
                    boxPadding: 6
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        color: '#475569',
                        font: { size: 11, weight: '700' }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.06)'
                    }
                },
                x: {
                    ticks: {
                        color: '#334155',
                        font: { size: 11, weight: '700' }
                    },
                    grid: { display: false }
                }
            }
        }
    });
})();
</script>