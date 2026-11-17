<?php
/**
 * SmartLaundry AI Chatbot Floating Widget
 * Rendered for authenticated customers
 */
if (!is_authenticated()) {
    return;
}

$chatbotEnabled = (bool)\App\Models\Setting::get('chatbot_enabled', true);
if (!$chatbotEnabled) {
    return;
}

$userRole = user_role();
$allowedRoles = \App\Models\Setting::get('chatbot_roles_enabled', ['customer', 'kurir', 'admin_toko', 'owner_toko', 'owner_platform', 'admin_owner', 'admin_platform']);
if (is_string($allowedRoles)) {
    $allowedRoles = json_decode($allowedRoles, true) ?: ['customer', 'kurir', 'admin_toko', 'owner_toko', 'owner_platform', 'admin_owner', 'admin_platform'];
}
if (!in_array($userRole, (array)$allowedRoles)) {
    return;
}

$botName = \App\Models\Setting::get('chatbot_bot_name', 'SmartBot');
$authUserName = auth_user()['name'] ?? null;
$timeGreeting = \App\Services\ChatbotNLUService::getTimeGreetingWord();
$timeBasedFull = \App\Services\ChatbotNLUService::getTimeBasedGreeting($authUserName);

$botGreeting = \App\Models\Setting::get('chatbot_greeting_' . $userRole);
if (!empty($botGreeting)) {
    $botGreeting = str_replace(
        ['{sapaan}', '{greeting}', '{name}', '{user_name}', '{nama}', '{bot_name}'],
        [$timeBasedFull, $timeBasedFull, $authUserName ?: '', $authUserName ?: '', $authUserName ?: '', $botName],
        $botGreeting
    );
    $botGreeting = preg_replace('/Selamat pagi\/siang,?\s*(Anda)?\.?/i', $timeGreeting, $botGreeting);
} else {
    $botGreeting = "{$timeBasedFull} Selamat datang di " . app_name() . ". Saya {$botName}, asisten resmi " . app_name() . ", ada yang bisa saya bantu?";
}
?>

<!-- Floating AI Chatbot Button -->
<div id="smartbot-trigger-container" class="position-fixed bottom-0 end-0 p-4" style="z-index: 1050;">
    <button id="smartbot-open-btn" class="btn btn-primary rounded-circle shadow-lg d-flex align-items-center justify-content-center p-0 position-relative" style="width: 58px; height: 58px; border: 3px solid rgba(255,255,255,0.8); transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);" title="Tanya <?= e($botName) ?>">
        <i class="bi bi-robot fs-4 text-white"></i>
        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-success border border-light rounded-circle">
            <span class="visually-hidden">Active</span>
        </span>
    </button>
</div>

