<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/notes.php

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

$card_colors = [
    ['bg' => '#fefce8', 'accent' => '#854d0e', 'border' => '#fde047'],
    ['bg' => '#eff6ff', 'accent' => '#1e40af', 'border' => '#bfdbfe'],
    ['bg' => '#f0fdf4', 'accent' => '#166534', 'border' => '#bbf7d0'],
    ['bg' => '#fdf2f8', 'accent' => '#9d174d', 'border' => '#fbcfe8'],
    ['bg' => '#f5f3ff', 'accent' => '#5b21b6', 'border' => '#ddd6fe'],
    ['bg' => '#fff7ed', 'accent' => '#9a3412', 'border' => '#fed7aa'],
    ['bg' => '#ecfeff', 'accent' => '#155e75', 'border' => '#a5f3fc'],
    ['bg' => '#f8fafc', 'accent' => '#334155', 'border' => '#cbd5e1'],
];
?>

<style>
.note-card { transition: all 0.2s ease; }
.note-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
.modal-overlay { backdrop-filter: blur(4px); }
</style>

<div class="p-5 md:p-8 w-full">

    <div class="rounded-2xl p-7 md:p-9 mb-7 bg-indigo-950 text-white flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div class="relative z-10">
            <div class="text-indigo-300 text-xs font-bold uppercase tracking-wider mb-2">Dokumentasi &amp; Ide</div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-white leading-tight mb-1">Catatan dan Ide</h1>
            <p class="text-indigo-200 text-sm font-medium"><?php echo count($notes); ?> catatan tersimpan · Klik kartu untuk mengedit.</p>
        </div>
        <div class="relative z-10 flex items-center gap-3 flex-shrink-0">
            <div class="relative hidden md:block">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.7)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="searchNotes" placeholder="Cari catatan..." oninput="filterNotes(this.value)"
                       class="pl-9 pr-4 py-2.5 text-sm font-medium w-48 rounded-xl transition-all text-white bg-white/10 border border-white/20 focus:bg-white/20 focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <button onclick="openAddModal()"
                    class="min-h-[44px] flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm transition-all active:scale-95 text-white bg-indigo-600 hover:bg-indigo-700">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Catatan Baru
            </button>
        </div>
    </div>

    <?php if (empty($notes)): ?>
        <div class="flex flex-col items-center justify-center py-24 text-center">
            <div class="w-16 h-16 rounded-2xl bg-indigo-50 flex items-center justify-center mb-4">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <h3 class="text-xl font-bold text-slate-900 mb-1">Belum ada catatan</h3>
            <p class="text-slate-500 text-sm mb-6">Mulai tulis catatan atau draft ide pertama Anda.</p>
            <button onclick="openAddModal()"
                    class="min-h-[44px] px-6 py-3 rounded-xl font-bold text-white text-sm bg-indigo-600 hover:bg-indigo-700 transition-colors">
                + Buat Catatan Pertama
            </button>
        </div>
    <?php else: ?>
        <div id="notesGrid" class="columns-1 md:columns-2 xl:columns-3 gap-6">
            <?php foreach($notes as $idx => $note):
                $color   = $card_colors[$idx % count($card_colors)];
                $preview = strlen($note['content']) > 200 ? substr($note['content'], 0, 200) . '…' : $note['content'];
            ?>
                <div class="note-card break-inside-avoid mb-6 rounded-2xl p-6 border cursor-pointer relative"
                     style="background:<?php echo $color['bg']; ?>; border-color:<?php echo $color['border']; ?>;"
                     data-title="<?php echo strtolower(htmlspecialchars($note['title'])); ?>"
                     data-content="<?php echo strtolower(htmlspecialchars($note['content'])); ?>"
                     onclick="openEditModal(<?php echo $note['id']; ?>, <?php echo htmlspecialchars(json_encode($note['title'])); ?>, <?php echo htmlspecialchars(json_encode($note['content'])); ?>)">
                    <div class="text-[10px] font-bold uppercase tracking-wider mb-2"
                         style="color:<?php echo $color['accent']; ?>;">
                        <?php echo date('d M Y · H:i', strtotime($note['updated_at'])); ?>
                    </div>
                    <h4 class="text-base font-bold text-slate-900 mb-2 leading-snug">
                        <?php echo htmlspecialchars($note['title']); ?>
                    </h4>
                    <p class="text-sm text-slate-800 leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($preview)); ?>
                    </p>
                    <div class="mt-4 pt-3 border-t flex items-center justify-between"
                         style="border-color:<?php echo $color['border']; ?>">
                        <span class="text-xs font-semibold"
                              style="color:<?php echo $color['accent']; ?>">Klik untuk edit</span>
                        <div class="w-6 h-6 rounded-md flex items-center justify-center"
                             style="background:<?php echo $color['border']; ?>">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?php echo $color['accent']; ?>" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="addModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-overlay bg-slate-900/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-7 md:p-8" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Catatan Baru</h3>
                <p class="text-slate-500 text-xs font-semibold mt-0.5">Tulis draf atau ide baru</p>
            </div>
            <button onclick="closeAddModal()" aria-label="Tutup modal catatan baru" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-all">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="add_note" value="1">
            <div>
                <label for="newNoteTitle" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Judul</label>
                <input type="text" name="title" id="newNoteTitle" required placeholder="Judul catatan..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
            <div>
                <label for="newNoteContent" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Konten</label>
                <textarea name="content" id="newNoteContent" placeholder="Tulis isi catatan di sini..." rows="7"
                          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-normal text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeAddModal()"
                        class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-all text-sm">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-all text-sm">
                    Simpan Catatan
                </button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-overlay bg-slate-900/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-7 md:p-8" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Edit Catatan</h3>
                <p class="text-slate-500 text-xs font-semibold mt-0.5">Perbarui isi catatan Anda</p>
            </div>
            <button onclick="closeEditModal()" aria-label="Tutup modal edit catatan" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-all">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" id="editNoteForm" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="edit_note" value="1">
            <input type="hidden" name="note_id" id="editNoteId">
            <div>
                <label for="editNoteTitle" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Judul</label>
                <input type="text" name="title" id="editNoteTitle" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
            </div>
            <div>
                <label for="editNoteContent" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Konten</label>
                <textarea name="content" id="editNoteContent" rows="7"
                          class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-normal text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <form method="POST" id="deleteNoteForm" class="flex-none" onsubmit="return confirm('Hapus catatan ini?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="delete_note" value="1">
                    <input type="hidden" name="note_id" id="deleteNoteId">
                    <button type="submit" aria-label="Hapus catatan" class="px-4 py-3 rounded-xl border border-red-200 text-red-600 font-bold hover:bg-red-50 transition-all">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </form>
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-all text-sm">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-all text-sm">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    const m = document.getElementById('addModal');
    m.classList.remove('hidden'); m.classList.add('flex');
    m.querySelector('input[name="title"]').focus();
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
    const m = document.getElementById('editModal');
    m.classList.remove('hidden'); m.classList.add('flex');
    document.getElementById('editNoteTitle').focus();
}
function closeEditModal() {
    const m = document.getElementById('editModal');
    m.classList.add('hidden'); m.classList.remove('flex');
}
document.getElementById('addModal').addEventListener('click', closeAddModal);
document.getElementById('editModal').addEventListener('click', closeEditModal);
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeAddModal(); closeEditModal(); } });

function filterNotes(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('#notesGrid .note-card').forEach(card => {
        const title   = card.dataset.title   || '';
        const content = card.dataset.content || '';
        card.style.display = (!q || title.includes(q) || content.includes(q)) ? '' : 'none';
    });
}
</script>
