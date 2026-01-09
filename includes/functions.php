<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/analytics_tracker.php';

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function get_categories(): array
{
    return db_fetch_all('SELECT * FROM categories ORDER BY name ASC');
}

function get_featured_categories(int $limit = 6): array
{
    return db_fetch_all('SELECT * FROM categories ORDER BY id DESC LIMIT ' . (int)$limit);
}

function get_trending_products(int $limit = 8): array
{
    return db_fetch_all('SELECT * FROM products ORDER BY id DESC LIMIT ' . (int)$limit);
}

function get_products(array $filters = []): array
{
    $sql = 'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id';
    $conditions = [];
    $params = [];

    if (!empty($filters['category_id'])) {
        $conditions[] = 'p.category_id = ?';
        $params[] = $filters['category_id'];
    }

    if (!empty($filters['search'])) {
        // Comprehensive search across multiple fields with case-insensitive partial matching
        $searchConditions = [
            'LOWER(p.name) LIKE LOWER(?)',
            'LOWER(p.description) LIKE LOWER(?)',
            'LOWER(c.name) LIKE LOWER(?)'
        ];
        
        // Check if SKU column exists and add to search
        try {
            $db = get_db_connection();
            $checkSku = $db->query("SHOW COLUMNS FROM products LIKE 'sku'");
            if ($checkSku->rowCount() > 0) {
                $searchConditions[] = 'LOWER(p.sku) LIKE LOWER(?)';
            }
        } catch (PDOException $e) {
            // SKU column doesn't exist, continue without it
        }
        
        // Check if keywords/tags column exists and add to search
        try {
            $db = get_db_connection();
            $checkKeywords = $db->query("SHOW COLUMNS FROM products LIKE 'keywords'");
            if ($checkKeywords->rowCount() > 0) {
                $searchConditions[] = 'LOWER(p.keywords) LIKE LOWER(?)';
            }
        } catch (PDOException $e) {
            // Keywords column doesn't exist, continue without it
        }
        
        $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        
        // Add search parameter for each search condition
        $searchTerm = '%' . trim($filters['search']) . '%';
        foreach ($searchConditions as $condition) {
            $params[] = $searchTerm;
        }
    }

    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    if (!empty($filters['sort'])) {
        if ($filters['sort'] === 'price-asc') {
            $sql .= ' ORDER BY p.price ASC';
        } elseif ($filters['sort'] === 'price-desc') {
            $sql .= ' ORDER BY p.price DESC';
        } elseif ($filters['sort'] === 'name') {
            $sql .= ' ORDER BY p.name ASC';
        }
    } else {
        $sql .= ' ORDER BY p.id DESC';
    }

    return db_fetch_all($sql, $params);
}

