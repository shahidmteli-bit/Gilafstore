<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_config.php';

require_admin();

$suggestionId = $_GET['id'] ?? 0;

if (!$suggestionId) {
    header('Location: suggestions_center.php');
    exit;
}

// Handle GET action for delete
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $adminId = $_SESSION['user']['id'];
    $adminName = $_SESSION['user']['name'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get suggestion details before deletion for audit
        $query = "SELECT * FROM suggestions WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $suggestionId);
        $stmt->execute();
        $suggestion = $stmt->get_result()->fetch_assoc();
        
        if (!$suggestion) {
            throw new Exception('Suggestion not found');
        }
        
        // Delete related records first (foreign key constraints)
        // Delete rewards
        $conn->query("DELETE FROM suggestion_rewards WHERE suggestion_id = {$suggestionId}");
        
        // Delete audit logs
        $conn->query("DELETE FROM suggestion_audit_log WHERE suggestion_id = {$suggestionId}");
        
        // Delete email logs
        $conn->query("DELETE FROM suggestion_email_log WHERE suggestion_id = {$suggestionId}");
        
        // Delete the suggestion
        $deleteQuery = "DELETE FROM suggestions WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param('i', $suggestionId);
        
        if ($deleteStmt->execute()) {
            $conn->commit();
            $_SESSION['flash_message'] = 'Suggestion deleted successfully!';
            $_SESSION['flash_type'] = 'success';
        } else {
            throw new Exception('Failed to delete suggestion');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_message'] = 'Error deleting suggestion: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        error_log("Suggestion deletion error: " . $e->getMessage());
    }
    
    header('Location: suggestions_center.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $adminId = $_SESSION['user']['id'];
    $adminName = $_SESSION['user']['name'];
    
    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['status'];
            $adminNotes = $_POST['admin_notes'] ?? '';
            $rejectionReason = $_POST['rejection_reason'] ?? null;
            
            $updateQuery = "UPDATE suggestions 
                           SET status = ?, reviewed_by = ?, reviewed_at = NOW(), 
                               admin_notes = ?, rejection_reason = ?
                           WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('sissi', $newStatus, $adminId, $adminNotes, $rejectionReason, $suggestionId);
            
            if ($stmt->execute()) {
                // Log audit
                $auditQuery = "INSERT INTO suggestion_audit_log 
                              (suggestion_id, action_type, old_value, new_value, performed_by, performed_by_name) 
                              VALUES (?, 'status_changed', ?, ?, ?, ?)";
                $auditStmt = $conn->prepare($auditQuery);
                $oldStatus = $_POST['old_status'];
                $auditStmt->bind_param('issss', $suggestionId, $oldStatus, $newStatus, $adminId, $adminName);
                $auditStmt->execute();
                
                // Send email notification
                sendStatusUpdateEmail($suggestionId, $newStatus, $rejectionReason);
                
                $_SESSION['success'] = 'Status updated successfully!';
            }
            break;
            
        case 'mark_best':
            $isBest = $_POST['is_best'] ? 1 : 0;
            $isBusinessImpact = $_POST['is_business_impact'] ? 1 : 0;
            
            $updateQuery = "UPDATE suggestions 
                           SET is_best_suggestion = ?, is_business_impact = ?
                           WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('iii', $isBest, $isBusinessImpact, $suggestionId);
            
            if ($stmt->execute()) {
                $auditQuery = "INSERT INTO suggestion_audit_log 
                              (suggestion_id, action_type, new_value, performed_by, performed_by_name) 
                              VALUES (?, 'updated', ?, ?, ?)";
                $auditStmt = $conn->prepare($auditQuery);
                $newValue = json_encode(['is_best' => $isBest, 'is_business_impact' => $isBusinessImpact]);
                $auditStmt->bind_param('isss', $suggestionId, $newValue, $adminId, $adminName);
                $auditStmt->execute();
                
                $_SESSION['success'] = 'Suggestion marked successfully!';
            }
            break;
            
        case 'add_reward':
            $rewardType = $_POST['reward_type'];
            $rewardValue = $_POST['reward_value'];
            $rewardDescription = $_POST['reward_description'];
            $rewardCode = $_POST['reward_code'] ?? null;
            $expiresAt = $_POST['expires_at'] ?? null;
            
            $insertQuery = "INSERT INTO suggestion_rewards 
                           (suggestion_id, reward_type, reward_value, reward_description, 
                            reward_code, expires_at, assigned_by, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('idssssi', $suggestionId, $rewardType, $rewardValue, 
                             $rewardDescription, $rewardCode, $expiresAt, $adminId);
            
            if ($stmt->execute()) {
                $auditQuery = "INSERT INTO suggestion_audit_log 
                              (suggestion_id, action_type, new_value, performed_by, performed_by_name) 
                              VALUES (?, 'rewarded', ?, ?, ?)";
                $auditStmt = $conn->prepare($auditQuery);
                $newValue = json_encode(['reward_type' => $rewardType, 'reward_value' => $rewardValue]);
                $auditStmt->bind_param('isss', $suggestionId, $newValue, $adminId, $adminName);
                $auditStmt->execute();
                
                // Send reward notification email
                sendRewardEmail($suggestionId, $conn->insert_id);
                
                $_SESSION['success'] = 'Reward added successfully!';
            }
            break;
    }
    
    header("Location: suggestion_manage.php?id={$suggestionId}");
    exit;
}

// Get suggestion details
$query = "SELECT s.*, 
          u.name as user_name, u.email as user_email,
          r.name as reviewer_name
          FROM suggestions s
          LEFT JOIN users u ON s.user_id = u.id
          LEFT JOIN users r ON s.reviewed_by = r.id
          WHERE s.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $suggestionId);
