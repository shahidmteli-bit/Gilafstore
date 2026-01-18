<?php
/**
 * Public Batch Verification Page
 * Allows anyone to verify batch authenticity via QR scan or manual entry
 */

$pageTitle = 'Verify Batch Authenticity - Gilaf Store';
$activePage = 'verify';

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/batch_functions.php';

// Auto-update expired batches
check_and_update_expired_batches();

$batchCode = $_GET['code'] ?? '';
$verificationResult = null;
$error = null;

if (!empty($batchCode)) {
    try {
        $db = get_db_connection();
        
        // Clean and normalize batch code
        $cleanBatchCode = strtoupper(trim($batchCode));
        
        // Fetch batch details with case-insensitive search
        $stmt = $db->prepare("
            SELECT bc.*, p.image as product_image, p.price as product_price
            FROM batch_codes bc
            LEFT JOIN products p ON p.id = bc.product_id
            WHERE UPPER(bc.batch_code) = :batch_code
            AND (bc.is_active = 1 OR bc.is_active IS NULL)
        ");
        $stmt->execute([':batch_code' => $cleanBatchCode]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($batch) {
            // Log verification (temporarily disabled due to permission issue)
            // log_batch_verification($batch['id'], $batch['batch_code'], 'manual_entry');
            
            // Get approver name if approved
            $approverName = null;
            if (!empty($batch['quality_approved']) && !empty($batch['quality_approver_id'])) {
                $approverStmt = $db->prepare("SELECT name FROM users WHERE id = :id");
                $approverStmt->execute([':id' => $batch['quality_approver_id']]);
                $approver = $approverStmt->fetch(PDO::FETCH_ASSOC);
                $approverName = $approver ? $approver['name'] : 'Admin';
            }
            
            $verificationResult = [
                'valid' => true,
                'batch' => $batch,
                'approver_name' => $approverName,
                'verified_at' => date('F j, Y, g:i a')
            ];
        } else {
            $error = 'not_found';
        }
    } catch (PDOException $e) {
        error_log("Batch verification database error: " . $e->getMessage());
        error_log("Batch code attempted: " . $batchCode);
        $error = 'system_error';
    } catch (Exception $e) {
        error_log("Batch verification error: " . $e->getMessage());
        error_log("Batch code attempted: " . $batchCode);
        $error = 'system_error';
    }
}

include __DIR__ . '/includes/new-header.php';
?>

<style>
.verify-hero {
    background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%);
    padding: 60px 0;
    color: white;
}

.verify-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 20px;
}

.verify-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    padding: 40px;
    margin-bottom: 30px;
}

.verify-input-group {
    position: relative;
    margin-bottom: 20px;
}

.verify-input {
    width: 100%;
    padding: 18px 24px;
    font-size: 18px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-family: 'Courier New', monospace;
}

.verify-input:focus {
    border-color: var(--color-green);
    outline: none;
    box-shadow: 0 0 0 4px rgba(26, 60, 52, 0.1);
}

.verify-buttons {
    display: flex;
    gap: 12px;
    margin-top: 12px;
}

.verify-btn {
    flex: 1;
    padding: 18px;
    font-size: 18px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--color-green) 0%, #0f2820 100%);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.verify-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(26, 60, 52, 0.3);
}

.clear-btn {
    flex: 0 0 140px;
    padding: 18px;
    font-size: 18px;
    font-weight: 600;
    background: white;
    color: var(--color-green);
    border: 2px solid var(--color-green);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.clear-btn:hover {
    background: var(--color-green);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(26, 60, 52, 0.3);
}

.result-card {
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 20px;
}

.result-valid {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 2px solid #10b981;
}

.result-invalid {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border: 2px solid #ef4444;
}

.result-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 2px solid #f59e0b;
}

.authenticity-badge {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background: #10b981;
    color: white;
    border-radius: 50px;
    font-weight: 600;
    font-size: 18px;
    margin: 20px 0;
}

.product-preview {
    display: flex;
    gap: 20px;
    align-items: center;
    padding: 20px;
    background: white;
    border-radius: 12px;
    margin: 20px 0;
}

.product-preview img {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 8px;
}

.batch-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.batch-info-item {
    padding: 15px;
    background: #f9fafb;
    border-radius: 8px;
    border-left: 4px solid var(--color-green);
}

.batch-info-label {
    font-size: 12px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.batch-info-value {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
}

.warning-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.cta-buttons {
    display: flex;
    gap: 15px;
    margin-top: 20px;
}

.cta-btn {
    flex: 1;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    font-weight: 600;
    transition: all 0.3s;
}

.cta-primary {
    background: var(--color-green);
    color: white;
}

.cta-secondary {
    background: white;
    color: var(--color-green);
    border: 2px solid var(--color-green);
}

.verification-footer {
    text-align: center;
    padding: 30px;
    color: #6b7280;
    font-size: 14px;
}

.qr-scanner-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: white;
    color: var(--color-green);
    border: 2px solid var(--color-green);
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 20px;
}

