<?php
// index.php
require_once 'config.php';
define('IN_APP', true);

ob_start();

if (!isLoggedIn()) {
    header('Location: ' . route('login'));
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$user_id = $_SESSION['user_id'] ?? null;
$display_name = $_SESSION['display_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
$email = $_SESSION['email'] ?? '';
$app_logo_info = getAppLogoInfo();

// Simple Logout logic
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . route('login'));
    exit;
}

// Standalone Print View or Export Bypass (Bypasses main UI chrome completely)
if ((isset($_GET['print_view']) && $_GET['print_view'] === '1') || (isset($_GET['export']) && $_GET['export'] === 'csv')) {
    ob_clean();
}

// AJAX Content Helper
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ob_clean();
    // Return only the inner content for AJAX requests
    switch($page) {
        case 'dashboard': include 'pages/dashboard.php'; break;
        case 'calendar': include 'pages/calendar.php'; break;
        case 'notes': include 'pages/notes.php'; break;
        case 'documents': include 'pages/documents.php'; break;
        case 'projects': include 'pages/projects.php'; break;
        case 'activities': include 'pages/activities.php'; break;
        case 'chat': include 'pages/chat.php'; break;
        case 'admin': if(isAdmin()) include 'pages/admin.php'; else echo "Akses Ditolak"; break;
        case 'settings': if(isAdmin()) include 'pages/settings.php'; else echo "Akses Ditolak"; break;
        case 'backup': if(isAdmin()) include 'pages/backup.php'; else echo "Akses Ditolak"; break;
        case 'cache': if(isAdmin()) include 'pages/cache.php'; else echo "Akses Ditolak"; break;
        case 'profile': include 'pages/profile.php'; break;
        case 'reset-password': include 'pages/reset-password.php'; break;
        default: include 'pages/dashboard.php';
    }
    exit;
}
// Dynamic Storage & Activity stats for Goodle Drive UI
$sidebar_storage_used = 0;
$sidebar_doc_count = 0;
$sidebar_user_quota = (int)($_SESSION['storage_quota'] ?? 104857600); // 100MB default
try {
    $stmtSideStore = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(file_size), 0) FROM documents WHERE user_id = ?");
    $stmtSideStore->execute([$_SESSION['user_id'] ?? 0]);
    $resSideStore = $stmtSideStore->fetch(PDO::FETCH_NUM);
    $sidebar_doc_count = (int)($resSideStore[0] ?? 0);
    $sidebar_storage_used = (int)($resSideStore[1] ?? 0);
} catch(Exception $e) {}

$sidebar_storage_pct = ($sidebar_user_quota <= 0) ? 0 : min(100, max(2, round(($sidebar_storage_used / max(1, $sidebar_user_quota)) * 100)));
$sidebar_storage_formatted = formatSize($sidebar_storage_used);
$sidebar_quota_formatted = ($sidebar_user_quota <= 0) ? 'Tanpa Batas' : formatSize($sidebar_user_quota);

// Quick Complete Task Handler (Called via AJAX from notification center or quick actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_complete_task'])) {
    if (isset($_SESSION['user_id']) && validateCSRF($_POST['csrf_token'] ?? '')) {
        $todo_id = (int)$_POST['todo_id'];
        $curr_user_id = (int)$_SESSION['user_id'];
        $stmtQC = $pdo->prepare("UPDATE todos SET status = 'done', completed = 1 WHERE id = ? AND user_id = ?");
        $stmtQC->execute([$todo_id, $curr_user_id]);
        logActivity("Menyelesaikan tugas via notifikasi tenggat waktu (#" . $todo_id . ")", "todo", $todo_id);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'todo_id' => $todo_id]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Validasi gagal']);
    exit;
}

// Pending tasks count
$badge_pending_tasks = 0;
try {
    $stmtBadge = $pdo->prepare("SELECT COUNT(*) FROM todos WHERE (completed = 0 AND status != 'done')");
    $stmtBadge->execute();
    $badge_pending_tasks = (int)$stmtBadge->fetchColumn();
} catch(Exception $e) {}

