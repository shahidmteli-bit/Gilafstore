<?php
/**
 * Sales Portal - Collect Payment from Party
 * Records cash/cheque/online payments received from parties
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment_adjustment_helper.php';
if (file_exists(__DIR__ . '/../includes/order_deletion_helper.php')) {
    require_once __DIR__ . '/../includes/order_deletion_helper.php';
}
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Collect Payment';
$currentPage = 'collect_payment';

// Pre-selected party
$prePartyId = (int)($_GET['party_id'] ?? 0);

// Fetch parties for dropdown (include inactive parties with outstanding amounts)
$allParties = db_fetch_all('SELECT id, shop_name, owner_name, phone, outstanding_amount, party_code, is_active FROM sales_parties WHERE created_by = ? AND (is_active = 1 OR outstanding_amount > 0) ORDER BY shop_name ASC', [$execId]);

// Total outstanding across all parties
$totalOutstandingAll = 0;
foreach ($allParties as $ap) $totalOutstandingAll += (float)$ap['outstanding_amount'];

// Handle collection deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_collection'])) {
    $collectionId = (int)($_POST['collection_id'] ?? 0);
    if ($collectionId > 0) {
        if (function_exists('delete_collection_cascade')) {
            $result = delete_collection_cascade($collectionId);
            if ($result['success']) {
                $_SESSION['sp_flash'] = ['type' => 'success', 'message' => $result['message']];
            } else {
                $_SESSION['sp_flash'] = ['type' => 'error', 'message' => $result['message']];
            }
        } else {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Delete function not available. Please upload order_deletion_helper.php'];
        }
    }
    header('Location: ' . sales_base_url('collect_payment.php'));
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_collection'])) {
    $partyId = (int)($_POST['party_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $chequeNumber = trim($_POST['cheque_number'] ?? '');
    $chequeDate = $_POST['cheque_date'] ?? null;
    $chequeBank = trim($_POST['cheque_bank'] ?? '');
    $onlineAccount = $_POST['online_account'] ?? null;
    $onlineReference = trim($_POST['online_reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    // Validate
    $errors = [];
    if ($partyId <= 0) $errors[] = 'Please select a party.';
    
    // Fetch party to check outstanding
    $party = db_fetch('SELECT outstanding_amount FROM sales_parties WHERE id = ?', [$partyId]);
    $outstanding = $party ? (float)$party['outstanding_amount'] : 0;
    
    // Validate outstanding and amount
    if ($outstanding <= 0) {
        $errors[] = 'No payment can be collected because outstanding amount is zero.';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than zero.';
    }
    if ($amount > $outstanding) {
        $errors[] = 'Received amount (₹' . number_format($amount, 2) . ') cannot be greater than outstanding amount (₹' . number_format($outstanding, 2) . ').';
    }
    
    if (!in_array($paymentMethod, ['cash', 'cheque', 'online_transfer'])) $errors[] = 'Invalid payment method.';
    if ($paymentMethod === 'cheque' && empty($chequeNumber)) $errors[] = 'Cheque number is required.';
    if ($paymentMethod === 'online_transfer' && empty($onlineAccount)) $errors[] = 'Please select the account type.';

    if (!empty($errors)) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    } else {
        // Save original outstanding BEFORE any payment processing
        $originalOutstanding = $outstanding;
        try {
            // Generate collection number
            $collNum = 'COL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Insert collection record with status = 'confirmed' (auto-confirmed)
            db_query('INSERT INTO sales_collections (collection_number, executive_id, party_id, amount, payment_method, cheque_number, cheque_date, cheque_bank, online_account, online_reference, notes, status, confirmed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [
                $collNum, $execId, $partyId, $amount, $paymentMethod,
                $paymentMethod === 'cheque' ? $chequeNumber : null,
                $paymentMethod === 'cheque' && $chequeDate ? $chequeDate : null,
                $paymentMethod === 'cheque' ? $chequeBank : null,
                $paymentMethod === 'online_transfer' ? $onlineAccount : null,
                $paymentMethod === 'online_transfer' ? $onlineReference : null,
                $notes,
                'confirmed'
            ]);
            
            $collectionId = function_exists('db_last_insert_id') ? db_last_insert_id() : 0;

            // Auto-apply FIFO payment allocation immediately (if function exists)
            $adjustmentResult = ['success' => false];
            if (function_exists('adjust_payment_to_orders')) {
                try {
                    $refNum = $collNum;
                    if ($paymentMethod === 'cheque' && $chequeNumber) {
                        $refNum .= ' | Cheque: ' . $chequeNumber;
                    } elseif ($paymentMethod === 'online_transfer' && $onlineReference) {
                        $refNum .= ' | Ref: ' . $onlineReference;
                    }
                    
                    $adjustmentResult = adjust_payment_to_orders(
                        $partyId,
                        $amount,
                        $paymentMethod,
                        $refNum,
                        'Collection #' . $collNum . ' recorded by ' . $exec['name'] . '. ' . $notes,
                        $execId,
                        $collectionId
                    );
                } catch (Exception $adjEx) {
                    // Continue even if adjustment fails
                }
            }

            // Recalculate party outstanding (if function exists)
            if (function_exists('recalculate_party_outstanding')) {
                try {
                    recalculate_party_outstanding($partyId);
                } catch (Exception $recalcEx) {
                    // Continue even if recalculation fails
                }
            }
            
            // Fetch updated party details for share prompt
            $shareParty = db_fetch('SELECT shop_name, phone, outstanding_amount, party_code FROM sales_parties WHERE id = ?', [$partyId]);
            
            $successMsg = 'Payment of ₹' . number_format($amount, 0) . ' recorded successfully. Collection #' . $collNum;
            if ($adjustmentResult['success']) {
                $successMsg .= ' | Adjusted against ' . count($adjustmentResult['adjustments']) . ' order(s).';
                if (isset($adjustmentResult['orders_settled']) && $adjustmentResult['orders_settled'] > 0) {
                    $successMsg .= ' ' . $adjustmentResult['orders_settled'] . ' order(s) fully paid.';
                }
            }
            
            $_SESSION['sp_flash'] = ['type' => 'success', 'message' => $successMsg];
            // Use ORIGINAL outstanding (before payment) and UPDATED outstanding (after recalculation)
            $updatedOutstanding = (float)($shareParty['outstanding_amount'] ?? 0);
            $_SESSION['sp_share_payment'] = [
                'party_name' => $shareParty['shop_name'] ?? '',
                'party_phone' => $shareParty['phone'] ?? '',
                'party_code' => $shareParty['party_code'] ?? '',
                'collection_number' => $collNum,
                'amount' => number_format($amount, 0),
                'date' => date('d M Y'),
                'previous_outstanding' => number_format($originalOutstanding, 0),
                'outstanding' => number_format(max(0, $updatedOutstanding), 0),
            ];
            header('Location: ' . sales_base_url('collect_payment.php'));
            exit;
        } catch (Exception $e) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Fetch collection history
$collections = [];
try {
    $collections = db_fetch_all('SELECT sc.*, sp.shop_name, sp.owner_name, sp.phone as party_phone, sp.party_code, sp.outstanding_amount as party_outstanding FROM sales_collections sc JOIN sales_parties sp ON sc.party_id = sp.id WHERE sc.executive_id = ? ORDER BY sc.created_at DESC LIMIT 50', [$execId]);
} catch (PDOException $e) { /* table may not exist */ }

