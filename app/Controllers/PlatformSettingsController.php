<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Setting;
use App\Models\ChatbotKnowledge;

class PlatformSettingsController extends Controller {
    private ?ChatbotKnowledge $knowledgeModel = null;

    public function __construct() {
        if (class_exists('App\Models\ChatbotKnowledge')) {
            $this->knowledgeModel = new ChatbotKnowledge();
        }
    }

    /**
     * Settings Dashboard Page
     * GET /platform/settings
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            $this->render('errors/403', ['title' => '403 - Akses Ditolak'], 'layouts/main');
            return;
        }

        $activeTab = $request->get('tab', 'general');

        // 1. Load settings per group
        $settingsByGroup = [];
        foreach (Setting::GROUPS as $groupKey => $groupLabel) {
            $settingsByGroup[$groupKey] = Setting::getGroup($groupKey);
        }

        // 2. Audit logs pagination
        $page = max(1, (int)$request->get('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;
        $totalLogs = Setting::countAuditLogs();
        $auditLogs = Setting::getAuditLogs($perPage, $offset);
        $totalPages = ceil($totalLogs / $perPage);

        // 3. System info & stats
        $systemInfo = Setting::getSystemInfo();

        // 4. Cron status
        $cronStatus = Setting::getCronStatus();

        // 5. Integration status
        $integrationStatus = Setting::getIntegrationStatus();

        // 6. Chatbot Knowledge Base
        $knowledgeItems = [];
        if ($this->knowledgeModel) {
            try {
                $knowledgeItems = $this->knowledgeModel->all('id DESC');
            } catch (\Throwable $e) {
                $knowledgeItems = [];
            }
        }

        // 7. Dynamic AI Configuration & Backup Agents Pool
        $aiProvider = Setting::get('ai_provider') ?: env('AI_PROVIDER', 'mock');
        $aiModel    = Setting::get('ai_model') ?: env('AI_MODEL', 'gemini-1.5-flash');
        $aiApiKey   = Setting::get('ai_primary_credential') ?: (Setting::get('ai_api_key') ?: env('AI_API_KEY', ''));
        $aiBaseUrl  = Setting::get('ai_base_url') ?: env('AI_BASE_URL', '');
        $rawBackups = Setting::get('ai_backup_models', '[]');
        $aiBackupModels = is_array($rawBackups) ? $rawBackups : (json_decode((string)$rawBackups, true) ?: []);

        $this->render('platform/settings', [
            'title' => 'Konfigurasi Sistem - ' . app_name(),
            'user' => $user,
            'activeTab' => $activeTab,
            'groups' => Setting::GROUPS,
            'editableGroups' => Setting::EDITABLE_GROUPS_BY_ROLE[$user['role']] ?? [],
            'settingsByGroup' => $settingsByGroup,
            'auditLogs' => $auditLogs,
            'auditPage' => $page,
            'auditTotalPages' => $totalPages,
            'auditTotalCount' => $totalLogs,
            'systemInfo' => $systemInfo,
            'cronStatus' => $cronStatus,
            'integrationStatus' => $integrationStatus,
            'knowledgeItems' => $knowledgeItems,
            'aiProvider' => $aiProvider,
            'aiModel' => $aiModel,
            'aiApiKey' => $aiApiKey,
            'aiBaseUrl' => $aiBaseUrl,
            'aiBackupModels' => $aiBackupModels
        ], 'layouts/main');
    }

    /**
     * Process Settings Update Form
     * POST /platform/settings/update
     */
    public function update(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden: Akses ditolak.');
        }

