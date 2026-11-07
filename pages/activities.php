<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/activities.php - System Activity Center
$isAdmin = isAdmin();
$user_id = $_SESSION['user_id'];

$admin_activities = [];
$user_activities = [];
$my_activities = [];
$total_activities = 0;

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
        'setelan sistem'
    ];
    foreach ($system_prefixes as $prefix) {
        if (strpos($action, $prefix) !== false) {
            return true;
        }
    }
    return false;
}

$page_admin = isset($_GET['pa']) ? max(1, (int)$_GET['pa']) : 1;
$page_user = isset($_GET['pu']) ? max(1, (int)$_GET['pu']) : 1;
$page_my = isset($_GET['pm']) ? max(1, (int)$_GET['pm']) : 1;
$limit = 5;

$total_admin_pages = 1;
$total_user_pages = 1;
$total_my_pages = 1;

// Fetch activities with user info
if ($isAdmin) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.display_name, u.profile_photo, u.role as user_role 
        FROM activities a 
        JOIN users u ON a.user_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 1000
    ");
    $stmt->execute();
    $all = $stmt->fetchAll();
    $total_activities = count($all);
    foreach ($all as $act) {
        if (isSystemAction($act['action'])) {
            $admin_activities[] = $act;
        } else {
            $user_activities[] = $act;
        }
    }
    
    $total_admin_pages = ceil(count($admin_activities) / $limit) ?: 1;
    $total_user_pages = ceil(count($user_activities) / $limit) ?: 1;
    
    // Paginate manually in PHP
    $admin_activities = array_slice($admin_activities, ($page_admin - 1) * $limit, $limit);
    $user_activities = array_slice($user_activities, ($page_user - 1) * $limit, $limit);
    
} else {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE user_id = :uid");
    $stmtCount->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmtCount->execute();
    $total_activities = $stmtCount->fetchColumn();
    $total_my_pages = ceil($total_activities / $limit) ?: 1;
    
    $offset = ($page_my - 1) * $limit;
    $stmt = $pdo->prepare("
        SELECT a.*, u.display_name, u.profile_photo, u.role as user_role 
        FROM activities a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.user_id = :uid
        ORDER BY a.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $my_activities = $stmt->fetchAll();
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

function renderTimeline($activities, $currentPage, $totalPages, $pageParam, $activeTab) {
    if (empty($activities)) {
        echo '
        <div class="bg-white dark:bg-slate-900 rounded-3xl p-16 text-center border border-slate-200/90 dark:border-slate-800 shadow-sm">
            <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-400 dark:text-slate-500">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Belum Ada Aktivitas</h3>
            <p class="text-slate-500 dark:text-slate-400 font-medium text-sm max-w-xs mx-auto">Sistem belum mencatat aktivitas apa pun untuk kategori ini.</p>
        </div>';
        return;
    }
    
    echo '<div class="flex flex-col gap-6">';
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
            <div class="relative flex items-center gap-4 my-2">
                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></div>
                <span class="bg-white dark:bg-slate-900 px-5 py-1.5 rounded-full text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider border border-slate-200/90 dark:border-slate-800 shadow-sm">' . htmlspecialchars($dateLabel) . '</span>
                <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></div>
            </div>';
        }
        
        $photoHtml = $hasPhoto 
            ? '<img src="uploads/avatars/' . htmlspecialchars($act['profile_photo']) . '" alt="' . htmlspecialchars($act['display_name']) . '" class="w-full h-full object-cover">'
            : '<span class="text-[9px] font-bold text-slate-500 dark:text-slate-400">' . htmlspecialchars($initials) . '</span>';
            
        echo '
        <div class="group relative flex gap-4 md:gap-6 items-start">
            <div class="hidden md:flex flex-col items-end w-16 pt-2 flex-shrink-0">
                <span class="text-xs font-bold text-slate-900 dark:text-white leading-none">' . htmlspecialchars($timeLabel) . '</span>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-1">WIB</span>
            </div>
            <div class="flex-1 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-2xl p-4 md:p-5 border border-slate-200/90 dark:border-slate-800 shadow-sm transition-all hover:shadow-md hover:border-slate-300 dark:hover:border-slate-700 flex flex-col md:flex-row md:items-center gap-4">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center border flex-shrink-0 ' . $style[1] . '">
                    ' . $style[0] . '
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-slate-900 dark:text-white font-bold leading-snug text-sm mb-2">' . htmlspecialchars($act['action']) . '</p>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 rounded-lg">
                            <div class="w-5 h-5 rounded-md overflow-hidden bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex items-center justify-center">
                                ' . $photoHtml . '
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">' . htmlspecialchars($act['display_name']) . '</span>
                            <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-slate-600"></span>
                            <span class="text-[10px] font-extrabold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">' . htmlspecialchars($act['user_role']) . '</span>
                        </div>
                        <div class="md:hidden flex items-center gap-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800/80 px-2.5 py-1 rounded-lg border border-slate-200/80 dark:border-slate-700">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>' . htmlspecialchars($timeLabel) . ' WIB</span>
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="px-3.5 py-1.5 bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider rounded-xl border border-slate-200/80 dark:border-slate-700">Tercatat</div>
                </div>
            </div>
        </div>';
    }
    echo '</div>';
    
    // Pagination Controls
    if ($totalPages > 1) {
        echo '<div class="mt-8 flex flex-col md:flex-row items-center justify-between gap-4 p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 shadow-sm">';
        
        // Prev button
        if ($currentPage > 1) {
            $prev = $currentPage - 1;
            echo "<a href=\"?page=activities&{$pageParam}={$prev}&tab={$activeTab}\" aria-label=\"Halaman sebelumnya\" class=\"flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all shadow-sm\">
                <svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"15 18 9 12 15 6\"/></svg> Sebelumnya
            </a>";
        } else {
            echo "<span class=\"flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/60 text-xs font-bold text-slate-400 cursor-not-allowed\">
                <svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"15 18 9 12 15 6\"/></svg> Sebelumnya
            </span>";
        }

        echo "<div class=\"flex flex-col items-center\">
                <span class=\"text-[10px] font-bold text-slate-400 uppercase tracking-widest\">Halaman</span>
                <span class=\"text-xs font-bold text-slate-700 dark:text-slate-300\">{$currentPage} <span class=\"text-slate-400 mx-1\">/</span> {$totalPages}</span>
              </div>";

        // Next button
        if ($currentPage < $totalPages) {
            $next = $currentPage + 1;
            echo "<a href=\"?page=activities&{$pageParam}={$next}&tab={$activeTab}\" aria-label=\"Halaman selanjutnya\" class=\"flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all shadow-sm\">
                Selanjutnya <svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"9 18 15 12 9 6\"/></svg>
            </a>";
        } else {
            echo "<span class=\"flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/60 text-xs font-bold text-slate-400 cursor-not-allowed\">
                Selanjutnya <svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"9 18 15 12 9 6\"/></svg>
            </span>";
        }
        
        echo '</div>';
    }
}
?>

