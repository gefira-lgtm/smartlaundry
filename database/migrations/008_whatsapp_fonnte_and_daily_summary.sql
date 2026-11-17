-- Migration 008: Fonnte WhatsApp Enhancements & Daily Summary Preferences
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND COLUMN_NAME = 'provider_message_id';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `whatsapp_logs` ADD COLUMN `provider_message_id` VARCHAR(100) NULL AFTER `status`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add index on provider_message_id
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_logs' AND INDEX_NAME = 'idx_wa_provider_msg_id';

SET @sql_idx = IF(@idx_exists = 0,
    'ALTER TABLE `whatsapp_logs` ADD INDEX `idx_wa_provider_msg_id` (`provider_message_id`)',
    'SELECT 1');
PREPARE stmt2 FROM @sql_idx; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- Users table: wa_daily_summary_enabled (Default 1 for owner roles)
SET @u_col_exists = 0;
SELECT COUNT(*) INTO @u_col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'wa_daily_summary_enabled';

SET @sql_u = IF(@u_col_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `wa_daily_summary_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `wa_notifications_enabled`',
    'SELECT 1');
PREPARE stmt3 FROM @sql_u; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

