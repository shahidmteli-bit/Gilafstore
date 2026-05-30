<?php
$pageTitle   = 'MRP Calculator — Gilaf Admin';
$adminPage   = 'mrp_calculator';

require_once '../includes/db_connect.php';

// Auto-create table if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mrp_calculations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        data LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }

// Handle AJAX / redirect requests BEFORE any HTML output
if (!empty($_POST['action']) && $_POST['action'] === 'save_calculation') {
    header('Content-Type: application/json');
    try {
        $calcData = json_encode($_POST);
        $name     = trim($_POST['calculation_name'] ?? 'Unnamed Calculation');
        if ($name === '') $name = 'Unnamed Calculation';
        $variant  = trim($_POST['product_weight_variant'] ?? '');

        // UPSERT: find existing record with same product name + variant and update it
        $existingId = null;
        $chk = $pdo->prepare("SELECT id, data FROM mrp_calculations WHERE name = ?");
        $chk->execute([$name]);
        foreach ($chk->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rd = json_decode($row['data'] ?? '{}', true) ?: [];
            if (trim($rd['product_weight_variant'] ?? '') === $variant) {
                $existingId = $row['id'];
                break;
            }
        }

        if ($existingId) {
            $stmt = $pdo->prepare("UPDATE mrp_calculations SET data = ?, created_at = NOW() WHERE id = ?");
            $stmt->execute([$calcData, $existingId]);
            echo json_encode(['success' => true, 'id' => $existingId, 'updated' => true]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO mrp_calculations (name, data, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $calcData]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'updated' => false]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (!empty($_GET['action'])) {
    if ($_GET['action'] === 'load_calculation' && !empty($_GET['id'])) {
        header('Content-Type: application/json');
        try {
            $stmt = $pdo->prepare("SELECT * FROM mrp_calculations WHERE id = ?");
            $stmt->execute([(int)$_GET['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    if ($_GET['action'] === 'delete_calculation' && !empty($_GET['id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM mrp_calculations WHERE id = ?");
            $stmt->execute([(int)$_GET['id']]);
        } catch (Exception $e) { /* ignore */ }
        header('Location: mrp_calculator.php');
        exit;
    }
}

// Handle push price to product
if (!empty($_POST['action']) && $_POST['action'] === 'push_price') {
    header('Content-Type: application/json');
    try {
        $wid      = (int)($_POST['weight_id'] ?? 0);
        $fields   = [];
        $params   = [];
        $allowed  = ['offline_mrp','price','wholesale_price','wholesale_gst','distributor_price','distributor_gst','franchise_price','franchise_gst','retail_price','retail_gst'];
        foreach ($allowed as $col) {
            if (isset($_POST[$col]) && $_POST[$col] !== '') {
                $fields[] = "`$col` = ?";
                $params[] = number_format((float)$_POST[$col], 2, '.', '');
            }
        }
        if (empty($fields) || $wid <= 0) throw new Exception('No fields or invalid weight ID');
        $params[] = $wid;
        $pdo->prepare("UPDATE product_weights SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        echo json_encode(['success' => true, 'rows' => 1]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Fetch products + weights for push-to-store selector
$productWeights = [];
try {
    $rows = $pdo->query("SELECT p.id AS pid, p.name AS pname, pw.id AS wid, pw.display_weight,
        pw.price AS website_price, pw.offline_mrp, pw.wholesale_price, pw.distributor_price,
        pw.franchise_price, pw.retail_price
        FROM products p
        INNER JOIN product_weights pw ON pw.product_id = p.id
        ORDER BY p.name, pw.display_weight")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $productWeights[$r['pid']]['name'] = $r['pname'];
        $productWeights[$r['pid']]['weights'][] = $r;
    }
} catch (Exception $e) { /* products table may not be accessible */ }

// Get saved calculations for display
$savedCalculations = [];
try {
    $savedCalculations = $pdo->query("SELECT * FROM mrp_calculations ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist yet — user needs to run setup
}

require_once '../includes/admin_header.php';
?>
<style>
        .mrp-container {
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .mrp-header {
            text-align: center;
            margin-bottom: 30px;
            color: #2C5530;
        }
        .cost-section {
            background: #f8f9fa;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid #2C5530;
        }
        .cost-section h3 {
            color: #2C5530;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .form-group input, .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2C5530;
            box-shadow: 0 0 0 2px rgba(44,85,48,0.1);
        }
        .result-section {
            background: linear-gradient(135deg, #2C5530, #3a6b3e);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin: 25px 0;
        }
        .result-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .result-item {
            text-align: center;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        .result-item .label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        .result-item .value {
            font-size: 24px;
            font-weight: bold;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #2C5530;
            color: white;
        }
        .btn-primary:hover {
            background: #234428;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .saved-calculations {
            margin-top: 30px;
        }
        .calc-item {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        .calc-item-info {
            flex: 1;
        }
        .calc-item-actions {
            display: flex;
            gap: 10px;
        }
        .icon {
            width: 20px;
            height: 20px;
            display: inline-block;
        }
        .result-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 0;
        }
        .result-tab-btn {
            padding: 12px 24px;
            border: 2px solid #2C5530;
            background: white;
            color: #2C5530;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            border-bottom: none;
        }
        .result-tab-btn.active, .result-tab-btn:hover {
            background: #2C5530;
            color: white;
        }
        .result-tab-content {
            border-radius: 0 8px 8px 8px;
        }
        .stage-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #dee2e6;
        }
        .stage-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        .stage-table tr td {
            padding: 7px 10px;
            border-bottom: 1px solid #eee;
        }
        .stage-table tr td:last-child {
            text-align: right;
            font-weight: 600;
            color: #2C5530;
            white-space: nowrap;
        }
        .stage-table tr.highlight td {
            background: #e8f5e9;
            color: #1a3a1d;
            font-weight: 700;
        }
        .stage-table tr:hover td { background: #f0f0f0; }
        .push-field label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            margin-bottom: 5px;
        }
        .push-curr {
            font-size: 11px;
            color: #888;
            font-weight: 400;
            margin-left: 4px;
        }
        @media (max-width: 900px) {
            #tab2 > div { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 768px) {
            .mrp-container { margin: 10px; padding: 15px; }
            .form-grid { grid-template-columns: 1fr; }
            .result-grid { grid-template-columns: 1fr; }
            .result-tab-btn { padding: 10px 14px; font-size: 12px; }
        }
    </style>
<div class="mrp-container">
        <div class="mrp-header">
            <h1>🧮 Advanced MRP Calculator</h1>
            <p>Comprehensive pricing with all business costs included</p>
        </div>

        <!-- Loaded Calculation Banner -->
        <div id="loadedCalcBanner" style="display:none; background:#fff3cd; border:2px solid #ffc107; border-radius:10px; padding:14px 18px; margin-bottom:18px; display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong style="color:#856404;">✏️ Editing Loaded Calculation: </strong>
                    <span id="loadedCalcName" style="color:#533f03; font-weight:600;"></span>
                    <div style="font-size:13px; color:#856404; margin-top:3px;">Modify any fields below, then click <strong>Calculate MRP</strong> to update results, or <strong>Save Calculation</strong> to save a copy.</div>
                </div>
                <button type="button" onclick="clearLoadedCalc()" style="background:#dc3545; color:#fff; border:none; border-radius:6px; padding:6px 12px; cursor:pointer; white-space:nowrap;">✕ Clear</button>
            </div>
        </div>

        <form id="mrpForm">
            <!-- Basic Product Info -->
            <div class="cost-section">
                <h3>📦 Product Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name</label>
                        <select name="product_name" id="product_name" onchange="onProductChange()" style="padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px;">
                            <option value="">— Select Product —</option>
                            <?php foreach ($productWeights as $pid => $prod): ?>
                                <option value="<?= htmlspecialchars($prod['name']) ?>" data-pid="<?= $pid ?>">
                                    <?= htmlspecialchars($prod['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product Size / Weight Variant</label>
                        <select id="product_weight_variant" name="product_weight_variant" onchange="onWeightVariantChange()" style="padding:10px; border:1px solid #2C5530; border-radius:6px; font-size:14px;">
                            <option value="" data-grams="0">— Select Size —</option>
                        </select>
                        <small id="variant_hint" style="color:#888; display:block; margin-top:4px;">Select product first</small>
                    </div>
                    <div class="form-group">
                        <label>Batch Size (Units)</label>
                        <input type="number" name="batch_size" id="batch_size" placeholder="1000" value="1000" oninput="autoCalcRawMaterial(); updatePerUnitHints()">
                    </div>
                    <div class="form-group">
                        <label>Unit of Measure</label>
                        <select name="unit" id="unit">
                            <option value="pieces">Pieces</option>
                            <option value="kg">Kilogram</option>
                            <option value="liters">Liters</option>
                            <option value="grams">Grams</option>
                            <option value="ml">Milliliters</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Manufacturing Costs -->
            <div class="cost-section">
                <h3>🏭 Manufacturing Costs</h3>
                <div style="background:#e8f4ea; border-left:4px solid #2C5530; padding:8px 14px; border-radius:0 6px 6px 0; margin-bottom:12px; font-size:13px; color:#2C5530;">
                    ✅ Enter Labor, Machine, Packaging, QC &amp; Overhead costs <strong>PER UNIT</strong>. The calculator multiplies by batch size automatically. Raw Materials total is auto-calculated from rate/kg.
                </div>
                <div class="form-grid">
                    <div class="form-group" style="grid-column:span 2;">
                        <label style="font-weight:700;">Raw Material Rate</label>
                        <div style="display:grid; grid-template-columns:1fr auto 1fr; gap:12px; align-items:end;">
                            <div>
                                <label style="font-size:12px; color:#555;">Rate per kg (₹/kg)</label>
                                <input type="number" id="rm_rate_per_kg" placeholder="e.g. 610" step="0.01"
                                    oninput="updateRmPerGram(); autoCalcRawMaterial()"
                                    style="width:100%; padding:9px; border:2px solid #2C5530; border-radius:6px; font-weight:600;">
                            </div>
                            <div style="text-align:center; padding-bottom:8px;">
                                <div style="font-size:12px; color:#888;">Per gram</div>
                                <div id="rm_per_gram" style="font-weight:700; color:#2C5530; font-size:16px;">—</div>
                            </div>
                            <div>
                                <label style="font-size:12px; color:#555;">Batch Total Auto-Calculated (₹) <span style="color:#17a2b8;">← editable</span></label>
                                <input type="number" name="raw_materials" id="raw_materials" placeholder="0" step="0.01"
                                    style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;">
                            </div>
                        </div>
                        <small id="rm_calc_hint" style="color:#2C5530; margin-top:5px; display:block; font-size:12px;"></small>
                    </div>
                    <div class="form-group">
                        <label>Labor Cost (₹) <small style="color:#2C5530;font-weight:600;">— per unit</small></label>
                        <input type="number" name="labor_cost" id="labor_cost" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Machine/Equipment Cost (₹) <small style="color:#2C5530;font-weight:600;">— per unit</small></label>
                        <input type="number" name="machine_cost" id="machine_cost" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Packaging Material (₹) <small style="color:#2C5530;font-weight:600;">— per unit</small></label>
                        <input type="number" name="packaging" id="packaging" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Quality Control (₹) <small style="color:#2C5530;font-weight:600;">— per unit</small></label>
                        <input type="number" name="quality_control" id="quality_control" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Factory Overhead (₹) <small style="color:#2C5530;font-weight:600;">— per unit</small></label>
                        <input type="number" name="factory_overhead" id="factory_overhead" placeholder="0" step="0.01">
                    </div>
                </div>
            </div>

            <!-- Transportation Costs -->
            <div class="cost-section">
                <h3>🚚 Transportation Costs</h3>
                <div style="background:#e8f0fe; border-left:4px solid #4a6cf7; padding:8px 14px; border-radius:0 6px 6px 0; margin-bottom:12px; font-size:13px; color:#3c50cf;">
                    ✅ Enter the cost <strong>PER UNIT</strong> for each transport expense (e.g., ₹5 per unit for local delivery). The calculator multiplies by batch size automatically.
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Local Transport (₹) <small style="color:#4a6cf7;font-weight:600;">— per unit</small></label>
                        <input type="number" name="local_transport" id="local_transport" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Long Distance Transport (₹) <small style="color:#4a6cf7;font-weight:600;">— per unit</small></label>
                        <input type="number" name="long_distance_transport" id="long_distance_transport" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Fuel Cost (₹) <small style="color:#4a6cf7;font-weight:600;">— per unit</small></label>
                        <input type="number" name="fuel_cost" id="fuel_cost" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Vehicle Maintenance (₹) <small style="color:#4a6cf7;font-weight:600;">— per unit</small></label>
                        <input type="number" name="vehicle_maintenance" id="vehicle_maintenance" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Driver Salary (₹) <small style="color:#4a6cf7;font-weight:600;">— per unit</small></label>
                        <input type="number" name="driver_salary" id="driver_salary" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Loading/Unloading (₹) <small style="color:#4a6cf7;font-weight:600;">— per unit</small></label>
                        <input type="number" name="loading_unloading" id="loading_unloading" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group" style="border:2px solid #17a2b8; border-radius:8px; padding:10px; background:#e8f7fa;">
                        <label style="color:#17a2b8;">🌐 Online Shipping / Delivery (₹) <small style="font-weight:400; color:#555;">(Website MRP only)</small></label>
                        <input type="number" name="online_shipping" id="online_shipping" placeholder="0" step="0.01" style="border-color:#17a2b8;">
                        <small style="color:#888; margin-top:4px; display:block;">Included in Website MRP only — not in Offline MRP</small>
                    </div>
                </div>
            </div>

            <!-- Marketing & Advertising Costs -->
            <div class="cost-section">
                <h3>📱 Marketing & Advertising Costs</h3>
                <div style="background:#fef4e4; border-left:4px solid #f59e0b; padding:8px 14px; border-radius:0 6px 6px 0; margin-bottom:12px; font-size:13px; color:#92400e;">
                    ✅ Enter the marketing cost <strong>PER UNIT</strong> (e.g., ₹5 per unit for ads). The calculator multiplies by batch size automatically.
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Social Media Marketing (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="social_media" id="social_media" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Google/Facebook Ads (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="google_ads" id="google_ads" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Website Development (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="website_cost" id="website_cost" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Internet/Domain Cost (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="internet_cost" id="internet_cost" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Print Advertising (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="print_ads" id="print_ads" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Influencer Marketing (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="influencer_marketing" id="influencer_marketing" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Events/Promotions (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="events_promotions" id="events_promotions" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Brand Ambassador (₹) <small style="color:#f59e0b;font-weight:600;">— per unit</small></label>
                        <input type="number" name="brand_ambassador" id="brand_ambassador" placeholder="0" step="0.01">
                    </div>
                </div>
            </div>

            <!-- Miscellaneous Costs -->
            <div class="cost-section">
                <h3>📋 Miscellaneous Costs</h3>
                <div style="background:#fce8f3; border-left:4px solid #db2777; padding:8px 14px; border-radius:0 6px 6px 0; margin-bottom:12px; font-size:13px; color:#9d174d;">
                    ✅ Enter overhead cost <strong>PER UNIT</strong> (e.g., ₹1 per unit for rent allocation). The calculator multiplies by batch size automatically.
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Office Rent (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="office_rent" id="office_rent" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Electricity/Water (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="utilities" id="utilities" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Phone/Communication (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="communication" id="communication" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Insurance (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="insurance" id="insurance" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Taxes & GST (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="taxes" id="taxes" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Bank Charges (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="bank_charges" id="bank_charges" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Legal/Professional Fees (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="legal_fees" id="legal_fees" placeholder="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Other Expenses (₹) <small style="color:#db2777;font-weight:600;">— per unit</small></label>
                        <input type="number" name="other_expenses" id="other_expenses" placeholder="0" step="0.01">
                    </div>
                </div>
            </div>

            <!-- Profit Settings -->
            <div class="cost-section">
                <h3>💰 Profit & GST Settings</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Profit Margin (%)</label>
                        <input type="number" name="profit_margin" id="profit_margin" placeholder="25" value="25" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Franchise Margin (%)</label>
                        <input type="number" name="franchise_margin" id="franchise_margin" placeholder="8" value="8" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Distributor Margin (%)</label>
                        <input type="number" name="distributor_margin" id="distributor_margin" placeholder="10" value="10" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Wholesaler Margin (%)</label>
                        <input type="number" name="wholesaler_margin" id="wholesaler_margin" placeholder="5" value="5" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Retail Margin (%)</label>
                        <input type="number" name="retailer_margin" id="retailer_margin" placeholder="20" value="20" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>GST % (on final MRP)</label>
                        <input type="number" name="gst_percent" id="gst_percent" placeholder="5" value="5" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Contingency (%)</label>
                        <input type="number" name="contingency" id="contingency" placeholder="5" value="5" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Offline MRP Rounding</label>
                        <select name="rounding_mode" id="rounding_mode">
                            <option value="nearest1">Nearest ₹1</option>
                            <option value="nearest5">Nearest ₹5</option>
                            <option value="nearest10">Nearest ₹10</option>
                            <option value="psychological">Psychological (e.g. ₹199)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Offline MRP Psych. Step (₹)</label>
                        <input type="number" name="psych_step" id="psych_step" placeholder="10" value="10" step="1">
                    </div>
                    <div class="form-group">
                        <label>Website MRP Rounding</label>
                        <select name="web_rounding_mode" id="web_rounding_mode">
                            <option value="nearest1">Nearest ₹1</option>
                            <option value="nearest5">Nearest ₹5</option>
                            <option value="nearest10">Nearest ₹10</option>
                            <option value="psychological" selected>Psychological (e.g. ₹199)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Website MRP Psych. Step (₹)</label>
                        <input type="number" name="web_psych_step" id="web_psych_step" placeholder="10" value="10" step="1">
                    </div>
                </div>
            </div>

            <!-- Scheme / Discount Settings -->
            <div class="cost-section">
                <h3>🎁 Scheme / Discount Settings (Optional)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Scheme Mode</label>
                        <select name="scheme_mode" id="scheme_mode">
                            <option value="none">None</option>
                            <option value="xplusy">X+Y Free</option>
                            <option value="cash">Cash Discount %</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Buy Qty (X)</label>
                        <input type="number" name="buy_qty" id="buy_qty" placeholder="12" value="12" step="1">
                    </div>
                    <div class="form-group">
                        <label>Free Qty (Y)</label>
                        <input type="number" name="free_qty" id="free_qty" placeholder="1" value="1" step="1">
                    </div>
                    <div class="form-group">
                        <label>Cash Discount (%)</label>
                        <input type="number" name="cash_discount" id="cash_discount" placeholder="0" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Target Qty</label>
                        <input type="number" name="target_qty" id="target_qty" placeholder="100" step="1">
                    </div>
                    <div class="form-group">
                        <label>Achieved Qty</label>
                        <input type="number" name="achieved_qty" id="achieved_qty" placeholder="100" step="1">
                    </div>
                    <div class="form-group">
                        <label>Target Bonus (%)</label>
                        <input type="number" name="target_bonus" id="target_bonus" placeholder="0" step="0.1">
                    </div>
                    <div class="form-group">
                        <label>Scheme Applies On</label>
                        <select name="scheme_applies" id="scheme_applies">
                            <option value="company">Company → Distributor</option>
                            <option value="distributor">Distributor → Wholesaler</option>
                            <option value="wholesaler">Wholesaler → Retailer</option>
                            <option value="retailer">Retailer → Consumer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Scheme Impact Mode</label>
                        <select name="scheme_impact" id="scheme_impact">
                            <option value="passed_down">Passed Down to Market</option>
                            <option value="absorbed">Absorbed by Bearer Only</option>
                        </select>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <button type="button" onclick="calculateMRP()" class="btn btn-primary">Calculate MRP</button>
                <button type="button" onclick="resetForm()" class="btn btn-secondary">Reset</button>
                <button type="button" onclick="saveCalculation()" class="btn btn-success">Save Calculation</button>
                <button type="button" onclick="exportResults()" class="btn btn-primary">Export Results</button>
            </div>
        </form>

        <!-- Results Section with Tabs -->
        <div id="resultsSection" style="display: none; margin: 25px 0;">
            <!-- Tab Buttons -->
            <div class="result-tabs">
                <button class="result-tab-btn active" onclick="switchTab('tab1')">📊 Cost Summary</button>
                <button class="result-tab-btn" onclick="switchTab('tab2')">🏷️ Stage Prices & Earnings</button>
                <button class="result-tab-btn" onclick="switchTab('tab3')">💡 Smart Suggestions</button>
            </div>

            <!-- Tab 1: Cost Summary -->
            <div id="tab1" class="result-tab-content active result-section">
                <h3>📊 Cost Summary</h3>
                <div class="result-grid">
                    <div class="result-item"><div class="label">Manufacturing Cost</div><div class="value" id="totalManufacturing">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">📦 BATCH TOTAL</div></div>
                    <div class="result-item"><div class="label">Transportation Cost</div><div class="value" id="totalTransportation">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">📦 BATCH TOTAL</div></div>
                    <div class="result-item"><div class="label">Marketing Cost</div><div class="value" id="totalMarketing">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">📦 BATCH TOTAL</div></div>
                    <div class="result-item"><div class="label">Miscellaneous Cost</div><div class="value" id="totalMiscellaneous">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">📦 BATCH TOTAL</div></div>
                    <div class="result-item" style="border:2px solid #a3d9a5;"><div class="label">Total Cost Per Unit</div><div class="value" id="costPerUnit">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">✅ PER UNIT</div></div>
                    <div class="result-item"><div class="label">GST Amount</div><div class="value" id="gstAmount">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">✅ PER UNIT</div></div>
                    <div class="result-item"><div class="label">Final MRP (Incl. GST)</div><div class="value" id="finalMRP">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">✅ PER UNIT</div></div>
                    <div class="result-item"><div class="label">Offline MRP (Rounded)</div><div class="value" id="finalMRPRounded">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">✅ PER UNIT</div></div>
                    <div class="result-item"><div class="label">MRP per Unit</div><div class="value" id="mrpPerUnit">₹0</div><div style="font-size:11px;color:#a5c8a5;margin-top:3px;letter-spacing:.3px;">✅ PER UNIT</div></div>
                    <div class="result-item" style="background:rgba(23,162,184,0.15); border:2px solid #17a2b8;">
                        <div class="label" style="color:#17a2b8;">🌐 Website MRP (Psychological)</div>
                        <div class="value" id="websiteMRP" style="color:#17a2b8;">₹0</div>
                        <div style="font-size:11px;color:#5bc0de;margin-top:3px;letter-spacing:.3px;">✅ PER UNIT</div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Stage Prices & Earnings -->
            <div id="tab2" class="result-tab-content" style="display:none;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

                    <!-- Before Scheme -->
                    <div class="stage-card">
                        <h4 style="color:#2C5530; border-bottom:2px solid #2C5530; padding-bottom:8px; margin-bottom:15px;">🏷️ Outputs (MRP &amp; Stage Prices)</h4>
                        <table class="stage-table">
                            <tr><td>After Company (Excl. GST)</td><td id="s_afterCompany">₹0</td></tr>
                            <tr><td>After Franchise (Excl. GST)</td><td id="s_afterFranchise">₹0</td></tr>
                            <tr><td>After Distributor (Excl. GST)</td><td id="s_afterDistributor">₹0</td></tr>
                            <tr><td>After Wholesaler (Excl. GST)</td><td id="s_afterWholesaler">₹0</td></tr>
                            <tr><td>After Retail (Excl. GST)</td><td id="s_afterRetailer">₹0</td></tr>
                            <tr class="highlight"><td>GST Amount</td><td id="s_gstAmt">₹0</td></tr>
                            <tr class="highlight"><td>Final MRP (Incl. GST)</td><td id="s_finalMRPIncl">₹0</td></tr>
                            <tr class="highlight"><td>Final MRP (Rounded)</td><td id="s_finalMRPRnd">₹0</td></tr>
                            <tr><td>MRP per Unit (Rounded)</td><td id="s_mrpUnit">₹0</td></tr>
                            <tr style="border-top:1px solid #ddd;"><td>Company Profit</td><td id="s_compProfit">₹0</td></tr>
                            <tr><td>Franchise Earning</td><td id="s_franchiseEarning">₹0</td></tr>
                            <tr><td>Distributor Earning</td><td id="s_distEarning">₹0</td></tr>
                            <tr><td>Wholesaler Earning</td><td id="s_wholeEarning">₹0</td></tr>
                            <tr><td>Retail Earning</td><td id="s_retailEarning">₹0</td></tr>
                        </table>
                    </div>

                    <!-- After Scheme -->
                    <div class="stage-card">
                        <h4 style="color:#8B0000; border-bottom:2px solid #8B0000; padding-bottom:8px; margin-bottom:15px;">🎁 Outputs (After Scheme / Discount)</h4>
                        <table class="stage-table">
                            <tr><td>Scheme Factor</td><td id="s_schemeLabel">—</td></tr>
                            <tr><td>Eff. After Company</td><td id="s_effCompany">₹0</td></tr>
                            <tr><td>Eff. After Franchise</td><td id="s_effFranchise">₹0</td></tr>
                            <tr><td>Eff. After Distributor</td><td id="s_effDist">₹0</td></tr>
                            <tr><td>Eff. After Wholesaler</td><td id="s_effWhole">₹0</td></tr>
                            <tr><td>Eff. After Retail</td><td id="s_effRetailer">₹0</td></tr>
                            <tr style="border-top:1px solid #ddd;"><td>Company Profit After Scheme</td><td id="s_compScheme">₹0</td></tr>
                            <tr><td>Franchise Earning After Scheme</td><td id="s_franchiseScheme">₹0</td></tr>
                            <tr><td>Distributor Earning After Scheme</td><td id="s_distScheme">₹0</td></tr>
                            <tr><td>Wholesaler Earning After Scheme</td><td id="s_wholeScheme">₹0</td></tr>
                            <tr><td>Retail Earning After Scheme</td><td id="s_retailScheme">₹0</td></tr>
                            <tr class="highlight" style="border-top:2px solid #2C5530;"><td><strong>Loss / Profit Status</strong></td><td id="s_profitStatus" style="font-weight:bold;">—</td></tr>
                            <tr><td>Max Cash Discount %</td><td id="s_maxCashDisc">—</td></tr>
                            <tr><td>Max Free Qty Y (for X)</td><td id="s_maxFreeQty">—</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Smart Suggestions -->
            <div id="tab3" class="result-tab-content" style="display:none; padding:25px; background:#fff; border:2px solid #2C5530; border-radius:0 8px 8px 8px;">
                <h3 style="color:#2C5530; margin-bottom:20px;">💡 Smart Business Suggestions</h3>
                <div id="suggestionsContainer" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;"></div>
            </div>
        </div>

        <!-- Push Price to Store Panel -->
        <div id="pushPricePanel" style="display:none; margin:30px 0; background:#fff; border:2px solid #2C5530; border-radius:12px; overflow:hidden;">
            <div style="background:#2C5530; color:#fff; padding:15px 20px; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-upload" style="font-size:18px;"></i>
                <div>
                    <div style="font-weight:700; font-size:16px;">Push Calculated Prices to Store</div>
                    <div style="font-size:12px; opacity:0.85;">Select a product variant and push the MRP/prices directly to your website & sales portal</div>
                </div>
            </div>
            <div style="padding:20px;">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                    <!-- Product selector -->
                    <div>
                        <label style="font-weight:600; display:block; margin-bottom:6px;">Select Product</label>
                        <select id="push_product_id" onchange="loadWeights()" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px;">
                            <option value="">— Choose Product —</option>
                            <?php foreach ($productWeights as $pid => $prod): ?>
                                <option value="<?= $pid ?>"><?= htmlspecialchars($prod['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Weight selector -->
                    <div>
                        <label style="font-weight:600; display:block; margin-bottom:6px;">Select Weight / Variant</label>
                        <select id="push_weight_id" onchange="fillCurrentPrices()" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px;">
                            <option value="">— Choose Variant —</option>
                        </select>
                    </div>
                </div>

                <!-- Price fields grid -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin-bottom:20px;" id="pushPriceFields">
                    <div class="push-field">
                        <label>Offline MRP (₹) <span class="push-curr" id="curr_offline_mrp"></span></label>
                        <input type="number" id="push_offline_mrp" placeholder="₹ from calculator" step="0.01" style="width:100%; padding:9px; border:2px solid #2C5530; border-radius:6px; font-weight:600;">
                    </div>
                    <div class="push-field">
                        <label>Website Price (₹) <span class="push-curr" id="curr_website_price"></span></label>
                        <input type="number" id="push_website_price" placeholder="₹ from calculator" step="0.01" style="width:100%; padding:9px; border:2px solid #17a2b8; border-radius:6px; font-weight:600;">
                    </div>
                    <div class="push-field">
                        <label>Wholesale Price (₹) <span class="push-curr" id="curr_wholesale_price"></span></label>
                        <input type="number" id="push_wholesale_price" placeholder="₹" step="0.01" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;">
                    </div>
                    <div class="push-field">
                        <label>Distributor Price (₹) <span class="push-curr" id="curr_distributor_price"></span></label>
                        <input type="number" id="push_distributor_price" placeholder="₹" step="0.01" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;">
                    </div>
                    <div class="push-field">
                        <label>Franchise Price (₹) <span class="push-curr" id="curr_franchise_price"></span></label>
                        <input type="number" id="push_franchise_price" placeholder="₹" step="0.01" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;">
                    </div>
                    <div class="push-field">
                        <label>Retail Price (₹) <span class="push-curr" id="curr_retail_price"></span></label>
                        <input type="number" id="push_retail_price" placeholder="₹" step="0.01" style="width:100%; padding:9px; border:1px solid #ddd; border-radius:6px;">
                    </div>
                    <div class="push-field" style="border:2px solid #e67e22; border-radius:8px; padding:10px; background:#fff8f0;">
                        <label style="color:#e67e22;">GST % (applied to all channels) <span class="push-curr" id="curr_gst"></span></label>
                        <input type="number" id="push_gst" placeholder="e.g. 18" step="0.01" style="width:100%; padding:9px; border:2px solid #e67e22; border-radius:6px; font-weight:600;">
                        <small style="color:#888; display:block; margin-top:4px;">Pushes to wholesale_gst, distributor_gst, franchise_gst, retail_gst</small>
                    </div>
                </div>

                <div style="background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:12px; margin-bottom:16px; font-size:13px; color:#795548;">
                    <strong>⚠ Note:</strong> Only filled fields will be updated. Leave blank to keep current value. MRP &amp; Website Price auto-filled from calculator.
                </div>

                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <button onclick="autofillFromCalculator()" class="btn btn-secondary" style="background:#6c757d;">⚡ Auto-fill from Calculator</button>
                    <button onclick="pushPriceToStore()" class="btn btn-primary" style="background:#2C5530; font-size:15px; padding:12px 28px;">
                        <i class="fas fa-upload"></i> Push Prices to Store
                    </button>
                    <div id="pushStatus" style="display:none; align-self:center; font-weight:600; padding:8px 16px; border-radius:6px;"></div>
                </div>
            </div>
        </div>

        <!-- Saved Calculations -->
        <div class="saved-calculations">
            <h3>💾 Saved Calculations</h3>
            <?php if (empty($savedCalculations)): ?>
                <p>No saved calculations yet.</p>
            <?php else: ?>
                <?php foreach ($savedCalculations as $calc):
                    $cd = json_decode($calc['data'] ?? '{}', true) ?: [];
                    $batchSize = $cd['batch_size'] ?? null;
                    $unit      = $cd['unit']       ?? '';
                    $variant   = $cd['product_weight_variant'] ?? null;
                    $mrp       = $cd['final_mrp_rounded'] ?? ($cd['cost_per_unit'] ?? null);
                ?>
                    <div class="calc-item">
                        <div class="calc-item-info">
                            <strong><?php echo htmlspecialchars($calc['name']); ?></strong><?php if ($variant): ?> <span style="background:#d1ecf1;color:#0c5460;padding:1px 8px;border-radius:10px;font-size:12px;font-weight:700;"><?php echo htmlspecialchars($variant); ?></span><?php endif; ?><br>
                            <small>Created: <?php echo date('d M Y, h:i A', strtotime($calc['created_at'])); ?></small>
                            <?php if ($batchSize): ?>
                                <small style="display:inline-block;margin-top:3px;background:#e8f4ea;color:#2C5530;padding:2px 8px;border-radius:10px;font-weight:600;">
                                    📦 Batch: <?php echo htmlspecialchars($batchSize); ?> <?php echo htmlspecialchars($unit); ?>
                                </small>
                            <?php endif; ?>
                            <?php if ($mrp): ?>
                                <small style="display:inline-block;margin-top:3px;margin-left:4px;background:#fff3cd;color:#856404;padding:2px 8px;border-radius:10px;font-weight:600;">
                                    MRP: <?php echo htmlspecialchars($mrp); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="calc-item-actions">
                            <button onclick="loadCalculation(<?php echo $calc['id']; ?>)" class="btn btn-secondary">Load</button>
                            <button onclick="deleteCalculation(<?php echo $calc['id']; ?>)" class="btn btn-danger">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // PHP product weights data for push-to-store
        const _productWeights = <?= json_encode($productWeights, JSON_HEX_TAG) ?>;
    </script>
    <script>
        // ── helpers ──────────────────────────────────────────────────────────
        const fmt  = v => '₹' + v.toFixed(2);
        const fmtP = v => v.toFixed(4);
        const g    = id => parseFloat(document.getElementById(id).value) || 0;

        function roundMRP(value, mode, step) {
            if (mode === 'nearest1')  return Math.round(value);
            if (mode === 'nearest5')  return Math.round(value / 5) * 5;
            if (mode === 'nearest10') return Math.round(value / 10) * 10;
            if (mode === 'psychological') {
                const s = step > 0 ? step : 10;
                const base = Math.ceil(value / s) * s;
                return base - 1;           // e.g. 199, 299 …
            }
            return Math.round(value);
        }

        function set(id, val) {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        }

        // ── global store for last calculation (used by push-to-store) ────────
        let _lastCalc = {};

        // ── main calculation ─────────────────────────────────────────────────
        function calculateMRP() {
            const form     = document.getElementById('mrpForm');
            const fd       = new FormData(form);
            const batch    = parseFloat(fd.get('batch_size')) || 1;

            // ── costs ──
            // raw_materials = batch total (auto-calc from rate/kg); others = per-unit (multiply by batch)
            const rawMaterials = parseFloat(fd.get('raw_materials')) || 0;
            const mfgPerUnit = ['labor_cost','machine_cost','packaging','quality_control','factory_overhead']
                .reduce((s, f) => s + (parseFloat(fd.get(f)) || 0), 0);
            const manufacturing = rawMaterials + (mfgPerUnit * batch);
            // Transportation inputs are PER UNIT — multiply by batch so cpuBase (÷batch) gives per-unit correctly
            const transportationPerUnit = ['local_transport','long_distance_transport','fuel_cost','vehicle_maintenance','driver_salary','loading_unloading']
                .reduce((s, f) => s + (parseFloat(fd.get(f)) || 0), 0);
            const transportation = transportationPerUnit * batch;
            const onlineShipping = parseFloat(fd.get('online_shipping')) || 0;
            // Marketing & Miscellaneous inputs are PER UNIT — multiply by batch
            const marketingPerUnit = ['social_media','google_ads','website_cost','internet_cost','print_ads','influencer_marketing','events_promotions','brand_ambassador']
                .reduce((s, f) => s + (parseFloat(fd.get(f)) || 0), 0);
            const marketing = marketingPerUnit * batch;
            const miscPerUnit = ['office_rent','utilities','communication','insurance','taxes','bank_charges','legal_fees','other_expenses']
                .reduce((s, f) => s + (parseFloat(fd.get(f)) || 0), 0);
            const miscellaneous = miscPerUnit * batch;

            // Offline: excludes online shipping | Website: includes online shipping
            const totalCost        = manufacturing + transportation + marketing + miscellaneous;
            const totalCostWebsite = totalCost + onlineShipping;
            const cpuBase          = totalCost        / batch;
            const cpuBaseWeb       = totalCostWebsite / batch;

            // ── margin inputs ──
            const contingency      = parseFloat(fd.get('contingency'))        || 0;
            const profitPct        = parseFloat(fd.get('profit_margin'))       || 0;
            const franchisePct     = parseFloat(fd.get('franchise_margin'))    || 0;
            const distributorPct   = parseFloat(fd.get('distributor_margin'))  || 0;
            const wholesalerPct    = parseFloat(fd.get('wholesaler_margin'))   || 0;
            const retailerPct      = parseFloat(fd.get('retailer_margin'))     || 0;
            const gstPct           = parseFloat(fd.get('gst_percent'))        || 0;
            const roundingMode     = fd.get('rounding_mode')     || 'nearest1';
            const psychStep        = parseFloat(fd.get('psych_step'))          || 10;
            const webRoundingMode  = fd.get('web_rounding_mode') || 'psychological';
            const webPsychStep     = parseFloat(fd.get('web_psych_step'))      || 10;

            // ── stage prices — OFFLINE chain: CPU→Franchise→Distributor→Wholesaler→Retail ──
            const cpu            = cpuBase    * (1 + contingency   / 100);
            const afterCompany   = cpu           * (1 + profitPct      / 100);
            const afterFranchise = afterCompany  * (1 + franchisePct   / 100);
            const afterDist      = afterFranchise* (1 + distributorPct / 100);
            const afterWhole     = afterDist     * (1 + wholesalerPct  / 100);
            const afterRetailer  = afterWhole    * (1 + retailerPct    / 100);

            // ── stage prices — WEBSITE (incl online shipping) ──
            const cpuWeb             = cpuBaseWeb    * (1 + contingency   / 100);
            const afterCompanyWeb    = cpuWeb           * (1 + profitPct      / 100);
            const afterFranchiseWeb  = afterCompanyWeb  * (1 + franchisePct   / 100);
            const afterDistWeb       = afterFranchiseWeb* (1 + distributorPct / 100);
            const afterWholeWeb      = afterDistWeb     * (1 + wholesalerPct  / 100);
            const afterRetailerWeb   = afterWholeWeb    * (1 + retailerPct    / 100);

            // ── GST & final MRP ──
            const gstAmt         = afterRetailer    * (gstPct / 100);
            const gstAmtWeb      = afterRetailerWeb * (gstPct / 100);
            const finalMRPIncl   = afterRetailer    + gstAmt;
            const finalMRPInclWeb= afterRetailerWeb + gstAmtWeb;
            const finalMRPRnd    = roundMRP(finalMRPIncl,    roundingMode,    psychStep);
            const websiteMRPRnd  = roundMRP(finalMRPInclWeb, webRoundingMode, webPsychStep);
            const mrpPerUnit     = finalMRPRnd;

            // ── earnings before scheme ──
            const companyProfit    = afterCompany   - cpu;
            const franchiseEarning = afterFranchise - afterCompany;
            const distEarning      = afterDist      - afterFranchise;
            const wholeEarning     = afterWhole     - afterDist;
            const retailEarning    = afterRetailer  - afterWhole;

            // ── scheme factor ──
            const schemeMode   = fd.get('scheme_mode')   || 'none';
            const schemeImpact = fd.get('scheme_impact') || 'passed_down';
            const buyQty       = parseFloat(fd.get('buy_qty'))      || 1;
            const freeQty      = parseFloat(fd.get('free_qty'))      || 0;
            const cashDisc     = parseFloat(fd.get('cash_discount')) || 0;

            let schemeFactor = 1;
            let schemeLabel  = '—';
            if (schemeMode === 'xplusy' && freeQty > 0) {
                schemeFactor = buyQty / (buyQty + freeQty);
                schemeLabel  = fmtP(schemeFactor) + ' (X+Y)';
            } else if (schemeMode === 'cash' && cashDisc > 0) {
                schemeFactor = 1 - (cashDisc / 100);
                schemeLabel  = fmtP(schemeFactor) + ' (Cash)';
            }

            // ── effective prices after scheme — respects impact mode ──
            // "Absorbed by Bearer Only": only bearer's sell price is reduced; downstream unchanged
            // "Passed Down to Market":   scheme factor cascades through all downstream stages
            let effCompany, effFranchise, effDist, effWhole, effRetailer;
            if (schemeImpact === 'absorbed') {
                effCompany   = afterCompany   * schemeFactor;
                effFranchise = afterFranchise;
                effDist      = afterDist;
                effWhole     = afterWhole;
                effRetailer  = afterRetailer;
            } else {
                effCompany   = afterCompany   * schemeFactor;
                effFranchise = afterFranchise * schemeFactor;
                effDist      = afterDist      * schemeFactor;
                effWhole     = afterWhole     * schemeFactor;
                effRetailer  = afterRetailer  * schemeFactor;
            }

            const compProfScheme     = effCompany   - cpu;
            const franchiseScheme    = effFranchise - effCompany;
            const distScheme         = effDist      - effFranchise;
            const wholeScheme        = effWhole     - effDist;
            const retailScheme       = effRetailer  - effWhole;

            // profit/loss status
            const isProfit = compProfScheme >= 0;

            // max cash discount (break-even: effCompany == cpu → cashDisc = (1 - cpu/afterCompany)*100)
            const maxCashDisc = afterCompany > 0 ? ((1 - cpu / afterCompany) * 100).toFixed(2) : '0.00';
            // max free qty for breakeven (schemeFactor = cpu/afterCompany → buyQty/(buyQty+Y) = cpu/afterCompany)
            const maxFreeQty = afterCompany > 0 && cpu < afterCompany
                ? Math.floor(buyQty * (afterCompany - cpu) / cpu)
                : 0;

            // ── update Tab 1 ──
            set('totalManufacturing', fmt(manufacturing));
            set('totalTransportation', fmt(transportation));
            set('totalMarketing', fmt(marketing));
            set('totalMiscellaneous', fmt(miscellaneous));
            set('costPerUnit', fmt(cpu));
            set('gstAmount', fmt(gstAmt));
            set('finalMRP', fmt(finalMRPIncl));
            set('finalMRPRounded', '₹' + finalMRPRnd.toFixed(2));
            set('mrpPerUnit', '₹' + mrpPerUnit.toFixed(2));
            set('websiteMRP', '₹' + websiteMRPRnd.toFixed(2));

            // ── update Tab 2 — before scheme ──
            set('s_afterCompany',    fmt(afterCompany));
            set('s_afterFranchise',  fmt(afterFranchise));
            set('s_afterDistributor',fmt(afterDist));
            set('s_afterWholesaler', fmt(afterWhole));
            set('s_afterRetailer',   fmt(afterRetailer));
            set('s_gstAmt',          fmt(gstAmt));
            set('s_finalMRPIncl',    fmt(finalMRPIncl));
            set('s_finalMRPRnd',     '₹' + finalMRPRnd.toFixed(2));
            set('s_mrpUnit',         '₹' + mrpPerUnit.toFixed(2));
            set('s_compProfit',      fmt(companyProfit));
            set('s_franchiseEarning',fmt(franchiseEarning));
            set('s_distEarning',     fmt(distEarning));
            set('s_wholeEarning',    fmt(wholeEarning));
            set('s_retailEarning',   fmt(retailEarning));

            // ── update Tab 2 — after scheme ──
            set('s_schemeLabel',     schemeLabel);
            set('s_effCompany',      fmt(effCompany));
            set('s_effFranchise',    fmt(effFranchise));
            set('s_effDist',         fmt(effDist));
            set('s_effWhole',        fmt(effWhole));
            set('s_effRetailer',     fmt(effRetailer));
            set('s_compScheme',      fmt(compProfScheme));
            set('s_franchiseScheme', fmt(franchiseScheme));
            set('s_distScheme',      fmt(distScheme));
            set('s_wholeScheme',     fmt(wholeScheme));
            set('s_retailScheme',    fmt(retailScheme));

            const statusEl = document.getElementById('s_profitStatus');
            statusEl.textContent = isProfit ? 'PROFIT ' : 'LOSS ';
            statusEl.textContent = isProfit ? 'PROFIT ✅' : 'LOSS ❌';
            statusEl.style.color = isProfit ? '#2C5530' : '#dc3545';

            set('s_maxCashDisc', maxCashDisc + '%');
            set('s_maxFreeQty',  maxFreeQty + ' units (for ' + buyQty + ' buy)');

            // ── save to global for push-to-store autofill ──
            // Push mapping (sequential chain — each tier sees the price their supplier charges them):
            // franchise_price   = what franchise pays company        = afterCompany
            // distributor_price = what distributor pays franchise    = afterFranchise
            // wholesale_price   = what wholesaler pays distributor   = afterDist
            // retail_price      = what retailer pays wholesaler      = afterWhole
            _lastCalc = {
                offline_mrp:        finalMRPRnd,
                website_price:      websiteMRPRnd,
                franchise_price:    afterCompany,
                distributor_price:  afterFranchise,
                wholesale_price:    afterDist,
                retail_price:       afterWhole,
                gst_pct:            gstPct
            };

            // show push panel
            document.getElementById('pushPricePanel').style.display = 'block';

            // generate smart suggestions
            generateSuggestions({
                cpu, afterCompany, afterFranchise, afterDist, afterWhole, afterRetailer,
                finalMRPRnd, gstPct, profitPct, franchisePct, distributorPct, wholesalerPct, retailerPct,
                manufacturing, transportation, marketing, miscellaneous, totalCost,
                schemeMode, buyQty, freeQty, cashDisc, schemeFactor,
                companyProfit, distEarning, wholeEarning, retailEarning,
                compProfScheme, maxCashDisc, maxFreeQty, batch
            });

            // show results
            document.getElementById('resultsSection').style.display = 'block';
            document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });
        }

        // ── smart suggestions ────────────────────────────────────────────────
        function suggCard(color, icon, title, rows) {
            const rowsHtml = rows.map(r =>
                `<tr><td style="padding:6px 8px;color:#555;">${r[0]}</td>
                 <td style="padding:6px 8px;text-align:right;font-weight:600;color:${r[2]||'#2C5530'};">${r[1]}</td></tr>`
            ).join('');
            return `<div style="background:#fff;border:2px solid ${color};border-radius:10px;overflow:hidden;">
                <div style="background:${color};color:#fff;padding:12px 16px;font-weight:700;font-size:15px;">${icon} ${title}</div>
                <table style="width:100%;border-collapse:collapse;">${rowsHtml}</table>
            </div>`;
        }

        function badge(label, color) {
            return `<span style="display:inline-block;padding:2px 10px;border-radius:12px;background:${color};color:#fff;font-size:12px;">${label}</span>`;
        }

        function generateSuggestions(d) {
            const container = document.getElementById('suggestionsContainer');
            const cards = [];

            // 1 ── Profit health
            const profitPct = d.afterCompany > 0 ? (d.companyProfit / d.cpu * 100) : 0;
            let profitHealth, profitColor;
            if (profitPct >= 30)      { profitHealth = badge('Excellent', '#28a745'); profitColor = '#28a745'; }
            else if (profitPct >= 20) { profitHealth = badge('Good', '#2C5530');      profitColor = '#2C5530'; }
            else if (profitPct >= 10) { profitHealth = badge('Moderate', '#fd7e14');  profitColor = '#fd7e14'; }
            else                      { profitHealth = badge('Low — Review Costs', '#dc3545'); profitColor = '#dc3545'; }
            cards.push(suggCard('#2C5530', '📈', 'Profit Health Check', [
                ['Actual Company Margin', profitPct.toFixed(1) + '%', profitColor],
                ['Status', profitHealth, '#000'],
                ['Min Viable Margin', '15%', '#888'],
                ['Safe Margin Target', '25–35%', '#888'],
                ['Profit Per Unit', '₹' + d.companyProfit.toFixed(2), profitColor],
                ['Revenue @ Batch ' + d.batch, '₹' + (d.afterCompany * d.batch).toFixed(2), '#2C5530'],
            ]));

            // 2 ── Scheme recommendation
            const bestFreeQty = d.afterCompany > 0 && d.cpu < d.afterCompany
                ? Math.floor(d.buyQty * (d.afterCompany - d.cpu) / d.cpu * 0.6)   // 60% of max = safe scheme
                : 0;
            const safeCashDisc = d.afterCompany > 0
                ? ((1 - d.cpu / d.afterCompany) * 100 * 0.6).toFixed(2)
                : '0.00';
            cards.push(suggCard('#6f42c1', '🎁', 'Recommended Scheme', [
                ['Best Scheme Type', d.profitPct >= 20 ? 'X+Y Free works well' : 'Cash Discount safer', '#6f42c1'],
                ['Safe X+Y Scheme', `Buy ${d.buyQty} Get ${bestFreeQty} Free`, '#6f42c1'],
                ['Safe Cash Discount', safeCashDisc + '%', '#6f42c1'],
                ['Max Cash Disc (break-even)', d.maxCashDisc + '%', '#dc3545'],
                ['Max Free Qty (break-even)', d.maxFreeQty + ' units', '#dc3545'],
                ['Current Scheme Factor', d.schemeFactor !== 1 ? d.schemeFactor.toFixed(4) : '— (none set)', '#888'],
            ]));

            // 3 ── Cost breakdown advice
            const totalCost = d.manufacturing + d.transportation + d.marketing + d.miscellaneous;
            const mfgPct  = totalCost > 0 ? (d.manufacturing / totalCost * 100).toFixed(1) : 0;
            const trPct   = totalCost > 0 ? (d.transportation / totalCost * 100).toFixed(1) : 0;
            const mktPct  = totalCost > 0 ? (d.marketing / totalCost * 100).toFixed(1) : 0;
            const miscPct = totalCost > 0 ? (d.miscellaneous / totalCost * 100).toFixed(1) : 0;
            const mktFlag = parseFloat(mktPct) > 25 ? badge('⚠ High', '#fd7e14') : badge('OK', '#28a745');
            cards.push(suggCard('#17a2b8', '🏭', 'Cost Breakdown Advice', [
                ['Manufacturing', mfgPct + '%', '#17a2b8'],
                ['Transportation', trPct + '%', '#17a2b8'],
                ['Marketing', mktPct + '% ' + mktFlag, '#000'],
                ['Miscellaneous', miscPct + '%', '#17a2b8'],
                ['Tip', parseFloat(mktPct) > 25 ? 'Marketing > 25% — audit ad spend' : 'Cost mix looks balanced', parseFloat(mktPct) > 25 ? '#dc3545' : '#28a745'],
                ['Total Cost / Unit', '₹' + d.cpu.toFixed(2), '#17a2b8'],
            ]));

            // 4 ── MRP & pricing strategy
            const mrpMultiple = d.cpu > 0 ? (d.finalMRPRnd / d.cpu).toFixed(2) : '—';
            let pricingTip;
            if (d.finalMRPRnd < 100)       pricingTip = 'Price < ₹100 — mass market appeal, focus on volume';
            else if (d.finalMRPRnd < 500)  pricingTip = 'Price ₹100–500 — sweet spot for FMCG/consumer goods';
            else if (d.finalMRPRnd < 2000) pricingTip = 'Price ₹500–2000 — consider EMI or bundle offers';
            else                            pricingTip = 'Premium price — stress quality & brand story';
            cards.push(suggCard('#e83e8c', '🏷️', 'MRP & Pricing Strategy', [
                ['Final MRP (Rounded)', '₹' + d.finalMRPRnd.toFixed(2), '#e83e8c'],
                ['MRP = Cost × ', mrpMultiple + 'x', '#e83e8c'],
                ['GST on MRP', d.gstPct + '%', '#888'],
                ['Psychological Price', '₹' + (Math.ceil(d.finalMRPRnd / 10) * 10 - 1).toFixed(0), '#6f42c1'],
                ['Pricing Tip', pricingTip, '#e83e8c'],
                ['E-commerce MRP', '₹' + (d.finalMRPRnd * 1.05).toFixed(0) + ' (incl. platform fee ~5%)', '#888'],
            ]));

            // 5 ── Channel margin recommendations
            const distOk   = d.distributorPct >= 8  && d.distributorPct <= 15;
            const wholeOk  = d.wholesalerPct  >= 4  && d.wholesalerPct  <= 8;
            const retailOk = d.retailerPct    >= 15 && d.retailerPct    <= 30;
            cards.push(suggCard('#fd7e14', '🔗', 'Channel Margin Advice', [
                ['Distributor Margin', d.distributorPct.toFixed(1) + '% ' + (distOk ? badge('Good','#28a745') : badge('Adjust','#fd7e14')), '#000'],
                ['Wholesaler Margin', d.wholesalerPct.toFixed(1) + '% ' + (wholeOk ? badge('Good','#28a745') : badge('Adjust','#fd7e14')), '#000'],
                ['Retailer Margin', d.retailerPct.toFixed(1) + '% ' + (retailOk ? badge('Good','#28a745') : badge('Adjust','#fd7e14')), '#000'],
                ['Recommended Dist', '8–15%', '#888'],
                ['Recommended Whole', '4–8%', '#888'],
                ['Recommended Retail', '15–30%', '#888'],
            ]));

            // 6 ── Volume & breakeven
            const fixedApprox  = d.miscellaneous + d.marketing;
            const varApprox    = d.manufacturing + d.transportation;
            const breakevenBatch = d.companyProfit > 0 ? Math.ceil(fixedApprox / (d.companyProfit)) : '∞';
            cards.push(suggCard('#20c997', '📦', 'Volume & Break-even', [
                ['Variable Cost / Unit', '₹' + (varApprox / d.batch).toFixed(2), '#20c997'],
                ['Fixed Cost / Batch', '₹' + fixedApprox.toFixed(2), '#20c997'],
                ['Break-even Units (est.)', breakevenBatch, '#20c997'],
                ['Batch Size Used', d.batch + ' units', '#888'],
                ['Scale Suggestion', d.batch < 500 ? 'Increase batch to 1000+ to cut per-unit cost' : 'Good batch size', d.batch < 500 ? '#fd7e14' : '#28a745'],
                ['ROI (per batch)', d.companyProfit > 0 ? ((d.companyProfit * d.batch / d.totalCost) * 100).toFixed(1) + '%' : '—', '#20c997'],
            ]));

            container.innerHTML = cards.join('');
            // fix grid on mobile
            if (window.innerWidth < 700) container.style.gridTemplateColumns = '1fr';
        }

        // ── tab switcher ─────────────────────────────────────────────────────
        function switchTab(tabId) {
            document.querySelectorAll('.result-tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.result-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).style.display = 'block';
            event.currentTarget.classList.add('active');
        }

        // ── utilities ────────────────────────────────────────────────────────
        function resetForm() {
            document.getElementById('mrpForm').reset();
            document.getElementById('resultsSection').style.display = 'none';
        }

        function saveCalculation() {
            const productName = document.getElementById('product_name').value || 'Unnamed Calculation';
            const variantEl   = document.getElementById('product_weight_variant');
            const variant     = variantEl ? variantEl.value : '';
            if (!variant) {
                alert('⚠️ Please select a Product Size / Weight Variant before saving (e.g. 500ml, 1L). This helps identify the calculation later.');
                return;
            }
            const formData = new FormData(document.getElementById('mrpForm'));
            formData.append('action', 'save_calculation');
            formData.append('calculation_name', productName);
            fetch('mrp_calculator.php', { method: 'POST', body: formData })
                .then(r => {
                    if (!r.ok) throw new Error('Server returned HTTP ' + r.status);
                    return r.text();
                })
                .then(text => {
                    let data;
                    try { data = JSON.parse(text); }
                    catch(e) { throw new Error('Server response not JSON: ' + text.substring(0, 200)); }
                    if (data.success) {
                        const msg = data.updated
                            ? '✅ Updated existing calculation for ' + productName + ' (' + variant + ')'
                            : '✅ Saved new calculation for ' + productName + ' (' + variant + ')';
                        alert(msg);
                        location.reload();
                    } else throw new Error(data.error || 'Unknown error');
                })
                .catch(err => alert('❌ Save failed: ' + err.message));
        }

        function loadCalculation(id) {
            fetch('mrp_calculator.php?action=load_calculation&id=' + id)
                .then(r => r.json())
                .then(data => {
                    const calcData = JSON.parse(data.data);
                    const form = document.getElementById('mrpForm');
                    Object.keys(calcData).forEach(key => {
                        const el = form.elements[key];
                        if (el) el.value = calcData[key];
                    });
                    // Show editable banner
                    const banner = document.getElementById('loadedCalcBanner');
                    document.getElementById('loadedCalcName').textContent = data.name || 'Untitled';
                    banner.style.display = 'block';
                    // Also restore /kg rate hint if raw_materials present
                    updatePerUnitHints();
                    // Scroll to top of form
                    document.getElementById('mrpForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
        }

        function clearLoadedCalc() {
            document.getElementById('loadedCalcBanner').style.display = 'none';
            document.getElementById('mrpForm').reset();
            document.getElementById('resultsSection').style.display = 'none';
            document.getElementById('pushPricePanel').style.display = 'none';
            document.querySelectorAll('.per-unit-hint').forEach(el => el.textContent = '');
            document.getElementById('rm_per_gram').textContent = '—';
            document.getElementById('rm_calc_hint').textContent = '';
        }

        // ── per-unit live hints ──────────────────────────────────────────────────
        function updatePerUnitHints() {
            const batch = parseFloat(document.getElementById('batch_size').value) || 0;
            document.querySelectorAll('.per-unit-hint[data-field]').forEach(hint => {
                const field = hint.dataset.field;
                const input = document.getElementById(field);
                if (!input) return;
                const val = parseFloat(input.value) || 0;
                if (val > 0 && batch > 0) {
                    hint.textContent = '≈ ₹' + (val / batch).toFixed(4) + ' per unit';
                    hint.style.color = '#2C5530';
                } else {
                    hint.textContent = '';
                }
            });
        }

        function deleteCalculation(id) {
            if (confirm('Delete this calculation?'))
                window.location.href = 'mrp_calculator.php?action=delete_calculation&id=' + id;
        }

        // ── parse display_weight text → grams ────────────────────────────────
        function parseWeightToGrams(text) {
            if (!text) return 0;
            const t = text.toLowerCase().trim();
            const num = parseFloat(t);
            if (isNaN(num)) return 0;
            if (t.includes('kg'))  return num * 1000;
            if (t.includes('mg'))  return num / 1000;
            if (t.includes('ml'))  return num;           // treat ml ≈ g
            if (t.includes('l') && !t.includes('ml')) return num * 1000;
            if (t.includes('g'))   return num;
            return num; // assume grams if no unit
        }

        // ── product name changed → populate weight variants ──────────────────
        function onProductChange() {
            const sel = document.getElementById('product_name');
            const opt = sel.options[sel.selectedIndex];
            const varSel  = document.getElementById('product_weight_variant');
            const hint    = document.getElementById('variant_hint');
            varSel.innerHTML = '<option value="" data-grams="0">— Select Size —</option>';

            if (!opt || !opt.dataset.pid) {
                hint.textContent = 'Select product first';
                // still sync push panel
                return;
            }
            const pid = opt.dataset.pid;
            const weights = (_productWeights[pid] && _productWeights[pid].weights) || [];
            weights.forEach(w => {
                const grams = parseWeightToGrams(w.display_weight);
                const o = document.createElement('option');
                o.value = w.display_weight;
                o.textContent = w.display_weight;
                o.dataset.grams = grams;
                o.dataset.wid   = w.wid;
                varSel.appendChild(o);
            });
            hint.textContent = weights.length ? weights.length + ' size(s) available' : 'No variants found';

            // sync push-to-store product selector
            const pushSel = document.getElementById('push_product_id');
            if (pushSel) { pushSel.value = pid; loadWeights(); }
        }

        // ── weight variant changed → auto-calc raw material cost ─────────────
        function onWeightVariantChange() {
            const varSel = document.getElementById('product_weight_variant');
            const opt    = varSel.options[varSel.selectedIndex];
            const grams  = parseFloat(opt && opt.dataset.grams) || 0;
            const hint   = document.getElementById('variant_hint');
            hint.textContent = grams > 0 ? 'Unit weight: ' + opt.value + ' = ' + grams + 'g' : '';

            // sync push weight selector
            if (opt && opt.dataset.wid) {
                const pushWid = document.getElementById('push_weight_id');
                if (pushWid) { pushWid.value = opt.dataset.wid; fillCurrentPrices(); }
            }
            autoCalcRawMaterial();
        }

        // ── show ₹/g equivalent next to ₹/kg ─────────────────────────────────
        function updateRmPerGram() {
            const ratePerKg = parseFloat(document.getElementById('rm_rate_per_kg').value) || 0;
            const el = document.getElementById('rm_per_gram');
            if (ratePerKg > 0) {
                el.textContent = '₹' + (ratePerKg / 1000).toFixed(4) + '/g';
            } else {
                el.textContent = '—';
            }
        }

        // ── auto-calculate batch raw material cost ────────────────────────────
        function autoCalcRawMaterial() {
            const ratePerKg = parseFloat(document.getElementById('rm_rate_per_kg').value) || 0;
            const batchSize = parseFloat(document.getElementById('batch_size').value)     || 0;
            const varSel    = document.getElementById('product_weight_variant');
            const opt       = varSel.options[varSel.selectedIndex];
            const grams     = parseFloat(opt && opt.dataset.grams) || 0;
            const hint      = document.getElementById('rm_calc_hint');

            if (ratePerKg > 0 && batchSize > 0 && grams > 0) {
                const costPerUnit  = (grams / 1000) * ratePerKg;
                const batchTotal   = costPerUnit * batchSize;
                document.getElementById('raw_materials').value = batchTotal.toFixed(2);
                hint.textContent = '📐 ' + batchSize + ' units × ' + grams + 'g × ₹' + ratePerKg + '/kg = ₹' + batchTotal.toFixed(2) + ' (₹' + costPerUnit.toFixed(4) + '/unit)';
                hint.style.color = '#2C5530';
            } else {
                hint.textContent = ratePerKg > 0 && grams === 0 ? '⚠ Select a weight variant to auto-calculate batch cost' : '';
            }
        }

        // ── push-to-store functions ───────────────────────────────────────────
        function loadWeights() {
            const pid    = document.getElementById('push_product_id').value;
            const sel    = document.getElementById('push_weight_id');
            sel.innerHTML = '<option value="">— Choose Variant —</option>';
            clearCurrentPrices();
            if (!pid || !_productWeights[pid]) return;
            const weights = _productWeights[pid].weights || [];
            weights.forEach(w => {
                const opt = document.createElement('option');
                opt.value = w.wid;
                opt.textContent = w.display_weight;
                opt.dataset.prices = JSON.stringify(w);
                sel.appendChild(opt);
            });
        }

        function clearCurrentPrices() {
            ['offline_mrp','website_price','wholesale_price','distributor_price','franchise_price','retail_price']
                .forEach(k => { const el = document.getElementById('curr_' + k); if (el) el.textContent = ''; });
        }

        function fillCurrentPrices() {
            const sel = document.getElementById('push_weight_id');
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.dataset.prices) { clearCurrentPrices(); return; }
            const p = JSON.parse(opt.dataset.prices);
            const map = {
                offline_mrp: p.offline_mrp, website_price: p.website_price,
                wholesale_price: p.wholesale_price, distributor_price: p.distributor_price,
                franchise_price: p.franchise_price, retail_price: p.retail_price
            };
            Object.entries(map).forEach(([k, v]) => {
                const el = document.getElementById('curr_' + k);
                if (el) el.textContent = v ? '(now: ₹' + parseFloat(v).toFixed(2) + ')' : '';
            });
        }

        function autofillFromCalculator() {
            if (!_lastCalc.offline_mrp) {
                alert('Please click Calculate MRP first.'); return;
            }
            const map = {
                push_offline_mrp:       _lastCalc.offline_mrp,
                push_website_price:     _lastCalc.website_price,
                push_wholesale_price:   _lastCalc.wholesale_price,
                push_distributor_price: _lastCalc.distributor_price,
                push_franchise_price:   _lastCalc.franchise_price,
                push_retail_price:      _lastCalc.retail_price
            };
            Object.entries(map).forEach(([id, val]) => {
                const el = document.getElementById(id);
                if (el) el.value = val > 0 ? val.toFixed(2) : '';
            });
            const gstEl = document.getElementById('push_gst');
            if (gstEl && _lastCalc.gst_pct > 0) gstEl.value = _lastCalc.gst_pct.toFixed(2);
        }

        function pushPriceToStore() {
            const wid = document.getElementById('push_weight_id').value;
            if (!wid) { alert('Please select a product and variant first.'); return; }

            const gstVal = document.getElementById('push_gst').value;
            const fields = {
                offline_mrp:        document.getElementById('push_offline_mrp').value,
                price:              document.getElementById('push_website_price').value,
                wholesale_price:    document.getElementById('push_wholesale_price').value,
                wholesale_gst:      gstVal,
                distributor_price:  document.getElementById('push_distributor_price').value,
                distributor_gst:    gstVal,
                franchise_price:    document.getElementById('push_franchise_price').value,
                franchise_gst:      gstVal,
                retail_price:       document.getElementById('push_retail_price').value,
                retail_gst:         gstVal
            };

            const hasValue = Object.values(fields).some(v => v !== '');
            if (!hasValue) { alert('Please fill at least one price field.'); return; }

            const fd = new FormData();
            fd.append('action', 'push_price');
            fd.append('weight_id', wid);
            Object.entries(fields).forEach(([k, v]) => { if (v !== '') fd.append(k, v); });

            const statusEl = document.getElementById('pushStatus');
            statusEl.style.display = 'inline-block';
            statusEl.style.background = '#fff3cd';
            statusEl.style.color = '#856404';
            statusEl.textContent = '⏳ Updating...';

            fetch('mrp_calculator.php', { method: 'POST', body: fd })
                .then(r => r.text())
                .then(text => {
                    let data;
                    try { data = JSON.parse(text); }
                    catch(e) { throw new Error('Invalid response: ' + text.substring(0, 150)); }
                    if (data.success) {
                        statusEl.style.background = '#d4edda';
                        statusEl.style.color = '#155724';
                        statusEl.textContent = '✅ Prices updated in store!';
                        // Refresh current price display
                        setTimeout(() => { statusEl.style.display = 'none'; }, 4000);
                    } else {
                        throw new Error(data.error || 'Unknown error');
                    }
                })
                .catch(err => {
                    statusEl.style.background = '#f8d7da';
                    statusEl.style.color = '#721c24';
                    statusEl.textContent = '❌ Failed: ' + err.message;
                });
        }

        function exportResults() {
            const results = {
                product_name: document.getElementById('product_name').value,
                batch_size:   document.getElementById('batch_size').value,
                unit:         document.getElementById('unit').value,
                manufacturing:  document.getElementById('totalManufacturing').textContent,
                transportation: document.getElementById('totalTransportation').textContent,
                marketing:      document.getElementById('totalMarketing').textContent,
                miscellaneous:  document.getElementById('totalMiscellaneous').textContent,
                cost_per_unit:  document.getElementById('costPerUnit').textContent,
                final_mrp_incl_gst: document.getElementById('finalMRP').textContent,
                final_mrp_rounded:  document.getElementById('finalMRPRounded').textContent,
                after_company:   document.getElementById('s_afterCompany').textContent,
                after_distributor: document.getElementById('s_afterDistributor').textContent,
                after_wholesaler: document.getElementById('s_afterWholesaler').textContent,
                after_retailer:  document.getElementById('s_afterRetailer').textContent,
                profit_status:   document.getElementById('s_profitStatus').textContent,
                export_date:     new Date().toLocaleString()
            };
            const a = document.createElement('a');
            a.href = 'data:application/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(results, null, 2));
            a.download = 'mrp_' + Date.now() + '.json';
            a.click();
        }
    </script>
</div>
<?php require_once '../includes/admin_footer.php'; ?>
