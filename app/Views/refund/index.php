<div class="container py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengembalian Laundry</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0">Kelola Pengembalian Laundry</h3>
            <p class="text-muted small mb-0">Daftar pesanan dengan pembayaran kadaluarsa & proses pengembalian pakaian</p>
        </div>
        <div>
            <a href="<?= url('dashboard/store') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- 1. Antrean Menunggu Persetujuan Pengembalian (PEMBAYARAN_KADALUARSA) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Menunggu Persetujuan Pengembalian (<?= count($expiredOrders) ?>)
            </h5>
            <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">Status: PEMBAYARAN_KADALUARSA</span>
        </div>
        <div class="card-body px-4 pb-4">
            <?php if (empty($expiredOrders)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-check-circle-fill text-success fs-1 mb-2 d-block"></i>
                    <p class="mb-0">Tidak ada pesanan pembayaran kadaluarsa yang membutuhkan tindakan saat ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order</th>
                                <th>Pelanggan</th>
                                <th>Alamat Tujuan</th>
                                <th>Penimbangan</th>
                                <th>Waktu Expired</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiredOrders as $exp): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary">#<?= e($exp['order_code']) ?></span>
                                        <small class="d-block text-muted"><?= e($exp['service_names'] ?? 'Laundry') ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= e($exp['customer_name']) ?></span>
                                        <small class="d-block text-muted"><?= e($exp['customer_phone'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted d-inline-block text-truncate" style="max-width: 200px;">
                                            <?= e($exp['customer_address']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= !empty($exp['weight_actual']) ? number_format($exp['weight_actual'], 2) . ' kg' : '-' ?> (<?= (int)($exp['pieces_count'] ?? 0) ?> pcs)
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-danger fw-semibold">
                                            <?= !empty($exp['expired_at']) ? date('d M Y, H:i', strtotime($exp['expired_at'])) : '-' ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('store/orders/' . $exp['id'] . '/refund-approval') ?>" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark shadow-sm">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Proses Pengembalian
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Sedang Dalam Pengembalian (PROSES_PENGEMBALIAN) -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-warning">
                <i class="bi bi-truck me-2"></i>Sedang Dalam Proses Pengantaran Balik (<?= count($refundsQueue) ?>)
            </h5>
            <span class="badge bg-warning-subtle text-warning px-3 py-1 rounded-pill">Status: PROSES_PENGEMBALIAN</span>
        </div>
        <div class="card-body px-4 pb-4">
            <?php if (empty($refundsQueue)): ?>
                <div class="text-center py-4 text-muted">
                    <p class="mb-0">Tidak ada pengembalian yang sedang berjalan di jalan.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order</th>
                                <th>Pelanggan</th>
                                <th>Alamat Pengembalian</th>
                                <th>Kurir Penanggung Jawab</th>
                                <th>Biaya Pengembalian</th>
                                <th class="text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($refundsQueue as $rf): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary">#<?= e($rf['order_code']) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= e($rf['customer_name']) ?></span>
                                        <small class="d-block text-muted"><?= e($rf['customer_phone'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted d-inline-block text-truncate" style="max-width: 220px;">
                                            <?= e($rf['customer_address']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if (!empty($rf['courier_name'])): ?>
                                            <span class="fw-semibold"><i class="bi bi-person-badge text-primary me-1"></i><?= e($rf['courier_name']) ?></span>
                                            <small class="d-block text-muted"><?= e($rf['courier_phone'] ?? '') ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Belum Ditugaskan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold"><?= format_rupiah($rf['refund_fee'] ?? 0) ?></span>
                                        <small class="d-block text-muted text-uppercase">(<?= e($rf['refund_fee_bearer'] ?? 'free') ?>)</small>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                                            <i class="bi bi-hourglass-split me-1"></i> Dalam Pengantaran Balik
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
