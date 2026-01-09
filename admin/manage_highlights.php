<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Manage Product Highlights';
$adminPage = 'highlights';

// Get all products for dropdown
$products = db_fetch_all('SELECT id, name FROM products ORDER BY name ASC');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $product_id = (int)$_POST['product_id'];
            $highlights_text = trim($_POST['highlights_text']);
            
            if ($product_id && $highlights_text) {
                // Split by newlines and filter empty lines
                $highlights = array_filter(array_map('trim', explode("\n", $highlights_text)));
                
                // Validate count (min 3, max 5)
                $count = count($highlights);
                if ($count < 3) {
                    $error = 'Please enter at least 3 highlights (one per line).';
                } elseif ($count > 5) {
                    $error = 'Maximum 5 highlights allowed. Please remove ' . ($count - 5) . ' highlight(s).';
                } else {
                    // Delete existing highlights for this product
                    db_query('DELETE FROM product_highlights WHERE product_id = ?', [$product_id]);
                    
                    // Insert new highlights
                    $display_order = 1;
                    foreach ($highlights as $highlight_text) {
                        db_query('INSERT INTO product_highlights (product_id, highlight_text, display_order) VALUES (?, ?, ?)', 
                            [$product_id, $highlight_text, $display_order]);
                        $display_order++;
                    }
                    $success = count($highlights) . ' highlights saved successfully!';
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $product_id = (int)$_POST['product_id'];
            db_query('DELETE FROM product_highlights WHERE product_id = ?', [$product_id]);
            $success = 'All highlights deleted for this product!';
        } elseif ($_POST['action'] === 'edit') {
            $product_id = (int)$_POST['product_id'];
            $highlights_text = trim($_POST['highlights_text']);
            
            if ($product_id && $highlights_text) {
                // Split by newlines and filter empty lines
                $highlights = array_filter(array_map('trim', explode("\n", $highlights_text)));
                
                // Validate count (min 3, max 5)
                $count = count($highlights);
                if ($count < 3) {
                    $error = 'Please enter at least 3 highlights (one per line).';
                } elseif ($count > 5) {
                    $error = 'Maximum 5 highlights allowed. Please remove ' . ($count - 5) . ' highlight(s).';
                } else {
                    // Delete existing highlights for this product
                    db_query('DELETE FROM product_highlights WHERE product_id = ?', [$product_id]);
                    
                    // Insert new highlights
                    $display_order = 1;
                    foreach ($highlights as $highlight_text) {
                        db_query('INSERT INTO product_highlights (product_id, highlight_text, display_order) VALUES (?, ?, ?)', 
                            [$product_id, $highlight_text, $display_order]);
                        $display_order++;
                    }
                    $success = count($highlights) . ' highlights updated successfully!';
                }
            }
        }
    }
}

// Check if highlights system is set up
$highlightsSystemExists = false;
$setupError = null;

