<?php
/**
 * Settings Management Functions
 * Functions to get and set site configuration settings
 * Uses existing gst_settings table
 */

require_once __DIR__ . '/db_connect.php';

/**
 * Get a setting value from gst_settings table
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value
 */
function get_setting($key, $default = null) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT setting_value FROM gst_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("Error fetching setting {$key}: " . $e->getMessage());
        return $default;
    }
}

/**
 * Update a setting value in gst_settings table
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @param int $userId User ID making the update
 * @return bool Success status
 */
function update_setting($key, $value, $userId = 1) {
    try {
        $db = get_db_connection();
        
        // Check if setting exists
        $stmt = $db->prepare("SELECT id FROM gst_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        
        if ($stmt->fetch()) {
            // Update existing
            $stmt = $db->prepare("UPDATE gst_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $stmt->execute([$value, $userId, $key]);
        } else {
            // Insert new
            $stmt = $db->prepare("INSERT INTO gst_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)");
            $stmt->execute([$key, $value, $userId]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating setting {$key}: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all settings as associative array
 * @return array Settings array
 */
function get_all_settings() {
    try {
        $db = get_db_connection();
        $stmt = $db->query("SELECT setting_key, setting_value, setting_type, description FROM gst_settings");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'type' => $row['setting_type'],
                'description' => $row['description']
            ];
        }
        
        return $settings;
    } catch (PDOException $e) {
        error_log("Error fetching all settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get GST rate from settings
 * @return float GST rate as decimal (e.g., 5 for 5%)
 */
function get_gst_rate() {
    return (float)get_setting('default_gst_rate', 5);
}

/**
 * Get promotional discount rate from settings
 * @return float Discount rate as decimal (e.g., 10 for 10%)
 */
function get_promotional_discount() {
    return (float)get_setting('promotional_discount', 10);
}
?>
