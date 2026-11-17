<?php
/**
 * SmartLaundry - Notification Templates Configuration
 * 
 * Available Placeholders:
 * - {order_code} / {nomor_pesanan} : Nomor/kode pesanan (e.g. ORD-202610-001)
 * - {store_name}                   : Nama outlet laundry
 * - {nama_customer}                : Nama customer
 * - {courier_name} / {nama_kurir}  : Nama kurir yang ditugaskan
 * - {method} / {metode_pembayaran} : Metode pembayaran (QRIS, Tunai / Cash)
 * - {total} / {total_harga}        : Total tagihan pembayaran (e.g. Rp 45.000)
 * - {berat}                        : Berat cucian hasil penimbangan (e.g. 3.5)
 * - {batas_pembayaran}             : Batas waktu pelunasan pembayaran
 * - {qr_code_pembayaran}           : String/link QR Code QRIS Dinamis
 * - {item}                         : Nama barang / item pengeluaran
 * - {category}                     : Kategori pengeluaran
 * - {date}                         : Tanggal transaksi
 * - {actor_name}                   : Nama pelaku perubahan aktivitas
 * - {actor_role}                   : Peran/role pelaku perubahan aktivitas
 * - {activity_type}                : Jenis aktivitas sistem
 * - {change_detail} / {detail}     : Rincian spesifik perubahan
 * - {timestamp}                    : Waktu aktivitas dilakukan
 * - {link}                         : Tautan URL tindakan (otomatis dikosongkan jika APP_URL masih localhost)
 */