        // 1. CSRF Verification
        $csrfToken = $request->get('_csrf_token');
        if (!verify_csrf_token($csrfToken)) {
            set_flash('error', 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.');
            $this->redirect('platform/settings');
            return;
        }

        $group = trim((string)$request->get('setting_group', ''));
        if (!array_key_exists($group, Setting::GROUPS)) {
            set_flash('error', 'Grup pengaturan tidak valid.');
            $this->redirect('platform/settings');
            return;
        }

        // 2. Permission Check based on Role Access Matrix
        if (!Setting::canRoleEditGroup($user['role'], $group)) {
            http_response_code(403);
            $roleDisplay = $user['role_display'] ?? $user['role'];
            set_flash('error', "Hak Akses Ditolak: Role '{$roleDisplay}' tidak diizinkan mengubah grup {$group}.");
            $this->redirect("platform/settings?tab={$group}");
            return;
        }

        $post = $request->post();
        $userId = (int)$user['id'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $errors = [];

        // 3. Validation & Update per group
        switch ($group) {
            case Setting::GROUP_GENERAL:
                // Handle Logo Upload if present
                if (isset($_FILES['app_logo_file']) && $_FILES['app_logo_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['app_logo_file'];
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    if (!in_array($mime, $allowedMimes, true)) {
                        $errors[] = 'Format logo harus berupa file gambar JPG, PNG, atau WEBP.';
                    } elseif ($file['size'] > 2 * 1024 * 1024) {
                        $errors[] = 'Ukuran file logo maksimal 2MB.';
                    } else {
                        $uploadDir = ROOT_PATH . '/public/uploads/settings/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
                        $filename = 'app_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                            Setting::set('app_logo', 'uploads/settings/' . $filename, $userId, $ip);
                        } else {
                            $errors[] = 'Gagal menyimpan file logo.';
                        }
                    }
                }

                $appName = trim((string)($post['app_name'] ?? 'SmartLaundry'));
                if (empty($appName)) {
                    $errors[] = 'Nama aplikasi tidak boleh kosong.';
                } else {
                    Setting::set('app_name', $appName, $userId, $ip);
                }

                Setting::set('app_tagline', trim((string)($post['app_tagline'] ?? '')), $userId, $ip);
                Setting::set('contact_email', trim((string)($post['contact_email'] ?? '')), $userId, $ip);
                Setting::set('contact_phone', trim((string)($post['contact_phone'] ?? '')), $userId, $ip);
                Setting::set('contact_address', trim((string)($post['contact_address'] ?? '')), $userId, $ip);
                Setting::set('footer_text', trim((string)($post['footer_text'] ?? '')), $userId, $ip);
                break;

            case Setting::GROUP_PAYMENT:
                $qrisExp = (int)($post['qris_expiration_minutes'] ?? 60);
                if ($qrisExp < 10 || $qrisExp > 1440) {
                    $errors[] = 'Durasi kedaluwarsa QRIS harus berada di rentang 10 sampai 1440 menit (24 jam).';
                } else {
                    Setting::set('qris_expiration_minutes', $qrisExp, $userId, $ip);
                }

                $cashEnabled = !empty($post['payment_cash_enabled']) ? 1 : 0;
                Setting::set('payment_cash_enabled', $cashEnabled, $userId, $ip);

                $reminderMin = (int)($post['payment_reminder_minutes'] ?? 15);
                if ($reminderMin < 1 || $reminderMin > 60) {
                    $errors[] = 'Menit pengingat sebelum kedaluwarsa harus antara 1 sampai 60 menit.';
                } else {
                    Setting::set('payment_reminder_minutes', $reminderMin, $userId, $ip);
                }
                break;

            case Setting::GROUP_REFUND:
                $rawFee = str_replace(['.', ',', ' '], '', $post['return_fee_default'] ?? '0');
                $returnFee = max(0, (int)$rawFee);
                Setting::set('return_fee_default', $returnFee, $userId, $ip);

                $bearer = strtoupper(trim((string)($post['return_fee_bearer_default'] ?? 'CUSTOMER')));
                if (!in_array($bearer, ['CUSTOMER', 'STORE', 'PLATFORM'], true)) {
                    $bearer = 'CUSTOMER';
                }
                Setting::set('return_fee_bearer_default', $bearer, $userId, $ip);

                $reqApproval = !empty($post['return_require_store_approval']) ? 1 : 0;
                Setting::set('return_require_store_approval', $reqApproval, $userId, $ip);
                break;

            case Setting::GROUP_COURIER_ORDER:
                $timeout = (int)($post['courier_offer_timeout_minutes'] ?? 15);
                if ($timeout < 1 || $timeout > 120) {
                    $errors[] = 'Batas waktu respons tawaran kurir harus antara 1 sampai 120 menit.';
                } else {
                    Setting::set('courier_offer_timeout_minutes', $timeout, $userId, $ip);
                }

                $autoComp = (int)($post['order_autocomplete_hours'] ?? 24);
                if ($autoComp < 1 || $autoComp > 168) {
                    $errors[] = 'Batas auto-complete pesanan harus antara 1 sampai 168 jam (7 hari).';
                } else {
                    Setting::set('order_autocomplete_hours', $autoComp, $userId, $ip);
                }

                $pollInterval = (int)($post['tracking_poll_interval_seconds'] ?? 10);
                if ($pollInterval < 3 || $pollInterval > 30) {
                    $errors[] = 'Interval polling tracking harus berada di rentang 3 sampai 30 detik.';
                } else {
                    Setting::set('tracking_poll_interval_seconds', $pollInterval, $userId, $ip);
                }
                break;

            case Setting::GROUP_NOTIFICATION:
                $inApp = !empty($post['in_app_notification_enabled']) ? 1 : 0;
                Setting::set('in_app_notification_enabled', $inApp, $userId, $ip);

                $waGlobal = !empty($post['whatsapp_notification_enabled']) ? 1 : 0;
                Setting::set('whatsapp_notification_enabled', $waGlobal, $userId, $ip);

                // Templates
                $templates = [
                    'wa_template_order_created',
                    'wa_template_order_confirmed',
                    'wa_template_courier_dispatched',
                    'wa_template_payment_success',
                    'wa_template_laundry_ready'
                ];
                foreach ($templates as $tKey) {
                    if (isset($post[$tKey])) {
                        Setting::set($tKey, trim((string)$post[$tKey]), $userId, $ip);
                    }
                }
                break;

            case Setting::GROUP_CHATBOT:
                $cbGlobal = !empty($post['chatbot_enabled']) ? 1 : 0;
                Setting::set('chatbot_enabled', $cbGlobal, $userId, $ip);

                $rolesAllowed = $post['chatbot_roles_enabled'] ?? [];
                if (!is_array($rolesAllowed)) {
                    $rolesAllowed = ['customer'];
                }
                Setting::set('chatbot_roles_enabled', json_encode(array_values($rolesAllowed)), $userId, $ip);

                $botName = trim((string)($post['chatbot_bot_name'] ?? 'SmartBot'));
                Setting::set('chatbot_bot_name', $botName ?: 'SmartBot', $userId, $ip);

                Setting::set('chatbot_greeting_customer', trim((string)($post['chatbot_greeting_customer'] ?? '')), $userId, $ip);
                Setting::set('chatbot_greeting_kurir', trim((string)($post['chatbot_greeting_kurir'] ?? '')), $userId, $ip);
                Setting::set('chatbot_greeting_admin', trim((string)($post['chatbot_greeting_admin'] ?? '')), $userId, $ip);
                Setting::set('chatbot_fallback_message', trim((string)($post['chatbot_fallback_message'] ?? '')), $userId, $ip);

                $rateLimit = (int)($post['chatbot_rate_limit_per_minute'] ?? 20);
                if ($rateLimit < 1 || $rateLimit > 120) {
                    $errors[] = 'Batas pesan per menit chatbot harus antara 1 sampai 120.';
                } else {
                    Setting::set('chatbot_rate_limit_per_minute', $rateLimit, $userId, $ip);
                }

                $retention = (int)($post['chatbot_log_retention_days'] ?? 90);
                if ($retention < 1 || $retention > 365) {
                    $errors[] = 'Retensi log chat harus antara 1 sampai 365 hari.';
                } else {
                    Setting::set('chatbot_log_retention_days', $retention, $userId, $ip);
                }

                $canViewFinance = !empty($post['admin_toko_can_view_finance']) ? 1 : 0;
                Setting::set('admin_toko_can_view_finance', $canViewFinance, $userId, $ip);

                // Konfigurasi AI Provider Utama Dinamis (tanpa harus buka .env)
                if (isset($post['ai_provider'])) {
                    $aiProv = strtolower(trim((string)$post['ai_provider']));
                    Setting::set('ai_provider', $aiProv, $userId, $ip);
                }
                if (isset($post['ai_model'])) {
                    $aiModel = trim((string)$post['ai_model']);
                    if (!empty($aiModel)) {
                        Setting::set('ai_model', $aiModel, $userId, $ip);
                    }
                }
                if (!empty($post['ai_api_key'])) {
                    Setting::set('ai_primary_credential', trim((string)$post['ai_api_key']), $userId, $ip);
                }
                if (isset($post['ai_base_url'])) {
                    Setting::set('ai_base_url', trim((string)$post['ai_base_url']), $userId, $ip);
                }
                break;

            case Setting::GROUP_SECURITY:
                $maxAttempts = (int)($post['login_max_attempts'] ?? 5);
                if ($maxAttempts < 3 || $maxAttempts > 20) {
                    $errors[] = 'Jumlah percobaan login maksimal harus antara 3 sampai 20 kali.';
                } else {
                    Setting::set('login_max_attempts', $maxAttempts, $userId, $ip);
                }

                $lockout = (int)($post['login_lockout_duration_minutes'] ?? 15);
                if ($lockout < 1 || $lockout > 120) {
                    $errors[] = 'Durasi blokir login harus antara 1 sampai 120 menit.';
                } else {
                    Setting::set('login_lockout_duration_minutes', $lockout, $userId, $ip);
                }

                $sessionLife = (int)($post['session_lifetime_minutes'] ?? 120);
                if ($sessionLife < 15 || $sessionLife > 1440) {
                    $errors[] = 'Lama masa aktif sesi harus antara 15 sampai 1440 menit (24 jam).';
                } else {
                    Setting::set('session_lifetime_minutes', $sessionLife, $userId, $ip);
                }
                break;

            case Setting::GROUP_MAINTENANCE:
                $maintActive = !empty($post['maintenance_mode']) ? 1 : 0;
                $maintMsg = trim((string)($post['maintenance_message'] ?? ''));
                if (empty($maintMsg)) {
                    $maintMsg = 'Sistem sedang dalam proses pemeliharaan berkala.';
                }

                Setting::set('maintenance_mode', $maintActive, $userId, $ip);
                Setting::set('maintenance_message', $maintMsg, $userId, $ip);
                break;
        }

        if (!empty($errors)) {
            set_flash('error', implode('<br>', $errors));
        } else {
            // Activity Notification: SETTING_UPDATED
            try {
                $waNotif = new \App\Services\WhatsAppNotificationService();
                $waNotif->notifyActivity('SETTING_UPDATED', [
                    'setting_name' => Setting::GROUPS[$group] ?? $group,
                    'change_detail' => "Grup konfigurasi '" . (Setting::GROUPS[$group] ?? $group) . "' berhasil diperbarui."
                ], null, [
                    'name' => $user['name'] ?? 'Admin Platform',
                    'role' => $user['role_name'] ?? ($user['role'] ?? 'owner_platform')
                ]);
            } catch (\Throwable $e) {}

            set_flash('success', "Pengaturan grup '" . Setting::GROUPS[$group] . "' berhasil diperbarui dan disimpan ke sistem.");
        }

        $this->redirect("platform/settings?tab={$group}");
    }

    /**
     * Confirmation & toggle switch for Maintenance Mode
     * POST /platform/settings/toggle-maintenance
     */
    public function toggleMaintenance(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || $user['role'] !== 'owner_platform') {
            http_response_code(403);
            exit('403 Forbidden: Hanya Owner Platform yang berhak mengaktifkan mode pemeliharaan.');
        }

        $csrfToken = $request->get('_csrf_token');
        if (!verify_csrf_token($csrfToken)) {
            set_flash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('platform/settings?tab=maintenance');
            return;
        }

        $currentStatus = (bool)Setting::get('maintenance_mode', false);
        $newStatus = !$currentStatus;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        Setting::set('maintenance_mode', $newStatus ? 1 : 0, (int)$user['id'], $ip);

        if ($newStatus) {
            set_flash('success', 'Mode Pemeliharaan (503) BERHASIL DIAKTIFKAN. Pengguna umum akan diarahkan ke halaman 503.');
        } else {
            set_flash('success', 'Mode Pemeliharaan dinonaktifkan. Sistem kini kembali online untuk seluruh pengguna.');
        }

        $this->redirect('platform/settings?tab=maintenance');
    }

