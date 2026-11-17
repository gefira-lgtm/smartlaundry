<?php
/**
 * Test Platform, Store Owner, and Store Admin Dashboard Queries & Actions
 */

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Models/Role.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Models/Store.php';
require_once dirname(__DIR__) . '/app/Models/Order.php';
require_once dirname(__DIR__) . '/app/Models/Payment.php';
require_once dirname(__DIR__) . '/app/Models/LaundryService.php';
require_once dirname(__DIR__) . '/app/Models/OrderStatusHistory.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;

echo "=================================================================\n";
echo " SmartLaundry AI - Tahap 4a Dashboards Verification Test\n";
echo "=================================================================\n\n";

$storeModel = new Store();
$orderModel = new Order();
$paymentModel = new Payment();
$userModel = new User();

// 1. Platform Dashboard Verification
echo "[Test 1] Platform Dashboard Metrics:\n";
$gmv = $paymentModel->getTotalGMV();
$totalOrders = $orderModel->count();
$verifiedStores = $storeModel->count(['is_verified' => 1]);
$trends = $paymentModel->getTransactionTrends(7);
$storesList = $storeModel->getAllWithStats();
$recentOrders = $orderModel->getRecentOrders(5);

echo "  - Total GMV: " . format_rupiah($gmv) . "\n";
echo "  - Total Orders: {$totalOrders}\n";
echo "  - Verified Stores: {$verifiedStores}\n";
echo "  - 7-Day Chart Labels: " . implode(', ', $trends['labels']) . "\n";
echo "  - Stores Monitored: " . count($storesList) . " stores\n";
echo "  - Recent Orders Sample: " . count($recentOrders) . " orders\n";

assert($gmv > 0, "GMV should be greater than 0");
assert(count($trends['sales']) === 7, "Trends should have 7 data points");
assert(count($storesList) === 2, "Expected 2 stores");
echo "  => Test 1 (Platform Dashboard) PASSED!\n\n";

// 2. Store Owner Dashboard Verification (CleanWash: User ID 3)
echo "[Test 2] Store Owner Dashboard (CleanWash Express):\n";
$ownerStore = $storeModel->findForUser(3, 'owner_toko');
assert($ownerStore !== null, "Store for user 3 not found");
echo "  - Store: {$ownerStore['name']} (Owner: {$ownerStore['owner_name']})\n";

$storeStats = $storeModel->getStoreSummary((int)$ownerStore['id']);
echo "  - Summary -> Orders Today: {$storeStats['orders_today']}, Pending: {$storeStats['orders_pending']}, Monthly Revenue: " . format_rupiah($storeStats['monthly_revenue']) . ", Rating: {$storeStats['rating_avg']}\n";

$topServices = $storeModel->getTopServices((int)$ownerStore['id'], 3);
echo "  - Top Services count: " . count($topServices) . "\n";
foreach ($topServices as $ts) {
    echo "    • {$ts['name']} -> " . format_rupiah($ts['total_sales']) . " ({$ts['total_quantity']} {$ts['unit']})\n";
}

$pendingOrders = $orderModel->getStorePendingOrders((int)$ownerStore['id']);
echo "  - Pending Orders to Accept/Reject: " . count($pendingOrders) . " orders\n";
echo "  => Test 2 (Store Owner Dashboard) PASSED!\n\n";

// 3. Store Admin Dashboard Verification (CleanWash Admin: User ID 5)
echo "[Test 3] Store Admin Dashboard Queues & Actions (CleanWash):\n";
$adminStore = $storeModel->findForUser(5, 'admin_toko');
assert($adminStore !== null, "Store for admin 5 not found");
echo "  - Admin Staff: {$adminStore['name']} (Role in Store: {$adminStore['role_in_store']})\n";

$weighingQueue = $orderModel->getStoreWeighingQueue((int)$adminStore['id']);
echo "  - Weighing Queue: " . count($weighingQueue) . " orders\n";

$paymentQueue = $orderModel->getStorePaymentQueue((int)$adminStore['id']);
echo "  - Payment Queue: " . count($paymentQueue) . " orders\n";

$washingQueue = $orderModel->getStoreWashingQueue((int)$adminStore['id']);
echo "  - Washing Queue: " . count($washingQueue) . " orders\n";

// Test weighing submission logic on a temporary test
echo "  - Testing Weighing Submission & Automatic QRIS calculation...\n";
$orderModel->submitWeighing(1, 4.2, 15, "Spot treatment noda kopi", 5);
$updatedOrder = $orderModel->findWithDetails(1);
echo "    • Order #1 Total Price updated to: " . format_rupiah($updatedOrder['total_price']) . " (Status: {$updatedOrder['status']})\n";
echo "    • QRIS Payment Code: {$updatedOrder['payment_code']} (Amount: " . format_rupiah($updatedOrder['payment_amount']) . ")\n";

assert((float)$updatedOrder['total_price'] > 0, "Calculated total price must be > 0");
assert($updatedOrder['status'] === 'MENUNGGU_PEMBAYARAN', "Status must transition to MENUNGGU_PEMBAYARAN");

echo "  => Test 3 (Store Admin Operational Workflows) PASSED!\n\n";

echo "=================================================================\n";
echo " ALL TAHAP 4a DASHBOARD TESTS PASSED (100%)\n";
echo "=================================================================\n";
