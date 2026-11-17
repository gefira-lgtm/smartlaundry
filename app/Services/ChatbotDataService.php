<?php
namespace App\Services;

use App\Core\Database;
use App\Models\ChatbotKnowledge;
use PDO;

/**
 * ChatbotDataService â€” Lapisan akses data read-only untuk chatbot admin/owner.
 *
 * PRINSIP KEAMANAN:
 *  - Semua query menggunakan prepared statement dengan parameter terikat.
 *  - Teks dari user atau LLM TIDAK PERNAH dijadikan SQL atau nama tabel/kolom.
 *  - Hanya kolom yang diizinkan yang dikembalikan (whitelist kolom per method).
 *  - Kolom TERLARANG (password, google_id, token, remember_token, qris_payload mentah) tidak pernah keluar.
 *  - Telepon dan email selalu ditampilkan tersamarkan.
 *  - Maksimal 10 baris per jawaban; jika ada lebih, sertakan info sisanya.
 *  - store_id SELALU dari session/relasi user, TIDAK dari teks chat.
 *  - Setiap akses dicatat ke tabel chatbot_data_access_logs.
 *  - Tidak ada kemampuan INSERT, UPDATE, atau DELETE.
 */
class ChatbotDataService {
    private PDO $db;
    private int    $maxRows = 10;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // =========================================================================
    // Â§ HELPER: Masking PII
    // =========================================================================

