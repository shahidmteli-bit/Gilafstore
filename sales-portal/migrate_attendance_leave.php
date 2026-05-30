<?php
/**
 * Migration: Add attendance, leave tables + assigned_area column
 * Run once, then delete this file.
 */
require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();

$queries = [
    // Add assigned_area to sales_executives
    "ALTER TABLE sales_executives ADD COLUMN assigned_area VARCHAR(200) DEFAULT NULL AFTER location",

    // Attendance table
    "CREATE TABLE IF NOT EXISTS sales_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        executive_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        check_in_time TIME DEFAULT NULL,
        check_out_time TIME DEFAULT NULL,
        status ENUM('present','absent','half_day','on_leave') DEFAULT 'present',
        latitude DECIMAL(11,8) DEFAULT NULL,
        longitude DECIMAL(11,8) DEFAULT NULL,
        google_maps_url VARCHAR(500) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_exec_date (executive_id, attendance_date),
        FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Leave requests table
    "CREATE TABLE IF NOT EXISTS sales_leaves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        executive_id INT NOT NULL,
        leave_type ENUM('annual_leave','sick_leave','casual_leave','emergency') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        total_days INT NOT NULL DEFAULT 1,
        reason TEXT NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_remarks TEXT DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Payment Collections table
    "CREATE TABLE IF NOT EXISTS sales_collections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        collection_number VARCHAR(30) NOT NULL UNIQUE,
        executive_id INT NOT NULL,
        party_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_method ENUM('cash','cheque','online_transfer') NOT NULL,
        cheque_number VARCHAR(50) DEFAULT NULL,
        cheque_date DATE DEFAULT NULL,
        cheque_bank VARCHAR(100) DEFAULT NULL,
        online_account ENUM('gilaf_account','other') DEFAULT NULL,
        online_reference VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        receipt_image VARCHAR(255) DEFAULT NULL,
        status ENUM('pending','confirmed','rejected') DEFAULT 'pending',
        admin_remarks TEXT DEFAULT NULL,
        confirmed_by INT DEFAULT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        is_settled TINYINT(1) DEFAULT 0,
        settled_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE RESTRICT,
        FOREIGN KEY (party_id) REFERENCES sales_parties(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Indexes
    "CREATE INDEX IF NOT EXISTS idx_attendance_date ON sales_attendance(attendance_date)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_exec ON sales_attendance(executive_id)",
    "CREATE INDEX IF NOT EXISTS idx_leaves_exec ON sales_leaves(executive_id)",
    "CREATE INDEX IF NOT EXISTS idx_leaves_status ON sales_leaves(status)",
    "CREATE INDEX IF NOT EXISTS idx_collections_exec ON sales_collections(executive_id)",
    "CREATE INDEX IF NOT EXISTS idx_collections_status ON sales_collections(status)",
    "CREATE INDEX IF NOT EXISTS idx_collections_party ON sales_collections(party_id)",
];

$ok = 0;
$errors = [];
foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        $ok++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'Duplicate key name') !== false) {
            $ok++;
        } else {
            $errors[] = $e->getMessage();
        }
    }
}

echo "<h3>Attendance & Leave Migration</h3>";
echo "<p style='color:green;'>✅ {$ok} operations completed.</p>";
if ($errors) {
    foreach ($errors as $err) echo "<p style='color:red;'>❌ {$err}</p>";
}
echo "<p><a href='index.php'>→ Go to Dashboard</a></p>";
