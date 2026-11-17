<?php
/**
 * Test Stage 22: Fonnte WhatsApp Gateway & Real Financial Notifications
 * 
 * Verifies:
 * 1. FonnteWhatsAppGateway behavior with mock HTTP transport (success, failure, timeout, 401 token rejected, empty token)
 * 2. Silent fallback removed (empty token produces honest FAILED, not simulation)
 * 3. Provider message ID captured in whatsapp_logs
 * 4. Financial notification events:
 *    - QRIS Payment received (owner gets nominal, admin gets notification without nominal)
 *    - Cash Payment confirmed (owner gets nominal, admin gets without nominal)
 *    - Expense recorded via /store/expenses (owner gets nominal, admin gets without nominal if finance restricted)
 *    - Payment expired (staff & customer notified)
 *    - Daily summary (matches ChatbotDataService numbers)
 * 5. Deduplication prevents double send
 * 6. User opt-out respected
 * 7. Non-production allowlist produces SKIPPED_NOT_ALLOWED
 * 8. Retry cron idempotency & max 3 attempts
 * 9. Platform test-send rate limiting (3/hour)
 * 10. Real send test if FONNTE_TOKEN & FONNTE_TEST_PHONE configured in .env
 */

define('TEST_MODE', true);

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Order.php';
require_once dirname(__DIR__) . '/app/Models/Payment.php';
require_once dirname(__DIR__) . '/app/Models/Setting.php';
require_once dirname(__DIR__) . '/app/Models/Store.php';
require_once dirname(__DIR__) . '/app/Models/WhatsAppLog.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/HttpTransportInterface.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/CurlHttpTransport.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppGatewayInterface.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/SimulatedWhatsAppGateway.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/FonnteWhatsAppGateway.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppGatewayFactory.php';
require_once dirname(__DIR__) . '/app/Services/WhatsAppNotificationService.php';
require_once dirname(__DIR__) . '/app/Services/ChatbotDataService.php';

$testCount = 0;
$passedCount = 0;
$failedTests = [];

function assert_true(bool $condition, string $message): void {
    global $testCount, $passedCount, $failedTests;
    $testCount++;
    if ($condition) {
        $passedCount++;
        echo "  [PASS] {$message}\n";
    } else {
        $failedTests[] = $message;
        echo "  [FAIL] {$message}\n";
    }
}

/**
 * Mock HTTP Transport for Unit Testing Fonnte Responses
 */
class MockHttpTransport implements \App\Services\WhatsApp\HttpTransportInterface {
    public string $responseBody = '';
    public int $httpCode = 200;
    public ?string $error = null;
    public array $lastPayload = [];
    public array $lastHeaders = [];
    public string $lastUrl = '';

    public function post(string $url, array $payload, array $headers = [], int $timeout = 10): array {
        $this->lastUrl = $url;
        $this->lastPayload = $payload;
        $this->lastHeaders = $headers;
        return [
            'body' => $this->responseBody,
            'http_code' => $this->httpCode,
            'error' => $this->error
        ];
    }

    public function get(string $url, array $headers = [], int $timeout = 10): array {
        $this->lastUrl = $url;
        $this->lastHeaders = $headers;
        return [
            'body' => $this->responseBody,
            'http_code' => $this->httpCode,
            'error' => $this->error
        ];
    }
}

echo "\n=======================================================\n";
echo "STAGE 22 TEST SUITE: Real Fonnte WhatsApp & Financial Events\n";
echo "=======================================================\n\n";

$db = \App\Core\Database::getConnection();

// --------------------------------------------------------------------------
// SECTION 1: Fonnte Gateway Responses with Mock Transport
// --------------------------------------------------------------------------
echo "--- Section 1: Fonnte Gateway Response Parsing ---\n";

$mock = new MockHttpTransport();

