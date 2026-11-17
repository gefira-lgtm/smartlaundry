<?php
namespace App\Services;

use App\Models\Payment;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\Role;

/**
 * MetricsService — satu sumber kebenaran untuk semua metrik platform.
 *
 * Setiap method memanggil metode Model yang SAMA dengan yang digunakan
 * DashboardController::platform(), sehingga angka chatbot dan dashboard
 * selalu identik.
 */
class MetricsService {
    private Payment $paymentModel;
    private Order   $orderModel;
    private Store   $storeModel;
    private User    $userModel;

    public function __construct() {
        $this->paymentModel = new Payment();
        $this->orderModel   = new Order();
        $this->storeModel   = new Store();
        $this->userModel    = new User();
    }

    // ----------------------------------------------------------------
    // GMV
    // ----------------------------------------------------------------

    /**
     * Total GMV platform: jumlah pembayaran berstatus PAID di seluruh toko.
     * Definisi identik dengan DashboardController::platform() baris 39.
     */
    public function getPlatformGMV(): float {
        return $this->paymentModel->getTotalGMV();
    }

    // ----------------------------------------------------------------
    // Orders
    // ----------------------------------------------------------------

    /**
     * Total semua pesanan (seluruh status) di platform.
     * Identik dengan DashboardController::platform() baris 40.
     */
    public function getPlatformOrderCount(): int {
        return (int) $this->orderModel->count();
    }

    /**
     * 10 pesanan terbaru di platform.
     * Identik dengan DashboardController::platform() baris 57.
     */
    public function getPlatformRecentOrders(int $limit = 10): array {
        return $this->orderModel->getRecentOrders($limit);
    }

    // ----------------------------------------------------------------
    // Stores
    // ----------------------------------------------------------------

    /**
     * Jumlah toko yang sudah terverifikasi (is_verified = 1).
     * Identik dengan DashboardController::platform() baris 41.
     */
    public function getPlatformVerifiedStoreCount(): int {
        return (int) $this->storeModel->count(['is_verified' => 1]);
    }

    /**
     * Jumlah seluruh toko (terverifikasi maupun belum).
     * Identik dengan DashboardController::platform() baris 42.
     */
    public function getPlatformAllStoreCount(): int {
        return (int) $this->storeModel->count();
    }

    /**
     * Data lengkap semua toko berikut statistik pesanan & pendapatan.
     * Identik dengan DashboardController::platform() baris 54.
     */
    public function getPlatformStoreStats(): array {
        return $this->storeModel->getAllWithStats();
    }

    // ----------------------------------------------------------------
    // Customers
    // ----------------------------------------------------------------

    /**
     * Jumlah pengguna dengan role 'customer' (role_id = 6 by default).
     * Identik dengan DashboardController::platform() baris 44–48.
     */
    public function getPlatformCustomerCount(): int {
        $roleModel      = new Role();
        $customerRole   = $roleModel->findByName('customer');
        $customerRoleId = $customerRole ? (int)$customerRole['id'] : 6;
        return (int) $this->userModel->count(['role_id' => $customerRoleId]);
    }

    // ----------------------------------------------------------------
    // Trends
    // ----------------------------------------------------------------

    /**
     * Tren transaksi harian untuk n hari terakhir.
     * Identik dengan DashboardController::platform() baris 51.
     */
    public function getPlatformTransactionTrends(int $days = 7): array {
        return $this->paymentModel->getTransactionTrends($days);
    }

    // ----------------------------------------------------------------
    // Convenience: semua metrik ringkasan sekaligus
    // ----------------------------------------------------------------

    /**
     * Mengembalikan array berisi semua metrik ringkasan platform.
     * Cocok untuk digunakan chatbot maupun endpoint API.
     */
    public function getPlatformSummary(): array {
        return [
            'gmv_total'        => $this->getPlatformGMV(),
            'total_orders'     => $this->getPlatformOrderCount(),
            'verified_stores'  => $this->getPlatformVerifiedStoreCount(),
            'all_stores'       => $this->getPlatformAllStoreCount(),
            'total_customers'  => $this->getPlatformCustomerCount(),
        ];
    }
}

