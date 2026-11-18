<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/projects.php - Kanban Board 2.0 (Executive Clean & Interactive Task Tracking with Lane Pagination, Linear-Style Detail Modal, and Checklists)
$user_id = (int)$_SESSION['user_id'];

// Handle Quick Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = in_array($_POST['priority'] ?? 'medium', ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
        $status = in_array($_POST['status'] ?? 'todo', ['todo', 'in_progress', 'done']) ? $_POST['status'] : 'todo';
        $todo_date = !empty($_POST['todo_date']) ? $_POST['todo_date'] : date('Y-m-d');
        $completed = ($status === 'done') ? 1 : 0;
        
        if ($title) {
            $stmt = $pdo->prepare("INSERT INTO todos (user_id, title, description, priority, todo_date, completed, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $description, $priority, $todo_date, $completed, $status]);
            $todo_id = $pdo->lastInsertId();
            logActivity("Menambahkan tugas ke papan Kanban: " . $title, "todo", $todo_id);
        }
        header("Location: index.php?page=projects");
        exit;
    }
}

// Handle Update Full Task Details (from Linear Detail Modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_details'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $todo_id = (int)$_POST['todo_id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = in_array($_POST['priority'] ?? 'medium', ['low', 'medium', 'high']) ? $_POST['priority'] : 'medium';
        $status = in_array($_POST['status'] ?? 'todo', ['todo', 'in_progress', 'done']) ? $_POST['status'] : 'todo';
        $todo_date = !empty($_POST['todo_date']) ? $_POST['todo_date'] : date('Y-m-d');
        $completed = ($status === 'done') ? 1 : 0;

        if ($title) {
            $stmt = $pdo->prepare("UPDATE todos SET title = ?, description = ?, priority = ?, status = ?, completed = ?, todo_date = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$title, $description, $priority, $status, $completed, $todo_date, $todo_id, $user_id]);
            logActivity("Memperbarui detail tugas #" . $todo_id . " (" . $title . ")", "todo", $todo_id);
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        header("Location: index.php?page=projects");
        exit;
    }
}

