-- ============================================================
-- Shipping Hybrid System Migration
-- Adds integration_type to shipping_partners
-- Creates order_shipments table for unified shipment tracking
-- Migrates courier_companies into shipping_partners as manual
-- ============================================================

-- 1) Add integration_type column to shipping_partners
ALTER TABLE shipping_partners
  ADD COLUMN integration_type ENUM('api','manual') NOT NULL DEFAULT 'api' AFTER partner_type;

-- 2) Mark all existing shipping_partners as API type (they already are)
UPDATE shipping_partners SET integration_type = 'api' WHERE integration_type = 'api';

-- 3) Migrate courier_companies into shipping_partners as manual couriers
--    Skip any that already exist by partner_code
INSERT IGNORE INTO shipping_partners 
  (partner_name, partner_code, partner_type, integration_type, is_active, base_url, extra_fields)
SELECT 
  cc.name,
  cc.code,
  'domestic',
  'manual',
  cc.is_active,
  cc.tracking_url_pattern,
  JSON_OBJECT('tracking_url_pattern', cc.tracking_url_pattern, 'display_order', cc.display_order, 'migrated_from', 'courier_companies', 'original_id', cc.id)
FROM courier_companies cc
WHERE cc.code NOT IN (SELECT partner_code FROM shipping_partners);

-- 4) Create order_shipments table
CREATE TABLE IF NOT EXISTS order_shipments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  shipping_type ENUM('api','manual') NOT NULL DEFAULT 'manual',
  shipping_partner VARCHAR(100) NOT NULL COMMENT 'Partner name (e.g. Shiprocket, DTDC)',
  shipping_partner_code VARCHAR(50) DEFAULT NULL COMMENT 'Partner code from shipping_partners',
  awb_or_tracking VARCHAR(100) DEFAULT NULL,
  dispatch_mode VARCHAR(30) DEFAULT NULL COMMENT 'Surface/Air/Hand-delivery',
  dispatch_date DATE DEFAULT NULL,
  tracking_url VARCHAR(500) DEFAULT NULL,
  label_url VARCHAR(500) DEFAULT NULL,
  label_file_path VARCHAR(500) DEFAULT NULL COMMENT 'For manual label upload',
  api_shipment_id VARCHAR(100) DEFAULT NULL COMMENT 'Shipment ID from API provider',
  api_order_id VARCHAR(100) DEFAULT NULL COMMENT 'Order ID from API provider',
  api_response TEXT DEFAULT NULL COMMENT 'Last API response JSON',
  shipping_status VARCHAR(50) DEFAULT 'created' COMMENT 'created/shipped/in_transit/delivered/rto etc.',
  notes TEXT DEFAULT NULL,
  created_by_admin_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_order_id (order_id),
  KEY idx_shipping_status (shipping_status),
  KEY idx_awb (awb_or_tracking),
  CONSTRAINT fk_shipment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Add scope column alias (partner_type already covers domestic/international/both)
--    No change needed - partner_type ENUM('domestic','international','both') already exists
