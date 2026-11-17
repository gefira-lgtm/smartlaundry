<?php
namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

class AuthMiddleware {
    /**
     * Handle authentication check
     */
    public function handle(Request $request, Response $response): void {
        if (!is_authenticated()) {
            if ($request->isAjax() || strpos($request->getPath(), '/api/') === 0) {
                $response->json([
                    'success' => false,
                    'error' => 'Sesi Anda telah berakhir. Silakan masuk terlebih dahulu.',
                    'message' => 'Sesi Anda telah berakhir. Silakan masuk terlebih dahulu.'
                ], 401);
                exit;
            }
            set_flash('error', 'Sesi Anda telah berakhir. Silakan masuk terlebih dahulu.');
            redirect('login');
        }
    }
}
