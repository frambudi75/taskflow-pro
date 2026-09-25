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

// Simple Logout logic
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . route('login'));
    exit;
}

// Standalone Print View or Export Bypass (Bypasses main UI chrome completely)
if ((isset($_GET['print_view']) && $_GET['print_view'] === '1') || (isset($_GET['export']) && $_GET['export'] === 'csv')) {
    ob_clean();
    if ($page === 'reports') {
        include 'pages/reports.php';
        exit;
    }
    if ($page === 'team-reports') {
        if (isAdmin()) {
            include 'pages/team-reports.php';
        } else {
            echo "Akses Ditolak";
        }
        exit;
    }
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
        case 'reports': include 'pages/reports.php'; break;
        case 'activities': include 'pages/activities.php'; break;
        case 'chat': include 'pages/chat.php'; break;
        case 'admin': if(isAdmin()) include 'pages/admin.php'; else echo "Akses Ditolak"; break;
        case 'team-reports': if(isAdmin()) include 'pages/team-reports.php'; else echo "Akses Ditolak"; break;
        case 'settings': if(isAdmin()) include 'pages/settings.php'; else echo "Akses Ditolak"; break;
        case 'backup': if(isAdmin()) include 'pages/backup.php'; else echo "Akses Ditolak"; break;
        case 'cache': if(isAdmin()) include 'pages/cache.php'; else echo "Akses Ditolak"; break;
        case 'profile': include 'pages/profile.php'; break;
        case 'reset-password': include 'pages/reset-password.php'; break;
        default: include 'pages/dashboard.php';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow Pro')); ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-main: #f1f5f9;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #f1f5f9;
            --sidebar-grad-start: #0f172a;
            --sidebar-grad-end: #1e293b;
            --sidebar-text: rgba(203,213,225,0.85);
            --sidebar-icon-bg: rgba(255,255,255,0.06);
        }

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; overflow: hidden; height: 100dvh; min-height: 100dvh; }
        
        /* Universal sleek scrollbars */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(148, 163, 184, 0.4) transparent;
        }
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.35);
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(148, 163, 184, 0.6);
        }

        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius: 10px; }

        /* Dark sidebar sleek scrollbar */
        .sidebar-scrollbar,
        #sidebar nav,
        #sidebar {
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }
        .sidebar-scrollbar::-webkit-scrollbar,
        #sidebar nav::-webkit-scrollbar,
        #sidebar::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        .sidebar-scrollbar::-webkit-scrollbar-track,
        #sidebar nav::-webkit-scrollbar-track,
        #sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-scrollbar::-webkit-scrollbar-thumb,
        #sidebar nav::-webkit-scrollbar-thumb,
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.18);
            border-radius: 9999px;
        }
        .sidebar-scrollbar::-webkit-scrollbar-thumb:hover,
        #sidebar nav::-webkit-scrollbar-thumb:hover,
        #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.35);
        }
        
        .main-content { background: var(--bg-main); }

        .sidebar-bg {
            background: #0f172a;
            position: relative;
            overflow: hidden;
            box-shadow: 2px 0 12px rgba(0,0,0,0.12);
        }

        .sidebar-section-label {
            font-size: 10px; font-weight: 700; letter-spacing: 0.12em;
            text-transform: uppercase; color: #94a3b8;
            padding: 0 16px; margin: 12px 0 6px;
        }

        .nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px; border-radius: 12px;
            margin: 2px 8px; font-size: 13px; font-weight: 600;
            color: var(--sidebar-text); transition: all 0.2s ease;
            text-decoration: none; position: relative;
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item:focus-visible {
            outline: 2px solid #818cf8;
            outline-offset: 2px;
        }
        .nav-item.active {
            background: #4338ca; color: #fff;
        }
        .nav-item.active .nav-icon { color: #fff; background: rgba(255,255,255,0.15); }

        .nav-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; background: var(--sidebar-icon-bg);
            color: rgba(203,213,225,0.7); transition: all 0.2s ease;
        }
        .nav-item:hover .nav-icon { background: rgba(255,255,255,0.12); color: #fff; }

        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 10px 16px; }
        .sidebar-brand-text { color: #fff; font-size: 17px; font-weight: 800; }
        .sidebar-brand-sub {
            font-size: 10px; font-weight: 600; letter-spacing: 0.15em;
            text-transform: uppercase; color: #94a3b8;
        }

        .sidebar-user-card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; padding: 12px; margin: 0 8px 8px;
        }
        .sidebar-user-name { color: #f1f5f9; font-size: 13px; font-weight: 700; }
        .sidebar-logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: calc(100% - 16px); min-height: 44px; padding: 10px; background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2); border-radius: 10px;
            color: #fca5a5; font-size: 12px; font-weight: 700;
            text-decoration: none; margin: 8px 8px 0;
            transition: all 0.2s ease;
        }
        .sidebar-logout-btn:hover { background: rgba(239,68,68,0.2); color: #fff; }
        .sidebar-logout-btn:focus-visible {
            outline: 2px solid #ef4444;
            outline-offset: 2px;
        }
        /* Mobile Overlaybackdrop */
        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px); z-index: 40;
            transition: all 0.3s ease; opacity: 0; visibility: hidden;
        }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }

        #pageLoader {
            position: fixed; top: 0; left: 0; right: 0; height: 3px;
            background: #4f46e5;
            z-index: 9999; transform: translateX(-100%); transition: transform 0.3s ease;
            pointer-events: none;
        }
        #pageLoader.loading { transform: translateX(-30%); }
        #pageLoader.finish { transform: translateX(0); }

        /* Desktop Collapsed Sidebar Styles */
        @media (min-width: 768px) {
            aside#sidebar.sidebar-collapsed {
                width: 5rem !important; /* 80px */
            }
            aside#sidebar.sidebar-collapsed .sidebar-header-box {
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 1.25rem 0.5rem 0.75rem !important;
                gap: 0.625rem !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-brand-text-wrap {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed #sidebarCollapseBtn {
                display: flex !important;
                margin: 0 auto !important;
                width: 2rem !important;
                height: 2rem !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-section-label {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-divider {
                margin: 8px 12px !important;
            }
            aside#sidebar.sidebar-collapsed .nav-item {
                justify-content: center !important;
                padding: 10px 0 !important;
                margin: 4px 10px !important;
                gap: 0 !important;
            }
            aside#sidebar.sidebar-collapsed .nav-label {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-user-card {
                padding: 8px !important;
                margin: 0 10px 8px !important;
                justify-content: center !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-user-info {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-logout-btn {
                width: 38px !important;
                min-height: 38px !important;
                padding: 0 !important;
                margin: 6px auto 0 !important;
                justify-content: center !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-logout-text {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed #collapseIcon {
                transform: rotate(180deg);
            }
            aside#sidebar.sidebar-collapsed #unreadChatBadge {
                position: absolute;
                top: 2px;
                right: 2px;
            }
        }

        @media print {
            header, aside, #sidebar, .sidebar-overlay, #pageLoader, #unreadChatBadge, .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                color: #000000 !important;
                height: auto !important;
                overflow: visible !important;
            }
            main, #ajaxContent {
                overflow: visible !important;
                height: auto !important;
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }

    </style>
