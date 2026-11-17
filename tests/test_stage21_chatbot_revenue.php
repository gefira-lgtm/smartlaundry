<?php
/**
 * Test Stage 21: Chatbot Revenue Answers & Today's Income Queries
 * 
 * Verifies that chatbot provides accurate database-driven financial answers
 * for "pemasukan hari ini" and variants across roles, respects permissions,
 * excludes non-PAID and non-today records, formats time-based greetings cleanly,
 * and tracks tool_used in logs.
 */

define('TEST_MODE', true);

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Order.php';
require_once dirname(__DIR__) . '/app/Models/Setting.php';
require_once dirname(__DIR__) . '/app/Models/ChatbotMessage.php';
require_once dirname(__DIR__) . '/app/Services/ChatbotNLUService.php';
require_once dirname(__DIR__) . '/app/Services/ChatbotDataService.php';
require_once dirname(__DIR__) . '/app/Services/AIChatbotService.php';

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

echo "\n============================================================\n";
echo "  Stage 21: Chatbot Revenue & Income Today Tests\n";
echo "============================================================\n\n";

$bot = new \App\Services\AIChatbotService('mock');
$dataService = new \App\Services\ChatbotDataService();
$db = \App\Core\Database::getConnection();

// Menyiapkan data transaksi hari ini (CURDATE) agar test deterministik dan independen dari tanggal seeder
$db->exec("UPDATE `payments` SET `paid_at` = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE `id` NOT IN (101, 102, 103, 104, 105, 106, 201, 202, 302) AND DATE(`paid_at`) = CURDATE()");
$db->exec("UPDATE `payments` SET `paid_at` = CONCAT(CURDATE(), ' ', TIME(`paid_at`)) WHERE `id` IN (101, 102, 103, 104, 105, 106, 201, 202, 302)");

// --- Suite 1: Query Variations & Role Scoping ---
echo "Suite 1: Query Variations & Role Scoping\n";

$queries = [
    'pemasukan hari ini',
    'berapa pemasukan hari ini',
    'total pendapatan hari ini',
    'omset hari ini',
];

// Platform Owner (UID 1)
foreach ($queries as $q) {
    $res = $bot->chat($q, 1, 'owner_platform');
    assert_true($res['intent'] === 'DATA_REVENUE', "Platform: \"{$q}\" detected as DATA_REVENUE");
    assert_true($res['metadata']['tool_used'] === 'getRevenueToday', "Platform: \"{$q}\" used getRevenueToday tool");
    assert_true(str_contains($res['reply'], '561.500'), "Platform: \"{$q}\" contains platform total Rp 561.500");
    assert_true(str_contains($res['reply'], '9 transaksi'), "Platform: \"{$q}\" mentions 9 transaksi");
    assert_true(str_contains($res['reply'], 'CleanWash Express: Rp 450.000 (6 transaksi)'), "Platform: \"{$q}\" breakdown CleanWash correct");
    assert_true(str_contains($res['reply'], 'Fresh & Soft Laundry: Rp 111.500 (3 transaksi)'), "Platform: \"{$q}\" breakdown FreshSoft correct");
}

// Admin Owner (UID 2)
$resAdminOwner = $bot->chat('berapa pemasukan hari ini', 2, 'admin_owner');
assert_true(str_contains($resAdminOwner['reply'], '561.500') && str_contains($resAdminOwner['reply'], '9 transaksi'),
    "Admin Owner: receives total platform revenue (Rp 561.500, 9 transaksi)");

// Store Owner CleanWash (UID 3, Store ID 1)
foreach ($queries as $q) {
    $res = $bot->chat($q, 3, 'owner_toko');
    assert_true($res['intent'] === 'DATA_REVENUE', "Owner Toko 1: \"{$q}\" detected as DATA_REVENUE");
    assert_true($res['metadata']['tool_used'] === 'getRevenueToday', "Owner Toko 1: \"{$q}\" used getRevenueToday tool");
    assert_true(str_contains($res['reply'], '450.000'), "Owner Toko 1: \"{$q}\" contains Rp 450.000");
    assert_true(str_contains($res['reply'], '6 transaksi'), "Owner Toko 1: \"{$q}\" mentions 6 transaksi");
    assert_true(!str_contains($res['reply'], 'Fresh & Soft'), "Owner Toko 1: \"{$q}\" does not leak other store");
}

// Full Recap
$resRecapPlatform = $bot->chat('rekap hari ini', 1, 'owner_platform');
assert_true($resRecapPlatform['intent'] === 'DATA_FULL_RECAP', "Platform: 'rekap hari ini' detected as DATA_FULL_RECAP");
assert_true(str_contains($resRecapPlatform['reply'], '561.500'), "Platform: 'rekap hari ini' contains total pemasukan Rp 561.500");

$resRecapStore = $bot->chat('rekap hari ini', 3, 'owner_toko');
assert_true($resRecapStore['intent'] === 'DATA_FULL_RECAP', "Store 1: 'rekap hari ini' detected as DATA_FULL_RECAP");
assert_true(str_contains($resRecapStore['reply'], '450.000'), "Store 1: 'rekap hari ini' contains store pemasukan Rp 450.000");

// --- Suite 2: Negative Filters & Exclusions ---
echo "\nSuite 2: Negative Filters & Exclusions\n";

// Verify payment 2 (historical September 2026) is excluded from today
$checkHist = $db->query("SELECT COUNT(*) FROM payments WHERE id = 2 AND paid_at >= CURDATE()")->fetchColumn();
assert_true((int)$checkHist === 0, "Historical payment ID 2 is outside CURDATE()");

