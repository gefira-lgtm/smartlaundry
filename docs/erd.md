# Entity Relationship Diagram (ERD) - SmartLaundry AI Marketplace

Dokumen ini mendefinisikan desain skema database relasional (25 Tabel) untuk sistem **Marketplace Laundry Berbasis Web dengan Integrasi AI**.

---

## 1. Visualisasi ERD (Mermaid Diagram)

```mermaid
erDiagram
    roles ||--o{ users : "has many"
    users ||--o{ addresses : "has many"
    users ||--o{ stores : "owns"
    users ||--o{ store_admins : "assigned to"
    users ||--o| couriers : "profile"
    users ||--o{ orders : "places (customer)"
    users ||--o{ orders : "delivers (courier)"
    users ||--o{ notifications : "receives"
    users ||--o{ order_status_histories : "logs changes"
    users ||--o{ laundry_details : "weighed by"
    users ||--o{ chatbot_messages : "sends/receives chat"
    users ||--o{ chatbot_data_access_logs : "audits data search"
    users ||--o{ settings : "updates setting"
    users ||--o{ setting_audit_logs : "recorded change"
    users ||--o{ store_settings : "updates store config"
    users ||--o{ otp_codes : "verifies OTP"

    stores ||--o{ store_admins : "has staff"
    stores ||--o{ laundry_services : "offers"
    stores ||--o{ couriers : "employs"
    stores ||--o{ orders : "receives orders"
    users ||--o{ store_expenses : "records"
    stores ||--o{ store_expenses : "incurs expenses"
    stores ||--o{ store_settings : "has store config"
    stores }o--|| addresses : "located at"

    addresses ||--o{ orders : "pickup address"
    addresses ||--o{ orders : "delivery address"

    orders ||--o{ order_items : "contains"
    orders ||--o| laundry_details : "measured weights"
    orders ||--o{ payments : "has payments"
    orders ||--o{ courier_locations : "tracking point"
    orders ||--o{ notifications : "triggers"
    orders ||--o{ order_status_histories : "audit trail"

    laundry_services ||--o{ order_items : "itemized in"
    couriers ||--o{ courier_locations : "logs GPS"

    roles {
        int id PK
        string name UK
        string display_name
        string description
        timestamp created_at
        timestamp updated_at
    }

    users {
        int id PK
        int role_id FK
        string name
        string email UK
        string password
        string phone
        string google_id UK
        string avatar
        tinyint is_active
        timestamp email_verified_at
        timestamp phone_verified_at
        tinyint wa_notifications_enabled
        timestamp created_at
        timestamp updated_at
    }

    addresses {
        int id PK
        int user_id FK
        string label
        string recipient_name
        string phone
        text full_address
        string rt
        string rw
        string kelurahan
        string kecamatan
        string kota
        string provinsi
        string postal_code
        decimal latitude
        decimal longitude
        string notes
        tinyint is_primary
        timestamp created_at
        timestamp updated_at
    }

    stores {
        int id PK
        int owner_id FK
        string name
        string slug UK
        text description
        int address_id FK
        string phone
        string logo
        string banner
        string operational_hours
        decimal rating_avg
        int rating_count
        tinyint is_verified
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }

    store_admins {
        int id PK
        int store_id FK
        int user_id FK
        enum role_in_store
        timestamp created_at
        timestamp updated_at
    }

    laundry_services {
        int id PK
        int store_id FK
        string name
        enum category
        decimal price
        string unit
        decimal min_order
        string estimated_duration
        text description
        string icon
        tinyint is_active
        timestamp created_at
        timestamp updated_at
    }

    couriers {
        int id PK
        int user_id FK
        int store_id FK
        string vehicle_type
        string license_plate
        tinyint is_available
        enum status
        decimal current_lat
        decimal current_lng
        timestamp last_location_updated_at
        timestamp created_at
        timestamp updated_at
    }

    orders {
        int id PK
        string order_code UK
        int customer_id FK
        int store_id FK
        int courier_id FK
        int pickup_address_id FK
        int delivery_address_id FK
        enum status
        enum payment_method
        decimal total_price
        decimal delivery_fee
        text notes
        datetime scheduled_pickup_at
        datetime picked_up_at
        datetime completed_at
        timestamp created_at
        timestamp updated_at
    }

    order_items {
        int id PK
        int order_id FK
        int service_id FK
        decimal quantity
        decimal unit_price
        decimal subtotal
        string item_notes
        timestamp created_at
        timestamp updated_at
    }

    laundry_details {
        int id PK
        int order_id FK
        decimal weight_actual
        int pieces_count
        text notes_from_store
        string before_photo
        string after_photo
        int weighed_by FK
        timestamp created_at
        timestamp updated_at
    }

    payments {
        int id PK
        int order_id FK
        string payment_code UK
        enum method
        decimal amount
        enum status
        text qris_payload
        string qris_image_url
        string transaction_ref
        datetime expired_at
        datetime reminder_sent_at
        datetime paid_at
        timestamp created_at
        timestamp updated_at
    }

    courier_locations {
        bigint id PK
        int courier_id FK
        int order_id FK
        decimal latitude
        decimal longitude
        timestamp recorded_at
    }

    notifications {
        int id PK
        int user_id FK
        int order_id FK
        string title
        text message
        enum type
        string action_url
        tinyint is_read
        timestamp created_at
        timestamp updated_at
    }

    order_status_histories {
        int id PK
        int order_id FK
        string status
        int changed_by FK
        text notes
        timestamp created_at
    }

    chatbot_messages {
        bigint id PK
        int user_id FK
        string user_role
        string session_id
        enum sender
        text message
        string detected_intent
        tinyint is_fallback
        enum mode
        timestamp created_at
    }

    chatbot_knowledge {
        int id PK
        string category
        string title
        text keywords
        text content
        string roles_allowed
        tinyint is_active
        timestamp updated_at
    }

    chatbot_data_access_logs {
        bigint id PK
        int user_id FK
        string role
        int store_id
        string tool
        string params_summary
        smallint rows_returned
        timestamp created_at
    }

    store_expenses {
        int id PK
        int store_id FK
        int recorded_by FK
        string title
        string category
        string item_name
        decimal quantity
        string unit
        decimal unit_price
        decimal amount
        decimal total_amount
        string supplier
        datetime expense_date
        text notes
        timestamp created_at
        timestamp updated_at
    }

    whatsapp_logs {
        int id PK
        string phone
        string recipient_name
        string event
        text message
        enum status
        string dedupe_key UK
        int attempts
        text last_error
        datetime next_retry_at
        timestamp sent_at
    }

    otp_codes {
        bigint id PK
        int user_id FK
        enum channel
        string destination
        enum purpose
        string code_hash
        datetime expires_at
        tinyint attempts
        tinyint max_attempts
        tinyint resend_count
        datetime last_sent_at
        datetime verified_at
        timestamp created_at
        string ip_address
    }
```

