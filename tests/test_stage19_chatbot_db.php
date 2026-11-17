<?php
/**
 * Test Stage 19: Chatbot DB-driven Answers & Cross-table Queries
 */

define('TEST_MODE', true);

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';

use App\Core\Database;
use App\Services\AIChatbotService;
use App\Services\ChatbotNLUService;
use App\Services\ChatbotQueryEngine;

$passed = 0;
$failed = 0;
$errors = [];

function assert_test(bool $condition, string $testName, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "  [PASS] {$testName}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$testName}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
        $errors[] = $testName . ($detail ? ": {$detail}" : '');
    }
}

echo "====================================================================\n";
echo "STAGE 19: CHATBOT DB-DRIVEN ANSWERS & CROSS-TABLE QUERIES\n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$bot = new AIChatbotService();

// -----------------------------------------------------------------------------
// 1. NLU Unit Tests: Amount Normalization & Period Extraction
// -----------------------------------------------------------------------------
echo "--- 1. NLU & Entity Normalization Tests ---\n";

$amt1 = ChatbotNLUService::normalizeAmount('500.000');
assert_test($amt1 === 500000.0, 'Normalize 500.000 -> 500000');

$amt2 = ChatbotNLUService::normalizeAmount('Rp 500.000');
assert_test($amt2 === 500000.0, 'Normalize Rp 500.000 -> 500000');

$amt3 = ChatbotNLUService::normalizeAmount('500rb');
assert_test($amt3 === 500000.0, 'Normalize 500rb -> 500000');

$amt4 = ChatbotNLUService::normalizeAmount('500 ribu');
assert_test($amt4 === 500000.0, 'Normalize 500 ribu -> 500000');

$amt5 = ChatbotNLUService::normalizeAmount('500k');
assert_test($amt5 === 500000.0, 'Normalize 500k -> 500000');

$amt6 = ChatbotNLUService::normalizeAmount('setengah juta');
assert_test($amt6 === 500000.0, 'Normalize setengah juta -> 500000');

$amt7 = ChatbotNLUService::normalizeAmount('1,5 juta');
assert_test($amt7 === 1500000.0, 'Normalize 1,5 juta -> 1500000');

$amtExtracted = ChatbotNLUService::extractAmountFromMessage('pengeluaran 500.000 digunakan untuk apa');
assert_test($amtExtracted === 500000.0, 'Extract amount from message "pengeluaran 500.000 digunakan untuk apa"');

$periodBulanIni = ChatbotNLUService::extractPeriod('total pengeluaran bulan ini');
assert_test($periodBulanIni === 'bulan_ini', 'Extract period "bulan ini"');

$catBahanBaku = ChatbotNLUService::extractExpenseCategory('pengeluaran bahan baku');
assert_test($catBahanBaku === 'BAHAN_BAKU', 'Extract category "bahan baku"');

// -----------------------------------------------------------------------------
// 2. Chatbot DB Answers: Rp 500.000 Query & Role Scoping
// -----------------------------------------------------------------------------
echo "\n--- 2. Expense Lookup & Store Role Scoping Tests ---\n";

// Owner Toko CleanWash (Store 1, user_id=3)
$resOwner1 = $bot->chat('pengeluaran 500.000 digunakan untuk apa?', 3, 'owner_toko');
assert_test($resOwner1['intent'] === 'DATA_EXPENSE_LOOKUP', 'Owner Toko 1 intent is DATA_EXPENSE_LOOKUP');
assert_test(str_contains($resOwner1['reply'], 'Deterjen Cair Premium'), 'Owner Toko 1 sees their Deterjen Cair Premium');
assert_test(str_contains($resOwner1['reply'], 'CV Bersih Jaya'), 'Owner Toko 1 sees supplier CV Bersih Jaya');
assert_test(str_contains($resOwner1['reply'], 'CleanWash Express'), 'Owner Toko 1 sees CleanWash Express');
assert_test(!str_contains($resOwner1['reply'], 'Pelembut Pakaian'), 'Owner Toko 1 CANNOT see Store 2 Pelembut Pakaian (Isolation)');

