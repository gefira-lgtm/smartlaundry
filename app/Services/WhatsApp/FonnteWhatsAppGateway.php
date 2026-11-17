<?php
namespace App\Services\WhatsApp;

class FonnteWhatsAppGateway implements WhatsAppGatewayInterface {
    private string $token;
    private string $apiUrl;
    private string $deviceUrl;
    private int $timeout;
    private HttpTransportInterface $transport;

    public function __construct(
        string $token,
        ?HttpTransportInterface $transport = null,
        ?string $apiUrl = null,
        int $timeout = 10,
        ?string $deviceUrl = null
    ) {
        $this->token = trim($token);
        $this->transport = $transport ?? new CurlHttpTransport();
        $this->apiUrl = !empty($apiUrl) ? trim($apiUrl) : 'https://api.fonnte.com/send';
        $this->deviceUrl = !empty($deviceUrl) ? trim($deviceUrl) : 'https://api.fonnte.com/device';
        $this->timeout = $timeout;
    }

    /**
     * Send WhatsApp message via Fonnte API
     * 
     * Docs: docs.fonnte.com
     * - Endpoint: https://api.fonnte.com/send
     * - Header: Authorization: {TOKEN} (no "Bearer")
     * - Form fields: target, message, countryCode, delay
     * - Success response: {"status": true, "id": "...", "detail": "..."}
     * - Failure response: {"status": false, "reason": "..."}
     */
    public function send(string $phone, string $message): array {
        if (empty($this->token)) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'FONNTE_TOKEN belum diisi',
                'provider_message_id' => null,
                'response' => null
            ];
        }

        $payload = [
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62',
            'delay' => '2'
        ];

        $headers = [
            'Authorization: ' . $this->token
        ];

        $res = $this->transport->post($this->apiUrl, $payload, $headers, $this->timeout);

        if (!empty($res['error'])) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'cURL Error: ' . $res['error'],
                'provider_message_id' => null,
                'response' => null
            ];
        }

        $httpCode = $res['http_code'];
        $body = $res['body'];
        $decoded = json_decode((string)$body, true);

        // Strict validation: HTTP 401 or token rejected
        if ($httpCode === 401 || (is_array($decoded) && isset($decoded['reason']) && stripos($decoded['reason'], 'token') !== false)) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'error' => 'token ditolak: ' . ($decoded['reason'] ?? 'Unauthorized'),
                'provider_message_id' => null,
                'response' => $decoded
            ];
        }

        // Strict Fonnte success: status must be strictly boolean true
        $isSuccess = ($httpCode >= 200 && $httpCode < 300) 
            && is_array($decoded) 
            && isset($decoded['status']) 
            && ($decoded['status'] === true);

        if ($isSuccess) {
            $providerMessageId = null;
            if (!empty($decoded['id'])) {
                $providerMessageId = is_array($decoded['id']) ? implode(',', $decoded['id']) : (string)$decoded['id'];
            }

            return [
                'success' => true,
                'status' => 'SENT',
                'error' => null,
                'provider_message_id' => $providerMessageId,
                'response' => $decoded
            ];
        }

        $reason = 'Gagal mengirim pesan';
        if (is_array($decoded) && !empty($decoded['reason'])) {
            $reason = (string)$decoded['reason'];
        } elseif (is_array($decoded) && !empty($decoded['detail'])) {
            $reason = (string)$decoded['detail'];
        } elseif (is_array($decoded) && !empty($decoded['message'])) {
            $reason = (string)$decoded['message'];
        } elseif ($httpCode !== 200) {
            $reason = "HTTP {$httpCode}: " . substr((string)$body, 0, 100);
        }

        return [
            'success' => false,
            'status' => 'FAILED',
            'error' => $reason,
            'provider_message_id' => null,
            'response' => $decoded
        ];
    }

    /**
     * Check Fonnte device connection status
     * Docs: GET https://api.fonnte.com/device
     * Headers: Authorization: {token}
     */
    public function checkDevice(): array {
        if (empty($this->token)) {
            return [
                'connected' => false,
                'status' => 'TOKEN_MISSING',
                'detail' => 'FONNTE_TOKEN belum diisi'
            ];
        }

        $headers = [
            'Authorization: ' . $this->token
        ];

        $res = $this->transport->post($this->deviceUrl, [], $headers, 5);
        if (!empty($res['error'])) {
            return [
                'connected' => false,
                'status' => 'ERROR',
                'detail' => $res['error']
            ];
        }

        $decoded = json_decode((string)$res['body'], true);
        if (is_array($decoded) && !empty($decoded['status']) && $decoded['status'] === true) {
            $deviceStatus = $decoded['device_status'] ?? ($decoded['status_device'] ?? 'connected');
            $devicePhone = $decoded['device'] ?? '';
            return [
                'connected' => true,
                'status' => $deviceStatus,
                'device' => $devicePhone,
                'raw' => $decoded
            ];
        }

        return [
            'connected' => false,
            'status' => 'DISCONNECTED',
            'detail' => $decoded['reason'] ?? ($decoded['message'] ?? 'Device tidak terhubung')
        ];
    }
}
