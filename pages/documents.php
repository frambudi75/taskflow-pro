<?php
if (!defined('IN_APP')) exit('Akses Ditolak');
// pages/documents.php
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? AND (name LIKE ? OR tags LIKE ?) ORDER BY upload_date DESC");
    $stmt->execute([$_SESSION['user_id'], '%' . $search . '%', '%' . $search . '%']);
} else {
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$_SESSION['user_id']]);
}
$documents = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doc'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $name = $_POST['name'];
        $tags = $_POST['tags'];

        $file_path = null;
        $file_size = 0;
        $type = 'Unknown';
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            $upload_dir = 'uploads/' . $_SESSION['user_id'] . '/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $filename    = time() . '_' . basename($file['name']);
            $target_path = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $file_path = $target_path;
                $file_size = $file['size'];
                $type      = $file['type'];
            } else {
                header("Location: index.php?page=documents&error=upload_failed");
                exit;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO documents (user_id, name, file_path, file_type, file_size, tags) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $name, $file_path, $type, $file_size, $tags]);
        $doc_id = $pdo->lastInsertId();
        logActivity("Mengunggah dokumen: " . $name, "document", $doc_id);
        header("Location: index.php?page=documents&success=uploaded");
        exit;
    }
}

// Logic: Delete Document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doc'])) {
    if (validateCSRF($_POST['csrf_token'])) {
        $doc_id = $_POST['doc_id'];
        
        // Fetch to get path & verify ownership
        $stmt = $pdo->prepare("SELECT file_path, name FROM documents WHERE id = ? AND user_id = ?");
        $stmt->execute([$doc_id, $_SESSION['user_id']]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            // Delete physical file
            if ($doc['file_path'] && file_exists($doc['file_path'])) {
                unlink($doc['file_path']);
            }
            
            // Delete from DB
            $del = $pdo->prepare("DELETE FROM documents WHERE id = ?");
            $del->execute([$doc_id]);
            
            logActivity("Menghapus dokumen: " . $doc['name'], "document_delete");
            header("Location: index.php?page=documents&success=deleted");
            exit;
        }
    }
}

