<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/admin.php - Executive User & Cloud Storage Management

// -------------------------------------------------------------
// Helper: Consistent Pastel Avatar Styles & Initials
// -------------------------------------------------------------
function userAvatarStyles($name) {
    $palettes = [
        'bg-indigo-50 text-indigo-700 border-indigo-200/80 dark:bg-indigo-950/60 dark:text-indigo-300 dark:border-indigo-800/60',
        'bg-emerald-50 text-emerald-700 border-emerald-200/80 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60',
        'bg-sky-50 text-sky-700 border-sky-200/80 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800/60',
        'bg-purple-50 text-purple-700 border-purple-200/80 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800/60',
        'bg-amber-50 text-amber-700 border-amber-200/80 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/60',
        'bg-rose-50 text-rose-700 border-rose-200/80 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800/60'
    ];
    $idx = abs(crc32($name)) % count($palettes);
    return $palettes[$idx];
}

function getUserInitials($name) {
    $clean = trim($name);
    if (empty($clean)) return 'TF';
    $parts = preg_split('/\s+/', $clean);
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($clean, 0, 2));
}

// -------------------------------------------------------------
// 1. Data Retrieval: Users & Aggregated Storage
// -------------------------------------------------------------
$stmt = $pdo->query("
    SELECT 
        u.*, 
        COALESCE(SUM(d.file_size), 0) AS used_bytes, 
        COUNT(d.id) AS doc_count 
    FROM users u 
    LEFT JOIN documents d ON d.user_id = u.id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Global System Storage Aggregation
$total_server_bytes = 0;
$total_allocated_bytes = 0;
$unlimited_users_count = 0;
$total_docs_count = 0;

foreach ($users as $u) {
    $total_server_bytes += (int)$u['used_bytes'];
    $total_docs_count += (int)$u['doc_count'];
    if ((int)$u['storage_quota'] === 0) {
        $unlimited_users_count++;
    } else {
        $total_allocated_bytes += (int)$u['storage_quota'];
    }
}
$total_server_formatted = formatSize($total_server_bytes);
$total_allocated_formatted = formatSize($total_allocated_bytes);
$global_util_pct = $total_allocated_bytes > 0 ? round(($total_server_bytes / $total_allocated_bytes) * 100, 2) : 0;
$default_system_quota = (int)(getSetting('default_storage_quota', '5368709120'));

// Active Tab
$current_tab = isset($_GET['tab']) && in_array($_GET['tab'], ['users', 'storage', 'database']) ? $_GET['tab'] : 'users';

// -------------------------------------------------------------
// 2. Action Handlers (POST)
// -------------------------------------------------------------
// Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $email  = trim($_POST['email']);
        $name   = trim($_POST['name']);
        $role   = $_POST['role'] === 'administrator' ? 'administrator' : 'user';
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';
        $quota  = isset($_POST['storage_quota']) ? (int)$_POST['storage_quota'] : $default_system_quota;
        $pass   = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Check duplicate email
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            header("Location: index.php?page=admin&tab=users&error=email_exists");
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO users (email, password, display_name, role, status, storage_quota) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$email, $pass, $name, $role, $status, $quota]);
        
        logActivity("Mendaftarkan pengguna baru: " . $name . " (" . $email . ")", "admin_user");
        header("Location: index.php?page=admin&tab=users&success=user_added&name=" . urlencode($name));
        exit;
    }
}

// Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $target_id = (int)$_POST['target_user_id'];
        $name      = trim($_POST['name']);
        $email     = trim($_POST['email']);
        $role      = $_POST['role'] === 'administrator' ? 'administrator' : 'user';
        $status    = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        // Check if demoting or deactivating last admin
        $stmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $current = $stmt->fetch();

        $active_admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'administrator' AND status = 'active'")->fetchColumn();

        if ($current && $current['role'] === 'administrator') {
            if ($role !== 'administrator' && $active_admins <= 1) {
                header("Location: index.php?page=admin&tab=users&error=cannot_demote_last_admin");
                exit;
            }
            if ($status === 'inactive' && $active_admins <= 1) {
                header("Location: index.php?page=admin&tab=users&error=cannot_suspend_last_admin");
                exit;
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET display_name = ?, email = ?, role = ?, status = ? WHERE id = ?");
        $stmt->execute([$name, $email, $role, $status, $target_id]);

        logActivity("Memperbarui data pengguna: " . $name, "admin_user");
        header("Location: index.php?page=admin&tab=users&success=user_updated&name=" . urlencode($name));
        exit;
    }
}

// Toggle User Status (Active / Inactive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_status'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $target_id = (int)$_POST['target_user_id'];
        $stmt = $pdo->prepare("SELECT role, status, display_name FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $user_row = $stmt->fetch();

        if ($user_row) {
            $new_status = ($user_row['status'] === 'active') ? 'inactive' : 'active';
            
            // Check if deactivating last active admin
            if ($user_row['role'] === 'administrator' && $new_status === 'inactive') {
                $active_admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'administrator' AND status = 'active'")->fetchColumn();
                if ($active_admins <= 1) {
                    header("Location: index.php?page=admin&tab=users&error=cannot_suspend_last_admin");
                    exit;
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $target_id]);

            $statusText = ($new_status === 'active') ? 'diaktifkan' : 'dinonaktifkan';
            logActivity("Akun {$user_row['display_name']} berhasil {$statusText}", "admin_user");
            header("Location: index.php?page=admin&tab=users&success=status_changed&name=" . urlencode($user_row['display_name']) . "&status=" . $new_status);
            exit;
        }
    }
}

// Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $user_id = (int)$_POST['delete_user'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'administrator'");
        $stmt->execute();
        $admin_count = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT role, display_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $target = $stmt->fetch();

        if ($target && $target['role'] === 'administrator' && $admin_count <= 1) {
            header("Location: index.php?page=admin&tab=users&error=cannot_delete_last_admin");
            exit;
        }

        if ($target) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            logActivity("Menghapus akun pengguna: " . $target['display_name'], "admin_user");
        }
        header("Location: index.php?page=admin&tab=users&success=user_deleted");
        exit;
    }
}

// Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $user_id  = (int)$_POST['reset_password'];
        $new_pass = password_hash('password123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_pass, $user_id]);
        logActivity("Mereset password user ID " . $user_id . " ke bawaan", "admin_user");
        header("Location: index.php?page=admin&tab=" . $current_tab . "&success=password_reset");
        exit;
    }
}

