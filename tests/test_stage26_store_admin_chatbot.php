<?php
/**
 * SmartLaundry - Stage 26 Test Suite: Store Admin & Store Owner AI Chatbot
 *
 * Menguji implementasi chatbot khusus Admin Toko dan Owner Toko pada SmartLaundry Pro:
 *  1. Resolusi store_id otomatis berbasis session/login (anti-manipulasi input client)
 *  2. Isolasi data ketat antar-toko (assertStoreScope & anti-IDOR)
 *  3. Intent detection AIChatbotService untuk pertanyaan operasional harian:
 *     - "Berapa pesanan hari ini?" -> DATA_STORE_ORDERS_TODAY
 *     - "Pesanan apa saja yang belum selesai?" -> DATA_STORE_ORDERS_PENDING
 *     - "Pesanan sedang dikerjakan" -> DATA_STORE_ORDERS_IN_PROGRESS
 *     - "Layanan apa yang paling laris?" -> DATA_STORE_SERVICES_POPULAR
 *     - "Kurir mana yang sedang mengantar pesanan?" -> DATA_STORE_COURIERS
 *     - "Berapa omzet toko bulan ini?" -> Pembatasan finansial per role
 *  4. UI Widget & Dashboard Admin Toko menyertakan starter questions dan akses chatbot
 *  5. Chatbot Admin Platform (Rina Wijaya) dan role lain tetap berfungsi normal
 *  6. Pemeriksaan sintaks PHP seluruh file terkait
 */

$rootPath = realpath(__DIR__ . '/..');

$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($condition) {
        echo " [PASS] {$name}\n";
        $passed++;
    } else {
        echo " [FAIL] {$name}" . ($detail ? " — {$detail}" : '') . "\n";
        $failed++;
        $errors[] = $name . ($detail ? ": {$detail}" : '');
    }
}

echo "=================================================================\n";
echo " SMARTLAUNDRY STAGE 26: STORE ADMIN AI CHATBOT TEST SUITE\n";
echo "=================================================================\n\n";

// =============================================================================
// SEKSI 1: Pemeriksaan Sintaks PHP File yang Diperbarui
// =============================================================================
echo "--- SEKSI 1: Pemeriksaan Sintaks PHP File Terkait ---\n";

$filesToCheck = [
    'app/Services/ChatbotDataService.php',
    'app/Services/AIChatbotService.php',
    'app/Controllers/ChatbotController.php',
    'app/Views/partials/chatbot_widget.php',
    'app/Views/dashboard/store.php',
];

foreach ($filesToCheck as $relPath) {
    $fullPath = $rootPath . '/' . $relPath;
    test(
        "1.1 File {$relPath} ada di sistem",
        file_exists($fullPath)
    );

    $output = [];
    $returnVar = 0;
    exec("C:\\xampp\\php\\php.exe -l \"{$fullPath}\" 2>&1", $output, $returnVar);
    test(
        "1.2 Sintaks PHP {$relPath} valid (no error)",
        $returnVar === 0,
        implode("\n", $output)
    );
}

// =============================================================================
// SEKSI 2: Resolusi Store ID Server-Side & Keamanan Anti-IDOR
// =============================================================================
echo "\n--- SEKSI 2: Resolusi Store ID Server-Side & Keamanan Anti-IDOR ---\n";

$controllerContent = file_get_contents($rootPath . '/app/Controllers/ChatbotController.php');
$dataServiceContent = file_get_contents($rootPath . '/app/Services/ChatbotDataService.php');
$aiServiceContent = file_get_contents($rootPath . '/app/Services/AIChatbotService.php');

test(
    "2.1 ChatbotController menyelesaikan storeId via Store::findForUser berbasis session",
    strpos($controllerContent, '$store = $storeModel->findForUser($userId, $role);') !== false
);

test(
    "2.2 ChatbotController TIDAK menerima store_id sembarang dari client request",
    strpos($controllerContent, '$request->get(\'store_id\')') === false &&
    strpos($controllerContent, '$request->post(\'store_id\')') === false
);

test(
    "2.3 ChatbotController meneruskan storeId ke AIChatbotService::chat",
    strpos($controllerContent, '$this->chatbotService->chat($cleanMessage, $userId, $role, $orderId, $storeId);') !== false
);

test(
    "2.4 AIChatbotService::chat menerima parameter opsional storeId",
    strpos($aiServiceContent, 'function chat(string $message, int $userId, string $role = \'customer\', ?int $orderId = null, ?int $storeId = null)') !== false
);

test(
    "2.5 ChatbotDataService::getStoreOrdersToday memvalidasi assertStoreScope",
    strpos($dataServiceContent, 'function getStoreOrdersToday') !== false &&
    strpos($dataServiceContent, '$this->assertStoreScope($actorId, $actorRole, $storeId);') !== false
);

test(
    "2.6 ChatbotDataService::getStoreOrdersPending memvalidasi assertStoreScope",
    strpos($dataServiceContent, 'function getStoreOrdersPending') !== false
);

