<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Models\WhatsAppLog;
use App\Models\Setting;
use App\Services\WhatsApp\FonnteWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppGatewayFactory;

class WhatsAppLogController extends Controller {
    private WhatsAppLog $waLogModel;

    public function __construct() {
        $this->waLogModel = new WhatsAppLog();
    }

    /**
     * Display WhatsApp gateway status and outbox logs for Platform Admins
     * GET /platform/whatsapp-logs
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        $logs = $this->waLogModel->getLogsForView(50);
        $todayStats = $this->waLogModel->getTodayStats();
        $recentFailures = $this->waLogModel->getRecentFailures(10);

        // Detect Gateway Configuration
        $gatewayType = strtolower((string)env('WHATSAPP_GATEWAY', env('WA_GATEWAY', 'simulated')));
        $token = trim((string)env('FONNTE_TOKEN', env('WA_API_KEY', env('WA_API_TOKEN', ''))));
        $hasToken = !empty($token);

        // Check device connection if fonnte and token configured
        $deviceInfo = [
            'checked' => false,
            'connected' => false,
            'status' => 'NOT_CHECKED',
            'detail' => ''
        ];

        if ($gatewayType === 'fonnte' && $hasToken) {
            try {
                $gateway = new FonnteWhatsAppGateway($token);
                $deviceRes = $gateway->checkDevice();
                $deviceInfo = array_merge(['checked' => true], $deviceRes);
            } catch (\Throwable $e) {
                $deviceInfo = [
                    'checked' => true,
                    'connected' => false,
                    'status' => 'ERROR',
                    'detail' => $e->getMessage()
                ];
            }
        }

        // Warning banner condition: gateway=fonnte but (no token OR device disconnected)
        $showWarningBanner = false;
        $warningMessage = '';
        if ($gatewayType === 'fonnte') {
            if (!$hasToken) {
                $showWarningBanner = true;
                $warningMessage = 'WHATSAPP_GATEWAY diatur ke "fonnte", tetapi FONNTE_TOKEN belum diisi di .env. Pesan WhatsApp tidak akan terkirim dan dicatat FAILED.';
            } elseif ($deviceInfo['checked'] && !$deviceInfo['connected']) {
                $showWarningBanner = true;
                $warningMessage = 'Perangkat Fonnte terputus atau token tidak valid: ' . ($deviceInfo['detail'] ?? 'Device Offline');
            }
        }

        // Read raw file log if exists
        $rawLogContent = '';
        $logPath = ROOT_PATH . '/storage/logs/whatsapp_simulation.log';
        if (file_exists($logPath)) {
            $lines = file($logPath);
            $lastLines = array_slice($lines, -40);
            $rawLogContent = implode('', $lastLines);
        }

        $this->render('platform/whatsapp_logs', [
            'title' => 'Log & Pemantauan WhatsApp Gateway - ' . APP_NAME,
            'user' => $user,
            'logs' => $logs,
            'todayStats' => $todayStats,
            'recentFailures' => $recentFailures,
            'gatewayType' => $gatewayType,
            'hasToken' => $hasToken,
            'deviceInfo' => $deviceInfo,
            'showWarningBanner' => $showWarningBanner,
            'warningMessage' => $warningMessage,
            'rawLogs' => $rawLogContent
        ], 'layouts/main');
    }

    /**
     * Send a single test message (owner_platform only, rate-limited 3x/hour)
     * POST /platform/whatsapp-logs/test-send
     */
    public function testSend(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || $user['role'] !== 'owner_platform') {
            $response->json([
                'success' => false,
                'status' => 'FORBIDDEN',
                'message' => 'Hanya owner_platform yang berhak mengirim pesan tes.'
            ], 403);
            return;
        }

        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            $response->json([
                'success' => false,
                'status' => 'INVALID_CSRF',
                'message' => 'Token CSRF tidak valid. Silakan refresh halaman.'
            ], 400);
            return;
        }

        // Rate Limit Check: max 3 per hour per user
        $userId = (int)$user['id'];
        $db = Database::getConnection();
        $rateStmt = $db->prepare(
            "SELECT COUNT(*) FROM `whatsapp_logs` 
             WHERE `event` = 'TEST_SEND' 
               AND `sent_at` >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $rateStmt->execute();
        $sentCountLastHour = (int)$rateStmt->fetchColumn();

        if ($sentCountLastHour >= 3) {
            $response->json([
                'success' => false,
                'status' => 'RATE_LIMITED',
                'message' => 'Batas pengiriman pesan tes tercapai (maksimal 3 kali per jam). Silakan coba lagi nanti.'
            ], 429);
            return;
        }

        // Determine destination phone
        $testPhone = !empty($user['phone']) ? $user['phone'] : env('FONNTE_TEST_PHONE', '');
        if (empty($testPhone)) {
            $response->json([
                'success' => false,
                'status' => 'NO_PHONE',
                'message' => 'Nomor WhatsApp tujuan tidak ditemukan. Isi nomor di profil akun Anda atau FONNTE_TEST_PHONE di .env.'
            ], 400);
            return;
        }

        $normalizedPhone = normalize_phone_number($testPhone);
        $testMessage = "SmartLaundry: Halo " . $user['name'] . ", ini adalah pesan uji coba dari sistem SmartLaundry. Gateway WhatsApp Anda berfungsi dengan baik!";
        $dedupeKey = "TEST_SEND:" . $userId . ":" . time();

        $logId = $this->waLogModel->createPending($normalizedPhone, $user['name'], 'TEST_SEND', $testMessage, $dedupeKey);

        try {
            $gateway = WhatsAppGatewayFactory::create();
            $result = $gateway->send($normalizedPhone, $testMessage);

            if (!empty($result['success'])) {
                $providerMsgId = $result['provider_message_id'] ?? null;
                $this->waLogModel->updateStatus($logId, $result['status'] ?? 'SENT', null, null, false, $providerMsgId);
                $response->json([
                    'success' => true,
                    'status' => $result['status'] ?? 'SENT',
                    'message' => 'Pesan tes berhasil dikirim ke ' . $normalizedPhone,
                    'provider_message_id' => $providerMsgId
                ]);
            } else {
                $errorMsg = $result['error'] ?? 'Pengiriman gagal';
                $this->waLogModel->updateStatus($logId, 'FAILED', $errorMsg, null, true);
                $response->json([
                    'success' => false,
                    'status' => 'FAILED',
                    'message' => 'Gagal mengirim pesan tes: ' . $errorMsg
                ]);
            }
        } catch (\Throwable $e) {
            $this->waLogModel->updateStatus($logId, 'FAILED', $e->getMessage(), null, true);
            $response->json([
                'success' => false,
                'status' => 'ERROR',
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }
}
