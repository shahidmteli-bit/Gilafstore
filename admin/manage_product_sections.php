<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    redirect_with_message('/login.php', 'Access denied', 'danger');
}

$pageTitle = 'Manage Product Sections';
$adminPage = 'product_sections';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_products_by_category') {
    header('Content-Type: application/json');
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    
    if ($categoryId > 0) {
        $products = db_fetch_all('SELECT id, name FROM products WHERE category_id = ? ORDER BY name ASC', [$categoryId]);
    } else {
        $products = db_fetch_all('SELECT id, name FROM products ORDER BY name ASC');
    }
    
    echo json_encode(['products' => $products]);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Handle Bulk Update actions
        if ($_POST['action'] === 'bulk_update') {
            $sourceProductId = (int)$_POST['source_product_id'];
            $targetType = $_POST['target_type']; // 'products' or 'category'
            $selectedSections = $_POST['sections'] ?? [];
            $includeHighlights = isset($_POST['include_highlights']);
            
            $targetProducts = [];
            
            if ($targetType === 'category') {
                $categoryId = (int)$_POST['category_id'];
                $targetProducts = db_fetch_all('SELECT id FROM products WHERE category_id = ?', [$categoryId]);
            } else {
                $selectedProducts = $_POST['target_products'] ?? [];
                foreach ($selectedProducts as $pid) {
                    $targetProducts[] = ['id' => (int)$pid];
                }
            }
            
            $updatedCount = 0;
            
            foreach ($targetProducts as $targetProduct) {
                $targetId = $targetProduct['id'];
                
                // Skip source product
                if ($targetId == $sourceProductId) continue;
                
                // Copy selected sections
                foreach ($selectedSections as $sectionType) {
                    $sourceSection = db_fetch('SELECT * FROM product_sections WHERE product_id = ? AND section_type = ?', 
                        [$sourceProductId, $sectionType]);
                    
                    if ($sourceSection) {
                        // Check if target has this section
                        $existingSection = db_fetch('SELECT id FROM product_sections WHERE product_id = ? AND section_type = ?', 
                            [$targetId, $sectionType]);
                        
                        if ($existingSection) {
                            // Update existing
                            db_query('UPDATE product_sections SET content = ?, display_order = ?, is_active = ?, updated_at = NOW() 
                                     WHERE product_id = ? AND section_type = ?', 
                                [$sourceSection['content'], $sourceSection['display_order'], $sourceSection['is_active'], $targetId, $sectionType]);
                        } else {
                            // Insert new
                            db_query('INSERT INTO product_sections (product_id, section_type, content, display_order, is_active) 
                                     VALUES (?, ?, ?, ?, ?)', 
                                [$targetId, $sectionType, $sourceSection['content'], $sourceSection['display_order'], $sourceSection['is_active']]);
                        }
                    }
                }
                
                // Copy highlights if selected
                if ($includeHighlights) {
                    $sourceHighlights = db_fetch_all('SELECT * FROM product_highlights WHERE product_id = ? ORDER BY display_order ASC', 
                        [$sourceProductId]);
                    
                    if (!empty($sourceHighlights)) {
                        // Delete existing highlights
                        db_query('DELETE FROM product_highlights WHERE product_id = ?', [$targetId]);
                        
                        // Insert source highlights
                        foreach ($sourceHighlights as $highlight) {
                            db_query('INSERT INTO product_highlights (product_id, highlight_text, display_order) VALUES (?, ?, ?)', 
                                [$targetId, $highlight['highlight_text'], $highlight['display_order']]);
                        }
                    }
                }
                
                $updatedCount++;
            }
            
            $_SESSION['message'] = "Successfully updated {$updatedCount} product(s) with selected sections and highlights!";
            $_SESSION['message_type'] = 'success';
            header('Location: manage_product_sections.php?bulk_mode=1');
            exit;
        }
        
        $productId = (int)$_POST['product_id'];
        
        // Handle Highlights actions
        if ($_POST['action'] === 'save_highlights') {
            $highlights_text = trim($_POST['highlights_text']);
            
            if ($productId && $highlights_text) {
                $highlights = array_filter(array_map('trim', explode("\n", $highlights_text)));
                $count = count($highlights);
                
                if ($count < 3) {
                    $_SESSION['message'] = 'Please enter at least 3 highlights (one per line).';
                    $_SESSION['message_type'] = 'danger';
                } elseif ($count > 5) {
                    $_SESSION['message'] = 'Maximum 5 highlights allowed. Please remove ' . ($count - 5) . ' highlight(s).';
                    $_SESSION['message_type'] = 'danger';
                } else {
                    db_query('DELETE FROM product_highlights WHERE product_id = ?', [$productId]);
                    $display_order = 1;
                    foreach ($highlights as $highlight_text) {
                        db_query('INSERT INTO product_highlights (product_id, highlight_text, display_order) VALUES (?, ?, ?)', 
                            [$productId, $highlight_text, $display_order]);
                        $display_order++;
                    }
                    $_SESSION['message'] = count($highlights) . ' highlights saved successfully!';
                    $_SESSION['message_type'] = 'success';
                }
            }
            header('Location: manage_product_sections.php?product_id=' . $productId);
            exit;
        } elseif ($_POST['action'] === 'delete_highlights') {
            db_query('DELETE FROM product_highlights WHERE product_id = ?', [$productId]);
            $_SESSION['message'] = 'All highlights deleted for this product!';
            $_SESSION['message_type'] = 'success';
            header('Location: manage_product_sections.php?product_id=' . $productId);
            exit;
        }
        
        // Handle Product Sections actions
        $sectionType = $_POST['section_type'] ?? '';
        
        if ($_POST['action'] === 'save') {
            $content = trim($_POST['content']);
            $displayOrder = (int)$_POST['display_order'];
            
            if (!empty($content)) {
                $existing = db_fetch('SELECT id FROM product_sections WHERE product_id = ? AND section_type = ?', [$productId, $sectionType]);
                
                if ($existing) {
                    db_query('UPDATE product_sections SET content = ?, display_order = ?, updated_at = NOW() WHERE product_id = ? AND section_type = ?', 
                        [$content, $displayOrder, $productId, $sectionType]);
                    $_SESSION['message'] = 'Section updated successfully';
                } else {
                    db_query('INSERT INTO product_sections (product_id, section_type, content, display_order) VALUES (?, ?, ?, ?)', 
                        [$productId, $sectionType, $content, $displayOrder]);
                    $_SESSION['message'] = 'Section added successfully';
                }
                $_SESSION['message_type'] = 'success';
            }
        } elseif ($_POST['action'] === 'delete') {
            db_query('DELETE FROM product_sections WHERE product_id = ? AND section_type = ?', [$productId, $sectionType]);
            $_SESSION['message'] = 'Section deleted successfully';
            $_SESSION['message_type'] = 'success';
        } elseif ($_POST['action'] === 'toggle') {
            db_query('UPDATE product_sections SET is_active = NOT is_active WHERE product_id = ? AND section_type = ?', [$productId, $sectionType]);
            $_SESSION['message'] = 'Section status updated';
            $_SESSION['message_type'] = 'success';
        }
        
        header('Location: manage_product_sections.php?product_id=' . $productId);
        exit;
    }
}

