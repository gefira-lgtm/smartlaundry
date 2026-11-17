<?php
namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

class RoleMiddleware {
    private array $allowedRoles;

    public function __construct($allowedRoles = []) {
        if (is_string($allowedRoles)) {
            $this->allowedRoles = array_map('trim', explode(',', $allowedRoles));
        } elseif (is_array($allowedRoles)) {
            $this->allowedRoles = $allowedRoles;
        } else {
            $this->allowedRoles = [];
        }
    }

    /**
     * Handle role authorization check
     */
    public function handle(Request $request, Response $response): void {
        if (!is_authenticated()) {
            if ($request->isAjax() || strpos($request->getPath(), '/api/') === 0) {
                $response->json([
                    'success' => false,
                    'error' => 'Silakan masuk terlebih dahulu.',
                    'message' => 'Silakan masuk terlebih dahulu.'
                ], 401);
                exit;
            }
            set_flash('error', 'Silakan masuk terlebih dahulu.');
            redirect('login');
        }

        $userRole = user_role();
        if (!empty($this->allowedRoles) && !in_array($userRole, $this->allowedRoles, true)) {
            http_response_code(403);
            if ($request->isAjax() || strpos($request->getPath(), '/api/') === 0) {
                $response->json([
                    'success' => false,
                    'status' => 'error',
                    'error' => 'Akses ditolak: Anda tidak memiliki izin untuk mengakses resource ini.',
                    'message' => 'Akses ditolak: Anda tidak memiliki izin untuk mengakses resource ini.'
                ], 403);
            } else {
                View::render('errors/403', [
                    'title' => '403 - Akses Ditolak',
                    'userRole' => $userRole,
                    'allowedRoles' => $this->allowedRoles
                ], 'layouts/main');
            }
            exit;
        }
    }
}
