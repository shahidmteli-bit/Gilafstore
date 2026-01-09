<?php
/**
 * Content Pages Setup Script
 * Run this once to set up database tables and upload directories
 */

session_start();
require_once __DIR__ . '/includes/db_connect.php';

// Check if running from command line or admin access
$isAdmin = isset($_SESSION['user']['is_admin']) && $_SESSION['user']['is_admin'];
$isCLI = php_sapi_name() === 'cli';

if (!$isAdmin && !$isCLI) {
    die('Access denied. Please run from command line or login as admin.');
}

echo "=== Content Pages Setup Script ===\n\n";

// Create upload directories
$uploadDirs = [
    __DIR__ . '/uploads/team',
    __DIR__ . '/uploads/values',
    __DIR__ . '/uploads/blog',
    __DIR__ . '/uploads/about-us'
];

echo "Creating upload directories...\n";
foreach ($uploadDirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Created: $dir\n";
        } else {
            echo "✗ Failed to create: $dir\n";
        }
    } else {
        echo "✓ Already exists: $dir\n";
    }
}

echo "\n";

// Read and execute SQL schema
echo "Setting up database tables...\n";
$sqlFile = __DIR__ . '/includes/content_pages_schema.sql';

if (file_exists($sqlFile)) {
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $success = 0;
    $failed = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            if ($conn->query($statement)) {
                $success++;
            } else {
                $failed++;
                echo "✗ Error: " . $conn->error . "\n";
            }
        } catch (Exception $e) {
            $failed++;
            echo "✗ Exception: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ Executed $success SQL statements successfully\n";
    if ($failed > 0) {
        echo "✗ $failed statements failed\n";
    }
} else {
    echo "✗ SQL file not found: $sqlFile\n";
}

echo "\n";

// Create .htaccess for uploads directory
$htaccessContent = "# Protect upload directory\n";
$htaccessContent .= "Options -Indexes\n";
$htaccessContent .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|webp|svg)$\">\n";
$htaccessContent .= "    Order Allow,Deny\n";
$htaccessContent .= "    Allow from all\n";
$htaccessContent .= "</FilesMatch>\n";

$htaccessPath = __DIR__ . '/uploads/.htaccess';
if (file_put_contents($htaccessPath, $htaccessContent)) {
    echo "✓ Created .htaccess for uploads directory\n";
} else {
    echo "✗ Failed to create .htaccess\n";
}

echo "\n";

// Verify setup
echo "Verifying setup...\n";

$tables = [
    'about_us',
    'team_members',
    'milestones',
    'company_values',
    'blog_categories',
    'blog_posts',
    'blog_tags',
    'blog_post_tags'
];

$allTablesExist = true;
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table exists: $table\n";
    } else {
        echo "✗ Table missing: $table\n";
        $allTablesExist = false;
    }
}

echo "\n";

// Summary
echo "=== Setup Summary ===\n";
if ($allTablesExist) {
    echo "✓ All database tables created successfully\n";
} else {
    echo "✗ Some database tables are missing\n";
}

echo "✓ Upload directories created\n";
echo "✓ Security files created\n";

echo "\n=== Next Steps ===\n";
echo "1. Access admin panel: /admin/\n";
echo "2. Navigate to 'Content Pages' section\n";
echo "3. Add your About Us content\n";
echo "4. Add team members (Founder, CEO)\n";
echo "5. Create company values\n";
echo "6. Publish your first blog post\n";
echo "7. Visit frontend pages:\n";
echo "   - /about-us.php\n";
echo "   - /our-values.php\n";
echo "   - /blogs.php\n";

echo "\n✓ Setup complete!\n";

if (!$isCLI) {
    echo '<br><br><a href="admin/">Go to Admin Panel</a>';
}
?>
