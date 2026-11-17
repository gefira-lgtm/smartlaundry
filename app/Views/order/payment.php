<?php
/**
 * SmartLaundry — QRIS Payment Page (Tahap 7)
 * Menampilkan QR code QRIS dinamis dengan countdown 1 jam + AJAX polling setiap 3 detik.
 * $order, $payment, $secondsLeft disediakan oleh PaymentController::show()
 */
$isPending  = ($payment['status'] ?? '') === 'PENDING';
$isExpired  = ($payment['status'] ?? '') === 'EXPIRED';
$isPaid     = ($payment['status'] ?? '') === 'PAID';
$isCash     = ($order['payment_method'] ?? 'ONLINE_QRIS') === 'CASH';
$qrisUrl    = $payment['qris_image_url'] ?? null;
$expiredAt  = $payment['expired_at'] ?? null;
?>

<div class="container py-4" style="max-width: 640px;">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= url('dashboard/customer') ?>" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item active">Pembayaran #<?= e($order['order_code']) ?></li>
        </ol>
    </nav>

    <!-- Flash Messages -->
    <?php if ($flash = get_flash('success')): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-3" id="flash-alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($flash) ?>
        </div>
    <?php endif; ?>
    <?php if ($flash = get_flash('error')): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-3" id="flash-alert">
            <i class="bi bi-x-circle-fill me-2"></i><?= e($flash) ?>
        </div>
    <?php endif; ?>

    <!-- ============================
         STATUS: PAID
         ============================ -->
    <?php if ($isPaid): ?>
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden text-center">
            <div class="card-body p-5">
                <div class="mb-4">
                    <div class="display-1 text-success mb-3">
                        <i class="bi bi-patch-check-fill"></i>
                    </div>
                    <h3 class="fw-bold text-dark">Pembayaran LUNAS!</h3>
                    <p class="text-muted">Pesanan <strong>#<?= e($order['order_code']) ?></strong> telah berhasil dibayar. Cucian Anda sedang diproses.</p>
                </div>
                <div class="bg-light rounded-4 p-4 mb-4 text-start">
                    <div class="row g-2 small">
                        <div class="col-6 text-muted">Ref. Transaksi</div>
                        <div class="col-6 fw-semibold font-monospace text-success"><?= e($payment['transaction_ref'] ?? '-') ?></div>
                        <div class="col-6 text-muted">Metode</div>
                        <div class="col-6 fw-semibold"><?= e($payment['method'] ?? '-') ?></div>
                        <div class="col-6 text-muted">Total Dibayar</div>
                        <div class="col-6 fw-bold text-success fs-6"><?= format_rupiah($payment['amount'] ?? 0) ?></div>
                        <div class="col-6 text-muted">Waktu Bayar</div>
                        <div class="col-6"><?= $payment['paid_at'] ? date('d M Y, H:i', strtotime($payment['paid_at'])) . ' WIB' : '-' ?></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="<?= url('order/' . $order['id'] . '/invoice') ?>" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm" target="_blank">
                        <i class="bi bi-file-earmark-text me-1"></i> Lihat Invoice
                    </a>
                    <a href="<?= url('dashboard/customer') ?>" class="btn btn-outline-secondary rounded-pill px-4">
                        <i class="bi bi-house me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

    <!-- ============================
         STATUS: EXPIRED
         ============================ -->
    <?php elseif ($isExpired): ?>
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-body p-5 text-center">
                <div class="display-1 text-danger mb-3"><i class="bi bi-hourglass-bottom"></i></div>
                <h3 class="fw-bold text-dark">Batas Waktu QRIS Habis</h3>
                <p class="text-muted">Kode QRIS untuk pesanan <strong>#<?= e($order['order_code']) ?></strong> telah kadaluarsa. Buat kode baru untuk melanjutkan pembayaran.</p>
                <div class="alert alert-warning border-0 rounded-4 small mt-3 mb-4 text-start">
                    <i class="bi bi-info-circle me-2"></i>
                    Tagihan <strong><?= format_rupiah($payment['amount'] ?? 0) ?></strong> tetap berlaku. QRIS baru akan aktif selama <strong>1 jam</strong>.
                </div>
                <form action="<?= url('order/' . $order['id'] . '/payment/regenerate') ?>" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-warning text-dark rounded-pill px-5 py-2 fw-bold shadow-sm">
                        <i class="bi bi-arrow-clockwise me-2"></i> Buat Ulang QRIS (1 Jam Baru)
                    </button>
                </form>
                <a href="<?= url('dashboard/customer') ?>" class="btn btn-link text-muted mt-2 small d-block">
                    Kembali ke Dashboard
                </a>
            </div>
        </div>

    <!-- ============================
         STATUS: PENDING CASH
         ============================ -->
    <?php elseif ($isCash && $isPending): ?>
        <div class="card border-0 shadow-lg rounded-4">
            <div class="card-body p-5 text-center">
                <div class="display-1 text-warning mb-3"><i class="bi bi-cash-coin"></i></div>
                <h3 class="fw-bold text-dark">Pembayaran Tunai</h3>
                <p class="text-muted">Pesanan <strong>#<?= e($order['order_code']) ?></strong> akan dibayar secara <strong>tunai</strong> saat laundry diantarkan oleh kurir.</p>
                <div class="bg-light rounded-4 p-4 my-4 text-start small">
                    <div class="row g-2">
                        <div class="col-6 text-muted">Total Tagihan</div>
                        <div class="col-6 fw-bold text-dark fs-5"><?= format_rupiah($payment['amount'] ?? $order['total_price']) ?></div>
                        <div class="col-6 text-muted">Status</div>
                        <div class="col-6"><span class="badge bg-warning text-dark">Menunggu Kurir</span></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="<?= url('order/' . $order['id'] . '/invoice') ?>" class="btn btn-outline-primary rounded-pill px-4" target="_blank">
                        <i class="bi bi-file-earmark-text me-1"></i> Invoice
                    </a>
                    <a href="<?= url('dashboard/customer') ?>" class="btn btn-primary rounded-pill px-4 fw-semibold">
                        <i class="bi bi-house me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

    <!-- ============================
         STATUS: PENDING QRIS (MAIN)
         ============================ -->
    <?php else: ?>
        <!-- Main QRIS Card -->
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden" id="qris-card">
            <!-- Header Gradient -->
            <div class="bg-primary-gradient text-white p-4 text-center">
                <h4 class="fw-bold mb-1"><i class="bi bi-qr-code-scan me-2"></i>Bayar dengan QRIS</h4>
                <p class="opacity-75 small mb-0">Pesanan #<?= e($order['order_code']) ?> — <?= e($order['store_name']) ?></p>
            </div>

            <div class="card-body p-4">

                <!-- Countdown Timer -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center gap-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-4 px-4 py-3 mb-2" id="countdown-wrapper">
                        <i class="bi bi-alarm-fill text-danger fs-4"></i>
                        <div>
                            <div class="small text-muted fw-semibold">Batas Waktu Pembayaran</div>
                            <div class="fw-extrabold text-danger fs-4 font-monospace lh-1" id="countdown-display">--:--:--</div>
                        </div>
                    </div>
                    <?php if ($expiredAt): ?>
                        <div class="small text-muted">Berlaku hingga: <strong><?= date('d M Y, H:i', strtotime($expiredAt)) ?> WIB</strong></div>
                    <?php endif; ?>
                </div>

                <!-- QR Code Display -->
                <div class="text-center mb-4">
                    <?php if ($qrisUrl): ?>
                        <div class="d-inline-block p-3 border border-2 rounded-4 shadow-sm bg-white" id="qr-wrapper">
                            <img src="<?= e($qrisUrl) ?>" alt="QRIS Code" class="img-fluid" style="width: 240px; height: 240px;" id="qr-image">
                            <div class="mt-2">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/1e/QRIS_logo.svg/240px-QRIS_logo.svg.png"
                                     alt="QRIS" style="height: 28px; opacity:.85;" onerror="this.style.display='none'">
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-5 bg-light rounded-4 text-muted">
                            <i class="bi bi-qr-code display-4 d-block mb-2"></i>
                            QR Code sedang digenerate...
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Order Summary -->
                <div class="bg-light rounded-4 p-3 mb-4">
                    <div class="row g-2 small">
                        <div class="col-6 text-muted">Nama Toko</div>
                        <div class="col-6 fw-semibold text-dark"><?= e($order['store_name']) ?></div>
                        <div class="col-6 text-muted">Customer</div>
                        <div class="col-6 fw-semibold text-dark"><?= e($order['customer_name']) ?></div>
                        <?php if (!empty($order['weight_actual'])): ?>
                        <div class="col-6 text-muted">Berat Timbang</div>
                        <div class="col-6 fw-semibold text-primary"><?= (float)$order['weight_actual'] ?> kg</div>
                        <?php endif; ?>
                        <div class="col-6 text-muted">Kode Pembayaran</div>
                        <div class="col-6 fw-semibold font-monospace text-info"><?= e($payment['payment_code']) ?></div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-6 fw-bold text-dark fs-6">Total Tagihan</div>
                        <div class="col-6 fw-bold text-success fs-5"><?= format_rupiah($payment['amount'] ?? 0) ?></div>
                    </div>
                </div>

                <!-- QRIS Instructions -->
                <div class="alert alert-info border-0 rounded-4 small mb-4">
                    <strong class="d-block mb-1"><i class="bi bi-info-circle me-1"></i>Cara Bayar QRIS:</strong>
                    <ol class="mb-0 ps-3">
                        <li>Buka aplikasi mobile banking / e-wallet apapun</li>
                        <li>Pilih menu <strong>Bayar / Scan QR</strong></li>
                        <li>Arahkan kamera ke QR Code di atas</li>
                        <li>Verifikasi nominal, lalu konfirmasi pembayaran</li>
                        <li>Halaman ini akan otomatis update saat pembayaran berhasil</li>
                    </ol>
                </div>

                <!-- Payment Status Indicator (AJAX updates this) -->
                <div id="payment-status-indicator" class="text-center mb-3" style="display:none;">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    <span class="text-muted small">Menunggu konfirmasi pembayaran...</span>
                </div>

                <!-- Paid Success Overlay (shown on AJAX paid confirm) -->
                <div id="paid-overlay" class="d-none text-center py-3">
                    <div class="text-success display-4 mb-2"><i class="bi bi-patch-check-fill"></i></div>
                    <h5 class="fw-bold text-success">Pembayaran Berhasil Dikonfirmasi!</h5>
                    <p class="text-muted small">Halaman akan diarahkan ke dashboard dalam 3 detik...</p>
                </div>

                <!-- Dev: Simulate Payment Button -->
                <?php if (env('APP_ENV') === 'local' || env('APP_ENV') === 'development'): ?>
                    <div class="border-top pt-3 mt-2">
                        <p class="text-muted small text-center mb-2">
                            <i class="bi bi-bug-fill me-1 text-warning"></i>
                            <strong>Mode Dev:</strong> Simulasi pembayaran masuk tanpa scan QR
                        </p>
                        <form action="<?= url('payment/' . $order['id'] . '/simulate-paid') ?>" method="POST" id="simulate-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-warning text-dark w-100 rounded-pill fw-bold" id="btn-simulate">
                                <i class="bi bi-lightning-charge-fill me-1"></i> Simulasi Bayar Sekarang (Dev)
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Action Links -->
        <div class="mt-3 text-center">
            <a href="<?= url('order/' . $order['id'] . '/invoice') ?>" class="btn btn-outline-secondary rounded-pill px-4 me-2" target="_blank">
                <i class="bi bi-file-earmark-text me-1"></i> Invoice
            </a>
            <a href="<?= url('dashboard/customer') ?>" class="btn btn-link text-muted">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>

    <?php endif; ?>

