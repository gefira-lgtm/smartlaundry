<?php
namespace App\Models;

use App\Core\Model;

class Address extends Model {
    protected string $table = 'addresses';
    protected string $primaryKey = 'id';

    /**
     * Find all addresses for a specific user ordered by primary first
     */
    public function findByUser(int $userId): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = :user_id 
                ORDER BY `is_primary` DESC, `id` DESC";
        return $this->query($sql, ['user_id' => $userId]);
    }

    /**
     * Find address by ID strictly validating user ownership
     */
    public function findByIdAndUser(int $addressId, int $userId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = :id AND `user_id` = :user_id 
                LIMIT 1";
        return $this->firstQuery($sql, [
            'id' => $addressId,
            'user_id' => $userId
        ]);
    }

    /**
     * Set an address as primary and un-set others for the same user (Atomic Transaction)
     */
    public function setPrimary(int $addressId, int $userId): bool {
        // Validate address exists and belongs to user
        $address = $this->findByIdAndUser($addressId, $userId);
        if (!$address) {
            return false;
        }

        $this->beginTransaction();
        try {
            // Set all user addresses to is_primary = 0
            $sqlReset = "UPDATE `{$this->table}` SET `is_primary` = 0 WHERE `user_id` = :user_id";
            $stmtReset = $this->db->prepare($sqlReset);
            $stmtReset->execute(['user_id' => $userId]);

            // Set target address to is_primary = 1
            $sqlSet = "UPDATE `{$this->table}` SET `is_primary` = 1 WHERE `id` = :id AND `user_id` = :user_id";
            $stmtSet = $this->db->prepare($sqlSet);
            $stmtSet->execute(['id' => $addressId, 'user_id' => $userId]);

            $this->commit();
            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Check if address is currently referenced in an active (uncompleted) order
     */
    public function isUsedInActiveOrder(int $addressId): bool {
        $sql = "SELECT COUNT(*) AS total FROM `orders` 
                WHERE (`pickup_address_id` = :addr_id1 OR `delivery_address_id` = :addr_id2)
                  AND `status` NOT IN ('SELESAI', 'DIBATALKAN')";
        $res = $this->firstQuery($sql, [
            'addr_id1' => $addressId,
            'addr_id2' => $addressId
        ]);
        return ((int)($res['total'] ?? 0)) > 0;
    }

    /**
     * Delete address safely checking active order usage
     */
    public function deleteAddress(int $addressId, int $userId): bool {
        $address = $this->findByIdAndUser($addressId, $userId);
        if (!$address) {
            return false;
        }

        if ($this->isUsedInActiveOrder($addressId)) {
            return false;
        }

        return (bool)$this->delete($addressId);
    }
}
