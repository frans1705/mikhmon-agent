<?php
/*
 * Billing Portal (Admin Preview & Customer Portal builder)
 */

include_once('./include/db_config.php');
require_once('./lib/BillingService.class.php');

if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Jakarta');
}

$theme = 'default';
$themecolor = '#2563eb';
if (file_exists('./include/theme.php')) {
    include('./include/theme.php');
}

if (empty($themecolor)) {
    $themecolor = '#2563eb';
}

if (!function_exists('billingPortalAdjustColor')) {
    function billingPortalAdjustColor(string $hexColor, float $percent): string
    {
        $hexColor = ltrim($hexColor, '#');
        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0] . $hexColor[0] . $hexColor[1] . $hexColor[1] . $hexColor[2] . $hexColor[2];
        }

        $rgb = [
            hexdec(substr($hexColor, 0, 2)),
            hexdec(substr($hexColor, 2, 2)),
            hexdec(substr($hexColor, 4, 2)),
        ];

        foreach ($rgb as &$component) {
            $component = max(0, min(255, (int)round($component + ($percent / 100) * 255)));
        }

        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }
}

$accentPrimary = $themecolor;
$accentPrimaryLight = billingPortalAdjustColor($accentPrimary, 35);
$accentSuccess = billingPortalAdjustColor($accentPrimary, 45);
$accentWarn = billingPortalAdjustColor($accentPrimary, -15);
$accentWarnLight = billingPortalAdjustColor($accentPrimary, 15);

$serviceNumber = trim($_GET['service_number'] ?? $_GET['service'] ?? $_POST['service_number'] ?? '');
$pppoeUsername = trim($_GET['pppoe'] ?? $_GET['username'] ?? $_POST['pppoe_username'] ?? '');
$portalError = '';
$customer = null;
$profile = null;
$invoices = [];
$outstanding = [];
$wifiFeedback = ['error' => null, 'success' => null];

try {
    $billingService = new BillingService();
} catch (Throwable $e) {
    echo '<div class="alert bg-danger" style="margin:20px; border-radius:8px;">';
    echo '<strong>Billing Service error:</strong> ' . htmlspecialchars($e->getMessage());
    echo '</div>';
    return;
}

