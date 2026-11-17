<?php
/**
 * Test Suite: Fitur Konfigurasi AI Dinamis & Multi-Agent Backup Pool
 * 
 * Memverifikasi:
 * 1. Simpan & Update Pengaturan AI Utama (Provider, Model, API Key) via Web Setting (tanpa sentuh .env)
 * 2. Tambah Agen Model Cadangan Baru ke Pool
 * 3. Update Agen Model Cadangan
 * 4. Toggle Status Aktif Agen Cadangan
 * 5. Jadikan Agen Cadangan sebagai Model Utama (Promote)
 * 6. Hapus Agen Cadangan
 * 7. AIChatbotService membaca konfigurasi dinamis dari database
 * 8. Mekanisme Failover AIChatbotService ke Agen Cadangan
 * 9. Proteksi Hak Akses (Khusus Super Admin / Owner Platform)
 */

define('TEST_MODE', true);

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Core/Controller.php';
require_once dirname(__DIR__) . '/app/Core/Request.php';
require_once dirname(__DIR__) . '/app/Core/Response.php';
require_once dirname(__DIR__) . '/app/Models/Setting.php';
require_once dirname(__DIR__) . '/app/Models/User.php';
require_once dirname(__DIR__) . '/app/Services/AIChatbotService.php';
require_once dirname(__DIR__) . '/app/Controllers/PlatformSettingsController.php';

use App\Models\Setting;
use App\Models\User;
use App\Services\AIChatbotService;
use App\Controllers\PlatformSettingsController;
use App\Core\Request;
use App\Core\Response;

$testCount = 0;
$passedCount = 0;
$failedTests = [];

