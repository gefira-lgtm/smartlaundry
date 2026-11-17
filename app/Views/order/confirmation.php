<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Celebration Header Card -->
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white mb-4">
                <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center p-4 mx-auto mb-3 shadow-sm" style="width: 88px; height: 88px;">
                    <i class="bi bi-check-circle-fill fs-1"></i>
                </div>
                <div class="badge bg-success-subtle text-success rounded-pill px-3 py-1 mb-2 fw-bold fs-7">
                    Pesanan Berhasil Dibuat
                </div>
                <h2 class="fw-extrabold text-dark mb-2">Terima Kasih, <?= e($user['name']) ?>!</h2>
                <p class="text-muted leading-relaxed mb-4">
                    Pesanan Anda telah kami teruskan ke pihak toko laundry mitra. Kurir akan ditugaskan untuk menjemput pakaian kotor sesuai jadwal yang telah ditentukan.
                </p>

                <!-- Order Code Badge & Details -->
                <div class="p-3 bg-light rounded-4 border d-inline-flex flex-wrap align-items-center justify-content-center gap-3 mb-3">
                    <span class="text-muted small">Nomor Pesanan:</span>
                    <strong class="font-monospace text-primary fs-5">#<?= e($order['order_code']) ?></strong>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-semibold">
                        <?= str_replace('_', ' ', $order['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Order Details Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-receipt text-primary me-2"></i>Rincian Pesanan Laundry</h5>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <small class="text-muted d-block mb-1">Toko Laundry Mitra:</small>
                            <strong class="text-dark fs-6 d-block"><?= e($order['store_name']) ?></strong>
                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($order['store_phone']) ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100">
                            <small class="text-muted d-block mb-1">Jadwal Penjemputan:</small>
                            <strong class="text-dark fs-6 d-block"><?= date('d F Y', strtotime($order['scheduled_pickup_at'] ?? $order['created_at'])) ?></strong>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($order['scheduled_pickup_at'] ?? $order['created_at'])) ?> WIB</small>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <h6 class="fw-bold text-dark mb-2 fs-7 text-uppercase letter-spacing-1">Layanan yang Dipesan:</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-8 text-muted">
                            <tr>
                                <th>Nama Layanan</th>
                                <th class="text-center">Kategori</th>
                                <th class="text-center">Estimasi Qty</th>
                                <th class="text-end">Tarif Satuan</th>
                                <th class="text-end">Estimasi Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($order['items'])): ?>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-dark"><?= e($item['service_name']) ?></strong>
                                            <?php if (!empty($item['item_notes'])): ?>
                                                <small class="text-muted d-block"><?= e($item['item_notes']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border text-uppercase fs-8"><?= e($item['category'] ?? 'kiloan') ?></span>
                                        </td>
                                        <td class="text-center fw-semibold">
                                            <?= (float)$item['quantity'] ?> <?= e($item['unit'] ?? 'kg') ?>
                                        </td>
                                        <td class="text-end text-muted small">
                                            <?= format_rupiah($item['unit_price']) ?>
                                        </td>
                                        <td class="text-end fw-bold text-dark">
                                            <?= format_rupiah($item['subtotal']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <th colspan="4" class="text-end py-2">Estimasi Total Tagihan:</th>
                                <th class="text-end text-primary fs-6 py-2"><?= format_rupiah($order['total_price']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Pickup Address Details -->
                <div class="p-3 bg-light rounded-4 mb-4">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-geo-alt-fill text-danger"></i>
                        <strong class="text-dark">Lokasi Penjemputan Kurir:</strong>
                    </div>
                    <p class="small text-muted mb-0"><?= e($order['pickup_address_full']) ?></p>
                </div>

                <!-- Next Steps Visual Guide -->
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-arrow-right-circle text-primary me-2"></i>Tahapan Selanjutnya:</h6>
                <div class="row g-2 text-center small mb-4">
                    <div class="col-4">
                        <div class="p-3 bg-primary-subtle text-primary rounded-4 h-100">
                            <i class="bi bi-shop fs-3 d-block mb-1"></i>
                            <strong class="d-block">1. Konfirmasi Toko</strong>
                            <span class="fs-8 opacity-75">Toko menyetujui pesanan</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-light text-dark rounded-4 h-100 border">
                            <i class="bi bi-bicycle fs-3 text-secondary d-block mb-1"></i>
                            <strong class="d-block">2. Kurir Menjemput</strong>
                            <span class="fs-8 text-muted">Tracking live posisi kurir</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-light text-dark rounded-4 h-100 border">
                            <i class="bi bi-speedometer2 fs-3 text-secondary d-block mb-1"></i>
                            <strong class="d-block">3. Penimbangan Ril</strong>
                            <span class="fs-8 text-muted">QRIS terbit otomatis (1 jam)</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                    <a href="<?= url('stores') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                        <i class="bi bi-shop me-1"></i> Pesan Toko Lain
                    </a>
                    <a href="<?= url('dashboard/customer') ?>" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-speedometer2 me-1"></i> Lihat Pesanan di Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
