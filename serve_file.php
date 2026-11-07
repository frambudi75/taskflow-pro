<?php
// serve_file.php: Securely serves uploaded files with permission checks, compression integrity, and proper download headers
require_once 'config.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$user_id = (int)$_SESSION['user_id'];
$file    = isset($_GET['file']) ? trim($_GET['file']) : '';

if (empty($file)) {
    http_response_code(400);
    exit('Bad Request: Parameter file tidak boleh kosong.');
}

// Security: normalize slashes, strip leading slash, prevent traversal
$file = str_replace('\\', '/', $file);
$file = ltrim($file, '/');

if (strpos($file, 'uploads/') !== 0 || strpos($file, '..') !== false) {
    http_response_code(403);
    exit('Forbidden');
}

// Build absolute path
$abs_path = realpath(__DIR__ . '/' . $file);
$uploads_dir = realpath(__DIR__ . '/uploads');

if (!$abs_path || !$uploads_dir || strpos($abs_path, $uploads_dir) !== 0 || !file_exists($abs_path)) {
    http_response_code(404);
    exit('File Not Found');
}

$basename = basename($abs_path);
$isAllowed = false;
$originalName = $basename;

// 1. Public authenticated assets: Settings Logo, Avatars
if (strpos($file, 'uploads/settings/') === 0 || strpos($file, 'uploads/avatars/') === 0) {
    $isAllowed = true;
} else {
    // Check Document Ownership or Shared Access
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.file_type 
        FROM documents d 
        LEFT JOIN document_shares ds ON ds.document_id = d.id AND ds.shared_with_user_id = ?
        WHERE (d.user_id = ? OR ds.id IS NOT NULL" . (isAdmin() ? " OR 1=1" : "") . ") 
        AND d.file_path LIKE ?
    ");
    $stmt->execute([$user_id, $user_id, '%' . $basename]);
    $doc = $stmt->fetch();

    if ($doc) {
        $isAllowed = true;
        if (!empty($doc['name'])) {
            $ext = pathinfo($abs_path, PATHINFO_EXTENSION);
            $originalName = $doc['name'];
            if ($ext && !str_ends_with(strtolower($originalName), '.' . strtolower($ext))) {
                $originalName .= '.' . $ext;
            }
        }
    }
}

if (!$isAllowed) {
    http_response_code(403);
    exit('Forbidden: Anda tidak memiliki hak akses untuk file ini.');
}

// Clear any output buffers to prevent corruption during binary file downloads
while (ob_get_level()) {
    ob_end_clean();
}

$mime = mime_content_type($abs_path) ?: 'application/octet-stream';
$filesize = filesize($abs_path);
$isDownload = isset($_GET['download']) && $_GET['download'] === '1';
$disposition = $isDownload ? 'attachment' : 'inline';

// Send strict HTTP headers for intact binary delivery
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Length: ' . $filesize);
header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($originalName) . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, must-revalidate, max-age=3600');
header('Pragma: public');
header('Expires: 0');
header('X-Frame-Options: SAMEORIGIN');

readfile($abs_path);
exit;
