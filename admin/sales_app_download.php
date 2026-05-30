<?php
/**
 * Admin - Sales App Download & Install Instructions
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$pageTitle = 'Sales App Download';
$adminPage = 'sales_app_download';

// Detect server IP for the install link
$serverIP = $_SERVER['SERVER_ADDR'] ?? '192.168.1.11';
$portalUrl = 'http://' . $serverIP . '/Gilaf%20Ecommerce%20website/sales-portal/';
$portalUrlHttps = 'https://' . $serverIP . '/Gilaf%20Ecommerce%20website/sales-portal/';

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-mobile-alt me-2 text-primary"></i>Sales App Download</h2>
            <p class="text-muted mb-0">Share the app link with your sales team</p>
        </div>
    </div>

    <!-- Quick Share Links -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div style="width:70px;height:70px;background:linear-gradient(135deg,#7c3aed,#a78bfa);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;font-size:28px;">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Web App (PWA)</h4>
                    <p class="text-muted mb-3">Works instantly — no download needed. Sales team opens the link in Chrome and adds to home screen.</p>
                    
                    <div class="input-group mb-3">
                        <input type="text" class="form-control text-center fw-semibold" id="pwaLink" value="<?= htmlspecialchars($portalUrl) ?>" readonly style="font-size:13px;">
                        <button class="btn btn-primary" onclick="copyLink('pwaLink')" title="Copy"><i class="fas fa-copy"></i></button>
                    </div>

                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="https://wa.me/?text=<?= urlencode('Gilaf Sales App — Open this link in Chrome and tap "Add to Home Screen": ' . $portalUrl) ?>" target="_blank" class="btn btn-success btn-sm">
                            <i class="fab fa-whatsapp me-1"></i> Share via WhatsApp
                        </a>
                        <button class="btn btn-outline-primary btn-sm" onclick="copyLink('pwaLink')">
                            <i class="fas fa-link me-1"></i> Copy Link
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center p-4">
                    <div style="width:70px;height:70px;background:linear-gradient(135deg,#059669,#34d399);border-radius:18px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;font-size:28px;">
                        <i class="fab fa-android"></i>
                    </div>
                    <h4 class="fw-bold mb-2">Android APK</h4>
                    <p class="text-muted mb-3">Native Android app. Share this APK file with your sales team via WhatsApp, email, or Google Drive.</p>
                    
                    <?php
                    $apkFile = __DIR__ . '/../sales-portal/downloads/gilaf-sales.apk';
                    if (file_exists($apkFile)):
                        $apkSize = round(filesize($apkFile) / (1024 * 1024), 1);
                        $apkDate = date('d M Y, h:i A', filemtime($apkFile));
                    ?>
                    <a href="<?= base_url('sales-portal/downloads/gilaf-sales.apk') ?>" class="btn btn-success btn-lg mb-3" download>
                        <i class="fas fa-download me-2"></i> Download APK (<?= $apkSize ?> MB)
                    </a>
                    <div class="text-muted small mb-3">Built: <?= $apkDate ?></div>
                    
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="https://wa.me/?text=<?= urlencode('Download Gilaf Sales App: ' . 'http://' . ($_SERVER['SERVER_ADDR'] ?? '192.168.1.11') . '/Gilaf%20Ecommerce%20website/sales-portal/downloads/gilaf-sales.apk') ?>" target="_blank" class="btn btn-outline-success btn-sm">
                            <i class="fab fa-whatsapp me-1"></i> Share APK via WhatsApp
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning py-2 mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle me-1"></i> APK not built yet. Build it using the <code>apk-builder</code> folder.
                    </div>
                    <a href="<?= base_url('sales-portal/apk-builder/README.md') ?>" target="_blank" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-book me-1"></i> Build Instructions
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Install Instructions for Sales Team -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="fas fa-list-ol me-2 text-primary"></i>How to Install (Share with Sales Team)</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center mb-3">
                        <div style="width:50px;height:50px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;color:#2563eb;font-size:20px;font-weight:800;">1</div>
                    </div>
                    <h6 class="fw-bold text-center">Open in Chrome</h6>
                    <p class="text-muted text-center small">Open the sales portal link in <strong>Google Chrome</strong> on the phone (not any other browser).</p>
                </div>
                <div class="col-md-4">
                    <div class="text-center mb-3">
                        <div style="width:50px;height:50px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;color:#059669;font-size:20px;font-weight:800;">2</div>
                    </div>
                    <h6 class="fw-bold text-center">Add to Home Screen</h6>
                    <p class="text-muted text-center small">Tap the <strong>⋮ menu</strong> (3 dots top-right) → <strong>"Add to Home Screen"</strong> → Tap <strong>"Add"</strong>.</p>
                </div>
                <div class="col-md-4">
                    <div class="text-center mb-3">
                        <div style="width:50px;height:50px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;color:#d97706;font-size:20px;font-weight:800;">3</div>
                    </div>
                    <h6 class="fw-bold text-center">Open & Login</h6>
                    <p class="text-muted text-center small">The app icon appears on the home screen. Open it and login with the credentials you created.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Info -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="fas fa-server me-2 text-muted"></i>Server Info</h5>
        </div>
        <div class="card-body">
            <table class="table table-sm mb-0">
                <tr>
                    <td class="fw-semibold" style="width:200px;">Server IP</td>
                    <td><code><?= htmlspecialchars($serverIP) ?></code></td>
                </tr>
                <tr>
                    <td class="fw-semibold">HTTP URL</td>
                    <td><code><?= htmlspecialchars($portalUrl) ?></code></td>
                </tr>
                <tr>
                    <td class="fw-semibold">HTTPS URL</td>
                    <td><code><?= htmlspecialchars($portalUrlHttps) ?></code></td>
                </tr>
                <tr>
                    <td class="fw-semibold">Camera/QR Scanning</td>
                    <td><span class="badge bg-info">Requires HTTPS</span> — Use the HTTPS link for camera features, or use "Upload QR Image" fallback on HTTP.</td>
                </tr>
            </table>
        </div>
    </div>
</div>

<script>
function copyLink(inputId) {
    var input = document.getElementById(inputId);
    input.select();
    document.execCommand('copy');
    
    var btn = input.nextElementSibling;
    var origHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i>';
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-success');
    setTimeout(function() {
        btn.innerHTML = origHtml;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-primary');
    }, 1500);
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
