<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/documents.php - Dokumen 2.0 (Modern Executive File & Document Management)

$user_id = (int)$_SESSION['user_id'];
$search  = isset($_GET['search']) ? trim($_GET['search']) : '';
$tab     = isset($_GET['tab']) && in_array($_GET['tab'], ['my', 'shared']) ? $_GET['tab'] : 'my';

// -------------------------------------------------------------
// 1. Handle Add / Upload Document
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doc'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $name = trim($_POST['name']);
        $tags = trim($_POST['tags'] ?? '');

        $file_path = null;
        $file_size = 0;
        $type = 'application/octet-stream';

        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];

            // Enforce user storage quota
            $uQuotaStmt = $pdo->prepare("SELECT storage_quota FROM users WHERE id = ?");
            $uQuotaStmt->execute([$user_id]);
            $raw_quota = $uQuotaStmt->fetchColumn();
            $user_quota = ($raw_quota !== false) ? (int)$raw_quota : 5368709120;

            if ($user_quota > 0) {
                $usedStmt = $pdo->prepare("SELECT COALESCE(SUM(file_size), 0) FROM documents WHERE user_id = ?");
                $usedStmt->execute([$user_id]);
                $current_used = (int)$usedStmt->fetchColumn();

                if (($current_used + (int)$file['size']) > $user_quota) {
                    header("Location: index.php?page=documents&tab=" . $tab . "&error=quota_exceeded");
                    exit;
                }
            }

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

// -------------------------------------------------------------
// 2. Handle Share Document Internally
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// 3. Handle Revoke Share
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// 4. Handle Create Public Share Link (External with Expiration)
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// 5. Handle Revoke Public Share Link
// -------------------------------------------------------------
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

// -------------------------------------------------------------
// 6. Handle Delete Document
// -------------------------------------------------------------
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
            
            // Delete from DB (cascade deletes shares)
            $del = $pdo->prepare("DELETE FROM documents WHERE id = ?");
            $del->execute([$doc_id]);
            
            logActivity("Menghapus dokumen: " . $doc['name'], "document_delete");
            header("Location: index.php?page=documents&tab=" . $tab . "&success=deleted");
            exit;
        }
    }
}

// -------------------------------------------------------------
// Queries & Data Aggregation
// -------------------------------------------------------------
// 1. My Documents
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

// 2. Shared with Me Documents
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

// 3. System users for sharing modal
$uStmt = $pdo->prepare("SELECT id, display_name, email, role FROM users WHERE id != ? ORDER BY display_name ASC");
$uStmt->execute([$user_id]);
$system_users = $uStmt->fetchAll();

// 4. Active public shares
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

// Active documents by tab
$documents = $tab === 'shared' ? $shared_documents : $my_documents;

// Calculate storage stats & quota progress
$uQuotaStmt = $pdo->prepare("SELECT storage_quota FROM users WHERE id = ?");
$uQuotaStmt->execute([$user_id]);
$raw_user_quota = $uQuotaStmt->fetchColumn();
$user_quota = ($raw_user_quota !== false) ? (int)$raw_user_quota : 5368709120;
$is_unlimited = ($user_quota === 0);

$my_storage_bytes = 0;
foreach ($my_documents as $md) {
    $my_storage_bytes += (int)($md['file_size'] ?? 0);
}
$my_storage_formatted = formatSize($my_storage_bytes);
$storage_quota_formatted = $is_unlimited ? 'Tanpa Batas' : formatSize($user_quota);
$storage_pct = $is_unlimited ? 0 : round(($my_storage_bytes / max(1, $user_quota)) * 100, 1);
$storage_bar_pct = $is_unlimited ? 100 : min(100, max(1, $storage_pct));
$storage_remaining_bytes = max(0, $user_quota - $my_storage_bytes);
$storage_remaining_formatted = $is_unlimited ? 'Tanpa Batas' : formatSize($storage_remaining_bytes);

// -------------------------------------------------------------
// Helper: File Type Format & Icon Info
// -------------------------------------------------------------
function docInfo($mime, $filename = '') {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $is_img = (strncmp($mime, 'image/', 6) === 0 || in_array($ext, ['jpg','jpeg','png','webp','gif','svg']));
    if ($is_img) {
        return [
            'color'    => '#0284c7', 
            'bg'       => '#f0f9ff', 
            'badge_bg' => '#0284c7', 
            'border'   => '#bae6fd',
            'label'    => strtoupper($ext ?: 'IMG'), 
            'icon'     => 'image',
            'is_image' => true
        ];
    }
    if ($mime === 'application/pdf' || $ext === 'pdf') {
        return [
            'color'    => '#dc2626', 
            'bg'       => '#fef2f2', 
            'badge_bg' => '#dc2626', 
            'border'   => '#fecaca',
            'label'    => 'PDF', 
            'icon'     => 'pdf',
            'is_image' => false
        ];
    }
    if (strpos($mime, 'word') !== false || in_array($ext, ['doc','docx'])) {
        return [
            'color'    => '#2563eb', 
            'bg'       => '#eff6ff', 
            'badge_bg' => '#2563eb', 
            'border'   => '#bfdbfe',
            'label'    => 'DOC', 
            'icon'     => 'doc',
            'is_image' => false
        ];
    }
    if (strpos($mime, 'sheet') !== false || strpos($mime, 'excel') !== false || in_array($ext, ['xls','xlsx','csv'])) {
        return [
            'color'    => '#059669', 
            'bg'       => '#ecfdf5', 
            'badge_bg' => '#059669', 
            'border'   => '#a7f3d0',
            'label'    => 'XLS', 
            'icon'     => 'sheet',
            'is_image' => false
        ];
    }
    if ($mime === 'text/plain' || $ext === 'txt') {
        return [
            'color'    => '#475569', 
            'bg'       => '#f8fafc', 
            'badge_bg' => '#475569', 
            'border'   => '#e2e8f0',
            'label'    => 'TXT', 
            'icon'     => 'text',
            'is_image' => false
        ];
    }
    if (in_array($ext, ['zip','rar','7z','tar','gz'])) {
        return [
            'color'    => '#d97706', 
            'bg'       => '#fffbeb', 
            'badge_bg' => '#d97706', 
            'border'   => '#fde68a',
            'label'    => 'ZIP', 
            'icon'     => 'archive',
            'is_image' => false
        ];
    }
    return [
        'color'    => '#7c3aed', 
        'bg'       => '#f5f3ff', 
        'badge_bg' => '#7c3aed', 
        'border'   => '#ddd6fe',
        'label'    => strtoupper($ext ?: 'FILE'), 
        'icon'     => 'file',
        'is_image' => false
    ];
}

