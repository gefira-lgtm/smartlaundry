<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Courier;
use App\Models\Address;
use App\Services\MetricsService;

class DashboardController extends Controller {
    private Store $storeModel;
    private Order $orderModel;
    private Payment $paymentModel;
    private User $userModel;
    private Courier $courierModel;
    private Address $addressModel;

    public function __construct() {
        $this->storeModel = new Store();
        $this->orderModel = new Order();
        $this->paymentModel = new Payment();
        $this->userModel = new User();
        $this->courierModel = new Courier();
        $this->addressModel = new Address();
    }

    /**
     * Dashboard for Owner Platform & Admin Owner
     */
    public function platform(Request $request, Response $response): void {
        $user = auth_user();
        
        // 1. Summary Cards Data
        $totalGMV = $this->paymentModel->getTotalGMV();
        $totalOrders = $this->orderModel->count();
        $totalStores = $this->storeModel->count(['is_verified' => 1]);
        $allStoresCount = $this->storeModel->count();
        
        // Customer count (role_id = 6)
        $roleModel = new Role();
        $customerRole = $roleModel->findByName('customer');
        $customerRoleId = $customerRole ? (int)$customerRole['id'] : 6;
        $totalCustomers = $this->userModel->count(['role_id' => $customerRoleId]);

        // 2. Chart Trends Data (7 Days)
        $trends = $this->paymentModel->getTransactionTrends(7);

        // 3. Store Monitoring Table
        $stores = $this->storeModel->getAllWithStats();

        // 4. Recent Platform Orders (10 latest)
        $recentOrders = $this->orderModel->getRecentOrders(10);

        $this->render('dashboard/platform', [
            'title' => 'Dashboard Platform - ' . APP_NAME,
            'user' => $user,
            'isSuperAdmin' => ($user['role'] === 'owner_platform'),
            'isAdminOwner' => ($user['role'] === 'admin_owner'),
            'totalGMV' => $totalGMV,
            'totalOrders' => $totalOrders,
            'totalStores' => $totalStores,
            'allStoresCount' => $allStoresCount,
            'totalCustomers' => $totalCustomers,
            'trends' => $trends,
            'stores' => $stores,
            'recentOrders' => $recentOrders
        ], 'layouts/main');
    }

    /**
     * Dashboard for Store Owner & Store Admin
     */
    public function store(Request $request, Response $response): void {
        $user = auth_user();
        $userRole = $user['role'];

        // Find store assigned to this user
        $store = $this->storeModel->findForUser((int)$user['id'], $userRole);

        if (!$store) {
            set_flash('error', 'Toko Anda belum terdaftar dalam sistem. Hubungi administrator.');
            $this->render('dashboard/store_empty', [
                'title' => 'Toko Tidak Ditemukan - ' . APP_NAME,
                'user' => $user
            ], 'layouts/main');
            return;
        }

        $storeId = (int)$store['id'];
        $statusFilter = $request->get('status', 'all');

        // Summary Stats
        $stats = $this->storeModel->getStoreSummary($storeId);

        // Incoming pending orders (Menunggu Konfirmasi)
        $pendingOrders = $this->orderModel->getStorePendingOrders($storeId);

        // Active / filtered orders list
        $storeOrders = $this->orderModel->getStoreOrders($storeId, $statusFilter, 25);

        // Top services performance (Only relevant for store owner)
        $topServices = ($userRole === 'owner_toko') ? $this->storeModel->getTopServices($storeId, 5) : [];

        // Operational queues (Special focus for Admin Toko / Operator)
        $weighingQueue = $this->orderModel->getStoreWeighingQueue($storeId);
        $paymentQueue = $this->orderModel->getStorePaymentQueue($storeId);
        $washingQueue = $this->orderModel->getStoreWashingQueue($storeId);
        $expiredQueue = $this->orderModel->getStoreExpiredPaymentsQueue($storeId);
        $refundsQueue = $this->orderModel->getStoreRefundsQueue($storeId);
        $storeCouriers = $this->courierModel->getAllByStore($storeId);

        $this->render('dashboard/store', [
            'title' => 'Dashboard Toko: ' . $store['name'] . ' - ' . APP_NAME,
            'user' => $user,
            'isOwner' => ($userRole === 'owner_toko'),
            'isAdmin' => ($userRole === 'admin_toko'),
            'store' => $store,
            'stats' => $stats,
            'pendingOrders' => $pendingOrders,
            'storeOrders' => $storeOrders,
            'statusFilter' => $statusFilter,
            'topServices' => $topServices,
            'weighingQueue' => $weighingQueue,
            'paymentQueue' => $paymentQueue,
            'washingQueue' => $washingQueue,
            'expiredQueue' => $expiredQueue,
            'refundsQueue' => $refundsQueue,
            'storeCouriers' => $storeCouriers
        ], 'layouts/main');
    }