// Get product ID, category ID, and bulk mode from query string
$selectedProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$selectedCategoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$bulkMode = isset($_GET['bulk_mode']) ? (int)$_GET['bulk_mode'] : 0;

// Get all categories for dropdown
$categories = db_fetch_all('SELECT id, name FROM categories ORDER BY name ASC');

// Get products filtered by category if selected
if ($selectedCategoryId > 0) {
    $products = db_fetch_all('SELECT id, name FROM products WHERE category_id = ? ORDER BY name ASC', [$selectedCategoryId]);
} else {
    $products = db_fetch_all('SELECT id, name FROM products ORDER BY name ASC');
}

// Get sections for selected product
$sections = [];
$productHighlights = [];
if ($selectedProductId > 0) {
    $sections = db_fetch_all('SELECT * FROM product_sections WHERE product_id = ? ORDER BY display_order ASC', [$selectedProductId]);
    
    // Convert to associative array by section_type
    $sectionsMap = [];
    foreach ($sections as $section) {
        $sectionsMap[$section['section_type']] = $section;
    }
    
    // Get highlights for selected product
    try {
        $productHighlights = db_fetch_all('SELECT * FROM product_highlights WHERE product_id = ? ORDER BY display_order ASC', [$selectedProductId]);
    } catch (PDOException $e) {
        // Table doesn't exist yet
        $productHighlights = [];
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* Professional Product Sections Styling */
.card {
    border: 1px solid #e0e0e0;
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.card-header {
    border-bottom: 2px solid #e0e0e0;
    font-weight: 600;
}

.form-label {
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.form-select, .form-control {
    border: 1px solid #ced4da;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form-select:focus, .form-control:focus {
    border-color: #4a90e2;
    box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.15);
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(56, 239, 125, 0.4);
}

.shadow-sm {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
}

.border-primary {
    border-color: #667eea !important;
    border-width: 2px !important;
}

#productCount {
    min-height: 20px;
}

/* Smooth transitions */
select, input, textarea, button {
    transition: all 0.2s ease;
}

/* Loading state */
.form-select.loading {
    opacity: 0.6;
    pointer-events: none;
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Manage Product Sections</h1>
            <?php if ($bulkMode): ?>
                <p class="text-muted mb-0"><i class="fas fa-layer-group me-2"></i>Bulk Update Mode - Apply sections to multiple products</p>
            <?php endif; ?>
        </div>
        <div>
            <?php if ($bulkMode): ?>
                <a href="manage_product_sections.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Exit Bulk Mode
                </a>
            <?php else: ?>
                <a href="manage_product_sections.php?bulk_mode=1" class="btn btn-primary">
                    <i class="fas fa-layer-group me-2"></i>Bulk Update Mode
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if ($bulkMode): ?>
        <!-- Bulk Update Interface -->
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Bulk Update - Copy Sections to Multiple Products</h5>
            </div>
            <div class="card-body">
                <form method="post" id="bulkUpdateForm">
                    <input type="hidden" name="action" value="bulk_update">
                    
                    <div class="row g-4">
                        <!-- Step 1: Select Source Product -->
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong><i class="fas fa-info-circle me-2"></i>How it works:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Select a source product (the one with sections you want to copy)</li>
                                    <li>Choose which sections to copy (Storage, Description, etc.)</li>
                                    <li>Select target products or entire category</li>
                                    <li>Preview and apply changes</li>
                                </ol>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Step 1: Source Product (Copy From)</label>
                            <select name="source_product_id" id="sourceProduct" class="form-select" required>
                                <option value="">-- Select source product --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>">
                                        <?= htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Product with sections you want to copy</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Step 2: Select Sections to Copy</label>
                            <div class="border rounded p-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sections[]" value="storage" id="section_storage">
                                    <label class="form-check-label" for="section_storage">
                                        📦 Storage & Shelf Life
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sections[]" value="description" id="section_description">
                                    <label class="form-check-label" for="section_description">
                                        📝 Description
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sections[]" value="nutritional" id="section_nutritional">
                                    <label class="form-check-label" for="section_nutritional">
                                        🌿 Nutritional & Usage Information
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sections[]" value="shipping" id="section_shipping">
                                    <label class="form-check-label" for="section_shipping">
                                        🚚 Shipping & Returns
                                    </label>
                                </div>
                                <hr class="my-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_highlights" id="include_highlights">
                                    <label class="form-check-label" for="include_highlights">
                                        ⭐ Product Highlights
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Select Target -->
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Step 3: Apply To (Target)</label>
                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="target_type" id="target_products" value="products" checked>
                                <label class="btn btn-outline-primary" for="target_products">
                                    <i class="fas fa-box me-2"></i>Specific Products
                                </label>
                                
                                <input type="radio" class="btn-check" name="target_type" id="target_category" value="category">
                                <label class="btn btn-outline-primary" for="target_category">
                                    <i class="fas fa-tags me-2"></i>Entire Category
                                </label>
                            </div>
                            
                            <!-- Target: Specific Products -->
                            <div id="targetProductsDiv" class="border rounded p-3">
                                <label class="form-label">Select Products to Update:</label>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <div class="mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllProducts()">Select All</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllProducts()">Deselect All</button>
                                    </div>
                                    <?php foreach ($products as $product): ?>
                                        <div class="form-check">
                                            <input class="form-check-input product-checkbox" type="checkbox" name="target_products[]" 
                                                   value="<?= $product['id']; ?>" id="product_<?= $product['id']; ?>">
                                            <label class="form-check-label" for="product_<?= $product['id']; ?>">
                                                <?= htmlspecialchars($product['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Source product will be automatically excluded</small>
                            </div>
                            
                            <!-- Target: Category -->
                            <div id="targetCategoryDiv" class="border rounded p-3" style="display: none;">
                                <label class="form-label">Select Category:</label>
                                <select name="category_id" class="form-select">
                                    <option value="">-- Select category --</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id']; ?>">
                                            <?= htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">All products in this category will be updated (except source product)</small>
                            </div>
                        </div>
                        
                        <!-- Submit -->
                        <div class="col-md-12">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> This will overwrite existing sections in target products. This action cannot be undone.
                            </div>
                            <button type="submit" class="btn btn-success btn-lg" onclick="return confirm('Are you sure you want to apply these sections to the selected products? This will overwrite existing content.')">
                                <i class="fas fa-check me-2"></i>Apply Bulk Update
                            </button>
                            <a href="manage_product_sections.php" class="btn btn-outline-secondary btn-lg ms-2">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Regular Product Selection -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Product Selection</h6>
            </div>
            <div class="card-body">
                <form method="get" id="productSelectionForm">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-tags me-1"></i>Filter by Category
                            </label>
                            <select name="category_id" id="categoryFilter" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id']; ?>" <?= $selectedCategoryId == $category['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="productCount" class="mt-1">
                                <?php if ($selectedCategoryId > 0): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i><?= count($products); ?> product(s) available
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i><?= count($products); ?> total products
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-box me-1"></i>Select Product
                            </label>
                            <select name="product_id" class="form-select" required>
                                <option value="">-- Choose a product --</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id']; ?>" <?= $selectedProductId == $product['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100 d-block">
                                <i class="fas fa-arrow-right me-2"></i>Load
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selectedProductId > 0): ?>
        
        <!-- Product Highlights Section -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Product Highlights (3-5 items)</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                            <input type="hidden" name="action" value="save_highlights">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Enter Highlights (one per line, minimum 3, maximum 5)</label>
                                <textarea name="highlights_text" class="form-control" rows="6" placeholder="100% Natural Ingredients&#10;No Artificial Colors or Flavors&#10;Rich in Antioxidants&#10;Suitable for All Ages&#10;Certified Organic" required><?php
                                    if (!empty($productHighlights)) {
                                        foreach ($productHighlights as $highlight) {
                                            echo htmlspecialchars($highlight['highlight_text']) . "\n";
                                        }
                                    }
                                ?></textarea>
                                <small class="text-muted">Each line will be displayed as a separate highlight point on the product page.</small>
                            </div>
                            
                            <div class="d-flex gap-2 align-items-center">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save Highlights
                                </button>
                                <?php if (!empty($productHighlights)): ?>
                                    <button type="button" class="btn btn-danger" onclick="if(confirm('Delete all highlights for this product?')) { document.getElementById('deleteHighlightsForm').submit(); }">
                                        <i class="fas fa-trash me-2"></i>Delete All Highlights
                                    </button>
                                    <span class="badge bg-success ms-2"><?= count($productHighlights); ?> highlights saved</span>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if (!empty($productHighlights)): ?>
                            <form id="deleteHighlightsForm" method="post" class="d-none">
                                <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                                <input type="hidden" name="action" value="delete_highlights">
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Sections -->
        <div class="row g-4">
            
            <!-- Storage & Shelf Life -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📦 Storage & Shelf Life</h5>
                        <?php if (isset($sectionsMap['storage'])): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Toggle section status?');">
                                <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                                <input type="hidden" name="section_type" value="storage">
                                <input type="hidden" name="action" value="toggle">
                                <button type="submit" class="btn btn-sm btn-light">
                                    <?= $sectionsMap['storage']['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                            <input type="hidden" name="section_type" value="storage">
                            <input type="hidden" name="display_order" value="1">
                            <input type="hidden" name="action" value="save">
                            
                            <div class="mb-3">
                                <label class="form-label">Content (one item per line)</label>
                                <textarea name="content" class="form-control" rows="5" placeholder="Store in a cool, dry place&#10;Keep away from direct sunlight&#10;Refer to packaging for expiry date"><?= isset($sectionsMap['storage']) ? htmlspecialchars($sectionsMap['storage']['content']) : ''; ?></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save
                                </button>
                                <?php if (isset($sectionsMap['storage'])): ?>
                                    <button type="button" class="btn btn-danger" onclick="deleteSection('storage')">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Product Description -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📝 Product Description</h5>
                        <?php if (isset($sectionsMap['description'])): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Toggle section status?');">
                                <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                                <input type="hidden" name="section_type" value="description">
                                <input type="hidden" name="action" value="toggle">
                                <button type="submit" class="btn btn-sm btn-light">
                                    <?= $sectionsMap['description']['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                            <input type="hidden" name="section_type" value="description">
                            <input type="hidden" name="display_order" value="2">
                            <input type="hidden" name="action" value="save">
                            
                            <div class="mb-3">
                                <label class="form-label">Content (paragraph format)</label>
                                <textarea name="content" class="form-control" rows="5" placeholder="Gilaf Premium Raisins are carefully sourced and hygienically packed to preserve their natural taste and nutritional value..."><?= isset($sectionsMap['description']) ? htmlspecialchars($sectionsMap['description']['content']) : ''; ?></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save
                                </button>
                                <?php if (isset($sectionsMap['description'])): ?>
                                    <button type="button" class="btn btn-danger" onclick="deleteSection('description')">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Nutritional & Usage Information -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">🌿 Nutritional & Usage Information</h5>
                        <?php if (isset($sectionsMap['nutritional'])): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Toggle section status?');">
                                <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                                <input type="hidden" name="section_type" value="nutritional">
                                <input type="hidden" name="action" value="toggle">
                                <button type="submit" class="btn btn-sm btn-light">
                                    <?= $sectionsMap['nutritional']['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                            <input type="hidden" name="section_type" value="nutritional">
                            <input type="hidden" name="display_order" value="3">
                            <input type="hidden" name="action" value="save">
                            
                            <div class="mb-3">
                                <label class="form-label">Content (one item per line)</label>
                                <textarea name="content" class="form-control" rows="5" placeholder="Rich in natural antioxidants&#10;Supports daily energy needs&#10;Can be consumed directly or added to desserts"><?= isset($sectionsMap['nutritional']) ? htmlspecialchars($sectionsMap['nutritional']['content']) : ''; ?></textarea>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save
                                </button>
                                <?php if (isset($sectionsMap['nutritional'])): ?>
                                    <button type="button" class="btn btn-danger" onclick="deleteSection('nutritional')">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Shipping & Returns -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">🚚 Shipping & Returns</h5>
                        <?php if (isset($sectionsMap['shipping'])): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Toggle section status?');">
                                <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                                <input type="hidden" name="section_type" value="shipping">
                                <input type="hidden" name="action" value="toggle">
                                <button type="submit" class="btn btn-sm btn-light">
                                    <?= $sectionsMap['shipping']['is_active'] ? 'Active' : 'Inactive'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                            <input type="hidden" name="section_type" value="shipping">
                            <input type="hidden" name="display_order" value="4">
                            <input type="hidden" name="action" value="save">
                            
                            <div class="mb-3">
                                <label class="form-label">Content (one item per line)</label>
                                <textarea name="content" class="form-control" rows="5" placeholder="Domestic delivery within 3–5 business days&#10;International delivery available&#10;Returns accepted as per return policy"><?= isset($sectionsMap['shipping']) ? htmlspecialchars($sectionsMap['shipping']['content']) : ''; ?></textarea>
                                <small class="text-muted">Note: "Secure checkout" message is automatically added on the product page.</small>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save
                                </button>
                                <?php if (isset($sectionsMap['shipping'])): ?>
                                    <button type="button" class="btn btn-danger" onclick="deleteSection('shipping')">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

        <!-- Delete Forms (hidden) -->
        <?php foreach (['storage', 'description', 'nutritional', 'shipping'] as $type): ?>
            <form method="post" id="delete-form-<?= $type; ?>" style="display: none;">
                <input type="hidden" name="product_id" value="<?= $selectedProductId; ?>">
                <input type="hidden" name="section_type" value="<?= $type; ?>">
                <input type="hidden" name="action" value="delete">
            </form>
        <?php endforeach; ?>

    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Please select a product to manage its sections.
        </div>
    <?php endif; ?>

</div>

<script>
function deleteSection(type) {
    if (confirm('Are you sure you want to delete this section? This action cannot be undone.')) {
        document.getElementById('delete-form-' + type).submit();
    }
}

// Bulk Update Mode Functions
document.addEventListener('DOMContentLoaded', function() {
    // Toggle between specific products and category
    const targetProducts = document.getElementById('target_products');
    const targetCategory = document.getElementById('target_category');
    const targetProductsDiv = document.getElementById('targetProductsDiv');
    const targetCategoryDiv = document.getElementById('targetCategoryDiv');
    
    if (targetProducts && targetCategory) {
        targetProducts.addEventListener('change', function() {
            if (this.checked) {
                targetProductsDiv.style.display = 'block';
                targetCategoryDiv.style.display = 'none';
            }
        });
        
        targetCategory.addEventListener('change', function() {
            if (this.checked) {
                targetProductsDiv.style.display = 'none';
                targetCategoryDiv.style.display = 'block';
            }
        });
    }
    
    // Dynamic category filter (without page reload)
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            const categoryId = this.value;
            const form = document.getElementById('productSelectionForm');
            
            // Create FormData and submit via AJAX to get filtered products
            const formData = new FormData();
            formData.append('action', 'get_products_by_category');
            formData.append('category_id', categoryId);
            
            fetch('manage_product_sections.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const productSelect = form.querySelector('select[name="product_id"]');
                productSelect.innerHTML = '<option value="">-- Choose a product --</option>';
                
                data.products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = product.name;
                    productSelect.appendChild(option);
                });
                
                // Update product count
                const productCount = document.getElementById('productCount');
                if (productCount) {
                    const countText = categoryId 
                        ? `<i class="fas fa-info-circle me-1"></i>${data.products.length} product(s) available`
                        : `<i class="fas fa-info-circle me-1"></i>${data.products.length} total products`;
                    productCount.innerHTML = `<small class="text-muted">${countText}</small>`;
                }
            })
            .catch(error => {
                console.error('Error fetching products:', error);
            });
        });
    }
});

function selectAllProducts() {
    document.querySelectorAll('.product-checkbox').forEach(function(checkbox) {
        checkbox.checked = true;
    });
}

function deselectAllProducts() {
    document.querySelectorAll('.product-checkbox').forEach(function(checkbox) {
        checkbox.checked = false;
    });
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
