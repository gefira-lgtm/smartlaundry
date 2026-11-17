<?php
namespace App\Models;

use App\Core\Model;

class Review extends Model {
    protected string $table = 'reviews';
    protected string $primaryKey = 'id';

    /**
     * Create a new review and automatically recalculate store rating
     */
    public function createReview(array $data): int {
        $reviewId = (int)$this->create([
            'order_id' => (int)$data['order_id'],
            'customer_id' => (int)$data['customer_id'],
            'store_id' => (int)$data['store_id'],
            'rating' => max(1, min(5, (int)$data['rating'])),
            'comment' => trim($data['comment'] ?? ''),
            'photo' => $data['photo'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        if ($reviewId > 0) {
            $this->recalculateStoreRating((int)$data['store_id']);
        }

        return $reviewId;
    }

    /**
     * Find review by order ID
     */
    public function findByOrderId(int $orderId): ?array {
        $sql = "SELECT r.*, u.name AS customer_name, u.avatar AS customer_avatar 
                FROM `{$this->table}` r
                JOIN `users` u ON r.customer_id = u.id
                WHERE r.order_id = :order_id 
                LIMIT 1";
        return $this->firstQuery($sql, ['order_id' => $orderId]);
    }

    /**
     * Get reviews for a specific store with customer details
     */
    public function getByStoreId(int $storeId, int $limit = 10): array {
        $sql = "SELECT r.*, u.name AS customer_name, u.avatar AS customer_avatar,
                       o.order_code,
                       (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = r.order_id) AS service_names
                FROM `{$this->table}` r
                JOIN `users` u ON r.customer_id = u.id
                JOIN `orders` o ON r.order_id = o.id
                WHERE r.store_id = :store_id
                ORDER BY r.created_at DESC
                LIMIT {$limit}";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Recalculate average rating and total count for a store
     */
    public function recalculateStoreRating(int $storeId): array {
        $sql = "SELECT COALESCE(AVG(rating), 5.0) AS avg_rating, COUNT(*) AS total_count 
                FROM `{$this->table}` 
                WHERE `store_id` = :store_id";
        $stats = $this->firstQuery($sql, ['store_id' => $storeId]);

        $avg = round((float)($stats['avg_rating'] ?? 5.0), 2);
        $count = (int)($stats['total_count'] ?? 0);

        // Update stores table
        $stmt = $this->db->prepare("UPDATE `stores` SET `rating_avg` = :rating_avg, `rating_count` = :rating_count WHERE `id` = :id");
        $stmt->execute([
            'rating_avg' => $avg,
            'rating_count' => $count,
            'id' => $storeId
        ]);

        return [
            'rating_avg' => $avg,
            'rating_count' => $count
        ];
    }
}
