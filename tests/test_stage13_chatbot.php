<?php
/**
 * SmartLaundry - Stage 13 Test Suite: AI Chatbot Assistant
 */

require_once __DIR__ . '/../config/env.php';
define('TEST_MODE', true);
load_env(__DIR__ . '/../.env');
require_once __DIR__ . '/../config/app.php';

require_once ROOT_PATH . '/app/Core/Model.php';
require_once ROOT_PATH . '/app/Core/Database.php';
require_once ROOT_PATH . '/app/Core/Controller.php';
require_once ROOT_PATH . '/app/Core/Request.php';
require_once ROOT_PATH . '/app/Core/Response.php';

// Autoload models and services
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Services\AIChatbotService;
use App\Controllers\ChatbotController;
use App\Models\Order;
use App\Models\User;

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name} - {$detail}\n";
        $failed++;
    }
}

echo "=== SMARTLAUNDRY TAHAP 13: AI CHATBOT ASSISTANT TEST SUITE ===\n\n";

$service = new AIChatbotService();
$orderModel = new Order();
$userModel = new User();

// Setup customer accounts for testing
$customers = $userModel->query("SELECT u.* FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = 'customer' ORDER BY u.id ASC LIMIT 2");
if (count($customers) < 2) {
    die("Need at least 2 customer accounts for ownership testing.\n");
}
$customerA = $customers[0];
$customerB = $customers[1];
$userIdA = (int)$customerA['id'];
$userIdB = (int)$customerB['id'];

echo "Testing with Customer A (ID: {$userIdA}, {$customerA['name']}) and Customer B (ID: {$userIdB}, {$customerB['name']})\n\n";

// 1. Test FAQ Intent: Greetings
$chat1 = $service->chat("Halo selamat pagi SmartBot!", $userIdA);
assert_test("FAQ - Greeting", $chat1['intent'] === 'GREETING' && strpos($chat1['reply'], 'asisten resmi SmartLaundry') !== false, "Reply: " . substr($chat1['reply'], 0, 50));

// 2. Test FAQ Intent: Payment
$chat2 = $service->chat("Bagaimana metode pembayaran QRIS dan apakah ada batas waktu?", $userIdA);
assert_test("FAQ - Payment Info", $chat2['intent'] === 'FAQ_PAYMENT' && strpos($chat2['reply'], 'QRIS') !== false, "Reply: " . substr($chat2['reply'], 0, 50));

// 3. Test FAQ Intent: Order Flow
$chat3 = $service->chat("Gimana alur pemesanan laundry di web ini?", $userIdA);
assert_test("FAQ - Order Flow", $chat3['intent'] === 'FAQ_ORDER_FLOW' && strpos($chat3['reply'], 'Pilih Toko') !== false, "Reply: " . substr($chat3['reply'], 0, 50));

// 4. Test FAQ Intent: Refund & Return
$chat4 = $service->chat("Kalau pembayaran kadaluarsa bajunya gimana pengembalian refund?", $userIdA);
assert_test("FAQ - Refund / Expired Return", $chat4['intent'] === 'FAQ_REFUND' && strpos($chat4['reply'], 'PEMBAYARAN_KADALUARSA') !== false, "Reply: " . substr($chat4['reply'], 0, 50));

// 5. Test FAQ Intent: Contact CS
$chat5 = $service->chat("Minta kontak whatsapp customer service bantuan", $userIdA);
assert_test("FAQ - Contact Support", $chat5['intent'] === 'FAQ_CONTACT' && strpos($chat5['reply'], 'WhatsApp CS') !== false, "Reply: " . substr($chat5['reply'], 0, 50));

// 6. Test Services & Price Inquiry
$chat6 = $service->chat("Berapa daftar harga cuci kiloan dan satuan?", $userIdA);
assert_test("Service & Price Query", $chat6['intent'] === 'SERVICE_PRICE' && (strpos($chat6['reply'], 'Rp') !== false || strpos($chat6['reply'], 'Tarif') !== false), "Reply: " . substr($chat6['reply'], 0, 50));

// 6a. Test New Intents: Identity
$chatId = $service->chat("Sebenarnya kamu ini siapa sih? Kamu manusia atau robot?", $userIdA);
assert_test("Identity Intent", $chatId['intent'] === 'BOT_IDENTITY' && strpos($chatId['reply'], 'asisten resmi SmartLaundry') !== false, "Reply: " . substr($chatId['reply'], 0, 50));

// 6b. Test New Intents: Capability
$chatCap = $service->chat("Kamu bisa bantu apa aja?", $userIdA);
assert_test("Capability Intent", $chatCap['intent'] === 'BOT_CAPABILITY' && strpos($chatCap['reply'], 'saya bisa membantu Anda') !== false, "Reply: " . substr($chatCap['reply'], 0, 50));

// 6c. Test New Intents: Thanks
$chatThx = $service->chat("Terima kasih banyak ya", $userIdA);
assert_test("Thanks Intent", $chatThx['intent'] === 'THANKS' && strpos($chatThx['reply'], 'Sama-sama') !== false, "Reply: " . substr($chatThx['reply'], 0, 50));

