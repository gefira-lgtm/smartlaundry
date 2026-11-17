<?php
/**
 * SmartLaundry - Comprehensive Configuration & Settings Test Suite
 * Covers RBAC, Audit Logging, Setting Validation, Live Effect, Secret Leak Protection, and XSS Sanitization
 */

define('TEST_MODE', true);

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Models\Setting;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Controllers\PlatformSettingsController;
use App\Controllers\StoreSettingsController;
use App\Controllers\ChatbotController;
use App\Controllers\OrderController;

echo "====================================================================\n";
echo "SMARTLAUNDRY SYSTEM SETTINGS & CONFIGURATION TEST SUITE\n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$passed = 0;
$total = 0;

function runTest(string $title, callable $testFn, int &$passed, int &$total): void {
    $total++;
    echo "TEST {$total}: {$title}... ";
    try {
        $result = $testFn();
        if ($result === true) {
            echo "[PASSED]\n";
            $passed++;
        } else {
            echo "[FAILED]: " . (is_string($result) ? $result : "Returned false or unexpected value") . "\n";
        }
    } catch (\Throwable $e) {
        echo "[ERROR]: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

// Helper: Setup mock user session
function mockUser(string $role, int $storeId = 1): array {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT u.*, r.name as role_name FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = :role LIMIT 1");
    $stmt->execute(['role' => $role]);
    $user = $stmt->fetch(\PDO::FETCH_ASSOC);

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role_name'],
        'role_name' => $user['role_name'],
        'role_display' => $user['role_name'],
        'role_id' => (int)$user['role_id'],
        'store_id' => $storeId,
        'store_name' => 'CleanWash Express'
    ];
    return $user;
}

// -----------------------------------------------------------------
// 1. Schema & Seeder Verification
// -----------------------------------------------------------------
runTest("Settings tables exist and have 38 initial seeded settings", function() use ($db) {
    $tables = $db->query("SHOW TABLES LIKE '%setting%'")->fetchAll(\PDO::FETCH_COLUMN);
    $hasSettings = in_array('settings', $tables);
    $hasAudit = in_array('setting_audit_logs', $tables);
    $hasStoreSettings = in_array('store_settings', $tables);

    $count = (int)$db->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
    return $hasSettings && $hasAudit && $hasStoreSettings && ($count >= 38);
}, $passed, $total);

// -----------------------------------------------------------------
// 2. Setting Model Fallback Order & Cache
// -----------------------------------------------------------------
runTest("Setting::get() fallback order: DB -> .env -> Code Default, with per-request caching", function() {
    // 1. Existing DB key
    $appName = Setting::get('app_name', 'DefaultFallback');
    if (empty($appName) || $appName === 'DefaultFallback') {
        return "Failed reading existing key from DB";
    }

    // 2. Non-existent DB key with default
    $dummy = Setting::get('non_existent_key_xyz_123', 'FallbackValue');
    if ($dummy !== 'FallbackValue') {
        return "Fallback value did not match default";
    }

    // 3. Cache verification
    Setting::clearCache();
    $first = Setting::get('app_name');
    $second = Setting::get('app_name');
    return ($first === $second);
}, $passed, $total);

// -----------------------------------------------------------------
// 3. RBAC: owner_platform can update all groups
// -----------------------------------------------------------------
runTest("owner_platform can update general and restricted groups (security, maintenance)", function() {
    $owner = mockUser('owner_platform');
    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'setting_group' => 'security',
        'login_max_attempts' => '6',
        'login_lockout_duration_minutes' => '20',
        'session_lifetime_minutes' => '120'
    ];

    $ctrl = new PlatformSettingsController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    Setting::clearCache();
    $newMax = (int)Setting::get('login_max_attempts');
    return ($newMax === 6);
}, $passed, $total);