// Handle Checklist AJAX Operations (Add, Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checklist_action'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $action = $_POST['checklist_action'];
        
        if ($action === 'add') {
            $todo_id = (int)$_POST['todo_id'];
            $chk_title = trim($_POST['title'] ?? '');
            $chkOwner = $pdo->prepare("SELECT id FROM todos WHERE id = ? AND user_id = ?");
            $chkOwner->execute([$todo_id, $user_id]);
            if ($chkOwner->fetch() && $chk_title) {
                $stmt = $pdo->prepare("INSERT INTO todo_checklists (todo_id, title, is_completed) VALUES (?, ?, 0)");
                $stmt->execute([$todo_id, $chk_title]);
                $chk_id = $pdo->lastInsertId();
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $chk_id, 'title' => $chk_title, 'is_completed' => 0]);
                exit;
            }
        }
        
        if ($action === 'toggle') {
            $chk_id = (int)$_POST['checklist_id'];
            $is_completed = !empty($_POST['is_completed']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE todo_checklists tc JOIN todos t ON tc.todo_id = t.id SET tc.is_completed = ? WHERE tc.id = ? AND t.user_id = ?");
            $stmt->execute([$is_completed, $chk_id, $user_id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'is_completed' => $is_completed]);
            exit;
        }

        if ($action === 'delete') {
            $chk_id = (int)$_POST['checklist_id'];
            $stmt = $pdo->prepare("DELETE tc FROM todo_checklists tc JOIN todos t ON tc.todo_id = t.id WHERE tc.id = ? AND t.user_id = ?");
            $stmt->execute([$chk_id, $user_id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit;
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
        header('Content-Type: application/json');
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
$all_todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all checklists for user's todos
$checklists_by_todo = [];
try {
    $stmtChk = $pdo->prepare("SELECT tc.id, tc.todo_id, tc.title, tc.is_completed FROM todo_checklists tc JOIN todos t ON tc.todo_id = t.id WHERE t.user_id = ? ORDER BY tc.id ASC");
    $stmtChk->execute([$user_id]);
    $raw_checklists = $stmtChk->fetchAll(PDO::FETCH_ASSOC);
    foreach ($raw_checklists as $chk) {
        $checklists_by_todo[$chk['todo_id']][] = $chk;
    }
} catch(Exception $e) {}

// Fetch user's documents for quick attachment reference
$user_documents = [];
try {
    $stmtDoc = $pdo->prepare("SELECT id, name, file_type FROM documents WHERE user_id = ? ORDER BY id DESC LIMIT 15");
    $stmtDoc->execute([$user_id]);
    $user_documents = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}

// Priority helper
function getPriorityPill($priority) {
    switch(strtolower($priority ?? 'medium')) {
        case 'high':
        case 'tinggi':
            return [
                'label' => 'Tinggi',
                'class' => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/80',
                'dot'   => 'bg-rose-500 animate-pulse'
            ];
        case 'low':
        case 'rendah':
            return [
                'label' => 'Normal',
                'class' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                'dot'   => 'bg-slate-400'
            ];
        case 'medium':
        case 'sedang':
        default:
            return [
                'label' => 'Sedang',
                'class' => 'bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800/80',
                'dot'   => 'bg-amber-500'
            ];
    }
}

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
.dark .kanban-lane {
    background: rgba(30, 41, 59, 0.65);
    border-color: rgba(51, 65, 85, 0.6);
    box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.4);
}
.dark .kanban-lane.dragover {
    background: rgba(30, 58, 138, 0.4);
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
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
.dark .kanban-task-card {
    background: rgba(15, 23, 42, 0.85);
    border-color: rgba(51, 65, 85, 0.8);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
}
.dark .kanban-task-card:hover {
    background: rgba(15, 23, 42, 0.98);
    border-color: rgba(59, 130, 246, 0.5);
    box-shadow: 0 10px 24px -4px rgba(0, 0, 0, 0.6);
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
.dark .kanban-glass-panel {
    background: rgba(15, 23, 42, 0.85);
    border-color: rgba(51, 65, 85, 0.7);
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.5);
}
</style>

<div class="p-4 md:p-8 w-full space-y-6">

    <!-- Executive Header Section (Glassmorphic) -->
    <div class="rounded-3xl p-6 md:p-7 kanban-glass-panel relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Manajemen Proyek Visual
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mb-1">Papan Kanban</h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm max-w-xl leading-relaxed font-medium">
                Kelola aliran tugas secara visual dengan seret-dan-lepas (drag &amp; drop) dan pantau persentase penyelesaian proyek.
            </p>
        </div>

        <!-- Header Actions: Quick Add Modal Trigger + Stats Progress -->
        <div class="flex flex-wrap items-center gap-4 flex-shrink-0">
            <div class="hidden sm:flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/90 dark:border-slate-700">
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Penyelesaian</div>
                    <div class="text-xs font-black text-slate-900 dark:text-white"><?php echo $done_tasks; ?> dari <?php echo $total_tasks; ?> (<?php echo $overall_pct; ?>%)</div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-slate-700 text-indigo-600 dark:text-blue-400 flex items-center justify-center font-black text-xs border border-indigo-100 dark:border-slate-600">
                    <?php echo $overall_pct; ?>%
                </div>
            </div>

            <button type="button" onclick="openAddTaskModal('todo')" 
                    class="px-5 py-2.5 rounded-xl bg-[#1E5CE6] hover:bg-blue-600 text-white font-bold text-xs transition-transform active:scale-95 flex items-center gap-2 shadow-sm shadow-blue-600/25">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tambah Tugas Baru</span>
            </button>
        </div>
    </div>

    <!-- Filter & Pagination Bar -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 p-4 rounded-2xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="relative w-full sm:w-80">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="kanbanSearchInput" placeholder="Cari kartu tugas di papan..." oninput="filterKanban(this.value)"
                       class="pl-9 pr-4 py-2 text-xs font-bold w-full rounded-xl text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800 border border-slate-200/90 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/20 focus:bg-white dark:focus:bg-slate-800 focus:border-[#1E5CE6] transition-all placeholder:text-slate-400">
            </div>
        </div>

        <div class="flex items-center gap-3 justify-end">
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
                <span>Tampilan per kolom:</span>
                <select id="kanbanPageSizeSelect" onchange="changeKanbanPageSize(this.value)" 
                        class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 outline-none hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer">
                    <option value="5" selected>5 Tugas</option>
                    <option value="10">10 Tugas</option>
                    <option value="20">20 Tugas</option>
                    <option value="999">Semua</option>
                </select>
            </label>
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
                    <h2 class="font-black text-slate-900 dark:text-white text-sm tracking-tight"><?php echo $cdata['title']; ?></h2>
                </div>
                <span id="badge-count-<?php echo $cid; ?>" class="px-2.5 py-0.5 rounded-full text-xs font-black border <?php echo $cdata['badge_bg']; ?>">
                    <?php echo $count; ?>
                </span>
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-400 font-medium mb-4 px-1 leading-tight">
                <?php echo $cdata['subtitle']; ?>
            </p>

            <!-- Quick Add Button Per Column -->
            <button type="button" onclick="openAddTaskModal('<?php echo $cid; ?>')" 
                    class="w-full mb-3.5 py-2 px-3 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 hover:border-[#1E5CE6] hover:bg-white dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-[#1E5CE6] dark:hover:text-blue-400 text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-2xs">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Tambah ke <?php echo $cdata['title']; ?></span>
            </button>

            <!-- Cards Container -->
            <div class="flex flex-col gap-3 flex-1 kanban-cards-wrapper" id="lane-cards-<?php echo $cid; ?>">
                <?php foreach ($tasks as $todo): 
                    $dateObj      = strtotime($todo['todo_date']);
                    $dateFmt      = date('d M Y', $dateObj);
                    $isOverdue    = $dateObj < strtotime('today') && $cid !== 'done';
                    $isToday      = date('Y-m-d', $dateObj) === date('Y-m-d');
                    $todo_chks    = $checklists_by_todo[$todo['id']] ?? [];
                    $total_chk    = count($todo_chks);
                    $done_chk     = count(array_filter($todo_chks, fn($c) => !empty($c['is_completed'])));
                    $chk_pct      = $total_chk > 0 ? round(($done_chk / $total_chk) * 100) : 0;
                    $priorityPill = getPriorityPill($todo['priority'] ?? 'medium');
                    $has_desc     = !empty(trim($todo['description'] ?? ''));
                    $task_payload = htmlspecialchars(json_encode([
                        'id'          => $todo['id'],
                        'title'       => $todo['title'],
                        'description' => $todo['description'] ?? '',
                        'priority'    => $todo['priority'] ?? 'medium',
                        'status'      => $cid,
                        'status_label'=> $cdata['title'],
                        'todo_date'   => $todo['todo_date'],
                        'checklists'  => $todo_chks
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                ?>
                <div class="kanban-task-card p-4 cursor-pointer group relative select-none"
                     draggable="true" 
                     onclick="openTaskDetailModal(this)"
                     data-task="<?php echo $task_payload; ?>"
                     ondragstart="kanbanDragStart(event, <?php echo $todo['id']; ?>)" 
                     ondragend="kanbanDragEnd(event)"
                     id="todo-<?php echo $todo['id']; ?>"
                     data-lane="<?php echo $cid; ?>"
                     data-title="<?php echo strtolower(htmlspecialchars($todo['title'])); ?>">
                    
                    <!-- Top Meta Row: Priority Badge + Checklist Indicator -->
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-black border <?php echo $priorityPill['class']; ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?php echo $priorityPill['dot']; ?>"></span>
                            <?php echo $priorityPill['label']; ?>
                        </span>

                        <?php if ($total_chk > 0): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            <span>☑</span>
                            <span><?php echo $done_chk; ?>/<?php echo $total_chk; ?></span>
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- Title & Optional Description Preview -->
                    <div class="mb-2.5">
                        <h3 class="font-black text-slate-900 dark:text-white text-xs sm:text-[13px] leading-snug group-hover:text-[#1E5CE6] dark:group-hover:text-blue-400 transition-colors">
                            <?php echo htmlspecialchars($todo['title']); ?>
                        </h3>
                        <?php if ($has_desc): ?>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium line-clamp-2 mt-1 leading-relaxed">
                            <?php echo htmlspecialchars($todo['description']); ?>
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Checklist Mini Progress Bar (if checklists exist) -->
                    <?php if ($total_chk > 0): ?>
                    <div class="mb-3">
                        <div class="w-full h-1 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-[#1E5CE6] rounded-full transition-all duration-300" style="width: <?php echo $chk_pct; ?>%;"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Card Footer: Due Date Badge & Status Advance Button -->
                    <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-slate-800 mt-auto text-xs" onclick="event.stopPropagation()">
                        <!-- Date Chip -->
                        <div class="flex items-center gap-1.5">
                            <?php if ($isOverdue): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 px-2 py-0.5 rounded-md">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php echo $dateFmt; ?> (Terlambat)
                            </span>
                            <?php elseif ($isToday): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 px-2 py-0.5 rounded-md">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Hari Ini
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
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
                                    class="w-7 h-7 rounded-lg bg-slate-50 dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-slate-700 text-slate-400 hover:text-[#1E5CE6] dark:hover:text-blue-400 border border-slate-200/80 dark:border-slate-700 flex items-center justify-center transition-colors cursor-pointer">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>

                            <!-- Delete Task Button -->
                            <button type="button" 
                                    onclick="confirmDeleteTask(<?php echo $todo['id']; ?>, '<?php echo addslashes($todo['title']); ?>')"
                                    title="Hapus tugas" aria-label="Hapus tugas"
                                    class="w-7 h-7 rounded-lg bg-slate-50 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 border border-slate-200/80 dark:border-slate-700 flex items-center justify-center transition-colors cursor-pointer">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Empty Column Drop Area -->
                <div id="empty-lane-<?php echo $cid; ?>" class="<?php echo empty($tasks) ? 'flex' : 'hidden'; ?> flex-1 min-h-[140px] flex-col items-center justify-center text-center p-6 border-2 border-dashed border-slate-200/90 dark:border-slate-700 rounded-2xl bg-white/60 dark:bg-slate-800/40">
                    <p class="text-xs font-bold text-slate-400 dark:text-slate-400 mb-0.5">Tidak ada tugas</p>
                    <p class="text-[10px] text-slate-400 dark:text-slate-500">Tarik kartu ke sini untuk memindahkan</p>
                </div>
            </div>

            <!-- Lane Pagination Footer -->
            <div id="lane-pagination-<?php echo $cid; ?>" class="pt-3 mt-auto border-t border-slate-200/70 dark:border-slate-800 flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                <button type="button" onclick="prevLanePage('<?php echo $cid; ?>')" id="prev-btn-<?php echo $cid; ?>" 
                        class="w-7 h-7 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center transition-all shadow-2xs">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="flex items-center gap-1.5">
                    <span id="lane-indicator-<?php echo $cid; ?>" class="text-[11px] font-extrabold text-slate-600 dark:text-slate-300">Hal 1 / 1</span>
                    <span id="lane-count-<?php echo $cid; ?>" class="text-[10px] text-slate-400 dark:text-slate-500 font-semibold">(<?php echo $count; ?> tugas)</span>
                </div>
                <button type="button" onclick="nextLanePage('<?php echo $cid; ?>')" id="next-btn-<?php echo $cid; ?>" 
                        class="w-7 h-7 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center transition-all shadow-2xs">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
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
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full shadow-2xl overflow-hidden flex flex-col border border-slate-200 dark:border-slate-800 animate-modal-pop" onclick="event.stopPropagation()">
        
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/60 dark:bg-slate-800/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-[#1E5CE6]/10 text-[#1E5CE6] dark:text-blue-400 flex items-center justify-center border border-[#1E5CE6]/20 shadow-xs">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white" id="addTaskModalTitle">Tambah Tugas Baru</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-400 font-medium">Buat kartu tugas baru untuk papan visual Anda</p>
                </div>
            </div>
            <button type="button" onclick="closeAddTaskModal()" aria-label="Tutup modal" class="w-8 h-8 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="add_task" value="1">

            <div>
                <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Judul Tugas</label>
                <input type="text" name="title" required placeholder="Contoh: Selesaikan revisi laporan keuangan"
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/20 focus:bg-white dark:focus:bg-slate-800 focus:border-[#1E5CE6] transition-all placeholder:text-slate-400">
            </div>

            <div>
                <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Catatan / Deskripsi Singkat</label>
                <textarea name="description" rows="2" placeholder="Detail atau instruksi spesifik untuk tugas ini..."
                          class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/20 focus:bg-white dark:focus:bg-slate-800 focus:border-[#1E5CE6] transition-all placeholder:text-slate-400"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Kolom</label>
                    <select name="status" id="addTaskStatusSelect"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/20 focus:bg-white dark:focus:bg-slate-800 focus:border-[#1E5CE6]">
                        <option value="todo">Belum Mulai</option>
                        <option value="in_progress">Dikerjakan</option>
                        <option value="done">Selesai</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Prioritas</label>
                    <select name="priority"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/20 focus:bg-white dark:focus:bg-slate-800 focus:border-[#1E5CE6]">
                        <option value="medium" selected>Sedang</option>
                        <option value="high">Tinggi</option>
                        <option value="low">Normal</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest mb-1.5">Tenggat</label>
                    <input type="date" name="todo_date" value="<?php echo date('Y-m-d'); ?>" required
                           class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/20 focus:bg-white dark:focus:bg-slate-800 focus:border-[#1E5CE6]">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" 
                        class="w-full py-3 rounded-xl font-bold text-white bg-[#1E5CE6] hover:bg-blue-600 shadow-md shadow-blue-600/20 transition-transform active:scale-95 text-xs flex items-center justify-center gap-2 cursor-pointer">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Simpan Kartu Tugas
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- LINEAR / NOTION-STYLE COMPREHENSIVE TASK DETAIL MODAL -->
<!-- ============================================================== -->
<div id="taskDetailModal" class="fixed inset-0 hidden z-50 items-center justify-center p-3 sm:p-4 bg-slate-950/70 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="taskDetailModalTitle">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-3xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-slate-200 dark:border-slate-800 animate-modal-pop" onclick="event.stopPropagation()">
        
        <!-- Modal Top Bar -->
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/70 dark:bg-slate-800/50">
            <div class="flex items-center gap-2.5">
                <span id="detailStatusBadge" class="px-2.5 py-1 rounded-full text-[10px] font-black border uppercase tracking-wider">
                    Status
                </span>
                <span class="text-xs font-mono font-bold text-slate-400" id="detailTaskId">#0</span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="closeTaskDetailModal()" class="w-8 h-8 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors flex items-center justify-center cursor-pointer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- 2-Column Split Modal Body -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6">
            <form id="taskDetailForm" onsubmit="saveTaskDetails(event)" class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="update_task_details" value="1">
                <input type="hidden" name="todo_id" id="detailFormTodoId">

                <!-- Left Column (8 of 12 Cols) -->
                <div class="md:col-span-7 lg:col-span-8 space-y-5">
                    <!-- Title Input -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Judul Tugas</label>
                        <input type="text" name="title" id="detailTitleInput" required
                               class="w-full text-base sm:text-lg font-black text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/30 focus:border-[#1E5CE6] transition-all">
                    </div>

                    <!-- Description Input -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Catatan &amp; Deskripsi Tugas</label>
                        <textarea name="description" id="detailDescInput" rows="3" placeholder="Tulis catatan atau instruksi spesifik untuk tugas ini..."
                                  class="w-full text-xs font-medium text-slate-800 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/30 focus:border-[#1E5CE6] transition-all placeholder:text-slate-400 leading-relaxed"></textarea>
                    </div>

                    <!-- Checklists / Sub-tasks Section -->
                    <div class="pt-2">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sub-tugas (Checklist)</span>
                                <span id="detailChecklistRatio" class="text-[10px] font-bold font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">0/0</span>
                            </div>
                            <span id="detailChecklistPct" class="text-[11px] font-black text-[#1E5CE6] dark:text-blue-400">0% Selesai</span>
                        </div>

                        <!-- Animated Progress Bar -->
                        <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mb-3">
                            <div id="detailChecklistProgressBar" class="h-full bg-[#1E5CE6] rounded-full transition-all duration-300" style="width: 0%;"></div>
                        </div>

                        <!-- Checklists Items Container -->
                        <div id="detailChecklistContainer" class="space-y-2 mb-3">
                            <!-- Items injected dynamically -->
                        </div>

                        <!-- Add Checklist Item Input -->
                        <div class="flex items-center gap-2">
                            <input type="text" id="newChecklistInput" placeholder="+ Tambahkan sub-tugas baru (tekan Enter)..."
                                   onkeydown="if(event.key==='Enter'){event.preventDefault(); addNewChecklistItem();}"
                                   class="flex-1 bg-slate-50 dark:bg-slate-800 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/20 focus:border-[#1E5CE6] transition-all">
                            <button type="button" onclick="addNewChecklistItem()"
                                    class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-[#1E5CE6] dark:hover:bg-[#1E5CE6] text-slate-700 dark:text-slate-300 hover:text-white dark:hover:text-white text-xs font-bold transition-all shrink-0 cursor-pointer">
                                + Tambah
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column / Properties Panel (4 of 12 Cols) -->
                <div class="md:col-span-5 lg:col-span-4 p-4 rounded-2xl bg-slate-50/70 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-700/80 space-y-4 h-fit">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200/60 dark:border-slate-700/60 pb-2">Properti Tugas</p>

                    <!-- Status Selector -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Status Papan</label>
                        <select name="status" id="detailStatusSelect"
                                class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/30">
                            <option value="todo">Belum Mulai</option>
                            <option value="in_progress">Sedang Dikerjakan</option>
                            <option value="done">Selesai</option>
                        </select>
                    </div>

                    <!-- Priority Selector -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Tingkat Prioritas</label>
                        <select name="priority" id="detailPrioritySelect"
                                class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/30">
                            <option value="high">🔴 Prioritas Tinggi</option>
                            <option value="medium">🟡 Prioritas Sedang</option>
                            <option value="low">🟢 Prioritas Normal</option>
                        </select>
                    </div>

                    <!-- Due Date Picker -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Tenggat Waktu</label>
                        <input type="date" name="todo_date" id="detailDateInput" required
                               class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#1E5CE6]/30">
                    </div>

                    <!-- Related Document Quick Reference -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Lampiran / Berkas Terkait</label>
                        <a href="index.php?page=documents" target="_blank"
                           class="w-full p-2.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-blue-50/50 dark:hover:bg-slate-700/50 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:text-[#1E5CE6] transition-all flex items-center justify-between">
                            <span class="flex items-center gap-1.5 truncate">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span>Buka Manajemen Dokumen</span>
                            </span>
                            <span class="text-slate-400">&rarr;</span>
                        </a>
                    </div>

                    <!-- Save Button -->
                    <div class="pt-3 border-t border-slate-200/60 dark:border-slate-700/60">
                        <button type="submit" id="btnSaveDetail"
                                class="w-full py-2.5 rounded-xl font-bold text-white bg-[#1E5CE6] hover:bg-blue-600 shadow-md shadow-blue-600/25 transition-transform active:scale-95 text-xs flex items-center justify-center gap-2 cursor-pointer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Simpan Perubahan</span>
                        </button>
                    </div>

                    <!-- Delete Button -->
                    <div>
                        <button type="button" onclick="deleteTaskFromDetail()"
                                class="w-full py-2 rounded-xl text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-xs font-bold transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            <span>Hapus Tugas</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    // -------------------------------------------------------------
    // Linear-Style Task Detail Modal & Checklists State
    // -------------------------------------------------------------
    let currentTaskDetail = null;

    window.openTaskDetailModal = function(cardEl) {
        if (!cardEl) return;
        try {
            const payload = JSON.parse(cardEl.getAttribute('data-task'));
            currentTaskDetail = payload;
            
            document.getElementById('detailFormTodoId').value = payload.id;
            document.getElementById('detailTaskId').textContent = '#' + payload.id;
            document.getElementById('detailTitleInput').value = payload.title || '';
            document.getElementById('detailDescInput').value = payload.description || '';
            document.getElementById('detailStatusSelect').value = payload.status || 'todo';
            document.getElementById('detailPrioritySelect').value = payload.priority || 'medium';
            document.getElementById('detailDateInput').value = payload.todo_date || '';

            // Status badge in header
            const badge = document.getElementById('detailStatusBadge');
            if (badge) {
                badge.textContent = payload.status_label || payload.status;
                if (payload.status === 'done') {
                    badge.className = 'px-2.5 py-1 rounded-full text-[10px] font-black border bg-emerald-50 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800 uppercase tracking-wider';
                } else if (payload.status === 'in_progress') {
                    badge.className = 'px-2.5 py-1 rounded-full text-[10px] font-black border bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800 uppercase tracking-wider';
                } else {
                    badge.className = 'px-2.5 py-1 rounded-full text-[10px] font-black border bg-amber-50 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border-amber-200 dark:border-amber-800 uppercase tracking-wider';
                }
            }

            renderDetailChecklists(payload.checklists || []);

            const modal = document.getElementById('taskDetailModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
            }
        } catch(e) {
            console.error(e);
        }
    };

    window.closeTaskDetailModal = function() {
        const modal = document.getElementById('taskDetailModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
        currentTaskDetail = null;
    };

    function renderDetailChecklists(checklists) {
        const container = document.getElementById('detailChecklistContainer');
        if (!container) return;
        container.innerHTML = '';

        const total = checklists.length;
        const done = checklists.filter(c => c.is_completed == 1 || c.is_completed === true).length;
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;

        const ratioEl = document.getElementById('detailChecklistRatio');
        const pctEl = document.getElementById('detailChecklistPct');
        const barEl = document.getElementById('detailChecklistProgressBar');

        if (ratioEl) ratioEl.textContent = `${done}/${total}`;
        if (pctEl) pctEl.textContent = `${pct}% Selesai`;
        if (barEl) barEl.style.width = `${pct}%`;

        if (total === 0) {
            container.innerHTML = '<p class="text-xs text-slate-400 italic py-2">Belum ada sub-tugas. Tambahkan langkah pengerjaan di bawah.</p>';
            return;
        }

        checklists.forEach(chk => {
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between gap-2.5 p-2 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 hover:border-[#1E5CE6]/50 transition-all group';
            const isChecked = (chk.is_completed == 1);
            item.innerHTML = `
                <label class="flex items-center gap-2.5 min-w-0 flex-1 cursor-pointer select-none">
                    <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="toggleChecklistItem(${chk.id}, this.checked)"
                           class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-[#1E5CE6] focus:ring-[#1E5CE6]/20 cursor-pointer">
                    <span class="text-xs font-semibold ${isChecked ? 'line-through text-slate-400 dark:text-slate-500' : 'text-slate-800 dark:text-slate-200'} truncate transition-colors">
                        ${chk.title}
                    </span>
                </label>
                <button type="button" onclick="deleteChecklistItem(${chk.id})" title="Hapus sub-tugas"
                        class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-rose-600 transition-opacity p-1 text-xs cursor-pointer">
                    ✕
                </button>
            `;
            container.appendChild(item);
        });
    }

    window.addNewChecklistItem = function() {
        if (!currentTaskDetail) return;
        const input = document.getElementById('newChecklistInput');
        const title = input.value.trim();
        if (!title) return;

        const formData = new FormData();
        formData.append('checklist_action', 'add');
        formData.append('todo_id', currentTaskDetail.id);
        formData.append('title', title);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('index.php?page=projects', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                input.value = '';
                if (!currentTaskDetail.checklists) currentTaskDetail.checklists = [];
                currentTaskDetail.checklists.push({ id: res.id, title: res.title, is_completed: 0 });
                renderDetailChecklists(currentTaskDetail.checklists);
                if (typeof showToast === 'function') showToast('Sub-tugas berhasil ditambahkan', 'success');
            }
        });
    };

    window.toggleChecklistItem = function(chkId, isCompleted) {
        if (!currentTaskDetail) return;
        const formData = new FormData();
        formData.append('checklist_action', 'toggle');
        formData.append('checklist_id', chkId);
        formData.append('is_completed', isCompleted ? 1 : 0);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('index.php?page=projects', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                const item = currentTaskDetail.checklists.find(c => c.id == chkId);
                if (item) item.is_completed = isCompleted ? 1 : 0;
                renderDetailChecklists(currentTaskDetail.checklists);
            }
        });
    };

    window.deleteChecklistItem = function(chkId) {
        if (!currentTaskDetail) return;
        const formData = new FormData();
        formData.append('checklist_action', 'delete');
        formData.append('checklist_id', chkId);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        fetch('index.php?page=projects', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                currentTaskDetail.checklists = currentTaskDetail.checklists.filter(c => c.id != chkId);
                renderDetailChecklists(currentTaskDetail.checklists);
            }
        });
    };

    window.saveTaskDetails = function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSaveDetail');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block animate-spin">⏳</span> Menyimpan...';

        const form = document.getElementById('taskDetailForm');
        const formData = new FormData(form);
        formData.append('ajax', '1');

        fetch('index.php?page=projects', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res && res.success) {
                if (typeof showToast === 'function') showToast('Perubahan tugas berhasil disimpan! ✨', 'success');
                setTimeout(() => {
                    closeTaskDetailModal();
                    if (typeof loadPage === 'function') {
                        loadPage('index.php?page=projects', false);
                    } else {
                        window.location.reload();
                    }
                }, 400);
            } else {
                btn.disabled = false;
                btn.innerHTML = orig;
                if (typeof showToast === 'function') showToast('Gagal menyimpan perubahan', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan', 'error');
        });
    };

    window.deleteTaskFromDetail = function() {
        if (!currentTaskDetail) return;
        if (confirm('Hapus tugas ini dari papan Kanban? Semua sub-tugas terkait akan terhapus.')) {
            document.getElementById('deleteTodoId').value = currentTaskDetail.id;
            document.getElementById('deleteTaskForm').submit();
        }
    };

    // -------------------------------------------------------------
    // Kanban Pagination State
    // -------------------------------------------------------------
    const lanes = ['todo', 'in_progress', 'done'];
    const lanePages = { todo: 1, in_progress: 1, done: 1 };
    let kanbanPageSize = 5;
    let kanbanSearchQuery = '';

    window.changeKanbanPageSize = function(size) {
        kanbanPageSize = parseInt(size, 10) || 5;
        lanes.forEach(lane => {
            lanePages[lane] = 1;
            paginateKanbanLane(lane);
        });
    };

    window.filterKanban = function(query) {
        kanbanSearchQuery = (query || '').toLowerCase().trim();
        lanes.forEach(lane => {
            lanePages[lane] = 1;
            paginateKanbanLane(lane);
        });
    };

    window.prevLanePage = function(lane) {
        if (lanePages[lane] > 1) {
            lanePages[lane]--;
            paginateKanbanLane(lane);
        }
    };

    window.nextLanePage = function(lane) {
        lanePages[lane]++;
        paginateKanbanLane(lane);
    };

    function paginateKanbanLane(lane) {
        const wrapper = document.getElementById('lane-cards-' + lane);
        if (!wrapper) return;

        const cards = Array.from(wrapper.querySelectorAll('.kanban-task-card'));
        const emptyEl = document.getElementById('empty-lane-' + lane);
        const indicator = document.getElementById('lane-indicator-' + lane);
        const countText = document.getElementById('lane-count-' + lane);
        const prevBtn = document.getElementById('prev-btn-' + lane);
        const nextBtn = document.getElementById('next-btn-' + lane);

        // Filter cards by search
        const matchedCards = cards.filter(card => {
            if (!kanbanSearchQuery) return true;
            const title = card.dataset.title || '';
            return title.includes(kanbanSearchQuery);
        });

        const totalMatched = matchedCards.length;
        const totalPages = Math.max(1, Math.ceil(totalMatched / kanbanPageSize));

        if (lanePages[lane] > totalPages) {
            lanePages[lane] = totalPages;
        }
        if (lanePages[lane] < 1) {
            lanePages[lane] = 1;
        }
        const curPage = lanePages[lane];

        // Hide all cards first
        cards.forEach(c => c.style.display = 'none');

        if (totalMatched === 0) {
            if (emptyEl) {
                emptyEl.classList.remove('hidden');
                emptyEl.classList.add('flex');
            }
        } else {
            if (emptyEl) {
                emptyEl.classList.add('hidden');
                emptyEl.classList.remove('flex');
            }

            // Slice visible cards for this page
            const startIdx = (curPage - 1) * kanbanPageSize;
            const endIdx = Math.min(startIdx + kanbanPageSize, totalMatched);
            const visibleSlice = matchedCards.slice(startIdx, endIdx);

            visibleSlice.forEach(c => c.style.display = 'block');
        }

        // Update indicators
        if (indicator) {
            indicator.textContent = `Hal ${curPage} / ${totalPages}`;
        }
        if (countText) {
            countText.textContent = `(${totalMatched} tugas)`;
        }

        // Update Prev / Next buttons state
        if (prevBtn) {
            prevBtn.disabled = (curPage <= 1);
        }
        if (nextBtn) {
            nextBtn.disabled = (curPage >= totalPages);
        }
    }

    // Initialize lane pagination
    lanes.forEach(lane => paginateKanbanLane(lane));

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