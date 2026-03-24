<?php
date_default_timezone_set('Asia/Jakarta');
// config.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Base URL and Routing Helper
function baseUrl($path = '') {
    // Determine the base path (e.g., /taskflow/)
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $script_dir . '/' . ltrim($path, '/');
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
        'activities' => 'index.php?page=activities',
        'settings' => 'index.php?page=settings',
        'profile' => 'index.php?page=profile',
        'reset-password' => 'index.php?page=reset-password',
        'admin' => 'index.php?page=admin',
        'backup' => 'index.php?page=backup',
        'cache' => 'index.php?page=cache',
        'notif-harian' => 'notif_harian.php',
        'test-mail' => 'test_mail.php',
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

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

session_start();

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

// ============ CACHING SYSTEM ============
// Simple file-based caching with TTL support

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
        // Clear all cache
        $files = glob(CACHE_DIR . '/*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    } else {
        // Clear specific key
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

// ============ END CACHING SYSTEM ============

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
