<div class="container py-4">
    <!-- Header Banner -->
    <div class="card border-0 bg-primary-gradient text-white shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
                    <i class="bi bi-shop fs-3"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-1 fw-bold">
                            <?= e($user['role_display'] ?? 'Toko Laundry') ?>
                        </span>
                        <?php if ($store['is_verified']): ?>
                            <span class="badge bg-success rounded-pill px-2 py-1"><i class="bi bi-patch-check-fill me-1"></i> Terverifikasi</span>
                        <?php else: ?>
                            <span class="badge bg-secondary rounded-pill px-2 py-1">Pending Verifikasi</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="fw-bold mb-0"><?= e($store['name']) ?></h3>
                    <small class="opacity-75"><i class="bi bi-geo-alt me-1"></i><?= e($store['operational_hours'] ?? 'Buka 08:00 - 20:00') ?> • Penanggung Jawab: <strong><?= e($user['name']) ?></strong></small>
                </div>
            </div>
            <div class="d-flex flex-column align-items-md-end gap-2">
                <span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill small">
                    <i class="bi bi-telephone-fill me-1"></i> <?= e($store['phone']) ?>
                </span>
                <div class="d-flex flex-wrap gap-2 mt-1">
                    <button type="button" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm text-primary fw-semibold" onclick="document.getElementById('smartbot-open-btn')?.click()">
                        <i class="bi bi-robot me-1"></i> Asisten AI Toko
                    </button>
                    <?php if ($isOwner): ?>
                        <a href="<?= url('store/profile') ?>" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm text-primary fw-semibold">
                            <i class="bi bi-gear-fill me-1"></i> Profil Toko
                        </a>
                        <a href="<?= url('store/services') ?>" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm text-primary fw-semibold">
                            <i class="bi bi-tag-fill me-1"></i> Layanan
                        </a>
                        <a href="<?= url('store/couriers') ?>" class="btn btn-sm btn-light rounded-pill px-3 shadow-sm text-primary fw-semibold">
                            <i class="bi bi-truck me-1"></i> Armada Kurir
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <!-- 1. Summary Cards (Role Scoped: Omset only visible to Owner Toko) -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Pesanan Hari Ini</span>
                    <div class="bg-primary-subtle text-primary rounded-3 p-2"><i class="bi bi-calendar-check fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= (int)$stats['orders_today'] ?> <span class="fs-6 text-muted fw-normal">Order</span></h3>
                <small class="text-primary fw-semibold">Masuk Hari Ini</small>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Perlu Konfirmasi</span>
                    <div class="bg-warning-subtle text-warning rounded-3 p-2"><i class="bi bi-inbox-fill fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= (int)$stats['orders_pending'] ?> <span class="fs-6 text-muted fw-normal">Order</span></h3>
                <small class="text-warning fw-semibold"><i class="bi bi-exclamation-circle me-1"></i> Pesanan Baru</small>
            </div>
        </div>

        <?php if ($isOwner): ?>
            <!-- Omset Toko (Owner Toko Only) -->
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Omset Bulan Ini</span>
                        <div class="bg-success-subtle text-success rounded-3 p-2"><i class="bi bi-cash-stack fs-5"></i></div>
                    </div>
                    <h3 class="fw-extrabold text-success mb-1"><?= format_rupiah($stats['monthly_revenue']) ?></h3>
                    <small class="text-muted">Pendapatan Bersih Toko</small>
                </div>
            </div>

            <!-- Rating Toko -->
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Rating Kepuasan</span>
                        <div class="bg-warning-subtle text-warning rounded-3 p-2"><i class="bi bi-star-fill fs-5"></i></div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-1"><?= number_format($stats['rating_avg'], 1) ?> <span class="fs-6 text-muted fw-normal">/ 5.0</span></h3>
                    <small class="text-muted"><?= (int)$stats['rating_count'] ?> Ulasan Pelanggan</small>
                </div>
            </div>
        <?php else: ?>
            <!-- Admin Toko / Operator Operational Cards (No financial exposure) -->
            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Antrean Timbang</span>
                        <div class="bg-info-subtle text-info rounded-3 p-2"><i class="bi bi-speedometer fs-5"></i></div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-1"><?= count($weighingQueue) ?> <span class="fs-6 text-muted fw-normal">Cucian</span></h3>
                    <small class="text-info fw-semibold">Siap Ditimbang</small>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Dalam Pencucian</span>
                        <div class="bg-primary-subtle text-primary rounded-3 p-2"><i class="bi bi-water fs-5"></i></div>
                    </div>
                    <h3 class="fw-extrabold text-dark mb-1"><?= count($washingQueue) ?> <span class="fs-6 text-muted fw-normal">Order</span></h3>
                    <small class="text-primary fw-semibold">Mesin Aktif</small>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- AI Chatbot Assistant Banner for Store Admin -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-left: 5px solid #0d6efd !important;">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                        <i class="bi bi-robot fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            Asisten Pintar Toko <?= e($store['name']) ?>
                            <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0 fs-8">AI Assistant</span>
                        </h6>
                        <p class="text-muted small mb-0">Tanyakan pesanan hari ini, antrean cucian, omzet toko, performa layanan, atau status armada kurir Anda.</p>
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold d-flex align-items-center gap-2" onclick="document.getElementById('smartbot-open-btn')?.click()">
                        <i class="bi bi-chat-dots-fill"></i> Buka Chatbot
                    </button>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top border-light-subtle">
                <span class="small text-muted fw-semibold align-self-center me-1"><i class="bi bi-lightning-charge-fill text-warning"></i> Coba tanya:</span>
                <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-3 py-1 fs-8" onclick="if (window.SmartBotWidget) { window.SmartBotWidget.openWithPrompt('Berapa pesanan hari ini?'); } else { document.getElementById('smartbot-open-btn')?.click(); }">
                    📦 Berapa pesanan hari ini?
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fs-8" onclick="if (window.SmartBotWidget) { window.SmartBotWidget.openWithPrompt('Berapa omzet toko bulan ini?'); } else { document.getElementById('smartbot-open-btn')?.click(); }">
                    💰 Berapa omzet toko bulan ini?
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fs-8" onclick="if (window.SmartBotWidget) { window.SmartBotWidget.openWithPrompt('Layanan apa yang paling laris?'); } else { document.getElementById('smartbot-open-btn')?.click(); }">
                    ⭐ Layanan apa yang paling laris?
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fs-8" onclick="if (window.SmartBotWidget) { window.SmartBotWidget.openWithPrompt('Pesanan apa saja yang belum selesai?'); } else { document.getElementById('smartbot-open-btn')?.click(); }">
                    ⏳ Pesanan apa saja yang belum selesai?
                </button>
                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3 py-1 fs-8" onclick="if (window.SmartBotWidget) { window.SmartBotWidget.openWithPrompt('Kurir mana yang sedang mengantar pesanan?'); } else { document.getElementById('smartbot-open-btn')?.click(); }">
                    🛵 Kurir mana yang sedang mengantar pesanan?
                </button>
            </div>
        </div>
    </div>

    <!-- 2. Section: Pesanan Masuk (Menunggu Konfirmasi Toko) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-bell-fill text-warning me-2"></i>Pesanan Masuk Perlu Konfirmasi</h5>
                <p class="text-muted small mb-0">Pesanan baru dari customer yang memerlukan persetujuan toko sebelum kurir ditugaskan.</p>
            </div>
            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold">
                <?= count($pendingOrders) ?> Pesanan Baru
            </span>
        </div>

        <?php if (empty($pendingOrders)): ?>
            <div class="text-center py-4 bg-light rounded-4 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                Tidak ada pesanan baru yang menunggu konfirmasi saat ini.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small text-uppercase">
                            <th>Kode Order</th>
                            <th>Customer & Kontak</th>
                            <th>Alamat Penjemputan</th>
                            <th>Layanan Diminta</th>
                            <th>Jadwal Jemput</th>
                            <th class="text-end">Aksi Konfirmasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingOrders as $po): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= e($po['order_code']) ?></span>
                                    <div class="small text-muted mt-1"><?= date('H:i, d M', strtotime($po['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($po['customer_name']) ?></div>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($po['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <div class="small text-dark text-truncate" style="max-width: 200px;"><?= e($po['customer_address']) ?></div>
                                    <small class="text-muted"><?= e($po['notes'] ?? 'Tidak ada catatan khusus') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary"><?= e($po['item_summary'] ?? 'Paket Standar') ?></span>
                                </td>
                                <td>
                                    <span class="small fw-semibold text-dark"><?= format_datetime_id($po['scheduled_pickup_at']) ?></span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <form action="<?= url('store/orders/' . $po['id'] . '/accept') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                                <i class="bi bi-check-lg me-1"></i> Terima
                                            </button>
                                        </form>
                                        <form action="<?= url('store/orders/' . $po['id'] . '/reject') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Apakah Anda yakin ingin menolak pesanan ini?');">
                                                <i class="bi bi-x-lg me-1"></i> Tolak
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2.5 Section for Expired Payments & Laundry Returns (Tahap 11) -->
    <?php if (!empty($expiredQueue) || !empty($refundsQueue)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-danger bg-opacity-10 border border-danger">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-danger p-2 rounded-circle"><i class="bi bi-exclamation-triangle-fill text-white fs-5"></i></span>
                    <div>
                        <h5 class="fw-bold text-danger mb-0">Perhatian: Pengembalian Laundry & Pembayaran Kadaluarsa</h5>
                        <small class="text-muted">Terdapat <?= count($expiredQueue) ?> pesanan kadaluarsa yang membutuhkan persetujuan pengembalian dan <?= count($refundsQueue) ?> dalam proses pengembalian.</small>
                    </div>
                </div>
                <a href="<?= url('store/refunds') ?>" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold shadow-sm">
                    <i class="bi bi-arrow-right-circle me-1"></i> Buka Kelola Pengembalian
                </a>
            </div>

            <?php if (!empty($expiredQueue)): ?>
                <div class="table-responsive bg-white rounded-3 p-2 shadow-sm border border-danger-subtle">
                    <table class="table table-sm align-middle table-hover mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Kode Order</th>
                                <th>Pelanggan</th>
                                <th>Alamat</th>
                                <th>Timbangan</th>
                                <th>Waktu Expired</th>
                                <th class="text-end">Aksi Tindak Lanjut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiredQueue as $eq): ?>
                                <tr>
                                    <td><strong class="text-primary">#<?= e($eq['order_code']) ?></strong></td>
                                    <td>
                                        <div class="fw-semibold small"><?= e($eq['customer_name']) ?></div>
                                        <small class="text-muted"><?= e($eq['customer_phone'] ?? '-') ?></small>
                                    </td>
                                    <td><small class="text-truncate d-inline-block" style="max-width: 180px;"><?= e($eq['customer_address']) ?></small></td>
                                    <td><span class="badge bg-light text-dark border"><?= !empty($eq['weight_actual']) ? $eq['weight_actual'] . ' kg' : '-' ?></span></td>
                                    <td><small class="text-danger fw-bold"><?= !empty($eq['expired_at']) ? date('d M H:i', strtotime($eq['expired_at'])) : '-' ?></small></td>
                                    <td class="text-end">
                                        <a href="<?= url('store/orders/' . $eq['id'] . '/refund-approval') ?>" class="btn btn-xs btn-warning text-dark rounded-pill px-3 fw-bold">
                                            <i class="bi bi-box-arrow-in-right me-1"></i> Proses Pengembalian
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 3. Section for Admin Toko: Antrean Penimbangan & Konfirmasi Pembayaran -->
    <div class="row g-4 mb-4">
        <!-- Antrean Penimbangan -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-speedometer2 text-info me-2"></i>Antrean Penimbangan Laundry</h5>
                    <span class="badge bg-info-subtle text-info rounded-pill px-3 py-1"><?= count($weighingQueue) ?> Antrean</span>
                </div>
                <p class="text-muted small mb-3">Laundry yang telah tiba di toko. Masukkan berat riil (kg) dan helai pakaian untuk menerbitkan tagihan QRIS otomatis.</p>

                <?php if (empty($weighingQueue)): ?>
                    <div class="text-center py-4 bg-light rounded-4 text-muted small">
                        <i class="bi bi-check2-circle fs-3 d-block mb-1 text-success"></i>
                        Semua pakaian yang sampai telah selesai ditimbang.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($weighingQueue as $wq): ?>
                            <div class="border rounded-4 p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong class="text-dark">#<?= e($wq['order_code']) ?></strong> • <?= e($wq['customer_name']) ?>
                                        <div class="small text-muted"><?= e($wq['service_names'] ?? 'Kiloan') ?></div>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?= str_replace('_', ' ', $wq['status']) ?></span>
                                </div>

                                <form action="<?= url('store/orders/' . $wq['id'] . '/weigh') ?>" method="POST">
                                    <?= csrf_field() ?>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="form-label fs-8 fw-semibold mb-1">Berat Ril (Kg) *</label>
                                            <input type="number" step="0.1" min="0.1" name="weight_actual" class="form-control form-control-sm rounded-3" placeholder="Contoh: 3.5" value="<?= $wq['weight_actual'] ?? '' ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fs-8 fw-semibold mb-1">Jumlah Helai (Pcs)</label>
                                            <input type="number" min="1" name="pieces_count" class="form-control form-control-sm rounded-3" placeholder="Contoh: 14" value="<?= $wq['pieces_count'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="notes_from_store" class="form-control form-control-sm rounded-3" placeholder="Catatan kondisi (misal: ada noda teh di kemeja putih)" value="<?= e($wq['notes_from_store'] ?? '') ?>">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill fw-semibold">
                                        <i class="bi bi-calculator me-1"></i> Simpan Hasil Timbang & Terbitkan Tagihan QRIS
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Konfirmasi Pembayaran & Update Status Pengerjaan -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-qr-code-scan text-success me-2"></i>Konfirmasi Bayar & Cuci</h5>
                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-1"><?= count($paymentQueue) ?> Menunggu</span>
                </div>
                <p class="text-muted small mb-3">Verifikasi pembayaran tunai / simulasi pelunasan QRIS untuk memulai pencucian.</p>

                <?php if (empty($paymentQueue)): ?>
                    <div class="text-center py-4 bg-light rounded-4 text-muted small">
                        <i class="bi bi-credit-card-2-front fs-3 d-block mb-1 text-muted"></i>
                        Tidak ada tagihan yang menunggu verifikasi saat ini.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($paymentQueue as $pq): ?>
                            <div class="border rounded-4 p-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <strong class="text-dark">#<?= e($pq['order_code']) ?></strong> • <?= e($pq['customer_name']) ?>
                                        <div class="small text-muted">Berat: <?= $pq['weight_actual'] ?? '0' ?> kg</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-success fs-6"><?= format_rupiah($pq['payment_amount']) ?></div>
                                        <span class="badge bg-warning text-dark rounded-pill fs-8">QRIS (1 Jam)</span>
                                    </div>
                                </div>
                                <div class="d-flex flex-column gap-2 pt-1">
                                    <?php if (($pq['payment_method_type'] ?? 'ONLINE_QRIS') === 'ONLINE_QRIS' && ($pq['payment_status'] ?? '') !== 'PAID'): ?>
                                        <!-- QRIS: Simulasi Bayar (store admin trigger) -->
                                        <form action="<?= url('payment/' . $pq['id'] . '/simulate-paid') ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-success w-100 rounded-pill fw-semibold">
                                                <i class="bi bi-qr-code me-1"></i> Tandai Lunas QRIS
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Cash: Manual confirm -->
                                        <form action="<?= url('store/orders/' . $pq['id'] . '/confirm-payment') ?>" method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="method" value="CASH">
                                            <button type="submit" class="btn btn-sm btn-warning text-dark w-100 rounded-pill fw-semibold">
                                                <i class="bi bi-cash me-1"></i> Konfirmasi Terima Tunai
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= url('order/' . $pq['id'] . '/invoice') ?>" target="_blank" class="btn btn-sm btn-outline-secondary w-100 rounded-pill">
                                        <i class="bi bi-file-earmark-text me-1"></i> Lihat Invoice
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Active Washing Queue Quick Updates & WhatsApp Notification -->
                <?php if (!empty($washingQueue)): ?>
                    <hr class="my-3">
                    <h6 class="fw-bold text-dark fs-7 mb-2">
                        <i class="bi bi-arrow-repeat text-primary me-1"></i>Pembaruan Status & Kirim Notifikasi WA (<?= count($washingQueue) ?> Order Aktif)
                    </h6>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach (array_slice($washingQueue, 0, 5) as $wq): ?>
                            <div class="d-flex justify-content-between align-items-center bg-white border rounded-3 p-2 flex-wrap gap-2">
                                <div>
                                    <small class="fw-bold text-dark">#<?= e($wq['order_code']) ?></small>
                                    <span class="badge bg-primary-subtle text-primary ms-1 fs-8"><?= str_replace('_', ' ', $wq['status']) ?></span>
                                    <div class="small text-muted fs-8"><?= e($wq['customer_name']) ?> (<?= e($wq['customer_phone'] ?? '-') ?>)</div>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <!-- Form Update Status (otomatis kirim WA ke pelanggan) -->
                                    <form action="<?= url('store/orders/' . $wq['id'] . '/update-status') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <?php if ($wq['status'] === 'PEMBAYARAN_BERHASIL'): ?>
                                            <input type="hidden" name="new_status" value="SEDANG_DICUCI">
                                            <button type="submit" class="btn btn-xs btn-primary rounded-pill px-2 py-1 fs-8" title="Mulai cuci dan kirim notifikasi WhatsApp ke customer">
                                                <i class="bi bi-play-fill me-1"></i>Mulai Cuci
                                            </button>
                                        <?php elseif ($wq['status'] === 'SEDANG_DICUCI'): ?>
                                            <input type="hidden" name="new_status" value="SEDANG_DIPROSES">
                                            <button type="submit" class="btn btn-xs btn-info rounded-pill px-2 py-1 fs-8 text-white" title="Mulai setrika uap & packing dan kirim notifikasi WhatsApp">
                                                <i class="bi bi-arrow-right-circle me-1"></i>Setrika/Proses
                                            </button>
                                        <?php elseif ($wq['status'] === 'SEDANG_DIPROSES'): ?>
                                            <input type="hidden" name="new_status" value="SIAP_DIANTAR">
                                            <button type="submit" class="btn btn-xs btn-success rounded-pill px-2 py-1 fs-8" title="Tandai siap diantar dan kirim notifikasi WhatsApp">
                                                <i class="bi bi-box-seam me-1"></i>Siap Diantar
                                            </button>
                                        <?php elseif ($wq['status'] === 'SIAP_DIANTAR'): ?>
                                            <input type="hidden" name="new_status" value="SELESAI">
                                            <button type="submit" class="btn btn-xs btn-dark rounded-pill px-2 py-1 fs-8" onclick="return confirm('Selesaikan pesanan #<?= e($wq['order_code']) ?>? Notifikasi WA selesai akan dikirim ke customer.');">
                                                <i class="bi bi-check2-circle me-1"></i>Selesaikan
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Tombol Kirim Ulang WA Cepat via Fonnte -->
                                    <form action="<?= url('store/orders/' . $wq['id'] . '/send-whatsapp') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="process_type" value="AUTO">
                                        <button type="submit" class="btn btn-xs btn-outline-success rounded-pill px-2 py-1 fs-8" title="Kirim notifikasi WhatsApp status saat ini via Fonnte">
                                            <i class="bi bi-whatsapp"></i> WA
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 4. Section: Performa Layanan Terlaris (Khusus Owner Toko) -->
    <?php if ($isOwner && !empty($topServices)): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Performa Layanan Laundry Terlaris</h5>
                    <p class="text-muted small mb-0">Statistik layanan paling banyak dipesan dan kontribusi pendapatan toko Anda.</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small text-uppercase">
                            <th>Nama Layanan</th>
                            <th>Kategori</th>
                            <th>Tarif Dasar</th>
                            <th>Total Kuantitas Terjual</th>
                            <th>Total Omset</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topServices as $ts): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($ts['name']) ?></td>
                                <td><span class="badge bg-light text-dark border text-uppercase"><?= e($ts['category']) ?></span></td>
                                <td>Rp <?= number_format($ts['price'], 0, ',', '.') ?> /<?= e($ts['unit']) ?></td>
                                <td><span class="fw-bold text-primary"><?= (float)$ts['total_quantity'] ?></span> <?= e($ts['unit']) ?> (<?= $ts['orders_count'] ?> order)</td>
                                <td class="fw-bold text-success"><?= format_rupiah($ts['total_sales']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- 5. Section: Live Fleet Monitor (Peta Armada Kurir Real-Time - Tahap 9) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-radar text-primary me-2"></i>Live Fleet Monitor — Posisi Armada Kurir</h5>
                <p class="text-muted small mb-0">Pantau koordinat GPS dan status operasional seluruh kurir yang bertugas untuk <strong><?= e($store['name']) ?></strong>.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 fs-8">
                    <i class="bi bi-circle-fill text-success fs-9 me-1"></i> Multi-Kurir Live GPS
                </span>
                <small id="storeFleetTime" class="text-muted fs-8"></small>
            </div>
        </div>
        <div id="storeFleetMap" class="rounded-4 border shadow-inner mb-2" style="height: 320px; width: 100%;"></div>
        <small class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Peta otomatis me-refresh koordinat kurir setiap 4 detik. Klik marker kurir untuk melihat kontak dan status tugas aktifnya.</small>
    </div>

    <!-- 6. Section: Daftar Semua Pesanan Toko -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-list-check text-primary me-2"></i>Daftar Seluruh Pesanan Toko</h5>
                <p class="text-muted small mb-0">Pantau seluruh siklus pesanan dari penjemputan hingga selesai diantar.</p>
            </div>
            <!-- Filter Tabs -->
            <div class="btn-group rounded-pill p-1 bg-light">
                <a href="<?= url('dashboard/store?status=all') ?>" class="btn btn-sm rounded-pill <?= $statusFilter === 'all' ? 'btn-primary shadow-sm' : 'btn-light text-muted' ?>">Semua</a>
                <a href="<?= url('dashboard/store?status=active') ?>" class="btn btn-sm rounded-pill <?= $statusFilter === 'active' ? 'btn-primary shadow-sm' : 'btn-light text-muted' ?>">Aktif</a>
                <a href="<?= url('dashboard/store?status=completed') ?>" class="btn btn-sm rounded-pill <?= $statusFilter === 'completed' ? 'btn-primary shadow-sm' : 'btn-light text-muted' ?>">Selesai</a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>Kode Order</th>
                        <th>Customer</th>
                        <th>Layanan & Rincian</th>
                        <th>Berat Timbang</th>
                        <th>Total Biaya</th>
                        <th>Status Pembayaran</th>
                        <th>Status Laundry</th>
                        <th class="text-center">Kirim Notifikasi WA (Fonnte)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($storeOrders)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada data pesanan pada filter ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($storeOrders as $so): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= e($so['order_code']) ?></span>
                                    <div class="small text-muted mt-1"><?= date('d/m/Y H:i', strtotime($so['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($so['customer_name']) ?></div>
                                    <small class="text-muted"><?= e($so['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <span class="small text-dark"><?= e($so['item_summary'] ?? 'Paket Kiloan') ?></span>
                                </td>
                                <td>
                                    <?php if ($so['weight_actual']): ?>
                                        <span class="badge bg-info-subtle text-info fw-bold"><?= $so['weight_actual'] ?> kg (<?= (int)$so['pieces_count'] ?> pcs)</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Belum Ditimbang</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?= format_rupiah($so['total_price']) ?></span>
                                </td>
                                <td>
                                    <?php if ($so['payment_status'] === 'PAID'): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">LUNAS</span>
                                    <?php elseif ($so['payment_status'] === 'PENDING'): ?>
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1">MENUNGGU</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted rounded-pill px-2 py-1">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $sClass = 'bg-primary-subtle text-primary';
                                    if ($so['status'] === 'SELESAI') $sClass = 'bg-success-subtle text-success';
                                    elseif ($so['status'] === 'MENUNGGU_KONFIRMASI') $sClass = 'bg-warning-subtle text-warning';
                                    elseif ($so['status'] === 'DIBATALKAN') $sClass = 'bg-danger-subtle text-danger';
                                    ?>
                                    <span class="badge <?= $sClass ?> rounded-pill px-3 py-1">
                                        <?= str_replace('_', ' ', $so['status']) ?>
                                    </span>

                                    <!-- Status Penugasan Kurir (Tahap 12) -->
                                    <?php if (!empty($so['courier_assignment_status'])): ?>
                                        <div class="mt-1">
                                            <?php if ($so['courier_assignment_status'] === 'MENUNGGU_RESPON'): ?>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill fs-8">
                                                    <i class="bi bi-hourglass-split me-1"></i> Menunggu Respon Kurir
                                                </span>
                                            <?php elseif ($so['courier_assignment_status'] === 'DITERIMA'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-8">
                                                    <i class="bi bi-person-check-fill me-1"></i> Kurir Siap
                                                </span>
                                            <?php elseif ($so['courier_assignment_status'] === 'DITOLAK'): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill fs-8">
                                                    <i class="bi bi-person-x-fill me-1"></i> Kurir Menolak
                                                </span>
                                            <?php elseif ($so['courier_assignment_status'] === 'TIDAK_ADA_KURIR'): ?>
                                                <span class="badge bg-danger text-white rounded-pill fs-8 mb-1 d-inline-block">
                                                    <i class="bi bi-exclamation-triangle me-1"></i> Tidak Ada Kurir
                                                </span>
                                                <!-- Tombol Assign Manual -->
                                                <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2 py-0 fs-8 d-block" data-bs-toggle="modal" data-bs-target="#modalManualAssign<?= $so['id'] ?>">
                                                    Tugaskan Manual
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Modal Manual Assign -->
                                        <?php if ($so['courier_assignment_status'] === 'TIDAK_ADA_KURIR' || $so['courier_assignment_status'] === 'DITOLAK'): ?>
                                            <div class="modal fade" id="modalManualAssign<?= $so['id'] ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content rounded-4 border-0 shadow">
                                                        <div class="modal-header border-0 pb-0">
                                                            <h6 class="modal-title fw-bold text-dark">Penugasan Manual Kurir #<?= e($so['order_code']) ?></h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form action="<?= url('store/orders/' . $so['id'] . '/manual-assign-courier') ?>" method="POST">
                                                            <?= csrf_field() ?>
                                                            <div class="modal-body">
                                                                <p class="small text-muted mb-3">Pilih kurir armada Anda untuk ditugaskan secara langsung tanpa proses penawaran otomatis.</p>
                                                                <div class="mb-3">
                                                                    <label class="form-label small fw-semibold">Pilih Kurir Armada Toko</label>
                                                                    <select name="courier_user_id" class="form-select form-select-sm rounded-3" required>
                                                                        <option value="">-- Pilih Kurir --</option>
                                                                        <?php foreach ($storeCouriers as $sc): ?>
                                                                            <option value="<?= (int)$sc['user_id'] ?>">
                                                                                <?= e($sc['courier_name']) ?> (<?= e($sc['vehicle_type']) ?> - <?= e($sc['license_plate']) ?>) <?= $sc['is_available'] ? '• Online' : '• Offline' ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer border-0 pt-0">
                                                                <button type="button" class="btn btn-sm btn-light rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-bold">Tugaskan Kurir</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <!-- Dropdown Kirim Notifikasi WhatsApp Semua Proses -->
                                    <div class="dropdown">
                                        <button class="btn btn-xs btn-success rounded-pill px-3 py-1 dropdown-toggle fw-semibold shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-whatsapp me-1"></i> Kirim WA
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2 fs-8" style="min-width: 270px;">
                                            <li><h6 class="dropdown-header text-dark fw-bold px-2 py-1"><i class="bi bi-broadcast text-success me-1"></i>Kirim Notifikasi Proses Order:</h6></li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            
                                            <!-- 1. Auto sesuai status saat ini -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="AUTO">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2 fw-semibold text-primary">
                                                        <i class="bi bi-arrow-repeat me-1"></i> Status Terkini (<?= str_replace('_', ' ', $so['status']) ?>)
                                                    </button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>

                                            <!-- 2. Penimbangan Selesai (Tagihan) -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="WEIGHING">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2" <?= empty($so['weight_actual']) ? 'disabled title="Cucian belum ditimbang"' : '' ?>>
                                                        <i class="bi bi-speedometer2 text-info me-1"></i> 1. Tagihan Penimbangan
                                                    </button>
                                                </form>
                                            </li>

                                            <!-- 3. Pembayaran Lunas -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="PAYMENT_SUCCESS">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2">
                                                        <i class="bi bi-cash-stack text-success me-1"></i> 2. Pembayaran Lunas
                                                    </button>
                                                </form>
                                            </li>

                                            <!-- 4. Sedang Dicuci Mesin -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="WASHING">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2">
                                                        <i class="bi bi-water text-primary me-1"></i> 3. Sedang Dicuci Mesin
                                                    </button>
                                                </form>
                                            </li>

                                            <!-- 5. Sedang Disetrika & Dipacking -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="PROCESSING">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2">
                                                        <i class="bi bi-magic text-warning me-1"></i> 4. Setrika & Packing Wangi
                                                    </button>
                                                </form>
                                            </li>

                                            <!-- 6. Siap Diantar -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="READY">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2">
                                                        <i class="bi bi-box-seam text-success me-1"></i> 5. Siap Diantar / Selesai Cuci
                                                    </button>
                                                </form>
                                            </li>

                                            <!-- 7. Kurir Mengantar -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="DELIVERY">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2">
                                                        <i class="bi bi-truck text-secondary me-1"></i> 6. Kurir Menuju Lokasi Antar
                                                    </button>
                                                </form>
                                            </li>

                                            <!-- 8. Laundry Diserahkan -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="DELIVERED">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2">
                                                        <i class="bi bi-house-check text-info me-1"></i> 7. Laundry Telah Diantar
                                                    </button>
                                                </form>
                                            </li>

                                            <!-- 9. Pesanan Selesai -->
                                            <li>
                                                <form action="<?= url('store/orders/' . $so['id'] . '/send-whatsapp') ?>" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="process_type" value="COMPLETED">
                                                    <button type="submit" class="dropdown-item rounded-3 py-1 px-2 text-success fw-bold">
                                                        <i class="bi bi-star-fill text-warning me-1"></i> 8. Pesanan Selesai (Ulasan)
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Store Fleet Multi-Marker Leaflet Script (Tahap 9) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapEl = document.getElementById('storeFleetMap');
    if (!mapEl) return;

    const initialLat = -6.20000000;
    const initialLng = 106.81666600;

    const map = L.map('storeFleetMap').setView([initialLat, initialLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const courierMarkers = {};

    function fetchFleetLocations() {
        fetch('<?= url('store/couriers/live-locations') ?>')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.couriers)) {
                    const boundsGroup = [];

                    data.couriers.forEach(c => {
                        const lat = parseFloat(c.current_lat);
                        const lng = parseFloat(c.current_lng);
                        const cId = c.courier_id;

                        const isAvailable = parseInt(c.is_available) === 1;
                        const bgColor = isAvailable ? '#198754' : '#6c757d';

                        const courierIcon = L.divIcon({
                            className: 'store-courier-pin',
                            html: '<div style="background-color: ' + bgColor + '; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); border: 2px solid white;"><i class="bi bi-truck" style="font-size: 16px;"></i></div>',
                            iconSize: [34, 34],
                            iconAnchor: [17, 17]
                        });

                        const popupContent = '<div class="p-1">' +
                            '<strong>' + c.courier_name + '</strong> (' + c.vehicle_type + ')<br>' +
                            '<span class="badge bg-dark text-white font-monospace">' + c.license_plate + '</span><br>' +
                            '<small class="text-muted">Status: ' + (isAvailable ? 'Tersedia' : 'Offline') + '</small><br>' +
                            (c.active_order_id ? '<span class="badge bg-primary mt-1">Bertugas Order #' + c.active_order_id + '</span>' : '<span class="badge bg-light text-dark border mt-1">Standby</span>') +
                            '</div>';

                        if (!courierMarkers[cId]) {
                            courierMarkers[cId] = L.marker([lat, lng], { icon: courierIcon })
                                .addTo(map)
                                .bindPopup(popupContent);
                        } else {
                            courierMarkers[cId].setLatLng([lat, lng]);
                            courierMarkers[cId].setIcon(courierIcon);
                            courierMarkers[cId].setPopupContent(popupContent);
                        }

                        boundsGroup.push(courierMarkers[cId]);
                    });

                    if (boundsGroup.length > 0) {
                        const group = new L.featureGroup(boundsGroup);
                        map.fitBounds(group.getBounds().pad(0.2));
                    }

                    const timeEl = document.getElementById('storeFleetTime');
                    if (timeEl) {
                        timeEl.innerText = 'Update: ' + new Date().toLocaleTimeString('id-ID') + ' WIB';
                    }
                }
            })
            .catch(err => console.log('Fleet tracking polling:', err));
    }

    // Initial fetch
    fetchFleetLocations();

    // Periodic polling
    const fleetIntervalMs = <?= (int)\App\Models\Setting::get('tracking_poll_interval_seconds', 10) * 1000 ?>;
    const fleetInterval = setInterval(fetchFleetLocations, fleetIntervalMs);
    window.addEventListener('beforeunload', () => clearInterval(fleetInterval));
});
</script>

