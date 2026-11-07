<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/documents.php

$user_id = (int)$_SESSION['user_id'];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['my', 'shared']) ? $_GET['tab'] : 'my';

// Handle Add / Upload Document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doc'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $name = trim($_POST['name']);
        $tags = trim($_POST['tags'] ?? '');

        $file_path = null;
        $file_size = 0;
        $type = 'application/octet-stream';

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            $upload_dir = 'uploads/' . $user_id . '/';
            $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
            $filename    = time() . '_' . $safe_name;
            $target_path = $upload_dir . $filename;

            // Compress & optimize file (lossless/near-lossless for images, pristine binary for docs)
            $opt = optimizeAndSaveFile($file['tmp_name'], $target_path, $file['type']);
            if ($opt['success']) {
                $file_path = $opt['path'];
                $file_size = $opt['size'];
                $type      = $opt['mime'];
            } else {
                header("Location: index.php?page=documents&tab=" . $tab . "&error=upload_failed");
                exit;
            }
        }

        if ($file_path) {
            $stmt = $pdo->prepare("INSERT INTO documents (user_id, name, file_path, file_type, file_size, tags) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $file_path, $type, $file_size, $tags]);
            $doc_id = $pdo->lastInsertId();
            logActivity("Mengunggah dokumen: " . $name, "document", $doc_id);
            header("Location: index.php?page=documents&tab=my&success=uploaded");
            exit;
        }
    }
}

// Handle Share Document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_doc'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $doc_id = (int)$_POST['doc_id'];
        $target_user_id = (int)$_POST['target_user_id'];
        $permission = in_array($_POST['permission'] ?? 'view', ['view', 'download']) ? $_POST['permission'] : 'view';

        // Verify document ownership
        $chk = $pdo->prepare("SELECT id, name FROM documents WHERE id = ? AND user_id = ?");
        $chk->execute([$doc_id, $user_id]);
        $doc = $chk->fetch();

        if ($doc && $target_user_id !== $user_id) {
            $ins = $pdo->prepare("INSERT INTO document_shares (document_id, shared_with_user_id, shared_by_user_id, permission) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE permission = ?");
            $ins->execute([$doc_id, $target_user_id, $user_id, $permission, $permission]);
            
            // Get target user name for log
            $uStmt = $pdo->prepare("SELECT display_name FROM users WHERE id = ?");
            $uStmt->execute([$target_user_id]);
            $tUser = $uStmt->fetch();
            $tName = $tUser ? $tUser['display_name'] : 'Pengguna';

            logActivity("Membagikan dokumen '" . $doc['name'] . "' ke " . $tName, "document_share", $doc_id);
            header("Location: index.php?page=documents&tab=" . $tab . "&success=shared");
            exit;
        }
    }
}

// Handle Revoke Share
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_share'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $share_id = (int)$_POST['share_id'];
        $del = $pdo->prepare("DELETE FROM document_shares WHERE id = ? AND (shared_by_user_id = ? OR shared_with_user_id = ?" . (isAdmin() ? " OR 1=1" : "") . ")");
        $del->execute([$share_id, $user_id, $user_id]);
        logActivity("Mencabut hak akses berbagi dokumen", "document_revoke");
        header("Location: index.php?page=documents&tab=" . $tab . "&success=revoked");
        exit;
    }
}

// Handle Create Public Share Link (External with Expiration)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_public_share'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $doc_id = (int)$_POST['doc_id'];
        $duration = $_POST['duration'] ?? '24_hours';

        // Verify document ownership
        $chk = $pdo->prepare("SELECT id, name FROM documents WHERE id = ? AND (user_id = ?" . (isAdmin() ? " OR 1=1" : "") . ")");
        $chk->execute([$doc_id, $user_id]);
        $doc = $chk->fetch();

        if ($doc) {
            $durationMap = [
                '1_hour'   => '+1 hour',
                '6_hours'  => '+6 hours',
                '24_hours' => '+24 hours',
                '3_days'   => '+3 days',
                '7_days'   => '+7 days',
                '30_days'  => '+30 days'
            ];
            $interval = $durationMap[$duration] ?? '+24 hours';
            $expires_at = date('Y-m-d H:i:s', strtotime($interval));

            // Deactivate previous active shares for this doc
            $deact = $pdo->prepare("UPDATE document_public_shares SET is_active = 0 WHERE document_id = ?");
            $deact->execute([$doc_id]);

            // Create cryptographically secure token
            $token = bin2hex(random_bytes(24));
            $ins = $pdo->prepare("INSERT INTO document_public_shares (document_id, share_token, created_by, expires_at) VALUES (?, ?, ?, ?)");
            $ins->execute([$doc_id, $token, $user_id, $expires_at]);

            logActivity("Membuat tautan publik untuk dokumen '" . $doc['name'] . "'", "document_public_share", $doc_id);
            header("Location: index.php?page=documents&tab=" . $tab . "&success=public_shared");
            exit;
        }
    }
}

