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
        if (!isAjax()) {
            header("Location: index.php?page=calendar&month=" . $current_month . ($date ? "&date=" . $date : ""));
            exit;
        }
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
        $target_date = $_POST['selected_date'] ?? ($_GET['date'] ?? null);
        if (!isAjax()) {
            header("Location: index.php?page=calendar&month=" . $current_month . ($target_date ? "&date=" . $target_date : ""));
            exit;
        }
    }
}

// Handle Delete Todo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_todo'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $todo_id = (int)$_POST['todo_id'];
        $stmt = $pdo->prepare("DELETE FROM todos WHERE id = ? AND user_id = ?");
        $stmt->execute([$todo_id, $_SESSION['user_id']]);
        logActivity("Menghapus tugas", "todo", $todo_id, ['action' => 'delete']);
        $target_date = $_POST['selected_date'] ?? ($_GET['date'] ?? null);
        if (!isAjax()) {
            header("Location: index.php?page=calendar&month=" . $current_month . ($target_date ? "&date=" . $target_date : ""));
            exit;
        }
    }
}

$selected_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : null;

// All todos for the month
$stmt = $pdo->prepare("SELECT * FROM todos WHERE user_id = ? AND todo_date LIKE ? ORDER BY todo_date ASC, id ASC");
$stmt->execute([$_SESSION['user_id'], $current_month . '%']);
$all_month_todos = $stmt->fetchAll();

// Calculate Monthly Statistics
$total_month = count($all_month_todos);
$done_month = count(array_filter($all_month_todos, fn($t) => $t['completed'] || $t['status'] === 'done'));
$inprogress_month = count(array_filter($all_month_todos, fn($t) => $t['status'] === 'in_progress'));
$todo_month = count(array_filter($all_month_todos, fn($t) => !$t['completed'] && $t['status'] !== 'in_progress'));
$completion_pct = $total_month > 0 ? round(($done_month / $total_month) * 100) : 0;

// Filtered by selected date
$filtered_todos = $selected_date
    ? array_values(array_filter($all_month_todos, fn($t) => $t['todo_date'] === $selected_date))
    : [];

$day_total = count($filtered_todos);
$day_done = count(array_filter($filtered_todos, fn($t) => $t['completed'] || $t['status'] === 'done'));
$day_pct = $day_total > 0 ? round(($day_done / $day_total) * 100) : 0;

$today_date = date('Y-m-d');
?>

