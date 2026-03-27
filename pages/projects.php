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
    'todo'        => ['title' => '📋 Belum Mulai', 'bg' => 'bg-slate-100', 'border' => 'border-slate-200'],
    'in_progress' => ['title' => '⚡ Sedang Dikerjakan', 'bg' => 'bg-blue-50', 'border' => 'border-blue-100'],
    'done'        => ['title' => '✅ Selesai', 'bg' => 'bg-emerald-50', 'border' => 'border-emerald-100']
];

$grouped = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($all_todos as $t) {
    $status = $t['status'] ?: 'todo';
    if (isset($grouped[$status])) $grouped[$status][] = $t;
}
?>

<div class="p-5 md:p-8 w-full">
    <!-- HERO HEADER -->
    <div class="rounded-3xl p-7 md:p-10 mb-7 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5"
         style="background: linear-gradient(135deg,#312e81 0%,#4338ca 50%,#6366f1 100%)">
        <div class="absolute inset-0 pointer-events-none"
             style="background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:40px 40px"></div>
        <div class="relative z-10">
            <div class="text-indigo-200 text-[10px] font-black uppercase tracking-widest mb-2">Projects & Tracking</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">📋 Papan Kanban</h1>
            <p class="text-indigo-200 text-sm font-medium">Visualisasikan progres tugas Anda dengan mudah.</p>
        </div>
        <div class="relative z-10">
            <a href="?page=calendar" class="px-5 py-3 rounded-2xl bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-sm transition-all flex items-center gap-2">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tambah Tugas
            </a>
        </div>
    </div>

    <!-- KANBAN GRID -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
        <?php foreach ($columns as $cid => $cdata): ?>
        <div class="kanban-column bg-slate-50/50 rounded-[2rem] border border-slate-100 p-5 min-h-[500px]"
             id="col-<?php echo $cid; ?>" ondragover="allowDrop(event)" ondrop="drop(event, '<?php echo $cid; ?>')">
            
            <div class="flex items-center justify-between mb-5 px-2">
                <h3 class="font-black text-slate-900 text-sm uppercase tracking-wider"><?php echo $cdata['title']; ?></h3>
                <span class="px-2.5 py-1 rounded-lg bg-white border border-slate-100 text-[10px] font-black text-slate-400">
                    <?php echo count($grouped[$cid]); ?>
                </span>
            </div>

            <div class="flex flex-col gap-4">
                <?php foreach ($grouped[$cid] as $todo): 
                    $date = date('d M', strtotime($todo['todo_date']));
                    $isLate = strtotime($todo['todo_date']) < strtotime('today') && $cid !== 'done';
                ?>
                <div class="kanban-card bg-white rounded-2xl p-4 border border-slate-100 shadow-sm transition-all hover:shadow-md cursor-grab active:cursor-grabbing"
                     draggable="true" ondragstart="drag(event, <?php echo $todo['id']; ?>)" id="todo-<?php echo $todo['id']; ?>">
                    
                    <div class="mb-3">
                        <h4 class="font-bold text-slate-900 text-sm mb-1"><?php echo htmlspecialchars($todo['title']); ?></h4>
                        <?php if (isset($todo['description']) && $todo['description']): ?>
                            <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed"><?php echo htmlspecialchars($todo['description']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-between mt-auto pt-3 border-t border-slate-50">
                        <div class="flex items-center gap-1.5 <?php echo $isLate ? 'text-red-500' : 'text-slate-400'; ?>">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span class="text-[10px] font-bold"><?php echo $date; ?></span>
                        </div>
                        
                        <div class="flex items-center gap-1">
                            <button onclick="changeStatus(<?php echo $todo['id']; ?>, '<?php echo ($cid === 'todo' ? 'in_progress' : ($cid === 'in_progress' ? 'done' : 'todo')); ?>')"
                                    class="p-1.5 rounded-lg hover:bg-slate-100 transition-colors text-slate-400">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($grouped[$cid])): ?>
                <div class="py-10 text-center border-2 border-dashed border-slate-100 rounded-2xl">
                    <p class="text-[11px] font-bold text-slate-300 uppercase tracking-widest">Kosong</p>
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
