<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class WhatsAppNotificationRecipient extends Model {
    protected string $table = 'whatsapp_notification_recipients';
    protected string $primaryKey = 'id';

    /**
     * Get recipients list for a given category and scope/store
     */
    public function getRecipients(string $category, string $scope = 'GLOBAL', ?int $storeId = null): array {
        $category = strtoupper(trim($category));
        $scope = strtoupper($scope) === 'STORE' ? 'STORE' : 'GLOBAL';
        $db = Database::getConnection();

        $sql = "SELECT * FROM `{$this->table}` WHERE `category` = :cat AND `scope` = :scope";
        $params = ['cat' => $category, 'scope' => $scope];

        if ($scope === 'STORE' && $storeId !== null) {
            $sql .= " AND `store_id` = :sid";
            $params['sid'] = $storeId;
        }

        $sql .= " ORDER BY `created_at` DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add a new notification recipient
     */
    public function addRecipient(
        string $scope,
        ?int $storeId,
        string $category,
        string $recipientName,
        string $phoneNumber,
        string $role = 'STAFF'
    ): int {
        $scope = strtoupper($scope) === 'STORE' ? 'STORE' : 'GLOBAL';
        $storeId = ($scope === 'STORE') ? (int)$storeId : null;
        $category = strtoupper(trim($category));
        $normalizedPhone = normalize_phone_number($phoneNumber);

        if (empty($normalizedPhone)) {
            return 0;
        }

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "INSERT INTO `{$this->table}`
                (`scope`, `store_id`, `category`, `recipient_name`, `phone_number`, `role`, `is_enabled`, `created_at`, `updated_at`)
             VALUES
                (:scope, :store_id, :cat, :name, :phone, :role, 1, NOW(), NOW())"
        );
        $stmt->execute([
            'scope'    => $scope,
            'store_id' => $storeId,
            'cat'      => $category,
            'name'     => trim($recipientName),
            'phone'    => $normalizedPhone,
            'role'     => trim($role)
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * Delete recipient with anti-IDOR validation
     */
    public function deleteRecipient(int $id, ?int $storeId = null, bool $isPlatformAdmin = false): bool {
        $db = Database::getConnection();

        // Anti-IDOR check: if not platform admin, recipient must belong to owner's store
        if (!$isPlatformAdmin) {
            if ($storeId === null || $storeId <= 0) {
                return false;
            }
            $checkStmt = $db->prepare("SELECT `store_id`, `scope` FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
            $checkStmt->execute(['id' => $id]);
            $row = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || (int)$row['store_id'] !== (int)$storeId || $row['scope'] !== 'STORE') {
                return false; // Access denied / IDOR attempt
            }
        }

        $stmt = $db->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Toggle recipient enabled state with anti-IDOR validation
     */
    public function toggleRecipient(int $id, bool $isEnabled, ?int $storeId = null, bool $isPlatformAdmin = false): bool {
        $db = Database::getConnection();

        if (!$isPlatformAdmin) {
            if ($storeId === null || $storeId <= 0) {
                return false;
            }
            $checkStmt = $db->prepare("SELECT `store_id`, `scope` FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
            $checkStmt->execute(['id' => $id]);
            $row = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || (int)$row['store_id'] !== (int)$storeId || $row['scope'] !== 'STORE') {
                return false;
            }
        }

        $stmt = $db->prepare("UPDATE `{$this->table}` SET `is_enabled` = :enabled, `updated_at` = NOW() WHERE `id` = :id");
        return $stmt->execute([
            'enabled' => $isEnabled ? 1 : 0,
            'id'      => $id
        ]);
    }

    /**
     * Retrieve all active phone numbers for an event category and optional store
     */
    public function getActivePhoneNumbers(string $category, ?int $storeId = null): array {
        $category = strtoupper(trim($category));
        $db = Database::getConnection();

        $sql = "SELECT DISTINCT `phone_number` FROM `{$this->table}` WHERE `category` = :cat AND `is_enabled` = 1";
        $params = ['cat' => $category];

        if ($storeId !== null && $storeId > 0) {
            $sql .= " AND (`scope` = 'STORE' AND `store_id` = :sid)";
            $params['sid'] = $storeId;
        } else {
            $sql .= " AND `scope` = 'GLOBAL'";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
