<?php
/**
 * SmartLaundry - Platform System Configuration Dashboard
 * Accessible to owner_platform (all groups) and admin_owner (limited groups)
 */
$currentUser = auth_user();
$isOwnerPlatform = ($currentUser['role'] === 'owner_platform');
$currentLogo = app_logo();
?>

<div class="container py-4">
    <!-- Header Page -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-1 rounded-pill fs-8">
                    <i class="bi bi-gear-wide-connected me-1"></i> Konfigurasi Sistem
                </span>
                <span class="badge bg-secondary bg-opacity-10 text-secondary fw-semibold px-3 py-1 rounded-pill fs-8">
                    Role: <?= e($currentUser['role_display']) ?>
                </span>
            </div>
            <h2 class="fw-bold text-dark mb-0">Pusat Konfigurasi & Pengaturan Sistem</h2>
            <p class="text-secondary small mb-0">Kelola preferensi web, gateway pembayaran, integrasi kurir, template notifikasi, dan bot AI secara terpusat.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle rounded-pill px-3 fw-semibold shadow-xs" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download me-1"></i> Ekspor Konfigurasi
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                    <li><h6 class="dropdown-header">Pilih Format Ekspor</h6></li>
                    <li>
                        <a class="dropdown-item py-2" href="<?= url('platform/settings/export?format=json') ?>">
                            <i class="bi bi-filetype-json text-primary me-2"></i> Format JSON (.json)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?= url('platform/settings/export?format=csv') ?>">
                            <i class="bi bi-filetype-csv text-success me-2"></i> Format CSV (.csv)
                        </a>
                    </li>
                </ul>
            </div>
            <a href="<?= url('dashboard/platform') ?>" class="btn btn-primary rounded-pill px-3 fw-semibold shadow-xs">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-2">
            <ul class="nav nav-pills nav-fill flex-column flex-md-row gap-1" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'general' ? 'active' : '' ?>" 
                       id="tab-general-btn" data-bs-toggle="pill" href="#tab-general" role="tab">
                        <i class="bi bi-sliders me-1"></i> Umum
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'payment' ? 'active' : '' ?>" 
                       id="tab-payment-btn" data-bs-toggle="pill" href="#tab-payment" role="tab">
                        <i class="bi bi-credit-card me-1"></i> Pembayaran
                        <?php if (!$isOwnerPlatform): ?><i class="bi bi-lock-fill text-muted ms-1 fs-8" title="Hanya Owner Platform"></i><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'refund' ? 'active' : '' ?>" 
                       id="tab-refund-btn" data-bs-toggle="pill" href="#tab-refund" role="tab">
                        <i class="bi bi-arrow-return-left me-1"></i> Pengembalian
                        <?php if (!$isOwnerPlatform): ?><i class="bi bi-lock-fill text-muted ms-1 fs-8" title="Hanya Owner Platform"></i><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'courier_order' ? 'active' : '' ?>" 
                       id="tab-courier_order-btn" data-bs-toggle="pill" href="#tab-courier_order" role="tab">
                        <i class="bi bi-truck me-1"></i> Kurir & Pesanan
                        <?php if (!$isOwnerPlatform): ?><i class="bi bi-lock-fill text-muted ms-1 fs-8" title="Hanya Owner Platform"></i><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'notification' ? 'active' : '' ?>" 
                       id="tab-notification-btn" data-bs-toggle="pill" href="#tab-notification" role="tab">
                        <i class="bi bi-bell me-1"></i> Notifikasi
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'chatbot' ? 'active' : '' ?>" 
                       id="tab-chatbot-btn" data-bs-toggle="pill" href="#tab-chatbot" role="tab">
                        <i class="bi bi-robot me-1"></i> Chatbot
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'security' ? 'active' : '' ?>" 
                       id="tab-security-btn" data-bs-toggle="pill" href="#tab-security" role="tab">
                        <i class="bi bi-shield-check me-1"></i> Keamanan
                        <?php if (!$isOwnerPlatform): ?><i class="bi bi-lock-fill text-muted ms-1 fs-8" title="Hanya Owner Platform"></i><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'maintenance' ? 'active' : '' ?>" 
                       id="tab-maintenance-btn" data-bs-toggle="pill" href="#tab-maintenance" role="tab">
                        <i class="bi bi-wrench-adjustable me-1"></i> Pemeliharaan
                        <?php if (!$isOwnerPlatform): ?><i class="bi bi-lock-fill text-muted ms-1 fs-8" title="Hanya Owner Platform"></i><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'integration' ? 'active' : '' ?>" 
                       id="tab-integration-btn" data-bs-toggle="pill" href="#tab-integration" role="tab">
                        <i class="bi bi-plug me-1"></i> Integrasi
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'sysinfo' ? 'active' : '' ?>" 
                       id="tab-sysinfo-btn" data-bs-toggle="pill" href="#tab-sysinfo" role="tab">
                        <i class="bi bi-info-circle me-1"></i> Info Sistem
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link rounded-3 py-2 px-3 fw-semibold text-start text-md-center <?= $activeTab === 'audit' ? 'active' : '' ?>" 
                       id="tab-audit-btn" data-bs-toggle="pill" href="#tab-audit" role="tab">
                        <i class="bi bi-clock-history me-1"></i> Riwayat
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="settingsTabContent">

        <!-- =================================================================== -->
        <!-- 1. TAB UMUM (GENERAL) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'general' ? 'show active' : '' ?>" id="tab-general" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-sliders fs-5"></i></div>
                        <div>
                            <h5 class="fw-bold mb-0">Pengaturan Umum & Branding</h5>
                            <p class="text-secondary small mb-0">Identitas merek, logo, kontak customer care, dan teks hak cipta aplikasi.</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('platform/settings/update') ?>" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="general">

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Nama Aplikasi <span class="text-danger">*</span></label>
                                <input type="text" name="app_name" class="form-control rounded-3" 
                                       value="<?= e($settingsByGroup['general']['app_name']['typed_value'] ?? 'SmartLaundry') ?>" required maxlength="100">
                                <small class="text-secondary">Digunakan pada header browser title, navbar, footer, dan invoice.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Tagline Aplikasi</label>
                                <input type="text" name="app_tagline" class="form-control rounded-3" 
                                       value="<?= e($settingsByGroup['general']['app_tagline']['typed_value'] ?? '') ?>" maxlength="150">
                                <small class="text-secondary">Slogan promosi utama di halaman landing dan invoice.</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Email Layanan Pelanggan (CS)</label>
                                <input type="email" name="contact_email" class="form-control rounded-3" 
                                       value="<?= e($settingsByGroup['general']['contact_email']['typed_value'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Nomor Telepon / WhatsApp CS</label>
                                <input type="text" name="contact_phone" class="form-control rounded-3" 
                                       value="<?= e($settingsByGroup['general']['contact_phone']['typed_value'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold text-dark">Alamat Kantor Pusat</label>
                                <textarea name="contact_address" class="form-control rounded-3" rows="2"><?= e($settingsByGroup['general']['contact_address']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold text-dark">Teks Hak Cipta Footer</label>
                                <input type="text" name="footer_text" class="form-control rounded-3" 
                                       value="<?= e($settingsByGroup['general']['footer_text']['typed_value'] ?? '') ?>">
                            </div>

                            <!-- Logo Upload with Preview -->
                            <div class="col-12">
                                <div class="p-3 rounded-4 bg-light border border-light-subtle">
                                    <label class="form-label fw-bold text-dark mb-2">Upload Logo Aplikasi</label>
                                    <div class="d-flex align-items-center gap-4">
                                        <div class="rounded-circle bg-white shadow-xs p-2 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; overflow: hidden;">
                                            <?php if ($currentLogo): ?>
                                                <img src="<?= url($currentLogo) ?>" alt="Logo Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <i class="bi bi-water text-primary fs-2"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="file" name="app_logo_file" class="form-control rounded-3" accept=".jpg,.jpeg,.png,.webp">
                                            <small class="text-secondary d-block mt-1">Maks. 2MB. Format: JPG, PNG, atau WEBP. Jika dikosongkan, logo saat ini tetap digunakan.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Umum
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 2. TAB PEMBAYARAN (PAYMENT) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'payment' ? 'show active' : '' ?>" id="tab-payment" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-success bg-opacity-10 text-success rounded-3"><i class="bi bi-credit-card fs-5"></i></div>
                            <div>
                                <h5 class="fw-bold mb-0">Kebijakan & Pengaturan Pembayaran</h5>
                                <p class="text-secondary small mb-0">Aturan masa berlaku QRIS, pengingat kadaluarsa, dan aktivasi metode tunai.</p>
                            </div>
                        </div>
                        <?php if (!$isOwnerPlatform): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-lock me-1"></i> Read-Only (Owner Platform Only)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('platform/settings/update') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="payment">

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Durasi Kedaluwarsa QRIS (Menit) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="qris_expiration_minutes" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['payment']['qris_expiration_minutes']['typed_value'] ?? 60) ?>" 
                                           min="10" max="1440" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Menit</span>
                                </div>
                                <small class="text-secondary">Waktu berlaku sejak invoice diterbitkan pasca penimbangan (Rentang: 10 - 1440 menit / 24 jam).</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Menit Pengingat Sebelum Kedaluwarsa</label>
                                <div class="input-group">
                                    <input type="number" name="payment_reminder_minutes" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['payment']['payment_reminder_minutes']['typed_value'] ?? 15) ?>" 
                                           min="1" max="60" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Menit</span>
                                </div>
                                <small class="text-secondary">Notifikasi pengingat otomatis dikirim saat sisa waktu pembayaran mencapai nilai ini.</small>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch p-3 bg-light rounded-4 border border-light-subtle d-flex align-items-center justify-content-between mb-0">
                                    <div>
                                        <label class="form-check-label fw-bold text-dark mb-1" for="pay_cash_switch">
                                            Aktifkan Metode Pembayaran Tunai (Cash)
                                        </label>
                                        <div class="text-secondary small">Jika dinonaktifkan, opsi tunai akan disembunyikan dan checkout mewajibkan QRIS dinamis.</div>
                                    </div>
                                    <input class="form-check-input ms-3" type="checkbox" role="switch" id="pay_cash_switch" 
                                           name="payment_cash_enabled" value="1" 
                                           <?= !empty($settingsByGroup['payment']['payment_cash_enabled']['typed_value']) ? 'checked' : '' ?>
                                           <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <?php if ($isOwnerPlatform): ?>
                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Pembayaran
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 3. TAB PENGEMBALIAN (REFUND) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'refund' ? 'show active' : '' ?>" id="tab-refund" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-warning bg-opacity-10 text-warning rounded-3"><i class="bi bi-arrow-return-left fs-5"></i></div>
                            <div>
                                <h5 class="fw-bold mb-0">Aturan Pengembalian Laundry Kadaluarsa</h5>
                                <p class="text-secondary small mb-0">Ketentuan ongkir retur dan alur persetujuan admin outlet.</p>
                            </div>
                        </div>
                        <?php if (!$isOwnerPlatform): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-lock me-1"></i> Read-Only (Owner Platform Only)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('platform/settings/update') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="refund">

                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Biaya Pengembalian Default (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text rounded-start-3">Rp</span>
                                    <input type="number" name="return_fee_default" class="form-control rounded-end-3" 
                                           value="<?= (int)($settingsByGroup['refund']['return_fee_default']['typed_value'] ?? 0) ?>" 
                                           min="0" step="1000" <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                </div>
                                <small class="text-secondary">Tarif default ongkir kurir pengembalian pakaian.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Penanggung Biaya Default</label>
                                <select name="return_fee_bearer_default" class="form-select rounded-3" <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <?php $currentBearer = $settingsByGroup['refund']['return_fee_bearer_default']['typed_value'] ?? 'CUSTOMER'; ?>
                                    <option value="CUSTOMER" <?= $currentBearer === 'CUSTOMER' ? 'selected' : '' ?>>CUSTOMER (Pelanggan)</option>
                                    <option value="STORE" <?= $currentBearer === 'STORE' ? 'selected' : '' ?>>STORE (Mitra Toko)</option>
                                    <option value="PLATFORM" <?= $currentBearer === 'PLATFORM' ? 'selected' : '' ?>>PLATFORM (Subsidi Sistem)</option>
                                </select>
                                <small class="text-secondary">Pihak yang membiayai ongkos antar kembali jika invoice kadaluarsa.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Wajib Persetujuan Admin Toko</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="ret_approval_switch" 
                                           name="return_require_store_approval" value="1" 
                                           <?= !empty($settingsByGroup['refund']['return_require_store_approval']['typed_value']) ? 'checked' : '' ?>
                                           <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <label class="form-check-label text-dark fw-semibold" for="ret_approval_switch">
                                        Perlu Verifikasi Toko
                                    </label>
                                </div>
                                <small class="text-secondary">Jika aktif, pengembalian harus di-approve toko sebelum dialihkan ke kurir.</small>
                            </div>
                        </div>

                        <?php if ($isOwnerPlatform): ?>
                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Pengembalian
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 4. TAB KURIR & PESANAN (COURIER & ORDER) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'courier_order' ? 'show active' : '' ?>" id="tab-courier_order" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-info bg-opacity-10 text-info rounded-3"><i class="bi bi-truck fs-5"></i></div>
                            <div>
                                <h5 class="fw-bold mb-0">Pengaturan Armada Kurir & Alur Pesanan</h5>
                                <p class="text-secondary small mb-0">Tenggat penawaran tugas kurir, auto-complete, dan frekuensi refresh GPS Maps.</p>
                            </div>
                        </div>
                        <?php if (!$isOwnerPlatform): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-lock me-1"></i> Read-Only (Owner Platform Only)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('platform/settings/update') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="courier_order">

                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Batas Waktu Respons Tawaran (Menit)</label>
                                <div class="input-group">
                                    <input type="number" name="courier_offer_timeout_minutes" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['courier_order']['courier_offer_timeout_minutes']['typed_value'] ?? 15) ?>" 
                                           min="1" max="120" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Menit</span>
                                </div>
                                <small class="text-secondary">Waktu kurir merespon tawaran tugas sebelum tawaran kadaluarsa.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Batas Auto-Complete Pesanan (Jam)</label>
                                <div class="input-group">
                                    <input type="number" name="order_autocomplete_hours" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['courier_order']['order_autocomplete_hours']['typed_value'] ?? 24) ?>" 
                                           min="1" max="168" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Jam</span>
                                </div>
                                <small class="text-secondary">Pesanan status LAUNDRY_DIANTAR otomatis berstatus SELESAI jika customer tidak mengklik konfirmasi.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Interval Polling Live Tracking (Detik)</label>
                                <div class="input-group">
                                    <input type="number" name="tracking_poll_interval_seconds" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['courier_order']['tracking_poll_interval_seconds']['typed_value'] ?? 10) ?>" 
                                           min="3" max="30" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Detik</span>
                                </div>
                                <small class="text-secondary">Frekuensi client me-refresh posisi GPS kurir pada peta peta (Rentang: 3 - 30 detik).</small>
                            </div>
                        </div>

                        <?php if ($isOwnerPlatform): ?>
                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Kurir & Pesanan
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 5. TAB NOTIFIKASI (NOTIFICATION) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'notification' ? 'show active' : '' ?>" id="tab-notification" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-3"><i class="bi bi-bell fs-5"></i></div>
                        <div>
                            <h5 class="fw-bold mb-0">Pengaturan Notifikasi & Template WhatsApp</h5>
                            <p class="text-secondary small mb-0">Saklar global notifikasi serta penyesuaian template pesan resmi dengan placeholder variabel.</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('platform/settings/update') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="notification">

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 bg-light border border-light-subtle d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold text-dark">Lonceng Notifikasi In-App</div>
                                        <small class="text-secondary">Pusat notifikasi internal di navbar aplikasi</small>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="in_app_notification_enabled" value="1"
                                               <?= !empty($settingsByGroup['notification']['in_app_notification_enabled']['typed_value']) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 rounded-4 bg-light border border-light-subtle d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold text-dark">Notifikasi WhatsApp Gateway</div>
                                        <small class="text-secondary">Saklar global pengiriman pesan WA otomatis</small>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_notification_enabled" value="1"
                                               <?= !empty($settingsByGroup['notification']['whatsapp_notification_enabled']['typed_value']) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-chat-quote text-success me-1"></i> Template Pesan WhatsApp Gateway</h6><div class="mb-4">
    <h5 class="fw-bold mb-1"><i class="bi bi-phone"></i> Nomor Uji Coba WhatsApp</h5>
    <p class="text-muted small">Ketik nomor tujuan (nomor sendiri atau orang lain yang sudah setuju), atau pilih dari nomor tersimpan, lalu kirim pesan tes.</p>
    <iframe src="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') ?>/public/wa_test.php?embed=1"
            style="width:100%;height:660px;border:1px solid #dee2e6;border-radius:12px"
            title="Uji kirim WhatsApp"></iframe>
