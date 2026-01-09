<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Shipping Management';
$adminPage = 'shipping';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_domestic') {
            $enabled = isset($_POST['domestic_enabled']) ? 1 : 0;
            $base_charge = (float)$_POST['domestic_base_charge'];
            $free_threshold = (float)$_POST['domestic_free_threshold'];
            $days_min = (int)$_POST['domestic_days_min'];
            $days_max = (int)$_POST['domestic_days_max'];
            $description = trim($_POST['domestic_description']);
            
            db_query('UPDATE shipping_settings SET enabled = ?, base_charge = ?, free_shipping_threshold = ?, estimated_days_min = ?, estimated_days_max = ?, description = ? WHERE shipping_type = ?',
                [$enabled, $base_charge, $free_threshold, $days_min, $days_max, $description, 'domestic']);
            
            $success = 'Domestic shipping settings updated successfully!';
        } elseif ($_POST['action'] === 'update_worldwide') {
            $enabled = isset($_POST['worldwide_enabled']) ? 1 : 0;
            $base_charge = (float)$_POST['worldwide_base_charge'];
            $free_threshold = (float)$_POST['worldwide_free_threshold'];
            $days_min = (int)$_POST['worldwide_days_min'];
            $days_max = (int)$_POST['worldwide_days_max'];
            $description = trim($_POST['worldwide_description']);
            
            db_query('UPDATE shipping_settings SET enabled = ?, base_charge = ?, free_shipping_threshold = ?, estimated_days_min = ?, estimated_days_max = ?, description = ? WHERE shipping_type = ?',
                [$enabled, $base_charge, $free_threshold, $days_min, $days_max, $description, 'worldwide']);
            
            $success = 'Worldwide shipping settings updated successfully!';
        } elseif ($_POST['action'] === 'update_product_shipping') {
            $product_id = (int)$_POST['product_id'];
            $shipping_type = $_POST['shipping_type'];
            
            db_query('UPDATE products SET shipping_type = ? WHERE id = ?', [$shipping_type, $product_id]);
            
            $success = 'Product shipping type updated successfully!';
        }
    }
}

// Check if shipping system is set up
$shippingSystemExists = false;
$setupError = null;

