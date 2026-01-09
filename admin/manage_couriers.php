<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Courier Companies - Admin';
$adminPage = 'couriers';

// Handle courier actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = get_db_connection();
        
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $code = strtolower(str_replace(' ', '_', trim($_POST['code'])));
            $tracking_url = trim($_POST['tracking_url']);
            $display_order = (int)($_POST['display_order'] ?? 0);
            
            $stmt = $db->prepare("INSERT INTO courier_companies (name, code, tracking_url_pattern, display_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $code, $tracking_url, $display_order]);
            
            $_SESSION['message'] = 'Courier company added successfully';
            $_SESSION['message_type'] = 'success';
            
        } elseif ($action === 'edit') {
            $id = (int)$_POST['courier_id'];
            $name = trim($_POST['name']);
            $tracking_url = trim($_POST['tracking_url']);
            $display_order = (int)($_POST['display_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE courier_companies SET name = ?, tracking_url_pattern = ?, display_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $tracking_url, $display_order, $is_active, $id]);
            
            $_SESSION['message'] = 'Courier company updated successfully';
            $_SESSION['message_type'] = 'success';
            
        } elseif ($action === 'delete') {
            $id = (int)$_POST['courier_id'];
            
            $stmt = $db->prepare("DELETE FROM courier_companies WHERE id = ?");
            $stmt->execute([$id]);
            
            $_SESSION['message'] = 'Courier company deleted successfully';
            $_SESSION['message_type'] = 'success';
        }
        
        header('Location: manage_couriers.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// Get all courier companies
$db = get_db_connection();
$stmt = $db->query("SELECT * FROM courier_companies ORDER BY display_order ASC, name ASC");
$couriers = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    background-attachment: fixed;
    min-height: 100vh;
}

.courier-header {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    padding: 28px 32px;
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    animation: slideDown 0.6s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.courier-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.courier-table-container {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(30px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.15);
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.18);
    animation: slideUp 0.6s ease 0.2s backwards;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.courier-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.courier-table thead {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%);
    backdrop-filter: blur(10px);
}

.courier-table thead th {
    padding: 18px 24px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.courier-table tbody td {
    padding: 20px 24px;
    font-size: 14px;
    color: #ffffff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.courier-table tbody tr:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: scale(1.01);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.btn-glass {
    padding: 10px 18px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    color: white;
}

.btn-glass:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.btn-primary-glass {
    background: rgba(59, 130, 246, 0.3);
}

.btn-success-glass {
    background: rgba(16, 185, 129, 0.3);
}

.btn-danger-glass {
    background: rgba(239, 68, 68, 0.3);
}

.badge-active {
    background: rgba(16, 185, 129, 0.3);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.badge-inactive {
    background: rgba(239, 68, 68, 0.3);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.modal-glass {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.3);
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="courier-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1>📦 Courier Companies</h1>
            <button class="btn-glass btn-primary-glass" data-bs-toggle="modal" data-bs-target="#addCourierModal">
                <i class="fas fa-plus"></i> ADD COURIER
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert" style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); color: white;">
            <?= htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="courier-table-container">
        <table class="courier-table">
            <thead>
                <tr>
                    <th>COURIER NAME</th>
                    <th>CODE</th>
                    <th>TRACKING URL PATTERN</th>
                    <th>ORDER</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($couriers)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="fas fa-shipping-fast fa-3x mb-3 d-block"></i>
                            No courier companies added yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($couriers as $courier): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($courier['name']); ?></strong></td>
                            <td><code style="background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 6px;"><?= htmlspecialchars($courier['code']); ?></code></td>
                            <td><small style="word-break: break-all;"><?= htmlspecialchars($courier['tracking_url_pattern']); ?></small></td>
                            <td><?= $courier['display_order']; ?></td>
                            <td>
                                <?php if ($courier['is_active']): ?>
                                    <span class="badge-active">✓ Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">✗ Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn-glass btn-success-glass btn-sm" onclick="editCourier(<?= htmlspecialchars(json_encode($courier)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-glass btn-danger-glass btn-sm" onclick="deleteCourier(<?= $courier['id']; ?>, '<?= htmlspecialchars($courier['name']); ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Courier Modal -->
<div class="modal fade" id="addCourierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-glass">
            <div class="modal-header">
                <h5 class="modal-title">Add Courier Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Courier Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., India Post">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code *</label>
                        <input type="text" name="code" class="form-control" required placeholder="e.g., india_post">
                        <small class="text-muted">Lowercase, no spaces (use underscores)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tracking URL Pattern *</label>
                        <input type="text" name="tracking_url" class="form-control" required placeholder="https://example.com/track?id={TN}">
                        <small class="text-muted">Use {TN} as placeholder for tracking number</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Courier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Courier Modal -->
<div class="modal fade" id="editCourierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content modal-glass">
            <div class="modal-header">
                <h5 class="modal-title">Edit Courier Company</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCourierForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="courier_id" id="editCourierId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Courier Name *</label>
                        <input type="text" name="name" id="editCourierName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tracking URL Pattern *</label>
                        <input type="text" name="tracking_url" id="editCourierUrl" class="form-control" required>
                        <small class="text-muted">Use {TN} as placeholder for tracking number</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" id="editCourierOrder" class="form-control" min="0">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" name="is_active" id="editCourierActive" class="form-check-input" value="1">
                        <label class="form-check-label" for="editCourierActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Courier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form method="POST" id="deleteCourierForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="courier_id" id="deleteCourierId">
</form>

<script>
function editCourier(courier) {
    document.getElementById('editCourierId').value = courier.id;
    document.getElementById('editCourierName').value = courier.name;
    document.getElementById('editCourierUrl').value = courier.tracking_url_pattern;
    document.getElementById('editCourierOrder').value = courier.display_order;
    document.getElementById('editCourierActive').checked = courier.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('editCourierModal')).show();
}

function deleteCourier(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?\n\nThis will affect all orders using this courier.`)) {
        document.getElementById('deleteCourierId').value = id;
        document.getElementById('deleteCourierForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