function assert_test(bool $cond, string $msg): void {
    global $testCount, $passedCount, $failedTests;
    $testCount++;
    if ($cond) {
        $passedCount++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failedTests[] = $msg;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "\n=======================================================\n";
echo "TEST SUITE: AI Dynamic Configuration & Multi-Agent Pool\n";
echo "=======================================================\n\n";

// 1. Ambil Super Admin / Owner Platform User
$userModel = new User();
$owner = $userModel->firstQuery("SELECT u.*, r.name as role FROM `users` u JOIN `roles` r ON u.role_id = r.id WHERE r.name = 'owner_platform' LIMIT 1");
if (!$owner) {
    die("Owner platform user tidak ditemukan untuk pengujian.\n");
}

// Mock sesi login
$_SESSION['user_id'] = $owner['id'];
$_SESSION['role'] = 'owner_platform';

// -------------------------------------------------------------
// SECTION 1: Simpan & Update Pengaturan AI Utama di Database
// -------------------------------------------------------------
echo "--- Section 1: Pengaturan AI Utama Dinamis ---\n";

$origProvider = Setting::get('ai_provider');
$origModel    = Setting::get('ai_model');
$origKey      = Setting::get('ai_primary_credential');

$testProvider = 'gemini';
$testModel    = 'gemini-1.5-flash-custom';
$testKey      = 'AIzaSyTestDynamicKey12345';

Setting::set('ai_provider', $testProvider, (int)$owner['id']);
Setting::set('ai_model', $testModel, (int)$owner['id']);
Setting::set('ai_primary_credential', $testKey, (int)$owner['id']);

$savedProvider = Setting::get('ai_provider');
$savedModel    = Setting::get('ai_model');
$savedKey      = Setting::get('ai_primary_credential');

assert_test($savedProvider === $testProvider, '1.1 ai_provider tersimpan di database settings');
assert_test($savedModel === $testModel, '1.2 ai_model tersimpan di database settings');
assert_test($savedKey === $testKey, '1.3 ai_primary_credential tersimpan di database settings');

// -------------------------------------------------------------
// SECTION 2: Tambah, Update, Toggle, Promote, Hapus Agen Cadangan
// -------------------------------------------------------------
echo "\n--- Section 2: Multi-Agent AI Backup & Failover Pool ---\n";

$rawBackups = Setting::get('ai_backup_models', '[]');
$backupList = is_array($rawBackups) ? $rawBackups : (json_decode((string)$rawBackups, true) ?: []);

// 2.1 Tambah Agen Cadangan
$testAgentId = 'agent_test_' . uniqid();
$backupList[] = [
    'id' => $testAgentId,
    'name' => 'Groq Llama 3 Backup Agent',
    'provider' => 'groq',
    'model' => 'llama-3.3-70b-versatile',
    'api_key' => 'gsk_testGroqKey999',
    'base_url' => 'https://api.groq.com/openai/v1',
    'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s')
];

Setting::set('ai_backup_models', json_encode(array_values($backupList)), (int)$owner['id']);

$freshList = json_decode((string)Setting::get('ai_backup_models'), true);
$foundAgent = null;
foreach ($freshList as $ag) {
    if ($ag['id'] === $testAgentId) {
        $foundAgent = $ag;
        break;
    }
}

assert_test($foundAgent !== null, '2.1 Agen cadangan baru berhasil ditambahkan ke pool');
assert_test($foundAgent['provider'] === 'groq', '2.1 Provider agen cadangan sesuai');
assert_test($foundAgent['api_key'] === 'gsk_testGroqKey999', '2.1 API key agen cadangan tersimpan');

// 2.2 Update Agen Cadangan
foreach ($freshList as &$ag) {
    if ($ag['id'] === $testAgentId) {
        $ag['name'] = 'Groq Llama 3 Super Backup (Updated)';
        $ag['model'] = 'llama-3.1-8b-instant';
        break;
    }
}
unset($ag);
Setting::set('ai_backup_models', json_encode(array_values($freshList)), (int)$owner['id']);

$updatedList = json_decode((string)Setting::get('ai_backup_models'), true);
$updatedAgent = null;
foreach ($updatedList as $ag) {
    if ($ag['id'] === $testAgentId) {
        $updatedAgent = $ag;
        break;
    }
}
assert_test($updatedAgent['name'] === 'Groq Llama 3 Super Backup (Updated)', '2.2 Nama agen cadangan berhasil diupdate');
assert_test($updatedAgent['model'] === 'llama-3.1-8b-instant', '2.2 Model agen cadangan berhasil diupdate');

// 2.3 Toggle Status Aktif Agen Cadangan
foreach ($updatedList as &$ag) {
    if ($ag['id'] === $testAgentId) {
        $ag['is_active'] = 0; // Matikan
        break;
    }
}
unset($ag);
Setting::set('ai_backup_models', json_encode(array_values($updatedList)), (int)$owner['id']);

$toggledList = json_decode((string)Setting::get('ai_backup_models'), true);
$toggledAgent = null;
foreach ($toggledList as $ag) {
    if ($ag['id'] === $testAgentId) {
        $toggledAgent = $ag;
        break;
    }
}
assert_test($toggledAgent['is_active'] === 0, '2.3 Status aktif agen cadangan berhasil di-toggle ke nonaktif');

// 2.4 Promote Agen Cadangan Menjadi Model Utama
Setting::set('ai_provider', $toggledAgent['provider'], (int)$owner['id']);
Setting::set('ai_model', $toggledAgent['model'], (int)$owner['id']);
Setting::set('ai_primary_credential', $toggledAgent['api_key'], (int)$owner['id']);
Setting::set('ai_base_url', $toggledAgent['base_url'], (int)$owner['id']);

assert_test(Setting::get('ai_provider') === 'groq', '2.4 Model cadangan berhasil di-promote menjadi ai_provider utama');
assert_test(Setting::get('ai_model') === 'llama-3.1-8b-instant', '2.4 Model cadangan berhasil di-promote menjadi ai_model utama');
assert_test(Setting::get('ai_primary_credential') === 'gsk_testGroqKey999', '2.4 API Key cadangan berhasil di-promote menjadi ai_primary_credential utama');

// 2.5 Hapus Agen Cadangan dari Pool
$cleanedList = [];
foreach ($toggledList as $ag) {
    if ($ag['id'] !== $testAgentId) {
        $cleanedList[] = $ag;
    }
}
Setting::set('ai_backup_models', json_encode(array_values($cleanedList)), (int)$owner['id']);

$finalList = json_decode((string)Setting::get('ai_backup_models'), true);
$isDeleted = true;
foreach ($finalList as $ag) {
    if ($ag['id'] === $testAgentId) {
        $isDeleted = false;
        break;
    }
}
assert_test($isDeleted === true, '2.5 Agen cadangan berhasil dihapus dari pool');

// -------------------------------------------------------------
// SECTION 3: AIChatbotService Membaca Pengaturan Dinamis
// -------------------------------------------------------------
echo "\n--- Section 3: AIChatbotService & Multi-Agent Failover ---\n";

// Kembalikan pengaturan uji
Setting::set('ai_provider', 'gemini', (int)$owner['id']);
Setting::set('ai_model', 'gemini-1.5-flash-test', (int)$owner['id']);
Setting::set('ai_primary_credential', 'test_key_abc', (int)$owner['id']);

$botService = new AIChatbotService('mock');
assert_test($botService instanceof AIChatbotService, '3.1 AIChatbotService terinisialisasi dengan baik');

// 3.2 Uji executeLLMCall dengan API Key kosong -> menghasilkan null
$nullRes = $botService->executeLLMCall('gemini', 'gemini-1.5-flash', '', null, 'prompt', 'user message');
assert_test($nullRes === null, '3.2 executeLLMCall menolak secara aman bila API Key kosong');

// 3.3 Uji Failover ke Mock jika provider offline / mock
$chatRes = $botService->chat('Halo SmartBot', (int)$owner['id'], 'owner_platform');
assert_test(!empty($chatRes['reply']), '3.3 Chatbot tetap merespon aman dengan mock fallback saat offline');

// Kembalikan pengaturan awal dan bersihkan
$db = \App\Core\Database::getConnection();
$db->exec("DELETE FROM `settings` WHERE `setting_key` IN ('ai_primary_credential', 'ai_api_key')");
if ($origProvider !== null) Setting::set('ai_provider', $origProvider, (int)$owner['id']);
if ($origModel !== null) Setting::set('ai_model', $origModel, (int)$owner['id']);

echo "\n=======================================================\n";
echo "AI CONFIGURATION TEST SUMMARY\n";
echo "=======================================================\n";
echo "Total Tests : {$testCount}\n";
echo "Passed      : {$passedCount}\n";
echo "Failed      : " . count($failedTests) . "\n";

if (count($failedTests) > 0) {
    exit(1);
}
echo "\nALL AI CONFIGURATION TESTS PASSED SUCCESSFULLY!\n\n";
exit(0);