try {
    // Try to fetch shipping settings
    $domesticShipping = db_fetch('SELECT * FROM shipping_settings WHERE shipping_type = ?', ['domestic']);
    $worldwideShipping = db_fetch('SELECT * FROM shipping_settings WHERE shipping_type = ?', ['worldwide']);
    $products = db_fetch_all('SELECT id, name, shipping_type FROM products ORDER BY name ASC');
    $shippingSystemExists = true;
} catch (PDOException $e) {
    // Table doesn't exist
    if (strpos($e->getMessage(), "Base table or view not found") !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $setupError = "Shipping system not set up. Please run the SQL migration.";
        $domesticShipping = ['enabled' => 1, 'base_charge' => 50, 'free_shipping_threshold' => 500, 'estimated_days_min' => 3, 'estimated_days_max' => 5, 'description' => ''];
        $worldwideShipping = ['enabled' => 1, 'base_charge' => 200, 'free_shipping_threshold' => 2000, 'estimated_days_min' => 7, 'estimated_days_max' => 14, 'description' => ''];
        $products = [];
    } else {
        throw $e;
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.shipping-container {
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

.shipping-section {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 10px;
    margin-bottom: 24px;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.shipping-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.domestic-icon {
    background: #dbeafe;
    color: #1e40af;
}

.worldwide-icon {
    background: #fce7f3;
    color: #be185d;
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

.form-check {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #ffffff;
    border-radius: 8px;
}

.form-check-input {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.form-check-label {
    font-size: 15px;
    font-weight: 500;
    color: #1a1a1a;
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
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
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #c5a028 0%, #b69121 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
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

.info-box {
    background: #e0f2fe;
    border-left: 4px solid #0284c7;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info-box p {
    margin: 0;
    font-size: 14px;
    color: #0c4a6e;
}

.products-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.products-table thead {
    background: #f8f9fa;
}

.products-table th {
    padding: 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    border-bottom: 2px solid #e9ecef;
}

.products-table td {
    padding: 16px;
    border-bottom: 1px solid #f1f3f5;
}

.shipping-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.badge-domestic {
    background: #dbeafe;
    color: #1e40af;
}

.badge-worldwide {
    background: #fce7f3;
    color: #be185d;
}

.badge-both {
    background: #d1fae5;
    color: #059669;
}
</style>

<div class="container mt-4">
    <div class="shipping-container">
        <div class="page-header">
            <h1 class="page-title">Shipping Management</h1>
            <p style="color: #6c757d; margin-top: 8px;">Configure domestic and worldwide shipping settings</p>
        </div>

        <?php if (isset($setupError)): ?>
            <div class="alert alert-danger" style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b;">
                <h5 style="margin-bottom: 16px;"><i class="fas fa-exclamation-triangle me-2"></i>Setup Required</h5>
                <p style="margin-bottom: 16px;"><strong><?= htmlspecialchars($setupError); ?></strong></p>
                <p style="margin-bottom: 16px;">The shipping_settings table needs to be created. Follow these steps:</p>
                <ol style="margin-bottom: 16px;">
                    <li>Open phpMyAdmin: <code>http://localhost/phpmyadmin</code></li>
                    <li>Select database: <strong>ecommerce_db</strong></li>
                    <li>Click the <strong>SQL</strong> tab</li>
                    <li>Copy and paste the SQL from: <code>shipping_system_update.sql</code></li>
                    <li>Click <strong>Go</strong></li>
                    <li>Refresh this page</li>
                </ol>
                <div style="background: #1f2937; color: #f9fafb; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 13px; overflow-x: auto;">
CREATE TABLE `shipping_settings` (<br>
&nbsp;&nbsp;`id` INT AUTO_INCREMENT PRIMARY KEY,<br>
&nbsp;&nbsp;`shipping_type` ENUM('domestic', 'worldwide') NOT NULL,<br>
&nbsp;&nbsp;`enabled` TINYINT(1) DEFAULT 1,<br>
&nbsp;&nbsp;`base_charge` DECIMAL(10,2) DEFAULT 0.00,<br>
&nbsp;&nbsp;`free_shipping_threshold` DECIMAL(10,2) DEFAULT 0.00,<br>
&nbsp;&nbsp;`estimated_days_min` INT DEFAULT 3,<br>
&nbsp;&nbsp;`estimated_days_max` INT DEFAULT 7,<br>
&nbsp;&nbsp;`description` TEXT<br>
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;<br><br>
INSERT INTO `shipping_settings` VALUES<br>
(1, 'domestic', 1, 50.00, 500.00, 3, 5, 'Free shipping on orders above ₹500'),<br>
(2, 'worldwide', 1, 200.00, 2000.00, 7, 14, 'International shipping available');<br><br>
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `shipping_type` ENUM('domestic', 'worldwide', 'both') DEFAULT 'domestic';
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!$shippingSystemExists): ?>
            <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                <i class="fas fa-database" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                <p style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Database Setup Required</p>
                <p>Please run the SQL migration above to enable shipping management.</p>
            </div>
        <?php else: ?>

        <!-- Domestic Shipping -->
        <div class="shipping-section">
            <h3 class="section-title">
                <span class="shipping-icon domestic-icon">🇮🇳</span>
                Domestic Shipping
            </h3>
            
            <form method="post">
                <input type="hidden" name="action" value="update_domestic">
                
                <div class="form-check">
                    <input type="checkbox" name="domestic_enabled" id="domestic_enabled" class="form-check-input" <?= $domesticShipping['enabled'] ? 'checked' : ''; ?>>
                    <label for="domestic_enabled" class="form-check-label">Enable Domestic Shipping</label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Base Shipping Charge (₹)</label>
                        <input type="number" name="domestic_base_charge" class="form-control" value="<?= $domesticShipping['base_charge']; ?>" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Free Shipping Threshold (₹)</label>
                        <input type="number" name="domestic_free_threshold" class="form-control" value="<?= $domesticShipping['free_shipping_threshold']; ?>" step="0.01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Estimated Delivery (Min Days)</label>
                        <input type="number" name="domestic_days_min" class="form-control" value="<?= $domesticShipping['estimated_days_min']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Delivery (Max Days)</label>
                        <input type="number" name="domestic_days_max" class="form-control" value="<?= $domesticShipping['estimated_days_max']; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="domestic_description" class="form-control" rows="3" required><?= htmlspecialchars($domesticShipping['description']); ?></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-save me-2"></i>Update Domestic Shipping
                </button>
            </form>
        </div>

        <!-- Worldwide Shipping -->
        <div class="shipping-section">
            <h3 class="section-title">
                <span class="shipping-icon worldwide-icon">🌍</span>
                Worldwide Shipping
            </h3>
            
            <form method="post">
                <input type="hidden" name="action" value="update_worldwide">
                
                <div class="form-check">
                    <input type="checkbox" name="worldwide_enabled" id="worldwide_enabled" class="form-check-input" <?= $worldwideShipping['enabled'] ? 'checked' : ''; ?>>
                    <label for="worldwide_enabled" class="form-check-label">Enable Worldwide Shipping</label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Base Shipping Charge (₹)</label>
                        <input type="number" name="worldwide_base_charge" class="form-control" value="<?= $worldwideShipping['base_charge']; ?>" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Free Shipping Threshold (₹)</label>
                        <input type="number" name="worldwide_free_threshold" class="form-control" value="<?= $worldwideShipping['free_shipping_threshold']; ?>" step="0.01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Estimated Delivery (Min Days)</label>
                        <input type="number" name="worldwide_days_min" class="form-control" value="<?= $worldwideShipping['estimated_days_min']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Delivery (Max Days)</label>
                        <input type="number" name="worldwide_days_max" class="form-control" value="<?= $worldwideShipping['estimated_days_max']; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="worldwide_description" class="form-control" rows="3" required><?= htmlspecialchars($worldwideShipping['description']); ?></textarea>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-save me-2"></i>Update Worldwide Shipping
                </button>
            </form>
        </div>

        <!-- Product Shipping Types -->
        <div class="shipping-section">
            <h3 class="section-title">
                <span class="shipping-icon" style="background: #d1fae5; color: #059669;">📦</span>
                Product Shipping Types
            </h3>
            
            <div class="info-box">
                <p><i class="fas fa-info-circle me-2"></i>Assign shipping types to products. Badges will be displayed on product pages.</p>
            </div>

            <table class="products-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Current Shipping Type</th>
                        <th>Update Shipping</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['name']); ?></td>
                            <td>
                                <?php if ($product['shipping_type'] === 'domestic'): ?>
                                    <span class="shipping-badge badge-domestic">🇮🇳 Domestic Only</span>
                                <?php elseif ($product['shipping_type'] === 'worldwide'): ?>
                                    <span class="shipping-badge badge-worldwide">🌍 Worldwide</span>
                                <?php else: ?>
                                    <span class="shipping-badge badge-both">🇮🇳 🌍 Both</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline-block;">
                                    <input type="hidden" name="action" value="update_product_shipping">
                                    <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
                                    <select name="shipping_type" class="form-control" style="width: auto; display: inline-block; padding: 8px 12px;" onchange="this.form.submit()">
                                        <option value="domestic" <?= $product['shipping_type'] === 'domestic' ? 'selected' : ''; ?>>Domestic Only</option>
                                        <option value="worldwide" <?= $product['shipping_type'] === 'worldwide' ? 'selected' : ''; ?>>Worldwide</option>
                                        <option value="both" <?= $product['shipping_type'] === 'both' ? 'selected' : ''; ?>>Both</option>
                                    </select>
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

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
