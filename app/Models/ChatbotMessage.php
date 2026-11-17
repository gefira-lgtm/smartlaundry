<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

/**
 * ChatbotMessage – Model untuk tabel chatbot_messages.
 *
 * Menyimpan setiap pesan user dan balasan bot secara persisten.
 * Seluruh isi pesan yang masuk ke penyimpanan wajib disamarkan terlebih dahulu
 * lewat maskSensitiveData() sebelum diinsert.
 */
class ChatbotMessage extends Model {
    protected string $table = 'chatbot_messages';
    protected string $primaryKey = 'id';

    // -------------------------------------------------------------------------
    // PII Masking
    // -------------------------------------------------------------------------

    /**
     * Samarkan data sensitif di dalam teks bebas sebelum disimpan ke database.
     *
     * Pola yang disamarkan:
     *  - Nomor telepon Indonesia (08xx, +628xx, 628xx, 0812****789)
     *  - Alamat email (a***@domain.com)
     *  - Nomor kartu kredit / rekening 10-19 digit
     */
    public static function maskSensitiveData(string $text): string {
        // 1. Nomor telepon Indonesia (08xx, +628xx, 628xx)
        //    Pertahankan 4 digit awal + 4 digit akhir, samarkan tengahnya dengan minimal 4 bintang.
        $text = preg_replace_callback(
            '/(\+?62|0)([\s\-]?\d){6,12}/u',
            function (array $m) {
                $raw = preg_replace('/[\s\-]/', '', $m[0]);
                $len = mb_strlen($raw);
                if ($len <= 8) {
                    return $m[0];
                }
                $keep  = 4;
                $starCount = max(4, $len - $keep * 2);
                $stars = str_repeat('*', $starCount);
                return mb_substr($raw, 0, $keep) . $stars . mb_substr($raw, -$keep);
            },
            $text
        );

        // 2. Email address (a***@domain.com / visible + ****@)
        $text = preg_replace_callback(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/u',
            function (array $m) {
                $parts   = explode('@', $m[0]);
                $local   = $parts[0];
                $visible = mb_substr($local, 0, 1);
                return $visible . '****@' . ($parts[1] ?? 'xxx');
            },
            $text
        );

        // 3. Nomor kartu / rekening 10-19 digit
        $text = preg_replace_callback(
            '/\b(\d[\s\-]?){10,19}\b/u',
            function (array $m) {
                $raw = preg_replace('/[\s\-]/', '', $m[0]);
                $len = mb_strlen($raw);
                if ($len < 8) return $m[0];
                $starCount = max(4, $len - 8);
                return mb_substr($raw, 0, 4) . str_repeat('*', $starCount) . mb_substr($raw, -4);
            },
            $text
        );

        return $text;
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Simpan satu baris pesan (USER atau BOT) dengan masking otomatis.
     * Best-effort: jika gagal, catat ke log dan kembalikan null (tidak throw exception).
     *
     * @return int|null  ID baris baru, atau null jika gagal
     */
    public function logMessage(
        int     $userId,
        string  $userRole,
        string  $sessionId,
        string  $sender,
        string  $message,
        ?string $detectedIntent,
        string  $mode = 'MOCK',
        int     $isFallback = 0,
        ?string $toolUsed = null
    ): ?int {
        try {
            $maskedMessage = self::maskSensitiveData($message);
            $db = Database::getConnection();
            $stmt = $db->prepare(
                "INSERT INTO `chatbot_messages`
                 (`user_id`, `user_role`, `session_id`, `sender`,
                  `message`, `detected_intent`, `tool_used`, `is_fallback`, `mode`, `created_at`)
                 VALUES (:user_id, :user_role, :session_id, :sender,
                         :message, :detected_intent, :tool_used, :is_fallback, :mode, NOW())"
            );
            $stmt->execute([
                'user_id'         => $userId,
                'user_role'       => $userRole,
                'session_id'      => $sessionId,
                'sender'          => strtoupper($sender),
                'message'         => $maskedMessage,
                'detected_intent' => $detectedIntent,
                'tool_used'       => $toolUsed,
                'is_fallback'     => $isFallback ? 1 : 0,
                'mode'            => strtoupper($mode),
            ]);
            return (int)$db->lastInsertId();
        } catch (\Throwable $th) {
            // Best-effort: jangan ganggu flow chat jika logging DB gagal, catat error ke log
            error_log(sprintf(
                "[%s] Chatbot persist error for user #%d: %s\n",
                date('Y-m-d H:i:s'),
                $userId,
                $th->getMessage()
            ));
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Read – Admin monitoring
    // -------------------------------------------------------------------------

    /**
     * Daftar pesan dengan filter dan pagination untuk halaman monitoring.
     *
     * @param array  $filters  Kunci yang didukung:
     *                         date_from (Y-m-d), date_to (Y-m-d),
     *                         role (string), intent (string),
     *                         only_fallback (bool|int), keyword (string)
     * @param int    $page
     * @param int    $perPage
     * @return array  ['data'=>[], 'total'=>int, 'per_page'=>int,
     *                 'current_page'=>int, 'total_pages'=>int,
     *                 'has_next'=>bool, 'has_prev'=>bool]
     */
    public function getLogsForAdmin(array $filters = [], int $page = 1, int $perPage = 30): array {
        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilterWhere($filters);

        $db = Database::getConnection();

        // Count
        $cStmt = $db->prepare(
            "SELECT COUNT(*) AS total
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             {$where}"
        );
        $cStmt->execute($params);
        $total = (int)($cStmt->fetchColumn() ?: 0);

        // Data with optional bot_reply preview
        $dStmt = $db->prepare(
            "SELECT
                 cm.id, cm.user_id, cm.session_id, cm.sender,
                 cm.message, cm.detected_intent, cm.tool_used, cm.is_fallback, cm.mode,
                 cm.user_role, cm.user_role AS role_pengirim_user, cm.created_at,
                 u.name AS user_name, u.email AS user_email,
                 (
                     SELECT b.message 
                     FROM `chatbot_messages` b 
                     WHERE b.session_id = cm.session_id 
                       AND b.sender = 'BOT' 
                       AND b.id > cm.id 
                     ORDER BY b.id ASC 
                     LIMIT 1
                 ) AS bot_reply
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             {$where}
             ORDER BY cm.created_at DESC, cm.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $dStmt->execute($params);
        $data = $dStmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalPages = max(1, (int)ceil($total / $perPage));
        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'total_pages'  => $totalPages,
            'has_next'     => $page < $totalPages,
            'has_prev'     => $page > 1,
        ];
    }

    /**
     * Ringkasan statistik: total pesan, pengguna unik, 10 pertanyaan / intent paling sering,
     * serta jumlah dan daftar pertanyaan fallback.
     */
    public function getSummary(array $filters = []): array {
        [$where, $params] = $this->buildFilterWhere($filters);
        $db = Database::getConnection();

        // Total messages
        $s1 = $db->prepare(
            "SELECT COUNT(*) FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id {$where}"
        );
        $s1->execute($params);
        $totalMessages = (int)$s1->fetchColumn();

        // Unique users
        $s2 = $db->prepare(
            "SELECT COUNT(DISTINCT cm.user_id) FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id {$where}"
        );
        $s2->execute($params);
        $uniqueUsers = (int)$s2->fetchColumn();

        // Top 10 intents (hanya baris USER agar BOT tidak skew hitungan)
        $intentConnector = empty($where) ? 'WHERE' : 'AND';
        $s3 = $db->prepare(
            "SELECT cm.detected_intent AS intent, COUNT(*) AS cnt
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             {$where}
             {$intentConnector} cm.sender = 'USER' AND cm.detected_intent IS NOT NULL
             GROUP BY cm.detected_intent
             ORDER BY cnt DESC
             LIMIT 10"
        );
        $s3->execute($params);
        $topIntents = $s3->fetchAll(\PDO::FETCH_ASSOC);

        // Fallback count (is_fallback = 1 atau intent GENERAL / NULL pada USER)
        $s4 = $db->prepare(
            "SELECT COUNT(*) FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             {$where}
             {$intentConnector} cm.sender = 'USER'
             AND (cm.is_fallback = 1 OR cm.detected_intent = 'GENERAL' OR cm.detected_intent IS NULL)"
        );
        $s4->execute($params);
        $fallbackCount = (int)$s4->fetchColumn();

        // Top 10 Pertanyaan Paling Sering Ditanyakan Pengguna
        $s5 = $db->prepare(
            "SELECT cm.message, cm.detected_intent, COUNT(*) AS cnt
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             {$where}
             {$intentConnector} cm.sender = 'USER'
             GROUP BY cm.message, cm.detected_intent
             ORDER BY cnt DESC
             LIMIT 10"
        );
        $s5->execute($params);
        $topQuestions = $s5->fetchAll(\PDO::FETCH_ASSOC);

        // Daftar Pertanyaan Fallback (pertanyaan yang belum terjawab / tidak dikenali bot)
        $s6 = $db->prepare(
            "SELECT MAX(cm.id) AS id, cm.message, MAX(cm.user_id) AS user_id, cm.user_role,
                    MAX(cm.created_at) AS created_at, MAX(u.name) AS user_name,
                    COUNT(*) as occurrences
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             {$where}
             {$intentConnector} cm.sender = 'USER'
             AND (cm.is_fallback = 1 OR cm.detected_intent = 'GENERAL' OR cm.detected_intent IS NULL)
             GROUP BY cm.message, cm.user_role
             ORDER BY occurrences DESC, created_at DESC
             LIMIT 15"
        );
        $s6->execute($params);
        $fallbackQuestions = $s6->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total_messages'     => $totalMessages,
            'unique_users'       => $uniqueUsers,
            'top_intents'        => $topIntents,
            'fallback_count'     => $fallbackCount,
            'top_questions'      => $topQuestions,
            'fallback_questions' => $fallbackQuestions,
        ];
    }

    /**
     * Ambil seluruh percakapan dalam satu sesi berurutan (modal thread).
     */
    public function getSessionThread(string $sessionId): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT cm.*, cm.user_role AS role_pengirim_user, u.name AS user_name
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             WHERE cm.session_id = :session_id
             ORDER BY cm.id ASC"
        );
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ambil seluruh percakapan satu user berurutan (modal thread per-user).
     */
    public function getUserThread(int $userId, int $limit = 200): array {
        $db   = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT cm.*, cm.user_role AS role_pengirim_user, u.name AS user_name
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             WHERE cm.user_id = :user_id
             ORDER BY cm.id ASC
             LIMIT {$limit}"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ekspor CSV (data sudah masked di DB, aman langsung export).
     *
     * @return string  Konten CSV
     */
    public function exportCsv(array $filters = []): string {
        [$where, $params] = $this->buildFilterWhere($filters);
        $db   = Database::getConnection();
        $stmt = $db->prepare(
            "SELECT
                 cm.id, u.name AS user_name, u.email AS user_email,
                 cm.user_role, cm.session_id, cm.sender,
                 cm.message, cm.detected_intent, cm.tool_used, cm.is_fallback, cm.mode, cm.created_at
             FROM `chatbot_messages` cm
             JOIN `users` u ON cm.user_id = u.id
             {$where}
             ORDER BY cm.created_at DESC
             LIMIT 10000"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $headers = ['id','user_name','user_email','role','session_id','sender','message','intent','is_fallback','mode','created_at'];
        $out     = implode(',', $headers) . "\n";
        foreach ($rows as $row) {
            $out .= implode(',', [
                $row['id'],
                '"' . str_replace('"', '""', $row['user_name'])  . '"',
                '"' . str_replace('"', '""', $row['user_email']) . '"',
                $row['user_role'],
                $row['session_id'],
                $row['sender'],
                '"' . str_replace('"', '""', $row['message']) . '"',
                $row['detected_intent'] ?? '',
                $row['is_fallback'] ? '1' : '0',
                $row['mode'],
                $row['created_at'],
            ]) . "\n";
        }
        return $out;
    }

    /**
     * Hapus pesan lebih tua dari N hari (dipanggil oleh cron retensi).
     *
     * @return int  Jumlah baris yang dihapus
     */
    public function purgeOlderThan(int $days): int {
        $db   = Database::getConnection();
        $stmt = $db->prepare(
            "DELETE FROM `chatbot_messages`
             WHERE `created_at` < DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Bangun klausa WHERE dan array params dari filter array.
     *
     * @return array{0: string, 1: array}
     */
    private function buildFilterWhere(array $filters): array {
        $clauses = [];
        $params  = [];

        if (!empty($filters['date_from'])) {
            $clauses[] = 'cm.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'cm.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['role'])) {
            $clauses[] = 'cm.user_role = :role';
            $params['role'] = $filters['role'];
        }
        if (!empty($filters['intent'])) {
            $clauses[] = 'cm.detected_intent = :intent';
            $params['intent'] = $filters['intent'];
        }
        if (!empty($filters['only_fallback'])) {
            $clauses[] = '(cm.is_fallback = 1 OR cm.detected_intent = "GENERAL" OR cm.detected_intent IS NULL)';
        }
        if (!empty($filters['keyword'])) {
            $clauses[] = '(cm.message LIKE :keyword OR u.name LIKE :keyword_user)';
            $params['keyword'] = '%' . $filters['keyword'] . '%';
            $params['keyword_user'] = '%' . $filters['keyword'] . '%';
        }

        $where = !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }
}