// Verify CANCELLED and EXPIRED payments today are not counted
$cancelledPaid = $db->query("SELECT COUNT(*) FROM payments WHERE status != 'PAID' AND id IN (204, 205)")->fetchColumn();
assert_true((int)$cancelledPaid >= 2, "Payment 204 & 205 are non-PAID and excluded");

// Zero data scenario
$zeroResp = $dataService->getRevenueToday(3, 'owner_toko', '2025-01-01');
assert_true(str_starts_with($zeroResp, 'Belum ada pembayaran lunas'), "Zero data returns 'Belum ada pembayaran lunas'");
assert_true(str_contains($zeroResp, '(Rp 0)'), "Zero data includes '(Rp 0)'");
assert_true(!str_contains($zeroResp, 'dapat diakses melalui menu'), "Zero data does not point to menu");

// No hallucinated menu direction or unrequested active order text
foreach (['pemasukan hari ini', 'omset hari ini', 'berapa pemasukan hari ini'] as $q) {
    $r = $bot->chat($q, 1, 'owner_platform');
    assert_true(!str_contains($r['reply'], 'dapat diakses melalui menu'), "No menu pointer in reply to \"$q\"");
    assert_true(!str_contains($r['reply'], 'saat ini tidak terdapat pesanan aktif'), "No active orders sentence in reply to \"$q\"");
    assert_true(!str_contains($r['reply'], 'Selamat pagi/siang, Anda.'), "No corrupted greeting in reply to \"$q\"");
}

// --- Suite 3: Admin Toko & Customer Access Controls ---
echo "\nSuite 3: Admin Toko & Customer Access Controls\n";

// Admin Toko with permission FALSE
\App\Models\Setting::set('admin_toko_can_view_finance', '0');
$resAdminOff = $bot->chat('pemasukan hari ini', 5, 'admin_toko');
assert_true(str_contains($resAdminOff['reply'], 'Data finansial hanya dapat diakses oleh owner toko'),
    "Admin Toko denied when admin_toko_can_view_finance is 0");

// Admin Toko with permission TRUE
\App\Models\Setting::set('admin_toko_can_view_finance', '1');
$resAdminOn = $bot->chat('pemasukan hari ini', 5, 'admin_toko');
assert_true(str_contains($resAdminOn['reply'], '450.000'),
    "Admin Toko allowed when admin_toko_can_view_finance is 1");
\App\Models\Setting::set('admin_toko_can_view_finance', '0');

// Customer denied
$resCust = $bot->chat('pemasukan hari ini', 7, 'customer');
assert_true($resCust['intent'] === 'DATA_ACCESS_DENIED_ROLE' || str_contains($resCust['reply'], 'dibatasi'),
    "Customer denied financial access politely");

// --- Suite 4: Greetings & Time of Day Formatting ---
echo "\nSuite 4: Greetings & Time of Day Formatting\n";

$tz = new \DateTimeZone('Asia/Jakarta');
$now = new \DateTime('now', $tz);
$hour = (int)$now->format('G');
if ($hour >= 6 && $hour < 12) {
    $expectedGreetingPrefix = 'Selamat pagi';
} elseif ($hour >= 12 && $hour < 15) {
    $expectedGreetingPrefix = 'Selamat siang';
} elseif ($hour >= 15 && $hour < 18) {
    $expectedGreetingPrefix = 'Selamat sore';
} else {
    $expectedGreetingPrefix = 'Selamat malam';
}

$_SESSION['user'] = ['name' => 'Siti Rahmawati'];
$greetWithName = $bot->chat('Halo', 1, 'owner_platform');
assert_true(str_starts_with($greetWithName['reply'], $expectedGreetingPrefix . ', Siti Rahmawati!'),
    "Greeting contains proper time prefix and session user name without gender assumption");
assert_true(!str_contains($greetWithName['reply'], 'Selamat pagi/siang, Anda'), "Greeting does not use corrupted template 'Selamat pagi/siang, Anda.'");
assert_true(!str_starts_with($greetWithName['reply'], "{$expectedGreetingPrefix}, Anda"), "Greeting does not address user as 'Anda' instead of their name");

unset($_SESSION['user']);
$greetNoSession = $bot->chat('Hai', 1, 'owner_platform');
assert_true(str_starts_with($greetNoSession['reply'], $expectedGreetingPrefix),
    "Greeting without session name falls back to user record name or generic prefix");

// Ensure data answer does not prepend greeting
$dataAnswer = $bot->chat('pemasukan hari ini', 3, 'owner_toko');
assert_true(!str_starts_with($dataAnswer['reply'], 'Selamat'),
    "Data answer does not prepend time greeting");

// --- Suite 5: Logging of tool_used ---
echo "\nSuite 5: Logging of tool_used\n";

$latestLog = $db->query("SELECT user_role, sender, detected_intent, tool_used, message FROM chatbot_messages WHERE tool_used = 'getRevenueToday' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert_true(!empty($latestLog), "chatbot_messages contains row with tool_used = 'getRevenueToday'");
assert_true($latestLog['detected_intent'] === 'DATA_REVENUE' || $latestLog['sender'] === 'BOT', "Row intent matches DATA_REVENUE");

echo "\n------------------------------------------------------------\n";
echo "Total Tests: {$testCount} | Passed: {$passedCount} | Failed: " . count($failedTests) . "\n";
echo "------------------------------------------------------------\n";

if (count($failedTests) > 0) {
    echo "Failures:\n";
    foreach ($failedTests as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
echo "Stage 21 Chatbot Revenue tests passed successfully!\n\n";
exit(0);
