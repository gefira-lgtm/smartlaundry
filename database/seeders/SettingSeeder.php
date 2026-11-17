<?php
namespace Database\Seeders;

use PDO;

class SettingSeeder {
    /**
     * Default settings dictionary definition
     */
    public static function getDefaultSettings(): array {
        return [
            // 1. Umum (general)
            [
                'setting_key' => 'app_name',
                'setting_value' => 'SmartLaundry',
                'value_type' => 'STRING',
                'setting_group' => 'general',
                'label' => 'Nama Aplikasi',
                'description' => 'Nama merek aplikasi marketplace laundry pada judul dan navbar',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'app_tagline',
                'setting_value' => 'Platform Marketplace Laundry Modern & Cepat',
                'value_type' => 'STRING',
                'setting_group' => 'general',
                'label' => 'Tagline Aplikasi',
                'description' => 'Slogan promosi utama sistem pada halaman muka dan footer',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'contact_email',
                'setting_value' => 'support@smartlaundry.id',
                'value_type' => 'STRING',
                'setting_group' => 'general',
                'label' => 'Email Kontak Resmi',
                'description' => 'Email customer service resmi platform',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'contact_phone',
                'setting_value' => '081234567890',
                'value_type' => 'STRING',
                'setting_group' => 'general',
                'label' => 'Nomor Telepon / WhatsApp Kontak',
                'description' => 'Nomor WhatsApp resmi pusat bantuan pelanggan',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'contact_address',
                'setting_value' => 'Jl. Prof. Sudarto No. 1, Tembalang, Semarang',
                'value_type' => 'STRING',
                'setting_group' => 'general',
                'label' => 'Alamat Kantor Operasional',
                'description' => 'Alamat fisik kantor pengelola platform',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'footer_text',
                'setting_value' => 'SmartLaundry Marketplace. All rights reserved.',
                'value_type' => 'STRING',
                'setting_group' => 'general',
                'label' => 'Teks Footer Copyright',
                'description' => 'Teks hak cipta di bagian bawah setiap halaman aplikasi',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'app_logo',
                'setting_value' => '',
                'value_type' => 'STRING',
                'setting_group' => 'general',
                'label' => 'Logo Aplikasi',
                'description' => 'Path file logo resmi aplikasi (JPG, PNG, atau WEBP maks 2MB)',
                'is_editable_by' => 'admin_owner'
            ],

            // 2. Pembayaran (payment)
            [
                'setting_key' => 'qris_expiration_minutes',
                'setting_value' => '60',
                'value_type' => 'INT',
                'setting_group' => 'payment',
                'label' => 'Durasi Kedaluwarsa QRIS (Menit)',
                'description' => 'Masa berlaku timer pembayaran QRIS setelah penimbangan selesai (10 - 1440 menit)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'payment_cash_enabled',
                'setting_value' => '1',
                'value_type' => 'BOOL',
                'setting_group' => 'payment',
                'label' => 'Opsi Bayar Tunai (Cash)',
                'description' => 'Aktifkan atau nonaktifkan pilihan pembayaran tunai saat checkout pesanan',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'payment_reminder_minutes',
                'setting_value' => '15',
                'value_type' => 'INT',
                'setting_group' => 'payment',
                'label' => 'Menit Pengingat Sebelum Kedaluwarsa',
                'description' => 'Kirim peringatan notifikasi ke customer sekian menit sebelum batas waktu bayar habis',
                'is_editable_by' => 'owner_platform'
            ],

            // 3. Pengembalian (refund)
            [
                'setting_key' => 'return_fee_default',
                'setting_value' => '0',
                'value_type' => 'INT',
                'setting_group' => 'refund',
                'label' => 'Biaya Pengembalian Default (Rp)',
                'description' => 'Biaya default ongkos kirim pengembalian pakaian untuk pesanan kadaluarsa',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'return_fee_bearer_default',
                'setting_value' => 'CUSTOMER',
                'value_type' => 'STRING',
                'setting_group' => 'refund',
                'label' => 'Penanggung Biaya Pengembalian Default',
                'description' => 'Pihak yang bertanggung jawab atas ongkos kirim pengembalian (CUSTOMER / STORE / PLATFORM)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'return_require_store_approval',
                'setting_value' => '1',
                'value_type' => 'BOOL',
                'setting_group' => 'refund',
                'label' => 'Wajib Persetujuan Admin Toko',
                'description' => 'Proses pengembalian cucian wajib disetujui pihak outlet sebelum penugasan kurir',
                'is_editable_by' => 'owner_platform'
            ],

            // 4. Kurir dan Pesanan (courier_order)
            [
                'setting_key' => 'courier_offer_timeout_minutes',
                'setting_value' => '15',
                'value_type' => 'INT',
                'setting_group' => 'courier_order',
                'label' => 'Batas Waktu Respons Tawaran Kurir (Menit)',
                'description' => 'Waktu maksimal kurir merespons tawaran penugasan sebelum dialihkan (1 - 120 menit)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'order_autocomplete_hours',
                'setting_value' => '24',
                'value_type' => 'INT',
                'setting_group' => 'courier_order',
                'label' => 'Batas Auto-Complete Pesanan Diantar (Jam)',
                'description' => 'Pesanan berstatus diantar otomatis diselesaikan sistem setelah sekian jam (1 - 168 jam)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'tracking_poll_interval_seconds',
                'setting_value' => '10',
                'value_type' => 'INT',
                'setting_group' => 'courier_order',
                'label' => 'Interval Polling Tracking GPS (Detik)',
                'description' => 'Interval refresh live tracking posisi kurir pada peta peta digital (3 - 30 detik)',
                'is_editable_by' => 'owner_platform'
            ],

            // 5. Notifikasi (notification)
            [
                'setting_key' => 'in_app_notification_enabled',
                'setting_value' => '1',
                'value_type' => 'BOOL',
                'setting_group' => 'notification',
                'label' => 'Notifikasi In-App Aktif',
                'description' => 'Aktifkan pusat notifikasi lonceng di navbar aplikasi untuk seluruh pengguna',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'whatsapp_notification_enabled',
                'setting_value' => '1',
                'value_type' => 'BOOL',
                'setting_group' => 'notification',
                'label' => 'Notifikasi WhatsApp Aktif (Global)',
                'description' => 'Saklar global pengiriman pesan WhatsApp otomatis ke pelanggan dan mitra',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'wa_template_order_created',
                'setting_value' => 'Halo {customer_name}! Pesanan laundry Anda #{order_code} di {store_name} telah berhasil dibuat. Kami sedang menunggu konfirmasi dari pihak outlet.',
                'value_type' => 'STRING',
                'setting_group' => 'notification',
                'label' => 'Template WA: Pesanan Dibuat',
                'description' => 'Pesan otomatis saat order baru checkout ({order_code}, {store_name}, {customer_name})',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'wa_template_order_confirmed',
                'setting_value' => 'Kabar baik {customer_name}! Pesanan #{order_code} telah dikonfirmasi oleh {store_name}. Kurir kami akan segera meluncur ke lokasi Anda untuk menjemput pakaian.',
                'value_type' => 'STRING',
                'setting_group' => 'notification',
                'label' => 'Template WA: Pesanan Dikonfirmasi Toko',
                'description' => 'Pesan saat outlet menerima pesanan ({order_code}, {store_name}, {customer_name})',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'wa_template_courier_dispatched',
                'setting_value' => 'Halo {customer_name}! Kurir {courier_name} ({courier_phone}) sedang dalam perjalanan {action} untuk order #{order_code}. Anda dapat melacak posisi kurir secara langsung di web!',
                'value_type' => 'STRING',
                'setting_group' => 'notification',
                'label' => 'Template WA: Kurir Menuju Lokasi',
                'description' => 'Pesan saat kurir bertugas ({customer_name}, {courier_name}, {courier_phone}, {order_code}, {action})',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'wa_template_payment_success',
                'setting_value' => 'Pembayaran Berhasil! Tagihan untuk order #{order_code} sebesar {total} telah terverifikasi LUNAS. Pakaian Anda kini mulai masuk proses pencucian higienis.',
                'value_type' => 'STRING',
                'setting_group' => 'notification',
                'label' => 'Template WA: Pembayaran Berhasil',
                'description' => 'Pesan konfirmasi pembayaran lunas ({order_code}, {total})',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'wa_template_laundry_ready',
                'setting_value' => 'Pakaian Anda Selesai Dicuci! Cucian Anda untuk order #{order_code} di {store_name} sudah selesai dicuci, wangi, dan rapi. Siap untuk diantar kurir ke lokasi Anda!',
                'value_type' => 'STRING',
                'setting_group' => 'notification',
                'label' => 'Template WA: Cucian Selesai',
                'description' => 'Pesan saat cucian siap diantar ({order_code}, {store_name})',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'wa_daily_summary_enabled',
                'setting_value' => '0',
                'value_type' => 'BOOL',
                'setting_group' => 'notification',
                'label' => 'Ringkasan Harian WhatsApp Platform',
                'description' => 'Kirim ringkasan otomatis transaksi & toko harian ke owner platform via WhatsApp',
                'is_editable_by' => 'owner_platform'
            ],

            // 6. Chatbot (chatbot)
            [
                'setting_key' => 'chatbot_enabled',
                'setting_value' => '1',
                'value_type' => 'BOOL',
                'setting_group' => 'chatbot',
                'label' => 'Chatbot Global Aktif',
                'description' => 'Saklar utama asisten AI SmartBot di seluruh aplikasi web',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_roles_enabled',
                'setting_value' => json_encode(['customer', 'kurir', 'owner_platform', 'admin_owner', 'owner_toko', 'admin_toko']),
                'value_type' => 'JSON',
                'setting_group' => 'chatbot',
                'label' => 'Role Pengguna yang Diizinkan Chatbot',
                'description' => 'Daftar peran yang dapat melihat dan berinteraksi dengan widget asisten SmartBot',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_bot_name',
                'setting_value' => 'SmartBot',
                'value_type' => 'STRING',
                'setting_group' => 'chatbot',
                'label' => 'Nama Tampilan Bot',
                'description' => 'Nama panggilan resmi asisten virtual di UI widget dan pesan',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_greeting_customer',
                'setting_value' => "Halo! Saya SmartBot, asisten resmi SmartLaundry.\n\nAda yang bisa saya bantu hari ini?\n• Ketik *\"Siapa saya\"* untuk melihat info akun Anda.\n• Ketik *\"Status pesanan saya\"* untuk melacak cucian Anda.\n• Ketik *\"Daftar harga layanan\"* untuk info tarif.\n• Ketik *\"Cara bayar QRIS\"* untuk panduan pembayaran.\n• Atau tanyakan hal lain seputar layanan SmartLaundry.",
                'value_type' => 'STRING',
                'setting_group' => 'chatbot',
                'label' => 'Teks Sapaan Pembuka: Customer',
                'description' => 'Pesan pembuka saat widget dibuka pertama kali oleh pelanggan',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_greeting_kurir',
                'setting_value' => "Halo! Saya SmartBot, asisten resmi SmartLaundry.\n\nAda yang bisa saya bantu hari ini?\n• Ketik *\"Siapa saya\"* untuk melihat info akun dan status kurir Anda.\n• Ketik *\"Tugas saya sekarang\"* untuk mengecek tugas aktif.\n• Ketik *\"Gimana cara terima tugas\"* untuk panduan penerimaan tugas.\n• Ketik *\"Alur status tugas\"* untuk bantuan alur status.",
                'value_type' => 'STRING',
                'setting_group' => 'chatbot',
                'label' => 'Teks Sapaan Pembuka: Kurir',
                'description' => 'Pesan pembuka saat widget dibuka pertama kali oleh kurir',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_greeting_admin',
                'setting_value' => "Halo! Saya SmartBot, asisten resmi SmartLaundry.\n\nAda yang bisa saya bantu hari ini?\n• Ketik *\"Siapa saya\"* untuk melihat info akun dan peran Anda.\n• Ketik *\"Status pesanan\"* untuk memantau pesanan.\n• Silakan tanyakan hal lain seputar pengelolaan SmartLaundry.",
                'value_type' => 'STRING',
                'setting_group' => 'chatbot',
                'label' => 'Teks Sapaan Pembuka: Admin & Owner',
                'description' => 'Pesan pembuka saat widget dibuka oleh admin platform atau pemilik toko',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_fallback_message',
                'setting_value' => 'Maaf, saya belum memahami pertanyaan Anda. Silakan tanyakan hal lain seputar layanan, status pesanan, pembayaran, atau hubungi CS kami.',
                'value_type' => 'STRING',
                'setting_group' => 'chatbot',
                'label' => 'Pesan Fallback Chatbot',
                'description' => 'Respon saat pertanyaan tidak dikenali oleh pola kecerdasan sistem',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_rate_limit_per_minute',
                'setting_value' => '20',
                'value_type' => 'INT',
                'setting_group' => 'chatbot',
                'label' => 'Batas Pesan per Menit',
                'description' => 'Maksimal jumlah interaksi chat per user dalam jendela 60 detik (default 20)',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'chatbot_log_retention_days',
                'setting_value' => '90',
                'value_type' => 'INT',
                'setting_group' => 'chatbot',
                'label' => 'Retensi Log Chatbot (Hari)',
                'description' => 'Jumlah hari pesan riwayat disimpan sebelum dibersihkan otomatis oleh cron (1 - 365 hari)',
                'is_editable_by' => 'admin_owner'
            ],
            [
                'setting_key' => 'admin_toko_can_view_finance',
                'setting_value' => '0',
                'value_type' => 'BOOL',
                'setting_group' => 'chatbot',
                'label' => 'Admin Toko Akses Finansial via Bot',
                'description' => 'Izinkan peran admin_toko menanyakan laporan omset atau keuangan toko ke chatbot',
                'is_editable_by' => 'admin_owner'
            ],

            // 7. Keamanan (security - owner_platform only)
            [
                'setting_key' => 'login_max_attempts',
                'setting_value' => '5',
                'value_type' => 'INT',
                'setting_group' => 'security',
                'label' => 'Batas Percobaan Login Gagal',
                'description' => 'Batas percobaan salah kata sandi sebelum diblokir sementara (default 5 kali)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'login_lockout_duration_minutes',
                'setting_value' => '15',
                'value_type' => 'INT',
                'setting_group' => 'security',
                'label' => 'Durasi Blokir Login (Menit)',
                'description' => 'Lama waktu pembatasan login setelah percobaan salah terlampaui (default 15 menit)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'session_lifetime_minutes',
                'setting_value' => '120',
                'value_type' => 'INT',
                'setting_group' => 'security',
                'label' => 'Lama Sesi Pengguna (Menit)',
                'description' => 'Masa aktif sesi login tanpa interaksi sebelum otomatis logout (15 - 1440 menit)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'otp_required_channels',
                'setting_value' => 'BOTH',
                'value_type' => 'STRING',
                'setting_group' => 'security',
                'label' => 'Kanal Wajib OTP Pendaftaran',
                'description' => 'Kanal verifikasi pendaftaran akun baru: BOTH (WhatsApp & Email wajib) atau EITHER (Salah satu cukup)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'otp_expiry_minutes',
                'setting_value' => '5',
                'value_type' => 'INT',
                'setting_group' => 'security',
                'label' => 'Masa Berlaku Kode OTP (Menit)',
                'description' => 'Masa kedaluwarsa kode OTP sejak dikirimkan (1 - 30 menit)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'otp_resend_interval_seconds',
                'setting_value' => '60',
                'value_type' => 'INT',
                'setting_group' => 'security',
                'label' => 'Jeda Kirim Ulang OTP (Detik)',
                'description' => 'Waktu tunggu jeda sebelum tombol kirim ulang OTP aktif (30 - 300 detik)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'otp_max_attempts',
                'setting_value' => '5',
                'value_type' => 'INT',
                'setting_group' => 'security',
                'label' => 'Batas Percobaan OTP Salah',
                'description' => 'Maksimal percobaan verifikasi kode salah sebelum kode terkunci (1 - 10 kali)',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'otp_hourly_limit',
                'setting_value' => '5',
                'value_type' => 'INT',
                'setting_group' => 'security',
                'label' => 'Batas Pengiriman OTP per Jam',
                'description' => 'Maksimal permintaan kode OTP per jam untuk setiap nomor/email/IP (1 - 20 kali)',
                'is_editable_by' => 'owner_platform'
            ],

            // 8. Mode Pemeliharaan (maintenance - owner_platform only)
            [
                'setting_key' => 'maintenance_mode',
                'setting_value' => '0',
                'value_type' => 'BOOL',
                'setting_group' => 'maintenance',
                'label' => 'Aktifkan Mode Pemeliharaan (503)',
                'description' => 'Saat aktif, seluruh pengunjung & customer melihat halaman 503; owner tetap dapat masuk via login',
                'is_editable_by' => 'owner_platform'
            ],
            [
                'setting_key' => 'maintenance_message',
                'setting_value' => 'Sistem SmartLaundry sedang dalam proses pemeliharaan berkala untuk peningkatan kualitas layanan. Kami akan segera kembali! Halaman login admin tetap dapat diakses.',
                'value_type' => 'STRING',
                'setting_group' => 'maintenance',
                'label' => 'Pesan Halaman Pemeliharaan',
                'description' => 'Pesan ramah yang ditampilkan pada layar 503 pengunjung saat mode pemeliharaan aktif',
                'is_editable_by' => 'owner_platform'
            ]
        ];
    }

    /**
     * Run the idempotent seeder
     */
    public static function run(PDO $pdo): int {
        $settings = self::getDefaultSettings();
        $count = 0;

        $checkStmt = $pdo->prepare("SELECT id FROM `settings` WHERE `setting_key` = :key LIMIT 1");
        $insertStmt = $pdo->prepare("INSERT INTO `settings` 
            (`setting_key`, `setting_value`, `value_type`, `setting_group`, `label`, `description`, `is_editable_by`, `created_at`, `updated_at`)
            VALUES (:setting_key, :setting_value, :value_type, :setting_group, :label, :description, :is_editable_by, NOW(), NOW())");

        foreach ($settings as $s) {
            $checkStmt->execute(['key' => $s['setting_key']]);
            if (!$checkStmt->fetch()) {
                $insertStmt->execute([
                    'setting_key' => $s['setting_key'],
                    'setting_value' => $s['setting_value'],
                    'value_type' => $s['value_type'],
                    'setting_group' => $s['setting_group'],
                    'label' => $s['label'],
                    'description' => $s['description'],
                    'is_editable_by' => $s['is_editable_by']
                ]);
                $count++;
            }
        }

        return $count;
    }
}
