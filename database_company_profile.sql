-- Company Profile & Shipping Label System Migration
-- Run this SQL in phpMyAdmin or MySQL CLI

CREATE TABLE IF NOT EXISTS `company_profile` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL DEFAULT '',
  `brand_name` varchar(255) NOT NULL DEFAULT '',
  `tagline` varchar(500) DEFAULT '',
  `logo_web` varchar(500) DEFAULT '' COMMENT 'Optimized logo for web/screen',
  `logo_print` varchar(500) DEFAULT '' COMMENT 'High-res logo for print (300 DPI)',
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT '',
  `state` varchar(100) DEFAULT '',
  `pincode` varchar(10) DEFAULT '',
  `country` varchar(100) DEFAULT 'India',
  `phone` varchar(20) DEFAULT '',
  `email` varchar(255) DEFAULT '',
  `website` varchar(255) DEFAULT '',
  `gstin` varchar(20) DEFAULT '',
  `pan_number` varchar(15) DEFAULT '',
  `return_address` text DEFAULT NULL,
  `return_city` varchar(100) DEFAULT '',
  `return_state` varchar(100) DEFAULT '',
  `return_pincode` varchar(10) DEFAULT '',
  `return_phone` varchar(20) DEFAULT '',
  `default_courier` varchar(100) DEFAULT '',
  `show_gst_on_invoice` tinyint(1) NOT NULL DEFAULT 1,
  `show_pan_on_invoice` tinyint(1) NOT NULL DEFAULT 0,
  `show_phone_on_label` tinyint(1) NOT NULL DEFAULT 1,
  `show_email_on_invoice` tinyint(1) NOT NULL DEFAULT 1,
  `show_return_address` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed with current Gilaf Store data (one row only)
INSERT INTO `company_profile` (`id`, `company_name`, `brand_name`, `tagline`, `address`, `city`, `state`, `pincode`, `country`, `phone`, `email`, `website`, `gstin`, `pan_number`, `return_address`, `return_city`, `return_state`, `return_pincode`, `return_phone`)
VALUES (1, 'GILAF FOODS & SPICES', 'Gilaf Store', 'Premium Kashmiri Heritage Foods', 'Takiyabal, Arampora, Sopore', 'Sopore', 'Jammu & Kashmir', '193201', 'India', '+91 8825041655', 'gilaffoods@gmail.com', 'www.gilafstore.com', '01ABGFG2385F1ZU', 'ABGFG2385F', 'Takiyabal, Arampora, Sopore', 'Sopore', 'Jammu & Kashmir', '193201', '+91 8825041655')
ON DUPLICATE KEY UPDATE `id` = `id`;
