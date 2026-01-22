<?php
/**
 * Blog Listing Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Blogs - Gilaf Store';
$metaDescription = 'Read the latest stories, insights, and updates from Gilaf Store.';

// Sample blog posts (replace with database query when ready)
$posts = [
    [
        'id' => 1,
        'title' => 'Welcome to Gilaf Store Blog',
        'excerpt' => 'Discover the story behind our authentic cultural products and artisanal crafts.',
        'date' => date('M d, Y'),
        'category' => 'Company News',
        'image' => 'uploads/blog/gilaf-store-storefront.jpg.jpeg'
    ],
    [
        'id' => 2,
        'title' => 'The Art of Traditional Craftsmanship',
        'excerpt' => 'Learn about the skilled artisans who create our unique products.',
        'date' => date('M d, Y', strtotime('-7 days')),
        'category' => 'Product Stories',
        'image' => 'uploads/blog/traditional-craftsmanship.jpg.png'
    ],
    [
        'id' => 3,
        'title' => 'Celebrating Cultural Diversity',
        'excerpt' => 'How we bring authentic cultural experiences to your doorstep.',
        'date' => date('M d, Y', strtotime('-14 days')),
        'category' => 'Cultural Insights',
        'image' => 'uploads/blog/Cultural-Diversity.jpg.jpg'
    ]
];

include __DIR__ . '/includes/new-header.php';
?>

<style>
    /* Blog Hero - Using same container style as About Us */
    .blog-hero {
        background-color: var(--color-ivory, #FAF8F5);
        padding: 50px 20px 25px 20px;
        text-align: center;
    }
    .blog-hero-content {
        background: #fff;
        border-radius: 12px;
        padding: 30px 40px;
        max-width: 1200px;
        margin: 0 auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(197, 160, 89, 0.2);
    }
    .blog-hero h1 {
        font-size: 3.0rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1A3C34;
    }
    .blog-hero p {
        font-size: 1.3rem;
        max-width: 800px;
        margin: 0 auto;
        color: #C9A961;
        text-transform: uppercase;
        letter-spacing: 2px;
    }
    
    .blog-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 40px 20px 80px 20px;
    }
    
    .blog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 40px;
    }
    
    .blog-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }
    .blog-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.15);
    }
    
    .blog-image {
        width: 100%;
        height: 300px;
        background: linear-gradient(135deg, #f0f0f0 0%, #e0e0e0 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
        font-size: 3rem;
        overflow: hidden;
        position: relative;
    }
    .blog-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        background: #f5f5f5;
    }
    .blog-image i {
        position: absolute;
        z-index: 1;
    }
    
    .blog-content {
        padding: 30px;
    }
    
    .blog-category {
        display: inline-block;
        padding: 6px 15px;
        background: rgba(197,160,89,0.1);
        color: #C5A059;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 15px;
        text-transform: uppercase;
    }
    
    .blog-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1A3C34;
        margin-bottom: 15px;
        line-height: 1.3;
    }
    
    .blog-excerpt {
        font-size: 0.95rem;
        line-height: 1.6;
        color: #666;
        margin-bottom: 20px;
    }
    
    .blog-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: #999;
        padding-top: 20px;
        border-top: 1px solid #f0f0f0;
    }
    
    .read-more {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #1A3C34;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .read-more:hover {
        color: #C5A059;
        gap: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        color: #999;
    }
    .empty-state i {
        font-size: 5rem;
        margin-bottom: 30px;
        opacity: 0.2;
    }
    
    @media (max-width: 768px) {
        .blog-hero h1 { font-size: 2.5rem; }
        .blog-grid {
            grid-template-columns: 1fr;
            gap: 30px;
        }
    }
</style>

<!-- Hero Section -->
<div class="blog-hero">
    <div class="blog-hero-content">
        <h1>Our Blogs</h1>
        <p>Stories, insights, and updates from Gilaf Store</p>
    </div>
</div>

<!-- Blog Container -->
<div class="blog-container">
    <?php if (!empty($posts)): ?>
    <div class="blog-grid">
        <?php foreach ($posts as $post): 
            // Set icon based on category
            $categoryIcons = [
                'Company News' => 'fa-building',
                'Product Stories' => 'fa-box-open',
                'Cultural Insights' => 'fa-globe-asia'
            ];
            $icon = $categoryIcons[$post['category']] ?? 'fa-newspaper';
        ?>
        <article class="blog-card">
            <div class="blog-image">
                <?php if (!empty($post['image'])): ?>
                    <img src="<?= htmlspecialchars($post['image']); ?>" alt="<?= htmlspecialchars($post['title']); ?>">
                <?php else: ?>
                    <i class="fas <?= $icon; ?>"></i>
                <?php endif; ?>
            </div>
            
            <div class="blog-content">
                <span class="blog-category"><?= htmlspecialchars($post['category']); ?></span>
                
                <h2 class="blog-title"><?= htmlspecialchars($post['title']); ?></h2>
                
                <p class="blog-excerpt"><?= htmlspecialchars($post['excerpt']); ?></p>
                
                <a href="#" class="read-more">
                    Read More <i class="fas fa-arrow-right"></i>
                </a>
                
                <div class="blog-meta">
                    <i class="far fa-calendar"></i>
                    <?= $post['date']; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-newspaper"></i>
        <h2>No Posts Yet</h2>
        <p>Check back soon for new stories and updates!</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