<style>
.cal-kpi-card {
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.cal-kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px -6px rgba(0, 0, 0, 0.08);
}
.cal-kpi-card:hover .kpi-icon {
    transform: scale(1.15) rotate(6deg);
}
.kpi-icon {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.cal-cell {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.cal-cell:hover {
    z-index: 10;
    transform: scale(1.015);
    box-shadow: 0 8px 24px -4px rgba(15, 23, 42, 0.12);
}
.dark .cal-cell:hover {
    box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.5);
}

.cal-task-pill {
    transition: transform 0.15s ease;
}
.cal-task-pill:hover {
    transform: translateX(2px);
}

.cal-agenda-item {
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.cal-agenda-item:hover {
    transform: translateX(4px);
}
</style>

<div class="p-4 md:p-8 w-full max-w-7xl mx-auto space-y-6">

    <!-- Header Section -->
    <div class="relative overflow-hidden rounded-3xl p-6 md:p-8 bg-slate-900 text-white shadow-xl shadow-slate-950/20 border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <!-- Ambient lighting glow -->
        <div class="pointer-events-none absolute -right-16 -top-16 w-72 h-72 rounded-full bg-indigo-500/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -left-16 -bottom-16 w-72 h-72 rounded-full bg-violet-600/15 blur-3xl"></div>

        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-950/70 border border-indigo-500/30 text-indigo-300 text-xs font-bold uppercase tracking-widest mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
                Jadwal &amp; Agenda Kerja
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-white tracking-tight mb-1.5">Kalender Produktivitas</h1>
            <p class="text-slate-300 text-sm max-w-xl leading-relaxed font-medium">Kelola jadwal, deadline tugas harian, dan pantau penyelesaian agenda Anda secara real-time.</p>
        </div>

        <div class="relative z-10 flex flex-wrap items-center gap-2.5 flex-shrink-0">
            <!-- Today Jump Button -->
            <a href="?page=calendar&month=<?php echo date('Y-m'); ?>&date=<?php echo $today_date; ?>" 
               class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 border border-white/15 text-white text-xs font-bold transition-all flex items-center gap-2 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                Hari Ini
            </a>

            <!-- Month Controls -->
            <div class="flex items-center rounded-xl bg-slate-800/90 border border-slate-700 p-1 shadow-inner">
                <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' -1 month')); ?>"
                   aria-label="Bulan sebelumnya"
                   title="Bulan sebelumnya"
                   class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-slate-700/80 active:scale-95 transition-all">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
                <span class="px-4 md:px-6 font-extrabold text-white text-sm tracking-tight min-w-[130px] md:min-w-[160px] text-center">
                    <?php echo date('F Y', strtotime($current_month)); ?>
                </span>
                <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' +1 month')); ?>"
                   aria-label="Bulan berikutnya"
                   title="Bulan berikutnya"
                   class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-slate-700/80 active:scale-95 transition-all">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Monthly Summary KPI Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Agenda -->
        <div class="cal-kpi-card bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Tugas</span>
                <div class="kpi-icon w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-900 dark:text-white"><?php echo $total_month; ?></span>
                <span class="text-xs text-slate-400 font-semibold">agenda bulan ini</span>
            </div>
        </div>

        <!-- Card 2: Selesai -->
        <div class="cal-kpi-card bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Selesai</span>
                <div class="kpi-icon w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-100 dark:border-emerald-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400"><?php echo $done_month; ?></span>
                <span class="text-xs font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800"><?php echo $completion_pct; ?>% tuntas</span>
            </div>
        </div>

        <!-- Card 3: Dalam Progress -->
        <div class="cal-kpi-card bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dalam Proses</span>
                <div class="kpi-icon w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-sky-600 dark:text-sky-400"><?php echo $inprogress_month; ?></span>
                <span class="text-xs text-slate-400 font-semibold">sedang dikerjakan</span>
            </div>
        </div>

        <!-- Card 4: Belum Mulai -->
        <div class="cal-kpi-card bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/90 dark:border-slate-800 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Belum Mulai</span>
                <div class="kpi-icon w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-900/50">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-amber-600 dark:text-amber-400"><?php echo $todo_month; ?></span>
                <span class="text-xs text-slate-400 font-semibold">dalam antrean</span>
            </div>
        </div>
    </div>

    <!-- Main Content Area: Calendar Grid + Task List Side Panel -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

        <!-- Calendar Matrix (8 Columns on desktop) -->
        <div class="xl:col-span-8 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
            <!-- Day of Week Header -->
            <div class="grid grid-cols-7 border-b border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-800/80 text-center text-xs font-black uppercase tracking-wider py-3.5">
                <div class="text-rose-600">Min</div>
                <div class="text-slate-600 dark:text-slate-300">Sen</div>
                <div class="text-slate-600 dark:text-slate-300">Sel</div>
                <div class="text-slate-600 dark:text-slate-300">Rab</div>
                <div class="text-slate-600 dark:text-slate-300">Kam</div>
                <div class="text-slate-600 dark:text-slate-300">Jum</div>
                <div class="text-indigo-600 dark:text-indigo-400">Sab</div>
            </div>

            <!-- Calendar Cells -->
            <div class="grid grid-cols-7 bg-slate-200/70 dark:bg-slate-800 gap-[1px]">
                <?php
                $start_date = date('Y-m-01', strtotime($current_month));
                $start_day  = (int)date('w', strtotime($start_date));

                // Previous month padding
                for ($i = 0; $i < $start_day; $i++) {
                    echo '<div class="min-h-[105px] md:min-h-[120px] p-2 bg-slate-50/50 dark:bg-slate-900/40 select-none"></div>';
                }

                $days_in_month = (int)date('t', strtotime($current_month));
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date_str    = date('Y-m-', strtotime($current_month)) . sprintf('%02d', $day);
                    $is_today    = $date_str === $today_date;
                    $is_selected = $date_str === $selected_date;
                    $is_sunday   = (int)date('w', strtotime($date_str)) === 0;
                    $is_saturday = (int)date('w', strtotime($date_str)) === 6;

                    $day_todos = array_values(array_filter($all_month_todos, fn($t) => $t['todo_date'] === $date_str));
                    $pending   = count(array_filter($day_todos, fn($t) => !$t['completed'] && $t['status'] !== 'done'));
                    $done      = count(array_filter($day_todos, fn($t) => $t['completed'] || $t['status'] === 'done'));
                    $total     = count($day_todos);

                    $day_link  = '?page=calendar&month=' . $current_month . '&date=' . $date_str;

                    // Container styling
                    $cell_classes = 'cal-cell min-h-[105px] md:min-h-[120px] p-2 md:p-2.5 flex flex-col justify-between relative group ';
                    if ($is_selected) {
                        $cell_classes .= 'bg-indigo-50/95 dark:bg-indigo-950/70 ring-2 ring-indigo-600 dark:ring-indigo-500 z-10 shadow-sm';
                    } elseif ($is_today) {
                        $cell_classes .= 'bg-indigo-50/40 dark:bg-indigo-950/30 hover:bg-indigo-50/70 dark:hover:bg-indigo-950/50';
                    } elseif ($is_sunday) {
                        $cell_classes .= 'bg-rose-50/25 dark:bg-rose-950/15 hover:bg-white dark:hover:bg-slate-900';
                    } elseif ($is_saturday) {
                        $cell_classes .= 'bg-indigo-50/15 dark:bg-indigo-950/15 hover:bg-white dark:hover:bg-slate-900';
                    } else {
                        $cell_classes .= 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-850';
                    }

                    // Number badge styling
                    $num_classes = 'w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black transition-transform group-hover:scale-110 ';
                    if ($is_selected) {
                        $num_classes .= 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30';
                    } elseif ($is_today) {
                        $num_classes .= 'bg-slate-900 dark:bg-white text-white dark:text-slate-900 shadow-sm ring-2 ring-indigo-400';
                    } elseif ($is_sunday) {
                        $num_classes .= 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40';
                    } elseif ($is_saturday) {
                        $num_classes .= 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40';
                    } else {
                        $num_classes .= 'text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800';
                    }

                    echo '<a href="' . $day_link . '" class="' . $cell_classes . '">';
                    
                    // Top Row: Number & Today label
                    echo '<div class="flex items-center justify-between w-full">';
                    echo '<span class="' . $num_classes . '">' . $day . '</span>';
                    if ($is_today) {
                        echo '<span class="hidden md:inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-tight text-indigo-700 dark:text-indigo-300 bg-indigo-100/90 dark:bg-indigo-900/60 px-2 py-0.5 rounded-full"><span class="w-1 h-1 rounded-full bg-indigo-500 animate-ping"></span>Hari Ini</span>';
                    }
                    echo '</div>';

                    // Tasks Preview Inside Cell
                    echo '<div class="space-y-1 my-1 w-full overflow-hidden">';
                    if ($total > 0) {
                        $preview_todos = array_slice($day_todos, 0, 2);
                        foreach ($preview_todos as $pt) {
                            $is_pt_done = $pt['completed'] || $pt['status'] === 'done';
                            $pt_color = $is_pt_done ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' : ($pt['status'] === 'in_progress' ? 'bg-sky-100 dark:bg-sky-950/60 text-sky-800 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700');
                            $pt_dot   = $is_pt_done ? 'bg-emerald-500' : ($pt['status'] === 'in_progress' ? 'bg-sky-500' : 'bg-slate-400');
                            
                            echo '<div class="cal-task-pill text-[10px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1.5 truncate ' . $pt_color . '">';
                            echo '<span class="w-1.5 h-1.5 rounded-full flex-shrink-0 ' . $pt_dot . '"></span>';
                            echo '<span class="truncate ' . ($is_pt_done ? 'line-through opacity-70' : '') . '">' . htmlspecialchars($pt['title']) . '</span>';
                            echo '</div>';
                        }
                        if ($total > 2) {
                            echo '<div class="text-[9px] font-extrabold text-slate-500 dark:text-slate-400 px-1">+' . ($total - 2) . ' lainnya</div>';
                        }
                    }
                    echo '</div>';

                    // Bottom: Mini count pills
                    echo '<div class="flex items-center gap-1">';
                    if ($total > 0) {
                        if ($pending === 0 && $done > 0) {
                            echo '<span class="text-[9px] font-extrabold px-1.5 py-0.2 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/60 dark:border-emerald-800/60">✓ ' . $total . '</span>';
                        } else {
                            echo '<span class="text-[9px] font-bold px-1.5 py-0.2 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">' . $total . ' tugas</span>';
                        }
                    }
                    echo '</div>';

                    echo '</a>';
                }

                // Next month padding to fill complete grid
                $total_cells = $start_day + $days_in_month;
                $remaining   = (7 - ($total_cells % 7)) % 7;
                for ($i = 0; $i < $remaining; $i++) {
                    echo '<div class="min-h-[105px] md:min-h-[120px] p-2 bg-slate-50/50 dark:bg-slate-900/40 select-none"></div>';
                }
                ?>
            </div>
        </div>

        <!-- Right Side Panel: Day Agenda & Tasks (4 Columns on desktop) -->
        <div class="xl:col-span-4 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 flex flex-col min-h-[580px]">
            
            <!-- Panel Header -->
            <div class="border-b border-slate-100 dark:border-slate-800 pb-5 mb-5">
                <?php if ($selected_date): ?>
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <span class="inline-block text-[10px] font-black uppercase tracking-wider text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/70 border border-indigo-100 dark:border-indigo-800/60 px-3 py-1 rounded-full mb-1.5">
                                <?php echo date('l', strtotime($selected_date)); ?>
                            </span>
                            <h2 class="text-xl font-extrabold text-slate-900 dark:text-white"><?php echo date('d F Y', strtotime($selected_date)); ?></h2>
                        </div>
                        <a href="?page=calendar&month=<?php echo $current_month; ?>" 
                           title="Reset filter tanggal"
                           class="text-xs font-bold text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors p-1 flex items-center gap-1">
                            ✕ Tutup
                        </a>
                    </div>

                    <!-- Progress Bar for the day -->
                    <?php if ($day_total > 0): ?>
                    <div class="mt-3">
                        <div class="flex justify-between text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">
                            <span>Kemajuan Hari Ini</span>
                            <span class="text-indigo-600 dark:text-indigo-400 font-extrabold"><?php echo $day_done; ?> / <?php echo $day_total; ?> (<?php echo $day_pct; ?>%)</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-600 rounded-full transition-all duration-500" style="width: <?php echo $day_pct; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/30 flex-shrink-0">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900 dark:text-white">Agenda Harian</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">Pilih tanggal di kalender untuk melihat detail</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Add Form -->
            <?php if ($selected_date): ?>
            <form method="POST" class="mb-5 flex-shrink-0">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="add_todo" value="1">
                <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                <div class="flex gap-2">
                    <input type="text" name="title" required placeholder="Tulis agenda baru..." aria-label="Tambah agenda untuk tanggal ini"
                           class="flex-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-800 transition-all placeholder:text-slate-400">
                    <button type="submit" aria-label="Simpan Tugas" class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/30 transition-transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <!-- Task List Container -->
            <div class="flex-1 overflow-y-auto space-y-2.5 custom-scrollbar pr-1">
                <?php if (!$selected_date): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center py-12 px-4">
                        <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-4 border border-indigo-100 dark:border-indigo-900/40">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-200 mb-1">Pilih Tanggal</h3>
                        <p class="text-xs text-slate-400 max-w-[220px] leading-relaxed font-medium">Klik tanggal manapun pada matriks kalender untuk mengelola agenda dan tugas spesifik hari tersebut.</p>
                        <a href="?page=calendar&month=<?php echo date('Y-m'); ?>&date=<?php echo $today_date; ?>"
                           class="mt-4 px-4 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 font-bold text-xs transition-colors">
                            Buka Agenda Hari Ini &rarr;
                        </a>
                    </div>

                <?php elseif (empty($filtered_todos)): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center py-12 px-4">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-4 border border-emerald-100 dark:border-emerald-900/40">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-200 mb-1">Belum Ada Agenda</h3>
                        <p class="text-xs text-slate-400 max-w-[220px] leading-relaxed font-medium">Gunakan kolom input di atas untuk membuat jadwal tugas untuk tanggal ini.</p>
                    </div>

                <?php else: ?>
                    <?php foreach ($filtered_todos as $todo): 
                        $status = $todo['status'] ?: 'todo';
                        $is_done = $status === 'done' || $todo['completed'];
                        
                        $card_bg = $is_done 
                            ? 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-200/80 dark:border-emerald-800/50' 
                            : ($status === 'in_progress' ? 'bg-sky-50/60 dark:bg-sky-950/30 border-sky-200/80 dark:border-sky-800/50' : 'bg-slate-50 dark:bg-slate-800/70 border-slate-200/80 dark:border-slate-700');
                        
                        $badge_class = $is_done 
                            ? 'text-emerald-800 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/60' 
                            : ($status === 'in_progress' ? 'text-sky-800 dark:text-sky-300 bg-sky-100 dark:bg-sky-900/60' : 'text-slate-700 dark:text-slate-300 bg-slate-200 dark:bg-slate-700');
                        
                        $status_label = $is_done ? 'Selesai' : ($status === 'in_progress' ? 'Dalam Proses' : 'Belum Mulai');
                    ?>
                    <div class="cal-agenda-item p-3.5 rounded-2xl border <?php echo $card_bg; ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md <?php echo $badge_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </div>
                                <p class="text-xs font-bold leading-snug <?php echo $is_done ? 'text-slate-400 dark:text-slate-500 line-through' : 'text-slate-900 dark:text-white'; ?>">
                                    <?php echo htmlspecialchars($todo['title']); ?>
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <?php if ($status !== 'todo'): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="status" value="todo">
                                    <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
                                    <button type="submit" title="Ubah ke Belum Mulai" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white active:scale-95 transition-all">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if ($status !== 'in_progress'): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
                                    <button type="submit" title="Ubah ke Proses" class="w-7 h-7 flex items-center justify-center rounded-lg bg-sky-500 hover:bg-sky-600 text-white active:scale-95 transition-all shadow-sm">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <?php if (!$is_done): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="status" value="done">
                                    <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
                                    <button type="submit" title="Tandai Selesai" class="w-7 h-7 flex items-center justify-center rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white active:scale-95 transition-all shadow-sm">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" onsubmit="return confirm('Hapus tugas ini?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="delete_todo" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
                                    <button type="submit" title="Hapus Tugas" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 active:scale-95 transition-colors">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