    /** Samarkan nomor telepon: 0812****789 */
    public static function maskPhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone);
        $len   = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone, 0, 4) . str_repeat('*', max(0, $len - 7)) . substr($phone, -3);
    }

    /** Samarkan email: a***@domain.com */
    public static function maskEmail(string $email): string {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return '****';
        $local  = $parts[0];
        $domain = $parts[1];
        $masked = substr($local, 0, 1) . str_repeat('*', max(1, strlen($local) - 1));
        return $masked . '@' . $domain;
    }

    /** Bersihkan kolom sensitif dari row sebelum dikembalikan */
    private function sanitizeRow(array $row): array {
        $forbidden = ['password', 'google_id', 'remember_token', 'api_key',
                      'qris_payload', 'session_id', 'secret', 'token', 'access_token'];
        foreach ($forbidden as $col) {
            unset($row[$col]);
        }
        if (isset($row['phone']) && !empty($row['phone'])) {
            $row['phone'] = self::maskPhone($row['phone']);
        }
        if (isset($row['email']) && !empty($row['email'])) {
            $row['email'] = self::maskEmail($row['email']);
        }
        return $row;
    }

    /** Format hasil sebagai teks markdown, tambahkan info "ada X sisa" jika perlu */
    private function formatRows(array $rows, int $totalFound, string $noDataMsg = 'Tidak ada data.'): string {
        if (empty($rows)) return $noDataMsg;
        $out = '';
        foreach ($rows as $i => $row) {
            $out .= ($i + 1) . '. ' . implode(' | ', array_map(
                fn($k, $v) => "**{$k}**: " . ($v ?? '-'),
                array_keys($row), array_values($row)
            )) . "\n";
        }
        $shown = count($rows);
        if ($totalFound > $shown) {
            $sisa = $totalFound - $shown;
            $out .= "\n> â„¹ï¸ Menampilkan {$shown} dari {$totalFound} hasil. Ada **{$sisa} hasil lagi** â€” persempit pencarian untuk hasil lebih spesifik.";
        }
        return $out;
    }

    // =========================================================================
    // Â§ AUDIT LOGGING
    // =========================================================================

    /**
     * Catat setiap akses data ke chatbot_data_access_logs.
     * Hanya merekam ringkasan parameter (bukan data sensitif).
     */
    private function logAccess(int $userId, string $role, ?int $storeId, string $tool, string $paramsSummary, int $rowsReturned): void {
        try {
            // Potong params agar tidak > 500 char
            $safeSummary = substr($paramsSummary, 0, 490);
            $stmt = $this->db->prepare(
                "INSERT INTO `chatbot_data_access_logs`
                    (user_id, role, store_id, tool, params_summary, rows_returned, created_at)
                 VALUES (:uid, :role, :sid, :tool, :params, :rows, NOW())"
            );
            $stmt->execute([
                'uid'    => $userId,
                'role'   => $role,
                'sid'    => $storeId,
                'tool'   => $tool,
                'params' => $safeSummary,
                'rows'   => $rowsReturned,
            ]);
        } catch (\Throwable $th) {
            // Suppress: audit failure tidak boleh memblokir jawaban
        }
    }

    // =========================================================================
    // Â§ VALIDASI SCOPE TOKO
    // =========================================================================

    /**
     * Verifikasi bahwa $storeId benar-benar milik/ditugaskan ke $userId berdasarkan $role.
     * Digunakan oleh owner_toko dan admin_toko untuk mencegah lintas toko.
     *
     * @throws \RuntimeException jika toko tidak dalam scope user ini
     */
    private function assertStoreScope(int $userId, string $role, int $storeId): void {
        if ($role === 'owner_toko') {
            $stmt = $this->db->prepare("SELECT id FROM `stores` WHERE id = :sid AND owner_id = :uid LIMIT 1");
            $stmt->execute(['sid' => $storeId, 'uid' => $userId]);
        } elseif ($role === 'admin_toko') {
            $stmt = $this->db->prepare(
                "SELECT sa.id FROM `store_admins` sa WHERE sa.store_id = :sid AND sa.user_id = :uid LIMIT 1"
            );
            $stmt->execute(['sid' => $storeId, 'uid' => $userId]);
        } else {
            return; // platform roles: tidak perlu cek
        }
        if (!$stmt->fetch()) {
            throw new \RuntimeException("Akses ditolak: data toko tersebut di luar cakupan akses Anda.");
        }
    }

    /**
     * Ambil store_id yang valid milik/ditugaskan ke user ini.
     * Mengembalikan store_id atau null jika tidak ditemukan.
     */
    public function getMyStoreId(int $userId, string $role): ?int {
        if ($role === 'owner_toko') {
            $stmt = $this->db->prepare("SELECT id FROM `stores` WHERE owner_id = :uid LIMIT 1");
        } elseif ($role === 'admin_toko') {
            $stmt = $this->db->prepare(
                "SELECT store_id AS id FROM `store_admins` WHERE user_id = :uid LIMIT 1"
            );
        } else {
            return null;
        }
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    // =========================================================================
    // Â§ TOOLS: PLATFORM (owner_platform & admin_owner)
    // =========================================================================

    /**
     * Tool: searchUsers
     * Cari user berdasarkan nama atau email (partial match, case-insensitive).
     * Kolom aman: id, name, email (masked), phone (masked), role, is_active, created_at, order_count.
     */
    public function searchUsers(int $actorId, string $actorRole, string $keyword): string {
        $this->assertPlatformRole($actorRole);

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        $sql  = "SELECT u.id, u.name, u.email, u.phone,
                        r.display_name AS role,
                        u.is_active,
                        u.created_at,
                        (SELECT COUNT(*) FROM `orders` o
                         WHERE o.customer_id = u.id) AS order_count
                 FROM `users` u
                 JOIN `roles` r ON u.role_id = r.id
                 WHERE (u.name LIKE :like1 OR u.email LIKE :like2)
                 ORDER BY u.created_at DESC
                 LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':like1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':like2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':lim',   $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total  = count($allRows);
        $rows   = array_slice($allRows, 0, $this->maxRows);
        $safe   = array_map(function ($r) {
            return [
                'ID'     => $r['id'],
                'Nama'   => e($r['name']),
                'Email'  => self::maskEmail($r['email']),
                'HP'     => !empty($r['phone']) ? self::maskPhone($r['phone']) : '-',
                'Peran'  => e($r['role']),
                'Aktif'  => $r['is_active'] ? 'Ya' : 'Tidak',
                'Daftar' => $r['created_at'] ? date('d M Y', strtotime($r['created_at'])) : '-',
                'Pesanan'=> (int)$r['order_count'],
            ];
        }, $rows);

        $this->logAccess($actorId, $actorRole, null, 'searchUsers', "keyword:{$keyword}", count($rows));

        $result = "ðŸ” **Hasil Pencarian User \"{$keyword}\":**\n\n";
        $result .= $this->formatRows($safe, $total, "Tidak ada user ditemukan untuk pencarian **\"{$keyword}\"**.");
        return $result;
    }

    /**
     * Tool: getUserStats
     * Statistik user per role, user baru 7 hari, user nonaktif.
     */
    public function getUserStats(int $actorId, string $actorRole): string {
        $this->assertPlatformRole($actorRole);

        $rows = $this->db->query(
            "SELECT r.display_name AS role, COUNT(u.id) AS total,
                    SUM(CASE WHEN u.is_active = 0 THEN 1 ELSE 0 END) AS nonaktif,
                    SUM(CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS baru_7hari
             FROM `users` u JOIN `roles` r ON u.role_id = r.id
             GROUP BY r.id, r.display_name ORDER BY r.id"
        )->fetchAll(PDO::FETCH_ASSOC);

        $totalUsers = array_sum(array_column($rows, 'total'));
        $newUsers   = array_sum(array_column($rows, 'baru_7hari'));
        $inactive   = array_sum(array_column($rows, 'nonaktif'));

        $this->logAccess($actorId, $actorRole, null, 'getUserStats', '', count($rows));

        $result = "ðŸ“Š **Statistik User Platform SmartLaundry:**\n\n";
        $result .= "| Peran | Total | Baru (7hr) | Nonaktif |\n|-------|-------|------------|----------|\n";
        foreach ($rows as $row) {
            $result .= "| {$row['role']} | {$row['total']} | {$row['baru_7hari']} | {$row['nonaktif']} |\n";
        }
        $result .= "\n**Total Semua User**: {$totalUsers} | **Baru 7 Hari**: {$newUsers} | **Nonaktif**: {$inactive}";
        return $result;
    }

    /**
     * Tool: listStores
     * Daftar toko + status verifikasi, rating, jumlah pesanan.
     */
    public function listStores(int $actorId, string $actorRole, ?bool $pendingOnly = false): string {
        $this->assertPlatformRole($actorRole);

        $whereClause = $pendingOnly ? 'WHERE s.is_verified = 0' : '';
        $sql = "SELECT s.id, s.name, s.is_verified, s.is_active, s.rating_avg, s.rating_count,
                       u.name AS owner_name,
                       (SELECT COUNT(*) FROM `orders` o WHERE o.store_id = s.id) AS total_orders
                FROM `stores` s JOIN `users` u ON s.owner_id = u.id
                {$whereClause}
                ORDER BY s.is_verified ASC, s.created_at DESC
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($allRows);
        $rows  = array_slice($allRows, 0, $this->maxRows);
        $safe  = array_map(fn($r) => [
            'ID'        => $r['id'],
            'Toko'      => e($r['name']),
            'Owner'     => e($r['owner_name']),
            'Verified'  => $r['is_verified'] ? 'âœ… Ya' : 'â³ Belum',
            'Aktif'     => $r['is_active'] ? 'Ya' : 'Tidak',
            'Rating'    => number_format((float)$r['rating_avg'], 1) . ' (' . $r['rating_count'] . ' ulasan)',
            'Pesanan'   => (int)$r['total_orders'],
        ], $rows);

        $label = $pendingOnly ? 'Belum Terverifikasi' : 'Semua Toko';
        $this->logAccess($actorId, $actorRole, null, 'listStores', "pendingOnly:" . ($pendingOnly ? 'true' : 'false'), count($rows));

        $result = "ðŸª **Daftar Toko â€” {$label}:**\n\n";
        $result .= $this->formatRows($safe, $total, "Tidak ada toko ditemukan.");
        return $result;
    }

    /**
     * Tool: searchOrderByCode
     * Cari pesanan berdasarkan kode order. Platform: akses semua toko.
     */
    public function searchOrderByCode(int $actorId, string $actorRole, string $orderCode, ?int $storeId = null): string {
        $isPlatform = in_array($actorRole, ['owner_platform', 'admin_owner']);

        // Sanitasi: kode order hanya huruf, angka, dan tanda hubung
        $safeCode = preg_replace('/[^\w\-]/', '', strtoupper($orderCode));
        if (empty($safeCode)) {
            return "âš ï¸ Kode order tidak valid.";
        }

        $params  = ['code' => $safeCode];
        $addWhere = '';
        if (!$isPlatform && $storeId) {
            $addWhere = ' AND o.store_id = :sid';
            $params['sid'] = $storeId;
        }

        $sql = "SELECT o.id, o.order_code, o.status, o.payment_method, o.total_price,
                       o.delivery_fee, o.created_at, o.completed_at,
                       s.name AS store_name,
                       u.name AS customer_name
                FROM `orders` o
                JOIN `stores` s ON o.store_id = s.id
                JOIN `users` u ON o.customer_id = u.id
                WHERE o.order_code = :code{$addWhere}
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, $storeId, 'searchOrderByCode', "code:{$safeCode}", $order ? 1 : 0);

        if (!$order) {
            return "âŒ Pesanan dengan kode **{$safeCode}** tidak ditemukan" . (!$isPlatform ? " di toko Anda." : ".");
        }

        $totalPrice = 'Rp ' . number_format((float)$order['total_price'], 0, ',', '.');
        $result  = "ðŸ“¦ **Detail Pesanan {$order['order_code']}:**\n\n";
        $result .= "â€¢ **Status**: `{$order['status']}`\n";
        $result .= "â€¢ **Toko**: " . e($order['store_name']) . "\n";
        $result .= "â€¢ **Customer**: " . e($order['customer_name']) . "\n";
        $result .= "â€¢ **Metode Bayar**: {$order['payment_method']}\n";
        $result .= "â€¢ **Total**: {$totalPrice}\n";
        $result .= "â€¢ **Dibuat**: " . ($order['created_at'] ? date('d M Y H:i', strtotime($order['created_at'])) : '-') . "\n";
        $result .= "â€¢ **Selesai**: " . ($order['completed_at'] ? date('d M Y H:i', strtotime($order['completed_at'])) : '-');
        return $result;
    }

    /**
     * Tool: getOrderSummary
     * Ringkasan status pesanan platform: per-status count, kadaluarsa, pengembalian.
     */
    public function getOrderSummary(int $actorId, string $actorRole): string {
        $this->assertPlatformRole($actorRole);

        $rows = $this->db->query(
            "SELECT status, COUNT(*) AS total FROM `orders` GROUP BY status ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $grandTotal = array_sum(array_column($rows, 'total'));
        $gmvRow     = $this->db->query(
            "SELECT COALESCE(SUM(total_price),0) AS gmv FROM `orders` WHERE status = 'SELESAI'"
        )->fetch(PDO::FETCH_ASSOC);
        $gmv = 'Rp ' . number_format((float)($gmvRow['gmv'] ?? 0), 0, ',', '.');

        $this->logAccess($actorId, $actorRole, null, 'getOrderSummary', '', count($rows));

        $result = "ðŸ“Š **Ringkasan Pesanan Platform:**\n\n";
        $result .= "| Status | Jumlah |\n|--------|--------|\n";
        foreach ($rows as $r) {
            $result .= "| `{$r['status']}` | {$r['total']} |\n";
        }
        $result .= "\n**Total Semua Pesanan**: {$grandTotal} | **GMV (SELESAI)**: {$gmv}";
        return $result;
    }

    /**
     * Tool: getCourierAvailability
     * Status ketersediaan semua kurir di platform.
     */
    public function getCourierAvailability(int $actorId, string $actorRole): string {
        $this->assertPlatformRole($actorRole);

        $sql = "SELECT c.id, c.status, c.vehicle_type, c.license_plate,
                       u.name AS courier_name, s.name AS store_name,
                       (SELECT COUNT(*) FROM `orders` o
                        WHERE o.courier_id = c.user_id
                        AND o.status IN ('KURIR_MENUJU_LOKASI','LAUNDRY_DIJEMPUT','DALAM_PERJALANAN_KE_TOKO',
                                         'SIAP_DIANTAR','KURIR_MENGANTAR','PROSES_PENGEMBALIAN')) AS active_tasks
                FROM `couriers` c
                JOIN `users` u ON c.user_id = u.id
                JOIN `stores` s ON c.store_id = s.id
                ORDER BY c.status, s.name
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($allRows);
        $rows  = array_slice($allRows, 0, $this->maxRows);
        $safe  = array_map(fn($r) => [
            'Kurir'       => e($r['courier_name']),
            'Toko'        => e($r['store_name']),
            'Kendaraan'   => e($r['vehicle_type']) . ' (' . e($r['license_plate']) . ')',
            'Status'      => $r['status'],
            'Tugas Aktif' => (int)$r['active_tasks'],
        ], $rows);

        $this->logAccess($actorId, $actorRole, null, 'getCourierAvailability', '', count($rows));

        $result = "ðŸ›µ **Status Kurir Platform:**\n\n";
        $result .= $this->formatRows($safe, $total, "Tidak ada data kurir.");
        return $result;
    }

    /**
     * Tool: getReviewSummary
     * Ringkasan rating dan 3 ulasan terbaru.
     */
    public function getReviewSummary(int $actorId, string $actorRole): string {
        $this->assertPlatformRole($actorRole);

        $stats = $this->db->query(
            "SELECT COUNT(*) AS total, ROUND(AVG(rating),2) AS avg_rating,
                    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS bintang5,
                    SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS rendah
             FROM `reviews`"
        )->fetch(PDO::FETCH_ASSOC);

        $recent = $this->db->query(
            "SELECT r.rating, LEFT(r.comment, 80) AS komentar, s.name AS toko, r.created_at
             FROM `reviews` r JOIN `stores` s ON r.store_id = s.id
             ORDER BY r.created_at DESC LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, null, 'getReviewSummary', '', 1);

        $result  = "â­ **Ringkasan Ulasan Platform:**\n\n";
        $result .= "â€¢ Total ulasan: **{$stats['total']}** | Rating rata-rata: **{$stats['avg_rating']}** â­\n";
        $result .= "â€¢ Bintang 5: {$stats['bintang5']} | Rating â‰¤ 2: {$stats['rendah']}\n\n";
        $result .= "**3 Ulasan Terbaru:**\n";
        foreach ($recent as $i => $r) {
            $result .= ($i+1) . ". â­{$r['rating']} â€” Toko: " . e($r['toko']) . " â€” " . e($r['komentar'] ?? '(tanpa komentar)') . "\n";
        }
        return $result;
    }

    // =========================================================================
    // Â§ TOOLS: STORE SCOPE (owner_toko & admin_toko)
    // =========================================================================

    /**
     * Tool: getStoreOrders
     * Pesanan masuk ke toko sendiri. admin_toko tidak dapat data finansial.
     */
    public function getStoreOrders(int $actorId, string $actorRole, int $storeId, ?string $statusFilter = null): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $allowed = ['owner_toko', 'admin_toko', 'owner_platform', 'admin_owner'];
        if (!in_array($actorRole, $allowed)) {
            return "â›” Akses ditolak.";
        }

        // admin_toko tidak boleh lihat finansial
        $showFinancial = in_array($actorRole, ['owner_toko', 'owner_platform', 'admin_owner']);

        $whereStatus = '';
        $params = ['sid' => $storeId];
        if ($statusFilter) {
            $safeStatus  = preg_replace('/[^\w]/', '', strtoupper($statusFilter));
            $whereStatus = ' AND o.status = :status';
            $params['status'] = $safeStatus;
        }

        $priceCol = $showFinancial ? ', o.total_price, o.delivery_fee' : '';
        $sql = "SELECT o.order_code, o.status, o.payment_method{$priceCol},
                       o.created_at, u.name AS customer_name
                FROM `orders` o
                JOIN `users` u ON o.customer_id = u.id
                WHERE o.store_id = :sid{$whereStatus}
                ORDER BY o.created_at DESC
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':lim', $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($allRows);
        $rows  = array_slice($allRows, 0, $this->maxRows);
        $safe  = array_map(function ($r) use ($showFinancial) {
            $out = [
                'Kode'      => $r['order_code'],
                'Status'    => $r['status'],
                'Customer'  => e($r['customer_name']),
                'Metode'    => $r['payment_method'],
                'Tanggal'   => date('d M Y H:i', strtotime($r['created_at'])),
            ];
            if ($showFinancial) {
                $out['Total'] = 'Rp ' . number_format((float)$r['total_price'], 0, ',', '.');
            }
            return $out;
        }, $rows);

        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreOrders', "store:{$storeId},status:" . ($statusFilter ?? 'all'), count($rows));

        $result = "ðŸ“‹ **Pesanan Toko (ID: {$storeId})" . ($statusFilter ? " â€” Status: {$statusFilter}" : '') . ":**\n\n";
        $result .= $this->formatRows($safe, $total, "Tidak ada pesanan ditemukan.");
        return $result;
    }

    /**
     * Tool: getStoreServices
     * Katalog layanan dan harga toko sendiri.
     */
    public function getStoreServices(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $stmt = $this->db->prepare(
            "SELECT name, category, price, unit, min_order, estimated_duration, is_active
             FROM `laundry_services`
             WHERE store_id = :sid
             ORDER BY category, name
             LIMIT :lim"
        );
        $stmt->bindValue(':sid', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($allRows);
        $rows  = array_slice($allRows, 0, $this->maxRows);
        $safe  = array_map(fn($r) => [
            'Layanan'    => e($r['name']),
            'Kategori'   => $r['category'],
            'Harga'      => 'Rp ' . number_format((float)$r['price'], 0, ',', '.') . '/' . $r['unit'],
            'Min. Order' => $r['min_order'] . ' ' . $r['unit'],
            'Estimasi'   => $r['estimated_duration'],
            'Aktif'      => $r['is_active'] ? 'Ya' : 'Tidak',
        ], $rows);

        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreServices', "store:{$storeId}", count($rows));

        $result = "ðŸ§º **Katalog Layanan Toko (ID: {$storeId}):**\n\n";
        $result .= $this->formatRows($safe, $total, "Tidak ada layanan terdaftar.");
        return $result;
    }

    /**
     * Tool: getStoreCouriers
     * Armada kurir toko sendiri.
     */
    public function getStoreCouriers(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $sql = "SELECT u.name AS kurir, c.vehicle_type, c.license_plate, c.status, c.is_available,
                       (SELECT COUNT(*) FROM `orders` o
                        WHERE o.courier_id = c.user_id
                        AND o.status IN ('KURIR_MENUJU_LOKASI','LAUNDRY_DIJEMPUT','DALAM_PERJALANAN_KE_TOKO',
                                         'SIAP_DIANTAR','KURIR_MENGANTAR','PROSES_PENGEMBALIAN')) AS tugas_aktif
                FROM `couriers` c JOIN `users` u ON c.user_id = u.id
                WHERE c.store_id = :sid
                ORDER BY c.status
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($allRows);
        $rows  = array_slice($allRows, 0, $this->maxRows);
        $safe  = array_map(fn($r) => [
            'Kurir'       => e($r['kurir']),
            'Kendaraan'   => e($r['vehicle_type']) . ' (' . e($r['license_plate']) . ')',
            'Status'      => $r['status'],
            'Tersedia'    => $r['is_available'] ? 'Ya' : 'Tidak',
            'Tugas Aktif' => (int)$r['tugas_aktif'],
        ], $rows);

        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreCouriers', "store:{$storeId}", count($rows));

        $result = "ðŸ›µ **Armada Kurir Toko (ID: {$storeId}):**\n\n";
        $result .= $this->formatRows($safe, $total, "Tidak ada kurir terdaftar.");
        return $result;
    }

    /**
     * Tool: getStoreCustomers
     * Pelanggan yang pernah memesan di toko ini (kontak tersamarkan).
     * owner_toko saja. admin_toko: tidak boleh.
     */
    public function getStoreCustomers(int $actorId, string $actorRole, int $storeId): string {
        if ($actorRole === 'admin_toko') {
            return "â›” Akses ditolak: Admin toko tidak bisa melihat daftar pelanggan.";
        }
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $sql = "SELECT u.name, u.email, u.phone,
                       COUNT(o.id) AS total_orders,
                       MAX(o.created_at) AS last_order
                FROM `users` u
                JOIN `orders` o ON o.customer_id = u.id
                WHERE o.store_id = :sid
                GROUP BY u.id, u.name, u.email, u.phone
                ORDER BY total_orders DESC
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($allRows);
        $rows  = array_slice($allRows, 0, $this->maxRows);
        $safe  = array_map(fn($r) => [
            'Nama'        => e($r['name']),
            'Email'       => self::maskEmail($r['email']),
            'HP'          => !empty($r['phone']) ? self::maskPhone($r['phone']) : '-',
            'Total Pesan' => (int)$r['total_orders'],
            'Terakhir'    => $r['last_order'] ? date('d M Y', strtotime($r['last_order'])) : '-',
        ], $rows);

        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreCustomers', "store:{$storeId}", count($rows));

        $result = "ðŸ‘¥ **Pelanggan Toko (ID: {$storeId}):**\n\n";
        $result .= $this->formatRows($safe, $total, "Belum ada pelanggan.");
        return $result;
    }

    /**
     * Tool: getStoreRevenue
     * Omset dan pendapatan toko. Hanya owner_toko dan platform.
     */
    public function getStoreRevenue(int $actorId, string $actorRole, int $storeId): string {
        if ($actorRole === 'admin_toko') {
            $canViewFinance = (bool)\App\Models\Setting::get('admin_toko_can_view_finance', false);
            if (!$canViewFinance) {
                return "â›” Akses ditolak: Admin toko tidak memiliki akses laporan finansial.";
            }
        }
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $sql = "SELECT
                    COUNT(*) AS total_orders,
                    SUM(CASE WHEN status = 'SELESAI' THEN 1 ELSE 0 END) AS selesai,
                    COALESCE(SUM(CASE WHEN status = 'SELESAI' THEN total_price ELSE 0 END), 0) AS omset,
                    COALESCE(SUM(CASE WHEN status = 'SELESAI' THEN delivery_fee ELSE 0 END), 0) AS ongkir,
                    COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status = 'SELESAI' THEN total_price ELSE 0 END), 0) AS omset_bulan_ini
                FROM `orders`
                WHERE store_id = :sid";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $storeId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreRevenue', "store:{$storeId}", 1);

        $result  = "ðŸ’° **Laporan Omset Toko (ID: {$storeId}):**\n\n";
        $result .= "â€¢ Total Pesanan: **{$r['total_orders']}** | Selesai: **{$r['selesai']}**\n";
        $result .= "â€¢ Total Omset (SELESAI): **Rp " . number_format((float)$r['omset'], 0, ',', '.') . "**\n";
        $result .= "â€¢ Omset Bulan Ini: **Rp " . number_format((float)$r['omset_bulan_ini'], 0, ',', '.') . "**\n";
        $result .= "â€¢ Total Ongkir: Rp " . number_format((float)$r['ongkir'], 0, ',', '.');
        return $result;
    }

    /**
     * Tool: getStoreReviews
     * Ringkasan ulasan toko sendiri.
     */
    public function getStoreReviews(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $stats = $this->db->prepare(
            "SELECT COUNT(*) AS total, ROUND(AVG(rating),2) AS avg_rating FROM `reviews` WHERE store_id = :sid"
        );
        $stats->execute(['sid' => $storeId]);
        $s = $stats->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare(
            "SELECT r.rating, LEFT(r.comment, 100) AS komentar, u.name AS customer, r.created_at
             FROM `reviews` r JOIN `users` u ON r.customer_id = u.id
             WHERE r.store_id = :sid
             ORDER BY r.created_at DESC LIMIT 5"
        );
        $stmt->execute(['sid' => $storeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreReviews', "store:{$storeId}", count($rows));

        $result  = "â­ **Ulasan Toko (ID: {$storeId}):**\n\n";
        $result .= "â€¢ Total: **{$s['total']}** ulasan | Rating rata-rata: **{$s['avg_rating']}** â­\n\n";
        $result .= "**5 Ulasan Terbaru:**\n";
        foreach ($rows as $i => $r) {
            $result .= ($i+1) . ". â­{$r['rating']} â€” " . e($r['customer']) . ": " . e($r['komentar'] ?? '(tanpa komentar)') . "\n";
        }
        return $result;
    }

    /**
     * Tool: getExpiredOrders
     * Pesanan kadaluarsa dan dalam proses pengembalian (toko sendiri).
     */
    public function getExpiredOrders(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $sql = "SELECT o.order_code, o.status, o.created_at, u.name AS customer
                FROM `orders` o JOIN `users` u ON o.customer_id = u.id
                WHERE o.store_id = :sid
                  AND o.status IN ('PEMBAYARAN_KADALUARSA','PROSES_PENGEMBALIAN')
                ORDER BY o.created_at DESC
                LIMIT :lim";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sid', $storeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $this->maxRows + 1, PDO::PARAM_INT);
        $stmt->execute();
        $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($allRows);
        $rows  = array_slice($allRows, 0, $this->maxRows);
        $safe  = array_map(fn($r) => [
            'Kode'      => $r['order_code'],
            'Status'    => $r['status'],
            'Customer'  => e($r['customer']),
            'Tanggal'   => date('d M Y H:i', strtotime($r['created_at'])),
        ], $rows);

        $this->logAccess($actorId, $actorRole, $storeId, 'getExpiredOrders', "store:{$storeId}", count($rows));

        $result = "âš ï¸ **Pesanan Kadaluarsa / Pengembalian Toko (ID: {$storeId}):**\n\n";
        $result .= $this->formatRows($safe, $total, "Tidak ada pesanan kadaluarsa saat ini. âœ…");
        return $result;
    }

    // =========================================================================
    // Â§ HELPER PERIODE & KONVERSI ZONA WAKTU (Asia/Jakarta)
    // =========================================================================

    /**
     * Parse periode dari teks input user: hari_ini, kemarin, minggu_ini, bulan_ini.
     */
    /**
     * Tool: getStoreOrdersToday
     * Ringkasan pesanan masuk hari ini — format ringkas sesuai dashboard card.
     */
    public function getStoreOrdersToday(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $allowed = ['owner_toko', 'admin_toko', 'owner_platform', 'admin_owner'];
        if (!in_array($actorRole, $allowed)) {
            return "⛔ Akses ditolak.";
        }

        // Gunakan grouping status yang sama persis dengan Store::getStoreSummary() / dashboard card
        $sqlCounts = "SELECT 
                        COUNT(*) AS total_today,
                        SUM(CASE WHEN o.status = 'MENUNGGU_KONFIRMASI' THEN 1 ELSE 0 END) AS pending,
                        SUM(CASE WHEN o.status IN ('SAMPAI_DI_TOKO','SEDANG_DITIMBANG','MENUNGGU_PEMBAYARAN') THEN 1 ELSE 0 END) AS weighing,
                        SUM(CASE WHEN o.status IN ('SEDANG_DICUCI','SEDANG_DIPROSES') THEN 1 ELSE 0 END) AS washing,
                        SUM(CASE WHEN o.status IN ('SIAP_DIANTAR','KURIR_MENGANTAR') THEN 1 ELSE 0 END) AS delivering,
                        SUM(CASE WHEN o.status = 'SELESAI' THEN 1 ELSE 0 END) AS completed
                      FROM `orders` o
                      WHERE o.store_id = :sid AND DATE(o.created_at) = CURDATE()";
        $stmtC = $this->db->prepare($sqlCounts);
        $stmtC->execute(['sid' => $storeId]);
        $c = $stmtC->fetch(\PDO::FETCH_ASSOC) ?: [];

        $total = (int)($c['total_today'] ?? 0);
        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreOrdersToday', "store:{$storeId}", $total);

        if ($total === 0) {
            return "📦 **Pesanan Hari Ini:** Belum ada pesanan masuk (" . date('d M Y') . ").";
        }

        $out  = "📦 **Pesanan Hari Ini — " . date('d M Y') . ":**\n";
        $out .= "• Total: **{$total} pesanan**\n";
        $out .= "• Perlu Konfirmasi: **" . (int)($c['pending']    ?? 0) . "**\n";
        $out .= "• Antrean Timbang: **" . (int)($c['weighing']   ?? 0) . "**\n";
        $out .= "• Dalam Pencucian: **" . (int)($c['washing']    ?? 0) . "**\n";
        $out .= "• Diantar Kurir: **"   . (int)($c['delivering'] ?? 0) . "**\n";
        $out .= "• Selesai: **"         . (int)($c['completed']  ?? 0) . "**";

        return $out;
    }

    /**
     * Tool: getStoreOrdersPending
     * Ringkasan antrean yang perlu tindakan — format ringkas sesuai dashboard card.
     */
    public function getStoreOrdersPending(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $allowed = ['owner_toko', 'admin_toko', 'owner_platform', 'admin_owner'];
        if (!in_array($actorRole, $allowed)) {
            return "⛔ Akses ditolak.";
        }

        // Hitung per grup status — sama dengan dashboard card
        $sql = "SELECT
                  SUM(CASE WHEN o.status = 'MENUNGGU_KONFIRMASI' THEN 1 ELSE 0 END) AS konfirmasi,
                  SUM(CASE WHEN o.status IN ('SAMPAI_DI_TOKO','SEDANG_DITIMBANG') THEN 1 ELSE 0 END) AS timbang,
                  SUM(CASE WHEN o.status = 'MENUNGGU_PEMBAYARAN' THEN 1 ELSE 0 END) AS bayar,
                  COUNT(*) AS total
                FROM `orders` o
                WHERE o.store_id = :sid
                  AND o.status IN ('MENUNGGU_KONFIRMASI','SAMPAI_DI_TOKO','SEDANG_DITIMBANG','MENUNGGU_PEMBAYARAN')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $storeId]);
        $c = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $total = (int)($c['total'] ?? 0);
        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreOrdersPending', "store:{$storeId}", $total);

        if ($total === 0) {
            return "✅ **Antrean Pesanan:** Semua pesanan sudah diproses. Tidak ada yang perlu tindakan!";
        }

        $out  = "⏳ **Antrean Perlu Tindakan:**\n";
        $out .= "• Perlu Konfirmasi: **" . (int)($c['konfirmasi'] ?? 0) . "** pesanan\n";
        $out .= "• Antrean Timbang: **"  . (int)($c['timbang']    ?? 0) . "** pesanan\n";
        $out .= "• Menunggu Bayar: **"   . (int)($c['bayar']      ?? 0) . "** pesanan\n";
        $out .= "• **Total: {$total} perlu ditangani**";

        return $out;
    }

    /**
     * Tool: getStoreOrdersInProgress
     * Ringkasan pesanan yang sedang dikerjakan — format ringkas sesuai dashboard card.
     */
    public function getStoreOrdersInProgress(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $allowed = ['owner_toko', 'admin_toko', 'owner_platform', 'admin_owner'];
        if (!in_array($actorRole, $allowed)) {
            return "⛔ Akses ditolak.";
        }

        // Hitung per grup status — sama dengan dashboard card "Dalam Pencucian"
        $sql = "SELECT
                  SUM(CASE WHEN o.status IN ('SEDANG_DICUCI','SEDANG_DIPROSES') THEN 1 ELSE 0 END) AS cuci,
                  SUM(CASE WHEN o.status = 'SIAP_DIANTAR' THEN 1 ELSE 0 END) AS siap,
                  SUM(CASE WHEN o.status = 'KURIR_MENGANTAR' THEN 1 ELSE 0 END) AS antar,
                  COUNT(*) AS total
                FROM `orders` o
                WHERE o.store_id = :sid
                  AND o.status IN ('SEDANG_DICUCI','SEDANG_DIPROSES','SIAP_DIANTAR','KURIR_MENGANTAR')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $storeId]);
        $c = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $total = (int)($c['total'] ?? 0);
        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreOrdersInProgress', "store:{$storeId}", $total);

        if ($total === 0) {
            return "🧺 **Dalam Proses:** Tidak ada pesanan yang sedang dikerjakan saat ini.";
        }

        $out  = "🧺 **Sedang Dikerjakan:**\n";
        $out .= "• Proses Cuci/Setrika: **" . (int)($c['cuci'] ?? 0) . "** pesanan\n";
        $out .= "• Siap Diantar: **"        . (int)($c['siap'] ?? 0) . "** pesanan\n";
        $out .= "• Kurir Mengantar: **"     . (int)($c['antar'] ?? 0) . "** pesanan\n";
        $out .= "• **Total Aktif: {$total} pesanan**";

        return $out;
    }

    /**
     * Tool: getStoreTopServices
     * Layanan paling laris / populer di toko ini.
     */
    public function getStoreTopServices(int $actorId, string $actorRole, int $storeId): string {
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $allowed = ['owner_toko', 'admin_toko', 'owner_platform', 'admin_owner'];
        if (!in_array($actorRole, $allowed)) {
            return "⛔ Akses ditolak.";
        }

        $sql = "SELECT ls.name AS layanan, ls.category, ls.unit, ls.price,
                       COUNT(oi.id) AS jumlah_order,
                       COALESCE(SUM(oi.quantity), 0) AS total_qty
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN laundry_services ls ON oi.service_id = ls.id
                WHERE o.store_id = :sid AND o.status NOT IN ('DIBATALKAN', 'PEMBAYARAN_KADALUARSA')
                GROUP BY ls.id, ls.name, ls.category, ls.unit, ls.price
                ORDER BY jumlah_order DESC, total_qty DESC
                LIMIT 3";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $storeId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, $storeId, 'getStoreTopServices', "store:{$storeId}", count($rows));

        if (empty($rows)) {
            return "⭐ **Layanan Terlaris:** Belum ada data pesanan layanan pada toko ini.";
        }

        $medals = ['🥇', '🥈', '🥉'];
        $out = "⭐ **Layanan Terlaris:**\n";
        foreach ($rows as $idx => $r) {
            $badge = $medals[$idx] ?? ($idx + 1) . '.';
            $layananName = htmlspecialchars($r['layanan'] ?? '', ENT_QUOTES, 'UTF-8');
            $out .= "{$badge} **{$layananName}** — {$r['jumlah_order']} order\n";
        }

        return trim($out);
    }

    public static function parsePeriod(string $text): string {
        $t = strtolower($text);
        if (preg_match('/\b(kemarin)\b/i', $t)) {
            return 'kemarin';
        }
        if (preg_match('/\b(minggu ini|mingguan|seminggu|7 hari)\b/i', $t)) {
            return 'minggu_ini';
        }
        if (preg_match('/\b(bulan ini|bulanan|sebulan|30 hari)\b/i', $t)) {
            return 'bulan_ini';
        }
        return 'hari_ini';
    }

    /**
     * Dapatkan rentang tanggal YYYY-MM-DD HH:ii:ss dengan batas tengah malam Asia/Jakarta.
     * Mendukung: hari_ini, kemarin, minggu_ini, bulan_ini.
     */
    public function getPeriodDateRange(string $period): array {
        // Specific date format: YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
            return [
                'period'      => $period,
                'start'       => "{$period} 00:00:00",
                'end'         => "{$period} 23:59:59",
                'label'       => date('d M Y', strtotime($period)),
                'short_label' => date('d M Y', strtotime($period)),
                'days'        => 1,
            ];
        }

        // Pastikan zona waktu Asia/Jakarta (WIB)
        $tz  = new \DateTimeZone('Asia/Jakarta');
        $now = new \DateTimeImmutable('now', $tz);

        switch ($period) {
            case 'kemarin':
                $yesterday = $now->sub(new \DateInterval('P1D'));
                $start = $yesterday->format('Y-m-d 00:00:00');
                $end   = $yesterday->format('Y-m-d 23:59:59');
                $label = 'kemarin (' . $yesterday->format('d M Y') . ')';
                $short = 'kemarin';
                $days  = 1;
                break;
            case 'minggu_ini':
                $monday = $now->modify('this week');
                $start = $monday->format('Y-m-d 00:00:00');
                $end   = $now->format('Y-m-d 23:59:59');
                $label = 'minggu ini (' . $monday->format('d M') . ' - ' . $now->format('d M Y') . ')';
                $short = 'minggu ini';
                $days  = (int)$now->format('N'); // 1-7 hari berjalan
                break;
            case 'bulan_ini':
                $firstDay = $now->modify('first day of this month');
                $start = $firstDay->format('Y-m-d 00:00:00');
                $end   = $now->format('Y-m-d 23:59:59');
                $label = 'bulan ini (' . $now->format('F Y') . ')';
                $short = 'bulan ini';
                $days  = (int)$now->format('j'); // hari ke-X dalam bulan berjalan
                break;
            case 'bulan_lalu':
                $firstDayLastMonth = $now->modify('first day of last month');
                $lastDayLastMonth  = $now->modify('last day of last month');
                $start = $firstDayLastMonth->format('Y-m-d 00:00:00');
                $end   = $lastDayLastMonth->format('Y-m-d 23:59:59');
                $label = 'bulan lalu (' . $firstDayLastMonth->format('F Y') . ')';
                $short = 'bulan lalu';
                $days  = (int)$lastDayLastMonth->format('j');
                break;
            case 'tahun_ini':
                $start = $now->format('Y-01-01 00:00:00');
                $end   = $now->format('Y-12-31 23:59:59');
                $label = 'tahun ini (' . $now->format('Y') . ')';
                $short = 'tahun ini';
                $days  = (int)$now->format('z') + 1;
                break;
            case '7_hari':
                $sevenAgo = $now->sub(new \DateInterval('P6D'));
                $start = $sevenAgo->format('Y-m-d 00:00:00');
                $end   = $now->format('Y-m-d 23:59:59');
                $label = '7 hari terakhir (' . $sevenAgo->format('d M') . ' - ' . $now->format('d M Y') . ')';
                $short = '7 hari terakhir';
                $days  = 7;
                break;
            case '30_hari':
                $thirtyAgo = $now->sub(new \DateInterval('P29D'));
                $start = $thirtyAgo->format('Y-m-d 00:00:00');
                $end   = $now->format('Y-m-d 23:59:59');
                $label = '30 hari terakhir (' . $thirtyAgo->format('d M') . ' - ' . $now->format('d M Y') . ')';
                $short = '30 hari terakhir';
                $days  = 30;
                break;
            case 'hari_ini':
            default:
                $start = $now->format('Y-m-d 00:00:00');
                $end   = $now->format('Y-m-d 23:59:59');
                $label = 'hari ini (' . $now->format('d M Y') . ')';
                $short = 'hari ini';
                $days  = 1;
                $period = 'hari_ini';
                break;
        }

        return [
            'period'      => $period,
            'start'       => $start,
            'end'         => $end,
            'label'       => $label,
            'short_label' => $short,
            'days'        => max(1, $days),
        ];
    }

    /**
     * Cek setting apakah admin_toko diizinkan melihat keuangan (default false).
     */
    public static function adminTokoCanViewFinance(): bool {
        try {
            $settingVal = \App\Models\Setting::get('admin_toko_can_view_finance', null);
            if ($settingVal !== null) {
                if (is_bool($settingVal)) return $settingVal;
                return in_array(strtolower(trim((string)$settingVal)), ['1', 'true', 'yes', 'on'], true);
            }
        } catch (\Throwable $th) {}

        $val = getenv('ADMIN_TOKO_CAN_VIEW_FINANCE');
        if ($val === false && isset($_ENV['ADMIN_TOKO_CAN_VIEW_FINANCE'])) {
            $val = $_ENV['ADMIN_TOKO_CAN_VIEW_FINANCE'];
        }
        if (is_bool($val)) return $val;
        if (is_string($val)) {
            return in_array(strtolower(trim($val)), ['1', 'true', 'yes', 'on'], true);
        }
        return false;
    }

    /** Format rupiah */
    public static function formatRupiah(float $amount): string {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /** Format berat kg (misal 18 kg atau 18,5 kg) */
    public static function formatWeight(float $kg): string {
        if (fmod($kg, 1.0) == 0.0) {
            return number_format($kg, 0, ',', '.') . ' kg';
        }
        return number_format($kg, 1, ',', '.') . ' kg';
    }

    // =========================================================================
    // Â§ QUERY METRIK OPERASIONAL & KEUANGAN
    // =========================================================================

    /**
     * Ambil metrik operasional teragregasi (kg, pcs, customer unik, pesanan masuk, customer baru).
     */
    public function getOperationalMetrics(?int $storeId, string $period = 'hari_ini'): array {
        $range = $this->getPeriodDateRange($period);

        // 1. Total berat cucian (kg)
        // Definisi: sum laundry_details.weight_actual untuk order yang ditimbang pada periode,
        // hanya untuk layanan berunit kg, exclude DIBATALKAN, PEMBAYARAN_KADALUARSA, PROSES_PENGEMBALIAN.
        $sqlKg = "SELECT COALESCE(SUM(ld.weight_actual), 0) AS total_kg
                  FROM `laundry_details` ld
                  JOIN `orders` o ON ld.order_id = o.id
                  WHERE o.status NOT IN ('DIBATALKAN', 'PEMBAYARAN_KADALUARSA', 'PROSES_PENGEMBALIAN')
                    AND ld.weight_actual IS NOT NULL
                    AND ld.weight_actual > 0
                    AND ld.created_at BETWEEN :start_dt AND :end_dt
                    AND EXISTS (
                        SELECT 1 FROM `order_items` oi
                        JOIN `laundry_services` ls ON oi.service_id = ls.id
                        WHERE oi.order_id = o.id AND LOWER(ls.unit) = 'kg'
                    )";
        $paramsKg = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
        if ($storeId !== null) {
            $sqlKg .= " AND o.store_id = :sid";
            $paramsKg['sid'] = $storeId;
        }
        $stmtKg = $this->db->prepare($sqlKg);
        $stmtKg->execute($paramsKg);
        $totalKg = (float)$stmtKg->fetchColumn();

        // 2. Total item satuan (pcs)
        // Definisi: sum laundry_details.pieces_count atau quantity layanan berunit selain kg.
        $sqlPcs = "SELECT COALESCE(SUM(
                       CASE 
                           WHEN ld.pieces_count IS NOT NULL AND ld.pieces_count > 0 THEN ld.pieces_count
                           ELSE oi.quantity
                       END
                   ), 0) AS total_pcs
                   FROM `order_items` oi
                   JOIN `orders` o ON oi.order_id = o.id
                   JOIN `laundry_services` ls ON oi.service_id = ls.id
                   LEFT JOIN `laundry_details` ld ON ld.order_id = o.id
                   WHERE o.status NOT IN ('DIBATALKAN', 'PEMBAYARAN_KADALUARSA', 'PROSES_PENGEMBALIAN')
                     AND LOWER(ls.unit) != 'kg'
                     AND (
                         (ld.created_at IS NOT NULL AND ld.created_at BETWEEN :start_dt1 AND :end_dt1)
                         OR (ld.created_at IS NULL AND o.created_at BETWEEN :start_dt2 AND :end_dt2)
                     )";
        $paramsPcs = [
            'start_dt1' => $range['start'], 'end_dt1' => $range['end'],
            'start_dt2' => $range['start'], 'end_dt2' => $range['end'],
        ];
        if ($storeId !== null) {
            $sqlPcs .= " AND o.store_id = :sid";
            $paramsPcs['sid'] = $storeId;
        }
        $stmtPcs = $this->db->prepare($sqlPcs);
        $stmtPcs->execute($paramsPcs);
        $totalPcs = (int)$stmtPcs->fetchColumn();

        // 3. Customer unik & jumlah pesanan masuk
        // Definisi: distinct customer_id & total orders yang created_at pada periode.
        $sqlCust = "SELECT 
                        COUNT(DISTINCT o.customer_id) AS unique_customers,
                        COUNT(o.id) AS total_orders
                    FROM `orders` o
                    WHERE o.created_at BETWEEN :start_dt AND :end_dt";
        $paramsCust = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
        if ($storeId !== null) {
            $sqlCust .= " AND o.store_id = :sid";
            $paramsCust['sid'] = $storeId;
        }
        $stmtCust = $this->db->prepare($sqlCust);
        $stmtCust->execute($paramsCust);
        $rowCust = $stmtCust->fetch(PDO::FETCH_ASSOC);
        $uniqueCust  = (int)($rowCust['unique_customers'] ?? 0);
        $totalOrders = (int)($rowCust['total_orders'] ?? 0);

        // 4. Customer baru terdaftar pada periode (users.created_at, role customer)
        $sqlNewCust = "SELECT COUNT(u.id) AS new_customers
                       FROM `users` u
                       JOIN `roles` r ON u.role_id = r.id
                       WHERE r.name = 'customer'
                         AND u.created_at BETWEEN :start_dt AND :end_dt";
        $stmtNewCust = $this->db->prepare($sqlNewCust);
        $stmtNewCust->execute(['start_dt' => $range['start'], 'end_dt' => $range['end']]);
        $newCust = (int)$stmtNewCust->fetchColumn();

        // 5. Rata-rata kg per hari
        $avgKgPerDay = $range['days'] > 0 ? round($totalKg / $range['days'], 2) : 0.0;

        // Nama toko
        $storeName = 'Semua Toko';
        if ($storeId !== null) {
            $stmtSt = $this->db->prepare("SELECT name FROM `stores` WHERE id = :sid LIMIT 1");
            $stmtSt->execute(['sid' => $storeId]);
            $storeName = (string)$stmtSt->fetchColumn() ?: 'Toko #' . $storeId;
        }

        $hasData = ($totalKg > 0 || $totalPcs > 0 || $totalOrders > 0 || $uniqueCust > 0);

        return [
            'store_id'         => $storeId,
            'store_name'       => $storeName,
            'period'           => $range['period'],
            'period_label'     => $range['short_label'],
            'period_full'      => $range['label'],
            'days'             => $range['days'],
            'total_kg'         => $totalKg,
            'total_pcs'        => $totalPcs,
            'unique_customers' => $uniqueCust,
            'total_orders'     => $totalOrders,
            'new_customers'    => $newCust,
            'avg_kg_per_day'   => $avgKgPerDay,
            'has_data'         => $hasData,
        ];
    }

    /**
     * Ambil metrik finansial (pemasukan, pengeluaran, laba kotor).
     */
    public function getFinancialMetrics(?int $storeId, string $period = 'hari_ini'): array {
        $range = $this->getPeriodDateRange($period);

        // 1. Pemasukan (payments status = 'PAID' and paid_at in period)
        $sqlIn = "SELECT COALESCE(SUM(p.amount), 0) AS pemasukan
                  FROM `payments` p
                  JOIN `orders` o ON p.order_id = o.id
                  WHERE p.status = 'PAID'
                    AND p.paid_at BETWEEN :start_dt AND :end_dt";
        $paramsIn = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
        if ($storeId !== null) {
            $sqlIn .= " AND o.store_id = :sid";
            $paramsIn['sid'] = $storeId;
        }
        $stmtIn = $this->db->prepare($sqlIn);
        $stmtIn->execute($paramsIn);
        $pemasukan = (float)$stmtIn->fetchColumn();

        // 2. Pengeluaran (store_expenses.expense_date in period)
        $sqlEx = "SELECT COALESCE(SUM(se.amount), 0) AS pengeluaran
                  FROM `store_expenses` se
                  WHERE se.expense_date BETWEEN DATE(:start_dt) AND DATE(:end_dt)";
        $paramsEx = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
        if ($storeId !== null) {
            $sqlEx .= " AND se.store_id = :sid";
            $paramsEx['sid'] = $storeId;
        }
        $stmtEx = $this->db->prepare($sqlEx);
        $stmtEx->execute($paramsEx);
        $pengeluaran = (float)$stmtEx->fetchColumn();

        // 3. Laba kotor
        $labaKotor = $pemasukan - $pengeluaran;

        $storeName = 'Semua Toko';
        if ($storeId !== null) {
            $stmtSt = $this->db->prepare("SELECT name FROM `stores` WHERE id = :sid LIMIT 1");
            $stmtSt->execute(['sid' => $storeId]);
            $storeName = (string)$stmtSt->fetchColumn() ?: 'Toko #' . $storeId;
        }

        $hasData = ($pemasukan > 0 || $pengeluaran > 0);

        return [
            'store_id'     => $storeId,
            'store_name'   => $storeName,
            'period'       => $range['period'],
            'period_label' => $range['short_label'],
            'period_full'  => $range['label'],
            'pemasukan'    => $pemasukan,
            'pengeluaran'  => $pengeluaran,
            'laba_kotor'   => $labaKotor,
            'has_data'     => $hasData,
        ];
    }

    // =========================================================================
    // Â§ RINGKASAN CHATBOT BERBASIS ROLE (REKAP, BERAT, CUSTOMER, KEUANGAN)
    // =========================================================================

    /**
     * Tool / Intent: getFullRecap
     * Menampilkan semua metrik sekaligus: keuangan + operasional.
     */
    public function getFullRecap(int $actorId, string $actorRole, string $period = 'hari_ini'): string {
        if (in_array($actorRole, ['customer', 'kurir'])) {
            return "Mohon maaf, ringkasan operasional dan keuangan toko hanya dapat diakses oleh Admin Toko dan Owner.";
        }

        // Platform Role: owner_platform / admin_owner
        if (in_array($actorRole, ['owner_platform', 'admin_owner'])) {
            $opPlatform = $this->getOperationalMetrics(null, $period);
            $finPlatform = $this->getFinancialMetrics(null, $period);

            $this->logAccess($actorId, $actorRole, null, 'getFullRecap', "period:{$period}", 1);

            $hasAnyData = $opPlatform['has_data'] || $finPlatform['has_data'];
            if (!$hasAnyData) {
                return "Belum ada data tercatat untuk seluruh toko pada periode {$opPlatform['period_label']}.";
            }

            $kgStr = self::formatWeight($opPlatform['total_kg']);
            $inStr = self::formatRupiah($finPlatform['pemasukan']);
            $exStr = self::formatRupiah($finPlatform['pengeluaran']);
            $lbStr = self::formatRupiah($finPlatform['laba_kotor']);

            $newCustInfo = $opPlatform['new_customers'] > 0 ? " (terdaftar {$opPlatform['new_customers']} customer baru)" : "";

            $out = "Rekap {$opPlatform['period_label']} untuk seluruh toko (Platform): pemasukan {$inStr}, pengeluaran {$exStr}, laba kotor {$lbStr}. Cucian yang ditimbang {$kgStr} dan {$opPlatform['total_pcs']} item satuan. Customer yang memesan {$opPlatform['unique_customers']} orang dari {$opPlatform['total_orders']} pesanan{$newCustInfo}.\n\n";
            $out .= "**Rincian per toko:**\n";

            // Maksimal 10 toko
            $stores = $this->db->query("SELECT id, name FROM `stores` WHERE is_active = 1 ORDER BY id ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($stores as $idx => $st) {
                $sid = (int)$st['id'];
                $sName = $st['name'];
                $opStore = $this->getOperationalMetrics($sid, $period);
                $finStore = $this->getFinancialMetrics($sid, $period);

                $stKg = self::formatWeight($opStore['total_kg']);
                $stIn = self::formatRupiah($finStore['pemasukan']);
                $stEx = self::formatRupiah($finStore['pengeluaran']);
                $stLb = self::formatRupiah($finStore['laba_kotor']);

                $out .= ($idx + 1) . ". **{$sName}**: pemasukan {$stIn}, pengeluaran {$stEx}, laba kotor {$stLb}. Cucian ditimbang {$stKg} dan {$opStore['total_pcs']} item satuan. Customer {$opStore['unique_customers']} orang dari {$opStore['total_orders']} pesanan.\n";
            }

            return trim($out);
        }

        // Store Roles: owner_toko / admin_toko
        $storeId = $this->getMyStoreId($actorId, $actorRole);
        if (!$storeId) {
            return "Akun Anda belum terhubung dengan toko laundry mana pun.";
        }
        $this->assertStoreScope($actorId, $actorRole, $storeId);

        $op = $this->getOperationalMetrics($storeId, $period);
        $this->logAccess($actorId, $actorRole, $storeId, 'getFullRecap', "period:{$period}", 1);

        // Jika admin_toko dan tidak diizinkan melihat keuangan
        $canFinance = ($actorRole === 'owner_toko') || self::adminTokoCanViewFinance();

        if (!$canFinance) {
            // Admin Toko hanya mendapat metrik operasional
            if (!$op['has_data']) {
                return "Belum ada data tercatat untuk {$op['store_name']} pada periode {$op['period_label']}.";
            }
            $kgStr = self::formatWeight($op['total_kg']);
            $out = "Rekap operasional {$op['period_label']} untuk {$op['store_name']}: Cucian yang ditimbang {$kgStr} dan {$op['total_pcs']} item satuan. Customer yang memesan {$op['unique_customers']} orang dari {$op['total_orders']} pesanan.\n\n";
            $out .= "*(Catatan: Rincian data keuangan seperti pemasukan, pengeluaran, dan laba kotor dibatasi khusus untuk Owner Toko)*";
            return $out;
        }

        // Owner Toko (atau admin_toko dengan setting view finance true)
        $fin = $this->getFinancialMetrics($storeId, $period);
        if (!$op['has_data'] && !$fin['has_data']) {
            return "Belum ada data tercatat untuk {$op['store_name']} pada periode {$op['period_label']}.";
        }

        $kgStr = self::formatWeight($op['total_kg']);
        $inStr = self::formatRupiah($fin['pemasukan']);
        $exStr = self::formatRupiah($fin['pengeluaran']);
        $lbStr = self::formatRupiah($fin['laba_kotor']);

        return "Rekap {$op['period_label']} untuk {$op['store_name']}: pemasukan {$inStr}, pengeluaran {$exStr}, laba kotor {$lbStr}. Cucian yang ditimbang {$kgStr} dan {$op['total_pcs']} item satuan. Customer yang memesan {$op['unique_customers']} orang dari {$op['total_orders']} pesanan.";
    }

    /**
     * Tool / Intent: getWeightRecap
     * Menjawab: berapa kg hari ini, berat cucian hari ini, total kilo, item hari ini, rata-rata kg per hari.
     */
    public function getWeightRecap(int $actorId, string $actorRole, string $period = 'hari_ini', bool $isAverage = false): string {
        if (in_array($actorRole, ['customer', 'kurir'])) {
            return "Mohon maaf, informasi berat cucian toko hanya dapat diakses oleh Admin Toko dan Owner.";
        }

        $storeId = null;
        if (in_array($actorRole, ['owner_toko', 'admin_toko'])) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) return "Akun Anda belum terhubung dengan toko laundry.";
            $this->assertStoreScope($actorId, $actorRole, $storeId);
        }

        $op = $this->getOperationalMetrics($storeId, $period);
        $this->logAccess($actorId, $actorRole, $storeId, 'getWeightRecap', "period:{$period},avg:" . ($isAverage ? '1':'0'), 1);

        if (!$op['has_data']) {
            return "Belum ada data berat cucian tercatat untuk {$op['store_name']} pada periode {$op['period_label']}.";
        }

        $kgStr = self::formatWeight($op['total_kg']);
        if ($isAverage) {
            $avgStr = self::formatWeight($op['avg_kg_per_day']);
            return "Rata-rata berat cucian yang diproses {$op['store_name']} pada {$op['period_label']} adalah **{$avgStr} per hari** (total {$kgStr} dalam {$op['days']} hari).";
        }

        return "Berat cucian yang diproses {$op['period_label']} untuk {$op['store_name']}: ditimbang **{$kgStr}** dan **{$op['total_pcs']} item satuan**.";
    }

    /**
     * Tool / Intent: getCustomerRecap
     * Menjawab: berapa customer hari ini, jumlah pelanggan hari ini, pesanan masuk hari ini, customer baru.
     */
    public function getCustomerRecap(int $actorId, string $actorRole, string $period = 'hari_ini'): string {
        if (in_array($actorRole, ['customer', 'kurir'])) {
            return "Mohon maaf, informasi statistik customer hanya dapat diakses oleh Admin Toko dan Owner.";
        }

        $storeId = null;
        if (in_array($actorRole, ['owner_toko', 'admin_toko'])) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) return "Akun Anda belum terhubung dengan toko laundry.";
            $this->assertStoreScope($actorId, $actorRole, $storeId);
        }

        $op = $this->getOperationalMetrics($storeId, $period);
        $this->logAccess($actorId, $actorRole, $storeId, 'getCustomerRecap', "period:{$period}", 1);

        if ($op['unique_customers'] === 0 && $op['total_orders'] === 0 && $op['new_customers'] === 0) {
            return "Belum ada pesanan atau aktivitas customer tercatat untuk {$op['store_name']} pada periode {$op['period_label']}.";
        }

        $newCustStr = $op['new_customers'] > 0 ? " (terdaftar **{$op['new_customers']} customer baru**)" : "";
        return "Aktivitas customer {$op['period_label']} untuk {$op['store_name']}: **{$op['unique_customers']} orang** customer aktif dari **{$op['total_orders']} pesanan masuk**{$newCustStr}.";
    }

    /**
     * Tool / Intent: getFinancialRecap
     * Menjawab: berapa pemasukan, pengeluaran, laba kotor.
     */
    public function getFinancialRecap(int $actorId, string $actorRole, string $period = 'hari_ini'): string {
        if (in_array($actorRole, ['customer', 'kurir'])) {
            return "Mohon maaf, informasi keuangan hanya dapat diakses oleh Owner Toko dan Admin Owner.";
        }

        if ($actorRole === 'admin_toko' && !self::adminTokoCanViewFinance()) {
            return "Mohon maaf, rincian data keuangan (pemasukan, pengeluaran, dan laba kotor) dibatasi khusus untuk Owner Toko.";
        }

        $storeId = null;
        if (in_array($actorRole, ['owner_toko', 'admin_toko'])) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) return "Akun Anda belum terhubung dengan toko laundry.";
            $this->assertStoreScope($actorId, $actorRole, $storeId);
        }

        $fin = $this->getFinancialMetrics($storeId, $period);
        $this->logAccess($actorId, $actorRole, $storeId, 'getFinancialRecap', "period:{$period}", 1);

        if (!$fin['has_data']) {
            return "Belum ada transaksi keuangan tercatat untuk {$fin['store_name']} pada periode {$fin['period_label']}.";
        }

        $inStr = self::formatRupiah($fin['pemasukan']);
        $exStr = self::formatRupiah($fin['pengeluaran']);
        $lbStr = self::formatRupiah($fin['laba_kotor']);

        return "Ringkasan keuangan {$fin['period_label']} untuk {$fin['store_name']}: pemasukan **{$inStr}**, pengeluaran **{$exStr}**, dan laba kotor **{$lbStr}**.";
    }

    /**
     * Tool API: get_operational_summary(period)
     * Mengembalikan data agregat murni tanpa baris order mentah / data customer mentah.
     */
    public function get_operational_summary(int $actorId, string $actorRole, string $period = 'hari_ini'): array {
        if (in_array($actorRole, ['customer', 'kurir'])) {
            throw new \RuntimeException("Akses ditolak: role {$actorRole} tidak memiliki hak akses metrik operasional.");
        }

        $storeId = null;
        if (in_array($actorRole, ['owner_toko', 'admin_toko'])) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) {
                throw new \RuntimeException("Akun tidak terhubung ke toko.");
            }
            $this->assertStoreScope($actorId, $actorRole, $storeId);
        }

        $op = $this->getOperationalMetrics($storeId, $period);
        $this->logAccess($actorId, $actorRole, $storeId, 'get_operational_summary', "period:{$period}", 1);

        $result = [
            'period'           => $op['period'],
            'period_label'     => $op['period_label'],
            'store_name'       => $op['store_name'],
            'total_kg'         => $op['total_kg'],
            'total_pcs'        => $op['total_pcs'],
            'unique_customers' => $op['unique_customers'],
            'total_orders'     => $op['total_orders'],
            'new_customers'    => $op['new_customers'],
            'avg_kg_per_day'   => $op['avg_kg_per_day'],
        ];

        // Sertakan data keuangan jika diizinkan
        if (in_array($actorRole, ['owner_platform', 'admin_owner', 'owner_toko']) || self::adminTokoCanViewFinance()) {
            $fin = $this->getFinancialMetrics($storeId, $period);
            $result['pemasukan']   = $fin['pemasukan'];
            $result['pengeluaran'] = $fin['pengeluaran'];
            $result['laba_kotor']  = $fin['laba_kotor'];
        }

        return $result;
    }

    // =========================================================================
    // Â§ KNOWLEDGE BASE
    // =========================================================================

    /**
     * Tool: searchKnowledge
     * Cari di basis pengetahuan sistem SmartLaundry.
     */
    public function searchKnowledge(int $actorId, string $actorRole, string $keyword): string {
        try {
            $knowledgeModel = new ChatbotKnowledge();
            $results = $knowledgeModel->search($keyword, $actorRole, 3);
        } catch (\Throwable $th) {
            return "âš ï¸ Basis pengetahuan belum tersedia. Silakan hubungi admin untuk inisialisasi.";
        }

        $this->logAccess($actorId, $actorRole, null, 'searchKnowledge', "keyword:{$keyword}", count($results));

        if (empty($results)) {
            return "ðŸ” Tidak ada informasi yang ditemukan untuk **\"{$keyword}\"** di basis pengetahuan sistem.\n\nCoba kata kunci lain, seperti: *alur pemesanan*, *status order*, *aturan QRIS*, *hak akses role*.";
        }

        $result = "ðŸ“š **Informasi Sistem SmartLaundry untuk \"{$keyword}\":**\n\n";
        foreach ($results as $i => $entry) {
            $result .= "---\n### " . ($i + 1) . ". " . e($entry['title']) . "\n\n";
            $result .= e($entry['content']) . "\n\n";
        }
        return $result;
    }

    // =========================================================================
    // Â§ HELPER PRIVATE
    // =========================================================================

    /** Pastikan hanya platform role yang bisa mengakses tool platform */
    private function assertPlatformRole(string $role): void {
        if (!in_array($role, ['owner_platform', 'admin_owner'])) {
            throw new \RuntimeException("Tool ini hanya tersedia untuk owner_platform dan admin_owner.");
        }
    }

    /**
     * Dapatkan ringkasan data access logs untuk tampilan ChatbotLogController.
     */
    public function getDataAccessLogs(int $page = 1, int $perPage = 30, array $filters = []): array {
        $where  = '';
        $params = [];
        $clauses = [];

        if (!empty($filters['role'])) {
            $clauses[] = 'role = :role';
            $params['role'] = $filters['role'];
        }
        if (!empty($filters['tool'])) {
            $clauses[] = 'tool LIKE :tool';
            $params['tool'] = '%' . $filters['tool'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'DATE(created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'DATE(created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($clauses)) {
            $where = 'WHERE ' . implode(' AND ', $clauses);
        }

        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM `chatbot_data_access_logs` {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT l.*, u.name AS user_name
                FROM `chatbot_data_access_logs` l
                LEFT JOIN `users` u ON l.user_id = u.id
                {$where}
                ORDER BY l.created_at DESC
                LIMIT :lim OFFSET :off";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'        => $data,
            'total'       => $total,
            'per_page'    => $perPage,
            'current_page'=> $page,
            'total_pages' => (int)ceil($total / $perPage),
            'has_next'    => $page < (int)ceil($total / $perPage),
            'has_prev'    => $page > 1,
        ];
    }

    // =========================================================================
    // § EXPENSE QUERY METHODS — Feature B: DB-driven Chatbot Answers
    // =========================================================================

    /**
     * Look up expense(s) by amount with optional period filter.
     * Falls back to nearest amount if no exact match found.
     */
    public function lookupExpenseByAmount(int $userId, string $role, ?float $amount, ?string $period): string
    {
        if (in_array($role, ['customer', 'kurir'], true)) {
            return 'Maaf, Anda tidak memiliki akses ke data pengeluaran.';
        }
        if ($role === 'admin_toko') {
            $allowed = false;
            try {
                $stmt = $this->db->prepare("SELECT `value` FROM `settings` WHERE `key` = 'admin_toko_can_view_finance' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $allowed = $row && in_array(strtolower((string)$row['value']), ['1','true','yes','on'], true);
            } catch (\Throwable $e) {}
            if (!$allowed) { return 'Data finansial hanya dapat diakses oleh owner toko.'; }
        }
        try {
            $engine  = new \App\Services\ChatbotQueryEngine();
            $filters = [];
            if ($amount !== null) {
                $filters[] = ['col' => 'total_amount', 'op' => '=', 'val' => $amount];
            }
            if ($period !== null) {
                $range = $this->getPeriodDateRange($period);
                if (!empty($range['start']) && !empty($range['end'])) {
                    $filters[] = ['col' => 'expense_date', 'op' => 'BETWEEN', 'val' => [$range['start'], $range['end']]];
                }
            }
            $plan = [
                'entity'       => 'expenses',
                'joins'        => ['expenses->stores', 'expenses->users:recorder'],
                'filters'      => $filters,
                'columns'      => ['item_name','category','expense_date','total_amount',
                                   'supplier','notes','quantity','unit','unit_price','store_id'],
                'extra_select' => ['`stores`.`name` AS `store_name`', '`recorder`.`name` AS `recorded_by_name`'],
                'order_by'     => ['expense_date' => 'DESC'],
                'limit'        => 10,
            ];
            $result    = $engine->executeWithFallbackNearest($plan, $userId, $role, 'total_amount');
            $rows      = $result['rows'] ?? [];
            $isNearest = !empty($result['is_nearest']);

            if (empty($rows)) {
                $amtStr = $amount !== null ? \App\Services\ChatbotNLUService::formatRupiah($amount) : '';
                $msg = "Tidak ditemukan data pengeluaran" . ($amtStr ? " dengan nominal {$amtStr}" : "");
                if ($amount !== null) { $msg .= ". Coba sebutkan periode (mis. 'bulan ini'), kategori, atau nominal berbeda."; }
                return $msg;
            }
            if (count($rows) === 1) {
                $r = $rows[0];
                $lines = [];
                if ($isNearest) { $lines[] = "Tidak ada pengeluaran persis. Ini yang terdekat:"; $lines[] = ''; }
                $nominal  = isset($r['total_amount']) ? \App\Services\ChatbotNLUService::formatRupiah((float)$r['total_amount']) : '-';
                $tanggal  = isset($r['expense_date']) ? date('d M Y', strtotime($r['expense_date'])) : '-';
                $toko     = htmlspecialchars($r['store_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                $item     = htmlspecialchars($r['item_name'] ?? ($r['title'] ?? '-'), ENT_QUOTES, 'UTF-8');
                $kat      = ucfirst(strtolower(str_replace('_', ' ', $r['category'] ?? '-')));
                $pemasok  = htmlspecialchars($r['supplier'] ?? '-', ENT_QUOTES, 'UTF-8');
                $pencatat = htmlspecialchars($r['recorded_by_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                $catatan  = htmlspecialchars($r['notes'] ?? '', ENT_QUOTES, 'UTF-8');
                $qty      = isset($r['quantity']) && (float)$r['quantity'] > 0 ? (float)$r['quantity'] : null;
                $satuan   = htmlspecialchars($r['unit'] ?? '', ENT_QUOTES, 'UTF-8');
                $hargaSat = isset($r['unit_price']) && (float)$r['unit_price'] > 0 ? \App\Services\ChatbotNLUService::formatRupiah((float)$r['unit_price']) : null;
                $lines[] = "Pengeluaran {$nominal} pada {$tanggal} di {$toko} digunakan untuk:";
                $lines[] = "- Item: {$item}";
                $lines[] = "- Kategori: {$kat}";
                if ($qty !== null) { $lines[] = "- Jumlah: " . number_format($qty, 0, ',', '.') . ($satuan ? " {$satuan}" : ''); }
                if ($hargaSat) { $lines[] = "- Harga Satuan: {$hargaSat}"; }
                $lines[] = "- Pemasok: {$pemasok}";
                $lines[] = "- Dicatat oleh: {$pencatat}";
                if (!empty($catatan)) { $lines[] = "- Catatan: {$catatan}"; }
                $lines[] = "Berdasarkan data pengeluaran toko, {$tanggal}.";
                return implode("\n", $lines);
            }
            $amtStr = $amount !== null ? \App\Services\ChatbotNLUService::formatRupiah($amount) : 'tersebut';
            $header = $isNearest ? "Tidak ada yang persis. " . count($rows) . " terdekat:" : "Ditemukan " . count($rows) . " pengeluaran {$amtStr}:";
            $lines  = [$header, ''];
            foreach ($rows as $i => $r) {
                $nominal = isset($r['total_amount']) ? \App\Services\ChatbotNLUService::formatRupiah((float)$r['total_amount']) : '-';
                $tgl     = isset($r['expense_date']) ? date('d M Y', strtotime($r['expense_date'])) : '-';
                $item    = htmlspecialchars($r['item_name'] ?? ($r['title'] ?? '-'), ENT_QUOTES, 'UTF-8');
                $toko    = htmlspecialchars($r['store_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                $lines[] = ($i + 1) . ". {$nominal} - {$item} ({$tgl}) @ {$toko}";
            }
            $lines[] = "Ketik nomor untuk detail, atau persempit dengan periode/kategori.";
            return implode("\n", $lines);
        } catch (\RuntimeException $e) {
            return 'Maaf, ' . $e->getMessage();
        } catch (\InvalidArgumentException $e) {
            return 'Maaf, permintaan tidak dapat diproses: ' . $e->getMessage();
        } catch (\Throwable $e) {
            error_log('[ChatbotDataService] lookupExpenseByAmount: ' . $e->getMessage());
            return 'Maaf, terjadi kesalahan saat mengambil data pengeluaran.';
        }
    }

    /**
     * Aggregate expenses by category for a given period.
     */
    public function getExpenseAggregate(int $userId, string $role, ?string $category, string $period = 'bulan_ini'): string
    {
        if (in_array($role, ['customer', 'kurir'], true)) {
            return 'Maaf, Anda tidak memiliki akses ke data pengeluaran.';
        }
        if ($role === 'admin_toko') {
            $allowed = false;
            try {
                $stmt = $this->db->prepare("SELECT `value` FROM `settings` WHERE `key` = 'admin_toko_can_view_finance' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $allowed = $row && in_array(strtolower((string)$row['value']), ['1','true','yes','on'], true);
            } catch (\Throwable $e) {}
            if (!$allowed) { return 'Data finansial hanya dapat diakses oleh owner toko.'; }
        }
        try {
            $storeId = null;
            if (in_array($role, ['owner_toko', 'admin_toko'], true)) {
                $storeId = $this->getMyStoreId($userId, $role);
            }
            $range  = $this->getPeriodDateRange($period);
            $where  = []; $params = [];
            if ($storeId !== null) { $where[] = '`se`.`store_id` = :store_id'; $params['store_id'] = $storeId; }
            if ($category)        { $where[] = '`se`.`category` = :category'; $params['category'] = $category; }
            if (!empty($range['start'])) { $where[] = '`se`.`expense_date` >= :start'; $params['start'] = $range['start']; }
            if (!empty($range['end']))   { $where[] = '`se`.`expense_date` <= :end';   $params['end']   = $range['end'];   }
            $whereStr = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
            $sql = "SELECT `se`.`category`, COUNT(*) AS jumlah, SUM(`se`.`total_amount`) AS total, `s`.`name` AS store_name
                    FROM `store_expenses` `se`
                    LEFT JOIN `stores` `s` ON `se`.`store_id` = `s`.`id`
                    {$whereStr}
                    GROUP BY `se`.`category`, `se`.`store_id`, `s`.`name`
                    ORDER BY total DESC LIMIT 20";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                $pl = str_replace('_', ' ', $period);
                return "Tidak ada data pengeluaran untuk periode '{$pl}'" . ($category ? " kategori '{$category}'" : '') . '.';
            }
            $pl     = str_replace('_', ' ', $period);
            $catL   = $category ? ' kategori ' . ucfirst(strtolower(str_replace('_', ' ', $category))) : '';
            $lines  = ["Rekap pengeluaran{$catL} ({$pl}):", ''];
            $grand  = 0;
            foreach ($rows as $r) {
                $cat    = ucfirst(strtolower(str_replace('_', ' ', $r['category'] ?? '-')));
                $tot    = (float)($r['total'] ?? 0);
                $cnt    = (int)($r['jumlah'] ?? 0);
                $toko   = isset($r['store_name']) ? ' [' . htmlspecialchars($r['store_name'], ENT_QUOTES, 'UTF-8') . ']' : '';
                $grand += $tot;
                $lines[] = "- {$cat}{$toko}: " . \App\Services\ChatbotNLUService::formatRupiah($tot) . " ({$cnt} transaksi)";
            }
            $lines[] = '';
            $lines[] = "Total: " . \App\Services\ChatbotNLUService::formatRupiah($grand);
            $lines[] = "Berdasarkan data pengeluaran toko.";
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            error_log('[ChatbotDataService] getExpenseAggregate: ' . $e->getMessage());
            return 'Maaf, terjadi kesalahan saat mengambil rekap pengeluaran.';
        }
    }

    /**
     * Cross-table payment lookup by amount or order code.
     */
    public function lookupPaymentCrossTable(int $userId, string $role, ?float $amount, ?string $orderCode): string
    {
        if (in_array($role, ['customer', 'kurir'], true)) {
            return 'Maaf, Anda tidak memiliki akses ke data pembayaran.';
        }
        if ($role === 'admin_toko') {
            $allowed = false;
            try {
                $stmt = $this->db->prepare("SELECT `value` FROM `settings` WHERE `key` = 'admin_toko_can_view_finance' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $allowed = $row && in_array(strtolower((string)$row['value']), ['1','true','yes','on'], true);
            } catch (\Throwable $e) {}
            if (!$allowed) { return 'Data pembayaran hanya dapat diakses oleh owner toko.'; }
        }
        if ($amount === null && $orderCode === null) {
            return 'Sebutkan nominal pembayaran atau kode order untuk mencari data.';
        }
        try {
            $where = ['1=1']; $params = [];
            if (in_array($role, ['owner_toko', 'admin_toko'], true)) {
                $storeId = $this->getMyStoreId($userId, $role);
                if ($storeId !== null) { $where[] = 'o.store_id = :store_id'; $params['store_id'] = $storeId; }
            }
            if ($amount !== null)    { $where[] = 'p.amount = :amount';         $params['amount']     = $amount; }
            if ($orderCode !== null) { $where[] = 'o.order_code = :order_code'; $params['order_code'] = $orderCode; }
            $whereStr = implode(' AND ', $where);
            $sql = "SELECT p.id, p.amount, p.status, p.method, p.created_at AS paid_at,
                           o.order_code, o.total_price, o.status AS order_status,
                           s.name AS store_name, u.name AS customer_name
                    FROM payments p
                    LEFT JOIN orders o ON p.order_id = o.id
                    LEFT JOIN stores s ON o.store_id = s.id
                    LEFT JOIN users u  ON o.customer_id = u.id
                    WHERE {$whereStr} ORDER BY p.created_at DESC LIMIT 10";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($rows)) {
                $a = $amount ? ' nominal ' . \App\Services\ChatbotNLUService::formatRupiah($amount) : '';
                $c = $orderCode ? " order {$orderCode}" : '';
                return "Tidak ditemukan data pembayaran{$a}{$c}.";
            }
            if (count($rows) === 1) {
                $r = $rows[0];
                $nominal = \App\Services\ChatbotNLUService::formatRupiah((float)($r['amount'] ?? 0));
                $tgl     = isset($r['paid_at']) ? date('d M Y H:i', strtotime($r['paid_at'])) : '-';
                $toko    = htmlspecialchars($r['store_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                $cust    = htmlspecialchars($r['customer_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                $order   = htmlspecialchars($r['order_code'] ?? '-', ENT_QUOTES, 'UTF-8');
                return "Pembayaran {$nominal} pada {$tgl}:\n"
                     . "- Kode Order: {$order}\n- Toko: {$toko}\n- Customer: {$cust}\n"
                     . "- Metode: {$r['method']}\n- Status: {$r['status']}\nBerdasarkan data pembayaran.";
            }
            $lines = ['Ditemukan ' . count($rows) . ' pembayaran:', ''];
            foreach ($rows as $i => $r) {
                $nominal = \App\Services\ChatbotNLUService::formatRupiah((float)($r['amount'] ?? 0));
                $tgl     = isset($r['paid_at']) ? date('d/m/Y', strtotime($r['paid_at'])) : '-';
                $order   = htmlspecialchars($r['order_code'] ?? '-', ENT_QUOTES, 'UTF-8');
                $toko    = htmlspecialchars($r['store_name'] ?? '-', ENT_QUOTES, 'UTF-8');
                $lines[] = ($i + 1) . ". {$nominal} - Order {$order} ({$tgl}) @ {$toko}";
            }
            return implode("\n", $lines);
        } catch (\Throwable $e) {
            error_log('[ChatbotDataService] lookupPaymentCrossTable: ' . $e->getMessage());
            return 'Maaf, terjadi kesalahan saat mengambil data pembayaran.';
        }
    }

    // =========================================================================
    // § ANALYTICS: OMZET & PESANAN PER LAYANAN / PER TOKO
    // =========================================================================

    /**
     * Tool: getRevenueByService
     * Omzet dirinci per layanan laundry, berdasarkan order_items.subtotal
     * pada pesanan dengan status pembayaran 'PAID'.
     *
     * @param string $period  Periode dari parsePeriod/getPeriodDateRange
     * @param int|null $storeId  Jika null = seluruh toko (platform); jika diisi = satu toko
     */
    public function getRevenueByService(int $actorId, string $actorRole, string $period = 'semua', ?int $storeId = null): string {
        if (in_array($actorRole, ['customer', 'kurir'], true)) {
            return "⛔ Akses ditolak: Fitur analisis bisnis hanya tersedia untuk Admin Platform dan Owner Toko.";
        }

        // Store roles: batasi strictly ke toko sendiri
        if (in_array($actorRole, ['owner_toko', 'admin_toko'], true)) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) return "⚠️ Akun Anda belum terhubung dengan toko laundry.";
            $this->assertStoreScope($actorId, $actorRole, $storeId);
            if ($actorRole === 'admin_toko' && !self::adminTokoCanViewFinance()) {
                return "⛔ Data finansial hanya dapat diakses oleh Owner Toko.";
            }
        }

        // Bangun filter periode
        $periodWhere = '';
        $periodParams = [];
        $periodLabel = 'seluruh waktu';
        if ($period !== 'semua' && $period !== 'all') {
            $range = $this->getPeriodDateRange($period);
            $periodWhere = " AND p.paid_at BETWEEN :start_dt AND :end_dt";
            $periodParams = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
            $periodLabel = $range['label'];
        }

        $storeWhere = $storeId ? " AND o.store_id = :sid" : "";
        if ($storeId) $periodParams['sid'] = $storeId;

        // Gunakan subquery pembayaran lunas agar join tidak menduplikasi order_items
        $sql = "SELECT
                    ls.name AS layanan,
                    ls.category,
                    COUNT(DISTINCT o.id) AS jumlah_pesanan,
                    COALESCE(SUM(oi.subtotal), 0) AS total_omzet
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN laundry_services ls ON oi.service_id = ls.id
                JOIN (
                    SELECT order_id, MAX(paid_at) AS paid_at
                    FROM payments
                    WHERE status = 'PAID'
                    GROUP BY order_id
                ) p ON p.order_id = o.id {$periodWhere}
                WHERE 1=1
                {$storeWhere}
                GROUP BY ls.id, ls.name, ls.category
                ORDER BY total_omzet DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($periodParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, $storeId, 'getRevenueByService', "period:{$period},store:" . ($storeId ?? 'all'), count($rows));

        if (empty($rows)) {
            return "📊 Belum ada data omzet per layanan untuk periode **{$periodLabel}**.";
        }

        $grandTotal = array_sum(array_column($rows, 'total_omzet'));
        $totalOrders = array_sum(array_column($rows, 'jumlah_pesanan'));

        $storeName = $storeId ? '' : 'Seluruh Platform';
        if ($storeId) {
            $st = $this->db->prepare("SELECT name FROM stores WHERE id = :sid LIMIT 1");
            $st->execute(['sid' => $storeId]);
            $storeName = (string)$st->fetchColumn() ?: "Toko #{$storeId}";
        }

        $medals = ['🥇', '🥈', '🥉'];
        $out  = "🧺 **Laporan Omzet per Layanan" . ($storeName ? " — {$storeName}" : " — Seluruh Platform") . "**\n";
        $out .= "📅 Periode: {$periodLabel}\n\n";

        foreach ($rows as $i => $r) {
            $rank = $i + 1;
            $badge = $medals[$i] ?? "• #{$rank}.";
            $pct  = $grandTotal > 0 ? round(($r['total_omzet'] / $grandTotal) * 100, 1) : 0;
            $omz  = 'Rp ' . number_format((float)$r['total_omzet'], 0, ',', '.');
            $kat  = ucfirst($r['category'] ?? 'kiloan');

            $out .= "{$badge} **#{$rank}. {$r['layanan']}** ({$kat})\n";
            $out .= "   • Total Omzet: **{$omz}**\n";
            $out .= "   • Jumlah Pesanan: **{$r['jumlah_pesanan']} pesanan unik**\n";
            $out .= "   • Kontribusi Omzet: **{$pct}%** terhadap total\n\n";
        }

        $topLayanan = $rows[0]['layanan'];
        $topOmz     = 'Rp ' . number_format((float)$rows[0]['total_omzet'], 0, ',', '.');
        $topPct     = $grandTotal > 0 ? round(($rows[0]['total_omzet'] / $grandTotal) * 100, 1) : 0;
        $totalOmzStr= 'Rp ' . number_format($grandTotal, 0, ',', '.');

        $out .= "---\n";
        $out .= "📊 **Ringkasan Layanan:**\n";
        $out .= "• Total Omzet Realisasi: **{$totalOmzStr}**\n";
        $out .= "• Total Pesanan Masuk: **{$totalOrders} pesanan**\n";
        $out .= "• Layanan Terlaris: **{$topLayanan}** ({$topOmz}, menyumbang {$topPct}%)\n\n";
        $out .= "💡 **Kesimpulan:**\n";
        $out .= "Layanan **{$topLayanan}** memberikan kontribusi terbesar terhadap omzet pada periode {$periodLabel}. Omzet dihitung dari subtotal item layanan pada pesanan dengan status pembayaran lunas (PAID).";

        return $out;
    }

    /**
     * Tool: getRevenueByStore
     * Omzet semua toko platform, diurutkan dari terbesar. Hanya untuk platform roles.
     * owner_toko/admin_toko: tampilkan data toko sendiri saja.
     */
    public function getRevenueByStore(int $actorId, string $actorRole, string $period = 'semua'): string {
        if (in_array($actorRole, ['customer', 'kurir'], true)) {
            return "⛔ Akses ditolak: Fitur analisis bisnis hanya tersedia untuk Admin Platform dan Owner Toko.";
        }

        // Store roles: tampilkan toko sendiri saja
        if (in_array($actorRole, ['owner_toko', 'admin_toko'], true)) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) return "⚠️ Akun Anda belum terhubung dengan toko laundry.";
            if ($actorRole === 'admin_toko' && !self::adminTokoCanViewFinance()) {
                return "⛔ Data finansial hanya dapat diakses oleh Owner Toko.";
            }
            return $this->getRevenueByService($actorId, $actorRole, $period, $storeId);
        }

        if (!in_array($actorRole, ['owner_platform', 'admin_owner'], true)) {
            return "⛔ Akses ditolak: Laporan ini hanya dapat diakses oleh Admin Platform dan Owner Platform.";
        }

        $periodWhere = '';
        $periodParams = [];
        $periodLabel = 'seluruh waktu';
        if ($period !== 'semua' && $period !== 'all') {
            $range = $this->getPeriodDateRange($period);
            $periodWhere = " AND p.paid_at BETWEEN :start_dt AND :end_dt";
            $periodParams = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
            $periodLabel = $range['label'];
        }

        $sql = "SELECT
                    s.id,
                    s.name AS toko,
                    u.name AS pemilik,
                    COUNT(DISTINCT p.order_id) AS jumlah_pesanan,
                    COALESCE(SUM(p.amount), 0) AS total_omzet
                FROM stores s
                LEFT JOIN users u ON s.owner_id = u.id
                LEFT JOIN orders o ON o.store_id = s.id
                LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'PAID' {$periodWhere}
                GROUP BY s.id, s.name, u.name
                ORDER BY total_omzet DESC, s.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($periodParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, null, 'getRevenueByStore', "period:{$period}", count($rows));

        if (empty($rows)) {
            return "📊 Belum ada data toko.";
        }

        $grandTotal  = array_sum(array_column($rows, 'total_omzet'));
        $grandOrders = array_sum(array_column($rows, 'jumlah_pesanan'));

        $out  = "💰 **Laporan Omzet per Toko — Platform SmartLaundry**\n";
        $out .= "📅 Periode: {$periodLabel}\n\n";

        foreach ($rows as $i => $r) {
            $rank = $i + 1;
            $pct  = $grandTotal > 0 ? round(((float)$r['total_omzet'] / $grandTotal) * 100, 1) : 0;
            $omz  = 'Rp ' . number_format((float)$r['total_omzet'], 0, ',', '.');
            $crown= ($i === 0 && (float)$r['total_omzet'] > 0) ? ' 🏆' : '';
            $owner= $r['pemilik'] ?: '-';

            $out .= "🏪 **#{$rank}. {$r['toko']}**{$crown}\n";
            $out .= "   • Pemilik: {$owner}\n";
            $out .= "   • Total Omzet: **{$omz}**\n";
            $out .= "   • Transaksi Pembayaran Valid: **{$r['jumlah_pesanan']} transaksi lunas**\n";
            $out .= "   • Kontribusi Omzet: **{$pct}%** terhadap platform\n\n";
        }

        $topStore   = $rows[0];
        $topOmzStr  = 'Rp ' . number_format((float)$topStore['total_omzet'], 0, ',', '.');
        $topPct     = $grandTotal > 0 ? round(((float)$topStore['total_omzet'] / $grandTotal) * 100, 1) : 0;
        $totalOmzStr= 'Rp ' . number_format($grandTotal, 0, ',', '.');

        $out .= "---\n";
        $out .= "📊 **Ringkasan Platform:**\n";
        $out .= "• Total Omzet Terealisasi: **{$totalOmzStr}** dari **{$grandOrders} transaksi pembayaran lunas**\n";
        $out .= "• Toko Kontributor Terbesar: **{$topStore['toko']}** ({$topOmzStr}, menyumbang {$topPct}%)\n\n";
        $out .= "💡 **Kesimpulan:**\n";
        $out .= "**{$topStore['toko']}** memimpin pendapatan platform pada periode {$periodLabel}. Seluruh angka omzet dihitung murni dari transaksi pembayaran valid (status PAID), bukan sekadar nilai pesanan yang belum terbayar.";

        return $out;
    }

    /**
     * Tool: getTopRevenueStore
     * Tampilkan toko dengan omzet terbesar beserta layanan unggulannya.
     */
    public function getTopRevenueStore(int $actorId, string $actorRole, string $period = 'semua'): string {
        if (in_array($actorRole, ['customer', 'kurir'], true)) {
            return "⛔ Akses ditolak: Fitur analisis bisnis hanya tersedia untuk Admin Platform dan Owner Toko.";
        }

        if (!in_array($actorRole, ['owner_platform', 'admin_owner'], true)) {
            return "⛔ Informasi perbandingan omzet seluruh platform hanya dapat diakses oleh Admin Platform dan Owner Platform. Anda dapat melihat laporan omzet toko Anda sendiri dengan menanyakan *'omzet toko saya'* atau *'omzet per layanan'*.";
        }

        $periodWhere = '';
        $periodParams = [];
        $periodLabel = 'seluruh waktu';
        if ($period !== 'semua' && $period !== 'all') {
            $range = $this->getPeriodDateRange($period);
            $periodWhere = " AND p.paid_at BETWEEN :start_dt AND :end_dt";
            $periodParams = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
            $periodLabel = $range['label'];
        }

        // Dapatkan toko dengan omzet terbesar
        $sql = "SELECT s.id, s.name AS toko, u.name AS pemilik,
                        COUNT(DISTINCT p.order_id) AS jumlah_pesanan,
                        COALESCE(SUM(p.amount), 0) AS total_omzet
                FROM stores s
                LEFT JOIN users u ON s.owner_id = u.id
                LEFT JOIN orders o ON o.store_id = s.id
                LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'PAID' {$periodWhere}
                GROUP BY s.id, s.name, u.name
                ORDER BY total_omzet DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($periodParams);
        $top = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, null, 'getTopRevenueStore', "period:{$period}", 1);

        if (!$top || (float)$top['total_omzet'] === 0.0) {
            return "📊 Belum ada data omzet untuk periode **{$periodLabel}**.";
        }

        $topId   = (int)$top['id'];
        $topOmz  = 'Rp ' . number_format((float)$top['total_omzet'], 0, ',', '.');

        $out  = "🏆 **Toko dengan Omzet Terbesar — Periode: {$periodLabel}**\n\n";
        $out .= "🏪 **Nama Toko:** " . $top['toko'] . "\n";
        $out .= "👤 **Pemilik:** " . ($top['pemilik'] ?? '-') . "\n";
        $out .= "💰 **Total Omzet:** {$topOmz}\n";
        $out .= "📦 **Jumlah Pesanan:** {$top['jumlah_pesanan']}\n\n";

        // Layanan terlaris di toko tersebut (subquery pembayaran lunas mencegah penggandaan)
        $sqlSvc = "SELECT ls.name AS layanan,
                          COUNT(DISTINCT o.id) AS pesanan,
                          COALESCE(SUM(oi.subtotal), 0) AS omzet_layanan
                   FROM order_items oi
                   JOIN orders o ON oi.order_id = o.id
                   JOIN laundry_services ls ON oi.service_id = ls.id
                   JOIN (
                       SELECT order_id, MAX(paid_at) AS paid_at
                       FROM payments
                       WHERE status = 'PAID'
                       GROUP BY order_id
                   ) p ON p.order_id = o.id {$periodWhere}
                   WHERE o.store_id = :sid
                   GROUP BY ls.id, ls.name
                   ORDER BY omzet_layanan DESC
                   LIMIT 5";

        $paramsSvc = array_merge(['sid' => $topId], $periodParams);
        $stmtSvc = $this->db->prepare($sqlSvc);
        $stmtSvc->execute($paramsSvc);
        $svcRows = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($svcRows)) {
            $out .= "**🧺 Layanan Unggulan di Toko Ini:**\n";
            foreach ($svcRows as $i => $sv) {
                $svOmz = 'Rp ' . number_format((float)$sv['omzet_layanan'], 0, ',', '.');
                $out .= ($i + 1) . ". " . $sv['layanan'] . " — {$svOmz} ({$sv['pesanan']} pesanan)\n";
            }
        }

        $out .= "\n> *Data berdasarkan pembayaran lunas (PAID) dari database aktual.*";
        return $out;
    }

    /**
     * Tool: getOrdersByStore
     * Total pesanan per toko, diurutkan dari terbanyak.
     * Platform: semua toko. Owner/admin toko: toko sendiri.
     */
    public function getOrdersByStore(int $actorId, string $actorRole, string $period = 'semua'): string {
        if (in_array($actorRole, ['customer', 'kurir'], true)) {
            return "⛔ Akses ditolak: Fitur analisis bisnis hanya tersedia untuk Admin Platform dan Owner Toko.";
        }

        $periodWhere = '';
        $periodParams = [];
        $periodLabel = 'seluruh waktu';
        if ($period !== 'semua' && $period !== 'all') {
            $range = $this->getPeriodDateRange($period);
            $periodWhere = " AND o.created_at BETWEEN :start_dt AND :end_dt";
            $periodParams = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
            $periodLabel = $range['label'];
        }

        // Store roles: scope to own store
        $storeFilter = '';
        $storeId = null;
        if (in_array($actorRole, ['owner_toko', 'admin_toko'], true)) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) return "⚠️ Akun Anda belum terhubung dengan toko laundry.";
            $this->assertStoreScope($actorId, $actorRole, $storeId);
            $storeFilter = " AND o.store_id = :sid";
            $periodParams['sid'] = $storeId;
        }

        $sql = "SELECT s.id, s.name AS toko, u.name AS pemilik,
                        COUNT(DISTINCT o.id) AS total_pesanan,
                        SUM(CASE WHEN o.status = 'SELESAI' THEN 1 ELSE 0 END) AS pesanan_selesai,
                        SUM(CASE WHEN o.status IN ('MENUNGGU_PENJEMPUTAN','KURIR_MENUJU_OUTLET','DITERIMA_TOKO','SEDANG_DICUCI','SIAP_DIANTAR','KURIR_MENGANTAR') THEN 1 ELSE 0 END) AS pesanan_proses,
                        SUM(CASE WHEN o.status = 'MENUNGGU_PEMBAYARAN' THEN 1 ELSE 0 END) AS pesanan_menunggu_bayar,
                        SUM(CASE WHEN o.status IN ('DIBATALKAN','PEMBAYARAN_KADALUARSA','PROSES_PENGEMBALIAN') THEN 1 ELSE 0 END) AS pesanan_batal
                FROM stores s
                LEFT JOIN users u ON s.owner_id = u.id
                LEFT JOIN orders o ON o.store_id = s.id {$periodWhere} {$storeFilter}
                GROUP BY s.id, s.name, u.name
                ORDER BY total_pesanan DESC, s.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($periodParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, $storeId, 'getOrdersByStore', "period:{$period}", count($rows));

        if (empty($rows)) {
            return "📊 Belum ada data pesanan.";
        }

        $grandTotal   = array_sum(array_column($rows, 'total_pesanan'));
        $totalSelesai = array_sum(array_column($rows, 'pesanan_selesai'));
        $totalProses  = array_sum(array_column($rows, 'pesanan_proses'));
        $totalBatal   = array_sum(array_column($rows, 'pesanan_batal'));

        $out  = "📦 **Laporan Pesanan per Toko — Platform SmartLaundry**\n";
        $out .= "📅 Periode: {$periodLabel}\n\n";

        foreach ($rows as $i => $r) {
            $crown = ($i === 0 && (int)$r['total_pesanan'] > 0) ? ' 🏆' : '';
            $rank = $i + 1;
            $owner = $r['pemilik'] ?: '-';

            $out .= "🏪 **#{$rank}. {$r['toko']}**{$crown}\n";
            $out .= "   • Pemilik: {$owner}\n";
            $out .= "   • Total Pesanan: **{$r['total_pesanan']} pesanan**\n";
            $out .= "   • Selesai: **{$r['pesanan_selesai']}** pesanan\n";
            if ((int)$r['pesanan_proses'] > 0) {
                $out .= "   • Sedang Diproses: **{$r['pesanan_proses']}** pesanan\n";
            }
            if ((int)$r['pesanan_menunggu_bayar'] > 0) {
                $out .= "   • Menunggu Pembayaran: **{$r['pesanan_menunggu_bayar']}** pesanan\n";
            }
            $out .= "   • Batal / Kedaluwarsa: **{$r['pesanan_batal']}** pesanan\n\n";
        }

        $out .= "---\n";
        $out .= "📊 **Ringkasan Platform:**\n";
        $out .= "• Total Seluruh Pesanan: **{$grandTotal} pesanan**\n";
        $out .= "• Status Selesai: **{$totalSelesai}** | Sedang Diproses: **{$totalProses}** | Batal: **{$totalBatal}**\n";
        $out .= "• Toko Pesanan Terbanyak: **" . $rows[0]['toko'] . "** ({$rows[0]['total_pesanan']} pesanan)\n\n";
        $out .= "💡 **Kesimpulan:**\n";
        $out .= "Toko **" . $rows[0]['toko'] . "** menangani volume pesanan terbesar pada periode {$periodLabel}. Total pesanan dihitung dari pesanan unik (DISTINCT) di database aktual.";

        return $out;
    }

    /**
     * Tool: getOrdersByStoreAndService
     * Rincian pesanan setiap toko, dipecah per jenis layanan.
     * Ini adalah analisis terdalam — menghitung berapa pesanan per layanan per toko.
     *
     * PENTING: "jumlah pesanan" per layanan = jumlah order unik yang memiliki item layanan tersebut.
     * Total toko = jumlah order unik toko (bukan jumlah item layanan).
     */
    public function getOrdersByStoreAndService(int $actorId, string $actorRole, string $period = 'semua', ?int $filterStoreId = null): string {
        if (in_array($actorRole, ['customer', 'kurir'], true)) {
            return "⛔ Akses ditolak: Fitur analisis bisnis hanya tersedia untuk Admin Platform dan Owner Toko.";
        }

        $periodWhere = '';
        $periodParams = [];
        $periodLabel = 'seluruh waktu';
        if ($period !== 'semua' && $period !== 'all') {
            $range = $this->getPeriodDateRange($period);
            $periodWhere = " AND o.created_at BETWEEN :start_dt AND :end_dt";
            $periodParams = ['start_dt' => $range['start'], 'end_dt' => $range['end']];
            $periodLabel = $range['label'];
        }

        // Store roles: scope to own store
        $storeFilter = '';
        $storeId = $filterStoreId;
        if (in_array($actorRole, ['owner_toko', 'admin_toko'], true)) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) return "⚠️ Akun Anda belum terhubung dengan toko laundry.";
            $this->assertStoreScope($actorId, $actorRole, $storeId);
        }
        if ($storeId !== null) {
            $storeFilter = " AND o.store_id = :sid";
            $periodParams['sid'] = $storeId;
        }

        // 1. Dapatkan semua toko yang relevan
        $sqlStores = "SELECT DISTINCT s.id, s.name AS toko
                      FROM stores s
                      JOIN orders o ON o.store_id = s.id
                      WHERE 1=1 {$periodWhere} {$storeFilter}
                      ORDER BY s.name ASC";
        $stmtStores = $this->db->prepare($sqlStores);
        $stmtStores->execute($periodParams);
        $stores = $stmtStores->fetchAll(PDO::FETCH_ASSOC);

        $this->logAccess($actorId, $actorRole, $storeId, 'getOrdersByStoreAndService', "period:{$period}", count($stores));

        if (empty($stores)) {
            return "📊 Belum ada data pesanan untuk periode **{$periodLabel}**.";
        }

        $out  = "📦 **Analisis Pesanan per Toko & Layanan**\n";
        $out .= "📅 Periode: {$periodLabel}\n\n";

        $grandTotal = 0;

        foreach ($stores as $st) {
            $sid = (int)$st['id'];

            // Total pesanan unik di toko ini
            $sqlTotal = "SELECT COUNT(DISTINCT o.id) AS total
                         FROM orders o
                         WHERE o.store_id = :sid {$periodWhere}";
            $pTotal = array_merge(['sid' => $sid], array_diff_key($periodParams, ['sid' => null]));
            $stmtTotal = $this->db->prepare($sqlTotal);
            $stmtTotal->execute($pTotal);
            $totalToko = (int)$stmtTotal->fetchColumn();
            $grandTotal += $totalToko;

            // Pesanan per layanan: hitung order unik yang memiliki item layanan tertentu
            $sqlSvc = "SELECT ls.name AS layanan,
                              COUNT(DISTINCT oi.order_id) AS pesanan,
                              COALESCE(SUM(oi.subtotal), 0) AS omzet_layanan
                       FROM order_items oi
                       JOIN orders o ON oi.order_id = o.id
                       JOIN laundry_services ls ON oi.service_id = ls.id
                       WHERE o.store_id = :sid {$periodWhere}
                       GROUP BY ls.id, ls.name
                       ORDER BY pesanan DESC, omzet_layanan DESC";
            $pSvc = array_merge(['sid' => $sid], array_diff_key($periodParams, ['sid' => null]));
            $stmtSvc = $this->db->prepare($sqlSvc);
            $stmtSvc->execute($pSvc);
            $svcRows = $stmtSvc->fetchAll(PDO::FETCH_ASSOC);

            $out .= "---\n";
            $out .= "🏪 **" . $st['toko'] . "**\n";
            $out .= "📦 Total pesanan: **{$totalToko}**\n";

            if (!empty($svcRows)) {
                foreach ($svcRows as $sv) {
                    $svOmz = 'Rp ' . number_format((float)$sv['omzet_layanan'], 0, ',', '.');
                    $out .= "  - " . $sv['layanan'] . ": **{$sv['pesanan']} pesanan** ({$svOmz})\n";
                }
            } else {
                $out .= "  - *(Belum ada detail layanan)*\n";
            }
            $out .= "\n";
        }

        $out .= "---\n**📊 Total Semua Toko: {$grandTotal} pesanan**\n";
        $out .= "\n> *Catatan: Satu pesanan bisa memiliki beberapa jenis layanan. 'Pesanan per layanan' = jumlah order unik yang memiliki layanan tersebut.*\n";
        $out .= "> *Data berdasarkan database aktual.*";

        return $out;
    }

    /**
     * Tool: getRevenueToday (or specified period)
     * Menghitung pemasukan dari payments dengan status = 'PAID' dan paid_at berada pada rentang waktu.
     *
     * Scoping:
     * - owner_toko: hanya toko miliknya.
     * - owner_platform, admin_owner: total platform + breakdown per toko jika > 1 toko.
     * - admin_toko: tolak dengan sopan jika admin_toko_can_view_finance tidak mengizinkan.
     * - customer, kurir: tolak dengan sopan.
     */
    public function getRevenueToday(int $actorId, string $actorRole, string $period = 'hari_ini'): string {
        if (in_array($actorRole, ['customer', 'kurir'], true)) {
            return "Maaf, Anda tidak memiliki akses ke data pemasukan.";
        }

        if ($actorRole === 'admin_toko' && !self::adminTokoCanViewFinance()) {
            return "Data finansial hanya dapat diakses oleh owner toko.";
        }

        $range = $this->getPeriodDateRange($period);
        $shortLabel = $range['short_label']; // e.g. "hari ini", "kemarin", "minggu ini", "bulan ini"

        if (in_array($actorRole, ['owner_toko', 'admin_toko'], true)) {
            $storeId = $this->getMyStoreId($actorId, $actorRole);
            if (!$storeId) {
                return "Akun Anda belum terhubung dengan toko laundry.";
            }
            $this->assertStoreScope($actorId, $actorRole, $storeId);

            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(p.amount), 0) AS total_amount, COUNT(p.id) AS total_count
                 FROM payments p
                 JOIN orders o ON p.order_id = o.id
                 WHERE p.status = 'PAID'
                   AND p.paid_at BETWEEN :start_dt AND :end_dt
                   AND o.store_id = :sid"
            );
            $stmt->execute([
                'start_dt' => $range['start'],
                'end_dt'   => $range['end'],
                'sid'      => $storeId,
            ]);
            $res = $stmt->fetch(\PDO::FETCH_ASSOC);
            $cnt = (int)($res['total_count'] ?? 0);
            $total = (float)($res['total_amount'] ?? 0);

            $this->logAccess($actorId, $actorRole, $storeId, 'getRevenueToday', "period:{$period}", $cnt);

            if ($cnt === 0) {
                return "Belum ada pembayaran lunas {$shortLabel} (Rp 0)";
            }

            $formatted = number_format($total, 0, ',', '.');
            return "Total pemasukan {$shortLabel} Rp {$formatted} dari {$cnt} transaksi.";
        }

        // Platform roles: owner_platform, admin_owner
        $stmtTotal = $this->db->prepare(
            "SELECT COALESCE(SUM(p.amount), 0) AS total_amount, COUNT(p.id) AS total_count
             FROM payments p
             JOIN orders o ON p.order_id = o.id
             WHERE p.status = 'PAID'
               AND p.paid_at BETWEEN :start_dt AND :end_dt"
        );
        $stmtTotal->execute([
            'start_dt' => $range['start'],
            'end_dt'   => $range['end'],
        ]);
        $resTotal = $stmtTotal->fetch(\PDO::FETCH_ASSOC);
        $cntTotal = (int)($resTotal['total_count'] ?? 0);
        $totalAmt = (float)($resTotal['total_amount'] ?? 0);

        $this->logAccess($actorId, $actorRole, null, 'getRevenueToday', "period:{$period}", $cntTotal);

        if ($cntTotal === 0) {
            return "Belum ada pembayaran lunas {$shortLabel} (Rp 0)";
        }

        $formattedTotal = number_format($totalAmt, 0, ',', '.');
        $out = "Total pemasukan {$shortLabel} Rp {$formattedTotal} dari {$cntTotal} transaksi.";

        // Breakdown per store
        $stmtStores = $this->db->prepare(
            "SELECT s.id, s.name,
                    COALESCE(SUM(p.amount), 0) AS store_total,
                    COUNT(p.id) AS store_count
             FROM stores s
             LEFT JOIN orders o ON o.store_id = s.id
             LEFT JOIN payments p ON p.order_id = o.id AND p.status = 'PAID' AND p.paid_at BETWEEN :start_dt AND :end_dt
             GROUP BY s.id, s.name
             ORDER BY store_total DESC, s.name ASC"
        );
        $stmtStores->execute([
            'start_dt' => $range['start'],
            'end_dt'   => $range['end'],
        ]);
        $storeRows = $stmtStores->fetchAll(\PDO::FETCH_ASSOC);

        if (count($storeRows) > 1) {
            $out .= "\n\nRincian per toko:";
            foreach ($storeRows as $sRow) {
                $stAmt = number_format((float)$sRow['store_total'], 0, ',', '.');
                $stCnt = (int)$sRow['store_count'];
                $out .= "\n- {$sRow['name']}: Rp {$stAmt} ({$stCnt} transaksi)";
            }
        }

        return $out;
    }
}