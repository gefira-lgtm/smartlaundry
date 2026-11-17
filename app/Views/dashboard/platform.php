<div class="container py-4">
    <!-- Header Banner -->
    <div class="card border-0 bg-primary-gradient text-white shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
                    <i class="bi bi-speedometer2 fs-3"></i>
                </div>
                <div>
                    <div class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-1 fw-bold">
                        <?= e($user['role_display'] ?? 'Platform Admin') ?>
                    </div>
                    <h3 class="fw-bold mb-0">Dashboard Monitoring Platform</h3>
                    <small class="opacity-75">Selamat datang, <strong><?= e($user['name']) ?></strong> • Pengawasan Real-Time Seluruh Toko & Transaksi</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-white bg-opacity-25 text-white px-3 py-2 rounded-pill small">
                    <i class="bi bi-clock-history me-1"></i> Data Real-Time
                </span>
            </div>
        </div>
    </div>

    <!-- 1. Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Total GMV -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Total GMV (Omset)</span>
                    <div class="bg-success-subtle text-success rounded-3 p-2"><i class="bi bi-wallet2 fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= format_rupiah($totalGMV) ?></h3>
                <small class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Transaksi Lunas</small>
            </div>
        </div>

        <!-- Total Pesanan -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Total Pesanan</span>
                    <div class="bg-primary-subtle text-primary rounded-3 p-2"><i class="bi bi-bag-check-fill fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= number_format($totalOrders, 0, ',', '.') ?> <span class="fs-6 text-muted fw-normal">Order</span></h3>
                <small class="text-primary fw-semibold"><i class="bi bi-layers-fill me-1"></i> Semua Siklus Pesanan</small>
            </div>
        </div>

        <!-- Mitra Toko Aktif -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Toko Terverifikasi</span>
                    <div class="bg-info-subtle text-info rounded-3 p-2"><i class="bi bi-shop fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= $totalStores ?> <span class="fs-6 text-muted fw-normal">/ <?= $allStoresCount ?> Toko</span></h3>
                <small class="text-info fw-semibold"><i class="bi bi-patch-check-fill me-1"></i> Siap Melayani Order</small>
            </div>
        </div>

        <!-- Total Customer -->
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white hover-elevate h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold text-uppercase letter-spacing-1">Customer Terdaftar</span>
                    <div class="bg-warning-subtle text-warning rounded-3 p-2"><i class="bi bi-people-fill fs-5"></i></div>
                </div>
                <h3 class="fw-extrabold text-dark mb-1"><?= number_format($totalCustomers, 0, ',', '.') ?> <span class="fs-6 text-muted fw-normal">User</span></h3>
                <small class="text-warning fw-semibold"><i class="bi bi-person-heart me-1"></i> Pelanggan Aktif</small>
            </div>
        </div>
    </div>

    <!-- 2. Chart Section (7 Days Trends) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-graph-up-arrow text-primary me-2"></i>Tren Transaksi & Pesanan (7 Hari Terakhir)</h5>
                <p class="text-muted small mb-0">Agregasi harian nilai pembayaran lunas (GMV) dan volume pesanan masuk.</p>
            </div>
            <div>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2">
                    <i class="bi bi-calendar-event me-1"></i> <?= reset($trends['labels']) ?> - <?= end($trends['labels']) ?>
                </span>
            </div>
        </div>
        <div style="height: 280px; position: relative;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- 3. Store Monitoring Table -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-shop-window text-primary me-2"></i>Monitoring & Verifikasi Mitra Toko Laundry</h5>
                <p class="text-muted small mb-0">Daftar toko laundry yang bergabung dalam marketplace beserta status verifikasi.</p>
            </div>
            <?php if ($isAdminOwner): ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2 fw-semibold">
                    <i class="bi bi-shield-check me-1"></i> Hak Akses Verifikasi Aktif
                </span>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>Toko Laundry</th>
                        <th>Pemilik (Owner)</th>
                        <th>Rating</th>
                        <th>Pesanan Masuk</th>
                        <th>Omset Terkumpul</th>
                        <th>Status</th>
                        <th class="text-end">Aksi Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stores)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada toko yang terdaftar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($stores as $s): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="bi bi-water"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?= e($s['name']) ?></div>
                                            <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= e($s['phone']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($s['owner_name']) ?></div>
                                    <small class="text-muted"><?= e($s['owner_email']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-warning-subtle text-dark rounded-pill px-2 py-1">
                                        <i class="bi bi-star-fill text-warning me-1"></i> <?= number_format($s['rating_avg'], 1) ?> (<?= $s['rating_count'] ?>)
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= (int)$s['total_orders'] ?></span> Order
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?= format_rupiah($s['total_revenue']) ?></span>
                                </td>
                                <td>
                                    <?php if ($s['is_verified']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Terverifikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-1">
                                            <i class="bi bi-hourglass-split me-1"></i> Pending Verifikasi
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form action="<?= url('platform/stores/' . $s['id'] . '/toggle-verify') ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <?php if ($s['is_verified']): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Nonaktifkan status verifikasi toko ini?');">
                                                <i class="bi bi-x-circle me-1"></i> Batal Verifikasi
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3">
                                                <i class="bi bi-patch-check-fill me-1"></i> Verifikasi Toko
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 4. Recent Platform Orders -->
    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-receipt-cutoff text-primary me-2"></i>10 Pesanan Terbaru di Platform</h5>
                <p class="text-muted small mb-0">Aktivitas pesanan real-time dari seluruh cabang toko mitra.</p>
            </div>
            <span class="badge bg-light text-muted border rounded-pill px-3 py-2">
                Total: <?= count($recentOrders) ?> Pesanan
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-muted small text-uppercase">
                        <th>Kode Order</th>
                        <th>Customer</th>
                        <th>Toko Tujuan</th>
                        <th>Layanan</th>
                        <th>Total Tagihan</th>
                        <th>Pembayaran</th>
                        <th>Status Order</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada data pesanan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace"><?= e($ro['order_code']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ro['customer_name']) ?></div>
                                    <small class="text-muted"><?= e($ro['customer_phone']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ro['store_name']) ?></div>
                                </td>
                                <td>
                                    <span class="small text-muted"><?= e($ro['service_names'] ?? 'Layanan Kiloan/Satuan') ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?= format_rupiah($ro['total_price']) ?></span>
                                </td>
                                <td>
                                    <?php if ($ro['payment_status'] === 'PAID'): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> LUNAS (<?= e($ro['payment_method_detail'] ?? $ro['payment_method']) ?>)
                                        </span>
                                    <?php elseif ($ro['payment_status'] === 'PENDING'): ?>
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1">
                                            <i class="bi bi-clock me-1"></i> MENUNGGU
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-1">
                                            <?= e($ro['payment_status'] ?? 'BELUM DITIMBANG') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadgeClass = 'bg-primary-subtle text-primary';
                                    if ($ro['status'] === 'SELESAI') $statusBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
                                    elseif ($ro['status'] === 'MENUNGGU_KONFIRMASI') $statusBadgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                                    elseif ($ro['status'] === 'DIBATALKAN' || $ro['status'] === 'PEMBAYARAN_KADALUARSA') $statusBadgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';
                                    elseif (strpos($ro['status'], 'KURIR') !== false) $statusBadgeClass = 'bg-info-subtle text-info border border-info-subtle';
                                    ?>
                                    <span class="badge <?= $statusBadgeClass ?> rounded-pill px-3 py-1">
                                        <?= str_replace('_', ' ', $ro['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?= date('d/m/Y H:i', strtotime($ro['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('trendChart').getContext('2d');
    const labels = <?= json_encode($trends['labels']) ?>;
    const salesData = <?= json_encode($trends['sales']) ?>;
    const ordersData = <?= json_encode($trends['orders']) ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Nilai Transaksi Lunas (Rp)',
                    data: salesData,
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderColor: 'rgb(37, 99, 235)',
                    borderWidth: 1,
                    borderRadius: 6,
                    yAxisID: 'y'
                },
                {
                    label: 'Jumlah Pesanan',
                    data: ordersData,
                    type: 'line',
                    borderColor: 'rgb(245, 158, 11)',
                    backgroundColor: 'rgba(245, 158, 11, 0.2)',
                    borderWidth: 3,
                    tension: 0.35,
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Total Transaksi (Rp)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + value.toLocaleString('id-ID');
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Volume Pesanan'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>
