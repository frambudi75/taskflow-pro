<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/notes.php - Catatan & Ide 2.0 (Executive Personal Productivity Workspace)

// Schema safeguard: Ensure columns exist
try {
    $pdo->exec("ALTER TABLE notes ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 AFTER content");
} catch (\Exception $e) {}
try {
    $pdo->exec("ALTER TABLE notes ADD COLUMN category VARCHAR(50) DEFAULT 'Ide' AFTER is_pinned");
} catch (\Exception $e) {}
try {
    $pdo->exec("ALTER TABLE notes ADD COLUMN color VARCHAR(20) DEFAULT 'violet' AFTER category");
} catch (\Exception $e) {}

// Handle Toggle Pin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin'])) {
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $note_id = (int)$_POST['note_id'];
        $stmt = $pdo->prepare("UPDATE notes SET is_pinned = IF(is_pinned = 1, 0, 1) WHERE id = ? AND user_id = ?");
        $stmt->execute([$note_id, $_SESSION['user_id']]);
        if (isAjax()) {
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: index.php?page=notes");
        exit;
    }
}

// Handle Add Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $title     = trim($_POST['title']);
        $content   = trim($_POST['content']);
        $category  = trim($_POST['category'] ?? 'Ide');
        $color     = trim($_POST['color'] ?? 'violet');
        $is_pinned = !empty($_POST['is_pinned']) ? 1 : 0;
        if ($title) {
            $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, category, color, is_pinned) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $content, $category, $color, $is_pinned]);
            $note_id = $pdo->lastInsertId();
            logActivity("Membuat catatan baru: " . $title, "note", $note_id);
        }
        if (!isAjax()) {
            header("Location: index.php?page=notes");
            exit;
        }
    }
}

// Handle Edit Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $note_id   = (int)$_POST['note_id'];
        $title     = trim($_POST['title']);
        $content   = trim($_POST['content']);
        $category  = trim($_POST['category'] ?? 'Ide');
        $color     = trim($_POST['color'] ?? 'violet');
        $is_pinned = !empty($_POST['is_pinned']) ? 1 : 0;
        if ($title) {
            $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, category = ?, color = ?, is_pinned = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $content, $category, $color, $is_pinned, $note_id, $_SESSION['user_id']]);
            logActivity("Memperbarui catatan: " . $title, "note", $note_id);
        }
        if (!isAjax()) {
            header("Location: index.php?page=notes");
            exit;
        }
    }
}

// Handle Delete Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $note_id = (int)$_POST['note_id'];
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$note_id, $_SESSION['user_id']]);
        logActivity("Menghapus sebuah catatan", "note", $note_id);
        if (!isAjax()) {
            header("Location: index.php?page=notes");
            exit;
        }
    }
}

// Fetch notes: Pinned first, then newest updated
$stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY is_pinned DESC, updated_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notes = $stmt->fetchAll();

// Statistics calculation
$total_notes  = count($notes);
$pinned_notes = count(array_filter($notes, fn($n) => !empty($n['is_pinned'])));
$categories_list = array_values(array_unique(array_filter(array_map(fn($n) => $n['category'] ?: 'Ide', $notes))));
$total_cats   = count($categories_list) ?: 1;

// Helper relative time
if (!function_exists('notesTimeAgo')) {
    function notesTimeAgo($datetime) {
        if (!$datetime) return '-';
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return 'Baru saja';
        if ($diff < 3600) return round($diff / 60) . ' menit lalu';
        if ($diff < 86400) return round($diff / 3600) . ' jam lalu';
        if ($diff < 172800) return 'Kemarin';
        return date('d M Y', $time);
    }
}

// Helper category badge styles
if (!function_exists('getNoteCategoryBadge')) {
    function getNoteCategoryBadge($category) {
        $cat = strtolower(trim($category ?? 'ide'));
        switch($cat) {
            case 'rapat':
            case 'notula':
                return ['#Notula', 'bg-sky-50 text-sky-700 border-sky-200/80 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800/60'];
            case 'proyek':
                return ['#Proyek', 'bg-indigo-50 text-indigo-700 border-indigo-200/80 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800/60'];
            case 'draf':
                return ['#Draf', 'bg-amber-50 text-amber-700 border-amber-200/80 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/60'];
            case 'personal':
                return ['#Personal', 'bg-emerald-50 text-emerald-700 border-emerald-200/80 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60'];
            default:
                return ['#Ide', 'bg-violet-50 text-violet-700 border-violet-200/80 dark:bg-violet-950/60 dark:text-violet-300 dark:border-violet-800/60'];
        }
    }
}

// Helper border and accent color class
if (!function_exists('getNoteAccentClass')) {
    function getNoteAccentClass($color) {
        $c = strtolower(trim($color ?? 'violet'));
        $valid = ['violet', 'indigo', 'sky', 'emerald', 'amber', 'rose'];
        return in_array($c, $valid) ? 'note-accent-' . $c : 'note-accent-violet';
    }
}
if (!function_exists('getNoteBorderColor')) {
    function getNoteBorderColor($color) {
        return getNoteAccentClass($color);
    }
}
?>