<!-- AI Chatbot Modal / Drawer Box -->
<div id="smartbot-chat-window" class="position-fixed bottom-0 end-0 m-3 m-md-4 shadow-2xl rounded-4 bg-white d-none flex-column overflow-hidden border border-light-subtle" style="z-index: 1060; width: 380px; max-width: calc(100vw - 24px); height: 550px; max-height: calc(100vh - 80px); transition: all 0.3s ease;">
    
    <!-- Chat Header -->
    <div class="bg-primary text-white px-3 py-3 d-flex align-items-center justify-content-between shadow-sm" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
        <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle bg-white text-primary p-2 d-flex align-items-center justify-content-center shadow-xs" style="width: 36px; height: 36px;">
                <i class="bi bi-robot fs-5"></i>
            </div>
            <div>
                <h6 class="mb-0 fw-bold fs-6"><?= e($botName) ?> <span class="badge bg-warning text-dark rounded-pill py-0 px-2 fs-8 fw-bold">Asisten Resmi</span></h6>
                <small class="text-white-50 d-flex align-items-center gap-1 fs-8">
                    <span class="d-inline-block rounded-circle bg-success" style="width: 7px; height: 7px;"></span>
                    Online • Siap Membantu
                </small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1">
            <button id="smartbot-clear-btn" class="btn btn-sm text-white-50 hover-white p-1" title="Bersihkan Percakapan">
                <i class="bi bi-trash3 fs-6"></i>
            </button>
            <button id="smartbot-close-btn" class="btn btn-sm text-white-50 hover-white p-1" title="Tutup Chat">
                <i class="bi bi-x-lg fs-6"></i>
            </button>
        </div>
    </div>

    <!-- Quick Suggestions Bar -->
    <div class="bg-light px-2 py-2 border-bottom overflow-x-auto d-flex gap-1 scrollbar-none" style="white-space: nowrap;">
        <?php if ($userRole === 'kurir'): ?>
        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Tugas saya sekarang apa">
            📋 Cek Tugas Aktif
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Gimana cara terima tugas?">
            ✅ Cara Terima Tugas
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Cara update lokasi GPS">
            📍 Update GPS
        </button>
        <?php elseif (in_array($userRole, ['admin_toko', 'owner_toko'])): ?>
        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Berapa pesanan hari ini?">
            📦 Berapa pesanan hari ini?
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Berapa omzet toko bulan ini?">
            💰 Berapa omzet toko bulan ini?
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Layanan apa yang paling laris?">
            ⭐ Layanan apa yang paling laris?
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Pesanan apa saja yang belum selesai?">
            ⏳ Pesanan apa saja yang belum selesai?
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Kurir mana yang sedang mengantar pesanan?">
            🛵 Kurir mana yang sedang mengantar pesanan?
        </button>
        <?php elseif (in_array($userRole, ['owner_platform', 'admin_owner', 'admin_platform'])): ?>
        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Ringkasan pesanan platform">
            📊 Ringkasan Pesanan
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Daftar toko belum verifikasi">
            🏪 Status Toko
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Statistik user platform">
            👥 Statistik User
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Ketersediaan kurir">
            🛵 Status Kurir
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-xs btn-outline-primary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Status pesanan saya">
            🔍 Cek Status Pesanan
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Berapa daftar harga layanan?">
            💰 Daftar Harga
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Bagaimana cara bayar QRIS?">
            💳 Cara Bayar QRIS
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Bagaimana cara memesan laundry?">
            🧺 Cara Pesan
        </button>
        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill smartbot-chip fs-8 py-1 px-2 text-nowrap" data-prompt="Kontak Customer Service">
            📞 Kontak CS
        </button>
        <?php endif; ?>
    </div>

    <!-- Messages Container -->
    <div id="smartbot-messages" class="flex-grow-1 p-3 overflow-y-auto bg-body-tertiary d-flex flex-column gap-3" style="font-size: 0.875rem;">
        <!-- Initial Bot Greeting -->
        <div class="d-flex align-items-start gap-2">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px;">
                <i class="bi bi-robot fs-7"></i>
            </div>
            <div class="bg-white p-3 rounded-4 shadow-sm border border-light-subtle text-dark" style="max-width: 85%;">
                <p class="mb-1"><?= nl2br(e($botGreeting)) ?></p>
                <p class="mb-0 text-secondary fs-8">
                    <?php if ($userRole === 'kurir'): ?>
                        Silakan tanyakan informasi tugas penjemputan, alur status pengantaran, atau panduan kurir lainnya.
                    <?php elseif (in_array($userRole, ['admin_toko', 'owner_toko'])): ?>
                        Silakan tanyakan pesanan hari ini, antrean cucian, omzet toko, performa layanan, atau status armada kurir Anda.
                    <?php elseif (in_array($userRole, ['owner_platform', 'admin_owner', 'admin_platform'])): ?>
                        Silakan tanyakan metrik platform, ringkasan GMV, data toko, status order, atau statistik sistem.
                    <?php else: ?>
                        Silakan tanyakan status pesanan, tarif layanan, cara pembayaran QRIS, atau bantuan laundry lainnya.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div id="smartbot-typing" class="px-3 py-2 bg-body-tertiary d-none align-items-center gap-2">
        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 24px; height: 24px;">
            <i class="bi bi-robot fs-8"></i>
        </div>
        <div class="bg-white px-3 py-2 rounded-4 shadow-xs border border-light-subtle text-muted fs-8 d-flex align-items-center gap-1">
            <span>SmartBot sedang mengetik</span>
            <span class="spinner-grow spinner-grow-sm text-primary" role="status" style="width: 0.5rem; height: 0.5rem;"></span>
        </div>
    </div>

    <!-- Chat Input Area -->
    <div class="p-2 bg-white border-top">
        <form id="smartbot-form" class="d-flex align-items-center gap-2 mb-0">
            <input type="hidden" id="smartbot-csrf" value="<?= csrf_token() ?>">
            <input type="text" id="smartbot-input" class="form-control form-control-sm rounded-pill px-3 py-2 fs-7" placeholder="Ketik pertanyaan Anda..." autocomplete="off" maxlength="500">
            <button type="submit" id="smartbot-submit-btn" class="btn btn-primary btn-sm rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 p-0 shadow-xs" style="width: 36px; height: 36px;">
                <i class="bi bi-send-fill fs-7"></i>
            </button>
        </form>
        <p class="text-center mb-0 mt-1" style="font-size:0.65rem;color:#aaa;">
            <i class="bi bi-shield-check"></i> Percakapan dapat disimpan untuk meningkatkan layanan.
        </p>
    </div>
