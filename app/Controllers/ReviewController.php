<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use App\Models\Notification;

class ReviewController extends Controller {
    private Order $orderModel;
    private Review $reviewModel;
    private Store $storeModel;
    private Notification $notifModel;

    public function __construct() {
        $this->orderModel = new Order();
        $this->reviewModel = new Review();
        $this->storeModel = new Store();
        $this->notifModel = new Notification();
    }

    /**
     * Show review submission form for a completed order
     */
    public function create(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order || (int)$order['customer_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak: Pesanan tidak ditemukan atau bukan milik Anda.');
            $this->redirect('dashboard/customer');
            return;
        }

        if ($order['status'] !== 'SELESAI') {
            set_flash('error', 'Anda hanya dapat memberikan ulasan pada pesanan yang telah SELESAI.');
            $this->redirect('dashboard/customer');
            return;
        }

        $existingReview = $this->reviewModel->findByOrderId((int)$orderId);
        if ($existingReview) {
            set_flash('warning', 'Anda sudah pernah memberikan ulasan untuk pesanan #' . $order['order_code'] . '.');
            $this->redirect('store/' . $order['store_id'] . '/detail');
            return;
        }

        $this->render('order/review', [
            'title' => 'Beri Ulasan & Rating - ' . APP_NAME,
            'user' => $user,
            'order' => $order
        ], 'layouts/main');
    }

    /**
     * Store review and recalculate store rating
     */
    public function store(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid. Silakan coba lagi.');
            $this->redirect('order/' . $orderId . '/review');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order || (int)$order['customer_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard/customer');
            return;
        }

        if ($order['status'] !== 'SELESAI') {
            set_flash('error', 'Pesanan belum selesai.');
            $this->redirect('dashboard/customer');
            return;
        }

        $existingReview = $this->reviewModel->findByOrderId((int)$orderId);
        if ($existingReview) {
            set_flash('warning', 'Pesanan ini sudah pernah diulas.');
            $this->redirect('store/' . $order['store_id'] . '/detail');
            return;
        }

        $rating = (int)$request->get('rating', 5);
        if ($rating < 1 || $rating > 5) {
            set_flash('error', 'Rating bintang harus antara 1 sampai 5.');
            $this->redirect('order/' . $orderId . '/review');
            return;
        }

        $comment = trim($request->get('comment', ''));
        if (empty($comment)) {
            set_flash('error', 'Silakan tuliskan komentar ulasan Anda.');
            $this->redirect('order/' . $orderId . '/review');
            return;
        }

        // Handle review photo upload
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

            if ($file['size'] <= $maxFileSize) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (in_array($mime, $allowedMimes)) {
                    $uploadDir = ROOT_PATH . '/public/uploads/reviews/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $fileName = 'rev_' . $orderId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                        $photoPath = 'uploads/reviews/' . $fileName;
                    }
                }
            }
        }

        $reviewId = $this->reviewModel->createReview([
            'order_id' => (int)$orderId,
            'customer_id' => (int)$user['id'],
            'store_id' => (int)$order['store_id'],
            'rating' => $rating,
            'comment' => $comment,
            'photo' => $photoPath
        ]);

        // Notify Store Owner about new review
        $store = $this->storeModel->find((int)$order['store_id']);
        if ($store && !empty($store['owner_id'])) {
            $this->notifModel->createNotification(
                (int)$store['owner_id'],
                'Ulasan Pelanggan Baru ⭐ ' . $rating . '/5',
                "Pelanggan {$user['name']} memberikan ulasan {$rating} bintang untuk pesanan #{$order['order_code']}.",
                'order',
                'store/' . $order['store_id'] . '/detail',
                (int)$orderId
            );
        }

        // WhatsApp notification to store owner about new review
        $waService = new \App\Services\WhatsAppNotificationService();
        $waService->notifyStoreNewReview(
            (int)$order['store_id'],
            $order['order_code'],
            $rating,
            $comment,
            (int)$orderId
        );

        set_flash('success', 'Terima kasih! Ulasan & rating bintang Anda untuk ' . e($order['store_name']) . ' berhasil disimpan.');
        $this->redirect('store/' . $order['store_id'] . '/detail');
    }
}
