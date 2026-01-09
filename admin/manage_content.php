<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    redirect_with_message('/login.php', 'Access denied', 'danger');
}

$pageTitle = 'Manage Content Pages';
$adminPage = 'content';

// Create table if not exists
$createTableQuery = "CREATE TABLE IF NOT EXISTS page_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(50) UNIQUE NOT NULL,
    page_title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    meta_description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id)
)";

try {
    db_query($createTableQuery);
} catch (Exception $e) {
    // Table might already exist
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pageKey = $_POST['page_key'] ?? '';
    $pageTitle = $_POST['page_title'] ?? '';
    $content = $_POST['content'] ?? '';
    $metaDescription = $_POST['meta_description'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $updatedBy = $_SESSION['user']['id'];
    
    if ($_POST['action'] === 'save') {
        // Check if page exists
        $existing = db_fetch('SELECT id FROM page_content WHERE page_key = ?', [$pageKey]);
        
        if ($existing) {
            // Update existing
            db_query('UPDATE page_content SET page_title = ?, content = ?, meta_description = ?, is_active = ?, updated_by = ? WHERE page_key = ?',
                [$pageTitle, $content, $metaDescription, $isActive, $updatedBy, $pageKey]);
            $_SESSION['message'] = 'Content updated successfully!';
        } else {
            // Insert new
            db_query('INSERT INTO page_content (page_key, page_title, content, meta_description, is_active, updated_by) VALUES (?, ?, ?, ?, ?, ?)',
                [$pageKey, $pageTitle, $content, $metaDescription, $isActive, $updatedBy]);
            $_SESSION['message'] = 'Content created successfully!';
        }
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: manage_content.php');
    exit;
}

// Get all pages
$pages = db_fetch_all('SELECT * FROM page_content ORDER BY page_key ASC');

// Initialize default pages if empty
if (empty($pages)) {
    $defaultPages = [
        ['page_key' => 'about_us', 'page_title' => 'About Us', 'content' => '', 'meta_description' => 'Learn about Gilaf Store'],
        ['page_key' => 'our_philosophy', 'page_title' => 'Our Philosophy', 'content' => '<h2 style="color: #C9A961; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">OUR PHILOSOPHY</h2><h1 style="color: #C9A961; font-size: 3rem; font-weight: 700; margin-bottom: 30px;">Preserving the Art of Taste</h1><p style="font-size: 1.1rem; line-height: 1.8; margin-bottom: 20px;">At Gilaf Foods & Spices, we believe that food is not just sustenance—it is memory. Founded by Shahid Mohammad & Muneera Shahid, our mission is to bring the unadulterated taste of Kashmir to your table.</p><p style="font-size: 1.1rem; line-height: 1.8;">We work directly with local farmers, ensuring that every strand of saffron and every drop of honey retains the purity of the mountains.</p>', 'meta_description' => 'Discover our philosophy of preserving authentic Kashmiri taste'],
        ['page_key' => 'contact_us', 'page_title' => 'Contact Us', 'content' => '', 'meta_description' => 'Get in touch with us']
    ];
    
    foreach ($defaultPages as $page) {
        db_query('INSERT INTO page_content (page_key, page_title, content, meta_description) VALUES (?, ?, ?, ?)',
            [$page['page_key'], $page['page_title'], $page['content'], $page['meta_description']]);
    }
    
    $pages = db_fetch_all('SELECT * FROM page_content ORDER BY page_key ASC');
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.content-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    background: white;
    transition: box-shadow 0.3s ease;
}

.content-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.page-key-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.editor-container {
    border: 1px solid #ced4da;
    border-radius: 4px;
    min-height: 300px;
}

.btn-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    transition: transform 0.2s ease;
}

.btn-gradient:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: white;
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Manage Content Pages</h1>
            <p class="text-muted mb-0">Edit static page content like About Us, Our Story, etc.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($pages as $page): ?>
            <div class="col-12 mb-4">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-1"><?= htmlspecialchars($page['page_title']); ?></h4>
                            <span class="page-key-badge"><?= htmlspecialchars($page['page_key']); ?></span>
                        </div>
                        <button class="btn btn-gradient" onclick="editPage('<?= $page['page_key']; ?>')">
                            <i class="fas fa-edit me-2"></i>Edit Content
                        </button>
                    </div>
                    
                    <div class="mb-2">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>Last updated: <?= date('M d, Y h:i A', strtotime($page['updated_at'])); ?>
                        </small>
                        <?php if ($page['is_active']): ?>
                            <span class="badge bg-success ms-2">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary ms-2">Inactive</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($page['content'])): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <small class="text-muted">Content Preview:</small>
                            <div class="mt-2" style="max-height: 100px; overflow: hidden;">
                                <?= substr(strip_tags($page['content']), 0, 200); ?>...
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>No content added yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="post" id="editForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="page_key" id="edit_page_key">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Page Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Page Title</label>
                        <input type="text" name="page_title" id="edit_page_title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Meta Description (SEO)</label>
                        <textarea name="meta_description" id="edit_meta_description" class="form-control" rows="2"></textarea>
                        <small class="text-muted">Brief description for search engines (150-160 characters)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Content</label>
                        <textarea name="content" id="edit_content" class="form-control editor-container" rows="15"></textarea>
                        <small class="text-muted">You can use HTML formatting</small>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" checked>
                        <label class="form-check-label" for="edit_is_active">
                            Active (visible on website)
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const pagesData = <?= json_encode($pages); ?>;

function editPage(pageKey) {
    const page = pagesData.find(p => p.page_key === pageKey);
    if (!page) return;
    
    document.getElementById('edit_page_key').value = page.page_key;
    document.getElementById('edit_page_title').value = page.page_title;
    document.getElementById('edit_meta_description').value = page.meta_description || '';
    document.getElementById('edit_content').value = page.content || '';
    document.getElementById('edit_is_active').checked = page.is_active == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
