<?php
namespace App\Core;

class Request {
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private ?array $json = null;

    public function __construct() {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->files = $_FILES;

        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $this->json = json_decode($raw, true) ?? [];
        }
    }

    public function getMethod(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getPath(): string {
        $path = $this->get['url'] ?? $this->server['PATH_INFO'] ?? '/';
        $path = trim($path, '/');
        return $path === '' ? '/' : '/' . $path;
    }

    public function get(string $key, $default = null) {
        if ($this->json !== null && array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function post(?string $key = null, $default = null) {
        if ($key === null) {
            return $this->post;
        }
        if ($this->json !== null && array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        return $this->post[$key] ?? $default;
    }

    public function all(): array {
        if ($this->json !== null) {
            return array_merge($this->get, $this->json);
        }
        return array_merge($this->get, $this->post);
    }

    public function isPost(): bool {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool {
        return $this->getMethod() === 'GET';
    }

    public function isAjax(): bool {
        return (isset($this->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
            || (isset($this->server['HTTP_ACCEPT']) && stripos($this->server['HTTP_ACCEPT'], 'application/json') !== false);
    }

    public function header(string $key, ?string $default = null): ?string {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        if (isset($this->server[$normalized])) {
            return (string)$this->server[$normalized];
        }
        if (isset($this->server[$key])) {
            return (string)$this->server[$key];
        }
        $upperKey = strtoupper(str_replace('-', '_', $key));
        if (isset($this->server[$upperKey])) {
            return (string)$this->server[$upperKey];
        }
        return $default;
    }
}
