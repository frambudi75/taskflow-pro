<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/notes.php - Catatan & Ide 2.0 (Personal Productivity Workspace)

// Handle Add Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $title   = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title) {
            $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $content]);
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
    if (validateCSRF($_POST['csrf_token'])) {
        $note_id = (int)$_POST['note_id'];
        $title   = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title) {
            $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $content, $note_id, $_SESSION['user_id']]);
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
    if (validateCSRF($_POST['csrf_token'])) {
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

$stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notes = $stmt->fetchAll();

// Statistics calculation
$total_notes = count($notes);
$total_words = 0;
foreach ($notes as $n) {
    $total_words += str_word_count(strip_tags($n['content']));
}

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
?>

<style>
/* Catatan 2.0 Frosted Glass Craft & Micro-interactions */
.note-glass-card {
    background: rgba(255, 255, 255, 0.78);
    backdrop-filter: blur(18px) saturate(180%);
    -webkit-backdrop-filter: blur(18px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95);
    position: relative;
    overflow: hidden;
}
.note-glass-card:hover {
    transform: translateY(-3px);
    background: rgba(255, 255, 255, 0.94);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 12px 30px -4px rgba(79, 70, 229, 0.12), inset 0 1px 0 #ffffff;
}

.notes-glass-panel {
    background: rgba(255, 255, 255, 0.76);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.note-quick-action {
    opacity: 0;
    transform: translateY(4px);
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.note-glass-card:hover .note-quick-action {
    opacity: 1;
    transform: translateY(0);
}

.modal-backdrop-blur {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
</style>

<div class="p-4 md:p-8 w-full max-w-[1600px] mx-auto space-y-6">

    <!-- Executive Header Banner (Frosted Glass) -->
    <div class="rounded-3xl p-6 md:p-7 notes-glass-panel relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-50 border border-violet-100 text-violet-700 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <span class="w-2 h-2 rounded-full bg-violet-500 animate-pulse"></span>
                Dokumentasi &amp; Ide
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Catatan &amp; Ide Kerja</h1>
            <p class="text-slate-500 text-xs md:text-sm font-medium max-w-xl leading-relaxed">
                Kelola draf ide, notula rapat, dan dokumentasi kerja Anda dalam ruang tulis yang bersih.
            </p>
        </div>

        <!-- Metric Badges & Action -->
        <div class="flex flex-wrap items-center gap-3 flex-shrink-0">
            <div class="bg-slate-50 border border-slate-200/80 px-3.5 py-2 rounded-xl flex items-center gap-3">
                <div>
                    <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Total Catatan</div>
                    <div class="text-lg font-black text-slate-900 leading-none mt-0.5"><?php echo $total_notes; ?></div>
                </div>
                <div class="w-px h-6 bg-slate-200"></div>
                <div>
                    <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Estimasi Kata</div>
                    <div class="text-lg font-black text-violet-700 leading-none mt-0.5"><?php echo number_format($total_words); ?></div>
                </div>
            </div>

            <button onclick="openAddModal()"
                    class="min-h-[44px] flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/25 transition-all active:scale-95">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                + Catatan Baru
            </button>
        </div>
    </div>

    <!-- Controls Bar: Search, View Mode Toggle, Count -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1">
        <div class="flex items-center gap-3 flex-1 max-w-md">
            <div class="relative w-full">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="searchNotes" placeholder="Cari catatan berdasarkan judul atau isi..." oninput="filterNotes(this.value)"
                       class="w-full pl-9 pr-9 py-2 rounded-xl text-xs font-semibold bg-white/80 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all">
                <button type="button" id="clearSearchBtn" onclick="clearSearch()" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold">✕</button>
            </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto flex-shrink-0">
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
        <div class="flex flex-col items-center justify-center py-20 text-center rounded-3xl bg-white/70 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-800 shadow-sm">
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
        <!-- Grid View (Masonry) -->
        <div id="notesContainer" class="columns-1 md:columns-2 xl:columns-3 gap-5">
            <?php foreach($notes as $note):
                $preview = strlen($note['content']) > 240 ? substr($note['content'], 0, 240) . '…' : $note['content'];
                $wordCount = str_word_count(strip_tags($note['content']));
            ?>
                <div class="note-glass-card break-inside-avoid mb-5 p-5 border-t-[3px] border-t-violet-500 cursor-pointer group"
                     data-id="<?php echo $note['id']; ?>"
                     data-title="<?php echo strtolower(htmlspecialchars($note['title'])); ?>"
                     data-content="<?php echo strtolower(htmlspecialchars($note['content'])); ?>"
                     onclick="openEditModal(<?php echo $note['id']; ?>, <?php echo htmlspecialchars(json_encode($note['title'])); ?>, <?php echo htmlspecialchars(json_encode($note['content'])); ?>)">
                    
                    <!-- Header inside card -->
                    <div class="flex items-center justify-between gap-2 mb-2.5">
                        <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                            <?php echo notesTimeAgo($note['updated_at']); ?>
                        </span>
                        
                        <div class="flex items-center gap-1.5">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                <?php echo $wordCount; ?> kata
                            </span>
                        </div>
                    </div>

                    <!-- Note Title -->
                    <h3 class="text-[15px] font-extrabold text-slate-900 dark:text-white mb-2 leading-snug group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                        <?php echo htmlspecialchars($note['title']); ?>
                    </h3>

                    <!-- Note Excerpt -->
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed font-normal">
                        <?php echo nl2br(htmlspecialchars($preview)); ?>
                    </p>

                    <!-- Footer & Quick Actions -->
                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors flex items-center gap-1">
                            <span>Buka Catatan</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </span>

                        <div class="note-quick-action flex items-center gap-1" onclick="event.stopPropagation()">
                            <button type="button" onclick="copyNoteContent(<?php echo htmlspecialchars(json_encode($note['content'])); ?>, this)" title="Salin Isi Catatan"
                                    class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-slate-700 text-slate-500 flex items-center justify-center transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                            <button type="button" onclick="triggerDeleteNote(<?php echo $note['id']; ?>)" title="Hapus Catatan"
                                    class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 text-slate-500 flex items-center justify-center transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="12" x2="18" y2="12"/></svg>
                            </button>
                        </div>
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
                    <p class="text-slate-400 text-xs font-medium">Dokumentasikan ide atau agenda penting</p>
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

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="newNoteContent" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider">Isi Dokumentasi</label>
                    <span id="addWordCount" class="text-[10px] text-slate-400 font-semibold">0 kata</span>
                </div>
                <textarea name="content" id="newNoteContent" placeholder="Ketik isi catatan atau draf Anda di sini... (Tekan Ctrl + Enter untuk menyimpan)" rows="8"
                          oninput="updateCount(this.value, 'addWordCount')"
                          class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-xs font-medium leading-relaxed text-slate-800 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all resize-none"></textarea>
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

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="editNoteContent" class="block text-[11px] font-black text-slate-600 dark:text-slate-300 uppercase tracking-wider">Isi Dokumentasi</label>
                    <span id="editWordCount" class="text-[10px] text-slate-400 font-semibold">0 kata</span>
                </div>
                <textarea name="content" id="editNoteContent" rows="8"
                          oninput="updateCount(this.value, 'editWordCount')"
                          class="w-full bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 text-xs font-medium leading-relaxed text-slate-800 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 transition-all resize-none"></textarea>
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

<script>
function openAddModal() {
    const m = document.getElementById('addModal');
    m.classList.remove('hidden'); m.classList.add('flex');
    const input = m.querySelector('input[name="title"]');
    if (input) input.focus();
}
function closeAddModal() {
    const m = document.getElementById('addModal');
    m.classList.add('hidden'); m.classList.remove('flex');
}
function openEditModal(id, title, content) {
    document.getElementById('editNoteId').value    = id;
    document.getElementById('deleteNoteId').value  = id;
    document.getElementById('editNoteTitle').value   = title;
    document.getElementById('editNoteContent').value = content;
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
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a37a" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        setTimeout(() => { btn.innerHTML = originalHtml; }, 1600);
    }).catch(err => console.log('Copy failed:', err));
}

function triggerDeleteNote(id) {
    document.getElementById('deleteNoteId').value = id;
    const form = document.getElementById('deleteNoteForm');
    if (confirm('Hapus catatan ini secara permanen?')) {
        form.submit();
    }
}

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
    }
});

document.getElementById('addModal').addEventListener('click', closeAddModal);
document.getElementById('editModal').addEventListener('click', closeEditModal);

// Search filtering
function filterNotes(query) {
    const q = query.toLowerCase().trim();
    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) clearBtn.classList.toggle('hidden', !q);
    
    document.querySelectorAll('#notesContainer .note-glass-card').forEach(card => {
        const title   = card.dataset.title   || '';
        const content = card.dataset.content || '';
        card.style.display = (!q || title.includes(q) || content.includes(q)) ? '' : 'none';
    });
}

function clearSearch() {
    const input = document.getElementById('searchNotes');
    if (input) {
        input.value = '';
        filterNotes('');
        input.focus();
    }
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
        container.className = 'columns-1 md:columns-2 xl:columns-3 gap-5';
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
</script>
