<?php
namespace App\Services\Email;

interface EmailGatewayInterface {
    /**
     * Send email message
     *
     * @param string $to Recipient email address
     * @param string $subject Subject line
     * @param string $htmlBody HTML content
     * @param string $textBody Plain text fallback content
     * @return bool True if sent successfully, false otherwise
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody): bool;
}

