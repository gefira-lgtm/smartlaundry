<?php
namespace App\Services\Payment;

class PaymentGatewayFactory {
    
    /**
     * Create and return the appropriate payment gateway instance
     */
    public static function create(): PaymentGatewayInterface {
        $gatewayType = env('PAYMENT_GATEWAY', 'simulated');
        $serverKey = env('MIDTRANS_SERVER_KEY', '');
        $isProduction = env('MIDTRANS_IS_PRODUCTION', 'false') === 'true';

        // Fallback to simulated if midtrans is requested but no server key is provided
        if (strtolower($gatewayType) === 'midtrans') {
            if (empty($serverKey)) {
                error_log("PaymentGatewayFactory: MIDTRANS_SERVER_KEY is missing. Falling back to SimulatedPaymentGateway.");
                return new SimulatedPaymentGateway();
            }
            return new MidtransPaymentGateway($serverKey, $isProduction);
        }

        return new SimulatedPaymentGateway();
    }
}
