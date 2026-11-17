<?php
/**
 * SmartLaundry — Invoice Digital (Tahap 7)
 * Print-friendly layout dengan @media print CSS.
 * $order, $payment disediakan oleh PaymentController::invoice()
 */
$isPaid    = ($payment['status'] ?? '') === 'PAID';
$items     = $order['items'] ?? [];
$storeName = $order['store_name'] ?? 'SmartLaundry Store';
$storePhone = $order['store_phone'] ?? '-';
$custName  = $order['customer_name'] ?? '-';
$custEmail = $order['customer_email'] ?? '-';
$custPhone = $order['customer_phone'] ?? '-';
$pickupAddr = $order['pickup_address_full'] ?? '-';
$weightActual = $order['weight_actual'] ?? null;
$piecesCount  = $order['pieces_count'] ?? null;
$grandTotal   = (float)($payment['amount'] ?? $order['total_price'] ?? 0);
$deliveryFee  = (float)($order['delivery_fee'] ?? 0);
$invoiceDate  = date('d F Y, H:i', strtotime($order['created_at']));
$paidDate     = $payment['paid_at'] ? date('d F Y, H:i', strtotime($payment['paid_at'])) : null;
?>

<style>
/* ====== INVOICE PRINT STYLES ====== */
@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; font-size: 12pt; }
    .container { max-width: 100% !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: none !important; }
    .invoice-wrapper { border: none !important; }
    .status-watermark {
        position: fixed;
        top: 45%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-35deg);
        font-size: 5rem;
        font-weight: 900;
        opacity: 0.07;
        z-index: 0;
        pointer-events: none;
    }
    @page { margin: 1cm; }
}

.invoice-wrapper {
    border: 1.5px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
}

.invoice-header {
    background: linear-gradient(135deg, #1d4ed8, #3b82f6);
    color: white;
    padding: 28px 32px;
}

.invoice-body {
    padding: 28px 32px;
}

.invoice-table th {
    background: #f8fafc;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
}

.invoice-table td {
    vertical-align: middle;
    font-size: 0.88rem;
}

.badge-status-paid {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 6px 16px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.8rem;
}

.badge-status-pending {
    background: #fef9c3;
    color: #854d0e;
    border: 1px solid #fde68a;
    padding: 6px 16px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.8rem;
}

.badge-status-expired {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 6px 16px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.8rem;
}

.divider-dots {
    border: none;
    border-top: 2px dashed #e2e8f0;
    margin: 20px 0;
}

.status-watermark {
    position: fixed;
    top: 45%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-35deg);
    font-size: 5rem;
    font-weight: 900;
    opacity: 0.06;
    z-index: 0;
    pointer-events: none;
    color: <?= $isPaid ? '#15803d' : '#dc2626' ?>;
}
</style>

<div class="status-watermark"><?= $isPaid ? 'LUNAS' : ($payment['status'] ?? 'PENDING') ?></div>

