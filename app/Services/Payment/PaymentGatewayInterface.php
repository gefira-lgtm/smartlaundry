<?php
namespace App\Services\Payment;

interface PaymentGatewayInterface {
    /**
     * Create a payment transaction for an order
     *
     * @param array $orderData Order details (id, amount, etc)
     * @return array [ 'transaction_id' => string, 'qris_payload' => string|null, 'qris_image_url' => string|null ]
     */
    public function createTransaction(array $orderData): array;

    /**
     * Check transaction status
     *
     * @param string $transactionId Gateway-specific transaction reference
     * @return string Status (PAID, PENDING, EXPIRED, FAILED)
     */
    public function checkStatus(string $transactionId): string;

    /**
     * Handle incoming webhook payload
     *
     * @param array $payload The webhook JSON body
     * @return array [ 'status' => string, 'order_id' => string|int, 'transaction_id' => string, 'gross_amount' => float, 'message' => string ]
     */
    public function handleWebhook(array $payload): array;
}
