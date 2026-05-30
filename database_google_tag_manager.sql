-- Google Tag Manager Table
CREATE TABLE IF NOT EXISTS `google_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT 'Google Tag',
  `tag_script` text NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `page_conditions` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default record
INSERT INTO `google_tags` (`name`, `tag_script`, `enabled`, `page_conditions`) VALUES
('Google Analytics', '', 0, '{"pages": ["all"], "custom_urls": []}');
