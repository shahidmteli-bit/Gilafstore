<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Manage Applications - Admin';
$adminPage = 'applications';

// Get database connection
$db = get_db_connection();

// Check if table exists, create if not using correct schema
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'distributor_applications'");
    if ($tableCheck->rowCount() == 0) {
        // Table doesn't exist, create it with correct schema
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `distributor_applications` (
          `id` INT(11) NOT NULL AUTO_INCREMENT,
          `application_type` ENUM('reseller', 'distributor', 'official_store') NOT NULL,
          `owner_name` VARCHAR(255) NOT NULL,
          `owner_address` TEXT NOT NULL,
          `phone` VARCHAR(20) NOT NULL,
          `email` VARCHAR(255) NOT NULL,
          `business_address` TEXT NOT NULL,
          `same_as_owner_address` TINYINT(1) DEFAULT 0,
          `identity_proof_type` ENUM('aadhaar', 'pan', 'election_card') NOT NULL,
          `identity_proof_file` VARCHAR(255) NOT NULL,
          `shops_labour_license` VARCHAR(255),
          `municipality_license` VARCHAR(255),
          `msme_license` VARCHAR(255),
          `gst_license` VARCHAR(255),
          `gst_registration_number` VARCHAR(100),
          `latitude` DECIMAL(10, 8),
          `longitude` DECIMAL(11, 8),
          `google_maps_url` VARCHAR(500),
          `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
          `admin_notes` TEXT,
          `reviewed_by` INT(11),
          `reviewed_at` TIMESTAMP NULL,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `status` (`status`),
          KEY `application_type` (`application_type`),
          KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $db->exec($createTableSQL);
    }
} catch (PDOException $e) {
    die('Database setup error: ' . $e->getMessage());
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$sql = "SELECT * FROM distributor_applications WHERE 1=1";

if ($filter !== 'all') {
    $sql .= " AND status = :status";
}

if ($type !== 'all') {
    $sql .= " AND application_type = :type";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);

if ($filter !== 'all') {
    $stmt->bindValue(':status', $filter);
}

if ($type !== 'all') {
    $stmt->bindValue(':type', $type);
}

$stmt->execute();
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts
$counts = [
    'all' => $db->query("SELECT COUNT(*) FROM distributor_applications")->fetchColumn(),
    'pending' => $db->query("SELECT COUNT(*) FROM distributor_applications WHERE status = 'pending'")->fetchColumn(),
    'approved' => $db->query("SELECT COUNT(*) FROM distributor_applications WHERE status = 'approved'")->fetchColumn(),
    'rejected' => $db->query("SELECT COUNT(*) FROM distributor_applications WHERE status = 'rejected'")->fetchColumn(),
    'reseller' => $db->query("SELECT COUNT(*) FROM distributor_applications WHERE application_type = 'reseller'")->fetchColumn(),
    'distributor' => $db->query("SELECT COUNT(*) FROM distributor_applications WHERE application_type = 'distributor'")->fetchColumn(),
    'official_store' => $db->query("SELECT COUNT(*) FROM distributor_applications WHERE application_type = 'official_store'")->fetchColumn(),
];

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.applications-container {
    background: #f9fafb;
    min-height: 100vh;
    padding: 24px;
}
.applications-header {
    background: white;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.applications-header h1 {
    font-size: 28px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 8px 0;
}
.applications-header p {
    color: #6b7280;
    margin: 0;
    font-size: 15px;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.2s;
    border-left: 4px solid;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
.stat-card.all { border-left-color: #3b82f6; }
.stat-card.pending { border-left-color: #fbbf24; }
.stat-card.approved { border-left-color: #10b981; }
.stat-card.rejected { border-left-color: #ef4444; }
.stat-card.reseller { border-left-color: #8b5cf6; }
.stat-card.distributor { border-left-color: #06b6d4; }
.stat-card.official { border-left-color: #f59e0b; }
.stat-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 12px;
}
.stat-card.all .stat-card-icon { background: #dbeafe; color: #3b82f6; }
.stat-card.pending .stat-card-icon { background: #fef3c7; color: #f59e0b; }
.stat-card.approved .stat-card-icon { background: #d1fae5; color: #059669; }
.stat-card.rejected .stat-card-icon { background: #fee2e2; color: #dc2626; }
.stat-card.reseller .stat-card-icon { background: #ede9fe; color: #7c3aed; }
.stat-card.distributor .stat-card-icon { background: #cffafe; color: #0891b2; }
.stat-card.official .stat-card-icon { background: #fef3c7; color: #d97706; }
.stat-card-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.stat-card-value {
    font-size: 32px;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1;
}
.filters-bar {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.filter-section {
    margin-bottom: 16px;
}
.filter-section:last-child {
    margin-bottom: 0;
}
.filter-label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}
.filter-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    border: 2px solid #e5e7eb;
    background: white;
    color: #6b7280;
}
.filter-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}
.filter-btn.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border-color: #3b82f6;
}
.filter-btn.active.pending {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    border-color: #fbbf24;
}
.filter-btn.active.approved {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: #10b981;
}
.filter-btn.active.rejected {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-color: #ef4444;
}
.applications-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    overflow: hidden;
}
.applications-table {
    width: 100%;
    border-collapse: collapse;
}
.applications-table thead th {
    background: #f8f9fa;
    padding: 16px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e5e7eb;
}
.applications-table tbody td {
    padding: 16px 20px;
    font-size: 14px;
    color: #374151;
    border-bottom: none;
}
.applications-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
}
.applications-table tbody tr:last-child {
    border-bottom: none;
}
.applications-table tbody tr:hover {
    background: #f9fafb;
    transition: background 0.15s ease;
}
.app-id {
    font-weight: 600;
    color: #1a1a1a;
    font-family: 'Courier New', monospace;
}
.app-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 500;
}
.type-reseller {
    background: #ede9fe;
    color: #7c3aed;
}
.type-distributor {
    background: #dbeafe;
    color: #2563eb;
}
.type-official {
    background: #fef3c7;
    color: #d97706;
}
.app-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 12px;
    font-weight: 500;
}
.status-pending {
    background: #fef3c7;
    color: #92400e;
}
.status-approved {
    background: #d1fae5;
    color: #065f46;
}
.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}
.app-action-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
}
.app-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
.app-action-btn:active {
    transform: translateY(0);
}
</style>

<div class="applications-container">
    <!-- Header -->
    <div class="applications-header">
        <h1>Partnership Applications</h1>
        <p>Review and manage distributor, reseller, and official store applications</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <a href="?filter=all&type=<?= $type; ?>" class="stat-card all" style="text-decoration: none;">
            <div class="stat-card-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-card-label">Total Applications</div>
            <div class="stat-card-value"><?= $counts['all']; ?></div>
        </a>
        <a href="?filter=pending&type=<?= $type; ?>" class="stat-card pending" style="text-decoration: none;">
            <div class="stat-card-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-card-label">Pending Review</div>
            <div class="stat-card-value"><?= $counts['pending']; ?></div>
        </a>
        <a href="?filter=approved&type=<?= $type; ?>" class="stat-card approved" style="text-decoration: none;">
            <div class="stat-card-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-card-label">Approved</div>
            <div class="stat-card-value"><?= $counts['approved']; ?></div>
        </a>
        <a href="?filter=rejected&type=<?= $type; ?>" class="stat-card rejected" style="text-decoration: none;">
            <div class="stat-card-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-card-label">Rejected</div>
            <div class="stat-card-value"><?= $counts['rejected']; ?></div>
        </a>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
        <div class="row">
            <div class="col-md-6 filter-section">
                <div class="filter-label">Filter by Status</div>
                <div class="filter-buttons">
                    <a href="?filter=all&type=<?= $type; ?>" class="filter-btn <?= $filter === 'all' ? 'active' : ''; ?>">
                        All (<?= $counts['all']; ?>)
                    </a>
                    <a href="?filter=pending&type=<?= $type; ?>" class="filter-btn <?= $filter === 'pending' ? 'active pending' : ''; ?>">
                        Pending (<?= $counts['pending']; ?>)
                    </a>
                    <a href="?filter=approved&type=<?= $type; ?>" class="filter-btn <?= $filter === 'approved' ? 'active approved' : ''; ?>">
                        Approved (<?= $counts['approved']; ?>)
                    </a>
                    <a href="?filter=rejected&type=<?= $type; ?>" class="filter-btn <?= $filter === 'rejected' ? 'active rejected' : ''; ?>">
                        Rejected (<?= $counts['rejected']; ?>)
                    </a>
                </div>
            </div>
            <div class="col-md-6 filter-section">
                <div class="filter-label">Filter by Type</div>
                <div class="filter-buttons">
                    <a href="?filter=<?= $filter; ?>&type=all" class="filter-btn <?= $type === 'all' ? 'active' : ''; ?>">
                        All Types
                    </a>
                    <a href="?filter=<?= $filter; ?>&type=reseller" class="filter-btn <?= $type === 'reseller' ? 'active' : ''; ?>">
                        Resellers (<?= $counts['reseller']; ?>)
                    </a>
                    <a href="?filter=<?= $filter; ?>&type=distributor" class="filter-btn <?= $type === 'distributor' ? 'active' : ''; ?>">
                        Distributors (<?= $counts['distributor']; ?>)
                    </a>
                    <a href="?filter=<?= $filter; ?>&type=official_store" class="filter-btn <?= $type === 'official_store' ? 'active' : ''; ?>">
                        Official Stores (<?= $counts['official_store']; ?>)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="applications-table-container">
        <table class="applications-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Applicant</th>
                    <th>Contact</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Applied On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applications)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 60px 20px;">
                            <i class="fas fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px; display: block;"></i>
                            <p style="color: #9ca3af; font-size: 16px; margin: 0;">No applications found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <?php
                        // Extract location from admin_notes JSON if available
                        $city = '';
                        $state = '';
                        if (!empty($app['admin_notes'])) {
                            $notes = json_decode($app['admin_notes'], true);
                            if (is_array($notes)) {
                                $city = $notes['city'] ?? '';
                                $state = $notes['state'] ?? '';
                            }
                        }
                        $location = trim($city . ', ' . $state, ', ');
                        if (empty($location)) $location = 'N/A';
                        ?>
                        <tr>
                            <td><span class="app-id">#<?= str_pad($app['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                            <td>
                                <?php
                                $typeClass = [
                                    'reseller' => 'type-reseller',
                                    'distributor' => 'type-distributor',
                                    'official_store' => 'type-official'
                                ];
                                $typeIcon = [
                                    'reseller' => 'fa-store',
                                    'distributor' => 'fa-truck',
                                    'official_store' => 'fa-building'
                                ];
                                $typeLabel = [
                                    'reseller' => 'Reseller',
                                    'distributor' => 'Distributor',
                                    'official_store' => 'Official Store'
                                ];
                                $type = $app['application_type'] ?? 'distributor';
                                ?>
                                <span class="app-type-badge <?= $typeClass[$type] ?? ''; ?>">
                                    <i class="fas <?= $typeIcon[$type] ?? 'fa-briefcase'; ?>"></i>
                                    <?= $typeLabel[$type] ?? 'Unknown'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: #1a1a1a;"><?= htmlspecialchars($app['owner_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 13px; color: #6b7280;">
                                    <i class="fas fa-envelope" style="margin-right: 4px;"></i><?= htmlspecialchars($app['email'] ?? 'N/A'); ?>
                                </div>
                                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                                    <i class="fas fa-phone" style="margin-right: 4px;"></i><?= htmlspecialchars($app['phone'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($app['business_address'] ?? 'N/A'); ?></td>
                            <td><?= htmlspecialchars($location); ?></td>
                            <td>
                                <div style="font-size: 13px; color: #6b7280;">
                                    <i class="fas fa-calendar" style="margin-right: 4px;"></i><?= date('M d, Y', strtotime($app['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'pending' => 'status-pending',
                                    'approved' => 'status-approved',
                                    'rejected' => 'status-rejected'
                                ];
                                $statusIcon = [
                                    'pending' => 'fa-clock',
                                    'approved' => 'fa-check-circle',
                                    'rejected' => 'fa-times-circle'
                                ];
                                $status = $app['status'] ?? 'pending';
                                ?>
                                <span class="app-status-badge <?= $statusClass[$status] ?? ''; ?>">
                                    <i class="fas <?= $statusIcon[$status] ?? 'fa-question'; ?>"></i>
                                    <?= ucfirst($status); ?>
                                </span>
                            </td>
                            <td>
                                <button class="app-action-btn" onclick="viewApplication(<?= $app['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Application Modal -->
<div class="modal fade" id="viewApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--color-green); color: white;">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Application Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="applicationDetails">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-3">Loading application details...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewApplication(id) {
    const modal = new bootstrap.Modal(document.getElementById('viewApplicationModal'));
    modal.show();
    
    fetch(`<?= base_url('admin/get_application.php'); ?>?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('applicationDetails').innerHTML = data.html;
            } else {
                document.getElementById('applicationDetails').innerHTML = '<div class="alert alert-danger">Failed to load application details</div>';
            }
        })
        .catch(error => {
            document.getElementById('applicationDetails').innerHTML = '<div class="alert alert-danger">Error loading application</div>';
        });
}

