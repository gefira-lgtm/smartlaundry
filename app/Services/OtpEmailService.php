<?php
namespace App\Services;

use App\Services\Email\EmailGatewayFactory;
use App\Models\Setting;

class OtpEmailService {
    
    public function sendOtpEmail(string $to, string $recipientName, string $otpCode, int $expiryMinutes = 5): bool {
        try {
            $gateway = EmailGatewayFactory::create();
            $appName = 'SmartLaundry';
            
            $subject = "[{$appName}] Kode Verifikasi Akun Anda";
            
            $htmlBody = $this->buildHtmlTemplate($recipientName, $otpCode, $expiryMinutes, $appName);
            $textBody = $this->buildTextTemplate($recipientName, $otpCode, $expiryMinutes, $appName);
            
            return $gateway->send($to, $subject, $htmlBody, $textBody);
        } catch (\Throwable $e) {
            error_log("[OtpEmailService] Error sending OTP email to {$to}: " . $e->getMessage());
            return false;
        }
    }
    
    private function buildHtmlTemplate(string $name, string $code, int $expiryMinutes, string $appName): string {
        // HTML-encode the name to prevent XSS
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        
        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Akun {$appName}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:30px 0;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
      <tr><td style="background:#1565C0;padding:24px 32px;border-radius:8px 8px 0 0;">
        <h1 style="color:#ffffff;margin:0;font-size:22px;">{$appName}</h1>
        <p style="color:#bbdefb;margin:4px 0 0;font-size:13px;">Layanan Laundry Terpercaya</p>
      </td></tr>
      <tr><td style="padding:32px;">
        <p style="color:#333;font-size:15px;margin:0 0 16px;">Halo, <strong>{$safeName}</strong>,</p>
        <p style="color:#555;font-size:14px;line-height:1.6;margin:0 0 24px;">
          Kami menerima permintaan verifikasi akun Anda. Gunakan kode berikut untuk menyelesaikan proses pendaftaran:
        </p>
        <div style="text-align:center;margin:24px 0;">
          <div style="display:inline-block;background:#e3f2fd;border:2px solid #1565C0;border-radius:8px;padding:16px 40px;">
            <span style="font-size:36px;font-weight:bold;color:#1565C0;letter-spacing:8px;">{$safeCode}</span>
          </div>
        </div>
        <p style="color:#e53935;font-size:13px;text-align:center;margin:0 0 24px;">
          ⏱️ Kode ini berlaku selama <strong>{$expiryMinutes} menit</strong>
        </p>
        <div style="background:#fff8e1;border-left:4px solid #f57f17;padding:12px 16px;border-radius:4px;margin:0 0 24px;">
          <p style="color:#6d4c41;font-size:13px;margin:0;">
            <strong>⚠️ Penting:</strong> Jangan pernah bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai tim {$appName}. Kami tidak akan pernah meminta kode OTP Anda.
          </p>
        </div>
        <p style="color:#777;font-size:12px;margin:0;">
          Jika Anda tidak merasa mendaftar di {$appName}, abaikan email ini.
        </p>
      </td></tr>
      <tr><td style="background:#f8f9fa;padding:16px 32px;border-radius:0 0 8px 8px;border-top:1px solid #eee;">
        <p style="color:#999;font-size:11px;margin:0;text-align:center;">
          &copy; {$appName} — Layanan Laundry Terpercaya | Email ini dikirim secara otomatis, harap tidak membalas.
        </p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }
    
    private function buildTextTemplate(string $name, string $code, int $expiryMinutes, string $appName): string {
        return "{$appName} - Kode Verifikasi Akun\n\nHalo, {$name},\n\nKode verifikasi Anda adalah: {$code}\n\nKode ini berlaku selama {$expiryMinutes} menit.\n\nJangan pernah bagikan kode ini kepada siapa pun.\n\nTim {$appName}";
    }
}
