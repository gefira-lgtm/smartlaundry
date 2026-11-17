<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;

class PaymentController extends Controller {
    private Order $orderModel;
    private Payment $paymentModel;
    private Store $storeModel;
    private User $userModel;

    public function __construct() {
        $this->orderModel = new Order();
        $this->paymentModel = new Payment();
        $this->storeModel = new Store();
        $this->userModel = new User();
    }

    /**
     * Show interactive QRIS payment page for customer
     */
    public function show(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $customerId = (int)$user['id'];
        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/customer');
            return;
        }

        // Validate customer ownership
        if ((int)$order['customer_id'] !== $customerId) {
            set_flash('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat pembayaran pesanan ini.');
            $this->redirect('dashboard/customer');
            return;
        }

        // Fetch or create payment record
        $payment = $this->paymentModel->findByOrderId((int)$orderId);
        if (!$payment) {
            $payment = $this->paymentModel->createForOrder(
                (int)$orderId,
                (float)$order['total_price'],
                $order['payment_method'],
                60
            );
        }

        // Inline check for expiration
        $payment = $this->paymentModel->checkAndExpire((int)$payment['id']);

        // Refresh order data if status was auto-expired
        $order = $this->orderModel->findWithDetails((int)$orderId);

        // Calculate seconds remaining
        $secondsLeft = 0;
        if (!empty($payment['expired_at']) && $payment['status'] === 'PENDING') {
            $secondsLeft = max(0, strtotime($payment['expired_at']) - time());
        }