<style>
/* Catatan 2.0 Frosted Glass Craft & Micro-interactions */
.note-glass-card {
    background: #FFFFFF;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.02);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}
.dark .note-glass-card {
    background: rgba(15, 23, 42, 0.85);
    border-color: rgba(30, 41, 59, 0.85);
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.35);
}

/* Note Accent Color System (Enforced with high specificity and soft gradient tint) */
.note-accent-violet {
    border-top: 4px solid #8B5CF6 !important;
    background: linear-gradient(180deg, rgba(139, 92, 246, 0.09) 0%, #FFFFFF 36%) !important;
}
.note-accent-violet:hover {
    border-color: rgba(139, 92, 246, 0.45);
    border-top-color: #8B5CF6 !important;
    box-shadow: 0 14px 30px -4px rgba(139, 92, 246, 0.22);
}
.note-accent-violet .note-accent-dot { background-color: #8B5CF6 !important; }
.dark .note-accent-violet {
    background: linear-gradient(180deg, rgba(139, 92, 246, 0.14) 0%, rgba(15, 23, 42, 0.92) 40%) !important;
}

.note-accent-indigo {
    border-top: 4px solid #6366F1 !important;
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.09) 0%, #FFFFFF 36%) !important;
}
.note-accent-indigo:hover {
    border-color: rgba(99, 102, 241, 0.45);
    border-top-color: #6366F1 !important;
    box-shadow: 0 14px 30px -4px rgba(99, 102, 241, 0.22);
}
.note-accent-indigo .note-accent-dot { background-color: #6366F1 !important; }
.dark .note-accent-indigo {
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.14) 0%, rgba(15, 23, 42, 0.92) 40%) !important;
}

.note-accent-sky {
    border-top: 4px solid #0EA5E9 !important;
    background: linear-gradient(180deg, rgba(14, 165, 233, 0.09) 0%, #FFFFFF 36%) !important;
}
.note-accent-sky:hover {
    border-color: rgba(14, 165, 233, 0.45);
    border-top-color: #0EA5E9 !important;
    box-shadow: 0 14px 30px -4px rgba(14, 165, 233, 0.22);
}
.note-accent-sky .note-accent-dot { background-color: #0EA5E9 !important; }
.dark .note-accent-sky {
    background: linear-gradient(180deg, rgba(14, 165, 233, 0.14) 0%, rgba(15, 23, 42, 0.92) 40%) !important;
}

.note-accent-emerald {
    border-top: 4px solid #10B981 !important;
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.09) 0%, #FFFFFF 36%) !important;
}
.note-accent-emerald:hover {
    border-color: rgba(16, 185, 129, 0.45);
    border-top-color: #10B981 !important;
    box-shadow: 0 14px 30px -4px rgba(16, 185, 129, 0.22);
}
.note-accent-emerald .note-accent-dot { background-color: #10B981 !important; }
.dark .note-accent-emerald {
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.14) 0%, rgba(15, 23, 42, 0.92) 40%) !important;
}