// Owner Toko Fresh & Soft (Store 2, user_id=4)
$resOwner2 = $bot->chat('pengeluaran 500.000 digunakan untuk apa?', 4, 'owner_toko');
assert_test(str_contains($resOwner2['reply'], 'Pelembut Pakaian'), 'Owner Toko 2 sees their Pelembut Pakaian');
assert_test(str_contains($resOwner2['reply'], 'PT Harum Wangi'), 'Owner Toko 2 sees supplier PT Harum Wangi');
assert_test(str_contains(html_entity_decode($resOwner2['reply']), 'Fresh & Soft'), 'Owner Toko 2 sees Fresh & Soft');
assert_test(!str_contains($resOwner2['reply'], 'Deterjen Cair Premium'), 'Owner Toko 2 CANNOT see Store 1 Deterjen (Isolation)');

// Owner Platform (user_id=1) sees both stores
$resPlatform = $bot->chat('pengeluaran 500.000 digunakan untuk apa?', 1, 'owner_platform');
assert_test(str_contains($resPlatform['reply'], 'CleanWash Express') && str_contains(html_entity_decode($resPlatform['reply']), 'Fresh & Soft'),
    'Owner Platform sees entries from BOTH stores');
assert_test(str_contains($resPlatform['reply'], 'Deterjen Cair Premium') && str_contains($resPlatform['reply'], 'Pelembut Pakaian'),
    'Owner Platform sees both item names');

// -----------------------------------------------------------------------------
// 3. Amount Variations
// -----------------------------------------------------------------------------
echo "\n--- 3. Amount Format Variations Tests ---\n";

foreach (['500rb', '500 ribu', '500k', 'Rp 500.000', 'Rp500000'] as $var) {
    $resVar = $bot->chat("pengeluaran {$var} untuk apa?", 3, 'owner_toko');
    assert_test(str_contains($resVar['reply'], 'Deterjen Cair Premium'), "Format '{$var}' yields correct expense item");
}

// -----------------------------------------------------------------------------
// 4. Nearest Match & No Match Tests
// -----------------------------------------------------------------------------
echo "\n--- 4. Nearest Match & Missing Amount Tests ---\n";

// Nearest match: 490.000 does not exist, should show nearest
$resNear = $bot->chat('pengeluaran 490.000 untuk apa?', 3, 'owner_toko');
assert_test(str_contains($resNear['reply'], 'terdekat') || str_contains($resNear['reply'], 'Deterjen'),
    'Nearest match fallback suggests closest expense');

// -----------------------------------------------------------------------------
// 5. Admin Toko Financial Access Control
// -----------------------------------------------------------------------------
echo "\n--- 5. Financial Access Guard Tests ---\n";

// Default setting admin_toko_can_view_finance is false/not true
$resAdminToko = $bot->chat('pengeluaran 500.000 digunakan untuk apa?', 5, 'admin_toko');
assert_test(str_contains($resAdminToko['reply'], 'hanya dapat diakses oleh owner toko'),
    'Admin Toko blocked from financial query by default');

// Customer blocked
$resCustomer = $bot->chat('pengeluaran 500.000 digunakan untuk apa?', 9, 'customer');
assert_test(!str_contains($resCustomer['reply'], 'Deterjen Cair Premium') && !str_contains($resCustomer['reply'], 'Pelembut Pakaian'),
    'Customer blocked from reading financial expense records');

// Kurir blocked
$resKurir = $bot->chat('pengeluaran 500.000 digunakan untuk apa?', 7, 'kurir');
assert_test(!str_contains($resKurir['reply'], 'Deterjen Cair Premium'),
    'Kurir blocked from reading financial expense records');

