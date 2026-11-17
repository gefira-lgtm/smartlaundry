<?php
/**
 * SmartLaundry AI - Cron Job: Send WhatsApp Daily Financial Summary
 * 
 * Scheduled daily (default 21:00 WIB)
 * - Sends individual store summary to owner_toko
 * - Sends platform-wide summary to owner_platform & admin_owner
 * 
 * Usage: php database/cron_daily_summary.php
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
use App\Services\WhatsAppNotificationService;
use App\Services\ChatbotDataService;

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');
$today = date('Y-m-d');
echo "[{$timestamp}] Starting WhatsApp Daily Financial Summary Cron...\n";

// Optional hour check: If run with --force or current hour matches DAILY_SUMMARY_HOUR
$targetHour = (int)Setting::get('wa_daily_summary_hour', env('DAILY_SUMMARY_HOUR', 21));
$currentHour = (int)date('H');
$isForce = in_array('--force', $argv ?? [], true);

if (!$isForce && $currentHour !== $targetHour) {
    echo "[{$timestamp}] Current hour ({$currentHour}) does not match configured target hour ({$targetHour}). Use --force to override. Skipping.\n";
    exit(0);
}

try {
    $db = Database::getConnection();
    $waService = new WhatsAppNotificationService();
    $dataService = new ChatbotDataService();

    $storeSuccess = 0;
    $platformSuccess = 0;

    // 1. Process each active store for owner_toko
    $stores = $db->query("SELECT id, name, owner_id FROM `stores` WHERE is_active = 1 ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);
    echo "Found " . count($stores) . " active store(s).\n";

    foreach ($stores as $store) {
        $storeId = (int)$store['id'];
        $storeName = $store['name'];

        $fin = $dataService->getFinancialMetrics($storeId, 'hari_ini');
        $op = $dataService->getOperationalMetrics($storeId, 'hari_ini');

        $metrics = [
            'income' => $fin['pemasukan'] ?? 0,
            'expense' => $fin['pengeluaran'] ?? 0,
            'profit' => $fin['laba_kotor'] ?? 0,
            'weight' => $op['total_kg'] ?? 0,
            'orders' => $op['total_orders'] ?? 0,
            'customers' => $op['unique_customers'] ?? 0,
        ];

        echo " - Dispatching store summary for '{$storeName}' (ID: {$storeId})... ";
        $sent = $waService->sendStoreDailySummary($storeId, $storeName, $metrics, $today);
        if ($sent) {
            echo "[SENT]\n";
            $storeSuccess++;
        } else {
            echo "[SKIPPED / ALREADY SENT]\n";
        }
    }

    // 2. Process platform-wide summary for owner_platform & admin_owner
    $platformFin = $dataService->getFinancialMetrics(null, 'hari_ini');
    $platformOp = $dataService->getOperationalMetrics(null, 'hari_ini');

    // Get top 3 stores by revenue today
    $stmtTop = $db->prepare(
        "SELECT s.name, COALESCE(SUM(p.amount), 0) AS total_rev
         FROM `stores` s
         LEFT JOIN `orders` o ON o.store_id = s.id
         LEFT JOIN `payments` p ON p.order_id = o.id AND p.status = 'PAID' AND DATE(p.paid_at) = CURDATE()
         WHERE s.is_active = 1
         GROUP BY s.id, s.name
         ORDER BY total_rev DESC, s.name ASC
         LIMIT 3"
    );
    $stmtTop->execute();
    $topStores = $stmtTop->fetchAll(\PDO::FETCH_ASSOC);
    $topList = [];
    foreach ($topStores as $idx => $ts) {
        $topList[] = ($idx + 1) . ". {$ts['name']} (" . 'Rp ' . number_format($ts['total_rev'], 0, ',', '.') . ")";
    }
    $topStoresStr = !empty($topList) ? implode(', ', $topList) : 'Belum ada data';

    $inStr = 'Rp ' . number_format($platformFin['pemasukan'] ?? 0, 0, ',', '.');
    $exStr = 'Rp ' . number_format($platformFin['pengeluaran'] ?? 0, 0, ',', '.');
    $txCount = (int)($platformOp['total_orders'] ?? 0);

    $summaryText = "Pemasukan {$inStr}, Pengeluaran {$exStr}, Total {$txCount} transaksi. Toko teratas: {$topStoresStr}";
    echo " - Dispatching platform summary to platform admins... ";
    $platformSent = $waService->sendDailySummary($summaryText);
    echo "[{$platformSent} sent]\n";
    $platformSuccess = $platformSent;

    $duration = round(microtime(true) - $startTime, 4);
    $summary = "Daily summary cron executed: {$storeSuccess} stores, {$platformSuccess} platform admins.";
    Setting::recordCronHeartbeat('daily_summary_whatsapp', 'SUCCESS', $summary, $duration);

    echo "[{$timestamp}] Daily summary cron finished in {$duration}s.\n";
} catch (\Throwable $th) {
    $duration = round(microtime(true) - $startTime, 4);
    Setting::recordCronHeartbeat('daily_summary_whatsapp', 'ERROR', $th->getMessage(), $duration);
    echo "[{$timestamp}] CRITICAL ERROR in Daily Summary Cron: " . $th->getMessage() . "\n";
    exit(1);
}

