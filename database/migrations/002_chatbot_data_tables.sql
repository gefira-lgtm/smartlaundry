-- ==============================================================================
-- DATABASE MIGRATION: 002_chatbot_data_tables.sql
-- Project: SmartLaundry AI Marketplace
-- Tambahan: chatbot_knowledge + chatbot_data_access_logs
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 19. Table: chatbot_knowledge (Basis Pengetahuan Sistem untuk Chatbot)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chatbot_knowledge` (
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
CREATE TABLE IF NOT EXISTS `chatbot_data_access_logs` (
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

SET FOREIGN_KEY_CHECKS = 1;