// -----------------------------------------------------------------------------
// 6. Cross-table Query & Aggregation
// -----------------------------------------------------------------------------
echo "\n--- 6. Cross-table & Aggregation Tests ---\n";

// Aggregate expenses
$resAgg = $bot->chat('total pengeluaran bulan ini', 3, 'owner_toko');
assert_test($resAgg['intent'] === 'DATA_EXPENSE_AGGREGATE', 'Intent is DATA_EXPENSE_AGGREGATE');
assert_test(str_contains($resAgg['reply'], 'Rekap pengeluaran') && str_contains($resAgg['reply'], 'Total:'),
    'Expense aggregate includes breakdown and total');

// Cross-table: Payment lookup
$resPayment = $bot->chat('pembayaran Rp 75000 itu order apa', 3, 'owner_toko');
assert_test($resPayment['intent'] === 'DATA_PAYMENT_CROSS_TABLE', 'Payment lookup intent is DATA_PAYMENT_CROSS_TABLE');
assert_test(str_contains($resPayment['reply'], 'LND-') && str_contains($resPayment['reply'], 'CleanWash Express'),
    'Payment lookup cross-references order code and store name');

// -----------------------------------------------------------------------------
// 7. Security & Schema Whitelist Enforcement
// -----------------------------------------------------------------------------
echo "\n--- 7. Security & Schema Whitelist Tests ---\n";

$engine = new ChatbotQueryEngine();

// Injection attempt: invalid entity
$invalidEntityBlocked = false;
try {
    $engine->execute([
        'entity'  => 'secret_passwords',
        'columns' => ['id'],
        'filters' => []
    ], 1, 'owner_platform');
} catch (\InvalidArgumentException $e) {
    $invalidEntityBlocked = true;
}
assert_test($invalidEntityBlocked, 'Disallowed entity is rejected by ChatbotQueryEngine');

// Injection attempt: column not in whitelist
$invalidColumnBlocked = false;
try {
    $engine->execute([
        'entity'  => 'expenses',
        'columns' => ['password'],
        'filters' => []
    ], 1, 'owner_platform');
} catch (\InvalidArgumentException $e) {
    $invalidColumnBlocked = true;
}
assert_test($invalidColumnBlocked, 'Unlisted column (password) is rejected');

// Injection attempt: operator injection
$invalidOpBlocked = false;
try {
    $engine->execute([
        'entity'  => 'expenses',
        'columns' => ['total_amount'],
        'filters' => [['col' => 'total_amount', 'op' => '; DROP TABLE users;--', 'val' => 500000]]
    ], 1, 'owner_platform');
} catch (\InvalidArgumentException $e) {
    $invalidOpBlocked = true;
}
assert_test($invalidOpBlocked, 'Malicious operator injection rejected');

// PII Masking test
$maskQuery = $engine->execute([
    'entity'  => 'orders',
    'joins'   => ['orders->users'],
    'columns' => ['id', 'order_code'],
    'extra_select' => ['`users`.`phone` AS `customer_phone`', '`users`.`email` AS `customer_email`'],
    'limit'   => 1
], 1, 'owner_platform');
$firstRow = $maskQuery['rows'][0] ?? [];
$phoneMasked = isset($firstRow['customer_phone']) && str_contains($firstRow['customer_phone'], '*');
$emailMasked = isset($firstRow['customer_email']) && str_contains($firstRow['customer_email'], '*');
assert_test($phoneMasked, 'Customer phone is automatically masked with asterisks');
assert_test($emailMasked, 'Customer email is automatically masked with asterisks');

// Logging test
$logsCount = (int)$db->query("SELECT COUNT(*) FROM `chatbot_data_access_logs` WHERE `tool` LIKE 'query_engine:%'")->fetchColumn();
assert_test($logsCount > 0, "Query engine executions logged to chatbot_data_access_logs (Total logs: {$logsCount})");

echo "\n====================================================================\n";
echo "STAGE 19 SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