// Handle Revoke Public Share Link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_public_share'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $pub_share_id = (int)$_POST['pub_share_id'];
        $del = $pdo->prepare("UPDATE document_public_shares SET is_active = 0 WHERE id = ? AND (created_by = ?" . (isAdmin() ? " OR 1=1" : "") . ")");
        $del->execute([$pub_share_id, $user_id]);
        logActivity("Menonaktifkan tautan publik dokumen", "document_public_revoke");
        header("Location: index.php?page=documents&tab=" . $tab . "&success=public_revoked");
        exit;
    }
}

// Handle Delete Document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doc'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $doc_id = (int)$_POST['doc_id'];
        
        // Fetch to get path & verify ownership
        $stmt = $pdo->prepare("SELECT file_path, name FROM documents WHERE id = ? AND (user_id = ?" . (isAdmin() ? " OR 1=1" : "") . ")");
        $stmt->execute([$doc_id, $user_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            // Delete physical file
            if ($doc['file_path'] && file_exists($doc['file_path'])) {
                @unlink($doc['file_path']);
            }
            
            // Delete from DB (document_shares will be cascade deleted)
            $del = $pdo->prepare("DELETE FROM documents WHERE id = ?");
            $del->execute([$doc_id]);
            
            logActivity("Menghapus dokumen: " . $doc['name'], "document_delete");
            header("Location: index.php?page=documents&tab=" . $tab . "&success=deleted");
            exit;
        }
    }
}

