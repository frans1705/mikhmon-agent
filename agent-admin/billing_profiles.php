<?php
/*
 * Billing Profiles Management - Skeleton UI
 */

include_once('./include/db_config.php');
include_once('./include/config.php');
include_once('./lib/routeros_api.class.php');

try {
    $db = getDBConnection();
} catch (Exception $e) {
    echo '<div class="alert bg-danger">Gagal terhubung ke database: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

$profilesStmt = $db->query("SELECT * FROM billing_profiles ORDER BY created_at DESC");
$profiles = $profilesStmt->fetchAll(PDO::FETCH_ASSOC);
$totalProfiles = count($profiles);

// Ambil daftar MikroTik PPP profile (normal & isolasi)
$mikrotikProfiles = [];
$mikrotikIsolationProfiles = [];

$sessions = isset($data) ? array_keys($data) : [];
$sessionName = null;
foreach ($sessions as $s) {
    if ($s !== 'mikhmon') {
        $sessionName = $s;
        break;
    }
}

if ($sessionName) {
    $iphost = explode('!', $data[$sessionName][1])[1] ?? null;
    $userhost = explode('@|@', $data[$sessionName][2])[1] ?? null;
    $passwdhost = explode('#|#', $data[$sessionName][3])[1] ?? null;

    if ($iphost && $userhost && $passwdhost) {
        $API = new RouterosAPI();
        $API->debug = false;
        if ($API->connect($iphost, $userhost, decrypt($passwdhost))) {
            try {
                $rawPppProfiles = $API->comm('/ppp/profile/print');
                if (is_array($rawPppProfiles)) {
                    foreach ($rawPppProfiles as $p) {
                        if (!empty($p['name']) && $p['name'] !== 'default' && $p['name'] !== 'default-encryption') {
                            $mikrotikProfiles[] = $p['name'];
                        }
                    }
                }

                // Dropdown isolasi menggunakan daftar PPP profile yang sama.
                $mikrotikIsolationProfiles = $mikrotikProfiles;
            } catch (Throwable $e) {
                // abaikan error, keep arrays kosong
            }
            $API->disconnect();
        }
    }
}
?>

<style>
.billing-module {
    font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
    font-size: 15px;
    line-height: 1.6;
    color: #1f2937;
}

.billing-module .card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
}

.billing-module .card-header h3 {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 0;
}

.summary-row {
    margin-bottom: 20px;
}

.summary-row .box {
    min-height: 90px;
}

.summary-row .box h1 {
    margin: 0;
    font-size: 32px;
}

.summary-row .box h1 span {
    display: block;
    font-size: 15px;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.profile-table .action-cell {
    min-width: 110px;
}

.profile-table .btn-group {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
}

@media (max-width: 768px) {
    .summary-row .box h1 {
        font-size: 24px;
    }

    .profile-table th,
    .profile-table td {
        font-size: 13px;
        white-space: nowrap;
    }
}

.profile-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    font-size: 14px;
}

.profile-table th,
.profile-table td {
    padding: 10px;
    border: 1px solid #e5e7eb;
    text-align: left;
}

.profile-table th {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #fff;
    font-weight: 600;
    letter-spacing: 0.2px;
}

.profile-table td {
    color: #0f172a;
}

.profile-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

.profile-table td strong {
    color: #0b1f4b;
}

@media (max-width: 768px) {
    .profile-table {
        font-size: 13px;
    }

    .profile-table th,
    .profile-table td {
        padding: 8px;
    }
}

.profile-form-placeholder {
    background: #f0f9ff;
    border-left: 4px solid #0ea5e9;
    padding: 16px;
    border-radius: 8px;
    margin-top: 20px;
}

/* Modal Styling */
#profileEditModal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: none;
}

#profileEditModal > div {
    background: white;
    width: 80%;
    max-width: 600px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.btn-group .btn {
    padding: 5px 10px;
    font-size: 12px;
}
</style>

