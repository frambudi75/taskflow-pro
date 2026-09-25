<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/admin.php
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $email = $_POST['email'];
        $name  = $_POST['name'];
        $role  = $_POST['role'];
        $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, display_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $pass, $name, $role]);
        header("Location: index.php?page=admin");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $user_id = $_POST['delete_user'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'administrator'");
        $stmt->execute();
        $admin_count = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_role = $stmt->fetchColumn();
        if ($user_role === 'administrator' && $admin_count <= 1) {
            header("Location: index.php?page=admin&error=cannot_delete_last_admin");
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        header("Location: index.php?page=admin");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $user_id  = $_POST['reset_password'];
        $new_pass = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_pass, $user_id]);
        header("Location: index.php?page=admin&success=password_reset");
        exit;
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['optimize_database'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        try {
            clearCache();
            $tables = ['users', 'todos', 'notes', 'documents'];
            foreach ($tables as $table) {
                $pdo->prepare("ANALYZE TABLE $table")->execute();
                $pdo->prepare("OPTIMIZE TABLE $table")->execute();
            }
            $message = "✅ Optimasi database selesai untuk " . count($tables) . " tabel. Cache dibersihkan.";
        } catch (Exception $e) {
            $message = "❌ Optimasi gagal: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_indexes'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        try {
            $indexes = [
                "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
                "CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)",
                "CREATE INDEX IF NOT EXISTS idx_todos_user_id ON todos(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_todos_date ON todos(todo_date)",
                "CREATE INDEX IF NOT EXISTS idx_todos_completed ON todos(completed)",
                "CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_notes_updated ON notes(updated_at)",
                "CREATE INDEX IF NOT EXISTS idx_documents_user_id ON documents(user_id)",
                "CREATE INDEX IF NOT EXISTS idx_documents_uploaded ON documents(upload_date)"
            ];
            $created = 0;
            foreach ($indexes as $sql) {
                try { $pdo->exec($sql); $created++; } catch (Exception $e) {}
            }
            $message = "✅ Indexing selesai. Dibuat/diverifikasi $created indeks.";
        } catch (Exception $e) {
            $message = "❌ Indexing gagal: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_tables'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        try {
            $tables = ['users', 'todos', 'notes', 'documents'];
            $repaired = 0;
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("REPAIR TABLE $table");
                $stmt->execute();
                $result = $stmt->fetch();
                if ($result && isset($result['Msg_text']) && strpos($result['Msg_text'], 'OK') !== false) $repaired++;
            }
            $message = "✅ Repair selesai. $repaired tabel diperbaiki.";
        } catch (Exception $e) {
            $message = "❌ Repair gagal: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_auto_optimize'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        try {
            shell_exec('php ' . __DIR__ . '/../auto_optimize.php 2>&1');
            $message = "✅ Auto-optimasi selesai. Periksa log untuk detailnya.";
        } catch (Exception $e) {
            $message = "❌ Auto-optimasi gagal: " . $e->getMessage();
        }
    }
}

// Stats
$admin_count = count(array_filter($users, fn($u) => $u['role'] === 'administrator'));
$user_count  = count($users) - $admin_count;
?>