</div>
                        <p class="text-secondary small mb-3">
                            Tersedia placeholder variabel: <code>{customer_name}</code>, <code>{order_code}</code>, <code>{store_name}</code>, <code>{total}</code>, <code>{courier_name}</code>, <code>{courier_phone}</code>.
                        </p>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Template: Pesanan Baru Dibuat (ORDER_CREATED)</label>
                                <textarea name="wa_template_order_created" class="form-control rounded-3" rows="2"><?= e($settingsByGroup['notification']['wa_template_order_created']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Template: Pesanan Dikonfirmasi Toko (ORDER_CONFIRMED)</label>
                                <textarea name="wa_template_order_confirmed" class="form-control rounded-3" rows="2"><?= e($settingsByGroup['notification']['wa_template_order_confirmed']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Template: Kurir Menuju Lokasi (COURIER_DISPATCHED)</label>
                                <textarea name="wa_template_courier_dispatched" class="form-control rounded-3" rows="2"><?= e($settingsByGroup['notification']['wa_template_courier_dispatched']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Template: Pembayaran Lunas (PAYMENT_SUCCESS)</label>
                                <textarea name="wa_template_payment_success" class="form-control rounded-3" rows="2"><?= e($settingsByGroup['notification']['wa_template_payment_success']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark">Template: Laundry Selesai Dicuci (LAUNDRY_READY)</label>
                                <textarea name="wa_template_laundry_ready" class="form-control rounded-3" rows="2"><?= e($settingsByGroup['notification']['wa_template_laundry_ready']['typed_value'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Notifikasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 6. TAB CHATBOT (CHATBOT & KNOWLEDGE BASE) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'chatbot' ? 'show active' : '' ?>" id="tab-chatbot" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-robot fs-5"></i></div>
                            <div>
                                <h5 class="fw-bold mb-0">Pengaturan Asisten AI Chatbot (SmartBot)</h5>
                                <p class="text-secondary small mb-0">Konfigurasi operasional, sapaan pembuka per role, batas frekuensi chat, dan basis pengetahuan.</p>
                            </div>
                        </div>
                        <a href="<?= url('platform/chatbot-logs') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-journal-text me-1"></i> Buka Log Percakapan
                        </a>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('platform/settings/update') ?>" method="POST" id="formChatbotSettings">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="chatbot">

                        <!-- Status & Konfigurasi Penyedia AI Dinamis -->
                        <div class="card border border-primary-subtle bg-light-subtle rounded-4 mb-4 overflow-hidden shadow-sm">
                        <div class="card-header bg-primary bg-opacity-10 border-bottom border-primary-subtle p-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-cpu-fill fs-4 text-primary"></i>
                                <div>
                                    <strong class="text-dark">AI Agent Utama (Primary Live Model):</strong>
                                    <span class="badge bg-primary px-3 py-1 rounded-pill ms-2 text-uppercase">
                                        <?= e($aiProvider) ?> - <?= e($aiModel) ?>
                                    </span>
                                    <?php if (!empty($aiApiKey)): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-1">
                                            <i class="bi bi-key-fill me-1"></i> API Key Aktif (<?= \App\Models\Setting::get('ai_api_key') ? 'Database' : '.env' ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill ms-1">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Mode Offline / Mock
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" id="btnTestPrimaryAI">
                                    <i class="bi bi-broadcast me-1"></i> Tes Koneksi AI Utama
                                </button>
                                <span class="badge bg-white text-muted border rounded-pill px-2 py-1 small">
                                    <i class="bi bi-sliders"></i> Konfigurasi Web Aktif
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-4 bg-white">
                            <div class="alert alert-info border-0 rounded-3 p-3 mb-3 small d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill fs-5 text-primary"></i>
                                <div>
                                    <strong>Fleksibilitas Multi-Model:</strong> Anda dapat mengubah penyedia AI, mengganti API Key, serta menambahkan beberapa model cadangan (failover pool) langsung dari halaman ini tanpa perlu membuka file <code>.env</code> secara manual.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Penyedia AI (Provider)</label>
                                    <select name="ai_provider" id="primary_ai_provider" class="form-select rounded-3">
                                        <option value="gemini" <?= $aiProvider === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                                        <option value="openai" <?= $aiProvider === 'openai' ? 'selected' : '' ?>>OpenAI (ChatGPT)</option>
                                        <option value="groq" <?= $aiProvider === 'groq' ? 'selected' : '' ?>>Groq Cloud (Llama/Mixtral)</option>
                                        <option value="deepseek" <?= $aiProvider === 'deepseek' ? 'selected' : '' ?>>DeepSeek</option>
                                        <option value="custom" <?= $aiProvider === 'custom' ? 'selected' : '' ?>>Custom OpenAI-Compatible</option>
                                        <option value="mock" <?= $aiProvider === 'mock' ? 'selected' : '' ?>>Intelligent Mock (Offline Demo)</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Nama Model Utama</label>
                                    <input type="text" name="ai_model" id="primary_ai_model" class="form-control rounded-3" 
                                           list="modelSuggestions" value="<?= e($aiModel) ?>" placeholder="e.g. gemini-1.5-flash">
                                    <datalist id="modelSuggestions">
                                        <option value="gemini-1.5-flash">Google Gemini 1.5 Flash (Cepat & Hemat)</option>
                                        <option value="gemini-1.5-pro">Google Gemini 1.5 Pro (Cerdas & Luas)</option>
                                        <option value="gemini-2.0-flash-exp">Google Gemini 2.0 Flash</option>
                                        <option value="gpt-4o-mini">OpenAI GPT-4o Mini</option>
                                        <option value="gpt-4o">OpenAI GPT-4o</option>
                                        <option value="llama-3.3-70b-versatile">Groq — Llama 3.3 70B Versatile</option>
                                        <option value="llama-3.1-70b-versatile">Groq — Llama 3.1 70B Versatile</option>
                                        <option value="llama-3.1-8b-instant">Groq — Llama 3.1 8B Instant (Cepat)</option>
                                        <option value="mixtral-8x7b-32768">Groq — Mixtral 8x7B</option>
                                        <option value="gemma2-9b-it">Groq — Gemma 2 9B</option>
                                        <option value="deepseek-chat">DeepSeek Chat V3</option>
                                    </datalist>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">API Key Utama</label>
                                    <div class="input-group">
                                        <input type="password" name="ai_api_key" id="primary_ai_api_key" class="form-control rounded-start-3" 
                                               placeholder="<?= !empty($aiApiKey) ? '•••••••••••••••• (Tersimpan)' : 'Masukkan API Key baru' ?>" autocomplete="off">
                                        <button class="btn btn-outline-secondary rounded-end-3 toggle-password-btn" type="button" data-target="#primary_ai_api_key">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted" style="font-size: 0.75rem;">Kosongkan bila tidak ingin mengubah key tersimpan.</small>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label fw-bold text-dark small">Custom Base URL (Opsional)</label>
                                    <input type="text" name="ai_base_url" id="primary_ai_base_url" class="form-control rounded-3" 
                                           value="<?= e($aiBaseUrl) ?>" placeholder="https://api.openai.com/v1">
                                    <small class="text-muted" style="font-size: 0.75rem;">Khusus proxy atau penyedia non-standar.</small>
                                </div>
                            </div>

                            <div id="primaryTestResult" class="mt-2" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <!-- Global Switch -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 bg-light border border-light-subtle d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold text-dark">Chatbot Aktif (Global)</div>
                                        <small class="text-secondary">Bila mati, widget disembunyikan & API menolak dengan sopan</small>
                                    </div>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="chatbot_enabled" value="1"
                                               <?= !empty($settingsByGroup['chatbot']['chatbot_enabled']['typed_value']) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>

                            <!-- Bot Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Nama Tampilan Bot</label>
                                <input type="text" name="chatbot_bot_name" class="form-control rounded-3" 
                                       value="<?= e($settingsByGroup['chatbot']['chatbot_bot_name']['typed_value'] ?? 'SmartBot') ?>">
                            </div>

                            <!-- Role Checklist -->
                            <div class="col-12">
                                <label class="form-label fw-bold text-dark mb-2">Peran Pengguna yang Diizinkan Berinteraksi dengan Bot</label>
                                <?php 
                                    $rawRoles = $settingsByGroup['chatbot']['chatbot_roles_enabled']['typed_value'] ?? [];
                                    $activeRoles = is_array($rawRoles) ? $rawRoles : (json_decode((string)$rawRoles, true) ?: []);
                                    $allRoles = [
                                        'customer' => 'Customer (Pelanggan)',
                                        'kurir' => 'Kurir Mitra',
                                        'owner_platform' => 'Owner Platform',
                                        'admin_owner' => 'Admin Platform',
                                        'owner_toko' => 'Owner Toko',
                                        'admin_toko' => 'Admin Toko (Operator/Kasir)'
                                    ];
                                ?>
                                <div class="row g-2">
                                    <?php foreach ($allRoles as $rKey => $rLabel): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="p-2 border rounded-3 bg-light">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="chatbot_roles_enabled[]" 
                                                       value="<?= $rKey ?>" id="role_<?= $rKey ?>" 
                                                       <?= in_array($rKey, $activeRoles, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label fs-8 fw-semibold" for="role_<?= $rKey ?>">
                                                    <?= e($rLabel) ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Greetings per role -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Sapaan: Customer</label>
                                <textarea name="chatbot_greeting_customer" class="form-control rounded-3" rows="3"><?= e($settingsByGroup['chatbot']['chatbot_greeting_customer']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Sapaan: Kurir</label>
                                <textarea name="chatbot_greeting_kurir" class="form-control rounded-3" rows="3"><?= e($settingsByGroup['chatbot']['chatbot_greeting_kurir']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Sapaan: Admin & Owner</label>
                                <textarea name="chatbot_greeting_admin" class="form-control rounded-3" rows="3"><?= e($settingsByGroup['chatbot']['chatbot_greeting_admin']['typed_value'] ?? '') ?></textarea>
                            </div>

                            <!-- Fallback message -->
                            <div class="col-12">
                                <label class="form-label fw-bold text-dark">Pesan Fallback Chatbot</label>
                                <input type="text" name="chatbot_fallback_message" class="form-control rounded-3" 
                                       value="<?= e($settingsByGroup['chatbot']['chatbot_fallback_message']['typed_value'] ?? '') ?>">
                                <small class="text-secondary">Ditampilkan saat pola pertanyaan pengguna di luar cakupan pemahaman sistem.</small>
                            </div>

                            <!-- Rate limit, retention, finance switch -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Batas Pesan / Menit</label>
                                <input type="number" name="chatbot_rate_limit_per_minute" class="form-control rounded-3" 
                                       value="<?= (int)($settingsByGroup['chatbot']['chatbot_rate_limit_per_minute']['typed_value'] ?? 20) ?>" min="1" max="120">
                                <small class="text-secondary">Rate-limiting perlindungan spam per user.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Retensi Log Chat (Hari)</label>
                                <input type="number" name="chatbot_log_retention_days" class="form-control rounded-3" 
                                       value="<?= (int)($settingsByGroup['chatbot']['chatbot_log_retention_days']['typed_value'] ?? 90) ?>" min="1" max="365">
                                <small class="text-secondary">Dibersihkan otomatis oleh script cron purge.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Admin Toko Akses Finansial</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" name="admin_toko_can_view_finance" value="1" id="fin_switch"
                                           <?= !empty($settingsByGroup['chatbot']['admin_toko_can_view_finance']['typed_value']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="fin_switch">Izinkan Akses Omset</label>
                                </div>
                                <small class="text-secondary">Bila off, omset toko hanya boleh dilihat oleh owner_toko.</small>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Chatbot
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sub-Tab: Multi-Agent AI Backup & Failover Pool -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-diagram-3-fill fs-5"></i></div>
                            <div>
                                <h6 class="fw-bold mb-0">Multi-Agent AI Pool (Model Cadangan & Failover)</h6>
                                <small class="text-secondary">Tambah model & API key cadangan. Jika model utama limit / downtime, sistem otomatis beralih ke agen cadangan ini.</small>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalAddAIModel">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Agen Cadangan
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-8">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">No</th>
                                    <th>Nama Agen</th>
                                    <th>Penyedia & Model</th>
                                    <th>Status API Key</th>
                                    <th>Base URL</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($aiBackupModels)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-shield-slash fs-4 d-block mb-1 text-secondary opacity-50"></i>
                                            Belum ada model agen cadangan. Klik <strong>+ Tambah Agen Cadangan</strong> untuk menambahkan model alternatif.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($aiBackupModels as $bIdx => $bm): ?>
                                    <tr>
                                        <td class="ps-4 text-secondary">#<?= $bIdx + 1 ?></td>
                                        <td>
                                            <strong class="text-dark"><?= e($bm['name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary text-uppercase"><?= e($bm['provider']) ?></span>
                                            <code class="text-dark ms-1"><?= e($bm['model']) ?></code>
                                        </td>
                                        <td>
                                            <?php if (!empty($bm['api_key'])): ?>
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="bi bi-check-circle-fill me-1"></i> <?= substr($bm['api_key'], 0, 6) ?>••••<?= substr($bm['api_key'], -4) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger">Kunci Kosong</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= !empty($bm['base_url']) ? e($bm['base_url']) : '<span class="opacity-50">Default Endpoint</span>' ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($bm['is_active'])): ?>
                                                <span class="badge bg-success-subtle text-success">Siap Failover</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-inline-flex gap-1 align-items-center">
                                                <!-- Tes Koneksi Button -->
                                                <button type="button" class="btn btn-xs btn-outline-info rounded-pill px-2 btn-test-agent" 
                                                        data-provider="<?= e($bm['provider']) ?>" 
                                                        data-model="<?= e($bm['model']) ?>" 
                                                        data-key="<?= e($bm['api_key']) ?>" 
                                                        data-baseurl="<?= e($bm['base_url'] ?? '') ?>" 
                                                        data-name="<?= e($bm['name']) ?>" title="Tes koneksi live">
                                                    <i class="bi bi-broadcast"></i> Tes
                                                </button>

                                                <!-- Promote to Primary Button -->
                                                <form action="<?= url('platform/settings/ai-models/' . $bm['id'] . '/promote') ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-xs btn-outline-success rounded-pill px-2" title="Jadikan Model Utama" onclick="return confirm('Jadikan model ini sebagai AI Agent Utama?')">
                                                        <i class="bi bi-star-fill"></i> Utama
                                                    </button>
                                                </form>

                                                <!-- Toggle Active Button -->
                                                <form action="<?= url('platform/settings/ai-models/' . $bm['id'] . '/toggle') ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill px-2" title="Ubah status aktif">
                                                        <i class="bi bi-power"></i>
                                                    </button>
                                                </form>

                                                <!-- Edit Button -->
                                                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2 btn-edit-agent" 
                                                        data-id="<?= e($bm['id']) ?>" 
                                                        data-name="<?= e($bm['name']) ?>" 
                                                        data-provider="<?= e($bm['provider']) ?>" 
                                                        data-model="<?= e($bm['model']) ?>" 
                                                        data-baseurl="<?= e($bm['base_url'] ?? '') ?>" title="Edit agen">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <!-- Delete Button -->
                                                <form action="<?= url('platform/settings/ai-models/' . $bm['id'] . '/delete') ?>" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-xs btn-outline-danger rounded-pill px-2" title="Hapus agen" onclick="return confirm('Hapus agen cadangan <?= e($bm['name']) ?>?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sub-Tab: Chatbot Knowledge Base Management -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0"><i class="bi bi-book-half text-primary me-2"></i>Basis Pengetahuan Sistem (Knowledge Base)</h6>
                        <small class="text-secondary">Kelola daftar artikel dan panduan yang dapat dijawab oleh asisten SmartBot</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalAddKnowledge">
                        <i class="bi bi-plus-lg me-1"></i> Tambah Pengetahuan
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-8">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">ID</th>
                                    <th>Kategori</th>
                                    <th>Judul Entri</th>
                                    <th>Kata Kunci</th>
                                    <th>Role Diizinkan</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($knowledgeItems)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Belum ada artikel pengetahuan tercatat.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($knowledgeItems as $ki): ?>
                                    <tr>
                                        <td class="ps-4 text-secondary">#<?= $ki['id'] ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($ki['category']) ?></span></td>
                                        <td class="fw-semibold text-dark"><?= e($ki['title']) ?></td>
                                        <td class="text-muted" style="max-width: 200px;"><?= e(mb_strimwidth($ki['keywords'], 0, 40, '...')) ?></td>
                                        <td><span class="badge bg-info bg-opacity-10 text-info"><?= e($ki['roles_allowed'] ?? 'all') ?></span></td>
                                        <td>
                                            <?php if ($ki['is_active']): ?>
                                                <span class="badge bg-success-subtle text-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form action="<?= url('platform/settings/knowledge/' . $ki['id'] . '/toggle') ?>" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill px-2" title="Ubah status aktif">
                                                    <?= $ki['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 7. TAB KEAMANAN (SECURITY) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'security' ? 'show active' : '' ?>" id="tab-security" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-danger bg-opacity-10 text-danger rounded-3"><i class="bi bi-shield-check fs-5"></i></div>
                            <div>
                                <h5 class="fw-bold mb-0">Kebijakan Keamanan & Perlindungan Akun</h5>
                                <p class="text-secondary small mb-0">Batas toleransi login salah, durasi penangguhan IP, dan masa berlaku sesi pengguna.</p>
                            </div>
                        </div>
                        <?php if (!$isOwnerPlatform): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-lock me-1"></i> Read-Only (Owner Platform Only)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('platform/settings/update') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="security">

                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Maksimal Percobaan Login Gagal <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="login_max_attempts" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['security']['login_max_attempts']['typed_value'] ?? 5) ?>" 
                                           min="3" max="20" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Kali</span>
                                </div>
                                <small class="text-secondary">Batas kesalahan sandi sebelum akun/IP diblokir sementara (Rentang: 3 - 20 kali).</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Durasi Blokir Login (Menit) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="login_lockout_duration_minutes" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['security']['login_lockout_duration_minutes']['typed_value'] ?? 15) ?>" 
                                           min="1" max="120" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Menit</span>
                                </div>
                                <small class="text-secondary">Lama masa tunggu akun terkunci sebelum boleh mencoba masuk kembali.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Lama Sesi Pengguna (Menit) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="session_lifetime_minutes" class="form-control rounded-start-3" 
                                           value="<?= (int)($settingsByGroup['security']['session_lifetime_minutes']['typed_value'] ?? 120) ?>" 
                                           min="15" max="1440" required <?= !$isOwnerPlatform ? 'disabled' : '' ?>>
                                    <span class="input-group-text rounded-end-3 text-secondary">Menit</span>
                                </div>
                                <small class="text-secondary">Waktu kedaluwarsa sesi idle tanpa aktivitas di browser.</small>
                            </div>
                        </div>

                        <?php if ($isOwnerPlatform): ?>
                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Keamanan
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 8. TAB MODE PEMELIHARAAN (MAINTENANCE) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'maintenance' ? 'show active' : '' ?>" id="tab-maintenance" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 bg-dark bg-opacity-10 text-dark rounded-3"><i class="bi bi-wrench-adjustable fs-5"></i></div>
                            <div>
                                <h5 class="fw-bold mb-0">Mode Pemeliharaan Sistem (503 Service Unavailable)</h5>
                                <p class="text-secondary small mb-0">Aktifkan pemeliharaan berkala saat update sistem atau backup database besar.</p>
                            </div>
                        </div>
                        <?php if (!$isOwnerPlatform): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-lock me-1"></i> Read-Only (Owner Platform Only)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-4">
                    <?php $isMaintActive = !empty($settingsByGroup['maintenance']['maintenance_mode']['typed_value']); ?>
                    
                    <div class="p-4 rounded-4 mb-4 <?= $isMaintActive ? 'bg-danger-subtle border border-danger' : 'bg-success-subtle border border-success' ?>">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle <?= $isMaintActive ? 'bg-danger text-white' : 'bg-success text-white' ?> d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi <?= $isMaintActive ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' ?> fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0">Status Sistem: <?= $isMaintActive ? 'MODE PEMELIHARAAN AKTIF' : 'SISTEM ONLINE & BERJALAN NORMAL' ?></h5>
                                    <div class="small <?= $isMaintActive ? 'text-danger' : 'text-success' ?>">
                                        <?= $isMaintActive 
                                            ? 'Pengunjung & customer diarahkan ke layar 503. Halaman login admin tetap dapat diakses.' 
                                            : 'Semua fitur, pemesanan laundry, tracking kurir, dan pembayaran berjalan normal.' ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($isOwnerPlatform): ?>
                                <button type="button" class="btn <?= $isMaintActive ? 'btn-success' : 'btn-danger' ?> rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalConfirmMaintenance">
                                    <i class="bi <?= $isMaintActive ? 'bi-power' : 'bi-tools' ?> me-1"></i>
                                    <?= $isMaintActive ? 'Matikan Mode Pemeliharaan' : 'Aktifkan Mode Pemeliharaan' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form action="<?= url('platform/settings/update') ?>" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="setting_group" value="maintenance">
                        <input type="hidden" name="maintenance_mode" value="<?= $isMaintActive ? '1' : '0' ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Pesan Tampilan Halaman 503 (Maintenance Message)</label>
                            <textarea name="maintenance_message" class="form-control rounded-3" rows="3" <?= !$isOwnerPlatform ? 'disabled' : '' ?>><?= e($settingsByGroup['maintenance']['maintenance_message']['typed_value'] ?? '') ?></textarea>
                            <small class="text-secondary">Pesan informatif yang terbaca oleh publik saat layar 503 muncul.</small>
                        </div>

                        <?php if ($isOwnerPlatform): ?>
                        <div class="text-end">
                            <button type="submit" class="btn btn-outline-primary rounded-pill px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Teks Pesan Pemeliharaan
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 9. TAB INTEGRASI (INTEGRATION - READ ONLY) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'integration' ? 'show active' : '' ?>" id="tab-integration" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-plug fs-5"></i></div>
                        <div>
                            <h5 class="fw-bold mb-0">Status Integrasi Layanan Pihak Ketiga (API)</h5>
                            <p class="text-secondary small mb-0">Pemantauan status konektivitas payment gateway, WhatsApp, Maps, dan LLM AI. Kunci rahasia API tersimpan aman di server.</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <?php foreach ($integrationStatus as $svcKey => $svc): ?>
                        <div class="col-md-6">
                            <div class="card h-100 border rounded-4 p-4 shadow-xs">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="p-2 rounded-circle <?= $svc['is_configured'] ? 'bg-success bg-opacity-10 text-success' : 'bg-warning bg-opacity-10 text-warning' ?>">
                                            <i class="bi <?= $svc['is_configured'] ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold mb-0 text-dark"><?= e($svc['name']) ?></h6>
                                    </div>
                                    <span class="badge <?= $svc['is_configured'] ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> rounded-pill px-3 py-1 fs-8">
                                        <?= e($svc['status_label']) ?>
                                    </span>
                                </div>
                                <div class="text-secondary small mb-3">
                                    <div><strong>Gateway Aktif:</strong> <?= e($svc['active_gateway']) ?></div>
                                    <?php if (!empty($svc['model'])): ?>
                                        <div><strong>Model AI:</strong> <code><?= e($svc['model']) ?></code></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isOwnerPlatform && $svc['is_configured']): ?>
                                    <div class="mt-auto pt-2 border-top">
                                        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-3 btn-test-conn" data-service="<?= $svcKey ?>">
                                            <i class="bi bi-broadcast me-1"></i> Uji Koneksi API
                                        </button>
                                        <span class="test-result-msg ms-2 small"></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 10. TAB INFORMASI SISTEM (SYSINFO - READ ONLY) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'sysinfo' ? 'show active' : '' ?>" id="tab-sysinfo" role="tabpanel">
            <!-- Server Environment -->
            <div class="row g-4 mb-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white border-bottom p-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-hdd-network text-primary me-2"></i>Lingkungan Sistem & Server</h6>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped mb-0 fs-8 align-middle">
                                <tbody>
                                    <tr><td class="ps-3 text-secondary">Versi Aplikasi</td><td class="fw-bold"><?= e($systemInfo['environment']['app_version']) ?></td></tr>
                                    <tr><td class="ps-3 text-secondary">Versi PHP</td><td><?= e($systemInfo['environment']['php_version']) ?></td></tr>
                                    <tr><td class="ps-3 text-secondary">Versi Database MySQL</td><td><?= e($systemInfo['environment']['mysql_version']) ?></td></tr>
                                    <tr><td class="ps-3 text-secondary">Environment (APP_ENV)</td><td><span class="badge bg-secondary"><?= e($systemInfo['environment']['app_env']) ?></span></td></tr>
                                    <tr><td class="ps-3 text-secondary">Zona Waktu Sistem</td><td><?= e($systemInfo['environment']['timezone']) ?></td></tr>
                                    <tr><td class="ps-3 text-secondary">Jumlah Tabel Database</td><td><?= (int)$systemInfo['environment']['table_count'] ?> tabel</td></tr>
                                    <tr><td class="ps-3 text-secondary">Ukuran Basis Data</td><td><?= $systemInfo['environment']['database_size_mb'] ?> MB</td></tr>
                                    <tr><td class="ps-3 text-secondary">Penggunaan Storage Upload</td><td><?= $systemInfo['environment']['storage_usage_mb'] ?> MB</td></tr>
                                    <tr><td class="ps-3 text-secondary">Ukuran Log Error</td><td><?= $systemInfo['environment']['error_log_size_kb'] ?> KB</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white border-bottom p-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart text-success me-2"></i>Statistik Data Master & Transaksi</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2 mb-3">
                                <?php foreach ($systemInfo['stats']['roles'] as $r): ?>
                                <div class="col-6">
                                    <div class="p-2 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                                        <span class="fs-8 text-secondary"><?= e($r['display_name']) ?></span>
                                        <span class="badge bg-primary rounded-pill"><?= (int)$r['total'] ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="p-3 rounded-4 bg-light border border-light-subtle mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-secondary">Total Mitra Outlet Toko:</span>
                                    <span class="fw-bold"><?= (int)$systemInfo['stats']['stores']['total_stores'] ?> Toko</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center fs-8 text-muted">
                                    <span>Terverifikasi: <?= (int)$systemInfo['stats']['stores']['verified_stores'] ?></span>
                                    <span>Menunggu Verifikasi: <?= (int)$systemInfo['stats']['stores']['pending_stores'] ?></span>
                                </div>
                            </div>
                            <div class="p-3 rounded-4 bg-light border border-light-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small text-secondary">Total Transaksi Pesanan:</span>
                                    <span class="fw-bold"><?= (int)$systemInfo['stats']['order_summary']['total_orders'] ?> Pesanan</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center fs-8 text-muted">
                                    <span>Volume Pesanan GMV: <?= format_rupiah($systemInfo['stats']['order_summary']['total_gmv']) ?></span>
                                    <span>Pesan Chatbot: <?= (int)$systemInfo['stats']['chatbot']['total_messages'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cron Status Table -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-warning me-2"></i>Status & Heartbeat Pekerjaan Latar Belakang (Cron Jobs)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-8">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Nama Pekerjaan Cron</th>
                                    <th>Script Runner</th>
                                    <th>Jadwal Estimasi</th>
                                    <th>Eksekusi Terakhir</th>
                                    <th>Hasil Terakhir</th>
                                    <th>Status Heartbeat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cronStatus as $job): ?>
                                <tr>
                                    <td class="ps-3 fw-semibold text-dark"><?= e($job['name']) ?></td>
                                    <td><code><?= e($job['script']) ?></code></td>
                                    <td>Tiap <?= $job['expected_interval_minutes'] ?> menit</td>
                                    <td><?= !empty($job['last_run']) ? format_datetime_id($job['last_run']) : '<span class="text-muted">Belum pernah dijalankan</span>' ?></td>
                                    <td class="text-secondary" style="max-width: 250px;"><?= e($job['summary']) ?></td>
                                    <td>
                                        <?php if ($job['is_late']): ?>
                                            <span class="badge bg-danger-subtle text-danger px-2 py-1 rounded-pill">
                                                <i class="bi bi-exclamation-circle me-1"></i> Terlambat
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill">
                                                <i class="bi bi-check2-circle me-1"></i> Tepat Waktu (OK)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- 11. TAB RIWAYAT PERUBAHAN (AUDIT LOG) -->
        <!-- =================================================================== -->
        <div class="tab-pane fade <?= $activeTab === 'audit' ? 'show active' : '' ?>" id="tab-audit" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold mb-0">Audit Trail Riwayat Perubahan Pengaturan</h5>
                        <p class="text-secondary small mb-0">Merekam setiap aktivitas modifikasi konfigurasi oleh pengguna (siapa, kapan, IP, nilai lama, nilai baru).</p>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2">Total Log: <?= $auditTotalCount ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 fs-8">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Waktu</th>
                                    <th>Pengguna / Role</th>
                                    <th>Kunci Pengaturan</th>
                                    <th>Nilai Lama</th>
                                    <th>Nilai Baru</th>
                                    <th class="pe-4">Alamat IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditLogs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat perubahan pengaturan tercatat.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap"><?= format_datetime_id($log['created_at']) ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($log['user_name'] ?? 'System') ?></div>
                                            <div class="text-muted fs-8"><?= e($log['role_display'] ?? '-') ?></div>
                                        </td>
                                        <td><code><?= e($log['setting_key']) ?></code></td>
                                        <td class="text-danger" style="max-width: 200px; word-break: break-all;">
                                            <?= e(mb_strimwidth((string)$log['old_value'], 0, 50, '...')) ?>
                                        </td>
                                        <td class="text-success fw-semibold" style="max-width: 200px; word-break: break-all;">
                                            <?= e(mb_strimwidth((string)$log['new_value'], 0, 50, '...')) ?>
                                        </td>
                                        <td class="pe-4 text-muted"><?= e($log['ip_address'] ?? '-') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($auditTotalPages > 1): ?>
                    <div class="p-3 border-top d-flex justify-content-center">
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($p = 1; $p <= $auditTotalPages; $p++): ?>
                                    <li class="page-item <?= $p === $auditPage ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= url("platform/settings?tab=audit&page={$p}") ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Confirm Toggle Maintenance Mode -->
<?php if ($isOwnerPlatform): ?>
<div class="modal fade" id="modalConfirmMaintenance" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-shield-exclamation text-warning me-2"></i>Konfirmasi Mode Pemeliharaan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <p class="text-secondary leading-relaxed mb-0">
                    Apakah Anda yakin ingin <?= $isMaintActive ? 'menonaktifkan' : 'mengaktifkan' ?> <strong>Mode Pemeliharaan (503)</strong>?
                    <br><br>
                    <?= $isMaintActive 
                        ? 'Sistem akan dibuka kembali untuk seluruh customer dan mitra kurir/toko.' 
                        : 'Pelanggan umum tidak akan bisa membuat pesanan baru dan akan diarahkan ke layar 503. Halaman login admin tetap dapat diakses.' ?>
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <form action="<?= url('platform/settings/toggle-maintenance') ?>" method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn <?= $isMaintActive ? 'btn-success' : 'btn-danger' ?> rounded-pill px-4 fw-bold">
                        Ya, Lanjutkan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Add Chatbot Knowledge Entry -->
<div class="modal fade" id="modalAddKnowledge" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <form action="<?= url('platform/settings/knowledge/store') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-header border-bottom p-4">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-book me-2 text-primary"></i>Tambah Artikel Basis Pengetahuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Kategori</label>
                            <select name="category" class="form-select rounded-3">
                                <option value="faq">FAQ Umum</option>
                                <option value="order_status">Alur Status Pesanan</option>
                                <option value="payment">Aturan Pembayaran</option>
                                <option value="courier">Panduan Kurir</option>
                                <option value="roles">Hak Akses Role</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Role Diizinkan</label>
                            <input type="text" name="roles_allowed" class="form-control rounded-3" value="all" placeholder="all, atau pisah koma: customer,kurir">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Judul Entri</label>
                            <input type="text" name="title" class="form-control rounded-3" placeholder="Contoh: Cara Kerja Penjemputan Laundry" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Kata Kunci Pencarian (Pisah Koma)</label>
                            <input type="text" name="keywords" class="form-control rounded-3" placeholder="jemput, ambil cucian, alur kurir" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Isi Konten Jawaban (Format Markdown / Teks)</label>
                            <textarea name="content" class="form-control rounded-3" rows="4" placeholder="Penjelasan lengkap yang akan dijawab bot..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Simpan Artikel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Tambah Agen AI Cadangan -->
<div class="modal fade" id="modalAddAIModel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom p-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-robot fs-5"></i></div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0">Tambah AI Agent Cadangan Baru</h6>
                        <small class="text-secondary">Model tambahan ini otomatis digunakan jika model utama offline/limit</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= url('platform/settings/ai-models/store') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Nama / Label Agen</label>
                            <input type="text" name="name" class="form-control rounded-3" placeholder="contoh: Backup Gemini 2.0 / Groq Llama" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Penyedia AI (Provider)</label>
                            <select name="provider" class="form-select rounded-3" required>
                                <option value="gemini">Google Gemini</option>
                                <option value="openai">OpenAI (ChatGPT)</option>
                                <option value="groq">Groq Cloud (Llama/Mixtral)</option>
                                <option value="deepseek">DeepSeek</option>
                                <option value="custom">Custom OpenAI-Compatible</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Nama Model</label>
                            <input type="text" name="model" class="form-control rounded-3" list="modelSuggestions" placeholder="e.g. gemini-1.5-flash" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">API Key Khusus Model Ini</label>
                            <div class="input-group">
                                <input type="password" name="api_key" id="add_modal_api_key" class="form-control rounded-start-3" placeholder="Masukkan API Key" required>
                                <button class="btn btn-outline-secondary rounded-end-3 toggle-password-btn" type="button" data-target="#add_modal_api_key">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Custom Base URL (Opsional)</label>
                            <input type="text" name="base_url" class="form-control rounded-3" placeholder="https://api.openai.com/v1 (kosongkan untuk default)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Tambahkan Agen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Agen AI Cadangan -->
<div class="modal fade" id="modalEditAIModel" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom p-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-pencil-square fs-5"></i></div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0">Edit Agen AI Cadangan</h6>
                        <small class="text-secondary">Ubah kredensial atau parameter model cadangan</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditAIModel" action="" method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Nama / Label Agen</label>
                            <input type="text" name="name" id="edit_modal_name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Penyedia AI (Provider)</label>
                            <select name="provider" id="edit_modal_provider" class="form-select rounded-3" required>
                                <option value="gemini">Google Gemini</option>
                                <option value="openai">OpenAI (ChatGPT)</option>
                                <option value="groq">Groq Cloud (Llama/Mixtral)</option>
                                <option value="deepseek">DeepSeek</option>
                                <option value="custom">Custom OpenAI-Compatible</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-dark">Nama Model</label>
                            <input type="text" name="model" id="edit_modal_model" class="form-control rounded-3" list="modelSuggestions" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Ganti API Key (Opsional)</label>
                            <div class="input-group">
                                <input type="password" name="api_key" id="edit_modal_api_key" class="form-control rounded-start-3" placeholder="Kosongkan jika tidak ingin mengubah key">
                                <button class="btn btn-outline-secondary rounded-end-3 toggle-password-btn" type="button" data-target="#edit_modal_api_key">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold text-dark">Custom Base URL (Opsional)</label>
                            <input type="text" name="base_url" id="edit_modal_base_url" class="form-control rounded-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top p-3">
                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/Hide Password Eye Toggle
    document.querySelectorAll('.toggle-password-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetSel = this.getAttribute('data-target');
            const input = document.querySelector(targetSel);
            if (!input) return;
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'bi bi-eye';
            }
        });
    });

    // Edit AI Agent Modal Populate
    document.querySelectorAll('.btn-edit-agent').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const prov = this.getAttribute('data-provider');
            const model = this.getAttribute('data-model');
            const baseurl = this.getAttribute('data-baseurl');

            document.getElementById('edit_modal_name').value = name;
            document.getElementById('edit_modal_provider').value = prov;
            document.getElementById('edit_modal_model').value = model;
            document.getElementById('edit_modal_base_url').value = baseurl || '';
            document.getElementById('edit_modal_api_key').value = '';

            const form = document.getElementById('formEditAIModel');
            form.action = '<?= url('platform/settings/ai-models/') ?>' + id + '/update';

            const modal = new bootstrap.Modal(document.getElementById('modalEditAIModel'));
            modal.show();
        });
    });

    // Helper AJAX Test Connection
    function runAITestConnection(provider, model, apiKey, baseUrl, btnElem, resultElem) {
        btnElem.disabled = true;
        const originalHtml = btnElem.innerHTML;
        btnElem.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menguji...';
        resultElem.style.display = 'block';
        resultElem.innerHTML = '<div class="alert alert-warning py-2 px-3 small border-0 rounded-3 mb-0"><i class="bi bi-hourglass-split me-1"></i> Mengirim ping tes ke API ' + provider + ' (' + model + ')...</div>';

        const csrfToken = document.querySelector('input[name="_csrf_token"]').value;

        fetch('<?= url('platform/settings/ai-models/test-connection') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: new URLSearchParams({
                'provider': provider,
                'model': model,
                'api_key': apiKey,
                'base_url': baseUrl,
                '_csrf_token': csrfToken
            })
        })
        .then(res => res.json())
        .then(data => {
            btnElem.disabled = false;
            btnElem.innerHTML = originalHtml;
            if (data.success) {
                resultElem.innerHTML = '<div class="alert alert-success py-2 px-3 small border-0 rounded-3 mb-0"><i class="bi bi-check-circle-fill me-1"></i> <strong>' + data.message + '</strong>' + (data.reply ? ' &mdash; Respon: <em>&quot;' + data.reply + '&quot;</em>' : '') + '</div>';
            } else {
                resultElem.innerHTML = '<div class="alert alert-danger py-2 px-3 small border-0 rounded-3 mb-0"><i class="bi bi-x-circle-fill me-1"></i> ' + (data.error || 'Koneksi gagal.') + '</div>';
            }
        })
        .catch(err => {
            btnElem.disabled = false;
            btnElem.innerHTML = originalHtml;
            resultElem.innerHTML = '<div class="alert alert-danger py-2 px-3 small border-0 rounded-3 mb-0"><i class="bi bi-x-circle-fill me-1"></i> Terjadi kegagalan jaringan saat menguji koneksi.</div>';
        });
    }

    // Tes Koneksi AI Utama
    const btnTestPrimary = document.getElementById('btnTestPrimaryAI');
    if (btnTestPrimary) {
        btnTestPrimary.addEventListener('click', function() {
            const prov = document.getElementById('primary_ai_provider').value;
            const model = document.getElementById('primary_ai_model').value;
            const apiKey = document.getElementById('primary_ai_api_key').value;
            const baseUrl = document.getElementById('primary_ai_base_url').value;
            const resDiv = document.getElementById('primaryTestResult');
            runAITestConnection(prov, model, apiKey, baseUrl, this, resDiv);
        });
    }

    // Auto-fill Base URL and suggest model when provider changes
    const providerSelect = document.getElementById('primary_ai_provider');
    const baseUrlInput   = document.getElementById('primary_ai_base_url');
    const modelInput     = document.getElementById('primary_ai_model');
    const BASE_URLS = {
        gemini:   '',
        openai:   'https://api.openai.com/v1',
        groq:     'https://api.groq.com/openai/v1',
        deepseek: 'https://api.deepseek.com',
        custom:   '',
        mock:     ''
    };
    const DEFAULT_MODELS = {
        gemini:   'gemini-1.5-flash',
        openai:   'gpt-4o-mini',
        groq:     'llama-3.3-70b-versatile',
        deepseek: 'deepseek-chat',
        mock:     'mock',
        custom:   ''
    };
    if (providerSelect) {
        providerSelect.addEventListener('change', function () {
            const prov = this.value;
            if (baseUrlInput && (baseUrlInput.value === '' || Object.values(BASE_URLS).includes(baseUrlInput.value))) {
                baseUrlInput.value = BASE_URLS[prov] ?? '';
            }
            if (modelInput && modelInput.value === '' && DEFAULT_MODELS[prov]) {
                modelInput.value = DEFAULT_MODELS[prov];
            }
        });
    }

    // Tes Koneksi Agen Cadangan
    document.querySelectorAll('.btn-test-agent').forEach(btn => {
        btn.addEventListener('click', function() {
            const prov = this.getAttribute('data-provider');
            const model = this.getAttribute('data-model');
            const key = this.getAttribute('data-key');
            const baseurl = this.getAttribute('data-baseurl');
            const name = this.getAttribute('data-name');
            const resDiv = document.getElementById('primaryTestResult');
            resDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            runAITestConnection(prov, model, key, baseurl, this, resDiv);
        });
    });
    // Test Integration Connection AJAX
    document.querySelectorAll('.btn-test-conn').forEach(btn => {
        btn.addEventListener('click', function() {
            const svc = this.getAttribute('data-service');
            const resultMsg = this.nextElementSibling;
            this.disabled = true;
            resultMsg.innerHTML = '<span class="spinner-border spinner-border-sm text-primary"></span> Menghubungkan...';

            const csrfToken = document.querySelector('input[name="_csrf_token"]').value;

            fetch('<?= url('platform/settings/test-integration') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': csrfToken
                },
                body: new URLSearchParams({
                    'service': svc,
                    '_csrf_token': csrfToken
                })
            })
            .then(res => res.json())
            .then(data => {
                this.disabled = false;
                if (data.success) {
                    resultMsg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill"></i> ' + data.message + '</span>';
                } else {
                    resultMsg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> ' + (data.message || data.error) + '</span>';
                }
            })
            .catch(err => {
                this.disabled = false;
                resultMsg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill"></i> Gagal memeriksa koneksi.</span>';
            });
        });
    });
});
</script>