$stmt->execute();
$suggestion = $stmt->get_result()->fetch_assoc();

if (!$suggestion) {
    header('Location: suggestions_center.php');
    exit;
}

// Get rewards
$rewardsQuery = "SELECT sr.*, u.name as assigned_by_name 
                 FROM suggestion_rewards sr
                 LEFT JOIN users u ON sr.assigned_by = u.id
                 WHERE sr.suggestion_id = ?
                 ORDER BY sr.assigned_at DESC";
$rewardsStmt = $conn->prepare($rewardsQuery);
$rewardsStmt->bind_param('i', $suggestionId);
$rewardsStmt->execute();
$rewards = $rewardsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get audit log
$auditQuery = "SELECT * FROM suggestion_audit_log 
               WHERE suggestion_id = ? 
               ORDER BY performed_at DESC 
               LIMIT 20";
$auditStmt = $conn->prepare($auditQuery);
$auditStmt->bind_param('i', $suggestionId);
$auditStmt->execute();
$auditLog = $auditStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Manage Suggestion - ' . $suggestion['submission_id'];
$adminPage = 'suggestions';

// Email functions
function sendStatusUpdateEmail($suggestionId, $newStatus, $rejectionReason = null) {
    global $conn;
    
    $query = "SELECT * FROM suggestions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $suggestionId);
    $stmt->execute();
    $suggestion = $stmt->get_result()->fetch_assoc();
    
    if (!$suggestion || !$suggestion['user_email']) return;
    
    // Check if getMailer function exists
    if (!function_exists('getMailer')) {
        error_log("getMailer() function not available - skipping email notification");
        return;
    }
    
    try {
        $mail = getMailer();
        $mail->addAddress($suggestion['user_email'], $suggestion['user_name']);
        
        if ($newStatus === 'accepted') {
            $mail->Subject = 'Your Suggestion Has Been Accepted! - ' . $suggestion['submission_id'];
            $mail->Body = getAcceptedEmailTemplate($suggestion);
        } elseif ($newStatus === 'rejected') {
            $mail->Subject = 'Update on Your Suggestion - ' . $suggestion['submission_id'];
            $mail->Body = getRejectedEmailTemplate($suggestion, $rejectionReason);
        } else {
            $mail->Subject = 'Status Update - ' . $suggestion['submission_id'];
            $mail->Body = getStatusUpdateEmailTemplate($suggestion, $newStatus);
        }
        
        $mail->send();
        
        // Log email
        $logQuery = "INSERT INTO suggestion_email_log 
                    (suggestion_id, email_type, recipient_email, subject, status, sent_at) 
                    VALUES (?, ?, ?, ?, 'sent', NOW())";
        $logStmt = $conn->prepare($logQuery);
        $emailType = $newStatus === 'accepted' ? 'accepted' : ($newStatus === 'rejected' ? 'rejected' : 'reminder');
        $logStmt->bind_param('isss', $suggestionId, $emailType, $suggestion['user_email'], $mail->Subject);
        $logStmt->execute();
        
    } catch (Exception $e) {
        error_log("Failed to send email: " . $e->getMessage());
    }
}

