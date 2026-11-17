<footer class="bg-dark text-white pt-5 pb-4 mt-auto border-top border-secondary border-opacity-25">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?php $footerLogo = app_logo(); ?>
                    <?php if ($footerLogo): ?>
                        <img src="<?= url($footerLogo) ?>" alt="<?= e(app_name()) ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                    <?php else: ?>
                        <div class="brand-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                            <i class="bi bi-water fs-5"></i>
                        </div>
                    <?php endif; ?>
                    <span class="fs-5 fw-bold"><?= e(app_name()) ?></span>
                </div>
                <p class="text-secondary small leading-relaxed">
                    <?= e(\App\Models\Setting::get('app_tagline', 'Platform Marketplace Laundry Berbasis Web nomor satu dengan integrasi kurir otomatis, pelacakan live Maps, serta sistem pembayaran aman QRIS dan Cash.')) ?>
                </p>
                <div class="d-flex gap-2 pt-2">
                    <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-twitter-x"></i></a>
                    <?php $waContact = \App\Models\Setting::get('contact_phone'); ?>
                    <?php if ($waContact): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $waContact) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-whatsapp"></i></a>
                    <?php else: ?>
                        <a href="#" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-whatsapp"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold text-uppercase text-light mb-3 fs-7 tracking-wider">Layanan</h6>
                <ul class="list-unstyled text-secondary small d-flex flex-column gap-2">
                    <li><a href="<?= url('stores') ?>" class="text-secondary text-decoration-none hover-white">Laundry Kiloan</a></li>
                    <li><a href="<?= url('stores') ?>" class="text-secondary text-decoration-none hover-white">Laundry Satuan</a></li>
                    <li><a href="<?= url('stores') ?>" class="text-secondary text-decoration-none hover-white">Express 6 Jam</a></li>
                    <li><a href="<?= url('stores') ?>" class="text-secondary text-decoration-none hover-white">Cuci Sepatu & Tas</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="fw-bold text-uppercase text-light mb-3 fs-7 tracking-wider">Peran Pengguna</h6>
                <ul class="list-unstyled text-secondary small d-flex flex-column gap-2">
                    <li><a href="<?= url('login') ?>" class="text-secondary text-decoration-none hover-white">Customer</a></li>
                    <li><a href="<?= url('login') ?>" class="text-secondary text-decoration-none hover-white">Mitra Toko</a></li>
                    <li><a href="<?= url('login') ?>" class="text-secondary text-decoration-none hover-white">Admin Toko</a></li>
                    <li><a href="<?= url('login') ?>" class="text-secondary text-decoration-none hover-white">Kurir Antar</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-6">
                <h6 class="fw-bold text-uppercase text-light mb-3 fs-7 tracking-wider">Keunggulan Sistem</h6>
                <div class="d-flex flex-column gap-2 text-secondary small">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Live GPS Kurir Tracking</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>QRIS Otomatis dengan Timer 1 Jam</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Smart AI Assistant & Tanya Jawab</span>
                    </div>
                </div>
            </div>
        </div>

        <hr class="border-secondary border-opacity-25 my-4">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                <p class="text-secondary small mb-0">&copy; <?= date('Y') ?> <?= e(\App\Models\Setting::get('footer_text', 'SmartLaundry Marketplace. All rights reserved.')) ?></p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span class="badge bg-secondary bg-opacity-25 text-light px-3 py-2 rounded-pill small">
                    <i class="bi bi-code-slash text-warning me-1"></i> Arsitektur MVC • PHP 8+ • Bootstrap 5
                </span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet JS (OpenStreetMap Interactive Maps) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- Custom JS -->
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
