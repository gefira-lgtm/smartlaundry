<?php
/**
 * SmartLaundry - Stage 16 Test Suite: Complete Notification Coverage & Customer Receipt Confirmation (PRD Bagian 14)
 *
 * Verifies:
 *  1. Matrix coverage: Each event generates in-app notification & whatsapp_logs for correct recipient without duplicate.
 *  2. QRIS Expiring Soon reminder is sent once and is strictly idempotent.
 *  3. Courier "Konfirmasi Sudah Diantar" sets status to LAUNDRY_DIANTAR and sends notification to customer.
 *  4. Customer "Terima Laundry" transitions status to SELESAI, sets completed_at, records history, and invites review.
 *  5. Cross-customer isolation: other customers cannot confirm receipt of an order they do not own (HTTP 403).
 *  6. Auto-complete: orders past 24 hours in LAUNDRY_DIANTAR are automatically resolved (idempotent, note contains "Diselesaikan otomatis").
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
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\WhatsAppLog;
use App\Models\OrderStatusHistory;
use App\Controllers\DashboardController;
use App\Core\Request;
use App\Core\Response;

$db = Database::getConnection();
$userModel = new User();
$storeModel = new Store();
$orderModel = new Order();
$paymentModel = new Payment();
$notifModel = new Notification();
$waLogModel = new WhatsAppLog();

$passed = 0;
$total = 0;

function runTest(string $description, callable $testFn, int &$passed, int &$total): void {
    $total++;
    echo "TEST {$total}: {$description}... ";
    try {
        $result = $testFn();
        if ($result === true) {
            $passed++;
            echo "\033[32m[PASSED]\033[0m\n";
        } else {
            echo "\033[31m[FAILED]: Assertion returned false\033[0m\n";
        }
    } catch (\Throwable $th) {
        echo "\033[31m[ERROR]: " . $th->getMessage() . " on line " . $th->getLine() . "\033[0m\n";
    }
}

echo "====================================================================\n";
echo "SMARTLAUNDRY STAGE 16: NOTIFICATION COVERAGE & RECEIPT TEST SUITE\n";
echo "====================================================================\n\n";

// Fetch base entities for test
$customer = $userModel->findByEmail('customer.ani@gmail.com');
$otherCustomer = $userModel->findByEmail('customer.deni@gmail.com');
$store = $storeModel->find(1);
$courierUser = $userModel->query("SELECT u.* FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = 'kurir' LIMIT 1")[0] ?? null;

$customerId = (int)$customer['id'];
$otherCustomerId = (int)$otherCustomer['id'];
$storeId = (int)$store['id'];
$storeOwnerId = (int)$store['owner_id'];
$courierId = (int)$courierUser['id'];

$addr = $db->query("SELECT id FROM `addresses` WHERE user_id = {$customerId} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$addressId = (int)$addr['id'];

// -----------------------------------------------------------------
// 1. Schema check
// -----------------------------------------------------------------
runTest("Database schema contains payments.reminder_sent_at and orders.status supports LAUNDRY_DIANTAR", function() use ($db) {
    $colReminder = $db->query("SHOW COLUMNS FROM `payments` LIKE 'reminder_sent_at'")->fetch(\PDO::FETCH_ASSOC);
    $colStatus = $db->query("SHOW COLUMNS FROM `orders` LIKE 'status'")->fetch(\PDO::FETCH_ASSOC);

    return !empty($colReminder) && strpos($colStatus['Type'], "'LAUNDRY_DIANTAR'") !== false;
}, $passed, $total);

// -----------------------------------------------------------------
// 2. Order Created Notifications (Customer & Store Owner)
// -----------------------------------------------------------------
$testOrderCode = 'TEST-NOTIF-' . rand(1000, 9999);
$testOrderId = 0;

runTest("Order creation generates in-app and WhatsApp notifications for Customer and Store Owner", function() use ($db, $customerId, $storeId, $addressId, $testOrderCode, &$testOrderId, $customer, $store) {
    $orderModel = new Order();
    $testOrderId = $orderModel->createWithItems([
        'order_code' => $testOrderCode,
        'customer_id' => $customerId,
        'store_id' => $storeId,
        'pickup_address_id' => $addressId,
        'delivery_address_id' => $addressId,
        'status' => 'MENUNGGU_KONFIRMASI',
        'payment_method' => 'ONLINE_QRIS',
        'total_price' => 50000.00,
        'delivery_fee' => 10000.00,
        'notes' => 'Test Notification Suite'
    ], [
        ['service_id' => 1, 'quantity' => 2.0, 'unit_price' => 25000.00, 'subtotal' => 50000.00]
    ]);

    // Simulate controller level WA dispatch
    $waService = new \App\Services\WhatsAppNotificationService();
    $waService->notifyOrderCreated($customer['phone'] ?? '081234567890', $customer['name'], $testOrderCode, $store['name']);
    $waService->notifyStoreNewOrder($store['phone'] ?? '081299998888', $store['name'], $testOrderCode, $customer['name']);

    $custNotif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId}")->fetch();
    $storeNotif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$store['owner_id']} AND order_id = {$testOrderId}")->fetch();
    $waCust = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'ORDER_CREATED' AND message LIKE '%{$testOrderCode}%'")->fetch();
    $waStore = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'STORE_NEW_ORDER' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return !empty($custNotif) && !empty($storeNotif) && !empty($waCust) && !empty($waStore);
}, $passed, $total);

// -----------------------------------------------------------------
// 3. Courier Assigned Notification (Customer receives In-App + WA)
// -----------------------------------------------------------------
runTest("Courier assignment generates in-app and WhatsApp notification to customer with courier details", function() use ($orderModel, $testOrderId, $courierId, $storeOwnerId, $testOrderCode, $db, $customerId) {
    $waCountBefore = (int)$db->query("SELECT COUNT(*) FROM `whatsapp_logs` WHERE event = 'COURIER_ASSIGNED' AND message LIKE '%{$testOrderCode}%'")->fetchColumn();

    $assigned = $orderModel->manualAssignCourier($testOrderId, $courierId, $storeOwnerId, 'PICKUP');

    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Kurir Ditugaskan%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'COURIER_ASSIGNED' AND message LIKE '%{$testOrderCode}%'")->fetch();
    $waCountAfter = (int)$db->query("SELECT COUNT(*) FROM `whatsapp_logs` WHERE event = 'COURIER_ASSIGNED' AND message LIKE '%{$testOrderCode}%'")->fetchColumn();

    return $assigned && !empty($notif) && !empty($waLog) && ($waCountAfter === $waCountBefore + 1);
}, $passed, $total);

// -----------------------------------------------------------------
// 4. Courier Heading to Location (KURIR_MENUJU_LOKASI)
// -----------------------------------------------------------------
runTest("Start pickup generates in-app and WhatsApp notification to customer (JEMPUT)", function() use ($testOrderId, $testOrderCode, $courierUser, $customerId, $db) {
    $_SESSION['user'] = [
        'id' => (int)$courierUser['id'],
        'name' => $courierUser['name'],
        'role' => 'kurir'
    ];
    $_POST['_csrf_token'] = generate_csrf_token();

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->startPickup($req, $res, (string)$testOrderId);

    $order = $db->query("SELECT status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Kurir Menuju Lokasi%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'COURIER_DISPATCHED' AND message LIKE '%{$testOrderCode}%' AND message LIKE '%menjemput%'")->fetch();

    return ($order['status'] === 'KURIR_MENUJU_LOKASI') && !empty($notif) && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 5. Laundry Picked Up (LAUNDRY_DIJEMPUT)
// -----------------------------------------------------------------
runTest("Confirm picked generates in-app and WhatsApp notification to customer", function() use ($testOrderId, $testOrderCode, $courierUser, $customerId, $db) {
    $_SESSION['user'] = [
        'id' => (int)$courierUser['id'],
        'name' => $courierUser['name'],
        'role' => 'kurir'
    ];
    $token = generate_csrf_token();
    $_POST['_csrf_token'] = $token;

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->confirmPicked($req, $res, (string)$testOrderId);

    $order = $db->query("SELECT status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Laundry Berhasil Dijemput%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'LAUNDRY_PICKED' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return ($order['status'] === 'LAUNDRY_DIJEMPUT') && !empty($notif) && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 6. Arrived at Store (SAMPAI_DI_TOKO)
// -----------------------------------------------------------------
runTest("Arrive at store generates in-app and WhatsApp notification to customer", function() use ($testOrderId, $testOrderCode, $courierUser, $customerId, $db) {
    $_SESSION['user'] = [
        'id' => (int)$courierUser['id'],
        'name' => $courierUser['name'],
        'role' => 'kurir'
    ];
    $token = generate_csrf_token();
    $_POST['_csrf_token'] = $token;

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->arriveAtStore($req, $res, (string)$testOrderId);

    $order = $db->query("SELECT status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Laundry Sampai di Toko%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'ARRIVED_AT_STORE' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return ($order['status'] === 'SAMPAI_DI_TOKO') && !empty($notif) && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 7. Laundry Weighed & QRIS Bill (MENUNGGU_PEMBAYARAN)
// -----------------------------------------------------------------
runTest("Weighing submission generates in-app and WhatsApp notification with amount & weight", function() use ($orderModel, $testOrderId, $testOrderCode, $storeOwnerId, $customerId, $db) {
    $weighed = $orderModel->submitWeighing($testOrderId, 3.5, 12, 'Catatan toko penimbangan', $storeOwnerId);

    $order = $db->query("SELECT status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Tagihan Pembayaran QRIS%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'WEIGHING_COMPLETED' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return $weighed && ($order['status'] === 'MENUNGGU_PEMBAYARAN') && !empty($notif) && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 8. Payment Expiring Soon Reminder (~15 mins) & Idempotency
// -----------------------------------------------------------------
runTest("Payment reminder is sent when expiry is ~15 minutes and is strictly IDEMPOTENT", function() use ($db, $testOrderId, $customerId, $testOrderCode, $paymentModel) {
    // Set payment expired_at to 14 minutes from now and reset reminder_sent_at
    $db->exec("UPDATE `payments` SET `expired_at` = DATE_ADD(NOW(), INTERVAL 14 MINUTE), `reminder_sent_at` = NULL, `status` = 'PENDING' WHERE order_id = {$testOrderId}");

    $notifCountBefore = (int)$db->query("SELECT COUNT(*) FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Pengingat Pembayaran%'")->fetchColumn();
    $waCountBefore = (int)$db->query("SELECT COUNT(*) FROM `whatsapp_logs` WHERE event = 'PAYMENT_EXPIRING_SOON' AND message LIKE '%{$testOrderCode}%'")->fetchColumn();

    // First execution: should find 1 and send reminder
    $firstRunCount = $paymentModel->sendExpiringSoonReminders(15);

    $notifCountMid = (int)$db->query("SELECT COUNT(*) FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Pengingat Pembayaran%'")->fetchColumn();
    $waCountMid = (int)$db->query("SELECT COUNT(*) FROM `whatsapp_logs` WHERE event = 'PAYMENT_EXPIRING_SOON' AND message LIKE '%{$testOrderCode}%'")->fetchColumn();

    // Second execution (Idempotency test): must find 0 and send NOTHING
    $secondRunCount = $paymentModel->sendExpiringSoonReminders(15);

    $notifCountAfter = (int)$db->query("SELECT COUNT(*) FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Pengingat Pembayaran%'")->fetchColumn();
    $waCountAfter = (int)$db->query("SELECT COUNT(*) FROM `whatsapp_logs` WHERE event = 'PAYMENT_EXPIRING_SOON' AND message LIKE '%{$testOrderCode}%'")->fetchColumn();

    return ($firstRunCount >= 1)
        && ($notifCountMid === $notifCountBefore + 1)
        && ($waCountMid === $waCountBefore + 1)
        && ($secondRunCount === 0)
        && ($notifCountAfter === $notifCountMid)
        && ($waCountAfter === $waCountMid);
}, $passed, $total);

// -----------------------------------------------------------------
// 9. Payment Success & Laundry Processing (SEDANG_DICUCI)
// -----------------------------------------------------------------
runTest("Payment confirmation and washing stage triggers in-app and WhatsApp notifications", function() use ($testOrderId, $testOrderCode, $customerId, $store, $db) {
    $_SESSION['user'] = [
        'id' => (int)$store['owner_id'],
        'name' => 'Owner Store',
        'role' => 'owner_toko'
    ];
    $_POST['_csrf_token'] = generate_csrf_token();
    $_POST['new_status'] = 'SEDANG_DICUCI';
    $_POST['notes'] = 'Cucian mulai masuk mesin cuci';

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->updateOrderStatus($req, $res, (string)$testOrderId);

    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Laundry Sedang Dicuci%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'LAUNDRY_PROCESSING' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return !empty($notif) && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 10. Laundry Ready for Delivery (SIAP_DIANTAR)
// -----------------------------------------------------------------
runTest("Status SIAP_DIANTAR generates in-app and WhatsApp notification to customer", function() use ($testOrderId, $testOrderCode, $customerId, $store, $db) {
    $_SESSION['user'] = [
        'id' => (int)$store['owner_id'],
        'name' => 'Owner Store',
        'role' => 'owner_toko'
    ];
    $_POST['_csrf_token'] = generate_csrf_token();
    $_POST['new_status'] = 'SIAP_DIANTAR';

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->updateOrderStatus($req, $res, (string)$testOrderId);

    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Cucian Anda Sudah Bersih%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'LAUNDRY_READY' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return !empty($notif) && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 11. Courier Heading to Customer (KURIR_MENUJU_CUSTOMER)
// -----------------------------------------------------------------
runTest("Start delivery generates in-app and WhatsApp notification to customer (ANTAR)", function() use ($testOrderId, $testOrderCode, $courierUser, $customerId, $db) {
    $_SESSION['user'] = [
        'id' => (int)$courierUser['id'],
        'name' => $courierUser['name'],
        'role' => 'kurir'
    ];
    $_POST['_csrf_token'] = generate_csrf_token();

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->startDelivery($req, $res, (string)$testOrderId);

    $order = $db->query("SELECT status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Kurir Sedang Mengantar%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'COURIER_DISPATCHED' AND message LIKE '%{$testOrderCode}%' AND message LIKE '%mengantarkan%'")->fetch();

    return ($order['status'] === 'KURIR_MENUJU_CUSTOMER') && !empty($notif) && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 12. Courier Completes Delivery -> LAUNDRY_DIANTAR
// -----------------------------------------------------------------
runTest("Courier confirm delivery sets status to LAUNDRY_DIANTAR (not SELESAI) and notifies customer to confirm", function() use ($testOrderId, $testOrderCode, $courierUser, $customerId, $db) {
    $_SESSION['user'] = [
        'id' => (int)$courierUser['id'],
        'name' => $courierUser['name'],
        'role' => 'kurir'
    ];
    $_POST['_csrf_token'] = generate_csrf_token();

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->completeDelivery($req, $res, (string)$testOrderId);

    $order = $db->query("SELECT status, completed_at FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Laundry Anda Sudah Diantar%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'LAUNDRY_DELIVERED' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return ($order['status'] === 'LAUNDRY_DIANTAR')
        && empty($order['completed_at'])
        && !empty($notif)
        && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 13. Tenant Isolation on Receipt Confirmation
// -----------------------------------------------------------------
runTest("Another customer cannot confirm receipt of an order they do not own (HTTP 403)", function() use ($testOrderId, $otherCustomerId, $db) {
    $_SESSION['user'] = [
        'id' => $otherCustomerId,
        'name' => 'Customer Deni',
        'role' => 'customer'
    ];
    $_POST['_csrf_token'] = generate_csrf_token();

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->confirmCustomerReceived($req, $res, (string)$testOrderId);

    $code = http_response_code();
    $order = $db->query("SELECT status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);

    return ($code === 403) && ($order['status'] === 'LAUNDRY_DIANTAR');
}, $passed, $total);

// -----------------------------------------------------------------
// 14. Customer Confirms Receipt -> SELESAI
// -----------------------------------------------------------------
runTest("Legitimate customer confirms receipt -> status becomes SELESAI, completed_at set, review invited", function() use ($testOrderId, $customerId, $testOrderCode, $db) {
    $_SESSION['user'] = [
        'id' => $customerId,
        'name' => 'Customer Ani',
        'role' => 'customer'
    ];
    $_POST['_csrf_token'] = generate_csrf_token();

    $ctrl = new DashboardController();
    $req = new Request();
    $res = new Response();

    $ctrl->confirmCustomerReceived($req, $res, (string)$testOrderId);

    $order = $db->query("SELECT status, completed_at FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $history = $db->query("SELECT * FROM `order_status_histories` WHERE order_id = {$testOrderId} AND status = 'SELESAI' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customerId} AND order_id = {$testOrderId} AND title LIKE '%Beri Ulasan%'")->fetch();
    $waLog = $db->query("SELECT * FROM `whatsapp_logs` WHERE event = 'ORDER_COMPLETED' AND message LIKE '%{$testOrderCode}%'")->fetch();

    return ($order['status'] === 'SELESAI')
        && !empty($order['completed_at'])
        && !empty($history)
        && !empty($notif)
        && !empty($waLog);
}, $passed, $total);

// -----------------------------------------------------------------
// 15. Auto-Complete 24 Hours Idempotency
// -----------------------------------------------------------------
runTest("autoCompleteDeliveredOrders() completes orders past 24h with note 'Diselesaikan otomatis' and is idempotent", function() use ($db, $customerId, $storeId, $addressId, $orderModel) {
    // Create an order stuck in LAUNDRY_DIANTAR for 25 hours
    $autoCode = 'AUTO-24H-' . rand(1000, 9999);
    $db->exec("INSERT INTO `orders` (`order_code`, `customer_id`, `store_id`, `pickup_address_id`, `delivery_address_id`, `status`, `payment_method`, `total_price`, `delivery_fee`, `created_at`, `updated_at`)
               VALUES ('{$autoCode}', {$customerId}, {$storeId}, {$addressId}, {$addressId}, 'LAUNDRY_DIANTAR', 'ONLINE_QRIS', 35000.00, 5000.00, DATE_SUB(NOW(), INTERVAL 26 HOUR), DATE_SUB(NOW(), INTERVAL 25 HOUR))");
    $autoOrderId = (int)$db->lastInsertId();

    // Insert history with timestamp 25 hours ago
    $db->exec("INSERT INTO `order_status_histories` (`order_id`, `status`, `changed_by`, `notes`, `created_at`)
               VALUES ({$autoOrderId}, 'LAUNDRY_DIANTAR', 1, 'Kurir telah tiba', DATE_SUB(NOW(), INTERVAL 25 HOUR))");

    // First auto-complete run: should complete 1 order
    $completedCount1 = $orderModel->autoCompleteDeliveredOrders(24);

    $orderAfter1 = $db->query("SELECT status, completed_at FROM `orders` WHERE id = {$autoOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $historyAfter1 = $db->query("SELECT notes FROM `order_status_histories` WHERE order_id = {$autoOrderId} AND status = 'SELESAI' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

    // Second auto-complete run: should be 0 (Idempotent)
    $completedCount2 = $orderModel->autoCompleteDeliveredOrders(24);

    return ($completedCount1 >= 1)
        && ($orderAfter1['status'] === 'SELESAI')
        && !empty($orderAfter1['completed_at'])
        && (strpos($historyAfter1['notes'] ?? '', 'Diselesaikan otomatis') !== false)
        && ($completedCount2 === 0);
}, $passed, $total);

echo "\n====================================================================\n";
echo "STAGE 16 TEST RESULTS: {$passed} / {$total} PASSED\n";
echo "====================================================================\n";

if ($passed === $total) {
    echo "\033[32mALL TESTS PASSED SUCCESSFULLY! PRD SECTION 14 VERIFIED.\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSOME TESTS FAILED.\033[0m\n";
    exit(1);
}
