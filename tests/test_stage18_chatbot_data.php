<?php
/**
 * SmartLaundry - Stage 18 Test Suite: ChatbotDataService & AIChatbotService Data Access Integration
 *
 * Menguji integrasi kemampuan akses data database dan basis pengetahuan sistem untuk
 * chatbot AI role platform owner, admin owner, store owner, store admin, kurir, dan customer.
 *
 * Cakupan Pengujian:
 *  1. Whitelist read-only access (ChatbotDataService)
 *  2. Masking PII (telepon & email tersamarkan, kolom kredensial tidak pernah keluar)
 *  3. Batas 10 baris dan info sisa baris
 *  4. Pembatasan akses tool per role (platform vs store vs kurir vs customer)
 *  5. Isolasi scope toko (anti-IDOR & store_id dari session)
 *  6. Akses finansial: owner_toko boleh, admin_toko ditolak
 *  7. Audit logging ke tabel chatbot_data_access_logs
 *  8. Basis pengetahuan sistem (chatbot_knowledge)
 *  9. Routing intent di AIChatbotService (Mock mode & API delegation)
 * 10. Keamanan menyeluruh (tanpa kebocoran kredensial)
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
    $prefix  = 'App\\';
    $baseDir = APP_PATH . '/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relClass = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Core\Database;
use App\Services\ChatbotDataService;
use App\Services\AIChatbotService;
use App\Models\User;
use App\Models\Order;

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name}" . ($detail ? " -- {$detail}" : '') . "\n";
        $failed++;
    }
}

echo "=================================================================\n";
echo " SMARTLAUNDRY STAGE 18: CHATBOT DATA & KNOWLEDGE ACCESS TEST\n";
echo "=================================================================\n\n";

$dataService = new ChatbotDataService();
$aiService   = new AIChatbotService();
$pdo         = Database::getConnection();

// =============================================================================
// SEKSI 1: Unit & Security Tests ChatbotDataService
// =============================================================================
echo "--- SEKSI 1: ChatbotDataService Direct Method & Security Tests ---\n";

// 1.1 Masking PII
assert_test(
    "1.1.1 Masking nomor telepon panjang",
    ChatbotDataService::maskPhone('081234567890') === '0812*****890',
    "Hasil: " . ChatbotDataService::maskPhone('081234567890')
);
assert_test(
    "1.1.2 Masking email normal",
    ChatbotDataService::maskEmail('hendra.kurniawan@cleanwash.id') === 'h***************@cleanwash.id',
    "Hasil: " . ChatbotDataService::maskEmail('hendra.kurniawan@cleanwash.id')
);
assert_test(
    "1.1.3 Masking email pendek",
    ChatbotDataService::maskEmail('ani@gmail.com') === 'a**@gmail.com',
    "Hasil: " . ChatbotDataService::maskEmail('ani@gmail.com')
);

// 1.2 Platform Tool: searchUsers
$userSearch = $dataService->searchUsers(1, 'owner_platform', 'budi');
assert_test(
    "1.2.1 searchUsers mengembalikan data user",
    stripos($userSearch, 'Budi Santoso') !== false,
    "Output: " . substr($userSearch, 0, 150)
);
assert_test(
    "1.2.2 searchUsers menyamarkan nomor telepon",
    stripos($userSearch, '0817') !== false && stripos($userSearch, '****') !== false,
    "Output: " . substr($userSearch, 0, 150)
);
assert_test(
    "1.2.3 searchUsers TIDAK membocorkan password atau hash",
    stripos($userSearch, '$2y$') === false && stripos($userSearch, 'password123') === false,
    "Kredensial terdeteksi!"
);
assert_test(
    "1.2.4 searchUsers TIDAK membocorkan google_id",
    stripos($userSearch, 'google_uid') === false,
    "google_id terdeteksi!"
);

// 1.3 Platform Tool: getUserStats
$userStats = $dataService->getUserStats(1, 'owner_platform');
assert_test(
    "1.3.1 getUserStats menampilkan tabel ringkasan per role",
    stripos($userStats, 'Statistik User Platform') !== false && stripos($userStats, 'Total Semua User') !== false,
    "Output: " . substr($userStats, 0, 150)
);

// 1.4 Platform Tool: listStores
$allStores = $dataService->listStores(1, 'owner_platform', false);
assert_test(
    "1.4.1 listStores menampilkan CleanWash Express",
    stripos($allStores, 'CleanWash Express') !== false,
    "Output: " . substr($allStores, 0, 150)
);
$pendingStores = $dataService->listStores(1, 'owner_platform', true);
assert_test(
    "1.4.2 listStores pendingOnly berjalan aman",
    is_string($pendingStores) && strlen($pendingStores) > 0
);

// 1.5 Platform Tool: searchOrderByCode
$orderRow = $pdo->query("SELECT order_code FROM `orders` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$testCode = $orderRow['order_code'] ?? 'ORD-TEST';
$orderRes = $dataService->searchOrderByCode(1, 'owner_platform', $testCode);
assert_test(
    "1.5.1 searchOrderByCode menemukan order yang valid",
    stripos($orderRes, $testCode) !== false && stripos($orderRes, 'Detail Pesanan') !== false,
    "Output: " . substr($orderRes, 0, 150)
);
$invalidOrderRes = $dataService->searchOrderByCode(1, 'owner_platform', 'ORD-NONEXISTENT-999');
assert_test(
    "1.5.2 searchOrderByCode memberikan pesan ramah untuk kode tidak ada",
    stripos($invalidOrderRes, 'tidak ditemukan') !== false
);

// 1.6 Platform Tool: getOrderSummary & GMV
$orderSum = $dataService->getOrderSummary(1, 'owner_platform');
assert_test(
    "1.6.1 getOrderSummary menampilkan ringkasan status pesanan dan GMV",
    stripos($orderSum, 'Ringkasan Pesanan Platform') !== false && stripos($orderSum, 'GMV') !== false
);

// 1.7 Platform Tool: getCourierAvailability
$couriers = $dataService->getCourierAvailability(1, 'owner_platform');
assert_test(
    "1.7.1 getCourierAvailability menampilkan armada kurir",
    stripos($couriers, 'Status Kurir Platform') !== false && stripos($couriers, 'Budi') !== false
);

// 1.8 Platform Tool: getReviewSummary
$reviewSum = $dataService->getReviewSummary(1, 'owner_platform');
assert_test(
    "1.8.1 getReviewSummary menampilkan statistik ulasan",
    stripos($reviewSum, 'Ringkasan Ulasan Platform') !== false
);

// 1.9 Akses Platform Ditolak untuk Non-Platform
$deniedRole = false;
try {
    $dataService->searchUsers(9, 'customer', 'budi');
} catch (\RuntimeException $e) {
    $deniedRole = true;
}
assert_test(
    "1.9.1 Customer ditolak saat memanggil searchUsers",
    $deniedRole
);

$deniedStoreOwner = false;
try {
    $dataService->getUserStats(3, 'owner_toko');
} catch (\RuntimeException $e) {
    $deniedStoreOwner = true;
}
assert_test(
    "1.9.2 Owner Toko ditolak saat memanggil getUserStats platform",
    $deniedStoreOwner
);

// 1.10 Store Tools: owner_toko vs admin_toko
$storeOrdersOwner = $dataService->getStoreOrders(3, 'owner_toko', 1);
assert_test(
    "1.10.1 owner_toko melihat kolom Total / finansial pada getStoreOrders",
    stripos($storeOrdersOwner, 'Total') !== false || stripos($storeOrdersOwner, 'Tidak ada pesanan') !== false
);

$storeOrdersAdmin = $dataService->getStoreOrders(5, 'admin_toko', 1);
assert_test(
    "1.10.2 admin_toko TIDAK melihat kolom Total / finansial pada getStoreOrders",
    stripos($storeOrdersAdmin, 'Total') === false
);

$storeServices = $dataService->getStoreServices(3, 'owner_toko', 1);
assert_test(
    "1.10.3 getStoreServices menampilkan layanan CleanWash",
    stripos($storeServices, 'Katalog Layanan') !== false
);

$storeCouriers = $dataService->getStoreCouriers(3, 'owner_toko', 1);
assert_test(
    "1.10.4 getStoreCouriers menampilkan kurir CleanWash",
    stripos($storeCouriers, 'Armada Kurir Toko') !== false
);

$storeCustOwner = $dataService->getStoreCustomers(3, 'owner_toko', 1);
assert_test(
    "1.10.5 getStoreCustomers berhasil untuk owner_toko",
    stripos($storeCustOwner, 'Pelanggan Toko') !== false
);

$storeCustAdmin = $dataService->getStoreCustomers(5, 'admin_toko', 1);
assert_test(
    "1.10.6 getStoreCustomers DITOLAK untuk admin_toko",
    stripos($storeCustAdmin, 'Akses ditolak') !== false
);

$storeRevOwner = $dataService->getStoreRevenue(3, 'owner_toko', 1);
assert_test(
    "1.10.7 getStoreRevenue berhasil untuk owner_toko",
    stripos($storeRevOwner, 'Laporan Omset Toko') !== false
);

$storeRevAdmin = $dataService->getStoreRevenue(5, 'admin_toko', 1);
assert_test(
    "1.10.8 getStoreRevenue DITOLAK untuk admin_toko",
    stripos($storeRevAdmin, 'Akses ditolak') !== false
);

$storeRevSum = $dataService->getStoreReviews(3, 'owner_toko', 1);
assert_test(
    "1.10.9 getStoreReviews menampilkan ringkasan ulasan toko",
    stripos($storeRevSum, 'Ulasan Toko') !== false
);

$expiredOrders = $dataService->getExpiredOrders(3, 'owner_toko', 1);
assert_test(
    "1.10.10 getExpiredOrders berjalan aman",
    stripos($expiredOrders, 'Pesanan Kadaluarsa') !== false
);

// 1.11 Scope Isolation & Anti-IDOR
$idorOwnerBlocked = false;
try {
    // Hendra (User 3, owner toko 1) mencoba akses toko 2 (Dewi)
    $dataService->getStoreOrders(3, 'owner_toko', 2);
} catch (\RuntimeException $e) {
    $idorOwnerBlocked = true;
}
assert_test(
    "1.11.1 Anti-IDOR: owner_toko 1 dilarang mengakses data toko 2",
    $idorOwnerBlocked
);

$idorAdminBlocked = false;
try {
    // Fajar (User 5, admin toko 1) mencoba akses toko 2
    $dataService->getStoreOrders(5, 'admin_toko', 2);
} catch (\RuntimeException $e) {
    $idorAdminBlocked = true;
}
assert_test(
    "1.11.2 Anti-IDOR: admin_toko 1 dilarang mengakses data toko 2",
    $idorAdminBlocked
);

// 1.12 Basis Pengetahuan Sistem (chatbot_knowledge)
$know1 = $dataService->searchKnowledge(1, 'owner_platform', 'alur pemesanan');
assert_test(
    "1.12.1 searchKnowledge menemukan artikel alur pemesanan",
    stripos($know1, 'Alur Pemesanan') !== false || stripos($know1, 'End-to-End') !== false,
    "Output: " . substr($know1, 0, 120)
);

$know2 = $dataService->searchKnowledge(9, 'customer', 'aturan qris');
assert_test(
    "1.12.2 searchKnowledge aturan qris dapat diakses oleh customer",
    stripos($know2, 'QRIS') !== false && stripos($know2, '60 menit') !== false,
    "Output: " . substr($know2, 0, 120)
);

$know3 = $dataService->searchKnowledge(9, 'customer', 'xyzrandomnonexistenttopic123');
assert_test(
    "1.12.3 searchKnowledge kata kunci tidak ada memberikan saran kata kunci",
    stripos($know3, 'Tidak ada informasi') !== false && stripos($know3, 'Coba kata kunci lain') !== false
);

// 1.13 Audit Logging ke database
$logCount = (int)$pdo->query("SELECT COUNT(*) FROM `chatbot_data_access_logs`")->fetchColumn();
assert_test(
    "1.13.1 Audit logging mencatat riwayat pemanggilan tool ke database",
    $logCount > 0,
    "Jumlah log: {$logCount}"
);

$sampleLog = $pdo->query("SELECT * FROM `chatbot_data_access_logs` ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert_test(
    "1.13.2 Format audit log memiliki kolom lengkap (user_id, role, tool)",
    !empty($sampleLog['user_id']) && !empty($sampleLog['role']) && !empty($sampleLog['tool'])
);


// =============================================================================
// SEKSI 2: Integrasi AIChatbotService (End-to-End Chat & Routing per Role)
// =============================================================================
echo "\n--- SEKSI 2: AIChatbotService Chat Integration & Routing Tests ---\n";

// 2.1 Platform Owner (User 1)
$res = $aiService->chat("cari user Hendra", 1, 'owner_platform');
assert_test(
    "2.1.1 Platform: 'cari user Hendra' -> DATA_SEARCH_USERS",
    $res['intent'] === 'DATA_SEARCH_USERS' && stripos($res['reply'], 'Hendra') !== false,
    "Intent: {$res['intent']}, Reply: " . substr($res['reply'], 0, 100)
);

$res = $aiService->chat("statistik user platform", 1, 'owner_platform');
assert_test(
    "2.1.2 Platform: 'statistik user platform' -> DATA_USER_STATS",
    $res['intent'] === 'DATA_USER_STATS' && stripos($res['reply'], 'Statistik User') !== false
);

$res = $aiService->chat("daftar toko", 1, 'owner_platform');
assert_test(
    "2.1.3 Platform: 'daftar toko' -> DATA_LIST_STORES",
    $res['intent'] === 'DATA_LIST_STORES' && stripos($res['reply'], 'CleanWash') !== false
);

$res = $aiService->chat("ringkasan pesanan", 1, 'owner_platform');
assert_test(
    "2.1.4 Platform: 'ringkasan pesanan' -> DATA_ORDER_SUMMARY",
    $res['intent'] === 'DATA_ORDER_SUMMARY' && stripos($res['reply'], 'Ringkasan Pesanan') !== false
);

$res = $aiService->chat("ketersediaan kurir", 1, 'owner_platform');
assert_test(
    "2.1.5 Platform: 'ketersediaan kurir' -> DATA_COURIER_AVAILABILITY",
    $res['intent'] === 'DATA_COURIER_AVAILABILITY' && stripos($res['reply'], 'Status Kurir') !== false
);

$res = $aiService->chat("ringkasan ulasan", 1, 'owner_platform');
assert_test(
    "2.1.6 Platform: 'ringkasan ulasan' -> DATA_REVIEW_SUMMARY",
    $res['intent'] === 'DATA_REVIEW_SUMMARY' && stripos($res['reply'], 'Ringkasan Ulasan') !== false
);

// 2.2 Store Owner (User 3, CleanWash)
$res = $aiService->chat("pesanan toko", 3, 'owner_toko');
assert_test(
    "2.2.1 Store Owner: 'pesanan toko' -> DATA_STORE_ORDERS",
    $res['intent'] === 'DATA_STORE_ORDERS' && stripos($res['reply'], 'Pesanan Toko') !== false
);

$res = $aiService->chat("omset toko saya", 3, 'owner_toko');
assert_test(
    "2.2.2 Store Owner: 'omset toko saya' -> DATA_STORE_REVENUE",
    $res['intent'] === 'DATA_STORE_REVENUE' && stripos($res['reply'], 'Omset') !== false
);

$res = $aiService->chat("pelanggan toko", 3, 'owner_toko');
assert_test(
    "2.2.3 Store Owner: 'pelanggan toko' -> DATA_STORE_CUSTOMERS",
    $res['intent'] === 'DATA_STORE_CUSTOMERS' && stripos($res['reply'], 'Pelanggan Toko') !== false
);

$res = $aiService->chat("layanan toko", 3, 'owner_toko');
assert_test(
    "2.2.4 Store Owner: 'layanan toko' -> DATA_STORE_SERVICES",
    $res['intent'] === 'DATA_STORE_SERVICES' && stripos($res['reply'], 'Katalog Layanan') !== false
);

$res = $aiService->chat("kurir toko", 3, 'owner_toko');
assert_test(
    "2.2.5 Store Owner: 'kurir toko' -> DATA_STORE_COURIERS",
    $res['intent'] === 'DATA_STORE_COURIERS' && stripos($res['reply'], 'Armada Kurir') !== false
);

// 2.3 Store Admin (User 5, CleanWash)
$res = $aiService->chat("omset toko", 5, 'admin_toko');
assert_test(
    "2.3.1 Store Admin ditolak saat menanyakan omset",
    stripos($res['reply'], 'Akses ditolak') !== false,
    "Reply: {$res['reply']}"
);

$res = $aiService->chat("pelanggan toko", 5, 'admin_toko');
assert_test(
    "2.3.2 Store Admin ditolak saat menanyakan daftar pelanggan",
    stripos($res['reply'], 'Akses ditolak') !== false,
    "Reply: {$res['reply']}"
);

$res = $aiService->chat("pesanan toko", 5, 'admin_toko');
assert_test(
    "2.3.3 Store Admin melihat pesanan toko tanpa kolom finansial Total",
    $res['intent'] === 'DATA_STORE_ORDERS' && stripos($res['reply'], 'Total') === false
);

// 2.4 Pembatasan Role Customer & Kurir
$res = $aiService->chat("cari user budi", 9, 'customer');
assert_test(
    "2.4.1 Customer diblokir dengan pesan sopan saat mencari data user",
    $res['intent'] === 'DATA_ACCESS_DENIED_ROLE' && stripos($res['reply'], 'dibatasi khusus') !== false,
    "Reply: {$res['reply']}"
);

$res = $aiService->chat("omset toko", 9, 'customer');
assert_test(
    "2.4.2 Customer diblokir dengan pesan sopan saat meminta omset",
    $res['intent'] === 'DATA_ACCESS_DENIED_ROLE' && stripos($res['reply'], 'dibatasi khusus') !== false
);

$res = $aiService->chat("statistik user", 7, 'kurir');
assert_test(
    "2.4.3 Kurir diblokir dengan pesan sopan saat meminta statistik platform",
    $res['intent'] === 'DATA_ACCESS_DENIED_ROLE' && stripos($res['reply'], 'dibatasi khusus') !== false
);

// 2.5 Pencarian Kode Order ORD-xxx
$res = $aiService->chat("cek status {$testCode}", 1, 'owner_platform');
assert_test(
    "2.5.1 Platform mencari order by code {$testCode}",
    $res['intent'] === 'DATA_SEARCH_ORDER_CODE' && stripos($res['reply'], $testCode) !== false
);

// 2.6 Pengetahuan Sistem (Knowledge Search) Lintas Role
$res = $aiService->chat("bagaimana aturan qris dan kadaluarsa", 9, 'customer');
assert_test(
    "2.6.1 Customer dapat menanyakan aturan QRIS -> KNOWLEDGE_SEARCH",
    $res['intent'] === 'KNOWLEDGE_SEARCH' && (stripos($res['reply'], 'QRIS') !== false || stripos($res['reply'], 'SmartLaundry') !== false),
    "Intent: {$res['intent']}, Reply: " . substr($res['reply'], 0, 100)
);

$res = $aiService->chat("apa arti status SEDANG_DICUCI", 7, 'kurir');
assert_test(
    "2.6.2 Kurir dapat menanyakan arti status -> KNOWLEDGE_SEARCH",
    $res['intent'] === 'KNOWLEDGE_SEARCH'
);

// 2.7 Bot Capability per Role
$res = $aiService->chat("anda bisa apa", 1, 'owner_platform');
assert_test(
    "2.7.1 Platform Owner BOT_CAPABILITY mencantumkan alat data",
    stripos($res['reply'], 'Pencarian User') !== false && stripos($res['reply'], 'Statistik Pengguna') !== false
);

$res = $aiService->chat("anda bisa apa", 3, 'owner_toko');
assert_test(
    "2.7.2 Store Owner BOT_CAPABILITY mencantumkan omset & pelanggan toko",
    stripos($res['reply'], 'Laporan Omset') !== false && stripos($res['reply'], 'Daftar Pelanggan') !== false
);

$res = $aiService->chat("anda bisa apa", 5, 'admin_toko');
assert_test(
    "2.7.3 Store Admin BOT_CAPABILITY mencantumkan catatan pembatasan finansial",
    stripos($res['reply'], 'Akses omset dan daftar pelanggan dibatasi') !== false
);

// 2.8 Identitas Akun "siapa saya" tetap prioritas tertinggi
$res = $aiService->chat("siapa saya", 3, 'owner_toko');
assert_test(
    "2.8.1 'siapa saya' tetap memiliki prioritas tertinggi (USER_IDENTITY)",
    $res['intent'] === 'USER_IDENTITY' && stripos($res['reply'], 'Hendra Kurniawan') !== false
);

// 2.9 Proteksi Kredensial Menyeluruh
$testReplies = [
    $aiService->chat("cari user admin", 1, 'owner_platform')['reply'],
    $aiService->chat("siapa saya", 1, 'owner_platform')['reply'],
    $aiService->chat("pelanggan toko", 3, 'owner_toko')['reply'],
    $aiService->chat("pesanan toko", 3, 'owner_toko')['reply'],
];
$credentialLeaked = false;
foreach ($testReplies as $rep) {
    if (stripos($rep, '$2y$') !== false ||
        stripos($rep, 'password123') !== false ||
        stripos($rep, 'remember_token') !== false ||
        stripos($rep, 'google_uid') !== false) {
        $credentialLeaked = true;
        break;
    }
}
assert_test(
    "2.9.1 Tidak ada satupun respons chatbot yang membocorkan hash password, google_id, atau token",
    !$credentialLeaked
);

// =============================================================================
// RINGKASAN TEST
// =============================================================================
echo "\n=================================================================\n";
echo "STAGE 18 TEST RESULTS:\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Total:  " . ($passed + $failed) . "\n";
echo "=================================================================\n";

if ($failed > 0) {
    echo "❌ SOME TESTS FAILED!\n";
    exit(1);
} else {
    echo "✅ ALL STAGE 18 TESTS PASSED (100%)\n";
    exit(0);
}
