<div class="container py-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-extrabold text-dark mb-1">Cari Toko Laundry</h2>
            <p class="text-muted">Pilih toko laundry terbaik di sekitar lokasi penjemputan Anda.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                <i class="bi bi-info-circle me-1"></i> Data toko lengkap akan aktif di Tahap 6-8
            </span>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <div class="row g-3">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 rounded-end-pill" placeholder="Cari nama toko atau lokasi...">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select rounded-pill">
                    <option selected>Semua Jenis Layanan</option>
                    <option value="kiloan">Laundry Kiloan</option>
                    <option value="express">Express</option>
                    <option value="satuan">Satuan (Bed Cover / Jas)</option>
                    <option value="sepatu">Sepatu & Tas</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100 rounded-pill fw-semibold">
                    <i class="bi bi-funnel me-1"></i> Terapkan Filter
                </button>
            </div>
        </div>
    </div>

    <!-- Dynamic Store Cards -->
    <div class="row g-4">
        <?php if (!empty($stores)): ?>
            <?php foreach ($stores as $st): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100 hover-elevate transition bg-white">
                        <div class="bg-primary-subtle p-4 text-center">
                            <i class="bi bi-shop fs-1 text-primary"></i>
                        </div>
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold text-dark mb-0"><?= e($st['name']) ?></h5>
                                <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">
                                    <i class="bi bi-star-fill text-warning me-1"></i> <?= number_format((float)($st['rating_avg'] ?? 5.0), 1) ?>
                                </span>
                            </div>
                            <p class="text-muted small mb-3"><i class="bi bi-clock me-1"></i> <?= e($st['operational_hours'] ?? 'Buka 08:00 - 20:00 WIB') ?> • <i class="bi bi-telephone me-1"></i> <?= e($st['phone']) ?></p>
                            
                            <div class="d-flex flex-wrap gap-1 mb-3 flex-grow-1">
                                <?php if (!empty($st['services'])): ?>
                                    <?php foreach (array_slice($st['services'], 0, 3) as $srv): ?>
                                        <span class="badge bg-light text-dark border rounded-pill"><?= e($srv['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border rounded-pill">Layanan Lengkap</span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center pt-3 border-top border-light-subtle mt-auto">
                                <div>
                                    <small class="text-muted">Tarif mulai</small>
                                    <div class="fw-bold text-primary">
                                        <?= !empty($st['services']) ? format_rupiah($st['services'][0]['price']) . ' /' . $st['services'][0]['unit'] : 'Tarif Terjangkau' ?>
                                    </div>
                                </div>
                                <?php if (is_authenticated() && user_role() === 'customer'): ?>
                                    <a href="<?= url('order/create?store_id=' . $st['id']) ?>" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                                        <i class="bi bi-cart-plus me-1"></i> Pesan
                                    </a>
                                <?php else: ?>
                                    <a href="<?= url('order/create?store_id=' . $st['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-4">
                                        <i class="bi bi-cart me-1"></i> Pesan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