if ($serviceNumber !== '' || $pppoeUsername !== '') {
    try {
        if ($pppoeUsername !== '') {
            $customer = $billingService->getCustomerByPppoeUsername($pppoeUsername);
        }
        if (!$customer && $serviceNumber !== '') {
            $customer = $billingService->getCustomerByServiceNumber($serviceNumber);
        }
        if (!$customer && $pppoeUsername === '' && $serviceNumber !== '') {
            $customer = $billingService->getCustomerByPhone($serviceNumber);
        }
    } catch (Throwable $e) {
        $portalError = 'Gagal memuat data pelanggan: ' . $e->getMessage();
    }

    if (!$customer && !$portalError) {
        $portalError = 'Pelanggan tidak ditemukan. Periksa kembali nomor layanan atau PPPoE username.';
    }

    if ($customer) {
        $profile = $billingService->getProfileById((int)($customer['profile_id'] ?? 0));
        try {
            $invoices = $billingService->listInvoices(['customer_id' => (int)$customer['id']], 12);
        } catch (Throwable $e) {
            $portalError = 'Gagal memuat daftar invoice: ' . $e->getMessage();
        }
        $outstanding = array_filter($invoices, static function (array $invoice) {
            return in_array(strtolower($invoice['status'] ?? ''), ['unpaid', 'overdue'], true);
        });
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_wifi'])) {
    $ssid = trim($_POST['ssid'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$customer) {
        $wifiFeedback['error'] = 'Pelanggan tidak ditemukan. Pastikan nomor layanan benar lalu coba lagi.';
    } elseif (mb_strlen($ssid) < 3) {
        $wifiFeedback['error'] = 'SSID minimal 3 karakter.';
    } else {
        try {
            $billingService->changeCustomerWifi((int)$customer['id'], $ssid, $password !== '' ? $password : null);
            $wifiFeedback['success'] = 'Perubahan WiFi berhasil dikirim. Harap tunggu beberapa menit dan hubungkan ulang perangkat.';
        } catch (Throwable $e) {
            $wifiFeedback['error'] = $e->getMessage();
        }
    }
}

$publicPortalUrl = '';
if ($customer && !empty($customer['service_number'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '.') {
        $scriptDir = '';
    }
    if ($host !== '') {
        $publicPortalUrl = rtrim($scheme . '://' . $host . $scriptDir, '/') . '/public/billing_portal.php?service=' . urlencode($customer['service_number']);
    } else {
        $publicPortalUrl = './public/billing_portal.php?service=' . urlencode($customer['service_number']);
    }
}
?>
<style>
.billing-portal {
    font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
    font-size: 15px;
    color: #1f2937;
}

.billing-portal .card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    margin-bottom: 20px;
    background: rgba(255, 255, 255, 0.96);
}

.billing-portal .card-header {
    padding: 18px 22px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.billing-portal .card-header h3,
.billing-portal .card-header h2 {
    margin: 0;
    font-weight: 600;
    color: #0f172a;
}

.billing-portal .card-body { padding: 22px; }

.billing-portal .summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 20px;
}

.billing-portal .box {
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    padding: 20px;
    color: #fff;
    min-height: 120px;
}

.billing-portal .box h2 {
    margin: 0 0 6px;
    font-size: 26px;
    font-weight: 700;
}

.billing-portal .portal-alert {
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 18px;
}

.billing-portal .portal-alert strong { font-weight: 600; }

.billing-portal .table-responsive {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.05);
}

.billing-portal table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.billing-portal th,
.billing-portal td {
    padding: 12px 14px;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    text-align: left;
}

.billing-portal thead th {
    background: rgba(15, 23, 42, 0.06);
    font-weight: 600;
}

.billing-portal .badge-status {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.billing-portal .badge-status.paid { background: rgba(34, 197, 94, 0.15); color: #15803d; }
.billing-portal .badge-status.unpaid { background: rgba(239, 68, 68, 0.16); color: #b91c1c; }
.billing-portal .badge-status.overdue { background: rgba(234, 179, 8, 0.2); color: #92400e; }

.billing-portal .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
}

.billing-portal .share-link {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-top: 10px;
}

.billing-portal .share-link input {
    flex: 1;
    min-width: 220px;
    background: rgba(15, 23, 42, 0.04);
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 14px;
}

.billing-portal .share-link button {
    padding: 9px 16px;
    border-radius: 6px;
    border: none;
    background: <?= htmlspecialchars($accentPrimary); ?>;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

@media (max-width: 768px) {
    .billing-portal .card-body { padding: 18px; }
    .billing-portal .summary-grid { grid-template-columns: 1fr; }
}
</style>

<div class="billing-module billing-portal">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa fa-globe"></i> Portal Pelanggan Billing</h2>
                    <p style="margin:8px 0 0; color:#6b7280;">
                        Pratinjau portal yang akan diakses pelanggan. Masukkan nomor layanan/CPE ID untuk melihat detail.
                    </p>
                </div>
                <div class="card-body">
                    <form method="get" class="form-grid" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label for="portal_service_number">Nomor Layanan / CPE ID</label>
                            <input type="text" id="portal_service_number" name="service_number" class="form-control" placeholder="Contoh: CPE-00123" value="<?= htmlspecialchars($serviceNumber); ?>">
                            <small class="form-text text-muted">Boleh dikosongkan jika mencari berdasarkan PPPoE username.</small>
                        </div>
                        <div class="form-group">
                            <label for="portal_pppoe_username">PPPoE Username</label>
                            <input type="text" id="portal_pppoe_username" name="pppoe_username" class="form-control" placeholder="user@isp" value="<?= htmlspecialchars($pppoeUsername); ?>">
                            <small class="form-text text-muted">Jika diisi, pencarian akan diprioritaskan dengan PPPoE username.</small>
                        </div>
                        <div class="form-group" style="display:flex; align-items:flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding:10px 18px;">
                                <i class="fa fa-search"></i> Tampilkan Portal
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($portalError)): ?>
                        <div class="portal-alert" style="background: rgba(251, 191, 36, 0.15); color:#92400e;">
                            <strong>Perhatian:</strong> <?= htmlspecialchars($portalError); ?>
                        </div>
                    <?php elseif ($customer): ?>
                        <div class="summary-grid">
                            <div class="box" style="background: linear-gradient(135deg, <?= htmlspecialchars($accentPrimary); ?>, <?= htmlspecialchars($accentPrimaryLight); ?>);">
                                <h2><?= htmlspecialchars($profile['profile_name'] ?? 'Profil tidak ditemukan'); ?></h2>
                                <p style="margin:0;">Harga: Rp <?= number_format((float)($profile['price_monthly'] ?? 0), 0, ',', '.'); ?>/bulan</p>
                                <p style="margin:0;">Billing tiap tanggal <?= str_pad((int)($customer['billing_day'] ?? 1), 2, '0', STR_PAD_LEFT); ?></p>
                            </div>
                            <div class="box" style="background: linear-gradient(135deg, <?= htmlspecialchars($accentSuccess); ?>, <?= htmlspecialchars(billingPortalAdjustColor($accentSuccess, 18)); ?>);">
                                <h2><?= count($outstanding); ?></h2>
                                <p style="margin:0;">Tagihan belum dibayar</p>
                                <p style="margin:0;">Total invoice tampil (12 terbaru)</p>
                            </div>
                            <div class="box" style="background: linear-gradient(135deg, <?= htmlspecialchars($accentWarn); ?>, <?= htmlspecialchars($accentWarnLight); ?>);">
                                <h2><?= strtoupper(htmlspecialchars($customer['status'] ?? 'unknown')); ?></h2>
                                <p style="margin:0;">Status akun pelanggan</p>
                                <p style="margin:0;">Kondisi: <?= (int)($customer['is_isolated'] ?? 0) === 1 ? 'TERISOLASI' : 'NORMAL'; ?></p>
                            </div>
                        </div>

                        <div class="card" style="margin-top: 10px;">
                            <div class="card-header" style="border-bottom:none;">
                                <h3 style="font-size:18px;"><i class="fa fa-id-card"></i> Identitas Pelanggan</h3>
                            </div>
                            <div class="card-body" style="padding-top:0;">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Nama</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($customer['name'] ?? '-'); ?>" disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>No. WhatsApp</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($customer['phone'] ?? '-'); ?>" disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '-'); ?>" disabled>
                                    </div>
                                    <div class="form-group">
                                        <label>PPPoE Username</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($customer['genieacs_pppoe_username'] ?? '-'); ?>" disabled>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top:15px;">
                                    <label>Tautan Portal Publik</label>
                                    <div class="share-link">
                                        <input type="text" value="<?= htmlspecialchars($publicPortalUrl); ?>" readonly>
                                        <button type="button" onclick="navigator.clipboard?.writeText('<?= htmlspecialchars($publicPortalUrl); ?>');">
                                            <i class="fa fa-clipboard"></i> Salin Link
                                        </button>
                                    </div>
                                    <small style="display:block;margin-top:8px;color:#6b7280;">Bagikan tautan ini kepada pelanggan agar mereka bisa memantau tagihan dan mengubah SSID secara mandiri.</small>
                                </div>
                            </div>
                        </div>

                        <div class="card" style="margin-top: 10px;">
                            <div class="card-header">
                                <h3 style="font-size:18px;"><i class="fa fa-file-text-o"></i> Riwayat Tagihan</h3>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Periode</th>
                                                <th>Nominal</th>
                                                <th>Jatuh Tempo</th>
                                                <th>Status</th>
                                                <th>Dibayar</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (empty($invoices)): ?>
                                            <tr>
                                                <td colspan="5" style="text-align:center; padding: 24px; color:#6b7280;">Belum ada invoice yang tercatat.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($invoices as $invoice): ?>
                                                <?php $status = strtolower($invoice['status'] ?? 'unpaid'); ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($invoice['period']); ?></td>
                                                    <td>Rp <?= number_format((float)$invoice['amount'], 0, ',', '.'); ?></td>
                                                    <td><?= htmlspecialchars(date('d M Y', strtotime($invoice['due_date']))); ?></td>
                                                    <td><span class="badge-status <?= htmlspecialchars($status); ?>"><?= ucfirst($status); ?></span></td>
                                                    <td><?= !empty($invoice['paid_at']) ? htmlspecialchars(date('d M Y H:i', strtotime($invoice['paid_at']))) : '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card" style="margin-top: 10px;">
                            <div class="card-header">
                                <h3 style="font-size:18px;"><i class="fa fa-wifi"></i> Ubah SSID / Password WiFi</h3>
                                <p style="margin:6px 0 0; color:#6b7280;">Perubahan dikirim ke perangkat lewat GenieACS. Pastikan pelanggan terhubung setelah perangkat menerima update.</p>
                            </div>
                            <div class="card-body">
                                <?php if ($wifiFeedback['error']): ?>
                                    <div class="portal-alert" style="background: rgba(239, 68, 68, 0.16); color:#b91c1c;">
                                        <strong>Gagal:</strong> <?= htmlspecialchars($wifiFeedback['error']); ?>
                                    </div>
                                <?php elseif ($wifiFeedback['success']): ?>
                                    <div class="portal-alert" style="background: rgba(34, 197, 94, 0.12); color:#15803d;">
                                        <strong>Sukses:</strong> <?= htmlspecialchars($wifiFeedback['success']); ?>
                                    </div>
                                <?php endif; ?>
                                <form method="post">
                                    <input type="hidden" name="service_number" value="<?= htmlspecialchars($customer['service_number']); ?>">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label>SSID Baru</label>
                                            <input type="text" name="ssid" class="form-control" placeholder="Nama WiFi" minlength="3" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Password Baru (opsional)</label>
                                            <input type="text" name="password" class="form-control" placeholder="Kosongkan jika tidak berubah">
                                            <small style="color:#6b7280;">Minimal 8 karakter untuk keamanan yang baik.</small>
                                        </div>
                                    </div>
                                    <input type="hidden" name="change_wifi" value="1">
                                    <button type="submit" class="btn btn-primary" style="margin-top:14px;">
                                        <i class="fa fa-save"></i> Kirim Perubahan
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="portal-alert" style="background: rgba(59, 130, 246, 0.12); color:#1d4ed8;">
                            Masukkan nomor layanan pelanggan untuk menampilkan rincian portal. Halaman ini mengikuti tema MikhMon aktif sehingga tampilan pelanggan selalu konsisten.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
