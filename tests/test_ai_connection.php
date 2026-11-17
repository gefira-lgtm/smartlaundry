<?php
/**
 * SmartLaundry AI - API Key & Gemini Connection Tester
 * Jalankan via CLI:
 *   C:\xampp\php\php.exe tests/test_ai_connection.php
 */

require_once __DIR__ . '/../config/env.php';
load_env(__DIR__ . '/../.env');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Core/Database.php';

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Services\AIChatbotService;

echo "=================================================================\n";
echo " SmartLaundry AI - Google Gemini API Connection Test\n";
echo "=================================================================\n\n";

$provider = $_ENV['AI_PROVIDER'] ?? 'mock';
$model    = $_ENV['AI_MODEL'] ?? 'gemini-3.5-flash';
$apiKey   = $_ENV['AI_API_KEY'] ?? '';

echo "[1] Konfigurasi di .env:\n";
echo "    - AI_PROVIDER : {$provider}\n";
echo "    - AI_MODEL    : {$model}\n";
echo "    - AI_API_KEY  : " . (empty($apiKey) ? '(KOSONG)' : substr($apiKey, 0, 10) . '...' . substr($apiKey, -5) . " (Panjang: " . strlen($apiKey) . " karakter)") . "\n\n";

if (empty($apiKey)) {
    echo "❌ ERROR: AI_API_KEY belum diisi di file .env!\n";
    exit(1);
}

if ($provider !== 'gemini') {
    echo "⚠️ PERINGATAN: AI_PROVIDER saat ini bernilai '{$provider}'.\n";
    echo "   Untuk mengaktifkan Google Gemini, ubah AI_PROVIDER=gemini di .env\n\n";
}

echo "[2] Menguji Request Langsung ke Google Gemini REST API...\n";
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
$payload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => 'Halo Gemini! Ini adalah tes koneksi dari sistem SmartLaundry AI. Berikan balasan singkat salam 1-2 kalimat.']
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 1024
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "    - HTTP Status Code : {$httpCode}\n";

if ($curlErr) {
    echo "    - CURL Error       : {$curlErr}\n";
}

if ($httpCode === 200) {
    $data = json_decode($res, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo "\n✅ KONEKSI KE GEMINI API BERHASIL!\n";
    echo "-----------------------------------------------------------------\n";
    echo "Respons Gemini:\n" . trim($text) . "\n";
    echo "-----------------------------------------------------------------\n\n";
} elseif ($httpCode === 429) {
    echo "\n⚠️ INFO RATE LIMIT (429): API Key valid dan terhubung, namun kuota per menit Free Tier tercapai.\n";
    echo "   Tunggu 30-60 detik sebelum melakukan request berikutnya.\n\n";
} else {
    echo "\n❌ KONEKSI GAGAL (HTTP {$httpCode}):\n";
    echo $res . "\n\n";
}

echo "[3] Menguji integrasi end-to-end via AIChatbotService...\n";
$service = new AIChatbotService();
$testQuery = "Halo, apakah saya bisa pesan laundry kiloan antar jemput?";
$chatResult = $service->chat($testQuery, 9, 'customer', null);

echo "    - Provider yang aktif : " . ($chatResult['provider'] ?? '-') . "\n";
echo "    - Intent terdeteksi    : " . ($chatResult['intent'] ?? '-') . "\n";
echo "    - Cuplikan balasan     :\n";
echo "      " . str_replace("\n", "\n      ", substr(trim($chatResult['reply'] ?? ''), 0, 200)) . "...\n\n";

echo "=================================================================\n";
echo " PENGUJIAN SELESAI\n";
echo "=================================================================\n";
