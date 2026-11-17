<?php
/**
 * OTP Verification Page
 * 
 * Shown after registration - customer must enter OTP codes from WhatsApp and/or Email
 * to activate their account.
 */
$pendingPhone     = $pendingPhone ?? '';
$pendingEmail     = $pendingEmail ?? '';
$pendingName      = $pendingName ?? '';
$otpExpiry        = (int)($otpExpiry ?? 5);
$resendCooldown   = (int)($resendCooldown ?? 60);
$requiredChannels = $requiredChannels ?? 'BOTH';

// Mask phone: show only last 4 digits: +62 **** **** 5678
$maskedPhone = '';
if (!empty($pendingPhone)) {
    $len = strlen($pendingPhone);
    $maskedPhone = substr($pendingPhone, 0, 3) . str_repeat('*', max(0, $len - 7)) . substr($pendingPhone, -4);
}

// Mask email: show first 2 chars, then *** before @
$maskedEmail = '';
if (!empty($pendingEmail)) {
    $parts = explode('@', $pendingEmail);
    $user  = $parts[0] ?? '';
    $domain = $parts[1] ?? '';
    $maskedEmail = substr($user, 0, 2) . str_repeat('*', max(0, strlen($user) - 2)) . '@' . $domain;
}
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4">
  <div class="max-w-md w-full">

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">

      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-8 py-6 text-white">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>
          <h1 class="text-xl font-bold">Verifikasi OTP</h1>
        </div>
        <p class="text-blue-100 text-sm">
          Kode verifikasi telah dikirim ke WhatsApp<?= ($requiredChannels !== 'WHATSAPP_ONLY') ? ' dan email' : '' ?> Anda.
        </p>
      </div>

      <!-- Body -->
      <div class="px-8 py-6">

        <!-- Flash messages -->
        <?php if ($flash = get_flash('error')): ?>
          <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= e($flash) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($flash = get_flash('warning')): ?>
          <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-700 text-sm flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= e($flash) ?></span>
          </div>
        <?php endif; ?>

        <?php if ($flash = get_flash('info')): ?>
          <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-sm flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zm-1 4a1 1 0 00-1 1v4a1 1 0 102 0v-4a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span><?= e($flash) ?></span>
          </div>
        <?php endif; ?>

        <!-- Recipient Info -->
        <div class="mb-6 p-4 bg-gray-50 rounded-xl space-y-2">
          <?php if (!empty($maskedPhone)): ?>
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
              <path d="M11.997 0C5.372 0 0 5.373 0 12c0 2.117.553 4.103 1.521 5.824L.037 23.784a.5.5 0 00.614.614l5.96-1.484A11.947 11.947 0 0011.997 24C18.622 24 24 18.627 24 12S18.622 0 11.997 0zm0 21.818a9.795 9.795 0 01-5.009-1.376l-.36-.213-3.726.928.945-3.635-.234-.374a9.795 9.795 0 01-1.434-5.148C2.179 6.567 6.566 2.182 12 2.182c5.432 0 9.818 4.385 9.818 9.818 0 5.432-4.386 9.818-9.821 9.818z"/>
            </svg>
            <span>WhatsApp: <strong><?= e($maskedPhone) ?></strong></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($maskedEmail)): ?>
          <div class="flex items-center gap-2 text-sm text-gray-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <span>Email: <strong><?= e($maskedEmail) ?></strong></span>
          </div>
          <?php endif; ?>
        </div>

        <!-- OTP Timer -->
        <div class="text-center mb-5">
          <p class="text-sm text-gray-500">Kode berlaku selama:</p>
          <div id="otp-timer" class="text-2xl font-mono font-bold text-blue-600" data-expires="<?= (int)($otpExpiry * 60) ?>">
            <?= sprintf('%02d', $otpExpiry) ?>:00
          </div>
          <p id="timer-expired" class="hidden text-red-500 text-sm font-medium">⚠️ Kode telah kedaluwarsa. Gunakan tombol "Kirim Ulang".</p>
        </div>

        <!-- Verification Form -->
        <form method="POST" action="<?= url('verify-otp') ?>">
          <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

          <!-- WA OTP -->
          <div class="mb-4">
            <label for="otp_wa" class="block text-sm font-medium text-gray-700 mb-1">
              Kode OTP WhatsApp
              <?php if ($requiredChannels !== 'EITHER'): ?>
                <span class="text-red-500">*</span>
              <?php else: ?>
                <span class="text-gray-400 font-normal">(opsional jika email terisi)</span>
              <?php endif; ?>
            </label>
            <div class="relative">
              <input
                type="text"
                id="otp_wa"
                name="otp_wa"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="— — — — — —"
                autocomplete="one-time-code"
                class="w-full px-4 py-3 text-center text-2xl font-mono tracking-widest border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              >
              <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                </svg>
              </div>
            </div>
            <div class="mt-1.5 flex items-center justify-between">
              <span class="text-xs text-gray-400">Kode 6 digit via WhatsApp</span>
              <button type="button"
                id="resend-wa-btn"
                data-channel="WHATSAPP"
                data-cooldown="<?= $resendCooldown ?>"
                onclick="resendOtp('WHATSAPP')"
                class="text-xs text-blue-600 hover:text-blue-800 font-medium disabled:opacity-40 disabled:cursor-not-allowed transition"
              >
                Kirim ulang <span id="resend-wa-countdown"></span>
              </button>
            </div>
          </div>

          <!-- Email OTP -->
          <div class="mb-6">
            <label for="otp_email" class="block text-sm font-medium text-gray-700 mb-1">
              Kode OTP Email
              <?php if ($requiredChannels !== 'EITHER'): ?>
                <span class="text-red-500">*</span>
              <?php else: ?>
                <span class="text-gray-400 font-normal">(opsional jika WhatsApp terisi)</span>
              <?php endif; ?>
            </label>
            <div class="relative">
              <input
                type="text"
                id="otp_email"
                name="otp_email"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="— — — — — —"
                autocomplete="one-time-code"
                class="w-full px-4 py-3 text-center text-2xl font-mono tracking-widest border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              >
              <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
              </div>
            </div>
            <div class="mt-1.5 flex items-center justify-between">
              <span class="text-xs text-gray-400">Kode 6 digit via email</span>
              <button type="button"
                id="resend-email-btn"
                data-channel="EMAIL"
                data-cooldown="<?= $resendCooldown ?>"
                onclick="resendOtp('EMAIL')"
                class="text-xs text-blue-600 hover:text-blue-800 font-medium disabled:opacity-40 disabled:cursor-not-allowed transition"
              >
                Kirim ulang <span id="resend-email-countdown"></span>
              </button>
            </div>
          </div>

          <button type="submit" id="submit-btn" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition text-sm shadow-md hover:shadow-lg">
            Verifikasi & Aktifkan Akun
          </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-4">
          Salah mendaftar? <a href="<?= url('register') ?>" class="text-blue-600 hover:underline">Daftar ulang</a>
        </p>

      </div>
    </div>

  </div>
