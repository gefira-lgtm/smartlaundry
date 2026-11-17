<?php
require_once __DIR__ . "/../config/env.php";
define("TEST_MODE", true);
load_env(__DIR__ . "/../.env");
require_once __DIR__ . "/../config/app.php";

require_once ROOT_PATH . "/app/Core/Model.php";
require_once ROOT_PATH . "/app/Core/Database.php";
require_once ROOT_PATH . "/app/Core/Controller.php";
require_once ROOT_PATH . "/app/Core/Request.php";
require_once ROOT_PATH . "/app/Core/Response.php";

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
use App\Models\Order;
use App\Models\User;

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = "") {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name} - {$detail}\n";
        $failed++;
    }
}

echo "=== SMARTLAUNDRY TAHAP 13: AI CHATBOT COURIER TEST SUITE ===\n\n";

$service = new AIChatbotService();
$orderModel = new Order();
$userModel = new User();

// Setup courier and customer accounts for testing
$couriers = $userModel->query("SELECT u.* FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = \"kurir\" ORDER BY u.id ASC LIMIT 2");
if (count($couriers) < 2) {
    die("Need at least 2 courier accounts for testing.\n");
}
$courierA = $couriers[0];
$courierB = $couriers[1];
$userIdA = (int)$courierA["id"];
$userIdB = (int)$courierB["id"];

echo "Testing with Courier A (ID: {$userIdA}) and Courier B (ID: {$userIdB})\n\n";

// 1. Test Courier FAQ Intents
$chat1 = $service->chat("Gimana cara terima tugas tawaran?", $userIdA, "kurir");
assert_test("Courier FAQ - Accept Task", $chat1["intent"] === "COURIER_FAQ_ACCEPT_TASK" && strpos($chat1["reply"], "Menerima / Menolak Tugas") !== false, "Reply: " . substr($chat1["reply"], 0, 50));

$chat2 = $service->chat("mulai otw jemput customer gimana", $userIdA, "kurir");
assert_test("Courier FAQ - Status Flow", $chat2["intent"] === "COURIER_FAQ_STATUS_FLOW" && strpos($chat2["reply"], "Alur Status Tugas Kurir") !== false, "Reply: " . substr($chat2["reply"], 0, 50));

$chat3 = $service->chat("Kenapa harus update lokasi gps", $userIdA, "kurir");
assert_test("Courier FAQ - GPS", $chat3["intent"] === "COURIER_FAQ_GPS" && strpos($chat3["reply"], "Pentingnya Update Lokasi (GPS)") !== false, "Reply: " . substr($chat3["reply"], 0, 50));

$chat4 = $service->chat("Status ketersediaan online offline tersedia", $userIdA, "kurir");
assert_test("Courier FAQ - Availability", $chat4["intent"] === "COURIER_FAQ_AVAILABILITY" && strpos($chat4["reply"], "Status Ketersediaan") !== false, "Reply: " . substr($chat4["reply"], 0, 50));

$chat5 = $service->chat("Tugas pengembalian refund bedanya apa", $userIdA, "kurir");
assert_test("Courier FAQ - Refund", $chat5["intent"] === "COURIER_FAQ_REFUND" && strpos($chat5["reply"], "Tugas Pengembalian") !== false, "Reply: " . substr($chat5["reply"], 0, 50));

// 2. Test Customer intent blocking for Courier
$chatBlock = $service->chat("Berapa harga daftar layanan cuci kiloan?", $userIdA, "kurir");
assert_test("Courier blocked from Customer FAQ", strpos($chatBlock["reply"], "di luar cakupan peran Anda sebagai Kurir") !== false, "Reply: " . substr($chatBlock["reply"], 0, 50));

// 3. Test Courier intent blocking for Customer
$chatBlockC = $service->chat("Gimana cara terima tugas", $userIdA, "customer");
assert_test("Customer blocked from Courier FAQ", strpos($chatBlockC["reply"], "khusus untuk mitra Kurir") !== false, "Reply: " . substr($chatBlockC["reply"], 0, 50));

// 4. Test Security Ownership check on specific task
// Create an order for courier A
$randomCode = "LND-" . date("Ymd") . "-" . rand(1000, 9999);
$orderIdA = $orderModel->create([
    "order_code" => $randomCode,
    "customer_id" => 1,
    "store_id" => 1,
    "pickup_address_id" => 1,
    "status" => "KURIR_MENUJU_LOKASI",
    "payment_method" => "ONLINE_QRIS",
    "total_price" => 25000.00,
    "created_at" => date("Y-m-d H:i:s"),
    "updated_at" => date("Y-m-d H:i:s"),
    "courier_id" => $userIdA
]);

$chatT1 = $service->chat("Tolong cek " . $randomCode, $userIdA, "kurir");
assert_test("Courier A can check their own task", $chatT1["intent"] === "COURIER_ACTIVE_TASKS" && strpos($chatT1["reply"], $randomCode) !== false, "Reply: " . substr($chatT1["reply"], 0, 50));

$chatT2 = $service->chat("Tolong cek " . $randomCode, $userIdB, "kurir");
assert_test("Courier B blocked from Courier A task", $chatT2["intent"] === "COURIER_ACTIVE_TASKS" && strpos($chatT2["reply"], "Akses Dibatasi") !== false, "Reply: " . substr($chatT2["reply"], 0, 50));

echo "\n=======================================================\n";
echo "STAGE 13 COURIER TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}

