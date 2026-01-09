-- ========================================
-- Website Performance Analytics - Database Schema
-- Admin-Only Traffic Intelligence & Analytics System
-- ========================================

-- Drop tables if they exist (for clean installation)
DROP TABLE IF EXISTS analytics_product_events;
DROP TABLE IF EXISTS analytics_page_views;
DROP TABLE IF EXISTS analytics_visitors;
DROP TABLE IF EXISTS analytics_daily_summary;
DROP TABLE IF EXISTS analytics_geographic_data;
DROP TABLE IF EXISTS analytics_settings;

-- Visitor Tracking Table
CREATE TABLE analytics_visitors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_id VARCHAR(64) UNIQUE NOT NULL COMMENT 'Unique visitor identifier (cookie/session based)',
    user_id INT DEFAULT NULL COMMENT 'Linked user if logged in',
    
    -- First Visit Info
    first_visit_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    first_visit_url TEXT DEFAULT NULL,
    first_referrer TEXT DEFAULT NULL,
    
    -- Last Visit Info
    last_visit_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    total_visits INT DEFAULT 1,
    total_page_views INT DEFAULT 0,
    
    -- Geographic Data
    country VARCHAR(100) DEFAULT NULL,
    country_code VARCHAR(10) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    timezone VARCHAR(50) DEFAULT NULL,
    
    -- Technical Data
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    browser VARCHAR(50) DEFAULT NULL,
    browser_version VARCHAR(20) DEFAULT NULL,
    os VARCHAR(50) DEFAULT NULL,
    os_version VARCHAR(20) DEFAULT NULL,
    device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
    screen_resolution VARCHAR(20) DEFAULT NULL,
    language VARCHAR(10) DEFAULT NULL,
    
    -- Indexes
    KEY idx_visitor_id (visitor_id),
    KEY idx_user_id (user_id),
    KEY idx_country (country),
    KEY idx_first_visit (first_visit_at),
    KEY idx_last_visit (last_visit_at),
    KEY idx_device_type (device_type),
    
    -- Foreign Keys
    CONSTRAINT fk_visitor_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page Views Tracking Table
CREATE TABLE analytics_page_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    
    -- Page Info
    page_url TEXT NOT NULL,
    page_title VARCHAR(255) DEFAULT NULL,
    page_type VARCHAR(50) DEFAULT NULL COMMENT 'home, product, category, cart, checkout, etc.',
    
    -- Referrer Info
    referrer_url TEXT DEFAULT NULL,
    referrer_type VARCHAR(50) DEFAULT NULL COMMENT 'direct, search, social, internal, external',
    
    -- Session Info
    session_id VARCHAR(64) DEFAULT NULL,
    visit_duration INT DEFAULT 0 COMMENT 'Time spent on page in seconds',
    
    -- Engagement Metrics
    scroll_depth INT DEFAULT 0 COMMENT 'Percentage scrolled',
    clicks_count INT DEFAULT 0,
    
    -- Technical Data
    load_time INT DEFAULT NULL COMMENT 'Page load time in milliseconds',
    
    -- Timestamp
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_visitor_id (visitor_id),
    KEY idx_user_id (user_id),
    KEY idx_page_type (page_type),
    KEY idx_viewed_at (viewed_at),
    KEY idx_session_id (session_id),
    
    -- Foreign Keys
    CONSTRAINT fk_pageview_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product Events Tracking Table
CREATE TABLE analytics_product_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    product_id INT NOT NULL,
    
    -- Event Details
    event_type ENUM('view', 'click', 'add_to_cart', 'remove_from_cart', 'wishlist', 'purchase') NOT NULL,
    event_source VARCHAR(50) DEFAULT NULL COMMENT 'homepage, category, search, related, etc.',
    
    -- Product Context
    category_id INT DEFAULT NULL,
    product_price DECIMAL(10,2) DEFAULT NULL,
    quantity INT DEFAULT 1,
    
    -- Session Info
    session_id VARCHAR(64) DEFAULT NULL,
    page_url TEXT DEFAULT NULL,
    
    -- Timestamp
    event_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_visitor_id (visitor_id),
    KEY idx_user_id (user_id),
    KEY idx_product_id (product_id),
    KEY idx_event_type (event_type),
    KEY idx_event_at (event_at),
    KEY idx_category_id (category_id),
    
    -- Foreign Keys
    CONSTRAINT fk_product_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_product_event_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_product_event_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily Summary Table (Pre-aggregated for performance)
