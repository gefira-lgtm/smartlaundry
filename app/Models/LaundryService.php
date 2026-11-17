<?php
namespace App\Models;

use App\Core\Model;

class LaundryService extends Model {
    protected string $table = 'laundry_services';
    protected string $primaryKey = 'id';

    /**
     * Get active services for a store
     */
    public function getByStoreId(int $storeId): array {
        return $this->where(['store_id' => $storeId, 'is_active' => 1], 'price ASC');
    }

    /**
     * Get all services for a store (for store management), both active and inactive
     */
    public function getAllByStore(int $storeId): array {
        $sql = "SELECT ls.*, 
                       (SELECT COUNT(DISTINCT oi.order_id) FROM `order_items` oi WHERE oi.service_id = ls.id) AS total_orders_used
                FROM `{$this->table}` ls
                WHERE ls.store_id = :store_id
                ORDER BY ls.is_active DESC, ls.category ASC, ls.name ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Find any service by ID and store_id (active or inactive)
     */
    public function findByIdAndStore(int $serviceId, int $storeId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = :id AND `store_id` = :store_id 
                LIMIT 1";
        return $this->firstQuery($sql, ['id' => $serviceId, 'store_id' => $storeId]);
    }

    /**
     * Find active service by ID and ensure it belongs to the given store
     */
    public function findActiveByIdAndStore(int $serviceId, int $storeId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `id` = :id AND `store_id` = :store_id AND `is_active` = 1 
                LIMIT 1";
        return $this->firstQuery($sql, ['id' => $serviceId, 'store_id' => $storeId]);
    }

    /**
     * Check if service is currently referenced in an active (in-progress) order
     */
    public function isUsedInActiveOrders(int $serviceId): bool {
        $sql = "SELECT COUNT(*) AS total 
                FROM `order_items` oi
                JOIN `orders` o ON oi.order_id = o.id
                WHERE oi.service_id = :service_id 
                  AND o.status NOT IN ('SELESAI', 'DIBATALKAN')";
        $res = $this->firstQuery($sql, ['service_id' => $serviceId]);
        return ((int)($res['total'] ?? 0)) > 0;
    }

    /**
     * Create a new service for a store
     */
    public function createService(array $data): int {
        return (int)$this->create($data);
    }

    /**
     * Update an existing service ensuring store ownership
     */
    public function updateService(int $id, int $storeId, array $data): bool {
        $svc = $this->findByIdAndStore($id, $storeId);
        if (!$svc) return false;
        return (bool)$this->update($id, $data);
    }

    /**
     * Toggle active status for a service
     */
    public function toggleActive(int $id, int $storeId): bool {
        $svc = $this->findByIdAndStore($id, $storeId);
        if (!$svc) return false;
        $newStatus = $svc['is_active'] ? 0 : 1;
        return (bool)$this->update($id, ['is_active' => $newStatus]);
    }

    /**
     * Delete service safely with active orders check
     */
    public function deleteService(int $id, int $storeId): bool {
        $svc = $this->findByIdAndStore($id, $storeId);
        if (!$svc) return false;

        if ($this->isUsedInActiveOrders($id)) {
            return false;
        }

        return (bool)$this->delete($id);
    }
}

