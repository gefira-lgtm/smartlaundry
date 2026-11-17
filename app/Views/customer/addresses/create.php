<div class="container py-4">
    <!-- Header Navigation -->
    <div class="mb-4">
        <a href="<?= url('customer/addresses') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mb-2">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Buku Alamat
        </a>
        <h3 class="fw-bold text-dark mb-0">Tambah Alamat Baru</h3>
        <small class="text-muted">Tentukan titik penjemputan cucian dengan presisi menggunakan peta interaktif.</small>
    </div>

    <form action="<?= url('customer/addresses') ?>" method="POST">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- Left: Form Fields -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-card-text text-primary me-2"></i>Informasi Alamat</h5>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Label Alamat <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill label-quick" data-label="Rumah">Rumah</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill label-quick" data-label="Kantor">Kantor</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill label-quick" data-label="Kos">Kos</button>
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill label-quick" data-label="Apartemen">Apartemen</button>
                        </div>
                        <input type="text" name="label" id="addressLabel" class="form-control rounded-3" placeholder="Contoh: Rumah, Kos Putri Melati" value="Rumah" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Penerima / Penghuni <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_name" class="form-control rounded-3" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor Telepon / WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control rounded-3" value="<?= e($user['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat Lengkap (Jalan, No Rumah) <span class="text-danger">*</span></label>
                        <textarea name="full_address" id="fullAddress" rows="3" class="form-control rounded-3" placeholder="Jl. Kemang Raya No. 45B, RT 03 / RW 02..." required></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">RT</label>
                            <input type="text" name="rt" class="form-control rounded-3" placeholder="001">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">RW</label>
                            <input type="text" name="rw" class="form-control rounded-3" placeholder="005">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Kelurahan</label>
                            <input type="text" name="kelurahan" class="form-control rounded-3" placeholder="Bangka">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold">Kecamatan</label>
                            <input type="text" name="kecamatan" class="form-control rounded-3" placeholder="Mampang">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Kota / Kabupaten</label>
                            <input type="text" name="kota" class="form-control rounded-3" value="Jakarta Selatan">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Provinsi</label>
                            <input type="text" name="provinsi" class="form-control rounded-3" value="DKI Jakarta">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Kode Pos</label>
                            <input type="text" name="postal_code" class="form-control rounded-3" placeholder="12730">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Patokan / Catatan Lokasi Kurir</label>
                        <input type="text" name="notes" class="form-control rounded-3" placeholder="Pagar hitam samping warung Madura, ada pohon mangga">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="isPrimaryCheck" checked>
                        <label class="form-check-label small fw-bold text-dark" for="isPrimaryCheck">
                            Jadikan sebagai Alamat Utama penjemputan laundry
                        </label>
                    </div>
                </div>
            </div>

            <!-- Right: Interactive Leaflet Map Picker -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Pin Point Lokasi Peta</h5>
                            <small class="text-muted">Geser pin merah atau klik peta untuk menentukan titik koordinat GPS penjemputan.</small>
                        </div>
                        <button type="button" id="btnGetCurrentLocation" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="bi bi-crosshair me-1"></i> Lokasi Saya
                        </button>
                    </div>

                    <!-- Map Container -->
                    <div id="addressMap" class="rounded-4 border shadow-inner mb-3" style="height: 380px; width: 100%;"></div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fs-8 text-muted fw-bold">Latitude</label>
                            <input type="text" name="latitude" id="inputLat" class="form-control form-control-sm font-monospace bg-light rounded-3" value="-6.20000000" readonly>
                        </div>
                        <div class="col-6">
                            <label class="form-label fs-8 text-muted fw-bold">Longitude</label>
                            <input type="text" name="longitude" id="inputLng" class="form-control form-control-sm font-monospace bg-light rounded-3" value="106.81666600" readonly>
                        </div>
                    </div>
                    <small class="text-muted fs-8 mt-2 d-block"><i class="bi bi-info-circle me-1"></i> Titik koordinat ini akan digunakan kurir untuk navigasi GPS langsung ke pintu Anda.</small>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= url('customer/addresses') ?>" class="btn btn-light rounded-pill px-4">Batal</a>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Alamat
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Leaflet Map Script Integration -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quick label buttons
    document.querySelectorAll('.label-quick').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('addressLabel').value = this.getAttribute('data-label');
        });
    });

    // Default Coordinates (Jakarta Center)
    var defaultLat = -6.20000000;
    var defaultLng = 106.81666600;

    var map = L.map('addressMap').setView([defaultLat, defaultLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var marker = L.marker([defaultLat, defaultLng], {
        draggable: true
    }).addTo(map);

    function updateCoords(lat, lng) {
        document.getElementById('inputLat').value = lat.toFixed(8);
        document.getElementById('inputLng').value = lng.toFixed(8);
    }

    marker.on('dragend', function(e) {
        var pos = marker.getLatLng();
        updateCoords(pos.lat, pos.lng);
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng);
    });

    // Geolocation Browser API
    document.getElementById('btnGetCurrentLocation').addEventListener('click', function() {
        if (navigator.geolocation) {
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mendeteksi...';
            var self = this;
            navigator.geolocation.getCurrentPosition(function(pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
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
});
</script>
