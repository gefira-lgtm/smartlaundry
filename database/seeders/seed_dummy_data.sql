-- ==============================================================================
-- DATABASE SEEDER: seed_dummy_data.sql
-- Project: Marketplace Laundry Berbasis Web dengan Integrasi AI (SmartLaundry AI)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clean existing data
TRUNCATE TABLE `order_status_histories`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `otp_codes`;
TRUNCATE TABLE `courier_locations`;
TRUNCATE TABLE `payments`;
TRUNCATE TABLE `laundry_details`;
TRUNCATE TABLE `order_items`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `couriers`;
TRUNCATE TABLE `laundry_services`;
TRUNCATE TABLE `store_admins`;
TRUNCATE TABLE `stores`;
TRUNCATE TABLE `store_expenses`;
TRUNCATE TABLE `addresses`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `roles`;

-- ------------------------------------------------------------------------------
-- 1. Seed Roles (6 Roles)
-- ------------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'owner_platform', 'Super Owner / Platform Owner', 'Akses penuh terhadap operasional platform, keuangan, dan seluruh toko'),
(2, 'admin_owner', 'Admin Owner', 'Membantu pengawasan platform, verifikasi toko, dan manajemen komplain'),
(3, 'owner_toko', 'Owner Toko Laundry', 'Pemilik usaha laundry mengelola profil toko, harga layanan, dan kurir'),
(4, 'admin_toko', 'Admin Toko / Kasir', 'Operator harian yang melakukan penimbangan cucian dan update status pengerjaan'),
(5, 'kurir', 'Kurir / Jasa Antar', 'Petugas penjemputan dan pengantaran laundry dengan tracking GPS'),
(6, 'customer', 'Customer / Pembeli', 'Pengguna yang memesan jasa laundry, pelacakan live, dan bayar via QRIS/Cash');

