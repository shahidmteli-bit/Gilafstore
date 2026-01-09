<?php
/**
 * COMPREHENSIVE SHIPPING SETTINGS - ADMIN PANEL
 * Manage zones, methods, rates, and shipping configuration
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Shipping Settings';
$adminPage = 'shipping';

// Check if new shipping system exists
$newSystemExists = false;
try {
    $db = get_db_connection();
    $check = $db->query("SHOW TABLES LIKE 'shipping_zones'");
    $newSystemExists = ($check->rowCount() > 0);
} catch (Exception $e) {
    $newSystemExists = false;
}

// Get all data for new system
if ($newSystemExists) {
    $zones = $db->query("SELECT * FROM shipping_zones ORDER BY display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $methods = $db->query("SELECT * FROM shipping_methods ORDER BY display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $weightSlabs = $db->query("SELECT * FROM shipping_weight_slabs ORDER BY min_weight ASC")->fetchAll(PDO::FETCH_ASSOC);
    $freeRules = $db->query("SELECT sfr.*, sz.zone_name FROM shipping_free_rules sfr LEFT JOIN shipping_zones sz ON sfr.zone_id = sz.id ORDER BY priority DESC")->fetchAll(PDO::FETCH_ASSOC);
    $codSettings = $db->query("SELECT scs.*, sz.zone_name FROM shipping_cod_settings scs LEFT JOIN shipping_zones sz ON scs.zone_id = sz.id")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get rates count
    $ratesCount = $db->query("SELECT COUNT(*) FROM shipping_rates")->fetchColumn();
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.shipping-settings-container {
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

.page-subtitle {
    color: #6c757d;
    margin: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #1a3c34;
}

.stat-card.gold {
    border-left-color: #c5a059;
}

.stat-card.blue {
    border-left-color: #3498db;
}

.stat-card.green {
    border-left-color: #27ae60;
}

.stat-number {
    font-size: 36px;
    font-weight: 700;
    color: #1a3c34;
    margin: 0;
}

.stat-label {
    color: #6c757d;
    font-size: 14px;
    margin-top: 8px;
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

.section-title i {
    color: #c5a059;
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

.badge-active {
    background: #d4edda;
    color: #155724;
}

.badge-inactive {
    background: #f8d7da;
    color: #721c24;
}

.badge-zone {
    background: #e3f2fd;
    color: #1565c0;
}

.btn {
    padding: 8px 16px;
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

.btn-sm {
    padding: 4px 12px;
    font-size: 13px;
}

.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
}

.alert-info {
    background: #d1ecf1;
    border: 1px solid #17a2b8;
    color: #0c5460;
}

.setup-instructions {
    background: white;
    padding: 32px;
    border-radius: 12px;
    text-align: center;
}

.setup-instructions i {
    font-size: 64px;
    color: #c5a059;
    margin-bottom: 20px;
}

.setup-instructions h2 {
    color: #1a3c34;
    margin-bottom: 16px;
}

.code-block {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 8px;
    border-left: 4px solid #c5a059;
    margin: 20px 0;
    text-align: left;
    font-family: monospace;
    overflow-x: auto;
}

.quick-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}
</style>

<div class="shipping-settings-container">
    <div class="page-header">
        <h1 class="page-title"><i class="fas fa-shipping-fast"></i> Shipping Settings</h1>
        <p class="page-subtitle">Comprehensive shipping management for worldwide eCommerce</p>
    </div>

    <?php if (!$newSystemExists): ?>
        <!-- Setup Instructions -->
        <div class="setup-instructions">
            <i class="fas fa-database"></i>
            <h2>Shipping System Not Installed</h2>
            <p style="color: #6c757d; margin-bottom: 24px;">
                The comprehensive shipping management system needs to be installed first.
            </p>

            <div class="alert alert-info" style="text-align: left;">
                <strong><i class="fas fa-info-circle"></i> Installation Steps:</strong>
                <ol style="margin: 12px 0 0 20px;">
                    <li>Open phpMyAdmin</li>
                    <li>Select your database (gilaf_store)</li>
                    <li>Go to SQL tab</li>
                    <li>Run the file: <code>shipping_system_schema.sql</code></li>
                    <li>Refresh this page</li>
                </ol>
            </div>

            <div class="code-block">
                <strong>File Location:</strong><br>
                c:\xampp\htdocs\Gilaf Ecommerce website\shipping_system_schema.sql
            </div>

            <div class="quick-actions">
                <a href="shipping_management.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Simple Shipping
                </a>
                <a href="<?= base_url('SHIPPING_SYSTEM_DOCUMENTATION.md') ?>" class="btn btn-primary" target="_blank">
                    <i class="fas fa-book"></i> View Documentation
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- System Overview Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($zones) ?></div>
                <div class="stat-label"><i class="fas fa-globe"></i> Shipping Zones</div>
            </div>
            <div class="stat-card gold">
                <div class="stat-number"><?= count($methods) ?></div>
                <div class="stat-label"><i class="fas fa-truck"></i> Shipping Methods</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-number"><?= count($weightSlabs) ?></div>
                <div class="stat-label"><i class="fas fa-weight"></i> Weight Slabs</div>
            </div>
            <div class="stat-card green">
                <div class="stat-number"><?= $ratesCount ?></div>
                <div class="stat-label"><i class="fas fa-dollar-sign"></i> Configured Rates</div>
            </div>
        </div>

        <!-- Shipping Zones -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-map-marked-alt"></i>
                Shipping Zones
            </h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Zone Name</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zones as $zone): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($zone['zone_name']) ?></strong></td>
                                <td><span class="badge badge-zone"><?= ucfirst($zone['zone_type']) ?></span></td>
                                <td><?= htmlspecialchars($zone['description'] ?? '-') ?></td>
                                <td>
                                    <?php if ($zone['is_active']): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="shipping_zone_edit.php?id=<?= $zone['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shipping Methods -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-shipping-fast"></i>
                Shipping Methods
            </h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Method Name</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($methods as $method): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($method['method_name']) ?></strong></td>
                                <td><code><?= htmlspecialchars($method['method_code']) ?></code></td>
                                <td><?= ucfirst(str_replace('_', ' ', $method['method_type'])) ?></td>
                                <td>
                                    <?php if ($method['is_active']): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="shipping_method_edit.php?id=<?= $method['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Weight Slabs -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-balance-scale"></i>
                Weight Slabs
            </h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Slab Name</th>
                            <th>Weight Range</th>
                            <th>Unit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weightSlabs as $slab): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($slab['slab_name']) ?></strong></td>
                                <td><?= $slab['min_weight'] ?> - <?= $slab['max_weight'] ?> <?= $slab['weight_unit'] ?></td>
                                <td><?= strtoupper($slab['weight_unit']) ?></td>
                                <td>
                                    <?php if ($slab['is_active']): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Free Shipping Rules -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-gift"></i>
                Free Shipping Rules
            </h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Rule Name</th>
                            <th>Zone</th>
                            <th>Min Order Value</th>
                            <th>Exclude International</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($freeRules)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #6c757d;">No free shipping rules configured</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($freeRules as $rule): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($rule['rule_name']) ?></strong></td>
                                    <td><?= $rule['zone_name'] ?? 'All Zones' ?></td>
                                    <td>₹<?= number_format($rule['min_order_value'], 2) ?></td>
                                    <td><?= $rule['exclude_international'] ? 'Yes' : 'No' ?></td>
                                    <td>
                                        <?php if ($rule['is_active']): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- COD Settings -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-money-bill-wave"></i>
                Cash on Delivery (COD) Settings
            </h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th>COD Charge</th>
                            <th>Charge Type</th>
                            <th>Max Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($codSettings)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #6c757d;">No COD settings configured</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($codSettings as $cod): ?>
                                <tr>
                                    <td><?= $cod['zone_name'] ?? 'All Zones' ?></td>
                                    <td>₹<?= number_format($cod['cod_charge'], 2) ?></td>
                                    <td><?= ucfirst($cod['cod_charge_type']) ?></td>
                                    <td><?= $cod['max_cod_amount'] ? '₹' . number_format($cod['max_cod_amount'], 2) : 'No Limit' ?></td>
                                    <td>
                                        <?php if ($cod['is_enabled']): ?>
                                            <span class="badge badge-active">Enabled</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-tools"></i>
                Quick Actions
            </h2>
            <div class="quick-actions">
                <a href="shipping_rates_manage.php" class="btn btn-primary">
                    <i class="fas fa-dollar-sign"></i> Manage Shipping Rates
                </a>
                <a href="<?= base_url('SHIPPING_SYSTEM_DOCUMENTATION.md') ?>" class="btn btn-secondary" target="_blank">
                    <i class="fas fa-book"></i> View Documentation
                </a>
                <a href="shipping_management.php" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> Legacy Settings
                </a>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
