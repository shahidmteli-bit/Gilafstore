<?php
/**
 * Banking Details Management
 * Manage multiple bank accounts with enable/disable, extra security
 * Bank details available site-wide via get_active_bank_accounts() helper
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pageTitle = 'Banking Details';
$adminPage = 'banking_details';
$db = get_db_connection();

// ─── Auto-create table ───
try {
    $db->exec("CREATE TABLE IF NOT EXISTS bank_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_label VARCHAR(100) NOT NULL,
        bank_name VARCHAR(200) NOT NULL,
        account_holder VARCHAR(200) NOT NULL,
        account_number_enc TEXT NOT NULL,
        ifsc_code VARCHAR(20),
        branch_name VARCHAR(200),
        account_type ENUM('savings','current','overdraft') DEFAULT 'current',
        upi_id VARCHAR(100),
        swift_code VARCHAR(20),
        iban VARCHAR(50),
        is_primary TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        notes TEXT,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { /* exists */ }

// ─── Encryption helpers (same AES-256-CBC as shipping partners) ───
function ba_get_key() {
    $keyFile = dirname(__DIR__) . '/.gilaf_security_key';
    if (file_exists($keyFile)) return trim(file_get_contents($keyFile));
    return hash('sha256', 'gilaf_banking_' . DB_NAME . '_secret_2026', true);
}
function ba_encrypt($plain) {
    if (empty($plain)) return '';
    $key = ba_get_key();
    $iv = openssl_random_pseudo_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . '::' . $cipher);
}
function ba_decrypt($enc) {
    if (empty($enc)) return '';
    $key = ba_get_key();
    $data = base64_decode($enc);
    $parts = explode('::', $data, 2);
    if (count($parts) !== 2) return '';
    return openssl_decrypt($parts[1], 'AES-256-CBC', $key, 0, $parts[0]);
}
function ba_mask_account($num) {
    if (empty($num)) return '';
    $len = strlen($num);
    if ($len <= 4) return str_repeat('•', $len);
    return str_repeat('•', $len - 4) . substr($num, -4);
}

