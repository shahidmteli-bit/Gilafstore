<?php
/**
 * Sales Executive Portal - Party (Customer) Management
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Parties';
$currentPage = 'parties';
$action = $_GET['action'] ?? 'list';

// Auto-migrate: ensure profile_type ENUM includes 'retailer' and prefix config exists
try {
    $migDb = get_db_connection();
    $migDb->exec("ALTER TABLE sales_parties MODIFY COLUMN profile_type ENUM('wholesaler','distributor','franchise','retailer') NOT NULL DEFAULT 'wholesaler'");
    // Ensure retailer prefix exists in config
    try {
        $hasRetailerPrefix = $migDb->prepare("SELECT id FROM sales_prefix_config WHERE prefix_type='party_type' AND reference_name='retailer'");
        $hasRetailerPrefix->execute();
        if ($hasRetailerPrefix->rowCount() === 0) {
            $migDb->exec("INSERT INTO sales_prefix_config (prefix_type, reference_name, prefix_code, description) VALUES ('party_type','retailer','R','Retailer prefix')");
        }
    } catch (PDOException $epc) { /* prefix table may not exist */ }
} catch (PDOException $eMig) { /* safe to ignore */ }

// Handle Delete Party
if ($action === 'delete' && isset($_GET['id']) && isset($_GET['confirm'])) {
    $delId = (int)$_GET['id'];
    // Check party belongs to this exec
    $delParty = db_fetch('SELECT id, shop_name FROM sales_parties WHERE id = ? AND created_by = ?', [$delId, $execId]);
    if ($delParty) {
        // Check if party has orders
        $hasOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE party_id = ?', [$delId])['cnt'] ?? 0;
        if ($hasOrders > 0) {
            // Soft delete (deactivate)
            db_query('UPDATE sales_parties SET is_active = 0 WHERE id = ?', [$delId]);
            $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Party "' . $delParty['shop_name'] . '" has been deactivated (has ' . $hasOrders . ' orders).'];
        } else {
            db_query('DELETE FROM sales_parties WHERE id = ? AND created_by = ?', [$delId, $execId]);
            $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Party "' . $delParty['shop_name'] . '" deleted permanently.'];
        }
    } else {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Party not found.'];
    }
    header('Location: ' . sales_base_url('parties.php'));
    exit;
}

