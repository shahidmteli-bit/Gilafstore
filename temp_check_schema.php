<?php
require_once 'includes/db_connect.php';
$db = get_db_connection();
$stmt = $db->query('DESCRIBE product_weights');
echo "=== product_weights schema ===\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-20s %-15s %-10s %-10s %-10s %-20s\n", 
        $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default'], $row['Extra']);
}
