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

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_report':
                $reportType = $_POST['report_type'];
                $fromDate = $_POST['from_date'];
                $toDate = $_POST['to_date'];
                $format = $_POST['format'];
                
                try {
                    $reportData = generateReportData($reportType, $fromDate, $toDate, $conn);
                    $fileName = generateReportFile($reportType, $reportData, $format, $fromDate, $toDate);
                    
                    // Save report to database
                    $query = "INSERT INTO gst_reports 
                             (report_type, report_name, from_date, to_date, report_data, generated_by, file_path, file_type, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
                    
                    $stmt = $conn->prepare($query);
                    $reportName = ucfirst($reportType) . ' Report ' . date('Y-m-d H:i:s');
                    $reportJson = json_encode($reportData);
                    $stmt->bind_param('ssssssss', $reportType, $reportName, $fromDate, $toDate, $reportJson, $adminId, $fileName, $format);
                    $stmt->execute();
                    
                    $success = "Report generated successfully! <a href='{$fileName}' download>Download Report</a>";
                    
                } catch (Exception $e) {
                    $error = "Failed to generate report: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get existing reports
$reportsQuery = "SELECT gr.*, u.name as generated_by_name 
                FROM gst_reports gr 
                LEFT JOIN users u ON gr.generated_by = u.id 
                ORDER BY gr.generated_at DESC LIMIT 20";
$reportsResult = $conn->query($reportsQuery);
$reports = $reportsResult ? $reportsResult->fetch_all(MYSQLI_ASSOC) : [];

// Generate report data function
function generateReportData($reportType, $fromDate, $toDate, $conn) {
    switch ($reportType) {
        case 'gstr1':
            return generateGSTR1Data($fromDate, $toDate, $conn);
        case 'gstr2':
            return generateGSTR2Data($fromDate, $toDate, $conn);
        case 'gstr3':
            return generateGSTR3Data($fromDate, $toDate, $conn);
        case 'summary':
            return generateSummaryData($fromDate, $toDate, $conn);
        case 'detailed':
            return generateDetailedData($fromDate, $toDate, $conn);
        default:
            throw new Exception("Invalid report type");
    }
}

// GSTR1 Data (Outward Supplies)
function generateGSTR1Data($fromDate, $toDate, $conn) {
    $query = "SELECT 
                go.invoice_number,
                go.invoice_date,
                go.customer_state,
                go.place_of_supply,
                go.gstin,
                go.order_type,
                SUM(gi.taxable_amount) as taxable_amount,
                SUM(gi.cgst_amount) as cgst_amount,
                SUM(gi.sgst_amount) as sgst_amount,
                SUM(gi.igst_amount) as igst_amount,
                SUM(gi.cess_amount) as cess_amount,
                SUM(gi.total_gst_amount) as total_gst_amount
              FROM gst_orders go
              JOIN gst_order_items gi ON go.id = gi.gst_order_id
              WHERE DATE(go.created_at) BETWEEN ? AND ?
              AND go.invoice_number IS NOT NULL
              GROUP BY go.id
              ORDER BY go.invoice_date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    
    return [
        'b2b_invoices' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
        'summary' => calculateGSTR1Summary($fromDate, $toDate, $conn)
    ];
}

// GSTR2 Data (Inward Supplies)
function generateGSTR2Data($fromDate, $toDate, $conn) {
    // This would typically include purchase data
    // For now, returning a placeholder structure
    return [
        'message' => 'GSTR2 data requires purchase module integration',
        'period' => ['from' => $fromDate, 'to' => $toDate]
    ];
}

// GSTR3 Data (Monthly Return)
function generateGSTR3Data($fromDate, $toDate, $conn) {
    $gstr1Data = generateGSTR1Data($fromDate, $toDate, $conn);
    
    return [
        'gstr1_summary' => $gstr1Data['summary'],
        'itc_details' => [], // Would include Input Tax Credit details
        'tax_liability' => calculateTaxLiability($fromDate, $toDate, $conn)
    ];
}

// Summary Report
function generateSummaryData($fromDate, $toDate, $conn) {
    $query = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN order_type = 'intra_state' THEN 1 ELSE 0 END) as intra_state_orders,
                SUM(CASE WHEN order_type = 'inter_state' THEN 1 ELSE 0 END) as inter_state_orders,
                SUM(taxable_amount) as total_taxable_amount,
                SUM(cgst_amount) as total_cgst_amount,
                SUM(sgst_amount) as total_sgst_amount,
                SUM(igst_amount) as total_igst_amount,
                SUM(cess_amount) as total_cess_amount,
                SUM(total_gst_amount) as total_gst_amount,
                SUM(grand_total) as total_grand_total
              FROM gst_orders 
              WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    
    $summary = $stmt->get_result()->fetch_assoc();
    
    // Get state-wise breakdown
    $stateQuery = "SELECT 
                     customer_state,
                     COUNT(*) as order_count,
                     SUM(taxable_amount) as taxable_amount,
                     SUM(total_gst_amount) as gst_amount
                   FROM gst_orders 
                   WHERE DATE(created_at) BETWEEN ? AND ?
                   GROUP BY customer_state
                   ORDER BY gst_amount DESC";
    
    $stmt = $conn->prepare($stateQuery);
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    $stateBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return [
        'summary' => $summary,
        'state_breakdown' => $stateBreakdown,
        'period' => ['from' => $fromDate, 'to' => $toDate]
    ];
}

// Detailed Report
function generateDetailedData($fromDate, $toDate, $conn) {
    $query = "SELECT 
                go.*,
                o.customer_id,
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                GROUP_CONCAT(
                    CONCAT(p.name, ' (', gi.quantity, ' x ₹', gi.unit_price, ')')
                    SEPARATOR '; '
                ) as items_detail
              FROM gst_orders go
              JOIN orders o ON go.order_id = o.id
              LEFT JOIN customers c ON o.customer_id = c.id
              JOIN gst_order_items gi ON go.id = gi.gst_order_id
              JOIN products p ON gi.product_id = p.id
              WHERE DATE(go.created_at) BETWEEN ? AND ?
              GROUP BY go.id
              ORDER BY go.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    
    return [
        'orders' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
        'period' => ['from' => $fromDate, 'to' => $toDate]
    ];
}

// Helper functions
function calculateGSTR1Summary($fromDate, $toDate, $conn) {
    $query = "SELECT 
                SUM(taxable_amount) as total_taxable_amount,
                SUM(cgst_amount) as total_cgst_amount,
                SUM(sgst_amount) as total_sgst_amount,
                SUM(igst_amount) as total_igst_amount,
                SUM(cess_amount) as total_cess_amount,
                SUM(total_gst_amount) as total_gst_amount,
                COUNT(DISTINCT customer_state) as states_supplied
              FROM gst_orders 
              WHERE DATE(created_at) BETWEEN ? AND ?
              AND invoice_number IS NOT NULL";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $fromDate, $toDate);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}

function calculateTaxLiability($fromDate, $toDate, $conn) {
    $summary = calculateGSTR1Summary($fromDate, $toDate, $conn);
    
    return [
        'output_tax' => [
            'cgst' => $summary['total_cgst_amount'],
            'sgst' => $summary['total_sgst_amount'],
            'igst' => $summary['total_igst_amount'],
            'cess' => $summary['total_cess_amount']
        ],
        'input_tax' => [
            'cgst' => 0, // Would be calculated from purchases
            'sgst' => 0,
            'igst' => 0,
            'cess' => 0
        ],
        'net_liability' => [
            'cgst' => $summary['total_cgst_amount'],
            'sgst' => $summary['total_sgst_amount'],
            'igst' => $summary['total_igst_amount'],
            'cess' => $summary['total_cess_amount']
        ]
    ];
}

// Generate report file
function generateReportFile($reportType, $reportData, $format, $fromDate, $toDate) {
    $fileName = "reports/gst_{$reportType}_{$fromDate}_to_{$toDate}." . $format;
    $filePath = '../' . $fileName;
    
    // Ensure reports directory exists
    if (!is_dir('../reports')) {
        mkdir('../reports', 0755, true);
    }
    
    switch ($format) {
        case 'excel':
            return generateExcelReport($reportType, $reportData, $filePath);
        case 'pdf':
            return generatePDFReport($reportType, $reportData, $filePath);
        case 'csv':
            return generateCSVReport($reportType, $reportData, $filePath);
        default:
            throw new Exception("Unsupported format");
    }
}

// Generate Excel Report
function generateExcelReport($reportType, $reportData, $filePath) {
    // Simple CSV format for Excel (can be enhanced with PHPExcel library)
    return generateCSVReport($reportType, $reportData, $filePath);
}

// Generate PDF Report
function generatePDFReport($reportType, $reportData, $filePath) {
    // Simple HTML to PDF conversion (can be enhanced with TCPDF/FPDF)
    $html = generateHTMLReport($reportType, $reportData);
    
    // For now, save as HTML file
    file_put_contents(str_replace('.pdf', '.html', $filePath), $html);
    return str_replace('.pdf', '.html', $filePath);
}

// Generate CSV Report
function generateCSVReport($reportType, $reportData, $filePath) {
    $handle = fopen($filePath, 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fwrite($handle, "\xEF\xBB\xBF");
    
    switch ($reportType) {
        case 'gstr1':
            fputcsv($handle, ['Invoice No', 'Invoice Date', 'Customer State', 'GSTIN', 'Taxable Amount', 'CGST', 'SGST', 'IGST', 'CESS', 'Total GST']);
            foreach ($reportData['b2b_invoices'] as $invoice) {
                fputcsv($handle, [
                    $invoice['invoice_number'],
                    $invoice['invoice_date'],
                    $invoice['customer_state'],
                    $invoice['gstin'],
                    $invoice['taxable_amount'],
                    $invoice['cgst_amount'],
                    $invoice['sgst_amount'],
                    $invoice['igst_amount'],
                    $invoice['cess_amount'],
                    $invoice['total_gst_amount']
                ]);
            }
            break;
            
        case 'summary':
            fputcsv($handle, ['Metric', 'Value']);
            foreach ($reportData['summary'] as $key => $value) {
                fputcsv($handle, [ucfirst(str_replace('_', ' ', $key)), $value]);
            }
            break;
            
        case 'detailed':
            fputcsv($handle, ['Order ID', 'Customer', 'State', 'Type', 'Taxable Amount', 'GST Amount', 'Grand Total', 'Items']);
            foreach ($reportData['orders'] as $order) {
                fputcsv($handle, [
                    $order['order_id'],
                    $order['customer_name'] ?? 'Guest',
                    $order['customer_state'],
                    $order['order_type'],
                    $order['taxable_amount'],
                    $order['total_gst_amount'],
                    $order['grand_total'],
                    $order['items_detail']
                ]);
            }
            break;
    }
    
    fclose($handle);
    return $filePath;
}

// Generate HTML Report
function generateHTMLReport($reportType, $reportData) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <title>GST ' . strtoupper($reportType) . ' Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .header { text-align: center; margin-bottom: 30px; }
        .summary { background-color: #f9f9f9; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>GST ' . strtoupper($reportType) . ' Report</h1>
        <p>Period: ' . $reportData['period']['from'] . ' to ' . $reportData['period']['to'] . '</p>
    </div>';
    
    // Add content based on report type
    switch ($reportType) {
        case 'summary':
            $html .= '<div class="summary">';
            foreach ($reportData['summary'] as $key => $value) {
                $html .= '<p><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</p>';
            }
            $html .= '</div>';
            break;
    }
    
    $html .= '</body></html>';
    return $html;
}

$pageTitle = 'GST Reports';
include '../includes/admin_header.php';
?>

<!-- Premium GST Reports Interface -->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">GST Reports</h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateReportModal">
                        <i class="fas fa-file-alt me-2"></i>Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Generation Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-gradient-primary text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-file-invoice fa-3x mb-3"></i>
                    <h5>GSTR-1</h5>
                    <p class="small">Outward Supplies</p>
                    <button class="btn btn-light btn-sm" onclick="quickGenerate('gstr1')">Generate</button>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-success text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-file-contract fa-3x mb-3"></i>
                    <h5>GSTR-2</h5>
                    <p class="small">Inward Supplies</p>
                    <button class="btn btn-light btn-sm" onclick="quickGenerate('gstr2')">Generate</button>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-warning text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-file-alt fa-3x mb-3"></i>
                    <h5>GSTR-3</h5>
                    <p class="small">Monthly Return</p>
                    <button class="btn btn-light btn-sm" onclick="quickGenerate('gstr3')">Generate</button>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-gradient-info text-white h-100">
                <div class="card-body text-center">
                    <i class="fas fa-chart-bar fa-3x mb-3"></i>
                    <h5>Summary</h5>
                    <p class="small">Analytics Report</p>
                    <button class="btn btn-light btn-sm" onclick="quickGenerate('summary')">Generate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reports -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Reports</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="reportsTable">
                    <thead>
                        <tr>
                            <th>Report Name</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Generated By</th>
                            <th>Generated At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($report['report_name']); ?></strong>
                                        <?php if ($report['file_path']): ?>
                                            <br>
                                            <small class="text-muted"><?php echo basename($report['file_path']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo strtoupper($report['report_type']); ?></span>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($report['from_date'])); ?> - 
                                    <?php echo date('d M Y', strtotime($report['to_date'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($report['generated_by_name'] ?? 'System'); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($report['generated_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $report['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($report['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($report['file_path'] && file_exists('../' . $report['file_path'])): ?>
                                            <a href="<?php echo $report['file_path']; ?>" 
                                               class="btn btn-outline-primary btn-sm" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-info btn-sm view-report" 
                                                data-id="<?php echo $report['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm delete-report" 
                                                data-id="<?php echo $report['id']; ?>">
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

<!-- Generate Report Modal -->
<div class="modal fade" id="generateReportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate GST Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="generate_report">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type" required>
                            <option value="">Select Report Type</option>
                            <option value="gstr1">GSTR-1 (Outward Supplies)</option>
                            <option value="gstr2">GSTR-2 (Inward Supplies)</option>
                            <option value="gstr3">GSTR-3 (Monthly Return)</option>
                            <option value="summary">Summary Report</option>
                            <option value="detailed">Detailed Report</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">From Date</label>
                                <input type="date" class="form-control" name="from_date" 
                                       value="<?php echo date('Y-m-01'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">To Date</label>
                                <input type="date" class="form-control" name="to_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Format</label>
                        <select class="form-select" name="format" required>
                            <option value="excel">Excel (.xlsx)</option>
                            <option value="pdf">PDF (.pdf)</option>
                            <option value="csv">CSV (.csv)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>

<script>
// Quick Generate Function
function quickGenerate(reportType) {
    const modal = document.getElementById('generateReportModal');
    const form = modal.querySelector('form');
    
    form.querySelector('[name="report_type"]').value = reportType;
    form.querySelector('[name="from_date"]').value = '<?php echo date('Y-m-01'); ?>';
    form.querySelector('[name="to_date"]').value = '<?php echo date('Y-m-d'); ?>';
    form.querySelector('[name="format"]').value = 'excel';
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Initialize DataTable
$(document).ready(function() {
    $('#reportsTable').DataTable({
        responsive: true,
        order: [[4, 'desc']],
        pageLength: 25
    });
});

// Delete Report
$(document).on('click', '.delete-report', function() {
    const reportId = $(this).data('id');
    
    Swal.fire({
        title: 'Delete Report?',
        text: 'This will permanently delete the report.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            // Implement delete functionality
            location.reload();
        }
    });
});

// View Report
$(document).on('click', '.view-report', function() {
    const reportId = $(this).data('id');
    // Implement view functionality
    window.open(`view_gst_report.php?id=${reportId}`, '_blank');
});

// Show alerts
<?php if (isset($success)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        html: '<?php echo $success; ?>',
        timer: 5000
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