// Summary stats
$pendingAmount = 0;
$confirmedToday = 0;
$confirmedMonth = 0;
$duesOnHead = 0;
try {
    $pendingAmount = db_fetch('SELECT COALESCE(SUM(amount),0) as t FROM sales_collections WHERE executive_id = ? AND status = "pending"', [$execId])['t'] ?? 0;
    $confirmedToday = db_fetch('SELECT COALESCE(SUM(amount),0) as t FROM sales_collections WHERE executive_id = ? AND status = "confirmed" AND DATE(confirmed_at) = CURDATE()', [$execId])['t'] ?? 0;
    $confirmedMonth = db_fetch('SELECT COALESCE(SUM(amount),0) as t FROM sales_collections WHERE executive_id = ? AND status = "confirmed" AND MONTH(confirmed_at) = MONTH(NOW()) AND YEAR(confirmed_at) = YEAR(NOW())', [$execId])['t'] ?? 0;
    // Dues = confirmed collections not yet settled (money still with sales person)
    $duesOnHead = db_fetch('SELECT COALESCE(SUM(amount),0) as t FROM sales_collections WHERE executive_id = ? AND status = "confirmed" AND is_settled = 0 AND payment_method = "cash"', [$execId])['t'] ?? 0;
} catch (PDOException $e) { /* table may not exist */ }

