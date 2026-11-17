<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use PDO;

class Setting extends Model {
    protected string $table = 'settings';
    protected string $primaryKey = 'id';

    /**
     * In-memory static cache per request
     */
    protected static array $cache = [];
    protected static array $storeCache = [];

    /**
     * Permission and group constants
     */
    public const GROUP_GENERAL       = 'general';
    public const GROUP_PAYMENT       = 'payment';
    public const GROUP_REFUND        = 'refund';
    public const GROUP_COURIER_ORDER = 'courier_order';
    public const GROUP_NOTIFICATION  = 'notification';
    public const GROUP_CHATBOT       = 'chatbot';
    public const GROUP_SECURITY      = 'security';
    public const GROUP_MAINTENANCE   = 'maintenance';
    public const GROUP_INTEGRATION   = 'integration';

    public const GROUPS = [
        self::GROUP_GENERAL       => 'Umum',
        self::GROUP_PAYMENT       => 'Pembayaran',
        self::GROUP_REFUND        => 'Pengembalian',
        self::GROUP_COURIER_ORDER => 'Kurir dan Pesanan',
        self::GROUP_NOTIFICATION  => 'Notifikasi',
        self::GROUP_CHATBOT       => 'Chatbot',
        self::GROUP_SECURITY      => 'Keamanan',
        self::GROUP_MAINTENANCE   => 'Mode Pemeliharaan',
        self::GROUP_INTEGRATION   => 'Integrasi'
    ];

    /**
     * Role Edit Capabilities Matrix
     * owner_platform: can view & edit all groups (integration is read-only)
     * admin_owner: can view all, but can ONLY edit general, notification, chatbot
     */
    public const EDITABLE_GROUPS_BY_ROLE = [
        'owner_platform' => [
            self::GROUP_GENERAL,
            self::GROUP_PAYMENT,
            self::GROUP_REFUND,
            self::GROUP_COURIER_ORDER,
            self::GROUP_NOTIFICATION,
            self::GROUP_CHATBOT,
            self::GROUP_SECURITY,
            self::GROUP_MAINTENANCE
        ],
        'admin_owner' => [
            self::GROUP_GENERAL,
            self::GROUP_NOTIFICATION,
            self::GROUP_CHATBOT
        ]
    ];

    public const READ_ONLY_GROUPS = [
        self::GROUP_INTEGRATION
    ];

    /**
     * Clear in-memory cache
     */
    public static function clearCache(): void {
        self::$cache = [];
        self::$storeCache = [];
    }