// 6d. Test New Intents: Farewell
$chatBye = $service->chat("Oke sudah dulu, sampai jumpa!", $userIdA);
assert_test("Farewell Intent", $chatBye['intent'] === 'FAREWELL' && strpos($chatBye['reply'], 'Sampai jumpa') !== false, "Reply: " . substr($chatBye['reply'], 0, 50));

// 6e. Test Fallback Intent (GENERAL)
$chatGen = $service->chat("abrakadabra 123", $userIdA);
assert_test("Fallback Intent", $chatGen['intent'] === 'GENERAL' && strpos($chatGen['reply'], 'Maaf, saya belum memahami') !== false, "Reply: " . substr($chatGen['reply'], 0, 50));

// --- Test Revisi Identitas & Gaya Bahasa Formal ---
echo "\nTesting Identity Intent & Formal Tone Standardization...\n";

$identityPhrasesFormal = [
    "siapa anda",
    "anda siapa",
    "anda itu siapa",
    "siapakah anda",
    "anda ini apa",
    "anda bot atau manusia",
    "anda manusia atau robot",
    "ini siapa",
    "Siapa anda?",
    "SIAPA ANDA",
    "siapa   anda ?"
];

$identityPhrasesInformal = [
    "kamu siapa",
    "siapa kamu",
    "kamu itu apa",
    "nama kamu siapa",
    "kamu bot ya"
];

$allIdentityPhrases = array_merge($identityPhrasesFormal, $identityPhrasesInformal);

foreach ($allIdentityPhrases as $phrase) {
    // Test customer role
    $resCust = $service->chat($phrase, $userIdA, 'customer');
    assert_test(
        "Identity phrase customer: '{$phrase}'",
        $resCust['intent'] === 'BOT_IDENTITY' &&
        strpos($resCust['reply'], 'asisten resmi SmartLaundry') !== false &&
        stripos($resCust['reply'], 'kamu') === false &&
        preg_match('/\bAI\b/', $resCust['reply']) === 0,
        "Reply: " . $resCust['reply']
    );

    // Test kurir role
    $resKurir = $service->chat($phrase, $userIdA, 'kurir');
    assert_test(
        "Identity phrase kurir: '{$phrase}'",
        $resKurir['intent'] === 'BOT_IDENTITY' &&
        strpos($resKurir['reply'], 'asisten resmi SmartLaundry') !== false &&
        stripos($resKurir['reply'], 'kamu') === false &&
        preg_match('/\bAI\b/', $resKurir['reply']) === 0,
        "Reply: " . $resKurir['reply']
    );
}

// Test Role-specific capabilities in identity answer
$custIdentity = $service->chat("siapa anda?", $userIdA, 'customer');
assert_test(
    "Customer identity capabilities list",
    strpos($custIdentity['reply'], 'cara memesan') !== false &&
    strpos($custIdentity['reply'], 'metode pembayaran') !== false &&
    strpos($custIdentity['reply'], 'info layanan dan harga') !== false &&
    strpos($custIdentity['reply'], 'status pesanan milik sendiri') !== false
);

$kurirIdentity = $service->chat("siapa anda?", $userIdA, 'kurir');
assert_test(
    "Courier identity capabilities list",
    strpos($kurirIdentity['reply'], 'tugas') !== false &&
    strpos($kurirIdentity['reply'], 'alur status') !== false &&
    strpos($kurirIdentity['reply'], 'update lokasi') !== false &&
    strpos($kurirIdentity['reply'], 'ketersediaan') !== false
);

// Test Human/Bot honesty disclosure
$botQuery = $service->chat("anda bot atau manusia?", $userIdA, 'customer');
assert_test(
    "Identity honestly discloses virtual assistant and advises contacting store/admin",
    strpos($botQuery['reply'], 'asisten virtual, bukan petugas manusia') !== false &&
    strpos($botQuery['reply'], 'toko atau admin') !== false
);

// Test Formal 'Anda' across multiple other intents without 'kamu'
$intentChecks = [
    "Halo selamat siang" => 'GREETING',
    "Terima kasih banyak" => 'THANKS',
    "Sampai jumpa lagi" => 'FAREWELL',
    "Apa saja kemampuan anda?" => 'BOT_CAPABILITY',
    "xyz123randomnotfound" => 'GENERAL'
];

foreach ($intentChecks as $msg => $expectedIntent) {
    $res = $service->chat($msg, $userIdA, 'customer');
    assert_test(
        "Formal tone intent {$expectedIntent}",
        $res['intent'] === $expectedIntent &&
        stripos($res['reply'], 'kamu') === false &&
        stripos($res['reply'], 'lu ') === false &&
        stripos($res['reply'], 'kakak') === false &&
        strpos($res['reply'], 'Anda') !== false,
        "Reply: " . $res['reply']
    );
}