.note-accent-amber {
    border-top: 4px solid #F59E0B !important;
    background: linear-gradient(180deg, rgba(245, 158, 11, 0.10) 0%, #FFFFFF 36%) !important;
}
.note-accent-amber:hover {
    border-color: rgba(245, 158, 11, 0.45);
    border-top-color: #F59E0B !important;
    box-shadow: 0 14px 30px -4px rgba(245, 158, 11, 0.22);
}
.note-accent-amber .note-accent-dot { background-color: #F59E0B !important; }
.dark .note-accent-amber {
    background: linear-gradient(180deg, rgba(245, 158, 11, 0.14) 0%, rgba(15, 23, 42, 0.92) 40%) !important;
}

.note-accent-rose {
    border-top: 4px solid #F43F5E !important;
    background: linear-gradient(180deg, rgba(244, 63, 94, 0.09) 0%, #FFFFFF 36%) !important;
}
.note-accent-rose:hover {
    border-color: rgba(244, 63, 94, 0.45);
    border-top-color: #F43F5E !important;
    box-shadow: 0 14px 30px -4px rgba(244, 63, 94, 0.22);
}
.note-accent-rose .note-accent-dot { background-color: #F43F5E !important; }
.dark .note-accent-rose {
    background: linear-gradient(180deg, rgba(244, 63, 94, 0.14) 0%, rgba(15, 23, 42, 0.92) 40%) !important;
}

/* Pinned Note Styling - Adds golden halo ring while preserving chosen top accent color */
.note-glass-card.is-pinned-card {
    box-shadow: 0 0 0 1.5px rgba(245, 158, 11, 0.5), 0 6px 18px -2px rgba(245, 158, 11, 0.16) !important;
}
.dark .note-glass-card.is-pinned-card {
    box-shadow: 0 0 0 1.5px rgba(245, 158, 11, 0.4), 0 6px 20px -2px rgba(0, 0, 0, 0.5) !important;
}

.notes-glass-panel {
    background: #FFFFFF;
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.04), 0 1px 2px -1px rgba(15, 23, 42, 0.02);
    border-radius: 24px;
}
.dark .notes-glass-panel {
    background: rgba(15, 23, 42, 0.85);
    border-color: rgba(30, 41, 59, 0.85);
}

.note-body-preview {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    min-height: 48px;
}

.note-card-actions {
    opacity: 0.85;
    transition: all 0.18s ease;
}
.note-glass-card:hover .note-card-actions {
    opacity: 1;
}

.modal-backdrop-blur {
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
</style>

<div class="p-4 md:p-8 w-full max-w-[1600px] mx-auto space-y-6">

    <!-- Executive Header Banner (Frosted Glass with Comprehensive Layout) -->
    <div class="rounded-3xl p-6 md:p-7 notes-glass-panel relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="max-w-2xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-50 dark:bg-violet-950/60 border border-violet-100 dark:border-violet-900/50 text-violet-700 dark:text-violet-300 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <span class="w-2 h-2 rounded-full bg-violet-500 animate-pulse"></span>
                Dokumentasi &amp; Ruang Ide
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mb-1.5">Catatan &amp; Ide Kerja</h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium leading-relaxed">
                Kelola seluruh draf ide, notula rapat koordinasi, dan dokumentasi kerja harian Anda dalam satu kanvas produktivitas yang terorganisir dan mudah ditelusuri.
            </p>
        </div>

        <!-- Metric Badges & Action -->
        <div class="flex flex-wrap items-center gap-3.5 flex-shrink-0">
            <!-- 3 High-Value Standard Metrics -->
            <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 px-4 py-2.5 rounded-2xl flex items-center gap-3.5">
                <div>
                    <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Catatan</div>
                    <div class="text-xl font-black text-slate-900 dark:text-white leading-none mt-1"><?php echo $total_notes; ?></div>
                </div>
                <div class="w-px h-7 bg-slate-200 dark:bg-slate-700"></div>
                <div>
                    <div class="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400 tracking-wider flex items-center gap-1">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/></svg>
                        Disematkan
                    </div>
                    <div class="text-xl font-black text-amber-600 dark:text-amber-400 leading-none mt-1"><?php echo $pinned_notes; ?></div>
                </div>
                <div class="w-px h-7 bg-slate-200 dark:bg-slate-700"></div>
                <div>
                    <div class="text-[10px] uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">Topik/Kategori</div>
                    <div class="text-xl font-black text-indigo-600 dark:text-indigo-400 leading-none mt-1"><?php echo $total_cats; ?></div>
                </div>
            </div>

            <!-- Primary CTA -->
            <button onclick="openAddModal()"
                    class="min-h-[46px] flex items-center gap-2 px-5 py-2.5 rounded-2xl font-bold text-xs text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/30 transition-all active:scale-95 flex-shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>+ Catatan Baru</span>
            </button>
        </div>
    </div>

    <!-- Controls Bar: Search, Category Filter Chips, Sort Dropdown & View Mode Switcher -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5 pb-1">
        
        <!-- Left: Search Bar with Controlled Width -->
        <div class="relative w-full max-w-sm flex-shrink-0">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="text" id="searchNotes" placeholder="Cari catatan berdasarkan judul atau isi..." oninput="handleFilterChange()"
                   class="w-full pl-9 pr-9 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
            <button type="button" id="clearSearchBtn" onclick="clearSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold">✕</button>
        </div>

        <!-- Center: Category / Tag Filter Chips -->
        <div class="flex items-center gap-1.5 overflow-x-auto py-1 no-scrollbar text-xs">
            <button type="button" onclick="setCategoryFilter('all')" data-cat="all" class="cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-indigo-600 text-white shadow-xs">
                Semua
            </button>
            <button type="button" onclick="setCategoryFilter('Ide')" data-cat="Ide" class="cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200">
                #Ide
            </button>
            <button type="button" onclick="setCategoryFilter('Rapat')" data-cat="Rapat" class="cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200">
                #Notula
            </button>
            <button type="button" onclick="setCategoryFilter('Proyek')" data-cat="Proyek" class="cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200">
                #Proyek
            </button>
            <button type="button" onclick="setCategoryFilter('Draf')" data-cat="Draf" class="cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200">
                #Draf
            </button>
            <button type="button" onclick="setCategoryFilter('Personal')" data-cat="Personal" class="cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200">
                #Personal
            </button>
        </div>

        <!-- Right: Sort Dropdown & View Mode Switcher -->
        <div class="flex items-center gap-2.5 self-end lg:self-auto flex-shrink-0">
            <!-- Sort Dropdown -->
            <div class="flex items-center gap-1.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-2.5 py-1.5 rounded-xl">
                <span class="text-[11px] font-bold text-slate-400">Urutkan:</span>
                <select id="sortNotes" onchange="handleSortChange(this.value)" class="text-xs font-bold text-slate-700 dark:text-slate-200 bg-transparent focus:outline-none cursor-pointer">
                    <option value="pinned_first">📌 Disematkan &amp; Terbaru</option>
                    <option value="newest">🕒 Terbaru Diperbarui</option>
                    <option value="oldest">⏳ Terlama</option>
                    <option value="alpha">🔤 Judul A–Z</option>
                </select>
            </div>

            <!-- View Mode Switcher -->
            <div class="flex items-center p-1 rounded-xl bg-slate-200/60 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700">
                <button type="button" onclick="setNotesView('grid')" id="viewBtnGrid" title="Tampilan Grid"
                        class="p-1.5 rounded-lg text-slate-700 dark:text-white bg-white dark:bg-slate-700 shadow-sm transition-all">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                </button>
                <button type="button" onclick="setNotesView('list')" id="viewBtnList" title="Tampilan List"
                        class="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Notes Content Area -->
    <?php if (empty($notes)): ?>
        <div class="flex flex-col items-center justify-center py-20 text-center rounded-3xl bg-white dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800 shadow-sm">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-center mb-4 text-indigo-500">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white mb-1.5">Belum Ada Catatan Tersimpan</h3>
            <p class="text-slate-500 dark:text-slate-400 text-xs mb-5 max-w-sm font-medium leading-relaxed">
                Ruang dokumentasi Anda masih kosong. Buat draf ide baru atau notula kerja untuk memulai.
            </p>
            <button onclick="openAddModal()"
                    class="px-5 py-2.5 rounded-xl font-bold text-white text-xs bg-indigo-600 hover:bg-indigo-700 transition-all shadow-md shadow-indigo-600/30 active:scale-95">
                + Buat Catatan Pertama
            </button>
        </div>
    <?php else: ?>
        <!-- Grid View (Clean 3-Column SaaS Cards with Whole-Card Click Surface) -->
        <div id="notesContainer" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach($notes as $note):
                $preview = strip_tags($note['content']);
                $catInfo = getNoteCategoryBadge($note['category'] ?? 'Ide');
                $accentClass = getNoteAccentClass($note['color'] ?? 'violet');
                $isPinned = !empty($note['is_pinned']);
                $updatedTimestamp = strtotime($note['updated_at']);
            ?>
                <div class="note-glass-card p-5 <?php echo $accentClass; ?> <?php echo $isPinned ? 'is-pinned-card' : ''; ?> group flex flex-col justify-between"
                     data-id="<?php echo $note['id']; ?>"
                     data-title="<?php echo strtolower(htmlspecialchars($note['title'])); ?>"
                     data-content="<?php echo strtolower(htmlspecialchars($note['content'])); ?>"
                     data-category="<?php echo htmlspecialchars($note['category'] ?: 'Ide'); ?>"
                     data-pinned="<?php echo $isPinned ? '1' : '0'; ?>"
                     data-updated="<?php echo $updatedTimestamp; ?>"
                     onclick="openEditModal(<?php echo $note['id']; ?>, <?php echo htmlspecialchars(json_encode($note['title'])); ?>, <?php echo htmlspecialchars(json_encode($note['content'])); ?>, <?php echo htmlspecialchars(json_encode($note['category'] ?: 'Ide')); ?>, <?php echo htmlspecialchars(json_encode($note['color'] ?: 'violet')); ?>, <?php echo $isPinned ? '1' : '0'; ?>)">
                    
                    <!-- Card Top Anatomy: [● Date] [#Tag] ... [Pin Icon] [··· Dropdown] -->
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <!-- Left: Date pill & Tag badge -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800/80 px-2 py-0.5 rounded-md">
                                    <span class="note-accent-dot w-2 h-2 rounded-full inline-block flex-shrink-0"></span>
                                    <?php echo date('d M Y', $updatedTimestamp); ?>
                                </span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border <?php echo $catInfo[1]; ?>">
                                    <?php echo $catInfo[0]; ?>
                                </span>
                            </div>
                            
                            <!-- Right: Pin Icon + Menu Titik Tiga (···) -->
                            <div class="note-card-actions flex items-center gap-1" onclick="event.stopPropagation()">
                                <!-- Pin Toggle Button -->
                                <button type="button" onclick="togglePinNote(<?php echo $note['id']; ?>, this)" 
                                        title="<?php echo $isPinned ? 'Lepas Sematan' : 'Sematkan Catatan'; ?>"
                                        class="w-7 h-7 rounded-lg flex items-center justify-center transition-all <?php echo $isPinned ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-300 dark:border-amber-700' : 'text-slate-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/40'; ?>">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="<?php echo $isPinned ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2.2"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5v6l1 1 1-1v-6h5v-2l-2-2z"/></svg>
                                </button>

                                <!-- Dropdown Quick Action / Titik Tiga -->
                                <div class="relative inline-block text-left">
                                    <button type="button" onclick="toggleNoteMenu(<?php echo $note['id']; ?>, event)" title="Menu Opsi"
                                            class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/><circle cx="5" cy="12" r="1.5"/></svg>
                                    </button>
                                    <div id="noteMenu-<?php echo $note['id']; ?>" class="hidden note-menu-dropdown absolute right-0 top-8 z-30 w-36 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl p-1 text-xs">
                                        <button type="button" onclick="copyNoteContent(<?php echo htmlspecialchars(json_encode($note['content'])); ?>, this); hideAllNoteMenus();" class="w-full text-left px-2.5 py-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center gap-2 font-semibold">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            Salin Isi
                                        </button>
                                        <button type="button" onclick="openEditModal(<?php echo $note['id']; ?>, <?php echo htmlspecialchars(json_encode($note['title'])); ?>, <?php echo htmlspecialchars(json_encode($note['content'])); ?>, <?php echo htmlspecialchars(json_encode($note['category'] ?: 'Ide')); ?>, <?php echo htmlspecialchars(json_encode($note['color'] ?: 'violet')); ?>, <?php echo $isPinned ? '1' : '0'; ?>); hideAllNoteMenus();" class="w-full text-left px-2.5 py-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 flex items-center gap-2 font-semibold">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            Edit Catatan
                                        </button>
                                        <div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
                                        <button type="button" onclick="triggerDeleteNote(<?php echo $note['id']; ?>); hideAllNoteMenus();" class="w-full text-left px-2.5 py-1.5 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2 font-semibold">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="12" x2="18" y2="12"/></svg>
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Note Title -->
                        <h3 class="text-[15px] font-black text-slate-900 dark:text-white mb-2 leading-snug group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                            <?php echo htmlspecialchars($note['title']); ?>
                        </h3>

                        <!-- Note Body Excerpt (Bounded to 2-3 lines with neat gray font) -->
                        <p class="note-body-preview text-xs md:text-[13px] text-slate-600 dark:text-slate-400 leading-relaxed font-normal mb-3">
                            <?php echo !empty($preview) ? nl2br(htmlspecialchars($preview)) : '<span class="italic text-slate-400">(Catatan ini tidak memiliki isi teks tambahan)</span>'; ?>
                        </p>
                    </div>

                    <!-- Card Bottom Anatomy: [Diedit X waktu lalu] ---------------- [Buka Catatan ->] -->
                    <div class="mt-2 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-[11px] font-semibold text-slate-400">
                        <span class="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Diedit <?php echo notesTimeAgo($note['updated_at']); ?>
                        </span>

                        <span class="group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors flex items-center gap-1 font-bold">
                            <span>Buka Catatan</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="group-hover:translate-x-1 transition-transform"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah Catatan -->
<div id="addModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop-blur bg-slate-900/60">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl w-full max-w-xl p-6 sm:p-7" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-center">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white">Tulis Catatan Baru</h3>
                    <p class="text-slate-400 text-xs font-medium">Dokumentasikan ide, rapat, atau agenda kerja</p>
                </div>
            </div>
            <button onclick="closeAddModal()" aria-label="Tutup modal" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-all">
                ✕
            </button>
        </div>

        <form method="POST" id="addNoteForm" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="add_note" value="1">
            
            <div>
                <label for="newNoteTitle" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Judul Catatan</label>
                <input type="text" name="title" id="newNoteTitle" required placeholder="Tulis judul yang jelas..."
                       class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="newNoteCategory" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Kategori / Topik</label>
                    <select name="category" id="newNoteCategory"
                            class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                        <option value="Ide">💡 #Ide</option>
                        <option value="Rapat">📋 #Notula</option>
                        <option value="Proyek">🚀 #Proyek</option>
                        <option value="Draf">📝 #Draf</option>
                        <option value="Personal">👤 #Personal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Warna Aksen</label>
                    <input type="hidden" name="color" id="newNoteColor" value="violet">
                    <div class="flex items-center gap-2 pt-1" id="newColorPicker">
                        <button type="button" onclick="selectNewColor('violet')" data-color="violet" title="Ungu (Violet)" class="new-swatch-btn w-7 h-7 rounded-full bg-[#8B5CF6] transition-transform flex items-center justify-center text-white ring-2 ring-offset-2 ring-[#8B5CF6] scale-110">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </button>
                        <button type="button" onclick="selectNewColor('indigo')" data-color="indigo" title="Biru Indigo" class="new-swatch-btn w-7 h-7 rounded-full bg-[#6366F1] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectNewColor('sky')" data-color="sky" title="Biru Langit" class="new-swatch-btn w-7 h-7 rounded-full bg-[#0EA5E9] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectNewColor('emerald')" data-color="emerald" title="Hijau Emerald" class="new-swatch-btn w-7 h-7 rounded-full bg-[#10B981] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectNewColor('amber')" data-color="amber" title="Kuning Amber" class="new-swatch-btn w-7 h-7 rounded-full bg-[#F59E0B] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectNewColor('rose')" data-color="rose" title="Merah Rose" class="new-swatch-btn w-7 h-7 rounded-full bg-[#F43F5E] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                    </div>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="newNoteContent" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider">Isi Dokumentasi</label>
                    <span id="addWordCount" class="text-[10px] text-slate-400 font-semibold">0 kata</span>
                </div>
                <textarea name="content" id="newNoteContent" placeholder="Ketik isi catatan atau ringkasan ide di sini... (Tekan Ctrl + Enter untuk menyimpan)" rows="7"
                          oninput="updateCount(this.value, 'addWordCount')"
                          class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-xs font-medium leading-relaxed text-slate-800 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all resize-none"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_pinned" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Sematkan catatan ini di posisi atas</span>
                </label>
            </div>

            <div class="flex items-center justify-between pt-2">
                <span class="text-[11px] text-slate-400 font-medium hidden sm:inline">Tip: <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 border text-[10px] font-mono">Ctrl + Enter</kbd> untuk simpan</span>
                <div class="flex gap-2.5 ml-auto">
                    <button type="button" onclick="closeAddModal()"
                            class="py-2 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all text-xs">
                        Batal
                    </button>
                    <button type="submit"
                            class="py-2 px-5 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-all text-xs shadow-md shadow-indigo-600/30 active:scale-95">
                        Simpan Catatan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Catatan -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop-blur bg-slate-900/60">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl w-full max-w-xl p-6 sm:p-7" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 border border-violet-100 dark:border-violet-900/50 flex items-center justify-center">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white">Edit Catatan</h3>
                    <p class="text-slate-400 text-xs font-medium">Perbarui isi draf dokumentasi</p>
                </div>
            </div>
            <button onclick="closeEditModal()" aria-label="Tutup modal" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-all">
                ✕
            </button>
        </div>

        <form method="POST" id="editNoteForm" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="edit_note" value="1">
            <input type="hidden" name="note_id" id="editNoteId">
            
            <div>
                <label for="editNoteTitle" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Judul Catatan</label>
                <input type="text" name="title" id="editNoteTitle" required
                       class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="editNoteCategory" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Kategori / Topik</label>
                    <select name="category" id="editNoteCategory"
                            class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                        <option value="Ide">💡 #Ide</option>
                        <option value="Rapat">📋 #Notula</option>
                        <option value="Proyek">🚀 #Proyek</option>
                        <option value="Draf">📝 #Draf</option>
                        <option value="Personal">👤 #Personal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Warna Aksen</label>
                    <input type="hidden" name="color" id="editNoteColor" value="violet">
                    <div class="flex items-center gap-2 pt-1" id="editColorPicker">
                        <button type="button" onclick="selectEditColor('violet')" data-color="violet" title="Ungu (Violet)" class="edit-swatch-btn w-7 h-7 rounded-full bg-[#8B5CF6] transition-transform flex items-center justify-center text-white ring-2 ring-offset-2 ring-[#8B5CF6] scale-110">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </button>
                        <button type="button" onclick="selectEditColor('indigo')" data-color="indigo" title="Biru Indigo" class="edit-swatch-btn w-7 h-7 rounded-full bg-[#6366F1] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectEditColor('sky')" data-color="sky" title="Biru Langit" class="edit-swatch-btn w-7 h-7 rounded-full bg-[#0EA5E9] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectEditColor('emerald')" data-color="emerald" title="Hijau Emerald" class="edit-swatch-btn w-7 h-7 rounded-full bg-[#10B981] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectEditColor('amber')" data-color="amber" title="Kuning Amber" class="edit-swatch-btn w-7 h-7 rounded-full bg-[#F59E0B] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                        <button type="button" onclick="selectEditColor('rose')" data-color="rose" title="Merah Rose" class="edit-swatch-btn w-7 h-7 rounded-full bg-[#F43F5E] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100"></button>
                    </div>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="editNoteContent" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider">Isi Dokumentasi</label>
                    <span id="editWordCount" class="text-[10px] text-slate-400 font-semibold">0 kata</span>
                </div>
                <textarea name="content" id="editNoteContent" rows="7"
                          oninput="updateCount(this.value, 'editWordCount')"
                          class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-xs font-medium leading-relaxed text-slate-800 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all resize-none"></textarea>
            </div>

            <div class="flex items-center gap-2">
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_pinned" id="editNotePinned" value="1" class="rounded text-indigo-600 focus:ring-indigo-500">
                    <span>Sematkan catatan ini di posisi teratas</span>
                </label>
            </div>

            <div class="flex items-center justify-between pt-2">
                <button type="submit" form="deleteNoteForm" aria-label="Hapus Catatan"
                        class="px-3.5 py-2 rounded-xl border border-rose-200 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 font-bold hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs flex items-center gap-1.5 transition-all">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                    Hapus
                </button>

                <div class="flex gap-2.5">
                    <button type="button" onclick="closeEditModal()"
                            class="py-2 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all text-xs">
                        Batal
                    </button>
                    <button type="submit"
                            class="py-2 px-5 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-all text-xs shadow-md shadow-indigo-600/30 active:scale-95">
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Note Form -->
<form method="POST" id="deleteNoteForm" onsubmit="return confirm('Hapus catatan ini secara permanen?')">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="delete_note" value="1">
    <input type="hidden" name="note_id" id="deleteNoteId">
</form>

<!-- Hidden Toggle Pin Form -->
<form method="POST" id="togglePinForm" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="toggle_pin" value="1">
    <input type="hidden" name="note_id" id="togglePinNoteId">
</form>

<script>
let currentCategoryFilter = 'all';

const NOTE_COLOR_MAP = {
    'violet': '#8B5CF6',
    'indigo': '#6366F1',
    'sky': '#0EA5E9',
    'emerald': '#10B981',
    'amber': '#F59E0B',
    'rose': '#F43F5E'
};

function selectNewColor(color) {
    const input = document.getElementById('newNoteColor');
    if (input) input.value = color;
    document.querySelectorAll('#newColorPicker .new-swatch-btn').forEach(btn => {
        const c = btn.getAttribute('data-color');
        const hex = NOTE_COLOR_MAP[c] || '#8B5CF6';
        if (c === color) {
            btn.className = `new-swatch-btn w-7 h-7 rounded-full bg-[${hex}] transition-transform flex items-center justify-center text-white ring-2 ring-offset-2 ring-[${hex}] scale-110`;
            btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        } else {
            btn.className = `new-swatch-btn w-7 h-7 rounded-full bg-[${hex}] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100`;
            btn.innerHTML = '';
        }
    });
}

function selectEditColor(color) {
    const input = document.getElementById('editNoteColor');
    if (input) input.value = color;
    document.querySelectorAll('#editColorPicker .edit-swatch-btn').forEach(btn => {
        const c = btn.getAttribute('data-color');
        const hex = NOTE_COLOR_MAP[c] || '#8B5CF6';
        if (c === color) {
            btn.className = `edit-swatch-btn w-7 h-7 rounded-full bg-[${hex}] transition-transform flex items-center justify-center text-white ring-2 ring-offset-2 ring-[${hex}] scale-110`;
            btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        } else {
            btn.className = `edit-swatch-btn w-7 h-7 rounded-full bg-[${hex}] transition-transform flex items-center justify-center text-white opacity-75 hover:opacity-100`;
            btn.innerHTML = '';
        }
    });
}

function openAddModal() {
    selectNewColor('violet');
    const m = document.getElementById('addModal');
    m.classList.remove('hidden'); m.classList.add('flex');
    const input = m.querySelector('input[name="title"]');
    if (input) input.focus();
}
function closeAddModal() {
    const m = document.getElementById('addModal');
    m.classList.add('hidden'); m.classList.remove('flex');
}
function openEditModal(id, title, content, category, color, isPinned) {
    document.getElementById('editNoteId').value       = id;
    document.getElementById('deleteNoteId').value     = id;
    document.getElementById('editNoteTitle').value    = title;
    document.getElementById('editNoteContent').value  = content;
    document.getElementById('editNoteCategory').value = category || 'Ide';
    selectEditColor(color || 'violet');
    document.getElementById('editNotePinned').checked = (parseInt(isPinned, 10) === 1);
    updateCount(content, 'editWordCount');
    const m = document.getElementById('editModal');
    m.classList.remove('hidden'); m.classList.add('flex');
    document.getElementById('editNoteTitle').focus();
}
function closeEditModal() {
    const m = document.getElementById('editModal');
    m.classList.add('hidden'); m.classList.remove('flex');
}

function updateCount(text, targetId) {
    const el = document.getElementById(targetId);
    if (!el) return;
    const words = text.trim() ? text.trim().split(/\s+/).length : 0;
    el.innerText = words + ' kata';
}

function copyNoteContent(content, btn) {
    navigator.clipboard.writeText(content).then(() => {
        if (window.showToast) {
            window.showToast('Isi catatan berhasil disalin ke clipboard!', 'success');
        } else {
            alert('Isi catatan berhasil disalin!');
        }
    }).catch(err => console.log('Copy failed:', err));
}

function triggerDeleteNote(id) {
    document.getElementById('deleteNoteId').value = id;
    const form = document.getElementById('deleteNoteForm');
    if (confirm('Hapus catatan ini secara permanen?')) {
        form.submit();
    }
}

function togglePinNote(id, btn) {
    document.getElementById('togglePinNoteId').value = id;
    document.getElementById('togglePinForm').submit();
}

function toggleNoteMenu(id, event) {
    event.stopPropagation();
    hideAllNoteMenus();
    const menu = document.getElementById('noteMenu-' + id);
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

function hideAllNoteMenus() {
    document.querySelectorAll('.note-menu-dropdown').forEach(m => m.classList.add('hidden'));
}

document.addEventListener('click', hideAllNoteMenus);

// Category Filter Handler
function setCategoryFilter(category) {
    currentCategoryFilter = category;
    document.querySelectorAll('.cat-filter-btn').forEach(btn => {
        const cat = btn.getAttribute('data-cat');
        if (cat === category) {
            btn.className = 'cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-indigo-600 text-white shadow-xs';
        } else {
            btn.className = 'cat-filter-btn px-3 py-1.5 rounded-xl font-bold transition-all bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200';
        }
    });
    handleFilterChange();
}

// Search and Category Combined Filter
function handleFilterChange() {
    const query = document.getElementById('searchNotes').value.toLowerCase().trim();
    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) clearBtn.classList.toggle('hidden', !query);
    
    document.querySelectorAll('#notesContainer .note-glass-card').forEach(card => {
        const title   = card.dataset.title   || '';
        const content = card.dataset.content || '';
        const cat     = card.dataset.category || 'Ide';
        
        const matchQuery = !query || title.includes(query) || content.includes(query);
        const matchCat   = currentCategoryFilter === 'all' || cat.toLowerCase() === currentCategoryFilter.toLowerCase();
        
        card.style.display = (matchQuery && matchCat) ? '' : 'none';
    });
}

function clearSearch() {
    const input = document.getElementById('searchNotes');
    if (input) {
        input.value = '';
        handleFilterChange();
        input.focus();
    }
}

// Sorting logic
function handleSortChange(sortMode) {
    const container = document.getElementById('notesContainer');
    if (!container) return;
    const cards = Array.from(container.querySelectorAll('.note-glass-card'));
    
    cards.sort((a, b) => {
        const pinnedA = parseInt(a.dataset.pinned || '0', 10);
        const pinnedB = parseInt(b.dataset.pinned || '0', 10);
        const timeA   = parseInt(a.dataset.updated || '0', 10);
        const timeB   = parseInt(b.dataset.updated || '0', 10);
        const titleA  = (a.dataset.title || '').toLowerCase();
        const titleB  = (b.dataset.title || '').toLowerCase();

        if (sortMode === 'pinned_first') {
            if (pinnedA !== pinnedB) return pinnedB - pinnedA;
            return timeB - timeA;
        } else if (sortMode === 'newest') {
            return timeB - timeA;
        } else if (sortMode === 'oldest') {
            return timeA - timeB;
        } else if (sortMode === 'alpha') {
            return titleA.localeCompare(titleB);
        }
        return 0;
    });

    cards.forEach(card => container.appendChild(card));
}

// View switcher (Grid vs List)
function setNotesView(mode) {
    const container = document.getElementById('notesContainer');
    const btnGrid = document.getElementById('viewBtnGrid');
    const btnList = document.getElementById('viewBtnList');
    if (!container) return;

    if (mode === 'list') {
        container.className = 'flex flex-col gap-3.5 max-w-4xl mx-auto';
        btnList.className = 'p-1.5 rounded-lg text-slate-700 dark:text-white bg-white dark:bg-slate-700 shadow-sm transition-all';
        btnGrid.className = 'p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all';
    } else {
        container.className = 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5';
        btnGrid.className = 'p-1.5 rounded-lg text-slate-700 dark:text-white bg-white dark:bg-slate-700 shadow-sm transition-all';
        btnList.className = 'p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all';
    }
    localStorage.setItem('taskflow_notes_view', mode);
}

// Restore view preference
(function() {
    const pref = localStorage.getItem('taskflow_notes_view');
    if (pref === 'list') {
        setNotesView('list');
    }
})();

// Shortcuts: Ctrl + Enter to submit modals
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        if (!addModal.classList.contains('hidden')) {
            document.getElementById('addNoteForm').submit();
        } else if (!editModal.classList.contains('hidden')) {
            document.getElementById('editNoteForm').submit();
        }
    }
    if (e.key === 'Escape') {
        closeAddModal();
        closeEditModal();
        hideAllNoteMenus();
    }
});

document.getElementById('addModal').addEventListener('click', closeAddModal);
document.getElementById('editModal').addEventListener('click', closeEditModal);
</script>
