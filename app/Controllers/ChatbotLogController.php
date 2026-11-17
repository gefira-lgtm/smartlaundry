<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\ChatbotMessage;

/**
 * ChatbotLogController
 * GET  /platform/chatbot-logs           – tabel monitoring percakapan
 * GET  /platform/chatbot-logs/thread    – thread satu sesi (JSON)
 * GET  /platform/chatbot-logs/user-thread – thread satu user (JSON)
 * GET  /platform/chatbot-logs/export    – download CSV
 *
 * Hanya owner_platform dan admin_owner yang boleh mengakses.
 * Role lain mendapat 403, tamu belum login redirect ke login.
 */
class ChatbotLogController extends Controller {
    private ChatbotMessage $logModel;

    public function __construct() {
        $this->logModel = new ChatbotMessage();
    }

    // -------------------------------------------------------------------------
    // Authorization & Audit logging
    // -------------------------------------------------------------------------

    private function authorize(Response $response): bool {
        $user = auth_user();
        if (!$user) {
            $response->redirect('login');
            return false;
        }
        $role = user_role();
        if (!in_array($role, ['owner_platform', 'admin_owner'])) {
            http_response_code(403);
            $this->render('errors/403', [
                'title' => '403 – Akses Ditolak',
                'user'  => $user,
            ], 'layouts/main');
            return false;
        }
        http_response_code(200);
        return true;
    }

    /**
     * Catat riwayat akses & aksi ke storage/logs/chatbot_access.log
     */
    private function logAudit(array $user, string $action, array $details = []): void {
        try {
            $logDir = ROOT_PATH . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $entry = sprintf(
                "[%s] AUDIT_CHATBOT | User #%s (%s - %s) | Action: %s | IP: %s | Details: %s\n",
                date('Y-m-d H:i:s'),
                $user['id'] ?? 'unknown',
                $user['name'] ?? 'unknown',
                $user['role'] ?? 'unknown',
                $action,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            @file_put_contents($logDir . '/chatbot_access.log', $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $th) {
            // Suppress logging error
        }
    }

    // -------------------------------------------------------------------------
    // Index – halaman monitoring
    // -------------------------------------------------------------------------

    /**
     * GET /platform/chatbot-logs
     */
    public function index(Request $request, Response $response): void {
        if (!$this->authorize($response)) return;

        $user = auth_user();

        // --- Filters ---
        $filters = [
            'date_from'     => $request->get('date_from') ? trim($request->get('date_from')) : '',
            'date_to'       => $request->get('date_to')   ? trim($request->get('date_to'))   : '',
            'role'          => $request->get('role') ? trim($request->get('role')) : '',
            'intent'        => $request->get('intent')  ? trim($request->get('intent'))  : '',
            'keyword'       => $request->get('keyword') ? trim($request->get('keyword')) : '',
            'only_fallback' => !empty($request->get('only_fallback')) ? 1 : 0,
        ];

        // Audit Log akses halaman
        $this->logAudit($user, 'VIEW_CHATBOT_LOGS', [
            'filters' => array_filter($filters),
            'page'    => (int)($request->get('page') ?? 1)
        ]);

        $page    = max(1, (int)($request->get('page') ?? 1));
        $perPage = 30;

        $pagination = $this->logModel->getLogsForAdmin($filters, $page, $perPage);
        $summary    = $this->logModel->getSummary($filters);

        $this->render('platform/chatbot_logs', [
            'title'      => 'Monitoring Percakapan Chatbot – ' . APP_NAME,
            'user'       => $user,
            'logs'       => $pagination['data'],
            'pagination' => $pagination,
            'summary'    => $summary,
            'filters'    => $filters,
        ], 'layouts/main');
    }

    // -------------------------------------------------------------------------
    // Thread – percakapan satu sesi (JSON, dipanggil via AJAX)
    // -------------------------------------------------------------------------

    /**
     * GET /platform/chatbot-logs/thread?session_id=xxx
     */
    public function thread(Request $request, Response $response): void {
        if (!$this->authorize($response)) return;

        $sessionId = trim((string)($request->get('session_id') ?? ''));
        if (empty($sessionId)) {
            $response->json(['success' => false, 'error' => 'session_id diperlukan'], 400);
            return;
        }

        $thread = $this->logModel->getSessionThread($sessionId);
        $response->json(['success' => true, 'thread' => $thread]);
    }

    /**
     * GET /platform/chatbot-logs/user-thread?user_id=xxx
     */
    public function userThread(Request $request, Response $response): void {
        if (!$this->authorize($response)) return;

        $userId = (int)($request->get('user_id') ?? 0);
        if ($userId <= 0) {
            $response->json(['success' => false, 'error' => 'user_id diperlukan'], 400);
            return;
        }

        $thread = $this->logModel->getUserThread($userId);
        $response->json(['success' => true, 'thread' => $thread]);
    }

    // -------------------------------------------------------------------------
    // Export CSV
    // -------------------------------------------------------------------------

    /**
     * GET /platform/chatbot-logs/export
     */
    public function export(Request $request, Response $response): void {
        if (!$this->authorize($response)) return;

        $user = auth_user();

        $filters = [
            'date_from'     => $request->get('date_from') ? trim($request->get('date_from')) : '',
            'date_to'       => $request->get('date_to')   ? trim($request->get('date_to'))   : '',
            'role'          => $request->get('role') ? trim($request->get('role')) : '',
            'intent'        => $request->get('intent')  ? trim($request->get('intent'))  : '',
            'keyword'       => $request->get('keyword') ? trim($request->get('keyword')) : '',
            'only_fallback' => !empty($request->get('only_fallback')) ? 1 : 0,
        ];

        // Audit Log ekspor CSV
        $this->logAudit($user, 'EXPORT_CHATBOT_CSV', [
            'filters' => array_filter($filters)
        ]);

        $csv      = $this->logModel->exportCsv($filters);
        $filename = 'chatbot_logs_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM agar Excel terbaca dengan benar
        echo $csv;
        exit;
    }
}