// Urgent Tasks for Interactive Notification Center: Overdue & Due Today
$notif_overdue_tasks = [];
$notif_today_tasks = [];
try {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $stmtOverdue = $pdo->prepare("SELECT id, title, todo_date, priority FROM todos WHERE user_id = ? AND completed = 0 AND status != 'done' AND todo_date < CURDATE() ORDER BY todo_date ASC LIMIT 5");
    $stmtOverdue->execute([$uid]);
    $notif_overdue_tasks = $stmtOverdue->fetchAll(PDO::FETCH_ASSOC);

    $stmtToday = $pdo->prepare("SELECT id, title, todo_date, priority FROM todos WHERE user_id = ? AND completed = 0 AND status != 'done' AND todo_date = CURDATE() ORDER BY id DESC LIMIT 5");
    $stmtToday->execute([$uid]);
    $notif_today_tasks = $stmtToday->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

$notif_urgent_count = count($notif_overdue_tasks) + count($notif_today_tasks);

// Recent activities for notifications
$recent_activities = [];
try {
    $stmtAct = $pdo->prepare("SELECT action, details, created_at FROM audit_logs ORDER BY id DESC LIMIT 5");
    $stmtAct->execute();
    $recent_activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1E5CE6">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow Pro')); ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="<?php echo $app_logo_info['mime']; ?>" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <script>
        // Anti-FOUC Dark Mode Immediate Initialization
        (function() {
            try {
                const savedTheme = localStorage.getItem('taskflow_theme');
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            } catch (e) {}
        })();
        // Suppress Tailwind CDN production notice in console
        (function() {
            const _warn = console.warn;
            console.warn = function(...args) {
                if (typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com')) return;
                _warn.apply(console, args);
            };
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'Inter', 'sans-serif'],
                        body: ['Inter', 'sans-serif']
                    },
                    colors: {
                        drive: {
                            bg: '#235EE7',
                            primary: '#1E5CE6',
                            primaryDark: '#1749B8',
                            lightBg: '#F3F5F9',
                            rowHover: '#F5F8FF',
                            rowSelected: '#EBF2FC',
                            textDark: '#1E293B',
                            textMuted: '#8C97A8'
                        }
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: #FFFFFF;
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            height: 100vh;
            width: 100vw;
            margin: 0;
            overflow: hidden;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .dark body {
            background-color: #0B1120;
            color: #E2E8F0;
        }

        /* 100% Full-Width Application Frame */
        .app-frame {
            background: #FFFFFF;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .dark .app-frame {
            background-color: #0B1120;
        }

        /* Distinctive Curved Royal Blue Sidebar */
        .curved-sidebar {
            background: linear-gradient(180deg, #1E5CE6 0%, #1B55D8 100%);
            border-top-right-radius: 46px;
            transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1), background 0.3s ease;
        }
        .dark .curved-sidebar {
            background: linear-gradient(180deg, #133E9E 0%, #0F2F7A 100%);
            border-right: 1px solid rgba(30, 58, 138, 0.45);
        }
        @media (max-width: 1023px) {
            .curved-sidebar {
                border-top-right-radius: 0 !important;
            }
        }

        /* Custom Folder Silhouette Tab */
        .folder-card-wrapper {
            position: relative;
            padding-top: 18px;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.25s ease;
            cursor: pointer;
        }
        .folder-card-wrapper:hover {
            transform: translateY(-4px);
        }
        .folder-tab {
            position: absolute;
            top: 0;
            left: 0;
            width: 43%;
            height: 24px;
            border-top-left-radius: 18px;
            border-top-right-radius: 10px;
            transition: background-color 0.25s ease;
        }
        .folder-tab-curve {
            position: absolute;
            top: 0;
            left: calc(43% - 4px);
            width: 28px;
            height: 19px;
            pointer-events: none;
        }
        .folder-body {
            position: relative;
            border-radius: 18px;
            border-top-left-radius: 0;
            padding: 20px 20px 18px 20px;
            transition: background-color 0.25s ease, box-shadow 0.25s ease, color 0.25s ease, border-color 0.25s ease;
        }
        .folder-card-wrapper.active .folder-tab {
            background-color: #1E5CE6;
        }
        .folder-card-wrapper.active .folder-body {
            background-color: #1E5CE6;
            color: #FFFFFF;
            box-shadow: 0 16px 30px -8px rgba(30, 92, 230, 0.48);
        }
        .folder-card-wrapper.active .curve-fill {
            fill: #1E5CE6;
        }
        .folder-card-wrapper.inactive .folder-tab {
            background-color: #F3F5F9;
        }
        .dark .folder-card-wrapper.inactive .folder-tab {
            background-color: #1E293B;
        }
        .folder-card-wrapper.inactive .folder-body {
            background-color: #F3F5F9;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);
        }
        .dark .folder-card-wrapper.inactive .folder-body {
            background-color: #1E293B;
            border: 1px solid rgba(51, 65, 85, 0.6);
            color: #E2E8F0;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3);
        }
        .folder-card-wrapper.inactive .curve-fill {
            fill: #F3F5F9;
        }
        .dark .folder-card-wrapper.inactive .curve-fill {
            fill: #1E293B;
        }
        .folder-card-wrapper.inactive:hover .folder-body,
        .folder-card-wrapper.inactive:hover .folder-tab {
            background-color: #EAEEF6;
        }
        .dark .folder-card-wrapper.inactive:hover .folder-body,
        .dark .folder-card-wrapper.inactive:hover .folder-tab {
            background-color: #273549;
        }
        .folder-card-wrapper.inactive:hover .curve-fill {
            fill: #EAEEF6;
        }
        .dark .folder-card-wrapper.inactive:hover .curve-fill {
            fill: #273549;
        }

        /* Sidebar Navigation Items */
        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 12px;
            color: rgba(255, 255, 255, 0.85);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .nav-item:hover:not(.active) {
            background: rgba(255, 255, 255, 0.12);
            color: #FFFFFF;
            transform: translateX(3px);
        }
        .nav-item.active {
            background: rgba(255, 255, 255, 0.22) !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 14px rgba(10, 30, 90, 0.16);
        }
        .nav-item .nav-icon {
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: inherit;
        }

        /* Storage bar transitions */
        .storage-bar-fill {
            transition: width 0.8s cubic-bezier(0.22, 1, 0.36, 1);
        }

        /* File row styling */
        .file-row {
            transition: background-color 0.18s ease;
            border-bottom: 1px solid #EEF2F6;
            position: relative;
        }
        .file-row:hover:not(.selected) {
            background-color: #F6F9FF;
        }
        .file-row.selected {
            background-color: #EBF1FC;
            border-bottom-color: transparent;
        }
        .file-row.selected::before {
            content: '';
            position: absolute;
            left: 0;
            top: 4px;
            bottom: 4px;
            width: 3.5px;
            background-color: #1E5CE6;
            border-radius: 4px;
        }

        /* Sleek scrollbar */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.35) transparent;
        }
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius: 10px; }

        /* Sidebar scrollbar invisible */
        .sidebar-scroll {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar-scroll::-webkit-scrollbar {
            display: none;
        }

        /* Page Loader */
        #pageLoader {
            position: fixed; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #1E5CE6, #60A5FA, #38BDF8);
            z-index: 9999; transform: translateX(-100%); transition: transform 0.2s ease-out;
            pointer-events: none;
            box-shadow: 0 0 10px rgba(30, 92, 230, 0.5);
        }
        #pageLoader.loading { transform: translateX(-20%); }
        #pageLoader.finish { transform: translateX(0); }

        #ajaxContent {
            transition: opacity 0.08s ease;
        }
        #ajaxContent.page-fade {
            opacity: 0.85;
        }

        @keyframes modalPop {
            from { opacity: 0; transform: scale(0.96) translateY(-8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .animate-modal-pop {
            animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>
    <script>
        // Universal modal helpers accessible globally before DOM completion
        function openUploadModal() {
            if (typeof window.documentsOpenUploadModal === 'function') {
                window.documentsOpenUploadModal();
            } else if (document.getElementById('uploadModal')) {
                const m = document.getElementById('uploadModal');
                m.classList.remove('hidden');
                m.style.display = 'flex';
                const fi = document.getElementById('fileInput');
                if (fi) fi.focus();
            } else {
                window.location.href = 'index.php?page=documents&openUpload=1';
            }
        }
        window.openUploadModal = openUploadModal;

        function closeUploadModal() {
            if (typeof window.documentsCloseUploadModal === 'function') {
                window.documentsCloseUploadModal();
            } else {
                const m = document.getElementById('uploadModal');
                if (m) {
                    m.classList.add('hidden');
                    m.style.display = 'none';
                }
            }
        }
        window.closeUploadModal = closeUploadModal;
    </script>
</head>
<body class="bg-white overflow-hidden h-screen w-screen flex flex-col m-0 p-0 select-none">
    <div id="pageLoader"></div>

    <!-- 100% FULL-SCREEN APPLICATION FRAME -->
    <div class="app-frame w-full h-screen flex flex-col relative bg-white overflow-hidden">

        <!-- ==================== TOP HEADER BAR ==================== -->
        <header class="w-full bg-white dark:bg-[#0F172A] px-6 lg:px-8 py-3.5 flex items-center justify-between gap-4 z-20 border-b border-slate-100 dark:border-slate-800 flex-shrink-0 transition-colors">
            <!-- Left: Logo + Brand Title + Mobile Menu Toggle -->
            <div class="flex items-center gap-3">
                <button id="mobileMenuBtn" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" aria-label="Toggle navigation">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="<?php echo route('dashboard'); ?>" class="flex items-center gap-2.5 group">
                    <?php if ($app_logo_info['is_custom']): ?>
                        <img src="<?php echo htmlspecialchars($app_logo_info['url']); ?>" alt="Logo" class="w-8 h-8 rounded-lg object-contain bg-blue-50 border border-blue-100 p-0.5 flex-shrink-0" />
                    <?php else: ?>
                        <div class="w-9 h-9 flex items-center justify-center transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3">
                            <svg viewBox="0 0 36 32" class="w-7 h-7" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12.4 1.5L23.6 1.5L34.5 20.2L23.3 20.2L12.4 1.5Z" fill="#1E5CE6"/>
                                <path d="M11.2 3.3L16.8 12.9L6.8 30.1L1.2 20.5L11.2 3.3Z" fill="#194EC2"/>
                                <path d="M8.8 30.5L30.2 30.5L35.2 21.8L13.8 21.8L8.8 30.5Z" fill="#3B76F6"/>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <span class="text-[#1E5CE6] font-extrabold text-[21px] sm:text-[22px] tracking-tight truncate"><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow Pro')); ?></span>
                </a>
            </div>

            <!-- Center: Pill Search Input (Triggers Command Palette Ctrl + K) -->
            <div class="flex-1 max-w-[440px] mx-auto hidden sm:block">
                <div onclick="openCmdPalette()" class="relative flex items-center w-full bg-[#F3F5F8] dark:bg-slate-800/80 hover:bg-[#EBEEF4] dark:hover:bg-slate-700/80 focus-within:bg-white dark:focus-within:bg-slate-800 rounded-full px-4 py-2.5 transition-all duration-200 cursor-pointer group shadow-2xs border border-transparent dark:border-slate-700/60">
                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-400 mr-2.5 shrink-0 group-hover:text-[#1E5CE6] transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path stroke-linecap="round" d="M20 20l-3.5-3.5"></path>
                    </svg>
                    <span class="text-xs text-slate-400 dark:text-slate-300 font-medium group-hover:text-slate-600 dark:group-hover:text-slate-200 transition-colors select-none">Cari di TaskFlow..</span>
                    <kbd class="ml-auto px-2 py-0.5 text-[10px] font-bold text-slate-400 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-full shadow-2xs">Ctrl+K</kbd>
                </div>
            </div>

            <!-- Right: Action Icons, Notifications, User Profile & Modals -->
            <div class="flex items-center gap-2 sm:gap-3 relative">
                <!-- Mobile Search Button -->
                <button onclick="openCmdPalette()" class="sm:hidden p-2 text-slate-600 dark:text-slate-300 hover:text-[#1E5CE6] rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Pencarian">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path stroke-linecap="round" d="M20 20l-3.5-3.5"></path></svg>
                </button>

                <!-- Notification Bell & Center -->
                <div class="relative">
                    <button onclick="toggleDropdown('notifDropdown')" class="relative p-2 text-slate-600 dark:text-slate-300 hover:text-[#1E5CE6] dark:hover:text-blue-400 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer" title="Pusat Notifikasi &amp; Tenggat Waktu" aria-label="Notifikasi">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <?php if ($notif_urgent_count > 0): ?>
                        <span id="notifBadge" class="absolute -top-0.5 -right-0.5 min-w-[19px] h-[19px] px-1 bg-rose-600 text-white rounded-full text-[10px] font-black flex items-center justify-center ring-2 ring-white dark:ring-slate-900 animate-pulse shadow-sm">
                            <?php echo $notif_urgent_count; ?>
                        </span>
                        <?php elseif (!empty($recent_activities)): ?>
                        <span id="notifBadge" class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-blue-500 rounded-full ring-2 ring-white dark:ring-slate-900"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Interactive Notification Dropdown Panel -->
                    <div id="notifDropdown" class="hidden absolute right-0 mt-3 w-84 sm:w-96 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200/90 dark:border-slate-800 p-0 z-50 overflow-hidden text-left animate-modal-pop">
                        <!-- Top Header with Segmented Tabs -->
                        <div class="p-3.5 bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full <?php echo $notif_urgent_count > 0 ? 'bg-rose-500 animate-ping' : 'bg-emerald-500'; ?>"></span>
                                <span class="font-black text-xs text-slate-900 dark:text-white">Pusat Notifikasi</span>
                            </div>
                            <div class="flex items-center gap-1 bg-white dark:bg-slate-700/80 p-0.5 rounded-xl border border-slate-200/80 dark:border-slate-600/80 text-[11px] font-bold">
                                <button type="button" id="tabNotifUrgentBtn" onclick="switchNotifTab('urgent')" class="px-2.5 py-1 rounded-lg bg-[#1E5CE6] text-white transition-all">
                                    Tenggat Waktu <?php if ($notif_urgent_count > 0): ?><span class="ml-1 px-1.5 py-0.2 bg-white/25 rounded-full text-[9px]"><?php echo $notif_urgent_count; ?></span><?php endif; ?>
                                </button>
                                <button type="button" id="tabNotifActivityBtn" onclick="switchNotifTab('activity')" class="px-2.5 py-1 rounded-lg text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white transition-all">
                                    Aktivitas
                                </button>
                            </div>
                        </div>

                        <!-- TAB 1: URGENT / DUE DATE TASKS -->
                        <div id="tabNotifUrgentContent" class="max-h-80 overflow-y-auto custom-scrollbar p-3 space-y-2.5">
                            <?php if ($notif_urgent_count === 0): ?>
                            <div class="py-8 px-4 text-center">
                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2.5 text-xl font-bold shadow-xs">
                                    ✓
                                </div>
                                <p class="text-xs font-bold text-slate-800 dark:text-white mb-0.5">Semua Tenggat Waktu Aman!</p>
                                <p class="text-[11px] text-slate-400">Tidak ada agenda tugas yang jatuh tempo atau terlambat hari ini.</p>
                            </div>
                            <?php else: ?>
                                <!-- Overdue Tasks -->
                                <?php foreach ($notif_overdue_tasks as $ot): ?>
                                <div id="notif-item-<?php echo $ot['id']; ?>" class="p-3 rounded-xl bg-rose-50/60 dark:bg-rose-950/30 border border-rose-200/80 dark:border-rose-900/50 flex items-start justify-between gap-2.5 transition-all">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-900/60 px-2 py-0.5 rounded-md">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                                Terlambat
                                            </span>
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400"><?php echo date('d M', strtotime($ot['todo_date'])); ?></span>
                                        </div>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white leading-snug truncate" title="<?php echo htmlspecialchars($ot['title']); ?>">
                                            <?php echo htmlspecialchars($ot['title']); ?>
                                        </p>
                                    </div>
                                    <button type="button" onclick="completeTaskFromNotif(<?php echo $ot['id']; ?>, this)" title="Tandai Selesai"
                                            class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-emerald-600 dark:hover:bg-emerald-600 text-slate-700 dark:text-slate-200 hover:text-white dark:hover:text-white border border-slate-200 dark:border-slate-700 hover:border-transparent text-[11px] font-bold transition-all shadow-2xs flex items-center gap-1 shrink-0">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Selesai</span>
                                    </button>
                                </div>
                                <?php endforeach; ?>

                                <!-- Today Tasks -->
                                <?php foreach ($notif_today_tasks as $tt): ?>
                                <div id="notif-item-<?php echo $tt['id']; ?>" class="p-3 rounded-xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/50 flex items-start justify-between gap-2.5 transition-all">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase text-amber-800 dark:text-amber-300 bg-amber-100 dark:bg-amber-900/60 px-2 py-0.5 rounded-md">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                Hari Ini
                                            </span>
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400">Jatuh Tempo</span>
                                        </div>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white leading-snug truncate" title="<?php echo htmlspecialchars($tt['title']); ?>">
                                            <?php echo htmlspecialchars($tt['title']); ?>
                                        </p>
                                    </div>
                                    <button type="button" onclick="completeTaskFromNotif(<?php echo $tt['id']; ?>, this)" title="Tandai Selesai"
                                            class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-emerald-600 dark:hover:bg-emerald-600 text-slate-700 dark:text-slate-200 hover:text-white dark:hover:text-white border border-slate-200 dark:border-slate-700 hover:border-transparent text-[11px] font-bold transition-all shadow-2xs flex items-center gap-1 shrink-0">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Selesai</span>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 2: RECENT ACTIVITIES -->
                        <div id="tabNotifActivityContent" class="hidden max-h-80 overflow-y-auto custom-scrollbar p-3 divide-y divide-slate-100 dark:divide-slate-800">
                            <?php if (empty($recent_activities)): ?>
                                <p class="text-xs text-slate-400 py-6 text-center">Belum ada riwayat aktivitas terbaru.</p>
                            <?php else: ?>
                                <?php foreach ($recent_activities as $ra): ?>
                                <div class="py-2.5 flex items-start gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] dark:text-blue-400 flex items-center justify-center shrink-0 font-bold text-xs">⚡</div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 truncate"><?php echo htmlspecialchars($ra['action']); ?></p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><?php echo htmlspecialchars($ra['details']); ?></p>
                                        <span class="text-[10px] text-slate-400"><?php echo time_elapsed_string($ra['created_at']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Dropdown Footer -->
                        <div class="p-2.5 bg-slate-50 dark:bg-slate-800/80 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs font-bold">
                            <a href="<?php echo route('projects'); ?>" class="text-[#1E5CE6] dark:text-blue-400 hover:underline flex items-center gap-1 text-[11px]">
                                <span>Buka Papan Kanban</span> &rarr;
                            </a>
                            <a href="<?php echo route('activities'); ?>" class="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white transition-colors text-[11px]">
                                Riwayat Log
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Dark / Light Mode Toggle Button -->
                <button onclick="toggleTheme()" id="themeToggleBtn" class="p-2 text-slate-600 dark:text-slate-300 hover:text-[#1E5CE6] dark:hover:text-blue-400 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer" title="Beralih Tema (Gelap / Terang)" aria-label="Beralih Tema">
                    <!-- Sun Icon (Active in Dark Mode) -->
                    <svg id="themeIconSun" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                    <!-- Moon Icon (Active in Light Mode) -->
                    <svg id="themeIconMoon" class="w-5 h-5 block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </button>

                <!-- Help Shortcuts Button (?) -->
                <button onclick="openShortcutsModal()" class="p-2 text-slate-600 dark:text-slate-300 hover:text-[#1E5CE6] rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Pintasan Keyboard & Bantuan" aria-label="Bantuan">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3m.08 4h.01"/>
                    </svg>
                </button>

                <!-- Settings Gear -->
                <a href="<?php echo isAdmin() ? route('settings') : route('profile'); ?>" class="p-2 text-slate-600 dark:text-slate-300 hover:text-[#1E5CE6] rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Pengaturan" aria-label="Pengaturan">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </a>

                <!-- User Profile Badge & Dropdown -->
                <?php
                $profilePhoto = $_SESSION['profile_photo'] ?? null;
                $hasAvatar = $profilePhoto && file_exists(__DIR__ . '/uploads/avatars/' . $profilePhoto);
                $avatarSrc = $hasAvatar ? 'uploads/avatars/' . $profilePhoto : '';
                $initial = strtoupper(substr($display_name ?: 'U', 0, 1));
                ?>
                <div class="relative">
                    <div onclick="toggleDropdown('userDropdown')" class="flex items-center gap-2.5 pl-1.5 cursor-pointer group">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-200 group-hover:text-[#1E5CE6] transition-colors hidden md:inline truncate max-w-[120px]"><?php echo htmlspecialchars($display_name); ?></span>
                        <?php if ($hasAvatar): ?>
                            <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="w-8 h-8 rounded-full object-cover shadow-xs border border-blue-200 dark:border-slate-700 group-hover:ring-4 group-hover:ring-blue-100 transition-all"/>
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-[#1E5CE6] text-white font-bold text-xs flex items-center justify-center shadow-xs group-hover:ring-4 group-hover:ring-blue-100 transition-all">
                                <?php echo $initial; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- User Dropdown Menu -->
                    <div id="userDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-800 p-2 z-50">
                        <div class="px-3 py-2.5 border-b border-slate-100 dark:border-slate-800">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate"><?php echo htmlspecialchars($display_name); ?></p>
                            <p class="text-[11px] text-slate-400 truncate"><?php echo htmlspecialchars($email ?: ($role === 'admin' ? 'Super Admin' : 'Anggota Tim')); ?></p>
                        </div>
                        <div class="py-1">
                            <a href="<?php echo route('profile'); ?>" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-[#1E5CE6] hover:bg-blue-50 dark:hover:bg-slate-800 rounded-xl transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Profil &amp; Keamanan
                            </a>
                            <a href="<?php echo route('reset-password'); ?>" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:text-[#1E5CE6] hover:bg-blue-50 dark:hover:bg-slate-800 rounded-xl transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Ubah Password
                            </a>
                            <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
                            <a href="<?php echo route('logout'); ?>" class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Keluar Sistem
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filter Sliders Button (Quick Actions) -->
                <button onclick="openCmdPalette()" class="p-2 text-slate-600 dark:text-slate-300 hover:text-[#1E5CE6] rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Pencarian & Perintah Cepat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <line x1="4" y1="6" x2="20" y2="6" stroke-linecap="round"/>
                        <circle cx="15" cy="6" r="2" fill="#FFFFFF" class="dark:fill-slate-800" stroke="currentColor" stroke-width="1.8"/>
                        <line x1="4" y1="12" x2="20" y2="12" stroke-linecap="round"/>
                        <circle cx="9" cy="12" r="2" fill="#FFFFFF" class="dark:fill-slate-800" stroke="currentColor" stroke-width="1.8"/>
                        <line x1="4" y1="18" x2="20" y2="18" stroke-linecap="round"/>
                        <circle cx="14" cy="18" r="2" fill="#FFFFFF" class="dark:fill-slate-800" stroke="currentColor" stroke-width="1.8"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- ==================== BODY AREA (SIDEBAR + MAIN CONTENT) ==================== -->
        <div class="flex flex-1 relative overflow-hidden">
            <!-- Mobile Backdrop Overlay -->
            <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-950/40 backdrop-blur-xs z-30 hidden lg:hidden"></div>

            <!-- ==================== LEFT CURVED ROYAL BLUE SIDEBAR ==================== -->
            <aside id="appSidebar" class="curved-sidebar w-[240px] shrink-0 text-white flex flex-col justify-between pt-7 pb-6 px-5 z-40 fixed lg:static inset-y-0 left-0 -translate-x-full lg:translate-x-0">
                <!-- Top CTA + Navigation Links -->
                <div class="flex-1 flex flex-col min-h-0">
                    <!-- "Upload New Files / Buat Cepat" Pill CTA -->
                    <div class="px-1 mb-6">
                        <button
                            onclick="openGlobalCreateModal()"
                            class="w-full bg-white text-[#1E5CE6] hover:bg-blue-50 active:scale-[0.98] font-bold text-xs py-3.5 px-5 rounded-full shadow-lg shadow-blue-950/20 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span>Buat Cepat</span>
                        </button>
                    </div>

                    <!-- Sidebar Menu List -->
                    <nav class="space-y-1 overflow-y-auto sidebar-scroll pr-1 flex-1" id="sidebarNav">
                        <!-- 1. Dasbor -->
                        <a href="<?php echo route('dashboard'); ?>" class="nav-item <?php echo $page==='dashboard'?'active':''; ?>" data-nav="dashboard" title="Dasbor Utama">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                            </span>
                            <span class="truncate">Dasbor</span>
                        </a>

                        <!-- 2. Kalender -->
                        <a href="<?php echo route('calendar'); ?>" class="nav-item <?php echo $page==='calendar'?'active':''; ?>" data-nav="calendar" title="Kalender Produktivitas">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </span>
                            <span class="truncate">Kalender</span>
                        </a>

                        <!-- 3. Catatan -->
                        <a href="<?php echo route('notes'); ?>" class="nav-item <?php echo $page==='notes'?'active':''; ?>" data-nav="notes" title="Catatan &amp; Ide">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            </span>
                            <span class="truncate">Catatan</span>
                        </a>

                        <!-- 4. Dokumen (Matching Goodle Drive Hub) -->
                        <a href="<?php echo route('documents'); ?>" class="nav-item <?php echo $page==='documents'?'active':''; ?>" data-nav="documents" title="Pusat Dokumen &amp; Berkas">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            </span>
                            <span class="truncate">Dokumen</span>
                        </a>

                        <!-- 5. Proyek Kanban -->
                        <a href="<?php echo route('projects'); ?>" class="nav-item <?php echo $page==='projects'?'active':''; ?>" data-nav="projects" title="Papan Kanban Proyek">
                            <div class="flex items-center justify-between w-full">
                                <div class="flex items-center gap-3 truncate">
                                    <span class="nav-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                                    </span>
                                    <span class="truncate">Proyek</span>
                                </div>
                                <?php if ($badge_pending_tasks > 0): ?>
                                <span class="text-[10px] bg-white/20 px-2 py-0.5 rounded-full font-bold ml-1 shrink-0"><?php echo $badge_pending_tasks; ?></span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <!-- 6. Aktivitas -->
                        <a href="<?php echo route('activities'); ?>" class="nav-item <?php echo $page==='activities'?'active':''; ?>" data-nav="activities" title="Audit Log &amp; Rekap Aktivitas">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </span>
                            <span class="truncate">Aktivitas</span>
                        </a>

                        <!-- 7. Profil & Keamanan -->
                        <a href="<?php echo route('profile'); ?>" class="nav-item <?php echo in_array($page, ['profile','reset-password'])?'active':''; ?>" data-nav="profile" title="Profil Akun">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <span class="truncate">Profil Saya</span>
                        </a>

                        <?php if (isAdmin()): ?>
                        <div class="pt-3 pb-1">
                            <p class="text-[10px] font-bold tracking-wider uppercase text-blue-200 px-3">ADMINISTRASI</p>
                        </div>

                        <!-- 8. Pengguna -->
                        <a href="<?php echo route('admin'); ?>" class="nav-item <?php echo $page==='admin'?'active':''; ?>" data-nav="admin" title="Manajemen Pengguna">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </span>
                            <span class="truncate">Pengguna</span>
                        </a>

                        <!-- 9. Cadangan Data -->
                        <a href="<?php echo route('backup'); ?>" class="nav-item <?php echo $page==='backup'?'active':''; ?>" data-nav="backup" title="Pencadangan Basis Data">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </span>
                            <span class="truncate">Cadangan Data</span>
                        </a>

                        <!-- 10. Optimasi Cache -->
                        <a href="<?php echo route('cache'); ?>" class="nav-item <?php echo $page==='cache'?'active':''; ?>" data-nav="cache" title="Pembersih Cache &amp; Disk">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            </span>
                            <span class="truncate">Optimasi Cache</span>
                        </a>

                        <!-- 11. Pengaturan Sistem -->
                        <a href="<?php echo route('settings'); ?>" class="nav-item <?php echo $page==='settings'?'active':''; ?>" data-nav="settings" title="Pengaturan Sistem &amp; Logo">
                            <span class="nav-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            </span>
                            <span class="truncate">Pengaturan</span>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>

                <!-- Bottom: STORAGE DETAILS (Exact match to Goodle Drive UI) -->
                <div class="pt-5 border-t border-white/15 shrink-0">
                    <p class="text-[10px] font-bold tracking-wider uppercase text-blue-200 mb-3">STORAGE DETAILS</p>

                    <!-- Storage Bar: Cloud Storage -->
                    <div class="mb-3.5">
                        <div class="flex items-center gap-2 text-xs text-white/95 mb-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                            <span class="font-medium text-[11.5px]">Penyimpanan</span>
                        </div>
                        <div class="w-full h-[3px] bg-white/25 rounded-full overflow-hidden mb-1">
                            <div class="storage-bar-fill h-full bg-white rounded-full" style="width: <?php echo $sidebar_storage_pct; ?>%;"></div>
                        </div>
                        <p class="text-[10px] text-blue-100/80 font-sans truncate"><?php echo $sidebar_storage_formatted; ?> dari <?php echo $sidebar_quota_formatted; ?> digunakan</p>
                    </div>

                    <!-- Storage Bar: Dokumen Aktif -->
                    <div>
                        <div class="flex items-center gap-2 text-xs text-white/95 mb-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12V4a4 4 0 014 4h-4zm0 0h8a4 4 0 01-4 4v-4zm0 0v8a4 4 0 01-4-4h4zm0 0H4a4 4 0 014-4v4z"/>
                            </svg>
                            <span class="font-medium text-[11.5px]">Dokumen</span>
                        </div>
                        <div class="w-full h-[3px] bg-white/25 rounded-full overflow-hidden mb-1">
                            <div class="storage-bar-fill h-full bg-white rounded-full" style="width: <?php echo min(100, max(5, $sidebar_doc_count * 5)); ?>%;"></div>
                        </div>
                        <p class="text-[10px] text-blue-100/80 font-sans"><?php echo $sidebar_doc_count; ?> berkas tersimpan</p>
                    </div>
                </div>
            </aside>

            <!-- ==================== MAIN CONTENT WORKSPACE ==================== -->
            <main class="flex-1 flex flex-col overflow-hidden relative bg-[#FAFBFD] dark:bg-[#0B1120] transition-colors" style="min-width:0">
                <div id="ajaxContent" class="flex-1 overflow-y-auto custom-scrollbar px-4 sm:px-8 lg:px-10 py-6 flex flex-col relative z-10">
                    <?php
                    // Simple Page Router
                    switch($page) {
                        case 'dashboard': include 'pages/dashboard.php'; break;
                        case 'calendar': include 'pages/calendar.php'; break;
                        case 'notes': include 'pages/notes.php'; break;
                        case 'documents': include 'pages/documents.php'; break;
                        case 'projects': include 'pages/projects.php'; break;
                        case 'activities': include 'pages/activities.php'; break;
                        case 'admin': if(isAdmin()) include 'pages/admin.php'; else echo "Akses Ditolak"; break;
                        case 'settings': if(isAdmin()) include 'pages/settings.php'; else echo "Akses Ditolak"; break;
                        case 'backup': if(isAdmin()) include 'pages/backup.php'; else echo "Akses Ditolak"; break;
                        case 'cache': if(isAdmin()) include 'pages/cache.php'; else echo "Akses Ditolak"; break;
                        case 'profile': include 'pages/profile.php'; break;
                        case 'reset-password': include 'pages/reset-password.php'; break;
                        default: include 'pages/dashboard.php';
                    }
                    ?>
                    <footer class="py-6 px-4 mt-auto text-center border-t border-slate-100 bg-transparent flex-shrink-0">
                        <p class="text-slate-400 text-xs font-medium">© 2026 <?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow Pro')); ?> v<?php echo APP_VERSION; ?> &bull; Dibuat oleh Habib Frambudi</p>
                    </footer>
                </div>
            </main>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- GLOBAL QUICK CREATE MODAL ("+ Buat Cepat") -->
    <!-- ============================================================== -->
    <div id="globalCreateModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full shadow-2xl overflow-hidden border border-slate-100 dark:border-slate-800 p-6 animate-modal-pop">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center font-bold">⚡</div>
                    <h3 class="font-extrabold text-slate-800 dark:text-slate-100 text-base">Buat Aksi Cepat</h3>
                </div>
                <button onclick="closeGlobalCreateModal()" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a href="<?php echo route('calendar'); ?>" onclick="closeGlobalCreateModal()" class="p-4 rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-800/40 hover:border-blue-300 dark:hover:border-blue-500/50 hover:bg-blue-50/50 dark:hover:bg-slate-800 flex flex-col items-center text-center gap-2 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/50 text-[#1E5CE6] dark:text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform">📅</div>
                    <span class="font-bold text-xs text-slate-800 dark:text-slate-200">Tugas Kalender</span>
                    <span class="text-[10px] text-slate-400">Jadwal kerja baru</span>
                </a>
                <a href="<?php echo route('documents'); ?>&openUpload=1" onclick="closeGlobalCreateModal()" class="p-4 rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-800/40 hover:border-emerald-300 dark:hover:border-emerald-500/50 hover:bg-emerald-50/50 dark:hover:bg-slate-800 flex flex-col items-center text-center gap-2 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">📄</div>
                    <span class="font-bold text-xs text-slate-800 dark:text-slate-200">Unggah Berkas</span>
                    <span class="text-[10px] text-slate-400">Simpan dokumen baru</span>
                </a>
                <a href="<?php echo route('notes'); ?>" onclick="closeGlobalCreateModal()" class="p-4 rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-800/40 hover:border-purple-300 dark:hover:border-purple-500/50 hover:bg-purple-50/50 dark:hover:bg-slate-800 flex flex-col items-center text-center gap-2 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform">📝</div>
                    <span class="font-bold text-xs text-slate-800 dark:text-slate-200">Catatan Baru</span>
                    <span class="text-[10px] text-slate-400">Ide &amp; memo kerja</span>
                </a>
                <a href="<?php echo route('projects'); ?>" onclick="closeGlobalCreateModal()" class="p-4 rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-800/40 hover:border-amber-300 dark:hover:border-amber-500/50 hover:bg-amber-50/50 dark:hover:bg-slate-800 flex flex-col items-center text-center gap-2 transition-all group">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 flex items-center justify-center group-hover:scale-110 transition-transform">📌</div>
                    <span class="font-bold text-xs text-slate-800 dark:text-slate-200">Proyek Kanban</span>
                    <span class="text-[10px] text-slate-400">Kelola target kerja</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- SHORTCUTS HELP MODAL (?) -->
    <!-- ============================================================== -->
    <div id="shortcutsModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full shadow-2xl overflow-hidden border border-slate-100 dark:border-slate-800 p-6 animate-modal-pop">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center font-bold">⌨️</div>
                    <h3 class="font-extrabold text-slate-800 dark:text-slate-100 text-base">Pintasan Keyboard</h3>
                </div>
                <button onclick="closeShortcutsModal()" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between py-1.5 border-b border-slate-50 dark:border-slate-800/80">
                    <span class="text-slate-600 dark:text-slate-300 font-medium">Buka Pencarian &amp; Perintah</span>
                    <kbd class="px-2 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md font-bold text-slate-700 dark:text-slate-200">Ctrl + K</kbd>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-slate-50 dark:border-slate-800/80">
                    <span class="text-slate-600 dark:text-slate-300 font-medium">Tutup Modal / Popup</span>
                    <kbd class="px-2 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md font-bold text-slate-700 dark:text-slate-200">ESC</kbd>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-slate-50 dark:border-slate-800/80">
                    <span class="text-slate-600 dark:text-slate-300 font-medium">Navigasi Pilihan Perintah</span>
                    <kbd class="px-2 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md font-bold text-slate-700 dark:text-slate-200">↑ / ↓</kbd>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-slate-50 dark:border-slate-800/80">
                    <span class="text-slate-600 dark:text-slate-300 font-medium">Eksekusi Perintah Terpilih</span>
                    <kbd class="px-2 py-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md font-bold text-slate-700 dark:text-slate-200">Enter</kbd>
                </div>
            </div>
            <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 text-center">
                <button onclick="closeShortcutsModal()" class="px-5 py-2.5 rounded-xl bg-[#1E5CE6] text-white font-bold text-xs hover:bg-[#1749B8] transition-colors w-full cursor-pointer">
                    Mengerti
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- GLOBAL COMMAND PALETTE MODAL (Ctrl + K / Cmd + K) -->
    <!-- ============================================================== -->
    <div id="cmdPaletteModal" class="fixed inset-0 hidden z-50 items-start justify-center pt-20 md:pt-28 p-4 bg-slate-950/60 backdrop-blur-md" role="dialog" aria-modal="true">
        <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl rounded-3xl max-w-xl w-full shadow-2xl overflow-hidden border border-slate-200/90 dark:border-slate-800 flex flex-col transition-all animate-modal-pop">
            <!-- Search bar -->
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center gap-3 bg-slate-50/60 dark:bg-slate-800/50">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E5CE6" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="cmdInput" placeholder="Ketik perintah atau cari halaman... (cth: kalender, dokumen, proyek)" 
                       class="flex-1 bg-transparent border-none text-sm font-bold text-slate-900 dark:text-white outline-none placeholder:text-slate-400 placeholder:font-medium"
                       oninput="filterCmdList(this.value)" autocomplete="off">
                <kbd class="px-2 py-0.5 text-[10px] font-black uppercase text-slate-400 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-2xs">ESC</kbd>
            </div>

            <!-- Command items list -->
            <div id="cmdItemsList" class="p-2 max-h-80 overflow-y-auto space-y-1 custom-scrollbar text-xs">
                <!-- Group: Navigasi -->
                <div class="cmd-group-label px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Navigasi Halaman</div>
                <a href="<?php echo route('dashboard'); ?>" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center text-xs">📊</span>
                        Dasbor Utama
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="<?php echo route('calendar'); ?>" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center text-xs">📅</span>
                        Kalender Produktivitas
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="<?php echo route('documents'); ?>" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center text-xs">📂</span>
                        Pusat Dokumen &amp; Berkas
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="<?php echo route('notes'); ?>" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center text-xs">📝</span>
                        Catatan &amp; Ide Kerja
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="<?php echo route('projects'); ?>" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center text-xs">📌</span>
                        Papan Kanban Proyek
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="<?php echo route('activities'); ?>" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center text-xs">⚡</span>
                        Audit Log &amp; Aktivitas
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="<?php echo route('profile'); ?>" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-[#1E5CE6] flex items-center justify-center text-xs">👤</span>
                        Profil &amp; Keamanan Akun
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>

                <!-- Group: Aksi Cepat & Pengaturan -->
                <div class="cmd-group-label px-3 pt-3 pb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Aksi Cepat &amp; Pengaturan</div>
                <button type="button" onclick="toggleTheme(); closeCmdPalette();" class="cmd-item w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-indigo-700 dark:hover:text-blue-400 font-bold transition-colors cursor-pointer text-left">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs">🌓</span>
                        Beralih Tema (Gelap / Terang)
                    </span>
                    <span class="text-[10px] text-indigo-600 dark:text-blue-400 font-bold">Ganti Tema</span>
                </button>
                <a href="<?php echo route('calendar'); ?>" onclick="closeCmdPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-emerald-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-emerald-700 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">➕</span>
                        Tambah Tugas Baru
                    </span>
                    <span class="text-[10px] text-emerald-600 font-bold">Aksi Cepat</span>
                </a>
                <a href="<?php echo route('documents'); ?>&openUpload=1" onclick="closeCmdPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-sky-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-sky-700 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center text-xs">⬆️</span>
                        Unggah Dokumen Berkas
                    </span>
                    <span class="text-[10px] text-sky-600 font-bold">Aksi Cepat</span>
                </a>
                <a href="<?php echo route('notes'); ?>" onclick="closeCmdPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-violet-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 hover:text-violet-700 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xs">✍️</span>
                        Tulis Draf Catatan Baru
                    </span>
                    <span class="text-[10px] text-violet-600 font-bold">Aksi Cepat</span>
                </a>
            </div>

            <!-- Footer instructions -->
            <div class="px-4 py-2.5 bg-slate-50/80 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <div class="flex items-center gap-2">
                    <span>Gunakan <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded text-[9px] font-bold">↑</kbd> <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded text-[9px] font-bold">↓</kbd> untuk memilih</span>
                    <span>·</span>
                    <span><kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded text-[9px] font-bold">Enter</kbd> untuk buka</span>
                </div>
                <span>Tekan <kbd class="px-1.5 py-0.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded text-[9px] font-bold">ESC</kbd> untuk menutup</span>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MOBILE FLOATING BOTTOM NAVIGATION BAR (< md screens) -->
    <!-- ============================================================== -->
    <nav class="md:hidden fixed bottom-3 inset-x-3 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border border-slate-200/90 dark:border-slate-800/90 rounded-2xl shadow-2xl px-2 py-1.5 flex items-center justify-around">
        <a href="<?php echo route('dashboard'); ?>" class="flex flex-col items-center gap-0.5 py-1 px-2.5 rounded-xl <?php echo $page === 'dashboard' ? 'text-[#1E5CE6] font-bold' : 'text-slate-500 dark:text-slate-400'; ?> transition-colors">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
            <span class="text-[10px]">Dasbor</span>
        </a>
        <a href="<?php echo route('calendar'); ?>" class="flex flex-col items-center gap-0.5 py-1 px-2.5 rounded-xl <?php echo $page === 'calendar' ? 'text-[#1E5CE6] font-bold' : 'text-slate-500 dark:text-slate-400'; ?> transition-colors">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span class="text-[10px]">Kalender</span>
        </a>
        <!-- Center Floating Action Button -->
        <button onclick="openGlobalCreateModal()" class="w-11 h-11 -mt-4 rounded-full bg-[#1E5CE6] hover:bg-[#1749B8] text-white flex items-center justify-center shadow-lg shadow-blue-600/35 active:scale-95 transition-transform cursor-pointer" title="Buat Aksi Cepat">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        </button>
        <a href="<?php echo route('notes'); ?>" class="flex flex-col items-center gap-0.5 py-1 px-2.5 rounded-xl <?php echo $page === 'notes' ? 'text-[#1E5CE6] font-bold' : 'text-slate-500 dark:text-slate-400'; ?> transition-colors">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <span class="text-[10px]">Catatan</span>
        </a>
        <a href="<?php echo route('documents'); ?>" class="flex flex-col items-center gap-0.5 py-1 px-2.5 rounded-xl <?php echo $page === 'documents' ? 'text-[#1E5CE6] font-bold' : 'text-slate-500 dark:text-slate-400'; ?> transition-colors">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <span class="text-[10px]">Dokumen</span>
        </a>
    </nav>

    <!-- ============================================================== -->
    <!-- FLOATING TOAST NOTIFICATION CONTAINER -->
    <!-- ============================================================== -->
    <div id="toastContainer" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2.5 pointer-events-none" style="min-width: 320px; max-width: 420px;"></div>

<script>
// Sidebar Toggle Logic
function toggleSidebar() {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar) return;
    const isClosed = sidebar.classList.contains('-translate-x-full');
    if (isClosed) {
        sidebar.classList.remove('-translate-x-full');
        backdrop?.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        backdrop?.classList.add('hidden');
    }
}
function closeSidebar() {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar?.classList.add('-translate-x-full');
    backdrop?.classList.add('hidden');
}

// Dropdown Helper
function toggleDropdown(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = el.classList.contains('hidden');
    document.querySelectorAll('#notifDropdown, #userDropdown').forEach(d => {
        if (d.id !== id) d.classList.add('hidden');
    });
    if (isHidden) {
        el.classList.remove('hidden');
    } else {
        el.classList.add('hidden');
    }
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('#notifDropdown') && !e.target.closest('[onclick*="notifDropdown"]')) {
        document.getElementById('notifDropdown')?.classList.add('hidden');
    }
    if (!e.target.closest('#userDropdown') && !e.target.closest('[onclick*="userDropdown"]')) {
        document.getElementById('userDropdown')?.classList.add('hidden');
    }
});

// Notification Panel Tabs
function switchNotifTab(tab) {
    const btnUrgent = document.getElementById('tabNotifUrgentBtn');
    const btnActivity = document.getElementById('tabNotifActivityBtn');
    const contentUrgent = document.getElementById('tabNotifUrgentContent');
    const contentActivity = document.getElementById('tabNotifActivityContent');
    if (!btnUrgent || !btnActivity) return;
    if (tab === 'urgent') {
        btnUrgent.className = 'px-2.5 py-1 rounded-lg bg-[#1E5CE6] text-white transition-all';
        btnActivity.className = 'px-2.5 py-1 rounded-lg text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white transition-all';
        contentUrgent?.classList.remove('hidden');
        contentActivity?.classList.add('hidden');
    } else {
        btnActivity.className = 'px-2.5 py-1 rounded-lg bg-[#1E5CE6] text-white transition-all';
        btnUrgent.className = 'px-2.5 py-1 rounded-lg text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white transition-all';
        contentActivity?.classList.remove('hidden');
        contentUrgent?.classList.add('hidden');
    }
}
window.switchNotifTab = switchNotifTab;

// Complete Task directly from Notification Center
function completeTaskFromNotif(todoId, btn) {
    if (!todoId || !btn) return;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="inline-block animate-spin text-[10px]">⏳</span>';

    const formData = new FormData();
    formData.append('quick_complete_task', '1');
    formData.append('todo_id', todoId);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');

    fetch('index.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.success) {
            const item = document.getElementById('notif-item-' + todoId);
            if (item) {
                item.style.transition = 'all 0.35s ease';
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    item.remove();
                    // Update badge count
                    const badge = document.getElementById('notifBadge');
                    if (badge) {
                        let count = parseInt(badge.innerText.trim()) || 0;
                        count = Math.max(0, count - 1);
                        if (count <= 0) {
                            badge.remove();
                            const listContainer = document.getElementById('tabNotifUrgentContent');
                            if (listContainer && listContainer.children.length === 0) {
                                listContainer.innerHTML = '<div class="py-8 px-4 text-center"><div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-2.5 text-xl font-bold shadow-xs">✓</div><p class="text-xs font-bold text-slate-800 dark:text-white mb-0.5">Semua Tenggat Waktu Aman!</p><p class="text-[11px] text-slate-400">Tidak ada agenda tugas yang jatuh tempo atau terlambat hari ini.</p></div>';
                            }
                        } else {
                            badge.innerText = count;
                        }
                    }
                }, 350);
            }
            if (typeof showToast === 'function') {
                showToast('Tugas berhasil diselesaikan! 🎉', 'success');
            }
        } else {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (typeof showToast === 'function') showToast('Gagal memperbarui tugas', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan', 'error');
    });
}
window.completeTaskFromNotif = completeTaskFromNotif;

// Modals Helper
function openGlobalCreateModal() {
    const m = document.getElementById('globalCreateModal');
    if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
}
function closeGlobalCreateModal() {
    const m = document.getElementById('globalCreateModal');
    if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
}
function openShortcutsModal() {
    const m = document.getElementById('shortcutsModal');
    if (m) { m.classList.remove('hidden'); m.classList.add('flex'); }
}
function closeShortcutsModal() {
    const m = document.getElementById('shortcutsModal');
    if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
}

// Instant In-Memory Page Cache for 0ms Navigation
const pageMemoryCache = new Map();

function updateNavActive(targetUrl) {
    try {
        const urlObj = new URL(targetUrl, window.location.origin);
        const page = urlObj.searchParams.get('page') || 'dashboard';
        document.querySelectorAll('.nav-item').forEach(el => {
            const dataNav = el.getAttribute('data-nav');
            if (dataNav === 'profile' && (page === 'profile' || page === 'reset-password')) {
                el.classList.add('active');
            } else {
                el.classList.toggle('active', dataNav === page);
            }
        });
    } catch (e) {}
}

// Prefetch page on hover (mouseenter/mouseover)
function prefetchPage(url) {
    if (!url || pageMemoryCache.has(url)) return;
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.text())
        .then(html => {
            if (html && !html.includes('Akses Ditolak') && html.length > 50) {
                pageMemoryCache.set(url, { html: html, time: Date.now() });
            }
        })
        .catch(() => {});
}

// AJAX Navigation System
function loadPage(url, pushState = true) {
    const loader = document.getElementById('pageLoader');
    const content = document.getElementById('ajaxContent');
    
    // Instant optimistic active tab feedback (0ms perceived latency)
    updateNavActive(url);

    // If cached in memory (< 45s old), render INSTANTLY (0ms)
    const cached = pageMemoryCache.get(url);
    const isFresh = cached && (Date.now() - cached.time < 45000);

    if (isFresh && content) {
        content.innerHTML = cached.html;
        executeScripts(content);
        content.scrollTop = 0;
        if (pushState) history.pushState({url: url}, '', url);
        
        // Background silent revalidation
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(newHtml => {
                if (newHtml && newHtml.length > 50) {
                    pageMemoryCache.set(url, { html: newHtml, time: Date.now() });
                }
            }).catch(() => {});
        return;
    }

    loader.classList.add('loading');
    if (content) content.classList.add('page-fade');
    
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => {
        if (res.redirected) {
            window.location.href = res.url;
            return;
        }
        return res.text();
    })
    .then(html => {
        if (!html) return;
        pageMemoryCache.set(url, { html: html, time: Date.now() });

        if (content) {
            content.innerHTML = html;
            executeScripts(content);
            content.scrollTop = 0;
            content.classList.remove('page-fade');
        }
        
        // Update URL
        if (pushState) history.pushState({url: url}, '', url);
        updateNavActive(url);

        loader.classList.add('finish');
        setTimeout(() => {
            loader.classList.remove('loading', 'finish');
        }, 120);
    })
    .catch(err => {
        console.error('AJAX Load Error:', err);
        loader.classList.remove('loading');
        if (content) content.classList.remove('page-fade');
    });
}

function executeScripts(container) {
    const scripts = container.querySelectorAll('script');
    scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
        });
        if (oldScript.src) {
            newScript.src = oldScript.src;
        } else {
            newScript.textContent = oldScript.textContent;
        }
        oldScript.parentNode.replaceChild(newScript, oldScript);
    });
}