function get_product(int $id): ?array
{
    return db_fetch('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [$id]);
}

function get_product_highlights(int $productId): array
{
    try {
        return db_fetch_all('SELECT * FROM product_highlights WHERE product_id = ? ORDER BY display_order ASC', [$productId]);
    } catch (PDOException $e) {
        // Table doesn't exist yet - return empty array
        if (strpos($e->getMessage(), "Base table or view not found") !== false) {
            return [];
        }
        // Re-throw other exceptions
        throw $e;
    }
}

function get_product_variants(int $productId): array
{
    try {
        return db_fetch_all('SELECT * FROM product_variants WHERE product_id = ? ORDER BY CAST(REPLACE(size, "g", "") AS UNSIGNED) ASC', [$productId]);
    } catch (PDOException $e) {
        return [];
    }
}

function get_site_setting(string $key, string $default = ''): string
{
    try {
        $result = db_fetch('SELECT setting_value FROM site_settings WHERE setting_key = ?', [$key]);
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function get_batch_details_for_product(int $productId): ?array
{
    try {
        return db_fetch('SELECT net_quantity, shelf_life_months, is_lab_tested, is_organic FROM batches WHERE product_id = ? ORDER BY created_at DESC LIMIT 1', [$productId]);
    } catch (PDOException $e) {
        return null;
    }
}

function user_has_purchased_product(int $userId, int $productId): bool
{
    try {
        $result = db_fetch('
            SELECT COUNT(*) as count 
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            WHERE o.user_id = ? AND oi.product_id = ? AND o.status = "completed"
        ', [$userId, $productId]);
        return $result && $result['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function get_product_rating(int $productId): array
{
    try {
        $result = db_fetch('
            SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating
            FROM reviews 
            WHERE product_id = ?
        ', [$productId]);
        
        if ($result && $result['total_reviews'] > 0) {
            return [
                'rating' => round($result['average_rating'], 1),
                'count' => (int)$result['total_reviews'],
                'is_actual' => true
            ];
        }
        
        // Return default rating if no reviews
        return [
            'rating' => 4.5,
            'count' => 0,
            'is_actual' => false
        ];
    } catch (PDOException $e) {
        return [
            'rating' => 4.5,
            'count' => 0,
            'is_actual' => false
        ];
    }
}

function get_shipping_badge_html(string $shippingType): string
{
    $badges = '';
    
    if ($shippingType === 'domestic') {
        $badges = '<span class="shipping-badge domestic-badge" title="Domestic Shipping Available">🇮🇳 Domestic</span>';
    } elseif ($shippingType === 'worldwide') {
        $badges = '<span class="shipping-badge worldwide-badge" title="Worldwide Shipping Available">🌍 Worldwide</span>';
    } elseif ($shippingType === 'both') {
        $badges = '<span class="shipping-badge domestic-badge" title="Domestic Shipping Available">🇮🇳 Domestic</span> <span class="shipping-badge worldwide-badge" title="Worldwide Shipping Available">🌍 Worldwide</span>';
    }
    
    return $badges;
}

function get_related_products(int $categoryId, int $excludeId, int $limit = 4): array
{
    return db_fetch_all('SELECT * FROM products WHERE category_id = ? AND id != ? ORDER BY id DESC LIMIT ' . (int)$limit, [
        $categoryId,
        $excludeId,
    ]);
}

function get_reviews_for_product(int $productId): array
{
    try {
        return db_fetch_all('SELECT name, rating, comment, created_at FROM product_reviews WHERE product_id = ? ORDER BY created_at DESC', [$productId]);
    } catch (Throwable $exception) {
        return [];
    }
}

function cart_items(): array
{
    if (empty($_SESSION['cart'])) {
        return [];
    }

    $productIds = array_keys($_SESSION['cart']);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $products = db_fetch_all("SELECT * FROM products WHERE id IN ($placeholders)", $productIds);

    $items = [];
    foreach ($products as $product) {
        // Fetch stock quantity from database based on product_id and batch_code
        $stockQuery = "SELECT stock FROM products WHERE id = ?";
        $stockResult = db_fetch($stockQuery, [$product['id']]);
        $stockQuantity = $stockResult ? (int)$stockResult['stock'] : 0;
        
        $items[] = [
            'id' => $product['id'],
            'product_id' => $product['id'],
            'name' => $product['name'],
            'price' => (float)$product['price'],
            'image' => $product['image'],
            'quantity' => (int)$_SESSION['cart'][$product['id']],
            'gst_rate' => isset($product['gst_rate']) ? (float)$product['gst_rate'] : 5.00,
            'weight' => $product['weight'] ?? 'N/A',
            'batch_code' => $product['batch_code'] ?? 'N/A',
            'stock_quantity' => $stockQuantity,
        ];
    }

    return $items;
}

function cart_add(int $productId, int $quantity = 1): void
{
    $product = get_product($productId);
    if (!$product) {
        redirect_with_message('/shop.php', 'Product not found', 'danger');
    }

    if (!isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'],
            'quantity' => 0,
        ];
    }

    $_SESSION['cart'][$productId]['quantity'] += $quantity;
    redirect_with_message('/cart.php', 'Product added to cart');
}

function cart_update(int $productId, int $quantity): void
{
    if (isset($_SESSION['cart'][$productId])) {
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId]['quantity'] = $quantity;
        }
    }
}

function cart_remove(int $productId): void
{
    if (isset($_SESSION['cart'][$productId])) {
        unset($_SESSION['cart'][$productId]);
    }
}

function cart_total(): float
{
    $total = 0.0;
    foreach (cart_items() as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

function cart_subtotal(): float
{
    $subtotal = 0.0;
    foreach (cart_items() as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    return $subtotal;
}

function cart_gst(): float
{
    $gst = 0.0;
    foreach (cart_items() as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $gstRate = $item['gst_rate'] ?? 5.00; // Default 5% if not set
        $gst += ($itemTotal * $gstRate) / 100;
    }
    return $gst;
}

function cart_total_with_gst(): float
{
    return cart_subtotal() + cart_gst();
}

function place_order(int $userId, array $cart): int
{
    global $pdo;
    $pdo->beginTransaction();

    try {
        $total = cart_total();
        db_query('INSERT INTO orders (user_id, total_amount, status, created_at) VALUES (?, ?, ?, NOW())', [
            $userId,
            $total,
            'pending',
        ]);

        $orderId = (int)$pdo->lastInsertId();

        foreach ($cart as $item) {
            db_query('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)', [
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
            ]);

            db_query('UPDATE products SET stock = stock - ? WHERE id = ?', [
                $item['quantity'],
                $item['product_id'],
            ]);
        }

        $pdo->commit();
        $_SESSION['cart'] = [];
        return $orderId;
    } catch (Exception $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function get_user_orders(int $userId): array
{
    return db_fetch_all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
}

function get_order_with_items(int $orderId): ?array
{
    $order = db_fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        return null;
    }

    $items = db_fetch_all('SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$orderId]);
    $order['items'] = $items;
    return $order;
}

function get_user(int $userId): ?array
{
    // Check if phone column exists
    $db = get_db_connection();
    $hasPhoneColumn = false;
    try {
        $checkPhone = $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
        $hasPhoneColumn = $checkPhone->rowCount() > 0;
    } catch (PDOException $e) {
        // Column doesn't exist
    }
    
    if ($hasPhoneColumn) {
        return db_fetch('SELECT id, name, email, phone FROM users WHERE id = ?', [$userId]);
    } else {
        return db_fetch('SELECT id, name, email FROM users WHERE id = ?', [$userId]);
    }
}

function update_user_profile(int $userId, string $name, string $email, ?string $password = null, ?string $phone = null): bool
{
    // Check if email is already in use by another user
    $existing = db_fetch('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $userId]);
    if ($existing) {
        return false;
    }

    // Check if phone column exists
    $db = get_db_connection();
    $hasPhoneColumn = false;
    try {
        $checkPhone = $db->query("SHOW COLUMNS FROM users LIKE 'phone'");
        $hasPhoneColumn = $checkPhone->rowCount() > 0;
    } catch (PDOException $e) {
        // Column doesn't exist
    }

    // Build update query based on available columns and provided data
    if ($password && $phone && $hasPhoneColumn) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        db_query('UPDATE users SET name = ?, email = ?, password = ?, phone = ? WHERE id = ?', 
            [$name, $email, $hashed, $phone, $userId]);
    } elseif ($password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        db_query('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?', 
            [$name, $email, $hashed, $userId]);
    } elseif ($phone && $hasPhoneColumn) {
        db_query('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?', 
            [$name, $email, $phone, $userId]);
    } else {
        db_query('UPDATE users SET name = ?, email = ? WHERE id = ?', 
            [$name, $email, $userId]);
    }

    // Update session data
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;
    if ($phone && $hasPhoneColumn) {
        $_SESSION['user']['phone'] = $phone;
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    return true;
}

function admin_get_stats(): array
{
    $totalUsers = (int)db_fetch('SELECT COUNT(*) AS total FROM users WHERE COALESCE(is_admin, 0) = 0')['total'];
    $totalProducts = (int)db_fetch('SELECT COUNT(*) AS total FROM products')['total'];
    $totalOrders = (int)db_fetch('SELECT COUNT(*) AS total FROM orders')['total'];
    
    // Try to fetch revenue with proper column handling
    try {
        $sales = db_fetch('SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM orders WHERE order_status IN ("shipped", "delivered")');
    } catch (PDOException $e) {
        // If order_status doesn't exist, use status column
        $sales = db_fetch('SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM orders WHERE status IN ("shipped", "delivered")');
    }

    // Calculate payment received (completed/paid orders)
    try {
        $payments = db_fetch('SELECT COALESCE(SUM(total_amount), 0) AS payment_received FROM orders WHERE order_status IN ("delivered", "shipped")');
    } catch (PDOException $e) {
        $payments = db_fetch('SELECT COALESCE(SUM(total_amount), 0) AS payment_received FROM orders WHERE status IN ("delivered", "shipped")');
    }

    return [
        'users' => $totalUsers,
        'products' => $totalProducts,
        'orders' => $totalOrders,
        'revenue' => (float)$sales['revenue'],
        'payment_received' => (float)$payments['payment_received'],
    ];
}

function admin_get_recent_orders(int $limit = 5): array
{
    return db_fetch_all('SELECT o.*, u.name AS customer, COALESCE(o.order_status, "pending") as status FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT ' . (int)$limit);
}

function admin_get_products(): array
{
    try {
        // Try to fetch with new columns
        return db_fetch_all('SELECT p.*, c.name AS category_name, c.category_code FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC');
    } catch (PDOException $e) {
        // If category_code doesn't exist, fetch without it
        if (strpos($e->getMessage(), "Unknown column 'c.category_code'") !== false) {
            return db_fetch_all('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC');
        }
        throw $e;
    }
}

function admin_create_product(array $data): int
{
    global $pdo;
    
    // Check if new columns exist
    $hasExtendedFields = false;
    $hasLabReportFields = false;
    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'net_weight'");
        $hasExtendedFields = $stmt->rowCount() > 0;
        
        $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'has_lab_report'");
        $hasLabReportFields = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // Columns don't exist yet
    }
    
    if ($hasExtendedFields && $hasLabReportFields) {
        // Full schema with all new fields
        db_query('INSERT INTO products (name, description, category_id, price, stock, image, net_weight, bullet_points, has_lab_report, lab_report_file, lab_report_uploaded_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
            $data['name'],
            $data['description'],
            $data['category_id'],
            $data['price'],
            $data['stock'],
            $data['image'],
            $data['net_weight'] ?? null,
            $data['bullet_points'] ?? null,
            $data['has_lab_report'] ?? 0,
            $data['lab_report_file'] ?? null,
            !empty($data['lab_report_file']) ? date('Y-m-d H:i:s') : null,
        ]);
    } elseif ($hasExtendedFields) {
        // Schema with net_weight and bullet_points only
        db_query('INSERT INTO products (name, description, category_id, price, stock, image, net_weight, bullet_points, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())', [
            $data['name'],
            $data['description'],
            $data['category_id'],
            $data['price'],
            $data['stock'],
            $data['image'],
            $data['net_weight'] ?? null,
            $data['bullet_points'] ?? null,
        ]);
    } else {
        // Old schema without new columns
        db_query('INSERT INTO products (name, description, category_id, price, stock, image, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [
            $data['name'],
            $data['description'],
            $data['category_id'],
            $data['price'],
            $data['stock'],
            $data['image'],
        ]);
    }

    return (int)$pdo->lastInsertId();
}

function admin_update_product(int $productId, array $data): void
{
    $fields = ['name = ?', 'description = ?', 'category_id = ?', 'price = ?', 'stock = ?'];
    $params = [$data['name'], $data['description'], $data['category_id'], $data['price'], $data['stock']];

    if (!empty($data['ean'])) {
        $fields[] = 'ean = ?';
        $params[] = $data['ean'];
    }

    if (!empty($data['image'])) {
        $fields[] = 'image = ?';
        $params[] = $data['image'];
    }

    $params[] = $productId;
    db_query('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
}

function admin_delete_product(int $productId): void
{
    db_query('DELETE FROM products WHERE id = ?', [$productId]);
}

function admin_get_categories(): array
{
    try {
        // Try to fetch with category_code column
        return db_fetch_all('SELECT id, name, category_code FROM categories ORDER BY name ASC');
    } catch (PDOException $e) {
        // If category_code doesn't exist, fetch without it
        if (strpos($e->getMessage(), "Unknown column 'category_code'") !== false) {
            return db_fetch_all('SELECT id, name FROM categories ORDER BY name ASC');
        }
        throw $e;
    }
}

function admin_create_category(string $name, string $categoryCode = ''): void
{
    // Auto-generate category code if not provided
    if (empty($categoryCode)) {
        // Find first capital letter
        preg_match('/[A-Z]/', $name, $matches);
        $categoryCode = $matches[0] ?? strtoupper(substr($name, 0, 1));
    }
    
    try {
        db_query('INSERT INTO categories (name, category_code) VALUES (?, ?)', [$name, $categoryCode]);
    } catch (PDOException $e) {
        // If category_code column doesn't exist, insert without it
        if (strpos($e->getMessage(), "Unknown column 'category_code'") !== false) {
            db_query('INSERT INTO categories (name) VALUES (?)', [$name]);
        } else {
            throw $e;
        }
    }
}

function admin_update_category(int $categoryId, string $name, string $categoryCode = ''): void
{
    // Auto-generate category code if not provided
    if (empty($categoryCode)) {
        // Find first capital letter
        preg_match('/[A-Z]/', $name, $matches);
        $categoryCode = $matches[0] ?? strtoupper(substr($name, 0, 1));
    }
    
    try {
        db_query('UPDATE categories SET name = ?, category_code = ? WHERE id = ?', [$name, $categoryCode, $categoryId]);
    } catch (PDOException $e) {
        // If category_code column doesn't exist, update without it
        if (strpos($e->getMessage(), "Unknown column 'category_code'") !== false) {
            db_query('UPDATE categories SET name = ? WHERE id = ?', [$name, $categoryId]);
        } else {
            throw $e;
        }
    }
}

function admin_delete_category(int $categoryId): void
{
    db_query('DELETE FROM categories WHERE id = ?', [$categoryId]);
}

function admin_get_orders(): array
{
    return db_fetch_all('SELECT o.*, u.name AS customer FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC');
}

function admin_update_order_status(int $orderId, string $status): void
{
    db_query('UPDATE orders SET order_status = ? WHERE id = ?', [$status, $orderId]);
}

function admin_get_users(): array
{
    return db_fetch_all('SELECT id, name, email, COALESCE(is_admin, 0) AS is_admin, created_at FROM users ORDER BY created_at DESC');
}

function admin_delete_user(int $userId): void
{
    db_query('DELETE FROM users WHERE id = ?', [$userId]);
}

function get_product_sections(int $productId): array
{
    try {
        return db_fetch_all('SELECT * FROM product_sections WHERE product_id = ? AND is_active = 1 ORDER BY display_order ASC', [$productId]);
    } catch (PDOException $e) {
        return [];
    }
}

function get_product_section_by_type(int $productId, string $type): ?array
{
    try {
        return db_fetch('SELECT * FROM product_sections WHERE product_id = ? AND section_type = ? AND is_active = 1', [$productId, $type]);
    } catch (PDOException $e) {
        return null;
    }
}

function get_product_reviews(int $productId, bool $approvedOnly = true): array
{
    try {
        $sql = 'SELECT pr.*, u.name as user_name FROM product_reviews pr 
                LEFT JOIN users u ON pr.user_id = u.id 
                WHERE pr.product_id = ?';
        if ($approvedOnly) {
            $sql .= ' AND pr.is_approved = 1';
        }
        $sql .= ' ORDER BY pr.created_at DESC';
        return db_fetch_all($sql, [$productId]);
    } catch (PDOException $e) {
        return [];
    }
}

function get_product_average_rating(int $productId): array
{
    try {
        $result = db_fetch('SELECT AVG(rating) as average, COUNT(*) as total FROM product_reviews WHERE product_id = ? AND is_approved = 1', [$productId]);
        return [
            'average' => $result ? round((float)$result['average'], 1) : 0,
            'total' => $result ? (int)$result['total'] : 0
        ];
    } catch (PDOException $e) {
        return ['average' => 0, 'total' => 0];
    }
}

/**
 * Get active discount for a product
 * Returns discount data if active and within date range, null otherwise
 */
function get_product_discount(int $productId): ?array
{
    try {
        $sql = 'SELECT * FROM product_discounts 
                WHERE product_id = ? 
                AND is_active = 1 
                AND start_date <= NOW() 
                AND end_date >= NOW() 
                ORDER BY discount_value DESC 
                LIMIT 1';
        return db_fetch($sql, [$productId]);
    } catch (PDOException $e) {
        // Table doesn't exist or other error - return null
        return null;
    }
}

/**
 * Calculate discounted price for a product
 * Returns array with original_price, discounted_price, discount_amount, discount_percentage
 */
function calculate_discount_price(float $originalPrice, ?array $discount): array
{
    $result = [
        'original_price' => $originalPrice,
        'discounted_price' => $originalPrice,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'has_discount' => false
    ];
    
    if (!$discount || $originalPrice <= 0) {
        return $result;
    }
    
    $discountValue = (float)$discount['discount_value'];
    
    if ($discount['discount_type'] === 'percentage') {
        // Percentage discount (e.g., 15% off)
        $discountValue = max(0, min(100, $discountValue)); // Clamp between 0-100
        $discountAmount = ($originalPrice * $discountValue) / 100;
        $result['discount_percentage'] = $discountValue;
    } else {
        // Flat amount discount (e.g., $20 off)
        $discountAmount = min($discountValue, $originalPrice); // Cannot exceed original price
        $result['discount_percentage'] = ($discountAmount / $originalPrice) * 100;
    }
    
    $result['discount_amount'] = round($discountAmount, 2);
    $result['discounted_price'] = max(0, round($originalPrice - $discountAmount, 2));
    $result['has_discount'] = $result['discount_amount'] > 0;
    
    return $result;
}

/**
 * Enrich products array with discount information
 * Adds discount data to each product if available
 */
function enrich_products_with_discounts(array $products): array
{
    foreach ($products as &$product) {
        $discount = get_product_discount((int)$product['id']);
        $priceInfo = calculate_discount_price((float)$product['price'], $discount);
        
        $product['discount'] = $discount;
        $product['original_price'] = $priceInfo['original_price'];
        $product['discounted_price'] = $priceInfo['discounted_price'];
        $product['discount_amount'] = $priceInfo['discount_amount'];
        $product['discount_percentage'] = $priceInfo['discount_percentage'];
        $product['has_discount'] = $priceInfo['has_discount'];
    }
    
    return $products;
}
