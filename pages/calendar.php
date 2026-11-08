<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/calendar.php - Modern & Clean Executive Productivity Calendar

$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$today_date    = date('Y-m-d');
$month_today   = date('Y-m');
$view_mode     = isset($_GET['view']) && in_array($_GET['view'], ['month', 'week', 'day']) ? $_GET['view'] : 'month';

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
            header("Location: index.php?page=calendar&month=" . $current_month . ($date ? "&date=" . $date : "") . "&view=" . $view_mode);
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
            header("Location: index.php?page=calendar&month=" . $current_month . ($target_date ? "&date=" . $target_date : "") . "&view=" . $view_mode);
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
            header("Location: index.php?page=calendar&month=" . $current_month . ($target_date ? "&date=" . $target_date : "") . "&view=" . $view_mode);
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
/* Executive Glassmorphic Productivity Calendar System */
.cal-glass-panel {
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(16px) saturate(180%);
    -webkit-backdrop-filter: blur(16px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: 24px;
    box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 0 rgba(255, 255, 255, 0.95);
    position: relative;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.dark .cal-glass-panel {
    background: rgba(15, 23, 42, 0.82);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 8px 30px -4px rgba(0, 0, 0, 0.45), inset 0 1px 0 0 rgba(255, 255, 255, 0.06);
}

.cal-glass-card {
    background: rgba(255, 255, 255, 0.82);
    backdrop-filter: blur(14px) saturate(180%);
    -webkit-backdrop-filter: blur(14px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04), inset 0 1px 0 0 rgba(255, 255, 255, 0.95);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
.dark .cal-glass-card {
    background: rgba(15, 23, 42, 0.8);
    border-color: rgba(255, 255, 255, 0.08);
    box-shadow: 0 6px 20px -2px rgba(0, 0, 0, 0.4), inset 0 1px 0 0 rgba(255, 255, 255, 0.06);
}
.cal-glass-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 28px -4px rgba(79, 70, 229, 0.12), inset 0 1px 0 0 rgba(255, 255, 255, 1);
    border-color: rgba(99, 102, 241, 0.35);
}
.dark .cal-glass-card:hover {
    box-shadow: 0 12px 28px -4px rgba(0, 0, 0, 0.5), inset 0 1px 0 0 rgba(255, 255, 255, 0.12);
    border-color: rgba(99, 102, 241, 0.45);
}

.cal-cell {
    transition: all 0.16s ease;
}
.cal-cell:hover {
    z-index: 5;
}
.cal-cell.is-selected {
    box-shadow: inset 0 0 0 2px #6366f1, 0 4px 14px -2px rgba(99, 102, 241, 0.25) !important;
    background: rgba(238, 242, 255, 0.92) !important;
    position: relative;
    z-index: 20 !important;
}
.dark .cal-cell.is-selected {
    box-shadow: inset 0 0 0 2px #818cf8, 0 4px 14px -2px rgba(99, 102, 241, 0.35) !important;
    background: rgba(30, 27, 75, 0.7) !important;
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
    box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.08);
}
.dark .cal-agenda-row:hover {
    box-shadow: 0 6px 16px -2px rgba(0, 0, 0, 0.4);
}
</style>

<div class="p-4 md:p-8 pb-20 md:pb-24 w-full space-y-6">

    <!-- Header Section (Executive Clean SaaS Frosted Glass Header) -->
    <div class="rounded-3xl p-6 md:p-7 cal-glass-panel bg-gradient-to-br from-white/95 via-slate-50/70 to-indigo-50/30 dark:from-slate-900/90 dark:via-slate-900/80 dark:to-indigo-950/20 border-t-[3px] border-t-indigo-600 shadow-xs relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50/90 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/60 text-indigo-700 dark:text-indigo-300 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Jadwal &amp; Agenda Kerja
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight mb-1">Kalender Produktivitas</h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm max-w-xl leading-relaxed font-medium">
                Pantau seluruh agenda, tenggat waktu harian, dan rencanakan aktivitas kerja Anda dalam satu kanvas terintegrasi.
            </p>
        </div>

        <div class="flex items-center gap-3 self-start md:self-center flex-shrink-0">
            <button type="button" onclick="focusAgendaInput()" 
                    class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 active:scale-95 text-white font-bold text-xs shadow-md shadow-indigo-600/25 flex items-center gap-2 transition-all">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>+ Buat Agenda Cepat</span>
            </button>
        </div>
    </div>

    <!-- Monthly Summary KPI Stats (Standard Cards with Clean Scope Sub-labels & Accent Icons) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Agenda -->
        <div class="cal-glass-card p-5 border-t-[3px] border-t-indigo-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Tugas</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-900/50">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none"><?php echo $total_month; ?></span>
            </div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 font-semibold mt-1">di bulan <?php echo date('F Y', strtotime($current_month)); ?></div>
        </div>

        <!-- Card 2: Selesai -->
        <div class="cal-glass-card p-5 border-t-[3px] border-t-emerald-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Selesai</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-100 dark:border-emerald-900/50">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight leading-none"><?php echo $done_month; ?></span>
                <span class="text-[11px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-0.5 rounded-full border border-emerald-200 dark:border-emerald-800/60"><?php echo $completion_pct; ?>%</span>
            </div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 font-semibold mt-1">selesai bulan ini</div>
        </div>

        <!-- Card 3: Dalam Progress -->
        <div class="cal-glass-card p-5 border-t-[3px] border-t-sky-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dalam Proses</span>
                <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-900/50">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="13 17 18 12 13 7"/><polyline points="6 17 11 12 6 7"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-sky-600 dark:text-sky-400 tracking-tight leading-none"><?php echo $inprogress_month; ?></span>
            </div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 font-semibold mt-1">sedang aktif berjalan</div>
        </div>

        <!-- Card 4: Belum Mulai -->
        <div class="cal-glass-card p-5 border-t-[3px] border-t-amber-500">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">Belum Mulai</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-900/50">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl md:text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tight leading-none"><?php echo $todo_month; ?></span>
            </div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 font-semibold mt-1">dalam antrean bulan ini</div>
        </div>
    </div>

    <!-- Main Content Area: Calendar Grid + Task List Side Panel -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

        <!-- Calendar Matrix (8 Columns on desktop) -->
        <div class="xl:col-span-8 cal-glass-panel border-t-[3px] border-t-indigo-600 rounded-3xl overflow-hidden flex flex-col">
            
            <!-- Proximity Calendar Header: Month Navigator + Today Jump + View Switcher + Indicators -->
            <div class="px-5 py-4 border-b border-slate-200/70 dark:border-slate-800/80 bg-white/50 dark:bg-slate-900/40 backdrop-blur-md flex flex-col md:flex-row md:items-center justify-between gap-4">
                
                <!-- Left: Navigation Controls directly on top of grid -->
                <div class="flex items-center gap-3">
                    <!-- Month Navigation Group -->
                    <div class="flex items-center rounded-xl bg-white/90 dark:bg-slate-800/80 backdrop-blur-sm border border-slate-200/90 dark:border-slate-700/80 shadow-2xs p-1">
                        <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' -1 month')); ?>&date=<?php echo $selected_date; ?>&view=<?php echo $view_mode; ?>"
                           aria-label="Bulan sebelumnya"
                           title="Bulan sebelumnya"
                           class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 active:scale-95 transition-all">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </a>
                        <span class="px-3 md:px-5 font-black text-slate-900 dark:text-white text-sm tracking-tight text-center min-w-[130px]">
                            <?php echo date('F Y', strtotime($current_month)); ?>
                        </span>
                        <a href="?page=calendar&month=<?php echo date('Y-m', strtotime($current_month . ' +1 month')); ?>&date=<?php echo $selected_date; ?>&view=<?php echo $view_mode; ?>"
                           aria-label="Bulan berikutnya"
                           title="Bulan berikutnya"
                           class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-700 active:scale-95 transition-all">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </a>
                    </div>

                    <!-- Jump to Today -->
                    <a href="?page=calendar&month=<?php echo date('Y-m'); ?>&date=<?php echo $today_date; ?>&view=<?php echo $view_mode; ?>" 
                       class="px-3.5 py-2 rounded-xl bg-indigo-50/90 dark:bg-indigo-950/70 hover:bg-indigo-100 dark:hover:bg-indigo-900/80 active:scale-95 border border-indigo-200/80 dark:border-indigo-800/70 text-indigo-700 dark:text-indigo-300 text-xs font-bold transition-all flex items-center gap-1.5 shadow-2xs">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Hari Ini
                    </a>
                </div>

                <!-- Right: View Switcher (Bulan | Minggu | Hari) & Dot Legend -->
                <div class="flex items-center gap-3 self-end md:self-auto flex-wrap">
                    <!-- View Switcher -->
                    <div class="flex items-center p-1 rounded-xl bg-white/90 dark:bg-slate-800/80 backdrop-blur-sm border border-slate-200/90 dark:border-slate-700/80 shadow-2xs text-xs font-bold">
                        <a href="?page=calendar&month=<?php echo $current_month; ?>&date=<?php echo $selected_date; ?>&view=month" 
                           class="px-3 py-1.5 rounded-lg transition-all <?php echo $view_mode === 'month' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'; ?>">
                            Bulan
                        </a>
                        <a href="?page=calendar&month=<?php echo $current_month; ?>&date=<?php echo $selected_date; ?>&view=week" 
                           class="px-3 py-1.5 rounded-lg transition-all <?php echo $view_mode === 'week' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'; ?>">
                            Minggu
                        </a>
                        <a href="?page=calendar&month=<?php echo $current_month; ?>&date=<?php echo $selected_date; ?>&view=day" 
                           class="px-3 py-1.5 rounded-lg transition-all <?php echo $view_mode === 'day' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'; ?>">
                            Hari
                        </a>
                    </div>

                    <!-- Indicators -->
                    <div class="hidden md:flex items-center gap-2.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400 pl-3 border-l border-slate-200/80 dark:border-slate-800">
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Selesai</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-sky-500"></span>Proses</span>
                        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-indigo-500"></span>Pending</span>
                    </div>
                </div>
            </div>

            <?php if ($view_mode === 'month'): ?>
                <!-- Day of Week Header -->
                <div class="grid grid-cols-7 bg-slate-200/60 dark:bg-slate-800/60 gap-[1px] border-b border-slate-200/80 dark:border-slate-800 text-center text-xs font-black uppercase tracking-wider">
                    <div class="py-2.5 bg-rose-50/70 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400">Min</div>
                    <div class="py-2.5 bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400">Sen</div>
                    <div class="py-2.5 bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400">Sel</div>
                    <div class="py-2.5 bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400">Rab</div>
                    <div class="py-2.5 bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400">Kam</div>
                    <div class="py-2.5 bg-slate-50/80 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400">Jum</div>
                    <div class="py-2.5 bg-indigo-50/70 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400">Sab</div>
                </div>

                <!-- Calendar Cells Grid -->
                <div class="grid grid-cols-7 bg-slate-200/60 dark:bg-slate-800/60 gap-[1px]">
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
                        $p_is_selected = $p_date_str === $selected_date;
                        $p_classes = 'cal-cell min-h-[95px] p-2 md:p-2.5 flex flex-col justify-between opacity-30 hover:opacity-75 bg-slate-50/50 dark:bg-slate-900/30 transition-all select-none ';
                        if ($p_is_selected) $p_classes .= 'is-selected opacity-100 ';
                        echo '<a href="?page=calendar&month=' . $prev_month_str . '&date=' . $p_date_str . '&view=' . $view_mode . '" class="' . $p_classes . '">';
                        echo '<div class="flex items-center justify-between w-full">';
                        echo '<span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-slate-400 dark:text-slate-500">' . $p_day . '</span>';
                        echo '</div>';
                        echo '<div class="text-[9px] text-slate-400 dark:text-slate-500 font-semibold truncate">' . date('M', $prev_month_ts) . '</div>';
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

                        $day_link  = '?page=calendar&month=' . $current_month . '&date=' . $date_str . '&view=' . $view_mode;

                        // Distinct Weekend vs Workday Cell Background
                        $cell_classes = 'cal-cell min-h-[95px] p-2 md:p-2.5 flex flex-col justify-between relative group ';
                        if ($is_selected) {
                            $cell_classes .= 'is-selected ';
                        } elseif ($is_today) {
                            $cell_classes .= 'bg-indigo-50/35 dark:bg-indigo-950/30 hover:bg-indigo-50/60 dark:hover:bg-indigo-950/50 ';
                        } elseif ($is_sunday) {
                            $cell_classes .= 'bg-rose-50/20 dark:bg-rose-950/10 hover:bg-rose-50/40 dark:hover:bg-rose-950/20 ';
                        } elseif ($is_saturday) {
                            $cell_classes .= 'bg-indigo-50/15 dark:bg-indigo-950/10 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 ';
                        } else {
                            $cell_classes .= 'bg-white/85 dark:bg-slate-900/80 hover:bg-slate-50/90 dark:hover:bg-slate-800/70 ';
                        }

                        // Distinct Number badge styling
                        $num_classes = 'w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black transition-transform group-hover:scale-105 ';
                        if ($is_today) {
                            // Solid primary badge for Today
                            $num_classes .= 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-200 dark:ring-indigo-900/60';
                        } elseif ($is_selected) {
                            $num_classes .= 'bg-slate-900 dark:bg-indigo-600 text-white shadow-xs';
                        } elseif ($is_sunday) {
                            $num_classes .= 'text-rose-600 dark:text-rose-400 bg-rose-100/70 dark:bg-rose-950/50';
                        } elseif ($is_saturday) {
                            $num_classes .= 'text-indigo-600 dark:text-indigo-400 bg-indigo-100/70 dark:bg-indigo-950/50';
                        } else {
                            $num_classes .= 'text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800';
                        }

                        echo '<a href="' . $day_link . '" class="' . $cell_classes . '">';
                        
                        // Top Row: Number & Status Indicators
                        echo '<div class="flex items-center justify-between w-full gap-1">';
                        echo '<span class="' . $num_classes . '">' . $day . '</span>';
                        
                        if ($is_today) {
                            echo '<span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-tight text-indigo-700 dark:text-indigo-300 bg-indigo-100/90 dark:bg-indigo-950/80 px-1.5 py-0.5 rounded-full shrink-0" title="Hari Ini"><span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span><span class="hidden 2xl:inline">Hari Ini</span></span>';
                        } elseif ($total > 0) {
                            echo '<div class="flex items-center gap-1 shrink-0">';
                            if ($done > 0) echo '<span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0" title="' . $done . ' Selesai"></span>';
                            if ($inprog > 0) echo '<span class="w-2 h-2 rounded-full bg-sky-500 shrink-0" title="' . $inprog . ' Dalam Proses"></span>';
                            if ($pending > 0) echo '<span class="w-2 h-2 rounded-full bg-indigo-500 shrink-0" title="' . $pending . ' Belum Selesai"></span>';
                            echo '</div>';
                        }
                        echo '</div>';

                        // Tasks Event Pills Inside Cell (Max 2 items + overflow pill)
                        echo '<div class="space-y-1 my-1 w-full overflow-hidden">';
                        if ($total > 0) {
                            $preview_todos = array_slice($day_todos, 0, 2);
                            foreach ($preview_todos as $pt) {
                                $is_pt_done = $pt['completed'] || $pt['status'] === 'done';
                                $pt_color = $is_pt_done 
                                    ? 'bg-emerald-50/90 dark:bg-emerald-950/50 text-emerald-800 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/50' 
                                    : ($pt['status'] === 'in_progress' ? 'bg-sky-50/90 dark:bg-sky-950/50 text-sky-800 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/50' : 'bg-indigo-50/90 dark:bg-indigo-950/50 text-indigo-800 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/50');
                                $pt_dot   = $is_pt_done ? 'bg-emerald-500' : ($pt['status'] === 'in_progress' ? 'bg-sky-500' : 'bg-indigo-500');
                                
                                echo '<div class="cal-task-pill text-[10px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-1.5 truncate ' . $pt_color . '">';
                                echo '<span class="w-1.5 h-1.5 rounded-full flex-shrink-0 ' . $pt_dot . '"></span>';
                                echo '<span class="truncate ' . ($is_pt_done ? 'line-through opacity-70' : '') . '">' . htmlspecialchars($pt['title']) . '</span>';
                                echo '</div>';
                            }
                            if ($total > 2) {
                                echo '<div class="text-[9px] font-black text-slate-500 dark:text-slate-400 px-1">+' . ($total - 2) . ' lainnya...</div>';
                            }
                        }
                        echo '</div>';

                        // Bottom: Mini count pill
                        echo '<div class="flex items-center gap-1">';
                        if ($total > 0) {
                            if ($pending === 0 && $done > 0) {
                                echo '<span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">✓ ' . $total . ' Selesai</span>';
                            } else {
                                echo '<span class="text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-slate-100/90 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200/60 dark:border-slate-700/60">' . $total . ' agenda</span>';
                            }
                        }
                        echo '</div>';

                        echo '</a>';
                    }
                    // 3. Next month trailing padding cells
                    $total_rendered = $start_day + $days_in_month;
                    $target_cells   = ($total_rendered <= 35) ? 35 : 42;
                    $remaining      = $target_cells - $total_rendered;

                    $next_month_ts  = strtotime($current_month . ' +1 month');
                    $next_month_str = date('Y-m', $next_month_ts);

                    for ($i = 1; $i <= $remaining; $i++) {
                        $n_date_str = $next_month_str . '-' . sprintf('%02d', $i);
                        $n_is_selected = $n_date_str === $selected_date;
                        $n_classes = 'cal-cell min-h-[95px] p-2 md:p-2.5 flex flex-col justify-between opacity-30 hover:opacity-75 bg-slate-50/50 dark:bg-slate-900/30 transition-all select-none ';
                        if ($n_is_selected) $n_classes .= 'is-selected opacity-100 ';
                        echo '<a href="?page=calendar&month=' . $next_month_str . '&date=' . $n_date_str . '&view=' . $view_mode . '" class="' . $n_classes . '">';
                        echo '<div class="flex items-center justify-between w-full">';
                        echo '<span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-slate-400 dark:text-slate-500">' . $i . '</span>';
                        echo '</div>';
                        echo '<div class="text-[9px] text-slate-400 dark:text-slate-500 font-semibold truncate">' . date('M', $next_month_ts) . '</div>';
                        echo '</a>';
                    }
                    ?>
                </div>

            <?php elseif ($view_mode === 'week'): ?>
                <!-- WEEK VIEW: 7 Detailed Columns for the Selected Week -->
                <?php
                $w_day = (int)date('w', strtotime($selected_date));
                $week_start_ts = strtotime("-$w_day days", strtotime($selected_date));
                $day_names = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
                ?>
                <div class="grid grid-cols-1 md:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-slate-200/80 dark:divide-slate-800 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md min-h-[460px]">
                    <?php for ($d = 0; $d < 7; $d++):
                        $d_ts = strtotime("+$d days", $week_start_ts);
                        $d_date = date('Y-m-d', $d_ts);
                        $d_is_today = $d_date === $today_date;
                        $d_is_selected = $d_date === $selected_date;
                        $d_todos = array_values(array_filter($all_month_todos, fn($t) => $t['todo_date'] === $d_date));
                    ?>
                    <div class="p-3 flex flex-col justify-between <?php echo $d_is_selected ? 'bg-indigo-50/50 dark:bg-indigo-950/40' : ($d === 0 ? 'bg-rose-50/20 dark:bg-rose-950/10' : ($d === 6 ? 'bg-indigo-50/10 dark:bg-indigo-950/10' : '')); ?>">
                        <div>
                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100 dark:border-slate-800">
                                <div>
                                    <div class="text-[10px] font-black uppercase <?php echo $d === 0 ? 'text-rose-600 dark:text-rose-400' : ($d === 6 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500 dark:text-slate-400'); ?>"><?php echo $day_names[$d]; ?></div>
                                    <div class="text-sm font-black text-slate-900 dark:text-white"><?php echo date('d M', $d_ts); ?></div>
                                </div>
                                <a href="?page=calendar&month=<?php echo date('Y-m', $d_ts); ?>&date=<?php echo $d_date; ?>&view=week"
                                   class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold <?php echo $d_is_selected ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'; ?>">
                                    ✓
                                </a>
                            </div>
                            <div class="space-y-1.5">
                                <?php if (empty($d_todos)): ?>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 italic py-2">Kosong</div>
                                <?php else: ?>
                                    <?php foreach ($d_todos as $dt): 
                                        $is_dt_done = $dt['completed'] || $dt['status'] === 'done';
                                    ?>
                                    <div class="p-1.5 rounded-lg border text-[11px] font-semibold <?php echo $is_dt_done ? 'bg-emerald-50/80 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-200/80 dark:border-emerald-800/60' : 'bg-slate-50/80 dark:bg-slate-800/60 text-slate-800 dark:text-slate-200 border-slate-200/80 dark:border-slate-700/60'; ?>">
                                        <div class="truncate <?php echo $is_dt_done ? 'line-through opacity-70' : ''; ?>"><?php echo htmlspecialchars($dt['title']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

            <?php elseif ($view_mode === 'day'): ?>
                <!-- DAY VIEW: Focused Agenda Breakdown -->
                <div class="p-6 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md min-h-[460px]">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-5">
                        <div>
                            <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider"><?php echo date('l', strtotime($selected_date)); ?></div>
                            <h2 class="text-2xl font-black text-slate-900 dark:text-white"><?php echo date('d F Y', strtotime($selected_date)); ?></h2>
                        </div>
                        <span class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold border border-slate-200/60 dark:border-slate-700/60"><?php echo count($filtered_todos); ?> Total Agenda</span>
                    </div>

                    <?php if (empty($filtered_todos)): ?>
                        <div class="text-center py-12">
                            <p class="text-slate-400 dark:text-slate-500 text-xs font-medium">Belum ada agenda pada hari ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-2.5 max-w-2xl">
                            <?php foreach ($filtered_todos as $idx => $todo): 
                                $is_t_done = $todo['completed'] || $todo['status'] === 'done';
                            ?>
                            <div class="p-3.5 rounded-2xl border <?php echo $is_t_done ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-50/80 dark:bg-slate-800/60 border-slate-200 dark:border-slate-700/60'; ?> flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs font-black"><?php echo $idx + 1; ?></span>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 <?php echo $is_t_done ? 'line-through opacity-60' : ''; ?>"><?php echo htmlspecialchars($todo['title']); ?></span>
                                </div>
                                <span class="text-[10px] font-black px-2 py-0.5 rounded-md <?php echo $is_t_done ? 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'; ?>">
                                    <?php echo $is_t_done ? 'Selesai' : 'Pending'; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right Side Panel: Day Agenda & Tasks (Always active, defaults to Today) -->
        <div class="xl:col-span-4 cal-glass-panel border-t-[3px] border-t-indigo-600 rounded-3xl p-6 flex flex-col min-h-[500px]">
            
            <!-- Panel Header -->
            <div class="border-b border-slate-200/80 dark:border-slate-800/80 pb-4 mb-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-block text-[10px] font-black uppercase tracking-wider text-indigo-700 dark:text-indigo-300 bg-indigo-50/90 dark:bg-indigo-950/70 border border-indigo-100 dark:border-indigo-900/60 px-2.5 py-0.5 rounded-full">
                                <?php echo date('l', strtotime($selected_date)); ?>
                            </span>
                            <?php if ($selected_date === $today_date): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Hari Ini
                            </span>
                            <?php endif; ?>
                        </div>
                        <h2 class="text-xl md:text-2xl font-black text-slate-900 dark:text-white"><?php echo date('d F Y', strtotime($selected_date)); ?></h2>
                    </div>

                    <?php if ($selected_date !== $today_date): ?>
                    <a href="?page=calendar&month=<?php echo date('Y-m'); ?>&date=<?php echo $today_date; ?>&view=<?php echo $view_mode; ?>" 
                       title="Kembali ke Hari Ini"
                       class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors py-1 px-2.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/60 flex items-center gap-1 shadow-2xs">
                        Hari Ini &rarr;
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Progress Bar for the day (Only shown when total > 0) -->
                <div class="mt-3">
                    <?php if ($day_total === 0): ?>
                        <div class="text-xs font-medium text-slate-400 dark:text-slate-500 py-1">Tidak ada agenda untuk hari ini</div>
                    <?php else: ?>
                        <div class="flex justify-between text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5">
                            <span>Penyelesaian Agenda</span>
                            <span class="text-indigo-600 dark:text-indigo-400 font-extrabold"><?php echo $day_done; ?> / <?php echo $day_total; ?> (<?php echo $day_pct; ?>%)</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-600 rounded-full transition-all duration-500" style="width: <?php echo $day_pct; ?>%"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Filter Tabs: Semua / Pending / Selesai -->
                <div class="flex items-center gap-1.5 mt-4 p-1 rounded-xl bg-slate-100/80 dark:bg-slate-800/60 backdrop-blur-sm border border-slate-200/70 dark:border-slate-700/60">
                    <button type="button" onclick="filterAgendaTasks('all')" id="btn-filter-all"
                            class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-extrabold text-slate-900 dark:text-white bg-white dark:bg-slate-900 shadow-2xs transition-all text-center">
                        Semua (<?php echo $day_total; ?>)
                    </button>
                    <button type="button" onclick="filterAgendaTasks('pending')" id="btn-filter-pending"
                            class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all text-center">
                        Pending (<?php echo $day_pending; ?>)
                    </button>
                    <button type="button" onclick="filterAgendaTasks('done')" id="btn-filter-done"
                            class="flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all text-center">
                        Selesai (<?php echo $day_done; ?>)
                    </button>
                </div>
            </div>

            <!-- Quick Add Form with Dynamic Button State & Keyboard Hint -->
            <form method="POST" class="mb-4 flex-shrink-0">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="add_todo" value="1">
                <input type="hidden" name="date" value="<?php echo $selected_date; ?>">
                <div class="flex gap-2">
                    <input type="text" name="title" id="agendaInput" required 
                           placeholder="+ Tulis agenda untuk tanggal ini..." 
                           aria-label="Tambah agenda"
                           oninput="handleAgendaInputChange(this)"
                           class="flex-1 bg-white/80 dark:bg-slate-800/70 backdrop-blur-sm border border-slate-200/90 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 focus:bg-white dark:focus:bg-slate-800 focus:border-indigo-500 shadow-2xs transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500">
                    <button type="submit" id="agendaSubmitBtn" disabled aria-label="Simpan Tugas" 
                            class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 cursor-not-allowed transition-all">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                </div>
                <div class="text-[10px] text-slate-400 dark:text-slate-500 font-medium mt-1.5 flex items-center gap-1">
                    <span>💡 Tekan <kbd class="px-1 py-0.5 rounded bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[9px] font-mono">Enter</kbd> untuk menyimpan</span>
                </div>
            </form>

            <!-- Task List Container -->
            <div id="agendaTaskList" class="flex-1 overflow-y-auto space-y-2.5 custom-scrollbar pr-1">
                <?php if (empty($filtered_todos)): ?>
                    <!-- Compact & Proportional Empty State -->
                    <div class="h-full flex flex-col items-center justify-center text-center py-8 px-4">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mb-2 border border-indigo-100 dark:border-indigo-900/50 shadow-xs">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 mb-0.5">Belum Ada Agenda</h3>
                        <p class="text-[11px] text-slate-400 dark:text-slate-500 max-w-[200px] leading-relaxed font-medium">Tulis agenda di kolom input di atas untuk tanggal ini.</p>
                    </div>

                <?php else: ?>
                    <?php foreach ($filtered_todos as $todo): 
                        $status = $todo['status'] ?: 'todo';
                        $is_done = $status === 'done' || $todo['completed'];
                        
                        $card_bg = $is_done 
                            ? 'bg-emerald-50/40 dark:bg-emerald-950/20 border-emerald-200/70 dark:border-emerald-800/50' 
                            : ($status === 'in_progress' ? 'bg-sky-50/40 dark:bg-sky-950/20 border-sky-200/70 dark:border-sky-800/50' : 'bg-white/80 dark:bg-slate-800/60 border-slate-200/80 dark:border-slate-700/70 hover:border-indigo-300 dark:hover:border-indigo-600/50 shadow-2xs');
                        
                        $badge_class = $is_done 
                            ? 'text-emerald-800 dark:text-emerald-300 bg-emerald-100/90 dark:bg-emerald-900/60 border border-emerald-200/80 dark:border-emerald-800/50' 
                            : ($status === 'in_progress' ? 'text-sky-800 dark:text-sky-300 bg-sky-100/90 dark:bg-sky-900/60 border border-sky-200/80 dark:border-sky-800/50' : 'text-slate-700 dark:text-slate-300 bg-slate-200/80 dark:bg-slate-700 border border-slate-300/80 dark:border-slate-600');
                        
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
                                    <button type="submit" title="Reset ke Belum Mulai" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-white active:scale-95 transition-all shadow-2xs">
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
                                    <button type="submit" title="Ubah ke Dalam Proses" class="w-7 h-7 flex items-center justify-center rounded-lg bg-sky-500 hover:bg-sky-600 text-white active:scale-95 transition-all shadow-2xs">
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
                                    <button type="submit" title="Tandai Selesai" class="w-7 h-7 flex items-center justify-center rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white active:scale-95 transition-all shadow-2xs">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </form>
                                <?php endif; ?>

                                <form method="POST" onsubmit="return confirm('Hapus tugas ini?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="delete_todo" value="1">
                                    <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                    <input type="hidden" name="selected_date" value="<?php echo $selected_date; ?>">
                                    <button type="submit" title="Hapus Tugas" class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40 active:scale-95 transition-colors">
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
function focusAgendaInput() {
    const input = document.getElementById('agendaInput');
    if (input) {
        input.focus();
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function handleAgendaInputChange(input) {
    const btn = document.getElementById('agendaSubmitBtn');
    if (!btn) return;
    const hasText = input.value.trim().length > 0;
    btn.disabled = !hasText;
    if (hasText) {
        btn.className = 'w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white shadow-md shadow-indigo-600/30 transition-all cursor-pointer active:scale-95';
    } else {
        btn.className = 'w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-200 text-slate-400 cursor-not-allowed transition-all';
    }
}

function filterAgendaTasks(type) {
    const btnAll = document.getElementById('btn-filter-all');
    const btnPending = document.getElementById('btn-filter-pending');
    const btnDone = document.getElementById('btn-filter-done');
    const items = document.querySelectorAll('#agendaTaskList .cal-agenda-row');

    // Reset styles
    [btnAll, btnPending, btnDone].forEach(b => {
        if (!b) return;
        b.className = 'flex-1 py-1.5 px-2 rounded-lg text-[11px] font-bold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-all text-center';
    });

    const activeBtn = type === 'all' ? btnAll : (type === 'pending' ? btnPending : btnDone);
    if (activeBtn) {
        activeBtn.className = 'flex-1 py-1.5 px-2 rounded-lg text-[11px] font-extrabold text-slate-900 dark:text-white bg-white dark:bg-slate-900 shadow-2xs transition-all text-center';
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
