<?php
/**
 * SHIPPING CALCULATION FUNCTIONS
 * Core logic for calculating shipping costs based on zones, methods, and weight
 * Database: ecommerce_db
 */

/**
 * Calculate shipping cost for cart
 * @param float $cartTotal - Total cart value
 * @param float $cartWeight - Total cart weight in kg
 * @param string $countryCode - Customer country code
 * @param string $postalCode - Customer postal code
 * @param int $methodId - Selected shipping method ID
 * @return array - Shipping details with cost, delivery time, etc.
 */
function calculateShippingCost($cartTotal, $cartWeight, $countryCode, $postalCode = null, $methodId = null) {
    $db = get_db_connection();
    
    // Step 1: Determine shipping zone
    $zoneId = getShippingZoneByLocation($countryCode, $postalCode);
    
    if (!$zoneId) {
        return [
            'success' => false,
            'message' => 'Shipping not available to your location'
        ];
    }
    
    // Step 2: Check for free shipping
    $freeShipping = checkFreeShippingEligibility($cartTotal, $zoneId);
    
    if ($freeShipping['eligible']) {
        return [
            'success' => true,
            'shipping_cost' => 0.00,
            'is_free' => true,
            'zone_id' => $zoneId,
            'method_id' => $methodId,
            'message' => 'Free Shipping Applied!'
        ];
    }
    
    // Step 3: Get available shipping methods for zone
    $methods = getAvailableShippingMethods($zoneId);
    
    if (empty($methods)) {
        return [
            'success' => false,
            'message' => 'No shipping methods available for your location'
        ];
    }
    
    // Step 4: Calculate cost for selected method or get all methods
    if ($methodId) {
        $cost = calculateMethodCost($zoneId, $methodId, $cartWeight);
        return array_merge($cost, ['zone_id' => $zoneId]);
    } else {
        // Return all available methods with costs
        $allMethods = [];
        foreach ($methods as $method) {
            $cost = calculateMethodCost($zoneId, $method['id'], $cartWeight);
            if ($cost['success']) {
                $allMethods[] = array_merge($method, $cost);
            }
        }
        return [
            'success' => true,
            'zone_id' => $zoneId,
            'methods' => $allMethods
        ];
    }
}

/**
 * Get shipping zone by customer location
 */
function getShippingZoneByLocation($countryCode, $postalCode = null) {
    $db = get_db_connection();
    
    $sql = "SELECT DISTINCT sz.id 
            FROM shipping_zones sz
            JOIN shipping_zone_locations szl ON sz.id = szl.zone_id
            WHERE szl.country_code = :country 
            AND sz.is_active = 1 
            AND szl.is_active = 1";
    
    $params = [':country' => $countryCode];
    
    if ($postalCode) {
        $sql .= " AND (szl.postal_code_pattern IS NULL OR :postal LIKE szl.postal_code_pattern)";
        $params[':postal'] = $postalCode;
    }
    
    $sql .= " ORDER BY sz.display_order ASC LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['id'] : null;
}

/**
 * Check if order qualifies for free shipping
 */
function checkFreeShippingEligibility($cartTotal, $zoneId) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT * FROM shipping_free_rules 
        WHERE is_active = 1 
        AND (zone_id IS NULL OR zone_id = :zone)
        AND min_order_value <= :total
        ORDER BY priority DESC, min_order_value DESC
        LIMIT 1
    ");
    
    $stmt->execute([
        ':zone' => $zoneId,
        ':total' => $cartTotal
    ]);
    
    $rule = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rule) {
        // Check if international is excluded
        $zone = getZoneDetails($zoneId);
        if ($rule['exclude_international'] && in_array($zone['zone_type'], ['international', 'regional'])) {
            return ['eligible' => false];
        }
        
        return [
            'eligible' => true,
            'rule_name' => $rule['rule_name']
        ];
    }
    
    return ['eligible' => false];
}

/**
 * Get available shipping methods for a zone
 */
