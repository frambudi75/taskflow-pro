<?php
// config.php
// Base URL and Routing Helper
function baseUrl($path = '') {
    // Determine the base path (e.g., /taskflow/)
    $script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $script_dir . '/' . ltrim($path, '/');
}

function route($page) {
    // Map pages to clean URLs defined in .htaccess
    $map = [
        'login' => 'auth',
        'dashboard' => 'dashboard',
        'calendar' => 'calendar',
        'notes' => 'notes',
        'documents' => 'dokumen',
        'projects' => 'kanban',
        'activities' => 'activities',
        'settings' => 'settings',
        'profile' => 'profile',
        'reset-password' => 'reset-password',
        'logout' => 'logout'
    ];
    return baseUrl($map[$page] ?? $page);
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
function sendTelegramNotif($chat_id, $message) {
    $token = getSetting('telegram_bot_token');
    if (empty($token) || empty($chat_id)) return false;

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    return $result !== false;
}

?>
