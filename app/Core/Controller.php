<?php
namespace App\Core;

abstract class Controller {
    /**
     * Render a view file
     */
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void {
        View::render($view, $data, $layout);
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $statusCode = 200): void {
        $response = new Response();
        $response->json($data, $statusCode);
    }

    /**
     * Redirect to another URL
     */
    protected function redirect(string $path, int $statusCode = 302): void {
        redirect($path, $statusCode);
    }

    /**
     * Get currently logged in user
     */
    protected function getUser(): ?array {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Check if user is logged in
     */
    protected function isLoggedIn(): bool {
        return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }

    /**
     * Check if logged in user has specific role
     */
    protected function hasRole(string ...$roles): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }
        $userRole = $_SESSION['user']['role'] ?? '';
        return in_array($userRole, $roles, true);
    }

    /**
     * Enforce authentication guard
     */
    protected function requireAuth(): void {
        if (!$this->isLoggedIn()) {
            set_flash('error', 'Silakan login terlebih dahulu untuk mengakses halaman ini.');
            $this->redirect('login');
        }
    }

    /**
     * Enforce role authorization guard
     */
    protected function requireRole(string ...$allowedRoles): void {
        $this->requireAuth();
        if (!$this->hasRole(...$allowedRoles)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => '403 - Akses Ditolak'], 'layouts/main');
            exit;
        }
    }

    /**
     * Simple input validation helper
     */
    protected function validate(array $data, array $rules): array {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = trim((string)($data[$field] ?? ''));

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && $value === '') {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' wajib diisi.';
                    break;
                }
                if ($rule === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Format email tidak valid.';
                    break;
                }
                if (strpos($rule, 'min:') === 0) {
                    $min = (int)substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " minimal {$min} karakter.";
                        break;
                    }
                }
                if (strpos($rule, 'numeric') === 0 && $value !== '' && !is_numeric($value)) {
                    $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' harus berupa angka.';
                    break;
                }
            }
        }
        return $errors;
    }
}
