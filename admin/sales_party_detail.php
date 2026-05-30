<?php
/**
 * Admin Panel - Sales Party Detail Page
 * Full detail view for a single party (like Application Details)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Party Details';
$adminPage = 'sales_parties';

$partyId = (int)($_GET['id'] ?? 0);
if ($partyId <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid party ID.'];
    header('Location: ' . base_url('admin/sales_parties.php'));
    exit;
}

// Auto-fix: recalculate this party's outstanding from actual order data
recalculate_party_outstanding($partyId);

// Handle credit limit update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_credit'])) {
    $creditLimit = (float)$_POST['credit_limit'];
    db_query('UPDATE sales_parties SET credit_limit = ? WHERE id = ?', [$creditLimit, $partyId]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Credit limit updated.'];
    header('Location: ' . base_url('admin/sales_party_detail.php?id=' . $partyId));
    exit;
}

// Handle outstanding adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_outstanding'])) {
    $amount = (float)$_POST['amount'];
    $adjustType = $_POST['adjust_type'] ?? 'reduce';
    if ($adjustType === 'reduce') {
        db_query('UPDATE sales_parties SET outstanding_amount = GREATEST(0, outstanding_amount - ?) WHERE id = ?', [$amount, $partyId]);
        db_query('INSERT INTO sales_payment_history (party_id, amount, payment_type, notes, recorded_by) VALUES (?,?,?,?,?)', [
            $partyId, $amount, 'adjustment', 'Admin adjustment - reduced outstanding', 0
        ]);
    } else {
        db_query('UPDATE sales_parties SET outstanding_amount = outstanding_amount + ? WHERE id = ?', [$amount, $partyId]);
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Outstanding amount adjusted.'];
    header('Location: ' . base_url('admin/sales_party_detail.php?id=' . $partyId));
    exit;
}

// Handle party status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $newStatus = ($_POST['current_status'] == '1') ? 0 : 1;
    db_query('UPDATE sales_parties SET is_active = ? WHERE id = ?', [$newStatus, $partyId]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => $newStatus ? 'Party activated.' : 'Party deactivated.'];
    header('Location: ' . base_url('admin/sales_party_detail.php?id=' . $partyId));
    exit;
}

// Fetch party with executive info
$party = db_fetch(
    'SELECT sp.*, se.name as exec_name, se.phone as exec_phone, se.email as exec_email, se.district as exec_district
     FROM sales_parties sp 
     JOIN sales_executives se ON sp.created_by = se.id 
     WHERE sp.id = ?', [$partyId]
);

if (!$party) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Party not found.'];
    header('Location: ' . base_url('admin/sales_parties.php'));
    exit;
}

// Order stats
$orderStats = db_fetch(
    'SELECT COUNT(*) as total_orders, 
     COALESCE(SUM(CASE WHEN status IN ("approved","dispatched","delivered") THEN total_amount ELSE 0 END),0) as total_turnover,
     COALESCE(SUM(CASE WHEN status IN ("approved","dispatched","delivered") THEN total_amount ELSE 0 END),0) as total_sales,
     COALESCE(SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END),0) as pending_orders,
     COALESCE(SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END),0) as delivered_orders,
     MAX(created_at) as last_order_date
     FROM sales_orders WHERE party_id = ?', [$partyId]
);

// Recent orders
$recentOrders = db_fetch_all(
    'SELECT so.*, se.name as exec_name FROM sales_orders so 
     JOIN sales_executives se ON so.executive_id = se.id 
     WHERE so.party_id = ? ORDER BY so.created_at DESC LIMIT 10', [$partyId]
);

// Payment history
$payments = db_fetch_all(
    'SELECT * FROM sales_payment_history WHERE party_id = ? ORDER BY created_at DESC LIMIT 10', [$partyId]
);

// Collection history
$collections = [];
try {
    $collections = db_fetch_all(
        'SELECT sc.*, se.name as exec_name FROM sales_collections sc 
         JOIN sales_executives se ON sc.executive_id = se.id 
         WHERE sc.party_id = ? ORDER BY sc.created_at DESC LIMIT 10', [$partyId]
    );
} catch (PDOException $e) {}

$profileLabels = ['wholesaler' => 'Wholesaler', 'distributor' => 'Distributor', 'franchise' => 'Franchisee', 'retailer' => 'Retailer'];
$profileColors = ['wholesaler' => 'primary', 'distributor' => 'success', 'franchise' => 'info', 'retailer' => 'danger'];
$statusBadge = $party['is_active'] ? '<span class="badge bg-success fs-6">Active</span>' : '<span class="badge bg-danger fs-6">Inactive</span>';
$creditPct = $party['credit_limit'] > 0 ? ($party['outstanding_amount'] / $party['credit_limit']) * 100 : 0;

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Back Button + Title -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="<?= base_url('admin/sales_parties.php') ?>" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> Back to All Parties
            </a>
            <h2 class="fw-bold mb-0 mt-1">
                <i class="fas fa-store me-2 text-primary"></i>
                Party #<?= htmlspecialchars($party['party_code'] ?? $party['id']) ?> — <?= htmlspecialchars($party['shop_name']) ?>
            </h2>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#qrModal">
                <i class="fas fa-qrcode me-1"></i>QR Code
            </button>
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: Details -->
        <div class="col-lg-8">

            <!-- Section: Shop & Owner Information -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-user"></i> Shop & Owner Information</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Shop Name:</strong><br>
                            <?= htmlspecialchars($party['shop_name']) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Owner Name:</strong><br>
                            <?= htmlspecialchars($party['owner_name']) ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Phone:</strong><br>
                            <a href="tel:<?= htmlspecialchars($party['phone']) ?>"><?= htmlspecialchars($party['phone']) ?></a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Email:</strong><br>
                            <?= $party['email'] ? '<a href="mailto:' . htmlspecialchars($party['email']) . '">' . htmlspecialchars($party['email']) . '</a>' : '<span class="text-muted">Not provided</span>' ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Party Code:</strong><br>
                            <span class="badge bg-dark fs-6"><?= htmlspecialchars($party['party_code'] ?? '—') ?></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Profile Type:</strong><br>
                            <span class="badge bg-<?= $profileColors[$party['profile_type'] ?? 'wholesaler'] ?? 'primary' ?>">
                                <?= $profileLabels[$party['profile_type'] ?? 'wholesaler'] ?? 'Wholesaler' ?>
                            </span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>GST Number:</strong><br>
                            <?= $party['gst_number'] ? htmlspecialchars($party['gst_number']) : '<span class="text-muted">Not provided</span>' ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Created On:</strong><br>
                            <?= date('d M Y, h:i A', strtotime($party['created_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Address & Location Details -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-building"></i> Address & Location Details</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <strong>Address:</strong><br>
                            <?= nl2br(htmlspecialchars($party['address'] ?? 'Not provided')) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>City:</strong><br>
                            <?= htmlspecialchars($party['city'] ?? 'N/A') ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>State:</strong><br>
                            <?= htmlspecialchars($party['state'] ?? 'N/A') ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>Pincode:</strong><br>
                            <?= htmlspecialchars($party['pincode'] ?? 'N/A') ?>
                        </div>
                        <div class="col-md-12"><hr style="margin:4px 0 12px;"></div>
                        <div class="col-md-4 mb-3">
                            <strong>District:</strong><br>
                            <?= htmlspecialchars($party['district']) ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>Latitude:</strong><br>
                            <?= $party['latitude'] ? htmlspecialchars($party['latitude']) : '<span class="text-muted">Not recorded</span>' ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>Longitude:</strong><br>
                            <?= $party['longitude'] ? htmlspecialchars($party['longitude']) : '<span class="text-muted">Not recorded</span>' ?>
                        </div>
                        <div class="col-md-12 mb-3">
                            <strong>Google Maps:</strong><br>
                            <?php
                            $mapsUrl = $party['google_maps_url'] ?? '';
                            if (!$mapsUrl && $party['latitude'] && $party['longitude']) {
                                $mapsUrl = 'https://www.google.com/maps?q=' . $party['latitude'] . ',' . $party['longitude'];
                            }
                            if ($mapsUrl): ?>
                                <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                    <i class="fas fa-map-marked-alt"></i> View on Google Maps
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Not provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Financial Summary -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-chart-line"></i> Financial Summary</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 text-center h-100">
                                <div class="fs-4 fw-bold text-primary"><?= (int)$orderStats['total_orders'] ?></div>
                                <div class="small text-muted">Total Orders</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 text-center h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                                <div class="fs-4 fw-bold">₹<?= number_format((float)$orderStats['total_turnover'], 0) ?></div>
                                <div class="small" style="opacity: 0.9;">Total Turnover</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 text-center h-100">
                                <div class="fs-4 fw-bold <?= $party['outstanding_amount'] > 0 ? 'text-danger' : 'text-success' ?>">₹<?= number_format((float)$party['outstanding_amount'], 0) ?></div>
                                <div class="small text-muted">Outstanding</div>
                                <?php if ($creditPct >= 80): ?>
                                    <span class="badge bg-<?= $creditPct >= 100 ? 'danger' : 'warning text-dark' ?>" style="font-size:10px;"><?= round($creditPct) ?>% used</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 text-center h-100">
                                <div class="fs-4 fw-bold">₹<?= number_format((float)$party['credit_limit'], 0) ?></div>
                                <div class="small text-muted">Credit Limit</div>
                            </div>
                        </div>
                    </div>
                    <?php if ($orderStats['last_order_date']): ?>
                    <div class="mt-3 small text-muted">
                        <i class="fas fa-clock me-1"></i> Last order: <?= date('d M Y', strtotime($orderStats['last_order_date'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Created By (Executive) -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-user-tie"></i> Created By (Sales Executive)</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <strong>Name:</strong><br><?= htmlspecialchars($party['exec_name']) ?>
                        </div>
                        <div class="col-md-4 mb-2">
                            <strong>Phone:</strong><br><?= htmlspecialchars($party['exec_phone']) ?>
                        </div>
                        <div class="col-md-4 mb-2">
                            <strong>District:</strong><br><?= htmlspecialchars($party['exec_district']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Notes -->
            <?php if (!empty($party['notes'])): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-sticky-note"></i> Notes</strong>
                </div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($party['notes'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section: Recent Orders -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-clipboard-list"></i> Recent Orders</strong>
                    <span class="badge bg-primary"><?= (int)$orderStats['total_orders'] ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentOrders)): ?>
                        <div class="text-center py-4 text-muted"><i class="fas fa-clipboard fa-2x mb-2 d-block" style="opacity:0.3;"></i>No orders yet</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th>Executive</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $orderBadge = ['pending'=>'warning text-dark','approved'=>'info','dispatched'=>'primary','delivered'=>'success','rejected'=>'danger','cancelled'=>'secondary'];
                                foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><a href="<?= base_url('admin/sales_order_detail.php?id=' . $order['id']) ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($order['order_number']) ?></a></td>
                                    <td><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                                    <td class="fw-bold">₹<?= number_format((float)$order['total_amount'], 0) ?></td>
                                    <td><span class="badge bg-<?= $orderBadge[$order['status']] ?? 'secondary' ?>"><?= ucfirst($order['status']) ?></span></td>
                                    <td class="text-muted"><?= htmlspecialchars($order['exec_name']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Collections -->
            <?php if (!empty($collections)): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-hand-holding-usd"></i> Recent Collections</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th>Collection #</th><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php
                                $colBadge = ['pending'=>'warning text-dark','confirmed'=>'success','rejected'=>'danger'];
                                foreach ($collections as $col): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($col['collection_number']) ?></td>
                                    <td><?= date('d M Y', strtotime($col['created_at'])) ?></td>
                                    <td class="fw-bold text-success">₹<?= number_format((float)$col['amount'], 0) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $col['payment_method'])) ?></td>
                                    <td><span class="badge bg-<?= $colBadge[$col['status']] ?? 'secondary' ?>"><?= ucfirst($col['status']) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT COLUMN: Status, QR, Actions -->
        <div class="col-lg-4">

            <!-- Party Status -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong>Party Status</strong>
                </div>
                <div class="card-body text-center">
                    <h3><?= $statusBadge ?></h3>
                    <small class="text-muted">Registered on: <?= date('M d, Y', strtotime($party['created_at'])) ?></small>
                    <?php if ($party['updated_at'] && $party['updated_at'] !== $party['created_at']): ?>
                        <br><small class="text-muted">Last updated: <?= date('M d, Y', strtotime($party['updated_at'])) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QR Code Card -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-qrcode"></i> Party QR Code</strong>
                </div>
                <div class="card-body text-center">
                    <?php if ($party['party_code']): ?>
                    <div style="background:#111;border-radius:12px;padding:20px;margin-bottom:12px;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($party['party_code']) ?>" alt="QR" style="width:180px;height:180px;border-radius:6px;">
                        <div style="color:#fff;font-size:18px;font-weight:700;margin-top:10px;letter-spacing:1px;"><?= htmlspecialchars($party['party_code']) ?></div>
                        <div style="color:#ccc;font-size:12px;margin-top:4px;"><?= htmlspecialchars($party['shop_name']) ?></div>
                        <div style="margin-top:6px;"><span style="background:#fbbf24;color:#000;font-size:10px;font-weight:700;padding:2px 10px;border-radius:10px;text-transform:uppercase;letter-spacing:0.5px;"><?= htmlspecialchars($profileLabels[$party['profile_type'] ?? 'wholesaler'] ?? 'Wholesaler') ?></span></div>
                    </div>
                    <a href="https://api.qrserver.com/v1/create-qr-code/?size=600x600&format=png&data=<?= urlencode($party['party_code']) ?>" download="QR_<?= $party['party_code'] ?>.png" class="btn btn-dark btn-sm me-1"><i class="fas fa-download me-1"></i>Download</a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printPartyQR('<?= htmlspecialchars($party['party_code']) ?>', '<?= htmlspecialchars(addslashes($party['shop_name'])) ?>', '<?= htmlspecialchars(addslashes($party['owner_name'])) ?>', '<?= htmlspecialchars($profileLabels[$party['profile_type'] ?? 'wholesaler'] ?? 'Wholesaler') ?>')"><i class="fas fa-print me-1"></i>Print</button>
                    <?php else: ?>
                    <p class="text-muted">No party code assigned</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong>Actions</strong>
                </div>
                <div class="card-body">
                    <button class="btn btn-outline-primary w-100 mb-2 btn-sm" data-bs-toggle="modal" data-bs-target="#creditModal">
                        <i class="fas fa-credit-card me-1"></i> Update Credit Limit
                    </button>
                    <button class="btn btn-outline-warning w-100 mb-2 btn-sm" data-bs-toggle="modal" data-bs-target="#outstandingModal">
                        <i class="fas fa-money-bill me-1"></i> Adjust Outstanding
                    </button>
                    <hr>
                    <form method="POST" onsubmit="return confirm('Are you sure?');">
                        <input type="hidden" name="toggle_status" value="1">
                        <input type="hidden" name="current_status" value="<?= $party['is_active'] ?>">
                        <?php if ($party['is_active']): ?>
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                                <i class="fas fa-ban me-1"></i> Deactivate Party
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-outline-success w-100 btn-sm">
                                <i class="fas fa-check-circle me-1"></i> Activate Party
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Payment History -->
            <?php if (!empty($payments)): ?>
            <div class="card mb-3 shadow-sm">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-history"></i> Payment History</strong>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($payments as $pay): ?>
                        <div class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <strong class="text-success">₹<?= number_format((float)$pay['amount'], 0) ?></strong>
                                <span class="text-muted"><?= date('d M Y', strtotime($pay['created_at'])) ?></span>
                            </div>
                            <div class="text-muted" style="font-size:11px;">
                                <?= ucfirst(str_replace('_', ' ', $pay['payment_type'])) ?>
                                <?= $pay['notes'] ? ' — ' . htmlspecialchars($pay['notes']) : '' ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Credit Limit Modal -->
<div class="modal fade" id="creditModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="update_credit" value="1">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-credit-card me-1"></i> Credit Limit</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Current: <strong>₹<?= number_format((float)$party['credit_limit'], 0) ?></strong></p>
                    <label class="form-label fw-semibold">New Credit Limit (₹)</label>
                    <input type="number" name="credit_limit" class="form-control" step="0.01" min="0" value="<?= $party['credit_limit'] ?>">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Outstanding Adjustment Modal -->
<div class="modal fade" id="outstandingModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="adjust_outstanding" value="1">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-money-bill me-1"></i> Outstanding</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small">Current: <strong class="text-danger">₹<?= number_format((float)$party['outstanding_amount'], 0) ?></strong></p>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Amount (₹)</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Action</label>
                        <select name="adjust_type" class="form-select">
                            <option value="reduce">Reduce (Payment Received)</option>
                            <option value="increase">Increase</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Adjust</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Code Full Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-qrcode me-1"></i> Party QR Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div style="background:#000;border-radius:12px;padding:20px;margin-bottom:12px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($party['party_code'] ?? '') ?>" alt="QR" style="width:200px;height:200px;border-radius:6px;">
                    <div style="color:#fff;font-size:18px;font-weight:700;margin-top:10px;letter-spacing:1px;"><?= htmlspecialchars($party['party_code'] ?? '') ?></div>
                    <div style="color:#aaa;font-size:12px;margin-top:4px;"><?= htmlspecialchars($party['shop_name']) ?></div>
                    <div style="margin-top:6px;"><span style="background:#fbbf24;color:#000;font-size:10px;font-weight:700;padding:2px 10px;border-radius:10px;text-transform:uppercase;letter-spacing:0.5px;"><?= htmlspecialchars($profileLabels[$party['profile_type'] ?? 'wholesaler'] ?? 'Wholesaler') ?></span></div>
                </div>
                <p class="small text-muted mb-2">Print this QR and give to the party. Sales executives can scan it to quickly select this party.</p>
            </div>
        </div>
    </div>
</div>

<script>
function printPartyQR(code, shopName, ownerName, profileType) {
    var w = window.open('', '_blank', 'width=400,height=600');
    w.document.write('<html><head><title>Party QR - ' + code + '</title>');
    w.document.write('<style>body{font-family:Arial,sans-serif;text-align:center;padding:40px 20px;}');
    w.document.write('.qr-card{border:2px solid #000;border-radius:12px;padding:30px;display:inline-block;max-width:320px;}');
    w.document.write('.logo{font-size:22px;font-weight:800;color:#1A3C34;margin-bottom:4px;}');
    w.document.write('.subtitle{font-size:10px;color:#888;letter-spacing:2px;text-transform:uppercase;margin-bottom:20px;}');
    w.document.write('.code{font-size:24px;font-weight:800;letter-spacing:2px;margin-top:16px;color:#1A3C34;}');
    w.document.write('.shop{font-size:14px;color:#555;margin-top:6px;}');
    w.document.write('.owner{font-size:12px;color:#888;margin-top:2px;}');
    w.document.write('.ptype{display:inline-block;background:#fbbf24;color:#000;font-size:10px;font-weight:700;padding:2px 12px;border-radius:10px;margin-top:8px;text-transform:uppercase;letter-spacing:0.5px;}');
    w.document.write('.footer{font-size:9px;color:#aaa;margin-top:16px;border-top:1px solid #eee;padding-top:10px;}');
    w.document.write('</style></head><body>');
    w.document.write('<div class="qr-card">');
    w.document.write('<div class="logo">GILAF STORE</div>');
    w.document.write('<div class="subtitle">Party Identification</div>');
    w.document.write('<img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' + encodeURIComponent(code) + '" width="220" height="220" style="border-radius:6px;">');
    w.document.write('<div class="code">' + code + '</div>');
    w.document.write('<div class="shop">' + shopName + '</div>');
    w.document.write('<div class="owner">' + ownerName + '</div>');
    if (profileType) w.document.write('<div class="ptype">' + profileType + '</div>');
    w.document.write('<div class="footer">Scan this QR code using the Gilaf Sales Portal</div>');
    w.document.write('</div></body></html>');
    w.document.close();
    w.onload = function() { w.print(); };
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
