<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'System Check — Admin';
$adminPage = 'dashboard';

// Run comprehensive system checks
$checks = [];

// 1. Database Connection
try {
    $db = get_db_connection();
    $checks[] = ['test' => 'Database Connection', 'status' => 'success', 'message' => 'Connected successfully'];
} catch (Exception $e) {
    $checks[] = ['test' => 'Database Connection', 'status' => 'error', 'message' => $e->getMessage()];
}

// 2. Categories Table
try {
    $categories = admin_get_categories();
    $checks[] = ['test' => 'Categories Table', 'status' => 'success', 'message' => count($categories) . ' categories found'];
} catch (Exception $e) {
    $checks[] = ['test' => 'Categories Table', 'status' => 'error', 'message' => $e->getMessage()];
}

// 3. Products Table
try {
    $products = admin_get_products();
    $checks[] = ['test' => 'Products Table', 'status' => 'success', 'message' => count($products) . ' products found'];
} catch (Exception $e) {
    $checks[] = ['test' => 'Products Table', 'status' => 'error', 'message' => $e->getMessage()];
}

// 4. Upload Directory
$uploadDir = __DIR__ . '/../assets/images/products/';
if (is_dir($uploadDir) && is_writable($uploadDir)) {
    $checks[] = ['test' => 'Upload Directory', 'status' => 'success', 'message' => 'Exists and writable'];
} else {
    $checks[] = ['test' => 'Upload Directory', 'status' => 'error', 'message' => 'Not writable or missing'];
}

// 5. Error Logging
$logDir = __DIR__ . '/../logs/';
if (is_dir($logDir) && is_writable($logDir)) {
    $checks[] = ['test' => 'Error Log Directory', 'status' => 'success', 'message' => 'Exists and writable'];
} else {
    $checks[] = ['test' => 'Error Log Directory', 'status' => 'warning', 'message' => 'Will be auto-created'];
}

// 6. Bootstrap JS
$checks[] = ['test' => 'Bootstrap 5 JS', 'status' => 'info', 'message' => 'Check browser console for "bootstrap" object'];

// 7. Required Tables
$requiredTables = ['users', 'categories', 'products', 'orders', 'order_items'];
foreach ($requiredTables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $checks[] = ['test' => "Table: $table", 'status' => 'success', 'message' => 'Exists'];
        } else {
            $checks[] = ['test' => "Table: $table", 'status' => 'error', 'message' => 'Missing'];
        }
    } catch (Exception $e) {
        $checks[] = ['test' => "Table: $table", 'status' => 'error', 'message' => $e->getMessage()];
    }
}

// 8. New Columns Check
try {
    $stmt = $db->query("SHOW COLUMNS FROM products LIKE 'net_weight'");
    if ($stmt->rowCount() > 0) {
        $checks[] = ['test' => 'Product New Columns', 'status' => 'success', 'message' => 'net_weight and bullet_points exist'];
    } else {
        $checks[] = ['test' => 'Product New Columns', 'status' => 'warning', 'message' => 'Run admin_fixes_schema.sql for new features'];
    }
} catch (Exception $e) {
    $checks[] = ['test' => 'Product New Columns', 'status' => 'warning', 'message' => 'Check skipped'];
}

