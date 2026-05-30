<?php
/**
 * Cleanup Script - Remove Duplicate Transaction Records for Asif General Store
 * This script removes duplicate payment history entries and collections
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Cleanup Asif Transactions';
$adminPage = 'sales_orders';

$cleanupResults = [];
$executed = false;

// Handle cleanup execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_cleanup'])) {
    $db = get_db_connection();
    $db->beginTransaction();
    
    try {
        // Find Asif General Store party
        $asifParty = db_fetch("SELECT id, shop_name FROM sales_parties WHERE shop_name LIKE '%Asif%General%Store%' LIMIT 1");
        
        if (!$asifParty) {
            throw new Exception('Asif General Store party not found');
        }
        
        $partyId = $asifParty['id'];
        $partyName = $asifParty['shop_name'];
        
        // 1. Find and remove duplicate payment history entries
        // Keep only the oldest entry for each unique reference_number
        $duplicatePayments = db_fetch_all(
            "SELECT reference_number, COUNT(*) as cnt, MIN(id) as keep_id
             FROM sales_payment_history 
             WHERE party_id = ? 
             AND reference_number IS NOT NULL
             GROUP BY reference_number 
             HAVING cnt > 1",
            [$partyId]
        );
        
        $deletedPayments = 0;
        foreach ($duplicatePayments as $dup) {
            // Delete all except the oldest (keep_id)
            $result = db_query(
                "DELETE FROM sales_payment_history 
                 WHERE party_id = ? 
                 AND reference_number = ? 
                 AND id != ?",
                [$partyId, $dup['reference_number'], $dup['keep_id']]
            );
            $deletedPayments += $result->rowCount();
        }
        
        // 2. Find and remove duplicate collection entries
        // Keep only one collection per collection_number
        $duplicateCollections = db_fetch_all(
            "SELECT collection_number, COUNT(*) as cnt, MIN(id) as keep_id
             FROM sales_collections 
             WHERE party_id = ? 
             GROUP BY collection_number 
             HAVING cnt > 1",
            [$partyId]
        );
        
        $deletedCollections = 0;
        foreach ($duplicateCollections as $dup) {
            // Delete all except the oldest (keep_id)
            $result = db_query(
                "DELETE FROM sales_collections 
                 WHERE party_id = ? 
                 AND collection_number = ? 
                 AND id != ?",
                [$partyId, $dup['collection_number'], $dup['keep_id']]
            );
            $deletedCollections += $result->rowCount();
        }
        
        // 3. Remove orphaned payment history (no linked order)
        $orphanedPayments = db_query(
            "DELETE FROM sales_payment_history 
             WHERE party_id = ? 
             AND order_id IS NOT NULL 
             AND order_id NOT IN (SELECT id FROM sales_orders)",
            [$partyId]
        );
        $deletedOrphaned = $orphanedPayments->rowCount();
        
        $db->commit();
        
        // Recalculate outstanding for Asif party
        recalculate_party_outstanding($partyId);
        
        $cleanupResults = [
            'success' => true,
            'party_name' => $partyName,
            'party_id' => $partyId,
            'deleted_duplicate_payments' => $deletedPayments,
            'deleted_duplicate_collections' => $deletedCollections,
            'deleted_orphaned_payments' => $deletedOrphaned,
            'total_deleted' => $deletedPayments + $deletedCollections + $deletedOrphaned
        ];
        
        $executed = true;
        $_SESSION['flash'] = [
            'type' => 'success', 
            'message' => "Cleanup completed for {$partyName}. Deleted {$cleanupResults['total_deleted']} duplicate/orphaned records."
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $cleanupResults = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cleanup failed: ' . $e->getMessage()];
    }
}

// Preview mode - show what will be cleaned
$previewData = [];
try {
    $asifParty = db_fetch("SELECT id, shop_name, outstanding_amount FROM sales_parties WHERE shop_name LIKE '%Asif%General%Store%' LIMIT 1");
    
    if ($asifParty) {
        $partyId = $asifParty['id'];
        
        // Count duplicate payments
        $dupPayments = db_fetch(
            "SELECT COUNT(*) as total FROM (
                SELECT reference_number, COUNT(*) as cnt
                FROM sales_payment_history 
                WHERE party_id = ? 
                AND reference_number IS NOT NULL
                GROUP BY reference_number 
                HAVING cnt > 1
            ) as dups",
            [$partyId]
        );
        
        // Count duplicate collections
        $dupCollections = db_fetch(
            "SELECT COUNT(*) as total FROM (
                SELECT collection_number, COUNT(*) as cnt
                FROM sales_collections 
                WHERE party_id = ? 
                GROUP BY collection_number 
                HAVING cnt > 1
            ) as dups",
            [$partyId]
        );
        
        // Count orphaned payments
        $orphaned = db_fetch(
            "SELECT COUNT(*) as total 
             FROM sales_payment_history 
             WHERE party_id = ? 
             AND order_id IS NOT NULL 
             AND order_id NOT IN (SELECT id FROM sales_orders)",
            [$partyId]
        );
        
        $previewData = [
            'party' => $asifParty,
            'duplicate_payments' => (int)($dupPayments['total'] ?? 0),
            'duplicate_collections' => (int)($dupCollections['total'] ?? 0),
            'orphaned_payments' => (int)($orphaned['total'] ?? 0)
        ];
    }
} catch (Exception $e) {
    $previewData['error'] = $e->getMessage();
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Cleanup Asif General Store Transactions</h2>
            <p class="text-muted mb-0">Remove duplicate and orphaned transaction records</p>
        </div>
        <a href="<?= base_url('admin/sales_orders.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>

    <?php if ($executed && !empty($cleanupResults)): ?>
        <!-- Cleanup Results -->
        <div class="card shadow-sm mb-4 border-start border-success border-4">
            <div class="card-header bg-success bg-opacity-10">
                <h5 class="mb-0 text-success"><i class="fas fa-check-circle me-2"></i>Cleanup Completed</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Party:</strong> <?= htmlspecialchars($cleanupResults['party_name']) ?></p>
                        <p><strong>Party ID:</strong> <?= $cleanupResults['party_id'] ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Duplicate Payments Deleted:</strong> <?= $cleanupResults['deleted_duplicate_payments'] ?></p>
                        <p><strong>Duplicate Collections Deleted:</strong> <?= $cleanupResults['deleted_duplicate_collections'] ?></p>
                        <p><strong>Orphaned Payments Deleted:</strong> <?= $cleanupResults['deleted_orphaned_payments'] ?></p>
                        <p class="fw-bold text-success"><strong>Total Records Deleted:</strong> <?= $cleanupResults['total_deleted'] ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($previewData) && !isset($previewData['error'])): ?>
        <!-- Preview Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning bg-opacity-10">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Preview - Records to be Cleaned</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Party:</strong> <?= htmlspecialchars($previewData['party']['shop_name']) ?></p>
                        <p><strong>Party ID:</strong> <?= $previewData['party']['id'] ?></p>
                        <p><strong>Current Outstanding:</strong> ₹<?= number_format($previewData['party']['outstanding_amount'], 2) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Duplicate Payment Groups:</strong> <span class="badge bg-danger"><?= $previewData['duplicate_payments'] ?></span></p>
                        <p><strong>Duplicate Collection Groups:</strong> <span class="badge bg-danger"><?= $previewData['duplicate_collections'] ?></span></p>
                        <p><strong>Orphaned Payments:</strong> <span class="badge bg-warning"><?= $previewData['orphaned_payments'] ?></span></p>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>What will be cleaned:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Duplicate payment history entries (keeps oldest entry per reference number)</li>
                        <li>Duplicate collection entries (keeps oldest entry per collection number)</li>
                        <li>Orphaned payment records (linked to deleted orders)</li>
                    </ul>
                </div>

                <?php if ($previewData['duplicate_payments'] > 0 || $previewData['duplicate_collections'] > 0 || $previewData['orphaned_payments'] > 0): ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to clean up these records? This action cannot be undone.');">
                        <button type="submit" name="execute_cleanup" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Execute Cleanup
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>No duplicate or orphaned records found. Database is clean!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif (isset($previewData['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>Error: <?= htmlspecialchars($previewData['error']) ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>Asif General Store party not found in the database.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