// Check for share prompt after successful payment
$sharePayment = null;
if (isset($_SESSION['sp_share_payment'])) {
    $sharePayment = $_SESSION['sp_share_payment'];
    unset($_SESSION['sp_share_payment']);
}

include __DIR__ . '/includes/header.php';
?>

<!-- Total Outstanding Banner -->
<div style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border-radius:16px;padding:16px;margin-bottom:16px;text-align:center;">
    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:0.85;">Total Outstanding (All Parties)</div>
    <div style="font-size:28px;font-weight:800;">₹<?= number_format($totalOutstandingAll, 0) ?></div>
</div>

<!-- Stats -->
<div class="sp-collect-stats">
    <div class="sp-collect-stat" style="border-left:3px solid #d97706;">
        <div class="sp-collect-stat-val" style="color:#d97706;">₹<?= number_format($pendingAmount, 0) ?></div>
        <div class="sp-collect-stat-lbl">Pending Confirmation</div>
    </div>
    <div class="sp-collect-stat" style="border-left:3px solid #059669;">
        <div class="sp-collect-stat-val" style="color:#059669;">₹<?= number_format($confirmedToday, 0) ?></div>
        <div class="sp-collect-stat-lbl">Confirmed Today</div>
    </div>
    <div class="sp-collect-stat" style="border-left:3px solid #dc2626;">
        <div class="sp-collect-stat-val" style="color:#dc2626;">₹<?= number_format($confirmedMonth, 0) ?></div>
        <div class="sp-collect-stat-lbl">Confirmed This Month</div>
    </div>
</div>

<!-- Collect Payment Form -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-hand-holding-usd"></i> Record Payment Collection</h3>
    </div>
    <form method="POST" id="collectionForm">
        <input type="hidden" name="submit_collection" value="1">

        <div class="sp-form-group">
            <label>Select Party *</label>
            <select name="party_id" id="collPartySelect" class="sp-select" required onchange="updatePartyOutstanding()">
                <option value="">— Select Party —</option>
                <?php foreach ($allParties as $p): ?>
                    <option value="<?= $p['id'] ?>" data-outstanding="<?= $p['outstanding_amount'] ?>" data-code="<?= htmlspecialchars($p['party_code'] ?? '') ?>" <?= $prePartyId == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['shop_name']) ?> — <?= htmlspecialchars($p['owner_name']) ?>
                        <?php if ($p['outstanding_amount'] > 0): ?> (Due: ₹<?= number_format($p['outstanding_amount'], 0) ?>)<?php endif; ?>
                        <?php if (!empty($p['party_code'])): ?> [<?= $p['party_code'] ?>]<?php endif; ?>
                        <?php if (!$p['is_active']): ?> [INACTIVE]<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sp-form-group">
            <label>Outstanding</label>
            <input type="text" class="sp-input" id="partyOutstandingDisplay" readonly value="₹0" style="background:#f3f4f6;color:#dc2626;font-weight:700;">
            <input type="hidden" id="partyOutstandingValue" value="0">
        </div>

        <div class="sp-form-group">
            <label>Amount Received (₹) *</label>
            <input type="number" name="amount" id="amountReceived" class="sp-input" step="0.01" min="0.01" max="0" required placeholder="No outstanding amount" disabled oninput="validateAmount()">
            <div id="amountError" style="color:#dc2626;font-size:12px;margin-top:4px;display:none;"></div>
        </div>
        <div class="sp-form-group">
            <label>Payment Method *</label>
            <select name="payment_method" id="paymentMethod" class="sp-select" required onchange="togglePaymentFields()">
                <option value="">— Select —</option>
                <option value="cash">Cash</option>
                <option value="cheque">Cheque</option>
                <option value="online_transfer">Online Transfer</option>
            </select>
        </div>

        <!-- Cheque Fields (hidden by default) -->
        <div id="chequeFields" style="display:none;">
            <div class="sp-form-group">
                <label>Cheque Number *</label>
                <input type="text" name="cheque_number" id="chequeNumber" class="sp-input" placeholder="Enter cheque number">
            </div>
            <div class="sp-leave-dates">
                <div class="sp-form-group">
                    <label>Cheque Date</label>
                    <input type="date" name="cheque_date" class="sp-input">
                </div>
                <div class="sp-form-group">
                    <label>Bank Name</label>
                    <input type="text" name="cheque_bank" class="sp-input" placeholder="Issuing bank">
                </div>
            </div>
        </div>

        <!-- Online Transfer Fields (hidden by default) -->
        <div id="onlineFields" style="display:none;">
            <div class="sp-form-group">
                <label>Transferred To *</label>
                <select name="online_account" id="onlineAccount" class="sp-select">
                    <option value="">— Select Account —</option>
                    <option value="gilaf_account">Gilaf Store Official Account</option>
                    <option value="other">Other Account</option>
                </select>
            </div>
            <div class="sp-form-group">
                <label>Transaction / Reference Number</label>
                <input type="text" name="online_reference" class="sp-input" placeholder="UTR / Transaction ID">
            </div>
        </div>

        <!-- Notes -->
        <div class="sp-form-group">
            <label>Notes</label>
            <textarea name="notes" class="sp-textarea" rows="2" placeholder="Any additional details about this payment..."></textarea>
        </div>

        <button type="submit" id="submitBtn" class="sp-btn sp-btn-primary sp-btn-block sp-btn-lg" disabled style="opacity:0.5;cursor:not-allowed;">
            <i class="fas fa-check-circle"></i> Submit Payment Collection
        </button>
    </form>