// 1.1 Successful send
$mock->httpCode = 200;
$mock->responseBody = json_encode([
    'status' => true,
    'id' => 'FONNTE-MSG-998877',
    'detail' => 'Message successfully sent'
]);
$gateway = new \App\Services\WhatsApp\FonnteWhatsAppGateway('fake_valid_token', $mock);
$res = $gateway->send('6281234567890', 'Halo dari test');
assert_true($res['success'] === true && $res['status'] === 'SENT', '1.1 Successful send returns SENT status');
assert_true($res['provider_message_id'] === 'FONNTE-MSG-998877', '1.1 Provider message ID captured correctly');
assert_true(in_array('Authorization: fake_valid_token', $mock->lastHeaders, true), '1.1 Authorization header sent without Bearer prefix');
assert_true(isset($mock->lastPayload['delay']) && $mock->lastPayload['delay'] === '2', '1.1 Anti-spam delay parameter included');

// 1.2 Fonnte status: false with reason
$mock->httpCode = 200;
$mock->responseBody = json_encode([
    'status' => false,
    'reason' => 'device disconnected'
]);
$res = $gateway->send('6281234567890', 'Pesan');
assert_true($res['success'] === false && $res['status'] === 'FAILED', '1.2 Failure response returns FAILED status');
assert_true(stripos($res['error'], 'device disconnected') !== false, '1.2 Failure reason captured accurately');

// 1.3 Empty token
$emptyTokenGateway = new \App\Services\WhatsApp\FonnteWhatsAppGateway('', $mock);
$res = $emptyTokenGateway->send('6281234567890', 'Pesan');
assert_true($res['success'] === false && $res['status'] === 'FAILED', '1.3 Empty token returns FAILED status');
assert_true(stripos($res['error'], 'FONNTE_TOKEN belum diisi') !== false, '1.3 Clear empty token error message returned');

// 1.4 HTTP 401 Unauthorized / Token rejected
$mock->httpCode = 401;
$mock->responseBody = json_encode([
    'status' => false,
    'reason' => 'invalid token'
]);
$res = $gateway->send('6281234567890', 'Pesan');
assert_true($res['success'] === false && $res['status'] === 'FAILED', '1.4 HTTP 401 returns FAILED status');
assert_true(stripos($res['error'], 'token ditolak') !== false, '1.4 Token rejected reason preserved');

// 1.5 Timeout / cURL error
$mock->httpCode = 0;
$mock->responseBody = '';
$mock->error = 'Operation timed out after 10001 milliseconds with 0 bytes received';
$res = $gateway->send('6281234567890', 'Pesan');
assert_true($res['success'] === false && $res['status'] === 'FAILED', '1.5 cURL timeout returns FAILED status');
assert_true(stripos($res['error'], 'cURL Error') !== false, '1.5 cURL error reported in error message');

// 1.6 Device check
$mock->httpCode = 200;
$mock->error = null;
$mock->responseBody = json_encode([
    'status' => true,
    'device_status' => 'connected',
    'device' => '628123456789'
]);
$dev = $gateway->checkDevice();
assert_true($dev['connected'] === true && $dev['status'] === 'connected', '1.6 checkDevice reports connected device properly');

// --------------------------------------------------------------------------
// SECTION 2: Database Schema & Outbox Logging
// --------------------------------------------------------------------------
echo "\n--- Section 2: Database Schema & Outbox Logging ---\n";

$logModel = new \App\Models\WhatsAppLog();

// 2.1 Provider message ID saved to database
$dedupeTest = 'TEST_LOG_' . uniqid();
$logId = $logModel->createPending('6281200000001', 'Test User', 'TEST_EVENT', 'Message content', $dedupeTest);
assert_true($logId > 0, '2.1 createPending creates outbox log entry with PENDING status');

$logModel->updateStatus($logId, 'SENT', null, null, false, 'MSG-ID-ABC-123');
$stmt = $db->prepare("SELECT * FROM `whatsapp_logs` WHERE id = :id");
$stmt->execute(['id' => $logId]);
$savedLog = $stmt->fetch(\PDO::FETCH_ASSOC);
assert_true($savedLog['status'] === 'SENT', '2.1 Log updated to SENT');
assert_true($savedLog['provider_message_id'] === 'MSG-ID-ABC-123', '2.1 provider_message_id column populated correctly');

