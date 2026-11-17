<!-- Hero Section -->
<section class="hero-section py-5 position-relative overflow-hidden">
    <div class="container py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 mb-3 fw-semibold">
                    <i class="bi bi-stars text-warning me-1"></i> Generasi Baru Marketplace Laundry Berbasis AI
                </div>
                <h1 class="display-4 fw-extrabold text-dark tracking-tight mb-4">
                    Laundry Bersih, Wangi & Cepat, <span class="gradient-text">Dijemput Langsung</span> ke Rumah.
                </h1>
                <p class="lead text-muted mb-4">
                    Hubungkan pakaian kotor Anda dengan puluhan mitra laundry terbaik di sekitar Anda. Lengkap dengan penjemputan kurir live GPS, pembayaran QRIS otomatis, dan bantuan asisten AI cerdas.
                </p>

                <div class="d-flex flex-wrap gap-3 mb-4">
                    <a href="<?= url('stores') ?>" class="btn btn-primary btn-lg rounded-pill px-4 shadow-sm fw-semibold d-flex align-items-center gap-2">
                        <i class="bi bi-bag-plus"></i> Cari Toko Laundry
                    </a>
                    <a href="<?= url('login') ?>" class="btn btn-outline-dark btn-lg rounded-pill px-4 fw-semibold d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk Akun
                    </a>
                </div>

                <div class="row g-3 pt-3 border-top border-light-subtle">
                    <div class="col-4">
                        <div class="fw-bold fs-4 text-dark">50+</div>
                        <div class="text-muted small">Mitra Toko Aktif</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-4 text-dark">1 Jam</div>
                        <div class="text-muted small">QRIS Auto Timer</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-4 text-dark">100%</div>
                        <div class="text-muted small">Live GPS Tracking</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="position-relative">
                    <div class="hero-card-glow position-absolute top-50 start-50 translate-middle"></div>
                    
                    <!-- Interactive Visual Mockup Card -->
                    <div class="card border-0 shadow-lg rounded-5 overflow-hidden hero-visual-card position-relative z-1">
                        <div class="card-header bg-primary text-white p-4 border-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold"><i class="bi bi-truck me-2"></i>Status Penjemputan Real-Time</h6>
                                <small class="opacity-75">Order #LND-8942 • Kiloan Cuci & Setrika</small>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold animate-pulse">
                                <i class="bi bi-geo-alt-fill me-1"></i> Kurir Menuju Lokasi
                            </span>
                        </div>
                        <div class="card-body p-4 bg-light">
                            <!-- Tracking Step Preview -->
                            <div class="d-flex justify-content-between position-relative mb-4 tracking-progress-bar">
                                <div class="text-center z-1">
                                    <div class="badge rounded-circle bg-success text-white p-2 mb-1"><i class="bi bi-check-lg"></i></div>
                                    <div class="small fw-semibold text-muted">Order Dibuat</div>
                                </div>
                                <div class="text-center z-1">
                                    <div class="badge rounded-circle bg-primary text-white p-2 mb-1"><i class="bi bi-person-walking"></i></div>
                                    <div class="small fw-bold text-primary">Kurir OTW</div>
                                </div>
                                <div class="text-center z-1">
                                    <div class="badge rounded-circle bg-secondary text-white p-2 mb-1"><i class="bi bi-buildings"></i></div>
                                    <div class="small fw-semibold text-muted">Di Toko</div>
                                </div>
                                <div class="text-center z-1">
                                    <div class="badge rounded-circle bg-secondary text-white p-2 mb-1"><i class="bi bi-house-check"></i></div>
                                    <div class="small fw-semibold text-muted">Selesai</div>
                                </div>
                            </div>

                            <!-- Live AI Feature Highlight -->
                            <div class="card border-0 bg-white shadow-sm rounded-4 p-3 mb-3">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="ai-avatar-icon bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center">
                                        <i class="bi bi-robot fs-4"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-7">SmartLaundry AI Assistant</div>
                                        <p class="text-muted small mb-0">"Pakaian kotor Anda seberat ±3.5 kg diperkirakan siap diantar kembali besok sore pukul 16:00 WIB."</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Price & QRIS info -->
                            <div class="d-flex justify-content-between align-items-center bg-white rounded-4 p-3 shadow-sm">
                                <div>
                                    <div class="text-muted small">Total Tagihan (3.5 kg)</div>
                                    <div class="fw-extrabold text-success fs-5">Rp 24.500</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                                        <i class="bi bi-qr-code me-1"></i> QRIS Ready (1 Jam)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-5 bg-white border-top border-light-subtle">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5">
            <h6 class="text-primary text-uppercase fw-bold letter-spacing-1 mb-2">Fitur Unggulan</h6>
            <h2 class="fw-extrabold text-dark">Solusi Lengkap Laundry Modern Masa Kini</h2>
            <p class="text-muted">Dirancang khusus untuk memberikan kenyamanan maksimal bagi pelanggan dan efisiensi operasional bagi pemilik laundry.</p>
        </div>

        <div class="row g-4">
            <?php foreach ($features as $f): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border-0 shadow-sm rounded-4 p-3 hover-elevate transition">
                        <div class="card-body">
                            <div class="feature-icon bg-primary-subtle text-primary rounded-4 d-inline-flex align-items-center justify-content-center p-3 mb-3">
                                <i class="bi <?= $f['icon'] ?> fs-3"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-2"><?= e($f['title']) ?></h5>
                            <p class="text-muted small mb-0"><?= e($f['desc']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-5 bg-light">
    <div class="container py-4">
        <div class="text-center max-w-700 mx-auto mb-5">
            <h6 class="text-primary text-uppercase fw-bold letter-spacing-1 mb-2">Alur Pemesanan</h6>
            <h2 class="fw-extrabold text-dark">Mudah, Praktis dalam 4 Langkah</h2>
            <p class="text-muted">Pesan laundry dari ponsel tanpa perlu repot keluar rumah.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-3 text-center">
                <div class="step-card p-4 rounded-4 bg-white shadow-sm h-100">
                    <div class="step-number bg-primary text-white rounded-circle mx-auto mb-3 fw-bold d-flex align-items-center justify-content-center">1</div>
                    <h6 class="fw-bold text-dark">Pilih Toko & Layanan</h6>
                    <p class="text-muted small mb-0">Cari toko laundry terdekat dan tentukan jenis layanan yang diinginkan.</p>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="step-card p-4 rounded-4 bg-white shadow-sm h-100">
                    <div class="step-number bg-primary text-white rounded-circle mx-auto mb-3 fw-bold d-flex align-items-center justify-content-center">2</div>
                    <h6 class="fw-bold text-dark">Kurir Menjemput</h6>
                    <p class="text-muted small mb-0">Kurir datang menjemput pakaian kotor dan dapat dipantau via live GPS Maps.</p>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="step-card p-4 rounded-4 bg-white shadow-sm h-100">
                    <div class="step-number bg-primary text-white rounded-circle mx-auto mb-3 fw-bold d-flex align-items-center justify-content-center">3</div>
                    <h6 class="fw-bold text-dark">Timbang & Bayar</h6>
                    <p class="text-muted small mb-0">Laundry ditimbang di toko, invoice otomatis muncul, bayar via QRIS (1 jam) atau Cash.</p>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="step-card p-4 rounded-4 bg-white shadow-sm h-100">
                    <div class="step-number bg-primary text-white rounded-circle mx-auto mb-3 fw-bold d-flex align-items-center justify-content-center">4</div>
                    <h6 class="fw-bold text-dark">Diantar Bersih & Rapi</h6>
                    <p class="text-muted small mb-0">Pakaian dicuci higienis, disetrika rapi, dan diantar kembali tepat waktu.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Popular Services Section -->
<section class="py-5 bg-white">
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <h6 class="text-primary text-uppercase fw-bold letter-spacing-1 mb-2">Pilihan Paket</h6>
                <h2 class="fw-extrabold text-dark mb-0">Layanan Populer</h2>
            </div>
            <a href="<?= url('stores') ?>" class="btn btn-outline-primary rounded-pill px-4 mt-2 mt-sm-0">
                Lihat Semua Toko & Layanan <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($popularServices as $srv): ?>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 border border-light-subtle shadow-sm rounded-4 p-3 hover-elevate transition">
                        <div class="card-body d-flex flex-column">
                            <span class="badge bg-primary-subtle text-primary rounded-pill align-self-start mb-2 px-3 py-2 fw-semibold">
                                <?= e($srv['badge']) ?>
                            </span>
                            <h5 class="fw-bold text-dark mt-2 mb-1"><?= e($srv['name']) ?></h5>
                            <p class="text-muted small"><i class="bi bi-clock me-1"></i> Estimasi <?= e($srv['time']) ?></p>

                            <div class="mt-auto pt-3 border-top border-light-subtle d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Mulai dari</small>
                                    <div class="fw-bold fs-5 text-primary">Rp <?= number_format($srv['price'], 0, ',', '.') ?> <span class="fs-7 text-muted fw-normal">/<?= e($srv['unit']) ?></span></div>
                                </div>
                                <a href="<?= url('stores') ?>" class="btn btn-sm btn-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary-gradient text-white">
    <div class="container py-4 text-center">
        <div class="max-w-700 mx-auto">
            <h2 class="display-6 fw-extrabold mb-3">Siap Menikmati Laundry Tanpa Ribet?</h2>
            <p class="lead opacity-90 mb-4">Daftar sekarang dan nikmati kemudahan mencuci pakaian dengan jemput-antar dan asisten AI.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="<?= url('register') ?>" class="btn btn-warning text-dark btn-lg rounded-pill px-5 fw-bold shadow">
                    Daftar Sekarang Gratis
                </a>
            </div>
        </div>
    </div>
</section>
