-- ==============================================================================
-- DATABASE MIGRATION: 003_store_expenses.sql
-- Project: SmartLaundry AI Marketplace
-- Tambahan: store_expenses (Pencatatan Biaya & Pengeluaran Operasional Toko)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 21. Table: store_expenses (Pengeluaran Operasional Toko)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `store_expenses` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `store_id`     INT UNSIGNED NOT NULL COMMENT 'FK ke stores (toko yang mengeluarkan biaya)',
    `title`        VARCHAR(150) NOT NULL COMMENT 'Judul pengeluaran, misal: Pembelian Deterjen Premium',
    `category`     VARCHAR(60)  NOT NULL DEFAULT 'operasional' COMMENT 'Kategori: bahan_baku, listrik_air, plastik_packing, gaji, perawatan_mesin, operasional',
    `amount`       DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Nominal pengeluaran dalam Rupiah',
    `expense_date` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal/waktu pengeluaran dalam zona waktu Asia/Jakarta',
    `notes`        TEXT         NULL COMMENT 'Catatan tambahan pengeluaran',
    `created_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_store_expenses_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    INDEX `idx_store_expenses_store_id` (`store_id`),
    INDEX `idx_store_expenses_date` (`expense_date`),
    INDEX `idx_store_expenses_cat` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pencatatan pengeluaran operasional toko untuk laporan keuangan & laba rugi';

SET FOREIGN_KEY_CHECKS = 1;
