<?php
/**
 * SmartLaundry AI Marketplace - Front Controller & Route Registrar
 */

// 1. Load Environment Configuration
require_once __DIR__ . '/config/env.php';
load_env(__DIR__ . '/.env');

// Error reporting & Global Exception Handling
if (env('APP_ENV', 'production') === 'development' || env('APP_ENV', 'production') === 'local') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/storage/logs/error.log');
}

// Global Exception Handler
set_exception_handler(function (\Throwable $e) {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    $logMessage .= "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    
    // Log to file
    $logDir = __DIR__ . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    error_log($logMessage, 3, $logDir . '/error.log');

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        || (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (!empty($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        || (stripos($uri, '/api/') !== false);
    http_response_code(500);

    if ($isAjax) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        $errorMsg = (env('APP_ENV', 'production') === 'development' || env('APP_ENV', 'production') === 'local')
            ? $e->getMessage()
            : 'Terjadi gangguan internal pada server. Silakan coba lagi nanti.';
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'error' => $errorMsg,
            'message' => $errorMsg
        ]);
        exit;
    }

    // Show 500 page
    if (env('APP_ENV', 'production') === 'development' || env('APP_ENV', 'production') === 'local') {
        echo "<h1>500 Internal Server Error</h1>";
        echo "<pre>" . htmlspecialchars($logMessage) . "</pre>";
    } else {
        // Load the 500 view if possible, otherwise generic text
        if (file_exists(__DIR__ . '/app/Core/View.php')) {
            require_once __DIR__ . '/app/Core/View.php';
            try {
                \App\Core\View::render('errors/500', ['title' => '500 - Internal Server Error'], 'layouts/main');
                exit;
            } catch (\Throwable $innerException) {
                // Ignore if view rendering fails
            }
        }
        echo "<h1>500 - Internal Server Error</h1>";
        echo "<p>Something went wrong on our end. Please try again later.</p>";
    }
});

// Base Paths
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('VIEW_PATH', APP_PATH . '/Views');



// 2. Load App Configuration & Global Helpers
require_once ROOT_PATH . '/config/app.php';

// 3. Class Autoloader (PSR-4 Style for App Namespace)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AddressController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\StoreProfileController;
use App\Controllers\ServiceController;
use App\Controllers\CourierManagementController;
use App\Controllers\TrackingController;
use App\Controllers\ReviewController;
use App\Controllers\NotificationController;
use App\Controllers\StoreDetailController;
use App\Controllers\WhatsAppLogController;
use App\Controllers\RefundController;
use App\Controllers\CourierAssignmentController;
use App\Controllers\ChatbotController;
use App\Controllers\ChatbotLogController;
use App\Controllers\WebhookController;
use App\Controllers\PlatformSettingsController;
use App\Controllers\StoreSettingsController;
use App\Controllers\StoreExpenseController;
use App\Controllers\WhatsAppSettingsController;
use App\Models\Setting;
// 4. Initialize HTTP Request & Response
$request = new Request();
$response = new Response();
$router = new Router($request, $response);

// Maintenance Mode Interceptor
if ((bool)Setting::get('maintenance_mode', false)) {
    $currentPath = $request->getPath();
    $allowedPaths = ['/login', '/logout', '/platform/settings', '/platform/settings/toggle-maintenance'];
    $isAllowed = in_array($currentPath, $allowedPaths) || strpos($currentPath, '/webhook') === 0;

    $user = auth_user();
    $role = $user['role_name'] ?? ($user['role'] ?? '');
    $isPlatformStaff = in_array($role, ['owner_platform', 'admin_owner']);

    if (!$isPlatformStaff && !$isAllowed) {
        http_response_code(503);
        $message = Setting::get('maintenance_message', 'Sistem sedang dalam pemeliharaan berkala untuk meningkatkan kualitas layanan. Kami akan segera kembali.');
        if ($request->isAjax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'status' => 'maintenance',
                'message' => $message,
                'error' => '503 Service Unavailable (Mode Pemeliharaan)'
            ]);
            exit;
        }
        require VIEW_PATH . '/errors/503.php';
        exit;
    }
}

// --------------------------------------------------------------------------
// 5. PUBLIC & GUEST ROUTES
// --------------------------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/home', [HomeController::class, 'index']);
$router->get('/stores', [HomeController::class, 'stores']);
$router->get('/about', [HomeController::class, 'about']);