// Update User Storage Quota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_quota'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $target_id  = (int)$_POST['target_user_id'];
        $quota_mode = $_POST['quota_mode'] ?? 'preset';

        if ($quota_mode === 'unlimited') {
            $new_quota = 0; // 0 = unlimited
        } elseif ($quota_mode === 'custom') {
            $val  = max(0, (float)($_POST['custom_val'] ?? 0));
            $unit = $_POST['custom_unit'] ?? 'GB';
            if ($unit === 'MB') {
                $new_quota = (int)round($val * 1024 * 1024);
            } elseif ($unit === 'TB') {
                $new_quota = (int)round($val * 1024 * 1024 * 1024 * 1024);
            } else { // GB default
                $new_quota = (int)round($val * 1024 * 1024 * 1024);
            }
        } else {
            $new_quota = max(0, (int)($_POST['preset_bytes'] ?? 5368709120));
        }

        $stmt = $pdo->prepare("UPDATE users SET storage_quota = ? WHERE id = ?");
        $stmt->execute([$new_quota, $target_id]);

        $uChk = $pdo->prepare("SELECT display_name FROM users WHERE id = ?");
        $uChk->execute([$target_id]);
        $target_name = $uChk->fetchColumn() ?: 'Pengguna';

        $qLabel = ($new_quota === 0) ? 'Tanpa Batas (Unlimited)' : formatSize($new_quota);
        logActivity("Mengubah kuota penyimpanan {$target_name} menjadi {$qLabel}", "admin_storage");

        $redirect_tab = isset($_POST['redirect_tab']) && in_array($_POST['redirect_tab'], ['users', 'storage']) ? $_POST['redirect_tab'] : 'storage';
        header("Location: index.php?page=admin&tab=" . $redirect_tab . "&success=quota_updated&name=" . urlencode($target_name) . "&quota=" . urlencode($qLabel));
        exit;
    }
}

// Update Default System Quota
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default_quota'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $new_default = max(0, (int)$_POST['default_quota_bytes']);
        setSetting('default_storage_quota', (string)$new_default);

        $msg_suffix = '';
        if (!empty($_POST['apply_to_all_users'])) {
            $stmt = $pdo->prepare("UPDATE users SET storage_quota = ?");
            $stmt->execute([$new_default]);
            $msg_suffix = '&applied=all';
        }

        $defLabel = ($new_default === 0) ? 'Tanpa Batas' : formatSize($new_default);
        logActivity("Memperbarui kuota default sistem menjadi {$defLabel}", "admin_storage");

        header("Location: index.php?page=admin&tab=storage&success=default_updated" . $msg_suffix);
        exit;
    }
}

// Database Optimization Actions
$db_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['optimize_database'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        try {
            clearCache();
            $tables = ['users', 'todos', 'notes', 'documents'];
            foreach ($tables as $table) {
                $pdo->prepare("ANALYZE TABLE $table")->execute();
                $pdo->prepare("OPTIMIZE TABLE $table")->execute();
            }
            $db_message = "✅ Optimasi database selesai untuk " . count($tables) . " tabel. Cache dibersihkan.";
        } catch (Exception $e) {
            $db_message = "❌ Optimasi gagal: " . $e->getMessage();
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
            $db_message = "✅ Indexing selesai. Dibuat/diverifikasi $created indeks.";
        } catch (Exception $e) {
            $db_message = "❌ Indexing gagal: " . $e->getMessage();
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
            $db_message = "✅ Repair selesai. $repaired tabel diperbaiki.";
        } catch (Exception $e) {
            $db_message = "❌ Repair gagal: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_auto_optimize'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        try {
            shell_exec('php ' . __DIR__ . '/../auto_optimize.php 2>&1');
            $db_message = "✅ Auto-optimasi selesai. Periksa log untuk detailnya.";
        } catch (Exception $e) {
            $db_message = "❌ Auto-optimasi gagal: " . $e->getMessage();
        }
    }
}

// User role stats
$admin_count = count(array_filter($users, fn($u) => $u['role'] === 'administrator'));
$user_count  = count($users) - $admin_count;
?>

