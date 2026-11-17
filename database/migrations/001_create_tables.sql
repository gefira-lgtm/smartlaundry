-- ==============================================================================
-- DATABASE MIGRATION: 001_create_tables.sql
-- Project: Marketplace Laundry Berbasis Web dengan Integrasi AI (SmartLaundry AI)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Table: roles
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Slug peran: owner_platform, admin_owner, owner_toko, admin_toko, kurir, customer',
    `display_name` VARCHAR(100) NOT NULL COMMENT 'Nama tampilan peran',
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Table: users
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NULL COMMENT 'Bcrypt hash password (nullable jika login via Google)',
    `phone` VARCHAR(25) NULL,
    `google_id` VARCHAR(100) NULL UNIQUE COMMENT 'Google OAuth unique subject ID',
    `avatar` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified_at` TIMESTAMP NULL,
    `phone_verified_at` TIMESTAMP NULL,
    `wa_notifications_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `wa_daily_summary_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_google_id` (`google_id`),
    INDEX `idx_users_role_id` (`role_id`),
    INDEX `idx_users_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Table: addresses
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `label` VARCHAR(50) NOT NULL DEFAULT 'Rumah' COMMENT 'Label: Rumah, Kantor, Kos, Toko, dll',
    `recipient_name` VARCHAR(150) NULL,
    `phone` VARCHAR(25) NULL,
    `full_address` TEXT NOT NULL,
    `rt` VARCHAR(10) NULL,
    `rw` VARCHAR(10) NULL,
    `kelurahan` VARCHAR(100) NULL,
    `kecamatan` VARCHAR(100) NULL,
    `kota` VARCHAR(100) NULL,
    `provinsi` VARCHAR(100) NULL,
    `postal_code` VARCHAR(10) NULL,
    `latitude` DECIMAL(10, 8) NULL COMMENT 'Koordinat latitude Google Maps',
    `longitude` DECIMAL(11, 8) NULL COMMENT 'Koordinat longitude Google Maps',
    `notes` VARCHAR(255) NULL COMMENT 'Patokan lokasi / instruksi pengantaran',
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_addresses_user_id` (`user_id`),
    INDEX `idx_addresses_is_primary` (`is_primary`),
    INDEX `idx_addresses_coordinates` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Table: stores
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `stores`;
CREATE TABLE `stores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `address_id` INT UNSIGNED NULL,
    `phone` VARCHAR(25) NOT NULL,
    `logo` VARCHAR(255) NULL,
    `banner` VARCHAR(255) NULL,
    `operational_hours` VARCHAR(100) NULL DEFAULT '08:00 - 20:00 WIB',
    `rating_avg` DECIMAL(3, 2) NOT NULL DEFAULT 5.00,
    `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Status verifikasi oleh Owner Platform/Admin Owner',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_stores_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_stores_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_stores_owner_id` (`owner_id`),
    INDEX `idx_stores_slug` (`slug`),
    INDEX `idx_stores_is_verified` (`is_verified`),
    INDEX `idx_stores_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Table: store_admins (Pivot / Multi-Admin Toko)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `store_admins`;
CREATE TABLE `store_admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `role_in_store` ENUM('manager', 'operator', 'cashier') NOT NULL DEFAULT 'operator',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_store_admins_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_store_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    UNIQUE KEY `uk_store_user` (`store_id`, `user_id`),
    INDEX `idx_store_admins_store_id` (`store_id`),
    INDEX `idx_store_admins_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Table: laundry_services
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `laundry_services`;
CREATE TABLE `laundry_services` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `category` ENUM('kiloan', 'satuan', 'express', 'special') NOT NULL DEFAULT 'kiloan',
    `price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `unit` VARCHAR(20) NOT NULL DEFAULT 'kg' COMMENT 'kg, item, pasang, meter, set',
    `min_order` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `estimated_duration` VARCHAR(50) NOT NULL DEFAULT '1-2 Hari',
    `description` TEXT NULL,
    `icon` VARCHAR(50) NULL DEFAULT 'bi-water',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_laundry_services_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_laundry_services_store_id` (`store_id`),
    INDEX `idx_laundry_services_category` (`category`),
    INDEX `idx_laundry_services_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. Table: couriers
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `couriers`;
CREATE TABLE `couriers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `store_id` INT UNSIGNED NULL COMMENT 'NULL jika kurir marketplace global, atau FK ke toko spesifik',
    `vehicle_type` VARCHAR(50) NOT NULL DEFAULT 'Motor' COMMENT 'Motor, Mobil Box, Sepeda Listrik',
    `license_plate` VARCHAR(20) NOT NULL,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `is_permanently_inactive` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('TERSEDIA', 'BERTUGAS', 'OFFLINE') NOT NULL DEFAULT 'TERSEDIA',
    `current_lat` DECIMAL(10, 8) NULL,
    `current_lng` DECIMAL(11, 8) NULL,
    `last_location_updated_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_couriers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_couriers_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_couriers_store_id` (`store_id`),
    INDEX `idx_couriers_status` (`status`),
    INDEX `idx_couriers_is_available` (`is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. Table: orders
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Format: LND-YYYYMMDD-XXXX',
    `customer_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `courier_id` INT UNSIGNED NULL COMMENT 'FK ke users (kurir) yang bertugas',
    `pickup_address_id` INT UNSIGNED NOT NULL,
    `delivery_address_id` INT UNSIGNED NULL,
    `status` ENUM(
        'MENUNGGU_KONFIRMASI',
        'MENUNGGU_KURIR',
        'KURIR_MENUJU_LOKASI',
        'LAUNDRY_DIJEMPUT',
        'DALAM_PERJALANAN_KE_TOKO',
        'SAMPAI_DI_TOKO',
        'SEDANG_DITIMBANG',
        'MENUNGGU_PEMBAYARAN',
        'PEMBAYARAN_BERHASIL',
        'SEDANG_DICUCI',
        'SEDANG_DIPROSES',
        'SIAP_DIANTAR',
        'KURIR_MENUJU_CUSTOMER',
        'LAUNDRY_DIANTAR',
        'SELESAI',
        'PEMBAYARAN_KADALUARSA',
        'PROSES_PENGEMBALIAN',
        'DIBATALKAN'
    ) NOT NULL DEFAULT 'MENUNGGU_KONFIRMASI',
    `payment_method` ENUM('CASH', 'ONLINE_QRIS') NOT NULL DEFAULT 'ONLINE_QRIS',
    `total_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `refund_fee` DECIMAL(12, 2) NULL DEFAULT 0.00,
    `refund_fee_bearer` ENUM('STORE', 'CUSTOMER', 'PLATFORM') NULL DEFAULT 'STORE',
    `refund_assigned_courier_id` INT UNSIGNED NULL,
    `courier_assignment_status` ENUM('NONE', 'MENUNGGU_RESPON', 'DITERIMA', 'DITOLAK', 'TIDAK_ADA_KURIR') NOT NULL DEFAULT 'NONE',
    `notes` TEXT NULL,
    `scheduled_pickup_at` DATETIME NULL,
    `picked_up_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_courier` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_orders_refund_courier` FOREIGN KEY (`refund_assigned_courier_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT `fk_orders_pickup_addr` FOREIGN KEY (`pickup_address_id`) REFERENCES `addresses` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_orders_delivery_addr` FOREIGN KEY (`delivery_address_id`) REFERENCES `addresses` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_orders_code` (`order_code`),
    INDEX `idx_orders_customer_id` (`customer_id`),
    INDEX `idx_orders_store_id` (`store_id`),
    INDEX `idx_orders_courier_id` (`courier_id`),
    INDEX `idx_orders_status` (`status`),
    INDEX `idx_orders_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. Table: order_items
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `service_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(8, 2) NOT NULL DEFAULT 1.00,
    `unit_price` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `item_notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_service` FOREIGN KEY (`service_id`) REFERENCES `laundry_services` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX `idx_order_items_order_id` (`order_id`),
    INDEX `idx_order_items_service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. Table: laundry_details (Hasil Penimbangan Aktual di Toko)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `laundry_details`;
CREATE TABLE `laundry_details` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL UNIQUE,
    `weight_actual` DECIMAL(8, 2) NULL COMMENT 'Berat ril ditimbang dalam satuan kg',
    `pieces_count` INT UNSIGNED NULL COMMENT 'Jumlah helai pakaian',
    `notes_from_store` TEXT NULL COMMENT 'Catatan noda, pakaian sobek, atau instruksi khusus',
    `before_photo` VARCHAR(255) NULL COMMENT 'Foto kondisi saat diterima',
    `after_photo` VARCHAR(255) NULL COMMENT 'Foto kondisi saat sudah rapi / packing',
    `weighed_by` INT UNSIGNED NULL COMMENT 'Admin toko yang melakukan penimbangan',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_laundry_details_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_laundry_details_admin` FOREIGN KEY (`weighed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_laundry_details_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. Table: payments
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `payment_code` VARCHAR(50) NOT NULL UNIQUE,
    `method` ENUM('CASH', 'ONLINE_QRIS') NOT NULL DEFAULT 'ONLINE_QRIS',
    `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('PENDING', 'PAID', 'EXPIRED', 'FAILED', 'REFUNDED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    `qris_payload` TEXT NULL COMMENT 'Data string string payload QRIS / EMVCo',
    `qris_image_url` VARCHAR(255) NULL,
    `transaction_ref` VARCHAR(100) NULL COMMENT 'Referensi external payment gateway',
    `expired_at` DATETIME NULL COMMENT 'Batas waktu 1 jam dari penerbitan invoice',
    `reminder_sent_at` DATETIME NULL COMMENT 'Waktu pengiriman pengingat pembayaran 15 menit sebelum kadaluarsa',
    `paid_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_payments_order_id` (`order_id`),
    INDEX `idx_payments_code` (`payment_code`),
    INDEX `idx_payments_status` (`status`),
    INDEX `idx_payments_expired_at` (`expired_at`),
    INDEX `idx_payments_reminder` (`reminder_sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. Table: courier_locations (Riwayat Log Tracking GPS Kurir)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `courier_locations`;
CREATE TABLE `courier_locations` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `courier_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_courier_locations_courier` FOREIGN KEY (`courier_id`) REFERENCES `couriers` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_courier_locations_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_courier_locations_courier_id` (`courier_id`),
    INDEX `idx_courier_locations_order_id` (`order_id`),
    INDEX `idx_courier_locations_recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. Table: notifications
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('order', 'payment', 'system', 'courier', 'ai') NOT NULL DEFAULT 'order',
    `action_url` VARCHAR(255) NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_notifications_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_notifications_user_id` (`user_id`),
    INDEX `idx_notifications_is_read` (`is_read`),
    INDEX `idx_notifications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 14. Table: order_status_histories (Audit Trail Perubahan Status Pesanan)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_status_histories`;
CREATE TABLE `order_status_histories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `changed_by` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_histories_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_histories_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_histories_order_id` (`order_id`),
    INDEX `idx_histories_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 15. Table: courier_task_offers (Penawaran Tugas Kurir Multi-Kurir / Re-offer)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `courier_task_offers`;
CREATE TABLE `courier_task_offers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `courier_id` INT UNSIGNED NOT NULL,
    `task_type` ENUM('PICKUP', 'DELIVERY', 'RETURN') NOT NULL DEFAULT 'PICKUP',
    `status` ENUM('MENUNGGU_RESPON', 'DITERIMA', 'DITOLAK', 'KADALUARSA') NOT NULL DEFAULT 'MENUNGGU_RESPON',
    `offered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `responded_at` TIMESTAMP NULL,
    `reject_reason` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cto_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_cto_courier` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_cto_order` (`order_id`),
    INDEX `idx_cto_courier` (`courier_id`),
    INDEX `idx_cto_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 16. Table: whatsapp_logs (Log Notifikasi WhatsApp Gateway)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `whatsapp_logs`;
CREATE TABLE `whatsapp_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(25) NOT NULL,
    `recipient_name` VARCHAR(150) NULL,
    `event` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('PENDING', 'SENT', 'SIMULATED', 'FAILED', 'SKIPPED_NOT_ALLOWED') NOT NULL DEFAULT 'SIMULATED',
    `provider_message_id` VARCHAR(100) NULL,
    `dedupe_key` VARCHAR(191) NULL UNIQUE,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_error` TEXT NULL,
    `next_retry_at` DATETIME NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wa_phone` (`phone`),
    INDEX `idx_wa_event` (`event`),
    INDEX `idx_wa_status` (`status`),
    INDEX `idx_wa_provider_msg_id` (`provider_message_id`),
    INDEX `idx_wa_dedupe` (`dedupe_key`),
    INDEX `idx_wa_retry` (`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 17. Table: reviews (Ulasan & Rating Layanan Laundry)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL UNIQUE,
    `customer_id` INT UNSIGNED NOT NULL,
    `store_id` INT UNSIGNED NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `comment` TEXT NULL,
    `photo` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reviews_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_customer` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_reviews_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_reviews_store_id` (`store_id`),
    INDEX `idx_reviews_customer_id` (`customer_id`),
    INDEX `idx_reviews_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 18. Table: chatbot_messages (Log Percakapan AI Chatbot)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `chatbot_messages`;
CREATE TABLE `chatbot_messages` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'FK ke users (customer / kurir / role lainnya)',
    `user_role` VARCHAR(50) NOT NULL DEFAULT 'customer' COMMENT 'Peran pengirim saat pesan dikirim',
    `session_id` VARCHAR(128) NOT NULL COMMENT 'PHP session_id() saat percakapan',
    `sender` ENUM('USER', 'BOT') NOT NULL DEFAULT 'USER' COMMENT 'Pengirim baris ini: USER atau BOT',
    `message` TEXT NOT NULL COMMENT 'Isi pesan (sudah disamarkan untuk data sensitif)',
    `detected_intent` VARCHAR(80) NULL COMMENT 'Intent terdeteksi oleh chatbot (misal GREETING, ORDER_STATUS)',
    `tool_used` VARCHAR(80) NULL COMMENT 'Tool / method data service yang dipanggil (misal getRevenueToday, lookupExpenseByAmount)',
    `is_fallback` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 jika pertanyaan tidak dikenali (fallback)',
    `mode` ENUM('MOCK', 'API') NOT NULL DEFAULT 'MOCK' COMMENT 'Mode provider: MOCK = rule-based, API = LLM eksternal',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_chatbot_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_chatbot_user_id` (`user_id`),
    INDEX `idx_chatbot_user_role` (`user_role`),
    INDEX `idx_chatbot_created_at` (`created_at`),
    INDEX `idx_chatbot_intent` (`detected_intent`),
    INDEX `idx_chatbot_is_fallback` (`is_fallback`),
    INDEX `idx_chatbot_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 19. Table: chatbot_knowledge (Basis Pengetahuan Sistem untuk Chatbot)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `chatbot_knowledge`;
CREATE TABLE `chatbot_knowledge` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category`      VARCHAR(60)  NOT NULL COMMENT 'Kategori: order_status, payment, courier, roles, faq, etc.',
    `title`         VARCHAR(200) NOT NULL COMMENT 'Judul entri pengetahuan',
    `keywords`      TEXT         NOT NULL COMMENT 'Kata kunci pencarian, dipisah koma',
    `content`       LONGTEXT     NOT NULL COMMENT 'Konten jawaban dalam format teks/markdown',
    `roles_allowed` VARCHAR(255) NOT NULL DEFAULT 'all' COMMENT 'Role yang boleh mendapat entri ini, dipisah koma, atau "all"',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT INDEX `ft_knowledge_search` (`title`, `keywords`, `content`),
    INDEX `idx_knowledge_category` (`category`),
    INDEX `idx_knowledge_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Basis pengetahuan sistem untuk chatbot AI SmartLaundry';

-- ------------------------------------------------------------------------------
-- 20. Table: chatbot_data_access_logs (Audit Log Pencarian Data via Chatbot)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `chatbot_data_access_logs`;
CREATE TABLE `chatbot_data_access_logs` (
    `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED NOT NULL COMMENT 'User yang melakukan pencarian',
    `role`        VARCHAR(50)  NOT NULL COMMENT 'Role saat pencarian',
    `store_id`    INT UNSIGNED NULL COMMENT 'Scope toko (untuk owner_toko/admin_toko)',
    `tool`        VARCHAR(80)  NOT NULL COMMENT 'Nama method/tool yang dipanggil di ChatbotDataService',
    `params_summary` VARCHAR(500) NULL COMMENT 'Ringkasan parameter (bukan data sensitif)',
    `rows_returned`  SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Jumlah baris yang dikembalikan',
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cda_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_cda_logs_user_id`   (`user_id`),
    INDEX `idx_cda_logs_role`      (`role`),
    INDEX `idx_cda_logs_tool`      (`tool`),
    INDEX `idx_cda_logs_created_at`(`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit log setiap pencarian data yang dilakukan chatbot per user';

-- ------------------------------------------------------------------------------
-- 21. Table: store_expenses (Pengeluaran Operasional Toko)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `store_expenses`;
CREATE TABLE `store_expenses` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id`     INT UNSIGNED NOT NULL COMMENT 'FK ke stores (toko yang mengeluarkan biaya)',
    `recorded_by`  INT UNSIGNED NULL COMMENT 'FK ke users (yang mencatat pengeluaran)',
    `title`        VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Judul pengeluaran lama / kompatibilitas',
    `category`     ENUM('BAHAN_BAKU','KEMASAN','OPERASIONAL','LAINNYA','PERALATAN','UTILITAS','GAJI') NOT NULL DEFAULT 'OPERASIONAL' COMMENT 'Kategori pengeluaran',
    `item_name`    VARCHAR(200) NOT NULL DEFAULT '' COMMENT 'Nama item pengeluaran spesifik',
    `quantity`     DECIMAL(10, 2) NULL DEFAULT 1.00 COMMENT 'Jumlah item',
    `unit`         VARCHAR(30)  NULL DEFAULT 'pcs' COMMENT 'Satuan (kg, liter, drum, pack, dll)',
    `unit_price`   DECIMAL(12, 2) NULL DEFAULT 0.00 COMMENT 'Harga per satuan',
    `amount`       DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Nominal pengeluaran dalam Rupiah (legacy)',
    `total_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total nominal pengeluaran dalam Rupiah',
    `supplier`     VARCHAR(150) NULL COMMENT 'Nama pemasok / vendor',
    `expense_date` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal/waktu pengeluaran dalam zona waktu Asia/Jakarta',
    `notes`        TEXT         NULL COMMENT 'Catatan tambahan pengeluaran',
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_store_expenses_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_store_expenses_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_store_expenses_store_id` (`store_id`),
    INDEX `idx_store_expenses_date` (`expense_date`),
    INDEX `idx_store_expenses_cat` (`category`),
    INDEX `idx_store_expenses_total_amount` (`total_amount`),
    INDEX `idx_store_expenses_store_date` (`store_id`, `expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pencatatan pengeluaran operasional toko untuk laporan keuangan & laba rugi';

-- ------------------------------------------------------------------------------
-- 22. Table: settings (Pengaturan Sistem Global)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` LONGTEXT NULL,
    `value_type` ENUM('STRING', 'INT', 'BOOL', 'JSON') NOT NULL DEFAULT 'STRING',
    `setting_group` VARCHAR(50) NOT NULL,
    `label` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_editable_by` ENUM('owner_platform', 'admin_owner') NOT NULL DEFAULT 'owner_platform',
    `updated_by` INT UNSIGNED NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_settings_key` (`setting_key`),
    INDEX `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 23. Table: setting_audit_logs (Audit Trail Perubahan Konfigurasi)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `setting_audit_logs`;
CREATE TABLE `setting_audit_logs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL,
    `old_value` LONGTEXT NULL,
    `new_value` LONGTEXT NULL,
    `changed_by` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sal_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    INDEX `idx_sal_key` (`setting_key`),
    INDEX `idx_sal_changed_by` (`changed_by`),
    INDEX `idx_sal_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 24. Table: store_settings (Pengaturan Khusus per Toko / Outlet)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `store_settings`;
CREATE TABLE `store_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT UNSIGNED NOT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` LONGTEXT NULL,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_store_settings_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT `fk_store_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
    UNIQUE KEY `uk_store_setting` (`store_id`, `setting_key`),
    INDEX `idx_store_settings_store` (`store_id`),
    INDEX `idx_store_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 25. Table: otp_codes (Kode OTP Verifikasi WhatsApp & Email)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `otp_codes`;
CREATE TABLE `otp_codes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `channel` ENUM('WHATSAPP', 'EMAIL') NOT NULL,
    `destination` VARCHAR(150) NOT NULL,
    `purpose` ENUM('REGISTER', 'CHANGE_PHONE') NOT NULL DEFAULT 'REGISTER',
    `code_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `resend_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `last_sent_at` DATETIME NOT NULL,
    `verified_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_otp_user_id` (`user_id`),
    INDEX `idx_otp_destination` (`destination`),
    INDEX `idx_otp_purpose` (`purpose`),
    INDEX `idx_otp_expires_at` (`expires_at`),
    INDEX `idx_otp_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Penyimpanan kode OTP verifikasi WhatsApp dan Email pelanggan';

SET FOREIGN_KEY_CHECKS = 1;