        $this->render('order/payment', [
            'title' => 'Pembayaran QRIS #' . $order['order_code'] . ' - ' . APP_NAME,
            'user' => $user,
            'order' => $order,
            'payment' => $payment,
            'secondsLeft' => $secondsLeft
        ], 'layouts/main');
    }

    /**
     * Real-time polling endpoint for payment status (JSON)
     */
    public function status(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order) {
            $response->json(['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'], 404);
            return;
        }

        // Scoping check (customer owner or store staff or platform admin)
        $userId = (int)$user['id'];
        $userRole = $user['role'];
        $isCustomerOwner = ((int)$order['customer_id'] === $userId);
        $isPlatformAdmin = in_array($userRole, ['owner_platform', 'admin_owner'], true);
        
        $isStoreStaff = false;
        if (in_array($userRole, ['owner_toko', 'admin_toko'], true)) {
            $store = $this->storeModel->findForUser($userId, $userRole);
            if ($store && (int)$store['id'] === (int)$order['store_id']) {
                $isStoreStaff = true;
            }
        }

        if (!$isCustomerOwner && !$isPlatformAdmin && !$isStoreStaff) {
            $response->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
            return;
        }

        $payment = $this->paymentModel->findByOrderId((int)$orderId);
        if ($payment) {
            $payment = $this->paymentModel->checkAndExpire((int)$payment['id']);
        }

        // Re-read order status in case it was auto-expired
        $orderUpdated = $this->orderModel->find((int)$orderId);

        $secondsLeft = 0;
        if ($payment && !empty($payment['expired_at']) && $payment['status'] === 'PENDING') {
            $secondsLeft = max(0, strtotime($payment['expired_at']) - time());
        }

        $response->json([
            'status' => 'success',
            'order_id' => (int)$orderId,
            'order_status' => $orderUpdated['status'],
            'payment_status' => $payment['status'] ?? 'PENDING',
            'is_paid' => ($payment['status'] ?? '') === 'PAID',
            'is_expired' => ($payment['status'] ?? '') === 'EXPIRED',
            'seconds_left' => $secondsLeft,
            'amount' => (float)($payment['amount'] ?? $orderUpdated['total_price']),
            'paid_at' => $payment['paid_at'] ?? null,
            'transaction_ref' => $payment['transaction_ref'] ?? null
        ]);
    }

    /**
     * Regenerate fresh QRIS after expiration (Customer Action)
     */
    public function regenerate(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('order/' . $orderId . '/payment');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order || (int)$order['customer_id'] !== (int)$user['id']) {
            set_flash('error', 'Pesanan tidak ditemukan atau akses ditolak.');
            $this->redirect('dashboard/customer');
            return;
        }

        $this->paymentModel->regenerateQris((int)$orderId, 60);

        set_flash('success', 'Kode QRIS baru berhasil diterbitkan dengan batas waktu 1 jam ke depan.');
        $this->redirect('order/' . $orderId . '/payment');
    }

    /**
     * Simulate Payment Webhook / Confirmation (Development & Store Admin Action)
     */
    public function simulatePaid(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            if ($request->isAjax()) {
                $response->json(['status' => 'error', 'message' => 'Token CSRF tidak valid.'], 400);
                return;
            }
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('order/' . $orderId . '/payment');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order) {
            if ($request->isAjax()) {
                $response->json(['status' => 'error', 'message' => 'Pesanan tidak ditemukan.'], 404);
                return;
            }
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/customer');
            return;
        }

        // Authorization check: Customer owner, store staff, or dev environment
        $userId = (int)$user['id'];
        $userRole = $user['role'];
        $isCustomerOwner = ((int)$order['customer_id'] === $userId);
        $isPlatformAdmin = in_array($userRole, ['owner_platform', 'admin_owner'], true);
        
        $isStoreStaff = false;
        if (in_array($userRole, ['owner_toko', 'admin_toko'], true)) {
            $store = $this->storeModel->findForUser($userId, $userRole);
            if ($store && (int)$store['id'] === (int)$order['store_id']) {
                $isStoreStaff = true;
            }
        }

        $isDev = env('APP_ENV') === 'local' || env('APP_ENV') === 'development';

        if (!$isCustomerOwner && !$isStoreStaff && !$isPlatformAdmin && !$isDev) {
            if ($request->isAjax()) {
                $response->json(['status' => 'error', 'message' => 'Akses ditolak.'], 403);
                return;
            }
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard/customer');
            return;
        }

        $method = $order['payment_method'] ?? 'ONLINE_QRIS';
        $txnRef = 'SIM_QRIS_' . date('YmdHis') . '_' . mt_rand(1000, 9999);

        // Mark payment as PAID
        $this->paymentModel->markAsPaid((int)$orderId, $method, $txnRef);

        // Advance order status to SEDANG_DICUCI
        $this->orderModel->updateStatus(
            (int)$orderId,
            'SEDANG_DICUCI',
            $userId,
            "Pembayaran {$method} (#{$txnRef}) berhasil diverifikasi LUNAS. Cucian masuk proses pencucian."
        );

        // Trigger in-app notification to customer
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Pembayaran Berhasil Diverifikasi! 🎉',
            "Pembayaran pesanan #{$order['order_code']} via {$method} berhasil dikonfirmasi LUNAS. Cucian Anda sedang diproses.",
            'payment',
            url('order/' . $orderId . '/payment'),
            (int)$orderId
        );

        // Trigger WhatsApp Notification
        $customerUser = $this->userModel->find((int)$order['customer_id']);
        $waService = new \App\Services\WhatsAppNotificationService();
        if ($customerUser) {
            $waService->notifyPaymentSuccess(
                $customerUser['phone'] ?? '081234567890',
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                (float)($payment['amount'] ?? $order['total_price']),
                $txnRef,
                $order['store_name'] ?? 'SmartLaundry',
                (int)$orderId,
                (int)$order['customer_id'],
                $method
            );
        }

        // Notify store staff about payment received
        $waService->notifyStorePaymentReceived(
            (int)$order['store_id'],
            $order['order_code'],
            (float)($payment['amount'] ?? $order['total_price']),
            (int)$orderId,
            $method
        );

        if ($request->isAjax()) {
            $response->json([
                'status' => 'success',
                'message' => 'Pembayaran berhasil dikonfirmasi LUNAS.',
                'transaction_ref' => $txnRef,
                'redirect_url' => url('order/' . $orderId . '/payment')
            ]);
            return;
        }

        set_flash('success', "Pembayaran pesanan #{$order['order_code']} berhasil dikonfirmasi LUNAS via QRIS ({$txnRef}). Cucian masuk tahap pencucian.");
        
        if ($isStoreStaff) {
            $this->redirect('dashboard/store');
        } else {
            $this->redirect('order/' . $orderId . '/payment');
        }
    }

    /**
     * Show Printable Digital Invoice
     */
    public function invoice(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/customer');
            return;
        }

        // Authorization check (Customer owner, Store Staff, or Platform Admin)
        $userId = (int)$user['id'];
        $userRole = $user['role'];
        $isCustomerOwner = ((int)$order['customer_id'] === $userId);
        $isPlatformAdmin = in_array($userRole, ['owner_platform', 'admin_owner'], true);
        
        $isStoreStaff = false;
        if (in_array($userRole, ['owner_toko', 'admin_toko'], true)) {
            $store = $this->storeModel->findForUser($userId, $userRole);
            if ($store && (int)$store['id'] === (int)$order['store_id']) {
                $isStoreStaff = true;
            }
        }

        if (!$isCustomerOwner && !$isStoreStaff && !$isPlatformAdmin) {
            set_flash('error', 'Akses ditolak: Anda tidak memiliki izin untuk melihat invoice pesanan ini.');
            $this->redirect('dashboard');
            return;
        }

        $payment = $this->paymentModel->findByOrderId((int)$orderId);

        $this->render('order/invoice', [
            'title' => 'Invoice #' . $order['order_code'] . ' - ' . APP_NAME,
            'user' => $user,
            'order' => $order,
            'payment' => $payment
        ], 'layouts/main');
    }
}
