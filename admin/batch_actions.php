<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$db = get_db_connection();
$action = $_POST['action'] ?? '';

function handle_batch_lab_report_upload(array $file): ?string
{
    if (empty($file['name'])) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Lab report upload failed');
    }

    // Check file type (PDF or JPEG)
    $allowedTypes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg'
    ];
    
    if (!isset($allowedTypes[$file['type']])) {
        throw new RuntimeException('Lab report must be a PDF or JPEG file');
    }

    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Lab report file size must be less than 5MB');
    }

    $extension = $allowedTypes[$file['type']];
    $filename = uniqid('batch_lab_', true) . '.' . $extension;
    $uploadDir = __DIR__ . '/../assets/lab_reports/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new RuntimeException('Unable to create lab reports directory');
        }
        chmod($uploadDir, 0777);
    }

    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save lab report file');
    }
    
    chmod($destination, 0644);

    return $filename;
}

try {
    switch ($action) {
        case 'create':
            $batchCode = trim($_POST['batch_code'] ?? '');
            $productId = (int)($_POST['product_id'] ?? 0);
            $productName = trim($_POST['product_name'] ?? '');
            $netWeight = trim($_POST['net_weight'] ?? '');
            $mfgDate = $_POST['manufacturing_date'] ?? '';
            $expDate = $_POST['expiry_date'] ?? '';
            $shift = trim($_POST['shift'] ?? '');
            $country = trim($_POST['country_of_origin'] ?? 'India (Pampore, Kashmir)');
            $labReportUrl = trim($_POST['lab_report_url'] ?? '');
            $hasLabReport = isset($_POST['has_lab_report']) ? 1 : 0;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($batchCode) || empty($productName) || empty($netWeight) || empty($mfgDate) || empty($expDate)) {
                throw new Exception('Please fill in all required fields.');
            }
            
            // Check if batch code already exists
            $stmt = $db->prepare("SELECT id FROM batch_codes WHERE batch_code = :code");
            $stmt->execute([':code' => $batchCode]);
            if ($stmt->fetch()) {
                throw new Exception('Batch code already exists. Please use a unique code.');
            }
            
            // Handle lab report file upload
            $labReportFile = null;
            if ($hasLabReport && !empty($_FILES['lab_report_file']['name'])) {
                $labReportFile = handle_batch_lab_report_upload($_FILES['lab_report_file']);
            }
            
            // Use file if uploaded, otherwise use URL
            $finalLabReport = $labReportFile ? $labReportFile : $labReportUrl;
            
            // Get lifecycle fields
            $status = trim($_POST['status'] ?? 'production');
            $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $totalUnits = !empty($_POST['total_units_manufactured']) ? (int)$_POST['total_units_manufactured'] : 0;
            $unitsSold = !empty($_POST['units_sold']) ? (int)$_POST['units_sold'] : 0;
            $unitsRemaining = $totalUnits - $unitsSold;
            $isLabTested = isset($_POST['is_lab_tested']) ? 1 : 0;
            $isOrganic = isset($_POST['is_organic']) ? 1 : 0;
            
            // Officer names and validation
            $approvalOfficerName = trim($_POST['approval_officer_name'] ?? '');
            $releaseOfficerName = trim($_POST['release_officer_name'] ?? '');
            
            // Validate officer names based on status
            if ($status === 'quality_approved' && empty($approvalOfficerName)) {
                throw new Exception('Approval Officer Name is required when status is Quality Approved');
            }
            if ($status === 'released_for_sale' && empty($releaseOfficerName)) {
                throw new Exception('Release Officer Name is required when status is Released for Sale');
            }
            
            // Auto-activate batch when Released for Sale
            if ($status === 'released_for_sale') {
                $isActive = 1;
            }
            
            // Set quality approval fields if status is quality_approved
            $qualityApproved = ($status === 'quality_approved' || $status === 'released_for_sale') ? 1 : 0;
            $qualityApprovedAt = $qualityApproved ? date('Y-m-d H:i:s') : null;
            $adminId = $_SESSION['user']['id'] ?? null;
            
            // Set release fields if status is released_for_sale
            $releasedForSale = ($status === 'released_for_sale') ? 1 : 0;
            $releasedAt = $releasedForSale ? date('Y-m-d H:i:s') : null;
            
            $stmt = $db->prepare("
                INSERT INTO batch_codes 
                (batch_code, product_id, product_name, net_weight, manufacturing_date, expiry_date, shift, country_of_origin, 
                 lab_report_url, is_active, status, category_id, total_units_manufactured, units_sold, units_remaining, 
                 is_lab_tested, is_organic, quality_approved, quality_approver_id, quality_approved_at, 
                 released_for_sale, releaser_id, released_at, created_at) 
                VALUES 
                (:batch_code, :product_id, :product_name, :net_weight, :mfg_date, :exp_date, :shift, :country, 
                 :lab_report, :is_active, :status, :category_id, :total_units, :units_sold, :units_remaining, 
                 :is_lab_tested, :is_organic, :quality_approved, :quality_approver_id, :quality_approved_at,
                 :released_for_sale, :releaser_id, :released_at, NOW())
            ");
            
            $stmt->execute([
                ':batch_code' => $batchCode,
                ':product_id' => $productId,
                ':product_name' => $productName,
                ':net_weight' => $netWeight,
                ':mfg_date' => $mfgDate,
                ':exp_date' => $expDate,
                ':shift' => $shift,
                ':country' => $country,
                ':lab_report' => $finalLabReport,
                ':is_active' => $isActive,
                ':status' => $status,
                ':category_id' => $categoryId,
                ':total_units' => $totalUnits,
                ':units_sold' => $unitsSold,
                ':units_remaining' => $unitsRemaining,
                ':is_lab_tested' => $isLabTested,
                ':is_organic' => $isOrganic,
                ':quality_approved' => $qualityApproved,
                ':quality_approver_id' => $adminId,
                ':quality_approved_at' => $qualityApprovedAt,
                ':released_for_sale' => $releasedForSale,
                ':releaser_id' => $adminId,
                ':released_at' => $releasedAt
            ]);
            
            // Log audit trail
            $batchId = $db->lastInsertId();
            $adminId = $_SESSION['user']['id'] ?? 0;
            $adminName = $_SESSION['user']['name'] ?? 'Admin';
            
            require_once __DIR__ . '/../includes/batch_functions.php';
            
            $auditDetails = [
                'product_name' => $productName,
                'total_units' => $totalUnits,
                'is_lab_tested' => $isLabTested,
                'is_organic' => $isOrganic
            ];
            
            // Add officer names to audit trail
            if ($status === 'quality_approved' && $approvalOfficerName) {
                $auditDetails['approval_officer'] = $approvalOfficerName;
            }
            if ($status === 'released_for_sale' && $releaseOfficerName) {
                $auditDetails['release_officer'] = $releaseOfficerName;
                $auditDetails['auto_activated'] = true;
            }
            
            log_batch_audit($batchId, $batchCode, 'batch_created', null, $status, $adminId, $adminName, $auditDetails);
            
            redirect_with_message('admin/manage_batches.php', 'Batch code generated successfully!', 'success');
            break;
            
        case 'update':
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $productName = trim($_POST['product_name'] ?? '');
            $grade = trim($_POST['grade'] ?? '');
            $netWeight = trim($_POST['net_weight'] ?? '');
            $mfgDate = $_POST['manufacturing_date'] ?? '';
            $expDate = $_POST['expiry_date'] ?? '';
            $country = trim($_POST['country_of_origin'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if ($batchId <= 0 || empty($productName) || empty($netWeight) || empty($mfgDate) || empty($expDate)) {
                throw new Exception('Please fill in all required fields.');
            }
            
            $stmt = $db->prepare("
                UPDATE batch_codes 
                SET product_name = :product_name, 
                    grade = :grade, 
                    net_weight = :net_weight, 
                    manufacturing_date = :mfg_date, 
                    expiry_date = :exp_date, 
                    country_of_origin = :country, 
                    is_active = :is_active 
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':product_name' => $productName,
                ':grade' => $grade,
                ':net_weight' => $netWeight,
                ':mfg_date' => $mfgDate,
                ':exp_date' => $expDate,
                ':country' => $country,
                ':is_active' => $isActive,
                ':id' => $batchId
            ]);
            
            redirect_with_message('admin/manage_batches.php', 'Batch code updated successfully!', 'success');
            break;
            
        case 'update_status':
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 1);
            
            if (!$batchId) {
                throw new Exception('Invalid batch ID');
            }
            
            // Validate status (0 = Blocked, 1 = Active, 2 = Paused)
            if (!in_array($status, [0, 1, 2])) {
                throw new Exception('Invalid status value');
            }
            
            $stmt = $db->prepare("UPDATE batch_codes SET is_active = :status WHERE id = :id");
            $stmt->execute([
                ':status' => $status,
                ':id' => $batchId
            ]);
            
            $statusText = ['Blocked', 'Active', 'Paused'][$status];
            redirect_with_message('admin/manage_batches.php', "Batch code status updated to: $statusText", 'success');
            break;
            
        case 'delete':
            $batchId = (int)($_POST['batch_id'] ?? 0);
            
            if ($batchId <= 0) {
                throw new Exception('Invalid batch ID.');
            }
            
            $stmt = $db->prepare("DELETE FROM batch_codes WHERE id = :id");
            $stmt->execute([':id' => $batchId]);
            
            redirect_with_message('admin/manage_batches.php', 'Batch code deleted successfully!', 'success');
            break;
            
        default:
            throw new Exception('Invalid action.');
    }
} catch (Exception $e) {
    redirect_with_message('admin/manage_batches.php', $e->getMessage(), 'error');
}
