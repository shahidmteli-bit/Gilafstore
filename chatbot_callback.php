<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = sanitize_input($data['name'] ?? '');
    $phone = sanitize_input($data['phone'] ?? '');
    $preferred_time = sanitize_input($data['preferred_time'] ?? '');
    $message = sanitize_input($data['message'] ?? '');
    
    // Validate inputs
    if (empty($name) || empty($phone)) {
        echo json_encode([
            'success' => false,
            'message' => 'Name and phone number are required.'
        ]);
        exit;
    }
    
    // Validate phone number (basic validation)
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a valid 10-digit phone number.'
        ]);
        exit;
    }
    
    try {
        // Insert callback request into database
        $stmt = $conn->prepare("
            INSERT INTO callback_requests (name, phone, preferred_time, message, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->bind_param("ssss", $name, $phone, $preferred_time, $message);
        
        if ($stmt->execute()) {
            // Send notification email to admin (optional)
            $admin_email = "support@gilaf.com"; // Update with actual admin email
            $subject = "New Callback Request - Gilaf Support";
            $email_message = "
                New callback request received:\n\n
                Name: $name\n
                Phone: $phone\n
                Preferred Time: $preferred_time\n
                Message: $message\n
                Requested At: " . date('Y-m-d H:i:s') . "\n
            ";
            
            @mail($admin_email, $subject, $email_message);
            
            echo json_encode([
                'success' => true,
                'message' => 'Callback request submitted successfully! Our team will contact you soon.'
            ]);
        } else {
            throw new Exception('Failed to save callback request');
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again or contact us directly.'
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>
