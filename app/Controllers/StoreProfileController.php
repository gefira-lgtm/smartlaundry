<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Store;
use App\Models\Address;
use App\Models\User;

class StoreProfileController extends Controller {
    private Store $storeModel;
    private Address $addressModel;
    private User $userModel;

    public function __construct() {
        $this->storeModel = new Store();
        $this->addressModel = new Address();
        $this->userModel = new User();
    }

    /**
     * Show store profile edit page with Leaflet map and verification badge
     */
    public function profile(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], 'owner_toko');

        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $storeData = $this->storeModel->findWithAddress((int)$store['id']);

        $this->render('store/profile', [
            'title' => 'Pengaturan Profil Toko - ' . APP_NAME,
            'user' => $user,
            'store' => $storeData
        ], 'layouts/main');
    }

    /**
     * Update store profile, address, and upload logo/banner
     */
    public function updateProfile(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('store/profile');
            return;
        }

        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], 'owner_toko');
        if (!$store) {
            set_flash('error', 'Data toko tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $storeId = (int)$store['id'];

        $errors = $this->validate($request->all(), [
            'name' => 'required',
            'phone' => 'required',
            'operational_hours' => 'required',
            'full_address' => 'required'
        ]);

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('store/profile');
            return;
        }

        // Handle file uploads
        $uploadDir = ROOT_PATH . '/public/uploads/stores/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB

        $storeUpdate = [
            'name' => trim($request->get('name')),
            'description' => trim($request->get('description', '')),
            'phone' => trim($request->get('phone')),
            'operational_hours' => trim($request->get('operational_hours'))
        ];

        // 1. Logo Upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo'];
            if ($file['size'] > $maxFileSize) {
                set_flash('error', 'Ukuran file Logo terlalu besar (Maksimal 2 MB).');
                $this->redirect('store/profile');
                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedMimes)) {
                set_flash('error', 'Format file Logo tidak valid (Gunakan JPG, PNG, atau WEBP).');
                $this->redirect('store/profile');
                return;
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $logoName = 'logo_' . $storeId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $logoName)) {
                $storeUpdate['logo'] = 'uploads/stores/' . $logoName;
            }
        }

        // 2. Banner Upload
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['banner'];
            if ($file['size'] > $maxFileSize) {
                set_flash('error', 'Ukuran file Banner terlalu besar (Maksimal 2 MB).');
                $this->redirect('store/profile');
                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedMimes)) {
                set_flash('error', 'Format file Banner tidak valid (Gunakan JPG, PNG, atau WEBP).');
                $this->redirect('store/profile');
                return;
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $bannerName = 'banner_' . $storeId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $bannerName)) {
                $storeUpdate['banner'] = 'uploads/stores/' . $bannerName;
            }
        }

        // 3. Handle Store Address Update/Create
        $lat = $request->get('latitude') !== '' ? (float)$request->get('latitude') : -6.20000000;
        $lng = $request->get('longitude') !== '' ? (float)$request->get('longitude') : 106.81666600;

        $addressData = [
            'user_id' => (int)$user['id'],
            'label' => 'Outlet ' . $storeUpdate['name'],
            'recipient_name' => $storeUpdate['name'],
            'phone' => $storeUpdate['phone'],
            'full_address' => trim($request->get('full_address')),
            'rt' => trim($request->get('rt', '')),
            'rw' => trim($request->get('rw', '')),
            'kelurahan' => trim($request->get('kelurahan', '')),
            'kecamatan' => trim($request->get('kecamatan', '')),
            'kota' => trim($request->get('kota', 'Jakarta Selatan')),
            'provinsi' => trim($request->get('provinsi', 'DKI Jakarta')),
            'postal_code' => trim($request->get('postal_code', '')),
            'latitude' => $lat,
            'longitude' => $lng,
            'notes' => trim($request->get('notes', 'Lokasi Outlet Laundry')),
            'is_primary' => 1
        ];

        $currentAddressId = (int)($store['address_id'] ?? 0);

        if ($currentAddressId > 0 && $this->addressModel->find($currentAddressId)) {
            $this->addressModel->update($currentAddressId, $addressData);
        } else {
            $newAddrId = (int)$this->addressModel->create($addressData);
            if ($newAddrId) {
                $storeUpdate['address_id'] = $newAddrId;
            }
        }

        // Update Store
        $this->storeModel->updateProfile($storeId, $storeUpdate);

        // Activity Notification: STORE_PROFILE_UPDATED
        try {
            $waNotif = new \App\Services\WhatsAppNotificationService();
            $waNotif->notifyActivity('STORE_PROFILE_UPDATED', [
                'store_name' => $storeUpdate['name'],
                'change_detail' => 'Profil/jam operasional diperbarui: ' . ($storeUpdate['operational_hours'] ?? '-') . ', telepon: ' . $storeUpdate['phone']
            ], $storeId, [
                'name' => $user['name'] ?? 'Owner Toko',
                'role' => $user['role_name'] ?? ($user['role'] ?? 'owner_toko')
            ]);
        } catch (\Throwable $e) {}

        set_flash('success', 'Profil toko dan lokasi outlet berhasil diperbarui.');
        $this->redirect('store/profile');
    }
}
