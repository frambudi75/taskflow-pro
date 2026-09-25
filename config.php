<?php
date_default_timezone_set('Asia/Jakarta');

// PHP 8.0 Polyfills for backward compatibility (PHP 7.4)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

// config.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Base URL and Routing Helper
function baseUrl($path = '') {
    // 1. Try to get from database settings first
    $appUrl = getSetting('app_url');
    if (!empty($appUrl)) {
        return rtrim($appUrl, '/') . '/' . ltrim($path, '/');
    }

    // 2. Fallback to auto-detection (Works for Web, might fail for CLI)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine the base folder (e.g., /taskflow/)
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    
    // On CLI, SCRIPT_NAME might be absolute filesystem path. Try to cleanup.
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        // If it's a server path, we can't reliably guess the URL without a setting.
        // We'll just return the relative path as a fallback.
        return '/' . ltrim($path, '/');
    }
    
    return $protocol . $host . $script_dir . '/' . ltrim($path, '/');
}

function route($page) {
    // Map pages to standard PHP URLs for broad compatibility
    $map = [
        'login' => 'login.php',
        'dashboard' => 'index.php?page=dashboard',
        'calendar' => 'index.php?page=calendar',
        'notes' => 'index.php?page=notes',
        'documents' => 'index.php?page=documents',
        'projects' => 'index.php?page=projects',
        'reports' => 'index.php?page=reports',
        'activities' => 'index.php?page=activities',
        'settings' => 'index.php?page=settings',
        'profile' => 'index.php?page=profile',
        'reset-password' => 'index.php?page=reset-password',
        'admin' => 'index.php?page=admin',
        'backup' => 'index.php?page=backup',
        'cache' => 'index.php?page=cache',
        'notif-harian' => 'notif_harian.php',

        'chat' => 'index.php?page=chat',
        'logout' => 'index.php?logout=1'
    ];
    return baseUrl($map[$page] ?? "index.php?page=" . $page);
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'taskflow_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
 // Sesuaikan dengan password database Anda
$charset = 'utf8mb4';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
     die("Koneksi Database Gagal: " . $e->getMessage());
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}

function countUnreadMessages($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

session_start();

function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
}

// Ensure database tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        shared_with_user_id INT NOT NULL,
        shared_by_user_id INT NOT NULL,
        permission VARCHAR(20) DEFAULT 'view',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_doc_share (document_id, shared_with_user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        status VARCHAR(50) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_projects (user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        report_date DATE NOT NULL,
        category VARCHAR(50) DEFAULT 'Umum',
        start_time TIME NULL,
        end_time TIME NULL,
        supervisor_name VARCHAR(100) NULL,
        project_id INT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        photo_path TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_report_date (user_id, report_date)
    )");

    // Ensure photo_path is TEXT and new columns exist
    try {
        $pdo->exec("ALTER TABLE daily_reports MODIFY COLUMN photo_path TEXT NULL");
    } catch (\Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE daily_reports ADD COLUMN category VARCHAR(50) DEFAULT 'Umum' AFTER report_date");
    } catch (\Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE daily_reports ADD COLUMN start_time TIME NULL AFTER category");
    } catch (\Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE daily_reports ADD COLUMN end_time TIME NULL AFTER start_time");
    } catch (\Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE daily_reports ADD COLUMN supervisor_name VARCHAR(100) NULL AFTER end_time");
    } catch (\Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE daily_reports ADD COLUMN project_id INT NULL AFTER supervisor_name");
    } catch (\Throwable $e) {}
} catch (\Throwable $e) {}

/**
 * Compresses and saves uploaded files without quality loss.
 * Images (JPEG, PNG, WebP) are optimized via GD if available.
 * Documents (PDF, DOCX, XLSX, TXT, ZIP) are securely copied in binary mode.
 */
