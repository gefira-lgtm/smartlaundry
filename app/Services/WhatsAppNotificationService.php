<?php
namespace App\Services;

use App\Models\WhatsAppLog;
use App\Models\WhatsAppNotificationSetting;
use App\Models\WhatsAppNotificationRecipient;
use App\Models\WhatsAppNotificationTemplate;
use App\Models\User;
use App\Models\Store;
use App\Models\Setting;
use App\Core\Database;
use App\Services\WhatsApp\WhatsAppGatewayFactory;

class WhatsAppNotificationService {
    private WhatsAppLog $logModel;
    private WhatsAppNotificationSetting $settingModel;
    private WhatsAppNotificationRecipient $recipientModel;
    private WhatsAppNotificationTemplate $templateModel;
    private array $templates;

    public function __construct() {
        $this->logModel = new WhatsAppLog();
        $this->settingModel = new WhatsAppNotificationSetting();
        $this->recipientModel = new WhatsAppNotificationRecipient();
        $this->templateModel = new WhatsAppNotificationTemplate();
        $this->loadTemplates();
    }

    /**
     * Load notification templates from config or fallback settings
     */
    private function loadTemplates(): void {
        $templateFile = ROOT_PATH . '/config/notification_templates.php';
        if (file_exists($templateFile)) {
            $this->templates = require $templateFile;
        } else {
            $this->templates = [];
        }
    }

    /**
     * Get template string with setting fallback and category support
     */
    public function getTemplate(string $key, string $default = '', ?int $storeId = null, string $category = 'TRANSACTION'): string {
        $category = strtoupper($category);
        return $this->settingModel->getTemplate($category, $key, $storeId, $this->templates[$key] ?? $default);
    }

    /**
     * Format action URL: if APP_URL is localhost or empty, return empty string
     */
    public function formatActionLink(?string $path = null): string {
        $appUrl = env('APP_URL', '');
        if (empty($appUrl) || stripos($appUrl, 'localhost') !== false || stripos($appUrl, '127.0.0.1') !== false) {
            return '';
        }
        $full = rtrim($appUrl, '/') . ($path ? '/' . ltrim($path, '/') : '');
        return "\nLihat detail: " . $full;
    }

    /**
     * Core Outbox Send Function
     * 
     * @param string $phoneNumber Recipient phone
     * @param string|null $recipientName Name of recipient
     * @param string $event Event identifier (e.g. ORDER_CREATED, PAYMENT_SUCCESS)
     * @param string $message Compiled message text
     * @param string|null $dedupeKey Unique deduplication key
     * @param int|null $recipientUserId User ID for preference check
     * @param bool $isOtp True if message is critical OTP
     * @param string $category Category: OTP, TRANSACTION, or ACTIVITY
     * @param int|null $storeId Store ID for scoping
     * @param int|null $orderId Order ID for tracing
     * @return bool
     */
    public function send(
        string $phoneNumber,
        ?string $recipientName,
        string $event,
        string $message,
        ?string $dedupeKey = null,
        ?int $recipientUserId = null,
        bool $isOtp = false,
        string $category = 'TRANSACTION',
        ?int $storeId = null,
        ?int $orderId = null,
        bool $force = false
    ): bool {
        $category = strtoupper(trim($category));
        if ($isOtp) {
            $category = 'OTP';
        }

        // 1. Category and Event toggle check
        if ($category === 'OTP') {
            if (!$this->settingModel->isCategoryEnabled('OTP')) {
                return false;
            }
            if (!$this->settingModel->isEnabled('OTP', $event)) {
                return false;
            }
        } else {
            if (!$this->settingModel->isCategoryEnabled($category, $storeId)) {
                return false;
            }
            if (!$this->settingModel->isEnabled($category, $event, $storeId)) {
                return false;
            }

            // 2. User preference check (users.wa_notifications_enabled)
            if ($recipientUserId !== null) {
                try {
                    $db = Database::getConnection();
                    $stmt = $db->prepare("SELECT wa_notifications_enabled FROM `users` WHERE `id` = :uid LIMIT 1");
                    $stmt->execute(['uid' => $recipientUserId]);
                    $optIn = $stmt->fetchColumn();
                    if ($optIn !== false && (int)$optIn === 0) {
                        return false; // User has opted out of WhatsApp transaction notifications
                    }
                } catch (\Throwable $e) {}
            }
        }

        // 3. Normalize phone number
        $normalizedPhone = normalize_phone_number($phoneNumber);
        if (empty($normalizedPhone)) {
            $normalizedPhone = '6281200000000';
        }

        // 3b. Phone Override Mode (redirect all WA to test number, per recipient type)
        $overrideEnabled = (bool)\App\Models\Setting::get('wa_override_enabled', false);
        if ($overrideEnabled && !$isOtp && $category !== 'OTP') {
            // Determine recipient type from event name and category
            $overridePhone = null;
            $eventUpper = strtoupper($event);
            $isCourierEvent = str_contains($eventUpper, 'COURIER') || str_contains($eventUpper, 'PICKUP') || str_contains($eventUpper, 'KURIR') || $category === 'ACTIVITY';
            $isAdminEvent = str_contains($eventUpper, 'ADMIN') || str_contains($eventUpper, 'STORE_STAFF') || str_contains($eventUpper, 'NEW_ORDER_STORE') || str_contains($eventUpper, 'STAFF');

            if ($isCourierEvent && !$isAdminEvent) {
                $rawOverride = trim((string)\App\Models\Setting::get('wa_override_courier_phone', ''));
                if (!empty($rawOverride)) {
                    $overridePhone = normalize_phone_number($rawOverride) ?: $rawOverride;
                }
            } elseif ($isAdminEvent) {
                $rawOverride = trim((string)\App\Models\Setting::get('wa_override_admin_phone', ''));
                if (!empty($rawOverride)) {
                    $overridePhone = normalize_phone_number($rawOverride) ?: $rawOverride;
                }
            } else {
                // customer / default
                $rawOverride = trim((string)\App\Models\Setting::get('wa_override_customer_phone', ''));
                if (!empty($rawOverride)) {
                    $overridePhone = normalize_phone_number($rawOverride) ?: $rawOverride;
                }
            }

            if (!empty($overridePhone)) {
                $normalizedPhone = $overridePhone;
                // Mark recipient name so logs clearly show it's overridden
                $recipientName = '[TEST] ' . ($recipientName ?? 'Override');
            }
        }

        // 4. Deduplication check (Idempotency)
        if (!empty($dedupeKey) && !$force) {
            $existing = $this->logModel->findByDedupeKey($dedupeKey);
            if ($existing) {
                return true; // Already processed & recorded, avoid duplicate sending
            }
        }
        if ($force && !empty($dedupeKey)) {
            $dedupeKey .= ':MANUAL:' . microtime(true);
        }

        // 5. Non-production phone allowlist security check (A4)
        $appEnv = strtolower((string)env('APP_ENV', 'local'));
        if ($appEnv !== 'production' && !$isOtp && $category !== 'OTP' && !$force) {
            $allowedRaw = trim((string)env('WHATSAPP_ALLOWED_NUMBERS', ''));
            if (!empty($allowedRaw)) {
                $allowedList = array_filter(array_map('trim', explode(',', $allowedRaw)));
                $normalizedAllowed = array_filter(array_map('normalize_phone_number', $allowedList));

                // If allowed numbers are configured and recipient is not among them:
                if (!empty($normalizedAllowed) && !in_array($normalizedPhone, $normalizedAllowed, true)) {
                    try {
                        $logId = $this->logModel->createPending($normalizedPhone, $recipientName, $event, $message, $dedupeKey, $category, $storeId, $orderId);
                        if ($logId > 0) {
                            $this->logModel->updateStatus($logId, 'SKIPPED_NOT_ALLOWED', 'Nomor tidak terdaftar dalam WHATSAPP_ALLOWED_NUMBERS (mode non-produksi)');
                        }
                    } catch (\Throwable $th) {}
                    return true;
                }
            }
        }

        // 6. Record to Outbox with PENDING status (masks sensitive OTP codes)
        $logId = 0;
        try {
            $logId = $this->logModel->createPending($normalizedPhone, $recipientName, $event, $message, $dedupeKey, $category, $storeId, $orderId);
        } catch (\Throwable $th) {
            // If duplicate race condition, ignore
            return true;
        }

        // 7. Attempt immediate dispatch via Gateway
        try {
            $gateway = WhatsAppGatewayFactory::create();
            $res = $gateway->send($normalizedPhone, $message);

            if (!empty($res['success'])) {
                if ($logId > 0) {
                    $providerMsgId = $res['provider_message_id'] ?? null;
                    $this->logModel->updateStatus($logId, $res['status'] ?? 'SENT', null, null, false, $providerMsgId);
                }
                return true;
            } else {
                $errorMsg = $res['error'] ?? 'Gagal mengirim pesan via WhatsApp Gateway';
                $nextRetry = date('Y-m-d H:i:s', time() + 300); // 5 minutes backoff
                if ($logId > 0) {
                    $this->logModel->updateStatus($logId, 'FAILED', $errorMsg, $nextRetry, true);
                }
                return false;
            }
        } catch (\Throwable $e) {
            if ($logId > 0) {
                $this->logModel->updateStatus($logId, 'FAILED', $e->getMessage(), date('Y-m-d H:i:s', time() + 300), true);
            }
            return false;
        }
    }

    // =========================================================================
    // =========================================================================
    // HELPER: ORDER CONTEXT & CUSTOMER TEMPLATING
    // =========================================================================

