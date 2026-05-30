<?php
/**
 * Sales Portal - Gifts / Promotional Distribution Module
 * Party selection, item management, district filters, distribution history.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Gifts & Promotions';
$currentPage = 'gifts';

$districtFilter = trim($_GET['district'] ?? '');
$dateFilter = trim($_GET['date_range'] ?? '');
$searchFilter = trim($_GET['search'] ?? '');

// Fetch gift items
$giftItems = [];
try {
    $giftItems = db_fetch_all('SELECT * FROM sales_gift_items WHERE is_active = 1 ORDER BY name ASC');
} catch (PDOException $e) { /* table may not exist */ }

// Fetch parties for selection
$allParties = db_fetch_all('SELECT id, shop_name, owner_name, phone, district, party_code FROM sales_parties WHERE created_by = ? AND is_active = 1 ORDER BY shop_name ASC', [$execId]);

// Get distinct districts
$districts = db_fetch_all('SELECT DISTINCT district FROM sales_parties WHERE created_by = ? AND is_active = 1 AND district != "" ORDER BY district ASC', [$execId]);

// Handle delete distribution
if (isset($_GET['delete_id'])) {
    $delId = (int)$_GET['delete_id'];
    try {
        db_query('DELETE FROM sales_gift_distributions WHERE id = ? AND executive_id = ?', [$delId, $execId]);
        $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Distribution record deleted.'];
    } catch (PDOException $e) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Error deleting record.'];
    }
    header('Location: ' . sales_base_url('gifts.php'));
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_gift'])) {
    $gPartyId = (int)($_POST['party_id'] ?? 0);
    $gItemId = (int)($_POST['gift_item_id'] ?? 0);
    $gCustomItem = trim($_POST['custom_item_name'] ?? '');
    $gQty = max(1, (int)($_POST['quantity'] ?? 1));
    $gAmount = (float)($_POST['amount'] ?? 0);
    $gNotes = trim($_POST['notes'] ?? '');

    $errors = [];
    if ($gPartyId <= 0) $errors[] = 'Select a party.';
    if ($gItemId <= 0 && empty($gCustomItem)) $errors[] = 'Select a gift item or enter custom item name.';

    if (!empty($errors)) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    } else {
        try {
            // Get party district
            $partyDistrict = db_fetch('SELECT district FROM sales_parties WHERE id = ?', [$gPartyId])['district'] ?? '';

            db_query('INSERT INTO sales_gift_distributions (executive_id, party_id, gift_item_id, custom_item_name, quantity, amount, district, notes) VALUES (?,?,?,?,?,?,?,?)', [
                $execId, $gPartyId,
                $gItemId > 0 ? $gItemId : null,
                $gItemId <= 0 ? $gCustomItem : null,
                $gQty, $gAmount, $partyDistrict, $gNotes
            ]);

            $_SESSION['sp_flash'] = ['type' => 'success', 'message' => 'Gift distribution recorded successfully.'];
            header('Location: ' . sales_base_url('gifts.php'));
            exit;
        } catch (PDOException $e) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Fetch distribution history
$historySql = 'SELECT gd.*, sp.shop_name, sp.owner_name, gi.name as gift_name FROM sales_gift_distributions gd JOIN sales_parties sp ON gd.party_id = sp.id LEFT JOIN sales_gift_items gi ON gd.gift_item_id = gi.id WHERE gd.executive_id = ?';
$historyParams = [$execId];
if ($districtFilter) {
    $historySql .= ' AND gd.district = ?';
    $historyParams[] = $districtFilter;
}
if ($searchFilter) {
    $historySql .= ' AND (sp.shop_name LIKE ? OR sp.owner_name LIKE ?)';
    $historyParams[] = '%' . $searchFilter . '%';
    $historyParams[] = '%' . $searchFilter . '%';
}
if ($dateFilter === 'today') {
    $historySql .= ' AND DATE(gd.created_at) = CURDATE()';
} elseif ($dateFilter === 'week') {
    $historySql .= ' AND gd.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
} elseif ($dateFilter === 'month') {
    $historySql .= ' AND MONTH(gd.created_at) = MONTH(CURDATE()) AND YEAR(gd.created_at) = YEAR(CURDATE())';
} elseif ($dateFilter === 'last_month') {
    $historySql .= ' AND MONTH(gd.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(gd.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))';
}
$historySql .= ' ORDER BY gd.created_at DESC LIMIT 50';
$distributions = [];
try {
    $distributions = db_fetch_all($historySql, $historyParams);
} catch (PDOException $e) { /* table may not exist */ }

// Summary stats
$totalDistributions = 0;
$totalValue = 0;
$todayCount = 0;
try {
    $totalDistributions = db_fetch('SELECT COUNT(*) as cnt FROM sales_gift_distributions WHERE executive_id = ?', [$execId])['cnt'] ?? 0;
    $totalValue = db_fetch('SELECT COALESCE(SUM(amount * quantity), 0) as t FROM sales_gift_distributions WHERE executive_id = ?', [$execId])['t'] ?? 0;
    $todayCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_gift_distributions WHERE executive_id = ? AND DATE(created_at) = CURDATE()', [$execId])['cnt'] ?? 0;
} catch (PDOException $e) { /* safe */ }

include __DIR__ . '/includes/header.php';
?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
    <div style="background:#ede9fe;border-radius:14px;padding:14px;text-align:center;">
        <div style="font-size:22px;font-weight:800;color:#5b21b6;"><?= $totalDistributions ?></div>
        <div style="font-size:11px;color:#6d28d9;font-weight:600;">Total Given</div>
    </div>
    <div style="background:#fef3c7;border-radius:14px;padding:14px;text-align:center;">
        <div style="font-size:22px;font-weight:800;color:#92400e;">₹<?= number_format($totalValue, 0) ?></div>
        <div style="font-size:11px;color:#b45309;font-weight:600;">Total Value</div>
    </div>
    <div style="background:#ecfdf5;border-radius:14px;padding:14px;text-align:center;">
        <div style="font-size:22px;font-weight:800;color:#059669;"><?= $todayCount ?></div>
        <div style="font-size:11px;color:#047857;font-weight:600;">Today</div>
    </div>
</div>

<!-- Record Distribution Form -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-gift"></i> Record Gift Distribution</h3>
    </div>
    <form method="POST">
        <input type="hidden" name="submit_gift" value="1">

        <div class="sp-form-group">
            <label>Select Party *</label>
            <select name="party_id" class="sp-select" required id="giftPartySelect">
                <option value="">— Select Party —</option>
                <?php foreach ($allParties as $p): ?>
                    <option value="<?= $p['id'] ?>" data-district="<?= htmlspecialchars($p['district']) ?>">
                        <?= htmlspecialchars($p['shop_name']) ?> — <?= htmlspecialchars($p['owner_name']) ?>
                        (<?= htmlspecialchars($p['district']) ?>)
                        <?php if (!empty($p['party_code'])): ?> [<?= $p['party_code'] ?>]<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sp-form-group">
            <label>Gift / Promotional Item *</label>
            <select name="gift_item_id" class="sp-select" id="giftItemSelect" onchange="toggleCustomItem()">
                <option value="0">— Select Item —</option>
                <?php foreach ($giftItems as $gi): ?>
                    <option value="<?= $gi['id'] ?>" data-value="<?= $gi['default_value'] ?>"><?= htmlspecialchars($gi['name']) ?>
                        <?= $gi['default_value'] > 0 ? ' (₹' . number_format($gi['default_value'], 0) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
                <option value="-1">— Custom Item —</option>
            </select>
        </div>

        <div class="sp-form-group" id="customItemGroup" style="display:none;">
            <label>Custom Item Name *</label>
            <input type="text" name="custom_item_name" class="sp-input" placeholder="Enter item name">
        </div>

        <div class="sp-form-row">
            <div class="sp-form-group">
                <label>Quantity</label>
                <input type="number" name="quantity" class="sp-input" value="1" min="1">
            </div>
            <div class="sp-form-group">
                <label>Value (₹)</label>
                <input type="number" name="amount" id="giftAmount" class="sp-input" step="0.01" min="0" value="0" placeholder="0.00">
            </div>
        </div>

        <div class="sp-form-group">
            <label>Notes</label>
            <textarea name="notes" class="sp-textarea" rows="2" placeholder="Any details about this distribution..."></textarea>
        </div>

        <button type="submit" class="sp-btn sp-btn-primary sp-btn-block sp-btn-lg">
            <i class="fas fa-gift"></i> Record Distribution
        </button>
    </form>
</div>

<!-- Filters -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-filter"></i> Filter History</h3>
    </div>

    <!-- District Filter -->
    <div style="margin-bottom:10px;">
        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">DISTRICT</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="<?= sales_base_url('gifts.php?' . http_build_query(array_filter(['date_range' => $dateFilter, 'search' => $searchFilter]))) ?>" class="sp-btn <?= !$districtFilter ? 'sp-btn-primary' : 'sp-btn-outline' ?> sp-btn-sm">All</a>
            <?php foreach ($districts as $d): ?>
                <a href="<?= sales_base_url('gifts.php?' . http_build_query(array_filter(['district' => $d['district'], 'date_range' => $dateFilter, 'search' => $searchFilter]))) ?>"
                   class="sp-btn <?= $districtFilter === $d['district'] ? 'sp-btn-primary' : 'sp-btn-outline' ?> sp-btn-sm">
                    <?= htmlspecialchars($d['district']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div style="margin-bottom:10px;">
        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">DATE RANGE</div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php
            $dateOptions = ['' => 'All Time', 'today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'last_month' => 'Last Month'];
            foreach ($dateOptions as $dv => $dl): ?>
                <a href="<?= sales_base_url('gifts.php?' . http_build_query(array_filter(['district' => $districtFilter, 'date_range' => $dv, 'search' => $searchFilter]))) ?>"
                   class="sp-btn <?= $dateFilter === $dv ? 'sp-btn-primary' : 'sp-btn-outline' ?> sp-btn-sm">
                    <?= $dl ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Party Search -->
    <div>
        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">SEARCH BY PARTY</div>
        <form method="GET" style="display:flex;gap:6px;">
            <?php if ($districtFilter): ?><input type="hidden" name="district" value="<?= htmlspecialchars($districtFilter) ?>"><?php endif; ?>
            <?php if ($dateFilter): ?><input type="hidden" name="date_range" value="<?= htmlspecialchars($dateFilter) ?>"><?php endif; ?>
            <input type="text" name="search" class="sp-input" value="<?= htmlspecialchars($searchFilter) ?>" placeholder="Shop name or owner..." style="flex:1;">
            <button type="submit" class="sp-btn sp-btn-primary sp-btn-sm"><i class="fas fa-search"></i></button>
            <?php if ($searchFilter): ?>
                <a href="<?= sales_base_url('gifts.php?' . http_build_query(array_filter(['district' => $districtFilter, 'date_range' => $dateFilter]))) ?>" class="sp-btn sp-btn-outline sp-btn-sm"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Distribution History -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-history"></i> Distribution History</h3>
    </div>
    <?php if (empty($distributions)): ?>
        <div class="sp-empty">
            <i class="fas fa-gift"></i>
            <h3>No distributions yet</h3>
            <p>Record your first gift distribution above.</p>
        </div>
    <?php else: ?>
        <div style="font-size:11px;color:#6b7280;margin-bottom:8px;padding:0 4px;">Showing <?= count($distributions) ?> record(s)</div>
        <?php foreach ($distributions as $dist): ?>
        <div style="padding:12px 0;border-bottom:1px solid #f3f4f6;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:14px;">
                        <i class="fas fa-gift" style="color:#7c3aed;"></i>
                        <?= htmlspecialchars($dist['gift_name'] ?? $dist['custom_item_name'] ?? 'Item') ?>
                        <?php if ($dist['quantity'] > 1): ?>
                            <span style="font-size:12px;color:#6b7280;">×<?= $dist['quantity'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:13px;color:#374151;margin-top:2px;">
                        <i class="fas fa-store" style="width:14px;color:#6b7280;"></i>
                        <?= htmlspecialchars($dist['shop_name']) ?> — <?= htmlspecialchars($dist['owner_name']) ?>
                    </div>
                    <div style="font-size:11px;color:#6b7280;margin-top:2px;">
                        <i class="fas fa-map-marker-alt" style="width:14px;"></i> <?= htmlspecialchars($dist['district'] ?? '') ?>
                        · <?= date('d M Y, h:i A', strtotime($dist['created_at'])) ?>
                    </div>
                    <?php if ($dist['notes']): ?>
                        <div style="font-size:11px;color:#6b7280;margin-top:2px;font-style:italic;">
                            <i class="fas fa-sticky-note" style="width:14px;"></i> <?= htmlspecialchars($dist['notes']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;margin-left:10px;">
                    <?php if ($dist['amount'] > 0): ?>
                    <div style="font-weight:700;color:#7c3aed;">
                        ₹<?= number_format($dist['amount'] * $dist['quantity'], 0) ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?= sales_base_url('gifts.php?delete_id=' . $dist['id']) ?>" onclick="return confirm('Delete this distribution record?')" style="font-size:11px;color:#dc2626;text-decoration:none;" title="Delete">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleCustomItem() {
    var sel = document.getElementById('giftItemSelect');
    var val = parseInt(sel.value);
    document.getElementById('customItemGroup').style.display = val === -1 ? 'block' : 'none';

    // Auto-fill amount from default_value
    if (val > 0) {
        var opt = sel.options[sel.selectedIndex];
        var defVal = parseFloat(opt.dataset.value || 0);
        if (defVal > 0) document.getElementById('giftAmount').value = defVal;
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
