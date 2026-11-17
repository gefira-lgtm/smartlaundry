<?php
/**
 * Automated Test Suite for Stage 11: Expired Payments & Laundry Return Workflow
 */
define('ROOT_PATH', 'c:/xampp/htdocs/RPL2');
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('VIEW_PATH', APP_PATH . '/Views');

require_once ROOT_PATH . '/config/env.php';
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
require_once ROOT_PATH . '/app/Services/WhatsAppNotificationService.php';

use App\Core\Database;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Courier;
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
echo "SMARTLAUNDRY STAGE 11: EXPIRED PAYMENTS & RETURN WORKFLOW TEST SUITE\n";
echo "====================================================================\n\n";

// 1. Schema check
runTest("Database Schema has refund_fee, refund_fee_bearer, refund_assigned_courier_id", function() use ($db) {
    $cols = $db->query("SHOW COLUMNS FROM `orders`")->fetchAll(\PDO::FETCH_COLUMN);
    return in_array('refund_fee', $cols) 
        && in_array('refund_fee_bearer', $cols) 
        && in_array('refund_assigned_courier_id', $cols);
}, $passed, $total);

// Fetch test entities
$customer = $db->query("SELECT u.id, u.name, u.phone FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = 'customer' LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$store = $db->query("SELECT s.id, s.name, s.phone, s.owner_id FROM `stores` s WHERE s.is_verified = 1 LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$courier = $db->query("SELECT c.id AS courier_id, c.user_id, u.name, u.phone FROM `couriers` c JOIN `users` u ON c.user_id = u.id WHERE c.store_id = {$store['id']} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$address = $db->query("SELECT id FROM `addresses` WHERE user_id = {$customer['id']} LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

if (!$address) {
    $db->exec("INSERT INTO `addresses` (`user_id`, `label`, `recipient_name`, `phone`, `full_address`, `latitude`, `longitude`, `is_primary`, `created_at`, `updated_at`)
               VALUES ({$customer['id']}, 'Rumah', '{$customer['name']}', '08123456789', 'Jl. Testing No. 123', -6.20000000, 106.81666600, 1, NOW(), NOW())");
    $addressId = (int)$db->lastInsertId();
} else {
    $addressId = (int)$address['id'];
}

$testOrderCode = 'EXP-' . date('Ymd') . '-' . rand(1000, 9999);

// Create Test Order
$stmtOrder = $db->prepare("INSERT INTO `orders` (`order_code`, `customer_id`, `store_id`, `courier_id`, `pickup_address_id`, `delivery_address_id`, `status`, `payment_method`, `total_price`, `delivery_fee`, `created_at`, `updated_at`)
                           VALUES (:code, :cust, :store, :cour, :pickup_addr, :delivery_addr, 'MENUNGGU_PEMBAYARAN', 'ONLINE_QRIS', 45000.00, 10000.00, NOW(), NOW())");
$stmtOrder->execute([
    'code' => $testOrderCode,
    'cust' => $customer['id'],
    'store' => $store['id'],
    'cour' => $courier['user_id'],
    'pickup_addr' => $addressId,
    'delivery_addr' => $addressId
]);
$testOrderId = (int)$db->lastInsertId();

// Create Past Overdue Payment Record (expired 2 hours ago)
$pastExpiredTime = date('Y-m-d H:i:s', strtotime('-2 hours'));
$stmtPayment = $db->prepare("INSERT INTO `payments` (`order_id`, `payment_code`, `method`, `amount`, `status`, `expired_at`, `created_at`, `updated_at`)
                             VALUES (:order_id, :code, 'ONLINE_QRIS', 45000.00, 'PENDING', :expired_at, NOW(), NOW())");
$stmtPayment->execute([
    'order_id' => $testOrderId,
    'code' => 'PAY-' . $testOrderCode,
    'expired_at' => $pastExpiredTime
]);
$testPaymentId = (int)$db->lastInsertId();

// 2. Test Check & Expire Trigger
runTest("Payment::checkAndExpire() transitions payment to EXPIRED and order to PEMBAYARAN_KADALUARSA", function() use ($testPaymentId, $testOrderId, $db) {
    $paymentModel = new Payment();
    $payment = $paymentModel->checkAndExpire($testPaymentId);

    $order = $db->query("SELECT status FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);

    return ($payment['status'] === 'EXPIRED') && ($order['status'] === 'PEMBAYARAN_KADALUARSA');
}, $passed, $total);

// 3. Test Order Status History for automated expiry
runTest("Order status history logged with system notes for payment expiration", function() use ($testOrderId, $db) {
    $history = $db->query("SELECT * FROM `order_status_histories` WHERE order_id = {$testOrderId} AND status = 'PEMBAYARAN_KADALUARSA' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    return !empty($history) && strpos($history['notes'], 'kadaluarsa') !== false;
}, $passed, $total);

// 4. Test In-App Notifications for Expiration (Customer & Store Owner)
runTest("In-App notifications created for both customer and store owner", function() use ($testOrderId, $customer, $store, $db) {
    $custNotif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customer['id']} AND order_id = {$testOrderId} ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    $storeNotif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$store['owner_id']} AND order_id = {$testOrderId} ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

    return !empty($custNotif) && !empty($storeNotif);
}, $passed, $total);

// 5. Test WhatsApp Simulation Logs for Expiration
runTest("WhatsApp notification logs dispatched for expiration", function() use ($testOrderCode, $db) {
    $logs = $db->query("SELECT * FROM `whatsapp_logs` WHERE message LIKE '%{$testOrderCode}%' AND event IN ('PAYMENT_EXPIRED', 'STORE_PAYMENT_EXPIRED')")->fetchAll(\PDO::FETCH_ASSOC);
    return count($logs) >= 1;
}, $passed, $total);

// 6. Test Idempotency Guard (Repeated checkAndExpire should not duplicate history or notifs)
runTest("Payment::checkAndExpire() is idempotent (No duplicate history or notifs on re-run)", function() use ($testPaymentId, $testOrderId, $db) {
    $historyCountBefore = (int)$db->query("SELECT COUNT(*) FROM `order_status_histories` WHERE order_id = {$testOrderId} AND status = 'PEMBAYARAN_KADALUARSA'")->fetchColumn();
    $notifCountBefore = (int)$db->query("SELECT COUNT(*) FROM `notifications` WHERE order_id = {$testOrderId}")->fetchColumn();

    $paymentModel = new Payment();
    $paymentModel->checkAndExpire($testPaymentId);

    $historyCountAfter = (int)$db->query("SELECT COUNT(*) FROM `order_status_histories` WHERE order_id = {$testOrderId} AND status = 'PEMBAYARAN_KADALUARSA'")->fetchColumn();
    $notifCountAfter = (int)$db->query("SELECT COUNT(*) FROM `notifications` WHERE order_id = {$testOrderId}")->fetchColumn();

    return ($historyCountBefore === $historyCountAfter) && ($notifCountBefore === $notifCountAfter);
}, $passed, $total);

// 7. Test Store Expired Queue
runTest("Order appears in Order::getStoreExpiredPaymentsQueue()", function() use ($store, $testOrderId) {
    $orderModel = new Order();
    $queue = $orderModel->getStoreExpiredPaymentsQueue((int)$store['id']);
    $found = false;
    foreach ($queue as $q) {
        if ((int)$q['id'] === $testOrderId) {
            $found = true;
            break;
        }
    }
    return $found;
}, $passed, $total);

// 8. Test Store Admin Approval of Return
runTest("Order::approveRefund() updates status to PROSES_PENGEMBALIAN, sets fee and bearer", function() use ($testOrderId, $store, $courier, $db) {
    $orderModel = new Order();
    $approved = $orderModel->approveRefund(
        $testOrderId,
        15000.00,
        'customer',
        (int)$courier['user_id'],
        (int)$store['owner_id'],
        'Barang dikembalikan karena pembayaran QRIS kadaluarsa'
    );

    $order = $db->query("SELECT status, refund_fee, refund_fee_bearer, refund_assigned_courier_id FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);

    return $approved 
        && ($order['status'] === 'PROSES_PENGEMBALIAN') 
        && ((float)$order['refund_fee'] == 15000.00) 
        && (strtoupper($order['refund_fee_bearer']) === 'CUSTOMER')
        && ((int)$order['refund_assigned_courier_id'] === (int)$courier['user_id']);
}, $passed, $total);

// 9. Test Courier Task Retrieval for Refund
runTest("Courier::getCourierTasks() includes refund task with PROSES_PENGEMBALIAN", function() use ($courier, $testOrderId) {
    $courierModel = new Courier();
    $tasks = $courierModel->getCourierTasks((int)$courier['user_id']);
    $found = false;
    foreach ($tasks['refund'] as $rt) {
        if ((int)$rt['id'] === $testOrderId) {
            $found = true;
            break;
        }
    }
    return $found;
}, $passed, $total);

// 10. Test Courier Task Anti-Duplication Check
runTest("Order::assignRefundCourier() prevents duplicate assignment and history", function() use ($testOrderId, $courier, $store, $db) {
    $orderModel = new Order();
    $historyCountBefore = (int)$db->query("SELECT COUNT(*) FROM `order_status_histories` WHERE order_id = {$testOrderId}")->fetchColumn();

    $reassignResult = $orderModel->assignRefundCourier($testOrderId, (int)$courier['user_id'], (int)$store['owner_id']);
    $historyCountAfter = (int)$db->query("SELECT COUNT(*) FROM `order_status_histories` WHERE order_id = {$testOrderId}")->fetchColumn();

    return $reassignResult === true && ($historyCountBefore === $historyCountAfter);
}, $passed, $total);

// 11. Test Courier Journey Start for Refund
runTest("Order::startRefundDelivery() logs transit history and notifies customer", function() use ($testOrderId, $courier, $db) {
    $orderModel = new Order();
    $started = $orderModel->startRefundDelivery($testOrderId, (int)$courier['user_id']);

    $histories = $db->query("SELECT * FROM `order_status_histories` WHERE order_id = {$testOrderId} ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    return $started && strpos($histories['notes'], 'perjalanan') !== false;
}, $passed, $total);

// 12. Test Courier Complete Return Delivery -> Order Status becomes DIBATALKAN
runTest("Order::completeRefundReturn() closes order with status DIBATALKAN (not SELESAI)", function() use ($testOrderId, $courier, $db) {
    $orderModel = new Order();
    $completed = $orderModel->completeRefundReturn($testOrderId, (int)$courier['user_id'], 'Pakaian telah diserahterimakan kembali ke customer.');

    $order = $db->query("SELECT status, completed_at FROM `orders` WHERE id = {$testOrderId}")->fetch(\PDO::FETCH_ASSOC);

    return $completed && ($order['status'] === 'DIBATALKAN') && !empty($order['completed_at']);
}, $passed, $total);

// 13. Test Final History & Notification Audit
runTest("Final return completion is logged in history and customer/store notified", function() use ($testOrderId, $customer, $db) {
    $history = $db->query("SELECT * FROM `order_status_histories` WHERE order_id = {$testOrderId} AND status = 'DIBATALKAN' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    $notif = $db->query("SELECT * FROM `notifications` WHERE user_id = {$customer['id']} AND order_id = {$testOrderId} AND title LIKE '%Pengembalian Selesai%'")->fetch(\PDO::FETCH_ASSOC);

    return !empty($history) && !empty($notif);
}, $passed, $total);

// 14. Test CLI Cron Runner Execution
runTest("cron_check_expired_payments.php runs via CLI cleanly", function() {
    $cmd = 'C:\\xampp\\php\\php.exe c:\\xampp\\htdocs\\RPL2\\cron_check_expired_payments.php 2>&1';
    $output = shell_exec($cmd);
    return strpos($output, 'Completed!') !== false;
}, $passed, $total);

echo "\n====================================================================\n";
echo "STAGE 11 TEST RESULTS: {$passed} / {$total} PASSED\n";
echo "====================================================================\n";

if ($passed === $total) {
    echo "\033[32mALL TESTS PASSED SUCCESSFULLY! STAGE 11 READY FOR VERIFICATION.\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSOME TESTS FAILED.\033[0m\n";
    exit(1);
}
