<?php
/**
 * Single Blog Post Page
 * Professional SEO-optimized blog display with product blocks
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$db = get_db_connection();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: blogs.php');
    exit;
}

// Auto-create blog tables if they don't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS blog_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description TEXT,
            icon VARCHAR(50) DEFAULT 'fa-folder',
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS blogs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(280) NOT NULL,
            excerpt TEXT,
            content LONGTEXT,
            featured_image VARCHAR(500),
            category_id INT,
            author_name VARCHAR(100) DEFAULT 'Gilaf Store',
            meta_title VARCHAR(70),
            meta_description VARCHAR(160),
            meta_keywords VARCHAR(255),
            views INT DEFAULT 0,
            reading_time INT DEFAULT 5,
            status ENUM('draft', 'published', 'scheduled') DEFAULT 'draft',
            publish_date DATETIME,
            is_featured TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS blog_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blog_id INT NOT NULL,
            product_id INT NOT NULL,
            display_order INT DEFAULT 0,
            display_type ENUM('inline', 'sidebar', 'bottom') DEFAULT 'bottom',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS blog_faqs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blog_id INT NOT NULL,
            question VARCHAR(500) NOT NULL,
            answer TEXT NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Tables might already exist or no DDL permission - continue anyway
}

// Check if admin preview mode (logged in admin can view drafts)
$isAdminPreview = false;
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
    $isAdminPreview = true;
}

// Fetch blog with category
try {
    if ($isAdminPreview) {
        // Admins can preview drafts too
        $stmt = $db->prepare("
            SELECT b.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon
            FROM blogs b
            LEFT JOIN blog_categories c ON c.id = b.category_id
            WHERE b.slug = ?
        ");
        $stmt->execute([$slug]);
    } else {
        $stmt = $db->prepare("
            SELECT b.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon
            FROM blogs b
            LEFT JOIN blog_categories c ON c.id = b.category_id
            WHERE b.slug = ? AND b.status = 'published'
        ");
        $stmt->execute([$slug]);
    }
    $blog = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $blog = null;
}

if (!$blog) {
    // Show a helpful message instead of 404 if it's a draft
    try {
        $checkDraft = $db->prepare("SELECT id, status FROM blogs WHERE slug = ?");
        $checkDraft->execute([$slug]);
        $draftBlog = $checkDraft->fetch(PDO::FETCH_ASSOC);
        if ($draftBlog && $draftBlog['status'] === 'draft') {
            header('HTTP/1.0 404 Not Found');
            die('<h1 style="font-family:sans-serif;text-align:center;margin-top:100px;">Blog not yet published</h1><p style="text-align:center;color:#666;">This blog is currently in draft mode. Please publish it from the admin panel.</p>');
        }
    } catch (PDOException $e) {}
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/404.php';
    exit;
}

// Increment view count
try {
    $db->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?")->execute([$blog['id']]);
} catch (PDOException $e) {}

// Fetch linked products
$linkedProducts = [];
try {
    $stmt = $db->prepare("
        SELECT bp.*, p.id as product_id, p.name, p.slug as product_slug, p.image, p.description,
               (SELECT MIN(price) FROM product_weights WHERE product_id = p.id) as min_price,
               (SELECT MAX(price) FROM product_weights WHERE product_id = p.id) as max_price
        FROM blog_products bp
        JOIN products p ON p.id = bp.product_id
        WHERE bp.blog_id = ?
        ORDER BY bp.display_order ASC
    ");
    $stmt->execute([$blog['id']]);
    $linkedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Separate by display type
$bottomProducts = array_filter($linkedProducts, fn($p) => $p['display_type'] === 'bottom');
$sidebarProducts = array_filter($linkedProducts, fn($p) => $p['display_type'] === 'sidebar');

// Fetch FAQs
$faqs = [];
try {
    $stmt = $db->prepare("SELECT * FROM blog_faqs WHERE blog_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$blog['id']]);
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch manually linked related blogs from blog_internal_links (admin-curated)
$linkedRelatedBlogs = [];
try {
    $stmt = $db->prepare("
        SELECT b.id, b.title, b.slug, b.featured_image, b.reading_time, b.publish_date
        FROM blog_internal_links bil
        JOIN blogs b ON b.id = bil.linked_blog_id
        WHERE bil.blog_id = ? AND b.status = 'published'
        ORDER BY bil.id ASC
        LIMIT 5
    ");
    $stmt->execute([$blog['id']]);
    $linkedRelatedBlogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch related blogs (same category) as fallback / supplement
$relatedBlogs = [];
if (!empty($blog['category_id'])) {
    try {
        $linkedIds = array_column($linkedRelatedBlogs, 'id');
        $linkedIds[] = $blog['id'];
        $placeholders = implode(',', array_fill(0, count($linkedIds), '?'));
        $stmt = $db->prepare("
            SELECT id, title, slug, excerpt, featured_image, reading_time, publish_date
            FROM blogs 
            WHERE category_id = ? AND id NOT IN ($placeholders) AND status = 'published'
            ORDER BY publish_date DESC
            LIMIT 3
        ");
        $stmt->execute(array_merge([$blog['category_id']], $linkedIds));
        $relatedBlogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

// Merge: linked blogs first, then category blogs (no duplicates), max 5 total
$allRelatedBlogs = array_merge($linkedRelatedBlogs, $relatedBlogs);
$allRelatedBlogs = array_slice($allRelatedBlogs, 0, 5);

// Category-based author expertise for E-E-A-T
$authorExpertiseMap = [
    'Health Benefits' => ['role' => 'Nutrition & Wellness Researcher',   'about' => "Gilaf Store's research team specialises in the health properties and nutritional science of Kashmir's traditional foods, verified through direct sourcing partnerships."],
    'Buying Guides'   => ['role' => 'Kashmir Product Specialist',         'about' => "Gilaf Store's sourcing experts have years of hands-on experience identifying authentic Kashmir products and exposing adulteration."],
    'Recipes'         => ['role' => 'Culinary Heritage Expert',           'about' => "Gilaf Store's culinary team researches traditional Kashmiri recipes and modern applications of authentic heritage ingredients."],
    'Product Stories' => ['role' => 'Artisan Product Researcher',         'about' => "Gilaf Store's team documents authentic origins, harvesting processes, and quality markers of Kashmir's finest artisan products."],
    'Kashmir Culture' => ['role' => 'Kashmir Heritage Specialist',        'about' => "Gilaf Store's team is deeply rooted in Kashmiri tradition, bringing authentic stories of culture and heritage to readers worldwide."],
];
$authorInfo = $authorExpertiseMap[$blog['category_name'] ?? ''] ?? [
    'role'  => 'Kashmir Heritage Expert',
    'about' => "Gilaf Store is a direct-from-Kashmir purveyor of authentic saffron, olive oil, CTC tea, and heritage products. Our team personally verifies authenticity and quality on every product we offer.",
];

// SEO
$pageTitle = $blog['meta_title'] ?: $blog['title'] . ' | Gilaf Store Blog';
$metaDescription = $blog['meta_description'] ?: $blog['excerpt'];
$metaKeywords = $blog['meta_keywords'] ?: '';
$ogImage = $blog['featured_image'] ? base_url('uploads/blog/' . $blog['featured_image']) : '';
$canonicalUrl = base_url('blog/' . $blog['slug']);

// Blog-specific OG overrides passed to new-header.php
$blogOgImage   = !empty($blog['featured_image']) ? base_url('uploads/blog/' . $blog['featured_image']) : null;
$blogOgType    = 'article';
$blogOgPubTime = !empty($blog['publish_date']) ? date('c', strtotime($blog['publish_date'])) : null;
$blogOgModTime = !empty($blog['updated_at'])   ? date('c', strtotime($blog['updated_at']))   : null;
$blogOgSection = $blog['category_name'] ?? null;

include __DIR__ . '/includes/new-header.php';

// Schema Markup
$wordCount = $blog['content'] ? str_word_count(strip_tags($blog['content'])) : 0;
$articleSchema = [
    '@context' => 'https://schema.org',
    '@type'    => 'BlogPosting',
    'headline' => $blog['title'],
    'description' => $blog['excerpt'],
    'image'    => $ogImage ?: base_url('assets/images/gilaf-store-og-default.jpg'),
    'author'   => [
        '@type'      => 'Organization',
        'name'       => 'Gilaf Store',
        'url'        => 'https://gilafstore.com',
        'knowsAbout' => array_values(array_filter([
            $blog['category_name'] ?? null,
            'Kashmir heritage products',
            'authentic saffron',
            'quality verification',
            'traditional Kashmiri recipes'
        ]))
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name'  => 'Gilaf Store',
        'logo'  => [
            '@type' => 'ImageObject',
            'url'   => base_url('assets/images/logo.png')
        ]
    ],
    'datePublished'    => $blog['publish_date'],
    'dateModified'     => $blog['updated_at'],
    'mainEntityOfPage' => $canonicalUrl,
    'url'       => $canonicalUrl,
    'wordCount' => $wordCount,
    'keywords'  => $blog['meta_keywords'] ?: ($blog['category_name'] ?? 'Gilaf Store')
];

// Organization schema (E-E-A-T signal)
$orgSchema = [
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => 'Gilaf Store',
    'url'      => 'https://gilafstore.com',
    'logo'     => ['@type' => 'ImageObject', 'url' => base_url('assets/images/logo.png')],
    'sameAs'   => [
        'https://www.facebook.com/gilafstore',
        'https://www.instagram.com/gilafstore'
    ]
];

// Breadcrumb Schema
$breadcrumbSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => base_url()],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => base_url('blogs.php')]
    ]
];
if ($blog['category_name']) {
    $breadcrumbSchema['itemListElement'][] = [
        '@type' => 'ListItem', 
        'position' => 3, 
        'name' => $blog['category_name'], 
        'item' => base_url('blogs.php?category=' . $blog['category_slug'])
    ];
    $breadcrumbSchema['itemListElement'][] = [
        '@type' => 'ListItem', 
        'position' => 4, 
        'name' => $blog['title']
    ];
} else {
    $breadcrumbSchema['itemListElement'][] = [
        '@type' => 'ListItem', 
        'position' => 3, 
        'name' => $blog['title']
    ];
}

// FAQ Schema
$faqSchema = null;
if (!empty($faqs)) {
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($faq) => [
            '@type' => 'Question',
            'name' => $faq['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer']
            ]
        ], $faqs)
    ];
}

// Pre-render blog content (used for HowTo schema detection AND HTML display)
$renderedContent = $blog['content'] ?? '';
$renderedContent = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $renderedContent);
$renderedContent = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $renderedContent);
$renderedContent = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $renderedContent);
$renderedContent = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $renderedContent);
$renderedContent = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $renderedContent);
$renderedContent = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $renderedContent);
$renderedContent = preg_replace('/\*\*/', '', $renderedContent);
$renderedContent = preg_replace('/href=(["\'])\/products\//', 'href=$1/product/', $renderedContent);
$renderedContent = preg_replace('/href=(["\'])https?:\/\/gilafstore\.com\/products\//', 'href=$1https://gilafstore.com/product/', $renderedContent);
$renderedContent = preg_replace('/<img[^>]*src=["\']https?:\/\/(images\.unsplash\.com|unsplash\.com|images\.pexels\.com)[^"\']*["\'][^>]*\/?>/i', '', $renderedContent);
$renderedContent = str_replace([' — ', '—', ' – ', '–'], [', ', ', ', ', ', ', '], $renderedContent);
// Add lazy loading to all content images for Core Web Vitals
$renderedContent = preg_replace('/<img(?![^>]*loading=)([^>]*)>/i', '<img loading="lazy"$1>', $renderedContent);
if (strpos($renderedContent, '<p>') === false && strpos($renderedContent, '<h') === false) {
    $renderedContent = '<p>' . preg_replace('/\n\n+/', '</p><p>', $renderedContent) . '</p>';
}

