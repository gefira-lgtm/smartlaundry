-- =============================================================================
-- Migration 006: Enhance store_expenses & add chatbot indexes
-- Safe to run on existing databases (idempotent).
-- Adds missing columns to store_expenses and creates indexes for chatbot queries.
-- =============================================================================

-- -----------------------------------------------------------------------
-- 1. Add missing columns to store_expenses
-- -----------------------------------------------------------------------

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'recorded_by';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE `store_expenses` ADD COLUMN `recorded_by` INT UNSIGNED NULL AFTER `store_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'item_name';
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `store_expenses` ADD COLUMN `item_name` VARCHAR(200) NOT NULL DEFAULT "" AFTER `category`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'quantity';
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `store_expenses` ADD COLUMN `quantity` DECIMAL(10,2) NULL DEFAULT 1 AFTER `item_name`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'unit';
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `store_expenses` ADD COLUMN `unit` VARCHAR(30) NULL DEFAULT "pcs" AFTER `quantity`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'unit_price';
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `store_expenses` ADD COLUMN `unit_price` DECIMAL(12,2) NULL DEFAULT 0 AFTER `unit`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'total_amount';
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `store_expenses` ADD COLUMN `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `unit_price`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'supplier';
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `store_expenses` ADD COLUMN `supplier` VARCHAR(150) NULL AFTER `total_amount`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND COLUMN_NAME = 'updated_at';
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `store_expenses` ADD COLUMN `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Back-fill item_name and total_amount from existing title and amount
UPDATE `store_expenses` SET `item_name` = `title` WHERE `item_name` = '' OR `item_name` IS NULL;
UPDATE `store_expenses` SET `total_amount` = `amount` WHERE `total_amount` = 0 OR `total_amount` IS NULL;
UPDATE `store_expenses` SET `unit_price` = `amount` WHERE `unit_price` = 0 OR `unit_price` IS NULL;

-- -----------------------------------------------------------------------
-- 2. Add FK for recorded_by -> users.id (nullable, on delete set null)
-- -----------------------------------------------------------------------

SET @fk_exists = 0;
SELECT COUNT(*) INTO @fk_exists FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND CONSTRAINT_NAME = 'fk_expense_recorded_by';
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `store_expenses` ADD CONSTRAINT `fk_expense_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 3. Add indexes for chatbot search performance
-- -----------------------------------------------------------------------

SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND INDEX_NAME = 'idx_expense_total_amount';
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `store_expenses` ADD INDEX `idx_expense_total_amount` (`total_amount`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_expenses' AND INDEX_NAME = 'idx_expense_store_date';
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `store_expenses` ADD INDEX `idx_expense_store_date` (`store_id`, `expense_date`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payment_amount';
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `payments` ADD INDEX `idx_payment_amount` (`amount`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND INDEX_NAME = 'idx_payment_paid_at';
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `payments` ADD INDEX `idx_payment_paid_at` (`paid_at`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND INDEX_NAME = 'idx_orders_order_code';
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_orders_order_code` (`order_code`)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------
-- 4. Ensure category ENUM includes required values
-- -----------------------------------------------------------------------
ALTER TABLE `store_expenses` MODIFY `category` ENUM(
    'BAHAN_BAKU','KEMASAN','OPERASIONAL','LAINNYA','PERALATAN','UTILITAS','GAJI'
) NOT NULL DEFAULT 'OPERASIONAL';

