-- FAQ System Database Schema
-- This creates the table structure for the centralized FAQ management system

-- Create FAQs table
CREATE TABLE IF NOT EXISTS `faqs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `question` TEXT NOT NULL,
    `answer` TEXT NOT NULL,
    `keywords` TEXT DEFAULT NULL COMMENT 'Comma-separated keywords for better matching',
    `category` VARCHAR(100) DEFAULT 'General' COMMENT 'FAQ category (e.g., Shipping, Returns, Products)',
    `priority` INT(11) DEFAULT 0 COMMENT 'Higher priority FAQs shown first when multiple matches',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT '1 = Active, 0 = Disabled',
    `view_count` INT(11) DEFAULT 0 COMMENT 'Number of times this FAQ was viewed',
    `helpful_count` INT(11) DEFAULT 0 COMMENT 'Number of times marked as helpful',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT(11) DEFAULT NULL COMMENT 'Admin user ID who created this FAQ',
    `updated_by` INT(11) DEFAULT NULL COMMENT 'Admin user ID who last updated this FAQ',
    PRIMARY KEY (`id`),
    FULLTEXT KEY `idx_question_answer` (`question`, `answer`),
    KEY `idx_category` (`category`),
    KEY `idx_active` (`is_active`),
    KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default FAQs
INSERT INTO `faqs` (`question`, `answer`, `keywords`, `category`, `priority`, `is_active`) VALUES
('What is your return policy?', 'We offer a 7-day return policy for all products. Items must be unused, in original packaging, and accompanied by the original invoice. Perishable items like honey and saffron are non-returnable unless damaged or defective. To initiate a return, please contact our support team or create a ticket from your account.', 'return, refund, exchange, policy, 7 days, money back', 'Returns & Refunds', 10, 1),

('How long does shipping take?', 'Standard shipping takes 3-7 business days depending on your location. Express shipping (1-3 days) is available for select pincodes. Free shipping is available on orders above ₹500. You can track your order using the tracking ID sent to your email or from your account dashboard.', 'shipping, delivery, time, days, tracking, courier', 'Shipping & Delivery', 10, 1),

('How can I verify product authenticity?', 'Every Gilaf product comes with a unique batch code and QR code. You can verify authenticity by:\n1. Scanning the QR code on the product packaging\n2. Visiting our Authenticity Check section on the website\n3. Entering the batch code manually\n\nOur verification system will show you the batch details, manufacturing date, expiry date, and lab test reports.', 'verify, authentic, genuine, fake, batch code, qr code, verification', 'Product Authenticity', 10, 1),

('What payment methods do you accept?', 'We accept multiple payment methods:\n- UPI (Google Pay, PhonePe, Paytm, etc.)\n- Credit/Debit Cards (Visa, Mastercard, RuPay)\n- Net Banking\n- Cash on Delivery (COD) - Available for select locations\n\nAll payments are secure and encrypted. For UPI payments, you will need to enter your transaction ID after completing the payment.', 'payment, upi, card, cod, cash on delivery, net banking, pay', 'Payment & Billing', 9, 1),

('How do I track my order?', 'You can track your order in multiple ways:\n1. Click "Track Order" in the footer and enter your Tracking ID\n2. Log in to your account and go to "My Orders"\n3. Check the tracking link sent to your email\n4. Use the tracking ID with the courier partner directly\n\nYou will receive real-time updates on your order status via email and SMS.', 'track, order, status, tracking id, courier, delivery status', 'Order Tracking', 9, 1),

('What is your cancellation policy?', 'You can cancel your order before it is shipped. Once shipped, cancellation is not possible, but you can return the product after delivery. To cancel:\n1. Go to "My Orders" in your account\n2. Select the order you want to cancel\n3. Click "Cancel Order" and confirm\n\nRefunds for cancelled orders are processed within 5-7 business days.', 'cancel, cancellation, order cancel, stop order', 'Order Management', 8, 1),

('Are your products organic and lab-tested?', 'Yes! All Gilaf products are:\n- 100% Natural and Pure\n- Lab-tested for quality and authenticity\n- Free from artificial colors, preservatives, and additives\n- Sourced directly from Kashmir valleys\n\nEach product comes with lab test reports that you can view by scanning the QR code or checking the batch verification page.', 'organic, lab test, natural, pure, quality, certificate, tested', 'Product Quality', 9, 1),

('How do I become a distributor or reseller?', 'We welcome partnerships! To become a Gilaf distributor or reseller:\n1. Click "Become a Distributor" in the footer\n2. Fill out the application form with your business details\n3. Upload required documents (GST, business license, etc.)\n4. Our team will review your application within 3-5 business days\n5. Once approved, you will receive login credentials for the distributor portal\n\nBenefits include bulk pricing, territory rights, and marketing support.', 'distributor, reseller, partner, wholesale, bulk, business, apply', 'Business Partnership', 7, 1),

('What is your customer support contact?', 'You can reach our customer support team through:\n- Create a Support Ticket: Log in to your account and go to "Support"\n- Email: support@gilafstore.com\n- Phone: +91-XXXXXXXXXX (Mon-Sat, 10 AM - 6 PM IST)\n- Live Chat: Available on our website\n\nWe typically respond within 24 hours. For urgent issues, please call us directly.', 'contact, support, help, customer care, phone, email, ticket', 'Customer Support', 8, 1),

('Do you ship internationally?', 'Yes, we ship to select international locations including USA, UK, UAE, Canada, Australia, and other countries. International shipping charges vary by destination and weight. Estimated delivery time is 7-15 business days. Customs duties and taxes may apply based on your country''s regulations. Please check the shipping calculator at checkout for exact charges.', 'international, shipping, abroad, overseas, export, foreign, global', 'Shipping & Delivery', 7, 1),

('How do I change or update my delivery address?', 'To change your delivery address:\n1. Log in to your account\n2. Go to "Manage Addresses"\n3. Add a new address or edit existing ones\n4. Set your preferred address as default\n\nNote: You can only change the delivery address before the order is shipped. Once shipped, address changes are not possible. Please ensure your address is correct before placing an order.', 'address, change address, delivery address, update address, wrong address', 'Order Management', 7, 1),

('What if I receive a damaged or defective product?', 'We sincerely apologize for any inconvenience. If you receive a damaged or defective product:\n1. Take clear photos of the product and packaging\n2. Create a support ticket or contact us within 48 hours of delivery\n3. Provide your order ID and photos\n4. We will arrange a free pickup and send a replacement or issue a full refund\n\nYour satisfaction is our priority, and we will resolve the issue immediately.', 'damaged, defective, broken, wrong product, quality issue, complaint', 'Returns & Refunds', 10, 1),

('How can I apply a coupon or discount code?', 'To apply a coupon code:\n1. Add products to your cart\n2. Go to the cart page\n3. Look for the "Apply Coupon" or "Discount Code" field\n4. Enter your coupon code and click "Apply"\n5. The discount will be reflected in your total amount\n\nNote: Only one coupon can be used per order. Coupons cannot be combined with other offers. Check the coupon terms and conditions for validity and minimum order requirements.', 'coupon, discount, promo code, offer, voucher, apply coupon', 'Payment & Billing', 6, 1),

('What are your business hours?', 'Our business hours are:\n- Customer Support: Monday to Saturday, 10:00 AM - 6:00 PM IST\n- Order Processing: Monday to Saturday\n- Warehouse Operations: Monday to Saturday\n- Closed on Sundays and National Holidays\n\nYou can place orders 24/7 on our website. Orders placed after business hours or on holidays will be processed on the next working day.', 'business hours, timing, open, closed, working hours, office hours', 'General Information', 5, 1),

('Do you have a physical store or showroom?', 'Yes! You can visit our authorized Gilaf stores and distributors across India. To find the nearest store:\n1. Click "Find Gilaf Stores" in the footer\n2. Enter your location or pincode\n3. View nearby stores with addresses and contact details\n4. Get directions via Google Maps\n\nYou can also purchase products directly from our website with home delivery.', 'store, shop, physical store, showroom, retail, visit, location, near me', 'Store Locations', 6, 1);

-- Create FAQ analytics table for tracking
CREATE TABLE IF NOT EXISTS `faq_analytics` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `faq_id` INT(11) NOT NULL,
    `user_query` TEXT DEFAULT NULL COMMENT 'Original user question',
    `matched_keywords` TEXT DEFAULT NULL COMMENT 'Keywords that matched',
    `relevance_score` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Matching score (0-100)',
    `was_helpful` TINYINT(1) DEFAULT NULL COMMENT '1 = Helpful, 0 = Not helpful, NULL = No feedback',
    `user_feedback` TEXT DEFAULT NULL COMMENT 'Optional user feedback',
    `session_id` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_faq_id` (`faq_id`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`faq_id`) REFERENCES `faqs`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create FAQ categories table for better organization
