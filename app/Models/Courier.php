<?php
namespace App\Models;

use App\Core\Model;

class Courier extends Model {
    protected string $table = 'couriers';
    protected string $primaryKey = 'id';

    /**
     * Find courier record by user_id including user and store metadata
     */
    public function findByUserId(int $userId): ?array {
        $sql = "SELECT c.*, u.name AS courier_name, u.email AS courier_email, u.phone AS courier_phone, u.avatar,
                       s.name AS store_name, s.phone AS store_phone
                FROM `{$this->table}` c
                JOIN `users` u ON c.user_id = u.id
                LEFT JOIN `stores` s ON c.store_id = s.id
                WHERE c.user_id = :user_id
                LIMIT 1";
        return $this->firstQuery($sql, ['user_id' => $userId]);
    }

    /**
     * Toggle availability status
     */
    public function toggleAvailability(int $courierId): bool {
        $courier = $this->find($courierId);
        if (!$courier) return false;

        $newAvailable = $courier['is_available'] ? 0 : 1;
        $newStatus = $newAvailable ? 'TERSEDIA' : 'OFFLINE';

        return $this->update($courierId, [
            'is_available' => $newAvailable,
            'status' => $newStatus
        ]);
    }

    /**
     * Check if courier currently has any active ongoing task on the road
     */
    public function hasActiveTask(int $courierUserId): bool {
        $sql = "SELECT COUNT(*) AS active_count FROM `orders` 
                WHERE COALESCE(refund_assigned_courier_id, courier_id) = :uid
                  AND status IN ('KURIR_MENUJU_LOKASI', 'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO', 'SIAP_DIANTAR', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR', 'PROSES_PENGEMBALIAN')";
        $row = $this->firstQuery($sql, ['uid' => $courierUserId]);
        return ((int)($row['active_count'] ?? 0)) > 0;
    }

    /**
     * Find best candidate available courier for store (online, not permanently inactive, no conflicting active tasks, not in excluded list)
     */
    public function findAvailableCourierForStore(int $storeId, array $excludeCourierUserIds = []): ?array {
        $sql = "SELECT c.*, u.name AS courier_name, u.phone AS courier_phone, u.email AS courier_email
                FROM `{$this->table}` c
                JOIN `users` u ON c.user_id = u.id
                WHERE c.store_id = :store_id 
                  AND c.is_available = 1 
                  AND c.is_permanently_inactive = 0";
        
        $couriers = $this->query($sql, ['store_id' => $storeId]);

        foreach ($couriers as $courier) {
            $userId = (int)$courier['user_id'];
            // Skip if courier already rejected this order
            if (in_array($userId, $excludeCourierUserIds, true)) {
                continue;
            }

            // Skip if courier is already on active duty (anti-collision constraint)
            if ($this->hasActiveTask($userId)) {
                continue;
            }

            return $courier;
        }

        return null;
    }


    /**
     * Get tasks for this courier grouped by task lifecycle
     */
    public function getCourierTasks(int $courierUserId): array {
        // Pickup tasks (Menunggu Kurir / Kurir Menuju Lokasi)
        $sqlPickup = "SELECT o.*, 
                             u.name AS customer_name, u.phone AS customer_phone,
                             pa.full_address AS pickup_address, pa.latitude AS pickup_lat, pa.longitude AS pickup_lng,
                             s.name AS store_name, s.phone AS store_phone,
                             (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                              FROM `order_items` oi 
                              JOIN `laundry_services` ls ON oi.service_id = ls.id 
                              WHERE oi.order_id = o.id) AS service_names
                      FROM `orders` o
                      JOIN `users` u ON o.customer_id = u.id
                      JOIN `addresses` pa ON o.pickup_address_id = pa.id
                      JOIN `stores` s ON o.store_id = s.id
                      WHERE o.courier_id = :courier_id 
                        AND o.status IN ('MENUNGGU_KURIR', 'KURIR_MENUJU_LOKASI', 'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO')
                      ORDER BY o.id ASC";
        $pickupTasks = $this->query($sqlPickup, ['courier_id' => $courierUserId]);

        // Delivery tasks (Siap Diantar / Kurir Menuju Customer)
        $sqlDelivery = "SELECT o.*, 
                               u.name AS customer_name, u.phone AS customer_phone,
                               COALESCE(da.full_address, pa.full_address) AS delivery_address,
                               COALESCE(da.latitude, pa.latitude) AS delivery_lat,
                               COALESCE(da.longitude, pa.longitude) AS delivery_lng,
                               s.name AS store_name,
                               p.status AS payment_status, p.amount AS payment_amount,
                               ld.weight_actual, ld.pieces_count,
                               (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                                FROM `order_items` oi 
                                JOIN `laundry_services` ls ON oi.service_id = ls.id 
                                WHERE oi.order_id = o.id) AS service_names
                        FROM `orders` o
                        JOIN `users` u ON o.customer_id = u.id
                        JOIN `addresses` pa ON o.pickup_address_id = pa.id
                        LEFT JOIN `addresses` da ON o.delivery_address_id = da.id
                        JOIN `stores` s ON o.store_id = s.id
                        LEFT JOIN `payments` p ON o.id = p.order_id
                        LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                        WHERE o.courier_id = :courier_id 
                          AND o.status IN ('SIAP_DIANTAR', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR')
                        ORDER BY o.id ASC";
        $deliveryTasks = $this->query($sqlDelivery, ['courier_id' => $courierUserId]);

        // Refund & Return tasks (Proses Pengembalian Laundry)
        $sqlRefund = "SELECT o.*, 
                             u.name AS customer_name, u.phone AS customer_phone,
                             pa.full_address AS pickup_address, pa.latitude AS pickup_lat, pa.longitude AS pickup_lng,
                             COALESCE(da.full_address, pa.full_address) AS delivery_address,
                             COALESCE(da.latitude, pa.latitude) AS delivery_lat,
                             COALESCE(da.longitude, pa.longitude) AS delivery_lng,
                             s.name AS store_name, s.phone AS store_phone,
                             ld.weight_actual, ld.pieces_count,
                             (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                              FROM `order_items` oi 
                              JOIN `laundry_services` ls ON oi.service_id = ls.id 
                              WHERE oi.order_id = o.id) AS service_names
                      FROM `orders` o
                      JOIN `users` u ON o.customer_id = u.id
                      JOIN `addresses` pa ON o.pickup_address_id = pa.id
                      LEFT JOIN `addresses` da ON o.delivery_address_id = da.id
                      JOIN `stores` s ON o.store_id = s.id
                      LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                      WHERE COALESCE(o.refund_assigned_courier_id, o.courier_id) = :courier_id
                        AND o.status = 'PROSES_PENGEMBALIAN'
                      ORDER BY o.id ASC";
        $refundTasks = $this->query($sqlRefund, ['courier_id' => $courierUserId]);

        // Completed tasks today (including completed successful orders and finished returns/cancellations)
        $today = date('Y-m-d');
        $sqlCompleted = "SELECT o.*, 
                                u.name AS customer_name,
                                s.name AS store_name,
                                p.amount AS payment_amount
                         FROM `orders` o
                         JOIN `users` u ON o.customer_id = u.id
                         JOIN `stores` s ON o.store_id = s.id
                         LEFT JOIN `payments` p ON o.id = p.order_id
                         WHERE COALESCE(o.refund_assigned_courier_id, o.courier_id) = :courier_id
                           AND o.status IN ('SELESAI', 'DIBATALKAN')
                           AND DATE(o.completed_at) = :today
                         ORDER BY o.completed_at DESC";
        $completedToday = $this->query($sqlCompleted, ['courier_id' => $courierUserId, 'today' => $today]);

        return [
            'pickup' => $pickupTasks,
            'delivery' => $deliveryTasks,
            'refund' => $refundTasks,
            'completed_today' => $completedToday
        ];
    }

    /**
     * Get active couriers with their latest GPS locations for Store Dashboard Mini-Map
     */
    public function getActiveCouriersWithLocations(int $storeId): array {
        $sql = "SELECT c.id AS courier_id, c.user_id, c.vehicle_type, c.license_plate, 
                       c.is_available, c.status AS courier_status, 
                       COALESCE(c.current_lat, -6.20000000) AS current_lat, 
                       COALESCE(c.current_lng, 106.81666600) AS current_lng, 
                       c.last_location_updated_at,
                       u.name AS courier_name, u.phone AS courier_phone, u.avatar,
                       (SELECT o.id FROM `orders` o 
                        WHERE o.courier_id = c.user_id 
                          AND o.status IN ('KURIR_MENUJU_LOKASI', 'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR') 
                        ORDER BY o.id ASC LIMIT 1) AS active_order_id,
                       (SELECT o.status FROM `orders` o 
                        WHERE o.courier_id = c.user_id 
                          AND o.status IN ('KURIR_MENUJU_LOKASI', 'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR') 
                        ORDER BY o.id ASC LIMIT 1) AS active_order_status
                FROM `{$this->table}` c
                JOIN `users` u ON c.user_id = u.id
                WHERE c.store_id = :store_id 
                  AND c.is_permanently_inactive = 0
                ORDER BY c.is_available DESC, u.name ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Get all couriers belonging to a specific store
     */
    public function getAllByStore(int $storeId): array {
        $sql = "SELECT c.*, u.name AS courier_name, u.email AS courier_email, u.phone AS courier_phone, u.avatar,
                       (SELECT COUNT(*) FROM `orders` o 
                        WHERE o.courier_id = c.user_id 
                          AND o.status IN ('MENUNGGU_KURIR', 'KURIR_MENUJU_LOKASI', 'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO', 'SIAP_DIANTAR', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR')) AS active_tasks_count
                FROM `{$this->table}` c

                JOIN `users` u ON c.user_id = u.id
                WHERE c.store_id = :store_id
                ORDER BY c.is_permanently_inactive ASC, c.is_available DESC, u.name ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Find courier by ID strictly ensuring store ownership
     */
    public function findByIdAndStore(int $courierId, int $storeId): ?array {
        $sql = "SELECT c.*, u.name AS courier_name, u.email AS courier_email, u.phone AS courier_phone, u.avatar
                FROM `{$this->table}` c
                JOIN `users` u ON c.user_id = u.id
                WHERE c.id = :id AND c.store_id = :store_id
                LIMIT 1";
        return $this->firstQuery($sql, ['id' => $courierId, 'store_id' => $storeId]);
    }

    /**
     * Create new courier and user account atomically
     */
    public function createCourierWithUser(int $storeId, array $userData, array $courierData): int {
        $this->beginTransaction();
        try {
            // 1. Get kurir role ID
            $roleStmt = $this->db->prepare("SELECT id FROM `roles` WHERE `name` = 'kurir' LIMIT 1");
            $roleStmt->execute();
            $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);
            $roleId = $role ? (int)$role['id'] : 5;

            // 2. Insert into users
            $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
            $sqlUser = "INSERT INTO `users` (`role_id`, `name`, `email`, `password`, `phone`, `avatar`, `is_active`, `created_at`, `updated_at`)
                        VALUES (:role_id, :name, :email, :password, :phone, :avatar, 1, NOW(), NOW())";
            $stmtUser = $this->db->prepare($sqlUser);
            $stmtUser->execute([
                'role_id' => $roleId,
                'name' => trim($userData['name']),
                'email' => strtolower(trim($userData['email'])),
                'password' => $hashedPassword,
                'phone' => trim($userData['phone']),
                'avatar' => $userData['avatar'] ?? 'avatar_default.png'
            ]);
            $userId = (int)$this->db->lastInsertId();

            // 3. Insert into couriers
            $sqlCourier = "INSERT INTO `couriers` (`user_id`, `store_id`, `vehicle_type`, `license_plate`, `is_available`, `status`, `is_permanently_inactive`, `created_at`, `updated_at`)
                           VALUES (:user_id, :store_id, :vehicle_type, :license_plate, 1, 'TERSEDIA', 0, NOW(), NOW())";
            $stmtCourier = $this->db->prepare($sqlCourier);
            $stmtCourier->execute([
                'user_id' => $userId,
                'store_id' => $storeId,
                'vehicle_type' => trim($courierData['vehicle_type']),
                'license_plate' => strtoupper(trim($courierData['license_plate']))
            ]);
            $courierId = (int)$this->db->lastInsertId();

            $this->commit();
            return $courierId;
        } catch (\Throwable $th) {
            $this->rollBack();
            throw $th;
        }
    }

    /**
     * Update courier vehicle data
     */
    public function updateVehicleInfo(int $courierId, int $storeId, array $data): bool {
        $courier = $this->findByIdAndStore($courierId, $storeId);
        if (!$courier) return false;

        return (bool)$this->update($courierId, [
            'vehicle_type' => trim($data['vehicle_type']),
            'license_plate' => strtoupper(trim($data['license_plate']))
        ]);
    }

    /**
     * Soft deactivation of courier
     */
    public function deactivatePermanently(int $courierId, int $storeId): bool {
        $courier = $this->findByIdAndStore($courierId, $storeId);
        if (!$courier) return false;

        return (bool)$this->update($courierId, [
            'is_permanently_inactive' => 1,
            'is_available' => 0,
            'status' => 'OFFLINE'
        ]);
    }

    /**
     * Reactivate courier
     */
    public function reactivate(int $courierId, int $storeId): bool {
        $courier = $this->findByIdAndStore($courierId, $storeId);
        if (!$courier) return false;

        return (bool)$this->update($courierId, [
            'is_permanently_inactive' => 0,
            'is_available' => 1,
            'status' => 'TERSEDIA'
        ]);
    }

    /**
     * Get latest recorded GPS location for an order
     */
    public function getLatestOrderLocation(int $orderId): ?array {
        $sql = "SELECT * FROM `courier_locations` 
                WHERE `order_id` = :order_id 
                ORDER BY `id` DESC 
                LIMIT 1";
        return $this->firstQuery($sql, ['order_id' => $orderId]);
    }

    /**
     * Update courier current GPS location and record to courier_locations history
     */
    public function updateLocation(int $courierId, float $lat, float $lng, ?int $orderId = null): bool {
        $stmt1 = $this->db->prepare("UPDATE `{$this->table}` 
                                     SET `current_lat` = :lat, `current_lng` = :lng, `last_location_updated_at` = NOW(), `updated_at` = NOW() 
                                     WHERE `id` = :id");
        $res1 = $stmt1->execute(['lat' => $lat, 'lng' => $lng, 'id' => $courierId]);

        $stmt2 = $this->db->prepare("INSERT INTO `courier_locations` (`courier_id`, `order_id`, `latitude`, `longitude`, `recorded_at`) 
                                     VALUES (:courier_id, :order_id, :lat, :lng, NOW())");
        $res2 = $stmt2->execute([
            'courier_id' => $courierId,
            'order_id' => $orderId,
            'lat' => $lat,
            'lng' => $lng
        ]);

        return $res1 && $res2;
    }
}

