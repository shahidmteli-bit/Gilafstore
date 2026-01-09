<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/batch_functions.php';

require_admin();

// Auto-check for expired batches
check_and_update_expired_batches();

$pageTitle = 'Manage Batch Codes - Admin';
$adminPage = 'batches';

// Get all batch codes
$db = get_db_connection();
$stmt = $db->query("SELECT b.*, p.name as product_name_db FROM batch_codes b LEFT JOIN products p ON b.product_id = p.id ORDER BY b.created_at DESC");
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all products for dropdown
$products = admin_get_products();

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* Clean Professional Background */
body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    background-attachment: fixed;
    min-height: 100vh;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.batch-modern-header {
    background: #ffffff;
    padding: 28px 32px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 24px;
    border: 1px solid #e5e7eb;
    animation: slideDown 0.6s ease;
}

.batch-modern-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
    letter-spacing: -0.5px;
}

.batch-filters {
    background: #ffffff;
    padding: 20px 28px;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
    border: 1px solid #e5e7eb;
    animation: slideDown 0.6s ease 0.1s backwards;
}

.batch-filters select,
.batch-filters input[type="date"] {
    padding: 10px 16px;
    border: 2px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    color: #1f2937;
    background: #ffffff;
    min-width: 160px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.batch-filters select:focus,
.batch-filters input[type="date"]:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.batch-filters select option {
    background: #ffffff;
    color: #1f2937;
}

.batch-table-container {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    border: 1px solid #e5e7eb;
    animation: slideUp 0.6s ease 0.2s backwards;
}

.batch-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.batch-table thead {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.batch-table thead th {
    padding: 18px 24px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: #ffffff;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    border-bottom: none;
}

.batch-table tbody td {
    padding: 20px 24px;
    font-size: 14px;
    color: #1f2937;
    border-bottom: 1px solid #f3f4f6;
    font-weight: 500;
}

.batch-table tbody tr {
    transition: all 0.2s ease;
}

.batch-table tbody tr:last-child td {
    border-bottom: none;
}

.batch-table tbody tr:hover {
    background: #f9fafb;
}
.batch-code-cell {
    font-weight: 700;
    color: #1f2937;
    font-family: 'Courier New', monospace;
    background: #f3f4f6;
    padding: 8px 14px;
    border-radius: 8px;
    display: inline-block;
    border: 2px solid #e5e7eb;
}
.batch-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    border: 2px solid;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.status-active {
    background: #d1fae5;
    color: #065f46;
    border-color: #10b981;
}
.status-paused {
    background: #fef3c7;
    color: #92400e;
    border-color: #f59e0b;
}
.status-blocked {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
}
.batch-action-btn {
    padding: 10px 16px;
    border: 2px solid;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0 4px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.batch-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}
.batch-action-btn:active {
    transform: translateY(0);
}
.btn-play {
    background: #10b981;
    color: white;
    border-color: #059669;
}
.btn-play:hover {
    background: #059669;
}
.btn-block {
    background: #ef4444;
    color: white;
    border-color: #dc2626;
}
.btn-block:hover {
    background: #dc2626;
}
.btn-download {
    background: #3b82f6;
    color: white;
    border-color: #2563eb;
}
.btn-download:hover {
    background: #2563eb;
}
.btn-share {
    background: #8b5cf6;
    color: white;
    border-color: #7c3aed;
}
.btn-share:hover {
    background: #7c3aed;
}
.btn-email {
    background: #6b7280;
    color: white;
    border-color: #4b5563;
}
.btn-email:hover {
    background: #4b5563;
}
.btn-edit {
    background: #ffffff;
    color: #3b82f6;
    border-color: #3b82f6;
}
.btn-edit:hover {
    background: #3b82f6;
    color: white;
}
.btn-delete {
    background: #ef4444;
    color: white;
    border-color: #dc2626;
}
.btn-delete:hover {
    background: #dc2626;
}
.btn-qr {
    background: #f3f4f6;
    color: #1f2937;
    border-color: #d1d5db;
}
.btn-qr:hover {
    background: #e5e7eb;
}
.btn-action-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-btn-group {
    display: flex;
    gap: 10px;
    margin-left: auto;
}
.filter-btn-group button {
    padding: 10px 18px;
    border-radius: 8px;
    border: 2px solid;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.btn-export {
    background: #374151;
    color: white;
    border-color: #1f2937;
}
.btn-export:hover {
    background: #1f2937;
    transform: translateY(-2px);
}
.btn-share {
    background: #3b82f6;
    color: white;
    border-color: #2563eb;
}
.btn-share:hover {
    background: #2563eb;
    transform: translateY(-2px);
}
</style>

<div class="container-fluid px-4 py-4">
    <!-- Modern Header -->
    <div class="batch-modern-header">
        <div class="d-flex justify-content-between align-items-center">
            <h1>Batch History</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal" style="padding: 12px 24px; border-radius: 8px; font-weight: 600; background: #3b82f6; border: 2px solid #2563eb; color: white; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); transition: all 0.2s ease;">
                <i class="fas fa-plus"></i> GENERATE NEW BATCH
            </button>
        </div>
    </div>
    
    <!-- Filters Section -->
    <div class="batch-filters">
        <select id="statusFilter" onchange="filterBatches()">
            <option value="all">All Reports</option>
            <option value="sold_out">Sold Out</option>
            <option value="recalled">Recall</option>
            <option value="blocked">Block</option>
        </select>
        
        <input type="date" id="dateFrom" placeholder="dd-mm-yyyy">
        <span style="color: #9ca3af;">-</span>
        <input type="date" id="dateTo" placeholder="dd-mm-yyyy">
        
        <div class="filter-btn-group">
            <button class="btn-export" onclick="exportBatches()">
                <i class="fas fa-download"></i>
            </button>
            <button class="btn-share" onclick="shareAllBatches()">
                <i class="fas fa-share-alt"></i>
            </button>
        </div>
    </div>

    <!-- Batch Codes Table -->
    <div class="batch-table-container">
        <table class="batch-table">
            <thead>
                <tr>
                    <th>BATCH CODE</th>
                    <th>PRODUCT</th>
                    <th>SPECIFICATION</th>
                    <th>TIMELINE</th>
                    <th>STATUS</th>
                    <th>CERTIFICATIONS</th>
                    <th>QR CODE</th>
                    <th>CONTROLS</th>
                </tr>
            </thead>
            <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-barcode fa-3x mb-3 d-block"></i>
                                    No batch codes generated yet. Click "Generate New Batch" to create one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <!-- Batch Code -->
                                    <td class="batch-code-cell"><?= htmlspecialchars($batch['batch_code']); ?></td>
                                    
                                    <!-- Product -->
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($batch['product_name']); ?></div>
                                        <small style="color: #9ca3af;">ID: <?= $batch['product_id'] ?? 'N/A'; ?></small>
                                    </td>
                                    
                                    <!-- Specification -->
                                    <td>
                                        <div><strong>Grade:</strong> <?= htmlspecialchars($batch['grade'] ?: 'Standard'); ?></div>
                                        <div><strong>Weight:</strong> <?= htmlspecialchars($batch['net_weight']); ?></div>
                                        <small style="color: #9ca3af;"><?= htmlspecialchars($batch['country_of_origin']); ?></small>
                                    </td>
                                    
                                    <!-- Timeline -->
                                    <td>
                                        <div><strong>Mfg:</strong> <?= date('M d, Y', strtotime($batch['manufacturing_date'])); ?></div>
                                        <div><strong>Exp:</strong> <?= date('M d, Y', strtotime($batch['expiry_date'])); ?></div>
                                    </td>
                                    
                                    <!-- Status -->
                                    <td>
                                        <?= get_batch_status_badge($batch['status'] ?? 'production'); ?>
                                    </td>
                                    
                                    <!-- Certifications -->
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 6px;">
                                            <?php if ($batch['is_lab_tested']): ?>
                                                <span style="background: #3b82f6; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; width: fit-content;">
                                                    <i class="fas fa-flask"></i> LAB TESTED
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($batch['is_organic']): ?>
                                                <span style="background: #10b981; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; width: fit-content;">
                                                    <i class="fas fa-leaf"></i> ORGANIC
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($batch['lab_report_url'])): ?>
                                                <a href="<?= base_url('assets/lab_reports/' . $batch['lab_report_url']); ?>" target="_blank" style="background: #8b5cf6; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; width: fit-content; text-decoration: none;">
                                                    <i class="fas fa-file-pdf"></i> LAB REPORT
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!$batch['is_lab_tested'] && !$batch['is_organic'] && empty($batch['lab_report_url'])): ?>
                                                <span style="color: #9ca3af; font-size: 0.75rem;">No certifications</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- QR Code -->
                                    <td>
                                        <button class="batch-action-btn btn-qr" onclick="showQRCode('<?= htmlspecialchars($batch['batch_code']); ?>')" title="View QR Code">
                                            <i class="fas fa-qrcode"></i>
                                        </button>
                                    </td>
                                    
                                    <!-- Controls -->
                                    <td>
                                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                            <?php
                                            // Determine which buttons to show based on status
                                            $status = $batch['status'];
                                            $isActive = ($status === 'released_for_sale' && $batch['is_active'] == 1);
                                            ?>
                                            
                                            <!-- Production Status: Show Quality Approve/Reject -->
                                            <?php if ($status === 'production'): ?>
                                                <button class="btn btn-sm btn-success" onclick="qualityApprove(<?= $batch['id']; ?>)" title="Quality Approve">
                                                    <i class="fas fa-check-circle"></i> Quality Approve
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="qualityReject(<?= $batch['id']; ?>)" title="Quality Reject">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Quality Approved Status: Show Release for Sale -->
                                            <?php if ($status === 'quality_approved'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="releaseForSale(<?= $batch['id']; ?>)" title="Release for Sale">
                                                    <i class="fas fa-rocket"></i> Release for Sale
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Active Status: Show additional actions -->
                                            <?php if ($isActive): ?>
                                                <button class="btn btn-sm btn-dark" onclick="markSoldOut(<?= $batch['id']; ?>)" title="Mark Sold Out">
                                                    <i class="fas fa-box-open"></i> Sold Out
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="recallBatch(<?= $batch['id']; ?>)" title="Recall Batch">
                                                    <i class="fas fa-ban"></i> Recall
                                                </button>
                                                <button class="btn btn-sm btn-secondary" onclick="blockBatch(<?= $batch['id']; ?>)" title="Block Batch">
                                                    <i class="fas fa-lock"></i> Block
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Delete Button: Available at ALL stages -->
                                            <button class="btn btn-sm btn-danger" onclick="deleteBatch(<?= $batch['id']; ?>, '<?= htmlspecialchars($batch['batch_code']); ?>')" title="Delete Batch">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
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
</div>

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--color-green); color: white;">
                <h5 class="modal-title"><i class="fas fa-barcode"></i> Generate New Batch Code</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('admin/batch_actions.php'); ?>" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">C-Code *</label>
                            <select name="category_code" id="categoryCodeSelect" class="form-select" required onchange="loadProductsByCode(); generateBatchCode();">
                                <option value="">Select C-Code...</option>
                                <?php 
                                $categories = admin_get_categories();
                                foreach ($categories as $cat): 
                                    if (!empty($cat['category_code'])):
                                ?>
                                    <option value="<?= htmlspecialchars($cat['category_code']); ?>" data-category-id="<?= $cat['id']; ?>" data-category-name="<?= htmlspecialchars($cat['name']); ?>">
                                        [<?= htmlspecialchars($cat['category_code']); ?>] <?= htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                            <small class="text-muted">Select category code to filter products</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Code (Auto-Generated) *</label>
                            <input type="text" name="batch_code" id="batchCodeDisplay" class="form-control" placeholder="Will be auto-generated" readonly required style="background-color: #f8f9fa; font-weight: 600; color: #059669;">
                            <small class="text-muted">Format: G-[CategoryCode][ProductCode]-[MMYY]-[DD]-[Shift]</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select Product *</label>
                            <select name="product_id" id="productSelect" class="form-select" required onchange="loadProductDetails(); generateBatchCode();">
                                <option value="">First select C-Code...</option>
                            </select>
                            <small class="text-muted">Products filtered by selected C-Code</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Net Weight *</label>
                            <input type="text" name="net_weight" id="netWeightInput" class="form-control" placeholder="Auto-filled from product" readonly required style="background-color: #f8f9fa;">
                            <small class="text-muted">Automatically populated from product</small>
                        </div>
                    </div>
                    <input type="hidden" name="category_id" id="categoryIdHidden" value="">
                    <input type="hidden" name="product_name" id="productNameHidden" value="">
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Manufacturing Date *</label>
                            <input type="date" name="manufacturing_date" id="mfgDateInput" class="form-control" required onchange="generateBatchCode()">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Shift *</label>
                            <select name="shift" id="shiftSelect" class="form-select" required onchange="generateBatchCode()">
                                <option value="">Select Shift...</option>
                                <option value="M">Morning (M)</option>
                                <option value="A">Afternoon (A)</option>
                                <option value="E">Evening (E)</option>
                                <option value="N">Night (N)</option>
                            </select>
                            <small class="text-muted">Select production shift</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expiry Date *</label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Country of Origin</label>
                        <input type="text" name="country_of_origin" class="form-control" value="India (Pampore, Kashmir)">
                    </div>
                    
                    <!-- Batch Lifecycle Fields -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Initial Status *</label>
                            <select name="status" id="batchStatus" class="form-select" required readonly disabled style="background-color: #f8f9fa;">
                                <option value="production" selected>Production</option>
                            </select>
                            <input type="hidden" name="status" value="production">
                            <small class="text-muted">All batches start in Production status</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Units Manufactured</label>
                            <input type="number" name="total_units_manufactured" class="form-control" placeholder="e.g., 1000" min="0">
                            <small class="text-muted">Optional</small>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Units Sold</label>
                            <input type="number" name="units_sold" class="form-control" placeholder="0" value="0" min="0">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Units Remaining</label>
                            <input type="number" name="units_remaining" class="form-control" placeholder="Auto-calculated" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title mb-3"><i class="fas fa-certificate"></i> Certifications & Quality</h6>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_lab_tested" id="isLabTested" value="1">
                                    <label class="form-check-label" for="isLabTested">
                                        <strong>🧪 Lab Tested</strong>
                                        <small class="d-block text-muted">This batch has been laboratory tested</small>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_organic" id="isOrganic" value="1">
                                    <label class="form-check-label" for="isOrganic">
                                        <strong>🌱 Organic Certified</strong>
                                        <small class="d-block text-muted">This batch is certified organic</small>
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="has_lab_report" id="hasLabReportBatch" value="1" onchange="toggleBatchLabReport()">
                                    <label class="form-check-label" for="hasLabReportBatch">
                                        <strong>Upload Lab Report</strong>
                                        <small class="d-block text-muted">Check this to upload or link to a lab test certificate</small>
                                    </label>
                                </div>
                                
                                <div id="batchLabReportSection" style="display:none;">
                                    <div class="mb-3">
                                        <label class="form-label">Upload Lab Report <small class="text-muted">(PDF or JPEG, max 5MB)</small></label>
                                        <input type="file" name="lab_report_file" id="labReportFileBatch" class="form-control" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg">
                                        <small class="text-muted">Supported formats: PDF, JPEG</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Or Lab Report URL <small class="text-muted">(Optional)</small></label>
                                        <input type="url" name="lab_report_url" class="form-control" placeholder="https://...">
                                        <small class="text-muted">Provide a link if file is hosted elsewhere</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" checked>
                        <label class="form-check-label" for="isActive">Active (Verifiable by customers)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Batch Code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Batch Modal -->
<div class="modal fade" id="editBatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--color-green); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Batch Code</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('admin/batch_actions.php'); ?>" method="post" id="editBatchForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="batch_id" id="editBatchId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batch Code *</label>
                            <input type="text" name="batch_code" id="editBatchCode" class="form-control" required readonly>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="product_name" id="editProductName" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Grade</label>
                            <input type="text" name="grade" id="editGrade" class="form-control">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Net Weight *</label>
                            <input type="text" name="net_weight" id="editNetWeight" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manufacturing Date *</label>
                            <input type="date" name="manufacturing_date" id="editMfgDate" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expiry Date *</label>
                            <input type="date" name="expiry_date" id="editExpDate" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Country of Origin</label>
                        <input type="text" name="country_of_origin" id="editCountry" class="form-control">
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="editIsActive">
                        <label class="form-check-label" for="editIsActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Load products filtered by C-Code
function loadProductsByCode() {
    const codeSelect = document.getElementById('categoryCodeSelect');
    const selectedOption = codeSelect.options[codeSelect.selectedIndex];
    const categoryId = selectedOption.getAttribute('data-category-id');
    const productSelect = document.getElementById('productSelect');
    
    // Store category ID in hidden field
    document.getElementById('categoryIdHidden').value = categoryId || '';
    
    // Clear product dropdown
    productSelect.innerHTML = '<option value="">Loading products...</option>';
    
    if (!categoryId) {
        productSelect.innerHTML = '<option value="">First select C-Code...</option>';
        return;
    }
    
    // Fetch products for this category
    fetch('<?= base_url('admin/get_products_by_category.php'); ?>?category_id=' + categoryId)
        .then(response => response.json())
        .then(data => {
            productSelect.innerHTML = '<option value="">Select product...</option>';
            data.products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                option.setAttribute('data-name', product.name);
                option.setAttribute('data-weight', product.net_weight || '');
                productSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading products:', error);
            productSelect.innerHTML = '<option value="">Error loading products</option>';
        });
}

// Load product details when product is selected
function loadProductDetails() {
    const productSelect = document.getElementById('productSelect');
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    
    if (selectedOption.value) {
        const productName = selectedOption.getAttribute('data-name');
        const netWeight = selectedOption.getAttribute('data-weight');
        
        document.getElementById('productNameHidden').value = productName || '';
        document.getElementById('netWeightInput').value = netWeight || '';
    } else {
        document.getElementById('productNameHidden').value = '';
        document.getElementById('netWeightInput').value = '';
    }
}

// Auto-generate Batch Code
function generateBatchCode() {
    // Get all required elements
    const codeSelect = document.getElementById('categoryCodeSelect');
    const productSelect = document.getElementById('productSelect');
    const mfgDateInput = document.getElementById('mfgDateInput');
    const shiftSelect = document.getElementById('shiftSelect');
    const batchCodeDisplay = document.getElementById('batchCodeDisplay');
    
    // Check if all required fields are filled
    if (!codeSelect.value || !productSelect.value || !mfgDateInput.value || !shiftSelect.value) {
        batchCodeDisplay.value = '';
        return;
    }
    
    // Get category name from selected C-Code
    const categoryOption = codeSelect.options[codeSelect.selectedIndex];
    const categoryName = categoryOption.getAttribute('data-category-name') || '';
    const categoryCode = categoryName.charAt(0).toUpperCase(); // First letter of category
    
    // Get product name from selected product
    const productOption = productSelect.options[productSelect.selectedIndex];
    const productName = productOption.getAttribute('data-name') || '';
    const productCode = productName.charAt(0).toUpperCase(); // First letter of product
    
    // Get manufacturing date components
    const mfgDate = new Date(mfgDateInput.value);
    const month = String(mfgDate.getMonth() + 1).padStart(2, '0'); // MM
    const year = String(mfgDate.getFullYear()).slice(-2); // YY (last 2 digits)
    const day = String(mfgDate.getDate()).padStart(2, '0'); // DD
    
    // Get shift code
    const shift = shiftSelect.value;
    
    // Generate batch code: G-[CategoryCode][ProductCode]-[MMYY]-[DD]-[Shift]
    const batchCode = `G-${categoryCode}${productCode}-${month}${year}-${day}-${shift}`;
    
    // Display the generated batch code
    batchCodeDisplay.value = batchCode;
}

function toggleBatchLabReport() {
    const checkbox = document.getElementById('hasLabReportBatch');
    const section = document.getElementById('batchLabReportSection');
    
    if (checkbox.checked) {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
    }
}

// No longer needed - officer names are prompted during approval actions

function editBatch(batch) {
    document.getElementById('editBatchId').value = batch.id;
    document.getElementById('editBatchCode').value = batch.batch_code;
    document.getElementById('editProductName').value = batch.product_name;
    document.getElementById('editGrade').value = batch.grade || '';
    document.getElementById('editNetWeight').value = batch.net_weight;
    document.getElementById('editMfgDate').value = batch.manufacturing_date;
    document.getElementById('editExpDate').value = batch.expiry_date;
    document.getElementById('editCountry').value = batch.country_of_origin;
    document.getElementById('editIsActive').checked = batch.is_active == 1;
    
    new bootstrap.Modal(document.getElementById('editBatchModal')).show();
}

function pauseBatch(id, code) {
    if (confirm(`Pause batch code "${code}"? It will not be verifiable by customers.`)) {
        updateBatchStatus(id, 2, 'paused');
    }
}

function activateBatch(id, code) {
    if (confirm(`Activate batch code "${code}"? It will be verifiable by customers.`)) {
        updateBatchStatus(id, 1, 'activated');
    }
}

function blockBatch(id, code) {
    if (confirm(`Block batch code "${code}"? This will permanently block verification.`)) {
        updateBatchStatus(id, 0, 'blocked');
    }
}

function updateBatchStatus(id, status, action) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= base_url('admin/batch_actions.php'); ?>';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'update_status';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'batch_id';
    idInput.value = id;
    
    const statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'status';
    statusInput.value = status;
    
    form.appendChild(actionInput);
    form.appendChild(idInput);
    form.appendChild(statusInput);
    document.body.appendChild(form);
    form.submit();
}

