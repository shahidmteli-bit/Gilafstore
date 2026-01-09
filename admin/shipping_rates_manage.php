<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Manage Shipping Rates';
$adminPage = 'shipping';

$db = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_rate'])) {
        $zoneId = (int)$_POST['zone_id'];
        $methodId = (int)$_POST['method_id'];
        $weightSlabId = $_POST['weight_slab_id'] ? (int)$_POST['weight_slab_id'] : null;
        $baseCost = (float)$_POST['base_cost'];
        $perKgCost = (float)$_POST['per_kg_cost'];
        $minDays = (int)$_POST['min_delivery_days'];
        $maxDays = (int)$_POST['max_delivery_days'];
        
        $stmt = $db->prepare("
            INSERT INTO shipping_rates (zone_id, method_id, weight_slab_id, base_cost, per_kg_cost, min_delivery_days, max_delivery_days)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$zoneId, $methodId, $weightSlabId, $baseCost, $perKgCost, $minDays, $maxDays])) {
            $success = 'Shipping rate added successfully!';
        } else {
            $error = 'Failed to add shipping rate. This combination may already exist.';
        }
    } elseif (isset($_POST['delete_rate'])) {
        $rateId = (int)$_POST['rate_id'];
        $stmt = $db->prepare("DELETE FROM shipping_rates WHERE id = ?");
        if ($stmt->execute([$rateId])) {
            $success = 'Shipping rate deleted successfully!';
        }
    }
}

$zones = $db->query("SELECT * FROM shipping_zones WHERE is_active = 1 ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
$methods = $db->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
$weightSlabs = $db->query("SELECT * FROM shipping_weight_slabs WHERE is_active = 1 ORDER BY min_weight")->fetchAll(PDO::FETCH_ASSOC);

$rates = $db->query("
    SELECT sr.*, sz.zone_name, sm.method_name, ws.slab_name
    FROM shipping_rates sr
    JOIN shipping_zones sz ON sr.zone_id = sz.id
    JOIN shipping_methods sm ON sr.method_id = sm.id
    LEFT JOIN shipping_weight_slabs ws ON sr.weight_slab_id = ws.id
    ORDER BY sz.display_order, sm.display_order, ws.min_weight
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.rates-container {
    padding: 24px;
    background: #f8f9fa;
    min-height: 100vh;
}

.page-header {
    background: white;
    padding: 32px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.page-title {
    font-size: 32px;
    font-weight: 700;
    color: #1a3c34;
    margin: 0 0 8px 0;
}

.section-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #1a3c34;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control, .form-select {
    width: 100%;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 14px;
}

.form-control:focus, .form-select:focus {
    outline: none;
    border-color: #1a3c34;
}

.btn {
    padding: 10px 20px;
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

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: #f8f9fa;
}

th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

td {
    padding: 12px;
    border-bottom: 1px solid #dee2e6;
}

.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-zone {
    background: #e3f2fd;
    color: #1565c0;
}

.badge-method {
    background: #f3e5f5;
    color: #6a1b9a;
}

.badge-weight {
    background: #fff3e0;
    color: #e65100;
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

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 64px;
    opacity: 0.3;
    margin-bottom: 20px;
}
</style>

<div class="rates-container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-dollar-sign"></i> Manage Shipping Rates</h1>
        <p style="color: #6c757d; margin: 0;">Configure shipping costs for different zones, methods, and weight ranges</p>
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

    <div class="section-card">
        <h2 class="section-title">
            <i class="fas fa-plus-circle"></i>
            Add New Shipping Rate
        </h2>
        
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Shipping Zone *</label>
                    <select name="zone_id" class="form-select" required>
                        <option value="">Select Zone...</option>
                        <?php foreach ($zones as $zone): ?>
                            <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['zone_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Shipping Method *</label>
                    <select name="method_id" class="form-select" required>
                        <option value="">Select Method...</option>
                        <?php foreach ($methods as $method): ?>
                            <option value="<?= $method['id'] ?>"><?= htmlspecialchars($method['method_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Weight Slab</label>
                    <select name="weight_slab_id" class="form-select">
                        <option value="">All Weights</option>
                        <?php foreach ($weightSlabs as $slab): ?>
                            <option value="<?= $slab['id'] ?>"><?= htmlspecialchars($slab['slab_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Base Cost (₹) *</label>
                    <input type="number" name="base_cost" class="form-control" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Per KG Cost (₹)</label>
                    <input type="number" name="per_kg_cost" class="form-control" step="0.01" min="0" value="0">
                </div>

                <div class="form-group">
                    <label class="form-label">Min Delivery Days *</label>
                    <input type="number" name="min_delivery_days" class="form-control" min="1" value="3" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Max Delivery Days *</label>
                    <input type="number" name="max_delivery_days" class="form-control" min="1" value="7" required>
                </div>
            </div>

            <button type="submit" name="add_rate" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Shipping Rate
            </button>
            <a href="shipping_settings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </a>
        </form>
    </div>

    <div class="section-card">
        <h2 class="section-title">
            <i class="fas fa-list"></i>
            Current Shipping Rates
        </h2>

        <?php if (empty($rates)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Shipping Rates Configured</h3>
                <p>Add your first shipping rate using the form above.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th>Method</th>
                            <th>Weight Slab</th>
                            <th>Base Cost</th>
                            <th>Per KG</th>
                            <th>Delivery Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rates as $rate): ?>
                            <tr>
                                <td><span class="badge badge-zone"><?= htmlspecialchars($rate['zone_name']) ?></span></td>
                                <td><span class="badge badge-method"><?= htmlspecialchars($rate['method_name']) ?></span></td>
                                <td>
                                    <?php if ($rate['slab_name']): ?>
                                        <span class="badge badge-weight"><?= htmlspecialchars($rate['slab_name']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">All Weights</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>₹<?= number_format($rate['base_cost'], 2) ?></strong></td>
                                <td>₹<?= number_format($rate['per_kg_cost'], 2) ?></td>
                                <td><?= $rate['min_delivery_days'] ?>-<?= $rate['max_delivery_days'] ?> days</td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this rate?');">
                                        <input type="hidden" name="rate_id" value="<?= $rate['id'] ?>">
                                        <button type="submit" name="delete_rate" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
