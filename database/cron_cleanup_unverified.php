<?php
/**
 * SmartLaundry AI - Cron Job: Cleanup Unverified Registrations Past 24 Hours
 * 
 * Removes user registrations that have remained unverified for > 24 hours.
 * 
 * Usage: php database/cron_cleanup_unverified.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('VIEW_PATH', APP_PATH . '/Views');

require_once ROOT_PATH . '/config/env.php';
load_env(ROOT_PATH . '/.env');
require_once ROOT_PATH . '/config/app.php';

// PSR-4 Autoload
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Database;
use App\Models\Setting;
use App\Models\OtpCode;

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Starting Unverified Accounts & OTP Cleanup Worker...\n";

try {
    $db = Database::getConnection();
    $cutoff = date('Y-m-d H:i:s', time() - 86400); // 24 hours ago

    // 1. Find unverified customer accounts created > 24 hours ago
    $findSql = "SELECT id, email, phone FROM `users` 
                WHERE `is_active` = 0 
                  AND `email_verified_at` IS NULL 
                  AND `phone_verified_at` IS NULL 
                  AND `created_at` < :cutoff";
    $findStmt = $db->prepare($findSql);
    $findStmt->execute(['cutoff' => $cutoff]);
    $staleUsers = $findStmt->fetchAll(\PDO::FETCH_ASSOC);

    $deletedUsersCount = 0;
    if (!empty($staleUsers)) {
        $userIds = array_column($staleUsers, 'id');
        $inClause = implode(',', array_map('intval', $userIds));
        $delStmt = $db->query("DELETE FROM `users` WHERE `id` IN ({$inClause})");
        $deletedUsersCount = $delStmt->rowCount();
    }

    // 2. Clean up old OTP records older than 24 hours
    $otpModel = new OtpCode();
    $deletedOtpsCount = $otpModel->cleanupExpired(24);

    $duration = round(microtime(true) - $startTime, 4);
    $summary = "Cleanup: {$deletedUsersCount} unverified account(s) deleted, {$deletedOtpsCount} expired OTP code(s) purged.";
    Setting::recordCronHeartbeat('cleanup_unverified', 'SUCCESS', $summary, $duration);

    echo "[{$timestamp}] Cleanup completed in {$duration}s: {$deletedUsersCount} account(s) deleted, {$deletedOtpsCount} OTP(s) purged.\n";
} catch (\Throwable $th) {
    $duration = round(microtime(true) - $startTime, 4);
    Setting::recordCronHeartbeat('cleanup_unverified', 'ERROR', $th->getMessage(), $duration);
    echo "[{$timestamp}] ERROR in unverified cleanup worker: " . $th->getMessage() . "\n";
    exit(1);
}

