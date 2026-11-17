<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Models\Role;
use App\Models\Setting;
use App\Models\OtpCode;
use App\Services\WhatsAppNotificationService;
use App\Services\OtpEmailService;

class AuthController extends Controller {
    private User $userModel;
    private Role $roleModel;
    private OtpCode $otpModel;

    public function __construct() {
        $this->userModel = new User();
        $this->roleModel = new Role();
        $this->otpModel = new OtpCode();
    }

    /**
     * Show login form
     */
    public function login(Request $request, Response $response): void {
        if (is_authenticated()) {
            $this->redirect(get_dashboard_url_by_role(user_role()));
            return;
        }

        $this->render('auth/login', [
            'title' => 'Masuk - SmartLaundry'
        ], 'layouts/main');
    }

    /**
     * Process standard login
     */
    public function processLogin(Request $request, Response $response): void {
        // 1. CSRF Protection Check
        $csrfToken = $request->get('_csrf_token');
        if (!verify_csrf_token($csrfToken)) {
            set_flash('error', 'Token keamanan (CSRF) tidak valid atau telah kedaluwarsa. Silakan coba lagi.');
            $this->redirect('login');
            return;
        }

        // 2. Rate Limiting Check (Max 5 failed attempts per 15 minutes)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey = 'rate_limit_' . md5($ip . '_' . strtolower(trim($request->get('email', ''))));
        $now = time();

        $maxAttempts = (int)Setting::get('login_max_attempts', 5);
        $lockoutSeconds = (int)Setting::get('login_lockout_duration_minutes', 15) * 60;

        if (isset($_SESSION[$rateKey])) {
            $attemptData = $_SESSION[$rateKey];
            if ($attemptData['count'] >= $maxAttempts && ($now - $attemptData['first_attempt']) < $lockoutSeconds) {
                $remainingMinutes = ceil(($lockoutSeconds - ($now - $attemptData['first_attempt'])) / 60);
                set_flash('error', "Terlalu banyak percobaan login gagal. Akun Anda dibatasi sementara. Coba lagi dalam {$remainingMinutes} menit.");
                $this->redirect('login');
                return;
            } elseif (($now - $attemptData['first_attempt']) >= $lockoutSeconds) {
                unset($_SESSION[$rateKey]);
            }
        }

        // 3. Validation
        $errors = $this->validate($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('login');
            return;
        }

        $email = strtolower(trim($request->get('email')));
        $password = $request->get('password');

        // 4. Find user by email
        $user = $this->userModel->findByEmail($email);

        if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
            // Record failed attempt
            if (!isset($_SESSION[$rateKey])) {
                $_SESSION[$rateKey] = ['count' => 1, 'first_attempt' => $now];
            } else {
                $_SESSION[$rateKey]['count']++;
            }

            set_flash('error', 'Email atau kata sandi yang Anda masukkan salah.');
            $this->redirect('login');
            return;
        }

        // 5. Check if user is active
        if (empty($user['is_active'])) {
            // Check if it's an unverified registration (phone_verified_at is null)
            if (empty($user['phone_verified_at']) && $user['role_name'] === 'customer') {
                // Store pending user id and redirect to verify
                $_SESSION['pending_user_id'] = (int)$user['id'];
                set_flash('warning', 'Akun Anda belum diverifikasi. Silakan selesaikan verifikasi OTP terlebih dahulu.');
                $this->redirect('verify-otp');
                return;
            }
            set_flash('error', 'Akun Anda sedang dinonaktifkan oleh administrator.');
            $this->redirect('login');
            return;
        }

        // 6. Reset Rate Limiting
        unset($_SESSION[$rateKey]);

        // 7. Regenerate Session ID (Prevent Session Fixation)
        session_regenerate_id(true);

        // 8. Fetch associated store info if user is store owner/admin
        $store = $this->userModel->getStore((int)$user['id']);

