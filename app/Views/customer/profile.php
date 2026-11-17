<div class="container py-4">
    <!-- Header Card -->
    <div class="card border-0 bg-primary-gradient text-white shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 64px; height: 64px;">
                    <i class="bi bi-person-circle fs-2"></i>
                </div>
                <div>
                    <div class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-1 fw-bold">
                        <?= e($user['role_display'] ?? 'Pelanggan') ?>
                    </div>
                    <h3 class="fw-bold mb-0"><?= e($user['name']) ?></h3>
                    <small class="opacity-75"><i class="bi bi-envelope me-1"></i><?= e($user['email']) ?> • Terdaftar sejak <?= date('d M Y', strtotime($user['created_at'])) ?></small>
                </div>
            </div>
            <div>
                <a href="<?= url('customer/addresses') ?>" class="btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-geo-alt-fill me-1"></i> Kelola Buku Alamat (<?= count($addresses) ?>)
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Profile Completeness Checklist & Account Info -->
        <div class="col-lg-4">
            <!-- 1. Profile Completeness Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check text-primary me-2"></i>Kelengkapan Akun</h6>

                <?php 
                $totalReq = 3;
                $doneReq = $totalReq - count($completeness['missing']);
                $pct = round(($doneReq / $totalReq) * 100);
                ?>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted">Status Profil</span>
                    <span class="badge <?= $pct === 100 ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill px-3 py-1 fw-bold"><?= $pct ?>% Selesai</span>
                </div>

                <div class="progress mb-3" style="height: 8px;">
                    <div class="progress-bar <?= $pct === 100 ? 'bg-success' : 'bg-warning' ?>" role="progressbar" style="width: <?= $pct ?>%"></div>
                </div>

                <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small">
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi <?= !empty($user['name']) ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
                        <span class="<?= !empty($user['name']) ? 'text-dark' : 'text-muted' ?>">Nama Lengkap</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi <?= !empty($user['phone']) ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
                        <span class="<?= !empty($user['phone']) ? 'text-dark' : 'text-muted' ?>">Nomor WhatsApp Aktif</span>
                    </li>
                    <li class="d-flex align-items-center gap-2">
                        <i class="bi <?= !empty($addresses) ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
                        <span class="<?= !empty($addresses) ? 'text-dark' : 'text-muted' ?>">Alamat Utama Penjemputan (<?= count($addresses) ?> Alamat)</span>
                    </li>
                </ul>
            </div>

            <!-- 2. Quick Security Info -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-lock-fill text-primary me-2"></i>Keamanan Akun</h6>
                <div class="d-flex flex-column gap-2 small text-muted">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Metode Login:</span>
                        <span class="badge bg-light text-dark border">
                            <?= !empty($user['google_id']) ? '<i class="bi bi-google text-danger me-1"></i> Google Auth' : '<i class="bi bi-envelope-at me-1"></i> Email & Password' ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Status Akun:</span>
                        <span class="badge bg-success-subtle text-success">Aktif</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Edit Profile Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-pencil-square text-primary me-2"></i>Perbarui Data Pribadi</h5>

                <form action="<?= url('customer/profile/update') ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Email</label>
                        <input type="email" class="form-control rounded-3 bg-light" value="<?= e($user['email']) ?>" readonly>
                        <small class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Alamat email terdaftar tidak dapat diubah.</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control rounded-3" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor WhatsApp / Telepon <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control rounded-3" value="<?= e($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                            <small class="text-muted fs-8">Digunakan kurir untuk konfirmasi jemput pakaian.</small>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-key-fill text-warning me-2"></i>Ubah Kata Sandi (Opsional)</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Kata Sandi Baru</label>
                        <input type="password" name="password" class="form-control rounded-3" placeholder="Kosongkan jika tidak ingin mengubah kata sandi">
                        <small class="text-muted fs-8">Minimal 6 karakter.</small>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= url('dashboard/customer') ?>" class="btn btn-light rounded-pill px-4">Kembali ke Dashboard</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                            <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview Saved Addresses -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-geo-alt text-primary me-2"></i>Alamat Tersimpan (<?= count($addresses) ?>)</h6>
                    <a href="<?= url('customer/addresses') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">Lihat Semua</a>
                </div>

                <?php if (empty($addresses)): ?>
                    <div class="alert alert-light border rounded-3 text-center small text-muted mb-0">
                        Belum ada alamat tersimpan. <a href="<?= url('customer/addresses/create') ?>" class="fw-bold text-primary">Tambah Alamat Baru</a>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach (array_slice($addresses, 0, 3) as $a): ?>
                            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong class="text-dark fs-7"><?= e($a['label']) ?></strong>
                                        <?php if ($a['is_primary']): ?>
                                            <span class="badge bg-primary fs-8">Utama</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted d-block"><?= e($a['full_address']) ?></small>
                                </div>
                                <a href="<?= url('customer/addresses/' . $a['id'] . '/edit') ?>" class="btn btn-sm btn-light rounded-circle"><i class="bi bi-pencil"></i></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
