<?php
/**
 * Run All Regression Tests
 */

$testFiles = [
    'test_auth.php',
    'test_stage5_and_audit.php',
    'test_order_flow.php',
    'test_dashboards.php',
    'test_courier_customer.php',
    'test_stage11_refund.php',
    'test_stage12_courier_offers.php',
    'test_stage13_chatbot.php',
    'test_chatbot_courier.php',
    'test_stage14_chatbot_logs.php',
    'test_stage15_midtrans.php',
    'test_stage16_notifications.php',
    'test_stage17_user_identity.php',
    'test_stage18_chatbot_data.php',
    'test_settings.php',
    'test_stage19_chatbot_db.php',
    'test_stage21_chatbot_revenue.php',
    'test_stage22_whatsapp_fonnte.php',
    'test_ai_agent_configuration.php',
    'test_stage23_whatsapp_refactor.php',
    'test_stage25_admin_whatsapp_order_processes.php',
    'test_stage26_store_admin_chatbot.php'
];

echo "=================================================\n";
echo "       SMARTLAUNDRY AI - REGRESSION TESTS       \n";
echo "=================================================\n\n";

$totalPass = 0;
$totalFail = 0;

foreach ($testFiles as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        echo "[SKIP] {$file} not found.\n";
        continue;
    }

    echo "Running {$file}...\n";
    $phpExec = PHP_BINARY ? escapeshellarg(PHP_BINARY) : 'php';
    $output = '';
    $outputLines = [];
    $exitCode = 0;
    exec($phpExec . ' ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1', $outputLines, $exitCode);
    $output = implode("\n", $outputLines);
    
    // A test file fails if the process exits non-zero, or contains hard failure phrases
    $hasFatalError = stripos($output, 'Fatal error:') !== false || stripos($output, 'Uncaught') !== false;
    $hasSomeFailed = preg_match('/\d+ \/ \d+ PASSED.*SOME TESTS FAILED/si', $output);
    
    if ($exitCode !== 0 || $hasFatalError || $hasSomeFailed) {
        echo "[FAIL] {$file} encountered errors.\n";
        $totalFail++;
        // Print partial output for debugging
        echo "Output snippet: \n" . substr($output, -500) . "\n\n";
    } else {
        echo "[PASS] {$file} passed successfully.\n\n";
        $totalPass++;
    }
}

echo "=================================================\n";
echo "TEST SUMMARY:\n";
echo "Total Passed: {$totalPass}\n";
echo "Total Failed: {$totalFail}\n";
echo "=================================================\n";

if ($totalFail > 0) {
    exit(1);
}
exit(0);
