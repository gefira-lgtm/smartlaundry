<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/navbar.php';
?>

<main class="flex-grow-1">
    <!-- Flash Messages -->
    <?php if (has_flash('success')): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div><?= e(get_flash('success')) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (has_flash('error')): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?= e(get_flash('error')) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (has_flash('info')): ?>
        <div class="container mt-3">
            <div class="alert alert-info alert-dismissible fade show shadow-sm rounded-4 d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div><?= e(get_flash('info')) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Injected from View -->
    <?= $content ?? '' ?>
</main>

<?php
// AI Chatbot Assistant Widget
require_once __DIR__ . '/../partials/chatbot_widget.php';
require_once __DIR__ . '/footer.php';
?>

