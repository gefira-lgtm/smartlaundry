<?php
namespace App\Models;

use App\Core\Model;

class Payment extends Model {
    protected string $table = 'payments';
    protected string $primaryKey = 'id';

    /**
     * Get Total Gross Merchandise Value (GMV) across platform
     */
    public function getTotalGMV(): float {
        $sql = "SELECT COALESCE(SUM(amount), 0) AS total_gmv FROM `{$this->table}` WHERE `status` = 'PAID'";
        $row = $this->firstQuery($sql);
        return (float)($row['total_gmv'] ?? 0);
    }

    /**
     * Get daily transaction and order trends for Chart.js
     */
    public function getTransactionTrends(int $days = 7): array {
        $labels = [];
        $salesData = [];
        $ordersData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dateLabel = date('d M', strtotime($date));
            $labels[] = $dateLabel;

            // Daily sales sum
            $sqlSales = "SELECT COALESCE(SUM(amount), 0) AS daily_sales 
                         FROM `{$this->table}` 
                         WHERE `status` = 'PAID' AND DATE(`paid_at`) = :date";
            $salesRow = $this->firstQuery($sqlSales, ['date' => $date]);
            $salesData[] = (float)($salesRow['daily_sales'] ?? 0);

            // Daily orders count
            $sqlOrders = "SELECT COUNT(*) AS daily_orders FROM `orders` WHERE DATE(`created_at`) = :date";
            $ordersRow = $this->firstQuery($sqlOrders, ['date' => $date]);
            $ordersData[] = (int)($ordersRow['daily_orders'] ?? 0);
        }

