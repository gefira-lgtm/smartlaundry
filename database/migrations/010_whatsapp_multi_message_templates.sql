-- Migration 010: WhatsApp Multi-Message Templates per Event
-- Allows sending multiple separate chat messages for a single event/process

CREATE TABLE IF NOT EXISTS `whatsapp_notification_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope` ENUM('GLOBAL', 'STORE') NOT NULL DEFAULT 'GLOBAL',
    `store_id` INT UNSIGNED NULL,
    `category` ENUM('OTP', 'TRANSACTION', 'ACTIVITY') NOT NULL DEFAULT 'TRANSACTION',
    `event_key` VARCHAR(60) NOT NULL,
    `message_sequence` INT UNSIGNED NOT NULL DEFAULT 1,
    `title` VARCHAR(120) NULL,
    `template_content` TEXT NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_wa_tmpl_scope` (`scope`, `store_id`),
    INDEX `idx_wa_tmpl_cat` (`category`),
    INDEX `idx_wa_tmpl_event_seq` (`event_key`, `message_sequence`),
    UNIQUE KEY `uk_wa_tmpl_seq` (`scope`, `store_id`, `category`, `event_key`, `message_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default multi-message for courier pickup (Pesan 1: Info Kurir, Pesan 2: Kurir Menuju Lokasi)
INSERT INTO `whatsapp_notification_templates` 
    (`scope`, `store_id`, `category`, `event_key`, `message_sequence`, `title`, `template_content`, `is_enabled`, `created_at`, `updated_at`)
VALUES 
    (
        'GLOBAL', 
        NULL, 
        'TRANSACTION', 
        'customer_courier_to_pickup', 
        1, 
        'Pesan 1: Detail Kurir Ditugaskan', 
        'Halo, {nama_customer}! 👋\n\nKurir kami telah ditugaskan untuk menjemput pakaian cucian Anda pada pesanan *{nomor_pesanan}* di outlet {store_name}. 📋\n\n*Detail Kurir Penjemput:*\n- Nama Kurir: {nama_kurir}\n- WhatsApp: {wa_kurir}\n- Kendaraan: {jenis_kendaraan}\n- Plat Nomor: {plat_nomor}\n\nMohon pastikan cucian sudah siap diserahkan. 😊{link}', 
        1, 
        NOW(), 
        NOW()
    ),
    (
        'GLOBAL', 
        NULL, 
        'TRANSACTION', 
        'customer_courier_to_pickup', 
        2, 
        'Pesan 2: Kurir Sedang Menuju Lokasi', 
        'Halo, {nama_customer}! 👋\n\nKabar baik! Kurir {nama_kurir} saat ini *SEDANG MENUJU KE LOKASI ANDA* untuk menjemput pakaian cucian. 🛵💨\n\nMohon pastikan nomor WhatsApp Anda aktif saat kurir tiba di lokasi. Terima kasih telah mempercayai SmartLaundry! 🙏{link}', 
        1, 
        NOW(), 
        NOW()
    )
ON DUPLICATE KEY UPDATE 
    `template_content` = VALUES(`template_content`),
    `title` = VALUES(`title`),
    `updated_at` = NOW();
