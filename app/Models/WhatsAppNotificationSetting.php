<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Models\Setting;

class WhatsAppNotificationSetting extends Model {
    protected string $table = 'whatsapp_notification_settings';
    protected string $primaryKey = 'id';

    /**
     * Check if a notification category is globally or store-level enabled
     */
    public function isCategoryEnabled(string $category, ?int $storeId = null): bool {
        $category = strtoupper(trim($category));
        $db = Database::getConnection();

        // 1. If Category is OTP: Global only (storeId ignored for security)
        if ($category === 'OTP') {
            $stmt = $db->prepare(
                "SELECT `is_enabled` FROM `{$this->table}` 
                 WHERE `scope` = 'GLOBAL' AND `category` = 'OTP' AND `event_key` = 'master' 
                 LIMIT 1"
            );
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val !== false) {
                return (bool)$val;
            }
            return (bool)Setting::get('wa_otp_enabled', true);
        }

        // 2. If Category is TRANSACTION or ACTIVITY
        // Check store level override if storeId is passed
        if ($storeId !== null && $storeId > 0) {
            $stmt = $db->prepare(
                "SELECT `is_enabled` FROM `{$this->table}` 
                 WHERE `scope` = 'STORE' AND `store_id` = :sid AND `category` = :cat AND `event_key` = 'master' 
                 LIMIT 1"
            );
            $stmt->execute(['sid' => $storeId, 'cat' => $category]);
            $val = $stmt->fetchColumn();
            if ($val !== false) {
                return (bool)$val;
            }
        }

        // Check global category master switch
        $stmt = $db->prepare(
            "SELECT `is_enabled` FROM `{$this->table}` 
             WHERE `scope` = 'GLOBAL' AND `category` = :cat AND `event_key` = 'master' 
             LIMIT 1"
        );
        $stmt->execute(['cat' => $category]);
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            return (bool)$val;
        }

        if ($category === 'TRANSACTION') {
            return (bool)Setting::get('whatsapp_notification_enabled', true);
        }

        return (bool)Setting::get('wa_activity_enabled', true);
    }

    /**
     * Check if a specific event is enabled
     */
    public function isEnabled(string $category, string $eventKey, ?int $storeId = null): bool {
        $category = strtoupper(trim($category));
        $eventKey = strtolower(trim($eventKey));

        // 1. First check if category master is enabled
        if (!$this->isCategoryEnabled($category, $storeId)) {
            return false;
        }

        $db = Database::getConnection();

        // 2. Check store-level event override
        if ($storeId !== null && $storeId > 0 && $category !== 'OTP') {
            $stmt = $db->prepare(
                "SELECT `is_enabled` FROM `{$this->table}` 
                 WHERE `scope` = 'STORE' AND `store_id` = :sid AND `category` = :cat AND `event_key` = :ev 
                 LIMIT 1"
            );
            $stmt->execute(['sid' => $storeId, 'cat' => $category, 'ev' => $eventKey]);
            $val = $stmt->fetchColumn();
            if ($val !== false) {
                return (bool)$val;
            }
        }

        // 3. Check global event setting
        $stmt = $db->prepare(
            "SELECT `is_enabled` FROM `{$this->table}` 
             WHERE `scope` = 'GLOBAL' AND `category` = :cat AND `event_key` = :ev 
             LIMIT 1"
        );
        $stmt->execute(['cat' => $category, 'ev' => $eventKey]);
        $val = $stmt->fetchColumn();
        if ($val !== false) {
            return (bool)$val;
        }

        // Default: enabled
        return true;
    }

    /**
     * Get compiled or custom template for an event
     */
    public function getTemplate(string $category, string $eventKey, ?int $storeId = null, ?string $fallback = null): string {
        $category = strtoupper(trim($category));
        $eventKey = strtolower(trim($eventKey));
        $db = Database::getConnection();

        // 1. Check store custom template (if storeId given and category != OTP)
        if ($storeId !== null && $storeId > 0 && $category !== 'OTP') {
            $stmt = $db->prepare(
                "SELECT `custom_template` FROM `{$this->table}` 
                 WHERE `scope` = 'STORE' AND `store_id` = :sid AND `category` = :cat AND `event_key` = :ev 
                 LIMIT 1"
            );
            $stmt->execute(['sid' => $storeId, 'cat' => $category, 'ev' => $eventKey]);
            $tpl = $stmt->fetchColumn();
            if (!empty($tpl)) {
                return trim((string)$tpl);
            }
        }

        // 2. Check global custom template
        $stmt = $db->prepare(
            "SELECT `custom_template` FROM `{$this->table}` 
             WHERE `scope` = 'GLOBAL' AND `category` = :cat AND `event_key` = :ev 
             LIMIT 1"
        );
        $stmt->execute(['cat' => $category, 'ev' => $eventKey]);
        $tpl = $stmt->fetchColumn();
        if (!empty($tpl)) {
            return trim((string)$tpl);
        }

        // 3. Check Setting model fallback (wa_template_{eventKey})
        $settingTpl = Setting::get('wa_template_' . $eventKey);
        if (!empty($settingTpl)) {
            return (string)$settingTpl;
        }

        // 4. Fallback to notification_templates.php config or provided fallback
        if (!empty($fallback)) {
            return $fallback;
        }

        $templateFile = ROOT_PATH . '/config/notification_templates.php';
        if (file_exists($templateFile)) {
            $configs = require $templateFile;
            if (isset($configs[$eventKey])) {
                return $configs[$eventKey];
            }
        }

        return "SmartLaundry: Notifikasi {$eventKey}.";
    }

    /**
     * Save / update a setting
     */
    public function setSetting(
        string $scope,
        ?int $storeId,
        string $category,
        string $eventKey,
        bool $isEnabled,
        ?string $customTemplate = null
    ): bool {
        $scope = strtoupper($scope) === 'STORE' ? 'STORE' : 'GLOBAL';
        $storeId = ($scope === 'STORE') ? (int)$storeId : null;
        $category = strtoupper(trim($category));
        $eventKey = strtolower(trim($eventKey));

        $db = Database::getConnection();
        $stmt = $db->prepare(
            "INSERT INTO `{$this->table}` 
                (`scope`, `store_id`, `category`, `event_key`, `is_enabled`, `custom_template`, `updated_at`)
             VALUES 
                (:scope, :store_id, :cat, :ev, :enabled, :tpl, NOW())
             ON DUPLICATE KEY UPDATE 
                `is_enabled` = VALUES(`is_enabled`),
                `custom_template` = VALUES(`custom_template`),
                `updated_at` = NOW()"
        );

        return $stmt->execute([
            'scope'    => $scope,
            'store_id' => $storeId,
            'cat'      => $category,
            'ev'       => $eventKey,
            'enabled'  => $isEnabled ? 1 : 0,
            'tpl'      => $customTemplate !== null ? trim($customTemplate) : null
        ]);
    }

    /**
     * Get all settings mapped by event_key for a given category and scope
     */
    public function getCategorySettings(string $category, string $scope = 'GLOBAL', ?int $storeId = null): array {
        $category = strtoupper(trim($category));
        $scope = strtoupper($scope) === 'STORE' ? 'STORE' : 'GLOBAL';
        $db = Database::getConnection();

        $sql = "SELECT * FROM `{$this->table}` WHERE `scope` = :scope AND `category` = :cat";
        $params = ['scope' => $scope, 'cat' => $category];

        if ($scope === 'STORE' && $storeId !== null) {
            $sql .= " AND `store_id` = :sid";
            $params['sid'] = $storeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $r) {
            $map[$r['event_key']] = $r;
        }
        return $map;
    }
}
