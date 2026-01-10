-- ============================================
-- GILAF STORE - DATABASE SCHEMA UPDATES
-- Enterprise Features: CMS, Banners, Batches
-- ============================================

-- 1. BANNERS TABLE (Admin-controlled homepage banners)
CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `subtitle` TEXT,
  `image` VARCHAR(255) NOT NULL,
  `link_url` VARCHAR(500),
  `button_text` VARCHAR(100),
  `position` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. BATCH CODES TABLE (Product authenticity verification)
CREATE TABLE IF NOT EXISTS `batch_codes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `batch_code` VARCHAR(50) NOT NULL UNIQUE,
  `product_id` INT(11) NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `grade` VARCHAR(100),
  `net_weight` VARCHAR(100) NOT NULL,
  `manufacturing_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `country_of_origin` VARCHAR(255) DEFAULT 'India (Pampore, Kashmir)',
  `lab_report_url` VARCHAR(500),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CMS PAGES TABLE (Static content management)
CREATE TABLE IF NOT EXISTS `cms_pages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `meta_description` TEXT,
  `parent_id` INT(11) DEFAULT NULL,
  `menu_order` INT(11) DEFAULT 0,
  `is_published` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. SITE SETTINGS TABLE (Global site configuration)
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  `setting_type` ENUM('text','textarea','image','number','boolean') DEFAULT 'text',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Add search_keywords column to products table (if not exists)
-- Note: Comment out if column already exists
-- ALTER TABLE `products` 
-- ADD COLUMN IF NOT EXISTS `search_keywords` TEXT AFTER `description`;

-- 6. Add is_featured column to categories (for homepage display)
-- Note: Comment out if columns already exist
-- ALTER TABLE `categories` 
-- ADD COLUMN IF NOT EXISTS `is_featured` TINYINT(1) DEFAULT 0 AFTER `name`,
-- ADD COLUMN IF NOT EXISTS `icon` VARCHAR(100) AFTER `is_featured`,
-- ADD COLUMN IF NOT EXISTS `display_order` INT(11) DEFAULT 0 AFTER `icon`;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Default CMS Pages (Policies & Legal)
INSERT IGNORE INTO `cms_pages` (`slug`, `title`, `content`, `meta_description`, `is_published`) VALUES
('privacy-policy', 'Privacy Policy', '<h1>Privacy Policy</h1><p>Your privacy is important to us. This policy outlines how we collect, use, and protect your personal information.</p>', 'Learn about how Gilaf Store protects your privacy', 1),
('terms-conditions', 'Terms & Conditions', '<h1>Terms & Conditions</h1><p>By accessing and using this website, you accept and agree to be bound by the terms and provision of this agreement.</p>', 'Terms and conditions for using Gilaf Store', 1),
('shipping-policy', 'Shipping Policy', '<h1>Shipping Policy</h1><p>We offer free domestic shipping across India and international shipping to 15+ countries.</p>', 'Shipping policy and delivery information', 1),
('refund-return-policy', 'Refund & Return Policy', '<h1>Refund & Return Policy</h1><p>We accept returns within 7 days of delivery for unopened products.</p>', 'Return and refund policy details', 1),
('cancellation-policy', 'Order Cancellation Policy', '<h1>Order Cancellation Policy</h1><p>Orders can be cancelled within 24 hours of placement.</p>', 'How to cancel your order', 1),
('payment-policy', 'Payment Policy', '<h1>Payment Policy</h1><p>We accept all major payment methods including credit cards, debit cards, UPI, and net banking.</p>', 'Secure payment options available', 1),
('disclaimer', 'Disclaimer', '<h1>Disclaimer</h1><p>The information provided on this website is for general informational purposes only.</p>', 'Legal disclaimer for Gilaf Store', 1),
('contact-us', 'Contact Us', '<h1>Contact Us</h1><p>Email: gilafstore@gmail.com<br>Phone: +91 99000 12345<br>Address: Srinagar, Kashmir, India</p>', 'Get in touch with Gilaf Store', 1),
('become-distributor', 'Become a Distributor', '<h1>Become a Distributor</h1><p>Join our network of distributors and grow your business with authentic Kashmiri products.</p>', 'Partner with Gilaf Store as a distributor', 1),
('faqs', 'Frequently Asked Questions', '<h1>FAQs</h1><h3>Q: How do I track my order?</h3><p>A: Use the tracking feature in the navigation menu.</p>', 'Common questions about Gilaf Store', 1),
('shipping-logistics', 'Shipping & Logistics', '<h1>Shipping & Logistics</h1><p>We partner with trusted courier services for safe and timely delivery.</p>', 'Shipping and logistics information', 1);

-- Default Site Settings
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('site_name', 'Gilaf Store', 'text'),
('site_tagline', 'Taste • Culture • Craft', 'text'),
('contact_email', 'gilafstore@gmail.com', 'text'),
('contact_phone', '+91 99000 12345', 'text'),
('contact_address', 'Srinagar, Kashmir, India', 'textarea'),
('hero_title', 'The Essence of Purity & Tradition', 'text'),
('hero_subtitle', 'Experience the finest saffron, unadulterated honey, and hand-selected spices from the valleys of Kashmir.', 'textarea'),
('free_shipping_threshold', '500', 'number'),
('currency_symbol', '₹', 'text');

-- Sample Batch Codes (for testing)
INSERT IGNORE INTO `batch_codes` (`batch_code`, `product_id`, `product_name`, `grade`, `net_weight`, `manufacturing_date`, `expiry_date`, `country_of_origin`, `is_active`) VALUES
('GF-2025-01', 1, 'Mogra Saffron', 'Grade A', '5 grams', '2024-10-01', '2026-10-01', 'India (Pampore, Kashmir)', 1),
('GF-2025-02', 2, 'Raw Acacia Honey', 'Premium', '500 grams', '2024-11-15', '2026-11-15', 'India (Srinagar Valley)', 1),
('GF-2025-03', 3, 'Kashmiri Walnuts', 'Premium', '250 grams', '2024-12-01', '2025-12-01', 'India (Kupwara)', 1);

-- Sample Banners
INSERT IGNORE INTO `banners` (`title`, `subtitle`, `image`, `link_url`, `button_text`, `position`, `is_active`) VALUES
('Premium Heritage Foods', 'Experience the finest saffron and honey from Kashmir', 'hero-banner-1.jpg', '/shop.php', 'Shop Collection', 1, 1),
('Certified Organic Products', 'Sourced directly from certified farms', 'hero-banner-2.jpg', '/shop.php?category=organic', 'Explore Organic', 2, 1);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================
-- Note: Comment out if indexes already exist to avoid duplicate key errors
-- ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_name (name(191));
-- ALTER TABLE batch_codes ADD INDEX IF NOT EXISTS idx_batch_active (is_active, batch_code);
-- ALTER TABLE cms_pages ADD INDEX IF NOT EXISTS idx_cms_published (is_published, slug(191));
-- ALTER TABLE banners ADD INDEX IF NOT EXISTS idx_banners_active (is_active, position);