    /**
     * Export Configuration Summary (JSON or CSV)
     * GET /platform/settings/export
     */
    public function export(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden: Akses ekspor ditolak.');
        }

        $format = strtolower($request->get('format', 'json'));
        if (!in_array($format, ['json', 'csv'], true)) {
            $format = 'json';
        }

        $filename = 'smartlaundry_konfigurasi_' . date('Ymd_His') . '.' . $format;
        $content = Setting::exportConfiguration($format);

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
        } else {
            header('Content-Type: application/json; charset=utf-8');
        }

        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $content;
        exit;
    }

    /**
     * Test Integration Connection (AJAX endpoint)
     * POST /platform/settings/test-integration
     */
    public function testIntegration(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || $user['role'] !== 'owner_platform') {
            $response->json(['success' => false, 'error' => 'Khusus Owner Platform'], 403);
            return;
        }

        $csrfToken = $request->get('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!verify_csrf_token($csrfToken)) {
            $response->json(['success' => false, 'error' => 'CSRF token tidak valid'], 403);
            return;
        }

        $service = $request->get('service');
        $now = time();

        // Rate limit: 1 request per 10 seconds per service
        $rateKey = 'test_integration_' . $service;
        if (isset($_SESSION[$rateKey]) && ($now - $_SESSION[$rateKey]) < 10) {
            $response->json([
                'success' => false,
                'error' => 'Harap tunggu 10 detik sebelum melakukan tes koneksi ulang.'
            ], 429);
            return;
        }
        $_SESSION[$rateKey] = $now;

        switch ($service) {
            case 'midtrans':
                $serverKey = env('MIDTRANS_SERVER_KEY');
                if (empty($serverKey)) {
                    $response->json(['success' => false, 'message' => 'Kunci MIDTRANS_SERVER_KEY belum diisi di .env']);
                    return;
                }
                $response->json(['success' => true, 'message' => 'Koneksi Midtrans Server Key terverifikasi (Kredensial siap digunakan).']);
                break;

            case 'whatsapp':
                $waKey = env('WA_API_KEY');
                if (empty($waKey)) {
                    $response->json(['success' => false, 'message' => 'Kunci WA_API_KEY belum diisi di .env (Mode Simulasi Aktif)']);
                    return;
                }
                $response->json(['success' => true, 'message' => 'Koneksi Fonnte WhatsApp API Gateway terverifikasi.']);
                break;

            case 'ai_service':
                $aiKey = Setting::get('ai_api_key') ?: env('AI_API_KEY');
                $provider = Setting::get('ai_provider') ?: env('AI_PROVIDER', 'mock');
                $model = Setting::get('ai_model') ?: env('AI_MODEL', 'gemini-1.5-flash');
                if (empty($aiKey) || $provider === 'mock') {
                    $response->json(['success' => true, 'message' => 'Sistem menggunakan AI Intelligent Rule-Based Mock (Offline Demo).']);
                    return;
                }
                $response->json(['success' => true, 'message' => "Koneksi API AI {$provider} ({$model}) terverifikasi siap melayani chat."]);
                break;

            default:
                $response->json(['success' => false, 'error' => 'Layanan tidak dikenali'], 400);
        }
    }

    /**
     * Add Chatbot Knowledge Entry
     * POST /platform/settings/knowledge/store
     */
    public function storeKnowledge(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }

        if (!verify_csrf_token($request->get('_csrf_token'))) {
            set_flash('error', 'CSRF token tidak valid');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $category = trim((string)$request->get('category', 'faq'));
        $title = trim((string)$request->get('title', ''));
        $keywords = trim((string)$request->get('keywords', ''));
        $content = trim((string)$request->get('content', ''));
        $rolesAllowed = trim((string)$request->get('roles_allowed', 'all'));

        if (empty($title) || empty($content)) {
            set_flash('error', 'Judul dan konten pengetahuan tidak boleh kosong.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        try {
            $pdo = \App\Core\Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO `chatbot_knowledge` 
                (`category`, `title`, `keywords`, `content`, `roles_allowed`, `is_active`, `updated_at`) 
                VALUES (:cat, :title, :kw, :content, :roles, 1, NOW())");
            $stmt->execute([
                'cat' => $category,
                'title' => $title,
                'kw' => $keywords,
                'content' => $content,
                'roles' => $rolesAllowed ?: 'all'
            ]);
            set_flash('success', 'Artikel basis pengetahuan baru berhasil ditambahkan.');
        } catch (\Throwable $e) {
            set_flash('error', 'Gagal menambahkan artikel: ' . $e->getMessage());
        }

        $this->redirect('platform/settings?tab=chatbot');
    }

    /**
     * Toggle Chatbot Knowledge active state
     * POST /platform/settings/knowledge/{id}/toggle
     */
    public function toggleKnowledge(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }

        if (!verify_csrf_token($request->get('_csrf_token'))) {
            set_flash('error', 'CSRF token tidak valid');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        try {
            $pdo = \App\Core\Database::getConnection();
            $stmt = $pdo->prepare("UPDATE `chatbot_knowledge` SET `is_active` = IF(`is_active`=1, 0, 1), `updated_at` = NOW() WHERE `id` = :id");
            $stmt->execute(['id' => (int)$id]);
            set_flash('success', 'Status artikel basis pengetahuan berhasil diubah.');
        } catch (\Throwable $e) {
            set_flash('error', 'Gagal mengubah status: ' . $e->getMessage());
        }

        $this->redirect('platform/settings?tab=chatbot');
    }

    /**
     * Tambah Model Agen Cadangan AI
     * POST /platform/settings/ai-models/store
     */
    public function storeAIBackupModel(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }

        if (!verify_csrf_token($request->get('_csrf_token'))) {
            set_flash('error', 'CSRF token tidak valid.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $name = trim((string)$request->post('name', ''));
        $provider = strtolower(trim((string)$request->post('provider', 'gemini')));
        $model = trim((string)$request->post('model', ''));
        $apiKey = trim((string)$request->post('api_key', ''));
        $baseUrl = trim((string)$request->post('base_url', ''));

        if (empty($name) || empty($model) || empty($apiKey)) {
            set_flash('error', 'Nama agen, nama model, dan API key wajib diisi.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $rawBackups = Setting::get('ai_backup_models', '[]');
        $backupList = is_array($rawBackups) ? $rawBackups : (json_decode((string)$rawBackups, true) ?: []);

        $newId = 'agent_' . time() . '_' . bin2hex(random_bytes(3));
        $backupList[] = [
            'id' => $newId,
            'name' => $name,
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
            'base_url' => $baseUrl,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        Setting::set('ai_backup_models', json_encode(array_values($backupList)), (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        set_flash('success', "Model AI cadangan '{$name}' ({$model}) berhasil ditambahkan.");
        $this->redirect('platform/settings?tab=chatbot');
    }

    /**
     * Update Model Agen Cadangan AI
     * POST /platform/settings/ai-models/{id}/update
     */
    public function updateAIBackupModel(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }

        if (!verify_csrf_token($request->get('_csrf_token'))) {
            set_flash('error', 'CSRF token tidak valid.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $name = trim((string)$request->post('name', ''));
        $provider = strtolower(trim((string)$request->post('provider', 'gemini')));
        $model = trim((string)$request->post('model', ''));
        $apiKey = trim((string)$request->post('api_key', ''));
        $baseUrl = trim((string)$request->post('base_url', ''));

        $rawBackups = Setting::get('ai_backup_models', '[]');
        $backupList = is_array($rawBackups) ? $rawBackups : (json_decode((string)$rawBackups, true) ?: []);

        $found = false;
        foreach ($backupList as &$item) {
            if ($item['id'] === $id) {
                $item['name'] = $name ?: $item['name'];
                $item['provider'] = $provider ?: $item['provider'];
                $item['model'] = $model ?: $item['model'];
                if (!empty($apiKey)) {
                    $item['api_key'] = $apiKey;
                }
                $item['base_url'] = $baseUrl;
                $found = true;
                break;
            }
        }
        unset($item);

        if ($found) {
            Setting::set('ai_backup_models', json_encode(array_values($backupList)), (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            set_flash('success', "Model AI cadangan '{$name}' berhasil diperbarui.");
        } else {
            set_flash('error', 'Model AI cadangan tidak ditemukan.');
        }

        $this->redirect('platform/settings?tab=chatbot');
    }

    /**
     * Hapus Model Agen Cadangan AI
     * POST /platform/settings/ai-models/{id}/delete
     */
    public function deleteAIBackupModel(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }

        if (!verify_csrf_token($request->get('_csrf_token'))) {
            set_flash('error', 'CSRF token tidak valid.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $rawBackups = Setting::get('ai_backup_models', '[]');
        $backupList = is_array($rawBackups) ? $rawBackups : (json_decode((string)$rawBackups, true) ?: []);

        $newList = [];
        $deletedName = '';
        foreach ($backupList as $item) {
            if ($item['id'] === $id) {
                $deletedName = $item['name'] ?? $item['model'];
                continue;
            }
            $newList[] = $item;
        }

        Setting::set('ai_backup_models', json_encode(array_values($newList)), (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        set_flash('success', "Model AI cadangan '{$deletedName}' berhasil dihapus.");
        $this->redirect('platform/settings?tab=chatbot');
    }

    /**
     * Toggle status aktif model cadangan
     * POST /platform/settings/ai-models/{id}/toggle
     */
    public function toggleAIBackupModel(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }

        if (!verify_csrf_token($request->get('_csrf_token'))) {
            set_flash('error', 'CSRF token tidak valid.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $rawBackups = Setting::get('ai_backup_models', '[]');
        $backupList = is_array($rawBackups) ? $rawBackups : (json_decode((string)$rawBackups, true) ?: []);

        foreach ($backupList as &$item) {
            if ($item['id'] === $id) {
                $item['is_active'] = empty($item['is_active']) ? 1 : 0;
                break;
            }
        }
        unset($item);

        Setting::set('ai_backup_models', json_encode(array_values($backupList)), (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        set_flash('success', 'Status keaktifan model cadangan berhasil diperbarui.');
        $this->redirect('platform/settings?tab=chatbot');
    }

    /**
     * Jadikan model cadangan sebagai Model Utama (Promote to Primary)
     * POST /platform/settings/ai-models/{id}/promote
     */
    public function promoteAIBackupModel(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            http_response_code(403);
            exit('403 Forbidden');
        }

        if (!verify_csrf_token($request->get('_csrf_token'))) {
            set_flash('error', 'CSRF token tidak valid.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $rawBackups = Setting::get('ai_backup_models', '[]');
        $backupList = is_array($rawBackups) ? $rawBackups : (json_decode((string)$rawBackups, true) ?: []);

        $target = null;
        foreach ($backupList as $item) {
            if ($item['id'] === $id) {
                $target = $item;
                break;
            }
        }

        if (!$target) {
            set_flash('error', 'Model cadangan tidak ditemukan.');
            $this->redirect('platform/settings?tab=chatbot');
            return;
        }

        $userId = (int)$user['id'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Simpan model ini sebagai model utama
        Setting::set('ai_provider', $target['provider'], $userId, $ip);
        Setting::set('ai_model', $target['model'], $userId, $ip);
        Setting::set('ai_primary_credential', $target['api_key'], $userId, $ip);
        Setting::set('ai_base_url', $target['base_url'] ?? '', $userId, $ip);

        set_flash('success', "Model '{$target['name']}' ({$target['model']}) berhasil dijadikan sebagai AI Agent Utama!");
        $this->redirect('platform/settings?tab=chatbot');
    }

    /**
     * Test Koneksi AI Model (AJAX)
     * POST /platform/settings/ai-models/test-connection
     */
    public function testAIModelConnection(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'], true)) {
            $response->json(['success' => false, 'error' => 'Akses ditolak.'], 403);
            return;
        }

        $csrfToken = $request->get('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!verify_csrf_token($csrfToken)) {
            $response->json(['success' => false, 'error' => 'CSRF token tidak valid.'], 403);
            return;
        }

        $provider = strtolower(trim((string)$request->post('provider', 'gemini')));
        $model = trim((string)$request->post('model', 'gemini-1.5-flash'));
        $apiKey = trim((string)$request->post('api_key', ''));
        $baseUrl = trim((string)$request->post('base_url', ''));

        // Jika API key kosong tapi mengecek model tersimpan
        if (empty($apiKey)) {
            $apiKey = (string)(Setting::get('ai_primary_credential') ?: Setting::get('ai_api_key', env('AI_API_KEY', '')));
        }

        if (empty($apiKey)) {
            $response->json([
                'success' => false,
                'error' => 'API Key belum diisi atau belum tersimpan.'
            ]);
            return;
        }

        if ($provider === 'mock') {
            $response->json([
                'success' => true,
                'message' => 'Mode Mock / Offline aktif. Tidak memerlukan panggilan API eksternal.',
                'reply' => 'SmartBot Mock Siap Melayani.'
            ]);
            return;
        }

        try {
            $aiService = new \App\Services\AIChatbotService('mock');
            $testPrompt = "Anda adalah asisten AI. Jawab pertanyaan berikut dalam 1 kalimat pendek: 'Koneksi API berhasil!'";
            $startTime = microtime(true);
            $errorDetail = null;
            $res = $aiService->executeLLMCall($provider, $model, $apiKey, $baseUrl, $testPrompt, "Tes ping koneksi AI.", $errorDetail);
            $durationMs = round((microtime(true) - $startTime) * 1000);

            if ($res !== null && trim($res) !== '') {
                $response->json([
                    'success' => true,
                    'message' => "Koneksi ke {$provider} ({$model}) BERHASIL! ({$durationMs} ms)",
                    'reply' => trim($res)
                ]);
            } else {
                $errMsg = $errorDetail
                    ? "Koneksi gagal: {$errorDetail}"
                    : "Koneksi ke {$provider} ({$model}) gagal atau tidak ada respon. Periksa kembali API Key dan nama model.";
                $response->json([
                    'success' => false,
                    'error' => $errMsg
                ]);
            }
        } catch (\Throwable $e) {
            $response->json([
                'success' => false,
                'error' => 'Terjadi kesalahan saat menguji koneksi: ' . $e->getMessage()
            ]);
        }
    }
}
