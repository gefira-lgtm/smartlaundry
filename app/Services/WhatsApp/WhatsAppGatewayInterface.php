<?php
namespace App\Services\WhatsApp;

interface WhatsAppGatewayInterface {
    /**
     * Send outbound WhatsApp message
     *
     * @param string $phone Destination phone number in normalized 628xxx format
     * @param string $message Text message to deliver
     * @return array [ 'success' => bool, 'status' => string ('SENT'|'SIMULATED'|'FAILED'), 'error' => ?string, 'response' => mixed ]
     */
    public function send(string $phone, string $message): array;
}

