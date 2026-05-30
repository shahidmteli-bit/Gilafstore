<?php
/**
 * Admin Panel - Sales Cash Collection Management
 * View, confirm/reject collections, track dues per sales person
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment_adjustment_helper.php';
require_once __DIR__ . '/../includes/order_deletion_helper.php';
require_admin();

$pageTitle = 'Cash Collections';
$adminPage = 'sales_collections';

// Handle confirm / reject / settle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colId = (int)($_POST['collection_id'] ?? 0);
    $action = $_POST['col_action'] ?? '';
    $remarks = trim($_POST['admin_remarks'] ?? '');

    if ($colId > 0) {
        $col = db_fetch('SELECT * FROM sales_collections WHERE id = ?', [$colId]);
        if ($col) {
            if ($action === 'confirmed') {
                // Check for duplicate payment voucher
                $refNum = $col['payment_method'] === 'cheque' ? $col['cheque_number'] : ($col['online_reference'] ?? $col['collection_number']);
                
                if (is_duplicate_payment_voucher((int)$col['party_id'], $refNum)) {
                    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Payment voucher already exists for Collection #' . $col['collection_number'] . '. Duplicate prevented.'];
                } else {
                    // Check if payment exceeds outstanding
                    $excessCheck = check_payment_excess((int)$col['party_id'], (float)$col['amount']);
                    
                    if ($excessCheck['has_excess']) {
                        $_SESSION['flash'] = ['type' => 'warning', 'message' => $excessCheck['warning'] . ' Collection not confirmed.'];
                    } else {
                        // Use payment adjustment helper with FIFO logic
                        $result = adjust_payment_to_orders(
                            (int)$col['party_id'],
                            (float)$col['amount'],
                            $col['payment_method'],
                            $refNum,
                            'Collection #' . $col['collection_number'] . ' confirmed. ' . $remarks,
                            $col['executive_id'],
                            $colId
                        );
                        
                        if ($result['success']) {
                            db_query('UPDATE sales_collections SET status = "confirmed", admin_remarks = ?, confirmed_by = ?, confirmed_at = NOW() WHERE id = ?', [
                                $remarks, $_SESSION['admin_id'] ?? 0, $colId
                            ]);
                            recalculate_party_outstanding((int)$col['party_id']);
                            
                            $msg = 'Collection #' . $col['collection_number'] . ' confirmed. ₹' . number_format($result['total_adjusted'], 2) . ' adjusted against ' . count($result['adjustments']) . ' order(s).';
                            if ($result['excess_payment'] > 0) {
                                $msg .= ' Excess: ₹' . number_format($result['excess_payment'], 2);
                            }
                            $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
                        } else {
                            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Payment adjustment failed: ' . ($result['error'] ?? 'Unknown error')];
                        }
                    }
                }

            } elseif ($action === 'rejected') {
                db_query('UPDATE sales_collections SET status = "rejected", admin_remarks = ?, confirmed_by = ?, confirmed_at = NOW() WHERE id = ?', [
                    $remarks, $_SESSION['admin_id'] ?? 0, $colId
                ]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Collection #' . $col['collection_number'] . ' rejected. Amount remains as dues on sales person.'];

            } elseif ($action === 'settle_cash') {
                db_query('UPDATE sales_collections SET is_settled = 1, settled_at = NOW() WHERE id = ?', [$colId]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cash collection #' . $col['collection_number'] . ' marked as settled (cash received from sales person).'];
            } elseif ($action === 'delete') {
                $result = delete_collection_cascade($colId);
                if ($result['success']) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => $result['message']];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => $result['message']];
                }
            }
        }
    }
    header('Location: ' . base_url('admin/sales_collections.php') . '?' . http_build_query($_GET));
    exit;
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterExec = (int)($_GET['exec'] ?? 0);
$filterMethod = $_GET['method'] ?? '';

$hasArea = false;
try { db_fetch('SELECT assigned_area FROM sales_executives LIMIT 1'); $hasArea = true; } catch (PDOException $e) {}
$hasCode = false;
try { db_fetch('SELECT party_code FROM sales_parties LIMIT 1'); $hasCode = true; } catch (PDOException $e) {}
$extraExecCols = $hasArea ? ', se.assigned_area' : '';
$extraPartyCols = $hasCode ? ', sp.party_code' : '';
$sql = 'SELECT sc.*, sp.shop_name, sp.owner_name' . $extraPartyCols . ', se.name as exec_name, se.district' . $extraExecCols . ' FROM sales_collections sc JOIN sales_parties sp ON sc.party_id = sp.id JOIN sales_executives se ON sc.executive_id = se.id WHERE 1=1';
$params = [];

if ($filterStatus) {
    $sql .= ' AND sc.status = ?';
    $params[] = $filterStatus;
}
if ($filterExec > 0) {
    $sql .= ' AND sc.executive_id = ?';
    $params[] = $filterExec;
}
if ($filterMethod) {
    $sql .= ' AND sc.payment_method = ?';
    $params[] = $filterMethod;
}
$sql .= ' ORDER BY sc.created_at DESC';
$collections = db_fetch_all($sql, $params);

$executives = db_fetch_all('SELECT id, name FROM sales_executives ORDER BY name ASC');

// Summary
$pendingCount = db_fetch('SELECT COUNT(*) as c FROM sales_collections WHERE status = "pending"')['c'] ?? 0;
$pendingAmount = db_fetch('SELECT COALESCE(SUM(amount),0) as t FROM sales_collections WHERE status = "pending"')['t'] ?? 0;
$confirmedToday = db_fetch('SELECT COALESCE(SUM(amount),0) as t FROM sales_collections WHERE status = "confirmed" AND DATE(confirmed_at) = CURDATE()')['t'] ?? 0;
$unsettledCash = db_fetch('SELECT COALESCE(SUM(amount),0) as t FROM sales_collections WHERE status = "confirmed" AND payment_method = "cash" AND is_settled = 0')['t'] ?? 0;

// Dues per sales person (cash confirmed but not settled)
$duesPerExec = db_fetch_all('SELECT se.name, se.id as exec_id, COALESCE(SUM(sc.amount),0) as dues FROM sales_collections sc JOIN sales_executives se ON sc.executive_id = se.id WHERE sc.status = "confirmed" AND sc.payment_method = "cash" AND sc.is_settled = 0 GROUP BY sc.executive_id HAVING dues > 0 ORDER BY dues DESC');

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Cash Collections</h2>
            <p class="text-muted mb-0">
                Manage payment collections from sales executives
                <?php if ($pendingCount > 0): ?>
                    <span class="badge bg-warning text-dark ms-2"><?= $pendingCount ?> pending</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-warning border-4 p-3">
                <div class="fw-bold fs-4 text-warning"><?= $pendingCount ?></div>
                <div class="text-muted small">Pending (₹<?= number_format($pendingAmount, 0) ?>)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-success border-4 p-3">
                <div class="fw-bold fs-4 text-success">₹<?= number_format($confirmedToday, 0) ?></div>
                <div class="text-muted small">Confirmed Today</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-danger border-4 p-3">
                <div class="fw-bold fs-4 text-danger">₹<?= number_format($unsettledCash, 0) ?></div>
                <div class="text-muted small">Unsettled Cash (Dues)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-start border-info border-4 p-3">
                <div class="fw-bold fs-4 text-info"><?= count($duesPerExec) ?></div>
                <div class="text-muted small">Executives with Dues</div>
            </div>
        </div>
    </div>

    <!-- Dues per Sales Person -->
    <?php if (!empty($duesPerExec)): ?>
    <div class="card shadow-sm mb-4 border-start border-danger border-4">
        <div class="card-header bg-danger bg-opacity-10">
            <h6 class="mb-0 fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Cash Dues — Sales Person Wise</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Sales Executive</th>
                            <th>Cash Dues (₹)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duesPerExec as $d): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
                            <td class="fw-bold text-danger fs-5">₹<?= number_format($d['dues'], 0) ?></td>
                            <td>
                                <a href="?exec=<?= $d['exec_id'] ?>&status=confirmed&method=cash" class="btn btn-outline-danger btn-sm">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label fw-semibold small">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">Executive</label>
                <select name="exec" class="form-select">
                    <option value="0">All</option>
                    <?php foreach ($executives as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filterExec == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label fw-semibold small">Method</label>
                <select name="method" class="form-select">
                    <option value="">All</option>
                    <option value="cash" <?= $filterMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="cheque" <?= $filterMethod === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                    <option value="online_transfer" <?= $filterMethod === 'online_transfer' ? 'selected' : '' ?>>Online</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= base_url('admin/sales_collections.php') ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>

    <!-- Collections Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Collection #</th>
                            <th>Executive</th>
                            <th>Party</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($collections)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted"><i class="fas fa-receipt fa-3x mb-3 d-block"></i>No collections found.</td></tr>
                        <?php else: ?>
                        <?php
                        $methodLabels = ['cash' => '<span class="badge bg-success">Cash</span>', 'cheque' => '<span class="badge bg-primary">Cheque</span>', 'online_transfer' => '<span class="badge bg-info">Online</span>'];
                        $statusBadge = ['pending' => 'bg-warning text-dark', 'confirmed' => 'bg-success', 'rejected' => 'bg-danger'];
                        foreach ($collections as $col):
                        ?>
                        <tr>
                            <td><code class="small"><?= htmlspecialchars($col['collection_number']) ?></code></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($col['exec_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($col['assigned_area'] ?? $col['district'] ?? '') ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($col['shop_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($col['owner_name']) ?></div>
                            </td>
                            <td class="fw-bold text-success fs-6">₹<?= number_format($col['amount'], 0) ?></td>
                            <td><?= $methodLabels[$col['payment_method']] ?? $col['payment_method'] ?></td>
                            <td class="small">
                                <?php if ($col['payment_method'] === 'cheque'): ?>
                                    <strong>Chq#:</strong> <?= htmlspecialchars($col['cheque_number']) ?><br>
                                    <?php if ($col['cheque_bank']): ?><strong>Bank:</strong> <?= htmlspecialchars($col['cheque_bank']) ?><br><?php endif; ?>
                                    <?php if ($col['cheque_date']): ?><strong>Date:</strong> <?= date('d M Y', strtotime($col['cheque_date'])) ?><?php endif; ?>
                                <?php elseif ($col['payment_method'] === 'online_transfer'): ?>
                                    <strong>A/C:</strong> <?= $col['online_account'] === 'gilaf_account' ? 'Gilaf Official' : 'Other' ?><br>
                                    <?php if ($col['online_reference']): ?><strong>Ref:</strong> <?= htmlspecialchars($col['online_reference']) ?><?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Cash payment</span>
                                <?php endif; ?>
                                <?php if ($col['notes']): ?><br><em class="text-muted"><?= htmlspecialchars($col['notes']) ?></em><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $statusBadge[$col['status']] ?? 'bg-secondary' ?>"><?= ucfirst($col['status']) ?></span>
                                <?php if ($col['status'] === 'confirmed' && $col['payment_method'] === 'cash'): ?>
                                    <?php if ($col['is_settled']): ?>
                                        <br><span class="badge bg-success mt-1" style="font-size:9px;">Cash Settled</span>
                                    <?php else: ?>
                                        <br><span class="badge bg-danger mt-1" style="font-size:9px;">Cash NOT Settled</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= date('d M Y', strtotime($col['created_at'])) ?><br><?= date('h:i A', strtotime($col['created_at'])) ?></td>
                            <td>
                                <?php if ($col['status'] === 'pending'): ?>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                    <ul class="dropdown-menu">
                                        <li><button class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#confirmModal<?= $col['id'] ?>"><i class="fas fa-check me-2"></i>Confirm</button></li>
                                        <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $col['id'] ?>"><i class="fas fa-times me-2"></i>Reject</button></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $col['id'] ?>"><i class="fas fa-trash me-2"></i>Delete</button></li>
                                    </ul>
                                </div>

                                <!-- Confirm Modal -->
                                <div class="modal fade" id="confirmModal<?= $col['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                                <input type="hidden" name="col_action" value="confirmed">
                                                <div class="modal-header bg-success text-white">
                                                    <h6 class="modal-title"><i class="fas fa-check-circle me-1"></i> Confirm Collection</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-info small mb-3">
                                                        <strong><?= htmlspecialchars($col['exec_name']) ?></strong> collected
                                                        <strong class="text-success">₹<?= number_format($col['amount'], 0) ?></strong>
                                                        (<?= ucfirst(str_replace('_', ' ', $col['payment_method'])) ?>)
                                                        from <strong><?= htmlspecialchars($col['shop_name']) ?></strong>
                                                    </div>
                                                    <p class="small text-muted mb-2">Confirming will reduce the party's outstanding by ₹<?= number_format($col['amount'], 0) ?>.</p>
                                                    <?php if ($col['payment_method'] === 'cash'): ?>
                                                        <div class="alert alert-warning small"><i class="fas fa-exclamation-triangle me-1"></i> This is a <strong>cash</strong> collection. The amount will be tracked as <strong>dues on the sales person</strong> until you mark it as settled.</div>
                                                    <?php endif; ?>
                                                    <label class="form-label fw-semibold small">Remarks</label>
                                                    <textarea name="admin_remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Confirm Collection</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?= $col['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                                <input type="hidden" name="col_action" value="rejected">
                                                <div class="modal-header bg-danger text-white">
                                                    <h6 class="modal-title"><i class="fas fa-times-circle me-1"></i> Reject Collection</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-danger small mb-3">
                                                        Rejecting this means the payment of <strong>₹<?= number_format($col['amount'], 0) ?></strong> from <strong><?= htmlspecialchars($col['shop_name']) ?></strong> is not valid. The amount stays as <strong>dues on <?= htmlspecialchars($col['exec_name']) ?></strong>.
                                                    </div>
                                                    <label class="form-label fw-semibold small">Reason for rejection *</label>
                                                    <textarea name="admin_remarks" class="form-control" rows="2" required placeholder="Why is this being rejected?"></textarea>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Reject</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php elseif ($col['status'] === 'confirmed' && $col['payment_method'] === 'cash' && !$col['is_settled']): ?>
                                    <div class="btn-group" role="group">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                            <input type="hidden" name="col_action" value="settle_cash">
                                            <button type="submit" class="btn btn-outline-success btn-sm" onclick="return confirm('Mark this cash as received from the sales person?')">
                                                <i class="fas fa-hand-holding-usd me-1"></i>Settle Cash
                                            </button>
                                        </form>
                                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $col['id'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php elseif ($col['status'] === 'confirmed'): ?>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="text-success small"><i class="fas fa-check"></i></span>
                                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $col['id'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php elseif ($col['status'] === 'rejected'): ?>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="text-muted small"><?= htmlspecialchars($col['admin_remarks'] ?? '') ?></span>
                                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $col['id'] ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?= $col['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                                <input type="hidden" name="col_action" value="delete">
                                                <div class="modal-header bg-danger text-white">
                                                    <h6 class="modal-title"><i class="fas fa-trash me-1"></i> Delete Collection</h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="alert alert-danger">
                                                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Warning!</strong>
                                                        This will permanently delete collection <strong><?= htmlspecialchars($col['collection_number']) ?></strong> and reverse all its effects:
                                                    </div>
                                                    <ul class="small">
                                                        <li>Amount: <strong>₹<?= number_format($col['amount'], 2) ?></strong></li>
                                                        <li>Party: <strong><?= htmlspecialchars($col['shop_name']) ?></strong></li>
                                                        <li>Status: <strong><?= ucfirst($col['status']) ?></strong></li>
                                                        <?php if ($col['status'] === 'confirmed'): ?>
                                                        <li class="text-danger"><strong>Payment allocations will be reversed</strong></li>
                                                        <li class="text-danger"><strong>Outstanding will be recalculated</strong></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                    <p class="text-danger fw-bold mb-0"><i class="fas fa-exclamation-circle me-1"></i>This action cannot be undone!</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
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
