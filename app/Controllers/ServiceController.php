<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Store;
use App\Models\LaundryService;

class ServiceController extends Controller {
    private Store $storeModel;
    private LaundryService $serviceModel;

    public function __construct() {
        $this->storeModel = new Store();
        $this->serviceModel = new LaundryService();
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
     * List all laundry services for this store
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->getOwnerStore();

        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $services = $this->serviceModel->getAllByStore((int)$store['id']);

        $this->render('store/services/index', [
            'title' => 'Katalog Layanan Laundry - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'services' => $services
        ], 'layouts/main');
    }

    /**
     * Show create service form
     */
    public function create(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->getOwnerStore();

        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $this->render('store/services/create', [
            'title' => 'Tambah Layanan Laundry - ' . APP_NAME,
            'user' => $user,
            'store' => $store
        ], 'layouts/main');
    }

    /**
     * Store new laundry service
     */
    public function store(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/services/create');
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
            'category' => 'required',
            'price' => 'required',
            'unit' => 'required',
            'estimated_duration' => 'required'
        ]);

        $price = (float)$request->get('price', 0);
        if ($price <= 0) {
            $errors['price'] = 'Harga layanan harus lebih besar dari 0.';
        }

        $validCategories = ['kiloan', 'satuan', 'express', 'special'];
        if (!in_array($request->get('category'), $validCategories)) {
            $errors['category'] = 'Kategori layanan tidak valid.';
        }

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('store/services/create');
            return;
        }

        $serviceData = [
            'store_id' => (int)$store['id'],
            'name' => trim($request->get('name')),
            'category' => $request->get('category'),
            'price' => $price,
            'unit' => trim($request->get('unit', 'kg')),
            'min_order' => (float)$request->get('min_order', 1),
            'estimated_duration' => trim($request->get('estimated_duration')),
            'description' => trim($request->get('description', '')),
            'icon' => trim($request->get('icon', 'bi-droplet')),
            'is_active' => (int)$request->get('is_active', 1)
        ];

        $this->serviceModel->createService($serviceData);

        // Activity Notification: SERVICE_ADDED
        try {
            $waNotif = new \App\Services\WhatsAppNotificationService();
            $waNotif->notifyActivity('SERVICE_ADDED', [
                'nama_layanan' => $serviceData['name'],
                'harga' => 'Rp ' . number_format($serviceData['price'], 0, ',', '.'),
                'unit' => $serviceData['unit'],
                'store_name' => $store['name'] ?? 'SmartLaundry'
            ], (int)$store['id'], [
                'name' => $user['name'] ?? 'Owner Toko',
                'role' => $user['role_name'] ?? ($user['role'] ?? 'owner_toko')
            ]);
        } catch (\Throwable $e) {}

        set_flash('success', 'Layanan laundry baru berhasil ditambahkan ke katalog.');
        $this->redirect('store/services');
    }

    /**
     * Show edit service form
     */
    public function edit(Request $request, Response $response, string $id): void {
        $user = auth_user();
        $store = $this->getOwnerStore();

        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $serviceId = (int)$id;
        $service = $this->serviceModel->findByIdAndStore($serviceId, (int)$store['id']);

        if (!$service) {
            set_flash('error', 'Layanan tidak ditemukan atau Anda tidak memiliki akses.');
            $this->redirect('store/services');
            return;
        }

        $this->render('store/services/edit', [
            'title' => 'Edit Layanan Laundry - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'service' => $service
        ], 'layouts/main');
    }

    /**
     * Update service
     */
    public function update(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/services/' . $id . '/edit');
            return;
        }

        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $serviceId = (int)$id;
        $service = $this->serviceModel->findByIdAndStore($serviceId, (int)$store['id']);
        if (!$service) {
            set_flash('error', 'Layanan tidak ditemukan atau akses ditolak.');
            $this->redirect('store/services');
            return;
        }

        $errors = $this->validate($request->all(), [
            'name' => 'required',
            'category' => 'required',
            'price' => 'required',
            'unit' => 'required',
            'estimated_duration' => 'required'
        ]);

        $price = (float)$request->get('price', 0);
        if ($price <= 0) {
            $errors['price'] = 'Harga layanan harus lebih besar dari 0.';
        }

        $validCategories = ['kiloan', 'satuan', 'express', 'special'];
        if (!in_array($request->get('category'), $validCategories)) {
            $errors['category'] = 'Kategori layanan tidak valid.';
        }

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('store/services/' . $id . '/edit');
            return;
        }

        $updateData = [
            'name' => trim($request->get('name')),
            'category' => $request->get('category'),
            'price' => $price,
            'unit' => trim($request->get('unit', 'kg')),
            'min_order' => (float)$request->get('min_order', 1),
            'estimated_duration' => trim($request->get('estimated_duration')),
            'description' => trim($request->get('description', '')),
            'icon' => trim($request->get('icon', 'bi-droplet')),
            'is_active' => (int)$request->get('is_active', 1)
        ];

        $oldPrice = (float)$service['price'];
        $this->serviceModel->updateService($serviceId, (int)$store['id'], $updateData);

        // Activity Notification: SERVICE_PRICE_CHANGED
        if (abs($oldPrice - $price) > 0.01) {
            try {
                $waNotif = new \App\Services\WhatsAppNotificationService();
                $waNotif->notifyActivity('SERVICE_PRICE_CHANGED', [
                    'nama_layanan' => $updateData['name'],
                    'harga_lama' => 'Rp ' . number_format($oldPrice, 0, ',', '.'),
                    'harga_baru' => 'Rp ' . number_format($price, 0, ',', '.'),
                    'unit' => $updateData['unit'],
                    'store_name' => $store['name'] ?? 'SmartLaundry'
                ], (int)$store['id'], [
                    'name' => $user['name'] ?? 'Owner Toko',
                    'role' => $user['role_name'] ?? ($user['role'] ?? 'owner_toko')
                ]);
            } catch (\Throwable $e) {}
        }

        set_flash('success', 'Perubahan layanan berhasil disimpan.');
        $this->redirect('store/services');
    }

    /**
     * Toggle service active status
     */
    public function toggle(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/services');
            return;
        }

        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $serviceId = (int)$id;
        $success = $this->serviceModel->toggleActive($serviceId, (int)$store['id']);

        if ($success) {
            set_flash('success', 'Status keaktifan layanan berhasil diubah.');
        } else {
            set_flash('error', 'Gagal mengubah status layanan.');
        }

        $this->redirect('store/services');
    }

    /**
     * Delete service with active order protection
     */
    public function delete(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/services');
            return;
        }

        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $serviceId = (int)$id;
        $service = $this->serviceModel->findByIdAndStore($serviceId, (int)$store['id']);
        if (!$service) {
            set_flash('error', 'Layanan tidak ditemukan.');
            $this->redirect('store/services');
            return;
        }

        // Check if used in active orders
        if ($this->serviceModel->isUsedInActiveOrders($serviceId)) {
            set_flash('warning', 'Layanan "' . e($service['name']) . '" tidak dapat dihapus permanen karena sedang digunakan pada pesanan yang belum selesai. Anda dapat menonaktifkannya agar tidak muncul lagi untuk order baru.');
            $this->redirect('store/services');
            return;
        }

        $deleted = $this->serviceModel->deleteService($serviceId, (int)$store['id']);
        if ($deleted) {
            set_flash('success', 'Layanan "' . e($service['name']) . '" berhasil dihapus dari katalog.');
        } else {
            set_flash('error', 'Gagal menghapus layanan.');
        }

        $this->redirect('store/services');
    }
}
