<div class="container py-4">
    <!-- Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                <li class="breadcrumb-item"><a href="<?= url('store/couriers') ?>" class="text-decoration-none">Armada Kurir</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Kendaraan Kurir</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Edit Kendaraan Kurir</h3>
        <small class="text-muted">Perbarui data armada kendaraan operasional untuk <strong><?= e($courier['courier_name']) ?></strong>.</small>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashError = get_flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <!-- Courier Profile Summary -->
                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-4">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-4 shadow-sm" style="width: 54px; height: 54px;">
                        <?= strtoupper(substr($courier['courier_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><?= e($courier['courier_name']) ?></h5>
                        <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= e($courier['courier_email']) ?> • <i class="bi bi-telephone ms-2 me-1"></i><?= e($courier['courier_phone']) ?></small>
                    </div>
                </div>

                <form action="<?= url('store/couriers/' . $courier['id'] . '/update') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Jenis / Model Kendaraan <span class="text-danger">*</span></label>
                        <input type="text" name="vehicle_type" class="form-control rounded-3" value="<?= e($courier['vehicle_type']) ?>" required placeholder="Contoh: Honda Beat Street / Yamaha Aerox">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold">Nomor Plat Polisi <span class="text-danger">*</span></label>
                        <input type="text" name="license_plate" class="form-control rounded-3 text-uppercase font-monospace" value="<?= e($courier['license_plate']) ?>" required placeholder="Contoh: B 1234 XYZ">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('store/couriers') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Kendaraan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