<div class="billing-module">
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa fa-sliders"></i> Profil Paket Billing</h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-3 col-box-6">
                        <div class="box bg-blue bmh-75">
                            <h1><?= number_format($totalProfiles, 0); ?>
                                <span style="font-size: 15px;">profiles</span>
                            </h1>
                            <div><i class="fa fa-list"></i> Total Profil</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-green bmh-75">
                            <h1><?= number_format(array_sum(array_map(static function ($profile) {
                                return (float)($profile['price_monthly'] ?? 0);
                            }, $profiles)), 0, ',', '.'); ?></h1>
                            <div><i class="fa fa-money"></i> Akumulasi Harga</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-yellow bmh-75">
                            <h1><?= number_format(count(array_filter($profiles, static function ($profile) {
                                return !empty($profile['mikrotik_profile_isolation']) && strcasecmp($profile['mikrotik_profile_isolation'], 'ISOLIR') === 0;
                            })), 0); ?>
                                <span style="font-size: 15px;">isolir</span>
                            </h1>
                            <div><i class="fa fa-exclamation-triangle"></i> Gunakan Isolasi</div>
                        </div>
                    </div>
                    <div class="col-3 col-box-6">
                        <div class="box bg-aqua bmh-75">
                            <h1><?= number_format(count(array_filter($profiles, static function ($profile) {
                                return (float)($profile['price_monthly'] ?? 0) >= 100000;
                            })), 0); ?>
                                <span style="font-size: 15px;">premium</span>
                            </h1>
                            <div><i class="fa fa-star"></i> Harga ≥ 100K</div>
                        </div>
                    </div>
                </div>

                <div class="alert bg-light" style="margin-bottom: 20px;">
                    <strong>Catatan:</strong> Pengisian data profil akan terhubung dengan MikroTik. Pastikan profil normal dan profil isolasi tersedia di halaman PPP > Profiles.
                </div>

                <?php if (empty($profiles)): ?>
                    <div class="alert bg-warning">
                        <i class="fa fa-info-circle"></i> Belum ada profil billing yang tersimpan. Gunakan formulir di bawah untuk menyiapkan paket langganan.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="profile-table">
                            <thead>
                                <tr>
                                    <th style="width: 110px;">Aksi</th>
                                    <th>Nama Profil</th>
                                    <th>Harga Bulanan</th>
                                    <th>Profil MikroTik</th>
                                    <th>Profil Isolasi</th>
                                    <th>Kecepatan</th>
                                    <th>Dibuat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($profiles as $profile): ?>
                                    <tr>
                                        <td class="action-cell">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" data-profile-id="<?= $profile['id']; ?>" onclick="editProfile(<?= $profile['id']; ?>)">
                                                    <i class="fa fa-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" data-profile-id="<?= $profile['id']; ?>" onclick="deleteProfile(<?= $profile['id']; ?>)">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($profile['profile_name']); ?></strong></td>
                                        <td>Rp <?= number_format($profile['price_monthly'], 0, ',', '.'); ?></td>
                                        <td><?= htmlspecialchars($profile['mikrotik_profile_normal']); ?></td>
                                        <td><?= htmlspecialchars($profile['mikrotik_profile_isolation']); ?></td>
                                        <td><?= htmlspecialchars($profile['speed_label'] ?? '-'); ?></td>
                                        <td><?= date('d M Y H:i', strtotime($profile['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="profile-form-placeholder">
                    <h4 style="margin-top:0;"><i class="fa fa-plus-circle"></i> Tambah Profil Billing</h4>
                    <p style="margin-bottom: 10px;">Gunakan formulir berikut untuk menambahkan paket langganan baru. Daftar profil MikroTik otomatis diambil dari router aktif.</p>
                    <form data-api-form data-api-endpoint="./billing/profiles.php" data-success-reload="true">
                        <input type="hidden" name="action" value="create">
                        <div class="row">
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="profile_name">Nama Profil</label>
                                    <input type="text" id="profile_name" name="profile_name" class="form-control" placeholder="Contoh: Paket Rumah 30 Mbps" required>
                                </div>
                            </div>
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="price_monthly">Harga Bulanan (Rp)</label>
                                    <input type="number" id="price_monthly" name="price_monthly" class="form-control" min="0" step="1000" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="mikrotik_profile_normal">Profil MikroTik Normal</label>
                                    <select id="mikrotik_profile_normal" name="mikrotik_profile_normal" class="form-control" required>
                                        <option value="">-- pilih profil PPP normal --</option>
<?php foreach ($mikrotikProfiles as $pppProfile): ?>
                                        <option value="<?= htmlspecialchars($pppProfile); ?>"><?= htmlspecialchars($pppProfile); ?></option>
<?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="mikrotik_profile_isolation">Profil MikroTik Isolasi</label>
                                    <select id="mikrotik_profile_isolation" name="mikrotik_profile_isolation" class="form-control" required>
                                        <option value="">-- pilih profil isolasi --</option>
<?php foreach ($mikrotikIsolationProfiles as $isolationProfile): ?>
                                        <option value="<?= htmlspecialchars($isolationProfile); ?>"><?= htmlspecialchars($isolationProfile); ?></option>
<?php endforeach; ?>
<?php if (empty($mikrotikIsolationProfiles)): ?>
                                        <option value="ISOLIR">ISOLIR (fallback)</option>
<?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="speed_label">Label Kecepatan</label>
                                    <input type="text" id="speed_label" name="speed_label" class="form-control" placeholder="Contoh: 30 Mbps">
                                </div>
                            </div>
                            <div class="col-6 col-box-12">
                                <div class="form-group">
                                    <label for="description">Catatan/Deskripsi</label>
                                    <input type="text" id="description" name="description" class="form-control" placeholder="Opsional">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Simpan Profil
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div id="profileEditModal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Profil Billing</h5>
                <button type="button" class="close" onclick="document.getElementById('profileEditModal').style.display = 'none';">&times;</button>
            </div>
            <div class="modal-body">
                <form data-api-form data-api-endpoint="./billing/profiles.php" data-success-reload="true">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="row">
                        <div class="col-6 col-box-12">
                            <div class="form-group">
                                <label for="edit_profile_name">Nama Profil</label>
                                <input type="text" id="edit_profile_name" name="profile_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-6 col-box-12">
                            <div class="form-group">
                                <label for="edit_price_monthly">Harga Bulanan (Rp)</label>
                                <input type="number" id="edit_price_monthly" name="price_monthly" class="form-control" min="0" step="1000" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 col-box-12">
                            <div class="form-group">
                                <label for="edit_mikrotik_profile_normal">Profil MikroTik Normal</label>
                                <select id="edit_mikrotik_profile_normal" name="mikrotik_profile_normal" class="form-control" required>
                                    <option value="">-- pilih profil PPP normal --</option>
<?php foreach ($mikrotikProfiles as $pppProfile): ?>
                                    <option value="<?= htmlspecialchars($pppProfile); ?>"><?= htmlspecialchars($pppProfile); ?></option>
<?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 col-box-12">
                            <div class="form-group">
                                <label for="edit_mikrotik_profile_isolation">Profil MikroTik Isolasi</label>
                                <select id="edit_mikrotik_profile_isolation" name="mikrotik_profile_isolation" class="form-control" required>
                                    <option value="">-- pilih profil isolasi --</option>
<?php foreach ($mikrotikIsolationProfiles as $isolationProfile): ?>
                                    <option value="<?= htmlspecialchars($isolationProfile); ?>"><?= htmlspecialchars($isolationProfile); ?></option>
<?php endforeach; ?>
<?php if (empty($mikrotikIsolationProfiles)): ?>
                                    <option value="ISOLIR">ISOLIR (fallback)</option>
<?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 col-box-12">
                            <div class="form-group">
                                <label for="edit_speed_label">Label Kecepatan</label>
                                <input type="text" id="edit_speed_label" name="speed_label" class="form-control" placeholder="Contoh: 30 Mbps">
                            </div>
                        </div>
                        <div class="col-6 col-box-12">
                            <div class="form-group">
                                <label for="edit_description">Catatan/Deskripsi</label>
                                <input type="text" id="edit_description" name="description" class="form-control" placeholder="Opsional">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="./js/billing_forms.js"></script>
<script>
// Fungsi untuk edit profile
function editProfile(id) {
    // Implementasi AJAX untuk mengambil data profile
    fetch(`./billing/profiles.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Isi form edit
                document.querySelector('#edit_id').value = data.data.id;
                document.querySelector('#edit_profile_name').value = data.data.profile_name;
                document.querySelector('#edit_price_monthly').value = data.data.price_monthly;
                document.querySelector('#edit_mikrotik_profile_normal').value = data.data.mikrotik_profile_normal;
                document.querySelector('#edit_mikrotik_profile_isolation').value = data.data.mikrotik_profile_isolation;
                document.querySelector('#edit_speed_label').value = data.data.speed_label || '';
                document.querySelector('#edit_description').value = data.data.description || '';
                
                // Tampilkan modal edit
                document.getElementById('profileEditModal').style.display = 'block';
            }
        });
}

// Fungsi untuk hapus profile
function deleteProfile(id) {
    if (confirm('Apakah Anda yakin ingin menghapus profil ini?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        fetch('./billing/profiles.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}
</script>
</div>