// Auth Routes (Protected by Guest Middleware)
$router->get('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/login', [AuthController::class, 'processLogin'], ['guest']);
$router->get('/register', [AuthController::class, 'register'], ['guest']);
$router->post('/register', [AuthController::class, 'processRegister'], ['guest']);
$router->get('/logout', [AuthController::class, 'logout']);

// OTP Verification Routes
$router->get('/verify-otp', [AuthController::class, 'showVerifyOtp']);
$router->post('/verify-otp', [AuthController::class, 'processVerifyOtp']);
$router->post('/otp/resend', [AuthController::class, 'resendOtp']);

// Google OAuth / Simulation Routes
$router->get('/auth/google', [AuthController::class, 'googleAuth']);
$router->get('/auth/google/callback', [AuthController::class, 'googleAuth']);

// --------------------------------------------------------------------------
// 6. PROTECTED DASHBOARD & ORDER ROUTES (RBAC Protected)
// --------------------------------------------------------------------------
// Global Dashboard redirector
$router->get('/dashboard', function ($req, $res) {
    if (!is_authenticated()) {
        redirect('login');
    }
    $role = user_role();
    redirect(get_dashboard_url_by_role($role));
}, ['auth']);

// Owner Platform & Admin Owner Dashboard
$router->get('/dashboard/platform', [DashboardController::class, 'platform'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/stores/{id}/toggle-verify', [DashboardController::class, 'toggleStoreVerify'], ['auth', 'role:owner_platform,admin_owner']);

// Store Owner & Store Admin Dashboard
$router->get('/dashboard/store', [DashboardController::class, 'store'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/accept', [DashboardController::class, 'acceptOrder'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/reject', [DashboardController::class, 'rejectOrder'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/weigh', [DashboardController::class, 'submitWeighing'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/confirm-payment', [DashboardController::class, 'confirmPayment'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/update-status', [DashboardController::class, 'updateOrderStatus'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/send-whatsapp', [DashboardController::class, 'sendOrderWhatsAppNotification'], ['auth', 'role:owner_toko,admin_toko,owner_platform,admin_owner']);
$router->get('/store/orders/{id}/send-whatsapp', [DashboardController::class, 'redirectStoreDashboard'], ['auth', 'role:owner_toko,admin_toko,owner_platform,admin_owner']);
$router->post('/store/orders/{id}/manual-assign-courier', [CourierAssignmentController::class, 'manualAssign'], ['auth', 'role:owner_toko,admin_toko']);

// Store Expired Payments & Return Approval Routes (Tahap 11)
$router->get('/store/refunds', [RefundController::class, 'index'], ['auth', 'role:owner_toko,admin_toko']);
$router->get('/store/orders/{id}/refund-approval', [RefundController::class, 'showApproval'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/refund-approve', [RefundController::class, 'processApproval'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/orders/{id}/assign-refund-courier', [RefundController::class, 'assignCourier'], ['auth', 'role:owner_toko,admin_toko']);

// Courier Task Offers: Accept / Reject (Tahap 12)
$router->post('/courier/offers/{id}/accept', [CourierAssignmentController::class, 'acceptOffer'], ['auth', 'role:kurir']);
$router->post('/courier/offers/{id}/reject', [CourierAssignmentController::class, 'rejectOffer'], ['auth', 'role:kurir']);

// Courier Dashboard & Granular Actions (Tahap 9)
$router->get('/dashboard/courier', [DashboardController::class, 'courier'], ['auth', 'role:kurir']);
$router->post('/courier/toggle-availability', [DashboardController::class, 'toggleCourierAvailability'], ['auth', 'role:kurir']);
$router->post('/courier/orders/{id}/start-pickup', [DashboardController::class, 'startPickup'], ['auth', 'role:kurir']);
$router->post('/courier/orders/{id}/confirm-picked', [DashboardController::class, 'confirmPicked'], ['auth', 'role:kurir']);
$router->post('/courier/orders/{id}/start-travel-store', [DashboardController::class, 'startTravelToStore'], ['auth', 'role:kurir']);
$router->post('/courier/orders/{id}/arrive-store', [DashboardController::class, 'arriveAtStore'], ['auth', 'role:kurir']);
$router->post('/courier/orders/{id}/complete-pickup', [DashboardController::class, 'arriveAtStore'], ['auth', 'role:kurir']); // Alias for backward compat
$router->post('/courier/orders/{id}/start-delivery', [DashboardController::class, 'startDelivery'], ['auth', 'role:kurir']);
$router->post('/courier/orders/{id}/complete-delivery', [DashboardController::class, 'completeDelivery'], ['auth', 'role:kurir']);

// Courier Refund Return Execution Actions (Tahap 11)
$router->post('/courier/orders/{id}/start-refund-delivery', [RefundController::class, 'startReturnDelivery'], ['auth', 'role:kurir']);
$router->post('/courier/orders/{id}/confirm-refund-returned', [RefundController::class, 'confirmReturn'], ['auth', 'role:kurir']);

// Real-Time GPS Tracking & Location Polling (Tahap 9)
$router->get('/order/{id}/courier-location', [TrackingController::class, 'getCourierLocation'], ['auth']);
$router->post('/courier/update-location', [TrackingController::class, 'updateCourierLocation'], ['auth', 'role:kurir']);
$router->get('/store/couriers/live-locations', [TrackingController::class, 'getStoreCourierLocations'], ['auth', 'role:owner_toko,admin_toko']);


// Customer Dashboard & Actions
$router->get('/dashboard/customer', [DashboardController::class, 'customer'], ['auth', 'role:customer']);
$router->post('/customer/orders/{id}/confirm-received', [DashboardController::class, 'confirmCustomerReceived'], ['auth', 'role:customer']);
$router->post('/customer/orders/{id}/reorder', [DashboardController::class, 'reorder'], ['auth', 'role:customer']);

// Customer Addresses & Profile (Tahap 5)
$router->get('/customer/addresses', [AddressController::class, 'index'], ['auth', 'role:customer']);
$router->get('/customer/addresses/create', [AddressController::class, 'create'], ['auth', 'role:customer']);
$router->post('/customer/addresses', [AddressController::class, 'store'], ['auth', 'role:customer']);
$router->get('/customer/addresses/{id}/edit', [AddressController::class, 'edit'], ['auth', 'role:customer']);
$router->post('/customer/addresses/{id}/update', [AddressController::class, 'update'], ['auth', 'role:customer']);
$router->post('/customer/addresses/{id}/delete', [AddressController::class, 'delete'], ['auth', 'role:customer']);
$router->post('/customer/addresses/{id}/set-primary', [AddressController::class, 'setPrimary'], ['auth', 'role:customer']);
$router->get('/customer/profile', [AddressController::class, 'profile'], ['auth', 'role:customer']);
$router->post('/customer/profile/update', [AddressController::class, 'updateProfile'], ['auth', 'role:customer']);

// Customer Order Checkout Flow (Tahap 6)
$router->get('/order/create', [OrderController::class, 'create'], ['auth', 'role:customer']);
$router->post('/order/store', [OrderController::class, 'store'], ['auth', 'role:customer']);
$router->get('/order/{id}/confirmation', [OrderController::class, 'confirmation'], ['auth', 'role:customer']);

// Payment & Invoice Routes (Tahap 7)
$router->get('/order/{id}/payment', [PaymentController::class, 'show'], ['auth']);
$router->get('/order/{id}/payment/status', [PaymentController::class, 'status'], ['auth']);
$router->post('/order/{id}/payment/regenerate', [PaymentController::class, 'regenerate'], ['auth', 'role:customer']);
$router->post('/payment/{id}/simulate-paid', [PaymentController::class, 'simulatePaid'], ['auth']);
$router->get('/order/{id}/invoice', [PaymentController::class, 'invoice'], ['auth']);

// Store Management Routes (Tahap 8 - owner_toko ONLY)
// 1. Profil Toko & Lokasi Alamat Outlet
$router->get('/store/profile', [StoreProfileController::class, 'profile'], ['auth', 'role:owner_toko']);
$router->post('/store/profile', [StoreProfileController::class, 'updateProfile'], ['auth', 'role:owner_toko']);

// 2. Katalog Layanan Laundry
$router->get('/store/services', [ServiceController::class, 'index'], ['auth', 'role:owner_toko']);
$router->get('/store/services/create', [ServiceController::class, 'create'], ['auth', 'role:owner_toko']);
$router->post('/store/services', [ServiceController::class, 'store'], ['auth', 'role:owner_toko']);
$router->get('/store/services/{id}/edit', [ServiceController::class, 'edit'], ['auth', 'role:owner_toko']);
$router->post('/store/services/{id}/update', [ServiceController::class, 'update'], ['auth', 'role:owner_toko']);
$router->post('/store/services/{id}/delete', [ServiceController::class, 'delete'], ['auth', 'role:owner_toko']);
$router->post('/store/services/{id}/toggle', [ServiceController::class, 'toggle'], ['auth', 'role:owner_toko']);

// 3. Manajemen Armada Kurir
$router->get('/store/couriers', [CourierManagementController::class, 'index'], ['auth', 'role:owner_toko']);
$router->get('/store/couriers/create', [CourierManagementController::class, 'create'], ['auth', 'role:owner_toko']);
$router->post('/store/couriers', [CourierManagementController::class, 'store'], ['auth', 'role:owner_toko']);
$router->get('/store/couriers/{id}/edit', [CourierManagementController::class, 'edit'], ['auth', 'role:owner_toko']);
$router->post('/store/couriers/{id}/update', [CourierManagementController::class, 'update'], ['auth', 'role:owner_toko']);
$router->post('/store/couriers/{id}/deactivate', [CourierManagementController::class, 'deactivate'], ['auth', 'role:owner_toko']);
$router->post('/store/couriers/{id}/reactivate', [CourierManagementController::class, 'reactivate'], ['auth', 'role:owner_toko']);

// Review & Rating Routes (Tahap 10)
$router->get('/order/{id}/review', [ReviewController::class, 'create'], ['auth', 'role:customer']);
$router->post('/order/{id}/review', [ReviewController::class, 'store'], ['auth', 'role:customer']);
$router->get('/store/{id}/detail', [StoreDetailController::class, 'show']);

// In-App Notification Center Routes (Tahap 10)
$router->get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount'], ['auth']);
$router->get('/notifications/recent', [NotificationController::class, 'getRecent'], ['auth']);
$router->post('/notifications/{id}/mark-read', [NotificationController::class, 'markRead'], ['auth']);
$router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'], ['auth']);

// Simulated WhatsApp Outbox Logs (Tahap 10 - Platform Admins Only)
$router->get('/platform/whatsapp-logs', [WhatsAppLogController::class, 'index'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-logs/test-send', [WhatsAppLogController::class, 'testSend'], ['auth', 'role:owner_platform']);

// WhatsApp Notification Settings — 3-Tab (Tahap 23)
$router->get('/platform/whatsapp-settings', [WhatsAppSettingsController::class, 'platformIndex'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/update', [WhatsAppSettingsController::class, 'savePlatformSettings'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/recipients/add', [WhatsAppSettingsController::class, 'addRecipient'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/recipients/{id}/delete', [WhatsAppSettingsController::class, 'deleteRecipient'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/recipients/{id}/toggle', [WhatsAppSettingsController::class, 'toggleRecipient'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/messages/add', [WhatsAppSettingsController::class, 'addEventMessage'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/messages/{id}/update', [WhatsAppSettingsController::class, 'updateEventMessage'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/messages/{id}/delete', [WhatsAppSettingsController::class, 'deleteEventMessage'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/messages/{id}/toggle', [WhatsAppSettingsController::class, 'toggleEventMessage'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/messages/test-send', [WhatsAppSettingsController::class, 'testSendEventMessage'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/messages/preview', [WhatsAppSettingsController::class, 'previewEventMessage'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/whatsapp-settings/override', [WhatsAppSettingsController::class, 'saveOverrideSettings'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/store/whatsapp-settings/override', [WhatsAppSettingsController::class, 'saveOverrideSettings'], ['auth', 'role:owner_toko']);

$router->get('/store/whatsapp-settings', [WhatsAppSettingsController::class, 'storeIndex'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/update', [WhatsAppSettingsController::class, 'saveStoreSettings'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/recipients/add', [WhatsAppSettingsController::class, 'addRecipient'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/recipients/{id}/delete', [WhatsAppSettingsController::class, 'deleteRecipient'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/recipients/{id}/toggle', [WhatsAppSettingsController::class, 'toggleRecipient'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/messages/add', [WhatsAppSettingsController::class, 'addEventMessage'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/messages/{id}/update', [WhatsAppSettingsController::class, 'updateEventMessage'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/messages/{id}/delete', [WhatsAppSettingsController::class, 'deleteEventMessage'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/messages/{id}/toggle', [WhatsAppSettingsController::class, 'toggleEventMessage'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/messages/test-send', [WhatsAppSettingsController::class, 'testSendEventMessage'], ['auth', 'role:owner_toko']);
$router->post('/store/whatsapp-settings/messages/preview', [WhatsAppSettingsController::class, 'previewEventMessage'], ['auth', 'role:owner_toko']);

// Store Operational Expenses (Tahap 22)
$router->get('/store/expenses', [StoreExpenseController::class, 'index'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/expenses', [StoreExpenseController::class, 'store'], ['auth', 'role:owner_toko,admin_toko']);

// Chatbot Conversation Monitoring (Tahap 14 - Platform Admins Only)
$router->get('/platform/chatbot-logs', [ChatbotLogController::class, 'index'], ['auth', 'role:owner_platform,admin_owner']);
$router->get('/platform/chatbot-logs/thread', [ChatbotLogController::class, 'thread'], ['auth', 'role:owner_platform,admin_owner']);
$router->get('/platform/chatbot-logs/user-thread', [ChatbotLogController::class, 'userThread'], ['auth', 'role:owner_platform,admin_owner']);
$router->get('/platform/chatbot-logs/export', [ChatbotLogController::class, 'export'], ['auth', 'role:owner_platform,admin_owner']);

// AI Chatbot Assistant API Routes (Tahap 13 - Customer, Kurir & Admins)
$router->post('/api/chatbot/message', [ChatbotController::class, 'sendMessage'], ['auth', 'role:customer,kurir,owner_platform,admin_owner,owner_toko,admin_toko']);
$router->get('/api/chatbot/history', [ChatbotController::class, 'getHistory'], ['auth', 'role:customer,kurir,owner_platform,admin_owner,owner_toko,admin_toko']);
$router->post('/api/chatbot/clear', [ChatbotController::class, 'clearHistory'], ['auth', 'role:customer,kurir,owner_platform,admin_owner,owner_toko,admin_toko']);

// Payment Gateway Webhooks (Tahap 15 - External API, No Auth)
$router->post('/webhook/midtrans', [WebhookController::class, 'midtrans']);

// --------------------------------------------------------------------------
// Platform Settings (Konfigurasi Sistem)
// --------------------------------------------------------------------------
$router->get('/platform/settings', [PlatformSettingsController::class, 'index'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/update', [PlatformSettingsController::class, 'update'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/toggle-maintenance', [PlatformSettingsController::class, 'toggleMaintenance'], ['auth', 'role:owner_platform']);
$router->get('/platform/settings/export', [PlatformSettingsController::class, 'export'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/test-integration', [PlatformSettingsController::class, 'testIntegration'], ['auth', 'role:owner_platform']);
$router->post('/platform/settings/knowledge/store', [PlatformSettingsController::class, 'storeKnowledge'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/knowledge/{id}/toggle', [PlatformSettingsController::class, 'toggleKnowledge'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/ai-models/store', [PlatformSettingsController::class, 'storeAIBackupModel'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/ai-models/{id}/update', [PlatformSettingsController::class, 'updateAIBackupModel'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/ai-models/{id}/delete', [PlatformSettingsController::class, 'deleteAIBackupModel'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/ai-models/{id}/toggle', [PlatformSettingsController::class, 'toggleAIBackupModel'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/ai-models/{id}/promote', [PlatformSettingsController::class, 'promoteAIBackupModel'], ['auth', 'role:owner_platform,admin_owner']);
$router->post('/platform/settings/ai-models/test-connection', [PlatformSettingsController::class, 'testAIModelConnection'], ['auth', 'role:owner_platform,admin_owner']);

// --------------------------------------------------------------------------
// Store Settings (Pengaturan Toko)
// --------------------------------------------------------------------------
$router->get('/store/settings', [StoreSettingsController::class, 'index'], ['auth', 'role:owner_toko,admin_toko']);
$router->post('/store/settings', [StoreSettingsController::class, 'update'], ['auth', 'role:owner_toko']);

// --------------------------------------------------------------------------
// 7. Dispatch the request
// --------------------------------------------------------------------------
$router->dispatch();