</head>
<body class="bg-slate-50 overflow-hidden h-screen flex flex-col">
    <div id="pageLoader"></div>

    <header class="md:hidden flex items-center justify-between px-5 py-4 bg-[#0f172a] shadow-sm z-30 flex-shrink-0">
        <div class="flex items-center gap-3">
             <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center text-white">
                 <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
             </div>
             <span class="text-white font-bold text-sm uppercase tracking-tight"><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow')); ?></span>
        </div>
        <button onclick="toggleSidebar()" aria-label="Buka menu navigasi" class="w-11 h-11 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-white">
             <svg id="iconMenu" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
             <svg id="iconClose" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="flex flex-1 overflow-hidden">
        <aside id="sidebar" 
               class="sidebar-bg fixed inset-y-0 left-0 z-50 w-72 transform -translate-x-full transition-all duration-300 md:static md:translate-x-0 md:inset-auto md:w-64 flex flex-col h-full overflow-hidden flex-shrink-0">

            <div class="px-4 pt-5 pb-4 flex items-center justify-between relative z-20 sidebar-header-box">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-sm flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    </div>
                    <div class="sidebar-brand-text-wrap overflow-hidden whitespace-nowrap">
                        <div class="sidebar-brand-text truncate"><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow')); ?></div>
                        <div class="sidebar-brand-sub truncate">Management System</div>
                    </div>
                </div>
                <button type="button" onclick="toggleDesktopSidebar()" id="sidebarCollapseBtn" aria-label="Minimize sidebar" title="Minimize sidebar" class="hidden md:flex w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white items-center justify-center transition-all flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <svg id="collapseIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="transition-transform duration-300"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto sidebar-scrollbar pb-2 relative z-20">

                <!-- Main -->
                <div class="sidebar-section-label">Menu Utama</div>

                <a href="<?php echo route('dashboard'); ?>" class="nav-item <?php echo $page==='dashboard'?'active':''; ?>" data-nav="dashboard" title="Dashboard">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </span>
                    <span class="nav-label truncate">Dashboard</span>
                </a>

                <a href="<?php echo route('calendar'); ?>" class="nav-item <?php echo $page==='calendar'?'active':''; ?>" data-nav="calendar" title="Kalender">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    <span class="nav-label truncate">Kalender</span>
                </a>

                <a href="<?php echo route('notes'); ?>" class="nav-item <?php echo $page==='notes'?'active':''; ?>" data-nav="notes" title="Catatan">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </span>
                    <span class="nav-label truncate">Catatan</span>
                </a>

                <a href="<?php echo route('documents'); ?>" class="nav-item <?php echo $page==='documents'?'active':''; ?>" data-nav="documents" title="Dokumen">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span class="nav-label truncate">Dokumen</span>
                </a>

                <a href="<?php echo route('projects'); ?>" class="nav-item <?php echo $page==='projects'?'active':''; ?>" data-nav="projects" title="Proyek (Kanban)">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                    </span>
                    <span class="nav-label truncate">Proyek (Kanban)</span>
                </a>

                <a href="<?php echo route('reports'); ?>" class="nav-item <?php echo $page==='reports'?'active':''; ?>" data-nav="reports" title="Laporan Harian">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </span>
                    <span class="nav-label truncate">Laporan Harian</span>
                </a>

                <a href="<?php echo route('activities'); ?>" class="nav-item <?php echo $page==='activities'?'active':''; ?>" data-nav="activities" title="Aktivitas">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </span>
                    <span class="nav-label truncate">Aktivitas</span>
                </a>

                <?php if (getSetting('enable_chat', '1') === '1'): ?>
                <a href="<?php echo route('chat'); ?>" class="nav-item <?php echo $page==='chat'?'active':''; ?>" data-nav="chat" title="Pesan">
                    <span class="nav-icon relative">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Pesan</span>
                    <?php 
                        $unreadMsg = countUnreadMessages($user_id);
                        $badgeStyle = $unreadMsg > 0 ? '' : 'display:none';
                    ?>
                    <span id="unreadChatBadge" style="<?php echo $badgeStyle; ?>" 
                          class="w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-[10px] font-black border-2 border-[#1e293b]">
                        <?php echo $unreadMsg; ?>
                    </span>
                </a>
                <?php endif; ?>

                <div class="sidebar-divider"></div>

                <!-- Account -->
                <div class="sidebar-section-label">Akun Saya</div>

                <a href="<?php echo route('profile'); ?>" class="nav-item <?php echo $page==='profile'?'active':''; ?>" data-nav="profile" title="Profil">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <span class="nav-label truncate">Profil</span>
                </a>

                <a href="<?php echo route('reset-password'); ?>" class="nav-item <?php echo $page==='reset-password'?'active':''; ?>" data-nav="reset-password" title="Password">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <span class="nav-label truncate">Password</span>
                </a>

                <?php if (isAdmin()): ?>
                <div class="sidebar-divider"></div>
                <div class="sidebar-section-label">Administrasi</div>

                <a href="<?php echo route('admin'); ?>" class="nav-item <?php echo $page==='admin'?'active':''; ?>" data-nav="admin" title="Users">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <span class="nav-label truncate">Users</span>
                </a>

                <a href="<?php echo route('team-reports'); ?>" class="nav-item <?php echo $page==='team-reports'?'active':''; ?>" data-nav="team-reports" title="Laporan Tim">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><line x1="14" y1="11" x2="20" y2="11"/></svg>
                    </span>
                    <span class="nav-label truncate">Laporan Tim</span>
                </a>

                <a href="<?php echo route('backup'); ?>" class="nav-item <?php echo $page==='backup'?'active':''; ?>" data-nav="backup" title="Backup">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </span>
                    <span class="nav-label truncate">Backup</span>
                </a>

                <a href="<?php echo route('cache'); ?>" class="nav-item <?php echo $page==='cache'?'active':''; ?>" data-nav="cache" title="Cache">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </span>
                    <span class="nav-label truncate">Cache</span>
                </a>

                <a href="<?php echo route('settings'); ?>" class="nav-item <?php echo $page==='settings'?'active':''; ?>" data-nav="settings" title="Pengaturan">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span class="nav-label truncate">Pengaturan</span>
                </a>
