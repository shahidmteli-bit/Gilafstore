<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Barcode Inventory Dashboard';
$adminPage = 'barcode_management';

$db = get_db_connection();

// Ensure table exists to prevent crash on first visit
$db->exec("CREATE TABLE IF NOT EXISTS barcode_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL DEFAULT NULL,
    product_id INT NULL,
    weight_value DECIMAL(10,2) NULL,
    barcode_number VARCHAR(20) NOT NULL UNIQUE,
    sku_code VARCHAR(50) NOT NULL,
    status ENUM('Unused', 'Used', 'Added to Product Design', 'Planned for Use', 'Archived') DEFAULT 'Unused',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_product (product_id),
    INDEX idx_status (status),
    INDEX idx_barcode (barcode_number),
    INDEX idx_sku (sku_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// --- Migrate existing table ---
try { $db->exec("ALTER TABLE barcode_inventory MODIFY COLUMN category_id INT NULL DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE barcode_inventory ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE barcode_inventory ADD COLUMN deleted_by VARCHAR(100) NULL DEFAULT NULL"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE barcode_inventory ADD COLUMN edit_approved TINYINT(1) NOT NULL DEFAULT 0"); } catch(Exception $e) {}

// --- Edit Requests table ---
$db->exec("CREATE TABLE IF NOT EXISTS barcode_edit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode_id INT NOT NULL,
    requested_by VARCHAR(100) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by VARCHAR(100) NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_barcode (barcode_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// --- Fetch Edit Requests ---
$editRequests = $db->query("
    SELECT er.*, bi.barcode_number, bi.sku_code
    FROM barcode_edit_requests er
    JOIN barcode_inventory bi ON er.barcode_id = bi.id
    WHERE er.status = 'pending'
    ORDER BY er.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$pendingRequestCount = count($editRequests);

// Map: barcode_id => request status for quick lookup in table
$barcodeRequestMap = [];
$allRequests = $db->query("SELECT barcode_id, status FROM barcode_edit_requests ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allRequests as $r) {
    if (!isset($barcodeRequestMap[$r['barcode_id']])) {
        $barcodeRequestMap[$r['barcode_id']] = $r['status'];
    }
}

// --- Auto-purge: permanently delete barcodes in recycle bin older than 15 days ---
$db->exec("DELETE FROM barcode_inventory WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL 15 DAY)");

// --- Auto-fix: Legacy G-prefix removal for EAN-13 compliance ---
// Old system used 'G' prefix. New EAN/GTIN barcodes are pure 13-digit numbers.
// No auto-modification of barcode_number is performed.

// --- Helper: Calculate EAN-13 check digit for 12-digit input ---
if (!function_exists('ean13_check_digit')) {
    function ean13_check_digit(string $twelve): string {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$twelve[$i] * (($i % 2 === 0) ? 1 : 3);
        }
        return (string)((10 - ($sum % 10)) % 10);
    }
    function ean13_normalize(string $raw): string {
        // Strip non-digits (handles legacy 'G' prefix and any other chars)
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) === 12) {
            $digits .= ean13_check_digit($digits);
        }
        return $digits;
    }
}

// --- Recycle Bin Data ---
$recycleCount = $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE deleted_at IS NOT NULL")->fetchColumn();
$recycleBin   = $db->query("
    SELECT bi.*, c.name as category_name, p.name as product_name,
        DATEDIFF(DATE_ADD(bi.deleted_at, INTERVAL 15 DAY), NOW()) as days_left
    FROM barcode_inventory bi
    LEFT JOIN categories c ON bi.category_id = c.id
    LEFT JOIN products p ON bi.product_id = p.id
    WHERE bi.deleted_at IS NOT NULL
    ORDER BY bi.deleted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Pool & Category Stats (active only) ---
$poolCount     = $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE category_id IS NULL AND status = 'Unused' AND deleted_at IS NULL")->fetchColumn();
$categoryStats = $db->query("
    SELECT c.id, c.name,
        COUNT(bi.id) as total,
        SUM(CASE WHEN bi.status = 'Unused' THEN 1 ELSE 0 END) as unused,
        SUM(CASE WHEN bi.status = 'Used'   THEN 1 ELSE 0 END) as used
    FROM categories c
    LEFT JOIN barcode_inventory bi ON bi.category_id = c.id AND bi.deleted_at IS NULL
    GROUP BY c.id, c.name
    ORDER BY c.name
")->fetchAll(PDO::FETCH_ASSOC);

// --- 1. Handle Filters ---
$filterCategory = (int)($_GET['category_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

// --- 2. Build Query (active barcodes only) ---
$sql = "
    SELECT 
        bi.*,
        c.name as category_name,
        p.name as product_name
    FROM barcode_inventory bi
    LEFT JOIN categories c ON bi.category_id = c.id
    LEFT JOIN products p ON bi.product_id = p.id
    WHERE bi.deleted_at IS NULL
";
$params = [];

if ($filterCategory > 0) {
    $sql .= " AND bi.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($filterStatus)) {
    $sql .= " AND bi.status = ?";
    $params[] = $filterStatus;
}

if (!empty($searchQuery)) {
    $sql .= " AND (bi.barcode_number LIKE ? OR bi.sku_code LIKE ? OR p.name LIKE ?)";
    $term = "%$searchQuery%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY bi.created_at DESC";

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get Total Count
$countSql = str_replace("bi.*, c.name as category_name, p.name as product_name", "COUNT(*)", $sql);
$stmt = $db->prepare($countSql);
$stmt->execute($params);
$totalItems = $stmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Get Data
$sql .= " LIMIT $perPage OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$barcodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Get Stats for Dashboard (active only) ---
$stats = [
    'total'    => $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE deleted_at IS NULL")->fetchColumn(),
    'unused'   => $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE status = 'Unused' AND deleted_at IS NULL")->fetchColumn(),
    'used'     => $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE status = 'Used' AND deleted_at IS NULL")->fetchColumn(),
    'archived' => $db->query("SELECT COUNT(*) FROM barcode_inventory WHERE status = 'Archived' AND deleted_at IS NULL")->fetchColumn(),
    'dup_barcodes' => $db->query("SELECT COUNT(*) FROM (SELECT barcode_number FROM barcode_inventory WHERE deleted_at IS NULL GROUP BY barcode_number HAVING COUNT(*) > 1) t")->fetchColumn(),
    'dup_skus'     => $db->query("SELECT COUNT(*) FROM (SELECT sku_code FROM barcode_inventory WHERE deleted_at IS NULL AND sku_code != 'POOL' GROUP BY sku_code HAVING COUNT(*) > 1) t")->fetchColumn()
];

// Fetch Categories for Filter
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
@media print {
    .no-print, .admin-sidebar, .admin-topbar, .card-header button, .card-header a, form, .btn, .card-footer { display: none !important; }
    .admin-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .container-fluid { padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    body { background-color: white !important; }
}

/* ===== PAGE WRAP ===== */
.bm-wrap { width:100%; max-width:100%; overflow-x:hidden; padding:0 !important; font-family:'Inter','Segoe UI',system-ui,-apple-system,sans-serif; }

/* ===== STAT BAR ===== */
.bm-stats-row { display:flex; border-radius:6px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.12); background:#1e293b; /* fallback to prevent sub-pixel white gaps */ }
.bm-stat { flex:1 1 0%; padding:8px 12px; color:#fff; position:relative; min-width:0; margin:0; }
.bm-stat::after { content:''; position:absolute; right:0; top:20%; bottom:20%; width:1px; background:rgba(255,255,255,.2); }
.bm-stat:last-child::after { display:none; }
.bm-stat .bm-stat-num { font-size:1rem; font-weight:700; line-height:1.2; }
.bm-stat .bm-stat-lbl { font-size:0.55rem; text-transform:uppercase; letter-spacing:0.3px; opacity:0.9; white-space:nowrap; font-weight:500; margin-bottom:1px; }

/* ===== LEFT PANEL ===== */
.bm-left .card { border:0; margin-bottom:4px !important; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.06); border-left:3px solid transparent !important; transition:box-shadow .15s; }
.bm-left .card:hover { box-shadow:0 2px 6px rgba(0,0,0,.1); }
.bm-left .card-body { padding:6px 8px !important; }
.bm-left .card-header { padding:4px 8px !important; font-size:0.72rem; border-bottom:1px solid #f0f0f0; }
.bm-left h6 { font-size:0.72rem !important; margin-bottom:3px !important; font-weight:600 !important; color:#1e293b; }
.bm-left .form-label { font-size:0.65rem !important; margin-bottom:1px !important; line-height:1.3; color:#64748b; font-weight:500; }
.bm-left .form-select, .bm-left .form-control { font-size:0.68rem !important; padding:3px 6px !important; height:auto !important; border-radius:4px; border-color:#e2e8f0; }
.bm-left .form-select:focus, .bm-left .form-control:focus { border-color:#93c5fd; box-shadow:0 0 0 2px rgba(59,130,246,.1); }
.bm-left .input-group-text { font-size:0.65rem !important; padding:3px 5px !important; background:#f8fafc; border-color:#e2e8f0; color:#64748b; }
.bm-left .btn { font-size:0.65rem !important; padding:3px 7px !important; border-radius:4px; font-weight:500; }
.bm-left .form-text { font-size:0.58rem !important; margin-top:0 !important; }
.bm-left .badge { font-size:0.6rem !important; padding:2px 5px !important; border-radius:4px; font-weight:600; }
.bm-left .input-group-sm > .form-control { min-height:0; }
.bm-left .input-group-sm > .btn { min-height:0; }
.bm-left .form-select-sm { min-height:0; }

/* ===== RIGHT TABLE ===== */
.bm-tbl-card { border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:visible; }
.bm-tbl { width:100%; table-layout:fixed; border-collapse:collapse; line-height:1.25; font-size:0.72rem; }
.bm-tbl thead th { background:linear-gradient(135deg,#1e3a5f 0%,#2d5a8e 100%); color:#fff !important; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.4px; padding:7px 8px !important; white-space:nowrap; border:0; font-weight:600; }
.bm-tbl tbody td { padding:5px 8px !important; vertical-align:middle; border-bottom:1px solid #f1f5f9; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#334155; }
.bm-tbl tbody td.td-status { overflow:visible !important; position:relative; }
.bm-tbl .form-check-input { vertical-align:middle; }
.bm-tbl tbody tr:nth-child(even) { background:#fafbfc; }
.bm-tbl tbody tr:hover { background:#eff6ff; }
.bm-tbl tbody tr { transition:background .1s; }

/* Column widths — fixed layout, must total ~100% */
.bm-tbl .col-chk { width:3%; text-align:center; }
.bm-tbl .col-sku { width:15%; }
.bm-tbl .col-prod { width:25%; }
.bm-tbl .col-bc { width:20%; }
.bm-tbl .col-status { width:13%; overflow:visible !important; }
.bm-tbl .col-date { width:12%; }
.bm-tbl .col-act { width:12%; text-align:right; }

/* Action btns */
.bm-tbl .btn-act { font-size:0.55rem; padding:1px 3px; border-radius:3px; line-height:1.2; transition:all .15s; }
.bm-tbl .btn-act:hover { transform:scale(1.1); }

/* Status pill */
.st-pill { display:inline-flex; align-items:center; gap:2px; padding:1px 5px; border-radius:10px; font-size:0.57rem; font-weight:600; border:none; cursor:pointer; white-space:nowrap; transition:all .15s; line-height:1.3; }
.st-pill .dot { width:5px; height:5px; border-radius:50%; display:inline-block; }
.st-pill:hover { opacity:0.85; transform:translateY(-1px); }
.st-Unused { background:#dcfce7; color:#166534; }
.st-Unused .dot { background:#22c55e; }
.st-Used { background:#dbeafe; color:#1e40af; }
.st-Used .dot { background:#3b82f6; }
.st-Added-to-Product-Design { background:#fef3c7; color:#92400e; }
.st-Added-to-Product-Design .dot { background:#f59e0b; }
.st-Planned-for-Use { background:#ede9fe; color:#5b21b6; }
.st-Planned-for-Use .dot { background:#8b5cf6; }
.st-Archived { background:#f1f5f9; color:#475569; }
.st-Archived .dot { background:#94a3b8; }
.st-dropdown .dropdown-item { font-size:0.7rem; padding:4px 12px; transition:background .1s; }
.st-dropdown .dropdown-item:hover { background:#eff6ff; }

/* Category overview */
.bm-cat-tbl { width:100%; font-size:0.67rem; border-collapse:collapse; }
.bm-cat-tbl th, .bm-cat-tbl td { padding:3px 4px !important; }
.bm-cat-tbl thead th { font-size:0.58rem; font-weight:600; }
.bm-cat-tbl tbody tr:hover { background:#f8fafc; }

/* Recycle / Edit Requests */
.bm-sub-tbl { width:100%; font-size:0.67rem; }
.bm-sub-tbl th, .bm-sub-tbl td { padding:3px 5px !important; }

/* Pagination */
.bm-pag { flex-wrap:wrap !important; }
.bm-pag .page-link { font-size:0.68rem; padding:2px 8px; border-radius:4px; margin:0 1px; transition:all .15s; }
.bm-pag .page-item.active .page-link { background:#2563eb; border-color:#2563eb; }

/* Dropdown z-index */
.bm-tbl .dropdown { position:static; }
.bm-tbl .dropdown-menu { z-index:1060 !important; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.15) !important; }
.bm-tbl tbody tr { transform:none !important; position:relative; }

/* Form check compact */
.bm-tbl .form-check-input { width:13px; height:13px; margin:0; border-radius:3px; cursor:pointer; accent-color:#2563eb; }
.bm-tbl thead .form-check-input { accent-color:#fff; filter:brightness(1.3); }
.bm-left .form-check-input { width:13px; height:13px; }

/* Action bar buttons */
.bm-actions .btn { font-size:0.68rem; padding:3px 8px; border-radius:4px; font-weight:500; }

/* Filter bar */
.bm-filter-bar { background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:4px 8px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
.bm-flbl { font-size:0.62rem; font-weight:600; color:#64748b; white-space:nowrap; margin:0; }
.bm-fctl { font-size:0.67rem !important; padding:2px 6px !important; height:auto !important; border-radius:4px !important; border-color:#e2e8f0 !important; min-height:0 !important; }
.bm-fctl:focus { border-color:#93c5fd !important; box-shadow:0 0 0 2px rgba(59,130,246,.1) !important; }
.bm-fbtn { font-size:0.62rem !important; padding:2px 8px !important; border-radius:4px !important; }

/* Empty state compact */
.bm-tbl tbody td[colspan] { background:#fafbfc; }

/* ===== TABLE CARD MIN HEIGHT when empty ===== */
.bm-tbl-card .card-body { min-height:120px; }

/* ===== RESPONSIVE ===== */
@media (max-width:1400px) {
    .bm-stats-row { flex-wrap:wrap; }
    .bm-stat { flex:1 1 calc(25% - 0px); min-width:90px; }
}
@media (max-width:992px) {
    .bm-tbl .col-date { display:none; }
    .bm-tbl thead th:nth-child(6), .bm-tbl tbody td:nth-child(6) { display:none; }
    .bm-stats-row .bm-stat { flex:1 1 calc(33% - 0px); }
}
</style>

<div class="container-fluid py-1 bm-wrap">

    <!-- ===== STATS ROW ===== -->
    <div class="bm-stats-row mb-2 no-print">
        <div class="bm-stat" style="background:linear-gradient(135deg,#2563eb,#3b82f6);"><div><div class="bm-stat-lbl">Total Barcodes</div><div class="bm-stat-num"><?= number_format($stats['total']); ?></div></div></div>
        <div class="bm-stat" style="background:linear-gradient(135deg,#d97706,#f59e0b);"><div><div class="bm-stat-lbl">Unassigned Pool</div><div class="bm-stat-num"><?= number_format($poolCount); ?></div></div></div>
        <div class="bm-stat" style="background:linear-gradient(135deg,#059669,#10b981);"><div><div class="bm-stat-lbl">Assigned</div><div class="bm-stat-num"><?= number_format(max(0, $stats['unused'] - $poolCount)); ?></div></div></div>
        <div class="bm-stat" style="background:linear-gradient(135deg,#0891b2,#22d3ee);"><div><div class="bm-stat-lbl">In Use</div><div class="bm-stat-num"><?= number_format($stats['used']); ?></div></div></div>
        <div class="bm-stat" style="background:linear-gradient(135deg,#64748b,#94a3b8);"><div><div class="bm-stat-lbl">Archived</div><div class="bm-stat-num"><?= number_format($stats['archived']); ?></div></div></div>
        <div class="bm-stat" style="background:linear-gradient(135deg,<?= $stats['dup_barcodes'] > 0 ? '#dc2626,#ef4444' : '#16a34a,#22c55e'; ?>);"><div><div class="bm-stat-lbl">Dup Barcodes</div><div class="bm-stat-num"><?= number_format($stats['dup_barcodes']); ?></div></div></div>
        <div class="bm-stat" style="background:linear-gradient(135deg,<?= $stats['dup_skus'] > 0 ? '#dc2626,#ef4444' : '#16a34a,#22c55e'; ?>);"><div><div class="bm-stat-lbl">Dup SKUs</div><div class="bm-stat-num"><?= number_format($stats['dup_skus']); ?></div></div></div>
    </div>

    <!-- ===== HEADER ===== -->
    <div class="d-flex justify-content-between align-items-center mb-1 no-print">
        <h6 class="fw-bold mb-0" style="font-size:0.82rem;"><i class="fas fa-barcode me-1"></i>GTIN/EAN Inventory</h6>
    </div>

    <!-- ===== FILTER + ACTION BAR ===== -->
    <div class="bm-filter-bar mb-1 no-print d-flex align-items-center" style="white-space:nowrap;">
        <form method="GET" class="d-flex align-items-center gap-2" style="white-space:nowrap;">
            <label class="bm-flbl">Category</label>
            <select name="category_id" class="form-select form-select-sm bm-fctl" style="width:120px;">
                <option value="">All</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id']; ?>" <?= $filterCategory == $cat['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <label class="bm-flbl">Status</label>
            <select name="status" class="form-select form-select-sm bm-fctl" style="width:110px;">
                <option value="">All</option>
                <?php foreach (['Unused','Used','Added to Product Design','Planned for Use','Archived'] as $s): ?>
                <option value="<?= $s; ?>" <?= $filterStatus == $s ? 'selected' : ''; ?>><?= $s; ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control form-control-sm bm-fctl" style="width:150px;" placeholder="Search..." value="<?= htmlspecialchars($searchQuery); ?>">
            <button class="btn btn-sm btn-primary bm-fbtn" type="submit"><i class="fas fa-search"></i></button>
            <a href="barcode_management.php" class="btn btn-sm btn-outline-secondary bm-fbtn"><i class="fas fa-undo"></i></a>
        </form>
        <div class="d-flex gap-1 align-items-center ms-auto bm-actions">
            <button class="btn btn-sm btn-info text-white" id="syncToProductsBtn" title="Sync all Used barcodes to their product EAN fields"><i class="fas fa-sync-alt me-1"></i>Sync to Products</button>
            <?php if ($stats['dup_barcodes'] > 0 || $stats['dup_skus'] > 0): ?>
            <button class="btn btn-sm btn-warning" id="fixDuplicatesBtn" title="Fix duplicate barcodes and SKUs"><i class="fas fa-wrench me-1"></i>Fix Duplicates</button>
            <?php endif; ?>
            <button class="btn btn-sm btn-danger" id="deleteAllBtn" <?= $stats['total'] == 0 ? 'disabled' : ''; ?>><i class="fas fa-lock me-1"></i><i class="fas fa-trash-alt"></i> Delete All</button>
            <button class="btn btn-sm btn-outline-danger d-none" id="deleteSelectedBtn"><i class="fas fa-trash"></i> (<span id="selectedCount">0</span>)</button>
            <?php if ($recycleCount > 0): ?>
            <a href="#recycleBinSection" class="btn btn-sm btn-outline-secondary"><i class="fas fa-recycle"></i> Bin <span class="badge bg-danger"><?= $recycleCount; ?></span></a>
            <?php endif; ?>
            <?php if ($pendingRequestCount > 0): ?>
            <a href="#editRequestsSection" class="btn btn-sm btn-warning"><i class="fas fa-inbox"></i> Requests <span class="badge bg-danger"><?= $pendingRequestCount; ?></span></a>
            <?php endif; ?>
            <a href="barcode_generator.php" class="btn btn-sm btn-dark"><i class="fas fa-plus me-1"></i>Add GTIN/EAN</a>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-success dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-file-alt me-1"></i>Report</button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item fw-bold" href="download_barcodes_zip.php?category_id=<?= $filterCategory; ?>&status=<?= urlencode($filterStatus); ?>&q=<?= urlencode($searchQuery); ?>"><i class="fas fa-file-archive text-warning me-2"></i>Download Barcodes (ZIP)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="export_used_barcodes.php?format=pdf&category_id=<?= $filterCategory; ?>&status=<?= urlencode($filterStatus); ?>&q=<?= urlencode($searchQuery); ?>" target="_blank"><i class="fas fa-file-pdf text-danger me-2"></i>PDF Report (with Barcode Image)</a></li>
                    <li><a class="dropdown-item" href="export_used_barcodes.php?format=excel&category_id=<?= $filterCategory; ?>&status=<?= urlencode($filterStatus); ?>&q=<?= urlencode($searchQuery); ?>"><i class="fas fa-file-excel text-success me-2"></i>Excel Summary</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportTableToCSV('barcode_inventory.csv'); return false;"><i class="fas fa-table text-secondary me-2"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="#" onclick="window.print(); return false;"><i class="fas fa-print text-secondary me-2"></i>Print</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ===== 2-COLUMN LAYOUT ===== -->
    <div class="row g-2">

        <!-- LEFT: Controls -->
        <div class="col-xl-2 col-lg-3 bm-left">

            <!-- Pool -->
            <div class="card" style="border-left-color:#f59e0b !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="fw-bold mb-0"><i class="fas fa-layer-group me-1 text-warning"></i>Pool</h6>
                        <span class="badge bg-warning text-dark"><?= number_format($poolCount); ?></span>
                    </div>
                    <a href="barcode_generator.php" class="btn btn-sm btn-warning w-100 mb-1"><i class="fas fa-plus me-1"></i>Add GTIN/EAN Record</a>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Per cat</span>
                        <input type="number" class="form-control" id="perCategoryInput" value="25" min="1" max="999">
                        <button class="btn btn-success" id="autoDistributeBtn" <?= $poolCount == 0 ? 'disabled' : ''; ?>><i class="fas fa-random me-1"></i>Distribute</button>
                    </div>
                </div>
            </div>

            <!-- Assign -->
            <div class="card" style="border-left-color:#3b82f6 !important;">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-arrow-right me-1 text-primary"></i>Assign &rarr; Category</h6>
                    <select class="form-select form-select-sm mb-1" id="assignToCatSelect">
                        <option value="">-- Category --</option>
                        <?php foreach ($categoryStats as $cat): ?>
                        <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?> (<?= $cat['unused']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Qty</span>
                        <input type="number" class="form-control" id="assignQtyInput" value="25" min="1" max="<?= $poolCount; ?>">
                        <button class="btn btn-primary" id="assignToCatBtn" <?= $poolCount == 0 ? 'disabled' : ''; ?>><i class="fas fa-check me-1"></i>Assign</button>
                    </div>
                </div>
            </div>

            <!-- Transfer -->
            <div class="card" style="border-left-color:#06b6d4 !important;">
                <div class="card-body">
                    <h6 class="fw-bold mb-1"><i class="fas fa-exchange-alt me-1 text-info"></i>Transfer</h6>
                    <label class="form-label mb-0">From</label>
                    <select class="form-select form-select-sm mb-1" id="transferFromSelect">
                        <option value="pool">Pool (<?= $poolCount; ?>)</option>
                        <?php foreach ($categoryStats as $cat): ?>
                        <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?> (<?= $cat['unused']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label mb-0">To</label>
                    <select class="form-select form-select-sm mb-1" id="transferToSelect">
                        <option value="">-- Target --</option>
                        <?php foreach ($categoryStats as $cat): ?>
                        <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Qty</span>
                        <input type="number" class="form-control" id="transferQtyInput" value="10" min="1">
                        <button class="btn btn-info text-white" id="transferBtn"><i class="fas fa-exchange-alt me-1"></i>Go</button>
                    </div>
                </div>
            </div>

            <!-- Category Overview -->
            <div class="card" style="border-left-color:#8b5cf6 !important;">
                <div class="card-header bg-white py-1 d-flex justify-content-between align-items-center">
                    <span class="fw-bold" style="font-size:0.78rem;"><i class="fas fa-tags me-1"></i>Categories</span>
                    <span class="badge bg-secondary"><?= count($categoryStats); ?></span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0 bm-cat-tbl">
                        <thead class="text-white" style="background:var(--gradient-primary, #1e3a5f);">
                            <tr><th class="ps-2">Name</th><th class="text-center">Total</th><th class="text-center">Free</th><th class="text-center">Used</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryStats as $cat): ?>
                            <tr>
                                <td class="ps-2 fw-semibold"><?= htmlspecialchars($cat['name']); ?></td>
                                <td class="text-center"><?= (int)$cat['total']; ?></td>
                                <td class="text-center"><span class="badge bg-success"><?= (int)$cat['unused']; ?></span></td>
                                <td class="text-center"><span class="badge bg-info"><?= (int)$cat['used']; ?></span></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-secondary quick-return" data-cat-id="<?= $cat['id']; ?>" data-cat-name="<?= htmlspecialchars($cat['name']); ?>" title="Return to pool" <?= $cat['unused'] == 0 ? 'disabled' : ''; ?> style="font-size:0.6rem;padding:1px 5px;"><i class="fas fa-undo"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($categoryStats)): ?>
                            <tr><td colspan="5" class="text-center py-2 text-muted small">No categories.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>


        </div><!-- /col left -->

        <!-- RIGHT: Table -->
        <div class="col-xl-10 col-lg-9">
            <div class="card border-0 bm-tbl-card">
                <div class="card-body p-0">
                    <div style="width:100%;overflow-x:auto;">
                        <table class="bm-tbl" id="barcodeTable">
                            <thead>
                                <tr>
                                    <th class="col-chk ps-1"><input type="checkbox" class="form-check-input" id="selectAll" title="Select All"></th>
                                    <th class="col-sku">SKU</th>
                                    <th class="col-prod">Product</th>
                                    <th class="col-bc">Barcode</th>
                                    <th class="col-status">Status</th>
                                    <th class="col-date">Created</th>
                                    <th class="col-act">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($barcodes) > 0): ?>
                                    <?php foreach ($barcodes as $item): ?>
                                    <tr>
                                        <td class="ps-1"><input type="checkbox" class="form-check-input row-checkbox" value="<?= $item['id']; ?>"></td>
                                        <td class="fw-bold font-monospace text-primary" style="font-size:0.63rem;"><?= htmlspecialchars($item['sku_code']); ?></td>
                                        <td title="<?= htmlspecialchars($item['product_name'] . ' / ' . $item['category_name']); ?>"><?= htmlspecialchars($item['product_name'] ?: '-'); ?></td>
                                        <td class="font-monospace" style="font-size:0.63rem;"><?php
                                            // Normalize to full 13-digit EAN with check digit (strips legacy G prefix)
                                            $displayBarcode = isset($item['barcode_number']) ? ean13_normalize($item['barcode_number']) : '-';
                                            echo htmlspecialchars($displayBarcode);
                                        ?></td>
                                        <td class="td-status">
                                            <?php $stClass = 'st-' . str_replace(' ', '-', $item['status']); ?>
                                            <div class="dropdown" style="position:static;">
                                                <button type="button" class="st-pill <?= $stClass; ?> dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="statusBtn<?= $item['id']; ?>">
                                                    <span class="dot"></span><?= htmlspecialchars($item['status']); ?>
                                                </button>
                                                <ul class="dropdown-menu shadow st-dropdown py-1">
                                                    <?php foreach (['Unused','Used','Added to Product Design','Planned for Use','Archived'] as $s): ?>
                                                    <li><a class="dropdown-item status-change-item <?= $item['status']==$s?'fw-bold':''; ?>" href="#" data-id="<?= $item['id']; ?>" data-status="<?= $s; ?>" data-class="st-<?= str_replace(' ','-',$s); ?>"><?= $item['status']==$s?'&check; ':''; ?><?= htmlspecialchars($s); ?></a></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </td>
                                        <td class="text-muted" style="font-size:0.63rem;"><?= date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        <td class="text-end pe-1">
                                            <?php
                                            $isUsed = $item['status'] === 'Used';
                                            $editApproved = !empty($item['edit_approved']);
                                            $reqStatus = $barcodeRequestMap[$item['id']] ?? null;
                                            ?>
                                            <?php if (!$isUsed || $editApproved): ?>
                                            <button class="btn btn-outline-primary btn-act btn-assign-product" data-id="<?= $item['id']; ?>" data-cat="<?= $item['category_id']; ?>" data-prod="<?= $item['product_id']; ?>" title="Assign"><i class="fas <?= $editApproved?'fa-unlock-alt':'fa-tag'; ?>"></i></button>
                                            <?php elseif ($reqStatus === 'pending'): ?>
                                            <button class="btn btn-outline-warning btn-act" disabled title="Pending"><i class="fas fa-clock"></i></button>
                                            <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-act btn-request-edit" data-id="<?= $item['id']; ?>" data-sku="<?= htmlspecialchars($item['sku_code']); ?>" title="Request edit"><i class="fas fa-lock"></i></button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-secondary btn-act" onclick="downloadBarcode(<?= $item['id']; ?>,'<?= htmlspecialchars($displayBarcode); ?>')" title="Download"><i class="fas fa-download"></i></button>
                                            <button class="btn btn-outline-danger btn-act" onclick="deleteOne(<?= $item['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center py-3 text-muted" style="font-size:0.75rem;"><i class="fas fa-barcode me-1" style="opacity:0.3;"></i> No barcodes found. <a href="barcode_generator.php" class="fw-bold text-primary">Add GTIN/EAN</a></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white py-1 d-flex justify-content-between align-items-center" style="border-top:1px solid #f1f5f9;">
                    <span class="text-muted" style="font-size:0.65rem;">Page <?= $page; ?> of <?= $totalPages; ?> &middot; <?= number_format($totalItems); ?> records</span>
                    <nav>
                        <ul class="pagination mb-0 bm-pag">
                            <li class="page-item <?= $page<=1?'disabled':''; ?>"><a class="page-link" href="?page=<?= $page-1; ?>&category_id=<?= $filterCategory; ?>&status=<?= $filterStatus; ?>&q=<?= urlencode($searchQuery); ?>">&laquo;</a></li>
                            <?php
                            $range = 3;
                            $start = max(1, $page - $range);
                            $end = min($totalPages, $page + $range);
                            if ($start > 1) echo '<li class="page-item"><a class="page-link" href="?page=1&category_id='.$filterCategory.'&status='.$filterStatus.'&q='.urlencode($searchQuery).'">1</a></li>';
                            if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                            for ($i=$start; $i<=$end; $i++):
                            ?>
                            <li class="page-item <?= $page==$i?'active':''; ?>"><a class="page-link" href="?page=<?= $i; ?>&category_id=<?= $filterCategory; ?>&status=<?= $filterStatus; ?>&q=<?= urlencode($searchQuery); ?>"><?= $i; ?></a></li>
                            <?php endfor;
                            if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                            if ($end < $totalPages) echo '<li class="page-item"><a class="page-link" href="?page='.$totalPages.'&category_id='.$filterCategory.'&status='.$filterStatus.'&q='.urlencode($searchQuery).'">'.$totalPages.'</a></li>';
                            ?>
                            <li class="page-item <?= $page>=$totalPages?'disabled':''; ?>"><a class="page-link" href="?page=<?= $page+1; ?>&category_id=<?= $filterCategory; ?>&status=<?= $filterStatus; ?>&q=<?= urlencode($searchQuery); ?>">&raquo;</a></li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recycle Bin -->
            <?php if ($recycleCount > 0): ?>
            <div class="card shadow-sm border-0 mt-2" id="recycleBinSection" style="border-left:3px solid #dc3545 !important;">
                <div class="card-header d-flex justify-content-between align-items-center py-2" style="background:#fff5f5;">
                    <span class="fw-bold text-danger" style="font-size:0.82rem;"><i class="fas fa-recycle me-1"></i>Recycle Bin <span class="badge bg-danger ms-1"><?= $recycleCount; ?></span></span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-success" id="restoreAllBtn" style="font-size:0.7rem;padding:2px 6px;"><i class="fas fa-undo me-1"></i>Restore All</button>
                        <button class="btn btn-sm btn-danger" id="purgeAllBtn" style="font-size:0.7rem;padding:2px 6px;"><i class="fas fa-fire me-1"></i>Empty</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 bm-sub-tbl">
                        <thead class="table-danger small text-uppercase"><tr><th class="ps-2">SKU</th><th>Barcode</th><th>Category</th><th>Deleted</th><th class="text-center">Days</th><th class="text-end pe-2">Act</th></tr></thead>
                        <tbody>
                            <?php foreach ($recycleBin as $rb): ?>
                            <tr>
                                <td class="ps-2 font-monospace fw-bold text-danger"><?= htmlspecialchars($rb['sku_code']); ?></td>
                                <td class="font-monospace"><?= htmlspecialchars($rb['barcode_number'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($rb['category_name'] ?? 'Pool'); ?></td>
                                <td class="text-muted"><?= date('M d H:i', strtotime($rb['deleted_at'])); ?></td>
                                <td class="text-center"><?php $dl=max(0,(int)$rb['days_left']); ?><span class="badge <?= $dl<=3?'bg-danger':($dl<=7?'bg-warning text-dark':'bg-secondary'); ?>"><?= $dl; ?>d</span></td>
                                <td class="text-end pe-2">
                                    <button class="btn btn-success btn-act btn-restore-one" data-id="<?= $rb['id']; ?>" title="Restore"><i class="fas fa-undo"></i></button>
                                    <button class="btn btn-danger btn-act btn-purge-one" data-id="<?= $rb['id']; ?>" title="Purge"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit Requests -->
            <?php if (!empty($editRequests)): ?>
            <div class="card shadow-sm border-0 mt-2" id="editRequestsSection" style="border-left:3px solid #f59e0b !important;">
                <div class="card-header py-2" style="background:#fffbf0;">
                    <span class="fw-bold text-warning" style="font-size:0.82rem;"><i class="fas fa-inbox me-1"></i>Edit Requests <span class="badge bg-warning text-dark ms-1"><?= $pendingRequestCount; ?></span></span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 bm-sub-tbl">
                        <thead class="table-warning small text-uppercase"><tr><th class="ps-2">Barcode</th><th>SKU</th><th>By</th><th>Reason</th><th>Date</th><th class="text-end pe-2">Act</th></tr></thead>
                        <tbody>
                            <?php foreach ($editRequests as $req): ?>
                            <tr>
                                <td class="ps-2 font-monospace"><?= htmlspecialchars($req['barcode_number'] ?? '-'); ?></td>
                                <td class="font-monospace fw-bold text-primary"><?= htmlspecialchars($req['sku_code']); ?></td>
                                <td><?= htmlspecialchars($req['requested_by']); ?></td>
                                <td class="text-muted" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($req['reason']); ?></td>
                                <td class="text-muted"><?= date('M d H:i', strtotime($req['created_at'])); ?></td>
                                <td class="text-end pe-2">
                                    <button class="btn btn-success btn-act btn-approve-request" data-id="<?= $req['id']; ?>" title="Approve"><i class="fas fa-check"></i></button>
                                    <button class="btn btn-danger btn-act btn-reject-request" data-id="<?= $req['id']; ?>" title="Reject"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /col-lg-9 -->
    </div><!-- /row -->
</div><!-- /bm-wrap -->

<!-- ===== REQUEST EDIT MODAL ===== -->
<div class="modal fade" id="requestEditModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-lock me-2"></i>Request Edit Permission</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reqEditBarcodeId">
                <p class="small text-muted mb-2">This barcode is <strong>locked</strong> (status: Used). Provide a reason to request edit permission from admin.</p>
                <label class="form-label fw-bold small">SKU: <span id="reqEditSku" class="font-monospace text-primary"></span></label>
                <textarea class="form-control mt-2" id="reqEditReason" rows="3" placeholder="Reason for editing this assigned barcode..."></textarea>
                <div class="form-text text-danger d-none" id="reqEditError">Please enter a reason.</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-warning" id="reqEditSubmitBtn"><i class="fas fa-paper-plane me-1"></i>Submit Request</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== SECURITY LOCK MODAL ===== -->
<div class="modal fade" id="securityLockModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-lock me-2"></i>Security Confirmation</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2 small mb-3"><i class="fas fa-exclamation-triangle me-1"></i><span id="secLockDesc">This action will move barcodes to the Recycle Bin.</span></div>
                <label class="form-label fw-bold small">Type <code>CONFIRM</code> to proceed:</label>
                <input type="text" class="form-control" id="secLockInput" placeholder="Type CONFIRM here..." autocomplete="off">
                <div class="form-text text-danger d-none" id="secLockError">Incorrect. Type exactly: CONFIRM</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="secLockConfirmBtn" disabled><i class="fas fa-unlock me-1"></i>Confirm Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== ASSIGN TO PRODUCT MODAL ===== -->
<div class="modal fade" id="assignProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--gradient-primary);">
                <h5 class="modal-title text-white"><i class="fas fa-tag me-2"></i>Assign Barcode to Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="apBarcodeId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">1. Category</label>
                        <select class="form-select" id="apCategorySelect">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id']; ?>" data-name="<?= htmlspecialchars($cat['name']); ?>" data-code="<?= htmlspecialchars($cat['category_code'] ?? ''); ?>"><?= htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">2. Product</label>
                        <select class="form-select" id="apProductSelect" disabled><option value="">-- Select Category First --</option></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">3. Weight / Size</label>
                        <select class="form-select" id="apWeightSelect" disabled><option value="">-- Select Product First --</option></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">SKU Preview</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">G</span>
                            <input type="text" class="form-control font-monospace" id="apSkuPreview" readonly placeholder="Select all fields...">
                        </div>
                    </div>
                </div>
                <div id="apLoadingMsg" class="text-muted small mt-2 d-none"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="apSaveBtn" disabled><i class="fas fa-save me-2"></i>Save Assignment</button>
            </div>
        </div>
    </div>
</div>

<?php
function getStatusStyle($status) {
    switch ($status) {
        case 'Unused': return 'background-color: #e8f5e9; color: #198754; border-color: #c3e6cb;';
        case 'Used': return 'background-color: #cfe2ff; color: #0d6efd; border-color: #b6d4fe;';
        case 'Archived': return 'background-color: #e2e3e5; color: #41464b; border-color: #d3d6d8;';
        case 'Added to Product Design': return 'background-color: #fff3cd; color: #664d03; border-color: #ffecb5;';
        default: return '';
    }
}
?>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

<!-- Dependencies (must load AFTER jQuery from admin_footer) -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    // Init Select2
    $('.select2').select2({ theme: 'bootstrap-5' });

    // Render Barcodes
    try {
        JsBarcode(".barcode-mini").init();
    } catch(e) {
        console.warn("Barcode render error (likely invalid numeric format):", e);
    }

    // Select All / Delete handlers
    $('#selectAll').on('change', function() {
        $('.row-checkbox').prop('checked', this.checked);
        updateSelectedCount();
    });

    $(document).on('change', '.row-checkbox', function() {
        updateSelectedCount();
        if(!this.checked) $('#selectAll').prop('checked', false);
    });

    function updateSelectedCount() {
        const count = $('.row-checkbox:checked').length;
        $('#selectedCount').text(count);
        if(count > 0) {
            $('#deleteSelectedBtn').removeClass('d-none');
        } else {
            $('#deleteSelectedBtn').addClass('d-none');
        }
    }

    // ===== SECURITY LOCK SYSTEM =====
    let secLockAction = null;
    const secModal = new bootstrap.Modal(document.getElementById('securityLockModal'));

    function openSecLock(description, callback) {
        secLockAction = callback;
        $('#secLockDesc').text(description);
        $('#secLockInput').val('');
        $('#secLockError').addClass('d-none');
        $('#secLockConfirmBtn').prop('disabled', true);
        secModal.show();
    }

    $('#secLockInput').on('input', function() {
        const val = $(this).val().trim();
        const ok = val === 'CONFIRM';
        $('#secLockConfirmBtn').prop('disabled', !ok);
        $('#secLockError').toggleClass('d-none', ok || val.length === 0);
    });

    $('#secLockConfirmBtn').on('click', function() {
        if ($('#secLockInput').val().trim() !== 'CONFIRM') return;
        secModal.hide();
        if (typeof secLockAction === 'function') secLockAction();
        secLockAction = null;
    });

    // Sync Barcodes to Products
    $('#syncToProductsBtn').on('click', function() {
        if (!confirm('This will sync all Used barcodes to their assigned product EAN fields.\n\nEach barcode will be mapped to its specific product/weight variant. Continue?')) return;
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Syncing...');
        $.getJSON('sync_barcodes_to_products.php', function(res) {
            if (res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert('Error: ' + res.error);
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i>Sync to Products');
            }
        }).fail(function() {
            alert('Network error');
            btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i>Sync to Products');
        });
    });

    // Fix Duplicates
    $('#fixDuplicatesBtn').on('click', function() {
        if (!confirm('This will fix all duplicate barcodes (regenerate new unique ones) and duplicate SKUs (add suffix -2, -3, etc.).\n\nThe oldest entry is kept unchanged. Continue?')) return;
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Fixing...');
        $.getJSON('fix_duplicates.php', function(res) {
            if (res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert('Error: ' + res.error);
                btn.prop('disabled', false).html('<i class="fas fa-wrench me-1"></i>Fix Duplicates');
            }
        }).fail(function() {
            alert('Network error');
            btn.prop('disabled', false).html('<i class="fas fa-wrench me-1"></i>Fix Duplicates');
        });
    });

    // Delete All (security locked)
    $('#deleteAllBtn').on('click', function() {
        openSecLock('Move ALL <?= $stats['total']; ?> barcodes to Recycle Bin. They will be auto-purged after 15 days.', function() {
            $.post('delete_barcodes.php', { action: 'delete_all' }, function(res) {
                if(res.success) { alert(res.message); location.reload(); }
                else { alert('Error: ' + res.error); }
            }, 'json').fail(function() { alert('Network error'); });
        });
    });

    // Delete Selected (security locked)
    $('#deleteSelectedBtn').on('click', function() {
        const ids = $('.row-checkbox:checked').map(function() { return this.value; }).get();
        if(ids.length === 0) return;
        openSecLock('Move ' + ids.length + ' selected barcode(s) to Recycle Bin.', function() {
            $.post('delete_barcodes.php', { action: 'delete_selected', ids: ids }, function(res) {
                if(res.success) { alert(res.message); location.reload(); }
                else { alert('Error: ' + res.error); }
            }, 'json').fail(function() { alert('Network error'); });
        });
    });

    // Restore All
    $('#restoreAllBtn').on('click', function() {
        if(!confirm('Restore ALL barcodes from Recycle Bin?')) return;
        $.post('delete_barcodes.php', { action: 'restore_all' }, function(res) {
            if(res.success) { alert(res.message); location.reload(); }
            else { alert('Error: ' + res.error); }
        }, 'json');
    });

    // Purge All
    $('#purgeAllBtn').on('click', function() {
        openSecLock('Permanently delete ALL barcodes in the Recycle Bin. This CANNOT be undone!', function() {
            $.post('delete_barcodes.php', { action: 'purge_all' }, function(res) {
                if(res.success) { alert(res.message); location.reload(); }
                else { alert('Error: ' + res.error); }
            }, 'json');
        });
    });

    // Restore One
    $(document).on('click', '.btn-restore-one', function() {
        const id = $(this).data('id');
        $.post('delete_barcodes.php', { action: 'restore_one', id: id }, function(res) {
            if(res.success) { location.reload(); }
            else { alert('Error: ' + res.error); }
        }, 'json');
    });

    // Purge One
    $(document).on('click', '.btn-purge-one', function() {
        const id = $(this).data('id');
        openSecLock('Permanently delete this barcode. This CANNOT be undone!', function() {
            $.post('delete_barcodes.php', { action: 'purge_one', id: id }, function(res) {
                if(res.success) { location.reload(); }
                else { alert('Error: ' + res.error); }
            }, 'json');
        });
    });

    // ===== POOL MANAGEMENT HANDLERS =====
    function poolPost(data, btnEl, originalHtml) {
        return $.post('assign_barcodes.php', data, function(res) {
            btnEl.prop('disabled', false).html(originalHtml);
            if(res.success) {
                alert(res.message);
                location.reload();
            } else {
                alert('Error: ' + (res.error || 'Unknown error'));
            }
        }, 'json').fail(function() {
            btnEl.prop('disabled', false).html(originalHtml);
            alert('Network error. Please try again.');
        });
    }

    // Auto-Distribute
    $('#autoDistributeBtn').on('click', function() {
        const perCat = parseInt($('#perCategoryInput').val()) || 25;
        const btn = $(this), orig = btn.html();
        if(!confirm(`Distribute ${perCat} barcodes to EACH category from the pool?\n\nTotal needed: ${perCat} × <?= count($categoryStats); ?> = ${perCat * <?= count($categoryStats); ?>} barcodes`)) return;
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>...');
        poolPost({ action: 'auto_distribute', per_category: perCat }, btn, orig);
    });

    // Assign to Category
    $('#assignToCatBtn').on('click', function() {
        const catId = $('#assignToCatSelect').val();
        const qty = parseInt($('#assignQtyInput').val()) || 0;
        const catName = $('#assignToCatSelect option:selected').text();
        if(!catId) { alert('Please select a category.'); return; }
        if(qty < 1) { alert('Enter a valid quantity.'); return; }
        const btn = $(this), orig = btn.html();
        if(!confirm(`Assign ${qty} barcodes from pool to "${catName}"?`)) return;
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>...');
        poolPost({ action: 'assign_to_category', category_id: catId, quantity: qty }, btn, orig);
    });

    // Transfer Between Categories
    $('#transferBtn').on('click', function() {
        const from = $('#transferFromSelect').val();
        const to = $('#transferToSelect').val();
        const qty = parseInt($('#transferQtyInput').val()) || 0;
        if(!to) { alert('Please select a target category.'); return; }
        if(from === to) { alert('Source and target cannot be the same.'); return; }
        if(qty < 1) { alert('Enter a valid quantity.'); return; }
        const fromName = $('#transferFromSelect option:selected').text();
        const toName = $('#transferToSelect option:selected').text();
        if(!confirm(`Transfer ${qty} barcodes from "${fromName}" to "${toName}"?`)) return;
        const btn = $(this), orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>...');
        poolPost({ action: 'transfer', from_category: from, to_category: to, quantity: qty }, btn, orig);
    });

    // Return to Pool
    $(document).on('click', '.quick-return', function() {
        const catId = $(this).data('cat-id');
        const catName = $(this).data('cat-name');
        const qty = prompt(`How many unused barcodes to return from "${catName}" to pool?`, '10');
        if(qty === null) return;
        const qtyInt = parseInt(qty);
        if(isNaN(qtyInt) || qtyInt < 1) { alert('Invalid quantity.'); return; }
        const btn = $(this), orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>...');
        poolPost({ action: 'return_to_pool', category_id: catId, quantity: qtyInt }, btn, orig);
    });

    // ===== ASSIGN TO PRODUCT MODAL =====
    let apCatCode = '', apProdCode = '', apWtCode = '';

    function apUpdateSku() {
        const sku = apCatCode + apProdCode + (apWtCode ? '-' + apWtCode : '');
        $('#apSkuPreview').val(sku);
        const ready = apCatCode && apProdCode && apWtCode;
        $('#apSaveBtn').prop('disabled', !ready);
    }

    // Open modal
    $(document).on('click', '.btn-assign-product', function() {
        const id = $(this).data('id');
        const catId = $(this).data('cat');
        apCatCode = ''; apProdCode = ''; apWtCode = '';
        $('#apBarcodeId').val(id);
        $('#apProductSelect').empty().append('<option value="">-- Select Category First --</option>').prop('disabled', true);
        $('#apWeightSelect').empty().append('<option value="">-- Select Product First --</option>').prop('disabled', true);
        $('#apSkuPreview').val('');
        $('#apSaveBtn').prop('disabled', true);
        if (catId) {
            $('#apCategorySelect').val(catId).trigger('change');
        } else {
            $('#apCategorySelect').val('');
        }
        new bootstrap.Modal(document.getElementById('assignProductModal')).show();
    });

    // Category change in modal
    $('#apCategorySelect').on('change', function() {
        const catId = $(this).val();
        const opt = $(this).find(':selected');
        apCatCode = opt.attr('data-code') || opt.attr('data-name')?.substring(0, 2).toUpperCase() || '';
        apProdCode = ''; apWtCode = '';
        apUpdateSku();

        const $prod = $('#apProductSelect').empty().append('<option>Loading...</option>').prop('disabled', true);
        $('#apWeightSelect').empty().append('<option value="">-- Select Product First --</option>').prop('disabled', true);

        if (!catId) return;
        $('#apLoadingMsg').removeClass('d-none');
        $.get('get_products_by_category.php', { category_id: catId }, function(res) {
            $prod.empty().append('<option value="">-- Select Product --</option>');
            if (res.products && res.products.length) {
                res.products.forEach(p => {
                    $prod.append($('<option>', { value: p.id, text: p.name, 'data-serial': p.serial }));
                });
            }
            $prod.prop('disabled', false);
            $('#apLoadingMsg').addClass('d-none');
        }, 'json');
    });

    // Product change in modal
    $('#apProductSelect').on('change', function() {
        const prodId = $(this).val();
        const serial = $(this).find(':selected').attr('data-serial') || '001';
        apProdCode = serial;
        apWtCode = '';
        apUpdateSku();

        const $wt = $('#apWeightSelect').empty().append('<option>Loading...</option>').prop('disabled', true);
        if (!prodId) return;
        $('#apLoadingMsg').removeClass('d-none');
        $.get('get_product_weights.php', { product_id: prodId }, function(res) {
            $wt.empty().append('<option value="">-- Select Weight --</option>');
            if (res.success && res.weights) {
                let hasAvailable = false;
                res.weights.forEach(w => {
                    const wtCode = w.wt_code || (w.display_weight || '').replace(/\s+/g, '').toUpperCase();
                    const assigned = w.has_assigned_barcode;
                    const label = assigned
                        ? w.display_weight + ' (₹' + w.price + ') — ✓ Already Assigned'
                        : w.display_weight + ' (₹' + w.price + ')';
                    const opt = $('<option>', { value: assigned ? '' : wtCode, text: label });
                    if (assigned) opt.prop('disabled', true).css('color', '#999');
                    else hasAvailable = true;
                    $wt.append(opt);
                });
                if (!hasAvailable) {
                    $wt.append('<option value="" disabled style="color:#dc3545">All weights already have barcodes assigned</option>');
                }
            }
            $wt.prop('disabled', false);
            $('#apLoadingMsg').addClass('d-none');
        }, 'json');
    });

    // Weight change in modal
    $('#apWeightSelect').on('change', function() {
        apWtCode = $(this).val() || '';
        apUpdateSku();
    });

    // Save assignment
    $('#apSaveBtn').on('click', function() {
        const barcodeId = $('#apBarcodeId').val();
        const catId = $('#apCategorySelect').val();
        const prodId = $('#apProductSelect').val();
        const wtVal = $('#apWeightSelect').val();
        const skuFull = 'G' + $('#apSkuPreview').val();

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

        $.post('assign_barcodes.php', {
            action: 'assign_product',
            barcode_id: barcodeId,
            category_id: catId,
            product_id: prodId,
            weight: wtVal,
            sku_base: skuFull
        }, function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Assignment');
            if (res.success) {
                bootstrap.Modal.getInstance(document.getElementById('assignProductModal')).hide();
                // Revoke edit_approved after saving (re-lock the barcode)
                $.post('barcode_edit_request.php', { action: 'revoke_edit', barcode_id: barcodeId }, function() {
                    location.reload();
                });
            } else {
                alert('Error: ' + (res.error || 'Unknown'));
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save Assignment');
            alert('Network error');
        });
    });

    // ===== EDIT REQUEST HANDLERS =====
    const reqEditModal = new bootstrap.Modal(document.getElementById('requestEditModal'));

    // Open Request Edit modal
    $(document).on('click', '.btn-request-edit', function() {
        const id = $(this).data('id');
        const sku = $(this).data('sku');
        $('#reqEditBarcodeId').val(id);
        $('#reqEditSku').text(sku);
        $('#reqEditReason').val('');
        $('#reqEditError').addClass('d-none');
        reqEditModal.show();
    });

    // Submit Edit Request
    $('#reqEditSubmitBtn').on('click', function() {
        const barcodeId = $('#reqEditBarcodeId').val();
        const reason = $('#reqEditReason').val().trim();
        if (!reason) { $('#reqEditError').removeClass('d-none'); return; }
        $('#reqEditError').addClass('d-none');

        const btn = $(this), orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Submitting...');

        $.post('barcode_edit_request.php', { action: 'raise_request', barcode_id: barcodeId, reason: reason }, function(res) {
            btn.prop('disabled', false).html(orig);
            if (res.success) {
                reqEditModal.hide();
                alert(res.message);
                location.reload();
            } else {
                alert('Error: ' + (res.error || 'Unknown'));
            }
        }, 'json').fail(function() { btn.prop('disabled', false).html(orig); alert('Network error'); });
    });

    // Approve Request
    $(document).on('click', '.btn-approve-request', function() {
        const requestId = $(this).data('id');
        if (!confirm('Approve this edit request? The barcode will become editable once.')) return;
        $.post('barcode_edit_request.php', { action: 'approve_request', request_id: requestId }, function(res) {
            if (res.success) { alert(res.message); location.reload(); }
            else { alert('Error: ' + res.error); }
        }, 'json');
    });

    // Reject Request
    $(document).on('click', '.btn-reject-request', function() {
        const requestId = $(this).data('id');
        if (!confirm('Reject this edit request?')) return;
        $.post('barcode_edit_request.php', { action: 'reject_request', request_id: requestId }, function(res) {
            if (res.success) { location.reload(); }
            else { alert('Error: ' + res.error); }
        }, 'json');
    });

    // Status Update Handler (pill badge dropdown)
    $(document).on('click', '.status-change-item', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const newStatus = $(this).data('status');
        const newClass = $(this).data('class');
        const btn = $('#statusBtn' + id);

        const allStatusClasses = 'st-Unused st-Used st-Added-to-Product-Design st-Planned-for-Use st-Archived';
        btn.css('opacity', '0.5');

        $.post('update_barcode_status.php', { id: id, status: newStatus }, function(res) {
            btn.css('opacity', '1');
            if(res.success) {
                btn.removeClass(allStatusClasses).addClass(newClass);
                btn.find('.dot').next().remove();
                btn.append(document.createTextNode(newStatus));
                btn.contents().filter(function(){ return this.nodeType === 3; }).last().replaceWith(newStatus);
                location.reload();
            } else {
                alert('Error: ' + (res.error || 'Unknown error'));
            }
        }, 'json').fail(function() {
            btn.css('opacity', '1');
            alert('Network error');
        });
    });
});

function exportTableToCSV(filename) {
    const csv = [];
    const rows = document.querySelectorAll("#barcodeTable tr");
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            if(j === 3) continue;
            let data = cols[j].innerText.trim();
            const select = cols[j].querySelector("select");
            if(select) data = select.value;
            row.push('"' + data + '"');
        }
        csv.push(row.join(","));
    }
    downloadCSV(csv.join("\n"), filename);
}

function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], {type: "text/csv"});
    const downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}

// EAN-13 check digit calculator (GS1 standard)
function ean13CheckDigit(twelve) {
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(twelve.charAt(i), 10) * ((i % 2 === 0) ? 1 : 3);
    }
    return ((10 - (sum % 10)) % 10).toString();
}

// Normalize any input to a valid 13-digit EAN (adds check digit if 12 digits)
function normalizeEan13(input) {
    let digits = String(input).replace(/\D/g, '');
    if (digits.length === 12) {
        digits += ean13CheckDigit(digits);
    }
    return digits;
}

function downloadBarcode(id, barcodeNum) {
    // Always normalize to 13 digits with valid check digit
    const ean = normalizeEan13(barcodeNum);

    // Determine format
    let format = 'CODE128'; // fallback only if length is unusual
    if (ean.length === 13) format = 'EAN13';
    else if (ean.length === 8) format = 'EAN8';

    // Create temporary SVG for rendering
    const tmpSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    tmpSvg.style.position = 'absolute';
    tmpSvg.style.left = '-9999px';
    document.body.appendChild(tmpSvg);

    try {
        JsBarcode(tmpSvg, ean, {
            format: format,
            width: 2.4,
            height: 90,
            displayValue: true,
            fontSize: 18,
            font: '"OCR-B", "Courier New", monospace',
            textMargin: 4,
            margin: 12,
            background: '#ffffff',
            lineColor: '#000000',
            flat: false
        });
    } catch (e) {
        console.error('Barcode render error:', e);
        JsBarcode(tmpSvg, barcodeNum, {
            format: 'CODE128',
            width: 2,
            height: 90,
            displayValue: true,
            fontSize: 18,
            margin: 12
        });
    }

    const serializer = new XMLSerializer();
    const baseWidth = parseFloat(tmpSvg.getAttribute('width') || tmpSvg.getBoundingClientRect().width || 300);
    const baseHeight = parseFloat(tmpSvg.getAttribute('height') || tmpSvg.getBoundingClientRect().height || 140);
    const svgStr = serializer.serializeToString(tmpSvg);
    document.body.removeChild(tmpSvg);

    // High-res render at 4x scale for crisp print quality
    const scale = 4;
    const canvas = document.createElement('canvas');
    canvas.width = baseWidth * scale;
    canvas.height = baseHeight * scale;
    const ctx = canvas.getContext('2d');
    const img = new Image();
    img.onload = function () {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        const pngUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.href = pngUrl;
        link.download = 'EAN13_' + ean + '.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgStr)));
}

function deleteOne(id) {
    openSecLock('Move this barcode to Recycle Bin (auto-purged after 15 days).', function() {
        $.post('delete_barcodes.php', { action: 'delete_one', id: id }, function(res) {
            if(res.success) { location.reload(); }
            else { alert('Error: ' + res.error); }
        }, 'json').fail(function() { alert('Network error'); });
    });
}
</script>
