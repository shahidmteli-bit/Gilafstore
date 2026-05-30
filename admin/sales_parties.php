<?php
/**
 * Admin Panel - Sales Parties (Customers) Management
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales Parties';
$adminPage = 'sales_parties';

$search = trim($_GET['search'] ?? '');

$sql = 'SELECT sp.*, se.name as exec_name, (SELECT COUNT(*) FROM sales_orders WHERE party_id = sp.id) as order_count, (SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE party_id = sp.id AND status IN ("approved","dispatched","delivered")) as total_orders FROM sales_parties sp JOIN sales_executives se ON sp.created_by = se.id WHERE 1=1';
$params = [];

if ($search) {
    $sql .= ' AND (sp.shop_name LIKE ? OR sp.owner_name LIKE ? OR sp.phone LIKE ? OR sp.district LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY sp.created_at DESC';
$parties = db_fetch_all($sql, $params);

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Sales Parties</h2>
            <p class="text-muted mb-0">All customers created by sales executives</p>
        </div>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width:400px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" name="search" class="form-control" placeholder="Search shop, owner, phone, district..." value="<?= htmlspecialchars($search) ?>">
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Party Code</th>
                            <th>Shop Name</th>
                            <th>Owner</th>
                            <th>Phone</th>
                            <th>District</th>
                            <th>Executive</th>
                            <th>Orders</th>
                            <th>Total Sales</th>
                            <th>Outstanding</th>
                            <th>Credit Limit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($parties)): ?>
                        <tr><td colspan="11" class="text-center py-5 text-muted"><i class="fas fa-store fa-3x mb-3 d-block"></i>No parties found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($parties as $party): 
                            $creditPct = $party['credit_limit'] > 0 ? ($party['outstanding_amount'] / $party['credit_limit']) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-dark" style="font-size:11px;letter-spacing:0.5px;"><?= htmlspecialchars($party['party_code'] ?? '—') ?></span>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($party['shop_name']) ?></td>
                            <td><?= htmlspecialchars($party['owner_name']) ?></td>
                            <td><?= htmlspecialchars($party['phone']) ?></td>
                            <td><?= htmlspecialchars($party['district']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($party['exec_name']) ?></td>
                            <td><span class="badge bg-primary"><?= $party['order_count'] ?></span></td>
                            <td class="fw-bold text-success">₹<?= number_format($party['total_orders'], 0) ?></td>
                            <td class="fw-bold <?= $party['outstanding_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                                ₹<?= number_format($party['outstanding_amount'], 0) ?>
                                <?php if ($creditPct >= 100): ?>
                                    <span class="badge bg-danger ms-1" style="font-size:9px;">EXCEEDED</span>
                                <?php elseif ($creditPct >= 80): ?>
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:9px;"><?= round($creditPct) ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td>₹<?= number_format($party['credit_limit'], 0) ?></td>
                            <td>
                                <a href="<?= base_url('admin/sales_party_detail.php?id=' . $party['id']) ?>" class="btn btn-success btn-sm me-1">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                <div class="dropdown d-inline">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="<?= base_url('admin/sales_party_detail.php?id=' . $party['id']) ?>">
                                                <i class="fas fa-store me-2"></i>Full Details
                                            </a>
                                        </li>
                                    </ul>
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

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