// HowTo Schema — auto-detect ordered lists in instructional/guide articles
$howtoSchema = null;
$howToTriggers = ['how to', 'test', 'guide', 'steps', 'ways', 'tips', 'check', 'identify', 'spot', 'store', 'choose', 'real', 'fake', 'authentic'];
$titleLower = strtolower($blog['title'] ?? '');
$isHowToArticle = false;
foreach ($howToTriggers as $trigger) {
    if (strpos($titleLower, $trigger) !== false) { $isHowToArticle = true; break; }
}
if ($isHowToArticle) {
    preg_match_all('/<ol[^>]*>(.*?)<\/ol>/si', $renderedContent, $olMatches);
    foreach ($olMatches[1] as $olContent) {
        preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $olContent, $liMatches);
        if (count($liMatches[1]) >= 3) {
            $howSteps = [];
            foreach ($liMatches[1] as $idx => $stepHtml) {
                $stepText = trim(strip_tags($stepHtml));
                if (mb_strlen($stepText) > 5) {
                    $howSteps[] = [
                        '@type'    => 'HowToStep',
                        'position' => $idx + 1,
                        'name'     => mb_substr($stepText, 0, 80),
                        'text'     => $stepText
                    ];
                }
            }
            if (count($howSteps) >= 3) {
                $howtoSchema = [
                    '@context'    => 'https://schema.org',
                    '@type'       => 'HowTo',
                    'name'        => $blog['title'],
                    'description' => $blog['excerpt'],
                    'image'       => $ogImage ?: base_url('assets/images/gilaf-store-og-default.jpg'),
                    'totalTime'   => 'PT' . min(60, count($howSteps) * 3) . 'M',
                    'step'        => $howSteps
                ];
                break;
            }
        }
    }
}
?>