-- ------------------------------------------------------------------------------
-- 2. Seed Users
-- Password default: password123 ($2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW)
-- ------------------------------------------------------------------------------
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password`, `phone`, `google_id`, `avatar`, `is_active`, `created_at`) VALUES
-- 1. Owner Platform
(1, 1, 'Bambang Sudirgo (Super Admin)', 'owner@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081122334455', NULL, 'avatar_owner.png', 1, '2026-08-01 08:00:00'),
-- 2. Admin Owner
(2, 2, 'Rina Wijaya (Admin Platform)', 'admin.owner@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081233445566', NULL, 'avatar_admin_owner.png', 1, '2026-08-01 08:30:00'),
-- 3 & 4. Store Owners (2 Toko)
(3, 3, 'Hendra Kurniawan (Owner CleanWash)', 'owner.cleanwash@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081344556677', NULL, 'avatar_owner1.png', 1, '2026-08-05 09:00:00'),
(4, 3, 'Dewi Lestari (Owner FreshSoft)', 'owner.freshsoft@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081455667788', NULL, 'avatar_owner2.png', 1, '2026-08-05 09:30:00'),
-- 5 & 6. Store Admins
(5, 4, 'Fajar Ramadhan (Admin CleanWash)', 'admin.cleanwash@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081566778899', NULL, 'avatar_admin1.png', 1, '2026-08-10 10:00:00'),
(6, 4, 'Maya Anggraini (Admin FreshSoft)', 'admin.freshsoft@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081677889900', NULL, 'avatar_admin2.png', 1, '2026-08-10 10:30:00'),
-- 7 & 8. Couriers (2 Kurir)
(7, 5, 'Budi Santoso (Kurir CleanWash)', 'kurir.budi@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081788990011', NULL, 'avatar_courier1.png', 1, '2026-08-12 11:00:00'),
(8, 5, 'Andi Pratama (Kurir FreshSoft)', 'kurir.andi@smartlaundry.id', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081899001122', NULL, 'avatar_courier2.png', 1, '2026-08-12 11:30:00'),
-- 9, 10, 11. Existing Customers
(9, 6, 'Ani Rahmawati', 'customer.ani@gmail.com', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '081900112233', 'google_uid_109283019283', 'avatar_customer1.png', 1, '2026-08-15 13:00:00'),
(10, 6, 'Deni Firmansyah', 'customer.deni@gmail.com', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '082111223344', NULL, 'avatar_customer2.png', 1, '2026-08-15 14:00:00'),
(11, 6, 'Siti Nurhaliza', 'customer.siti@gmail.com', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '082222334455', 'google_uid_987123987123', 'avatar_customer3.png', 1, '2026-08-15 15:00:00'),
-- 12 & 13. Customer Baru Terdaftar Hari Ini
(12, 6, 'Eko Prasetyo', 'customer.eko@gmail.com', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '082333445566', NULL, 'avatar_customer_eko.png', 1, CONCAT(CURDATE(), ' 07:00:00')),
(13, 6, 'Rian Hidayat', 'customer.rian@gmail.com', '$2y$10$EorXUr1DsYD/o77QF1G66umBdksGxouOZPvDGFQKRH/OZgL5RavpW', '082444556677', NULL, 'avatar_customer_rian.png', 1, CONCAT(CURDATE(), ' 07:15:00'));

-- Mark seeded users verified so they are never locked out
UPDATE `users` SET `phone_verified_at` = COALESCE(`created_at`, NOW()), `email_verified_at` = COALESCE(`created_at`, NOW()), `wa_notifications_enabled` = 1;

-- ------------------------------------------------------------------------------
-- 3. Seed Addresses
-- Termasuk alamat toko & alamat customer lengkap RT/RW, Kecamatan, Koordinat Maps
-- ------------------------------------------------------------------------------
INSERT INTO `addresses` (`id`, `user_id`, `label`, `recipient_name`, `phone`, `full_address`, `rt`, `rw`, `kelurahan`, `kecamatan`, `kota`, `provinsi`, `postal_code`, `latitude`, `longitude`, `notes`, `is_primary`) VALUES
-- Alamat Toko CleanWash (Owner: User 3)
(1, 3, 'Toko CleanWash Express', 'CleanWash Express Store', '081344556677', 'Jl. Kebon Jeruk Raya No. 12', '003', '005', 'Kebon Jeruk', 'Kebon Jeruk', 'Jakarta Barat', 'DKI Jakarta', '11530', -6.19125000, 106.77250000, 'Sebelah Indomaret Kebon Jeruk', 1),
-- Alamat Toko FreshSoft (Owner: User 4)
(2, 4, 'Toko Fresh & Soft Laundry', 'Fresh & Soft Laundry Store', '081455667788', 'Jl. Daan Mogot KM 11 No. 45', '002', '003', 'Cengkareng Timur', 'Cengkareng', 'Jakarta Barat', 'DKI Jakarta', '11730', -6.15540000, 106.73210000, 'Ruko Graha Prima Blok C-4', 1),
-- Alamat Customer 1: Ani (User 9)
(3, 9, 'Rumah Utama', 'Ani Rahmawati', '081900112233', 'Jl. Palmerah Barat No. 88, Komplek Permata Hijau', '005', '002', 'Palmerah', 'Palmerah', 'Jakarta Barat', 'DKI Jakarta', '11480', -6.20810000, 106.78920000, 'Pagar hitam, bel di samping pintu gerbang', 1),
(4, 9, 'Kantor', 'Ani Rahmawati', '081900112233', 'Gedung Wisma 77 Tower 2 Lt. 8, Jl. S. Parman', '001', '001', 'Slipi', 'Palmerah', 'Jakarta Barat', 'DKI Jakarta', '11410', -6.19830000, 106.79710000, 'Titip di lobby reception lantai dasar', 0),
-- Alamat Customer 2: Deni (User 10)
(5, 10, 'Rumah Deni', 'Deni Firmansyah', '082111223344', 'Jl. Tanjung Duren Raya No. 19B', '007', '004', 'Tanjung Duren Utara', 'Grogol Petamburan', 'Jakarta Barat', 'DKI Jakarta', '11470', -6.17550000, 106.78650000, 'Rumah cat krem 2 lantai seberang Apotek K24', 1),
-- Alamat Customer 3: Siti (User 11)
(6, 11, 'Apartemen Siti', 'Siti Nurhaliza', '082222334455', 'Apartemen Mediterania Garden Residences 1 Tower Azalea No. 12A', '001', '007', 'Tanjung Duren Selatan', 'Grogol Petamburan', 'Jakarta Barat', 'DKI Jakarta', '11470', -6.17880000, 106.79120000, 'Masuk lewat lobby tower Azalea, hubungi kurir saat tiba', 1),
-- Alamat Customer 4: Eko (User 12)
(7, 12, 'Rumah Eko', 'Eko Prasetyo', '082333445566', 'Jl. Meruya Ilir No. 55', '003', '004', 'Meruya Utara', 'Kembangan', 'Jakarta Barat', 'DKI Jakarta', '11620', -6.19500000, 106.75500000, 'Rumah pagar hijau', 1),
-- Alamat Customer 5: Rian (User 13)
(8, 13, 'Kos Rian', 'Rian Hidayat', '082444556677', 'Jl. Susilo 2B No. 10', '002', '001', 'Grogol', 'Grogol Petamburan', 'Jakarta Barat', 'DKI Jakarta', '11450', -6.16800000, 106.79000000, 'Kos Biru Kamar 05', 1);

-- ------------------------------------------------------------------------------
-- 4. Seed Stores (2 Toko Terverifikasi)
-- ------------------------------------------------------------------------------
INSERT INTO `stores` (`id`, `owner_id`, `name`, `slug`, `description`, `address_id`, `phone`, `logo`, `banner`, `operational_hours`, `rating_avg`, `rating_count`, `is_verified`, `is_active`) VALUES
(1, 3, 'CleanWash Express', 'cleanwash-express', 'Layanan laundry premium dengan mesin modern, deterjen ramah lingkungan, wangi tahan lama, serta jaminan pengerjaan cepat.', 1, '081344556677', 'store_cleanwash.png', 'banner_cleanwash.jpg', '07:30 - 21:00 WIB', 4.90, 128, 1, 1),
(2, 4, 'Fresh & Soft Laundry', 'fresh-and-soft-laundry', 'Spesialis cuci kiloan higienis, bed cover tebal, setrika uap anti kusut, dan treatment khusus pakaian branded.', 2, '081455667788', 'store_freshsoft.png', 'banner_freshsoft.jpg', '08:00 - 20:00 WIB', 4.80, 95, 1, 1);

-- ------------------------------------------------------------------------------
-- 5. Seed Store Admins (Pivot Table)
-- ------------------------------------------------------------------------------
INSERT INTO `store_admins` (`id`, `store_id`, `user_id`, `role_in_store`) VALUES
(1, 1, 5, 'operator'), -- Fajar admin di CleanWash
(2, 2, 6, 'operator'); -- Maya admin di FreshSoft

-- ------------------------------------------------------------------------------
-- 6. Seed Laundry Services
-- ------------------------------------------------------------------------------
INSERT INTO `laundry_services` (`id`, `store_id`, `name`, `category`, `price`, `unit`, `min_order`, `estimated_duration`, `description`, `icon`, `is_active`) VALUES
-- Layanan Toko 1 (CleanWash Express)
(1, 1, 'Cuci & Setrika Reguler (Kiloan)', 'kiloan', 7000.00, 'kg', 3.00, '2 Hari', 'Pakaian dicuci bersih, diberi pewangi premium, dan disetrika uap rapi berplastik rapi.', 'bi-water', 1),
(2, 1, 'Express 6 Jam Selesai', 'express', 15000.00, 'kg', 2.00, '6 Jam', 'Layanan prioritas cepat selesai dalam 6 jam dengan pengeringan optimal.', 'bi-lightning-charge-fill', 1),
(3, 1, 'Cuci Bed Cover King Size', 'satuan', 35000.00, 'item', 1.00, '2 Hari', 'Pencucian bed cover tebal dengan deterjen anti tungau dan pelembut serat kain.', 'bi-box-seam', 1),
(4, 1, 'Cuci Sepatu Sneaker & Treatment', 'special', 30000.00, 'pasang', 1.00, '2-3 Hari', 'Deep cleaning outsole, midsole, insole, dan deodorizing anti bakteri.', 'bi-shield-check', 1),
-- Layanan Toko 2 (Fresh & Soft)
(5, 2, 'Kiloan Cuci Kering Setrika', 'kiloan', 6500.00, 'kg', 3.00, '1-2 Hari', 'Pencucian higienis 1 mesin 1 pelanggan (tidak dicampur), setrika wangi lembut.', 'bi-water', 1),
(6, 2, 'Setrika Saja Anti Kusut', 'kiloan', 4500.00, 'kg', 3.00, '1 Hari', 'Hanya setrika uap rapi untuk pakaian yang sudah Anda cuci dari rumah.', 'bi-feather', 1),
(7, 2, 'Cuci Selimut / Boneka Besar', 'satuan', 25000.00, 'item', 1.00, '2 Hari', 'Pencucian boneka besar & selimut dengan sanitizer aman untuk anak-anak.', 'bi-emoji-smile', 1);

-- ------------------------------------------------------------------------------
-- 7. Seed Couriers
-- ------------------------------------------------------------------------------
INSERT INTO `couriers` (`id`, `user_id`, `store_id`, `vehicle_type`, `license_plate`, `is_available`, `status`, `current_lat`, `current_lng`, `last_location_updated_at`) VALUES
(1, 7, 1, 'Honda Vario 160', 'B 4821 KLO', 1, 'BERTUGAS', -6.20120000, 106.78230000, NOW()),
(2, 8, 2, 'Yamaha NMAX 155', 'B 3192 TZX', 1, 'TERSEDIA', -6.16230000, 106.74100000, NOW());

-- ------------------------------------------------------------------------------
-- 8. Seed Store Expenses (Pengeluaran Operasional Toko) — Enhanced schema
-- ------------------------------------------------------------------------------
INSERT INTO `store_expenses` (`id`, `store_id`, `recorded_by`, `title`, `category`, `amount`, `expense_date`, `notes`, `item_name`, `quantity`, `unit`, `unit_price`, `total_amount`, `supplier`, `created_at`) VALUES
-- CleanWash Express (Store 1) — Hendra Kurniawan (owner_id=3), Fajar Ramadhan (admin=5)
-- *** Rp 500.000 seed item untuk test "pengeluaran 500.000 untuk apa" ***
(1,  1, 5, 'Deterjen Cair Premium 25L',     'BAHAN_BAKU',   500000.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  'Deterjen cair premium 25L untuk mesin cuci industri',    'Deterjen Cair Premium',    1,  'drum',   500000.00, 500000.00, 'CV Bersih Jaya',        CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 08:00:00')),
(2,  1, 5, 'Kantong Plastik Laundry',        'KEMASAN',      250000.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY),  'Kantong plastik ukuran besar 100pcs/pack',                'Kantong Plastik Laundry', 10, 'pack',    25000.00, 250000.00, 'UD Plastik Sejahtera',  CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 08:30:00')),
(3,  1, 3, 'Sewa Mesin Cuci Tambahan',       'OPERASIONAL', 1500000.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY),  'Sewa 2 unit mesin cuci kapasitas 15kg selama 1 bulan',    'Sewa Mesin Cuci',          1, 'bulan', 1500000.00,1500000.00, 'CV Mesin Laundry Jaya', CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 09:00:00')),
(4,  1, 5, 'Token Listrik Operasional',      'OPERASIONAL',   25000.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY),  'Isi token PLN mesin cuci',                                'Token Listrik',            1, 'bulan',   25000.00,  25000.00, 'PLN',                   CONCAT(DATE_SUB(CURDATE(), INTERVAL 7 DAY), ' 09:00:00')),
(5,  1, 5, 'Pewangi Softener Grade A',       'BAHAN_BAKU',    20000.00, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  'Softener pakaian lembut',                                 'Pewangi Softener',         2, 'botol',   10000.00,  20000.00, 'Toko Kimia Makmur',     CONCAT(DATE_SUB(CURDATE(), INTERVAL 8 DAY), ' 09:00:00')),
(6,  1, 5, 'Hanger Kawat 5 Lusin',           'LAINNYA',       20000.00, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Hanger gantung baju kemeja',                              'Hanger Kawat',             5, 'lusin',    4000.00,  20000.00, '-',                     CONCAT(DATE_SUB(CURDATE(), INTERVAL 10 DAY), ' 08:00:00')),
-- Fresh & Soft Laundry (Store 2) — Dewi Lestari (owner_id=4), Maya Anggraini (admin=6)
-- *** Rp 500.000 seed item untuk test scope isolation ***
(7,  2, 6, 'Pelembut Pakaian 20L',           'BAHAN_BAKU',   500000.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY),  'Pelembut pakaian 20L, aroma bunga segar',                 'Pelembut Pakaian',         2, 'jerigen', 250000.00, 500000.00, 'PT Harum Wangi',        CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 08:15:00')),
(8,  2, 6, 'Tagihan Listrik Oktober',        'OPERASIONAL',  150000.00, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  'Pembayaran tagihan listrik Oktober 2026',                 'Tagihan Listrik',          1, 'bulan',  150000.00, 150000.00, 'PLN',                   CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 08:45:00')),
(9,  2, 6, 'Deterjen Higienis Anti Bakteri', 'BAHAN_BAKU',    75000.00, DATE_SUB(CURDATE(), INTERVAL 6 DAY),  'Deterjen cair mesin depan 10L',                           'Deterjen Higienis',        1, 'jerigen',  75000.00,  75000.00, 'CV Bersih Jaya',        CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 08:45:00')),
(10, 2, 4, 'Perlengkapan Kebersihan',        'LAINNYA',       75000.00, DATE_SUB(CURDATE(), INTERVAL 9 DAY),  'Sapu, pel, ember untuk area toko',                        'Perlengkapan Kebersihan',  3, 'set',     25000.00,  75000.00, '-',                     CONCAT(DATE_SUB(CURDATE(), INTERVAL 9 DAY), ' 09:15:00')),
(11, 2, 6, 'Plastik Packing & Hanger',       'KEMASAN',       30000.00, DATE_SUB(CURDATE(), INTERVAL 11 DAY), 'Plastik bening tebal hari ini',                           'Plastik Packing',          3, 'roll',    10000.00,  30000.00, 'UD Plastik Sejahtera',  CONCAT(DATE_SUB(CURDATE(), INTERVAL 11 DAY), ' 08:30:00')),
(12, 2, 6, 'Gas LPG Pengering',              'OPERASIONAL',   15000.00, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'Tabung gas dryer',                                        'Gas LPG 3kg',              1, 'tabung',  15000.00,  15000.00, 'Agen Gas Barokah',      CONCAT(DATE_SUB(CURDATE(), INTERVAL 12 DAY), ' 08:30:00'))
ON DUPLICATE KEY UPDATE
  `item_name`    = VALUES(`item_name`),
  `quantity`     = VALUES(`quantity`),
  `unit`         = VALUES(`unit`),
  `unit_price`   = VALUES(`unit_price`),
  `total_amount` = VALUES(`total_amount`),
  `supplier`     = VALUES(`supplier`),
  `recorded_by`  = VALUES(`recorded_by`),
  `notes`        = VALUES(`notes`);

-- ------------------------------------------------------------------------------
-- 9. Seed Orders (Historical + 7-Day Demo)
-- ------------------------------------------------------------------------------
INSERT INTO `orders` (`id`, `order_code`, `customer_id`, `store_id`, `courier_id`, `pickup_address_id`, `delivery_address_id`, `status`, `payment_method`, `total_price`, `delivery_fee`, `notes`, `scheduled_pickup_at`, `picked_up_at`, `completed_at`, `created_at`) VALUES
-- Order Historis 1 s/d 5 (Pertahankan untuk kompatibilitas test lama)
(1, 'LND-20260901-0001', 9,  1, NULL, 3, 3, 'MENUNGGU_KONFIRMASI', 'ONLINE_QRIS',     0.00, 5000.00, 'Tolong jemput pakaian', '2026-09-01 10:00:00', NULL, NULL, '2026-09-01 08:30:00'),
(2, 'LND-20260902-0002', 10, 1, 7,    5, 5, 'KURIR_MENUJU_LOKASI', 'ONLINE_QRIS',     0.00, 5000.00, 'Pakaian campur kemeja', '2026-09-02 11:00:00', NULL, NULL, '2026-09-02 09:15:00'),
(3, 'LND-20260902-0003', 11, 2, 8,    6, 6, 'MENUNGGU_PEMBAYARAN', 'ONLINE_QRIS', 31000.00, 5000.00, 'Cuci kilat',           '2026-09-02 08:00:00', '2026-09-02 08:45:00', NULL, '2026-09-02 07:30:00'),
(4, 'LND-20260902-0004', 9,  1, 7,    3, 3, 'SEDANG_DICUCI',       'ONLINE_QRIS', 28000.00, 5000.00, 'Noda teh di kemeja',    '2026-09-02 09:00:00', '2026-09-02 09:30:00', NULL, '2026-09-02 08:00:00'),
(5, 'LND-20260830-0005', 10, 2, 8,    5, 5, 'SELESAI',             'CASH',        44000.00, 5000.00, 'Bed cover motif bunga', '2026-08-30 14:00:00', '2026-08-30 14:30:00', '2026-08-31 16:00:00', '2026-08-30 13:00:00'),

-- ==========================================
-- ORDER HARI INI (CURDATE) — CLEANWASH EXPRESS (STORE 1)
-- Target: Pemasukan 450.000, Pengeluaran 120.000, Laba 330.000, Berat 18.5 kg, Item 3 pcs, 5 customer dari 6 pesanan
-- ==========================================
(101, 'LND-TDY-101', 9,  1, 7, 3, 3, 'SEDANG_DICUCI', 'ONLINE_QRIS', 75000.00,  5000.00, 'Ani order 1: Cuci Kiloan 10kg',      NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 08:30:00')),
(102, 'LND-TDY-102', 9,  1, 7, 3, 3, 'SEDANG_DICUCI', 'ONLINE_QRIS', 64500.00,  5000.00, 'Ani order 2: Cuci Kiloan 8.5kg',     NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 09:30:00')),
(103, 'LND-TDY-103', 10, 1, 7, 5, 5, 'SEDANG_DICUCI', 'ONLINE_QRIS', 75000.00,  5000.00, 'Deni: Cuci Bed Cover 2 item',        NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 10:00:00')),
(104, 'LND-TDY-104', 11, 1, 7, 6, 6, 'SEDANG_DICUCI', 'ONLINE_QRIS', 35000.00,  5000.00, 'Siti: Cuci Sepatu 1 pasang',         NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 10:30:00')),
(105, 'LND-TDY-105', 12, 1, 7, 7, 7, 'SEDANG_DICUCI', 'ONLINE_QRIS', 100500.00, 5000.00, 'Eko (Cust Baru): Layanan khusus',   NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 11:00:00')),
(106, 'LND-TDY-106', 13, 1, 7, 8, 8, 'SEDANG_DICUCI', 'ONLINE_QRIS', 100000.00, 5000.00, 'Rian (Cust Baru): Layanan khusus',  NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 11:15:00')),

-- ==========================================
-- ORDER HARI INI (CURDATE) — FRESH & SOFT (STORE 2)
-- Target: Pemasukan 111.500, Pengeluaran 30.000, Laba 81.500, Berat 11.0 kg, Item 1 pcs, Customer 2 (Siti & Eko)
-- Termasuk Midnight Boundary 00:01 hari ini, plus 2 data pengecualian (DIBATALKAN & PEMBAYARAN_KADALUARSA)
-- ==========================================
(302, 'LND-TDY-302', 11, 2, 8, 6, 6, 'SEDANG_DICUCI',       'ONLINE_QRIS', 31000.00, 5000.00, 'Siti: Midnight 00:01 hari ini 4kg', NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 00:01:00')),
(201, 'LND-TDY-201', 11, 2, 8, 6, 6, 'SEDANG_DICUCI',       'ONLINE_QRIS', 50500.00, 5000.00, 'Siti: Kiloan sore 7kg',             NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 14:00:00')),
(202, 'LND-TDY-202', 12, 2, 8, 7, 7, 'SEDANG_DICUCI',       'ONLINE_QRIS', 30000.00, 5000.00, 'Eko: 1 Selimut Besar',              NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 15:00:00')),
(204, 'LND-TDY-204', 10, 2, 8, 5, 5, 'DIBATALKAN',          'ONLINE_QRIS', 35000.00, 5000.00, 'PENGECUALIAN: Order Dibatalkan',    NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 11:00:00')),
(205, 'LND-TDY-205', 11, 2, 8, 6, 6, 'PEMBAYARAN_KADALUARSA','ONLINE_QRIS', 42000.00, 5000.00, 'PENGECUALIAN: QRIS Kadaluarsa',   NOW(), NOW(), NULL, CONCAT(CURDATE(), ' 12:00:00')),

-- ==========================================
-- ORDER KEMARIN (DATE_SUB 1 DAY)
-- Termasuk Midnight Boundary 23:59 kemarin
-- ==========================================
(301, 'LND-YST-301', 9,  1, 7, 3, 3, 'SELESAI', 'ONLINE_QRIS', 26000.00, 5000.00, 'CleanWash: Midnight 23:59 kemarin 3kg', NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 23:59:00')),
(109, 'LND-YST-109', 10, 1, 7, 5, 5, 'SELESAI', 'ONLINE_QRIS', 89000.00, 5000.00, 'CleanWash kemarin siang 12kg',        NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 13:00:00')),
(203, 'LND-YST-203', 11, 2, 8, 6, 6, 'SELESAI', 'ONLINE_QRIS', 57000.00, 5000.00, 'FreshSoft kemarin siang 8kg',         NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 14:00:00')),

-- ==========================================
-- ORDER HARI 2 S/D 6 LALU (KONTINUITAS 7 HARI & BULAN INI)
-- ==========================================
(110, 'LND-D2-110', 9,  1, 7, 3, 3, 'SELESAI', 'ONLINE_QRIS', 75000.00, 5000.00, 'CleanWash D-2 10kg', NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 10:00:00')),
(206, 'LND-D2-206', 10, 2, 8, 5, 5, 'SELESAI', 'ONLINE_QRIS', 44000.00, 5000.00, 'FreshSoft D-2 6kg',  NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 11:00:00')),

(111, 'LND-D3-111', 10, 1, 7, 5, 5, 'SELESAI', 'ONLINE_QRIS', 61000.00, 5000.00, 'CleanWash D-3 8kg',  NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 10:00:00')),
(207, 'LND-D3-207', 11, 2, 8, 6, 6, 'SELESAI', 'ONLINE_QRIS', 50500.00, 5000.00, 'FreshSoft D-3 7kg',  NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 11:00:00')),

(112, 'LND-D4-112', 9,  1, 7, 3, 3, 'SELESAI', 'ONLINE_QRIS', 68000.00, 5000.00, 'CleanWash D-4 9kg',  NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 10:00:00')),
(208, 'LND-D4-208', 10, 2, 8, 5, 5, 'SELESAI', 'ONLINE_QRIS', 37500.00, 5000.00, 'FreshSoft D-4 5kg',  NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 11:00:00')),

(113, 'LND-D5-113', 11, 1, 7, 6, 6, 'SELESAI', 'ONLINE_QRIS', 82000.00, 5000.00, 'CleanWash D-5 11kg', NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 10:00:00')),
(209, 'LND-D5-209', 11, 2, 8, 6, 6, 'SELESAI', 'ONLINE_QRIS', 44000.00, 5000.00, 'FreshSoft D-5 6kg',  NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 11:00:00')),

(114, 'LND-D6-114', 10, 1, 7, 5, 5, 'SELESAI', 'ONLINE_QRIS', 57500.00, 5000.00, 'CleanWash D-6 7.5kg',NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 10:00:00')),
(210, 'LND-D6-210', 9,  2, 8, 3, 3, 'SELESAI', 'ONLINE_QRIS', 57000.00, 5000.00, 'FreshSoft D-6 8kg',  NOW(), NOW(), NOW(), CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 11:00:00'));

-- ------------------------------------------------------------------------------
-- 10. Seed Order Items
-- ------------------------------------------------------------------------------
INSERT INTO `order_items` (`id`, `order_id`, `service_id`, `quantity`, `unit_price`, `subtotal`, `item_notes`) VALUES
-- Items Historis 1 s/d 5
(1, 1, 1, 3.00, 7000.00, 21000.00, 'Estimasi awal 3 kg'),
(2, 2, 1, 4.00, 7000.00, 28000.00, 'Estimasi awal 4 kg'),
(3, 3, 5, 4.00, 6500.00, 26000.00, 'Berat ril 4.0 kg'),
(4, 4, 1, 4.00, 7000.00, 28000.00, 'Berat ril 4.0 kg cuci setrika'),
(5, 5, 7, 1.00, 25000.00, 25000.00, '1 Bed cover besar'),
(6, 5, 5, 2.00, 6500.00, 13000.00, '2 kg pakaian biasa'),

-- CleanWash Hari Ini
(101, 101, 1, 10.00, 7000.00, 70000.00, '10 kg cuci & setrika reguler'),
(102, 102, 1,  8.50, 7000.00, 59500.00, '8.5 kg cuci & setrika reguler'),
(103, 103, 3,  2.00, 35000.00, 70000.00, '2 Bed cover king size'),
(104, 104, 4,  1.00, 30000.00, 30000.00, '1 pasang sepatu sneaker'),
(105, 105, 1, 13.64, 7000.00, 95500.00, 'Estimasi layanan'),
(106, 106, 1, 13.57, 7000.00, 95000.00, 'Estimasi layanan'),

-- FreshSoft Hari Ini
(302, 302, 5, 4.00, 6500.00, 26000.00, '4 kg kiloan cuci kering'),
(201, 201, 5, 7.00, 6500.00, 45500.00, '7 kg kiloan cuci kering'),
(202, 202, 7, 1.00, 25000.00, 25000.00, '1 Cuci boneka / selimut'),
(204, 204, 5, 5.00, 6500.00, 32500.00, '5 kg (Dibatalkan)'),
(205, 205, 5, 6.00, 6500.00, 39000.00, '6 kg (Kadaluarsa)'),

-- Kemarin
(301, 301, 1,  3.00, 7000.00, 21000.00, '3 kg CleanWash midnight'),
(109, 109, 1, 12.00, 7000.00, 84000.00, '12 kg CleanWash kemarin'),
(203, 203, 5,  8.00, 6500.00, 52000.00, '8 kg FreshSoft kemarin'),

-- Hari 2 s/d 6 lalu
(110, 110, 1, 10.00, 7000.00, 70000.00, '10 kg D-2 CleanWash'),
(206, 206, 5,  6.00, 6500.00, 39000.00, '6 kg D-2 FreshSoft'),
(111, 111, 1,  8.00, 7000.00, 56000.00, '8 kg D-3 CleanWash'),
(207, 207, 5,  7.00, 6500.00, 45500.00, '7 kg D-3 FreshSoft'),
(112, 112, 1,  9.00, 7000.00, 63000.00, '9 kg D-4 CleanWash'),
(208, 208, 5,  5.00, 6500.00, 32500.00, '5 kg D-4 FreshSoft'),
(113, 113, 1, 11.00, 7000.00, 77000.00, '11 kg D-5 CleanWash'),
(209, 209, 5,  6.00, 6500.00, 39000.00, '6 kg D-5 FreshSoft'),
(114, 114, 1,  7.50, 7000.00, 52500.00, '7.5 kg D-6 CleanWash'),
(210, 210, 5,  8.00, 6500.00, 52000.00, '8 kg D-6 FreshSoft');

-- ------------------------------------------------------------------------------
-- 11. Seed Laundry Details (Hasil Timbang Aktual di Toko)
-- ------------------------------------------------------------------------------
INSERT INTO `laundry_details` (`id`, `order_id`, `weight_actual`, `pieces_count`, `notes_from_store`, `before_photo`, `after_photo`, `weighed_by`, `created_at`) VALUES
-- Historis 1 s/d 3
(1, 3, 4.00, 18, 'Kondisi pakaian baik', 'timbang_ord3_before.jpg', NULL, 6, '2026-09-02 08:50:00'),
(2, 4, 4.00, 16, 'Noda teh diberi spot treatment', 'timbang_ord4_before.jpg', NULL, 5, '2026-09-02 09:35:00'),
(3, 5, 2.00, 9,  'Pakaian rapi wangi lavender', 'timbang_ord5_before.jpg', 'timbang_ord5_after.jpg', 6, '2026-08-30 14:45:00'),

-- CleanWash Hari Ini:
-- Order 101: 10.0 kg
(101, 101, 10.00, 32, 'Pakaian campur', 'timbang_101.jpg', NULL, 5, CONCAT(CURDATE(), ' 09:00:00')),
-- Order 102: 8.5 kg (Ani order ke-2, total kg CleanWash hari ini = 18.5 kg)
(102, 102,  8.50, 28, 'Baju kemeja kantor', 'timbang_102.jpg', NULL, 5, CONCAT(CURDATE(), ' 10:00:00')),
-- Order 103: Bed Cover 2 item satuan (weight_actual null/0)
(103, 103,  NULL,  2, '2 Bed cover king size bersih', 'timbang_103.jpg', NULL, 5, CONCAT(CURDATE(), ' 10:30:00')),
-- Order 104: Sepatu 1 pasang (weight_actual null/0)
(104, 104,  NULL,  1, '1 Pasang sneaker putih', 'timbang_104.jpg', NULL, 5, CONCAT(CURDATE(), ' 11:00:00')),
-- Order 105 & 106 belum ditimbang (belum masuk laundry_details)

-- FreshSoft Hari Ini:
-- Order 302: 4.0 kg (Midnight boundary 00:01)
(302, 302, 4.00, 15, 'Cuci kilat subuh', 'timbang_302.jpg', NULL, 6, CONCAT(CURDATE(), ' 00:05:00')),
-- Order 201: 7.0 kg (Total valid FreshSoft = 4.0 + 7.0 = 11.0 kg)
(201, 201, 7.00, 24, 'Kiloan reguler', 'timbang_201.jpg', NULL, 6, CONCAT(CURDATE(), ' 14:30:00')),
-- Order 202: 1 item satuan (weight_actual null)
(202, 202, NULL,  1, '1 Selimut tebal boneka', 'timbang_202.jpg', NULL, 6, CONCAT(CURDATE(), ' 15:15:00')),
-- Order 204: PENGECUALIAN DIBATALKAN (5.0 kg ditimbang hari ini tapi status DIBATALKAN -> JANGAN DIHITUNG)
(204, 204, 5.00, 18, 'Customer batal cuci karena buru-buru', 'timbang_204.jpg', NULL, 6, CONCAT(CURDATE(), ' 11:30:00')),
-- Order 205: PENGECUALIAN PEMBAYARAN_KADALUARSA (6.0 kg ditimbang hari ini tapi expired -> JANGAN DIHITUNG)
(205, 205, 6.00, 22, 'QRIS tidak dibayar dalam 60 menit', 'timbang_205.jpg', NULL, 6, CONCAT(CURDATE(), ' 12:30:00')),

-- Kemarin:
-- Order 301: 3.0 kg (Midnight boundary 23:59:00 kemarin)
(301, 301, 3.00, 12, 'CleanWash 23:59', 'timbang_301.jpg', NULL, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 23:59:30')),
-- Order 109: 12.0 kg CleanWash kemarin
(109, 109, 12.00, 40, 'CleanWash kemarin siang', 'timbang_109.jpg', NULL, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 13:30:00')),
-- Order 203: 8.0 kg FreshSoft kemarin
(203, 203,  8.00, 28, 'FreshSoft kemarin siang', 'timbang_203.jpg', NULL, 6, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 14:30:00')),

-- Hari 2 s/d 6 lalu:
(110, 110, 10.00, 35, 'CleanWash D-2', 'timbang_110.jpg', NULL, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 10:30:00')),
(206, 206,  6.00, 20, 'FreshSoft D-2', 'timbang_206.jpg', NULL, 6, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 11:30:00')),
(111, 111,  8.00, 26, 'CleanWash D-3', 'timbang_111.jpg', NULL, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 10:30:00')),
(207, 207,  7.00, 25, 'FreshSoft D-3', 'timbang_207.jpg', NULL, 6, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 11:30:00')),
(112, 112,  9.00, 30, 'CleanWash D-4', 'timbang_112.jpg', NULL, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 10:30:00')),
(208, 208,  5.00, 16, 'FreshSoft D-4', 'timbang_208.jpg', NULL, 6, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 11:30:00')),
(113, 113, 11.00, 38, 'CleanWash D-5', 'timbang_113.jpg', NULL, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 10:30:00')),
(209, 209,  6.00, 22, 'FreshSoft D-5', 'timbang_209.jpg', NULL, 6, CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 11:30:00')),
(114, 114,  7.50, 24, 'CleanWash D-6', 'timbang_114.jpg', NULL, 5, CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 10:30:00')),
(210, 210,  8.00, 27, 'FreshSoft D-6', 'timbang_210.jpg', NULL, 6, CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 11:30:00'));

-- ------------------------------------------------------------------------------
-- 12. Seed Payments (QRIS & Cash)
-- ------------------------------------------------------------------------------
INSERT INTO `payments` (`id`, `order_id`, `payment_code`, `method`, `amount`, `status`, `qris_payload`, `qris_image_url`, `transaction_ref`, `expired_at`, `paid_at`) VALUES
-- Historis 1 s/d 3
(1, 3, 'PAY-20260902-0003', 'ONLINE_QRIS', 31000.00, 'PENDING', 'payload_qris_sample_3', 'qris_31000.png', 'TRX-QRIS-992102', DATE_ADD(NOW(), INTERVAL 50 MINUTE), NULL),
(2, 4, 'PAY-20260902-0004', 'ONLINE_QRIS', 33000.00, 'PAID',    'payload_qris_sample_4', 'qris_33000.png', 'TRX-QRIS-881273', '2026-09-02 12:00:00', '2026-09-02 11:35:00'),
(3, 5, 'PAY-20260830-0005', 'CASH',        44000.00, 'PAID',    NULL,                   NULL,             'CASH-COLLECTED-8', NULL, '2026-08-31 16:00:00'),

-- CleanWash Hari Ini (Total Pemasukan = 75k + 64.5k + 75k + 35k + 100.5k + 100k = 450.000)
(101, 101, 'PAY-TDY-101', 'ONLINE_QRIS',  75000.00, 'PAID', 'payload_101', 'qris_101.png', 'TRX-101', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 09:10:00')),
(102, 102, 'PAY-TDY-102', 'ONLINE_QRIS',  64500.00, 'PAID', 'payload_102', 'qris_102.png', 'TRX-102', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 10:10:00')),
(103, 103, 'PAY-TDY-103', 'ONLINE_QRIS',  75000.00, 'PAID', 'payload_103', 'qris_103.png', 'TRX-103', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 10:40:00')),
(104, 104, 'PAY-TDY-104', 'ONLINE_QRIS',  35000.00, 'PAID', 'payload_104', 'qris_104.png', 'TRX-104', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 11:10:00')),
(105, 105, 'PAY-TDY-105', 'ONLINE_QRIS', 100500.00, 'PAID', 'payload_105', 'qris_105.png', 'TRX-105', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 11:20:00')),
(106, 106, 'PAY-TDY-106', 'ONLINE_QRIS', 100000.00, 'PAID', 'payload_106', 'qris_106.png', 'TRX-106', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 11:30:00')),

-- FreshSoft Hari Ini (Total Pemasukan = 31k + 50.5k + 30k = 111.500)
(302, 302, 'PAY-TDY-302', 'ONLINE_QRIS',  31000.00, 'PAID',      'payload_302', 'qris_302.png', 'TRX-302', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 00:10:00')),
(201, 201, 'PAY-TDY-201', 'ONLINE_QRIS',  50500.00, 'PAID',      'payload_201', 'qris_201.png', 'TRX-201', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 14:40:00')),
(202, 202, 'PAY-TDY-202', 'ONLINE_QRIS',  30000.00, 'PAID',      'payload_202', 'qris_202.png', 'TRX-202', DATE_ADD(NOW(), INTERVAL 1 HOUR), CONCAT(CURDATE(), ' 15:20:00')),
(204, 204, 'PAY-TDY-204', 'ONLINE_QRIS',  35000.00, 'CANCELLED', 'payload_204', 'qris_204.png', 'TRX-204', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL),
(205, 205, 'PAY-TDY-205', 'ONLINE_QRIS',  42000.00, 'EXPIRED',   'payload_205', 'qris_205.png', 'TRX-205', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL),

-- Kemarin
(301, 301, 'PAY-YST-301', 'ONLINE_QRIS', 26000.00, 'PAID', 'payload_301', 'qris_301.png', 'TRX-301', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 23:59:45')),
(109, 109, 'PAY-YST-109', 'ONLINE_QRIS', 89000.00, 'PAID', 'payload_109', 'qris_109.png', 'TRX-109', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 13:45:00')),
(203, 203, 'PAY-YST-203', 'ONLINE_QRIS', 57000.00, 'PAID', 'payload_203', 'qris_203.png', 'TRX-203', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 14:45:00')),

-- Hari 2 s/d 6 lalu
(110, 110, 'PAY-D2-110', 'ONLINE_QRIS', 75000.00, 'PAID', 'payload_110', 'qris_110.png', 'TRX-110', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 10:45:00')),
(206, 206, 'PAY-D2-206', 'ONLINE_QRIS', 44000.00, 'PAID', 'payload_206', 'qris_206.png', 'TRX-206', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 2 DAY), ' 11:45:00')),
(111, 111, 'PAY-D3-111', 'ONLINE_QRIS', 61000.00, 'PAID', 'payload_111', 'qris_111.png', 'TRX-111', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 10:45:00')),
(207, 207, 'PAY-D3-207', 'ONLINE_QRIS', 50500.00, 'PAID', 'payload_207', 'qris_207.png', 'TRX-207', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 3 DAY), ' 11:45:00')),
(112, 112, 'PAY-D4-112', 'ONLINE_QRIS', 68000.00, 'PAID', 'payload_112', 'qris_112.png', 'TRX-112', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 10:45:00')),
(208, 208, 'PAY-D4-208', 'ONLINE_QRIS', 37500.00, 'PAID', 'payload_208', 'qris_208.png', 'TRX-208', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 4 DAY), ' 11:45:00')),
(113, 113, 'PAY-D5-113', 'ONLINE_QRIS', 82000.00, 'PAID', 'payload_113', 'qris_113.png', 'TRX-113', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 10:45:00')),
(209, 209, 'PAY-D5-209', 'ONLINE_QRIS', 44000.00, 'PAID', 'payload_209', 'qris_209.png', 'TRX-209', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 5 DAY), ' 11:45:00')),
(114, 114, 'PAY-D6-114', 'ONLINE_QRIS', 57500.00, 'PAID', 'payload_114', 'qris_114.png', 'TRX-114', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 10:45:00')),
(210, 210, 'PAY-D6-210', 'ONLINE_QRIS', 57000.00, 'PAID', 'payload_210', 'qris_210.png', 'TRX-210', NULL, CONCAT(DATE_SUB(CURDATE(), INTERVAL 6 DAY), ' 11:45:00'));

-- ------------------------------------------------------------------------------
-- 13. Seed Courier Locations
-- ------------------------------------------------------------------------------
INSERT INTO `courier_locations` (`id`, `courier_id`, `order_id`, `latitude`, `longitude`, `recorded_at`) VALUES
(1, 1, 2, -6.18200000, 106.77800000, DATE_SUB(NOW(), INTERVAL 15 MINUTE)),
(2, 1, 2, -6.17950000, 106.78120000, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(3, 1, 2, -6.17700000, 106.78450000, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(4, 1, 2, -6.17550000, 106.78650000, NOW());

-- ------------------------------------------------------------------------------
-- 14. Seed Notifications
-- ------------------------------------------------------------------------------
INSERT INTO `notifications` (`id`, `user_id`, `order_id`, `title`, `message`, `type`, `action_url`, `is_read`) VALUES
(1, 9, 1, 'Pesanan Dibuat', 'Pesanan #LND-20260901-0001 berhasil dibuat.', 'order', 'orders/1', 1),
(2, 10, 2, 'Kurir Menuju Lokasi', 'Kurir Budi Santoso sedang dalam perjalanan.', 'courier', 'tracking/2', 0),
(3, 11, 3, 'Tagihan QRIS Siap Dibayar', 'Laundry telah selesai ditimbang (4.0 kg).', 'payment', 'payment/3', 0),
(4, 9, 4, 'Pembayaran Berhasil Diterima', 'Pembayaran sebesar Rp 33.000 berhasil diverifikasi.', 'payment', 'orders/4', 1);

-- ------------------------------------------------------------------------------
-- 15. Seed Order Status Histories (Audit Trail)
-- ------------------------------------------------------------------------------
INSERT INTO `order_status_histories` (`id`, `order_id`, `status`, `changed_by`, `notes`, `created_at`) VALUES
(1, 1, 'MENUNGGU_KONFIRMASI', 9, 'Pesanan dibuat oleh customer', '2026-09-01 08:30:00'),
(2, 2, 'MENUNGGU_KONFIRMASI', 10, 'Pesanan dibuat oleh customer', '2026-09-02 09:15:00'),
(3, 2, 'MENUNGGU_KURIR', 3, 'Toko mengonfirmasi pesanan', '2026-09-02 09:20:00'),
(4, 2, 'KURIR_MENUJU_LOKASI', 7, 'Kurir menuju lokasi', '2026-09-02 09:25:00'),
(5, 3, 'MENUNGGU_KONFIRMASI', 11, 'Pesanan dibuat oleh customer', '2026-09-02 07:30:00'),
(6, 3, 'SAMPAI_DI_TOKO', 8, 'Laundry tiba di toko Fresh & Soft', '2026-09-02 08:45:00'),
(7, 3, 'SEDANG_DITIMBANG', 6, 'Admin Maya menimbang: 4.0 kg', '2026-09-02 08:50:00'),
(8, 3, 'MENUNGGU_PEMBAYARAN', 6, 'Tagihan QRIS diterbitkan', '2026-09-02 08:55:00'),
(9, 4, 'MENUNGGU_KONFIRMASI', 9, 'Pesanan dibuat', '2026-09-02 08:00:00'),
(10, 4, 'SAMPAI_DI_TOKO', 7, 'Laundry tiba di CleanWash', '2026-09-02 09:30:00'),
(11, 4, 'SEDANG_DITIMBANG', 5, 'Admin Fajar menimbang: 4.0 kg', '2026-09-02 09:35:00'),
(12, 4, 'PEMBAYARAN_BERHASIL', 9, 'Pembayaran QRIS lunas', '2026-09-02 09:40:00'),
(13, 4, 'SEDANG_DICUCI', 5, 'Masuk mesin cuci #02', '2026-09-02 09:45:00'),
(14, 5, 'MENUNGGU_KONFIRMASI', 10, 'Pesanan dibuat', '2026-08-30 13:00:00'),
(15, 5, 'SELESAI', 8, 'Pesanan selesai diserahkan', '2026-08-31 16:00:00');

SET FOREIGN_KEY_CHECKS = 1;
