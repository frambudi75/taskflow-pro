<?php
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
        header("Location: index.php?page=notes");
        exit;
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
        header("Location: index.php?page=notes");
        exit;
    }
}

// Handle Delete Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $note_id = (int)$_POST['note_id'];
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$note_id, $_SESSION['user_id']]);
        logActivity("Menghapus sebuah catatan", "note", $note_id);
        header("Location: index.php?page=notes");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notes = $stmt->fetchAll();

$card_colors = [
    ['bg' => '#fef9c3', 'accent' => '#ca8a04', 'border' => '#fde047'],
    ['bg' => '#dbeafe', 'accent' => '#1d4ed8', 'border' => '#93c5fd'],
    ['bg' => '#dcfce7', 'accent' => '#15803d', 'border' => '#86efac'],
    ['bg' => '#fce7f3', 'accent' => '#be185d', 'border' => '#f9a8d4'],
    ['bg' => '#ede9fe', 'accent' => '#6d28d9', 'border' => '#c4b5fd'],
    ['bg' => '#ffedd5', 'accent' => '#c2410c', 'border' => '#fdba74'],
    ['bg' => '#cffafe', 'accent' => '#0e7490', 'border' => '#67e8f9'],
    ['bg' => '#f0fdf4', 'accent' => '#166534', 'border' => '#bbf7d0'],
];
?>

<style>
.note-card { transition: all 0.3s cubic-bezier(.175,.885,.32,1.275); }
.note-card:hover { transform: translateY(-6px) rotate(-0.5deg); box-shadow: 0 24px 48px rgba(0,0,0,0.12); }
.note-card:nth-child(even):hover { transform: translateY(-6px) rotate(0.5deg); }
.modal-overlay { backdrop-filter: blur(8px); }
</style>

<div class="p-5 md:p-8 max-w-7xl mx-auto">

    <!-- ===== HERO HEADER ===== -->
    <div class="rounded-3xl p-7 md:p-10 mb-7 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5"
         style="background:linear-gradient(135deg,#1e1b4b 0%,#4c1d95 50%,#5b21b6 100%)">
        <div class="absolute inset-0 pointer-events-none"
             style="background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:40px 40px"></div>
        <div class="absolute right-0 top-0 w-72 h-72 pointer-events-none"
             style="background:radial-gradient(circle,rgba(167,139,250,0.25) 0%,transparent 70%);transform:translate(30%,-30%)"></div>
        <div class="relative z-10">
            <div class="text-violet-300 text-[10px] font-black uppercase tracking-widest mb-2">Ide &amp; Inspirasi</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">📝 Catatan &amp; Ide</h1>
            <p class="text-violet-300 text-sm font-medium"><?php echo count($notes); ?> catatan tersimpan · Klik kartu untuk mengedit.</p>
        </div>
        <div class="relative z-10 flex items-center gap-3 flex-shrink-0">
            <!-- Search -->
            <div class="relative hidden md:block">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="searchNotes" placeholder="Cari catatan..." oninput="filterNotes(this.value)"
                       class="pl-9 pr-4 py-2.5 text-sm font-medium w-48 rounded-2xl transition-all text-white"
                       style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);outline:none"
                       onfocus="this.style.background='rgba(255,255,255,0.18)'"
                       onblur="this.style.background='rgba(255,255,255,0.1)'"
                       placeholder="Cari catatan...">
            </div>
            <!-- Add button -->
            <button onclick="openAddModal()"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-2xl font-bold text-sm transition-all active:scale-95 text-white"
                    style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Catatan Baru
            </button>
        </div>
    </div>

    <!-- ===== NOTES GRID ===== -->
    <?php if (empty($notes)): ?>
        <div class="flex flex-col items-center justify-center py-32 text-center">
            <div class="w-24 h-24 rounded-3xl flex items-center justify-center mb-6"
                 style="background:linear-gradient(135deg,#ede9fe,#dbeafe)">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#a5b4fc" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <h3 class="text-2xl font-black text-slate-900 mb-2">Halaman masih kosong</h3>
            <p class="text-slate-400 mb-8">Mulai tulis ide pertama Anda!</p>
            <button onclick="openAddModal()"
                    class="px-8 py-4 rounded-2xl font-bold text-white transition-all active:scale-95"
                    style="background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 8px 24px rgba(99,102,241,0.35)">
                + Buat Catatan Pertama
            </button>
        </div>
    <?php else: ?>
        <div id="notesGrid" class="columns-1 md:columns-2 xl:columns-3 gap-6">
            <?php foreach($notes as $idx => $note):
                $color   = $card_colors[$idx % count($card_colors)];
                $preview = strlen($note['content']) > 200 ? substr($note['content'], 0, 200) . '…' : $note['content'];
            ?>
                <div class="note-card break-inside-avoid mb-6 rounded-3xl p-7 border cursor-pointer relative"
                     style="background:<?php echo $color['bg']; ?>; border-color:<?php echo $color['border']; ?>;"
                     data-title="<?php echo strtolower(htmlspecialchars($note['title'])); ?>"
                     data-content="<?php echo strtolower(htmlspecialchars($note['content'])); ?>"
                     onclick="openEditModal(<?php echo $note['id']; ?>, <?php echo htmlspecialchars(json_encode($note['title'])); ?>, <?php echo htmlspecialchars(json_encode($note['content'])); ?>)">
                    <div class="text-[9px] font-black uppercase tracking-[0.2em] mb-3"
                         style="color:<?php echo $color['accent']; ?>; opacity:0.7">
                        <?php echo date('d M Y · H:i', strtotime($note['updated_at'])); ?>
                    </div>
                    <h4 class="text-lg font-black text-slate-900 mb-3 leading-tight">
                        <?php echo htmlspecialchars($note['title']); ?>
                    </h4>
                    <p class="text-sm text-slate-700 leading-relaxed opacity-80">
                        <?php echo nl2br(htmlspecialchars($preview)); ?>
                    </p>
                    <div class="mt-5 pt-4 border-t flex items-center justify-between"
                         style="border-color:<?php echo $color['border']; ?>">
                        <span class="text-[9px] font-black uppercase tracking-widest"
                              style="color:<?php echo $color['accent']; ?>">Klik untuk edit</span>
                        <div class="w-7 h-7 rounded-xl flex items-center justify-center"
                             style="background:<?php echo $color['border']; ?>">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="<?php echo $color['accent']; ?>" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== ADD NOTE MODAL ===== -->
