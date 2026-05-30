<?php
/**
 * Sales Portal Pricing Helper Functions
 * Handles party-based pricing resolution with GST
 * Ensures accurate price fetching based on party type
 */

/**
 * Get the correct price for a product weight based on party type
 * 
 * @param int $weightId Product weight ID
 * @param string $partyType Party profile type (distributor, wholesaler, retailer, franchise)
 * @param bool $includeGst Whether to include GST in the price
 * @return array ['base_price' => float, 'gst' => float, 'total_price' => float, 'mrp' => float]
 */
function get_party_price($weightId, $partyType, $includeGst = false) {
    $db = get_db_connection();
    
    // Map party types to price columns
    $priceMapping = [
        'distributor' => ['price_col' => 'distributor_price', 'gst_col' => 'distributor_gst'],
        'wholesaler' => ['price_col' => 'wholesale_price', 'gst_col' => 'wholesale_gst'],
        'retailer' => ['price_col' => 'retail_price', 'gst_col' => 'retail_gst'],
        'franchise' => ['price_col' => 'franchise_price', 'gst_col' => 'franchise_gst'],
    ];
    
    // Default to retail if party type not recognized
    if (!isset($priceMapping[$partyType])) {
        $partyType = 'retailer';
    }
    
    $mapping = $priceMapping[$partyType];
    
    $sql = "SELECT 
                {$mapping['price_col']} as base_price,
                {$mapping['gst_col']} as gst_percent,
                offline_mrp
            FROM product_weights 
            WHERE id = ?";
    
    $row = db_fetch($sql, [$weightId]);
    
    if (!$row) {
        return [
            'base_price' => 0.00,
            'gst' => 0.00,
            'gst_percent' => 0.00,
            'total_price' => 0.00,
            'mrp' => 0.00
        ];
    }
    
    $basePrice = (float)$row['base_price'];
    $gstPercent = (float)$row['gst_percent'];
    $mrp = (float)$row['offline_mrp'];
    
    // Calculate GST amount
    $gstAmount = $includeGst ? round(($basePrice * $gstPercent / 100), 2) : 0.00;
    $totalPrice = round($basePrice + $gstAmount, 2);
    
    return [
        'base_price' => number_format($basePrice, 2, '.', ''),
        'gst' => number_format($gstAmount, 2, '.', ''),
        'gst_percent' => number_format($gstPercent, 2, '.', ''),
        'total_price' => number_format($totalPrice, 2, '.', ''),
        'mrp' => number_format($mrp, 2, '.', '')
    ];
}

/**
 * Get pricing for multiple weights based on party type
 * 
 * @param array $weightIds Array of product weight IDs
 * @param string $partyType Party profile type
 * @param bool $includeGst Whether to include GST
 * @return array Associative array with weight_id as key
 */
function get_party_prices_bulk($weightIds, $partyType, $includeGst = false) {
    if (empty($weightIds)) {
        return [];
    }
    
    $db = get_db_connection();
    
    // Map party types to price columns
    $priceMapping = [
        'distributor' => ['price_col' => 'distributor_price', 'gst_col' => 'distributor_gst'],
        'wholesaler' => ['price_col' => 'wholesale_price', 'gst_col' => 'wholesale_gst'],
        'retailer' => ['price_col' => 'retail_price', 'gst_col' => 'retail_gst'],
        'franchise' => ['price_col' => 'franchise_price', 'gst_col' => 'franchise_gst'],
    ];
    
    if (!isset($priceMapping[$partyType])) {
        $partyType = 'retailer';
    }
    
    $mapping = $priceMapping[$partyType];
    
    $placeholders = implode(',', array_fill(0, count($weightIds), '?'));
    $sql = "SELECT 
                id as weight_id,
                {$mapping['price_col']} as base_price,
                {$mapping['gst_col']} as gst_percent,
                offline_mrp
            FROM product_weights 
            WHERE id IN ($placeholders)";
    
    $rows = db_fetch_all($sql, $weightIds);
    
    $result = [];
    foreach ($rows as $row) {
        $basePrice = (float)$row['base_price'];
        $gstPercent = (float)$row['gst_percent'];
        $mrp = (float)$row['offline_mrp'];
        
        $gstAmount = $includeGst ? round(($basePrice * $gstPercent / 100), 2) : 0.00;
        $totalPrice = round($basePrice + $gstAmount, 2);
        
        $result[$row['weight_id']] = [
            'base_price' => number_format($basePrice, 2, '.', ''),
            'gst' => number_format($gstAmount, 2, '.', ''),
            'gst_percent' => number_format($gstPercent, 2, '.', ''),
            'total_price' => number_format($totalPrice, 2, '.', ''),
            'mrp' => number_format($mrp, 2, '.', '')
        ];
    }
    
    return $result;
}

