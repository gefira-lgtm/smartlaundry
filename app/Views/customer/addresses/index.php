<div class="container py-4">
    <!-- Header Card -->
    <div class="card border-0 bg-primary-gradient text-white shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
                    <i class="bi bi-geo-alt-fill fs-3"></i>
                </div>
                <div>
                    <div class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-1 fw-bold">
                        Buku Alamat
                    </div>
                    <h3 class="fw-bold mb-0">Kelola Alamat Pengantaran</h3>
                    <small class="opacity-75">Simpan alamat rumah, kantor, atau kos untuk penjemputan cucian cepat.</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('customer/addresses/create') ?>" class="btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-plus-circle-fill me-1"></i> Tambah Alamat Baru
                </a>
            </div>
        </div>
    </div>

    <!-- Completeness Indicator Banner (if incomplete) -->
    <?php if (!$completeness['is_complete']): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-warning text-dark rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Profil Anda Belum Lengkap!</h6>
                        <p class="small text-muted mb-0">
                            Perlu melengkapi: <strong><?= implode(', ', $completeness['missing']) ?></strong> agar kurir dapat menjemput pakaian tanpa hambatan.
                        </p>
                    </div>
                </div>
                <div>
                    <a href="<?= url('customer/profile') ?>" class="btn btn-sm btn-dark rounded-pill px-3 fw-semibold">
                        <i class="bi bi-person-fill-gear me-1"></i> Lengkapi Profil
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Address Cards List -->
    <div class="row g-4">
        <?php if (empty($addresses)): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                    <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center p-4 mx-auto mb-3" style="width: 80px; height: 80px;">
                        <i class="bi bi-pin-map fs-1 text-muted"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Belum Ada Alamat Tersimpan</h5>
                    <p class="text-muted small mb-3">Tambahkan alamat utama Anda sekarang agar kurir dapat langsung diarahkan ke lokasi Anda via GPS.</p>
                    <div>
                        <a href="<?= url('customer/addresses/create') ?>" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Alamat Pertama
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($addresses as $addr): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100 position-relative <?= $addr['is_primary'] ? 'border-primary border-2' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge <?= $addr['is_primary'] ? 'bg-primary' : 'bg-secondary-subtle text-dark' ?> rounded-pill px-3 py-2 fw-bold">
                                    <i class="bi bi-house-door me-1"></i> <?= e($addr['label']) ?>
                                </span>
                                <?php if ($addr['is_primary']): ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fs-8 fw-semibold">
                                        <i class="bi bi-check-circle-fill me-1"></i> Utama
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle shadow-none" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 py-2">
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="<?= url('customer/addresses/' . $addr['id'] . '/edit') ?>">
                                            <i class="bi bi-pencil-square text-primary"></i> Edit Alamat
                                        </a>
                                    </li>
                                    <?php if (!$addr['is_primary']): ?>
                                        <li>
                                            <form action="<?= url('customer/addresses/' . $addr['id'] . '/set-primary') ?>" method="POST">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2">
                                                    <i class="bi bi-star-fill text-warning"></i> Jadikan Alamat Utama
                                                </button>
                                            </form>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="<?= url('customer/addresses/' . $addr['id'] . '/delete') ?>" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus alamat ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger">
                                                    <i class="bi bi-trash-fill"></i> Hapus Alamat
                                                </button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark mb-1"><?= e($addr['recipient_name'] ?: $user['name']) ?></h6>
                        <p class="small text-muted mb-2"><i class="bi bi-telephone me-1"></i><?= e($addr['phone'] ?: ($user['phone'] ?? '-')) ?></p>
                        
                        <p class="text-secondary small leading-relaxed mb-3 flex-grow-1">
                            <?= e($addr['full_address']) ?>
                            <?php if (!empty($addr['rt']) || !empty($addr['rw'])): ?>
                                <br><span class="text-muted">RT <?= e($addr['rt']) ?> / RW <?= e($addr['rw']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($addr['kelurahan']) || !empty($addr['kecamatan'])): ?>
                                <br><span class="text-muted"><?= e($addr['kelurahan']) ?><?= (!empty($addr['kelurahan']) && !empty($addr['kecamatan'])) ? ', ' : '' ?><?= e($addr['kecamatan']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($addr['kota'])): ?>
                                <br><span class="text-muted"><?= e($addr['kota']) ?> <?= e($addr['postal_code']) ?></span>
                            <?php endif; ?>
                        </p>

                        <?php if (!empty($addr['notes'])): ?>
                            <div class="bg-light rounded-3 p-2 mb-3 small text-muted">
                                <i class="bi bi-info-circle me-1 text-primary"></i><strong>Patokan:</strong> <?= e($addr['notes']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- GPS Coordinates Pill -->
                        <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                            <small class="text-muted font-monospace fs-8">
                                <i class="bi bi-geo-fill text-danger me-1"></i><?= number_format((float)$addr['latitude'], 4) ?>, <?= number_format((float)$addr['longitude'], 4) ?>
                            </small>
                            <a href="https://www.google.com/maps?q=<?= $addr['latitude'] ?>,<?= $addr['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 py-1 fs-8">
                                <i class="bi bi-map me-1"></i> Buka Maps
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