<?php endif; ?>

            </nav>

            <!-- User Footer -->
            <?php
            $profilePhoto = $_SESSION['profile_photo'] ?? null;
            $avatarSrc = $profilePhoto && file_exists(__DIR__ . '/uploads/avatars/' . $profilePhoto)
                ? 'uploads/avatars/' . $profilePhoto
                : getAvatarDataUri($display_name, 40);
            ?>
            <div class="relative z-20 pt-2 border-t border-white/10 flex-shrink-0">

                <div class="sidebar-user-card flex items-center gap-3">
                    <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="w-9 h-9 rounded-xl border border-white/20 object-cover flex-shrink-0" />
                    <div class="sidebar-user-info flex-1 min-w-0">
                        <div class="sidebar-user-name truncate"><?php echo htmlspecialchars($display_name); ?></div>
                        <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-0.5"><?php echo $role; ?></div>
                    </div>
                </div>

                <a href="<?php echo route('logout'); ?>" class="sidebar-logout-btn" title="Logout">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" class="flex-shrink-0"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span class="sidebar-logout-text">Logout</span>
                </a>
                <div class="pb-4"></div>
            </div>
        </aside>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-hidden flex flex-col" style="min-width:0">
        <div id="ajaxContent" class="flex-1 overflow-y-auto custom-scrollbar flex flex-col">
            <?php
            // Simple Page Router
            switch($page) {
                case 'dashboard': include 'pages/dashboard.php'; break;
                case 'calendar': include 'pages/calendar.php'; break;
                case 'notes': include 'pages/notes.php'; break;
                case 'documents': include 'pages/documents.php'; break;
                case 'projects': include 'pages/projects.php'; break;
                case 'reports': include 'pages/reports.php'; break;
                case 'activities': include 'pages/activities.php'; break;
                case 'chat': include 'pages/chat.php'; break;
                case 'admin': if(isAdmin()) include 'pages/admin.php'; else echo "Akses Ditolak"; break;
                case 'team-reports': if(isAdmin()) include 'pages/team-reports.php'; else echo "Akses Ditolak"; break;
                case 'settings': if(isAdmin()) include 'pages/settings.php'; else echo "Akses Ditolak"; break;
                case 'backup': if(isAdmin()) include 'pages/backup.php'; else echo "Akses Ditolak"; break;
                case 'cache': if(isAdmin()) include 'pages/cache.php'; else echo "Akses Ditolak"; break;
                case 'profile': include 'pages/profile.php'; break;
                case 'reset-password': include 'pages/reset-password.php'; break;
                default: include 'pages/dashboard.php';
            }
            ?>
        </div>
        <footer class="bg-white border-t border-slate-200 py-3 px-6 flex-shrink-0">
            <div class="text-center">
                <p class="text-slate-400 text-xs font-medium">© 2026 TaskFlow. Dibuat oleh Habib Frambudi</p>
            </div>
        </footer>
    </main>

    </div>

