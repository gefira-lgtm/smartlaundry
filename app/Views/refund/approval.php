<div class="container py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Persetujuan Pengembalian</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0">Persetujuan Pengembalian Laundry</h3>
            <p class="text-muted small mb-0">Pesanan #<?= e($order['order_code']) ?> — Pembayaran Kadaluarsa</p>
        </div>
        <div>
            <a href="<?= url('dashboard/store') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Kolom Kiri: Detail Pesanan & Customer -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                    <h5 class="fw-bold mb-0"><i class="bi bi-info-circle-fill text-primary me-2"></i>Informasi Pesanan & Customer</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <div class="p-3 bg-danger-subtle rounded-3 text-danger mb-4 border border-danger-subtle">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <strong>Status: PEMBAYARAN KADALUARSA</strong>
                        </div>
                        <small class="d-block">Batas waktu pelunasan QRIS 1 jam telah terlewati tanpa pembayaran. Pakaian yang sudah di outlet harus dikembalikan ke customer.</small>
                    </div>

                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Kode Pesanan</span>
                            <span class="fw-bold text-primary">#<?= e($order['order_code']) ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Nama Pelanggan</span>
                            <span class="fw-semibold"><?= e($order['customer_name']) ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Nomor WhatsApp / HP</span>
                            <span><a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? '') ?>" target="_blank" class="text-success text-decoration-none fw-semibold"><i class="bi bi-whatsapp me-1"></i><?= e($order['customer_phone'] ?? '-') ?></a></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Alamat Pengembalian</span>
                            <span class="fw-semibold text-end" style="max-width: 60%;"><?= e($order['pickup_address_full']) ?></span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Berat Timbangan Aktual</span>
                            <span class="fw-bold text-dark"><?= !empty($order['weight_actual']) ? number_format($order['weight_actual'], 2) . ' kg' : '-' ?> (<?= (int)($order['pieces_count'] ?? 0) ?> pcs)</span>
                        </li>
                        <li class="list-group-item px-0 d-flex justify-content-between">
                            <span class="text-muted">Total Tagihan Batal</span>
                            <span class="text-decoration-line-through text-muted"><?= format_rupiah($order['total_price']) ?></span>
                        </li>
                    </ul>

                    <?php if (!empty($order['notes_from_store'])): ?>
                        <div class="mt-3 p-3 bg-light rounded-3 small">
                            <strong>Catatan Penimbangan Sebelumnya:</strong>
                            <p class="mb-0 text-muted mt-1"><?= nl2br(e($order['notes_from_store'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Form Approval Pengembalian -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                    <h5 class="fw-bold mb-0"><i class="bi bi-truck-flatbed text-warning me-2"></i>Konfigurasi Pengembalian</h5>
                </div>
                <div class="card-body px-4 pb-4">
                    <form action="<?= url('store/orders/' . $order['id'] . '/refund-approve') ?>" method="POST" id="formApproveRefund">
                        <?= csrf_field() ?>

                        <!-- 1. Biaya Pengembalian (Input Manual Fleksibel) -->
                        <div class="mb-3">
                            <label for="refund_fee" class="form-label fw-semibold">Biaya Ongkir Pengembalian (Rp) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">Rp</span>
                                <input type="number" class="form-control border-start-0" id="refund_fee" name="refund_fee" value="<?= (float)$order['delivery_fee'] ?>" min="0" step="500" required>
                            </div>
                            <small class="text-muted">Dapat diisi <code>0</code> jika bebas ongkir atau disesuaikan dengan tarif standar antar.</small>
                        </div>

                        <!-- 2. Pihak Penanggung Biaya -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pihak yang Menanggung Biaya <span class="text-danger">*</span></label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check p-3 border rounded-3 bg-light">
                                    <input class="form-check-input" type="radio" name="refund_fee_bearer" id="bearer_customer" value="customer">
                                    <label class="form-check-label fw-semibold" for="bearer_customer">
                                        Ditanggung Pelanggan (Customer)
                                        <small class="d-block text-muted fw-normal">Pelanggan membayar ongkir pengembalian secara tunai (COD) ke kurir saat barang tiba.</small>
                                    </label>
                                </div>
                                <div class="form-check p-3 border rounded-3 bg-light">
                                    <input class="form-check-input" type="radio" name="refund_fee_bearer" id="bearer_store" value="store">
                                    <label class="form-check-label fw-semibold" for="bearer_store">
                                        Ditanggung Outlet Toko (Store)
                                        <small class="d-block text-muted fw-normal">Outlet menanggung ongkos kurir sebagai kompensasi operasional.</small>
                                    </label>
                                </div>
                                <div class="form-check p-3 border rounded-3 bg-light">
                                    <input class="form-check-input" type="radio" name="refund_fee_bearer" id="bearer_free" value="free" checked>
                                    <label class="form-check-label fw-semibold" for="bearer_free">
                                        Bebas Biaya (Gratis / Internal)
                                        <small class="d-block text-muted fw-normal">Tidak ada pungutan ongkir pengembalian yang ditagihkan.</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Penugasan Kurir Armada -->
                        <div class="mb-3">
                            <label for="courier_user_id" class="form-label fw-semibold">Tugaskan Kurir Pengembalian</label>
                            <select class="form-select" id="courier_user_id" name="courier_user_id">
                                <option value="">-- Pilih Kurir Outlet --</option>
                                <?php foreach ($couriers as $cr): ?>
                                    <option value="<?= (int)$cr['user_id'] ?>" <?= ((int)($order['courier_id'] ?? 0) === (int)$cr['user_id']) ? 'selected' : '' ?>>
                                        <?= e($cr['courier_name']) ?> (<?= e($cr['vehicle_type']) ?> - <?= e($cr['license_plate']) ?>) <?= $cr['is_available'] ? '• [Online]' : '• [Offline]' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Kurir yang dipilih akan menerima tugas dengan label <strong>Tugas Pengembalian</strong> di dashboard kurir.</small>
                        </div>

                        <!-- 4. Catatan untuk Kurir & Customer -->
                        <div class="mb-4">
                            <label for="notes" class="form-label fw-semibold">Catatan Pengembalian (Opsional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Contoh: Pakaian belum dicuci, titip di pos satpam jika tidak ada orang."></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning py-3 fw-bold rounded-pill text-dark shadow-sm">
                                <i class="bi bi-check2-circle me-1 fs-5 align-middle"></i> Setujui & Alihkan ke Armada Kurir
                            </button>
                            <a href="<?= url('dashboard/store') ?>" class="btn btn-light py-2 rounded-pill text-muted">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
