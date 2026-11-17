<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AIChatbotService;

class ChatbotController extends Controller {
    private AIChatbotService $chatbotService;

    public function __construct() {
        $this->chatbotService = new AIChatbotService();
    }

    /**
     * Send message to AI Chatbot endpoint
     * POST /api/chatbot/message
     */
    public function sendMessage(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json([
                'success' => false,
                'error' => 'Silakan login terlebih dahulu untuk menggunakan asisten SmartLaundry.',
                'message' => 'Silakan login terlebih dahulu untuk menggunakan asisten SmartLaundry.'
            ], 401);
            return;
        }

        // 1. Check if chatbot is enabled globally and for role
        $chatbotEnabled = (bool)\App\Models\Setting::get('chatbot_enabled', true);
        if (!$chatbotEnabled) {
            $response->json([
                'success' => false,
                'error' => 'Layanan chatbot asisten saat ini sedang dinonaktifkan oleh administrator.',
                'message' => 'Mohon maaf, layanan asisten chatbot sedang tidak aktif saat ini.'
            ], 503);
            return;
        }

        $role = user_role();
        $allowedRoles = \App\Models\Setting::get('chatbot_roles_enabled', ['customer', 'kurir', 'admin_toko', 'owner_toko', 'owner_platform', 'admin_owner', 'admin_platform']);
        if (is_string($allowedRoles)) {
            $allowedRoles = json_decode($allowedRoles, true) ?: ['customer', 'kurir', 'admin_toko', 'owner_toko', 'owner_platform', 'admin_owner', 'admin_platform'];
        }
        if (!in_array($role, (array)$allowedRoles)) {
            $response->json([
                'success' => false,
                'error' => 'Layanan chatbot tidak tersedia untuk role akun Anda saat ini.',
                'message' => 'Mohon maaf, layanan asisten chatbot tidak aktif untuk jenis akun Anda.'
            ], 403);
            return;
        }

        // 2. CSRF Verification
        $csrfToken = $request->get('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!verify_csrf_token($csrfToken)) {
            $response->json([
                'success' => false,
                'error' => 'Sesi keamanan (CSRF Token) tidak valid atau telah kadaluarsa. Silakan refresh halaman.',
                'message' => 'Sesi keamanan (CSRF Token) tidak valid atau telah kadaluarsa. Silakan refresh halaman.'
            ], 403);
            return;
        }

        // 3. Rate Limiting per minute from Setting
        $rateLimit = (int)\App\Models\Setting::get('chatbot_rate_limit_per_minute', 20);
        if ($rateLimit < 1) $rateLimit = 20;

        $userId = (int)$user['id'];
        $now = time();
        if (!isset($_SESSION['chatbot_rate_limit'][$userId])) {
            $_SESSION['chatbot_rate_limit'][$userId] = [
                'window_start' => $now,
                'count' => 0
            ];
        }

        $rateData = &$_SESSION['chatbot_rate_limit'][$userId];
        if (($now - $rateData['window_start']) > 60) {
            // Reset window
            $rateData['window_start'] = $now;
            $rateData['count'] = 0;
        }

        $rateData['count']++;
        if ($rateData['count'] > $rateLimit) {
            $response->json([
                'success' => false,
                'error' => "Anda telah mencapai batas maksimum pengiriman pesan (maks. {$rateLimit} pesan / menit). Silakan tunggu sebentar.",
                'message' => "Anda telah mencapai batas maksimum pengiriman pesan (maks. {$rateLimit} pesan / menit). Silakan tunggu sebentar."
            ], 429);
            return;
        }

        // 3. Message validation & sanitization
        $rawMessage = (string)($request->get('message') ?? '');
        $cleanMessage = trim(strip_tags($rawMessage));

        if (empty($cleanMessage)) {
            $response->json([
                'success' => false,
                'error' => 'Pesan tidak boleh kosong.',
                'message' => 'Pesan tidak boleh kosong.'
            ], 400);
            return;
        }

        if (mb_strlen($cleanMessage) > 500) {
            $response->json([
                'success' => false,
                'error' => 'Pesan terlalu panjang (maksimum 500 karakter).',
                'message' => 'Pesan terlalu panjang (maksimum 500 karakter).'
            ], 400);
            return;
        }

        // 4. Process Chat with AIChatbotService
        $orderId = $request->get('order_id') ? (int)$request->get('order_id') : null;
        $role = user_role();

        // Server-side store resolution: never trust client input for store access scoping
        $storeId = null;
        if (in_array($role, ['owner_toko', 'admin_toko'])) {
            $storeModel = new \App\Models\Store();
            $store = $storeModel->findForUser($userId, $role);
            $storeId = $store ? (int)$store['id'] : null;
        }

        $chatResult = $this->chatbotService->chat($cleanMessage, $userId, $role, $orderId, $storeId);

        $timestamp = date('H:i');

        // 5. Store conversation history into session
        if (!isset($_SESSION['chatbot_history'][$userId])) {
            $_SESSION['chatbot_history'][$userId] = [];
        }

        $_SESSION['chatbot_history'][$userId][] = [
            'role' => 'user',
            'message' => $cleanMessage,
            'time' => $timestamp
        ];

        $_SESSION['chatbot_history'][$userId][] = [
            'role' => 'assistant',
            'message' => $chatResult['reply'],
            'intent' => $chatResult['intent'],
            'time' => $timestamp
        ];

        // Keep at most 30 recent messages in session
        if (count($_SESSION['chatbot_history'][$userId]) > 30) {
            $_SESSION['chatbot_history'][$userId] = array_slice($_SESSION['chatbot_history'][$userId], -30);
        }

        $response->json([
            'success' => true,
            'reply' => $chatResult['reply'],
            'intent' => $chatResult['intent'],
            'provider' => $chatResult['provider'],
            'timestamp' => $timestamp
        ]);
    }

    /**
     * Get Chat history from session
     * GET /api/chatbot/history
     */
    public function getHistory(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'history' => []], 401);
            return;
        }

        $userId = (int)$user['id'];
        $history = $_SESSION['chatbot_history'][$userId] ?? [];

        $response->json([
            'success' => true,
            'history' => $history
        ]);
    }

    /**
     * Clear chat session history
     * POST /api/chatbot/clear
     */
    public function clearHistory(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false], 401);
            return;
        }

        $csrfToken = $request->get('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!verify_csrf_token($csrfToken)) {
            $response->json([
                'success' => false,
                'error' => 'Sesi keamanan (CSRF Token) tidak valid atau telah kadaluarsa.',
                'message' => 'CSRF Token Invalid'
            ], 403);
            return;
        }

        $userId = (int)$user['id'];
        unset($_SESSION['chatbot_history'][$userId]);

        $response->json([
            'success' => true,
            'message' => 'Riwayat chat telah dibersihkan.'
        ]);
    }
}
