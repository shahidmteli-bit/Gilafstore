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
    
    // Search for stores by pincode
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
        WHERE pincode = :pincode AND is_active = 1
        ORDER BY store_type DESC, store_name ASC
    ");
    
    $stmt->execute([':pincode' => $pincode]);
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($stores)) {
        echo json_encode([
            'success' => false,
            'message' => 'No stores found for pincode ' . $pincode
        ]);
        exit;
    }
    
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
    
    foreach ($stores as &$store) {
        $store['type_label'] = $typeLabels[$store['store_type']] ?? 'Store';
        $store['type_color'] = $typeColors[$store['store_type']] ?? '#666666';
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($stores),
        'pincode' => $pincode,
        'stores' => $stores
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching for stores'
    ]);
}
