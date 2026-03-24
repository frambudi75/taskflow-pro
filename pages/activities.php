<?php
// pages/activities.php - System Activity Center
$isAdmin = isAdmin();
$user_id = $_SESSION['user_id'];

// Fetch activities with user info
$stmt = $pdo->prepare("
    SELECT a.*, u.display_name, u.profile_photo, u.role as user_role 
    FROM activities a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 60
");
$stmt->execute();
$activities = $stmt->fetchAll();

function getActionStyle($action) {
    $action = strtolower($action);
    if (strpos($action, 'dokumen') !== false || strpos($action, 'upload') !== false) 
        return ['📁', 'bg-emerald-50 text-emerald-600 border-emerald-100'];
    if (strpos($action, 'catatan') !== false || strpos($action, 'note') !== false) 
        return ['📝', 'bg-indigo-50 text-indigo-600 border-indigo-100'];
    if (strpos($action, 'tugas') !== false || strpos($action, 'todo') !== false || strpos($action, 'status') !== false) 
        return ['✅', 'bg-blue-50 text-blue-600 border-blue-100'];
    if (strpos($action, 'profil') !== false || strpos($action, 'password') !== false) 
        return ['👤', 'bg-amber-50 text-amber-600 border-amber-100'];
    if (strpos($action, 'login') !== false || strpos($action, 'logout') !== false) 
        return ['🔑', 'bg-rose-50 text-rose-600 border-rose-100'];
    return ['✨', 'bg-slate-50 text-slate-600 border-slate-100'];
}
?>

<div class="p-6 md:p-10 max-w-5xl mx-auto min-h-screen">
    
    <!-- PREMIUM LIGHT HEADER -->
    <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="px-3 py-1 bg-white border border-slate-200 rounded-full text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Audit Log</span>
                <div class="h-px w-8 bg-slate-200"></div>
                <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Real-time Tracking</span>
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Aktivitas Sistem</h1>
            <p class="text-slate-500 font-medium max-w-md">Pantau semua jejak digital, perubahan tugas, dan interaksi dokumen dalam satu timeline terpadu.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="bg-white px-5 py-3 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="text-right">
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Sesi</div>
                    <div class="text-xl font-black text-slate-900"><?php echo count($activities); ?></div>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- TIMELINE CONTAINER -->
    <div class="relative pl-4 md:pl-0">
        
        <?php if (empty($activities)): ?>
            <div class="bg-white rounded-[2.5rem] p-16 text-center border border-slate-100 shadow-sm">
                <div class="w-24 h-24 bg-slate-50 rounded-3xl flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">🏜️</div>
                <h3 class="text-2xl font-black text-slate-900 mb-2">Hening Sekejap...</h3>
                <p class="text-slate-500 font-medium max-w-xs mx-auto">Sistem belum mencatat aktivitas apa pun hari ini. Mulailah membuat tugas atau catatan!</p>
            </div>
        <?php else: ?>
            
            <div class="flex flex-col gap-10">
                <?php 
                $currentDate = '';
                foreach ($activities as $act): 
                    $dateLabel = date('d F Y', strtotime($act['created_at']));
                    $timeLabel = date('H:i', strtotime($act['created_at']));
                    $displayDate = ($dateLabel !== $currentDate);
                    $currentDate = $dateLabel;
                    $style = getActionStyle($act['action']);
                    
                    $initials = strtoupper(substr($act['display_name'], 0, 2));
                    $hasPhoto = !empty($act['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $act['profile_photo']);
                ?>
                    
                    <?php if ($displayDate): ?>
                        <div class="relative flex items-center gap-4 mb-4">
                            <div class="h-px flex-1 bg-gradient-to-r from-transparent via-slate-200 to-transparent"></div>
                            <span class="bg-white px-6 py-2 rounded-2xl text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] border border-slate-100 shadow-sm">
                                <?php echo $dateLabel; ?>
                            </span>
                            <div class="h-px flex-1 bg-gradient-to-r from-transparent via-slate-200 to-transparent"></div>
                        </div>
                    <?php endif; ?>

                    <!-- Activity Row -->
                    <div class="group relative flex gap-6 md:gap-8 items-start">
                        
                        <!-- Side Info (Desktop) -->
                        <div class="hidden md:flex flex-col items-end w-20 pt-1.5 flex-shrink-0">
                            <span class="text-xs font-black text-slate-900 leading-none"><?php echo $timeLabel; ?></span>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">WIB</span>
                        </div>

                        <!-- Connector Dot -->
                        <div class="relative z-10 flex flex-col items-center flex-shrink-0 pt-1">
                            <div class="w-5 h-5 rounded-full border-4 border-white shadow-md transition-all group-hover:scale-125 <?php echo $style[1]; ?>"></div>
                            <div class="w-0.5 flex-1 bg-slate-100 mt-2 rounded-full absolute top-7 -bottom-14 hidden md:block"></div>
                        </div>

                        <!-- Card Content -->
                        <div class="flex-1 bg-white rounded-[1.75rem] p-5 md:p-6 border border-slate-100 shadow-sm transition-all hover:shadow-xl hover:shadow-indigo-500/5 hover:-translate-y-1 flex flex-col md:flex-row md:items-center gap-5">
                            
                            <!-- Large Icon -->
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl border flex-shrink-0 <?php echo $style[1]; ?>">
                                <?php echo $style[0]; ?>
                            </div>

                            <!-- Text Content -->
                            <div class="flex-1">
                                <p class="text-slate-800 font-bold leading-snug text-[15px] mb-2">
                                    <?php echo htmlspecialchars($act['action']); ?>
                                </p>
                                
                                <div class="flex flex-wrap items-center gap-3">
                                    <!-- User Badge -->
                                    <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-slate-50 border border-slate-100 rounded-lg">
                                        <div class="w-5 h-5 rounded-md overflow-hidden bg-white border border-slate-200 flex items-center justify-center">
                                            <?php if ($hasPhoto): ?>
                                                <img src="uploads/avatars/<?php echo htmlspecialchars($act['profile_photo']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <span class="text-[8px] font-black text-slate-400"><?php echo $initials; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-[11px] font-bold text-slate-600"><?php echo htmlspecialchars($act['display_name']); ?></span>
                                        <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                        <span class="text-[9px] font-black text-indigo-400 uppercase tracking-widest"><?php echo $act['user_role']; ?></span>
                                    </div>

                                    <!-- Mobile Time (Hidden on Desktop) -->
                                    <div class="md:hidden flex items-center gap-1.5 text-[11px] font-bold text-slate-400 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100">
                                        <span>🕒 <?php echo $timeLabel; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Badge -->
                            <div class="hidden md:block">
                                <div class="px-4 py-2 bg-slate-50 text-slate-400 text-[10px] font-black uppercase tracking-[0.15em] rounded-xl border border-dotted border-slate-200">
                                    Verified Action
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
            
            <div class="mt-16 text-center">
                <p class="text-slate-400 text-xs font-medium italic underline decoration-slate-200 underline-offset-4">Menampilkan 60 aktivitas terakhir secara otomatis.</p>
            </div>

        <?php endif; ?>
    </div>
</div>

<style>
/* Custom subtle animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.group {
    animation: fadeIn 0.4s ease forwards;
}
</style>