<div class="p-4 md:p-8 w-full space-y-6">

    <!-- ============================================================== -->
    <!-- EXECUTIVE GLASSMORPHIC HEADER & UNIFIED METRIC CAPSULE         -->
    <!-- ============================================================== -->
    <div class="rounded-3xl p-6 md:p-7 bg-white/76 backdrop-blur-xl border border-white/85 shadow-sm relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-black uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Administrator Command Center
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white leading-tight tracking-tight mb-1">
                <?php 
                if ($current_tab === 'storage') echo "Manajemen Penyimpanan Cloud &amp; Kuota";
                elseif ($current_tab === 'database') echo "Database &amp; Performa Sistem";
                else echo "Manajemen Pengguna &amp; Hak Akses";
                ?>
            </h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm font-medium">
                <?php 
                if ($current_tab === 'storage') echo "Kelola alokasi kuota penyimpanan untuk masing-masing pengguna dan pantau konsumsi disk server.";
                elseif ($current_tab === 'database') echo "Pemeliharaan berkala tabel basis data, defragmentasi, dan indexing performa TaskFlow.";
                else echo "Kelola akun pengguna, peran hak akses organisasi, status akun, dan alokasi penyimpanan.";
                ?>
            </p>
        </div>

        <!-- Unified Executive Metric Capsule (Thin vertical borders) -->
        <div class="flex items-center rounded-2xl bg-slate-100/90 dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700 shadow-xs divide-x divide-slate-200/80 dark:divide-slate-700 overflow-hidden flex-shrink-0">
            <div class="px-5 py-2.5 text-center min-w-[76px]">
                <div class="text-xl font-black text-slate-900 dark:text-white leading-tight"><?php echo count($users); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total User</div>
            </div>
            <div class="px-5 py-2.5 text-center min-w-[100px]">
                <div class="text-xl font-black text-indigo-600 dark:text-indigo-400 leading-tight"><?php echo $total_server_formatted; ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Disk Terpakai</div>
            </div>
            <div class="px-5 py-2.5 text-center min-w-[76px]">
                <div class="text-xl font-black text-slate-900 dark:text-white leading-tight"><?php echo $admin_count; ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Admin</div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- MODERN SEGMENTED NAVIGATION TABS                               -->
    <!-- ============================================================== -->
    <div class="inline-flex p-1.5 bg-slate-100/90 dark:bg-slate-800/80 rounded-2xl border border-slate-200/90 dark:border-slate-700/80 gap-1.5">
        <!-- Tab 1: Pengguna & Peran -->
        <a href="index.php?page=admin&tab=users" 
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition-all <?php echo $current_tab === 'users' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-white font-black shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 font-bold hover:bg-white/50 dark:hover:bg-slate-800'; ?>">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span>Pengguna &amp; Peran</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black <?php echo $current_tab === 'users' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300' : 'bg-slate-200/70 dark:bg-slate-700 text-slate-500'; ?>"><?php echo count($users); ?></span>
        </a>

        <!-- Tab 2: Penyimpanan & Kuota -->
        <a href="index.php?page=admin&tab=storage" 
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition-all <?php echo $current_tab === 'storage' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-white font-black shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 font-bold hover:bg-white/50 dark:hover:bg-slate-800'; ?>">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
            <span>Penyimpanan Cloud &amp; Kuota</span>
            <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black <?php echo $current_tab === 'storage' ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200/70 dark:bg-slate-700 text-slate-500'; ?>"><?php echo $total_server_formatted; ?></span>
        </a>

        <!-- Tab 3: Database & Performa -->
        <a href="index.php?page=admin&tab=database" 
           class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs transition-all <?php echo $current_tab === 'database' ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-white font-black shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 font-bold hover:bg-white/50 dark:hover:bg-slate-800'; ?>">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            <span>Database &amp; Performa</span>
        </a>
    </div>

    <!-- ============================================================== -->
    <!-- ALERTS & SYSTEM FEEDBACK                                       -->
    <!-- ============================================================== -->
    <?php if (isset($_GET['success'])): ?>
    <div class="flex items-center gap-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl px-5 py-3.5 shadow-xs">
        <div class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center text-emerald-700 dark:text-emerald-300 flex-shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p class="text-emerald-900 dark:text-emerald-200 text-xs font-bold">
            <?php 
            if ($_GET['success'] === 'user_added') echo "Pengguna baru <strong>" . htmlspecialchars($_GET['name'] ?? '') . "</strong> berhasil didaftarkan.";
            elseif ($_GET['success'] === 'user_updated') echo "Data pengguna <strong>" . htmlspecialchars($_GET['name'] ?? '') . "</strong> berhasil diperbarui.";
            elseif ($_GET['success'] === 'status_changed') echo "Status akun <strong>" . htmlspecialchars($_GET['name'] ?? '') . "</strong> berhasil diubah menjadi " . (($_GET['status'] ?? '') === 'active' ? 'Aktif' : 'Nonaktif') . ".";
            elseif ($_GET['success'] === 'user_deleted') echo "Akun pengguna berhasil dihapus permanen dari sistem.";
            elseif ($_GET['success'] === 'password_reset') echo "Password pengguna berhasil direset ke sandi bawaan (password123).";
            elseif ($_GET['success'] === 'quota_updated') echo "Batas kuota penyimpanan untuk <strong>" . htmlspecialchars($_GET['name'] ?? 'Pengguna') . "</strong> berhasil diperbarui menjadi <strong>" . htmlspecialchars($_GET['quota'] ?? '') . "</strong>.";
            elseif ($_GET['success'] === 'default_updated') echo "Kebijakan kuota bawaan sistem berhasil diperbarui" . (isset($_GET['applied']) ? " dan langsung diterapkan ke seluruh pengguna." : ".");
            else echo "Operasi administratif berhasil dijalankan.";
            ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="flex items-center gap-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 rounded-2xl px-5 py-3.5 shadow-xs">
        <svg class="w-5 h-5 flex-shrink-0 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-rose-900 dark:text-rose-200 text-xs font-bold">
            <?php 
            if ($_GET['error'] === 'cannot_delete_last_admin') echo "Tindakan ditolak: Tidak dapat menghapus administrator terakhir di dalam sistem.";
            elseif ($_GET['error'] === 'cannot_demote_last_admin') echo "Tindakan ditolak: Tidak dapat menurunkan peran administrator terakhir menjadi pengguna biasa.";
            elseif ($_GET['error'] === 'cannot_suspend_last_admin') echo "Tindakan ditolak: Tidak dapat menonaktifkan akun administrator aktif terakhir di dalam sistem.";
            elseif ($_GET['error'] === 'email_exists') echo "Pendaftaran gagal: Alamat email tersebut sudah terdaftar untuk pengguna lain.";
            else echo "Terjadi kesalahan dalam memproses permintaan Anda.";
            ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- TAB 1: PENGGUNA & PERAN (100% FULL-WIDTH RESTRUCTURED TABLE)   -->
    <!-- ============================================================== -->
    <?php if ($current_tab === 'users'): ?>
    
    <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 overflow-hidden shadow-sm">
        
        <!-- Table Toolbar with Proximity Button -->
        <div class="px-6 py-5 border-b border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Daftar Pengguna</h3>
                <p class="text-slate-400 text-xs">Total <?php echo count($users); ?> pengguna terdaftar di sistem</p>
            </div>
            
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Search Bar -->
                <div class="relative w-full sm:w-64">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input id="searchUsers" type="text" placeholder="Cari user..." aria-label="Cari pengguna" oninput="filterUsers(this.value)"
                           class="w-full pl-9 pr-4 py-2 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                </div>

                <!-- Primary Action: + Tambah Pengguna (Opens Drawer) -->
                <button type="button" onclick="openAddUserDrawer()"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center gap-2 shadow-sm shadow-indigo-600/20 active:scale-95 transition-all">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Tambah Pengguna</span>
                </button>
            </div>
        </div>

        <!-- Full-Width Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="usersTable">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200/80 dark:border-slate-800">
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Pengguna</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest hidden md:table-cell">Email</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Peran</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Penyimpanan</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach($users as $u):
                        $initials    = getUserInitials($u['display_name']);
                        $avatarClass = userAvatarStyles($u['display_name']);
                        $isAdmin     = $u['role'] === 'administrator';
                        $isActive    = ($u['status'] ?? 'active') === 'active';
                        $hasPhoto    = !empty($u['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $u['profile_photo']);
                        $usedBytes   = (int)$u['used_bytes'];
                        $quotaBytes  = (int)($u['storage_quota'] ?? 5368709120);
                        $isUnlimited = ($quotaBytes === 0);
                        $quotaLabel  = $isUnlimited ? 'Tanpa Batas' : formatSize($quotaBytes);
                        $usedLabel   = formatSize($usedBytes);
                        $pct         = $isUnlimited ? 0 : round(($usedBytes / max(1, $quotaBytes)) * 100, 1);
                        
                        // Progress bar color
                        if ($isUnlimited) {
                            $barColor = 'bg-indigo-500';
                        } elseif ($pct >= 90) {
                            $barColor = 'bg-rose-500';
                        } elseif ($pct >= 75) {
                            $barColor = 'bg-amber-500';
                        } else {
                            $barColor = 'bg-emerald-500';
                        }
                    ?>
                    <tr class="group hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors user-row"
                        data-name="<?php echo strtolower(htmlspecialchars($u['display_name'])); ?>"
                        data-email="<?php echo strtolower(htmlspecialchars($u['email'])); ?>">
                        
                        <!-- Pengguna & Avatar Hashed Pastel -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-xs border flex-shrink-0 overflow-hidden shadow-xs <?php echo $avatarClass; ?>">
                                    <?php if ($hasPhoto): ?>
                                        <img src="uploads/avatars/<?php echo htmlspecialchars($u['profile_photo']); ?>" alt="<?php echo htmlspecialchars($u['display_name']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-slate-900 dark:text-white leading-snug"><?php echo htmlspecialchars($u['display_name']); ?></div>
                                    <div class="text-xs text-slate-400 font-medium md:hidden"><?php echo htmlspecialchars($u['email']); ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Email -->
                        <td class="px-6 py-4 text-xs font-medium text-slate-600 dark:text-slate-400 hidden md:table-cell">
                            <?php echo htmlspecialchars($u['email']); ?>
                        </td>

                        <!-- Role Badge (Distinct Indigo vs Slate) -->
                        <td class="px-6 py-4">
                            <?php if ($isAdmin): ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                                    Administrator
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    User
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Penyimpanan & Kuota with Mini Progress Bar (4px height) -->
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1.5 min-w-[150px]">
                                <div class="flex items-baseline justify-between text-[11px] leading-tight">
                                    <span class="font-bold text-slate-900 dark:text-white"><?php echo $usedLabel; ?></span>
                                    <span class="text-slate-400 font-medium text-[10px]">/ <?php echo $quotaLabel; ?></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden border border-slate-200/60 dark:border-slate-700">
                                    <div class="h-full rounded-full transition-all duration-300 <?php echo $barColor; ?>" 
                                         style="width: <?php echo $isUnlimited ? '100' : max(3, min(100, $pct)); ?>%"></div>
                                </div>
                            </div>
                        </td>

                        <!-- Status Akun (Aktif / Nonaktif) -->
                        <td class="px-6 py-4 text-center">
                            <?php if ($isActive): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200/60 dark:border-emerald-800/60">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Aktif
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold text-slate-600 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Nonaktif
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Aksi Dropdown (Three Dots Menu) -->
                        <td class="px-6 py-4 text-right">
                            <div class="relative inline-block text-left action-dropdown-container">
                                <button type="button" onclick="toggleActionMenu(event, 'actionMenu-<?php echo $u['id']; ?>')"
                                        aria-label="Menu aksi untuk <?php echo htmlspecialchars($u['display_name']); ?>"
                                        class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition-all active:scale-95 shadow-xs">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                                </button>
                                
                                <div id="actionMenu-<?php echo $u['id']; ?>" 
                                     class="action-menu-dropdown hidden absolute right-0 mt-1.5 w-48 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xl py-1.5 z-40 text-left transition-all backdrop-blur-md">
                                    
                                    <!-- Edit User Profile & Role -->
                                    <button type="button" 
                                            onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode([
                                                'id'           => $u['id'],
                                                'display_name' => $u['display_name'],
                                                'email'        => $u['email'],
                                                'role'         => $u['role'],
                                                'status'       => $u['status'] ?? 'active'
                                            ]), ENT_QUOTES, 'UTF-8'); ?>)" 
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 flex items-center gap-2.5 transition-colors">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                        Edit Akun
                                    </button>

                                    <!-- Adjust Quota -->
                                    <button type="button" 
                                            onclick="openQuotaModal(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['display_name'])); ?>', '<?php echo addslashes(htmlspecialchars($u['email'])); ?>', <?php echo $usedBytes; ?>, <?php echo $quotaBytes; ?>, 'users')" 
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 flex items-center gap-2.5 transition-colors">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                        Atur Kuota
                                    </button>

                                    <!-- Reset Password -->
                                    <button type="button" 
                                            onclick="resetPassword(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['display_name'])); ?>')" 
                                            class="w-full px-3.5 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/80 flex items-center gap-2.5 transition-colors">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        Reset Password
                                    </button>

                                    <!-- Toggle Status (Activate / Suspend) -->
                                    <button type="button" 
                                            onclick="toggleUserStatus(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['display_name'])); ?>', '<?php echo $isActive ? 'nonaktifkan' : 'aktifkan'; ?>')" 
                                            class="w-full px-3.5 py-2 text-xs font-bold <?php echo $isActive ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'; ?> hover:bg-slate-50 dark:hover:bg-slate-800/80 flex items-center gap-2.5 transition-colors">
                                        <?php if ($isActive): ?>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/></svg>
                                            Nonaktifkan Akun
                                        <?php else: ?>
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                            Aktifkan Akun
                                        <?php endif; ?>
                                    </button>

                                    <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>

                                    <!-- Delete User -->
                                    <button type="button" 
                                            onclick="confirmDeleteUser(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['display_name'])); ?>')" 
                                            class="w-full px-3.5 py-2 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2.5 transition-colors">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        Hapus Pengguna
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- TAB 2: MANAJEMEN PENYIMPANAN & KUOTA USER                      -->
    <!-- ============================================================== -->
    <?php if ($current_tab === 'storage'): ?>
    
    <!-- 4 Global Storage KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Disk Terpakai -->
        <div class="rounded-3xl p-5 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">Penyimpanan Terpakai</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-100 dark:border-emerald-800/60">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </div>
                </div>
                <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
                    <?php echo $total_server_formatted; ?>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 font-semibold mt-3">
                dari <?php echo $total_docs_count; ?> total berkas terunggah
            </div>
        </div>

        <!-- Card 2: Total Kuota Dialokasikan -->
        <div class="rounded-3xl p-5 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">Total Kuota Alokasi</span>
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-800/60">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                    </div>
                </div>
                <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
                    <?php echo $total_allocated_formatted; ?>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 font-semibold mt-3">
                <?php echo $unlimited_users_count; ?> akun tanpa batas kuota
            </div>
        </div>

        <!-- Card 3: Utilisasi Server Global -->
        <div class="rounded-3xl p-5 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">Rasio Utilisasi</span>
                    <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                </div>
                <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
                    <?php echo $global_util_pct; ?>%
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-gradient-to-r from-sky-400 to-indigo-500 h-full rounded-full" style="width: <?php echo max(3, min(100, $global_util_pct)); ?>%"></div>
                </div>
                <div class="text-[10px] text-slate-400 font-bold mt-1.5">Kapasitas penyimpanan server aman</div>
            </div>
        </div>

        <!-- Card 4: Kuota Bawaan Sistem -->
        <div class="rounded-3xl p-5 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border border-slate-200/90 dark:border-slate-800 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">Bawaan Pengguna Baru</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-800/60">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </div>
                </div>
                <div class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-none">
                    <?php echo $default_system_quota === 0 ? 'Unlimited' : formatSize($default_system_quota); ?>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 font-semibold mt-3">
                Dapat disesuaikan per user
            </div>
        </div>
    </div>

    <!-- Main Storage Quota Table & Controls -->
    <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 overflow-hidden shadow-sm">
        
        <!-- Table Toolbar -->
        <div class="px-6 py-5 border-b border-slate-200/80 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Alokasi Kuota Penyimpanan Per-Pengguna</h3>
                <p class="text-slate-400 text-xs">Atur batas kuota, pantau kapasitas terpakai, dan tetapkan kuota tak terbatas.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input id="searchStorageUsers" type="text" placeholder="Cari pengguna..." oninput="filterStorageUsers(this.value)"
                           class="pl-8 pr-4 py-2 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left" id="storageUsersTable">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200/80 dark:border-slate-800">
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Pengguna</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Berkas</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Terpakai</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Batas Kuota</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Penggunaan (%)</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-center">Status</th>
                        <th class="px-6 py-3.5 text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach($users as $u):
                        $initials    = getUserInitials($u['display_name']);
                        $avatarClass = userAvatarStyles($u['display_name']);
                        $isAdmin     = $u['role'] === 'administrator';
                        $hasPhoto    = !empty($u['profile_photo']) && file_exists(__DIR__ . '/../uploads/avatars/' . $u['profile_photo']);
                        $usedBytes   = (int)$u['used_bytes'];
                        $quotaBytes  = (int)($u['storage_quota'] ?? 5368709120);
                        $isUnlimited = ($quotaBytes === 0);
                        $quotaLabel  = $isUnlimited ? 'Tanpa Batas' : formatSize($quotaBytes);
                        $usedLabel   = formatSize($usedBytes);
                        $pct         = $isUnlimited ? 0 : round(($usedBytes / max(1, $quotaBytes)) * 100, 1);
                        
                        // Status badge logic
                        if ($isUnlimited) {
                            $statusBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/60">♾️ Bebas</span>';
                        } elseif ($pct >= 90) {
                            $statusBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">🔴 Kritis</span>';
                        } elseif ($pct >= 75) {
                            $statusBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">🟡 Peringatan</span>';
                        } else {
                            $statusBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">🟢 Normal</span>';
                        }
                    ?>
                    <tr class="group hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors storage-row"
                        data-name="<?php echo strtolower(htmlspecialchars($u['display_name'])); ?>"
                        data-email="<?php echo strtolower(htmlspecialchars($u['email'])); ?>">
                        
                        <!-- User Name & Email -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-xs border flex-shrink-0 overflow-hidden shadow-xs <?php echo $avatarClass; ?>">
                                    <?php if ($hasPhoto): ?>
                                        <img src="uploads/avatars/<?php echo htmlspecialchars($u['profile_photo']); ?>" alt="<?php echo htmlspecialchars($u['display_name']); ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <?php echo $initials; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($u['display_name']); ?></span>
                                        <?php if ($isAdmin): ?>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950 dark:text-indigo-300 dark:border-indigo-800">Admin</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($u['email']); ?></div>
                                </div>
                            </div>
                        </td>

                        <!-- Doc Count -->
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-black bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                <?php echo $u['doc_count']; ?>
                            </span>
                        </td>

                        <!-- Used Size -->
                        <td class="px-6 py-4">
                            <span class="text-sm font-black text-slate-900 dark:text-white"><?php echo $usedLabel; ?></span>
                        </td>

                        <!-- Quota Limit -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-bold <?php echo $isUnlimited ? 'text-indigo-600 dark:text-indigo-400 font-black' : 'text-slate-700 dark:text-slate-300'; ?>">
                                    <?php echo $quotaLabel; ?>
                                </span>
                            </div>
                        </td>

                        <!-- Progress Bar & % -->
                        <td class="px-6 py-4">
                            <div class="w-36 space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-bold">
                                    <span class="text-slate-500"><?php echo $isUnlimited ? 'Unlimited' : $pct . '%'; ?></span>
                                    <span class="text-slate-400"><?php echo $isUnlimited ? 'Bebas' : formatSize(max(0, $quotaBytes - $usedBytes)) . ' sisa'; ?></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden border border-slate-200/60 dark:border-slate-700">
                                    <div class="h-full rounded-full transition-all duration-300 <?php echo $isUnlimited ? 'bg-indigo-500' : ($pct >= 90 ? 'bg-rose-500' : ($pct >= 75 ? 'bg-amber-500' : 'bg-emerald-500')); ?>" 
                                         style="width: <?php echo $isUnlimited ? '100' : max(3, min(100, $pct)); ?>%"></div>
                                </div>
                            </div>
                        </td>

                        <!-- Status Badge -->
                        <td class="px-6 py-4 text-center">
                            <?php echo $statusBadge; ?>
                        </td>

                        <!-- Action: Adjust Quota -->
                        <td class="px-6 py-4 text-right">
                            <button type="button" 
                                    onclick="openQuotaModal(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['display_name'])); ?>', '<?php echo addslashes(htmlspecialchars($u['email'])); ?>', <?php echo $usedBytes; ?>, <?php echo $quotaBytes; ?>, 'storage')"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl font-bold text-xs text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800/60 transition-all active:scale-95 shadow-xs">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                <span>Atur Kuota</span>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Default System Storage Policy Settings Card -->
    <div class="rounded-3xl p-6 md:p-8 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border border-slate-200/90 dark:border-slate-800 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold uppercase tracking-widest mb-2">
                    <svg class="w-3.5 h-3.5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Kebijakan Global Organisasi
                </div>
                <h4 class="text-lg font-black text-slate-900 dark:text-white">Batas Kuota Bawaan untuk Pengguna Baru</h4>
                <p class="text-slate-500 dark:text-slate-400 text-xs mt-1 leading-relaxed">
                    Setiap kali pengguna baru mendaftar atau didaftarkan oleh administrator, kuota ini akan otomatis dialokasikan. Anda juga dapat memilih untuk memperbarui kuota seluruh pengguna lama agar seragam.
                </p>
            </div>

            <form method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-shrink-0">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="set_default_quota" value="1">

                <div class="flex flex-col gap-2">
                    <select name="default_quota_bytes" 
                            class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                        <option value="1073741824" <?php echo $default_system_quota === 1073741824 ? 'selected' : ''; ?>>1 GB</option>
                        <option value="2147483648" <?php echo $default_system_quota === 2147483648 ? 'selected' : ''; ?>>2 GB</option>
                        <option value="5368709120" <?php echo $default_system_quota === 5368709120 ? 'selected' : ''; ?>>5 GB (Standar)</option>
                        <option value="10737418240" <?php echo $default_system_quota === 10737418240 ? 'selected' : ''; ?>>10 GB</option>
                        <option value="26843545600" <?php echo $default_system_quota === 26843545600 ? 'selected' : ''; ?>>25 GB</option>
                        <option value="53687091200" <?php echo $default_system_quota === 53687091200 ? 'selected' : ''; ?>>50 GB</option>
                        <option value="0" <?php echo $default_system_quota === 0 ? 'selected' : ''; ?>>♾️ Tanpa Batas (Unlimited)</option>
                    </select>

                    <label class="flex items-center gap-2 text-[11px] font-semibold text-slate-600 dark:text-slate-400 cursor-pointer">
                        <input type="checkbox" name="apply_to_all_users" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500/20">
                        <span>Terapkan juga ke semua pengguna saat ini</span>
                    </label>
                </div>

                <button type="submit" 
                        class="px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-indigo-600 hover:bg-indigo-700 transition-all active:scale-95 shadow-sm shadow-indigo-600/20 self-start sm:self-center">
                    Simpan Kebijakan
                </button>
            </form>
        </div>
    </div>

    <?php endif; ?>

    <!-- ============================================================== -->
    <!-- TAB 3: DATABASE & PERFORMA                                     -->
    <!-- ============================================================== -->
    <?php if ($current_tab === 'database'): ?>
    <div class="mb-4">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Database &amp; Performa</h2>
        <p class="text-slate-500 dark:text-slate-400 text-xs font-medium">Pemeliharaan, defragmentasi, dan optimasi tabel database TaskFlow.</p>
    </div>

    <?php if ($db_message): ?>
    <div class="mb-5 flex items-center gap-3 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl px-5 py-4">
        <p class="text-slate-800 dark:text-slate-200 text-sm font-bold"><?php echo htmlspecialchars($db_message); ?></p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
        <!-- Optimize -->
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 p-6 flex flex-col items-center text-center shadow-sm">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            </div>
            <h3 class="font-bold text-slate-900 dark:text-white text-sm mb-1">Optimize Database</h3>
            <p class="text-slate-500 dark:text-slate-400 text-xs mb-5">Analisis dan defrag seluruh tabel untuk kestabilan performa.</p>
            <form method="POST" class="w-full mt-auto">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="optimize_database" value="1">
                <button type="submit" class="w-full py-2.5 rounded-xl font-bold text-xs text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-all">Optimize Sekarang</button>
            </form>
        </div>

        <!-- Index -->
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 p-6 flex flex-col items-center text-center shadow-sm">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
            </div>
            <h3 class="font-bold text-slate-900 dark:text-white text-sm mb-1">Index Database</h3>
            <p class="text-slate-500 dark:text-slate-400 text-xs mb-5">Verifikasi indeks tabel untuk mempercepat waktu query.</p>
            <form method="POST" class="w-full mt-auto">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="create_indexes" value="1">
                <button type="submit" class="w-full py-2.5 rounded-xl font-bold text-xs text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-all">Buat Index</button>
            </form>
        </div>

        <!-- Repair -->
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm rounded-3xl border border-slate-200/90 dark:border-slate-800 p-6 flex flex-col items-center text-center shadow-sm">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </div>
            <h3 class="font-bold text-slate-900 dark:text-white text-sm mb-1">Repair Tables</h3>
            <p class="text-slate-500 dark:text-slate-400 text-xs mb-5">Periksa dan perbaiki integritas tabel database yang bermasalah.</p>
            <form method="POST" class="w-full mt-auto">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="repair_tables" value="1">
                <button type="submit" class="w-full py-2.5 rounded-xl font-bold text-xs text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 transition-all">Repair Tables</button>
            </form>
        </div>
    </div>

    <!-- Auto Optimize -->
    <div class="rounded-3xl border border-slate-200/90 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm p-6 flex flex-col sm:flex-row items-center gap-6 shadow-sm">
        <div class="flex items-center gap-4 flex-1">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-sm">Auto Optimize Scheduler</h3>
                <p class="text-slate-500 dark:text-slate-400 text-xs">Jalankan seluruh rangkaian pemeliharaan tabel dan pembersihan cache sistem.</p>
            </div>
        </div>
        <form method="POST" class="flex-shrink-0 w-full sm:w-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="run_auto_optimize" value="1">
            <button type="submit"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl font-bold text-white text-xs transition-all active:scale-95 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                Jalankan Auto Optimize
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>