</div>

<!-- Payment Collection Filters -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-calendar-alt"></i> Collection Summary</h3>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;" id="collFilterBtns">
        <button type="button" class="sp-btn sp-btn-outline sp-btn-sm coll-filter-btn active" data-filter="today">Today</button>
        <button type="button" class="sp-btn sp-btn-outline sp-btn-sm coll-filter-btn" data-filter="yesterday">Yesterday</button>
        <button type="button" class="sp-btn sp-btn-outline sp-btn-sm coll-filter-btn" data-filter="week">This Week</button>
        <button type="button" class="sp-btn sp-btn-outline sp-btn-sm coll-filter-btn" data-filter="month">This Month</button>
        <button type="button" class="sp-btn sp-btn-outline sp-btn-sm coll-filter-btn" data-filter="last_month">Last Month</button>
        <button type="button" class="sp-btn sp-btn-outline sp-btn-sm coll-filter-btn" data-filter="custom">Custom</button>
    </div>
    <div id="customDateRange" style="display:none;margin-bottom:12px;">
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="date" id="collDateFrom" class="sp-input" style="flex:1;">
            <span style="color:#6b7280;">to</span>
            <input type="date" id="collDateTo" class="sp-input" style="flex:1;">
            <button type="button" class="sp-btn sp-btn-primary sp-btn-sm" onclick="applyCustomFilter()"><i class="fas fa-check"></i></button>
        </div>
    </div>
    <div id="collFilterSummary" style="text-align:center;padding:12px 0;">
        <div style="font-size:24px;font-weight:800;color:#059669;" id="filteredAmount">₹<?= number_format($confirmedToday, 0) ?></div>
        <div style="font-size:12px;color:#6b7280;" id="filteredLabel">Collected Today</div>
    </div>
</div>

