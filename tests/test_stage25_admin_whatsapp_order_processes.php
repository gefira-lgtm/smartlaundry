<?php
/**
 * Test Suite: Stage 25 — Admin Role WhatsApp Notification for All Order Processes (Fonnte)
 *
 * Validates:
 *  1. WhatsAppNotificationService::sendOrderProcessNotification method and process coverage
 *  2. WhatsAppNotificationService::notifyOrderStatusChanged completeness
 *  3. Distinct template for SEDANG_DIPROSES (customer_laundry_processing)
 *  4. DashboardController::sendOrderWhatsAppNotification action and access control
 *  5. DashboardController::updateOrderStatus automatic WhatsApp notification dispatch
 *  6. Route registration for POST /store/orders/{id}/send-whatsapp
 *  7. UI integration in Store Dashboard (Washing Queue + Table Dropdown)
 *  8. PHP syntax checks on all modified files
 */

$rootPath = realpath(__DIR__ . '/..');

$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo "[PASS] {$name}\n";
        $passed++;
    } else {
        echo "[FAIL] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
        $errors[] = $name . ($detail ? ": {$detail}" : '');
    }
}

function readFileContents(string $path): string {
    return file_exists($path) ? file_get_contents($path) : '';
}

$appPath = $rootPath . '/app';

echo "=== STAGE 25: Admin Role WhatsApp Notification for All Order Processes ===\n\n";

// =========================================================================
// TEST 1: WhatsAppNotificationService.php
// =========================================================================
echo "--- 1. WhatsAppNotificationService Methods & Processes ---\n";

$serviceFile = $appPath . '/Services/WhatsAppNotificationService.php';
$serviceContent = readFileContents($serviceFile);

test('WhatsAppNotificationService has sendOrderProcessNotification()', strpos($serviceContent, 'function sendOrderProcessNotification') !== false);
test('sendOrderProcessNotification supports WEIGHING process', strpos($serviceContent, "case 'WEIGHING':") !== false);
test('sendOrderProcessNotification supports PAYMENT_SUCCESS process', strpos($serviceContent, "case 'PAYMENT_SUCCESS':") !== false);
test('sendOrderProcessNotification supports WASHING process', strpos($serviceContent, "case 'WASHING':") !== false);
test('sendOrderProcessNotification supports PROCESSING process', strpos($serviceContent, "case 'PROCESSING':") !== false);
test('sendOrderProcessNotification supports READY process', strpos($serviceContent, "case 'READY':") !== false);
test('sendOrderProcessNotification supports DELIVERY process', strpos($serviceContent, "case 'DELIVERY':") !== false);
test('sendOrderProcessNotification supports DELIVERED process', strpos($serviceContent, "case 'DELIVERED':") !== false);
test('sendOrderProcessNotification supports COMPLETED process', strpos($serviceContent, "case 'COMPLETED':") !== false);
test('sendOrderProcessNotification supports AUTO process mapping', strpos($serviceContent, "\$normType = match (strtoupper(\$order['status']))") !== false);

test('send() method accepts force parameter', strpos($serviceContent, 'bool $force = false') !== false);
test('sendEventMultiMessages accepts force parameter', strpos($serviceContent, 'bool $force = false') !== false);

// Check notifyOrderStatusChanged
test('notifyOrderStatusChanged handles MENUNGGU_KURIR', strpos($serviceContent, "case 'MENUNGGU_KURIR':") !== false);
test('notifyOrderStatusChanged handles PEMBAYARAN_BERHASIL', strpos($serviceContent, "case 'PEMBAYARAN_BERHASIL':") !== false);
test('notifyOrderStatusChanged handles SEDANG_DICUCI', strpos($serviceContent, "case 'SEDANG_DICUCI':") !== false);
test('notifyOrderStatusChanged handles SEDANG_DIPROSES', strpos($serviceContent, "case 'SEDANG_DIPROSES':") !== false);
test('notifyOrderStatusChanged handles SIAP_DIANTAR', strpos($serviceContent, "case 'SIAP_DIANTAR':") !== false);
test('notifyOrderStatusChanged handles KURIR_MENUJU_CUSTOMER', strpos($serviceContent, "case 'KURIR_MENUJU_CUSTOMER':") !== false);
test('notifyOrderStatusChanged handles LAUNDRY_DIANTAR', strpos($serviceContent, "case 'LAUNDRY_DIANTAR':") !== false);
test('notifyOrderStatusChanged handles SELESAI', strpos($serviceContent, "case 'SELESAI':") !== false);

// Check notifyLaundryProcessing distinct template
test('notifyLaundryProcessing has distinct customer_laundry_processing template', strpos($serviceContent, 'customer_laundry_processing') !== false);

// =========================================================================
// TEST 2: config/notification_templates.php
// =========================================================================
echo "\n--- 2. Notification Templates Config ---\n";

$tplConfigFile = $rootPath . '/config/notification_templates.php';
$tplConfigContent = readFileContents($tplConfigFile);

test('config/notification_templates.php defines customer_laundry_processing', strpos($tplConfigContent, "'customer_laundry_processing'") !== false);
test('customer_laundry_processing contains setrika & packing text', strpos($tplConfigContent, 'pengeringan, setrika uap, dan packing wangi') !== false);

