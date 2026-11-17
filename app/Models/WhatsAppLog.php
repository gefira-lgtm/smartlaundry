<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class WhatsAppLog extends Model {
    protected string $table = 'whatsapp_logs';
    protected string $primaryKey = 'id';

    /**
     * Find log entry by unique dedupe key
     */
    public function findByDedupeKey(string $dedupeKey): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM `{$this->table}` WHERE `dedupe_key` = :key LIMIT 1");
        $stmt->execute(['key' => $dedupeKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Helper to mask sensitive OTP codes from log storage
     */
    public static function maskSensitiveMessage(string $message, string $category): string {
        if (strtoupper($category) === 'OTP') {
            // Mask numeric OTP codes (e.g. *123456* -> *12**** or 123456 -> 12****)
            return preg_replace_callback('/\b([0-9]{2})([0-9]{2,6})\b/', function($matches) {
                return $matches[1] . str_repeat('*', strlen($matches[2]));
            }, $message);
        }
        return $message;
    }

    /**
     * Record an initial outbound message in PENDING status (Outbox Pattern)
     * If dedupe key is present and already exists, returns 0 without inserting
     */
    public function createPending(
        string $phone,
        ?string $recipientName,
        string $event,
        string $message,
        ?string $dedupeKey = null,
        string $category = 'TRANSACTION',
        ?int $storeId = null,
        ?int $orderId = null
    ): int {
        $db = Database::getConnection();

        if (!empty($dedupeKey)) {
            $existing = $this->findByDedupeKey($dedupeKey);
            if ($existing) {
                return 0; // Already exists, skip insertion
            }
        }

        $category = strtoupper(trim($category));
        if (!in_array($category, ['OTP', 'TRANSACTION', 'ACTIVITY'])) {
            $category = 'TRANSACTION';
        }

        $storedMessage = self::maskSensitiveMessage($message, $category);

        try {
            $stmt = $db->prepare(
                "INSERT INTO `{$this->table}` 
                 (`phone`, `recipient_name`, `event`, `category`, `store_id`, `order_id`, `message`, `status`, `dedupe_key`, `attempts`, `sent_at`)
                 VALUES (:phone, :recipient_name, :event, :category, :store_id, :order_id, :message, 'PENDING', :dedupe_key, 0, NOW())"
            );
            $stmt->execute([
                'phone'          => trim($phone),
                'recipient_name' => trim($recipientName ?? 'Customer/User'),
                'event'          => trim($event),
                'category'       => $category,
                'store_id'       => $storeId,
                'order_id'       => $orderId,
                'message'        => trim($storedMessage),
                'dedupe_key'     => $dedupeKey
            ]);
            return (int)$db->lastInsertId();
        } catch (\PDOException $e) {
            // Check for duplicate key race condition
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * Update log status, error, provider message ID, and retry attributes
     */
    public function updateStatus(
        int $id,
        string $status,
        ?string $lastError = null,
        ?string $nextRetryAt = null,
        bool $incrementAttempts = false,
        ?string $providerMessageId = null
    ): bool {
        $db = Database::getConnection();
        $sql = "UPDATE `{$this->table}` SET `status` = :status, `last_error` = :last_error, `next_retry_at` = :next_retry_at";
        $params = [
            'id'            => $id,
            'status'        => $status,
            'last_error'    => $lastError,
            'next_retry_at' => $nextRetryAt
        ];

        if ($incrementAttempts) {
            $sql .= ", `attempts` = `attempts` + 1";
        }

        if ($providerMessageId !== null) {
            $sql .= ", `provider_message_id` = :provider_msg_id";
            $params['provider_msg_id'] = $providerMessageId;
        }

        $sql .= " WHERE `id` = :id";

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Fetch failed messages ready for retry (attempts < 3)
     */
    public function getPendingRetries(int $limit = 30): array {
        $db = Database::getConnection();
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `status` = 'FAILED' 
                  AND `attempts` < 3 
                  AND (`next_retry_at` IS NULL OR `next_retry_at` <= NOW())
                ORDER BY `sent_at` ASC 
                LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Log a simulated WhatsApp outbound message (legacy fallback)
     */
    public function log(
        string $phone,
        ?string $recipientName,
        string $event,
        string $message,
        string $status = 'SENT',
        string $category = 'TRANSACTION',
        ?int $storeId = null,
        ?int $orderId = null
    ): int {
        $db = Database::getConnection();
        $category = strtoupper(trim($category));
        if (!in_array($category, ['OTP', 'TRANSACTION', 'ACTIVITY'])) {
            $category = 'TRANSACTION';
        }
        $storedMessage = self::maskSensitiveMessage($message, $category);

        $stmt = $db->prepare(
            "INSERT INTO `{$this->table}` 
             (`phone`, `recipient_name`, `event`, `category`, `store_id`, `order_id`, `message`, `status`, `sent_at`)
             VALUES (:phone, :recipient_name, :event, :category, :store_id, :order_id, :message, :status, NOW())"
        );
        $stmt->execute([
            'phone'          => trim($phone),
            'recipient_name' => trim($recipientName ?? 'Customer/User'),
            'event'          => trim($event),
            'category'       => $category,
            'store_id'       => $storeId,
            'order_id'       => $orderId,
            'message'        => trim($storedMessage),
            'status'         => $status,
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * Get recent WhatsApp logs ordered by sent_at DESC
     */
    public function getAllLogs(int $limit = 50): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM `{$this->table}` ORDER BY `sent_at` DESC LIMIT :limit");
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add phone_number alias for view compatibility
     */
    public function getLogsForView(int $limit = 50): array {
        $db = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT *, `phone` AS `phone_number`, `event` AS `event_type`, `sent_at` AS `created_at`
             FROM `{$this->table}` ORDER BY `sent_at` DESC LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get scoped and filtered logs (Strict Anti-IDOR for Store Owners)
     */
    public function getFilteredLogs(
        ?string $category = null,
        ?int $storeId = null,
        ?string $status = null,
        ?string $search = null,
        int $limit = 50,
        bool $excludeOtp = false
    ): array {
        $db = Database::getConnection();
        $sql = "SELECT *, `phone` AS `phone_number`, `event` AS `event_type`, `sent_at` AS `created_at` 
                FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($category)) {
            $sql .= " AND `category` = :cat";
            $params['cat'] = strtoupper($category);
        }

        if ($excludeOtp) {
            $sql .= " AND `category` != 'OTP'";
        }

        if ($storeId !== null && $storeId > 0) {
            $sql .= " AND `store_id` = :sid";
            $params['sid'] = $storeId;
        }

        if (!empty($status)) {
            $sql .= " AND `status` = :status";
            $params['status'] = strtoupper($status);
        }

        if (!empty($search)) {
            $sql .= " AND (`phone` LIKE :search OR `recipient_name` LIKE :search OR `event` LIKE :search OR `message` LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        $sql .= " ORDER BY `sent_at` DESC LIMIT :limit";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get counts of SENT, FAILED, SIMULATED, etc. for today (optionally scoped)
     */
    public function getTodayStats(?int $storeId = null, ?string $category = null): array {
        $db = Database::getConnection();
        $sql = "SELECT 
                    COUNT(*) as total_today,
                    SUM(CASE WHEN `status` = 'SENT' THEN 1 ELSE 0 END) as sent_today,
                    SUM(CASE WHEN `status` = 'FAILED' THEN 1 ELSE 0 END) as failed_today,
                    SUM(CASE WHEN `status` = 'SIMULATED' THEN 1 ELSE 0 END) as simulated_today,
                    SUM(CASE WHEN `status` = 'PENDING' THEN 1 ELSE 0 END) as pending_today,
                    SUM(CASE WHEN `status` = 'SKIPPED_NOT_ALLOWED' THEN 1 ELSE 0 END) as skipped_today
                FROM `{$this->table}` 
                WHERE DATE(`sent_at`) = CURDATE()";
        
        $params = [];
        if ($storeId !== null && $storeId > 0) {
            $sql .= " AND `store_id` = :sid AND `category` != 'OTP'";
            $params['sid'] = $storeId;
        }

        if (!empty($category)) {
            $sql .= " AND `category` = :cat";
            $params['cat'] = strtoupper($category);
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total_today' => (int)($res['total_today'] ?? 0),
            'sent_today' => (int)($res['sent_today'] ?? 0),
            'failed_today' => (int)($res['failed_today'] ?? 0),
            'simulated_today' => (int)($res['simulated_today'] ?? 0),
            'pending_today' => (int)($res['pending_today'] ?? 0),
            'skipped_today' => (int)($res['skipped_today'] ?? 0),
        ];
    }

    /**
     * Get 10 most recent failed logs with their error reasons
     */
    public function getRecentFailures(int $limit = 10, ?int $storeId = null): array {
        $db = Database::getConnection();
        $sql = "SELECT id, phone, recipient_name, event, last_error, attempts, sent_at
                FROM `{$this->table}`
                WHERE `status` = 'FAILED'";
        $params = [];

        if ($storeId !== null && $storeId > 0) {
            $sql .= " AND `store_id` = :sid AND `category` != 'OTP'";
            $params['sid'] = $storeId;
        }

        $sql .= " ORDER BY `sent_at` DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
