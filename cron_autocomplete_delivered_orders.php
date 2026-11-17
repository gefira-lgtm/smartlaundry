<?php
/**
 * Cron Job: Auto-Complete Delivered Orders Past 24 Hours
 * 
 * Usage: php cron_autocomplete_delivered_orders.php
 */
define('ROOT_PATH', __DIR__);
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

use App\Models\Order;
use App\Models\Setting;

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Starting automated order auto-completion check...\n";

try {
    $orderModel = new Order();
    $hours = (int)Setting::get('order_autocomplete_hours', 24);
    $autoCompletedCount = $orderModel->autoCompleteDeliveredOrders($hours);
    $duration = round(microtime(true) - $startTime, 4);

    $summary = "Auto-completed {$autoCompletedCount} delivered order(s) past {$hours} hours.";
    Setting::recordCronHeartbeat('autocomplete_delivered_orders', 'SUCCESS', $summary, $duration);

    echo "[{$timestamp}] Completed! Auto-completed {$autoCompletedCount} delivered order(s) past {$hours} hours in {$duration}s.\n";
} catch (\Throwable $th) {
    $duration = round(microtime(true) - $startTime, 4);
    Setting::recordCronHeartbeat('autocomplete_delivered_orders', 'ERROR', $th->getMessage(), $duration);
    echo "[{$timestamp}] ERROR during auto-complete check: " . $th->getMessage() . "\n";
    exit(1);
}
