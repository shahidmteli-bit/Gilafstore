-- Database Schema for Content Management System
-- About Us, Our Values, and Blogs

-- About Us Page Content
CREATE TABLE IF NOT EXISTS about_us (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(255),
    content TEXT,
    image_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_section_key (section_key),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Team Members (Founder, CEO, etc.)
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    designation VARCHAR(255) NOT NULL,
    bio TEXT,
    photo_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_display_order (display_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Company Milestones
CREATE TABLE IF NOT EXISTS milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year VARCHAR(10) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_year (year),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Our Values
CREATE TABLE IF NOT EXISTS company_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    icon_class VARCHAR(100),
    icon_url VARCHAR(255),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_display_order (display_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog Categories
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog Posts
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(255),
    category_id INT,
    author_id INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    views INT DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_category_id (category_id),
    INDEX idx_published_at (published_at),
    FULLTEXT idx_search (title, excerpt, content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog Tags
CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog Post Tags (Many-to-Many)
CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default About Us sections
INSERT INTO about_us (section_key, title, content, display_order) VALUES
('company_overview', 'About Gilaf Store', 'Welcome to Gilaf Store - your premier destination for authentic cultural products, artisanal crafts, and unique taste experiences from around the world.', 1),
('mission', 'Our Mission', 'To bring the world''s finest cultural products and artisanal crafts to your doorstep, celebrating diversity and supporting traditional craftsmanship.', 2),
('story', 'Our Story', 'Founded with a passion for authentic cultural experiences, Gilaf Store has grown to become a trusted platform connecting artisans with customers worldwide.', 3)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Insert default values
INSERT INTO company_values (title, description, icon_class, display_order) VALUES
('Quality First', 'We ensure every product meets our high standards of quality and authenticity.', 'fa-certificate', 1),
('Cultural Respect', 'We honor and celebrate the cultural heritage behind every product we offer.', 'fa-globe', 2),
('Customer Focus', 'Your satisfaction is our priority. We provide exceptional service at every step.', 'fa-users', 3),
('Sustainability', 'We support sustainable practices and fair trade with our artisan partners.', 'fa-leaf', 4)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Insert default blog category
INSERT INTO blog_categories (name, slug, description) VALUES
('Company News', 'company-news', 'Latest updates and news from Gilaf Store'),
('Product Stories', 'product-stories', 'Stories behind our products and artisans'),
('Cultural Insights', 'cultural-insights', 'Explore cultures and traditions')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
