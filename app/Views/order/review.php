<div class="container py-4">
    <!-- Header -->
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= url('dashboard/customer') ?>" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Beri Ulasan & Rating</li>
            </ol>
        </nav>
        <h3 class="fw-bold text-dark mb-0">Ulasan & Rating Kepuasan Pelanggan</h3>
        <small class="text-muted">Bantu pelanggan lain dan dukung mitra toko <strong><?= e($order['store_name']) ?></strong> dengan ulasan jujur Anda.</small>
    </div>

    <!-- Flash Messages -->
    <?php if ($flashError = get_flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($flashError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                <!-- Order Summary Card -->
                <div class="d-flex flex-wrap justify-content-between align-items-center p-3 bg-light rounded-4 mb-4 border border-light-subtle">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                            <i class="bi bi-shop fs-4"></i>
                        </div>
                        <div>
                            <div class="fw-bold text-dark"><?= e($order['store_name']) ?></div>
                            <small class="text-muted">Order: <span class="badge bg-light text-dark border font-monospace"><?= e($order['order_code']) ?></span> • <?= e($order['item_summary'] ?? 'Paket Cuci') ?></small>
                        </div>
                    </div>
                    <span class="badge bg-success text-white rounded-pill px-3 py-2 fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> SELESAI
                    </span>
                </div>

                <form action="<?= url('order/' . $order['id'] . '/review') ?>" method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <!-- Star Rating Widget -->
                    <div class="text-center my-4 p-4 bg-light bg-opacity-50 rounded-4 border border-light-subtle">
                        <label class="form-label fw-bold text-dark fs-6 d-block mb-2">Seberapa puas Anda dengan hasil cucian?</label>
                        
                        <div id="starRatingContainer" class="d-inline-flex gap-2 justify-content-center my-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill star-icon fs-1 text-warning cursor-pointer transition" data-rating="<?= $i ?>" style="cursor: pointer; transition: transform 0.2s;"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="inputRating" value="5">
                        
                        <div id="ratingLabel" class="fw-bold text-primary mt-2 fs-6">
                            ⭐⭐⭐⭐⭐ 5 / 5 — Luar Biasa Bersih, Wangi, & Cepat!
                        </div>
                    </div>

                    <!-- Comment -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Testimoni & Pengalaman Anda <span class="text-danger">*</span></label>
                        <textarea name="comment" class="form-control rounded-3" rows="4" required placeholder="Ceritakan bagaimana kebersihan pakaian, aroma wangi, kerapian lipatan/setrika, serta keramahan kurir penjemputan..."></textarea>
                    </div>

                    <!-- Photo Upload (Optional) -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold d-block">Foto Hasil Cucian (Opsional, Max 2MB)</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="border rounded-4 bg-light p-1 shadow-sm d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; overflow: hidden;">
                                <img id="previewPhoto" src="https://images.unsplash.com/photo-1545173168-9f1947eebb7f?w=150" alt="Preview Foto" class="w-100 h-100 object-fit-cover rounded-3 opacity-50">
                            </div>
                            <div class="flex-grow-1">
                                <input type="file" name="photo" id="inputPhoto" class="form-control form-control-sm rounded-3" accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted fs-8">Upload foto pakaian yang rapi atau bersih untuk menambah reputasi toko.</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= url('dashboard/customer') ?>" class="btn btn-light rounded-pill px-4">Kembali</a>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                            <i class="bi bi-send-fill me-1"></i> Kirim Ulasan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Star Rating & Preview JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-icon');
    const inputRating = document.getElementById('inputRating');
    const ratingLabel = document.getElementById('ratingLabel');

    const labels = {
        1: '⭐ 1 / 5 — Sangat Kecewa (Cucian Kurang Bersih)',
        2: '⭐⭐ 2 / 5 — Kurang Puas (Ada Catatan Kualitas)',
        3: '⭐⭐⭐ 3 / 5 — Cukup Bagus',
        4: '⭐⭐⭐⭐ 4 / 5 — Puas & Bersih Rapi',
        5: '⭐⭐⭐⭐⭐ 5 / 5 — Luar Biasa Bersih, Wangi, & Cepat!'
    };

    function highlightStars(val) {
        stars.forEach(star => {
            const starVal = parseInt(star.getAttribute('data-rating'));
            if (starVal <= val) {
                star.classList.remove('bi-star', 'text-muted');
                star.classList.add('bi-star-fill', 'text-warning');
            } else {
                star.classList.remove('bi-star-fill', 'text-warning');
                star.classList.add('bi-star', 'text-muted');
            }
        });
        if (labels[val]) {
            ratingLabel.innerText = labels[val];
        }
    }

    stars.forEach(star => {
        star.addEventListener('mouseenter', function() {
            const val = parseInt(this.getAttribute('data-rating'));
            highlightStars(val);
        });

        star.addEventListener('click', function() {
            const val = parseInt(this.getAttribute('data-rating'));
            inputRating.value = val;
            highlightStars(val);
        });
    });

    document.getElementById('starRatingContainer').addEventListener('mouseleave', function() {
        const currentVal = parseInt(inputRating.value) || 5;
        highlightStars(currentVal);
    });

    // Photo Preview
    const inputPhoto = document.getElementById('inputPhoto');
    const previewPhoto = document.getElementById('previewPhoto');
    if (inputPhoto && previewPhoto) {
        inputPhoto.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewPhoto.src = e.target.result;
                    previewPhoto.classList.remove('opacity-50');
                }
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>
