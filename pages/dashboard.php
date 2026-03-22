<?php
// pages/dashboard.php - Main Dashboard
$user_id = $_SESSION['user_id'];

// Get statistics (with caching for 10 minutes)
$cache_key_stats = "dashboard_stats_user_" . $user_id;
$dashboard_stats = getCache($cache_key_stats);

if ($dashboard_stats === null) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_todos FROM todos WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_todos = $stmt->fetch()['total_todos'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_todos FROM todos WHERE user_id = ? AND completed = 1");
    $stmt->execute([$user_id]);
    $completed_todos = $stmt->fetch()['completed_todos'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_notes FROM notes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_notes = $stmt->fetch()['total_notes'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_docs FROM documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_docs = $stmt->fetch()['total_docs'];

    $dashboard_stats = [
        'total_todos'     => $total_todos,
        'completed_todos' => $completed_todos,
        'total_notes'     => $total_notes,
        'total_docs'      => $total_docs
    ];
    setCache($cache_key_stats, $dashboard_stats, 600);
} else {
    $total_todos     = $dashboard_stats['total_todos'];
    $completed_todos = $dashboard_stats['completed_todos'];
    $total_notes     = $dashboard_stats['total_notes'];
    $total_docs      = $dashboard_stats['total_docs'];
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
        $total_users = $stmt->fetch()['total_users'];
        $stmt = $pdo->query("SELECT COUNT(*) as admin_users FROM users WHERE role = 'administrator'");
        $admin_users = $stmt->fetch()['admin_users'];
        $stmt = $pdo->query("SELECT COUNT(*) as total_system_todos FROM todos");
        $total_system_todos = $stmt->fetch()['total_system_todos'];
        $stmt = $pdo->query("SELECT COUNT(*) as total_system_notes FROM notes");
        $total_system_notes = $stmt->fetch()['total_system_notes'];
        $stmt = $pdo->query("SELECT COUNT(*) as total_system_docs FROM documents");
        $total_system_docs = $stmt->fetch()['total_system_docs'];
        $admin_stats = [
            'total_users'       => $total_users,
            'admin_users'       => $admin_users,
            'total_system_todos' => $total_system_todos,
            'total_system_notes' => $total_system_notes,
            'total_system_docs'  => $total_system_docs,
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

// Get Productivity Data (Last 7 Days)
$productivity_data = [];
$labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('D', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM todos WHERE user_id = ? AND completed = 1 AND todo_date = ?");
    $stmt->execute([$user_id, $date]);
    $productivity_data[] = $stmt->fetch()['count'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.dash-stat {
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 20px; padding: 22px 24px;
    display: flex; flex-direction: column; gap: 14px;
    position: relative; overflow: hidden;
    transition: transform 0.25s, box-shadow 0.25s;
    cursor: default;
}
.dash-stat:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
.dash-stat::after {
    content: ''; position: absolute;
    width: 150px; height: 150px; border-radius: 50%;
    right: -30px; top: -30px;
    background: rgba(255,255,255,0.1); pointer-events: none;
}
.d-section {
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 20px; overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.d-section-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px; border-bottom: 1px solid var(--border-color);
}
.d-section-head h3 { font-size: 14px; font-weight: 800; color: var(--text-main); margin: 0; }
.d-section-head a { font-size: 11px; font-weight: 700; color: var(--text-muted); text-decoration: none; }
.d-section-head a:hover { color: #6366f1; }
.d-row {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 22px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s;
}
.d-row:last-child { border-bottom: none; }
.d-row:hover { background: rgba(0,0,0,0.02); }
.q-btn {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; border-radius: 14px;
    text-decoration: none; transition: all 0.2s;
    border: 1.5px solid transparent;
}
.q-btn:hover { transform: translateX(4px); opacity: 0.9; }
.pb-bg { background: rgba(255,255,255,0.2); border-radius: 100px; height: 4px; margin-top: 2px; }
.pb-fill { height: 4px; border-radius: 100px; background: rgba(255,255,255,0.85); }

/* Chart specific override */
.chart-container { position: relative; height: 300px; width: 100%; padding: 10px 20px 25px; }
</style>

<div class="p-5 md:p-8 max-w-7xl mx-auto">

    <!-- HERO HEADER -->
    <div class="rounded-3xl p-7 md:p-10 mb-7 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5"
         style="background: linear-gradient(135deg,#1e1b4b 0%,#312e81 50%,#4c1d95 100%)">
        <div class="absolute inset-0 pointer-events-none"
             style="background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:40px 40px"></div>
        <div class="absolute right-0 top-0 w-64 h-64 pointer-events-none"
             style="background:radial-gradient(circle,rgba(167,139,250,0.3) 0%,transparent 70%);transform:translate(30%,-30%)"></div>
        <div class="relative z-10">
            <div class="text-indigo-300 text-[10px] font-black uppercase tracking-widest mb-2"><?php echo date('l, d F Y'); ?></div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">
                <?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['display_name'] ?? 'User'); ?>! 👋
            </h1>
            <p class="text-indigo-300 text-sm font-medium">
                <?php echo ($total_todos - $completed_todos) > 0
                    ? ($total_todos - $completed_todos) . ' tugas masih menunggu diselesaikan.'
                    : 'Semua tugas selesai! Kerja bagus 🎉'; ?>
            </p>
        </div>
        <div class="relative z-10 flex items-center gap-3 flex-shrink-0">
            <div class="text-right hidden md:block">
                <div class="text-white font-black text-sm"><?php echo htmlspecialchars($_SESSION['display_name'] ?? ''); ?></div>
                <div class="text-indigo-300 text-[10px] font-bold uppercase tracking-widest"><?php echo $_SESSION['role'] ?? ''; ?></div>
            </div>
            <img src="<?php echo $avatarSrc; ?>" alt="Avatar"
                 class="w-14 h-14 rounded-2xl object-cover"
                 style="border:2px solid rgba(255,255,255,0.2);box-shadow:0 8px 24px rgba(0,0,0,0.3)">
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
        <div class="dash-stat" style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
            <div class="flex items-center justify-between">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.15)">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <span style="color:rgba(255,255,255,0.6);font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase">TUGAS</span>
            </div>
            <div>
                <div style="font-size:2.5rem;font-weight:900;color:#fff;line-height:1"><?php echo $total_todos; ?></div>
                <div style="color:rgba(255,255,255,0.65);font-size:11px;font-weight:700;margin-top:4px"><?php echo $completed_todos; ?> selesai · <?php echo $pct; ?>%</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo $pct; ?>%"></div></div>
        </div>

        <div class="dash-stat" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed)">
            <div class="flex items-center justify-between">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.15)">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                </div>
                <span style="color:rgba(255,255,255,0.6);font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase">CATATAN</span>
            </div>
            <div>
                <div style="font-size:2.5rem;font-weight:900;color:#fff;line-height:1"><?php echo $total_notes; ?></div>
                <div style="color:rgba(255,255,255,0.65);font-size:11px;font-weight:700;margin-top:4px">Total Ide & Catatan</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo min(100, $total_notes * 10); ?>%"></div></div>
        </div>

        <div class="dash-stat" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="flex items-center justify-between">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.15)">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <span style="color:rgba(255,255,255,0.6);font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase">DOKUMEN</span>
            </div>
            <div>
                <div style="font-size:2.5rem;font-weight:900;color:#fff;line-height:1"><?php echo $total_docs; ?></div>
                <div style="color:rgba(255,255,255,0.65);font-size:11px;font-weight:700;margin-top:4px">File Tersimpan</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo min(100, $total_docs * 10); ?>%"></div></div>
        </div>

        <div class="dash-stat" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="flex items-center justify-between">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.15)">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <span style="color:rgba(255,255,255,0.6);font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase">PROGRESS</span>
            </div>
            <div>
                <div style="font-size:2.5rem;font-weight:900;color:#fff;line-height:1"><?php echo $pct; ?>%</div>
                <div style="color:rgba(255,255,255,0.65);font-size:11px;font-weight:700;margin-top:4px"><?php echo $completed_todos; ?>/<?php echo $total_todos; ?> Tugas</div>
            </div>
            <div class="pb-bg"><div class="pb-fill" style="width:<?php echo $pct; ?>%"></div></div>
        </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT -->
        <div class="lg:col-span-8 flex flex-col gap-6">

            <!-- Productivity Chart -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3>📈 Grafik Produktivitas (7 Hari Terakhir)</h3>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Selesai / Hari</span>
                </div>
                <div class="chart-container">
                    <canvas id="productivityChart"></canvas>
                </div>
            </div>

            <!-- Upcoming Tasks -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3>📅 Tugas Mendatang</h3>
                    <a href="?page=calendar">Lihat Semua →</a>
                </div>
                <?php if (empty($upcoming_todos)): ?>
                <div style="padding:40px;text-align:center">
                    <div style="width:52px;height:52px;border-radius:16px;background:#f8fafc;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <p style="color:#94a3b8;font-weight:700;font-size:13px;margin:0 0 12px">Tidak ada tugas mendatang</p>
                    <a href="?page=calendar" style="color:#6366f1;font-weight:700;font-size:12px;text-decoration:none">+ Buat Tugas Baru</a>
                </div>
                <?php else: ?>
                    <?php foreach($upcoming_todos as $todo):
                        $dl = (int)((strtotime($todo['todo_date']) - strtotime('today')) / 86400);
                        if ($dl === 0)      $badge = ['Hari Ini','#dc2626','#fef2f2'];
                        elseif ($dl === 1)  $badge = ['Besok','#d97706','#fffbeb'];
                        else               $badge = [$dl.' hari','#4f46e5','#eef2ff'];
                    ?>
                    <div class="d-row">
                        <div style="width:36px;height:36px;border-radius:12px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
                                    background:<?php echo $todo['completed'] ? '#dcfce7' : '#f8fafc'; ?>;
                                    border:1.5px solid <?php echo $todo['completed'] ? '#86efac' : '#e2e8f0'; ?>">
                            <?php if ($todo['completed']): ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else: ?>
                                <div style="width:7px;height:7px;border-radius:50%;background:#cbd5e1"></div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1;min-width:0">
                            <p style="font-weight:700;color:#0f172a;font-size:13px;margin:0;<?php echo $todo['completed'] ? 'text-decoration:line-through;opacity:0.5' : ''; ?>"
                               class="truncate"><?php echo htmlspecialchars($todo['title']); ?></p>
                            <p style="color:#94a3b8;font-size:11px;margin:2px 0 0"><?php echo date('d M Y', strtotime($todo['todo_date'])); ?></p>
                        </div>
                        <span style="font-size:10px;font-weight:800;padding:4px 10px;border-radius:100px;flex-shrink:0;color:<?php echo $badge[1]; ?>;background:<?php echo $badge[2]; ?>"><?php echo $badge[0]; ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Notes -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3>📝 Catatan Terbaru</h3>
                    <a href="?page=notes">Lihat Semua →</a>
                </div>
                <?php if (empty($recent_notes)): ?>
                <div style="padding:28px;text-align:center">
                    <p style="color:#94a3b8;font-size:13px;font-weight:700;margin:0">Belum ada catatan.
                        <a href="?page=notes" style="color:#6366f1;text-decoration:none">Tulis sekarang →</a>
                    </p>
                </div>
                <?php else:
                    $nc_list = [
                        ['#fef9c3','#92400e'],['#dbeafe','#1e40af'],
                        ['#dcfce7','#166534'],['#fce7f3','#9d174d'],['#ede9fe','#5b21b6']
                    ];
                ?>
                <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
                    <?php foreach($recent_notes as $i => $note):
                        $nc = $nc_list[$i % count($nc_list)]; ?>
                    <a href="?page=notes" style="display:block;border-radius:16px;padding:14px;text-decoration:none;background:<?php echo $nc[0]; ?>;transition:opacity .2s" class="hover:opacity-85">
                        <div style="font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:<?php echo $nc[1]; ?>;opacity:.7;margin-bottom:6px">
                            <?php echo date('d M Y', strtotime($note['updated_at'])); ?>
                        </div>
                        <div style="font-weight:800;color:#0f172a;font-size:13px;line-height:1.3;margin-bottom:6px"><?php echo htmlspecialchars($note['title']); ?></div>
                        <div style="font-size:11px;color:<?php echo $nc[1]; ?>;opacity:.75;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                            <?php echo htmlspecialchars(substr($note['content'], 0, 80)); ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="lg:col-span-4 flex flex-col gap-6">

            <!-- Quick Actions -->
            <div class="d-section">
                <div class="d-section-head"><h3>⚡ Aksi Cepat</h3></div>
                <div style="padding:12px;display:flex;flex-direction:column;gap:8px">
                    <a href="?page=calendar" class="q-btn" style="background:#eef2ff">
                        <div style="width:34px;height:34px;border-radius:11px;background:#c7d2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#4338ca" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div>
                            <div style="color:#1e1b4b;font-weight:800;font-size:13px">Buat Tugas Baru</div>
                            <div style="color:#6366f1;font-size:11px;font-weight:600">Atur jadwal & deadline</div>
                        </div>
                    </a>
                    <a href="?page=notes" class="q-btn" style="background:#f5f3ff">
                        <div style="width:34px;height:34px;border-radius:11px;background:#ddd6fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div>
                            <div style="color:#1e1b4b;font-weight:800;font-size:13px">Tulis Catatan</div>
                            <div style="color:#7c3aed;font-size:11px;font-weight:600">Simpan ide & pemikiran</div>
                        </div>
                    </a>
                    <a href="?page=documents" class="q-btn" style="background:#ecfdf5">
                        <div style="width:34px;height:34px;border-radius:11px;background:#a7f3d0;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#065f46" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div>
                            <div style="color:#1e1b4b;font-weight:800;font-size:13px">Upload Dokumen</div>
                            <div style="color:#059669;font-size:11px;font-weight:600">Simpan file penting</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Recent Docs -->
            <div class="d-section">
                <div class="d-section-head">
                    <h3>📁 Dokumen Terbaru</h3>
                    <a href="?page=documents">Lihat Semua →</a>
                </div>
                <?php if (empty($recent_docs)): ?>
                <div style="padding:24px;text-align:center">
                    <p style="color:#94a3b8;font-size:13px;font-weight:700;margin:0">Belum ada dokumen</p>
                </div>
                <?php else: ?>
                    <?php foreach($recent_docs as $doc): ?>
                    <div class="d-row">
                        <div style="width:34px;height:34px;border-radius:11px;background:#fef3c7;flex-shrink:0;display:flex;align-items:center;justify-content:center">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div style="flex:1;min-width:0">
                            <p style="font-weight:700;color:#0f172a;font-size:13px;margin:0" class="truncate"><?php echo htmlspecialchars($doc['name']); ?></p>
                            <p style="color:#94a3b8;font-size:11px;margin:2px 0 0"><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Admin Stats -->
            <?php if ($admin_stats): ?>
            <div style="border-radius:20px;padding:22px;position:relative;overflow:hidden;background:linear-gradient(135deg,#0f172a,#1e1b4b)">
                <div style="position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.03) 1px,transparent 1px);background-size:32px 32px"></div>
                <div style="position:relative;z-index:1">
                    <h3 style="color:#fff;font-size:13px;font-weight:800;margin:0 0 16px;display:flex;align-items:center;gap:8px">
                        <span style="width:28px;height:28px;border-radius:9px;background:rgba(99,102,241,0.3);display:inline-flex;align-items:center;justify-content:center">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="2.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        Statistik Sistem
                    </h3>
                    <?php foreach([
                        ['Total Pengguna', $admin_stats['total_users']],
                        ['Administrator',  $admin_stats['admin_users']],
                        ['Total Tugas',    $admin_stats['total_system_todos']],
                        ['Total Catatan',  $admin_stats['total_system_notes']],
                        ['Total Dokumen',  $admin_stats['total_system_docs']],
                    ] as $r): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid rgba(255,255,255,0.06)">
                        <span style="color:#94a3b8;font-size:12px;font-weight:700"><?php echo $r[0]; ?></span>
                        <span style="color:#fff;font-size:14px;font-weight:900"><?php echo $r[1]; ?></span>
                    </div>
                    <?php endforeach; ?>
                    <a href="?page=admin"
                       style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:16px;padding:11px;border-radius:13px;font-weight:800;font-size:13px;color:#1e1b4b;text-decoration:none;background:linear-gradient(135deg,#a5b4fc,#818cf8);transition:opacity .2s"
                       class="hover:opacity-90">
                        Kelola Sistem →
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('productivityChart').getContext('2d');
    const isDark = document.documentElement.classList.contains('dark');
    
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Selesai',
                data: <?php echo json_encode($productivity_data); ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#6366f1',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                            ticks: {
                        stepSize: 1,
                        color: '#64748b',
                        font: { size: 10, weight: '700' }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.03)'
                    }
                },
                x: {
                    ticks: {
                        color: '#64748b',
                        font: { size: 10, weight: '700' }
                    },
                    grid: { display: false }
                }
            }
        }
    });
</script>