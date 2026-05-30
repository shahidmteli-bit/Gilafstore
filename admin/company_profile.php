<?php
/**
 * Company Profile Settings
 * Admin panel page to manage company details, logo, and visibility toggles
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/company_profile_functions.php';

require_admin();

$pageTitle = 'Company Profile — Admin';
$adminPage = 'company_profile';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_profile') {
        $data = [
            'company_name'        => trim($_POST['company_name'] ?? ''),
            'brand_name'          => trim($_POST['brand_name'] ?? ''),
            'tagline'             => trim($_POST['tagline'] ?? ''),
            'address'             => trim($_POST['address'] ?? ''),
            'city'                => trim($_POST['city'] ?? ''),
            'state'               => trim($_POST['state'] ?? ''),
            'pincode'             => trim($_POST['pincode'] ?? ''),
            'country'             => trim($_POST['country'] ?? 'India'),
            'phone'               => trim($_POST['phone'] ?? ''),
            'email'               => trim($_POST['email'] ?? ''),
            'website'             => trim($_POST['website'] ?? ''),
            'fssai_number'        => trim($_POST['fssai_number'] ?? ''),
            'gstin'               => trim($_POST['gstin'] ?? ''),
            'gstin_2'             => trim($_POST['gstin_2'] ?? ''),
            'pan_number'          => trim($_POST['pan_number'] ?? ''),
            'return_address'      => trim($_POST['return_address'] ?? ''),
            'return_city'         => trim($_POST['return_city'] ?? ''),
            'return_state'        => trim($_POST['return_state'] ?? ''),
            'return_pincode'      => trim($_POST['return_pincode'] ?? ''),
            'return_phone'        => trim($_POST['return_phone'] ?? ''),
            'default_courier'     => trim($_POST['default_courier'] ?? ''),
            'timezone'            => trim($_POST['timezone'] ?? 'Asia/Kolkata'),
            'show_gst_on_invoice' => isset($_POST['show_gst_on_invoice']) ? 1 : 0,
            'show_pan_on_invoice' => isset($_POST['show_pan_on_invoice']) ? 1 : 0,
            'show_phone_on_label' => isset($_POST['show_phone_on_label']) ? 1 : 0,
            'show_email_on_invoice' => isset($_POST['show_email_on_invoice']) ? 1 : 0,
            'show_return_address' => isset($_POST['show_return_address']) ? 1 : 0,
        ];

        if (empty($data['company_name'])) {
            $error = 'Company name is required.';
        } else {
            if (save_company_profile($data)) {
                $success = 'Company profile saved successfully.';
            } else {
                $error = 'Failed to save profile. Check error logs.';
            }
        }
    }

    if ($action === 'save_footer_contact') {
        $footerData = [
            'footer_description'   => trim($_POST['footer_description'] ?? ''),
            'footer_reg_address'   => trim($_POST['footer_reg_address'] ?? ''),
            'footer_reg_city'      => trim($_POST['footer_reg_city'] ?? ''),
            'footer_reg_state'     => trim($_POST['footer_reg_state'] ?? ''),
            'footer_reg_pincode'   => trim($_POST['footer_reg_pincode'] ?? ''),
            'footer_reg_country'   => trim($_POST['footer_reg_country'] ?? 'India'),
            'footer_support_email' => trim($_POST['footer_support_email'] ?? ''),
            'footer_phone_display' => trim($_POST['footer_phone_display'] ?? ''),
            'footer_whatsapp'      => trim($_POST['footer_whatsapp'] ?? ''),
        ];
        if (save_company_profile($footerData)) {
            $success = 'Footer contact info saved successfully.';
        } else {
            $error = 'Failed to save footer contact info.';
        }
    }

    if ($action === 'save_social_media') {
        $raw = $_POST['social'] ?? [];
        if (!is_array($raw)) $raw = [];
        // Re-assign sort_order based on posted order array
        $orderArr = $_POST['social_order'] ?? [];
        foreach ($orderArr as $pos => $pid) {
            if (isset($raw[(int)$pid])) {
                $raw[(int)$pid]['sort_order'] = (int)$pos + 1;
            }
        }
        if (save_footer_social_platforms($raw)) {
            $success = 'Social media settings saved successfully.';
        } else {
            $error = 'Failed to save social media settings.';
        }
    }

    if ($action === 'upload_logo') {
        if (!empty($_FILES['logo']['tmp_name'])) {
            $result = upload_company_logo($_FILES['logo']);
            if ($result) {
                $success = 'Logo uploaded and optimized successfully. Web: ' . $result['web'] . ', Print: ' . $result['print'];
            } else {
                $error = 'Logo upload failed. Accepted formats: PNG, JPG, WebP, SVG.';
            }
        } else {
            $error = 'Please select a logo file to upload.';
        }
    }
}

$profile          = get_company_profile();
$socialPlatforms  = get_footer_social_platforms();

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.cp-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 28px; margin-bottom: 20px; }
.cp-card h5 { font-weight: 700; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; }
.cp-card h5 i { color: #3b82f6; margin-right: 8px; }
.cp-label { font-weight: 600; font-size: 13px; color: #374151; margin-bottom: 4px; }
.cp-hint { font-size: 11px; color: #9ca3af; margin-top: 2px; }
.cp-logo-preview { width: 120px; height: 120px; border: 2px dashed #d1d5db; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fafafa; }
.cp-logo-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
.cp-toggle { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #f9fafb; border-radius: 8px; margin-bottom: 8px; }
.cp-toggle label { font-weight: 600; font-size: 13px; margin: 0; cursor: pointer; }
.cp-toggle .form-check-input { width: 44px; height: 22px; cursor: pointer; }
.cp-toggle .cp-toggle-hint { font-size: 11px; color: #6b7280; }

/* ── Social Media Settings ── */
.sm-row { display:flex; align-items:center; gap:12px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; margin-bottom:10px; transition:box-shadow .2s; }
.sm-row:hover { box-shadow:0 2px 10px rgba(0,0,0,.08); }
.sm-drag-handle { cursor:grab; color:#9ca3af; font-size:16px; flex-shrink:0; }
.sm-drag-handle:active { cursor:grabbing; }
.sm-icon-badge { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; color:#fff; flex-shrink:0; }
.sm-name { font-weight:600; font-size:13px; min-width:90px; flex-shrink:0; }
.sm-url-wrap { flex:1; min-width:0; }
.sm-url-wrap .form-control { font-size:12px; height:34px; }
.sm-controls { display:flex; align-items:center; gap:8px; flex-shrink:0; }
.sm-controls label { font-size:11px; color:#6b7280; margin:0; white-space:nowrap; }
.sm-section-header { font-size:11px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
.sm-migrate-notice { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px 14px; font-size:12px; color:#92400e; margin-bottom:16px; }
@media (max-width:768px) {
  .sm-row { flex-wrap:wrap; }
  .sm-name { min-width:70px; }
}
</style>

<section class="py-3">
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-semibold mb-0"><i class="fas fa-building text-primary me-2"></i>Company Profile</h4>
            <p class="text-muted mb-0">Manage company details used across invoices, shipping labels, and documents.</p>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Profile Form -->
        <div class="col-lg-8">

            <form method="POST" id="profileForm">
            <input type="hidden" name="action" value="save_profile">

            <!-- Company Info -->
            <div class="cp-card">
                <h5><i class="fas fa-info-circle"></i>Company Information</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="cp-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($profile['company_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="cp-label">Brand Name</label>
                        <input type="text" name="brand_name" class="form-control" value="<?= htmlspecialchars($profile['brand_name']); ?>">
                        <div class="cp-hint">Displayed on labels and invoices</div>
                    </div>
                    <div class="col-12">
                        <label class="cp-label">Tagline</label>
                        <input type="text" name="tagline" class="form-control" value="<?= htmlspecialchars($profile['tagline']); ?>" maxlength="500">
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="cp-card">
                <h5><i class="fas fa-phone-alt"></i>Contact Details</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="cp-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($profile['phone']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="cp-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($profile['email']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="cp-label">Website</label>
                        <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($profile['website']); ?>">
                    </div>
                </div>
            </div>

            <!-- Business Address -->
            <div class="cp-card">
                <h5><i class="fas fa-map-marker-alt"></i>Business Address</h5>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="cp-label">Full Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($profile['address']); ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($profile['city']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">State</label>
                        <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($profile['state']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">PIN Code</label>
                        <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($profile['pincode']); ?>" maxlength="10">
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">Country</label>
                        <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($profile['country']); ?>">
                    </div>
                </div>
            </div>

            <!-- Tax & Legal -->
            <div class="cp-card">
                <h5><i class="fas fa-file-invoice-dollar"></i>Tax & Legal</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="cp-label">FSSAI License No.</label>
                        <input type="text" name="fssai_number" class="form-control" value="<?= htmlspecialchars($profile['fssai_number']); ?>" maxlength="20">
                        <div class="cp-hint">Food Safety License — shown in website footer</div>
                    </div>
                    <div class="col-md-6">
                        <label class="cp-label">PAN Number</label>
                        <input type="text" name="pan_number" class="form-control" value="<?= htmlspecialchars($profile['pan_number']); ?>" maxlength="15" style="text-transform:uppercase;">
                        <div class="cp-hint">Permanent Account Number</div>
                    </div>
                    <div class="col-md-6">
                        <label class="cp-label">GSTIN 1 (Primary State)</label>
                        <input type="text" name="gstin" class="form-control" value="<?= htmlspecialchars($profile['gstin']); ?>" maxlength="20" style="text-transform:uppercase;">
                        <div class="cp-hint">Primary GST — shown in footer &amp; invoices</div>
                    </div>
                    <div class="col-md-6">
                        <label class="cp-label">GSTIN 2 (Another State)</label>
                        <input type="text" name="gstin_2" class="form-control" value="<?= htmlspecialchars($profile['gstin_2']); ?>" maxlength="20" style="text-transform:uppercase;">
                        <div class="cp-hint">Secondary GST — shown below primary in footer</div>
                    </div>
                </div>
            </div>

            <!-- Return Address -->
            <div class="cp-card">
                <h5><i class="fas fa-undo-alt"></i>Return Address <small class="text-muted fw-normal">(for shipping labels)</small></h5>
                <div class="mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyMainAddress()"><i class="fas fa-copy me-1"></i>Copy from Business Address</button>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="cp-label">Return Address</label>
                        <textarea name="return_address" id="return_address" class="form-control" rows="2"><?= htmlspecialchars($profile['return_address']); ?></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">City</label>
                        <input type="text" name="return_city" id="return_city" class="form-control" value="<?= htmlspecialchars($profile['return_city']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">State</label>
                        <input type="text" name="return_state" id="return_state" class="form-control" value="<?= htmlspecialchars($profile['return_state']); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">PIN Code</label>
                        <input type="text" name="return_pincode" id="return_pincode" class="form-control" value="<?= htmlspecialchars($profile['return_pincode']); ?>" maxlength="10">
                    </div>
                    <div class="col-md-3">
                        <label class="cp-label">Phone</label>
                        <input type="text" name="return_phone" id="return_phone" class="form-control" value="<?= htmlspecialchars($profile['return_phone']); ?>">
                    </div>
                </div>
            </div>

            <!-- Shipping Defaults -->
            <div class="cp-card">
                <h5><i class="fas fa-shipping-fast"></i>Shipping Defaults</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="cp-label">Default Courier</label>
                        <input type="text" name="default_courier" class="form-control" value="<?= htmlspecialchars($profile['default_courier']); ?>" placeholder="e.g. DTDC, BlueDart, Delhivery">
                        <div class="cp-hint">Pre-filled on shipping labels (can be overridden per order)</div>
                    </div>
                </div>
            </div>

            <!-- Regional Settings -->
            <div class="cp-card">
                <h5><i class="fas fa-globe-asia"></i>Regional Settings</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="cp-label">Time Zone <span class="text-danger">*</span></label>
                        <?php
                        $commonTimezones = [
                            'Asia/Kolkata'       => 'IST — India Standard Time (UTC+05:30)',
                            'UTC'                => 'UTC — Coordinated Universal Time',
                            'Europe/London'      => 'GMT/BST — London (UTC+00:00)',
                            'America/New_York'   => 'EST/EDT — New York (UTC−05:00)',
                            'America/Chicago'    => 'CST/CDT — Chicago (UTC−06:00)',
                            'America/Denver'     => 'MST/MDT — Denver (UTC−07:00)',
                            'America/Los_Angeles'=> 'PST/PDT — Los Angeles (UTC−08:00)',
                            'Asia/Dubai'         => 'GST — Dubai (UTC+04:00)',
                            'Asia/Singapore'     => 'SGT — Singapore (UTC+08:00)',
                            'Asia/Tokyo'         => 'JST — Tokyo (UTC+09:00)',
                            'Australia/Sydney'   => 'AEST — Sydney (UTC+10:00)',
                            'Pacific/Auckland'   => 'NZST — Auckland (UTC+12:00)',
                            'Asia/Karachi'       => 'PKT — Pakistan (UTC+05:00)',
                            'Asia/Dhaka'         => 'BST — Bangladesh (UTC+06:00)',
                            'Asia/Kathmandu'     => 'NPT — Nepal (UTC+05:45)',
                            'Asia/Colombo'       => 'SLST — Sri Lanka (UTC+05:30)',
                        ];
                        $currentTz = $profile['timezone'] ?? 'Asia/Kolkata';
                        ?>
                        <select name="timezone" class="form-select" id="timezoneSelect">
                            <?php foreach ($commonTimezones as $tzId => $tzLabel): ?>
                            <option value="<?= htmlspecialchars($tzId); ?>" <?= $currentTz === $tzId ? 'selected' : ''; ?>><?= htmlspecialchars($tzLabel); ?></option>
                            <?php endforeach; ?>
                            <option disabled>──────────────</option>
                            <?php
                            $allTz = timezone_identifiers_list();
                            foreach ($allTz as $tz):
                                if (!isset($commonTimezones[$tz])):
                            ?>
                            <option value="<?= htmlspecialchars($tz); ?>" <?= $currentTz === $tz ? 'selected' : ''; ?>><?= htmlspecialchars($tz); ?></option>
                            <?php endif; endforeach; ?>
                        </select>
                        <div class="cp-hint">
                            All dates and times across the site (promo codes, offers, orders) will follow this timezone.
                            <br>Current server time: <strong id="currentServerTime"><?= date('d M Y, h:i:s A T'); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Profile</button>
            </div>

            </form>
        </div>

        <!-- Right Column: Logo + Toggles -->
        <div class="col-lg-4">

            <!-- Logo Upload -->
            <div class="cp-card">
                <h5><i class="fas fa-image"></i>Company Logo</h5>
                <div class="text-center mb-3">
                    <div class="cp-logo-preview mx-auto mb-2">
                        <?php if (!empty($profile['logo_web'])): ?>
                        <img src="<?= base_url('assets/images/' . $profile['logo_web']); ?>" alt="Company Logo" id="logoPreview">
                        <?php elseif (!empty($profile['logo_print'])): ?>
                        <img src="<?= base_url('assets/images/' . $profile['logo_print']); ?>" alt="Company Logo" id="logoPreview">
                        <?php else: ?>
                        <span class="text-muted"><i class="fas fa-cloud-upload-alt fa-2x"></i></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($profile['logo_web']) || !empty($profile['logo_print'])): ?>
                    <div class="mb-2">
                        <span class="badge bg-success"><i class="fas fa-check"></i> Web: <?= htmlspecialchars($profile['logo_web']); ?></span><br>
                        <span class="badge bg-info mt-1"><i class="fas fa-print"></i> Print: <?= htmlspecialchars($profile['logo_print']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_logo">
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-control form-control-sm mb-2" required data-skip-validation="1">
                    <div class="cp-hint mb-2">PNG, JPG, WebP, or SVG. Auto-optimized: web (WebP, 400px) + print (PNG, 1200px high-res).</div>
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-upload me-1"></i>Upload Logo</button>
                </form>
            </div>

            <!-- Visibility Toggles -->
            <div class="cp-card">
                <h5><i class="fas fa-eye-slash"></i>Visibility Controls</h5>
                <p class="text-muted" style="font-size:12px;">Toggle which fields appear on invoices and shipping labels.</p>

                <div class="cp-toggle">
                    <div>
                        <label for="t_gst">Show GST on Invoice</label>
                        <div class="cp-toggle-hint">Display GSTIN on invoices</div>
                    </div>
                    <input class="form-check-input" type="checkbox" id="t_gst" name="show_gst_on_invoice" form="profileForm" <?= $profile['show_gst_on_invoice'] ? 'checked' : ''; ?>>
                </div>

                <div class="cp-toggle">
                    <div>
                        <label for="t_pan">Show PAN on Invoice</label>
                        <div class="cp-toggle-hint">Display PAN number on invoices</div>
                    </div>
                    <input class="form-check-input" type="checkbox" id="t_pan" name="show_pan_on_invoice" form="profileForm" <?= $profile['show_pan_on_invoice'] ? 'checked' : ''; ?>>
                </div>

                <div class="cp-toggle">
                    <div>
                        <label for="t_phone">Show Phone on Label</label>
                        <div class="cp-toggle-hint">Display phone on shipping labels</div>
                    </div>
                    <input class="form-check-input" type="checkbox" id="t_phone" name="show_phone_on_label" form="profileForm" <?= $profile['show_phone_on_label'] ? 'checked' : ''; ?>>
                </div>

                <div class="cp-toggle">
                    <div>
                        <label for="t_email">Show Email on Invoice</label>
                        <div class="cp-toggle-hint">Display email on invoices</div>
                    </div>
                    <input class="form-check-input" type="checkbox" id="t_email" name="show_email_on_invoice" form="profileForm" <?= $profile['show_email_on_invoice'] ? 'checked' : ''; ?>>
                </div>

                <div class="cp-toggle">
                    <div>
                        <label for="t_return">Show Return Address</label>
                        <div class="cp-toggle-hint">Display return address on labels</div>
                    </div>
                    <input class="form-check-input" type="checkbox" id="t_return" name="show_return_address" form="profileForm" <?= $profile['show_return_address'] ? 'checked' : ''; ?>>
                </div>

                <div class="mt-3 text-center">
                    <button type="submit" form="profileForm" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i>Save All Settings</button>
                </div>
            </div>

        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         FOOTER CONTACT INFORMATION
    ════════════════════════════════════════════════════════ -->
    <form method="POST" id="footerContactForm">
    <input type="hidden" name="action" value="save_footer_contact">
    <div class="cp-card">
        <h5><i class="fas fa-map-marker-alt"></i>Footer Contact Information
            <small class="text-muted fw-normal ms-2" style="font-size:12px;">Controls what appears in the website footer brand block</small>
        </h5>

        <div class="row g-3">
            <div class="col-12">
                <label class="cp-label">Brand Description <small class="text-muted fw-normal">(shown below logo in footer)</small></label>
                <textarea name="footer_description" class="form-control" rows="2" maxlength="500" placeholder="e.g. A premium D2C brand by Gilaf Foods &amp; Spices."><?= htmlspecialchars($profile['footer_description'] ?? 'A premium D2C brand by Gilaf Foods & Spices.'); ?></textarea>
                <div class="cp-hint">This text appears below the store logo in the footer. Leave blank to hide.</div>
            </div>

            <div class="col-12">
                <label class="cp-label">Registered Office — Street / Area</label>
                <input type="text" name="footer_reg_address" class="form-control"
                    value="<?= htmlspecialchars($profile['footer_reg_address'] ?? ''); ?>"
                    placeholder="e.g. Sopore, Baramulla">
            </div>
            <div class="col-md-3">
                <label class="cp-label">City</label>
                <input type="text" name="footer_reg_city" class="form-control"
                    value="<?= htmlspecialchars($profile['footer_reg_city'] ?? ''); ?>"
                    placeholder="e.g. Sopore">
            </div>
            <div class="col-md-3">
                <label class="cp-label">State</label>
                <input type="text" name="footer_reg_state" class="form-control"
                    value="<?= htmlspecialchars($profile['footer_reg_state'] ?? ''); ?>"
                    placeholder="e.g. J&K">
            </div>
            <div class="col-md-2">
                <label class="cp-label">Pincode</label>
                <input type="text" name="footer_reg_pincode" class="form-control" maxlength="10"
                    value="<?= htmlspecialchars($profile['footer_reg_pincode'] ?? ''); ?>"
                    placeholder="193201">
            </div>
            <div class="col-md-4">
                <label class="cp-label">Country</label>
                <input type="text" name="footer_reg_country" class="form-control"
                    value="<?= htmlspecialchars($profile['footer_reg_country'] ?? 'India'); ?>"
                    placeholder="India">
            </div>

            <div class="col-md-4">
                <label class="cp-label">Support Email</label>
                <input type="email" name="footer_support_email" class="form-control"
                    value="<?= htmlspecialchars($profile['footer_support_email'] ?? ''); ?>"
                    placeholder="support@yourstore.com">
                <div class="cp-hint">Displayed in footer contact bar. Leave blank to hide.</div>
            </div>
            <div class="col-md-4">
                <label class="cp-label">Phone Number</label>
                <input type="text" name="footer_phone_display" class="form-control"
                    value="<?= htmlspecialchars($profile['footer_phone_display'] ?? ''); ?>"
                    placeholder="+91 98765 43210">
                <div class="cp-hint">Displayed in footer contact bar. Leave blank to hide.</div>
            </div>
            <div class="col-md-4">
                <label class="cp-label">WhatsApp Number <small class="text-muted">(digits only)</small></label>
                <div class="input-group">
                    <span class="input-group-text" style="background:#25D366;color:#fff;border-color:#25D366;"><i class="fab fa-whatsapp"></i></span>
                    <input type="text" name="footer_whatsapp" class="form-control"
                        value="<?= htmlspecialchars($profile['footer_whatsapp'] ?? ''); ?>"
                        placeholder="919876543210">
                </div>
                <div class="cp-hint">Used for the WhatsApp social icon link. Format: country code + number.</div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Footer Contact</button>
        </div>
    </div>
    </form>

    <!-- ════════════════════════════════════════════════════════
         SOCIAL MEDIA SETTINGS
    ════════════════════════════════════════════════════════ -->
    <?php if (empty($socialPlatforms)): ?>
    <div class="cp-card">
        <h5><i class="fas fa-share-alt"></i>Social Media Settings</h5>
        <div class="sm-migrate-notice">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Social media table not found. Please run the migration first:
            <a href="migrate_footer_social.php" class="btn btn-sm btn-warning ms-2"><i class="fas fa-database me-1"></i>Run Migration</a>
        </div>
    </div>
    <?php else: ?>
    <form method="POST" id="socialMediaForm">
    <input type="hidden" name="action" value="save_social_media">
    <div class="cp-card">
        <h5><i class="fas fa-share-alt"></i>Social Media Settings
            <small class="text-muted fw-normal ms-2" style="font-size:12px;">Drag rows to reorder · Toggle to show/hide icons in footer</small>
        </h5>

        <div class="row mb-3 d-none d-md-flex px-1" style="font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">
            <div class="col-auto" style="width:30px;"></div>
            <div class="col-auto" style="width:50px;">Icon</div>
            <div class="col-auto" style="width:100px;">Platform</div>
            <div class="col">Profile URL</div>
            <div class="col-auto" style="min-width:80px;text-align:center;">Enable</div>
            <div class="col-auto" style="min-width:90px;text-align:center;">New Tab</div>
        </div>

        <div id="smSortable">
        <?php foreach ($socialPlatforms as $sm): ?>
        <?php
            $pid     = (int)$sm['id'];
            $color   = htmlspecialchars($sm['icon_color'] ?? '#555555');
            $icon    = htmlspecialchars($sm['icon_class']);
            $name    = htmlspecialchars($sm['platform_name']);
            $url     = htmlspecialchars($sm['social_url'] ?? '');
            $enabled = (int)($sm['is_enabled'] ?? 0);
            $newTab  = (int)($sm['open_new_tab'] ?? 1);
            $so      = (int)($sm['sort_order'] ?? 0);
        ?>
        <div class="sm-row" data-id="<?= $pid; ?>">
            <span class="sm-drag-handle" title="Drag to reorder"><i class="fas fa-grip-vertical"></i></span>
            <input type="hidden" name="social[<?= $pid; ?>][sort_order]" class="sm-sort-input" value="<?= $so; ?>">

            <div class="sm-icon-badge" style="background-color:<?= $color; ?>;">
                <i class="<?= $icon; ?>"></i>
            </div>

            <span class="sm-name"><?= $name; ?></span>

            <div class="sm-url-wrap">
                <input type="url" name="social[<?= $pid; ?>][social_url]"
                    class="form-control sm-url-input"
                    value="<?= $url; ?>"
                    placeholder="https://..."
                    data-id="<?= $pid; ?>">
            </div>

            <div class="sm-controls">
                <div class="d-flex flex-column align-items-center gap-1">
                    <label style="font-size:10px;color:#6b7280;">Enable</label>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input sm-enable-toggle" type="checkbox"
                            name="social[<?= $pid; ?>][is_enabled]" value="1"
                            <?= $enabled ? 'checked' : ''; ?>
                            data-id="<?= $pid; ?>"
                            style="width:36px;height:20px;cursor:pointer;">
                    </div>
                </div>
                <div class="d-flex flex-column align-items-center gap-1">
                    <label style="font-size:10px;color:#6b7280;">New Tab</label>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox"
                            name="social[<?= $pid; ?>][open_new_tab]" value="1"
                            <?= $newTab ? 'checked' : ''; ?>
                            style="width:36px;height:20px;cursor:pointer;">
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <input type="hidden" name="social_order" id="smOrderInput" value="">

        <div class="alert alert-info py-2 mt-3 mb-0" style="font-size:12px;">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Rules:</strong> Icon only shows in footer if <strong>enabled</strong> AND URL is not empty.
            Disable to completely hide from frontend regardless of URL.
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary px-4" onclick="prepareSmOrder()">
                <i class="fas fa-save me-2"></i>Save Social Media Settings
            </button>
            <a href="migrate_footer_social.php" class="btn btn-outline-secondary ms-2" title="Re-run migration to add missing platforms">
                <i class="fas fa-sync-alt me-1"></i>Re-run Migration
            </a>
        </div>
    </div>
    </form>
    <?php endif; ?>

</div>
</section>

<script>
function copyMainAddress() {
    document.getElementById('return_address').value = document.querySelector('[name="address"]').value;
    document.getElementById('return_city').value = document.querySelector('[name="city"]').value;
    document.getElementById('return_state').value = document.querySelector('[name="state"]').value;
    document.getElementById('return_pincode').value = document.querySelector('[name="pincode"]').value;
    document.getElementById('return_phone').value = document.querySelector('[name="phone"]').value;
}

// ── Social Media: Sortable drag-drop ──────────────────────────────────────
function prepareSmOrder() {
    var rows  = document.querySelectorAll('#smSortable .sm-row');
    var ids   = [];
    rows.forEach(function(row, idx) {
        var pid = row.getAttribute('data-id');
        ids.push(pid);
        // Also update hidden sort_order inputs
        var inp = row.querySelector('.sm-sort-input');
        if (inp) inp.value = idx + 1;
    });
    document.getElementById('smOrderInput').value = ids.join(',');
}

// Auto-disable toggle when URL is cleared
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sm-url-input').forEach(function(inp) {
        inp.addEventListener('input', function() {
            var pid    = this.getAttribute('data-id');
            var toggle = document.querySelector('.sm-enable-toggle[data-id="' + pid + '"]');
            if (toggle && !this.value.trim()) {
                toggle.checked = false;
            }
        });
    });

    // Init Sortable if library loaded
    if (typeof Sortable !== 'undefined') {
        Sortable.create(document.getElementById('smSortable'), {
            handle: '.sm-drag-handle',
            animation: 150,
            ghostClass: 'bg-light'
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