<div id="addModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-overlay bg-slate-900/50">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg p-8 md:p-10" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-2xl font-black text-slate-900">Catatan Baru</h3>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Draft Cepat</p>
            </div>
            <button onclick="closeAddModal()" class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-all">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="add_note" value="1">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Judul</label>
                <input type="text" name="title" required placeholder="Judul yang menarik..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold text-slate-900 focus:outline-none transition-all"
                       style="focus:border-color:#6366f1">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Konten</label>
                <textarea name="content" placeholder="Tuangkan pikiran Anda di sini..." rows="8"
                          class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium text-slate-700 focus:outline-none transition-all resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeAddModal()"
                        class="flex-1 py-4 rounded-2xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 py-4 rounded-2xl font-bold text-white transition-all active:scale-95"
                        style="background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 8px 20px rgba(99,102,241,0.3)">
                    Simpan Catatan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== EDIT NOTE MODAL ===== -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-overlay bg-slate-900/50">
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg p-8 md:p-10" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h3 class="text-2xl font-black text-slate-900">Edit Catatan</h3>
                <p class="text-slate-400 text-xs font-bold uppercase tracking-widest mt-1">Perbarui ide Anda</p>
            </div>
            <button onclick="closeEditModal()" class="w-10 h-10 rounded-2xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition-all">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST" id="editNoteForm" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="edit_note" value="1">
            <input type="hidden" name="note_id" id="editNoteId">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Judul</label>
                <input type="text" name="title" id="editNoteTitle" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-bold text-slate-900 focus:outline-none transition-all">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Konten</label>
                <textarea name="content" id="editNoteContent" rows="8"
                          class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-medium text-slate-700 focus:outline-none transition-all resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <form method="POST" id="deleteNoteForm" class="flex-none" onsubmit="return confirm('Hapus catatan ini?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="delete_note" value="1">
                    <input type="hidden" name="note_id" id="deleteNoteId">
                    <button type="submit" class="px-5 py-4 rounded-2xl border border-red-100 text-red-500 font-bold hover:bg-red-50 transition-all">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </form>
                <button type="button" onclick="closeEditModal()"
                        class="flex-1 py-4 rounded-2xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition-all">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 py-4 rounded-2xl font-bold text-white transition-all active:scale-95"
                        style="background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 8px 20px rgba(99,102,241,0.3)">
                    Simpan
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
