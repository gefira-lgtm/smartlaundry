<div class="container py-5 my-auto">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="card-header bg-primary-gradient text-white text-center py-4 border-0">
                    <div class="brand-icon bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2 shadow-sm" style="width: 48px; height: 48px;">
                        <i class="bi bi-water fs-4"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Masuk ke SmartLaundry</h4>
                    <p class="small opacity-75 mb-0">Platform Marketplace Laundry Berbasis AI</p>
                </div>

                <div class="card-body p-4 p-md-5 bg-white">
                    <!-- Google OAuth 1-Click Button (with Simulated fallback) -->
                    <a href="<?= url('auth/google') ?>" class="btn btn-outline-dark w-100 py-2 rounded-pill fw-semibold mb-4 d-flex align-items-center justify-content-center gap-2 border-secondary-subtle hover-elevate">
                        <svg width="18" height="18" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
                            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.35 24 12 24z"/>
                            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.98 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
                            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.35 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
                        </svg>
                        <span>Masuk Cepat dengan Google</span>
                    </a>

                    <div class="position-relative text-center my-4">
                        <hr class="border-secondary-subtle">
                        <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 text-muted small">atau via Email & Password</span>
                    </div>

                    <form action="<?= url('login') ?>" method="POST">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-dark">Alamat Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-4"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" name="email" class="form-control bg-light border-start-0 rounded-end-4" placeholder="nama@email.com" value="<?= e($_POST['email'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold text-dark mb-0">Kata Sandi</label>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 rounded-start-4"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password" class="form-control bg-light border-start-0 rounded-end-4" placeholder="••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow-sm mb-3">
                            Masuk
                        </button>
                    </form>

                    <div class="text-center text-muted small">
                        Belum punya akun? <a href="<?= url('register') ?>" class="text-primary fw-semibold text-decoration-none">Daftar Customer Baru</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
