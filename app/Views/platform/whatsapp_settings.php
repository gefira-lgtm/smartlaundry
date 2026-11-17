<?php
/**
 * WhatsApp Multi-Category & Multi-Message Settings (Admin Platform)
 * 3 Tabs: Kode OTP Masuk WA, Transaksi Masuk WA, Aktivitas Masuk WA
 * Mendukung pengiriman LEBIH DARI SATU pesan WhatsApp terpisah per event.
 */
$csrfToken = csrf_token();
$overrideEnabled     = \App\Models\Setting::get('wa_override_enabled', '0');
$overrideCustomer    = \App\Models\Setting::get('wa_override_customer_phone', '');
$overrideCourier     = \App\Models\Setting::get('wa_override_courier_phone', '');
$overrideAdmin       = \App\Models\Setting::get('wa_override_admin_phone', '');
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/platform') ?>" class="text-decoration-none">Dashboard Platform</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengaturan Notifikasi WhatsApp</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-whatsapp text-success"></i> Pengaturan Notifikasi WhatsApp
            </h1>
            <p class="text-muted small mb-0">Kelola 3 kategori utama dengan dukungan pengiriman pesan bertahap (multi-chat) terpisah per event.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($user['role'] === 'owner_platform'): ?>
                <button type="button" class="btn btn-primary rounded-pill px-3 btn-sm" id="btnTestSend">
                    <i class="bi bi-send me-1"></i> Kirim Pesan Tes
                </button>
            <?php endif; ?>
            <a href="<?= url('platform/whatsapp-settings?tab=' . $activeTab) ?>" class="btn btn-outline-primary rounded-pill px-3 btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </a>
            <a href="<?= url('dashboard/platform') ?>" class="btn btn-secondary rounded-pill px-3 btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Platform
            </a>
        </div>
    </div>

    <!-- Gateway & Device Info Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-hdd-network fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Gateway Provider</div>
                        <h4 class="fw-bold mb-0 text-dark fs-6 text-uppercase"><?= e($gatewayType) ?></h4>
                        <span class="badge <?= $hasToken ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> fs-8 mt-1">
                            <?= $hasToken ? 'Token Fonnte Terpasang' : 'Token Kosong' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="<?= ($deviceInfo['connected'] ?? false) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> p-3 rounded-circle">
                        <i class="bi bi-phone fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Status Perangkat</div>
                        <?php if ($gatewayType !== 'fonnte'): ?>
                            <h4 class="fw-bold mb-0 text-secondary fs-6">Mode Simulasi</h4>
                            <span class="small text-muted fs-8">Uji Coba Lokal</span>
                        <?php elseif ($deviceInfo['connected']): ?>
                            <h4 class="fw-bold mb-0 text-success fs-6"><?= e(strtoupper($deviceInfo['status'] ?? 'CONNECTED')) ?></h4>
                            <span class="small text-muted fs-8"><?= e($deviceInfo['device'] ?? 'Device Online') ?></span>
                        <?php else: ?>
                            <h4 class="fw-bold mb-0 text-danger fs-6"><?= e(strtoupper($deviceInfo['status'] ?? 'DISCONNECTED')) ?></h4>
                            <span class="small text-muted fs-8">Periksa Koneksi Fonnte</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-success-subtle text-success p-3 rounded-circle">
                        <i class="bi bi-check2-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Terkirim Hari Ini</div>
                        <h4 class="fw-bold mb-0 text-success fs-4"><?= number_format($todayStats['sent_today'] ?? 0) ?></h4>
                        <span class="text-muted fs-8">Dari <?= number_format($todayStats['total_today'] ?? 0) ?> antrean</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-danger-subtle text-danger p-3 rounded-circle">
                        <i class="bi bi-x-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Gagal Hari Ini</div>
                        <h4 class="fw-bold mb-0 text-danger fs-4"><?= number_format($todayStats['failed_today'] ?? 0) ?></h4>
                        <span class="text-muted fs-8">Auto-retry max 3x</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PANEL MODE OVERRIDE NOMOR WA -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 <?= $overrideEnabled ? 'border-warning border-2' : '' ?>">
        <div class="card-header bg-warning-subtle border-0 rounded-top-4 d-flex align-items-center justify-content-between py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-telephone-forward text-warning fs-5"></i>
                <div>
                    <h6 class="fw-bold mb-0">Mode Override Nomor WhatsApp</h6>
                    <small class="text-muted">Alihkan semua notifikasi ke nomor pengujian agar tidak mengganggu pelanggan / kurir asli</small>
                </div>
            </div>
            <?php if ($overrideEnabled): ?>
                <span class="badge bg-warning text-dark rounded-pill px-3"><i class="bi bi-exclamation-triangle-fill me-1"></i>OVERRIDE AKTIF</span>
            <?php else: ?>
                <span class="badge bg-secondary rounded-pill px-3">Nonaktif</span>
            <?php endif; ?>
        </div>
        <div class="card-body px-4 pb-4">
            <?php if ($overrideEnabled): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="small fw-semibold">Semua notifikasi WA sedang dialihkan ke nomor pengujian di bawah. Nomor customer/kurir asli TIDAK menerima pesan.</span>
                </div>
            <?php endif; ?>
            <form action="<?= url('platform/whatsapp-settings/override') ?>" method="POST">
                <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="wa_override_enabled" id="waOverrideEnabled" value="1"
                                   <?= $overrideEnabled ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="waOverrideEnabled">
                                Aktifkan Mode Override Nomor
                            </label>
                        </div>
                        <small class="text-muted d-block ms-5">Jika aktif, seluruh notifikasi WA diarahkan ke nomor di bawah (kecuali OTP).</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold"><i class="bi bi-person-circle text-primary me-1"></i>Nomor Override Customer</label>
                        <div class="input-group">
                            <span class="input-group-text bg-primary-subtle text-primary border-0"><i class="bi bi-whatsapp"></i></span>
                            <input type="text" name="wa_override_customer_phone" class="form-control"
                                   placeholder="08xxx / 62xxx"
                                   value="<?= e($overrideCustomer) ?>">
                        </div>
                        <small class="text-muted">Pesan ke Customer dialihkan ke nomor ini</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold"><i class="bi bi-scooter text-success me-1"></i>Nomor Override Kurir</label>
                        <div class="input-group">
                            <span class="input-group-text bg-success-subtle text-success border-0"><i class="bi bi-whatsapp"></i></span>
                            <input type="text" name="wa_override_courier_phone" class="form-control"
                                   placeholder="08xxx / 62xxx"
                                   value="<?= e($overrideCourier) ?>">
                        </div>
                        <small class="text-muted">Pesan ke Kurir (aktivitas) dialihkan ke nomor ini</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold"><i class="bi bi-shop text-warning me-1"></i>Nomor Override Admin/Toko</label>
                        <div class="input-group">
                            <span class="input-group-text bg-warning-subtle text-warning border-0"><i class="bi bi-whatsapp"></i></span>
                            <input type="text" name="wa_override_admin_phone" class="form-control"
                                   placeholder="08xxx / 62xxx"
                                   value="<?= e($overrideAdmin) ?>">
                        </div>
                        <small class="text-muted">Pesan ke Admin/Staff Toko dialihkan ke nomor ini</small>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-warning rounded-pill px-4 fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan Pengaturan Override
                        </button>
                        <?php if ($overrideEnabled): ?>
                            <span class="text-warning fw-semibold small ms-3"><i class="bi bi-exclamation-circle me-1"></i>Mode override sedang aktif!</span>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 3 MAIN TABS -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-light border-bottom p-0">
            <ul class="nav nav-tabs nav-fill card-header-tabs m-0 border-0 fs-6 fw-bold">
                <li class="nav-item">
                    <a class="nav-link py-3 border-0 <?= $activeTab === 'otp' ? 'active bg-white text-primary border-top border-top-3 border-primary' : 'text-muted' ?>" 
                       href="<?= url('platform/whatsapp-settings?tab=otp') ?>">
                        <i class="bi bi-shield-lock me-2"></i> 1. Kode OTP Masuk WA
                        <?php if ($otpMaster): ?>
                            <span class="badge bg-success ms-2">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger ms-2">Nonaktif</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-3 border-0 <?= $activeTab === 'transaction' ? 'active bg-white text-primary border-top border-top-3 border-primary' : 'text-muted' ?>" 
                       href="<?= url('platform/whatsapp-settings?tab=transaction') ?>">
                        <i class="bi bi-receipt me-2"></i> 2. Transaksi Masuk WA
                        <?php if ($trxMaster): ?>
                            <span class="badge bg-success ms-2">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger ms-2">Nonaktif</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-3 border-0 <?= $activeTab === 'activity' ? 'active bg-white text-primary border-top border-top-3 border-primary' : 'text-muted' ?>" 
                       href="<?= url('platform/whatsapp-settings?tab=activity') ?>">
                        <i class="bi bi-activity me-2"></i> 3. Aktivitas Masuk WA
                        <?php if ($actMaster): ?>
                            <span class="badge bg-success ms-2">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger ms-2">Nonaktif</span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-4">

            <!-- TAB 1: KODE OTP MASUK WA -->
            <?php if ($activeTab === 'otp'): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-shield-lock text-primary me-2"></i>Pengaturan Global Notifikasi Kode OTP</h5>
                            <p class="text-muted small mb-0">Kode OTP dikirimkan via WhatsApp untuk login, verifikasi nomor HP, verifikasi akun, dan reset password.</p>
                        </div>
                    </div>

                    <form action="<?= url('platform/whatsapp-settings/update') ?>" method="POST">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="tab" value="otp">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card border rounded-3 p-3 bg-light">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch" id="masterOtpSwitch" name="master_enabled" value="1" <?= $otpMaster ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold text-dark" for="masterOtpSwitch">Aktifkan Pengiriman OTP via WhatsApp</label>
                                    </div>
                                    <div class="small text-muted">Jika dinonaktifkan, pengiriman kode OTP via WhatsApp akan dicegat untuk seluruh pengguna sistem.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border rounded-3 p-3 bg-light">
                                    <label class="form-label fw-bold text-dark small mb-1">Masa Berlaku Kode OTP (Menit)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="otp_expiry_minutes" value="<?= (int)Setting::get('otp_expiry_minutes', 5) ?>" min="1" max="60">
                                        <span class="input-group-text">Menit</span>
                                    </div>
                                    <div class="small text-muted mt-1">Masa berlaku kode sebelum kedaluwarsa (maks. batas percobaan: 3 kali).</div>
                                </div>
                            </div>
                        </div>

                        <!-- Daftar Pesan OTP -->
                        <div class="card border rounded-3 p-3 mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-chat-text text-primary me-1"></i> Template Pesan WhatsApp OTP</h6>
                                    <small class="text-muted">Setiap event OTP dapat memiliki template dan varian pesan tersendiri.</small>
                                </div>
                            </div>

                            <?php
                            $otpEvents = [
                                'otp_whatsapp' => '1. OTP Verifikasi Standar',
                                'otp_login' => '2. OTP Login Masuk Akun',
                                'otp_verify_phone' => '3. OTP Verifikasi Nomor WhatsApp',
                                'otp_reset_password' => '4. OTP Reset Password Akun',
                                'otp_verify_account' => '5. OTP Aktivasi Akun Pengguna'
                            ];
                            foreach ($otpEvents as $evKey => $evLabel):
                                $messages = $templateModel->getEventMessages('OTP', $evKey);
                            ?>
                                <div class="border rounded-3 p-3 mb-3 bg-white">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <span class="fw-bold text-dark"><?= e($evLabel) ?></span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-2"><?= count($messages) ?> Pesan</span>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2 fs-8 btn-add-message"
                                                data-bs-toggle="modal" data-bs-target="#modalAddMessage"
                                                data-category="OTP" data-event-key="<?= e($evKey) ?>"
                                                data-event-label="<?= e($evLabel) ?>"
                                                data-next-seq="<?= count($messages) + 1 ?>">
                                            <i class="bi bi-plus-circle me-1"></i> Tambah Pesan
                                        </button>
                                    </div>

                                    <?php foreach ($messages as $msg): ?>
                                        <div class="card border rounded-3 p-2 mb-2 bg-light">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                                <div>
                                                    <span class="badge bg-primary me-1">Pesan <?= $msg['message_sequence'] ?></span>
                                                    <span class="fw-semibold small text-dark"><?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?></span>
                                                    <?php if (!empty($msg['is_default'])): ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1 fs-9">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-8 btn-preview-message"
                                                            data-bs-toggle="modal" data-bs-target="#modalPreviewMessage"
                                                            data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                            data-content="<?= e($msg['template_content']) ?>">
                                                        <i class="bi bi-eye"></i> Preview
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 fs-8 btn-test-message"
                                                            data-bs-toggle="modal" data-bs-target="#modalTestMessage"
                                                            data-event-key="<?= e($evKey) ?>"
                                                            data-sequence="<?= $msg['message_sequence'] ?>"
                                                            data-content="<?= e($msg['template_content']) ?>">
                                                        <i class="bi bi-send"></i> Uji Kirim
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-8 btn-edit-message"
                                                            data-bs-toggle="modal" data-bs-target="#modalEditMessage"
                                                            data-id="<?= $msg['id'] ?? '' ?>"
                                                            data-category="OTP"
                                                            data-event-key="<?= e($evKey) ?>"
                                                            data-sequence="<?= $msg['message_sequence'] ?>"
                                                            data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                            data-content="<?= e($msg['template_content']) ?>"
                                                            data-enabled="<?= $msg['is_enabled'] ? '1' : '0' ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                    <?php if (!empty($msg['id'])): ?>
                                                        <form action="<?= url('platform/whatsapp-settings/messages/' . $msg['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus pesan ini?')">
                                                            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fs-8"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="small font-monospace text-muted bg-white p-2 rounded border" style="white-space: pre-wrap; font-size: 0.78rem;"><?= e($msg['template_content']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="alert alert-info py-2 px-3 small rounded-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i> Demi privasi dan keamanan akun, kode OTP <strong>TIDAK PERNAH</strong> disimpan dalam bentuk teks polos pada database log riwayat outbox (kode disamarkan otomatis menjadi bintang).
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan Pengaturan Global OTP
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- TAB 2: TRANSAKSI MASUK WA (MULTI-MESSAGE PER EVENT) -->
            <?php if ($activeTab === 'transaction'): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-receipt text-primary me-2"></i>Pengaturan Notifikasi Transaksi WhatsApp (Multi-Chat)</h5>
                            <p class="text-muted small mb-0">Setiap tahapan proses dapat mengirim lebih dari satu chat pesan WhatsApp secara terpisah dan berurutan.</p>
                        </div>
                    </div>

                    <form action="<?= url('platform/whatsapp-settings/update') ?>" method="POST">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="tab" value="transaction">

                        <div class="card border rounded-3 p-3 bg-light mb-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="masterTrxSwitch" name="master_enabled" value="1" <?= $trxMaster ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark" for="masterTrxSwitch">Aktifkan Notifikasi Transaksi Global</label>
                            </div>
                            <div class="small text-muted">Saklar utama untuk mengaktifkan atau menonaktifkan seluruh pengiriman pesan transaksi sistem.</div>
                        </div>

                        <div class="accordion mb-4" id="accordionTrxEvents">
                            <!-- Section: Event Customer (13 Tahapan) -->
                            <div class="accordion-item border rounded-3 mb-3 overflow-hidden shadow-sm">
                                <h2 class="accordion-header" id="headingCustomerEvents">
                                    <button class="accordion-button fw-bold text-dark bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCustomerEvents">
                                        <i class="bi bi-person-check text-primary me-2"></i> Notifikasi Status Pesanan Customer (Multi-Pesan per Event)
                                    </button>
                                </h2>
                                <div id="collapseCustomerEvents" class="accordion-collapse collapse show">
                                    <div class="accordion-body p-3">
                                        <?php
                                        $custEvents = [
                                            'customer_order_created' => '1. Pesanan Dibuat (Pesanan diterima toko)',
                                            'customer_courier_to_pickup' => '2. Kurir Menjemput: Penugasan & Perjalanan Kurir (2 Pesan Terpisah)',
                                            'customer_laundry_picked' => '3. Laundry Diambil Kurir (Menuju outlet)',
                                            'customer_arrived_at_store' => '4. Laundry Diterima Toko (Sedang ditimbang)',
                                            'customer_weighing_completed' => '5. Penimbangan Selesai (Tagihan siap dibayar)',
                                            'customer_ready_to_pay_qris' => 'Tahap 1: Laundry Selesai Ditimbang (QRIS Dinamis 1 Jam)',
                                            'customer_ready_to_pay_cash' => 'Tahap 1: Laundry Selesai Ditimbang (Tunai / CASH)',
                                            'customer_payment_success' => 'Tahap 2: Pembayaran Berhasil Diterima (LUNAS)',
                                            'customer_payment_expired' => 'Pembayaran Kedaluwarsa',
                                            'customer_laundry_washing' => '6. Sedang Diproses / Dicuci',
                                            'customer_laundry_ready' => '7. Laundry Selesai Dicuci & Bersih Wangi',
                                            'customer_courier_to_delivery' => '8. Kurir Mengantar ke Lokasi Customer',
                                            'customer_order_completed' => '9. Pesanan Selesai / Diterima Customer'
                                        ];
                                        foreach ($custEvents as $evKey => $evLabel):
                                            $isEnabled = !isset($trxSettings[$evKey]) || (bool)$trxSettings[$evKey]['is_enabled'];
                                            $messages = $templateModel->getEventMessages('TRANSACTION', $evKey);
                                        ?>
                                            <div class="border rounded-3 p-3 mb-3 bg-white">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                    <div>
                                                        <span class="fw-bold text-dark"><?= e($evLabel) ?></span>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-2"><?= count($messages) ?> Pesan Terpisah</span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="form-check form-switch mb-0">
                                                            <input class="form-check-input" type="checkbox" name="events[<?= $evKey ?>]" value="1" <?= $isEnabled ? 'checked' : '' ?> title="Aktifkan/Nonaktifkan Event Ini">
                                                        </div>
                                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2 fs-8 btn-add-message"
                                                                data-bs-toggle="modal" data-bs-target="#modalAddMessage"
                                                                data-category="TRANSACTION" data-event-key="<?= e($evKey) ?>"
                                                                data-event-label="<?= e($evLabel) ?>"
                                                                data-next-seq="<?= count($messages) + 1 ?>">
                                                            <i class="bi bi-plus-circle me-1"></i> Tambah Pesan
                                                        </button>
                                                    </div>
                                                </div>

                                                <!-- Daftar Pesan Terpisah untuk Event Ini -->
                                                <div class="mt-2">
                                                    <?php foreach ($messages as $msg): ?>
                                                        <div class="card border rounded-3 p-2 mb-2 bg-light">
                                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                                                <div>
                                                                    <span class="badge bg-primary me-1">Pesan <?= $msg['message_sequence'] ?></span>
                                                                    <span class="fw-semibold small text-dark"><?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?></span>
                                                                    <?php if (!empty($msg['is_default'])): ?>
                                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1 fs-9">Default</span>
                                                                    <?php endif; ?>
                                                                    <span class="badge <?= $msg['is_enabled'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> ms-1 fs-9">
                                                                        <?= $msg['is_enabled'] ? 'Aktif' : 'Nonaktif' ?>
                                                                    </span>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-8 btn-preview-message"
                                                                            data-bs-toggle="modal" data-bs-target="#modalPreviewMessage"
                                                                            data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                                            data-content="<?= e($msg['template_content']) ?>">
                                                                        <i class="bi bi-eye"></i> Preview
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-success py-0 px-2 fs-8 btn-test-message"
                                                                            data-bs-toggle="modal" data-bs-target="#modalTestMessage"
                                                                            data-event-key="<?= e($evKey) ?>"
                                                                            data-sequence="<?= $msg['message_sequence'] ?>"
                                                                            data-content="<?= e($msg['template_content']) ?>">
                                                                        <i class="bi bi-send"></i> Uji Kirim
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-8 btn-edit-message"
                                                                            data-bs-toggle="modal" data-bs-target="#modalEditMessage"
                                                                            data-id="<?= $msg['id'] ?? '' ?>"
                                                                            data-category="TRANSACTION"
                                                                            data-event-key="<?= e($evKey) ?>"
                                                                            data-sequence="<?= $msg['message_sequence'] ?>"
                                                                            data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                                            data-content="<?= e($msg['template_content']) ?>"
                                                                            data-enabled="<?= $msg['is_enabled'] ? '1' : '0' ?>">
                                                                        <i class="bi bi-pencil"></i> Edit
                                                                    </button>
                                                                    <?php if (!empty($msg['id'])): ?>
                                                                        <form action="<?= url('platform/whatsapp-settings/messages/' . $msg['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus pesan <?= $msg['message_sequence'] ?> ini?')">
                                                                            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fs-8"><i class="bi bi-trash"></i></button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="small font-monospace text-muted bg-white p-2 rounded border" style="white-space: pre-wrap; font-size: 0.78rem; max-height: 90px; overflow-y: auto;"><?= e($msg['template_content']) ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Event Store & Admin -->
                            <div class="accordion-item border rounded-3 mb-2 overflow-hidden shadow-sm">
                                <h2 class="accordion-header" id="headingStoreEvents">
                                    <button class="accordion-button collapsed fw-bold text-dark bg-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStoreEvents">
                                        <i class="bi bi-shop text-success me-2"></i> Notifikasi Transaksi Toko & Pemilik
                                    </button>
                                </h2>
                                <div id="collapseStoreEvents" class="accordion-collapse collapse">
                                    <div class="accordion-body p-3">
                                        <?php
                                        $storeEvents = [
                                            'store_new_order' => 'Pesanan Baru Masuk Toko',
                                            'store_payment_received_owner' => 'Pembayaran Masuk Toko (Owner)',
                                            'store_payment_expired' => 'Pembayaran Kedaluwarsa Toko',
                                            'store_daily_summary' => 'Ringkasan Harian Keuangan Toko'
                                        ];
                                        foreach ($storeEvents as $evKey => $evLabel):
                                            $isEnabled = !isset($trxSettings[$evKey]) || (bool)$trxSettings[$evKey]['is_enabled'];
                                            $messages = $templateModel->getEventMessages('TRANSACTION', $evKey);
                                        ?>
                                            <div class="border rounded-3 p-3 mb-3 bg-white">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                    <div>
                                                        <span class="fw-bold text-dark"><?= e($evLabel) ?></span>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-2"><?= count($messages) ?> Pesan</span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="form-check form-switch mb-0">
                                                            <input class="form-check-input" type="checkbox" name="events[<?= $evKey ?>]" value="1" <?= $isEnabled ? 'checked' : '' ?>>
                                                        </div>
                                                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2 fs-8 btn-add-message"
                                                                data-bs-toggle="modal" data-bs-target="#modalAddMessage"
                                                                data-category="TRANSACTION" data-event-key="<?= e($evKey) ?>"
                                                                data-event-label="<?= e($evLabel) ?>"
                                                                data-next-seq="<?= count($messages) + 1 ?>">
                                                            <i class="bi bi-plus-circle me-1"></i> Tambah
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="mt-2">
                                                    <?php foreach ($messages as $msg): ?>
                                                        <div class="card border rounded-3 p-2 mb-2 bg-light">
                                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                                <div>
                                                                    <span class="badge bg-primary me-1">Pesan <?= $msg['message_sequence'] ?></span>
                                                                    <span class="fw-semibold small text-dark"><?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?></span>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-8 btn-preview-message"
                                                                            data-bs-toggle="modal" data-bs-target="#modalPreviewMessage"
                                                                            data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                                            data-content="<?= e($msg['template_content']) ?>">
                                                                        <i class="bi bi-eye"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-8 btn-edit-message"
                                                                            data-bs-toggle="modal" data-bs-target="#modalEditMessage"
                                                                            data-id="<?= $msg['id'] ?? '' ?>"
                                                                            data-category="TRANSACTION"
                                                                            data-event-key="<?= e($evKey) ?>"
                                                                            data-sequence="<?= $msg['message_sequence'] ?>"
                                                                            data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                                            data-content="<?= e($msg['template_content']) ?>"
                                                                            data-enabled="<?= $msg['is_enabled'] ? '1' : '0' ?>">
                                                                        <i class="bi bi-pencil"></i>
                                                                    </button>
                                                                    <?php if (!empty($msg['id'])): ?>
                                                                        <form action="<?= url('platform/whatsapp-settings/messages/' . $msg['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus pesan ini?')">
                                                                            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fs-8"><i class="bi bi-trash"></i></button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="small font-monospace text-muted bg-white p-2 rounded border" style="white-space: pre-wrap; font-size: 0.78rem;"><?= e($msg['template_content']) ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold mb-4">
                            <i class="bi bi-save me-1"></i> Simpan Status Master Event Transaksi
                        </button>
                    </form>

                    <!-- Recipient Management Section (Transaction) -->
                    <div class="card border rounded-3 p-3 bg-light mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-people text-primary me-1"></i> Nomor Penerima Tambahan Notifikasi Transaksi (Platform Scope)</h6>
                                <div class="text-muted small">Nomor WhatsApp tambahan untuk menerima salinan notifikasi transaksi platform.</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalAddTrxRecipient">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Penerima
                            </button>
                        </div>

                        <?php if (empty($trxRecipients)): ?>
                            <div class="text-muted small py-2">Belum ada nomor penerima tambahan. Notifikasi hanya dikirim ke customer dan staf toko terkait.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white mb-0 align-middle">
                                    <thead>
                                        <tr class="table-light">
                                            <th>Nama</th>
                                            <th>Nomor WhatsApp</th>
                                            <th>Peran</th>
                                            <th>Status</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trxRecipients as $rec): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= e($rec['recipient_name']) ?></td>
                                                <td><code><?= e($rec['phone_number']) ?></code></td>
                                                <td><span class="badge bg-secondary"><?= e($rec['role']) ?></span></td>
                                                <td>
                                                    <span class="badge <?= $rec['is_enabled'] ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $rec['is_enabled'] ? 'Aktif' : 'Nonaktif' ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <form action="<?= url('platform/whatsapp-settings/recipients/' . $rec['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus penerima ini?')">
                                                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill">Hapus</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 3: AKTIVITAS MASUK WA (MULTI-MESSAGE PER EVENT) -->
            <?php if ($activeTab === 'activity'): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-activity text-primary me-2"></i>Pengaturan Notifikasi Aktivitas WhatsApp (Multi-Chat)</h5>
                            <p class="text-muted small mb-0">Notifikasi otomatis saat terjadi perubahan data penting: tambah layanan, ubah harga, kurir, profil toko, atau pengaturan.</p>
                        </div>
                    </div>

                    <form action="<?= url('platform/whatsapp-settings/update') ?>" method="POST">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="tab" value="activity">

                        <div class="card border rounded-3 p-3 bg-light mb-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="masterActSwitch" name="master_enabled" value="1" <?= $actMaster ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark" for="masterActSwitch">Aktifkan Notifikasi Aktivitas Sistem</label>
                            </div>
                            <div class="small text-muted">Mengaktifkan pengiriman WhatsApp untuk perubahan operasional penting dan audit aktivitas.</div>
                        </div>

                        <div class="card border rounded-3 p-3 mb-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bell-fill text-warning me-1"></i> Event Aktivitas yang Dipantau</h6>
                            <?php
                            $actEvents = [
                                'activity_service_added' => 'Penambahan Layanan Laundry Baru',
                                'activity_price_changed' => 'Perubahan Harga Layanan Laundry (Harga lama -> Harga baru)',
                                'activity_store_updated' => 'Pembaruan Data & Profil Toko (Outlet / Jam Operasional)',
                                'activity_courier_changed' => 'Penambahan atau Perubahan Data Kurir',
                                'activity_user_role_changed' => 'Perubahan Role / Hak Akses Akun Pengguna',
                                'activity_setting_updated' => 'Perubahan Pengaturan Kritis Sistem Platform'
                            ];
                            foreach ($actEvents as $evKey => $evLabel):
                                $isEnabled = !isset($actSettings[$evKey]) || (bool)$actSettings[$evKey]['is_enabled'];
                                $messages = $templateModel->getEventMessages('ACTIVITY', $evKey);
                            ?>
                                <div class="border rounded-3 p-3 mb-3 bg-white">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <div>
                                            <span class="fw-bold text-dark"><?= e($evLabel) ?></span>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill ms-2"><?= count($messages) ?> Pesan</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" name="events[<?= $evKey ?>]" value="1" <?= $isEnabled ? 'checked' : '' ?>>
                                            </div>
                                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill py-0 px-2 fs-8 btn-add-message"
                                                    data-bs-toggle="modal" data-bs-target="#modalAddMessage"
                                                    data-category="ACTIVITY" data-event-key="<?= e($evKey) ?>"
                                                    data-event-label="<?= e($evLabel) ?>"
                                                    data-next-seq="<?= count($messages) + 1 ?>">
                                                <i class="bi bi-plus-circle me-1"></i> Tambah
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-2">
                                        <?php foreach ($messages as $msg): ?>
                                            <div class="card border rounded-3 p-2 mb-2 bg-light">
                                                <div class="d-flex align-items-center justify-content-between mb-1">
                                                    <div>
                                                        <span class="badge bg-primary me-1">Pesan <?= $msg['message_sequence'] ?></span>
                                                        <span class="fw-semibold small text-dark"><?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?></span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-8 btn-preview-message"
                                                                data-bs-toggle="modal" data-bs-target="#modalPreviewMessage"
                                                                data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                                data-content="<?= e($msg['template_content']) ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 fs-8 btn-edit-message"
                                                                data-bs-toggle="modal" data-bs-target="#modalEditMessage"
                                                                data-id="<?= $msg['id'] ?? '' ?>"
                                                                data-category="ACTIVITY"
                                                                data-event-key="<?= e($evKey) ?>"
                                                                data-sequence="<?= $msg['message_sequence'] ?>"
                                                                data-title="<?= e($msg['title'] ?? 'Pesan ' . $msg['message_sequence']) ?>"
                                                                data-content="<?= e($msg['template_content']) ?>"
                                                                data-enabled="<?= $msg['is_enabled'] ? '1' : '0' ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if (!empty($msg['id'])): ?>
                                                            <form action="<?= url('platform/whatsapp-settings/messages/' . $msg['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus pesan ini?')">
                                                                <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fs-8"><i class="bi bi-trash"></i></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="small font-monospace text-muted bg-white p-2 rounded border" style="white-space: pre-wrap; font-size: 0.78rem;"><?= e($msg['template_content']) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold mb-4">
                            <i class="bi bi-save me-1"></i> Simpan Status Master Event Aktivitas
                        </button>
                    </form>

                    <!-- Recipient Management Section (Activity) -->
                    <div class="card border rounded-3 p-3 bg-light mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bell-fill text-primary me-1"></i> Nomor Penerima Alert Aktivitas Sistem (Platform Scope)</h6>
                                <div class="text-muted small">Nomor WhatsApp administrator untuk menerima broadcast setiap terjadi perubahan penting.</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalAddActRecipient">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Penerima Alert
                            </button>
                        </div>

                        <?php if (empty($actRecipients)): ?>
                            <div class="text-muted small py-2">Belum ada nomor penerima alert aktivitas platform.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white mb-0 align-middle">
                                    <thead>
                                        <tr class="table-light">
                                            <th>Nama</th>
                                            <th>Nomor WhatsApp</th>
                                            <th>Peran</th>
                                            <th>Status</th>
                                            <th class="text-end">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($actRecipients as $rec): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= e($rec['recipient_name']) ?></td>
                                                <td><code><?= e($rec['phone_number']) ?></code></td>
                                                <td><span class="badge bg-secondary"><?= e($rec['role']) ?></span></td>
                                                <td>
                                                    <span class="badge <?= $rec['is_enabled'] ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $rec['is_enabled'] ? 'Aktif' : 'Nonaktif' ?>
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <form action="<?= url('platform/whatsapp-settings/recipients/' . $rec['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus penerima ini?')">
                                                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill">Hapus</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <hr class="my-4">

            <!-- OUTBOX / RIWAYAT PENGIRIMAN SESUAI KATEGORI AKTIF -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-clock-history me-1"></i> Riwayat Pengiriman Notifikasi (Kategori <?= strtoupper($activeTab) ?>)
                    </h5>
                    <p class="text-muted small mb-0">Daftar outbox dan log pengiriman WhatsApp dengan status per pesan terpisah real-time.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <form method="GET" action="<?= url('platform/whatsapp-settings') ?>" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                        
                        <?php if ($activeTab !== 'otp' && !empty($allStores)): ?>
                            <select name="store_id" class="form-select form-select-sm" style="width: 170px;" onchange="this.form.submit()">
                                <option value="">Semua Toko</option>
                                <?php foreach ($allStores as $st): ?>
                                    <option value="<?= $st['id'] ?>" <?= ($storeFilter === (int)$st['id']) ? 'selected' : '' ?>><?= e($st['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <select name="status" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="SENT" <?= $statusFilter === 'SENT' ? 'selected' : '' ?>>TERKIRIM</option>
                            <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>GAGAL</option>
                            <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                            <option value="SIMULATED" <?= $statusFilter === 'SIMULATED' ? 'selected' : '' ?>>SIMULASI</option>
                        </select>

                        <div class="input-group input-group-sm" style="width: 220px;">
                            <input type="text" name="search" class="form-control" placeholder="Cari nomor/pesan..." value="<?= e($searchFilter) ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Outbox Table -->
            <div class="table-responsive rounded-3 border">
                <table class="table table-hover align-middle mb-0 fs-8">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 150px;">Waktu Kirim</th>
                            <th style="width: 130px;">Nomor WA</th>
                            <th style="width: 140px;">Nama Penerima</th>
                            <th style="width: 150px;">Event & Urutan</th>
                            <th>Isi Pesan Chat</th>
                            <th style="width: 100px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                    Belum ada log pengiriman untuk kategori ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-muted">
                                        <?= date('d/m/Y H:i:s', strtotime($log['created_at'] ?? $log['sent_at'])) ?>
                                    </td>
                                    <td>
                                        <code class="text-dark fw-bold"><?= e($log['phone_number'] ?? $log['phone']) ?></code>
                                    </td>
                                    <td><?= e($log['recipient_name'] ?? 'User') ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= e($log['event_type'] ?? $log['event']) ?></span>
                                    </td>
                                    <td class="text-break">
                                        <span class="d-inline-block text-truncate" style="max-width: 420px;" title="<?= e($log['message']) ?>">
                                            <?= e($log['message']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $st = $log['status'];
                                        $badgeClass = match ($st) {
                                            'SENT' => 'bg-success',
                                            'FAILED' => 'bg-danger',
                                            'PENDING' => 'bg-warning text-dark',
                                            'SIMULATED' => 'bg-info text-dark',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= e($st) ?></span>
                                        <?php if (!empty($log['last_error'])): ?>
                                            <div class="text-danger fs-8 mt-1" title="<?= e($log['last_error']) ?>">
                                                <?= e(substr($log['last_error'], 0, 30)) ?>...
                                            </div>
                                        <?php endif; ?>
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

<!-- MODAL TAMBAH PESAN -->
<div class="modal fade" id="modalAddMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= url('platform/whatsapp-settings/messages/add') ?>" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="category" id="addMsgCategory" value="TRANSACTION">
            <input type="hidden" name="event_key" id="addMsgEventKey" value="">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="bi bi-chat-plus text-primary me-2"></i>Tambah Pesan Terpisah</h5>
                        <p class="text-muted small mb-0" id="addMsgEventLabel">Event: -</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Judul / Keterangan Pesan</label>
                            <input type="text" name="title" id="addMsgTitle" class="form-control" placeholder="Contoh: Pesan 2: Kurir Sedang Menuju Lokasi" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Urutan Pengiriman (Sequence)</label>
                            <input type="number" name="message_sequence" id="addMsgSequence" class="form-control" value="2" min="1" max="20" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Isi Template Pesan WhatsApp</label>
                        <div class="small text-muted mb-1">
                            Placeholder tersedia: <code>{nama_customer}</code>, <code>{nomor_pesanan}</code>, <code>{nama_kurir}</code>, <code>{wa_kurir}</code>, <code>{plat_nomor}</code>, <code>{jenis_kendaraan}</code>, <code>{store_name}</code>, <code>{nama_layanan}</code>, <code>{total_harga}</code>, <code>{link}</code>
                        </div>
                        <textarea name="template_content" id="addMsgContent" class="form-control font-monospace fs-8" rows="6" placeholder="Ketik isi template chat WhatsApp..." required></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="addMsgEnabled" checked>
                        <label class="form-check-label small fw-semibold" for="addMsgEnabled">Aktifkan pesan ini dalam alur pengiriman</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Pesan Baru</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT PESAN -->
<div class="modal fade" id="modalEditMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="formEditMessage" action="" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Template Pesan WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Judul / Keterangan Pesan</label>
                            <input type="text" name="title" id="editMsgTitle" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Urutan Pengiriman (Sequence)</label>
                            <input type="number" name="message_sequence" id="editMsgSequence" class="form-control" min="1" max="20" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Isi Template Pesan WhatsApp</label>
                        <textarea name="template_content" id="editMsgContent" class="form-control font-monospace fs-8" rows="6" required></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="editMsgEnabled">
                        <label class="form-check-label small fw-semibold" for="editMsgEnabled">Aktifkan pesan ini dalam alur pengiriman</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Perbarui Template</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PREVIEW TEMPLATE -->
<div class="modal fade" id="modalPreviewMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-whatsapp text-success me-2"></i>Preview Chat WhatsApp</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 rounded-4" style="background-color: #E5DDD5; min-height: 180px;">
                    <div class="bg-white p-3 rounded-3 shadow-sm border" style="max-width: 90%; margin-left: auto;">
                        <div class="small fw-bold text-success mb-1">SmartLaundry Official</div>
                        <div id="previewMessageContent" class="text-dark small" style="white-space: pre-wrap; font-family: system-ui, -apple-system, sans-serif;">
                            Memuat preview...
                        </div>
                        <div class="text-end text-muted mt-2" style="font-size: 0.7rem;">
                            <?= date('H:i') ?> <i class="bi bi-check2-all text-primary ms-1"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL UJI KIRIM PESAN TERTENTU -->
<div class="modal fade" id="modalTestMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-send text-success me-2"></i>Uji Kirim Pesan Ini</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Kirim satu chat pesan ini ke nomor WhatsApp Anda untuk menguji format tampilan dan keterbacaan.</p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nomor WhatsApp Tujuan</label>
                    <input type="text" id="testTargetPhone" class="form-control" value="<?= e($user['phone'] ?? '081234567890') ?>" placeholder="08xxxxxxxxxx">
                    <small class="text-muted">Gunakan nomor yang terdaftar di WhatsApp.</small>
                </div>
                <div class="alert alert-secondary py-2 px-3 small rounded-3 mb-0" id="testSendStatus" style="display:none;"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success rounded-pill px-4" id="btnExecuteTestSend">
                    <i class="bi bi-send me-1"></i> Kirim Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH PENERIMA TRANSAKSI -->
<div class="modal fade" id="modalAddTrxRecipient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= url('platform/whatsapp-settings/recipients/add') ?>" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="category" value="TRANSACTION">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Tambah Penerima Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap / Jabatan</label>
                        <input type="text" name="recipient_name" class="form-control" placeholder="Contoh: CS Platform / Admin Pusat" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor WhatsApp</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="Contoh: 08123456789 atau +62812..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Peran / Label</label>
                        <input type="text" name="role" class="form-control" value="ADMIN_PLATFORM" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Penerima</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH PENERIMA AKTIVITAS -->
<div class="modal fade" id="modalAddActRecipient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= url('platform/whatsapp-settings/recipients/add') ?>" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="category" value="ACTIVITY">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Tambah Penerima Alert Aktivitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nama Lengkap</label>
                        <input type="text" name="recipient_name" class="form-control" placeholder="Contoh: Head of Operations" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor WhatsApp</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="Contoh: 08123456789" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Peran / Role</label>
                        <input type="text" name="role" class="form-control" value="SUPERVISOR" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Alert</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPT PENGELOLAAN MULTI-MESSAGE & UJI COBA -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Tombol Tes Kirim Global
    const btnTest = document.getElementById('btnTestSend');
    if (btnTest) {
        btnTest.addEventListener('click', function() {
            if (!confirm('Kirim pesan tes WhatsApp ke nomor profil atau nomor pengujian sekarang? (Batas: 3x per jam)')) return;
            btnTest.disabled = true;
            btnTest.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...';

            fetch('<?= url('platform/whatsapp-logs/test-send') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_csrf_token=<?= $csrfToken ?>'
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message || (data.success ? 'Pesan tes terkirim!' : 'Pengiriman gagal.'));
                window.location.reload();
            })
            .catch(err => {
                alert('Gagal menghubungi server: ' + err.message);
                btnTest.disabled = false;
                btnTest.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Pesan Tes';
            });
        });
    }

    // 2. Setup Modal Tambah Pesan
    document.querySelectorAll('.btn-add-message').forEach(btn => {
        btn.addEventListener('click', function() {
            const cat = this.getAttribute('data-category') || 'TRANSACTION';
            const evKey = this.getAttribute('data-event-key') || '';
            const evLabel = this.getAttribute('data-event-label') || evKey;
            const nextSeq = this.getAttribute('data-next-seq') || '2';

            document.getElementById('addMsgCategory').value = cat;
            document.getElementById('addMsgEventKey').value = evKey;
            document.getElementById('addMsgEventLabel').textContent = 'Event: ' + evLabel;
            document.getElementById('addMsgSequence').value = nextSeq;
            document.getElementById('addMsgTitle').value = 'Pesan ' + nextSeq;
            document.getElementById('addMsgContent').value = '';
        });
    });

    // 3. Setup Modal Edit Pesan
    document.querySelectorAll('.btn-edit-message').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id') || '';
            const title = this.getAttribute('data-title') || '';
            const seq = this.getAttribute('data-sequence') || '1';
            const content = this.getAttribute('data-content') || '';
            const enabled = this.getAttribute('data-enabled') === '1';

            const form = document.getElementById('formEditMessage');
            if (id) {
                form.action = '<?= url('platform/whatsapp-settings/messages/') ?>' + id + '/update';
            } else {
                // If it was a virtual default, route to add
                form.action = '<?= url('platform/whatsapp-settings/messages/add') ?>';
            }

            document.getElementById('editMsgTitle').value = title;
            document.getElementById('editMsgSequence').value = seq;
            document.getElementById('editMsgContent').value = content;
            document.getElementById('editMsgEnabled').checked = enabled;
        });
    });

    // 4. Setup Modal Preview Template (Real-time render)
    let currentPreviewContent = '';
    document.querySelectorAll('.btn-preview-message').forEach(btn => {
        btn.addEventListener('click', function() {
            currentPreviewContent = this.getAttribute('data-content') || '';
            const container = document.getElementById('previewMessageContent');
            container.textContent = 'Memuat preview...';

            fetch('<?= url('platform/whatsapp-settings/messages/preview') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_csrf_token=<?= $csrfToken ?>&template_content=' + encodeURIComponent(currentPreviewContent)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    container.textContent = data.preview;
                } else {
                    container.textContent = currentPreviewContent;
                }
            })
            .catch(() => {
                container.textContent = currentPreviewContent;
            });
        });
    });

    // 5. Setup Modal Uji Kirim Pesan Spesifik
    let activeTestContent = '';
    let activeTestSeq = 1;
    let activeTestEvent = '';
    document.querySelectorAll('.btn-test-message').forEach(btn => {
        btn.addEventListener('click', function() {
            activeTestContent = this.getAttribute('data-content') || '';
            activeTestSeq = this.getAttribute('data-sequence') || 1;
            activeTestEvent = this.getAttribute('data-event-key') || '';
            const statusDiv = document.getElementById('testSendStatus');
            statusDiv.style.display = 'none';
        });
    });

    const btnExecTest = document.getElementById('btnExecuteTestSend');
    if (btnExecTest) {
        btnExecTest.addEventListener('click', function() {
            const phone = document.getElementById('testTargetPhone').value.trim();
            if (!phone) {
                alert('Silakan masukkan nomor WhatsApp tujuan.');
                return;
            }

            btnExecTest.disabled = true;
            btnExecTest.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...';
            const statusDiv = document.getElementById('testSendStatus');
            statusDiv.style.display = 'none';

            fetch('<?= url('platform/whatsapp-settings/messages/test-send') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_csrf_token=<?= $csrfToken ?>&phone=' + encodeURIComponent(phone) +
                      '&template_content=' + encodeURIComponent(activeTestContent) +
                      '&message_sequence=' + encodeURIComponent(activeTestSeq) +
                      '&event_key=' + encodeURIComponent(activeTestEvent)
            })
            .then(res => res.json())
            .then(data => {
                btnExecTest.disabled = false;
                btnExecTest.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Sekarang';
                statusDiv.style.display = 'block';
                if (data.success) {
                    statusDiv.className = 'alert alert-success py-2 px-3 small rounded-3 mb-0';
                    statusDiv.innerHTML = '<i class="bi bi-check-circle me-1"></i> ' + (data.message || 'Pesan uji coba terkirim!');
                } else {
                    statusDiv.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-0';
                    statusDiv.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> ' + (data.message || 'Gagal mengirim pesan uji coba.');
                }
            })
            .catch(err => {
                btnExecTest.disabled = false;
                btnExecTest.innerHTML = '<i class="bi bi-send me-1"></i> Kirim Sekarang';
                statusDiv.style.display = 'block';
                statusDiv.className = 'alert alert-danger py-2 px-3 small rounded-3 mb-0';
                statusDiv.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Gangguan: ' + err.message;
            });
        });
    }
});
</script>
