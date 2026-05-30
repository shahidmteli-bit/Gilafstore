<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$pageTitle = 'Payment Settings';
$adminPage = 'payment_settings';

$db = get_db_connection();

// Ensure gst_settings table can hold our payment keys
try {
    $db->exec("ALTER TABLE gst_settings MODIFY COLUMN setting_value TEXT");
} catch (Exception $e) {}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_razorpay') {
        $keyId = trim($_POST['razorpay_key_id'] ?? '');
        $keySecret = trim($_POST['razorpay_key_secret'] ?? '');
        $enabled = isset($_POST['razorpay_enabled']) ? '1' : '0';
        $testMode = isset($_POST['razorpay_test_mode']) ? '1' : '0';
        $webhookSecret = trim($_POST['razorpay_webhook_secret'] ?? '');
        
        update_setting('razorpay_key_id', $keyId);
        update_setting('razorpay_key_secret', $keySecret);
        update_setting('razorpay_enabled', $enabled);
        update_setting('razorpay_test_mode', $testMode);
        update_setting('razorpay_webhook_secret', $webhookSecret);
        
        $success = 'Razorpay settings saved successfully!';
    } elseif ($action === 'save_stripe') {
        $stripePublishableKey = trim($_POST['stripe_publishable_key'] ?? '');
        $stripeSecretKey = trim($_POST['stripe_secret_key'] ?? '');
        $stripeEnabled = isset($_POST['stripe_enabled']) ? '1' : '0';
        $stripeTestMode = isset($_POST['stripe_test_mode']) ? '1' : '0';
        $stripeWebhookSecret = trim($_POST['stripe_webhook_secret'] ?? '');
        
        update_setting('stripe_publishable_key', $stripePublishableKey);
        update_setting('stripe_secret_key', $stripeSecretKey);
        update_setting('stripe_enabled', $stripeEnabled);
        update_setting('stripe_test_mode', $stripeTestMode);
        update_setting('stripe_webhook_secret', $stripeWebhookSecret);
        
        $success = 'Stripe settings saved successfully!';
    } elseif ($action === 'save_paytm') {
        $paytmMid = trim($_POST['paytm_mid'] ?? '');
        $paytmMerchantKey = trim($_POST['paytm_merchant_key'] ?? '');
        $paytmEnabled = isset($_POST['paytm_enabled']) ? '1' : '0';
        $paytmTestMode = isset($_POST['paytm_test_mode']) ? '1' : '0';
        $paytmWebsite = trim($_POST['paytm_website'] ?? 'DEFAULT');
        
        update_setting('paytm_mid', $paytmMid);
        update_setting('paytm_merchant_key', $paytmMerchantKey);
        update_setting('paytm_enabled', $paytmEnabled);
        update_setting('paytm_test_mode', $paytmTestMode);
        update_setting('paytm_website', $paytmWebsite);
        
        $success = 'Paytm settings saved successfully!';
    } elseif ($action === 'save_phonepe') {
        $phonepeMid = trim($_POST['phonepe_mid'] ?? '');
        $phonepeSaltKey = trim($_POST['phonepe_salt_key'] ?? '');
        $phonepeSaltIndex = trim($_POST['phonepe_salt_index'] ?? '1');
        $phonepeEnabled = isset($_POST['phonepe_enabled']) ? '1' : '0';
        $phonepeTestMode = isset($_POST['phonepe_test_mode']) ? '1' : '0';
        
        update_setting('phonepe_mid', $phonepeMid);
        update_setting('phonepe_salt_key', $phonepeSaltKey);
        update_setting('phonepe_salt_index', $phonepeSaltIndex);
        update_setting('phonepe_enabled', $phonepeEnabled);
        update_setting('phonepe_test_mode', $phonepeTestMode);
        
        $success = 'PhonePe settings saved successfully!';
    } elseif ($action === 'save_upi_discount') {
        $upiDiscountEnabled = isset($_POST['upi_discount_enabled']) ? '1' : '0';
        $upiDiscountPercent = floatval($_POST['upi_discount_percent'] ?? 1.5);
        $upiDiscountMaxCap = floatval($_POST['upi_discount_max_cap'] ?? 50);
        
        // Validate ranges
        if ($upiDiscountPercent < 0 || $upiDiscountPercent > 100) $upiDiscountPercent = 1.5;
        if ($upiDiscountMaxCap < 0) $upiDiscountMaxCap = 50;
        
        update_setting('upi_discount_enabled', $upiDiscountEnabled);
        update_setting('upi_discount_percent', (string)$upiDiscountPercent);
        update_setting('upi_discount_max_cap', (string)$upiDiscountMaxCap);
        
        $success = 'UPI discount offer settings saved successfully!';
    } elseif ($action === 'save_payment_options') {
        $codEnabled = isset($_POST['cod_enabled']) ? '1' : '0';
        $upiEnabled = isset($_POST['upi_enabled']) ? '1' : '0';
        $razorpayCheckout = isset($_POST['razorpay_checkout_enabled']) ? '1' : '0';
        $stripeCheckout = isset($_POST['stripe_checkout_enabled']) ? '1' : '0';
        $paytmCheckout = isset($_POST['paytm_checkout_enabled']) ? '1' : '0';
        $phonepeCheckout = isset($_POST['phonepe_checkout_enabled']) ? '1' : '0';
        
        update_setting('cod_enabled', $codEnabled);
        update_setting('upi_direct_enabled', $upiEnabled);
        update_setting('razorpay_checkout_enabled', $razorpayCheckout);
        update_setting('stripe_checkout_enabled', $stripeCheckout);
        update_setting('paytm_checkout_enabled', $paytmCheckout);
        update_setting('phonepe_checkout_enabled', $phonepeCheckout);
        
        $success = 'Payment options saved successfully!';
    }
}

