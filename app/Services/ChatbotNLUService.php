<?php
namespace App\Services;

/**
 * ChatbotNLUService — Natural Language Understanding helper for chatbot.
 *
 * Extracts structured entities (amounts, dates, periods, intents) from
 * Indonesian-language user messages. Strictly stateless and read-only.
 * NO database access.
 */
class ChatbotNLUService
{
    // =========================================================================
    // Amount Normalization
    // =========================================================================

    /**
     * Normalize an Indonesian amount string to float.
     *
     * Examples:
     *   "500.000"       → 500000
     *   "500,000"       → 500000
     *   "500000"        → 500000
     *   "500rb"         → 500000
     *   "500 ribu"      → 500000
     *   "500k"          → 500000
     *   "setengah juta" → 500000
     *   "1,5 juta"      → 1500000
     *   "Rp 500.000"    → 500000
     *   "1.500.000"     → 1500000
     */
    public static function normalizeAmount(string $text): ?float
    {
        $text = trim($text);

        // Remove "Rp", "rp", currency prefix + optional dot/space
        $text = preg_replace('/^Rp\.?\s*/i', '', $text);
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        // "setengah juta" → 500000
        if (preg_match('/^setengah\s+juta$/i', $text)) {
            return 500000.0;
        }

        // Number + "juta" (e.g. "1,5 juta", "2 juta", "10 juta")
        if (preg_match('/^([\d.,]+)\s*juta$/i', $text, $m)) {
            $num = self::parseDecimalString($m[1]);
            return $num !== null ? round($num * 1_000_000, 2) : null;
        }

        // Number + "ribu" | "rb" | "k" (e.g. "500 ribu", "500rb", "500k")
        if (preg_match('/^([\d.,]+)\s*(ribu|rb|k)$/i', $text, $m)) {
            $num = self::parseDecimalString($m[1]);
            return $num !== null ? round($num * 1_000, 2) : null;
        }

        // Plain number (Indonesian style: dots as thousand separators)
        return self::parseDecimalString($text);
    }