function docIconSvg($type, $color) {
    $icons = [
        'image'   => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'pdf'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'doc'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>',
        'sheet'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/>',
        'text'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'archive' => '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>',
        'file'    => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>',
    ];
    $paths = $icons[$type] ?? $icons['file'];
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}
?>

<style>
/* Modern Executive Frosted Glassmorphism Styling */
.doc-card-kpi {
    background: rgba(255, 255, 255, 0.76);
    backdrop-filter: blur(18px) saturate(180%);
    -webkit-backdrop-filter: blur(18px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    box-shadow: 
        0 4px 20px -2px rgba(15, 23, 42, 0.04),
        0 1px 3px rgba(15, 23, 42, 0.02),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.doc-card-kpi:hover {
    background: rgba(255, 255, 255, 0.92);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 12px 28px -4px rgba(79, 70, 229, 0.12), inset 0 1px 0 #ffffff;
    transform: translateY(-2px);
}

.doc-grid-card {
    background: rgba(255, 255, 255, 0.78);
    backdrop-filter: blur(18px) saturate(180%);
    -webkit-backdrop-filter: blur(18px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    box-shadow: 
        0 4px 18px -2px rgba(15, 23, 42, 0.04),
        0 1px 3px rgba(15, 23, 42, 0.02),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}
.doc-grid-card:hover {
    background: rgba(255, 255, 255, 0.94);
    border-color: rgba(99, 102, 241, 0.35);
    box-shadow: 0 12px 30px -4px rgba(79, 70, 229, 0.12), inset 0 1px 0 #ffffff;
    transform: translateY(-2px);
}

.doc-glass-panel {
    background: rgba(255, 255, 255, 0.76);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.85);
    box-shadow: 
        0 4px 20px -2px rgba(15, 23, 42, 0.04),
        0 1px 3px rgba(15, 23, 42, 0.02),
        inset 0 1px 0 rgba(255, 255, 255, 0.95);
}

.doc-table-row {
    transition: all 0.15s ease;
}
.doc-table-row:hover {
    background-color: rgba(248, 250, 252, 0.85);
}
</style>

<div class="p-4 md:p-8 w-full max-w-[1600px] mx-auto space-y-6">

    <!-- Executive Header Section (Glassmorphic) -->
    <div class="rounded-3xl p-6 md:p-7 doc-glass-panel relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-extrabold uppercase tracking-widest mb-2.5">
                <svg class="w-3.5 h-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                Penyimpanan &amp; Berbagi Dokumen
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight mb-1">Manajemen Dokumen</h1>
            <p class="text-slate-500 text-xs md:text-sm max-w-xl leading-relaxed font-medium">
                Simpan berkas kerja terkompresi berkualitas tinggi, pratinjau instan visual, dan kelola kolaborasi tim secara terpadu.
            </p>
        </div>

        <!-- Search Bar with Instant Debounce & Upload CTA -->
        <div class="flex flex-wrap items-center gap-3 flex-shrink-0">
            <!-- Instant Search Input -->
            <div class="relative w-full sm:w-64 md:w-72">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="docSearchInput" oninput="handleDocSearch(this.value)" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama berkas atau tag..." aria-label="Cari dokumen"
                       class="w-full pl-9 pr-8 py-2.5 text-xs font-bold rounded-xl text-slate-900 bg-slate-50 border border-slate-200/90 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all placeholder:text-slate-400">
                <button type="button" id="docSearchClear" onclick="clearDocSearch()" class="<?php echo $search ? '' : 'hidden'; ?> absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 text-xs font-bold w-5 h-5 flex items-center justify-center">✕</button>
            </div>

            <!-- Single Primary Upload Action (Opens Upload Modal) -->
            <button type="button" onclick="openUploadModal()"
                    class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition-transform active:scale-95 flex items-center gap-2 shadow-sm shadow-indigo-600/20">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Unggah Berkas</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['error']) && $_GET['error'] === 'quota_exceeded'): ?>
    <div class="flex items-start gap-3 bg-rose-50 border border-rose-200 rounded-2xl p-4 text-rose-900 text-xs shadow-xs">
        <div class="w-7 h-7 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div class="flex-1">
            <h4 class="font-black text-rose-950 text-sm mb-0.5">Kapasitas Kuota Penyimpanan Penuh</h4>
            <p class="font-medium text-rose-700 leading-relaxed">
                Penyimpanan akun Anda tidak mencukupi untuk mengunggah berkas ini. Batas kuota Anda adalah <span class="font-bold underline"><?php echo $storage_quota_formatted; ?></span> dan saat ini telah terpakai <span class="font-bold underline"><?php echo $my_storage_formatted; ?></span> (sisa <?php echo $storage_remaining_formatted; ?>). Silakan hapus beberapa berkas lama atau hubungi Administrator untuk menambah kapasitas penyimpanan Anda.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'upload_failed'): ?>
    <div class="flex items-center gap-3 bg-rose-50 border border-rose-200 rounded-2xl px-5 py-3.5 text-rose-800 text-xs font-bold shadow-xs">
        <svg class="w-5 h-5 flex-shrink-0 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Gagal mengunggah berkas. Pastikan ukuran file tidak melebihi batas upload server dan format berkas diizinkan.</span>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-3.5 text-emerald-900 text-xs font-bold shadow-xs">
        <div class="w-6 h-6 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-700 flex-shrink-0">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span>
            <?php 
            if($_GET['success'] === 'uploaded') echo "Dokumen berhasil diunggah dan dikompresi secara cerdas."; 
            if($_GET['success'] === 'deleted') echo "Dokumen berhasil dihapus permanen dari sistem."; 
            if($_GET['success'] === 'shared') echo "Hak akses dokumen berhasil dibagikan ke pengguna tim."; 
            if($_GET['success'] === 'revoked') echo "Hak akses berbagi dokumen berhasil dicabut."; 
            if($_GET['success'] === 'public_shared') echo "Tautan publik berbatas waktu berhasil dibuat dan siap dibagikan!"; 
            if($_GET['success'] === 'public_revoked') echo "Tautan publik berhasil dinonaktifkan."; 
            ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- Document Summary KPI Stats (4 Standard Cards with Storage Quota Bar) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Dokumen Saya -->
        <div class="doc-card-kpi p-5 border-t-[3px] border-t-indigo-500 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Dokumen Saya</span>
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-none"><?php echo count($my_documents); ?></span>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 font-semibold mt-2">berkas tersimpan</div>
        </div>

        <!-- Card 2: Kapasitas Penyimpanan Terpakai -->
        <div class="doc-card-kpi p-5 border-t-[3px] border-t-emerald-500 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Penyimpanan Terpakai</span>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5 flex-wrap">
                    <span class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-none"><?php echo $my_storage_formatted; ?></span>
                    <span class="text-xs font-bold text-slate-400">/ <?php echo $storage_quota_formatted; ?></span>
                </div>
            </div>
            <div class="mt-3">
                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200/60">
                    <?php 
                    $barGradient = 'from-emerald-400 to-emerald-500';
                    if (!$is_unlimited) {
                        if ($storage_pct >= 90) {
                            $barGradient = 'from-rose-500 to-red-600';
                        } elseif ($storage_pct >= 75) {
                            $barGradient = 'from-amber-400 to-amber-500';
                        }
                    } else {
                        $barGradient = 'from-indigo-400 to-indigo-600';
                    }
                    ?>
                    <div class="bg-gradient-to-r <?php echo $barGradient; ?> h-full rounded-full transition-all duration-500 shadow-xs" style="width: <?php echo max(3, $storage_bar_pct); ?>%"></div>
                </div>
                <div class="flex items-center justify-between text-[10px] text-slate-400 font-bold mt-1.5">
                    <?php if ($is_unlimited): ?>
                        <span class="text-indigo-600 font-bold">♾️ Kuota Bebas</span>
                        <span>Tanpa Batas</span>
                    <?php else: ?>
                        <span class="<?php echo $storage_pct >= 90 ? 'text-rose-600 font-black' : ''; ?>"><?php echo $storage_pct; ?>% kuota terpakai</span>
                        <span>Sisa <?php echo $storage_remaining_formatted; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card 3: Dibagikan ke Saya (Neutral zero state) -->
        <div class="doc-card-kpi p-5 border-t-[3px] border-t-sky-500 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Dibagikan ke Saya</span>
                    <div class="w-8 h-8 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center border border-sky-100">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl md:text-3xl font-black tracking-tight leading-none <?php echo count($shared_documents) > 0 ? 'text-sky-600' : 'text-slate-400'; ?>"><?php echo count($shared_documents); ?></span>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 font-semibold mt-2">dari rekan kerja</div>
        </div>

        <!-- Card 4: Tautan Publik Aktif (Neutral zero state) -->
        <div class="doc-card-kpi p-5 border-t-[3px] border-t-amber-500 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Tautan Publik Aktif</span>
                    <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-100">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl md:text-3xl font-black tracking-tight leading-none <?php echo count($public_shares_raw) > 0 ? 'text-amber-600' : 'text-slate-400'; ?>"><?php echo count($public_shares_raw); ?></span>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 font-semibold mt-2">siap diakses luar</div>
        </div>
    </div>

    <!-- Navigation Tabs & View Mode Switcher -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-3">
        <!-- Tabs with Clean Pill Styles (Fixed Typo: DIBAGIKAN KE SAYA) -->
        <div class="flex items-center gap-2">
            <a href="?page=documents&tab=my" 
               class="px-4 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab === 'my' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200/80 hover:bg-slate-50'; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Dokumen Saya
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black <?php echo $tab === 'my' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-600'; ?>">
                    <?php echo count($my_documents); ?>
                </span>
            </a>
            <a href="?page=documents&tab=shared" 
               class="px-4 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider transition-all flex items-center gap-2 <?php echo $tab === 'shared' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200/80 hover:bg-slate-50'; ?>">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Dibagikan ke Saya
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black <?php echo $tab === 'shared' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-600'; ?>">
                    <?php echo count($shared_documents); ?>
                </span>
            </a>
        </div>

        <!-- View Switcher (Grid vs List View) -->
        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/80 self-start sm:self-auto">
            <button type="button" id="btnViewGrid" onclick="setDocViewMode('grid')" title="Tampilan Kisi (Grid)"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 bg-white text-slate-900 shadow-xs">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>Grid</span>
            </button>
            <button type="button" id="btnViewList" onclick="setDocViewMode('list')" title="Tampilan Tabel (List)"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 text-slate-500 hover:text-slate-900">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                <span>Tabel</span>
            </button>
        </div>
    </div>

    <!-- Main Content Area: Document View (Full Width 3 or 4 Columns) -->
    <div class="w-full space-y-4">

        <?php if (empty($documents)): ?>
        <!-- Empty State Card -->
        <div class="bg-white rounded-3xl border border-slate-200/90 shadow-sm p-12 md:p-16 text-center">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 bg-indigo-50 text-indigo-600 border border-indigo-100 shadow-sm">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
            </div>
            <h3 class="text-slate-900 font-black text-base mb-1.5">
                <?php echo $tab === 'shared' ? 'Belum Ada Dokumen Dibagikan' : 'Belum Ada Dokumen Tersimpan'; ?>
            </h3>
            <p class="text-slate-400 text-xs md:text-sm max-w-md mx-auto leading-relaxed font-medium mb-6">
                <?php echo $tab === 'shared' 
                    ? 'Ketika rekan kerja membagikan berkas ke akun Anda, berkas akan muncul di panel ini secara instan.' 
                    : 'Unggah berkas foto, PDF, laporan dokumen, atau spreadsheet Anda untuk mulai mengelola dokumen kerja secara terorganisir.'; ?>
            </p>
            <?php if ($tab === 'my'): ?>
            <button type="button" onclick="openUploadModal()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition-transform active:scale-95 shadow-sm shadow-indigo-600/20">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Unggah Berkas Sekarang
            </button>
            <?php endif; ?>
        </div>

        <?php else: ?>

        <!-- ===================== VIEW 1: GRID VIEW (Full Width Responsive 1-2-3-4 Columns) ===================== -->
        <div id="docContainerGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            <?php foreach ($documents as $doc):
                $clean_path         = str_replace('\\', '/', $doc['file_path']);
                $info               = docInfo($doc['file_type'], $clean_path ?: $doc['name']);
                $iconSvg            = docIconSvg($info['icon'], $info['color']);
                $serve_view_url     = 'serve_file.php?file=' . rawurlencode($clean_path);
                $serve_download_url = 'serve_file.php?file=' . rawurlencode($clean_path) . '&download=1';
                $tags               = array_filter(array_map('trim', explode(',', $doc['tags'] ?? '')));
                $is_owner           = ((int)$doc['user_id'] === $user_id);
                $pubShare           = $public_shares_by_doc[$doc['id']] ?? null;
                $isImage            = !empty($info['is_image']);
            ?>
            <div class="doc-grid-card rounded-2xl border border-slate-200/80 bg-white shadow-xs overflow-hidden flex flex-col justify-between group cursor-pointer transition-all hover:shadow-lg hover:-translate-y-1 hover:border-indigo-300"
                 data-name="<?php echo htmlspecialchars($doc['name']); ?>"
                 data-tags="<?php echo htmlspecialchars($doc['tags'] ?? ''); ?>"
                 onclick="viewDocument('<?php echo htmlspecialchars($clean_path); ?>', '<?php echo htmlspecialchars($doc['file_type']); ?>', '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', '<?php echo htmlspecialchars($serve_download_url); ?>')">
                
                <div>
                    <!-- Consistent Fixed-Height (h-36) Header for Perfect Grid Alignment -->
                    <?php if ($isImage): ?>
                    <div class="h-36 w-full overflow-hidden bg-slate-50 relative border-b border-slate-100 flex items-center justify-center">
                        <img src="<?php echo htmlspecialchars($serve_view_url); ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" 
                             loading="lazy" 
                             alt="<?php echo htmlspecialchars($doc['name']); ?>"
                             onerror="this.onerror=null; this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
                        <!-- Safe Fallback Placeholder if Image Fails to Load -->
                        <div class="hidden w-full h-full flex flex-col items-center justify-center bg-slate-100/80 text-slate-400">
                            <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200 flex items-center justify-center shadow-xs">
                                <?php echo $iconSvg; ?>
                            </div>
                            <span class="text-[10px] font-semibold text-slate-400 mt-1.5">Berkas Gambar</span>
                        </div>
                        <!-- Frosted Glassmorphism Extension Badge -->
                        <span class="absolute top-2.5 right-2.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold text-white tracking-wider backdrop-blur-md bg-slate-900/65 shadow-xs border border-white/20">
                            <?php echo $info['label']; ?>
                        </span>
                    </div>
                    <?php else: ?>
                    <div class="h-36 w-full flex flex-col items-center justify-center relative border-b border-slate-100 transition-colors"
                         style="background: linear-gradient(180deg, <?php echo $info['bg']; ?> 0%, #FFFFFF 100%);">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center border shadow-xs group-hover:scale-110 transition-transform duration-200"
                             style="background:<?php echo $info['bg']; ?>; border-color:<?php echo $info['border']; ?>;">
                            <?php echo $iconSvg; ?>
                        </div>
                        <!-- Frosted Glassmorphism Extension Badge -->
                        <span class="absolute top-2.5 right-2.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold text-white tracking-wider backdrop-blur-md bg-slate-900/65 shadow-xs border border-white/20">
                            <?php echo $info['label']; ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <!-- Card Body Content -->
                    <div class="p-4">
                        <!-- Document Name -->
                        <h3 class="font-black text-slate-900 text-xs sm:text-sm group-hover:text-indigo-600 transition-colors line-clamp-2 leading-snug mb-1">
                            <?php echo htmlspecialchars($doc['name']); ?>
                        </h3>

                        <!-- Compact Meta Info: Size • Date -->
                        <div class="text-[11px] font-semibold text-slate-400 mb-2 flex items-center gap-1.5">
                            <span><?php echo formatSize($doc['file_size']); ?></span>
                            <span>•</span>
                            <span><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></span>
                        </div>

                        <!-- Tags / Collaboration Chips in Rounded-Full Pastel Pills -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <?php if ($tab === 'shared' && !empty($doc['owner_name'])): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 rounded-full">
                                👤 <?php echo htmlspecialchars($doc['owner_name']); ?>
                            </span>
                            <?php endif; ?>

                            <?php if ($is_owner && !empty($doc['share_count']) && (int)$doc['share_count'] > 0): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                                🔗 Tim (<?php echo $doc['share_count']; ?>)
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($pubShare)): ?>
                            <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-full" 
                                  title="Tautan publik aktif hingga <?php echo $pubShare['expires_formatted']; ?>">
                                🌐 Publik: <?php echo $pubShare['remaining_text']; ?>
                            </span>
                            <?php endif; ?>

                            <?php foreach($tags as $tag): ?>
                            <button type="button" 
                                    onclick="event.stopPropagation(); filterByDocTag('<?php echo htmlspecialchars(addslashes($tag)); ?>')" 
                                    title="Filter dokumen dengan tag #<?php echo htmlspecialchars($tag); ?>"
                                    class="inline-flex items-center text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 hover:bg-indigo-100 hover:border-indigo-200 transition-colors">
                                #<?php echo htmlspecialchars($tag); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Footer Action Buttons (Balanced Layout: Primary Action Left, Secondary Actions Right) -->
                <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between gap-2 mt-auto" onclick="event.stopPropagation()">
                    <!-- Primary Quick Action: Pratinjau Button -->
                    <button type="button" 
                            onclick="viewDocument('<?php echo htmlspecialchars($clean_path); ?>', '<?php echo htmlspecialchars($doc['file_type']); ?>', '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', '<?php echo htmlspecialchars($serve_download_url); ?>')"
                            title="Lihat Pratinjau Berkas" aria-label="Lihat dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                            class="px-3 py-1.5 rounded-xl bg-slate-50 hover:bg-indigo-50 text-slate-700 hover:text-indigo-600 border border-slate-200/80 text-xs font-bold transition-all flex items-center gap-1.5 shadow-xs">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span>Pratinjau</span>
                    </button>

                    <!-- Secondary Actions Group: Unduh, Bagikan, Hapus -->
                    <div class="flex items-center gap-1">
                        <!-- Unduh -->
                        <a href="<?php echo htmlspecialchars($serve_download_url); ?>" 
                           title="Unduh Berkas" aria-label="Unduh dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                           class="w-8 h-8 rounded-xl bg-slate-50 hover:bg-emerald-50 text-slate-500 hover:text-emerald-600 border border-slate-200/80 flex items-center justify-center transition-colors">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </a>

                        <!-- Bagikan (Only Owner) -->
                        <?php if ($is_owner): ?>
                        <button type="button" 
                                onclick="openShareModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', <?php echo !empty($pubShare) ? htmlspecialchars(json_encode($pubShare), ENT_QUOTES, 'UTF-8') : 'null'; ?>)"
                                title="Bagikan Dokumen" aria-label="Bagikan dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                                class="w-8 h-8 rounded-xl bg-slate-50 hover:bg-sky-50 text-slate-500 hover:text-sky-600 border border-slate-200/80 flex items-center justify-center transition-colors">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                        </button>
                        <?php endif; ?>

                        <!-- Hapus (Owner or Admin) -->
                        <?php if ($is_owner || isAdmin()): ?>
                        <button type="button" 
                                onclick="confirmDelete(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['name']); ?>')"
                                title="Hapus Dokumen" aria-label="Hapus dokumen <?php echo htmlspecialchars($doc['name']); ?>"
                                class="w-8 h-8 rounded-xl bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-600 border border-slate-200/80 flex items-center justify-center transition-colors">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===================== VIEW 2: LIST / TABLE VIEW ===================== -->
        <div id="docContainerList" class="doc-glass-panel rounded-3xl overflow-hidden hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/80 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200/80">
                        <tr>
                            <th class="px-5 py-3.5">Dokumen</th>
                            <th class="px-4 py-3.5">Ukuran</th>
                            <th class="px-4 py-3.5">Tanggal</th>
                            <th class="px-4 py-3.5">Kolaborasi</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($documents as $doc):
                            $clean_path         = str_replace('\\', '/', $doc['file_path']);
                            $info               = docInfo($doc['file_type'], $clean_path ?: $doc['name']);
                            $iconSvg            = docIconSvg($info['icon'], $info['color']);
                            $serve_download_url = 'serve_file.php?file=' . rawurlencode($clean_path) . '&download=1';
                            $tags               = array_filter(array_map('trim', explode(',', $doc['tags'] ?? '')));
                            $is_owner           = ((int)$doc['user_id'] === $user_id);
                            $pubShare           = $public_shares_by_doc[$doc['id']] ?? null;
                        ?>
                        <tr class="doc-table-row" data-name="<?php echo htmlspecialchars($doc['name']); ?>" data-tags="<?php echo htmlspecialchars($doc['tags'] ?? ''); ?>">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 border"
                                         style="background:<?php echo $info['bg']; ?>; border-color:<?php echo $info['border']; ?>;">
                                        <?php echo $iconSvg; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <button type="button" 
                                                onclick="viewDocument('<?php echo htmlspecialchars($clean_path); ?>', '<?php echo htmlspecialchars($doc['file_type']); ?>', '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', '<?php echo htmlspecialchars($serve_download_url); ?>')"
                                                class="font-black text-slate-900 text-xs hover:text-indigo-600 transition-colors truncate block max-w-xs text-left">
                                            <?php echo htmlspecialchars($doc['name']); ?>
                                        </button>
                                        <span class="text-[10px] font-extrabold uppercase" style="color:<?php echo $info['color']; ?>;">
                                            <?php echo $info['label']; ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 font-bold text-slate-600 whitespace-nowrap">
                                <?php echo formatSize($doc['file_size']); ?>
                            </td>
                            <td class="px-4 py-3.5 text-slate-500 font-medium whitespace-nowrap">
                                <?php echo date('d M Y', strtotime($doc['upload_date'])); ?>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <?php if ($tab === 'shared' && !empty($doc['owner_name'])): ?>
                                    <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded-md">
                                        👤 <?php echo htmlspecialchars($doc['owner_name']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($is_owner && !empty($doc['share_count']) && (int)$doc['share_count'] > 0): ?>
                                    <span class="text-[10px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">
                                        🔗 <?php echo $doc['share_count']; ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($pubShare)): ?>
                                    <span class="text-[10px] font-extrabold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md">
                                        🌐 <?php echo $pubShare['remaining_text']; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button" 
                                            onclick="viewDocument('<?php echo htmlspecialchars($clean_path); ?>', '<?php echo htmlspecialchars($doc['file_type']); ?>', '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', '<?php echo htmlspecialchars($serve_download_url); ?>')"
                                            title="Lihat" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 flex items-center justify-center transition-colors">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <a href="<?php echo htmlspecialchars($serve_download_url); ?>" 
                                       title="Unduh" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-600 flex items-center justify-center transition-colors">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    </a>
                                    <?php if ($is_owner): ?>
                                    <button type="button" 
                                            onclick="openShareModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['name'])); ?>', <?php echo !empty($pubShare) ? htmlspecialchars(json_encode($pubShare), ENT_QUOTES, 'UTF-8') : 'null'; ?>)"
                                            title="Bagikan" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-sky-50 text-slate-600 hover:text-sky-600 flex items-center justify-center transition-colors">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($is_owner || isAdmin()): ?>
                                    <button type="button" 
                                            onclick="confirmDelete(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['name']); ?>')"
                                            title="Hapus" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-600 flex items-center justify-center transition-colors">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

