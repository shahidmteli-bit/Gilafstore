<?php
/**
 * Sales Portal V2 Upgrade Migration
 * Adds tables and columns for: visit schedules, party ratings, gifts,
 * outstanding tracking, GST on order items, turnover, etc.
 * Safe to re-run — uses IF NOT EXISTS and column existence checks.
 */
require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();
$success = 0;
$errors = [];

// Helper: run query safely
function run_safe($db, $sql, &$success, &$errors) {
    try {
        $db->exec($sql);
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') === false
            && strpos($e->getMessage(), 'already exists') === false) {
            $errors[] = $e->getMessage();
        } else {
            $success++;
        }
    }
}

// Helper: add column if not exists
function add_col($db, $table, $col, $definition, &$success, &$errors) {
    try {
        $check = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$col}'");
        if ($check->rowCount() === 0) {
            $db->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$definition}");
            $success++;
            echo "<p style='color:green;'>✅ Added {$table}.{$col}</p>";
        } else {
            $success++;
        }
    } catch (PDOException $e) {
        $errors[] = "ALTER {$table}.{$col}: " . $e->getMessage();
    }
}

// ═══════════════════════════════════════════════════════════════════
// 1. NEW COLUMNS on sales_parties
// ═══════════════════════════════════════════════════════════════════
add_col($db, 'sales_parties', 'rating', "DECIMAL(3,2) DEFAULT 0.00 AFTER notes", $success, $errors);
add_col($db, 'sales_parties', 'rating_label', "ENUM('good','average','low','blocked') DEFAULT 'good' AFTER rating", $success, $errors);
add_col($db, 'sales_parties', 'turnover_amount', "DECIMAL(14,2) DEFAULT 0.00 AFTER rating_label", $success, $errors);
add_col($db, 'sales_parties', 'last_visit_date', "DATE DEFAULT NULL AFTER turnover_amount", $success, $errors);
add_col($db, 'sales_parties', 'consecutive_low_recovery', "INT DEFAULT 0 AFTER last_visit_date", $success, $errors);
add_col($db, 'sales_parties', 'oldest_due_date', "DATE DEFAULT NULL AFTER consecutive_low_recovery", $success, $errors);

// ═══════════════════════════════════════════════════════════════════
// 2. NEW COLUMNS on sales_order_items (GST tracking)
// ═══════════════════════════════════════════════════════════════════
add_col($db, 'sales_order_items', 'base_price', "DECIMAL(10,2) DEFAULT 0.00 AFTER price", $success, $errors);
add_col($db, 'sales_order_items', 'gst_rate', "DECIMAL(5,2) DEFAULT 0.00 AFTER base_price", $success, $errors);
add_col($db, 'sales_order_items', 'gst_amount', "DECIMAL(10,2) DEFAULT 0.00 AFTER gst_rate", $success, $errors);

// ═══════════════════════════════════════════════════════════════════
// 3. Weekly Visit Schedules (admin-managed per executive)
// ═══════════════════════════════════════════════════════════════════
run_safe($db, "CREATE TABLE IF NOT EXISTS sales_visit_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    executive_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sun,1=Mon,...6=Sat',
    district VARCHAR(100) NOT NULL,
    area_name VARCHAR(200) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    is_off TINYINT(1) DEFAULT 0 COMMENT '1 = week off',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_exec_day (executive_id, day_of_week),
    FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $success, $errors);

// ═══════════════════════════════════════════════════════════════════
// 4. Party Visit Tracking (Reached button)
// ═══════════════════════════════════════════════════════════════════
run_safe($db, "CREATE TABLE IF NOT EXISTS sales_party_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    executive_id INT NOT NULL,
    party_id INT NOT NULL,
    visit_date DATE NOT NULL,
    reached_at DATETIME DEFAULT NULL,
    latitude DECIMAL(11,8) DEFAULT NULL,
    longitude DECIMAL(11,8) DEFAULT NULL,
    distance_meters INT DEFAULT NULL,
    outcome ENUM('visited','no_order','ordered','payment_collected','not_available') DEFAULT 'visited',
    recovery_amount DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_visit (executive_id, party_id, visit_date),
    INDEX idx_visit_date (visit_date),
    FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE CASCADE,
    FOREIGN KEY (party_id) REFERENCES sales_parties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $success, $errors);

// ═══════════════════════════════════════════════════════════════════
// 5. Gift / Promotional Item Types (admin-defined)
// ═══════════════════════════════════════════════════════════════════
run_safe($db, "CREATE TABLE IF NOT EXISTS sales_gift_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    default_value DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $success, $errors);

// ═══════════════════════════════════════════════════════════════════
// 6. Gift Distribution Records
// ═══════════════════════════════════════════════════════════════════
run_safe($db, "CREATE TABLE IF NOT EXISTS sales_gift_distributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    executive_id INT NOT NULL,
    party_id INT NOT NULL,
    gift_item_id INT DEFAULT NULL,
    custom_item_name VARCHAR(200) DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 1,
    amount DECIMAL(10,2) DEFAULT 0.00,
    district VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    distributed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exec (executive_id),
    INDEX idx_party (party_id),
    INDEX idx_district (district),
    FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE CASCADE,
    FOREIGN KEY (party_id) REFERENCES sales_parties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $success, $errors);

// ═══════════════════════════════════════════════════════════════════
// 7. Insert default gift items
// ═══════════════════════════════════════════════════════════════════
try {
    $giftCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_gift_items')['cnt'] ?? 0;
    if ($giftCount == 0) {
        $defaults = ['Display Board', 'Promotional Material', 'Sample Pack', 'Banner', 'Standee', 'Visiting Cards'];
        foreach ($defaults as $g) {
            db_query('INSERT INTO sales_gift_items (name) VALUES (?)', [$g]);
        }
        echo "<p style='color:green;'>✅ Default gift items created.</p>";
    }
} catch (PDOException $e) { /* safe */ }

// ═══════════════════════════════════════════════════════════════════
// 8. Back-fill base_price from price where missing
// ═══════════════════════════════════════════════════════════════════
try {
    $db->exec("UPDATE sales_order_items SET base_price = price WHERE base_price = 0 AND price > 0");
    echo "<p style='color:green;'>✅ Back-filled base_price on existing order items.</p>";
} catch (PDOException $e) { /* safe */ }

// ═══════════════════════════════════════════════════════════════════
// 9. Recalculate turnover for all parties
// ═══════════════════════════════════════════════════════════════════
try {
    $db->exec("UPDATE sales_parties sp
        SET sp.turnover_amount = (
            SELECT COALESCE(SUM(so.total_amount), 0)
            FROM sales_orders so
            WHERE so.party_id = sp.id AND so.status IN ('approved','dispatched','delivered')
        )");
    echo "<p style='color:green;'>✅ Recalculated turnover for all parties.</p>";
} catch (PDOException $e) { /* safe */ }

// ═══════════════════════════════════════════════════════════════════
// DONE
// ═══════════════════════════════════════════════════════════════════
echo "<h2>Sales Portal V2 Migration</h2>";
echo "<p style='color:green;'>✅ {$success} operations completed.</p>";
if (!empty($errors)) {
    echo "<h3 style='color:red;'>Errors:</h3>";
    foreach ($errors as $err) echo "<p style='color:red;'>❌ {$err}</p>";
} else {
    echo "<p style='color:green;'>✅ All tables and columns ready. No errors.</p>";
}
echo "<br><a href='" . base_url('sales-portal/') . "'>→ Back to Sales Portal</a>";