    /**
     * Fetch complete order details and customer profile from database
     */
    public function getOrderDetails(string $orderCode, ?int $orderId = null): array {
        try {
            $db = \App\Core\Database::getConnection();
            $order = null;
            $orderModel = new \App\Models\Order();

            if ($orderId) {
                $order = $orderModel->findWithDetails($orderId);
            }
            if (!$order && !empty($orderCode)) {
                $stmt = $db->prepare("SELECT id FROM `orders` WHERE order_code = :code LIMIT 1");
                $stmt->execute(['code' => $orderCode]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['id'])) {
                    $order = $orderModel->findWithDetails((int)$row['id']);
                }
            }
            if (is_array($order) && !empty($order['courier_id'])) {
                try {
                    $stmtC = $db->prepare("SELECT vehicle_type, license_plate FROM `couriers` WHERE user_id = :uid LIMIT 1");
                    $stmtC->execute(['uid' => (int)$order['courier_id']]);
                    $cr = $stmtC->fetch(\PDO::FETCH_ASSOC);
                    if ($cr) {
                        $order['vehicle_type'] = $cr['vehicle_type'] ?? 'Motor';
                        $order['license_plate'] = $cr['license_plate'] ?? '-';
                    }
                } catch (\Throwable $e) {}
            }
            return is_array($order) ? $order : [];
        } catch (\Throwable $th) {
            return [];
        }
    }

    /**
     * Extract formatted service names from order details
     */
    private function extractServiceName(array $order): string {
        if (!empty($order['items']) && is_array($order['items'])) {
            $names = [];
            foreach ($order['items'] as $item) {
                if (!empty($item['service_name'])) {
                    $names[] = $item['service_name'];
                }
            }
            if (!empty($names)) {
                return implode(', ', array_unique($names));
            }
        }
        return 'Layanan Laundry';
    }

    /**
     * Render customer notification message with full placeholder support
     */
    public function renderCustomerMessage(string $template, array $data): string {
        $customerName = !empty($data['nama_customer']) ? $data['nama_customer'] : (!empty($data['customer_name']) ? $data['customer_name'] : 'Pelanggan');
        $orderCode = !empty($data['nomor_pesanan']) ? $data['nomor_pesanan'] : (!empty($data['order_code']) ? $data['order_code'] : '-');
        $storeName = !empty($data['store_name']) ? $data['store_name'] : 'SmartLaundry';
        $serviceName = !empty($data['nama_layanan']) ? $data['nama_layanan'] : (!empty($data['service_name']) ? $data['service_name'] : 'Layanan Laundry');
        $courierName = !empty($data['nama_kurir']) ? $data['nama_kurir'] : (!empty($data['courier_name']) ? $data['courier_name'] : 'Kurir SmartLaundry');
        $courierPhone = !empty($data['wa_kurir']) ? $data['wa_kurir'] : (!empty($data['courier_phone']) ? $data['courier_phone'] : '-');
        $licensePlate = !empty($data['plat_nomor']) ? $data['plat_nomor'] : (!empty($data['license_plate']) ? $data['license_plate'] : '-');
        $vehicleType = !empty($data['jenis_kendaraan']) ? $data['jenis_kendaraan'] : (!empty($data['vehicle_type']) ? $data['vehicle_type'] : 'Motor');
        $payDeadline = !empty($data['batas_pembayaran']) ? $data['batas_pembayaran'] : (!empty($data['pay_deadline']) ? $data['pay_deadline'] : '1 jam ke depan');
        $method = !empty($data['method']) ? $data['method'] : 'QRIS';
        $link = !empty($data['link']) ? $data['link'] : '';

        $weight = isset($data['weight']) ? (float)$data['weight'] : 0.0;
        $total = isset($data['total']) ? (float)$data['total'] : 0.0;

        $weightNum = ($weight > 0) ? number_format($weight, 1, ',', '.') : '-';
        $totalFormatted = ($total > 0) ? 'Rp ' . number_format($total, 0, ',', '.') : 'Rp 0';

        $msg = $template;

        // If template has '{berat} KG' and weight is not available/0
        if (strpos($msg, '{berat} KG') !== false) {
            $msg = str_replace('{berat} KG', ($weight > 0 ? $weightNum . ' KG' : '-'), $msg);
        }

        $replacements = [
            '{nama_customer}' => $customerName,
            '{customer_name}' => $customerName,
            '{nomor_pesanan}' => $orderCode,
            '{order_code}'    => $orderCode,
            '{nama_layanan}'  => $serviceName,
            '{service_name}'  => $serviceName,
            '{berat}'         => ($weight > 0 ? $weightNum : '-'),
            '{weight}'        => ($weight > 0 ? $weightNum : '-'),
            '{total_harga}'   => $totalFormatted,
            '{total}'         => $totalFormatted,
            '{total_price}'   => $totalFormatted,
            '{amount}'        => $totalFormatted,
            '{nama_kurir}'    => $courierName,
            '{courier_name}'  => $courierName,
            '{wa_kurir}'      => $courierPhone,
            '{courier_phone}' => $courierPhone,
            '{nomor_kurir}'   => $courierPhone,
            '{plat_nomor}'    => $licensePlate,
            '{license_plate}' => $licensePlate,
            '{plate_number}'  => $licensePlate,
            '{jenis_kendaraan}' => $vehicleType,
            '{vehicle_type}'  => $vehicleType,
            '{store_name}'    => $storeName,
            '{nama_toko}'     => $storeName,
            '{activity_time}' => !empty($data['activity_time']) ? $data['activity_time'] : (date('d/m/Y H:i') . ' WIB'),
            '{waktu_aktivitas}' => !empty($data['waktu_aktivitas']) ? $data['waktu_aktivitas'] : (!empty($data['activity_time']) ? $data['activity_time'] : (date('d/m/Y H:i') . ' WIB')),
            '{waktu_penjemputan}' => !empty($data['waktu_penjemputan']) ? $data['waktu_penjemputan'] : (!empty($data['activity_time']) ? $data['activity_time'] : (date('d/m/Y H:i') . ' WIB')),
            '{waktu_penyerahan}'  => !empty($data['waktu_penyerahan']) ? $data['waktu_penyerahan'] : (!empty($data['activity_time']) ? $data['activity_time'] : (date('d/m/Y H:i') . ' WIB')),
            '{batas_pembayaran}' => $payDeadline,
            '{waktu_kedaluwarsa}' => !empty($data['waktu_kedaluwarsa']) ? $data['waktu_kedaluwarsa'] : $payDeadline,
            '{pay_deadline}'  => $payDeadline,
            '{method}'        => $method,
            '{metode_pembayaran}' => !empty($data['metode_pembayaran']) ? $data['metode_pembayaran'] : $method,
            '{qr_code_pembayaran}' => !empty($data['qr_code_pembayaran']) ? $data['qr_code_pembayaran'] : (!empty($data['qris_image_url']) ? $data['qris_image_url'] : (!empty($data['qris_payload']) ? $data['qris_payload'] : '-')),
            '{status_pengantaran}' => !empty($data['status_pengantaran']) ? $data['status_pengantaran'] : 'Pesanan Anda akan segera diproses untuk pengantaran oleh kurir.',
            '{otp_code}'      => !empty($data['otp_code']) ? $data['otp_code'] : '',
            '{expiry_minutes}' => !empty($data['expiry_minutes']) ? (string)$data['expiry_minutes'] : '5',
            '{link}'          => $link
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $msg);
    }

    /**
     * Send multiple separate WhatsApp chat messages sequentially for an event
     * Each message has its own dedupe key, separate Fonnte dispatch, and separate log entry.
     */
    public function sendEventMultiMessages(
        string $category,
        string $eventKey,
        string $phone,
        string $recipientName,
        array $placeholders,
        ?string $dedupeBase = null,
        ?int $userId = null,
        ?int $storeId = null,
        ?int $orderId = null,
        bool $force = false
    ): bool {
        $category = strtoupper(trim($category));
        $eventKey = strtolower(trim($eventKey));

        // 1. Check if category master switch is enabled
        if (!$this->settingModel->isCategoryEnabled($category, $storeId)) {
            return false;
        }

        // 2. Check if event master switch is enabled
        if (!$this->settingModel->isEnabled($category, $eventKey, $storeId)) {
            return false;
        }

        // 3. Fetch all active messages configured for this event
        $messages = $this->templateModel->getEventMessages($category, $eventKey, $storeId, true);
        if (empty($messages)) {
            return false;
        }

        $sentAny = false;
        $count = count($messages);

        foreach ($messages as $idx => $msgItem) {
            $seq = (int)($msgItem['message_sequence'] ?? ($idx + 1));
            $rawTpl = $msgItem['template_content'];

            // Render message with placeholders
            $renderedMsg = $this->renderCustomerMessage($rawTpl, $placeholders);

            // Sequence-specific deduplication key for idempotency
            $seqDedupe = $dedupeBase ? "{$dedupeBase}:SEQ:{$seq}" : null;

            // Dispatch as a separate chat
            $isSent = $this->send(
                $phone,
                $recipientName,
                $eventKey . ':SEQ' . $seq,
                $renderedMsg,
                $seqDedupe,
                $userId,
                false,
                $category,
                $storeId,
                $orderId,
                $force
            );

            if ($isSent) {
                $sentAny = true;
            }

            // Small pause between sequential chats so Fonnte/WhatsApp delivers in order
            if ($count > 1 && $idx < $count - 1) {
                usleep(250000); // 250ms
            }
        }

        return $sentAny;
    }

    // =========================================================================
    // CUSTOMER NOTIFICATIONS (8 PRODUCTION STATUSES & LIFECYCLE EVENTS)
    // =========================================================================

    /**
     * 1. Pesanan dibuat: pesanan berhasil diterima
     */
    public function notifyOrderCreated(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;

        $link = $this->formatActionLink('dashboard/customer');
        $placeholders = [
            'nama_customer' => $customerName,
            'customer_name' => $customerName,
            'nomor_pesanan' => $orderCode,
            'order_code'    => $orderCode,
            'nama_layanan'  => $serviceName,
            'service_name'  => $serviceName,
            'store_name'    => $storeName,
            'total'         => $totalPrice,
            'weight'        => $weight,
            'link'          => $link
        ];

        $dedupeBase = $orderId && $userId ? "ORDER_CREATED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->sendEventMultiMessages(
            'TRANSACTION',
            'customer_order_created',
            $phone,
            $customerName,
            $placeholders,
            $dedupeBase,
            $userId,
            !empty($order['store_id']) ? (int)$order['store_id'] : null,
            $orderId
        );
    }

    public function notifyOrderConfirmed(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_order_confirmed', "Halo, {nama_customer}! 👋\n\nKabar baik! Pesanan {nomor_pesanan} sudah dikonfirmasi {store_name}. Kurir kami akan segera menjemput cucian Anda.{link}");
        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'store_name' => $storeName,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "ORDER_CONFIRMED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'ORDER_CONFIRMED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId);
    }

    public function notifyCourierAssigned(string $phone, string $customerName, string $orderCode, string $courierName, string $courierPhone, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $link = $this->formatActionLink('tracking/' . $orderCode);
        $tpl = $this->getTemplate('customer_courier_assigned', "Halo, {nama_customer}! 👋\n\nKurir {nama_kurir} telah ditugaskan untuk menjemput cucian pesanan {nomor_pesanan} di {store_name}. Mohon siapkan pakaian cucian Anda ya.{link}");
        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_kurir' => $courierName,
            'store_name' => $storeName,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "COURIER_ASSIGNED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'COURIER_ASSIGNED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId);
    }

    /**
     * 2. Kurir menjemput: kurir sedang menuju lokasi customer
     */
    public function notifyCourierToPickup(string $phone, string $customerName, string $orderCode, string $courierName = 'Kurir SmartLaundry', string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if (!empty($order['courier_name'])) $courierName = $order['courier_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;
        $courierPhone = !empty($order['courier_phone']) ? $order['courier_phone'] : '-';
        $licensePlate = !empty($order['license_plate']) ? $order['license_plate'] : '-';
        $vehicleType = !empty($order['vehicle_type']) ? $order['vehicle_type'] : 'Motor';

        $link = $this->formatActionLink('tracking/' . $orderCode);

        $placeholders = [
            'nama_customer' => $customerName,
            'customer_name' => $customerName,
            'nomor_pesanan' => $orderCode,
            'order_code'    => $orderCode,
            'nama_kurir'    => $courierName,
            'courier_name'  => $courierName,
            'wa_kurir'      => $courierPhone,
            'courier_phone' => $courierPhone,
            'plat_nomor'    => $licensePlate,
            'license_plate' => $licensePlate,
            'jenis_kendaraan' => $vehicleType,
            'vehicle_type'  => $vehicleType,
            'nama_layanan'  => $serviceName,
            'service_name'  => $serviceName,
            'store_name'    => $storeName,
            'total'         => $totalPrice,
            'weight'        => $weight,
            'link'          => $link
        ];

        $dedupeBase = $orderId && $userId ? "COURIER_DISPATCHED:{$orderId}:{$userId}:CUSTOMER_PICKUP" : null;
        return $this->sendEventMultiMessages(
            'TRANSACTION',
            'customer_courier_to_pickup',
            $phone,
            $customerName,
            $placeholders,
            $dedupeBase,
            $userId,
            !empty($order['store_id']) ? (int)$order['store_id'] : null,
            $orderId,
            $force
        );
    }

    public function notifyCourierDispatched(string $phone, string $customerName, string $orderCode, string $courierName, string $courierPhone, string $type = 'JEMPUT', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        if ($type === 'JEMPUT') {
            return $this->notifyCourierToPickup($phone, $customerName, $orderCode, $courierName, 'SmartLaundry', $orderId, $userId, $force);
        }
        return $this->notifyCourierToDelivery($phone, $customerName, $orderCode, $courierName, 'SmartLaundry', $orderId, $userId, $force);
    }

    public function notifyLaundryPicked(string $phone, string $customerName, string $orderCode, string $courierName = 'Kurir SmartLaundry', string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if (!empty($order['courier_name'])) $courierName = $order['courier_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_laundry_picked', "Halo, {nama_customer}! 👋\n\nCucian Anda untuk pesanan {nomor_pesanan} sudah dijemput oleh kurir {nama_kurir} dan sedang dibawa ke {store_name} untuk ditimbang.{link}");
        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_kurir' => $courierName,
            'store_name' => $storeName,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "LAUNDRY_PICKED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'LAUNDRY_PICKED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    /**
     * 3. Laundry diterima toko: laundry berhasil diterima dan sedang ditimbang
     */
    public function notifyArrivedAtStore(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_arrived_at_store', "Halo, {nama_customer}! 👋\n\nCucian Anda telah tiba dan berhasil diterima di outlet {store_name}. Saat ini sedang dalam antrean penimbangan. ⚖️\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Laundry Diterima Toko\n\nRincian tagihan pembayaran akan dikirimkan otomatis setelah proses penimbangan selesai.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}");

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_layanan' => $serviceName,
            'store_name' => $storeName,
            'total' => $totalPrice,
            'weight' => $weight,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "ARRIVED_AT_STORE:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'ARRIVED_AT_STORE', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    /**
     * Notifikasi Pertama: Laundry Selesai / Siap Dibayar (Metode QRIS Dinamis)
     * Mengirimkan detail pesanan, berat laundry, total pembayaran, status Menunggu Pembayaran, QRIS dinamis, dan batas waktu pembayaran.
     */
    public function notifyReadyToPayQRIS(
        string $phone,
        string $customerName,
        string $orderCode,
        float $totalAmount,
        float $weight,
        string $qrCode,
        string $expiryTime,
        string $storeName = 'SmartLaundry',
        ?int $orderId = null,
        ?int $userId = null,
        ?int $paymentId = null,
        bool $force = false
    ): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
            if ($weight <= 0 && !empty($order['weight_actual'])) $weight = (float)$order['weight_actual'];
            if ($totalAmount <= 0 && !empty($order['total_price'])) $totalAmount = (float)$order['total_price'];
        }

        $serviceName = $this->extractServiceName($order);
        $link = $this->formatActionLink('order/' . ($orderId ?? $orderCode) . '/payment');

        $defaultTpl = "Halo, {nama_customer}! 👋\n\nKabar baik! Laundry Anda sudah selesai diproses. 🎉\n\nBerikut detail pesanan Anda:\n\nNomor Pesanan: {nomor_pesanan}\nBerat Laundry: {berat} KG\nTotal Pembayaran: {total_harga}\nStatus: Menunggu Pembayaran\n\nSilakan lakukan pembayaran menggunakan QRIS berikut.\n\nQRIS: {qr_code_pembayaran}\n\nBatas pembayaran: {waktu_kedaluwarsa}\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}";
        $tpl = $this->getTemplate('customer_ready_to_pay_qris', $defaultTpl);

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_layanan' => $serviceName,
            'store_name' => $storeName,
            'total' => $totalAmount,
            'weight' => $weight,
            'qr_code_pembayaran' => $qrCode,
            'waktu_kedaluwarsa' => $expiryTime,
            'batas_pembayaran' => $expiryTime,
            'method' => 'QRIS',
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "WEIGHING_COMPLETED:{$orderId}:{$userId}:CUSTOMER" : ($orderId ? "WEIGHING_COMPLETED:{$orderId}:CUSTOMER" : null);
        return $this->send($phone, $customerName, 'WEIGHING_COMPLETED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    /**
     * Notifikasi Pertama: Laundry Selesai / Siap Dibayar (Metode Tunai / CASH)
     * Tidak menyertakan QRIS dinamis dan mengarahkan customer untuk menyiapkan uang tunai.
     */
    public function notifyReadyToPayCash(
        string $phone,
        string $customerName,
        string $orderCode,
        float $totalAmount,
        float $weight,
        string $storeName = 'SmartLaundry',
        ?int $orderId = null,
        ?int $userId = null,
        bool $force = false
    ): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
            if ($weight <= 0 && !empty($order['weight_actual'])) $weight = (float)$order['weight_actual'];
            if ($totalAmount <= 0 && !empty($order['total_price'])) $totalAmount = (float)$order['total_price'];
        }

        $serviceName = $this->extractServiceName($order);
        $link = $this->formatActionLink('dashboard/customer');

        $defaultTpl = "Halo, {nama_customer}! 👋\n\nKabar baik! Laundry Anda sudah selesai diproses. 🎉\n\nBerikut detail pesanan Anda:\n\nNomor Pesanan: {nomor_pesanan}\nBerat Laundry: {berat} KG\nTotal Pembayaran: {total_harga}\nMetode Pembayaran: Tunai (CASH)\nStatus: Menunggu Pembayaran\n\nSilakan siapkan pembayaran tunai saat serah terima cucian.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}";
        $tpl = $this->getTemplate('customer_ready_to_pay_cash', $defaultTpl);

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_layanan' => $serviceName,
            'store_name' => $storeName,
            'total' => $totalAmount,
            'weight' => $weight,
            'method' => 'CASH',
            'link' => $link
        ]);

        $dedupe = $orderId ? "READY_TO_PAY_CASH:{$orderId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'READY_TO_PAY_CASH', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    /**
     * 4. Menunggu pembayaran: informasi total biaya dan instruksi pembayaran
     */
    public function notifyWeighingCompleted(string $phone, string $customerName, string $orderCode, string $storeName, float $totalAmount, float $weight, ?string $expiryTime = null, ?int $orderId = null, ?int $userId = null): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
            if ($weight <= 0 && !empty($order['weight_actual'])) $weight = (float)$order['weight_actual'];
            if ($totalAmount <= 0 && !empty($order['total_price'])) $totalAmount = (float)$order['total_price'];
            if (empty($expiryTime) && !empty($order['payment_expired_at'])) {
                $expiryTime = date('d/m/Y H:i', strtotime($order['payment_expired_at'])) . ' WIB';
            }
        }

        $paymentMethod = $order['payment_method'] ?? 'ONLINE_QRIS';
        if ($paymentMethod === 'CASH') {
            return $this->notifyReadyToPayCash($phone, $customerName, $orderCode, $totalAmount, $weight, $storeName, $orderId, $userId);
        }

        // Check if QRIS payment already generated in DB
        $qrisCode = '';
        $paymentId = null;
        try {
            if ($orderId) {
                $db = \App\Core\Database::getConnection();
                $stmt = $db->prepare("SELECT id, qris_image_url, qris_payload, expired_at FROM `payments` WHERE order_id = :oid LIMIT 1");
                $stmt->execute(['oid' => $orderId]);
                $pRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($pRow) {
                    $paymentId = (int)$pRow['id'];
                    $qrisCode = !empty($pRow['qris_image_url']) ? $pRow['qris_image_url'] : ($pRow['qris_payload'] ?? '');
                    if (empty($expiryTime) && !empty($pRow['expired_at'])) {
                        $expiryTime = date('d/m/Y H:i', strtotime($pRow['expired_at'])) . ' WIB';
                    }
                }
            }
        } catch (\Throwable $th) {}

        if (empty($qrisCode)) {
            $qrisCode = $this->formatActionLink('order/' . ($orderId ?? $orderCode) . '/payment');
        }

        $deadlineText = $expiryTime ? $expiryTime : '1 jam ke depan';
        return $this->notifyReadyToPayQRIS($phone, $customerName, $orderCode, $totalAmount, $weight, $qrisCode, $deadlineText, $storeName, $orderId, $userId, $paymentId);
    }

    public function notifyPaymentExpiringSoon(string $phone, string $customerName, string $orderCode, string $storeName, float $totalAmount, int $remainingMinutes = 15, ?int $orderId = null, ?int $userId = null): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
            if ($totalAmount <= 0 && !empty($order['total_price'])) $totalAmount = (float)$order['total_price'];
        }

        $deadlineText = "{$remainingMinutes} menit lagi";
        $link = $this->formatActionLink('payment/' . ($orderId ?? $orderCode));
        $tpl = $this->getTemplate('customer_payment_expiring_soon', "Halo, {nama_customer}! 👋\n\nPengingat: Batas waktu pembayaran pesanan {nomor_pesanan} di {store_name} sebesar {total_harga} akan berakhir pada {batas_pembayaran}. Yuk segera selesaikan pembayaran agar cucian langsung diproses.{link}");

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'store_name' => $storeName,
            'total' => $totalAmount,
            'batas_pembayaran' => $deadlineText,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "PAYMENT_EXPIRING_SOON:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'PAYMENT_EXPIRING_SOON', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId);
    }

    /**
     * Notifikasi Kedua: Pembayaran Berhasil Diterima
     * Mengirimkan detail nomor pesanan, total pembayaran, metode pembayaran (QRIS / CASH), status LUNAS,
     * serta informasi bahwa pesanan siap diantar oleh kurir jika masih menunggu pengantaran.
     */
    public function notifyPaymentSuccess(
        string $phone,
        string $customerName,
        string $orderCode,
        float $amount,
        ?string $txnRef = null,
        string $storeName = 'SmartLaundry',
        ?int $orderId = null,
        ?int $userId = null,
        string $method = 'QRIS',
        ?string $deliveryStatusInfo = null,
        bool $force = false
    ): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
            if ($amount <= 0 && !empty($order['total_price'])) $amount = (float)$order['total_price'];
        }

        $link = $this->formatActionLink('dashboard/customer');
        $deliveryInfo = $deliveryStatusInfo ?? 'Pesanan Anda akan segera diproses untuk pengantaran oleh kurir.';

        $defaultTpl = "Halo, {nama_customer}! 👋\n\nPembayaran laundry Anda telah BERHASIL DITERIMA. 🎉\n\nDetail pembayaran:\n\nNomor Pesanan: {nomor_pesanan}\nTotal Pembayaran: {total_harga}\nMetode Pembayaran: {metode_pembayaran}\nStatus Pembayaran: LUNAS\n\nTerima kasih telah mempercayakan laundry Anda kepada SmartLaundry. Kami senang dapat melayani Anda! ❤️\n\n{status_pengantaran}{link}";
        $tpl = $this->getTemplate('customer_payment_success', $defaultTpl);

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'total' => $amount,
            'method' => $method,
            'metode_pembayaran' => $method,
            'status_pengantaran' => $deliveryInfo,
            'store_name' => $storeName,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "PAYMENT_SUCCESS:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'PAYMENT_SUCCESS', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    public function notifyPaymentExpired(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_payment_expired', "Halo, {nama_customer}! 👋\n\nBatas waktu pembayaran pesanan {nomor_pesanan} telah kadaluarsa. Pakaian Anda akan diproses untuk pengembalian ke lokasi Anda.{link}");
        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'store_name' => $storeName,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "PAYMENT_EXPIRED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'PAYMENT_EXPIRED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId);
    }

    /**
     * 5. Sedang diproses: laundry sedang dicuci
     */
    public function notifyLaundryWashing(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_laundry_washing', "Halo, {nama_customer}! 👋\n\nPembayaran terverifikasi! Laundry Anda sedang diproses dan dicuci bersih secara higienis oleh tim {store_name}. 🧼\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Sedang Diproses\n\nKami memastikan pakaian Anda dicuci bersih dengan standar kebersihan terbaik.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}");

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_layanan' => $serviceName,
            'store_name' => $storeName,
            'total' => $totalPrice,
            'weight' => $weight,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "LAUNDRY_PROCESSING:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'LAUNDRY_PROCESSING', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    public function notifyLaundryProcessing(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', string $stage = 'SEDANG_DIPROSES', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        if ($stage === 'SEDANG_DICUCI') {
            return $this->notifyLaundryWashing($phone, $customerName, $orderCode, $storeName, $orderId, $userId, $force);
        }

        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;

        $link = $this->formatActionLink('dashboard/customer');
        $defaultTpl = "Halo, {nama_customer}! 👋\n\nLaundry pesanan {nomor_pesanan} sedang dalam proses pengeringan, setrika uap, dan packing wangi oleh tim {store_name}. 👔✨\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Status: Sedang Disetrika & Diproses\n\nPakaian Anda akan segera siap untuk diantar.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}";
        $tpl = $this->getTemplate('customer_laundry_processing', $defaultTpl);

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_layanan' => $serviceName,
            'store_name' => $storeName,
            'total' => $totalPrice,
            'weight' => $weight,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "LAUNDRY_IRONING:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'LAUNDRY_PROCESSING', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    /**
     * 6. Laundry selesai: laundry sudah selesai diproses
     */
    public function notifyLaundryReady(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_laundry_ready', "Halo, {nama_customer}! 👋\n\nKabar baik! Laundry Anda sudah selesai diproses, bersih, dan wangi banget. 🎉\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Laundry Selesai\n\nSilakan menunggu proses pengantaran oleh kurir.\n\nTerima kasih telah mempercayai SmartLaundry. 😊{link}");

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_layanan' => $serviceName,
            'store_name' => $storeName,
            'total' => $totalPrice,
            'weight' => $weight,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "LAUNDRY_READY:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'LAUNDRY_READY', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    /**
     * 7. Sedang diantar: kurir sedang mengantarkan laundry
     */
    public function notifyCourierToDelivery(string $phone, string $customerName, string $orderCode, string $courierName = 'Kurir SmartLaundry', string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if (!empty($order['courier_name'])) $courierName = $order['courier_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;

        $courierPhone = !empty($order['courier_phone']) ? $order['courier_phone'] : '-';
        $licensePlate = !empty($order['license_plate']) ? $order['license_plate'] : '-';
        $vehicleType = !empty($order['vehicle_type']) ? $order['vehicle_type'] : 'Motor';

        $link = $this->formatActionLink('tracking/' . $orderCode);

        $placeholders = [
            'nama_customer' => $customerName,
            'customer_name' => $customerName,
            'nomor_pesanan' => $orderCode,
            'order_code'    => $orderCode,
            'nama_kurir'    => $courierName,
            'courier_name'  => $courierName,
            'wa_kurir'      => $courierPhone,
            'courier_phone' => $courierPhone,
            'plat_nomor'    => $licensePlate,
            'license_plate' => $licensePlate,
            'jenis_kendaraan' => $vehicleType,
            'vehicle_type'  => $vehicleType,
            'nama_layanan'  => $serviceName,
            'service_name'  => $serviceName,
            'store_name'    => $storeName,
            'total'         => $totalPrice,
            'weight'        => $weight,
            'link'          => $link
        ];

        $dedupeBase = $orderId && $userId ? "COURIER_DISPATCHED:{$orderId}:{$userId}:CUSTOMER_DELIVERY" : null;
        return $this->sendEventMultiMessages(
            'TRANSACTION',
            'customer_courier_to_delivery',
            $phone,
            $customerName,
            $placeholders,
            $dedupeBase,
            $userId,
            !empty($order['store_id']) ? (int)$order['store_id'] : null,
            $orderId,
            $force
        );
    }

    public function notifyLaundryDelivered(string $phone, string $customerName, string $orderCode, string $courierName = 'Kurir SmartLaundry', string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if (!empty($order['courier_name'])) $courierName = $order['courier_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_laundry_delivered', "Halo, {nama_customer}! 👋\n\nCucian pesanan {nomor_pesanan} sudah diantar oleh kurir {nama_kurir}. Mohon cek cucian Anda dan konfirmasi penerimaan di aplikasi ya.{link}");
        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_kurir' => $courierName,
            'store_name' => $storeName,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "LAUNDRY_DELIVERED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'LAUNDRY_DELIVERED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    /**
     * 8. Pesanan selesai: laundry telah diterima customer
     */
    public function notifyOrderCompleted(string $phone, string $customerName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null, bool $force = false): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $serviceName = $this->extractServiceName($order);
        $totalPrice = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;

        $link = $this->formatActionLink('orders/' . ($orderId ?? $orderCode) . '/review');
        $tpl = $this->getTemplate('customer_order_completed', "Halo, {nama_customer}! 👋\n\nPesanan laundry Anda telah selesai dan berhasil diterima dengan baik. ✨\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Pesanan Selesai\n\nTerima kasih banyak telah mempercayai layanan {store_name}. Jangan lupa berikan penilaian & ulasan bintang 5 Anda! ⭐\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}");

        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'nama_layanan' => $serviceName,
            'store_name' => $storeName,
            'total' => $totalPrice,
            'weight' => $weight,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "ORDER_COMPLETED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'ORDER_COMPLETED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId, $force);
    }

    public function notifyRefundApproved(string $phone, string $customerName, string $orderCode, float $refundFee = 0, string $bearer = 'CUSTOMER', string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $userId = null): bool {
        $order = $this->getOrderDetails($orderCode, $orderId);
        if (!empty($order)) {
            if (!empty($order['customer_phone'])) $phone = $order['customer_phone'];
            if (!empty($order['customer_name'])) $customerName = $order['customer_name'];
            if (!empty($order['store_name'])) $storeName = $order['store_name'];
            if ($orderId === null && !empty($order['id'])) $orderId = (int)$order['id'];
            if ($userId === null && !empty($order['customer_id'])) $userId = (int)$order['customer_id'];
        }

        $link = $this->formatActionLink('dashboard/customer');
        $tpl = $this->getTemplate('customer_refund_processing', "Halo, {nama_customer}! 👋\n\nPengembalian cucian pesanan {nomor_pesanan} sedang diproses oleh {store_name}. Kurir akan segera mengantar kembali ke lokasi Anda.{link}");
        $msg = $this->renderCustomerMessage($tpl, [
            'nama_customer' => $customerName,
            'nomor_pesanan' => $orderCode,
            'store_name' => $storeName,
            'link' => $link
        ]);

        $dedupe = $orderId && $userId ? "REFUND_APPROVED:{$orderId}:{$userId}:CUSTOMER" : null;
        return $this->send($phone, $customerName, 'REFUND_APPROVED', $msg, $dedupe, $userId, false, 'TRANSACTION', !empty($order['store_id']) ? (int)$order['store_id'] : null, $orderId);
    }

    public function notifyRefundCourierDispatched(string $phone, string $customerName, string $orderCode, string $courierName, string $courierPhone, ?int $orderId = null, ?int $userId = null): bool {
        return $this->notifyCourierToDelivery($phone, $customerName, $orderCode, $courierName, 'SmartLaundry', $orderId, $userId);
    }

    public function notifyRefundCompleted(string $phone, string $customerName, string $orderCode, ?int $orderId = null, ?int $userId = null): bool {
        return $this->notifyOrderCompleted($phone, $customerName, $orderCode, 'SmartLaundry', $orderId, $userId);
    }

    /**
     * Dispatch automatic WhatsApp customer notification on order status transition
     */
    public function notifyOrderStatusChanged(int $orderId, string $status, array $extra = []): bool {
        $order = $this->getOrderDetails('', $orderId);
        if (empty($order)) {
            return false;
        }

        $phone = $order['customer_phone'] ?? '';
        $customerName = $order['customer_name'] ?? 'Pelanggan';
        $orderCode = $order['order_code'] ?? ('ORD-' . $orderId);
        $storeName = $order['store_name'] ?? 'SmartLaundry';
        $courierName = $order['courier_name'] ?? ($extra['courier_name'] ?? 'Kurir SmartLaundry');
        $userId = (int)($order['customer_id'] ?? 0);

        switch (strtoupper($status)) {
            case 'MENUNGGU_KONFIRMASI':
                return $this->notifyOrderCreated($phone, $customerName, $orderCode, $storeName, $orderId, $userId);

            case 'MENUNGGU_KURIR':
                return $this->notifyOrderConfirmed($phone, $customerName, $orderCode, $storeName, $orderId, $userId);

            case 'KURIR_MENUJU_LOKASI':
                return $this->notifyCourierToPickup($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId);

            case 'LAUNDRY_DIJEMPUT':
            case 'DALAM_PERJALANAN_KE_TOKO':
                return $this->notifyLaundryPicked($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId);

            case 'SAMPAI_DI_TOKO':
            case 'SEDANG_DITIMBANG':
                return $this->notifyArrivedAtStore($phone, $customerName, $orderCode, $storeName, $orderId, $userId);

            case 'MENUNGGU_PEMBAYARAN':
                $total = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
                $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;
                return $this->notifyWeighingCompleted($phone, $customerName, $orderCode, $storeName, $total, $weight, null, $orderId, $userId);

            case 'PEMBAYARAN_BERHASIL':
                $total = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
                $method = $order['payment_method'] ?? 'ONLINE_QRIS';
                return $this->notifyPaymentSuccess($phone, $customerName, $orderCode, $total, null, $storeName, $orderId, $userId, $method);

            case 'SEDANG_DICUCI':
                return $this->notifyLaundryWashing($phone, $customerName, $orderCode, $storeName, $orderId, $userId);

            case 'SEDANG_DIPROSES':
            case 'DIPROSES':
                return $this->notifyLaundryProcessing($phone, $customerName, $orderCode, $storeName, 'SEDANG_DIPROSES', $orderId, $userId);

            case 'SIAP_DIANTAR':
                return $this->notifyLaundryReady($phone, $customerName, $orderCode, $storeName, $orderId, $userId);

            case 'KURIR_MENUJU_CUSTOMER':
                return $this->notifyCourierToDelivery($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId);

            case 'LAUNDRY_DIANTAR':
                return $this->notifyLaundryDelivered($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId);

            case 'SELESAI':
                return $this->notifyOrderCompleted($phone, $customerName, $orderCode, $storeName, $orderId, $userId);

            case 'PEMBAYARAN_KADALUARSA':
                return $this->notifyPaymentExpired($phone, $customerName, $orderCode, $storeName, $orderId, $userId);

            default:
                return false;
        }
    }

    /**
     * Send specific order process notification to customer via Fonnte (invoked by Admin or auto)
     * Supports all processes from weighing to completion.
     * 
     * @param int $orderId
     * @param string $processType WEIGHING|PAYMENT_SUCCESS|WASHING|PROCESSING|READY|DELIVERY|DELIVERED|COMPLETED|AUTO
     * @param bool $force When true, bypasses previous dedupe key so Admin can resend manually
     * @return array ['success' => bool, 'message' => string, 'process' => string, 'phone' => string]
     */
    public function sendOrderProcessNotification(int $orderId, string $processType = 'AUTO', bool $force = false): array {
        $order = $this->getOrderDetails('', $orderId);
        if (empty($order)) {
            return [
                'success' => false,
                'message' => "Pesanan #{$orderId} tidak ditemukan di sistem.",
                'process' => $processType,
                'phone' => ''
            ];
        }

        $phone = $order['customer_phone'] ?? '';
        if (empty($phone) && !empty($order['pickup_phone'])) {
            $phone = $order['pickup_phone'];
        }
        $customerName = $order['customer_name'] ?? 'Pelanggan';
        $orderCode = $order['order_code'] ?? ('ORD-' . $orderId);
        $storeName = $order['store_name'] ?? 'SmartLaundry';
        $courierName = $order['courier_name'] ?? 'Kurir SmartLaundry';
        $userId = (int)($order['customer_id'] ?? 0);
        $total = !empty($order['total_price']) ? (float)$order['total_price'] : 0.0;
        $weight = !empty($order['weight_actual']) ? (float)$order['weight_actual'] : 0.0;
        $paymentMethod = $order['payment_method'] ?? 'ONLINE_QRIS';

        if (empty($phone) && $userId > 0) {
            try {
                $db = Database::getConnection();
                $stmtU = $db->prepare("SELECT phone FROM `users` WHERE id = :uid LIMIT 1");
                $stmtU->execute(['uid' => $userId]);
                $userPhone = $stmtU->fetchColumn();
                if (!empty($userPhone)) {
                    $phone = (string)$userPhone;
                }
            } catch (\Throwable $th) {}
        }

        if (empty($phone)) {
            return [
                'success' => false,
                'message' => "Nomor WhatsApp pelanggan untuk pesanan #{$orderCode} tidak terdaftar.",
                'process' => $processType,
                'phone' => ''
            ];
        }

        $normType = strtoupper(trim($processType));
        if ($normType === 'AUTO') {
            $normType = match (strtoupper($order['status'])) {
                'MENUNGGU_KONFIRMASI' => 'ORDER_CREATED',
                'MENUNGGU_KURIR'      => 'COURIER_ASSIGNED',
                'KURIR_MENUJU_LOKASI' => 'COURIER_PICKUP',
                'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO' => 'LAUNDRY_PICKED',
                'SAMPAI_DI_TOKO', 'SEDANG_DITIMBANG' => 'ARRIVED_STORE',
                'MENUNGGU_PEMBAYARAN' => 'WEIGHING',
                'PEMBAYARAN_BERHASIL' => 'PAYMENT_SUCCESS',
                'SEDANG_DICUCI'       => 'WASHING',
                'SEDANG_DIPROSES'     => 'PROCESSING',
                'SIAP_DIANTAR'        => 'READY',
                'KURIR_MENUJU_CUSTOMER' => 'DELIVERY',
                'LAUNDRY_DIANTAR'     => 'DELIVERED',
                'SELESAI'             => 'COMPLETED',
                default               => 'UNKNOWN'
            };
        }

        $sent = false;
        $label = 'Proses Pesanan';

        switch ($normType) {
            case 'WEIGHING':
            case 'TAGIHAN':
            case 'MENUNGGU_PEMBAYARAN':
                $label = 'Penimbangan Selesai & Tagihan';
                $expiry = !empty($order['payment_expired_at'])
                    ? date('H:i, d M Y', strtotime($order['payment_expired_at']))
                    : date('H:i', strtotime('+60 minutes')) . ' WIB';

                if ($paymentMethod === 'ONLINE_QRIS') {
                    $qr = !empty($order['qris_payload']) ? $order['qris_payload'] : ($order['qris_image_url'] ?? '-');
                    $sent = $this->notifyReadyToPayQRIS($phone, $customerName, $orderCode, $total, $weight, $qr, $expiry, $storeName, $orderId, $userId, null, $force);
                } else {
                    $sent = $this->notifyReadyToPayCash($phone, $customerName, $orderCode, $total, $weight, $storeName, $orderId, $userId, $force);
                }
                break;

            case 'PAYMENT_SUCCESS':
            case 'LUNAS':
            case 'PEMBAYARAN_BERHASIL':
                $label = 'Pembayaran Lunas';
                $sent = $this->notifyPaymentSuccess(
                    $phone,
                    $customerName,
                    $orderCode,
                    $total,
                    null,
                    $storeName,
                    $orderId,
                    $userId,
                    $paymentMethod,
                    null,
                    $force
                );
                break;

            case 'WASHING':
            case 'CUCI':
            case 'SEDANG_DICUCI':
                $label = 'Sedang Dicuci Mesin';
                $sent = $this->notifyLaundryWashing($phone, $customerName, $orderCode, $storeName, $orderId, $userId, $force);
                break;

            case 'PROCESSING':
            case 'SETRIKA':
            case 'SEDANG_DIPROSES':
                $label = 'Sedang Disetrika & Dipacking';
                $sent = $this->notifyLaundryProcessing($phone, $customerName, $orderCode, $storeName, 'SEDANG_DIPROSES', $orderId, $userId, $force);
                break;

            case 'READY':
            case 'SIAP_DIANTAR':
                $label = 'Cucian Siap Diantar';
                $sent = $this->notifyLaundryReady($phone, $customerName, $orderCode, $storeName, $orderId, $userId, $force);
                break;

            case 'DELIVERY':
            case 'KURIR_MENUJU_CUSTOMER':
                $label = 'Kurir Berangkat Mengantar';
                $sent = $this->notifyCourierToDelivery($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId, $force);
                break;

            case 'DELIVERED':
            case 'LAUNDRY_DIANTAR':
                $label = 'Laundry Telah Diantar';
                $sent = $this->notifyLaundryDelivered($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId, $force);
                break;

            case 'COMPLETED':
            case 'SELESAI':
                $label = 'Pesanan Selesai';
                $sent = $this->notifyOrderCompleted($phone, $customerName, $orderCode, $storeName, $orderId, $userId, $force);
                break;

            case 'COURIER_PICKUP':
            case 'KURIR_MENUJU_LOKASI':
                $label = 'Kurir Menuju Lokasi Penjemputan';
                $sent = $this->notifyCourierToPickup($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId, $force);
                break;

            case 'LAUNDRY_PICKED':
            case 'LAUNDRY_DIJEMPUT':
                $label = 'Laundry Berhasil Dijemput';
                $sent = $this->notifyLaundryPicked($phone, $customerName, $orderCode, $courierName, $storeName, $orderId, $userId, $force);
                break;

            case 'ARRIVED_STORE':
            case 'SAMPAI_DI_TOKO':
                $label = 'Laundry Tiba di Toko';
                $sent = $this->notifyArrivedAtStore($phone, $customerName, $orderCode, $storeName, $orderId, $userId, $force);
                break;

            default:
                return [
                    'success' => false,
                    'message' => "Jenis proses '{$processType}' tidak dikenali untuk pesanan #{$orderCode}.",
                    'process' => $processType,
                    'phone' => $phone
                ];
        }

        if ($sent) {
            return [
                'success' => true,
                'message' => "Notifikasi WhatsApp [{$label}] berhasil dikirim ke nomor {$phone} via Fonnte.",
                'process' => $normType,
                'label'   => $label,
                'phone'   => $phone,
                'order_code' => $orderCode
            ];
        }

        return [
            'success' => false,
            'message' => "Gagal mengirim notifikasi WhatsApp [{$label}] ke nomor {$phone}. Pastikan token Fonnte aktif dan kategori notifikasi diaktifkan.",
            'process' => $normType,
            'label'   => $label,
            'phone'   => $phone,
            'order_code' => $orderCode
        ];
    }

    // =========================================================================
    // STORE OWNER & ADMIN TOKO NOTIFICATIONS (Multi-Recipient)
    // =========================================================================

    /**
     * Helper to get Store Staff users (Owner and Store Admins)
     */
    public function getStoreStaff(int $storeId): array {
        $db = Database::getConnection();
        $staff = [];

        // 1. Store Owner
        $stmtOwner = $db->prepare("SELECT u.id, u.name, u.phone, u.wa_notifications_enabled, 'owner_toko' as staff_role 
                                   FROM `stores` s 
                                   JOIN `users` u ON s.owner_id = u.id 
                                   WHERE s.id = :sid LIMIT 1");
        $stmtOwner->execute(['sid' => $storeId]);
        $owner = $stmtOwner->fetch(\PDO::FETCH_ASSOC);
        if ($owner) {
            $staff[] = $owner;
        }

        // 2. Store Admins
        $stmtAdmins = $db->prepare("SELECT u.id, u.name, u.phone, u.wa_notifications_enabled, 'admin_toko' as staff_role 
                                    FROM `store_admins` sa 
                                    JOIN `users` u ON sa.user_id = u.id 
                                    WHERE sa.store_id = :sid");
        $stmtAdmins->execute(['sid' => $storeId]);
        $admins = $stmtAdmins->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($admins as $adm) {
            // Avoid duplicate if owner is also admin
            if (!$owner || (int)$adm['id'] !== (int)$owner['id']) {
                $staff[] = $adm;
            }
        }

        return $staff;
    }

    private function isPhoneArg($val): bool {
        if (!is_string($val)) return false;
        $val = trim($val);
        return str_starts_with($val, '0') || str_starts_with($val, '62') || str_starts_with($val, '+') || strlen($val) >= 7;
    }

    /**
     * Trigger: Order Created (To Store Staff & Additional Recipients)
     */
    public function notifyStoreNewOrder($storeIdOrPhone, ...$args): void {
        if ($this->isPhoneArg($storeIdOrPhone)) {
            $phone = (string)$storeIdOrPhone;
            $storeName = (string)($args[0] ?? 'Owner Toko');
            $orderCode = (string)($args[1] ?? '');
            $tpl = $this->getTemplate('store_new_order', "SmartLaundry: Pesanan baru {order_code} masuk di outlet {store_name}. Silakan buka dashboard untuk konfirmasi.{link}");
            $link = $this->formatActionLink('dashboard/store');
            $msg = str_replace(['{order_code}', '{store_name}', '{link}'], [$orderCode, $storeName, $link], $tpl);
            $this->send($phone, $storeName, 'STORE_NEW_ORDER', $msg, null, null, false, 'TRANSACTION');
            return;
        }

        $storeId = (int)$storeIdOrPhone;
        $orderCode = (string)($args[0] ?? '');
        $customerName = (string)($args[1] ?? '');
        $orderId = isset($args[2]) && is_numeric($args[2]) ? (int)$args[2] : null;

        $staffMembers = $this->getStoreStaff($storeId);
        $tpl = $this->getTemplate('store_new_order', "SmartLaundry: Pesanan baru {order_code} masuk di outlet {store_name}. Silakan buka dashboard untuk konfirmasi.{link}", $storeId);
        $link = $this->formatActionLink('dashboard/store');
        $msg = str_replace(['{order_code}', '{store_name}', '{link}'], [$orderCode, 'Toko', $link], $tpl);

        foreach ($staffMembers as $staff) {
            if (empty($staff['phone'])) continue;
            $dedupe = $orderId ? "STORE_NEW_ORDER:{$orderId}:{$staff['id']}:STORE" : null;
            $this->send($staff['phone'], $staff['name'], 'STORE_NEW_ORDER', $msg, $dedupe, (int)$staff['id'], false, 'TRANSACTION', $storeId, $orderId);
        }

        // Additional store recipients registered for TRANSACTION
        $additionalPhones = $this->recipientModel->getActivePhoneNumbers('TRANSACTION', $storeId);
        foreach ($additionalPhones as $idx => $addPhone) {
            $addDedupe = $orderId ? "STORE_NEW_ORDER:{$orderId}:ADD:{$idx}" : null;
            $this->send($addPhone, 'Staff Toko', 'STORE_NEW_ORDER', $msg, $addDedupe, null, false, 'TRANSACTION', $storeId, $orderId);
        }
    }

    /**
     * Trigger: Payment Received (Owner gets nominal, Admin gets notification WITHOUT nominal)
     */
    public function notifyStorePaymentReceived(int $storeId, string $orderCode, float $total, ?int $orderId = null, string $method = 'QRIS'): void {
        $staffMembers = $this->getStoreStaff($storeId);
        $formattedAmount = 'Rp ' . number_format($total, 0, ',', '.');
        $link = $this->formatActionLink('dashboard/store');

        $tplOwner = $this->getTemplate('store_payment_received_owner', "SmartLaundry: Pembayaran pesanan {order_code} sebesar {total} via {method} telah diterima untuk outlet {store_name}.{link}", $storeId);
        $tplAdmin = $this->getTemplate('store_payment_received_admin', "SmartLaundry: Pembayaran pesanan {order_code} via {method} telah diterima untuk outlet {store_name}. Cucian siap diproses.{link}", $storeId);

        foreach ($staffMembers as $staff) {
            if (empty($staff['phone'])) continue;
            
            if ($staff['staff_role'] === 'owner_toko') {
                $msg = str_replace(['{order_code}', '{total}', '{method}', '{store_name}', '{link}'], [$orderCode, $formattedAmount, $method, 'Toko', $link], $tplOwner);
            } else {
                // Admin Toko NEVER receives nominal
                $msg = str_replace(['{order_code}', '{method}', '{store_name}', '{link}'], [$orderCode, $method, 'Toko', $link], $tplAdmin);
            }

            $dedupe = $orderId ? "STORE_PAYMENT_RECEIVED:{$orderId}:{$staff['id']}:STORE" : null;
            $this->send($staff['phone'], $staff['name'], 'STORE_PAYMENT_RECEIVED', $msg, $dedupe, (int)$staff['id'], false, 'TRANSACTION', $storeId, $orderId);
        }

        // Additional store recipients registered for TRANSACTION
        $additionalPhones = $this->recipientModel->getActivePhoneNumbers('TRANSACTION', $storeId);
        foreach ($additionalPhones as $idx => $addPhone) {
            $msgAdd = str_replace(['{order_code}', '{method}', '{store_name}', '{link}'], [$orderCode, $method, 'Toko', $link], $tplAdmin);
            $addDedupe = $orderId ? "STORE_PAYMENT_RECEIVED:{$orderId}:ADD:{$idx}" : null;
            $this->send($addPhone, 'Staff Toko', 'STORE_PAYMENT_RECEIVED', $msgAdd, $addDedupe, null, false, 'TRANSACTION', $storeId, $orderId);
        }
    }

    /**
     * Trigger: Store Expense Recorded
     * Owner gets nominal, Admin gets nominal only if financial view is enabled.
     */
    public function notifyStoreExpenseRecorded(int $storeId, string $category, string $item, float $amount, string $date, ?int $expenseId = null): void {
        // Check minimum threshold setting (default 0)
        $threshold = (float)Setting::getStore($storeId, 'wa_expense_notify_threshold', Setting::get('wa_expense_notify_threshold', 0));
        if ($amount < $threshold) {
            return;
        }

        $staffMembers = $this->getStoreStaff($storeId);
        $formattedAmount = 'Rp ' . number_format($amount, 0, ',', '.');
        $formattedDate = date('d M Y', strtotime($date));
        $link = $this->formatActionLink('store/expenses');

        $tplOwner = $this->getTemplate('store_expense_recorded_owner', "SmartLaundry: Pengeluaran baru dicatat di {store_name} — {item} ({category}) sebesar {total} pada {date}.{link}", $storeId);
        $tplAdmin = $this->getTemplate('store_expense_recorded_admin', "SmartLaundry: Pengeluaran baru dicatat di {store_name} — {item} ({category}) pada {date}. Silakan cek detail di dashboard.{link}", $storeId);

        // Check whether admin_toko can view finance
        $adminCanViewFinance = (bool)Setting::getStore($storeId, 'admin_toko_can_view_finance', Setting::get('admin_toko_can_view_finance', false));

        foreach ($staffMembers as $staff) {
            if (empty($staff['phone'])) continue;

            $isOwner = ($staff['staff_role'] === 'owner_toko');
            if ($isOwner || $adminCanViewFinance) {
                $msg = str_replace(['{store_name}', '{item}', '{category}', '{total}', '{date}', '{link}'], ['Toko', $item, $category, $formattedAmount, $formattedDate, $link], $tplOwner);
            } else {
                // Admin Toko without financial permissions receives operational notification WITHOUT nominal
                $msg = str_replace(['{store_name}', '{item}', '{category}', '{date}', '{link}'], ['Toko', $item, $category, $formattedDate, $link], $tplAdmin);
            }

            $dedupe = $expenseId ? "STORE_EXPENSE:{$storeId}:{$expenseId}:{$staff['id']}" : "STORE_EXPENSE:{$storeId}:" . md5("{$item}_{$amount}_{$date}") . ":{$staff['id']}";
            $this->send($staff['phone'], $staff['name'], 'STORE_EXPENSE_RECORDED', $msg, $dedupe, (int)$staff['id'], false, 'TRANSACTION', $storeId);
        }
    }

    /**
     * Trigger: Payment Expired (To Store Staff)
     */
    public function notifyStorePaymentExpired($storeIdOrPhone, ...$args): void {
        if ($this->isPhoneArg($storeIdOrPhone)) {
            $phone = (string)$storeIdOrPhone;
            $storeName = (string)($args[0] ?? 'Owner Toko');
            $orderCode = (string)($args[1] ?? '');
            $tpl = $this->getTemplate('store_payment_expired', "SmartLaundry: Pembayaran pesanan {order_code} di {store_name} telah kadaluarsa dan membutuhkan persetujuan pengembalian cucian.{link}");
            $link = $this->formatActionLink('store/refunds');
            $msg = str_replace(['{order_code}', '{store_name}', '{link}'], [$orderCode, $storeName, $link], $tpl);
            $this->send($phone, $storeName, 'STORE_PAYMENT_EXPIRED', $msg, null, null, false, 'TRANSACTION');
            return;
        }

        $storeId = (int)$storeIdOrPhone;
        $orderCode = (string)($args[0] ?? '');
        $customerName = (string)($args[1] ?? '');
        $orderId = isset($args[2]) && is_numeric($args[2]) ? (int)$args[2] : null;

        $orderTotal = 0;
        if ($orderId) {
            try {
                $db = Database::getConnection();
                $st = $db->prepare("SELECT total_price FROM `orders` WHERE id = :id LIMIT 1");
                $st->execute(['id' => $orderId]);
                $orderTotal = (float)$st->fetchColumn();
            } catch (\Throwable $e) {}
        }
        $formattedAmount = 'Rp ' . number_format($orderTotal, 0, ',', '.');

        $staffMembers = $this->getStoreStaff($storeId);
        $tpl = $this->getTemplate('store_payment_expired', "SmartLaundry: Pembayaran pesanan {order_code} sebesar {total} di {store_name} telah kadaluarsa dan membutuhkan persetujuan pengembalian cucian.{link}", $storeId);
        $link = $this->formatActionLink('store/refunds');

        foreach ($staffMembers as $staff) {
            if (empty($staff['phone'])) continue;
            $msg = str_replace(['{order_code}', '{total}', '{store_name}', '{link}'], [$orderCode, $formattedAmount, 'Toko', $link], $tpl);
            $dedupe = $orderId ? "STORE_PAYMENT_EXPIRED:{$orderId}:{$staff['id']}:STORE" : null;
            $this->send($staff['phone'], $staff['name'], 'STORE_PAYMENT_EXPIRED', $msg, $dedupe, (int)$staff['id'], false, 'TRANSACTION', $storeId, $orderId);
        }
    }

    /**
     * Trigger: No Courier Available (To Store Staff)
     */
    public function notifyStoreNoCourierAvailable($storeIdOrPhone, ...$args): void {
        if ($this->isPhoneArg($storeIdOrPhone)) {
            $phone = (string)$storeIdOrPhone;
            $storeName = (string)($args[0] ?? 'Owner Toko');
            $orderCode = (string)($args[1] ?? '');
            $tpl = $this->getTemplate('store_no_courier_available', "SmartLaundry: Tidak ada kurir tersedia untuk pesanan {order_code} di {store_name}. Silakan lakukan penugasan manual di dashboard.{link}");
            $link = $this->formatActionLink('dashboard/store');
            $msg = str_replace(['{order_code}', '{store_name}', '{link}'], [$orderCode, $storeName, $link], $tpl);
            $this->send($phone, $storeName, 'NO_COURIER_AVAILABLE', $msg);
            return;
        }

        $storeId = (int)$storeIdOrPhone;
        $orderCode = (string)($args[0] ?? '');
        $orderId = isset($args[1]) && is_numeric($args[1]) ? (int)$args[1] : null;

        $staffMembers = $this->getStoreStaff($storeId);
        $tpl = $this->getTemplate('store_no_courier_available', "SmartLaundry: Tidak ada kurir tersedia untuk pesanan {order_code} di {store_name}. Silakan lakukan penugasan manual di dashboard.{link}");
        $link = $this->formatActionLink('dashboard/store');

        foreach ($staffMembers as $staff) {
            if (empty($staff['phone'])) continue;
            $msg = str_replace(['{order_code}', '{store_name}', '{link}'], [$orderCode, 'Toko', $link], $tpl);
            $dedupe = $orderId ? "NO_COURIER_AVAILABLE:{$orderId}:{$staff['id']}:STORE" : null;
            $this->send($staff['phone'], $staff['name'], 'NO_COURIER_AVAILABLE', $msg, $dedupe, (int)$staff['id']);
        }
    }

    /**
     * Trigger: Courier Rejected Task (To Store Staff)
     */
    public function notifyStoreCourierRejected($storeIdOrPhone, ...$args): void {
        if ($this->isPhoneArg($storeIdOrPhone)) {
            $phone = (string)$storeIdOrPhone;
            $storeName = (string)($args[0] ?? 'Owner Toko');
            $orderCode = (string)($args[1] ?? '');
            $courierName = (string)($args[2] ?? 'Kurir');
            $reason = $args[3] ?? null;
            $tpl = $this->getTemplate('store_courier_rejected', "SmartLaundry: Kurir {courier_name} menolak tugas untuk pesanan {order_code} di {store_name}. Sistem sedang mencari kurir lain.{link}");
            $link = $this->formatActionLink('dashboard/store');
            $msg = str_replace(['{courier_name}', '{order_code}', '{store_name}', '{link}'], [$courierName, $orderCode, $storeName, $link], $tpl);
            $this->send($phone, $storeName, 'COURIER_REJECTED_OFFER', $msg);
            return;
        }

        $storeId = (int)$storeIdOrPhone;
        $orderCode = (string)($args[0] ?? '');
        $courierName = (string)($args[1] ?? '');
        $reason = $args[2] ?? null;
        $orderId = isset($args[3]) && is_numeric($args[3]) ? (int)$args[3] : null;

        $staffMembers = $this->getStoreStaff($storeId);
        $tpl = $this->getTemplate('store_courier_rejected', "SmartLaundry: Kurir {courier_name} menolak tugas untuk pesanan {order_code} di {store_name}. Sistem sedang mencari kurir lain.{link}");
        $link = $this->formatActionLink('dashboard/store');

        foreach ($staffMembers as $staff) {
            if (empty($staff['phone'])) continue;
            $msg = str_replace(['{courier_name}', '{order_code}', '{store_name}', '{link}'], [$courierName, $orderCode, 'Toko', $link], $tpl);
            $dedupe = $orderId ? "COURIER_REJECTED_OFFER:{$orderId}:{$staff['id']}:STORE" : null;
            $this->send($staff['phone'], $staff['name'], 'COURIER_REJECTED_OFFER', $msg, $dedupe, (int)$staff['id']);
        }
    }

    /**
     * Trigger: New Review Submitted (To Store Owner)
     */
    public function notifyStoreNewReview(int $storeId, string $orderCode, int $rating, ?string $comment = null, ?int $orderId = null): void {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT u.id, u.name, u.phone 
                              FROM `stores` s 
                              JOIN `users` u ON s.owner_id = u.id 
                              WHERE s.id = :sid LIMIT 1");
        $stmt->execute(['sid' => $storeId]);
        $owner = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($owner && !empty($owner['phone'])) {
            $tpl = $this->getTemplate('store_new_review', "SmartLaundry: Ulasan baru telah diterima untuk outlet {store_name} pada pesanan {order_code}.{link}");
            $link = $this->formatActionLink('dashboard/store');
            $msg = str_replace(['{store_name}', '{order_code}', '{link}'], ['Toko', $orderCode, $link], $tpl);
            $dedupe = $orderId ? "NEW_REVIEW:{$orderId}:{$owner['id']}:STORE" : null;
            $this->send($owner['phone'], $owner['name'], 'NEW_REVIEW', $msg, $dedupe, (int)$owner['id']);
        }
    }

    // =========================================================================
    // COURIER NOTIFICATIONS
    // =========================================================================

    public function notifyCourierTaskOffered(string $phone, string $courierName, string $orderCode, string $taskType, string $address, ?int $orderId = null, ?int $courierUserId = null): bool {
        $tpl = $this->getTemplate('courier_task_offered', "SmartLaundry: Halo {courier_name}, Anda menerima tawaran tugas baru untuk pesanan {order_code} di {store_name}. Buka dashboard kurir untuk merespon.{link}");
        $link = $this->formatActionLink('dashboard/courier');
        $msg = str_replace(['{courier_name}', '{order_code}', '{store_name}', '{link}'], [$courierName, $orderCode, 'SmartLaundry', $link], $tpl);
        $dedupe = $orderId && $courierUserId ? "COURIER_TASK_OFFERED:{$orderId}:{$courierUserId}:COURIER" : null;
        return $this->send($phone, $courierName, 'COURIER_TASK_OFFERED', $msg, $dedupe, $courierUserId);
    }

    public function notifyCourierReturnTask(string $phone, string $courierName, string $orderCode, string $storeName = 'SmartLaundry', ?int $orderId = null, ?int $courierUserId = null): bool {
        $tpl = $this->getTemplate('courier_return_task', "SmartLaundry: Halo {courier_name}, Anda ditugaskan untuk tugas pengembalian cucian pesanan {order_code} di {store_name}.{link}");
        $link = $this->formatActionLink('dashboard/courier');
        $msg = str_replace(['{courier_name}', '{order_code}', '{store_name}', '{link}'], [$courierName, $orderCode, $storeName, $link], $tpl);
        $dedupe = $orderId && $courierUserId ? "COURIER_RETURN_TASK:{$orderId}:{$courierUserId}:COURIER" : null;
        return $this->send($phone, $courierName, 'COURIER_RETURN_TASK', $msg, $dedupe, $courierUserId);
    }

    public function notifyCourierTaskCancelled(string $phone, string $courierName, string $orderCode, ?int $orderId = null, ?int $courierUserId = null): bool {
        $tpl = $this->getTemplate('courier_task_cancelled', "SmartLaundry: Halo {courier_name}, tugas untuk pesanan {order_code} telah dibatalkan oleh sistem.{link}");
        $link = $this->formatActionLink('dashboard/courier');
        $msg = str_replace(['{courier_name}', '{order_code}', '{link}'], [$courierName, $orderCode, $link], $tpl);
        $dedupe = $orderId && $courierUserId ? "COURIER_TASK_CANCELLED:{$orderId}:{$courierUserId}:COURIER" : null;
        return $this->send($phone, $courierName, 'COURIER_TASK_CANCELLED', $msg, $dedupe, $courierUserId);
    }

    // =========================================================================
    // PLATFORM ADMIN NOTIFICATIONS (owner_platform & admin_owner)
    // =========================================================================

    public function notifyPlatformNewStoreRegistered(string $storeName, string $ownerName, string $storePhone, ?int $storeId = null): void {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT u.id, u.name, u.phone, u.wa_notifications_enabled 
                            FROM `users` u 
                            JOIN `roles` r ON u.role_id = r.id 
                            WHERE r.name IN ('owner_platform', 'admin_owner')");
        $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $tpl = $this->getTemplate('platform_new_store_registered', "SmartLaundry: Toko baru \"{store_name}\" telah mendaftar dan menunggu verifikasi dari admin platform.{link}");
        $link = $this->formatActionLink('dashboard/platform');

        foreach ($admins as $adm) {
            if (empty($adm['phone'])) continue;
            $msg = str_replace(['{store_name}', '{link}'], [$storeName, $link], $tpl);
            $dedupe = $storeId ? "STORE_PENDING_VERIFICATION:{$storeId}:{$adm['id']}:PLATFORM" : null;
            $this->send($adm['phone'], $adm['name'], 'STORE_PENDING_VERIFICATION', $msg, $dedupe, (int)$adm['id']);
        }
    }

    public function sendDailySummary(string $summaryText): int {
        if (!Setting::get('wa_daily_summary_enabled', true)) {
            return 0;
        }

        $db = Database::getConnection();
        $stmt = $db->query("SELECT u.id, u.name, u.phone, u.wa_daily_summary_enabled 
                            FROM `users` u 
                            JOIN `roles` r ON u.role_id = r.id 
                            WHERE r.name IN ('owner_platform', 'admin_owner')");
        $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $today = date('Y-m-d');
        $tpl = $this->getTemplate('platform_daily_summary', "SmartLaundry: Ringkasan Harian Platform ({date}): {summary_text}.{link}");
        $link = $this->formatActionLink('dashboard/platform');
        $sentCount = 0;

        foreach ($admins as $adm) {
            if (empty($adm['phone'])) continue;
            if (isset($adm['wa_daily_summary_enabled']) && (int)$adm['wa_daily_summary_enabled'] === 0) continue;
            $msg = str_replace(['{summary_text}', '{date}', '{link}'], [$summaryText, $today, $link], $tpl);
            $dedupe = "DAILY_SUMMARY:PLATFORM:{$today}:{$adm['id']}";
            if ($this->send($adm['phone'], $adm['name'], 'DAILY_SUMMARY_PLATFORM', $msg, $dedupe, (int)$adm['id'])) {
                $sentCount++;
            }
        }
        return $sentCount;
    }

    /**
     * Send Store Financial Daily Summary (To Store Owner)
     */
    public function sendStoreDailySummary(int $storeId, string $storeName, array $metrics, string $date): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT u.id, u.name, u.phone, u.wa_notifications_enabled, u.wa_daily_summary_enabled 
                              FROM `stores` s 
                              JOIN `users` u ON s.owner_id = u.id 
                              WHERE s.id = :sid LIMIT 1");
        $stmt->execute(['sid' => $storeId]);
        $owner = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$owner || empty($owner['phone'])) {
            return false;
        }

        // Check if opted out of daily summary
        if (isset($owner['wa_daily_summary_enabled']) && (int)$owner['wa_daily_summary_enabled'] === 0) {
            return false;
        }

        $income = 'Rp ' . number_format($metrics['income'] ?? 0, 0, ',', '.');
        $expense = 'Rp ' . number_format($metrics['expense'] ?? 0, 0, ',', '.');
        $profit = 'Rp ' . number_format($metrics['profit'] ?? 0, 0, ',', '.');
        $weight = number_format($metrics['weight'] ?? 0, 1, ',', '.');
        $orders = (int)($metrics['orders'] ?? 0);
        $customers = (int)($metrics['customers'] ?? 0);

        $tpl = $this->getTemplate('store_daily_summary', "SmartLaundry: Ringkasan Keuangan {store_name} ({date}): Pemasukan {income}, Pengeluaran {expense}, Laba Kotor {profit}. Total cucian {weight} kg dari {orders} pesanan ({customers} pelanggan).{link}");
        $link = $this->formatActionLink('dashboard/store');
        $msg = str_replace(
            ['{store_name}', '{date}', '{income}', '{expense}', '{profit}', '{weight}', '{orders}', '{customers}', '{link}'],
            [$storeName, $date, $income, $expense, $profit, $weight, $orders, $customers, $link],
            $tpl
        );

        $dedupe = "DAILY_SUMMARY:STORE:{$storeId}:{$date}:{$owner['id']}";
        return $this->send($owner['phone'], $owner['name'], 'DAILY_SUMMARY_STORE', $msg, $dedupe, (int)$owner['id']);
    }

    /**
     * Send Payment OTP to customer via WhatsApp (Fonnte)
     * Used to verify customer identity before processing payment.
     * OTP code is NEVER logged in plaintext in production.
     */
    public function sendPaymentOtp(
        string $phone,
        string $customerName,
        string $otpCode,
        string $orderCode,
        int $expiryMinutes = 5,
        ?int $orderId = null,
        ?int $userId = null
    ): bool {
        if (!$this->settingModel->isCategoryEnabled('OTP')) {
            return false;
        }

        if (!$this->settingModel->isEnabled('OTP', 'otp_payment')) {
            return false;
        }

        $placeholders = [
            'nama_customer' => $customerName,
            'customer_name' => $customerName,
            'order_code'    => $orderCode,
            'nomor_pesanan' => $orderCode,
            'otp_code'      => $otpCode,
            'expiry_minutes' => (string)$expiryMinutes,
            'link'          => ''
        ];

        $storeId = null;
        if ($orderId) {
            $order = $this->getOrderDetails($orderCode, $orderId);
            if (!empty($order['store_id'])) $storeId = (int)$order['store_id'];
        }

        $dedupe = "OTP:PAYMENT:{$orderId}:{$userId}:" . floor(time() / 60);
        return $this->sendEventMultiMessages(
            'OTP',
            'otp_payment',
            $phone,
            $customerName,
            $placeholders,
            $dedupe,
            $userId,
            $storeId,
            $orderId
        );
    }

    // =========================================================================
    // KATEGORI A: OTP WHATSAPP DISPATCH
    // =========================================================================

    public function sendOtp(string $phone, string $recipientName, string $otpCode, int $expiryMinutes = 5, string $purpose = 'VERIFIKASI'): bool {
        if (!$this->settingModel->isCategoryEnabled('OTP')) {
            return false;
        }

        $purposeKey = strtolower(trim($purpose));
        $eventKey = 'otp_' . $purposeKey;
        if (!$this->settingModel->isEnabled('OTP', $eventKey)) {
            return false;
        }

        $defaultTpl = "SmartLaundry: Halo {nama_customer}, kode verifikasi akun Anda adalah *{otp_code}* (berlaku {expiry_minutes} menit). JANGAN bagikan kode ini kepada siapa pun demi keamanan akun Anda.";
        $tpl = $this->settingModel->getTemplate('OTP', $eventKey, null, $this->templates['otp_whatsapp'] ?? $defaultTpl);

        $msg = str_replace(
            ['{nama_customer}', '{nama}', '{otp_code}', '{expiry_minutes}'],
            [$recipientName, $recipientName, $otpCode, $expiryMinutes],
            $tpl
        );

        $normalizedPhone = normalize_phone_number($phone);
        $dedupe = "OTP:{$purposeKey}:{$normalizedPhone}:" . time();
        return $this->send($phone, $recipientName, 'OTP_' . strtoupper($purposeKey), $msg, $dedupe, null, true, 'OTP');
    }

    // =========================================================================
    // KATEGORI C: AKTIVITAS MASUK WA
    // =========================================================================

    public function notifyActivity(string $activityType, array $data, ?int $storeId = null, ?array $actor = null): bool {
        $activityType = strtoupper(trim($activityType));
        if (!$this->settingModel->isCategoryEnabled('ACTIVITY', $storeId)) {
            return false;
        }

        $eventKey = strtolower($activityType);
        if (strpos($eventKey, 'activity_') !== 0) {
            $eventKey = 'activity_' . $eventKey;
        }

        if (!$this->settingModel->isEnabled('ACTIVITY', $eventKey, $storeId)) {
            return false;
        }

        $currentUser = auth_user();
        $actorName = !empty($actor['name']) ? $actor['name'] : (!empty($currentUser['name']) ? $currentUser['name'] : 'Admin/Owner');
        $actorRole = !empty($actor['role_name']) ? $actor['role_name'] : (!empty($actor['role']) ? $actor['role'] : (!empty($currentUser['role_name']) ? $currentUser['role_name'] : (!empty($currentUser['role']) ? $currentUser['role'] : 'owner_toko')));
        $storeName = !empty($data['store_name']) ? $data['store_name'] : 'SmartLaundry';
        $timestamp = date('d-m-Y H:i');

        $tpl = $this->settingModel->getTemplate('ACTIVITY', $eventKey, $storeId);
        $link = $this->formatActionLink($storeId ? 'dashboard/store' : 'platform/settings');

        $serviceName = !empty($data['nama_layanan']) ? $data['nama_layanan'] : (!empty($data['service_name']) ? $data['service_name'] : '-');
        $oldPrice = !empty($data['harga_lama']) ? $data['harga_lama'] : '-';
        $newPrice = !empty($data['harga_baru']) ? $data['harga_baru'] : '-';
        $price = !empty($data['harga']) ? $data['harga'] : '-';
        $unit = !empty($data['unit']) ? $data['unit'] : 'kg';
        $courierName = !empty($data['courier_name']) ? $data['courier_name'] : (!empty($data['nama_kurir']) ? $data['nama_kurir'] : '-');
        $userName = !empty($data['user_name']) ? $data['user_name'] : '-';
        $roleOld = !empty($data['role_lama']) ? $data['role_lama'] : '-';
        $roleNew = !empty($data['role_baru']) ? $data['role_baru'] : '-';
        $settingName = !empty($data['setting_name']) ? $data['setting_name'] : '-';
        $detail = !empty($data['detail']) ? $data['detail'] : (!empty($data['change_detail']) ? $data['change_detail'] : '-');

        $actMessages = $this->templateModel->getEventMessages('ACTIVITY', $eventKey, $storeId, true);
        if (empty($actMessages)) {
            $actMessages = [['message_sequence' => 1, 'template_content' => $tpl]];
        }

        $sentAny = false;

        foreach ($actMessages as $mIdx => $msgItem) {
            $seq = (int)($msgItem['message_sequence'] ?? ($mIdx + 1));
            $rawTpl = $msgItem['template_content'];

            $msg = str_replace(
                [
                    '{actor_name}', '{actor_role}', '{activity_type}', '{nama_layanan}',
                    '{harga_lama}', '{harga_baru}', '{harga}', '{unit}', '{courier_name}',
                    '{user_name}', '{role_lama}', '{role_baru}', '{setting_name}',
                    '{change_detail}', '{detail}', '{store_name}', '{timestamp}', '{link}'
                ],
                [
                    $actorName, $actorRole, $activityType, $serviceName,
                    $oldPrice, $newPrice, $price, $unit, $courierName,
                    $userName, $roleOld, $roleNew, $settingName,
                    $detail, $detail, $storeName, $timestamp, $link
                ],
                $rawTpl
            );

            $dedupeHash = md5($activityType . ':' . ($storeId ?? 0) . ':' . json_encode($data) . ':' . $seq . ':' . date('YmdHi'));
            $dedupe = "ACT:{$activityType}:{$storeId}:{$dedupeHash}:SEQ:{$seq}";

            // 1. Send to Store Owner & additional store recipients
            if ($storeId !== null && $storeId > 0) {
                $storeStaff = $this->getStoreStaff($storeId);
                foreach ($storeStaff as $staff) {
                    if (($staff['staff_role'] ?? '') === 'owner_toko' && !empty($staff['phone'])) {
                        if ($this->send($staff['phone'], $staff['name'], $activityType . ':SEQ' . $seq, $msg, $dedupe . ':OWNER', (int)$staff['id'], false, 'ACTIVITY', $storeId)) {
                            $sentAny = true;
                        }
                    }
                }
                $storeRecipients = $this->recipientModel->getActivePhoneNumbers('ACTIVITY', $storeId);
                foreach ($storeRecipients as $idx => $rPhone) {
                    if ($this->send($rPhone, 'Staff Toko', $activityType . ':SEQ' . $seq, $msg, $dedupe . ':ST_ADD:' . $idx, null, false, 'ACTIVITY', $storeId)) {
                        $sentAny = true;
                    }
                }
            }

            // 2. Send to Platform recipients
            $platRecipients = $this->recipientModel->getActivePhoneNumbers('ACTIVITY', null);
            foreach ($platRecipients as $idx => $rPhone) {
                if ($this->send($rPhone, 'Admin Platform', $activityType . ':SEQ' . $seq, $msg, $dedupe . ':PL_ADD:' . $idx, null, false, 'ACTIVITY', $storeId)) {
                    $sentAny = true;
                }
            }
        }

        return $sentAny;
    }
}