// Intercept internal links for AJAX
document.addEventListener('click', e => {
    const link = e.target.closest('a');
    if (!link) return;
    
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.includes('logout') || href.includes('serve_file.php')) return;
    if (href.startsWith('http') && !href.includes(window.location.host)) return;
    
    if (href.includes('index.php?page=') || href === 'index.php') {
        e.preventDefault();
        loadPage(href);
        if (window.innerWidth < 1024) closeSidebar();
    }
});

// Hover prefetch for instantaneous 0ms page loads
document.addEventListener('mouseover', e => {
    const link = e.target.closest('a');
    if (!link) return;
    const href = link.getAttribute('href');
    if (href && (href.includes('index.php?page=') || href === 'index.php')) {
        prefetchPage(href);
    }
});

// Intercept forms for AJAX
document.addEventListener('submit', e => {
    const form = e.target;
    if (form.getAttribute('data-no-ajax') || form.getAttribute('enctype') === 'multipart/form-data') return;
    
    e.preventDefault();
    const loader = document.getElementById('pageLoader');
    loader.classList.add('loading');
    
    const formData = new FormData(form);
    const url = form.getAttribute('action') || window.location.href;
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    
    fetch(url, {
        method: method,
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => {
        if (res.redirected) {
            loadPage(res.url);
            return;
        }
        return res.text();
    })
    .then(html => {
        if (!html) return;
        const content = document.getElementById('ajaxContent');
        content.innerHTML = html;
        executeScripts(content);
        content.scrollTop = 0;
        
        loader.classList.add('finish');
        setTimeout(() => {
            loader.classList.remove('loading', 'finish');
        }, 300);
    })
    .catch(err => {
        console.error('Form AJAX error:', err);
        loader.classList.remove('loading');
    });
});

window.addEventListener('popstate', e => {
    if (e.state && e.state.url) {
        loadPage(e.state.url, false);
    }
});

// Toast System
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    const isSuccess = type === 'success';
    
    toast.className = `p-4 rounded-2xl shadow-xl flex items-center justify-between gap-3 text-xs font-semibold text-white pointer-events-auto transition-all duration-300 transform translate-y-4 opacity-0 ${
        isSuccess ? 'bg-[#1E5CE6]' : 'bg-rose-600'
    }`;
    
    const icon = isSuccess 
        ? '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'
        : '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';

    toast.innerHTML = `
        <div class="flex items-center gap-2.5">
            ${icon}
            <span>${message}</span>
        </div>
        <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white ml-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    `;

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Global safe upload modal trigger
window.openUploadModal = function() {
    if (typeof window.documentsOpenUploadModal === 'function') {
        window.documentsOpenUploadModal();
    } else {
        if (typeof loadPage === 'function') {
            loadPage('index.php?page=documents&openUpload=1');
        } else {
            window.location.href = 'index.php?page=documents&openUpload=1';
        }
    }
};

// URL query notifications
function checkUrlNotifications() {
    const params = new URLSearchParams(window.location.search);
    const success = params.get('success');
    const error = params.get('error');

    if (success) {
        let msg = 'Operasi berhasil dilakukan.';
        if (success === 'uploaded') msg = 'File dokumen berhasil diunggah.';
        if (success === 'deleted') msg = 'Dokumen berhasil dihapus.';
        if (success === 'cleaned') msg = 'Pembersihan database berhasil diselesaikan.';
        showToast(msg, 'success');

        params.delete('success');
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newUrl);
    } else if (error) {
        let msg = 'Terjadi kesalahan sistem.';
        if (error === 'upload_failed') msg = 'Gagal mengunggah file. Periksa format dan batas ukuran.';
        showToast(msg, 'error');

        params.delete('error');
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
        window.history.replaceState({}, '', newUrl);
    }
}
document.addEventListener('DOMContentLoaded', checkUrlNotifications);