    /**
     * Extract the first amount mentioned in a natural-language message.
     * Returns null if no recognizable amount found.
     */
    public static function extractAmountFromMessage(string $message): ?float
    {
        // 1. "setengah juta"
        if (preg_match('/\bsetengah\s+juta\b/i', $message)) {
            return 500000.0;
        }

        // 2. "Rp 500.000" or "Rp500.000" with optional multiplier suffix
        if (preg_match('/Rp\.?\s*([\d.,]+)\s*(juta|ribu|rb|k)?/i', $message, $m)) {
            $amtStr = $m[1] . (isset($m[2]) && $m[2] !== '' ? ' ' . $m[2] : '');
            $result = self::normalizeAmount($amtStr);
            if ($result !== null && $result > 0) {
                return $result;
            }
        }

        // 3. Number + multiplier word (e.g. "500 ribu", "1.5 juta", "500rb")
        if (preg_match('/([\d.,]+)\s*(juta|ribu|rb|k)\b/i', $message, $m)) {
            $amtStr = $m[1] . ' ' . $m[2];
            $result = self::normalizeAmount($amtStr);
            if ($result !== null && $result > 0) {
                return $result;
            }
        }

        // 4. Number with Indonesian thousand dots: "500.000" or "1.500.000"
        if (preg_match('/\b(\d{1,3}(?:\.\d{3})+)\b/', $message, $m)) {
            $result = self::normalizeAmount($m[1]);
            if ($result !== null && $result > 0) {
                return $result;
            }
        }

        // 5. Number with comma thousands: "500,000"
        if (preg_match('/\b(\d{1,3}(?:,\d{3})+)\b/', $message, $m)) {
            $result = self::normalizeAmount($m[1]);
            if ($result !== null && $result > 0) {
                return $result;
            }
        }

        // 6. Plain number ≥ 1000 (avoids matching page numbers / quantities)
        if (preg_match('/\b(\d{4,})\b/', $message, $m)) {
            $result = self::normalizeAmount($m[1]);
            if ($result !== null && $result > 0) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Format a float amount back to Indonesian currency string.
     */
    public static function formatRupiah(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    // =========================================================================
    // Order Code Extraction
    // =========================================================================

    /**
     * Extract an order code (LND-XXXXXX) from a message.
     */
    public static function extractOrderCode(string $message): ?string
    {
        if (preg_match('/\b(LND-\d{4,})\b/i', $message, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    // =========================================================================
    // Period / Date Extraction
    // =========================================================================

    /**
     * Extract a period keyword from a message.
     *
     * Returns one of:
     *   'hari_ini', 'kemarin', 'minggu_ini', 'bulan_ini', 'bulan_lalu',
     *   'tahun_ini', '7_hari', '30_hari'
     * or null if no period found.
     */
    public static function extractPeriod(string $message): ?string
    {
        $msg = mb_strtolower($message, 'UTF-8');

        $periodMap = [
            'hari_ini'   => ['hari ini', 'today', 'sekarang', 'hari ini'],
            'kemarin'    => ['kemarin', 'yesterday'],
            'minggu_ini' => ['minggu ini', 'pekan ini', 'this week', 'minggu ini'],
            'bulan_ini'  => ['bulan ini', 'this month'],
            'bulan_lalu' => ['bulan lalu', 'bulan kemarin', 'last month', 'bulan sebelumnya'],
            'tahun_ini'  => ['tahun ini', 'this year'],
            '7_hari'     => ['7 hari', '7hari', 'tujuh hari', 'seminggu terakhir', '7 hari terakhir'],
            '30_hari'    => ['30 hari', '30hari', 'tiga puluh hari', 'sebulan terakhir', '30 hari terakhir'],
        ];

        foreach ($periodMap as $key => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($msg, $phrase)) {
                    return $key;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // Expense Category Extraction
    // =========================================================================

    /**
     * Detect expense category keyword from message.
     * Returns one of: BAHAN_BAKU, KEMASAN, OPERASIONAL, LAINNYA, or null.
     */
    public static function extractExpenseCategory(string $message): ?string
    {
        $msg = mb_strtolower($message, 'UTF-8');

        $map = [
            'BAHAN_BAKU'  => ['bahan baku', 'deterjen', 'sabun cuci', 'pewangi', 'pelembut', 'bahan', 'raw material'],
            'KEMASAN'     => ['kemasan', 'plastik', 'kantong', 'packaging', 'bungkus', 'karton'],
            'OPERASIONAL' => ['operasional', 'listrik', 'air', 'sewa', 'gaji', 'upah', 'bensin', 'transport', 'utilitas'],
            'LAINNYA'     => ['lainnya', 'lain-lain', 'lain', 'other', 'miscellaneous'],
        ];

        foreach ($map as $cat => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($msg, $kw)) {
                    return $cat;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // Intent Detection
    // =========================================================================

    /**
     * Detect expense-related intent from message.
     * Returns: 'EXPENSE_LOOKUP', 'EXPENSE_AGGREGATE', 'PAYMENT_CROSS', or null.
     */
    public static function detectExpenseIntent(string $message, string $role): ?string
    {
        $allowedRoles = ['owner_toko', 'admin_toko', 'owner_platform', 'admin_owner'];
        if (!in_array($role, $allowedRoles, true)) {
            return null;
        }

        $msg = mb_strtolower($message, 'UTF-8');

        // Expense lookup: "pengeluaran 500.000 digunakan untuk apa"
        $hasExpenseKw = (bool)preg_match('/\b(pengeluaran|biaya|belanja|expense|beli|bahan baku)\b/', $msg);
        $hasUsageKw   = (bool)preg_match('/\b(digunakan|untuk apa|dibeli|dari siapa|ini apa|itu apa|buat apa|keperluan)\b/', $msg);
        $hasAmount    = self::extractAmountFromMessage($message) !== null;

        if ($hasExpenseKw && ($hasUsageKw || $hasAmount)) {
            return 'EXPENSE_LOOKUP';
        }

        // Expense aggregate: "total pengeluaran bulan ini"
        if (preg_match('/\b(total pengeluaran|rekap biaya|laporan pengeluaran|pengeluaran bulan|pengeluaran hari|pengeluaran minggu|pengeluaran tahun|ringkasan pengeluaran|rangkuman biaya)\b/', $msg)) {
            return 'EXPENSE_AGGREGATE';
        }

        // Payment cross-table: "pembayaran Rp X itu order apa"
        if (preg_match('/\b(pembayaran|bayar)\b.*\b(order|pesanan|itu|ini|dari|berapa)\b/i', $msg)
            || preg_match('/\b(order|pesanan)\b.*\b(pembayaran|bayar|lunas)\b/i', $msg)) {
            return 'PAYMENT_CROSS';
        }

        return null;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Parse a decimal string that may use dot or comma as thousand/decimal separator.
     *
     * Indonesian convention: dots as thousand separators (500.000 = 500000).
     * European: 1.500,50 = 1500.50.
     * US: 1,500.50 = 1500.50.
     */
    public static function parseDecimalString(string $str): ?float
    {
        $str = trim($str);
        if ($str === '') {
            return null;
        }

        $dotCount   = substr_count($str, '.');
        $commaCount = substr_count($str, ',');

        // No separators: plain number
        if ($dotCount === 0 && $commaCount === 0) {
            return is_numeric($str) ? (float)$str : null;
        }

        // Only dots: Indonesian thousands "500.000" or "1.500.000"
        if ($dotCount > 0 && $commaCount === 0) {
            $parts = explode('.', $str);
            $last  = end($parts);
            // If last segment is exactly 3 digits → thousand separator
            if (strlen($last) === 3 && count($parts) >= 2) {
                $clean = str_replace('.', '', $str);
                return is_numeric($clean) ? (float)$clean : null;
            }
            // Otherwise: dot is decimal separator (e.g. "1.5")
            return is_numeric($str) ? (float)$str : null;
        }

        // Only commas
        if ($commaCount > 0 && $dotCount === 0) {
            $parts = explode(',', $str);
            $last  = end($parts);
            // Comma as thousand separator: "500,000"
            if (strlen($last) === 3 && count($parts) >= 2) {
                $clean = str_replace(',', '', $str);
                return is_numeric($clean) ? (float)$clean : null;
            }
            // Comma as decimal: "500,5"
            $clean = str_replace(',', '.', $str);
            return is_numeric($clean) ? (float)$clean : null;
        }

        // Both dot and comma: determine which is decimal by last occurrence
        $dotPos   = strrpos($str, '.');
        $commaPos = strrpos($str, ',');

        if ($commaPos > $dotPos) {
            // European: "1.500,50" → comma is decimal
            $clean = str_replace('.', '', $str);
            $clean = str_replace(',', '.', $clean);
        } else {
            // US: "1,500.50" → dot is decimal
            $clean = str_replace(',', '', $str);
        }

        return is_numeric($clean) ? (float)$clean : null;
    }

    /**
     * Dapatkan kata sapaan waktu Indonesia sesuai jam Asia/Jakarta.
     * Pagi: 06:00 - 11:59 (06:00 - 12:00)
     * Siang: 12:00 - 14:59 (12:00 - 15:00)
     * Sore: 15:00 - 17:59 (15:00 - 18:00)
     * Malam: 18:00 - 05:59 (18:00 - 24:00 / 06:00)
     */
    public static function getTimeGreetingWord(?\DateTimeInterface $date = null): string
    {
        $tz = new \DateTimeZone('Asia/Jakarta');
        $now = $date ?? new \DateTime('now', $tz);
        $hour = (int)$now->format('G');

        if ($hour >= 6 && $hour < 12) {
            return 'Selamat pagi';
        } elseif ($hour >= 12 && $hour < 15) {
            return 'Selamat siang';
        } elseif ($hour >= 15 && $hour < 18) {
            return 'Selamat sore';
        } else {
            return 'Selamat malam';
        }
    }

    /**
     * Generate an appropriate Indonesian time-based greeting for Asia/Jakarta timezone.
     * Pagi: 06:00-11:59, Siang: 12:00-14:59, Sore: 15:00-17:59, Malam: 18:00-05:59.
     * Incorporates user's real name without gender assumptions; avoids "Anda".
     */
    public static function getTimeBasedGreeting(?string $userName = null, ?\DateTimeInterface $date = null): string
    {
        $greet = self::getTimeGreetingWord($date);
        $name = trim((string)$userName);
        if ($name !== '' && strtolower($name) !== 'anda' && strtolower($name) !== 'null') {
            return "{$greet}, {$name}!";
        }

        return "{$greet}!";
    }
}

