<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/activities.php - Aktivitas 2.0 (Modern Executive Audit Log & Real-time Timeline with Rich Pagination)
$isAdmin = isAdmin();
$user_id = (int)$_SESSION['user_id'];

// Fetch all activities with user info (safely via LEFT JOIN)
$stmt = $pdo->prepare("
    SELECT a.*, u.display_name, u.profile_photo, u.role as user_role 
    FROM activities a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 300
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
    if (strpos($action, 'dokumen') !== false || strpos($action, 'upload') !== false) {
        return [
            '<svg class="w-5 h-5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
            'bg-indigo-50 border-indigo-200 text-indigo-700'
        ];
    }
    if (strpos($action, 'catatan') !== false || strpos($action, 'note') !== false) {
        return [
            '<svg class="w-5 h-5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
            'bg-indigo-50 border-indigo-200 text-indigo-700'
        ];
    }
    if (strpos($action, 'tugas') !== false || strpos($action, 'todo') !== false || strpos($action, 'status') !== false) {
        return [
            '<svg class="w-5 h-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'bg-emerald-50 border-emerald-200 text-emerald-700'
        ];
    }
    if (strpos($action, 'profil') !== false || strpos($action, 'password') !== false) {
        return [
            '<svg class="w-5 h-5 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            'bg-slate-100 border-slate-200 text-slate-700'
        ];
    }
    if (strpos($action, 'login') !== false || strpos($action, 'logout') !== false) {
        return [
            '<svg class="w-5 h-5 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'bg-slate-100 border-slate-200 text-slate-700'
        ];
    }
    return [
        '<svg class="w-5 h-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        'bg-slate-100 border-slate-200 text-slate-700'
    ];
}

