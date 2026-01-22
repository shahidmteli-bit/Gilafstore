<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Auto-create callback_requests table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS callback_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    preferred_time VARCHAR(100),
    message TEXT,
    status ENUM('pending', 'contacted', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    contacted_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            // Log successful insertion for debugging
            error_log("Callback request saved: Name=$name, Phone=$phone, ID=" . $conn->insert_id);
            
            // Also insert into live_chat_call_requests for admin live chat panel
            $conn->query("INSERT INTO live_chat_call_requests (customer_name, customer_phone, status, created_at) 
                          VALUES ('$name', '$phone', 'pending', NOW())");
            
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
