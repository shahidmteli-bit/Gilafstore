<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/email_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$category = trim($_POST['category'] ?? '');
$customCategory = trim($_POST['custom_category'] ?? '');
$description = trim($_POST['description'] ?? '');
$subject = trim($_POST['subject'] ?? ''); // Get auto-generated subject
$userEmail = trim($_POST['user_email'] ?? '');
$userName = trim($_POST['user_name'] ?? '');
$source = trim($_POST['source'] ?? 'website');

// Use custom category if "Other" is selected
if ($category === 'Other' && !empty($customCategory)) {
    $category = $customCategory;
}

// Fallback: Generate subject if not provided
if (empty($subject)) {
    require_once __DIR__ . '/includes/subject_generator.php';
    $subject = SubjectGenerator::generateAdvanced($description, $category);
}

// Validation
$errors = [];

if (empty($category)) {
    $errors[] = 'Please select a category';
}

if ($_POST['category'] === 'Other' && empty($customCategory)) {
    $errors[] = 'Please specify a custom category';
}

if (empty($description) || strlen($description) < 50) {
    $errors[] = 'Description must be at least 50 characters';
}

// Check if guest submission
$userId = $_SESSION['user']['id'] ?? null;
$isGuest = empty($userId);

if ($isGuest) {
    if (empty($userEmail) || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required for guest submissions';
    }
    if (empty($userName)) {
        $errors[] = 'Name is required for guest submissions';
    }
} else {
    // Get user details from session
    $userEmail = $_SESSION['user']['email'] ?? $userEmail;
    $userName = $_SESSION['user']['name'] ?? $userName;
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Rate limiting - check submissions per user for current calendar day
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Debug logging
error_log("Rate Limit Check - isGuest: " . ($isGuest ? 'true' : 'false'));
error_log("Rate Limit Check - userId: " . ($userId ?? 'null'));
error_log("Rate Limit Check - userEmail: " . $userEmail);

if ($isGuest) {
    // For guest users: track by email for current calendar day
    $rateLimitQuery = "SELECT COUNT(*) as count FROM suggestions 
                       WHERE user_email = ? 
                       AND DATE(submitted_at) = CURDATE()";
    $stmt = $conn->prepare($rateLimitQuery);
    $stmt->bind_param('s', $userEmail);
    error_log("Rate Limit Query (Guest): Checking email = " . $userEmail);
} else {
    // For logged-in users: track by user_id for current calendar day
    $rateLimitQuery = "SELECT COUNT(*) as count FROM suggestions 
                       WHERE user_id = ? 
                       AND DATE(submitted_at) = CURDATE()";
    $stmt = $conn->prepare($rateLimitQuery);
    $stmt->bind_param('i', $userId);
    error_log("Rate Limit Query (Logged-in): Checking user_id = " . $userId);
}

$stmt->execute();
$rateLimitResult = $stmt->get_result()->fetch_assoc();
$currentCount = $rateLimitResult['count'];
error_log("Rate Limit Check - Current submission count: " . $currentCount);

if ($currentCount >= 5) {
    error_log("Rate Limit BLOCKED - User has " . $currentCount . " submissions today");
    echo json_encode([
        'success' => false, 
        'message' => 'You have reached the maximum number of submissions for today. Please try again tomorrow.'
    ]);
    exit;
}

error_log("Rate Limit PASSED - User has " . $currentCount . " submissions today, allowing submission");

// Generate unique submission ID in DDMMYY-XXX format
$datePrefix = date('dmy'); // e.g., 010126 for Jan 01, 2026
$countQuery = "SELECT COUNT(*) as count FROM suggestions WHERE DATE(submitted_at) = CURDATE()";
$countResult = $conn->query($countQuery)->fetch_assoc();
$nextNumber = str_pad($countResult['count'] + 1, 3, '0', STR_PAD_LEFT);
$submissionId = "{$datePrefix}-{$nextNumber}";

// Insert suggestion
$insertQuery = "INSERT INTO suggestions 
                (submission_id, subject, category, description, user_id, user_email, user_name, 
                 is_guest, ip_address, user_agent, source, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')";

$stmt = $conn->prepare($insertQuery);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$stmt->bind_param('ssssissssss', 
    $submissionId, $subject, $category, $description, $userId, 
    $userEmail, $userName, $isGuest, $ipAddress, $userAgent, $source
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to submit suggestion. Please try again.']);
    exit;
}

$suggestionId = $conn->insert_id;

// Log audit trail
$auditQuery = "INSERT INTO suggestion_audit_log 
               (suggestion_id, action_type, new_value, performed_by_name, ip_address) 
               VALUES (?, 'created', ?, ?, ?)";
$auditStmt = $conn->prepare($auditQuery);
$newValue = json_encode(['subject' => $subject, 'category' => $category]);
$auditStmt->bind_param('isss', $suggestionId, $newValue, $userName, $ipAddress);
$auditStmt->execute();

// Send acknowledgment email (skip if email functions not available)
try {
    if (function_exists('getMailer')) {
        error_log("Attempting to send acknowledgment email to: {$userEmail}");
        $mail = getMailer();
        $mail->addAddress($userEmail, $userName);
        $mail->Subject = 'Thank You for Your Suggestion - ' . $submissionId;
    
    $emailBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>Thank You for Your Suggestion!</h1>
        </div>
        
        <div style='padding: 30px; background: #f9f9f9;'>
            <p style='font-size: 16px; color: #333;'>Dear {$userName},</p>
            
            <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                Thank you for taking the time to share your valuable suggestion with us. 
                We truly appreciate your contribution to making our platform better.
            </p>
            
            <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='color: #1A3C34; margin-top: 0;'>Submission Details</h3>
                <p style='margin: 5px 0;'><strong>Tracking ID:</strong> {$submissionId}</p>
                <p style='margin: 5px 0;'><strong>Subject:</strong> {$subject}</p>
                <p style='margin: 5px 0;'><strong>Category:</strong> {$category}</p>
                <p style='margin: 5px 0;'><strong>Status:</strong> Under Review</p>
            </div>
            
            <p style='font-size: 14px; color: #666; line-height: 1.6;'>
                Our team will carefully review your suggestion and get back to you soon. 
                You can use the tracking ID <strong>{$submissionId}</strong> to reference this submission.
            </p>
            
            <p style='font-size: 14px; color: #666;'>
                Best regards,<br>
                <strong>Gilaf Innovation Team</strong>
            </p>
        </div>
        
        <div style='background: #1A3C34; padding: 20px; text-align: center;'>
            <p style='color: #C5A059; margin: 0; font-size: 12px;'>
                © " . date('Y') . " Gilaf. All rights reserved.
            </p>
        </div>
    </div>
    ";
    
        $mail->Body = $emailBody;
        
        if ($mail->send()) {
            error_log("SUCCESS: Acknowledgment email sent to {$userEmail}");
            
            // Log email sent
            $emailLogQuery = "INSERT INTO suggestion_email_log 
                              (suggestion_id, email_type, recipient_email, subject, status, sent_at) 
                              VALUES (?, 'acknowledgment', ?, ?, 'sent', NOW())";
            $emailLogStmt = $conn->prepare($emailLogQuery);
            $emailSubject = 'Thank You for Your Suggestion - ' . $submissionId;
            $emailLogStmt->bind_param('iss', $suggestionId, $userEmail, $emailSubject);
            $emailLogStmt->execute();
        } else {
            error_log("FAILED: Could not send acknowledgment email to {$userEmail}. Error: " . $mail->ErrorInfo);
            
            // Log email failure
            $emailLogQuery = "INSERT INTO suggestion_email_log 
                              (suggestion_id, email_type, recipient_email, subject, status, error_message, sent_at) 
                              VALUES (?, 'acknowledgment', ?, ?, 'failed', ?, NOW())";
            $emailLogStmt = $conn->prepare($emailLogQuery);
            $emailSubject = 'Thank You for Your Suggestion - ' . $submissionId;
            $errorMsg = $mail->ErrorInfo;
            $emailLogStmt->bind_param('issss', $suggestionId, $userEmail, $emailSubject, $errorMsg);
            $emailLogStmt->execute();
        }
    } else {
        error_log("WARNING: getMailer() function not available - skipping acknowledgment email");
    }
} catch (Exception $e) {
    error_log("EXCEPTION: Email sending failed - " . $e->getMessage());
    
    // Log email failure
    if (function_exists('getMailer')) {
        $emailLogQuery = "INSERT INTO suggestion_email_log 
                          (suggestion_id, email_type, recipient_email, subject, status, error_message, sent_at) 
                          VALUES (?, 'acknowledgment', ?, ?, 'failed', ?, NOW())";
        $emailLogStmt = $conn->prepare($emailLogQuery);
        $emailSubject = 'Thank You for Your Suggestion - ' . $submissionId;
        $errorMsg = $e->getMessage();
        $emailLogStmt->bind_param('issss', $suggestionId, $userEmail, $emailSubject, $errorMsg);
        $emailLogStmt->execute();
    }
}

// Success response
echo json_encode([
    'success' => true,
    'message' => 'Thank you! Your suggestion has been submitted successfully.',
    'submission_id' => $submissionId,
    'data' => [
        'id' => $suggestionId,
        'submission_id' => $submissionId,
        'subject' => $subject,
        'category' => $category
    ]
]);