try {
    // Try to fetch highlights
    $highlights = db_fetch_all('
        SELECT h.*, p.name as product_name 
        FROM product_highlights h 
        LEFT JOIN products p ON h.product_id = p.id 
        ORDER BY p.name, h.display_order ASC
    ');
    $highlightsSystemExists = true;
} catch (PDOException $e) {
    // Table doesn't exist
    if (strpos($e->getMessage(), "Base table or view not found") !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $setupError = "Product highlights system not set up. Please run the SQL migration.";
        $highlights = [];
    } else {
        throw $e;
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.highlights-container {
    background: #ffffff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f1f3f5;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.add-highlight-form {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 15px;
}

.form-control:focus {
    border-color: #d4af37;
    outline: none;
}

.btn-primary {
    padding: 12px 24px;
    background: linear-gradient(135deg, #d4af37 0%, #c5a028 100%);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #c5a028 0%, #b69121 100%);
}

.highlights-table {
    width: 100%;
    border-collapse: collapse;
}

.highlights-table thead {
    background: #f8f9fa;
}

.highlights-table th {
    padding: 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    border-bottom: 2px solid #e9ecef;
}

.highlights-table td {
    padding: 16px;
    border-bottom: 1px solid #f1f3f5;
}

.highlight-text {
    font-size: 15px;
    color: #1a1a1a;
    font-weight: 500;
}

.product-name {
    font-size: 14px;
    color: #6c757d;
}

.display-order-badge {
    display: inline-block;
    padding: 4px 12px;
    background: #e9ecef;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.btn-edit, .btn-delete {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    margin-right: 8px;
}

.btn-edit {
    background: #fff9e6;
    color: #d4af37;
}

.btn-edit:hover {
    background: #d4af37;
    color: #ffffff;
}

.btn-delete {
    background: #fff5f5;
    color: #dc3545;
}

.btn-delete:hover {
    background: #dc3545;
    color: #ffffff;
}

.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #10b981;
}
</style>

<div class="container mt-4">
    <div class="highlights-container">
        <div class="page-header">
            <h1 class="page-title">Manage Product Highlights</h1>
        </div>

        <?php if (isset($setupError)): ?>
            <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b;">
                <h5 style="margin-bottom: 16px;"><i class="fas fa-exclamation-triangle me-2"></i>Setup Required</h5>
                <p style="margin-bottom: 16px;"><strong><?= htmlspecialchars($setupError); ?></strong></p>
                <p style="margin-bottom: 16px;">The product_highlights table needs to be created. Follow these steps:</p>
                <ol style="margin-bottom: 16px;">
                    <li>Open phpMyAdmin: <code>http://localhost/phpmyadmin</code></li>
                    <li>Select database: <strong>ecommerce_db</strong></li>
                    <li>Click the <strong>SQL</strong> tab</li>
                    <li>Copy and paste the SQL below</li>
                    <li>Click <strong>Go</strong></li>
                    <li>Refresh this page</li>
                </ol>
                <div style="background: #1f2937; color: #f9fafb; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 13px; overflow-x: auto;">
CREATE TABLE `product_highlights` (<br>
&nbsp;&nbsp;`id` INT AUTO_INCREMENT PRIMARY KEY,<br>
&nbsp;&nbsp;`product_id` INT NOT NULL,<br>
&nbsp;&nbsp;`highlight_text` VARCHAR(255) NOT NULL,<br>
&nbsp;&nbsp;`display_order` INT DEFAULT 0,<br>
&nbsp;&nbsp;`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,<br>
&nbsp;&nbsp;FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,<br>
&nbsp;&nbsp;INDEX `idx_product_id` (`product_id`),<br>
&nbsp;&nbsp;INDEX `idx_display_order` (`display_order`)<br>
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;<br><br>
INSERT INTO `product_highlights` (`product_id`, `highlight_text`, `display_order`) VALUES<br>
(1, 'Immunity Booster', 1),<br>
(1, 'Antioxidant', 2),<br>
(1, 'Weight Loss', 3);
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!$highlightsSystemExists): ?>
            <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                <i class="fas fa-database" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Database Setup Required</p>
                <p>Please run the SQL migration above to enable highlights management.</p>
            </div>
        <?php else: ?>

        <!-- Add New Highlight Form -->
        <div class="add-highlight-form">
            <h3 style="margin-bottom: 20px; font-size: 18px; font-weight: 700;">Add/Update Product Highlights</h3>
            <form method="post" id="highlightForm">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label">Product</label>
                    <select name="product_id" id="product_select" class="form-control" required onchange="loadProductHighlights(this.value)">
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['id']; ?>"><?= htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Highlights (Min: 3, Max: 5)</label>
                    <textarea name="highlights_text" id="highlights_textarea" class="form-control" rows="5" placeholder="Enter one highlight per line&#10;Example:&#10;Immunity Booster&#10;Antioxidant&#10;Weight Loss" required style="font-family: inherit; resize: vertical;"></textarea>
                    <small style="display: block; margin-top: 8px; color: #6c757d;">
                        <i class="fas fa-info-circle"></i> Enter 3-5 highlights, one per line. Press Enter for a new line.
                    </small>
                    <div id="line_counter" style="margin-top: 8px; font-weight: 600; font-size: 14px;"></div>
                </div>

                <button type="submit" class="btn-primary" id="submit_btn">Add Highlights</button>
            </form>
        </div>

        <!-- Highlights Table -->
        <table class="highlights-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Highlights</th>
                    <th>Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Group highlights by product
                $grouped = [];
                foreach ($highlights as $highlight) {
                    $pid = $highlight['product_id'];
                    if (!isset($grouped[$pid])) {
                        $grouped[$pid] = [
                            'product_name' => $highlight['product_name'],
                            'product_id' => $pid,
                            'highlights' => []
                        ];
                    }
                    $grouped[$pid]['highlights'][] = $highlight['highlight_text'];
                }
                
                foreach ($grouped as $product): 
                    $count = count($product['highlights']);
                    $countColor = ($count >= 3 && $count <= 5) ? '#10b981' : '#ef4444';
                ?>
                    <tr>
                        <td>
                            <span class="product-name"><?= htmlspecialchars($product['product_name']); ?></span>
                        </td>
                        <td>
                            <ul style="margin: 0; padding-left: 20px;">
                                <?php foreach ($product['highlights'] as $hl): ?>
                                    <li class="highlight-text"><?= htmlspecialchars($hl); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td>
                            <span class="display-order-badge" style="background: <?= $countColor; ?>; color: white;"><?= $count; ?></span>
                        </td>
                        <td>
                            <button class="btn-edit" onclick="editProductHighlights(<?= $product['product_id']; ?>, '<?= htmlspecialchars($product['product_name'], ENT_QUOTES); ?>')">Edit</button>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?= $product['product_id']; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Delete all highlights for this product?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

        <?php endif; ?>

    </div>
</div>

<script>
const productHighlights = <?= json_encode($highlights); ?>;

function countLines() {
    const textarea = document.getElementById('highlights_textarea');
    const text = textarea.value.trim();
    const lines = text ? text.split('\n').filter(line => line.trim()).length : 0;
    const counter = document.getElementById('line_counter');
    const submitBtn = document.getElementById('submit_btn');
    
    if (lines < 3) {
        counter.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-exclamation-circle"></i> ' + lines + ' highlights (Need at least 3)</span>';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
    } else if (lines > 5) {
        counter.innerHTML = '<span style="color: #ef4444;"><i class="fas fa-exclamation-circle"></i> ' + lines + ' highlights (Maximum 5 allowed)</span>';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
    } else {
        counter.innerHTML = '<span style="color: #10b981;"><i class="fas fa-check-circle"></i> ' + lines + ' highlights (Valid)</span>';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
    }
}

function loadProductHighlights(productId) {
    const textarea = document.getElementById('highlights_textarea');
    const form = document.getElementById('highlightForm');
    const submitBtn = document.getElementById('submit_btn');
    
    if (!productId) {
        textarea.value = '';
        form.querySelector('input[name="action"]').value = 'add';
        submitBtn.textContent = 'Add Highlights';
        countLines();
        return;
    }
    
    // Get highlights for this product
    const highlights = productHighlights.filter(h => h.product_id == productId);
    
    if (highlights.length > 0) {
        // Load existing highlights
        const texts = highlights.map(h => h.highlight_text).join('\n');
        textarea.value = texts;
        form.querySelector('input[name="action"]').value = 'edit';
        submitBtn.textContent = 'Update Highlights';
    } else {
        // No highlights yet
        textarea.value = '';
        form.querySelector('input[name="action"]').value = 'add';
        submitBtn.textContent = 'Add Highlights';
    }
    
    countLines();
}

function editProductHighlights(productId, productName) {
    // Set the product in dropdown
    document.getElementById('product_select').value = productId;
    
    // Load highlights
    loadProductHighlights(productId);
    
    // Scroll to form
    document.querySelector('.add-highlight-form').scrollIntoView({ behavior: 'smooth' });
}

// Add event listener for textarea
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('highlights_textarea');
    textarea.addEventListener('input', countLines);
    textarea.addEventListener('keyup', countLines);
    countLines();
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
