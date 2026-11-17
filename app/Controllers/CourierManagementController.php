<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Store;
use App\Models\Courier;
use App\Models\User;

class CourierManagementController extends Controller {
    private Store $storeModel;
    private Courier $courierModel;
    private User $userModel;

    public function __construct() {
        $this->storeModel = new Store();
        $this->courierModel = new Courier();
        $this->userModel = new User();
    }

    /**
     * Get store ID for current authenticated owner_toko
     */
    private function getOwnerStore(): ?array {
        $user = auth_user();
        if (!$user) return null;
        return $this->storeModel->findForUser((int)$user['id'], 'owner_toko');
    }

    /**
     * List all couriers registered for this store
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->getOwnerStore();

        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $couriers = $this->courierModel->getAllByStore((int)$store['id']);

        $this->render('store/couriers/index', [
            'title' => 'Manajemen Armada Kurir - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'couriers' => $couriers
        ], 'layouts/main');
    }

    /**
     * Show form to register new courier
     */
    public function create(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->getOwnerStore();

        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $this->render('store/couriers/create', [
            'title' => 'Tambah Kurir Baru - ' . APP_NAME,
            'user' => $user,
            'store' => $store
        ], 'layouts/main');
    }

    /**
     * Store newly created courier and user account atomically
     */
    public function store(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/couriers/create');
            return;
        }

        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $errors = $this->validate($request->all(), [
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'password' => 'required',
            'vehicle_type' => 'required',
            'license_plate' => 'required'
        ]);

        $email = strtolower(trim($request->get('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }

        // Check email uniqueness
        $existingUser = $this->userModel->findByEmail($email);
        if ($existingUser) {
            $errors['email'] = 'Alamat email sudah terdaftar di sistem. Gunakan email lain.';
        }

        $password = $request->get('password');
        if (strlen($password) < 6) {
            $errors['password'] = 'Password minimal harus 6 karakter.';
        }

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('store/couriers/create');
            return;
        }

        $userData = [
            'name' => trim($request->get('name')),
            'email' => $email,
            'phone' => trim($request->get('phone')),
            'password' => $password,
            'avatar' => 'avatar_default.png'
        ];

        $courierData = [
            'vehicle_type' => trim($request->get('vehicle_type')),
            'license_plate' => strtoupper(trim($request->get('license_plate')))
        ];

        try {
            $this->courierModel->createCourierWithUser((int)$store['id'], $userData, $courierData);

            // Activity Notification: COURIER_CHANGED
            try {
                $waNotif = new \App\Services\WhatsAppNotificationService();
                $waNotif->notifyActivity('COURIER_CHANGED', [
                    'courier_name' => $userData['name'],
                    'change_detail' => 'Kurir baru ditambahkan (' . $courierData['vehicle_type'] . ' - ' . $courierData['license_plate'] . ')',
                    'store_name' => $store['name'] ?? 'SmartLaundry'
                ], (int)$store['id'], [
                    'name' => auth_user()['name'] ?? 'Owner Toko',
                    'role' => auth_user()['role_name'] ?? (auth_user()['role'] ?? 'owner_toko')
                ]);
            } catch (\Throwable $e) {}

            set_flash('success', 'Kurir baru "' . e($userData['name']) . '" dan akun login berhasil dibuat.');
            $this->redirect('store/couriers');
        } catch (\Throwable $th) {
            set_flash('error', 'Gagal mendaftarkan kurir: ' . $th->getMessage());
            $this->redirect('store/couriers/create');
        }
    }

    /**
     * Show edit vehicle form
     */
    public function edit(Request $request, Response $response, string $id): void {
        $user = auth_user();
        $store = $this->getOwnerStore();

        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $courierId = (int)$id;
        $courier = $this->courierModel->findByIdAndStore($courierId, (int)$store['id']);

        if (!$courier) {
            set_flash('error', 'Data kurir tidak ditemukan atau Anda tidak memiliki akses.');
            $this->redirect('store/couriers');
            return;
        }

        $this->render('store/couriers/edit', [
            'title' => 'Edit Kendaraan Kurir - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'courier' => $courier
        ], 'layouts/main');
    }

    /**
     * Update courier vehicle info
     */
    public function update(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/couriers/' . $id . '/edit');
            return;
        }

        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $courierId = (int)$id;
        $courier = $this->courierModel->findByIdAndStore($courierId, (int)$store['id']);
        if (!$courier) {
            set_flash('error', 'Data kurir tidak ditemukan.');
            $this->redirect('store/couriers');
            return;
        }

        $errors = $this->validate($request->all(), [
            'vehicle_type' => 'required',
            'license_plate' => 'required'
        ]);

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('store/couriers/' . $id . '/edit');
            return;
        }

        $this->courierModel->updateVehicleInfo($courierId, (int)$store['id'], [
            'vehicle_type' => trim($request->get('vehicle_type')),
            'license_plate' => strtoupper(trim($request->get('license_plate')))
        ]);

        set_flash('success', 'Data armada kendaraan kurir berhasil diperbarui.');
        $this->redirect('store/couriers');
    }

    /**
     * Soft deactivation of courier
     */
    public function deactivate(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/couriers');
            return;
        }

        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $courierId = (int)$id;
        $courier = $this->courierModel->findByIdAndStore($courierId, (int)$store['id']);
        if (!$courier) {
            set_flash('error', 'Kurir tidak ditemukan.');
            $this->redirect('store/couriers');
            return;
        }

        $this->courierModel->deactivatePermanently($courierId, (int)$store['id']);

        set_flash('success', 'Kurir "' . e($courier['courier_name']) . '" berhasil dinonaktifkan dari armada aktif.');
        $this->redirect('store/couriers');
    }

    /**
     * Reactivate courier
     */
    public function reactivate(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/couriers');
            return;
        }

        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $courierId = (int)$id;
        $courier = $this->courierModel->findByIdAndStore($courierId, (int)$store['id']);
        if (!$courier) {
            set_flash('error', 'Kurir tidak ditemukan.');
            $this->redirect('store/couriers');
            return;
        }

        $this->courierModel->reactivate($courierId, (int)$store['id']);

        set_flash('success', 'Kurir "' . e($courier['courier_name']) . '" berhasil diaktifkan kembali.');
        $this->redirect('store/couriers');
    }
}