// Load current settings
$razorpayKeyId = get_setting('razorpay_key_id', '');
$razorpayKeySecret = get_setting('razorpay_key_secret', '');
$razorpayEnabled = get_setting('razorpay_enabled', '0');
$razorpayTestMode = get_setting('razorpay_test_mode', '1');
$razorpayWebhookSecret = get_setting('razorpay_webhook_secret', '');
$codEnabled = get_setting('cod_enabled', '1');
$upiDirectEnabled = get_setting('upi_direct_enabled', '1');
$razorpayCheckoutEnabled = get_setting('razorpay_checkout_enabled', '0');
$upiDiscountEnabled = get_setting('upi_discount_enabled', '0');
$upiDiscountPercent = get_setting('upi_discount_percent', '1.5');
$upiDiscountMaxCap = get_setting('upi_discount_max_cap', '50');

// Stripe settings
$stripePublishableKey = get_setting('stripe_publishable_key', '');
$stripeSecretKey = get_setting('stripe_secret_key', '');
$stripeEnabled = get_setting('stripe_enabled', '0');
$stripeTestMode = get_setting('stripe_test_mode', '1');
$stripeWebhookSecret = get_setting('stripe_webhook_secret', '');

// Paytm settings
$paytmMid = get_setting('paytm_mid', '');
$paytmMerchantKey = get_setting('paytm_merchant_key', '');
$paytmEnabled = get_setting('paytm_enabled', '0');
$paytmTestMode = get_setting('paytm_test_mode', '1');
$paytmWebsite = get_setting('paytm_website', 'DEFAULT');

// PhonePe settings
$phonepeMid = get_setting('phonepe_mid', '');
$phonepeSaltKey = get_setting('phonepe_salt_key', '');
$phonepeSaltIndex = get_setting('phonepe_salt_index', '1');
$phonepeEnabled = get_setting('phonepe_enabled', '0');
$phonepeTestMode = get_setting('phonepe_test_mode', '1');

// Transactions filter values
$showTransactions = isset($_GET['show_txn']) && $_GET['show_txn'] === '1';

// Create payments table if not exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT DEFAULT NULL,
        internal_order_id VARCHAR(50) DEFAULT NULL,
        razorpay_order_id VARCHAR(100) DEFAULT NULL,
        razorpay_payment_id VARCHAR(100) DEFAULT NULL,
        razorpay_signature VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'INR',
        status ENUM('created','authorized','captured','failed','refunded') DEFAULT 'created',
        payment_method VARCHAR(50) DEFAULT NULL,
        error_code VARCHAR(100) DEFAULT NULL,
        error_description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_internal_order_id (internal_order_id),
        INDEX idx_razorpay_order_id (razorpay_order_id),
        INDEX idx_razorpay_payment_id (razorpay_payment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Best-effort migration for older installs (Hostinger): make order_id nullable + add missing columns/indexes
try {
    $col = $db->query("SHOW COLUMNS FROM payments LIKE 'order_id'")->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper((string)($col['Null'] ?? '')) === 'NO') {
        $db->exec("ALTER TABLE payments MODIFY COLUMN order_id INT DEFAULT NULL");
    }
} catch (Exception $e) {}

