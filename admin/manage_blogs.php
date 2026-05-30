<?php
/**
 * Admin Blog Management
 * Professional blog system with product linking, SEO, and AI features
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

require_admin();

$pageTitle = 'Blog Management — Gilaf Store';
$adminPage = 'blog_manage';

$db = get_db_connection();

// Auto-create tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS blog_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            description TEXT,
            icon VARCHAR(50) DEFAULT 'fa-folder',
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS blogs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(280) NOT NULL UNIQUE,
            excerpt TEXT,
            content LONGTEXT,
            featured_image VARCHAR(500),
            category_id INT,
            author_name VARCHAR(100) DEFAULT 'Gilaf Store',
            author_image VARCHAR(500),
            meta_title VARCHAR(70),
            meta_description VARCHAR(160),
            meta_keywords VARCHAR(255),
            canonical_url VARCHAR(500),
            og_image VARCHAR(500),
            views INT DEFAULT 0,
            reading_time INT DEFAULT 5,
            status ENUM('draft', 'published', 'scheduled') DEFAULT 'draft',
            publish_date DATETIME,
            is_featured TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS blog_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blog_id INT NOT NULL,
            product_id INT NOT NULL,
            display_order INT DEFAULT 0,
            display_type ENUM('inline', 'sidebar', 'bottom') DEFAULT 'bottom',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_blog_product (blog_id, product_id),
            INDEX idx_blog (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS blog_faqs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blog_id INT NOT NULL,
            question VARCHAR(500) NOT NULL,
            answer TEXT NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_blog (blog_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default categories if empty
    $catCount = $db->query("SELECT COUNT(*) FROM blog_categories")->fetchColumn();
    if ($catCount == 0) {
        $db->exec("
            INSERT INTO blog_categories (name, slug, description, icon, sort_order) VALUES
            ('Health Benefits', 'health-benefits', 'Health and wellness benefits of our products', 'fa-heart', 1),
            ('Recipes', 'recipes', 'Delicious recipes using our products', 'fa-utensils', 2),
            ('Product Stories', 'product-stories', 'Stories behind our authentic products', 'fa-book-open', 3),
            ('Kashmir Culture', 'kashmir-culture', 'Explore the rich culture of Kashmir', 'fa-mountain', 4),
            ('Buying Guides', 'buying-guides', 'How to choose the best products', 'fa-shopping-cart', 5),
            ('Tea & Kahwa Guides', 'tea-guides', 'Everything about traditional teas', 'fa-mug-hot', 6),
            ('Honey Benefits', 'honey-benefits', 'Benefits and uses of pure honey', 'fa-jar', 7),
            ('Dry Fruit Nutrition', 'dry-fruit-nutrition', 'Nutritional benefits of dry fruits', 'fa-seedling', 8)
        ");
    }
} catch (PDOException $e) {
    // Tables might already exist
}

// Fetch blogs with category info
$blogs = $db->query("
    SELECT b.*, c.name as category_name, c.icon as category_icon,
           (SELECT COUNT(*) FROM blog_products WHERE blog_id = b.id) as product_count
    FROM blogs b
    LEFT JOIN blog_categories c ON c.id = b.category_id
    ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter/modal
$categories = $db->query("SELECT * FROM blog_categories WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch products for linking
$products = $db->query("SELECT id, name, image FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Blog Management';
include __DIR__ . '/admin_header.php';
?>

<style>
/* Professional Blog Admin Styles */
.blog-admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}
.blog-admin-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1a3c34;
    margin: 0;
}
.blog-stats {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.blog-stat {
    background: white;
    padding: 15px 25px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    text-align: center;
    min-width: 120px;
}
.blog-stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1a3c34;
}
.blog-stat-label {
    font-size: 0.85rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Blog Table */
.blog-table-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}
.blog-table-header {
    padding: 20px 25px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}
