<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Courier;
use App\Models\User;
use App\Models\Store;

class TrackingController extends Controller {
    private Order $orderModel;
    private Courier $courierModel;
    private User $userModel;
    private Store $storeModel;

    public function __construct() {
        $this->orderModel = new Order();
        $this->courierModel = new Courier();
        $this->userModel = new User();
        $this->storeModel = new Store();
    }

    /**
     * JSON Endpoint: Get real-time courier GPS coordinates and destination for an order
     * GET /order/{id}/courier-location
     */
    public function getCourierLocation(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $id = (int)$orderId;
        $order = $this->orderModel->findWithDetails($id);
        if (!$order) {
            $response->json(['success' => false, 'message' => 'Pesanan tidak ditemukan'], 404);
            return;
        }

        $userId = (int)$user['id'];
        $userRole = $user['role'] ?? '';

        // Authorization check: customer owner, assigned courier, store owner/admin, platform admin
        $isCustomerOwner = ($userRole === 'customer' && (int)$order['customer_id'] === $userId);
        $isAssignedCourier = ($userRole === 'kurir' && (int)($order['courier_id'] ?? 0) === $userId);
        $isPlatformAdmin = in_array($userRole, ['owner_platform', 'admin_owner']);
        
        $isStoreStaff = false;
        if (in_array($userRole, ['owner_toko', 'admin_toko'])) {
            $store = $this->userModel->getStore($userId);
            if ($store && (int)$store['id'] === (int)$order['store_id']) {
                $isStoreStaff = true;
            }
        }

        if (!$isCustomerOwner && !$isAssignedCourier && !$isPlatformAdmin && !$isStoreStaff) {
            $response->json(['success' => false, 'message' => 'Akses ditolak: Anda tidak berhak melihat pelacakan order ini.'], 403);
            return;
        }

        $courierUserId = (int)($order['courier_id'] ?? 0);
        $courierData = null;

        if ($courierUserId > 0) {
            $courier = $this->courierModel->findByUserId($courierUserId);
            $latestLocation = $this->courierModel->getLatestOrderLocation($id);

            $lat = $latestLocation ? (float)$latestLocation['latitude'] : (float)($courier['current_lat'] ?? -6.20000000);
            $lng = $latestLocation ? (float)$latestLocation['longitude'] : (float)($courier['current_lng'] ?? 106.81666600);

            $courierData = [
                'id' => (int)($courier['id'] ?? 0),
                'user_id' => $courierUserId,
                'name' => $courier['courier_name'] ?? 'Kurir SmartLaundry',
                'phone' => $courier['courier_phone'] ?? '',
                'avatar' => $courier['avatar'] ?? 'avatar_default.png',
                'vehicle_type' => $courier['vehicle_type'] ?? 'Motor Operasional',
                'license_plate' => $courier['license_plate'] ?? 'B 1234 JKT',
                'status' => $courier['status'] ?? 'TERSEDIA',
                'latitude' => $lat,
                'longitude' => $lng,
                'updated_at' => $latestLocation['recorded_at'] ?? $courier['last_location_updated_at'] ?? date('Y-m-d H:i:s')
            ];
        }

        // Determine destination coordinates based on order status phase
        $status = $order['status'];
        $destination = [
            'label' => 'Alamat Jemput / Antar',
            'recipient' => $order['customer_name'] ?? 'Customer',
            'phone' => $order['customer_phone'] ?? '',
            'address' => $order['pickup_address'] ?? 'Alamat Pelanggan',
            'latitude' => (float)($order['pickup_lat'] ?? -6.20888800),
            'longitude' => (float)($order['pickup_lng'] ?? 106.82888800)
        ];

        // If in delivery phase, point to delivery address
        if (in_array($status, ['SIAP_DIANTAR', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR'])) {
            $destination['label'] = 'Alamat Pengantaran Cucian Bersih';
            $destination['address'] = $order['delivery_address'] ?? $order['pickup_address'];
            $destination['latitude'] = (float)($order['delivery_lat'] ?? $order['pickup_lat'] ?? -6.20888800);
            $destination['longitude'] = (float)($order['delivery_lng'] ?? $order['pickup_lng'] ?? 106.82888800);
        }

        // Store location
        $storeData = [
            'name' => $order['store_name'] ?? 'Outlet Laundry',
            'phone' => $order['store_phone'] ?? '',
            'address' => $order['store_address'] ?? '',
            'latitude' => (float)($order['store_lat'] ?? -6.19500000),
            'longitude' => (float)($order['store_lng'] ?? 106.82000000)
        ];

        $response->json([
            'success' => true,
            'order_id' => $id,
            'order_code' => $order['order_code'] ?? '',
            'order_status' => $status,
            'courier' => $courierData,
            'destination' => $destination,
            'store' => $storeData,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * POST Endpoint: Courier updates GPS location
     * POST /courier/update-location
     */
    public function updateCourierLocation(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || ($user['role'] ?? '') !== 'kurir') {
            $response->json(['success' => false, 'message' => 'Hanya kurir yang dapat memperbarui lokasi GPS.'], 403);
            return;
        }

        $courier = $this->courierModel->findByUserId((int)$user['id']);
        if (!$courier) {
            $response->json(['success' => false, 'message' => 'Profil kurir tidak ditemukan.'], 404);
            return;
        }

        $courierId = (int)$courier['id'];
        $orderId = (int)$request->get('order_id', 0);

        $lat = $request->get('latitude');
        $lng = $request->get('longitude');

        // If coords not provided (or simulation button pressed), create realistic slight step offset
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            $currentLat = (float)($courier['current_lat'] ?: -6.20000000);
            $currentLng = (float)($courier['current_lng'] ?: 106.81666600);

            // If orderId is given, take a step towards destination
            if ($orderId > 0) {
                $order = $this->orderModel->findWithDetails($orderId);
                if ($order) {
                    $targetLat = (float)($order['pickup_lat'] ?? -6.20888800);
                    $targetLng = (float)($order['pickup_lng'] ?? 106.82888800);

                    // Step 20% closer to target + tiny jitter
                    $dLat = ($targetLat - $currentLat) * 0.25 + (mt_rand(-5, 5) / 100000.0);
                    $dLng = ($targetLng - $currentLng) * 0.25 + (mt_rand(-5, 5) / 100000.0);

                    $lat = $currentLat + $dLat;
                    $lng = $currentLng + $dLng;
                }
            }

            if ($lat === null || $lat === '') {
                $lat = $currentLat + (mt_rand(-20, 20) / 10000.0);
                $lng = $currentLng + (mt_rand(-20, 20) / 10000.0);
            }
        } else {
            $lat = (float)$lat;
            $lng = (float)$lng;
        }

        $this->courierModel->updateLocation($courierId, (float)$lat, (float)$lng, $orderId > 0 ? $orderId : null);

        // If request is from browser form POST (redirect) vs fetch JSON
        if ($request->isAjax() || $request->getHeader('Accept') === 'application/json' || str_contains($request->getHeader('Content-Type') ?? '', 'json')) {
            $response->json([
                'success' => true,
                'message' => 'Koordinat GPS kurir berhasil diperbarui.',
                'latitude' => (float)$lat,
                'longitude' => (float)$lng,
                'updated_at' => date('H:i:s')
            ]);
            return;
        }

        set_flash('success', 'Lokasi GPS Anda berhasil diperbarui.');
        $this->redirect('dashboard/courier');
    }

    /**
     * JSON Endpoint: Get live locations of all active couriers for Store Dashboard mini-map
     * GET /store/couriers/live-locations
     */
    public function getStoreCourierLocations(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $userId = (int)$user['id'];
        $store = $this->userModel->getStore($userId);

        if (!$store) {
            $response->json(['success' => false, 'message' => 'Data toko tidak ditemukan.'], 404);
            return;
        }

        $storeId = (int)$store['id'];
        $couriers = $this->courierModel->getActiveCouriersWithLocations($storeId);

        $response->json([
            'success' => true,
            'store_id' => $storeId,
            'store_name' => $store['name'],
            'couriers' => $couriers,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}