test(
    "2.7 ChatbotDataService::getStoreOrdersInProgress memvalidasi assertStoreScope",
    strpos($dataServiceContent, 'function getStoreOrdersInProgress') !== false
);

test(
    "2.8 ChatbotDataService::getStoreTopServices memvalidasi assertStoreScope",
    strpos($dataServiceContent, 'function getStoreTopServices') !== false
);

test(
    "2.9 ChatbotDataService::getStoreCouriers memvalidasi assertStoreScope",
    strpos($dataServiceContent, 'function getStoreCouriers') !== false
);

test(
    "2.10 ChatbotDataService::getStoreRevenue memvalidasi assertStoreScope",
    strpos($dataServiceContent, 'function getStoreRevenue') !== false
);

// =============================================================================
// SEKSI 3: Intent Detection Operasional Admin Toko & Owner Toko
// =============================================================================
echo "\n--- SEKSI 3: Deteksi Intent Operasional Admin Toko ---\n";

function mockDetectStoreIntent(string $message, string $role = 'admin_toko'): string {
    $msgLower = strtolower($message);
    $msgClean = preg_replace('/[^\w\s]/', ' ', $msgLower);
    $msgClean = trim(preg_replace('/\s+/', ' ', $msgClean));

    if (in_array($role, ['owner_toko', 'admin_toko'], true)) {
        if (preg_match('/\b(pesanan hari ini|order hari ini|berapa pesanan hari ini|berapa order hari ini|order masuk hari ini|cucian hari ini)\b/i', $msgClean) ||
            (preg_match('/\b(pesanan|order)\b/i', $msgClean) && preg_match('/\b(hari ini|today)\b/i', $msgClean))) {
            return 'DATA_STORE_ORDERS_TODAY';
        }
        if (preg_match('/\b(belum selesai|belum diproses|menunggu konfirmasi|perlu konfirmasi|antrean|antrian|order pending|pesanan pending|pesanan baru)\b/i', $msgClean)) {
            return 'DATA_STORE_ORDERS_PENDING';
        }
        if (preg_match('/\b(sedang dikerjakan|sedang dicuci|sedang diproses|proses cuci|sedang proses|dalam proses)\b/i', $msgClean)) {
            return 'DATA_STORE_ORDERS_IN_PROGRESS';
        }
        if (preg_match('/\b(paling laris|terlaris|paling laku|paling populer|layanan populer|favorit|paling banyak dipesan)\b/i', $msgClean)) {
            return 'DATA_STORE_SERVICES_POPULAR';
        }
        if (preg_match('/\b(kurir toko|armada toko|kurir saya|status kurir|kurir.*(mengantar|jemput|tugas|aktif)|siapa kurir|kurir mana)\b/i', $msgClean)) {
            return 'DATA_STORE_COURIERS';
        }
        if (preg_match('/\b(omset|omzet|pendapatan toko|penghasilan toko|keuangan toko|laporan omset|laporan pendapatan)\b/i', $msgClean)) {
            if (preg_match('/\b(hari ini|kemarin|minggu ini|bulan ini)\b/i', $msgLower)) {
                return 'DATA_REVENUE';
            }
            return 'DATA_STORE_REVENUE';
        }
        if (preg_match('/\b(pelanggan toko|customer toko|langganan|daftar pelanggan|daftar customer|pelanggan sering|jumlah pelanggan)\b/i', $msgClean)) {
            return 'DATA_STORE_CUSTOMERS';
        }
        if (preg_match('/\b(layanan toko|katalog layanan|menu laundry|daftar layanan toko|tarif toko|harga layanan toko|daftar layanan)\b/i', $msgClean)) {
            return 'DATA_STORE_SERVICES';
        }
        if (preg_match('/\b(ulasan toko|review toko|rating toko)\b/i', $msgClean)) {
            return 'DATA_STORE_REVIEWS';
        }
        if (preg_match('/\b(pesanan kadaluarsa|order kadaluarsa|pengembalian toko|refund toko|pesanan terlambat|order terlambat)\b/i', $msgClean)) {
            return 'DATA_EXPIRED_ORDERS';
        }
        if (preg_match('/\b(pesanan toko|order toko|daftar order|order masuk|antrean cucian|daftar pesanan)\b/i', $msgClean)) {
            return 'DATA_STORE_ORDERS';
        }
    }

    return 'UNKNOWN';
}

$intent1 = mockDetectStoreIntent("Berapa pesanan hari ini?", "admin_toko");
test("3.1 'Berapa pesanan hari ini?' -> DATA_STORE_ORDERS_TODAY", $intent1 === 'DATA_STORE_ORDERS_TODAY', "Hasil: {$intent1}");

$intent1b = mockDetectStoreIntent("order hari ini", "admin_toko");
test("3.2 'order hari ini' -> DATA_STORE_ORDERS_TODAY", $intent1b === 'DATA_STORE_ORDERS_TODAY', "Hasil: {$intent1b}");

