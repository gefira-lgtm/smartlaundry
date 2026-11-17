<?php
/**
 * Automated Test Suite for Stage 12: Courier Task Acceptance, Rejection & Re-offer Workflow
 */
define('ROOT_PATH', 'c:/xampp/htdocs/RPL2');
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('VIEW_PATH', APP_PATH . '/Views');

require_once ROOT_PATH . '/config/env.php';
define('TEST_MODE', true);
load_env(ROOT_PATH . '/.env');
require_once ROOT_PATH . '/config/app.php';
require_once ROOT_PATH . '/app/Core/Model.php';
require_once ROOT_PATH . '/app/Core/Database.php';

require_once ROOT_PATH . '/app/Models/User.php';
require_once ROOT_PATH . '/app/Models/Store.php';
require_once ROOT_PATH . '/app/Models/Courier.php';
require_once ROOT_PATH . '/app/Models/Order.php';
require_once ROOT_PATH . '/app/Models/Payment.php';
require_once ROOT_PATH . '/app/Models/OrderStatusHistory.php';
require_once ROOT_PATH . '/app/Models/Notification.php';
require_once ROOT_PATH . '/app/Models/WhatsAppLog.php';
require_once ROOT_PATH . '/app/Models/CourierTaskOffer.php';
require_once ROOT_PATH . '/app/Services/WhatsAppNotificationService.php';

use App\Core\Database;
use App\Models\Order;
use App\Models\Store;
use App\Models\Courier;
use App\Models\CourierTaskOffer;
use App\Models\OrderStatusHistory;
use App\Models\Notification;
use App\Models\WhatsAppLog;

$db = Database::getConnection();
$passed = 0;
$total = 0;

function runTest(string $title, callable $fn, &$passed, &$total) {
    $total++;
    echo "TEST {$total}: {$title}... ";
    try {
        $result = $fn();
        if ($result === true) {
            echo "\033[32m[PASSED]\033[0m\n";
            $passed++;
        } else {
            echo "\033[31m[FAILED]\033[0m: Returned false or unexpected value\n";
        }
    } catch (\Throwable $th) {
        echo "\033[31m[ERROR]\033[0m: " . $th->getMessage() . "\n";
        echo "Line " . $th->getLine() . " in " . $th->getFile() . "\n";
    }
}

echo "====================================================================\n";
echo "SMARTLAUNDRY STAGE 12: COURIER TASK ACCEPT / REJECT TEST SUITE\n";
echo "====================================================================\n\n";

// 1. Schema Check
runTest("Table `courier_task_offers` exists and `orders.courier_assignment_status` exists", function() use ($db) {
    $tables = $db->query("SHOW TABLES LIKE 'courier_task_offers'")->fetchAll();
    $cols = $db->query("SHOW COLUMNS FROM `orders` LIKE 'courier_assignment_status'")->fetchAll();
    return count($tables) > 0 && count($cols) > 0;
}, $passed, $total);