<script>
// AJAX Navigation System
function loadPage(url, pushState = true) {
    const loader = document.getElementById('pageLoader');
    const content = document.getElementById('ajaxContent');
    
    loader.classList.add('loading');
    
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
        const content = document.getElementById('ajaxContent');
        content.innerHTML = html;
        executeScripts(content);
        content.scrollTop = 0;
        
        // Update URL
        if (pushState) history.pushState({url: url}, '', url);
        
        // Update active menu
        const urlObj = new URL(url, window.location.origin);
        const page = urlObj.searchParams.get('page') || 'dashboard';
        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.toggle('active', el.getAttribute('data-nav') === page);
        });

        loader.classList.add('finish');
        setTimeout(() => {
            loader.classList.remove('loading', 'finish');
        }, 300);
    })
    .catch(err => {
        console.error('AJAX Load Error:', err);
        loader.classList.remove('loading');
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

// Intercept all internal links
document.addEventListener('click', e => {
    const link = e.target.closest('a');
    if (!link) return;
    
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.includes('logout')) return;
    if (href.startsWith('http') && !href.includes(window.location.host)) return;
    
    // Check if it's an internal page link
    if (href.includes('index.php?page=') || href === 'index.php') {
        e.preventDefault();
        loadPage(href);
        if (window.innerWidth < 768) closeSidebar();
    }
});

// Intercept all forms for AJAX
document.addEventListener('submit', e => {
    const form = e.target;
    // Don't intercept if specified or if it's multipart file upload
    if (form.getAttribute('data-no-ajax') || form.getAttribute('enctype') === 'multipart/form-data') return;
    
    e.preventDefault();
    const loader = document.getElementById('pageLoader');
    loader.classList.add('loading');
    
    const formData = new FormData(form);
    const url = form.getAttribute('action') || window.location.href;
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    
    fetch(url, {
        method: method,
        body: method === 'POST' ? formData : null,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.text())
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
        console.error('Form AJAX Error:', err);
        loader.classList.remove('loading');
    });
});