        return [
            'labels' => $labels,
            'sales' => $salesData,
            'orders' => $ordersData
        ];
    }

    /**
     * Find payment by order ID
     */
    public function findByOrderId(int $orderId): ?array {
        return $this->first(['order_id' => $orderId]);
    }



    /**
     * Create or update payment record for order (QRIS or Cash)
     */
    public function createForOrder(int $orderId, float $amount, string $method = 'ONLINE_QRIS', ?int $expiryMinutes = null): array {
        if ($expiryMinutes === null) {
            $expiryMinutes = (int)\App\Models\Setting::get('qris_expiration_minutes', 60);
        }
        $payment = $this->findByOrderId($orderId);
        $paymentCode = 'PAY-' . date('Ymd') . '-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT);
        $expiredAt = ($method === 'ONLINE_QRIS') ? date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes")) : null;

        $qrisPayload = null;
        $qrisImageUrl = null;
        $transactionRef = null;

        if ($method === 'ONLINE_QRIS') {
            // IDEMPOTENSI: Jika sudah ada transaksi QRIS PENDING yang belum kedaluwarsa dengan nominal yang sama,
            // gunakan kembali transaksi tersebut agar tidak membuat transaksi QRIS ganda tanpa alasan.
            if ($payment && $payment['status'] === 'PENDING' 
                && !empty($payment['expired_at']) && strtotime($payment['expired_at']) > time()
                && (!empty($payment['qris_payload']) || !empty($payment['qris_image_url']))
                && abs((float)$payment['amount'] - (float)$amount) < 0.01
            ) {
                return $payment;
            }

            $gateway = \App\Services\Payment\PaymentGatewayFactory::create();
            $transaction = $gateway->createTransaction([
                'id' => $orderId,
                'total_price' => $amount
            ]);
            $qrisPayload = $transaction['qris_payload'] ?? null;
            $qrisImageUrl = $transaction['qris_image_url'] ?? null;
            $transactionRef = $transaction['transaction_id'] ?? null;
        }

        $data = [
            'order_id' => $orderId,
            'payment_code' => $paymentCode,
            'method' => $method,
            'amount' => $amount,
            'status' => 'PENDING',
            'qris_payload' => $qrisPayload,
            'qris_image_url' => $qrisImageUrl,
            'expired_at' => $expiredAt,
            'paid_at' => null,
            'transaction_ref' => $transactionRef
        ];

        if ($payment) {
            $this->update($payment['id'], $data);
            return array_merge($payment, $data);
        } else {
            $newId = (int)$this->create($data);
            $data['id'] = $newId;
            return $data;
        }
    }

    /**
     * Backward compatibility wrapper
     */
    public function createOrUpdateQRIS(int $orderId, float $amount, ?int $expiryMinutes = null): array {
        if ($expiryMinutes === null) {
            $expiryMinutes = (int)\App\Models\Setting::get('qris_expiration_minutes', 60);
        }
        return $this->createForOrder($orderId, $amount, 'ONLINE_QRIS', $expiryMinutes);
    }

    /**
     * Check payment expiry status and update if past deadline (Idempotent)
     */
    public function checkAndExpire(int $paymentId): array {
        $payment = $this->find($paymentId);
        if (!$payment) return [];

        if ($payment['status'] === 'PENDING' && !empty($payment['expired_at'])) {
            $now = time();
            $expiredTime = strtotime($payment['expired_at']);

            if ($now >= $expiredTime) {
                $this->update($paymentId, ['status' => 'EXPIRED']);
                $payment['status'] = 'EXPIRED';

                $orderId = (int)$payment['order_id'];
                $orderModel = new Order();
                $order = $orderModel->findWithDetails($orderId);

                // IDEMPOTENCY CHECK: Only process transitions and notifications if order is not yet marked expired
                if ($order && $order['status'] !== 'PEMBAYARAN_KADALUARSA' && $order['status'] !== 'PROSES_PENGEMBALIAN' && $order['status'] !== 'DIBATALKAN') {
                    // 1. Update order status
                    $orderModel->update($orderId, [
                        'status' => 'PEMBAYARAN_KADALUARSA'
                    ]);

                    // 2. Record to order_status_histories (changed_by = null for system automated action)
                    $historyModel = new OrderStatusHistory();
                    $historyModel->create([
                        'order_id' => $orderId,
                        'status' => 'PEMBAYARAN_KADALUARSA',
                        'changed_by' => null,
                        'notes' => 'Pembayaran QRIS kadaluarsa otomatis karena melewati batas waktu 1 jam.'
                    ]);

                    // 3. In-App Notifications
                    $notifModel = new Notification();
                    // To Customer
                    if (!empty($order['customer_id'])) {
                        $notifModel->createNotification(
                            (int)$order['customer_id'],
                            'Pembayaran Kadaluarsa #' . $order['order_code'],
                            'Batas waktu pembayaran pesanan laundry Anda telah berakhir. Toko akan memproses pengembalian pakaian Anda.',
                            'payment',
                            '/dashboard/customer',
                            $orderId
                        );
                    }

                    // To Store Owner / Admin Toko
                    $storeModel = new Store();
                    $store = $storeModel->find((int)$order['store_id']);
                    $storeOwnerId = !empty($store['owner_id']) ? (int)$store['owner_id'] : (!empty($store['user_id']) ? (int)$store['user_id'] : null);
                    if ($storeOwnerId) {
                        $notifModel->createNotification(
                            $storeOwnerId,
                            'Pesanan Kadaluarsa #' . $order['order_code'],
                            'Pesanan #' . $order['order_code'] . ' dari ' . ($order['customer_name'] ?? 'Pelanggan') . ' telah kadaluarsa. Silakan proses pengembalian pakaian.',
                            'order',
                            '/dashboard/store',
                            $orderId
                        );
                    }

                    // 4. WhatsApp Simulation Alerts
                    $waService = new \App\Services\WhatsAppNotificationService();
                    if (!empty($order['customer_phone'])) {
                        $waService->notifyPaymentExpired(
                            $order['customer_phone'],
                            $order['customer_name'] ?? 'Pelanggan',
                            $order['order_code'],
                            $order['store_name'] ?? 'SmartLaundry'
                        );
                    }

                    if ($store) {
                        $waService->notifyStorePaymentExpired(
                            (int)$order['store_id'],
                            $order['order_code'],
                            $order['customer_name'] ?? 'Pelanggan'
                        );
                    }
                }
            }
        }

        return $payment;
    }

    /**
     * Batch check and expire all overdue pending payments (For Cron)
     */
    public function expireAllPendingPastDeadline(): int {
        $now = date('Y-m-d H:i:s');
        $sql = "SELECT id, order_id FROM `{$this->table}` 
                WHERE `status` = 'PENDING' 
                  AND `expired_at` IS NOT NULL 
                  AND `expired_at` <= :now";
        $overdue = $this->query($sql, ['now' => $now]);
        $count = 0;

        foreach ($overdue as $p) {
            $this->checkAndExpire((int)$p['id']);
            $count++;
        }

        return $count;
    }

    /**
     * Send reminder to customer when QRIS payment is expiring soon (~15 mins remaining)
     * Strictly idempotent: only triggers when reminder_sent_at IS NULL and updates timestamp.
     */
    public function sendExpiringSoonReminders(int $thresholdMinutes = 15): int {
        $sql = "SELECT p.*, o.order_code, o.customer_id, u.name AS customer_name, u.phone AS customer_phone, s.name AS store_name
                FROM `{$this->table}` p
                JOIN `orders` o ON p.order_id = o.id
                JOIN `users` u ON o.customer_id = u.id
                JOIN `stores` s ON o.store_id = s.id
                WHERE p.status = 'PENDING' 
                  AND p.expired_at IS NOT NULL 
                  AND p.expired_at > NOW()
                  AND p.expired_at <= DATE_ADD(NOW(), INTERVAL :threshold MINUTE)
                  AND p.reminder_sent_at IS NULL";
        $expiring = $this->query($sql, ['threshold' => $thresholdMinutes]);
        $count = 0;

        $notifModel = new Notification();
        $waService = new \App\Services\WhatsAppNotificationService();

        foreach ($expiring as $p) {
            $paymentId = (int)$p['id'];
            $orderId = (int)$p['order_id'];

            // IDEMPOTENCY: Immediately update reminder_sent_at
            $this->update($paymentId, [
                'reminder_sent_at' => date('Y-m-d H:i:s')
            ]);

            $remainingSecs = max(60, strtotime($p['expired_at']) - time());
            $remainingMins = max(1, (int)round($remainingSecs / 60));

            // 1. In-App Notification to Customer
            $notifModel->createNotification(
                (int)$p['customer_id'],
                'Pengingat Pembayaran QRIS #' . $p['order_code'],
                "Batas waktu pembayaran QRIS pesanan Anda tersisa sekitar {$remainingMins} menit. Segera selesaikan pembayaran di aplikasi.",
                'payment',
                url('order/' . $orderId . '/payment'),
                $orderId
            );

            // 2. WhatsApp Notification to Customer
            if (!empty($p['customer_phone'])) {
                $waService->notifyPaymentExpiringSoon(
                    $p['customer_phone'],
                    $p['customer_name'] ?? 'Pelanggan',
                    $p['order_code'],
                    $p['store_name'] ?? 'SmartLaundry',
                    (float)$p['amount'],
                    $remainingMins
                );
            }

            $count++;
        }

        return $count;
    }

    /**
     * Regenerate fresh 1-hour QRIS after expiry
     */
    public function regenerateQris(int $orderId, int $expiryMinutes = 60): ?array {
        $orderModel = new Order();
        $order = $orderModel->find($orderId);
        if (!$order) return null;

        $amount = (float)$order['total_price'];
        $payment = $this->createForOrder($orderId, $amount, 'ONLINE_QRIS', $expiryMinutes);

        // Restore order status back to MENUNGGU_PEMBAYARAN
        $orderModel->updateStatus(
            $orderId,
            'MENUNGGU_PEMBAYARAN',
            (int)$order['customer_id'],
            "QRIS baru berhasil diterbitkan ulang dengan batas waktu 1 jam ke depan."
        );

        return $payment;
    }

    /**
     * Mark payment as paid and update transaction reference
     */
    public function markAsPaid(int $orderId, string $method = 'ONLINE_QRIS', ?string $ref = null): bool {
        $payment = $this->findByOrderId($orderId);
        $paidAt = date('Y-m-d H:i:s');
        $txnRef = $ref ?? ('PAY_' . strtoupper($method) . '_' . date('YmdHis') . '_' . mt_rand(1000, 9999));

        if ($payment) {
            return (bool)$this->update($payment['id'], [
                'status' => 'PAID',
                'method' => $method,
                'paid_at' => $paidAt,
                'transaction_ref' => $txnRef
            ]);
        }

        $orderModel = new Order();
        $order = $orderModel->find($orderId);
        $amount = $order ? (float)$order['total_price'] : 0.00;

        return (bool)$this->create([
            'order_id' => $orderId,
            'payment_code' => 'PAY-' . date('Ymd') . '-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT),
            'method' => $method,
            'amount' => $amount,
            'status' => 'PAID',
            'paid_at' => $paidAt,
            'transaction_ref' => $txnRef
        ]);
    }
}