// Helper: file-type icon & color
function docIconInfo($mime) {
    if (str_starts_with($mime, 'image/')) return ['color'=>'#0ea5e9','bg'=>'#e0f2fe','icon'=>'image'];
    if ($mime === 'application/pdf')       return ['color'=>'#ef4444','bg'=>'#fee2e2','icon'=>'pdf'];
    if (str_contains($mime, 'word'))       return ['color'=>'#3b82f6','bg'=>'#dbeafe','icon'=>'doc'];
    if (str_contains($mime, 'sheet') || str_contains($mime, 'excel')) return ['color'=>'#10b981','bg'=>'#dcfce7','icon'=>'sheet'];
    if ($mime === 'text/plain')            return ['color'=>'#64748b','bg'=>'#f1f5f9','icon'=>'text'];
    return ['color'=>'#8b5cf6','bg'=>'#ede9fe','icon'=>'file'];
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

<div class="p-5 md:p-8 max-w-7xl mx-auto">

    <!-- ===== HERO HEADER ===== -->
    <div class="rounded-3xl p-7 md:p-10 mb-7 relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5"
         style="background:linear-gradient(135deg,#052e16 0%,#065f46 45%,#0f766e 100%)">
        <div class="absolute inset-0 pointer-events-none"
             style="background-image:linear-gradient(rgba(255,255,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.04) 1px,transparent 1px);background-size:40px 40px"></div>
        <div class="absolute right-0 bottom-0 w-72 h-72 pointer-events-none"
             style="background:radial-gradient(circle,rgba(52,211,153,0.2) 0%,transparent 70%);transform:translate(30%,30%)"></div>
        <div class="relative z-10">
            <div class="text-emerald-300 text-[10px] font-black uppercase tracking-widest mb-2">Manajemen File</div>
            <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-1">📁 Arsip Dokumen</h1>
            <p class="text-emerald-300 text-sm font-medium"><?php echo count($documents); ?> file tersimpan · Aman &amp; terorganisir.</p>
        </div>
        <!-- Search in hero -->
        <div class="relative z-10 flex items-center gap-3 flex-shrink-0">
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="page" value="documents">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari dokumen..."
                           class="pl-9 pr-4 py-2.5 text-sm font-medium w-44 rounded-2xl text-white transition-all"
                           style="background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);outline:none">
                </div>
                <button type="submit" class="px-4 py-2.5 rounded-2xl text-sm font-bold text-white transition-all active:scale-95"
                        style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2)">Cari</button>
                <?php if ($search): ?>
                    <a href="?page=documents" class="px-3 py-2.5 rounded-2xl text-sm font-bold transition-all"
                       style="color:rgba(255,255,255,0.7);background:rgba(255,255,255,0.08)">✕</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'upload_failed'): ?>
    <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-100 rounded-2xl px-5 py-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <p class="text-red-600 text-sm font-bold">Gagal mengupload file. Silakan coba lagi.</p>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    <div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-100 rounded-3xl px-6 py-4 animate-in fade-in slide-in-from-top-4 duration-500">
        <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
            <p class="text-emerald-900 text-sm font-black italic">
                <?php 
                if($_GET['success'] === 'uploaded') echo "Dokumen Berhasil Diunggah!"; 
                if($_GET['success'] === 'deleted') echo "Dokumen Berhasil Dihapus!"; 
                ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-7">

        <!-- ===== DOCUMENT LIST ===== -->
        <div class="lg:col-span-8">
            <div class="bg-white rounded-[2rem] border border-slate-100 overflow-hidden" style="box-shadow:0 4px 24px rgba(0,0,0,0.06)">
                <div class="px-7 py-5 border-b border-slate-50 flex items-center justify-between">
                    <h3 class="font-black text-slate-900 text-base">File Tersimpan</h3>
                    <span class="text-xs font-bold text-slate-400"><?php echo count($documents); ?> total</span>
                </div>

                <?php if (empty($documents)): ?>
                <div class="p-16 text-center">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                         style="background:linear-gradient(135deg,#dcfce7,#d1fae5)">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="1.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <p class="text-slate-500 font-bold text-sm">Belum ada dokumen.</p>
                    <p class="text-slate-400 text-xs mt-1">Upload file pertama Anda di panel kanan.</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-slate-50">
                    <?php foreach($documents as $doc):
                        $info    = docIconInfo($doc['file_type']);
                        $iconSvg = docSvgIcon($info['icon'], $info['color']);
                        $base    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
                        $file_url = isset($doc['file_path']) && $doc['file_path']
                            ? $base . ltrim(str_replace('\\', '/', $doc['file_path']), '/')
                            : null;
                        $tags = array_filter(array_map('trim', explode(',', $doc['tags'])));
                    ?>
                    <div class="group px-7 py-5 hover:bg-slate-50 transition-all flex items-center gap-5">
                        <!-- Icon -->
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0 transition-all"
                             style="background:<?php echo $info['bg']; ?>">
                            <?php echo $iconSvg; ?>
                        </div>
                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <?php if ($file_url): ?>
                                <a href="<?php echo htmlspecialchars($file_url); ?>" download
                                   class="font-black text-slate-900 text-sm hover:text-emerald-700 truncate block mb-1 transition-colors"
                                   style="text-decoration:none">
                                    <?php echo htmlspecialchars($doc['name']); ?>
                                </a>
                            <?php else: ?>
                                <div class="font-black text-slate-900 text-sm truncate mb-1"><?php echo htmlspecialchars($doc['name']); ?></div>
                            <?php endif; ?>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-bold uppercase text-slate-400"><?php echo $doc['file_type']; ?></span>
                                <span class="text-slate-200">·</span>
                                <span class="text-[10px] font-bold text-slate-400"><?php echo formatSize($doc['file_size']); ?></span>
                                <span class="text-slate-200">·</span>
                                <span class="text-[10px] font-bold text-slate-400"><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></span>
                                <?php foreach($tags as $tag): ?>
                                <span class="text-[9px] font-black px-2 py-0.5 rounded-full text-emerald-700"
                                      style="background:#dcfce7">#<?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <!-- Actions -->
                        <div class="flex items-center gap-1 opacity-100 md:opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="viewDocument('<?php echo htmlspecialchars(str_replace('\\', '/', $doc['file_path'])); ?>', '<?php echo htmlspecialchars($doc['file_type']); ?>', '<?php echo htmlspecialchars($doc['name']); ?>', '<?php echo htmlspecialchars($file_url ?? ''); ?>')"
                                    title="Preview"
                                    class="w-9 h-9 flex items-center justify-center rounded-xl transition-all hover:bg-indigo-50"
                                    style="color:#6366f1">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <?php if ($file_url): ?>
                            <a href="<?php echo htmlspecialchars($file_url); ?>" download title="Download"
                               class="w-9 h-9 flex items-center justify-center rounded-xl transition-all hover:bg-emerald-50"
                               style="color:#10b981">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                            <?php endif; ?>
                            <button onclick="confirmDelete(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['name']); ?>')"
                                    title="Hapus"
                                    class="w-9 h-9 flex items-center justify-center rounded-xl transition-all hover:bg-rose-50"
                                    style="color:#ef4444">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                            </button>
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

        <!-- ===== UPLOAD FORM ===== -->
        <div class="lg:col-span-4">
            <div class="bg-white rounded-[2rem] border border-slate-100 p-7 sticky top-6" style="box-shadow:0 4px 24px rgba(0,0,0,0.06)">
                <!-- Form header -->
                <div class="flex items-center gap-3 mb-7">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center"
                         style="background:linear-gradient(135deg,#10b981,#059669)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.3"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-900 text-base">Upload Dokumen</h3>
                        <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest">File Baru</p>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="add_doc" value="1">

                    <!-- File upload -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Pilih File</label>
                        <label class="flex flex-col items-center justify-center gap-2 px-4 py-8 rounded-2xl border-2 border-dashed cursor-pointer transition-all hover:border-emerald-400 hover:bg-emerald-50"
                               style="border-color:#e2e8f0" id="dropZone">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span class="text-slate-500 text-xs font-bold">Klik untuk pilih file</span>
                            <span class="text-slate-400 text-[10px]">PDF, Gambar, Word, dll</span>
                            <input type="file" name="file" required class="hidden" id="fileInput" onchange="updateFileName(this)">
                        </label>
                        <p id="fileName" class="text-xs text-emerald-600 font-bold mt-2 hidden"></p>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Nama Dokumen</label>
                        <input type="text" name="name" placeholder="Contoh: Laporan Keuangan Q1" required
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-900 focus:outline-none transition-all"
                               style="font-family:inherit">
                    </div>

                    <!-- Tags -->
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Tag (pisah koma)</label>
                        <input type="text" name="tags" placeholder="keuangan, penting, 2024"
                               class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-medium text-slate-700 focus:outline-none transition-all"
                               style="font-family:inherit">
                    </div>

                    <button type="submit"
                            class="w-full py-4 rounded-2xl font-bold text-white transition-all active:scale-95 flex items-center justify-center gap-2"
                            style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 8px 20px rgba(16,185,129,0.3)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Dokumen
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===== PREVIEW MODAL ===== -->
<div id="viewModal" class="fixed inset-0 hidden z-50 items-center justify-center p-4"
     style="background:rgba(0,0,0,0.65);backdrop-filter:blur(6px)">
    <div class="bg-white rounded-3xl max-w-5xl w-full shadow-2xl overflow-hidden flex flex-col" style="max-height:90vh">
        <div class="px-7 py-5 border-b border-slate-100 flex justify-between items-center flex-shrink-0">
            <div>
                <h3 class="text-base font-black text-slate-900" id="viewModalTitle">Pratinjau Dokumen</h3>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest" id="viewModalType"></p>
            </div>
            <div class="flex items-center gap-2">
                <a id="viewModalDownload" href="#" download
                   class="flex items-center gap-2 px-4 py-2 text-sm font-bold rounded-xl transition-all"
                   style="color:#374151;background:#f8fafc">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download
                </a>
                <button onclick="closeModal()" class="w-9 h-9 hover:bg-slate-100 rounded-xl transition-all flex items-center justify-center">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-auto p-6" style="min-height:500px">
            <div id="documentViewer" class="w-full h-full rounded-2xl flex items-center justify-center" style="min-height:480px;background:#f8fafc"></div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?php echo baseUrl(); ?>';

