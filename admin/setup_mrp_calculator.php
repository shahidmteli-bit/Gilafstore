<?php
require_once '../includes/db_connect.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mrp_calculations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            data TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    echo "✅ MRP Calculator database table created successfully!";
    echo "<br><br>";
    echo "<a href='mrp_calculator.php' style='display:inline-block;padding:10px 20px;background:#2C5530;color:white;text-decoration:none;border-radius:5px;'>🧮 Go to MRP Calculator</a>";
    
} catch(PDOException $e) {
    echo "❌ Table creation failed: " . $e->getMessage();
}
?>
