<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Models\WhatsAppLog;
use App\Models\WhatsAppNotificationSetting;
use App\Models\WhatsAppNotificationRecipient;
use App\Models\WhatsAppNotificationTemplate;
use App\Models\Setting;
use App\Models\Store;
use App\Services\WhatsApp\FonnteWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppGatewayFactory;

class WhatsAppSettingsController extends Controller {
    private WhatsAppLog $waLogModel;
    private WhatsAppNotificationSetting $settingModel;
    private WhatsAppNotificationRecipient $recipientModel;
    private WhatsAppNotificationTemplate $templateModel;
    private Store $storeModel;

    public function __construct() {
        $this->waLogModel = new WhatsAppLog();
        $this->settingModel = new WhatsAppNotificationSetting();
        $this->recipientModel = new WhatsAppNotificationRecipient();
        $this->templateModel = new WhatsAppNotificationTemplate();
        $this->storeModel = new Store();
    }

    /**
     * Get store ID for current authenticated owner_toko / admin_toko
     */
    private function getOwnerStore(): ?array {
        $user = auth_user();
        if (!$user) return null;
        return $this->storeModel->findForUser((int)$user['id'], $user['role']);
    }

    // =========================================================================
    // PLATFORM ADMIN / ADMIN OWNER
    // =========================================================================

    /**
     * Platform WhatsApp Settings & Outbox Management (3 Tabs)
     * GET /platform/whatsapp-settings
     */
    public function platformIndex(Request $request, Response $response): void {
        $user = auth_user();
        $activeTab = strtolower(trim((string)$request->get('tab', 'otp')));
        if (!in_array($activeTab, ['otp', 'transaction', 'activity'])) {
            $activeTab = 'otp';
        }

        // Category Master Switches
        $otpMaster = $this->settingModel->isCategoryEnabled('OTP');
        $trxMaster = $this->settingModel->isCategoryEnabled('TRANSACTION');
        $actMaster = $this->settingModel->isCategoryEnabled('ACTIVITY');

        // Detailed settings
        $otpSettings = $this->settingModel->getCategorySettings('OTP', 'GLOBAL');
        $trxSettings = $this->settingModel->getCategorySettings('TRANSACTION', 'GLOBAL');
        $actSettings = $this->settingModel->getCategorySettings('ACTIVITY', 'GLOBAL');

        // Recipients
        $trxRecipients = $this->recipientModel->getRecipients('TRANSACTION', 'GLOBAL');
        $actRecipients = $this->recipientModel->getRecipients('ACTIVITY', 'GLOBAL');

        // Filter parameters for outbox logs
        $logCategory = strtoupper($activeTab);
        $statusFilter = trim((string)$request->get('status', ''));
        $searchFilter = trim((string)$request->get('search', ''));
        $storeFilter = $request->get('store_id') !== null && is_numeric($request->get('store_id')) ? (int)$request->get('store_id') : null;

        $logs = $this->waLogModel->getFilteredLogs(
            $logCategory,
            $storeFilter,
            $statusFilter ?: null,
            $searchFilter ?: null,
            50,
            false // Platform Admin can see OTP
        );

        $todayStats = $this->waLogModel->getTodayStats(null, $logCategory);

        // Gateway configuration status
        $gatewayType = strtolower((string)env('WHATSAPP_GATEWAY', env('WA_GATEWAY', 'simulated')));
        $token = trim((string)env('FONNTE_TOKEN', env('WA_API_KEY', env('WA_API_TOKEN', ''))));
        $hasToken = !empty($token);

        $deviceInfo = ['checked' => false, 'connected' => false, 'status' => 'NOT_CHECKED', 'detail' => ''];
        if ($gatewayType === 'fonnte' && $hasToken) {
            try {
                $gateway = new FonnteWhatsAppGateway($token);
                $deviceRes = $gateway->checkDevice();
                $deviceInfo = array_merge(['checked' => true], $deviceRes);
            } catch (\Throwable $e) {
                $deviceInfo = ['checked' => true, 'connected' => false, 'status' => 'ERROR', 'detail' => $e->getMessage()];
            }
        }

        $allStores = [];
        try {
            $db = Database::getConnection();
            $allStores = $db->query("SELECT id, name FROM `stores` ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        $this->render('platform/whatsapp_settings', [
            'title' => 'Pengaturan Notifikasi WhatsApp - ' . APP_NAME,
            'user' => $user,
            'activeTab' => $activeTab,
            'otpMaster' => $otpMaster,
            'trxMaster' => $trxMaster,
            'actMaster' => $actMaster,
            'otpSettings' => $otpSettings,
            'trxSettings' => $trxSettings,
            'actSettings' => $actSettings,
            'trxRecipients' => $trxRecipients,
            'actRecipients' => $actRecipients,
            'logs' => $logs,
            'todayStats' => $todayStats,
            'gatewayType' => $gatewayType,
            'hasToken' => $hasToken,
            'deviceInfo' => $deviceInfo,
            'statusFilter' => $statusFilter,
            'searchFilter' => $searchFilter,
            'storeFilter' => $storeFilter,
            'allStores' => $allStores,
            'templateModel' => $this->templateModel
        ], 'layouts/main');
    }

