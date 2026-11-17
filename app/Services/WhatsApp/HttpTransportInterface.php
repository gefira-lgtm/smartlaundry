<?php
namespace App\Services\WhatsApp;

interface HttpTransportInterface {
    /**
     * Send HTTP POST request
     * 
     * @param string $url Target endpoint URL
     * @param array $payload Form fields to post
     * @param array $headers List of HTTP headers
     * @param int $timeout Timeout in seconds
     * @return array [ 'body' => string, 'http_code' => int, 'error' => ?string ]
     */
    public function post(string $url, array $payload, array $headers = [], int $timeout = 10): array;

    /**
     * Send HTTP GET request
     * 
     * @param string $url Target endpoint URL
     * @param array $headers List of HTTP headers
     * @param int $timeout Timeout in seconds
     * @return array [ 'body' => string, 'http_code' => int, 'error' => ?string ]
     */
    public function get(string $url, array $headers = [], int $timeout = 10): array;
}

