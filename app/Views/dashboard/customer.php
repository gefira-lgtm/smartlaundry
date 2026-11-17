<div class="container py-4">
    <!-- Header Banner -->
    <div class="card border-0 bg-primary-gradient text-white shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
                    <i class="bi bi-person-heart fs-3"></i>
                </div>
                <div>
                    <div class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-1 fw-bold">
                        <?= e($user['role_display'] ?? 'Pelanggan') ?>
                    </div>
                    <h3 class="fw-bold mb-0">Halo, <?= e($user['name']) ?>!</h3>
                    <small class="opacity-75">Pantau status cucian, tracking kurir, dan riwayat pesanan Anda.</small>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= url('customer/addresses') ?>" class="btn btn-outline-light rounded-pill px-3 fw-semibold">
                    <i class="bi bi-geo-alt-fill me-1"></i> Buku Alamat (<?= count($addresses ?? []) ?>)
                </a>
                <a href="<?= url('customer/profile') ?>" class="btn btn-outline-light rounded-pill px-3 fw-semibold">
                    <i class="bi bi-person-gear me-1"></i> Profil Saya
                </a>
                <a href="<?= url('stores') ?>" class="btn btn-warning text-dark rounded-pill px-4 fw-bold shadow-sm">
                    <i class="bi bi-plus-circle-fill me-1"></i> Pesan Laundry Baru
                </a>
            </div>
        </div>
    </div>

    <!-- Profile Completeness Warning Banner (Stage 5) -->
    <?php if (isset($completeness) && !$completeness['is_complete']): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-warning text-dark rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                        <i class="bi bi-shield-exclamation fs-3"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1">Lengkapi Profil & Alamat Utama Anda!</h6>
                        <p class="small text-muted mb-0">
                            Data yang belum lengkap: <strong class="text-danger"><?= implode(', ', $completeness['missing']) ?></strong>. Lengkapi sekarang agar kurir dapat menjemput cucian ke lokasi Anda.
                        </p>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if (empty($addresses)): ?>
                        <a href="<?= url('customer/addresses/create') ?>" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="bi bi-pin-map-fill me-1"></i> Tambah Alamat Sekarang
                        </a>
                    <?php else: ?>
                        <a href="<?= url('customer/profile') ?>" class="btn btn-dark rounded-pill px-4 fw-semibold shadow-sm">
                            <i class="bi bi-person-check-fill me-1"></i> Lengkapi Data
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 1. Pesanan Aktif Section -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-hourglass-split text-primary me-2"></i>Pesanan Anda yang Sedang Berjalan</h5>
                <small class="text-muted">Pelacakan progress real-time dari penjemputan hingga pengantaran.</small>
            </div>
            <span class="badge bg-primary rounded-pill px-3 py-2"><?= count($activeOrders) ?> Pesanan Aktif</span>
        </div>

        <?php if (empty($activeOrders)): ?>
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center bg-white">
                <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center p-4 mx-auto mb-3" style="width: 80px; height: 80px;">
                    <i class="bi bi-bag-check fs-1 text-muted"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">Tidak Ada Pesanan Aktif</h5>
                <p class="text-muted small mb-3">Pakaian kotor mulai menumpuk? Pesan jasa laundry terbaik sekarang!</p>
                <div>
                    <a href="<?= url('stores') ?>" class="btn btn-primary rounded-pill px-4 fw-semibold">
                        <i class="bi bi-shop me-1"></i> Jelajahi Toko Laundry
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-4">
                <?php foreach ($activeOrders as $ao): ?>
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                        <!-- Header Card Pesanan -->
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 border-bottom pb-3 mb-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="badge bg-light text-dark border font-monospace fs-7">#<?= e($ao['order_code']) ?></span>
                                    <strong class="text-dark fs-6"><?= e($ao['store_name']) ?></strong>
                                </div>
                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>Penjemputan: <?= e($ao['pickup_address']) ?></small>
                            </div>
                            <div class="text-end">
                                <?php if ($ao['status'] === 'PEMBAYARAN_KADALUARSA'): ?>
                                    <span class="badge bg-danger text-white rounded-pill px-3 py-2 fw-semibold">
                                        <i class="bi bi-x-circle me-1"></i> Pembayaran Kadaluarsa
                                    </span>
                                <?php elseif ($ao['status'] === 'PROSES_PENGEMBALIAN'): ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-semibold">
                                        <i class="bi bi-arrow-return-left me-1"></i> Pengembalian dalam Proses
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-primary text-white rounded-pill px-3 py-2 fw-semibold">
                                        <?= str_replace('_', ' ', $ao['status']) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($ao['total_price'] > 0): ?>
                                    <div class="fw-bold text-success fs-6 mt-1"><?= format_rupiah($ao['total_price']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Alert Khusus: Pembayaran Kadaluarsa (Tahap 11) -->
                        <?php if ($ao['status'] === 'PEMBAYARAN_KADALUARSA'): ?>
                            <div class="alert alert-danger border-danger rounded-4 p-3 my-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h6 class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i> Pembayaran QRIS Telah Kadaluarsa</h6>
                                        <p class="small text-muted mb-0">Batas waktu pelunasan 1 jam telah terlewati. Cucian Anda sedang dipersiapkan oleh pihak <strong><?= e($ao['store_name']) ?></strong> untuk proses pengembalian ke alamat Anda.</p>
                                    </div>
                                    <a href="tel:<?= preg_replace('/[^0-9+]/', '', $ao['store_phone'] ?? '') ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-semibold">
                                        <i class="bi bi-telephone-fill me-1"></i> Hubungi Toko
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($ao['status'] === 'PROSES_PENGEMBALIAN'): ?>
                            <div class="alert alert-warning border-warning rounded-4 p-3 my-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-truck text-warning me-1"></i> Cucian Sedang Dikembalikan</h6>
                                        <p class="small text-muted mb-0">
                                            Kurir sedang dalam proses mengantarkan pakaian kembali ke alamat Anda.
                                            <?php if (!empty($ao['refund_fee']) && (float)$ao['refund_fee'] > 0): ?>
                                                <span class="d-block mt-1">Biaya pengembalian: <strong><?= format_rupiah($ao['refund_fee']) ?></strong> (Ditanggung: <strong><?= strtoupper(e($ao['refund_fee_bearer'] ?? 'free')) ?></strong>)</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
                                        <i class="bi bi-geo-alt-fill me-1"></i> Pengantaran Balik
                                    </span>
                                </div>
                            </div>
                        <?php elseif ($ao['status'] === 'LAUNDRY_DIANTAR'): ?>
                            <div class="alert alert-success border-success rounded-4 p-3 my-3 shadow-sm">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div>
                                        <h6 class="fw-bold text-success mb-1"><i class="bi bi-box2-heart-fill me-1"></i> Laundry Telah Diantar Kurir! 📦</h6>
                                        <p class="small text-muted mb-0">Kurir telah mengantarkan pakaian bersih Anda. Mohon periksa cucian Anda dan tekan tombol <strong>Terima Laundry</strong> untuk menyelesaikan pesanan.</p>
                                    </div>
                                    <div>
                                        <form action="<?= url('customer/orders/' . $ao['id'] . '/confirm-received') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm" onclick="return confirm('Apakah Anda telah menerima laundry dalam kondisi bersih dan baik?');">
                                                <i class="bi bi-check-circle-fill me-1"></i> Terima Laundry
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Visual Progress Stepper (6 Tahap) -->
                        <div class="my-3 px-2">
                            <div class="d-flex justify-content-between position-relative tracking-stepper">
                                <?php
                                $steps = [
                                    1 => ['title' => 'Menunggu', 'icon' => 'bi-clock'],
                                    2 => ['title' => 'Dijemput', 'icon' => 'bi-person-walking'],
                                    3 => ['title' => 'Ditimbang', 'icon' => 'bi-speedometer2'],
                                    4 => ['title' => 'Dicuci', 'icon' => 'bi-water'],
                                    5 => ['title' => 'Diantar', 'icon' => 'bi-truck'],
                                    6 => ['title' => 'Selesai', 'icon' => 'bi-check-lg']
                                ];
                                $currentStep = $ao['step'];
                                ?>
                                <?php foreach ($steps as $num => $s): ?>
                                    <div class="text-center z-1 stepper-item">
                                        <div class="stepper-circle mx-auto mb-1 rounded-circle d-flex align-items-center justify-content-center shadow-sm <?= $num < $currentStep ? 'bg-success text-white' : ($num === $currentStep ? 'bg-primary text-white animate-pulse' : 'bg-light text-muted border') ?>" style="width: 38px; height: 38px;">
                                            <?php if ($num < $currentStep): ?>
                                                <i class="bi bi-check-lg fw-bold"></i>
                                            <?php else: ?>
                                                <i class="bi <?= $s['icon'] ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="fs-8 fw-semibold <?= $num === $currentStep ? 'text-primary fw-bold' : ($num < $currentStep ? 'text-success' : 'text-muted') ?>">
                                            <?= $s['title'] ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Alert Khusus: Menunggu Pembayaran QRIS (Timer 1 Jam) -->
                        <?php if ($ao['status'] === 'MENUNGGU_PEMBAYARAN'): ?>
                            <div class="alert alert-warning border-warning rounded-4 p-3 my-3">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1"><i class="bi bi-qr-code me-1"></i> Tagihan QRIS Siap Dibayar</h6>
                                        <p class="small text-muted mb-0">Cucian Anda telah selesai ditimbang (<strong><?= $ao['weight_actual'] ?> kg</strong>). Total tagihan: <strong class="text-dark"><?= format_rupiah($ao['total_price']) ?></strong>.</p>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge bg-danger text-white rounded-pill px-3 py-2 fw-bold animate-pulse">
                                            <i class="bi bi-stopwatch me-1"></i> Batas 1 Jam
                                        </span>
                                        <a href="<?= url('order/' . $ao['id'] . '/payment') ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold shadow-sm">
                                            <i class="bi bi-qr-code-scan me-1"></i> Bayar QRIS Sekarang
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Detail Kurir & Live GPS Tracking Map (Tahap 9) -->
                        <?php if (!empty($ao['courier_name'])): ?>
                            <div class="bg-light rounded-4 p-3 my-2 border border-light-subtle">
                                <div class="row align-items-center g-3 mb-3">
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-white text-primary rounded-circle p-2 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                                                <i class="bi bi-truck fs-4"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                                    <?= e($ao['courier_name']) ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill fs-8">
                                                        <i class="bi bi-dot"></i> Kurir Bertugas
                                                    </span>
                                                </div>
                                                <small class="text-muted"><i class="bi bi-bicycle me-1"></i><?= e($ao['vehicle_type'] ?? 'Motor Operasional') ?> • <strong><?= e($ao['license_plate'] ?? '-') ?></strong></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <div class="d-flex flex-wrap justify-content-md-end align-items-center gap-2">
                                            <a href="tel:<?= preg_replace('/[^0-9+]/', '', $ao['courier_phone']) ?>" class="btn btn-sm btn-outline-success rounded-pill px-3">
                                                <i class="bi bi-telephone-fill me-1"></i> Hubungi Kurir
                                            </a>
                                            <a href="https://www.google.com/maps?q=<?= $ao['latest_location']['latitude'] ?? $ao['current_lat'] ?? '-6.2000' ?>,<?= $ao['latest_location']['longitude'] ?? $ao['current_lng'] ?? '106.8166' ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                                <i class="bi bi-box-arrow-up-right me-1"></i> Google Maps
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Embedded Interactive Leaflet Live Tracking Map -->
                                <?php if (in_array($ao['status'], ['MENUNGGU_KURIR', 'KURIR_MENUJU_LOKASI', 'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO', 'SIAP_DIANTAR', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR'])): ?>
                                    <div class="card border-0 rounded-4 overflow-hidden shadow-inner mb-2 bg-white">
                                        <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-0">
                                            <div class="small fw-bold text-dark">
                                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> Live Pelacakan Posisi Kurir
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span id="liveBadge_<?= $ao['id'] ?>" class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 fs-8">
                                                    <i class="bi bi-circle-fill text-success fs-9 me-1"></i> Real-time Active
                                                </span>
                                                <small id="lastUpdated_<?= $ao['id'] ?>" class="text-muted fs-8"></small>
                                            </div>
                                        </div>
                                        <div id="customerLiveMap_<?= $ao['id'] ?>" style="height: 240px; width: 100%;"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Riwayat Transisi Status (Histori) -->
                        <?php if (!empty($ao['histories'])): ?>
                            <div class="mt-2">
                                <a class="text-decoration-none small text-primary fw-semibold" data-bs-toggle="collapse" href="#historyCollapse<?= $ao['id'] ?>" role="button" aria-expanded="false">
                                    <i class="bi bi-clock-history me-1"></i> Lihat Catatan Riwayat Status Cucian (<?= count($ao['histories']) ?> Entri)
                                </a>
                                <div class="collapse mt-2" id="historyCollapse<?= $ao['id'] ?>">
                                    <div class="p-3 bg-light rounded-4 border border-light-subtle small">
                                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                                            <?php foreach ($ao['histories'] as $h): ?>
                                                <li class="d-flex justify-content-between align-items-start border-bottom pb-1">
                                                    <div>
                                                        <strong class="text-dark"><?= str_replace('_', ' ', $h['status']) ?></strong>
                                                        <div class="text-muted"><?= e($h['notes'] ?? '-') ?></div>
                                                    </div>
                                                    <span class="text-muted fs-8"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?> WIB</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>


    <!-- 2. Riwayat Pesanan Selesai (5 Terakhir) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-success me-2"></i>Riwayat Cucian Selesai</h5>
                <small class="text-muted">Daftar cucian yang telah selesai dan diterima kembali dengan rapi.</small>
            </div>
            <span class="badge bg-light text-muted border rounded-pill px-3 py-1"><?= count($completedOrders) ?> Selesai</span>
        </div>

        <?php if (empty($completedOrders)): ?>
            <div class="text-center py-4 bg-light rounded-4 text-muted small">
                Belum ada riwayat pesanan selesai.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small text-uppercase">
                            <th>Kode Order</th>
                            <th>Toko Laundry</th>
                            <th>Rincian Layanan</th>
                            <th>Berat Timbang</th>
                            <th>Total Bayar</th>
                            <th>Tanggal Selesai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($completedOrders as $co): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">#<?= e($co['order_code']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($co['store_name']) ?></div>
                                    <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($co['store_phone']) ?></small>
                                </td>
                                <td>
                                    <span class="small text-dark"><?= e($co['item_summary'] ?? 'Paket Cuci Komplit') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info"><?= $co['weight_actual'] ?? '0' ?> kg (<?= (int)$co['pieces_count'] ?> pcs)</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?= format_rupiah($co['payment_amount'] ?? $co['total_price']) ?></span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d M Y, H:i', strtotime($co['completed_at'])) ?> WIB
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap gap-1 justify-content-end align-items-center">
                                        <?php if (!empty($co['review_id'])): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-3 py-2 fw-semibold" title="<?= e($co['review_comment'] ?? '') ?>">
                                                <i class="bi bi-star-fill text-warning me-1"></i> <?= $co['review_rating'] ?>/5 Terulas
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= url('order/' . $co['id'] . '/review') ?>" class="btn btn-sm btn-warning text-dark rounded-pill px-3 fw-bold shadow-sm">
                                                <i class="bi bi-star-fill me-1 text-danger"></i> Beri Ulasan
                                            </a>
                                        <?php endif; ?>
                                        <form action="<?= url('customer/orders/' . $co['id'] . '/reorder') ?>" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                                <i class="bi bi-arrow-repeat me-1"></i> Pesan Lagi
                                            </button>
                                        </form>
                                        <a href="<?= url('order/' . $co['id'] . '/invoice') ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                            <i class="bi bi-file-earmark-text me-1"></i> Invoice
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Leaflet Live Tracking Real-time Polling Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($activeOrders)): ?>
        <?php foreach ($activeOrders as $ao): ?>
            <?php if (in_array($ao['status'], ['MENUNGGU_KURIR', 'KURIR_MENUJU_LOKASI', 'LAUNDRY_DIJEMPUT', 'DALAM_PERJALANAN_KE_TOKO', 'SIAP_DIANTAR', 'KURIR_MENUJU_CUSTOMER', 'LAUNDRY_DIANTAR'])): ?>
                (function() {
                    const orderId = <?= (int)$ao['id'] ?>;
                    const mapContainerId = 'customerLiveMap_' + orderId;
                    const mapEl = document.getElementById(mapContainerId);
                    if (!mapEl) return;

                    const initialLat = <?= (float)($ao['pickup_lat'] ?? -6.20888800) ?>;
                    const initialLng = <?= (float)($ao['pickup_lng'] ?? 106.82888800) ?>;

                    const map = L.map(mapContainerId).setView([initialLat, initialLng], 14);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    // Destination Marker (Customer Address)
                    const destIcon = L.divIcon({
                        className: 'custom-dest-pin',
                        html: '<div style="background-color: #dc3545; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); border: 2px solid white;"><i class="bi bi-house-door-fill" style="font-size: 16px;"></i></div>',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16]
                    });

                    const destMarker = L.marker([initialLat, initialLng], { icon: destIcon })
                        .addTo(map)
                        .bindPopup('<strong>Tujuan:</strong> <?= e($ao['customer_name'] ?? 'Alamat Pelanggan') ?>');

                    // Courier Marker (Moving Truck Icon)
                    const courierIcon = L.divIcon({
                        className: 'custom-courier-pin',
                        html: '<div style="background-color: #0d6efd; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 3px 8px rgba(13,110,253,0.5); border: 2px solid white; animation: pulse 2s infinite;"><i class="bi bi-truck" style="font-size: 18px;"></i></div>',
                        iconSize: [36, 36],
                        iconAnchor: [18, 18]
                    });

                    let courierMarker = null;

                    function fetchLocation() {
                        fetch('<?= url('order/') ?>' + orderId + '/courier-location')
                            .then(res => res.json())
                            .then(data => {
                                if (data.success && data.courier) {
                                    const cLat = parseFloat(data.courier.latitude);
                                    const cLng = parseFloat(data.courier.longitude);

                                    if (!courierMarker) {
                                        courierMarker = L.marker([cLat, cLng], { icon: courierIcon })
                                            .addTo(map)
                                            .bindPopup('<strong>' + data.courier.name + '</strong><br>' + data.courier.vehicle_type + ' (' + data.courier.license_plate + ')');
                                    } else {
                                        courierMarker.setLatLng([cLat, cLng]);
                                    }

                                    // Fit bounds to show both courier and destination
                                    const group = new L.featureGroup([destMarker, courierMarker]);
                                    map.fitBounds(group.getBounds().pad(0.2));

                                    // Update timestamp
                                    const timeEl = document.getElementById('lastUpdated_' + orderId);
                                    if (timeEl) {
                                        timeEl.innerText = 'Update: ' + new Date().toLocaleTimeString('id-ID') + ' WIB';
                                    }
                                }
                            })
                            .catch(err => console.log('Location polling:', err));
                    }

                    // Initial fetch
                    fetchLocation();

                    // Real-time polling
                    const pollIntervalMs = <?= (int)\App\Models\Setting::get('tracking_poll_interval_seconds', 10) * 1000 ?>;
                    const pollInterval = setInterval(fetchLocation, pollIntervalMs);

                    // Clean up interval when window is unloaded
                    window.addEventListener('beforeunload', () => clearInterval(pollInterval));
                })();
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

