-- ==============================================================================
-- DATABASE MIGRATION: 005_otp_and_whatsapp_enhancements.sql
-- Project: SmartLaundry AI Marketplace
-- Tambahan: otp_codes, users.phone_verified_at, users.wa_notifications_enabled,
--           whatsapp_logs enhancements (dedupe_key, attempts, next_retry_at, etc.)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Alter users: phone_verified_at & wa_notifications_enabled
-- ------------------------------------------------------------------------------
SET @exist_col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone_verified_at');
SET @sql := IF(@exist_col = 0, 'ALTER TABLE `users` ADD COLUMN `phone_verified_at` TIMESTAMP NULL AFTER `email_verified_at`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist_col2 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'wa_notifications_enabled');
SET @sql2 := IF(@exist_col2 = 0, 'ALTER TABLE `users` ADD COLUMN `wa_notifications_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `phone_verified_at`', 'SELECT 1');
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Mark all existing users as verified so existing accounts are not locked out
UPDATE `users` SET `phone_verified_at` = NOW() WHERE `phone_verified_at` IS NULL;
UPDATE `users` SET `email_verified_at` = NOW() WHERE `email_verified_at` IS NULL;

-- ------------------------------------------------------------------------------
-- 2. Alter whatsapp_logs: dedupe_key, attempts, last_error, next_retry_at, status ENUM
-- ------------------------------------------------------------------------------
ALTER TABLE `whatsapp_logs` 
    MODIFY COLUMN `status` ENUM('PENDING', 'SENT', 'SIMULATED', 'FAILED', 'SKIPPED_NOT_ALLOWED') NOT NULL DEFAULT 'SIMULATED';

SET @exist_col3 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'dedupe_key');
SET @sql3 := IF(@exist_col3 = 0, 'ALTER TABLE `whatsapp_logs` ADD COLUMN `dedupe_key` VARCHAR(191) NULL UNIQUE AFTER `status`', 'SELECT 1');
PREPARE stmt FROM @sql3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist_col4 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'attempts');
SET @sql4 := IF(@exist_col4 = 0, 'ALTER TABLE `whatsapp_logs` ADD COLUMN `attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `dedupe_key`', 'SELECT 1');
PREPARE stmt FROM @sql4; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist_col5 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'last_error');
SET @sql5 := IF(@exist_col5 = 0, 'ALTER TABLE `whatsapp_logs` ADD COLUMN `last_error` TEXT NULL AFTER `attempts`', 'SELECT 1');
PREPARE stmt FROM @sql5; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exist_col6 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'next_retry_at');
SET @sql6 := IF(@exist_col6 = 0, 'ALTER TABLE `whatsapp_logs` ADD COLUMN `next_retry_at` DATETIME NULL AFTER `last_error`', 'SELECT 1');
PREPARE stmt FROM @sql6; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------------------
-- 3. Table: otp_codes (Kode OTP Verifikasi WhatsApp & Email)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `otp_codes` (
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

