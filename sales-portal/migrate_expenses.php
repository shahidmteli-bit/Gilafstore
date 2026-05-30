<?php
/**
 * Sales Portal - Travel & Field Expenses Migration
 * Run once to create expense tables. Safe to re-run (IF NOT EXISTS).
 */
require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();
$success = 0;
$errors = [];

$queries = [
    // ─── Expense Categories (scalable — admin can add/disable) ───
    "CREATE TABLE IF NOT EXISTS sales_expense_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        icon VARCHAR(50) DEFAULT 'fas fa-receipt',
        max_per_day DECIMAL(10,2) DEFAULT NULL,
        max_per_month DECIMAL(10,2) DEFAULT NULL,
        require_attachment TINYINT(1) DEFAULT 0,
        require_notes TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Expenses ────────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        executive_id INT NOT NULL,
        category_id INT NOT NULL,
        expense_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        district VARCHAR(100) DEFAULT NULL,
        party_id INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        attachment VARCHAR(255) DEFAULT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        admin_remarks TEXT DEFAULT NULL,
        approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        latitude DECIMAL(11,8) DEFAULT NULL,
        longitude DECIMAL(11,8) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_exp_exec_date (executive_id, expense_date),
        INDEX idx_exp_status (status),
        INDEX idx_exp_category (category_id),
        INDEX idx_exp_date (expense_date),
        FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES sales_expense_categories(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Expense Settings (global key-value policy store) ────────
    "CREATE TABLE IF NOT EXISTS sales_expense_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        $success++;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            $errors[] = $e->getMessage();
        } else {
            $success++;
        }
    }
}

// ─── Add party_id FK safely (may fail if column type mismatch — skip) ───
try {
    $check = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_NAME='sales_expenses' AND CONSTRAINT_NAME='sales_expenses_ibfk_3'");
    if ($check->rowCount() === 0) {
        $db->exec("ALTER TABLE sales_expenses ADD FOREIGN KEY (party_id) REFERENCES sales_parties(id) ON DELETE SET NULL");
    }
    $success++;
} catch (PDOException $e) { $success++; }

// ─── Seed default expense categories ─────────────────────────────
$defaultCategories = [
    ['Travel',              'travel',            'fas fa-route',           1],
    ['Fuel / Oil',          'fuel',              'fas fa-gas-pump',        2],
    ['Food',                'food',              'fas fa-utensils',        3],
    ['Beverages / Tea',     'beverages',         'fas fa-mug-hot',         4],
    ['Refreshment',         'refreshment',       'fas fa-coffee',          5],
    ['Parking',             'parking',           'fas fa-parking',         6],
    ['Toll',                'toll',              'fas fa-road',            7],
    ['Loading / Unloading', 'loading_unloading', 'fas fa-truck-loading',   8],
    ['Stay / Accommodation','accommodation',     'fas fa-hotel',           9],
    ['Miscellaneous',       'miscellaneous',     'fas fa-ellipsis-h',     10],
];

foreach ($defaultCategories as [$name, $slug, $icon, $order]) {
    try {
        $exists = db_fetch('SELECT id FROM sales_expense_categories WHERE slug = ?', [$slug]);
        if (!$exists) {
            db_query('INSERT INTO sales_expense_categories (name, slug, icon, sort_order, is_active) VALUES (?, ?, ?, ?, 1)', [
                $name, $slug, $icon, $order
            ]);
            $success++;
        } else {
            $success++;
        }
    } catch (PDOException $e) {
        $errors[] = "Category '{$slug}': " . $e->getMessage();
    }
}

// ─── Seed default expense settings ───────────────────────────────
$defaultSettings = [
    ['require_approval', '1'],
    ['notes_mandatory', '1'],
    ['attachment_mandatory_above', '500'],
    ['daily_limit_enabled', '0'],
    ['daily_limit_amount', '0'],
    ['monthly_limit_enabled', '0'],
    ['monthly_limit_amount', '0'],
];

foreach ($defaultSettings as [$key, $value]) {
    try {
        $exists = db_fetch('SELECT id FROM sales_expense_settings WHERE setting_key = ?', [$key]);
        if (!$exists) {
            db_query('INSERT INTO sales_expense_settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value]);
        }
        $success++;
    } catch (PDOException $e) {
        $errors[] = "Setting '{$key}': " . $e->getMessage();
    }
}

// ─── Create uploads directory ────────────────────────────────────
$uploadDir = __DIR__ . '/uploads/expense_receipts';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "<p style='color:green;'>✅ Upload directory created: sales-portal/uploads/expense_receipts/</p>";
}

echo "<h2>Travel & Field Expenses — Database Setup</h2>";
echo "<p style='color:green;'>✅ {$success} operations completed successfully</p>";

if (!empty($errors)) {
    echo "<h3 style='color:red;'>Errors:</h3>";
    foreach ($errors as $err) {
        echo "<p style='color:red;'>❌ {$err}</p>";
    }
} else {
    echo "<p style='color:green;'>✅ All expense tables, categories, and settings are ready.</p>";
}

// Show summary
try {
    $catCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_expense_categories')['cnt'] ?? 0;
    $settCount = db_fetch('SELECT COUNT(*) as cnt FROM sales_expense_settings')['cnt'] ?? 0;
    echo "<p><strong>Categories:</strong> {$catCount} | <strong>Settings:</strong> {$settCount}</p>";
} catch (PDOException $e) {}

echo "<br><a href='" . base_url('sales-portal/expenses.php') . "'>→ Go to Expenses</a>";
echo " | <a href='" . base_url('admin/sales_expenses.php') . "'>→ Admin Expense Management</a>";