/**
 * Calculate order total with accurate decimal handling
 * 
 * @param array $items Array of items with ['weight_id' => int, 'quantity' => int]
 * @param string $partyType Party profile type
 * @param bool $includeGst Whether to include GST
 * @return array ['subtotal' => float, 'gst_total' => float, 'grand_total' => float]
 */
function calculate_order_total($items, $partyType, $includeGst = true) {
    $weightIds = array_column($items, 'weight_id');
    $prices = get_party_prices_bulk($weightIds, $partyType, $includeGst);
    
    $subtotal = 0.00;
    $gstTotal = 0.00;
    
    foreach ($items as $item) {
        $weightId = $item['weight_id'];
        $quantity = (int)$item['quantity'];
        
        if (isset($prices[$weightId])) {
            $basePrice = (float)$prices[$weightId]['base_price'];
            $gst = (float)$prices[$weightId]['gst'];
            
            $subtotal += round($basePrice * $quantity, 2);
            $gstTotal += round($gst * $quantity, 2);
        }
    }
    
    $grandTotal = round($subtotal + $gstTotal, 2);
    
    return [
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'gst_total' => number_format($gstTotal, 2, '.', ''),
        'grand_total' => number_format($grandTotal, 2, '.', '')
    ];
}

/**
 * Validate that pricing exists for a weight and party type
 * 
 * @param int $weightId Product weight ID
 * @param string $partyType Party profile type
 * @return bool True if valid price exists
 */
function validate_party_pricing($weightId, $partyType) {
    $pricing = get_party_price($weightId, $partyType);
    return (float)$pricing['base_price'] > 0;
}

/**
 * Get all pricing tiers for a product weight (for display/comparison)
 * 
 * @param int $weightId Product weight ID
 * @return array All pricing tiers
 */
function get_all_pricing_tiers($weightId) {
    $db = get_db_connection();
    
    $sql = "SELECT 
                wholesale_price, wholesale_gst,
                distributor_price, distributor_gst,
                franchise_price, franchise_gst,
                retail_price, retail_gst,
                offline_mrp,
                price as website_price
            FROM product_weights 
            WHERE id = ?";
    
    $row = db_fetch($sql, [$weightId]);
    
    if (!$row) {
        return null;
    }
    
    return [
        'wholesale' => [
            'base_price' => number_format((float)$row['wholesale_price'], 2, '.', ''),
            'gst_percent' => number_format((float)$row['wholesale_gst'], 2, '.', ''),
        ],
        'distributor' => [
            'base_price' => number_format((float)$row['distributor_price'], 2, '.', ''),
            'gst_percent' => number_format((float)$row['distributor_gst'], 2, '.', ''),
        ],
        'franchise' => [
            'base_price' => number_format((float)$row['franchise_price'], 2, '.', ''),
            'gst_percent' => number_format((float)$row['franchise_gst'], 2, '.', ''),
        ],
        'retailer' => [
            'base_price' => number_format((float)$row['retail_price'], 2, '.', ''),
            'gst_percent' => number_format((float)$row['retail_gst'], 2, '.', ''),
        ],
        'offline_mrp' => number_format((float)$row['offline_mrp'], 2, '.', ''),
        'website_price' => number_format((float)$row['website_price'], 2, '.', '')
    ];
}
