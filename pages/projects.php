<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/projects.php - Kanban Board 2.0 (Executive Clean & Interactive Task Tracking)
$user_id = (int)$_SESSION['user_id'];

// Handle Quick Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $title = trim($_POST['title'] ?? '');
        $status = in_array($_POST['status'] ?? 'todo', ['todo', 'in_progress', 'done']) ? $_POST['status'] : 'todo';
        $todo_date = !empty($_POST['todo_date']) ? $_POST['todo_date'] : date('Y-m-d');
        $completed = ($status === 'done') ? 1 : 0;
        
        if ($title) {
            $stmt = $pdo->prepare("INSERT INTO todos (user_id, title, todo_date, completed, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $todo_date, $completed, $status]);
            $todo_id = $pdo->lastInsertId();
            logActivity("Menambahkan tugas ke papan Kanban: " . $title, "todo", $todo_id);
        }
        header("Location: index.php?page=projects");
        exit;
    }
}

// Handle Status Update (Drag & Drop or Button Click)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $todo_id = (int)$_POST['todo_id'];
        $new_status = in_array($_POST['status'], ['todo', 'in_progress', 'done']) ? $_POST['status'] : 'todo';
        $is_completed = ($new_status === 'done') ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE todos SET status = ?, completed = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $is_completed, $todo_id, $user_id]);
        
        logActivity("Mengubah status tugas #" . $todo_id . " menjadi " . strtoupper($new_status), "todo", $todo_id, ['new_status' => $new_status]);
        
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header("Location: index.php?page=projects");
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// Handle Delete Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $todo_id = (int)$_POST['todo_id'];
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ? AND user_id = ?");
        $stmt->execute([$todo_id, $user_id]);
        logActivity("Menghapus tugas dari Kanban", "todo", $todo_id);
        header("Location: index.php?page=projects");
        exit;
    }
}

// Fetch all todos for user
$stmt = $pdo->prepare("SELECT * FROM todos WHERE user_id = ? ORDER BY todo_date ASC, id DESC");
$stmt->execute([$user_id]);
$all_todos = $stmt->fetchAll();

$columns = [
    'todo' => [
        'id'          => 'todo',
        'title'       => 'Belum Mulai',
        'subtitle'    => 'Antrean pekerjaan yang siap dieksekusi',
        'accent'      => 'border-t-amber-500',
        'badge_bg'    => 'bg-amber-50 text-amber-800 border-amber-200',
        'dot'         => 'bg-amber-500',
        'next_status' => 'in_progress',
        'next_label'  => 'Mulai Kerjakan'
    ],
    'in_progress' => [
        'id'          => 'in_progress',
        'title'       => 'Sedang Dikerjakan',
        'subtitle'    => 'Tugas aktif dalam proses penyelesaian',
        'accent'      => 'border-t-indigo-500',
        'badge_bg'    => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'dot'         => 'bg-indigo-500 animate-pulse',
        'next_status' => 'done',
        'next_label'  => 'Selesaikan'
    ],
    'done' => [
        'id'          => 'done',
        'title'       => 'Selesai',
        'subtitle'    => 'Daftar pencapaian yang tuntas',
        'accent'      => 'border-t-emerald-500',
        'badge_bg'    => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'dot'         => 'bg-emerald-500',
        'next_status' => 'todo',
        'next_label'  => 'Buka Kembali'
    ]
];

$grouped = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($all_todos as $t) {
    $status = $t['status'] ?: ($t['completed'] ? 'done' : 'todo');
    if (!isset($grouped[$status])) $status = 'todo';
    $grouped[$status][] = $t;
}

$total_tasks = count($all_todos);
$done_tasks  = count($grouped['done']);
$overall_pct = $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0;
?>