<!-- Schema Markup -->
<?php
// Check for custom AI-generated schema (applied via SEO Dashboard)
$customSchema = null;
try {
    $csStmt = $db->prepare("SELECT custom_schema FROM blogs WHERE id = ? AND custom_schema IS NOT NULL AND custom_schema != ''");
    $csStmt->execute([$blog['id']]);
    $csRow = $csStmt->fetch(PDO::FETCH_ASSOC);
    if ($csRow && !empty($csRow['custom_schema'])) {
        $customSchema = json_decode($csRow['custom_schema'], true);
    }
} catch (Exception $e) { /* column may not exist yet */ }

if ($customSchema): ?>
<script type="application/ld+json"><?= json_encode($customSchema, JSON_UNESCAPED_SLASHES); ?></script>
<?php endif; ?>
<script type="application/ld+json"><?= json_encode($articleSchema, JSON_UNESCAPED_SLASHES); ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES); ?></script>
<script type="application/ld+json"><?= json_encode($orgSchema, JSON_UNESCAPED_SLASHES); ?></script>
<?php if ($faqSchema): ?>
<script type="application/ld+json"><?= json_encode($faqSchema, JSON_UNESCAPED_SLASHES); ?></script>
<?php endif; ?>
<?php if ($howtoSchema): ?>
<script type="application/ld+json"><?= json_encode($howtoSchema, JSON_UNESCAPED_SLASHES); ?></script>
<?php endif; ?>