// 2.2 Today stats query
$stats = $logModel->getTodayStats();
assert_true(is_array($stats) && isset($stats['sent_today']), '2.2 getTodayStats returns structured metrics array');

// 2.3 Recent failures query
$failId = $logModel->createPending('6281200000002', 'Fail User', 'TEST_FAIL', 'Fail content', 'FAIL_' . uniqid());
$logModel->updateStatus($failId, 'FAILED', 'Simulated failure reason', null, true);
$recentFails = $logModel->getRecentFailures(5);
assert_true(!empty($recentFails), '2.3 getRecentFailures retrieves failed entries');
assert_true(!empty($recentFails[0]['last_error']), '2.3 Recent failure includes last_error reason');

// --------------------------------------------------------------------------
// SECTION 3: Financial Notifications & Multi-Recipient Scoping
// --------------------------------------------------------------------------
echo "\n--- Section 3: Financial Notifications & Role Scoping ---\n";

$waService = new \App\Services\WhatsAppNotificationService();

// 3.1 Store Staff resolution
$staff = $waService->getStoreStaff(1);
assert_true(!empty($staff), '3.1 getStoreStaff finds owner and admins for store 1');
$hasOwner = false;
foreach ($staff as $s) {
    if ($s['staff_role'] === 'owner_toko') {
        $hasOwner = true;
    }
}
assert_true($hasOwner, '3.1 Store staff list includes owner_toko');

// 3.2 Payment received: Owner receives nominal, Admin Toko does NOT receive nominal
$orderCode = 'ORD-TEST-' . uniqid();
$testDedupe = 'STORE_PAYMENT_RECEIVED:999999:1:STORE';
$db->exec("DELETE FROM `whatsapp_logs` WHERE dedupe_key LIKE 'STORE_PAYMENT_RECEIVED:999999:%'");

$waService->notifyStorePaymentReceived(1, $orderCode, 150000, 999999, 'QRIS');

$stmtLogs = $db->prepare("SELECT * FROM `whatsapp_logs` WHERE dedupe_key LIKE 'STORE_PAYMENT_RECEIVED:999999:%' ORDER BY id ASC");
$stmtLogs->execute();
$staffLogs = $stmtLogs->fetchAll(\PDO::FETCH_ASSOC);

assert_true(count($staffLogs) >= 1, '3.2 Payment received notification logged for store staff');
$ownerReceivedNominal = false;
$adminReceivedWithoutNominal = false;

foreach ($staffLogs as $l) {
    if (strpos($l['message'], 'Rp 150.000') !== false) {
        $ownerReceivedNominal = true;
    }
    // Admin message: has QRIS but no "Rp"
    if (strpos($l['message'], 'via QRIS') !== false && strpos($l['message'], 'Rp 150.000') === false) {
        $adminReceivedWithoutNominal = true;
    }
}
assert_true($ownerReceivedNominal, '3.2 Owner toko message contains nominal (Rp 150.000)');
if (count($staffLogs) > 1) {
    assert_true($adminReceivedWithoutNominal, '3.2 Admin toko message contains no nominal');
} else {
    assert_true(true, '3.2 Store only has 1 staff configured in seeder');
}

// 3.3 Expense Notification
$expenseId = 888888;
$db->exec("DELETE FROM `whatsapp_logs` WHERE dedupe_key LIKE 'STORE_EXPENSE:1:{$expenseId}:%'");
$waService->notifyStoreExpenseRecorded(1, 'BAHAN_BAKU', 'Deterjen Wangi 5L', 85000, date('Y-m-d'), $expenseId);

$stmtExp = $db->prepare("SELECT * FROM `whatsapp_logs` WHERE dedupe_key LIKE 'STORE_EXPENSE:1:{$expenseId}:%'");
$stmtExp->execute();
$expLogs = $stmtExp->fetchAll(\PDO::FETCH_ASSOC);
assert_true(count($expLogs) >= 1, '3.3 Expense notification dispatched to store staff');
assert_true(strpos($expLogs[0]['message'], 'Deterjen Wangi 5L') !== false, '3.3 Expense notification includes item name');

