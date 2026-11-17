<?php
/**
 * Chatbot Schema Whitelist Configuration
 * 
 * Mendefinisikan entitas, kolom yang diizinkan, tipe, dan relasi join yang valid
 * untuk ChatbotQueryEngine. TIDAK ADA kolom di luar daftar ini yang bisa diakses.
 * 
 * Format kolom: [label, type, searchable]
 *   type: 'text' | 'number' | 'money' | 'date' | 'enum' | 'masked_phone' | 'masked_email'
 *   searchable: true jika bisa dipakai sebagai filter
 * 
 * Kolom TERLARANG secara global (tidak boleh masuk ke whitelist ini):
 *   password, google_id, remember_token, api_key, qris_payload, session_id,
 *   secret, token, access_token, otp_*, code_hash
 */

return [

    // ==========================================================================
    // ENTITIES — Tabel yang boleh diakses chatbot
    // ==========================================================================

    'entities' => [

        'expenses' => [
            'table'       => 'store_expenses',
            'label'       => 'Pengeluaran',
            'store_fk'    => 'store_id',          // kolom foreign key ke stores.id untuk scope toko
            'columns'     => [
                'id'           => ['Nomor',          'number', false],
                'store_id'     => ['ID Toko',         'number', true],
                'recorded_by'  => ['Dicatat Oleh (ID)', 'number', false],
                'expense_date' => ['Tanggal',         'date',   true],
                'category'     => ['Kategori',        'enum',   true],
                'item_name'    => ['Nama Item',       'text',   true],
                'quantity'     => ['Jumlah',          'number', false],
                'unit'         => ['Satuan',          'text',   false],
                'unit_price'   => ['Harga Satuan',    'money',  false],
                'total_amount' => ['Total',           'money',  true],
                'supplier'     => ['Pemasok',         'text',   true],
                'notes'        => ['Catatan',         'text',   false],
                'created_at'   => ['Dibuat',          'date',   false],
            ],
            'allowed_aggregations' => ['COUNT', 'SUM', 'AVG', 'MAX', 'MIN'],
        ],

        'orders' => [
            'table'       => 'orders',
            'label'       => 'Pesanan',
            'store_fk'    => 'store_id',
            'columns'     => [
                'id'             => ['ID',            'number', false],
                'order_code'     => ['Kode Order',    'text',   true],
                'store_id'       => ['ID Toko',       'number', true],
                'customer_id'    => ['ID Customer',   'number', false],
                'courier_id'     => ['ID Kurir',      'number', false],
                'status'         => ['Status',        'enum',   true],
                'payment_method' => ['Metode Bayar',  'enum',   true],
                'total_price'    => ['Total',         'money',  true],
                'delivery_fee'   => ['Ongkir',        'money',  false],
                'weight_actual'  => ['Berat (kg)',    'number', false],
                'created_at'     => ['Tanggal',       'date',   true],
                'completed_at'   => ['Selesai',       'date',   false],
            ],
            'allowed_aggregations' => ['COUNT', 'SUM', 'MAX', 'MIN'],
        ],

        'order_items' => [
            'table'    => 'order_items',
            'label'    => 'Item Pesanan',
            'store_fk' => null,          // tidak ada direct FK ke stores; diakses via orders
            'columns'  => [
                'id'         => ['ID',         'number', false],
                'order_id'   => ['ID Pesanan', 'number', true],
                'service_id' => ['ID Layanan', 'number', false],
                'quantity'   => ['Jumlah',     'number', false],
                'unit_price' => ['Harga',      'money',  false],
                'subtotal'   => ['Subtotal',   'money',  false],
                'notes'      => ['Catatan',    'text',   false],
            ],
            'allowed_aggregations' => ['COUNT', 'SUM'],
        ],

        'laundry_services' => [
            'table'    => 'laundry_services',
            'label'    => 'Layanan Laundry',
            'store_fk' => 'store_id',
            'columns'  => [
                'id'                 => ['ID',          'number', false],
                'store_id'           => ['ID Toko',     'number', true],
                'name'               => ['Nama',        'text',   true],
                'category'           => ['Kategori',    'enum',   true],
                'price'              => ['Harga',       'money',  true],
                'unit'               => ['Satuan',      'text',   false],
                'min_order'          => ['Min Order',   'number', false],
                'estimated_duration' => ['Estimasi',    'text',   false],
                'is_active'          => ['Aktif',       'enum',   true],
            ],
            'allowed_aggregations' => ['COUNT'],
        ],

        'payments' => [
            'table'    => 'payments',
            'label'    => 'Pembayaran',
            'store_fk' => null,   // akses via orders.store_id
            'columns'  => [
                'id'         => ['ID',           'number', false],
                'order_id'   => ['ID Pesanan',   'number', true],
                'amount'     => ['Jumlah',       'money',  true],
                'status'     => ['Status',       'enum',   true],
                'method'     => ['Metode',       'enum',   true],
                'paid_at'    => ['Dibayar',      'date',   true],
                'expired_at' => ['Kadaluarsa',   'date',   false],
                'created_at' => ['Dibuat',       'date',   false],
            ],
            'allowed_aggregations' => ['COUNT', 'SUM', 'MAX'],
        ],

        'stores' => [
            'table'    => 'stores',
            'label'    => 'Toko',
            'store_fk' => 'id',
            'columns'  => [
                'id'          => ['ID',         'number', false],
                'name'        => ['Nama Toko',  'text',   true],
                'city'        => ['Kota',       'text',   true],
                'is_verified' => ['Terverifikasi', 'enum', true],
                'is_active'   => ['Aktif',      'enum',   true],
                'rating_avg'  => ['Rating',     'number', false],
                'rating_count'=> ['Jml Ulasan', 'number', false],
                'created_at'  => ['Dibuat',     'date',   false],
            ],
            'allowed_aggregations' => ['COUNT'],
        ],

        'users' => [
            'table'    => 'users',
            'label'    => 'Pengguna',
            'store_fk' => null,
            'columns'  => [
                'id'         => ['ID',        'number', false],
                'name'       => ['Nama',      'text',   true],
                'email'      => ['Email',     'masked_email', true],
                'phone'      => ['HP',        'masked_phone', false],
                'is_active'  => ['Aktif',     'enum',   true],
                'created_at' => ['Terdaftar', 'date',   false],
            ],
            'allowed_aggregations' => ['COUNT'],
        ],

        'couriers' => [
            'table'    => 'couriers',
            'label'    => 'Kurir',
            'store_fk' => 'store_id',
            'columns'  => [
                'id'           => ['ID',        'number', false],
                'user_id'      => ['ID User',   'number', false],
                'store_id'     => ['ID Toko',   'number', true],
                'vehicle_type' => ['Kendaraan', 'text',   true],
                'status'       => ['Status',    'enum',   true],
                'is_available' => ['Tersedia',  'enum',   true],
            ],
            'allowed_aggregations' => ['COUNT'],
        ],

        'reviews' => [
            'table'    => 'reviews',
            'label'    => 'Ulasan',
            'store_fk' => 'store_id',
            'columns'  => [
                'id'          => ['ID',      'number', false],
                'order_id'    => ['ID Order','number', true],
                'store_id'    => ['ID Toko', 'number', true],
                'customer_id' => ['ID Customer','number', false],
                'rating'      => ['Rating',  'number', true],
                'comment'     => ['Komentar','text',   false],
                'created_at'  => ['Tanggal', 'date',   true],
            ],
            'allowed_aggregations' => ['COUNT', 'AVG'],
        ],

        'order_status_histories' => [
            'table'    => 'order_status_histories',
            'label'    => 'Riwayat Status',
            'store_fk' => null,
            'columns'  => [
                'id'         => ['ID',        'number', false],
                'order_id'   => ['ID Order',  'number', true],
                'status'     => ['Status',    'enum',   true],
                'notes'      => ['Catatan',   'text',   false],
                'created_at' => ['Waktu',     'date',   true],
            ],
            'allowed_aggregations' => ['COUNT'],
        ],

        'addresses' => [
            'table'    => 'addresses',
            'label'    => 'Alamat',
            'store_fk' => null,
            'columns'  => [
                'id'       => ['ID',         'number', false],
                'user_id'  => ['ID User',    'number', false],
                'label'    => ['Label',      'text',   false],
                'city'     => ['Kota',       'text',   true],
                'district' => ['Kecamatan',  'text',   true],
                'is_default'=> ['Default',   'enum',   false],
            ],
            // Note: full address details (street, lat/lng) intentionally omitted for privacy
            'allowed_aggregations' => ['COUNT'],
        ],

        'courier_task_offers' => [
            'table'    => 'courier_task_offers',
            'label'    => 'Penawaran Tugas Kurir',
            'store_fk' => null,
            'columns'  => [
                'id'          => ['ID',        'number', false],
                'order_id'    => ['ID Order',  'number', true],
                'courier_id'  => ['ID Kurir',  'number', true],
                'status'      => ['Status',    'enum',   true],
                'offered_at'  => ['Ditawarkan','date',   true],
                'responded_at'=> ['Direspon',  'date',   false],
            ],
            'allowed_aggregations' => ['COUNT'],
        ],

        'laundry_details' => [
            'table'    => 'laundry_details',
            'label'    => 'Detail Laundry',
            'store_fk' => null,
            'columns'  => [
                'id'             => ['ID',          'number', false],
                'order_id'       => ['ID Order',    'number', true],
                'weight_actual'  => ['Berat Aktual','number', false],
                'weight_estimate'=> ['Estimasi Berat','number',false],
                'pieces_count'   => ['Jumlah Item', 'number', false],
                'notes_from_store'=> ['Catatan Toko','text',  false],
            ],
            'allowed_aggregations' => ['COUNT', 'SUM'],
        ],

    ],

    // ==========================================================================
    // JOINS — Relasi yang diizinkan (entity_alias.column -> entity_alias.column)
    // ==========================================================================

    'joins' => [
        // expenses -> stores
        ['from' => 'expenses',        'from_col' => 'store_id',    'to' => 'stores',   'to_col' => 'id',      'type' => 'LEFT'],
        // expenses -> users (recorded_by)
        ['from' => 'expenses',        'from_col' => 'recorded_by', 'to' => 'users',    'to_col' => 'id',      'type' => 'LEFT', 'alias' => 'recorder'],
        // orders -> stores
        ['from' => 'orders',          'from_col' => 'store_id',    'to' => 'stores',   'to_col' => 'id',      'type' => 'LEFT'],
        // orders -> users (customer)
        ['from' => 'orders',          'from_col' => 'customer_id', 'to' => 'users',    'to_col' => 'id',      'type' => 'LEFT', 'alias' => 'customer'],
        // orders -> couriers
        ['from' => 'orders',          'from_col' => 'courier_id',  'to' => 'couriers', 'to_col' => 'user_id', 'type' => 'LEFT'],
        // orders -> users (courier user)
        ['from' => 'orders',          'from_col' => 'courier_id',  'to' => 'users',    'to_col' => 'id',      'type' => 'LEFT', 'alias' => 'courier_user'],
        // payments -> orders
        ['from' => 'payments',        'from_col' => 'order_id',    'to' => 'orders',   'to_col' => 'id',      'type' => 'INNER'],
        // order_items -> orders
        ['from' => 'order_items',     'from_col' => 'order_id',    'to' => 'orders',   'to_col' => 'id',      'type' => 'INNER'],
        // order_items -> laundry_services
        ['from' => 'order_items',     'from_col' => 'service_id',  'to' => 'laundry_services', 'to_col' => 'id', 'type' => 'LEFT'],
        // reviews -> orders
        ['from' => 'reviews',         'from_col' => 'order_id',    'to' => 'orders',   'to_col' => 'id',      'type' => 'LEFT'],
        // reviews -> stores
        ['from' => 'reviews',         'from_col' => 'store_id',    'to' => 'stores',   'to_col' => 'id',      'type' => 'LEFT'],
        // reviews -> users (customer)
        ['from' => 'reviews',         'from_col' => 'customer_id', 'to' => 'users',    'to_col' => 'id',      'type' => 'LEFT', 'alias' => 'reviewer'],
        // couriers -> stores
        ['from' => 'couriers',        'from_col' => 'store_id',    'to' => 'stores',   'to_col' => 'id',      'type' => 'LEFT'],
        // couriers -> users
        ['from' => 'couriers',        'from_col' => 'user_id',     'to' => 'users',    'to_col' => 'id',      'type' => 'LEFT', 'alias' => 'courier_user'],
        // order_status_histories -> orders
        ['from' => 'order_status_histories', 'from_col' => 'order_id', 'to' => 'orders', 'to_col' => 'id',   'type' => 'INNER'],
        // courier_task_offers -> orders
        ['from' => 'courier_task_offers', 'from_col' => 'order_id', 'to' => 'orders',  'to_col' => 'id',     'type' => 'INNER'],
        // laundry_details -> orders
        ['from' => 'laundry_details', 'from_col' => 'order_id',    'to' => 'orders',   'to_col' => 'id',      'type' => 'INNER'],
    ],

    // ==========================================================================
    // ALLOWED_JOINS — Keyed by "entity->target[:alias]" for ChatbotQueryEngine
    // ==========================================================================

    'allowed_joins' => [
        // expenses
        'expenses->stores'          => ['from_col' => 'store_id',    'to_table' => 'stores',           'to_col' => 'id',      'type' => 'LEFT'],
        'expenses->users'           => ['from_col' => 'recorded_by', 'to_table' => 'users',            'to_col' => 'id',      'type' => 'LEFT'],
        // orders
        'orders->stores'            => ['from_col' => 'store_id',    'to_table' => 'stores',           'to_col' => 'id',      'type' => 'LEFT'],
        'orders->users'             => ['from_col' => 'customer_id', 'to_table' => 'users',            'to_col' => 'id',      'type' => 'LEFT'],
        'orders->couriers'          => ['from_col' => 'courier_id',  'to_table' => 'couriers',         'to_col' => 'user_id', 'type' => 'LEFT'],
        // payments
        'payments->orders'          => ['from_col' => 'order_id',    'to_table' => 'orders',           'to_col' => 'id',      'type' => 'INNER'],
        // order_items
        'order_items->orders'       => ['from_col' => 'order_id',    'to_table' => 'orders',           'to_col' => 'id',      'type' => 'INNER'],
        'order_items->services'     => ['from_col' => 'service_id',  'to_table' => 'laundry_services', 'to_col' => 'id',      'type' => 'LEFT'],
        // reviews
        'reviews->orders'           => ['from_col' => 'order_id',    'to_table' => 'orders',           'to_col' => 'id',      'type' => 'LEFT'],
        'reviews->stores'           => ['from_col' => 'store_id',    'to_table' => 'stores',           'to_col' => 'id',      'type' => 'LEFT'],
        'reviews->users'            => ['from_col' => 'customer_id', 'to_table' => 'users',            'to_col' => 'id',      'type' => 'LEFT'],
        // couriers
        'couriers->stores'          => ['from_col' => 'store_id',    'to_table' => 'stores',           'to_col' => 'id',      'type' => 'LEFT'],
        'couriers->users'           => ['from_col' => 'user_id',     'to_table' => 'users',            'to_col' => 'id',      'type' => 'LEFT'],
        // order_status_histories
        'order_status_histories->orders'  => ['from_col' => 'order_id', 'to_table' => 'orders', 'to_col' => 'id', 'type' => 'INNER'],
        // courier_task_offers
        'courier_task_offers->orders'     => ['from_col' => 'order_id', 'to_table' => 'orders', 'to_col' => 'id', 'type' => 'INNER'],
        // laundry_details
        'laundry_details->orders'         => ['from_col' => 'order_id', 'to_table' => 'orders', 'to_col' => 'id', 'type' => 'INNER'],
    ],

    // ==========================================================================
    // OPERATORS — Operator perbandingan yang diizinkan
    // ==========================================================================

    'operators' => [
        '='  => '=',
        '!=' => '!=',
        '>'  => '>',
        '>=' => '>=',
        '<'  => '<',
        '<=' => '<=',
        'BETWEEN'   => 'BETWEEN',
        'LIKE'      => 'LIKE',
        'IN'        => 'IN',
        'IS NULL'   => 'IS NULL',
        'IS NOT NULL' => 'IS NOT NULL',
    ],

    // ==========================================================================
    // FINANCIAL ENTITIES — Entitas yang dianggap finansial
    // admin_toko tidak boleh akses ini kecuali setting admin_toko_can_view_finance=true
    // ==========================================================================

    'financial_entities' => ['expenses', 'payments'],

    // ==========================================================================
    // ROLES ALLOWED — Role mana yang boleh menggunakan ChatbotQueryEngine
    // customer dan kurir tidak boleh
    // ==========================================================================

    'allowed_roles' => ['owner_platform', 'admin_owner', 'owner_toko', 'admin_toko'],

];

