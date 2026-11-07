<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/projects.php - Kanban Board
$user_id = $_SESSION['user_id'];

// Handle Status Update (AJAX or POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $todo_id = $_POST['todo_id'];
        $new_status = $_POST['status'];
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

// Fetch all todos
$stmt = $pdo->prepare("SELECT * FROM todos WHERE user_id = ? ORDER BY todo_date ASC");
$stmt->execute([$user_id]);
$all_todos = $stmt->fetchAll();

$columns = [
    'todo'        => ['title' => 'Belum Mulai', 'dot' => 'bg-slate-400 dark:bg-slate-500'],
    'in_progress' => ['title' => 'Sedang Dikerjakan', 'dot' => 'bg-indigo-500 animate-pulse'],
    'done'        => ['title' => 'Selesai', 'dot' => 'bg-emerald-500']
];

$grouped = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($all_todos as $t) {
    $status = $t['status'] ?: 'todo';
    if (isset($grouped[$status])) $grouped[$status][] = $t;
}
?>

<div class="p-5 md:p-8 w-full max-w-[1600px] mx-auto">
    <!-- Executive Glassmorphic Header -->
    <div class="rounded-3xl p-6 md:p-8 mb-7 bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-bold uppercase tracking-widest mb-3">
                <svg class="w-3.5 h-3.5 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Projects &amp; Tracking
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white leading-tight tracking-tight mb-1">Papan Kanban</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">Visualisasikan dan kelola kemajuan penyelesaian tugas secara visual.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="?page=calendar" class="min-h-[44px] px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm transition-all flex items-center gap-2 shadow-sm active:scale-95">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tambah Tugas
            </a>
        </div>
    </div>

    <!-- Kanban Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
        <?php foreach ($columns as $cid => $cdata): ?>
        <div class="kanban-column bg-slate-50/80 dark:bg-slate-900/60 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 p-4 sm:p-5 min-h-[520px] transition-colors"
             id="col-<?php echo $cid; ?>" ondragover="allowDrop(event)" ondrop="drop(event, '<?php echo $cid; ?>')">
            
            <div class="flex items-center justify-between mb-4 px-1.5">
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full <?php echo $cdata['dot']; ?>"></span>
                    <h3 class="font-extrabold text-slate-900 dark:text-white text-sm tracking-tight"><?php echo $cdata['title']; ?></h3>
                </div>
                <span class="px-2.5 py-0.5 rounded-full bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 shadow-sm">
                    <?php echo count($grouped[$cid]); ?>
                </span>
            </div>

            <div class="flex flex-col gap-3">
                <?php foreach ($grouped[$cid] as $todo): 
                    $date = date('d M Y', strtotime($todo['todo_date']));
                    $isLate = strtotime($todo['todo_date']) < strtotime('today') && $cid !== 'done';
                ?>
                <div class="kanban-card bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/80 shadow-sm hover:shadow-md hover:border-slate-300 dark:hover:border-slate-600 transition-all duration-200 cursor-grab active:cursor-grabbing group"
                     draggable="true" ondragstart="drag(event, <?php echo $todo['id']; ?>)" id="todo-<?php echo $todo['id']; ?>">
                    
                    <div class="mb-3">
                        <h4 class="font-bold text-slate-900 dark:text-white text-sm mb-1.5 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                            <?php echo htmlspecialchars($todo['title']); ?>
                        </h4>
                        <?php if (isset($todo['description']) && $todo['description']): ?>
                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                <?php echo htmlspecialchars($todo['description']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-between mt-auto pt-3 border-t border-slate-100 dark:border-slate-700/60">
                        <div class="flex items-center gap-1.5 <?php echo $isLate ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-500 dark:text-slate-400 font-medium'; ?>">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span class="text-xs"><?php echo $date; ?></span>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <button onclick="changeStatus(<?php echo $todo['id']; ?>, '<?php echo ($cid === 'todo' ? 'in_progress' : ($cid === 'in_progress' ? 'done' : 'todo')); ?>')"
                                    title="Pindahkan status tugas"
                                    aria-label="Pindahkan status tugas"
                                    class="w-7 h-7 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/80 flex items-center justify-center text-slate-400 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($grouped[$cid])): ?>
                <div class="py-12 px-4 text-center border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-white/40 dark:bg-slate-900/30">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">Belum ada tugas di kolom ini.</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Tarik kartu tugas ke sini untuk memindahkan status.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function drag(ev, id) {
    ev.dataTransfer.setData("text", id);
}

function allowDrop(ev) {
    ev.preventDefault();
}

function drop(ev, status) {
    ev.preventDefault();
    var id = ev.dataTransfer.getData("text");
    changeStatus(id, status);
}

function changeStatus(id, status) {
    const formData = new FormData();
    formData.append('update_status', '1');
    formData.append('todo_id', id);
    formData.append('status', status);
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

    fetch('index.php?page=projects', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-WITH': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPage('index.php?page=projects', false);
        }
    });
}
</script>
