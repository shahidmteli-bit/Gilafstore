<?php
/**
 * Blog Actions Handler
 * Handles save, delete, and other blog operations
 * VERSION: 2.1 (May 17, 2026) - Featured image persistence fix
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

// For AJAX requests, check admin status
if (!is_admin()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: ' . base_url('gs-secure-portal-92XK'));
    exit;
}

$db = get_db_connection();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'save':
        saveBlog($db);
        break;
    case 'delete':
        deleteBlog($db);
        break;
    case 'search_products':
        searchProducts($db);
        break;
    case 'upload_content_image':
        uploadContentImage();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function saveBlog($db) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? '';
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $authorName = trim($_POST['author_name'] ?? 'Gilaf Store');
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDesc = trim($_POST['meta_description'] ?? '');
        $metaKeywords = trim($_POST['meta_keywords'] ?? '');
        $status = $_POST['status'] ?? 'draft';
        $linkedProducts = json_decode($_POST['linked_products'] ?? '[]', true);
        $linkedRelatedBlogs = json_decode($_POST['linked_blogs'] ?? '[]', true);
        $faqs = json_decode($_POST['faqs'] ?? '[]', true);
        
        // Validation
        if (empty($title)) {
            redirectWithError('Title is required');
            return;
        }
        
        // Generate slug if empty
        if (empty($slug)) {
            $slug = generateSlug($title);
        } else {
            $slug = generateSlug($slug);
        }
        
        // Check slug uniqueness
        $slugCheck = $db->prepare("SELECT id FROM blogs WHERE slug = ? AND id != ?");
        $slugCheck->execute([$slug, $id]);
        if ($slugCheck->fetch()) {
            $slug .= '-' . time();
        }
        
        // Handle featured image upload
        $featuredImage = trim($_POST['existing_image'] ?? '');
        $removeImage = $_POST['remove_image'] ?? '0';
        
        // DEBUG LOG - write to file so we can diagnose image issues
        $debugLog = date('Y-m-d H:i:s') . " | Blog ID: {$id} | existing_image POST: '{$featuredImage}' | remove_image: '{$removeImage}' | FILES: " . (!empty($_FILES['featured_image']['name']) ? $_FILES['featured_image']['name'] : 'none') . "\n";
        @file_put_contents(__DIR__ . '/blog_image_debug.log', $debugLog, FILE_APPEND);
        
        // ALWAYS get the current DB image first for existing blogs
        $currentDbImage = '';
        if ($id > 0) {
            try {
                $imgStmt = $db->prepare("SELECT featured_image FROM blogs WHERE id = ?");
                $imgStmt->execute([$id]);
                $currentDbImage = $imgStmt->fetchColumn() ?: '';
            } catch (Exception $e) {}
        }
        
        // If existing_image from POST is empty and remove not requested, keep current DB image
        if (empty($featuredImage) && $removeImage !== '1' && !empty($currentDbImage)) {
            $featuredImage = $currentDbImage;
        }
        
        // If remove was explicitly requested, clear the image
        if ($removeImage === '1') {
            if ($featuredImage && file_exists(__DIR__ . '/../uploads/blog/' . $featuredImage)) {
                unlink(__DIR__ . '/../uploads/blog/' . $featuredImage);
            }
            $featuredImage = '';
        }
        
        // If a new file was uploaded, use that instead
        if (!empty($_FILES['featured_image']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/blog/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed)) {
                // Delete old image
                if ($featuredImage && file_exists($uploadDir . $featuredImage)) {
                    unlink($uploadDir . $featuredImage);
                }
                
                $newFilename = $slug . '-' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $uploadDir . $newFilename)) {
                    $featuredImage = $newFilename;
                }
            }
        }
        
        // LAST RESORT: If featured_image is still empty, scan uploads/blog/ for matching AI image
        if (empty($featuredImage) && !empty($slug)) {
            $uploadDir = __DIR__ . '/../uploads/blog/';
            if (is_dir($uploadDir)) {
                // Try exact slug match first
                $matches = glob($uploadDir . "ai-{$slug}-*.jpg");
                if (empty($matches)) {
                    $matches = glob($uploadDir . "ai-{$slug}-*.webp");
                }
                // Try partial slug match (first 20 chars) if exact match failed
                if (empty($matches) && strlen($slug) > 10) {
                    $partialSlug = substr($slug, 0, 20);
                    $matches = glob($uploadDir . "ai-{$partialSlug}*.jpg");
                    if (empty($matches)) {
                        $matches = glob($uploadDir . "ai-{$partialSlug}*.webp");
                    }
                }
                // Also try any recent AI image (within last 5 minutes) as final fallback
                if (empty($matches)) {
                    $allAi = glob($uploadDir . "ai-*.jpg");
                    $recent = array_filter($allAi, function($f) { return filemtime($f) > time() - 300; });
                    if (!empty($recent)) $matches = $recent;
                }
                if (!empty($matches)) {
                    usort($matches, function($a, $b) { return filemtime($b) - filemtime($a); });
                    $featuredImage = basename($matches[0]);
                }
            }
        }
        
        // DEBUG LOG final result
        @file_put_contents(__DIR__ . '/blog_image_debug.log', "  -> FINAL featured_image: '{$featuredImage}' (DB was: '{$currentDbImage}')\n", FILE_APPEND);
        
        // Calculate reading time (avg 200 words per minute)
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = max(1, ceil($wordCount / 200));
        
        // Publish date
        $publishDate = ($status === 'published') ? date('Y-m-d H:i:s') : null;
        
        if ($id > 0) {
            // Update existing blog
            $stmt = $db->prepare("
                UPDATE blogs SET 
                    title = ?, slug = ?, excerpt = ?, content = ?,
                    featured_image = ?, category_id = ?, author_name = ?,
                    meta_title = ?, meta_description = ?, meta_keywords = ?,
                    reading_time = ?, status = ?, 
                    publish_date = COALESCE(publish_date, ?),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $slug, $excerpt, $content,
                $featuredImage, $categoryId, $authorName,
                $metaTitle, $metaDesc, $metaKeywords,
                $readingTime, $status, $publishDate, $id
            ]);
        } else {
            // Insert new blog
            $stmt = $db->prepare("
                INSERT INTO blogs (
                    title, slug, excerpt, content,
                    featured_image, category_id, author_name,
                    meta_title, meta_description, meta_keywords,
                    reading_time, status, publish_date, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $title, $slug, $excerpt, $content,
                $featuredImage, $categoryId, $authorName,
                $metaTitle, $metaDesc, $metaKeywords,
                $readingTime, $status, $publishDate
            ]);
            $id = $db->lastInsertId();
        }
        
        // Update linked products
        $db->prepare("DELETE FROM blog_products WHERE blog_id = ?")->execute([$id]);
        if (!empty($linkedProducts)) {
            $stmt = $db->prepare("
                INSERT INTO blog_products (blog_id, product_id, display_type, display_order)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($linkedProducts as $lp) {
                $stmt->execute([
                    $id,
                    (int)$lp['product_id'],
                    $lp['display_type'] ?? 'bottom',
                    (int)($lp['display_order'] ?? 0)
                ]);
            }
        }
        
        // Update linked related blogs
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS blog_internal_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                blog_id INT NOT NULL,
                linked_blog_id INT NOT NULL,
                display_type VARCHAR(20) DEFAULT 'bottom',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_link (blog_id, linked_blog_id),
                INDEX idx_blog (blog_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            try { $db->exec("ALTER TABLE blog_internal_links ADD COLUMN IF NOT EXISTS display_type VARCHAR(20) DEFAULT 'bottom'"); } catch (Exception $e) {}
            $db->prepare("DELETE FROM blog_internal_links WHERE blog_id = ?")->execute([$id]);
            if (!empty($linkedRelatedBlogs)) {
                $stmt = $db->prepare("INSERT IGNORE INTO blog_internal_links (blog_id, linked_blog_id, display_type) VALUES (?, ?, ?)");
                foreach ($linkedRelatedBlogs as $lb) {
                    $linkedBlogId = (int)($lb['blog_id'] ?? 0);
                    $displayType = in_array($lb['display_type'] ?? '', ['bottom','inline','sidebar']) ? $lb['display_type'] : 'bottom';
                    if ($linkedBlogId > 0 && $linkedBlogId !== $id) {
                        $stmt->execute([$id, $linkedBlogId, $displayType]);
                    }
                }
            }
        } catch (Exception $e) {}

        // Update FAQs
        $db->prepare("DELETE FROM blog_faqs WHERE blog_id = ?")->execute([$id]);
        if (!empty($faqs)) {
            $stmt = $db->prepare("
                INSERT INTO blog_faqs (blog_id, question, answer, sort_order)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($faqs as $faq) {
                if (!empty($faq['question']) && !empty($faq['answer'])) {
                    $stmt->execute([
                        $id,
                        $faq['question'],
                        $faq['answer'],
                        (int)($faq['sort_order'] ?? 0)
                    ]);
                }
            }
        }
        
        // Redirect to blog list with success
        header('Location: manage_blogs.php?success=1');
        exit;
        
    } catch (PDOException $e) {
        redirectWithError('Database error: ' . $e->getMessage());
    }
}

function deleteBlog($db) {
    try {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid blog ID']);
            return;
        }
        
        // Get blog to delete image
        $stmt = $db->prepare("SELECT featured_image FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($blog && $blog['featured_image']) {
            $imagePath = __DIR__ . '/../uploads/blog/' . $blog['featured_image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // Delete blog (cascades to blog_products and blog_faqs)
        $stmt = $db->prepare("DELETE FROM blogs WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function searchProducts($db) {
    $query = trim($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        echo json_encode([]);
        return;
    }
    
    $stmt = $db->prepare("
        SELECT id, name, image 
        FROM products 
        WHERE name LIKE ? 
        ORDER BY name ASC 
        LIMIT 15
    ");
    $stmt->execute(['%' . $query . '%']);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
}

function generateSlug($text) {
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

function redirectWithError($message) {
    $_SESSION['blog_error'] = $message;
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

function uploadContentImage() {
    header('Content-Type: application/json');
    
    if (empty($_FILES['content_image']['name'])) {
        echo json_encode(['success' => false, 'message' => 'No image file received']);
        return;
    }
    
    $file = $_FILES['content_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: jpg, png, gif, webp']);
        return;
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB.']);
        return;
    }
    
    $uploadDir = __DIR__ . '/../uploads/blog/content/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = 'content-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        $baseUrl = '';
        if (function_exists('base_url')) {
            $baseUrl = base_url('uploads/blog/content/' . $filename);
        } else {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $protocol . '://' . $host . '/uploads/blog/content/' . $filename;
        }
        echo json_encode(['success' => true, 'url' => $baseUrl, 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    }
}
