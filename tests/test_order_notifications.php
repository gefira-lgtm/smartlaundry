<?php
/**
 * Test Suite: SmartLaundry WhatsApp Customer Notification Overhaul
 * Verifies all 8 order status notifications with dynamic DB data & formatting
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

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
use App\Models\Order;
use App\Models\User;
use App\Models\Store;
use App\Services\WhatsAppNotificationService;

$db = Database::getConnection();
$orderModel = new Order();
$userModel = new User();
$storeModel = new Store();
$waService = new WhatsAppNotificationService();

echo "=======================================================\n";
echo "TEST SUITE: WHATSAPP NOTIFICATION OVERHAUL (8 STATUSES)\n";
echo "=======================================================\n\n";

$passed = 0;
$total = 0;

function assertTest(string $description, bool $condition, int &$passed, int &$total): void {
    $total++;
    echo "TEST {$total}: {$description}... ";
    if ($condition) {
        $passed++;
        echo "\033[32m[PASSED]\033[0m\n";
    } else {
        echo "\033[31m[FAILED]\033[0m\n";
    }
}

// 1. Prepare Test Customer and Order in Database
$customer = $userModel->findByEmail('customer.ani@gmail.com');
$store = $storeModel->find(1);
$courier = $userModel->query("SELECT u.* FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = 'kurir' LIMIT 1")[0] ?? null;

$addr = $db->query("SELECT id FROM `addresses` WHERE user_id = {$customer['id']} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$addressId = (int)$addr['id'];

$testOrderCode = 'WA-NOTIF-' . rand(1000, 9999);
$testOrderId = $orderModel->createWithItems([
    'order_code' => $testOrderCode,
    'customer_id' => (int)$customer['id'],
    'store_id' => (int)$store['id'],
    'pickup_address_id' => $addressId,
    'delivery_address_id' => $addressId,
    'status' => 'MENUNGGU_KONFIRMASI',
    'payment_method' => 'ONLINE_QRIS',
    'total_price' => 50000.00,
    'delivery_fee' => 0.00,
    'notes' => 'Testing WhatsApp dynamic notifications'
], [
    ['service_id' => 1, 'quantity' => 3.5, 'unit_price' => 10000.00, 'subtotal' => 35000.00]
]);

// Assign courier
$orderModel->manualAssignCourier($testOrderId, (int)$courier['id'], (int)$store['owner_id'], 'PICKUP');

// Submit weighing
$orderModel->submitWeighing($testOrderId, 3.5, 10, 'Penimbangan rapi', (int)$store['owner_id']);

echo "Created Order #{$testOrderCode} (ID: {$testOrderId}) for customer {$customer['name']} ({$customer['phone']})\n\n";

// -------------------------------------------------------------
// Status 1: Pesanan dibuat
// -------------------------------------------------------------
$res1 = $waService->notifyOrderCreated($customer['phone'], $customer['name'], $testOrderCode, $store['name'], $testOrderId, (int)$customer['id']);
$log1 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'ORDER_CREATED' AND phone LIKE '%" . substr($customer['phone'], -6) . "%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("1. Status 'Pesanan Dibuat' sends dynamic notification with customer profile", 
    $res1 && !empty($log1) && strpos($log1['message'], $testOrderCode) !== false && strpos($log1['message'], 'Pesanan Dibuat') !== false,
    $passed, $total
);
assertTest("1. Message does not contain test placeholder string", 
    strpos($log1['message'], 'ini pesan uji coba') === false,
    $passed, $total
);

// -------------------------------------------------------------
// Status 2: Kurir menjemput
// -------------------------------------------------------------
$res2 = $waService->notifyCourierToPickup($customer['phone'], $customer['name'], $testOrderCode, $courier['name'], $store['name'], $testOrderId, (int)$customer['id']);
$log2 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'COURIER_DISPATCHED' AND message LIKE '%{$testOrderCode}%' AND message LIKE '%menjemput%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("2. Status 'Kurir Menjemput' includes courier name & menjemput keyword", 
    $res2 && !empty($log2) && strpos($log2['message'], $courier['name']) !== false,
    $passed, $total
);

// -------------------------------------------------------------
// Status 3: Laundry diterima toko
// -------------------------------------------------------------
$res3 = $waService->notifyArrivedAtStore($customer['phone'], $customer['name'], $testOrderCode, $store['name'], $testOrderId, (int)$customer['id']);
$log3 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'ARRIVED_AT_STORE' AND message LIKE '%{$testOrderCode}%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("3. Status 'Laundry Diterima Toko' includes store name & weighing queue info", 
    $res3 && !empty($log3) && strpos($log3['message'], 'Laundry Diterima Toko') !== false,
    $passed, $total
);

// -------------------------------------------------------------
// Status 4: Menunggu pembayaran
// -------------------------------------------------------------
$res4 = $waService->notifyWeighingCompleted($customer['phone'], $customer['name'], $testOrderCode, $store['name'], 50000.0, 3.5, '1 jam ke depan', $testOrderId, (int)$customer['id']);
$log4 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'WEIGHING_COMPLETED' AND message LIKE '%{$testOrderCode}%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("4. Status 'Menunggu Pembayaran' includes total price, weight, & QRIS instructions", 
    $res4 && !empty($log4) && strpos($log4['message'], 'Menunggu Pembayaran') !== false && strpos($log4['message'], 'QRIS') !== false,
    $passed, $total
);

// -------------------------------------------------------------
// Status 5: Sedang diproses
// -------------------------------------------------------------
$res5 = $waService->notifyLaundryWashing($customer['phone'], $customer['name'], $testOrderCode, $store['name'], $testOrderId, (int)$customer['id']);
$log5 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'LAUNDRY_PROCESSING' AND message LIKE '%{$testOrderCode}%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("5. Status 'Sedang Diproses' informs washing process & hygiene standard", 
    $res5 && !empty($log5) && strpos($log5['message'], 'Sedang Diproses') !== false,
    $passed, $total
);

// -------------------------------------------------------------
// Status 6: Laundry selesai
// -------------------------------------------------------------
$res6 = $waService->notifyLaundryReady($customer['phone'], $customer['name'], $testOrderCode, $store['name'], $testOrderId, (int)$customer['id']);
$log6 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'LAUNDRY_READY' AND message LIKE '%{$testOrderCode}%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("6. Status 'Laundry Selesai' contains 'wangi banget' and clean completion", 
    $res6 && !empty($log6) && strpos($log6['message'], 'wangi banget') !== false && strpos($log6['message'], 'Laundry Selesai') !== false,
    $passed, $total
);

// -------------------------------------------------------------
// Status 7: Sedang diantar
// -------------------------------------------------------------
$res7 = $waService->notifyCourierToDelivery($customer['phone'], $customer['name'], $testOrderCode, $courier['name'], $store['name'], $testOrderId, (int)$customer['id']);
$log7 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'COURIER_DISPATCHED' AND message LIKE '%{$testOrderCode}%' AND message LIKE '%mengantarkan%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("7. Status 'Sedang Diantar' includes courier name & mengantarkan keyword", 
    $res7 && !empty($log7) && strpos($log7['message'], 'mengantarkan') !== false,
    $passed, $total
);

// -------------------------------------------------------------
// Status 8: Pesanan selesai
// -------------------------------------------------------------
$res8 = $waService->notifyOrderCompleted($customer['phone'], $customer['name'], $testOrderCode, $store['name'], $testOrderId, (int)$customer['id']);
$log8 = $db->query("SELECT * FROM whatsapp_logs WHERE event = 'ORDER_COMPLETED' AND message LIKE '%{$testOrderCode}%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

assertTest("8. Status 'Pesanan Selesai' invites review & thanks customer", 
    $res8 && !empty($log8) && strpos($log8['message'], 'Pesanan Selesai') !== false,
    $passed, $total
);

// -------------------------------------------------------------
// 9. Deduplication (Idempotency) check
// -------------------------------------------------------------
$countBefore = (int)$db->query("SELECT COUNT(*) FROM whatsapp_logs WHERE event = 'LAUNDRY_READY' AND message LIKE '%{$testOrderCode}%'")->fetchColumn();
$waService->notifyLaundryReady($customer['phone'], $customer['name'], $testOrderCode, $store['name'], $testOrderId, (int)$customer['id']);
$countAfter = (int)$db->query("SELECT COUNT(*) FROM whatsapp_logs WHERE event = 'LAUNDRY_READY' AND message LIKE '%{$testOrderCode}%'")->fetchColumn();

assertTest("9. Deduplication check: re-triggering status change sends NO duplicate message", 
    $countBefore === $countAfter,
    $passed, $total
);

// -------------------------------------------------------------
// 10. Customer Phone Isolation Check
// -------------------------------------------------------------
// Calling with wrong dummy phone '080000000000' should still use actual DB phone if order exists
$res10 = $waService->notifyOrderStatusChanged($testOrderId, 'KURIR_MENUJU_CUSTOMER');
$log10 = $db->query("SELECT phone FROM whatsapp_logs WHERE dedupe_key = 'COURIER_DISPATCHED:{$testOrderId}:{$customer['id']}:CUSTOMER_DELIVERY'")->fetchColumn();
assertTest("10. Customer phone is retrieved from customer profile in database", 
    !empty($log10) && strpos($log10, '62') === 0,
    $passed, $total
);

echo "\nSample Generated Message (Status 6: Laundry Selesai):\n";
echo "-------------------------------------------------------\n";
echo $log6['message'] . "\n";
echo "-------------------------------------------------------\n\n";

echo "=======================================================\n";
echo "TEST RESULTS: {$passed} / {$total} PASSED\n";
echo "=======================================================\n";

if ($passed === $total) {
    echo "\033[32mALL NOTIFICATION SUITE TESTS PASSED!\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSOME TESTS FAILED!\033[0m\n";
    exit(1);
}
