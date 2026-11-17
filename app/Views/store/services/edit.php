<div class="container py-4">
    <!-- Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                <li class="breadcrumb-item"><a href="<?= url('store/services') ?>" class="text-decoration-none">Katalog Layanan</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Layanan</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Edit Layanan Laundry</h3>
        <small class="text-muted">Perbarui tarif harga, estimasi waktu, atau spesifikasi untuk <strong><?= e($service['name']) ?></strong>.</small>
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
                <form action="<?= url('store/services/' . $service['id'] . '/update') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Layanan <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control rounded-3" value="<?= e($service['name']) ?>" required placeholder="Contoh: Cuci Komplit Reguler">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kategori Layanan <span class="text-danger">*</span></label>
                            <select name="category" class="form-select rounded-3" required>
                                <option value="kiloan" <?= $service['category'] === 'kiloan' ? 'selected' : '' ?>>Kiloan (Pakaian Sehari-hari)</option>
                                <option value="satuan" <?= $service['category'] === 'satuan' ? 'selected' : '' ?>>Satuan (Jas, Gaun, Bedcover, dll)</option>
                                <option value="express" <?= $service['category'] === 'express' ? 'selected' : '' ?>>Express (Kilat 6-12 Jam)</option>
                                <option value="special" <?= $service['category'] === 'special' ? 'selected' : '' ?>>Special Treatment (Sepatu, Tas, Helm)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Icon Layanan</label>
                            <select name="icon" class="form-select rounded-3">
                                <option value="bi-droplet" <?= ($service['icon'] ?? '') === 'bi-droplet' ? 'selected' : '' ?>>💧 bi-droplet (Cuci Air)</option>
                                <option value="bi-wind" <?= ($service['icon'] ?? '') === 'bi-wind' ? 'selected' : '' ?>>💨 bi-wind (Kering / Angin)</option>
                                <option value="bi-lightning-charge" <?= ($service['icon'] ?? '') === 'bi-lightning-charge' ? 'selected' : '' ?>>⚡ bi-lightning (Express Kilat)</option>
                                <option value="bi-stars" <?= ($service['icon'] ?? '') === 'bi-stars' ? 'selected' : '' ?>>✨ bi-stars (Premium / Treatment)</option>
                                <option value="bi-box-seam" <?= ($service['icon'] ?? '') === 'bi-box-seam' ? 'selected' : '' ?>>📦 bi-box-seam (Satuan / Bed Cover)</option>
                                <option value="bi-award" <?= ($service['icon'] ?? '') === 'bi-award' ? 'selected' : '' ?>>🏅 bi-award (Sepatu / Tas)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tarif Harga (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">Rp</span>
                                <input type="number" name="price" class="form-control rounded-end-3" value="<?= (float)$service['price'] ?>" required min="100" step="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Satuan Ukuran <span class="text-danger">*</span></label>
                            <select name="unit" class="form-select rounded-3" required>
                                <option value="kg" <?= $service['unit'] === 'kg' ? 'selected' : '' ?>>kg (Kilogram)</option>
                                <option value="pcs" <?= $service['unit'] === 'pcs' ? 'selected' : '' ?>>pcs (Potong / Lembar)</option>
                                <option value="pasang" <?= $service['unit'] === 'pasang' ? 'selected' : '' ?>>pasang (Sepatu / Sandal)</option>
                                <option value="set" <?= $service['unit'] === 'set' ? 'selected' : '' ?>>set (Sprei / Bedcover)</option>
                                <option value="m2" <?= $service['unit'] === 'm2' ? 'selected' : '' ?>>m2 (Meter Persegi - Karpet)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Minimal Order</label>
                            <input type="number" name="min_order" class="form-control rounded-3" value="<?= (float)$service['min_order'] ?>" min="0.1" step="0.5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Estimasi Durasi Pengerjaan <span class="text-danger">*</span></label>
                            <input type="text" name="estimated_duration" class="form-control rounded-3" value="<?= e($service['estimated_duration']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deskripsi Layanan & Catatan Khusus</label>
                        <textarea name="description" class="form-control rounded-3" rows="3"><?= e($service['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-check form-switch mb-4 p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="is_active" value="1" id="isActiveCheck" <?= !empty($service['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-dark" for="isActiveCheck">
                            Status Layanan Aktif
                        </label>
                        <small class="text-muted d-block ms-5">Jika dinonaktifkan, layanan ini tidak akan muncul di opsi pemesanan checkout customer.</small>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('store/services') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