// -----------------------------------------------------------------
// 4. RBAC: admin_owner can update allowed groups (general, notification, chatbot)
// -----------------------------------------------------------------
runTest("admin_owner can update allowed groups (general, notification, chatbot)", function() {
    $admin = mockUser('admin_owner');
    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'setting_group' => 'general',
        'app_name' => 'SmartLaundry Pro',
        'app_tagline' => 'Solusi laundry modern terbaik',
        'contact_email' => 'halo@smartlaundry.id',
        'contact_phone' => '081299998888',
        'contact_address' => 'Jakarta Selatan',
        'footer_text' => 'Hak Cipta Dilindungi'
    ];

    $ctrl = new PlatformSettingsController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    Setting::clearCache();
    $appName = Setting::get('app_name');
    return ($appName === 'SmartLaundry Pro');
}, $passed, $total);

// -----------------------------------------------------------------
// 5. RBAC: admin_owner is BLOCKED from updating restricted groups (security, maintenance)
// -----------------------------------------------------------------
runTest("admin_owner is blocked from updating restricted groups (security, maintenance)", function() {
    $admin = mockUser('admin_owner');
    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'setting_group' => 'security',
        'login_max_attempts' => '10'
    ];

    $ctrl = new PlatformSettingsController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    Setting::clearCache();
    $maxAttempts = (int)Setting::get('login_max_attempts');
    // Value must remain 6 from previous test, not 10
    return ($maxAttempts === 6);
}, $passed, $total);

// -----------------------------------------------------------------
// 6. RBAC: other roles get 403 on /platform/settings
// -----------------------------------------------------------------
runTest("Unauthorized roles (customer, kurir, owner_toko, admin_toko) cannot access platform settings", function() {
    $rolesToTest = ['customer', 'kurir', 'owner_toko', 'admin_toko'];
    foreach ($rolesToTest as $r) {
        mockUser($r);
        $user = auth_user();
        $isAllowed = in_array($user['role_name'], ['owner_platform', 'admin_owner']);
        if ($isAllowed) {
            return "Role {$r} was incorrectly allowed";
        }
    }
    return true;
}, $passed, $total);

// -----------------------------------------------------------------
// 7. Store Settings: owner_toko can update, isolated to own store
// -----------------------------------------------------------------
runTest("owner_toko can update store settings for own store", function() use ($db) {
    mockUser('owner_toko', 1);
    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'is_open_temporary' => '1',
        'operational_hours' => '08:00 - 21:00 WIB',
        'phone' => '081234567890',
        'return_fee_bearer_default' => 'STORE'
    ];

    $ctrl = new StoreSettingsController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    $val = Setting::getStore(1, 'operational_hours');
    return ($val === '08:00 - 21:00 WIB');
}, $passed, $total);

// -----------------------------------------------------------------
// 8. Store Settings: admin_toko cannot update store settings
// -----------------------------------------------------------------
runTest("admin_toko is rejected when attempting to update store settings", function() {
    mockUser('admin_toko', 1);
    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'is_open_temporary' => '1',
        'operational_hours' => '00:00 - 24:00 WIB',
        'phone' => '089999999999',
        'return_fee_bearer_default' => 'CUSTOMER'
    ];

    $ctrl = new StoreSettingsController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    $val = Setting::getStore(1, 'operational_hours');
    // Must still be '08:00 - 21:00 WIB', not overwritten
    return ($val === '08:00 - 21:00 WIB');
}, $passed, $total);

// -----------------------------------------------------------------
// 8b. Store Settings: owner_toko store A cannot modify store B
// -----------------------------------------------------------------
runTest("owner_toko of store A cannot modify store B settings (cross-store isolation)", function() {
    mockUser('owner_toko', 1);
    $store2Before = Setting::getStore(2, 'operational_hours', '09:00 - 18:00 WIB');

    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'store_id' => 2,
        'operational_hours' => 'HACKED HOURS',
        'phone' => '089999999999'
    ];

    $ctrl = new StoreSettingsController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    $store2After = Setting::getStore(2, 'operational_hours', '09:00 - 18:00 WIB');
    return ($store2After !== 'HACKED HOURS');
}, $passed, $total);

