-- Migration: Add tool_used column to chatbot_messages
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chatbot_messages' AND COLUMN_NAME = 'tool_used';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `chatbot_messages` ADD COLUMN `tool_used` VARCHAR(80) NULL AFTER `detected_intent`',
    'SELECT 1');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

