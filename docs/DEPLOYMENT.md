# Panduan Deployment & Konfigurasi (SmartLaundry AI)

Dokumen ini berisi panduan teknis untuk melakukan deployment proyek SmartLaundry (RPL2) ke server *production* (VPS atau Shared Hosting) serta panduan transisi dari API tersimulasi ke API asli (Production Hardening - Tahap 15+).

---

## 1. Persyaratan Server (Requirements)
Pastikan environment server/hosting memenuhi standar minimum berikut:
- **Sistem Operasi**: Linux (Ubuntu 20.04+, CentOS, atau serupa)
- **Web Server**: Apache 2.4+ atau Nginx (dengan PHP-FPM)
- **PHP**: Versi 8.0, 8.1, atau 8.2 (direkomendasikan)
- **Ekstensi PHP Aktif**: 
  - `pdo_mysql`
  - `mbstring`
  - `json`
  - `curl` (Wajib untuk API eksternal)
- **Database**: MySQL 5.7+ atau MariaDB 10.4+

---

## 2. Langkah-Langkah Deployment (Shared Hosting / VPS cPanel)

### A. Persiapan Kode
1. **PENTING**: **JANGAN PERNAH** men-commit file `.env` ke repository Git. Pastikan `.gitignore` sudah terkonfigurasi dengan benar.
2. Buat arsip ZIP dari seluruh isi folder aplikasi Anda, kecuali folder `.git/`, file `error.log` lama, dan `.env`.

### B. Upload & Ekstrak
1. Upload file ZIP ke folder `public_html` (atau subdirektori sesuai domain Anda) melalui File Manager / SFTP.
2. Ekstrak file ZIP.
3. Pastikan file `.htaccess` di root directory ikut terekstrak (seringkali *hidden file* tidak ikut tersalin jika tidak hati-hati). `.htaccess` wajib ada agar routing MVC (index.php) berfungsi.

### C. Konfigurasi Database
1. Buat Database MySQL baru beserta User dan Password-nya melalui control panel hosting.
2. Berikan *All Privileges* pada user tersebut untuk mengakses database.
3. Gunakan **phpMyAdmin** untuk melakukan impor data:
   - Karena Anda tidak memiliki akses CLI di sebagian shared hosting, export database lokal Anda menggunakan perintah `mysqldump` atau phpMyAdmin lokal.
   - Import file `.sql` tersebut ke phpMyAdmin di server hosting.
   - *Alternatif*: Jika hosting mendukung akses SSH / Terminal, jalankan migrasi via CLI: `php database/migrate.php`

### D. Konfigurasi Environment (`.env`)
1. Di server hosting, copy file `.env.example` dan ubah namanya menjadi `.env`.
2. Sesuaikan konfigurasi berikut:
```env
APP_ENV=production        # Mengubah ini ke 'production' akan mematikan display_errors
APP_URL=https://domainanda.com  # URL resmi aplikasi
APP_TIMEZONE=Asia/Jakarta

DB_HOST=localhost         # Atau IP Database server
DB_PORT=3306
DB_DATABASE=nama_database_hosting
DB_USERNAME=user_database_hosting
DB_PASSWORD=password_database_hosting
```
3. Simpan perubahan.

### E. Permission Folder
Pastikan direktori log memiliki izin tulis (write access):
- `storage/logs/`: Set CHMOD `755` atau `777` (jika `755` gagal) agar aplikasi dapat menulis `error.log`, `audit.log`, dan `chatbot_access.log`.

### F. Cron Job Retensi Log Chatbot (Tahap 14)
Sistem memiliki kebijakan retensi privasi untuk membersihkan riwayat percakapan chatbot secara otomatis yang lebih lama dari 90 hari (atau sesuai nilai `CHATBOT_LOG_RETENTION_DAYS` di `.env`). Script ini bersifat idempoten dan aman dijalankan berulang.

1. **Jalankan Secara Manual**:
   ```bash
   php cron_purge_chatbot_logs.php
   ```
2. **Setup Otomatis via Crontab (Linux / cPanel)**:
   Jadwalkan script berjalan setiap hari pada pukul 02:00 dini hari:
   ```cron
   0 2 * * * /usr/bin/php /path/to/public_html/cron_purge_chatbot_logs.php >> /dev/null 2>&1
   ```
   Di Windows Task Scheduler / PowerShell:
   ```powershell
   php C:\xampp\htdocs\RPL2\cron_purge_chatbot_logs.php
   ```
