<div class="container py-4">
    <!-- Header Banner -->
    <div class="card border-0 bg-primary-gradient text-white shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
                    <i class="bi bi-truck fs-3"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">
                            <?= e($user['role_display'] ?? 'Kurir Toko') ?>
                        </span>
                        <?php if ($courier['is_available']): ?>
                            <span class="badge bg-success rounded-pill px-3 py-1 animate-pulse">
                                <i class="bi bi-circle-fill fs-8 me-1"></i> Siap Bertugas
                            </span>
                        <?php else: ?>
                            <span class="badge bg-secondary rounded-pill px-3 py-1">
                                <i class="bi bi-dash-circle me-1"></i> Sedang Offline
                            </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="fw-bold mb-0"><?= e($courier['courier_name']) ?></h3>
                    <small class="opacity-75">
                        <i class="bi bi-bicycle me-1"></i><?= e($courier['vehicle_type']) ?> (<strong><?= e($courier['license_plate']) ?></strong>) • Outlet: <strong><?= e($courier['store_name'] ?? 'Pusat Logistik') ?></strong>
                    </small>
                </div>
            </div>

            <!-- Toggle Availability Action -->
            <div>
                <form action="<?= url('courier/toggle-availability') ?>" method="POST">
                    <?= csrf_field() ?>
                    <?php if ($courier['is_available']): ?>
                        <button type="submit" class="btn btn-outline-light rounded-pill px-3 py-2 fw-semibold">
                            <i class="bi bi-power me-1"></i> Set Status: Offline
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-warning text-dark rounded-pill px-3 py-2 fw-bold shadow-sm">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Aktifkan Ketersediaan
                        </button>
                    <?php endif; ?>
                </form>
            </div>
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

    <!-- Live GPS Location Simulation Widget -->
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="bi bi-geo-alt-fill fs-4"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark fs-7 mb-0">Posisi Live GPS Kurir:</div>
                    <div class="font-monospace small text-muted">
                        Lat: <strong id="displayLat"><?= $courier['current_lat'] ?? '-6.20000000' ?></strong>, 
                        Lng: <strong id="displayLng"><?= $courier['current_lng'] ?? '106.81666600' ?></strong>
                        <span id="displayTime" class="ms-2 badge bg-light text-muted border">
                            Update: <?= !empty($courier['last_location_updated_at']) ? date('H:i:s', strtotime($courier['last_location_updated_at'])) . ' WIB' : 'Baru saja' ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a id="linkGmaps" href="https://www.google.com/maps?q=<?= $courier['current_lat'] ?? '-6.20000000' ?>,<?= $courier['current_lng'] ?? '106.81666600' ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Google Maps
                </a>
                <button type="button" id="btnUpdateGpsGlobal" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-crosshair me-1"></i> Update Lokasi Saya (GPS)
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Tugas Penjemputan</span>
                    <div class="bg-warning-subtle text-warning rounded-3 p-2"><i class="bi bi-box-arrow-in-down fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= count($pickupTasks) ?> <span class="fs-6 text-muted fw-normal">Tugas</span></h3>
                <small class="text-warning fw-semibold">Perlu Dijemput ke Rumah</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Tugas Pengantaran</span>
                    <div class="bg-info-subtle text-info rounded-3 p-2"><i class="bi bi-box-arrow-up fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= count($deliveryTasks) ?> <span class="fs-6 text-muted fw-normal">Tugas</span></h3>
                <small class="text-info fw-semibold">Siap Diantar ke Customer</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase">Selesai Hari Ini</span>
                    <div class="bg-success-subtle text-success rounded-3 p-2"><i class="bi bi-check2-circle fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= count($completedToday) ?> <span class="fs-6 text-muted fw-normal">Selesai</span></h3>
                <small class="text-success fw-semibold">Riwayat Berhasil</small>
            </div>
        </div>
    </div>

    <!-- 0.5 Section Tawaran Tugas Baru (Tahap 12 - Menunggu Respon Kurir) -->
    <?php if (!empty($pendingOffers)): ?>
        <div class="card border-0 shadow-lg rounded-4 p-4 mb-4 bg-primary text-white position-relative overflow-hidden">
            <div class="position-absolute end-0 top-0 opacity-10 p-3">
                <i class="bi bi-bell-fill" style="font-size: 8rem;"></i>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3 position-relative z-1">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark p-2 rounded-circle animate-pulse"><i class="bi bi-bell-fill fs-5"></i></span>
                    <div>
                        <h5 class="fw-bold mb-0 text-white">Tawaran Tugas Baru (Menunggu Respon Anda)</h5>
                        <small class="opacity-75">Terdapat <?= count($pendingOffers) ?> pesanan yang ditawarkan kepada Anda. Silakan terima atau tolak tugas ini.</small>
                    </div>
                </div>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 fw-bold"><?= count($pendingOffers) ?> Tawaran Masuk</span>
            </div>

            <div class="row g-3 position-relative z-1">
                <?php foreach ($pendingOffers as $po): ?>
                    <div class="col-lg-6">
                        <div class="card border-0 rounded-4 p-3 bg-white text-dark shadow-sm h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-light text-dark border font-monospace fs-7">#<?= e($po['order_code']) ?></span>
                                            <?php if ($po['task_type'] === 'RETURN'): ?>
                                                <span class="badge bg-danger text-white rounded-pill px-2 py-1 fs-8">PENGEMBALIAN</span>
                                            <?php elseif ($po['task_type'] === 'DELIVERY'): ?>
                                                <span class="badge bg-info text-white rounded-pill px-2 py-1 fs-8">PENGANTARAN</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1 fs-8">PENJEMPUTAN</span>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-0"><?= e($po['customer_name']) ?></h6>
                                        <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($po['customer_phone'] ?? '-') ?> • Outlet: <strong><?= e($po['store_name']) ?></strong></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 fs-8">
                                            <i class="bi bi-hourglass-split me-1"></i> Menunggu Respon
                                        </span>
                                        <div class="small text-muted mt-1"><?= date('H:i, d M', strtotime($po['offered_at'])) ?></div>
                                    </div>
                                </div>

                                <!-- Lokasi Jemput / Antar -->
                                <div class="p-2 bg-light rounded-3 mb-3 border small">
                                    <div class="fw-semibold text-dark mb-1">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                        <?= ($po['task_type'] === 'DELIVERY' || $po['task_type'] === 'RETURN') ? 'Alamat Pengantaran:' : 'Alamat Penjemputan:' ?>
                                    </div>
                                    <div class="text-muted text-truncate" style="max-width: 100%;">
                                        <?= e(($po['task_type'] === 'DELIVERY' || $po['task_type'] === 'RETURN') ? ($po['delivery_address'] ?? $po['pickup_address']) : $po['pickup_address']) ?>
                                    </div>
                                    <?php if (!empty($po['service_names'])): ?>
                                        <div class="mt-1 small text-primary"><i class="bi bi-tag me-1"></i><?= e($po['service_names']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tombol Aksi Terima / Tolak -->
                            <div class="pt-2 border-top d-flex gap-2 align-items-center">
                                <!-- Form Terima Tugas -->
                                <form action="<?= url('courier/offers/' . $po['id'] . '/accept') ?>" method="POST" class="flex-grow-1">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-success w-100 rounded-pill fw-bold shadow-sm py-2">
                                        <i class="bi bi-check-circle-fill me-1"></i> Terima Tugas
                                    </button>
                                </form>

                                <!-- Tombol Tolak Tugas (Dropdown/Modal Sederhana) -->
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-3 py-2 fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-x-circle me-1"></i> Tolak
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg rounded-4 border-0" style="width: 280px;">
                                        <h6 class="fw-bold text-dark fs-8 mb-2">Pilih Alasan Penolakan:</h6>
                                        <form action="<?= url('courier/offers/' . $po['id'] . '/reject') ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <div class="mb-2">
                                                <select name="reject_reason" class="form-select form-select-sm rounded-3">
                                                    <option value="Kendaraan sedang bermasalah / perbaikan">Kendaraan bermasalah</option>
                                                    <option value="Sedang istirahat / jeda makan">Sedang istirahat</option>
                                                    <option value="Lokasi di luar jangkauan rute operasional saat ini">Lokasi terlalu jauh</option>
                                                    <option value="Kapasitas muatan kendaraan sudah penuh">Kapasitas penuh</option>
                                                    <option value="Lainnya">Lainnya</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-xs btn-danger w-100 rounded-pill fw-bold">
                                                Konfirmasi Tolak
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. Antrean Tugas Penjemputan (Pickup) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-box-arrow-in-down text-warning me-2"></i>Daftar Tugas Penjemputan Laundry</h5>
                <p class="text-muted small mb-0">Pesanan yang ditugaskan ke Anda untuk dijemput dari rumah customer dan dibawa ke toko.</p>
            </div>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-1"><?= count($pickupTasks) ?> Antrean</span>
        </div>

        <?php if (empty($pickupTasks)): ?>
            <div class="text-center py-4 bg-light rounded-4 text-muted small">
                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                Tidak ada tugas penjemputan aktif saat ini.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($pickupTasks as $pt): ?>
                    <div class="col-lg-6">
                        <div class="card border border-light-subtle rounded-4 p-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-light text-dark border font-monospace mb-1"><?= e($pt['order_code']) ?></span>
                                        <h6 class="fw-bold text-dark mb-0"><?= e($pt['customer_name']) ?></h6>
                                    </div>
                                    <span class="badge bg-primary rounded-pill fs-8"><?= str_replace('_', ' ', $pt['status']) ?></span>
                                </div>

                                <!-- Customer Contact & Action -->
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $pt['customer_phone']) ?>" class="btn btn-xs btn-outline-success rounded-pill px-3 py-1 fs-8 fw-semibold">
                                        <i class="bi bi-telephone-fill me-1"></i> Telepon: <?= e($pt['customer_phone']) ?>
                                    </a>
                                </div>

                                <!-- Pickup Address Card -->
                                <div class="p-3 bg-white rounded-3 mb-3 border border-light-subtle">
                                    <div class="small fw-semibold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Alamat Penjemputan:</div>
                                    <div class="small text-muted mb-2"><?= e($pt['pickup_address']) ?></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-primary"><i class="bi bi-calendar-event me-1"></i><?= format_datetime_id($pt['scheduled_pickup_at']) ?></small>
                                        <a href="https://www.google.com/maps?q=<?= $pt['pickup_lat'] ?? '-6.2081' ?>,<?= $pt['pickup_lng'] ?? '106.7892' ?>" target="_blank" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 fs-8">
                                            <i class="bi bi-map me-1"></i> Rute Maps
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Contextual Action Buttons per Status -->
                            <div class="pt-2 border-top d-flex flex-column gap-2">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-update-gps-task" data-order-id="<?= $pt['id'] ?>">
                                        <i class="bi bi-geo-alt me-1"></i> Update Lokasi Tugas
                                    </button>

                                    <?php if ($pt['status'] === 'MENUNGGU_KURIR'): ?>
                                        <form action="<?= url('courier/orders/' . $pt['id'] . '/start-pickup') ?>" method="POST" class="flex-grow-1">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-bold">
                                                <i class="bi bi-person-walking me-1"></i> Mulai OTW Jemput
                                            </button>
                                        </form>
                                    <?php elseif ($pt['status'] === 'KURIR_MENUJU_LOKASI'): ?>
                                        <form action="<?= url('courier/orders/' . $pt['id'] . '/confirm-picked') ?>" method="POST" class="flex-grow-1">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-warning text-dark w-100 rounded-pill fw-bold">
                                                <i class="bi bi-bag-check-fill me-1"></i> Konfirmasi Sampai & Ambil Laundry
                                            </button>
                                        </form>
                                    <?php elseif ($pt['status'] === 'LAUNDRY_DIJEMPUT'): ?>
                                        <form action="<?= url('courier/orders/' . $pt['id'] . '/start-travel-store') ?>" method="POST" class="flex-grow-1">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-info text-white w-100 rounded-pill fw-bold">
                                                <i class="bi bi-arrow-right-circle-fill me-1"></i> Mulai Perjalanan ke Toko
                                            </button>
                                        </form>
                                    <?php elseif ($pt['status'] === 'DALAM_PERJALANAN_KE_TOKO'): ?>
                                        <form action="<?= url('courier/orders/' . $pt['id'] . '/arrive-store') ?>" method="POST" class="flex-grow-1">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-success w-100 rounded-pill fw-bold">
                                                <i class="bi bi-building-check me-1"></i> Sudah Sampai Toko
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 1.5 Antrean Tugas Pengembalian Laundry (Tahap 11 - Bukan Laundry Selesai) -->
    <?php if (!empty($refundTasks)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-warning bg-opacity-10 border border-warning">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-arrow-return-left text-danger me-2"></i>Tugas Pengembalian Laundry
                        <span class="badge bg-danger rounded-pill ms-2 fs-8">Bukan Laundry Selesai</span>
                    </h5>
                    <p class="text-muted small mb-0">Pesanan ini mengalami pembayaran kadaluarsa dan pakaian harus dikembalikan ke alamat pelanggan (Order akan Dibatalkan).</p>
                </div>
                <span class="badge bg-danger text-white rounded-pill px-3 py-1"><?= count($refundTasks) ?> Pengembalian</span>
            </div>

            <div class="row g-3">
                <?php foreach ($refundTasks as $rt): ?>
                    <div class="col-lg-6">
                        <div class="card border-danger-subtle rounded-4 p-3 bg-white h-100 d-flex flex-column justify-content-between shadow-sm">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace mb-1">#<?= e($rt['order_code']) ?></span>
                                        <h6 class="fw-bold text-dark mb-0"><?= e($rt['customer_name']) ?></h6>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-warning text-dark rounded-pill mb-1 fs-8">PROSES PENGEMBALIAN</span>
                                        <div class="small text-muted">Biaya: <strong><?= format_rupiah($rt['refund_fee'] ?? 0) ?></strong> (<?= e($rt['refund_fee_bearer'] ?? 'free') ?>)</div>
                                    </div>
                                </div>

                                <!-- Customer Contact -->
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $rt['customer_phone'] ?? '') ?>" class="btn btn-xs btn-outline-success rounded-pill px-3 py-1 fs-8 fw-semibold">
                                        <i class="bi bi-telephone-fill me-1"></i> Telepon: <?= e($rt['customer_phone'] ?? '-') ?>
                                    </a>
                                </div>

                                <!-- Delivery Address Card -->
                                <div class="p-3 bg-light rounded-3 mb-3 border border-light-subtle">
                                    <div class="small fw-semibold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Alamat Pengantaran Kembali:</div>
                                    <div class="small text-muted mb-2"><?= e($rt['delivery_address']) ?></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Berat: <?= $rt['weight_actual'] ?? '0' ?> kg (<?= (int)($rt['pieces_count'] ?? 0) ?> pcs)</small>
                                        <a href="https://www.google.com/maps?q=<?= $rt['delivery_lat'] ?? '-6.1755' ?>,<?= $rt['delivery_lng'] ?? '106.7865' ?>" target="_blank" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 fs-8">
                                            <i class="bi bi-map me-1"></i> Buka Rute Maps
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Contextual Action Buttons -->
                            <div class="pt-2 border-top d-flex flex-column gap-2">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-update-gps-task" data-order-id="<?= $rt['id'] ?>">
                                        <i class="bi bi-geo-alt me-1"></i> Update Lokasi
                                    </button>

                                    <form action="<?= url('courier/orders/' . $rt['id'] . '/start-refund-delivery') ?>" method="POST" class="flex-grow-1">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-warning text-dark w-100 rounded-pill fw-bold">
                                            <i class="bi bi-truck me-1"></i> Mulai Jalan Antar Balik
                                        </button>
                                    </form>
                                </div>
                                <form action="<?= url('courier/orders/' . $rt['id'] . '/confirm-refund-returned') ?>" method="POST" onsubmit="return confirm('Konfirmasi bahwa pakaian telah berhasil diserahkan kembali kepada pelanggan? Pesanan akan resmi DIBATALKAN.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger text-white w-100 rounded-pill fw-bold shadow-sm">
                                        <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Selesai Dikembalikan (Batal)
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 2. Antrean Tugas Pengantaran (Delivery) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-box-arrow-up text-info me-2"></i>Daftar Tugas Pengantaran Laundry</h5>
                <p class="text-muted small mb-0">Pakaian yang sudah selesai dicuci dan siap diantar kembali ke rumah customer.</p>
            </div>
            <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1"><?= count($deliveryTasks) ?> Antrean</span>
        </div>

        <?php if (empty($deliveryTasks)): ?>
            <div class="text-center py-4 bg-light rounded-4 text-muted small">
                <i class="bi bi-check2-all fs-2 d-block mb-1 text-success"></i>
                Tidak ada pakaian yang menunggu pengantaran saat ini.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($deliveryTasks as $dt): ?>
                    <div class="col-lg-6">
                        <div class="card border border-light-subtle rounded-4 p-3 bg-light h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-light text-dark border font-monospace mb-1"><?= e($dt['order_code']) ?></span>
                                        <h6 class="fw-bold text-dark mb-0"><?= e($dt['customer_name']) ?></h6>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success rounded-pill mb-1 fs-8"><?= str_replace('_', ' ', $dt['status']) ?></span>
                                        <div class="small fw-bold text-success"><?= format_rupiah($dt['total_price'] ?? $dt['payment_amount'] ?? 0) ?></div>
                                    </div>
                                </div>

                                <!-- Customer Contact & Action -->
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $dt['customer_phone']) ?>" class="btn btn-xs btn-outline-success rounded-pill px-3 py-1 fs-8 fw-semibold">
                                        <i class="bi bi-telephone-fill me-1"></i> Telepon: <?= e($dt['customer_phone']) ?>
                                    </a>
                                </div>

                                <!-- Delivery Address Card -->
                                <div class="p-3 bg-white rounded-3 mb-3 border border-light-subtle">
                                    <div class="small fw-semibold text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i>Alamat Pengantaran:</div>
                                    <div class="small text-muted mb-2"><?= e($dt['delivery_address']) ?></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Berat: <?= $dt['weight_actual'] ?? '0' ?> kg (<?= (int)($dt['pieces_count'] ?? 0) ?> pcs)</small>
                                        <a href="https://www.google.com/maps?q=<?= $dt['delivery_lat'] ?? '-6.1755' ?>,<?= $dt['delivery_lng'] ?? '106.7865' ?>" target="_blank" class="btn btn-xs btn-outline-primary rounded-pill px-2 py-1 fs-8">
                                            <i class="bi bi-map me-1"></i> Rute Maps
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Contextual Action Buttons per Status -->
                            <div class="pt-2 border-top d-flex flex-column gap-2">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 btn-update-gps-task" data-order-id="<?= $dt['id'] ?>">
                                        <i class="bi bi-geo-alt me-1"></i> Update Lokasi
                                    </button>

                                    <?php if ($dt['status'] === 'SIAP_DIANTAR'): ?>
                                        <form action="<?= url('courier/orders/' . $dt['id'] . '/start-delivery') ?>" method="POST" class="flex-grow-1">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-bold">
                                                <i class="bi bi-truck me-1"></i> Mulai Antar ke Customer
                                            </button>
                                        </form>
                                    <?php elseif ($dt['status'] === 'KURIR_MENUJU_CUSTOMER'): ?>
                                        <form action="<?= url('courier/orders/' . $dt['id'] . '/complete-delivery') ?>" method="POST" class="flex-grow-1">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-success w-100 rounded-pill fw-bold">
                                                <i class="bi bi-check-circle-fill me-1"></i> Konfirmasi Sudah Diantar
                                            </button>
                                        </form>
                                    <?php elseif ($dt['status'] === 'LAUNDRY_DIANTAR'): ?>
                                        <div class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill py-2 px-3 fs-8 flex-grow-1 d-flex align-items-center justify-content-center">
                                            <i class="bi bi-hourglass-split me-1"></i> Menunggu Konfirmasi Penerimaan Pelanggan
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 3. Riwayat Tugas Selesai Hari Ini -->
    <?php if (!empty($completedToday)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-success me-2"></i>Tugas Selesai Hari Ini</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small text-uppercase">
                            <th>Kode Order</th>
                            <th>Customer</th>
                            <th>Outlet Toko</th>
                            <th>Nominal</th>
                            <th>Waktu Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedToday as $ct): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border font-monospace"><?= e($ct['order_code']) ?></span></td>
                                <td class="fw-semibold text-dark"><?= e($ct['customer_name']) ?></td>
                                <td><?= e($ct['store_name']) ?></td>
                                <td class="fw-bold text-success"><?= format_rupiah($ct['payment_amount'] ?? 0) ?></td>
                                <td class="small text-muted"><?= date('H:i WIB', strtotime($ct['completed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Real-time GPS Location Update Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    function sendGpsUpdate(orderId = 0, btnElement = null) {
        if (btnElement) {
            btnElement.disabled = true;
            btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengupdate...';
        }

        function executePost(lat, lng) {
            const formData = new FormData();
            formData.append('_csrf_token', '<?= csrf_token() ?>');
            formData.append('order_id', orderId);
            if (lat !== null && lng !== null) {
                formData.append('latitude', lat);
                formData.append('longitude', lng);
            }

            fetch('<?= url('courier/update-location') ?>', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('displayLat').innerText = parseFloat(data.latitude).toFixed(6);
                    document.getElementById('displayLng').innerText = parseFloat(data.longitude).toFixed(6);
                    document.getElementById('displayTime').innerText = 'Update: ' + data.updated_at + ' WIB';
                    document.getElementById('linkGmaps').href = 'https://www.google.com/maps?q=' + data.latitude + ',' + data.longitude;
                    
                    if (btnElement) {
                        btnElement.innerHTML = '<i class="bi bi-check2 me-1"></i> Terupdate!';
                        setTimeout(() => {
                            btnElement.disabled = false;
                            btnElement.innerHTML = orderId > 0 ? '<i class="bi bi-geo-alt me-1"></i> Update Lokasi' : '<i class="bi bi-crosshair me-1"></i> Update Lokasi Saya (GPS)';
                        }, 1500);
                    }
                } else {
                    alert(data.message || 'Gagal memperbarui lokasi');
                    if (btnElement) btnElement.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                if (btnElement) btnElement.disabled = false;
            });
        }

        // Try Geolocation API
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    executePost(pos.coords.latitude, pos.coords.longitude);
                },
                function(err) {
                    // Fallback to simulation endpoint
                    console.log('GPS browser not permitted, using intelligent simulation fallback');
                    executePost(null, null);
                },
                { timeout: 5000 }
            );
        } else {
            executePost(null, null);
        }
    }

    const btnGlobal = document.getElementById('btnUpdateGpsGlobal');
    if (btnGlobal) {
        btnGlobal.addEventListener('click', function() {
            sendGpsUpdate(0, this);
        });
    }

    document.querySelectorAll('.btn-update-gps-task').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            sendGpsUpdate(orderId, this);
        });
    });
});
</script>
