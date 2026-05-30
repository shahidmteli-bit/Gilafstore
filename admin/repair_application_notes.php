<?php
/**
 * One-time repair script: Restore lost pincode/city/state data in admin_notes
 * for approved applications where process_application.php overwrote the data.
 * 
 * This script reads the business_address and attempts to re-populate
 * missing admin_notes fields. Run once, then delete this file.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db_connection();

// Find all applications where admin_notes is missing pincode
$stmt = $db->query("SELECT id, admin_notes, business_address, owner_address FROM distributor_applications");
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fixed = 0;

echo '<h2>Repairing Application Notes</h2>';
echo '<pre>';

foreach ($apps as $app) {
    $notes = json_decode($app['admin_notes'] ?? '{}', true) ?: [];
    
    // Skip if already has pincode
    if (!empty($notes['pincode'])) {
        echo "App #{$app['id']}: Already has pincode ({$notes['pincode']}) — skipped\n";
        continue;
    }
    
    // Try to extract pincode from business_address (look for 6-digit number)
    $address = $app['business_address'] . ' ' . $app['owner_address'];
    $pincodeFound = '';
    if (preg_match('/\b(\d{6})\b/', $address, $m)) {
        $pincodeFound = $m[1];
    }
    
    // For application #4 specifically (Sopore, J&K, 193201)
    if ($app['id'] == 4 && empty($pincodeFound)) {
        $pincodeFound = '193201';
    }
    
    if (!empty($pincodeFound)) {
        $notes['pincode'] = $pincodeFound;
    }
    
    // Try to extract city/state from address
    if (empty($notes['city'])) {
        // Common pattern: "..., City"  or "City, State"
        if (stripos($address, 'Sopore') !== false) $notes['city'] = 'Sopore';
    }
    if (empty($notes['state'])) {
        if (stripos($address, 'Kashmir') !== false || stripos($address, 'Sopore') !== false) {
            $notes['state'] = 'Jammu and Kashmir';
        }
    }
    
    // Update if we found anything new
    if (!empty($notes['pincode']) || !empty($notes['city']) || !empty($notes['state'])) {
        $stmt2 = $db->prepare("UPDATE distributor_applications SET admin_notes = :notes WHERE id = :id");
        $stmt2->execute([':notes' => json_encode($notes), ':id' => $app['id']]);
        $fixed++;
        echo "App #{$app['id']}: REPAIRED — pincode={$notes['pincode']}, city={$notes['city']}, state={$notes['state']}\n";
    } else {
        echo "App #{$app['id']}: No data could be recovered — manual update needed\n";
    }
}

echo "\n\nDone! Fixed $fixed application(s).\n";
echo "You can now delete this file: admin/repair_application_notes.php\n";
echo '</pre>';
