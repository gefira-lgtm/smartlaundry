<?php
namespace App\Services\Email;

class SmtpEmailGateway implements EmailGatewayInterface {
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption;
    private string $fromAddress;
    private string $fromName;
    private int $timeout;

    public function __construct(
        string $host,
        int $port,
        string $username,
        string $password,
        string $encryption = 'tls',
        ?string $fromAddress = null,
        ?string $fromName = null,
        int $timeout = 5
    ) {
        $this->host = trim($host);
        $this->port = $port;
        $this->username = trim($username);
        $this->password = trim($password);
        $this->encryption = strtolower(trim($encryption));
        $this->fromAddress = !empty($fromAddress) ? trim($fromAddress) : 'no-reply@smartlaundry.id';
        $this->fromName = !empty($fromName) ? trim($fromName) : 'SmartLaundry';
        $this->timeout = $timeout;
    }

    /**
     * Send email via direct SMTP socket with STARTTLS and AUTH LOGIN
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody): bool {
        if (empty($this->host) || empty($this->username) || empty($this->password)) {
            error_log("SmtpEmailGateway: SMTP credentials not fully configured.");
            return false;
        }

        try {
            $isSsl = ($this->encryption === 'ssl' || $this->port === 465);
            $connectHost = ($isSsl ? 'ssl://' : '') . $this->host;

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $socket = @stream_socket_client(
                "{$connectHost}:{$this->port}",
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                error_log("SmtpEmailGateway connection failed: {$errstr} ({$errno})");
                return false;
            }

            stream_set_timeout($socket, $this->timeout);

            // Read welcome banner
            $this->readResponse($socket);

            // EHLO
            $clientHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $this->sendCommand($socket, "EHLO {$clientHost}");

            // STARTTLS for port 587 / tls encryption
            if (!$isSsl && ($this->encryption === 'tls' || $this->port === 587)) {
                $this->sendCommand($socket, "STARTTLS");
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$crypto) {
                    error_log("SmtpEmailGateway: Failed to enable crypto / STARTTLS.");
                    fclose($socket);
                    return false;
                }
                // Resend EHLO after TLS handshake
                $this->sendCommand($socket, "EHLO {$clientHost}");
            }

            // AUTH LOGIN
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode($this->username));
            $this->sendCommand($socket, base64_encode($this->password));

            // MAIL FROM
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromAddress}>");

            // RCPT TO
            $this->sendCommand($socket, "RCPT TO:<{$to}>");

            // DATA
            $this->sendCommand($socket, "DATA");

            // Build MIME message
            $boundary = "==Multipart_Boundary_x" . md5(microtime()) . "x";
            $headers = [];
            $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromAddress}>";
            $headers[] = "To: <{$to}>";
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "Date: " . date('r');
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $body = implode("\r\n", $headers) . "\r\n\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            $body .= "--{$boundary}--\r\n";
            $body .= ".\r\n";

            fwrite($socket, $body);
            $res = $this->readResponse($socket);

            $this->sendCommand($socket, "QUIT");
            fclose($socket);

            return (strpos($res, '250') === 0 || strpos($res, '235') === 0 || strpos($res, '220') === 0);
        } catch (\Throwable $e) {
            error_log("SmtpEmailGateway exception: " . $e->getMessage());
            return false;
        }
    }

    private function sendCommand($socket, string $command): string {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string {
        $response = "";
        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }
}

