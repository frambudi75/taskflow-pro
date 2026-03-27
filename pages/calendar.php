<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/calendar.php
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Handle Add Todo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_todo'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $title = trim($_POST['title']);
        $date  = $_POST['date'];
        if ($title) {
            $stmt = $pdo->prepare("INSERT INTO todos (user_id, title, todo_date, completed, status) VALUES (?, ?, ?, 0, 'todo')");
            $stmt->execute([$_SESSION['user_id'], $title, $date]);
            $todo_id = $pdo->lastInsertId();
            
            // Log Activity with Metadata
            logActivity("Menambahkan tugas baru: " . $title, "todo", $todo_id, ['date' => $date]);
            
            // Push Notification Integration
            $usrStmt = $pdo->prepare("SELECT email, telegram_chat_id, display_name FROM users WHERE id = ?");
            $usrStmt->execute([$_SESSION['user_id']]);
            $userDt = $usrStmt->fetch();
            
            if ($userDt) {
                $msgBody = "Halo " . $userDt['display_name'] . ",\n\nTugas baru telah ditambahkan ke Kalender Kerja Anda:\n\n*Judul:* " . $title . "\n*Tanggal:* " . \date('d M Y', \strtotime($date)) . "\n\nTerus produktif!\nTaskFlow Pro";
                
                // Telegram Notification
                if (!empty($userDt['telegram_chat_id'])) {
                    sendTelegramNotif($userDt['telegram_chat_id'], nl2br($msgBody));
                }
                
                // Email Notification
                if (!empty($userDt['email'])) {
                    sendEmailNotif($userDt['email'], "Tugas Baru: " . $title, nl2br($msgBody));
                }
            }
        }
        header("Location: index.php?page=calendar&month=" . $current_month);
        exit;
    }
}

// Handle Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $todo_id = (int)$_POST['todo_id'];
        $new_status = $_POST['status'];
        $is_completed = ($new_status === 'done') ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE todos SET status = ?, completed = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $is_completed, $todo_id, $_SESSION['user_id']]);
        
        logActivity("Mengubah status tugas menjadi " . strtoupper($new_status), "todo", $todo_id, ['new_status' => $new_status]);
        header("Location: index.php?page=calendar&month=" . $current_month . ($selected_date ? "&date=".$selected_date : ""));
        exit;
    }
}

// Handle Delete Todo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_todo'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $todo_id = (int)$_POST['todo_id'];
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ? AND user_id = ?");
        $stmt->execute([$todo_id, $_SESSION['user_id']]);
        logActivity("Menghapus tugas", "todo", $todo_id, ['action' => 'delete']);
        header("Location: index.php?page=calendar&month=" . $current_month);
        exit;
    }
}

$selected_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : null;

// All todos for the month
$stmt = $pdo->prepare("SELECT * FROM todos WHERE user_id = ? AND todo_date LIKE ? ORDER BY todo_date ASC");
$stmt->execute([$_SESSION['user_id'], $current_month . '%']);
$all_month_todos = $stmt->fetchAll();

// Filtered by selected date
$filtered_todos = $selected_date
    ? array_values(array_filter($all_month_todos, fn($t) => $t['todo_date'] === $selected_date))
    : [];
?>