</div>

<style>
.fs-8 { font-size: 0.775rem !important; }
.shadow-xs { box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.shadow-2xl { box-shadow: 0 20px 30px -10px rgba(0,0,0,0.18), 0 10px 15px -5px rgba(0,0,0,0.08); }
.scrollbar-none::-webkit-scrollbar { display: none; }
.scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
.smartbot-chip:hover { transform: translateY(-1px); transition: transform 0.15s; }
.hover-white:hover { color: #fff !important; }
.bot-text { line-height: 1.45; word-break: break-word; }
.bot-text p:last-child { margin-bottom: 0; }
.bot-text ul { padding-left: 1.1rem; margin-bottom: 0.4rem; }
.bot-text li { margin-bottom: 0.2rem; }
.bot-text table { font-size: 0.75rem; width: 100%; border-collapse: collapse; }
.bot-text hr { opacity: 0.15; margin: 0.4rem 0; }
.bot-text h5, .bot-text h6 { line-height: 1.3; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const triggerBtn = document.getElementById('smartbot-open-btn');
    const closeBtn = document.getElementById('smartbot-close-btn');
    const clearBtn = document.getElementById('smartbot-clear-btn');
    const chatWindow = document.getElementById('smartbot-chat-window');
    const form = document.getElementById('smartbot-form');
    const input = document.getElementById('smartbot-input');
    const submitBtn = document.getElementById('smartbot-submit-btn');
    const messagesContainer = document.getElementById('smartbot-messages');
    const typingIndicator = document.getElementById('smartbot-typing');
    const csrfToken = document.getElementById('smartbot-csrf').value;
    const chips = document.querySelectorAll('.smartbot-chip');

    let isChatOpen = false;

    // Toggle Chat Window
    function toggleChat() {
        isChatOpen = !isChatOpen;
        if (isChatOpen) {
            chatWindow.classList.remove('d-none');
            chatWindow.classList.add('d-flex');
            triggerBtn.style.transform = 'scale(0.85)';
            input.focus();
            scrollToBottom();
        } else {
            chatWindow.classList.add('d-none');
            chatWindow.classList.remove('d-flex');
            triggerBtn.style.transform = 'scale(1)';
        }
    }

    if (triggerBtn) triggerBtn.addEventListener('click', toggleChat);
    if (closeBtn) closeBtn.addEventListener('click', toggleChat);

    // Scroll to bottom
    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Render Markdown table blocks into responsive HTML tables
    function renderMarkdownTable(rows) {
        if (!rows || rows.length === 0) return '';
        let html = '<div class="table-responsive my-2"><table class="table table-sm table-bordered table-striped mb-1 fs-8" style="font-size:0.75rem; background-color: #fff;">';
        let hasHeader = false;
        let inBody = false;

        for (let r = 0; r < rows.length; r++) {
            if (rows[r].isSep) {
                hasHeader = true;
                continue;
            }
            let rawCells = rows[r].line.split('|');
            if (rawCells.length >= 2) {
                let cells = rawCells.slice(1, -1).map(c => c.trim());
                if (!hasHeader && r === 0 && rows.length > 1 && rows[1].isSep) {
                    html += '<thead class="table-light"><tr>';
                    cells.forEach(c => { html += `<th class="py-1 px-2 text-nowrap">${c}</th>`; });
                    html += '</tr></thead><tbody>';
                    inBody = true;
                } else {
                    if (!inBody) {
                        html += '<tbody>';
                        inBody = true;
                    }
                    html += '<tr>';
                    cells.forEach(c => { html += `<td class="py-1 px-2">${c}</td>`; });
                    html += '</tr>';
                }
            }
        }
        if (inBody) html += '</tbody>';
        html += '</table></div>';
        return html;
    }

    // Format markdown text into sanitized, clean HTML
    function formatMarkdown(text) {
        if (!text) return '';

        // 1. Cegah double entity encoding: decode entity standar terlebih dahulu
        let unescaped = text
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#039;/g, "'")
            .replace(/&#39;/g, "'");

        // 2. Escape HTML entities untuk pencegahan XSS
        let safe = unescaped
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        // 3. Parse Markdown Tables bila ada
        const lines = safe.split('\n');
        let processedLines = [];
        let tableRows = [];
        let inTable = false;

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i].trim();
            if (line.startsWith('|') && line.endsWith('|')) {
                let isSep = /^\|(\s*:?-+:?\s*\|)+$/.test(line);
                tableRows.push({ line, isSep });
                inTable = true;
            } else {
                if (inTable) {
                    processedLines.push(renderMarkdownTable(tableRows));
                    tableRows = [];
                    inTable = false;
                }
                processedLines.push(lines[i]);
            }
        }
        if (inTable && tableRows.length > 0) {
            processedLines.push(renderMarkdownTable(tableRows));
        }

        let formatted = processedLines.join('\n');

        // 4. Headings (#, ##, ###)
        formatted = formatted.replace(/^### (.*$)/gim, '<h6 class="fw-bold mt-2 mb-1 fs-7 text-dark">$1</h6>');
        formatted = formatted.replace(/^## (.*$)/gim, '<h6 class="fw-bold mt-2 mb-1 text-primary">$1</h6>');
        formatted = formatted.replace(/^# (.*$)/gim, '<h5 class="fw-bold mt-2 mb-1 text-primary border-bottom pb-1">$1</h5>');

        // 5. Horizontal rules (--- atau ***)
        formatted = formatted.replace(/^(\s*[-*_]){3,}\s*$/gim, '<hr class="my-2 border-secondary-subtle">');

        // 6. Blockquote (> quote)
        formatted = formatted.replace(/^&gt; (.*$)/gim, '<div class="border-start border-3 border-primary ps-2 py-1 my-1 text-secondary bg-light rounded-end fs-8">$1</div>');

        // 7. Bullet lists (- item, * item, • item)
        formatted = formatted.replace(/^[\s]*[-*•]\s+(.*$)/gim, '<li class="smartbot-li mb-1">$1</li>');
        formatted = formatted.replace(/((?:<li class="smartbot-li mb-1">.*?<\/li>(?:\r?\n)?)+)/gim, function(match) {
            return '<ul class="ps-3 mb-2 mt-1">' + match.replace(/\r?\n/g, '') + '</ul>';
        });

        // 8. Inlines: Bold, Italic, Inline Code
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
        formatted = formatted.replace(/`(.*?)`/g, '<code class="bg-light px-1 py-0.5 rounded text-danger fs-8 border">$1</code>');

        // 9. Links: [text](url)
        formatted = formatted.replace(/\[(.*?)\]\((https?:\/\/[^\s\)]+|\/[^\s\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-underline">$1</a>');

        // 10. Bersihkan newline di sekitar tag blok sebelum convert ke <br>
        formatted = formatted.replace(/<\/ul>\n/g, '</ul>');
        formatted = formatted.replace(/<\/div>\n/g, '</div>');
        formatted = formatted.replace(/<\/h[1-6]>\n/g, function(m) { return m.trim(); });
        formatted = formatted.replace(/<hr[^>]*>\n/g, function(m) { return m.trim(); });
        formatted = formatted.replace(/\n{2,}/g, '<div class="my-2"></div>');
        formatted = formatted.replace(/\n/g, '<br>');

        return formatted;
    }

    // Append message to UI
    function appendMessage(role, text, time = '') {
        const timeStr = time || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const msgDiv = document.createElement('div');
        msgDiv.className = 'd-flex align-items-start gap-2 ' + (role === 'user' ? 'justify-content-end' : '');

        if (role === 'assistant') {
            msgDiv.innerHTML = `
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px;">
                    <i class="bi bi-robot fs-7"></i>
                </div>
                <div class="bg-white p-3 rounded-4 shadow-sm border border-light-subtle text-dark" style="max-width: 85%;">
                    <div class="bot-text">${formatMarkdown(text)}</div>
                    <div class="text-end mt-1 text-muted" style="font-size: 0.7rem;">${timeStr}</div>
                </div>
            `;
        } else {
            msgDiv.innerHTML = `
                <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 85%; border-bottom-right-radius: 4px;">
                    <div class="user-text">${formatMarkdown(text)}</div>
                    <div class="text-end mt-1 text-white-50" style="font-size: 0.7rem;">${timeStr}</div>
                </div>
            `;
        }

        messagesContainer.appendChild(msgDiv);
        scrollToBottom();
    }

    // Send Message AJAX Handler
    async function handleSendMessage(messageText) {
        const text = messageText.trim();
        if (!text) return;

        // Display user message in UI
        appendMessage('user', text);
        input.value = '';
        input.disabled = true;
        submitBtn.disabled = true;
        typingIndicator.classList.remove('d-none');
        typingIndicator.classList.add('d-flex');
        scrollToBottom();

        try {
            const response = await fetch('<?= url("api/chatbot/message") ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: text,
                    _csrf_token: csrfToken
                })
            });

            const data = await response.json();

            typingIndicator.classList.add('d-none');
            typingIndicator.classList.remove('d-flex');

            if (data.success) {
                appendMessage('assistant', data.reply, data.timestamp);
            } else {
                appendMessage('assistant', '⚠️ ' + (data.message || 'Maaf, terjadi kendala saat memproses pertanyaan Anda.'));
            }
        } catch (error) {
            typingIndicator.classList.add('d-none');
            typingIndicator.classList.remove('d-flex');
            appendMessage('assistant', '⚠️ Gagal terhubung ke server. Pastikan koneksi internet Anda aktif.');
        } finally {
            input.disabled = false;
            submitBtn.disabled = false;
            input.focus();
            scrollToBottom();
        }
    }

    // Submit form
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleSendMessage(input.value);
        });
    }

    // Click suggestions chips
    chips.forEach(chip => {
        chip.addEventListener('click', function() {
            const prompt = this.getAttribute('data-prompt');
            if (prompt) {
                handleSendMessage(prompt);
            }
        });
    });

    // Clear history
    if (clearBtn) {
        clearBtn.addEventListener('click', async function() {
            if (!confirm('Hapus seluruh riwayat percakapan chat ini?')) return;
            try {
                await fetch('<?= url("api/chatbot/clear") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ _csrf_token: csrfToken })
                });

                // Reset message container
                messagesContainer.innerHTML = `
                    <div class="d-flex align-items-start gap-2">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px;">
                            <i class="bi bi-robot fs-7"></i>
                        </div>
                        <div class="bg-white p-3 rounded-4 shadow-sm border border-light-subtle text-dark" style="max-width: 85%;">
                            <p class="mb-1">Riwayat percakapan telah dibersihkan. 🧹</p>
                            <p class="mb-0 text-secondary fs-8"><?= user_role() === 'kurir' ? 'Silakan tanyakan hal lain seputar tugas kurir.' : 'Silakan tanyakan hal lain seputar layanan SmartLaundry.' ?></p>
                        </div>
                    </div>
                `;
            } catch (err) {}
        });
    }

    // Load initial history from session on page load
    async function loadHistory() {
        try {
            const res = await fetch('<?= url("api/chatbot/history") ?>');
            const data = await res.json();
            if (data.success && Array.isArray(data.history) && data.history.length > 0) {
                // Clear initial greeting and render history
                messagesContainer.innerHTML = '';
                data.history.forEach(item => {
                    appendMessage(item.role, item.message, item.time);
                });
                scrollToBottom();
            }
        } catch (err) {}
    }

    // Expose global controller for widget
    window.SmartBotWidget = {
        open: function() {
            if (!isChatOpen) toggleChat();
        },
        close: function() {
            if (isChatOpen) toggleChat();
        },
        openWithPrompt: function(promptText) {
            if (!isChatOpen) toggleChat();
            if (promptText) {
                handleSendMessage(promptText);
            }
        }
    };

    loadHistory();
});
</script>