// Query 1: My Documents (Strict User Isolation)
if ($search) {
    $stmt = $pdo->prepare("
        SELECT d.*, (SELECT COUNT(*) FROM document_shares ds WHERE ds.document_id = d.id) as share_count 
        FROM documents d 
        WHERE d.user_id = ? AND (d.name LIKE ? OR d.tags LIKE ?) 
        ORDER BY d.upload_date DESC
    ");
    $stmt->execute([$user_id, '%' . $search . '%', '%' . $search . '%']);
} else {
    $stmt = $pdo->prepare("
        SELECT d.*, (SELECT COUNT(*) FROM document_shares ds WHERE ds.document_id = d.id) as share_count 
        FROM documents d 
        WHERE d.user_id = ? 
        ORDER BY d.upload_date DESC
    ");
    $stmt->execute([$user_id]);
}
$my_documents = $stmt->fetchAll();

// Query 2: Shared with Me Documents
if ($search) {
    $stmt = $pdo->prepare("
        SELECT d.*, u.display_name as owner_name, u.email as owner_email, ds.id as share_id, ds.permission, ds.created_at as shared_at 
        FROM documents d 
        JOIN document_shares ds ON ds.document_id = d.id 
        JOIN users u ON u.id = d.user_id 
        WHERE ds.shared_with_user_id = ? AND (d.name LIKE ? OR d.tags LIKE ?) 
        ORDER BY ds.created_at DESC
    ");
    $stmt->execute([$user_id, '%' . $search . '%', '%' . $search . '%']);
} else {
    $stmt = $pdo->prepare("
        SELECT d.*, u.display_name as owner_name, u.email as owner_email, ds.id as share_id, ds.permission, ds.created_at as shared_at 
        FROM documents d 
        JOIN document_shares ds ON ds.document_id = d.id 
        JOIN users u ON u.id = d.user_id 
        WHERE ds.shared_with_user_id = ? 
        ORDER BY ds.created_at DESC
    ");
    $stmt->execute([$user_id]);
}
$shared_documents = $stmt->fetchAll();

// List of all active users in system for sharing modal
$uStmt = $pdo->prepare("SELECT id, display_name, email, role FROM users WHERE id != ? ORDER BY display_name ASC");
$uStmt->execute([$user_id]);
$system_users = $uStmt->fetchAll();

// Active public shares created by this user
$pub_stmt = $pdo->prepare("SELECT * FROM document_public_shares WHERE created_by = ? AND is_active = 1 AND expires_at > NOW()");
$pub_stmt->execute([$user_id]);
$public_shares_raw = $pub_stmt->fetchAll();
$public_shares_by_doc = [];

// Base URL for public sharing
$configured_app_url = getSetting('app_url');
if (!empty($configured_app_url)) {
    $share_base_url = rtrim($configured_app_url, '/') . '/share.php?token=';
} else {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $share_base_url = $protocol . '://' . $host . ($dir ? $dir : '') . '/share.php?token=';
}

foreach ($public_shares_raw as $ps) {
    $ps['full_url'] = $share_base_url . $ps['share_token'];
    $ps['expires_formatted'] = date('d M Y H:i', strtotime($ps['expires_at']));
    $diff_sec = strtotime($ps['expires_at']) - time();
    $diff_hrs = floor($diff_sec / 3600);
    $diff_mins = floor(($diff_sec % 3600) / 60);
    $ps['remaining_text'] = $diff_hrs > 0 ? ($diff_hrs . 'j ' . $diff_mins . 'm') : ($diff_mins . 'm');
    $public_shares_by_doc[$ps['document_id']] = $ps;
}

// Active documents to display based on selected tab
$documents = $tab === 'shared' ? $shared_documents : $my_documents;

// Helper: file-type icon, badge & color
function docIconInfo($mime, $filename = '') {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (strncmp($mime, 'image/', 6) === 0 || in_array($ext, ['jpg','jpeg','png','webp','gif','svg'])) {
        return ['color'=>'#0284c7', 'bg'=>'#e0f2fe', 'badge_bg'=>'#0284c7', 'label'=> strtoupper($ext ?: 'IMG'), 'icon'=>'image'];
    }
    if ($mime === 'application/pdf' || $ext === 'pdf') {
        return ['color'=>'#dc2626', 'bg'=>'#fee2e2', 'badge_bg'=>'#dc2626', 'label'=>'PDF', 'icon'=>'pdf'];
    }
    if (strpos($mime, 'word') !== false || in_array($ext, ['doc','docx'])) {
        return ['color'=>'#2563eb', 'bg'=>'#dbeafe', 'badge_bg'=>'#2563eb', 'label'=>'DOC', 'icon'=>'doc'];
    }
    if (strpos($mime, 'sheet') !== false || strpos($mime, 'excel') !== false || in_array($ext, ['xls','xlsx','csv'])) {
        return ['color'=>'#059669', 'bg'=>'#d1fae5', 'badge_bg'=>'#059669', 'label'=>'XLS', 'icon'=>'sheet'];
    }
    if ($mime === 'text/plain' || $ext === 'txt') {
        return ['color'=>'#475569', 'bg'=>'#f1f5f9', 'badge_bg'=>'#475569', 'label'=>'TXT', 'icon'=>'text'];
    }
    return ['color'=>'#7c3aed', 'bg'=>'#ede9fe', 'badge_bg'=>'#7c3aed', 'label'=> strtoupper($ext ?: 'FILE'), 'icon'=>'file'];
}

function docSvgIcon($type, $color) {
    $icons = [
        'image'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'pdf'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'doc'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>',
        'sheet'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/>',
        'text'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'file'   => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>',
    ];
    $paths = $icons[$type] ?? $icons['file'];
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}
?>

<div class="p-4 md:p-8 w-full max-w-7xl mx-auto space-y-6">

    <!-- Hero Header -->
    <div class="rounded-3xl p-6 md:p-9 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5 bg-gradient-to-r from-emerald-950 via-teal-900 to-slate-900 text-white shadow-lg border border-emerald-900/50">
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-400/20 text-emerald-300 text-xs font-bold uppercase tracking-wider mb-3">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                Penyimpanan Aman &amp; Kolaborasi
            </div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-white leading-tight mb-1">Manajemen Dokumen</h1>
            <p class="text-emerald-200 text-sm max-w-xl">Simpan berkas terkompresi berkualitas tinggi, terisolasi aman per user, dan bagikan dengan rekan kerja.</p>
        </div>

        <!-- Search in hero -->
        <div class="relative z-10 flex items-center gap-3 flex-shrink-0">
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="page" value="documents">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari dokumen..." aria-label="Cari dokumen"
                           class="pl-9 pr-4 py-2.5 text-sm font-medium w-44 md:w-56 rounded-xl text-white transition-all bg-white/10 border border-white/15 focus:outline-none focus:ring-2 focus:ring-emerald-400 placeholder:text-white/40">
                </div>
                <button type="submit" class="px-4 py-2.5 rounded-xl text-sm font-bold text-white transition-all active:scale-95 bg-emerald-600 hover:bg-emerald-500 shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">Cari</button>
                <?php if ($search): ?>
                    <a href="?page=documents&tab=<?php echo $tab; ?>" aria-label="Reset pencarian" class="px-3 py-2.5 rounded-xl text-sm font-bold transition-all text-white/70 hover:text-white bg-white/10">✕</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['error']) && $_GET['error'] === 'upload_failed'): ?>
    <div class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-2xl px-5 py-4">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-red-700 text-sm font-bold">Gagal mengunggah file. Pastikan format didukung dan ukuran file tidak melebihi batas server.</p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-4">
        <div class="w-7 h-7 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600 flex-shrink-0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p class="text-emerald-900 text-sm font-bold">
            <?php 
            if($_GET['success'] === 'uploaded') echo "Dokumen berhasil diunggah dengan kompresi optimal."; 
            if($_GET['success'] === 'deleted') echo "Dokumen berhasil dihapus dari sistem."; 
            if($_GET['success'] === 'shared') echo "Hak akses dokumen berhasil dibagikan ke pengguna terpilih."; 
            if($_GET['success'] === 'revoked') echo "Hak akses berbagi dokumen berhasil dicabut."; 
            if($_GET['success'] === 'public_shared') echo "Tautan publik berhasil dibuat dan siap dibagikan!"; 
            if($_GET['success'] === 'public_revoked') echo "Tautan publik berhasil dinonaktifkan."; 
            ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Document Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
        <a href="?page=documents&tab=my" 
           class="px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab === 'my' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Dokumen Saya (<?php echo count($my_documents); ?>)
        </a>
        <a href="?page=documents&tab=shared" 
           class="px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab === 'shared' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'; ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Dibagikan Dengan Saya (<?php echo count($shared_documents); ?>)
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-7">

        <!-- Document List Area (8 Columns) -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
                <div class="px-6 py-4.5 bg-gradient-to-r from-slate-50/80 to-white border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="font-extrabold text-slate-900 text-base">
                            <?php echo $tab === 'shared' ? 'Dokumen Dibagikan ke Anda' : 'File Pribadi Anda'; ?>
                        </h2>
                        <p class="text-xs text-slate-400 font-medium">Berkas tersimpan aman dan terorganisir</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-sm">
                        <?php echo count($documents); ?> Berkas Tersedia
                    </span>
                </div>

                <?php if (empty($documents)): ?>
                <div class="p-16 text-center">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 bg-emerald-50 text-emerald-600">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h3 class="text-slate-800 font-extrabold text-sm mb-1">
                        <?php echo $tab === 'shared' ? 'Belum ada dokumen yang dibagikan kepada Anda.' : 'Belum ada dokumen yang tersimpan.'; ?>
                    </h3>
                    <p class="text-slate-400 text-xs max-w-sm mx-auto">
                        <?php echo $tab === 'shared' ? 'Ketika rekan kerja membagikan dokumen ke akun Anda, berkas akan muncul di sini.' : 'Unggah dokumen atau foto Anda menggunakan formulir di samping.'; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="p-4 sm:p-5 space-y-3">
                    <?php foreach($documents as $doc):
                        $clean_path = str_replace('\\', '/', $doc['file_path']);
                        $info       = docIconInfo($doc['file_type'], $clean_path ?: $doc['name']);
                        $iconSvg    = docSvgIcon($info['icon'], $info['color']);
                        $serve_view_url = 'serve_file.php?file=' . rawurlencode($clean_path);
                        $serve_download_url = 'serve_file.php?file=' . rawurlencode($clean_path) . '&download=1';
                        $tags       = array_filter(array_map('trim', explode(',', $doc['tags'] ?? '')));
                        $is_owner   = ((int)$doc['user_id'] === $user_id);
                        $pubShare   = $public_shares_by_doc[$doc['id']] ?? null;
                    ?>
                    <div class="group relative p-4 rounded-2xl bg-white border border-slate-200/90 hover:border-emerald-400 hover:shadow-md hover:shadow-emerald-500/5 hover:-translate-y-0.5 transition-all duration-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        
                        <!-- Left: File Icon & Metadata -->
                        <div class="flex items-center gap-3.5 min-w-0 flex-1">
                            <!-- Icon Box with Format Badge -->
                            <div class="relative w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm border border-black/5"
                                 style="background:<?php echo $info['bg']; ?>">
                                <?php echo $iconSvg; ?>
                                <span class="absolute -bottom-1 -right-1 text-[8px] font-black text-white px-1.5 py-0.5 rounded-md tracking-wider shadow-sm"
                                      style="background:<?php echo $info['badge_bg']; ?>">
                                    <?php echo $info['label']; ?>
                                </span>
                            </div>

                            <!-- Document Details -->
                            <div class="min-w-0 flex-1">
                                <button type="button" onclick="viewDocument('<?php echo htmlspecialchars($clean_path); ?>', '<?php echo htmlspecialchars($doc['file_type']); ?>', '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', '<?php echo htmlspecialchars($serve_download_url); ?>')"
                                        class="text-left font-black text-slate-900 text-sm sm:text-[15px] group-hover:text-emerald-600 truncate block mb-1 transition-colors">
                                    <?php echo htmlspecialchars($doc['name']); ?>
                                </button>

                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span class="inline-flex items-center gap-1 font-bold text-[11px] text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md">
                                        <?php echo formatSize($doc['file_size']); ?>
                                    </span>
                                    <span class="text-slate-300">·</span>
                                    <span class="text-[11px] font-medium text-slate-400">
                                        <?php echo date('d M Y', strtotime($doc['upload_date'])); ?>
                                    </span>

                                    <?php if ($tab === 'shared' && !empty($doc['owner_name'])): ?>
                                    <span class="text-slate-300">·</span>
                                    <span class="text-indigo-700 font-extrabold bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-md text-[10px]">
                                        👤 <?php echo htmlspecialchars($doc['owner_name']); ?>
                                    </span>
                                    <?php endif; ?>

                                    <?php if ($is_owner && !empty($doc['share_count']) && (int)$doc['share_count'] > 0): ?>
                                    <span class="text-slate-300">·</span>
                                    <span class="text-emerald-800 font-extrabold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md text-[10px]">
                                        🔗 Internal (<?php echo $doc['share_count']; ?>)
                                    </span>
                                    <?php endif; ?>

                                    <?php if (!empty($pubShare)): ?>
                                    <span class="text-slate-300">·</span>
                                    <span class="inline-flex items-center gap-1 text-sky-800 font-extrabold bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-md text-[10px]" title="Tautan publik aktif hingga <?php echo $pubShare['expires_formatted']; ?>">
                                        🌐 Link Publik (exp: <?php echo $pubShare['remaining_text']; ?>)
                                    </span>
                                    <?php endif; ?>

                                    <?php foreach($tags as $tag): ?>
                                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md text-emerald-800 bg-emerald-50 border border-emerald-200">#<?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Action Buttons (High-contrast, elegant) -->
                        <div class="flex items-center gap-1.5 flex-shrink-0 self-end sm:self-center">
                            <!-- Preview Button -->
                            <button type="button" onclick="viewDocument('<?php echo htmlspecialchars($clean_path); ?>', '<?php echo htmlspecialchars($doc['file_type']); ?>', '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', '<?php echo htmlspecialchars($serve_download_url); ?>')"
                                    title="Lihat Pratinjau" aria-label="Preview dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                                    class="h-9 px-3 flex items-center gap-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white font-extrabold text-xs transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span>Lihat</span>
                            </button>

                            <!-- Download Button -->
                            <a href="<?php echo htmlspecialchars($serve_download_url); ?>" title="Unduh Berkas" aria-label="Download dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                               class="w-9 h-9 flex items-center justify-center rounded-xl bg-emerald-50 hover:bg-emerald-600 text-emerald-700 hover:text-white transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>

                            <!-- Share Button (Only for Document Owner) -->
                            <?php if ($is_owner): ?>
                            <button type="button" onclick="openShareModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', <?php echo !empty($pubShare) ? htmlspecialchars(json_encode($pubShare), ENT_QUOTES, 'UTF-8') : 'null'; ?>)"
                                    title="Bagikan Dokumen" aria-label="Bagikan dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                                    class="w-9 h-9 flex items-center justify-center rounded-xl bg-sky-50 hover:bg-sky-600 text-sky-700 hover:text-white transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                            </button>
                            <?php endif; ?>

                            <!-- Delete Button (Only for Owner or Admin) -->
                            <?php if ($is_owner || isAdmin()): ?>
                            <button type="button" onclick="confirmDelete(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['name']); ?>')"
                                    title="Hapus Dokumen" aria-label="Hapus dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                                    class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-100 hover:bg-rose-600 text-slate-400 hover:text-white transition-all shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Hidden Delete Form -->
                <form id="deleteForm" method="POST" class="hidden">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="delete_doc" value="1">
                    <input type="hidden" name="doc_id" id="deleteDocId">
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upload Form Area (4 Columns) -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-3xl border border-slate-200 p-6 sticky top-6 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center bg-emerald-600 text-white shadow-sm">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div>
                        <h2 class="font-extrabold text-slate-900 text-base">Upload Dokumen</h2>
                        <p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Kompresi Otomatis</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" data-no-ajax="true" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="add_doc" value="1">

                    <!-- File drop area -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Pilih Berkas</label>
                        <label class="flex flex-col items-center justify-center gap-2 px-4 py-7 rounded-2xl border-2 border-dashed border-slate-200 cursor-pointer transition-all hover:border-emerald-500 hover:bg-emerald-50/40"
                               id="dropZone">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.8"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span class="text-slate-700 text-xs font-bold">Klik untuk memilih file</span>
                            <span class="text-slate-400 text-[10px]">Foto, PDF, Word, Excel, TXT</span>
                            <input type="file" name="file" required class="hidden" id="fileInput" onchange="updateFileName(this)">
                        </label>
                        <p id="fileName" class="text-xs text-emerald-700 font-bold mt-2 hidden"></p>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Dokumen</label>
                        <input type="text" name="name" placeholder="Contoh: Laporan Keuangan" required
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all placeholder:text-slate-400">
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tag (pisah koma)</label>
                        <input type="text" name="tags" placeholder="laporan, kerja, penting"
                               class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all placeholder:text-slate-400">
                    </div>

                    <button type="submit"
                            class="w-full py-3.5 rounded-xl font-bold text-white transition-transform active:scale-95 flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload &amp; Kompres
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal (Fitur Berbagi Dokumen ke Tim & Tautan Publik dengan Expired) -->
<div id="shareModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center shadow-sm">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900" id="shareModalTitle">Bagikan Dokumen</h3>
                    <p class="text-xs text-slate-400 font-medium truncate max-w-[260px]" id="shareDocName"></p>
                </div>
            </div>
            <button type="button" onclick="closeShareModal()" aria-label="Tutup modal berbagi" class="w-8 h-8 rounded-xl hover:bg-slate-200 transition-colors flex items-center justify-center text-slate-500">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Share Tabs -->
        <div class="flex border-b border-slate-100 bg-slate-50/80 p-1.5 gap-1.5 text-xs font-bold">
            <button type="button" id="tabBtnExternal" onclick="switchShareModalTab('external')"
                    class="flex-1 py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-white text-sky-700 shadow-sm border border-slate-200/80">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Tautan Eksternal (Expired)
            </button>
            <button type="button" id="tabBtnInternal" onclick="switchShareModalTab('internal')"
                    class="flex-1 py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Rekan Tim Internal
            </button>
        </div>

        <div class="p-6">
            <!-- TAB 1: EXTERNAL PUBLIC SHARE LINK -->
            <div id="tabContentExternal" class="space-y-4">
                
                <!-- If Active Share Exists -->
                <div id="sharePublicActiveBlock" class="hidden space-y-4">
                    <div class="p-4 rounded-2xl bg-sky-50 border border-sky-200 text-sky-900">
                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-sky-700">
                                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                Tautan Aktif
                            </span>
                            <span id="pubExpiryPill" class="text-[11px] font-bold text-sky-800 bg-sky-100 px-2 py-0.5 rounded-md"></span>
                        </div>
                        <p class="text-xs text-sky-800 font-medium mb-3">Siapa pun yang memiliki tautan ini dapat mengunduh dokumen sebelum batas waktu habis.</p>

                        <!-- Copy Link Field -->
                        <div class="flex items-center gap-2">
                            <input type="text" id="pubShareUrlInput" readonly
                                   class="flex-1 bg-white border border-sky-200 rounded-xl px-3 py-2 text-xs font-mono text-slate-800 outline-none select-all">
                            <button type="button" id="copyBtn" onclick="copyShareLink()"
                                    class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs transition-transform active:scale-95 flex items-center gap-1.5 shadow-sm">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span id="copyBtnText">Salin</span>
                            </button>
                        </div>

                        <!-- Stats Views & Downloads -->
                        <div class="mt-3 pt-3 border-t border-sky-200/60 flex items-center justify-between text-[11px] text-sky-800 font-semibold">
                            <span id="pubViewsText">Dilihat: 0 kali</span>
                            <span id="pubDownloadsText">Diunduh: 0 kali</span>
                        </div>
                    </div>

                    <!-- Revoke Form -->
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan tautan publik ini? Pengguna luar tidak akan bisa mengakses lagi.');">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="revoke_public_share" value="1">
                        <input type="hidden" name="pub_share_id" id="revokePubShareId">
                        <button type="submit" class="w-full py-2.5 rounded-xl border border-rose-200 hover:bg-rose-50 text-rose-700 font-bold text-xs transition-colors flex items-center justify-center gap-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                            Cabut &amp; Nonaktifkan Tautan Publik
                        </button>
                    </form>
                </div>

                <!-- If No Active Share (Create Form) -->
                <div id="sharePublicCreateBlock" class="space-y-4">
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">
                        Buat tautan aman untuk dibagikan ke klien atau pihak luar. Tautan otomatis kedaluwarsa setelah durasi yang Anda pilih.
                    </p>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="create_public_share" value="1">
                        <input type="hidden" name="doc_id" id="pubDocId">

                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Masa Berlaku Tautan (Expired)</label>
                            <select name="duration" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500">
                                <option value="1_hour">1 Jam</option>
                                <option value="6_hours">6 Jam</option>
                                <option value="24_hours" selected>24 Jam (1 Hari)</option>
                                <option value="3_days">3 Hari</option>
                                <option value="7_days">7 Hari (1 Minggu)</option>
                                <option value="30_days">30 Hari (1 Bulan)</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full py-3.5 rounded-xl font-bold text-white bg-sky-600 hover:bg-sky-700 shadow-md shadow-sky-600/20 transition-transform active:scale-95 text-xs flex items-center justify-center gap-2">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            Buat Tautan Publik Berwaktu
                        </button>
                    </form>
                </div>

            </div>

            <!-- TAB 2: INTERNAL USER SHARE -->
            <div id="tabContentInternal" class="hidden space-y-4">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="share_doc" value="1">
                    <input type="hidden" name="doc_id" id="shareDocId">

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Pilih Pengguna Tim</label>
                        <select name="target_user_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <option value="">-- Pilih Rekan Kerja --</option>
                            <?php foreach($system_users as $su): ?>
                            <option value="<?php echo $su['id']; ?>"><?php echo htmlspecialchars($su['display_name']); ?> (<?php echo htmlspecialchars($su['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Hak Akses</label>
                        <select name="permission" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500">
                            <option value="view">Hanya Pratinjau (Lihat)</option>
                            <option value="download">Pratinjau &amp; Boleh Unduh</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-xl font-bold text-white bg-sky-600 hover:bg-sky-700 shadow-sm transition-transform active:scale-95 text-xs flex items-center justify-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Berikan Akses Internal
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="viewModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-black/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
    <div class="bg-white rounded-3xl max-w-5xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center flex-shrink-0 bg-slate-50/50">
            <div>
                <h3 class="text-base font-extrabold text-slate-900" id="viewModalTitle">Pratinjau Dokumen</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest" id="viewModalType"></p>
            </div>
            <div class="flex items-center gap-2">
                <a id="viewModalDownload" href="#" download
                   class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all text-slate-700 bg-white border border-slate-200 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download File
                </a>
                <button type="button" onclick="closeModal()" aria-label="Tutup pratinjau" class="w-8 h-8 hover:bg-slate-200 rounded-xl transition-colors flex items-center justify-center text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-auto p-6 min-h-[480px]">
            <div id="documentViewer" class="w-full h-full rounded-2xl flex items-center justify-center min-h-[460px] bg-slate-50"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const BASE_URL = '<?php echo baseUrl(); ?>';

    window.updateFileName = function(input) {
        const label = document.getElementById('fileName');
        if (input.files && input.files[0]) {
            label.textContent = '✓ ' + input.files[0].name;
            label.classList.remove('hidden');
        }
    };

    window.openShareModal = function(docId, docName, pubShare) {
        document.getElementById('shareDocId').value = docId;
        document.getElementById('pubDocId').value = docId;
        document.getElementById('shareDocName').textContent = docName;

        const activeBlock = document.getElementById('sharePublicActiveBlock');
        const createBlock = document.getElementById('sharePublicCreateBlock');

        if (pubShare && pubShare.full_url) {
            document.getElementById('pubShareUrlInput').value = pubShare.full_url;
            document.getElementById('pubExpiryPill').textContent = 'Exp: ' + (pubShare.remaining_text || pubShare.expires_formatted);
            document.getElementById('pubViewsText').textContent = 'Dilihat: ' + (pubShare.views_count || 0) + ' kali';
            document.getElementById('pubDownloadsText').textContent = 'Diunduh: ' + (pubShare.downloads_count || 0) + ' kali';
            document.getElementById('revokePubShareId').value = pubShare.id;

            activeBlock.classList.remove('hidden');
            createBlock.classList.add('hidden');
        } else {
            activeBlock.classList.add('hidden');
            createBlock.classList.remove('hidden');
        }

        switchShareModalTab('external');

        const modal = document.getElementById('shareModal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    };

    window.switchShareModalTab = function(tab) {
        const extContent = document.getElementById('tabContentExternal');
        const intContent = document.getElementById('tabContentInternal');
        const extBtn = document.getElementById('tabBtnExternal');
        const intBtn = document.getElementById('tabBtnInternal');

        if (tab === 'external') {
            extContent.classList.remove('hidden');
            intContent.classList.add('hidden');
            extBtn.className = 'flex-1 py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-white text-sky-700 shadow-sm border border-slate-200/80';
            intBtn.className = 'flex-1 py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
        } else {
            extContent.classList.add('hidden');
            intContent.classList.remove('hidden');
            intBtn.className = 'flex-1 py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-white text-sky-700 shadow-sm border border-slate-200/80';
            extBtn.className = 'flex-1 py-2.5 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-600 hover:text-slate-900';
        }
    };

    window.copyShareLink = function() {
        const input = document.getElementById('pubShareUrlInput');
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(function() {
            const btnText = document.getElementById('copyBtnText');
            if (btnText) {
                btnText.textContent = 'Tersalin!';
                setTimeout(function() { btnText.textContent = 'Salin'; }, 2000);
            }
        }).catch(function() {
            document.execCommand('copy');
            const btnText = document.getElementById('copyBtnText');
            if (btnText) {
                btnText.textContent = 'Tersalin!';
                setTimeout(function() { btnText.textContent = 'Salin'; }, 2000);
            }
        });
    };

    window.closeShareModal = function() {
        const modal = document.getElementById('shareModal');
        modal.classList.add('hidden');
        modal.style.display = 'none';
    };

    window.viewDocument = function(filePath, fileType, fileName, downloadUrl) {
        const modal  = document.getElementById('viewModal');
        const viewer = document.getElementById('documentViewer');
        const title  = document.getElementById('viewModalTitle');
        const typeEl = document.getElementById('viewModalType');
        const dlBtn  = document.getElementById('viewModalDownload');

        title.textContent  = fileName || 'Pratinjau';
        typeEl.textContent = fileType;
        dlBtn.href         = downloadUrl || ('serve_file.php?file=' + encodeURIComponent(filePath) + '&download=1');
        dlBtn.setAttribute('download', fileName || '');
        viewer.innerHTML   = '';
        viewer.style.background = '#f8fafc';

        const serveUrl = 'serve_file.php?file=' + encodeURIComponent(filePath);

        if (fileType.startsWith('image/')) {
            viewer.innerHTML = `<img src="${serveUrl}" alt="Preview ${escHtml(fileName)}" style="max-width:100%;max-height:500px;object-fit:contain;border-radius:12px">`;
        } else if (fileType === 'application/pdf') {
            viewer.style.background = 'transparent';
            viewer.innerHTML = `<object data="${serveUrl}" type="application/pdf" style="width:100%;height:520px;border:none;border-radius:12px">
                <div style="text-align:center;padding:40px;color:#475569">
                    <p style="font-weight:700;margin-bottom:12px">Browser tidak dapat menampilkan pratinjau PDF interaktif.</p>
                    <a href="${serveUrl}" target="_blank" style="background:#059669;color:#fff;padding:10px 24px;border-radius:12px;font-weight:700;text-decoration:none">Buka Dokumen PDF</a>
                </div></object>`;
        } else if (fileType === 'text/plain') {
            fetch(serveUrl).then(r=>r.text()).then(text=>{
                viewer.innerHTML = `<pre style="width:100%;height:450px;overflow:auto;padding:20px;background:#fff;border-radius:12px;font-family:monospace;font-size:13px;line-height:1.6;color:#334155;white-space:pre-wrap;word-break:break-word">${escHtml(text)}</pre>`;
            }).catch(()=>{ viewer.innerHTML = noPreview(serveUrl); });
        } else {
            viewer.innerHTML = noPreview(serveUrl);
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    };

    function noPreview(downloadUrl) {
        return `<div style="text-align:center;padding:40px;color:#64748b">
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 16px;display:block"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
            <p style="font-weight:700;margin-bottom:8px">Pratinjau langsung tidak tersedia untuk format berkas ini</p>
            <a href="${downloadUrl}" style="color:#059669;font-weight:700;text-decoration:none">Unduh file untuk membuka di aplikasi lokal</a>
        </div>`;
    }

    function escHtml(text) {
        return (text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    window.closeModal = function() {
        const modal = document.getElementById('viewModal');
        modal.classList.add('hidden');
        modal.style.display = 'none';
        document.getElementById('documentViewer').innerHTML = '';
    };

    window.confirmDelete = function(id, name) {
        if (confirm('Apakah Anda yakin ingin menghapus dokumen "' + name + '"? File akan dihapus permanen dari server.')) {
            document.getElementById('deleteDocId').value = id;
            document.getElementById('deleteForm').submit();
        }
    };
})();
</script>
