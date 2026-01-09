<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Edit Shipping Method';
$adminPage = 'shipping';

$db = get_db_connection();
$methodId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $methodName = trim($_POST['method_name']);
    $methodType = $_POST['method_type'];
    $description = trim($_POST['description']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $displayOrder = (int)$_POST['display_order'];
    
    $stmt = $db->prepare("
        UPDATE shipping_methods 
        SET method_name = ?, method_type = ?, description = ?, is_active = ?, display_order = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$methodName, $methodType, $description, $isActive, $displayOrder, $methodId])) {
        $success = 'Shipping method updated successfully!';
    } else {
        $error = 'Failed to update shipping method.';
    }
}

$stmt = $db->prepare("SELECT * FROM shipping_methods WHERE id = ?");
$stmt->execute([$methodId]);
$method = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$method) {
    header('Location: shipping_settings.php');
    exit;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.edit-container {
    max-width: 800px;
    margin: 24px auto;
    background: white;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.page-header {
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f1f3f5;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1a3c34;
    margin: 0;
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #1a3c34;
}

.form-select {
    width: 100%;
    padding: 12px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
    background: white;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn {
    padding: 12px 24px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #1a3c34;
    color: white;
}

.btn-primary:hover {
    background: #2d5a4d;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #28a745;
    color: #155724;
}

.alert-danger {
    background: #f8d7da;
    border: 1px solid #dc3545;
    color: #721c24;
}

.info-box {
    background: #e7f3ff;
    border: 1px solid #3498db;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #0c5460;
}
</style>

<div class="edit-container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-shipping-fast"></i> Edit Shipping Method</h1>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <strong>Method Code:</strong> <code><?= htmlspecialchars($method['method_code']) ?></code> (Read-only)
    </div>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Method Name *</label>
            <input type="text" name="method_name" class="form-control" value="<?= htmlspecialchars($method['method_name']) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Method Type *</label>
            <select name="method_type" class="form-select" required>
                <option value="standard" <?= $method['method_type'] === 'standard' ? 'selected' : '' ?>>Standard</option>
                <option value="express" <?= $method['method_type'] === 'express' ? 'selected' : '' ?>>Express</option>
                <option value="overnight" <?= $method['method_type'] === 'overnight' ? 'selected' : '' ?>>Overnight</option>
                <option value="local_pickup" <?= $method['method_type'] === 'local_pickup' ? 'selected' : '' ?>>Local Pickup</option>
                <option value="economy" <?= $method['method_type'] === 'economy' ? 'selected' : '' ?>>Economy</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($method['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Display Order</label>
            <input type="number" name="display_order" class="form-control" value="<?= $method['display_order'] ?>" min="0">
        </div>

        <div class="form-group">
            <label class="form-check">
                <input type="checkbox" name="is_active" value="1" <?= $method['is_active'] ? 'checked' : '' ?>>
                <span>Active (Method is available for customers)</span>
            </label>
        </div>

        <div class="btn-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="shipping_settings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
