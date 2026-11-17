<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Models\Store;
use App\Models\LaundryService;
use App\Models\Address;
use App\Models\User;

class OrderController extends Controller {
    private Order $orderModel;
    private Store $storeModel;
    private LaundryService $laundryServiceModel;
    private Address $addressModel;
    private User $userModel;

    public function __construct() {
        $this->orderModel = new Order();
        $this->storeModel = new Store();
        $this->laundryServiceModel = new LaundryService();
        $this->addressModel = new Address();
        $this->userModel = new User();
    }

    /**
     * Show multi-step checkout form
     */
    public function create(Request $request, Response $response): void {
        $user = auth_user();
        $customerId = (int)$user['id'];

        // 1. Check if customer has addresses. If none, force add address first.
        $addresses = $this->addressModel->findByUser($customerId);
        if (empty($addresses)) {
            set_flash('warning', 'Silakan simpan minimal 1 alamat penjemputan terlebih dahulu sebelum membuat pesanan baru.');
            $this->redirect('customer/addresses/create');
            return;
        }

        // 2. Fetch all active and verified stores
        $stores = $this->storeModel->where(['is_active' => 1], 'rating_avg DESC, name ASC');

        if (empty($stores)) {
            set_flash('error', 'Saat ini belum ada toko laundry mitra yang aktif. Silakan hubungi administrator.');
            $this->redirect('dashboard/customer');
            return;
        }

        // 3. Determine selected store (from query param or session draft or first store)
        $requestedStoreId = (int)$request->get('store_id', 0);
        $draft = $_SESSION['order_draft'] ?? null;

        if ($requestedStoreId > 0) {
            $selectedStoreId = $requestedStoreId;
        } elseif (!empty($draft['store_id'])) {
            $selectedStoreId = (int)$draft['store_id'];
        } else {
            $selectedStoreId = (int)$stores[0]['id'];
        }

        $selectedStore = $this->storeModel->find($selectedStoreId);
        if (!$selectedStore || empty($selectedStore['is_active'])) {
            $selectedStore = $stores[0];
            $selectedStoreId = (int)$selectedStore['id'];
        }

        // 4. Fetch services for the selected store
        $services = $this->laundryServiceModel->getByStoreId($selectedStoreId);

        // 5. Time slots definition
        $timeSlots = [
            '08:00 - 10:00 WIB',
            '10:00 - 12:00 WIB',
            '13:00 - 15:00 WIB',
            '15:00 - 17:00 WIB',
            '17:00 - 19:00 WIB'
        ];

        $this->render('order/create', [
            'title' => 'Buat Pesanan Laundry Baru - ' . APP_NAME,
            'user' => $user,
            'stores' => $stores,
            'selectedStore' => $selectedStore,
            'services' => $services,
            'addresses' => $addresses,
            'timeSlots' => $timeSlots,
            'draft' => $draft
        ], 'layouts/main');
    }

    /**
     * Process checkout submission
     */
    public function store(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token keamanan CSRF tidak valid atau telah kedaluwarsa.');
            $this->redirect('order/create');
            return;
        }

        $user = auth_user();
        $customerId = (int)$user['id'];

        // 1. Validate Store
        $storeId = (int)$request->get('store_id');
        $store = $this->storeModel->find($storeId);
        if (!$store || empty($store['is_active'])) {
            set_flash('error', 'Toko laundry yang dipilih tidak valid atau sedang offline.');
            $this->redirect('order/create');
            return;
        }

        // Check store temporary open/close setting
        $isOpen = (int)\App\Models\Setting::getStore($storeId, 'is_open_temporary', 1);
        if (!$isOpen) {
            set_flash('error', 'Toko ' . e($store['name']) . ' sedang tutup sementara dan tidak dapat menerima pesanan baru.');
            $this->redirect('order/create?store_id=' . $storeId);
            return;
        }

        // 2. Validate Selected Services
        $rawServices = $request->get('services', []);
        $items = [];
        $estimatedSubtotal = 0.00;

        if (is_array($rawServices)) {
            foreach ($rawServices as $serviceId => $itemData) {
                $qty = isset($itemData['quantity']) ? (float)$itemData['quantity'] : 0;
                $isSelected = !empty($itemData['selected']);

                if ($isSelected && $qty > 0) {
                    $service = $this->laundryServiceModel->findActiveByIdAndStore((int)$serviceId, $storeId);
                    if ($service) {
                        $unitPrice = (float)$service['price'];
                        $subtotal = $qty * $unitPrice;
                        $estimatedSubtotal += $subtotal;

                        $items[] = [
                            'service_id' => (int)$service['id'],
                            'quantity' => $qty,
                            'unit_price' => $unitPrice,
                            'subtotal' => $subtotal,
                            'item_notes' => trim($itemData['notes'] ?? '')
                        ];
                    }
                }
            }
        }

        if (empty($items)) {
            set_flash('error', 'Silakan pilih minimal 1 layanan laundry dan tentukan estimasi jumlah / beratnya.');
            $this->redirect('order/create?store_id=' . $storeId);
            return;
        }

        // 3. Validate Addresses
        $pickupAddressId = (int)$request->get('pickup_address_id');
        $pickupAddress = $this->addressModel->findByIdAndUser($pickupAddressId, $customerId);
        if (!$pickupAddress) {
            set_flash('error', 'Alamat penjemputan tidak valid.');
            $this->redirect('order/create?store_id=' . $storeId);
            return;
        }

