<?php
/**
 * Live Chat User API - Frontend endpoints for user chatbot
 */

session_start();
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Auto-create tables if they don't exist
$conn->query("CREATE TABLE IF NOT EXISTS live_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    customer_id INT NULL,
    customer_name VARCHAR(100) DEFAULT 'Guest',
    customer_email VARCHAR(100),
    customer_phone VARCHAR(20),
    status ENUM('bot', 'waiting', 'active', 'closed') DEFAULT 'bot',
    assigned_admin_id INT NULL,
    assigned_admin_name VARCHAR(100),
    is_online TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    INDEX idx_session (session_id),
    INDEX idx_status (status),
    INDEX idx_admin (assigned_admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS live_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender_type ENUM('user', 'admin', 'bot', 'system') NOT NULL,
    sender_id INT NULL,
    sender_name VARCHAR(100),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat (chat_id),
    INDEX idx_sender (sender_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS live_chat_call_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(100),
    status ENUM('pending', 'connected', 'completed', 'cancelled') DEFAULT 'pending',
    connected_admin_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    connected_at DATETIME NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get or create session ID for this chat
function getChatSessionId() {
    if (!isset($_SESSION['live_chat_session'])) {
        $_SESSION['live_chat_session'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['live_chat_session'];
}

// Get user info if logged in
function getUserInfo() {
    global $conn;
    
    $user = [
        'id' => $_SESSION['user']['id'] ?? null,
        'name' => $_SESSION['user']['name'] ?? null,
        'email' => $_SESSION['user']['email'] ?? null,
        'phone' => $_SESSION['user']['phone'] ?? null
    ];
    
    // If logged in, try to get more info from database
    if ($user['id']) {
        $result = $conn->query("SELECT name, email, phone FROM users WHERE id = " . intval($user['id']));
        if ($result && $row = $result->fetch_assoc()) {
            $user['name'] = $row['name'];
            $user['email'] = $row['email'];
            $user['phone'] = $row['phone'];
        }
    }
    
    return $user;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_session':
            getSession($conn);
            break;
            
        case 'get_messages':
            getMessages($conn);
            break;
            
        case 'poll':
            $last_id = intval($_GET['last_id'] ?? 0);
            pollMessages($conn, $last_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'start_chat':
            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            startChat($conn, $name, $email);
            break;
            
        case 'send_message':
            $message = trim($data['message'] ?? '');
            sendUserMessage($conn, $message);
            break;
            
        case 'request_agent':
            requestAgent($conn);
            break;
            
        case 'request_call':
            $name = trim($data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');
            requestCall($conn, $name, $phone);
            break;
            
        case 'end_chat':
            endChat($conn);
            break;
            
        case 'update_online':
            updateOnlineStatus($conn);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// ============ API Functions ============

function getSession($conn) {
    $session_id = getChatSessionId();
    $user = getUserInfo();
    
    // Check if chat exists
    $session_escaped = $conn->real_escape_string($session_id);
    $result = $conn->query("SELECT * FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    
    $chat = null;
    if ($result && $result->num_rows > 0) {
        $chat = $result->fetch_assoc();
    }
    
    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'user' => $user,
        'chat' => $chat
    ]);
}

function getMessages($conn) {
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    
    // Get chat
    $chat_result = $conn->query("SELECT id FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    if (!$chat_result || $chat_result->num_rows === 0) {
        echo json_encode(['success' => true, 'messages' => []]);
        return;
    }
    
    $chat = $chat_result->fetch_assoc();
    $chat_id = $chat['id'];
    
    // Get messages
    $messages = [];
    $msg_result = $conn->query("SELECT * FROM live_chat_messages WHERE chat_id = $chat_id ORDER BY created_at ASC");
    if ($msg_result) {
        while ($row = $msg_result->fetch_assoc()) {
            $messages[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}

function pollMessages($conn, $last_id) {
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    
    // Get chat
    $chat_result = $conn->query("SELECT id, status, assigned_admin_id FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    if (!$chat_result || $chat_result->num_rows === 0) {
        echo json_encode(['success' => true, 'messages' => [], 'chat_status' => 'none']);
        return;
    }
    
    $chat = $chat_result->fetch_assoc();
    $chat_id = $chat['id'];
    
    // Update online status
    $conn->query("UPDATE live_chats SET is_online = 1, last_activity = NOW() WHERE id = $chat_id");
    
    // Get new messages
    $messages = [];
    if ($last_id > 0) {
        $msg_result = $conn->query("SELECT * FROM live_chat_messages WHERE chat_id = $chat_id AND id > $last_id ORDER BY created_at ASC");
        if ($msg_result) {
            while ($row = $msg_result->fetch_assoc()) {
                $messages[] = $row;
                // Mark admin messages as read
                if ($row['sender_type'] === 'admin') {
                    $conn->query("UPDATE live_chat_messages SET is_read = 1 WHERE id = " . $row['id']);
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'chat_status' => $chat['status'],
        'has_admin' => !empty($chat['assigned_admin_id'])
    ]);
}

function startChat($conn, $name, $email) {
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    $user = getUserInfo();
    
    // Use provided name/email or fall back to user info
    $customer_name = !empty($name) ? $name : ($user['name'] ?: 'Guest');
    $customer_email = !empty($email) ? $email : $user['email'];
    $customer_id = $user['id'];
    $customer_phone = $user['phone'];
    
    // Check if chat already exists
    $existing = $conn->query("SELECT id FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    if ($existing && $existing->num_rows > 0) {
        $chat = $existing->fetch_assoc();
        // Update last_activity to prevent auto-deletion
        $conn->query("UPDATE live_chats SET last_activity = NOW() WHERE id = " . $chat['id']);
        echo json_encode(['success' => true, 'chat_id' => $chat['id'], 'existing' => true]);
        return;
    }
    
    // Create new chat
    $customer_name_escaped = $conn->real_escape_string($customer_name);
    $customer_email_escaped = $conn->real_escape_string($customer_email ?? '');
    $customer_phone_escaped = $conn->real_escape_string($customer_phone ?? '');
    
    $sql = "INSERT INTO live_chats (session_id, customer_id, customer_name, customer_email, customer_phone, status, last_activity) 
            VALUES ('$session_escaped', " . ($customer_id ? intval($customer_id) : "NULL") . ", '$customer_name_escaped', '$customer_email_escaped', '$customer_phone_escaped', 'bot', NOW())";
    
    if ($conn->query($sql)) {
        $chat_id = $conn->insert_id;
        
        // Add welcome message from bot
        $welcome = "Hello! Welcome to Gilaf Store! 👋 I'm your AI assistant. How can I help you today?";
        $welcome_escaped = $conn->real_escape_string($welcome);
        $conn->query("INSERT INTO live_chat_messages (chat_id, sender_type, sender_name, message) 
                      VALUES ($chat_id, 'bot', 'Gilaf Bot', '$welcome_escaped')");
        
        echo json_encode(['success' => true, 'chat_id' => $chat_id, 'existing' => false]);
    } else {
        error_log("Live Chat Error: " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Failed to start chat: ' . $conn->error]);
    }
}

function sendUserMessage($conn, $message) {
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message is empty']);
        return;
    }
    
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    $user = getUserInfo();
    
    // Get chat
    $chat_result = $conn->query("SELECT id, status, customer_name FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    if (!$chat_result || $chat_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Chat not found']);
        return;
    }
    
    $chat = $chat_result->fetch_assoc();
    $chat_id = $chat['id'];
    $sender_name = $user['name'] ?: $chat['customer_name'];
    
    // Insert user message
    $message_escaped = $conn->real_escape_string($message);
    $sender_name_escaped = $conn->real_escape_string($sender_name);
    
    $sql = "INSERT INTO live_chat_messages (chat_id, sender_type, sender_id, sender_name, message) 
            VALUES ($chat_id, 'user', " . ($user['id'] ? $user['id'] : "NULL") . ", '$sender_name_escaped', '$message_escaped')";
    
    if ($conn->query($sql)) {
        $msg_id = $conn->insert_id;
        
        // Update chat activity
        $conn->query("UPDATE live_chats SET last_activity = NOW(), is_online = 1 WHERE id = $chat_id");
        
        // Get the inserted message
        $result = $conn->query("SELECT * FROM live_chat_messages WHERE id = $msg_id");
        $msg = $result ? $result->fetch_assoc() : null;
        
        // Check if bot should respond (only if status is 'bot')
        $should_bot_respond = ($chat['status'] === 'bot');
        
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'bot_should_respond' => $should_bot_respond
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
}

function requestAgent($conn) {
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    $user = getUserInfo();
    
    // Get or create chat
    $chat_result = $conn->query("SELECT id FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    
    if (!$chat_result || $chat_result->num_rows === 0) {
        // Auto-create chat if it doesn't exist
        $customer_name = $conn->real_escape_string($user['name'] ?: 'Guest');
        $customer_email = $conn->real_escape_string($user['email'] ?? '');
        $customer_phone = $conn->real_escape_string($user['phone'] ?? '');
        $customer_id = $user['id'] ? intval($user['id']) : "NULL";
        
        $sql = "INSERT INTO live_chats (session_id, customer_id, customer_name, customer_email, customer_phone, status, last_activity) 
                VALUES ('$session_escaped', $customer_id, '$customer_name', '$customer_email', '$customer_phone', 'waiting', NOW())";
        
        if (!$conn->query($sql)) {
            error_log("Live Chat Error: " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Failed to create chat']);
            return;
        }
        $chat_id = $conn->insert_id;
    } else {
        $chat = $chat_result->fetch_assoc();
        $chat_id = $chat['id'];
        
        // Update status to waiting and refresh last_activity
        $conn->query("UPDATE live_chats SET status = 'waiting', last_activity = NOW() WHERE id = $chat_id");
    }
    
    // Add system message
    $conn->query("INSERT INTO live_chat_messages (chat_id, sender_type, sender_name, message) 
                  VALUES ($chat_id, 'system', 'System', 'You requested to talk to an agent. Please wait while we connect you.')");
    
    // Add bot message
    $conn->query("INSERT INTO live_chat_messages (chat_id, sender_type, sender_name, message) 
                  VALUES ($chat_id, 'bot', 'Gilaf Bot', 'I\\'m connecting you with a support agent. They\\'ll be with you shortly! 🙋')");
    
    echo json_encode(['success' => true, 'chat_id' => $chat_id]);
}

function requestCall($conn, $name, $phone) {
    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Name and phone are required']);
        return;
    }
    
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    $user = getUserInfo();
    
    // Get chat ID if exists
    $chat_id = null;
    $chat_result = $conn->query("SELECT id FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    if ($chat_result && $chat_result->num_rows > 0) {
        $chat = $chat_result->fetch_assoc();
        $chat_id = $chat['id'];
    }
    
    $name_escaped = $conn->real_escape_string($name);
    $phone_escaped = $conn->real_escape_string($phone);
    $email_escaped = $conn->real_escape_string($user['email'] ?? '');
    
    $sql = "INSERT INTO live_chat_call_requests (chat_id, customer_id, customer_name, customer_phone, customer_email, status) 
            VALUES (" . ($chat_id ? $chat_id : "NULL") . ", " . ($user['id'] ? $user['id'] : "NULL") . ", '$name_escaped', '$phone_escaped', '$email_escaped', 'pending')";
    
    if ($conn->query($sql)) {
        // Add message to chat if exists
        if ($chat_id) {
            $conn->query("INSERT INTO live_chat_messages (chat_id, sender_type, sender_name, message) 
                          VALUES ($chat_id, 'system', 'System', 'Call request submitted. Our team will call you at $phone_escaped soon.')");
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to submit call request']);
    }
}

function endChat($conn) {
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    
    // Get chat
    $chat_result = $conn->query("SELECT id FROM live_chats WHERE session_id = '$session_escaped' AND status != 'closed'");
    if ($chat_result && $chat_result->num_rows > 0) {
        $chat = $chat_result->fetch_assoc();
        $chat_id = $chat['id'];
        
        // Delete messages and chat
        $conn->query("DELETE FROM live_chat_messages WHERE chat_id = $chat_id");
        $conn->query("DELETE FROM live_chats WHERE id = $chat_id");
    }
    
    // Clear session
    unset($_SESSION['live_chat_session']);
    
    echo json_encode(['success' => true]);
}

function updateOnlineStatus($conn) {
    $session_id = getChatSessionId();
    $session_escaped = $conn->real_escape_string($session_id);
    
    $conn->query("UPDATE live_chats SET is_online = 1, last_activity = NOW() WHERE session_id = '$session_escaped'");
    
    echo json_encode(['success' => true]);
}
?>