<!-- Collection History -->
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-history"></i> Collection History</h3>
    </div>
    <?php if (empty($collections)): ?>
        <div class="sp-empty">
            <i class="fas fa-receipt"></i>
            <h3>No collections yet</h3>
            <p>Record your first payment collection above.</p>
        </div>
    <?php else: ?>
        <div class="sp-collect-history">
            <?php
            $methodLabels = ['cash' => 'Cash', 'cheque' => 'Cheque', 'online_transfer' => 'Online'];
            $methodColors = ['cash' => '#059669', 'cheque' => '#d97706', 'online_transfer' => '#2563eb'];
            $statusBadgeColors = ['pending' => ['bg' => '#fef3c7', 'color' => '#92400e'], 'confirmed' => ['bg' => '#d1fae5', 'color' => '#065f46'], 'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b']];
            foreach ($collections as $col):
                $mc = $methodColors[$col['payment_method']] ?? '#6b7280';
                $sb = $statusBadgeColors[$col['status']] ?? ['bg' => '#f3f4f6', 'color' => '#6b7280'];
            ?>
            <div class="sp-collect-item">
                <div class="sp-collect-item-left">
                    <div class="sp-collect-item-amount">₹<?= number_format($col['amount'], 0) ?></div>
                    <div class="sp-collect-item-party"><?= htmlspecialchars($col['shop_name']) ?></div>
                    <div class="sp-collect-item-meta">
                        <span style="color:<?= $mc ?>;"><?= $methodLabels[$col['payment_method']] ?? $col['payment_method'] ?></span>
                        · <?= date('d M, h:i A', strtotime($col['created_at'])) ?>
                    </div>
                    <?php if ($col['payment_method'] === 'cheque' && $col['cheque_number']): ?>
                        <div class="sp-collect-item-detail">Chq# <?= htmlspecialchars($col['cheque_number']) ?></div>
                    <?php elseif ($col['payment_method'] === 'online_transfer' && $col['online_reference']): ?>
                        <div class="sp-collect-item-detail">Ref: <?= htmlspecialchars($col['online_reference']) ?></div>
                    <?php endif; ?>
                    <?php if ($col['admin_remarks']): ?>
                        <div class="sp-collect-item-detail" style="color:#7c3aed;"><i class="fas fa-comment-alt"></i> <?= htmlspecialchars($col['admin_remarks']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <div class="sp-collect-item-badge" style="background:<?= $sb['bg'] ?>;color:<?= $sb['color'] ?>;">
                        <?= ucfirst($col['status']) ?>
                    </div>
                    <?php if ($col['status'] === 'confirmed' && $col['payment_method'] === 'cash' && !$col['is_settled']): ?>
                        <div class="sp-collect-item-unsettled">Unsettled</div>
                    <?php endif; ?>
                    <div style="display:flex;gap:6px;margin-top:6px;">
                        <?php
                            // For history items: Previous outstanding = current + this payment amount
                            $histCurrentOutstanding = (float)($col['party_outstanding'] ?? 0);
                            $histPaymentAmt = (float)($col['amount'] ?? 0);
                            $histPreviousOutstanding = $histCurrentOutstanding + $histPaymentAmt;
                        ?>
                        <span class="sp-order-share-btn" onclick="sharePaymentWhatsApp(<?= $col['id'] ?>,'<?= htmlspecialchars(addslashes($col['shop_name'])) ?>','<?= htmlspecialchars($col['party_code'] ?? '') ?>','<?= htmlspecialchars($col['collection_number'] ?? '') ?>','<?= number_format($histPaymentAmt, 0) ?>','<?= date('d M Y', strtotime($col['created_at'])) ?>','<?= number_format($histPreviousOutstanding, 0) ?>','<?= htmlspecialchars($col['party_phone'] ?? '') ?>','<?= number_format($histCurrentOutstanding, 0) ?>')" title="Share on WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </span>
                        <button type="button" class="sp-order-share-btn" style="background:#dc2626;" onclick="confirmDeleteCollection(<?= $col['id'] ?>,'<?= htmlspecialchars(addslashes($col['collection_number'])) ?>','<?= number_format($col['amount'], 0) ?>','<?= htmlspecialchars(addslashes($col['shop_name'])) ?>','<?= $col['status'] ?>')" title="Delete Collection">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($sharePayment): ?>
<!-- Share Payment Prompt after successful submission -->
<div id="sharePaymentPrompt" style="position:fixed;bottom:0;left:0;right:0;z-index:9999;background:#fff;border-top:2px solid #25D366;box-shadow:0 -4px 20px rgba(0,0,0,0.15);padding:16px;border-radius:16px 16px 0 0;animation:slideUp 0.3s ease-out;">
    <style>@keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}</style>
    <div style="text-align:center;margin-bottom:12px;">
        <i class="fas fa-check-circle" style="color:#059669;font-size:28px;"></i>
        <div style="font-weight:700;font-size:15px;margin-top:4px;">Payment Recorded!</div>
        <div style="font-size:12px;color:#6b7280;">₹<?= $sharePayment['amount'] ?> from <?= htmlspecialchars($sharePayment['party_name']) ?></div>
    </div>
    <button type="button" onclick="sharePaymentWhatsApp(null,'<?= htmlspecialchars(addslashes($sharePayment['party_name'])) ?>','<?= htmlspecialchars($sharePayment['party_code'] ?? '') ?>','<?= htmlspecialchars($sharePayment['collection_number']) ?>','<?= $sharePayment['amount'] ?>','<?= $sharePayment['date'] ?>','<?= $sharePayment['previous_outstanding'] ?? $sharePayment['outstanding'] ?>','<?= htmlspecialchars($sharePayment['party_phone']) ?>','<?= $sharePayment['outstanding'] ?>');closeSharePrompt();" style="width:100%;background:#25D366;color:#fff;border:none;border-radius:10px;padding:12px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px;">
        <i class="fab fa-whatsapp" style="font-size:20px;"></i> Share Receipt on WhatsApp
    </button>
    <button type="button" onclick="closeSharePrompt()" style="width:100%;background:#f3f4f6;color:#6b7280;border:none;border-radius:10px;padding:10px;font-size:13px;cursor:pointer;">Skip</button>