3. **Konfigurasi Durasi Retensi (`.env`)**:
   ```env
   # Jumlah hari retensi pesan percakapan chatbot sebelum dihapus otomatis (default: 90)
   CHATBOT_LOG_RETENTION_DAYS=90
   ```

---

## 3. Checklist Hardening & Keamanan (Tahap 14)

Sebelum merilis aplikasi ke publik, sistem telah dilengkapi dengan beberapa pengamanan yang sudah diimplementasikan pada Tahap 14:

1. **Global Exception Handling**: Error database / sintaks tidak akan lagi mencetak *stack trace* PDO mentah ke layar (mencegah kebocoran informasi skema DB). Sebaliknya, pengguna diarahkan ke halaman Error 500 yang ramah, dan detail error dicatat ke `storage/logs/error.log`.
2. **Display Errors Off**: Dengan mengubah `APP_ENV=production` di `.env`, konfigurasi `display_errors` PHP akan otomatis diubah ke `0` melalui `config/env.php`.
3. **Anti-XSS**: Seluruh output data berbasis input pengguna ke View (terutama chat AI, review toko, dan nama kurir) menggunakan utilitas `htmlspecialchars` atau fungsi helper `e()`.
4. **Validasi Input Controller**: Seluruh input dari POST telah diproteksi validasi dan diproses dengan Prepared Statements PDO (mencegah SQL Injection).

---

## 4. Rencana Transisi API Asli (Tahap 15)

Aplikasi saat ini berjalan menggunakan mekanisme *mock/simulasi* untuk fitur pihak ketiga. Berikut adalah langkah-langkah mengganti ke integrasi *Live API*:

### A. Google Maps & Geocoding
**Saat ini**: `App\Services\LocationService` menggunakan simulasi koordinat dan perhitungan Haversine lurus (offline).
**Migrasi**:
1. Buat project di Google Cloud Console.
2. Aktifkan **Maps JavaScript API**, **Geocoding API**, dan **Distance Matrix API**.
3. Dapatkan API Key, dan masukkan ke `.env`: `GOOGLE_MAPS_API_KEY=AIzaSy...`
4. Ubah logika di `LocationService` untuk melakukan curl ke endpoint Google.
5. Pada view frontend (`public/js/app.js`), ganti peta Leaflet (OpenStreetMap) menjadi Google Maps SDK jika diperlukan.