// Handle Create Party
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'create' || $action === 'edit')) {
    $partyId = (int)($_POST['party_id'] ?? 0);
    $data = [
        'shop_name' => trim($_POST['shop_name'] ?? ''),
        'owner_name' => trim($_POST['owner_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'district' => trim($_POST['district'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => trim($_POST['state'] ?? 'Jammu and Kashmir'),
        'pincode' => trim($_POST['pincode'] ?? ''),
        'gst_number' => trim($_POST['gst_number'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'latitude' => !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null,
        'longitude' => !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null,
        'google_maps_url' => trim($_POST['google_maps_url'] ?? ''),
        'profile_type' => trim($_POST['profile_type'] ?? 'wholesaler'),
    ];

    if (empty($data['shop_name']) || empty($data['owner_name']) || empty($data['phone']) || empty($data['address']) || empty($data['district'])) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Please fill all required fields.'];
    } else {
        try {
            $baseFields = ['shop_name','owner_name','phone','email','address','district','city','state','pincode','gst_number','notes','profile_type'];
            $baseValues = [$data['shop_name'], $data['owner_name'], $data['phone'], $data['email'], $data['address'],
                $data['district'], $data['city'], $data['state'], $data['pincode'], $data['gst_number'], $data['notes'], $data['profile_type']];

            // Check if GPS columns exist using SHOW COLUMNS (reliable)
            $hasGps = false;
            try {
                $colCheck = db_fetch("SHOW COLUMNS FROM sales_parties LIKE 'latitude'");
                $hasGps = !empty($colCheck);
            } catch (\Throwable $e2) { /* columns don't exist yet */ }

            if ($hasGps) {
                $baseFields = array_merge($baseFields, ['latitude','longitude','google_maps_url']);
                $baseValues = array_merge($baseValues, [$data['latitude'], $data['longitude'], $data['google_maps_url']]);
            }

            if ($partyId > 0 && $action === 'edit') {
                $setClauses = implode('=?, ', $baseFields) . '=?';
                $params = array_merge($baseValues, [$partyId, $execId]);
                db_query('UPDATE sales_parties SET ' . $setClauses . ' WHERE id=? AND created_by=?', $params);

                // Regenerate party_code to reflect current profile_type & district
                try {
                    $companyPrefix = 'G';
                    $districtPrefix = '';
                    $partyTypePrefix = '';
                    try {
                        $cp = db_fetch("SELECT prefix_code FROM sales_prefix_config WHERE prefix_type='company' AND is_active=1 LIMIT 1");
                        if ($cp) $companyPrefix = $cp['prefix_code'];
                        $dp = db_fetch("SELECT prefix_code FROM sales_prefix_config WHERE prefix_type='district' AND reference_name=? AND is_active=1", [$data['district']]);
                        if ($dp) $districtPrefix = $dp['prefix_code'];
                        $tp = db_fetch("SELECT prefix_code FROM sales_prefix_config WHERE prefix_type='party_type' AND reference_name=? AND is_active=1", [$data['profile_type']]);
                        if ($tp) $partyTypePrefix = $tp['prefix_code'];
                    } catch (PDOException $epfx) { /* prefix table may not exist */ }

                    $codeParts = [$companyPrefix];
                    if ($districtPrefix) $codeParts[] = $districtPrefix;
                    if ($partyTypePrefix) $codeParts[] = $partyTypePrefix;
                    $codeParts[] = str_pad($partyId, 5, '0', STR_PAD_LEFT);
                    $newCode = implode('-', $codeParts);
                    db_query('UPDATE sales_parties SET party_code = ? WHERE id = ?', [$newCode, $partyId]);
                    $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Party updated successfully. Code: ' . $newCode];
                } catch (PDOException $erc) {
                    $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Party updated successfully.'];
                }
            } else {
                $placeholders = implode(',', array_fill(0, count($baseFields) + 1, '?'));
                $fieldList = implode(',', $baseFields) . ',created_by';
                $params = array_merge($baseValues, [$execId]);
                db_query('INSERT INTO sales_parties (' . $fieldList . ') VALUES (' . $placeholders . ')', $params);

                // Auto-generate unique party_code using configured prefixes
                $newId = (int)get_db_connection()->lastInsertId();
                try {
                    // Fetch configured prefixes
                    $companyPrefix = 'G';
                    $districtPrefix = '';
                    $partyTypePrefix = '';
                    try {
                        $cp = db_fetch("SELECT prefix_code FROM sales_prefix_config WHERE prefix_type='company' AND is_active=1 LIMIT 1");
                        if ($cp) $companyPrefix = $cp['prefix_code'];
                        $dp = db_fetch("SELECT prefix_code FROM sales_prefix_config WHERE prefix_type='district' AND reference_name=? AND is_active=1", [$data['district']]);
                        if ($dp) $districtPrefix = $dp['prefix_code'];
                        $tp = db_fetch("SELECT prefix_code FROM sales_prefix_config WHERE prefix_type='party_type' AND reference_name=? AND is_active=1", [$data['profile_type']]);
                        if ($tp) $partyTypePrefix = $tp['prefix_code'];
                    } catch (PDOException $epfx) { /* prefix table may not exist */ }

                    // Build party code: G-BRM-W-00001
                    $codeParts = [$companyPrefix];
                    if ($districtPrefix) $codeParts[] = $districtPrefix;
                    if ($partyTypePrefix) $codeParts[] = $partyTypePrefix;
                    $codeParts[] = str_pad($newId, 5, '0', STR_PAD_LEFT);
                    $partyCode = implode('-', $codeParts);

                    db_query('UPDATE sales_parties SET party_code = ? WHERE id = ?', [$partyCode, $newId]);
                    $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Party created successfully. Code: ' . $partyCode];
                } catch (PDOException $e3) {
                    $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Party created successfully.'];
                }
            }
            header('Location: ' . sales_base_url('parties.php'));
            exit;
        } catch (PDOException $e) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// District/city counts for filter pills
$districtCounts = db_fetch_all('SELECT district, COUNT(*) as cnt FROM sales_parties WHERE created_by = ? AND district IS NOT NULL AND district != "" GROUP BY district ORDER BY cnt DESC', [$execId]);
$filterDistrict = trim($_GET['district'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// Build status filter SQL
$statusWhere = '';
$statusParams = [];
if ($filterStatus === 'active') {
    $statusWhere = ' AND sp.is_active = 1 AND sp.is_blocked = 0 AND EXISTS (SELECT 1 FROM sales_orders so WHERE so.party_id = sp.id)';
} elseif ($filterStatus === 'inactive') {
    $statusWhere = ' AND sp.is_active = 1 AND sp.is_blocked = 0 AND NOT EXISTS (SELECT 1 FROM sales_orders so WHERE so.party_id = sp.id)';
} elseif ($filterStatus === 'at-risk') {
    $statusWhere = ' AND sp.is_active = 1 AND sp.is_blocked = 0 AND (sp.rating_label IN ("low","average") OR sp.consecutive_low_recovery >= 2 OR (sp.outstanding_amount > 0 AND sp.oldest_due_date IS NOT NULL AND sp.oldest_due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND sp.oldest_due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)))';
} elseif ($filterStatus === 'blocked') {
    $statusWhere = ' AND (sp.is_blocked = 1 OR sp.rating_label = "blocked")';
}

// Fetch parties
$search = trim($_GET['search'] ?? '');
$parties = [];
try {
    $baseQuery = 'SELECT sp.* FROM sales_parties sp WHERE sp.created_by = ?' . $statusWhere;
    $baseParams = [$execId];

    if ($search) {
        $like = '%' . $search . '%';
        $searchWhere = ' AND (sp.shop_name LIKE ? OR sp.owner_name LIKE ? OR sp.phone LIKE ? OR sp.district LIKE ?)';
        if ($filterDistrict) {
            $parties = db_fetch_all($baseQuery . ' AND sp.district = ?' . $searchWhere . ' ORDER BY sp.shop_name ASC', array_merge($baseParams, [$filterDistrict, $like, $like, $like, $like]));
        } else {
            $parties = db_fetch_all($baseQuery . $searchWhere . ' ORDER BY sp.shop_name ASC', array_merge($baseParams, [$like, $like, $like, $like]));
        }
    } elseif ($filterDistrict) {
        $parties = db_fetch_all($baseQuery . ' AND sp.district = ? ORDER BY sp.created_at DESC', array_merge($baseParams, [$filterDistrict]));
    } else {
        $parties = db_fetch_all($baseQuery . ' ORDER BY sp.created_at DESC', $baseParams);
    }
} catch (PDOException $eqs) {
    // Fallback if new columns don't exist
    if ($search) {
        $like = '%' . $search . '%';
        $parties = db_fetch_all('SELECT * FROM sales_parties WHERE created_by = ? AND (shop_name LIKE ? OR owner_name LIKE ? OR phone LIKE ? OR district LIKE ?) ORDER BY shop_name ASC', [$execId, $like, $like, $like, $like]);
    } else {
        $parties = db_fetch_all('SELECT * FROM sales_parties WHERE created_by = ? ORDER BY created_at DESC', [$execId]);
    }
}

// Edit mode
$editParty = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editParty = db_fetch('SELECT * FROM sales_parties WHERE id = ? AND created_by = ?', [(int)$_GET['id'], $execId]);
    if (!$editParty) {
        header('Location: ' . sales_base_url('parties.php'));
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'create' || $action === 'edit'): ?>
<!-- Create / Edit Party Form -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-<?= $editParty ? 'edit' : 'user-plus' ?>"></i> <?= $editParty ? 'Edit Party' : 'Create New Party' ?></h3>
        <a href="<?= sales_base_url('parties.php') ?>" class="sp-btn sp-btn-outline sp-btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <form method="POST">
        <?php if ($editParty): ?>
            <input type="hidden" name="party_id" value="<?= $editParty['id'] ?>">
        <?php endif; ?>

        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>Shop Name *</label>
                <input type="text" name="shop_name" class="sp-input" required value="<?= htmlspecialchars($editParty['shop_name'] ?? '') ?>" placeholder="Enter shop name">
            </div>
            <div class="sp-form-group">
                <label>Owner Name *</label>
                <input type="text" name="owner_name" class="sp-input" required value="<?= htmlspecialchars($editParty['owner_name'] ?? '') ?>" placeholder="Enter owner name">
            </div>
        </div>

        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>Phone Number *</label>
                <input type="tel" name="phone" class="sp-input" required value="<?= htmlspecialchars($editParty['phone'] ?? '') ?>" placeholder="Enter phone number">
            </div>
            <div class="sp-form-group">
                <label>Email</label>
                <input type="email" name="email" class="sp-input" value="<?= htmlspecialchars($editParty['email'] ?? '') ?>" placeholder="Optional">
            </div>
        </div>

        <div class="sp-form-group">
            <label>Profile Type *</label>
            <select name="profile_type" class="sp-select" required>
                <option value="wholesaler" <?= ($editParty['profile_type'] ?? 'wholesaler') === 'wholesaler' ? 'selected' : '' ?>>Wholesaler</option>
                <option value="distributor" <?= ($editParty['profile_type'] ?? '') === 'distributor' ? 'selected' : '' ?>>Distributor</option>
                <option value="franchise" <?= ($editParty['profile_type'] ?? '') === 'franchise' ? 'selected' : '' ?>>Franchisee</option>
                <option value="retailer" <?= ($editParty['profile_type'] ?? '') === 'retailer' ? 'selected' : '' ?>>Retailer</option>
            </select>
        </div>

        <!-- GPS Auto-Fill Location -->
        <div class="sp-form-group">
            <button type="button" class="sp-btn sp-btn-primary sp-btn-lg" id="gpsAutoFillBtn" onclick="getPartyLocation()" style="width:100%;white-space:normal;text-align:center;">
                <i class="fas fa-crosshairs"></i> <span>Auto-Fill from GPS Location</span>
            </button>
            <div id="locationStatus" style="margin-top:8px;"></div>
        </div>

        <div class="sp-form-group">
            <label>Address *</label>
            <textarea name="address" id="partyAddress" class="sp-textarea" required placeholder="Full address"><?= htmlspecialchars($editParty['address'] ?? '') ?></textarea>
        </div>

        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>District *</label>
                <input type="text" name="district" id="partyDistrict" class="sp-input" required value="<?= htmlspecialchars($editParty['district'] ?? '') ?>" placeholder="Enter district">
            </div>
            <div class="sp-form-group">
                <label>City</label>
                <input type="text" name="city" id="partyCity" class="sp-input" value="<?= htmlspecialchars($editParty['city'] ?? '') ?>" placeholder="Enter city">
            </div>
        </div>

        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>State</label>
                <input type="text" name="state" id="partyState" class="sp-input" value="<?= htmlspecialchars($editParty['state'] ?? 'Jammu and Kashmir') ?>">
            </div>
            <div class="sp-form-group">
                <label>Pincode</label>
                <input type="text" name="pincode" id="partyPincode" class="sp-input" value="<?= htmlspecialchars($editParty['pincode'] ?? '') ?>" placeholder="Enter pincode">
            </div>
        </div>

        <!-- GPS Coordinates — hidden inputs for reliable submission + display inputs -->
        <input type="hidden" name="latitude" id="partyLatitude" value="<?= htmlspecialchars($editParty['latitude'] ?? '') ?>">
        <input type="hidden" name="longitude" id="partyLongitude" value="<?= htmlspecialchars($editParty['longitude'] ?? '') ?>">
        <input type="hidden" name="google_maps_url" id="partyMapsUrl" value="<?= htmlspecialchars($editParty['google_maps_url'] ?? '') ?>">
        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>Latitude</label>
                <input type="text" id="partyLatitudeDisplay" class="sp-input" disabled value="<?= htmlspecialchars($editParty['latitude'] ?? '') ?>" placeholder="Auto-filled by GPS" style="background:#f3f4f6;color:#6b7280;">
            </div>
            <div class="sp-form-group">
                <label>Longitude</label>
                <input type="text" id="partyLongitudeDisplay" class="sp-input" disabled value="<?= htmlspecialchars($editParty['longitude'] ?? '') ?>" placeholder="Auto-filled by GPS" style="background:#f3f4f6;color:#6b7280;">
            </div>
        </div>
        <div class="sp-form-group">
            <label>Google Maps URL</label>
            <input type="text" id="partyMapsUrlDisplay" class="sp-input" disabled value="<?= htmlspecialchars($editParty['google_maps_url'] ?? '') ?>" placeholder="Auto-generated" style="background:#f3f4f6;color:#6b7280;">
        </div>

        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>GST Number</label>
                <input type="text" name="gst_number" class="sp-input" value="<?= htmlspecialchars($editParty['gst_number'] ?? '') ?>" placeholder="Optional">
            </div>
            <div class="sp-form-group">
                <label>Credit Limit (₹) <span style="font-size:10px;color:#6b7280;font-weight:400;">Set by Admin</span></label>
                <input type="text" class="sp-input" readonly value="₹<?= number_format($editParty['credit_limit'] ?? 0, 2) ?>" style="background:#f3f4f6;color:#6b7280;cursor:not-allowed;">
            </div>
        </div>

        <div class="sp-form-group">
            <label>Notes</label>
            <textarea name="notes" class="sp-textarea" placeholder="Internal notes about this party"><?= htmlspecialchars($editParty['notes'] ?? '') ?></textarea>
        </div>

        <div class="sp-action-row" style="margin-top:8px;">
            <button type="submit" class="sp-btn sp-btn-primary sp-btn-lg">
                <i class="fas fa-<?= $editParty ? 'save' : 'plus' ?>"></i> <?= $editParty ? 'Update Party' : 'Create Party' ?>
            </button>
            <a href="<?= sales_base_url('parties.php') ?>" class="sp-btn sp-btn-outline sp-btn-lg">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- Party List -->
<div class="sp-flex-between sp-mb-24">
    <div></div>
    <a href="<?= sales_base_url('parties.php?action=create') ?>" class="sp-btn sp-btn-primary">
        <i class="fas fa-user-plus"></i> Create New Party
    </a>
</div>

<?php
// Status filter pills config
$statusFilters = [
    '' => ['label' => 'All', 'bg' => '#1A3C34', 'color' => '#fff', 'bgOff' => '#f3f4f6', 'colorOff' => '#374151'],
    'active' => ['label' => 'Active', 'bg' => '#059669', 'color' => '#fff', 'bgOff' => '#ecfdf5', 'colorOff' => '#065f46'],
    'inactive' => ['label' => 'Inactive', 'bg' => '#6b7280', 'color' => '#fff', 'bgOff' => '#f3f4f6', 'colorOff' => '#4b5563'],
    'at-risk' => ['label' => 'At-Risk', 'bg' => '#d97706', 'color' => '#fff', 'bgOff' => '#fef3c7', 'colorOff' => '#92400e'],
    'blocked' => ['label' => 'Blocked', 'bg' => '#dc2626', 'color' => '#fff', 'bgOff' => '#fef2f2', 'colorOff' => '#991b1b'],
];
?>
<div style="display:flex;gap:6px;overflow-x:auto;padding:0 0 8px 0;margin-bottom:8px;-webkit-overflow-scrolling:touch;">
    <?php foreach ($statusFilters as $sKey => $sf):
        $isActive = ($filterStatus === $sKey);
        $href = $sKey ? sales_base_url('parties.php?status=' . $sKey) : sales_base_url('parties.php');
    ?>
    <a href="<?= $href ?>" style="flex:0 0 auto;padding:7px 14px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;<?= $isActive ? 'background:' . $sf['bg'] . ';color:' . $sf['color'] . ';' : 'background:' . $sf['bgOff'] . ';color:' . $sf['colorOff'] . ';border:1px solid #e5e7eb;' ?>">
        <?= $sf['label'] ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($districtCounts)): ?>
<div style="display:flex;gap:8px;overflow-x:auto;padding:0 0 8px 0;margin-bottom:12px;-webkit-overflow-scrolling:touch;">
    <a href="<?= sales_base_url('parties.php' . ($filterStatus ? '?status=' . urlencode($filterStatus) : '')) ?>" style="flex:0 0 auto;display:flex;align-items:center;gap:5px;padding:8px 14px;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;<?= !$filterDistrict ? 'background:#1A3C34;color:#fff;' : 'background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;' ?>">
        All <span style="background:<?= !$filterDistrict ? 'rgba(255,255,255,0.2)' : '#e5e7eb' ?>;padding:2px 7px;border-radius:6px;font-size:11px;"><?= array_sum(array_column($districtCounts, 'cnt')) ?></span>
    </a>
    <?php foreach ($districtCounts as $dc):
        $distHref = 'parties.php?district=' . urlencode($dc['district']) . ($filterStatus ? '&status=' . urlencode($filterStatus) : '');
    ?>
    <a href="<?= sales_base_url($distHref) ?>" style="flex:0 0 auto;display:flex;align-items:center;gap:5px;padding:8px 14px;border-radius:10px;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;<?= $filterDistrict === $dc['district'] ? 'background:#1A3C34;color:#fff;' : 'background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;' ?>">
        <?= htmlspecialchars($dc['district']) ?> <span style="background:<?= $filterDistrict === $dc['district'] ? 'rgba(255,255,255,0.2)' : '#e5e7eb' ?>;padding:2px 7px;border-radius:6px;font-size:11px;"><?= $dc['cnt'] ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;">
    <div class="sp-search-bar" style="flex:1;margin-bottom:0;">
        <i class="fas fa-search"></i>
        <form method="GET" style="width:100%;">
            <?php if ($filterDistrict): ?><input type="hidden" name="district" value="<?= htmlspecialchars($filterDistrict) ?>"><?php endif; ?>
            <?php if ($filterStatus): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
            <input type="text" name="search" placeholder="Search by shop name, owner, phone, district..." value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
        </form>
    </div>
    <a href="<?= sales_base_url('scan_party.php') ?>" style="flex:0 0 auto;width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#1A3C34;color:#fff;font-size:18px;text-decoration:none;">
        <i class="fas fa-qrcode"></i>
    </a>
</div>

<?php if (empty($parties)): ?>
    <div class="sp-card">
        <div class="sp-empty">
            <i class="fas fa-users"></i>
            <h3>No parties found</h3>
            <p><?= $search ? 'Try a different search term.' : 'Start by creating your first party.' ?></p>
            <a href="<?= sales_base_url('parties.php?action=create') ?>" class="sp-btn sp-btn-primary">
                <i class="fas fa-user-plus"></i> Create Party
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="sp-party-list">
        <?php
        $ptColors = ['wholesaler' => ['bg' => '#d1fae5', 'color' => '#065f46'], 'distributor' => ['bg' => '#fef3c7', 'color' => '#92400e'], 'franchise' => ['bg' => '#ede9fe', 'color' => '#5b21b6'], 'retailer' => ['bg' => '#fce7f3', 'color' => '#9d174d'], 'reseller' => ['bg' => '#fce7f3', 'color' => '#9d174d']];
        $ptLabels = ['wholesaler' => 'Wholesaler', 'distributor' => 'Distributor', 'franchise' => 'Franchisee', 'retailer' => 'Retailer', 'reseller' => 'Retailer'];
        foreach ($parties as $party):
            $pt = !empty($party['profile_type']) ? $party['profile_type'] : 'wholesaler';
            $ptc = $ptColors[$pt] ?? $ptColors['wholesaler'];
            $hasOutstanding = $party['outstanding_amount'] > 0;
        ?>
        <?php
            $rating = (float)($party['rating'] ?? 0);
            $ratingLabel = $party['rating_label'] ?? 'good';
            $ratingColors = ['good' => ['bg' => '#ecfdf5', 'color' => '#059669'], 'average' => ['bg' => '#fef3c7', 'color' => '#d97706'], 'low' => ['bg' => '#fee2e2', 'color' => '#dc2626'], 'blocked' => ['bg' => '#1f2937', 'color' => '#fff']];
            $rc = $ratingColors[$ratingLabel] ?? $ratingColors['good'];
            $turnover = (float)($party['turnover_amount'] ?? 0);

            // Determine party status
            $isBlocked = !empty($party['is_blocked']) || $ratingLabel === 'blocked';
            $partyStatus = 'active'; $statusBadge = ['bg' => '#ecfdf5', 'color' => '#059669', 'icon' => 'check-circle', 'label' => 'Active'];
            if ($isBlocked) {
                $partyStatus = 'blocked'; $statusBadge = ['bg' => '#1f2937', 'color' => '#fff', 'icon' => 'ban', 'label' => 'Blocked'];
            } elseif ($ratingLabel === 'low' || (!empty($party['consecutive_low_recovery']) && $party['consecutive_low_recovery'] >= 2) || (!empty($party['oldest_due_date']) && $party['outstanding_amount'] > 0 && strtotime($party['oldest_due_date']) < strtotime('-30 days'))) {
                $partyStatus = 'at-risk'; $statusBadge = ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'exclamation-triangle', 'label' => 'At-Risk'];
            } else {
                try {
                    $hasOrders = (int)(db_fetch("SELECT COUNT(*) as cnt FROM sales_orders WHERE party_id = ? LIMIT 1", [$party['id']])['cnt'] ?? 0);
                    if ($hasOrders === 0) {
                        $partyStatus = 'inactive'; $statusBadge = ['bg' => '#f3f4f6', 'color' => '#6b7280', 'icon' => 'pause-circle', 'label' => 'Inactive'];
                    }
                } catch (PDOException $eso) { /* safe */ }
            }
        ?>
        <div class="sp-party-card">
            <a href="<?= sales_base_url('party_detail.php?id=' . $party['id']) ?>" class="sp-party-card-main">
                <div class="sp-party-card-avatar">
                    <?= strtoupper(substr($party['shop_name'], 0, 1)) ?>
                </div>
                <div class="sp-party-card-info">
                    <div class="sp-party-card-name"><?= htmlspecialchars($party['shop_name']) ?></div>
                    <div class="sp-party-card-owner"><?= htmlspecialchars($party['owner_name']) ?></div>
                    <div class="sp-party-card-meta">
                        <span><i class="fas fa-phone"></i> <?= htmlspecialchars($party['phone']) ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($party['district']) ?></span>
                    </div>
                    <?php if ($turnover > 0): ?>
                    <div style="font-size:11px;color:#059669;font-weight:600;margin-top:2px;"><i class="fas fa-chart-bar"></i> Turnover: ₹<?= number_format($turnover, 0) ?></div>
                    <?php endif; ?>
                </div>
                <i class="fas fa-chevron-right sp-party-card-arrow"></i>
            </a>
            <div class="sp-party-card-footer">
                <div class="sp-party-card-badges">
                    <span class="sp-party-card-badge" style="background:rgba(26,60,52,0.08);color:#1A3C34;"><?= htmlspecialchars($party['party_code'] ?? '—') ?></span>
                    <span class="sp-party-card-badge" style="background:<?= $ptc['bg'] ?>;color:<?= $ptc['color'] ?>;"><?= $ptLabels[$pt] ?? ucfirst($pt) ?></span>
                    <?php if ($hasOutstanding): ?>
                    <span class="sp-party-card-badge" style="background:#fee2e2;color:#991b1b;">Due: ₹<?= number_format($party['outstanding_amount'], 0) ?></span>
                    <?php endif; ?>
                    <?php if ($rating > 0): ?>
                    <span class="sp-party-card-badge" style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;" title="Rating: <?= number_format($rating, 1) ?>/5">
                        <?php for ($s = 1; $s <= 5; $s++): ?><i class="fa<?= $s <= round($rating) ? 's' : 'r' ?> fa-star" style="font-size:9px;"></i><?php endfor; ?>
                    </span>
                    <?php endif; ?>
                    <span class="sp-party-card-badge" style="background:<?= $statusBadge['bg'] ?>;color:<?= $statusBadge['color'] ?>;"><i class="fas fa-<?= $statusBadge['icon'] ?>"></i> <?= $statusBadge['label'] ?></span>
                </div>
                <div class="sp-party-card-actions">
                    <?php if (!empty($party['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($party['phone']) ?>" class="sp-party-action-btn" title="Call" style="color:#059669;">
                        <i class="fas fa-phone-alt"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($party['google_maps_url'])): ?>
                    <a href="<?= htmlspecialchars($party['google_maps_url']) ?>" target="_blank" class="sp-party-action-btn" title="Directions" style="color:#2563eb;">
                        <i class="fas fa-directions"></i>
                    </a>
                    <?php elseif (!empty($party['latitude']) && !empty($party['longitude'])): ?>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $party['latitude'] ?>,<?= $party['longitude'] ?>" target="_blank" class="sp-party-action-btn" title="Directions" style="color:#2563eb;">
                        <i class="fas fa-directions"></i>
                    </a>
                    <?php endif; ?>
                    <a href="<?= sales_base_url('new_order.php?party_id=' . $party['id']) ?>" class="sp-party-action-btn sp-party-action-order" title="New Order">
                        <i class="fas fa-cart-plus"></i>
                    </a>
                    <span class="sp-party-action-btn" id="shareBtn<?= $party['id'] ?>" title="Share on WhatsApp" style="color:#25D366;cursor:pointer;font-size:15px;" onclick="sharePartyWhatsApp(<?= $party['id'] ?>,'<?= htmlspecialchars(addslashes($party['shop_name'])) ?>','<?= htmlspecialchars($party['party_code'] ?? '') ?>','<?= number_format($party['outstanding_amount'], 0) ?>','<?= htmlspecialchars($party['phone'] ?? '') ?>', <?= $hasOutstanding ? 'true' : 'false' ?>)">
                        <i class="fab fa-whatsapp"></i>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php endif; ?>

<script>
function confirmDeleteParty(id, name) {
    if (confirm('Are you sure you want to delete party "' + name + '"?\n\nIf this party has orders, it will be deactivated instead of permanently deleted.')) {
        window.location.href = '<?= sales_base_url("parties.php") ?>?action=delete&id=' + id + '&confirm=1';
    }
}

function _applyGpsResult(lat, lng, accuracy) {
    // Fill hidden inputs (form submission)
    document.getElementById('partyLatitude').value = lat.toFixed(8);
    document.getElementById('partyLongitude').value = lng.toFixed(8);
    document.getElementById('partyMapsUrl').value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
    // Fill display inputs (visual feedback)
    document.getElementById('partyLatitudeDisplay').value = lat.toFixed(8);
    document.getElementById('partyLongitudeDisplay').value = lng.toFixed(8);
    document.getElementById('partyMapsUrlDisplay').value = 'https://www.google.com/maps?q=' + lat + ',' + lng;

    var statusDiv = document.getElementById('locationStatus');
    var btn = document.getElementById('gpsAutoFillBtn');

    statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#ecfdf5;color:#065f46;font-size:13px;"><i class="fas fa-check-circle"></i> Location captured! Fetching address...</div>';

    // Reverse geocoding via OpenStreetMap Nominatim
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.address) {
                var addr = data.address;
                var district = addr.state_district || addr.county || addr.city_district || '';
                if (district) document.getElementById('partyDistrict').value = district;
                var city = addr.city || addr.town || addr.village || addr.municipality || '';
                if (city) document.getElementById('partyCity').value = city;
                var state = addr.state || '';
                if (state) document.getElementById('partyState').value = state;
                var pincode = addr.postcode || '';
                if (pincode) document.getElementById('partyPincode').value = pincode;

                var addressField = document.getElementById('partyAddress');
                if (!addressField.value.trim()) {
                    var parts = [addr.road, addr.suburb || addr.neighbourhood, addr.city || addr.town || addr.village, district, state, pincode].filter(function(p) { return p; });
                    addressField.value = parts.join(', ');
                }

                statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#ecfdf5;color:#065f46;font-size:13px;">' +
                    '<i class="fas fa-check-circle"></i> <strong>Location Auto-Filled!</strong><br>' +
                    '<small><strong>City:</strong> ' + city + ' | <strong>District:</strong> ' + district + '<br>' +
                    '<strong>State:</strong> ' + state + ' | <strong>PIN:</strong> ' + pincode + '<br>' +
                    '<strong>Accuracy:</strong> ~' + accuracy.toFixed(0) + ' meters</small></div>';
            } else {
                statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#fffbeb;color:#92400e;font-size:13px;"><i class="fas fa-exclamation-triangle"></i> GPS captured but address details unavailable. Please fill manually.</div>';
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Location Captured — Tap to Refresh';
        })
        .catch(function() {
            statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#fffbeb;color:#92400e;font-size:13px;"><i class="fas fa-exclamation-triangle"></i> GPS captured but could not fetch address. Fill manually.</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-crosshairs"></i> Use Current Location & Auto-Fill Address';
        });
}

function getPartyLocation() {
    var statusDiv = document.getElementById('locationStatus');
    var btn = document.getElementById('gpsAutoFillBtn');

    if (!navigator.geolocation) {
        statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#fef2f2;color:#991b1b;font-size:13px;">Geolocation is not supported by your browser.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting Location...';
    statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#eff6ff;color:#1e40af;font-size:13px;"><i class="fas fa-info-circle"></i> Requesting GPS (high accuracy)...</div>';

    // Attempt 1: High accuracy (GPS hardware)
    navigator.geolocation.getCurrentPosition(
        function(pos) { _applyGpsResult(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy); },
        function(err) {
            // If PERMISSION_DENIED, no point retrying
            if (err.code === 1) {
                statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#fef2f2;color:#991b1b;font-size:13px;"><i class="fas fa-times-circle"></i> Location permission denied. Please allow location access in your browser/phone settings and try again.</div>';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-crosshairs"></i> <span>Try Again</span>';
                return;
            }
            // Attempt 2: Low accuracy fallback (WiFi / cell tower)
            statusDiv.innerHTML = '<div style="padding:10px;border-radius:10px;background:#eff6ff;color:#1e40af;font-size:13px;"><i class="fas fa-spinner fa-spin"></i> GPS unavailable, trying network location...</div>';
            navigator.geolocation.getCurrentPosition(
                function(pos) { _applyGpsResult(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy); },
                function(err2) {
                    // Both attempts failed — show helpful message with manual option
                    var helpHtml = '<div style="padding:12px;border-radius:10px;background:#fef2f2;color:#991b1b;font-size:13px;">' +
                        '<i class="fas fa-times-circle"></i> <strong>Could not get location.</strong><br><br>' +
                        '<strong>Try these steps:</strong><br>' +
                        '1. Turn ON your phone\'s GPS/Location<br>' +
                        '2. Allow location permission when prompted<br>' +
                        '3. If using the app, close and reopen it<br>' +
                        '4. Tap the button to try again<br><br>' +
                        '<strong>Or paste from Google Maps:</strong><br>' +
                        '<input type="text" id="manualCoordsInput" class="sp-input" placeholder="Paste: 34.0836, 74.7973" style="margin-top:6px;font-size:13px;">' +
                        '<button type="button" onclick="applyManualCoords()" class="sp-btn sp-btn-primary" style="margin-top:6px;padding:6px 14px;font-size:12px;"><i class="fas fa-check"></i> Apply</button>' +
                        '</div>';
                    statusDiv.innerHTML = helpHtml;
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-crosshairs"></i> <span>Try Again</span>';
                },
                { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 }
            );
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

function applyManualCoords() {
    var input = document.getElementById('manualCoordsInput');
    if (!input) return;
    var val = input.value.trim();
    // Accept formats: "34.0836, 74.7973" or "34.0836 74.7973" or Google Maps URL
    var lat, lng;
    var urlMatch = val.match(/[?&@](-?\d+\.?\d*),\s*(-?\d+\.?\d*)/);
    var plainMatch = val.match(/^(-?\d+\.?\d*)[,\s]+(-?\d+\.?\d*)$/);
    if (urlMatch) { lat = parseFloat(urlMatch[1]); lng = parseFloat(urlMatch[2]); }
    else if (plainMatch) { lat = parseFloat(plainMatch[1]); lng = parseFloat(plainMatch[2]); }

    if (lat && lng && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        _applyGpsResult(lat, lng, 0);
    } else {
        document.getElementById('locationStatus').innerHTML = '<div style="padding:10px;border-radius:10px;background:#fef2f2;color:#991b1b;font-size:13px;"><i class="fas fa-times-circle"></i> Invalid coordinates. Paste like: <code>34.0836, 74.7973</code> or a Google Maps URL.</div>';
    }
}

function sharePartyWhatsApp(id, partyName, partyId, dueAmount, partyPhone, hasDue) {
    var today = new Date();
    var dateStr = today.getDate().toString().padStart(2,'0') + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][today.getMonth()] + ' ' + today.getFullYear();

    var msg;
    if (hasDue && dueAmount !== '0') {
        msg = 'Hi ' + partyName + ',\n'
            + '🆔 *Party ID:* ' + partyId + '\n\n'
            + 'This is a gentle reminder regarding your pending payment.\n\n'
            + '📅 *Date:* ' + dateStr + '\n'
            + '💰 *Due Amount:* ₹' + dueAmount + '\n\n'
            + 'Kindly clear the outstanding amount at your earliest convenience.\n\n'
            + 'Thanks for choosing *Gilaf*.\n'
            + 'Your satisfaction is our priority.';
    } else {
        msg = 'Hi ' + partyName + ',\n'
            + '🆔 *Party ID:* ' + partyId + '\n\n'
            + 'Greetings from *Gilaf*!\n\n'
            + '📅 *Date:* ' + dateStr + '\n\n'
            + 'Thank you for being a valued partner.\n'
            + 'Your satisfaction is our priority.';
    }

    var url;
    if (partyPhone) {
        var phone = partyPhone.replace(/[^0-9]/g, '');
        if (phone.length === 10) phone = '91' + phone;
        url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg);
    } else {
        url = 'https://wa.me/?text=' + encodeURIComponent(msg);
    }
    window.open(url, '_blank');

    // Mark as shared
    var btn = document.getElementById('shareBtn' + id);
    if (btn) {
        btn.style.color = '#9ca3af';
        btn.style.borderColor = '#e5e7eb';
        btn.title = 'Shared';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