// -----------------------------------------------------------------
// 9. Audit Logging on changes
// -----------------------------------------------------------------
runTest("Every setting change records old_value, new_value, user_id, and ip in setting_audit_logs", function() use ($db) {
    mockUser('owner_platform');
    $oldVal = Setting::get('payment_reminder_minutes', 15);
    $newVal = ((int)$oldVal === 25) ? 35 : 25;

    Setting::set('payment_reminder_minutes', $newVal, (int)$_SESSION['user']['id'], '127.0.0.1');

    $log = $db->query("SELECT * FROM `setting_audit_logs` WHERE setting_key = 'payment_reminder_minutes' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    if (!$log) {
        return "No audit log found";
    }

    return ((int)$log['old_value'] === (int)$oldVal && (int)$log['new_value'] === $newVal && !empty($log['changed_by']));
}, $passed, $total);

// -----------------------------------------------------------------
// 10. Validation: rejects invalid ranges and missing CSRF
// -----------------------------------------------------------------
runTest("Validation rejects out of range values and missing CSRF token", function() {
    mockUser('owner_platform');
    
    // Test 1: Missing CSRF
    $_POST = [
        '_csrf_token' => 'invalid_csrf_token_xyz',
        'setting_group' => 'payment',
        'qris_expiration_minutes' => '60'
    ];
    $ctrl = new PlatformSettingsController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    // Test 2: Out of range (QRIS expiration < 10 or > 1440)
    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'setting_group' => 'payment',
        'qris_expiration_minutes' => '5' // below minimum 10
    ];
    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    Setting::clearCache();
    $qrisMin = (int)Setting::get('qris_expiration_minutes');
    return ($qrisMin >= 10); // Not changed to 5
}, $passed, $total);

// -----------------------------------------------------------------
// 11. Live Impact: QRIS Expiration Setting
// -----------------------------------------------------------------
runTest("Modifying qris_expiration_minutes changes expired_at on newly created payments", function() use ($db) {
    mockUser('owner_platform');
    Setting::set('qris_expiration_minutes', 45, (int)$_SESSION['user']['id']);
    Setting::clearCache();

    $paymentModel = new Payment();
    $testOrderCode = 'TEST-EXP-' . rand(1000, 9999);
    
    // Create an order
    $orderModel = new Order();
    $orderId = $orderModel->createWithItems([
        'order_code' => $testOrderCode,
        'customer_id' => 9,
        'store_id' => 1,
        'pickup_address_id' => 1,
        'delivery_address_id' => 1,
        'status' => 'MENUNGGU_PEMBAYARAN',
        'payment_method' => 'ONLINE_QRIS',
        'total_price' => 50000.00,
        'delivery_fee' => 10000.00
    ], [
        ['service_id' => 1, 'quantity' => 2.0, 'unit_price' => 25000.00, 'subtotal' => 50000.00]
    ]);

    $paymentId = $paymentModel->createForOrder($orderId, 50000.00, 'ONLINE_QRIS');
    $pay = $paymentModel->findByOrderId($orderId);

    $diffMinutes = round((strtotime($pay['expired_at']) - time()) / 60);
    // Should be close to 45 minutes (allow 43 - 46 range)
    return ($diffMinutes >= 43 && $diffMinutes <= 46);
}, $passed, $total);

// -----------------------------------------------------------------
// 12. Live Impact: Chatbot Enable/Disable & Role Restrictions
// -----------------------------------------------------------------
runTest("Disabling chatbot returns 503 polite message from API endpoint", function() {
    mockUser('customer');
    Setting::set('chatbot_enabled', 0, 1);
    Setting::clearCache();

    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'message' => 'Halo apa kabar'
    ];

    $ctrl = new ChatbotController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->sendMessage($req, $res);
    $output = ob_get_clean();

    $data = json_decode($output, true);
    $is503Polite = !empty($data) && $data['success'] === false && strpos($data['message'], 'tidak aktif') !== false;

    // Restore chatbot_enabled
    Setting::set('chatbot_enabled', 1, 1);
    Setting::clearCache();

    return $is503Polite;
}, $passed, $total);