function optimizeAndSaveFile($tmpPath, $targetPath, $mimeType, $maxDimension = 2560, $jpegQuality = 88) {
    $dir = dirname($targetPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $saved = false;

    // Check if image and GD available
    if (extension_loaded('gd') && function_exists('imagecreatefromstring')) {
        $imageMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
        if (in_array(strtolower($mimeType), $imageMimes, true)) {
            $rawContent = @file_get_contents($tmpPath);
            if ($rawContent !== false) {
                $srcImg = @imagecreatefromstring($rawContent);
                if ($srcImg !== false) {
                    $origWidth = imagesx($srcImg);
                    $origHeight = imagesy($srcImg);

                    // Fix EXIF orientation for JPEGs
                    if (function_exists('exif_read_data') && in_array(strtolower($mimeType), ['image/jpeg', 'image/jpg'])) {
                        try {
                            $exif = @exif_read_data($tmpPath);
                            if (!empty($exif['Orientation'])) {
                                switch ($exif['Orientation']) {
                                    case 3:
                                        $srcImg = imagerotate($srcImg, 180, 0);
                                        break;
                                    case 6:
                                        $srcImg = imagerotate($srcImg, -90, 0);
                                        break;
                                    case 8:
                                        $srcImg = imagerotate($srcImg, 90, 0);
                                        break;
                                }
                                $origWidth = imagesx($srcImg);
                                $origHeight = imagesy($srcImg);
                            }
                        } catch (\Throwable $e) {}
                    }

                    // Resize if larger than maxDimension while preserving aspect ratio
                    if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
                        $ratio = min($maxDimension / $origWidth, $maxDimension / $origHeight);
                        $newWidth = (int)round($origWidth * $ratio);
                        $newHeight = (int)round($origHeight * $ratio);

                        $destImg = imagecreatetruecolor($newWidth, $newHeight);
                        if (in_array(strtolower($mimeType), ['image/png', 'image/webp', 'image/gif'])) {
                            imagealphablending($destImg, false);
                            imagesavealpha($destImg, true);
                            $transparent = imagecolorallocatealpha($destImg, 255, 255, 255, 127);
                            imagefilledrectangle($destImg, 0, 0, $newWidth, $newHeight, $transparent);
                        }
                        imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                        imagedestroy($srcImg);
                        $srcImg = $destImg;
                    }

                    // Save optimized format
                    if (in_array(strtolower($mimeType), ['image/jpeg', 'image/jpg'])) {
                        $saved = @imagejpeg($srcImg, $targetPath, $jpegQuality);
                    } elseif (strtolower($mimeType) === 'image/png') {
                        imagealphablending($srcImg, false);
                        imagesavealpha($srcImg, true);
                        $saved = @imagepng($srcImg, $targetPath, 8);
                    } elseif (strtolower($mimeType) === 'image/webp' && function_exists('imagewebp')) {
                        imagealphablending($srcImg, false);
                        imagesavealpha($srcImg, true);
                        $saved = @imagewebp($srcImg, $targetPath, 85);
                    } elseif (strtolower($mimeType) === 'image/gif') {
                        $saved = @imagegif($srcImg, $targetPath);
                    }

                    imagedestroy($srcImg);
                }
            }
        }
    }

    // Fallback if not an image, GD not available, or processing failed
    if (!$saved) {
        if (is_uploaded_file($tmpPath)) {
            $saved = @move_uploaded_file($tmpPath, $targetPath);
        } else {
            $saved = @copy($tmpPath, $targetPath);
        }
    }

    if ($saved && file_exists($targetPath)) {
        return [
            'success' => true,
            'path'    => $targetPath,
            'size'    => filesize($targetPath),
            'mime'    => $mimeType
        ];
    }

    return ['success' => false, 'path' => null, 'size' => 0, 'mime' => $mimeType];
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'administrator';
}

function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// File-based caching with TTL support
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_ENABLED', true);
define('CACHE_TTL_DEFAULT', 3600); // 1 hour default

// Initialize cache directory
if (!is_dir(CACHE_DIR) && CACHE_ENABLED) {
    @mkdir(CACHE_DIR, 0755, true);
}

/**
 * Get value from cache
 * @param string $key Cache key
 * @return mixed|null Cached value or null if not cached/expired
 */
function getCache($key) {
    if (!CACHE_ENABLED) return null;
    
    $cache_file = CACHE_DIR . '/' . md5($key) . '.cache';
    
    if (!file_exists($cache_file)) {
        return null;
    }
    
    $data = unserialize(file_get_contents($cache_file));
    
    if (!$data || (isset($data['expires']) && time() > $data['expires'])) {
        @unlink($cache_file);
        return null;
    }
    
    return $data['value'] ?? null;
}

/**
 * Set value in cache
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds
 */
function setCache($key, $value, $ttl = CACHE_TTL_DEFAULT) {
    if (!CACHE_ENABLED) return false;
    
    $cache_file = CACHE_DIR . '/' . md5($key) . '.cache';
    
    $data = [
        'value' => $value,
        'expires' => time() + $ttl,
        'created' => time()
    ];
    
    return file_put_contents($cache_file, serialize($data), LOCK_EX) !== false;
}

/**
 * Clear specific cache key
 * @param string $key Cache key
 */