<!-- ============================================================== -->
<!-- 0. DEDICATED UPLOAD MODAL (Clean, Non-Redundant & Focused) -->
<!-- ============================================================== -->
<div id="uploadModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-md" role="dialog" aria-modal="true" aria-labelledby="uploadModalTitle">
    <div class="bg-white rounded-3xl max-w-xl w-full shadow-2xl overflow-hidden flex flex-col border border-slate-200" onclick="event.stopPropagation()">
        
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/25">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900" id="uploadModalTitle">Unggah Dokumen Baru</h3>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Kompresi Cerdas Aktif
                        </span>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">
                            Sisa Kuota: <?php echo $storage_remaining_formatted; ?>
                        </span>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeUploadModal()" aria-label="Tutup modal unggah" class="w-8 h-8 rounded-xl hover:bg-slate-200 text-slate-400 hover:text-slate-900 transition-colors flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Upload Form -->
        <form method="POST" enctype="multipart/form-data" data-no-ajax="true" class="p-6 space-y-4" id="docUploadForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="add_doc" value="1">

            <!-- Drag & Drop Zone -->
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Pilih Berkas</label>
                <label for="fileInput" 
                       id="dropZone"
                       class="flex flex-col items-center justify-center gap-2.5 px-4 py-8 rounded-2xl border-2 border-dashed border-slate-200 hover:border-indigo-500 bg-slate-50/50 hover:bg-indigo-50/20 transition-all cursor-pointer group">
                    <div class="w-12 h-12 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-slate-400 group-hover:text-indigo-600 group-hover:scale-105 transition-all shadow-xs">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div class="text-center">
                        <span class="text-slate-800 text-xs font-bold block mb-0.5">Pilih atau Seret Berkas ke Sini</span>
                        <span class="text-slate-400 text-[10px]">Foto (JPG, PNG), PDF, Dokumen Word, Excel, TXT, ZIP</span>
                    </div>
                    <input type="file" name="file" required class="hidden" id="fileInput" onchange="handleFileSelect(this)">
                </label>

                <!-- Selected File Preview Chip -->
                <div id="fileSelectedBox" class="hidden mt-2.5 p-3 rounded-xl bg-indigo-50/80 border border-indigo-200/80 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-[10px] font-black flex-shrink-0">✓</span>
                        <div class="min-w-0">
                            <p id="selectedFileName" class="text-xs font-black text-indigo-950 truncate"></p>
                            <p id="selectedFileSize" class="text-[10px] font-semibold text-indigo-600"></p>
                        </div>
                    </div>
                    <button type="button" onclick="clearSelectedFile()" class="text-xs font-bold text-slate-400 hover:text-rose-600 p-1">✕</button>
                </div>
            </div>

            <!-- Name -->
            <div>
                <label for="docNameInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nama Dokumen</label>
                <input type="text" name="name" id="docNameInput" placeholder="Contoh: Laporan Keuangan Q4" required
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500 transition-all placeholder:text-slate-400">
            </div>

            <!-- Tags -->
            <div>
                <label for="docTagsInput" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tag Kategori (pisahkan dengan koma)</label>
                <input type="text" name="tags" id="docTagsInput" placeholder="keuangan, penting, internal"
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500 transition-all placeholder:text-slate-400">
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeUploadModal()"
                        class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-100 transition-all text-xs">
                    Batal
                </button>
                <button type="submit" id="btnUploadSubmit"
                        class="px-5 py-2.5 rounded-xl font-bold text-white transition-transform active:scale-95 flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/25 text-xs">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span>Unggah &amp; Kompres Berkas</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================== -->
