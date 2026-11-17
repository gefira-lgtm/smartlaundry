<?php
namespace App\Services\Payment;

class MidtransPaymentGateway implements PaymentGatewayInterface {
    
    private string $serverKey;
    private bool $isProduction;
    private string $baseUrl;

    public function __construct(string $serverKey, bool $isProduction = false) {
        $this->serverKey = $serverKey;
        $this->isProduction = $isProduction;
        $this->baseUrl = $isProduction ? 'https://api.midtrans.com/v2' : 'https://api.sandbox.midtrans.com/v2';
    }

    public function createTransaction(array $orderData): array {
        if (empty($this->serverKey)) {
            throw new \Exception("Midtrans Server Key is not configured.");
        }

        $orderId = (int)$orderData['id'];
        $amount = (float)$orderData['total_price'];
        
        // Prepare Midtrans Core API payload for QRIS
        $transactionId = 'MD_QRIS_' . $orderId . '_' . time();

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $transactionId,
                'gross_amount' => $amount
            ],
            'custom_expiry' => [
                'expiry_duration' => 60,
                'unit' => 'minute'
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/charge');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->serverKey . ':')
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Midtrans cURL Error: " . $error_msg);
        }
        
        curl_close($ch);
        $result = json_decode($response, true);

        if (isset($result['status_code']) && $result['status_code'] == '201') {
            $qrisImageUrl = null;
            // Extract QR code URL from actions if available
            if (isset($result['actions']) && is_array($result['actions'])) {
                foreach ($result['actions'] as $action) {
                    if ($action['name'] === 'generate-qr-code') {
                        $qrisImageUrl = $action['url'];
                        break;
                    }
                }
            }

            return [
                'transaction_id' => $result['transaction_id'] ?? $transactionId, // Store actual midtrans transaction_id if present
                'qris_payload' => $result['qr_string'] ?? '',
                'qris_image_url' => $qrisImageUrl,
                'order_id_ref' => $transactionId // The custom order_id we sent
            ];
        }

        // Log the full response for debugging if it fails
        error_log("Midtrans createTransaction failed: " . $response);
        throw new \Exception("Failed to create Midtrans QRIS transaction: " . ($result['status_message'] ?? 'Unknown Error'));
    }

    public function checkStatus(string $transactionId): string {
        if (empty($this->serverKey)) {
            return 'PENDING';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/' . $transactionId . '/status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $headers = [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->serverKey . ':')
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);

        if (isset($result['transaction_status'])) {
            return $this->mapMidtransStatus($result['transaction_status']);
        }
        
        return 'PENDING';
    }

    public function handleWebhook(array $payload): array {
        // Validation: Verify signature key
        if (!isset($payload['signature_key'], $payload['order_id'], $payload['status_code'], $payload['gross_amount'])) {
            return ['status' => 'IGNORE', 'message' => 'Invalid webhook payload structure.'];
        }

        $orderId = $payload['order_id'];
        $statusCode = $payload['status_code'];
        $grossAmount = $payload['gross_amount'];
        $serverKey = $this->serverKey;

        $calculatedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($calculatedSignature !== $payload['signature_key']) {
            return ['status' => 'INVALID_SIGNATURE', 'message' => 'Signature key mismatch.'];
        }

        $transactionStatus = $payload['transaction_status'];
        $fraudStatus = $payload['fraud_status'] ?? null;
        $mappedStatus = $this->mapMidtransStatus($transactionStatus, $fraudStatus);
        
        // Extract original RPL order ID from the custom order_id sent (MD_QRIS_{orderId}_{time} or numeric)
        $internalOrderId = null;
        if (preg_match('/MD_QRIS_(\d+)/i', $orderId, $matches)) {
            $internalOrderId = (int)$matches[1];
        } elseif (is_numeric($orderId)) {
            $internalOrderId = (int)$orderId;
        }

        return [
            'status' => $mappedStatus,
            'order_id' => $internalOrderId,
            'transaction_id' => $payload['transaction_id'] ?? $orderId,
            'gross_amount' => (float)$grossAmount,
            'message' => 'Webhook validated and mapped.'
        ];
    }

    private function mapMidtransStatus(string $status, ?string $fraudStatus = null): string {
        switch (strtolower($status)) {
            case 'capture':
                return ($fraudStatus === null || strtolower($fraudStatus) === 'accept') ? 'PAID' : 'PENDING';
            case 'settlement':
                return 'PAID';
            case 'pending':
                return 'PENDING';
            case 'deny':
            case 'cancel':
            case 'expire':
            case 'failure':
                return 'EXPIRED'; // We treat failures/expiries as EXPIRED
            default:
                return 'PENDING';
        }
    }
}
