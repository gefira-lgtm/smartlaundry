<?php
/**
 * metrics_catalog.php — Katalog metrik platform SmartLaundry.
 *
 * Setiap entri mendefinisikan:
 *   - label        : nama tampilan
 *   - definition   : kalimat penjelas yang akan disertakan di jawaban chatbot
 *   - synonyms     : kata kunci/sinonim yang dikenali NLU
 *   - roles        : role yang boleh mengakses metrik ini
 *   - method       : nama method MetricsService yang menghitung nilai ini
 *   - format       : cara memformat nilai ('currency' | 'integer')
 *   - period       : periode yang berlaku ('all_time')
 */
return [

    'gmv_total' => [
        'label'      => 'Total GMV / Omset Platform',
        'definition' => 'Jumlah seluruh pembayaran lunas (status PAID) dari semua toko di platform.',
        'synonyms'   => [
            'gmv', 'omset', 'omzet', 'pendapatan', 'pemasukan',
            'total transaksi', 'revenue', 'total pendapatan',
            'berapa gmv', 'berapa omset', 'berapa omzet',
            'berapa pendapatan', 'berapa pemasukan',
        ],
        'roles'      => ['owner_platform', 'admin_owner'],
        'method'     => 'getPlatformGMV',
        'format'     => 'currency',
        'period'     => 'all_time',
    ],

    'total_orders' => [
        'label'      => 'Total Pesanan Platform',
        'definition' => 'Jumlah seluruh pesanan dari semua toko, mencakup semua status pesanan.',
        'synonyms'   => [
            'pesanan', 'order', 'orders', 'transaksi', 'total pesanan',
            'berapa pesanan', 'berapa order', 'jumlah pesanan', 'jumlah order',
        ],
        'roles'      => ['owner_platform', 'admin_owner'],
        'method'     => 'getPlatformOrderCount',
        'format'     => 'integer',
        'period'     => 'all_time',
    ],

    'verified_stores' => [
        'label'      => 'Toko Terverifikasi',
        'definition' => 'Jumlah toko yang sudah terverifikasi dan aktif di platform.',
        'synonyms'   => [
            'toko terverifikasi', 'toko aktif', 'toko verified',
            'berapa toko', 'jumlah toko', 'toko bergabung',
            'berapa toko terverifikasi', 'toko yang aktif',
        ],
        'roles'      => ['owner_platform', 'admin_owner'],
        'method'     => 'getPlatformVerifiedStoreCount',
        'format'     => 'integer',
        'period'     => 'all_time',
    ],

    'total_customers' => [
        'label'      => 'Total Customer Terdaftar',
        'definition' => 'Jumlah pengguna dengan role customer yang terdaftar di platform.',
        'synonyms'   => [
            'customer', 'pelanggan', 'pengguna', 'user',
            'berapa customer', 'berapa pelanggan', 'jumlah customer',
            'jumlah pelanggan', 'berapa pengguna terdaftar',
        ],
        'roles'      => ['owner_platform', 'admin_owner'],
        'method'     => 'getPlatformCustomerCount',
        'format'     => 'integer',
        'period'     => 'all_time',
    ],

];

