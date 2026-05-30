-- Blog System Database Schema
-- Run this SQL to create the blog tables

-- Blog Categories
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fa-folder',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Main Blogs Table
CREATE TABLE IF NOT EXISTS blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT,
    featured_image VARCHAR(500),
    category_id INT,
    author_name VARCHAR(100) DEFAULT 'Gilaf Store',
    author_image VARCHAR(500),
    
    -- SEO Fields
    meta_title VARCHAR(70),
    meta_description VARCHAR(160),
    meta_keywords VARCHAR(255),
    canonical_url VARCHAR(500),
    og_image VARCHAR(500),
    
    -- Stats
    views INT DEFAULT 0,
    reading_time INT DEFAULT 5,
    
    -- Status
    status ENUM('draft', 'published', 'scheduled') DEFAULT 'draft',
    publish_date DATETIME,
    is_featured TINYINT(1) DEFAULT 0,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_publish_date (publish_date),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog to Product Linking (Many-to-Many)
CREATE TABLE IF NOT EXISTS blog_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    product_id INT NOT NULL,
    display_order INT DEFAULT 0,
    display_type ENUM('inline', 'sidebar', 'bottom') DEFAULT 'bottom',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_blog_product (blog_id, product_id),
    INDEX idx_blog (blog_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog FAQs (for Schema markup)
CREATE TABLE IF NOT EXISTS blog_faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blog_id INT NOT NULL,
    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    INDEX idx_blog (blog_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog Tags
CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blog to Tag Linking
CREATE TABLE IF NOT EXISTS blog_tag_links (
    blog_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (blog_id, tag_id),
    FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default categories
INSERT INTO blog_categories (name, slug, description, icon, sort_order) VALUES
('Health Benefits', 'health-benefits', 'Health and wellness benefits of our products', 'fa-heart', 1),
('Recipes', 'recipes', 'Delicious recipes using our products', 'fa-utensils', 2),
('Product Stories', 'product-stories', 'Stories behind our authentic products', 'fa-book-open', 3),
('Kashmir Culture', 'kashmir-culture', 'Explore the rich culture of Kashmir', 'fa-mountain', 4),
('Buying Guides', 'buying-guides', 'How to choose the best products', 'fa-shopping-cart', 5),
('Tea & Kahwa Guides', 'tea-guides', 'Everything about traditional teas', 'fa-mug-hot', 6),
('Honey Benefits', 'honey-benefits', 'Benefits and uses of pure honey', 'fa-jar', 7),
('Dry Fruit Nutrition', 'dry-fruit-nutrition', 'Nutritional benefits of dry fruits', 'fa-seedling', 8);
