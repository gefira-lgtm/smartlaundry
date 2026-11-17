<?php
$appName = \App\Models\Setting::get('app_name', 'SmartLaundry');
$appTagline = \App\Models\Setting::get('app_tagline', 'Platform Marketplace Laundry Modern & Cepat');
$message = \App\Models\Setting::get('maintenance_message', 'Sistem SmartLaundry sedang dalam proses pemeliharaan berkala untuk peningkatan kualitas layanan. Kami akan segera kembali! Halaman login admin tetap dapat diakses.');
$contactPhone = \App\Models\Setting::get('contact_phone', '081234567890');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Sistem Dalam Pemeliharaan | <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f8fafc;
            padding: 24px;
        }
        .maintenance-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 620px;
            width: 100%;
            padding: 48px 36px;
            text-align: center;
        }
        .icon-pulse {
            width: 88px;
            height: 88px;
            background: rgba(234, 179, 8, 0.15);
            color: #eab308;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2.75rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.4); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 20px rgba(234, 179, 8, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(234, 179, 8, 0.2);
            color: #fde047;
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-owner-login {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border: none;
            color: #fff;
            padding: 12px 28px;
            border-radius: 9999px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-owner-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(59, 130, 246, 0.5);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <div class="icon-pulse">
            <i class="bi bi-tools"></i>
        </div>
        <div class="status-badge">
            <span class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
            MODE PEMELIHARAAN AKTIF (503)
        </div>
        <h2 class="fw-bold mb-2"><?= e($appName) ?> Sedang Ditingkatkan</h2>
        <p class="text-secondary mb-4"><?= e($appTagline) ?></p>

        <div class="p-4 rounded-4 bg-dark bg-opacity-50 border border-secondary border-opacity-25 mb-4 text-start">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-info-circle-fill text-warning fs-5 flex-shrink-0 mt-1"></i>
                <div>
                    <div class="fw-semibold text-light mb-1">Informasi Pemeliharaan:</div>
                    <div class="text-secondary small leading-relaxed">
                        <?= nl2br(e($message)) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
            <a href="<?= url('login') ?>" class="btn-owner-login d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-shield-lock-fill"></i> Masuk Pengelola (Owner / Admin)
            </a>
            <?php if (!empty($contactPhone)): ?>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $contactPhone) ?>" target="_blank" class="btn btn-outline-secondary rounded-pill px-4 d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-whatsapp text-success"></i> Hubungi Dukungan CS
                </a>
            <?php endif; ?>
        </div>

        <div class="mt-4 pt-3 border-top border-secondary border-opacity-25 text-secondary fs-8">
            &copy; <?= date('Y') ?> <?= e($appName) ?>. Sistem akan segera beroperasi normal kembali.
        </div>
    </div>
</body>
</html>
