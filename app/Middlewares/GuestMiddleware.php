<?php
namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;

class GuestMiddleware {
    /**
     * Prevent authenticated users from accessing guest routes
     */
    public function handle(Request $request, Response $response): void {
        if (is_authenticated()) {
            $role = user_role();
            $target = get_dashboard_url_by_role($role);
            redirect($target);
        }
    }
}
