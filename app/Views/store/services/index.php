<div class="container py-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Katalog Layanan</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-0">Katalog Layanan Laundry</h3>
            <small class="text-muted">Kelola daftar jenis cucian, tarif harga, minimal order, dan estimasi pengerjaan untuk <strong><?= e($store['name']) ?></strong>.</small>
        </div>
        <div>
            <a href="<?= url('store/services/create') ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-plus-circle-fill me-1"></i> Tambah Layanan Baru
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

    <?php if ($flashWarning = get_flash('warning')): ?>
        <div class="alert alert-warning alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i><?= e($flashWarning) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashSuccess = get_flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($flashSuccess) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Services Table Card -->
    <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
        <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">Daftar Layanan Tersedia (<?= count($services) ?>)</h5>
            <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1">Owner Controls</span>
        </div>

        <?php if (empty($services)): ?>
            <div class="p-5 text-center">
                <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center p-4 mb-3">
                    <i class="bi bi-tag fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark">Belum Ada Layanan Laundry</h5>
                <p class="text-muted small mb-4">Tambahkan layanan kiloan, satuan, atau express pertama Anda agar pelanggan dapat mulai membuat pesanan.</p>
                <a href="<?= url('store/services/create') ?>" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="bi bi-plus-lg me-1"></i> Buat Layanan Baru
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 50px;">Icon</th>
                            <th>Nama Layanan & Deskripsi</th>
                            <th>Kategori</th>
                            <th>Tarif Harga</th>
                            <th>Min. Order</th>
                            <th>Estimasi</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $svc): ?>
                            <tr class="<?= empty($svc['is_active']) ? 'opacity-75 bg-light' : '' ?>">
                                <td class="ps-4">
                                    <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                        <i class="bi <?= !empty($svc['icon']) ? e($svc['icon']) : 'bi-droplet' ?> fs-5"></i>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($svc['name']) ?></div>
                                    <small class="text-muted d-block text-truncate" style="max-width: 250px;">
                                        <?= e($svc['description'] ?? 'Tidak ada deskripsi.') ?>
                                    </small>
                                </td>
                                <td>
                                    <?php 
                                        $catColor = match($svc['category']) {
                                            'kiloan' => 'bg-primary-subtle text-primary',
                                            'satuan' => 'bg-info-subtle text-info',
                                            'express' => 'bg-warning-subtle text-warning',
                                            'special' => 'bg-purple-subtle text-purple',
                                            default => 'bg-secondary-subtle text-secondary'
                                        };
                                    ?>
                                    <span class="badge <?= $catColor ?> rounded-pill px-3 py-1 text-uppercase fs-8 fw-bold">
                                        <?= e($svc['category']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= format_rupiah($svc['price']) ?></span>
                                    <small class="text-muted">/ <?= e($svc['unit']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border rounded-pill px-2 py-1 small">
                                        <?= (float)$svc['min_order'] ?> <?= e($svc['unit']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($svc['estimated_duration']) ?></small>
                                </td>
                                <td class="text-center">
                                    <form action="<?= url('store/services/' . $svc['id'] . '/toggle') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm rounded-pill px-3 py-1 <?= $svc['is_active'] ? 'btn-success text-white' : 'btn-outline-secondary' ?>" title="Klik untuk ubah status aktif">
                                            <?php if ($svc['is_active']): ?>
                                                <i class="bi bi-check-circle-fill me-1"></i> Aktif
                                            <?php else: ?>
                                                <i class="bi bi-dash-circle me-1"></i> Nonaktif
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="<?= url('store/services/' . $svc['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary rounded-start-pill px-3" title="Edit Layanan">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-end-pill px-3" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $svc['id'] ?>" title="Hapus Layanan">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>

                                    <!-- Delete Confirmation Modal -->
                                    <div class="modal fade text-start" id="deleteModal<?= $svc['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content rounded-4 border-0 shadow">
                                                <div class="modal-header border-0 pb-0">
                                                    <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Hapus Layanan Laundry</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body py-3">
                                                    <p class="mb-2">Apakah Anda yakin ingin menghapus layanan <strong><?= e($svc['name']) ?></strong>?</p>
                                                    <div class="p-3 bg-light rounded-3 small text-muted">
                                                        <i class="bi bi-info-circle me-1"></i> Jika layanan ini pernah atau sedang digunakan pada pesanan yang belum selesai, sistem akan melarang penghapusan demi menjaga integritas data riwayat transaksi.
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0 pt-0">
                                                    <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                    <form action="<?= url('store/services/' . $svc['id'] . '/delete') ?>" method="POST" class="d-inline">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                                                            Ya, Hapus Layanan
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
