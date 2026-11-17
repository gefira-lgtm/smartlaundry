<?php
/**
 * WhatsApp Notification Settings — Store Owner View (2 Tabs, Multi-Message Support)
 * Tabs: Transaksi & Aktivitas (OTP tab hidden — Platform only)
 * Anti-IDOR: Hanya menampilkan data toko yang dimiliki owner yang login.
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
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengaturan Notifikasi WhatsApp</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-whatsapp text-success"></i> Notifikasi WhatsApp
                <?php if (!empty($store)): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 fw-semibold ms-1"><?= e($store['name']) ?></span>
                <?php endif; ?>
            </h1>
            <p class="text-muted small mb-0">Kelola pesan WhatsApp terpisah (multi-chat) untuk transaksi pesanan dan aktivitas outlet toko Anda.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('store/whatsapp-settings?tab=' . $activeTab) ?>" class="btn btn-outline-primary rounded-pill px-3 btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </a>
            <a href="<?= url('dashboard/store') ?>" class="btn btn-secondary rounded-pill px-3 btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Toko
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
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

        <div class="col-6 col-md-4">
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

        <div class="col-6 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="<?= ($trxMaster && $actMaster) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?> p-3 rounded-circle">
                        <i class="bi bi-bell-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Status Notifikasi Toko</div>
                        <h4 class="fw-bold mb-0 fs-6">
                            Transaksi: <span class="<?= $trxMaster ? 'text-success' : 'text-danger' ?>"><?= $trxMaster ? 'Aktif' : 'Nonaktif' ?></span>
                        </h4>
                        <span class="text-muted fs-8">Aktivitas: <span class="<?= $actMaster ? 'text-success' : 'text-danger' ?>"><?= $actMaster ? 'Aktif' : 'Nonaktif' ?></span></span>
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
            <form action="<?= url('store/whatsapp-settings/override') ?>" method="POST">
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

    <!-- 2 MAIN TABS (Transaksi + Aktivitas — Tanpa Tab OTP) -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden mb-4">
        <div class="card-header bg-light border-bottom p-0">
            <ul class="nav nav-tabs nav-fill card-header-tabs m-0 border-0 fs-6 fw-bold">
                <li class="nav-item">
                    <a class="nav-link py-3 border-0 <?= $activeTab === 'transaction' ? 'active bg-white text-primary border-top border-top-3 border-primary' : 'text-muted' ?>"
                       href="<?= url('store/whatsapp-settings?tab=' . $activeTab) ?>">
                        <i class="bi bi-receipt me-2"></i> 1. Transaksi Masuk WA
                        <?php if ($trxMaster): ?>
                            <span class="badge bg-success ms-2">Aktif</span>
                        <?php else: ?>
                            <span class="badge bg-danger ms-2">Nonaktif</span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-3 border-0 <?= $activeTab === 'activity' ? 'active bg-white text-primary border-top border-top-3 border-primary' : 'text-muted' ?>"
                       href="<?= url('store/whatsapp-settings?tab=activity') ?>">
                        <i class="bi bi-activity me-2"></i> 2. Aktivitas Masuk WA
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

            <!-- TAB 1: TRANSAKSI (MULTI-MESSAGE PER EVENT) -->
            <?php if ($activeTab === 'transaction'): ?>
                <div class="mb-4">
                    <div class="mb-3">
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-receipt text-primary me-2"></i>Pengaturan Notifikasi Transaksi (Multi-Pesan)</h5>
                        <p class="text-muted small mb-0">
                            Kelola pesan WhatsApp bertahap untuk toko <strong><?= e($store['name'] ?? 'Anda') ?></strong>.
                            Sistem dapat mengirim lebih dari satu chat terpisah per status.
                        </p>
                    </div>

                    <form action="<?= url('store/whatsapp-settings/update') ?>" method="POST">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="tab" value="transaction">

                        <div class="card border rounded-3 p-3 bg-light mb-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="masterTrxSwitch" name="master_enabled" value="1" <?= $trxMaster ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark" for="masterTrxSwitch">Aktifkan Notifikasi Transaksi Toko Ini</label>
                            </div>
                            <div class="small text-muted">Jika dinonaktifkan, seluruh notifikasi transaksi pesanan customer dari toko ini akan diblokir.</div>
                        </div>

                        <!-- Accordion Event Transaksi -->
                        <div class="card border rounded-3 p-3 mb-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-check text-primary me-1"></i> Event Notifikasi Status Pesanan Customer</h6>
                            <?php
                            $storeId = (int)($store['id'] ?? 0);
                            $custEvents = [
                                'customer_order_created'       => '1. Pesanan Dibuat (Diterima toko)',
                                'customer_courier_to_pickup'   => '2. Kurir Menjemput: Penugasan & Perjalanan Kurir (2 Pesan Terpisah)',
                                'customer_laundry_picked'      => '3. Laundry Diambil Kurir (Menuju outlet)',
                                'customer_arrived_at_store'    => '4. Laundry Diterima Toko (Sedang ditimbang)',
                                'customer_weighing_completed'  => '5. Penimbangan Selesai (Tagihan siap)',
                                'customer_ready_to_pay_qris'   => 'Tagihan QRIS Dikirim (QRIS Dinamis 1 Jam)',
                                'customer_ready_to_pay_cash'   => 'Tagihan CASH Dikirim (Bayar Tunai)',
                                'customer_payment_success'     => 'Pembayaran Berhasil Diterima (LUNAS)',
                                'customer_payment_expired'     => 'Pembayaran Kedaluwarsa',
                                'customer_laundry_washing'     => '6. Sedang Diproses / Dicuci',
                                'customer_laundry_ready'       => '7. Laundry Selesai Dicuci',
                                'customer_courier_to_delivery' => '8. Kurir Mengantar ke Customer',
                                'customer_order_completed'     => '9. Pesanan Selesai Diterima',
                            ];
                            foreach ($custEvents as $evKey => $evLabel):
                                $isEnabled = !isset($trxSettings[$evKey]) || (bool)$trxSettings[$evKey]['is_enabled'];
                                $messages = $templateModel->getEventMessages('TRANSACTION', $evKey, $storeId);
                            ?>
                                <div class="border rounded-3 p-3 mb-3 bg-white">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <div>
                                            <span class="fw-bold text-dark small"><?= e($evLabel) ?></span>
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
                                                <i class="bi bi-plus-circle me-1"></i> Tambah
                                            </button>
                                        </div>
                                    </div>

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
                                                            <form action="<?= url('store/whatsapp-settings/messages/' . $msg['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus pesan ini?')">
                                                                <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fs-8"><i class="bi bi-trash"></i></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="small font-monospace text-muted bg-white p-2 rounded border" style="white-space: pre-wrap; font-size: 0.78rem; max-height: 80px; overflow-y: auto;"><?= e($msg['template_content']) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold mb-4">
                            <i class="bi bi-save me-1"></i> Simpan Status Master Event Transaksi
                        </button>
                    </form>

                    <!-- Recipient Management (Transaction scope, store-level) -->
                    <div class="card border rounded-3 p-3 bg-light mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-people text-primary me-1"></i> Nomor Penerima Tambahan Transaksi Toko</h6>
                                <div class="text-muted small">Nomor WhatsApp staf outlet yang ikut menerima salinan notifikasi transaksi pesanan.</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalAddTrxRecipient">
                                <i class="bi bi-plus-circle me-1"></i> Tambah
                            </button>
                        </div>

                        <?php if (empty($trxRecipients)): ?>
                            <div class="text-muted small py-2">Belum ada nomor penerima tambahan untuk transaksi toko ini.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white mb-0 align-middle">
                                    <thead>
                                        <tr class="table-light">
                                            <th>Nama</th>
                                            <th>Nomor WA</th>
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
                                                    <form action="<?= url('store/whatsapp-settings/recipients/' . $rec['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus penerima ini?')">
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

            <!-- TAB 2: AKTIVITAS (MULTI-MESSAGE PER EVENT) -->
            <?php if ($activeTab === 'activity'): ?>
                <div class="mb-4">
                    <div class="mb-3">
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-activity text-primary me-2"></i>Pengaturan Notifikasi Aktivitas Toko (Multi-Pesan)</h5>
                        <p class="text-muted small mb-0">
                            Pemberitahuan perubahan operasional penting pada toko <strong><?= e($store['name'] ?? 'Anda') ?></strong>.
                        </p>
                    </div>

                    <form action="<?= url('store/whatsapp-settings/update') ?>" method="POST">
                        <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="tab" value="activity">

                        <div class="card border rounded-3 p-3 bg-light mb-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input" type="checkbox" role="switch" id="masterActSwitch" name="master_enabled" value="1" <?= $actMaster ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold text-dark" for="masterActSwitch">Aktifkan Notifikasi Aktivitas untuk Toko Ini</label>
                            </div>
                            <div class="small text-muted">Mendapat peringatan WhatsApp setiap ada perubahan penting pada data toko Anda.</div>
                        </div>

                        <div class="card border rounded-3 p-3 mb-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bell-fill text-warning me-1"></i> Event Aktivitas yang Dipantau</h6>
                            <?php
                            $storeId = (int)($store['id'] ?? 0);
                            $actEvents = [
                                'activity_service_added'    => 'Penambahan Layanan Laundry Baru',
                                'activity_price_changed'    => 'Perubahan Harga Layanan (Harga lama → Harga baru)',
                                'activity_store_updated'    => 'Pembaruan Profil & Data Outlet',
                                'activity_courier_changed'  => 'Penambahan atau Perubahan Data Kurir',
                            ];
                            foreach ($actEvents as $evKey => $evLabel):
                                $isEnabled = !isset($actSettings[$evKey]) || (bool)$actSettings[$evKey]['is_enabled'];
                                $messages = $templateModel->getEventMessages('ACTIVITY', $evKey, $storeId);
                            ?>
                                <div class="border rounded-3 p-3 mb-3 bg-white">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <div>
                                            <span class="fw-bold text-dark small"><?= e($evLabel) ?></span>
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
                                                            <form action="<?= url('store/whatsapp-settings/messages/' . $msg['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus pesan ini?')">
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

                    <!-- Recipient Management (Activity scope, store-level) -->
                    <div class="card border rounded-3 p-3 bg-light mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-bell-fill text-primary me-1"></i> Nomor Penerima Alert Aktivitas Toko</h6>
                                <div class="text-muted small">Nomor WhatsApp yang mendapat notifikasi setiap ada perubahan aktivitas penting di toko.</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#modalAddActRecipient">
                                <i class="bi bi-plus-circle me-1"></i> Tambah
                            </button>
                        </div>

                        <?php if (empty($actRecipients)): ?>
                            <div class="text-muted small py-2">Belum ada nomor penerima alert aktivitas untuk toko ini.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white mb-0 align-middle">
                                    <thead>
                                        <tr class="table-light">
                                            <th>Nama</th>
                                            <th>Nomor WA</th>
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
                                                    <form action="<?= url('store/whatsapp-settings/recipients/' . $rec['id'] . '/delete') ?>" method="POST" class="d-inline" onsubmit="return confirm('Hapus penerima ini?')">
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

            <!-- OUTBOX RIWAYAT PENGIRIMAN (scoped ke store_id, tanpa OTP) -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-clock-history me-1"></i> Riwayat Pengiriman Notifikasi (<?= strtoupper($activeTab) ?>)
                    </h5>
                    <p class="text-muted small mb-0">Log notifikasi WhatsApp yang dikirimkan dari outlet toko Anda.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <form method="GET" action="<?= url('store/whatsapp-settings') ?>" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                        <select name="status" class="form-select form-select-sm" style="width: 140px;" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="SENT" <?= $statusFilter === 'SENT' ? 'selected' : '' ?>>TERKIRIM</option>
                            <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>GAGAL</option>
                            <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                            <option value="SIMULATED" <?= $statusFilter === 'SIMULATED' ? 'selected' : '' ?>>SIMULASI</option>
                        </select>
                        <div class="input-group input-group-sm" style="width: 200px;">
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
                                    Belum ada log pengiriman untuk kategori ini dari toko Anda.
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
                                        <span class="d-inline-block text-truncate" style="max-width: 360px;" title="<?= e($log['message']) ?>">
                                            <?= e($log['message']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $st = $log['status'];
                                        $badgeClass = match ($st) {
                                            'SENT'      => 'bg-success',
                                            'FAILED'    => 'bg-danger',
                                            'PENDING'   => 'bg-warning text-dark',
                                            'SIMULATED' => 'bg-info text-dark',
                                            default     => 'bg-secondary'
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

<!-- MODAL TAMBAH PESAN (STORE OWNER) -->
<div class="modal fade" id="modalAddMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= url('store/whatsapp-settings/messages/add') ?>" method="POST">
            <input type="hidden" name="_csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="category" id="addMsgCategory" value="TRANSACTION">
            <input type="hidden" name="event_key" id="addMsgEventKey" value="">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="bi bi-chat-plus text-primary me-2"></i>Tambah Pesan Terpisah untuk Toko</h5>
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
                        <textarea name="template_content" id="addMsgContent" class="form-control font-monospace fs-8" rows="6" placeholder="Ketik isi template chat WhatsApp..." required></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="addMsgEnabled" checked>
                        <label class="form-check-label small fw-semibold" for="addMsgEnabled">Aktifkan pesan ini dalam alur pengiriman</label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Simpan Pesan Toko</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT PESAN (STORE OWNER) -->
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
                        <div class="small fw-bold text-success mb-1"><?= e($store['name'] ?? 'SmartLaundry') ?></div>
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

<!-- MODAL UJI KIRIM PESAN -->
<div class="modal fade" id="modalTestMessage" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-send text-success me-2"></i>Uji Kirim Pesan Ini</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Kirim satu chat pesan ini ke nomor WhatsApp Anda untuk menguji format tampilan.</p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nomor WhatsApp Tujuan</label>
                    <input type="text" id="testTargetPhone" class="form-control" value="<?= e($user['phone'] ?? '081234567890') ?>" placeholder="08xxxxxxxxxx">
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

<!-- MODAL TAMBAH PENERIMA TRANSAKSI TOKO -->
<div class="modal fade" id="modalAddTrxRecipient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= url('store/whatsapp-settings/recipients/add') ?>" method="POST">
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
                        <input type="text" name="recipient_name" class="form-control" placeholder="Contoh: Admin Toko / Staf CS" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor WhatsApp</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="Contoh: 08123456789 atau +62812..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Peran / Label</label>
                        <input type="text" name="role" class="form-control" value="ADMIN_TOKO" required>
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

<!-- MODAL TAMBAH PENERIMA AKTIVITAS TOKO -->
<div class="modal fade" id="modalAddActRecipient" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= url('store/whatsapp-settings/recipients/add') ?>" method="POST">
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
                        <input type="text" name="recipient_name" class="form-control" placeholder="Contoh: Pemilik Toko / Supervisor" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor WhatsApp</label>
                        <input type="text" name="phone_number" class="form-control" placeholder="Contoh: 08123456789" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Peran / Role</label>
                        <input type="text" name="role" class="form-control" value="OWNER_TOKO" required>
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

<!-- SCRIPT PENGELOLAAN STORE MULTI-MESSAGE -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup Modal Tambah Pesan
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

    // Setup Modal Edit Pesan
    document.querySelectorAll('.btn-edit-message').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id') || '';
            const title = this.getAttribute('data-title') || '';
            const seq = this.getAttribute('data-sequence') || '1';
            const content = this.getAttribute('data-content') || '';
            const enabled = this.getAttribute('data-enabled') === '1';

            const form = document.getElementById('formEditMessage');
            if (id) {
                form.action = '<?= url('store/whatsapp-settings/messages/') ?>' + id + '/update';
            } else {
                form.action = '<?= url('store/whatsapp-settings/messages/add') ?>';
            }

            document.getElementById('editMsgTitle').value = title;
            document.getElementById('editMsgSequence').value = seq;
            document.getElementById('editMsgContent').value = content;
            document.getElementById('editMsgEnabled').checked = enabled;
        });
    });

    // Setup Modal Preview
    let currentPreviewContent = '';
    document.querySelectorAll('.btn-preview-message').forEach(btn => {
        btn.addEventListener('click', function() {
            currentPreviewContent = this.getAttribute('data-content') || '';
            const container = document.getElementById('previewMessageContent');
            container.textContent = 'Memuat preview...';

            fetch('<?= url('store/whatsapp-settings/messages/preview') ?>', {
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

    // Setup Modal Uji Kirim
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

            fetch('<?= url('store/whatsapp-settings/messages/test-send') ?>', {
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