<!-- ============================================================== -->
<!-- 1. SLIDE-OVER SHEET (DRAWER): TAMBAH PENGGUNA BARU            -->
<!-- ============================================================== -->
<div id="addUserDrawerOverlay" class="fixed inset-0 hidden z-50 bg-slate-950/60 backdrop-blur-sm transition-opacity" onclick="closeAddUserDrawer()">
    <div id="addUserDrawer" 
         class="fixed right-0 top-0 bottom-0 w-full max-w-md bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-800 shadow-2xl flex flex-col z-50 transform translate-x-full transition-transform duration-300 ease-in-out" 
         onclick="event.stopPropagation()">
        
        <!-- Drawer Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/70 dark:bg-slate-800/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/25">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white">Tambah Pengguna Baru</h3>
                    <p class="text-xs text-slate-400">Pendaftaran akun &amp; alokasi kuota awal</p>
                </div>
            </div>
            <button type="button" onclick="closeAddUserDrawer()" aria-label="Tutup form" class="w-8 h-8 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Drawer Form Body -->
        <form method="POST" class="p-6 space-y-4 overflow-y-auto flex-1">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="add_user" value="1">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                <input type="text" name="name" placeholder="Nama pengguna" required
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Email</label>
                <input type="email" name="email" placeholder="email@domain.com" required
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Password</label>
                <input type="password" name="password" placeholder="••••••••" required
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Peran</label>
                    <select name="role"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                        <option value="user">User Biasa</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Status Akun</label>
                    <select name="status"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                        <option value="active" selected>● Aktif</option>
                        <option value="inactive">● Nonaktif</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Batas Kuota Penyimpanan</label>
                <select name="storage_quota"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                    <option value="1073741824">1 GB (Kecil)</option>
                    <option value="2147483648">2 GB (Standar Ringan)</option>
                    <option value="5368709120" selected>5 GB (Bawaan Sistem)</option>
                    <option value="10737418240">10 GB (Eksekutif)</option>
                    <option value="26843545600">25 GB (Kapasitas Besar)</option>
                    <option value="53687091200">50 GB (Enterprise)</option>
                    <option value="0">♾️ Tanpa Batas (Unlimited)</option>
                </select>
            </div>

            <!-- Action Buttons in Drawer -->
            <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeAddUserDrawer()"
                        class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all text-xs">
                    Batal
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl font-bold text-white transition-all text-xs flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/25 active:scale-95">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Daftarkan Pengguna
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- 2. EDIT USER MODAL DIALOG                                      -->
<!-- ============================================================== -->
<div id="editUserModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-md" role="dialog" aria-modal="true" aria-labelledby="editUserModalTitle">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full shadow-2xl overflow-hidden flex flex-col border border-slate-200 dark:border-slate-800" onclick="event.stopPropagation()">
        
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/70 dark:bg-slate-800/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/25">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white" id="editUserModalTitle">Edit Akun Pengguna</h3>
                    <p class="text-xs text-slate-400">Perbarui identitas, peran akses, dan status</p>
                </div>
            </div>
            <button type="button" onclick="closeEditUserModal()" aria-label="Tutup modal" class="w-8 h-8 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Form -->
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="target_user_id" id="editTargetUserId" value="">

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nama Lengkap</label>
                <input type="text" name="name" id="editUserName" required
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Email</label>
                <input type="email" name="email" id="editUserEmail" required
                       class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Peran</label>
                    <select name="role" id="editUserRole"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                        <option value="user">User Biasa</option>
                        <option value="administrator">Administrator</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Status Akun</label>
                    <select name="status" id="editUserStatus"
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30 transition-all">
                        <option value="active">● Aktif</option>
                        <option value="inactive">● Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeEditUserModal()"
                        class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all text-xs">
                    Batal
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl font-bold text-white transition-all text-xs flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/25 active:scale-95">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- 3. USER QUOTA ADJUSTMENT MODAL                                 -->
<!-- ============================================================== -->
<div id="quotaModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-md" role="dialog" aria-modal="true" aria-labelledby="quotaModalTitle">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col border border-slate-200 dark:border-slate-800" onclick="event.stopPropagation()">
        
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50/70 dark:bg-slate-800/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/25">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900 dark:text-white" id="quotaModalTitle">Atur Batas Kuota Penyimpanan</h3>
                    <p class="text-xs text-slate-400" id="quotaModalSubtitle">Konfigurasi kapasitas akun pengguna</p>
                </div>
            </div>
            <button type="button" onclick="closeQuotaModal()" aria-label="Tutup modal" class="w-8 h-8 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Form -->
        <form method="POST" class="p-6 space-y-5" id="quotaForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="update_user_quota" value="1">
            <input type="hidden" name="target_user_id" id="quotaTargetUserId" value="">
            <input type="hidden" name="redirect_tab" id="quotaRedirectTab" value="storage">
            <input type="hidden" name="quota_mode" id="quotaModeInput" value="preset">
            <input type="hidden" name="preset_bytes" id="presetBytesInput" value="5368709120">

            <!-- Target User Info Card -->
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/80 flex items-center justify-between">
                <div>
                    <div class="text-xs font-black text-slate-900 dark:text-white" id="quotaUserName">Administrator</div>
                    <div class="text-[11px] text-slate-500" id="quotaUserEmail">admin@taskflow.com</div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Terpakai Saat Ini</div>
                    <div class="text-xs font-black text-emerald-600 dark:text-emerald-400" id="quotaUserCurrentUsed">348 KB</div>
                </div>
            </div>

            <!-- Quick Presets -->
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-2">Pilihan Cepat (Presets)</label>
                <div class="grid grid-cols-4 gap-2" id="quotaPresetsGrid">
                    <button type="button" onclick="selectQuotaPreset(524288000, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-indigo-500 text-center transition-all">500 MB</button>
                    <button type="button" onclick="selectQuotaPreset(1073741824, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-indigo-500 text-center transition-all">1 GB</button>
                    <button type="button" onclick="selectQuotaPreset(2147483648, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-indigo-500 text-center transition-all">2 GB</button>
                    <button type="button" onclick="selectQuotaPreset(5368709120, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-indigo-500 text-center transition-all">5 GB</button>
                    <button type="button" onclick="selectQuotaPreset(10737418240, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-indigo-500 text-center transition-all">10 GB</button>
                    <button type="button" onclick="selectQuotaPreset(26843545600, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-indigo-500 text-center transition-all">25 GB</button>
                    <button type="button" onclick="selectQuotaPreset(53687091200, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-indigo-500 text-center transition-all">50 GB</button>
                    <button type="button" onclick="selectQuotaPreset(0, this)" class="quota-preset-btn px-2.5 py-2 rounded-xl border border-indigo-200 bg-indigo-50/60 dark:bg-indigo-950/40 text-xs font-black text-indigo-600 dark:text-indigo-400 text-center transition-all">♾️ Bebas</button>
                </div>
            </div>

            <!-- Custom Input Accordion / Toggle -->
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Atau Masukkan Nilai Kustom</label>
                <div class="flex items-center gap-2">
                    <input type="number" step="0.1" min="0" name="custom_val" id="customQuotaVal" placeholder="Misal: 15" oninput="onCustomQuotaInput()"
                           class="flex-1 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3.5 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    <select name="custom_unit" id="customQuotaUnit" onchange="onCustomQuotaInput()"
                            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                        <option value="MB">MB</option>
                        <option value="GB" selected>GB</option>
                        <option value="TB">TB</option>
                    </select>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeQuotaModal()"
                        class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all text-xs">
                    Batal
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl font-bold text-white transition-all active:scale-95 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/25 text-xs">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Simpan Perubahan Kuota
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- HIDDEN FORMS FOR STATUS TOGGLE & DELETE                        -->
<!-- ============================================================== -->
<form id="toggleStatusForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="toggle_user_status" value="1">
    <input type="hidden" name="target_user_id" id="toggleStatusUserId" value="">
