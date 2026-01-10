-- ========================================
-- GST Tax Compliance Module - Database Schema
-- ========================================

-- GST Configuration Table
CREATE TABLE IF NOT EXISTS gst_configuration (
    id INT PRIMARY KEY AUTO_INCREMENT,
    entity_type ENUM('category', 'product') NOT NULL,
    entity_id INT NOT NULL,
    gst_slab DECIMAL(5,2) NOT NULL COMMENT 'GST percentage (0, 5, 12, 18, 28)',
    hsn_code VARCHAR(20) DEFAULT NULL COMMENT 'HSN/SAC code',
    cess_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Additional cess rate',
    is_exempt BOOLEAN DEFAULT FALSE COMMENT 'GST exempt flag',
    effective_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_to DATETIME DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_gst_config (entity_type, entity_id, effective_from),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_status (status),
    KEY idx_effective_date (effective_from, effective_to),
    CONSTRAINT fk_gst_config_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_gst_config_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GST Orders Table
CREATE TABLE IF NOT EXISTS gst_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    order_type ENUM('intra_state', 'inter_state') NOT NULL,
    customer_state VARCHAR(50) NOT NULL,
    seller_state VARCHAR(50) NOT NULL DEFAULT 'Maharashtra',
    taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cgst_rate DECIMAL(5,2) DEFAULT 0.00,
    sgst_rate DECIMAL(5,2) DEFAULT 0.00,
    igst_rate DECIMAL(5,2) DEFAULT 0.00,
    cess_rate DECIMAL(5,2) DEFAULT 0.00,
    cgst_amount DECIMAL(12,2) DEFAULT 0.00,
    sgst_amount DECIMAL(12,2) DEFAULT 0.00,
    igst_amount DECIMAL(12,2) DEFAULT 0.00,
    cess_amount DECIMAL(12,2) DEFAULT 0.00,
    total_gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gstin VARCHAR(15) DEFAULT NULL COMMENT 'Customer GSTIN',
    place_of_supply VARCHAR(50) NOT NULL,
    reverse_charge BOOLEAN DEFAULT FALSE,
    invoice_number VARCHAR(50) DEFAULT NULL,
    invoice_date DATE DEFAULT NULL,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    KEY idx_order_id (order_id),
    KEY idx_order_type (order_type),
    KEY idx_customer_state (customer_state),
    KEY idx_invoice_date (invoice_date),
    KEY idx_gstin (gstin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GST Order Items Table
CREATE TABLE IF NOT EXISTS gst_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gst_order_id INT NOT NULL,
    order_item_id INT NOT NULL,
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gst_slab DECIMAL(5,2) NOT NULL,
    cgst_rate DECIMAL(5,2) DEFAULT 0.00,
    sgst_rate DECIMAL(5,2) DEFAULT 0.00,
    igst_rate DECIMAL(5,2) DEFAULT 0.00,
    cess_rate DECIMAL(5,2) DEFAULT 0.00,
    cgst_amount DECIMAL(12,2) DEFAULT 0.00,
    sgst_amount DECIMAL(12,2) DEFAULT 0.00,
    igst_amount DECIMAL(12,2) DEFAULT 0.00,
    cess_amount DECIMAL(12,2) DEFAULT 0.00,
    total_gst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    item_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    
    FOREIGN KEY (gst_order_id) REFERENCES gst_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    KEY idx_gst_order_id (gst_order_id),
    KEY idx_product_id (product_id),
    KEY idx_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GST Audit Trail Table
CREATE TABLE IF NOT EXISTS gst_audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    action_type ENUM('create', 'update', 'delete') NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    changed_fields JSON DEFAULT NULL,
    changed_by INT DEFAULT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    
    KEY idx_table_record (table_name, record_id),
    KEY idx_changed_by (changed_by),
    KEY idx_changed_at (changed_at),
    KEY idx_action_type (action_type),
    CONSTRAINT fk_gst_audit_changed_by FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GST Reports Table
CREATE TABLE IF NOT EXISTS gst_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_type ENUM('gstr1', 'gstr2', 'gstr3', 'summary', 'detailed') NOT NULL,
    report_name VARCHAR(100) NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    report_data JSON NOT NULL,
    generated_by INT DEFAULT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255) DEFAULT NULL,
    file_type ENUM('excel', 'pdf', 'csv') DEFAULT NULL,
    status ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
    
    KEY idx_report_type (report_type),
    KEY idx_date_range (from_date, to_date),
    KEY idx_generated_by (generated_by),
    KEY idx_status (status),
    CONSTRAINT fk_gst_reports_generated_by FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GST Settings Table
CREATE TABLE IF NOT EXISTS gst_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_setting_key (setting_key),
    CONSTRAINT fk_gst_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default GST settings (skip if already exists)
INSERT IGNORE INTO gst_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES
('seller_state', 'Maharashtra', 'string', 'Default seller state for GST calculations', 1),
('seller_gstin', '', 'string', 'Seller GSTIN number', 1),
('auto_calculate_gst', 'true', 'boolean', 'Automatically calculate GST on orders', 1),
('gst_rounding', '2', 'number', 'Decimal places for GST rounding', 1),
('enable_cess', 'false', 'boolean', 'Enable additional cess calculation', 1),
('invoice_prefix', 'INV', 'string', 'Invoice number prefix', 1),
('invoice_start', '1001', 'number', 'Starting invoice number', 1);

-- Create indexes for better performance (skip if already exists)
CREATE INDEX IF NOT EXISTS idx_gst_orders_date_range ON gst_orders(invoice_date);
CREATE INDEX IF NOT EXISTS idx_gst_order_items_product ON gst_order_items(product_id, gst_order_id);
CREATE INDEX IF NOT EXISTS idx_gst_config_entity_date ON gst_configuration(entity_type, entity_id, effective_from, effective_to);

-- Foreign key constraints are now defined directly in CREATE TABLE statements above