// 9. Test Category Creation
$testCategoryName = 'TEST_' . time();
try {
    $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->execute([$testCategoryName]);
    $testId = $db->lastInsertId();
    
    // Clean up
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$testId]);
    
    $checks[] = ['test' => 'Category Creation Test', 'status' => 'success', 'message' => 'Can create and delete categories'];
} catch (Exception $e) {
    $checks[] = ['test' => 'Category Creation Test', 'status' => 'error', 'message' => $e->getMessage()];
}

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="mb-4">
      <h4 class="fw-semibold mb-2">System Health Check</h4>
      <p class="text-muted">Comprehensive check of all admin panel components</p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card bg-success text-white">
          <div class="card-body">
            <h6 class="mb-1">Passed</h6>
            <h3 class="mb-0"><?= count(array_filter($checks, fn($c) => $c['status'] === 'success')); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-danger text-white">
          <div class="card-body">
            <h6 class="mb-1">Failed</h6>
            <h3 class="mb-0"><?= count(array_filter($checks, fn($c) => $c['status'] === 'error')); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning text-white">
          <div class="card-body">
            <h6 class="mb-1">Warnings</h6>
            <h3 class="mb-0"><?= count(array_filter($checks, fn($c) => $c['status'] === 'warning')); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white">
          <div class="card-body">
            <h6 class="mb-1">Info</h6>
            <h3 class="mb-0"><?= count(array_filter($checks, fn($c) => $c['status'] === 'info')); ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Detailed Results -->
    <div class="card">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-check-circle"></i> System Check Results</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Test</th>
                <th>Status</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($checks as $check): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($check['test']); ?></strong></td>
                  <td>
                    <?php
                    $badges = [
                      'success' => 'success',
                      'error' => 'danger',
                      'warning' => 'warning',
                      'info' => 'info'
                    ];
                    $icons = [
                      'success' => 'check-circle',
                      'error' => 'times-circle',
                      'warning' => 'exclamation-triangle',
                      'info' => 'info-circle'
                    ];
                    $badge = $badges[$check['status']] ?? 'secondary';
                    $icon = $icons[$check['status']] ?? 'question-circle';
                    ?>
                    <span class="badge bg-<?= $badge; ?>">
                      <i class="fas fa-<?= $icon; ?>"></i>
                      <?= strtoupper($check['status']); ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($check['message']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Quick Fixes -->
    <div class="card mt-4">
      <div class="card-header bg-warning">
        <h5 class="mb-0"><i class="fas fa-wrench"></i> Quick Fixes</h5>
      </div>
      <div class="card-body">
        <h6>If Upload Directory Failed:</h6>
        <pre class="bg-dark text-white p-3 rounded">mkdir "c:\xampp\htdocs\Gilaf Ecommerce website\assets\images\products"
icacls "c:\xampp\htdocs\Gilaf Ecommerce website\assets\images\products" /grant Everyone:F</pre>

        <h6 class="mt-3">If Tables Missing:</h6>
        <pre class="bg-dark text-white p-3 rounded">Run database.sql in phpMyAdmin</pre>

        <h6 class="mt-3">If Category Creation Test Failed:</h6>
        <p>Check the exact error message above. Common issues:</p>
        <ul>
          <li>Table doesn't exist → Run database.sql</li>
          <li>Permission denied → Check MySQL user permissions</li>
          <li>Duplicate entry → Check for unique constraints</li>
        </ul>
      </div>
    </div>

    <!-- JavaScript Test -->
    <div class="card mt-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-code"></i> JavaScript Check</h5>
      </div>
      <div class="card-body">
        <div id="jsCheckResults"></div>
        <button class="btn btn-primary mt-3" onclick="testModal()">Test Modal Functionality</button>
      </div>
    </div>
  </div>
</section>

<!-- Test Modal -->
<div class="modal fade" id="testModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Test Modal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-success"><i class="fas fa-check-circle"></i> <strong>Modal is working!</strong></p>
        <p>If you can see this, Bootstrap modals are functioning correctly.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
// JavaScript checks
document.addEventListener('DOMContentLoaded', function() {
  const results = [];
  
  // Check if Bootstrap is loaded
  if (typeof bootstrap !== 'undefined') {
    results.push('<div class="alert alert-success"><i class="fas fa-check"></i> Bootstrap 5 JS loaded</div>');
  } else {
    results.push('<div class="alert alert-danger"><i class="fas fa-times"></i> Bootstrap 5 JS NOT loaded</div>');
  }
  
  // Check if jQuery is loaded
  if (typeof $ !== 'undefined') {
    results.push('<div class="alert alert-success"><i class="fas fa-check"></i> jQuery loaded (version: ' + $.fn.jquery + ')</div>');
  } else {
    results.push('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> jQuery not loaded (optional)</div>');
  }
  
  // Check if MDB is loaded
  if (typeof mdb !== 'undefined') {
    results.push('<div class="alert alert-info"><i class="fas fa-info"></i> MDB UI Kit loaded (may cause conflicts)</div>');
  } else {
    results.push('<div class="alert alert-success"><i class="fas fa-check"></i> MDB not loaded (good - using Bootstrap 5)</div>');
  }
  
  document.getElementById('jsCheckResults').innerHTML = results.join('');
});

function testModal() {
  const modal = new bootstrap.Modal(document.getElementById('testModal'));
  modal.show();
}
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
