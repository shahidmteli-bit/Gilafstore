<?php
/**
 * One-time fix: Scan uploads/blog/ for AI-generated images and
 * assign them to blogs that have empty featured_image in the DB.
 * Also allows manual assignment of any image to any blog.
 * 
 * DELETE THIS FILE AFTER USE.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

require_admin();

$db = get_db_connection();
$message = '';
$messageType = '';

// Handle manual image assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['assign'])) {
    $blogId = (int)$_POST['blog_id'];
    $filename = trim($_POST['filename']);
    
    if ($blogId > 0 && !empty($filename)) {
        $uploadDir = __DIR__ . '/../uploads/blog/';
        if (file_exists($uploadDir . $filename)) {
            $stmt = $db->prepare("UPDATE blogs SET featured_image = ? WHERE id = ?");
            $stmt->execute([$filename, $blogId]);
            $message = "Blog #{$blogId} updated with image: {$filename}";
            $messageType = 'success';
        } else {
            $message = "File not found: {$filename}";
            $messageType = 'error';
        }
    }
}

// Handle auto-fix: match AI images to blogs by slug
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['auto_fix'])) {
    $uploadDir = __DIR__ . '/../uploads/blog/';
    $fixed = 0;
    
    // Get all blogs with empty featured_image
    $stmt = $db->query("SELECT id, slug, title FROM blogs WHERE featured_image IS NULL OR featured_image = '' ORDER BY id DESC");
    $emptyBlogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($emptyBlogs as $blog) {
        $slug = $blog['slug'];
        // Look for AI-generated images matching this slug
        $pattern = $uploadDir . "ai-{$slug}-*.jpg";
        $matches = glob($pattern);
        
        if (empty($matches)) {
            // Try without 'ai-' prefix
            $pattern = $uploadDir . "{$slug}-*.jpg";
            $matches = glob($pattern);
        }
        
        if (empty($matches)) {
            // Try matching any image with the slug
            $pattern = $uploadDir . "*{$slug}*";
            $matches = glob($pattern);
        }
        
        if (!empty($matches)) {
            // Use the most recent file
            usort($matches, function($a, $b) { return filemtime($b) - filemtime($a); });
            $filename = basename($matches[0]);
            
            $update = $db->prepare("UPDATE blogs SET featured_image = ? WHERE id = ?");
            $update->execute([$filename, $blog['id']]);
            $fixed++;
        }
    }
    
    $message = "Auto-fix complete: {$fixed} blogs updated with matching images.";
    $messageType = 'success';
}

// Get all blogs
$blogs = $db->query("SELECT id, title, slug, featured_image, status FROM blogs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get all images in uploads/blog/
$uploadDir = __DIR__ . '/../uploads/blog/';
$allImages = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $allImages[] = $f;
        }
    }
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.fix-container { max-width: 1200px; margin: 20px auto; padding: 20px; }
.fix-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
.fix-card h2 { margin: 0 0 15px; font-size: 1.2rem; color: #1f2937; }
.blog-row { display: flex; align-items: center; gap: 15px; padding: 12px; border-bottom: 1px solid #f3f4f6; }
.blog-row:last-child { border-bottom: none; }
.blog-thumb { width: 80px; height: 50px; border-radius: 6px; object-fit: cover; background: #f5f5f5; }
.blog-thumb-empty { width: 80px; height: 50px; border-radius: 6px; background: #fee2e2; display: flex; align-items: center; justify-content: center; color: #ef4444; font-size: 0.8rem; }
.blog-info { flex: 1; }
.blog-info strong { font-size: 0.9rem; color: #1f2937; }
.blog-info small { display: block; color: #6b7280; font-size: 0.75rem; }
.status-badge { padding: 2px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
.status-published { background: #dcfce7; color: #166534; }
.status-draft { background: #fef9c3; color: #854d0e; }
.btn-fix { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; }
.btn-auto { background: #6366f1; color: white; padding: 12px 24px; font-size: 0.9rem; border-radius: 8px; }
.btn-assign { background: #22c55e; color: white; }
.alert-success { background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; }
.alert-error { background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; }
select.image-select { padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 0.75rem; max-width: 200px; }
</style>

<div class="fix-container">
    <h1 style="font-size:1.5rem;margin-bottom:20px;">
        <i class="fas fa-wrench" style="color:#6366f1;"></i> Fix Blog Featured Images
    </h1>
    
    <?php if ($message): ?>
        <div class="alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <!-- Auto-Fix Button -->
    <div class="fix-card">
        <h2><i class="fas fa-magic" style="color:#a855f7;"></i> Auto-Fix: Match Images to Blogs</h2>
        <p style="color:#6b7280;font-size:0.85rem;margin-bottom:15px;">
            Scans <code>uploads/blog/</code> for images matching blog slugs and assigns them automatically.
            Only updates blogs with empty featured images.
        </p>
        <form method="POST">
            <button type="submit" name="auto_fix" value="1" class="btn-fix btn-auto">
                <i class="fas fa-magic"></i> Auto-Fix All Empty Blogs
            </button>
        </form>
    </div>
    
    <!-- Blog List -->
    <div class="fix-card">
        <h2><i class="fas fa-list"></i> All Blogs (<?= count($blogs) ?>)</h2>
        
        <?php foreach ($blogs as $blog): ?>
        <div class="blog-row">
            <?php if (!empty($blog['featured_image'])): ?>
                <img src="<?= base_url('uploads/blog/' . $blog['featured_image']) ?>" class="blog-thumb" alt="">
            <?php else: ?>
                <div class="blog-thumb-empty"><i class="fas fa-exclamation"></i></div>
            <?php endif; ?>
            
            <div class="blog-info">
                <strong>#<?= $blog['id'] ?> — <?= htmlspecialchars($blog['title']) ?></strong>
                <small>
                    Slug: <?= htmlspecialchars($blog['slug']) ?> | 
                    Image: <?= $blog['featured_image'] ?: '<span style="color:#ef4444;font-weight:600;">EMPTY</span>' ?>
                </small>
            </div>
            
            <span class="status-badge status-<?= $blog['status'] ?>"><?= ucfirst($blog['status']) ?></span>
            
            <?php if (empty($blog['featured_image']) && !empty($allImages)): ?>
            <form method="POST" style="display:flex;gap:5px;align-items:center;">
                <input type="hidden" name="blog_id" value="<?= $blog['id'] ?>">
                <select name="filename" class="image-select">
                    <option value="">Select image...</option>
                    <?php foreach ($allImages as $img): ?>
                        <option value="<?= htmlspecialchars($img) ?>" 
                            <?= (strpos($img, $blog['slug']) !== false) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($img) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="assign" value="1" class="btn-fix btn-assign">
                    <i class="fas fa-check"></i> Set
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Available Images -->
    <div class="fix-card">
        <h2><i class="fas fa-images"></i> Available Images in uploads/blog/ (<?= count($allImages) ?>)</h2>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <?php foreach (array_slice($allImages, 0, 30) as $img): ?>
            <div style="text-align:center;">
                <img src="<?= base_url('uploads/blog/' . $img) ?>" style="width:120px;height:70px;object-fit:cover;border-radius:6px;" alt="">
                <div style="font-size:0.65rem;color:#6b7280;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($img) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <p style="text-align:center;color:#ef4444;font-size:0.85rem;margin-top:20px;">
        <i class="fas fa-exclamation-triangle"></i> Delete this file (<code>admin/fix_blog_images.php</code>) after fixing all images.
    </p>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