function renderTimeline($activities, $tabId) {
    if (empty($activities)) {
        echo '
        <div class="bg-white/80 backdrop-blur-md rounded-3xl p-14 text-center border border-slate-200/90 shadow-sm">
            <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-indigo-100 shadow-xs">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h3 class="text-base font-black text-slate-900 mb-1">Belum Ada Catatan Aktivitas</h3>
            <p class="text-slate-400 font-medium text-xs max-w-xs mx-auto">Sistem belum mencatat rekaman jejak audit untuk kategori ini.</p>
        </div>';
        return;
    }
    
    echo '<div class="flex flex-col gap-3 activity-timeline-list" id="timeline-' . $tabId . '" data-tab="' . $tabId . '">';
    $currentDate = '';
    
    foreach ($activities as $act) {
        $dateLabel = date('d F Y', strtotime($act['created_at']));
        $timeLabel = date('H:i', strtotime($act['created_at']));
        $displayDate = ($dateLabel !== $currentDate);
        $currentDate = $dateLabel;
        $style = getActionStyle($act['action']);
        
        $initials = strtoupper(substr($act['display_name'] ?? 'US', 0, 2));
        $hasPhoto = !empty($act['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $act['profile_photo']);
        
        // Date divider element (identified by data-date)
        if ($displayDate) {
            echo '
            <div class="date-divider relative flex items-center gap-4 my-2" data-date="' . htmlspecialchars($dateLabel) . '" data-tab="' . $tabId . '">
                <div class="h-px flex-1 bg-slate-200/80"></div>
                <span class="bg-white px-4 py-1 rounded-full text-[10px] font-black text-slate-500 uppercase tracking-wider border border-slate-200/80 shadow-2xs">' . htmlspecialchars($dateLabel) . '</span>
                <div class="h-px flex-1 bg-slate-200/80"></div>
            </div>';
        }
        
        $photoHtml = $hasPhoto 
            ? '<img src="uploads/avatars/' . htmlspecialchars($act['profile_photo']) . '" alt="' . htmlspecialchars($act['display_name']) . '" class="w-full h-full object-cover">'
            : '<span class="text-[9px] font-black text-slate-500">' . htmlspecialchars($initials) . '</span>';
            
        echo '
        <div class="activity-item group relative flex gap-4 items-start" 
             data-tab="' . $tabId . '" 
             data-date="' . htmlspecialchars($dateLabel) . '"
             data-text="' . strtolower(htmlspecialchars($act['action'] . ' ' . ($act['display_name'] ?? '') . ' ' . $dateLabel)) . '">
            <div class="hidden sm:flex flex-col items-end w-14 pt-2.5 flex-shrink-0">
                <span class="text-xs font-black text-slate-900 leading-none">' . htmlspecialchars($timeLabel) . '</span>
                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-wider mt-1">WIB</span>
            </div>
            <div class="flex-1 bg-white/80 backdrop-blur-md rounded-2xl p-4 sm:p-5 border border-white/90 shadow-xs hover:bg-white/95 hover:border-indigo-300 hover:shadow-md transition-all duration-200 flex flex-col sm:flex-row sm:items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center border flex-shrink-0 shadow-xs ' . $style[1] . '">
                    ' . $style[0] . '
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-slate-900 font-bold leading-snug text-sm mb-2">' . htmlspecialchars($act['action']) . '</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-slate-50/80 border border-slate-200/80 rounded-lg">
                            <div class="w-5 h-5 rounded-md overflow-hidden bg-slate-100 border border-slate-200 flex items-center justify-center">
                                ' . $photoHtml . '
                            </div>
                            <span class="text-xs font-bold text-slate-700">' . htmlspecialchars($act['display_name'] ?? 'System') . '</span>
                            <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                            <span class="text-[10px] font-extrabold text-indigo-600 uppercase tracking-wider">' . htmlspecialchars($act['user_role'] ?? 'user') . '</span>
                        </div>
                        <div class="sm:hidden flex items-center gap-1 text-xs font-semibold text-slate-400 bg-slate-50 px-2 py-0.5 rounded-md border border-slate-200/60">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>' . htmlspecialchars($timeLabel) . ' WIB</span>
                        </div>
                        <span class="text-xs text-slate-400 font-medium sm:hidden">• ' . htmlspecialchars($dateLabel) . '</span>
                    </div>
                </div>
                <div class="hidden md:block flex-shrink-0">
                    <span class="px-3 py-1 bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-wider rounded-lg border border-slate-200/60">Tercatat</span>
                </div>
            </div>
        </div>';
    }
    echo '</div>';

    // Empty search state
    echo '
    <div id="empty-search-' . $tabId . '" class="hidden bg-white/80 backdrop-blur-md rounded-3xl p-12 text-center border border-slate-200/90 shadow-sm mt-4">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </div>
        <p class="text-slate-700 font-bold text-sm">Tidak ada aktivitas yang sesuai.</p>
        <p class="text-slate-400 text-xs font-medium mt-1">Coba gunakan kata kunci lain dalam pencarian Anda.</p>
    </div>';

    // Pagination Bar
    echo '
    <div id="pagination-' . $tabId . '" class="activity-pagination-bar mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-2xl bg-white/80 backdrop-blur-md border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3 text-xs font-bold text-slate-500">
            <span id="info-' . $tabId . '" class="pagination-info">Menampilkan...</span>
            <span class="text-slate-300">•</span>
            <label class="flex items-center gap-1.5 text-xs text-slate-500 font-semibold">
                Per hal:
                <select id="size-' . $tabId . '" onchange="changeActPageSize(this.value)" class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 outline-none hover:bg-slate-100 transition-colors cursor-pointer">
                    <option value="10">10</option>
                    <option value="15" selected>15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </label>
        </div>
        <div id="nav-' . $tabId . '" class="flex items-center gap-1.5 pagination-buttons">
            <!-- Buttons dynamically generated -->
        </div>
    </div>';
}
?>

<div class="p-4 md:p-8 w-full space-y-6">
    
    <!-- Executive Header Section (Glassmorphic) -->
    <div class="rounded-3xl p-6 md:p-7 bg-white/76 backdrop-blur-xl border border-white/85 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Audit Log &amp; Timeline Real-time
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Aktivitas Sistem</h1>
            <p class="text-slate-500 text-xs md:text-sm max-w-xl leading-relaxed font-medium">
                Rekaman jejak aktivitas tugas, berkas dokumen, autentikasi, dan interaksi pengguna secara real-time.
            </p>
        </div>
        
        <div class="flex items-center gap-3 flex-shrink-0">
            <div class="bg-slate-50 px-4 py-2.5 rounded-2xl border border-slate-200/90 shadow-xs flex items-center gap-3.5">
                <div class="text-right">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Log</div>
                    <div class="text-xl font-black text-slate-900 leading-tight"><?php echo $total_activities; ?></div>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 border border-indigo-100 shadow-xs">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Controls: Search & Tabs -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
        <!-- Segmented Tabs -->
        <div class="inline-flex p-1 rounded-2xl bg-slate-100 border border-slate-200/90">
            <button onclick="switchActTab('all')" id="tab-btn-all" 
                    class="px-4 py-2 rounded-xl text-xs font-extrabold transition-all bg-white text-slate-900 shadow-xs">
                Semua (<?php echo count($all_activities); ?>)
            </button>
            <button onclick="switchActTab('work')" id="tab-btn-work" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-900">
                Tugas &amp; Dokumen (<?php echo count($work_activities); ?>)
            </button>
            <button onclick="switchActTab('system')" id="tab-btn-system" 
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-900">
                Sistem &amp; Akun (<?php echo count($system_activities); ?>)
            </button>
        </div>

        <!-- Search input -->
        <div class="relative">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="actSearchInput" placeholder="Cari aktivitas atau nama..." oninput="handleActSearch(this.value)"
                   class="pl-9 pr-4 py-2 text-xs font-bold w-full sm:w-64 rounded-xl text-slate-900 bg-white border border-slate-200/90 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-slate-400">
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
(function() {
    let currentTab = 'all';
    let searchQuery = '';
    let pageSize = 15;
    const pageState = {
        all: 1,
        work: 1,
        system: 1
    };

    window.switchActTab = function(tab) {
        currentTab = tab;
        const tabs = ['all', 'work', 'system'];
        tabs.forEach(t => {
            const btn = document.getElementById('tab-btn-' + t);
            const content = document.getElementById('tab-content-' + t);
            if (t === tab) {
                btn.className = "px-4 py-2 rounded-xl text-xs font-extrabold transition-all bg-white text-slate-900 shadow-xs";
                content.classList.remove('hidden');
            } else {
                btn.className = "px-4 py-2 rounded-xl text-xs font-bold transition-all text-slate-500 hover:text-slate-900";
                content.classList.add('hidden');
            }
        });
        paginateTab(currentTab);
    };

    window.handleActSearch = function(query) {
        searchQuery = (query || '').toLowerCase().trim();
        pageState[currentTab] = 1;
        paginateTab(currentTab);
    };

    window.changeActPageSize = function(size) {
        pageSize = parseInt(size, 10) || 15;
        // Keep all dropdowns synced
        ['all', 'work', 'system'].forEach(t => {
            const el = document.getElementById('size-' + t);
            if (el) el.value = pageSize;
        });
        pageState[currentTab] = 1;
        paginateTab(currentTab);
    };

    window.goToActPage = function(tab, page) {
        pageState[tab] = page;
        paginateTab(tab);
        // Scroll to timeline smoothly
        const container = document.getElementById('tab-content-' + tab);
        if (container) {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    function paginateTab(tab) {
        const container = document.getElementById('timeline-' + tab);
        if (!container) return;

        const items = Array.from(container.querySelectorAll('.activity-item[data-tab="' + tab + '"]'));
        const dividers = Array.from(container.querySelectorAll('.date-divider[data-tab="' + tab + '"]'));
        const emptyState = document.getElementById('empty-search-' + tab);
        const paginationBar = document.getElementById('pagination-' + tab);
        const infoEl = document.getElementById('info-' + tab);
        const navEl = document.getElementById('nav-' + tab);

        // 1. Filter items by search query
        const matchedItems = items.filter(item => {
            if (!searchQuery) return true;
            const text = item.dataset.text || '';
            return text.includes(searchQuery);
        });

        const totalItems = matchedItems.length;
        const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
        
        if (pageState[tab] > totalPages) {
            pageState[tab] = totalPages;
        }
        const currentPage = pageState[tab];

        // If no matching items
        if (totalItems === 0) {
            items.forEach(it => it.style.display = 'none');
            dividers.forEach(d => d.style.display = 'none');
            if (emptyState) emptyState.classList.remove('hidden');
            if (paginationBar) paginationBar.classList.add('hidden');
            return;
        }

        if (emptyState) emptyState.classList.add('hidden');
        if (paginationBar) paginationBar.classList.remove('hidden');

        // 2. Hide all items initially
        items.forEach(it => it.style.display = 'none');
        dividers.forEach(d => d.style.display = 'none');

        // 3. Slice visible items for current page
        const startIdx = (currentPage - 1) * pageSize;
        const endIdx = Math.min(startIdx + pageSize, totalItems);
        const pageItems = matchedItems.slice(startIdx, endIdx);

        // 4. Reveal current page items and organize date dividers
        const datesShown = new Set();
        pageItems.forEach(item => {
            item.style.display = 'flex';
            const itemDate = item.dataset.date;
            if (itemDate && !datesShown.has(itemDate)) {
                datesShown.add(itemDate);
                // Find matching divider
                const div = dividers.find(d => d.dataset.date === itemDate);
                if (div) {
                    div.style.display = 'flex';
                }
            }
        });

        // 5. Update info text
        if (infoEl) {
            const startDisplay = totalItems > 0 ? (startIdx + 1) : 0;
            const endDisplay = endIdx;
            infoEl.textContent = `Menampilkan ${startDisplay} – ${endDisplay} dari ${totalItems} aktivitas`;
        }

        // 6. Render pagination controls
        if (navEl) {
            let html = '';

            // Prev Button
            const prevDisabled = currentPage === 1;
            html += `
                <button type="button" onclick="goToActPage('${tab}', ${currentPage - 1})" ${prevDisabled ? 'disabled' : ''}
                        class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold transition-all flex items-center gap-1 ${prevDisabled ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white hover:bg-slate-50 text-slate-700 active:scale-95 shadow-2xs'}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    <span class="hidden sm:inline">Sebelumnya</span>
                </button>
            `;

            // Page numbers
            const maxVisiblePills = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePills / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePills - 1);
            if (endPage - startPage + 1 < maxVisiblePills) {
                startPage = Math.max(1, endPage - maxVisiblePills + 1);
            }

            if (startPage > 1) {
                html += `
                    <button type="button" onclick="goToActPage('${tab}', 1)" class="w-8 h-8 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all shadow-2xs">1</button>
                `;
                if (startPage > 2) {
                    html += `<span class="px-1 text-slate-400 text-xs font-bold">...</span>`;
                }
            }

            for (let p = startPage; p <= endPage; p++) {
                const isActive = (p === currentPage);
                if (isActive) {
                    html += `
                        <button type="button" class="w-8 h-8 rounded-xl bg-indigo-600 text-white text-xs font-extrabold shadow-sm shadow-indigo-600/30">${p}</button>
                    `;
                } else {
                    html += `
                        <button type="button" onclick="goToActPage('${tab}', ${p})" class="w-8 h-8 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all active:scale-95 shadow-2xs">${p}</button>
                    `;
                }
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="px-1 text-slate-400 text-xs font-bold">...</span>`;
                }
                html += `
                    <button type="button" onclick="goToActPage('${tab}', ${totalPages})" class="w-8 h-8 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all shadow-2xs">${totalPages}</button>
                `;
            }

            // Next Button
            const nextDisabled = currentPage === totalPages;
            html += `
                <button type="button" onclick="goToActPage('${tab}', ${currentPage + 1})" ${nextDisabled ? 'disabled' : ''}
                        class="px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold transition-all flex items-center gap-1 ${nextDisabled ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white hover:bg-slate-50 text-slate-700 active:scale-95 shadow-2xs'}">
                    <span class="hidden sm:inline">Selanjutnya</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            `;

            navEl.innerHTML = html;
        }
    }

    // Initialize all tabs pagination on load
    document.addEventListener('DOMContentLoaded', function() {
        ['all', 'work', 'system'].forEach(t => paginateTab(t));
    });
    // Also run immediately in case DOM is already ready (e.g. loaded via SPA/fetch)
    ['all', 'work', 'system'].forEach(t => paginateTab(t));
})();
</script>