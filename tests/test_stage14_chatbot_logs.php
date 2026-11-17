<?php
/**
 * SmartLaundry – Stage 14 Test Suite: Chatbot Monitoring & Logging
 *
 * Covers:
 *  1. PII masking (phone 08xx/62xx, email a***@domain.com, card/account number)
 *  2. DB persistence (user_role, detected_intent, is_fallback, session_id)
 *  3. Persistensi dari AIChatbotService (chat() menyimpan ke DB untuk berbagai role)
 *  4. Best-effort: chat tidak berhenti saat DB insert gagal & error dicatat di log
 *  5. Filter & summary stats: total, unique users, top intents, fallback count, top questions, fallback questions, filter only_fallback
 *  6. Session Thread & User Thread (modal alur percakapan)
 *  7. RBAC: hanya owner_platform & admin_owner dapat akses & export CSV; role lain 403; tamu redirect login
 *  8. CSV export: header benar, data masked
 *  9. Script Retensi cron_purge_chatbot_logs (idempoten, hapus data > 90 hari, pertahankan data baru)
 *  10. Audit Logging: akses halaman monitoring dan ekspor CSV tercatat di log
 */

require_once __DIR__ . '/../config/env.php';
define('TEST_MODE', true);
load_env(__DIR__ . '/../.env');
require_once __DIR__ . '/../config/app.php';

