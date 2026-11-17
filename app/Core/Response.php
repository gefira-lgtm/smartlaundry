<?php
namespace App\Core;

class Response {
    protected int $statusCode = 200;

    public function setStatusCode(int $code): self {
        $this->statusCode = $code;
        if (!headers_sent()) {
            http_response_code($code);
        }
        return $this;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }

    public function json(array $data, int $statusCode = 200): void {
        $this->setStatusCode($statusCode);
        if (!defined('TEST_MODE')) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data);
        if (!defined('TEST_MODE')) {
            exit;
        }
    }

    public function redirect(string $path, int $statusCode = 302): void {
        $this->setStatusCode($statusCode);
        if (!headers_sent()) {
            header('Location: ' . url($path), true, $statusCode);
        }
        if (!defined('TEST_MODE')) {
            exit;
        }
    }
}
