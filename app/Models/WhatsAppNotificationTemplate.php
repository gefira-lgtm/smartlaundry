<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class WhatsAppNotificationTemplate extends Model {
    protected string $table = 'whatsapp_notification_templates';
    protected string $primaryKey = 'id';

    /**
     * Default multi-messages definition by category and event_key
     */
    public function getDefaultMessages(string $category, string $eventKey): array {
        $category = strtoupper(trim($category));
        $eventKey = strtolower(trim($eventKey));

        // Event spesifik: Kurir Menjemput memiliki 2 pesan terpisah secara default
        if ($eventKey === 'customer_courier_to_pickup') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'customer_courier_to_pickup',
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Detail Kurir Ditugaskan',
                    'template_content' => "Halo, {nama_customer}! 👋\n\nKurir kami telah ditugaskan untuk menjemput pakaian cucian Anda pada pesanan *{nomor_pesanan}* di outlet {store_name}. 📋\n\n*Detail Kurir Penjemput:*\n- Nama Kurir: {nama_kurir}\n- WhatsApp: {wa_kurir}\n- Kendaraan: {jenis_kendaraan}\n- Plat Nomor: {plat_nomor}\n\nMohon pastikan cucian sudah siap diserahkan. 😊{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ],
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'customer_courier_to_pickup',
                    'message_sequence' => 2,
                    'title' => 'Pesan 2: Kurir Sedang Menuju Lokasi',
                    'template_content' => "Halo, {nama_customer}! 👋\n\nKabar baik! Kurir {nama_kurir} saat ini *SEDANG MENUJU KE LOKASI ANDA* untuk menjemput pakaian cucian. 🛵💨\n\nMohon pastikan nomor WhatsApp Anda aktif saat kurir tiba di lokasi. Terima kasih telah mempercayai SmartLaundry! 🙏{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 1. COURIER_ASSIGNED_PICKUP
        if ($eventKey === 'courier_assigned_pickup') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'courier_assigned_pickup',
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Detail Kurir Ditugaskan',
                    'template_content' => "Halo, {nama_customer}! 👋\n\nKurir SmartLaundry telah ditugaskan untuk menjemput pakaian cucian Anda pada pesanan *{nomor_pesanan}* di outlet {store_name}. 📋\n\nMohon siapkan pakaian cucian Anda. 😊{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ],
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'courier_assigned_pickup',
                    'message_sequence' => 2,
                    'title' => 'Pesan 2: Detail Kontak & Kendaraan Kurir',
                    'template_content' => "*Detail Kurir Penjemput:*\n- Nama Kurir: {nama_kurir}\n- WhatsApp: {wa_kurir}\n- Kendaraan: {jenis_kendaraan}\n- Plat Nomor: {plat_nomor}\n\nKurir kami akan segera meluncur ke lokasi Anda.",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 2. COURIER_EN_ROUTE_PICKUP
        if ($eventKey === 'courier_en_route_pickup') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'courier_en_route_pickup',
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Kurir Berangkat Menuju Lokasi',
                    'template_content' => "Halo {customer_name},\n\nKurir SmartLaundry sedang menuju lokasi Anda untuk menjemput laundry. 🛵💨\n\nNomor Pesanan: {order_code}\nNama Kurir: {courier_name}\nNomor WhatsApp Kurir: {courier_phone}\nKendaraan: {vehicle_type}\nPlat Nomor: {plate_number}\n\nSilakan persiapkan laundry Anda.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ],
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'courier_en_route_pickup',
                    'message_sequence' => 2,
                    'title' => 'Pesan 2: Kurir Sedang Menuju Lokasi',
                    'template_content' => "Mohon pastikan nomor WhatsApp Anda aktif saat kurir tiba di lokasi. Terima kasih telah mempercayai SmartLaundry! 🙏",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 3. COURIER_ARRIVED_PICKUP
        if ($eventKey === 'courier_arrived_pickup') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'courier_arrived_pickup',
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Kurir Tiba di Lokasi Penjemputan',
                    'template_content' => "Halo {customer_name},\n\nKurir kami sudah tiba di lokasi penjemputan. 📍\n\nNomor Pesanan: {order_code}\nNama Kurir: {courier_name}\nNomor WhatsApp: {courier_phone}\n\nSilakan temui kurir kami untuk serah terima kantong cucian.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 4. COURIER_PICKED_UP / customer_laundry_picked
        if ($eventKey === 'courier_picked_up' || $eventKey === 'customer_laundry_picked') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => $eventKey,
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Laundry Berhasil Dijemput',
                    'template_content' => "Halo {customer_name},\n\nLaundry Anda berhasil dijemput oleh kurir. 🧺✨\n\nNomor Pesanan: {order_code}\nNama Kurir: {courier_name}\nWaktu Penjemputan: {activity_time}\nOutlet Tujuan: {store_name}\n\nCucian Anda sedang dibawa menuju outlet untuk proses penimbangan.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 5. COURIER_DELIVERED_TO_STORE / customer_arrived_at_store
        if ($eventKey === 'courier_delivered_to_store' || $eventKey === 'customer_arrived_at_store') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => $eventKey,
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Laundry Diserahkan ke Toko',
                    'template_content' => "Halo {customer_name},\n\nLaundry Anda sudah diserahkan kepada toko dan tiba di outlet {store_name}. ⚖️\n\nNomor Pesanan: {order_code}\nWaktu Tiba: {activity_time}\nStatus: Siap Ditimbang\n\nDetail tagihan dan QRIS akan dikirimkan otomatis setelah penimbangan selesai.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 6. DELIVERY_READY / customer_laundry_ready
        if ($eventKey === 'delivery_ready' || $eventKey === 'customer_laundry_ready') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => $eventKey,
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Laundry Siap Diantar',
                    'template_content' => "Halo {customer_name},\n\nLaundry Anda sudah selesai diproses dan siap diantar. 🎉\n\nNomor Pesanan: {order_code}\nNama Toko: {store_name}\n\nKurir kami akan segera ditugaskan untuk mengantarkan laundry bersih & wangi Anda.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 7. COURIER_ASSIGNED_DELIVERY
        if ($eventKey === 'courier_assigned_delivery') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'courier_assigned_delivery',
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Kurir Ditugaskan Mengantar',
                    'template_content' => "Halo {customer_name},\n\nKurir telah ditugaskan untuk mengantarkan laundry pesanan {order_code}. 🛵\n\nNama Kurir: {courier_name}\nNomor Kurir: {courier_phone}\nKendaraan: {vehicle_type} ({plate_number})\n\nPakaian Anda akan segera meluncur ke lokasi pengantaran.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 8. COURIER_EN_ROUTE_DELIVERY / customer_courier_to_delivery
        if ($eventKey === 'courier_en_route_delivery' || $eventKey === 'customer_courier_to_delivery') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => $eventKey,
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Kurir Sedang Mengantarkan Laundry',
                    'template_content' => "Halo {customer_name},\n\nKurir sedang mengantarkan laundry Anda. 🛵📦\n\nNomor Pesanan: {order_code}\nNama Kurir: {courier_name}\nNomor Kurir: {courier_phone}\nKendaraan: {vehicle_type} ({plate_number})\n\nMohon pastikan ada penerima di lokasi tujuan.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 9. COURIER_ARRIVED_DELIVERY
        if ($eventKey === 'courier_arrived_delivery') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => 'courier_arrived_delivery',
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Kurir Tiba di Lokasi Customer',
                    'template_content' => "Halo {customer_name},\n\nKurir kami sudah tiba di alamat Anda membawa cucian bersih. 📍📦\n\nNomor Pesanan: {order_code}\nNama Kurir: {courier_name}\nNomor WhatsApp: {courier_phone}\n\nSilakan temui kurir kami untuk serah terima pakaian.{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 10. COURIER_DELIVERED_TO_CUSTOMER / customer_laundry_delivered
        if ($eventKey === 'courier_delivered_to_customer' || $eventKey === 'customer_laundry_delivered') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'TRANSACTION',
                    'event_key' => $eventKey,
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: Laundry Berhasil Diserahkan',
                    'template_content' => "Halo {customer_name},\n\nLaundry Anda telah berhasil diserahkan. ✅\n\nNomor Pesanan: {order_code}\nWaktu Penyerahan: {activity_time}\n\nTerima kasih telah menggunakan SmartLaundry Pro. Mohon periksa cucian Anda dan berikan ulasan Anda di aplikasi! ⭐{link}",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // 11. OTP_PAYMENT
        if ($eventKey === 'otp_payment') {
            return [
                [
                    'id' => null,
                    'scope' => 'GLOBAL',
                    'store_id' => null,
                    'category' => 'OTP',
                    'event_key' => 'otp_payment',
                    'message_sequence' => 1,
                    'title' => 'Pesan 1: OTP Verifikasi Pembayaran',
                    'template_content' => "SmartLaundry: Kode OTP verifikasi pembayaran Anda untuk pesanan {order_code} adalah *{otp_code}*.\n\nKode ini berlaku selama {expiry_minutes} menit. JANGAN bagikan kode ini kepada siapapun demi keamanan transaksi Anda.",
                    'is_enabled' => 1,
                    'is_default' => true
                ]
            ];
        }

        // Fallback untuk event lainnya: ambil dari notification_templates.php config
        $templateFile = ROOT_PATH . '/config/notification_templates.php';
        $content = "SmartLaundry: Notifikasi {$eventKey}.";
        if (file_exists($templateFile)) {
            $configs = require $templateFile;
            if (!empty($configs[$eventKey])) {
                $content = $configs[$eventKey];
            }
        }

        $defaultTitle = match ($eventKey) {
            'customer_order_created' => 'Pesan 1: Konfirmasi Pesanan Dibuat',
            'customer_laundry_picked' => 'Pesan 1: Laundry Berhasil Diambil Kurir',
            'customer_arrived_at_store' => 'Pesan 1: Laundry Tiba di Outlet',
            'customer_weighing_completed' => 'Pesan 1: Penimbangan Selesai & Tagihan Siap',
            'customer_ready_to_pay_qris' => 'Pesan 1: Tagihan QRIS Dinamis (1 Jam)',
            'customer_ready_to_pay_cash' => 'Pesan 1: Tagihan Pembayaran Tunai (CASH)',
            'customer_payment_success' => 'Pesan 1: Pembayaran Berhasil Diterima (LUNAS)',
            'customer_laundry_washing' => 'Pesan 1: Laundry Sedang Dicuci & Diproses',
            'customer_laundry_ready' => 'Pesan 1: Laundry Selesai Dicuci Bersih & Wangi',
            'customer_courier_to_delivery' => 'Pesan 1: Kurir Sedang Mengantar Cucian',
            'customer_order_completed' => 'Pesan 1: Pesanan Selesai & Permintaan Ulasan',
            'otp_whatsapp', 'otp_login', 'otp_verify_phone', 'otp_reset_password', 'otp_verify_account' => 'Pesan 1: Kode OTP Masuk WA',
            'activity_service_added' => 'Pesan 1: Alert Penambahan Layanan Baru',
            'activity_price_changed' => 'Pesan 1: Alert Perubahan Harga Layanan',
            'activity_store_updated' => 'Pesan 1: Alert Pembaruan Data Toko',
            'activity_courier_changed' => 'Pesan 1: Alert Perubahan Kurir Toko',
            default => 'Pesan 1'
        };

        return [
            [
                'id' => null,
                'scope' => 'GLOBAL',
                'store_id' => null,
                'category' => $category,
                'event_key' => $eventKey,
                'message_sequence' => 1,
                'title' => $defaultTitle,
                'template_content' => $content,
                'is_enabled' => 1,
                'is_default' => true
            ]
        ];
    }

    /**
     * Get all messages for an event (sorted by message_sequence ASC)
     * Checks store level first, then global, then default fallback.
     */
    public function getEventMessages(string $category, string $eventKey, ?int $storeId = null, bool $onlyEnabled = false): array {
        $category = strtoupper(trim($category));
        $eventKey = strtolower(trim($eventKey));

        try {
            $db = Database::getConnection();

            // 1. Check Store Level overrides (if storeId is provided and category != OTP)
            if ($storeId !== null && $storeId > 0 && $category !== 'OTP') {
                $sql = "SELECT * FROM `{$this->table}` 
                        WHERE `scope` = 'STORE' AND `store_id` = :sid AND `category` = :cat AND `event_key` = :ev";
                if ($onlyEnabled) {
                    $sql .= " AND `is_enabled` = 1";
                }
                $sql .= " ORDER BY `message_sequence` ASC, `id` ASC";

                $stmt = $db->prepare($sql);
                $stmt->execute(['sid' => $storeId, 'cat' => $category, 'ev' => $eventKey]);
                $storeRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($storeRows)) {
                    return $storeRows;
                }
            }

            // 2. Check Global Level settings
            $sql = "SELECT * FROM `{$this->table}` 
                    WHERE `scope` = 'GLOBAL' AND `category` = :cat AND `event_key` = :ev";
            if ($onlyEnabled) {
                $sql .= " AND `is_enabled` = 1";
            }
            $sql .= " ORDER BY `message_sequence` ASC, `id` ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute(['cat' => $category, 'ev' => $eventKey]);
            $globalRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($globalRows)) {
                return $globalRows;
            }
        } catch (\Throwable $th) {
            // Database not ready or socket unreachable, proceed to default fallback
        }

        // 3. Fallback to default in-code messages
        $defaults = $this->getDefaultMessages($category, $eventKey);
        if ($onlyEnabled) {
            return array_filter($defaults, fn($m) => !empty($m['is_enabled']));
        }
        return $defaults;
    }

    /**
     * Get next sequence number for an event
     */
    public function getNextSequence(string $scope, ?int $storeId, string $category, string $eventKey): int {
        try {
            $db = Database::getConnection();
            $sql = "SELECT MAX(`message_sequence`) as max_seq FROM `{$this->table}` 
                    WHERE `scope` = :scope AND `category` = :cat AND `event_key` = :ev";
            $params = ['scope' => $scope, 'cat' => $category, 'ev' => $eventKey];
            if ($scope === 'STORE' && $storeId !== null) {
                $sql .= " AND `store_id` = :sid";
                $params['sid'] = $storeId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $maxSeq = $stmt->fetchColumn();
            return $maxSeq !== false && $maxSeq !== null ? (int)$maxSeq + 1 : 1;
        } catch (\Throwable $th) {
            $defaults = $this->getDefaultMessages($category, $eventKey);
            return count($defaults) + 1;
        }
    }

    /**
     * Create a new message for an event
     */
    public function createMessage(array $data): int|bool {
        $db = Database::getConnection();
        $scope = strtoupper($data['scope'] ?? 'GLOBAL') === 'STORE' ? 'STORE' : 'GLOBAL';
        $storeId = ($scope === 'STORE' && !empty($data['store_id'])) ? (int)$data['store_id'] : null;
        $category = strtoupper(trim($data['category'] ?? 'TRANSACTION'));
        $eventKey = strtolower(trim($data['event_key'] ?? ''));
        $sequence = !empty($data['message_sequence']) ? (int)$data['message_sequence'] : $this->getNextSequence($scope, $storeId, $category, $eventKey);
        $title = trim($data['title'] ?? "Pesan {$sequence}");
        $content = trim($data['template_content'] ?? '');
        $isEnabled = !empty($data['is_enabled']) ? 1 : 0;

        $stmt = $db->prepare(
            "INSERT INTO `{$this->table}` 
                (`scope`, `store_id`, `category`, `event_key`, `message_sequence`, `title`, `template_content`, `is_enabled`, `created_at`, `updated_at`)
             VALUES 
                (:scope, :sid, :cat, :ev, :seq, :title, :content, :enabled, NOW(), NOW())"
        );

        $ok = $stmt->execute([
            'scope' => $scope,
            'sid' => $storeId,
            'cat' => $category,
            'ev' => $eventKey,
            'seq' => $sequence,
            'title' => $title,
            'content' => $content,
            'enabled' => $isEnabled
        ]);

        return $ok ? (int)$db->lastInsertId() : false;
    }

    /**
     * Update an existing message (Anti-IDOR protected)
     */
    public function updateMessage(int $id, array $data, ?int $storeId = null, bool $isPlatform = false): bool {
        $db = Database::getConnection();
        $sql = "UPDATE `{$this->table}` SET `updated_at` = NOW()";
        $params = ['id' => $id];

        if (isset($data['title'])) {
            $sql .= ", `title` = :title";
            $params['title'] = trim($data['title']);
        }
        if (isset($data['template_content'])) {
            $sql .= ", `template_content` = :content";
            $params['content'] = trim($data['template_content']);
        }
        if (isset($data['message_sequence'])) {
            $sql .= ", `message_sequence` = :seq";
            $params['seq'] = (int)$data['message_sequence'];
        }
        if (isset($data['is_enabled'])) {
            $sql .= ", `is_enabled` = :enabled";
            $params['enabled'] = !empty($data['is_enabled']) ? 1 : 0;
        }

        $sql .= " WHERE `id` = :id";

        // Anti-IDOR enforcement:
        if (!$isPlatform) {
            $sql .= " AND `scope` = 'STORE' AND `store_id` = :sid";
            $params['sid'] = (int)$storeId;
        }

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a message (Anti-IDOR protected)
     */
    public function deleteMessage(int $id, ?int $storeId = null, bool $isPlatform = false): bool {
        $db = Database::getConnection();
        $sql = "DELETE FROM `{$this->table}` WHERE `id` = :id";
        $params = ['id' => $id];

        if (!$isPlatform) {
            $sql .= " AND `scope` = 'STORE' AND `store_id` = :sid";
            $params['sid'] = (int)$storeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Toggle enabled status of a message (Anti-IDOR protected)
     */
    public function toggleMessage(int $id, bool $isEnabled, ?int $storeId = null, bool $isPlatform = false): bool {
        $db = Database::getConnection();
        $sql = "UPDATE `{$this->table}` SET `is_enabled` = :enabled, `updated_at` = NOW() WHERE `id` = :id";
        $params = ['id' => $id, 'enabled' => $isEnabled ? 1 : 0];

        if (!$isPlatform) {
            $sql .= " AND `scope` = 'STORE' AND `store_id` = :sid";
            $params['sid'] = (int)$storeId;
        }

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Find single message by ID
     */
    public function findMessage(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM `{$this->table}` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
