<div class="container py-4">
    <!-- Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                <li class="breadcrumb-item"><a href="<?= url('store/couriers') ?>" class="text-decoration-none">Armada Kurir</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah Kurir Baru</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Tambah & Daftarkan Akun Kurir Baru</h3>
        <small class="text-muted">Buat akun login kurir sekaligus mendaftarkan armada kendaraan untuk <strong><?= e($store['name']) ?></strong>.</small>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashError = get_flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <form action="<?= url('store/couriers') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="row g-4">
                    <!-- Left: Data Akun Login Kurir -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge text-primary me-2"></i>Informasi & Akun Kurir</h5>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Nama Lengkap Kurir <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control rounded-3" required placeholder="Contoh: Ahmad Fauzi">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Alamat Email (Untuk Login) <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control rounded-3" required placeholder="kurir.ahmad@example.com">
                                <small class="text-muted fs-8">Email ini digunakan kurir untuk login ke aplikasi.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Nomor WhatsApp / Telepon <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                    <input type="text" name="phone" class="form-control rounded-end-3" required placeholder="081234567890">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Password Awal Akun <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                    <input type="password" name="password" class="form-control rounded-end-3" required minlength="6" placeholder="Minimal 6 karakter">
                                </div>
                                <small class="text-muted fs-8">Kurir dapat mengganti password setelah berhasil masuk.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Data Armada Kendaraan -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
                            <div>
                                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-truck text-primary me-2"></i>Data Armada Kendaraan</h5>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Jenis / Model Kendaraan <span class="text-danger">*</span></label>
                                    <input type="text" name="vehicle_type" class="form-control rounded-3" required placeholder="Contoh: Honda Vario 160 / Yamaha NMAX">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Nomor Plat Polisi <span class="text-danger">*</span></label>
                                    <input type="text" name="license_plate" class="form-control rounded-3 text-uppercase font-monospace" required placeholder="Contoh: B 1234 XYZ">
                                    <small class="text-muted fs-8">Nomor plat resmi yang tertera pada kendaraan operasional.</small>
                                </div>

                                <div class="p-3 bg-primary-subtle rounded-3 mb-3">
                                    <div class="d-flex align-items-center gap-2 text-primary">
                                        <i class="bi bi-info-circle-fill fs-5"></i>
                                        <small class="fw-semibold">Registrasi otomatis 2-in-1: Membuat akun user dengan role <code>kurir</code> sekaligus mendaftarkan profil armada toko Anda.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 pt-3">
                                <a href="<?= url('store/couriers') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                    <i class="bi bi-check2-circle me-1"></i> Daftarkan Kurir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
