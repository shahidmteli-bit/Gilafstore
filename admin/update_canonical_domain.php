<?php
/**
 * DATABASE UPDATE SCRIPT
 * Updates all stored URLs from www.gilafstore.com to gilafstore.com (non-www canonical)
 * Also updates any http:// references to https://
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db_connection();
$updates = [];
$errors = [];

try {
    // Start transaction
    $db->beginTransaction();
    
    // Helper function to check if column exists
    function columnExists($db, $table, $column) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // 1. Update products table - canonical_override, og_image_url
    if (columnExists($db, 'products', 'canonical_override')) {
        $stmt = $db->prepare("UPDATE products SET canonical_override = REPLACE(canonical_override, 'https://www.gilafstore.com', 'https://gilafstore.com') WHERE canonical_override LIKE '%www.gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Products canonical_override: " . $stmt->rowCount() . " rows updated";
        
        $stmt = $db->prepare("UPDATE products SET canonical_override = REPLACE(canonical_override, 'http://gilafstore.com', 'https://gilafstore.com') WHERE canonical_override LIKE 'http://gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Products canonical_override (http to https): " . $stmt->rowCount() . " rows updated";
    } else {
        $updates[] = "Products canonical_override: Column does not exist (skipped)";
    }
    
    if (columnExists($db, 'products', 'og_image_url')) {
        $stmt = $db->prepare("UPDATE products SET og_image_url = REPLACE(og_image_url, 'https://www.gilafstore.com', 'https://gilafstore.com') WHERE og_image_url LIKE '%www.gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Products og_image_url: " . $stmt->rowCount() . " rows updated";
        
        $stmt = $db->prepare("UPDATE products SET og_image_url = REPLACE(og_image_url, 'http://gilafstore.com', 'https://gilafstore.com') WHERE og_image_url LIKE 'http://gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Products og_image_url (http to https): " . $stmt->rowCount() . " rows updated";
    } else {
        $updates[] = "Products og_image_url: Column does not exist (skipped)";
    }
    
    // 2. Update categories table - canonical_override
    if (columnExists($db, 'categories', 'canonical_override')) {
        $stmt = $db->prepare("UPDATE categories SET canonical_override = REPLACE(canonical_override, 'https://www.gilafstore.com', 'https://gilafstore.com') WHERE canonical_override LIKE '%www.gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Categories canonical_override: " . $stmt->rowCount() . " rows updated";
        
        $stmt = $db->prepare("UPDATE categories SET canonical_override = REPLACE(canonical_override, 'http://gilafstore.com', 'https://gilafstore.com') WHERE canonical_override LIKE 'http://gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Categories canonical_override (http to https): " . $stmt->rowCount() . " rows updated";
    } else {
        $updates[] = "Categories canonical_override: Column does not exist (skipped)";
    }
    
    // 3. Update seo_redirects table - old_path, new_path
    $stmt = $db->prepare("UPDATE seo_redirects SET old_path = REPLACE(old_path, 'https://www.gilafstore.com', 'https://gilafstore.com') WHERE old_path LIKE '%www.gilafstore.com%'");
    $stmt->execute();
    $updates[] = "SEO redirects old_path: " . $stmt->rowCount() . " rows updated";
    
    $stmt = $db->prepare("UPDATE seo_redirects SET new_path = REPLACE(new_path, 'https://www.gilafstore.com', 'https://gilafstore.com') WHERE new_path LIKE '%www.gilafstore.com%'");
    $stmt->execute();
    $updates[] = "SEO redirects new_path: " . $stmt->rowCount() . " rows updated";
    
    $stmt = $db->prepare("UPDATE seo_redirects SET old_path = REPLACE(old_path, 'http://gilafstore.com', 'https://gilafstore.com') WHERE old_path LIKE 'http://gilafstore.com%'");
    $stmt->execute();
    $updates[] = "SEO redirects old_path (http to https): " . $stmt->rowCount() . " rows updated";
    
    $stmt = $db->prepare("UPDATE seo_redirects SET new_path = REPLACE(new_path, 'http://gilafstore.com', 'https://gilafstore.com') WHERE new_path LIKE 'http://gilafstore.com%'");
    $stmt->execute();
    $updates[] = "SEO redirects new_path (http to https): " . $stmt->rowCount() . " rows updated";
    
    // 4. Update settings table if exists
    try {
        $stmt = $db->prepare("UPDATE settings SET value = REPLACE(value, 'https://www.gilafstore.com', 'https://gilafstore.com') WHERE value LIKE '%www.gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Settings: " . $stmt->rowCount() . " rows updated";
        
        $stmt = $db->prepare("UPDATE settings SET value = REPLACE(value, 'http://gilafstore.com', 'https://gilafstore.com') WHERE value LIKE 'http://gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Settings (http to https): " . $stmt->rowCount() . " rows updated";
    } catch (Exception $e) {
        $updates[] = "Settings table: not found or error - " . $e->getMessage();
    }
    
    // 5. Update blog_posts table if exists
    try {
        $stmt = $db->prepare("UPDATE blog_posts SET content = REPLACE(content, 'https://www.gilafstore.com', 'https://gilafstore.com') WHERE content LIKE '%www.gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Blog posts content: " . $stmt->rowCount() . " rows updated";
        
        $stmt = $db->prepare("UPDATE blog_posts SET content = REPLACE(content, 'http://gilafstore.com', 'https://gilafstore.com') WHERE content LIKE '%http://gilafstore.com%'");
        $stmt->execute();
        $updates[] = "Blog posts content (http to https): " . $stmt->rowCount() . " rows updated";
    } catch (Exception $e) {
        $updates[] = "Blog posts table: not found or error - " . $e->getMessage();
    }
    
    // Commit transaction
    $db->commit();
    $success = true;
    
} catch (Exception $e) {
    $db->rollBack();
    $errors[] = "Database update failed: " . $e->getMessage();
    $success = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canonical Domain Update - Gilaf Store</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h1 { color: #1a1a1a; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .status { padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .status.success { background: #d1fae5; border-left: 4px solid #10b981; }
        .status.error { background: #fee2e2; border-left: 4px solid #ef4444; }
        .status h2 { font-size: 18px; margin-bottom: 10px; }
        .status.success h2 { color: #065f46; }
        .status.error h2 { color: #991b1b; }
        .update-list { list-style: none; }
        .update-list li { padding: 8px 0; border-bottom: 1px solid #e5e7eb; color: #374151; }
        .update-list li:last-child { border-bottom: none; }
        .update-list li::before { content: '✓ '; color: #10b981; font-weight: bold; margin-right: 8px; }
        .info-box { background: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; border-radius: 8px; margin-top: 30px; }
        .info-box h3 { color: #075985; margin-bottom: 10px; font-size: 16px; }
        .info-box ul { margin-left: 20px; color: #0c4a6e; }
        .info-box li { margin: 8px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 20px; }
        .btn:hover { background: #5568d3; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: 'Courier New', monospace; color: #1f2937; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔄 Canonical Domain Update</h1>
    <p class="subtitle">Database URL Migration: www.gilafstore.com → gilafstore.com</p>
    
    <?php if ($success): ?>
        <div class="status success">
            <h2>✅ Database Update Completed Successfully</h2>
            <p>All stored URLs have been updated to use the canonical non-www domain.</p>
        </div>
        
        <ul class="update-list">
            <?php foreach ($updates as $update): ?>
                <li><?= htmlspecialchars($update) ?></li>
            <?php endforeach; ?>
        </ul>
        
        <div class="info-box">
            <h3>📋 Next Steps</h3>
            <ul>
                <li><strong>Upload .htaccess</strong> - Ensure the updated .htaccess file is uploaded to the server</li>
                <li><strong>Clear sitemap cache</strong> - Delete files in <code>/cache/</code> folder to regenerate sitemaps</li>
                <li><strong>Test redirects</strong> - Verify all URL variations redirect correctly:
                    <ul style="margin-top: 8px;">
                        <li>http://gilafstore.com → https://gilafstore.com (single 301)</li>
                        <li>http://www.gilafstore.com → https://gilafstore.com (single 301)</li>
                        <li>https://www.gilafstore.com → https://gilafstore.com (single 301)</li>
                    </ul>
                </li>
                <li><strong>Update Google Search Console</strong> - Set preferred domain to non-www</li>
                <li><strong>Update external services</strong> - Update payment gateways, social media, etc.</li>
                <li><strong>Monitor analytics</strong> - Check for any 404 errors or redirect issues</li>
            </ul>
        </div>
        
    <?php else: ?>
        <div class="status error">
            <h2>❌ Database Update Failed</h2>
            <p>An error occurred during the update process. No changes were made.</p>
        </div>
        
        <ul class="update-list">
            <?php foreach ($errors as $error): ?>
                <li style="color: #991b1b;"><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <a href="<?= base_url('admin/dashboard.php') ?>" class="btn">← Back to Dashboard</a>
</div>
</body>
</html>