</div>
<script>function closeSharePrompt(){document.getElementById('sharePaymentPrompt').remove();}</script>
<?php endif; ?>

<script>
function updatePartyOutstanding() {
    var sel = document.getElementById('collPartySelect');
    var opt = sel.options[sel.selectedIndex];
    var outstanding = parseFloat(opt.dataset.outstanding || 0);
    
    // Update display
    document.getElementById('partyOutstandingDisplay').value = '₹' + outstanding.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('partyOutstandingValue').value = outstanding;
    
    var amountInput = document.getElementById('amountReceived');
    var submitBtn = document.getElementById('submitBtn');
    var amountError = document.getElementById('amountError');
    
    // Clear amount field and error
    amountInput.value = '';
    amountError.style.display = 'none';
    
    if (outstanding <= 0) {
        // Disable amount field when outstanding is 0
        amountInput.disabled = true;
        amountInput.placeholder = 'No outstanding amount';
        amountInput.min = '0';
        amountInput.max = '0';
        amountInput.style.background = '#f3f4f6';
        amountInput.style.cursor = 'not-allowed';
        
        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
    } else {
        // Enable amount field when outstanding > 0
        amountInput.disabled = false;
        amountInput.placeholder = 'Enter amount (max ₹' + outstanding.toFixed(2) + ')';
        amountInput.min = '0.01';
        amountInput.max = outstanding.toFixed(2);
        amountInput.style.background = '#fff';
        amountInput.style.cursor = 'text';
    }
    
    // Revalidate form
    validateForm();
}

function validateAmount() {
    var amountInput = document.getElementById('amountReceived');
    var outstanding = parseFloat(document.getElementById('partyOutstandingValue').value || 0);
    var amount = parseFloat(amountInput.value || 0);
    var amountError = document.getElementById('amountError');
    
    // Clear previous error
    amountError.style.display = 'none';
    amountError.textContent = '';
    
    if (amountInput.value !== '' && amount > 0) {
        if (amount > outstanding) {
            amountError.textContent = 'Received amount cannot be greater than outstanding amount (₹' + outstanding.toFixed(2) + ').';
            amountError.style.display = 'block';
            amountInput.style.borderColor = '#dc2626';
        } else {
            amountInput.style.borderColor = '#059669';
        }
    } else {
        amountInput.style.borderColor = '';
    }
    
    validateForm();
}

// Auto-select party if pre-selected via URL
window.addEventListener('DOMContentLoaded', function() {
    var prePartyId = <?= $prePartyId ?>;
    if (prePartyId > 0) {
        var sel = document.getElementById('collPartySelect');
        sel.value = prePartyId;
        updatePartyOutstanding();
    }
});

