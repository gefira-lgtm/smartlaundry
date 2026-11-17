<?php
/**
 * SmartLaundry AI Marketplace - Database Migration & Seeder CLI Runner
 */

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';

// PSR-4 Autoloader for CLI runner
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

use App\Core\Database;

echo "=================================================================\n";
echo " SmartLaundry AI - Database Migration & Seeder Tool\n";
echo "=================================================================\n\n";

try {
    $dbConfig = require ROOT_PATH . '/config/database.php';
    echo "Connecting to MySQL server ({$dbConfig['host']}:{$dbConfig['port']})...\n";
    $pdo = Database::getConnection();
    echo "Connected successfully to database: `{$dbConfig['database']}`\n\n";

    // 1. Run Migrations
    $migrationFiles = glob(ROOT_PATH . '/database/migrations/*.sql');
    sort($migrationFiles);
    if (empty($migrationFiles)) {
        throw new Exception("No migration files found in database/migrations/");
    }

    foreach ($migrationFiles as $migrationFile) {
        $filename = basename($migrationFile);
        echo "Running Migration: {$filename}...\n";
        $migrationSql = trim(file_get_contents($migrationFile));
        if ($migrationSql !== '') {
            $pdo->exec($migrationSql);
        }
    }
    echo "All migrations completed successfully!\n\n";

    // 2. Run Seeder
    $seederFile = ROOT_PATH . '/database/seeders/seed_dummy_data.sql';
    if (file_exists($seederFile)) {
        echo "Running Seeder: seed_dummy_data.sql...\n";
        $seederSql = file_get_contents($seederFile);
        $pdo->exec($seederSql);
        echo "Seeding completed successfully! (Roles, Users, Stores, Services, Orders, Payments, GPS logs populated)\n\n";
    }

    // 2b. Seed Chatbot Knowledge Base
    if (class_exists('App\Models\ChatbotKnowledge')) {
        $knowledgeModel = new \App\Models\ChatbotKnowledge();
        $seededCount = $knowledgeModel->seedKnowledge();
        if ($seededCount > 0) {
            echo "Chatbot Knowledge base seeded with {$seededCount} system articles.\n\n";
        }
    }

    // 2c. Seed Default System Settings
    require_once ROOT_PATH . '/database/seeders/SettingSeeder.php';
    $settingCount = \Database\Seeders\SettingSeeder::run($pdo);
    if ($settingCount > 0) {
        echo "Default system settings seeded with {$settingCount} items.\n\n";
    }

    // 3. Display summary
    $tables = [
        'roles', 'users', 'addresses', 'stores', 'store_admins',
        'laundry_services', 'couriers', 'orders', 'order_items',
        'laundry_details', 'payments', 'courier_locations',
        'notifications', 'order_status_histories', 'courier_task_offers',
        'whatsapp_logs', 'reviews', 'chatbot_messages',
        'chatbot_knowledge', 'chatbot_data_access_logs',
        'store_expenses', 'settings', 'setting_audit_logs', 'store_settings',
        'otp_codes'
    ];

    echo "-----------------------------------------------------------------\n";
    echo " Database Table Records Summary:\n";
    echo "-----------------------------------------------------------------\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM `{$table}`");
        $count = $stmt->fetchColumn();
        printf(" - %-25s : %d baris\n", $table, $count);
    }
    echo "-----------------------------------------------------------------\n";
    echo "All " . count($tables) . " tables migrated and verified successfully!\n\n";

} catch (Exception $e) {
    echo "\n[ERROR] Migration/Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
