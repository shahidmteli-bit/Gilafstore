<?php
require_once __DIR__ . '/../includes/functions.php';

try {
    $pdo = db_connect();
    
    // Create hero_banner_slides table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hero_banner_slides (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(500) NOT NULL,
            heading_text VARCHAR(255) DEFAULT NULL,
            sub_text TEXT DEFAULT NULL,
            cta_text VARCHAR(100) DEFAULT NULL,
            cta_link VARCHAR(500) DEFAULT NULL,
            cta2_text VARCHAR(100) DEFAULT NULL,
            cta2_link VARCHAR(500) DEFAULT NULL,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create hero_banner_settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hero_banner_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value VARCHAR(255) NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insert default settings
    $pdo->exec("INSERT IGNORE INTO hero_banner_settings (setting_key, setting_value) VALUES ('slider_enabled', '0'), ('slider_timer', '5')");
    
    // Create uploads directory
    $uploadDir = __DIR__ . '/../assets/uploads/hero_banner/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    echo "SUCCESS: Hero Banner tables created!";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
