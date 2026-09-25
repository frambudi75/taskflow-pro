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
        return ['📁', 'bg-emerald-50 text-emerald-600 border-emerald-100'];
    if (strpos($action, 'catatan') !== false || strpos($action, 'note') !== false) 
        return ['📝', 'bg-indigo-50 text-indigo-600 border-indigo-100'];
    if (strpos($action, 'tugas') !== false || strpos($action, 'todo') !== false || strpos($action, 'status') !== false) 
        return ['✅', 'bg-blue-50 text-blue-600 border-blue-100'];
    if (strpos($action, 'profil') !== false || strpos($action, 'password') !== false) 
        return ['👤', 'bg-amber-50 text-amber-600 border-amber-100'];
    if (strpos($action, 'login') !== false || strpos($action, 'logout') !== false) 
        return ['🔑', 'bg-rose-50 text-rose-600 border-rose-100'];
    return ['✨', 'bg-slate-50 text-slate-600 border-slate-100'];
}

function renderTimeline($activities, $currentPage, $totalPages, $pageParam, $activeTab) {
    if (empty($activities)) {
        echo '
        <div class="bg-white rounded-[2.5rem] p-16 text-center border border-slate-100 shadow-sm">
            <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-400">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="text-xl font-black text-slate-900 mb-1">Belum Ada Aktivitas</h3>
            <p class="text-slate-500 font-medium text-sm max-w-xs mx-auto">Sistem belum mencatat aktivitas apa pun untuk kategori ini.</p>
        </div>';
        return;
    }
    
    echo '<div class="flex flex-col gap-10">';
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
            <div class="relative flex items-center gap-4 mb-4">
                <div class="h-px flex-1 bg-slate-200"></div>
                <span class="bg-white px-6 py-2 rounded-2xl text-[11px] font-black text-slate-500 uppercase tracking-[0.2em] border border-slate-100 shadow-sm">' . htmlspecialchars($dateLabel) . '</span>
                <div class="h-px flex-1 bg-slate-200"></div>
            </div>';
        }
        
        $photoHtml = $hasPhoto 
            ? '<img src="uploads/avatars/' . htmlspecialchars($act['profile_photo']) . '" alt="' . htmlspecialchars($act['display_name']) . '" class="w-full h-full object-cover">'
            : '<span class="text-[8px] font-black text-slate-400">' . htmlspecialchars($initials) . '</span>';
            
        echo '
        <div class="group relative flex gap-6 md:gap-8 items-start">
            <div class="hidden md:flex flex-col items-end w-20 pt-1.5 flex-shrink-0">
                <span class="text-xs font-black text-slate-900 leading-none">' . htmlspecialchars($timeLabel) . '</span>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">WIB</span>
            </div>
            <div class="relative z-10 flex flex-col items-center flex-shrink-0 pt-1">
                <div class="w-5 h-5 rounded-full border-4 border-white shadow-md transition-all group-hover:scale-125 ' . $style[1] . '"></div>
                <div class="w-0.5 flex-1 bg-slate-100 mt-2 rounded-full absolute top-7 -bottom-14 hidden md:block"></div>
            </div>
            <div class="flex-1 bg-white rounded-[1.75rem] p-5 md:p-6 border border-slate-100 shadow-sm transition-all hover:shadow-md hover:-translate-y-0.5 flex flex-col md:flex-row md:items-center gap-5">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl border flex-shrink-0 ' . $style[1] . '">
                    ' . $style[0] . '
                </div>
                <div class="flex-1">
                    <p class="text-slate-800 font-bold leading-snug text-[15px] mb-2">' . htmlspecialchars($act['action']) . '</p>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-slate-50 border border-slate-100 rounded-lg">
                            <div class="w-5 h-5 rounded-md overflow-hidden bg-white border border-slate-200 flex items-center justify-center">
                                ' . $photoHtml . '
                            </div>
                            <span class="text-[11px] font-bold text-slate-600">' . htmlspecialchars($act['display_name']) . '</span>
                            <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                            <span class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">' . htmlspecialchars($act['user_role']) . '</span>
                        </div>
                        <div class="md:hidden flex items-center gap-1.5 text-[11px] font-bold text-slate-500 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>' . htmlspecialchars($timeLabel) . ' WIB</span>
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="px-4 py-2 bg-slate-50 text-slate-500 text-[10px] font-black uppercase tracking-[0.15em] rounded-xl border border-slate-200">Aktivitas Terverifikasi</div>
                </div>
            </div>
        </div>';
    }
    echo '</div>';
    
    // Pagination Controls
    if ($totalPages > 1) {
        echo '<div class="mt-12 flex flex-col md:flex-row items-center justify-between gap-4 p-4 bg-white rounded-2xl border border-slate-200 shadow-sm">';
        
        // Prev button
        if ($currentPage > 1) {
            $prev = $currentPage - 1;
            echo "<a href=\"?page=activities&{$pageParam}={$prev}&tab={$activeTab}\" aria-label=\"Halaman sebelumnya\" class=\"flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-700 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500\">
                <svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"15 18 9 12 15 6\"/></svg> Sebelumnya
            </a>";
        } else {
            echo "<span class=\"flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-sm font-bold text-slate-400 cursor-not-allowed\">
                <svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"15 18 9 12 15 6\"/></svg> Sebelumnya
            </span>";
        }

        echo "<div class=\"flex flex-col items-center\">
                <span class=\"text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]\">Halaman</span>
                <span class=\"text-sm font-bold text-slate-700\">{$currentPage} <span class=\"text-slate-400 mx-1\">/</span> {$totalPages}</span>
              </div>";

        // Next button
        if ($currentPage < $totalPages) {
            $next = $currentPage + 1;
            echo "<a href=\"?page=activities&{$pageParam}={$next}&tab={$activeTab}\" aria-label=\"Halaman selanjutnya\" class=\"flex items-center gap-2 px-5 py-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-700 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50 transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500\">
                Selanjutnya <svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"9 18 15 12 9 6\"/></svg>
            </a>";
        } else {
            echo "<span class=\"flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-sm font-bold text-slate-400 cursor-not-allowed\">
                Selanjutnya <svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\"><polyline points=\"9 18 15 12 9 6\"/></svg>
            </span>";
        }
        
        echo '</div>';
    }
}
?>

<div class="p-6 md:p-10 max-w-5xl mx-auto min-h-screen">
    
    <!-- PREMIUM LIGHT HEADER -->
    <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="px-3 py-1 bg-white border border-slate-200 rounded-full text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Audit Log</span>
                <div class="h-px w-8 bg-slate-200"></div>
                <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Real-time Tracking</span>
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Aktivitas Sistem</h1>
            <p class="text-slate-500 font-medium max-w-md">Pantau semua jejak digital, perubahan tugas, dan interaksi dokumen dalam satu timeline terpadu.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="bg-white px-5 py-3 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="text-right">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Sesi</div>
                    <div class="text-xl font-black text-slate-900"><?php echo $total_activities; ?></div>
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
