<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/gst_calculator.php';

// Check if admin is logged in
if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    header('Location: admin_login.php');
    exit();
}

$adminId = $_SESSION['user']['id'];

// Initialize GST Calculator
$gstCalculator = new GSTCalculator($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'calculate_gst':
                $orderId = $_POST['order_id'];
                $customerState = $_POST['customer_state'];
                $customerGSTIN = $_POST['customer_gstin'] ?? null;
                
                try {
                    $result = $gstCalculator->calculateOrderGST($orderId, $customerState, $customerGSTIN);
                    $success = "GST calculated successfully for Order #{$orderId}";
                } catch (Exception $e) {
                    $error = "Failed to calculate GST: " . $e->getMessage();
                }
                break;
                
            case 'bulk_calculate':
                $orderIds = $_POST['order_ids'];
                $customerState = $_POST['customer_state'];
                
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($orderIds as $orderId) {
                    try {
                        $gstCalculator->calculateOrderGST($orderId, $customerState);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                    }
                }
                
                if ($successCount > 0) {
                    $success = "GST calculated for {$successCount} orders";
                }
                if ($errorCount > 0) {
                    $error = "Failed to calculate GST for {$errorCount} orders";
                }
                break;
                
            case 'generate_invoice':
                $orderId = $_POST['order_id'];
                $invoiceNumber = $gstCalculator->generateInvoiceNumber();
                
                $query = "UPDATE gst_orders SET invoice_number = ?, invoice_date = CURDATE() WHERE order_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('si', $invoiceNumber, $orderId);
                
                if ($stmt->execute()) {
                    $success = "Invoice generated: {$invoiceNumber}";
                } else {
                    $error = "Failed to generate invoice";
                }
                break;
        }
    }
}