return [
    // --------------------------------------------------------------------------
    // KATEGORI A: KODE OTP MASUK WA
    // --------------------------------------------------------------------------
    'otp_whatsapp' => "SmartLaundry: Halo {nama_customer}, kode verifikasi akun Anda adalah *{otp_code}* (berlaku {expiry_minutes} menit). JANGAN bagikan kode ini kepada siapa pun demi keamanan akun Anda.",
    'otp_login' => "SmartLaundry: Halo {nama_customer}, kode OTP untuk login ke akun Anda adalah *{otp_code}* (berlaku {expiry_minutes} menit). Rahasiakan kode ini.",
    'otp_verify_phone' => "SmartLaundry: Halo {nama_customer}, kode verifikasi nomor WhatsApp Anda adalah *{otp_code}* (berlaku {expiry_minutes} menit).",
    'otp_reset_password' => "SmartLaundry: Halo {nama_customer}, kode OTP untuk reset password Anda adalah *{otp_code}* (berlaku {expiry_minutes} menit). Jangan berikan kode ini ke siapa pun.",
    'otp_verify_account' => "SmartLaundry: Halo {nama_customer}, kode aktivasi akun SmartLaundry Anda adalah *{otp_code}* (berlaku {expiry_minutes} menit).",

    // --------------------------------------------------------------------------
    // KATEGORI B: TRANSAKSI MASUK WA (CUSTOMER)
    // --------------------------------------------------------------------------
    // 1. Pesanan dibuat: pesanan berhasil diterima
    'customer_order_created' => "Halo, {nama_customer}! 👋\n\nPesanan laundry Anda berhasil dibuat dan telah diterima oleh SmartLaundry. Outlet {store_name} sedang mengonfirmasi pesanan Anda. 📋\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Pesanan Dibuat\n\nMohon menunggu konfirmasi toko dan penugasan kurir jemput.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // 2. Kurir menjemput: kurir sedang menuju lokasi customer
    'customer_courier_to_pickup' => "Halo, {nama_customer}! 👋\n\nKabar baik! Kurir {nama_kurir} sedang menuju lokasi Anda untuk menjemput pakaian cucian. 🛵\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Kurir Menjemput\n\nMohon siapkan pakaian cucian Anda. Kurir kami akan segera tiba di lokasi untuk menjemput.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // 3. Laundry diambil kurir
    'customer_laundry_picked' => "Halo, {nama_customer}! 👋\n\nCucian Anda untuk pesanan {nomor_pesanan} sudah dijemput oleh kurir {nama_kurir} dan sedang dibawa ke {store_name} untuk ditimbang.{link}",

    // 4. Laundry diterima toko: laundry berhasil diterima dan sedang ditimbang
    'customer_arrived_at_store' => "Halo, {nama_customer}! 👋\n\nCucian Anda telah tiba dan berhasil diterima di outlet {store_name}. Saat ini sedang dalam antrean penimbangan. ⚖️\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Laundry Diterima Toko\n\nRincian tagihan pembayaran akan dikirimkan otomatis setelah proses penimbangan selesai.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // 5. Laundry selesai ditimbang: informasi total biaya
    'customer_weighing_completed' => "Halo, {nama_customer}! 👋\n\nPenimbangan pesanan laundry Anda di {store_name} telah selesai. Total tagihan siap dibayarkan. 💳\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Menunggu Pembayaran\n\nBatas waktu pembayaran: {batas_pembayaran}. Silakan selesaikan pembayaran agar cucian segera kami proses.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // Notifikasi Dua Tahap: Tahap 1 - Laundry Selesai Ditimbang / Siap Dibayar (QRIS Dinamis)
    'customer_ready_to_pay_qris' => "Halo, {nama_customer}! 👋\n\nKabar baik! Laundry Anda sudah selesai diproses. 🎉\n\nBerikut detail pesanan Anda:\n\nNomor Pesanan: {nomor_pesanan}\nBerat Laundry: {berat} KG\nTotal Pembayaran: {total_harga}\nStatus: Menunggu Pembayaran\n\nSilakan lakukan pembayaran menggunakan QRIS berikut.\n\nQRIS: {qr_code_pembayaran}\n\nBatas pembayaran: {waktu_kedaluwarsa}\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // Notifikasi Dua Tahap: Tahap 1 - Laundry Selesai Ditimbang / Siap Dibayar (Tunai / CASH)
    'customer_ready_to_pay_cash' => "Halo, {nama_customer}! 👋\n\nKabar baik! Laundry Anda sudah selesai diproses. 🎉\n\nBerikut detail pesanan Anda:\n\nNomor Pesanan: {nomor_pesanan}\nBerat Laundry: {berat} KG\nTotal Pembayaran: {total_harga}\nMetode Pembayaran: Tunai (CASH)\nStatus: Menunggu Pembayaran\n\nSilakan siapkan uang pas saat penyerahan laundry oleh kurir atau saat pengambilan di toko.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // 6. Pembayaran berhasil diterima (Tahap 2 Notifikasi)
    'customer_payment_success' => "Halo, {nama_customer}! 👋\n\nPembayaran laundry Anda telah BERHASIL DITERIMA. 🎉\n\nDetail pembayaran:\n\nNomor Pesanan: {nomor_pesanan}\nTotal Pembayaran: {total_harga}\nMetode Pembayaran: {metode_pembayaran}\nStatus Pembayaran: LUNAS\n\nTerima kasih telah mempercayakan laundry Anda kepada SmartLaundry. Kami senang dapat melayani Anda! ❤️\n\n{status_pengantaran}{link}",

    // 7. Pembayaran kedaluwarsa
    'customer_payment_expired' => "Halo, {nama_customer}! 👋\n\nBatas waktu pembayaran pesanan {nomor_pesanan} telah kadaluarsa. Pakaian Anda akan diproses untuk pengembalian ke lokasi Anda.{link}",

    // 8. Laundry sedang dicuci / diproses
    'customer_laundry_washing' => "Halo, {nama_customer}! 👋\n\nPembayaran terverifikasi! Laundry Anda sedang diproses dan dicuci bersih secara higienis oleh tim {store_name}. 🧼\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Sedang Dicuci\n\nKami memastikan pakaian Anda dicuci bersih dengan standar kebersihan terbaik.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",
    'customer_laundry_processing' => "Halo, {nama_customer}! 👋\n\nLaundry pesanan {nomor_pesanan} sedang dalam proses pengeringan, setrika uap, dan packing wangi oleh tim {store_name}. 👔✨\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Sedang Disetrika & Diproses\n\nPakaian Anda akan segera siap untuk diantar.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // 9. Laundry selesai dicuci & siap diantar
    'customer_laundry_ready' => "Halo, {nama_customer}! 👋\n\nKabar baik! Laundry Anda sudah selesai diproses, bersih, dan wangi banget. 🎉\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Laundry Selesai\n\nSilakan menunggu proses pengantaran oleh kurir.\n\nTerima kasih telah mempercayai SmartLaundry. 😊{link}",

    // 10. Kurir menuju lokasi antar
    'customer_courier_to_delivery' => "Halo, {nama_customer}! 👋\n\nKurir {nama_kurir} sedang dalam perjalanan mengantarkan cucian bersih & wangi Anda ke alamat tujuan. 🛵\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Sedang Diantar\n\nMohon pastikan ada penerima di lokasi tujuan untuk serah terima cucian.\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // 11. Pesanan selesai / diterima customer
    'customer_order_completed' => "Halo, {nama_customer}! 👋\n\nPesanan laundry Anda telah selesai dan berhasil diterima dengan baik. ✨\n\nDetail pesanan:\n- Nomor pesanan: {nomor_pesanan}\n- Layanan: {nama_layanan}\n- Berat: {berat} KG\n- Total pembayaran: {total_harga}\n- Status: Pesanan Selesai\n\nTerima kasih banyak telah mempercayai layanan {store_name}. Jangan lupa berikan penilaian & ulasan bintang 5 Anda! ⭐\n\nTerima kasih telah menggunakan SmartLaundry. 😊{link}",

    // Additional customer events
    'customer_order_confirmed' => "Halo, {nama_customer}! 👋\n\nKabar baik! Pesanan {nomor_pesanan} sudah dikonfirmasi {store_name}. Kurir kami akan segera menjemput cucian Anda.{link}",
    'customer_courier_assigned' => "Halo, {nama_customer}! 👋\n\nKurir {nama_kurir} telah ditugaskan untuk menjemput cucian pesanan {nomor_pesanan} di {store_name}. Mohon siapkan pakaian cucian Anda ya.{link}",
    'customer_payment_expiring_soon' => "Halo, {nama_customer}! 👋\n\nPengingat: Batas waktu pembayaran pesanan {nomor_pesanan} di {store_name} sebesar {total_harga} akan berakhir pada {batas_pembayaran}. Yuk segera selesaikan pembayaran agar cucian langsung diproses.{link}",
    'customer_laundry_delivered' => "Halo, {nama_customer}! 👋\n\nCucian pesanan {nomor_pesanan} sudah diantar oleh kurir {nama_kurir}. Mohon cek cucian Anda dan konfirmasi penerimaan di aplikasi ya.{link}",
    'customer_refund_processing' => "Halo, {nama_customer}! 👋\n\nPengembalian cucian pesanan {nomor_pesanan} sedang diproses oleh {store_name}. Kurir akan segera mengantar kembali ke lokasi Anda.{link}",

    // --------------------------------------------------------------------------
    // KATEGORI B: TRANSAKSI MASUK WA (STORE OWNER & ADMIN TOKO)
    // --------------------------------------------------------------------------
    'store_new_order' => "SmartLaundry: Pesanan baru {order_code} masuk di outlet {store_name}. Silakan buka dashboard untuk konfirmasi.{link}",
    'store_courier_picked' => "SmartLaundry: Kurir {courier_name} telah menjemput cucian untuk pesanan {order_code} ({store_name}). Cucian sedang menuju toko.{link}",
    'store_arrived' => "SmartLaundry: Cucian pesanan {order_code} telah tiba di {store_name}. Silakan lakukan penimbangan dan input berat.{link}",
    'store_weighed' => "SmartLaundry: Pesanan {order_code} telah ditimbang ({weight} kg). Menunggu pembayaran pelanggan sebesar {total}.{link}",
    'store_payment_received_owner' => "SmartLaundry: Pembayaran pesanan {order_code} sebesar {total} via {method} telah diterima untuk outlet {store_name}. Omzet bertambah!{link}",
    'store_payment_received_admin' => "SmartLaundry: Pembayaran pesanan {order_code} via {method} telah diterima untuk outlet {store_name}. Cucian siap diproses.{link}",
    'store_payment_expired' => "SmartLaundry: Pembayaran pesanan {order_code} sebesar {total} di {store_name} telah kadaluarsa dan membutuhkan persetujuan pengembalian cucian.{link}",
    'store_order_completed' => "SmartLaundry: Pesanan {order_code} telah berhasil diselesaikan dan diterima pelanggan.{link}",
    'store_expense_recorded_owner' => "SmartLaundry: Pengeluaran baru dicatat di {store_name} — {item} ({category}) sebesar {total} pada {date}.{link}",
    'store_expense_recorded_admin' => "SmartLaundry: Pengeluaran baru dicatat di {store_name} — {item} ({category}) pada {date}. Silakan cek detail di dashboard.{link}",
    'store_daily_summary' => "SmartLaundry: Ringkasan Keuangan {store_name} ({date}): Pemasukan {income}, Pengeluaran {expense}, Laba Kotor {profit}. Total cucian {weight} kg dari {orders} pesanan ({customers} pelanggan).{link}",
    'store_no_courier_available' => "SmartLaundry: Tidak ada kurir tersedia untuk pesanan {order_code} di {store_name}. Silakan lakukan penugasan manual di dashboard.{link}",
    'store_courier_rejected' => "SmartLaundry: Kurir {courier_name} menolak tugas untuk pesanan {order_code} di {store_name}. Sistem sedang mencari kurir lain.{link}",
    'store_new_review' => "SmartLaundry: Ulasan baru telah diterima untuk outlet {store_name} pada pesanan {order_code}.{link}",

    // --------------------------------------------------------------------------
    // KATEGORI B: TRANSAKSI MASUK WA (KURIR & PLATFORM ADMIN)
    // --------------------------------------------------------------------------
    'courier_task_offered' => "SmartLaundry: Halo {courier_name}, Anda menerima tawaran tugas baru untuk pesanan {order_code} di {store_name}. Buka dashboard kurir untuk merespon.{link}",
    'courier_return_task' => "SmartLaundry: Halo {courier_name}, Anda ditugaskan untuk tugas pengembalian cucian pesanan {order_code} di {store_name}.{link}",
    'courier_task_cancelled' => "SmartLaundry: Halo {courier_name}, tugas untuk pesanan {order_code} telah dibatalkan oleh sistem.{link}",
    'platform_new_store_registered' => "SmartLaundry: Toko baru \"{store_name}\" telah mendaftar dan menunggu verifikasi dari admin platform.{link}",
    'platform_daily_summary' => "SmartLaundry: Ringkasan Harian Platform ({date}): Pemasukan {income}, Pengeluaran {expense}, Total {transactions} transaksi. Toko teratas: {top_stores}.{link}",
    'platform_transaction_alert' => "SmartLaundry Alert: Transaksi bermasalah pada pesanan {order_code} ({store_name}) - {detail}. Waktu: {timestamp}.{link}",

    // --------------------------------------------------------------------------
    // KATEGORI C: AKTIVITAS MASUK WA
    // --------------------------------------------------------------------------
    'activity_service_added' => "SmartLaundry Aktivitas: Layanan baru \"{nama_layanan}\" ({harga}/{unit}) telah ditambahkan oleh {actor_name} ({actor_role}) pada toko {store_name} ({timestamp}).{link}",
    'activity_price_changed' => "SmartLaundry Aktivitas: Perubahan harga layanan \"{nama_layanan}\" ({harga_lama} -> {harga_baru}/{unit}) oleh {actor_name} ({actor_role}) pada toko {store_name} ({timestamp}).{link}",
    'activity_store_updated' => "SmartLaundry Aktivitas: Pembaruan data outlet \"{store_name}\" oleh {actor_name} ({actor_role}) pada {timestamp}. Detail: {change_detail}.{link}",
    'activity_courier_changed' => "SmartLaundry Aktivitas: Perubahan kurir \"{courier_name}\" ({change_detail}) oleh {actor_name} ({actor_role}) pada toko {store_name} ({timestamp}).{link}",
    'activity_user_role_changed' => "SmartLaundry Aktivitas: Perubahan hak akses/role akun {user_name} ({role_lama} -> {role_baru}) oleh {actor_name} ({actor_role}) pada {timestamp}.{link}",
    'activity_setting_updated' => "SmartLaundry Aktivitas: Pengaturan sistem \"{setting_name}\" diubah oleh {actor_name} ({actor_role}) pada {timestamp}. Detail: {change_detail}.{link}",
];
