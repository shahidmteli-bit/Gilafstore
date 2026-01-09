<?php
/**
 * Database Migration: Create settings table for managing site configurations
 * Run this file once to create the settings table
 */

require_once __DIR__ . '/../includes/db_connect.php';

try {
    $db = get_db_connection();
    
    // Create settings table
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type VARCHAR(50) DEFAULT 'text',
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $db->exec($sql);
    echo "✅ Settings table created successfully!<br>";
    
    // Insert default GST rate
    $stmt = $db->prepare("
        INSERT INTO settings (setting_key, setting_value, setting_type, description) 
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = setting_value
    ");
    
    $stmt->execute(['gst_rate', '5', 'number', 'GST rate percentage (e.g., 5 for 5%)']);
    echo "✅ Default GST rate (5%) inserted!<br>";
    
    // Insert default discount rate
    $stmt->execute(['promotional_discount', '10', 'number', 'Promotional discount percentage for savings display']);
    echo "✅ Default promotional discount (10%) inserted!<br>";
    
    echo "<br><strong>Migration completed successfully!</strong><br>";
    echo "You can now manage these settings from the admin panel.";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