<style>
/* Kanban 2.0 Frosted Glassmorphism Visual Refinement */
.kanban-lane {
    background: rgba(248, 250, 252, 0.72);
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 24px;
    box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.03), inset 0 1px 0 rgba(255, 255, 255, 0.9);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.kanban-lane.dragover {
    background: rgba(238, 242, 255, 0.85);
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.kanban-task-card {
    background: rgba(255, 255, 255, 0.84);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 18px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03), inset 0 1px 0 #ffffff;
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.kanban-task-card:hover {
    background: rgba(255, 255, 255, 0.98);
    border-color: rgba(99, 102, 241, 0.4);
    box-shadow: 0 10px 24px -4px rgba(79, 70, 229, 0.12), inset 0 1px 0 #ffffff;
    transform: translateY(-2px);
}
.kanban-task-card.is-dragging {
    opacity: 0.4;
    transform: scale(0.98);
}

.kanban-glass-panel {
    background: rgba(255, 255, 255, 0.76);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}
</style>

<div class="p-4 md:p-7 w-full max-w-[1600px] mx-auto space-y-6">

    <!-- Executive Header Section (Glassmorphic) -->
    <div class="rounded-3xl p-6 md:p-7 kanban-glass-panel relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Manajemen Proyek Visual
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Papan Kanban</h1>
            <p class="text-slate-500 text-xs md:text-sm max-w-xl leading-relaxed font-medium">
                Kelola aliran tugas secara visual dengan seret-dan-lepas (drag &amp; drop) dan pantau persentase penyelesaian proyek.
            </p>
        </div>

        <!-- Header Actions: Quick Add Modal Trigger + Stats Progress -->
        <div class="flex flex-wrap items-center gap-4 flex-shrink-0">
            <div class="hidden sm:flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-200/90">
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase tracking-wider text-slate-500">Penyelesaian</div>
                    <div class="text-xs font-black text-slate-900"><?php echo $done_tasks; ?> dari <?php echo $total_tasks; ?> (<?php echo $overall_pct; ?>%)</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-black text-xs border border-indigo-100">
                    <?php echo $overall_pct; ?>%
                </div>
            </div>

            <button type="button" onclick="openAddTaskModal('todo')" 
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-transform active:scale-95 flex items-center gap-2 shadow-sm shadow-indigo-600/25">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tambah Tugas Baru</span>
            </button>
        </div>
    </div>

    <!-- Kanban Grid (3 Responsive Columns) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
        <?php foreach ($columns as $cid => $cdata): 
            $tasks = $grouped[$cid];
            $count = count($tasks);
        ?>
        <div class="kanban-lane p-4 md:p-5 flex flex-col min-h-[580px] border-t-4 <?php echo $cdata['accent']; ?>"
             id="col-<?php echo $cid; ?>"
             ondragover="kanbanAllowDrop(event)"
             ondragenter="kanbanDragEnter(event, '<?php echo $cid; ?>')"
             ondragleave="kanbanDragLeave(event, '<?php echo $cid; ?>')"
             ondrop="kanbanDrop(event, '<?php echo $cid; ?>')">
            
            <!-- Lane Header -->
            <div class="flex items-center justify-between mb-2 px-1">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full <?php echo $cdata['dot']; ?>"></span>
                    <h2 class="font-black text-slate-900 text-sm tracking-tight"><?php echo $cdata['title']; ?></h2>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-black border <?php echo $cdata['badge_bg']; ?>">
                    <?php echo $count; ?>
                </span>
            </div>
            <p class="text-[11px] text-slate-400 font-medium mb-4 px-1 leading-tight">
                <?php echo $cdata['subtitle']; ?>
            </p>

            <!-- Quick Add Button Per Column -->
            <button type="button" onclick="openAddTaskModal('<?php echo $cid; ?>')" 
                    class="w-full mb-3.5 py-2 px-3 rounded-xl border border-dashed border-slate-300 hover:border-indigo-500 hover:bg-white text-slate-500 hover:text-indigo-600 text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tambah ke <?php echo $cdata['title']; ?></span>
            </button>

            <!-- Cards Container -->
            <div class="flex flex-col gap-3 flex-1">
                <?php foreach ($tasks as $todo): 
                    $dateObj   = strtotime($todo['todo_date']);
                    $dateFmt   = date('d M Y', $dateObj);
                    $isOverdue = $dateObj < strtotime('today') && $cid !== 'done';
                    $isToday   = date('Y-m-d', $dateObj) === date('Y-m-d');
                ?>
                <div class="kanban-task-card p-4 cursor-grab active:cursor-grabbing group relative select-none"
                     draggable="true" 
                     ondragstart="kanbanDragStart(event, <?php echo $todo['id']; ?>)" 
                     ondragend="kanbanDragEnd(event)"
                     id="todo-<?php echo $todo['id']; ?>">
                    
                    <!-- Card Header: Title -->
                    <div class="mb-2.5">
                        <h3 class="font-black text-slate-900 text-xs sm:text-[13px] leading-snug group-hover:text-indigo-600 transition-colors">
                            <?php echo htmlspecialchars($todo['title']); ?>
                        </h3>
                    </div>

                    <!-- Card Footer: Due Date Badge & Status Advance Button -->
                    <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 mt-auto text-xs">
                        <!-- Date Chip -->
                        <div class="flex items-center gap-1.5">
                            <?php if ($isOverdue): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-md">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php echo $dateFmt; ?> (Terlambat)
                            </span>
                            <?php elseif ($isToday): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Hari Ini
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-500">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                                <?php echo $dateFmt; ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Advance / Options Buttons -->
                        <div class="flex items-center gap-1">
                            <!-- Advance Step Button -->
                            <button type="button" 
                                    onclick="changeKanbanStatus(<?php echo $todo['id']; ?>, '<?php echo $cdata['next_status']; ?>')"
                                    title="<?php echo $cdata['next_label']; ?>" aria-label="<?php echo $cdata['next_label']; ?>"
                                    class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-indigo-50 text-slate-400 hover:text-indigo-600 border border-slate-200/80 flex items-center justify-center transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>

                            <!-- Delete Task Button -->
                            <button type="button" 
                                    onclick="confirmDeleteTask(<?php echo $todo['id']; ?>, '<?php echo addslashes($todo['title']); ?>')"
                                    title="Hapus tugas" aria-label="Hapus tugas"
                                    class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-600 border border-slate-200/80 flex items-center justify-center transition-colors">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($tasks)): ?>
                <!-- Empty Column Drop Area -->
                <div class="flex-1 min-h-[140px] flex flex-col items-center justify-center text-center p-6 border-2 border-dashed border-slate-200/90 rounded-2xl bg-white/60">
                    <p class="text-xs font-bold text-slate-400 mb-0.5">Tidak ada tugas</p>
                    <p class="text-[10px] text-slate-400">Tarik kartu ke sini untuk memindahkan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Hidden Delete Task Form -->
    <form id="deleteTaskForm" method="POST" class="hidden">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="delete_task" value="1">
        <input type="hidden" name="todo_id" id="deleteTodoId">
    </form>
</div>

<!-- ============================================================== -->
<!-- QUICK ADD TASK MODAL -->
<!-- ============================================================== -->
<div id="addTaskModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="addTaskModalTitle">
    <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl overflow-hidden flex flex-col border border-slate-200">
        
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/60">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100 shadow-xs">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900" id="addTaskModalTitle">Tambah Tugas Baru</h3>
                    <p class="text-xs text-slate-400 font-medium">Buat kartu tugas baru untuk papan visual Anda</p>
                </div>
            </div>
            <button type="button" onclick="closeAddTaskModal()" aria-label="Tutup modal" class="w-8 h-8 rounded-xl hover:bg-slate-200 text-slate-400 hover:text-slate-900 transition-colors flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="add_task" value="1">

            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Judul Tugas</label>
                <input type="text" name="title" required placeholder="Contoh: Selesaikan revisi laporan keuangan"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500 transition-all placeholder:text-slate-400">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Kolom Status</label>
                    <select name="status" id="addTaskStatusSelect"
                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500">
                        <option value="todo">Belum Mulai</option>
                        <option value="in_progress">Sedang Dikerjakan</option>
                        <option value="done">Selesai</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tenggat Waktu</label>
                    <input type="date" name="todo_date" value="<?php echo date('Y-m-d'); ?>" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" 
                        class="w-full py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/20 transition-transform active:scale-95 text-xs flex items-center justify-center gap-2">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Simpan Kartu Tugas
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    // -------------------------------------------------------------
    // Drag and Drop Logic
    // -------------------------------------------------------------
    let draggedId = null;

    window.kanbanDragStart = function(ev, id) {
        draggedId = id;
        ev.dataTransfer.setData("text/plain", id);
        ev.dataTransfer.effectAllowed = "move";
        const el = document.getElementById('todo-' + id);
        if (el) el.classList.add('is-dragging');
    };

    window.kanbanDragEnd = function(ev) {
        if (draggedId) {
            const el = document.getElementById('todo-' + draggedId);
            if (el) el.classList.remove('is-dragging');
        }
        document.querySelectorAll('.kanban-lane').forEach(l => l.classList.remove('dragover'));
    };

    window.kanbanAllowDrop = function(ev) {
        ev.preventDefault();
        ev.dataTransfer.dropEffect = "move";
    };

    window.kanbanDragEnter = function(ev, colId) {
        ev.preventDefault();
        const lane = document.getElementById('col-' + colId);
        if (lane) lane.classList.add('dragover');
    };

    window.kanbanDragLeave = function(ev, colId) {
        const lane = document.getElementById('col-' + colId);
        if (lane && !lane.contains(ev.relatedTarget)) {
            lane.classList.remove('dragover');
        }
    };

    window.kanbanDrop = function(ev, targetStatus) {
        ev.preventDefault();
        document.querySelectorAll('.kanban-lane').forEach(l => l.classList.remove('dragover'));
        const id = ev.dataTransfer.getData("text/plain") || draggedId;
        if (id) {
            changeKanbanStatus(id, targetStatus);
        }
    };

    window.changeKanbanStatus = function(id, status) {
        const formData = new FormData();
        formData.append('update_status', '1');
        formData.append('todo_id', id);
        formData.append('status', status);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('index.php?page=projects', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                if (typeof loadPage === 'function') {
                    loadPage('index.php?page=projects', false);
                } else {
                    window.location.reload();
                }
            }
        })
        .catch(function() {
            window.location.reload();
        });
    };

    // -------------------------------------------------------------
    // Add & Delete Modals
    // -------------------------------------------------------------
    window.openAddTaskModal = function(defaultStatus) {
        const modal = document.getElementById('addTaskModal');
        const sel = document.getElementById('addTaskStatusSelect');
        if (sel && defaultStatus) sel.value = defaultStatus;
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }
    };

    window.closeAddTaskModal = function() {
        const modal = document.getElementById('addTaskModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    };

    window.confirmDeleteTask = function(id, title) {
        if (confirm('Hapus tugas "' + title + '" dari papan Kanban?')) {
            document.getElementById('deleteTodoId').value = id;
            document.getElementById('deleteTaskForm').submit();
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddTaskModal();
        }
    });
})();
</script>
