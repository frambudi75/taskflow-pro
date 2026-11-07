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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow Pro')); ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: '#5148d9'
                    }
                }
            }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-canvas: #f3f6fb;
            --bg-surface: #ffffff;
            --brand-primary: #5148d9;
            --brand-navy: #0b1220;
            --color-success: #16a37a;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --sidebar-grad-start: #0b1220;
            --sidebar-grad-end: #0f172a;
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

        /* Elegant micro-interactions and smooth transitions */
        .sidebar-bg {
            background: linear-gradient(180deg, #0b1120 0%, #0f172a 50%, #111827 100%);
            position: relative;
            overflow: hidden;
            box-shadow: 4px 0 24px rgba(0,0,0,0.25);
            border-right: 1px solid rgba(255,255,255,0.06);
        }

        /* Subtle glow accent inside sidebar */
        .sidebar-bg::before {
            content: '';
            position: absolute;
            top: -60px;
            left: -60px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
            pointer-events: none;
            z-index: 1;
        }

        .sidebar-section-label {
            font-size: 10px; font-weight: 800; letter-spacing: 0.12em;
            text-transform: uppercase; color: #94a3b8;
            padding: 0 16px; margin: 14px 0 6px;
        }

        .nav-item {
            display: flex; align-items: center; gap: 11px;
            padding: 8px 12px; border-radius: 10px;
            margin: 2px 10px; font-size: 13px; font-weight: 500;
            color: #94a3b8; transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none; position: relative;
            border: 1px solid transparent;
        }
        .nav-item:hover {
            background: rgba(255,255,255,0.06);
            color: #f1f5f9;
        }
        .nav-item:active {
            transform: scale(0.98);
        }
        .nav-item:focus-visible {
            outline: 2px solid #6366f1;
            outline-offset: 1px;
        }
        /* Soft Accent Active State with Luminous Left Indicator Bar */
        .nav-item.active {
            background: rgba(99, 102, 241, 0.18) !important;
            border-color: rgba(99, 102, 241, 0.32) !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 10px -2px rgba(99, 102, 241, 0.28) !important;
        }
        .nav-item .nav-indicator {
            opacity: 0;
            transform: scaleY(0.4);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .nav-item.active .nav-indicator {
            opacity: 1;
            transform: scaleY(1);
        }
        .nav-item.active .nav-icon {
            color: #818cf8 !important;
            background: transparent !important;
        }

        /* Clean Stroke Icon - No Heavy Container Box */
        .nav-icon {
            width: 20px; height: 20px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; background: transparent;
            color: #94a3b8; transition: all 0.18s ease;
            border: none;
        }
        .nav-item:hover .nav-icon {
            color: #f8fafc;
        }

        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.06); margin: 10px 16px; }
        .sidebar-brand-text { color: #ffffff; font-size: 15px; font-weight: 900; letter-spacing: -0.02em; }
        .sidebar-brand-sub {
            font-size: 9px; font-weight: 700; letter-spacing: 0.14em;
            text-transform: uppercase; color: #818cf8; line-height: 1.2;
        }

        .sidebar-user-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; padding: 7px 10px; margin: 0 10px 8px;
            transition: all 0.18s ease;
        }
        .sidebar-user-card:hover {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.14);
        }
        .sidebar-user-name { color: #f8fafc; font-size: 12px; font-weight: 700; }
        
        .sidebar-logout-icon-btn {
            width: 32px; height: 32px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #94a3b8; background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            flex-shrink: 0; cursor: pointer;
        }
        .sidebar-logout-icon-btn:hover {
            color: #f87171 !important;
            background: rgba(239, 68, 68, 0.15) !important;
            border-color: rgba(239, 68, 68, 0.3) !important;
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
        }
        .sidebar-logout-icon-btn:active {
            transform: scale(0.95);
        }
        
        .sidebar-scrollbar {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .sidebar-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Ambient Glassmorphism & High-Craft Surface System */
        .glass-panel,
        .glass-surface {
            background: rgba(255, 255, 255, 0.76) !important;
            backdrop-filter: blur(18px) saturate(180%);
            -webkit-backdrop-filter: blur(18px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.85) !important;
            box-shadow: 
                0 4px 20px -2px rgba(15, 23, 42, 0.04),
                0 1px 3px rgba(15, 23, 42, 0.02),
                inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
        }
        .dark .glass-panel,
        .dark .glass-surface {
            background: rgba(15, 23, 42, 0.78) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            box-shadow: 
                0 4px 24px -2px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.06) !important;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.78) !important;
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.85) !important;
            box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95) !important;
            transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-card:hover {
            background: rgba(255, 255, 255, 0.94) !important;
            border-color: rgba(99, 102, 241, 0.35) !important;
            box-shadow: 0 12px 30px -4px rgba(79, 70, 229, 0.12), inset 0 1px 0 #ffffff !important;
            transform: translateY(-2px);
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
            background: linear-gradient(90deg, #4f46e5, #818cf8, #06b6d4);
            z-index: 9999; transform: translateX(-100%); transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
            box-shadow: 0 0 10px rgba(99, 102, 241, 0.5);
        }
        #pageLoader.loading { transform: translateX(-25%); }
        #pageLoader.finish { transform: translateX(0); }

        #ajaxContent {
            transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #ajaxContent.page-fade {
            opacity: 0.4;
        }

        /* Desktop Collapsed Sidebar Styles */
        @media (min-width: 768px) {
            aside#sidebar.sidebar-collapsed {
                width: 4.75rem !important; /* 76px */
            }
            aside#sidebar.sidebar-collapsed .sidebar-header-box {
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
                padding: 1.1rem 0.5rem 0.75rem !important;
                gap: 0.5rem !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-brand-text-wrap {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed #sidebarCollapseBtn {
                display: flex !important;
                margin: 0 auto !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-section-label {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-divider {
                margin: 8px 12px !important;
            }
            aside#sidebar.sidebar-collapsed .nav-item {
                justify-content: center !important;
                padding: 9px 0 !important;
                margin: 3px 8px !important;
                gap: 0 !important;
            }
            aside#sidebar.sidebar-collapsed .nav-item.active::before {
                left: -8px;
            }
            aside#sidebar.sidebar-collapsed .nav-label,
            aside#sidebar.sidebar-collapsed .sidebar-badge {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-user-card {
                flex-direction: column !important;
                padding: 6px !important;
                margin: 0 8px 8px !important;
                gap: 6px !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-user-link {
                justify-content: center !important;
                flex: initial !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-user-info {
                display: none !important;
            }
            aside#sidebar.sidebar-collapsed .sidebar-logout-icon-btn {
                width: 28px !important;
                height: 28px !important;
                margin: 0 auto !important;
            }
            aside#sidebar.sidebar-collapsed #collapseIcon {
                transform: rotate(180deg);
            }
        }

        @media print {
            header, aside, #sidebar, .sidebar-overlay, #pageLoader, .no-print {
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

            <div class="px-4 py-3.5 flex items-center justify-between relative z-20 sidebar-header-box border-b border-white/[0.08]">
                <div class="flex items-center gap-2.5 overflow-hidden">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white shadow-sm flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                    </div>
                    <div class="sidebar-brand-text-wrap overflow-hidden whitespace-nowrap">
                        <div class="sidebar-brand-text truncate"><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow')); ?></div>
                        <div class="sidebar-brand-sub truncate">Workspace</div>
                    </div>
                </div>
                <button type="button" onclick="toggleDesktopSidebar()" id="sidebarCollapseBtn" aria-label="Minimize sidebar" title="Minimize sidebar" class="hidden md:flex w-7 h-7 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 items-center justify-center transition-colors flex-shrink-0 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <svg id="collapseIcon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="transition-transform duration-300"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
                </button>
            </div>

            <?php
            // Sidebar pending tasks count for badge
            $badge_pending_tasks = 0;
            try {
                $stmtBadge = $pdo->prepare("SELECT COUNT(*) FROM todos WHERE (completed = 0 AND status != 'done')");
                $stmtBadge->execute();
                $badge_pending_tasks = (int)$stmtBadge->fetchColumn();
            } catch(Exception $e) {}
            ?>

            <nav class="flex-1 overflow-y-auto sidebar-scrollbar py-2 relative z-20">

                <!-- Main -->
                <div class="sidebar-section-label">Menu Utama</div>

                <a href="<?php echo route('dashboard'); ?>" class="nav-item <?php echo $page==='dashboard'?'active':''; ?>" data-nav="dashboard" title="Dasbor">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Dasbor</span>
                </a>

                <a href="<?php echo route('calendar'); ?>" class="nav-item <?php echo $page==='calendar'?'active':''; ?>" data-nav="calendar" title="Kalender">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Kalender</span>
                </a>

                <a href="<?php echo route('notes'); ?>" class="nav-item <?php echo $page==='notes'?'active':''; ?>" data-nav="notes" title="Catatan">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Catatan</span>
                </a>

                <a href="<?php echo route('documents'); ?>" class="nav-item <?php echo $page==='documents'?'active':''; ?>" data-nav="documents" title="Dokumen">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Dokumen</span>
                </a>

                <a href="<?php echo route('projects'); ?>" class="nav-item <?php echo $page==='projects'?'active':''; ?>" data-nav="projects" title="Proyek (Kanban)">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Proyek (Kanban)</span>
                    <?php if ($badge_pending_tasks > 0): ?>
                    <span class="sidebar-badge text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 flex-shrink-0" title="<?php echo $badge_pending_tasks; ?> agenda aktif"><?php echo $badge_pending_tasks; ?></span>
                    <?php endif; ?>
                </a>

                <a href="<?php echo route('activities'); ?>" class="nav-item <?php echo $page==='activities'?'active':''; ?>" data-nav="activities" title="Aktivitas">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Aktivitas</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.8)] flex-shrink-0" title="Sistem Aktif"></span>
                </a>

                <div class="sidebar-divider"></div>

                <!-- Account -->
                <div class="sidebar-section-label">Akun Saya</div>

                <a href="<?php echo route('profile'); ?>" class="nav-item <?php echo in_array($page, ['profile','reset-password'])?'active':''; ?>" data-nav="profile" title="Profil & Keamanan">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <span class="nav-label truncate flex-1">Profil &amp; Keamanan</span>
                </a>

                <?php if (isAdmin()): ?>
                <div class="sidebar-divider"></div>
                <div class="sidebar-section-label">Administrasi</div>

                <a href="<?php echo route('admin'); ?>" class="nav-item <?php echo $page==='admin'?'active':''; ?>" data-nav="admin" title="Pengguna">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <span class="nav-label truncate">Pengguna</span>
                </a>

                <a href="<?php echo route('backup'); ?>" class="nav-item <?php echo $page==='backup'?'active':''; ?>" data-nav="backup" title="Cadangan Data">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </span>
                    <span class="nav-label truncate">Cadangan Data</span>
                </a>

                <a href="<?php echo route('cache'); ?>" class="nav-item <?php echo $page==='cache'?'active':''; ?>" data-nav="cache" title="Optimasi Cache">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </span>
                    <span class="nav-label truncate">Optimasi Cache</span>
                </a>

                <a href="<?php echo route('settings'); ?>" class="nav-item <?php echo $page==='settings'?'active':''; ?>" data-nav="settings" title="Pengaturan Sistem">
                    <span class="nav-indicator absolute left-0 top-1.5 bottom-1.5 w-[3.5px] bg-indigo-500 rounded-r-full shadow-[0_0_10px_rgba(99,102,241,0.85)]"></span>
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span class="nav-label truncate">Pengaturan Sistem</span>
                </a>
                <?php endif; ?>

            </nav>

            <!-- User Footer -->
            <?php
            $profilePhoto = $_SESSION['profile_photo'] ?? null;
            $avatarSrc = $profilePhoto && file_exists(__DIR__ . '/uploads/avatars/' . $profilePhoto)
                ? 'uploads/avatars/' . $profilePhoto
                : getAvatarDataUri($display_name, 40);

            // User subtitle differentiation (avoid duplicate Administrator label)
            $role_label = ($role === 'admin') ? 'Super Admin' : 'Anggota Tim';
            $user_subtitle = !empty($email) ? $email : $role_label;
            if (strtolower($display_name) === strtolower($role_label) || strtolower($display_name) === 'administrator') {
                $user_subtitle = !empty($email) ? $email : 'Super Admin';
            }
            ?>
            <div class="relative z-20 p-2.5 border-t border-white/[0.08] flex-shrink-0">
                <div class="sidebar-user-card flex items-center justify-between gap-2.5 relative">
                    <a href="<?php echo route('profile'); ?>" onclick="handleUserAvatarClick(event)" class="sidebar-user-link flex items-center gap-2.5 min-w-0 flex-1 group" title="Buka Profil Saya">
                        <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="w-8 h-8 rounded-lg border border-white/20 object-cover flex-shrink-0 group-hover:border-indigo-400 transition-colors" />
                        <div class="sidebar-user-info flex-1 min-w-0 text-left">
                            <div class="sidebar-user-name truncate text-xs font-bold text-slate-100 group-hover:text-white transition-colors"><?php echo htmlspecialchars($display_name); ?></div>
                            <div class="text-[11px] font-medium text-slate-400 group-hover:text-slate-300 transition-colors truncate"><?php echo htmlspecialchars($user_subtitle); ?></div>
                        </div>
                    </a>
                    <a href="<?php echo route('logout'); ?>" class="sidebar-logout-icon-btn" title="Keluar dari Sistem" aria-label="Keluar">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </a>
                </div>

                <!-- Floating Popover for Collapsed Sidebar or Quick Actions -->
                <div id="userProfilePopover" class="hidden absolute bottom-16 left-3 z-50 w-52 rounded-2xl bg-[#0f172a] border border-white/10 shadow-2xl p-1.5 text-xs backdrop-blur-md">
                    <div class="px-3 py-2 border-b border-white/10 mb-1">
                        <div class="font-bold text-white truncate"><?php echo htmlspecialchars($display_name); ?></div>
                        <div class="text-[11px] text-slate-400 truncate"><?php echo htmlspecialchars($user_subtitle); ?></div>
                    </div>
                    <a href="<?php echo route('profile'); ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 transition-colors">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Profil Saya
                    </a>
                    <a href="<?php echo route('reset-password'); ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-300 hover:text-white hover:bg-white/10 transition-colors">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Ubah Password
                    </a>
                    <div class="my-1 border-t border-white/10"></div>
                    <a href="<?php echo route('logout'); ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition-colors font-medium">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Keluar
                    </a>
                </div>
            </div>
        </aside>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-hidden flex flex-col relative bg-[#f1f5fb] dark:bg-[#090d16]" style="min-width:0">
        <!-- Ambient Glassmorphism Atmospheric Lighting Orbs (Visible through frosted glass) -->
        <div class="fixed top-[-100px] right-[-60px] w-[620px] h-[620px] bg-gradient-to-br from-indigo-500/20 via-purple-400/18 to-sky-300/12 rounded-full blur-[110px] pointer-events-none z-0"></div>
        <div class="fixed bottom-[-80px] left-64 w-[600px] h-[600px] bg-gradient-to-tr from-sky-400/18 via-indigo-300/14 to-emerald-300/12 rounded-full blur-[110px] pointer-events-none z-0"></div>
        <div class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[750px] h-[750px] bg-gradient-to-r from-purple-300/12 via-indigo-200/18 to-transparent rounded-full blur-[130px] pointer-events-none z-0"></div>

        <div id="ajaxContent" class="flex-1 overflow-y-auto custom-scrollbar flex flex-col relative z-10">
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
            <footer class="py-6 px-6 mt-auto text-center border-t border-slate-200/60 bg-transparent flex-shrink-0">
                <p class="text-slate-400 text-xs font-medium">© 2026 TaskFlow. Dibuat oleh Habib Frambudi</p>
            </footer>
        </div>
    </main>

    </div>

    <!-- ============================================================== -->
    <!-- GLOBAL COMMAND PALETTE MODAL (Ctrl + K / Cmd + K) -->
    <!-- ============================================================== -->
    <div id="cmdPaletteModal" class="fixed inset-0 hidden z-50 items-start justify-center pt-20 md:pt-28 p-4 bg-slate-950/60 backdrop-blur-md" role="dialog" aria-modal="true">
        <div class="bg-white/95 backdrop-blur-2xl rounded-3xl max-w-xl w-full shadow-2xl overflow-hidden border border-slate-200/90 flex flex-col transition-all">
            <!-- Search bar -->
            <div class="p-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/60">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="cmdInput" placeholder="Ketik perintah atau cari halaman... (cth: kalender, dokumen, tambah)" 
                       class="flex-1 bg-transparent border-none text-sm font-bold text-slate-900 outline-none placeholder:text-slate-400 placeholder:font-medium"
                       oninput="filterCmdList(this.value)" autocomplete="off">
                <kbd class="px-2 py-0.5 text-[10px] font-black uppercase text-slate-400 bg-white border border-slate-200 rounded-lg shadow-2xs">ESC</kbd>
            </div>

            <!-- Command items list -->
            <div id="cmdItemsList" class="p-2 max-h-80 overflow-y-auto space-y-1 custom-scrollbar text-xs">
                <!-- Group: Navigasi -->
                <div class="cmd-group-label px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Navigasi Halaman</div>
                <a href="index.php?page=dashboard" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">📊</span>
                        Dasbor Utama
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="index.php?page=calendar" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">📅</span>
                        Kalender Produktivitas
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="index.php?page=documents" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">📂</span>
                        Pusat Dokumen &amp; Berkas
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="index.php?page=notes" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">📝</span>
                        Catatan &amp; Ide Kerja
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="index.php?page=projects" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">📌</span>
                        Papan Kanban Proyek
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="index.php?page=activities" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">⚡</span>
                        Audit Log &amp; Aktivitas
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>
                <a href="index.php?page=profile" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">👤</span>
                        Profil &amp; Keamanan Akun
                    </span>
                    <span class="text-[10px] text-slate-400 font-semibold">Buka &rarr;</span>
                </a>

                <!-- Group: Aksi Cepat -->
                <div class="cmd-group-label px-3 pt-3 pb-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Aksi Cepat</div>
                <a href="index.php?page=calendar" onclick="closeCmdPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">➕</span>
                        Tambah Tugas Baru
                    </span>
                    <span class="text-[10px] text-emerald-600 font-bold">Aksi Cepat</span>
                </a>
                <a href="index.php?page=documents#uploadSection" onclick="closeCmdPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-sky-50 text-slate-700 hover:text-sky-700 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center text-xs">⬆️</span>
                        Unggah Dokumen Berkas
                    </span>
                    <span class="text-[10px] text-sky-600 font-bold">Aksi Cepat</span>
                </a>
                <a href="index.php?page=notes" onclick="closeCmdPalette()" class="cmd-item flex items-center justify-between p-2.5 rounded-xl hover:bg-violet-50 text-slate-700 hover:text-violet-700 font-bold transition-colors">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center text-xs">✍️</span>
                        Tulis Draf Catatan Baru
                    </span>
                    <span class="text-[10px] text-violet-600 font-bold">Aksi Cepat</span>
                </a>
            </div>

            <!-- Footer instructions -->
            <div class="px-4 py-2.5 bg-slate-50/80 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-medium">
                <div class="flex items-center gap-2">
                    <span>Gunakan <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[9px] font-bold">↑</kbd> <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[9px] font-bold">↓</kbd> untuk memilih</span>
                    <span>·</span>
                    <span><kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[9px] font-bold">Enter</kbd> untuk buka</span>
                </div>
                <span>Tekan <kbd class="px-1.5 py-0.5 bg-white border border-slate-200 rounded text-[9px] font-bold">ESC</kbd> untuk menutup</span>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- FLOATING TOAST NOTIFICATION CONTAINER -->
    <!-- ============================================================== -->
    <div id="toastContainer" class="fixed bottom-6 right-6 z-50 flex flex-col gap-2.5 pointer-events-none" style="min-width: 320px; max-width: 420px;"></div>

<script>
// AJAX Navigation System
function loadPage(url, pushState = true) {
    const loader = document.getElementById('pageLoader');
    const content = document.getElementById('ajaxContent');
    
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
        const content = document.getElementById('ajaxContent');
        content.innerHTML = html;
        executeScripts(content);
        content.scrollTop = 0;
        if (content) content.classList.remove('page-fade');
        
        // Update URL
        if (pushState) history.pushState({url: url}, '', url);
        
        // Update active menu
        const urlObj = new URL(url, window.location.origin);
        const page = urlObj.searchParams.get('page') || 'dashboard';
        document.querySelectorAll('.nav-item').forEach(el => {
            const dataNav = el.getAttribute('data-nav');
            if (dataNav === 'profile' && (page === 'profile' || page === 'reset-password')) {
                el.classList.add('active');
            } else {
                el.classList.toggle('active', dataNav === page);
            }
        });

        loader.classList.add('finish');
        setTimeout(() => {
            loader.classList.remove('loading', 'finish');
        }, 300);
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

// Collapsed user profile popover handler
function handleUserAvatarClick(event) {
    const sidebar = document.getElementById('sidebar');
    const popover = document.getElementById('userProfilePopover');
    if (sidebar && sidebar.classList.contains('sidebar-collapsed')) {
        event.preventDefault();
        event.stopPropagation();
        if (popover) {
            popover.classList.toggle('hidden');
        }
    }
}

document.addEventListener('click', function(e) {
    const popover = document.getElementById('userProfilePopover');
    if (popover && !popover.classList.contains('hidden')) {
        if (!popover.contains(e.target) && !e.target.closest('.sidebar-user-card')) {
            popover.classList.add('hidden');
        }
    }
});

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

// ==============================================================
// 1. FLOATING TOAST NOTIFICATION SYSTEM
// ==============================================================
window.showToast = function(message, type = 'success', duration = 3800) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'pointer-events-auto transform transition-all duration-300 translate-y-4 opacity-0 flex items-center gap-3 p-4 rounded-2xl shadow-xl border text-xs font-bold';
    
    if (type === 'error' || type === 'danger') {
        toast.className += ' bg-white/95 border-rose-200 text-rose-900 shadow-rose-500/10';
        toast.innerHTML = `
            <div class="w-8 h-8 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 flex-shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div class="flex-1 leading-snug">${message}</div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 p-1">✕</button>
        `;
    } else {
        toast.className += ' bg-white/95 border-emerald-200 text-slate-900 shadow-emerald-500/10';
        toast.innerHTML = `
            <div class="w-8 h-8 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="flex-1 leading-snug">${message}</div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-600 p-1">✕</button>
        `;
    }

    container.appendChild(toast);

    // Slide in
    setTimeout(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    }, 20);

    // Auto dismiss
    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, duration);
};

