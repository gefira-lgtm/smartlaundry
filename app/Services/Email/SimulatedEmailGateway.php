<?php
namespace App\Services\Email;

class SimulatedEmailGateway implements EmailGatewayInterface {
    private string $logFilePath;

    public function __construct(?string $logFilePath = null) {
        $this->logFilePath = $logFilePath ?? (ROOT_PATH . '/storage/logs/email_simulation.log');
    }

    /**
     * Send simulated email (logs to storage/logs/email_simulation.log)
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody): bool {
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logEntry = sprintf(
            "[%s] [SIMULATED EMAIL] [TO: %s] [SUBJECT: %s]\nTEXT:\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            $textBody,
            str_repeat('=', 60)
        );

        @file_put_contents($this->logFilePath, $logEntry, FILE_APPEND);
        return true;
    }
}