// Global functions for approve/reject/delete
function approveApplication(id) {
    console.log("Approve clicked for ID:", id);
    if (confirm("Are you sure you want to APPROVE this application?")) {
        console.log("Approval confirmed");
        // Close modal
        const modalEl = document.getElementById('viewApplicationModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        
        // Redirect
        window.location.href = "/Gilaf Ecommerce website/admin/process_application.php?id=" + id + "&action=approve";
    }
}

function rejectApplication(id) {
    console.log("Reject clicked for ID:", id);
    const reason = prompt("Please enter rejection reason (optional):");
    if (reason !== null) {
        console.log("Rejection confirmed with reason:", reason);
        // Close modal
        const modalEl = document.getElementById('viewApplicationModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        
        // Redirect
        window.location.href = "/Gilaf Ecommerce website/admin/process_application.php?id=" + id + "&action=reject&reason=" + encodeURIComponent(reason);
    }
}

function deleteApplication(id) {
    console.log("Delete clicked for ID:", id);
    if (confirm("Are you sure you want to PERMANENTLY DELETE this distributor/application?\n\nThis action cannot be undone!")) {
        if (confirm("Final confirmation: Delete this distributor permanently?")) {
            console.log("Deletion confirmed");
            // Close modal
            const modalEl = document.getElementById('viewApplicationModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            
            // Redirect
            window.location.href = "/Gilaf Ecommerce website/admin/delete_application.php?id=" + id;
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