// Command Palette Logic
let cmdSelectedIndex = -1;

window.openCmdPalette = function() {
    const modal = document.getElementById('cmdPaletteModal');
    const input = document.getElementById('cmdInput');
    if (!modal || !input) return;

    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    input.value = '';
    cmdSelectedIndex = -1;
    filterCmdList('');
    setTimeout(() => input.focus(), 50);
};

window.closeCmdPalette = function() {
    const modal = document.getElementById('cmdPaletteModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
        cmdSelectedIndex = -1;
    }
};

window.filterCmdList = function(query) {
    const q = (query || '').toLowerCase().trim();
    const items = document.querySelectorAll('#cmdItemsList .cmd-item');
    let firstVisible = -1;
    items.forEach((el, index) => {
        const text = el.textContent.toLowerCase();
        const visible = (!q || text.includes(q));
        el.style.display = visible ? 'flex' : 'none';
        el.classList.remove('bg-blue-50', 'ring-2', 'ring-blue-500/20');
        if (visible && firstVisible === -1) firstVisible = index;
    });
    cmdSelectedIndex = -1;
    if (firstVisible !== -1) {
        updateCmdHighlight(firstVisible);
    }
};

function getVisibleCmdItems() {
    return Array.from(document.querySelectorAll('#cmdItemsList .cmd-item')).filter(el => el.style.display !== 'none');
}

