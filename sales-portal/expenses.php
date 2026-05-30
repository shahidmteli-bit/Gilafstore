<?php
/**
 * Sales Portal - Travel & Field Expenses
 * Salesperson can add expenses, view history, check status
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();
require_once __DIR__ . '/includes/expense_setup.php';

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Travel & Field Expenses';
$currentPage = 'expenses';

// Fetch active expense categories
$categories = [];
try {
    $categories = db_fetch_all('SELECT * FROM sales_expense_categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC');
} catch (PDOException $e) { /* table may not exist — run migration */ }

// Fetch expense settings
function getExpSetting($key, $default = '') {
    try {
        $row = db_fetch('SELECT setting_value FROM sales_expense_settings WHERE setting_key = ?', [$key]);
        return $row ? $row['setting_value'] : $default;
    } catch (PDOException $e) { return $default; }
}

$notesMandatory = getExpSetting('notes_mandatory', '1') === '1';
$attachAbove = (float)getExpSetting('attachment_mandatory_above', '500');

// Fetch parties for optional linking
$parties = [];
try {
    $parties = db_fetch_all('SELECT id, shop_name, party_code FROM sales_parties WHERE created_by = ? AND is_active = 1 ORDER BY shop_name ASC', [$execId]);
} catch (PDOException $e) {}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_expense'])) {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0);
    $district = trim($_POST['district'] ?? $exec['district']);
    $partyId = (int)($_POST['party_id'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');
    $lat = $_POST['latitude'] ?? null;
    $lng = $_POST['longitude'] ?? null;

    $errors = [];
    if ($categoryId <= 0) $errors[] = 'Select an expense category.';
    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
    if (empty($expenseDate)) $errors[] = 'Date is required.';
    if ($notesMandatory && empty($notes)) $errors[] = 'Notes/remarks are required.';

    // Validate category exists
    $cat = null;
    if ($categoryId > 0) {
        try {
            $cat = db_fetch('SELECT * FROM sales_expense_categories WHERE id = ? AND is_active = 1', [$categoryId]);
        } catch (PDOException $e) {}
        if (!$cat) $errors[] = 'Invalid expense category.';
    }

    // Check daily limit per category
    if ($cat && $cat['max_per_day'] > 0) {
        try {
            $dayTotal = db_fetch('SELECT COALESCE(SUM(amount),0) as total FROM sales_expenses WHERE executive_id = ? AND category_id = ? AND expense_date = ?', [$execId, $categoryId, $expenseDate]);
            if (($dayTotal['total'] + $amount) > $cat['max_per_day']) {
                $errors[] = 'Daily limit for ' . $cat['name'] . ' is ₹' . number_format($cat['max_per_day'], 0) . '. Already spent: ₹' . number_format($dayTotal['total'], 0) . '.';
            }
        } catch (PDOException $e) {}
    }

    // Check per-category monthly limit
    if ($cat && $cat['max_per_month'] > 0) {
        try {
            $monthTotal = db_fetch('SELECT COALESCE(SUM(amount),0) as total FROM sales_expenses WHERE executive_id = ? AND category_id = ? AND YEAR(expense_date) = YEAR(?) AND MONTH(expense_date) = MONTH(?)', [$execId, $categoryId, $expenseDate, $expenseDate]);
            if (($monthTotal['total'] + $amount) > $cat['max_per_month']) {
                $errors[] = 'Monthly limit for ' . $cat['name'] . ' is ₹' . number_format($cat['max_per_month'], 0) . '. Already spent: ₹' . number_format($monthTotal['total'], 0) . '.';
            }
        } catch (PDOException $e) {}
    }

    // Handle attachment upload
    $attachmentPath = null;
    if (!empty($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/expense_receipts/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Receipt must be an image (JPG/PNG/GIF/WebP) or PDF.';
        } elseif ($_FILES['receipt']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Receipt file must be under 5 MB.';
        } else {
            $filename = 'exp_' . $execId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadDir . $filename)) {
                $attachmentPath = 'uploads/expense_receipts/' . $filename;
            } else {
                $errors[] = 'Failed to upload receipt. Try again.';
            }
        }
    }

    // Check if attachment is mandatory for this amount
    if ($attachAbove > 0 && $amount >= $attachAbove && !$attachmentPath) {
        $errors[] = 'Receipt/bill upload is required for expenses of ₹' . number_format($attachAbove, 0) . ' or more.';
    }

    if (!empty($errors)) {
        $_SESSION['sp_flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
    } else {
        try {
            db_query('INSERT INTO sales_expenses (executive_id, category_id, expense_date, amount, district, party_id, notes, attachment, latitude, longitude) VALUES (?,?,?,?,?,?,?,?,?,?)', [
                $execId, $categoryId, $expenseDate, $amount, $district, $partyId, $notes, $attachmentPath,
                $lat ? (float)$lat : null, $lng ? (float)$lng : null
            ]);
            $_SESSION['sp_flash'] = ['type' => 'success', 'message' => '₹' . number_format($amount, 0) . ' expense submitted — pending approval.'];
            header('Location: ' . sales_base_url('expenses.php'));
            exit;
        } catch (PDOException $e) {
            $_SESSION['sp_flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    header('Location: ' . sales_base_url('expenses.php'));
    exit;
}

// Fetch expense history
$expenses = [];
try {
    $expenses = db_fetch_all(
        'SELECT e.*, c.name as category_name, c.icon as category_icon, p.shop_name 
         FROM sales_expenses e 
         JOIN sales_expense_categories c ON e.category_id = c.id 
         LEFT JOIN sales_parties p ON e.party_id = p.id 
         WHERE e.executive_id = ? 
         ORDER BY e.expense_date DESC, e.created_at DESC 
         LIMIT 100', [$execId]
    );
} catch (PDOException $e) { /* table may not exist */ }

// Monthly summary
$monthSummary = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
try {
    $ms = db_fetch('SELECT 
        COALESCE(SUM(amount),0) as total,
        COALESCE(SUM(CASE WHEN status="approved" THEN amount ELSE 0 END),0) as approved,
        COALESCE(SUM(CASE WHEN status="pending" THEN amount ELSE 0 END),0) as pending,
        COALESCE(SUM(CASE WHEN status="rejected" THEN amount ELSE 0 END),0) as rejected
        FROM sales_expenses WHERE executive_id = ? AND YEAR(expense_date) = YEAR(NOW()) AND MONTH(expense_date) = MONTH(NOW())', [$execId]);
    if ($ms) $monthSummary = $ms;
} catch (PDOException $e) {}

// Category-wise this month
$catSummary = [];
try {
    $catSummary = db_fetch_all(
        'SELECT c.name, c.icon, COALESCE(SUM(e.amount),0) as total 
         FROM sales_expenses e 
         JOIN sales_expense_categories c ON e.category_id = c.id 
         WHERE e.executive_id = ? AND YEAR(e.expense_date) = YEAR(NOW()) AND MONTH(e.expense_date) = MONTH(NOW()) 
         GROUP BY c.id ORDER BY total DESC', [$execId]
    );
} catch (PDOException $e) {}

include __DIR__ . '/includes/header.php';
?>

<!-- Monthly Summary Cards -->
<div class="sp-expense-summary">
    <div class="sp-expense-sum-item">
        <div class="sp-expense-sum-val">₹<?= number_format((float)$monthSummary['total'], 0) ?></div>
        <div class="sp-expense-sum-lbl">This Month</div>
    </div>
    <div class="sp-expense-sum-item sp-color-green">
        <div class="sp-expense-sum-val">₹<?= number_format((float)$monthSummary['approved'], 0) ?></div>
        <div class="sp-expense-sum-lbl">Approved</div>
    </div>
    <div class="sp-expense-sum-item" style="color:#d97706;">
        <div class="sp-expense-sum-val">₹<?= number_format((float)$monthSummary['pending'], 0) ?></div>
        <div class="sp-expense-sum-lbl">Pending</div>
    </div>
    <div class="sp-expense-sum-item sp-color-red">
        <div class="sp-expense-sum-val">₹<?= number_format((float)$monthSummary['rejected'], 0) ?></div>
        <div class="sp-expense-sum-lbl">Rejected</div>
    </div>
</div>

<?php if (!empty($catSummary)): ?>
<div class="sp-card sp-mb-16">
    <div class="sp-card-header"><h3><i class="fas fa-chart-pie"></i> Category Breakdown (This Month)</h3></div>
    <div style="padding:4px 0;">
        <?php foreach ($catSummary as $cs): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 4px;border-bottom:1px solid #f3f4f6;">
            <i class="<?= htmlspecialchars($cs['icon']) ?>" style="width:24px;text-align:center;color:#6b7280;"></i>
            <span style="flex:1;font-size:14px;"><?= htmlspecialchars($cs['name']) ?></span>
            <span style="font-weight:700;font-size:14px;">₹<?= number_format((float)$cs['total'], 0) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add Expense Form -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-plus-circle"></i> Add Expense</h3>
    </div>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="submit_expense" value="1">
        <input type="hidden" name="latitude" id="expLat" value="">
        <input type="hidden" name="longitude" id="expLng" value="">

        <div class="sp-form-group">
            <label>Expense Category *</label>
            <select name="category_id" class="sp-select" required id="expCategorySelect">
                <option value="">Select category</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" data-icon="<?= htmlspecialchars($c['icon']) ?>" 
                    data-max-day="<?= $c['max_per_day'] ?? '' ?>" data-max-month="<?= $c['max_per_month'] ?? '' ?>"
                    data-req-attach="<?= $c['require_attachment'] ?>" data-req-notes="<?= $c['require_notes'] ?>">
                    <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div id="catLimitInfo" style="font-size:11px;color:#6b7280;margin-top:4px;display:none;"></div>
        </div>

        <div style="display:flex;gap:8px;">
            <div class="sp-form-group" style="flex:1;">
                <label>Date *</label>
                <input type="date" name="expense_date" class="sp-input" value="<?= date('Y-m-d') ?>" required max="<?= date('Y-m-d') ?>">
            </div>
            <div class="sp-form-group" style="flex:1;">
                <label>Amount (₹) *</label>
                <input type="number" name="amount" class="sp-input" placeholder="0" min="1" step="1" required id="expAmount">
            </div>
        </div>

        <div class="sp-form-group">
            <label>District / Area</label>
            <input type="text" name="district" class="sp-input" value="<?= htmlspecialchars($exec['district']) ?>" placeholder="Auto-filled from your profile">
        </div>

        <div class="sp-form-group">
            <label>Related Party (optional)</label>
            <select name="party_id" class="sp-select">
                <option value="">— None —</option>
                <?php foreach ($parties as $p): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['shop_name']) ?> (<?= $p['party_code'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sp-form-group">
            <label>Notes / Remarks <?= $notesMandatory ? '*' : '' ?></label>
            <textarea name="notes" class="sp-input" rows="2" placeholder="e.g. Fuel for visiting Baramulla market" <?= $notesMandatory ? 'required' : '' ?>></textarea>
        </div>

        <div class="sp-form-group">
            <label>Receipt / Bill <span id="attachReqLabel" style="color:#dc2626;display:none;">*</span></label>
            <input type="file" name="receipt" accept="image/*,.pdf" class="sp-input" id="receiptInput" style="padding:8px;">
            <div style="font-size:11px;color:#6b7280;margin-top:2px;">
                JPG, PNG, or PDF — Max 5 MB
                <?php if ($attachAbove > 0): ?>
                    <span style="color:#dc2626;">| Required for ₹<?= number_format($attachAbove, 0) ?>+</span>
                <?php endif; ?>
            </div>
        </div>

        <button type="submit" class="sp-btn sp-btn-primary sp-btn-block" style="margin-top:8px;">
            <i class="fas fa-paper-plane"></i> Submit Expense
        </button>
    </form>
</div>

<!-- Expense History -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-history"></i> Expense History</h3>
    </div>
    <div style="padding:0;">
        <?php if (empty($expenses)): ?>
            <div style="padding:24px;text-align:center;color:#6b7280;">
                <i class="fas fa-receipt fa-2x" style="margin-bottom:8px;display:block;opacity:0.4;"></i>
                No expenses submitted yet.
            </div>
        <?php else: ?>
            <?php
            $badgeMap = ['pending' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-clock'],
                         'approved' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-check-circle'],
                         'rejected' => ['bg' => '#fce4ec', 'color' => '#991b1b', 'icon' => 'fa-times-circle']];
            foreach ($expenses as $exp):
                $badge = $badgeMap[$exp['status']] ?? $badgeMap['pending'];
            ?>
            <div class="sp-expense-item" onclick="toggleExpDetail(this)">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="sp-expense-icon">
                        <i class="<?= htmlspecialchars($exp['category_icon']) ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($exp['category_name']) ?></div>
                        <div style="font-size:12px;color:#6b7280;">
                            <?= date('d M Y', strtotime($exp['expense_date'])) ?>
                            <?php if ($exp['shop_name']): ?> · <?= htmlspecialchars($exp['shop_name']) ?><?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:700;font-size:15px;">₹<?= number_format((float)$exp['amount'], 0) ?></div>
                        <span class="sp-expense-badge" style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;">
                            <i class="fas <?= $badge['icon'] ?>"></i> <?= ucfirst($exp['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="sp-expense-detail" style="display:none;margin-top:10px;padding-top:10px;border-top:1px solid #f3f4f6;font-size:13px;color:#4b5563;">
                    <?php if ($exp['notes']): ?>
                        <div><i class="fas fa-sticky-note" style="width:18px;color:#9ca3af;"></i> <?= htmlspecialchars($exp['notes']) ?></div>
                    <?php endif; ?>
                    <?php if ($exp['district']): ?>
                        <div><i class="fas fa-map-marker-alt" style="width:18px;color:#9ca3af;"></i> <?= htmlspecialchars($exp['district']) ?></div>
                    <?php endif; ?>
                    <?php if ($exp['attachment']): ?>
                        <div><i class="fas fa-paperclip" style="width:18px;color:#9ca3af;"></i> <a href="<?= sales_base_url($exp['attachment']) ?>" target="_blank" style="color:#2563eb;">View Receipt</a></div>
                    <?php endif; ?>
                    <?php if ($exp['status'] !== 'pending' && $exp['admin_remarks']): ?>
                        <div style="margin-top:6px;padding:6px 8px;background:#f9fafb;border-radius:6px;">
                            <strong>Admin:</strong> <?= htmlspecialchars($exp['admin_remarks']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle expense detail
function toggleExpDetail(el) {
    var detail = el.querySelector('.sp-expense-detail');
    if (detail) detail.style.display = detail.style.display === 'none' ? 'block' : 'none';
}

// Show category limit info
document.getElementById('expCategorySelect').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var info = document.getElementById('catLimitInfo');
    var parts = [];
    if (opt.dataset.maxDay && parseFloat(opt.dataset.maxDay) > 0) parts.push('Daily limit: ₹' + parseFloat(opt.dataset.maxDay).toLocaleString('en-IN'));
    if (opt.dataset.maxMonth && parseFloat(opt.dataset.maxMonth) > 0) parts.push('Monthly limit: ₹' + parseFloat(opt.dataset.maxMonth).toLocaleString('en-IN'));
    if (parts.length > 0) { info.textContent = parts.join(' | '); info.style.display = 'block'; }
    else { info.style.display = 'none'; }
});

// Show attachment required label if amount exceeds threshold
var attachThreshold = <?= (float)$attachAbove ?>;
document.getElementById('expAmount').addEventListener('input', function() {
    var label = document.getElementById('attachReqLabel');
    if (attachThreshold > 0 && parseFloat(this.value) >= attachThreshold) {
        label.style.display = 'inline';
    } else {
        label.style.display = 'none';
    }
});

// Auto-capture GPS location
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
        document.getElementById('expLat').value = pos.coords.latitude;
        document.getElementById('expLng').value = pos.coords.longitude;
    }, function() {}, { enableHighAccuracy: false, timeout: 5000 });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