---

## 2. Penjelasan Relasi dan Kardinalitas

| Hubungan Relasi | Kardinalitas | Tipe Constraint (FK) | Penjelasan |
| :--- | :--- | :--- | :--- |
| `users` &rarr; `otp_codes` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Kode verifikasi OTP WhatsApp & Email untuk registrasi customer baru dan perubahan nomor WhatsApp. |
| :--- | :--- | :--- | :--- |
| `roles` &rarr; `users` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE RESTRICT` | Satu role dapat dimiliki oleh banyak user. Role tidak boleh dihapus jika masih ada user aktif. |
| `users` &rarr; `addresses` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Satu customer dapat menyimpan banyak alamat penjemputan (Rumah, Kantor, Kos). |
| `users` &rarr; `stores` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE RESTRICT` | User dengan role `owner_toko` memiliki satu atau lebih cabang toko laundry. |
| `stores` &rarr; `store_admins` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Tabel pivot relasi multi-admin/kasir yang ditugaskan di toko tertentu. |
| `stores` &rarr; `laundry_services` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Setiap toko mendefinisikan menu layanan dan tarif masing-masing. |
| `users` &rarr; `couriers` | 1 to 1 (1:1) | `ON UPDATE CASCADE ON DELETE CASCADE` | Data profil dan kendaraan spesifik untuk user ber-role kurir. |
| `users` (Customer) &rarr; `orders` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE RESTRICT` | Customer dapat membuat banyak pesanan laundry. |
| `stores` &rarr; `orders` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE RESTRICT` | Toko menerima banyak pesanan masuk. |
| `stores` &rarr; `store_expenses` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Catatan pengeluaran operasional toko (bahan baku, listrik, plastik packing, dll). |
| `orders` &rarr; `order_items` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Rincian paket layanan dalam satu order. |
| `orders` &rarr; `laundry_details` | 1 to 1 (1:1) | `ON UPDATE CASCADE ON DELETE CASCADE` | Catatan penimbangan berat aktual (kg), helai pakaian, dan foto sebelum/sesudah. |
| `orders` &rarr; `payments` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Rekam pembayaran invoice (QRIS / Cash), timer batas 1 jam, dan status lunas. |
| `couriers` &rarr; `courier_locations`| 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Rekam titik koordinat GPS real-time kurir selama penjemputan/pengantaran. |
| `orders` &rarr; `order_status_histories` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Audit log lengkap histori transisi status pesanan beserta pengguna yang mengubah. |
| `users` &rarr; `notifications` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Notifikasi in-app untuk pembaruan pesanan, kurir, atau pembayaran. |
| `users` &rarr; `chatbot_messages` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Log percakapan AI Chatbot per-user. Konten disamarkan (PII-masked) sebelum disimpan. Retensi otomatis 90 hari via cron. |
| `users` &rarr; `chatbot_data_access_logs` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Audit log setiap pemanggilan tool akses data database yang dilakukan chatbot per user. |
| `chatbot_knowledge` | Standalone | - | Basis pengetahuan sistem terindeks FULLTEXT untuk referensi chatbot lintas role. |
| `users` &rarr; `settings` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE SET NULL` | Konfigurasi sistem dinamis (Umum, Pembayaran, Refund, Kurir, Notifikasi, Chatbot, Keamanan, Mode Pemeliharaan). |
| `users` &rarr; `setting_audit_logs` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE SET NULL` | Audit trail riwayat seluruh modifikasi konfigurasi (nilai lama, baru, waktu, IP, user). |
| `stores` &rarr; `store_settings` | 1 to Many (1:N) | `ON UPDATE CASCADE ON DELETE CASCADE` | Pengaturan spesifik outlet toko (jam buka, status buka/tutup, WA toko, retur fee bearer). |

