<?php
// No session_start() needed - already started in index.php
// No auth check needed - already checked in index.php

include_once('./include/db_config.php');
include_once('./lib/Agent.class.php');

$agent = new Agent();
$agentId = $_GET['agent_id'] ?? 0;
$agentData = $agentId ? $agent->getAgentById($agentId) : null;
$transactions = $agentId ? $agent->getTransactions($agentId, 100) : [];
$agents = $agent->getAllAgents();

// Get session from URL or global
$session = $_GET['session'] ?? (isset($session) ? $session : '');
?>

<style>
/* Minimal custom styles - using MikhMon classes */
.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-topup {
    background: #d1fae5;
    color: #065f46;
}

.badge-generate {
    background: #fee2e2;
    color: #991b1b;
}

.badge-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-failed {
    background: #fee2e2;
    color: #b91c1c;
}

.status-note {
    margin-top: 4px;
    font-size: 11px;
    color: #475569;
}
</style>

<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
    <h3><i class="fa fa-history"></i> Transaksi Agent</h3>
</div>
<div class="card-body">
    <div class="card">
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="hotspot" value="agent-transactions">
                <input type="hidden" name="session" value="<?= $session; ?>">
                <div class="form-group">
                    <label>Pilih Agent</label>
                    <select name="agent_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Pilih Agent --</option>
                        <?php foreach ($agents as $agt): ?>
                        <option value="<?= $agt['id']; ?>" <?= $agentId == $agt['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($agt['agent_name']); ?> (<?= htmlspecialchars($agt['agent_code']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($agentData && !empty($transactions)): ?>
    <div class="card">
        <div class="card-header">
            <h3><?= htmlspecialchars($agentData['agent_name']); ?> - Saldo: Rp <?= number_format($agentData['balance'], 0, ',', '.'); ?></h3>
        </div>
        <div class="card-body">
        <div class="table-responsive">
        <table class="table table-bordered table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Tipe</th>
                    <th>Jumlah</th>
                    <th>Status</th>
                    <th>SN</th>
                    <th>Saldo Sebelum</th>
                    <th>Saldo Sesudah</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $trx): ?>
                <tr>
                    <td><?= date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                    <td><span class="badge badge-<?= $trx['transaction_type']; ?>"><?= ucfirst($trx['transaction_type']); ?></span></td>
                    <td style="font-weight: bold; color: <?= $trx['transaction_type'] == 'topup' ? '#10b981' : '#ef4444'; ?>">
                        <?= $trx['transaction_type'] == 'topup' ? '+' : '-'; ?>Rp <?= number_format($trx['amount'], 0, ',', '.'); ?>
                    </td>
                    <td>
                        <?php if ($trx['transaction_type'] === 'digiflazz'): ?>
                            <?php
                                $statusRaw = strtolower($trx['digiflazz_status'] ?? '');
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
                            ?>
                            <span class="badge-status <?= $statusClass; ?>"><?= htmlspecialchars($statusLabel); ?></span>
                            <?php if (!empty($trx['digiflazz_message'])): ?>
                                <div class="status-note"><?= htmlspecialchars($trx['digiflazz_message']); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($trx['transaction_type'] === 'digiflazz' && !empty($trx['digiflazz_serial'])): ?>
                            <span class="badge-status" style="background:#e0f2fe;color:#0f172a;font-family:'Courier New',monospace;letter-spacing:0.4px;"><?= htmlspecialchars($trx['digiflazz_serial']); ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>Rp <?= number_format($trx['balance_before'], 0, ',', '.'); ?></td>
                    <td>Rp <?= number_format($trx['balance_after'], 0, ',', '.'); ?></td>
                    <td><?= htmlspecialchars($trx['description'] ?: ($trx['profile_name'] . ' - ' . $trx['voucher_username'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
</div>