function sendRewardEmail($suggestionId, $rewardId) {
    global $conn;
    
    $query = "SELECT s.*, sr.* 
              FROM suggestions s
              JOIN suggestion_rewards sr ON s.id = sr.suggestion_id
              WHERE s.id = ? AND sr.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ii', $suggestionId, $rewardId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    
    if (!$data || !$data['user_email']) return;
    
    // Check for duplicate email - prevent sending same reward email twice
    $checkQuery = "SELECT COUNT(*) as count FROM suggestion_email_log 
                   WHERE suggestion_id = ? AND email_type = 'reward' 
                   AND recipient_email = ? AND subject LIKE ?
                   AND sent_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $checkStmt = $conn->prepare($checkQuery);
    $subjectPattern = '%Reward%' . $data['submission_id'] . '%';
    $checkStmt->bind_param('iss', $suggestionId, $data['user_email'], $subjectPattern);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    
    if ($checkResult['count'] > 0) {
        error_log("Duplicate reward email prevented for suggestion ID: {$suggestionId}");
        return;
    }
    
    // Check if getMailer function exists
    if (!function_exists('getMailer')) {
        error_log("getMailer() function not available - skipping reward email notification");
        return;
    }
    
    try {
        $mail = getMailer();
        $mail->addAddress($data['user_email'], $data['user_name']);
        $mail->Subject = '🎁 Congratulations! You\'ve Received a Reward - ' . $data['submission_id'];
        $mail->Body = getRewardEmailTemplate($data);
        $mail->send();
        
        // Log successful email
        $logQuery = "INSERT INTO suggestion_email_log 
                    (suggestion_id, email_type, recipient_email, subject, status, sent_at) 
                    VALUES (?, 'reward', ?, ?, 'sent', NOW())";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('iss', $suggestionId, $data['user_email'], $mail->Subject);
        $logStmt->execute();
        
    } catch (Exception $e) {
        error_log("Failed to send reward email: " . $e->getMessage());
        
        // Log failed email
        $logQuery = "INSERT INTO suggestion_email_log 
                    (suggestion_id, email_type, recipient_email, subject, status, error_message, sent_at) 
                    VALUES (?, 'reward', ?, ?, 'failed', ?, NOW())";
        $logStmt = $conn->prepare($logQuery);
        $subject = '🎁 Reward Notification - ' . $data['submission_id'];
        $errorMsg = $e->getMessage();
        $logStmt->bind_param('isss', $suggestionId, $data['user_email'], $subject, $errorMsg);
        $logStmt->execute();
    }
}

function getAcceptedEmailTemplate($suggestion) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #198754 0%, #20c997 100%); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>✅ Suggestion Accepted!</h1>
        </div>
        <div style='padding: 30px; background: #f9f9f9;'>
            <p style='font-size: 16px; color: #333;'>Dear {$suggestion['user_name']},</p>
            <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                We are thrilled to inform you that your suggestion has been <strong>accepted</strong> 
                and will be considered for implementation!
            </p>
            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #198754;'>
                <h3 style='color: #198754; margin-top: 0;'>Your Suggestion</h3>
                <p style='margin: 5px 0;'><strong>ID:</strong> {$suggestion['submission_id']}</p>
                <p style='margin: 5px 0;'><strong>Subject:</strong> {$suggestion['subject']}</p>
            </div>
            <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                Your contribution is invaluable to our continuous improvement. Thank you for helping us build a better platform!
            </p>
            <p style='font-size: 14px; color: #666;'>Best regards,<br><strong>Gilaf Innovation Team</strong></p>
        </div>
    </div>
    ";
}

