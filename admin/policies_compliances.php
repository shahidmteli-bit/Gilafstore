<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Policies & Compliances';
$adminPage = 'policies';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_return_policy') {
            $return_policy = trim($_POST['return_policy']);
            
            // Update or insert return policy
            $existing = db_fetch('SELECT * FROM site_settings WHERE setting_key = ?', ['return_policy']);
            
            if ($existing) {
                db_query('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', 
                    [$return_policy, 'return_policy']);
            } else {
                db_query('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)', 
                    ['return_policy', $return_policy]);
            }
            
            $success = 'Return policy updated successfully!';
        } elseif ($_POST['action'] === 'update_fssai') {
            $fssai = trim($_POST['fssai_license']);
            
            $existing = db_fetch('SELECT * FROM site_settings WHERE setting_key = ?', ['fssai_license_number']);
            
            if ($existing) {
                db_query('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', 
                    [$fssai, 'fssai_license_number']);
            } else {
                db_query('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)', 
                    ['fssai_license_number', $fssai]);
            }
            
            $success = 'FSSAI License updated successfully!';
        } elseif ($_POST['action'] === 'update_privacy_policy') {
            $privacy_policy = trim($_POST['privacy_policy']);
            
            $existing = db_fetch('SELECT * FROM site_settings WHERE setting_key = ?', ['privacy_policy']);
            
            if ($existing) {
                db_query('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', 
                    [$privacy_policy, 'privacy_policy']);
            } else {
                db_query('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)', 
                    ['privacy_policy', $privacy_policy]);
            }
            
            $success = 'Privacy policy updated successfully!';
        } elseif ($_POST['action'] === 'update_terms') {
            $terms = trim($_POST['terms_conditions']);
            
            $existing = db_fetch('SELECT * FROM site_settings WHERE setting_key = ?', ['terms_conditions']);
            
            if ($existing) {
                db_query('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?', 
                    [$terms, 'terms_conditions']);
            } else {
                db_query('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)', 
                    ['terms_conditions', $terms]);
            }
            
            $success = 'Terms & Conditions updated successfully!';
        }
    }
}

// Get current policies
$returnPolicy = get_site_setting('return_policy', 'Returns allowed only for damaged, defective, or incorrect products within 7 days of delivery');
$fssaiLicense = get_site_setting('fssai_license_number', '12724064000335');
$privacyPolicy = get_site_setting('privacy_policy', 'Your privacy is important to us...');
$termsConditions = get_site_setting('terms_conditions', 'By using our website, you agree to these terms...');

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.policies-container {
    background: #ffffff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.page-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f1f3f5;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.policy-section {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 10px;
    margin-bottom: 24px;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 16px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 8px;
    display: block;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 15px;
    font-family: inherit;
}

.form-control:focus {
    border-color: #d4af37;
    outline: none;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.btn-primary {
    padding: 12px 24px;
    background: linear-gradient(135deg, #d4af37 0%, #c5a028 100%);
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #c5a028 0%, #b69121 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
}

.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #10b981;
}

.info-box {
    background: #e0f2fe;
    border-left: 4px solid #0284c7;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.info-box p {
    margin: 0;
    font-size: 14px;
    color: #0c4a6e;
}
</style>

<div class="container mt-4">
    <div class="policies-container">
        <div class="page-header">
            <h1 class="page-title">Policies & Compliances</h1>
            <p style="color: #6c757d; margin-top: 8px;">Manage your store's policies and compliance information</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Return Policy -->
        <div class="policy-section">
            <h3 class="section-title">Return Policy</h3>
            <div class="info-box">
                <p><i class="fas fa-info-circle me-2"></i>This policy will be displayed on all product pages. Be clear about return conditions and timeframes.</p>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_return_policy">
                <div class="form-group">
                    <label class="form-label">Return Policy Text</label>
                    <textarea name="return_policy" class="form-control" required><?= htmlspecialchars($returnPolicy); ?></textarea>
                    <small style="color: #6c757d; display: block; margin-top: 8px;">
                        Example: "Returns allowed only for damaged, defective, or incorrect products within 7 days of delivery"
                    </small>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save me-2"></i>Update Return Policy
                </button>
            </form>
        </div>

        <!-- FSSAI License -->
        <div class="policy-section">
            <h3 class="section-title">FSSAI License Number</h3>
            <div class="info-box">
                <p><i class="fas fa-info-circle me-2"></i>Food Safety and Standards Authority of India license number. Required for food products.</p>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_fssai">
                <div class="form-group">
                    <label class="form-label">FSSAI License Number</label>
                    <input type="text" name="fssai_license" class="form-control" value="<?= htmlspecialchars($fssaiLicense); ?>" required>
                    <small style="color: #6c757d; display: block; margin-top: 8px;">
                        This will be displayed in the product details section
                    </small>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save me-2"></i>Update FSSAI License
                </button>
            </form>
        </div>

        <!-- Privacy Policy -->
        <div class="policy-section">
            <h3 class="section-title">Privacy Policy</h3>
            <div class="info-box">
                <p><i class="fas fa-info-circle me-2"></i>Explain how you collect, use, and protect customer data.</p>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_privacy_policy">
                <div class="form-group">
                    <label class="form-label">Privacy Policy</label>
                    <textarea name="privacy_policy" class="form-control" style="min-height: 200px;" required><?= htmlspecialchars($privacyPolicy); ?></textarea>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save me-2"></i>Update Privacy Policy
                </button>
            </form>
        </div>

        <!-- Terms & Conditions -->
        <div class="policy-section">
            <h3 class="section-title">Terms & Conditions</h3>
            <div class="info-box">
                <p><i class="fas fa-info-circle me-2"></i>Define the rules and guidelines for using your website and services.</p>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update_terms">
                <div class="form-group">
                    <label class="form-label">Terms & Conditions</label>
                    <textarea name="terms_conditions" class="form-control" style="min-height: 200px;" required><?= htmlspecialchars($termsConditions); ?></textarea>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save me-2"></i>Update Terms & Conditions
                </button>
            </form>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