---

## 3. Daftar Nilai Terkontrol (ENUM Reference)

### 1. `orders.status` (18 Status Workflow Terintegrasi)
1. `MENUNGGU_KONFIRMASI` — Pesanan baru dibuat oleh customer.
2. `MENUNGGU_KURIR` — Pesanan dikonfirmasi toko, menunggu kurir ditugaskan.
3. `KURIR_MENUJU_LOKASI` — Kurir dalam perjalanan ke alamat penjemputan.
4. `LAUNDRY_DIJEMPUT` — Pakaian kotor telah diserahkan customer ke kurir.
5. `DALAM_PERJALANAN_KE_TOKO` — Kurir membawa pakaian menuju outlet toko.
6. `SAMPAI_DI_TOKO` — Cucian tiba di toko laundry.
7. `SEDANG_DITIMBANG` — Operator menimbang berat ril & menghitung tagihan.
8. `MENUNGGU_PEMBAYARAN` — Invoice diterbitkan (QRIS countdown 1 jam berjalan).
9. `PEMBAYARAN_BERHASIL` — Pembayaran QRIS/DP telah terverifikasi lunas.
10. `SEDANG_DICUCI` — Pakaian masuk tahap pencucian mesin.
11. `SEDANG_DIPROSES` — Pakaian dalam tahap pengeringan, setrika uap, & packing wangi.
12. `SIAP_DIANTAR` — Pakaian bersih siap dijemput kurir untuk pengantaran.
13. `KURIR_MENUJU_CUSTOMER` — Kurir dalam perjalanan mengantar cucian bersih ke rumah.
14. `LAUNDRY_DIANTAR` — Kurir telah mengantarkan cucian ke customer dan menunggu konfirmasi penerimaan.
15. `SELESAI` — Laundry diterima oleh customer (Selesai).
16. `PEMBAYARAN_KADALUARSA` — Batas waktu pembayaran QRIS 1 jam habis tanpa pelunasan.
17. `PROSES_PENGEMBALIAN` — Laundry tidak dibayar dan dijadwalkan kembali ke customer.
18. `DIBATALKAN` — Pesanan dibatalkan sesuai kebijakan sistem.

### 2. `payments.status`
- `PENDING` — Menunggu pembayaran (QRIS aktif).
- `PAID` — Lunas terverifikasi.
- `EXPIRED` — Melewati batas waktu 1 jam.
- `FAILED` — Transaksi gagal.
- `REFUNDED` — Dana dikembalikan.
- `CANCELLED` — Dibatalkan.

### 3. `couriers.status`
- `TERSEDIA` — Kurir standby siap menerima tugas penjemputan/pengantaran baru.
- `BERTUGAS` — Kurir sedang aktif di jalan menyelesaikan tugas.
- `OFFLINE` — Kurir tidak bertugas / istirahat.