<div class="p-5 md:p-8 max-w-7xl mx-auto">

    <!-- Hero Header -->
    <div class="rounded-3xl p-7 md:p-10 mb-7 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950">
        <div class="relative z-10">
            <div class="text-blue-300 text-[10px] font-black uppercase tracking-widest mb-2">Administrator Panel</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">Manajemen Pengguna</h1>
            <p class="text-blue-200 text-sm font-medium">Kelola hak akses dan identitas seluruh pengguna sistem.</p>
        </div>
        <!-- Stat pills in hero -->
        <div class="relative z-10 flex items-center gap-3 flex-shrink-0">
            <div class="px-5 py-3 rounded-2xl text-center bg-white/10 border border-white/15">
                <div class="text-2xl font-black text-white"><?php echo count($users); ?></div>
                <div class="text-blue-200 text-[10px] font-black uppercase tracking-widest">Total</div>
            </div>
            <div class="px-5 py-3 rounded-2xl text-center bg-white/10 border border-white/15">
                <div class="text-2xl font-black text-white"><?php echo $admin_count; ?></div>
                <div class="text-blue-200 text-[10px] font-black uppercase tracking-widest">Admin</div>
            </div>
            <div class="px-5 py-3 rounded-2xl text-center bg-white/10 border border-white/15">
                <div class="text-2xl font-black text-white"><?php echo $user_count; ?></div>
                <div class="text-blue-200 text-[10px] font-black uppercase tracking-widest">User</div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'password_reset'): ?>
    <div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-100 rounded-2xl px-5 py-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        <p class="text-emerald-800 text-sm font-bold">Password berhasil direset ke <code class="bg-emerald-100 px-2 py-0.5 rounded-lg">password123</code>.</p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'cannot_delete_last_admin'): ?>
    <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-100 rounded-2xl px-5 py-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-red-700 text-sm font-bold">Tidak dapat menghapus administrator terakhir.</p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">

        <!-- User Table -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden shadow-sm">
                <div class="px-7 py-5 border-b border-slate-50 flex items-center justify-between">
                    <h3 class="font-black text-slate-900 text-base">Daftar Pengguna</h3>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input id="searchUsers" type="text" placeholder="Cari user..." aria-label="Cari pengguna" oninput="filterUsers(this.value)"
                               class="pl-8 pr-4 py-2 text-xs font-bold rounded-xl border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left" id="usersTable">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-7 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Pengguna</th>
                                <th class="px-7 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest hidden md:table-cell">Email</th>
                                <th class="px-7 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Peran</th>
                                <th class="px-7 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($users as $u):
                                $initials = strtoupper(substr($u['display_name'], 0, 2));
                                $isAdmin  = $u['role'] === 'administrator';
                                $colors   = ['#2563eb','#7c3aed','#059669','#d97706','#dc2626','#0891b2'];
                                $colorIdx = crc32($u['email']) % count($colors);
                                $avatarBg = $colors[abs($colorIdx)];
                                $hasPhoto = !empty($u['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $u['profile_photo']);
                            ?>
                            <tr class="group hover:bg-slate-50 transition-all user-row"
                                data-name="<?php echo strtolower(htmlspecialchars($u['display_name'])); ?>"
                                data-email="<?php echo strtolower(htmlspecialchars($u['email'])); ?>">
                                <td class="px-7 py-5">
                                    <div class="flex items-center gap-3">
                                        <!-- Avatar -->
                                        <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-xs text-white flex-shrink-0 overflow-hidden"
                                             style="background:<?php echo $avatarBg; ?>">
                                            <?php if ($hasPhoto): ?>
                                                <img src="uploads/avatars/<?php echo htmlspecialchars($u['profile_photo']); ?>" alt="<?php echo htmlspecialchars($u['display_name']); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <?php echo $initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="text-sm font-black text-slate-900"><?php echo htmlspecialchars($u['display_name']); ?></div>
                                            <div class="text-[10px] font-bold text-slate-500 md:hidden"><?php echo htmlspecialchars($u['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-7 py-5 text-sm text-slate-600 font-medium hidden md:table-cell">
                                    <?php echo htmlspecialchars($u['email']); ?>
                                </td>
                                <td class="px-7 py-5">
                                    <span class="text-[10px] font-black uppercase px-3 py-1.5 rounded-full <?php echo $isAdmin ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700'; ?>">
                                        <?php echo $isAdmin ? 'Admin' : 'User'; ?>
                                    </span>
                                </td>
                                <td class="px-7 py-5">
                                    <div class="flex items-center gap-1">
                                        <!-- Reset password -->
                                        <button type="button" onclick="resetPassword(<?php echo $u['id']; ?>)"
                                                title="Reset Password ke password123" aria-label="Reset password untuk <?php echo htmlspecialchars($u['display_name']); ?>"
                                                class="w-8 h-8 flex items-center justify-center rounded-xl transition-all text-amber-700 bg-amber-50 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        </button>
                                        <!-- Delete -->
                                        <form method="POST" class="inline" onsubmit="return confirm('Hapus user <?php echo htmlspecialchars($u['email']); ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="delete_user" value="<?php echo $u['id']; ?>">
                                            <button type="submit"
                                                    title="Hapus User" aria-label="Hapus user <?php echo htmlspecialchars($u['display_name']); ?>"
                                                    class="w-8 h-8 flex items-center justify-center rounded-xl transition-all text-rose-700 bg-rose-50 hover:bg-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add User Form -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-[2rem] border border-slate-100 p-7 sticky top-6 shadow-sm">
                <div class="flex items-center gap-3 mb-7">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center bg-blue-600 text-white">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-900">Tambah Pengguna</h3>
                        <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">Akun Baru</p>
                    </div>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="add_user" value="1">

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Nama Lengkap</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            <input type="text" name="name" placeholder="Nama pengguna" required
                                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-3.5 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Email</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            </span>
                            <input type="email" name="email" placeholder="email@domain.com" required
                                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-3.5 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Password</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <input type="password" name="password" placeholder="••••••••" required
                                   class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-10 pr-4 py-3.5 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Peran Sistem</label>
                        <select name="role"
                                class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3.5 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all">
                            <option value="user">User Biasa</option>
                            <option value="administrator">Administrator</option>
                        </select>
                    </div>

                    <button type="submit"
                            class="w-full py-4 rounded-2xl font-bold text-white transition-all active:scale-95 flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Daftarkan Pengguna
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Database Optimization Section -->
    <div class="mb-3">
        <h2 class="text-xl font-black text-slate-900 mb-1">Database &amp; Optimasi</h2>
        <p class="text-slate-500 text-sm font-medium">Kelola performa dan kesehatan database sistem.</p>
    </div>

    <?php if ($message): ?>
    <div class="mb-5 flex items-center gap-3 bg-blue-50 border border-blue-100 rounded-2xl px-5 py-4">
        <p class="text-blue-800 text-sm font-bold"><?php echo htmlspecialchars($message); ?></p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
        <!-- Optimize -->
        <div class="bg-white rounded-[1.5rem] border border-slate-100 p-6 flex flex-col items-center text-center shadow-sm">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4 bg-emerald-100 text-emerald-700">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
            <h3 class="font-black text-slate-900 mb-1">Optimize Database</h3>
            <p class="text-slate-500 text-xs mb-5">Analisis dan optimalkan semua tabel untuk performa maksimal.</p>
            <form method="POST" class="w-full">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="optimize_database" value="1">
                <button type="submit" class="w-full py-2.5 rounded-2xl font-bold text-sm text-white transition-all active:scale-95 bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">Optimize Sekarang</button>
            </form>
        </div>

        <!-- Index -->
        <div class="bg-white rounded-[1.5rem] border border-slate-100 p-6 flex flex-col items-center text-center shadow-sm">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4 bg-blue-100 text-blue-700">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
            </div>
            <h3 class="font-black text-slate-900 mb-1">Index Database</h3>
            <p class="text-slate-500 text-xs mb-5">Buat index untuk mempercepat query dan pencarian data.</p>
            <form method="POST" class="w-full">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="create_indexes" value="1">
                <button type="submit" class="w-full py-2.5 rounded-2xl font-bold text-sm text-white transition-all active:scale-95 bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Buat Index</button>
            </form>
        </div>

        <!-- Repair -->
        <div class="bg-white rounded-[1.5rem] border border-slate-100 p-6 flex flex-col items-center text-center shadow-sm">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4 bg-amber-100 text-amber-800">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </div>
            <h3 class="font-black text-slate-900 mb-1">Repair Tables</h3>
            <p class="text-slate-500 text-xs mb-5">Perbaiki tabel yang rusak atau corrupt dalam database.</p>
            <form method="POST" class="w-full">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="repair_tables" value="1">
                <button type="submit" class="w-full py-2.5 rounded-2xl font-bold text-sm text-white transition-all active:scale-95 bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500">Repair Tables</button>
            </form>
        </div>
    </div>

    <!-- Auto Optimize -->
    <div class="rounded-[1.5rem] border border-purple-200 bg-purple-50/50 p-6 flex flex-col sm:flex-row items-center gap-6 shadow-sm">
        <div class="flex items-center gap-4 flex-1">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 bg-purple-100 text-purple-700">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <h3 class="font-black text-slate-900">Auto Optimize Scheduler</h3>
                <p class="text-slate-600 text-xs">Optimasi seluruh tabel, periksa index, dan catat log aktivitas secara otomatis.</p>
            </div>
        </div>
        <form method="POST" class="flex-shrink-0 w-full sm:w-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="run_auto_optimize" value="1">
            <button type="submit"
                    class="w-full sm:w-auto px-6 py-3 rounded-2xl font-bold text-white text-sm transition-all active:scale-95 flex items-center gap-2 bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                Jalankan Auto Optimize
            </button>
        </form>
    </div>
</div>

<script>
function resetPassword(userId) {
    if (confirm('Reset password user ini ke "password123"?')) {
        const csrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>";
        var form = document.createElement('form');
        form.method = 'POST';

        var csrfInp = document.createElement('input');
        csrfInp.type  = 'hidden';
        csrfInp.name  = 'csrf_token';
        csrfInp.value = csrfToken;

        var resetInp = document.createElement('input');
        resetInp.type  = 'hidden';
        resetInp.name  = 'reset_password';
        resetInp.value = userId;

        form.appendChild(csrfInp);
        form.appendChild(resetInp);
        document.body.appendChild(form);
        form.submit();
    }
}

function filterUsers(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.user-row').forEach(row => {
        const name  = row.dataset.name  || '';
        const email = row.dataset.email || '';
        row.style.display = (!q || name.includes(q) || email.includes(q)) ? '' : 'none';
    });
}
</script>
