<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header('Location: admin_login.php');
    exit();
}

$adminId = $_SESSION['user']['id'];

// GST Configuration Class
class GSTConfiguration {
    private $db;
    private $adminId;
    
    public function __construct($database, $adminId) {
        $this->db = $database;
        $this->adminId = $adminId;
    }
    
    // Get GST configuration for entity
    public function getGSTConfig($entityType, $entityId, $date = null) {
        $date = $date ?? date('Y-m-d H:i:s');
        
        $query = "SELECT * FROM gst_configuration 
                  WHERE entity_type = ? AND entity_id = ? 
                  AND effective_from <= ? 
                  AND (effective_to IS NULL OR effective_to >= ?)
                  AND status = 'active'
                  ORDER BY effective_from DESC LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('siss', $entityType, $entityId, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    // Set GST configuration
    public function setGSTConfig($entityType, $entityId, $gstSlab, $hsnCode = null, $cessRate = 0, $isExempt = false, $effectiveFrom = null, $effectiveTo = null) {
        $effectiveFrom = $effectiveFrom ?? date('Y-m-d H:i:s');
        $isExempt = $isExempt ? 1 : 0;
        
        // Check if HSN code already exists (if provided)
        if (!empty($hsnCode)) {
            $checkQuery = "SELECT id, entity_type, entity_id FROM gst_configuration 
                          WHERE hsn_code = ? AND status = 'active'";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bind_param('s', $hsnCode);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                $existing = $result->fetch_assoc();
                throw new Exception("HSN Code '{$hsnCode}' is already assigned to another {$existing['entity_type']} (ID: {$existing['entity_id']}). Each HSN code must be unique.");
            }
        }
        
        // Deactivate previous configurations
        $this->deactivatePreviousConfigs($entityType, $entityId, $effectiveFrom);
        
        $query = "INSERT INTO gst_configuration 
                  (entity_type, entity_id, gst_slab, hsn_code, cess_rate, is_exempt, effective_from, effective_to, created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            error_log("GST Config Prepare Error: " . $this->db->error);
            throw new Exception("Database prepare error: " . $this->db->error);
        }
        
        $stmt->bind_param('sidddsiis', $entityType, $entityId, $gstSlab, $hsnCode, $cessRate, $isExempt, $effectiveFrom, $effectiveTo, $this->adminId);
        
        if ($stmt->execute()) {
            try {
                $this->logAuditTrail('create', 'gst_configuration', $this->db->insert_id, null, [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'gst_slab' => $gstSlab,
                    'hsn_code' => $hsnCode,
                    'cess_rate' => $cessRate,
                    'is_exempt' => $isExempt,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => $effectiveTo
                ]);
            } catch (Exception $e) {
                error_log("Audit trail error: " . $e->getMessage());
                // Continue even if audit trail fails
            }
            return true;
        }
        
        error_log("GST Config Execute Error: " . $stmt->error);
        throw new Exception("Database execute error: " . $stmt->error);
    }
    
    // Deactivate previous configurations
    private function deactivatePreviousConfigs($entityType, $entityId, $effectiveFrom) {
        $query = "UPDATE gst_configuration 
                  SET effective_to = ?, status = 'inactive', updated_by = ?, updated_at = NOW() 
                  WHERE entity_type = ? AND entity_id = ? 
                  AND effective_from < ? AND (effective_to IS NULL OR effective_to >= ?)";
        
        $stmt = $this->db->prepare($query);
        $deactivationDate = date('Y-m-d H:i:s', strtotime($effectiveFrom) - 1);
        $stmt->bind_param('sissss', $deactivationDate, $this->adminId, $entityType, $entityId, $effectiveFrom, $effectiveFrom);
        $stmt->execute();
    }
    
    // Get all GST configurations
    public function getAllGSTConfigurations($entityType = null, $status = 'active') {
        // First check if gst_configuration table exists
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'gst_configuration'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return []; // Table doesn't exist yet, return empty array
        }
        
        $query = "SELECT gc.*, 
                         CASE 
                             WHEN gc.entity_type = 'category' THEN c.name 
                             WHEN gc.entity_type = 'product' THEN p.name 
                             ELSE 'Unknown' 
                         END as entity_name 
                  FROM gst_configuration gc 
                  LEFT JOIN categories c ON gc.entity_type = 'category' AND gc.entity_id = c.id 
                  LEFT JOIN products p ON gc.entity_type = 'product' AND gc.entity_id = p.id";
        
        $conditions = [];
        $params = [];
        $types = '';
        
        if ($entityType) {
            $conditions[] = "gc.entity_type = ?";
            $params[] = $entityType;
            $types .= 's';
        }
        
        if ($status) {
            $conditions[] = "gc.status = ?";
            $params[] = $status;
            $types .= 's';
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $query .= " ORDER BY gc.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            // Query preparation failed, return empty array
            error_log("GST Configuration query failed: " . $this->db->error);
            return [];
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
    // Log audit trail
    private function logAuditTrail($action, $table, $recordId, $oldValues, $newValues) {
        $query = "INSERT INTO gst_audit_trail 
                  (action_type, table_name, record_id, old_values, new_values, changed_by, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $oldJson = $oldValues ? json_encode($oldValues) : null;
        $newJson = $newValues ? json_encode($newValues) : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('sssissss', $action, $table, $recordId, $oldJson, $newJson, $this->adminId, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    // Get GST settings
    public function getGSTSettings() {
        // Check if table exists
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'gst_settings'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return []; // Table doesn't exist yet
        }
        
        $query = "SELECT * FROM gst_settings";
        $result = $this->db->query($query);
        $settings = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        return $settings;
    }
    
    // Update GST settings
    public function updateGSTSetting($key, $value) {
        $query = "UPDATE gst_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('sis', $value, $this->adminId, $key);
        
        if ($stmt->execute()) {
            $this->logAuditTrail('update', 'gst_settings', $key, null, [$key => $value]);
            return true;
        }
        
        return false;
    }
}

// Initialize GST Configuration
$gstConfig = new GSTConfiguration($conn, $adminId);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'set_gst_config':
                $entityType = $_POST['entity_type'];
                $entityId = $_POST['entity_id'];
                $gstSlab = $_POST['gst_slab'];
                $hsnCode = $_POST['hsn_code'] ?? null;
                $cessRate = $_POST['cess_rate'] ?? 0;
                $isExempt = isset($_POST['is_exempt']);
                $effectiveFrom = $_POST['effective_from'] ?? null;
                $effectiveTo = $_POST['effective_to'] ?? null;
                
                try {
                    if ($gstConfig->setGSTConfig($entityType, $entityId, $gstSlab, $hsnCode, $cessRate, $isExempt, $effectiveFrom, $effectiveTo)) {
                        $success = "GST configuration saved successfully!";
                    } else {
                        $error = "Failed to save GST configuration! " . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
                break;
                
            case 'update_settings':
                foreach ($_POST['settings'] as $key => $value) {
                    $gstConfig->updateGSTSetting($key, $value);
                }
                $success = "GST settings updated successfully!";
                break;
        }
    }
}

// Get data for display with error handling
$categories = [];
$categoriesResult = $conn->query("SELECT id, name FROM categories ORDER BY name");
if ($categoriesResult) {
    $categories = $categoriesResult->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Failed to fetch categories: " . $conn->error);
}

$products = [];
$productsResult = $conn->query("SELECT id, name FROM products ORDER BY name");
if ($productsResult) {
    $products = $productsResult->fetch_all(MYSQLI_ASSOC);
} else {
    error_log("Failed to fetch products: " . $conn->error);
}

// Debug: Log the counts
error_log("Categories loaded: " . count($categories));
error_log("Products loaded: " . count($products));

$gstConfigurations = $gstConfig->getAllGSTConfigurations();
$gstSettings = $gstConfig->getGSTSettings();

// Check if GST tables need to be created
$gstTablesExist = $conn->query("SHOW TABLES LIKE 'gst_configuration'")->num_rows > 0;

$pageTitle = 'GST Configuration';
include '../includes/admin_header.php';
?>

<!-- Premium GST Configuration Interface -->
<div class="container-fluid">
    <?php if (!$gstTablesExist): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>GST Database Tables Required</h5>
        <p class="mb-3">The GST Tax Compliance Module requires database tables to be created. Please run the SQL migration to enable all features.</p>
        <hr>
        <p class="mb-2"><strong>Steps to setup:</strong></p>
        <ol class="mb-3">
            <li>Open phpMyAdmin: <code>http://localhost/phpmyadmin</code></li>
            <li>Select database: <strong>ecommerce_db</strong></li>
            <li>Click the <strong>SQL</strong> tab</li>
            <li>Open file: <code>database_gst_schema.sql</code> from project root</li>
            <li>Copy and paste the SQL code</li>
            <li>Click <strong>Go</strong></li>
            <li>Refresh this page</li>
        </ol>
        <a href="gst_debug_test.php" class="btn btn-sm btn-info">
            <i class="fas fa-bug me-2"></i>Run Debug Test
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">GST Configuration</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gstConfigModal" <?php echo !$gstTablesExist ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus me-2"></i>Add GST Configuration
                    </button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#gstSettingsModal" <?php echo !$gstTablesExist ? 'disabled' : ''; ?>>
                        <i class="fas fa-cog me-2"></i>Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GST Configuration Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-gradient-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo count(array_filter($gstConfigurations, fn($c) => $c['entity_type'] == 'category')); ?></h4>
                            <p class="mb-0">Categories</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-tags fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo count(array_filter($gstConfigurations, fn($c) => $c['entity_type'] == 'product')); ?></h4>
                            <p class="mb-0">Products</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-box fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $gstSettings['seller_state'] ?? 'N/A'; ?></h4>
                            <p class="mb-0">Seller State</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-map-marker-alt fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo $gstSettings['auto_calculate_gst'] == 'true' ? 'Enabled' : 'Disabled'; ?></h4>
                            <p class="mb-0">Auto Calculation</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calculator fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GST Configuration Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">GST Configuration List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="gstConfigTable">
                    <thead>
                        <tr>
                            <th>Entity Type</th>
                            <th>Entity Name</th>
                            <th>GST Slab</th>
                            <th>HSN Code</th>
                            <th>Cess Rate</th>
                            <th>Exempt</th>
                            <th>Effective From</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gstConfigurations as $config): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $config['entity_type'] == 'category' ? 'success' : 'info'; ?>">
                                        <?php echo ucfirst($config['entity_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($config['entity_name']); ?></td>
                                <td><?php echo $config['gst_slab']; ?>%</td>
                                <td><?php echo $config['hsn_code'] ?: 'N/A'; ?></td>
                                <td><?php echo $config['cess_rate']; ?>%</td>
                                <td>
                                    <?php if ($config['is_exempt']): ?>
                                        <span class="badge bg-warning">Exempt</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Taxable</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($config['effective_from'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $config['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($config['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-sm edit-gst" 
                                                data-id="<?php echo $config['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm delete-gst" 
                                                data-id="<?php echo $config['id']; ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<!-- GST Configuration Modal -->
<div class="modal fade" id="gstConfigModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add GST Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="set_gst_config">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Entity Type</label>
                                <select class="form-select" name="entity_type" id="entityType" required>
                                    <option value="">Select Entity Type</option>
                                    <option value="category">Category</option>
                                    <option value="product">Product</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Entity</label>
                                <select class="form-select" name="entity_id" id="entitySelect" required>
                                    <option value="">Select Entity Type First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">GST Slab (%)</label>
                                <select class="form-select" name="gst_slab" required>
                                    <option value="">Select GST Slab</option>
                                    <option value="0">0% (Exempt)</option>
                                    <option value="5">5%</option>
                                    <option value="12">12%</option>
                                    <option value="18">18%</option>
                                    <option value="28">28%</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">HSN/SAC Code</label>
                                <input type="text" class="form-control" name="hsn_code" placeholder="Enter HSN/SAC Code">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Cess Rate (%)</label>
                                <input type="number" class="form-control" name="cess_rate" step="0.01" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Effective From</label>
                                <input type="datetime-local" class="form-control" name="effective_from" 
                                       value="<?php echo date('Y-m-d\TH:i'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Effective To (Optional)</label>
                                <input type="datetime-local" class="form-control" name="effective_to">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_exempt" id="isExempt">
                            <label class="form-check-label" for="isExempt">
                                GST Exempt
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- GST Settings Modal -->
<div class="modal fade" id="gstSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">GST Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_settings">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Seller State</label>
                        <input type="text" class="form-control" name="settings[seller_state]" 
                               value="<?php echo htmlspecialchars($gstSettings['seller_state'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller GSTIN</label>
                        <input type="text" class="form-control" name="settings[seller_gstin]" 
                               value="<?php echo htmlspecialchars($gstSettings['seller_gstin'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" class="form-control" name="settings[invoice_prefix]" 
                               value="<?php echo htmlspecialchars($gstSettings['invoice_prefix'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invoice Start Number</label>
                        <input type="number" class="form-control" name="settings[invoice_start]" 
                               value="<?php echo htmlspecialchars($gstSettings['invoice_start'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GST Rounding (Decimal Places)</label>
                        <input type="number" class="form-control" name="settings[gst_rounding]" 
                               value="<?php echo htmlspecialchars($gstSettings['gst_rounding'] ?? '2'); ?>" min="0" max="4">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="settings[auto_calculate_gst]" 
                                   value="true" <?php echo ($gstSettings['auto_calculate_gst'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label">Auto Calculate GST</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="settings[enable_cess]" 
                                   value="true" <?php echo ($gstSettings['enable_cess'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                            <label class="form-check-label">Enable Cess Calculation</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit GST Configuration Modal -->
<div class="modal fade" id="editGSTModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit GST Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editGSTForm">
                <input type="hidden" id="editConfigId" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Entity Type</label>
                        <input type="text" class="form-control" id="editEntityType" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Entity ID</label>
                        <input type="text" class="form-control" id="editEntityId" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GST Slab (%)</label>
                        <input type="number" class="form-control" id="editGstSlab" name="gst_slab" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">HSN Code</label>
                        <input type="text" class="form-control" id="editHsnCode" name="hsn_code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cess Rate (%)</label>
                        <input type="number" class="form-control" id="editCessRate" name="cess_rate" step="0.01">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="editIsExempt" name="is_exempt">
                            <label class="form-check-label">Tax Exempt</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Effective From</label>
                        <input type="datetime-local" class="form-control" id="editEffectiveFrom" name="effective_from">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Effective To</label>
                        <input type="datetime-local" class="form-control" id="editEffectiveTo" name="effective_to">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<!-- DataTables CSS & JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Initialize entity dropdown when DOM is ready
$(document).ready(function() {
    // Store categories and products data
    const categoriesData = <?php echo json_encode($categories); ?>;
    const productsData = <?php echo json_encode($products); ?>;
    
    // Entity type change handler
    $('#entityType').on('change', function() {
        const entityType = $(this).val();
        const entitySelect = $('#entitySelect');
        
        entitySelect.html('<option value="">Loading...</option>');
        
        if (entityType === 'category') {
            let options = '<option value="">Select Category</option>';
            categoriesData.forEach(cat => {
                options += `<option value="${cat.id}">${cat.name}</option>`;
            });
            entitySelect.html(options);
        } else if (entityType === 'product') {
            let options = '<option value="">Select Product</option>';
            productsData.forEach(prod => {
                options += `<option value="${prod.id}">${prod.name}</option>`;
            });
            entitySelect.html(options);
        } else {
            entitySelect.html('<option value="">Select Entity Type First</option>');
        }
    });
});

// Initialize DataTable
$(document).ready(function() {
    $('#gstConfigTable').DataTable({
        responsive: true,
        order: [[6, 'desc']],
        pageLength: 25
    });
});

// Show alerts
<?php if (isset($success)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: '<?php echo $success; ?>',
        timer: 3000
    });
<?php endif; ?>

<?php if (isset($error)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '<?php echo $error; ?>'
    });
<?php endif; ?>

// Edit GST Configuration
$(document).on('click', '.edit-gst', function() {
    const configId = $(this).data('id');
    
    // Fetch configuration data via AJAX
    $.ajax({
        url: 'gst_actions.php',
        method: 'POST',
        data: { action: 'get_config', id: configId },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                // Populate edit modal with data
                $('#editConfigId').val(data.config.id);
                $('#editEntityType').val(data.config.entity_type);
                $('#editEntityId').val(data.config.entity_id);
                $('#editGstSlab').val(data.config.gst_slab);
                $('#editHsnCode').val(data.config.hsn_code);
                $('#editCessRate').val(data.config.cess_rate);
                $('#editIsExempt').prop('checked', data.config.is_exempt == 1);
                $('#editEffectiveFrom').val(data.config.effective_from);
                $('#editEffectiveTo').val(data.config.effective_to);
                
                // Show edit modal
                $('#editGSTModal').modal('show');
            } else {
                Swal.fire('Error', data.message || 'Failed to load configuration', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to fetch configuration data', 'error');
        }
    });
});

// Handle edit form submission
$('#editGSTForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_config');
    
    $.ajax({
        url: 'gst_actions.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                $('#editGSTModal').modal('hide');
                Swal.fire('Success!', data.message, 'success')
                    .then(() => location.reload());
            } else {
                Swal.fire('Error', data.message || 'Failed to update configuration', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to update configuration', 'error');
        }
    });
});

// Delete GST Configuration
$(document).on('click', '.delete-gst', function() {
    const configId = $(this).data('id');
    
    Swal.fire({
        title: 'Are you sure?',
        text: "This will deactivate the GST configuration!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Delete via AJAX
            $.ajax({
                url: 'gst_actions.php',
                method: 'POST',
                data: { action: 'delete_config', id: configId },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        Swal.fire('Deleted!', 'GST configuration has been deactivated.', 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Failed to delete configuration', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete configuration', 'error');
                }
            });
        }
    });
});
</script>