    /**
     * Dashboard for Courier
     */
    public function courier(Request $request, Response $response): void {
        $user = auth_user();
        $courier = $this->courierModel->findByUserId((int)$user['id']);

        if (!$courier) {
            set_flash('error', 'Profil kurir Anda tidak ditemukan.');
            $this->redirect('login');
            return;
        }

        $tasks = $this->courierModel->getCourierTasks((int)$user['id']);
        $offerModel = new \App\Models\CourierTaskOffer();
        $pendingOffers = $offerModel->getPendingOffersForCourier((int)$user['id']);

        $this->render('dashboard/courier', [
            'title' => 'Dashboard Kurir - ' . APP_NAME,
            'user' => $user,
            'courier' => $courier,
            'pendingOffers' => $pendingOffers,
            'pickupTasks' => $tasks['pickup'],
            'deliveryTasks' => $tasks['delivery'],
            'refundTasks' => $tasks['refund'] ?? [],
            'completedToday' => $tasks['completed_today']
        ], 'layouts/main');
    }

    /**
     * Dashboard for Customer
     */
    public function customer(Request $request, Response $response): void {
        $user = auth_user();
        $customerId = (int)$user['id'];

        $activeOrders = $this->orderModel->getCustomerActiveOrders($customerId);
        $completedOrders = $this->orderModel->getCustomerCompletedOrders($customerId, 5);
        $completeness = $this->userModel->isProfileComplete($customerId);
        $addresses = $this->addressModel->findByUser($customerId);

        $this->render('dashboard/customer', [
            'title' => 'Dashboard Pelanggan - ' . APP_NAME,
            'user' => $user,
            'activeOrders' => $activeOrders,
            'completedOrders' => $completedOrders,
            'completeness' => $completeness,
            'addresses' => $addresses
        ], 'layouts/main');
    }

    // =========================================================================
    // STORE ACTIONS (WITH STRICT OWNERSHIP & ROLE AUTHORIZATION)
    // =========================================================================

    /**
     * Toggle Store Verification (Admin Owner & Super Owner)
     */
    public function toggleStoreVerify(Request $request, Response $response, string $storeId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/platform');
            return;
        }

        $user = auth_user();
        if ($user['role'] !== 'admin_owner' && $user['role'] !== 'owner_platform') {
            set_flash('error', 'Hanya Admin Owner / Owner Platform yang dapat memverifikasi toko.');
            $this->redirect('dashboard/platform');
            return;
        }

        $this->storeModel->toggleVerification((int)$storeId);

        // Notify platform admins when a store is newly verified
        $storeData = $this->storeModel->find((int)$storeId);
        if ($storeData && !empty($storeData['is_verified'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyPlatformNewStoreRegistered(
                $storeData['name'] ?? 'Toko Baru',
                $storeData['owner_name'] ?? 'Owner',
                $storeData['phone'] ?? '',
                (int)$storeId
            );
        }

        set_flash('success', 'Status verifikasi toko berhasil diperbarui.');
        $this->redirect('dashboard/platform');
    }

    /**
     * Accept incoming order
     */
    public function acceptOrder(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/store');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        // Validate Store Ownership
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store || (int)$store['id'] !== (int)$order['store_id']) {
            set_flash('error', 'Akses ditolak: Anda tidak memiliki wewenang atas pesanan toko ini.');
            $this->redirect('dashboard/store');
            return;
        }

        $this->orderModel->updateStatus((int)$orderId, 'MENUNGGU_KURIR', (int)$user['id'], 'Pesanan diterima oleh toko. Menunggu konfirmasi penerimaan kurir.');

        // Trigger task offer dispatch to available candidate courier (Tahap 12)
        $this->orderModel->dispatchTaskOffer((int)$orderId, 'PICKUP');

        // In-app notification to customer
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Pesanan Dikonfirmasi Toko 👍',
            "Pesanan #{$order['order_code']} telah dikonfirmasi oleh {$store['name']}. Kurir sedang dihubungi untuk penjemputan.",
            'order',
            url('order/' . $orderId . '/tracking'),
            (int)$orderId
        );

