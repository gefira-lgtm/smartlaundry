<div class="container py-4">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/store') ?>" class="text-decoration-none">Dashboard Toko</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengaturan Profil</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-0">Profil & Lokasi Outlet Toko</h3>
        </div>
        <div class="d-flex align-items-center gap-2">
            <!-- Read-only Verification Badge -->
            <?php if (!empty($store['is_verified'])): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fs-7">
                    <i class="bi bi-patch-check-fill me-1 text-success"></i> Toko Terverifikasi
                </span>
            <?php else: ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill fs-7">
                    <i class="bi bi-clock-history me-1"></i> Menunggu Verifikasi Platform
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert / Flash Messages -->
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

    <form action="<?= url('store/profile') ?>" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- Left Column: Informasi Toko & Upload Media -->
            <div class="col-lg-6">
                <!-- Data Toko Card -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-shop text-primary me-2"></i>Informasi Umum Toko</h5>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Toko Laundry <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control rounded-3" value="<?= e($store['name'] ?? '') ?>" required placeholder="Contoh: CleanWash Express">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor WhatsApp / Telepon <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                <input type="text" name="phone" class="form-control rounded-end-3" value="<?= e($store['phone'] ?? '') ?>" required placeholder="081234567890">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Jam Operasional <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-clock"></i></span>
                                <input type="text" name="operational_hours" class="form-control rounded-end-3" value="<?= e($store['operational_hours'] ?? '08:00 - 20:00 WIB') ?>" required placeholder="08:00 - 20:00 WIB">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deskripsi & Keunggulan Toko</label>
                        <textarea name="description" class="form-control rounded-3" rows="3" placeholder="Jelaskan spesialisasi toko Anda, jenis deterjen premium, garansi kebersihan, dll..."><?= e($store['description'] ?? '') ?></textarea>
                    </div>

                    <div class="p-3 bg-light rounded-3 mb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-shield-lock-fill text-muted fs-5"></i>
                                <div>
                                    <div class="fw-bold fs-7 text-dark">Status Verifikasi Outlet</div>
                                    <small class="text-muted">Perubahan status verifikasi hanya dapat dilakukan oleh Admin Platform.</small>
                                </div>
                            </div>
                            <?php if (!empty($store['is_verified'])): ?>
                                <span class="badge bg-success text-white rounded-pill px-3 py-1">Aktif & Terverifikasi</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark rounded-pill px-3 py-1">Pending Review</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Media Upload Card (Logo & Banner) -->
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-image text-primary me-2"></i>Logo & Banner Toko</h5>

                    <!-- Logo Upload -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold d-block">Logo Toko (Rasio 1:1, Max 2MB)</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="border rounded-4 p-1 bg-light position-relative shadow-sm" style="width: 80px; height: 80px; overflow: hidden;">
                                <?php 
                                    $logoSrc = !empty($store['logo']) ? (str_starts_with($store['logo'], 'http') || str_starts_with($store['logo'], 'uploads/') ? url($store['logo']) : url('uploads/stores/' . $store['logo'])) : 'https://images.unsplash.com/photo-1545173168-9f1947eebb7f?w=150';
                                ?>
                                <img id="previewLogo" src="<?= $logoSrc ?>" alt="Logo Toko" class="w-100 h-100 object-fit-cover rounded-3" onerror="this.src='https://images.unsplash.com/photo-1545173168-9f1947eebb7f?w=150'">
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" name="logo" id="inputLogo" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted fs-8">Format JPG, PNG, atau WEBP.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Banner Upload -->
                    <div class="mb-2">
                        <label class="form-label small fw-bold d-block">Banner Header Toko (Rasio 16:9, Max 2MB)</label>
                        <div class="border rounded-4 bg-light p-1 mb-2 position-relative shadow-sm" style="height: 120px; overflow: hidden;">
                            <?php 
                                $bannerSrc = !empty($store['banner']) ? (str_starts_with($store['banner'], 'http') || str_starts_with($store['banner'], 'uploads/') ? url($store['banner']) : url('uploads/stores/' . $store['banner'])) : 'https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=600';
                            ?>
                            <img id="previewBanner" src="<?= $bannerSrc ?>" alt="Banner Toko" class="w-100 h-100 object-fit-cover rounded-3" onerror="this.src='https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=600'">
                        </div>
                        <input type="file" name="banner" id="inputBanner" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted fs-8">Ditampilkan di header profil publik toko.</small>
                    </div>
                </div>
            </div>

            <!-- Right Column: Alamat Outlet & Interactive Leaflet Map -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Alamat Fisik & Titik Peta</h5>
                        <button type="button" id="btnGetCurrentLocation" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-crosshair me-1"></i> Lokasi Saya
                        </button>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Lengkap Outlet <span class="text-danger">*</span></label>
                        <textarea name="full_address" class="form-control rounded-3" rows="2" required placeholder="Jl. Fatmawati Raya No. 45..."><?= e($store['full_address'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label fs-8 fw-bold">RT</label>
                            <input type="text" name="rt" class="form-control form-control-sm rounded-3" value="<?= e($store['rt'] ?? '') ?>" placeholder="005">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fs-8 fw-bold">RW</label>
                            <input type="text" name="rw" class="form-control form-control-sm rounded-3" value="<?= e($store['rw'] ?? '') ?>" placeholder="002">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fs-8 fw-bold">Kelurahan</label>
                            <input type="text" name="kelurahan" class="form-control form-control-sm rounded-3" value="<?= e($store['kelurahan'] ?? '') ?>" placeholder="Gandaria Selatan">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label fs-8 fw-bold">Kecamatan</label>
                            <input type="text" name="kecamatan" class="form-control form-control-sm rounded-3" value="<?= e($store['kecamatan'] ?? '') ?>" placeholder="Cilandak">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label class="form-label fs-8 fw-bold">Kota / Kabupaten</label>
                            <input type="text" name="kota" class="form-control form-control-sm rounded-3" value="<?= e($store['kota'] ?? 'Jakarta Selatan') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-8 fw-bold">Provinsi</label>
                            <input type="text" name="provinsi" class="form-control form-control-sm rounded-3" value="<?= e($store['provinsi'] ?? 'DKI Jakarta') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-8 fw-bold">Kode Pos</label>
                            <input type="text" name="postal_code" class="form-control form-control-sm rounded-3" value="<?= e($store['postal_code'] ?? '') ?>" placeholder="12420">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-8 fw-bold">Patokan / Catatan Alamat</label>
                        <input type="text" name="notes" class="form-control form-control-sm rounded-3" value="<?= e($store['address_notes'] ?? '') ?>" placeholder="Sebelah Indomaret, seberang masjid...">
                    </div>

                    <!-- Leaflet Map Container -->
                    <label class="form-label small fw-bold mb-2">Pin Lokasi GPS Outlet di Peta</label>
                    <div id="storeMap" class="rounded-4 border shadow-inner mb-3" style="height: 280px; width: 100%;"></div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-8 text-muted fw-bold">Latitude</label>
                            <input type="text" name="latitude" id="inputLat" class="form-control form-control-sm font-monospace bg-light rounded-3" value="<?= e($store['latitude'] ?? '-6.20000000') ?>" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 text-muted fw-bold">Longitude</label>
                            <input type="text" name="longitude" id="inputLng" class="form-control form-control-sm font-monospace bg-light rounded-3" value="<?= e($store['longitude'] ?? '106.81666600') ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url('dashboard/store') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Profil
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Leaflet Map & Image Preview Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Image Previews
    const inputLogo = document.getElementById('inputLogo');
    const previewLogo = document.getElementById('previewLogo');
    if (inputLogo && previewLogo) {
        inputLogo.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewLogo.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }

    const inputBanner = document.getElementById('inputBanner');
    const previewBanner = document.getElementById('previewBanner');
    if (inputBanner && previewBanner) {
        inputBanner.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewBanner.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // 2. Leaflet Map Initialization
    const initialLat = parseFloat(document.getElementById('inputLat').value) || -6.20000000;
    const initialLng = parseFloat(document.getElementById('inputLng').value) || 106.81666600;

    const map = L.map('storeMap').setView([initialLat, initialLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const marker = L.marker([initialLat, initialLng], {
        draggable: true
    }).addTo(map);

    function updateCoords(lat, lng) {
        document.getElementById('inputLat').value = lat.toFixed(8);
        document.getElementById('inputLng').value = lng.toFixed(8);
    }

    marker.on('dragend', function(e) {
        const pos = marker.getLatLng();
        updateCoords(pos.lat, pos.lng);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng);
    });

    // Geolocation Button
    const btnGeo = document.getElementById('btnGetCurrentLocation');
    if (btnGeo) {
        btnGeo.addEventListener('click', function() {
            if (navigator.geolocation) {
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mendeteksi...';
                const self = this;
                navigator.geolocation.getCurrentPosition(function(pos) {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    map.setView([lat, lng], 16);
                    marker.setLatLng([lat, lng]);
                    updateCoords(lat, lng);
                    self.innerHTML = '<i class="bi bi-crosshair me-1"></i> Lokasi Saya';
                }, function(err) {
                    alert('Tidak dapat mendeteksi lokasi otomatis. Silakan geser pin pada peta.');
                    self.innerHTML = '<i class="bi bi-crosshair me-1"></i> Lokasi Saya';
                });
            } else {
                alert('Browser Anda tidak mendukung geolokasi otomatis.');
            }
        });
    }
});
</script>
