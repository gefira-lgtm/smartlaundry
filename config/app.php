<?php
/**
 * Application Global Configuration & Helpers
 */

if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Application Constants
if (!defined('APP_NAME')) define('APP_NAME', 'SmartLaundry Marketplace');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');

// Calculate Base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/RPL2/index.php';
$baseDir = dirname($scriptName);
$baseDir = str_replace('\\', '/', $baseDir);
if ($baseDir === '/') {
    $baseDir = '';
}
if (!defined('BASE_URL')) define('BASE_URL', rtrim($protocol . $host . $baseDir, '/'));

// Directory Paths
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__));
if (!defined('APP_PATH')) define('APP_PATH', ROOT_PATH . '/app');
if (!defined('PUBLIC_PATH')) define('PUBLIC_PATH', ROOT_PATH . '/public');
if (!defined('VIEW_PATH')) define('VIEW_PATH', APP_PATH . '/Views');

// Register autoloader for App\ namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Application Name Helper (reads dynamic setting with fallback)
 */
function app_name(): string {
    if (class_exists('App\Models\Setting')) {
        return (string)\App\Models\Setting::get('app_name', defined('APP_NAME') ? APP_NAME : 'SmartLaundry');
    }
    return defined('APP_NAME') ? APP_NAME : 'SmartLaundry';
}

/**
 * Application Logo Helper
 */
function app_logo(): ?string {
    if (class_exists('App\Models\Setting')) {
        $logo = \App\Models\Setting::get('app_logo', '');
        return !empty($logo) ? (string)$logo : null;
    }
    return null;
}

// Session lifetime enforcement if authenticated
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user']['id'])) {
    $lifetime = 120;
    if (class_exists('App\Models\Setting')) {
        try {
            $lifetime = (int)\App\Models\Setting::get('session_lifetime_minutes', 120);
        } catch (\Throwable $e) {}
    }
    if (isset($_SESSION['_last_activity']) && (time() - $_SESSION['_last_activity']) > ($lifetime * 60)) {
        session_unset();
        session_destroy();
        if (php_sapi_name() !== 'cli') {
            header('Location: ' . url('login?expired=1'));
            exit;
        }
    }
    $_SESSION['_last_activity'] = time();
}

/**
 * URL Helper
 */
function url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

/**
 * Asset Helper
 */
function asset($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . '/public/' . $path;
}

/**
 * Redirect Helper
 */
function redirect($path = '', $statusCode = 302) {
    if (php_sapi_name() !== 'cli') {
        header('Location: ' . url($path), true, $statusCode);
        exit;
    }
}

/**
 * Flash Message Helpers
 */
function set_flash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $msg = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $msg;
    }
    return null;
}

function has_flash($type) {
    return isset($_SESSION['flash'][$type]);
}

/**
 * CSRF Protection Helpers
 */
function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function generate_csrf_token(): string {
    return csrf_token();
}

function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

/**
 * Auth Helpers
 */
function auth_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_authenticated(): bool {
    return !empty($_SESSION['user']['id']);
}

function user_role(): ?string {
    return $_SESSION['user']['role'] ?? null;
}

function get_dashboard_url_by_role(?string $role): string {
    switch ($role) {
        case 'owner_platform':
        case 'admin_owner':
            return 'dashboard/platform';
        case 'owner_toko':
        case 'admin_toko':
            return 'dashboard/store';
        case 'kurir':
            return 'dashboard/courier';
        case 'customer':
        default:
            return 'dashboard/customer';
    }
}

/**
 * Currency Formatter
 */
function format_rupiah($number) {
    return 'Rp ' . number_format((float)$number, 0, ',', '.');
}

/**
 * Date Formatter
 */
function format_datetime_id($datetime) {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = date('d', $timestamp);
    $m = (int)date('m', $timestamp);
    $y = date('Y', $timestamp);
    $h = date('H:i', $timestamp);
    return "$d {$bulan[$m]} $y, $h WIB";
}

/**
 * Clean string for output
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * Normalize Indonesian phone numbers to standard 628xxxxxxxx format
 */
function normalize_phone_number(string $phone): string {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (empty($cleaned)) {
        return '';
    }
    // If starts with 08..., convert to 628...
    if (strpos($cleaned, '0') === 0) {
        $cleaned = '62' . substr($cleaned, 1);
    } elseif (strpos($cleaned, '8') === 0) {
        $cleaned = '62' . $cleaned;
    }
    return $cleaned;
}