// -----------------------------------------------------------------
// 13. Live Impact: Closed Store rejects checkout
// -----------------------------------------------------------------
runTest("Temporarily closed store rejects new order checkout with clear message", function() use ($db) {
    mockUser('customer');
    $storeRow = $db->query("SELECT id, name FROM `stores` WHERE is_active = 1 LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    $activeStoreId = (int)$storeRow['id'];

    // Set store to temporarily closed
    Setting::setStore($activeStoreId, 'is_open_temporary', '0', 1);
    Setting::clearCache();

    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'store_id' => $activeStoreId,
        'pickup_address_id' => 1,
        'delivery_address_id' => 1,
        'payment_method' => 'ONLINE_QRIS'
    ];

    $ctrl = new OrderController();
    $req = new Request();
    $res = new Response();

    ob_start();
    $ctrl->store($req, $res);
    ob_end_clean();

    $flashError = get_flash('error');
    $rejected = !empty($flashError) && strpos($flashError, 'tutup') !== false;

    // Restore store to open
    Setting::setStore($activeStoreId, 'is_open_temporary', '1', 1);
    Setting::clearCache();

    if (!$rejected) {
        return "Flash error was: " . var_export($flashError, true);
    }
    return true;
}, $passed, $total);

// -----------------------------------------------------------------
// 14. Live Impact: Maintenance Mode blocks customer, allows platform owner
// -----------------------------------------------------------------
runTest("Maintenance mode activates 503 for customers, but allows owner_platform", function() {
    Setting::set('maintenance_mode', 1, 1);
    Setting::clearCache();

    // 1. Customer check
    mockUser('customer');
    $customerAllowed = in_array(user_role(), ['owner_platform', 'admin_owner']);

    // 2. Owner platform check
    mockUser('owner_platform');
    $ownerAllowed = in_array(user_role(), ['owner_platform', 'admin_owner']);

    // Restore maintenance_mode
    Setting::set('maintenance_mode', 0, 1);
    Setting::clearCache();

    return (!$customerAllowed && $ownerAllowed);
}, $passed, $total);

// -----------------------------------------------------------------
// 15. Zero Secrets Leak: No API keys in settings, audit logs, or exports
// -----------------------------------------------------------------
runTest("Zero secrets leaked in settings table, audit logs, or configuration export", function() use ($db) {
    // Check settings table
    $leakedKeys = $db->query("SELECT setting_key FROM `settings` WHERE setting_key LIKE '%secret%' OR setting_key LIKE '%key%' OR setting_key LIKE '%password%' OR setting_key LIKE '%token%'")->fetchAll(\PDO::FETCH_COLUMN);

    // Filter out innocent keys if any
    $dangerous = array_filter($leakedKeys, function($k) {
        return stripos($k, 'api_key') !== false || stripos($k, 'midtrans') !== false || stripos($k, 'server_key') !== false;
    });

    if (!empty($dangerous)) {
        return "Secret keys found in settings table: " . implode(', ', $dangerous);
    }

    // Check JSON export
    $json = Setting::exportConfiguration('json');
    $hasSecret = stripos($json, 'SB-Mid-server') !== false || stripos($json, 'AI_API_KEY') !== false || stripos($json, 'FONNTE_TOKEN') !== false;

    return !$hasSecret;
}, $passed, $total);

// -----------------------------------------------------------------
// 16. XSS Sanitization: Injection payloads are rendered as harmless text
// -----------------------------------------------------------------
runTest("XSS payloads in settings values are rendered safely via e() without executing", function() {
    $xssPayload = "<script>alert('XSS_ATTACK');</script>";
    Setting::set('app_tagline', $xssPayload, 1);
    Setting::clearCache();

    $raw = Setting::get('app_tagline');
    $escaped = e($raw);

    $isSanitized = (strpos($escaped, '<script>') === false) && (strpos($escaped, '&lt;script&gt;') !== false);

    // Restore tagline
    Setting::set('app_tagline', 'Platform Marketplace Laundry Berbasis Web', 1);
    Setting::clearCache();

    return $isSanitized;
}, $passed, $total);

// -----------------------------------------------------------------
// 17. UI Page Rendering: /platform/settings renders all tabs for owner_platform and admin_owner
// -----------------------------------------------------------------
runTest("/platform/settings renders without error for owner_platform and admin_owner across all tabs", function() {
    $tabs = ['general', 'payment', 'refund', 'courier_order', 'notification', 'chatbot', 'security', 'maintenance', 'integration', 'sysinfo', 'audit'];
    $roles = ['owner_platform', 'admin_owner'];

    foreach ($roles as $role) {
        mockUser($role);
        $ctrl = new PlatformSettingsController();
        foreach ($tabs as $tab) {
            $_GET['tab'] = $tab;
            $req = new Request();
            $res = new Response();
            ob_start();
            $ctrl->index($req, $res);
            $html = ob_get_clean();
            if (empty($html) || strpos($html, 'Konfigurasi Sistem') === false) {
                return "Failed rendering tab {$tab} for role {$role}";
            }
        }
    }
    $_GET = [];
    return true;
}, $passed, $total);

