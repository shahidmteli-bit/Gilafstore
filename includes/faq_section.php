<?php
/**
 * Reusable FAQ Section Component
 * Displays FAQs from database with accordion functionality
 * 
 * Usage: include this file and call display_faq_section($category, $title)
 */

function display_faq_section($category = null, $title = 'Frequently Asked Questions', $limit = 10) {
    $pdo = get_db_connection();
    
    // Build query based on category filter
    if ($category) {
        $stmt = $pdo->prepare("
            SELECT id, question, answer, category 
            FROM faqs 
            WHERE is_active = 1 AND category = ?
            ORDER BY priority DESC, view_count DESC 
            LIMIT " . (int)$limit
        );
        $stmt->execute([$category]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, question, answer, category 
            FROM faqs 
            WHERE is_active = 1 
            ORDER BY priority DESC, view_count DESC 
            LIMIT " . (int)$limit
        );
        $stmt->execute();
    }
    
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($faqs)) {
        return; // Don't display anything if no FAQs
    }
    
    // Generate unique ID for this FAQ section
    $sectionId = 'faq-' . uniqid();
    ?>
    
    <style>
        .faq-section {
            max-width: 1000px;
            margin: 80px auto;
            padding: 0 20px;
        }
        
        .faq-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .faq-header h2 {
            font-size: 2.5rem;
            color: #1A3C34;
            margin-bottom: 15px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
        }
        
        .faq-header p {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .faq-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .faq-item {
            background: #fff;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            border-color: #C9A961;
            box-shadow: 0 4px 12px rgba(201, 169, 97, 0.15);
        }
        
        .faq-item.active {
            border-color: #C9A961;
        }
        
        .faq-question {
            padding: 20px 25px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            transition: background 0.3s ease;
            user-select: none;
        }
        
        .faq-question:hover {
            background: #f8f9fa;
        }
        
        .faq-item.active .faq-question {
            background: #f8f9fa;
        }
        
        .faq-question-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1A3C34;
            flex: 1;
            padding-right: 20px;
        }
        
        .faq-icon {
            font-size: 1.2rem;
            color: #C9A961;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        
        .faq-item.active .faq-icon {
            transform: rotate(180deg);
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .faq-item.active .faq-answer {
            max-height: 1000px;
        }
        
        .faq-answer-content {
            padding: 0 25px 20px 25px;
            color: #555;
            font-size: 1.05rem;
            line-height: 1.8;
        }
        
        .faq-category-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .faq-section {
                margin: 60px auto;
            }
            
            .faq-header h2 {
                font-size: 2rem;
            }
            
            .faq-question {
                padding: 15px 20px;
            }
            
            .faq-question-text {
                font-size: 1rem;
            }
            
            .faq-answer-content {
                padding: 0 20px 15px 20px;
                font-size: 1rem;
            }
        }
    </style>
    
    <section class="faq-section" id="<?= $sectionId; ?>">
        <div class="faq-header">
            <h2><?= htmlspecialchars($title); ?></h2>
            <p>Find answers to commonly asked questions</p>
        </div>
        
        <div class="faq-container">
            <?php foreach ($faqs as $index => $faq): ?>
                <div class="faq-item" data-faq-id="<?= $faq['id']; ?>">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <div>
                            <?php if ($faq['category']): ?>
                                <div class="faq-category-badge"><?= htmlspecialchars($faq['category']); ?></div>
                            <?php endif; ?>
                            <div class="faq-question-text"><?= htmlspecialchars($faq['question']); ?></div>
                        </div>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </div>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <?= nl2br(htmlspecialchars($faq['answer'])); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    
    <script>
        function toggleFAQ(element) {
            const faqItem = element.closest('.faq-item');
            const isActive = faqItem.classList.contains('active');
            
            // Close all other FAQs in this section
            const section = element.closest('.faq-section');
            section.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Toggle current FAQ
            if (!isActive) {
                faqItem.classList.add('active');
                
                // Track FAQ view
                const faqId = faqItem.getAttribute('data-faq-id');
                if (faqId) {
                    trackFAQView(faqId);
                }
            }
        }
        
        function trackFAQView(faqId) {
            // Send view tracking to backend
            fetch('<?= base_url('api/faq_feedback.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    faq_id: faqId,
                    action: 'view'
                })
            }).catch(err => console.log('FAQ tracking error:', err));
        }
    </script>
    
    <?php
}
?>