        // WhatsApp Notification Simulation
        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyOrderConfirmed(
                $customerUser['phone'] ?? '081234567890',
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                $store['name'],
                (int)$orderId,
                (int)$order['customer_id']
            );
        }

        set_flash('success', "Pesanan #{$order['order_code']} berhasil dikonfirmasi dan dialihkan ke antrean penjemputan kurir.");
        $this->redirect('dashboard/store');
    }

    /**
     * Reject incoming order
     */
    public function rejectOrder(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/store');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        // Validate Store Ownership
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store || (int)$store['id'] !== (int)$order['store_id']) {
            set_flash('error', 'Akses ditolak: Anda tidak berwenang atas pesanan toko ini.');
            $this->redirect('dashboard/store');
            return;
        }

        $reason = trim($request->get('reason', 'Kapasitas toko sedang penuh'));
        $this->orderModel->updateStatus((int)$orderId, 'DIBATALKAN', (int)$user['id'], "Pesanan ditolak oleh toko: {$reason}");
        set_flash('info', "Pesanan #{$order['order_code']} telah dibatalkan.");
        $this->redirect('dashboard/store');
    }

    /**
     * Submit laundry actual weighing (Admin Toko / Owner Toko)
     */
    public function submitWeighing(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/store');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        // Validate Store Ownership
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store || (int)$store['id'] !== (int)$order['store_id']) {
            set_flash('error', 'Akses ditolak: Anda tidak berwenang atas pesanan toko ini.');
            $this->redirect('dashboard/store');
            return;
        }

        $weight = (float)$request->get('weight_actual', 0);
        $pieces = (int)$request->get('pieces_count', 0);
        $notes = trim($request->get('notes_from_store', ''));

        if ($weight <= 0) {
            set_flash('error', 'Berat aktual penimbangan harus lebih dari 0 kg.');
            $this->redirect('dashboard/store');
            return;
        }

        $success = $this->orderModel->submitWeighing((int)$orderId, $weight, $pieces, $notes, (int)$user['id']);

        if ($success) {
            // WA notification to customer about weighing completed
            $updatedOrder = $this->orderModel->find((int)$orderId);
            $customerUser = $this->userModel->find((int)$order['customer_id']);
            if ($customerUser && !empty($customerUser['phone'])) {
                $waService = new \App\Services\WhatsAppNotificationService();
                $expiryText = ($updatedOrder && !empty($updatedOrder['payment_expired_at']))
                    ? date('H:i, d M Y', strtotime($updatedOrder['payment_expired_at']))
                    : '1 jam ke depan';
                $waService->notifyWeighingCompleted(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $store['name'] ?? 'SmartLaundry',
                    (float)(($updatedOrder['total_price'] ?? 0)),
                    $weight,
                    $expiryText,
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }
            // Notify store staff about payment received pending
            $waService2 = new \App\Services\WhatsAppNotificationService();
            $waService2->notifyStorePaymentReceived(
                (int)$store['id'],
                $order['order_code'],
                (float)(($updatedOrder['total_price'] ?? 0)),
                (int)$orderId
            );

            set_flash('success', "Penimbangan pesanan #{$order['order_code']} berhasil disimpan ({$weight} kg). Tagihan QRIS 1 jam telah diterbitkan!");
        } else {
            set_flash('error', 'Terjadi kesalahan saat menyimpan hasil penimbangan.');
        }

        $this->redirect('dashboard/store');
    }

    /**
     * Confirm Payment (Cash or QRIS Manual Confirmation)
     */
    public function confirmPayment(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/store');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        // Validate Store Ownership
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store || (int)$store['id'] !== (int)$order['store_id']) {
            set_flash('error', 'Akses ditolak: Anda tidak berwenang atas pesanan toko ini.');
            $this->redirect('dashboard/store');
            return;
        }

        $method = $request->get('method', 'CASH');

        // Mark payment as PAID
        $this->paymentModel->markAsPaid((int)$orderId, $method, 'MANUAL_STORE_CONFIRM_' . time());

        // Update order status to SEDANG_DICUCI
        $this->orderModel->updateStatus((int)$orderId, 'SEDANG_DICUCI', (int)$user['id'], "Pembayaran {$method} berhasil dikonfirmasi oleh toko. Pakaian masuk proses pencucian.");

        // In-app & WhatsApp notification to customer
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Pembayaran Berhasil Dikonfirmasi! 🎉',
            "Pembayaran pesanan #{$order['order_code']} berhasil dikonfirmasi oleh toko. Pakaian Anda masuk proses pencucian.",
            'payment',
            url('dashboard/customer'),
            (int)$orderId
        );

        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser && !empty($customerUser['phone'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyPaymentSuccess(
                $customerUser['phone'],
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                (float)$order['total_price'],
                'CASH',
                $store['name'] ?? 'SmartLaundry',
                (int)$orderId,
                (int)$order['customer_id'],
                $method
            );
        }

        // Notify store staff about payment received
        $waServiceStore = new \App\Services\WhatsAppNotificationService();
        $waServiceStore->notifyStorePaymentReceived((int)$store['id'], $order['order_code'], (float)$order['total_price'], (int)$orderId, $method);

        set_flash('success', "Pembayaran pesanan #{$order['order_code']} berhasil diverifikasi LUNAS. Status pesanan diubah ke 'Sedang Dicuci'.");
        $this->redirect('dashboard/store');
    }

    /**
     * Update order status along the laundry processing workflow
     */
    public function updateOrderStatus(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/store');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        // Validate Store Ownership
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store || (int)$store['id'] !== (int)$order['store_id']) {
            set_flash('error', 'Akses ditolak: Anda tidak berwenang atas pesanan toko ini.');
            $this->redirect('dashboard/store');
            return;
        }

        $newStatus = $request->get('new_status');
        $validStatuses = [
            'SAMPAI_DI_TOKO',
            'SEDANG_DICUCI',
            'SEDANG_DIPROSES',
            'SIAP_DIANTAR',
            'KURIR_MENUJU_CUSTOMER',
            'LAUNDRY_DIANTAR',
            'SELESAI'
        ];

        if (!in_array($newStatus, $validStatuses, true)) {
            set_flash('error', 'Status tahapan laundry tidak valid.');
            $this->redirect('dashboard/store');
            return;
        }

        $note = trim($request->get('notes', "Status diperbarui oleh operator toko ke {$newStatus}"));
        $this->orderModel->updateStatus((int)$orderId, $newStatus, (int)$user['id'], $note);

        $customerUser = $this->userModel->find((int)$order['customer_id']);
        $waService = new \App\Services\WhatsAppNotificationService();
        $notifModel = new \App\Models\Notification();

        if ($newStatus === 'SEDANG_DICUCI') {
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Laundry Sedang Dicuci #' . $order['order_code'],
                "Cucian pesanan #{$order['order_code']} sedang dalam tahap pencucian mesin higienis.",
                'order',
                url('order/' . $orderId . '/tracking'),
                (int)$orderId
            );

            if ($customerUser && !empty($customerUser['phone'])) {
                $waService->notifyLaundryWashing(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $store['name'] ?? 'SmartLaundry',
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }
        } elseif ($newStatus === 'SEDANG_DIPROSES') {
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Laundry Sedang Diproses #' . $order['order_code'],
                "Cucian pesanan #{$order['order_code']} sedang dalam tahap pengeringan, setrika uap, dan packing wangi.",
                'order',
                url('order/' . $orderId . '/tracking'),
                (int)$orderId
            );

            if ($customerUser && !empty($customerUser['phone'])) {
                $waService->notifyLaundryProcessing(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $store['name'] ?? 'SmartLaundry',
                    'SEDANG_DIPROSES',
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }
        } elseif ($newStatus === 'SIAP_DIANTAR') {
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Cucian Anda Sudah Bersih & Rapi! ✨',
                "Pesanan #{$order['order_code']} telah selesai diproses oleh {$store['name']} dan siap untuk diantar.",
                'order',
                url('order/' . $orderId . '/tracking'),
                (int)$orderId
            );

            if ($customerUser && !empty($customerUser['phone'])) {
                $waService->notifyLaundryReady(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $store['name'] ?? 'SmartLaundry',
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }
        } elseif ($newStatus === 'KURIR_MENUJU_CUSTOMER') {
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Kurir Sedang Mengantar Cucian #' . $order['order_code'],
                "Kurir sedang dalam perjalanan mengantarkan cucian bersih pesanan #{$order['order_code']} ke alamat Anda.",
                'order',
                url('order/' . $orderId . '/tracking'),
                (int)$orderId
            );

            if ($customerUser && !empty($customerUser['phone'])) {
                $waService->notifyCourierToDelivery(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $order['courier_name'] ?? 'Kurir SmartLaundry',
                    $store['name'] ?? 'SmartLaundry',
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }
        } elseif ($newStatus === 'LAUNDRY_DIANTAR') {
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Laundry Anda Telah Diantar 📦',
                "Cucian pesanan #{$order['order_code']} telah tiba di alamat Anda. Mohon konfirmasi penerimaan di aplikasi.",
                'order',
                url('dashboard/customer'),
                (int)$orderId
            );

            if ($customerUser && !empty($customerUser['phone'])) {
                $waService->notifyLaundryDelivered(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $order['courier_name'] ?? 'Kurir SmartLaundry',
                    $store['name'] ?? 'SmartLaundry',
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }
        } elseif ($newStatus === 'SELESAI') {
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Pesanan Selesai! Terima Kasih 🎉',
                "Pesanan #{$order['order_code']} telah selesai. Terima kasih telah mempercayai {$store['name']}.",
                'order',
                url('order/' . $orderId . '/review'),
                (int)$orderId
            );

            if ($customerUser && !empty($customerUser['phone'])) {
                $waService->notifyOrderCompleted(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $store['name'] ?? 'SmartLaundry',
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }
        }

        set_flash('success', "Status pesanan #{$order['order_code']} berhasil diperbarui menjadi '{$newStatus}' dan notifikasi WhatsApp telah dikirim.");
        $this->redirect('dashboard/store');
    }

    /**
     * Send order process WhatsApp notification via Fonnte (Admin action)
     * Supports sending/resending notification for all stages: weighing to completed
     */
    public function sendOrderWhatsAppNotification(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Token CSRF tidak valid.'], 403);
                return;
            }
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/store');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order) {
            if ($request->isAjax()) {
                $response->json(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
                return;
            }
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        // Validate admin access (store owner/admin of this store, or platform admin)
        $isPlatformAdmin = in_array($user['role'], ['owner_platform', 'admin_owner'], true);
        if (!$isPlatformAdmin) {
            $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
            if (!$store || (int)$store['id'] !== (int)$order['store_id']) {
                if ($request->isAjax()) {
                    $response->json(['success' => false, 'message' => 'Akses ditolak: Anda tidak berwenang atas pesanan toko ini.'], 403);
                    return;
                }
                set_flash('error', 'Akses ditolak: Anda tidak berwenang atas pesanan toko ini.');
                $this->redirect('dashboard/store');
                return;
            }
        }

        $processType = trim($request->get('process_type', 'AUTO'));
        $waService = new \App\Services\WhatsAppNotificationService();
        $result = $waService->sendOrderProcessNotification((int)$orderId, $processType, true);

        if ($request->isAjax()) {
            $response->json($result, $result['success'] ? 200 : 400);
            return;
        }

        if ($result['success']) {
            set_flash('success', $result['message']);
        } else {
            set_flash('error', $result['message']);
        }

        $this->redirect('dashboard/store');
    }

    /**
     * Redirect GET requests to store/orders/{id}/send-whatsapp back to dashboard
     * This prevents 500 errors when users open the URL directly in a browser
     */
    public function redirectStoreDashboard(Request $request, Response $response, string $id = ''): void {
        set_flash('info', 'Gunakan tombol kirim notifikasi pada halaman dashboard untuk mengirim WhatsApp.');
        $this->redirect('dashboard/store');
    }

    // =========================================================================
    // COURIER ACTIONS (WITH STRICT SCOPING TO LOGGED-IN COURIER)
    // =========================================================================

    /**
     * Toggle Courier Availability (Standby / Offline)
     */
    public function toggleCourierAvailability(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $courier = $this->courierModel->findByUserId((int)$user['id']);

        if (!$courier) {
            set_flash('error', 'Profil kurir tidak ditemukan.');
            $this->redirect('dashboard/courier');
            return;
        }

        $this->courierModel->toggleAvailability((int)$courier['id']);
        set_flash('success', 'Status ketersediaan kurir berhasil diperbarui.');
        $this->redirect('dashboard/courier');
    }

    /**
     * Start pickup travel: KURIR_MENUJU_LOKASI
     */
    public function startPickup(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order || (int)$order['courier_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak: Tugas ini bukan milik Anda.');
            $this->redirect('dashboard/courier');
            return;
        }

        $this->orderModel->updateStatus((int)$orderId, 'KURIR_MENUJU_LOKASI', (int)$user['id'], 'Kurir memulai perjalanan menuju alamat penjemputan customer.');
        
        $courier = $this->courierModel->findByUserId((int)$user['id']);
        if ($courier) {
            $this->courierModel->updateLocation((int)$courier['id'], (float)($order['pickup_lat'] ?? -6.20000000) - 0.005, (float)($order['pickup_lng'] ?? 106.81666600) - 0.005, (int)$orderId);
        }

        // In-app & WhatsApp notification to customer
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Kurir Menuju Lokasi Penjemputan #' . $order['order_code'],
            "Kurir {$user['name']} sedang dalam perjalanan menuju lokasi Anda untuk menjemput pakaian.",
            'order',
            url('order/' . $orderId . '/tracking'),
            (int)$orderId
        );

        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser && !empty($customerUser['phone'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyCourierDispatched(
                $customerUser['phone'],
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                $user['name'] ?? 'Kurir',
                $user['phone'] ?? '-',
                'JEMPUT',
                (int)$orderId,
                (int)$order['customer_id']
            );
        }

        set_flash('success', "Navigasi dimulai! Anda sedang menuju lokasi customer untuk order #{$order['order_code']}.");
        $this->redirect('dashboard/courier');
    }

    /**
     * Confirm laundry picked up from customer: LAUNDRY_DIJEMPUT
     */
    public function confirmPicked(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order || (int)$order['courier_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak: Tugas ini bukan milik Anda.');
            $this->redirect('dashboard/courier');
            return;
        }

        $this->orderModel->updateStatus((int)$orderId, 'LAUNDRY_DIJEMPUT', (int)$user['id'], 'Kurir telah tiba di alamat customer dan menerima kantong cucian.');

        $courier = $this->courierModel->findByUserId((int)$user['id']);
        if ($courier) {
            $this->courierModel->updateLocation((int)$courier['id'], (float)($order['pickup_lat'] ?? -6.20888800), (float)($order['pickup_lng'] ?? 106.82888800), (int)$orderId);
        }

        $storeModel = new \App\Models\Store();
        $store = $storeModel->find((int)$order['store_id']);
        $storeName = $store['name'] ?? 'SmartLaundry';

        // In-app & WhatsApp notification to customer
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Laundry Berhasil Dijemput #' . $order['order_code'],
            "Pakaian cucian Anda telah berhasil dijemput oleh kurir {$user['name']} dan sedang dibawa menuju outlet toko.",
            'order',
            url('order/' . $orderId . '/tracking'),
            (int)$orderId
        );

        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser && !empty($customerUser['phone'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyLaundryPicked(
                $customerUser['phone'],
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                $user['name'] ?? 'Kurir',
                $storeName,
                (int)$orderId,
                (int)$order['customer_id']
            );
        }

        set_flash('success', "Pakaian order #{$order['order_code']} berhasil diambil dari customer! Siap diantar ke outlet.");
        $this->redirect('dashboard/courier');
    }

    /**
     * Start travel back to store: DALAM_PERJALANAN_KE_TOKO
     */
    public function startTravelToStore(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order || (int)$order['courier_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak: Tugas ini bukan milik Anda.');
            $this->redirect('dashboard/courier');
            return;
        }

        $this->orderModel->updateStatus((int)$orderId, 'DALAM_PERJALANAN_KE_TOKO', (int)$user['id'], 'Kurir membawa pakaian customer dan sedang dalam perjalanan menuju outlet toko.');

        $courier = $this->courierModel->findByUserId((int)$user['id']);
        if ($courier) {
            $this->courierModel->updateLocation((int)$courier['id'], -6.20200000, 106.81800000, (int)$orderId);
        }

        // In-app and WhatsApp notification to customer
        $storeModel = new \App\Models\Store();
        $store = $storeModel->find((int)$order['store_id']);
        $storeName = $store['name'] ?? 'SmartLaundry';

        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Laundry Menuju Outlet Toko #' . $order['order_code'],
            "Cucian Anda sedang dibawa kurir menuju outlet {$storeName} untuk ditimbang.",
            'order',
            url('order/' . $orderId . '/tracking'),
            (int)$orderId
        );

        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser && !empty($customerUser['phone'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->sendEventMultiMessages(
                'TRANSACTION',
                'courier_picked_up',
                $customerUser['phone'],
                $customerUser['name'] ?? 'Pelanggan',
                [
                    'nama_customer' => $customerUser['name'] ?? 'Pelanggan',
                    'customer_name' => $customerUser['name'] ?? 'Pelanggan',
                    'nomor_pesanan' => $order['order_code'],
                    'order_code'    => $order['order_code'],
                    'nama_kurir'    => $user['name'] ?? 'Kurir',
                    'courier_name'  => $user['name'] ?? 'Kurir',
                    'store_name'    => $storeName,
                    'activity_time' => date('d/m/Y H:i') . ' WIB',
                    'link'          => ''
                ],
                "COURIER_TO_STORE:{$orderId}:{$order['customer_id']}",
                (int)$order['customer_id'],
                (int)$order['store_id'],
                (int)$orderId
            );
        }

        set_flash('success', "Perjalanan ke outlet dimulai untuk order #{$order['order_code']}.");
        $this->redirect('dashboard/courier');
    }

    /**
     * Arrive at store: SAMPAI_DI_TOKO
     */
    public function arriveAtStore(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order || (int)$order['courier_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak: Tugas ini bukan milik Anda.');
            $this->redirect('dashboard/courier');
            return;
        }

        $this->orderModel->updateStatus((int)$orderId, 'SAMPAI_DI_TOKO', (int)$user['id'], 'Pakaian telah sampai di outlet toko dan siap masuk antrean penimbangan.');

        $courier = $this->courierModel->findByUserId((int)$user['id']);
        if ($courier) {
            $this->courierModel->updateLocation((int)$courier['id'], -6.19500000, 106.82000000, (int)$orderId);
        }

        $storeModel = new \App\Models\Store();
        $store = $storeModel->find((int)$order['store_id']);
        $storeName = $store['name'] ?? 'SmartLaundry';

        // In-app & WhatsApp notification to customer
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Laundry Sampai di Toko #' . $order['order_code'],
            "Cucian Anda telah tiba di outlet {$storeName} dan sedang dalam antrean penimbangan.",
            'order',
            url('order/' . $orderId . '/tracking'),
            (int)$orderId
        );

        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser && !empty($customerUser['phone'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyArrivedAtStore(
                $customerUser['phone'],
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                $storeName,
                (int)$orderId,
                (int)$order['customer_id']
            );
        }

        set_flash('success', "Pakaian order #{$order['order_code']} telah tiba di outlet! Admin toko akan segera menimbang.");
        $this->redirect('dashboard/courier');
    }

    /**
     * Start delivery travel: KURIR_MENUJU_CUSTOMER
     */
    public function startDelivery(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order || (int)$order['courier_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak: Tugas ini bukan milik Anda.');
            $this->redirect('dashboard/courier');
            return;
        }

        $this->orderModel->updateStatus((int)$orderId, 'KURIR_MENUJU_CUSTOMER', (int)$user['id'], 'Kurir membawa cucian bersih dan menuju alamat pengantaran customer.');
        
        $courier = $this->courierModel->findByUserId((int)$user['id']);
        if ($courier) {
            $this->courierModel->updateLocation((int)$courier['id'], -6.19800000, 106.81900000, (int)$orderId);
        }

        // In-app & WhatsApp notification to customer
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Kurir Sedang Mengantar Laundry #' . $order['order_code'],
            "Kurir {$user['name']} sedang dalam perjalanan mengantarkan pakaian bersih Anda ke alamat tujuan.",
            'order',
            url('order/' . $orderId . '/tracking'),
            (int)$orderId
        );

        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser && !empty($customerUser['phone'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyCourierDispatched(
                $customerUser['phone'],
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                $user['name'] ?? 'Kurir',
                $user['phone'] ?? '-',
                'ANTAR',
                (int)$orderId,
                (int)$order['customer_id']
            );
        }

        set_flash('success', "Pengantaran dimulai! Cucian bersih order #{$order['order_code']} sedang dalam perjalanan ke customer.");
        $this->redirect('dashboard/courier');
    }

    /**
     * Complete delivery: LAUNDRY_DIANTAR -> SELESAI
     */
    public function completeDelivery(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order || (int)$order['courier_id'] !== (int)$user['id']) {
            set_flash('error', 'Akses ditolak: Tugas ini bukan milik Anda.');
            $this->redirect('dashboard/courier');
            return;
        }

        // Update status to LAUNDRY_DIANTAR (customer must confirm receipt to complete order)
        $this->orderModel->updateStatus((int)$orderId, 'LAUNDRY_DIANTAR', (int)$user['id'], 'Kurir telah tiba di alamat customer dan menyerahkan pakaian bersih. Menunggu konfirmasi penerimaan oleh customer.');

        // Update payment if cash
        if ($order['payment_method'] === 'CASH') {
            $this->paymentModel->markAsPaid((int)$orderId, 'CASH', 'CASH_COLLECTED_BY_COURIER_' . $user['id']);
        }

        $courier = $this->courierModel->findByUserId((int)$user['id']);
        if ($courier) {
            $this->courierModel->updateLocation((int)$courier['id'], (float)($order['delivery_lat'] ?? $order['pickup_lat'] ?? -6.20888800), (float)($order['delivery_lng'] ?? $order['pickup_lng'] ?? 106.82888800), (int)$orderId);
        }

        $storeModel = new \App\Models\Store();
        $store = $storeModel->find((int)$order['store_id']);
        $storeName = $store['name'] ?? 'SmartLaundry';

        // In-app notification to customer: "Laundry Anda sudah diantar, mohon konfirmasi penerimaan"
        $notifModel = new \App\Models\Notification();
        $notifModel->createNotification(
            (int)$order['customer_id'],
            'Laundry Anda Sudah Diantar 📦',
            "Laundry Anda sudah diantar oleh kurir. Mohon periksa cucian Anda dan konfirmasi penerimaan di aplikasi.",
            'order',
            url('dashboard/customer'),
            (int)$orderId
        );

        // WhatsApp Notification to customer
        $customerUser = $this->userModel->find((int)$order['customer_id']);
        if ($customerUser && !empty($customerUser['phone'])) {
            $waService = new \App\Services\WhatsAppNotificationService();
            $waService->notifyLaundryDelivered(
                $customerUser['phone'],
                $customerUser['name'] ?? 'Pelanggan',
                $order['order_code'],
                $user['name'] ?? 'Kurir',
                $storeName,
                (int)$orderId,
                (int)$order['customer_id']
            );
        }

        set_flash('success', "Laundry order #{$order['order_code']} berhasil diserahkan! Status: LAUNDRY_DIANTAR. Menunggu konfirmasi penerimaan dari pelanggan.");
        $this->redirect('dashboard/courier');
    }

    /**
     * Customer confirms receipt of laundry -> transitions status to SELESAI
     */
    public function confirmCustomerReceived(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/customer');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->find((int)$orderId);

        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/customer');
            return;
        }

        // Strict tenant authorization: only the customer who owns the order can confirm receipt
        if ((int)$order['customer_id'] !== (int)$user['id']) {
            http_response_code(403);
            set_flash('error', 'Akses ditolak: Anda tidak memiliki akses atas pesanan ini.');
            $this->redirect('dashboard/customer');
            return;
        }

        if ($order['status'] !== 'LAUNDRY_DIANTAR') {
            set_flash('info', 'Status pesanan saat ini tidak menunggu konfirmasi penerimaan.');
            $this->redirect('dashboard/customer');
            return;
        }

        $this->orderModel->completeOrder(
            (int)$orderId,
            (int)$user['id'],
            'Laundry telah diterima oleh customer dalam kondisi bersih dan rapi. Transaksi SELESAI.'
        );

        set_flash('success', "Terima kasih! Pesanan #{$order['order_code']} telah Anda terima. Silakan berikan ulasan & rating bintang 5 Anda.");
        $this->redirect('order/' . $orderId . '/review');
    }


    /**
     * Simulated GPS Location Update
     */
    public function updateCourierLocation(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $user = auth_user();
        $courier = $this->courierModel->findByUserId((int)$user['id']);

        if (!$courier) {
            set_flash('error', 'Profil kurir tidak ditemukan.');
            $this->redirect('dashboard/courier');
            return;
        }

        // Generate or get coordinates
        $lat = (float)$request->get('latitude', (-6.1750 + (mt_rand(-300, 300) / 10000)));
        $lng = (float)$request->get('longitude', (106.7800 + (mt_rand(-300, 300) / 10000)));
        $orderId = !empty($request->get('order_id')) ? (int)$request->get('order_id') : null;

        $this->courierModel->updateLocation((int)$courier['id'], $lat, $lng, $orderId);

        set_flash('success', "Titik GPS kurir berhasil diperbarui: ({$lat}, {$lng})");
        $this->redirect('dashboard/courier');
    }

    // =========================================================================
    // CUSTOMER ACTIONS
    // =========================================================================

    /**
     * Customer Reorder draft action
     */
    public function reorder(Request $request, Response $response, string $orderId): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('dashboard/customer');
            return;
        }

        $user = auth_user();
        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order || (int)$order['customer_id'] !== (int)$user['id']) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/customer');
            return;
        }

        // Store draft into session for order creation flow
        $_SESSION['order_draft'] = [
            'store_id' => $order['store_id'],
            'store_name' => $order['store_name'],
            'pickup_address_id' => $order['pickup_address_id'],
            'items' => $order['items'] ?? [],
            'reorder_from' => $order['order_code']
        ];

        set_flash('info', "Rincian layanan dari pesanan #{$order['order_code']} berhasil dimuat kembali. Silakan tentukan jadwal dan alamat penjemputan.");
        $this->redirect('order/create?store_id=' . $order['store_id']);
    }
}