function downloadReport(id, code) {
    window.open('<?= base_url('admin/batch_report.php'); ?>?id=' + id + '&action=download', '_blank');
}

function shareReport(id, code) {
    const shareUrl = '<?= base_url('verify.php'); ?>?code=' + encodeURIComponent(code);
    
    if (navigator.share) {
        navigator.share({
            title: 'Batch Code: ' + code,
            text: 'Verify this product batch code',
            url: shareUrl
        }).catch(err => console.log('Share failed:', err));
    } else {
        // Fallback: Copy to clipboard
        navigator.clipboard.writeText(shareUrl).then(() => {
            alert('Verification link copied to clipboard!\n\n' + shareUrl);
        }).catch(() => {
            prompt('Copy this verification link:', shareUrl);
        });
    }
}

function emailReport(id, code) {
    const subject = encodeURIComponent('Batch Code Report: ' + code);
    const body = encodeURIComponent('Please find the batch code report for: ' + code + '\n\nVerification Link: <?= base_url('verify.php'); ?>?code=' + code);
    window.location.href = 'mailto:?subject=' + subject + '&body=' + body;
}

function filterBatches() {
    const statusFilter = document.getElementById('statusFilter').value;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    const rows = document.querySelectorAll('.batch-table tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('td[colspan]')) return; // Skip empty state row
        
        let showRow = true;
        
        // Status filter for Reports
        if (statusFilter !== 'all') {
            const statusBadge = row.querySelector('.batch-status-badge');
            const statusText = statusBadge ? statusBadge.textContent.toLowerCase() : '';
            
            if (statusFilter === 'sold_out' && !statusText.includes('sold out')) showRow = false;
            if (statusFilter === 'recalled' && !statusText.includes('recall')) showRow = false;
            if (statusFilter === 'blocked' && !statusText.includes('block')) showRow = false;
        }
        
        row.style.display = showRow ? '' : 'none';
    });
}