try {
    $col = $db->query("SHOW COLUMNS FROM payments LIKE 'internal_order_id'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $db->exec("ALTER TABLE payments ADD COLUMN internal_order_id VARCHAR(50) DEFAULT NULL AFTER order_id");
    }
} catch (Exception $e) {}

try {
    $idx = $db->query("SHOW INDEX FROM payments WHERE Key_name = 'idx_internal_order_id'")->fetch(PDO::FETCH_ASSOC);
    if (!$idx) {
        $db->exec("ALTER TABLE payments ADD INDEX idx_internal_order_id (internal_order_id)");
    }
} catch (Exception $e) {}

// Get payment stats
// Stats and transactions are on payment_transactions.php

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.ps-wrap { max-width: 900px; margin: 0 auto; padding: 20px; }
.ps-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 20px; overflow: hidden; }
.ps-card-header { padding: 14px 20px; font-weight: 600; font-size: 15px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
.ps-card-body { padding: 20px; }
.ps-form-group { margin-bottom: 16px; }
.ps-form-group label { display: block; font-weight: 500; font-size: 13px; margin-bottom: 5px; color: #374151; }
.ps-form-group input[type="text"],
.ps-form-group input[type="password"] { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
.ps-form-group input:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
.ps-form-group .ps-hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
.ps-toggle { display: flex; align-items: center; gap: 10px; }
.ps-toggle input[type="checkbox"] { width: 18px; height: 18px; accent-color: #10b981; }
.ps-toggle label { font-weight: 500; font-size: 14px; margin: 0; cursor: pointer; }
.ps-btn { padding: 8px 20px; border: none; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; }
.ps-btn-primary { background: #3b82f6; color: #fff; }
.ps-btn-primary:hover { background: #2563eb; }
.ps-btn-success { background: #10b981; color: #fff; }
.ps-btn-success:hover { background: #059669; }
.ps-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 20px; }
.ps-stat { padding: 14px; border-radius: 8px; color: #fff; text-align: center; }
.ps-stat-num { font-size: 22px; font-weight: 700; }
.ps-stat-lbl { font-size: 11px; text-transform: uppercase; opacity: .85; margin-top: 2px; }
.ps-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.ps-badge-live { background: #dcfce7; color: #166534; }
.ps-badge-test { background: #fef3c7; color: #92400e; }
.ps-badge-off { background: #fee2e2; color: #991b1b; }
.ps-key-mask { font-family: monospace; letter-spacing: 1px; }
.ps-section-title { font-size: 13px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; }
.ps-payment-option { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px; }
.ps-payment-option-left { display: flex; align-items: center; gap: 12px; }
.ps-payment-option-left i { font-size: 20px; width: 30px; text-align: center; }
.ps-filter { display:flex; gap:12px; flex-wrap:wrap; align-items:end; }
.ps-filter .ps-form-group { margin-bottom: 0; }
.ps-gw-tabs { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.ps-gw-tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px; font-weight: 600; font-size: 12px; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; color: #6b7280; transition: all .2s; white-space: nowrap; }
.ps-gw-tab:hover { border-color: #3b82f6; color: #3b82f6; }
.ps-gw-tab.active { background: linear-gradient(135deg,#2563eb,#3b82f6); color: #fff; border-color: transparent; }
.ps-gw-tab .gw-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.ps-gw-tab .gw-dot.on { background: #10b981; }
.ps-gw-tab .gw-dot.off { background: #d1d5db; }
.ps-gw-tab.active .gw-dot.on { background: #86efac; }
.ps-gw-tab.active .gw-dot.off { background: rgba(255,255,255,.4); }
</style>

<div class="ps-wrap">
    <h4 style="margin-bottom: 5px;"><i class="fas fa-credit-card me-2"></i>Payment Settings</h4>
    <p style="color: #6b7280; font-size: 14px; margin-bottom: 16px;">Configure payment gateways and checkout options</p>

    <div style="display:flex;gap:8px;margin-bottom:12px;">
        <a href="<?= base_url('admin/payment_settings.php'); ?>" style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:8px;font-weight:700;font-size:13px;text-decoration:none;background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;border:1px solid transparent;"><i class="fas fa-sliders-h"></i> Settings</a>
        <a href="<?= base_url('admin/payment_transactions.php'); ?>" style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;border-radius:8px;font-weight:700;font-size:13px;text-decoration:none;background:#fff;color:#374151;border:1px solid #e5e7eb;"><i class="fas fa-exchange-alt"></i> Transactions</a>
    </div>

    <!-- Gateway Filter Tabs -->
    <div class="ps-gw-tabs" style="margin-bottom:18px;">
        <div class="ps-gw-tab active" onclick="filterGateway('all', this)">All Gateways</div>
        <div class="ps-gw-tab" onclick="filterGateway('razorpay', this)">
            <span class="gw-dot <?= $razorpayEnabled === '1' ? 'on' : 'off'; ?>"></span> Razorpay
        </div>
        <div class="ps-gw-tab" onclick="filterGateway('stripe', this)">
            <span class="gw-dot <?= $stripeEnabled === '1' ? 'on' : 'off'; ?>"></span> Stripe
        </div>
        <div class="ps-gw-tab" onclick="filterGateway('paytm', this)">
            <span class="gw-dot <?= $paytmEnabled === '1' ? 'on' : 'off'; ?>"></span> Paytm
        </div>
        <div class="ps-gw-tab" onclick="filterGateway('phonepe', this)">
            <span class="gw-dot <?= $phonepeEnabled === '1' ? 'on' : 'off'; ?>"></span> PhonePe
        </div>
        <div class="ps-gw-tab" onclick="filterGateway('options', this)">
            <i class="fas fa-toggle-on" style="font-size:11px;"></i> Checkout Options
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="font-size: 14px;">
        <i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Razorpay Configuration -->
    <div class="ps-card" data-gateway="razorpay">
        <div class="ps-card-header" style="background: linear-gradient(135deg,#1a237e,#283593); color: #fff;">
            <img src="https://razorpay.com/assets/razorpay-glyph.svg" alt="Razorpay" style="height: 22px; filter: brightness(10);" onerror="this.style.display='none'">
            Razorpay Configuration
            <?php if ($razorpayEnabled === '1'): ?>
                <?php if ($razorpayTestMode === '1'): ?>
                    <span class="ps-badge ps-badge-test">TEST MODE</span>
                <?php else: ?>
                    <span class="ps-badge ps-badge-live">LIVE</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="ps-badge ps-badge-off">DISABLED</span>
            <?php endif; ?>
        </div>
        <div class="ps-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_razorpay">
                
                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="razorpay_enabled" id="razorpayEnabled" <?= $razorpayEnabled === '1' ? 'checked' : ''; ?>>
                        <label for="razorpayEnabled">Enable Razorpay Payment Gateway</label>
                    </div>
                </div>

                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="razorpay_test_mode" id="razorpayTestMode" <?= $razorpayTestMode === '1' ? 'checked' : ''; ?>>
                        <label for="razorpayTestMode">Test Mode (use test API keys)</label>
                    </div>
                    <div class="ps-hint">Enable this for testing. Disable for live payments.</div>
                </div>

                <div class="ps-form-group">
                    <label>API Key ID</label>
                    <input type="text" name="razorpay_key_id" value="<?= htmlspecialchars($razorpayKeyId); ?>" placeholder="rzp_test_xxxxxxxxxx or rzp_live_xxxxxxxxxx">
                    <div class="ps-hint">Found in Razorpay Dashboard → Settings → API Keys</div>
                </div>

                <div class="ps-form-group">
                    <label>API Key Secret</label>
                    <input type="password" name="razorpay_key_secret" value="<?= htmlspecialchars($razorpayKeySecret); ?>" placeholder="Enter your API Key Secret">
                    <div class="ps-hint">Keep this secret. Never share or expose in frontend code.</div>
                </div>

                <div class="ps-form-group">
                    <label>Webhook Secret (Optional)</label>
                    <input type="text" name="razorpay_webhook_secret" value="<?= htmlspecialchars($razorpayWebhookSecret); ?>" placeholder="Enter webhook secret for signature verification">
                    <div class="ps-hint">Set this if you configure webhooks in Razorpay Dashboard</div>
                </div>

                <button type="submit" class="ps-btn ps-btn-primary"><i class="fas fa-save me-1"></i> Save Razorpay Settings</button>
            </form>
        </div>
    </div>

    <!-- Stripe Configuration -->
    <div class="ps-card" data-gateway="stripe">
        <div class="ps-card-header" style="background: linear-gradient(135deg,#635bff,#7a73ff); color: #fff;">
            <i class="fab fa-stripe-s" style="font-size: 20px;"></i>
            Stripe Configuration
            <?php if ($stripeEnabled === '1'): ?>
                <?php if ($stripeTestMode === '1'): ?>
                    <span class="ps-badge ps-badge-test">TEST MODE</span>
                <?php else: ?>
                    <span class="ps-badge ps-badge-live">LIVE</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="ps-badge ps-badge-off">DISABLED</span>
            <?php endif; ?>
        </div>
        <div class="ps-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_stripe">
                
                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="stripe_enabled" id="stripeEnabled" <?= $stripeEnabled === '1' ? 'checked' : ''; ?>>
                        <label for="stripeEnabled">Enable Stripe Payment Gateway</label>
                    </div>
                </div>

                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="stripe_test_mode" id="stripeTestMode" <?= $stripeTestMode === '1' ? 'checked' : ''; ?>>
                        <label for="stripeTestMode">Test Mode (use test API keys)</label>
                    </div>
                    <div class="ps-hint">Enable for testing with Stripe test keys. Disable for live payments.</div>
                </div>

                <div class="ps-form-group">
                    <label>Publishable Key</label>
                    <input type="text" name="stripe_publishable_key" value="<?= htmlspecialchars($stripePublishableKey); ?>" placeholder="pk_test_xxxxxxxxxx or pk_live_xxxxxxxxxx">
                    <div class="ps-hint">Found in Stripe Dashboard → Developers → API Keys</div>
                </div>

                <div class="ps-form-group">
                    <label>Secret Key</label>
                    <input type="password" name="stripe_secret_key" value="<?= htmlspecialchars($stripeSecretKey); ?>" placeholder="sk_test_xxxxxxxxxx or sk_live_xxxxxxxxxx">
                    <div class="ps-hint">Keep this secret. Never share or expose in frontend code.</div>
                </div>

                <div class="ps-form-group">
                    <label>Webhook Secret (Optional)</label>
                    <input type="text" name="stripe_webhook_secret" value="<?= htmlspecialchars($stripeWebhookSecret); ?>" placeholder="whsec_xxxxxxxxxx">
                    <div class="ps-hint">Set this if you configure webhooks in Stripe Dashboard</div>
                </div>

                <button type="submit" class="ps-btn ps-btn-primary"><i class="fas fa-save me-1"></i> Save Stripe Settings</button>
            </form>
        </div>
    </div>

    <!-- Paytm Configuration -->
    <div class="ps-card" data-gateway="paytm">
        <div class="ps-card-header" style="background: linear-gradient(135deg,#00baf2,#0098c9); color: #fff;">
            <i class="fas fa-wallet" style="font-size: 18px;"></i>
            Paytm Configuration
            <?php if ($paytmEnabled === '1'): ?>
                <?php if ($paytmTestMode === '1'): ?>
                    <span class="ps-badge ps-badge-test">TEST MODE</span>
                <?php else: ?>
                    <span class="ps-badge ps-badge-live">LIVE</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="ps-badge ps-badge-off">DISABLED</span>
            <?php endif; ?>
        </div>
        <div class="ps-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_paytm">
                
                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="paytm_enabled" id="paytmEnabled" <?= $paytmEnabled === '1' ? 'checked' : ''; ?>>
                        <label for="paytmEnabled">Enable Paytm Payment Gateway</label>
                    </div>
                </div>

                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="paytm_test_mode" id="paytmTestMode" <?= $paytmTestMode === '1' ? 'checked' : ''; ?>>
                        <label for="paytmTestMode">Test Mode (use staging environment)</label>
                    </div>
                    <div class="ps-hint">Enable for testing. Disable for live payments on production.</div>
                </div>

                <div class="ps-form-group">
                    <label>Merchant ID (MID)</label>
                    <input type="text" name="paytm_mid" value="<?= htmlspecialchars($paytmMid); ?>" placeholder="e.g., YourMerchant12345678901234">
                    <div class="ps-hint">Found in Paytm Business Dashboard → Developer Settings</div>
                </div>

                <div class="ps-form-group">
                    <label>Merchant Key</label>
                    <input type="password" name="paytm_merchant_key" value="<?= htmlspecialchars($paytmMerchantKey); ?>" placeholder="Enter your Merchant Key">
                    <div class="ps-hint">Keep this secret. Found alongside your MID in Paytm dashboard.</div>
                </div>

                <div class="ps-form-group">
                    <label>Website Name</label>
                    <input type="text" name="paytm_website" value="<?= htmlspecialchars($paytmWebsite); ?>" placeholder="DEFAULT or WEBSTAGING">
                    <div class="ps-hint">Use <code>WEBSTAGING</code> for test mode, <code>DEFAULT</code> for production</div>
                </div>

                <button type="submit" class="ps-btn ps-btn-primary"><i class="fas fa-save me-1"></i> Save Paytm Settings</button>
            </form>
        </div>
    </div>

    <!-- PhonePe Configuration -->
    <div class="ps-card" data-gateway="phonepe">
        <div class="ps-card-header" style="background: linear-gradient(135deg,#5f259f,#7b3fc4); color: #fff;">
            <i class="fas fa-mobile-alt" style="font-size: 18px;"></i>
            PhonePe Configuration
            <?php if ($phonepeEnabled === '1'): ?>
                <?php if ($phonepeTestMode === '1'): ?>
                    <span class="ps-badge ps-badge-test">TEST MODE</span>
                <?php else: ?>
                    <span class="ps-badge ps-badge-live">LIVE</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="ps-badge ps-badge-off">DISABLED</span>
            <?php endif; ?>
        </div>
        <div class="ps-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_phonepe">
                
                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="phonepe_enabled" id="phonepeEnabled" <?= $phonepeEnabled === '1' ? 'checked' : ''; ?>>
                        <label for="phonepeEnabled">Enable PhonePe Payment Gateway</label>
                    </div>
                </div>

                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="phonepe_test_mode" id="phonepeTestMode" <?= $phonepeTestMode === '1' ? 'checked' : ''; ?>>
                        <label for="phonepeTestMode">Test Mode (use UAT environment)</label>
                    </div>
                    <div class="ps-hint">Enable for testing on UAT. Disable for live production payments.</div>
                </div>

                <div class="ps-form-group">
                    <label>Merchant ID</label>
                    <input type="text" name="phonepe_mid" value="<?= htmlspecialchars($phonepeMid); ?>" placeholder="e.g., MERCHANTUAT or your production MID">
                    <div class="ps-hint">Found in PhonePe Business Dashboard</div>
                </div>

                <div class="ps-form-group">
                    <label>Salt Key</label>
                    <input type="password" name="phonepe_salt_key" value="<?= htmlspecialchars($phonepeSaltKey); ?>" placeholder="Enter your Salt Key">
                    <div class="ps-hint">Used for checksum generation. Keep this secret.</div>
                </div>

                <div class="ps-form-group">
                    <label>Salt Index</label>
                    <input type="text" name="phonepe_salt_index" value="<?= htmlspecialchars($phonepeSaltIndex); ?>" placeholder="1">
                    <div class="ps-hint">Usually <code>1</code>. Found in PhonePe dashboard alongside Salt Key.</div>
                </div>

                <button type="submit" class="ps-btn ps-btn-primary"><i class="fas fa-save me-1"></i> Save PhonePe Settings</button>
            </form>
        </div>
    </div>

    <!-- Payment Methods Toggle -->
    <div class="ps-card" data-gateway="options">
        <div class="ps-card-header"><i class="fas fa-toggle-on" style="color: #10b981;"></i> Checkout Payment Options</div>
        <div class="ps-card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_payment_options">

                <div class="ps-payment-option">
                    <div class="ps-payment-option-left">
                        <i class="fas fa-credit-card" style="color: #1a237e;"></i>
                        <div>
                            <strong>Razorpay Checkout</strong><br>
                            <small style="color: #6b7280;">Cards, UPI, Netbanking, Wallets — all via Razorpay</small>
                        </div>
                    </div>
                    <div class="ps-toggle">
                        <input type="checkbox" name="razorpay_checkout_enabled" id="rzpCheckout" <?= $razorpayCheckoutEnabled === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="ps-payment-option">
                    <div class="ps-payment-option-left">
                        <i class="fas fa-mobile-alt" style="color: #7c3aed;"></i>
                        <div>
                            <strong>Direct UPI</strong><br>
                            <small style="color: #6b7280;">Pay via UPI QR code / deep link (existing flow)</small>
                        </div>
                    </div>
                    <div class="ps-toggle">
                        <input type="checkbox" name="upi_enabled" id="upiDirect" <?= $upiDirectEnabled === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="ps-payment-option">
                    <div class="ps-payment-option-left">
                        <i class="fab fa-stripe-s" style="color: #635bff; font-size: 22px;"></i>
                        <div>
                            <strong>Stripe Checkout</strong><br>
                            <small style="color: #6b7280;">International cards, Apple Pay, Google Pay — via Stripe</small>
                        </div>
                    </div>
                    <div class="ps-toggle">
                        <input type="checkbox" name="stripe_checkout_enabled" id="stripeCheckout" <?= get_setting('stripe_checkout_enabled', '0') === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="ps-payment-option">
                    <div class="ps-payment-option-left">
                        <i class="fas fa-wallet" style="color: #00baf2;"></i>
                        <div>
                            <strong>Paytm Checkout</strong><br>
                            <small style="color: #6b7280;">UPI, Wallet, Netbanking, Cards — via Paytm</small>
                        </div>
                    </div>
                    <div class="ps-toggle">
                        <input type="checkbox" name="paytm_checkout_enabled" id="paytmCheckout" <?= get_setting('paytm_checkout_enabled', '0') === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="ps-payment-option">
                    <div class="ps-payment-option-left">
                        <i class="fas fa-mobile-alt" style="color: #5f259f;"></i>
                        <div>
                            <strong>PhonePe Checkout</strong><br>
                            <small style="color: #6b7280;">UPI, Wallet, Cards — via PhonePe PG</small>
                        </div>
                    </div>
                    <div class="ps-toggle">
                        <input type="checkbox" name="phonepe_checkout_enabled" id="phonepeCheckout" <?= get_setting('phonepe_checkout_enabled', '0') === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="ps-payment-option">
                    <div class="ps-payment-option-left">
                        <i class="fas fa-money-bill-wave" style="color: #059669;"></i>
                        <div>
                            <strong>Cash on Delivery</strong><br>
                            <small style="color: #6b7280;">Pay when the order is delivered</small>
                        </div>
                    </div>
                    <div class="ps-toggle">
                        <input type="checkbox" name="cod_enabled" id="codEnabled" <?= $codEnabled === '1' ? 'checked' : ''; ?>>
                    </div>
                </div>

                <button type="submit" class="ps-btn ps-btn-success mt-3"><i class="fas fa-save me-1"></i> Save Payment Options</button>
            </form>
        </div>
    </div>

    <!-- UPI Discount Offer -->
    <div class="ps-card" data-gateway="options">
        <div class="ps-card-header" style="background: linear-gradient(135deg, #059669, #10b981); color: #fff;">
            <i class="fas fa-percentage"></i> UPI Discount Offer
            <?php if ($upiDiscountEnabled === '1'): ?>
                <span class="ps-badge ps-badge-live">ACTIVE</span>
            <?php else: ?>
                <span class="ps-badge ps-badge-off">DISABLED</span>
            <?php endif; ?>
        </div>
        <div class="ps-card-body">
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 16px;">Configure the instant discount offered to customers who pay via UPI. This discount is applied automatically on the UPI payment page.</p>
            <form method="POST">
                <input type="hidden" name="action" value="save_upi_discount">
                
                <div class="ps-form-group">
                    <div class="ps-toggle">
                        <input type="checkbox" name="upi_discount_enabled" id="upiDiscountEnabled" <?= $upiDiscountEnabled === '1' ? 'checked' : ''; ?>>
                        <label for="upiDiscountEnabled">Enable UPI Discount Offer</label>
                    </div>
                    <div class="ps-hint">Show discount banner on checkout and apply discount on UPI payments</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="ps-form-group">
                        <label>Discount Percentage (%)</label>
                        <input type="number" name="upi_discount_percent" value="<?= htmlspecialchars($upiDiscountPercent); ?>" min="0.1" max="100" step="0.1" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <div class="ps-hint">e.g. 1.5 means 1.5% off on UPI payments</div>
                    </div>

                    <div class="ps-form-group">
                        <label>Maximum Discount Cap (₹)</label>
                        <input type="number" name="upi_discount_max_cap" value="<?= htmlspecialchars($upiDiscountMaxCap); ?>" min="1" step="1" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <div class="ps-hint">Maximum discount amount in rupees (e.g. 50 = up to ₹50 off)</div>
                    </div>
                </div>

                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                    <strong style="color: #166534; font-size: 13px;"><i class="fas fa-eye me-1"></i> Banner Preview:</strong>
                    <p style="color: #166534; font-size: 13px; margin: 8px 0 0 0;">Get <strong>flat <?= htmlspecialchars($upiDiscountPercent); ?>% off</strong> (up to ₹<?= htmlspecialchars(number_format((float)$upiDiscountMaxCap, 0)); ?>) on your order when you pay via UPI.</p>
                </div>

                <button type="submit" class="ps-btn ps-btn-success"><i class="fas fa-save me-1"></i> Save UPI Discount Settings</button>
            </form>
        </div>
    </div>

    <!-- Setup Guides -->
    <div class="ps-card" data-gateway="guides">
        <div class="ps-card-header"><i class="fas fa-book" style="color: #f59e0b;"></i> Payment Gateway Setup Guides</div>
        <div class="ps-card-body" style="font-size: 14px; line-height: 1.7;">
            
            <div style="margin-bottom: 20px;">
                <h6 style="color: #1a237e; margin-bottom: 8px;"><i class="fas fa-bolt me-1"></i> Razorpay</h6>
                <ol style="padding-left: 20px; margin-bottom: 0;">
                    <li>Sign up at <a href="https://dashboard.razorpay.com/signup" target="_blank">dashboard.razorpay.com</a></li>
                    <li>Go to Settings → API Keys → Generate Key</li>
                    <li>Paste Key ID and Key Secret above</li>
                    <li>Use <code>rzp_test_*</code> keys for testing, <code>rzp_live_*</code> for production</li>
                    <li>Webhook URL: <code><?= base_url('razorpay_webhook.php'); ?></code></li>
                </ol>
            </div>

            <hr style="border-color: #f3f4f6;">

            <div style="margin-bottom: 20px;">
                <h6 style="color: #635bff; margin-bottom: 8px;"><i class="fab fa-stripe-s me-1"></i> Stripe</h6>
                <ol style="padding-left: 20px; margin-bottom: 0;">
                    <li>Sign up at <a href="https://dashboard.stripe.com/register" target="_blank">dashboard.stripe.com</a></li>
                    <li>Go to Developers → API Keys</li>
                    <li>Copy Publishable Key (<code>pk_*</code>) and Secret Key (<code>sk_*</code>)</li>
                    <li>Toggle between Test and Live keys using the dashboard switch</li>
                    <li>Supports international cards, Apple Pay, Google Pay</li>
                </ol>
            </div>

            <hr style="border-color: #f3f4f6;">

            <div style="margin-bottom: 20px;">
                <h6 style="color: #00baf2; margin-bottom: 8px;"><i class="fas fa-wallet me-1"></i> Paytm</h6>
                <ol style="padding-left: 20px; margin-bottom: 0;">
                    <li>Sign up at <a href="https://business.paytm.com/" target="_blank">business.paytm.com</a></li>
                    <li>Go to Developer Settings to get MID and Merchant Key</li>
                    <li>Use Website: <code>WEBSTAGING</code> for test, <code>DEFAULT</code> for production</li>
                    <li>Supports UPI, Paytm Wallet, Netbanking, Cards</li>
                </ol>
            </div>

            <hr style="border-color: #f3f4f6;">

            <div style="margin-bottom: 16px;">
                <h6 style="color: #5f259f; margin-bottom: 8px;"><i class="fas fa-mobile-alt me-1"></i> PhonePe</h6>
                <ol style="padding-left: 20px; margin-bottom: 0;">
                    <li>Sign up at <a href="https://www.phonepe.com/business/" target="_blank">phonepe.com/business</a></li>
                    <li>Get Merchant ID, Salt Key, and Salt Index from dashboard</li>
                    <li>Use UAT environment for testing, Production for live</li>
                    <li>Supports UPI, PhonePe Wallet, Cards</li>
                </ol>
            </div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 12px; margin-top: 12px;">
                <strong style="color: #166534;"><i class="fas fa-shield-alt me-1"></i> Security Note:</strong>
                <span style="color: #166534;">All secret keys and merchant keys are stored securely in the database and never exposed to the frontend. Only public/publishable keys are used client-side.</span>
            </div>
        </div>
    </div>

</div>

<script>
function filterGateway(gateway, tab) {
    // Update active tab
    document.querySelectorAll('.ps-gw-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    
    // Show/hide cards
    document.querySelectorAll('.ps-card[data-gateway]').forEach(card => {
        if (gateway === 'all') {
            card.style.display = '';
        } else if (gateway === 'options') {
            card.style.display = (card.dataset.gateway === 'options') ? '' : 'none';
        } else {
            card.style.display = (card.dataset.gateway === gateway || card.dataset.gateway === 'guides') ? '' : 'none';
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