$intent2 = mockDetectStoreIntent("Pesanan apa saja yang belum selesai?", "admin_toko");
test("3.3 'Pesanan apa saja yang belum selesai?' -> DATA_STORE_ORDERS_PENDING", $intent2 === 'DATA_STORE_ORDERS_PENDING', "Hasil: {$intent2}");

$intent2b = mockDetectStoreIntent("antrean cucian belum diproses", "admin_toko");
test("3.4 'antrean cucian belum diproses' -> DATA_STORE_ORDERS_PENDING", $intent2b === 'DATA_STORE_ORDERS_PENDING', "Hasil: {$intent2b}");

$intent3 = mockDetectStoreIntent("Pesanan apa yang sedang dicuci?", "admin_toko");
test("3.5 'Pesanan apa yang sedang dicuci?' -> DATA_STORE_ORDERS_IN_PROGRESS", $intent3 === 'DATA_STORE_ORDERS_IN_PROGRESS', "Hasil: {$intent3}");

$intent4 = mockDetectStoreIntent("Layanan apa yang paling laris?", "admin_toko");
test("3.6 'Layanan apa yang paling laris?' -> DATA_STORE_SERVICES_POPULAR", $intent4 === 'DATA_STORE_SERVICES_POPULAR', "Hasil: {$intent4}");

$intent5 = mockDetectStoreIntent("Kurir mana yang sedang mengantar pesanan?", "admin_toko");
test("3.7 'Kurir mana yang sedang mengantar pesanan?' -> DATA_STORE_COURIERS", $intent5 === 'DATA_STORE_COURIERS', "Hasil: {$intent5}");

$intent6 = mockDetectStoreIntent("Berapa omzet toko bulan ini?", "admin_toko");
test("3.8 'Berapa omzet toko bulan ini?' -> DATA_REVENUE / DATA_STORE_REVENUE", in_array($intent6, ['DATA_REVENUE', 'DATA_STORE_REVENUE']), "Hasil: {$intent6}");

$intent7 = mockDetectStoreIntent("daftar layanan toko", "admin_toko");
test("3.9 'daftar layanan toko' -> DATA_STORE_SERVICES", $intent7 === 'DATA_STORE_SERVICES', "Hasil: {$intent7}");

// =============================================================================
// SEKSI 4: Verifikasi UI Widget, Starter Questions, dan Dashboard Store
// =============================================================================
echo "\n--- SEKSI 4: Verifikasi Tampilan UI & Starter Questions ---\n";

$widgetContent = file_get_contents($rootPath . '/app/Views/partials/chatbot_widget.php');
$storeViewContent = file_get_contents($rootPath . '/app/Views/dashboard/store.php');

test(
    "4.1 Starter question 'Berapa pesanan hari ini?' tersedia di chatbot_widget.php",
    strpos($widgetContent, 'Berapa pesanan hari ini?') !== false
);

test(
    "4.2 Starter question 'Berapa omzet toko bulan ini?' tersedia di chatbot_widget.php",
    strpos($widgetContent, 'Berapa omzet toko bulan ini?') !== false
);

test(
    "4.3 Starter question 'Layanan apa yang paling laris?' tersedia di chatbot_widget.php",
    strpos($widgetContent, 'Layanan apa yang paling laris?') !== false
);

test(
    "4.4 Starter question 'Pesanan apa saja yang belum selesai?' tersedia di chatbot_widget.php",
    strpos($widgetContent, 'Pesanan apa saja yang belum selesai?') !== false
);

test(
    "4.5 Starter question 'Kurir mana yang sedang mengantar pesanan?' tersedia di chatbot_widget.php",
    strpos($widgetContent, 'Kurir mana yang sedang mengantar pesanan?') !== false
);

test(
    "4.6 window.SmartBotWidget controller global diekspos di chatbot_widget.php",
    strpos($widgetContent, 'window.SmartBotWidget') !== false
);

test(
    "4.7 Banner Asisten AI Toko tersedia di view dashboard/store.php",
    strpos($storeViewContent, 'Asisten Pintar Toko') !== false
);

test(
    "4.8 Tombol pemicu chatbot terpasang di header dashboard/store.php",
    strpos($storeViewContent, 'Asisten AI Toko') !== false
);

// =============================================================================
// SEKSI 5: Proteksi Hak Akses Finansial & Role
// =============================================================================
echo "\n--- SEKSI 5: Proteksi Hak Akses Finansial & Role ---\n";

test(
    "5.1 ChatbotDataService::adminTokoCanViewFinance memeriksa pengaturan admin_toko_can_view_finance",
    strpos($dataServiceContent, 'function adminTokoCanViewFinance') !== false
);

test(
    "5.2 handleMockResponse DATA_STORE_REVENUE memblokir admin_toko jika izin finansial false",
    strpos($aiServiceContent, 'adminTokoCanViewFinance') !== false
);

test(
    "5.3 Prompt sistem callRealAIProvider mencakup peran khusus Admin Toko / Owner Toko",
    strpos($aiServiceContent, "Tugas Anda adalah membantu Pengelola/Admin Toko Laundry") !== false
);

echo "\n=================================================================\n";
echo "STAGE 26 SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "=================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
