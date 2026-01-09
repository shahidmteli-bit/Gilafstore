<?php
/**
 * Admin Settings Management Page
 * Manage site-wide settings including GST rate, discounts, etc.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

require_admin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $gstRate = (float)($_POST['gst_rate'] ?? 5);
    $promotionalDiscount = (float)($_POST['promotional_discount'] ?? 10);
    
    // Validate inputs
    if ($gstRate < 0 || $gstRate > 100) {
        $error = "GST rate must be between 0 and 100";
    } elseif ($promotionalDiscount < 0 || $promotionalDiscount > 100) {
        $error = "Promotional discount must be between 0 and 100";
    } else {
        update_setting('gst_rate', $gstRate);
        update_setting('promotional_discount', $promotionalDiscount);
        $success = "Settings updated successfully!";
    }
}

// Get current settings
$settings = get_all_settings();
$gstRate = $settings['gst_rate']['value'] ?? 5;
$promotionalDiscount = $settings['promotional_discount']['value'] ?? 10;

$pageTitle = 'Site Settings — Admin';
include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.settings-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.settings-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 20px;
}

.settings-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #1A3C34;
}

.settings-header h1 {
    color: #1A3C34;
    font-size: 28px;
    margin: 0 0 10px 0;
}

.settings-header p {
    color: #666;
    margin: 0;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 15px;
}

.form-group .help-text {
    display: block;
    font-size: 13px;
    color: #666;
    margin-top: 5px;
    font-style: italic;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 15px;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #1A3C34;
    box-shadow: 0 0 0 3px rgba(26, 60, 52, 0.1);
}

.input-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.input-group .form-control {
    flex: 1;
}

.input-addon {
    background: #f5f5f5;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-weight: 600;
    color: #666;
}

.btn-primary {
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    color: white;
    padding: 14px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(26, 60, 52, 0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0f2820 0%, #1A3C34 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.4);
}

.alert {
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-weight: 500;
}

.alert-success {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}

.alert-error {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #dc2626;
}

.settings-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #e5e5e5;
}

.settings-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: #1A3C34;
    margin-bottom: 15px;
}
</style>

<div class="settings-container">
    <div class="settings-card">
        <div class="settings-header">
            <h1><i class="fas fa-cog"></i> Site Settings</h1>
            <p>Manage global site configurations including tax rates and promotional discounts</p>
        </div>

        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="settings-section">
                <h3 class="section-title"><i class="fas fa-percent"></i> Tax Configuration</h3>
                
                <div class="form-group">
                    <label for="gst_rate">GST Rate (%)</label>
                    <div class="input-group">
                        <input type="number" 
                               id="gst_rate" 
                               name="gst_rate" 
                               class="form-control" 
                               value="<?= htmlspecialchars($gstRate) ?>" 
                               min="0" 
                               max="100" 
                               step="0.01" 
                               required>
                        <span class="input-addon">%</span>
                    </div>
                    <span class="help-text">
                        This GST rate will be applied to all products across the site (cart, checkout, invoices, orders)
                    </span>
                </div>
            </div>

            <div class="settings-section">
                <h3 class="section-title"><i class="fas fa-tags"></i> Promotional Settings</h3>
                
                <div class="form-group">
                    <label for="promotional_discount">Promotional Discount (%)</label>
                    <div class="input-group">
                        <input type="number" 
                               id="promotional_discount" 
                               name="promotional_discount" 
                               class="form-control" 
                               value="<?= htmlspecialchars($promotionalDiscount) ?>" 
                               min="0" 
                               max="100" 
                               step="0.01" 
                               required>
                        <span class="input-addon">%</span>
                    </div>
                    <span class="help-text">
                        This discount percentage is used to calculate "You will save ₹xxx" messages in cart and orders
                    </span>
                </div>
            </div>

            <button type="submit" name="update_settings" class="btn-primary">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </form>
    </div>

    <div class="settings-card" style="background: #f9fafb; border: 1px dashed #ddd;">
        <h3 style="color: #1A3C34; margin-top: 0;"><i class="fas fa-info-circle"></i> How It Works</h3>
        <ul style="color: #666; line-height: 1.8;">
            <li><strong>GST Rate:</strong> Applied to all product prices. For example, if set to 5%, a ₹100 item will show ₹105 total (₹100 + ₹5 GST)</li>
            <li><strong>Promotional Discount:</strong> Used to calculate savings messages. For example, 10% on a ₹1000 order shows "You will save ₹100"</li>
            <li><strong>Automatic Updates:</strong> Changes take effect immediately across cart, checkout, invoices, and order pages</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
