<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Address;
use App\Models\User;

class AddressController extends Controller {
    private Address $addressModel;
    private User $userModel;

    public function __construct() {
        $this->addressModel = new Address();
        $this->userModel = new User();
    }

    /**
     * List all customer saved addresses
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        $userId = (int)$user['id'];

        $addresses = $this->addressModel->findByUser($userId);
        $completeness = $this->userModel->isProfileComplete($userId);

        $this->render('customer/addresses/index', [
            'title' => 'Buku Alamat Saya - ' . APP_NAME,
            'user' => $user,
            'addresses' => $addresses,
            'completeness' => $completeness
        ], 'layouts/main');
    }

    /**
     * Show form to add new address
     */
    public function create(Request $request, Response $response): void {
        $user = auth_user();

        $this->render('customer/addresses/create', [
            'title' => 'Tambah Alamat Baru - ' . APP_NAME,
            'user' => $user
        ], 'layouts/main');
    }

    /**
     * Store newly created address
     */
    public function store(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('customer/addresses/create');
            return;
        }

        $user = auth_user();
        $userId = (int)$user['id'];

        $errors = $this->validate($request->all(), [
            'label' => 'required',
            'recipient_name' => 'required',
            'phone' => 'required',
            'full_address' => 'required'
        ]);

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('customer/addresses/create');
            return;
        }

        $existingAddresses = $this->addressModel->findByUser($userId);
        $isFirst = empty($existingAddresses);
        $isPrimary = $isFirst || (bool)$request->get('is_primary', 0);

        $lat = $request->get('latitude') !== '' ? (float)$request->get('latitude') : -6.20000000;
        $lng = $request->get('longitude') !== '' ? (float)$request->get('longitude') : 106.81666600;

        $addressData = [
            'user_id' => $userId,
            'label' => trim($request->get('label', 'Rumah')),
            'recipient_name' => trim($request->get('recipient_name')),
            'phone' => trim($request->get('phone')),
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
            'notes' => trim($request->get('notes', '')),
            'is_primary' => $isPrimary ? 1 : 0
        ];

        $newId = (int)$this->addressModel->create($addressData);

        if ($newId && $isPrimary) {
            $this->addressModel->setPrimary($newId, $userId);
        }

        set_flash('success', 'Alamat baru berhasil ditambahkan ke buku alamat.');
        $this->redirect('customer/addresses');
    }

    /**
     * Show edit form for existing address
     */
    public function edit(Request $request, Response $response, string $id): void {
        $user = auth_user();
        $userId = (int)$user['id'];
        $addressId = (int)$id;

        $address = $this->addressModel->findByIdAndUser($addressId, $userId);

        if (!$address) {
            set_flash('error', 'Alamat tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.');
            $this->redirect('customer/addresses');
            return;
        }

        $this->render('customer/addresses/edit', [
            'title' => 'Edit Alamat: ' . $address['label'] . ' - ' . APP_NAME,
            'user' => $user,
            'address' => $address
        ], 'layouts/main');
    }

    /**
     * Update existing address
     */
    public function update(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('customer/addresses/' . $id . '/edit');
            return;
        }

        $user = auth_user();
        $userId = (int)$user['id'];
        $addressId = (int)$id;

        $existing = $this->addressModel->findByIdAndUser($addressId, $userId);
        if (!$existing) {
            set_flash('error', 'Alamat tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.');
            $this->redirect('customer/addresses');
            return;
        }

        $errors = $this->validate($request->all(), [
            'label' => 'required',
            'recipient_name' => 'required',
            'phone' => 'required',
            'full_address' => 'required'
        ]);

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('customer/addresses/' . $id . '/edit');
            return;
        }

        $lat = $request->get('latitude') !== '' ? (float)$request->get('latitude') : (float)($existing['latitude'] ?? -6.20000000);
        $lng = $request->get('longitude') !== '' ? (float)$request->get('longitude') : (float)($existing['longitude'] ?? 106.81666600);
        $isPrimary = (bool)$request->get('is_primary', 0);

        $updateData = [
            'label' => trim($request->get('label', 'Rumah')),
            'recipient_name' => trim($request->get('recipient_name')),
            'phone' => trim($request->get('phone')),
            'full_address' => trim($request->get('full_address')),
            'rt' => trim($request->get('rt', '')),
            'rw' => trim($request->get('rw', '')),
            'kelurahan' => trim($request->get('kelurahan', '')),
            'kecamatan' => trim($request->get('kecamatan', '')),
            'kota' => trim($request->get('kota', '')),
            'provinsi' => trim($request->get('provinsi', '')),
            'postal_code' => trim($request->get('postal_code', '')),
            'latitude' => $lat,
            'longitude' => $lng,
            'notes' => trim($request->get('notes', ''))
        ];

        $this->addressModel->update($addressId, $updateData);

        if ($isPrimary) {
            $this->addressModel->setPrimary($addressId, $userId);
        }

        set_flash('success', 'Data alamat berhasil diperbarui.');
        $this->redirect('customer/addresses');
    }

    /**
     * Delete an address safely
     */
    public function delete(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('customer/addresses');
            return;
        }

        $user = auth_user();
        $userId = (int)$user['id'];
        $addressId = (int)$id;

        $address = $this->addressModel->findByIdAndUser($addressId, $userId);
        if (!$address) {
            set_flash('error', 'Alamat tidak ditemukan.');
            $this->redirect('customer/addresses');
            return;
        }

        if ($this->addressModel->isUsedInActiveOrder($addressId)) {
            set_flash('error', 'Alamat ini tidak dapat dihapus karena masih digunakan pada pesanan yang sedang berjalan.');
            $this->redirect('customer/addresses');
            return;
        }

        $this->addressModel->deleteAddress($addressId, $userId);
        set_flash('success', 'Alamat berhasil dihapus.');
        $this->redirect('customer/addresses');
    }

    /**
     * Set address as primary
     */
    public function setPrimary(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('customer/addresses');
            return;
        }

        $user = auth_user();
        $userId = (int)$user['id'];
        $addressId = (int)$id;

        $success = $this->addressModel->setPrimary($addressId, $userId);
        if ($success) {
            set_flash('success', 'Alamat utama penjemputan & pengantaran berhasil diperbarui.');
        } else {
            set_flash('error', 'Gagal menetapkan alamat utama. Pastikan alamat valid.');
        }

        $this->redirect('customer/addresses');
    }

    /**
     * Customer Profile & Completeness Page
     */
    public function profile(Request $request, Response $response): void {
        $user = auth_user();
        $userId = (int)$user['id'];

        $userData = $this->userModel->findWithRole($userId);
        $completeness = $this->userModel->isProfileComplete($userId);
        $addresses = $this->addressModel->findByUser($userId);

        $this->render('customer/profile', [
            'title' => 'Profil Saya - ' . APP_NAME,
            'user' => $userData,
            'completeness' => $completeness,
            'addresses' => $addresses
        ], 'layouts/main');
    }

    /**
     * Update Customer Profile
     */
    public function updateProfile(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('customer/profile');
            return;
        }

        $user = auth_user();
        $userId = (int)$user['id'];

        $errors = $this->validate($request->all(), [
            'name' => 'required',
            'phone' => 'required'
        ]);

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('customer/profile');
            return;
        }

        $updateData = [
            'name' => trim($request->get('name')),
            'phone' => trim($request->get('phone'))
        ];

        $newPassword = $request->get('password');
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 6) {
                set_flash('error', 'Kata sandi baru minimal harus 6 karakter.');
                $this->redirect('customer/profile');
                return;
            }
            $updateData['password'] = $newPassword;
        }

        $this->userModel->updateProfile($userId, $updateData);

        // Update session state
        $_SESSION['user']['name'] = $updateData['name'];
        $_SESSION['user']['phone'] = $updateData['phone'];

        set_flash('success', 'Profil dan informasi kontak Anda berhasil diperbarui.');
        $this->redirect('customer/profile');
    }
}
