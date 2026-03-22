<?php
// serve_file.php — Securely serves uploaded files with correct Content-Type headers
require_once 'config.php'; // handles session_start() and $pdo

if (!isLoggedIn()) {
    http_response_code(403);
    exit('Forbidden');
}

$user_id = $_SESSION['user_id'];
$file    = isset($_GET['file']) ? $_GET['file'] : '';

// Security: convert slashes, strip leading slash, no traversal
$file = str_replace('\\', '/', $file);
$file = ltrim($file, '/');

// Must start with uploads/
if (strpos($file, 'uploads/') !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

// Prevent directory traversal
if (strpos($file, '..') !== false) {
    http_response_code(403);
    exit('Forbidden');
}

// Build absolute path
$abs_path = realpath(__DIR__ . '/' . $file);

// Ensure path is inside uploads/
$uploads_dir = realpath(__DIR__ . '/uploads');
if (!$abs_path || !$uploads_dir || strpos($abs_path, $uploads_dir) !== 0) {
    http_response_code(404);
    exit('Not Found');
}

// Validate user owns this file (match by basename in DB)
$basename = basename($abs_path);
$stmt = $pdo->prepare("SELECT id FROM documents WHERE user_id = ? AND file_path LIKE ?");
$stmt->execute([$user_id, '%' . $basename]);
if (!$stmt->fetch()) {
    http_response_code(403);
    exit('Forbidden');
}

if (!file_exists($abs_path)) {
    http_response_code(404);
    exit('File Not Found');
}

// Clear any output buffers from config.php
while (ob_get_level()) ob_end_clean();

// Output the file
$mime = mime_content_type($abs_path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs_path));
header('Content-Disposition: inline; filename="' . rawurlencode(basename($abs_path)) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Frame-Options: SAMEORIGIN'); // override config.php's DENY so iframe/object works
readfile($abs_path);
exit;