<div class="p-5 md:p-8 max-w-7xl mx-auto">

    <!-- ===== HERO HEADER ===== -->
    <div class="rounded-3xl p-7 md:p-10 mb-7 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5"
         style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#312e81 100%)">
        <div class="absolute inset-0 pointer-events-none"
             style="background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:40px 40px"></div>
        <div class="absolute left-0 bottom-0 w-72 h-72 pointer-events-none"
             style="background:radial-gradient(circle,rgba(99,102,241,0.2) 0%,transparent 70%);transform:translate(-30%,30%)"></div>
        <div class="relative z-10">
            <div class="text-indigo-300 text-[10px] font-black uppercase tracking-widest mb-2">Produktivitas Harian</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">📅 Kalender Kerja</h1>
            <p class="text-indigo-300 text-sm font-medium">Klik tanggal untuk melihat &amp; mengelola tugas harian.</p>
        </div>
        <!-- Month navigator -->
        <div class="relative z-10 flex items-center gap-1 flex-shrink-0 rounded-2xl p-1.5"
             style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15)">
            <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' -1 month')); ?>"
               class="w-9 h-9 flex items-center justify-center rounded-xl text-white transition-colors"
               style="hover:background:rgba(255,255,255,0.15)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <span class="px-4 font-black text-white text-sm uppercase tracking-tight min-w-[150px] text-center">
                <?php echo date('F Y', strtotime($current_month)); ?>
            </span>
            <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' +1 month')); ?>"
               class="w-9 h-9 flex items-center justify-center rounded-xl text-white transition-colors">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">

        <!-- ===== CALENDAR GRID ===== -->
        <div class="xl:col-span-8 bg-white rounded-[2.5rem] border border-stone-100 shadow-2xl shadow-stone-200/50 overflow-hidden">
            <!-- Day labels -->
            <div class="grid grid-cols-7 border-b border-stone-100" style="background:#fafafa">
                <?php foreach(['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $i => $day): ?>
                    <div class="py-4 text-center text-[10px] font-black uppercase tracking-[0.2em]"
                         style="color:<?php echo $i === 0 ? '#ef4444' : ($i === 6 ? '#6366f1' : '#94a3b8'); ?>">
                        <?php echo $day; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Calendar days -->
            <div class="grid grid-cols-7">
                <?php
                $start_date = date('Y-m-01', strtotime($current_month));
                $start_day  = date('w', strtotime($start_date));

                for ($i = 0; $i < $start_day; $i++) {
                    echo '<div class="h-24 md:h-32 p-2 border-r border-b border-stone-50" style="background:#fafafa"></div>';
                }

                $days_in_month = date('t', strtotime($current_month));
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date_str    = date('Y-m-', strtotime($current_month)) . sprintf('%02d', $day);
                    $is_today    = $date_str === date('Y-m-d');
                    $is_selected = $date_str === $selected_date;
                    $is_sunday   = date('w', strtotime($date_str)) == 0;
                    $is_saturday = date('w', strtotime($date_str)) == 6;

                    $day_todos = array_filter($all_month_todos, fn($t) => $t['todo_date'] === $date_str);
                    $pending   = count(array_filter($day_todos, fn($t) => !$t['completed']));
                    $done      = count(array_filter($day_todos, fn($t) => $t['completed']));
                    $total     = count($day_todos);

                    $badge = '';
                    if ($done > 0 && $pending === 0) {
                        $badge = '<div style="margin-top:6px"><span style="font-size:9px;font-weight:800;background:#dcfce7;color:#15803d;padding:2px 7px;border-radius:100px">✓ ' . $total . '</span></div>';
                    } elseif ($pending > 0) {
                        $badge = '<div style="margin-top:6px"><span style="font-size:9px;font-weight:800;background:#fef3c7;color:#d97706;padding:2px 7px;border-radius:100px">' . $pending . ' tugas</span></div>';
                    }

                    $day_link  = '?page=calendar&month=' . $current_month . '&date=' . $date_str;
                    $cell_bg   = $is_selected ? 'background:#eef2ff;border-color:#c7d2fe' : '';
                    $num_bg    = $is_selected ? 'background:#6366f1;color:#fff;border-radius:12px'
                               : ($is_today   ? 'background:#0f172a;color:#fff;border-radius:12px'
                               : 'color:' . ($is_sunday ? '#ef4444' : ($is_saturday ? '#6366f1' : '#374151')));

                    echo '
                    <a href="' . $day_link . '" class="h-24 md:h-32 p-2 md:p-3 border-r border-b border-stone-100 flex flex-col items-start transition-all cursor-pointer hover:bg-slate-50" style="' . $cell_bg . '">
                        <span class="text-sm font-black w-8 h-8 flex items-center justify-center transition-all" style="' . $num_bg . '">' . $day . '</span>
                        ' . $badge . '
                    </a>';
                }
                ?>
            </div>
        </div>

        <!-- ===== TODO PANEL ===== -->
        <div class="xl:col-span-4 flex flex-col gap-6">
            <div class="bg-white rounded-[2.5rem] border border-stone-100 shadow-2xl shadow-stone-200/50 p-7 md:p-9 flex flex-col" style="max-height:640px">
                <!-- Header -->
                <div class="mb-6 flex-shrink-0">
                    <?php if ($selected_date): ?>
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <div>
                                <div class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-1">
                                    <?php echo date('l', strtotime($selected_date)); ?>
                                </div>
                                <h3 class="text-2xl font-black text-slate-900"><?php echo date('d F Y', strtotime($selected_date)); ?></h3>
                                <p class="text-slate-400 text-xs font-bold mt-0.5"><?php echo count($filtered_todos); ?> tugas tersimpan</p>
                            </div>
                            <a href="?page=calendar&month=<?php echo $current_month; ?>"
                               class="text-[10px] font-black text-indigo-500 hover:text-indigo-700 uppercase tracking-widest mt-1 flex-shrink-0">← Semua</a>
                        </div>
                    <?php else: ?>
                        <div class="w-12 h-12 rounded-2xl mb-4 flex items-center justify-center" style="background:linear-gradient(135deg,#6366f1,#4f46e5)">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <h3 class="text-xl font-black text-slate-900 mb-1">Daftar Tugas</h3>
                        <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">Klik tanggal untuk melihat tugas</p>
                    <?php endif; ?>
                </div>

                <!-- Add Form -->
                <?php if ($selected_date): ?>
                <form method="POST" class="mb-5 flex-shrink-0">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="add_todo" value="1">
                    <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                    <div class="flex gap-2">
                        <input type="text" name="title" required placeholder="Tambah tugas..."
                               class="flex-1 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-bold focus:outline-none text-slate-900 transition-all"
                               style="focus:border-color:#6366f1">
                        <button type="submit" class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 transition-all active:scale-95"
                                style="background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 4px 16px rgba(99,102,241,0.35)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- Todo list -->
                <div class="flex-1 overflow-auto space-y-2" style="scrollbar-width:thin">
                    <?php if (!$selected_date): ?>
                        <div class="h-full flex flex-col items-center justify-center text-center py-12">
                            <div style="width:64px;height:64px;border-radius:20px;background:#f8fafc;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#c7d2fe" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <p class="text-slate-400 text-sm font-bold">Pilih tanggal di kalender<br>untuk melihat tugasnya</p>
                        </div>
                    <?php elseif (empty($filtered_todos)): ?>
                        <div class="h-full flex flex-col items-center justify-center text-center py-12">
                            <div style="width:64px;height:64px;border-radius:20px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#86efac" stroke-width="1.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            </div>
                            <p class="text-slate-400 text-sm font-bold">Belum ada tugas.<br><span style="color:#6366f1">Tambah di atas!</span></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($filtered_todos as $todo): 
                            $status = $todo['status'] ?: 'todo';
                            $status_color = $status === 'done' ? 'bg-emerald-500' : ($status === 'in_progress' ? 'bg-blue-500' : 'bg-slate-300');
                            $status_bg = $status === 'done' ? 'background:#f0fdf4;border-color:#bbf7d0' : ($status === 'in_progress' ? 'background:#eff6ff;border-color:#bfdbfe' : 'background:#fafafa;border-color:#f1f5f9');
                        ?>
                        <div class="group flex items-center gap-3 p-3 rounded-2xl border transition-all" style="<?php echo $status_bg; ?>">
                            <!-- Status Indicator -->
                            <div class="w-1.5 h-8 rounded-full <?php echo $status_color; ?> flex-shrink-0"></div>
                            
                            <!-- Text -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold truncate <?php echo $status === 'done' ? 'text-slate-400 line-through' : 'text-slate-900'; ?>">
                                    <?php echo htmlspecialchars($todo['title']); ?>
                                </p>
                                <div class="text-[9px] font-black uppercase tracking-widest mt-0.5" style="color:<?php echo $status==='done'?'#10b981':($status==='in_progress'?'#3b82f6':'#94a3b8'); ?>">
                                    <?php echo $status === 'done' ? 'Selesai' : ($status === 'in_progress' ? 'Progress' : 'Belum Mulai'); ?>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1">
                                <?php if ($status !== 'todo'): ?>
                                <form method="POST" class="flex-shrink-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="status" value="todo">
                                    <button type="submit" title="Set Belum Mulai" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-slate-100 text-slate-400 hover:text-slate-600 transition-all">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($status !== 'in_progress'): ?>
                                <form method="POST" class="flex-shrink-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" title="Set Progress" class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-500 text-white hover:bg-blue-600 transition-all shadow-sm">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($status !== 'done'): ?>
                                <form method="POST" class="flex-shrink-0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="status" value="done">
                                    <button type="submit" title="Set Selesai" class="w-7 h-7 flex items-center justify-center rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition-all shadow-sm">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <!-- Delete -->
                                <form method="POST" class="flex-shrink-0 ml-1" onsubmit="return confirm('Hapus tugas ini?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="delete_todo" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-300 hover:text-red-500 transition-all">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
