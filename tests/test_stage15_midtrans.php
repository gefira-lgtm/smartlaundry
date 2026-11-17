<?php
/**
 * SmartLaundry - Stage 15 Test Suite: Midtrans Payment Gateway Adapter
 */

require_once __DIR__ . '/../config/env.php';
define('TEST_MODE', true);
load_env(__DIR__ . '/../.env');
require_once __DIR__ . '/../config/app.php';

require_once ROOT_PATH . '/app/Core/Model.php';
require_once ROOT_PATH . '/app/Core/Database.php';

// Autoload
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\SimulatedPaymentGateway;
use App\Services\Payment\MidtransPaymentGateway;
use App\Models\Order;

$total = 0;
$passed = 0;

function runTest($name, $closure, &$passed, &$total) {
    $total++;
    echo "TEST {$total}: {$name}... ";
    try {
        $result = $closure();
        if ($result === true) {
            echo "[\033[32mPASSED\033[0m]\n";
            $passed++;
        } else {
            echo "[\033[31mFAILED\033[0m]: Returned false or unexpected value\n";
        }
    } catch (\Throwable $e) {
        echo "[\033[31mERROR\033[0m]: " . $e->getMessage() . "\n";
    }
}

echo "====================================================================\n";
echo "SMARTLAUNDRY STAGE 15: MIDTRANS ADAPTER TEST SUITE\n";
echo "====================================================================\n\n";

$serverKey = env('MIDTRANS_SERVER_KEY', '');

// 1. Test Factory Simulated Fallback
runTest("PaymentGatewayFactory returns SimulatedPaymentGateway when MIDTRANS_SERVER_KEY is empty", function() {
    // Force env override
    $_ENV['PAYMENT_GATEWAY'] = 'midtrans';
    $_ENV['MIDTRANS_SERVER_KEY'] = ''; // Empty key
    
    $gateway = PaymentGatewayFactory::create();
    return $gateway instanceof SimulatedPaymentGateway;
}, $passed, $total);

// 2. Test Factory Midtrans Instantiation
runTest("PaymentGatewayFactory returns MidtransPaymentGateway when configured properly", function() {
    $_ENV['PAYMENT_GATEWAY'] = 'midtrans';
    $_ENV['MIDTRANS_SERVER_KEY'] = 'SB-Mid-server-DUMMY123';
    
    $gateway = PaymentGatewayFactory::create();
    return $gateway instanceof MidtransPaymentGateway;
}, $passed, $total);

// 3. Test Webhook Signature Validation (Simulated logic)
runTest("MidtransPaymentGateway handles webhook validation correctly", function() {
    $gateway = new MidtransPaymentGateway('SB-Mid-server-DUMMY123', false);
    
    // Simulate valid signature
    $orderId = 'MD_QRIS_99_123456';
    $statusCode = '200';
    $grossAmount = '50000.00';
    $serverKey = 'SB-Mid-server-DUMMY123';
    $signature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
    
    $payload = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signature,
        'transaction_status' => 'settlement',
        'transaction_id' => 'abc-123'
    ];
    
    $result = $gateway->handleWebhook($payload);
    
    return $result['status'] === 'PAID' && $result['order_id'] === 99;
}, $passed, $total);

// 4. Test Live Midtrans API Call (Skipped if no key)
if (empty($serverKey) || $serverKey === 'SB-Mid-server-DUMMY123') {
    echo "\n[INFO] Midtrans API key belum dikonfigurasi di .env.\n";
    echo "[INFO] Test pemanggilan API langsung ke Sandbox dilewati (SKIPPED).\n";
} else {
    runTest("Live Midtrans API call creates transaction successfully", function() use ($serverKey) {
        $gateway = new MidtransPaymentGateway($serverKey, false);
        $result = $gateway->createTransaction([
            'id' => 9999, // dummy order id
            'total_price' => 10000.00
        ]);
        
        return !empty($result['qris_payload']) && !empty($result['transaction_id']);
    }, $passed, $total);
}

echo "\n====================================================================\n";
echo "STAGE 15 TEST RESULTS: {$passed} / {$total} PASSED\n";
echo "====================================================================\n";

if ($passed === $total) {
    echo "\033[32mALL TESTS PASSED SUCCESSFULLY! STAGE 15 READY.\033[0m\n";
    exit(0);
} else {
    echo "\033[31mSOME TESTS FAILED.\033[0m\n";
    exit(1);
}
