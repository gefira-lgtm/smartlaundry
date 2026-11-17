<?php
namespace App\Services\Payment;

class SimulatedPaymentGateway implements PaymentGatewayInterface {
    
    /**
     * Generate standard QRIS EMVCo payload string
     */
    private function generateQrisPayload(int $orderId, float $amount): string {
        $storeIdFormatted = str_pad((string)$orderId, 6, '0', STR_PAD_LEFT);
        $amountInt = (int)$amount;
        $amountStr = (string)$amountInt;
        $amountLen = str_pad((string)strlen($amountStr), 2, '0', STR_PAD_LEFT);

        // Standard EMVCo QRIS Payload specification (Indonesian Standard)
        return "00020101021226580016ID.CO.SMARTLAUNDRY01189360091100{$storeIdFormatted}52045812530336054{$amountLen}{$amountStr}5802ID5914SMART LAUNDRY6007JAKARTA61051273062150711INV-{$orderId}6304" . strtoupper(substr(md5((string)$orderId . (string)$amount), 0, 4));
    }

    /**
     * Generate dynamic QR code image URL (using public QR API or SVG fallback)
     */
    private function generateQrisQrUrl(string $payload, int $size = 280): string {
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&margin=10&data=" . urlencode($payload);
    }

    public function createTransaction(array $orderData): array {
        $orderId = (int)$orderData['id'];
        $amount = (float)$orderData['total_price'];
        
        $payload = $this->generateQrisPayload($orderId, $amount);
        $imageUrl = $this->generateQrisQrUrl($payload);
        $transactionId = 'SIM_QRIS_' . date('YmdHis') . '_' . mt_rand(1000, 9999);

        return [
            'transaction_id' => $transactionId,
            'qris_payload' => $payload,
            'qris_image_url' => $imageUrl
        ];
    }

    public function checkStatus(string $transactionId): string {
        // Since it's simulated, we cannot actually poll a real bank.
        // The mock logic relies on manual trigger from the frontend/admin.
        return 'PENDING';
    }

    public function handleWebhook(array $payload): array {
        // Simulated gateway doesn't typically receive real webhooks.
        // Return dummy implementation.
        return [
            'status' => 'IGNORE',
            'order_id' => null,
            'transaction_id' => null,
            'gross_amount' => 0.0,
            'message' => 'Simulated gateway does not handle webhooks directly.'
        ];
    }
}
