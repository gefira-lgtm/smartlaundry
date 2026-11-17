<?php
namespace App\Models;

use App\Core\Model;

/**
 * ChatbotKnowledge
 *
 * Model untuk tabel `chatbot_knowledge` — basis pengetahuan sistem SmartLaundry.
 * Dipakai oleh ChatbotDataService untuk menjawab pertanyaan seputar sistem.
 */
class ChatbotKnowledge extends Model {
    protected string $table      = 'chatbot_knowledge';
    protected string $primaryKey = 'id';

    // ─────────────────────────────────────────────────────────────────────────
    // Pencarian keyword
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cari entri pengetahuan berdasarkan keyword dan role.
     * Gunakan FULLTEXT jika tersedia, fallback ke LIKE jika tidak.
     *
     * @param string $keyword  Kata kunci dari pengguna (sudah di-sanitize oleh caller)
     * @param string $role     Role pengguna saat ini
     * @param int    $limit    Maks hasil dikembalikan
     */
    public function search(string $keyword, string $role = 'all', int $limit = 3): array {
        // Sanitasi keyword — hanya karakter alfanumerik dan spasi
        $cleanKeyword = preg_replace('/[^\w\s]/u', ' ', $keyword);
        $cleanKeyword = trim(preg_replace('/\s+/', ' ', $cleanKeyword));

        if (empty($cleanKeyword)) return [];

        try {
            // Coba FULLTEXT search dulu
            $sql = "SELECT id, category, title, content, roles_allowed
                    FROM `chatbot_knowledge`
                    WHERE is_active = 1
                      AND MATCH(title, keywords, content) AGAINST (:kw IN BOOLEAN MODE)
                      AND (roles_allowed = 'all' OR FIND_IN_SET(:role, roles_allowed) > 0 OR :role2 IN ('owner_platform','admin_owner'))
                    ORDER BY MATCH(title, keywords, content) AGAINST (:kw2 IN BOOLEAN MODE) DESC
                    LIMIT :lim";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':kw',   '+' . implode(' +', explode(' ', $cleanKeyword)), \PDO::PARAM_STR);
            $stmt->bindValue(':kw2',  '+' . implode(' +', explode(' ', $cleanKeyword)), \PDO::PARAM_STR);
            $stmt->bindValue(':role',  $role, \PDO::PARAM_STR);
            $stmt->bindValue(':role2', $role, \PDO::PARAM_STR);
            $stmt->bindValue(':lim',   $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            // Kalau FULLTEXT tidak dapat hasil, pakai LIKE
            if (empty($rows)) {
                throw new \Exception('No FULLTEXT results, fallback to LIKE');
            }
            return $rows;

        } catch (\Throwable $th) {
            // LIKE fallback
            $like = '%' . $cleanKeyword . '%';
            $sql2 = "SELECT id, category, title, content, roles_allowed
                     FROM `chatbot_knowledge`
                     WHERE is_active = 1
                       AND (title LIKE :like1 OR keywords LIKE :like2 OR content LIKE :like3)
                       AND (roles_allowed = 'all' OR FIND_IN_SET(:role, roles_allowed) > 0 OR :role2 IN ('owner_platform','admin_owner'))
                     ORDER BY
                       CASE WHEN title LIKE :like4 THEN 1
                            WHEN keywords LIKE :like5 THEN 2
                            ELSE 3 END
                     LIMIT :lim";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->bindValue(':like1', $like, \PDO::PARAM_STR);
            $stmt2->bindValue(':like2', $like, \PDO::PARAM_STR);
            $stmt2->bindValue(':like3', $like, \PDO::PARAM_STR);
            $stmt2->bindValue(':like4', $like, \PDO::PARAM_STR);
            $stmt2->bindValue(':like5', $like, \PDO::PARAM_STR);
            $stmt2->bindValue(':role',  $role, \PDO::PARAM_STR);
            $stmt2->bindValue(':role2', $role, \PDO::PARAM_STR);
            $stmt2->bindValue(':lim',   $limit, \PDO::PARAM_INT);
            $stmt2->execute();
            return $stmt2->fetchAll();
        }
    }

    /**
     * Apakah entri boleh dilihat oleh role ini?
     */
    public function isAllowed(array $entry, string $role): bool {
        $allowed = $entry['roles_allowed'] ?? 'all';
        if ($allowed === 'all') return true;
        $parts = array_map('trim', explode(',', $allowed));
        return in_array($role, $parts, true)
            || in_array('all', $parts, true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Seeding otomatis (dipanggil sekali saat migrasi)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Seed 20+ entri pengetahuan sistem.
     * Idempotent: skip jika sudah ada data.
     */
    public function seedKnowledge(): int {
        $count = (int)$this->db->query("SELECT COUNT(*) FROM `chatbot_knowledge`")->fetchColumn();
        if ($count > 0) return 0; // sudah di-seed

        $entries = $this->getKnowledgeEntries();
        $inserted = 0;
        foreach ($entries as $entry) {
            try {
                $stmt = $this->db->prepare(
                    "INSERT INTO `chatbot_knowledge` (category, title, keywords, content, roles_allowed, is_active, updated_at)
                     VALUES (:category, :title, :keywords, :content, :roles_allowed, 1, NOW())"
                );
                $stmt->execute($entry);
                $inserted++;
            } catch (\Throwable $th) {
                // Skip jika duplikat
            }
        }
        return $inserted;
    }

    /**
     * Daftar lengkap entri pengetahuan sistem.
     * Sumber: README.md, docs/erd.md, PRD sistem.
     */
    private function getKnowledgeEntries(): array {
        return [
            // ── Alur Pemesanan ────────────────────────────────────────────────
            [
                'category'      => 'order_flow',
                'title'         => 'Alur Pemesanan Laundry End-to-End',
                'keywords'      => 'alur pemesanan, cara order, langkah pesan, proses laundry, checkout, jemput, antar',
                'content'       => "**Alur Pemesanan SmartLaundry (End-to-End):**\n\n1. **Customer Memilih Toko** — jelajahi mitra laundry terdekat, lihat layanan & harga.\n2. **Checkout** — pilih layanan, alamat penjemputan, dan metode bayar (QRIS/Cash).\n3. **MENUNGGU_KONFIRMASI** — toko menerima dan mengonfirmasi pesanan.\n4. **Penugasan Kurir Jemput** — toko menugaskan kurir yang tersedia.\n5. **KURIR_MENUJU_LOKASI** → **LAUNDRY_DIJEMPUT** — kurir berangkat dan menjemput.\n6. **DALAM_PERJALANAN_KE_TOKO** → **SAMPAI_DI_TOKO** — kurir tiba di toko.\n7. **SEDANG_DITIMBANG** — admin toko menimbang dan menghitung tagihan.\n8. **MENUNGGU_PEMBAYARAN** — QRIS diterbitkan, timer 1 jam aktif.\n9. **PEMBAYARAN_DIKONFIRMASI** → **SEDANG_DICUCI** → **SELESAI_DICUCI** — proses pencucian.\n10. **SIAP_DIANTAR** → **KURIR_MENGANTAR** → **SELESAI** — pengantaran balik ke customer.\n\nJika QRIS tidak dibayar dalam 1 jam → PEMBAYARAN_KADALUARSA → pengembalian laundry.",
                'roles_allowed' => 'all',
            ],

            // ── Status Order ──────────────────────────────────────────────────
            [
                'category'      => 'order_status',
                'title'         => 'Daftar 17 Status Order dan Artinya',
                'keywords'      => 'status order, arti status, MENUNGGU_KONFIRMASI, KURIR_MENUJU_LOKASI, LAUNDRY_DIJEMPUT, DALAM_PERJALANAN_KE_TOKO, SAMPAI_DI_TOKO, SEDANG_DITIMBANG, MENUNGGU_PEMBAYARAN, PEMBAYARAN_DIKONFIRMASI, SEDANG_DICUCI, SELESAI_DICUCI, SIAP_DIANTAR, KURIR_MENGANTAR, SELESAI, DIBATALKAN, PEMBAYARAN_KADALUARSA, PROSES_PENGEMBALIAN',
                'content'       => "**17 Status Order SmartLaundry:**\n\n| Status | Arti |\n|--------|------|\n| MENUNGGU_KONFIRMASI | Pesanan masuk, menunggu toko konfirmasi |\n| KURIR_MENUJU_LOKASI | Kurir dalam perjalanan ke alamat customer |\n| LAUNDRY_DIJEMPUT | Kurir sudah ambil laundry dari customer |\n| DALAM_PERJALANAN_KE_TOKO | Kurir membawa laundry menuju toko |\n| SAMPAI_DI_TOKO | Laundry sudah diserahkan ke toko |\n| SEDANG_DITIMBANG | Admin menimbang dan menghitung tagihan |\n| MENUNGGU_PEMBAYARAN | QRIS diterbitkan, timer 1 jam aktif |\n| PEMBAYARAN_DIKONFIRMASI | Pembayaran berhasil dikonfirmasi |\n| SEDANG_DICUCI | Laundry dalam proses pencucian |\n| SELESAI_DICUCI | Selesai dicuci, menunggu kurir antar |\n| SIAP_DIANTAR | Kurir sudah ditugaskan untuk antar |\n| KURIR_MENGANTAR | Kurir dalam perjalanan ke customer |\n| SELESAI | Laundry diterima customer, pesanan selesai |\n| DIBATALKAN | Pesanan dibatalkan (sebelum proses cuci) |\n| PEMBAYARAN_KADALUARSA | QRIS tidak dibayar dalam 60 menit |\n| PROSES_PENGEMBALIAN | Laundry sedang dikembalikan ke customer |",
                'roles_allowed' => 'all',
            ],

            // ── Pembayaran ────────────────────────────────────────────────────
            [
                'category'      => 'payment',
                'title'         => 'Aturan Pembayaran QRIS, Cash, dan Kadaluarsa',
                'keywords'      => 'pembayaran, QRIS, cash, tunai, kadaluarsa, timer, 1 jam, 60 menit, pengembalian, biaya, metode bayar',
                'content'       => "**Aturan Pembayaran SmartLaundry:**\n\n**QRIS Dinamis:**\n- Diterbitkan otomatis setelah penimbangan selesai.\n- Berlaku **60 menit** (1 jam) sejak diterbitkan.\n- Jika lewat batas waktu → status `PEMBAYARAN_KADALUARSA` → laundry dikembalikan gratis.\n- Pelanggan TIDAK kena biaya cuci jika pesanan kadaluarsa.\n\n**Bayar Tunai (Cash):**\n- Dipilih saat checkout.\n- Pembayaran diserahkan langsung ke kurir saat penjemputan atau pengantaran.\n- Admin toko konfirmasi pembayaran manual di dashboard.\n\n**Regenerasi QRIS:**\n- Customer dapat meminta regenerasi QRIS baru dari halaman pembayaran sebelum kadaluarsa.\n\n**Biaya Layanan:**\n- Dihitung berdasarkan berat (kg) × harga layanan + ongkir (delivery fee).\n- Harga final ditentukan setelah penimbangan, bukan saat checkout.",
                'roles_allowed' => 'all',
            ],

            // ── Aturan Kurir ──────────────────────────────────────────────────
            [
                'category'      => 'courier',
                'title'         => 'Aturan Kurir: Terima/Tolak Tugas, Anti-Bentrok, Status',
                'keywords'      => 'kurir, terima tugas, tolak tugas, tawaran tugas, anti bentrok, status kurir, TERSEDIA, BERTUGAS, OFFLINE, task offer',
                'content'       => "**Aturan Kurir SmartLaundry:**\n\n**Penerimaan Tugas:**\n- Kurir menerima notifikasi tawaran tugas saat ada pesanan baru.\n- Kurir dapat Terima atau Tolak tawaran masing-masing.\n- Jika ditolak, sistem menawarkan ke kurir lain yang tersedia.\n- Kurir TIDAK bisa menerima tugas baru saat status `BERTUGAS` (anti-bentrok).\n\n**Status Ketersediaan:**\n- `TERSEDIA` — siap menerima tugas baru.\n- `BERTUGAS` — sedang mengerjakan tugas aktif.\n- `OFFLINE` — tidak menerima tugas.\n\n**Alur Status Tugas (Penjemputan):**\nTERSEDIA → KURIR_MENUJU_LOKASI → LAUNDRY_DIJEMPUT → DALAM_PERJALANAN_KE_TOKO → SAMPAI_DI_TOKO\n\n**Alur Status Tugas (Pengantaran):**\nSIAP_DIANTAR → KURIR_MENGANTAR → SELESAI\n\n**GPS & Tracking:**\n- Kurir wajib mengizinkan akses lokasi saat bertugas.\n- Posisi kurir diperbarui real-time agar customer dapat melacak.",
                'roles_allowed' => 'all',
            ],

            // ── Hak Akses Role ────────────────────────────────────────────────
            [
                'category'      => 'roles',
                'title'         => 'Hak Akses Setiap Role di SmartLaundry',
                'keywords'      => 'hak akses, role, owner platform, admin owner, owner toko, admin toko, kurir, customer, izin, permission, RBAC',
                'content'       => "**Hak Akses per Role SmartLaundry:**\n\n| Role | Akses Utama |\n|------|-------------|\n| **owner_platform** | Akses penuh: semua user, toko, transaksi, GMV, pengaturan platform |\n| **admin_owner** | Monitoring platform, verifikasi toko, manajemen komplain |\n| **owner_toko** | Profil toko, layanan & harga, armada kurir, laporan omset TOKO SENDIRI |\n| **admin_toko** | Proses pesanan, timbang, konfirmasi bayar, update status — TOKO SENDIRI, tanpa data finansial |\n| **kurir** | Dashboard tugas, GPS tracking, terima/tolak tawaran |\n| **customer** | Pesan laundry, lacak kurir, bayar QRIS/Cash, ulasan |\n\n**Prinsip isolasi toko:** owner_toko dan admin_toko HANYA bisa mengakses data toko yang menjadi tanggung jawab mereka. Data toko lain ditolak.",
                'roles_allowed' => 'all',
            ],

            // ── Verifikasi Toko ───────────────────────────────────────────────
            [
                'category'      => 'store',
                'title'         => 'Panduan Verifikasi Toko Mitra',
                'keywords'      => 'verifikasi toko, toko terverifikasi, toko belum diverifikasi, toggle verify, aktifasi toko, mitra laundry',
                'content'       => "**Panduan Verifikasi Toko Mitra SmartLaundry:**\n\n- Hanya `owner_platform` dan `admin_owner` yang dapat memverifikasi toko.\n- Toko yang belum terverifikasi (`is_verified = 0`) **tidak dapat menerima pesanan** dari customer.\n- Cara verifikasi: buka dashboard platform → Daftar Toko → klik tombol **Verifikasi Toko** pada toko yang ingin diaktifkan.\n- Status verifikasi dapat diubah (on/off) kapan saja oleh admin platform.\n- Toko yang dinonaktifkan tidak muncul di halaman pencarian customer.",
                'roles_allowed' => 'owner_platform,admin_owner',
            ],

            // ── Kelola Layanan ────────────────────────────────────────────────
            [
                'category'      => 'store',
                'title'         => 'Panduan Mengelola Layanan dan Harga Laundry',
                'keywords'      => 'kelola layanan, harga layanan, tambah layanan, edit layanan, kiloan, satuan, express, kategori layanan',
                'content'       => "**Panduan Layanan & Harga (Owner Toko):**\n\n- Buka menu **Layanan** di dashboard toko.\n- Kategori layanan: `kiloan`, `satuan`, `express`, `special`.\n- Setiap layanan memiliki: nama, kategori, harga/unit, min. order, estimasi durasi.\n- Layanan dapat diaktifkan/dinonaktifkan tanpa menghapus data histori.\n- Harga TIDAK bisa diubah retroaktif pada pesanan yang sudah dibuat.",
                'roles_allowed' => 'owner_toko,owner_platform,admin_owner',
            ],

            // ── Penimbangan ───────────────────────────────────────────────────
            [
                'category'      => 'store',
                'title'         => 'Panduan Penimbangan dan Penerbitan Tagihan',
                'keywords'      => 'timbang, penimbangan, submit weighing, berat laundry, hitung tagihan, QRIS terbit, admin toko',
                'content'       => "**Panduan Penimbangan (Admin Toko / Owner Toko):**\n\n1. Pesanan harus berstatus `SAMPAI_DI_TOKO` sebelum bisa ditimbang.\n2. Buka pesanan → klik **Timbang & Hitung**.\n3. Masukkan berat (kg) dan pilih layanan yang sesuai.\n4. Sistem menghitung total: berat × harga layanan + delivery fee.\n5. Klik **Konfirmasi Penimbangan** → QRIS otomatis diterbitkan.\n6. Status berubah → `MENUNGGU_PEMBAYARAN`.\n7. Customer memiliki 1 jam untuk menyelesaikan pembayaran QRIS.",
                'roles_allowed' => 'owner_toko,admin_toko,owner_platform,admin_owner',
            ],

            // ── Konfirmasi Bayar ──────────────────────────────────────────────
            [
                'category'      => 'payment',
                'title'         => 'Panduan Konfirmasi Pembayaran oleh Toko',
                'keywords'      => 'konfirmasi pembayaran, konfirmasi bayar, cash payment, tunai, admin toko, kasir',
                'content'       => "**Konfirmasi Pembayaran (Admin Toko / Owner Toko):**\n\n**Pembayaran QRIS:**\n- Dikonfirmasi otomatis oleh sistem/webhook saat customer scan & bayar.\n- Admin bisa mengkonfirmasi manual jika webhook gagal.\n\n**Pembayaran Cash/Tunai:**\n- Admin atau kurir menerima uang tunai dari customer.\n- Admin toko mengklik **Konfirmasi Pembayaran Cash** di dashboard.\n- Setelah dikonfirmasi → status berubah ke `PEMBAYARAN_DIKONFIRMASI` → proses cuci dimulai.",
                'roles_allowed' => 'owner_toko,admin_toko,owner_platform,admin_owner',
            ],

            // ── Penugasan Kurir Manual ────────────────────────────────────────
            [
                'category'      => 'courier',
                'title'         => 'Panduan Penugasan Kurir Manual oleh Toko',
                'keywords'      => 'penugasan kurir, assign kurir manual, pilih kurir, toko tugaskan kurir',
                'content'       => "**Penugasan Kurir Manual (Owner Toko / Admin Toko):**\n\n- Saat pesanan dikonfirmasi, sistem otomatis menawarkan tugas ke kurir yang `TERSEDIA`.\n- Jika tidak ada kurir tersedia, toko dapat menugaskan kurir secara manual.\n- Buka pesanan → klik **Tugaskan Kurir** → pilih kurir dari daftar kurir toko yang berstatus `TERSEDIA`.\n- Kurir yang dipilih akan mendapat notifikasi tawaran tugas.\n- Pastikan kurir yang dipilih tidak sedang mengerjakan tugas lain (status BERTUGAS).",
                'roles_allowed' => 'owner_toko,admin_toko,owner_platform,admin_owner',
            ],

            // ── Notifikasi ────────────────────────────────────────────────────
            [
                'category'      => 'notification',
                'title'         => 'Panduan Notifikasi In-App SmartLaundry',
                'keywords'      => 'notifikasi, in-app notification, pesan notif, bell, tanda baca, alert, pemberitahuan',
                'content'       => "**Notifikasi In-App SmartLaundry:**\n\n- Notifikasi muncul di ikon lonceng (🔔) di header dashboard.\n- Setiap perubahan status pesanan memicu notifikasi ke pihak terkait.\n- Contoh notifikasi customer: kurir berangkat, laundry selesai, tagihan diterbitkan.\n- Contoh notifikasi toko: pesanan baru masuk, pembayaran dikonfirmasi.\n- Contoh notifikasi kurir: ada tawaran tugas baru.\n- Klik notifikasi untuk melihat detail atau navigasi ke pesanan terkait.\n- Notifikasi dapat ditandai sudah dibaca (satu per satu atau semua sekaligus).",
                'roles_allowed' => 'all',
            ],

            // ── FAQ: Kadaluarsa & Pengembalian ────────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'FAQ: Pembayaran Kadaluarsa dan Pengembalian Laundry',
                'keywords'      => 'kadaluarsa, expired, pengembalian, refund, laundry dikembalikan, baju kembali, tidak bayar',
                'content'       => "**FAQ: Kadaluarsa & Pengembalian:**\n\n**Q: Apa yang terjadi jika QRIS tidak dibayar?**\nA: Setelah 60 menit, pesanan otomatis berstatus `PEMBAYARAN_KADALUARSA`. Customer tidak kena biaya cuci.\n\n**Q: Siapa yang menginisiasi pengembalian?**\nA: Toko mengonfirmasi proses pengembalian di menu Refund, lalu menugaskan kurir.\n\n**Q: Apakah ada biaya pengembalian?**\nA: Tidak ada. Laundry dikembalikan ke customer tanpa biaya.\n\n**Q: Berapa lama proses pengembalian?**\nA: Bergantung pada ketersediaan kurir, biasanya 1–3 jam setelah toko konfirmasi.\n\n**Q: Dapatkah customer memesan ulang?**\nA: Ya, setelah pesanan selesai atau dibatalkan, customer dapat melakukan reorder dari halaman riwayat pesanan.",
                'roles_allowed' => 'all',
            ],

            // ── FAQ: GMV & Laporan Omset ──────────────────────────────────────
            [
                'category'      => 'reporting',
                'title'         => 'Laporan Omset, GMV, dan Pendapatan Platform',
                'keywords'      => 'omset, GMV, gross merchandise value, laporan, revenue, pendapatan, finansial, total transaksi',
                'content'       => "**Laporan Finansial SmartLaundry:**\n\n- **GMV (Gross Merchandise Value)**: Total nilai seluruh transaksi yang berhasil di platform (status SELESAI).\n- **Laporan per Toko (owner_toko)**: Omset toko sendiri saja — total pesanan selesai × harga.\n- **Laporan Platform (owner_platform)**: GMV seluruh toko, breakdown per toko, trend harian/bulanan.\n- **admin_toko TIDAK mendapat akses laporan finansial** — hanya operasional.\n- **admin_owner**: Dapat melihat ringkasan GMV platform, tapi tidak mengubah data finansial.",
                'roles_allowed' => 'owner_platform,admin_owner,owner_toko',
            ],

            // ── FAQ: Sistem Ulasan ────────────────────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'Cara Memberikan dan Melihat Ulasan',
                'keywords'      => 'ulasan, review, rating, bintang, komentar, penilaian toko',
                'content'       => "**Sistem Ulasan SmartLaundry:**\n\n- Customer dapat memberikan ulasan setelah pesanan berstatus `SELESAI`.\n- Rating: 1–5 bintang, opsional komentar dan foto.\n- Setiap pesanan hanya bisa diulas satu kali.\n- Rating rata-rata toko diupdate otomatis setelah ulasan baru masuk.\n- Ulasan dapat dilihat di halaman detail toko (publik).\n- Owner/Admin toko dapat melihat ringkasan ulasan di dashboard mereka.",
                'roles_allowed' => 'all',
            ],

            // ── FAQ: Akses Data Chatbot per Role ──────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'Data Apa yang Bisa Dicari via Chatbot?',
                'keywords'      => 'chatbot data, cari user, cari pesanan, cari toko, akses chatbot, query chatbot, fitur chatbot admin',
                'content'       => "**Akses Data via Chatbot per Role:**\n\n**owner_platform & admin_owner:**\n- Cari user berdasarkan nama/email.\n- Statistik user per role, user baru, user nonaktif.\n- Daftar toko + status verifikasi.\n- Cari pesanan berdasarkan kode order.\n- Ringkasan status pesanan & pembayaran kadaluarsa.\n- Status ketersediaan kurir.\n- Ringkasan ulasan dan GMV.\n\n**owner_toko:**\n- Pesanan toko miliknya.\n- Katalog layanan & harga toko sendiri.\n- Armada kurir toko sendiri.\n- Pelanggan yang pernah pesan di tokonya.\n- Omset dan laporan finansial toko sendiri.\n\n**admin_toko:**\n- Antrean pesanan toko sendiri.\n- Status pembayaran & pesanan kadaluarsa.\n- Status kurir toko sendiri.\n- TIDAK mendapat akses omset/finansial.\n\n**customer & kurir:** Tidak mendapat akses lapisan data ini.",
                'roles_allowed' => 'owner_platform,admin_owner,owner_toko,admin_toko',
            ],

            // ── FAQ: QRIS ─────────────────────────────────────────────────────
            [
                'category'      => 'payment',
                'title'         => 'Apa itu QRIS Dinamis dan Cara Penggunaannya',
                'keywords'      => 'QRIS, QRIS dinamis, scan QR, bayar QR, cara bayar QRIS, kode QR',
                'content'       => "**QRIS Dinamis SmartLaundry:**\n\n- QRIS (Quick Response Code Indonesian Standard) dinamis diterbitkan otomatis setelah penimbangan.\n- Setiap tagihan memiliki kode QRIS unik dan berlaku **1 jam**.\n- Cara membayar: buka halaman pembayaran → scan kode QR dengan aplikasi dompet digital atau mobile banking apapun.\n- Jika QRIS kadaluarsa, customer dapat meminta regenerasi QRIS baru dari halaman pembayaran.\n- Konfirmasi pembayaran muncul otomatis setelah transaksi berhasil.",
                'roles_allowed' => 'all',
            ],

            // ── FAQ: Performa Chatbot ─────────────────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'Mode Chatbot: Mock vs API dan Batas Penggunaan',
                'keywords'      => 'mode chatbot, mock mode, API mode, Gemini, OpenAI, rate limit, batas pesan, chatbot provider',
                'content'       => "**Mode Chatbot SmartLaundry:**\n\n**Mode MOCK (default):**\n- Berbasis aturan (rule-based), tidak memerlukan API key eksternal.\n- Cepat dan konsisten untuk pertanyaan yang sudah terdaftar.\n- Cocok untuk pengembangan dan demo.\n\n**Mode API (Gemini/OpenAI):**\n- Menggunakan LLM eksternal untuk menjawab pertanyaan bebas.\n- Memerlukan konfigurasi `AI_API_KEY` dan `AI_PROVIDER` di `.env`.\n- Lebih fleksibel tapi bergantung pada kuota API eksternal.\n\n**Batas Penggunaan:**\n- Maksimal **20 pesan per menit** per user.\n- Riwayat percakapan disimpan per sesi browser.\n- Log percakapan disimpan di database dan dapat diekspor oleh admin platform.",
                'roles_allowed' => 'all',
            ],

            // ── FAQ: Platform Admin ───────────────────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'Fitur Khusus untuk Admin Platform',
                'keywords'      => 'admin platform, monitoring chatbot, chatbot logs, whatsapp logs, dashboard platform, tool admin',
                'content'       => "**Fitur Platform Admin (owner_platform & admin_owner):**\n\n- **Dashboard Platform**: Ringkasan GMV, total toko, total user, pesanan aktif.\n- **Monitoring Chatbot** (`/platform/chatbot-logs`): Lihat semua percakapan chatbot, filter per role/intent, ekspor CSV.\n- **Log Data Akses Chatbot**: Setiap pencarian data via chatbot tercatat di audit log.\n- **WhatsApp Log** (`/platform/whatsapp-logs`): Simulasi notifikasi WhatsApp keluar.\n- **Verifikasi Toko**: Aktifkan/nonaktifkan toko mitra.\n- **Manajemen User**: Pantau semua user, status aktif, role.",
                'roles_allowed' => 'owner_platform,admin_owner',
            ],