function exportBatches() {
    window.location.href = '<?= base_url('admin/batch_export.php'); ?>';
}

function shareAllBatches() {
    const shareUrl = '<?= base_url('admin/manage_batches.php'); ?>';
    
    if (navigator.share) {
        navigator.share({
            title: 'Batch Codes Management',
            text: 'View all product batch codes',
            url: shareUrl
        }).catch(err => console.log('Share failed:', err));
    } else {
        navigator.clipboard.writeText(shareUrl).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}

function showQRCode(code) {
    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent('<?= base_url('verify.php'); ?>?code=' + code);
    
    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(4px);';
    modal.innerHTML = `
        <div style="background:white;padding:40px;border-radius:16px;text-align:center;max-width:450px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h3 style="margin:0 0 8px 0;color:#1a1a1a;font-size:22px;font-weight:600;">QR Code</h3>
            <p style="margin:0 0 24px 0;color:#6b7280;font-size:14px;font-family:'Courier New',monospace;">${code}</p>
            <div style="background:#f9fafb;padding:20px;border-radius:12px;margin-bottom:24px;">
                <img src="${qrUrl}" alt="QR Code" id="qrImage" style="width:300px;height:300px;border-radius:8px;">
            </div>
            <p style="margin:0 0 20px 0;color:#9ca3af;font-size:13px;">
                <i class="fas fa-mobile-alt"></i> Scan to verify batch authenticity
            </p>
            <div style="display:flex;gap:12px;justify-content:center;">
                <button onclick="downloadQRCode('${qrUrl}', '${code}')" style="padding:12px 24px;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:500;font-size:14px;box-shadow:0 2px 8px rgba(59,130,246,0.3);transition:all 0.2s;">
                    <i class="fas fa-download"></i> Download QR
                </button>
                <button onclick="this.closest('div[style*=fixed]').remove()" style="padding:12px 24px;background:white;color:#6b7280;border:2px solid #e5e7eb;border-radius:8px;cursor:pointer;font-weight:500;font-size:14px;transition:all 0.2s;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        </div>
    `;
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
    document.body.appendChild(modal);
}

function downloadQRCode(qrUrl, code) {
    const link = document.createElement('a');
    link.href = qrUrl;
    link.download = 'QR_' + code + '.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function deleteBatch(id, code) {
    if (confirm(`Are you sure you want to delete batch code "${code}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= base_url('admin/batch_actions.php'); ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'batch_id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Batch Lifecycle Action Functions
function qualityApprove(id) {
    // Show cinematic Quality Approval modal
    showQualityApprovalModal(id);
}

function qualityReject(id) {
    const reason = prompt("Enter rejection reason:");
    if (reason && reason.trim()) {
        submitBatchAction(id, 'quality_reject', {reason: reason.trim()});
    } else {
        alert("Rejection reason is required!");
    }
}

function releaseForSale(id) {
    // Show cinematic Release for Sale modal
    showReleaseForSaleModal(id);
}

function markSoldOut(id) {
    if (confirm("Mark this batch as sold out?")) {
        submitBatchAction(id, 'mark_sold_out');
    }
}

function recallBatch(id) {
    const reason = prompt("Enter recall reason:");
    if (!reason || reason.trim() === '') {
        alert("Recall reason is required!");
        return;
    }
    
    const quantity = prompt("Enter recalled quantity (must be greater than 0):");
    if (!quantity || quantity.trim() === '') {
        alert("Recall quantity is required!");
        return;
    }
    
    const quantityNum = parseFloat(quantity);
    if (isNaN(quantityNum) || quantityNum <= 0) {
        alert("Recall quantity must be a positive number greater than 0!");
        return;
    }
    
    submitBatchAction(id, 'recall', {recall_reason: reason.trim(), recall_quantity: quantityNum});
}

function blockBatch(id) {
    const reason = prompt("Enter block reason:");
    if (reason) {
        submitBatchAction(id, 'block', {block_reason: reason});
    }
}

function submitBatchAction(id, action, extraData = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/Gilaf Ecommerce website/admin/batch_actions_lifecycle.php';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'batch_id';
    idInput.value = id;
    form.appendChild(idInput);
    
    // Add extra data fields
    for (const [key, value] of Object.entries(extraData)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}

// Cinematic Quality Approval Modal
function showQualityApprovalModal(batchId) {
    const modal = document.createElement('div');
    modal.id = 'qualityApprovalModal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);animation:fadeIn 0.3s ease;';
    
    modal.innerHTML = `
        <style>
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            .modal-content-cinematic {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 3px;
                border-radius: 24px;
                max-width: 550px;
                width: 90%;
                animation: slideUp 0.4s ease;
                box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            }
            .modal-inner {
                background: white;
                border-radius: 22px;
                padding: 40px;
            }
            .modal-header-cinematic {
                text-align: center;
                margin-bottom: 30px;
            }
            .modal-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            }
            .modal-title {
                font-size: 28px;
                font-weight: 700;
                color: #1a1a1a;
                margin: 0 0 8px 0;
            }
            .modal-subtitle {
                font-size: 14px;
                color: #6b7280;
                margin: 0;
            }
            .form-group-cinematic {
                margin-bottom: 24px;
            }
            .form-label-cinematic {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: #374151;
                margin-bottom: 8px;
            }
            .form-input-cinematic {
                width: 100%;
                padding: 14px 18px;
                border: 2px solid #e5e7eb;
                border-radius: 12px;
                font-size: 15px;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }
            .form-input-cinematic:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            }
            .form-input-cinematic.error {
                border-color: #ef4444;
            }
            .error-message {
                color: #ef4444;
                font-size: 13px;
                margin-top: 6px;
                display: none;
            }
            .button-group {
                display: flex;
                gap: 12px;
                margin-top: 32px;
            }
            .btn-cinematic {
                flex: 1;
                padding: 14px 24px;
                border: none;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .btn-primary-cinematic {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }
            .btn-primary-cinematic:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            }
            .btn-secondary-cinematic {
                background: #f3f4f6;
                color: #6b7280;
            }
            .btn-secondary-cinematic:hover {
                background: #e5e7eb;
            }
        </style>
        <div class="modal-content-cinematic">
            <div class="modal-inner">
                <div class="modal-header-cinematic">
                    <div class="modal-icon">
                        <i class="fas fa-check-circle" style="color: white; font-size: 36px;"></i>
                    </div>
                    <h2 class="modal-title">Quality Approval</h2>
                    <p class="modal-subtitle">Approve this batch for quality standards</p>
                </div>
                
                <form id="qualityApprovalForm">
                    <div class="form-group-cinematic">
                        <label class="form-label-cinematic">Approval Officer Name *</label>
                        <input type="text" id="approvalOfficerName" class="form-input-cinematic" placeholder="Enter officer name (alphabetic only)" required>
                        <div class="error-message" id="officerNameError">Only alphabetic characters are allowed</div>
                    </div>
                    
                    <div class="form-group-cinematic">
                        <label class="form-label-cinematic">Quality Approval Notes</label>
                        <textarea id="approvalNotes" class="form-input-cinematic" rows="4" placeholder="Enter any quality notes (optional)" style="resize: vertical;"></textarea>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn-cinematic btn-secondary-cinematic" onclick="closeQualityApprovalModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-cinematic btn-primary-cinematic">
                            <i class="fas fa-check"></i> Approve Quality
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Form validation and submission
    const form = document.getElementById('qualityApprovalForm');
    const officerInput = document.getElementById('approvalOfficerName');
    const errorMsg = document.getElementById('officerNameError');
    
    // Real-time validation for alphabetic only
    officerInput.addEventListener('input', function() {
        const value = this.value;
        const alphabeticRegex = /^[A-Za-z\s]*$/;
        
        if (!alphabeticRegex.test(value)) {
            this.classList.add('error');
            errorMsg.style.display = 'block';
            // Remove non-alphabetic characters
            this.value = value.replace(/[^A-Za-z\s]/g, '');
        } else {
            this.classList.remove('error');
            errorMsg.style.display = 'none';
        }
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const officerName = officerInput.value.trim();
        const notes = document.getElementById('approvalNotes').value.trim();
        
        if (!officerName) {
            alert('Approval Officer Name is required!');
            return;
        }
        
        const alphabeticRegex = /^[A-Za-z\s]+$/;
        if (!alphabeticRegex.test(officerName)) {
            alert('Officer name must contain only alphabetic characters!');
            return;
        }
        
        closeQualityApprovalModal();
        submitBatchAction(batchId, 'quality_approve', {
            approval_officer_name: officerName,
            notes: notes
        });
    });
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeQualityApprovalModal();
        }
    });
}