function clearCache($key = null) {
    if (!CACHE_ENABLED || !is_dir(CACHE_DIR)) return false;
    
    if ($key === null) {
        $files = glob(CACHE_DIR . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    } else {
        $cache_file = CACHE_DIR . '/' . md5($key) . '.cache';
        return @unlink($cache_file);
    }
}

/**
 * Clear cache by pattern (prefix)
 * @param string $prefix Cache key prefix
 */
function clearCacheByPattern($prefix) {
    if (!CACHE_ENABLED || !is_dir(CACHE_DIR)) return false;
    
    $files = glob(CACHE_DIR . '/*.cache');
    $cleared = 0;
    
    foreach ($files as $file) {
        $content = unserialize(file_get_contents($file));
        if (isset($content['value']) && is_array($content['value'])) {
            @unlink($file);
            $cleared++;
        }
    }
    
    return $cleared;
}

/**
 * Generate a lightweight SVG avatar as a data URI.
 * Vibrant initials-based avatar (Google Workspace style).
 */
function getAvatarDataUri($name, $size = 48) {
    $name = trim($name);
    $words = preg_split('/\s+/', $name);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        if ($word === '') continue;
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    if ($initials === '') $initials = '?';

    $palette = ['#3b82f6','#8b5cf6','#ec4899','#ef4444','#f97316',
                '#10b981','#06b6d4','#6366f1','#14b8a6','#f59e0b'];
    $bg = $palette[abs(crc32($name)) % count($palette)];
    $fs = round($size * 0.40);
    $rx = round($size * 0.22);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'">'
         . '<rect width="'.$size.'" height="'.$size.'" rx="'.$rx.'" ry="'.$rx.'" fill="'.$bg.'"/>'
         . '<text x="50%" y="50%" dominant-baseline="central" text-anchor="middle"'
         .   ' font-family="Inter,system-ui,Arial,sans-serif"'
         .   ' font-size="'.$fs.'" font-weight="700" fill="#ffffff">'
         . htmlspecialchars($initials)
         . '</text>'
         . '</svg>';

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

/**
 * Log a user activity to the database.
 */
function logActivity($action, $target_type = null, $target_id = null, $metadata = null) {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return false;
    
    $stmt = $pdo->prepare("INSERT INTO activities (user_id, action, target_type, target_id, metadata) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([
        $_SESSION['user_id'],
        $action,
        $target_type,
        $target_id,
        is_array($metadata) ? json_encode($metadata) : $metadata
    ]);
}

/**
 * Get system setting by key
 */
function getSetting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

/**
 * Send a notification via Telegram Bot API.
 */
/**
 * Send a notification via Telegram Bot API using cURL.
 */
function sendTelegramNotif($chat_id, $message) {
    if (empty($chat_id)) return false;
    
    $token = getSetting('telegram_bot_token');
    if (empty($token)) {
        error_log("Telegram Error: Bot Token is empty.");
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev compatibility
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result === false || $http_code !== 200) {
        $log_msg = "Telegram Error (HTTP $http_code): " . ($error ?: $result);
        error_log($log_msg);
        return false;
    }

    return true;
}

/**
 * Send a notification via Email using PHPMailer and stored SMTP settings.
 */
function sendEmailNotif($to, $subject, $message) {
    if (empty($to)) return false;

    // Load autoload if exists
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log("Email Error: PHPMailer autoload not found. Falling back to mail().");
        return @mail($to, $subject, nl2br($message));
    }
    require_once $autoload;

    $host    = getSetting('smtp_host');
    $user    = getSetting('smtp_user');
    $pass    = getSetting('smtp_pass');
    $port    = getSetting('smtp_port', '587');
    $from    = getSetting('smtp_from', 'admin@taskflow.local');
    $appName = getSetting('app_name', 'TaskFlow Pro');

    // If host is empty, fall back to mail()
    if (empty($host)) {
        $headers = "From: $from\r\nContent-Type: text/html; charset=UTF-8\r\n";
        return @mail($to, $subject, nl2br($message), $headers);
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->CharSet    = 'UTF-8';
        $mail->Encoding   = 'base64';
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = !empty($user);
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = ($port == '465') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;

        // Recipients
        $mail->setFrom($from, $appName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Premium HTML Template
        $htmlBody = "
        <html>
        <body style='margin:0;padding:0;background-color:#f8fafc;font-family:sans-serif;'>
            <div style='max-width:600px;margin:40px auto;background-color:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.05);border:1px solid #e2e8f0;'>
                <div style='background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);padding:40px;text-align:center;'>
                    <h1 style='color:#ffffff;margin:0;font-size:24px;font-weight:900;letter-spacing:-0.5px;'>$appName</h1>
                    <p style='color:#93c5fd;margin:10px 0 0 0;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>Sistem Notifikasi Pintar</p>
                </div>
                <div style='padding:40px;'>
                    <div style='margin-bottom:30px;'>
                        <h2 style='color:#0f172a;font-size:18px;font-weight:800;margin:0 0 15px 0;'>$subject</h2>
                        <div style='height:2px;width:40px;background-color:#3b82f6;'></div>
                    </div>
                    <div style='color:#475569;font-size:15px;line-height:1.6;font-weight:400;'>
                        " . nl2br($message) . "
                    </div>
                    <div style='margin-top:40px;padding-top:30px;border-top:1px solid #f1f5f9;text-align:center;'>
                        <p style='color:#94a3b8;font-size:11px;font-weight:500;margin:0;'>Email ini dikirim otomatis oleh sistem TaskFlow Pro. Mohon tidak membalas email ini.</p>
                        <p style='color:#94a3b8;font-size:11px;font-weight:500;margin:5px 0 0 0;'>&copy; 2026 " . $appName . ". Terus Produktif!</p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($message);

        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

?>
