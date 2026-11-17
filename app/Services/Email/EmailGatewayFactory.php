<?php
namespace App\Services\Email;

class EmailGatewayFactory {
    /**
     * Create appropriate Email gateway based on environment & configuration
     * Follows Adapter pattern with automatic simulation fallback
     */
    public static function create(): EmailGatewayInterface {
        $driver = strtolower((string)env('MAIL_DRIVER', 'simulated'));

        if ($driver === 'smtp') {
            $host = env('MAIL_HOST', '');
            $port = (int)env('MAIL_PORT', 587);
            $username = env('MAIL_USERNAME', '');
            $password = env('MAIL_PASSWORD', '');
            $encryption = env('MAIL_ENCRYPTION', 'tls');
            $fromAddress = env('MAIL_FROM_ADDRESS', 'no-reply@smartlaundry.id');
            $fromName = env('MAIL_FROM_NAME', 'SmartLaundry');

            if (empty($host) || empty($username) || empty($password)) {
                error_log("EmailGatewayFactory: MAIL_DRIVER is smtp but credentials (HOST/USERNAME/PASSWORD) are missing. Falling back to SimulatedEmailGateway.");
                return new SimulatedEmailGateway();
            }

            return new SmtpEmailGateway($host, $port, $username, $password, $encryption, $fromAddress, $fromName);
        }

        return new SimulatedEmailGateway();
    }
}