</div>

<script>
// ===================== OTP Expiry Countdown =====================
(function() {
  const timerEl = document.getElementById('otp-timer');
  const expiredEl = document.getElementById('timer-expired');
  const submitBtn = document.getElementById('submit-btn');
  if (!timerEl) return;

  let total = parseInt(timerEl.dataset.expires || '300', 10);

  const update = () => {
    if (total <= 0) {
      timerEl.textContent = '00:00';
      timerEl.classList.remove('text-blue-600');
      timerEl.classList.add('text-red-500');
      expiredEl?.classList.remove('hidden');
      submitBtn && (submitBtn.disabled = true);
      return;
    }
    const m = Math.floor(total / 60).toString().padStart(2, '0');
    const s = (total % 60).toString().padStart(2, '0');
    timerEl.textContent = m + ':' + s;
    if (total <= 60) {
      timerEl.classList.remove('text-blue-600');
      timerEl.classList.add('text-orange-500');
    }
    total--;
    setTimeout(update, 1000);
  };
  update();
})();

// ===================== Resend Cooldown =====================
const resendTimers = {};

function resendOtp(channel) {
  const btn = document.getElementById('resend-' + channel.toLowerCase() + '-btn');
  const countdownSpan = document.getElementById('resend-' + channel.toLowerCase() + '-countdown');
  if (!btn || btn.disabled) return;

  btn.disabled = true;

  fetch('<?= url('otp/resend') ?>', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      _csrf_token: '<?= csrf_token() ?>',
      channel: channel
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showToast('success', data.message);
      startResendCooldown(btn, countdownSpan, parseInt(btn.dataset.cooldown || '60', 10));
    } else {
      showToast('error', data.message || 'Gagal mengirim ulang OTP.');
      const remaining = data.remaining_seconds || parseInt(btn.dataset.cooldown || '60', 10);
      startResendCooldown(btn, countdownSpan, remaining);
    }
  })
  .catch(() => {
    showToast('error', 'Terjadi kesalahan jaringan. Silakan coba lagi.');
    btn.disabled = false;
  });
}

function startResendCooldown(btn, countdownSpan, seconds) {
  let t = seconds;
  const tick = () => {
    if (t <= 0) {
      btn.disabled = false;
      if (countdownSpan) countdownSpan.textContent = '';
      return;
    }
    if (countdownSpan) countdownSpan.textContent = '(' + t + 's)';
    t--;
    setTimeout(tick, 1000);
  };
  tick();
}

function showToast(type, msg) {
  const el = document.createElement('div');
  el.className = 'fixed top-4 right-4 z-50 max-w-sm p-4 rounded-xl shadow-xl text-sm font-medium transition '
    + (type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white');
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

// Auto-format OTP inputs: only allow digits
document.querySelectorAll('input[inputmode="numeric"]').forEach(inp => {
  inp.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
  });
});
</script>

