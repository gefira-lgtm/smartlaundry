<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Store;
use App\Models\Courier;
use App\Models\User;

class RefundController extends Controller {
    private Order $orderModel;
    private Store $storeModel;
    private Courier $courierModel;
    private User $userModel;

    public function __construct() {
        $this->orderModel = new Order();
        $this->storeModel = new Store();
        $this->courierModel = new Courier();
        $this->userModel = new User();
    }

    /**
     * Store Admin / Owner: View all expired orders needing return action
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);

        if (!$store) {
            set_flash('error', 'Toko tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        $storeId = (int)$store['id'];
        $expiredOrders = $this->orderModel->getStoreExpiredPaymentsQueue($storeId);
        $refundsQueue = $this->orderModel->getStoreRefundsQueue($storeId);

        $this->render('refund/index', [
            'title' => 'Pengembalian Laundry - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'expiredOrders' => $expiredOrders,
            'refundsQueue' => $refundsQueue
        ], 'layouts/main');
    }

    /**
     * Store Admin / Owner: Show Approval Page for Expired Order
     */
    public function showApproval(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);

        if (!$store) {
            set_flash('error', 'Akses ditolak: Anda tidak terdaftar pada toko manapun.');
            $this->redirect('dashboard/store');
            return;
        }

        $order = $this->orderModel->findWithDetails((int)$orderId);
        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            set_flash('error', 'Pesanan tidak ditemukan atau bukan milik outlet Anda.');
            $this->redirect('dashboard/store');
            return;
        }

        if ($order['status'] !== 'PEMBAYARAN_KADALUARSA') {
            set_flash('error', 'Pesanan ini tidak berada dalam status Pembayaran Kadaluarsa.');
            $this->redirect('dashboard/store');
            return;
        }

        // Fetch available couriers for store
        $couriers = $this->courierModel->getAllByStore((int)$store['id']);

        $this->render('refund/approval', [
            'title' => 'Persetujuan Pengembalian Laundry #' . $order['order_code'] . ' - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'order' => $order,
            'couriers' => $couriers
        ], 'layouts/main');
    }

    /**
     * Store Admin / Owner: Process Return Approval (POST)
     */
    public function processApproval(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);

        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard/store');
            return;
        }

        $order = $this->orderModel->findWithDetails((int)$orderId);
        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        if ($order['status'] !== 'PEMBAYARAN_KADALUARSA') {
            set_flash('error', 'Pesanan ini tidak dalam status Pembayaran Kadaluarsa.');
            $this->redirect('dashboard/store');
            return;
        }

        $post = $request->post();

        // Biaya pengembalian (manual input)
        $rawFee = str_replace(['.', ',', ' '], '', $post['refund_fee'] ?? '0');
        $refundFee = max(0.00, (float)$rawFee);

        // Penanggung biaya (customer / store / free)
        $bearer = strtolower(trim($post['refund_fee_bearer'] ?? 'free'));
        if (!in_array($bearer, ['customer', 'store', 'free'], true)) {
            $bearer = 'free';
        }

        $courierUserId = !empty($post['courier_user_id']) ? (int)$post['courier_user_id'] : null;
        $notes = trim($post['notes'] ?? '');

        $success = $this->orderModel->approveRefund(
            (int)$orderId,
            $refundFee,
            $bearer,
            $courierUserId,
            (int)$user['id'],
            $notes
        );

        if ($success) {
            set_flash('success', "Pengembalian pesanan #{$order['order_code']} berhasil disetujui dan dialihkan ke armada kurir.");
        } else {
            set_flash('error', 'Gagal memproses persetujuan pengembalian.');
        }

        $this->redirect('dashboard/store');
    }

    /**
     * Store Admin / Owner: Assign or Re-assign Courier for Return
     */
    public function assignCourier(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);

        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard/store');
            return;
        }

        $order = $this->orderModel->find((int)$orderId);
        if (!$order || (int)$order['store_id'] !== (int)$store['id']) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        $courierUserId = (int)($request->post('courier_user_id') ?? 0);
        if (!$courierUserId) {
            set_flash('error', 'Pilih kurir terlebih dahulu.');
            $this->redirect('dashboard/store');
            return;
        }

        $success = $this->orderModel->assignRefundCourier((int)$orderId, $courierUserId, (int)$user['id']);
        if ($success) {
            set_flash('success', 'Kurir berhasil ditugaskan untuk pengembalian laundry.');
        } else {
            set_flash('error', 'Gagal menugaskan kurir.');
        }

        $this->redirect('dashboard/store');
    }

    /**
     * Courier: Start Delivery of Refunded Laundry to Customer
     */
    public function startReturnDelivery(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $courierUserId = (int)$user['id'];

        $order = $this->orderModel->findWithDetails((int)$orderId);
        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/courier');
            return;
        }

        // Verify courier assignment
        if ((int)$order['courier_id'] !== $courierUserId && (int)$order['refund_assigned_courier_id'] !== $courierUserId) {
            set_flash('error', 'Akses ditolak: Tugas ini bukan untuk Anda.');
            $this->redirect('dashboard/courier');
            return;
        }

        if ($order['status'] !== 'PROSES_PENGEMBALIAN') {
            set_flash('error', 'Status pesanan tidak valid.');
            $this->redirect('dashboard/courier');
            return;
        }

        $this->orderModel->startRefundDelivery((int)$orderId, $courierUserId);
        set_flash('success', "Perjalanan pengantaran pengembalian #{$order['order_code']} dimulai. Posisi GPS Anda dipantau sistem.");
        $this->redirect('dashboard/courier');
    }

    /**
     * Courier: Confirm Laundry Received by Customer (Order cancelled/refunded)
     */
    public function confirmReturn(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $courierUserId = (int)$user['id'];

        $order = $this->orderModel->findWithDetails((int)$orderId);
        if (!$order) {
            set_flash('error', 'Pesanan tidak ditemukan.');
            $this->redirect('dashboard/courier');
            return;
        }

        // Verify courier assignment
        if ((int)$order['courier_id'] !== $courierUserId && (int)$order['refund_assigned_courier_id'] !== $courierUserId) {
            set_flash('error', 'Akses ditolak: Anda tidak ditugaskan untuk pesanan ini.');
            $this->redirect('dashboard/courier');
            return;
        }

        if ($order['status'] !== 'PROSES_PENGEMBALIAN') {
            set_flash('error', 'Pesanan tidak dalam status proses pengembalian.');
            $this->redirect('dashboard/courier');
            return;
        }

        $notes = trim($request->post('notes') ?? 'Pakaian telah diserahterimakan kembali ke customer.');
        $success = $this->orderModel->completeRefundReturn((int)$orderId, $courierUserId, $notes);

        if ($success) {
            set_flash('success', "Pengembalian cucian #{$order['order_code']} berhasil dikonfirmasi selesai. Pesanan telah ditutup (DIBATALKAN).");
        } else {
            set_flash('error', 'Gagal memproses penyelesaian pengembalian.');
        }

        $this->redirect('dashboard/courier');
    }
}
