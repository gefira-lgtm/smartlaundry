<?php
/**
 * SmartLaundry AI - Cron Job: Retry Failed WhatsApp Outbox Messages
 * 
 * Retries FAILED messages up to 3 times with exponential backoff.
 * 
 * Usage: php database/cron_retry_whatsapp.php
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

use App\Models\WhatsAppLog;
use App\Models\Setting;
use App\Services\WhatsApp\WhatsAppGatewayFactory;

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Starting WhatsApp Outbox Retry Worker...\n";

try {
    $logModel = new WhatsAppLog();
    $gateway = WhatsAppGatewayFactory::create();

    $pendingRetries = $logModel->getPendingRetries(50);
    $totalFound = count($pendingRetries);
    $successCount = 0;
    $failedCount = 0;

    echo "[{$timestamp}] Found {$totalFound} failed message(s) ready for retry.\n";

    foreach ($pendingRetries as $item) {
        $id = (int)$item['id'];
        $attempts = (int)$item['attempts'] + 1;
        $phone = $item['phone'];
        $message = $item['message'];

        echo " - Retrying log #{$id} to {$phone} (Attempt {$attempts}/3)... ";

        try {
            $res = $gateway->send($phone, $message);
            if (!empty($res['success'])) {
                $providerMsgId = $res['provider_message_id'] ?? null;
                $logModel->updateStatus($id, $res['status'] ?? 'SENT', null, null, false, $providerMsgId);
                echo "[SUCCESS, ProviderID: " . ($providerMsgId ?? 'N/A') . "]\n";
                $successCount++;
            } else {
                // Exponential backoff: attempt 1: 5m, attempt 2: 15m, attempt 3: locked
                $nextIntervalMinutes = ($attempts === 1) ? 5 : (($attempts === 2) ? 15 : 60);
                $nextRetryAt = ($attempts < 3) ? date('Y-m-d H:i:s', time() + ($nextIntervalMinutes * 60)) : null;
                $errorMsg = $res['error'] ?? 'Gagal pada percobaan ulang';

                $logModel->updateStatus($id, 'FAILED', $errorMsg, $nextRetryAt, true);
                echo "[FAILED: {$errorMsg}]\n";
                $failedCount++;
            }
        } catch (\Throwable $e) {
            $nextRetryAt = ($attempts < 3) ? date('Y-m-d H:i:s', time() + 300) : null;
            $logModel->updateStatus($id, 'FAILED', $e->getMessage(), $nextRetryAt, true);
            echo "[ERROR: {$e->getMessage()}]\n";
            $failedCount++;
        }
    }

    $duration = round(microtime(true) - $startTime, 4);
    $summary = "Retry outbox diproses: {$totalFound} pesan ({$successCount} berhasil, {$failedCount} gagal).";
    Setting::recordCronHeartbeat('retry_whatsapp_outbox', 'SUCCESS', $summary, $duration);

    echo "[{$timestamp}] Completed retry cycle in {$duration}s: {$successCount} succeeded, {$failedCount} failed.\n";
} catch (\Throwable $th) {
    $duration = round(microtime(true) - $startTime, 4);
    Setting::recordCronHeartbeat('retry_whatsapp_outbox', 'ERROR', $th->getMessage(), $duration);
    echo "[{$timestamp}] CRITICAL ERROR in WhatsApp retry worker: " . $th->getMessage() . "\n";
    exit(1);
}