### B. Payment Gateway (QRIS) - SELESAI (Tahap 15)
**Saat ini**: Aplikasi mendukung arsitektur Adapter untuk pembayaran (`PaymentGatewayFactory`). Secara *default*, aplikasi berjalan di mode `simulasi`.
**Cara Aktivasi Midtrans Sandbox / Production**:
1. Daftar akun di [Midtrans Dashboard](https://dashboard.midtrans.com/).
2. Masuk ke menu **Settings > Access Keys** untuk mendapatkan Server Key & Client Key.
3. Update `.env` Anda:
   ```env
   PAYMENT_GATEWAY=midtrans
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxx
   MIDTRANS_CLIENT_KEY=SB-Mid-client-xxx
   MIDTRANS_IS_PRODUCTION=false
   ```
4. **Setup Webhook (PENTING)**: Agar status pembayaran di-*update* otomatis saat customer membayar:
   - Di Dashboard Midtrans, masuk ke **Settings > Configuration**.
   - Isi **Payment Notification URL** dengan: `https://domainanda.com/webhook/midtrans`
   - *Catatan untuk localhost*: Gunakan alat seperti `ngrok` (contoh: `ngrok http 80`) untuk mendapatkan URL publik sementara, lalu isi ke Midtrans (contoh: `https://<ngrok-id>.ngrok-free.app/webhook/midtrans`).
5. Jika `MIDTRANS_SERVER_KEY` dibiarkan kosong, sistem tidak akan *crash* melainkan otomatis *fallback* ke mode simulasi (QRIS dummy).

### C. WhatsApp Gateway (Fonnte API)
SmartLaundry menggunakan adapter pattern dengan integrasi langsung ke Fonnte (`https://api.fonnte.com/send`).

**Langkah Mendapatkan Token Fonnte**:
1. Daftar atau masuk di [fonnte.com](https://fonnte.com).
2. Tambahkan device baru di menu **Device**.
3. Buka WhatsApp di smartphone Anda > **Perangkat tertaut** > Pindai kode QR yang ditampilkan di dashboard Fonnte.
4. Setelah terhubung (*connected*), salin token device dari dashboard Fonnte.
5. Masukkan token ke file `.env` di server/lokal:
   ```env
   WHATSAPP_GATEWAY=fonnte
   FONNTE_TOKEN=
   WHATSAPP_ALLOWED_NUMBERS=6281234567890
   FONNTE_TEST_PHONE=6281234567890
   DAILY_SUMMARY_HOUR=21
   ```

**Peringatan Operasional & Keamanan**:
- Gunakan nomor WhatsApp khusus bisnis / operasional laundry (bukan nomor pribadi).
- Pastikan smartphone yang terhubung tetap memiliki koneksi internet aktif agar pesan Fonnte terkirim secara berkala.
- Hindari pengiriman pesan massal (*broadcast spam*) di luar transaksi resmi untuk mencegah pemblokiran nomor oleh WhatsApp.
- Jika `WHATSAPP_GATEWAY=fonnte` tetapi `FONNTE_TOKEN` belum diisi, pesan akan dicatat sebagai `FAILED` dengan keterangan "FONNTE_TOKEN belum diisi" (tanpa fallback diam-diam).

---

## 4. Konfigurasi Cron Job / Penjadwalan Otomatis

Aplikasi menyediakan dua cron job penting yang harus dijalankan di background:
1. **Retry Antrean Pesan WhatsApp Gagal** (`database/cron_retry_whatsapp.php`):
   - Interval: setiap 5 menit.
   - Fungsi: mengulang pengiriman pesan outbox yang berstatus `FAILED` (maksimal 3 kali percobaan dengan *exponential backoff*).
2. **Ringkasan Finansial Harian WhatsApp** (`database/cron_daily_summary.php`):
   - Interval: setiap hari pukul 21:00 WIB (dapat diubah via `DAILY_SUMMARY_HOUR`).
   - Fungsi: mengirim rekap pemasukan, pengeluaran, laba kotor, dan total berat cucian ke masing-masing owner toko, serta ringkasan platform ke platform admin.

### Cara Menjadwalkan di Windows Task Scheduler:
1. Buka **Task Scheduler** di Windows.
2. Klik **Create Basic Task...**:
   - **Task 1 (Retry WA)**:
     - Name: `SmartLaundry WA Retry`
     - Trigger: Daily, repeat every 5 minutes indefinitely.
     - Action: Start a Program
     - Program/script: `C:\xampp\php\php.exe`
     - Add arguments: `c:\xampp\htdocs\RPL2\database\cron_retry_whatsapp.php`
   - **Task 2 (Daily Summary)**:
     - Name: `SmartLaundry Daily Summary`
     - Trigger: Daily at `21:00`.
     - Action: Start a Program
     - Program/script: `C:\xampp\php\php.exe`
     - Add arguments: `c:\xampp\htdocs\RPL2\database\cron_daily_summary.php`

### Cara Menjadwalkan di Linux Crontab (cPanel / VPS):
```bash
# Jalankan crontab -e lalu tambahkan:
*/5 * * * * /usr/bin/php /home/username/public_html/database/cron_retry_whatsapp.php > /dev/null 2>&1
0 21 * * * /usr/bin/php /home/username/public_html/database/cron_daily_summary.php > /dev/null 2>&1
```

### D. AI Chatbot (OpenAI)
**Saat ini**: `AIChatbotService` memiliki fungsi respon lokal menggunakan deteksi keyword Regex.
**Migrasi (OpenAI GPT-4 / GPT-3.5-Turbo)**:
1. Dapatkan API Key dari platform.openai.com.
2. Tambahkan ke `.env`: `OPENAI_API_KEY=sk-xxxx`
3. Edit `AIChatbotService->generateResponse()`. Susun prompt yang mencakup *system prompt* tentang aturan Laundry, masukkan pertanyaan pengguna, dan lakukan POST ke `https://api.openai.com/v1/chat/completions`.
4. Return isi *message* dari JSON response OpenAI ke frontend.

---
**Status Proyek Saat Ini**: Siap Demo / Siap Pitching (Tahap 14 Selesai).