<!-- 1. SHARE MODAL (Public Links with Expiry & Internal Sharing) -->
<!-- ============================================================== -->
<div id="shareModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl overflow-hidden flex flex-col border border-slate-200">
        
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/60">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100 shadow-xs">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </div>
                <div>
                    <h3 class="text-base font-black text-slate-900" id="shareModalTitle">Bagikan Dokumen</h3>
                    <p class="text-xs text-slate-400 font-medium truncate max-w-[260px]" id="shareDocName"></p>
                </div>
            </div>
            <button type="button" onclick="closeShareModal()" aria-label="Tutup modal berbagi" class="w-8 h-8 rounded-xl hover:bg-slate-200 text-slate-400 hover:text-slate-900 transition-colors flex items-center justify-center">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <!-- Share Tabs Switcher -->
        <div class="flex border-b border-slate-100 bg-slate-50/80 p-1.5 gap-1.5 text-xs font-bold">
            <button type="button" id="tabBtnExternal" onclick="switchShareModalTab('external')"
                    class="flex-1 py-2 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-white text-indigo-700 shadow-xs border border-slate-200/80">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Tautan Publik Berbatas Waktu
            </button>
            <button type="button" id="tabBtnInternal" onclick="switchShareModalTab('internal')"
                    class="flex-1 py-2 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-500 hover:text-slate-900">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Rekan Tim Internal
            </button>
        </div>

        <div class="p-6">
            <!-- TAB 1: EXTERNAL PUBLIC SHARE LINK -->
            <div id="tabContentExternal" class="space-y-4">
                
                <!-- If Active Share Exists -->
                <div id="sharePublicActiveBlock" class="hidden space-y-4">
                    <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-amber-950">
                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center gap-1.5 text-xs font-black uppercase tracking-wider text-amber-800">
                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                Tautan Aktif
                            </span>
                            <span id="pubExpiryPill" class="text-[11px] font-bold text-amber-900 bg-amber-100 px-2.5 py-0.5 rounded-full border border-amber-200"></span>
                        </div>
                        <p class="text-xs text-amber-900 font-medium mb-3">Siapa pun yang memiliki tautan ini dapat mengunduh dokumen sebelum batas waktu habis.</p>

                        <!-- Copy Link Field -->
                        <div class="flex items-center gap-2">
                            <input type="text" id="pubShareUrlInput" readonly
                                   class="flex-1 bg-white border border-amber-300 rounded-xl px-3 py-2 text-xs font-mono text-slate-800 outline-none select-all">
                            <button type="button" id="copyBtn" onclick="copyShareLink()"
                                    class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs transition-transform active:scale-95 flex items-center gap-1.5 shadow-sm">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                <span id="copyBtnText">Salin</span>
                            </button>
                        </div>

                        <!-- Stats Views & Downloads -->
                        <div class="mt-3 pt-3 border-t border-amber-200/80 flex items-center justify-between text-[11px] text-amber-900 font-semibold">
                            <span id="pubViewsText">Dilihat: 0 kali</span>
                            <span id="pubDownloadsText">Diunduh: 0 kali</span>
                        </div>
                    </div>

                    <!-- Revoke Form -->
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan tautan publik ini? Pengguna luar tidak akan bisa mengakses berkas lagi.');">
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
                        Buat tautan aman untuk dibagikan ke klien atau pihak luar. Tautan otomatis kedaluwarsa setelah durasi yang Anda tentukan.
                    </p>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="create_public_share" value="1">
                        <input type="hidden" name="doc_id" id="pubDocId">

                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Masa Berlaku Tautan (Expired)</label>
                            <select name="duration" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500">
                                <option value="1_hour">1 Jam</option>
                                <option value="6_hours">6 Jam</option>
                                <option value="24_hours" selected>24 Jam (1 Hari)</option>
                                <option value="3_days">3 Hari</option>
                                <option value="7_days">7 Hari (1 Minggu)</option>
                                <option value="30_days">30 Hari (1 Bulan)</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/20 transition-transform active:scale-95 text-xs flex items-center justify-center gap-2">
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
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Pilih Rekan Kerja</label>
                        <select name="target_user_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500">
                            <option value="">-- Pilih Anggota Tim --</option>
                            <?php foreach($system_users as $su): ?>
                            <option value="<?php echo $su['id']; ?>"><?php echo htmlspecialchars($su['display_name']); ?> (<?php echo htmlspecialchars($su['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Hak Akses</label>
                        <select name="permission" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white focus:border-indigo-500">
                            <option value="view">Hanya Pratinjau (Lihat Saja)</option>
                            <option value="download">Pratinjau &amp; Boleh Unduh</option>
                        </select>
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl font-bold text-white bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-600/20 transition-transform active:scale-95 text-xs flex items-center justify-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        Berikan Akses Internal
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- 2. DOCUMENT PREVIEW MODAL (LIGHTBOX) -->
<!-- ============================================================== -->
<div id="viewModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
    <div class="bg-white rounded-3xl max-w-5xl w-full shadow-2xl overflow-hidden flex flex-col max-h-[92vh] border border-slate-200">
        
        <!-- Modal Top Bar -->
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center flex-shrink-0 bg-slate-50/70">
            <div>
                <h3 class="text-base font-black text-slate-900" id="viewModalTitle">Pratinjau Dokumen</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest" id="viewModalType"></p>
            </div>
            <div class="flex items-center gap-2">
                <a id="viewModalDownload" href="#" download
                   class="flex items-center gap-2 px-4 py-2 text-xs font-bold rounded-xl transition-all text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 shadow-xs">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    <span>Unduh Berkas</span>
                </a>
                <button type="button" onclick="closeModal()" aria-label="Tutup pratinjau" 
                        class="w-8 h-8 rounded-xl hover:bg-slate-200 text-slate-400 hover:text-slate-900 transition-colors flex items-center justify-center">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Document Viewer Content Area -->
        <div class="flex-1 overflow-auto p-6 min-h-[480px]">
            <div id="documentViewer" class="w-full h-full rounded-2xl flex items-center justify-center min-h-[460px] bg-slate-50"></div>
        </div>
    </div>
</div>

<script>
(function() {
    // -------------------------------------------------------------
    // View Mode Persistence (Grid vs Table)
    // -------------------------------------------------------------
    window.setDocViewMode = function(mode) {
        const grid = document.getElementById('docContainerGrid');
        const list = document.getElementById('docContainerList');
        const btnGrid = document.getElementById('btnViewGrid');
        const btnList = document.getElementById('btnViewList');

        if (!grid || !list) return;

        if (mode === 'list') {
            grid.classList.add('hidden');
            list.classList.remove('hidden');
            btnList.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 bg-white text-slate-900 shadow-xs';
            btnGrid.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 text-slate-500 hover:text-slate-900';
            localStorage.setItem('taskflow_doc_view', 'list');
        } else {
            list.classList.add('hidden');
            grid.classList.remove('hidden');
            btnGrid.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 bg-white text-slate-900 shadow-xs';
            btnList.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 text-slate-500 hover:text-slate-900';
            localStorage.setItem('taskflow_doc_view', 'grid');
        }
    };

    // Restore user view preference
    const savedView = localStorage.getItem('taskflow_doc_view') || 'grid';
    window.setDocViewMode(savedView);

    // -------------------------------------------------------------
    // File Selection & Drag-and-Drop Handler
    // -------------------------------------------------------------
    const USER_QUOTA_BYTES = <?php echo (int)$user_quota; ?>;
    const REMAINING_QUOTA_BYTES = <?php echo (int)$storage_remaining_bytes; ?>;

    window.handleFileSelect = function(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        const fileBox = document.getElementById('fileSelectedBox');
        const fileNameEl = document.getElementById('selectedFileName');
        const fileSizeEl = document.getElementById('selectedFileSize');
        const docNameInput = document.getElementById('docNameInput');
        const submitBtn = document.getElementById('btnUploadSubmit');

        if (fileBox && fileNameEl && fileSizeEl) {
            fileNameEl.textContent = file.name;
            
            // Check quota limit client-side
            if (USER_QUOTA_BYTES > 0 && file.size > REMAINING_QUOTA_BYTES) {
                fileBox.className = 'mt-2.5 p-3 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-between gap-2';
                fileSizeEl.innerHTML = `<span class="text-rose-600 font-bold">${formatBytesJs(file.size)} &mdash; Melebihi sisa kuota (${formatBytesJs(REMAINING_QUOTA_BYTES)})</span>`;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            } else {
                fileBox.className = 'mt-2.5 p-3 rounded-xl bg-indigo-50/80 border border-indigo-200/80 flex items-center justify-between gap-2';
                fileSizeEl.textContent = formatBytesJs(file.size);
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
            fileBox.classList.remove('hidden');
        }

        // Auto-fill document title if empty
        if (docNameInput && !docNameInput.value.trim()) {
            const cleanName = file.name.replace(/\.[^/.]+$/, "").replace(/[_-]/g, " ");
            docNameInput.value = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
        }
    };

    window.clearSelectedFile = function() {
        const fileInput = document.getElementById('fileInput');
        const fileBox = document.getElementById('fileSelectedBox');
        const submitBtn = document.getElementById('btnUploadSubmit');
        if (fileInput) fileInput.value = '';
        if (fileBox) fileBox.classList.add('hidden');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    };

    function formatBytesJs(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // -------------------------------------------------------------
    // Share Modal
    // -------------------------------------------------------------
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
            extBtn.className = 'flex-1 py-2 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-white text-indigo-700 shadow-xs border border-slate-200/80';
            intBtn.className = 'flex-1 py-2 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-500 hover:text-slate-900';
        } else {
            extContent.classList.add('hidden');
            intContent.classList.remove('hidden');
            intBtn.className = 'flex-1 py-2 px-3 rounded-xl transition-all flex items-center justify-center gap-2 bg-white text-indigo-700 shadow-xs border border-slate-200/80';
            extBtn.className = 'flex-1 py-2 px-3 rounded-xl transition-all flex items-center justify-center gap-2 text-slate-500 hover:text-slate-900';
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

    // -------------------------------------------------------------
    // Preview Modal (Lightbox)
    // -------------------------------------------------------------
    window.viewDocument = function(filePath, fileType, fileName, downloadUrl) {
        const modal  = document.getElementById('viewModal');
        const viewer = document.getElementById('documentViewer');
        const title  = document.getElementById('viewModalTitle');
        const typeEl = document.getElementById('viewModalType');
        const dlBtn  = document.getElementById('viewModalDownload');

        title.textContent  = fileName || 'Pratinjau Dokumen';
        typeEl.textContent = fileType;
        dlBtn.href         = downloadUrl || ('serve_file.php?file=' + encodeURIComponent(filePath) + '&download=1');
        dlBtn.setAttribute('download', fileName || '');
        viewer.innerHTML   = '';
        viewer.style.background = '#f8fafc';

        const serveUrl = 'serve_file.php?file=' + encodeURIComponent(filePath);

        if (fileType.startsWith('image/')) {
            viewer.innerHTML = `<img src="${serveUrl}" alt="Preview ${escHtml(fileName)}" style="max-width:100%;max-height:520px;object-fit:contain;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.06)">`;
        } else if (fileType === 'application/pdf') {
            viewer.style.background = 'transparent';
            viewer.innerHTML = `<object data="${serveUrl}" type="application/pdf" style="width:100%;height:540px;border:none;border-radius:12px">
                <div style="text-align:center;padding:48px;color:#475569">
                    <p style="font-weight:700;margin-bottom:12px">Browser tidak dapat menampilkan pratinjau PDF interaktif.</p>
                    <a href="${serveUrl}" target="_blank" style="background:#5148D9;color:#fff;padding:10px 24px;border-radius:12px;font-weight:700;text-decoration:none">Buka PDF di Tab Baru</a>
                </div></object>`;
        } else if (fileType === 'text/plain') {
            fetch(serveUrl).then(r=>r.text()).then(text=>{
                viewer.innerHTML = `<pre style="width:100%;height:460px;overflow:auto;padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;line-height:1.6;color:#334155;white-space:pre-wrap;word-break:break-word">${escHtml(text)}</pre>`;
            }).catch(()=>{ viewer.innerHTML = noPreview(serveUrl); });
        } else {
            viewer.innerHTML = noPreview(serveUrl);
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    };

    function noPreview(downloadUrl) {
        return `<div style="text-align:center;padding:48px;color:#64748b">
            <div style="width:64px;height:64px;border-radius:20px;background:#eef2ff;color:#5148D9;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <p style="font-weight:800;font-size:14px;color:#1e293b;margin-bottom:6px">Pratinjau Langsung Tidak Tersedia</p>
            <p style="font-size:12px;color:#64748b;margin-bottom:18px">Format berkas ini tidak dapat dipratinjau langsung di peramban.</p>
            <a href="${downloadUrl}" style="background:#5148D9;color:#fff;padding:10px 22px;border-radius:12px;font-size:12px;font-weight:700;text-decoration:none;display:inline-block">Unduh Berkas untuk Membuka</a>
        </div>`;
    }

    function escHtml(text) {
        return (text || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    
    // -------------------------------------------------------------
    // Upload Modal
    // -------------------------------------------------------------
    window.openUploadModal = function() {
        const m = document.getElementById('uploadModal');
        if (m) {
            m.classList.remove('hidden');
            m.style.display = 'flex';
            const fileIn = document.getElementById('fileInput');
            if (fileIn) fileIn.focus();
        }
    };

    window.closeUploadModal = function() {
        const m = document.getElementById('uploadModal');
        if (m) {
            m.classList.add('hidden');
            m.style.display = 'none';
        }
    };

    // -------------------------------------------------------------
    // Instant Client-side Filter / Search
    // -------------------------------------------------------------
    window.handleDocSearch = function(query) {
        const q = (query || '').toLowerCase().trim();
        const clearBtn = document.getElementById('docSearchClear');
        if (clearBtn) clearBtn.classList.toggle('hidden', !q);

        document.querySelectorAll('#docContainerGrid .doc-grid-card').forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const tags = (card.getAttribute('data-tags') || '').toLowerCase();
            if (!q || name.includes(q) || tags.includes(q)) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });

        document.querySelectorAll('#docContainerList .doc-table-row').forEach(row => {
            const name = (row.getAttribute('data-name') || '').toLowerCase();
            const tags = (row.getAttribute('data-tags') || '').toLowerCase();
            if (!q || name.includes(q) || tags.includes(q)) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    };

    window.filterByDocTag = function(tag) {
        const input = document.getElementById('docSearchInput');
        if (input) {
            input.value = tag;
            handleDocSearch(tag);
        }
    };

    window.clearDocSearch = function() {
        const input = document.getElementById('docSearchInput');
        if (input) {
            input.value = '';
            handleDocSearch('');
            input.focus();
        }
    };

    window.closeModal = function() {
        const modal = document.getElementById('viewModal');
        modal.classList.add('hidden');
        modal.style.display = 'none';
        document.getElementById('documentViewer').innerHTML = '';
    };

    window.confirmDelete = function(id, name) {
        if (confirm('Apakah Anda yakin ingin menghapus berkas "' + name + '"? Berkas akan dihapus secara permanen dari server.')) {
            document.getElementById('deleteDocId').value = id;
            document.getElementById('deleteForm').submit();
        }
    };

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeShareModal();
            closeUploadModal();
        }
    });
})();
</script>
