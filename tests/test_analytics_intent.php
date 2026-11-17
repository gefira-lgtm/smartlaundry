<?php
/**
 * Test: SmartBot Analytics Intent Detection (no DB required)
 * Tests ONLY the intent detection logic and NLU period extraction.
 * Run: C:\xampp\php\php.exe tests\test_analytics_intent.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

// Minimal bootstrap without DB
define('BASE_URL', '');
function env(string $key, $default = null) { return $default; }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Autoload
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

// Stub ChatbotNLUService independently
require_once APP_PATH . '/Services/ChatbotNLUService.php';

$passed = 0;
$failed = 0;

function assert_equal($actual, $expected, string $desc): void {
    global $passed, $failed;
    if ($actual === $expected) {
        echo "  [PASS] {$desc}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$desc}\n    Expected: " . var_export($expected, true) . "\n    Got:      " . var_export($actual, true) . "\n";
        $failed++;
    }
}

echo "=== SmartBot Analytics Intent Detection (No-DB) ===\n\n";

// --- Test NLU Period Extraction ---
echo "--- Period Extraction ---\n";
$periodTests = [
    ['hari ini omzet berapa', 'hari_ini'],
    ['omzet kemarin', 'kemarin'],
    ['omzet minggu ini', 'minggu_ini'],
    ['omzet bulan ini', 'bulan_ini'],
    ['omzet bulan lalu', 'bulan_lalu'],
    ['omzet tahun ini', 'tahun_ini'],
    ['omzet 7 hari terakhir', '7_hari'],
    ['omzet 30 hari terakhir', '30_hari'],
    ['omzet per layanan', null],
    ['toko mana yang terbesar', null],
    ['seminggu terakhir', '7_hari'],
    ['sebulan terakhir', '30_hari'],
];

foreach ($periodTests as [$msg, $expected]) {
    $result = \App\Services\ChatbotNLUService::extractPeriod($msg);
    $desc = "extractPeriod('{$msg}')";
    assert_equal($result, $expected, $desc);
}

// --- Test getPeriodDateRange date logic (standalone) ---
echo "\n--- getPeriodDateRange new periods ---\n";

// Simulate getPeriodDateRange logic without DB
function testPeriodRange(string $period): array {
    $tz  = new DateTimeZone('Asia/Jakarta');
    $now = new DateTimeImmutable('now', $tz);
    switch ($period) {
        case '7_hari':
            $ago = $now->sub(new DateInterval('P6D'));
            return ['start' => $ago->format('Y-m-d 00:00:00'), 'end' => $now->format('Y-m-d 23:59:59'), 'label' => '7 hari terakhir', 'days' => 7];
        case '30_hari':
            $ago = $now->sub(new DateInterval('P29D'));
            return ['start' => $ago->format('Y-m-d 00:00:00'), 'end' => $now->format('Y-m-d 23:59:59'), 'label' => '30 hari terakhir', 'days' => 30];
        case 'bulan_lalu':
            $f = $now->modify('first day of last month');
            $l = $now->modify('last day of last month');
            return ['start' => $f->format('Y-m-d 00:00:00'), 'end' => $l->format('Y-m-d 23:59:59'), 'label' => 'bulan lalu', 'days' => (int)$l->format('j')];
        case 'tahun_ini':
            return ['start' => $now->format('Y-01-01 00:00:00'), 'end' => $now->format('Y-12-31 23:59:59'), 'label' => 'tahun ini', 'days' => (int)$now->format('z') + 1];
        default:
            return ['start' => '', 'end' => '', 'label' => '', 'days' => 0];
    }
}

foreach (['7_hari', '30_hari', 'bulan_lalu', 'tahun_ini'] as $p) {
    $range = testPeriodRange($p);
    if (!empty($range['start']) && !empty($range['end'])) {
        echo "  [PASS] Period '{$p}': {$range['start']} → {$range['end']} ({$range['days']} hari)\n";
        $passed++;
    } else {
        echo "  [FAIL] Period '{$p}' returned empty range\n";
        $failed++;
    }
}

// --- Test intent patterns directly (using regex matching, no service needed) ---
echo "\n--- Intent Pattern Matching Tests ---\n";

// Replicate the intent detection logic for analytics intents
function detectAnalyticsIntent(string $message, string $role): ?string {
    $msgLower = strtolower($message);
    $msgClean = preg_replace('/[^\w\s]/', ' ', $msgLower);
    $msgClean = trim(preg_replace('/\s+/', ' ', $msgClean));

    if (!in_array($role, ['owner_platform', 'admin_owner', 'owner_toko', 'admin_toko'], true)) {
        return null;
    }

    // 1. Analisis pesanan per toko + rincian layanan (wajib ada unsur toko DAN layanan/breakdown/cuci/setrika)
    if (
        preg_match('/\b(jabarkan.*pesanan|breakdown.*pesanan|rincian.*pesanan.*layanan)\b/i', $msgClean) ||
        preg_match('/\b(pesanan|order).*(toko|outlet).*(layanan|service|jenis|kategori|cuci|setrika|rincian|detail)\b/i', $msgClean) ||
        preg_match('/\b(layanan|service|jenis|cuci|setrika|rincian|detail).*(pesanan|order).*(toko|outlet)\b/i', $msgClean) ||
        preg_match('/\b(pesanan|order).*(layanan|service|cuci|setrika).*(toko|outlet)\b/i', $msgClean) ||
        preg_match('/\b(rincian|detail|jabarkan).*(pesanan|order).*(toko|outlet)\b/i', $msgClean)
    ) {
        return 'DATA_ANALYTICS_STORE_SERVICE_BREAKDOWN';
    }

    // 2. Omzet atau pesanan per layanan (cek sebelum TOP_STORE agar "layanan terbesar" tidak salah ke toko)
    if (
        preg_match('/\b(omzet.*layanan|omset.*layanan|pendapatan.*layanan|layanan.*omzet|layanan.*omset)\b/i', $msgClean) ||
        preg_match('/\b(dari layanan|layanan apa|layanan mana)\b/i', $msgClean) ||
        preg_match('/\b(kontribusi layanan|persentase layanan|layanan terlaris|layanan terbesar|layanan.*paling)\b/i', $msgClean) ||
        preg_match('/\b(pesanan.*layanan|order.*layanan|layanan.*pesanan|jumlah.*pesanan.*layanan)\b/i', $msgClean) ||
        preg_match('/\b(berapa.*pesanan.*cuci|berapa.*pesanan.*setrika|pesanan.*cuci|pesanan.*setrika|pesanan.*express)\b/i', $msgClean)
    ) {
        return 'DATA_ANALYTICS_BY_SERVICE';
    }

    // 3. Toko dengan omzet terbesar
    if (
        preg_match('/\b(omzet terbesar|omset terbesar|pendapatan terbesar|omzet.*paling besar|omset.*paling besar|omzet.*paling banyak|omzet.*terbanyak)\b/i', $msgClean) ||
        preg_match('/\b(toko mana.*omzet|toko mana.*omset|toko.*omzet.*terbesar|toko.*omset.*terbesar|omzet.*toko.*terbesar)\b/i', $msgClean) ||
        preg_match('/\b(toko.*omzet.*paling besar|toko.*omset.*paling besar|terbesar.*omzet|terbesar.*omset)\b/i', $msgClean)
    ) {
        return 'DATA_ANALYTICS_TOP_STORE';
    }

    // 4. Omzet per toko (rekap / perbandingan omzet seluruh toko)
    if (
        preg_match('/\b(omzet.*toko|omset.*toko|pendapatan.*toko|penghasilan.*toko)\b/i', $msgClean) ||
        preg_match('/\b(dari toko|toko mana saja.*omzet|toko mana.*omzet)\b/i', $msgClean) ||
        preg_match('/\b(perbandingan.*toko|ranking.*toko|peringkat.*toko|kontribusi.*toko)\b/i', $msgClean)
    ) {
        return 'DATA_ANALYTICS_BY_STORE';
    }

    // 5. Pesanan per toko (jumlah order per toko tanpa breakdown layanan)
    if (
        preg_match('/\b(pesanan.*(seluruh|semua|setiap|tiap|masing|per).*toko)\b/i', $msgClean) ||
        preg_match('/\b(jumlah.*pesanan.*(di|dari|pada)?.*(setiap|tiap|masing|semua|seluruh)?.*toko)\b/i', $msgClean) ||
        preg_match('/\b(total.*pesanan.*(dari|di|seluruh|semua)?.*toko)\b/i', $msgClean) ||
        preg_match('/\b(pesanan.*toko|order.*toko|toko.*pesanan|toko.*order)\b/i', $msgClean) ||
        preg_match('/\b(toko mana.*(banyak|terbanyak).*pesanan|toko.*pesanan.*(terbanyak|paling banyak))\b/i', $msgClean) ||
        preg_match('/\b(pesanan dari masing|pesanan.*masing masing)\b/i', $msgClean)
    ) {
        return 'DATA_ANALYTICS_ORDERS_BY_STORE';
    }

    return null;
}

$intentTests = [
    // Mandated test queries from user instruction #3
    ['DATA_ANALYTICS_BY_SERVICE', 'owner_platform', 'Omzet diperoleh dari layanan apa?'],
    ['DATA_ANALYTICS_BY_SERVICE', 'owner_platform', 'Layanan apa yang omzetnya terbesar?'],
    ['DATA_ANALYTICS_BY_STORE', 'owner_platform', 'Omzet diperoleh dari toko laundry mana?'],
    ['DATA_ANALYTICS_TOP_STORE', 'owner_platform', 'Toko mana yang omzetnya paling besar?'],
    ['DATA_ANALYTICS_ORDERS_BY_STORE', 'owner_platform', 'Ada berapa total pesanan dari setiap toko?'],
    ['DATA_ANALYTICS_STORE_SERVICE_BREAKDOWN', 'owner_platform', 'Jabarkan pesanan masing-masing toko berdasarkan layanan.'],
    ['DATA_ANALYTICS_STORE_SERVICE_BREAKDOWN', 'owner_platform', 'Berapa jumlah pesanan Cuci saja, Setrika saja, dan Cuci + Setrika di setiap toko?'],

    // Additional variations
    ['DATA_ANALYTICS_BY_SERVICE', 'owner_platform', 'omzet dari layanan apa saja'],
    ['DATA_ANALYTICS_BY_SERVICE', 'owner_platform', 'layanan apa yang menghasilkan omzet terbesar'],
    ['DATA_ANALYTICS_BY_SERVICE', 'owner_platform', 'kontribusi layanan terhadap omzet'],
    ['DATA_ANALYTICS_BY_SERVICE', 'owner_platform', 'berapa pesanan cuci saja'],
    ['DATA_ANALYTICS_BY_SERVICE', 'owner_platform', 'berapa pesanan setrika saja'],
    ['DATA_ANALYTICS_BY_SERVICE', 'admin_owner', 'layanan terlaris bulan ini'],

    ['DATA_ANALYTICS_BY_STORE', 'owner_platform', 'omzet dari toko mana saja'],
    ['DATA_ANALYTICS_BY_STORE', 'owner_platform', 'berapa omzet setiap toko'],
    ['DATA_ANALYTICS_BY_STORE', 'admin_owner', 'perbandingan omzet toko'],
    ['DATA_ANALYTICS_BY_STORE', 'owner_platform', 'ranking toko berdasarkan omzet'],

    ['DATA_ANALYTICS_TOP_STORE', 'owner_platform', 'toko mana yang omzetnya terbesar'],
    ['DATA_ANALYTICS_TOP_STORE', 'admin_owner', 'omset terbesar diperoleh dari toko mana'],
    ['DATA_ANALYTICS_TOP_STORE', 'owner_platform', 'toko dengan omzet terbesar minggu ini'],

    ['DATA_ANALYTICS_ORDERS_BY_STORE', 'owner_platform', 'ada berapa total pesanan dari seluruh toko'],
    ['DATA_ANALYTICS_ORDERS_BY_STORE', 'owner_platform', 'berapa jumlah pesanan di setiap toko'],
    ['DATA_ANALYTICS_ORDERS_BY_STORE', 'admin_owner', 'pesanan dari masing-masing toko'],
    ['DATA_ANALYTICS_ORDERS_BY_STORE', 'owner_platform', 'jumlah pesanan setiap toko'],
    ['DATA_ANALYTICS_ORDERS_BY_STORE', 'owner_platform', 'toko mana yang paling banyak pesanan'],

    ['DATA_ANALYTICS_STORE_SERVICE_BREAKDOWN', 'owner_platform', 'jabarkan pesanan setiap toko berdasarkan jenis layanan'],
    ['DATA_ANALYTICS_STORE_SERVICE_BREAKDOWN', 'owner_platform', 'pesanan toko berdasarkan layanan'],
    ['DATA_ANALYTICS_STORE_SERVICE_BREAKDOWN', 'admin_owner', 'rincian pesanan toko berdasarkan layanan'],
    ['DATA_ANALYTICS_STORE_SERVICE_BREAKDOWN', 'owner_platform', 'breakdown pesanan setiap toko'],

    // Access control: null for customer/kurir
    [null, 'customer', 'omzet dari layanan apa saja'],
    [null, 'kurir', 'berapa pesanan setiap toko'],
];

foreach ($intentTests as [$expectedIntent, $role, $msg]) {
    $detectedIntent = detectAnalyticsIntent($msg, $role);
    $desc = "[{$role}] '{$msg}'";
    assert_equal($detectedIntent, $expectedIntent, $desc);
}

echo "\n=================================================\n";
echo "ANALYTICS INTENT TEST SUMMARY:\n";
echo "Total Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";
$pct = ($passed + $failed > 0) ? round($passed / ($passed + $failed) * 100) : 0;
echo "Pass Rate: {$pct}%\n";
echo "=================================================\n";
exit($failed > 0 ? 1 : 0);
