<?php
/**
 * Super Admin AI Configuration Controller
 * Allows Super Admin to add / edit custom AI models and API keys.
 */
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;

class SuperAdminAIConfigController extends Controller {
    /**
     * Show list of custom AI models and form to add new one.
     */
    public function index(Request $request, Response $response): void {
        // Only super admin can access – role check (assume auth_user() returns array with role)
        $user = auth_user();
        if ($user['role'] !== 'super_admin') {
            $response->setStatus(403);
            $this->render('errors/403', [], 'layouts/main');
            return;
        }

        $json = Setting::get('ai_custom_models', '{}');
        $models = json_decode($json, true) ?? [];
        $this->render('superadmin/ai_config', ['models' => $models, 'user' => $user], 'layouts/main');
    }

    /**
     * Store a new custom AI model.
     */
    public function store(Request $request, Response $response): void {
        $user = auth_user();
        if ($user['role'] !== 'super_admin') {
            $response->setStatus(403);
            $this->render('errors/403', [], 'layouts/main');
            return;
        }

        $modelName = trim($request->post('model_name'));
        $provider   = trim($request->post('provider'));
        $apiKey     = trim($request->post('api_key'));
        if ($modelName === '' || $provider === '' || $apiKey === '') {
            // Validation error – redirect back (could add flash message)
            $response->redirect(url('superadmin/ai-config'));
            return;
        }

        $json = Setting::get('ai_custom_models', '{}');
        $models = json_decode($json, true) ?? [];
        $models[$modelName] = ['provider' => $provider, 'api_key' => $apiKey];
        Setting::set('ai_custom_models', json_encode($models));
        $response->redirect(url('superadmin/ai-config'));
    }

    /**
     * Update an existing AI model (provider / api_key).
     * Expects POST fields: existing_model_name, provider, api_key.
     */
    public function update(Request $request, Response $response): void {
        $user = auth_user();
        if ($user['role'] !== 'super_admin') {
            $response->setStatus(403);
            $this->render('errors/403', [], 'layouts/main');
            return;
        }

        $modelName = trim($request->post('existing_model_name'));
        $provider   = trim($request->post('provider'));
        $apiKey     = trim($request->post('api_key'));
        if ($modelName === '' || $provider === '' || $apiKey === '') {
            $response->redirect(url('superadmin/ai-config'));
            return;
        }

        $json = Setting::get('ai_custom_models', '{}');
        $models = json_decode($json, true) ?? [];
        if (!isset($models[$modelName])) {
            // Model not found – treat as add
            $models[$modelName] = ['provider' => $provider, 'api_key' => $apiKey];
        } else {
            $models[$modelName] = ['provider' => $provider, 'api_key' => $apiKey];
        }
        Setting::set('ai_custom_models', json_encode($models));
        $response->redirect(url('superadmin/ai-config'));
    }

        $user = auth_user();
        if ($user['role'] !== 'super_admin') {
            $response->setStatus(403);
            $this->render('errors/403', [], 'layouts/main');
            return;
        }

        $modelName = trim($request->post('model_name'));
        $provider   = trim($request->post('provider'));
        $apiKey     = trim($request->post('api_key'));
        if ($modelName === '' || $provider === '' || $apiKey === '') {
            // Simple validation error – redirect back with message (omitted for brevity)
            $response->redirect(url('superadmin/ai-config'));
            return;
        }

        $json = Setting::get('ai_custom_models', '{}');
        $models = json_decode($json, true) ?? [];
        $models[$modelName] = ['provider' => $provider, 'api_key' => $apiKey];
        Setting::set('ai_custom_models', json_encode($models));
        $response->redirect(url('superadmin/ai-config'));
    }
}

