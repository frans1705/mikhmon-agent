<?php
session_start();
error_reporting(0);

// Check if logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

include_once('../include/db_config.php');
include_once('../lib/Agent.class.php');
include_once('../lib/BillingService.class.php');

$agent = new Agent();
$billingService = new BillingService();
$agentId = $_SESSION['agent_id'];
$agentData = $agent->getAgentById($agentId);

// Get agent billing settings
$agentSettings = $agent->getAgentSettings($agentId);
$feeEnabled = $agentSettings['billing_payment_fee_enabled'] ?? '0';
$feePercent = (float)($agentSettings['billing_payment_fee_percent'] ?? 0);
$broadcastTemplate = $agentSettings['billing_payment_broadcast_template'] ?? '';
$agentCommissionAmount = max(0.0, (float)($agentData['commission_amount'] ?? 0));

// Handle search
$searchResults = [];
$selectedCustomer = null;
$customerInvoices = [];
$paymentReceipt = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_customer'])) {
    $searchType = $_POST['search_type'] ?? 'phone';
    $searchValue = trim($_POST['search_value'] ?? '');

    if (!empty($searchValue)) {
        try {
            $customer = null;
            if ($searchType === 'phone') {
                $customer = $billingService->getCustomerByPhone($searchValue);
            } elseif ($searchType === 'service_number') {
                $customer = $billingService->getCustomerByServiceNumber($searchValue);
            } elseif ($searchType === 'pppoe_username') {
                $customer = $billingService->getCustomerByPppoeUsername($searchValue);
            } elseif ($searchType === 'name') {
                // Search by name (limited to 10 results)
                $customers = $billingService->getCustomers(10);
                foreach ($customers as $c) {
                    if (stripos($c['name'], $searchValue) !== false) {
                        $customer = $c;
                        break;
                    }
                }
            }

            if ($customer) {
                $selectedCustomer = $customer;
                // Get unpaid/overdue invoices
                $invoices = $billingService->listInvoices([
                    'customer_id' => $customer['id'],
                    'statuses' => ['unpaid', 'overdue']
                ], 10);
                $customerInvoices = $invoices;
            } else {
                $error = 'Pelanggan tidak ditemukan.';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = 'Masukkan nilai pencarian.';
    }
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_invoice'])) {
    $invoiceId = (int)($_POST['invoice_id'] ?? 0);
    $customerId = (int)($_POST['customer_id'] ?? 0);

    if ($invoiceId > 0 && $customerId > 0) {
        $invoice = $billingService->getInvoiceById($invoiceId);
        $agentFeeInput = $agentCommissionAmount;
        $invoiceAmount = 0.0;

        if ($invoice) {
            $invoiceAmount = (float)$invoice['amount'];
            if ($agentFeeInput > $invoiceAmount) {
                $agentFeeInput = $invoiceAmount;
            }
        }

        $fee = 0.0;
        if ($agentFeeInput > 0) {
            $fee = -$agentFeeInput;
        } elseif ($feeEnabled === '1' && $feePercent > 0 && $invoice) {
            $fee = ($invoice['amount'] * $feePercent / 100);
        }

        try {
            $result = $billingService->payInvoiceWithAgentBalance($agentId, $invoiceId, $fee);

            if ($result['success']) {
                $success = 'Pembayaran berhasil! Saldo dipotong Rp ' . number_format($deductedTotal, 0, ',', '.') . ' &mdash; sisa saldo: Rp ' . number_format($result['balance_after'], 0, ',', '.');

                $customer = $billingService->getCustomerById($customerId);
                $paidInvoice = $billingService->getInvoiceById($invoiceId);
                $paidAt = $paidInvoice && !empty($paidInvoice['paid_at'])
                    ? date('d M Y H:i', strtotime($paidInvoice['paid_at']))
                    : date('d M Y H:i');
                $dueDateFormatted = $paidInvoice && !empty($paidInvoice['due_date'])
                    ? date('d M Y', strtotime($paidInvoice['due_date']))
                    : '-';
                $invoiceAmount = (float)($paidInvoice['amount'] ?? ($invoice['amount'] ?? 0));
                $agentFeeApplied = $agentFeeInput > 0 ? $agentFeeInput : 0.0;
                $deductedTotal = $invoiceAmount + (float)$fee;

                $profileName = '-';
                if ($paidInvoice && !empty($paidInvoice['profile_snapshot'])) {
                    $snapshot = json_decode($paidInvoice['profile_snapshot'], true);
                    if (is_array($snapshot) && !empty($snapshot['profile_name'])) {
                        $profileName = $snapshot['profile_name'];
                    }
                }
                if ($profileName === '-' && $customer && !empty($customer['profile_id'])) {
                    $profileData = $billingService->getProfileById((int)$customer['profile_id']);
                    if ($profileData && !empty($profileData['profile_name'])) {
                        $profileName = $profileData['profile_name'];
                    }
                }
                $invoiceStatus = strtoupper($paidInvoice['status'] ?? ($invoice['status'] ?? 'paid'));

                $amountFormatted = number_format($invoiceAmount, 0, ',', '.');
                $totalFormatted = number_format($deductedTotal, 0, ',', '.');
                $balanceAfterFormatted = number_format($result['balance_after'], 0, ',', '.');

                $paymentReceipt = [
                    'invoice_id' => $invoiceId,
                    'customer_id' => $customerId,
                    'customer_name' => $customer['name'] ?? '-',
                    'customer_phone' => $customer['phone'] ?? '-',
                    'service_number' => $customer['service_number'] ?? '-',
                    'pppoe_username' => $customer['genieacs_pppoe_username'] ?? '-',
                    'period' => $paidInvoice['period'] ?? ($invoice['period'] ?? ''),
                    'due_date' => $dueDateFormatted,
                    'paid_at' => $paidAt,
                    'invoice_status' => $invoiceStatus,
                    'profile_name' => $profileName,
                    'amount' => $invoiceAmount,
                    'fee' => (float)$fee,
                    'agent_fee' => $agentFeeApplied,
                    'deduction' => $deductedTotal,
                    'total' => $deductedTotal,
                    'amount_formatted' => $amountFormatted,
                    'total_formatted' => $totalFormatted,
                    'balance_after_formatted' => $balanceAfterFormatted,
                    'balance_after' => (float)$result['balance_after'],
                    'agent_name' => $agentData['agent_name'] ?? '-',
                    'agent_code' => $agentData['agent_code'] ?? '-',
                ];

                $notification = null;
                if ($customer && !empty($customer['phone'])) {
                    include_once('../lib/WhatsAppNotification.class.php');
                    $notification = new WhatsAppNotification();
                }

                // Send broadcast if template available
                if ($notification && !empty($broadcastTemplate) && $paidInvoice) {
                    $payload = [
                        'customer_name' => $customer['name'],
                        'period' => $paidInvoice['period'],
                        'amount_formatted' => number_format($paidInvoice['amount'], 0, ',', '.'),
                        'agent_name' => $agentData['agent_name']
                    ];
                    $notification->notifyInvoicePaidByAgent($customer['phone'], $payload);
                }

                // Send structured WhatsApp receipt
                if ($notification) {
                    $waPayload = [
                        'invoice_id' => $invoiceId,
                        'customer_name' => $customer['name'] ?? '-',
                        'package_name' => $profileName,
                        'period' => $paymentReceipt['period'],
                        'due_date' => $dueDateFormatted,
                        'status' => $invoiceStatus,
                        'amount' => $invoiceAmount,
                        'amount_formatted' => $amountFormatted,
                        'total_paid_formatted' => $totalFormatted,
                        'balance_after_formatted' => $balanceAfterFormatted,
                        'agent_name' => $agentData['agent_name'] ?? '-',
                        'service_number' => $customer['service_number'] ?? '-',
                        'pppoe_username' => $customer['genieacs_pppoe_username'] ?? '-',
                        'paid_at' => $paidAt,
                    ];
                    $notification->notifyInvoicePaidByAgent($customer['phone'], $waPayload);
                }

                // Refresh customer invoices
                $customerInvoices = $billingService->listInvoices([
                    'customer_id' => $customerId,
                    'statuses' => ['unpaid', 'overdue']
                ], 10);
            } else {
                $error = $result['message'] ?? 'Pembayaran gagal diproses.';
            }
        } catch (Throwable $paymentException) {
            error_log('Agent payment failed: ' . $paymentException->getMessage());
            $error = 'Terjadi kesalahan saat memproses pembayaran: ' . htmlspecialchars($paymentException->getMessage());
        }
    } else {
        $error = 'Parameter tidak valid.';
    }
}

include_once('include_head.php');
include_once('include_nav.php');
?>

<div class="content-wrapper">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa fa-money"></i> Bayar Tagihan Pelanggan</h3>
                    <small>Cari pelanggan dan bayar tagihan menggunakan saldo agen</small>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <strong>Gagal:</strong> <?= htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <strong>Berhasil:</strong> <?= htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Search Form -->
                    <div class="row">
                        <div class="col-12">
                            <form method="post" class="mb-4">
                                <div class="form-group">
                                    <label for="search_type">Cari Berdasarkan:</label>
                                    <select name="search_type" id="search_type" class="form-control" style="width: 200px; display: inline-block; margin-right: 10px;">
                                        <option value="phone">Nomor Telepon</option>
                                        <option value="service_number">Service Number</option>
                                        <option value="pppoe_username">PPPoE Username</option>
                                        <option value="name">Nama Pelanggan</option>
                                    </select>
                                    <input type="text" name="search_value" id="search_value" class="form-control" style="width: 300px; display: inline-block; margin-right: 10px;" placeholder="Masukkan nilai pencarian" required>
                                    <button type="submit" name="search_customer" class="btn btn-primary">Cari</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($selectedCustomer): ?>
                        <!-- Customer Info -->
                        <style>
                        .billing-customer-card,
                        .billing-invoice-card {
                            border-radius: 14px;
                        }

                        .billing-total-box {
                            background: #f8fafc;
                            border: 1px solid #e2e8f0;
                            border-radius: 12px;
                            padding: 12px 14px;
                            display: flex;
                            flex-direction: column;
                            gap: 6px;
                            font-size: 13px;
                            color: #0f172a;
                        }

                        .billing-total-box strong {
                            font-size: 15px;
                            color: #0b1f33;
                        }

                        .billing-total-box .discount {
                            color: #059669;
                            font-weight: 700;
                        }

                        .billing-total-box .total-deduct {
                            font-size: 16px;
                            font-weight: 700;
                            color: #1d4ed8;
                        }

                        .invoice-table {
                            width: 100%;
                        }

                        .billing-customer-card .card-body p {
                            font-size: 16px;
                            color: #0f172a;
                            font-weight: 600;
                            margin-bottom: 10px;
                        }

                        .billing-customer-card .card-body strong {
                            color: #0b1f33;
                        }

                        .payment-receipt-overlay {
                            position: fixed;
                            inset: 0;
                            background: rgba(15, 23, 42, 0.65);
                            display: none;
                            align-items: center;
                            justify-content: center;
                            padding: 20px;
                            z-index: 4000;
                        }

                        .payment-receipt-modal {
                            background: #ffffff;
                            border-radius: 18px;
                            padding: 26px;
                            max-width: 440px;
                            width: 100%;
                            position: relative;
                            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.25);
                            color: #0f172a;
                            font-size: 15px;
                            line-height: 1.6;
                        }

                        .receipt-header {
                            margin-bottom: 18px;
                            display: flex;
                            flex-direction: column;
                            gap: 4px;
                        }

                        .receipt-status {
                            font-size: 18px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                            color: #047857;
                        }

                        .receipt-subtitle {
                            color: #1e293b;
                            font-size: 15px;
                            font-weight: 500;
                        }

                        .receipt-close {
                            position: absolute;
                            top: 14px;
                            right: 16px;
                            border: none;
                            background: none;
                            font-size: 22px;
                            color: #64748b;
                            cursor: pointer;
                        }

                        .payment-receipt-summary dl {
                            display: grid;
                            grid-template-columns: 130px 1fr;
                            row-gap: 8px;
                            column-gap: 12px;
                            margin: 0;
                        }

                        .payment-receipt-summary dt {
                            font-size: 13px;
                            text-transform: uppercase;
                            letter-spacing: 0.6px;
                            color: #1e293b;
                            font-weight: 700;
                        }

                        .payment-receipt-summary dd {
                            margin: 0;
                            font-weight: 700;
                            font-size: 17px;
                            color: #0b1f33;
                            letter-spacing: 0.2px;
                        }

                        .payment-receipt-actions {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 10px;
                            margin-top: 22px;
                        }

                        .payment-receipt-actions button i {
                            margin-right: 6px;
                            font-size: 15px;
                        }

                        .payment-receipt-actions button {
                            flex: 1 1 calc(33.333% - 10px);
                            border: none;
                            border-radius: 10px;
                            padding: 10px 14px;
                            font-weight: 700;
                            font-size: 15px;
                            cursor: pointer;
                            transition: transform 0.15s ease, box-shadow 0.15s ease;
                        }

                        .payment-receipt-actions button:hover {
                            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                            color: #fff;
                        }

                        #printNormalBtn {
                            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                            color: #fff;
                        }

                        #printThermalBtn {
                            background: linear-gradient(135deg, #059669 0%, #047857 100%);
                            color: #fff;
                        }

                        #receiptDoneBtn {
                            background: #f1f5f9;
                            color: #1e293b;
                            border: 1px solid #cbd5f5;
                        }

                        @media (max-width: 768px) {
                            .billing-customer-card,
                            .billing-invoice-card {
                                margin: 0 -12px 18px;
                                border-radius: 0;
                                box-shadow: none;
                            }

                            .billing-customer-card .card-body {
                                font-size: 16px;
                                line-height: 1.65;
                            }

                            .invoice-table thead {
                                display: none;
                            }

                            .invoice-table tbody,
                            .invoice-table tr,
                            .invoice-table td {
                                display: block;
                            }

                            .invoice-table tr {
                                background: #ffffff;
                                border: 1px solid #e2e8f0;
                                border-radius: 14px;
                                padding: 14px 16px;
                                margin-bottom: 14px;
                                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
                            }

                            .invoice-table td {
                                border: none;
                                padding: 8px 0;
                                font-size: 15px;
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                gap: 12px;
                                color: #0f172a;
                                font-weight: 600;
                            }

                            .invoice-table td::before {
                                content: attr(data-label);
                                font-weight: 600;
                                color: #1e293b;
                                text-transform: uppercase;
                                font-size: 13px;
                                letter-spacing: 0.5px;
                            }

                            .invoice-table td strong {
                                font-size: 18px;
                                color: #0b1f33;
                            }

                            .invoice-table td:last-child {
                                display: block;
                                margin-top: 12px;
                            }

                            .invoice-table form {
                                width: 100%;
                            }

                            .invoice-table .btn {
                                width: 100%;
                                font-size: 17px;
                                padding: 11px 16px;
                                border-radius: 12px;
                                font-weight: 700;
                            }

                            .invoice-table .text-muted {
                                text-align: center;
                                display: block;
                            }

                            .payment-receipt-modal {
                                max-width: 100%;
                                padding: 22px 18px;
                                border-radius: 0;
                            }

                            .payment-receipt-summary dl {
                                grid-template-columns: 1fr;
                            }

                            .payment-receipt-summary dt {
                                font-size: 12px;
                                color: #1e293b;
                            }

                            .payment-receipt-summary dd {
                                font-size: 18px;
                                margin-bottom: 6px;
                            }

                            .payment-receipt-actions button {
                                flex: 1 1 100%;
                            }
                        }

                        @media (max-width: 480px) {
                            .invoice-table tr {
                                padding: 12px;
                            }

                            .invoice-table td {
                                flex-direction: column;
                                align-items: flex-start;
                                color: #0f172a;
                            }

                            .invoice-table td::before {
                                margin-bottom: 4px;
                            }

                            .invoice-table td strong,
                            .invoice-table td span,
                            .invoice-table td .badge {
                                width: 100%;
                                font-size: 17px;
                            }
                        }
                    </style>

                    <div class="row">
                        <div class="col-12">
                            <div class="card billing-customer-card">
                                <div class="card-header">
                                    <h4>Informasi Pelanggan</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                            <div class="col-6">
                                                <p><strong>Nama:</strong> <?= htmlspecialchars($selectedCustomer['name']); ?></p>
                                                <p><strong>Telepon:</strong> <?= htmlspecialchars($selectedCustomer['phone'] ?: '-'); ?></p>
                                            </div>
                                            <div class="col-6">
                                                <p><strong>Service Number:</strong> <?= htmlspecialchars($selectedCustomer['service_number'] ?: '-'); ?></p>
                                                <p><strong>PPPoE Username:</strong> <?= htmlspecialchars($selectedCustomer['genieacs_pppoe_username'] ?: '-'); ?></p>
                                                <p><strong>Paket:</strong> <?= htmlspecialchars($selectedCustomer['profile_name'] ?: '-'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Invoices -->
                        <div class="row">
                        <div class="col-12">
                            <div class="card billing-invoice-card">
                                <div class="card-header">
                                    <h4>Tagihan Belum Dibayar</h4>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($customerInvoices)): ?>
                                        <p class="text-center text-muted">Tidak ada tagihan belum dibayar.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                                <table class="table table-striped invoice-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Periode</th>
                                                            <th>Jumlah</th>
                                                            <th>Jatuh Tempo</th>
                                                            <th>Status</th>
                                                            <th>Total Bayar</th>
                                                            <th>Aksi</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($customerInvoices as $invoice): ?>
                                                        <?php
                                                                $baseAmount = (float)$invoice['amount'];
                                                                $discountAmount = min($agentCommissionAmount, $baseAmount);
                                                                $percentageFee = 0.0;
                                                                if ($discountAmount <= 0 && $feeEnabled === '1' && $feePercent > 0) {
                                                                    $percentageFee = ($baseAmount * $feePercent / 100);
                                                                }
                                                                $deductionPreview = max(0.0, $baseAmount + $percentageFee - $discountAmount);
                                                                $formattedDeduction = number_format($deductionPreview, 0, ',', '.');
                                                            ?>
                                                            <tr>
                                                                <td data-label="Periode"><?= htmlspecialchars($invoice['period']); ?></td>
                                                                <td data-label="Jumlah">Rp <?= number_format($invoice['amount'], 0, ',', '.'); ?></td>
                                                                <td data-label="Jatuh Tempo"><?= date('d M Y', strtotime($invoice['due_date'])); ?></td>
                                                                <td data-label="Status">
                                                                    <span class="badge badge-<?= $invoice['status'] === 'overdue' ? 'danger' : 'warning' ?>">
                                                                        <?= ucfirst($invoice['status']); ?>
                                                                    </span>
                                                                </td>
                                                                <td data-label="Total Bayar">
                                                                    <div class="billing-total-box">
                                                                        <div><strong>Tagihan:</strong> Rp <?= number_format($baseAmount, 0, ',', '.'); ?></div>
                                                                        <div class="total-deduct">Saldo Dipotong: Rp <span><?= number_format($deductionPreview, 0, ',', '.'); ?></span></div>
                                                                    </div>
                                                                </td>
                                                                <td data-label="Aksi">
                                                                    <?php
                                                                    $initialTotalCost = $deductionPreview;
                                                                    if ($agentData['balance'] >= $initialTotalCost): ?>
                                                                        <form method="post" style="display: inline;" class="pay-invoice-form" data-deduction="<?= htmlspecialchars($deductionPreview, ENT_QUOTES, 'UTF-8'); ?>">
                                                                            <input type="hidden" name="invoice_id" value="<?= $invoice['id']; ?>">
                                                                            <input type="hidden" name="customer_id" value="<?= $selectedCustomer['id']; ?>">
                                                                            <button type="submit" name="pay_invoice" class="btn btn-success btn-sm">
                                                                                <i class="fa fa-money"></i> Bayar (Potong Rp <?= number_format($deductionPreview, 0, ',', '.'); ?>)
                                                                            </button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">Saldo tidak cukup</span>
                                                                    <?php endif; ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="payment-receipt-overlay" id="paymentReceiptOverlay">
        <div class="payment-receipt-modal" id="paymentReceiptModal">
            <button type="button" class="receipt-close" id="receiptCloseBtn" aria-label="Tutup">&times;</button>
            <div class="receipt-header">
                <div class="receipt-status">Pembayaran Sukses</div>
                <div class="receipt-subtitle">Silakan cetak atau kirim struk kepada pelanggan.</div>
            </div>
            <div class="payment-receipt-summary" id="paymentReceiptContent"></div>
            <div class="payment-receipt-actions">
                <button type="button" id="printNormalBtn"><i class="fa fa-print"></i> Print A4</button>
                <button type="button" id="printThermalBtn"><i class="fa fa-print"></i> Print Thermal</button>
                <button type="button" id="receiptDoneBtn"><i class="fa fa-check"></i> Selesai</button>
            </div>
        </div>
    </div>

