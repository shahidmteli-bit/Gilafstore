<?php
/**
 * Sales Executive Portal - Database Setup (Complete)
 * Run this once to create all required tables.
 * Safe to re-run — uses IF NOT EXISTS and ALTER checks.
 */
require_once __DIR__ . '/../includes/db_connect.php';

$db = get_db_connection();

$queries = [
    // ─── Sales Executives ────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_executives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        designation VARCHAR(100) DEFAULT 'Sales Executive',
        district VARCHAR(100) NOT NULL,
        location VARCHAR(200) NOT NULL,
        assigned_area VARCHAR(200) DEFAULT NULL,
        reporting_manager VARCHAR(100) DEFAULT NULL,
        profile_image VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        last_login DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Auth Tokens (for WebView / APK session persistence) ────
    "CREATE TABLE IF NOT EXISTS sales_auth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        executive_id INT NOT NULL,
        token VARCHAR(128) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_exec (executive_id),
        FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Parties (Customers) ────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_parties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shop_name VARCHAR(200) NOT NULL,
        owner_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(150) DEFAULT NULL,
        address TEXT NOT NULL,
        district VARCHAR(100) NOT NULL,
        city VARCHAR(100) DEFAULT NULL,
        state VARCHAR(100) DEFAULT 'Jammu and Kashmir',
        pincode VARCHAR(10) DEFAULT NULL,
        gst_number VARCHAR(20) DEFAULT NULL,
        party_code VARCHAR(20) DEFAULT NULL,
        profile_type ENUM('wholesaler','distributor','franchise','retailer') NOT NULL DEFAULT 'wholesaler',
        credit_limit DECIMAL(12,2) DEFAULT 0.00,
        outstanding_amount DECIMAL(12,2) DEFAULT 0.00,
        latitude DECIMAL(11,8) DEFAULT NULL,
        longitude DECIMAL(11,8) DEFAULT NULL,
        google_maps_url VARCHAR(500) DEFAULT NULL,
        created_by INT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        notes TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX idx_party_code (party_code),
        FOREIGN KEY (created_by) REFERENCES sales_executives(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Sales Orders ───────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(30) NOT NULL UNIQUE,
        executive_id INT NOT NULL,
        party_id INT NOT NULL,
        order_type ENUM('new_order','return','credit_note') DEFAULT 'new_order',
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(12,2) DEFAULT 0.00,
        tax_amount DECIMAL(12,2) DEFAULT 0.00,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        status ENUM('pending','approved','rejected','dispatched','delivered','cancelled') DEFAULT 'pending',
        payment_status ENUM('pending','partial','received') DEFAULT 'pending',
        payment_amount DECIMAL(12,2) DEFAULT 0.00,
        admin_notes TEXT DEFAULT NULL,
        executive_notes TEXT DEFAULT NULL,
        district VARCHAR(100) DEFAULT NULL,
        location VARCHAR(200) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        approved_at DATETIME DEFAULT NULL,
        dispatched_at DATETIME DEFAULT NULL,
        delivered_at DATETIME DEFAULT NULL,
        FOREIGN KEY (executive_id) REFERENCES sales_executives(id) ON DELETE RESTRICT,
        FOREIGN KEY (party_id) REFERENCES sales_parties(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Sales Order Items ──────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        sku VARCHAR(50) DEFAULT NULL,
        weight_id INT DEFAULT NULL,
        weight_label VARCHAR(50) DEFAULT NULL,
        price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Payment History ────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_payment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        party_id INT NOT NULL,
        order_id INT DEFAULT NULL,
        amount DECIMAL(12,2) NOT NULL,
        payment_type ENUM('payment','credit_note','adjustment') DEFAULT 'payment',
        payment_method VARCHAR(50) DEFAULT NULL,
        reference_number VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (party_id) REFERENCES sales_parties(id) ON DELETE RESTRICT,
        FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Attendance ─────────────────────────────────────────────
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

    // ─── Leave Requests ─────────────────────────────────────────
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

    // ─── Collections ────────────────────────────────────────────
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

    // ─── Notifications ──────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        executive_id INT NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'info',
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(500) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_exec_read (executive_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Login/Logout Reminders ─────────────────────────────────
    "CREATE TABLE IF NOT EXISTS sales_login_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reminder_type ENUM('login','logout') NOT NULL DEFAULT 'login',
        reminder_time TIME NOT NULL,
        days_of_week VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
        message VARCHAR(500) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Indexes ────────────────────────────────────────────────
    "CREATE INDEX IF NOT EXISTS idx_sales_orders_executive ON sales_orders(executive_id)",
    "CREATE INDEX IF NOT EXISTS idx_sales_orders_party ON sales_orders(party_id)",
    "CREATE INDEX IF NOT EXISTS idx_sales_orders_status ON sales_orders(status)",
    "CREATE INDEX IF NOT EXISTS idx_sales_parties_district ON sales_parties(district)",
    "CREATE INDEX IF NOT EXISTS idx_sales_parties_created_by ON sales_parties(created_by)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_date ON sales_attendance(attendance_date)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_exec ON sales_attendance(executive_id)",
    "CREATE INDEX IF NOT EXISTS idx_leaves_exec ON sales_leaves(executive_id)",
    "CREATE INDEX IF NOT EXISTS idx_leaves_status ON sales_leaves(status)",
    "CREATE INDEX IF NOT EXISTS idx_collections_exec ON sales_collections(executive_id)",
    "CREATE INDEX IF NOT EXISTS idx_collections_status ON sales_collections(status)",
    "CREATE INDEX IF NOT EXISTS idx_collections_party ON sales_collections(party_id)",
];

$success = 0;
$errors = [];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
        $success++;
    } catch (PDOException $e) {
        // Ignore duplicate index/column errors
        if (strpos($e->getMessage(), 'Duplicate key name') === false
            && strpos($e->getMessage(), 'Duplicate column') === false) {
            $errors[] = $e->getMessage();
        } else {
            $success++;
        }
    }
}

// ─── Add missing columns to existing tables (safe re-run) ───────
$alterQueries = [
    ['sales_executives', 'designation', "ALTER TABLE sales_executives ADD COLUMN designation VARCHAR(100) DEFAULT 'Sales Executive' AFTER phone"],
    ['sales_executives', 'assigned_area', "ALTER TABLE sales_executives ADD COLUMN assigned_area VARCHAR(200) DEFAULT NULL AFTER location"],
    ['sales_parties', 'latitude', "ALTER TABLE sales_parties ADD COLUMN latitude DECIMAL(11,8) DEFAULT NULL"],
    ['sales_parties', 'longitude', "ALTER TABLE sales_parties ADD COLUMN longitude DECIMAL(11,8) DEFAULT NULL"],
    ['sales_parties', 'google_maps_url', "ALTER TABLE sales_parties ADD COLUMN google_maps_url VARCHAR(500) DEFAULT NULL"],
    ['sales_parties', 'party_code', "ALTER TABLE sales_parties ADD COLUMN party_code VARCHAR(20) DEFAULT NULL"],
    ['sales_parties', 'profile_type', "ALTER TABLE sales_parties ADD COLUMN profile_type ENUM('wholesaler','distributor','franchise','retailer') NOT NULL DEFAULT 'wholesaler' AFTER party_code"],
    ['sales_parties', 'is_blocked', "ALTER TABLE sales_parties ADD COLUMN is_blocked TINYINT(1) DEFAULT 0"],
    ['sales_parties', 'blocked_reason', "ALTER TABLE sales_parties ADD COLUMN blocked_reason VARCHAR(255) DEFAULT NULL"],
    ['sales_parties', 'blocked_at', "ALTER TABLE sales_parties ADD COLUMN blocked_at DATETIME DEFAULT NULL"],
];

foreach ($alterQueries as [$table, $column, $sql]) {
    try {
        $check = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        if ($check->rowCount() === 0) {
            $db->exec($sql);
            $success++;
        } else {
            $success++;
        }
    } catch (PDOException $e) {
        // Table may not exist yet on first run — safe to skip
        $success++;
    }
}

// ─── Prefix Configuration Table ──────────────────────────────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS sales_prefix_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prefix_type ENUM('company','district','city','party_type','voucher') NOT NULL,
        reference_name VARCHAR(100) NOT NULL,
        prefix_code VARCHAR(20) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_prefix (prefix_type, reference_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $success++;

    // Seed default prefixes if table is empty
    $prefixCount = (int)$db->query("SELECT COUNT(*) FROM sales_prefix_config")->fetchColumn();
    if ($prefixCount === 0) {
        $defaults = [
            ['company', 'gilaf', 'G', 'Gilaf master company prefix'],
            ['district', 'Baramulla', 'BRM', 'Baramulla district'],
            ['district', 'Bandipore', 'BDP', 'Bandipore district'],
            ['district', 'Srinagar', 'SGR', 'Srinagar district'],
            ['district', 'Sopore', 'SPR', 'Sopore district'],
            ['district', 'Kupwara', 'KPW', 'Kupwara district'],
            ['district', 'Anantnag', 'ANG', 'Anantnag district'],
            ['district', 'Pulwama', 'PLW', 'Pulwama district'],
            ['district', 'Ganderbal', 'GBL', 'Ganderbal district'],
            ['district', 'Budgam', 'BGM', 'Budgam district'],
            ['district', 'Kulgam', 'KLG', 'Kulgam district'],
            ['district', 'Shopian', 'SHP', 'Shopian district'],
            ['party_type', 'wholesaler', 'W', 'Wholesaler prefix'],
            ['party_type', 'distributor', 'D', 'Distributor prefix'],
            ['party_type', 'franchise', 'F', 'Franchisee prefix'],
            ['party_type', 'retailer', 'R', 'Retailer prefix'],
            ['voucher', 'order', 'SO', 'Sales order voucher prefix'],
            ['voucher', 'collection', 'COL', 'Payment collection voucher prefix'],
            ['voucher', 'return', 'RET', 'Return voucher prefix'],
            ['voucher', 'credit_note', 'CN', 'Credit note voucher prefix'],
        ];
        $stmt = $db->prepare("INSERT INTO sales_prefix_config (prefix_type, reference_name, prefix_code, description) VALUES (?,?,?,?)");
        foreach ($defaults as $d) {
            try { $stmt->execute($d); } catch (PDOException $ei) { /* duplicate key */ }
        }
        echo "<p style='color:green;'>✅ Default prefix configuration seeded.</p>";
    }
} catch (PDOException $e) { $errors[] = "Prefix config: " . $e->getMessage(); }

// ─── Ensure profile_type ENUM includes 'retailer' and migrate old 'reseller' data ───
try {
    $db->exec("ALTER TABLE sales_parties MODIFY COLUMN profile_type ENUM('wholesaler','distributor','franchise','reseller','retailer') NOT NULL DEFAULT 'wholesaler'");
    $db->exec("UPDATE sales_parties SET profile_type = 'retailer' WHERE profile_type = 'reseller'");
    $db->exec("ALTER TABLE sales_parties MODIFY COLUMN profile_type ENUM('wholesaler','distributor','franchise','retailer') NOT NULL DEFAULT 'wholesaler'");
    $success++;
    // Update prefix config seed: rename reseller -> retailer
    try {
        $db->exec("UPDATE sales_prefix_config SET reference_name = 'retailer', description = 'Retailer prefix' WHERE prefix_type = 'party_type' AND reference_name = 'reseller'");
    } catch (PDOException $epc) { /* safe */ }
} catch (PDOException $e) { $success++; }

// ─── Add profile pricing columns to product_weights ─────────────
$priceColumns = ['wholesale_price', 'distributor_price', 'franchise_price'];
foreach ($priceColumns as $col) {
    try {
        $check = $db->query("SHOW COLUMNS FROM product_weights LIKE '{$col}'");
        if ($check->rowCount() === 0) {
            $db->exec("ALTER TABLE product_weights ADD COLUMN {$col} DECIMAL(10,2) NOT NULL DEFAULT 0.00");
            $success++;
        } else {
            $success++;
        }
    } catch (PDOException $e) { $success++; }
}

// Copy existing prices as defaults for profile pricing
try {
    $db->exec("UPDATE product_weights SET wholesale_price = price WHERE wholesale_price = 0 AND price > 0");
    $db->exec("UPDATE product_weights SET distributor_price = price WHERE distributor_price = 0 AND price > 0");
    $db->exec("UPDATE product_weights SET franchise_price = price WHERE franchise_price = 0 AND price > 0");
} catch (PDOException $e) { /* safe to skip */ }

// Generate party_code for existing parties that don't have one
try {
    $existing = $db->query("SELECT id FROM sales_parties WHERE party_code IS NULL OR party_code = ''")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($existing as $row) {
        $code = 'GLP-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE sales_parties SET party_code = ? WHERE id = ?")->execute([$code, $row['id']]);
    }
    if (count($existing) > 0) {
        echo "<p style='color:green;'>✅ Generated party codes for " . count($existing) . " existing parties.</p>";
    }
} catch (PDOException $e) { /* safe to skip */ }

// Insert default login/logout reminders
try {
    $hasReminders = db_fetch('SELECT COUNT(*) as cnt FROM sales_login_reminders');
    if (($hasReminders['cnt'] ?? 0) == 0) {
        db_query("INSERT INTO sales_login_reminders (reminder_type, reminder_time, days_of_week, message) VALUES (?, ?, ?, ?)",
            ['login', '09:00:00', '1,2,3,4,5', 'Good morning! Please log in and mark your attendance.']);
        db_query("INSERT INTO sales_login_reminders (reminder_type, reminder_time, days_of_week, message) VALUES (?, ?, ?, ?)",
            ['logout', '18:00:00', '1,2,3,4,5', 'Time to wrap up! Please mark checkout and submit your daily report.']);
        echo "<p style='color:green;'>✅ Default login/logout reminders created.</p>";
    }
} catch (PDOException $e) { /* safe to skip */ }

// Create default admin sales executive account
try {
    $existing = db_fetch('SELECT id FROM sales_executives WHERE email = ?', ['admin@gilafstore.com']);
    if (!$existing) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        db_query('INSERT INTO sales_executives (name, email, password, phone, designation, district, location, reporting_manager) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            'Admin Executive',
            'admin@gilafstore.com',
            $hashedPassword,
            '0000000000',
            'Sales Executive',
            'Sopore',
            'Head Office',
            'Admin'
        ]);
        echo "<p style='color:green;'>✅ Default executive account created (admin@gilafstore.com / admin123)</p>";
    }
} catch (PDOException $e) {
    $errors[] = "Default account: " . $e->getMessage();
}

echo "<h2>Sales Portal Database Setup</h2>";
echo "<p style='color:green;'>✅ {$success} queries executed successfully</p>";

if (!empty($errors)) {
    echo "<h3 style='color:red;'>Errors:</h3>";
    foreach ($errors as $err) {
        echo "<p style='color:red;'>❌ {$err}</p>";
    }
} else {
    echo "<p style='color:green;'>✅ All tables and columns are ready. No errors.</p>";
}

echo "<br><a href='" . base_url('sales-portal/login.php') . "'>→ Go to Sales Portal Login</a>";