CREATE TABLE analytics_daily_summary (
    id INT PRIMARY KEY AUTO_INCREMENT,
    summary_date DATE NOT NULL UNIQUE,
    
    -- Visitor Metrics
    total_visitors INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    new_visitors INT DEFAULT 0,
    returning_visitors INT DEFAULT 0,
    
    -- Page View Metrics
    total_page_views INT DEFAULT 0,
    avg_pages_per_visitor DECIMAL(10,2) DEFAULT 0,
    avg_session_duration INT DEFAULT 0 COMMENT 'Average in seconds',
    bounce_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentage',
    
    -- Engagement Metrics
    total_clicks INT DEFAULT 0,
    total_product_views INT DEFAULT 0,
    total_add_to_cart INT DEFAULT 0,
    total_purchases INT DEFAULT 0,
    
    -- Revenue Metrics
    total_revenue DECIMAL(12,2) DEFAULT 0,
    total_orders INT DEFAULT 0,
    avg_order_value DECIMAL(10,2) DEFAULT 0,
    
    -- Conversion Metrics
    conversion_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentage',
    cart_abandonment_rate DECIMAL(5,2) DEFAULT 0 COMMENT 'Percentage',
    
    -- Top Performers (JSON for flexibility)
    top_products JSON DEFAULT NULL,
    top_categories JSON DEFAULT NULL,
    top_countries JSON DEFAULT NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_summary_date (summary_date),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Geographic Data Summary Table
CREATE TABLE analytics_geographic_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Location
    country VARCHAR(100) NOT NULL,
    country_code VARCHAR(10) DEFAULT NULL,
    state VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    
    -- Date Range
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    
    -- Metrics
    visitor_count INT DEFAULT 0,
    page_views INT DEFAULT 0,
    total_clicks INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    total_orders INT DEFAULT 0,
    
    -- Percentages
    traffic_percentage DECIMAL(5,2) DEFAULT 0,
    revenue_percentage DECIMAL(5,2) DEFAULT 0,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    KEY idx_country (country),
    KEY idx_date_range (date_from, date_to),
    KEY idx_visitor_count (visitor_count),
    
    -- Unique constraint
    UNIQUE KEY unique_location_date (country, state, city, date_from, date_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Analytics Settings Table
CREATE TABLE analytics_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO analytics_settings (setting_key, setting_value, setting_type, description) VALUES
('tracking_enabled', 'true', 'boolean', 'Enable/disable analytics tracking'),
('track_logged_in_users', 'true', 'boolean', 'Track logged-in user activity'),
('track_guest_users', 'true', 'boolean', 'Track guest visitor activity'),
('session_timeout', '1800', 'number', 'Session timeout in seconds (30 minutes)'),
('data_retention_days', '365', 'number', 'Days to retain raw analytics data'),
('enable_geolocation', 'true', 'boolean', 'Enable IP-based geolocation'),
('bounce_threshold_seconds', '30', 'number', 'Seconds to consider as bounce'),
('real_time_enabled', 'true', 'boolean', 'Enable real-time analytics'),
('export_limit', '10000', 'number', 'Maximum rows for data export'),
('admin_only_access', 'true', 'boolean', 'Restrict analytics to admin only');

-- Create indexes for performance optimization
CREATE INDEX idx_analytics_visitors_country_date ON analytics_visitors(country, first_visit_at);
CREATE INDEX idx_analytics_pageviews_date_type ON analytics_page_views(viewed_at, page_type);
CREATE INDEX idx_analytics_product_events_date_type ON analytics_product_events(event_at, event_type, product_id);

-- Create composite indexes for common queries
CREATE INDEX idx_visitor_date_country ON analytics_visitors(first_visit_at, country);
CREATE INDEX idx_product_event_date_product ON analytics_product_events(event_at, product_id, event_type);

-- Success message
SELECT 'Website Performance Analytics database schema created successfully!' AS Status,
       '6 tables created: visitors, page_views, product_events, daily_summary, geographic_data, settings' AS Info;
