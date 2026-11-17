<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Models\Setting;

class OtpCode extends Model {
    protected string $table = 'otp_codes';
    protected string $primaryKey = 'id';

    public const CHANNEL_WHATSAPP = 'WHATSAPP';
    public const CHANNEL_EMAIL    = 'EMAIL';

    public const PURPOSE_REGISTER     = 'REGISTER';
    public const PURPOSE_CHANGE_PHONE = 'CHANGE_PHONE';
    public const PURPOSE_PAYMENT      = 'PAYMENT';

    /**
     * Check rate limit: maximum N requests per hour per destination or IP
     */
    public function checkHourlyLimit(string $destination, ?string $ip = null): bool {
        $hourlyLimit = (int)Setting::get('otp_hourly_limit', 5);
        $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);

        // Check destination limit
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `{$this->table}` 
                                    WHERE `destination` = :dest AND `created_at` >= :one_hour");
        $stmt->execute(['dest' => $destination, 'one_hour' => $oneHourAgo]);
        if ((int)$stmt->fetchColumn() >= $hourlyLimit) {
            return false;
        }

        // Check IP limit if IP provided
        if (!empty($ip)) {
            $stmtIp = $this->db->prepare("SELECT COUNT(*) FROM `{$this->table}` 
                                          WHERE `ip_address` = :ip AND `created_at` >= :one_hour");
            $stmtIp->execute(['ip' => $ip, 'one_hour' => $oneHourAgo]);
            if ((int)$stmtIp->fetchColumn() >= ($hourlyLimit * 2)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if cooldown has elapsed before resending (default 60 seconds)
     */
    public function canResend(string $destination, string $purpose = self::PURPOSE_REGISTER, ?int $orderId = null): array {
        $cooldown = (int)Setting::get('otp_resend_interval_seconds', 60);

        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `destination` = :dest AND `purpose` = :purpose";
        $params = ['dest' => $destination, 'purpose' => $purpose];

        if ($orderId !== null && $orderId > 0) {
            try {
                $checkCol = $this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'order_id'")->fetch();
                if ($checkCol) {
                    $sql .= " AND `order_id` = :order_id";
                    $params['order_id'] = $orderId;
                }
            } catch (\Throwable $e) {}
        }

        $sql .= " ORDER BY `id` DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $latest = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$latest) {
            return ['allowed' => true, 'remaining_seconds' => 0];
        }

        $lastSentTime = strtotime($latest['last_sent_at'] ?? $latest['created_at']);
        $elapsed = time() - $lastSentTime;

        if ($elapsed < $cooldown) {
            return [
                'allowed' => false,
                'remaining_seconds' => ($cooldown - $elapsed),
                'message' => "Silakan tunggu " . ($cooldown - $elapsed) . " detik sebelum meminta kode baru."
            ];
        }

        return ['allowed' => true, 'remaining_seconds' => 0];
    }

    /**
     * Generate and persist a new 6-digit OTP code
     * Invalidates any previously active codes for this destination & purpose
     *
     * @return array [ 'code' => string, 'id' => int, 'expires_at' => string ]
     */
    public function generateOtp(
        ?int $userId,
        string $channel,
        string $destination,
        string $purpose = self::PURPOSE_REGISTER,
        ?string $ip = null,
        ?int $orderId = null
    ): array {
        // 1. Generate 6-digit random code
        $code = (string)random_int(100000, 999999);
        $codeHash = password_hash($code, PASSWORD_BCRYPT);

        $expiryMinutes = (int)Setting::get('otp_expiry_minutes', 5);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
        $now = date('Y-m-d H:i:s');
        $maxAttempts = (int)Setting::get('otp_max_attempts', 5);

        // 2. Invalidate previous active unverified codes
        try {
            $hasOrderIdCol = (bool)$this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'order_id'")->fetch();
        } catch (\Throwable $e) {
            $hasOrderIdCol = false;
        }

        $invSql = "UPDATE `{$this->table}` SET `expires_at` = NOW() 
                   WHERE `destination` = :dest AND `purpose` = :purpose AND `verified_at` IS NULL";
        $invParams = ['dest' => $destination, 'purpose' => $purpose];
        if ($orderId !== null && $hasOrderIdCol) {
            $invSql .= " AND (`order_id` = :order_id OR `order_id` IS NULL)";
            $invParams['order_id'] = $orderId;
        }
        $invalidateStmt = $this->db->prepare($invSql);
        $invalidateStmt->execute($invParams);

        // 3. Insert new OTP
        if ($orderId !== null && $hasOrderIdCol) {
            $insertStmt = $this->db->prepare(
                "INSERT INTO `{$this->table}` 
                 (`user_id`, `order_id`, `channel`, `destination`, `purpose`, `code_hash`, `expires_at`, `attempts`, `max_attempts`, `resend_count`, `last_sent_at`, `created_at`, `ip_address`)
                 VALUES (:uid, :order_id, :channel, :dest, :purpose, :code_hash, :expires_at, 0, :max_attempts, 0, :last_sent, :created, :ip)"
            );
            $insertStmt->execute([
                'uid'          => $userId,
                'order_id'     => $orderId,
                'channel'      => $channel,
                'dest'         => $destination,
                'purpose'      => $purpose,
                'code_hash'    => $codeHash,
                'expires_at'   => $expiresAt,
                'max_attempts' => $maxAttempts,
                'last_sent'    => $now,
                'created'      => $now,
                'ip'           => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
            ]);
        } else {
            $insertStmt = $this->db->prepare(
                "INSERT INTO `{$this->table}` 
                 (`user_id`, `channel`, `destination`, `purpose`, `code_hash`, `expires_at`, `attempts`, `max_attempts`, `resend_count`, `last_sent_at`, `created_at`, `ip_address`)
                 VALUES (:uid, :channel, :dest, :purpose, :code_hash, :expires_at, 0, :max_attempts, 0, :last_sent, :created, :ip)"
            );
            $insertStmt->execute([
                'uid'          => $userId,
                'channel'      => $channel,
                'dest'         => $destination,
                'purpose'      => $purpose,
                'code_hash'    => $codeHash,
                'expires_at'   => $expiresAt,
                'max_attempts' => $maxAttempts,
                'last_sent'    => $now,
                'created'      => $now,
                'ip'           => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
            ]);
        }
        $otpId = (int)$this->db->lastInsertId();

        // 4. Safe logging for local development only (never in production)
        $appEnv = strtolower((string)env('APP_ENV', 'local'));
        if ($appEnv === 'local' || $appEnv === 'development') {
            $logDir = ROOT_PATH . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $entry = sprintf(
                "[%s] [ENV: %s] [OTP %s -> %s] (Order: %s) CODE: %s (Expires: %s)\n",
                date('Y-m-d H:i:s'),
                $appEnv,
                $channel,
                $destination,
                $orderId ?? '-',
                $code,
                $expiresAt
            );
            @file_put_contents($logDir . '/otp_development.log', $entry, FILE_APPEND);
        }

        return [
            'id' => $otpId,
            'code' => $code, // returned in-memory only to caller for immediate transmission
            'expires_at' => $expiresAt,
            'expiry_minutes' => $expiryMinutes
        ];
    }

    /**
     * Verify submitted code against hashed database record
     */
    public function verifyOtp(
        string $destination,
        string $inputCode,
        string $purpose = self::PURPOSE_REGISTER,
        ?string $channel = null,
        ?int $orderId = null
    ): array {
        $cleanCode = trim($inputCode);

        $sql = "SELECT * FROM `{$this->table}` 
                WHERE `destination` = :dest 
                  AND `purpose` = :purpose 
                  AND `verified_at` IS NULL";
        $params = ['dest' => $destination, 'purpose' => $purpose];

        if (!empty($channel)) {
            $sql .= " AND `channel` = :channel";
            $params['channel'] = $channel;
        }

        if ($orderId !== null && $orderId > 0) {
            try {
                $checkCol = $this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'order_id'")->fetch();
                if ($checkCol) {
                    $sql .= " AND (`order_id` = :order_id OR `order_id` IS NULL)";
                    $params['order_id'] = $orderId;
                }
            } catch (\Throwable $e) {}
        }

        $sql .= " ORDER BY `id` DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $otp = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$otp) {
            return [
                'success' => false,
                'message' => 'Kode verifikasi tidak ditemukan atau sudah pernah digunakan. Silakan minta kode baru.'
            ];
        }

        $id = (int)$otp['id'];
        $attempts = (int)$otp['attempts'];
        $maxAttempts = (int)$otp['max_attempts'];

        // 1. Check if locked due to maximum failed attempts
        if ($attempts >= $maxAttempts) {
            return [
                'success' => false,
                'message' => 'Kode OTP ini telah terkunci karena salah 5 kali. Silakan minta kode baru.'
            ];
        }

        // 2. Check if expired
        if (time() > strtotime($otp['expires_at'])) {
            return [
                'success' => false,
                'message' => 'Kode OTP telah kedaluwarsa (lebih dari 5 menit). Silakan kirim ulang kode baru.'
            ];
        }

        // 3. Verify code hash using password_verify
        if (!password_verify($cleanCode, $otp['code_hash'])) {
            $newAttempts = $attempts + 1;
            $updateStmt = $this->db->prepare("UPDATE `{$this->table}` SET `attempts` = :att WHERE `id` = :id");
            $updateStmt->execute(['att' => $newAttempts, 'id' => $id]);

            $remaining = $maxAttempts - $newAttempts;
            if ($remaining <= 0) {
                return [
                    'success' => false,
                    'message' => 'Kode OTP salah. Batas percobaan habis, kode telah dikunci. Silakan minta kode baru.'
                ];
            }
            return [
                'success' => false,
                'message' => "Kode verifikasi salah. Sisa percobaan: {$remaining} kali."
            ];
        }

        // 4. Mark verified & consumed
        $markStmt = $this->db->prepare("UPDATE `{$this->table}` SET `verified_at` = NOW() WHERE `id` = :id");
        $markStmt->execute(['id' => $id]);

        return [
            'success' => true,
            'message' => 'Kode verifikasi berhasil divalidasi.',
            'otp' => $otp
        ];
    }

    /**
     * Clean up expired unverified OTP codes
     */
    public function cleanupExpired(int $hours = 24): int {
        $threshold = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $stmt = $this->db->prepare("DELETE FROM `{$this->table}` WHERE `created_at` < :thresh");
        $stmt->execute(['thresh' => $threshold]);
        return $stmt->rowCount();
    }
}

