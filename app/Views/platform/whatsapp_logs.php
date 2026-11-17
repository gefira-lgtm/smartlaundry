<?php
/**
 * WhatsApp Gateway Monitoring & Outbox Log Viewer
 * Available for Owner Platform & Admin Owner
 */
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/platform') ?>" class="text-decoration-none">Dashboard Platform</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Log & Gateway WhatsApp</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-whatsapp text-success"></i> Pemantauan WhatsApp Gateway
            </h1>
            <p class="text-muted small mb-0">Status integrasi real Fonnte, monitoring outbox, dan riwayat notifikasi keuangan.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($user['role'] === 'owner_platform'): ?>
                <button type="button" class="btn btn-primary rounded-pill px-3 btn-sm" id="btnTestSend">
                    <i class="bi bi-send me-1"></i> Kirim Pesan Tes
                </button>
            <?php endif; ?>
            <a href="<?= url('platform/whatsapp-logs') ?>" class="btn btn-outline-primary rounded-pill px-3 btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </a>
            <a href="<?= url('dashboard/platform') ?>" class="btn btn-secondary rounded-pill px-3 btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard Platform
            </a>
        </div>
    </div>

    <!-- Alert / Warning Banner -->
    <?php if ($showWarningBanner): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 mb-4 p-3">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
            <div>
                <div class="fw-bold">Peringatan Integrasi WhatsApp</div>
                <div class="small"><?= e($warningMessage) ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Status & Stats Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Gateway Type -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="bi bi-hdd-network fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Driver Gateway</div>
                        <h4 class="fw-bold mb-0 text-dark fs-6 text-uppercase"><?= e($gatewayType) ?></h4>
                        <span class="badge <?= $hasToken ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> fs-8 mt-1">
                            <?= $hasToken ? 'Token Terkonfigurasi' : 'Token Kosong' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fonnte Device Status -->
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
                            <span class="small text-muted fs-8">Offline Test</span>
                        <?php elseif (!$hasToken): ?>
                            <h4 class="fw-bold mb-0 text-danger fs-6">Belum Ada Token</h4>
                            <span class="small text-muted fs-8">Isi FONNTE_TOKEN</span>
                        <?php elseif ($deviceInfo['connected']): ?>
                            <h4 class="fw-bold mb-0 text-success fs-6"><?= e(strtoupper($deviceInfo['status'] ?? 'CONNECTED')) ?></h4>
                            <span class="small text-muted fs-8"><?= e($deviceInfo['device'] ?? 'Terhubung') ?></span>
                        <?php else: ?>
                            <h4 class="fw-bold mb-0 text-danger fs-6"><?= e(strtoupper($deviceInfo['status'] ?? 'DISCONNECTED')) ?></h4>
                            <span class="small text-muted fs-8"><?= e(substr($deviceInfo['detail'] ?? '', 0, 25)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- SENT Today -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-success-subtle text-success p-3 rounded-circle">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Terkirim Hari Ini</div>
                        <h3 class="fw-bold mb-0 text-success"><?= (int)($todayStats['sent_today'] ?? 0) ?></h3>
                        <span class="small text-muted fs-8"><?= (int)($todayStats['total_today'] ?? 0) ?> total antrean</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAILED Today -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3 h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-danger-subtle text-danger p-3 rounded-circle">
                        <i class="bi bi-x-circle fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Gagal Hari Ini</div>
                        <h3 class="fw-bold mb-0 text-danger"><?= (int)($todayStats['failed_today'] ?? 0) ?></h3>
                        <span class="small text-muted fs-8">Maks 3x retry cron</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Failures Section (if any) -->
    <?php if (!empty($recentFailures)): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden border-start border-danger border-4">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-octagon-fill"></i> 10 Kegagalan Terbaru
                </h5>
                <span class="badge bg-danger rounded-pill"><?= count($recentFailures) ?> Gagal</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="fs-7 text-uppercase text-muted">
                            <th class="ps-4">Waktu</th>
                            <th>Penerima</th>
                            <th>Event</th>
                            <th>Alasan Kegagalan (Last Error)</th>
                            <th class="pe-4 text-center">Percobaan</th>
                        </tr>
                    </thead>
                    <tbody class="fs-7">
                        <?php foreach ($recentFailures as $fail): ?>
                            <tr>
                                <td class="ps-4 text-muted whitespace-nowrap">
                                    <?= date('d M Y H:i', strtotime($fail['sent_at'])) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($fail['recipient_name'] ?: 'User') ?></div>
                                    <div class="small text-muted"><?= e($fail['phone']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-dark border px-2 py-1">
                                        <?= e($fail['event']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-danger small font-monospace">
                                        <?= e($fail['last_error'] ?: 'Gagal terkirim') ?>
                                    </span>
                                </td>
                                <td class="pe-4 text-center">
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                        <?= (int)$fail['attempts'] ?> / 3
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Tabs / Cards -->
    <div class="row g-4">
        <!-- Database Logs Table -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-database-check text-primary"></i> Riwayat Pesan Outbox (50 Terakhir)
                    </h5>
                    <span class="badge bg-primary rounded-pill"><?= count($logs) ?> Pesan</span>
                </div>
                <div class="table-responsive" style="max-height: 540px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr class="fs-7 text-uppercase text-muted">
                                <th class="ps-4">Penerima</th>
                                <th>Event</th>
                                <th>Isi Pesan</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="fs-7">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada riwayat pengiriman notifikasi WhatsApp tersimpan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php 
                                        $statusClass = 'bg-secondary-subtle text-dark';
                                        if ($log['status'] === 'SENT') {
                                            $statusClass = 'bg-success-subtle text-success border-success-subtle';
                                        } elseif ($log['status'] === 'FAILED') {
                                            $statusClass = 'bg-danger-subtle text-danger border-danger-subtle';
                                        } elseif ($log['status'] === 'PENDING') {
                                            $statusClass = 'bg-warning-subtle text-warning border-warning-subtle';
                                        } elseif ($log['status'] === 'SKIPPED_NOT_ALLOWED') {
                                            $statusClass = 'bg-info-subtle text-info border-info-subtle';
                                        }
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= e($log['recipient_name'] ?? 'Pelanggan') ?></div>
                                            <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= e($log['phone_number']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-dark border px-2 py-1">
                                                <?= e($log['event_type']) ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <div class="text-truncate small text-secondary" title="<?= e($log['message']) ?>">
                                                <?= e($log['message']) ?>
                                            </div>
                                            <?php if (!empty($log['provider_message_id'])): ?>
                                                <div class="fs-8 text-muted font-monospace mt-1">ID: <?= e($log['provider_message_id']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge border px-2 py-1 <?= $statusClass ?>">
                                                <?= e($log['status']) ?>
                                            </span>
                                        </td>
                                        <td class="pe-4 text-end text-muted small whitespace-nowrap">
                                            <?= date('d M Y H:i', strtotime($log['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Raw Log File Stream -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 bg-dark text-light">
                <div class="card-header bg-black bg-opacity-25 py-3 px-4 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-terminal text-success"></i> File Log Simulasi
                    </h6>
                    <span class="badge bg-dark border border-secondary text-info fs-8">storage/logs</span>
                </div>
                <div class="card-body p-3 font-monospace small" style="max-height: 540px; overflow-y: auto; background: #121820;">
                    <?php if (empty($rawLogs)): ?>
                        <div class="text-muted text-center py-5">
                            <i class="bi bi-file-earmark-code fs-3 d-block mb-2"></i>
                            Log file simulasi masih kosong.
                        </div>
                    <?php else: ?>
                        <div class="text-success-emphasis mb-2 fs-8 text-uppercase border-bottom border-secondary border-opacity-25 pb-1">
                            --- Baris Terakhir Log Simulasi ---
                        </div>
                        <pre class="mb-0 text-light fs-8" style="white-space: pre-wrap; word-break: break-all;"><?= e($rawLogs) ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hasil Kirim Tes -->
<div class="modal fade" id="testSendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="testSendModalTitle">Kirim Pesan Tes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4" id="testSendModalBody">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <p class="text-muted mb-0">Sedang mengirim pesan tes ke WhatsApp...</p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnTest = document.getElementById('btnTestSend');
    if (!btnTest) return;

    btnTest.addEventListener('click', function() {
        if (!confirm('Kirim pesan tes WhatsApp ke nomor Anda sekarang? (Batas: 3 kali per jam)')) {
            return;
        }

        const modalEl = document.getElementById('testSendModal');
        const modal = new bootstrap.Modal(modalEl);
        const modalBody = document.getElementById('testSendModalBody');
        
        modalBody.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="text-muted mb-0">Sedang mengirim pesan tes ke WhatsApp Gateway...</p>
            </div>
        `;
        modal.show();

        const csrfToken = '<?= csrf_token() ?>';

        fetch('<?= url("platform/whatsapp-logs/test-send") ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: '_csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                modalBody.innerHTML = `
                    <div class="text-center text-success py-2">
                        <i class="bi bi-check-circle-fill fs-1 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">Berhasil Terkirim!</h6>
                        <p class="text-muted small mb-2">${data.message}</p>
                        ${data.provider_message_id ? `<div class="badge bg-light text-dark border font-monospace">Provider ID: ${data.provider_message_id}</div>` : ''}
                    </div>
                `;
            } else {
                modalBody.innerHTML = `
                    <div class="text-center text-danger py-2">
                        <i class="bi bi-x-circle-fill fs-1 d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">Pengiriman Gagal</h6>
                        <p class="text-muted small mb-0">${data.message || 'Terjadi kesalahan'}</p>
                    </div>
                `;
            }
        })
        .catch(err => {
            modalBody.innerHTML = `
                <div class="text-center text-danger py-2">
                    <i class="bi bi-exclamation-octagon-fill fs-1 d-block mb-2"></i>
                    <h6 class="fw-bold mb-1">Kesalahan Jaringan</h6>
                    <p class="text-muted small mb-0">${err.message}</p>
                </div>
            `;
        });
    });
});
</script>
