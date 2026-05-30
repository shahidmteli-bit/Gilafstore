<?php
/**
 * Admin Panel - Expense Policy Settings & Category Management
 * Manage expense categories, limits, and policy rules
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
require_once __DIR__ . '/../sales-portal/includes/expense_setup.php';

$pageTitle = 'Expense Settings';
$adminPage = 'sales_expense_settings';

// Helper to get/set expense settings
function getExpSetting($key, $default = '') {
    try {
        $row = db_fetch('SELECT setting_value FROM sales_expense_settings WHERE setting_key = ?', [$key]);
        return $row ? $row['setting_value'] : $default;
    } catch (PDOException $e) { return $default; }
}

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settingsToSave = [
        'require_approval' => isset($_POST['require_approval']) ? '1' : '0',
        'notes_mandatory' => isset($_POST['notes_mandatory']) ? '1' : '0',
        'attachment_mandatory_above' => max(0, (float)($_POST['attachment_mandatory_above'] ?? 0)),
        'daily_limit_enabled' => isset($_POST['daily_limit_enabled']) ? '1' : '0',
        'daily_limit_amount' => max(0, (float)($_POST['daily_limit_amount'] ?? 0)),
        'monthly_limit_enabled' => isset($_POST['monthly_limit_enabled']) ? '1' : '0',
        'monthly_limit_amount' => max(0, (float)($_POST['monthly_limit_amount'] ?? 0)),
    ];

    foreach ($settingsToSave as $key => $value) {
        try {
            $exists = db_fetch('SELECT id FROM sales_expense_settings WHERE setting_key = ?', [$key]);
            if ($exists) {
                db_query('UPDATE sales_expense_settings SET setting_value = ? WHERE setting_key = ?', [(string)$value, $key]);
            } else {
                db_query('INSERT INTO sales_expense_settings (setting_key, setting_value) VALUES (?, ?)', [$key, (string)$value]);
            }
        } catch (PDOException $e) {}
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Expense policy settings saved.'];
    header('Location: ' . base_url('admin/sales_expense_settings.php'));
    exit;
}

// Handle category update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $catId = (int)($_POST['cat_id'] ?? 0);
    $catName = trim($_POST['cat_name'] ?? '');
    $catIcon = trim($_POST['cat_icon'] ?? 'fas fa-receipt');
    $catMaxDay = ($_POST['cat_max_day'] !== '' && $_POST['cat_max_day'] !== null) ? max(0, (float)$_POST['cat_max_day']) : null;
    $catMaxMonth = ($_POST['cat_max_month'] !== '' && $_POST['cat_max_month'] !== null) ? max(0, (float)$_POST['cat_max_month']) : null;
    $catReqAttach = isset($_POST['cat_require_attachment']) ? 1 : 0;
    $catReqNotes = isset($_POST['cat_require_notes']) ? 1 : 0;
    $catActive = isset($_POST['cat_active']) ? 1 : 0;

    if ($catId > 0 && $catName) {
        db_query('UPDATE sales_expense_categories SET name = ?, icon = ?, max_per_day = ?, max_per_month = ?, require_attachment = ?, require_notes = ?, is_active = ? WHERE id = ?', [
            $catName, $catIcon, $catMaxDay, $catMaxMonth, $catReqAttach, $catReqNotes, $catActive, $catId
        ]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category "' . $catName . '" updated.'];
    }
    header('Location: ' . base_url('admin/sales_expense_settings.php'));
    exit;
}

// Handle new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $catName = trim($_POST['new_cat_name'] ?? '');
    $catSlug = strtolower(preg_replace('/[^a-z0-9]+/', '_', $catName));
    $catIcon = trim($_POST['new_cat_icon'] ?? 'fas fa-receipt');

    if ($catName && $catSlug) {
        try {
            $maxOrder = db_fetch('SELECT MAX(sort_order) as mx FROM sales_expense_categories')['mx'] ?? 0;
            db_query('INSERT INTO sales_expense_categories (name, slug, icon, sort_order, is_active) VALUES (?, ?, ?, ?, 1)', [
                $catName, $catSlug, $catIcon, $maxOrder + 1
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Category "' . $catName . '" added.'];
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    header('Location: ' . base_url('admin/sales_expense_settings.php'));
    exit;
}

// Load current settings
$settings = [
    'require_approval' => getExpSetting('require_approval', '1'),
    'notes_mandatory' => getExpSetting('notes_mandatory', '1'),
    'attachment_mandatory_above' => getExpSetting('attachment_mandatory_above', '500'),
    'daily_limit_enabled' => getExpSetting('daily_limit_enabled', '0'),
    'daily_limit_amount' => getExpSetting('daily_limit_amount', '0'),
    'monthly_limit_enabled' => getExpSetting('monthly_limit_enabled', '0'),
    'monthly_limit_amount' => getExpSetting('monthly_limit_amount', '0'),
];

// Load categories
$categories = [];
try { $categories = db_fetch_all('SELECT * FROM sales_expense_categories ORDER BY sort_order ASC, name ASC'); } catch (PDOException $e) {}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-cog me-2"></i>Expense Policy & Settings</h2>
            <p class="text-muted mb-0">Configure expense categories, limits, and approval policies</p>
        </div>
        <a href="<?= base_url('admin/sales_expenses.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Expenses</a>
    </div>

    <div class="row g-4">
        <!-- Policy Settings -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2 text-primary"></i>Global Policy</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="save_settings" value="1">

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="require_approval" id="reqApproval" <?= $settings['require_approval'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="reqApproval">Require approval before marking as reimbursed</label>
                            </div>
                            <small class="text-muted">If enabled, all expenses need admin approval</small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="notes_mandatory" id="notesMandatory" <?= $settings['notes_mandatory'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="notesMandatory">Notes/remarks mandatory</label>
                            </div>
                            <small class="text-muted">Salesperson must provide a description</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Require receipt/bill for expenses above (₹)</label>
                            <input type="number" name="attachment_mandatory_above" class="form-control" value="<?= $settings['attachment_mandatory_above'] ?>" min="0" step="50">
                            <small class="text-muted">Set to 0 to never require, or an amount threshold</small>
                        </div>

                        <hr>
                        <h6 class="fw-bold mb-3"><i class="fas fa-tachometer-alt me-1"></i> Global Limits (All Categories)</h6>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="daily_limit_enabled" id="dailyLimitEn" <?= $settings['daily_limit_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="dailyLimitEn">Enable global daily limit</label>
                            </div>
                            <input type="number" name="daily_limit_amount" class="form-control mt-1" value="<?= $settings['daily_limit_amount'] ?>" min="0" step="50" placeholder="Max per day (₹)">
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="monthly_limit_enabled" id="monthlyLimitEn" <?= $settings['monthly_limit_enabled'] === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="monthlyLimitEn">Enable global monthly limit</label>
                            </div>
                            <input type="number" name="monthly_limit_amount" class="form-control mt-1" value="<?= $settings['monthly_limit_amount'] ?>" min="0" step="100" placeholder="Max per month (₹)">
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Policy Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Expense Categories -->
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tags me-2 text-primary"></i>Expense Categories</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="fas fa-plus me-1"></i>Add</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th>Day Limit</th>
                                    <th>Month Limit</th>
                                    <th>Rules</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <i class="<?= htmlspecialchars($cat['icon']) ?> me-1 text-muted"></i>
                                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                    </td>
                                    <td class="small"><?= $cat['max_per_day'] ? '₹' . number_format($cat['max_per_day'], 0) : '—' ?></td>
                                    <td class="small"><?= $cat['max_per_month'] ? '₹' . number_format($cat['max_per_month'], 0) : '—' ?></td>
                                    <td class="small">
                                        <?php if ($cat['require_attachment']): ?><span class="badge bg-light text-dark border me-1">Receipt req.</span><?php endif; ?>
                                        <?php if ($cat['require_notes']): ?><span class="badge bg-light text-dark border">Notes req.</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cat['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-outline-secondary btn-sm py-0 px-1" data-bs-toggle="modal" data-bs-target="#editCat<?= $cat['id'] ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <!-- Edit Category Modal -->
                                        <div class="modal fade" id="editCat<?= $cat['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <input type="hidden" name="save_category" value="1">
                                                        <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
                                                        <div class="modal-header">
                                                            <h6 class="modal-title"><i class="fas fa-edit me-1"></i> Edit: <?= htmlspecialchars($cat['name']) ?></h6>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row g-2">
                                                                <div class="col-8">
                                                                    <label class="form-label fw-semibold small">Name</label>
                                                                    <input type="text" name="cat_name" class="form-control" value="<?= htmlspecialchars($cat['name']) ?>" required>
                                                                </div>
                                                                <div class="col-4">
                                                                    <label class="form-label fw-semibold small">Icon CSS</label>
                                                                    <input type="text" name="cat_icon" class="form-control" value="<?= htmlspecialchars($cat['icon']) ?>">
                                                                </div>
                                                            </div>
                                                            <div class="row g-2 mt-1">
                                                                <div class="col-6">
                                                                    <label class="form-label fw-semibold small">Max/Day (₹)</label>
                                                                    <input type="number" name="cat_max_day" class="form-control" value="<?= $cat['max_per_day'] ?? '' ?>" min="0" step="50" placeholder="No limit">
                                                                </div>
                                                                <div class="col-6">
                                                                    <label class="form-label fw-semibold small">Max/Month (₹)</label>
                                                                    <input type="number" name="cat_max_month" class="form-control" value="<?= $cat['max_per_month'] ?? '' ?>" min="0" step="100" placeholder="No limit">
                                                                </div>
                                                            </div>
                                                            <div class="mt-3">
                                                                <div class="form-check form-switch mb-2">
                                                                    <input class="form-check-input" type="checkbox" name="cat_require_attachment" <?= $cat['require_attachment'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label small">Require receipt/attachment</label>
                                                                </div>
                                                                <div class="form-check form-switch mb-2">
                                                                    <input class="form-check-input" type="checkbox" name="cat_require_notes" <?= $cat['require_notes'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label small">Require notes</label>
                                                                </div>
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" name="cat_active" <?= $cat['is_active'] ? 'checked' : '' ?>>
                                                                    <label class="form-check-label small">Active (visible to salespersons)</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>Save</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="add_category" value="1">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-plus me-1"></i> Add Expense Category</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Category Name *</label>
                        <input type="text" name="new_cat_name" class="form-control" required placeholder="e.g. Courier / Postage">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Icon (Font Awesome class)</label>
                        <input type="text" name="new_cat_icon" class="form-control" value="fas fa-receipt" placeholder="fas fa-receipt">
                        <small class="text-muted">Browse icons at <a href="https://fontawesome.com/icons" target="_blank">fontawesome.com</a></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
