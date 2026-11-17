<?php
namespace App\Models;

use App\Core\Model;

class OrderStatusHistory extends Model {
    protected string $table = 'order_status_histories';
    protected string $primaryKey = 'id';

    /**
     * Get histories for a specific order with user details
     */
    public function getByOrderId(int $orderId): array {
        $sql = "SELECT osh.*, u.name AS user_name, r.display_name AS user_role_display
                FROM `{$this->table}` osh
                LEFT JOIN `users` u ON osh.changed_by = u.id
                LEFT JOIN `roles` r ON u.role_id = r.id
                WHERE osh.order_id = :order_id
                ORDER BY osh.id ASC";
        return $this->query($sql, ['order_id' => $orderId]);
    }
}
