<?php
/**
 * GST Calculator Class
 * Handles automatic GST calculation for orders
 */

class GSTCalculator {
    private $db;
    private $settings;
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadSettings();
    }
    
    /**
     * Load GST settings from database
     */
    private function loadSettings() {
        $query = "SELECT setting_key, setting_value FROM gst_settings";
        $result = $this->db->query($query);
        
        $this->settings = [];
        while ($row = $result->fetch_assoc()) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    /**
     * Calculate GST for an order
     */
    public function calculateOrderGST($orderId, $customerState, $customerGSTIN = null) {
        // Get order details
        $orderQuery = "SELECT o.*, c.state as customer_state, c.gstin as customer_gstin 
                      FROM orders o 
                      LEFT JOIN customers c ON o.customer_id = c.id 
                      WHERE o.id = ?";
        $stmt = $this->db->prepare($orderQuery);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Get order items
        $itemsQuery = "SELECT oi.*, p.category_id 
                      FROM order_items oi 
                      LEFT JOIN products p ON oi.product_id = p.id 
                      WHERE oi.order_id = ?";
        $stmt = $this->db->prepare($itemsQuery);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Determine order type
        $sellerState = $this->settings['seller_state'] ?? 'Maharashtra';
        $orderType = ($customerState === $sellerState) ? 'intra_state' : 'inter_state';
        
        // Calculate GST for each item
        $orderGSTData = [
            'order_type' => $orderType,
            'customer_state' => $customerState,
            'seller_state' => $sellerState,
            'taxable_amount' => 0,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'igst_amount' => 0,
            'cess_amount' => 0,
            'total_gst_amount' => 0,
            'grand_total' => 0,
            'items' => []
        ];
        
        foreach ($items as $item) {
            $itemGSTData = $this->calculateItemGST($item, $orderType);
            $orderGSTData['items'][] = $itemGSTData;
            
            // Accumulate totals
            $orderGSTData['taxable_amount'] += $itemGSTData['taxable_amount'];
            $orderGSTData['cgst_amount'] += $itemGSTData['cgst_amount'];
            $orderGSTData['sgst_amount'] += $itemGSTData['sgst_amount'];
            $orderGSTData['igst_amount'] += $itemGSTData['igst_amount'];
            $orderGSTData['cess_amount'] += $itemGSTData['cess_amount'];
            $orderGSTData['total_gst_amount'] += $itemGSTData['total_gst_amount'];
        }
        
        $orderGSTData['grand_total'] = $orderGSTData['taxable_amount'] + $orderGSTData['total_gst_amount'];
        
        // Save GST data to database
        $this->saveOrderGST($orderId, $orderGSTData, $customerGSTIN);
        
        return $orderGSTData;
    }
    
    /**
     * Calculate GST for a single item
     */
    private function calculateItemGST($item, $orderType) {
        // Get GST configuration for this item
        $gstConfig = $this->getGSTConfigForItem($item);
        
        if (!$gstConfig || $gstConfig['is_exempt']) {
            // No GST or exempt
            return [
                'product_id' => $item['product_id'],
                'category_id' => $item['category_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'taxable_amount' => $item['price'] * $item['quantity'],
                'gst_slab' => 0,
                'cgst_rate' => 0,
                'sgst_rate' => 0,
                'igst_rate' => 0,
                'cess_rate' => 0,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'igst_amount' => 0,
                'cess_amount' => 0,
                'total_gst_amount' => 0,
                'item_total' => $item['price'] * $item['quantity']
            ];
        }
        
        $taxableAmount = $item['price'] * $item['quantity'];
        $gstSlab = $gstConfig['gst_slab'];
        $cessRate = $gstConfig['cess_rate'];
        
        $cgstAmount = 0;
        $sgstAmount = 0;
        $igstAmount = 0;
        $cessAmount = 0;
        
        if ($orderType === 'intra_state') {
            // Split GST into CGST + SGST
            $cgstAmount = round($taxableAmount * ($gstSlab / 2) / 100, $this->settings['gst_rounding'] ?? 2);
            $sgstAmount = round($taxableAmount * ($gstSlab / 2) / 100, $this->settings['gst_rounding'] ?? 2);
            $cgstRate = $gstSlab / 2;
            $sgstRate = $gstSlab / 2;
            $igstRate = 0;
        } else {
            // Apply IGST
            $igstAmount = round($taxableAmount * $gstSlab / 100, $this->settings['gst_rounding'] ?? 2);
            $igstRate = $gstSlab;
            $cgstRate = 0;
            $sgstRate = 0;
        }
        
        // Calculate cess if enabled
        if (($this->settings['enable_cess'] ?? 'false') === 'true' && $cessRate > 0) {
            $cessAmount = round($taxableAmount * $cessRate / 100, $this->settings['gst_rounding'] ?? 2);
        }
        
        $totalGSTAmount = $cgstAmount + $sgstAmount + $igstAmount + $cessAmount;
        
        return [
            'product_id' => $item['product_id'],
            'category_id' => $item['category_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['price'],
            'taxable_amount' => $taxableAmount,
            'gst_slab' => $gstSlab,
            'cgst_rate' => $cgstRate,
            'sgst_rate' => $sgstRate,
            'igst_rate' => $igstRate,
            'cess_rate' => $cessRate,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'igst_amount' => $igstAmount,
            'cess_amount' => $cessAmount,
            'total_gst_amount' => $totalGSTAmount,
            'item_total' => $taxableAmount + $totalGSTAmount
        ];
    }
    
    /**
     * Get GST configuration for an item
     */
    private function getGSTConfigForItem($item) {
        // Try product-level GST first
        $productGST = $this->getGSTConfig('product', $item['product_id']);
        if ($productGST) {
            return $productGST;
        }
        
        // Fall back to category-level GST
        $categoryGST = $this->getGSTConfig('category', $item['category_id']);
        if ($categoryGST) {
            return $categoryGST;
        }
        
        return null;
    }
    
    /**
     * Get GST configuration from database
     */
    private function getGSTConfig($entityType, $entityId) {
        $date = date('Y-m-d H:i:s');
        
        $query = "SELECT * FROM gst_configuration 
                  WHERE entity_type = ? AND entity_id = ? 
                  AND effective_from <= ? 
                  AND (effective_to IS NULL OR effective_to >= ?)
                  AND status = 'active'
                  ORDER BY effective_from DESC LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('siss', $entityType, $entityId, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Save GST data to database
     */
    private function saveOrderGST($orderId, $gstData, $customerGSTIN = null) {
        // Check if GST record already exists
        $checkQuery = "SELECT id FROM gst_orders WHERE order_id = ?";
        $stmt = $this->db->prepare($checkQuery);
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $existingRecord = $stmt->get_result()->fetch_assoc();
        
        if ($existingRecord) {
            // Update existing record
            $updateQuery = "UPDATE gst_orders SET 
                           order_type = ?, customer_state = ?, taxable_amount = ?,
                           cgst_rate = ?, sgst_rate = ?, igst_rate = ?, cess_rate = ?,
                           cgst_amount = ?, sgst_amount = ?, igst_amount = ?, cess_amount = ?,
                           total_gst_amount = ?, grand_total = ?, gstin = ?,
                           place_of_supply = ?, reverse_charge = ?
                           WHERE order_id = ?";
            
            $placeOfSupply = $gstData['customer_state'];
            $reverseCharge = false; // Default value
            
            $stmt = $this->db->prepare($updateQuery);
            $stmt->bind_param('ssddddddddddssssi', 
                $gstData['order_type'], 
                $gstData['customer_state'], 
                $gstData['taxable_amount'],
                $gstData['items'][0]['cgst_rate'] ?? 0,
                $gstData['items'][0]['sgst_rate'] ?? 0,
                $gstData['items'][0]['igst_rate'] ?? 0,
                $gstData['items'][0]['cess_rate'] ?? 0,
                $gstData['cgst_amount'],
                $gstData['sgst_amount'],
                $gstData['igst_amount'],
                $gstData['cess_amount'],
                $gstData['total_gst_amount'],
                $gstData['grand_total'],
                $customerGSTIN,
                $placeOfSupply,
                $reverseCharge,
                $orderId
            );
            $stmt->execute();
            
            $gstOrderId = $existingRecord['id'];
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO gst_orders 
                           (order_id, order_type, customer_state, seller_state, taxable_amount,
                            cgst_rate, sgst_rate, igst_rate, cess_rate, cgst_amount, sgst_amount,
                            igst_amount, cess_amount, total_gst_amount, grand_total, gstin,
                            place_of_supply, reverse_charge) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $placeOfSupply = $gstData['customer_state'];
            $reverseCharge = false;
            
            $stmt = $this->db->prepare($insertQuery);
            $stmt->bind_param('issssddddddddddsss', 
                $orderId, 
                $gstData['order_type'], 
                $gstData['customer_state'], 
                $gstData['seller_state'], 
                $gstData['taxable_amount'],
                $gstData['items'][0]['cgst_rate'] ?? 0,
                $gstData['items'][0]['sgst_rate'] ?? 0,
                $gstData['items'][0]['igst_rate'] ?? 0,
                $gstData['items'][0]['cess_rate'] ?? 0,
                $gstData['cgst_amount'],
                $gstData['sgst_amount'],
                $gstData['igst_amount'],
                $gstData['cess_amount'],
                $gstData['total_gst_amount'],
                $gstData['grand_total'],
                $customerGSTIN,
                $placeOfSupply,
                $reverseCharge
            );
            $stmt->execute();
            
            $gstOrderId = $this->db->insert_id;
        }
        
        // Save order items GST data
        $this->saveOrderItemsGST($gstOrderId, $gstData['items']);
    }
    
    /**
     * Save GST data for order items
     */
    private function saveOrderItemsGST($gstOrderId, $items) {
        // Delete existing items
        $deleteQuery = "DELETE FROM gst_order_items WHERE gst_order_id = ?";
        $stmt = $this->db->prepare($deleteQuery);
        $stmt->bind_param('i', $gstOrderId);
        $stmt->execute();
        
        // Insert new items
        $insertQuery = "INSERT INTO gst_order_items 
                       (gst_order_id, order_item_id, product_id, category_id, quantity, unit_price,
                        taxable_amount, gst_slab, cgst_rate, sgst_rate, igst_rate, cess_rate,
                        cgst_amount, sgst_amount, igst_amount, cess_amount, total_gst_amount, item_total) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($insertQuery);
        
        foreach ($items as $item) {
            $stmt->bind_param('iiiiidddddddddddd', 
                $gstOrderId, 
                $item['order_item_id'] ?? 0, 
                $item['product_id'], 
                $item['category_id'], 
                $item['quantity'], 
                $item['unit_price'],
                $item['taxable_amount'], 
                $item['gst_slab'], 
                $item['cgst_rate'], 
                $item['sgst_rate'], 
                $item['igst_rate'], 
                $item['cess_rate'],
                $item['cgst_amount'], 
                $item['sgst_amount'], 
                $item['igst_amount'], 
                $item['cess_amount'], 
                $item['total_gst_amount'], 
                $item['item_total']
            );
            $stmt->execute();
        }
    }
    
    /**
     * Generate invoice number
     */
    public function generateInvoiceNumber() {
        $prefix = $this->settings['invoice_prefix'] ?? 'INV';
        $startNumber = $this->settings['invoice_start'] ?? 1001;
        
        // Get the last invoice number
        $query = "SELECT invoice_number FROM gst_orders 
                  WHERE invoice_number IS NOT NULL 
                  ORDER BY id DESC LIMIT 1";
        $result = $this->db->query($query);
        $lastInvoice = $result->fetch_assoc();
        
        if ($lastInvoice) {
            // Extract number from last invoice
            $lastNumber = (int) preg_replace('/[^0-9]/', '', $lastInvoice['invoice_number']);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = $startNumber;
        }
        
        return $prefix . $newNumber;
    }
    
    /**
     * Get GST summary for dashboard
     */
    public function getGSTSummary($fromDate = null, $toDate = null) {
        $fromDate = $fromDate ?? date('Y-m-01');
        $toDate = $toDate ?? date('Y-m-d');
        
        $query = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN order_type = 'intra_state' THEN 1 ELSE 0 END) as intra_state_orders,
                    SUM(CASE WHEN order_type = 'inter_state' THEN 1 ELSE 0 END) as inter_state_orders,
                    SUM(taxable_amount) as total_taxable_amount,
                    SUM(cgst_amount) as total_cgst_amount,
                    SUM(sgst_amount) as total_sgst_amount,
                    SUM(igst_amount) as total_igst_amount,
                    SUM(cess_amount) as total_cess_amount,
                    SUM(total_gst_amount) as total_gst_amount,
                    SUM(grand_total) as total_grand_total
                  FROM gst_orders 
                  WHERE DATE(created_at) BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Get state-wise GST distribution
     */
    public function getStateWiseGST($fromDate = null, $toDate = null) {
        $fromDate = $fromDate ?? date('Y-m-01');
        $toDate = $toDate ?? date('Y-m-d');
        
        $query = "SELECT 
                    customer_state,
                    COUNT(*) as order_count,
                    SUM(taxable_amount) as taxable_amount,
                    SUM(total_gst_amount) as gst_amount,
                    SUM(grand_total) as grand_total
                  FROM gst_orders 
                  WHERE DATE(created_at) BETWEEN ? AND ?
                  GROUP BY customer_state
                  ORDER BY gst_amount DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get product-wise GST summary
     */
    public function getProductWiseGST($fromDate = null, $toDate = null, $limit = 50) {
        $fromDate = $fromDate ?? date('Y-m-01');
        $toDate = $toDate ?? date('Y-m-d');
        
        $query = "SELECT 
                    p.name as product_name,
                    p.category_id,
                    c.name as category_name,
                    SUM(oi.quantity) as total_quantity,
                    SUM(gi.taxable_amount) as taxable_amount,
                    SUM(gi.total_gst_amount) as gst_amount,
                    SUM(gi.item_total) as total_amount
                  FROM gst_order_items gi
                  JOIN order_items oi ON gi.order_item_id = oi.id
                  JOIN products p ON gi.product_id = p.id
                  JOIN categories c ON gi.category_id = c.id
                  JOIN gst_orders go ON gi.gst_order_id = go.id
                  WHERE DATE(go.created_at) BETWEEN ? AND ?
                  GROUP BY gi.product_id
                  ORDER BY gst_amount DESC
                  LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ssi', $fromDate, $toDate, $limit);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
