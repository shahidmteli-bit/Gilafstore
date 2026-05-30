<?php
/**
 * Admin Panel - Sales Executives Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales Executives';
$adminPage = 'sales_executives';

// Handle Actions
$action = $_GET['action'] ?? 'list';

// Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $hasOrders = db_fetch('SELECT COUNT(*) as cnt FROM sales_orders WHERE executive_id = ?', [$id])['cnt'] ?? 0;
    if ($hasOrders > 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cannot delete: executive has existing orders.'];
    } else {
        db_query('DELETE FROM sales_executives WHERE id = ?', [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Executive deleted successfully.'];
    }
    header('Location: ' . base_url('admin/sales_executives.php'));
    exit;
}

// Toggle Active
if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    db_query('UPDATE sales_executives SET is_active = NOT is_active WHERE id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Status updated.'];
    header('Location: ' . base_url('admin/sales_executives.php'));
    exit;
}

// Create / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'designation' => trim($_POST['designation'] ?? 'Sales Executive'),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'district' => trim($_POST['district'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'reporting_manager' => trim($_POST['reporting_manager'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';

    if (empty($data['name']) || empty($data['email']) || empty($data['phone']) || empty($data['district']) || empty($data['location'])) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Please fill all required fields.'];
    } else {
        try {
            if ($id > 0) {
                // Update
                db_query('UPDATE sales_executives SET name=?, designation=?, email=?, phone=?, district=?, location=?, reporting_manager=? WHERE id=?', [
                    $data['name'], $data['designation'], $data['email'], $data['phone'], $data['district'], $data['location'], $data['reporting_manager'], $id
                ]);
                if (!empty($password)) {
                    db_query('UPDATE sales_executives SET password=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Executive updated successfully.'];
            } else {
                // Create
                if (empty($password)) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Password is required for new executives.'];
                    header('Location: ' . base_url('admin/sales_executives.php?action=create'));
                    exit;
                }
                $existing = db_fetch('SELECT id FROM sales_executives WHERE email = ?', [$data['email']]);
                if ($existing) {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email already exists.'];
                    header('Location: ' . base_url('admin/sales_executives.php?action=create'));
                    exit;
                }
                db_query('INSERT INTO sales_executives (name, designation, email, password, phone, district, location, reporting_manager) VALUES (?,?,?,?,?,?,?,?)', [
                    $data['name'], $data['designation'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['phone'], $data['district'], $data['location'], $data['reporting_manager']
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Executive created successfully.'];
            }
            header('Location: ' . base_url('admin/sales_executives.php'));
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Fetch executives
$executives = db_fetch_all('SELECT se.*, (SELECT COUNT(*) FROM sales_orders WHERE executive_id = se.id) as order_count, (SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE executive_id = se.id AND status IN ("approved","dispatched","delivered")) as total_sales FROM sales_executives se ORDER BY se.created_at DESC');

// Edit mode
$editExec = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editExec = db_fetch('SELECT * FROM sales_executives WHERE id = ?', [(int)$_GET['id']]);
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sales Executives</h2>
            <p class="text-muted mb-0">Manage your field sales team</p>
        </div>
        <?php if ($action === 'list'): ?>
        <a href="<?= base_url('admin/sales_executives.php?action=create') ?>" class="btn btn-primary">
            <i class="fas fa-user-plus me-2"></i>Add Executive
        </a>
        <?php endif; ?>
    </div>

    <?php if ($action === 'create' || $action === 'edit'): ?>
    <!-- Create / Edit Form -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-<?= $editExec ? 'edit' : 'user-plus' ?> me-2 text-primary"></i><?= $editExec ? 'Edit Executive' : 'Add New Executive' ?></h5>
            <a href="<?= base_url('admin/sales_executives.php') ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editExec): ?>
                    <input type="hidden" name="id" value="<?= $editExec['id'] ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editExec['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Designation</label>
                        <select name="designation" class="form-select">
                            <option value="Sales Executive" <?= ($editExec['designation'] ?? 'Sales Executive') === 'Sales Executive' ? 'selected' : '' ?>>Sales Executive</option>
                            <option value="Senior Sales Executive" <?= ($editExec['designation'] ?? '') === 'Senior Sales Executive' ? 'selected' : '' ?>>Senior Sales Executive</option>
                            <option value="Area Sales Manager" <?= ($editExec['designation'] ?? '') === 'Area Sales Manager' ? 'selected' : '' ?>>Area Sales Manager</option>
                            <option value="Regional Sales Manager" <?= ($editExec['designation'] ?? '') === 'Regional Sales Manager' ? 'selected' : '' ?>>Regional Sales Manager</option>
                            <option value="Sales Manager" <?= ($editExec['designation'] ?? '') === 'Sales Manager' ? 'selected' : '' ?>>Sales Manager</option>
                            <option value="Territory Manager" <?= ($editExec['designation'] ?? '') === 'Territory Manager' ? 'selected' : '' ?>>Territory Manager</option>
                            <option value="Business Development" <?= ($editExec['designation'] ?? '') === 'Business Development' ? 'selected' : '' ?>>Business Development</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Email *</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($editExec['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password <?= $editExec ? '(leave blank to keep)' : '*' ?></label>
                        <input type="password" name="password" class="form-control" <?= $editExec ? '' : 'required' ?> placeholder="<?= $editExec ? 'Leave blank to keep current' : 'Enter password' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone *</label>
                        <input type="tel" name="phone" class="form-control" required value="<?= htmlspecialchars($editExec['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">District *</label>
                        <input type="text" name="district" class="form-control" required value="<?= htmlspecialchars($editExec['district'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Location *</label>
                        <input type="text" name="location" class="form-control" required value="<?= htmlspecialchars($editExec['location'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Reporting Manager</label>
                        <input type="text" name="reporting_manager" class="form-control" value="<?= htmlspecialchars($editExec['reporting_manager'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= $editExec ? 'Update' : 'Create' ?> Executive</button>
                    <a href="<?= base_url('admin/sales_executives.php') ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- Executives List -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="white-space:nowrap;">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>District</th>
                            <th>Orders</th>
                            <th>Sales</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($executives)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-user-tie fa-3x mb-3 d-block"></i>No sales executives yet. Add your first one.</td></tr>
                        <?php else: ?>
                        <?php foreach ($executives as $ex): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($ex['name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($ex['designation'] ?? 'Sales Executive') ?></small>
                            </td>
                            <td>
                                <div class="small"><?= htmlspecialchars($ex['email']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($ex['phone']) ?></small>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($ex['district']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($ex['location']) ?></small>
                            </td>
                            <td><span class="badge bg-primary"><?= $ex['order_count'] ?></span></td>
                            <td class="fw-bold text-success">₹<?= number_format($ex['total_sales'], 0) ?></td>
                            <td>
                                <?php if ($ex['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= $ex['last_login'] ? date('d M Y, h:i A', strtotime($ex['last_login'])) : 'Never' ?></td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="<?= base_url('admin/sales_executives.php?action=edit&id=' . $ex['id']) ?>" class="btn btn-outline-primary btn-sm" title="Edit"><i class="fas fa-edit"></i></a>
                                    <a href="<?= base_url('admin/sales_executives.php?action=toggle&id=' . $ex['id']) ?>" class="btn btn-outline-<?= $ex['is_active'] ? 'warning' : 'success' ?> btn-sm" title="<?= $ex['is_active'] ? 'Deactivate' : 'Activate' ?>"><i class="fas fa-<?= $ex['is_active'] ? 'ban' : 'check' ?>"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
