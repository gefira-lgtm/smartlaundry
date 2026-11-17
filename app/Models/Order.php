<?php
namespace App\Models;

use App\Core\Model;

class Order extends Model {
    protected string $table = 'orders';
    protected string $primaryKey = 'id';

    /**
     * Get recent orders across the entire platform with joined metadata
     */
    public function getRecentOrders(int $limit = 10): array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                       s.name AS store_name,
                       c.name AS courier_name,
                       p.status AS payment_status, p.method AS payment_method_detail,
                       (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS service_names
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                JOIN `stores` s ON o.store_id = s.id
                LEFT JOIN `users` c ON o.courier_id = c.id
                LEFT JOIN `payments` p ON o.id = p.order_id
                ORDER BY o.id DESC
                LIMIT {$limit}";
        return $this->query($sql);
    }

    /**
     * Get orders for a specific store with optional status filtering
     */
    public function getStoreOrders(int $storeId, ?string $statusFilter = null, int $limit = 30): array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.phone AS customer_phone,
                       a.full_address AS customer_address,
                       c.name AS courier_name,
                       p.status AS payment_status, p.amount AS payment_amount,
                       ld.weight_actual, ld.pieces_count,
                       (SELECT GROUP_CONCAT(CONCAT(ls.name, ' (', oi.quantity, ' ', ls.unit, ')') SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS item_summary
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                JOIN `addresses` a ON o.pickup_address_id = a.id
                LEFT JOIN `users` c ON o.courier_id = c.id
                LEFT JOIN `payments` p ON o.id = p.order_id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE o.store_id = :store_id";

        $params = ['store_id' => $storeId];

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            if ($statusFilter === 'active') {
                $sql .= " AND o.status NOT IN ('SELESAI', 'DIBATALKAN', 'PEMBAYARAN_KADALUARSA')";
            } elseif ($statusFilter === 'completed') {
                $sql .= " AND o.status = 'SELESAI'";
            } else {
                $sql .= " AND o.status = :status_filter";
                $params['status_filter'] = $statusFilter;
            }
        }

        $sql .= " ORDER BY o.id DESC LIMIT {$limit}";
        return $this->query($sql, $params);
    }

    /**
     * Get pending incoming orders for store (status: MENUNGGU_KONFIRMASI)
     */
    public function getStorePendingOrders(int $storeId): array {
        return $this->getStoreOrders($storeId, 'MENUNGGU_KONFIRMASI', 20);
    }

