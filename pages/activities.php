<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/activities.php - System Activity Center
$isAdmin = isAdmin();
$user_id = $_SESSION['user_id'];

// Fetch all activities with user info (safely via LEFT JOIN)
$stmt = $pdo->prepare("
    SELECT a.*, u.display_name, u.profile_photo, u.role as user_role 
    FROM activities a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 200
");
$stmt->execute();
$all_activities = $stmt->fetchAll();
$total_activities = count($all_activities);

function isSystemAction($action) {
    $action = strtolower($action);
    $system_prefixes = [
        'memperbarui pengaturan',
        'menambahkan pengguna',
        'mengubah role',
        'mereset password',
        'menghapus pengguna',
        'membuat file backup',
        'membersihkan cache',
        'setelan sistem',
        'login',
        'logout',
        'auth'
    ];
    foreach ($system_prefixes as $prefix) {
        if (strpos($action, $prefix) !== false) {
            return true;
        }
    }
    return false;
}

$work_activities = [];
$system_activities = [];

foreach ($all_activities as $act) {
    if (isSystemAction($act['action'])) {
        $system_activities[] = $act;
    } else {
        $work_activities[] = $act;
    }
}

function getActionStyle($action) {
    $action = strtolower($action);
    if (strpos($action, 'dokumen') !== false || strpos($action, 'upload') !== false) 
        return [
            '<svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
            'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-200/80 dark:border-indigo-800/60'
        ];
    if (strpos($action, 'catatan') !== false || strpos($action, 'note') !== false) 
        return [
            '<svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700'
        ];
    if (strpos($action, 'tugas') !== false || strpos($action, 'todo') !== false || strpos($action, 'status') !== false) 
        return [
            '<svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'bg-emerald-50/80 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60'
        ];
    if (strpos($action, 'profil') !== false || strpos($action, 'password') !== false) 
        return [
            '<svg class="w-5 h-5 text-slate-700 dark:text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700'
        ];
    if (strpos($action, 'login') !== false || strpos($action, 'logout') !== false) 
        return [
            '<svg class="w-5 h-5 text-slate-700 dark:text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700'
        ];
    return [
        '<svg class="w-5 h-5 text-slate-600 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700'
    ];
}

function renderTimeline($activities, $tabId) {
    if (empty($activities)) {
        echo '
        <div class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-md rounded-3xl p-14 text-center border border-slate-200/90 dark:border-slate-800 shadow-sm">
            <div class="w-14 h-14 bg-slate-100 dark:bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-400 dark:text-slate-500">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1">Belum Ada Aktivitas</h3>
            <p class="text-slate-500 dark:text-slate-400 font-medium text-xs max-w-xs mx-auto">Sistem belum mencatat rekaman jejak untuk kategori ini.</p>
        </div>';
        return;
    }
    
    echo '<div class="flex flex-col gap-4 activity-timeline-list" data-tab="' . $tabId . '">';
    $currentDate = '';
    foreach ($activities as $act) {
        $dateLabel = date('d F Y', strtotime($act['created_at']));
        $timeLabel = date('H:i', strtotime($act['created_at']));
        $displayDate = ($dateLabel !== $currentDate);
        $currentDate = $dateLabel;
        $style = getActionStyle($act['action']);
        
        $initials = strtoupper(substr($act['display_name'] ?? 'US', 0, 2));
        $hasPhoto = !empty($act['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $act['profile_photo']);
        
        if ($displayDate) {
            echo '
            <div class="relative flex items-center gap-4 my-2 date-divider">
                <div class="h-px flex-1 bg-slate-200/80 dark:bg-slate-800"></div>
                <span class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm px-4 py-1 rounded-full text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider border border-slate-200/80 dark:border-slate-800 shadow-sm">' . htmlspecialchars($dateLabel) . '</span>
                <div class="h-px flex-1 bg-slate-200/80 dark:bg-slate-800"></div>
            </div>';
        }
        
        $photoHtml = $hasPhoto 
            ? '<img src="uploads/avatars/' . htmlspecialchars($act['profile_photo']) . '" alt="' . htmlspecialchars($act['display_name']) . '" class="w-full h-full object-cover">'
            : '<span class="text-[9px] font-bold text-slate-500 dark:text-slate-400">' . htmlspecialchars($initials) . '</span>';
            
        echo '
        <div class="activity-item group relative flex gap-4 items-start" data-text="' . strtolower(htmlspecialchars($act['action'] . ' ' . ($act['display_name'] ?? ''))) . '">
            <div class="hidden sm:flex flex-col items-end w-14 pt-2 flex-shrink-0">
                <span class="text-xs font-bold text-slate-900 dark:text-white leading-none">' . htmlspecialchars($timeLabel) . '</span>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-1">WIB</span>
            </div>
            <div class="flex-1 bg-white/85 dark:bg-slate-900/85 backdrop-blur-md rounded-2xl p-4 sm:p-5 border border-white/80 dark:border-slate-800 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col sm:flex-row sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center border flex-shrink-0 shadow-sm ' . $style[1] . '">
                    ' . $style[0] . '
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-slate-900 dark:text-white font-bold leading-snug text-sm mb-2">' . htmlspecialchars($act['action']) . '</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 rounded-lg">
                            <div class="w-5 h-5 rounded-md overflow-hidden bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center">
                                ' . $photoHtml . '
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">' . htmlspecialchars($act['display_name'] ?? 'System') . '</span>
                            <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            <span class="text-[10px] font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">' . htmlspecialchars($act['user_role'] ?? 'user') . '</span>
                        </div>
                        <div class="sm:hidden flex items-center gap-1 text-xs font-semibold text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>' . htmlspecialchars($timeLabel) . ' WIB</span>
                        </div>
                    </div>
                </div>
                <div class="hidden md:block flex-shrink-0">
                    <span class="px-3 py-1 bg-slate-50 dark:bg-slate-800/60 text-slate-400 text-[10px] font-bold uppercase tracking-wider rounded-lg border border-slate-200/60 dark:border-slate-700/60">Tercatat</span>
                </div>
            </div>
        </div>';
    }
    echo '</div>';
}
?>

<div class="p-5 md:p-8 w-full max-w-5xl mx-auto min-h-screen">
    
    <!-- Executive Glassmorphic Header -->
    <div class="rounded-3xl p-6 md:p-8 mb-7 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border border-white/80 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold uppercase tracking-widest mb-3">
                <svg class="w-3.5 h-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Audit Log &amp; Timeline Real-time
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white leading-tight tracking-tight mb-1">Aktivitas Sistem</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Rekaman jejak aktivitas tugas, berkas dokumen, dan interaksi sistem pengguna.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="bg-white/90 dark:bg-slate-800/90 px-4 py-2.5 rounded-2xl border border-slate-200/90 dark:border-slate-700 shadow-sm flex items-center gap-3.5">
                <div class="text-right">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Tercatat</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white"><?php echo $total_activities; ?></div>
                </div>
                <div class="w-9 h-9 bg-indigo-50 dark:bg-indigo-950/60 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/60">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Controls: Search & Tabs -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 mb-6">
        <!-- Segmented Glass Tabs -->
        <div class="inline-flex p-1 rounded-2xl bg-slate-200/70 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
            <button onclick="switchActTab('all')" id="tab-btn-all" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm">
                Semua (<?php echo count($all_activities); ?>)
            </button>
            <button onclick="switchActTab('work')" id="tab-btn-work" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                Tugas &amp; Dokumen (<?php echo count($work_activities); ?>)
            </button>
            <button onclick="switchActTab('system')" id="tab-btn-system" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                Sistem &amp; Akun (<?php echo count($system_activities); ?>)
            </button>
        </div>

        <!-- Search input -->
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="actSearchInput" placeholder="Cari aktivitas atau nama..." oninput="filterActivities(this.value)"
                   class="pl-9 pr-4 py-2 text-xs font-medium w-full sm:w-64 rounded-xl transition-all text-slate-900 dark:text-white bg-white/90 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
        </div>
    </div>

    <!-- TIMELINE TABS CONTENT -->
    <div class="relative">
        <div id="tab-content-all" class="block">
            <?php renderTimeline($all_activities, 'all'); ?>
        </div>
        
        <div id="tab-content-work" class="hidden">
            <?php renderTimeline($work_activities, 'work'); ?>
        </div>

        <div id="tab-content-system" class="hidden">
            <?php renderTimeline($system_activities, 'system'); ?>
        </div>
    </div>
</div>

<script>
function switchActTab(tab) {
    const tabs = ['all', 'work', 'system'];
    tabs.forEach(t => {
        const btn = document.getElementById('tab-btn-' + t);
        const content = document.getElementById('tab-content-' + t);
        if (t === tab) {
            btn.className = "px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm";
            content.classList.remove('hidden');
        } else {
            btn.className = "px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";
            content.classList.add('hidden');
        }
    });
}

function filterActivities(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('.activity-item').forEach(item => {
        const text = item.dataset.text || '';
        item.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}
</script>