// ─── Handle POST ───
$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_account') {
        $id = (int)($_POST['account_id'] ?? 0);
        $label = trim($_POST['account_label'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $holder = trim($_POST['account_holder'] ?? '');
        $accNum = trim($_POST['account_number'] ?? '');
        $ifsc = strtoupper(trim($_POST['ifsc_code'] ?? ''));
        $branch = trim($_POST['branch_name'] ?? '');
        $accType = $_POST['account_type'] ?? 'current';
        $upiId = trim($_POST['upi_id'] ?? '');
        $swift = strtoupper(trim($_POST['swift_code'] ?? ''));
        $iban = strtoupper(trim($_POST['iban'] ?? ''));
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');

        if (empty($label) || empty($bankName) || empty($holder)) {
            $flash = 'Label, Bank Name, and Account Holder are required.';
            $flashType = 'danger';
        } else {
            // Encrypt account number
            if ($id > 0) {
                // Editing: if masked value, keep existing
                $existing = $db->prepare("SELECT account_number_enc FROM bank_accounts WHERE id = ?");
                $existing->execute([$id]);
                $row = $existing->fetch(PDO::FETCH_ASSOC);
                $accNumEnc = (!empty($accNum) && strpos($accNum, '•') === false) ? ba_encrypt($accNum) : ($row['account_number_enc'] ?? ba_encrypt($accNum));

                // If setting as primary, unset all others
                if ($isPrimary) {
                    $db->exec("UPDATE bank_accounts SET is_primary = 0");
                }

                $stmt = $db->prepare("UPDATE bank_accounts SET account_label=?, bank_name=?, account_holder=?, account_number_enc=?, ifsc_code=?, branch_name=?, account_type=?, upi_id=?, swift_code=?, iban=?, is_primary=?, is_active=?, notes=? WHERE id=?");
                $stmt->execute([$label, $bankName, $holder, $accNumEnc, $ifsc, $branch, $accType, $upiId, $swift, $iban, $isPrimary, $isActive, $notes, $id]);
                $flash = 'Bank account updated successfully.';
            } else {
                $accNumEnc = ba_encrypt($accNum);
                if ($isPrimary) {
                    $db->exec("UPDATE bank_accounts SET is_primary = 0");
                }
                $maxOrder = (int)$db->query("SELECT COALESCE(MAX(display_order),0) FROM bank_accounts")->fetchColumn();
                $stmt = $db->prepare("INSERT INTO bank_accounts (account_label, bank_name, account_holder, account_number_enc, ifsc_code, branch_name, account_type, upi_id, swift_code, iban, is_primary, is_active, notes, display_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$label, $bankName, $holder, $accNumEnc, $ifsc, $branch, $accType, $upiId, $swift, $iban, $isPrimary, $isActive, $notes, $maxOrder + 1]);
                $flash = 'Bank account added successfully.';
            }
        }
    } elseif ($action === 'toggle_account') {
        $id = (int)($_POST['account_id'] ?? 0);
        $db->prepare("UPDATE bank_accounts SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $flash = 'Account status updated.';
    } elseif ($action === 'set_primary') {
        $id = (int)($_POST['account_id'] ?? 0);
        $db->exec("UPDATE bank_accounts SET is_primary = 0");
        $db->prepare("UPDATE bank_accounts SET is_primary = 1, is_active = 1 WHERE id = ?")->execute([$id]);
        $flash = 'Primary account set.';
    } elseif ($action === 'delete_account') {
        $id = (int)($_POST['account_id'] ?? 0);
        $db->prepare("DELETE FROM bank_accounts WHERE id = ?")->execute([$id]);
        $flash = 'Bank account deleted.';
        $flashType = 'success';
    }
}

// ─── Fetch all accounts ───
$accounts = [];
try {
    $accounts = $db->query("SELECT * FROM bank_accounts ORDER BY is_primary DESC, display_order ASC, created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist yet */ }

$activeCount = 0;
foreach ($accounts as $a) { if ($a['is_active']) $activeCount++; }

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* ═══ Banking Details Styles ═══ */
.ba-page { max-width: 1200px; margin: 0 auto; padding: 0; }
.ba-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.ba-header h1 { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }
.ba-header h1 i { color: #7c3aed; margin-right: 8px; }
.ba-header-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.ba-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.ba-badge-purple { background: #f5f3ff; color: #6d28d9; }
.ba-badge-green { background: #f0fdf4; color: #166534; }

/* Stats row */
.ba-stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.ba-stat-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; flex: 1; min-width: 140px; }
.ba-stat-val { font-size: 24px; font-weight: 800; color: #1e293b; }
.ba-stat-label { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

/* Account cards */
.ba-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; margin-bottom: 24px; }
.ba-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 0; overflow: hidden; transition: box-shadow .2s; position: relative; }
.ba-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.06); }
.ba-card.primary { border-color: #7c3aed; }
.ba-card.inactive { opacity: .6; }
.ba-card-top { padding: 20px 20px 0; display: flex; align-items: flex-start; justify-content: space-between; }
.ba-card-bank { display: flex; align-items: center; gap: 14px; }
.ba-bank-icon { width: 52px; height: 52px; border-radius: 12px; background: linear-gradient(135deg, #6d28d9, #7c3aed); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; flex-shrink: 0; }
.ba-bank-name { font-size: 17px; font-weight: 700; color: #1e293b; }
.ba-bank-label { font-size: 12px; color: #7c3aed; font-weight: 600; }
.ba-card-badges { display: flex; gap: 6px; flex-wrap: wrap; }
.ba-pill { padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
.ba-pill-primary { background: #f5f3ff; color: #7c3aed; }
.ba-pill-active { background: #f0fdf4; color: #166534; }
.ba-pill-inactive { background: #fef2f2; color: #dc2626; }
.ba-pill-type { background: #f0f9ff; color: #0369a1; }

.ba-card-body { padding: 16px 20px; }
.ba-card-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; font-size: 13px; }
.ba-card-field { }
.ba-card-field-label { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; font-weight: 600; }
.ba-card-field-value { color: #1e293b; font-weight: 500; margin-top: 1px; word-break: break-all; }
.ba-card-field-value.mono { font-family: 'Courier New', monospace; letter-spacing: 1px; }

.ba-card-footer { padding: 12px 20px; background: #f8fafc; border-top: 1px solid #f1f5f9; display: flex; gap: 8px; flex-wrap: wrap; }

/* Buttons */
.ba-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: all .2s; text-decoration: none; }
.ba-btn-primary { background: #7c3aed; color: #fff; }
.ba-btn-primary:hover { background: #6d28d9; color: #fff; }
.ba-btn-green { background: #01875f; color: #fff; }
.ba-btn-green:hover { background: #016d4d; color: #fff; }
.ba-btn-outline { background: #fff; color: #475569; border: 1px solid #e2e8f0; }
.ba-btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
.ba-btn-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.ba-btn-danger:hover { background: #fee2e2; }
.ba-btn-sm { padding: 5px 12px; font-size: 12px; }

/* Add new card */
.ba-add-card { background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 14px; display: flex; align-items: center; justify-content: center; min-height: 220px; cursor: pointer; transition: all .2s; }
.ba-add-card:hover { border-color: #7c3aed; background: #faf5ff; }
.ba-add-card-inner { text-align: center; color: #64748b; }
.ba-add-card-inner i { font-size: 32px; margin-bottom: 8px; color: #94a3b8; }
.ba-add-card:hover .ba-add-card-inner i { color: #7c3aed; }
.ba-add-card-inner p { font-size: 14px; font-weight: 600; margin: 0; }

/* Modal */
.ba-modal-backdrop { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.4); align-items: center; justify-content: center; padding: 20px; }
.ba-modal-backdrop.show { display: flex; }
.ba-modal { background: #fff; border-radius: 16px; max-width: 620px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.15); }
.ba-modal-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: #fff; z-index: 1; border-radius: 16px 16px 0 0; }
.ba-modal-header h3 { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0; }
.ba-modal-close { background: none; border: none; font-size: 24px; color: #94a3b8; cursor: pointer; }
.ba-modal-body { padding: 24px; }
.ba-modal-footer { padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 10px; }

.ba-form-group { margin-bottom: 16px; }
.ba-form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.ba-form-group label .req { color: #dc2626; }
.ba-form-input, .ba-form-select { width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #1e293b; transition: border-color .2s; background: #fff; box-sizing: border-box; }
.ba-form-input:focus, .ba-form-select:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.1); }
.ba-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ba-form-check { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.ba-form-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: #7c3aed; }
.ba-form-check label { font-size: 13px; color: #475569; margin: 0; }
.ba-form-hint { font-size: 11px; color: #94a3b8; margin-top: 4px; }

.ba-security-notice { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #5b21b6; display: flex; align-items: flex-start; gap: 8px; margin-bottom: 20px; }
.ba-security-notice i { margin-top: 2px; color: #7c3aed; }

/* Flash */
.ba-flash { padding: 12px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.ba-flash.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.ba-flash.danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* Empty */
.ba-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
.ba-empty i { font-size: 48px; margin-bottom: 16px; opacity: .5; }
.ba-empty h3 { font-size: 18px; color: #64748b; margin-bottom: 4px; }

/* Responsive */
@media (max-width: 768px) {
    .ba-grid { grid-template-columns: 1fr; }
    .ba-form-row { grid-template-columns: 1fr; }
    .ba-card-grid { grid-template-columns: 1fr; }
    .ba-header { flex-direction: column; align-items: flex-start; }
    .ba-stats { flex-direction: column; }
}
</style>

<div class="ba-page">

    <div class="ba-header">
        <h1><i class="fas fa-university"></i> Banking Details</h1>
        <div class="ba-header-right">
            <span class="ba-badge ba-badge-purple"><i class="fas fa-lock"></i> Encrypted</span>
            <span class="ba-badge ba-badge-green"><i class="fas fa-check-circle"></i> <?= $activeCount; ?> Active</span>
            <button class="ba-btn ba-btn-primary" onclick="baOpenModal(0)"><i class="fas fa-plus"></i> Add Account</button>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="ba-flash <?= $flashType; ?>"><?php if ($flashType === 'success'): ?><i class="fas fa-check-circle"></i><?php else: ?><i class="fas fa-exclamation-circle"></i><?php endif; ?> <?= $flash; ?></div>
    <?php endif; ?>

    <div class="ba-security-notice">
        <i class="fas fa-shield-alt"></i>
        <div>
            <strong>Security:</strong> Account numbers are encrypted with AES-256-CBC. Only the last 4 digits are visible. Bank details stored here are accessible site-wide for invoices, orders, and app displays.
        </div>
    </div>

    <!-- Stats -->
    <div class="ba-stats">
        <div class="ba-stat-card">
            <div class="ba-stat-val"><?= count($accounts); ?></div>
            <div class="ba-stat-label">Total Accounts</div>
        </div>
        <div class="ba-stat-card">
            <div class="ba-stat-val"><?= $activeCount; ?></div>
            <div class="ba-stat-label">Active Accounts</div>
        </div>
        <div class="ba-stat-card">
            <div class="ba-stat-val"><?php $p = array_filter($accounts, fn($a) => $a['is_primary']); echo $p ? htmlspecialchars(reset($p)['bank_name']) : '—'; ?></div>
            <div class="ba-stat-label">Primary Bank</div>
        </div>
    </div>

    <!-- Account Cards -->
    <div class="ba-grid">
        <?php foreach ($accounts as $acc):
            $accNumDecrypted = ba_decrypt($acc['account_number_enc']);
            $accMasked = ba_mask_account($accNumDecrypted);
        ?>
        <div class="ba-card <?= $acc['is_primary'] ? 'primary' : ''; ?> <?= !$acc['is_active'] ? 'inactive' : ''; ?>">
            <div class="ba-card-top">
                <div class="ba-card-bank">
                    <div class="ba-bank-icon"><?= strtoupper(substr($acc['bank_name'], 0, 1)); ?></div>
                    <div>
                        <div class="ba-bank-name"><?= htmlspecialchars($acc['bank_name']); ?></div>
                        <div class="ba-bank-label"><?= htmlspecialchars($acc['account_label']); ?></div>
                    </div>
                </div>
                <div class="ba-card-badges">
                    <?php if ($acc['is_primary']): ?><span class="ba-pill ba-pill-primary">Primary</span><?php endif; ?>
                    <span class="ba-pill <?= $acc['is_active'] ? 'ba-pill-active' : 'ba-pill-inactive'; ?>"><?= $acc['is_active'] ? 'Active' : 'Disabled'; ?></span>
                    <span class="ba-pill ba-pill-type"><?= ucfirst($acc['account_type']); ?></span>
                </div>
            </div>
            <div class="ba-card-body">
                <div class="ba-card-grid">
                    <div class="ba-card-field">
                        <div class="ba-card-field-label">Account Holder</div>
                        <div class="ba-card-field-value"><?= htmlspecialchars($acc['account_holder']); ?></div>
                    </div>
                    <div class="ba-card-field">
                        <div class="ba-card-field-label">Account Number</div>
                        <div class="ba-card-field-value mono"><?= $accMasked; ?></div>
                    </div>
                    <?php if ($acc['ifsc_code']): ?>
                    <div class="ba-card-field">
                        <div class="ba-card-field-label">IFSC Code</div>
                        <div class="ba-card-field-value mono"><?= htmlspecialchars($acc['ifsc_code']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($acc['branch_name']): ?>
                    <div class="ba-card-field">
                        <div class="ba-card-field-label">Branch</div>
                        <div class="ba-card-field-value"><?= htmlspecialchars($acc['branch_name']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($acc['upi_id']): ?>
                    <div class="ba-card-field">
                        <div class="ba-card-field-label">UPI ID</div>
                        <div class="ba-card-field-value"><?= htmlspecialchars($acc['upi_id']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($acc['swift_code']): ?>
                    <div class="ba-card-field">
                        <div class="ba-card-field-label">SWIFT</div>
                        <div class="ba-card-field-value mono"><?= htmlspecialchars($acc['swift_code']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($acc['iban']): ?>
                    <div class="ba-card-field">
                        <div class="ba-card-field-label">IBAN</div>
                        <div class="ba-card-field-value mono"><?= htmlspecialchars($acc['iban']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($acc['notes']): ?>
                <div style="margin-top:10px;font-size:12px;color:#64748b;background:#f8fafc;padding:8px 12px;border-radius:6px;">
                    <i class="fas fa-sticky-note" style="margin-right:4px;"></i> <?= htmlspecialchars($acc['notes']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="ba-card-footer">
                <button class="ba-btn ba-btn-outline ba-btn-sm" onclick="baOpenModal(<?= $acc['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                <form method="post" style="display:inline;"><?= csrf_field(); ?><input type="hidden" name="action" value="toggle_account"><input type="hidden" name="account_id" value="<?= $acc['id']; ?>">
                    <button type="submit" class="ba-btn ba-btn-outline ba-btn-sm"><i class="fas fa-power-off"></i> <?= $acc['is_active'] ? 'Disable' : 'Enable'; ?></button>
                </form>
                <?php if (!$acc['is_primary']): ?>
                <form method="post" style="display:inline;"><?= csrf_field(); ?><input type="hidden" name="action" value="set_primary"><input type="hidden" name="account_id" value="<?= $acc['id']; ?>">
                    <button type="submit" class="ba-btn ba-btn-outline ba-btn-sm" title="Set as primary"><i class="fas fa-star"></i> Primary</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this bank account? This cannot be undone.')"><?= csrf_field(); ?><input type="hidden" name="action" value="delete_account"><input type="hidden" name="account_id" value="<?= $acc['id']; ?>">
                    <button type="submit" class="ba-btn ba-btn-danger ba-btn-sm"><i class="fas fa-trash"></i></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Add New Card -->
        <div class="ba-add-card" onclick="baOpenModal(0)">
            <div class="ba-add-card-inner">
                <i class="fas fa-plus-circle"></i>
                <p>Add Bank Account</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Modal ═══ -->
<div class="ba-modal-backdrop" id="baModal">
    <div class="ba-modal">
        <div class="ba-modal-header">
            <h3 id="baModalTitle"><i class="fas fa-university"></i> Add Bank Account</h3>
            <button class="ba-modal-close" onclick="baCloseModal()">&times;</button>
        </div>
        <form method="post" id="baForm">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="save_account">
            <input type="hidden" name="account_id" id="baId" value="0">
            <div class="ba-modal-body">
                <div class="ba-security-notice">
                    <i class="fas fa-lock"></i>
                    <div>Account numbers are encrypted before saving. They appear masked on the card for security.</div>
                </div>

                <div class="ba-form-row">
                    <div class="ba-form-group">
                        <label>Account Label <span class="req">*</span></label>
                        <input type="text" class="ba-form-input" name="account_label" id="baLabel" placeholder="e.g. Main Business Account" required>
                    </div>
                    <div class="ba-form-group">
                        <label>Bank Name <span class="req">*</span></label>
                        <input type="text" class="ba-form-input" name="bank_name" id="baBankName" placeholder="e.g. State Bank of India" required>
                    </div>
                </div>

                <div class="ba-form-group">
                    <label>Account Holder Name <span class="req">*</span></label>
                    <input type="text" class="ba-form-input" name="account_holder" id="baHolder" placeholder="Name as on bank account" required>
                </div>

                <div class="ba-form-row">
                    <div class="ba-form-group">
                        <label>Account Number <span class="req">*</span></label>
                        <input type="password" class="ba-form-input" name="account_number" id="baAccNum" autocomplete="off" required>
                        <div class="ba-form-hint">Leave blank when editing to keep existing</div>
                    </div>
                    <div class="ba-form-group">
                        <label>Account Type</label>
                        <select class="ba-form-select" name="account_type" id="baAccType">
                            <option value="current">Current</option>
                            <option value="savings">Savings</option>
                            <option value="overdraft">Overdraft</option>
                        </select>
                    </div>
                </div>

                <div class="ba-form-row">
                    <div class="ba-form-group">
                        <label>IFSC Code</label>
                        <input type="text" class="ba-form-input" name="ifsc_code" id="baIfsc" placeholder="e.g. SBIN0001234" style="text-transform:uppercase;">
                    </div>
                    <div class="ba-form-group">
                        <label>Branch Name</label>
                        <input type="text" class="ba-form-input" name="branch_name" id="baBranch" placeholder="e.g. Mumbai Main Branch">
                    </div>
                </div>

                <div class="ba-form-group">
                    <label>UPI ID</label>
                    <input type="text" class="ba-form-input" name="upi_id" id="baUpi" placeholder="e.g. gilafstore@upi">
                </div>

                <div class="ba-form-row">
                    <div class="ba-form-group">
                        <label>SWIFT Code <span style="font-size:10px;color:#94a3b8;">(International)</span></label>
                        <input type="text" class="ba-form-input" name="swift_code" id="baSwift" placeholder="e.g. SBININBB" style="text-transform:uppercase;">
                    </div>
                    <div class="ba-form-group">
                        <label>IBAN <span style="font-size:10px;color:#94a3b8;">(International)</span></label>
                        <input type="text" class="ba-form-input" name="iban" id="baIban" placeholder="e.g. IN12 SBIN 0001 2345 6789" style="text-transform:uppercase;">
                    </div>
                </div>

                <div class="ba-form-group">
                    <label>Notes</label>
                    <textarea class="ba-form-input" name="notes" id="baNotes" rows="2" placeholder="Internal notes (not shown publicly)" style="resize:vertical;"></textarea>
                </div>

                <div class="ba-form-check">
                    <input type="checkbox" name="is_primary" id="baPrimary" value="1">
                    <label for="baPrimary">Set as Primary Account</label>
                </div>
                <div class="ba-form-check">
                    <input type="checkbox" name="is_active" id="baActive" value="1" checked>
                    <label for="baActive">Account is Active</label>
                </div>
            </div>
            <div class="ba-modal-footer">
                <button type="button" class="ba-btn ba-btn-outline" onclick="baCloseModal()">Cancel</button>
                <button type="submit" class="ba-btn ba-btn-primary"><i class="fas fa-save"></i> Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
var baAccounts = <?= json_encode(array_map(function($a) {
    $num = ba_decrypt($a['account_number_enc']);
    return [
        'id' => (int)$a['id'],
        'label' => $a['account_label'],
        'bank_name' => $a['bank_name'],
        'holder' => $a['account_holder'],
        'acc_masked' => ba_mask_account($num),
        'ifsc' => $a['ifsc_code'],
        'branch' => $a['branch_name'],
        'acc_type' => $a['account_type'],
        'upi' => $a['upi_id'],
        'swift' => $a['swift_code'],
        'iban' => $a['iban'],
        'is_primary' => (int)$a['is_primary'],
        'is_active' => (int)$a['is_active'],
        'notes' => $a['notes'],
    ];
}, $accounts)); ?>;

function baOpenModal(id) {
    var d = null;
    if (id > 0) {
        for (var i = 0; i < baAccounts.length; i++) {
            if (baAccounts[i].id === id) { d = baAccounts[i]; break; }
        }
    }

    document.getElementById('baId').value = id;
    document.getElementById('baModalTitle').innerHTML = '<i class="fas fa-university"></i> ' + (id > 0 ? 'Edit Bank Account' : 'Add Bank Account');
    document.getElementById('baLabel').value = d ? d.label : '';
    document.getElementById('baBankName').value = d ? d.bank_name : '';
    document.getElementById('baHolder').value = d ? d.holder : '';
    document.getElementById('baAccNum').value = d ? d.acc_masked : '';
    document.getElementById('baAccNum').required = !d;
    document.getElementById('baIfsc').value = d ? d.ifsc : '';
    document.getElementById('baBranch').value = d ? d.branch : '';
    document.getElementById('baAccType').value = d ? d.acc_type : 'current';
    document.getElementById('baUpi').value = d ? d.upi : '';
    document.getElementById('baSwift').value = d ? d.swift : '';
    document.getElementById('baIban').value = d ? d.iban : '';
    document.getElementById('baPrimary').checked = d ? !!d.is_primary : false;
    document.getElementById('baActive').checked = d ? !!d.is_active : true;
    document.getElementById('baNotes').value = d ? d.notes : '';

    document.getElementById('baModal').classList.add('show');
}

function baCloseModal() {
    document.getElementById('baModal').classList.remove('show');
}

document.getElementById('baModal').addEventListener('click', function(e) {
    if (e.target === this) baCloseModal();
});

// Clear masked account number on focus
document.getElementById('baAccNum').addEventListener('focus', function() {
    if (this.value.indexOf('\u2022') !== -1) this.value = '';
    this.type = 'text';
});
document.getElementById('baAccNum').addEventListener('blur', function() {
    this.type = 'password';
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
