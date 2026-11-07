<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/calendar.php - Modern & Clean Executive Productivity Calendar
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$today_date    = date('Y-m-d');
$month_today   = date('Y-m');

// Handle Add Todo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_todo'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $title = trim($_POST['title']);
        $date  = $_POST['date'] ?: $today_date;
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
                
                if (!empty($userDt['telegram_chat_id'])) {
                    sendTelegramNotif($userDt['telegram_chat_id'], nl2br($msgBody));
                }
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

// Determine active selected date: default to today if in current month, or 1st of month
if (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
    $selected_date = $_GET['date'];
} elseif ($current_month === $month_today) {
    $selected_date = $today_date;
} else {
    $selected_date = $current_month . '-01';
}

// All todos for the month
$stmt = $pdo->prepare("SELECT * FROM todos WHERE user_id = ? AND todo_date LIKE ? ORDER BY todo_date ASC, id ASC");
$stmt->execute([$_SESSION['user_id'], $current_month . '%']);
$all_month_todos = $stmt->fetchAll();

// Monthly statistics
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
$day_pending = $day_total - $day_done;
$day_pct = $day_total > 0 ? round(($day_done / $day_total) * 100) : 0;
?>

<style>
/* Clean & Crisp SaaS Frosted Glass Calendar System */
.cal-white-card {
    background: rgba(255, 255, 255, 0.76);
    backdrop-filter: blur(18px) saturate(180%);
    -webkit-backdrop-filter: blur(18px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    box-shadow: 0 4px 18px -2px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.cal-white-card:hover {
    background: rgba(255, 255, 255, 0.94);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 12px 28px -4px rgba(79, 70, 229, 0.12), inset 0 1px 0 #ffffff;
    transform: translateY(-2px);
}

.cal-glass-panel {
    background: rgba(255, 255, 255, 0.76);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.02), inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.cal-cell {
    background: rgba(255, 255, 255, 0.85);
    transition: all 0.16s ease;
}
.cal-cell:hover {
    background: rgba(255, 255, 255, 0.98);
    z-index: 5;
}

.cal-task-pill {
    transition: transform 0.15s ease;
}
.cal-task-pill:hover {
    transform: translateX(2px);
}

.cal-agenda-row {
    transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.cal-agenda-row:hover {
    transform: translateY(-1.5px);
    box-shadow: 0 4px 12px -2px rgba(15, 23, 42, 0.06);
}
</style>

<div class="p-4 md:p-7 pb-20 md:pb-24 w-full max-w-[1600px] mx-auto space-y-6">

    <!-- Header Section (Executive Clean SaaS Frosted Glass Header) -->
    <div class="rounded-3xl p-6 md:p-7 cal-glass-panel relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Jadwal &amp; Agenda Kerja
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Kalender Produktivitas</h1>
            <p class="text-slate-500 text-xs md:text-sm max-w-xl leading-relaxed font-medium">
                Pantau seluruh agenda, tenggat waktu harian, dan rencanakan aktivitas kerja Anda.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 flex-shrink-0">
            <!-- Today Jump Button -->
            <a href="?page=calendar&month=<?php echo date('Y-m'); ?>&date=<?php echo $today_date; ?>" 
               class="px-4 py-2.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 active:scale-95 border border-indigo-200/80 text-indigo-700 text-xs font-bold transition-all flex items-center gap-2 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                Hari Ini
            </a>

            <!-- Month Controls -->
            <div class="flex items-center rounded-xl bg-slate-50 border border-slate-200 p-1 shadow-inner">
                <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' -1 month')); ?>"
                   aria-label="Bulan sebelumnya"
                   title="Bulan sebelumnya"
                   class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white active:scale-95 transition-all">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </a>
                <span class="px-4 md:px-6 font-extrabold text-slate-900 text-sm tracking-tight min-w-[140px] text-center">
                    <?php echo date('F Y', strtotime($current_month)); ?>
                </span>
                <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' +1 month')); ?>"
                   aria-label="Bulan berikutnya"
                   title="Bulan berikutnya"
                   class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-600 hover:text-slate-900 hover:bg-white active:scale-95 transition-all">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Monthly Summary KPI Stats (Clean White Surface Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Agenda -->
        <div class="cal-white-card p-5 border-t-[3px] border-t-indigo-500">
            <div class="flex items-center justify-between mb-2.5">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Total Tugas</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-none"><?php echo $total_month; ?></span>
                <span class="text-xs text-slate-400 font-semibold">agenda bulan ini</span>
            </div>
        </div>

        <!-- Card 2: Selesai -->
        <div class="cal-white-card p-5 border-t-[3px] border-t-emerald-500">
            <div class="flex items-center justify-between mb-2.5">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Selesai</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-emerald-600 tracking-tight leading-none"><?php echo $done_month; ?></span>
                <span class="text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200"><?php echo $completion_pct; ?>% tercapai</span>
            </div>
        </div>

        <!-- Card 3: Dalam Progress -->
        <div class="cal-white-card p-5 border-t-[3px] border-t-sky-500">
            <div class="flex items-center justify-between mb-2.5">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Dalam Proses</span>
                <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center border border-sky-100">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-sky-600 tracking-tight leading-none"><?php echo $inprogress_month; ?></span>
                <span class="text-xs text-slate-400 font-semibold">sedang berjalan</span>
            </div>
        </div>

        <!-- Card 4: Belum Mulai -->
        <div class="cal-white-card p-5 border-t-[3px] border-t-amber-500">
            <div class="flex items-center justify-between mb-2.5">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Belum Mulai</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-amber-600 tracking-tight leading-none"><?php echo $todo_month; ?></span>
                <span class="text-xs text-slate-400 font-semibold">dalam antrean</span>
            </div>
        </div>
    </div>

    <!-- Main Content Area: Calendar Grid + Task List Side Panel -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

        <!-- Calendar Matrix (8 Columns on desktop) -->
        <div class="xl:col-span-8 cal-glass-panel rounded-3xl overflow-hidden flex flex-col">
            
            <!-- Calendar Top Info Bar -->
            <div class="px-5 py-3.5 border-b border-slate-200/80 flex items-center justify-between bg-slate-50/60">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-slate-700">Indikator:</span>
                    <div class="flex items-center gap-3 text-[11px] font-medium text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Selesai</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-sky-500"></span>Dalam Proses</span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-indigo-500"></span>Pending</span>
                    </div>
                </div>
                <div class="text-[11px] text-slate-400 font-semibold hidden sm:block">
                    Klik tanggal untuk membuka agenda
                </div>
            </div>

            <!-- Day of Week Header -->
            <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/90 text-center text-xs font-black uppercase tracking-wider py-3">
                <div class="text-rose-600">Min</div>
                <div class="text-slate-600">Sen</div>
                <div class="text-slate-600">Sel</div>
                <div class="text-slate-600">Rab</div>
                <div class="text-slate-600">Kam</div>
                <div class="text-slate-600">Jum</div>
                <div class="text-indigo-600">Sab</div>
            </div>

            <!-- Calendar Cells Grid -->
            <div class="grid grid-cols-7 bg-slate-200/80 gap-[1px]">
                <?php
                $start_date = date('Y-m-01', strtotime($current_month));
                $start_day  = (int)date('w', strtotime($start_date));
                $days_in_month = (int)date('t', strtotime($current_month));

                // Previous month timestamps & days count
                $prev_month_ts = strtotime($current_month . ' -1 month');
                $days_in_prev_month = (int)date('t', $prev_month_ts);
                $prev_month_str = date('Y-m', $prev_month_ts);

                // 1. Previous month leading padding cells (complete with dates)
                for ($i = 0; $i < $start_day; $i++) {
                    $p_day = $days_in_prev_month - ($start_day - 1 - $i);
                    $p_date_str = $prev_month_str . '-' . sprintf('%02d', $p_day);
                    echo '<a href="?page=calendar&month=' . $prev_month_str . '&date=' . $p_date_str . '" class="cal-cell min-h-[85px] md:min-h-[96px] p-2 flex flex-col justify-between opacity-35 hover:opacity-75 bg-slate-50/70 transition-all select-none">';
                    echo '<div class="flex items-center justify-between w-full">';
                    echo '<span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-slate-400">' . $p_day . '</span>';
                    echo '</div>';
                    echo '<div class="text-[9px] text-slate-400 font-semibold truncate">' . date('M', $prev_month_ts) . '</div>';
                    echo '</a>';
                }

                // 2. Current month active days
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date_str    = date('Y-m-', strtotime($current_month)) . sprintf('%02d', $day);
                    $is_today    = $date_str === $today_date;
                    $is_selected = $date_str === $selected_date;
                    $is_sunday   = (int)date('w', strtotime($date_str)) === 0;
                    $is_saturday = (int)date('w', strtotime($date_str)) === 6;

                    $day_todos = array_values(array_filter($all_month_todos, fn($t) => $t['todo_date'] === $date_str));
                    $pending   = count(array_filter($day_todos, fn($t) => !$t['completed'] && $t['status'] !== 'done'));
                    $done      = count(array_filter($day_todos, fn($t) => $t['completed'] || $t['status'] === 'done'));
                    $inprog    = count(array_filter($day_todos, fn($t) => $t['status'] === 'in_progress'));
                    $total     = count($day_todos);

                    $day_link  = '?page=calendar&month=' . $current_month . '&date=' . $date_str;

                    // Container styling (Clean Crisp White Surface)
                    $cell_classes = 'cal-cell min-h-[85px] md:min-h-[96px] p-2 md:p-2.5 flex flex-col justify-between relative group ';
                    if ($is_selected) {
                        $cell_classes .= 'bg-indigo-50/90 ring-2 ring-indigo-600 z-10 shadow-sm';
                    } elseif ($is_today) {
                        $cell_classes .= 'bg-indigo-50/40 hover:bg-indigo-50/70';
                    } elseif ($is_sunday) {
                        $cell_classes .= 'bg-rose-50/20 hover:bg-slate-50';
                    } else {
                        $cell_classes .= 'bg-white hover:bg-slate-50';
                    }

                    // Number badge styling
                    $num_classes = 'w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black transition-transform group-hover:scale-105 ';
                    if ($is_selected) {
                        $num_classes .= 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30';
                    } elseif ($is_today) {
                        $num_classes .= 'bg-slate-900 text-white shadow-sm ring-2 ring-indigo-400';
                    } elseif ($is_sunday) {
                        $num_classes .= 'text-rose-600 bg-rose-50';
                    } elseif ($is_saturday) {
                        $num_classes .= 'text-indigo-600 bg-indigo-50';
                    } else {
                        $num_classes .= 'text-slate-700 bg-slate-100';
                    }

                    echo '<a href="' . $day_link . '" class="' . $cell_classes . '">';
                    
                    // Top Row: Number & Status Indicators (Dots)
                    echo '<div class="flex items-center justify-between w-full">';
                    echo '<span class="' . $num_classes . '">' . $day . '</span>';
                    
                    // Status indicator dot cluster
                    echo '<div class="flex items-center gap-1">';
                    if ($is_today) {
                        echo '<span class="hidden md:inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-tight text-indigo-700 bg-indigo-100/90 px-1.5 py-0.5 rounded-full"><span class="w-1 h-1 rounded-full bg-indigo-500 animate-ping"></span>Hari Ini</span>';
                    }
                    if ($total > 0) {
                        if ($done > 0) echo '<span class="w-2 h-2 rounded-full bg-emerald-500" title="' . $done . ' Selesai"></span>';
                        if ($inprog > 0) echo '<span class="w-2 h-2 rounded-full bg-sky-500" title="' . $inprog . ' Dalam Proses"></span>';
                        if ($pending > 0) echo '<span class="w-2 h-2 rounded-full bg-indigo-500" title="' . $pending . ' Belum Selesai"></span>';
                    }
                    echo '</div>';
                    echo '</div>';

                    // Tasks Preview Inside Cell
                    echo '<div class="space-y-1 my-1 w-full overflow-hidden">';
                    if ($total > 0) {
                        $preview_todos = array_slice($day_todos, 0, 2);
                        foreach ($preview_todos as $pt) {
                            $is_pt_done = $pt['completed'] || $pt['status'] === 'done';
                            $pt_color = $is_pt_done 
                                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' 
                                : ($pt['status'] === 'in_progress' ? 'bg-sky-50 text-sky-800 border border-sky-200' : 'bg-slate-50 text-slate-800 border border-slate-200');
                            $pt_dot   = $is_pt_done ? 'bg-emerald-500' : ($pt['status'] === 'in_progress' ? 'bg-sky-500' : 'bg-slate-400');
                            
                            echo '<div class="cal-task-pill text-[10px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1.5 truncate ' . $pt_color . '">';
                            echo '<span class="w-1.5 h-1.5 rounded-full flex-shrink-0 ' . $pt_dot . '"></span>';
                            echo '<span class="truncate ' . ($is_pt_done ? 'line-through opacity-70' : '') . '">' . htmlspecialchars($pt['title']) . '</span>';
                            echo '</div>';
                        }
                        if ($total > 2) {
                            echo '<div class="text-[9px] font-extrabold text-slate-500 px-1">+' . ($total - 2) . ' lainnya</div>';
                        }
                    }
                    echo '</div>';

                    // Bottom: Mini count pills
                    echo '<div class="flex items-center gap-1">';
                    if ($total > 0) {
                        if ($pending === 0 && $done > 0) {
                            echo '<span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">✓ ' . $total . ' Selesai</span>';
                        } else {
                            echo '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200/60">' . $total . ' agenda</span>';
                        }
                    }
                    echo '</div>';

                    echo '</a>';
                }

                // 3. Next month trailing padding cells (fills out to 35 or 42 cells seamlessly)
                $total_rendered = $start_day + $days_in_month;
                $target_cells   = ($total_rendered <= 35) ? 35 : 42;
                $remaining      = $target_cells - $total_rendered;

                $next_month_ts  = strtotime($current_month . ' +1 month');
                $next_month_str = date('Y-m', $next_month_ts);

                for ($i = 1; $i <= $remaining; $i++) {
                    $n_date_str = $next_month_str . '-' . sprintf('%02d', $i);
                    echo '<a href="?page=calendar&month=' . $next_month_str . '&date=' . $n_date_str . '" class="cal-cell min-h-[85px] md:min-h-[96px] p-2 flex flex-col justify-between opacity-35 hover:opacity-75 bg-slate-50/70 transition-all select-none">';
                    echo '<div class="flex items-center justify-between w-full">';
                    echo '<span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-slate-400">' . $i . '</span>';
                    echo '</div>';
                    echo '<div class="text-[9px] text-slate-400 font-semibold truncate">' . date('M', $next_month_ts) . '</div>';
                    echo '</a>';
                }
                ?>
            </div>
        </div>

        <!-- Right Side Panel: Day Agenda & Tasks (Always active, defaults to Today) -->
        <div class="xl:col-span-4 cal-glass-panel rounded-3xl p-6 flex flex-col min-h-[500px]">
            
            <!-- Panel Header -->
            <div class="border-b border-slate-200/80 pb-4 mb-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block text-[10px] font-black uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 rounded-full">
                                <?php echo date('l', strtotime($selected_date)); ?>
                            </span>
                            <?php if ($selected_date === $today_date): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Hari Ini
                            </span>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl font-black text-slate-900"><?php echo date('d F Y', strtotime($selected_date)); ?></h2>
                    </div>

                    <?php if ($selected_date !== $today_date): ?>
                    <a href="?page=calendar&month=<?php echo date('Y-m'); ?>&date=<?php echo $today_date; ?>" 
                       title="Kembali ke Hari Ini"
                       class="text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors py-1 px-2.5 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center gap-1">
                        Hari Ini &rarr;
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Progress Bar for the day -->
                <div class="mt-3">
                    <div class="flex justify-between text-xs font-bold text-slate-500 mb-1.5">
                        <span>Penyelesaian Agenda</span>
                        <span class="text-indigo-600 font-extrabold"><?php echo $day_done; ?> / <?php echo $day_total; ?> (<?php echo $day_pct; ?>%)</span>
                    </div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-full transition-all duration-500" style="width: <?php echo $day_pct; ?>%"></div>
                    </div>
                </div>

                <!-- Filter Tabs: Semua / Pending / Selesai -->
                <div class="flex items-center gap-1.5 mt-4 p-1 rounded-xl bg-slate-100 border border-slate-200/80">
                    <button type="button" onclick="filterAgendaTasks('all')" id="btn-filter-all"
                            class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-900 bg-white shadow-sm transition-all text-center">
                        Semua (<?php echo $day_total; ?>)
                    </button>
                    <button type="button" onclick="filterAgendaTasks('pending')" id="btn-filter-pending"
                            class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-500 hover:text-slate-900 transition-all text-center">
                        Pending (<?php echo $day_pending; ?>)
                    </button>
                    <button type="button" onclick="filterAgendaTasks('done')" id="btn-filter-done"
                            class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-500 hover:text-slate-900 transition-all text-center">
                        Selesai (<?php echo $day_done; ?>)
                    </button>
                </div>
            </div>

            <!-- Quick Add Form -->
            <form method="POST" class="mb-4 flex-shrink-0">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="add_todo" value="1">
                <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                <div class="flex gap-2">
                    <input type="text" name="title" required placeholder="+ Tulis agenda untuk tanggal ini..." aria-label="Tambah agenda"
                           class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:bg-white focus:border-indigo-500 transition-all placeholder:text-slate-400">
                    <button type="submit" aria-label="Simpan Tugas" class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/30 transition-transform active:scale-95 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                </div>
            </form>

            <!-- Task List Container -->
            <div id="agendaTaskList" class="flex-1 overflow-y-auto space-y-2.5 custom-scrollbar pr-1">
                <?php if (empty($filtered_todos)): ?>
                    <div class="h-full flex flex-col items-center justify-center text-center py-12 px-4">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center mb-3 border border-indigo-100 shadow-sm">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <h3 class="text-xs font-black text-slate-800 mb-1">Belum Ada Agenda</h3>
                        <p class="text-[11px] text-slate-400 max-w-[220px] leading-relaxed font-medium">Gunakan kolom input di atas untuk menambahkan tugas atau agenda pada tanggal ini.</p>
                    </div>

                <?php else: ?>
                    <?php foreach ($filtered_todos as $todo): 
                        $status = $todo['status'] ?: 'todo';
                        $is_done = $status === 'done' || $todo['completed'];
                        
                        $card_bg = $is_done 
                            ? 'bg-emerald-50/50 border-emerald-200' 
                            : ($status === 'in_progress' ? 'bg-sky-50/50 border-sky-200' : 'bg-slate-50 border-slate-200');
                        
                        $badge_class = $is_done 
                            ? 'text-emerald-800 bg-emerald-100 border border-emerald-200' 
                            : ($status === 'in_progress' ? 'text-sky-800 bg-sky-100 border border-sky-200' : 'text-slate-700 bg-slate-200 border border-slate-300');
                        
                        $status_label = $is_done ? 'Selesai' : ($status === 'in_progress' ? 'Dalam Proses' : 'Belum Mulai');
                    ?>
                    <div class="cal-agenda-row p-3.5 rounded-2xl border <?php echo $card_bg; ?>" data-status="<?php echo $is_done ? 'done' : 'pending'; ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1.5">
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md <?php echo $badge_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </div>
                                <p class="text-xs font-bold leading-snug <?php echo $is_done ? 'text-slate-400 line-through' : 'text-slate-900'; ?>">
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
                                    <button type="submit" title="Reset ke Belum Mulai" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-500 hover:text-slate-800 active:scale-95 transition-all shadow-sm">
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
                                    <button type="submit" title="Ubah ke Dalam Proses" class="w-7 h-7 flex items-center justify-center rounded-lg bg-sky-500 hover:bg-sky-600 text-white active:scale-95 transition-all shadow-sm">
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
                                    <button type="submit" title="Hapus Tugas" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 active:scale-95 transition-colors">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="12" x2="18" y2="12"/></svg>
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

<script>
function filterAgendaTasks(type) {
    const btnAll = document.getElementById('btn-filter-all');
    const btnPending = document.getElementById('btn-filter-pending');
    const btnDone = document.getElementById('btn-filter-done');
    const items = document.querySelectorAll('#agendaTaskList .cal-agenda-row');

    // Reset styles
    [btnAll, btnPending, btnDone].forEach(b => {
        if (!b) return;
        b.className = 'flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-500 hover:text-slate-900 transition-all text-center';
    });

    const activeBtn = type === 'all' ? btnAll : (type === 'pending' ? btnPending : btnDone);
    if (activeBtn) {
        activeBtn.className = 'flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-900 bg-white shadow-sm transition-all text-center';
    }

    items.forEach(el => {
        const itemStatus = el.getAttribute('data-status');
        if (type === 'all') {
            el.style.display = '';
        } else if (type === 'pending') {
            el.style.display = (itemStatus === 'pending') ? '' : 'none';
        } else if (type === 'done') {
            el.style.display = (itemStatus === 'done') ? '' : 'none';
        }
    });
}
</script>