window.addEventListener('popstate', e => {
    if (e.state && e.state.url) {
        loadPage(e.state.url, false);
    } else {
        location.reload();
    }
});

function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var iMenu   = document.getElementById('iconMenu');
    var iClose  = document.getElementById('iconClose');
    
    var isOpen = sidebar.classList.toggle('translate-x-0');
    if (isOpen) {
        sidebar.classList.remove('-translate-x-full');
    } else {
        sidebar.classList.add('-translate-x-full');
    }
    overlay.classList.toggle('active', isOpen);
    
    iMenu.style.display  = isOpen ? 'none' : '';
    iClose.style.display = isOpen ? '' : 'none';
}

function closeSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('translate-x-0');
    sidebar.classList.add('-translate-x-full');
    overlay.classList.remove('active');
    document.getElementById('iconMenu').style.display  = '';
    document.getElementById('iconClose').style.display = 'none';
}

function toggleDesktopSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
    localStorage.setItem('taskflow_sidebar_collapsed', isCollapsed ? '1' : '0');
    const collapseBtn = document.getElementById('sidebarCollapseBtn');
    if (collapseBtn) {
        collapseBtn.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Minimize sidebar');
        collapseBtn.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Minimize sidebar');
    }
}

// Restore sidebar collapsed preference on load
(function() {
    try {
        if (window.innerWidth >= 768 && localStorage.getItem('taskflow_sidebar_collapsed') === '1') {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.add('sidebar-collapsed');
                const collapseBtn = document.getElementById('sidebarCollapseBtn');
                if (collapseBtn) {
                    collapseBtn.setAttribute('aria-label', 'Expand sidebar');
                    collapseBtn.setAttribute('title', 'Expand sidebar');
                }
            }
        }
    } catch(e) {}
})();

<?php if (getSetting('enable_chat', '1') === '1'): ?>
let lastUnreadCount = <?php echo countUnreadMessages($user_id); ?>;
function updateChatBadge() {
    fetch('api_chat.php?action=count_unread', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('unreadChatBadge');
            if (badge) {
                if (data.unread_count > lastUnreadCount) {
                    const sound = document.getElementById('notifSound');
                    if (sound) sound.play().catch(e => console.log('Audio play blocked:', e));
                    if (Notification.permission === "granted") {
                        new Notification("TaskFlow Pro", {
                            body: "Pesan baru masuk! Klik untuk baca.",
                            icon: "favicon.ico"
                        }).onclick = function() {
                            window.focus();
                            loadPage('index.php?page=chat');
                        };
                    }
                }
                lastUnreadCount = data.unread_count;
                if (data.unread_count > 0) {
                    badge.innerText = data.unread_count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        }).catch(e => console.log('Chat badge error:', e));
}
if (Notification.permission !== "granted" && Notification.permission !== "denied") {
    Notification.requestPermission();
}
setInterval(updateChatBadge, 15000); 
updateChatBadge(); 
<?php endif; ?>
</script>

</body>
</html>
