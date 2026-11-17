<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Store;
use App\Models\Setting;

class StoreSettingsController extends Controller {
    private Store $storeModel;

    public function __construct() {
        $this->storeModel = new Store();
    }

    /**
     * Show store configuration page
     * GET /store/settings
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_toko', 'admin_toko'], true)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => '403 - Akses Ditolak'], 'layouts/main');
            return;
        }

        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store) {
            set_flash('error', 'Outlet toko Anda tidak ditemukan atau akun belum terdaftar pada toko manapun.');
            $this->redirect('dashboard/store');
            return;
        }

        $storeId = (int)$store['id'];
        $storeSettings = Setting::getStoreAll($storeId);

        // Operational hours default
        $operationalHours = $storeSettings['operational_hours'] ?? $store['operational_hours'] ?? '08:00 - 20:00 WIB';
        // Is open temporary default (1 = open, 0 = closed)
        $isOpenTemporary = isset($storeSettings['is_open_temporary']) ? (int)$storeSettings['is_open_temporary'] : (int)($store['is_active'] ?? 1);
        // Phone default
        $phone = $storeSettings['phone'] ?? $store['phone'] ?? '';
        // Return fee bearer default
        $returnFeeBearer = $storeSettings['return_fee_bearer_default'] ?? Setting::get('return_fee_bearer_default', 'CUSTOMER');

        $isReadOnly = ($user['role'] === 'admin_toko');

        $this->render('store/settings', [
            'title' => 'Pengaturan Outlet Toko - ' . app_name(),
            'user' => $user,
            'store' => $store,
            'isReadOnly' => $isReadOnly,
            'operationalHours' => $operationalHours,
            'isOpenTemporary' => $isOpenTemporary,
            'phone' => $phone,
            'returnFeeBearer' => $returnFeeBearer
        ], 'layouts/main');
    }

    /**
     * Update store settings (owner_toko only)
     * POST /store/settings
     */
    public function update(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || $user['role'] !== 'owner_toko') {
            http_response_code(403);
            set_flash('error', 'Akses Ditolak: Hanya Pemilik Toko (owner_toko) yang berhak mengubah pengaturan toko.');
            $this->redirect('store/settings');
            return;
        }

        $csrfToken = $request->get('_csrf_token');
        if (!verify_csrf_token($csrfToken)) {
            set_flash('error', 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa.');
            $this->redirect('store/settings');
            return;
        }

        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store) {
            set_flash('error', 'Toko tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        $storeId = (int)$store['id'];
        $userId = (int)$user['id'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Validate
        $post = $request->post();
        $opHours = trim((string)($post['operational_hours'] ?? '08:00 - 20:00 WIB'));
        $isOpen = !empty($post['is_open_temporary']) ? 1 : 0;
        $phone = trim((string)($post['phone'] ?? ''));
        $bearer = strtoupper(trim((string)($post['return_fee_bearer_default'] ?? 'CUSTOMER')));

        if (!in_array($bearer, ['CUSTOMER', 'STORE', 'FREE'], true)) {
            $bearer = 'CUSTOMER';
        }

        if (empty($opHours)) {
            $opHours = '08:00 - 20:00 WIB';
        }

        if (empty($phone)) {
            set_flash('error', 'Nomor telepon/WhatsApp outlet toko tidak boleh kosong.');
            $this->redirect('store/settings');
            return;
        }

        // Save to store_settings
        Setting::setStore($storeId, 'operational_hours', $opHours, $userId, $ip);
        Setting::setStore($storeId, 'is_open_temporary', $isOpen, $userId, $ip);
        Setting::setStore($storeId, 'phone', $phone, $userId, $ip);
        Setting::setStore($storeId, 'return_fee_bearer_default', $bearer, $userId, $ip);

        // Also synchronize core columns on stores table
        $this->storeModel->update($storeId, [
            'operational_hours' => $opHours,
            'phone' => $phone,
            'is_active' => $isOpen
        ]);

        $statusText = $isOpen ? 'BUKA (Menerima Pesanan)' : 'TUTUP SEMENTARA (Menolak Pesanan Baru)';
        set_flash('success', "Pengaturan outlet #{$store['name']} berhasil diperbarui. Status operasional: {$statusText}.");
        $this->redirect('store/settings');
    }
}
