<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Store;
use App\Models\Courier;
use App\Models\CourierTaskOffer;

class CourierAssignmentController extends Controller {
    private Order $orderModel;
    private Store $storeModel;
    private Courier $courierModel;
    private CourierTaskOffer $offerModel;

    public function __construct() {
        $this->orderModel = new Order();
        $this->storeModel = new Store();
        $this->courierModel = new Courier();
        $this->offerModel = new CourierTaskOffer();
    }

    /**
     * Courier: Accept Task Offer
     */
    public function acceptOffer(Request $request, Response $response, string $offerId): void {
        $user = auth_user();
        if ($user['role'] !== 'kurir') {
            set_flash('error', 'Akses ditolak: Hanya kurir yang dapat menerima tawaran tugas.');
            $this->redirect('dashboard');
            return;
        }

        $courierUserId = (int)$user['id'];
        $result = $this->orderModel->processCourierAccept((int)$offerId, $courierUserId);

        if ($result['success']) {
            set_flash('success', $result['message']);
        } else {
            set_flash('error', $result['message']);
        }

        $this->redirect('dashboard/courier');
    }

    /**
     * Courier: Reject Task Offer
     */
    public function rejectOffer(Request $request, Response $response, string $offerId): void {
        $user = auth_user();
        if ($user['role'] !== 'kurir') {
            set_flash('error', 'Akses ditolak: Hanya kurir yang dapat merespon tawaran tugas.');
            $this->redirect('dashboard');
            return;
        }

        $courierUserId = (int)$user['id'];
        $reason = trim($request->post('reject_reason') ?? '');
        if (empty($reason)) {
            $reason = 'Kurir sedang tidak dapat mengambil tugas saat ini';
        }

        $result = $this->orderModel->processCourierReject((int)$offerId, $courierUserId, $reason);

        if ($result['success']) {
            set_flash('info', $result['message']);
        } else {
            set_flash('error', $result['message']);
        }

        $this->redirect('dashboard/courier');
    }

    /**
     * Store Admin / Owner: Manually assign a courier to an order
     */
    public function manualAssign(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);

        if (!$store) {
            set_flash('error', 'Akses ditolak: Toko tidak ditemukan.');
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

        $taskType = $request->post('task_type') ?? (($order['status'] === 'PROSES_PENGEMBALIAN') ? 'RETURN' : 'PICKUP');
        $success = $this->orderModel->manualAssignCourier((int)$orderId, $courierUserId, (int)$user['id'], $taskType);

        if ($success) {
            set_flash('success', "Kurir berhasil ditugaskan secara manual untuk pesanan #{$order['order_code']}.");
        } else {
            set_flash('error', 'Gagal menugaskan kurir.');
        }

        $this->redirect('dashboard/store');
    }
}
