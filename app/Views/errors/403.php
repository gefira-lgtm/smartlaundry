<div class="container py-5 my-auto text-center">
    <div class="max-w-600 mx-auto py-5">
        <div class="display-1 fw-extrabold text-danger mb-3">403</div>
        <h3 class="fw-bold text-dark mb-2">Akses Ditolak (Unauthorized)</h3>
        <p class="text-muted mb-4">Anda tidak memiliki izin untuk mengakses halaman ini sesuai dengan hak akses peran (RBAC) akun Anda.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="<?= url('/') ?>" class="btn btn-primary rounded-pill px-4 py-2 fw-semibold">
                <i class="bi bi-house-door me-1"></i> Kembali ke Beranda
            </a>
            <a href="<?= url('login') ?>" class="btn btn-outline-dark rounded-pill px-4 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-1"></i> Ganti Akun
            </a>
        </div>
    </div>
</div>