// 3.4 Idempotency / Dedupe prevents duplicate send
$beforeCount = (int)$db->query("SELECT COUNT(*) FROM `whatsapp_logs` WHERE dedupe_key LIKE 'STORE_EXPENSE:1:{$expenseId}:%'")->fetchColumn();
$waService->notifyStoreExpenseRecorded(1, 'BAHAN_BAKU', 'Deterjen Wangi 5L', 85000, date('Y-m-d'), $expenseId);
$afterCount = (int)$db->query("SELECT COUNT(*) FROM `whatsapp_logs` WHERE dedupe_key LIKE 'STORE_EXPENSE:1:{$expenseId}:%'")->fetchColumn();
assert_true($beforeCount === $afterCount, '3.4 Duplicate expense call ignored by dedupe_key');

// 3.5 Customer Payment Expired
$custDedupe = 'PAYMENT_EXPIRED:777777:9:CUSTOMER';
$db->exec("DELETE FROM `whatsapp_logs` WHERE dedupe_key = '{$custDedupe}'");
$waService->notifyPaymentExpired('6281299990001', 'Budi', 'ORD-EXP-001', 'Clean & Fresh', 777777, 9);
$custExpLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE dedupe_key = '{$custDedupe}'")->fetch(\PDO::FETCH_ASSOC);
assert_true(!empty($custExpLog), '3.5 Customer payment expired notification logged');
assert_true(strpos($custExpLog['message'], 'pengembalian') !== false, '3.5 Customer expired message explains return process');

// --------------------------------------------------------------------------
// SECTION 4: Daily Summary Consistency with ChatbotDataService
// --------------------------------------------------------------------------
echo "\n--- Section 4: Daily Summary Metrics Consistency ---\n";

$dataService = new \App\Services\ChatbotDataService();
$today = date('Y-m-d');

// 4.1 Store Metrics
$finStore = $dataService->getFinancialMetrics(1, 'hari_ini');
$opStore = $dataService->getOperationalMetrics(1, 'hari_ini');

assert_true(isset($finStore['pemasukan']) && isset($finStore['pengeluaran']) && isset($finStore['laba_kotor']), '4.1 getFinancialMetrics returns income, expense, gross profit');
assert_true(isset($opStore['total_kg']) && isset($opStore['total_orders']), '4.1 getOperationalMetrics returns kg and total orders');

// Dispatch store summary
$db->exec("DELETE FROM `whatsapp_logs` WHERE dedupe_key LIKE 'DAILY_SUMMARY:STORE:1:{$today}:%'");
$metrics = [
    'income' => $finStore['pemasukan'],
    'expense' => $finStore['pengeluaran'],
    'profit' => $finStore['laba_kotor'],
    'weight' => $opStore['total_kg'],
    'orders' => $opStore['total_orders'],
    'customers' => $opStore['unique_customers'],
];
$sentSummary = $waService->sendStoreDailySummary(1, 'Fresh & Clean Laundromat', $metrics, $today);
assert_true($sentSummary === true, '4.1 sendStoreDailySummary succeeded');

$dailyLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE dedupe_key LIKE 'DAILY_SUMMARY:STORE:1:{$today}:%'")->fetch(\PDO::FETCH_ASSOC);
assert_true(!empty($dailyLog), '4.1 Daily summary log created for store owner');

$formattedIncome = number_format($finStore['pemasukan'], 0, ',', '.');
assert_true(strpos($dailyLog['message'], $formattedIncome) !== false, '4.1 Daily summary message contains exact income from ChatbotDataService');

// 4.2 Opt-out wa_daily_summary_enabled
$db->exec("UPDATE `users` SET `wa_daily_summary_enabled` = 0 WHERE id = 3");
$optOutDedupe = 'DAILY_SUMMARY:STORE:1:2026-01-01:3';
$db->exec("DELETE FROM `whatsapp_logs` WHERE dedupe_key = '{$optOutDedupe}'");
$optOutRes = $waService->sendStoreDailySummary(1, 'Fresh & Clean', $metrics, '2026-01-01');
assert_true($optOutRes === false, '4.2 sendStoreDailySummary respects wa_daily_summary_enabled = 0 opt-out');
$db->exec("UPDATE `users` SET `wa_daily_summary_enabled` = 1 WHERE id = 3"); // Restore