// Get filter parameters
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$orderType = $_GET['order_type'] ?? '';
$customerState = $_GET['customer_state'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = [];
$params = [];
$types = '';

if ($fromDate && $toDate) {
    $whereConditions[] = "DATE(go.created_at) BETWEEN ? AND ?";
    $params[] = $fromDate;
    $params[] = $toDate;
    $types .= 'ss';
}

if ($orderType) {
    $whereConditions[] = "go.order_type = ?";
    $params[] = $orderType;
    $types .= 's';
}

if ($customerState) {
    $whereConditions[] = "go.customer_state = ?";
    $params[] = $customerState;
    $types .= 's';
}

if ($search) {
    $whereConditions[] = "(o.id LIKE ? OR go.invoice_number LIKE ? OR go.gstin LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get GST orders
$query = "SELECT go.*, o.user_id, o.total_amount as order_total, u.name as customer_name, u.email as customer_email
          FROM gst_orders go
          JOIN orders o ON go.order_id = o.id
          LEFT JOIN users u ON o.user_id = u.id
          {$whereClause}
          ORDER BY go.created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Query preparation failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$gstOrders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get unique states for filter
$statesQuery = "SELECT DISTINCT customer_state FROM gst_orders ORDER BY customer_state";
$states = $conn->query($statesQuery)->fetch_all(MYSQLI_ASSOC);

// Get GST summary
$gstSummary = $gstCalculator->getGSTSummary($fromDate, $toDate);

$pageTitle = 'GST Orders';
include '../includes/admin_header.php';
?>

<!-- Premium GST Orders Interface -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">GST Orders</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#calculateGSTModal">
                        <i class="fas fa-calculator me-2"></i>Calculate GST
                    </button>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkCalculateModal">
                        <i class="fas fa-list me-2"></i>Bulk Calculate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GST Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="mb-0"><?php echo number_format($gstSummary['total_orders'] ?? 0); ?></h4>
                            <p class="mb-0">Total Orders</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-shopping-cart fa-2x opacity-75"></i>
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
                            <h4 class="mb-0"><?php echo number_format($gstSummary['intra_state_orders'] ?? 0); ?></h4>
                            <p class="mb-0">Intra-State</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-map fa-2x opacity-75"></i>
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
                            <h4 class="mb-0"><?php echo number_format($gstSummary['inter_state_orders'] ?? 0); ?></h4>
                            <p class="mb-0">Inter-State</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-globe fa-2x opacity-75"></i>
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
                            <h4 class="mb-0">₹<?php echo number_format($gstSummary['total_gst_amount'] ?? 0, 2); ?></h4>
                            <p class="mb-0">Total GST</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-coins fa-2x opacity-75"></i>
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
                    <label class="form-label">Order Type</label>
                    <select class="form-select" name="order_type">
                        <option value="">All Types</option>
                        <option value="intra_state" <?php echo $orderType === 'intra_state' ? 'selected' : ''; ?>>Intra-State</option>
                        <option value="inter_state" <?php echo $orderType === 'inter_state' ? 'selected' : ''; ?>>Inter-State</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">State</label>
                    <select class="form-select" name="customer_state">
                        <option value="">All States</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo $state['customer_state']; ?>" <?php echo $customerState === $state['customer_state'] ? 'selected' : ''; ?>>
                                <?php echo $state['customer_state']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo $search; ?>" placeholder="Order ID, Invoice, GSTIN">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <a href="gst_orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- GST Orders Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">GST Orders List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="gstOrdersTable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>State</th>
                            <th>Taxable Amount</th>
                            <th>CGST</th>
                            <th>SGST</th>
                            <th>IGST</th>
                            <th>Total GST</th>
                            <th>Grand Total</th>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gstOrders as $order): ?>
                            <tr>
                                <td>
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="text-decoration-none">
                                        #<?php echo $order['order_id']; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($order['customer_name']): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Guest Customer</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $order['order_type'] == 'intra_state' ? 'success' : 'info'; ?>">
                                        <?php echo ucfirst(str_replace('_', '-', $order['order_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($order['customer_state']); ?></td>
                                <td>₹<?php echo number_format($order['taxable_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($order['cgst_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($order['sgst_amount'], 2); ?></td>
                                <td>₹<?php echo number_format($order['igst_amount'], 2); ?></td>
                                <td>
                                    <strong>₹<?php echo number_format($order['total_gst_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <strong>₹<?php echo number_format($order['grand_total'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($order['invoice_number']): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($order['invoice_number']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Generated</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-sm view-details" 
                                                data-id="<?php echo $order['order_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!$order['invoice_number']): ?>
                                            <button type="button" class="btn btn-outline-success btn-sm generate-invoice" 
                                                    data-id="<?php echo $order['order_id']; ?>">
                                                <i class="fas fa-file-invoice"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Calculate GST Modal -->
<div class="modal fade" id="calculateGSTModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Calculate GST for Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="calculate_gst">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order ID</label>
                        <input type="number" class="form-control" name="order_id" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer State</label>
                        <select class="form-select" name="customer_state" required>
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['customer_state']; ?>">
                                    <?php echo $state['customer_state']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer GSTIN (Optional)</label>
                        <input type="text" class="form-control" name="customer_gstin" placeholder="Enter GSTIN">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Calculate GST</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Calculate Modal -->
<div class="modal fade" id="bulkCalculateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Calculate GST</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="bulk_calculate">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Order IDs (comma-separated)</label>
                        <textarea class="form-control" name="order_ids" rows="4" required
                                  placeholder="1, 2, 3, 4, 5"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Customer State</label>
                        <select class="form-select" name="customer_state" required>
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['customer_state']; ?>">
                                    <?php echo $state['customer_state']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Calculate GST</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#gstOrdersTable').DataTable({
        responsive: true,
        order: [[11, 'desc']],
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel me-2"></i>Excel',
                className: 'btn btn-success'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf me-2"></i>PDF',
                className: 'btn btn-danger'
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv me-2"></i>CSV',
                className: 'btn btn-info'
            }
        ]
    });
});

// Generate Invoice
$(document).on('click', '.generate-invoice', function() {
    const orderId = $(this).data('id');
    
    Swal.fire({
        title: 'Generate Invoice?',
        text: 'This will generate an invoice number for this order.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Generate'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="generate_invoice">
                <input type="hidden" name="order_id" value="${orderId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// View Details
$(document).on('click', '.view-details', function() {
    const orderId = $(this).data('id');
    window.open(`order_details.php?id=${orderId}`, '_blank');
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
</script>
