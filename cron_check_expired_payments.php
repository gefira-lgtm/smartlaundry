<?php
/**
 * Cron Job / Background Worker: Check and Expire Overdue QRIS Payments
 * 
 * Usage: php cron_check_expired_payments.php
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

use App\Models\Payment;
use App\Models\Order;
use App\Models\Setting;

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Starting automated background maintenance check...\n";

try {
    $paymentModel = new Payment();
    $orderModel = new Order();

    // 1. Expire overdue pending payments
    $expiredCount = $paymentModel->expireAllPendingPastDeadline();
    echo "[{$timestamp}] Overdue payments processed: {$expiredCount} payment(s) marked EXPIRED.\n";

    // 2. Send expiring soon reminders based on dynamic setting
    $reminderMinutes = (int)Setting::get('payment_reminder_minutes', 15);
    $reminderCount = $paymentModel->sendExpiringSoonReminders($reminderMinutes);
    echo "[{$timestamp}] Payment reminders sent ({$reminderMinutes}m threshold): {$reminderCount} customer(s) notified.\n";

    // 3. Auto-complete delivered orders based on dynamic setting
    $autoCompleteHours = (int)Setting::get('order_autocomplete_hours', 24);
    $autoCompletedCount = $orderModel->autoCompleteDeliveredOrders($autoCompleteHours);
    echo "[{$timestamp}] Auto-completed orders ({$autoCompleteHours}h threshold): {$autoCompletedCount} order(s) marked SELESAI.\n";

    $duration = round(microtime(true) - $startTime, 4);
    $summary = "Diproses: {$expiredCount} kedaluwarsa, {$reminderCount} pengingat ({$reminderMinutes}m), {$autoCompletedCount} auto-complete ({$autoCompleteHours}h).";
    Setting::recordCronHeartbeat('check_expired_payments', 'SUCCESS', $summary, $duration);

    echo "[{$timestamp}] Completed! Processed background maintenance tasks in {$duration}s.\n";
} catch (\Throwable $th) {
    $duration = round(microtime(true) - $startTime, 4);
    Setting::recordCronHeartbeat('check_expired_payments', 'ERROR', $th->getMessage(), $duration);
    echo "[{$timestamp}] ERROR during background maintenance check: " . $th->getMessage() . "\n";
    exit(1);
}
