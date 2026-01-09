<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Edit Shipping Zone';
$adminPage = 'shipping';

$db = get_db_connection();
$zoneId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zoneName = trim($_POST['zone_name']);
    $zoneType = $_POST['zone_type'];
    $description = trim($_POST['description']);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $displayOrder = (int)$_POST['display_order'];
    
    $stmt = $db->prepare("
        UPDATE shipping_zones 
        SET zone_name = ?, zone_type = ?, description = ?, is_active = ?, display_order = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$zoneName, $zoneType, $description, $isActive, $displayOrder, $zoneId])) {
        $success = 'Shipping zone updated successfully!';
    } else {
        $error = 'Failed to update shipping zone.';
    }
}

$stmt = $db->prepare("SELECT * FROM shipping_zones WHERE id = ?");
$stmt->execute([$zoneId]);
$zone = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$zone) {
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
</style>

<div class="edit-container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-map-marked-alt"></i> Edit Shipping Zone</h1>
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

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Zone Name *</label>
            <input type="text" name="zone_name" class="form-control" value="<?= htmlspecialchars($zone['zone_name']) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Zone Type *</label>
            <select name="zone_type" class="form-select" required>
                <option value="local" <?= $zone['zone_type'] === 'local' ? 'selected' : '' ?>>Local</option>
                <option value="national" <?= $zone['zone_type'] === 'national' ? 'selected' : '' ?>>National</option>
                <option value="regional" <?= $zone['zone_type'] === 'regional' ? 'selected' : '' ?>>Regional</option>
                <option value="international" <?= $zone['zone_type'] === 'international' ? 'selected' : '' ?>>International</option>
                <option value="remote" <?= $zone['zone_type'] === 'remote' ? 'selected' : '' ?>>Remote</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($zone['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Display Order</label>
            <input type="number" name="display_order" class="form-control" value="<?= $zone['display_order'] ?>" min="0">
        </div>

        <div class="form-group">
            <label class="form-check">
                <input type="checkbox" name="is_active" value="1" <?= $zone['is_active'] ? 'checked' : '' ?>>
                <span>Active (Zone is available for shipping)</span>
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