<div class="container py-4" style="max-width: 760px;">

    <!-- Action Buttons (no-print) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 no-print">
        <div>
            <a href="<?= url('dashboard/customer') ?>" class="btn btn-outline-secondary rounded-pill px-4 me-2">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
            <a href="<?= url('order/' . $order['id'] . '/payment') ?>" class="btn btn-outline-primary rounded-pill px-4">
                <i class="bi bi-qr-code me-1"></i> Halaman Pembayaran
            </a>
        </div>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
            <i class="bi bi-printer-fill me-2"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Invoice Card -->
    <div class="invoice-wrapper shadow-lg">

        <!-- Header -->
        <div class="invoice-header">
            <div class="row align-items-center g-3">
                <div class="col-sm-7">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <?php $invLogo = app_logo(); ?>
                        <?php if ($invLogo): ?>
                            <img src="<?= url($invLogo) ?>" alt="<?= e(app_name()) ?>" class="rounded-3 shadow-xs" style="width: 44px; height: 44px; object-fit: cover; background: #fff; padding: 2px;">
                        <?php else: ?>
                            <div class="bg-white bg-opacity-25 rounded-3 p-2">
                                <i class="bi bi-water fs-3 text-white"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h3 class="fw-extrabold mb-0 text-white" style="letter-spacing:-0.5px">
                                <?= e(app_name()) ?>
                            </h3>
                            <div class="small opacity-75"><?= e(\App\Models\Setting::get('app_tagline', 'Marketplace Laundry Cepat & Higienis')) ?></div>
                        </div>
                    </div>
                    <div class="small opacity-75">
                        <i class="bi bi-shop me-1"></i> <?= e($storeName) ?> &nbsp;|&nbsp;
                        <i class="bi bi-telephone me-1"></i> <?= e($storePhone) ?>
                    </div>
                </div>
                <div class="col-sm-5 text-sm-end">
                    <div class="fw-bold fs-5 text-white mb-1">INVOICE</div>
                    <div class="font-monospace fw-bold fs-6 text-white">#<?= e($order['order_code']) ?></div>
                    <div class="small opacity-75 mt-1"><?= $invoiceDate ?> WIB</div>
                    <?php if ($isPaid): ?>
                        <span class="badge-status-paid mt-2 d-inline-block">LUNAS</span>
                    <?php elseif (($payment['status'] ?? '') === 'EXPIRED'): ?>
                        <span class="badge-status-expired mt-2 d-inline-block">KADALUARSA</span>
                    <?php else: ?>
                        <span class="badge-status-pending mt-2 d-inline-block">MENUNGGU BAYAR</span>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /header -->

        <!-- Body -->
        <div class="invoice-body">

            <!-- Customer & Order Info -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6">
                    <div class="small text-muted fw-semibold text-uppercase letter-spacing-1 mb-2">Tagihan Kepada</div>
                    <div class="fw-bold text-dark"><?= e($custName) ?></div>
                    <?php if ($custEmail): ?>
                        <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= e($custEmail) ?></div>
                    <?php endif; ?>
                    <?php if ($custPhone): ?>
                        <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($custPhone) ?></div>
                    <?php endif; ?>
                    <div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= e($pickupAddr) ?></div>
                </div>
                <div class="col-sm-6">
                    <div class="small text-muted fw-semibold text-uppercase letter-spacing-1 mb-2">Rincian Pembayaran</div>
                    <table class="table table-borderless table-sm mb-0 small">
                        <tr>
                            <td class="ps-0 text-muted">Kode Pembayaran</td>
                            <td class="fw-semibold font-monospace text-info"><?= e($payment['payment_code'] ?? '-') ?></td>
                        </tr>
                        <tr>
                            <td class="ps-0 text-muted">Metode Bayar</td>
                            <td class="fw-semibold"><?= e($payment['method'] ?? $order['payment_method'] ?? '-') ?></td>
                        </tr>
                        <?php if ($isPaid && $paidDate): ?>
                        <tr>
                            <td class="ps-0 text-muted">Waktu Lunas</td>
                            <td class="fw-semibold"><?= $paidDate ?> WIB</td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($payment['transaction_ref'])): ?>
                        <tr>
                            <td class="ps-0 text-muted">Ref. Transaksi</td>
                            <td class="fw-semibold small font-monospace text-success"><?= e($payment['transaction_ref']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <hr class="divider-dots">

            <!-- Laundry Details -->
            <?php if ($weightActual): ?>
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <div class="bg-info-subtle text-info rounded-4 px-4 py-2 text-center">
                        <div class="fw-extrabold fs-5"><?= (float)$weightActual ?> kg</div>
                        <div class="small">Berat Timbang</div>
                    </div>
                    <?php if ($piecesCount): ?>
                    <div class="bg-primary-subtle text-primary rounded-4 px-4 py-2 text-center">
                        <div class="fw-extrabold fs-5"><?= (int)$piecesCount ?> pcs</div>
                        <div class="small">Jumlah Helai</div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['notes_from_store'])): ?>
                    <div class="bg-warning-subtle text-warning-emphasis rounded-4 px-4 py-2 flex-grow-1">
                        <div class="small fw-semibold mb-1">Catatan Toko</div>
                        <div class="small"><?= e($order['notes_from_store']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Line Items Table -->
            <div class="table-responsive mb-3">
                <table class="table invoice-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="rounded-start-3 ps-3">Layanan Laundry</th>
                            <th>Kategori</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-end">Qty / Satuan</th>
                            <th class="text-end rounded-end-3 pe-3">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?= e($item['service_name'] ?? $item['name'] ?? '-') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border text-uppercase"><?= e($item['category'] ?? '-') ?></span>
                                    </td>
                                    <td class="text-end"><?= format_rupiah($item['unit_price'] ?? 0) ?></td>
                                    <td class="text-end">
                                        <span class="fw-semibold"><?= (float)($item['quantity'] ?? 0) ?></span>
                                        <span class="text-muted small"><?= e($item['unit'] ?? 'pcs') ?></span>
                                    </td>
                                    <td class="text-end pe-3 fw-semibold"><?= format_rupiah($item['subtotal'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted ps-3">Rincian layanan tidak tersedia.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <hr class="divider-dots">

            <!-- Totals -->
            <div class="row justify-content-end">
                <div class="col-sm-7 col-md-5">
                    <table class="table table-borderless table-sm small mb-0">
                        <tr>
                            <td class="text-muted">Subtotal Layanan</td>
                            <td class="text-end fw-semibold"><?= format_rupiah($grandTotal - $deliveryFee) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Ongkos Jemput & Antar</td>
                            <td class="text-end fw-semibold"><?= format_rupiah($deliveryFee) ?></td>
                        </tr>
                        <tr class="border-top">
                            <td class="fw-bold text-dark fs-6 pt-2">Total Tagihan</td>
                            <td class="text-end fw-extrabold text-success fs-5 pt-2"><?= format_rupiah($grandTotal) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <hr class="divider-dots">

            <!-- Footer -->
            <div class="text-center text-muted small py-2">
                <i class="bi bi-heart-fill text-danger me-1"></i>
                Terima kasih telah mempercayakan cucian Anda kepada SmartLaundry.<br>
                Jika ada pertanyaan, hubungi toko di <strong><?= e($storePhone) ?></strong> atau email kami di <strong>support@smartlaundry.id</strong>.<br>
                <span class="opacity-75">Invoice ini dibuat secara otomatis oleh sistem SmartLaundry AI Marketplace.</span>
            </div>

        </div><!-- /invoice-body -->
    </div><!-- /invoice-wrapper -->

    <!-- Bottom Print CTA (no-print) -->
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-5">
            <i class="bi bi-download me-1"></i> Simpan sebagai PDF
        </button>
    </div>

</div><!-- /container -->
