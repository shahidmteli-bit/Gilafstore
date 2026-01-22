<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Order Dashboard — Admin';
$adminPage = 'orders';

$db = get_db_connection();

// Get current filter from URL
$currentFilter = $_GET['filter'] ?? 'all';
$highValueThreshold = 5000; // Configurable threshold for high-value orders
$delayedDays = 3; // Orders older than this without shipping are considered delayed

// Build the counts for all status filters
$counts = [];

// Total Orders
$counts['total'] = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Today's Orders
$counts['today'] = $db->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();

// Current Week Orders (this week starting Monday)
$counts['current_week'] = $db->query("SELECT COUNT(*) FROM orders WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn();

// Current Month Orders
$counts['current_month'] = $db->query("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();

// Last Month Orders
$counts['last_month'] = $db->query("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetchColumn();

// Paid Orders (payment_status = 'completed')
$counts['paid'] = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'completed'")->fetchColumn();

// Pending Orders (order placed, not processed)
$counts['pending'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

// Processing Orders (accepted, packing / ready to ship)
$counts['processing'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('accepted', 'processing')")->fetchColumn();

// Unshipped Orders (confirmed but not dispatched - accepted but not shipped)
$counts['unshipped'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'accepted' AND (courier_company IS NULL OR courier_company = '')")->fetchColumn();

// In Transit Orders (shipped but not delivered)
$counts['in_transit'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'shipped'")->fetchColumn();

// Delivered Orders
$counts['delivered'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'")->fetchColumn();

// Pending Payment Orders
$counts['pending_payment'] = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'")->fetchColumn();

// Transaction Failed Orders
$counts['failed_payment'] = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'failed'")->fetchColumn();

// COD Orders
$counts['cod'] = $db->query("SELECT COUNT(*) FROM orders WHERE payment_method = 'cod'")->fetchColumn();

// Refund Pending Orders
$counts['refund_pending'] = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'refund_pending'")->fetchColumn();

// Return Requested
$counts['return_requested'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'return_requested'")->fetchColumn();

// Returned Orders
$counts['returned'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'returned'")->fetchColumn();

// Refunded Orders
$counts['refunded'] = $db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'refunded'")->fetchColumn();

// Cancelled Orders
$counts['cancelled'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'cancelled'")->fetchColumn();

// Delayed Orders (pending/accepted for more than X days)
$counts['delayed'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('pending', 'accepted') AND created_at < DATE_SUB(NOW(), INTERVAL $delayedDays DAY)")->fetchColumn();

// Failed Delivery Attempts (if you have this field)
$counts['failed_delivery'] = $db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivery_failed'")->fetchColumn();

// High-Value Orders
$counts['high_value'] = $db->query("SELECT COUNT(*) FROM orders WHERE total_amount >= $highValueThreshold")->fetchColumn();

// Build WHERE clause based on filter
$whereClause = "1=1";
$filterTitle = "All Orders";

switch ($currentFilter) {
    case 'today':
        $whereClause = "DATE(o.created_at) = CURDATE()";
        $filterTitle = "Today's Orders";
        break;
    case 'current_week':
        $whereClause = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $filterTitle = "Current Week Orders";
        break;
    case 'current_month':
        $whereClause = "YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
        $filterTitle = "Current Month Orders";
        break;
    case 'last_month':
        $whereClause = "YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $filterTitle = "Last Month Orders";
        break;
    case 'paid':
        $whereClause = "o.payment_status = 'completed'";
        $filterTitle = "Paid Orders";
        break;
    case 'pending':
        $whereClause = "o.order_status = 'pending'";
        $filterTitle = "Pending Orders";
        break;
    case 'processing':
        $whereClause = "o.order_status IN ('accepted', 'processing')";
        $filterTitle = "Processing Orders";
        break;
    case 'unshipped':
        $whereClause = "o.order_status = 'accepted' AND (o.courier_company IS NULL OR o.courier_company = '')";
        $filterTitle = "Unshipped Orders";
        break;
    case 'in_transit':
        $whereClause = "o.order_status = 'shipped'";
        $filterTitle = "In Transit Orders";
        break;
    case 'delivered':
        $whereClause = "o.order_status = 'delivered'";
        $filterTitle = "Delivered Orders";
        break;
    case 'pending_payment':
        $whereClause = "o.payment_status = 'pending'";
        $filterTitle = "Pending Payment Orders";
        break;
    case 'failed_payment':
        $whereClause = "o.payment_status = 'failed'";
        $filterTitle = "Transaction Failed Orders";
        break;
    case 'cod':
        $whereClause = "o.payment_method = 'cod'";
        $filterTitle = "COD Orders";
        break;
    case 'refund_pending':
        $whereClause = "o.payment_status = 'refund_pending'";
        $filterTitle = "Refund Pending Orders";
        break;
    case 'return_requested':
        $whereClause = "o.order_status = 'return_requested'";
        $filterTitle = "Return Requested Orders";
        break;
    case 'returned':
        $whereClause = "o.order_status = 'returned'";
        $filterTitle = "Returned Orders";
        break;
    case 'refunded':
        $whereClause = "o.payment_status = 'refunded'";
        $filterTitle = "Refunded Orders";
        break;
    case 'cancelled':
        $whereClause = "o.order_status = 'cancelled'";
        $filterTitle = "Cancelled Orders";
        break;
    case 'delayed':
        $whereClause = "o.order_status IN ('pending', 'accepted') AND o.created_at < DATE_SUB(NOW(), INTERVAL $delayedDays DAY)";
        $filterTitle = "Delayed Orders";
        break;
    case 'failed_delivery':
        $whereClause = "o.order_status = 'delivery_failed'";
        $filterTitle = "Failed Delivery Orders";
        break;
    case 'high_value':
        $whereClause = "o.total_amount >= $highValueThreshold";
        $filterTitle = "High-Value Orders (₹$highValueThreshold+)";
        break;
    default:
        $whereClause = "1=1";
        $filterTitle = "All Orders";
}

// Fetch filtered orders
$orders = $db->query("
    SELECT o.*, u.name AS customer, u.email AS customer_email
    FROM orders o 
    LEFT JOIN users u ON u.id = o.user_id 
    WHERE $whereClause
    ORDER BY o.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get courier companies for modal
$couriers = $db->query("SELECT * FROM courier_companies WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* Professional Glassmorphism Dashboard */
.dashboard-container {
    padding: 0;
}

/* Section Styling */
.dashboard-section {
    margin-bottom: 28px;
}

.dashboard-section-title {
    font-size: 12px;
    font-weight: 700;
    color: #1a3c34;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 16px;
    padding: 8px 16px;
    background: linear-gradient(135deg, rgba(26,60,52,0.08) 0%, rgba(26,60,52,0.03) 100%);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(26,60,52,0.1);
}

.dashboard-section-title i {
    font-size: 14px;
    opacity: 0.8;
}

/* Status Cards Grid - Equal Width */
.status-cards {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 16px;
}

.status-cards.cols-4 {
    grid-template-columns: repeat(4, 1fr);
}

.status-cards.cols-3 {
    grid-template-columns: repeat(3, 1fr);
}

/* Glassmorphism Status Cards */
.status-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 20px 24px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    overflow: hidden;
}

.status-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: currentColor;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.status-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12), 0 4px 12px rgba(0, 0, 0, 0.08);
}

.status-card:hover::before {
    opacity: 1;
}

.status-card.active {
    border: 2px solid #1a3c34;
    box-shadow: 0 8px 32px rgba(26, 60, 52, 0.2);
}

.status-card .count {
    font-size: 32px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -1px;
}

.status-card .label {
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
    letter-spacing: 0.3px;
}

/* Color Variants with Glass Effect */
.status-card.green {
    background: linear-gradient(135deg, rgba(220,252,231,0.9) 0%, rgba(187,247,208,0.7) 100%);
    border-color: rgba(22,163,74,0.2);
    color: #16a34a;
}
.status-card.green .count { color: #15803d; }

.status-card.amber {
    background: linear-gradient(135deg, rgba(254,243,199,0.9) 0%, rgba(253,230,138,0.7) 100%);
    border-color: rgba(217,119,6,0.2);
    color: #d97706;
}
.status-card.amber .count { color: #b45309; }

.status-card.red {
    background: linear-gradient(135deg, rgba(254,226,226,0.9) 0%, rgba(254,202,202,0.7) 100%);
    border-color: rgba(220,38,38,0.2);
    color: #dc2626;
}
.status-card.red .count { color: #b91c1c; }

.status-card.blue {
    background: linear-gradient(135deg, rgba(219,234,254,0.9) 0%, rgba(191,219,254,0.7) 100%);
    border-color: rgba(37,99,235,0.2);
    color: #2563eb;
}
.status-card.blue .count { color: #1d4ed8; }

.status-card.purple {
    background: linear-gradient(135deg, rgba(243,232,255,0.9) 0%, rgba(233,213,255,0.7) 100%);
    border-color: rgba(147,51,234,0.2);
    color: #9333ea;
}
.status-card.purple .count { color: #7c3aed; }

.status-card.gray {
    background: linear-gradient(135deg, rgba(241,245,249,0.9) 0%, rgba(226,232,240,0.7) 100%);
    border-color: rgba(100,116,139,0.2);
    color: #64748b;
}
.status-card.gray .count { color: #475569; }

/* Summary Cards - Top Section */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}

.summary-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border-radius: 20px;
    padding: 28px 32px;
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08), 0 2px 8px rgba(0, 0, 0, 0.04);
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    overflow: hidden;
}

.summary-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 120px;
    height: 120px;
    background: radial-gradient(circle, currentColor 0%, transparent 70%);
    opacity: 0.05;
    transform: translate(30%, -30%);
}

.summary-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.12), 0 8px 20px rgba(0, 0, 0, 0.06);
}

.summary-card.active {
    border: 2px solid #1a3c34;
    box-shadow: 0 12px 40px rgba(26, 60, 52, 0.15);
}

.summary-card .icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.summary-card .info {
    flex: 1;
}

.summary-card .count {
    font-size: 42px;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 6px;
    letter-spacing: -2px;
}

.summary-card .label {
    font-size: 15px;
    color: #64748b;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.summary-card.primary { color: #1a3c34; }
.summary-card.primary .icon { background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%); color: #fff; }
.summary-card.primary .count { color: #1a3c34; }

.summary-card.success { color: #16a34a; }
.summary-card.success .icon { background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%); color: #fff; }
.summary-card.success .count { color: #16a34a; }

.summary-card.info { color: #0284c7; }
.summary-card.info .icon { background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%); color: #fff; }
.summary-card.info .count { color: #0284c7; }

.summary-card.purple { color: #9333ea; }
.summary-card.purple .icon { background: linear-gradient(135deg, #9333ea 0%, #a855f7 100%); color: #fff; }
.summary-card.purple .count { color: #9333ea; }

.summary-card.amber { color: #d97706; }
.summary-card.amber .icon { background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); color: #fff; }
.summary-card.amber .count { color: #d97706; }

.summary-card.gray { color: #64748b; }
.summary-card.gray .icon { background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%); color: #fff; }
.summary-card.gray .count { color: #64748b; }

/* Order Table Styling */
.orders-table-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.orders-table-header {
    padding: 20px 28px;
    background: rgba(255, 255, 255, 0.5);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.order-items-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 13px;
}

.order-item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.item-name {
    color: #2c3e50;
    font-weight: 500;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 150px;
}

.item-qty {
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
    background: rgba(241, 245, 249, 0.8);
    padding: 3px 8px;
    border-radius: 6px;
    backdrop-filter: blur(4px);
}

/* Status Badges */
.badge-order-status {
    font-size: 11px;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
}

.badge-pending { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e; }
.badge-accepted, .badge-processing { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #1e40af; }
.badge-shipped { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #3730a3; }
.badge-delivered { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #166534; }
.badge-cancelled { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; }
.badge-returned { background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%); color: #9d174d; }

.badge-payment-status {
    font-size: 10px;
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 700;
    display: inline-block;
}

.badge-payment-completed { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #166534; }
.badge-payment-pending { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e; }
.badge-payment-failed { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; }
.badge-payment-refunded { background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); color: #6b21a8; }

/* Filter Title */
.filter-active-title {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.filter-active-title h5 {
    margin: 0;
    font-weight: 700;
    font-size: 18px;
    color: #1a3c34;
}

.filter-active-title .clear-filter {
    font-size: 13px;
    color: #64748b;
    text-decoration: none;
    padding: 6px 12px;
    background: rgba(241, 245, 249, 0.8);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.filter-active-title .clear-filter:hover {
    background: rgba(26, 60, 52, 0.1);
    color: #1a3c34;
}

.filter-active-title .badge {
    font-size: 12px;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
}

/* Page Header */
.page-header {
    margin-bottom: 28px;
}

.page-header h4 {
    font-size: 28px;
    font-weight: 800;
    color: #1a3c34;
    letter-spacing: -0.5px;
    margin-bottom: 4px;
}

.page-header p {
    font-size: 15px;
    color: #64748b;
}

.simple-view-btn {
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 0, 0, 0.1);
}

.simple-view-btn:hover {
    background: #1a3c34;
    color: #fff;
    border-color: #1a3c34;
}

/* Export Button */
.export-btn {
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s ease;
    background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(22, 163, 74, 0.4);
    color: #fff;
}

/* Export Modal Styling */
.export-modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.export-modal-content .modal-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding: 20px 24px;
}

.export-modal-content .modal-body {
    padding: 24px;
}

.export-modal-content .modal-footer {
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    padding: 16px 24px;
}

/* Date Range Options */
.date-range-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.date-option {
    margin: 0;
    padding: 12px 16px;
    background: rgba(248, 250, 252, 0.8);
    border-radius: 10px;
    border: 2px solid transparent;
    transition: all 0.2s ease;
    cursor: pointer;
}

.date-option:hover {
    background: rgba(26, 60, 52, 0.05);
    border-color: rgba(26, 60, 52, 0.2);
}

.date-option .form-check-input:checked ~ .form-check-label {
    color: #1a3c34;
    font-weight: 600;
}

.date-option:has(.form-check-input:checked) {
    background: rgba(26, 60, 52, 0.1);
    border-color: #1a3c34;
}

.date-option .form-check-label {
    cursor: pointer;
    font-size: 13px;
    display: flex;
    align-items: center;
}

.date-option .form-check-label i {
    color: #64748b;
}

/* Custom Date Fields */
.custom-date-fields {
    background: rgba(248, 250, 252, 0.8);
    border-radius: 12px;
    padding: 16px;
    border: 1px dashed rgba(26, 60, 52, 0.2);
}

.custom-date-fields .form-control {
    border-radius: 8px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    padding: 10px 14px;
}

.custom-date-fields .form-control:focus {
    border-color: #1a3c34;
    box-shadow: 0 0 0 3px rgba(26, 60, 52, 0.1);
}

/* Export Info Box */
.export-info {
    background: linear-gradient(135deg, rgba(219, 234, 254, 0.5) 0%, rgba(191, 219, 254, 0.3) 100%);
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 13px;
    color: #1e40af;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.export-info i {
    margin-top: 2px;
}

/* Table Enhancements */
.table {
    margin-bottom: 0;
}

.table thead th {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    border-bottom: 2px solid rgba(0, 0, 0, 0.05);
    padding: 16px 20px;
    background: rgba(248, 250, 252, 0.5);
}

.table tbody td {
    padding: 16px 20px;
    vertical-align: middle;
    border-bottom: 1px solid rgba(0, 0, 0, 0.03);
}

.table tbody tr:hover {
    background: rgba(26, 60, 52, 0.02);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Action Buttons */
.btn-group-sm .btn {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 13px;
}

.btn-group-sm .btn-primary {
    background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%);
    border: none;
}

.btn-group-sm .btn-primary:hover {
    transform: scale(1.05);
}

/* Responsive Design */
@media (max-width: 1400px) {
    .status-cards {
        grid-template-columns: repeat(4, 1fr);
    }
    .status-cards.cols-4 {
        grid-template-columns: repeat(4, 1fr);
    }
    .status-cards.cols-3 {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1400px) {
    .summary-cards {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1200px) {
    .summary-cards {
        grid-template-columns: repeat(3, 1fr);
    }
    .status-cards {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .summary-cards {
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .summary-card {
        padding: 20px 24px;
    }
    .summary-card .count {
        font-size: 32px;
    }
    .summary-card .icon {
        width: 52px;
        height: 52px;
        font-size: 22px;
    }
    .status-cards {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .summary-cards {
        grid-template-columns: 1fr;
    }
    .status-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    .status-card {
        padding: 16px 20px;
    }
    .status-card .count {
        font-size: 26px;
    }
    .page-header h4 {
        font-size: 22px;
    }
}

@media (max-width: 576px) {
    .status-cards.cols-4,
    .status-cards.cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<section class="py-4">
    <div class="container-fluid dashboard-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center page-header">
            <div>
                <h4>Order Dashboard</h4>
                <p class="mb-0">Monitor sales, payments, fulfillment, and exceptions at a glance.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn export-btn" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-file-excel me-2"></i>Export Report
                </button>
                <a href="manage_orders.php" class="btn simple-view-btn">
                    <i class="fas fa-list me-2"></i>Simple View
                </a>
            </div>
        </div>

        <!-- 1. Top Summary Cards -->
        <div class="summary-cards">
            <a href="?filter=all" class="summary-card primary <?= $currentFilter === 'all' ? 'active' : ''; ?>">
                <div class="icon"><i class="fas fa-shopping-bag"></i></div>
                <div class="info">
                    <div class="count"><?= number_format($counts['total']); ?></div>
                    <div class="label">Total Orders</div>
                </div>
            </a>
            <a href="?filter=today" class="summary-card info <?= $currentFilter === 'today' ? 'active' : ''; ?>">
                <div class="icon"><i class="fas fa-calendar-day"></i></div>
                <div class="info">
                    <div class="count"><?= number_format($counts['today']); ?></div>
                    <div class="label">Today's Orders</div>
                </div>
            </a>
            <a href="?filter=current_week" class="summary-card purple <?= $currentFilter === 'current_week' ? 'active' : ''; ?>">
                <div class="icon"><i class="fas fa-calendar-week"></i></div>
                <div class="info">
                    <div class="count"><?= number_format($counts['current_week']); ?></div>
                    <div class="label">This Week</div>
                </div>
            </a>
            <a href="?filter=current_month" class="summary-card amber <?= $currentFilter === 'current_month' ? 'active' : ''; ?>">
                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="info">
                    <div class="count"><?= number_format($counts['current_month']); ?></div>
                    <div class="label">This Month</div>
                </div>
            </a>
            <a href="?filter=last_month" class="summary-card gray <?= $currentFilter === 'last_month' ? 'active' : ''; ?>">
                <div class="icon"><i class="fas fa-calendar-minus"></i></div>
                <div class="info">
                    <div class="count"><?= number_format($counts['last_month']); ?></div>
                    <div class="label">Last Month</div>
                </div>
            </a>
            <a href="?filter=paid" class="summary-card success <?= $currentFilter === 'paid' ? 'active' : ''; ?>">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="info">
                    <div class="count"><?= number_format($counts['paid']); ?></div>
                    <div class="label">Paid Orders</div>
                </div>
            </a>
        </div>

        <!-- 2. Fulfilment Status -->
        <div class="dashboard-section">
            <div class="dashboard-section-title"><i class="fas fa-truck"></i>Fulfilment Status</div>
            <div class="status-cards">
                <a href="?filter=pending" class="status-card amber <?= $currentFilter === 'pending' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['pending']; ?></div>
                    <div class="label">Pending</div>
                </a>
                <a href="?filter=processing" class="status-card amber <?= $currentFilter === 'processing' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['processing']; ?></div>
                    <div class="label">Processing</div>
                </a>
                <a href="?filter=unshipped" class="status-card amber <?= $currentFilter === 'unshipped' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['unshipped']; ?></div>
                    <div class="label">Unshipped</div>
                </a>
                <a href="?filter=in_transit" class="status-card blue <?= $currentFilter === 'in_transit' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['in_transit']; ?></div>
                    <div class="label">In Transit</div>
                </a>
                <a href="?filter=delivered" class="status-card green <?= $currentFilter === 'delivered' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['delivered']; ?></div>
                    <div class="label">Delivered</div>
                </a>
            </div>
        </div>

        <!-- 3. Payment & Risk Monitoring -->
        <div class="dashboard-section">
            <div class="dashboard-section-title"><i class="fas fa-credit-card"></i>Payment & Risk Monitoring</div>
            <div class="status-cards cols-4">
                <a href="?filter=pending_payment" class="status-card amber <?= $currentFilter === 'pending_payment' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['pending_payment']; ?></div>
                    <div class="label">Pending Payment</div>
                </a>
                <a href="?filter=failed_payment" class="status-card red <?= $currentFilter === 'failed_payment' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['failed_payment']; ?></div>
                    <div class="label">Failed Transactions</div>
                </a>
                <a href="?filter=cod" class="status-card gray <?= $currentFilter === 'cod' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['cod']; ?></div>
                    <div class="label">COD Orders</div>
                </a>
                <a href="?filter=refund_pending" class="status-card purple <?= $currentFilter === 'refund_pending' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['refund_pending']; ?></div>
                    <div class="label">Refund Pending</div>
                </a>
            </div>
        </div>

        <!-- 4. Returns & Post-Delivery -->
        <div class="dashboard-section">
            <div class="dashboard-section-title"><i class="fas fa-undo"></i>Returns & Post-Delivery</div>
            <div class="status-cards cols-3">
                <a href="?filter=return_requested" class="status-card amber <?= $currentFilter === 'return_requested' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['return_requested']; ?></div>
                    <div class="label">Return Requested</div>
                </a>
                <a href="?filter=returned" class="status-card purple <?= $currentFilter === 'returned' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['returned']; ?></div>
                    <div class="label">Returned</div>
                </a>
                <a href="?filter=refunded" class="status-card green <?= $currentFilter === 'refunded' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['refunded']; ?></div>
                    <div class="label">Refunded</div>
                </a>
            </div>
        </div>

        <!-- 5. Exceptions & Attention Required -->
        <div class="dashboard-section">
            <div class="dashboard-section-title"><i class="fas fa-exclamation-triangle"></i>Exceptions & Attention Required</div>
            <div class="status-cards cols-4">
                <a href="?filter=cancelled" class="status-card red <?= $currentFilter === 'cancelled' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['cancelled']; ?></div>
                    <div class="label">Cancelled</div>
                </a>
                <a href="?filter=delayed" class="status-card red <?= $currentFilter === 'delayed' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['delayed']; ?></div>
                    <div class="label">Delayed (><?= $delayedDays; ?> days)</div>
                </a>
                <a href="?filter=failed_delivery" class="status-card red <?= $currentFilter === 'failed_delivery' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['failed_delivery']; ?></div>
                    <div class="label">Failed Delivery</div>
                </a>
                <a href="?filter=high_value" class="status-card blue <?= $currentFilter === 'high_value' ? 'active' : ''; ?>">
                    <div class="count"><?= $counts['high_value']; ?></div>
                    <div class="label">High-Value (₹<?= number_format($highValueThreshold); ?>+)</div>
                </a>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-card mt-4">
            <div class="orders-table-header">
                <div class="filter-active-title">
                    <h5><i class="fas fa-list me-2"></i><?= htmlspecialchars($filterTitle); ?></h5>
                    <?php if ($currentFilter !== 'all'): ?>
                        <a href="?filter=all" class="clear-filter"><i class="fas fa-times"></i> Clear Filter</a>
                    <?php endif; ?>
                    <span class="badge bg-secondary"><?= count($orders); ?> orders</span>
                </div>
            </div>
            <div class="p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Order Status</th>
                                <th>Payment</th>
                                <th>Courier</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <?php 
                                $details = get_order_with_items((int)$order['id']); 
                                $orderStatus = $order['order_status'] ?? ($order['status'] ?? 'pending');
                                $paymentStatus = $order['payment_status'] ?? 'pending';
                                $paymentMethod = $order['payment_method'] ?? 'N/A';
                                ?>
                                <tr>
                                    <td class="fw-semibold">#<?= (int)$order['id']; ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($order['customer'] ?? 'Guest'); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($order['customer_email'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($details['items'])): ?>
                                            <div class="order-items-list">
                                                <?php foreach (array_slice($details['items'], 0, 3) as $item): ?>
                                                    <div class="order-item-row">
                                                        <span class="item-name"><?= htmlspecialchars($item['name']); ?></span>
                                                        <span class="item-qty">×<?= (int)$item['quantity']; ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php if (count($details['items']) > 3): ?>
                                                    <small class="text-muted">+<?= count($details['items']) - 3; ?> more</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold">₹<?= number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge-order-status badge-<?= $orderStatus; ?>"><?= ucfirst($orderStatus); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-payment-status badge-payment-<?= $paymentStatus; ?>"><?= ucfirst($paymentStatus); ?></span>
                                        <br><small class="text-muted"><?= strtoupper($paymentMethod); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['courier_company'])): ?>
                                            <strong><?= htmlspecialchars($order['courier_company']); ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($order['tracking_id'] ?? 'N/A'); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?= date('M d, Y', strtotime($order['created_at'])); ?><br><?= date('h:i A', strtotime($order['created_at'])); ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-primary" onclick="openOrderModal(<?= (int)$order['id']; ?>, '<?= $orderStatus; ?>', '<?= htmlspecialchars($order['courier_company'] ?? ''); ?>', '<?= htmlspecialchars($order['tracking_id'] ?? ''); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="order_details.php?order_id=<?= (int)$order['id']; ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No orders found for this filter.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Order Update Modal -->
<div class="modal fade" id="orderUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= base_url('admin_actions.php'); ?>" method="post" id="orderUpdateForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Update Order Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_order_status" />
                    <input type="hidden" name="order_id" id="modalOrderId" />
                    
                    <div class="mb-3">
                        <label class="form-label">Order Status *</label>
                        <select name="status" id="modalStatus" class="form-select" required onchange="toggleCourierFields()">
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="return_requested">Return Requested</option>
                            <option value="returned">Returned</option>
                        </select>
                    </div>
                    
                    <div id="courierFields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Courier Company *</label>
                            <select name="courier_company" id="modalCourier" class="form-select">
                                <option value="">Select Courier</option>
                                <?php foreach ($couriers as $courier): ?>
                                    <option value="<?= htmlspecialchars($courier['name']); ?>"><?= htmlspecialchars($courier['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tracking ID *</label>
                            <input type="text" name="tracking_id" id="modalTracking" class="form-control" placeholder="Enter tracking number" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Report Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content export-modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-excel text-success me-2"></i>Export Orders Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="export_orders.php" method="get" id="exportForm">
                <div class="modal-body">
                    <!-- Date Range Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Date Range</label>
                        <div class="date-range-options">
                            <div class="form-check date-option">
                                <input class="form-check-input" type="radio" name="date_range" id="dateAll" value="all" checked>
                                <label class="form-check-label" for="dateAll">
                                    <i class="fas fa-infinity me-2"></i>All Time
                                </label>
                            </div>
                            <div class="form-check date-option">
                                <input class="form-check-input" type="radio" name="date_range" id="dateToday" value="today">
                                <label class="form-check-label" for="dateToday">
                                    <i class="fas fa-calendar-day me-2"></i>Today
                                </label>
                            </div>
                            <div class="form-check date-option">
                                <input class="form-check-input" type="radio" name="date_range" id="dateLast7" value="last_7_days">
                                <label class="form-check-label" for="dateLast7">
                                    <i class="fas fa-calendar-week me-2"></i>Last 7 Days
                                </label>
                            </div>
                            <div class="form-check date-option">
                                <input class="form-check-input" type="radio" name="date_range" id="dateCurrentMonth" value="current_month">
                                <label class="form-check-label" for="dateCurrentMonth">
                                    <i class="fas fa-calendar-alt me-2"></i>Current Month
                                </label>
                            </div>
                            <div class="form-check date-option">
                                <input class="form-check-input" type="radio" name="date_range" id="dateLastMonth" value="last_month">
                                <label class="form-check-label" for="dateLastMonth">
                                    <i class="fas fa-calendar-minus me-2"></i>Last Month
                                </label>
                            </div>
                            <div class="form-check date-option">
                                <input class="form-check-input" type="radio" name="date_range" id="dateCustom" value="custom">
                                <label class="form-check-label" for="dateCustom">
                                    <i class="fas fa-calendar-range me-2"></i>Custom Range
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Date Range (hidden by default) -->
                    <div class="custom-date-fields mb-4" id="customDateFields" style="display: none;">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" id="startDate">
                            </div>
                            <div class="col-6">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" id="endDate">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Order Status Filter</label>
                        <select class="form-select" name="status_filter">
                            <option value="all">All Orders</option>
                            <optgroup label="Fulfilment Status">
                                <option value="pending">Pending Orders</option>
                                <option value="processing">Processing Orders</option>
                                <option value="unshipped">Unshipped Orders</option>
                                <option value="in_transit">In Transit Orders</option>
                                <option value="delivered">Delivered Orders</option>
                            </optgroup>
                            <optgroup label="Payment & Risk Monitoring">
                                <option value="pending_payment">Pending Payment</option>
                                <option value="failed_payment">Failed Transactions</option>
                                <option value="cod">COD Orders</option>
                                <option value="refund_pending">Refund Pending</option>
                            </optgroup>
                            <optgroup label="Returns & Post-Delivery">
                                <option value="return_requested">Return Requested</option>
                                <option value="returned">Returned Orders</option>
                                <option value="refunded">Refunded Orders</option>
                            </optgroup>
                            <optgroup label="Exceptions & Attention Required">
                                <option value="cancelled">Cancelled Orders</option>
                                <option value="delayed">Delayed Orders (>3 days)</option>
                                <option value="failed_delivery">Failed Delivery</option>
                                <option value="high_value">High-Value Orders (₹5,000+)</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <!-- Export Info -->
                    <div class="export-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span>Report will be downloaded as Excel-compatible CSV file with order details, customer info, and payment summary.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-download me-2"></i>Download Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openOrderModal(orderId, currentStatus, courier, tracking) {
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalStatus').value = currentStatus;
    document.getElementById('modalCourier').value = courier || '';
    document.getElementById('modalTracking').value = tracking || '';
    toggleCourierFields();
    new bootstrap.Modal(document.getElementById('orderUpdateModal')).show();
}

function toggleCourierFields() {
    const status = document.getElementById('modalStatus').value;
    const courierFields = document.getElementById('courierFields');
    const isShipped = status === 'shipped';
    
    courierFields.style.display = isShipped ? 'block' : 'none';
    document.getElementById('modalCourier').required = isShipped;
    document.getElementById('modalTracking').required = isShipped;
}

// Export Modal - Toggle custom date fields
document.querySelectorAll('input[name="date_range"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const customFields = document.getElementById('customDateFields');
        if (this.value === 'custom') {
            customFields.style.display = 'block';
            document.getElementById('startDate').required = true;
            document.getElementById('endDate').required = true;
        } else {
            customFields.style.display = 'none';
            document.getElementById('startDate').required = false;
            document.getElementById('endDate').required = false;
        }
    });
});

// Set default end date to today
document.getElementById('endDate').value = new Date().toISOString().split('T')[0];
// Set default start date to 30 days ago
const startDefault = new Date();
startDefault.setDate(startDefault.getDate() - 30);
document.getElementById('startDate').value = startDefault.toISOString().split('T')[0];
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