function validateForm() {
    var partySelect = document.getElementById('collPartySelect');
    var amountInput = document.getElementById('amountReceived');
    var paymentMethod = document.getElementById('paymentMethod');
    var submitBtn = document.getElementById('submitBtn');
    
    var partyId = parseInt(partySelect.value || 0);
    var outstanding = parseFloat(document.getElementById('partyOutstandingValue').value || 0);
    var amount = parseFloat(amountInput.value || 0);
    var method = paymentMethod.value;
    
    var isValid = true;
    
    // Check all conditions
    if (partyId <= 0) isValid = false;
    if (outstanding <= 0) isValid = false;
    if (amount <= 0 || amount > outstanding) isValid = false;
    if (!method) isValid = false;
    
    // Enable/disable submit button
    if (isValid) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
    } else {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
    }
}

function togglePaymentFields() {
    var method = document.getElementById('paymentMethod').value;
    document.getElementById('chequeFields').style.display = method === 'cheque' ? 'block' : 'none';
    document.getElementById('onlineFields').style.display = method === 'online_transfer' ? 'block' : 'none';
    document.getElementById('chequeNumber').required = (method === 'cheque');
    document.getElementById('onlineAccount').required = (method === 'online_transfer');
    validateForm();
}

// Initialize form validation on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($prePartyId): ?>
    updatePartyOutstanding();
    <?php endif; ?>
    
    // Add event listeners
    document.getElementById('collPartySelect').addEventListener('change', updatePartyOutstanding);
    document.getElementById('paymentMethod').addEventListener('change', validateForm);
    
    // Prevent form submission if validation fails
    document.getElementById('collectionForm').addEventListener('submit', function(e) {
        var outstanding = parseFloat(document.getElementById('partyOutstandingValue').value || 0);
        var amount = parseFloat(document.getElementById('amountReceived').value || 0);
        
        if (outstanding <= 0) {
            e.preventDefault();
            alert('No payment can be collected because outstanding amount is zero.');
            return false;
        }
        
        if (amount <= 0) {
            e.preventDefault();
            alert('Amount must be greater than zero.');
            return false;
        }
        
        if (amount > outstanding) {
            e.preventDefault();
            alert('Received amount (₹' + amount.toFixed(2) + ') cannot be greater than outstanding amount (₹' + outstanding.toFixed(2) + ').');
            return false;
        }
    });
});

// Collection filter logic
var collectionData = <?= json_encode(array_map(function($c) {
    return ['amount' => (float)$c['amount'], 'status' => $c['status'], 'date' => $c['created_at'], 'confirmed_at' => $c['confirmed_at'] ?? null];
}, $collections)) ?>;

document.querySelectorAll('.coll-filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.coll-filter-btn').forEach(function(b) { b.classList.remove('active'); b.style.background=''; b.style.color=''; });
        this.classList.add('active');
        this.style.background='var(--sp-primary)'; this.style.color='#fff';
        var filter = this.dataset.filter;
        document.getElementById('customDateRange').style.display = filter === 'custom' ? 'block' : 'none';
        if (filter !== 'custom') calculateFiltered(filter);
    });
});
// Set initial active style
document.querySelector('.coll-filter-btn.active').style.background='var(--sp-primary)';
document.querySelector('.coll-filter-btn.active').style.color='#fff';

function calculateFiltered(filter) {
    var now = new Date();
    var total = 0, label = '';
    var fromDate, toDate;
    if (filter === 'today') {
        fromDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        toDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
        label = 'Collected Today';
    } else if (filter === 'yesterday') {
        fromDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
        toDate = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 23, 59, 59);
        label = 'Collected Yesterday';
    } else if (filter === 'week') {
        var day = now.getDay(); var diff = now.getDate() - day + (day === 0 ? -6 : 1);
        fromDate = new Date(now.getFullYear(), now.getMonth(), diff);
        toDate = now;
        label = 'Collected This Week';
    } else if (filter === 'month') {
        fromDate = new Date(now.getFullYear(), now.getMonth(), 1);
        toDate = now;
        label = 'Collected This Month';
    } else if (filter === 'last_month') {
        fromDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        toDate = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59);
        label = 'Collected Last Month';
    }
    collectionData.forEach(function(c) {
        var d = new Date(c.date);
        if (d >= fromDate && d <= toDate) total += c.amount;
    });
    document.getElementById('filteredAmount').textContent = '₹' + total.toLocaleString('en-IN');
    document.getElementById('filteredLabel').textContent = label;
}

