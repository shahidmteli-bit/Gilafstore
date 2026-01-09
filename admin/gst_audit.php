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

// Get filter parameters
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$actionType = $_GET['action_type'] ?? '';
$tableName = $_GET['table_name'] ?? '';
$changedBy = $_GET['changed_by'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$types = '';

if ($fromDate && $toDate) {
    $whereConditions[] = "DATE(gat.changed_at) BETWEEN ? AND ?";
    $params[] = $fromDate;
    $params[] = $toDate;
    $types .= 'ss';
}

if ($actionType) {
    $whereConditions[] = "gat.action_type = ?";
    $params[] = $actionType;
    $types .= 's';
}

if ($tableName) {
    $whereConditions[] = "gat.table_name = ?";
    $params[] = $tableName;
    $types .= 's';
}

if ($changedBy) {
    $whereConditions[] = "gat.changed_by = ?";
    $params[] = $changedBy;
    $types .= 'i';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get audit trail records
$query = "SELECT gat.*, u.name as admin_name 
          FROM gst_audit_trail gat
          LEFT JOIN users u ON gat.changed_by = u.id
          {$whereClause}
          ORDER BY gat.changed_at DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Query preparation failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$auditRecords = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get unique values for filters
$tablesQuery = "SELECT DISTINCT table_name FROM gst_audit_trail ORDER BY table_name";
$tablesResult = $conn->query($tablesQuery);
$tables = $tablesResult ? $tablesResult->fetch_all(MYSQLI_ASSOC) : [];

$adminsQuery = "SELECT DISTINCT gat.changed_by, u.name 
               FROM gst_audit_trail gat 
               LEFT JOIN users u ON gat.changed_by = u.id 
               WHERE u.name IS NOT NULL
               ORDER BY u.name";
$adminsResult = $conn->query($adminsQuery);
$admins = $adminsResult ? $adminsResult->fetch_all(MYSQLI_ASSOC) : [];

// Get audit summary
$summaryQuery = "SELECT 
                   action_type,
                   table_name,
                   COUNT(*) as count,
                   DATE(changed_at) as date
                 FROM gst_audit_trail 
                 WHERE DATE(changed_at) BETWEEN ? AND ?
                 GROUP BY action_type, table_name, DATE(changed_at)
                 ORDER BY date DESC";
$stmt = $conn->prepare($summaryQuery);
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$auditSummary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'GST Audit Trail';
include '../includes/admin_header.php';
?>

<!-- Premium GST Audit Trail Interface -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">GST Audit Trail</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" onclick="exportAuditLog()">
                        <i class="fas fa-download me-2"></i>Export Log
                    </button>
                    <button type="button" class="btn btn-success" onclick="refreshAuditLog()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo count($auditRecords); ?></h4>
                            <p class="mb-0">Total Activities</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-history fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo count(array_filter($auditRecords, fn($r) => $r['action_type'] == 'create')); ?></h4>
                            <p class="mb-0">Creates</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-plus fa-2x opacity-75"></i>
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
                            <h4 class="mb-0"><?php echo count(array_filter($auditRecords, fn($r) => $r['action_type'] == 'update')); ?></h4>
                            <p class="mb-0">Updates</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-edit fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo count(array_filter($auditRecords, fn($r) => $r['action_type'] == 'delete')); ?></h4>
                            <p class="mb-0">Deletes</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-trash fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="from_date" value="<?php echo $fromDate; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="to_date" value="<?php echo $toDate; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Action Type</label>
                    <select class="form-select" name="action_type">
                        <option value="">All Actions</option>
                        <option value="create" <?php echo $actionType === 'create' ? 'selected' : ''; ?>>Create</option>
                        <option value="update" <?php echo $actionType === 'update' ? 'selected' : ''; ?>>Update</option>
                        <option value="delete" <?php echo $actionType === 'delete' ? 'selected' : ''; ?>>Delete</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Table</label>
                    <select class="form-select" name="table_name">
                        <option value="">All Tables</option>
                        <?php foreach ($tables as $table): ?>
                            <option value="<?php echo $table['table_name']; ?>" <?php echo $tableName === $table['table_name'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst($table['table_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Admin</label>
                    <select class="form-select" name="changed_by">
                        <option value="">All Admins</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['changed_by']; ?>" <?php echo $changedBy == $admin['changed_by'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['name'] ?? 'Unknown'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="gst_audit.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Trail Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Audit Log</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="auditTable">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Admin</th>
                            <th>IP Address</th>
                            <th>Changes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditRecords as $record): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo date('d M Y', strtotime($record['changed_at'])); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($record['changed_at'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getActionBadgeClass($record['action_type']); ?>">
                                        <?php echo ucfirst($record['action_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($record['table_name']); ?></span>
                                </td>
                                <td>
                                    <code><?php echo $record['record_id']; ?></code>
                                </td>
                                <td>
                                    <?php if ($record['admin_name']): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($record['admin_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: <?php echo $record['changed_by']; ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Admin #<?php echo $record['changed_by']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?php echo $record['ip_address'] ?? 'N/A'; ?></code>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-outline-info btn-sm view-changes" 
                                            data-old='<?php echo htmlspecialchars($record['old_values'] ?? '{}'); ?>'
                                            data-new='<?php echo htmlspecialchars($record['new_values'] ?? '{}'); ?>'
                                            data-fields='<?php echo htmlspecialchars($record['changed_fields'] ?? '{}'); ?>'>
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-sm view-details" 
                                                data-id="<?php echo $record['id']; ?>">
                                            <i class="fas fa-info-circle"></i>
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

    <!-- Activity Timeline -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Activity Timeline</h5>
        </div>
        <div class="card-body">
            <div class="timeline">
                <?php foreach (array_slice($auditRecords, 0, 10) as $record): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-<?php echo getActionBadgeClass($record['action_type']); ?>"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($record['admin_name'] ?? 'Unknown'); ?></strong>
                                    <span class="badge bg-<?php echo getActionBadgeClass($record['action_type']); ?> ms-2">
                                        <?php echo ucfirst($record['action_type']); ?>
                                    </span>
                                    <span class="badge bg-info ms-1">
                                        <?php echo ucfirst($record['table_name']); ?>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('d M Y H:i', strtotime($record['changed_at'])); ?>
                                </small>
                            </div>
                            <p class="mb-0 mt-1">
                                Record ID: <code><?php echo $record['record_id']; ?></code>
                                <?php if ($record['ip_address']): ?>
                                    | IP: <code><?php echo $record['ip_address']; ?></code>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Changes Modal -->
<div class="modal fade" id="changesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Old Values</h6>
                        <div id="oldValues" class="border p-3 bg-light" style="min-height: 100px; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
                    </div>
                    <div class="col-md-6">
                        <h6>New Values</h6>
                        <div id="newValues" class="border p-3 bg-light" style="min-height: 100px; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
                    </div>
                </div>
                <?php if (false): ?>
                    <div class="mt-3">
                        <h6>Changed Fields</h6>
                        <div id="changedFields" class="border p-3 bg-light" style="min-height: 50px; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -23px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}
</style>

<script>
// Helper function for action badge class
<?php
function getActionBadgeClass($actionType) {
    switch ($actionType) {
        case 'create': return 'success';
        case 'update': return 'warning';
        case 'delete': return 'danger';
        default: return 'secondary';
    }
}
?>

// Initialize DataTable
$(document).ready(function() {
    $('#auditTable').DataTable({
        responsive: true,
        order: [[0, 'desc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel me-2"></i>Excel',
                className: 'btn btn-success'
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv me-2"></i>CSV',
                className: 'btn btn-info'
            }
        ]
    });
});

// View Changes
$(document).on('click', '.view-changes', function() {
    const oldValues = $(this).data('old');
    const newValues = $(this).data('new');
    const changedFields = $(this).data('fields');
    
    $('#oldValues').text(oldValues ? JSON.stringify(JSON.parse(oldValues), null, 2) : 'No old values');
    $('#newValues').text(newValues ? JSON.stringify(JSON.parse(newValues), null, 2) : 'No new values');
    $('#changedFields').text(changedFields ? JSON.stringify(JSON.parse(changedFields), null, 2) : 'No changed fields');
    
    const modal = new bootstrap.Modal(document.getElementById('changesModal'));
    modal.show();
});

// Export Audit Log
function exportAuditLog() {
    window.open('export_audit_log.php?from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>', '_blank');
}

// Refresh Audit Log
function refreshAuditLog() {
    location.reload();
}

// Auto-refresh every 2 minutes
setInterval(refreshAuditLog, 120000);
</script>