</div><!-- /container -->

<!-- =============================================
     JAVASCRIPT: Countdown Timer + AJAX Polling
     ============================================= -->
<?php if ($isPending && !$isCash): ?>
<script>
(function() {
    'use strict';

    // ---- Countdown Timer ----
    let secondsLeft = <?= (int)$secondsLeft ?>;
    const displayEl = document.getElementById('countdown-display');
    const wrapperEl = document.getElementById('countdown-wrapper');

    function updateCountdown() {
        if (secondsLeft <= 0) {
            displayEl.textContent = '00:00:00';
            if (wrapperEl) {
                wrapperEl.classList.add('opacity-50');
            }
            // Reload so expired state is shown
            window.location.reload();
            return;
        }
        const h = Math.floor(secondsLeft / 3600);
        const m = Math.floor((secondsLeft % 3600) / 60);
        const s = secondsLeft % 60;
        displayEl.textContent =
            String(h).padStart(2, '0') + ':' +
            String(m).padStart(2, '0') + ':' +
            String(s).padStart(2, '0');
        secondsLeft--;
    }

    updateCountdown();
    const timerInterval = setInterval(updateCountdown, 1000);

    // ---- AJAX Status Polling (every 3 seconds) ----
    const statusUrl = '<?= url('order/' . $order['id'] . '/payment/status') ?>';
    const statusIndicator = document.getElementById('payment-status-indicator');
    const paidOverlay = document.getElementById('paid-overlay');

    if (statusIndicator) statusIndicator.style.display = '';

    let pollInterval = null;
    let isPollRunning = false;

    function pollPaymentStatus() {
        if (isPollRunning) return;
        isPollRunning = true;

        fetch(statusUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function(res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function(data) {
            isPollRunning = false;

            if (!data || data.status === 'error') return;

            // Update seconds from server (sync clock)
            if (typeof data.seconds_left === 'number') {
                secondsLeft = data.seconds_left;
            }

            if (data.is_paid) {
                // Stop polling & timer
                clearInterval(pollInterval);
                clearInterval(timerInterval);

                // Show success overlay
                const qrisCard = document.getElementById('qris-card');
                if (qrisCard) qrisCard.style.opacity = '0.5';
                if (paidOverlay) paidOverlay.classList.remove('d-none');
                if (statusIndicator) statusIndicator.style.display = 'none';

                // Redirect after 3 seconds
                setTimeout(function() {
                    window.location.href = '<?= url('order/' . $order['id'] . '/payment') ?>';
                }, 3000);
            } else if (data.is_expired) {
                clearInterval(pollInterval);
                clearInterval(timerInterval);
                window.location.reload();
            }
        })
        .catch(function(err) {
            isPollRunning = false;
            console.warn('[SmartLaundry] Polling error:', err.message);
        });
    }

    // Start polling after 3 seconds initial delay
    setTimeout(function() {
        pollInterval = setInterval(pollPaymentStatus, 5000);
    }, 3000);

    // ---- Dev: Simulate form feedback ----
    const simulateForm = document.getElementById('simulate-form');
    const btnSimulate = document.getElementById('btn-simulate');
    if (simulateForm && btnSimulate) {
        simulateForm.addEventListener('submit', function() {
            btnSimulate.disabled = true;
            btnSimulate.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
        });
    }

})();
</script>
<?php endif; ?>
