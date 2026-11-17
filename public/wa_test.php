<?php
/**
 * Halaman uji kirim WhatsApp (Fonnte) untuk Owner Platform dan Admin Owner.
 * - Nomor tujuan diketik sendiri atau dipilih dari daftar nomor tersimpan.
 * - Wajib konfirmasi dan persetujuan pemilik nomor sebelum dikirim.
 * Letak: public/wa_test.php -> http://localhost/RPL2/public/wa_test.php
 */
require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);

// ---- Hak akses: Owner Platform dan Admin Owner ----
$logged = function_exists('is_authenticated') ? is_authenticated() : !empty($_SESSION['user']);
$role   = function_exists('user_role') ? user_role() : ($_SESSION['user']['role'] ?? null);
if (!$logged) {
    header('Location: ../login');
    exit;
}
if (!in_array($role, ['owner_platform', 'admin_owner'], true)) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus Owner Platform dan Admin Owner.');
}
$userId   = (int)($_SESSION['user']['id'] ?? 0);
$userName = (string)($_SESSION['user']['name'] ?? '');

// ---- Token CSRF sederhana ----
if (empty($_SESSION['wa_test_csrf'])) {
    $_SESSION['wa_test_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['wa_test_csrf'];

// ---- Fungsi bantu ----
function wa_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function wa_normalize(string $raw): ?string {
    $d = preg_replace('/\D+/', '', $raw);
    if ($d === '') return null;
    if (strpos($d, '62') === 0)    { $d = substr($d, 2); }   // 62838...
    elseif (strpos($d, '0') === 0) { $d = substr($d, 1); }   // 0838...
    if ($d === '' || $d[0] === '0') return null;             // 620838... atau 00... tidak valid
    $full = '62' . $d;
    return preg_match('/^628\d{8,11}$/', $full) ? $full : null;
}

function wa_pretty(string $n): string {
    return '+' . substr($n, 0, 2) . ' ' . substr($n, 2, 3) . '-' . substr($n, 5, 4) . '-' . substr($n, 9);
}

function wa_mask(string $n): string {
    return substr($n, 0, 4) . str_repeat('*', max(0, strlen($n) - 7)) . substr($n, -3);
}

// Batas: 3 pesan per pengguna per jam, 6 pesan total per jam
function wa_rate(int $uid, string $root): bool {
    $file  = $root . '/storage/logs/whatsapp_test_rate.json';
    $data  = is_file($file) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
    $now   = time();
    $clean = fn($arr) => array_values(array_filter((array)$arr, fn($t) => $t > $now - 3600));
    $mine  = $clean($data['u' . $uid] ?? []);
    $all   = $clean($data['all'] ?? []);
    if (count($mine) >= 3 || count($all) >= 6) return false;
    $mine[] = $now;
    $all[]  = $now;
    $data['u' . $uid] = $mine;
    $data['all']      = $all;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

function wa_saved_load(string $root): array {
    $f = $root . '/storage/logs/whatsapp_test_numbers.json';
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    return is_array($d) ? $d : [];
}

function wa_saved_store(string $root, array $list): void {
    @file_put_contents(
        $root . '/storage/logs/whatsapp_test_numbers.json',
        json_encode(array_values($list), JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

$token = trim((string)(function_exists('env') ? env('FONNTE_TOKEN', '') : ($_ENV['FONNTE_TOKEN'] ?? '')));

$step         = 'form';
$error        = null;
$notice       = null;
$result       = null;
$number       = null;
$pendingLabel = '';

// ---- Proses form ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Token keamanan tidak valid. Muat ulang halaman.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'prepare') {
        $n1    = wa_normalize((string)($_POST['nomor'] ?? ''));
        $n2    = wa_normalize((string)($_POST['nomor2'] ?? ''));
        $label = mb_substr(trim(strip_tags((string)($_POST['label'] ?? ''))), 0, 30);
        if ($n1 === null) {
            $error = 'Format nomor tidak valid. Contoh: 0838xxxxxxxx atau 62838xxxxxxxx (jangan ada angka 0 setelah 62).';
        } elseif ($n1 !== $n2) {
            $error = 'Kedua nomor harus sama. Periksa lagi ketikan Anda.';
        } elseif ($token === '') {
            $error = 'FONNTE_TOKEN belum diisi di file .env.';
        } else {
            $_SESSION['wa_test_pending'] = [
                'n' => $n1, 't' => time(), 'label' => $label, 'simpan' => !empty($_POST['simpan']),
            ];
            $step         = 'confirm';
            $number       = $n1;
            $pendingLabel = $label;
        }

    } elseif ($action === 'send') {
        $p = $_SESSION['wa_test_pending'] ?? null;
        unset($_SESSION['wa_test_pending']);
        if (!$p || time() - (int)$p['t'] > 300) {
            $error = 'Konfirmasi kedaluwarsa. Ulangi dari awal.';
        } elseif (empty($_POST['yakin'])) {
            $error = 'Centang kotak persetujuan pemilik nomor terlebih dahulu.';
        } elseif (!wa_rate($userId, $rootPath)) {
            $error = 'Batas pesan tes per jam tercapai (3 per pengguna, 6 total). Coba lagi nanti.';
        } else {
            $message = "Halo, Pelanggan SmartLaundry! 👋\n\n"
                     . "Kabar baik! Laundry Anda sudah selesai diproses, bersih, dan wangi banget. 🎉\n\n"
                     . "Detail pesanan:\n"
                     . "- Nomor pesanan: ORD-" . date('Ymd') . "-001\n"
                     . "- Layanan: Cuci & Setrika Reguler (Kiloan)\n"
                     . "- Berat: 3,5 KG\n"
                     . "- Total pembayaran: Rp 35.000\n"
                     . "- Status: Laundry Selesai\n\n"
                     . "Silakan menunggu proses pengantaran oleh kurir.\n\n"
                     . "Terima kasih telah mempercayai SmartLaundry. 😊";
            $ch = curl_init('https://api.fonnte.com/send');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
                CURLOPT_POSTFIELDS     => ['target' => $p['n'], 'message' => $message, 'countryCode' => '62'],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false, // XAMPP lokal; jangan dipakai di server produksi
            ]);
            $raw  = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);

            $json = json_decode((string)$raw, true);
            $ok   = is_array($json) && !empty($json['status']);
            $result = [
                'ok'     => $ok,
                'http'   => $http,
                'number' => $p['n'],
                'detail' => is_array($json) ? ($json['detail'] ?? $json['reason'] ?? '') : ($cerr ?: 'Tidak ada respons dari Fonnte'),
            ];

            // Simpan nomor ke daftar bila diminta dan pengiriman diterima
            if ($ok && !empty($p['simpan'])) {
                $list  = wa_saved_load($rootPath);
                $lab   = $p['label'] !== '' ? $p['label'] : wa_mask($p['n']);
                $found = false;
                foreach ($list as &$row) {
                    if (($row['number'] ?? '') === $p['n']) { $row['label'] = $lab; $found = true; }
                }
                unset($row);
                if (!$found && count($list) < 20) {
                    $list[] = ['label' => $lab, 'number' => $p['n']];
                }
                wa_saved_store($rootPath, $list);
            }

            @file_put_contents(
                $rootPath . '/storage/logs/whatsapp_test.log',
                sprintf("[%s] user=%d (%s) target=%s ok=%s http=%s\n",
                    date('Y-m-d H:i:s'), $userId, $userName, wa_mask($p['n']), $ok ? 'ya' : 'tidak', $http),
                FILE_APPEND
            );
        }

    } elseif ($action === 'delete_saved') {
        $idx  = (int)($_POST['idx'] ?? -1);
        $list = wa_saved_load($rootPath);
        if (isset($list[$idx])) {
            unset($list[$idx]);
            wa_saved_store($rootPath, $list);
            $notice = 'Nomor dihapus dari daftar tersimpan.';
        }
    }
}

$saved = wa_saved_load($rootPath);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uji Kirim WhatsApp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<?php $embed = isset($_GET['embed']); ?>
<body class="<?= $embed ? 'bg-white' : 'bg-light' ?>">
<div class="container <?= $embed ? 'py-2' : 'py-4' ?>" style="max-width:<?= $embed ? '100%' : '640px' ?>">
    <?php if (!$embed): ?>
    <div class="mb-3"><a href="../platform/whatsapp-logs">&larr; Kembali ke Log WhatsApp</a></div>
    <?php endif; ?>
    <div class="card <?= $embed ? 'border-0' : 'shadow-sm' ?>">
        <div class="card-body">
            <h4 class="mb-1">Uji Kirim WhatsApp (Fonnte)</h4>
            <p class="text-muted">Ketik nomor tujuan atau pilih dari nomor tersimpan. Pesan baru dikirim setelah dikonfirmasi.</p>

            <p class="small">Token Fonnte:
                <?php if ($token !== ''): ?><span class="badge bg-success">terisi</span>
                <?php else: ?><span class="badge bg-danger">belum diisi di .env</span><?php endif; ?>
            </p>

            <div class="alert alert-secondary small">
                Kirim hanya ke orang yang sudah setuju menerima pesan tes. Fonnte bukan WhatsApp resmi, jadi mengirim ke
                banyak nomor asing dapat membuat nomor pengirim diblokir.
            </div>

            <?php if ($error): ?><div class="alert alert-danger"><?= wa_h($error) ?></div><?php endif; ?>
            <?php if ($notice): ?><div class="alert alert-info"><?= wa_h($notice) ?></div><?php endif; ?>

            <?php if ($result): ?>
                <div class="alert <?= $result['ok'] ? 'alert-success' : 'alert-danger' ?>">
                    <strong><?= $result['ok'] ? 'Diterima oleh Fonnte' : 'Gagal dikirim' ?></strong>
                    ke <?= wa_h(wa_mask($result['number'])) ?> (HTTP <?= (int)$result['http'] ?>).<br>
                    <?= wa_h($result['detail']) ?>
                </div>
                <p class="small text-muted">
                    "Diterima" artinya Fonnte menerima perintah kirim. Cek WhatsApp penerima. Jika nomor tujuan sama dengan
                    nomor device Fonnte, cari di chat "Pesan ke diri sendiri".
                </p>
                <a class="btn btn-outline-primary" href="">Kirim tes lain</a>

            <?php elseif ($step === 'confirm'): ?>
                <div class="alert alert-warning">
                    Pesan tes akan dikirim ke:<br>
                    <span class="fs-4 fw-bold"><?= wa_h(wa_pretty($number)) ?></span>
                    <?php if ($pendingLabel !== ''): ?><br><span class="small">(<?= wa_h($pendingLabel) ?>)</span><?php endif; ?>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= wa_h($csrf) ?>">
                    <input type="hidden" name="action" value="send">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="yakin" id="yakin" value="1">
                        <label class="form-check-label" for="yakin">
                            Pemilik nomor ini sudah setuju menerima pesan tes (bisa nomor saya sendiri).
                        </label>
                    </div>
                    <button class="btn btn-success" type="submit">Kirim pesan tes</button>
                    <a class="btn btn-outline-secondary" href="">Batal</a>
                </form>

            <?php else: ?>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= wa_h($csrf) ?>">
                    <input type="hidden" name="action" value="prepare">

                    <?php if ($saved): ?>
                        <div class="mb-3">
                            <label class="form-label">Pilih dari nomor tersimpan</label>
                            <select class="form-select" id="saved">
                                <option value="">- ketik nomor baru -</option>
                                <?php foreach ($saved as $s): ?>
                                    <option value="<?= wa_h($s['number']) ?>">
                                        <?= wa_h($s['label']) ?> (<?= wa_h(wa_mask($s['number'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Nomor WhatsApp tujuan</label>
                        <input class="form-control" name="nomor" placeholder="0838xxxxxxxx" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ulangi nomor tujuan</label>
                        <input class="form-control" name="nomor2" placeholder="0838xxxxxxxx" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama/label nomor (opsional)</label>
                        <input class="form-control" name="label" maxlength="30" placeholder="mis. HP Dina">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="simpan" id="simpan" value="1">
                        <label class="form-check-label" for="simpan">Simpan nomor ini ke daftar setelah berhasil dikirim</label>
                    </div>
                    <button class="btn btn-primary" type="submit">Lanjut ke konfirmasi</button>
                </form>

                <?php if ($saved): ?>
                    <hr>
                    <h6>Nomor tersimpan</h6>
                    <?php foreach ($saved as $i => $s): ?>
                        <form method="post" class="d-flex justify-content-between align-items-center mb-1"
                              onsubmit="return confirm('Hapus nomor ini dari daftar?')">
                            <input type="hidden" name="csrf" value="<?= wa_h($csrf) ?>">
                            <input type="hidden" name="action" value="delete_saved">
                            <input type="hidden" name="idx" value="<?= (int)$i ?>">
                            <span><?= wa_h($s['label']) ?> <small class="text-muted"><?= wa_h(wa_mask($s['number'])) ?></small></span>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>

                <script>
                    var saved = document.getElementById('saved');
                    if (saved) {
                        saved.addEventListener('change', function () {
                            document.querySelector('[name=nomor]').value  = this.value;
                            document.querySelector('[name=nomor2]').value = this.value;
                        });
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>