// 7. Order Status Testing: Find an order belonging to Customer A
$ordersA = $orderModel->where(['customer_id' => $userIdA]);
$orderA = !empty($ordersA) ? $ordersA[0] : null;
if (!$orderA) {
    // Create an order for testing
    $orderIdA = $orderModel->create([
        'order_code' => 'LND-' . date('Ymd') . '-9901',
        'customer_id' => $userIdA,
        'store_id' => 1,
        'pickup_address_id' => 1,
        'status' => 'SEDANG_DICUCI',
        'payment_method' => 'ONLINE_QRIS',
        'total_price' => 25000.00,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $orderA = $orderModel->find($orderIdA);
}


$orderCodeA = $orderA['order_code'];
echo "\nTarget Order Customer A: {$orderCodeA} (ID: {$orderA['id']})\n";

// Test 7a: Customer A asks for their own order by code
$chat7 = $service->chat("Cek status pesanan {$orderCodeA}", $userIdA);
assert_test("Order Status - Own Order Access", $chat7['intent'] === 'ORDER_STATUS' && strpos($chat7['reply'], $orderCodeA) !== false, "Reply: " . substr($chat7['reply'], 0, 80));

// Test 7b: Customer A asks generic "Status pesanan saya"
$chat8 = $service->chat("Status pesanan saya sekarang dimana ya?", $userIdA);
assert_test("Order Status - Generic Query Auto-resolve", $chat8['intent'] === 'ORDER_STATUS' && (strpos($chat8['reply'], 'Status Pesanan Anda') !== false || strpos($chat8['reply'], 'riwayat pesanan') !== false), "Reply: " . substr($chat8['reply'], 0, 80));

// Test 8: SECURITY TEST - Customer B tries to query Customer A's order
$chat9 = $service->chat("Tolong cek pesanan {$orderCodeA}", $userIdB);
assert_test("Security - Block Customer B accessing Customer A's order", 
    $chat9['intent'] === 'ORDER_STATUS' && strpos($chat9['reply'], 'Akses Dibatasi') !== false, 
    "Customer B was able to view order or unexpected response: " . $chat9['reply']
);

// 9. Rate Limiter & Controller Tests
echo "\nTesting ChatbotController Rate Limiter & Session Security...\n";

$_SESSION['user'] = [
    'id' => $userIdA,
    'name' => $customerA['name'],
    'role' => 'customer'
];
$_SESSION['_csrf_token'] = 'valid_secret_csrf_token_stage13';

// Test 9a: Invalid CSRF Token
$_POST = [
    'message' => 'Halo bot',
    '_csrf_token' => 'wrong_token'
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$controller = new ChatbotController();
$req = new App\Core\Request();
$res = new App\Core\Response();

ob_start();
$controller->sendMessage($req, $res);
$jsonOutput = ob_get_clean();
$respData = json_decode($jsonOutput, true);

assert_test("Controller - CSRF Invalid Rejected", isset($respData['success']) && $respData['success'] === false && strpos($respData['message'], 'CSRF') !== false, "Resp: " . $jsonOutput);

// Test 9b: Valid CSRF Token & Successful Message
$_POST['_csrf_token'] = 'valid_secret_csrf_token_stage13';
$_POST['message'] = 'Halo apa kabar SmartBot?';
$req = new App\Core\Request();

ob_start();
$controller->sendMessage($req, $res);
$jsonOutput = ob_get_clean();
$respData = json_decode($jsonOutput, true);

assert_test("Controller - Valid Message Success", isset($respData['success']) && $respData['success'] === true && !empty($respData['reply']), "Resp: " . $jsonOutput);

// Test 9c: Conversation History in Session
assert_test("Controller - History Stored in Session", !empty($_SESSION['chatbot_history'][$userIdA]) && count($_SESSION['chatbot_history'][$userIdA]) >= 2, "Session history count: " . count($_SESSION['chatbot_history'][$userIdA] ?? []));

// Test 10: Rate Limiter Trigger (>20 messages)
$_SESSION['chatbot_rate_limit'][$userIdA]['count'] = 20; // At limit
ob_start();
$controller->sendMessage($req, $res);
$jsonOutputRate = ob_get_clean();
$respRate = json_decode($jsonOutputRate, true);

assert_test("Security - Rate Limiter Block (>20 msg/min)", isset($respRate['success']) && $respRate['success'] === false && strpos($respRate['message'], 'maksimum') !== false, "Resp: " . $jsonOutputRate);

// Test 11: File Logging Verification
$logPath = ROOT_PATH . '/storage/logs/chatbot.log';
assert_test("Logging - Chatbot log file created", file_exists($logPath) && filesize($logPath) > 0, "Log file at: {$logPath}");

$logContent = file_get_contents($logPath);
assert_test("Logging - Contains sanitized user query and intent", strpos($logContent, "USER_ID: {$userIdA}") !== false && strpos($logContent, "GREETING") !== false, "Log content check");

echo "\n=======================================================\n";
echo "STAGE 13 TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
