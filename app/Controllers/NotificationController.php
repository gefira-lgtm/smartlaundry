<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

class NotificationController extends Controller {
    private Notification $notifModel;

    public function __construct() {
        $this->notifModel = new Notification();
    }

    /**
     * JSON Endpoint: Get unread notifications count for navbar badge
     * GET /notifications/unread-count
     */
    public function getUnreadCount(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'unread_count' => 0], 401);
            return;
        }

        $count = $this->notifModel->getUnreadCount((int)$user['id']);
        $response->json([
            'success' => true,
            'unread_count' => $count
        ]);
    }

    /**
     * JSON Endpoint: Get recent notifications list for navbar dropdown
     * GET /notifications/recent
     */
    public function getRecent(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'notifications' => []], 401);
            return;
        }

        $userId = (int)$user['id'];
        $notifications = $this->notifModel->getRecentNotifications($userId, 10);
        $unreadCount = $this->notifModel->getUnreadCount($userId);

        $response->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'notifications' => $notifications
        ]);
    }

    /**
     * Mark single notification as read and redirect to action_url
     * POST /notifications/{id}/mark-read
     */
    public function markRead(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user) {
            $this->redirect('login');
            return;
        }

        $notifId = (int)$id;
        $notif = $this->notifModel->markAsRead($notifId, (int)$user['id']);

        if ($request->isAjax()) {
            $response->json(['success' => true, 'action_url' => $notif['action_url'] ?? null]);
            return;
        }

        if ($notif && !empty($notif['action_url'])) {
            $this->redirect($notif['action_url']);
            return;
        }

        $this->redirect(get_dashboard_url_by_role($user['role'] ?? 'customer'));
    }

    /**
     * Mark all notifications as read
     * POST /notifications/mark-all-read
     */
    public function markAllRead(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $this->redirect('login');
            return;
        }

        $this->notifModel->markAllAsRead((int)$user['id']);

        if ($request->isAjax()) {
            $response->json(['success' => true, 'message' => 'Semua notifikasi telah ditandai dibaca.']);
            return;
        }

        set_flash('success', 'Semua notifikasi telah ditandai sebagai sudah dibaca.');
        $this->redirect(get_dashboard_url_by_role($user['role'] ?? 'customer'));
    }
}