function getRejectedEmailTemplate($suggestion, $reason) {
    $reasonText = $reason ? "<p style='font-size: 14px; color: #666;'><strong>Reason:</strong> {$reason}</p>" : '';
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #6c757d 0%, #495057 100%); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>Update on Your Suggestion</h1>
        </div>
        <div style='padding: 30px; background: #f9f9f9;'>
            <p style='font-size: 16px; color: #333;'>Dear {$suggestion['user_name']},</p>
            <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                Thank you for your suggestion. After careful review, we have decided not to proceed with this particular idea at this time.
            </p>
            {$reasonText}
            <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                We truly appreciate your effort and encourage you to continue sharing your ideas with us. 
                Every suggestion helps us understand our users better.
            </p>
            <p style='font-size: 14px; color: #666;'>Best regards,<br><strong>Gilaf Team</strong></p>
        </div>
    </div>
    ";
}

function getStatusUpdateEmailTemplate($suggestion, $status) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #0dcaf0 0%, #0d6efd 100%); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>Status Update</h1>
        </div>
        <div style='padding: 30px; background: #f9f9f9;'>
            <p style='font-size: 16px; color: #333;'>Dear {$suggestion['user_name']},</p>
            <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                Your suggestion <strong>{$suggestion['submission_id']}</strong> status has been updated to: 
                <strong>" . ucwords(str_replace('_', ' ', $status)) . "</strong>
            </p>
            <p style='font-size: 14px; color: #666;'>Best regards,<br><strong>Gilaf Team</strong></p>
        </div>
    </div>
    ";
}

