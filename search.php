<?php
$pageTitle = 'Search Results - Gilaf Store';
$activePage = 'search';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';

// Get user's region settings for currency conversion
$userRegion = get_user_region_settings();
$currentCurrency = $userRegion['currency'];
$currentCurrencySymbol = $userRegion['currency_symbol'];

// Get search query
$searchQuery = trim($_GET['q'] ?? '');
$category = isset($_GET['category']) ? (int)$_GET['category'] : null;

// Search products
$products = [];
if (!empty($searchQuery)) {
    $db = get_db_connection();
    
    // Comprehensive search across multiple fields with case-insensitive partial matching
    $searchConditions = [
        'LOWER(p.name) LIKE LOWER(:search1)',
        'LOWER(p.description) LIKE LOWER(:search2)',
        'LOWER(c.name) LIKE LOWER(:search3)'
    ];
    
    $paramCount = 3;
    
    // Check if SKU column exists and add to search
    try {
        $checkSku = $db->query("SHOW COLUMNS FROM products LIKE 'sku'");
        if ($checkSku->rowCount() > 0) {
            $paramCount++;
            $searchConditions[] = 'LOWER(p.sku) LIKE LOWER(:search' . $paramCount . ')';
        }
    } catch (PDOException $e) {
        // SKU column doesn't exist, continue without it
    }
    
    // Check if keywords/tags column exists and add to search
    try {
        $checkKeywords = $db->query("SHOW COLUMNS FROM products LIKE 'keywords'");
        if ($checkKeywords->rowCount() > 0) {
            $paramCount++;
            $searchConditions[] = 'LOWER(p.keywords) LIKE LOWER(:search' . $paramCount . ')';
        }
    } catch (PDOException $e) {
        // Keywords column doesn't exist, continue without it
    }
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE (" . implode(' OR ', $searchConditions) . ")";
    
    if ($category) {
        $sql .= " AND p.category_id = :category";
    }
    
    $sql .= " ORDER BY 
                CASE 
                    WHEN LOWER(p.name) = LOWER(:exactMatch) THEN 1
                    WHEN LOWER(p.name) LIKE LOWER(:startMatch) THEN 2
                    ELSE 3
                END,
                p.name ASC";
    
    $stmt = $db->prepare($sql);
    
    // Bind search parameters
    $searchTerm = '%' . trim($searchQuery) . '%';
    for ($i = 1; $i <= $paramCount; $i++) {
        $stmt->bindValue(':search' . $i, $searchTerm, PDO::PARAM_STR);
    }
    
    // Bind exact and start match for relevance sorting
    $stmt->bindValue(':exactMatch', trim($searchQuery), PDO::PARAM_STR);
    $stmt->bindValue(':startMatch', trim($searchQuery) . '%', PDO::PARAM_STR);
    
    if ($category) {
        $stmt->bindParam(':category', $category, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/new-header.php';
?>

<section class="search-results-section" style="padding: 60px 0; min-height: 60vh; background: var(--color-ivory);">
    <div class="container">
        <!-- Search Header -->
        <div style="text-align: center; margin-bottom: 50px;">
            <h1 class="section-title" style="margin-bottom: 15px;">Search Results</h1>
            <?php if (!empty($searchQuery)): ?>
                <p class="section-subtitle">
                    Found <?= count($products); ?> result<?= count($products) !== 1 ? 's' : ''; ?> for 
                    <strong style="color: var(--color-green);">"<?= htmlspecialchars($searchQuery); ?>"</strong>
                </p>
            <?php else: ?>
                <p class="section-subtitle">Enter a search term to find products</p>
            <?php endif; ?>
        </div>

        <!-- Search Form -->
        <div style="max-width: 700px; margin: 0 auto 50px;">
            <form action="<?= base_url('search.php'); ?>" method="get" style="display: flex; gap: 10px; background: white; padding: 10px; border-radius: 50px; box-shadow: 0 5px 20px rgba(0,0,0,0.08);">
                <input 
                    type="text" 
                    name="q" 
                    value="<?= htmlspecialchars($searchQuery); ?>" 
                    placeholder="Search by name, category, SKU, or keywords..." 
                    style="flex: 1; border: none; padding: 15px 25px; font-size: 1rem; outline: none; background: transparent;"
                    required
                    minlength="2"
                    autocomplete="off"
                >
                <button 
                    type="submit" 
                    class="btn btn-primary" 
                    style="border-radius: 50px; padding: 15px 35px;"
                >
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <!-- Results -->
        <?php if (!empty($searchQuery)): ?>
            <?php if (count($products) > 0): ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <article class="product-card">
                            
                            <div class="product-image-wrapper">
                                <a href="<?= base_url('product.php?id=' . $product['id']); ?>">
                                    <img src="<?= asset_url('images/products/' . htmlspecialchars($product['image'])); ?>" 
                                         alt="<?= htmlspecialchars($product['name']); ?>">
                                </a>
                                <div class="trust-overlay">
                                    <i class="fas fa-award" style="color: var(--color-green);"></i> 
                                    <i class="fas fa-flask" style="color: var(--color-green);"></i>
                                </div>
                            </div>
                            
                            <div class="product-details">
                                <span class="product-cat"><?= htmlspecialchars($product['category_name'] ?? 'Premium'); ?></span>
                                <h3 class="product-title">
                                    <a href="<?= base_url('product.php?id=' . $product['id']); ?>" style="color: inherit;">
                                        <?= htmlspecialchars($product['name']); ?>
                                    </a>
                                </h3>
                                <span class="product-origin">Origin: Kashmir Valley</span>
                                <div class="price-row">
                                    <span class="product-price dynamic-price" data-price-inr="<?= htmlspecialchars($product['price']); ?>">
                                        <?= display_price($product['price'], $currentCurrency, $currentCurrencySymbol); ?>
                                    </span>
                                </div>
                                <form action="<?= base_url('includes/cart.php'); ?>" method="post">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="add-btn">Add to Cart</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No Results -->
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.05);">
                    <i class="fas fa-search" style="font-size: 4rem; color: var(--color-gold); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--color-green); margin-bottom: 15px;">No products found</h3>
                    <p style="color: #666; margin-bottom: 30px;">
                        We couldn't find any products matching "<strong><?= htmlspecialchars($searchQuery); ?></strong>"
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <a href="<?= base_url('shop.php'); ?>" class="btn btn-primary">Browse All Products</a>
                        <a href="<?= base_url('index.php'); ?>" class="btn btn-outline" style="border: 1px solid var(--color-green); color: var(--color-green);">Back to Home</a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