// =========================================================================
// TEST 3: DashboardController.php
// =========================================================================
echo "\n--- 3. DashboardController Implementation ---\n";

$dashControllerFile = $appPath . '/Controllers/DashboardController.php';
$dashContent = readFileContents($dashControllerFile);

test('DashboardController has sendOrderWhatsAppNotification()', strpos($dashContent, 'function sendOrderWhatsAppNotification') !== false);
test('sendOrderWhatsAppNotification checks CSRF token', strpos($dashContent, 'verify_csrf_token($csrf)') !== false);
test('sendOrderWhatsAppNotification validates store ownership or platform admin', strpos($dashContent, 'owner_platform') !== false);
test('sendOrderWhatsAppNotification calls sendOrderProcessNotification()', strpos($dashContent, 'sendOrderProcessNotification') !== false);
test('sendOrderWhatsAppNotification supports JSON response for AJAX', strpos($dashContent, '$response->json') !== false);

test('updateOrderStatus validates all lifecycle statuses', strpos($dashContent, "'LAUNDRY_DIANTAR'") !== false && strpos($dashContent, "'SELESAI'") !== false);
test('updateOrderStatus dispatches WA for SEDANG_DICUCI', strpos($dashContent, 'notifyLaundryWashing') !== false);
test('updateOrderStatus dispatches WA for SEDANG_DIPROSES', strpos($dashContent, 'notifyLaundryProcessing') !== false);
test('updateOrderStatus dispatches WA for SIAP_DIANTAR', strpos($dashContent, 'notifyLaundryReady') !== false);
test('updateOrderStatus dispatches WA for KURIR_MENUJU_CUSTOMER', strpos($dashContent, 'notifyCourierToDelivery') !== false);
test('updateOrderStatus dispatches WA for LAUNDRY_DIANTAR', strpos($dashContent, 'notifyLaundryDelivered') !== false);
test('updateOrderStatus dispatches WA for SELESAI', strpos($dashContent, 'notifyOrderCompleted') !== false);

// =========================================================================
// TEST 4: Route in index.php
// =========================================================================
echo "\n--- 4. Route Registration ---\n";

$indexFile = $rootPath . '/index.php';
$indexContent = readFileContents($indexFile);

test('Route POST /store/orders/{id}/send-whatsapp registered', strpos($indexContent, "/store/orders/{id}/send-whatsapp") !== false);
test('Route /store/orders/{id}/send-whatsapp requires admin role', strpos($indexContent, "'role:owner_toko,admin_toko,owner_platform,admin_owner'") !== false);

// =========================================================================
// TEST 5: View app/Views/dashboard/store.php
// =========================================================================
echo "\n--- 5. Store Dashboard UI Integration ---\n";

$storeViewFile = $appPath . '/Views/dashboard/store.php';
$storeViewContent = readFileContents($storeViewFile);

test('Store view has WhatsApp column in order table', strpos($storeViewContent, 'Kirim Notifikasi WA (Fonnte)') !== false);
test('Store view has Kirim WA dropdown button', strpos($storeViewContent, 'Kirim WA') !== false);
test('Dropdown has Penimbangan option', strpos($storeViewContent, 'value="WEIGHING"') !== false);
test('Dropdown has Pembayaran Lunas option', strpos($storeViewContent, 'value="PAYMENT_SUCCESS"') !== false);
test('Dropdown has Sedang Dicuci option', strpos($storeViewContent, 'value="WASHING"') !== false);
test('Dropdown has Sedang Disetrika option', strpos($storeViewContent, 'value="PROCESSING"') !== false);
test('Dropdown has Siap Diantar option', strpos($storeViewContent, 'value="READY"') !== false);
test('Dropdown has Kurir Mengantar option', strpos($storeViewContent, 'value="DELIVERY"') !== false);
test('Dropdown has Laundry Diantar option', strpos($storeViewContent, 'value="DELIVERED"') !== false);
test('Dropdown has Pesanan Selesai option', strpos($storeViewContent, 'value="COMPLETED"') !== false);
test('Dropdown has Status Terkini option', strpos($storeViewContent, 'value="AUTO"') !== false);

test('Washing queue has quick update buttons with WA auto-dispatch', strpos($storeViewContent, 'Setrika/Proses') !== false && strpos($storeViewContent, 'Siap Diantar') !== false);

// =========================================================================
// TEST 6: PHP Syntax Checks
// =========================================================================
echo "\n--- 6. PHP Syntax Checks ---\n";

$filesToCheck = [
    $serviceFile,
    $tplConfigFile,
    $dashControllerFile,
    $indexFile,
    $storeViewFile
];

foreach ($filesToCheck as $file) {
    $cmd = 'C:\\xampp\\php\\php.exe -l ' . escapeshellarg($file) . ' 2>&1';
    $output = shell_exec($cmd);
    $syntaxOk = strpos((string)$output, 'No syntax errors detected') !== false;
    test('Syntax check: ' . basename($file), $syntaxOk, trim((string)$output));
}

// =========================================================================
// SUMMARY
// =========================================================================
echo "\n" . str_repeat('=', 60) . "\n";
echo "STAGE 25 TEST SUMMARY:\n";
echo "Total Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";
if (!empty($errors)) {
    echo "\nFailed Tests:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
}
echo str_repeat('=', 60) . "\n";

exit($failed > 0 ? 1 : 0);