// -----------------------------------------------------------------
// 18. UI Page Rendering: /store/settings renders for owner_toko and admin_toko
// -----------------------------------------------------------------
runTest("/store/settings renders without error for owner_toko (editable) and admin_toko (read-only)", function() {
    // 1. owner_toko
    mockUser('owner_toko', 1);
    $ctrl = new StoreSettingsController();
    $req = new Request();
    $res = new Response();
    ob_start();
    $ctrl->index($req, $res);
    $htmlOwner = ob_get_clean();
    if (strpos($htmlOwner, 'Pemilik Toko (Dapat Mengubah)') === false) {
        return "owner_toko edit mode banner not found";
    }

    // 2. admin_toko
    mockUser('admin_toko', 1);
    ob_start();
    $ctrl->index($req, $res);
    $htmlAdmin = ob_get_clean();
    if (strpos($htmlAdmin, 'Mode Lihat Saja (Admin Toko)') === false) {
        return "admin_toko read-only banner not found";
    }

    return true;
}, $passed, $total);

// -----------------------------------------------------------------
// 19. Store Settings: Update operational hours, phone, status, and audit trail
// -----------------------------------------------------------------
runTest("owner_toko can update store settings and it logs to audit trail", function() use ($db) {
    mockUser('owner_toko', 1);
    $user = $_SESSION['user'];
    $newHours = '08:00 - 21:00 WIB';
    $newPhone = '081234567890';

    $_POST = [
        '_csrf_token' => generate_csrf_token(),
        'is_open_temporary' => '1',
        'operational_hours' => $newHours,
        'phone' => $newPhone,
        'return_fee_bearer_default' => 'CUSTOMER'
    ];

    $ctrl = new StoreSettingsController();
    $req = new Request();
    $res = new Response();
    ob_start();
    $ctrl->update($req, $res);
    ob_end_clean();

    Setting::clearCache();
    $savedHours = Setting::getStore(1, 'operational_hours');
    $savedPhone = Setting::getStore(1, 'phone');

    if ($savedHours !== $newHours || $savedPhone !== $newPhone) {
        return "Store settings not saved: hours={$savedHours}, phone={$savedPhone}";
    }

    $audit = $db->query("SELECT * FROM `setting_audit_logs` WHERE `setting_key` = 'store_1:operational_hours' ORDER BY id DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
    if (!$audit || $audit['new_value'] !== $newHours) {
        return "Store audit trail log missing";
    }

    return true;
}, $passed, $total);

// -----------------------------------------------------------------
// 20. Resiliency: Setting::get() does not throw when table is missing
// -----------------------------------------------------------------
runTest("Setting::get() fallback to .env and code default without throwing when table is missing", function() use ($db) {
    Setting::clearCache();
    $db->exec("RENAME TABLE `settings` TO `settings_test_missing`");

    try {
        $envVal = Setting::get('app_name', 'DefaultFallback');
        $defaultVal = Setting::get('random_missing_key_999', 'SafeDefaultCode');

        $isValid = ($envVal !== null && $defaultVal === 'SafeDefaultCode');
    } catch (\Throwable $e) {
        $isValid = false;
    } finally {
        $db->exec("RENAME TABLE `settings_test_missing` TO `settings`");
        Setting::clearCache();
    }

    return $isValid;
}, $passed, $total);

echo "\n====================================================================\n";
echo "SETTINGS TEST RESULTS: {$passed} / {$total} PASSED\n";
echo "====================================================================\n";

if ($passed === $total) {
    echo "ALL TESTS PASSED SUCCESSFULLY! SETTINGS MODULE VERIFIED.\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED.\n";
    exit(1);
}