// Check URL query parameters for feedback messages and show toast
function checkUrlNotifications() {
    const params = new URLSearchParams(window.location.search);
    const success = params.get('success');
    const error = params.get('error');

    if (success) {
        let msg = 'Operasi berhasil dilakukan!';
        if (success === 'uploaded') msg = 'Dokumen berhasil diunggah dengan kompresi optimal!';
        if (success === 'deleted') msg = 'Data berhasil dihapus dari sistem.';
        if (success === 'shared') msg = 'Hak akses dokumen berhasil dibagikan!';
        if (success === 'revoked') msg = 'Hak akses berbagi telah dicabut.';
        if (success === 'public_shared') msg = 'Tautan publik siap dibagikan!';
        if (success === 'public_revoked') msg = 'Tautan publik telah dinonaktifkan.';
        showToast(msg, 'success');

        // Clean up URL without reload
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

// ==============================================================
// 2. COMMAND PALETTE LOGIC (Ctrl + K / Cmd + K)
// ==============================================================
window.openCmdPalette = function() {
    const modal = document.getElementById('cmdPaletteModal');
    const input = document.getElementById('cmdInput');
    if (!modal || !input) return;

    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    input.value = '';
    filterCmdList('');
    setTimeout(() => input.focus(), 50);
};

window.closeCmdPalette = function() {
    const modal = document.getElementById('cmdPaletteModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
};

window.filterCmdList = function(query) {
    const q = (query || '').toLowerCase().trim();
    const items = document.querySelectorAll('#cmdItemsList .cmd-item');
    items.forEach(el => {
        const text = el.textContent.toLowerCase();
        el.style.display = (!q || text.includes(q)) ? 'flex' : 'none';
    });
};

// Global Hotkeys: Ctrl+K / Cmd+K / Escape
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        const modal = document.getElementById('cmdPaletteModal');
        if (modal && !modal.classList.contains('hidden') && modal.style.display === 'flex') {
            closeCmdPalette();
        } else {
            openCmdPalette();
        }
    } else if (e.key === 'Escape') {
        closeCmdPalette();
    }
});

// Close command palette on outer click
document.getElementById('cmdPaletteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCmdPalette();
});


</script>

</body>
</html>
