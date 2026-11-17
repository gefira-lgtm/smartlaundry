<?php
namespace App\Services\WhatsApp;

class SimulatedWhatsAppGateway implements WhatsAppGatewayInterface {
    private string $logFilePath;

    public function __construct(?string $logFilePath = null) {
        $this->logFilePath = $logFilePath ?? (ROOT_PATH . '/storage/logs/whatsapp_simulation.log');
    }

    /**
     * Send simulated WhatsApp message (logs to file)
     */
    public function send(string $phone, string $message): array {
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logEntry = sprintf(
            "[%s] [SIMULATED GATEWAY] [TO: %s]\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $phone,
            $message,
            str_repeat('-', 60)
        );

        @file_put_contents($this->logFilePath, $logEntry, FILE_APPEND);

        return [
            'success' => true,
            'status' => 'SIMULATED',
            'error' => null,
            'response' => ['simulated' => true]
        ];
    }
}

