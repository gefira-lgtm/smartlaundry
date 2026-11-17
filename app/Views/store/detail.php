<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= url('stores') ?>" class="text-decoration-none">Daftar Toko</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($store['name']) ?></li>
        </ol>
    </nav>

    <!-- Header Banner Card -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white">
        <!-- Banner Image -->
        <div class="position-relative bg-primary-gradient" style="height: 180px;">
            <?php if (!empty($store['banner'])): ?>
                <?php $bannerSrc = (str_starts_with($store['banner'], 'http') || str_starts_with($store['banner'], 'uploads/')) ? url($store['banner']) : url('uploads/stores/' . $store['banner']); ?>
                <img src="<?= $bannerSrc ?>" alt="Banner" class="w-100 h-100 object-fit-cover opacity-75" onerror="this.style.display='none'">
            <?php endif; ?>
        </div>

        <!-- Store Info Overlay -->
        <div class="p-4 pt-0 position-relative">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3" style="margin-top: -50px;">
                <!-- Logo & Name -->
                <div class="d-flex align-items-end gap-3">
                    <div class="bg-white border rounded-4 p-1 shadow" style="width: 100px; height: 100px; z-index: 2;">
                        <?php 
                            $logoSrc = !empty($store['logo']) ? ((str_starts_with($store['logo'], 'http') || str_starts_with($store['logo'], 'uploads/')) ? url($store['logo']) : url('uploads/stores/' . $store['logo'])) : 'https://images.unsplash.com/photo-1545173168-9f1947eebb7f?w=150';
                        ?>
                        <img src="<?= $logoSrc ?>" alt="Logo" class="w-100 h-100 object-fit-cover rounded-3" onerror="this.src='https://images.unsplash.com/photo-1545173168-9f1947eebb7f?w=150'">
                    </div>
                    <div class="mb-2">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="fw-bold text-dark mb-0"><?= e($store['name']) ?></h3>
                            <?php if (!empty($store['is_verified'])): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fs-8">
                                    <i class="bi bi-patch-check-fill me-1"></i> Terverifikasi
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
                            <span><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($store['kota'] ?? 'Jakarta') ?></span>
                            <span><i class="bi bi-clock me-1"></i><?= e($store['operational_hours'] ?? '08:00 - 20:00 WIB') ?></span>
                            <span><i class="bi bi-telephone me-1"></i><?= e($store['phone']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Rating Score & CTA Order -->
                <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                    <div class="text-end">
                        <div class="d-flex align-items-center gap-1 justify-content-end">
                            <i class="bi bi-star-fill text-warning fs-5"></i>
                            <span class="fw-bold fs-4 text-dark"><?= number_format((float)$store['rating_avg'], 1) ?></span>
                            <span class="text-muted small">/ 5.0</span>
                        </div>
                        <small class="text-muted"><?= (int)$store['rating_count'] ?> Ulasan Pelanggan</small>
                    </div>
                    <a href="<?= url('order/create?store_id=' . $store['id']) ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                        <i class="bi bi-bag-plus-fill me-1"></i> Pesan di Toko Ini
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content: Services & Reviews -->
    <div class="row g-4">
        <!-- Left Column: Deskripsi & Katalog Layanan -->
        <div class="col-lg-7">
            <!-- Deskripsi -->
            <?php if (!empty($store['description'])): ?>
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h5 class="fw-bold text-dark mb-2"><i class="bi bi-info-circle text-primary me-2"></i>Tentang Outlet</h5>
                    <p class="text-muted mb-0"><?= nl2br(e($store['description'])) ?></p>
                </div>
            <?php endif; ?>

            <!-- Katalog Layanan -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-tags-fill text-primary me-2"></i>Katalog Layanan Laundry</h5>
                    <span class="badge bg-light text-muted border rounded-pill px-3 py-1"><?= count($services) ?> Paket</span>
                </div>

                <?php if (empty($services)): ?>
                    <p class="text-muted small py-3 text-center">Belum ada layanan yang aktif untuk toko ini.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($services as $svc): ?>
                            <div class="col-12">
                                <div class="p-3 bg-light rounded-4 border border-light-subtle d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px;">
                                            <i class="bi <?= !empty($svc['icon']) ? e($svc['icon']) : 'bi-droplet' ?> fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= e($svc['name']) ?></div>
                                            <small class="text-muted">Min: <?= (float)$svc['min_order'] ?> <?= e($svc['unit']) ?> • Estimasi: <?= e($svc['estimated_duration']) ?></small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-primary fs-6"><?= format_rupiah($svc['price']) ?> <span class="fs-8 fw-normal text-muted">/ <?= e($svc['unit']) ?></span></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Rating Breakdown & Feed Ulasan -->
        <div class="col-lg-5">
            <!-- Rating Breakdown Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-star-fill text-warning me-2"></i>Kepuasan Pelanggan</h5>
                
                <div class="d-flex align-items-center gap-4 mb-3 pb-3 border-bottom">
                    <div class="text-center">
                        <h1 class="fw-extrabold text-dark mb-0"><?= number_format((float)$store['rating_avg'], 1) ?></h1>
                        <div class="text-warning small my-1">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi <?= $i <= round($store['rating_avg']) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <small class="text-muted"><?= (int)$store['rating_count'] ?> rating</small>
                    </div>
                    <div class="flex-grow-1">
                        <?php 
                        $totalR = max(1, (int)$store['rating_count']);
                        for ($star = 5; $star >= 1; $star--): 
                            $cnt = $distribution[$star] ?? 0;
                            $pct = ($cnt / $totalR) * 100;
                        ?>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fs-8 fw-semibold text-muted" style="width: 25px;"><?= $star ?>★</span>
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span class="fs-8 text-muted" style="width: 20px;"><?= $cnt ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Feed Ulasan Customer -->
                <h6 class="fw-bold text-dark mb-3">Ulasan Terbaru</h6>

                <?php if (empty($reviews)): ?>
                    <div class="text-center py-4 bg-light rounded-4 text-muted small">
                        <i class="bi bi-chat-heart fs-2 d-block mb-1"></i>
                        Belum ada ulasan untuk outlet ini. Jadilah yang pertama memberikan ulasan!
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="p-3 bg-light rounded-4 border border-light-subtle">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-8" style="width: 30px; height: 30px;">
                                            <?= strtoupper(substr($rev['customer_name'], 0, 1)) ?>
                                        </div>
                                        <div class="fw-semibold text-dark fs-7"><?= e($rev['customer_name']) ?></div>
                                    </div>
                                    <div class="text-warning small">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <i class="bi <?= $s <= $rev['rating'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <p class="small text-dark mb-2"><?= nl2br(e($rev['comment'])) ?></p>

                                <?php if (!empty($rev['photo'])): ?>
                                    <div class="mb-2">
                                        <img src="<?= url($rev['photo']) ?>" alt="Foto Ulasan" class="rounded-3 border shadow-sm" style="max-height: 100px; max-width: 140px; object-fit: cover;">
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center text-muted fs-8">
                                    <span><?= e($rev['service_names'] ?? 'Layanan Laundry') ?></span>
                                    <span><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
