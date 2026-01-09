-- Courier Tracking System Database Schema

-- Create courier_companies table
CREATE TABLE IF NOT EXISTS `courier_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `tracking_url_pattern` text NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default courier companies
INSERT INTO `courier_companies` (`name`, `code`, `tracking_url_pattern`, `is_active`, `display_order`) VALUES
('India Post', 'india_post', 'https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?ConsignmentNo={TN}', 1, 1),
('DTDC', 'dtdc', 'https://www.dtdc.in/tracking/tracking_results.asp?cnno={TN}', 1, 2),
('Blue Dart', 'bluedart', 'https://www.bluedart.com/tracking?trackfor=0&tracknum={TN}', 1, 3),
('Delhivery', 'delhivery', 'https://www.delhivery.com/track/package/{TN}', 1, 4),
('FedEx', 'fedex', 'https://www.fedex.com/fedextrack/?trknbr={TN}', 1, 5),
('DHL', 'dhl', 'https://www.dhl.com/in-en/home/tracking/tracking-express.html?submit=1&tracking-id={TN}', 1, 6),
('Ecom Express', 'ecom_express', 'https://ecomexpress.in/tracking/?awb_field={TN}', 1, 7),
('Aramex', 'aramex', 'https://www.aramex.com/track/results?mode=0&ShipmentNumber={TN}', 1, 8);

-- Add tracking fields to orders table
ALTER TABLE `orders` 
ADD COLUMN `courier_company_id` int(11) DEFAULT NULL AFTER `status`,
ADD COLUMN `tracking_number` varchar(100) DEFAULT NULL AFTER `courier_company_id`,
ADD COLUMN `shipped_at` timestamp NULL DEFAULT NULL AFTER `tracking_number`,
ADD COLUMN `delivered_at` timestamp NULL DEFAULT NULL AFTER `shipped_at`,
ADD KEY `courier_company_id` (`courier_company_id`),
ADD CONSTRAINT `orders_courier_fk` FOREIGN KEY (`courier_company_id`) REFERENCES `courier_companies` (`id`) ON DELETE SET NULL;

-- Create shipment_tracking_history table for tracking updates
CREATE TABLE IF NOT EXISTS `shipment_tracking_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text,
  `tracked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `shipment_tracking_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
