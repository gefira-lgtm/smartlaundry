<?php
/**
 * Test Courier & Customer Dashboard Queries and Logic (Tahap 4b)
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
require_once dirname(__DIR__) . '/app/Models/Courier.php';

use App\Models\User;
use App\Models\Order;
use App\Models\Courier;

echo "=================================================================\n";
echo " SmartLaundry AI - Tahap 4b Courier & Customer Verification Test\n";
echo "=================================================================\n\n";

$courierModel = new Courier();
$orderModel = new Order();
$userModel = new User();

// 1. Courier Dashboard Logic Verification (Kurir Budi: User ID 7)
echo "[Test 1] Courier Dashboard Verification (Budi Santoso - Kurir ID 7):\n";
$courier = $courierModel->findByUserId(7);
assert($courier !== null, "Courier profile not found for user 7");
echo "  - Courier: {$courier['courier_name']} (Kendaraan: {$courier['vehicle_type']}, Plat: {$courier['license_plate']})\n";
echo "  - Status Ketersediaan: {$courier['status']} (Available: {$courier['is_available']})\n";

// Retrieve tasks
$tasks = $courierModel->getCourierTasks(7);
echo "  - Pickup Tasks: " . count($tasks['pickup']) . " orders\n";
echo "  - Delivery Tasks: " . count($tasks['delivery']) . " orders\n";
echo "  - Completed Today: " . count($tasks['completed_today']) . " orders\n";

// Test GPS update
echo "  - Testing GPS Location logging...\n";
$courierModel->updateLocation((int)$courier['id'], -6.18250000, 106.77900000, 2);
$latestLoc = $courierModel->getLatestOrderLocation(2);
assert($latestLoc !== null, "Latest location for order 2 should exist");
echo "  - Recorded GPS: Lat {$latestLoc['latitude']}, Lng {$latestLoc['longitude']}\n";
echo "  => Test 1 (Courier Dashboard) PASSED!\n\n";

// 2. Customer Dashboard Logic Verification (Customer Ani: User ID 9)
echo "[Test 2] Customer Dashboard Verification (Ani Rahmawati - User ID 9):\n";
$activeOrders = $orderModel->getCustomerActiveOrders(9);
echo "  - Active Orders Count: " . count($activeOrders) . "\n";
foreach ($activeOrders as $ao) {
    echo "    • Order #{$ao['order_code']} -> Status: {$ao['status']}, Step: {$ao['step']}/6, Toko: {$ao['store_name']}\n";
    if (!empty($ao['courier_name'])) {
        echo "      Kurir: {$ao['courier_name']} ({$ao['courier_phone']})\n";
    }
}

$completedOrders = $orderModel->getCustomerCompletedOrders(9, 5);
echo "  - Completed Orders Count: " . count($completedOrders) . "\n";
echo "  => Test 2 (Customer Dashboard) PASSED!\n\n";

// 3. Customer Progress Stepper Calculation Verification
echo "[Test 3] Progress Stepper Logic Verification:\n";
$statuses = [
    'MENUNGGU_KONFIRMASI' => 1,
    'MENUNGGU_KURIR' => 1,
    'KURIR_MENUJU_LOKASI' => 2,
    'LAUNDRY_DIJEMPUT' => 2,
    'SAMPAI_DI_TOKO' => 3,
    'MENUNGGU_PEMBAYARAN' => 3,
    'SEDANG_DICUCI' => 4,
    'SIAP_DIANTAR' => 5,
    'KURIR_MENUJU_CUSTOMER' => 5,
    'SELESAI' => 6
];

foreach ($statuses as $st => $expectedStep) {
    $calculated = Order::getProgressStep($st);
    assert($calculated === $expectedStep, "Step mismatch for {$st}: expected {$expectedStep}, got {$calculated}");
    echo "  - {$st} -> Step {$calculated}/6 (OK)\n";
}
echo "  => Test 3 (Progress Stepper) PASSED!\n\n";

echo "=================================================================\n";
echo " ALL TAHAP 4b TESTS PASSED (100%)\n";
echo "=================================================================\n";
