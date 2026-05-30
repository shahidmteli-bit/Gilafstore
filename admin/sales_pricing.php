<?php
/**
 * Admin - Sales Portal Pricing
 * Dedicated page for managing Wholesale, Distributor, and Franchise prices
 * These prices sync with the sales app based on party profile_type
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
    $count = 0;
    foreach ($updates as $weightId => $prices) {
        $wId = (int)$weightId;
        $fields = []; $params = [];
        $map = ['franchise'=>'franchise_price','distributor'=>'distributor_price','wholesale'=>'wholesale_price','retail'=>'retail_price'];
        foreach ($map as $key => $col) {
            if (isset($prices[$key])) { $fields[] = "`$col` = ?"; $params[] = (float)$prices[$key]; }
        }
        if ($fields) { $params[] = $wId; $db->prepare('UPDATE product_weights SET '.implode(', ',$fields).' WHERE id = ?')->execute($params); $count++; }
    }
    $_SESSION['admin_flash'] = ['type' => 'success', 'message' => "Updated pricing for {$count} product weights."];
    header('Location: ' . base_url('admin/sales_pricing.php') . '?' . http_build_query($_GET));
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
        pw.id as weight_id, pw.display_weight,
        pw.wholesale_price, pw.distributor_price, pw.franchise_price,
        pw.retail_price, pw.offline_mrp
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
    'franchise'   => ['label' => 'Franchise',   'color' => '#7c3aed', 'bg' => '#ede9fe', 'icon' => 'fa-handshake',    'col' => 'franchise_price'],
    'distributor' => ['label' => 'Distributor', 'color' => '#d97706', 'bg' => '#fef3c7', 'icon' => 'fa-truck',         'col' => 'distributor_price'],
    'wholesale'   => ['label' => 'Wholesale',   'color' => '#059669', 'bg' => '#d1fae5', 'icon' => 'fa-store',         'col' => 'wholesale_price'],
    'retail'      => ['label' => 'Retail',      'color' => '#0891b2', 'bg' => '#cffafe', 'icon' => 'fa-shopping-cart', 'col' => 'retail_price'],
];
$currentTab = $tabConfig[$activeTab];

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-1"><i class="fas fa-tags me-2"></i>Sales Pricing</h4>
        <p class="text-muted mb-0">Pricing chain: <strong>Company → Franchise → Distributor → Wholesale → Retail</strong>. Syncs with the sales app based on party profile.</p>
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
        <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($_SESSION['admin_flash']['message']) ?>
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
                    <th class="text-center" style="background:<?= $currentTab['bg'] ?>;min-width:150px;">
                      <span style="color:<?= $currentTab['color'] ?>;font-weight:700;"><?= $currentTab['label'] ?> Price (₹)</span>
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
                          <span class="fw-semibold text-muted">₹<?= number_format((float)($w['offline_mrp'] ?? 0), 2) ?></span>
                        </td>
                        <td class="text-center" style="background:<?= $currentTab['bg'] ?>20;">
                          <input type="number"
                                 name="prices[<?= $w['weight_id'] ?>][<?= $activeTab ?>]"
                                 class="form-control form-control-sm text-center fw-bold pricing-input"
                                 value="<?= number_format((float)$w[$currentTab['col']], 2, '.', '') ?>"
                                 step="0.01" min="0"
                                 style="max-width:130px;margin:0 auto;border-color:<?= $currentTab['color'] ?>50;"
                                 data-retail="<?= $w['retail_price'] ?>"
                                 onchange="updateMargin(this)">
                        </td>
                        <td class="text-center">
                          <?php
                          $profilePrice = (float)$w[$currentTab['col']];
                          $mrp = (float)($w['offline_mrp'] ?? 0);
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
          <div class="card-footer py-3 d-flex justify-content-between align-items-center">
            <div class="text-muted small">
              <i class="fas fa-info-circle me-1"></i> Changes here only affect <strong><?= $currentTab['label'] ?></strong> prices. Switch tabs to edit other profile prices.
            </div>
            <button type="submit" class="btn btn-lg rounded-pill px-4" style="background:<?= $currentTab['color'] ?>;color:#fff;">
              <i class="fas fa-save me-2"></i>Save <?= $currentTab['label'] ?> Prices
            </button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<script>
function updateMargin(input) {
    var retail = parseFloat(input.dataset.retail) || 0;
    var price = parseFloat(input.value) || 0;
    var marginEl = input.closest('tr').querySelector('.margin-display');
    if (retail > 0 && price > 0) {
        var margin = ((retail - price) / retail * 100).toFixed(1);
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
