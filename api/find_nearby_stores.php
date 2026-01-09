<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$pincode = trim($_GET['pincode'] ?? '');

if (empty($pincode)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a pincode'
    ]);
    exit;
}

// Validate pincode format (6 digits)
if (!preg_match('/^[0-9]{6}$/', $pincode)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid 6-digit pincode'
    ]);
    exit;
}

try {
    $db = get_db_connection();
    
    // Get all active approved stores with coordinates
    $stmt = $db->prepare("
        SELECT 
            id,
            store_type,
            store_name,
            owner_name,
            address,
            phone,
            email,
            pincode,
            city,
            state,
            latitude,
            longitude,
            google_maps_url
        FROM approved_stores 
        WHERE is_active = 1 
        AND latitude IS NOT NULL 
        AND longitude IS NOT NULL
        ORDER BY store_type DESC, store_name ASC
    ");
    
    $stmt->execute();
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($stores)) {
        echo json_encode([
            'success' => false,
            'message' => 'No stores available in the database'
        ]);
        exit;
    }
    
    // Try to get coordinates for the search pincode using a simple state-based reference
    // This is a fallback - ideally you'd use a geocoding API
    $referenceCoords = getReferenceCoordsForPincode($pincode);
    
    // Calculate distances
    $storesWithDistance = [];
    foreach ($stores as $store) {
        $distance = calculateDistance(
            $referenceCoords['lat'],
            $referenceCoords['lng'],
            floatval($store['latitude']),
            floatval($store['longitude'])
        );
        
        $store['distance'] = $distance;
        $storesWithDistance[] = $store;
    }
    
    // Sort by distance
    usort($storesWithDistance, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
    
    // Get top 5 nearest stores
    $nearestStores = array_slice($storesWithDistance, 0, 5);
    
    // Format store type labels
    $typeLabels = [
        'official_store' => 'Official Gilaf Store',
        'distributor' => 'Distributor',
        'reseller' => 'Reseller'
    ];
    
    $typeColors = [
        'official_store' => '#C5A059', // Gold
        'distributor' => '#1A3C34', // Green
        'reseller' => '#666666' // Gray
    ];
    
    foreach ($nearestStores as &$store) {
        $store['type_label'] = $typeLabels[$store['store_type']] ?? 'Store';
        $store['type_color'] = $typeColors[$store['store_type']] ?? '#666666';
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($nearestStores),
        'pincode' => $pincode,
        'reference_location' => $referenceCoords['name'],
        'stores' => $nearestStores
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching for stores'
    ]);
}

/**
 * Get reference coordinates based on pincode prefix (state-based approximation)
 */
function getReferenceCoordsForPincode($pincode) {
    $prefix = substr($pincode, 0, 2);
    
    // Major state/region reference coordinates based on first 2 digits of pincode
    $references = [
        '11' => ['lat' => 28.6139, 'lng' => 77.2090, 'name' => 'Delhi'],
        '12' => ['lat' => 28.7041, 'lng' => 77.1025, 'name' => 'Haryana'],
        '13' => ['lat' => 31.1048, 'lng' => 77.1734, 'name' => 'Punjab/Himachal'],
        '14' => ['lat' => 30.7333, 'lng' => 76.7794, 'name' => 'Punjab'],
        '15' => ['lat' => 30.9010, 'lng' => 75.8573, 'name' => 'Punjab'],
        '16' => ['lat' => 30.3165, 'lng' => 78.0322, 'name' => 'Uttarakhand'],
        '17' => ['lat' => 31.1048, 'lng' => 77.1734, 'name' => 'Himachal Pradesh'],
        '18' => ['lat' => 32.7266, 'lng' => 74.8570, 'name' => 'Jammu'],
        '19' => ['lat' => 34.0837, 'lng' => 74.7973, 'name' => 'Kashmir'],
        '20' => ['lat' => 26.9124, 'lng' => 75.7873, 'name' => 'Rajasthan'],
        '21' => ['lat' => 26.4499, 'lng' => 80.3319, 'name' => 'Uttar Pradesh'],
        '22' => ['lat' => 25.5941, 'lng' => 85.1376, 'name' => 'Bihar'],
        '23' => ['lat' => 23.3441, 'lng' => 85.3096, 'name' => 'Jharkhand'],
        '24' => ['lat' => 26.1445, 'lng' => 91.7362, 'name' => 'Assam'],
        '40' => ['lat' => 19.0760, 'lng' => 72.8777, 'name' => 'Mumbai'],
        '41' => ['lat' => 18.5204, 'lng' => 73.8567, 'name' => 'Maharashtra'],
        '42' => ['lat' => 21.1458, 'lng' => 79.0882, 'name' => 'Maharashtra'],
        '50' => ['lat' => 17.3850, 'lng' => 78.4867, 'name' => 'Telangana'],
        '51' => ['lat' => 15.3173, 'lng' => 75.7139, 'name' => 'Karnataka'],
        '56' => ['lat' => 12.9716, 'lng' => 77.5946, 'name' => 'Bangalore'],
        '60' => ['lat' => 13.0827, 'lng' => 80.2707, 'name' => 'Chennai'],
        '61' => ['lat' => 11.1271, 'lng' => 78.6569, 'name' => 'Tamil Nadu'],
        '68' => ['lat' => 10.8505, 'lng' => 76.2711, 'name' => 'Kerala'],
        '70' => ['lat' => 22.5726, 'lng' => 88.3639, 'name' => 'Kolkata'],
        '71' => ['lat' => 22.9868, 'lng' => 87.8550, 'name' => 'West Bengal'],
        '75' => ['lat' => 21.1702, 'lng' => 72.8311, 'name' => 'Gujarat'],
        '38' => ['lat' => 23.0225, 'lng' => 72.5714, 'name' => 'Gujarat'],
    ];
    
    if (isset($references[$prefix])) {
        return $references[$prefix];
    }
    
    // Default to Delhi if no match
    return ['lat' => 28.6139, 'lng' => 77.2090, 'name' => 'North India'];
}

/**
 * Calculate distance between two coordinates using Haversine formula
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;
    
    return round($distance, 1);
}