function getRewardEmailTemplate($data) {
    $expiryText = '';
    if (!empty($data['expires_at'])) {
        $expiryDate = date('F d, Y', strtotime($data['expires_at']));
        $expiryText = "<p style='margin: 5px 0; color: #dc3545;'><strong>⏰ Valid Until:</strong> {$expiryDate}</p>";
    }
    
    $couponSection = '';
    if (!empty($data['reward_code'])) {
        $couponSection = "
        <div style='background: linear-gradient(135deg, #fff9e6 0%, #ffe6b3 100%); padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; border: 2px dashed #C5A059;'>
            <p style='margin: 0 0 10px 0; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px;'>Your Coupon Code</p>
            <div style='background: white; padding: 15px 30px; border-radius: 6px; display: inline-block;'>
                <code style='font-size: 24px; font-weight: bold; color: #C5A059; letter-spacing: 2px;'>{$data['reward_code']}</code>
            </div>
            <p style='margin: 10px 0 0 0; font-size: 12px; color: #666;'>Copy this code to redeem your reward</p>
        </div>";
    }
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin: 0; padding: 0; background-color: #f5f5f5;'>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white;'>
            <!-- Header -->
            <div style='background: linear-gradient(135deg, #C5A059 0%, #d4b376 100%); padding: 40px 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🎁 Congratulations!</h1>
                <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 16px;'>You've Earned a Reward</p>
            </div>
            
            <!-- Content -->
            <div style='padding: 30px; background: #f9f9f9;'>
                <p style='font-size: 16px; color: #333; margin-top: 0;'>Dear {$data['user_name']},</p>
                
                <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                    🌟 <strong>Congratulations!</strong> Your outstanding suggestion has been recognized as a <strong>Best Idea</strong> 
                    and we're thrilled to reward your valuable contribution to our platform!
                </p>
                
                <!-- Reward Details Box -->
                <div style='background: white; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #C5A059; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                    <h3 style='color: #C5A059; margin-top: 0; margin-bottom: 15px; font-size: 18px;'>💰 Reward Details</h3>
                    
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #666; font-size: 14px;'><strong>Reward Type:</strong></td>
                            <td style='padding: 8px 0; color: #333; font-size: 14px; text-align: right;'>" . ucwords(str_replace('_', ' ', $data['reward_type'])) . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #666; font-size: 14px;'><strong>Reward Amount:</strong></td>
                            <td style='padding: 8px 0; color: #198754; font-size: 18px; font-weight: bold; text-align: right;'>₹{$data['reward_value']}</td>
                        </tr>
                        <tr>
                            <td colspan='2' style='padding: 12px 0 8px 0; color: #666; font-size: 14px;'><strong>Reason:</strong></td>
                        </tr>
                        <tr>
                            <td colspan='2' style='padding: 0 0 8px 0; color: #333; font-size: 14px; line-height: 1.5;'>{$data['reward_description']}</td>
                        </tr>
                    </table>
                    
                    {$expiryText}
                </div>
                
                {$couponSection}
                
                <!-- Redemption Instructions -->
                <div style='background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #0dcaf0;'>
                    <h4 style='color: #0a5a7a; margin-top: 0; margin-bottom: 12px; font-size: 16px;'>📋 How to Redeem Your Reward</h4>
                    <ol style='margin: 0; padding-left: 20px; color: #0a5a7a; font-size: 14px; line-height: 1.8;'>
                        <li>Log in to your Gilaf Store account</li>
                        <li>Add items to your cart</li>
                        <li>Proceed to checkout</li>
                        <li>Enter your coupon code in the 'Apply Coupon' field</li>
                        <li>Your discount will be applied automatically</li>
                    </ol>
                    <p style='margin: 15px 0 0 0; font-size: 13px; color: #0a5a7a;'>
                        <strong>Note:</strong> This reward can be used on your next purchase. Terms and conditions apply.
                    </p>
                </div>
                
                <!-- Suggestion Reference -->
                <div style='background: white; padding: 15px; border-radius: 8px; margin: 25px 0; border: 1px solid #e0e0e0;'>
                    <p style='margin: 0; font-size: 13px; color: #666;'>
                        <strong>Your Suggestion ID:</strong> {$data['submission_id']}<br>
                        <strong>Subject:</strong> {$data['subject']}
                    </p>
                </div>
                
                <!-- Footer Message -->
                <div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;'>
                    <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                        Thank you for being an active member of our community and helping us improve! 
                        Your ideas make a real difference. 🚀
                    </p>
                    <p style='font-size: 14px; color: #666; margin-bottom: 0;'>
                        Best regards,<br>
                        <strong style='color: #C5A059;'>Gilaf Innovation Team</strong>
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style='background: #333; padding: 20px; text-align: center;'>
                <p style='margin: 0; font-size: 12px; color: #999;'>
                    This is an automated email. Please do not reply to this message.<br>
                    © 2026 Gilaf Store. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="suggestions_center.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Suggestions
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Suggestion Details Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1"><?= htmlspecialchars($suggestion['subject']); ?></h5>
                            <div class="d-flex gap-2 mt-2">
                                <span class="badge bg-secondary"><?= $suggestion['submission_id']; ?></span>
                                <span class="badge bg-light text-dark"><?= $suggestion['category']; ?></span>
                                <?php if ($suggestion['is_best_suggestion']): ?>
                                <span class="badge" style="background: #C5A059;">
                                    <i class="fas fa-trophy me-1"></i>Best Suggestion
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge bg-<?= ['new' => 'info', 'under_review' => 'warning', 'accepted' => 'success', 'rejected' => 'danger', 'implemented' => 'primary'][$suggestion['status']] ?? 'secondary'; ?> fs-6">
                            <?= ucwords(str_replace('_', ' ', $suggestion['status'])); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <h6 class="text-muted mb-3">Description</h6>
                    <p class="mb-4" style="white-space: pre-wrap;"><?= htmlspecialchars($suggestion['description']); ?></p>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Submitted By</h6>
                            <p class="mb-0">
                                <?php if ($suggestion['is_guest']): ?>
                                <i class="fas fa-user text-muted me-2"></i><?= htmlspecialchars($suggestion['user_name']); ?>
                                <?php else: ?>
                                <i class="fas fa-user-check text-success me-2"></i><?= htmlspecialchars($suggestion['user_name'] ?? 'User'); ?>
                                <?php endif; ?>
                            </p>
                            <small class="text-muted"><?= htmlspecialchars($suggestion['user_email']); ?></small>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Submission Date</h6>
                            <p class="mb-0"><?= date('F d, Y \a\t h:i A', strtotime($suggestion['submitted_at'])); ?></p>
                            <small class="text-muted">Source: <?= ucfirst($suggestion['source']); ?></small>
                        </div>
                    </div>
                    
                    <?php if ($suggestion['reviewed_by']): ?>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Reviewed By</h6>
                            <p class="mb-0"><?= htmlspecialchars($suggestion['reviewer_name']); ?></p>
                            <small class="text-muted"><?= date('F d, Y', strtotime($suggestion['reviewed_at'])); ?></small>
                        </div>
                        <?php if ($suggestion['admin_notes']): ?>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Admin Notes</h6>
                            <p class="mb-0 small"><?= htmlspecialchars($suggestion['admin_notes']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rewards Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-gift text-warning me-2"></i>Rewards</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRewardModal">
                            <i class="fas fa-plus me-1"></i>Add Reward
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($rewards)): ?>
                    <p class="text-muted text-center py-3 mb-0">No rewards assigned yet</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($rewards as $reward): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= ucwords(str_replace('_', ' ', $reward['reward_type'])); ?></h6>
                                    <p class="mb-1">₹<?= number_format($reward['reward_value'], 2); ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($reward['reward_description']); ?></small>
                                    <?php if ($reward['reward_code']): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-light text-dark">Code: <?= htmlspecialchars($reward['reward_code']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?= ['pending' => 'warning', 'issued' => 'success', 'claimed' => 'primary', 'expired' => 'secondary'][$reward['status']] ?? 'secondary'; ?>">
                                    <?= ucfirst($reward['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Audit Log -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0"><i class="fas fa-history text-info me-2"></i>Activity Log</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($auditLog)): ?>
                    <p class="text-muted text-center py-3 mb-0">No activity yet</p>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($auditLog as $log): ?>
                        <div class="timeline-item mb-3">
                            <div class="d-flex">
                                <div class="me-3">
                                    <i class="fas fa-circle text-primary" style="font-size: 8px;"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= ucwords(str_replace('_', ' ', $log['action_type'])); ?></strong>
                                        <small class="text-muted"><?= date('M d, Y h:i A', strtotime($log['performed_at'])); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        by <?= htmlspecialchars($log['performed_by_name'] ?? 'System'); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="col-lg-4">
            <!-- Status Management -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Manage Status</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="old_status" value="<?= $suggestion['status']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="new" <?= $suggestion['status'] === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="under_review" <?= $suggestion['status'] === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="accepted" <?= $suggestion['status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="rejected" <?= $suggestion['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="implemented" <?= $suggestion['status'] === 'implemented' ? 'selected' : ''; ?>>Implemented</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Admin Notes</label>
                            <textarea name="admin_notes" class="form-control" rows="3"><?= htmlspecialchars($suggestion['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3" id="rejectionReasonDiv" style="display: none;">
                            <label class="form-label fw-semibold">Rejection Reason</label>
                            <textarea name="rejection_reason" class="form-control" rows="2"><?= htmlspecialchars($suggestion['rejection_reason'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </form>
                </div>
            </div>

            <!-- Mark as Best -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Recognition</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="mark_best">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_best" value="1" id="isBest" 
                                   <?= $suggestion['is_best_suggestion'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isBest">
                                <i class="fas fa-trophy text-warning me-1"></i>Mark as Best Suggestion
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_business_impact" value="1" id="isBusinessImpact"
                                   <?= $suggestion['is_business_impact'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isBusinessImpact">
                                <i class="fas fa-chart-line text-success me-1"></i>Business Impact Idea
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-check me-2"></i>Save Recognition
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Reward Modal -->
<div class="modal fade" id="addRewardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Reward</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_reward">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reward Type</label>
                        <select name="reward_type" class="form-select" required>
                            <option value="coupon">Coupon</option>
                            <option value="cashback">Cashback</option>
                            <option value="voucher">Voucher</option>
                            <option value="discount">Discount</option>
                            <option value="points">Points</option>
                            <option value="physical_gift">Physical Gift</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reward Value (₹)</label>
                        <input type="number" name="reward_value" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="reward_description" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reward Code (Optional)</label>
                        <input type="text" name="reward_code" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expires At (Optional)</label>
                        <input type="date" name="expires_at" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Reward</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide rejection reason based on status
document.querySelector('select[name="status"]').addEventListener('change', function() {
    const rejectionDiv = document.getElementById('rejectionReasonDiv');
    if (this.value === 'rejected') {
        rejectionDiv.style.display = 'block';
    } else {
        rejectionDiv.style.display = 'none';
    }
});

// Trigger on page load
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.querySelector('select[name="status"]');
    if (statusSelect.value === 'rejected') {
        document.getElementById('rejectionReasonDiv').style.display = 'block';
    }
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
