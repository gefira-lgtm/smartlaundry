<?php
/**
 * Cron Job: Purge old chatbot_messages
 *
 * Hapus otomatis pesan chatbot lebih tua dari CHATBOT_LOG_RETENTION_DAYS hari.
 * Script ini idempoten – aman dijalankan berkali-kali.
 *
 * Cara penggunaan:
 *   php cron_purge_chatbot_logs.php
 *
 * Contoh entri crontab (setiap hari pukul 02.00):
 *   0 2 * * * /usr/bin/php /var/www/html/RPL2/cron_purge_chatbot_logs.php >> /dev/null 2>&1
 */

// Bootstrap minimal (tanpa routing / session)
define('RUNNING_AS_CLI_CRON', true);

require_once __DIR__ . '/config/env.php';
load_env(__DIR__ . '/.env');
require_once __DIR__ . '/config/app.php';

// Autoloader
spl_autoload_register(function ($class) {
    $base = APP_PATH . '/';
    $prefix = 'App\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $base . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Models\ChatbotMessage;
use App\Models\Setting;

$startTime = microtime(true);
$retentionDays = (int)Setting::get('chatbot_log_retention_days', env('CHATBOT_LOG_RETENTION_DAYS', 90));
if ($retentionDays < 1) {
    $retentionDays = 90; // fallback ke default jika nilai tidak valid
}

$logDir  = ROOT_PATH . '/storage/logs';
$logFile = $logDir . '/cron_chatbot_purge.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

try {
    $model   = new ChatbotMessage();
    $deleted = $model->purgeOlderThan($retentionDays);
    $duration = round(microtime(true) - $startTime, 4);

    $msg = sprintf(
        "[%s] Purge chatbot_messages: %d baris dihapus (retensi %d hari) dalam %ss.\n",
        date('Y-m-d H:i:s'),
        $deleted,
        $retentionDays,
        $duration
    );

    $summary = "Purge: {$deleted} baris dihapus (retensi {$retentionDays} hari).";
    Setting::recordCronHeartbeat('purge_chatbot_logs', 'SUCCESS', $summary, $duration);

    echo $msg;
    file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);
} catch (\Throwable $th) {
    $duration = round(microtime(true) - $startTime, 4);
    Setting::recordCronHeartbeat('purge_chatbot_logs', 'ERROR', $th->getMessage(), $duration);
    $errMsg = sprintf(
        "[%s] ERROR saat purge chatbot_messages: %s\n",
        date('Y-m-d H:i:s'),
        $th->getMessage()
    );
    echo $errMsg;
    file_put_contents($logFile, $errMsg, FILE_APPEND | LOCK_EX);
    exit(1);
}

exit(0);