        // 9. Store User Session
        $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'phone'        => $user['phone'] ?? '',
            'avatar'       => $user['avatar'] ?? 'avatar_default.png',
            'role'         => $user['role_name'],
            'role_display' => $user['role_display'],
            'role_id'      => (int)$user['role_id'],
            'store_id'     => $store['id'] ?? null,
            'store_name'   => $store['name'] ?? null,
            'logged_at'    => date('Y-m-d H:i:s')
        ];

        set_flash('success', "Selamat datang kembali, {$user['name']}! Masuk sebagai {$user['role_display']}.");

        // 10. Redirect to Dashboard based on Role
        $targetUrl = get_dashboard_url_by_role($user['role_name']);
        $this->redirect($targetUrl);
    }

    /**
     * Show register form for Customer
     */
    public function register(Request $request, Response $response): void {
        if (is_authenticated()) {
            $this->redirect(get_dashboard_url_by_role(user_role()));
            return;
        }

        $this->render('auth/register', [
            'title' => 'Daftar Akun Customer - SmartLaundry'
        ], 'layouts/main');
    }

    /**
     * Process customer registration with OTP verification
     */
    public function processRegister(Request $request, Response $response): void {
        $csrfToken = $request->get('_csrf_token');
        if (!verify_csrf_token($csrfToken)) {
            set_flash('error', 'Token keamanan (CSRF) tidak valid. Silakan coba lagi.');
            $this->redirect('register');
            return;
        }

        // Rate limit: max 5 registrations per IP per hour
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $regRateKey = 'reg_rate_limit_' . md5($ip);
        $now = time();
        if (isset($_SESSION[$regRateKey])) {
            $rdata = $_SESSION[$regRateKey];
            if ($rdata['count'] >= 5 && ($now - $rdata['window_start']) < 3600) {
                set_flash('error', 'Terlalu banyak percobaan pendaftaran. Silakan coba lagi nanti.');
                $this->redirect('register');
                return;
            }
            if (($now - $rdata['window_start']) >= 3600) {
                unset($_SESSION[$regRateKey]);
            }
        }

        $errors = $this->validate($request->all(), [
            'name'     => 'required|min:3',
            'email'    => 'required|email',
            'phone'    => 'required|min:9',
            'password' => 'required|min:6'
        ]);

        if (!empty($errors)) {
            set_flash('error', reset($errors));
            $this->redirect('register');
            return;
        }

        $email = strtolower(trim($request->get('email')));
        $rawPhone = trim($request->get('phone'));
        $normalizedPhone = normalize_phone_number($rawPhone);

        if (empty($normalizedPhone)) {
            set_flash('error', 'Format nomor WhatsApp tidak valid. Gunakan format: 08xxx atau +628xxx');
            $this->redirect('register');
            return;
        }

        // Check duplicate email OR phone - use generic message to avoid user enumeration
        $existingByEmail = $this->userModel->findByEmail($email);
        $existingByPhone = $this->userModel->findByPhone($normalizedPhone);

        if ($existingByEmail || $existingByPhone) {
            set_flash('error', 'Data yang Anda masukkan sudah digunakan. Silakan periksa email dan nomor WhatsApp Anda.');
            $this->redirect('register');
            return;
        }

        // Create inactive user account
        $userId = $this->userModel->createCustomer([
            'name'       => trim($request->get('name')),
            'email'      => $email,
            'phone'      => $normalizedPhone,
            'password'   => $request->get('password'),
            'is_active'  => 0  // Not active until OTP verified
        ]);

        if (!$userId) {
            set_flash('error', 'Terjadi kesalahan saat membuat akun. Silakan coba lagi.');
            $this->redirect('register');
            return;
        }

        // Rate limit tracking
        if (!isset($_SESSION[$regRateKey])) {
            $_SESSION[$regRateKey] = ['count' => 1, 'window_start' => $now];
        } else {
            $_SESSION[$regRateKey]['count']++;
        }

        // Generate OTPs
        $otpWa    = null;
        $otpEmail = null;
        $waSuccess    = false;
        $emailSuccess = false;
        $sendErrors   = [];

        try {
            $otpWa = $this->otpModel->generateOtp((int)$userId, OtpCode::CHANNEL_WHATSAPP, $normalizedPhone, OtpCode::PURPOSE_REGISTER, $ip);
            $waService = new WhatsAppNotificationService();
            $waSuccess = $waService->sendOtp($normalizedPhone, trim($request->get('name')), $otpWa['code'], $otpWa['expiry_minutes']);
            if (!$waSuccess) {
                $sendErrors[] = 'WhatsApp';
            }
        } catch (\Throwable $e) {
            $sendErrors[] = 'WhatsApp';
        }

        try {
            $otpEmail = $this->otpModel->generateOtp((int)$userId, OtpCode::CHANNEL_EMAIL, $email, OtpCode::PURPOSE_REGISTER, $ip);
            $emailService = new OtpEmailService();
            $emailSuccess = $emailService->sendOtpEmail($email, trim($request->get('name')), $otpEmail['code'], $otpEmail['expiry_minutes']);
            if (!$emailSuccess) {
                $sendErrors[] = 'Email';
            }
        } catch (\Throwable $e) {
            $sendErrors[] = 'Email';
        }

        // Store pending user in session
        $_SESSION['pending_user_id'] = (int)$userId;
        $_SESSION['pending_phone']   = $normalizedPhone;
        $_SESSION['pending_email']   = $email;
        $_SESSION['pending_name']    = trim($request->get('name'));

        if (!empty($sendErrors)) {
            $channelStr = implode(' dan ', $sendErrors);
            set_flash('warning', "Kode OTP dikirim, namun pengiriman via {$channelStr} mengalami kendala. Periksa log simulasi atau gunakan tombol kirim ulang.");
        } else {
            set_flash('info', 'Kode verifikasi telah dikirim ke WhatsApp dan email Anda. Masukkan kedua kode untuk mengaktifkan akun.');
        }

        $this->redirect('verify-otp');
    }

    /**
     * Show OTP verification page
     */
    public function showVerifyOtp(Request $request, Response $response): void {
        if (is_authenticated()) {
            $this->redirect(get_dashboard_url_by_role(user_role()));
            return;
        }

        $pendingUserId = $_SESSION['pending_user_id'] ?? null;
        if (!$pendingUserId) {
            set_flash('error', 'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.');
            $this->redirect('register');
            return;
        }

        $user = $this->userModel->find((int)$pendingUserId);
        if (!$user || !empty($user['is_active'])) {
            unset($_SESSION['pending_user_id'], $_SESSION['pending_phone'], $_SESSION['pending_email']);
            set_flash('error', 'Akun tidak ditemukan atau sudah aktif. Silakan login.');
            $this->redirect('login');
            return;
        }

        $otpExpiry        = (int)Setting::get('otp_expiry_minutes', 5);
        $resendCooldown   = (int)Setting::get('otp_resend_interval_seconds', 60);
        $requiredChannels = Setting::get('otp_required_channels', 'BOTH');

        $this->render('auth/verify_otp', [
            'title'            => 'Verifikasi OTP - SmartLaundry',
            'pendingUserId'    => $pendingUserId,
            'pendingPhone'     => $_SESSION['pending_phone'] ?? '',
            'pendingEmail'     => $_SESSION['pending_email'] ?? '',
            'pendingName'      => $_SESSION['pending_name'] ?? '',
            'otpExpiry'        => $otpExpiry,
            'resendCooldown'   => $resendCooldown,
            'requiredChannels' => $requiredChannels,
        ], 'layouts/main');
    }

    /**
     * Process OTP verification submission
     */
    public function processVerifyOtp(Request $request, Response $response): void {
        $csrfToken = $request->get('_csrf_token');
        if (!verify_csrf_token($csrfToken)) {
            set_flash('error', 'Token keamanan tidak valid. Silakan coba lagi.');
            $this->redirect('verify-otp');
            return;
        }

        $pendingUserId = $_SESSION['pending_user_id'] ?? null;
        if (!$pendingUserId) {
            set_flash('error', 'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.');
            $this->redirect('register');
            return;
        }

        $user = $this->userModel->find((int)$pendingUserId);
        if (!$user) {
            set_flash('error', 'Akun tidak ditemukan. Silakan daftar ulang.');
            $this->redirect('register');
            return;
        }

        $normalizedPhone  = $_SESSION['pending_phone'] ?? normalize_phone_number($user['phone'] ?? '');
        $email            = $_SESSION['pending_email'] ?? $user['email'];
        $requiredChannels = Setting::get('otp_required_channels', 'BOTH');

        $otpWaCode    = trim($request->get('otp_wa', ''));
        $otpEmailCode = trim($request->get('otp_email', ''));

        $waVerified    = false;
        $emailVerified = false;
        $waResult      = null;
        $emailResult   = null;

        // Verify WhatsApp OTP
        if (!empty($otpWaCode)) {
            $waResult   = $this->otpModel->verifyOtp($normalizedPhone, $otpWaCode, OtpCode::PURPOSE_REGISTER, OtpCode::CHANNEL_WHATSAPP);
            $waVerified = $waResult['success'];
        }

        // Verify Email OTP
        if (!empty($otpEmailCode)) {
            $emailResult   = $this->otpModel->verifyOtp($email, $otpEmailCode, OtpCode::PURPOSE_REGISTER, OtpCode::CHANNEL_EMAIL);
            $emailVerified = $emailResult['success'];
        }

        // Check if verified based on required channels
        $isFullyVerified = false;
        if ($requiredChannels === 'EITHER') {
            $isFullyVerified = ($waVerified || $emailVerified);
        } else { // BOTH
            $isFullyVerified = ($waVerified && $emailVerified);
        }

        if (!$isFullyVerified) {
            // Build helpful error message
            $errors = [];
            if (!$waVerified && !empty($otpWaCode) && $waResult) {
                $errors[] = 'WhatsApp: ' . $waResult['message'];
            } elseif (!$waVerified && empty($otpWaCode) && $requiredChannels === 'BOTH') {
                $errors[] = 'Kode OTP WhatsApp wajib diisi.';
            }
            if (!$emailVerified && !empty($otpEmailCode) && $emailResult) {
                $errors[] = 'Email: ' . $emailResult['message'];
            } elseif (!$emailVerified && empty($otpEmailCode) && $requiredChannels === 'BOTH') {
                $errors[] = 'Kode OTP Email wajib diisi.';
            }

            if ($requiredChannels === 'EITHER' && !$waVerified && !$emailVerified) {
                $errors[] = 'Kode verifikasi tidak valid. Masukkan kode yang benar dari salah satu kanal.';
            }

            set_flash('error', implode(' | ', $errors) ?: 'Verifikasi gagal. Periksa kode OTP Anda.');
            $this->redirect('verify-otp');
            return;
        }

        // Activate account
        $now        = date('Y-m-d H:i:s');
        $updateData = ['is_active' => 1];
        if ($waVerified && !empty($normalizedPhone)) {
            $updateData['phone_verified_at'] = $now;
        }
        if ($emailVerified) {
            $updateData['email_verified_at'] = $now;
        }

        $this->userModel->update((int)$pendingUserId, $updateData);

        // Clean up session
        unset($_SESSION['pending_user_id'], $_SESSION['pending_phone'], $_SESSION['pending_email'], $_SESSION['pending_name']);

        // Auto login
        $user = $this->userModel->findWithRole((int)$pendingUserId);
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'phone'        => $user['phone'] ?? '',
            'avatar'       => 'avatar_default.png',
            'role'         => $user['role_name'],
            'role_display' => $user['role_display'],
            'role_id'      => (int)$user['role_id'],
            'logged_at'    => date('Y-m-d H:i:s')
        ];

        set_flash('success', "Akun Anda berhasil diverifikasi dan diaktifkan! Selamat datang di SmartLaundry, {$user['name']}.");
        $this->redirect('dashboard/customer');
    }

    /**
     * Resend OTP codes
     */
    public function resendOtp(Request $request, Response $response): void {
        $csrfToken = $request->get('_csrf_token');
        if (!verify_csrf_token($csrfToken)) {
            $response->json(['success' => false, 'message' => 'Token CSRF tidak valid.']);
            return;
        }

        $pendingUserId = $_SESSION['pending_user_id'] ?? null;
        if (!$pendingUserId) {
            $response->json(['success' => false, 'message' => 'Sesi tidak ditemukan.']);
            return;
        }

        $channel = strtoupper(trim($request->get('channel', 'WHATSAPP')));
        if (!in_array($channel, ['WHATSAPP', 'EMAIL'], true)) {
            $response->json(['success' => false, 'message' => 'Kanal tidak valid.']);
            return;
        }

        $user = $this->userModel->find((int)$pendingUserId);
        if (!$user) {
            $response->json(['success' => false, 'message' => 'Akun tidak ditemukan.']);
            return;
        }

        $ip              = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $normalizedPhone = $_SESSION['pending_phone'] ?? normalize_phone_number($user['phone'] ?? '');
        $email           = $_SESSION['pending_email'] ?? $user['email'];
        $destination     = ($channel === 'WHATSAPP') ? $normalizedPhone : $email;

        // Check cooldown
        $cooldownResult = $this->otpModel->canResend($destination, OtpCode::PURPOSE_REGISTER);
        if (!$cooldownResult['allowed']) {
            $response->json([
                'success'           => false,
                'message'           => $cooldownResult['message'],
                'remaining_seconds' => $cooldownResult['remaining_seconds']
            ]);
            return;
        }

        // Check hourly limit
        if (!$this->otpModel->checkHourlyLimit($destination, $ip)) {
            $response->json(['success' => false, 'message' => 'Batas pengiriman OTP per jam telah tercapai. Silakan coba lagi nanti.']);
            return;
        }

        // Generate new OTP
        try {
            $otp = $this->otpModel->generateOtp((int)$pendingUserId, $channel, $destination, OtpCode::PURPOSE_REGISTER, $ip);

            if ($channel === 'WHATSAPP') {
                $waService = new WhatsAppNotificationService();
                $sent = $waService->sendOtp($normalizedPhone, $user['name'], $otp['code'], $otp['expiry_minutes']);
            } else {
                $emailService = new OtpEmailService();
                $sent = $emailService->sendOtpEmail($email, $user['name'], $otp['code'], $otp['expiry_minutes']);
            }

            $response->json([
                'success'    => true,
                'message'    => "Kode OTP baru telah dikirim via " . ($channel === 'WHATSAPP' ? 'WhatsApp' : 'Email') . ".",
                'expires_at' => $otp['expires_at']
            ]);
        } catch (\Throwable $e) {
            $response->json(['success' => false, 'message' => 'Gagal mengirim OTP. Silakan coba lagi.']);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request, Response $response): void {
        unset($_SESSION['user']);
        session_destroy();
        session_start();
        set_flash('success', 'Anda telah berhasil keluar dari sistem.');
        $this->redirect('login');
    }

    /**
     * Google OAuth Login / Simulated OAuth Handler
     */
    public function googleAuth(Request $request, Response $response): void {
        $googleClientId     = env('GOOGLE_CLIENT_ID', '');
        $googleClientSecret = env('GOOGLE_CLIENT_SECRET', '');

        if (!empty($googleClientId) && !empty($googleClientSecret)) {
            $redirectUri = urlencode(url('auth/google/callback'));
            $scope       = urlencode('email profile openid');
            $authUrl     = "https://accounts.google.com/o/oauth2/v2/auth?response_type=code&client_id={$googleClientId}&redirect_uri={$redirectUri}&scope={$scope}&access_type=offline";
            header("Location: {$authUrl}");
            exit;
        }

        // Simulated Google OAuth
        $mockEmail = trim($request->get('email', 'customer.ani@gmail.com'));
        $mockName  = trim($request->get('name', 'Ani Rahmawati'));

        $user = $this->userModel->findByEmail($mockEmail);

        if (!$user) {
            $userId = $this->userModel->createCustomer([
                'name'              => $mockName,
                'email'             => $mockEmail,
                'google_id'         => 'google_mock_' . md5($mockEmail),
                'avatar'            => 'https://ui-avatars.com/api/?name=' . urlencode($mockName) . '&background=0D8ABC&color=fff',
                'email_verified_at' => date('Y-m-d H:i:s'),  // Google users have verified email
                'is_active'         => 1
            ]);
            $user = $this->userModel->findWithRole((int)$userId);
        } else {
            // Mark email as verified for existing Google users
            if (empty($user['email_verified_at'])) {
                $this->userModel->update((int)$user['id'], ['email_verified_at' => date('Y-m-d H:i:s')]);
            }
            $user = $this->userModel->findWithRole((int)$user['id']);
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'           => (int)$user['id'],
            'name'         => $user['name'],
            'email'        => $user['email'],
            'phone'        => $user['phone'] ?? '',
            'avatar'       => $user['avatar'] ?? 'avatar_default.png',
            'role'         => $user['role_name'],
            'role_display' => $user['role_display'],
            'role_id'      => (int)$user['role_id'],
            'google_auth'  => true,
            'logged_at'    => date('Y-m-d H:i:s')
        ];

        set_flash('success', "Login dengan Google berhasil! Selamat datang, {$user['name']}.");
        $this->redirect(get_dashboard_url_by_role($user['role_name']));
    }
}
