<?php
namespace App\Services;

class CryptoService {
    /**
     * Get the raw 32-byte encryption key from APP_KEY env
     */
    public static function getKey(): string {
        $key = env('APP_KEY', '');
        if (str_starts_with($key, 'base64:')) {
            $raw = base64_decode(substr($key, 7));
            if (strlen($raw) === 32) {
                return $raw;
            }
        } elseif (strlen($key) === 32) {
            return $key;
        }

        // Auto-generate if missing
        $genScript = (defined('ROOT_PATH') ? ROOT_PATH : dirname(dirname(__DIR__))) . '/database/generate_app_key.php';
        if (file_exists($genScript)) {
            exec('php ' . escapeshellarg($genScript));
            if (function_exists('load_env')) {
                load_env(defined('ROOT_PATH') ? ROOT_PATH . '/.env' : dirname(dirname(__DIR__)) . '/.env');
            }
            $key = env('APP_KEY', '');
            if (str_starts_with($key, 'base64:')) {
                $raw = base64_decode(substr($key, 7));
                if (strlen($raw) === 32) {
                    return $raw;
                }
            }
        }

        // Fallback SHA-256 derivation if somehow still empty
        return hash('sha256', $key ?: 'smartlaundry_secret_salt_fallback', true);
    }

    /**
     * Encrypt a string using AES-256-GCM
     * Output format: base64(iv(12) + tag(16) + ciphertext)
     */
    public static function encrypt(string $plaintext): string {
        if ($plaintext === '') {
            return '';
        }

        $key = self::getKey();
        $cipher = 'aes-256-gcm';
        $ivlen = openssl_cipher_iv_length($cipher); // 12 bytes for GCM
        $iv = random_bytes($ivlen);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a string using AES-256-GCM
     */
    public static function decrypt(string $payload): ?string {
        if ($payload === '') {
            return '';
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) < 28) {
            return null; // Invalid payload
        }

        $key = self::getKey();
        $cipher = 'aes-256-gcm';
        $ivlen = openssl_cipher_iv_length($cipher); // 12 bytes
        $iv = substr($decoded, 0, $ivlen);
        $tag = substr($decoded, $ivlen, 16);
        $ciphertext = substr($decoded, $ivlen + 16);

        $plaintext = openssl_decrypt(
            $ciphertext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            return null; // Decryption failed / tag mismatch
        }

        return $plaintext;
    }
}

