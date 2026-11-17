<?php
/**
 * SmartLaundry - Store Configuration Page
 * Accessible by owner_toko (can edit) and admin_toko (view only)
 */
?>

<div class="container py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-3 py-1 rounded-pill fs-8">
                    <i class="bi bi-shop me-1"></i> Outlet: <?= e($store['name']) ?>
                </span>
                <?php if ($isReadOnly): ?>
                    <span class="badge bg-warning bg-opacity-10 text-warning fw-semibold px-3 py-1 rounded-pill fs-8">
                        <i class="bi bi-eye me-1"></i> Mode Lihat Saja (Admin Toko)
                    </span>
                <?php else: ?>
                    <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-3 py-1 rounded-pill fs-8">
                        <i class="bi bi-pencil me-1"></i> Pemilik Toko (Dapat Mengubah)
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="fw-bold text-dark mb-0">Pengaturan Operasional Outlet Toko</h2>
            <p class="text-secondary small mb-0">Kelola jam kerja, status buka/tutup sementara, kontak WhatsApp toko, dan kebijakan pengembalian.</p>
        </div>
        <div>
            <a href="<?= url('dashboard/store') ?>" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold shadow-xs">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Form Settings Card -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom p-4">
                    <div class="d-flex align-items-center gap-2">
                        <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3"><i class="bi bi-sliders fs-5"></i></div>
                        <div>
                            <h5 class="fw-bold mb-0">Konfigurasi Operasional Toko</h5>
                            <p class="text-secondary small mb-0">Perubahan akan langsung berpengaruh pada sistem pemesanan customer.</p>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('store/settings') ?>" method="POST">
                        <?= csrf_field() ?>

                        <!-- Temporary Open / Closed Switch -->
                        <div class="p-4 rounded-4 mb-4 <?= $isOpenTemporary ? 'bg-success-subtle border border-success' : 'bg-danger-subtle border border-danger' ?>">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi <?= $isOpenTemporary ? 'bi-door-open-fill text-success' : 'bi-door-closed-fill text-danger' ?> fs-4"></i>
                                        <h6 class="fw-bold mb-0 <?= $isOpenTemporary ? 'text-success' : 'text-danger' ?>">
                                            Status Outlet: <?= $isOpenTemporary ? 'BUKA (Menerima Pesanan)' : 'TUTUP SEMENTARA (Menolak Pesanan)' ?>
                                        </h6>
                                    </div>
                                    <div class="small <?= $isOpenTemporary ? 'text-success' : 'text-danger' ?>">
                                        <?= $isOpenTemporary 
                                            ? 'Pelanggan dapat membuat pesanan laundry baru secara normal.' 
                                            : 'Pemesanan baru dari customer akan ditolak sistem secara otomatis saat checkout.' ?>
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input fs-4" type="checkbox" role="switch" name="is_open_temporary" value="1" 
                                           id="isOpenSwitch" <?= $isOpenTemporary ? 'checked' : '' ?> <?= $isReadOnly ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <!-- Operational Hours -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Jam Operasional Toko <span class="text-danger">*</span></label>
                                <input type="text" name="operational_hours" class="form-control rounded-3" 
                                       value="<?= e($operationalHours) ?>" placeholder="Contoh: 08:00 - 20:00 WIB" 
                                       required <?= $isReadOnly ? 'disabled' : '' ?>>
                                <small class="text-secondary">Ditampilkan kepada pelanggan pada katalog toko.</small>
                            </div>

                            <!-- Phone / WhatsApp -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Nomor WhatsApp Outlet <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text rounded-start-3"><i class="bi bi-whatsapp text-success"></i></span>
                                    <input type="text" name="phone" class="form-control rounded-end-3" 
                                           value="<?= e($phone) ?>" placeholder="08xxxxxxxxxx" 
                                           required <?= $isReadOnly ? 'disabled' : '' ?>>
                                </div>
                                <small class="text-secondary">Nomor untuk komunikasi kurir dan customer.</small>
                            </div>

                            <!-- Return Fee Bearer -->
                            <div class="col-md-12">
                                <label class="form-label fw-bold text-dark">Penanggung Biaya Pengembalian (Kadaluarsa)</label>
                                <select name="return_fee_bearer_default" class="form-select rounded-3" <?= $isReadOnly ? 'disabled' : '' ?>>
                                    <option value="CUSTOMER" <?= $returnFeeBearer === 'CUSTOMER' ? 'selected' : '' ?>>CUSTOMER (Pelanggan menanggung ongkir pengembalian)</option>
                                    <option value="STORE" <?= $returnFeeBearer === 'STORE' ? 'selected' : '' ?>>STORE (Toko menanggung ongkir pengembalian)</option>
                                    <option value="FREE" <?= $returnFeeBearer === 'FREE' ? 'selected' : '' ?>>FREE (Gratis / Tidak ada pungutan ongkir retur)</option>
                                </select>
                                <small class="text-secondary">Aturan default toko ini ketika pesanan QRIS kadaluarsa dan cucian harus diantar kembali.</small>
                            </div>
                        </div>

                        <?php if (!$isReadOnly): ?>
                        <div class="mt-4 pt-3 border-top text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-xs">
                                <i class="bi bi-save me-1"></i> Simpan Pengaturan Toko
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Store Info Summary Card -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-info-circle text-primary me-2"></i>Informasi Outlet</h6>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary mx-auto d-flex align-items-center justify-content-center mb-2" style="width: 64px; height: 64px;">
                            <i class="bi bi-shop fs-3"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-0"><?= e($store['name']) ?></h6>
                        <small class="text-muted">Slug: <?= e($store['slug']) ?></small>
                    </div>

                    <div class="p-3 bg-light rounded-3 fs-8 mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Status Verifikasi:</span>
                            <span class="badge <?= !empty($store['is_verified']) ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= !empty($store['is_verified']) ? 'Terverifikasi' : 'Menunggu Verifikasi' ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Rating Rata-rata:</span>
                            <span class="fw-bold text-warning"><i class="bi bi-star-fill me-1"></i><?= $store['rating_avg'] ?? '5.00' ?></span>
                        </div>
                    </div>

                    <div class="text-secondary small">
                        <p class="mb-2"><strong><i class="bi bi-shield-lock me-1"></i> Catatan Hak Akses:</strong></p>
                        <ul class="ps-3 mb-0">
                            <li><strong>Owner Toko</strong> memiliki izin penuh mengubah jadwal buka, nomor kontak, dan kebijakan retur.</li>
                            <li><strong>Admin Toko</strong> hanya memiliki izin melihat (read-only) untuk keseragaman operasional.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