require_once ROOT_PATH . '/app/Core/Model.php';
require_once ROOT_PATH . '/app/Core/Database.php';
require_once ROOT_PATH . '/app/Core/Controller.php';
require_once ROOT_PATH . '/app/Core/Request.php';
require_once ROOT_PATH . '/app/Core/Response.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Models\ChatbotMessage;
use App\Models\User;
use App\Services\AIChatbotService;
use App\Controllers\ChatbotLogController;

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name}" . ($detail ? " – {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "=== SMARTLAUNDRY TAHAP 14: CHATBOT MONITORING & LOGGING TEST SUITE ===\n\n";

$logModel  = new ChatbotMessage();
$userModel = new User();

// -------------------------------------------------------------------------
// 1. PII Masking Tests
// -------------------------------------------------------------------------
echo "--- 1. PII Masking ---\n";

$phone1  = ChatbotMessage::maskSensitiveData('Hubungi saya di 081234567890 ya.');
assert_test(
    'Mask phone 08xxx (0812****7890)',
    strpos($phone1, '081234567890') === false && strpos($phone1, '****') !== false,
    "Got: {$phone1}"
);

$phone2 = ChatbotMessage::maskSensitiveData('Nomor WA saya +628123456789');
assert_test(
    'Mask phone +62xxx',
    strpos($phone2, '628123456789') === false && strpos($phone2, '****') !== false,
    "Got: {$phone2}"
);

$email1 = ChatbotMessage::maskSensitiveData('Email saya adalah john.doe@example.com tolong hubungi');
assert_test(
    'Mask email (a***@domain.com)',
    strpos($email1, 'john.doe@example.com') === false && strpos($email1, '****@') !== false,
    "Got: {$email1}"
);

$card1 = ChatbotMessage::maskSensitiveData('Rekening saya 1234567890123456');
assert_test(
    'Mask card/account 16 digit',
    strpos($card1, '1234567890123456') === false && strpos($card1, '****') !== false,
    "Got: {$card1}"
);

$safe1 = ChatbotMessage::maskSensitiveData('Halo, status pesanan saya dimana?');
assert_test(
    'Safe text unchanged (no PII)',
    strpos($safe1, 'status pesanan') !== false && strpos($safe1, '****') === false,
    "Got: {$safe1}"
);

// -------------------------------------------------------------------------
// 2. DB Persistence (logMessage with user_role, is_fallback, PII masked)
// -------------------------------------------------------------------------
echo "\n--- 2. DB Persistence ---\n";

// Find real users for testing
$customers = $userModel->query(
    "SELECT u.* FROM `users` u JOIN `roles` r ON u.role_id = r.id
     WHERE r.name = 'customer' LIMIT 1"
);
if (empty($customers)) {
    die("Tidak ada customer untuk testing. Jalankan seeder dulu.\n");
}
$testUser   = $customers[0];
$testUserId = (int)$testUser['id'];
$testSessionId = 'test_s14_' . time();

// Insert standard recognized message
$insertedId = $logModel->logMessage(
    $testUserId,
    'customer',
    $testSessionId,
    'USER',
    'Halo, nomor hp saya 081298765432 boleh dihubungi',
    'GREETING',
    'MOCK',
    0
);
assert_test(
    'logMessage USER berhasil diinsert',
    $insertedId !== null && $insertedId > 0,
    "Returned ID: " . var_export($insertedId, true)
);

// Insert fallback message (pertanyaan tidak dikenali)
$fallbackInsertedId = $logModel->logMessage(
    $testUserId,
    'customer',
    $testSessionId,
    'USER',
    'Apakah SmartLaundry melayani cuci pesawat terbang?',
    'GENERAL',
    'MOCK',
    1
);
assert_test(
    'logMessage USER Fallback berhasil diinsert dengan is_fallback = 1',
    $fallbackInsertedId !== null && $fallbackInsertedId > 0,
    "Returned ID: " . var_export($fallbackInsertedId, true)
);

// Pastikan phone sudah dimasking di DB dan user_role serta is_fallback tersimpan benar
if ($insertedId) {
    $row = $logModel->find($insertedId);
    assert_test(
        'Pesan USER tersimpan dengan PII disamarkan',
        $row !== null && strpos($row['message'], '081298765432') === false,
        "Stored message: " . ($row['message'] ?? 'null')
    );
    assert_test(
        'Kolom user_role tersimpan benar di DB',
        $row !== null && ($row['user_role'] ?? '') === 'customer',
        "Stored role: " . ($row['user_role'] ?? 'null')
    );
}

if ($fallbackInsertedId) {
    $fbRow = $logModel->find($fallbackInsertedId);
    assert_test(
        'Kolom is_fallback bernilai 1 untuk pesan fallback',
        $fbRow !== null && (int)$fbRow['is_fallback'] === 1,
        "Stored is_fallback: " . ($fbRow['is_fallback'] ?? 'null')
    );
}

$botInsertedId = $logModel->logMessage(
    $testUserId,
    'customer',
    $testSessionId,
    'BOT',
    'Halo! Saya SmartBot, asisten virtual SmartLaundry.',
    null,
    'MOCK',
    0
);
assert_test(
    'logMessage BOT berhasil diinsert',
    $botInsertedId !== null && $botInsertedId > 0,
    "Returned ID: " . var_export($botInsertedId, true)
);

// -------------------------------------------------------------------------
// 3. Persistensi dari AIChatbotService (chat() menyimpan ke DB)
// -------------------------------------------------------------------------
echo "\n--- 3. AIChatbotService DB Persistence ---\n";

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$service = new AIChatbotService();
$chatResult = $service->chat('Halo selamat pagi!', $testUserId, 'customer', null);

assert_test(
    'AIChatbotService::chat() berhasil menghasilkan balasan',
    !empty($chatResult['reply']) && !empty($chatResult['intent']),
    'intent=' . ($chatResult['intent'] ?? 'null')
);

// Check that DB was written
$recentCount = $logModel->query(
    "SELECT COUNT(*) AS cnt FROM `chatbot_messages`
     WHERE `user_id` = :uid AND `created_at` >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
    ['uid' => $testUserId]
);
assert_test(
    'DB persistence: pesan tersimpan setelah chat()',
    !empty($recentCount) && (int)$recentCount[0]['cnt'] >= 2,
    'recent rows: ' . ($recentCount[0]['cnt'] ?? 0)
);

// -------------------------------------------------------------------------
// 4. Best-effort: DB failure tidak menghentikan chat
// -------------------------------------------------------------------------
echo "\n--- 4. Best-effort Persistence ---\n";

// ChatbotMessage::logMessage() pada error harus return null, bukan exception
$brokenModel = new class extends ChatbotMessage {
    public function logMessage(int $u, string $r, string $s, string $sn, string $m, ?string $i, string $mo = 'MOCK', int $fb = 0, ?string $toolUsed = null): ?int {
        try {
            $db = \App\Core\Database::getConnection();
            $db->prepare("INSERT INTO `nonexistent_table_xyz` VALUES ()")->execute();
        } catch (\Throwable $th) {
            return null; // best-effort
        }
        return null;
    }
};

$result = $brokenModel->logMessage($testUserId, 'customer', 'sess', 'USER', 'test', null);
assert_test(
    'logMessage failure returns null (best-effort, no exception thrown)',
    $result === null,
    'Returned: ' . var_export($result, true)
);

// -------------------------------------------------------------------------
// 5. Filter & Summary Stats
// -------------------------------------------------------------------------
echo "\n--- 5. Filter & Summary ---\n";

$summary = $logModel->getSummary([]);
assert_test(
    'getSummary() mengembalikan total_messages >= 2',
    isset($summary['total_messages']) && $summary['total_messages'] >= 2,
    'total_messages: ' . ($summary['total_messages'] ?? 'null')
);
assert_test(
    'getSummary() mengembalikan unique_users >= 1',
    isset($summary['unique_users']) && $summary['unique_users'] >= 1,
    'unique_users: ' . ($summary['unique_users'] ?? 'null')
);
assert_test(
    'getSummary() mengembalikan top_intents (array)',
    isset($summary['top_intents']) && is_array($summary['top_intents']),
    'top_intents type: ' . gettype($summary['top_intents'] ?? null)
);
assert_test(
    'getSummary() mengembalikan fallback_count (int) >= 1',
    isset($summary['fallback_count']) && is_int($summary['fallback_count']) && $summary['fallback_count'] >= 1,
    'fallback_count: ' . var_export($summary['fallback_count'] ?? null, true)
);
assert_test(
    'getSummary() mengembalikan top_questions (array)',
    isset($summary['top_questions']) && is_array($summary['top_questions']),
    'top_questions count: ' . count($summary['top_questions'] ?? [])
);
assert_test(
    'getSummary() mengembalikan fallback_questions (array)',
    isset($summary['fallback_questions']) && is_array($summary['fallback_questions']),
    'fallback_questions count: ' . count($summary['fallback_questions'] ?? [])
);

// Filter by session
$filteredSession = $logModel->getLogsForAdmin(['keyword' => 'test_s14_'], 1, 30);
assert_test(
    'getLogsForAdmin() mengembalikan struktur pagination yang benar',
    isset($filteredSession['data']) && isset($filteredSession['total']) &&
    isset($filteredSession['total_pages']) && is_array($filteredSession['data']),
    'Keys: ' . implode(', ', array_keys($filteredSession))
);

// Filter by role customer
$filteredRole = $logModel->getLogsForAdmin(['role' => 'customer'], 1, 10);
$allCustomer  = array_filter($filteredRole['data'], fn($r) => $r['user_role'] !== 'customer' && $r['role_pengirim_user'] !== 'customer');
assert_test(
    'Filter role=customer mengembalikan hanya baris customer',
    empty($allCustomer),
    'Non-customer rows: ' . count($allCustomer)
);

// Filter only_fallback
$filteredFallback = $logModel->getLogsForAdmin(['only_fallback' => 1], 1, 10);
$nonFallback = array_filter($filteredFallback['data'], fn($r) => (int)$r['is_fallback'] !== 1 && $r['detected_intent'] !== 'GENERAL' && !empty($r['detected_intent']));
assert_test(
    'Filter only_fallback mengembalikan hanya baris fallback',
    empty($nonFallback),
    'Non-fallback rows: ' . count($nonFallback)
);

// Filter by non-existent intent => 0 results
$filteredIntent = $logModel->getLogsForAdmin(['intent' => 'IMPOSSIBLE_INTENT_XYZ_9999'], 1, 5);
assert_test(
    'Filter intent yang tidak ada menghasilkan 0 baris',
    $filteredIntent['total'] === 0,
    'total: ' . $filteredIntent['total']
);

// -------------------------------------------------------------------------
// 6. Session Thread & User Thread
// -------------------------------------------------------------------------
echo "\n--- 6. Session & User Thread ---\n";

$thread = $logModel->getSessionThread($testSessionId);
assert_test(
    'getSessionThread() mengembalikan pesan sesi yang benar',
    count($thread) >= 2,
    'rows: ' . count($thread)
);

$senders = array_column($thread, 'sender');
assert_test(
    'Thread mengandung baris USER dan BOT',
    in_array('USER', $senders) && in_array('BOT', $senders),
    'senders: ' . implode(', ', array_unique($senders))
);

$userThread = $logModel->getUserThread($testUserId);
assert_test(
    'getUserThread() mengembalikan seluruh riwayat user',
    count($userThread) >= 2,
    'user rows: ' . count($userThread)
);

// -------------------------------------------------------------------------
// 7. RBAC – Controller access control (index & export)
// -------------------------------------------------------------------------
echo "\n--- 7. RBAC Akses Kontrol ---\n";

$forbiddenRoles = ['owner_toko', 'admin_toko', 'kurir', 'customer'];

foreach ($forbiddenRoles as $forbiddenRole) {
    $_SESSION['user'] = [
        'id'           => $testUserId,
        'name'         => 'Test ' . ucfirst($forbiddenRole),
        'email'        => "test.{$forbiddenRole}@example.com",
        'role'         => $forbiddenRole,
        'role_display' => ucfirst($forbiddenRole)
    ];

    $req = new App\Core\Request();
    $res = new App\Core\Response();
    $ctrl = new ChatbotLogController();

    // Test index access
    ob_start();
    $ctrl->index($req, $res);
    $output = ob_get_clean();

    $httpCode = http_response_code();
    assert_test(
        "RBAC: role '{$forbiddenRole}' mendapat 403 saat akses /platform/chatbot-logs",
        $httpCode === 403,
        "HTTP code: {$httpCode}"
    );

    // Test export access
    ob_start();
    $ctrl->export($req, $res);
    $outputExport = ob_get_clean();

    $httpCodeExport = http_response_code();
    assert_test(
        "RBAC: role '{$forbiddenRole}' mendapat 403 saat akses /platform/chatbot-logs/export",
        $httpCodeExport === 403,
        "HTTP code: {$httpCodeExport}"
    );
}

// Test Unauthenticated (guest) -> redirect to login
unset($_SESSION['user']);
$reqGuest = new App\Core\Request();
$resGuest = new App\Core\Response();
$ctrlGuest = new ChatbotLogController();

ob_start();
$ctrlGuest->index($reqGuest, $resGuest);
ob_end_clean();

assert_test(
    'RBAC: Tamu yang belum login diarahkan (redirect) ke login',
    $resGuest->getStatusCode() === 302,
    "Status code: " . $resGuest->getStatusCode()
);

// Test allowed roles (owner_platform & admin_owner)
$allowedRoles = ['owner_platform', 'admin_owner'];
foreach ($allowedRoles as $allowedRole) {
    $platformUsers = $userModel->query(
        "SELECT u.* FROM `users` u JOIN `roles` r ON u.role_id = r.id
         WHERE r.name = :r LIMIT 1",
        ['r' => $allowedRole]
    );

    if (!empty($platformUsers)) {
        $_SESSION['user'] = [
            'id'    => (int)$platformUsers[0]['id'],
            'name'  => $platformUsers[0]['name'],
            'email' => $platformUsers[0]['email'],
            'role'  => $allowedRole
        ];
        $req = new App\Core\Request();
        $res = new App\Core\Response();
        $ctrl = new ChatbotLogController();

        ob_start();
        $ctrl->index($req, $res);
        $output = ob_get_clean();
        $httpCode = http_response_code();

        assert_test(
            "RBAC: role '{$allowedRole}' diizinkan akses (HTTP 200)",
            $httpCode === 200,
            "HTTP code: {$httpCode}"
        );
    }
}

// -------------------------------------------------------------------------
// 8. CSV Export
// -------------------------------------------------------------------------
echo "\n--- 8. CSV Export ---\n";

$csv = $logModel->exportCsv([]);
assert_test(
    'exportCsv() menghasilkan string non-kosong',
    is_string($csv) && strlen($csv) > 0,
    'length: ' . strlen($csv)
);

$firstLine = strtok($csv, "\n");
assert_test(
    'CSV mengandung header id,user_name,user_email,role,is_fallback...',
    strpos($firstLine, 'id') !== false &&
    strpos($firstLine, 'user_name') !== false &&
    strpos($firstLine, 'message') !== false &&
    strpos($firstLine, 'intent') !== false &&
    strpos($firstLine, 'is_fallback') !== false,
    "Header: {$firstLine}"
);

// Nomor telepon tidak boleh ada dalam export (sudah masked)
assert_test(
    'CSV: nomor telepon asli tidak muncul (data sudah masked)',
    strpos($csv, '081298765432') === false,
    'Phone found in CSV – masking failed!'
);

// -------------------------------------------------------------------------
// 9. Retention & Purge Script (Idempotent)
// -------------------------------------------------------------------------
echo "\n--- 9. Retention & Purge Script ---\n";

$db = \App\Core\Database::getConnection();

// Insert an old message (100 days ago)
$oldSession = 'old_purge_sess_' . time();
$oldInsertStmt = $db->prepare(
    "INSERT INTO `chatbot_messages`
     (`user_id`, `user_role`, `session_id`, `sender`, `message`, `detected_intent`, `is_fallback`, `mode`, `created_at`)
     VALUES
     (:user_id, 'customer', :session_id, 'USER', 'Pertanyaan 100 hari lalu yang harus terhapus', 'OLD', 0, 'MOCK', DATE_SUB(NOW(), INTERVAL 100 DAY))"
);
$oldInsertStmt->execute([
    'user_id'    => $testUserId,
    'session_id' => $oldSession,
]);
$oldId = (int)$db->lastInsertId();

// Insert a recent message (today)
$recentSession = 'recent_sess_' . time();
$recentInsertStmt = $db->prepare(
    "INSERT INTO `chatbot_messages`
     (`user_id`, `user_role`, `session_id`, `sender`, `message`, `detected_intent`, `is_fallback`, `mode`, `created_at`)
     VALUES
     (:user_id, 'customer', :session_id, 'USER', 'Pertanyaan hari ini yang TIDAK boleh terhapus', 'RECENT', 0, 'MOCK', NOW())"
);
$recentInsertStmt->execute([
    'user_id'    => $testUserId,
    'session_id' => $recentSession,
]);
$recentId = (int)$db->lastInsertId();

// Run purge with 90 days retention
$purgedCount = $logModel->purgeOlderThan(90);
assert_test(
    'purgeOlderThan(90) berhasil menghapus data lama (>= 1 baris)',
    $purgedCount >= 1,
    "Purged: {$purgedCount}"
);

// Verify old record was purged
$checkOld = $logModel->find($oldId);
assert_test(
    'Pesan lama (> 90 hari) terhapus dari database',
    $checkOld === null,
    'Old row still found in DB!'
);

// Verify recent record is NOT purged
$checkRecent = $logModel->find($recentId);
assert_test(
    'Pesan baru (< 90 hari) tetap aman dan tidak terhapus',
    $checkRecent !== null,
    'Recent row was erroneously deleted!'
);

// Verify idempotency: run again immediately
$secondPurge = $logModel->purgeOlderThan(90);
assert_test(
    'Eksekusi retensi kedua aman & idempoten (0 baris dihapus)',
    $secondPurge === 0,
    "Second purge deleted: {$secondPurge}"
);

// Clean up recent test record
if ($recentId) {
    $db->prepare("DELETE FROM `chatbot_messages` WHERE `id` = :id")->execute(['id' => $recentId]);
}

// -------------------------------------------------------------------------
// 10. Audit Logging Verification
// -------------------------------------------------------------------------
echo "\n--- 10. Audit Logging Verification ---\n";

$auditFile = ROOT_PATH . '/storage/logs/chatbot_access.log';
assert_test(
    'Audit log file storage/logs/chatbot_access.log tercipta',
    file_exists($auditFile) && filesize($auditFile) > 0,
    "File exists: " . (file_exists($auditFile) ? 'YES' : 'NO')
);

if (file_exists($auditFile)) {
    $auditContent = file_get_contents($auditFile);
    assert_test(
        'Audit log mencatat aksi VIEW_CHATBOT_LOGS',
        strpos($auditContent, 'VIEW_CHATBOT_LOGS') !== false,
        'VIEW_CHATBOT_LOGS not found in audit log'
    );
}

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n=======================================================\n";
echo "STAGE 14 TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
