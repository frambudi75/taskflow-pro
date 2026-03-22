<?php
// pages/activities.php - System Activity Center
$isAdmin = isAdmin();
$user_id = $_SESSION['user_id'];

// Filter logic: Admins see everything, Users see their own + public actions (if any)
// For simplicity in this TaskFlow, let's assume team-wide visibility for now.
$stmt = $pdo->prepare("
    SELECT a.*, u.display_name, u.profile_photo, u.role as user_role 
    FROM activities a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 50
");
$stmt->execute();
$activities = $stmt->fetchAll();

function getActionIcon($action) {
    $action = strtolower($action);
    if (strpos($action, 'dokumen') !== false || strpos($action, 'upload') !== false) 
        return ['📁', 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600'];
    if (strpos($action, 'catatan') !== false || strpos($action, 'note') !== false) 
        return ['📝', 'bg-purple-100 dark:bg-purple-900/30 text-purple-600'];
    if (strpos($action, 'tugas') !== false || strpos($action, 'todo') !== false || strpos($action, 'status') !== false) 
        return ['✅', 'bg-blue-100 dark:bg-blue-900/30 text-blue-600'];
    if (strpos($action, 'profil') !== false || strpos($action, 'password') !== false) 
        return ['👤', 'bg-slate-100 dark:bg-slate-800 text-slate-600'];
    return ['✨', 'bg-amber-100 dark:bg-amber-900/30 text-amber-600'];
}
?>

<div class="p-5 md:p-8 max-w-4xl mx-auto">
    <!-- HERO HEADER -->
    <div class="rounded-3xl p-7 md:p-10 mb-8 relative overflow-hidden"
         style="background: linear-gradient(135deg,#1e293b 0%,#334155 100%)">
        <div class="absolute inset-0 opacity-10"
             style="background-image: radial-gradient(#64748b 1px, transparent 1px); background-size: 20px 20px;"></div>
        <div class="relative z-10">
            <div class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-2">System Audit & Timeline</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">⚡ Aktivitas Terkini</h1>
            <p class="text-slate-400 text-sm font-medium">Pantau jejak aktivitas dan perubahan dalam sistem Anda.</p>
        </div>
    </div>

    <!-- TIMELINE -->
    <div class="relative">
        <!-- Vertical line -->
        <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-slate-100 dark:bg-slate-800 hidden md:block"></div>

        <div class="flex flex-col gap-6">
            <?php if (empty($activities)): ?>
                <div class="bg-white dark:bg-slate-900 rounded-[2rem] p-10 text-center border border-slate-100 dark:border-slate-800">
                    <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">⚡</div>
                    <h3 class="font-black text-slate-900 dark:text-white mb-1">Belum ada aktivitas</h3>
                    <p class="text-slate-400 text-sm">Aktivitas sistem akan muncul di sini setelah Anda mulai bekerja.</p>
                </div>
            <?php endif; ?>

            <?php 
            $currentDate = '';
            foreach ($activities as $act): 
                $dateLabel = date('d F Y', strtotime($act['created_at']));
                $timeLabel = date('H:i', strtotime($act['created_at']));
                $displayDate = ($dateLabel !== $currentDate);
                $currentDate = $dateLabel;
                $iconData = getActionIcon($act['action']);
                
                $initials = strtoupper(substr($act['display_name'], 0, 2));
                $hasPhoto = !empty($act['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $act['profile_photo']);
            ?>
                
                <?php if ($displayDate): ?>
                    <div class="relative z-10 flex md:ml-4 mb-2">
                        <span class="bg-slate-50 dark:bg-slate-800 px-4 py-1.5 rounded-full text-[10px] font-black text-slate-500 uppercase tracking-widest border border-slate-100 dark:border-slate-700">
                            📅 <?php echo $dateLabel; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="relative z-10 flex flex-col md:flex-row gap-4 items-start md:items-center">
                    <!-- Icon / Avatar marker -->
                    <div class="hidden md:flex w-12 h-12 rounded-2xl border-4 border-white dark:border-slate-950 items-center justify-center flex-shrink-0 z-10 <?php echo $iconData[1]; ?>"
                         style="box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
                        <span class="text-xl"><?php echo $iconData[0]; ?></span>
                    </div>

                    <!-- Card content -->
                    <div class="flex-1 bg-white dark:bg-slate-900 rounded-[1.5rem] p-5 border border-slate-100 dark:border-slate-800 shadow-sm transition-all hover:shadow-md flex items-center gap-4 w-full">
                        <!-- Tiny Avatar for User -->
                        <div class="w-10 h-10 rounded-xl overflow-hidden flex-shrink-0 bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-xs text-slate-500">
                            <?php if ($hasPhoto): ?>
                                <img src="uploads/avatars/<?php echo htmlspecialchars($act['profile_photo']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-xs font-black text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars($act['display_name']); ?></span>
                                <span class="text-[10px] font-bold text-slate-400 whitespace-nowrap">🕒 <?php echo $timeLabel; ?></span>
                            </div>
                            <p class="text-sm text-slate-500 dark:text-slate-400 font-medium leading-relaxed">
                                <?php echo htmlspecialchars($act['action']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