    /**
     * Get weighing queue for Store Admin (orders arrived at store or picked up)
     */
    public function getStoreWeighingQueue(int $storeId): array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.phone AS customer_phone,
                       a.full_address AS customer_address,
                       c.name AS courier_name,
                       ld.weight_actual, ld.pieces_count, ld.notes_from_store,
                       (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS service_names
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                JOIN `addresses` a ON o.pickup_address_id = a.id
                LEFT JOIN `users` c ON o.courier_id = c.id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE o.store_id = :store_id 
                  AND o.status IN ('LAUNDRY_DIJEMPUT', 'SAMPAI_DI_TOKO', 'SEDANG_DITIMBANG')
                ORDER BY o.id ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Get payment confirmation queue for Store Admin (orders awaiting payment)
     */
    public function getStorePaymentQueue(int $storeId): array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.phone AS customer_phone,
                       p.id AS payment_id, p.payment_code, p.amount AS payment_amount, 
                       p.status AS payment_status, p.method AS payment_method_type,
                       p.expired_at,
                       ld.weight_actual
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                JOIN `payments` p ON o.id = p.order_id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE o.store_id = :store_id 
                  AND o.status = 'MENUNGGU_PEMBAYARAN'
                ORDER BY o.id ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Get active washing/processing queue for Store Admin
     */
    public function getStoreWashingQueue(int $storeId): array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.phone AS customer_phone,
                       ld.weight_actual, ld.pieces_count, ld.notes_from_store,
                       p.status AS payment_status,
                       (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS service_names
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                LEFT JOIN `payments` p ON o.id = p.order_id
                WHERE o.store_id = :store_id 
                  AND o.status IN ('PEMBAYARAN_BERHASIL', 'SEDANG_DICUCI', 'SEDANG_DIPROSES', 'SIAP_DIANTAR')
                ORDER BY o.id ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Find full single order details
     */
    public function findWithDetails(int $orderId): ?array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.email AS customer_email, 
                       COALESCE(NULLIF(TRIM(u.phone), ''), NULLIF(TRIM(pa.phone), ''), '') AS customer_phone,
                       s.name AS store_name, s.phone AS store_phone,
                       pa.full_address AS pickup_address_full, pa.latitude AS pickup_lat, pa.longitude AS pickup_lng,
                       pa.phone AS pickup_phone, pa.recipient_name AS pickup_recipient_name,
                       c.name AS courier_name, c.phone AS courier_phone,
                       p.id AS payment_id, p.payment_code, p.amount AS payment_amount, p.status AS payment_status, 
                       p.method AS payment_method_type, p.expired_at AS payment_expired_at, p.paid_at,
                       ld.weight_actual, ld.pieces_count, ld.notes_from_store, ld.before_photo, ld.after_photo
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                JOIN `stores` s ON o.store_id = s.id
                LEFT JOIN `addresses` pa ON o.pickup_address_id = pa.id
                LEFT JOIN `users` c ON o.courier_id = c.id
                LEFT JOIN `payments` p ON o.id = p.order_id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE o.id = :order_id
                LIMIT 1";
        $order = $this->firstQuery($sql, ['order_id' => $orderId]);
        if (!$order) return null;

        // Fetch order items
        $sqlItems = "SELECT oi.*, ls.name AS service_name, ls.category, ls.unit 
                     FROM `order_items` oi
                     JOIN `laundry_services` ls ON oi.service_id = ls.id
                     WHERE oi.order_id = :order_id";
        $order['items'] = $this->query($sqlItems, ['order_id' => $orderId]);

        return $order;
    }

    /**
     * Update order status and log to order_status_histories
     */
    public function updateStatus(int $orderId, string $newStatus, int $changedByUserId, ?string $notes = null): bool {
        $order = $this->find($orderId);
        if (!$order) return false;

        $oldStatus = $order['status'];
        if ($oldStatus === $newStatus) return true;

        $this->beginTransaction();
        try {
            // Update order status
            $this->update($orderId, [
                'status' => $newStatus,
                'completed_at' => ($newStatus === 'SELESAI') ? date('Y-m-d H:i:s') : $order['completed_at']
            ]);

            // Insert into order_status_histories
            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => $newStatus,
                'changed_by' => $changedByUserId,
                'notes' => $notes ?? "Status pesanan diubah dari {$oldStatus} ke {$newStatus}"
            ]);

            $this->commit();

            // Automatic WhatsApp notification for customer on order status transition
            try {
                $waService = new \App\Services\WhatsAppNotificationService();
                $waService->notifyOrderStatusChanged($orderId, $newStatus);
            } catch (\Throwable $th) {
                // Silent fail to not disrupt status update
            }

            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Process weighing submission and calculate price
     */
    public function submitWeighing(int $orderId, float $actualWeight, int $piecesCount, ?string $storeNotes, int $weighedByUserId): bool {
        $order = $this->findWithDetails($orderId);
        if (!$order) return false;

        $this->beginTransaction();
        try {
            // 1. Calculate price based on items and weight
            $subtotal = 0.00;
            if (!empty($order['items'])) {
                foreach ($order['items'] as $item) {
                    $unitPrice = (float)$item['unit_price'];
                    if ($item['unit'] === 'kg') {
                        $itemSubtotal = $actualWeight * $unitPrice;
                        // update order_items quantity & subtotal
                        $stmtItem = $this->db->prepare("UPDATE `order_items` SET `quantity` = :qty, `subtotal` = :subtotal WHERE `id` = :item_id");
                        $stmtItem->execute(['qty' => $actualWeight, 'subtotal' => $itemSubtotal, 'item_id' => $item['id']]);
                        $subtotal += $itemSubtotal;
                    } else {
                        $subtotal += (float)$item['subtotal'];
                    }
                }
            } else {
                // Fallback default unit rate (e.g. Rp 7.000 / kg)
                $subtotal = $actualWeight * 7000.00;
            }

            $deliveryFee = (float)$order['delivery_fee'];
            $grandTotal = $subtotal + $deliveryFee;

            // 2. Save or update laundry_details
            $sqlLd = "INSERT INTO `laundry_details` (`order_id`, `weight_actual`, `pieces_count`, `notes_from_store`, `weighed_by`, `created_at`, `updated_at`)
                      VALUES (:order_id, :weight, :pieces, :notes, :weighed_by, NOW(), NOW())
                      ON DUPLICATE KEY UPDATE 
                        `weight_actual` = :weight_u, 
                        `pieces_count` = :pieces_u, 
                        `notes_from_store` = :notes_u, 
                        `weighed_by` = :weighed_by_u,
                        `updated_at` = NOW()";
            $stmtLd = $this->db->prepare($sqlLd);
            $stmtLd->execute([
                'order_id' => $orderId,
                'weight' => $actualWeight,
                'pieces' => $piecesCount,
                'notes' => $storeNotes,
                'weighed_by' => $weighedByUserId,
                'weight_u' => $actualWeight,
                'pieces_u' => $piecesCount,
                'notes_u' => $storeNotes,
                'weighed_by_u' => $weighedByUserId
            ]);

            // 3. Update orders total price & status to MENUNGGU_PEMBAYARAN
            $this->update($orderId, [
                'total_price' => $grandTotal,
                'status' => 'MENUNGGU_PEMBAYARAN'
            ]);

            // 4. Create/update payment row with dynamic expiration from setting for QRIS or pending for Cash
            $paymentMethod = $order['payment_method'] ?? 'ONLINE_QRIS';
            $paymentModel = new Payment();
            $paymentModel->createForOrder($orderId, $grandTotal, $paymentMethod, null);

            // 5. Log history
            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => 'MENUNGGU_PEMBAYARAN',
                'changed_by' => $weighedByUserId,
                'notes' => "Hasil penimbangan: {$actualWeight} kg ({$piecesCount} helai). Total tagihan: " . format_rupiah($grandTotal)
            ]);

            // 6. In-App Notification to Customer
            $notifModel = new Notification();
            if (!empty($order['customer_id'])) {
                $notifModel->createNotification(
                    (int)$order['customer_id'],
                    'Tagihan Pembayaran QRIS Siap! ⚖️',
                    "Cucian pesanan #{$order['order_code']} telah ditimbang ({$actualWeight} kg). Total tagihan: " . format_rupiah($grandTotal) . ". Silakan selesaikan pembayaran via QRIS.",
                    'payment',
                    url('order/' . $orderId . '/payment'),
                    $orderId
                );
            }

            // 7. WhatsApp Notification to Customer (Notifikasi Pertama: Siap Dibayar)
            if (!empty($order['customer_phone'])) {
                $waService = new \App\Services\WhatsAppNotificationService();
                $expiryTime = date('H:i', strtotime('+60 minutes')) . ' WIB';
                $waService->notifyWeighingCompleted(
                    $order['customer_phone'],
                    $order['customer_name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $order['store_name'] ?? 'SmartLaundry',
                    $grandTotal,
                    $actualWeight,
                    $expiryTime,
                    $orderId,
                    (int)$order['customer_id']
                );
            }

            $this->commit();
            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Get active orders for a customer (status not SELESAI or DIBATALKAN)
     */
    public function getCustomerActiveOrders(int $customerId): array {
        $sql = "SELECT o.*, 
                       s.name AS store_name, s.phone AS store_phone,
                       pa.full_address AS pickup_address,
                       c.name AS courier_name, c.phone AS courier_phone,
                       cr.vehicle_type, cr.license_plate, cr.current_lat, cr.current_lng,
                       p.status AS payment_status, p.amount AS payment_amount, p.expired_at, p.qris_payload,
                       ld.weight_actual, ld.pieces_count, ld.notes_from_store,
                       (SELECT GROUP_CONCAT(CONCAT(ls.name, ' (', oi.quantity, ' ', ls.unit, ')') SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS item_summary
                FROM `{$this->table}` o
                JOIN `stores` s ON o.store_id = s.id
                JOIN `addresses` pa ON o.pickup_address_id = pa.id
                LEFT JOIN `users` c ON o.courier_id = c.id
                LEFT JOIN `couriers` cr ON c.id = cr.user_id
                LEFT JOIN `payments` p ON o.id = p.order_id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE o.customer_id = :customer_id 
                  AND o.status NOT IN ('SELESAI', 'DIBATALKAN')
                ORDER BY o.id DESC";
        $orders = $this->query($sql, ['customer_id' => $customerId]);

        // Fetch status histories for each active order
        $historyModel = new OrderStatusHistory();
        $courierModel = new Courier();
        foreach ($orders as &$order) {
            $order['histories'] = $historyModel->getByOrderId((int)$order['id']);
            $order['step'] = self::getProgressStep($order['status']);
            $order['latest_location'] = $courierModel->getLatestOrderLocation((int)$order['id']);
        }

        return $orders;
    }

    /**
     * Get completed orders history for customer
     */
    public function getCustomerCompletedOrders(int $customerId, int $limit = 5): array {
        $sql = "SELECT o.*, 
                       s.name AS store_name, s.phone AS store_phone,
                       p.status AS payment_status, p.amount AS payment_amount,
                       ld.weight_actual, ld.pieces_count,
                       r.id AS review_id, r.rating AS review_rating, r.comment AS review_comment,
                       (SELECT GROUP_CONCAT(CONCAT(ls.name, ' (', oi.quantity, ' ', ls.unit, ')') SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS item_summary
                FROM `{$this->table}` o
                JOIN `stores` s ON o.store_id = s.id
                LEFT JOIN `payments` p ON o.id = p.order_id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                LEFT JOIN `reviews` r ON o.id = r.order_id
                WHERE o.customer_id = :customer_id 
                  AND o.status = 'SELESAI'
                ORDER BY o.completed_at DESC, o.id DESC
                LIMIT {$limit}";
        return $this->query($sql, ['customer_id' => $customerId]);
    }

    /**
     * Determine progress step number (1 to 6)
     */
    public static function getProgressStep(string $status): int {
        switch ($status) {
            case 'MENUNGGU_KONFIRMASI':
            case 'MENUNGGU_KURIR':
                return 1; // Menunggu
            case 'KURIR_MENUJU_LOKASI':
            case 'LAUNDRY_DIJEMPUT':
            case 'DALAM_PERJALANAN_KE_TOKO':
                return 2; // Penjemputan
            case 'SAMPAI_DI_TOKO':
            case 'SEDANG_DITIMBANG':
            case 'MENUNGGU_PEMBAYARAN':
                return 3; // Penimbangan & Tagihan
            case 'PEMBAYARAN_BERHASIL':
            case 'SEDANG_DICUCI':
            case 'SEDANG_DIPROSES':
                return 4; // Pencucian & Setrika
            case 'SIAP_DIANTAR':
            case 'KURIR_MENUJU_CUSTOMER':
            case 'LAUNDRY_DIANTAR':
                return 5; // Pengantaran / Tiba di Lokasi
            case 'SELESAI':
                return 6; // Selesai
            default:
                return 1;
        }
    }

    /**
     * Create a new order with items, status history, and notifications atomically
     */
    public function createWithItems(array $orderData, array $items): ?int {
        $this->beginTransaction();
        try {
            // 1. Generate Order Code if not provided
            if (empty($orderData['order_code'])) {
                $orderData['order_code'] = 'LND-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            }

            // Set default status if empty
            if (empty($orderData['status'])) {
                $orderData['status'] = 'MENUNGGU_KONFIRMASI';
            }

            // 2. Insert into orders table
            $orderId = (int)$this->create($orderData);
            if (!$orderId) {
                $this->rollBack();
                return null;
            }

            // 3. Insert order_items
            $sqlItem = "INSERT INTO `order_items` (`order_id`, `service_id`, `quantity`, `unit_price`, `subtotal`, `item_notes`, `created_at`, `updated_at`) 
                        VALUES (:order_id, :service_id, :quantity, :unit_price, :subtotal, :item_notes, NOW(), NOW())";
            $stmtItem = $this->db->prepare($sqlItem);

            foreach ($items as $item) {
                $stmtItem->execute([
                    'order_id' => $orderId,
                    'service_id' => (int)$item['service_id'],
                    'quantity' => (float)$item['quantity'],
                    'unit_price' => (float)$item['unit_price'],
                    'subtotal' => (float)$item['subtotal'],
                    'item_notes' => $item['item_notes'] ?? null
                ]);
            }

            // 4. Insert initial history in order_status_histories
            $sqlHistory = "INSERT INTO `order_status_histories` (`order_id`, `status`, `changed_by`, `notes`, `created_at`) 
                           VALUES (:order_id, :status, :changed_by, :notes, NOW())";
            $stmtHistory = $this->db->prepare($sqlHistory);
            $stmtHistory->execute([
                'order_id' => $orderId,
                'status' => $orderData['status'],
                'changed_by' => $orderData['customer_id'],
                'notes' => 'Pesanan baru dibuat oleh pelanggan (estimasi awal)'
            ]);

            // 5. Insert notification for Customer
            $sqlNotif = "INSERT INTO `notifications` (`user_id`, `order_id`, `title`, `message`, `type`, `action_url`, `is_read`, `created_at`, `updated_at`) 
                         VALUES (:user_id, :order_id, :title, :message, :type, :action_url, 0, NOW(), NOW())";
            $stmtNotif = $this->db->prepare($sqlNotif);
            
            $stmtNotif->execute([
                'user_id' => $orderData['customer_id'],
                'order_id' => $orderId,
                'title' => 'Pesanan Berhasil Dibuat',
                'message' => "Pesanan #{$orderData['order_code']} berhasil dikirim ke toko dan menunggu konfirmasi.",
                'type' => 'order',
                'action_url' => 'dashboard/customer'
            ]);

            // 6. Insert notification for Store Owner
            $stmtStoreOwner = $this->db->prepare("SELECT owner_id FROM `stores` WHERE id = :store_id LIMIT 1");
            $stmtStoreOwner->execute(['store_id' => $orderData['store_id']]);
            $store = $stmtStoreOwner->fetch();
            if ($store && !empty($store['owner_id'])) {
                $stmtNotif->execute([
                    'user_id' => (int)$store['owner_id'],
                    'order_id' => $orderId,
                    'title' => 'Pesanan Baru Masuk!',
                    'message' => "Pesanan baru #{$orderData['order_code']} masuk dan membutuhkan konfirmasi toko.",
                    'type' => 'order',
                    'action_url' => 'dashboard/store'
                ]);
            }

            $this->commit();
            return $orderId;
        } catch (\Throwable $th) {
            $this->rollBack();
            return null;
        }
    }

    /**
     * Get expired payment queue awaiting store action
     */
    public function getStoreExpiredPaymentsQueue(int $storeId): array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.phone AS customer_phone,
                       a.full_address AS customer_address,
                       c.name AS courier_name,
                       p.status AS payment_status, p.amount AS payment_amount, p.expired_at,
                       ld.weight_actual, ld.pieces_count,
                       (SELECT GROUP_CONCAT(ls.name SEPARATOR ', ') 
                        FROM `order_items` oi 
                        JOIN `laundry_services` ls ON oi.service_id = ls.id 
                        WHERE oi.order_id = o.id) AS service_names
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                JOIN `addresses` a ON o.pickup_address_id = a.id
                LEFT JOIN `users` c ON o.courier_id = c.id
                LEFT JOIN `payments` p ON o.id = p.order_id
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE o.store_id = :store_id 
                  AND o.status = 'PEMBAYARAN_KADALUARSA'
                ORDER BY o.id ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Get active refund return queue in store
     */
    public function getStoreRefundsQueue(int $storeId): array {
        $sql = "SELECT o.*, 
                       u.name AS customer_name, u.phone AS customer_phone,
                       a.full_address AS customer_address,
                       c.name AS courier_name, c.phone AS courier_phone,
                       ld.weight_actual, ld.pieces_count
                FROM `{$this->table}` o
                JOIN `users` u ON o.customer_id = u.id
                JOIN `addresses` a ON o.pickup_address_id = a.id
                LEFT JOIN `users` c ON (o.refund_assigned_courier_id = c.id OR (o.refund_assigned_courier_id IS NULL AND o.courier_id = c.id))
                LEFT JOIN `laundry_details` ld ON o.id = ld.order_id
                WHERE o.store_id = :store_id 
                  AND o.status = 'PROSES_PENGEMBALIAN'
                ORDER BY o.id ASC";
        return $this->query($sql, ['store_id' => $storeId]);
    }

    /**
     * Store Admin/Owner approves return process and configures return fee & assigned courier
     */
    public function approveRefund(int $orderId, float $refundFee, string $bearer, ?int $courierUserId, int $approvedByUserId, ?string $notes = null): bool {
        $order = $this->findWithDetails($orderId);
        if (!$order) return false;

        if ($order['status'] !== 'PEMBAYARAN_KADALUARSA') {
            return false;
        }

        $this->beginTransaction();
        try {
            $assignedCourier = $courierUserId ? $courierUserId : (!empty($order['courier_id']) ? (int)$order['courier_id'] : null);

            $updateData = [
                'status' => 'PROSES_PENGEMBALIAN',
                'refund_fee' => $refundFee,
                'refund_fee_bearer' => in_array(strtoupper($bearer), ['STORE', 'CUSTOMER', 'PLATFORM'], true) ? strtoupper($bearer) : 'STORE',
                'refund_assigned_courier_id' => $assignedCourier,
                'courier_id' => $assignedCourier
            ];

            $this->update($orderId, $updateData);

            // Record History
            $historyNotes = "Pengembalian disetujui toko. Biaya pengembalian: " . format_rupiah($refundFee) . " (Penanggung: " . strtoupper($bearer) . ").";
            if (!empty($notes)) {
                $historyNotes .= " Catatan: {$notes}";
            }

            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => 'PROSES_PENGEMBALIAN',
                'changed_by' => $approvedByUserId,
                'notes' => $historyNotes
            ]);

            // Notify Customer (In-App + WhatsApp)
            $notifModel = new Notification();
            if (!empty($order['customer_id'])) {
                $notifModel->createNotification(
                    (int)$order['customer_id'],
                    'Pengembalian Laundry Diproses #' . $order['order_code'],
                    'Pihak toko telah menyetujui proses pengembalian pakaian Anda. Kurir akan segera mengantarkan kembali ke alamat Anda.',
                    'order',
                    '/dashboard/customer',
                    $orderId
                );
            }

            $waService = new \App\Services\WhatsAppNotificationService();
            if (!empty($order['customer_phone'])) {
                $waService->notifyRefundApproved(
                    $order['customer_phone'],
                    $order['customer_name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $refundFee,
                    $bearer
                );
            }

            // Notify Courier if assigned
            if ($assignedCourier) {
                $notifModel->createNotification(
                    $assignedCourier,
                    'Tugas Pengembalian Laundry #' . $order['order_code'],
                    'Anda ditugaskan untuk mengembalikan laundry customer #' . $order['order_code'] . ' ke alamat tujuan.',
                    'courier',
                    '/dashboard/courier',
                    $orderId
                );

                $courierUser = (new User())->find($assignedCourier);
                if ($courierUser && !empty($courierUser['phone'])) {
                    $waService->notifyCourierTaskOffered(
                        $courierUser['phone'],
                        $courierUser['name'] ?? 'Kurir',
                        $order['order_code'],
                        'RETURN',
                        $order['pickup_address_full'] ?? 'Alamat Pelanggan'
                    );
                }
            }

            $this->commit();
            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Assign courier for return task (Anti-duplication check)
     */
    public function assignRefundCourier(int $orderId, int $courierUserId, int $assignedByUserId): bool {
        $order = $this->find($orderId);
        if (!$order || $order['status'] !== 'PROSES_PENGEMBALIAN') return false;

        // Anti duplication check
        if (!empty($order['refund_assigned_courier_id']) && (int)$order['refund_assigned_courier_id'] === $courierUserId) {
            return true;
        }

        $this->beginTransaction();
        try {
            $this->update($orderId, [
                'refund_assigned_courier_id' => $courierUserId,
                'courier_id' => $courierUserId
            ]);

            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => 'PROSES_PENGEMBALIAN',
                'changed_by' => $assignedByUserId,
                'notes' => 'Kurir ditugaskan untuk pengembalian laundry ke customer.'
            ]);

            $notifModel = new Notification();
            $notifModel->createNotification(
                $courierUserId,
                'Tugas Pengembalian #' . $order['order_code'],
                'Anda ditugaskan untuk mengantar pengembalian pesanan #' . $order['order_code'],
                'courier',
                '/dashboard/courier',
                $orderId
            );

            $this->commit();
            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Courier starts return delivery journey
     */
    public function startRefundDelivery(int $orderId, int $courierUserId): bool {
        $order = $this->findWithDetails($orderId);
        if (!$order || $order['status'] !== 'PROSES_PENGEMBALIAN') return false;

        $historyModel = new OrderStatusHistory();
        $historyModel->create([
            'order_id' => $orderId,
            'status' => 'PROSES_PENGEMBALIAN',
            'changed_by' => $courierUserId,
            'notes' => 'Kurir sedang dalam perjalanan mengantarkan cucian kembali ke alamat customer.'
        ]);

        // In-App Notification & WhatsApp
        $notifModel = new Notification();
        if (!empty($order['customer_id'])) {
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Kurir Menuju Lokasi Anda (Pengembalian)',
                'Kurir sedang dalam perjalanan mengantarkan cucian kembali untuk pesanan #' . $order['order_code'],
                'courier',
                '/dashboard/customer',
                $orderId
            );
        }

        $waService = new \App\Services\WhatsAppNotificationService();
        if (!empty($order['customer_phone'])) {
            $waService->notifyRefundCourierDispatched(
                $order['customer_phone'],
                $order['customer_name'] ?? 'Pelanggan',
                $order['order_code'],
                $order['courier_name'] ?? 'Kurir SmartLaundry',
                $order['courier_phone'] ?? '-'
            );
        }

        return true;
    }

    /**
     * Complete laundry return: Order status becomes DIBATALKAN (not SELESAI)
     */
    public function completeRefundReturn(int $orderId, int $courierUserId, ?string $notes = null): bool {
        $order = $this->findWithDetails($orderId);
        if (!$order || $order['status'] !== 'PROSES_PENGEMBALIAN') return false;

        $this->beginTransaction();
        try {
            $this->update($orderId, [
                'status' => 'DIBATALKAN',
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            // History log
            $historyNotes = 'Pengembalian cucian selesai diserahterimakan kepada pelanggan. Pesanan resmi DIBATALKAN.';
            if (!empty($notes)) {
                $historyNotes .= " Catatan: {$notes}";
            }

            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => 'DIBATALKAN',
                'changed_by' => $courierUserId,
                'notes' => $historyNotes
            ]);

            // Notifications
            $notifModel = new Notification();
            if (!empty($order['customer_id'])) {
                $notifModel->createNotification(
                    (int)$order['customer_id'],
                    'Pengembalian Selesai #' . $order['order_code'],
                    'Cucian Anda telah berhasil dikembalikan dan pesanan #' . $order['order_code'] . ' telah ditutup (DIBATALKAN).',
                    'order',
                    '/dashboard/customer',
                    $orderId
                );
            }

            $storeModel = new Store();
            $store = $storeModel->find((int)$order['store_id']);
            $storeOwnerId = !empty($store['owner_id']) ? (int)$store['owner_id'] : (!empty($store['user_id']) ? (int)$store['user_id'] : null);
            if ($storeOwnerId) {
                $notifModel->createNotification(
                    $storeOwnerId,
                    'Pengembalian Selesai #' . $order['order_code'],
                    'Kurir telah menyelesaikan pengembalian laundry untuk pesanan #' . $order['order_code'] . ' ke customer.',
                    'order',
                    '/dashboard/store',
                    $orderId
                );
            }

            $waService = new \App\Services\WhatsAppNotificationService();
            if (!empty($order['customer_phone'])) {
                $waService->notifyRefundCompleted(
                    $order['customer_phone'],
                    $order['customer_name'] ?? 'Pelanggan',
                    $order['order_code']
                );
            }

            $this->commit();
            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Dispatch / Offer task to available candidate courier (with automatic fallback to TIDAK_ADA_KURIR)
     */
    public function dispatchTaskOffer(int $orderId, string $taskType = 'PICKUP'): bool {
        $order = $this->findWithDetails($orderId);
        if (!$order) return false;

        $storeId = (int)$order['store_id'];
        $offerModel = new CourierTaskOffer();
        $courierModel = new Courier();

        // 1. Get couriers that already rejected this order
        $rejectedCourierIds = $offerModel->getRejectedCourierIdsForOrder($orderId);

        // 2. Find available candidate courier
        $candidateCourier = $courierModel->findAvailableCourierForStore($storeId, $rejectedCourierIds);

        if ($candidateCourier) {
            $courierUserId = (int)$candidateCourier['user_id'];
            
            // Create pending offer
            $offerId = $offerModel->createOffer($orderId, $courierUserId, $taskType);

            // Update order status
            $this->update($orderId, [
                'courier_id' => $courierUserId,
                'courier_assignment_status' => 'MENUNGGU_RESPON'
            ]);

            // History log
            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => $order['status'],
                'changed_by' => null,
                'notes' => "Tawaran tugas [{$taskType}] dikirimkan ke Kurir {$candidateCourier['courier_name']} (Menunggu Konfirmasi)."
            ]);

            // In-App Notification to Courier
            $notifModel = new Notification();
            $notifModel->createNotification(
                $courierUserId,
                'Tawaran Tugas Baru #' . $order['order_code'],
                "Ada tawaran tugas baru untuk pesanan #{$order['order_code']}. Silakan konfirmasi terima atau tolak di dashboard.",
                'courier',
                '/dashboard/courier',
                $orderId
            );

            // WhatsApp Notification to Courier
            if (!empty($candidateCourier['courier_phone'])) {
                $waService = new \App\Services\WhatsAppNotificationService();
                $targetAddress = ($taskType === 'DELIVERY' || $taskType === 'RETURN') ? ($order['delivery_address'] ?? $order['pickup_address_full']) : $order['pickup_address_full'];
                $waService->notifyCourierTaskOffered(
                    $candidateCourier['courier_phone'],
                    $candidateCourier['courier_name'],
                    $order['order_code'],
                    $taskType,
                    $targetAddress
                );
            }

            return true;
        } else {
            // No courier available
            $this->update($orderId, [
                'courier_assignment_status' => 'TIDAK_ADA_KURIR'
            ]);

            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => $order['status'],
                'changed_by' => null,
                'notes' => "Seluruh kurir armada tidak tersedia atau telah menolak tawaran untuk pesanan #{$order['order_code']}. Memerlukan tindakan manual toko."
            ]);

            // Notify Store Owner
            $storeModel = new Store();
            $store = $storeModel->find($storeId);
            $storeOwnerId = !empty($store['owner_id']) ? (int)$store['owner_id'] : (!empty($store['user_id']) ? (int)$store['user_id'] : null);
            
            if ($storeOwnerId) {
                $notifModel = new Notification();
                $notifModel->createNotification(
                    $storeOwnerId,
                    'Perhatian: Tidak Ada Kurir Tersedia #' . $order['order_code'],
                    "Seluruh kurir armada sedang bertugas atau menolak pesanan #{$order['order_code']}. Silakan lakukan penugasan manual.",
                    'order',
                    '/dashboard/store',
                    $orderId
                );

                try {
                    $waService = new \App\Services\WhatsAppNotificationService();
                    $waService->notifyStoreNoCourierAvailable(
                        (int)$storeId,
                        $order['order_code'],
                        $orderId
                    );
                } catch (\Throwable $e) {
                    error_log('[WA] notifyStoreNoCourierAvailable error: ' . $e->getMessage());
                }
            }

            return false;
        }
    }

    /**
     * Process Courier Accepting Task Offer
     */
    public function processCourierAccept(int $offerId, int $courierUserId): array {
        $offerModel = new CourierTaskOffer();
        $offer = $offerModel->find($offerId);

        if (!$offer || (int)$offer['courier_id'] !== $courierUserId || $offer['status'] !== 'MENUNGGU_RESPON') {
            return ['success' => false, 'message' => 'Tawaran tugas tidak valid atau sudah kedaluwarsa.'];
        }

        $courierModel = new Courier();
        // Constraint: Kurir tidak boleh memiliki tugas aktif yang bertabrakan
        if ($courierModel->hasActiveTask($courierUserId)) {
            return ['success' => false, 'message' => 'Anda sedang menjalankan tugas aktif di jalan. Selesaikan tugas saat ini sebelum menerima tugas baru.'];
        }

        $orderId = (int)$offer['order_id'];
        $order = $this->findWithDetails($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];
        }

        $this->beginTransaction();
        try {
            // 1. Mark offer accepted
            $offerModel->markAccepted($offerId, $courierUserId);

            // 2. Lock assignment on order
            $this->update($orderId, [
                'courier_id' => $courierUserId,
                'courier_assignment_status' => 'DITERIMA'
            ]);

            // 3. History log
            $courierUser = $courierModel->findByUserId($courierUserId);
            $courierName = $courierUser['courier_name'] ?? 'Kurir';

            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => $order['status'],
                'changed_by' => $courierUserId,
                'notes' => "Kurir {$courierName} menyetujui tawaran tugas [{$offer['task_type']}]. Penugasan telah dikonfirmasi."
            ]);

            // 4. In-App Notification to Store
            $storeModel = new Store();
            $store = $storeModel->find((int)$order['store_id']);
            $storeOwnerId = !empty($store['owner_id']) ? (int)$store['owner_id'] : (!empty($store['user_id']) ? (int)$store['user_id'] : null);
            
            if ($storeOwnerId) {
                $notifModel = new Notification();
                $notifModel->createNotification(
                    $storeOwnerId,
                    'Tugas Diterima Kurir #' . $order['order_code'],
                    "Kurir {$courierName} telah menerima tugas untuk pesanan #{$order['order_code']}.",
                    'courier',
                    '/dashboard/store',
                    $orderId
                );
            }

            // 5. In-App Notification to Customer
            $notifModel = new Notification();
            if (!empty($order['customer_id'])) {
                $notifModel->createNotification(
                    (int)$order['customer_id'],
                    'Kurir Ditugaskan #' . $order['order_code'],
                    "Kurir {$courierName} telah ditugaskan untuk pesanan laundry Anda.",
                    'courier',
                    url('order/' . $orderId . '/tracking'),
                    $orderId
                );
            }

            // 6. WhatsApp Notification to Customer
            if (!empty($order['customer_phone'])) {
                $waService = new \App\Services\WhatsAppNotificationService();
                $waService->notifyCourierAssigned(
                    $order['customer_phone'],
                    $order['customer_name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $courierName,
                    $courierUser['courier_phone'] ?? $courierUser['phone'] ?? '-',
                    $order['store_name'] ?? 'SmartLaundry'
                );
            }

            $this->commit();
            return ['success' => true, 'message' => "Tugas pesanan #{$order['order_code']} berhasil diterima. Silakan jalankan tugas di dashboard."];
        } catch (\Throwable $th) {
            $this->rollBack();
            return ['success' => false, 'message' => 'Gagal memproses penerimaan tugas: ' . $th->getMessage()];
        }
    }

    /**
     * Process Courier Rejecting Task Offer (Triggers automatic re-offer to next available courier)
     */
    public function processCourierReject(int $offerId, int $courierUserId, ?string $reason = null): array {
        $offerModel = new CourierTaskOffer();
        $offer = $offerModel->find($offerId);

        if (!$offer || (int)$offer['courier_id'] !== $courierUserId || $offer['status'] !== 'MENUNGGU_RESPON') {
            return ['success' => false, 'message' => 'Tawaran tugas tidak valid atau sudah kedaluwarsa.'];
        }

        $orderId = (int)$offer['order_id'];
        $order = $this->findWithDetails($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Pesanan tidak ditemukan.'];
        }

        $this->beginTransaction();
        try {
            // 1. Mark offer rejected
            $offerModel->markRejected($offerId, $courierUserId, $reason);

            // 2. Reset courier_id on order temporarily
            $this->update($orderId, [
                'courier_id' => null,
                'courier_assignment_status' => 'DITOLAK'
            ]);

            $courierModel = new Courier();
            $courierUser = $courierModel->findByUserId($courierUserId);
            $courierName = $courierUser['courier_name'] ?? 'Kurir';

            // 3. History log with reject reason
            $reasonNote = !empty($reason) ? " Alasan: {$reason}." : '';
            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => $order['status'],
                'changed_by' => $courierUserId,
                'notes' => "Kurir {$courierName} menolak tawaran tugas [{$offer['task_type']}].{$reasonNote}"
            ]);

            // 4. Notify Store Owner via WhatsApp / In-App
            $storeModel = new Store();
            $store = $storeModel->find((int)$order['store_id']);
            $storeOwnerId = !empty($store['owner_id']) ? (int)$store['owner_id'] : (!empty($store['user_id']) ? (int)$store['user_id'] : null);
            
            if ($storeOwnerId) {
                $notifModel = new Notification();
                $notifModel->createNotification(
                    $storeOwnerId,
                    'Kurir Menolak Tugas #' . $order['order_code'],
                    "Kurir {$courierName} menolak pesanan #{$order['order_code']}.{$reasonNote} Sistem mencari kurir pengganti.",
                    'courier',
                    '/dashboard/store',
                    $orderId
                );

                try {
                    $waService = new \App\Services\WhatsAppNotificationService();
                    $waService->notifyStoreCourierRejected(
                        (int)$order['store_id'],
                        $order['order_code'],
                        $courierName,
                        $reason,
                        $orderId
                    );
                } catch (\Throwable $e) {
                    error_log('[WA] notifyStoreCourierRejected error: ' . $e->getMessage());
                }
            }

            $this->commit();

            // 5. Automatic Re-offer to next candidate courier
            $this->dispatchTaskOffer($orderId, $offer['task_type']);

            return ['success' => true, 'message' => "Tawaran tugas pesanan #{$order['order_code']} telah ditolak."];
        } catch (\Throwable $th) {
            $this->rollBack();
            return ['success' => false, 'message' => 'Gagal menolak tugas: ' . $th->getMessage()];
        }
    }

    /**
     * Manual assignment by Store Admin/Owner
     */
    public function manualAssignCourier(int $orderId, int $courierUserId, int $assignedByUserId, string $taskType = 'PICKUP'): bool {
        $order = $this->find($orderId);
        if (!$order) return false;

        $offerModel = new CourierTaskOffer();
        // Expire any existing pending offers
        $offerModel->expirePendingOffersForOrder($orderId);

        $this->beginTransaction();
        try {
            $this->update($orderId, [
                'courier_id' => $courierUserId,
                'courier_assignment_status' => 'DITERIMA'
            ]);

            $courierModel = new Courier();
            $courierUser = $courierModel->findByUserId($courierUserId);
            $courierName = $courierUser['courier_name'] ?? 'Kurir';

            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => $order['status'],
                'changed_by' => $assignedByUserId,
                'notes' => "Kurir {$courierName} ditugaskan secara manual oleh pihak outlet toko."
            ]);

            $notifModel = new Notification();
            $notifModel->createNotification(
                $courierUserId,
                'Tugas Ditugaskan Langsung #' . $order['order_code'],
                "Outlet toko menugaskan langsung pesanan #{$order['order_code']} kepada Anda.",
                'courier',
                '/dashboard/courier',
                $orderId
            );

            $waService = new \App\Services\WhatsAppNotificationService();
            // WhatsApp to Courier
            if (!empty($courierUser['courier_phone'])) {
                $targetAddress = ($taskType === 'DELIVERY' || $taskType === 'RETURN') ? ($order['delivery_address'] ?? $order['pickup_address_full'] ?? 'Alamat Tujuan') : ($order['pickup_address_full'] ?? 'Alamat Penjemputan');
                $waService->notifyCourierTaskOffered(
                    $courierUser['courier_phone'],
                    $courierName,
                    $order['order_code'],
                    $taskType,
                    $targetAddress
                );
            }

            // In-App & WhatsApp to Customer
            $orderFull = $this->findWithDetails($orderId);
            if ($orderFull && !empty($orderFull['customer_id'])) {
                $notifModel->createNotification(
                    (int)$orderFull['customer_id'],
                    'Kurir Ditugaskan #' . $orderFull['order_code'],
                    "Kurir {$courierName} telah ditugaskan untuk pesanan laundry Anda.",
                    'courier',
                    url('order/' . $orderId . '/tracking'),
                    $orderId
                );
            }
            if ($orderFull && !empty($orderFull['customer_phone'])) {
                $waService->notifyCourierAssigned(
                    $orderFull['customer_phone'],
                    $orderFull['customer_name'] ?? 'Pelanggan',
                    $orderFull['order_code'],
                    $courierName,
                    $courierUser['courier_phone'] ?? $courierUser['phone'] ?? '-',
                    $orderFull['store_name'] ?? 'SmartLaundry'
                );
            }

            $this->commit();
            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Complete Order: update status to SELESAI, set completed_at, log history, and notify customer
     */
    public function completeOrder(int $orderId, ?int $completedByUserId = null, ?string $historyNotes = null): bool {
        $order = $this->find($orderId);
        if (!$order) return false;

        $completedAt = date('Y-m-d H:i:s');
        $notes = $historyNotes ?? 'Laundry diterima oleh customer dalam kondisi bersih, rapi dan wangi. Transaksi SELESAI.';

        $this->beginTransaction();
        try {
            // Update order status & completed_at
            $this->update($orderId, [
                'status' => 'SELESAI',
                'completed_at' => $completedAt
            ]);

            // Add history
            $historyModel = new OrderStatusHistory();
            $historyModel->create([
                'order_id' => $orderId,
                'status' => 'SELESAI',
                'changed_by' => $completedByUserId,
                'notes' => $notes
            ]);

            // In-app review invitation notification to customer
            $notifModel = new Notification();
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Laundry Selesai! Beri Ulasan yuk ⭐',
                "Pesanan #{$order['order_code']} telah selesai. Berikan penilaian & testimoni untuk toko laundry.",
                'order',
                url('order/' . $orderId . '/review'),
                $orderId
            );

            // WhatsApp Notification to customer
            $customerModel = new User();
            $customerUser = $customerModel->find((int)$order['customer_id']);
            if ($customerUser && !empty($customerUser['phone'])) {
                $storeModel = new Store();
                $store = $storeModel->find((int)$order['store_id']);
                $storeName = $store['name'] ?? 'SmartLaundry';

                $waService = new \App\Services\WhatsAppNotificationService();
                $waService->notifyOrderCompleted(
                    $customerUser['phone'],
                    $customerUser['name'] ?? 'Pelanggan',
                    $order['order_code'],
                    $storeName,
                    (int)$orderId,
                    (int)$order['customer_id']
                );
            }

            $this->commit();
            return true;
        } catch (\Throwable $th) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Auto-Complete orders in LAUNDRY_DIANTAR status past X hours (Idempotent for Cron)
     */
    public function autoCompleteDeliveredOrders(?int $hoursThreshold = null): int {
        if ($hoursThreshold === null) {
            $hoursThreshold = (int)\App\Models\Setting::get('order_autocomplete_hours', 24);
        }
        $sql = "SELECT o.id, o.order_code, o.customer_id, o.store_id, MAX(osh.created_at) AS delivered_at
                FROM `{$this->table}` o
                JOIN `order_status_histories` osh ON o.id = osh.order_id
                WHERE o.status = 'LAUNDRY_DIANTAR'
                  AND osh.status = 'LAUNDRY_DIANTAR'
                GROUP BY o.id, o.order_code, o.customer_id, o.store_id
                HAVING delivered_at <= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
        $orders = $this->query($sql, ['hours' => $hoursThreshold]);
        $count = 0;

        foreach ($orders as $ord) {
            $orderId = (int)$ord['id'];
            $success = $this->completeOrder(
                $orderId,
                null,
                'Diselesaikan otomatis oleh sistem karena melewati 24 jam konfirmasi penerimaan customer.'
            );
            if ($success) {
                $count++;
            }
        }

        return $count;
    }
}