<div class="p-5 md:p-8 w-full max-w-5xl mx-auto min-h-screen">
    
    <!-- Executive Glassmorphic Header -->
    <div class="rounded-3xl p-6 md:p-8 mb-7 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold uppercase tracking-widest mb-3">
                <svg class="w-3.5 h-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Audit Log &amp; Timeline
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white leading-tight tracking-tight mb-1">Aktivitas Sistem</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Pantau semua rekaman aktivitas, tugas, dan interaksi dokumen dalam satu timeline.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="bg-white dark:bg-slate-800/80 px-5 py-3 rounded-2xl border border-slate-200/90 dark:border-slate-700 shadow-sm flex items-center gap-4">
                <div class="text-right">
                    <div class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Total Sesi</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white"><?php echo $total_activities; ?></div>
                </div>
                <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-950/50 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/60">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
        </div>
    </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- TIMELINE CONTAINER -->
    <div class="relative pl-4 md:pl-0">
        
        <?php if ($isAdmin): ?>
            <!-- Admin Tabs -->
            <div class="flex gap-4 mb-8 border-b border-slate-200 pb-px overflow-x-auto custom-scrollbar">
                <button onclick="switchActTab('admin', true)" id="tab-btn-admin" class="whitespace-nowrap px-6 py-3 font-bold text-sm border-b-2 border-indigo-500 text-indigo-600 transition-colors">Perubahan Sistem (Administrator)</button>
                <button onclick="switchActTab('user', true)" id="tab-btn-user" class="whitespace-nowrap px-6 py-3 font-bold text-sm border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-colors">Aktivitas Pengguna</button>
            </div>

            <div id="tab-content-admin" class="block">
                <?php renderTimeline($admin_activities, $page_admin, $total_admin_pages, 'pa', 'admin'); ?>
            </div>
            
            <div id="tab-content-user" class="hidden">
                <?php renderTimeline($user_activities, $page_user, $total_user_pages, 'pu', 'user'); ?>
            </div>

        <?php else: ?>
            <!-- User acts -->
            <?php renderTimeline($my_activities, $page_my, $total_my_pages, 'pm', 'user'); ?>
        <?php endif; ?>
        
        <div class="mt-8 text-center pb-8 border-t border-slate-200 pt-8">
            <p class="text-slate-400 text-xs font-medium italic underline decoration-slate-200 underline-offset-4">Menampilkan aktivitas terakhir secara berurut.</p>
        </div>
    </div>
</div>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.group {
    animation: fadeIn 0.4s ease forwards;
}
</style>

<script>
function switchActTab(tab, updateUrl = false) {
    const btnAdmin = document.getElementById('tab-btn-admin');
    const btnUser = document.getElementById('tab-btn-user');
    const contentAdmin = document.getElementById('tab-content-admin');
    const contentUser = document.getElementById('tab-content-user');

    if (tab === 'admin') {
        btnAdmin.className = "whitespace-nowrap px-6 py-3 font-bold text-sm border-b-2 border-indigo-500 text-indigo-600 transition-colors";
        btnUser.className = "whitespace-nowrap px-6 py-3 font-bold text-sm border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-colors";
        contentAdmin.classList.remove('hidden');
        contentUser.classList.add('hidden');
    } else {
        btnUser.className = "whitespace-nowrap px-6 py-3 font-bold text-sm border-b-2 border-indigo-500 text-indigo-600 transition-colors";
        btnAdmin.className = "whitespace-nowrap px-6 py-3 font-bold text-sm border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition-colors";
        contentUser.classList.remove('hidden');
        contentAdmin.classList.add('hidden');
    }
    
    if (updateUrl && window.history.replaceState) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('tab', tab);
        window.history.replaceState(null, '', '?' + urlParams.toString());
    }
}

// On page load, switch to relevant tab if defined
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab');
    if (activeTab === 'user' || activeTab === 'admin') {
        switchActTab(activeTab, false);
    }
});
</script>