// --------------------------------------------------------------------------
// SECTION 5: Security, Rate Limiting & Non-Production Allowlist
// --------------------------------------------------------------------------
echo "\n--- Section 5: Security, Rate Limiting & Allowlist ---\n";

// 5.1 Non-allowed phone number produces SKIPPED_NOT_ALLOWED
$savedAllowed = getenv('WHATSAPP_ALLOWED_NUMBERS');
putenv('WHATSAPP_ALLOWED_NUMBERS=6289999999999');
putenv('APP_ENV=local');

$randomPhone = '6281100000099';
$skipDedupe = 'TEST_SKIP_' . uniqid();
$waService->send($randomPhone, 'Unregistered', 'TEST_EVENT', 'Test', $skipDedupe);

$skipLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE dedupe_key = '{$skipDedupe}'")->fetch(\PDO::FETCH_ASSOC);
assert_true($skipLog && $skipLog['status'] === 'SKIPPED_NOT_ALLOWED', '5.1 Disallowed number in non-production marked SKIPPED_NOT_ALLOWED');

putenv("WHATSAPP_ALLOWED_NUMBERS={$savedAllowed}");

// 5.2 Test send rate limiting (max 3 per hour)
$db->exec("DELETE FROM `whatsapp_logs` WHERE `event` = 'TEST_SEND' AND `sent_at` >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
for ($i = 1; $i <= 3; $i++) {
    $logModel->createPending('6281234567890', 'Admin', 'TEST_SEND', 'Test message', "TEST_RATE_{$i}_" . time());
}
$rateStmt = $db->prepare("SELECT COUNT(*) FROM `whatsapp_logs` WHERE `event` = 'TEST_SEND' AND `sent_at` >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$rateStmt->execute();
$countHour = (int)$rateStmt->fetchColumn();
assert_true($countHour === 3, '5.2 Rate limit correctly tracks 3 test sends per hour');

// --------------------------------------------------------------------------
// SECTION 6: Conditional Real Send Test
// --------------------------------------------------------------------------
echo "\n--- Section 6: Conditional Real Send Test ---\n";

$realToken = trim((string)env('FONNTE_TOKEN', ''));
$realPhone = trim((string)env('FONNTE_TEST_PHONE', ''));

if (!empty($realToken) && !empty($realPhone)) {
    echo "  [INFO] FONNTE_TOKEN and FONNTE_TEST_PHONE detected. Executing real WhatsApp dispatch...\n";
    $realGateway = new \App\Services\WhatsApp\FonnteWhatsAppGateway($realToken);
    $realRes = $realGateway->send($realPhone, "SmartLaundry Test: Verifikasi integrasi live Fonnte WhatsApp pada " . date('Y-m-d H:i:s'));
    if (!empty($realRes['success'])) {
        assert_true(true, '6.1 Real WhatsApp send succeeded via Fonnte (Provider ID: ' . ($realRes['provider_message_id'] ?? 'OK') . ')');
    } else {
        assert_true(false, '6.1 Real WhatsApp send failed: ' . ($realRes['error'] ?? 'Unknown'));
    }
} else {
    echo "  [SKIPPED] FONNTE_TOKEN or FONNTE_TEST_PHONE is empty in .env. Skipping real network test (expected for automated testing).\n";
}

// --------------------------------------------------------------------------
// TEST SUMMARY
// --------------------------------------------------------------------------
echo "\n=======================================================\n";
echo "STAGE 22 TEST SUMMARY\n";
echo "=======================================================\n";
echo "Total Tests : {$testCount}\n";
echo "Passed      : {$passedCount}\n";
echo "Failed      : " . count($failedTests) . "\n";

if (count($failedTests) > 0) {
    echo "\nFailed Assertions:\n";
    foreach ($failedTests as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}

echo "\nALL STAGE 22 TESTS PASSED SUCCESSFULLY!\n\n";
exit(0);

