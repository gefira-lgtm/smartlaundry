<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Payment\PaymentGatewayFactory;
use App\Models\Payment;
use App\Models\Order;

class WebhookController extends Controller {
    
    /**
     * Handle incoming webhook from Midtrans
     * POST /webhook/midtrans
     */
    public function midtrans(Request $request, Response $response): void {
        // Since midtrans sends JSON payload, we read from raw input
        $json = file_get_contents('php://input');
        $payload = json_decode($json, true);

        if (!$payload) {
            $response->json(['status' => 'error', 'message' => 'Invalid JSON payload'], 400);
            return;
        }

        // Initialize Midtrans Gateway (if configured, otherwise it falls back to Simulated which ignores it)
        $gateway = PaymentGatewayFactory::create();
        
        // Log the webhook raw payload for debugging
        $logDir = ROOT_PATH . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook: " . $json . "\n", 3, $logDir . '/webhook.log');

        $result = $gateway->handleWebhook($payload);
        
        if ($result['status'] === 'INVALID_SIGNATURE') {
            error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook Error: Signature mismatch.\n", 3, $logDir . '/webhook.log');
            $response->json(['status' => 'error', 'message' => $result['message']], 401);
            return;
        }

        if ($result['status'] === 'IGNORE') {
            $response->json(['status' => 'success', 'message' => $result['message']], 200);
            return;
        }

        $orderId = $result['order_id'];
        if (!$orderId) {
            $response->json(['status' => 'error', 'message' => 'Missing local order ID mapping'], 400);
            return;
        }

        $paymentModel = new Payment();
        $orderModel = new Order();
        
        $payment = $paymentModel->findByOrderId((int)$orderId);
        if (!$payment) {
            $response->json(['status' => 'error', 'message' => 'Payment record not found'], 404);
            return;
        }

        $order = $orderModel->findWithDetails((int)$orderId);
        if (!$order) {
            $response->json(['status' => 'error', 'message' => 'Order record not found'], 404);
            return;
        }

        if ($result['status'] === 'PAID') {
            // 1. Verifikasi Kecocokan Nominal Pembayaran
            $expectedAmount = (float)$order['total_price'];
            $grossAmount = (float)$result['gross_amount'];
            if (abs($expectedAmount - $grossAmount) > 1.0) {
                error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook Error: Gross amount mismatch. Expected: {$expectedAmount}, Got: {$grossAmount} for Order {$orderId}.\n", 3, $logDir . '/webhook.log');
                $response->json(['status' => 'error', 'message' => 'Nominal pembayaran tidak sesuai dengan total tagihan pesanan'], 400);
                return;
            }

            // 2. Idempotensi Webhook: Jika transaksi sudah PAID sebelumnya, abaikan (mencegah pesan WhatsApp ganda)
            if ($payment['status'] === 'PAID') {
                error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook: Order {$orderId} already marked as PAID. Duplicate webhook ignored.\n", 3, $logDir . '/webhook.log');
                $response->json(['status' => 'success', 'message' => 'Payment already processed (Idempotent)'], 200);
                return;
            }

            // 3. Ubah status pembayaran menjadi PAID dan catat waktu pembayaran
            $paymentModel->markAsPaid((int)$orderId, $payment['method'] ?? 'ONLINE_QRIS', $result['transaction_id']);
            
            // 4. Update status pesanan (Pembayaran berhasil tidak otomatis COMPLETED)
            $newOrderStatus = ($order['status'] === 'MENUNGGU_PEMBAYARAN') ? 'SEDANG_DICUCI' : $order['status'];
            $orderModel->updateStatus(
                (int)$orderId,
                $newOrderStatus,
                $order['customer_id'],
                "Pembayaran " . ($payment['method'] ?? 'QRIS') . " (#{$result['transaction_id']}) berhasil diverifikasi LUNAS via Midtrans. Cucian masuk proses pencucian."
            );
            
            // 5. In-app notification to customer
            $notifModel = new \App\Models\Notification();
            $notifModel->createNotification(
                (int)$order['customer_id'],
                'Pembayaran Berhasil Diverifikasi! 🎉',
                "Pembayaran pesanan #{$order['order_code']} via " . ($payment['method'] ?? 'QRIS') . " berhasil dikonfirmasi LUNAS. Cucian Anda sedang diproses.",
                'payment',
                '/order/' . $orderId . '/payment',
                (int)$orderId
            );

            // 6. Notifikasi Kedua: Kirim WhatsApp Pembayaran Berhasil via Fonnte
            // Kegagalan pengiriman Fonnte tidak boleh membatalkan transaksi pembayaran yang valid di DB
            try {
                $userModel = new \App\Models\User();
                $customerUser = $userModel->find((int)$order['customer_id']);
                $waService = new \App\Services\WhatsAppNotificationService();
                if ($customerUser) {
                    $deliveryInfo = 'Pesanan Anda akan segera diproses untuk pengantaran oleh kurir.';
                    $waService->notifyPaymentSuccess(
                        $customerUser['phone'] ?? '081234567890',
                        $customerUser['name'] ?? 'Pelanggan',
                        $order['order_code'],
                        (float)$result['gross_amount'],
                        $result['transaction_id'],
                        $order['store_name'] ?? 'SmartLaundry',
                        (int)$orderId,
                        (int)$order['customer_id'],
                        $payment['method'] ?? 'QRIS',
                        $deliveryInfo
                    );
                }

                // 7. Notifikasi Staf Outlet / Toko
                $waService->notifyStorePaymentReceived(
                    (int)$order['store_id'],
                    $order['order_code'],
                    (float)$result['gross_amount'],
                    (int)$orderId,
                    $payment['method'] ?? 'QRIS'
                );
            } catch (\Throwable $waErr) {
                error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook Warning: WA notification error (payment preserved as PAID): " . $waErr->getMessage() . "\n", 3, $logDir . '/webhook.log');
            }
            
            error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook: Order {$orderId} marked as PAID.\n", 3, $logDir . '/webhook.log');

        } else if ($result['status'] === 'EXPIRED') {
            // Transaksi yang sudah PAID tidak dapat dianggap EXPIRED
            if ($payment['status'] === 'PAID') {
                error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook: Order {$orderId} is already PAID. Expired webhook ignored.\n", 3, $logDir . '/webhook.log');
                $response->json(['status' => 'success', 'message' => 'Order is already PAID, expired event ignored'], 200);
                return;
            }
            if ($payment['status'] === 'PENDING') {
                $paymentModel->update($payment['id'], ['expired_at' => date('Y-m-d H:i:s', time() - 60)]);
                $paymentModel->checkAndExpire((int)$payment['id']);
                error_log("[" . date('Y-m-d H:i:s') . "] Midtrans Webhook: Order {$orderId} marked as EXPIRED.\n", 3, $logDir . '/webhook.log');
            }
        }

        $response->json(['status' => 'success', 'message' => 'Webhook processed']);
    }
}
