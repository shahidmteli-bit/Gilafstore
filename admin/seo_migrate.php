<?php
/**
 * SEO Migration Tool — Gilaf Store
 * 
 * Run this AFTER executing database_seo_migration.sql in phpMyAdmin.
 * This script:
 * 1. Generates slugs for all existing products
 * 2. Generates slugs for all existing categories
 * 3. Creates 301 redirects from old ?id= URLs to new slug URLs
 * 4. Shows migration status report
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'SEO Migration — Admin';
$adminPage = 'seo';

$migrationResults = [];
$errors = [];

// Check if migration has been run
$status = seo_migration_status();

// Handle migration action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'migrate_slugs') {
        try {
            $productCount = migrate_product_slugs();
            $categoryCount = migrate_category_slugs();
            $migrationResults[] = "Generated slugs for {$productCount} products and {$categoryCount} categories.";
        } catch (Exception $e) {
            $errors[] = "Migration error: " . $e->getMessage();
        }
        
        // Refresh status
        $status = seo_migration_status();
    }
    
    if ($action === 'clear_sitemap_cache') {
        $cacheFile = __DIR__ . '/../cache/product-sitemap-cache.xml';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $migrationResults[] = "Sitemap cache cleared. Next crawl will generate fresh sitemap with slug URLs.";
        } else {
            $migrationResults[] = "No sitemap cache to clear.";
        }
    }
}

// Get current stats
$totalProducts = 0;
$productsWithSlugs = 0;
$productsMissingSlugs = 0;
$totalCategories = 0;
$categoriesWithSlugs = 0;
$totalRedirects = 0;

try {
    $totalProducts = (int)(db_fetch("SELECT COUNT(*) as cnt FROM products")['cnt'] ?? 0);
    
    try {
        $productsWithSlugs = (int)(db_fetch("SELECT COUNT(*) as cnt FROM products WHERE slug IS NOT NULL AND slug != ''")['cnt'] ?? 0);
    } catch (PDOException $e) {
        // slug column doesn't exist yet
    }
    
    $productsMissingSlugs = $totalProducts - $productsWithSlugs;
    $totalCategories = (int)(db_fetch("SELECT COUNT(*) as cnt FROM categories")['cnt'] ?? 0);
    
    try {
        $categoriesWithSlugs = (int)(db_fetch("SELECT COUNT(*) as cnt FROM categories WHERE slug IS NOT NULL AND slug != ''")['cnt'] ?? 0);
    } catch (PDOException $e) {}
    
    try {
        $totalRedirects = (int)(db_fetch("SELECT COUNT(*) as cnt FROM seo_redirects")['cnt'] ?? 0);
    } catch (PDOException $e) {}
} catch (Exception $e) {
    $errors[] = "Stats error: " . $e->getMessage();
}

// Get sample product URLs for preview
$sampleProducts = [];
try {
    $sampleProducts = db_fetch_all("SELECT id, name, slug FROM products LIMIT 5");
} catch (PDOException $e) {}

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid" style="max-width: 900px;">
    <a href="<?= base_url('admin/index.php'); ?>" class="btn btn-outline-secondary rounded-pill mb-3"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
    
    <div class="card shadow-3 border-0 mb-4">
      <div class="card-header bg-success bg-opacity-10">
        <h4 class="fw-semibold mb-0"><i class="fas fa-search-plus me-2"></i>SEO Migration Tool</h4>
      </div>
      <div class="card-body p-4">
        
        <?php foreach ($migrationResults as $msg): ?>
          <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
        
        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($err); ?></div>
        <?php endforeach; ?>
        
        <!-- Status Dashboard -->
        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <div class="card text-center border-primary">
              <div class="card-body py-3">
                <h3 class="mb-0 text-primary"><?= $totalProducts; ?></h3>
                <small class="text-muted">Total Products</small>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card text-center border-success">
              <div class="card-body py-3">
                <h3 class="mb-0 text-success"><?= $productsWithSlugs; ?></h3>
                <small class="text-muted">With Slugs</small>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card text-center <?= $productsMissingSlugs > 0 ? 'border-warning' : 'border-success'; ?>">
              <div class="card-body py-3">
                <h3 class="mb-0 <?= $productsMissingSlugs > 0 ? 'text-warning' : 'text-success'; ?>"><?= $productsMissingSlugs; ?></h3>
                <small class="text-muted">Missing Slugs</small>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card text-center border-info">
              <div class="card-body py-3">
                <h3 class="mb-0 text-info"><?= $totalRedirects; ?></h3>
                <small class="text-muted">301 Redirects</small>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Migration Steps -->
        <div class="card bg-light border mb-4">
          <div class="card-body">
            <h5 class="fw-semibold mb-3">Setup Instructions</h5>
            <ol class="mb-0">
              <li class="mb-2"><strong>Step 1:</strong> Run <code>database_seo_migration.sql</code> in phpMyAdmin to add SEO columns to the database.</li>
              <li class="mb-2"><strong>Step 2:</strong> Click "Generate Slugs" below to auto-create SEO-friendly URLs for all products.</li>
              <li class="mb-2"><strong>Step 3:</strong> Click "Clear Sitemap Cache" to refresh the sitemap with new slug URLs.</li>
              <li><strong>Step 4:</strong> Verify by visiting any product — old <code>?id=</code> URLs will 301 redirect to new slug URLs.</li>
            </ol>
          </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-flex gap-3 mb-4">
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="migrate_slugs">
            <button type="submit" class="btn btn-success rounded-pill" <?= !$status['migrated'] && $productsMissingSlugs === 0 ? '' : ''; ?>>
              <i class="fas fa-magic me-2"></i>Generate Slugs (<?= $productsMissingSlugs; ?> products need slugs)
            </button>
          </form>
          
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="clear_sitemap_cache">
            <button type="submit" class="btn btn-outline-primary rounded-pill">
              <i class="fas fa-sync me-2"></i>Clear Sitemap Cache
            </button>
          </form>
        </div>
        
        <!-- Sample URLs Preview -->
        <?php if (!empty($sampleProducts)): ?>
        <h5 class="fw-semibold mb-3">URL Preview (Sample Products)</h5>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="table-light">
              <tr>
                <th>Product</th>
                <th>Old URL</th>
                <th>New SEO URL</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sampleProducts as $sp): ?>
              <tr>
                <td><?= htmlspecialchars($sp['name']); ?></td>
                <td><code class="text-danger">/product.php?id=<?= $sp['id']; ?></code></td>
                <td>
                  <?php if (!empty($sp['slug'])): ?>
                    <code class="text-success">/product/<?= htmlspecialchars($sp['slug']); ?></code>
                  <?php else: ?>
                    <span class="text-muted">— not yet generated —</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
        
      </div>
    </div>
  </div>
</section>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