function applyCustomFilter() {
    var from = document.getElementById('collDateFrom').value;
    var to = document.getElementById('collDateTo').value;
    if (!from || !to) { alert('Please select both dates.'); return; }
    var fromDate = new Date(from); var toDate = new Date(to + 'T23:59:59');
    var total = 0;
    collectionData.forEach(function(c) {
        var d = new Date(c.date);
        if (d >= fromDate && d <= toDate) total += c.amount;
    });
    document.getElementById('filteredAmount').textContent = '₹' + total.toLocaleString('en-IN');
    document.getElementById('filteredLabel').textContent = 'Collected ' + from + ' to ' + to;
}

function sharePaymentWhatsApp(colId, partyName, partyId, receiptNo, amount, payDate, dueAmount, partyPhone, updatedOutstandingParam) {
    // Use provided updated outstanding, or calculate from previous outstanding
    var amountNum = parseFloat(amount.toString().replace(/,/g, ''));
    var dueAmountNum = parseFloat(dueAmount.toString().replace(/,/g, ''));
    var updatedOutstanding;
    if (typeof updatedOutstandingParam !== 'undefined' && updatedOutstandingParam !== null && updatedOutstandingParam !== '') {
        updatedOutstanding = parseFloat(updatedOutstandingParam.toString().replace(/,/g, ''));
    } else {
        updatedOutstanding = Math.max(0, dueAmountNum - amountNum);
    }
    
    var msg = '💵 *PAYMENT RECEIVED*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n\n'
        + 'Dear *' + partyName + '*,\n\n'
        + 'Thank you! We have received your payment successfully.\n\n'
        + '📊 *Payment Details:*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + '🆔 Party ID: ' + partyId + '\n'
        + '🧯 Receipt No: ' + receiptNo + '\n'
        + '📅 Payment Date: ' + payDate + '\n'
        + '💰 Amount Received: *₹' + amount + '*\n\n'
        + '📈 *Outstanding Summary:*\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + '🔴 Previous Outstanding: ₹' + dueAmount + '\n'
        + '➖ Payment Received: ₹' + amount + '\n'
        + '━━━━━━━━━━━━━━━━━━━━\n'
        + '🟢 *Updated Outstanding: ₹' + updatedOutstanding.toLocaleString('en-IN') + '*\n\n'
        + (updatedOutstanding > 0 
            ? '⚠️ Payment has been adjusted against your oldest pending bills.\n\n'
            : '✅ All dues cleared! Thank you for your prompt payment.\n\n')
        + 'Thank you for choosing *Gilaf*.\n'
        + 'Your satisfaction is our priority! 🙏';

    var url;
    if (partyPhone) {
        var phone = partyPhone.replace(/[^0-9]/g, '');
        if (phone.length === 10) phone = '91' + phone;
        url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg);
    } else {
        url = 'https://wa.me/?text=' + encodeURIComponent(msg);
    }
    window.open(url, '_blank');

    if (colId) {
        var btn = document.getElementById('colShareBtn' + colId);
        if (btn) { btn.style.background = '#6b7280'; }
    }
}

function confirmDeleteCollection(colId, collectionNumber, amount, partyName, status) {
    var warningMsg = '⚠️ DELETE COLLECTION?\n\n';
    warningMsg += 'Collection: ' + collectionNumber + '\n';
    warningMsg += 'Amount: ₹' + amount + '\n';
    warningMsg += 'Party: ' + partyName + '\n';
    warningMsg += 'Status: ' + status.toUpperCase() + '\n\n';
    
    if (status === 'confirmed') {
        warningMsg += '🔴 WARNING: This collection is CONFIRMED!\n';
        warningMsg += '• Payment allocations will be REVERSED\n';
        warningMsg += '• Outstanding will be RECALCULATED\n';
        warningMsg += '• All payment history will be REMOVED\n\n';
    }
    
    warningMsg += '❌ This action CANNOT be undone!\n\n';
    warningMsg += 'Are you sure you want to delete this collection?';
    
    if (confirm(warningMsg)) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        var input1 = document.createElement('input');
        input1.type = 'hidden';
        input1.name = 'delete_collection';
        input1.value = '1';
        form.appendChild(input1);
        
        var input2 = document.createElement('input');
        input2.type = 'hidden';
        input2.name = 'collection_id';
        input2.value = colId;
        form.appendChild(input2);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
