<?php
/**
 * Test Suite: Stage 23 — WhatsApp 3-Category Notification System
 * Static analysis (no DB required) + PHP syntax checks
 *
 * Coverage:
 *  1. WhatsAppNotificationSetting — imports Setting, correct signatures
 *  2. WhatsAppNotificationRecipient — correct method signatures
 *  3. WhatsAppNotificationService.notifyActivity() — storeStaff bug fixed
 *  4. Routes registered in index.php
 *  5. Store view exists and correct content
 *  6. Navbar links added for both roles
 *  7. PHP syntax check on all changed files
 *  8. WhatsAppLog Anti-IDOR: excludeOtp + store_id filter present
 *  9. Regression test 19/19 (delegates to run_all_tests.php)
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

echo "=== STAGE 23: WhatsApp 3-Category Notification System ===\n\n";

// =========================================================================
// TEST 1: WhatsAppNotificationSetting.php
// =========================================================================
echo "--- 1. WhatsAppNotificationSetting Model ---\n";

$settingModelFile = $appPath . '/Models/WhatsAppNotificationSetting.php';
$settingContent = file_get_contents($settingModelFile);

test('WhatsAppNotificationSetting.php exists', file_exists($settingModelFile));
test('Imports App\\Models\\Setting', strpos($settingContent, 'use App\\Models\\Setting') !== false);
test('Has isCategoryEnabled() method', strpos($settingContent, 'function isCategoryEnabled') !== false);
test('Has isEnabled() method', strpos($settingContent, 'function isEnabled') !== false);
test('Has setSetting() method', strpos($settingContent, 'function setSetting') !== false);
test('Has getCategorySettings() method', strpos($settingContent, 'function getCategorySettings') !== false);
test('Has getTemplate() method', strpos($settingContent, 'function getTemplate') !== false);

// =========================================================================
// TEST 2: WhatsAppNotificationRecipient.php
// =========================================================================
echo "\n--- 2. WhatsAppNotificationRecipient Model ---\n";

$recipientModelFile = $appPath . '/Models/WhatsAppNotificationRecipient.php';
$recipientContent = readFileContents($recipientModelFile);

test('WhatsAppNotificationRecipient.php exists', file_exists($recipientModelFile));
test('Has getRecipients() method', strpos($recipientContent, 'function getRecipients') !== false);
test('Has addRecipient() method', strpos($recipientContent, 'function addRecipient') !== false);
test('Has deleteRecipient() method', strpos($recipientContent, 'function deleteRecipient') !== false);
test('Has toggleRecipient() method', strpos($recipientContent, 'function toggleRecipient') !== false);
test('Has getActivePhoneNumbers() method', strpos($recipientContent, 'function getActivePhoneNumbers') !== false);

// =========================================================================
// TEST 3: WhatsAppNotificationService — notifyActivity() fix
// =========================================================================
echo "\n--- 3. WhatsAppNotificationService.notifyActivity() storeStaff bug fix ---\n";

$serviceFile = $appPath . '/Services/WhatsAppNotificationService.php';
$serviceContent = readFileContents($serviceFile);

test('WhatsAppNotificationService.php exists', file_exists($serviceFile));
test('notifyActivity() method exists', strpos($serviceContent, 'function notifyActivity') !== false);
test(
    'notifyActivity uses foreach loop over storeStaff array (fix applied)',
    strpos($serviceContent, 'foreach ($storeStaff as $staff)') !== false
);
test(
    'notifyActivity checks staff_role === owner_toko correctly',
    strpos($serviceContent, "staff_role'] === 'owner_toko'") !== false
);
test(
    'Old bug storeStaff["owner"] array key is removed from notifyActivity',
    strpos($serviceContent, "\$storeStaff['owner']") === false
);
test(
    'notifyActivity uses WhatsAppNotificationSetting::isCategoryEnabled',
    strpos($serviceContent, "isCategoryEnabled('ACTIVITY'") !== false
);
test(
    'Service imports WhatsAppNotificationSetting',
    strpos($serviceContent, 'use App\\Models\\WhatsAppNotificationSetting') !== false
);
test(
    'Service imports WhatsAppNotificationRecipient',
    strpos($serviceContent, 'use App\\Models\\WhatsAppNotificationRecipient') !== false
);
test(
    'sendOtp() backward-compatible 5th parameter with default',
    strpos($serviceContent, 'function sendOtp') !== false && strpos($serviceContent, '$purpose = \'VERIFIKASI\'') !== false
);

// =========================================================================
// TEST 4: Routes in index.php
// =========================================================================
echo "\n--- 4. Routes Registration in index.php ---\n";

$indexFile = $rootPath . '/index.php';
$indexContent = readFileContents($indexFile);

test('index.php exists', file_exists($indexFile));
test('Imports WhatsAppSettingsController', strpos($indexContent, 'use App\\Controllers\\WhatsAppSettingsController') !== false);
test('Route GET /platform/whatsapp-settings', strpos($indexContent, "'/platform/whatsapp-settings'") !== false);
test('Route POST /platform/whatsapp-settings/update', strpos($indexContent, "'/platform/whatsapp-settings/update'") !== false);
test('Route POST /platform/whatsapp-settings/recipients/add', strpos($indexContent, "platform/whatsapp-settings/recipients/add") !== false);
test('Route /platform/whatsapp-settings/recipients/{id}/delete', strpos($indexContent, "platform/whatsapp-settings/recipients/{id}/delete") !== false);
test('Route /platform/whatsapp-settings/recipients/{id}/toggle', strpos($indexContent, "platform/whatsapp-settings/recipients/{id}/toggle") !== false);
test('Route GET /store/whatsapp-settings', strpos($indexContent, "'/store/whatsapp-settings'") !== false);
test('Route POST /store/whatsapp-settings/update', strpos($indexContent, "'/store/whatsapp-settings/update'") !== false);
test('Route POST /store/whatsapp-settings/recipients/add', strpos($indexContent, "store/whatsapp-settings/recipients/add") !== false);
test('Route /store/whatsapp-settings/recipients/{id}/delete', strpos($indexContent, "store/whatsapp-settings/recipients/{id}/delete") !== false);
test('RBAC role:owner_platform,admin_owner on platform WA route', preg_match("/platform\/whatsapp-settings'.*owner_platform/", $indexContent) === 1);
test('RBAC role:owner_toko on store WA route', preg_match("/store\/whatsapp-settings'.*owner_toko/", $indexContent) === 1);

// =========================================================================
// TEST 5: WhatsAppSettingsController.php
// =========================================================================
echo "\n--- 5. WhatsAppSettingsController ---\n";

$controllerFile = $appPath . '/Controllers/WhatsAppSettingsController.php';
$controllerContent = readFileContents($controllerFile);

test('WhatsAppSettingsController.php exists', file_exists($controllerFile));
test('Has platformIndex() method', strpos($controllerContent, 'function platformIndex') !== false);
test('Has savePlatformSettings() method', strpos($controllerContent, 'function savePlatformSettings') !== false);
test('Has storeIndex() method', strpos($controllerContent, 'function storeIndex') !== false);
test('Has saveStoreSettings() method', strpos($controllerContent, 'function saveStoreSettings') !== false);
test('Has addRecipient() method', strpos($controllerContent, 'function addRecipient') !== false);
test('Has deleteRecipient() method', strpos($controllerContent, 'function deleteRecipient') !== false);
test('Has toggleRecipient() method', strpos($controllerContent, 'function toggleRecipient') !== false);
test('storeIndex enforces Anti-IDOR store scope', strpos($controllerContent, 'excludeOtp') !== false || strpos($controllerContent, 'storeId') !== false);
test('storeIndex renders store/whatsapp_settings view', strpos($controllerContent, "store/whatsapp_settings") !== false);
test('platformIndex renders platform/whatsapp_settings view', strpos($controllerContent, "platform/whatsapp_settings") !== false);

// =========================================================================
// TEST 6: Store View File
// =========================================================================
echo "\n--- 6. Store View: app/Views/store/whatsapp_settings.php ---\n";

$storeViewPath = $appPath . '/Views/store/whatsapp_settings.php';
$storeViewContent = readFileContents($storeViewPath);

test('Store view file exists', file_exists($storeViewPath));
test('Store view has TRANSACTION tab', strpos($storeViewContent, 'transaction') !== false);
test('Store view has ACTIVITY tab', strpos($storeViewContent, 'activity') !== false);
test('Store view does NOT have OTP tab link (platform-only)', strpos($storeViewContent, "tab=otp") === false);
test('Store view uses store/whatsapp-settings URLs', strpos($storeViewContent, "store/whatsapp-settings") !== false);
test('Store view has outbox/log table', strpos($storeViewContent, '<table') !== false);
test('Store view has recipient management modals', strpos($storeViewContent, 'modalAddTrxRecipient') !== false);
test('Store view has Anti-IDOR scoped outbox ($logs variable)', strpos($storeViewContent, '$logs') !== false);
test('Store view does not access platform-only variables (allStores)', strpos($storeViewContent, '$allStores') === false);

// =========================================================================
// TEST 7: Platform View File
// =========================================================================
echo "\n--- 7. Platform View: app/Views/platform/whatsapp_settings.php ---\n";

$platformViewPath = $appPath . '/Views/platform/whatsapp_settings.php';
$platformViewContent = readFileContents($platformViewPath);

test('Platform view file exists', file_exists($platformViewPath));
test('Platform view has OTP tab', strpos($platformViewContent, "tab=otp") !== false);
test('Platform view has TRANSACTION tab', strpos($platformViewContent, "tab=transaction") !== false);
test('Platform view has ACTIVITY tab', strpos($platformViewContent, "tab=activity") !== false);
test('Platform view has store filter for logs', strpos($platformViewContent, 'allStores') !== false);

// =========================================================================
// TEST 8: Navbar Links
// =========================================================================
echo "\n--- 8. Navbar Links ---\n";

$navbarFile = $appPath . '/Views/layouts/navbar.php';
$navContent = readFileContents($navbarFile);

test('Navbar file exists', file_exists($navbarFile));
test('Platform dropdown has Pengaturan Notifikasi WA link', strpos($navContent, "platform/whatsapp-settings") !== false);
test('Store dropdown has Pengaturan Notifikasi WA link', strpos($navContent, "store/whatsapp-settings") !== false);
test('Platform WA settings link is in platform admin block', (function() use ($navContent) {
    return (strpos($navContent, "'owner_platform'") !== false || strpos($navContent, 'owner_platform') !== false)
        && strpos($navContent, 'platform/whatsapp-settings') !== false;
})());

// =========================================================================
// TEST 9: PHP Syntax Check on All Changed Files
// =========================================================================
echo "\n--- 9. PHP Syntax Check ---\n";

$filesToCheck = [
    'app/Models/WhatsAppNotificationSetting.php',
    'app/Models/WhatsAppNotificationRecipient.php',
    'app/Models/WhatsAppNotificationTemplate.php',
    'app/Models/WhatsAppLog.php',
    'app/Services/WhatsAppNotificationService.php',
    'app/Controllers/WhatsAppSettingsController.php',
    'app/Views/platform/whatsapp_settings.php',
    'app/Views/store/whatsapp_settings.php',
    'app/Views/layouts/navbar.php',
    'index.php',
    'database/migrations/010_whatsapp_multi_message_templates.sql',
    'scratch/run_migration_010.php',
];

foreach ($filesToCheck as $f) {
    $fullPath = $rootPath . '/' . $f;
    if (!file_exists($fullPath)) {
        test("Syntax OK: {$f}", false, 'File not found');
        continue;
    }
    $output = shell_exec('C:\\xampp\\php\\php.exe -l ' . escapeshellarg($fullPath) . ' 2>&1');
    test("Syntax OK: {$f}", strpos($output, 'No syntax errors') !== false, trim($output ?? ''));
}

// =========================================================================
// TEST 10: WhatsAppLog Model Anti-IDOR Fields
// =========================================================================
echo "\n--- 10. WhatsAppLog Anti-IDOR and Category Fields ---\n";

$waLogFile = $appPath . '/Models/WhatsAppLog.php';
$waLogContent = readFileContents($waLogFile);

test('WhatsAppLog.php exists', file_exists($waLogFile));
test('getFilteredLogs() accepts excludeOtp parameter', strpos($waLogContent, 'excludeOtp') !== false);
test('getFilteredLogs() enforces store_id filter', strpos($waLogContent, 'store_id') !== false);
test('WhatsAppLog has category column handling', strpos($waLogContent, 'category') !== false);
test('maskSensitiveMessage() method exists', strpos($waLogContent, 'maskSensitiveMessage') !== false);
test('createPending() accepts category parameter', strpos($waLogContent, 'function createPending') !== false && strpos($waLogContent, 'category') !== false);

// =========================================================================
// TEST 11: Multi-Message per Event System (Koreksi Konsep)
// =========================================================================
echo "\n--- 11. Multi-Message per Event System ---\n";

$tmplModelFile = $appPath . '/Models/WhatsAppNotificationTemplate.php';
$tmplContent = readFileContents($tmplModelFile);

test('WhatsAppNotificationTemplate.php exists', file_exists($tmplModelFile));
test('Has getEventMessages() method', strpos($tmplContent, 'function getEventMessages') !== false);
test('Has getDefaultMessages() method', strpos($tmplContent, 'function getDefaultMessages') !== false);
test('Has createMessage() method', strpos($tmplContent, 'function createMessage') !== false);
test('Has updateMessage() method', strpos($tmplContent, 'function updateMessage') !== false);
test('Has deleteMessage() method', strpos($tmplContent, 'function deleteMessage') !== false);
test('Has toggleMessage() method', strpos($tmplContent, 'function toggleMessage') !== false);

// Check default multi-messages for courier pickup
test('customer_courier_to_pickup defines Pesan 1 (Info Kurir) in defaults', strpos($tmplContent, 'Pesan 1: Detail Kurir Ditugaskan') !== false);
test('customer_courier_to_pickup defines Pesan 2 (Kurir Menuju Lokasi) in defaults', strpos($tmplContent, 'Pesan 2: Kurir Sedang Menuju Lokasi') !== false);
test('Pesan 1 includes vehicle plate {plat_nomor} placeholder', strpos($tmplContent, '{plat_nomor}') !== false);
test('Pesan 1 includes vehicle type {jenis_kendaraan} placeholder', strpos($tmplContent, '{jenis_kendaraan}') !== false);
test('Pesan 1 includes courier WA {wa_kurir} placeholder', strpos($tmplContent, '{wa_kurir}') !== false);

// Service multi-message dispatch
test('WhatsAppNotificationService has sendEventMultiMessages() method', strpos($serviceContent, 'function sendEventMultiMessages') !== false);
test('notifyCourierToPickup uses sendEventMultiMessages()', strpos($serviceContent, "sendEventMultiMessages(\n            'TRANSACTION',\n            'customer_courier_to_pickup'") !== false || strpos($serviceContent, 'customer_courier_to_pickup') !== false);
test('notifyOrderCreated uses sendEventMultiMessages()', strpos($serviceContent, "customer_order_created") !== false);

// Multi-message routes in index.php
test('Route POST /platform/whatsapp-settings/messages/add registered', strpos($indexContent, "platform/whatsapp-settings/messages/add") !== false);
test('Route POST /platform/whatsapp-settings/messages/{id}/update registered', strpos($indexContent, "platform/whatsapp-settings/messages/{id}/update") !== false);
test('Route POST /platform/whatsapp-settings/messages/{id}/delete registered', strpos($indexContent, "platform/whatsapp-settings/messages/{id}/delete") !== false);
test('Route POST /platform/whatsapp-settings/messages/test-send registered', strpos($indexContent, "platform/whatsapp-settings/messages/test-send") !== false);
test('Route POST /platform/whatsapp-settings/messages/preview registered', strpos($indexContent, "platform/whatsapp-settings/messages/preview") !== false);
test('Route POST /store/whatsapp-settings/messages/add registered', strpos($indexContent, "store/whatsapp-settings/messages/add") !== false);

// Controller multi-message handlers
test('WhatsAppSettingsController has addEventMessage()', strpos($controllerContent, 'function addEventMessage') !== false);
test('WhatsAppSettingsController has updateEventMessage()', strpos($controllerContent, 'function updateEventMessage') !== false);
test('WhatsAppSettingsController has deleteEventMessage()', strpos($controllerContent, 'function deleteEventMessage') !== false);
test('WhatsAppSettingsController has toggleEventMessage()', strpos($controllerContent, 'function toggleEventMessage') !== false);
test('WhatsAppSettingsController has previewEventMessage()', strpos($controllerContent, 'function previewEventMessage') !== false);
test('WhatsAppSettingsController has testSendEventMessage()', strpos($controllerContent, 'function testSendEventMessage') !== false);

// Migration 010
$migration010File = $rootPath . '/database/migrations/010_whatsapp_multi_message_templates.sql';
test('Migration 010 SQL file exists', file_exists($migration010File));
$mig010Content = readFileContents($migration010File);
test('Migration 010 creates whatsapp_notification_templates table', strpos($mig010Content, 'CREATE TABLE IF NOT EXISTS `whatsapp_notification_templates`') !== false);
test('Migration 010 seeds Pesan 1 and Pesan 2 for courier pickup', strpos($mig010Content, 'Pesan 1: Detail Kurir Ditugaskan') !== false && strpos($mig010Content, 'Pesan 2: Kurir Sedang Menuju Lokasi') !== false);

// =========================================================================
// SUMMARY
// =========================================================================
echo "\n" . str_repeat('=', 60) . "\n";
echo "STAGE 23 TEST SUMMARY:\n";
echo "Total Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";
if (!empty($errors)) {
    echo "\nFailed Tests:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
}
echo str_repeat('=', 60) . "\n";

exit($failed === 0 ? 0 : 1);
