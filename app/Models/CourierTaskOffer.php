<?php
namespace App\Models;

use App\Core\Model;

class CourierTaskOffer extends Model {
    protected string $table = 'courier_task_offers';
    protected string $primaryKey = 'id';

    /**
     * Create a new task offer for a courier
     */
    public function createOffer(int $orderId, int $courierUserId, string $taskType = 'PICKUP'): ?int {
        $sql = "INSERT INTO `{$this->table}` (`order_id`, `courier_id`, `task_type`, `status`, `offered_at`, `created_at`, `updated_at`)
                VALUES (:order_id, :courier_id, :task_type, 'MENUNGGU_RESPON', NOW(), NOW(), NOW())";
        
        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute([
            'order_id' => $orderId,
            'courier_id' => $courierUserId,
            'task_type' => in_array($taskType, ['PICKUP', 'DELIVERY', 'RETURN'], true) ? $taskType : 'PICKUP'
        ]);

        return $res ? (int)$this->db->lastInsertId() : null;
    }

    /**
     * Get active pending offers for a specific courier
     */
    public function getPendingOffersForCourier(int $courierUserId): array {
        // Expire timed-out offers dynamically based on platform setting
        $this->expireTimedOutOffers();

        $sql = "SELECT cto.*, 
                       o.order_code, o.status AS order_status, o.total_price, o.delivery_fee, o.refund_fee, o.refund_fee_bearer,
                       u.name AS customer_name, u.phone AS customer_phone,
                       pa.full_address AS pickup_address, pa.latitude AS pickup_lat, pa.longitude AS pickup_lng,
                       da.full_address AS delivery_address, da.latitude AS delivery_lat, da.longitude AS delivery_lng,
                       s.name AS store_name, s.phone AS store_phone,
                       ld.weight_actual, ld.pieces_count,
                       (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS service_names
                FROM `{$this->table}` cto
                JOIN `orders` o ON cto.order_id = o.id
                JOIN `users` u ON o.customer_id = u.id
                JOIN `addresses` pa ON o.pickup_address_id = pa.id
                LEFT JOIN `addresses` da ON o.delivery_address_id = da.id
                JOIN `stores` s ON o.store_id = s.id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE cto.courier_id = :courier_id 
                  AND cto.status = 'MENUNGGU_RESPON'
                ORDER BY cto.id DESC";
        return $this->query($sql, ['courier_id' => $courierUserId]);
    }

    /**
     * Get active pending offer for an order
     */
    public function getActiveOfferForOrder(int $orderId): ?array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `order_id` = :order_id AND `status` = 'MENUNGGU_RESPON' 
                ORDER BY `id` DESC LIMIT 1";
        return $this->firstQuery($sql, ['order_id' => $orderId]);
    }

    /**
     * Get array of courier user IDs that have rejected this order
     */
    public function getRejectedCourierIdsForOrder(int $orderId): array {
        $sql = "SELECT DISTINCT `courier_id` FROM `{$this->table}` 
                WHERE `order_id` = :order_id AND `status` = 'DITOLAK'";
        $rows = $this->query($sql, ['order_id' => $orderId]);
        return array_map(fn($r) => (int)$r['courier_id'], $rows);
    }

    /**
     * Mark offer as accepted by courier
     */
    public function markAccepted(int $offerId, int $courierUserId): bool {
        $sql = "UPDATE `{$this->table}` 
                SET `status` = 'DITERIMA', `responded_at` = NOW(), `updated_at` = NOW() 
                WHERE `id` = :id AND `courier_id` = :courier_id AND `status` = 'MENUNGGU_RESPON'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $offerId, 'courier_id' => $courierUserId]) && $stmt->rowCount() > 0;
    }

    /**
     * Mark offer as rejected by courier
     */
    public function markRejected(int $offerId, int $courierUserId, ?string $reason = null): bool {
        $sql = "UPDATE `{$this->table}` 
                SET `status` = 'DITOLAK', `reject_reason` = :reason, `responded_at` = NOW(), `updated_at` = NOW() 
                WHERE `id` = :id AND `courier_id` = :courier_id AND `status` = 'MENUNGGU_RESPON'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'reason' => $reason,
            'id' => $offerId,
            'courier_id' => $courierUserId
        ]) && $stmt->rowCount() > 0;
    }

    /**
     * Expire any stale pending offers for an order
     */
    public function expirePendingOffersForOrder(int $orderId): bool {
        $sql = "UPDATE `{$this->table}` 
                SET `status` = 'KADALUARSA', `updated_at` = NOW() 
                WHERE `order_id` = :order_id AND `status` = 'MENUNGGU_RESPON'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['order_id' => $orderId]);
    }

    /**
     * Expire offers that have exceeded courier_offer_timeout_minutes
     */
    public function expireTimedOutOffers(?int $timeoutMinutes = null): int {
        if ($timeoutMinutes === null) {
            $timeoutMinutes = (int)\App\Models\Setting::get('courier_offer_timeout_minutes', 15);
        }
        $sql = "UPDATE `{$this->table}` 
                SET `status` = 'KADALUARSA', `updated_at` = NOW() 
                WHERE `status` = 'MENUNGGU_RESPON' 
                  AND TIMESTAMPDIFF(MINUTE, `offered_at`, NOW()) >= :timeout";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['timeout' => $timeoutMinutes]);
        return $stmt->rowCount();
    }
}

