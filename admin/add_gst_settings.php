<?php
/**
 * Add GST Rate Settings to Existing gst_settings Table
 * Run this file once to add the new settings
 */

require_once __DIR__ . '/../includes/db_connect.php';

try {
    $db = get_db_connection();
    
    // Insert default GST rate if not exists
    $stmt = $db->prepare("
        INSERT INTO gst_settings (setting_key, setting_value, setting_type, description, updated_by) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = setting_value
    ");
    
    $stmt->execute(['default_gst_rate', '5', 'number', 'Default GST rate percentage (e.g., 5 for 5%)', 1]);
    echo "✅ Default GST rate (5%) added to gst_settings!<br>";
    
    // Insert default promotional discount
    $stmt->execute(['promotional_discount', '10', 'number', 'Promotional discount percentage for savings display', 1]);
    echo "✅ Default promotional discount (10%) added to gst_settings!<br>";
    
    echo "<br><strong>Settings added successfully!</strong><br>";
    echo "You can now manage these settings from the admin GST configuration page.";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
