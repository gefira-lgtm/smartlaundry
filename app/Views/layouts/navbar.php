<?php
$currentUser = auth_user();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary-gradient sticky-top shadow-sm py-3">
    <div class="container">
        <!-- Brand Logo -->
        <?php $navLogo = app_logo(); ?>
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= url('/') ?>">
            <?php if ($navLogo): ?>
                <img src="<?= url($navLogo) ?>" alt="<?= e(app_name()) ?>" class="rounded-circle shadow-sm" style="width: 34px; height: 34px; object-fit: cover;">
            <?php else: ?>
                <div class="brand-icon bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm">
                    <i class="bi bi-water fs-5"></i>
                </div>
            <?php endif; ?>
            <span><?= e(app_name()) ?></span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item">
                    <a class="nav-link <?= ($_SERVER['REQUEST_URI'] ?? '') === url('/') ? 'active fw-semibold' : '' ?>" href="<?= url('/') ?>">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('stores') ?>">Cari Toko</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('about') ?>">Tentang Kami</a>
                </li>
                <?php if ($currentUser && in_array($currentUser['role'], ['owner_platform', 'admin_owner'], true)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'platform/settings') ? 'active fw-semibold' : '' ?>" href="<?= url('platform/settings') ?>">
                            <i class="bi bi-gear-wide-connected me-1"></i> Konfigurasi
                        </a>
                    </li>
                <?php elseif ($currentUser && in_array($currentUser['role'], ['owner_toko', 'admin_toko'], true)): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'store/settings') ? 'active fw-semibold' : '' ?>" href="<?= url('store/settings') ?>">
                            <i class="bi bi-gear-wide me-1"></i> Pengaturan Toko
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <!-- Notification Bell (For Logged In Users) -->
                <?php if ($currentUser): ?>
                    <div class="dropdown" id="navNotificationDropdown">
                        <button class="btn btn-outline-light btn-sm rounded-circle position-relative p-2 d-flex align-items-center justify-content-center shadow-sm" 
                                style="width: 38px; height: 38px;" 
                                type="button" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false" 
                                id="btnNavNotif"
                                title="Pusat Notifikasi">
                            <i class="bi bi-bell-fill fs-6"></i>
                            <span id="navNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.65rem;">
                                0
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-0 mt-2" style="width: 360px; max-width: 90vw;">
                            <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between rounded-top-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-bell-fill text-primary"></i>
                                    <h6 class="mb-0 fw-bold text-dark">Notifikasi</h6>
                                </div>
                                <button type="button" class="btn btn-link text-decoration-none text-primary p-0 fs-8 fw-semibold" id="btnMarkAllRead">
                                    Tandai semua dibaca
                                </button>
                            </div>
                            <div id="navNotifList" style="max-height: 380px; overflow-y: auto;">
                                <div class="text-center py-4 text-muted small">
                                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                    <div>Memuat notifikasi...</div>
                                </div>
                            </div>
                            <div class="p-2 bg-light border-top text-center rounded-bottom-4">
                                <span class="text-muted fs-8"><i class="bi bi-broadcast me-1 text-success"></i> Real-time Live Polling Aktif</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- User Profile / Auth State Buttons -->
                <?php if ($currentUser): ?>
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold me-2 shadow-sm" style="width: 36px; height: 36px;">
                                <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                            </div>
                            <div class="d-none d-md-block text-start">
                                <div class="fw-bold fs-7 text-white leading-tight"><?= e($currentUser['name']) ?></div>
                                <span class="badge bg-white text-dark rounded-pill fs-8 py-0 px-2"><?= e($currentUser['role_display']) ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 py-2 mt-2">
                            <li class="px-3 py-2 border-bottom">
                                <small class="text-muted d-block">Masuk sebagai</small>
                                <strong class="text-dark"><?= e($currentUser['email']) ?></strong>
                            </li>
                            <li>
                                <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url(get_dashboard_url_by_role($currentUser['role'])) ?>">
                                    <i class="bi bi-speedometer2 text-primary"></i> Dashboard <?= e($currentUser['role_display']) ?>
                                </a>
                            </li>
                            <?php if (in_array($currentUser['role'], ['owner_platform', 'admin_owner'], true)): ?>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('platform/settings') ?>">
                                        <i class="bi bi-gear-wide-connected text-primary"></i> Konfigurasi Sistem
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('platform/whatsapp-logs') ?>">
                                        <i class="bi bi-whatsapp text-success"></i> Log Outbox WhatsApp
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('platform/whatsapp-settings') ?>">
                                        <i class="bi bi-gear-wide-connected text-success"></i> Pengaturan Notifikasi WA
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('platform/chatbot-logs') ?>">
                                        <i class="bi bi-robot text-primary"></i> Log Chatbot
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php if ($currentUser['role'] === 'customer'): ?>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('customer/addresses') ?>">
                                        <i class="bi bi-geo-alt text-success"></i> Buku Alamat
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('customer/profile') ?>">
                                        <i class="bi bi-person-gear text-info"></i> Profil Saya
                                    </a>
                                </li>
                            <?php elseif (in_array($currentUser['role'], ['owner_toko', 'admin_toko'], true)): ?>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('store/settings') ?>">
                                        <i class="bi bi-gear-wide text-secondary"></i> Pengaturan Toko
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('store/whatsapp-settings') ?>">
                                        <i class="bi bi-whatsapp text-success"></i> Pengaturan Notifikasi WA
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('store/profile') ?>">
                                        <i class="bi bi-shop text-primary"></i> Profil & Alamat Toko
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('store/services') ?>">
                                        <i class="bi bi-tag text-success"></i> Katalog Layanan
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= url('store/couriers') ?>">
                                        <i class="bi bi-truck text-warning"></i> Armada Kurir
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="<?= url('logout') ?>">
                                    <i class="bi bi-box-arrow-right"></i> Keluar
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= url('login') ?>" class="btn btn-outline-light rounded-pill px-3">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                    </a>
                    <a href="<?= url('register') ?>" class="btn btn-warning text-dark rounded-pill px-3 fw-bold">
                        <i class="bi bi-person-plus-fill me-1"></i> Daftar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if ($currentUser): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const badge = document.getElementById('navNotifBadge');
    const notifList = document.getElementById('navNotifList');
    const btnMarkAll = document.getElementById('btnMarkAllRead');
    const btnNavNotif = document.getElementById('btnNavNotif');

    let isDropdownOpen = false;

    // Fetch Unread Count
    function fetchUnreadCount() {
        fetch('<?= url('notifications/unread-count') ?>')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.unread_count > 0) {
                        badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                        badge.classList.remove('d-none');
                    } else {
                        badge.classList.add('d-none');
                    }
                }
            })
            .catch(err => console.debug('Notif count fetch error:', err));
    }

    // Fetch Recent Notifications
    function fetchRecentNotifs() {
        fetch('<?= url('notifications/recent') ?>')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    renderNotifs(data.notifications || []);
                }
            })
            .catch(err => {
                notifList.innerHTML = '<div class="text-center py-3 text-muted small">Gagal memuat notifikasi.</div>';
            });
    }

    // Render Notifications HTML
    function renderNotifs(notifs) {
        if (!notifs || notifs.length === 0) {
            notifList.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-bell-slash fs-2 d-block mb-1 text-secondary opacity-50"></i>
                    <p class="mb-0 small">Belum ada notifikasi baru.</p>
                </div>`;
            return;
        }

        let html = '<div class="list-group list-group-flush">';
        notifs.forEach(n => {
            const isUnread = (parseInt(n.is_read) === 0);
            const bgClass = isUnread ? 'bg-primary-subtle bg-opacity-25' : 'bg-white';
            const unreadDot = isUnread ? '<span class="p-1 bg-primary rounded-circle me-2 flex-shrink-0" style="width: 8px; height: 8px;"></span>' : '';
            
            let icon = 'bi-bell';
            let iconColor = 'text-primary';
            if (n.type === 'order') { icon = 'bi-bag-check'; iconColor = 'text-success'; }
            else if (n.type === 'payment') { icon = 'bi-credit-card-2-front'; iconColor = 'text-warning'; }
            else if (n.type === 'courier') { icon = 'bi-truck'; iconColor = 'text-info'; }
            else if (n.type === 'ai') { icon = 'bi-stars'; iconColor = 'text-purple'; }

            const targetUrl = n.action_url ? n.action_url : '#';

            html += `
                <a href="${targetUrl}" class="list-group-item list-group-item-action p-3 border-bottom d-flex align-items-start gap-2 notif-item ${bgClass}" data-id="${n.id}">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center p-2 mt-1 shadow-sm flex-shrink-0" style="width: 34px; height: 34px;">
                        <i class="bi ${icon} ${iconColor} fs-6"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <strong class="fs-7 text-dark text-truncate d-flex align-items-center">
                                ${unreadDot}${escapeHtml(n.title)}
                            </strong>
                            <small class="text-muted fs-8 ms-1 flex-shrink-0">${n.time_ago}</small>
                        </div>
                        <p class="mb-0 fs-8 text-secondary line-clamp-2">${escapeHtml(n.message)}</p>
                    </div>
                </a>
            `;
        });
        html += '</div>';
        notifList.innerHTML = html;

        // Attach click handlers to mark individual as read
        notifList.querySelectorAll('.notif-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const notifId = this.getAttribute('data-id');
                if (notifId) {
                    fetch(`<?= url('notifications/') ?>${notifId}/mark-read`, { credentials: 'same-origin' })
                        .catch(err => console.debug(err));
                }
            });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Dropdown open event
    if (btnNavNotif) {
        btnNavNotif.addEventListener('click', function() {
            fetchRecentNotifs();
        });
    }

    // Mark all as read button
    if (btnMarkAll) {
        btnMarkAll.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fetch('<?= url('notifications/mark-all-read') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    fetchUnreadCount();
                    fetchRecentNotifs();
                }
            });
        });
    }

    // Initial load & Polling loop every 20 seconds
    fetchUnreadCount();
    setInterval(fetchUnreadCount, 30000);
});
</script>
<?php endif; ?>

