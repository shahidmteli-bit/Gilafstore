<?php
/**
 * Admin - Sales Portal Pricing (Enhanced)
 * Manages Wholesale, Distributor, Franchise, and Retail prices
 * Includes GST columns and Offline MRP
 * Prices sync with sales app based on party profile_type
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales Pricing — Admin';
$adminPage = 'sales_pricing';

$db = get_db_connection();

// Handle bulk price update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pricing'])) {
    $updates = $_POST['prices'] ?? [];
    $mrpUpdates = $_POST['offline_mrp'] ?? [];
    $count = 0;
    
    try {
        $db->beginTransaction();
        
        foreach ($updates as $weightId => $prices) {
            $wId = (int)$weightId;
            
            // Extract pricing data with proper decimal handling
            $wholesale = isset($prices['wholesale']) ? number_format((float)$prices['wholesale'], 2, '.', '') : null;
            $wholesaleGst = isset($prices['wholesale_gst']) ? number_format((float)$prices['wholesale_gst'], 2, '.', '') : null;
            
            $distributor = isset($prices['distributor']) ? number_format((float)$prices['distributor'], 2, '.', '') : null;
            $distributorGst = isset($prices['distributor_gst']) ? number_format((float)$prices['distributor_gst'], 2, '.', '') : null;
            
            $franchise = isset($prices['franchise']) ? number_format((float)$prices['franchise'], 2, '.', '') : null;
            $franchiseGst = isset($prices['franchise_gst']) ? number_format((float)$prices['franchise_gst'], 2, '.', '') : null;
            
            $retail = isset($prices['retail']) ? number_format((float)$prices['retail'], 2, '.', '') : null;
            $retailGst = isset($prices['retail_gst']) ? number_format((float)$prices['retail_gst'], 2, '.', '') : null;
            
            // Offline MRP (synced across all pricing tabs for same product)
            $offlineMrp = isset($mrpUpdates[$wId]) ? number_format((float)$mrpUpdates[$wId], 2, '.', '') : null;
            
            // Build dynamic UPDATE query to only update provided fields
            $updateFields = [];
            $updateParams = [];
            
            if ($wholesale !== null) {
                $updateFields[] = 'wholesale_price = ?';
                $updateParams[] = $wholesale;
            }
            if ($wholesaleGst !== null) {
                $updateFields[] = 'wholesale_gst = ?';
                $updateParams[] = $wholesaleGst;
            }
            if ($distributor !== null) {
                $updateFields[] = 'distributor_price = ?';
                $updateParams[] = $distributor;
            }
            if ($distributorGst !== null) {
                $updateFields[] = 'distributor_gst = ?';
                $updateParams[] = $distributorGst;
            }
            if ($franchise !== null) {
                $updateFields[] = 'franchise_price = ?';
                $updateParams[] = $franchise;
            }
            if ($franchiseGst !== null) {
                $updateFields[] = 'franchise_gst = ?';
                $updateParams[] = $franchiseGst;
            }
            if ($retail !== null) {
                $updateFields[] = 'retail_price = ?';
                $updateParams[] = $retail;
            }
            if ($retailGst !== null) {
                $updateFields[] = 'retail_gst = ?';
                $updateParams[] = $retailGst;
            }
            if ($offlineMrp !== null) {
                $updateFields[] = 'offline_mrp = ?';
                $updateParams[] = $offlineMrp;
            }
            
            if (!empty($updateFields)) {
                $updateParams[] = $wId;
                $sql = "UPDATE product_weights SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($updateParams);
                $count++;
            }
        }
        
        $db->commit();
        $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Updated pricing for {$count} product weights."];
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['admin_flash'] = ['type' => 'danger', 'message' => 'Error updating prices: ' . $e->getMessage()];
    }
    
    header('Location: ' . base_url('admin/sales_pricing_enhanced.php') . '?' . http_build_query($_GET));
    exit;
}

// Filters
$searchQuery = trim($_GET['search'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);
$activeTab = trim($_GET['tab'] ?? 'franchise');
if (!in_array($activeTab, ['franchise', 'distributor', 'wholesale', 'retail'])) $activeTab = 'franchise';

// Fetch categories
$categories = db_fetch_all('SELECT id, name FROM categories ORDER BY name ASC');

// Fetch products with weights
$sql = "SELECT p.id as product_id, p.name as product_name, p.image,
        pw.id as weight_id, pw.display_weight, pw.price as website_price,
        pw.wholesale_price, pw.wholesale_gst,
        pw.distributor_price, pw.distributor_gst,
        pw.franchise_price, pw.franchise_gst,
        pw.retail_price, pw.retail_gst,
        pw.offline_mrp
        FROM products p
        INNER JOIN product_weights pw ON pw.product_id = p.id
        WHERE 1=1";
$params = [];

if ($searchQuery) {
    $sql .= ' AND p.name LIKE ?';
    $params[] = '%' . $searchQuery . '%';
}
if ($categoryFilter) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $categoryFilter;
}
$sql .= ' ORDER BY p.name ASC, pw.sort_order ASC, pw.weight_value ASC';

$rows = db_fetch_all($sql, $params);

// Group by product
$products = [];
foreach ($rows as $row) {
    $pid = $row['product_id'];
    if (!isset($products[$pid])) {
        $products[$pid] = [
            'id' => $pid,
            'name' => $row['product_name'],
            'image' => $row['image'],
            'weights' => [],
        ];
    }
    $products[$pid]['weights'][] = $row;
}

// Chain order: Company → Franchise → Distributor → Wholesale → Retail
$tabConfig = [
    'franchise'   => ['label' => 'Franchise',   'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'fa-handshake',    'col' => 'franchise_price',   'gst_col' => 'franchise_gst'],
    'distributor' => ['label' => 'Distributor', 'color' => '#d97706', 'bg' => '#fef3c7', 'icon' => 'fa-truck',         'col' => 'distributor_price', 'gst_col' => 'distributor_gst'],
    'wholesale'   => ['label' => 'Wholesale',   'color' => '#059669', 'bg' => '#d1fae5', 'icon' => 'fa-store',         'col' => 'wholesale_price',   'gst_col' => 'wholesale_gst'],
    'retail'      => ['label' => 'Retail',      'color' => '#0891b2', 'bg' => '#cffafe', 'icon' => 'fa-shopping-cart', 'col' => 'retail_price',      'gst_col' => 'retail_gst'],
];
$currentTab = $tabConfig[$activeTab];

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-1"><i class="fas fa-tags me-2"></i>Sales Pricing</h4>
        <p class="text-muted mb-0">Pricing chain: <strong>Company → Franchise → Distributor → Wholesale → Retail</strong>. Offline MRP is separate from website pricing.</p>
      </div>
    </div>

    <!-- Profile Tabs -->
    <ul class="nav nav-pills mb-4" style="gap:8px;">
      <?php foreach ($tabConfig as $tabKey => $tab): ?>
        <li class="nav-item">
          <a class="nav-link <?= $activeTab === $tabKey ? 'active' : '' ?>"
             href="?tab=<?= $tabKey ?>&search=<?= urlencode($searchQuery) ?>&category=<?= $categoryFilter ?>"
             style="<?= $activeTab === $tabKey ? 'background:' . $tab['color'] . ';color:#fff;' : 'color:' . $tab['color'] . ';border:1px solid ' . $tab['color'] . ';' ?>">
            <i class="fas <?= $tab['icon'] ?> me-1"></i> <?= $tab['label'] ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body py-3">
        <form method="GET" class="row g-3 align-items-end">
          <input type="hidden" name="tab" value="<?= $activeTab ?>">
          <div class="col-md-5">
            <label class="form-label small fw-semibold">Search Product</label>
            <input type="text" name="search" class="form-control" placeholder="Search by product name..." value="<?= htmlspecialchars($searchQuery) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Category</label>
            <select name="category" class="form-select">
              <option value="0">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Filter</button>
          </div>
        </form>
      </div>
    </div>

    <?php if (!empty($_SESSION['admin_flash'])): ?>
      <div class="alert alert-<?= $_SESSION['admin_flash']['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
        <i class="fas fa-<?= $_SESSION['admin_flash']['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-1"></i> 
        <?= htmlspecialchars($_SESSION['admin_flash']['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['admin_flash']); ?>
    <?php endif; ?>

    <?php if (empty($products)): ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
          <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
          <h5>No products found</h5>
          <p class="text-muted">Try a different search or category filter.</p>
        </div>
      </div>
    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="save_pricing" value="1">

        <div class="card border-0 shadow-sm">
          <div class="card-header py-3" style="background:<?= $currentTab['bg'] ?>;">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="mb-0" style="color:<?= $currentTab['color'] ?>;">
                <i class="fas <?= $currentTab['icon'] ?> me-2"></i><?= $currentTab['label'] ?> Pricing
              </h5>
              <span class="badge" style="background:<?= $currentTab['color'] ?>;color:#fff;font-size:13px;">
                <?= count($products) ?> products · <?= count($rows) ?> weights
              </span>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:50px;"></th>
                    <th>Product</th>
                    <th>Weight</th>
                    <th class="text-center">Offline MRP (₹)</th>
                    <th class="text-center" style="background:<?= $currentTab['bg'] ?>;min-width:140px;">
                      <span style="color:<?= $currentTab['color'] ?>;font-weight:700;">Base Price (₹)</span>
                      <div class="small" style="color:<?= $currentTab['color'] ?>80;font-weight:400;">Excl. GST</div>
                    </th>
                    <th class="text-center" style="background:<?= $currentTab['bg'] ?>40;min-width:100px;">
                      <span style="color:<?= $currentTab['color'] ?>;font-weight:700;">GST (%)</span>
                    </th>
                    <th class="text-center" style="background:<?= $currentTab['bg'] ?>20;min-width:140px;">
                      <span style="color:<?= $currentTab['color'] ?>;font-weight:700;"><?= $currentTab['label'] ?> Price (₹)</span>
                      <div class="small" style="color:<?= $currentTab['color'] ?>80;font-weight:400;">Base + GST</div>
                    </th>
                    <th class="text-center">Margin</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($products as $product): ?>
                    <?php foreach ($product['weights'] as $i => $w): ?>
                      <tr>
                        <td class="text-center" style="width:60px;">
                          <?php if ($product['image']): ?>
                            <div style="width:44px;height:44px;border-radius:8px;border:1px solid #dee2e6;background:#f8f9fa url('<?= base_url('assets/images/products/' . $product['image']) ?>') center/contain no-repeat;display:inline-block;"></div>
                          <?php else: ?>
                            <div style="width:44px;height:44px;background:#f3f4f6;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-image text-muted"></i></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <strong><?= htmlspecialchars($product['name']) ?></strong>
                          <div class="text-muted small">ID: <?= $product['id'] ?></div>
                        </td>
                        <td>
                          <span class="badge bg-info"><?= htmlspecialchars($w['display_weight']) ?></span>
                        </td>
                        <td class="text-center">
                          <input type="number"
                                 name="offline_mrp[<?= $w['weight_id'] ?>]"
                                 class="form-control form-control-sm text-center fw-bold"
                                 value="<?= number_format((float)$w['offline_mrp'], 2, '.', '') ?>"
                                 step="0.01" min="0"
                                 style="max-width:110px;margin:0 auto;border-color:#6b728050;"
                                 placeholder="MRP">
                        </td>
                        <td class="text-center" style="background:<?= $currentTab['bg'] ?>20;">
                          <input type="number"
                                 name="prices[<?= $w['weight_id'] ?>][<?= $activeTab ?>]"
                                 class="form-control form-control-sm text-center fw-bold base-price-input"
                                 value="<?= number_format((float)$w[$currentTab['col']], 2, '.', '') ?>"
                                 step="0.01" min="0"
                                 style="max-width:120px;margin:0 auto;border-color:<?= $currentTab['color'] ?>50;"
                                 data-weight-id="<?= $w['weight_id'] ?>"
                                 data-mrp="<?= $w['offline_mrp'] ?>"
                                 oninput="calculateTotalPrice(this)">
                        </td>
                        <td class="text-center" style="background:<?= $currentTab['bg'] ?>10;">
                          <input type="number"
                                 name="prices[<?= $w['weight_id'] ?>][<?= $activeTab ?>_gst]"
                                 class="form-control form-control-sm text-center gst-input"
                                 value="<?= number_format((float)$w[$currentTab['gst_col']], 2, '.', '') ?>"
                                 step="0.01" min="0" max="100"
                                 style="max-width:90px;margin:0 auto;border-color:<?= $currentTab['color'] ?>30;"
                                 data-weight-id="<?= $w['weight_id'] ?>"
                                 placeholder="GST %"
                                 oninput="calculateTotalPrice(this)">
                        </td>
                        <td class="text-center" style="background:<?= $currentTab['bg'] ?>05;">
                          <?php
                          $basePrice = (float)$w[$currentTab['col']];
                          $gstPercent = (float)$w[$currentTab['gst_col']];
                          $gstAmount = $basePrice * ($gstPercent / 100);
                          $totalPrice = $basePrice + $gstAmount;
                          ?>
                          <div class="fw-bold total-price-display" style="color:<?= $currentTab['color'] ?>;font-size:15px;padding:6px 0;"
                               data-weight-id="<?= $w['weight_id'] ?>">
                            ₹<?= number_format($totalPrice, 2) ?>
                          </div>
                          <div class="small text-muted" style="font-size:11px;">
                            GST: ₹<span class="gst-amount-display" data-weight-id="<?= $w['weight_id'] ?>"><?= number_format($gstAmount, 2) ?></span>
                          </div>
                        </td>
                        <td class="text-center">
                          <?php
                          $profilePrice = (float)$w[$currentTab['col']];
                          $mrp = (float)$w['offline_mrp'];
                          $margin = $mrp > 0 && $profilePrice > 0 ? round((($mrp - $profilePrice) / $mrp) * 100, 1) : 0;
                          $marginColor = $margin > 0 ? '#059669' : ($margin < 0 ? '#dc2626' : '#6b7280');
                          ?>
                          <span class="margin-display fw-semibold" style="color:<?= $marginColor ?>;">
                            <?= $margin > 0 ? '-' . $margin . '%' : ($margin < 0 ? '+' . abs($margin) . '%' : '0%') ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer py-3">
            <div class="row align-items-center">
              <div class="col-md-8">
                <div class="text-muted small">
                  <i class="fas fa-info-circle me-1"></i> 
                  <strong>Note:</strong> Offline MRP is for sales portal only (not shown on website). 
                  Changes here only affect <strong><?= $currentTab['label'] ?></strong> prices. 
                  All pricing tiers are stored independently.
                </div>
              </div>
              <div class="col-md-4 text-end">
                <button type="submit" class="btn btn-lg rounded-pill px-4" style="background:<?= $currentTab['color'] ?>;color:#fff;">
                  <i class="fas fa-save me-2"></i>Save <?= $currentTab['label'] ?> Prices
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<script>
function calculateTotalPrice(input) {
    var row = input.closest('tr');
    var weightId = input.dataset.weightId;
    
    // Get base price and GST inputs
    var basePriceInput = row.querySelector('.base-price-input');
    var gstInput = row.querySelector('.gst-input');
    
    var basePrice = parseFloat(basePriceInput.value) || 0;
    var gstPercent = parseFloat(gstInput.value) || 0;
    
    // Calculate GST amount and total price
    var gstAmount = basePrice * (gstPercent / 100);
    var totalPrice = basePrice + gstAmount;
    
    // Update total price display
    var totalPriceDisplay = row.querySelector('.total-price-display[data-weight-id="' + weightId + '"]');
    var gstAmountDisplay = row.querySelector('.gst-amount-display[data-weight-id="' + weightId + '"]');
    
    if (totalPriceDisplay) {
        totalPriceDisplay.textContent = '₹' + totalPrice.toFixed(2);
    }
    if (gstAmountDisplay) {
        gstAmountDisplay.textContent = gstAmount.toFixed(2);
    }
    
    // Update margin calculation (based on total price vs MRP)
    updateMargin(basePriceInput, totalPrice);
}

function updateMargin(input, totalPrice) {
    var mrp = parseFloat(input.dataset.mrp) || 0;
    var price = totalPrice || parseFloat(input.value) || 0;
    var marginEl = input.closest('tr').querySelector('.margin-display');
    
    if (mrp > 0 && price > 0) {
        var margin = ((mrp - price) / mrp * 100).toFixed(1);
        if (margin > 0) {
            marginEl.textContent = '-' + margin + '%';
            marginEl.style.color = '#059669';
        } else if (margin < 0) {
            marginEl.textContent = '+' + Math.abs(margin) + '%';
            marginEl.style.color = '#dc2626';
        } else {
            marginEl.textContent = '0%';
            marginEl.style.color = '#6b7280';
        }
    } else {
        marginEl.textContent = '0%';
        marginEl.style.color = '#6b7280';
    }
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