</form>

<form id="deleteUserForm" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" name="delete_user" id="deleteUserId" value="">
</form>

<script>
// -------------------------------------------------------------
// Drawer: Tambah Pengguna
// -------------------------------------------------------------
function openAddUserDrawer() {
    const overlay = document.getElementById('addUserDrawerOverlay');
    const drawer  = document.getElementById('addUserDrawer');
    if (overlay && drawer) {
        overlay.classList.remove('hidden');
        setTimeout(() => {
            drawer.classList.remove('translate-x-full');
        }, 10);
    }
}

function closeAddUserDrawer() {
    const overlay = document.getElementById('addUserDrawerOverlay');
    const drawer  = document.getElementById('addUserDrawer');
    if (overlay && drawer) {
        drawer.classList.add('translate-x-full');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 280);
    }
}

// -------------------------------------------------------------
// Modal: Edit User
// -------------------------------------------------------------
function openEditUserModal(data) {
    closeAllActionMenus();
    document.getElementById('editTargetUserId').value = data.id;
    document.getElementById('editUserName').value     = data.display_name;
    document.getElementById('editUserEmail').value    = data.email;
    document.getElementById('editUserRole').value     = data.role;
    document.getElementById('editUserStatus').value   = data.status || 'active';

    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }
}

function closeEditUserModal() {
    const modal = document.getElementById('editUserModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

// -------------------------------------------------------------
// Action Dropdowns Logic (··· menu)
// -------------------------------------------------------------
function toggleActionMenu(event, menuId) {
    event.stopPropagation();
    const menu = document.getElementById(menuId);
    if (!menu) return;
    const isCurrentlyOpen = !menu.classList.contains('hidden');

    closeAllActionMenus();

    if (!isCurrentlyOpen) {
        menu.classList.remove('hidden');
    }
}

function closeAllActionMenus() {
    document.querySelectorAll('.action-menu-dropdown').forEach(m => {
        m.classList.add('hidden');
    });
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown-container')) {
        closeAllActionMenus();
    }
});