    /**
     * Save Platform Notification Settings (Master switch, event toggles, templates)
     * POST /platform/whatsapp-settings/update
     */
    public function savePlatformSettings(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user || !in_array($user['role'], ['owner_platform', 'admin_owner'])) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('platform/whatsapp-settings');
            return;
        }

        $tab = strtolower(trim((string)$request->get('tab', 'otp')));
        $category = strtoupper($tab);

        // 1. Update Master Switch
        $masterEnabled = !empty($request->get('master_enabled')) ? 1 : 0;
        $this->settingModel->setSetting('GLOBAL', null, $category, 'master', (bool)$masterEnabled);

        if ($category === 'OTP') {
            Setting::set('wa_otp_enabled', $masterEnabled, (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $expiry = (int)$request->get('otp_expiry_minutes', 5);
            Setting::set('otp_expiry_minutes', max(1, min(60, $expiry)), (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        } elseif ($category === 'TRANSACTION') {
            Setting::set('whatsapp_notification_enabled', $masterEnabled, (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        } elseif ($category === 'ACTIVITY') {
            Setting::set('wa_activity_enabled', $masterEnabled, (int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        }

        // 2. Update Event Toggles and Templates
        $events = $request->get('events', []);
        $templates = $request->get('templates', []);

        if (is_array($events)) {
            foreach ($events as $evKey => $val) {
                $evKey = strtolower(trim($evKey));
                $isEnabled = (bool)$val;
                $tpl = isset($templates[$evKey]) ? trim($templates[$evKey]) : null;
                $this->settingModel->setSetting('GLOBAL', null, $category, $evKey, $isEnabled, $tpl);
            }
        }

        set_flash('success', "Pengaturan notifikasi kategori " . e($category) . " berhasil disimpan.");
        $this->redirect("platform/whatsapp-settings?tab={$tab}");
    }

    // =========================================================================
    // STORE OWNER
    // =========================================================================

    /**
     * Store WhatsApp Settings & Outbox Management (3 Tabs, Scoped)
     * GET /store/whatsapp-settings
     */
    public function storeIndex(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Data toko Anda tidak ditemukan.');
            $this->redirect('dashboard');
            return;
        }

        $storeId = (int)$store['id'];
        $activeTab = strtolower(trim((string)$request->get('tab', 'transaction')));
        if (!in_array($activeTab, ['otp', 'transaction', 'activity'])) {
            $activeTab = 'transaction';
        }

        // Store Category Master Switches
        $trxMaster = $this->settingModel->isCategoryEnabled('TRANSACTION', $storeId);
        $actMaster = $this->settingModel->isCategoryEnabled('ACTIVITY', $storeId);

        // Store Settings
        $trxSettings = $this->settingModel->getCategorySettings('TRANSACTION', 'STORE', $storeId);
        $actSettings = $this->settingModel->getCategorySettings('ACTIVITY', 'STORE', $storeId);

        // Store Recipients
        $trxRecipients = $this->recipientModel->getRecipients('TRANSACTION', 'STORE', $storeId);
        $actRecipients = $this->recipientModel->getRecipients('ACTIVITY', 'STORE', $storeId);

        // Filter parameters for outbox logs
        $logCategory = strtoupper($activeTab);
        $statusFilter = trim((string)$request->get('status', ''));
        $searchFilter = trim((string)$request->get('search', ''));

        // Anti-IDOR: strictly enforce store_id = my_store_id and exclude OTP
        $logs = [];
        if ($activeTab !== 'otp') {
            $logs = $this->waLogModel->getFilteredLogs(
                $logCategory,
                $storeId,
                $statusFilter ?: null,
                $searchFilter ?: null,
                50,
                true // Exclude OTP strictly
            );
        }

        $todayStats = $this->waLogModel->getTodayStats($storeId, $activeTab !== 'otp' ? $logCategory : null);

        $this->render('store/whatsapp_settings', [
            'title' => 'Pengaturan Notifikasi WhatsApp Toko - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'activeTab' => $activeTab,
            'trxMaster' => $trxMaster,
            'actMaster' => $actMaster,
            'trxSettings' => $trxSettings,
            'actSettings' => $actSettings,
            'trxRecipients' => $trxRecipients,
            'actRecipients' => $actRecipients,
            'logs' => $logs,
            'todayStats' => $todayStats,
            'statusFilter' => $statusFilter,
            'searchFilter' => $searchFilter,
            'templateModel' => $this->templateModel
        ], 'layouts/main');
    }

    /**
     * Save Store Notification Settings
     * POST /store/whatsapp-settings/update
     */
    public function saveStoreSettings(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->getOwnerStore();
        if (!$store) {
            set_flash('error', 'Akses ditolak.');
            $this->redirect('dashboard');
            return;
        }

        $storeId = (int)$store['id'];
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/whatsapp-settings');
            return;
        }

        $tab = strtolower(trim((string)$request->get('tab', 'transaction')));
        if ($tab === 'otp') {
            set_flash('error', 'Konfigurasi OTP dikelola secara terpusat oleh Admin Platform demi keamanan.');
            $this->redirect('store/whatsapp-settings?tab=otp');
            return;
        }

        $category = strtoupper($tab);

        // Master switch for store
        $masterEnabled = !empty($request->get('master_enabled')) ? 1 : 0;
        $this->settingModel->setSetting('STORE', $storeId, $category, 'master', (bool)$masterEnabled);

        // Event settings & custom templates
        $events = $request->get('events', []);
        $templates = $request->get('templates', []);

        if (is_array($events)) {
            foreach ($events as $evKey => $val) {
                $evKey = strtolower(trim($evKey));
                $isEnabled = (bool)$val;
                $tpl = isset($templates[$evKey]) ? trim($templates[$evKey]) : null;
                $this->settingModel->setSetting('STORE', $storeId, $category, $evKey, $isEnabled, $tpl);
            }
        }

        set_flash('success', "Pengaturan notifikasi {$category} toko berhasil disimpan.");
        $this->redirect("store/whatsapp-settings?tab={$tab}");
    }

    // =========================================================================
    // RECIPIENT MANAGEMENT (ADD / DELETE / TOGGLE)
    // =========================================================================

    /**
     * Add notification recipient
     * POST /platform/whatsapp-settings/recipients/add
     * POST /store/whatsapp-settings/recipients/add
     */
    public function addRecipient(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirectBack();
            return;
        }

        $isPlatformAdmin = in_array($user['role'], ['owner_platform', 'admin_owner']);
        $category = strtoupper(trim((string)$request->get('category', 'TRANSACTION')));
        $name = trim((string)$request->get('recipient_name', ''));
        $phone = trim((string)$request->get('phone_number', ''));
        $role = trim((string)$request->get('role', 'STAFF'));

        if (empty($name) || empty($phone)) {
            set_flash('error', 'Nama penerima dan nomor WhatsApp wajib diisi.');
            $this->redirectBack();
            return;
        }

        $normalizedPhone = normalize_phone_number($phone);
        if (empty($normalizedPhone)) {
            set_flash('error', 'Format nomor WhatsApp tidak valid.');
            $this->redirectBack();
            return;
        }

        if ($isPlatformAdmin) {
            $scope = 'GLOBAL';
            $storeId = null;
        } else {
            $store = $this->getOwnerStore();
            if (!$store) {
                set_flash('error', 'Data toko tidak ditemukan.');
                $this->redirect('dashboard');
                return;
            }
            $scope = 'STORE';
            $storeId = (int)$store['id'];
        }

        $res = $this->recipientModel->addRecipient($scope, $storeId, $category, $name, $normalizedPhone, $role);
        if ($res > 0) {
            set_flash('success', "Nomor penerima WhatsApp {$name} ({$normalizedPhone}) berhasil ditambahkan.");
        } else {
            set_flash('error', 'Gagal menambahkan nomor penerima.');
        }

        $this->redirectBack();
    }

    /**
     * Delete notification recipient (Anti-IDOR protected)
     * POST /platform/whatsapp-settings/recipients/{id}/delete
     * POST /store/whatsapp-settings/recipients/{id}/delete
     */
    public function deleteRecipient(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirectBack();
            return;
        }

        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $isPlatformAdmin = in_array($user['role'], ['owner_platform', 'admin_owner']);
        $storeId = null;
        if (!$isPlatformAdmin) {
            $store = $this->getOwnerStore();
            $storeId = $store ? (int)$store['id'] : null;
        }

        $success = $this->recipientModel->deleteRecipient((int)$id, $storeId, $isPlatformAdmin);
        if ($success) {
            set_flash('success', 'Nomor penerima berhasil dihapus.');
        } else {
            set_flash('error', 'Gagal menghapus nomor penerima atau akses ditolak.');
        }

        $this->redirectBack();
    }

    /**
     * Toggle notification recipient active state (Anti-IDOR protected)
     * POST /platform/whatsapp-settings/recipients/{id}/toggle
     * POST /store/whatsapp-settings/recipients/{id}/toggle
     */
    public function toggleRecipient(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $isPlatformAdmin = in_array($user['role'], ['owner_platform', 'admin_owner']);
        $storeId = null;
        if (!$isPlatformAdmin) {
            $store = $this->getOwnerStore();
            $storeId = $store ? (int)$store['id'] : null;
        }

        $isEnabled = !empty($request->get('is_enabled'));
        $success = $this->recipientModel->toggleRecipient((int)$id, $isEnabled, $storeId, $isPlatformAdmin);

        if ($request->isAjax()) {
            $response->json(['success' => $success]);
            return;
        }

        if ($success) {
            set_flash('success', 'Status penerima berhasil diubah.');
        } else {
            set_flash('error', 'Gagal mengubah status penerima.');
        }

        $this->redirectBack();
    }

    /**
     * Test Send Message (rate limited)
     */
    public function testSend(Request $request, Response $response): void {
        $logCtrl = new WhatsAppLogController();
        $logCtrl->testSend($request, $response);
    }

    /**
     * Add new sequential message template for an event
     * POST /platform/whatsapp-settings/messages/add
     * POST /store/whatsapp-settings/messages/add
     */
    public function addEventMessage(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirectBack();
            return;
        }

        $user = auth_user();
        if (!$user) {
            $this->redirect('login');
            return;
        }

        $isPlatform = in_array($user['role'], ['owner_platform', 'admin_owner']);
        $storeId = null;
        $scope = 'GLOBAL';

        if (!$isPlatform) {
            $store = $this->getOwnerStore();
            if (!$store) {
                set_flash('error', 'Akses ditolak.');
                $this->redirect('dashboard');
                return;
            }
            $storeId = (int)$store['id'];
            $scope = 'STORE';
        }

        $category = strtoupper(trim((string)$request->get('category', 'TRANSACTION')));
        if (!$isPlatform && $category === 'OTP') {
            set_flash('error', 'Store Owner tidak memiliki izin mengelola template OTP.');
            $this->redirectBack();
            return;
        }

        $eventKey = strtolower(trim((string)$request->get('event_key', '')));
        $templateContent = trim((string)$request->get('template_content', ''));
        $title = trim((string)$request->get('title', ''));
        $sequence = (int)$request->get('message_sequence', 0);
        $isEnabled = !empty($request->get('is_enabled'));

        if (empty($eventKey) || empty($templateContent)) {
            set_flash('error', 'Event dan isi template pesan tidak boleh kosong.');
            $this->redirectBack();
            return;
        }

        if ($sequence <= 0) {
            $sequence = $this->templateModel->getNextSequence($scope, $storeId, $category, $eventKey);
        }

        if (empty($title)) {
            $title = "Pesan {$sequence}";
        }

        $newId = $this->templateModel->createMessage([
            'scope' => $scope,
            'store_id' => $storeId,
            'category' => $category,
            'event_key' => $eventKey,
            'message_sequence' => $sequence,
            'title' => $title,
            'template_content' => $templateContent,
            'is_enabled' => $isEnabled ? 1 : 0
        ]);

        if ($newId) {
            set_flash('success', "Pesan {$sequence} ({$title}) berhasil ditambahkan untuk event {$eventKey}.");
        } else {
            set_flash('error', 'Gagal menambahkan pesan ke database.');
        }

        $this->redirectBack();
    }

    /**
     * Update an event message template
     * POST /platform/whatsapp-settings/messages/{id}/update
     * POST /store/whatsapp-settings/messages/{id}/update
     */
    public function updateEventMessage(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirectBack();
            return;
        }

        $user = auth_user();
        if (!$user) {
            $this->redirect('login');
            return;
        }

        $isPlatform = in_array($user['role'], ['owner_platform', 'admin_owner']);
        $storeId = null;

        if (!$isPlatform) {
            $store = $this->getOwnerStore();
            $storeId = $store ? (int)$store['id'] : null;
        }

        $title = trim((string)$request->get('title', ''));
        $content = trim((string)$request->get('template_content', ''));
        $sequence = $request->get('message_sequence');
        $isEnabled = !empty($request->get('is_enabled'));

        $updateData = [
            'is_enabled' => $isEnabled ? 1 : 0
        ];
        if (!empty($title)) $updateData['title'] = $title;
        if (!empty($content)) $updateData['template_content'] = $content;
        if ($sequence !== null && is_numeric($sequence)) $updateData['message_sequence'] = (int)$sequence;

        $ok = $this->templateModel->updateMessage((int)$id, $updateData, $storeId, $isPlatform);
        if ($ok) {
            set_flash('success', 'Template pesan berhasil diperbarui.');
        } else {
            set_flash('error', 'Gagal memperbarui pesan atau akses ditolak.');
        }

        $this->redirectBack();
    }

    /**
     * Delete an event message template
     * POST /platform/whatsapp-settings/messages/{id}/delete
     * POST /store/whatsapp-settings/messages/{id}/delete
     */
    public function deleteEventMessage(Request $request, Response $response, string $id): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirectBack();
            return;
        }

        $user = auth_user();
        if (!$user) {
            $this->redirect('login');
            return;
        }

        $isPlatform = in_array($user['role'], ['owner_platform', 'admin_owner']);
        $storeId = null;

        if (!$isPlatform) {
            $store = $this->getOwnerStore();
            $storeId = $store ? (int)$store['id'] : null;
        }

        $ok = $this->templateModel->deleteMessage((int)$id, $storeId, $isPlatform);
        if ($ok) {
            set_flash('success', 'Pesan berhasil dihapus dari alur notifikasi.');
        } else {
            set_flash('error', 'Gagal menghapus pesan atau akses ditolak.');
        }

        $this->redirectBack();
    }

    /**
     * Toggle single message active state
     * POST /platform/whatsapp-settings/messages/{id}/toggle
     * POST /store/whatsapp-settings/messages/{id}/toggle
     */
    public function toggleEventMessage(Request $request, Response $response, string $id): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $isPlatform = in_array($user['role'], ['owner_platform', 'admin_owner']);
        $storeId = null;
        if (!$isPlatform) {
            $store = $this->getOwnerStore();
            $storeId = $store ? (int)$store['id'] : null;
        }

        $isEnabled = !empty($request->get('is_enabled'));
        $ok = $this->templateModel->toggleMessage((int)$id, $isEnabled, $storeId, $isPlatform);

        if ($request->isAjax()) {
            $response->json(['success' => $ok]);
            return;
        }

        if ($ok) {
            set_flash('success', 'Status pesan berhasil diubah.');
        } else {
            set_flash('error', 'Gagal mengubah status pesan.');
        }

        $this->redirectBack();
    }

    /**
     * Preview template rendering with sample variables
     * POST /platform/whatsapp-settings/messages/preview
     * POST /store/whatsapp-settings/messages/preview
     */
    public function previewEventMessage(Request $request, Response $response): void {
        $tpl = (string)$request->get('template_content', '');
        $waService = new \App\Services\WhatsAppNotificationService();
        $sampleData = [
            'nama_customer' => 'Budi Santoso',
            'nomor_pesanan' => 'ORD-202611-001',
            'nama_kurir'    => 'Ahmad Fauzi',
            'wa_kurir'      => '081234567890',
            'plat_nomor'    => 'B 1234 XYZ',
            'jenis_kendaraan' => 'Motor Honda Vario',
            'store_name'    => 'CleanWash Express',
            'nama_layanan'  => 'Cuci + Setrika',
            'total'         => 45000,
            'weight'        => 3.5,
            'otp_code'      => '582910',
            'expiry_minutes' => 5,
            'actor_name'    => 'Hendra Wijaya',
            'actor_role'    => 'Owner Toko',
            'harga_lama'    => 'Rp 8.000',
            'harga_baru'    => 'Rp 10.000',
            'harga'         => 'Rp 10.000',
            'unit'          => 'kg',
            'timestamp'     => date('d-m-Y H:i'),
            'link'          => ' https://smartlaundry.pro/tracking/ORD-202611-001'
        ];

        $rendered = $waService->renderCustomerMessage($tpl, $sampleData);
        $rendered = str_replace(
            ['{otp_code}', '{expiry_minutes}', '{actor_name}', '{actor_role}', '{harga_lama}', '{harga_baru}', '{harga}', '{unit}', '{timestamp}'],
            ['582910', '5', 'Hendra Wijaya', 'Owner Toko', 'Rp 8.000', 'Rp 10.000', 'Rp 10.000', 'kg', date('d-m-Y H:i')],
            $rendered
        );

        $response->json(['success' => true, 'preview' => $rendered]);
    }

    /**
     * Send test message for this specific sequence
     * POST /platform/whatsapp-settings/messages/test-send
     * POST /store/whatsapp-settings/messages/test-send
     */
    public function testSendEventMessage(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            $response->json(['success' => false, 'message' => 'Token CSRF tidak valid.'], 403);
            return;
        }

        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $targetPhone = trim((string)$request->get('phone', ''));
        if (empty($targetPhone)) {
            $targetPhone = !empty($user['phone']) ? $user['phone'] : '081234567890';
        }

        $tpl = (string)$request->get('template_content', '');
        $seq = (int)$request->get('message_sequence', 1);
        $eventKey = (string)$request->get('event_key', 'test_event');

        $waService = new \App\Services\WhatsAppNotificationService();
        $sampleData = [
            'nama_customer' => $user['name'] ?? 'Tester',
            'nomor_pesanan' => 'TEST-' . date('YmdHi'),
            'nama_kurir'    => 'Kurir Uji Coba',
            'wa_kurir'      => '081299988877',
            'plat_nomor'    => 'B 9999 TST',
            'jenis_kendaraan' => 'Motor Honda Vario',
            'store_name'    => 'SmartLaundry Outlet',
            'nama_layanan'  => 'Cuci + Setrika Kilat',
            'total'         => 35000,
            'weight'        => 2.5,
            'link'          => ''
        ];

        $rendered = $waService->renderCustomerMessage($tpl, $sampleData);

        $gateway = WhatsAppGatewayFactory::create();
        $result = $gateway->sendMessage($targetPhone, $rendered);

        $this->waLogModel->create([
            'phone'           => normalize_phone_number($targetPhone),
            'recipient_name'  => $user['name'] ?? 'Tester',
            'event'           => strtoupper($eventKey) . ':TEST_SEQ' . $seq,
            'category'        => 'TRANSACTION',
            'message'         => $rendered,
            'status'          => $result['status'] ?? 'SIMULATED',
            'attempts'        => 1,
            'last_error'      => $result['error'] ?? null,
            'sent_at'         => date('Y-m-d H:i:s')
        ]);

        $response->json([
            'success' => ($result['status'] === 'SENT' || $result['status'] === 'SIMULATED'),
            'status'  => $result['status'] ?? 'UNKNOWN',
            'message' => 'Pesan uji coba terkirim (' . ($result['status'] ?? 'SIMULATED') . ')'
        ]);
    }

    /**
     * Save WA phone override settings (Mode Pengujian)
     * POST /platform/whatsapp-settings/override  dan  /store/whatsapp-settings/override
     */
    public function saveOverrideSettings(Request $request, Response $response): void {
        $user = auth_user();
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Tidak terautentikasi.'], 401);
            return;
        }

        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirectBack();
            return;
        }

        // Hanya owner_platform, admin_owner, dan owner_toko yang boleh mengubah setting override
        $allowed = ['owner_platform', 'admin_owner', 'owner_toko'];
        if (!in_array($user['role'], $allowed, true)) {
            set_flash('error', 'Akses ditolak.');
            $this->redirectBack();
            return;
        }

        $overrideEnabled = !empty($request->get('wa_override_enabled')) ? '1' : '0';
        $customerPhone   = trim((string)$request->get('wa_override_customer_phone', ''));
        $courierPhone    = trim((string)$request->get('wa_override_courier_phone', ''));
        $adminPhone      = trim((string)$request->get('wa_override_admin_phone', ''));

        // Validate phone numbers (basic format check)
        $phoneFields = ['wa_override_customer_phone' => $customerPhone, 'wa_override_courier_phone' => $courierPhone, 'wa_override_admin_phone' => $adminPhone];
        foreach ($phoneFields as $field => $val) {
            if (!empty($val)) {
                $norm = normalize_phone_number($val);
                if (empty($norm)) {
                    set_flash('error', "Format nomor WhatsApp tidak valid: {$val}");
                    $this->redirectBack();
                    return;
                }
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $uid = (int)$user['id'];
        \App\Models\Setting::set('wa_override_enabled',        $overrideEnabled, $uid, $ip);
        \App\Models\Setting::set('wa_override_customer_phone', $customerPhone,   $uid, $ip);
        \App\Models\Setting::set('wa_override_courier_phone',  $courierPhone,    $uid, $ip);
        \App\Models\Setting::set('wa_override_admin_phone',    $adminPhone,      $uid, $ip);

        $mode = $overrideEnabled === '1' ? 'AKTIF' : 'NONAKTIF';
        set_flash('success', "Mode Override Nomor WA berhasil disimpan ({$mode}).");
        $this->redirectBack();
    }

    private function redirectBack(): void {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referer)) {
            header('Location: ' . $referer);
            exit;
        }
        $user = auth_user();
        if ($user && in_array($user['role'], ['owner_platform', 'admin_owner'])) {
            $this->redirect('platform/whatsapp-settings');
        } else {
            $this->redirect('store/whatsapp-settings');
        }
    }
}