function updateFileName(input) {
    const label = document.getElementById('fileName');
    if (input.files && input.files[0]) {
        label.textContent = '✓ ' + input.files[0].name;
        label.classList.remove('hidden');
    }
}

function viewDocument(filePath, fileType, fileName, downloadUrl) {
    const modal  = document.getElementById('viewModal');
    const viewer = document.getElementById('documentViewer');
    const title  = document.getElementById('viewModalTitle');
    const typeEl = document.getElementById('viewModalType');
    const dlBtn  = document.getElementById('viewModalDownload');

    title.textContent  = fileName || 'Pratinjau';
    typeEl.textContent = fileType;
    dlBtn.href         = downloadUrl || filePath;
    dlBtn.setAttribute('download', fileName || '');
    viewer.innerHTML   = '';
    viewer.style.background = '#f8fafc';

    // filePath is 'uploads/X/file.jpg' or similar
    const serveUrl = BASE_URL + 'serve_file.php?file=' + encodeURIComponent(filePath);

    if (fileType.startsWith('image/')) {
        viewer.innerHTML = `<img src="${serveUrl}" alt="Preview" style="max-width:100%;max-height:500px;object-fit:contain;border-radius:12px">`;
    } else if (fileType === 'application/pdf') {
        viewer.style.background = 'transparent';
        viewer.innerHTML = `<object data="${serveUrl}" type="application/pdf" style="width:100%;height:520px;border:none;border-radius:12px">
            <div style="text-align:center;padding:40px;color:#6b7280">
                <p style="font-weight:700;margin-bottom:12px">Browser tidak bisa preview PDF langsung.</p>
                <a href="${serveUrl}" target="_blank" style="background:#10b981;color:#fff;padding:10px 24px;border-radius:12px;font-weight:700;text-decoration:none">Buka PDF →</a>
            </div></object>`;
    } else if (fileType === 'text/plain') {
        fetch(serveUrl).then(r=>r.text()).then(text=>{
            viewer.innerHTML = `<pre style="width:100%;height:450px;overflow:auto;padding:20px;background:#fff;border-radius:12px;font-family:monospace;font-size:13px;line-height:1.6;color:#374151;white-space:pre-wrap;word-break:break-word">${escHtml(text)}</pre>`;
        }).catch(()=>{ viewer.innerHTML = noPreview(filePath); });
    } else {
        viewer.innerHTML = noPreview(filePath);
    }

    modal.classList.remove('hidden');
    modal.style.display = 'flex';
}

function noPreview(filePath) {
    return `<div style="text-align:center;padding:40px;color:#9ca3af">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin:0 auto 16px;display:block"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
        <p style="font-weight:700;margin-bottom:8px">Preview tidak tersedia</p>
        <a href="${filePath}" target="_blank" style="color:#10b981;font-weight:700;text-decoration:none">Buka di tab baru →</a>
    </div>`;
}

function escHtml(text) {
    return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function closeModal() {
    const modal = document.getElementById('viewModal');
    modal.classList.add('hidden');
    modal.style.display = 'none';
    document.getElementById('documentViewer').innerHTML = '';
}

function confirmDelete(id, name) {
    if (confirm('Apakah Anda yakin ingin menghapus dokumen "' + name + '"? File akan dihapus permanen dari server.')) {
        document.getElementById('deleteDocId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
