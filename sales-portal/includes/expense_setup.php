<?php
/**
 * Auto-setup for Travel & Field Expenses tables.
 * Include this at the top of any expense-related page.
 * Creates tables + seeds default data if they don't exist.
 * Safe to include multiple times (idempotent).
 */
if (!defined('EXPENSE_SETUP_DONE')) {
    define('EXPENSE_SETUP_DONE', true);

    $db = get_db_connection();

    // Quick check: does the main table exist?
    $tablesExist = true;
    try {
        $db->query('SELECT 1 FROM sales_expense_categories LIMIT 1');
    } catch (PDOException $e) {
        $tablesExist = false;
    }

    if (!$tablesExist) {
        // ─── Create tables ───────────────────────────────────────
        $setupQueries = [
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

            "CREATE TABLE IF NOT EXISTS sales_expense_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($setupQueries as $sql) {
            try { $db->exec($sql); } catch (PDOException $e) { /* safe */ }
        }

        // ─── Seed default categories ─────────────────────────────
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
                }
            } catch (PDOException $e) { /* safe */ }
        }

        // ─── Seed default settings ───────────────────────────────
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
            } catch (PDOException $e) { /* safe */ }
        }

        // ─── Create uploads directory ────────────────────────────
        $uploadDir = realpath(__DIR__ . '/..') . '/uploads/expense_receipts';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
    }
}