function getAvailableShippingMethods($zoneId) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT DISTINCT sm.* 
        FROM shipping_methods sm
        JOIN shipping_rates sr ON sm.id = sr.method_id
        WHERE sr.zone_id = :zone 
        AND sm.is_active = 1 
        AND sr.is_active = 1
        ORDER BY sm.display_order ASC
    ");
    
    $stmt->execute([':zone' => $zoneId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate shipping cost for specific method and weight
 */
function calculateMethodCost($zoneId, $methodId, $weight) {
    $db = get_db_connection();
    
    // Find appropriate weight slab
    $stmt = $db->prepare("
        SELECT sr.*, ws.slab_name, sm.method_name
        FROM shipping_rates sr
        LEFT JOIN shipping_weight_slabs ws ON sr.weight_slab_id = ws.id
        JOIN shipping_methods sm ON sr.method_id = sm.id
        WHERE sr.zone_id = :zone 
        AND sr.method_id = :method
        AND sr.is_active = 1
        AND (ws.min_weight <= :weight AND ws.max_weight >= :weight)
        ORDER BY ws.min_weight ASC
        LIMIT 1
    ");
    
    $stmt->execute([
        ':zone' => $zoneId,
        ':method' => $methodId,
        ':weight' => $weight
    ]);
    
    $rate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rate) {
        return [
            'success' => false,
            'message' => 'No rate found for this weight'
        ];
    }
    
    // Calculate final cost
    $shippingCost = $rate['base_cost'];
    
    // Add per-kg cost if applicable
    if ($rate['per_kg_cost'] > 0) {
        $shippingCost += ($weight * $rate['per_kg_cost']);
    }
    
    return [
        'success' => true,
        'shipping_cost' => round($shippingCost, 2),
        'method_name' => $rate['method_name'],
        'min_delivery_days' => $rate['min_delivery_days'],
        'max_delivery_days' => $rate['max_delivery_days'],
        'delivery_estimate' => $rate['min_delivery_days'] . '-' . $rate['max_delivery_days'] . ' days',
        'is_free' => false
    ];
}

/**
 * Get zone details
 */
function getZoneDetails($zoneId) {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM shipping_zones WHERE id = :id");
    $stmt->execute([':id' => $zoneId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Calculate total cart weight
 */
function calculateCartWeight($cartItems) {
    $totalWeight = 0;
    $db = get_db_connection();
    
    foreach ($cartItems as $item) {
        $stmt = $db->prepare("SELECT weight FROM products WHERE id = :id");
        $stmt->execute([':id' => $item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product && $product['weight']) {
            $totalWeight += ($product['weight'] * $item['quantity']);
        }
    }
    
    return $totalWeight;
}

/**
 * Check COD availability
 */
function isCODAvailable($zoneId, $orderTotal) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT * FROM shipping_cod_settings 
        WHERE (zone_id IS NULL OR zone_id = :zone)
        AND is_enabled = 1
        AND (max_cod_amount IS NULL OR max_cod_amount >= :total)
        ORDER BY zone_id DESC
        LIMIT 1
    ");
    
    $stmt->execute([
        ':zone' => $zoneId,
        ':total' => $orderTotal
    ]);
    
    $codSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$codSettings) {
        return ['available' => false];
    }
    
    // Check if international is excluded
    $zone = getZoneDetails($zoneId);
    if ($codSettings['exclude_international'] && in_array($zone['zone_type'], ['international', 'regional'])) {
        return ['available' => false];
    }
    
    // Calculate COD charge
    $codCharge = 0;
    if ($codSettings['cod_charge_type'] === 'fixed') {
        $codCharge = $codSettings['cod_charge'];
    } else {
        $codCharge = ($orderTotal * $codSettings['cod_charge']) / 100;
    }
    
    return [
        'available' => true,
        'cod_charge' => round($codCharge, 2),
        'charge_type' => $codSettings['cod_charge_type']
    ];
}

/**
 * Save shipping details to order
 */
function saveOrderShippingDetails($orderId, $shippingData) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        INSERT INTO order_shipping_details (
            order_id, zone_id, method_id, shipping_cost, cod_charge,
            total_weight, shipping_address, shipping_country, shipping_postal_code,
            estimated_delivery_date
        ) VALUES (
            :order_id, :zone_id, :method_id, :shipping_cost, :cod_charge,
            :weight, :address, :country, :postal, :delivery_date
        )
    ");
    
    $deliveryDate = date('Y-m-d', strtotime('+' . $shippingData['max_delivery_days'] . ' days'));
    
    return $stmt->execute([
        ':order_id' => $orderId,
        ':zone_id' => $shippingData['zone_id'],
        ':method_id' => $shippingData['method_id'],
        ':shipping_cost' => $shippingData['shipping_cost'],
        ':cod_charge' => $shippingData['cod_charge'] ?? 0,
        ':weight' => $shippingData['weight'],
        ':address' => $shippingData['address'],
        ':country' => $shippingData['country'],
        ':postal' => $shippingData['postal_code'],
        ':delivery_date' => $deliveryDate
    ]);
}

/**
 * Update shipping status
 */
function updateShippingStatus($orderShippingId, $newStatus, $trackingNumber = null, $notes = null) {
    $db = get_db_connection();
    
    // Get current status
    $stmt = $db->prepare("SELECT shipping_status FROM order_shipping_details WHERE id = :id");
    $stmt->execute([':id' => $orderShippingId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldStatus = $current ? $current['shipping_status'] : null;
    
    // Update status
    $sql = "UPDATE order_shipping_details SET shipping_status = :status";
    $params = [':status' => $newStatus, ':id' => $orderShippingId];
    
    if ($trackingNumber) {
        $sql .= ", tracking_number = :tracking";
        $params[':tracking'] = $trackingNumber;
    }
    
    if ($newStatus === 'shipped') {
        $sql .= ", shipped_at = NOW()";
    } elseif ($newStatus === 'delivered') {
        $sql .= ", delivered_at = NOW(), actual_delivery_date = CURDATE()";
    }
    
    $sql .= " WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Log status change
    $logStmt = $db->prepare("
        INSERT INTO shipping_status_history (order_shipping_id, old_status, new_status, notes)
        VALUES (:id, :old, :new, :notes)
    ");
    
    $logStmt->execute([
        ':id' => $orderShippingId,
        ':old' => $oldStatus,
        ':new' => $newStatus,
        ':notes' => $notes
    ]);
    
    return true;
}