<script>
(function() {
    const receiptData = <?php echo $paymentReceipt ? json_encode($paymentReceipt, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
    const overlay = document.getElementById('paymentReceiptOverlay');
    if (!overlay) {
        return;
    }

    const modal = document.getElementById('paymentReceiptModal');
    const contentContainer = document.getElementById('paymentReceiptContent');
    const closeBtn = document.getElementById('receiptCloseBtn');
    const doneBtn = document.getElementById('receiptDoneBtn');
    const printNormalBtn = document.getElementById('printNormalBtn');
    const printThermalBtn = document.getElementById('printThermalBtn');
    let currentReceipt = null;

    function formatCurrency(value) {
        const number = Number(value || 0);
        return new Intl.NumberFormat('id-ID').format(number);
    }

    function safeText(value, fallback = '-') {
        if (value === null || value === undefined || value === '') {
            return fallback;
        }
        return value;
    }

    function renderReceipt(data) {
        const agentLine = `${safeText(data.agent_name)}${data.agent_code ? ' (' + data.agent_code + ')' : ''}`;
        const packageName = safeText(data.profile_name || '-');
        const rows = [
            { label: 'Nama Pelanggan', value: safeText(data.customer_name) },
            { label: 'Paket', value: packageName },
            { label: 'Periode', value: safeText(data.period) },
            { label: 'Jatuh Tempo', value: safeText(data.due_date) },
            { label: 'Status', value: safeText((data.invoice_status || '').toUpperCase()) },
            { label: 'Tagihan', value: `Rp ${formatCurrency(data.amount)}` },
            { label: 'Total Dibayar', value: `<strong>Rp ${formatCurrency(data.total)}</strong>` },
            { label: 'Service Number', value: safeText(data.service_number || '-') },
            { label: 'PPPoE Username', value: safeText(data.pppoe_username || '-') },
            { label: 'Dibayar Pada', value: safeText(data.paid_at) },
            { label: 'Petugas', value: agentLine }
        ];

        contentContainer.innerHTML = `
            <div class="left">
                <p><strong>Pelanggan</strong><br>
                ${safeText(data.customer_name)}<br>
                Telp: ${safeText(data.customer_phone || '-')}<br>
                Paket: ${packageName}
                </p>
            </div>
            <div class="right">
                <p><strong>Tagihan</strong><br>
                Periode: ${safeText(data.period)}<br>
                Jatuh Tempo: ${safeText(data.due_date)}<br>
                Status: ${safeText((data.invoice_status || '').toUpperCase())}<br>
                Service No: ${safeText(data.service_number || '-')}<br>
                PPPoE: ${safeText(data.pppoe_username || '-')}<br>
                </p>
            </div>
            <table class="tbl">
                <tr>
                    <th>Rincian</th>
                    <th class="text-right">Nominal</th>
                </tr>
                <tr>
                    <td>Tagihan</td>
                    <td class="text-right">Rp ${formatCurrency(data.amount)}</td>
                </tr>
                <tr class="total">
                    <td>Total Dibayar</td>
                    <td class="text-right">Rp ${formatCurrency(data.total)}</td>
                </tr>
            </table>
            <div class="info">
                <p><strong>Tanggal Bayar:</strong> ${safeText(data.paid_at)}</p>
                <p><strong>Petugas:</strong> ${agentLine}</p>
            </div>
        `;
    }

    function showReceipt(data) {
        currentReceipt = data;
        renderReceipt(data);
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function hideReceipt() {
        overlay.style.display = 'none';
        document.body.style.removeProperty('overflow');
    }

    function buildPrintHtml(data, options = {}) {
        const amount = Number(data.amount || 0);
        const total = Number(data.total || amount);
        const agentLine = `${safeText(data.agent_name)}${data.agent_code ? ' (' + data.agent_code + ')' : ''}`;
        const paidAt = safeText(data.paid_at);
        const dueDate = safeText(data.due_date);
        const invoiceStatus = safeText((data.invoice_status || 'PAID').toUpperCase());
        const packageName = safeText(data.profile_name || '-');
        const serviceNumber = safeText(data.service_number || '-');
        const pppoe = safeText(data.pppoe_username || '-');
        const customerPhone = safeText(data.customer_phone || '-');

        if (options.thermal) {
            return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk Pembayaran Invoice ${safeText(data.invoice_id)}</title>
    <style>
        @page { size: 58mm auto; margin: 3mm; }
        body { font-family: 'Courier New', monospace; width: 58mm; margin: 0; padding: 0; font-size: 11px; }
        .wrapper { padding: 6px; text-align: center; }
        .title { font-size: 14px; font-weight: bold; margin-bottom: 6px; }
        .sub { font-size: 10px; margin-bottom: 8px; }
        .separator { border-top: 1px dashed #000; margin: 6px 0; }
        .section { text-align: left; }
        .row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .label { font-weight: bold; text-align: left; }
        .value { text-align: right; word-break: break-word; max-width: 34mm; }
        .status { font-weight: bold; margin: 6px 0; text-transform: uppercase; }
        .footer { margin-top: 10px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="title">STRUK PEMBAYARAN</div>
        <div class="sub">${agentLine}</div>
        <div class="separator"></div>
        <div class="section">
            <div class="row"><span class="label">Tanggal</span><span class="value">${paidAt}</span></div>
            <div class="row"><span class="label">Invoice</span><span class="value">#${safeText(data.invoice_id)}</span></div>
            <div class="row"><span class="label">Periode</span><span class="value">${safeText(data.period)}</span></div>
            <div class="row"><span class="label">Jatuh Tempo</span><span class="value">${dueDate}</span></div>
            <div class="row"><span class="label">Pelanggan</span><span class="value">${safeText(data.customer_name)}</span></div>
            <div class="row"><span class="label">Nominal</span><span class="value">Rp ${formatCurrency(amount)}</span></div>
            <div class="row"><span class="label">Total</span><span class="value">Rp ${formatCurrency(total)}</span></div>
        </div>
        <div class="separator"></div>
        <div class="section">
            <p><strong>Tanggal Bayar:</strong> ${paidAt}</p>
            <p><strong>Petugas:</strong> ${agentLine}</p>
        </div>
        <div class="separator"></div>
        <div class="section">
            <p><strong>Nama:</strong> ${safeText(data.customer_name)}</p>
            <p><strong>Telepon:</strong> ${customerPhone}</p>
            <p><strong>Paket:</strong> ${packageName}</p>
            <p><strong>Periode:</strong> ${safeText(data.period)}</p>
            <p><strong>Jatuh Tempo:</strong> ${dueDate}</p>
            <p><strong>Status:</strong> ${invoiceStatus}</p>
            <p><strong>Service No:</strong> ${serviceNumber}</p>
            <p><strong>PPPoE:</strong> ${pppoe}</p>
        </div>
        <div class="separator"></div>
        <div class="section">
            <p><strong>Tagihan:</strong> Rp ${formatCurrency(amount)}</p>
            <p><strong>Total Dibayar:</strong> Rp ${formatCurrency(total)}</p>
        </div>
        <div class="separator"></div>
        <div class="status">LUNAS</div>
        <div class="separator"></div>
        <div class="footer">Terima kasih telah membayar tepat waktu.</div>
    </div>
</body>
</html>`;
        }

        const feeRowHtml = '';

        return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Struk Pembayaran Invoice ${safeText(data.invoice_id)}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; padding: 28px; color: #0f172a; }
        h1 { margin: 0 0 14px; font-size: 24px; }
        .meta { margin-bottom: 20px; }
        .meta div { margin-bottom: 6px; font-size: 14px; }
        .meta span.label { font-weight: 600; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td { border: 1px solid #e2e8f0; padding: 10px 12px; font-size: 14px; }
        .footer { margin-top: 30px; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Struk Pembayaran</h1>
    <div class="meta">
        <div><span class="label">Invoice:</span> #${safeText(data.invoice_id)}</div>
        <div><span class="label">Periode:</span> ${safeText(data.period)}</div>
        <div><span class="label">Tanggal Pembayaran:</span> ${paidAt}</div>
        <div><span class="label">Agen:</span> ${agentLine}</div>
        <div class="section">
            <p><strong>Nama:</strong> ${safeText(data.customer_name)}</p>
            <p><strong>Telepon:</strong> ${customerPhone}</p>
            <p><strong>Paket:</strong> ${packageName}</p>
            <p><strong>Periode:</strong> ${safeText(data.period)}</p>
            <p><strong>Jatuh Tempo:</strong> ${dueDate}</p>
            <p><strong>Status:</strong> ${invoiceStatus}</p>
            <p><strong>Service No:</strong> ${serviceNumber}</p>
            <p><strong>PPPoE:</strong> ${pppoe}</p>
        </div>
        <div class="separator"></div>
        <div class="section">
            <p><strong>Tagihan:</strong> Rp ${formatCurrency(amount)}</p>
            <p><strong>Total Dibayar:</strong> Rp ${formatCurrency(total)}</p>
        </div>
        <div class="separator"></div>
        <div class="section">
            <p><strong>Tanggal Bayar:</strong> ${paidAt}</p>
            <p><strong>Petugas:</strong> ${agentLine}</p>
        </div>
    </div>
    <table>
        <tr><td>Pelanggan</td><td>${safeText(data.customer_name)}</td></tr>
        <tr><td>Nomor Layanan</td><td>${serviceNumber}</td></tr>
        <tr><td>PPPoE Username</td><td>${pppoe}</td></tr>
        <tr><td>Jatuh Tempo</td><td>${dueDate}</td></tr>
        <tr><td>Nominal</td><td>Rp ${formatCurrency(amount)}</td></tr>
        <tr><td><strong>Total Dibayar</strong></td><td><strong>Rp ${formatCurrency(total)}</strong></td></tr>
    </table>
    <div class="footer">Struk ini dicetak otomatis dari sistem MikhMon Agent.</div>
</body>
</html>`;
    }

    function openPrint(options) {
        if (!currentReceipt) {
            return;
        }
        const html = buildPrintHtml(currentReceipt, options);
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            alert('Popup diblokir oleh browser. Mohon izinkan popup untuk mencetak.');
            return;
        }
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
        }, 250);
    }

    function attachListeners() {
        if (closeBtn) {
            closeBtn.addEventListener('click', hideReceipt);
        }
        if (doneBtn) {
            doneBtn.addEventListener('click', hideReceipt);
        }
        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) {
                hideReceipt();
            }
        });
        if (printNormalBtn) {
            printNormalBtn.addEventListener('click', function() {
                openPrint({ thermal: false });
            });
        }
        if (printThermalBtn) {
            printThermalBtn.addEventListener('click', function() {
                openPrint({ thermal: true });
            });
        }
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && overlay.style.display === 'flex') {
                hideReceipt();
            }
        });
    }

    attachListeners();

    if (receiptData) {
        showReceipt(receiptData);
    }
})();
</script>

<?php include_once('include_foot.php'); ?>
