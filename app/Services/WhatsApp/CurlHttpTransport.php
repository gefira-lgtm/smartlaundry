<?php
namespace App\Services\WhatsApp;

class CurlHttpTransport implements HttpTransportInterface {
    public function post(string $url, array $payload, array $headers = [], int $timeout = 10): array {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $body = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'body' => (string)$body,
                'http_code' => $code,
                'error' => !empty($err) ? $err : null
            ];
        }

        // Fallback to stream context
        $headerStr = implode("\r\n", $headers);
        if (!empty($headerStr)) {
            $headerStr .= "\r\n";
        }
        $headerStr .= "Content-Type: application/x-www-form-urlencoded\r\n";

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => $headerStr,
                'content' => http_build_query($payload),
                'timeout' => $timeout,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);
        $code = 200;
        if (isset($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }

        return [
            'body' => (string)$body,
            'http_code' => $code,
            'error' => ($body === false) ? 'Gagal menghubungi server' : null
        ];
    }

    public function get(string $url, array $headers = [], int $timeout = 10): array {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $body = curl_exec($ch);
            $err = curl_error($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'body' => (string)$body,
                'http_code' => $code,
                'error' => !empty($err) ? $err : null
            ];
        }

        $headerStr = implode("\r\n", $headers);
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => $headerStr,
                'timeout' => $timeout,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context = stream_context_create($options);
        $body = @file_get_contents($url, false, $context);
        $code = 200;
        if (isset($http_response_header) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }

        return [
            'body' => (string)$body,
            'http_code' => $code,
            'error' => ($body === false) ? 'Gagal menghubungi server' : null
        ];
    }
}