            // ── FAQ: Owner Toko ───────────────────────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'Panduan Lengkap Fitur Owner Toko',
                'keywords'      => 'owner toko, fitur toko, kelola toko, profil toko, kurir toko, layanan toko, laporan toko',
                'content'       => "**Fitur Owner Toko SmartLaundry:**\n\n- **Profil Toko**: Edit nama, deskripsi, jam operasional, logo, banner.\n- **Layanan & Harga**: Tambah/edit/hapus layanan laundry (kiloan, satuan, express, special).\n- **Armada Kurir**: Tambah/edit/nonaktifkan kurir, lihat status ketersediaan.\n- **Dashboard Pesanan**: Lihat antrean pesanan, konfirmasi, timbang, dan update status.\n- **Penugasan Kurir Manual**: Tugaskan kurir ke pesanan yang belum terlayani.\n- **Laporan Omset**: Lihat total transaksi dan pendapatan toko sendiri.\n- **Ulasan Toko**: Pantau rating dan komentar pelanggan.",
                'roles_allowed' => 'owner_toko,owner_platform,admin_owner',
            ],

            // ── FAQ: Admin Toko ───────────────────────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'Panduan Fitur Admin Toko (Kasir/Operator)',
                'keywords'      => 'admin toko, kasir, operator toko, timbang, konfirmasi, antrean, pesanan masuk',
                'content'       => "**Fitur Admin Toko (Kasir/Operator) SmartLaundry:**\n\n- **Antrean Pesanan**: Lihat semua pesanan masuk ke toko.\n- **Penimbangan**: Timbang laundry customer dan hitung tagihan otomatis.\n- **Konfirmasi Pembayaran**: Konfirmasi pembayaran cash dari customer.\n- **Update Status**: Perbarui status pesanan (SEDANG_DICUCI, SELESAI_DICUCI, dll).\n- **Pesanan Kadaluarsa**: Kelola pesanan dengan pembayaran kadaluarsa dan inisiasi pengembalian.\n- **Status Kurir**: Pantau posisi dan ketersediaan kurir toko.\n\n**Catatan:** Admin toko TIDAK memiliki akses laporan omset atau data finansial.",
                'roles_allowed' => 'admin_toko,owner_toko,owner_platform,admin_owner',
            ],

            // ── Troubleshooting ───────────────────────────────────────────────
            [
                'category'      => 'faq',
                'title'         => 'Troubleshooting: Pesanan Tidak Update / Kurir Tidak Bergerak',
                'keywords'      => 'troubleshoot, masalah, kurir tidak bergerak, GPS tidak update, pesanan stuck, status tidak berubah, error pesanan',
                'content'       => "**Troubleshooting Umum SmartLaundry:**\n\n**GPS Kurir tidak update:**\n- Pastikan kurir memberikan izin akses lokasi di browser.\n- Halaman dashboard kurir harus tetap terbuka dan aktif.\n- Coba refresh halaman kurir.\n\n**Status pesanan tidak berubah:**\n- Periksa apakah langkah sebelumnya sudah diselesaikan (status harus berurutan).\n- Jika stuck di MENUNGGU_PEMBAYARAN: periksa apakah QRIS sudah kadaluarsa.\n\n**Kurir tidak mendapat tawaran tugas:**\n- Pastikan kurir berstatus `TERSEDIA` di dashboard.\n- Cek apakah ada kurir lain yang sudah menerima tugas tersebut.\n\n**Butuh bantuan lebih lanjut:**\n- Hubungi CS SmartLaundry atau admin platform.",
                'roles_allowed' => 'all',
            ],
        ];
    }
}

