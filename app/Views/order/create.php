<div class="container py-4">
    <!-- Header Banner -->
    <div class="card border-0 bg-primary-gradient text-white shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 56px; height: 56px;">
                    <i class="bi bi-cart-check-fill fs-3"></i>
                </div>
                <div>
                    <div class="badge bg-warning text-dark rounded-pill px-3 py-1 mb-1 fw-bold">
                        Pemesanan Baru
                    </div>
                    <h3 class="fw-bold mb-0">Buat Pesanan Laundry</h3>
                    <small class="opacity-75">Lengkapi formulir 4 langkah di bawah ini untuk memesan jasa laundry.</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= url('stores') ?>" class="btn btn-outline-light rounded-pill px-3">
                    <i class="bi bi-shop me-1"></i> Pilih Toko Lain
                </a>
            </div>
        </div>
    </div>

    <!-- Stepper Navigation Header -->
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
        <div class="row text-center g-2 g-md-0" id="checkoutStepper">
            <div class="col-3 stepper-step active" data-step="1">
                <div class="d-flex flex-column align-items-center">
                    <div class="step-num rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 shadow-sm">1</div>
                    <span class="fs-8 fw-bold step-title text-primary">Pilih Layanan</span>
                </div>
            </div>
            <div class="col-3 stepper-step" data-step="2">
                <div class="d-flex flex-column align-items-center">
                    <div class="step-num rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 shadow-sm">2</div>
                    <span class="fs-8 fw-semibold step-title text-muted">Alamat Jemput</span>
                </div>
            </div>
            <div class="col-3 stepper-step" data-step="3">
                <div class="d-flex flex-column align-items-center">
                    <div class="step-num rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 shadow-sm">3</div>
                    <span class="fs-8 fw-semibold step-title text-muted">Jadwal Jemput</span>
                </div>
            </div>
            <div class="col-3 stepper-step" data-step="4">
                <div class="d-flex flex-column align-items-center">
                    <div class="step-num rounded-circle d-flex align-items-center justify-content-center fw-bold mb-1 shadow-sm">4</div>
                    <span class="fs-8 fw-semibold step-title text-muted">Konfirmasi</span>
                </div>
            </div>
        </div>
    </div>

    <form id="formCheckout" action="<?= url('order/store') ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="store_id" id="inputStoreId" value="<?= $selectedStore['id'] ?>">

        <!-- =================================================================== -->
        <!-- STEP 1: PILIH TOKO & LAYANAN                                         -->
        <!-- =================================================================== -->
        <div class="checkout-step-content" id="stepContent1">
            <!-- Store Info Card -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle text-primary rounded-4 p-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="bi bi-shop fs-2"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="fw-bold text-dark mb-0" id="displayStoreName"><?= e($selectedStore['name']) ?></h5>
                                <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 fs-8">
                                    <i class="bi bi-star-fill text-warning me-1"></i><?= number_format((float)($selectedStore['rating_avg'] ?? 5.0), 1) ?>
                                </span>
                            </div>
                            <small class="text-muted"><i class="bi bi-clock me-1"></i><?= e($selectedStore['operational_hours'] ?? 'Buka 08:00 - 20:00 WIB') ?> • <i class="bi bi-telephone me-1"></i><?= e($selectedStore['phone']) ?></small>
                        </div>
                    </div>
                    <div>
                        <!-- Switch Store Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary rounded-pill btn-sm dropdown-toggle px-3" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-arrow-repeat me-1"></i> Ganti Mitra Toko
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-4 py-2">
                                <?php foreach ($stores as $s): ?>
                                    <li>
                                        <a class="dropdown-item py-2 d-flex align-items-center justify-content-between gap-3 <?= $s['id'] == $selectedStore['id'] ? 'active bg-primary-subtle text-primary fw-bold' : '' ?>" 
                                           href="<?= url('order/create?store_id=' . $s['id']) ?>">
                                            <div>
                                                <div class="fw-semibold small"><?= e($s['name']) ?></div>
                                                <small class="text-muted"><?= e($s['operational_hours']) ?></small>
                                            </div>
                                            <span class="badge bg-warning text-dark rounded-pill fs-8">⭐ <?= number_format((float)$s['rating_avg'], 1) ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services Selection Section -->
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-check text-primary me-2"></i>Pilih Paket & Layanan Laundry</h5>
                        <small class="text-muted">Centang layanan yang diinginkan dan masukkan estimasi jumlah / berat cucian.</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-bold" id="badgeSelectedCount">0 Layanan Dipilih</span>
                </div>

                <?php if (empty($services)): ?>
                    <div class="alert alert-light border rounded-4 text-center py-4 text-muted">
                        Toko ini belum menambahkan daftar layanan aktif. Silakan pilih toko mitra lainnya.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($services as $srv): ?>
                            <div class="col-md-6">
                                <div class="card border border-light-subtle rounded-4 p-3 h-100 service-item-card transition hover-elevate">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input service-checkbox fs-5" type="checkbox" 
                                                   name="services[<?= $srv['id'] ?>][selected]" 
                                                   value="1" 
                                                   id="srv_check_<?= $srv['id'] ?>"
                                                   data-id="<?= $srv['id'] ?>"
                                                   data-name="<?= e($srv['name']) ?>"
                                                   data-price="<?= (float)$srv['price'] ?>"
                                                   data-unit="<?= e($srv['unit']) ?>"
                                                   data-min="<?= (float)$srv['min_order'] ?>">
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <label class="fw-bold text-dark mb-0 cursor-pointer" for="srv_check_<?= $srv['id'] ?>">
                                                    <?= e($srv['name']) ?>
                                                </label>
                                                <span class="badge bg-light text-dark border rounded-pill fs-8 text-uppercase"><?= e($srv['category']) ?></span>
                                            </div>
                                            <div class="fw-bold text-primary fs-6 mb-1">
                                                <?= format_rupiah($srv['price']) ?> <span class="text-muted fs-8 fw-normal">/ <?= e($srv['unit']) ?></span>
                                            </div>
                                            <small class="text-muted d-block mb-2"><i class="bi bi-hourglass-split me-1"></i>Estimasi: <?= e($srv['estimated_duration']) ?></small>
                                            
                                            <!-- Qty Input Group (Enabled when selected) -->
                                            <div class="d-none qty-container pt-2 border-top" id="qty_box_<?= $srv['id'] ?>">
                                                <div class="row g-2 align-items-center">
                                                    <div class="col-7">
                                                        <label class="form-label fs-8 text-muted fw-bold mb-1">Estimasi Qty (<?= e($srv['unit']) ?>):</label>
                                                        <div class="input-group input-group-sm">
                                                            <button type="button" class="btn btn-outline-secondary btn-decrement" data-target="#qty_input_<?= $srv['id'] ?>">-</button>
                                                            <input type="number" 
                                                                   name="services[<?= $srv['id'] ?>][quantity]" 
                                                                   id="qty_input_<?= $srv['id'] ?>" 
                                                                   class="form-control text-center qty-input" 
                                                                   value="<?= (float)$srv['min_order'] ?>" 
                                                                   min="<?= (float)$srv['min_order'] ?>" 
                                                                   step="<?= $srv['unit'] === 'kg' ? '0.5' : '1' ?>">
                                                            <button type="button" class="btn btn-outline-secondary btn-increment" data-target="#qty_input_<?= $srv['id'] ?>">+</button>
                                                        </div>
                                                    </div>
                                                    <div class="col-5 text-end">
                                                        <small class="text-muted fs-8 d-block">Subtotal</small>
                                                        <strong class="text-dark fs-7 item-subtotal-display" id="subtotal_<?= $srv['id'] ?>">
                                                            <?= format_rupiah($srv['price'] * $srv['min_order']) ?>
                                                        </strong>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <input type="text" name="services[<?= $srv['id'] ?>][notes]" class="form-control form-control-sm rounded-3" placeholder="Catatan item (opsional, mis: parfum vanila)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Estimated Subtotal Live Bar -->
                <div class="mt-4 p-3 bg-light rounded-4 border d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="text-muted small">Estimasi Subtotal Layanan:</span>
                        <h4 class="fw-bold text-primary mb-0" id="liveEstimatedSubtotal">Rp 0</h4>
                    </div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnNextToStep2">
                        Lanjut: Pilih Alamat <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 2: ALAMAT JEMPUT & ANTAR                                       -->
        <!-- =================================================================== -->
        <div class="checkout-step-content d-none" id="stepContent2">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Pilih Alamat Penjemputan</h5>
                        <small class="text-muted">Kurir akan datang ke titik lokasi ini untuk mengambil cucian Anda.</small>
                    </div>
                    <a href="<?= url('customer/addresses/create') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Alamat Baru
                    </a>
                </div>

                <div class="row g-3 mb-4">
                    <?php foreach ($addresses as $idx => $addr): ?>
                        <div class="col-md-6">
                            <label class="card border rounded-4 p-3 h-100 cursor-pointer address-card-select transition <?= $addr['is_primary'] ? 'border-primary bg-primary-subtle bg-opacity-10' : 'border-light-subtle' ?>" for="addr_pickup_<?= $addr['id'] ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <input class="form-check-input mt-1 pickup-addr-radio" type="radio" name="pickup_address_id" id="addr_pickup_<?= $addr['id'] ?>" value="<?= $addr['id'] ?>" <?= ($addr['is_primary'] || $idx === 0) ? 'checked' : '' ?> data-full="<?= e($addr['full_address']) ?>" data-label="<?= e($addr['label']) ?>">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="text-dark"><?= e($addr['label']) ?></strong>
                                            <?php if ($addr['is_primary']): ?>
                                                <span class="badge bg-primary rounded-pill fs-8">Utama</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small fw-semibold text-dark"><?= e($addr['recipient_name']) ?> (<?= e($addr['phone']) ?>)</div>
                                        <p class="small text-muted mb-0"><?= e($addr['full_address']) ?></p>
                                        <?php if (!empty($addr['notes'])): ?>
                                            <small class="text-info d-block mt-1"><i class="bi bi-info-circle me-1"></i><?= e($addr['notes']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-check form-switch mb-3 p-3 bg-light rounded-4 border">
                    <input class="form-check-input ms-0 me-2" type="checkbox" name="same_delivery_address" id="sameDeliveryAddress" value="1" checked>
                    <label class="form-check-label fw-semibold text-dark cursor-pointer" for="sameDeliveryAddress">
                        Alamat pengantaran cucian bersih sama dengan alamat penjemputan
                    </label>
                </div>

                <!-- Delivery Address Selector (Hidden if same address) -->
                <div class="d-none" id="deliveryAddressSection">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-house-door text-success me-2"></i>Pilih Alamat Pengantaran Bersih</h6>
                    <div class="row g-3 mb-3">
                        <?php foreach ($addresses as $idx => $addr): ?>
                            <div class="col-md-6">
                                <label class="card border rounded-4 p-3 h-100 cursor-pointer" for="addr_deliv_<?= $addr['id'] ?>">
                                    <div class="d-flex align-items-start gap-3">
                                        <input class="form-check-input mt-1" type="radio" name="delivery_address_id" id="addr_deliv_<?= $addr['id'] ?>" value="<?= $addr['id'] ?>" <?= ($addr['is_primary'] || $idx === 0) ? 'checked' : '' ?>>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong class="text-dark"><?= e($addr['label']) ?></strong>
                                            </div>
                                            <p class="small text-muted mb-0"><?= e($addr['full_address']) ?></p>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" id="btnBackToStep1">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Layanan
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnNextToStep3">
                        Lanjut: Pilih Jadwal <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 3: JADWAL PENJEMPUTAN                                          -->
        <!-- =================================================================== -->
        <div class="checkout-step-content d-none" id="stepContent3">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-event text-primary me-2"></i>Tentukan Waktu Penjemputan</h5>

                <div class="row g-4 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Tanggal Penjemputan <span class="text-danger">*</span></label>
                        <input type="date" name="pickup_date" id="inputPickupDate" class="form-control rounded-3 py-2" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                        <small class="text-muted fs-8">Pilih hari ini atau jadwal hari berikutnya.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Slot Waktu Kurir Datang <span class="text-danger">*</span></label>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($timeSlots as $idx => $slot): ?>
                                <label class="btn btn-outline-primary text-start rounded-pill px-3 py-2 d-flex align-items-center gap-2 cursor-pointer slot-label <?= $idx === 0 ? 'active' : '' ?>">
                                    <input type="radio" name="pickup_slot" class="form-check-input m-0" value="<?= e($slot) ?>" <?= $idx === 0 ? 'checked' : '' ?>>
                                    <span class="small fw-semibold"><?= e($slot) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Catatan Khusus untuk Kurir / Toko (Opsional)</label>
                    <textarea name="order_notes" id="inputOrderNotes" rows="3" class="form-control rounded-3" placeholder="Contoh: Tolong jemput sebelum jam 10, pakaian putih dipisah ya..."></textarea>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" id="btnBackToStep2">
                        <i class="bi bi-arrow-left me-1"></i> Kembali ke Alamat
                    </button>
                    <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" id="btnNextToStep4">
                        Lanjut: Ringkasan & Konfirmasi <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- STEP 4: RINGKASAN & KONFIRMASI                                      -->
        <!-- =================================================================== -->
        <div class="checkout-step-content d-none" id="stepContent4">
            <div class="row g-4">
                <!-- Left Column: Summary Card -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-receipt text-primary me-2"></i>Rincian Pesanan Laundry</h5>

                        <!-- Store details -->
                        <div class="p-3 bg-light rounded-4 mb-3 border">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-shop text-primary"></i>
                                <strong class="text-dark" id="summaryStoreName"><?= e($selectedStore['name']) ?></strong>
                            </div>
                            <small class="text-muted d-block" id="summaryStoreInfo"><?= e($selectedStore['operational_hours']) ?> • <?= e($selectedStore['phone']) ?></small>
                        </div>

                        <!-- Chosen Services List -->
                        <h6 class="fw-bold text-dark mb-2 fs-7 text-uppercase letter-spacing-1">Layanan yang Dipesan:</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0" id="summaryServicesTable">
                                <thead class="table-light fs-8 text-muted">
                                    <tr>
                                        <th>Layanan</th>
                                        <th class="text-center">Estimasi Qty</th>
                                        <th class="text-end">Harga</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="summaryServicesBody">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Pickup & Schedule Details -->
                        <div class="row g-2 mb-3 small">
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-3 h-100">
                                    <strong class="text-dark d-block mb-1"><i class="bi bi-geo-alt text-danger me-1"></i>Alamat Penjemputan:</strong>
                                    <span class="text-muted" id="summaryPickupAddress">-</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-light rounded-3 h-100">
                                    <strong class="text-dark d-block mb-1"><i class="bi bi-clock-history text-primary me-1"></i>Jadwal Penjemputan:</strong>
                                    <span class="text-muted" id="summaryPickupSchedule">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Transparent Estimation Disclaimer -->
                        <div class="alert alert-info border-0 rounded-4 p-3 small mb-0">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Catatan Estimasi:</strong> Total harga di atas merupakan estimasi awal. Tagihan final akan dihitung secara transparan dan akurat oleh toko setelah cucian selesai ditimbang di outlet laundry.
                        </div>
                    </div>
                </div>

                <!-- Right Column: Payment Method & Submit -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-wallet2 text-success me-2"></i>Metode Pembayaran Awal</h5>

                        <div class="d-flex flex-column gap-3 mb-4">
                            <!-- QRIS Option -->
                            <label class="card border rounded-4 p-3 cursor-pointer payment-option-card active border-primary bg-primary-subtle bg-opacity-10" for="pay_qris">
                                <div class="d-flex align-items-center gap-3">
                                    <input class="form-check-input m-0" type="radio" name="payment_method" id="pay_qris" value="ONLINE_QRIS" checked>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-dark">QRIS (Otomatis / Cashless)</strong>
                                            <span class="badge bg-warning text-dark rounded-pill fs-8">Rekomendasi</span>
                                        </div>
                                        <small class="text-muted d-block">QR Code terbit setelah penimbangan (Batas waktu 1 jam)</small>
                                    </div>
                                </div>
                            </label>

                            <!-- Cash Option -->
                            <?php if (\App\Models\Setting::get('payment_cash_enabled', true)): ?>
                            <label class="card border rounded-4 p-3 cursor-pointer payment-option-card border-light-subtle" for="pay_cash">
                                <div class="d-flex align-items-center gap-3">
                                    <input class="form-check-input m-0" type="radio" name="payment_method" id="pay_cash" value="CASH">
                                    <div class="flex-grow-1">
                                        <strong class="text-dark">Tunai (Bayar Saat Kurir Antar)</strong>
                                        <small class="text-muted d-block">Bayar langsung uang pas ke kurir mitra kami</small>
                                    </div>
                                </div>
                            </label>
                            <?php endif; ?>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="border-top pt-3 mb-4">
                            <div class="d-flex justify-content-between text-muted small mb-2">
                                <span>Estimasi Total Layanan</span>
                                <span id="summaryPriceSubtotal">Rp 0</span>
                            </div>
                            <div class="d-flex justify-content-between text-muted small mb-2">
                                <span>Ongkos Kirim Jemput & Antar</span>
                                <span class="badge bg-success-subtle text-success">Gratis Promo</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <strong class="text-dark fs-6">Estimasi Total Tagihan</strong>
                                <h3 class="fw-extrabold text-primary mb-0" id="summaryGrandTotal">Rp 0</h3>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm" id="btnSubmitOrder">
                                <i class="bi bi-bag-check-fill me-1"></i> Buat Pesanan Sekarang
                            </button>
                            <button type="button" class="btn btn-light rounded-pill" id="btnBackToStep3">
                                <i class="bi bi-arrow-left me-1"></i> Ubah Jadwal
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Vanilla JS Multi-Step State & Realtime Calculator -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var currentStep = 1;
    var selectedItems = {};

    function formatRupiah(number) {
        return 'Rp ' + number.toLocaleString('id-ID');
    }

    // 1. Service Selection & Subtotal Calculation
    function updateCalculations() {
        var subtotal = 0;
        var count = 0;

        document.querySelectorAll('.service-checkbox:checked').forEach(function(cb) {
            var id = cb.getAttribute('data-id');
            var name = cb.getAttribute('data-name');
            var price = parseFloat(cb.getAttribute('data-price'));
            var unit = cb.getAttribute('data-unit');
            var qtyInput = document.getElementById('qty_input_' + id);
            var qty = parseFloat(qtyInput.value) || 0;
            var itemTotal = price * qty;

            subtotal += itemTotal;
            count++;

            document.getElementById('subtotal_' + id).innerText = formatRupiah(itemTotal);

            selectedItems[id] = {
                id: id,
                name: name,
                price: price,
                unit: unit,
                qty: qty,
                subtotal: itemTotal
            };
        });

        document.querySelectorAll('.service-checkbox:not(:checked)').forEach(function(cb) {
            var id = cb.getAttribute('data-id');
            delete selectedItems[id];
        });

        document.getElementById('badgeSelectedCount').innerText = count + ' Layanan Dipilih';
        document.getElementById('liveEstimatedSubtotal').innerText = formatRupiah(subtotal);
        document.getElementById('summaryPriceSubtotal').innerText = formatRupiah(subtotal);
        document.getElementById('summaryGrandTotal').innerText = formatRupiah(subtotal);
    }

    document.querySelectorAll('.service-checkbox').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var id = this.getAttribute('data-id');
            var qtyBox = document.getElementById('qty_box_' + id);
            var card = this.closest('.service-item-card');

            if (this.checked) {
                qtyBox.classList.remove('d-none');
                card.classList.add('border-primary', 'bg-primary-subtle', 'bg-opacity-10');
            } else {
                qtyBox.classList.add('d-none');
                card.classList.remove('border-primary', 'bg-primary-subtle', 'bg-opacity-10');
            }
            updateCalculations();
        });
    });

    // Qty Increment & Decrement Buttons
    document.querySelectorAll('.btn-increment').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = document.querySelector(this.getAttribute('data-target'));
            var step = parseFloat(input.getAttribute('step')) || 1;
            input.value = (parseFloat(input.value) + step).toFixed(1);
            updateCalculations();
        });
    });

    document.querySelectorAll('.btn-decrement').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = document.querySelector(this.getAttribute('data-target'));
            var step = parseFloat(input.getAttribute('step')) || 1;
            var min = parseFloat(input.getAttribute('min')) || 1;
            var newVal = parseFloat(input.value) - step;
            if (newVal >= min) {
                input.value = newVal.toFixed(1);
                updateCalculations();
            }
        });
    });

    document.querySelectorAll('.qty-input').forEach(function(input) {
        input.addEventListener('input', updateCalculations);
    });

    // 2. Step Navigation
    function goToStep(step) {
        document.querySelectorAll('.checkout-step-content').forEach(function(el) {
            el.classList.add('d-none');
        });
        document.getElementById('stepContent' + step).classList.remove('d-none');

        document.querySelectorAll('.stepper-step').forEach(function(s) {
            var sNum = parseInt(s.getAttribute('data-step'));
            var title = s.querySelector('.step-title');
            if (sNum === step) {
                s.classList.add('active');
                title.classList.remove('text-muted');
                title.classList.add('text-primary');
            } else if (sNum < step) {
                s.classList.remove('active');
                title.classList.remove('text-primary');
                title.classList.add('text-success');
            } else {
                s.classList.remove('active');
                title.classList.remove('text-primary', 'text-success');
                title.classList.add('text-muted');
            }
        });

        currentStep = step;
        window.scrollTo({ top: 0, behavior: 'smooth' });

        if (step === 4) {
            populateSummary();
        }
    }

    // Step 1 -> 2
    document.getElementById('btnNextToStep2').addEventListener('click', function() {
        var checkedCount = document.querySelectorAll('.service-checkbox:checked').length;
        if (checkedCount === 0) {
            alert('Silakan pilih minimal 1 layanan laundry terlebih dahulu.');
            return;
        }
        goToStep(2);
    });

    // Step 2 -> 1
    document.getElementById('btnBackToStep1').addEventListener('click', function() {
        goToStep(1);
    });

    // Step 2 -> 3
    document.getElementById('btnNextToStep3').addEventListener('click', function() {
        var checkedAddr = document.querySelector('.pickup-addr-radio:checked');
        if (!checkedAddr) {
            alert('Silakan pilih alamat penjemputan.');
            return;
        }
        goToStep(3);
    });

    // Step 3 -> 2
    document.getElementById('btnBackToStep2').addEventListener('click', function() {
        goToStep(2);
    });

    // Step 3 -> 4
    document.getElementById('btnNextToStep4').addEventListener('click', function() {
        var dateVal = document.getElementById('inputPickupDate').value;
        if (!dateVal) {
            alert('Silakan tentukan tanggal penjemputan.');
            return;
        }
        goToStep(4);
    });

    // Step 4 -> 3
    document.getElementById('btnBackToStep3').addEventListener('click', function() {
        goToStep(3);
    });

    // Toggle Delivery Address Box
    var sameDelivCheck = document.getElementById('sameDeliveryAddress');
    var delivSection = document.getElementById('deliveryAddressSection');
    if (sameDelivCheck && delivSection) {
        sameDelivCheck.addEventListener('change', function() {
            if (this.checked) {
                delivSection.classList.add('d-none');
            } else {
                delivSection.classList.remove('d-none');
            }
        });
    }

    // Payment Option Card Highlight
    document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.payment-option-card').forEach(function(card) {
                card.classList.remove('border-primary', 'bg-primary-subtle', 'bg-opacity-10');
            });
            this.closest('.payment-option-card').classList.add('border-primary', 'bg-primary-subtle', 'bg-opacity-10');
        });
    });

    // Populate Summary on Step 4
    function populateSummary() {
        var tbody = document.getElementById('summaryServicesBody');
        tbody.innerHTML = '';

        for (var key in selectedItems) {
            var item = selectedItems[key];
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><strong class="text-dark">' + item.name + '</strong></td>' +
                           '<td class="text-center">' + item.qty + ' ' + item.unit + '</td>' +
                           '<td class="text-end">' + formatRupiah(item.price) + '</td>' +
                           '<td class="text-end fw-bold text-primary">' + formatRupiah(item.subtotal) + '</td>';
            tbody.appendChild(tr);
        }

        var selectedAddr = document.querySelector('.pickup-addr-radio:checked');
        if (selectedAddr) {
            document.getElementById('summaryPickupAddress').innerHTML = '<strong>' + selectedAddr.getAttribute('data-label') + '</strong> - ' + selectedAddr.getAttribute('data-full');
        }

        var pickupDate = document.getElementById('inputPickupDate').value;
        var selectedSlot = document.querySelector('input[name="pickup_slot"]:checked');
        var slotVal = selectedSlot ? selectedSlot.value : '08:00 - 10:00 WIB';
        document.getElementById('summaryPickupSchedule').innerText = pickupDate + ' (Slot: ' + slotVal + ')';
    }

    // Auto-select first service by default if none checked
    var firstCheck = document.querySelector('.service-checkbox');
    if (firstCheck && document.querySelectorAll('.service-checkbox:checked').length === 0) {
        firstCheck.checked = true;
        firstCheck.dispatchEvent(new Event('change'));
    }
});
</script>

<style>
.cursor-pointer { cursor: pointer; }
.letter-spacing-1 { letter-spacing: 0.05em; }
.transition { transition: all 0.2s ease-in-out; }
.hover-elevate:hover { transform: translateY(-2px); }
.stepper-step .step-num {
    width: 38px;
    height: 38px;
    background-color: #f1f5f9;
    color: #64748b;
}
.stepper-step.active .step-num {
    background-color: #0d6efd;
    color: #fff;
}
</style>