// Toggle User Status Confirmation
function toggleUserStatus(userId, userName, actionName) {
    closeAllActionMenus();
    if (confirm(`Yakin ingin ${actionName} akun "${userName}"?`)) {
        document.getElementById('toggleStatusUserId').value = userId;
        document.getElementById('toggleStatusForm').submit();
    }
}

// Delete User Confirmation
function confirmDeleteUser(userId, userName) {
    closeAllActionMenus();
    if (confirm(`Hapus permanen akun pengguna "${userName}"? Seluruh berkas dan data terkait akan dihapus.`)) {
        document.getElementById('deleteUserId').value = userId;
        document.getElementById('deleteUserForm').submit();
    }
}

// Password reset helper
function resetPassword(userId, userName) {
    closeAllActionMenus();
    if (confirm(`Reset password user "${userName}" ke "password123"?`)) {
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

// User filter for Users tab
function filterUsers(q) {
    q = (q || '').toLowerCase().trim();
    document.querySelectorAll('.user-row').forEach(row => {
        const name  = row.dataset.name  || '';
        const email = row.dataset.email || '';
        row.style.display = (!q || name.includes(q) || email.includes(q)) ? '' : 'none';
    });
}

// User filter for Storage tab
function filterStorageUsers(q) {
    q = (q || '').toLowerCase().trim();
    document.querySelectorAll('.storage-row').forEach(row => {
        const name  = row.dataset.name  || '';
        const email = row.dataset.email || '';
        row.style.display = (!q || name.includes(q) || email.includes(q)) ? '' : 'none';
    });
}

// -------------------------------------------------------------
// Storage Quota Modal Logic
// -------------------------------------------------------------
function formatBytesJs(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function openQuotaModal(userId, userName, userEmail, currentUsedBytes, currentQuotaBytes, redirectTab) {
    closeAllActionMenus();
    const modal = document.getElementById('quotaModal');
    if (!modal) return;

    document.getElementById('quotaTargetUserId').value = userId;
    document.getElementById('quotaRedirectTab').value = redirectTab || 'storage';
    document.getElementById('quotaUserName').textContent = userName;
    document.getElementById('quotaUserEmail').textContent = userEmail;
    document.getElementById('quotaUserCurrentUsed').textContent = formatBytesJs(currentUsedBytes);

    // Reset buttons
    document.querySelectorAll('.quota-preset-btn').forEach(btn => {
        btn.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
    });

    // Match preset if possible
    let matched = false;
    document.querySelectorAll('.quota-preset-btn').forEach(btn => {
        if (btn.getAttribute('onclick').includes('(' + currentQuotaBytes + ',')) {
            btn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
            matched = true;
        }
    });

    if (matched) {
        document.getElementById('quotaModeInput').value = (currentQuotaBytes === 0) ? 'unlimited' : 'preset';
        document.getElementById('presetBytesInput').value = currentQuotaBytes;
        document.getElementById('customQuotaVal').value = '';
    } else {
        document.getElementById('quotaModeInput').value = 'custom';
        const inGb = (currentQuotaBytes / (1024 * 1024 * 1024)).toFixed(1);
        document.getElementById('customQuotaVal').value = inGb;
        document.getElementById('customQuotaUnit').value = 'GB';
    }

    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function closeQuotaModal() {
    const modal = document.getElementById('quotaModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
}

function selectQuotaPreset(bytes, btn) {
    document.querySelectorAll('.quota-preset-btn').forEach(b => {
        b.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
    });
    if (btn) {
        btn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
    }

    if (bytes === 0) {
        document.getElementById('quotaModeInput').value = 'unlimited';
        document.getElementById('presetBytesInput').value = 0;
    } else {
        document.getElementById('quotaModeInput').value = 'preset';
        document.getElementById('presetBytesInput').value = bytes;
    }
    document.getElementById('customQuotaVal').value = '';
}

function onCustomQuotaInput() {
    const val = parseFloat(document.getElementById('customQuotaVal').value) || 0;
    if (val > 0) {
        document.querySelectorAll('.quota-preset-btn').forEach(b => {
            b.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
        });
        document.getElementById('quotaModeInput').value = 'custom';
    }
}

// Global ESC key closes all overlays
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddUserDrawer();
        closeEditUserModal();
        closeQuotaModal();
        closeAllActionMenus();
    }
});
</script>