    /**
     * Retrieve setting value with fallback order:
     * 1. settings table
     * 2. .env file / getenv()
     * 3. $default in code
     */
    public static function get(string $key, $default = null) {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value, value_type FROM `settings` WHERE `setting_key` = :key LIMIT 1");
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row !== false && $row['setting_value'] !== null) {
                $val = self::castValue($row['setting_value'], $row['value_type']);
                self::$cache[$key] = $val;
                return $val;
            }
        } catch (\Throwable $e) {
            // Database not ready or table missing
        }

        // Fallback 2: Check .env (try uppercase and exact key)
        $envKeys = [
            strtoupper($key),
            $key,
            strtoupper(str_replace('-', '_', $key))
        ];

        foreach ($envKeys as $envKey) {
            $envVal = env($envKey);
            if ($envVal !== null) {
                self::$cache[$key] = $envVal;
                return $envVal;
            }
        }

        // Fallback 3: Code default
        self::$cache[$key] = $default;
        return $default;
    }

    /**
     * Set setting value and log change to audit trail
     */
    public static function set(string $key, $value, ?int $userId = null, ?string $ip = null): bool {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT id, setting_value, value_type FROM `settings` WHERE `setting_key` = :key LIMIT 1");
            $stmt->execute(['key' => $key]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                // Setting key belum ada di tabel settings -> otomatis insert
                $valueType = is_array($value) ? 'JSON' : (is_bool($value) ? 'BOOL' : (is_int($value) ? 'INT' : 'STRING'));
                $newRawValue = self::serializeValue($value, $valueType);
                $ipAddress = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

                $pdo->beginTransaction();
                $insStmt = $pdo->prepare("INSERT INTO `settings` 
                    (`setting_key`, `setting_value`, `value_type`, `setting_group`, `label`, `description`, `is_editable_by`, `updated_by`, `created_at`, `updated_at`) 
                    VALUES (:key, :val, :vtype, :grp, :lbl, :desc, 'owner_platform', :uid, NOW(), NOW())");
                $insStmt->execute([
                    'key'   => $key,
                    'val'   => $newRawValue,
                    'vtype' => $valueType,
                    'grp'   => self::GROUP_CHATBOT,
                    'lbl'   => ucwords(str_replace('_', ' ', $key)),
                    'desc'  => 'Dikonfigurasi melalui Web Admin',
                    'uid'   => $userId
                ]);

                // Audit log
                $auditStmt = $pdo->prepare("INSERT INTO `setting_audit_logs` 
                    (`setting_key`, `old_value`, `new_value`, `changed_by`, `ip_address`, `created_at`) 
                    VALUES (:key, NULL, :new, :uid, :ip, NOW())");
                $auditStmt->execute([
                    'key' => $key,
                    'new' => $newRawValue,
                    'uid' => $userId,
                    'ip'  => $ipAddress
                ]);

                $pdo->commit();
                self::$cache[$key] = self::castValue($newRawValue, $valueType);
                return true;
            }

            $oldRawValue = $current['setting_value'];
            $newRawValue = self::serializeValue($value, $current['value_type']);

            // Only log and update if value changed
            if ($oldRawValue !== $newRawValue) {
                $pdo->beginTransaction();

                // 1. Update setting
                $updateStmt = $pdo->prepare("UPDATE `settings` 
                    SET `setting_value` = :val, `updated_by` = :uid, `updated_at` = NOW() 
                    WHERE `id` = :id");
                $updateStmt->execute([
                    'val' => $newRawValue,
                    'uid' => $userId,
                    'id'  => $current['id']
                ]);

                // 2. Insert audit log
                $ipAddress = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
                $auditStmt = $pdo->prepare("INSERT INTO `setting_audit_logs` 
                    (`setting_key`, `old_value`, `new_value`, `changed_by`, `ip_address`, `created_at`) 
                    VALUES (:key, :old, :new, :uid, :ip, NOW())");
                $auditStmt->execute([
                    'key' => $key,
                    'old' => $oldRawValue,
                    'new' => $newRawValue,
                    'uid' => $userId,
                    'ip'  => $ipAddress
                ]);

                $pdo->commit();
            }

            // Update in-memory cache
            self::$cache[$key] = self::castValue($newRawValue, $current['value_type']);
            return true;
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Setting::set error for {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings in a specific group
     */
    public static function getGroup(string $group): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT s.*, u.name as updated_by_name 
                FROM `settings` s 
                LEFT JOIN `users` u ON s.updated_by = u.id 
                WHERE s.setting_group = :group 
                ORDER BY s.id ASC");
            $stmt->execute(['group' => $group]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $row['typed_value'] = self::castValue($row['setting_value'], $row['value_type']);
                $result[$row['setting_key']] = $row;
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Cast string representation to PHP type
     */
    protected static function castValue(?string $val, string $type) {
        if ($val === null) {
            return null;
        }

        switch (strtoupper($type)) {
            case 'INT':
                return (int)$val;
            case 'BOOL':
                return filter_var($val, FILTER_VALIDATE_BOOLEAN);
            case 'JSON':
                $decoded = json_decode($val, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
            case 'STRING':
            default:
                return (string)$val;
        }
    }

    /**
     * Serialize PHP type to database string representation
     */
    protected static function serializeValue($val, string $type): ?string {
        if ($val === null) {
            return null;
        }

        switch (strtoupper($type)) {
            case 'BOOL':
                return (filter_var($val, FILTER_VALIDATE_BOOLEAN) || $val === 1 || $val === '1' || $val === true) ? '1' : '0';
            case 'INT':
                return (string)((int)$val);
            case 'JSON':
                if (is_string($val)) {
                    // Ensure valid json
                    $test = json_decode($val, true);
                    return (json_last_error() === JSON_ERROR_NONE) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
                }
                return json_encode($val, JSON_UNESCAPED_UNICODE);
            case 'STRING':
            default:
                return (string)$val;
        }
    }

    /**
     * Check if a role can edit a setting group
     */
    public static function canRoleEditGroup(string $role, string $group): bool {
        if (in_array($group, self::READ_ONLY_GROUPS, true)) {
            return false;
        }
        $allowed = self::EDITABLE_GROUPS_BY_ROLE[$role] ?? [];
        return in_array($group, $allowed, true);
    }

    /**
     * Get Audit Logs with pagination and user join
     */
    public static function getAuditLogs(int $limit = 50, int $offset = 0): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT sal.*, u.name as user_name, u.email as user_email, r.display_name as role_display 
                FROM `setting_audit_logs` sal 
                LEFT JOIN `users` u ON sal.changed_by = u.id 
                LEFT JOIN `roles` r ON u.role_id = r.id 
                ORDER BY sal.id DESC 
                LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Count total audit logs
     */
    public static function countAuditLogs(): int {
        try {
            $pdo = Database::getConnection();
            return (int)$pdo->query("SELECT COUNT(*) FROM `setting_audit_logs`")->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =========================================================================
    // STORE SETTINGS HELPER (per outlet)
    // =========================================================================

    /**
     * Get setting for a specific store
     */
    public static function getStore(int $storeId, string $key, $default = null) {
        $cacheKey = "store_{$storeId}_{$key}";
        if (array_key_exists($cacheKey, self::$storeCache)) {
            return self::$storeCache[$cacheKey];
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM `store_settings` WHERE `store_id` = :sid AND `setting_key` = :key LIMIT 1");
            $stmt->execute(['sid' => $storeId, 'key' => $key]);
            $val = $stmt->fetchColumn();

            if ($val !== false && $val !== null) {
                self::$storeCache[$cacheKey] = $val;
                return $val;
            }
        } catch (\Throwable $e) {
            // fallback
        }

        self::$storeCache[$cacheKey] = $default;
        return $default;
    }

    /**
     * Set setting for a specific store and log to audit trail
     */
    public static function setStore(int $storeId, string $key, $value, ?int $userId = null, ?string $ip = null): bool {
        try {
            $pdo = Database::getConnection();
            
            // Get old value
            $checkStmt = $pdo->prepare("SELECT setting_value FROM `store_settings` WHERE `store_id` = :sid AND `setting_key` = :key LIMIT 1");
            $checkStmt->execute(['sid' => $storeId, 'key' => $key]);
            $oldValue = $checkStmt->fetchColumn();

            $strValue = is_array($value) ? json_encode($value) : (string)$value;

            $pdo->beginTransaction();

            if ($oldValue === false) {
                $ins = $pdo->prepare("INSERT INTO `store_settings` (`store_id`, `setting_key`, `setting_value`, `updated_by`, `created_at`, `updated_at`) 
                    VALUES (:sid, :key, :val, :uid, NOW(), NOW())");
                $ins->execute([
                    'sid' => $storeId,
                    'key' => $key,
                    'val' => $strValue,
                    'uid' => $userId
                ]);
                $oldValue = null;
            } else {
                $upd = $pdo->prepare("UPDATE `store_settings` SET `setting_value` = :val, `updated_by` = :uid, `updated_at` = NOW() 
                    WHERE `store_id` = :sid AND `setting_key` = :key");
                $upd->execute([
                    'val' => $strValue,
                    'uid' => $userId,
                    'sid' => $storeId,
                    'key' => $key
                ]);
            }

            // Record in audit log
            $ipAddress = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $auditKey = "store_{$storeId}:{$key}";
            $auditStmt = $pdo->prepare("INSERT INTO `setting_audit_logs` 
                (`setting_key`, `old_value`, `new_value`, `changed_by`, `ip_address`, `created_at`) 
                VALUES (:key, :old, :new, :uid, :ip, NOW())");
            $auditStmt->execute([
                'key' => $auditKey,
                'old' => $oldValue,
                'new' => $strValue,
                'uid' => $userId,
                'ip'  => $ipAddress
            ]);

            $pdo->commit();

            self::$storeCache["store_{$storeId}_{$key}"] = $strValue;
            return true;
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Setting::setStore error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings for a store as associative array
     */
    public static function getStoreAll(int $storeId): array {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM `store_settings` WHERE `store_id` = :sid");
            $stmt->execute(['sid' => $storeId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $r) {
                $result[$r['setting_key']] = $r['setting_value'];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // CRON HEARTBEAT TRACKING
    // =========================================================================

    /**
     * Record a heartbeat execution from a cron script
     */
    public static function recordCronHeartbeat(string $jobKey, string $status, string $summary, float $durationSeconds): bool {
        $data = [
            'job_key' => $jobKey,
            'status' => $status, // 'SUCCESS' | 'ERROR'
            'summary' => $summary,
            'duration_seconds' => round($durationSeconds, 4),
            'last_run' => date('Y-m-d H:i:s'),
            'timestamp' => time()
        ];

        return self::set('cron_heartbeat_' . $jobKey, json_encode($data));
    }

    /**
     * Retrieve status of all registered cron jobs with overdue detection
     */
    public static function getCronStatus(): array {
        $now = time();
        $jobs = [
            'check_expired_payments' => [
                'name' => 'Pemeriksaan Pembayaran QRIS Kedaluwarsa & Pengingat',
                'script' => 'cron_check_expired_payments.php',
                'expected_interval_minutes' => 15,
                'threshold_late_minutes' => 30
            ],
            'autocomplete_delivered_orders' => [
                'name' => 'Auto-Complete Pesanan Selesai Diantar',
                'script' => 'cron_autocomplete_delivered_orders.php',
                'expected_interval_minutes' => 60,
                'threshold_late_minutes' => 180
            ],
            'purge_chatbot_logs' => [
                'name' => 'Pembersihan / Retensi Log Chatbot Pesan Lama',
                'script' => 'cron_purge_chatbot_logs.php',
                'expected_interval_minutes' => 1440, // 24 hours
                'threshold_late_minutes' => 2880  // 48 hours
            ]
        ];

        $results = [];
        foreach ($jobs as $key => $info) {
            $raw = self::get('cron_heartbeat_' . $key);
            $heartbeat = is_array($raw) ? $raw : json_decode((string)$raw, true);

            $lastRun = $heartbeat['last_run'] ?? null;
            $status = $heartbeat['status'] ?? 'BELUM_PERNAH';
            $summary = $heartbeat['summary'] ?? 'Belum ada riwayat eksekusi tercatat.';
            $duration = $heartbeat['duration_seconds'] ?? null;

            $isLate = false;
            $diffMinutes = null;
            if ($lastRun) {
                $lastTime = strtotime($lastRun);
                $diffMinutes = round(($now - $lastTime) / 60);
                if ($diffMinutes > $info['threshold_late_minutes']) {
                    $isLate = true;
                }
            } else {
                $isLate = true;
            }

            $results[$key] = array_merge($info, [
                'last_run' => $lastRun,
                'status' => $status,
                'summary' => $summary,
                'duration_seconds' => $duration,
                'diff_minutes' => $diffMinutes,
                'is_late' => $isLate
            ]);
        }

        return $results;
    }

    // =========================================================================
    // SYSTEM INFORMATION & STATS (Tab 4)
    // =========================================================================

    /**
     * Compile system technical info and statistical data
     */
    public static function getSystemInfo(): array {
        $pdo = Database::getConnection();

        // 1. Technical Info
        $phpVersion = phpversion();
        $mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn() ?: 'Unknown';
        $timezone = date_default_timezone_get();
        $appEnv = env('APP_ENV', 'production');
        $appVersion = defined('APP_VERSION') ? APP_VERSION : '1.0.0';

        // Table count & Database size
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $tableCountStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :db");
        $tableCountStmt->execute(['db' => $dbName]);
        $tableCount = (int)$tableCountStmt->fetchColumn();

        $dbSizeStmt = $pdo->prepare("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = :db");
        $dbSizeStmt->execute(['db' => $dbName]);
        $dbSizeBytes = (float)$dbSizeStmt->fetchColumn();
        $dbSizeMb = round($dbSizeBytes / (1024 * 1024), 2);

        // Uploads & storage folder size
        $storageDir = ROOT_PATH . '/storage';
        $uploadsDir = ROOT_PATH . '/public/uploads';
        $storageSize = self::getDirectorySize($storageDir) + self::getDirectorySize($uploadsDir);
        $storageSizeMb = round($storageSize / (1024 * 1024), 2);

        // Error log size
        $errorLogFile = ROOT_PATH . '/storage/logs/error.log';
        $errorLogSizeKb = file_exists($errorLogFile) ? round(filesize($errorLogFile) / 1024, 2) : 0;

        // 2. Data Statistics
        // Users per role
        $roleStats = $pdo->query("SELECT r.name, r.display_name, COUNT(u.id) as total 
            FROM `roles` r 
            LEFT JOIN `users` u ON r.id = u.role_id 
            GROUP BY r.id, r.name, r.display_name 
            ORDER BY r.id ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Stores
        $storeStats = $pdo->query("SELECT 
            COUNT(*) as total_stores,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_stores,
            SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending_stores
            FROM `stores`")->fetch(PDO::FETCH_ASSOC);

        // Orders by status
        $orderStats = $pdo->query("SELECT status, COUNT(*) as total 
            FROM `orders` 
            GROUP BY status 
            ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);

        // Total orders and total GMV
        $orderSummary = $pdo->query("SELECT COUNT(*) as total_orders, COALESCE(SUM(total_price), 0) as total_gmv FROM `orders`")->fetch(PDO::FETCH_ASSOC);

        // Chatbot message count
        $chatbotCount = (int)$pdo->query("SELECT COUNT(*) FROM `chatbot_messages`")->fetchColumn();
        $chatbotFallbacks = (int)$pdo->query("SELECT COUNT(*) FROM `chatbot_messages` WHERE is_fallback = 1")->fetchColumn();

        return [
            'environment' => [
                'app_name' => self::get('app_name', 'SmartLaundry'),
                'app_version' => $appVersion,
                'php_version' => $phpVersion,
                'mysql_version' => $mysqlVersion,
                'app_env' => $appEnv,
                'timezone' => $timezone,
                'table_count' => $tableCount,
                'database_size_mb' => $dbSizeMb,
                'storage_usage_mb' => $storageSizeMb,
                'error_log_size_kb' => $errorLogSizeKb
            ],
            'stats' => [
                'roles' => $roleStats,
                'stores' => $storeStats,
                'orders' => $orderStats,
                'order_summary' => $orderSummary,
                'chatbot' => [
                    'total_messages' => $chatbotCount,
                    'fallback_messages' => $chatbotFallbacks
                ]
            ]
        ];
    }

    /**
     * Compute directory size recursively in bytes
     */
    protected static function getDirectorySize(string $path): int {
        if (!is_dir($path)) {
            return 0;
        }
        $size = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    // =========================================================================
    // INTEGRATION STATUS (Read-Only, Zero Secret Leaks)
    // =========================================================================

    /**
     * Get integration status without revealing keys
     */
    public static function getIntegrationStatus(): array {
        // 1. Midtrans QRIS
        $midtransKey = trim((string)env('MIDTRANS_SERVER_KEY', ''));
        $midtransGateway = strtolower(trim((string)env('PAYMENT_GATEWAY', 'simulated')));
        $midtransConfigured = !empty($midtransKey);

        // 2. Fonnte WhatsApp
        $waKey = trim((string)env('FONNTE_TOKEN', env('WA_API_KEY', env('WA_API_TOKEN', ''))));
        $waGateway = strtolower(trim((string)env('WHATSAPP_GATEWAY', env('WA_GATEWAY', 'simulated'))));
        $waEnabled = (bool)self::get('whatsapp_notification_enabled', true);
        $waConfigured = !empty($waKey);

        // 3. Google Maps
        $gmapsKey = trim((string)env('GOOGLE_MAPS_API_KEY', ''));
        $gmapsConfigured = !empty($gmapsKey);

        // 4. AI Provider
        $aiKey = trim((string)env('AI_API_KEY', ''));
        $aiProvider = strtolower(trim((string)env('AI_PROVIDER', 'mock')));
        $aiModel = trim((string)env('AI_MODEL', 'gemini-1.5-flash'));
        $aiConfigured = !empty($aiKey);

        return [
            'midtrans' => [
                'name' => 'Midtrans Payment Gateway (QRIS)',
                'is_configured' => $midtransConfigured,
                'status_label' => $midtransConfigured ? 'Terkonfigurasi' : 'Belum terkonfigurasi (mode simulasi)',
                'active_gateway' => $midtransGateway === 'midtrans' ? 'Midtrans Core API' : 'Simulated QRIS Adapter',
                'is_production' => (bool)env('MIDTRANS_IS_PRODUCTION', false)
            ],
            'whatsapp' => [
                'name' => 'Fonnte WhatsApp API Gateway',
                'is_configured' => $waConfigured,
                'status_label' => $waConfigured ? 'Terkonfigurasi' : 'Belum terkonfigurasi (token belum diisi)',
                'active_gateway' => ($waGateway === 'fonnte') ? 'Live Gateway (Fonnte)' : 'Mode Simulasi (Simulated)',
                'api_url' => env('WA_API_URL', 'https://api.fonnte.com/send')
            ],
            'google_maps' => [
                'name' => 'Google Maps Platform (Routing & Geocoding)',
                'is_configured' => $gmapsConfigured,
                'status_label' => $gmapsConfigured ? 'Terkonfigurasi' : 'Belum terkonfigurasi (mode simulasi OpenStreetMap)',
                'active_gateway' => $gmapsConfigured ? 'Google Maps JS API' : 'Leaflet + OpenStreetMap (Offline Demo)'
            ],
            'ai_service' => [
                'name' => 'AI Provider (Google Gemini / OpenAI)',
                'is_configured' => $aiConfigured,
                'status_label' => $aiConfigured ? 'Terkonfigurasi (' . strtoupper($aiProvider) . ')' : 'Belum terkonfigurasi (mode simulasi mock rule-based)',
                'active_gateway' => $aiProvider !== 'mock' && $aiConfigured ? "Live LLM ({$aiModel})" : 'Intelligent Rule-Based Mock',
                'model' => $aiModel
            ]
        ];
    }

    // =========================================================================
    // EXPORT CONFIGURATION (Clean, Zero Secrets)
    // =========================================================================

    /**
     * Export all system settings and system info without secrets
     */
    public static function exportConfiguration(string $format = 'json'): string {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT setting_key, setting_value, value_type, setting_group, label, description, is_editable_by, updated_at 
            FROM `settings` 
            ORDER BY setting_group, id ASC");
        $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sanitize: double-check that no keys match secret naming patterns
        $cleanedSettings = [];
        $forbiddenKeywords = ['key', 'secret', 'password', 'token', 'auth'];
        foreach ($allSettings as $row) {
            $k = strtolower($row['setting_key']);
            $isSecret = false;
            foreach ($forbiddenKeywords as $bad) {
                if (str_contains($k, $bad) && !in_array($k, ['setting_key'], true)) {
                    $isSecret = true;
                    break;
                }
            }
            if (!$isSecret) {
                $cleanedSettings[] = $row;
            }
        }

        if (strtolower($format) === 'csv') {
            $fp = fopen('php://memory', 'w+');
            fputcsv($fp, ['Group', 'Key', 'Value', 'Type', 'Label', 'Description', 'EditableBy', 'UpdatedAt']);
            foreach ($cleanedSettings as $s) {
                fputcsv($fp, [
                    $s['setting_group'],
                    $s['setting_key'],
                    $s['setting_value'],
                    $s['value_type'],
                    $s['label'],
                    $s['description'],
                    $s['is_editable_by'],
                    $s['updated_at']
                ]);
            }
            rewind($fp);
            $csv = stream_get_contents($fp);
            fclose($fp);
            return $csv;
        }

        // Default: JSON export
        $exportPayload = [
            'app' => [
                'name' => self::get('app_name', 'SmartLaundry'),
                'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
                'exported_at' => date('Y-m-d H:i:s'),
                'exported_by' => auth_user()['email'] ?? 'system'
            ],
            'system_environment' => self::getSystemInfo()['environment'],
            'settings' => $cleanedSettings,
            'cron_status' => self::getCronStatus(),
            'integration_status' => self::getIntegrationStatus()
        ];

        return json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
