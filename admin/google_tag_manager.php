<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/db_connect.php';

// Check if google_tags table exists
$table_check = $conn->query("SHOW TABLES LIKE 'google_tags'");
if ($table_check->num_rows === 0) {
    // Create table if it doesn't exist
    $create_table = "
        CREATE TABLE IF NOT EXISTS `google_tags` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL DEFAULT 'Google Tag',
          `tag_script` text NOT NULL,
          `enabled` tinyint(1) NOT NULL DEFAULT 0,
          `page_conditions` json DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $conn->query($create_table);
    
    // Insert default record
    $default_conditions = json_encode(['pages' => ['all'], 'custom_urls' => []]);
    $conn->query("INSERT INTO `google_tags` (`name`, `tag_script`, `enabled`, `page_conditions`) VALUES ('Google Analytics', '', 0, '$default_conditions')");
}

require_once '../includes/admin_header.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag_script = $_POST['tag_script'] ?? '';
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $pages = $_POST['pages'] ?? [];
    $custom_urls = $_POST['custom_urls'] ?? [];
    
    // Sanitize script
    $tag_script = trim($tag_script);
    
    // Prepare page conditions
    $page_conditions = [
        'pages' => $pages,
        'custom_urls' => array_filter(array_map('trim', explode("\n", $custom_urls)))
    ];
    
    // Update database
    $stmt = $conn->prepare("
        UPDATE google_tags 
        SET tag_script = ?, enabled = ?, page_conditions = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = 1
    ");
    $stmt->bind_param('sis', $tag_script, $enabled, json_encode($page_conditions));
    $stmt->execute();
    
    $success = "Google Tag settings updated successfully!";
    
    // Clear any cache if needed
    if (file_exists('../cache/google_tag_cache.json')) {
        unlink('../cache/google_tag_cache.json');
    }
}

// Get current settings
$result = $conn->query("SELECT * FROM google_tags WHERE id = 1");
$tag = $result->fetch_assoc();
$page_conditions = json_decode($tag['page_conditions'] ?? '{}', true) ?: ['pages' => [], 'custom_urls' => []];
?>

<div class="admin-content">
    <div class="content-header">
        <h1>Google Tag Manager</h1>
        <p>Manage Google Analytics, Ads, and other tracking tags</p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Tag Configuration</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="enabled" class="form-label">
                        <input type="checkbox" name="enabled" id="enabled" 
                               <?php echo $tag['enabled'] ? 'checked' : ''; ?>>
                        Enable Google Tag
                    </label>
                    <small class="text-muted">Toggle to enable/disable the tracking tag</small>
                </div>

                <div class="form-group">
                    <label for="tag_script" class="form-label">Google Tag Script</label>
                    <textarea name="tag_script" id="tag_script" rows="12" class="form-control" 
                              placeholder="Paste your complete Google Tag (gtag.js) script here..."><?php 
                        echo htmlspecialchars($tag['tag_script'] ?? ''); 
                    ?></textarea>
                    <small class="text-muted">
                        Paste the full Google Tag script including &lt;script&gt; tags. 
                        Example:<br>
                        &lt;!-- Google tag (gtag.js) --&gt;<br>
                        &lt;script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"&gt;&lt;/script&gt;<br>
                        &lt;script&gt;<br>
                        &nbsp;&nbsp;window.dataLayer = window.dataLayer || [];<br>
                        &nbsp;&nbsp;function gtag(){dataLayer.push(arguments);}<br>
                        &nbsp;&nbsp;gtag('js', new Date());<br>
                        &nbsp;&nbsp;gtag('config', 'GA_MEASUREMENT_ID');<br>
                        &lt;/script&gt;
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Where should this tag appear?</label>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="pages[]" value="all" 
                                           <?php echo in_array('all', $page_conditions['pages'] ?? []) ? 'checked' : ''; ?>>
                                    All Pages
                                </label>
                                
                                <label>
                                    <input type="checkbox" name="pages[]" value="homepage" 
                                           <?php echo in_array('homepage', $page_conditions['pages'] ?? []) ? 'checked' : ''; ?>>
                                    Homepage
                                </label>
                                
                                <label>
                                    <input type="checkbox" name="pages[]" value="product" 
                                           <?php echo in_array('product', $page_conditions['pages'] ?? []) ? 'checked' : ''; ?>>
                                    Product Pages
                                </label>
                                
                                <label>
                                    <input type="checkbox" name="pages[]" value="cart" 
                                           <?php echo in_array('cart', $page_conditions['pages'] ?? []) ? 'checked' : ''; ?>>
                                    Cart Page
                                </label>
                                
                                <label>
                                    <input type="checkbox" name="pages[]" value="checkout" 
                                           <?php echo in_array('checkout', $page_conditions['pages'] ?? []) ? 'checked' : ''; ?>>
                                    Checkout Page
                                </label>
                                
                                <label>
                                    <input type="checkbox" name="pages[]" value="thank_you" 
                                           <?php echo in_array('thank_you', $page_conditions['pages'] ?? []) ? 'checked' : ''; ?>>
                                    Thank You Page
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="custom_urls">Custom URLs (one per line)</label>
                            <textarea name="custom_urls" id="custom_urls" rows="6" class="form-control" 
                                      placeholder="Enter custom URLs where tag should appear...&#10;/blog&#10;/offers&#10;/contact"><?php 
                                echo htmlspecialchars(implode("\n", $page_conditions['custom_urls'] ?? [])); 
                            ?></textarea>
                            <small class="text-muted">
                                Enter relative URLs (without domain). Use partial matches like "/blog" to match all blog pages.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <a href="google_tag_test.php" class="btn btn-info" target="_blank">
                        <i class="fas fa-play-circle"></i> Test Tag
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Current Status</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $tag['enabled'] ? 'badge-success' : 'badge-secondary'; ?>">
                            <?php echo $tag['enabled'] ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </p>
                    <p><strong>Last Updated:</strong> <?php echo date('M j, Y, g:i a', strtotime($tag['updated_at'])); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Active Pages:</strong></p>
                    <ul class="list-unstyled">
                        <?php 
                        $active_pages = $page_conditions['pages'] ?? [];
                        if (in_array('all', $active_pages)) {
                            echo '<li><span class="badge badge-info">All Pages</span></li>';
                        } else {
                            foreach ($active_pages as $page) {
                                echo '<li><span class="badge badge-info">' . ucfirst(str_replace('_', ' ', $page)) . '</span></li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.checkbox-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: normal;
    margin-bottom: 0;
}

.checkbox-group input[type="checkbox"] {
    margin: 0;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: bold;
    border-radius: 4px;
}

.badge-success {
    color: #fff;
    background-color: #28a745;
}

.badge-secondary {
    color: #fff;
    background-color: #6c757d;
}

.badge-info {
    color: #fff;
    background-color: #17a2b8;
}
</style>

<?php require_once '../includes/admin_footer.php'; ?>