CREATE TABLE IF NOT EXISTS `faq_categories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Font Awesome icon class',
    `display_order` INT(11) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default FAQ categories
INSERT INTO `faq_categories` (`name`, `description`, `icon`, `display_order`, `is_active`) VALUES
('General Information', 'General questions about Gilaf Store', 'fa-info-circle', 1, 1),
('Product Quality', 'Questions about product authenticity and quality', 'fa-certificate', 2, 1),
('Product Authenticity', 'Verification and authenticity checks', 'fa-shield-alt', 3, 1),
('Shipping & Delivery', 'Shipping, delivery, and tracking information', 'fa-shipping-fast', 4, 1),
('Order Management', 'Order placement, cancellation, and modifications', 'fa-shopping-cart', 5, 1),
('Order Tracking', 'Track your order status', 'fa-map-marker-alt', 6, 1),
('Payment & Billing', 'Payment methods and billing queries', 'fa-credit-card', 7, 1),
('Returns & Refunds', 'Return policy and refund process', 'fa-undo', 8, 1),
('Customer Support', 'Contact and support information', 'fa-headset', 9, 1),
('Business Partnership', 'Distributor and reseller information', 'fa-handshake', 10, 1),
('Store Locations', 'Physical store and showroom locations', 'fa-store', 11, 1);

-- Add indexes for better search performance
ALTER TABLE `faqs` ADD INDEX `idx_keywords` (`keywords`(255));
ALTER TABLE `faqs` ADD INDEX `idx_view_count` (`view_count`);
ALTER TABLE `faqs` ADD INDEX `idx_helpful_count` (`helpful_count`);

-- Migration complete
SELECT 'FAQ System database schema created successfully!' as status;