.qr-scanner-btn:hover {
    background: var(--color-green);
    color: white;
}

.lab-report-section {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #e5e7eb;
    text-align: center;
}

.lab-report-btn {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 16px 32px;
    background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
    color: white;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    font-size: 16px;
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    transition: all 0.3s ease;
}

.lab-report-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
    color: white;
    text-decoration: none;
}

.lab-report-btn i:first-child {
    font-size: 20px;
}

.lab-report-btn i:last-child {
    font-size: 14px;
}
</style>

<section class="verify-hero">
    <div class="container text-center">
        <h1 class="display-4 mb-3">🔍 Verify Product Authenticity</h1>
        <p class="lead">Scan QR code or enter batch code to verify your Gilaf product</p>
    </div>
</section>

<div class="verify-container">
    <div class="verify-card">
        <h3 class="text-center mb-4">Enter Batch Code</h3>
        
        <form method="GET" action="" id="verifyForm">
            <div class="verify-input-group">
                <input 
                    type="text" 
                    name="code" 
                    id="batchCodeInput"
                    class="verify-input" 
                    placeholder="Enter Batch Code (e.g., GF-2025-01)"
                    value="<?= htmlspecialchars($batchCode) ?>"
                    required
                    autofocus
                >
            </div>
            
            <div class="verify-buttons">
                <button type="submit" class="verify-btn">
                    <i class="fas fa-check-circle"></i> VERIFY NOW
                </button>
                <button type="button" class="clear-btn" onclick="clearBatchInput()">
                    <i class="fas fa-times-circle"></i> CLEAR
                </button>
            </div>
        </form>
        
        <script>
        function clearBatchInput() {
            const input = document.getElementById('batchCodeInput');
            input.value = '';
            input.focus();
            
            // Clear any verification results if displayed
            const resultDiv = document.querySelector('.result-card');
            if (resultDiv) {
                resultDiv.style.display = 'none';
            }
            
            // If there's a code in URL, redirect to clean URL
            if (window.location.search) {
                window.history.pushState({}, '', window.location.pathname);
            }
        }
        </script>
        
        <div class="text-center mt-3">
            <button class="qr-scanner-btn" onclick="alert('QR Scanner feature coming soon!')">
                <i class="fas fa-qrcode"></i> Scan QR Code
            </button>
        </div>
    </div>

    <?php if ($verificationResult): ?>
        <?php $batch = $verificationResult['batch']; ?>
        
        <?php if (in_array($batch['status'], ['expired', 'recalled', 'blocked'])): ?>
            <!-- Invalid/Warning Result -->
            <div class="result-card result-<?= $batch['status'] === 'expired' ? 'warning' : 'invalid' ?>">
                <div class="text-center">
                    <div class="warning-icon">
                        <?php if ($batch['status'] === 'expired'): ?>
                            ⚠️
                        <?php elseif ($batch['status'] === 'recalled'): ?>
                            🚫
                        <?php else: ?>
                            ❌
                        <?php endif; ?>
                    </div>
                    
                    <h2 class="mb-3">
                        <?php if ($batch['status'] === 'expired'): ?>
                            Batch Expired
                        <?php elseif ($batch['status'] === 'recalled'): ?>
                            Batch Recalled
                        <?php else: ?>
                            Batch Blocked
                        <?php endif; ?>
                    </h2>
                    
                    <p class="lead mb-4">
                        <?php if ($batch['status'] === 'expired'): ?>
                            This batch has passed its expiry date and should not be consumed.
                        <?php elseif ($batch['status'] === 'recalled'): ?>
                            This batch has been recalled. Please do not use this product.
                        <?php else: ?>
                            This batch has been blocked and is not available for sale.
                        <?php endif; ?>
                    </p>
                    
                    <div class="batch-info-grid">
                        <div class="batch-info-item">
                            <div class="batch-info-label">Batch Code</div>
                            <div class="batch-info-value"><?= htmlspecialchars($batch['batch_code']) ?></div>
                        </div>
                        <div class="batch-info-item">
                            <div class="batch-info-label">Product</div>
                            <div class="batch-info-value"><?= htmlspecialchars($batch['product_name']) ?></div>
                        </div>
                        <div class="batch-info-item">
                            <div class="batch-info-label">Expiry Date</div>
                            <div class="batch-info-value"><?= date('M d, Y', strtotime($batch['expiry_date'])) ?></div>
                        </div>
                    </div>
                    
                    <div class="cta-buttons">
                        <a href="<?= base_url('contact.php') ?>" class="cta-btn cta-primary">
                            <i class="fas fa-headset"></i> Contact Support
                        </a>
                        <a href="<?= base_url('report-suspicious.php?batch=' . urlencode($batch['batch_code'])) ?>" class="cta-btn cta-secondary">
                            <i class="fas fa-flag"></i> Report Suspicious Product
                        </a>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Valid Result -->
            <div class="result-card result-valid">
                <div class="text-center">
                    <div class="authenticity-badge">
                        <i class="fas fa-check-circle"></i>
                        ✅ Authenticity Verified
                    </div>
                    
                    <h2 class="mt-3 mb-4">Genuine Gilaf Product</h2>
                </div>
                
                <?php if ($batch['product_image']): ?>
                <div class="product-preview">
                    <img src="<?= base_url('uploads/products/' . $batch['product_image']) ?>" alt="<?= htmlspecialchars($batch['product_name']) ?>">
                    <div>
                        <h4><?= htmlspecialchars($batch['product_name']) ?></h4>
                        <p class="mb-0"><?= get_batch_status_badge($batch['status']) ?> <?= get_batch_optional_badges($batch) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="batch-info-grid">
                    <div class="batch-info-item">
                        <div class="batch-info-label">Batch Code</div>
                        <div class="batch-info-value"><?= htmlspecialchars($batch['batch_code']) ?></div>
                    </div>
                    
                    <div class="batch-info-item">
                        <div class="batch-info-label">Net Weight</div>
                        <div class="batch-info-value"><?= htmlspecialchars($batch['net_weight']) ?></div>
                    </div>
                    
                    <div class="batch-info-item">
                        <div class="batch-info-label">MRP</div>
                        <div class="batch-info-value">₹<?= number_format($batch['product_price'], 2) ?></div>
                    </div>
                    
                    <div class="batch-info-item">
                        <div class="batch-info-label">Manufacturing Date</div>
                        <div class="batch-info-value"><?= date('M d, Y', strtotime($batch['manufacturing_date'])) ?></div>
                    </div>
                    
                    <div class="batch-info-item">
                        <div class="batch-info-label">Expiry Date</div>
                        <div class="batch-info-value"><?= date('M d, Y', strtotime($batch['expiry_date'])) ?></div>
                    </div>
                    
                    <div class="batch-info-item">
                        <div class="batch-info-label">Origin</div>
                        <div class="batch-info-value"><?= htmlspecialchars($batch['country_of_origin']) ?></div>
                    </div>
                    
                    <?php if ($verificationResult['approver_name']): ?>
                    <div class="batch-info-item">
                        <div class="batch-info-label">Approved By</div>
                        <div class="batch-info-value"><?= htmlspecialchars($verificationResult['approver_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="batch-info-item">
                        <div class="batch-info-label">Verified On</div>
                        <div class="batch-info-value"><?= $verificationResult['verified_at'] ?></div>
                    </div>
                </div>
                
                <?php if (!empty($batch['lab_report_url'])): ?>
                <div class="lab-report-section">
                    <a href="<?= base_url('assets/lab_reports/' . $batch['lab_report_url']) ?>" 
                       target="_blank" 
                       class="lab-report-btn" 
                       download>
                        <i class="fas fa-file-pdf"></i>
                        <span>View Lab Report</span>
                        <i class="fas fa-download"></i>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php elseif ($error === 'not_found'): ?>
        <!-- Batch Not Found -->
        <div class="result-card result-invalid">
            <div class="text-center">
                <div class="warning-icon">❌</div>
                <h2 class="mb-3">Batch Not Found</h2>
                <p class="lead mb-4">
                    The batch code <strong><?= htmlspecialchars($batchCode) ?></strong> does not exist in our system.
                </p>
                <p>This could indicate a counterfeit product or an incorrect batch code.</p>
                
                <div class="cta-buttons">
                    <a href="<?= base_url('contact.php') ?>" class="cta-btn cta-primary">
                        <i class="fas fa-headset"></i> Contact Support
                    </a>
                    <a href="<?= base_url('report-suspicious.php?batch=' . urlencode($batchCode)) ?>" class="cta-btn cta-secondary">
                        <i class="fas fa-flag"></i> Report Suspicious Product
                    </a>
                </div>
            </div>
        </div>
        
    <?php elseif ($error === 'system_error'): ?>
        <!-- System Error -->
        <div class="result-card result-invalid">
            <div class="text-center">
                <div class="warning-icon">⚠️</div>
                <h2 class="mb-3">System Error</h2>
                <p class="lead">Unable to verify batch at this time. Please try again later.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="verification-footer">
    <p><strong>Note:</strong> This verification confirms product authenticity at the time of scan.</p>
    <p class="mt-2">
        <img src="<?= base_url('assets/images/logo.png') ?>" alt="Gilaf Store" style="height: 40px;">
    </p>
    <p class="mt-2">
        <a href="<?= base_url('contact.php') ?>">Customer Support</a> | 
        <a href="<?= base_url('privacy.php') ?>">Privacy Policy</a>
    </p>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
