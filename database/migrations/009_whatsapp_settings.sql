-- Migration 009: WhatsApp Multi-Category Settings, Recipients, and Scoped Logs
-- Tables for:
-- 1. whatsapp_notification_settings (Category A: OTP, B: TRANSACTION, C: ACTIVITY)
-- 2. whatsapp_notification_recipients (Profile numbers + additional staff/alert numbers)
-- 3. whatsapp_logs enhancements (category, store_id, order_id)
-- 4. otp_codes purpose expansion

CREATE TABLE IF NOT EXISTS `whatsapp_notification_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope` ENUM('GLOBAL', 'STORE') NOT NULL DEFAULT 'GLOBAL',
    `store_id` INT UNSIGNED NULL,
    `category` ENUM('OTP', 'TRANSACTION', 'ACTIVITY') NOT NULL DEFAULT 'TRANSACTION',
    `event_key` VARCHAR(60) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `custom_template` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_wa_settings_scope` (`scope`, `store_id`),
    INDEX `idx_wa_settings_category` (`category`),
    UNIQUE KEY `uk_wa_settings_event` (`scope`, `store_id`, `category`, `event_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `whatsapp_notification_recipients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope` ENUM('GLOBAL', 'STORE') NOT NULL DEFAULT 'STORE',
    `store_id` INT UNSIGNED NULL,
    `category` ENUM('OTP', 'TRANSACTION', 'ACTIVITY') NOT NULL DEFAULT 'TRANSACTION',
    `recipient_name` VARCHAR(100) NOT NULL,
    `phone_number` VARCHAR(25) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'STAFF',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_wa_recipients_scope` (`scope`, `store_id`),
    INDEX `idx_wa_recipients_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- whatsapp_logs: add category column
SET @col_cat = 0;
SELECT COUNT(*) INTO @col_cat FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'category';
SET @sql_cat = IF(@col_cat = 0,
    'ALTER TABLE `whatsapp_logs` ADD COLUMN `category` ENUM(\'OTP\', \'TRANSACTION\', \'ACTIVITY\') NOT NULL DEFAULT \'TRANSACTION\' AFTER `event`',
    'SELECT 1');
PREPARE stmt_cat FROM @sql_cat; EXECUTE stmt_cat; DEALLOCATE PREPARE stmt_cat;

-- whatsapp_logs: add store_id column
SET @col_store = 0;
SELECT COUNT(*) INTO @col_store FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'store_id';
SET @sql_store = IF(@col_store = 0,
    'ALTER TABLE `whatsapp_logs` ADD COLUMN `store_id` INT UNSIGNED NULL AFTER `category`',
    'SELECT 1');
PREPARE stmt_store FROM @sql_store; EXECUTE stmt_store; DEALLOCATE PREPARE stmt_store;

-- whatsapp_logs: add order_id column
SET @col_order = 0;
SELECT COUNT(*) INTO @col_order FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'order_id';
SET @sql_order = IF(@col_order = 0,
    'ALTER TABLE `whatsapp_logs` ADD COLUMN `order_id` INT UNSIGNED NULL AFTER `store_id`',
    'SELECT 1');
PREPARE stmt_order FROM @sql_order; EXECUTE stmt_order; DEALLOCATE PREPARE stmt_order;

-- whatsapp_logs: indexes for scoped lookup
SET @idx_cat = 0;
SELECT COUNT(*) INTO @idx_cat FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND INDEX_NAME = 'idx_wa_logs_category';
SET @sql_idx_cat = IF(@idx_cat = 0,
    'ALTER TABLE `whatsapp_logs` ADD INDEX `idx_wa_logs_category` (`category`)',
    'SELECT 1');
PREPARE stmt_idx_cat FROM @sql_idx_cat; EXECUTE stmt_idx_cat; DEALLOCATE PREPARE stmt_idx_cat;

SET @idx_store = 0;
SELECT COUNT(*) INTO @idx_store FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND INDEX_NAME = 'idx_wa_logs_store';
SET @sql_idx_store = IF(@idx_store = 0,
    'ALTER TABLE `whatsapp_logs` ADD INDEX `idx_wa_logs_store` (`store_id`)',
    'SELECT 1');
PREPARE stmt_idx_store FROM @sql_idx_store; EXECUTE stmt_idx_store; DEALLOCATE PREPARE stmt_idx_store;

-- otp_codes: expand purpose to VARCHAR(50)
ALTER TABLE `otp_codes` MODIFY COLUMN `purpose` VARCHAR(50) NOT NULL DEFAULT 'REGISTER';
