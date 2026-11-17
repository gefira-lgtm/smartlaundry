<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Store;
use App\Models\LaundryService;
use App\Models\Review;

class StoreDetailController extends Controller {
    private Store $storeModel;
    private LaundryService $serviceModel;
    private Review $reviewModel;

    public function __construct() {
        $this->storeModel = new Store();
        $this->serviceModel = new LaundryService();
        $this->reviewModel = new Review();
    }

    /**
     * Show public store detail page with profile, services, and customer reviews
     * GET /store/{id}/detail
     */
    public function show(Request $request, Response $response, string $id): void {
        $storeId = (int)$id;
        $store = $this->storeModel->findWithAddress($storeId);

        if (!$store || empty($store['is_active'])) {
            set_flash('error', 'Outlet laundry tidak ditemukan atau sedang tidak aktif.');
            $this->redirect('stores');
            return;
        }

        $services = $this->serviceModel->getByStoreId($storeId);
        $reviews = $this->reviewModel->getByStoreId($storeId, 25);

        // Calculate rating breakdown distribution
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($reviews as $rev) {
            $r = (int)$rev['rating'];
            if (isset($distribution[$r])) {
                $distribution[$r]++;
            }
        }

        $this->render('store/detail', [
            'title' => e($store['name']) . ' - Detail & Ulasan | ' . APP_NAME,
            'store' => $store,
            'services' => $services,
            'reviews' => $reviews,
            'distribution' => $distribution,
            'user' => auth_user()
        ], 'layouts/main');
    }
}
