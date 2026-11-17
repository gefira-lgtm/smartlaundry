-- Migration 011: Add order_id to otp_codes table for payment OTP verification
-- Date: 2026-11-15

ALTER TABLE `otp_codes` 
ADD COLUMN `order_id` INT UNSIGNED NULL AFTER `user_id`,
ADD INDEX `idx_otp_order_id` (`order_id`);
