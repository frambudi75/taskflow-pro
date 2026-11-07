<?php
// share.php - Public External Document Sharing with Expiration
require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
$app_name = getSetting('app_name', 'TaskFlow Pro');
$company_name = getSetting('company_name', $app_name);
$app_logo_info = getAppLogoInfo();
$company_logo = $app_logo_info['url'];

// Fetch share record
$share = null;
$error_state = null;

if (empty($token) || strlen($token) < 16) {
    $error_state = 'invalid';
} else {
    $stmt = $pdo->prepare("SELECT s.*, d.name as doc_name, d.file_path, d.file_type, d.file_size, d.tags, d.upload_date,
                                  u.display_name as owner_name, u.email as owner_email
                           FROM document_public_shares s
                           JOIN documents d ON s.document_id = d.id
                           JOIN users u ON s.created_by = u.id
                           WHERE s.share_token = ?");
    $stmt->execute([$token]);
    $share = $stmt->fetch();

    if (!$share) {
        $error_state = 'not_found';
    } elseif ((int)$share['is_active'] !== 1) {
        $error_state = 'revoked';
    } elseif (strtotime($share['expires_at']) <= time()) {
        $error_state = 'expired';
    }
}

// Handle Direct Download
if ($share && !$error_state && isset($_GET['download']) && $_GET['download'] === '1') {
    $filePath = __DIR__ . '/' . $share['file_path'];
    if (file_exists($filePath) && is_readable($filePath)) {
        // Increment downloads
        $upd = $pdo->prepare("UPDATE document_public_shares SET downloads_count = downloads_count + 1 WHERE id = ?");
        $upd->execute([$share['id']]);

        // Construct safe filename
        $ext = strtolower(pathinfo($share['file_path'], PATHINFO_EXTENSION));
        $docName = $share['doc_name'];
        if ($ext && !str_ends_with(strtolower($docName), '.' . $ext)) {
            $docName .= '.' . $ext;
        }
        $docName = preg_replace('/[^a-zA-Z0-9._ -]/', '_', $docName);

        // Send headers
        header('Content-Description: File Transfer');
        header('Content-Type: ' . ($share['file_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $docName . '"');
        header('Expires: 0');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        while (ob_get_level()) {
            ob_end_clean();
        }
        flush();
        readfile($filePath);
        exit;
    } else {
        $error_state = 'file_missing';
    }
}

// Increment View Count (once per session per share)
if ($share && !$error_state) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $viewKey = 'viewed_share_' . $share['id'];
    if (empty($_SESSION[$viewKey])) {
        $upd = $pdo->prepare("UPDATE document_public_shares SET views_count = views_count + 1 WHERE id = ?");
        $upd->execute([$share['id']]);
        $_SESSION[$viewKey] = time();
    }
}

// Calculate remaining time
$remainingText = '';
$isNearExpiry = false;
if ($share && !$error_state) {
    $diffSec = strtotime($share['expires_at']) - time();
    if ($diffSec > 0) {
        $days = floor($diffSec / 86400);
        $hours = floor(($diffSec % 86400) / 3600);
        $minutes = floor(($diffSec % 3600) / 60);

        if ($days > 0) {
            $remainingText = $days . ' hari ' . $hours . ' jam lagi';
        } elseif ($hours > 0) {
            $remainingText = $hours . ' jam ' . $minutes . ' menit lagi';
            if ($hours < 2) $isNearExpiry = true;
        } else {
            $remainingText = max(1, $minutes) . ' menit lagi';
            $isNearExpiry = true;
        }
    }
}

function formatShareBytes($bytes, $precision = 1) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

$isImage = $share && in_array(strtolower($share['file_type']), ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
$isPdf   = $share && strtolower($share['file_type']) === 'application/pdf';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $share ? htmlspecialchars($share['doc_name']) . ' - ' : ''; ?><?php echo htmlspecialchars($app_name); ?></title>
    <link rel="icon" type="<?php echo $app_logo_info['mime']; ?>" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($app_logo_info['url']); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Ambient Glow Top -->
    <div class="pointer-events-none fixed top-0 left-1/2 -translate-x-1/2 w-full max-w-4xl h-80 bg-indigo-600/15 blur-[120px] -z-10"></div>

    <!-- Header Bar -->
    <header class="w-full border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <?php if ($app_logo_info['is_custom']): ?>
                <img src="<?php echo htmlspecialchars($app_logo_info['url']); ?>" alt="Logo" class="h-8 max-w-[140px] object-contain">
            <?php else: ?>
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white font-black text-base shadow-md shadow-indigo-600/30">
                    TF
                </div>
            <?php endif; ?>
            <div>
                <div class="text-sm font-extrabold text-white tracking-tight"><?php echo htmlspecialchars($app_name); ?></div>
                <div class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider"><?php echo htmlspecialchars($company_name); ?></div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-800/80 border border-slate-700/80 text-[11px] font-bold text-slate-300">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Tautan Berbagi Aman
            </span>
        </div>
    </header>

    <!-- Main Container -->
    <main class="w-full max-w-2xl mx-auto px-4 py-8 md:py-12 flex-1 flex flex-col justify-center">

        <?php if ($error_state): ?>
            <!-- Error State Cards -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 md:p-10 shadow-2xl text-center relative overflow-hidden">
                <div class="w-16 h-16 rounded-2xl mx-auto mb-5 flex items-center justify-center <?php echo $error_state === 'expired' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'; ?>">
                    <?php if ($error_state === 'expired'): ?>
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?php else: ?>
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <?php endif; ?>
                </div>

                <h1 class="text-xl md:text-2xl font-black text-white mb-2">
                    <?php 
                    if ($error_state === 'expired') {
                        echo 'Tautan Telah Kadaluwarsa';
                    } elseif ($error_state === 'revoked') {
                        echo 'Tautan Telah Dicabut';
                    } elseif ($error_state === 'file_missing') {
                        echo 'File Tidak Ditemukan di Server';
                    } else {
                        echo 'Tautan Tidak Valid';
                    }
                    ?>
                </h1>

                <p class="text-slate-400 text-sm max-w-md mx-auto leading-relaxed mb-6 font-medium">
                    <?php 
                    if ($error_state === 'expired') {
                        echo 'Masa berlaku tautan unduhan untuk dokumen ini telah berakhir pada ' . date('d F Y, H:i', strtotime($share['expires_at'])) . ' WIB. Silakan hubungi pengunggah dokumen untuk membuat tautan baru.';
                    } elseif ($error_state === 'revoked') {
                        echo 'Pemilik dokumen telah menonaktifkan atau mencabut hak akses publik untuk tautan ini.';
                    } elseif ($error_state === 'file_missing') {
                        echo 'Berkas fisik dokumen ini telah dipindahkan atau dihapus oleh sistem.';
                    } else {
                        echo 'Tautan dokumen yang Anda tuju tidak ditemukan atau format token keamanan tidak sesuai.';
                    }
                    ?>
                </p>

                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-semibold">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Dilindungi oleh Sistem Enkripsi TaskFlow
                </div>
            </div>

        <?php else: ?>
            <!-- Valid Share Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 md:p-9 shadow-2xl relative overflow-hidden">
                <!-- Expiry Alert Banner -->
                <div class="mb-6 p-4 rounded-2xl flex items-center justify-between gap-4 <?php echo $isNearExpiry ? 'bg-amber-500/10 border border-amber-500/30 text-amber-300' : 'bg-indigo-950/60 border border-indigo-500/30 text-indigo-200'; ?>">
                    <div class="flex items-center gap-3">
                        <span class="w-2.5 h-2.5 rounded-full <?php echo $isNearExpiry ? 'bg-amber-400 animate-ping' : 'bg-indigo-400 animate-pulse'; ?>"></span>
                        <div>
                            <div class="text-[11px] font-extrabold uppercase tracking-wider">Masa Berlaku Tautan</div>
                            <div class="text-xs font-medium">Berlaku hingga <strong class="font-bold text-white"><?php echo date('d M Y, H:i', strtotime($share['expires_at'])); ?> WIB</strong></div>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-wider <?php echo $isNearExpiry ? 'bg-amber-400/20 text-amber-200' : 'bg-indigo-500/20 text-indigo-300'; ?>">
                        <?php echo $remainingText; ?>
                    </span>
                </div>

                <!-- Document Info Header -->
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-md <?php 
                        if ($isPdf) echo 'bg-rose-500/10 text-rose-400 border border-rose-500/30';
                        elseif ($isImage) echo 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30';
                        else echo 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30';
                    ?>">
                        <?php if ($isPdf): ?>
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <?php elseif ($isImage): ?>
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <?php else: ?>
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-lg md:text-xl font-black text-white leading-tight mb-1 truncate" title="<?php echo htmlspecialchars($share['doc_name']); ?>">
                            <?php echo htmlspecialchars($share['doc_name']); ?>
                        </h1>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400 font-semibold">
                            <span>Ukuran: <strong class="text-slate-200"><?php echo formatShareBytes($share['file_size']); ?></strong></span>
                            <span>•</span>
                            <span>Dibagikan oleh: <strong class="text-slate-200"><?php echo htmlspecialchars($share['owner_name']); ?></strong></span>
                        </div>
                    </div>
                </div>

                <!-- Preview Box if Image -->
                <?php if ($isImage && file_exists(__DIR__ . '/' . $share['file_path'])): ?>
                <div class="mb-6 rounded-2xl overflow-hidden border border-slate-800 bg-slate-950/70 p-3 flex items-center justify-center">
                    <img src="<?php echo htmlspecialchars($share['file_path']); ?>" alt="<?php echo htmlspecialchars($share['doc_name']); ?>"
                         class="max-h-72 w-auto object-contain rounded-xl shadow-lg">
                </div>
                <?php endif; ?>

                <!-- Download Primary Action -->
                <div class="space-y-3 pt-2">
                    <a href="share.php?token=<?php echo urlencode($token); ?>&download=1"
                       class="w-full py-4 px-6 rounded-2xl bg-indigo-600 hover:bg-indigo-500 active:scale-[0.99] text-white font-extrabold text-sm md:text-base shadow-xl shadow-indigo-600/30 transition-all flex items-center justify-center gap-3">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Unduh Dokumen Sekarang
                    </a>

                    <div class="flex items-center justify-between text-[11px] text-slate-400 px-2 font-medium">
                        <span class="flex items-center gap-1.5">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            Dilihat: <?php echo (int)$share['views_count']; ?> kali
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Diunduh: <?php echo (int)$share['downloads_count']; ?> kali
                        </span>
                    </div>
                </div>

                <!-- Tags / Description if any -->
                <?php if (!empty($share['tags'])): ?>
                <div class="mt-6 pt-5 border-t border-slate-800/80 flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Tag / Keterangan:</span>
                    <?php foreach(explode(',', $share['tags']) as $t): $t = trim($t); if ($t): ?>
                        <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-lg bg-slate-800 text-slate-300 border border-slate-700/60"><?php echo htmlspecialchars($t); ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="w-full text-center py-6 text-xs text-slate-500 font-medium">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($app_name); ?>. Tautan berbagi eksternal dilindungi hak akses &amp; batasan waktu.
    </footer>

</body>
</html>