function updateCmdHighlight(index) {
    const items = getVisibleCmdItems();
    if (!items.length) return;
    items.forEach(el => el.classList.remove('bg-blue-50', 'ring-2', 'ring-blue-500/20'));
    if (index >= 0 && index < items.length) {
        cmdSelectedIndex = index;
        items[index].classList.add('bg-blue-50', 'ring-2', 'ring-blue-500/20');
        items[index].scrollIntoView({ block: 'nearest' });
    }
}

// Global Hotkeys
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('cmdPaletteModal');
    const isModalOpen = modal && !modal.classList.contains('hidden') && modal.style.display === 'flex';

    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        if (isModalOpen) {
            closeCmdPalette();
        } else {
            openCmdPalette();
        }
    } else if (e.key === 'Escape') {
        if (isModalOpen) closeCmdPalette();
        closeGlobalCreateModal();
        closeShortcutsModal();
    } else if (isModalOpen) {
        const visibleItems = getVisibleCmdItems();
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = (cmdSelectedIndex + 1) >= visibleItems.length ? 0 : cmdSelectedIndex + 1;
            updateCmdHighlight(next);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = (cmdSelectedIndex - 1) < 0 ? visibleItems.length - 1 : cmdSelectedIndex - 1;
            updateCmdHighlight(prev);
        } else if (e.key === 'Enter') {
            if (cmdSelectedIndex >= 0 && cmdSelectedIndex < visibleItems.length) {
                e.preventDefault();
                visibleItems[cmdSelectedIndex].click();
            }
        }
    }
});