function closeQualityApprovalModal() {
    const modal = document.getElementById('qualityApprovalModal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => modal.remove(), 300);
    }
}

// Cinematic Release for Sale Modal
function showReleaseForSaleModal(batchId) {
    const modal = document.createElement('div');
    modal.id = 'releaseForSaleModal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;z-index:10000;backdrop-filter:blur(8px);animation:fadeIn 0.3s ease;';
    
    modal.innerHTML = `
        <style>
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
            .modal-content-cinematic {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                padding: 3px;
                border-radius: 24px;
                max-width: 550px;
                width: 90%;
                animation: slideUp 0.4s ease;
                box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            }
            .modal-inner {
                background: white;
                border-radius: 22px;
                padding: 40px;
            }
            .modal-header-cinematic {
                text-align: center;
                margin-bottom: 30px;
            }
            .modal-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
            }
            .modal-title {
                font-size: 28px;
                font-weight: 700;
                color: #1a1a1a;
                margin: 0 0 8px 0;
            }
            .modal-subtitle {
                font-size: 14px;
                color: #6b7280;
                margin: 0;
            }
            .form-group-cinematic {
                margin-bottom: 24px;
            }
            .form-label-cinematic {
                display: block;
                font-size: 14px;
                font-weight: 600;
                color: #374151;
                margin-bottom: 8px;
            }
            .form-input-cinematic {
                width: 100%;
                padding: 14px 18px;
                border: 2px solid #e5e7eb;
                border-radius: 12px;
                font-size: 15px;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }
            .form-input-cinematic:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            }
            .form-input-cinematic.error {
                border-color: #ef4444;
            }
            .error-message {
                color: #ef4444;
                font-size: 13px;
                margin-top: 6px;
                display: none;
            }
            .button-group {
                display: flex;
                gap: 12px;
                margin-top: 32px;
            }
            .btn-cinematic {
                flex: 1;
                padding: 14px 24px;
                border: none;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .btn-primary-cinematic {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            }
            .btn-primary-cinematic:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
            }
            .btn-secondary-cinematic {
                background: #f3f4f6;
                color: #6b7280;
            }
            .btn-secondary-cinematic:hover {
                background: #e5e7eb;
            }
        </style>
        <div class="modal-content-cinematic">
            <div class="modal-inner">
                <div class="modal-header-cinematic">
                    <div class="modal-icon">
                        <i class="fas fa-rocket" style="color: white; font-size: 36px;"></i>
                    </div>
                    <h2 class="modal-title">Release for Sale</h2>
                    <p class="modal-subtitle">Release this batch to the market</p>
                </div>
                
                <form id="releaseForSaleForm">
                    <div class="form-group-cinematic">
                        <label class="form-label-cinematic">Release Officer Name *</label>
                        <input type="text" id="releaseOfficerName" class="form-input-cinematic" placeholder="Enter officer name (alphabetic only)" required>
                        <div class="error-message" id="releaseOfficerNameError">Only alphabetic characters are allowed</div>
                    </div>
                    
                    <div class="form-group-cinematic">
                        <label class="form-label-cinematic">Release Notes</label>
                        <textarea id="releaseNotes" class="form-input-cinematic" rows="4" placeholder="Enter any release notes (optional)" style="resize: vertical;"></textarea>
                    </div>
                    
                    <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                        <p style="margin: 0; font-size: 14px; color: #1e40af;">
                            <i class="fas fa-info-circle"></i> <strong>Note:</strong> Releasing this batch will automatically activate it for customer verification.
                        </p>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn-cinematic btn-secondary-cinematic" onclick="closeReleaseForSaleModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn-cinematic btn-primary-cinematic">
                            <i class="fas fa-rocket"></i> Release for Sale
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Form validation and submission
    const form = document.getElementById('releaseForSaleForm');
    const officerInput = document.getElementById('releaseOfficerName');
    const errorMsg = document.getElementById('releaseOfficerNameError');
    
    // Real-time validation for alphabetic only
    officerInput.addEventListener('input', function() {
        const value = this.value;
        const alphabeticRegex = /^[A-Za-z\s]*$/;
        
        if (!alphabeticRegex.test(value)) {
            this.classList.add('error');
            errorMsg.style.display = 'block';
            // Remove non-alphabetic characters
            this.value = value.replace(/[^A-Za-z\s]/g, '');
        } else {
            this.classList.remove('error');
            errorMsg.style.display = 'none';
        }
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const officerName = officerInput.value.trim();
        const notes = document.getElementById('releaseNotes').value.trim();
        
        if (!officerName) {
            alert('Release Officer Name is required!');
            return;
        }
        
        const alphabeticRegex = /^[A-Za-z\s]+$/;
        if (!alphabeticRegex.test(officerName)) {
            alert('Officer name must contain only alphabetic characters!');
            return;
        }
        
        closeReleaseForSaleModal();
        submitBatchAction(batchId, 'release_for_sale', {
            release_officer_name: officerName,
            notes: notes
        });
    });
    
    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeReleaseForSaleModal();
        }
    });
}

function closeReleaseForSaleModal() {
    const modal = document.getElementById('releaseForSaleModal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => modal.remove(), 300);
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
