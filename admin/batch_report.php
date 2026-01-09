<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$batchId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'view';

if (!$batchId) {
    die('Invalid batch ID');
}

$db = get_db_connection();
$stmt = $db->prepare("SELECT * FROM batch_codes WHERE id = ?");
$stmt->execute([$batchId]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die('Batch code not found');
}

// Set headers for download
if ($action === 'download') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="batch_report_' . $batch['batch_code'] . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Batch Report - <?= htmlspecialchars($batch['batch_code']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .report-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2d5016;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2d5016;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        .info-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #2d5016;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-active { background: #28a745; color: white; }
        .status-paused { background: #ffc107; color: #333; }
        .status-blocked { background: #dc3545; color: white; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .qr-section {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        @media print {
            body { background: white; margin: 0; }
            .report-container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <h1>Gilaf Store</h1>
            <p>Product Authenticity Batch Report</p>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Batch Code</div>
                <div class="info-value"><strong><?= htmlspecialchars($batch['batch_code']); ?></strong></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Status</div>
                <div class="info-value">
                    <?php if ($batch['is_active'] == 1): ?>
                        <span class="status-badge status-active">Active</span>
                    <?php elseif ($batch['is_active'] == 2): ?>
                        <span class="status-badge status-paused">Paused</span>
                    <?php else: ?>
                        <span class="status-badge status-blocked">Blocked</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Product Name</div>
                <div class="info-value"><?= htmlspecialchars($batch['product_name']); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Grade</div>
                <div class="info-value"><?= htmlspecialchars($batch['grade'] ?: 'N/A'); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Net Weight</div>
                <div class="info-value"><?= htmlspecialchars($batch['net_weight']); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Manufacturing Date</div>
                <div class="info-value"><?= date('F d, Y', strtotime($batch['manufacturing_date'])); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Expiry Date</div>
                <div class="info-value"><?= date('F d, Y', strtotime($batch['expiry_date'])); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Country of Origin</div>
                <div class="info-value"><?= htmlspecialchars($batch['country_of_origin']); ?></div>
            </div>
        </div>
        
        <?php if ($batch['lab_report_url']): ?>
        <div class="info-item" style="grid-column: 1 / -1;">
            <div class="info-label">Lab Report</div>
            <div class="info-value">
                <a href="<?= htmlspecialchars($batch['lab_report_url']); ?>" target="_blank">View Lab Report</a>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="qr-section">
            <div class="info-label">Verification Link</div>
            <p><?= base_url('verify.php?code=' . urlencode($batch['batch_code'])); ?></p>
        </div>
        
        <div class="footer">
            <p>Generated on <?= date('F d, Y \a\t g:i A'); ?></p>
            <p>&copy; <?= date('Y'); ?> Gilaf Store. All rights reserved.</p>
        </div>
    </div>
    
    <?php if ($action === 'download'): ?>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
    <?php endif; ?>
</body>
</html>