.blog-search {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.blog-search input, .blog-search select {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 0.9rem;
}
.blog-search input {
    min-width: 250px;
}

.blog-table {
    width: 100%;
    border-collapse: collapse;
}
.blog-table th {
    background: #f8f9fa;
    padding: 15px 20px;
    text-align: left;
    font-weight: 600;
    color: #333;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.blog-table td {
    padding: 18px 20px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}
.blog-table tr:hover {
    background: #fafafa;
}

.blog-thumb {
    width: 80px;
    height: 55px;
    border-radius: 8px;
    object-fit: cover;
    background: #f0f0f0;
}
.blog-title-cell {
    max-width: 300px;
}
.blog-title-cell h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1a3c34;
    margin: 0 0 5px 0;
    line-height: 1.3;
}
.blog-title-cell p {
    font-size: 0.85rem;
    color: #888;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.blog-category-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(197,160,89,0.1);
    color: #c5a059;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.blog-status {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}
.blog-status.published { background: #d4edda; color: #155724; }
.blog-status.draft { background: #fff3cd; color: #856404; }
.blog-status.scheduled { background: #cce5ff; color: #004085; }

.seo-score {
    display: flex;
    align-items: center;
    gap: 8px;
}
.seo-score-bar {
    width: 60px;
    height: 6px;
    background: #eee;
    border-radius: 3px;
    overflow: hidden;
}
.seo-score-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s;
}
.seo-score-fill.good { background: #28a745; }
.seo-score-fill.medium { background: #ffc107; }
.seo-score-fill.poor { background: #dc3545; }

.blog-actions {
    display: flex;
    gap: 8px;
}
.blog-actions .btn {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.blog-actions .btn-edit { background: #e3f2fd; color: #1976d2; }
.blog-actions .btn-edit:hover { background: #1976d2; color: white; }
.blog-actions .btn-view { background: #e8f5e9; color: #388e3c; }
.blog-actions .btn-view:hover { background: #388e3c; color: white; }
.blog-actions .btn-delete { background: #ffebee; color: #d32f2f; }
.blog-actions .btn-delete:hover { background: #d32f2f; color: white; }

.product-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: #e8f5e9;
    color: #2e7d32;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Create Blog Button */
.btn-create-blog {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}
.btn-create-blog:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(26,60,52,0.3);
    color: white;
}

/* Empty State */
.empty-blogs {
    text-align: center;
    padding: 80px 40px;
}
.empty-blogs i {
    font-size: 5rem;
    color: #ddd;
    margin-bottom: 20px;
}
.empty-blogs h3 {
    font-size: 1.5rem;
    color: #333;
    margin-bottom: 10px;
}
.empty-blogs p {
    color: #888;
    margin-bottom: 30px;
}

/* Responsive */
@media (max-width: 992px) {
    .blog-table-wrapper { overflow-x: auto; }
    .blog-table { min-width: 900px; }
}
@media (max-width: 768px) {
    .blog-admin-header { flex-direction: column; align-items: flex-start; }
    .blog-stats { width: 100%; }
    .blog-stat { flex: 1; min-width: 100px; }
}
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="blog-admin-header">
        <div>
            <h1><i class="fas fa-blog me-2"></i>Blog Management</h1>
            <p class="text-muted mb-0">Create SEO-optimized blogs with product linking</p>
        </div>
        <a href="blog_edit.php" class="btn-create-blog">
            <i class="fas fa-plus"></i> Create New Blog
        </a>
    </div>
    
    <!-- Stats -->
    <div class="blog-stats mb-4">
        <div class="blog-stat">
            <div class="blog-stat-number"><?= count($blogs); ?></div>
            <div class="blog-stat-label">Total Blogs</div>
        </div>
        <div class="blog-stat">
            <div class="blog-stat-number"><?= count(array_filter($blogs, fn($b) => $b['status'] === 'published')); ?></div>
            <div class="blog-stat-label">Published</div>
        </div>
        <div class="blog-stat">
            <div class="blog-stat-number"><?= count(array_filter($blogs, fn($b) => $b['status'] === 'draft')); ?></div>
            <div class="blog-stat-label">Drafts</div>
        </div>
        <div class="blog-stat">
            <div class="blog-stat-number"><?= array_sum(array_column($blogs, 'views')); ?></div>
            <div class="blog-stat-label">Total Views</div>
        </div>
    </div>
    
    <!-- Blog Table -->
    <div class="blog-table-card">
        <div class="blog-table-header">
            <div class="blog-search">
                <input type="text" id="blogSearch" placeholder="Search blogs..." onkeyup="filterBlogs()">
                <select id="categoryFilter" onchange="filterBlogs()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']); ?>"><?= htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="statusFilter" onchange="filterBlogs()">
                    <option value="">All Status</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                    <option value="scheduled">Scheduled</option>
                </select>
            </div>
        </div>
        
        <?php if (empty($blogs)): ?>
        <div class="empty-blogs">
            <i class="fas fa-newspaper"></i>
            <h3>No Blogs Yet</h3>
            <p>Start creating SEO-optimized content to drive traffic and sales</p>
            <a href="blog_edit.php" class="btn-create-blog">
                <i class="fas fa-plus"></i> Create Your First Blog
            </a>
        </div>
        <?php else: ?>
        <div class="blog-table-wrapper">
            <table class="blog-table" id="blogsTable">
                <thead>
                    <tr>
                        <th style="width:100px;">Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>SEO Score</th>
                        <th>Products</th>
                        <th>Views</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blogs as $blog): 
                        // Calculate SEO score
                        $seoScore = 0;
                        if (!empty($blog['meta_title'])) $seoScore += 20;
                        if (!empty($blog['meta_description'])) $seoScore += 20;
                        if (!empty($blog['meta_keywords'])) $seoScore += 15;
                        if (!empty($blog['featured_image'])) $seoScore += 15;
                        if (strlen($blog['content'] ?? '') > 500) $seoScore += 15;
                        if ($blog['product_count'] > 0) $seoScore += 15;
                        
                        $seoClass = $seoScore >= 70 ? 'good' : ($seoScore >= 40 ? 'medium' : 'poor');
                    ?>
                    <tr data-category="<?= htmlspecialchars($blog['category_name'] ?? ''); ?>" data-status="<?= $blog['status']; ?>">
                        <td>
                            <?php if (!empty($blog['featured_image'])): ?>
                                <img src="<?= base_url('uploads/blog/' . $blog['featured_image']); ?>" alt="" class="blog-thumb">
                            <?php else: ?>
                                <div class="blog-thumb d-flex align-items-center justify-content-center" style="background:#f5f5f5;">
                                    <i class="fas fa-image text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="blog-title-cell">
                            <h4><?= htmlspecialchars($blog['title']); ?></h4>
                            <p><?= htmlspecialchars($blog['excerpt'] ?? ''); ?></p>
                        </td>
                        <td>
                            <?php if (!empty($blog['category_name'])): ?>
                                <span class="blog-category-badge">
                                    <i class="fas <?= $blog['category_icon'] ?? 'fa-folder'; ?>"></i>
                                    <?= htmlspecialchars($blog['category_name']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="seo-score">
                                <div class="seo-score-bar">
                                    <div class="seo-score-fill <?= $seoClass; ?>" style="width: <?= $seoScore; ?>%;"></div>
                                </div>
                                <span style="font-size:0.85rem;font-weight:600;"><?= $seoScore; ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="product-count-badge">
                                <i class="fas fa-box"></i> <?= $blog['product_count']; ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= number_format($blog['views']); ?></strong>
                        </td>
                        <td>
                            <span class="blog-status <?= $blog['status']; ?>"><?= ucfirst($blog['status']); ?></span>
                        </td>
                        <td>
                            <div class="blog-actions">
                                <a href="blog_edit.php?id=<?= $blog['id']; ?>" class="btn btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?= base_url('blog/' . $blog['slug']); ?>" target="_blank" class="btn btn-view" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-delete" onclick="deleteBlog(<?= $blog['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
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
function filterBlogs() {
    const search = document.getElementById('blogSearch').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    document.querySelectorAll('#blogsTable tbody tr').forEach(row => {
        const title = row.querySelector('.blog-title-cell h4').textContent.toLowerCase();
        const rowCategory = row.dataset.category;
        const rowStatus = row.dataset.status;
        
        const matchSearch = title.includes(search);
        const matchCategory = !category || rowCategory === category;
        const matchStatus = !status || rowStatus === status;
        
        row.style.display = (matchSearch && matchCategory && matchStatus) ? '' : 'none';
    });
}

function deleteBlog(id) {
    if (confirm('Are you sure you want to delete this blog? This action cannot be undone.')) {
        fetch('blog_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete&id=' + id
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to delete blog');
            }
        });
    }
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
