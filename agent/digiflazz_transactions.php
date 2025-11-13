<?php
session_start();
error_reporting(0);

if (!isset($_SESSION['agent_id'])) {
    header('Location: index.php');
    exit();
}

include_once('../include/db_config.php');
include_once('../lib/Agent.class.php');

$agentId = (int)$_SESSION['agent_id'];
$agent = new Agent();
$agentData = $agent->getAgentById($agentId);

if (!$agentData || $agentData['status'] !== 'active') {
    header('Location: logout.php');
    exit();
}

$transactions = $agent->getDigiflazzTransactions($agentId, 150);

include_once('include_head.php');
include_once('include_nav.php');
require_once(__DIR__ . '/include_digiflazz_print.php');
?>

<style>
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.status-success { background: #dcfce7; color: #166534; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-failed  { background: #fee2e2; color: #b91c1c; }

.serial-chip {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(15,23,42,0.08);
    font-family: 'Courier New', monospace;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .4px;
}

.dark .serial-chip {
    background: rgba(255,255,255,0.08);
    color: #f9fafb;
}

.status-note {
    margin-top: 4px;
    font-size: 11px;
    color: #475569;
}

.tx-card {
    background: #fff;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    transition: box-shadow .2s ease;
}

.tx-card:hover {
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
}

.tx-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.tx-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    font-size: 13px;
    color: #475569;
}

.tx-meta strong {
    font-size: 13px;
    color: #0f172a;
}

.tx-amount {
    font-weight: 700;
    font-size: 15px;
}

.tx-grid {
    display: grid;
    gap: 16px;
}

@media (max-width: 768px) {
    .tx-card {
        padding: 15px;
    }
    .tx-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .tx-meta {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    }
}

.dark .tx-card {
    background: #1f2937;
    border-color: #334155;
    color: #e2e8f0;
}

.dark .tx-meta strong {
    color: #f8fafc;
}

.dark .status-note {
    color: #cbd5f5;
}
</style>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h3><i class="fa fa-bolt"></i> Riwayat Transaksi Digiflazz</h3>
                    <small>Daftar transaksi digital terakhir Anda (maksimal 150 riwayat).</small>
                </div>
                <div>
                    <a href="digital_products.php" class="btn btn-sm btn-primary"><i class="fa fa-shopping-cart"></i> Order Produk</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <div class="alert alert-info"><i class="fa fa-info-circle"></i> Belum ada transaksi Digiflazz.</div>
                <?php else: ?>
                    <div class="tx-grid">
                        <?php foreach ($transactions as $tx): ?>
                            <?php
                                $statusRaw = strtolower($tx['digiflazz_status'] ?? '');
                                $statusClass = 'status-pending';
                                $statusLabel = 'PENDING';
                                if (!$statusRaw || in_array($statusRaw, ['success', 'sukses', 'berhasil', 'ok'])) {
                                    $statusClass = 'status-success';
                                    $statusLabel = 'BERHASIL';
                                } elseif (in_array($statusRaw, ['pending', 'process', 'processing', 'menunggu'])) {
                                    $statusClass = 'status-pending';
                                    $statusLabel = 'PENDING';
                                } else {
                                    $statusClass = 'status-failed';
                                    $statusLabel = strtoupper($statusRaw);
                                }

                                $isDebit = ($tx['transaction_type'] !== 'topup');
                                $amountPrefix = $isDebit ? '-' : '+';
                                $amountColor = $isDebit ? '#ef4444' : '#10b981';
                                $basePrice = isset($tx['digiflazz_base_price']) ? (int)$tx['digiflazz_base_price'] : (int)$tx['amount'];
                                $sellPrice = isset($tx['digiflazz_sell_price']) ? (int)$tx['digiflazz_sell_price'] : (int)$tx['amount'];
                                $profit = max(0, $sellPrice - $basePrice);
                                $description = $tx['description'] ?: ($tx['profile_name'] . ' - ' . $tx['voucher_username']);
                            ?>
                            <div class="tx-card">
                                <div class="tx-header">
                                    <div>
                                        <div style="font-size:13px; color:#64748b;">Ref ID</div>
                                        <div style="font-weight:700; color:#0f172a; font-size:15px;">
                                            <?= htmlspecialchars($tx['voucher_username']); ?>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <span class="status-badge <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                                        <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                            <?= date('d M Y H:i', strtotime($tx['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="tx-meta">
                                    <div>
                                        <strong>Produk</strong><br>
                                        <?= htmlspecialchars($description); ?>
                                    </div>
                                    <div>
                                        <strong>Nomor Tujuan</strong><br>
                                        <?= htmlspecialchars($tx['digiflazz_customer_no'] ?: '-'); ?>
                                    </div>
                                    <div>
                                        <strong>Nama Pelanggan</strong><br>
                                        <?= htmlspecialchars($tx['digiflazz_customer_name'] ?: '-'); ?>
                                    </div>
                                    <div class="tx-amount" style="color: <?= $amountColor; ?>;">
                                        <?= $amountPrefix; ?>Rp <?= number_format($tx['amount'], 0, ',', '.'); ?>
                                        <div style="font-size:11px; color:#475569; margin-top:4px;">
                                            Modal: Rp <?= number_format($basePrice, 0, ',', '.'); ?><br>
                                            Untung: Rp <?= number_format($profit, 0, ',', '.'); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="tx-actions" style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                                    <button
                                        class="print-btn"
                                        data-digiflazz-print
                                        data-ref="<?= htmlspecialchars($tx['voucher_username']); ?>"
                                        data-product="<?= htmlspecialchars($tx['profile_name'] ?: $description); ?>"
                                        data-description="<?= htmlspecialchars($description); ?>"
                                        data-status="<?= htmlspecialchars($statusLabel); ?>"
                                        data-status-class="<?= htmlspecialchars($statusClass); ?>"
                                        data-message="<?= htmlspecialchars($tx['digiflazz_message'] ?? ''); ?>"
                                        data-customer-no="<?= htmlspecialchars($tx['digiflazz_customer_no'] ?? ''); ?>"
                                        data-customer-name="<?= htmlspecialchars($tx['digiflazz_customer_name'] ?? ''); ?>"
                                        data-serial="<?= htmlspecialchars($tx['digiflazz_serial'] ?? ''); ?>"
                                        data-sell-price="<?= $sellPrice; ?>"
                                        data-base-price="<?= $basePrice; ?>"
                                        data-created-at="<?= htmlspecialchars($tx['created_at']); ?>"
                                    >
                                        <i class="fa fa-print"></i> Cetak
                                    </button>
                                </div>
                                <?php if (!empty($tx['digiflazz_message'])): ?>
                                    <div class="status-note">
                                        <strong>Catatan:</strong> <?= htmlspecialchars($tx['digiflazz_message']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($tx['digiflazz_serial'])): ?>
                                    <div class="serial-chip">SN: <?= htmlspecialchars($tx['digiflazz_serial']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once('include_foot.php'); ?>