// Close command palette on outer click
document.getElementById('cmdPaletteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCmdPalette();
});
document.getElementById('globalCreateModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeGlobalCreateModal();
});
document.getElementById('shortcutsModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeShortcutsModal();
});

// Theme Management (Dark / Light Mode)
function updateThemeIcon() {
    const isDark = document.documentElement.classList.contains('dark');
    const sun = document.getElementById('themeIconSun');
    const moon = document.getElementById('themeIconMoon');
    if (sun && moon) {
        if (isDark) {
            sun.classList.remove('hidden');
            sun.classList.add('block');
            moon.classList.remove('block');
            moon.classList.add('hidden');
        } else {
            sun.classList.remove('block');
            sun.classList.add('hidden');
            moon.classList.remove('hidden');
            moon.classList.add('block');
        }
    }
}

window.toggleTheme = function() {
    const isDark = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem('taskflow_theme', isDark ? 'dark' : 'light');
    } catch (e) {}
    updateThemeIcon();
    showToast(isDark ? 'Tema Gelap diaktifkan' : 'Tema Terang diaktifkan', 'success');
};

document.addEventListener('DOMContentLoaded', updateThemeIcon);

// Register Service Worker for PWA with cache-busting and instant activation
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js?v=<?php echo APP_VERSION; ?>').then((reg) => {
            reg.update();
        }).catch(() => {});
    });
}
</script>

</body>
</html>
