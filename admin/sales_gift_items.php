<?php
/**
 * Admin Panel - Sales Gift / Promotional Items Management
 * Add, edit, deactivate gift item types. View distribution reports.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Gift Items';
$adminPage = 'sales_gift_items';

$action = $_GET['action'] ?? 'list';

// Handle Create / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $defaultValue = (float)($_POST['default_value'] ?? 0);

    if (empty($name)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Item name is required.'];
    } else {
        try {
            if ($itemId > 0) {
                db_query('UPDATE sales_gift_items SET name = ?, description = ?, default_value = ? WHERE id = ?', [$name, $description, $defaultValue, $itemId]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gift item updated.'];
            } else {
                db_query('INSERT INTO sales_gift_items (name, description, default_value) VALUES (?, ?, ?)', [$name, $description, $defaultValue]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gift item created.'];
            }
            header('Location: ' . base_url('admin/sales_gift_items.php'));
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Handle Toggle Active
if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    db_query('UPDATE sales_gift_items SET is_active = NOT is_active WHERE id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Item status updated.'];
    header('Location: ' . base_url('admin/sales_gift_items.php'));
    exit;
}

// Handle Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $usageCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_gift_distributions WHERE gift_item_id = ?', [$id])['cnt'] ?? 0;
    if ($usageCount > 0) {
        db_query('UPDATE sales_gift_items SET is_active = 0 WHERE id = ?', [$id]);
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Item has ' . $usageCount . ' distribution records. Deactivated instead of deleted.'];
    } else {
        db_query('DELETE FROM sales_gift_items WHERE id = ?', [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Gift item deleted.'];
    }
    header('Location: ' . base_url('admin/sales_gift_items.php'));
    exit;
}

// Fetch all items
$items = db_fetch_all('SELECT gi.*, (SELECT COUNT(*) FROM sales_gift_distributions WHERE gift_item_id = gi.id) as distribution_count, (SELECT COALESCE(SUM(quantity), 0) FROM sales_gift_distributions WHERE gift_item_id = gi.id) as total_qty FROM sales_gift_items gi ORDER BY gi.is_active DESC, gi.name ASC');

// Edit mode
$editItem = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editItem = db_fetch('SELECT * FROM sales_gift_items WHERE id = ?', [(int)$_GET['id']]);
}

// Distribution overview stats
$totalDistributions = db_fetch('SELECT COUNT(*) as cnt FROM sales_gift_distributions')['cnt'] ?? 0;
$totalValue = db_fetch('SELECT COALESCE(SUM(amount * quantity), 0) as t FROM sales_gift_distributions')['t'] ?? 0;
$todayDistributions = db_fetch('SELECT COUNT(*) as cnt FROM sales_gift_distributions WHERE DATE(created_at) = CURDATE()')['cnt'] ?? 0;

// Recent distributions
$districtFilter = trim($_GET['district'] ?? '');
$execFilter = (int)($_GET['exec_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$reportSql = 'SELECT gd.*, sp.shop_name, sp.owner_name, sp.district as party_district, gi.name as gift_name, se.name as exec_name FROM sales_gift_distributions gd JOIN sales_parties sp ON gd.party_id = sp.id LEFT JOIN sales_gift_items gi ON gd.gift_item_id = gi.id JOIN sales_executives se ON gd.executive_id = se.id WHERE 1=1';
$reportParams = [];

if ($districtFilter) {
    $reportSql .= ' AND gd.district = ?';
    $reportParams[] = $districtFilter;
}
if ($execFilter > 0) {
    $reportSql .= ' AND gd.executive_id = ?';
    $reportParams[] = $execFilter;
}
if ($dateFrom) {
    $reportSql .= ' AND DATE(gd.created_at) >= ?';
    $reportParams[] = $dateFrom;
}
if ($dateTo) {
    $reportSql .= ' AND DATE(gd.created_at) <= ?';
    $reportParams[] = $dateTo;
}
$reportSql .= ' ORDER BY gd.created_at DESC LIMIT 100';
$distributions = db_fetch_all($reportSql, $reportParams);

$allExecs = db_fetch_all('SELECT id, name FROM sales_executives WHERE is_active = 1 ORDER BY name ASC');
$allDistricts = db_fetch_all('SELECT DISTINCT district FROM sales_gift_distributions WHERE district != "" ORDER BY district ASC');

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-gift me-2 text-primary"></i>Gift & Promotional Items</h2>
            <p class="text-muted mb-0">Manage gift item types and view distribution reports</p>
        </div>
        <?php if ($action === 'list'): ?>
        <a href="<?= base_url('admin/sales_gift_items.php?action=create') ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Item
        </a>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?= count($items) ?></div>
                    <div class="small">Item Types</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?= $totalDistributions ?></div>
                    <div class="small">Total Distributions</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold">₹<?= number_format($totalValue, 0) ?></div>
                    <div class="small">Total Value</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold"><?= $todayDistributions ?></div>
                    <div class="small">Today</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- Create / Edit Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-<?= $editItem ? 'edit' : 'plus' ?> me-2 text-primary"></i><?= $editItem ? 'Edit Item' : 'Add New Gift Item' ?></h5>
            <a href="<?= base_url('admin/sales_gift_items.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="save_item" value="1">
                <?php if ($editItem): ?>
                    <input type="hidden" name="item_id" value="<?= $editItem['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Item Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editItem['name'] ?? '') ?>" placeholder="e.g. Display Board, Sample Pack...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Default Value (₹)</label>
                        <input type="number" name="default_value" class="form-control" step="0.01" min="0" value="<?= $editItem['default_value'] ?? 0 ?>" placeholder="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($editItem['description'] ?? '') ?>" placeholder="Optional description">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= $editItem ? 'Update' : 'Create' ?> Item</button>
                    <a href="<?= base_url('admin/sales_gift_items.php') ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Items List -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-list me-2 text-success"></i>Gift Item Types</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Default Value</th>
                            <th>Times Distributed</th>
                            <th>Total Qty</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No gift items yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                            <tr class="<?= $item['is_active'] ? '' : 'table-secondary' ?>">
                                <td class="fw-semibold"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($item['description'] ?? '—') ?></td>
                                <td><?= $item['default_value'] > 0 ? '₹' . number_format($item['default_value'], 0) : '—' ?></td>
                                <td><span class="badge bg-primary"><?= $item['distribution_count'] ?></span></td>
                                <td><?= $item['total_qty'] ?></td>
                                <td>
                                    <?php if ($item['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= base_url('admin/sales_gift_items.php?action=edit&id=' . $item['id']) ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?= base_url('admin/sales_gift_items.php?action=toggle&id=' . $item['id']) ?>" class="btn btn-outline-<?= $item['is_active'] ? 'warning' : 'success' ?> btn-sm" title="<?= $item['is_active'] ? 'Deactivate' : 'Activate' ?>"><i class="fas fa-<?= $item['is_active'] ? 'ban' : 'check' ?>"></i></a>
                                    <a href="<?= base_url('admin/sales_gift_items.php?action=delete&id=' . $item['id']) ?>" class="btn btn-outline-danger btn-sm" title="Delete" onclick="return confirm('Delete this item?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Distribution Report -->
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2 text-info"></i>Distribution Report</h5>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" class="row g-2 mb-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Executive</label>
                    <select name="exec_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach ($allExecs as $ae): ?>
                            <option value="<?= $ae['id'] ?>" <?= $execFilter == $ae['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ae['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">District</label>
                    <select name="district" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach ($allDistricts as $ad): ?>
                            <option value="<?= htmlspecialchars($ad['district']) ?>" <?= $districtFilter === $ad['district'] ? 'selected' : '' ?>><?= htmlspecialchars($ad['district']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="<?= base_url('admin/sales_gift_items.php') ?>" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="white-space:nowrap;">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Executive</th>
                            <th>Party</th>
                            <th>District</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Value</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($distributions)): ?>
                            <tr><td colspan="8" class="text-center py-3 text-muted">No distributions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($distributions as $dist): ?>
                            <tr>
                                <td class="small"><?= date('d M Y', strtotime($dist['created_at'])) ?></td>
                                <td class="fw-semibold small"><?= htmlspecialchars($dist['exec_name']) ?></td>
                                <td class="small"><?= htmlspecialchars($dist['shop_name']) ?></td>
                                <td class="small"><?= htmlspecialchars($dist['district'] ?? '') ?></td>
                                <td class="small fw-semibold"><?= htmlspecialchars($dist['gift_name'] ?? $dist['custom_item_name'] ?? '—') ?></td>
                                <td><?= $dist['quantity'] ?></td>
                                <td><?= $dist['amount'] > 0 ? '₹' . number_format($dist['amount'] * $dist['quantity'], 0) : '—' ?></td>
                                <td class="small text-muted"><?= htmlspecialchars($dist['notes'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
