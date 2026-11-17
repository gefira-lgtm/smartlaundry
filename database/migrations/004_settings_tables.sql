-- ==============================================================================
-- DATABASE MIGRATION: 004_settings_tables.sql
-- Project: SmartLaundry AI Marketplace
-- Tambahan: settings, setting_audit_logs, store_settings
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 22. Table: settings (Pengaturan Sistem Global)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Penyimpanan konfigurasi dinamis sistem SmartLaundry';

-- ------------------------------------------------------------------------------
-- 23. Table: setting_audit_logs (Audit Trail Perubahan Konfigurasi)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `setting_audit_logs` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit trail riwayat perubahan setting oleh user';

-- ------------------------------------------------------------------------------
-- 24. Table: store_settings (Pengaturan Khusus per Toko / Outlet)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `store_settings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pengaturan operasional khusus masing-masing outlet laundry';

SET FOREIGN_KEY_CHECKS = 1;
