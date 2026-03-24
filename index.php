<?php
// index.php
require_once 'config.php';

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow Pro')); ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
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

        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; color: #0f172a; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
        
        .main-content { background: var(--bg-main); min-height: 100vh; }

        /* ===== SIDEBAR ===== */
        .sidebar-bg {
            background: linear-gradient(160deg, var(--sidebar-grad-start) 0%, var(--sidebar-grad-end) 100%);
            position: relative;
            overflow: hidden;
            box-shadow: 4px 0 24px rgba(0,0,0,0.18);
        }
        .sidebar-bg::after {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 250px; height: 250px;
            background: radial-gradient(circle, rgba(99,102,241,0.18) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .sidebar-section-label {
            font-size: 9px; font-weight: 800; letter-spacing: 0.18em;
            text-transform: uppercase; color: rgba(148,163,184,0.7);
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
        .nav-item.active {
            background: rgba(99,102,241,0.25); color: #fff;
            box-shadow: inset 0 0 0 1px rgba(99,102,241,0.4);
        }
        .nav-item.active .nav-icon { color: #818cf8; }

        .nav-icon {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; background: var(--sidebar-icon-bg);
            color: rgba(203,213,225,0.7); transition: all 0.2s ease;
        }
        .nav-item:hover .nav-icon { background: rgba(255,255,255,0.12); color: #fff; }
        .nav-item.active .nav-icon { background: rgba(99,102,241,0.35); color: #a5b4fc; }

        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 10px 16px; }
        .sidebar-brand-text { color: #fff; font-size: 17px; font-weight: 800; }
        .sidebar-brand-sub {
            font-size: 9px; font-weight: 700; letter-spacing: 0.3em;
            text-transform: uppercase;
            background: linear-gradient(90deg, #818cf8, #38bdf8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .sidebar-user-card {
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px; padding: 12px; margin: 0 8px 8px;
        }
        .sidebar-user-name { color: #f1f5f9; font-size: 13px; font-weight: 700; }
        .sidebar-logout-btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: calc(100% - 16px); padding: 10px; background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.2); border-radius: 10px;
            color: #fca5a5; font-size: 12px; font-weight: 700;
            text-decoration: none; margin: 8px 8px 0;
            transition: all 0.2s ease;
        }
        .sidebar-logout-btn:hover { background: rgba(239,68,68,0.2); color: #fff; }
            background: rgba(239,68,68,0.2);
            color: #fecaca;
        }
        /* Mobile Overlaybackdrop */
        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px); z-index: 40;
            transition: all 0.3s ease; opacity: 0; visibility: hidden;
        }
        .sidebar-overlay.active { opacity: 1; visibility: visible; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">

    <!-- Mobile Header -->
    <header class="md:hidden flex items-center justify-between px-5 py-4 bg-[#0f172a] shadow-lg shadow-blue-900/10 sticky top-0 z-30">
        <div class="flex items-center gap-3">
             <div class="w-8 h-8 rounded-xl bg-indigo-500 flex items-center justify-center text-white">
                 <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
             </div>
             <span class="text-white font-black text-sm uppercase tracking-tight"><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow')); ?></span>
        </div>
        <button onclick="toggleSidebar()" class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-white">
             <svg id="iconMenu" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
             <svg id="iconClose" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </header>

    <!-- Overlay Backdrop -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="flex min-h-screen">
        <!-- SIDEBAR -->
        <aside id="sidebar" 
               class="sidebar-bg fixed inset-y-0 left-0 z-50 w-72 transform -translate-x-full transition-transform duration-300 md:static md:translate-x-0 md:inset-auto md:w-64 flex flex-col h-screen overflow-y-auto">

            <!-- Brand -->
            <div class="px-5 pt-6 pb-4 flex items-center gap-3 relative z-20">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 flex items-center justify-center text-white shadow-lg shadow-indigo-900/40 flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"></polyline><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
                </div>
                <div>
                    <div class="sidebar-brand-text"><?php echo htmlspecialchars(getSetting('app_name', 'TaskFlow')); ?></div>
                    <div class="sidebar-brand-sub">Professional Edition</div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto custom-scrollbar pb-2 relative z-20">

                <!-- Main -->
                <div class="sidebar-section-label">Menu Utama</div>

                <a href="<?php echo route('dashboard'); ?>" class="nav-item <?php echo $page==='dashboard'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </span>
                    Dashboard
                </a>

                <a href="<?php echo route('calendar'); ?>" class="nav-item <?php echo $page==='calendar'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </span>
                    Kalender
                </a>

                <a href="<?php echo route('notes'); ?>" class="nav-item <?php echo $page==='notes'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </span>
                    Catatan
                </a>

                <a href="<?php echo route('documents'); ?>" class="nav-item <?php echo $page==='documents'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    Dokumen
                </a>

                <a href="<?php echo route('projects'); ?>" class="nav-item <?php echo $page==='projects'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
                    </span>
                    Proyek (Kanban)
                </a>

                <a href="<?php echo route('activities'); ?>" class="nav-item <?php echo $page==='activities'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </span>
                    Aktivitas
                </a>

                <a href="<?php echo route('chat'); ?>" class="nav-item <?php echo $page==='chat'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </span>
                    <span class="flex-1">Pesan</span>
                    <?php 
                        $unreadMsg = countUnreadMessages($user_id);
                        $badgeStyle = $unreadMsg > 0 ? '' : 'display:none';
                    ?>
                    <span id="unreadChatBadge" style="<?php echo $badgeStyle; ?>" 
                          class="w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-[10px] font-black border-2 border-[#1e293b]">
                        <?php echo $unreadMsg; ?>
                    </span>
                </a>

                <div class="sidebar-divider"></div>

                <!-- Account -->
                <div class="sidebar-section-label">Akun Saya</div>

                <a href="<?php echo route('profile'); ?>" class="nav-item <?php echo $page==='profile'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    Profil
                </a>

                <a href="<?php echo route('reset-password'); ?>" class="nav-item <?php echo $page==='reset-password'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    Password
                </a>

                <?php if (isAdmin()): ?>
                <div class="sidebar-divider"></div>
                <div class="sidebar-section-label">Administrasi</div>

                <a href="<?php echo route('admin'); ?>" class="nav-item <?php echo $page==='admin'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    Users
                </a>

                <a href="<?php echo route('backup'); ?>" class="nav-item <?php echo $page==='backup'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </span>
                    Backup
                </a>

                <a href="<?php echo route('cache'); ?>" class="nav-item <?php echo $page==='cache'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </span>
                    Cache
                </a>

                <a href="<?php echo route('settings'); ?>" class="nav-item <?php echo $page==='settings'?'active':''; ?>">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    Pengaturan
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
            <div class="relative z-20 pt-2 border-t border-white/10">
                <div class="sidebar-user-card flex items-center gap-3">
                    <img src="<?php echo $avatarSrc; ?>" alt="Avatar" class="w-9 h-9 rounded-xl border border-white/20 object-cover flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="sidebar-user-name truncate"><?php echo htmlspecialchars($display_name); ?></div>
                        <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-0.5"><?php echo $role; ?></div>
                    </div>
                </div>


                <a href="<?php echo route('logout'); ?>" class="sidebar-logout-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </a>
                <div class="pb-4"></div>
            </div>
        </aside>

    <!-- Main Content -->
    <main class="flex-1 overflow-auto bg-stone-50" style="min-width:0">
        <?php
        // Simple Page Router
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
        ?>
    </main>

    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-stone-200 py-4 px-6">
        <div class="text-center">
            <p class="text-stone-400 text-sm font-medium">© 2026 TaskFlow. Dibuat oleh Habib Frambudi</p>
        </div>
    </footer>

<script>
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
// Auto-close when clicking a nav link on mobile
document.querySelectorAll('#sidebar .nav-item').forEach(function(el) {
    el.addEventListener('click', function() {
        if (window.innerWidth < 768) closeSidebar();
    });
});
<!-- Notification Audio -->
<audio id="notifSound" preload="auto">
    <source src="data:audio/mp3;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZTU4LjI5LjEwMAAAAAAAAAAAAAAA//MUxAAAAANIAAAAAExBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//MUxAsAAANIAAAAAE1hZ2ljIFNvdW5kIC0gRGluZyAtIDEyOCBrYnBzAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA//MUxBsAAANIAAAAAExBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV//MUxBsAAANIAAAAAExBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV" type="audio/mp3">
</audio>

<script>
let lastUnreadCount = <?php echo countUnreadMessages($user_id); ?>;

function updateChatBadge() {
    fetch('api_chat.php?action=count_unread')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('unreadChatBadge');
            if (badge) {
                // Play sound and show Desktop notification if new message arrives
                if (data.unread_count > lastUnreadCount) {
                    // Sound
                    const sound = document.getElementById('notifSound');
                    if (sound) sound.play().catch(e => console.log('Audio play blocked:', e));

                    // Desktop Notification
                    if (Notification.permission === "granted") {
                        new Notification("TaskFlow Pro", {
                            body: "Pesan baru masuk! Klik untuk baca.",
                            icon: "favicon.ico"
                        }).onclick = function() {
                            window.focus();
                            location.href = 'index.php?page=chat';
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

// Request Desktop Notification Permission
if (Notification.permission !== "granted" && Notification.permission !== "denied") {
    Notification.requestPermission();
}
setInterval(updateChatBadge, 10000); 
updateChatBadge(); 
</script>
</body>
</html>
