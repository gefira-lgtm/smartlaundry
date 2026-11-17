<?php
namespace App\Models;

use App\Core\Model;

class Store extends Model {
    protected string $table = 'stores';
    protected string $primaryKey = 'id';

    /**
     * Get all stores with owner details and order counts for Platform Dashboard
     */
    public function getAllWithStats(): array {
        $sql = "SELECT s.*, u.name AS owner_name, u.email AS owner_email,
                       (SELECT COUNT(*) FROM `orders` o WHERE o.store_id = s.id) AS total_orders,
                       (SELECT COALESCE(SUM(p.amount), 0) FROM `payments` p 
                        JOIN `orders` o ON p.order_id = o.id 
                        WHERE o.store_id = s.id AND p.status = 'PAID') AS total_revenue
                FROM `{$this->table}` s
                JOIN `users` u ON s.owner_id = u.id
                ORDER BY s.id ASC";
        return $this->query($sql);
    }

    /**
     * Find store for a specific user (either store owner or store admin)
     */
    public function findForUser(int $userId, string $role): ?array {
        if ($role === 'owner_toko') {
            $sql = "SELECT s.*, u.name AS owner_name 
                    FROM `{$this->table}` s
                    JOIN `users` u ON s.owner_id = u.id
                    WHERE s.owner_id = :user_id 
                    LIMIT 1";
            return $this->firstQuery($sql, ['user_id' => $userId]);
        }

        if ($role === 'admin_toko') {
            $sql = "SELECT s.*, u.name AS owner_name, sa.role_in_store 
                    FROM `{$this->table}` s
                    JOIN `users` u ON s.owner_id = u.id
                    JOIN `store_admins` sa ON s.id = sa.store_id
                    WHERE sa.user_id = :user_id 
                    LIMIT 1";
            return $this->firstQuery($sql, ['user_id' => $userId]);
        }

        return null;
    }

    /**
     * Get statistical summary for a single store
     */
    public function getStoreSummary(int $storeId): array {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');

        // Orders today
        $sqlToday = "SELECT COUNT(*) FROM `orders` WHERE `store_id` = :store_id AND DATE(`created_at`) = :today";
        $ordersToday = (int)($this->firstQuery($sqlToday, ['store_id' => $storeId, 'today' => $today])['COUNT(*)'] ?? 0);

        // Pending confirmation
        $sqlPending = "SELECT COUNT(*) FROM `orders` WHERE `store_id` = :store_id AND `status` = 'MENUNGGU_KONFIRMASI'";
        $ordersPending = (int)($this->firstQuery($sqlPending, ['store_id' => $storeId])['COUNT(*)'] ?? 0);

        // In weighing / QRIS pending
        $sqlWeighing = "SELECT COUNT(*) FROM `orders` WHERE `store_id` = :store_id AND `status` IN ('SAMPAI_DI_TOKO', 'SEDANG_DITIMBANG', 'MENUNGGU_PEMBAYARAN')";
        $ordersWeighing = (int)($this->firstQuery($sqlWeighing, ['store_id' => $storeId])['COUNT(*)'] ?? 0);

        // In washing / processing
        $sqlWashing = "SELECT COUNT(*) FROM `orders` WHERE `store_id` = :store_id AND `status` IN ('SEDANG_DICUCI', 'SEDANG_DIPROSES')";
        $ordersWashing = (int)($this->firstQuery($sqlWashing, ['store_id' => $storeId])['COUNT(*)'] ?? 0);

        // Monthly revenue
        $sqlRevenue = "SELECT COALESCE(SUM(p.amount), 0) AS total_revenue 
                       FROM `payments` p
                       JOIN `orders` o ON p.order_id = o.id
                       WHERE o.store_id = :store_id 
                         AND p.status = 'PAID' 
                         AND DATE_FORMAT(p.paid_at, '%Y-%m') = :this_month";
        $monthlyRevenue = (float)($this->firstQuery($sqlRevenue, ['store_id' => $storeId, 'this_month' => $thisMonth])['total_revenue'] ?? 0);

        // Rating
        $store = $this->find($storeId);

        return [
            'orders_today' => $ordersToday,
            'orders_pending' => $ordersPending,
            'orders_weighing' => $ordersWeighing,
            'orders_washing' => $ordersWashing,
            'monthly_revenue' => $monthlyRevenue,
            'rating_avg' => (float)($store['rating_avg'] ?? 5.0),
            'rating_count' => (int)($store['rating_count'] ?? 0)
        ];
    }

    /**
     * Get top services performance for store
     */
    public function getTopServices(int $storeId, int $limit = 5): array {
        $sql = "SELECT ls.id, ls.name, ls.category, ls.price, ls.unit,
                       COALESCE(SUM(oi.quantity), 0) AS total_quantity,
                       COALESCE(SUM(oi.subtotal), 0) AS total_sales,
                       COUNT(DISTINCT oi.order_id) AS orders_count
                FROM `laundry_services` ls
                LEFT JOIN `order_items` oi ON ls.id = oi.service_id
                LEFT JOIN `orders` o ON oi.order_id = o.id AND o.store_id = :store_id
                WHERE ls.store_id = :store_id_where
                GROUP BY ls.id
                ORDER BY total_sales DESC, total_quantity DESC
                LIMIT {$limit}";
        return $this->query($sql, ['store_id' => $storeId, 'store_id_where' => $storeId]);
    }

    /**
     * Find store by ID along with its linked address and owner details
     */
    public function findWithAddress(int $storeId): ?array {
        $sql = "SELECT s.*, u.name AS owner_name, u.email AS owner_email,
                       a.full_address, a.rt, a.rw, a.kelurahan, a.kecamatan, 
                       a.kota, a.provinsi, a.postal_code, a.latitude, a.longitude,
                       a.recipient_name, a.phone AS address_phone, a.notes AS address_notes
                FROM `{$this->table}` s
                JOIN `users` u ON s.owner_id = u.id
                LEFT JOIN `addresses` a ON s.address_id = a.id
                WHERE s.id = :id
                LIMIT 1";
        return $this->firstQuery($sql, ['id' => $storeId]);
    }

    /**
     * Update store profile data
     */
    public function updateProfile(int $storeId, array $data): bool {
        return (bool)$this->update($storeId, $data);
    }

    /**
     * Toggle store verification status
     */
    public function toggleVerification(int $storeId): bool {
        $store = $this->find($storeId);
        if (!$store) return false;
        $newStatus = $store['is_verified'] ? 0 : 1;
        return $this->update($storeId, ['is_verified' => $newStatus]);
    }
}

