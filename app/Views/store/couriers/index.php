<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Armada Kurir</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-0">Manajemen Armada Kurir</h3>
            <small class="text-muted">Kelola tim kurir penjemputan dan pengantaran laundry untuk <strong><?= e($store['name']) ?></strong>.</small>
        </div>
        <div>
            <a href="<?= url('store/couriers/create') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Kurir Baru
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashError = get_flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashSuccess = get_flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Couriers Table Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Daftar Kurir Terdaftar (<?= count($couriers) ?>)</h5>
            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1">Owner Toko Exclusive</span>
        </div>

        <?php if (empty($couriers)): ?>
            <div class="p-5 text-center">
                <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center p-4 mb-3">
                    <i class="bi bi-truck fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark">Belum Ada Kurir Terdaftar</h5>
                <p class="text-muted small mb-4">Tambahkan armada kurir toko Anda untuk menangani pickup pakaian dari rumah pelanggan dan delivery cucian bersih.</p>
                <a href="<?= url('store/couriers/create') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-person-plus me-1"></i> Daftarkan Kurir Pertama
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Kurir</th>
                            <th>Kontak & Akun Login</th>
                            <th>Kendaraan & Plat Nomor</th>
                            <th class="text-center">Status Operasional</th>
                            <th class="text-center">Tugas Aktif</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($couriers as $c): ?>
                            <tr class="<?= !empty($c['is_permanently_inactive']) ? 'opacity-75 bg-light' : '' ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 44px; height: 44px;">
                                            <?= strtoupper(substr($c['courier_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= e($c['courier_name']) ?></div>
                                            <small class="text-muted">ID Kurir: #KUR-<?= str_pad((string)$c['id'], 3, '0', STR_PAD_LEFT) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-dark fw-semibold"><i class="bi bi-envelope me-1 text-muted"></i><?= e($c['courier_email']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone me-1 text-muted"></i><?= e($c['courier_phone']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><i class="bi bi-bicycle me-1 text-primary"></i><?= e($c['vehicle_type']) ?></div>
                                    <span class="badge bg-dark-subtle text-dark border rounded-pill px-2 py-1 font-monospace fs-8">
                                        <?= e($c['license_plate']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($c['is_permanently_inactive'])): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-slash-circle me-1"></i> Nonaktif Permanen
                                        </span>
                                    <?php elseif ($c['is_available'] && $c['status'] === 'TERSEDIA'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-dot fs-6"></i> Tersedia (Online)
                                        </span>
                                    <?php elseif ($c['status'] === 'BERTUGAS'): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-truck me-1"></i> Sedang Bertugas
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-power me-1"></i> Offline
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ((int)($c['active_tasks_count'] ?? 0) > 0): ?>
                                        <span class="badge bg-warning text-dark rounded-pill px-2 py-1 fw-bold">
                                            <?= (int)$c['active_tasks_count'] ?> Order
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">0 Order</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="<?= url('store/couriers/' . $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary rounded-start-pill px-3" title="Edit Kendaraan">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>

                                        <?php if (empty($c['is_permanently_inactive'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-end-pill px-3" data-bs-toggle="modal" data-bs-target="#deactivateModal<?= $c['id'] ?>" title="Nonaktifkan Kurir">
                                                <i class="bi bi-person-x-fill"></i>
                                            </button>
                                        <?php else: ?>
                                            <form action="<?= url('store/couriers/' . $c['id'] . '/reactivate') ?>" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success rounded-end-pill px-3" title="Aktifkan Kembali Kurir">
                                                    <i class="bi bi-person-check-fill"></i> Aktifkan
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Deactivate Confirmation Modal -->
                                    <?php if (empty($c['is_permanently_inactive'])): ?>
                                        <div class="modal fade text-start" id="deactivateModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content rounded-4 border-0 shadow">
                                                    <div class="modal-header border-0 pb-0">
                                                        <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Nonaktifkan Kurir</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body py-3">
                                                        <p class="mb-2">Apakah Anda yakin ingin menonaktifkan kurir <strong><?= e($c['courier_name']) ?></strong>?</p>
                                                        <div class="p-3 bg-light rounded-3 small text-muted">
                                                            <i class="bi bi-info-circle me-1"></i> Kurir akan di-set menjadi nonaktif (soft-delete) sehingga tidak akan menerima tugas baru, namun seluruh data historis pengiriman order lama tetap tersimpan dengan aman.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-0 pt-0">
                                                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                        <form action="<?= url('store/couriers/' . $c['id'] . '/deactivate') ?>" method="POST" class="d-inline">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                                                                Ya, Nonaktifkan Kurir
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