// Prepare Entities
$customer = $db->query("SELECT u.id, u.name, u.phone FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = 'customer' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$store = $db->query("SELECT s.id, s.name, s.phone, s.owner_id FROM `stores` s WHERE s.is_verified = 1 LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

// Ensure at least 2 couriers exist for this store
$couriers = $db->query("SELECT c.id AS courier_id, c.user_id, u.name, u.phone FROM `couriers` c JOIN `users` u ON c.user_id = u.id WHERE c.store_id = {$store['id']} AND c.is_permanently_inactive = 0")->fetchAll(\PDO::FETCH_ASSOC);

if (count($couriers) < 2) {
    // Create a second courier for testing re-offer
    $db->exec("INSERT INTO `users` (`role_id`, `name`, `email`, `password`, `phone`, `avatar`, `is_active`, `created_at`, `updated_at`)
               VALUES (5, 'Kurir Kedua Test', 'kurir2_test_" . time() . "@smartlaundry.id', 'password', '08129998881', 'avatar_default.png', 1, NOW(), NOW())");
    $newUserId = (int)$db->lastInsertId();
    $db->exec("INSERT INTO `couriers` (`user_id`, `store_id`, `vehicle_type`, `license_plate`, `is_available`, `status`, `is_permanently_inactive`, `created_at`, `updated_at`)
               VALUES ({$newUserId}, {$store['id']}, 'Motor Matic', 'B 9999 TST', 1, 'TERSEDIA', 0, NOW(), NOW())");
    
    $couriers = $db->query("SELECT c.id AS courier_id, c.user_id, u.name, u.phone FROM `couriers` c JOIN `users` u ON c.user_id = u.id WHERE c.store_id = {$store['id']} AND c.is_permanently_inactive = 0")->fetchAll(\PDO::FETCH_ASSOC);
}

$courierA = $couriers[0];
$courierB = $couriers[1];

// Make sure both couriers are available and have no active tasks at test start
$db->exec("UPDATE `orders` SET `status` = 'SELESAI' WHERE courier_id IN ({$courierA['user_id']}, {$courierB['user_id']}) AND status NOT IN ('SELESAI', 'DIBATALKAN')");
$db->exec("UPDATE `couriers` SET `is_available` = 1, `status` = 'TERSEDIA' WHERE user_id IN ({$courierA['user_id']}, {$courierB['user_id']})");

$address = $db->query("SELECT id FROM `addresses` WHERE user_id = {$customer['id']} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$addressId = (int)$address['id'];

// Create Test Order
$testOrderCode = 'OFR-' . date('Ymd') . '-' . rand(1000, 9999);
$stmtOrder = $db->prepare("INSERT INTO `orders` (`order_code`, `customer_id`, `store_id`, `pickup_address_id`, `delivery_address_id`, `status`, `payment_method`, `total_price`, `delivery_fee`, `created_at`, `updated_at`)
                           VALUES (:code, :cust, :store, :p_addr, :d_addr, 'MENUNGGU_KURIR', 'ONLINE_QRIS', 35000.00, 10000.00, NOW(), NOW())");
$stmtOrder->execute([
    'code' => $testOrderCode,
    'cust' => $customer['id'],
    'store' => $store['id'],
    'p_addr' => $addressId,
    'd_addr' => $addressId
]);
$testOrderId = (int)$db->lastInsertId();

// 2. Test Dispatch Task Offer
runTest("Order::dispatchTaskOffer() creates pending offer and sets courier_assignment_status = MENUNGGU_RESPON", function() use ($testOrderId, $db) {
    $orderModel = new Order();
    $dispatched = $orderModel->dispatchTaskOffer($testOrderId, 'PICKUP');

    $order = $db->query("SELECT courier_id, courier_assignment_status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $offer = $db->query("SELECT * FROM `courier_task_offers` WHERE order_id = {$testOrderId} AND status = 'MENUNGGU_RESPON'")->fetch(\PDO::FETCH_ASSOC);

    return $dispatched && !empty($offer) && $order['courier_assignment_status'] === 'MENUNGGU_RESPON';
}, $passed, $total);

// 3. Test Courier Pending Offers Retrieval
runTest("CourierTaskOffer::getPendingOffersForCourier() returns dispatched offer", function() use ($testOrderId, $db) {
    $offer = $db->query("SELECT courier_id FROM `courier_task_offers` WHERE order_id = {$testOrderId} AND status = 'MENUNGGU_RESPON'")->fetch(\PDO::FETCH_ASSOC);
    $courierUserId = (int)$offer['courier_id'];

    $offerModel = new CourierTaskOffer();
    $pending = $offerModel->getPendingOffersForCourier($courierUserId);

    $found = false;
    foreach ($pending as $p) {
        if ((int)$p['order_id'] === $testOrderId) {
            $found = true;
            break;
        }
    }
    return $found;
}, $passed, $total);

// 4. Test Courier Task Notifications
runTest("In-App and WhatsApp task offer notifications sent to first candidate courier", function() use ($testOrderId, $testOrderCode, $db) {
    $offer = $db->query("SELECT courier_id FROM `courier_task_offers` WHERE order_id = {$testOrderId} ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    $courierUserId = (int)$offer['courier_id'];

    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$courierUserId} AND order_id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $wa = $db->query("SELECT * FROM `whatsapp_logs` WHERE message LIKE '%{$testOrderCode}%' AND event = 'COURIER_TASK_OFFERED'")->fetch(\PDO::FETCH_ASSOC);

    return !empty($notif) && !empty($wa);
}, $passed, $total);

// 5. Test Courier Reject & Automatic Re-Offer
runTest("Order::processCourierReject() marks offer DITOLAK and automatically re-offers to second candidate courier", function() use ($testOrderId, $db) {
    $firstOffer = $db->query("SELECT * FROM `courier_task_offers` WHERE order_id = {$testOrderId} AND status = 'MENUNGGU_RESPON'")->fetch(\PDO::FETCH_ASSOC);
    $firstCourierUserId = (int)$firstOffer['courier_id'];
    $firstOfferId = (int)$firstOffer['id'];

    $orderModel = new Order();
    $rejectResult = $orderModel->processCourierReject($firstOfferId, $firstCourierUserId, 'Kendaraan sedang ganti oli');

    // First offer must be DITOLAK with reason
    $updatedFirstOffer = $db->query("SELECT status, reject_reason FROM `courier_task_offers` WHERE id = {$firstOfferId}")->fetch(\PDO::FETCH_ASSOC);

    // Second offer must be created for the other courier
    $secondOffer = $db->query("SELECT * FROM `courier_task_offers` WHERE order_id = {$testOrderId} AND status = 'MENUNGGU_RESPON' AND id != {$firstOfferId}")->fetch(\PDO::FETCH_ASSOC);

    return $rejectResult['success'] 
        && ($updatedFirstOffer['status'] === 'DITOLAK') 
        && strpos($updatedFirstOffer['reject_reason'], 'ganti oli') !== false
        && !empty($secondOffer)
        && ((int)$secondOffer['courier_id'] !== $firstCourierUserId);
}, $passed, $total);

// 6. Test All Couriers Reject -> Fallback to TIDAK_ADA_KURIR
runTest("When all candidate couriers reject, order transitions to TIDAK_ADA_KURIR and store owner is notified", function() use ($testOrderId, $store, $db) {
    $secondOffer = $db->query("SELECT * FROM `courier_task_offers` WHERE order_id = {$testOrderId} AND status = 'MENUNGGU_RESPON'")->fetch(\PDO::FETCH_ASSOC);
    $secondCourierUserId = (int)$secondOffer['courier_id'];
    $secondOfferId = (int)$secondOffer['id'];

    $orderModel = new Order();
    $rejectResult = $orderModel->processCourierReject($secondOfferId, $secondCourierUserId, 'Sedang di luar rute operasional');

    $order = $db->query("SELECT courier_assignment_status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $storeNotif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$store['owner_id']} AND order_id = {$testOrderId} AND title LIKE '%Tidak Ada Kurir%'")->fetch(\PDO::FETCH_ASSOC);

    return $rejectResult['success'] 
        && ($order['courier_assignment_status'] === 'TIDAK_ADA_KURIR')
        && !empty($storeNotif);
}, $passed, $total);

// 7. Test Store Manual Assignment
runTest("Order::manualAssignCourier() allows store to manually assign courier and set status DITERIMA", function() use ($testOrderId, $courierA, $store, $db) {
    $orderModel = new Order();
    $manualAssigned = $orderModel->manualAssignCourier($testOrderId, (int)$courierA['user_id'], (int)$store['owner_id']);

    $order = $db->query("SELECT courier_id, courier_assignment_status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $history = $db->query("SELECT * FROM `order_status_histories` WHERE order_id = {$testOrderId} ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

    return $manualAssigned 
        && ((int)$order['courier_id'] === (int)$courierA['user_id'])
        && ($order['courier_assignment_status'] === 'DITERIMA')
        && strpos($history['notes'], 'manual') !== false;
}, $passed, $total);

// 8. Test Anti-Conflict Constraint: Courier with active task is detected
runTest("Courier::hasActiveTask() accurately detects ongoing task on the road", function() use ($testOrderId, $courierA, $db) {
    // Set order status to KURIR_MENUJU_LOKASI
    $db->exec("UPDATE `orders` SET `status` = 'KURIR_MENUJU_LOKASI' WHERE id = {$testOrderId}");

    $courierModel = new Courier();
    $hasActive = $courierModel->hasActiveTask((int)$courierA['user_id']);

    return $hasActive === true;
}, $passed, $total);

// 9. Test Anti-Conflict Constraint: Busy courier is skipped in candidate search
runTest("Courier::findAvailableCourierForStore() skips busy courier currently on active duty", function() use ($store, $courierA, $courierB) {
    $courierModel = new Courier();
    // Courier A has active task, Courier B is free
    $candidate = $courierModel->findAvailableCourierForStore((int)$store['id'], []);

    return !empty($candidate) && ((int)$candidate['user_id'] === (int)$courierB['user_id']);
}, $passed, $total);

// 10. Test Anti-Conflict Constraint: Busy courier blocked from accepting new offer
runTest("Order::processCourierAccept() rejects acceptance if courier has active task", function() use ($courierA, $testOrderId, $store, $customer, $addressId, $db) {
    // Create a new order & new offer for busy Courier A
    $newOrderCode = 'TST-BSY-' . rand(1000, 9999);
    $db->exec("INSERT INTO `orders` (`order_code`, `customer_id`, `store_id`, `pickup_address_id`, `delivery_address_id`, `status`, `payment_method`, `total_price`, `delivery_fee`, `created_at`, `updated_at`)
               VALUES ('{$newOrderCode}', {$customer['id']}, {$store['id']}, {$addressId}, {$addressId}, 'MENUNGGU_KURIR', 'ONLINE_QRIS', 30000.00, 10000.00, NOW(), NOW())");
    $busyOrderId = (int)$db->lastInsertId();

    $offerModel = new CourierTaskOffer();
    $busyOfferId = $offerModel->createOffer($busyOrderId, (int)$courierA['user_id'], 'PICKUP');

    $orderModel = new Order();
    $acceptResult = $orderModel->processCourierAccept($busyOfferId, (int)$courierA['user_id']);

    return ($acceptResult['success'] === false) && strpos($acceptResult['message'], 'aktif') !== false;
}, $passed, $total);

// 11. Test Courier Acceptance on Available State
runTest("Order::processCourierAccept() succeeds when courier is available, locking courier_id and status DITERIMA", function() use ($store, $courierB, $customer, $addressId, $db) {
    // Create a fresh test order for free Courier B
    $freshCode = 'FRESH-' . rand(1000, 9999);
    $db->exec("INSERT INTO `orders` (`order_code`, `customer_id`, `store_id`, `pickup_address_id`, `delivery_address_id`, `status`, `payment_method`, `total_price`, `delivery_fee`, `created_at`, `updated_at`)
               VALUES ('{$freshCode}', {$customer['id']}, {$store['id']}, {$addressId}, {$addressId}, 'MENUNGGU_KURIR', 'ONLINE_QRIS', 30000.00, 10000.00, NOW(), NOW())");
    $freshOrderId = (int)$db->lastInsertId();

    $offerModel = new CourierTaskOffer();
    $freshOfferId = $offerModel->createOffer($freshOrderId, (int)$courierB['user_id'], 'PICKUP');

    $orderModel = new Order();
    $acceptResult = $orderModel->processCourierAccept($freshOfferId, (int)$courierB['user_id']);

    $order = $db->query("SELECT courier_id, courier_assignment_status FROM `orders` WHERE id = {$freshOrderId}")->fetch(\PDO::FETCH_ASSOC);
    $offer = $db->query("SELECT status, responded_at FROM `courier_task_offers` WHERE id = {$freshOfferId}")->fetch(\PDO::FETCH_ASSOC);

    return ($acceptResult['success'] === true)
        && ((int)$order['courier_id'] === (int)$courierB['user_id'])
        && ($order['courier_assignment_status'] === 'DITERIMA')
        && ($offer['status'] === 'DITERIMA')
        && !empty($offer['responded_at']);
}, $passed, $total);

// 12. Test Courier Task Acceptance Notifies Store
runTest("Courier acceptance creates in-app notification for Store Owner", function() use ($store, $db) {
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$store['owner_id']} AND title LIKE '%Tugas Diterima Kurir%' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    return !empty($notif);
}, $passed, $total);

echo "\n====================================================================\n";
echo "STAGE 12 TEST RESULTS: {$passed} / {$total} PASSED\n";
echo "====================================================================\n";

if ($passed === $total) {
    echo "\033[32mALL TESTS PASSED SUCCESSFULLY! STAGE 12 READY FOR VERIFICATION.\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSOME TESTS FAILED.\033[0m\n";
    exit(1);
}
