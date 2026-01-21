<?php
/**
 * FAQs Page - Frequently Asked Questions
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'FAQs - Gilaf Store';
$metaDescription = 'Find answers to frequently asked questions about Gilaf Store products, shipping, returns, and more.';

include __DIR__ . '/includes/new-header.php';
?>

<style>
    .faqs-hero {
        background: linear-gradient(135deg, #C9A961 0%, #D4B76A 20%, #1A3C34 60%, #244A36 100%);
        color: white;
        padding: 120px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .faqs-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 30% 40%, rgba(201, 169, 97, 0.2) 0%, transparent 60%), 
                    radial-gradient(circle at 70% 60%, rgba(26, 60, 52, 0.3) 0%, transparent 60%);
        opacity: 0.25;
        z-index: 0;
    }
    .faqs-hero::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #C9A961 0%, #FFFFFF 50%, #C9A961 100%);
        z-index: 2;
    }
    .faqs-hero h1 {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        font-family: 'Poppins', sans-serif;
    }
    .faqs-hero p {
        font-size: 1.3rem;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.95;
        position: relative;
        z-index: 1;
        font-family: 'Poppins', sans-serif;
    }
    
    .faqs-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 80px 20px;
    }
    
    .faq-categories {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 60px;
    }
    
    .category-card {
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 25px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .category-card:hover {
        border-color: #C9A961;
        box-shadow: 0 4px 12px rgba(201, 169, 97, 0.2);
        transform: translateY(-2px);
    }
    
    .category-card.active {
        border-color: #C9A961;
        background: #fff9e6;
    }
    
    .category-card i {
        font-size: 2.5rem;
        color: #C9A961;
        margin-bottom: 15px;
    }
    
    .category-card h3 {
        font-size: 1.2rem;
        color: #1A3C34;
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .category-card p {
        font-size: 0.9rem;
        color: #666;
        margin: 0;
    }
    
    .faq-content-panel {
        background: #ffffff;
        border-radius: 12px;
        padding: 40px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e8e8e8;
        margin-top: 20px;
        min-height: 400px;
        transition: all 0.3s ease;
    }
    
    .faq-content-panel .faq-section {
        margin: 0;
    }
    
    .faq-content-panel .faq-header {
        text-align: center;
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .faq-content-panel .faq-header h2 {
        font-size: 2.2rem;
        color: #1A3C34;
        margin-bottom: 12px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }
    
    .faq-content-panel .faq-header h2::before {
        content: '';
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, transparent, #C9A961);
    }
    
    .faq-content-panel .faq-header h2::after {
        content: '';
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, #C9A961, transparent);
    }
    
    .faq-content-panel .faq-header p {
        font-size: 1.05rem;
        color: #777;
        margin: 0;
    }
    
    .faq-content-panel .faq-container {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    .faq-content-panel .faq-item {
        background: #ffffff;
        border: 2px solid #e8e8e8;
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }
    
    .faq-content-panel .faq-item:hover {
        border-color: #C9A961;
        box-shadow: 0 4px 16px rgba(201, 169, 97, 0.15);
        transform: translateY(-2px);
    }
    
    .faq-content-panel .faq-item.active {
        border-color: #C9A961;
        box-shadow: 0 6px 20px rgba(201, 169, 97, 0.2);
    }
    
    .faq-content-panel .faq-question {
        padding: 22px 28px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(to right, #ffffff 0%, #fafafa 100%);
        transition: all 0.3s ease;
        user-select: none;
    }
    
    .faq-content-panel .faq-question:hover {
        background: linear-gradient(to right, #fff9e6 0%, #fffbf0 100%);
    }
    
    .faq-content-panel .faq-item.active .faq-question {
        background: linear-gradient(to right, #fff9e6 0%, #fffbf0 100%);
        border-bottom: 1px solid #f0e8d0;
    }
    
    .faq-content-panel .faq-category-badge {
        display: inline-block;
        background: linear-gradient(135deg, #C9A961 0%, #D4B76A 100%);
        color: white;
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 10px;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 6px rgba(201, 169, 97, 0.3);
    }
    
    .faq-content-panel .faq-question-text {
        font-size: 1.15rem;
        font-weight: 600;
        color: #1A3C34;
        flex: 1;
        padding-right: 20px;
        line-height: 1.5;
    }
    
    .faq-content-panel .faq-icon {
        font-size: 1.3rem;
        color: #C9A961;
        transition: transform 0.3s ease;
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .faq-content-panel .faq-item.active .faq-icon {
        transform: rotate(180deg);
        color: #1A3C34;
    }
    
    .faq-content-panel .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s ease, padding 0.4s ease;
        background: #ffffff;
    }
    
    .faq-content-panel .faq-item.active .faq-answer {
        max-height: 1500px;
        border-top: 1px solid #f5f5f5;
    }
    
    .faq-content-panel .faq-answer-content {
        padding: 28px;
        color: #555;
        font-size: 1.05rem;
        line-height: 1.9;
        background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
    }
    
    .faq-content-panel .faq-answer-content p {
        margin-bottom: 12px;
    }
    
    .faq-content-panel .faq-answer-content ul,
    .faq-content-panel .faq-answer-content ol {
        margin: 12px 0;
        padding-left: 24px;
    }
    
    .faq-content-panel .faq-answer-content li {
        margin-bottom: 8px;
        line-height: 1.8;
    }
    
    @media (max-width: 768px) {
        .faqs-hero h1 { font-size: 2.5rem; }
        .faq-categories { grid-template-columns: 1fr; }
        
        .faq-content-panel {
            padding: 25px 20px;
        }
        
        .faq-content-panel .faq-header h2 {
            font-size: 1.8rem;
        }
        
        .faq-content-panel .faq-header h2::before,
        .faq-content-panel .faq-header h2::after {
            width: 20px;
        }
        
        .faq-content-panel .faq-question {
            padding: 18px 20px;
        }
        
        .faq-content-panel .faq-question-text {
            font-size: 1.05rem;
        }
        
        .faq-content-panel .faq-answer-content {
            padding: 20px;
            font-size: 1rem;
        }
    }
</style>

<!-- Hero Section -->
<div class="faqs-hero">
    <h1>Frequently Asked Questions</h1>
    <p>Find answers to common questions about our products, services, and policies</p>
</div>

<!-- FAQs Content -->
<div class="faqs-container">
    <?php
    // Get FAQ categories
    $pdo = get_db_connection();
    $categories = $pdo->query("
        SELECT DISTINCT category 
        FROM faqs 
        WHERE is_active = 1 
        ORDER BY category
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($categories)):
    ?>
    
    <div class="faq-categories">
        <div class="category-card active" onclick="filterFAQs('all')">
            <i class="fas fa-list"></i>
            <h3>All FAQs</h3>
            <p>View all questions</p>
        </div>
        <?php 
        $categoryIcons = [
            'General Information' => 'fa-info-circle',
            'Product Quality' => 'fa-certificate',
            'Product Authenticity' => 'fa-shield-alt',
            'Shipping & Delivery' => 'fa-shipping-fast',
            'Order Management' => 'fa-shopping-cart',
            'Order Tracking' => 'fa-map-marker-alt',
            'Payment & Billing' => 'fa-credit-card',
            'Returns & Refunds' => 'fa-undo',
            'Customer Support' => 'fa-headset',
            'Business Partnership' => 'fa-handshake',
            'Store Locations' => 'fa-store'
        ];
        
        foreach ($categories as $category): 
            $icon = $categoryIcons[$category] ?? 'fa-question-circle';
        ?>
        <div class="category-card" onclick="filterFAQs('<?= htmlspecialchars($category); ?>')">
            <i class="fas <?= $icon; ?>"></i>
            <h3><?= htmlspecialchars($category); ?></h3>
            <p>View questions</p>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
    
    <div id="faq-display-area" class="faq-content-panel">
        <?php
        // Display all FAQs by default
        require_once __DIR__ . '/includes/faq_section.php';
        display_faq_section(null, 'All Questions', 50);
        ?>
    </div>
</div>

<script>
function filterFAQs(category) {
    // Update active category card
    document.querySelectorAll('.category-card').forEach(card => {
        card.classList.remove('active');
    });
    event.target.closest('.category-card').classList.add('active');
    
    // Reload FAQs with category filter
    const displayArea = document.getElementById('faq-display-area');
    displayArea.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #C9A961;"></i></div>';
    
    // Fetch filtered FAQs
    fetch('<?= base_url('api/faq_categories.php'); ?>?category=' + encodeURIComponent(category))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.faqs && data.faqs.length > 0) {
                displayArea.innerHTML = buildFAQHTML(data.faqs, category === 'all' ? 'All Questions' : category);
            } else {
                displayArea.innerHTML = '<div style="text-align: center; padding: 40px; color: #666;"><i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.3;"></i><p>No FAQs found in this category.</p></div>';
            }
        })
        .catch(error => {
            console.error('Error loading FAQs:', error);
            displayArea.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc3545;">Error loading FAQs. Please refresh the page.</div>';
        });
}

function buildFAQHTML(faqs, title) {
    let html = `
        <section class="faq-section">
            <div class="faq-header">
                <h2>${escapeHtml(title)}</h2>
                <p>Find answers to commonly asked questions</p>
            </div>
            <div class="faq-container">
    `;
    
    faqs.forEach(faq => {
        html += `
            <div class="faq-item" data-faq-id="${faq.id}">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    <div>
                        ${faq.category ? '<div class="faq-category-badge">' + escapeHtml(faq.category) + '</div>' : ''}
                        <div class="faq-question-text">${escapeHtml(faq.question)}</div>
                    </div>
                    <i class="fas fa-chevron-down faq-icon"></i>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-content">${escapeHtml(faq.answer).replace(/\n/g, '<br>')}</div>
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
        </section>
    `;
    return html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
