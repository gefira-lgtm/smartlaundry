<?php
/**
 * SmartLaundry — Cron Job: Auto-expire overdue QRIS payments
 *
 * Usage:
 *   php database/cron_check_expired_payments.php
 *
 * Recommended cron (every 5 minutes):
 *   * / 5 * * * * /usr/bin/php /var/www/html/RPL2/database/cron_check_expired_payments.php >> /var/log/smartlaundry_cron.log 2>&1
 *
 * On Windows Task Scheduler:
 *   Program:  C:\xampp\php\php.exe
 *   Args:     C:\xampp\htdocs\RPL2\database\cron_check_expired_payments.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/Views');

// Load environment and config
require_once ROOT_PATH . '/config/env.php';
load_env(ROOT_PATH . '/.env');
require_once ROOT_PATH . '/config/app.php';

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix  = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Models\Payment;

$startTime = microtime(true);
$startLabel = date('Y-m-d H:i:s');

echo "[{$startLabel}] SmartLaundry — QRIS Expiry Cron Started\n";

try {
    $paymentModel = new Payment();
    $expiredCount = $paymentModel->expireAllPendingPastDeadline();

    $elapsed = round(microtime(true) - $startTime, 3);
    echo "[{$startLabel}] ✓ Expired {$expiredCount} overdue payment(s) in {$elapsed}s\n";
    echo "[{$startLabel}] Cron completed successfully.\n";
    exit(0);
} catch (\Throwable $e) {
    $elapsed = round(microtime(true) - $startTime, 3);
    echo "[{$startLabel}] ✗ ERROR: " . $e->getMessage() . " (in {$elapsed}s)\n";
    echo "[{$startLabel}] Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