        $sameAddress = (bool)$request->get('same_delivery_address', 1);
        if ($sameAddress) {
            $deliveryAddressId = $pickupAddressId;
        } else {
            $deliveryAddressId = (int)$request->get('delivery_address_id', $pickupAddressId);
            $deliveryAddress = $this->addressModel->findByIdAndUser($deliveryAddressId, $customerId);
            if (!$deliveryAddress) {
                $deliveryAddressId = $pickupAddressId;
            }
        }

        // 4. Validate Scheduling
        $pickupDate = trim($request->get('pickup_date', date('Y-m-d')));
        $pickupSlot = trim($request->get('pickup_slot', '08:00 - 10:00 WIB'));

        if (empty($pickupDate) || strtotime($pickupDate) < strtotime(date('Y-m-d'))) {
            set_flash('error', 'Tanggal penjemputan tidak boleh berada di masa lalu.');
            $this->redirect('order/create?store_id=' . $storeId);
            return;
        }

        // Extract hour from slot (e.g. "08:00 - 10:00 WIB" -> "08:00:00")
        $startHour = '08:00:00';
        if (preg_match('/(\d{2}:\d{2})/', $pickupSlot, $m)) {
            $startHour = $m[1] . ':00';
        }
        $scheduledPickupAt = $pickupDate . ' ' . $startHour;

        // 5. Payment Method & Delivery Fee
        $paymentMethod = $request->get('payment_method', 'ONLINE_QRIS');
        if (!in_array($paymentMethod, ['CASH', 'ONLINE_QRIS'], true)) {
            $paymentMethod = 'ONLINE_QRIS';
        }

        if ($paymentMethod === 'CASH' && !\App\Models\Setting::get('payment_cash_enabled', true)) {
            set_flash('error', 'Metode pembayaran tunai (Cash) sedang dinonaktifkan oleh administrator. Silakan pilih metode pembayaran QRIS.');
            $this->redirect('order/create?store_id=' . $storeId);
            return;
        }

        $deliveryFee = 0.00; // Free pickup & delivery promotion in marketplace
        $estimatedTotalPrice = $estimatedSubtotal + $deliveryFee;

        // 6. Build Order Payload
        $orderData = [
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'pickup_address_id' => $pickupAddressId,
            'delivery_address_id' => $deliveryAddressId,
            'status' => 'MENUNGGU_KONFIRMASI',
            'payment_method' => $paymentMethod,
            'total_price' => $estimatedTotalPrice,
            'delivery_fee' => $deliveryFee,
            'notes' => trim($request->get('order_notes', '')) . (!empty($pickupSlot) ? " (Slot: {$pickupSlot})" : ''),
            'scheduled_pickup_at' => $scheduledPickupAt
        ];

        // 7. Atomic Insert via Model Transaction
        $orderId = $this->orderModel->createWithItems($orderData, $items);

        if ($orderId) {
            // Clear reorder draft session if exists
            unset($_SESSION['order_draft']);

            // In-app notification to customer
            $notifModel = new \App\Models\Notification();
            $notifModel->createNotification(
                $customerId,
                'Pesanan Berhasil Dibuat! 🧺',
                "Pesanan baru Anda telah kami teruskan ke {$store['name']}. Menunggu konfirmasi toko.",
                'order',
                url('order/' . $orderId . '/confirmation'),
                $orderId
            );

            // In-app notification to store owner
            if (!empty($store['owner_id'])) {
                $notifModel->createNotification(
                    (int)$store['owner_id'],
                    'Pesanan Baru Masuk! 🔔',
                    "Toko {$store['name']} menerima pesanan baru dari {$user['name']}. Segera konfirmasi pesanan.",
                    'order',
                    url('dashboard/store'),
                    $orderId
                );
            }

            // WhatsApp Notification Simulation
            $waService = new \App\Services\WhatsAppNotificationService();
            $orderCode = 'ORD-' . str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
            $waService->notifyOrderCreated(
                $user['phone'] ?? '081234567890',
                $user['name'] ?? 'Pelanggan',
                $orderCode,
                $store['name'],
                $orderId,
                (int)$user['id']
            );

            // WhatsApp Notification to Store Owner
            $waService->notifyStoreNewOrder(
                (int)$storeId,
                $orderCode,
                $user['name'] ?? 'Pelanggan',
                (int)$orderId
            );

            set_flash('success', 'Pesanan laundry Anda berhasil dibuat! Toko akan segera mengonfirmasi dan menugaskan kurir.');
            $this->redirect('order/' . $orderId . '/confirmation');
        } else {
            set_flash('error', 'Gagal memproses pesanan baru. Silakan coba kembali.');
            $this->redirect('order/create?store_id=' . $storeId);
        }
    }

    /**
     * Show Order Confirmation Success Page
     */
    public function confirmation(Request $request, Response $response, string $orderId): void {
        $user = auth_user();
        $customerId = (int)$user['id'];

        $order = $this->orderModel->findWithDetails((int)$orderId);

        if (!$order || (int)$order['customer_id'] !== $customerId) {
            set_flash('error', 'Pesanan tidak ditemukan atau Anda tidak memiliki hak akses.');
            $this->redirect('dashboard/customer');
            return;
        }

        $this->render('order/confirmation', [
            'title' => 'Konfirmasi Pesanan #' . $order['order_code'] . ' - ' . APP_NAME,
            'user' => $user,
            'order' => $order
        ], 'layouts/main');
    }
}
