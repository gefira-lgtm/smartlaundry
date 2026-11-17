<?php
namespace App\Models;

use App\Core\Model;

class Notification extends Model {
    protected string $table = 'notifications';
    protected string $primaryKey = 'id';

    /**
     * Create a new notification for a specific user
     */
    public function createNotification(
        int $userId, 
        string $title, 
        string $message, 
        string $type = 'order', 
        ?string $actionUrl = null, 
        ?int $orderId = null
    ): int {
        if (!\App\Models\Setting::get('in_app_notification_enabled', true)) {
            return 0; // In-app notification globally disabled
        }
        return (int)$this->create([
            'user_id' => $userId,
            'order_id' => $orderId,
            'title' => trim($title),
            'message' => trim($message),
            'type' => $type,
            'action_url' => $actionUrl,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get count of unread notifications for a user
     */
    public function getUnreadCount(int $userId): int {
        $sql = "SELECT COUNT(*) AS total FROM `{$this->table}` 
                WHERE `user_id` = :user_id AND `is_read` = 0";
        $res = $this->firstQuery($sql, ['user_id' => $userId]);
        return (int)($res['total'] ?? 0);
    }

    /**
     * Get recent notifications for a user
     */
    public function getRecentNotifications(int $userId, int $limit = 10): array {
        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `user_id` = :user_id 
                ORDER BY `created_at` DESC 
                LIMIT {$limit}";
        $items = $this->query($sql, ['user_id' => $userId]);

        foreach ($items as &$item) {
            $item['time_ago'] = $this->timeAgo($item['created_at']);
        }

        return $items;
    }

    /**
     * Mark single notification as read strictly validating user ownership
     */
    public function markAsRead(int $notificationId, int $userId): ?array {
        $notif = $this->find($notificationId);
        if (!$notif || (int)$notif['user_id'] !== $userId) {
            return null;
        }

        $this->update($notificationId, ['is_read' => 1]);
        return $notif;
    }

    /**
     * Mark all notifications for a user as read
     */
    public function markAllAsRead(int $userId): bool {
        $stmt = $this->db->prepare("UPDATE `{$this->table}` SET `is_read` = 1 WHERE `user_id` = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Calculate human friendly Indonesian relative time
     */
    public static function timeAgo(string $datetime): string {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Baru saja';
        }
        if ($diff < 3600) {
            $mins = max(1, floor($diff / 60));
            return "{$mins} menit lalu";
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "{$hours} jam lalu";
        }
        if ($diff < 172800) {
            return 'Kemarin ' . date('H:i', $timestamp);
        }
        return date('d M Y, H:i', $timestamp);
    }
}
