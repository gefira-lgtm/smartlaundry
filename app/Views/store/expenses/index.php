<?php
/**
 * Store Expenses View
 */
?>
<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengeluaran Operasional</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-wallet2 text-primary"></i> Pengeluaran Toko - <?= e($store['name']) ?>
            </h1>
            <p class="text-muted small mb-0">Catat dan pantau seluruh pengeluaran operasional outlet laundry Anda.</p>
        </div>
        <div>
            <a href="<?= url('dashboard/store') ?>" class="btn btn-outline-secondary rounded-pill px-3 btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Form Pencatatan Pengeluaran Baru -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 px-4 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-plus-circle text-success me-1"></i> Catat Pengeluaran
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?= url('store/expenses') ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nama Barang / Item <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" class="form-control rounded-3" placeholder="Contoh: Deterjen Bubuk 20kg" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
                            <select name="category" class="form-select rounded-3" required>
                                <option value="BAHAN_BAKU">Bahan Baku (Deterjen, Pewangi, dll)</option>
                                <option value="KEMASAN">Kemasan (Plastik Packing, Tas)</option>
                                <option value="OPERASIONAL" selected>Operasional</option>
                                <option value="UTILITAS">Utilitas (Listrik, Air, Gas)</option>
                                <option value="PERALATAN">Peralatan & Perawatan Mesin</option>
                                <option value="GAJI">Gaji Karyawan</option>
                                <option value="LAINNYA">Lainnya</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nominal Total (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="1000" name="amount" class="form-control rounded-3" placeholder="Contoh: 150000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Tanggal Pengeluaran</label>
                            <input type="date" name="expense_date" class="form-control rounded-3" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nama Supplier / Vendor</label>
                            <input type="text" name="supplier" class="form-control rounded-3" placeholder="Contoh: CV Sumber Kimia">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Catatan Tambahan</label>
                            <textarea name="notes" class="form-control rounded-3" rows="2" placeholder="Keterangan opsional"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary rounded-pill w-100 py-2">
                            <i class="bi bi-save me-1"></i> Simpan Pengeluaran
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Pengeluaran Terakhir -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-clock-history text-primary me-1"></i> Riwayat Pengeluaran
                    </h5>
                    <span class="badge bg-primary rounded-pill"><?= count($expenses) ?> Item</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="fs-7 text-uppercase text-muted">
                                <th class="ps-4">Tanggal</th>
                                <th>Item / Kategori</th>
                                <th>Supplier</th>
                                <th>Dicatat Oleh</th>
                                <th class="pe-4 text-end">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="fs-7">
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-receipt fs-1 d-block mb-2 text-secondary"></i>
                                        Belum ada data pengeluaran yang dicatat.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($expenses as $ex): ?>
                                    <tr>
                                        <td class="ps-4 text-muted whitespace-nowrap">
                                            <?= date('d M Y', strtotime($ex['expense_date'])) ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($ex['item_name'] ?: $ex['title']) ?></div>
                                            <span class="badge bg-secondary-subtle text-dark border px-2 py-0 fs-8">
                                                <?= e($ex['category']) ?>
                                            </span>
                                            <?php if (!empty($ex['notes'])): ?>
                                                <div class="small text-muted mt-1"><?= e($ex['notes']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?= e($ex['supplier'] ?: '-') ?></td>
                                        <td class="text-muted small"><?= e($ex['recorded_by_name'] ?: 'Sistem') ?></td>
                                        <td class="pe-4 text-end fw-bold text-danger whitespace-nowrap">
                                            <?= format_rupiah($ex['total_amount'] ?: $ex['amount']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

