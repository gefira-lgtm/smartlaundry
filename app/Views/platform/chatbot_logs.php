<?php
/**
 * Platform Chatbot Conversation Monitoring View
 * Khusus untuk owner_platform & admin_owner
 */

if (!function_exists('clBuildQuery')) {
    function clBuildQuery(array $filters, int $page): string {
        $params = array_filter($filters, fn($v) => $v !== '' && $v !== null && $v !== 0);
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
}
?>

<div class="container-fluid py-4 px-3 px-md-4" style="max-width: 1600px;">

    <!-- ======================================================= HEADER -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= url('dashboard/platform') ?>" class="text-decoration-none">Dashboard Platform</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Monitoring Chatbot</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold mb-0 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-robot text-primary"></i> Monitoring Riwayat Percakapan Chatbot
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Pengawasan komprehensif seluruh interaksi pengguna dengan SmartBot. Data sensitif (telepon, email, kartu/rekening) disamarkan otomatis demi privasi.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= url('platform/chatbot-logs/export' . clBuildQuery($filters, 1)) ?>" id="btn-export-csv"
               class="btn btn-success rounded-pill px-3 btn-sm shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Ekspor CSV
            </a>
            <a href="<?= url('platform/chatbot-logs') ?>" class="btn btn-outline-primary rounded-pill px-3 btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </a>
            <a href="<?= url('dashboard/platform') ?>" class="btn btn-secondary rounded-pill px-3 btn-sm">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- ======================================================= SUMMARY CARDS -->
    <div class="row g-3 mb-4">
        <!-- Total Pesan -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, rgba(13,110,253,0.12), #ffffff);">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="bg-primary text-white p-3 rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                        <i class="bi bi-chat-dots-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Pesan</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($summary['total_messages'] ?? 0) ?></h4>
                        <small class="text-muted" style="font-size:.7rem;">Tersimpan di database</small>
                    </div>
                </div>
            </div>
        </div>
        <!-- Pengguna Unik -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, rgba(25,135,84,0.12), #ffffff);">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="bg-success text-white p-3 rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                        <i class="bi bi-people-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Pengguna Unik</div>
                        <h4 class="fw-bold mb-0 text-dark"><?= number_format($summary['unique_users'] ?? 0) ?></h4>
                        <small class="text-muted" style="font-size:.7rem;">Customer, Kurir, & Admin</small>
                    </div>
                </div>
            </div>
        </div>
        <!-- Fallback Count -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, rgba(220,53,69,0.12), #ffffff);">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="bg-danger text-white p-3 rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Pertanyaan Fallback</div>
                        <h4 class="fw-bold mb-0 text-danger"><?= number_format($summary['fallback_count'] ?? 0) ?></h4>
                        <small class="text-danger fw-semibold" style="font-size:.7rem;">
                            <a href="<?= url('platform/chatbot-logs?only_fallback=1') ?>" class="text-danger text-decoration-underline">Filter Fallback</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <!-- Retensi & Privasi -->
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, rgba(111,66,193,0.12), #ffffff);">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="bg-purple text-white p-3 rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:#6f42c1;">
                        <i class="bi bi-shield-check fs-5 text-white"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Privasi & Retensi</div>
                        <h4 class="fw-bold mb-0 fs-6 text-dark">PII Masked</h4>
                        <small class="text-muted" style="font-size:.7rem;">Auto-purge 90 hari via cron</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================= TOP INTENTS & FALLBACK ANALYSIS -->
    <div class="row g-4 mb-4">
        <!-- Top 10 Intent & Top Pertanyaan -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <ul class="nav nav-pills card-header-pills small" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-3 fw-semibold rounded-pill" id="pills-intent-tab" data-bs-toggle="pill" data-bs-target="#pills-intent" type="button" role="tab">
                                <i class="bi bi-bar-chart-line me-1"></i> Top 10 Intent
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-3 fw-semibold rounded-pill" id="pills-questions-tab" data-bs-toggle="pill" data-bs-target="#pills-questions" type="button" role="tab">
                                <i class="bi bi-chat-quote me-1"></i> 10 Pertanyaan Terbanyak
                            </button>
                        </li>
                    </ul>
                    <span class="badge bg-primary-subtle text-primary border small">Frekuensi</span>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="pills-tabContent">
                        <!-- Top Intents -->
                        <div class="tab-pane fade show active" id="pills-intent" role="tabpanel">
                            <?php if (empty($summary['top_intents'])): ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i> Belum ada data intent.
                                </div>
                            <?php else: ?>
                                <?php
                                $maxCnt = (int)($summary['top_intents'][0]['cnt'] ?? 1);
                                $intentColors = [
                                    'GREETING'=>'primary','THANKS'=>'success','FAREWELL'=>'info',
                                    'ORDER_STATUS'=>'warning','FAQ_PAYMENT'=>'danger',
                                    'FAQ_ORDER_FLOW'=>'secondary','FAQ_REFUND'=>'dark',
                                    'SERVICE_PRICE'=>'success','FAQ_CONTACT'=>'info',
                                    'GENERAL'=>'danger','BOT_IDENTITY'=>'primary','BOT_CAPABILITY'=>'secondary',
                                    'COURIER_ACTIVE_TASKS'=>'warning','COURIER_FAQ_ACCEPT_TASK'=>'primary'
                                ];
                                ?>
                                <ul class="list-group list-group-flush small">
                                    <?php foreach ($summary['top_intents'] as $rank => $intent): ?>
                                        <?php
                                        $pct = $maxCnt > 0 ? round(($intent['cnt'] / $maxCnt) * 100) : 0;
                                        $color = $intentColors[$intent['intent']] ?? 'secondary';
                                        ?>
                                        <li class="list-group-item px-4 py-2 border-0 border-bottom">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="fw-semibold text-dark">
                                                    <span class="text-muted me-1">#<?= $rank+1 ?>.</span>
                                                    <?= e($intent['intent']) ?>
                                                </span>
                                                <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border"><?= number_format($intent['cnt']) ?> kali</span>
                                            </div>
                                            <div class="progress" style="height:5px;">
                                                <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%;"></div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Top Questions -->
                        <div class="tab-pane fade" id="pills-questions" role="tabpanel">
                            <?php if (empty($summary['top_questions'])): ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i> Belum ada data pertanyaan.
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush small">
                                    <?php foreach ($summary['top_questions'] as $qIdx => $q): ?>
                                        <li class="list-group-item px-4 py-2 border-0 border-bottom d-flex justify-content-between align-items-center gap-2">
                                            <div class="text-truncate" style="max-width: 80%;">
                                                <span class="text-muted me-1">#<?= $qIdx+1 ?>.</span>
                                                <strong class="text-dark">"<?= e($q['message']) ?>"</strong>
                                                <span class="badge bg-light text-secondary border ms-1" style="font-size:0.7rem;"><?= e($q['detected_intent'] ?: 'GENERAL') ?></span>
                                            </div>
                                            <span class="badge bg-primary rounded-pill"><?= number_format($q['cnt']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fallback List Card (Pertanyaan Belum Dikenali) -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 d-flex align-items-center gap-2 text-danger">
                        <i class="bi bi-question-circle-fill"></i>
                        Pertanyaan Fallback (Belum Terjawab Bot)
                    </h6>
                    <span class="badge bg-danger-subtle text-danger border">Bahan Tambah Intent / FAQ</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($summary['fallback_questions'])): ?>
                        <div class="text-center py-5 text-muted small">
                            <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                            Tidak ada pertanyaan fallback saat ini! Semua pertanyaan dikenali bot.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0 small">
                                <thead class="table-light sticky-top">
                                    <tr class="text-muted fs-8 text-uppercase">
                                        <th class="ps-4">Pertanyaan</th>
                                        <th style="width:100px;">Role</th>
                                        <th style="width:90px;" class="text-center">Muncul</th>
                                        <th style="width:80px;" class="text-center pe-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($summary['fallback_questions'] as $fb): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;" title="<?= e($fb['message']) ?>">
                                                    "<?= e($fb['message']) ?>"
                                                </div>
                                                <div class="text-muted" style="font-size:0.7rem;">
                                                    Oleh: <?= e($fb['user_name']) ?> • <?= date('d M Y H:i', strtotime($fb['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary-subtle text-secondary border">
                                                    <?= e($fb['user_role'] ?? 'customer') ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger rounded-pill"><?= $fb['occurrences'] ?>x</span>
                                            </td>
                                            <td class="text-center pe-4">
                                                <a href="<?= url('platform/chatbot-logs?keyword=' . urlencode($fb['message'])) ?>"
                                                   class="btn btn-xs btn-outline-danger rounded-pill px-2" title="Cari di tabel">
                                                    <i class="bi bi-search"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================================= FILTER FORM -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom">
            <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-funnel text-primary"></i> Filter & Pencarian Percakapan
            </h6>
        </div>
        <div class="card-body p-4">
            <form id="filter-form" method="GET" action="<?= url('platform/chatbot-logs') ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Dari Tanggal</label>
                        <input type="date" name="date_from" id="f-date-from"
                               class="form-control form-control-sm rounded-3"
                               value="<?= e($filters['date_from'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Sampai Tanggal</label>
                        <input type="date" name="date_to" id="f-date-to"
                               class="form-control form-control-sm rounded-3"
                               value="<?= e($filters['date_to'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Role Pengguna</label>
                        <select name="role" id="f-role" class="form-select form-select-sm rounded-3">
                            <option value="">Semua Role</option>
                            <option value="customer"       <?= ($filters['role'] ?? '') === 'customer'       ? 'selected' : '' ?>>Customer</option>
                            <option value="kurir"          <?= ($filters['role'] ?? '') === 'kurir'          ? 'selected' : '' ?>>Kurir</option>
                            <option value="owner_platform" <?= ($filters['role'] ?? '') === 'owner_platform' ? 'selected' : '' ?>>Owner Platform</option>
                            <option value="admin_owner"    <?= ($filters['role'] ?? '') === 'admin_owner'    ? 'selected' : '' ?>>Admin Platform</option>
                            <option value="owner_toko"     <?= ($filters['role'] ?? '') === 'owner_toko'     ? 'selected' : '' ?>>Owner Toko</option>
                            <option value="admin_toko"     <?= ($filters['role'] ?? '') === 'admin_toko'     ? 'selected' : '' ?>>Admin Toko</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted">Intent Terdeteksi</label>
                        <input type="text" name="intent" id="f-intent"
                               class="form-control form-control-sm rounded-3"
                               placeholder="mis. GREETING, ORDER_STATUS"
                               value="<?= e($filters['intent'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold text-muted">Cari Kata Kunci (Pesan atau Nama User)</label>
                        <input type="text" name="keyword" id="f-keyword"
                               class="form-control form-control-sm rounded-3"
                               placeholder="Cari teks pertanyaan / kata kunci..."
                               value="<?= e($filters['keyword'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 d-flex align-items-center pt-md-4">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="only_fallback" value="1" id="f-only-fallback"
                                   <?= !empty($filters['only_fallback']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-danger small" for="f-only-fallback">
                                <i class="bi bi-filter-circle me-1"></i> Tampilkan Hanya Pertanyaan Fallback (Tidak Dikenali)
                            </label>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-xs">
                            <i class="bi bi-search me-1"></i> Terapkan Filter
                        </button>
                        <a href="<?= url('platform/chatbot-logs') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                            <i class="bi bi-x-lg me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================= LOGS TABLE -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="fw-bold mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-table text-primary"></i>
                Tabel Percakapan Terbaru
                <span class="badge bg-primary rounded-pill ms-1"><?= number_format($pagination['total']) ?> baris</span>
                <?php if (!empty($filters['only_fallback'])): ?>
                    <span class="badge bg-danger rounded-pill">Filter: Hanya Fallback</span>
                <?php endif; ?>
            </h6>
            <small class="text-muted">Halaman <?= $pagination['current_page'] ?> dari <?= $pagination['total_pages'] ?></small>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr class="text-uppercase text-muted fs-8 fw-semibold">
                        <th class="ps-4" style="width:140px;">Waktu</th>
                        <th style="width:160px;">Pengguna</th>
                        <th style="width:90px;">Role</th>
                        <th style="width:80px;">Sender</th>
                        <th>Pertanyaan / Pesan</th>
                        <th style="width:150px;">Balasan Bot</th>
                        <th style="width:130px;">Intent</th>
                        <th style="width:90px;" class="text-center">Status</th>
                        <th class="pe-4 text-center" style="width:140px;">Lihat Chat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-chat-square-dots fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Belum ada riwayat percakapan<?= !empty(array_filter($filters)) ? ' yang cocok dengan filter yang dipilih.' : '.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $isFallbackRow = !empty($log['is_fallback']) || $log['detected_intent'] === 'GENERAL' || empty($log['detected_intent']);
                            ?>
                            <tr class="<?= $isFallbackRow && $log['sender'] === 'USER' ? 'table-danger-subtle' : '' ?>">
                                <!-- Waktu -->
                                <td class="ps-4 text-muted" style="white-space:nowrap;">
                                    <div class="fw-semibold text-dark"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
                                    <div class="text-secondary" style="font-size:.72rem;"><?= date('H:i:s', strtotime($log['created_at'])) ?> WIB</div>
                                </td>

                                <!-- Pengguna -->
                                <td>
                                    <div class="fw-bold text-dark text-truncate" style="max-width:140px;" title="<?= e($log['user_name']) ?>">
                                        <?= e($log['user_name']) ?>
                                    </div>
                                    <div class="text-muted text-truncate" style="font-size:.72rem;max-width:140px;" title="<?= e($log['user_email']) ?>">
                                        <?= e($log['user_email']) ?>
                                    </div>
                                </td>

                                <!-- Role -->
                                <td>
                                    <?php if ($log['user_role'] === 'kurir'): ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Kurir</span>
                                    <?php elseif ($log['user_role'] === 'owner_platform' || $log['user_role'] === 'admin_owner'): ?>
                                        <span class="badge bg-purple-subtle text-purple border" style="background:#f3e8ff;color:#6f42c1;">Platform</span>
                                    <?php elseif ($log['user_role'] === 'owner_toko' || $log['user_role'] === 'admin_toko'): ?>
                                        <span class="badge bg-info-subtle text-info border">Toko</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Customer</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Sender -->
                                <td>
                                    <?php if ($log['sender'] === 'USER'): ?>
                                        <span class="badge bg-primary text-white"><i class="bi bi-person me-1"></i>USER</span>
                                    <?php else: ?>
                                        <span class="badge bg-success text-white"><i class="bi bi-robot me-1"></i>BOT</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Pesan / Pertanyaan -->
                                <td style="max-width:280px;">
                                    <div class="text-truncate text-dark fw-medium" style="max-width:280px;" title="<?= e($log['message']) ?>">
                                        <?= e(mb_substr($log['message'], 0, 120)) ?><?= mb_strlen($log['message']) > 120 ? '…' : '' ?>
                                    </div>
                                </td>

                                <!-- Balasan Bot -->
                                <td style="max-width:200px;">
                                    <?php if (!empty($log['bot_reply'])): ?>
                                        <div class="text-truncate text-muted small" style="max-width:200px;" title="<?= e($log['bot_reply']) ?>">
                                            <?= e(mb_substr($log['bot_reply'], 0, 90)) ?><?= mb_strlen($log['bot_reply']) > 90 ? '…' : '' ?>
                                        </div>
                                    <?php elseif ($log['sender'] === 'BOT'): ?>
                                        <span class="text-muted small"><em>(Baris balasan)</em></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Intent -->
                                <td>
                                    <?php if ($log['detected_intent']): ?>
                                        <span class="badge bg-light text-dark border px-2 py-1 text-truncate d-block" style="max-width:140px;" title="<?= e($log['detected_intent']) ?>">
                                            <?= e($log['detected_intent']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                    <?php if (!empty($log['tool_used'])): ?>
                                        <span class="badge bg-info-subtle text-info-emphasis border px-2 py-0 text-truncate d-block mt-1" style="max-width:140px; font-size:0.68rem;" title="Tool: <?= e($log['tool_used']) ?>">
                                            <i class="bi bi-gear-wide-connected me-1"></i><?= e($log['tool_used']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Penanda Fallback -->
                                <td class="text-center">
                                    <?php if ($isFallbackRow && $log['sender'] === 'USER'): ?>
                                        <span class="badge bg-danger rounded-pill px-2 py-1" title="Pertanyaan tidak dikenali bot">
                                            <i class="bi bi-exclamation-circle me-1"></i>Fallback
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border rounded-pill px-2 py-1">
                                            <i class="bi bi-check2 me-1"></i>Dikenali
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi Lihat Percakapan -->
                                <td class="pe-4 text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button"
                                                class="btn btn-outline-primary rounded-start-pill px-2 btn-view-thread"
                                                data-session="<?= e($log['session_id']) ?>"
                                                data-user="<?= e($log['user_name']) ?>"
                                                title="Lihat percakapan sesi ini">
                                            <i class="bi bi-chat-text"></i> Sesi
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-secondary rounded-end-pill px-2 btn-view-user"
                                                data-user-id="<?= (int)$log['user_id'] ?>"
                                                data-user="<?= e($log['user_name']) ?>"
                                                title="Lihat semua riwayat chat pengguna ini">
                                            <i class="bi bi-person-lines-fill"></i> User
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="card-footer bg-white py-3 px-4 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <small class="text-muted">
                Menampilkan <?= (($pagination['current_page']-1)*$pagination['per_page'])+1 ?>–<?= min($pagination['current_page']*$pagination['per_page'], $pagination['total']) ?>
                dari <?= number_format($pagination['total']) ?> percakapan
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1">
                    <li class="page-item <?= !$pagination['has_prev'] ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2" href="<?= url('platform/chatbot-logs' . clBuildQuery($filters, $pagination['current_page']-1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $start = max(1, $pagination['current_page'] - 2);
                    $end   = min($pagination['total_pages'], $start + 4);
                    for ($p = $start; $p <= $end; $p++):
                    ?>
                        <li class="page-item <?= $p === $pagination['current_page'] ? 'active' : '' ?>">
                            <a class="page-link rounded-2" href="<?= url('platform/chatbot-logs' . clBuildQuery($filters, $p)) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= !$pagination['has_next'] ? 'disabled' : '' ?>">
                        <a class="page-link rounded-2" href="<?= url('platform/chatbot-logs' . clBuildQuery($filters, $pagination['current_page']+1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /container -->

<!-- ======================================================= THREAD MODAL (CHAT VIEW) -->
<div class="modal fade" id="threadModal" tabindex="-1" aria-labelledby="threadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content rounded-4 overflow-hidden border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3 px-4 border-0">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="threadModalLabel">
                    <i class="bi bi-chat-dots-fill"></i>
                    <span id="thread-modal-title">Alur Percakapan Chat</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-body-tertiary p-3 p-md-4" id="thread-modal-body" style="min-height:320px;max-height:65vh;overflow-y:auto;">
                <div class="text-center py-5 text-muted" id="thread-loading">
                    <div class="spinner-border spinner-border-sm text-primary me-2"></div> Memuat percakapan…
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4">
                <small class="text-muted me-auto">
                    <i class="bi bi-shield-lock-fill text-success me-1"></i> Data sensitif telah disamarkan otomatis (Anti-XSS Aktif)
                </small>
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
.fs-8 { font-size: 0.75rem !important; }
.btn-xs { padding: 0.2rem 0.4rem; font-size: 0.75rem; }
.chat-bubble-user {
    background: #0d6efd;
    color: #fff;
    border-radius: 18px 18px 4px 18px;
    padding: 10px 14px;
    max-width: 85%;
    word-break: break-word;
    box-shadow: 0 2px 5px rgba(13,110,253,0.15);
}
.chat-bubble-bot {
    background: #fff;
    color: #212529;
    border-radius: 18px 18px 18px 4px;
    padding: 10px 14px;
    max-width: 85%;
    word-break: break-word;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 5px rgba(0,0,0,0.03);
}
.chat-time {
    font-size: 0.65rem;
    color: rgba(255,255,255,0.75);
    margin-top: 4px;
}
.chat-time-bot {
    font-size: 0.65rem;
    color: #888;
    margin-top: 4px;
}
</style>

<script>
(function () {
    const threadModalEl = document.getElementById('threadModal');
    const threadBody    = document.getElementById('thread-modal-body');
    const threadTitle   = document.getElementById('thread-modal-title');
    let modalInstance   = null;

    function getModal() {
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(threadModalEl);
        }
        return modalInstance;
    }

    /**
     * Escape HTML untuk mencegah XSS saat merender teks bebas dari pengguna.
     */
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Render formatting teks dengan aman (newline, bold, italic, code)
     */
    function formatMessageSafe(raw) {
        let s = escHtml(raw);
        s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/\*(.*?)\*/g, '<em>$1</em>');
        s = s.replace(/`(.*?)`/g, '<code style="background:rgba(0,0,0,.08);padding:1px 4px;border-radius:3px;">$1</code>');
        s = s.replace(/\n/g, '<br>');
        return s;
    }

    function renderThreadList(thread) {
        if (!thread || thread.length === 0) {
            threadBody.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i> Tidak ada pesan dalam percakapan ini.</div>';
            return;
        }

        let html = '';
        let lastDate = '';
        thread.forEach(msg => {
            const d    = new Date(msg.created_at);
            const date = isNaN(d) ? msg.created_at : d.toLocaleDateString('id-ID', {weekday:'short', day:'numeric', month:'short', year:'numeric'});
            const time = isNaN(d) ? '' : d.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});

            if (date !== lastDate) {
                html += `<div class="text-center my-3"><span class="badge bg-secondary-subtle text-secondary border small">${escHtml(date)}</span></div>`;
                lastDate = date;
            }

            const isFallback = msg.is_fallback == 1 || msg.detected_intent === 'GENERAL';

            if (msg.sender === 'USER') {
                html += `
                <div class="d-flex justify-content-end mb-3">
                    <div style="max-width: 85%;">
                        <div class="d-flex align-items-center justify-content-end gap-1 mb-1">
                            <span class="badge bg-secondary text-white" style="font-size:0.65rem;">${escHtml(msg.user_role || 'user')}</span>
                            <span class="small fw-semibold text-secondary" style="font-size:0.75rem;">${escHtml(msg.user_name || 'Pengguna')}</span>
                        </div>
                        <div class="chat-bubble-user ${isFallback ? 'border border-2 border-danger' : ''}">
                            ${formatMessageSafe(msg.message)}
                        </div>
                        <div class="d-flex align-items-center justify-content-end gap-1 mt-1">
                            ${msg.detected_intent ? `<span class="badge bg-light text-primary border" style="font-size:.65rem;">Intent: ${escHtml(msg.detected_intent)}</span>` : ''}
                            ${msg.tool_used ? `<span class="badge bg-info-subtle text-info border" style="font-size:.65rem;">Tool: ${escHtml(msg.tool_used)}</span>` : ''}
                            ${isFallback ? '<span class="badge bg-danger" style="font-size:.65rem;">Fallback</span>' : ''}
                            <span class="chat-time-bot ms-1">${escHtml(time)}</span>
                        </div>
                    </div>
                </div>`;
            } else {
                html += `
                <div class="d-flex align-items-start gap-2 mb-3">
                    <div class="rounded-circle bg-primary text-white flex-shrink-0 d-flex align-items-center justify-content-center shadow-xs" style="width:32px;height:32px;min-width:32px;">
                        <i class="bi bi-robot" style="font-size:.85rem;"></i>
                    </div>
                    <div style="max-width: 85%;">
                        <div class="small fw-semibold text-primary mb-1" style="font-size:0.75rem;">SmartBot (Asisten)</div>
                        <div class="chat-bubble-bot">
                            ${formatMessageSafe(msg.message)}
                        </div>
                        <div class="chat-time-bot mt-1">${escHtml(time)}</div>
                    </div>
                </div>`;
            }
        });

        threadBody.innerHTML = html;
        threadBody.scrollTop = threadBody.scrollHeight;
    }

    // View thread per session
    document.querySelectorAll('.btn-view-thread').forEach(btn => {
        btn.addEventListener('click', async function () {
            const sessionId = this.dataset.session;
            const userName  = this.dataset.user;

            threadTitle.textContent = 'Alur Sesi: ' + (sessionId.length > 14 ? sessionId.substring(0, 14) + '…' : sessionId) + ' (' + userName + ')';
            threadBody.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Memuat percakapan sesi…</div>';

            getModal().show();

            try {
                const res  = await fetch('<?= url('platform/chatbot-logs/thread') ?>?session_id=' + encodeURIComponent(sessionId));
                const data = await res.json();
                if (data.success) {
                    renderThreadList(data.thread);
                } else {
                    threadBody.innerHTML = '<div class="alert alert-warning m-3">' + escHtml(data.error || 'Gagal memuat pesan.') + '</div>';
                }
            } catch (err) {
                threadBody.innerHTML = '<div class="alert alert-danger m-3">Terjadi kesalahan jaringan saat memuat alur percakapan.</div>';
            }
        });
    });

    // View thread per user
    document.querySelectorAll('.btn-view-user').forEach(btn => {
        btn.addEventListener('click', async function () {
            const userId   = this.dataset.userId;
            const userName = this.dataset.user;

            threadTitle.textContent = 'Riwayat Percakapan User: ' + userName + ' (#' + userId + ')';
            threadBody.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Memuat seluruh riwayat pesan user…</div>';

            getModal().show();

            try {
                const res  = await fetch('<?= url('platform/chatbot-logs/user-thread') ?>?user_id=' + encodeURIComponent(userId));
                const data = await res.json();
                if (data.success) {
                    renderThreadList(data.thread);
                } else {
                    threadBody.innerHTML = '<div class="alert alert-warning m-3">' + escHtml(data.error || 'Gagal memuat pesan.') + '</div>';
                }
            } catch (err) {
                threadBody.innerHTML = '<div class="alert alert-danger m-3">Terjadi kesalahan jaringan saat memuat riwayat pengguna.</div>';
            }
        });
    });
})();
</script>