<!-- Styles moved to assets/css/blog.css (render-blocking, loaded via new-header.php) -->



<div class="blog-post-page">
    <!-- Breadcrumbs -->
    <nav class="blog-breadcrumbs">
        <div class="container">
            <ol class="breadcrumb-list">
                <li><a href="<?= base_url(); ?>"><i class="fas fa-home"></i> Home</a></li>
                <li><span class="separator">/</span></li>
                <li><a href="<?= base_url('blogs.php'); ?>">Blog</a></li>
                <?php if ($blog['category_name']): ?>
                    <li><span class="separator">/</span></li>
                    <li><a href="<?= base_url('blogs.php?category=' . $blog['category_slug']); ?>"><?= htmlspecialchars($blog['category_name']); ?></a></li>
                <?php endif; ?>
                <li><span class="separator">/</span></li>
                <li><span class="current"><?= htmlspecialchars($blog['title']); ?></span></li>
            </ol>
        </div>
    </nav>
    
    <div class="blog-layout">
        <!-- Main Content -->
        <article class="blog-main">
            <!-- Featured Image -->
            <?php if ($blog['featured_image']): ?>
                <img src="<?= base_url('uploads/blog/' . $blog['featured_image']); ?>" 
                     alt="<?= htmlspecialchars($blog['title']); ?>" 
                     class="blog-featured-image">
            <?php endif; ?>
            
            <!-- Header -->
            <header class="blog-header">
                <?php if ($blog['category_name']): ?>
                    <a href="<?= base_url('blogs.php?category=' . $blog['category_slug']); ?>" class="blog-category-tag">
                        <i class="fas <?= $blog['category_icon'] ?? 'fa-folder'; ?>"></i>
                        <?= htmlspecialchars($blog['category_name']); ?>
                    </a>
                <?php endif; ?>
                
                <h1 class="blog-title"><?= htmlspecialchars($blog['title']); ?></h1>
                
                <div class="blog-meta">
                    <div class="blog-meta-item">
                        <div class="author-avatar"><?= strtoupper(substr($blog['author_name'], 0, 1)); ?></div>
                        <span><?= htmlspecialchars($blog['author_name']); ?></span>
                    </div>
                    <div class="blog-meta-item">
                        <i class="far fa-calendar-alt"></i>
                        <span><?= date('M d, Y', strtotime($blog['publish_date'])); ?></span>
                    </div>
                    <div class="blog-meta-item">
                        <i class="far fa-clock"></i>
                        <span><?= $blog['reading_time']; ?> min read</span>
                    </div>
                    <div class="blog-meta-item">
                        <i class="far fa-eye"></i>
                        <span><?= number_format($blog['views']); ?> views</span>
                    </div>
                    <?php
                    $pubTs = strtotime($blog['publish_date']);
                    $modTs = strtotime($blog['updated_at']);
                    if ($modTs && $pubTs && $modTs > $pubTs + 86400):
                    ?>
                    <div class="blog-meta-item">
                        <i class="fas fa-sync-alt"></i>
                        <span>Updated <?= date('M d, Y', $modTs); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </header>
            
            <!-- Table of Contents (auto-generated from headings) -->
            <?php
            // Extract headings for TOC
            preg_match_all('/<h([2-3])[^>]*>(.*?)<\/h[2-3]>/i', $renderedContent, $tocMatches, PREG_SET_ORDER);
            if (count($tocMatches) >= 3):
                // Add IDs to headings in rendered content
                $tocItems = [];
                foreach ($tocMatches as $i => $m) {
                    $level = $m[1];
                    $text = strip_tags($m[2]);
                    $id = 'section-' . ($i + 1) . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
                    $id = substr($id, 0, 60);
                    $tocItems[] = ['level' => $level, 'text' => $text, 'id' => $id];
                    // Replace heading with ID-bearing version
                    $renderedContent = str_replace($m[0], '<h' . $level . ' id="' . $id . '">' . $m[2] . '</h' . $level . '>', $renderedContent);
                }
            ?>
            <nav class="blog-toc" aria-label="Table of Contents">
                <div class="toc-header" onclick="this.parentElement.classList.toggle('collapsed')">
                    <h4><i class="fas fa-list-ul"></i> Table of Contents</h4>
                    <i class="fas fa-chevron-up toc-toggle"></i>
                </div>
                <ol class="toc-list">
                    <?php foreach ($tocItems as $item): ?>
                    <li class="toc-level-<?= $item['level']; ?>">
                        <a href="#<?= $item['id']; ?>"><?= htmlspecialchars($item['text']); ?></a>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>

            <!-- Content -->
            <div class="blog-content">
                <?php echo $renderedContent; ?>
            </div>
            
            <!-- Bottom Products -->
            <?php if (!empty($bottomProducts)): ?>
            <section class="bottom-products-section">
                <h3 class="bottom-products-title">
                    <i class="fas fa-shopping-bag"></i> Shop Products From This Article
                </h3>
                <div class="product-block-grid">
                    <?php foreach ($bottomProducts as $product): 
                        $frontImg = get_product_default_front_image($product['product_id']);
                        $imgSrc = $frontImg ? asset_url('images/products/' . $frontImg) : asset_url('images/products/' . $product['image']);
                    ?>
                    <a href="<?= base_url('product/' . ($product['product_slug'] ?? '')); ?>" class="product-block-item">
                        <img src="<?= $imgSrc; ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                        <h4><?= htmlspecialchars($product['name']); ?></h4>
                        <div class="price">
                            <?php if ($product['min_price'] && $product['max_price']): ?>
                                ₹<?= number_format($product['min_price']); ?> - ₹<?= number_format($product['max_price']); ?>
                            <?php else: ?>
                                View Price
                            <?php endif; ?>
                        </div>
                        <span class="buy-btn"><i class="fas fa-shopping-cart"></i> View Product</span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- FAQs -->
            <?php if (!empty($faqs)): ?>
            <section class="blog-faqs">
                <h3 class="blog-faqs-title">
                    <i class="fas fa-question-circle"></i> Frequently Asked Questions
                </h3>
                <div class="faq-accordion">
                    <?php foreach ($faqs as $index => $faq): ?>
                    <div class="faq-item <?= $index === 0 ? 'open' : ''; ?>">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <?= htmlspecialchars($faq['question']); ?>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <?= nl2br(htmlspecialchars($faq['answer'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            
            <!-- Share -->
            <div class="blog-share">
                <span class="blog-share-label">Share this article:</span>
                <a href="https://wa.me/?text=<?= urlencode($blog['title'] . ' - ' . $canonicalUrl); ?>" target="_blank" class="share-btn whatsapp" title="Share on WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl); ?>" target="_blank" class="share-btn facebook" title="Share on Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?text=<?= urlencode($blog['title']); ?>&url=<?= urlencode($canonicalUrl); ?>" target="_blank" class="share-btn twitter" title="Share on Twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="https://pinterest.com/pin/create/button/?url=<?= urlencode($canonicalUrl); ?>&media=<?= urlencode($ogImage); ?>&description=<?= urlencode($blog['title']); ?>" target="_blank" class="share-btn pinterest" title="Share on Pinterest">
                    <i class="fab fa-pinterest-p"></i>
                </a>
                <button class="share-btn copy" onclick="copyLink()" title="Copy Link">
                    <i class="fas fa-link"></i>
                </button>
            </div>
            
            <!-- Author Box (E-E-A-T enhanced) -->
            <div class="author-box">
                <div class="author-box-avatar"><?= strtoupper(substr($blog['author_name'], 0, 1)); ?></div>
                <div class="author-box-info">
                    <h4><?= htmlspecialchars($blog['author_name']); ?> Research Team</h4>
                    <div class="author-expertise-badge">
                        <i class="fas fa-certificate"></i>
                        <?= htmlspecialchars($authorInfo['role']); ?>
                    </div>
                    <p><?= htmlspecialchars($authorInfo['about']); ?></p>
                    <div class="author-links">
                        <a href="<?= base_url('our-story'); ?>" class="author-link">
                            <i class="fas fa-external-link-alt"></i> About Us
                        </a>
                        <?php if ($blog['category_slug']): ?>
                        <a href="<?= base_url('blogs.php?category=' . $blog['category_slug']); ?>" class="author-link">
                            <i class="fas fa-newspaper"></i> More <?= htmlspecialchars($blog['category_name']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
        
        <!-- Sidebar -->
        <aside class="blog-sidebar">
            <!-- Sidebar Products -->
            <?php if (!empty($sidebarProducts)): ?>
            <div class="sidebar-card">
                <h4 class="sidebar-title"><i class="fas fa-box"></i> Featured Products</h4>
                <?php foreach ($sidebarProducts as $product): 
                    $frontImg = get_product_default_front_image($product['product_id']);
                    $imgSrc = $frontImg ? asset_url('images/products/' . $frontImg) : asset_url('images/products/' . $product['image']);
                ?>
                <a href="<?= base_url('product/' . ($product['product_slug'] ?? '')); ?>" class="sidebar-product">
                    <img src="<?= $imgSrc; ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                    <div class="sidebar-product-info">
                        <h5><?= htmlspecialchars($product['name']); ?></h5>
                        <div class="price">
                            <?php if ($product['min_price']): ?>
                                From ₹<?= number_format($product['min_price']); ?>
                            <?php else: ?>
                                View Price
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Related Articles (linked first, then same-category) -->
            <?php if (!empty($allRelatedBlogs)): ?>
            <div class="sidebar-card" style="border-top:3px solid #8b5cf6;">
                <h4 class="sidebar-title" style="color:#1e293b;">
                    <i class="fas fa-newspaper" style="color:#8b5cf6;"></i> Related Articles
                </h4>
                <?php foreach ($allRelatedBlogs as $related): ?>
                <a href="<?= base_url('blog/' . $related['slug']); ?>" class="related-blog" style="text-decoration:none;">
                    <?php if ($related['featured_image']): ?>
                        <img src="<?= base_url('uploads/blog/' . $related['featured_image']); ?>" 
                             alt="<?= htmlspecialchars($related['title']); ?>"
                             style="width:80px;height:60px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                    <?php else: ?>
                        <div style="width:80px;height:60px;background:#ede9fe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-newspaper" style="color:#8b5cf6;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="related-blog-info">
                        <h5 style="font-size:0.85rem;font-weight:600;color:#1e293b;line-height:1.3;margin:0 0 4px;"><?= htmlspecialchars($related['title']); ?></h5>
                        <span style="font-size:0.75rem;color:#94a3b8;"><i class="far fa-clock"></i> <?= $related['reading_time']; ?> min read</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Newsletter -->
            <div class="sidebar-card">
                <h4 class="sidebar-title"><i class="fas fa-envelope"></i> Newsletter</h4>
                <p style="color:#666;font-size:0.9rem;margin-bottom:15px;">Get the latest articles and exclusive offers delivered to your inbox.</p>
                <form class="newsletter-form" onsubmit="return subscribeNewsletter(event)">
                    <input type="email" placeholder="Your email address" required>
                    <button type="submit"><i class="fas fa-paper-plane"></i> Subscribe</button>
                </form>
            </div>
            
            <!-- Categories -->
            <div class="sidebar-card">
                <h4 class="sidebar-title"><i class="fas fa-folder"></i> Categories</h4>
                <?php
                $allCategories = $db->query("
                    SELECT c.*, COUNT(b.id) as blog_count 
                    FROM blog_categories c 
                    LEFT JOIN blogs b ON b.category_id = c.id AND b.status = 'published'
                    WHERE c.is_active = 1 
                    GROUP BY c.id 
                    ORDER BY c.sort_order ASC
                ")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($allCategories as $cat): ?>
                    <a href="<?= base_url('blogs.php?category=' . $cat['slug']); ?>" 
                       style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#faf8f5;border-radius:8px;text-decoration:none;color:#333;transition:all 0.2s;">
                        <span><i class="fas <?= $cat['icon']; ?> me-2" style="color:#c5a059;"></i> <?= htmlspecialchars($cat['name']); ?></span>
                        <span style="background:#eee;padding:2px 8px;border-radius:10px;font-size:0.8rem;"><?= $cat['blog_count']; ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</div>

<script>
function toggleFaq(el) {
    const item = el.parentElement;
    item.classList.toggle('open');
}

function copyLink() {
    navigator.clipboard.writeText('<?= $canonicalUrl; ?>');
    alert('Link copied to clipboard!');
}

function subscribeNewsletter(e) {
    e.preventDefault();
    var form = e.target;
    var email = form.querySelector('input[type="email"]').value;
    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';
    fetch('<?= base_url('newsletter_subscribe.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(email)
    }).then(function(r){ return r.json(); }).then(function(d){
        if (d.success) {
            form.innerHTML = '<p style="color:#1a3c34;font-weight:600;text-align:center;padding:10px 0;"><i class="fas fa-check-circle" style="color:#c5a059;margin-right:6px;"></i>Subscribed! Thank you.</p>';
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Subscribe';
            alert(d.message || 'Subscription failed. Please try again.');
        }
    }).catch(function(){
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Subscribe';
    });
    return false;
}

// Smooth scroll for TOC links
document.querySelectorAll('.blog-toc a[href^="#"]').forEach(function(link){
    link.addEventListener('click', function(e){
        e.preventDefault();
        var target = document.querySelector(this.getAttribute('href'));
        if (target) target.scrollIntoView({behavior:'smooth', block:'start'});
        history.replaceState(null, '', this.getAttribute('href'));
    });
});
// Add rel=noopener to external links for security
document.querySelectorAll('.blog-content a[href^="http"]').forEach(function(a){
    if (!a.hostname.includes('gilafstore.com')) {
        a.setAttribute('rel', 'noopener noreferrer');
        a.setAttribute('target', '_blank');
    }
});

// Inline Related Reading block — injected after 2nd h2 for internal linking
(function(){
    var relatedPosts = <?= json_encode(array_values(array_map(function($r) {
        return [
            'title'  => $r['title'],
            'slug'   => $r['slug'],
            'img'    => $r['featured_image'] ? base_url('uploads/blog/' . $r['featured_image']) : '',
            'time'   => $r['reading_time']
        ];
    }, $allRelatedBlogs)), JSON_UNESCAPED_SLASHES); ?>;

    if (!relatedPosts || relatedPosts.length === 0) return;
    var content = document.querySelector('.blog-content');
    if (!content) return;
    var h2s = content.querySelectorAll('h2');
    var insertAfterEl = h2s.length >= 2 ? h2s[1] : (h2s.length === 1 ? h2s[0] : null);
    if (!insertAfterEl) return;

    var links = relatedPosts.map(function(p){
        var img = p.img ? '<img src="' + p.img + '" alt="' + p.title.replace(/"/g,'&quot;') + '" loading="lazy">' : '';
        return '<a href="<?= base_url('blog/'); ?>' + p.slug + '" class="related-reading-link">' +
               img + '<span>' + p.title + '<small><i class="far fa-clock"></i> ' + p.time + ' min read</small></span></a>';
    }).join('');

    var block = document.createElement('div');
    block.className = 'related-reading-block';
    block.innerHTML = '<div class="related-reading-title"><i class="fas fa-bookmark"></i> Related Reading</div>' +
                      '<div class="related-reading-links">' + links + '</div>';

    insertAfterEl.parentNode.insertBefore(block, insertAfterEl.nextSibling);
})();
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
