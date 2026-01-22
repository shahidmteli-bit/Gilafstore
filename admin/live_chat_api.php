<?php
/**
 * Live Chat API - Backend endpoints for real-time chat
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check admin authentication
if (!is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['user']['id'] ?? 0;
$admin_name = $_SESSION['user']['name'] ?? 'Admin';

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_chats':
            getChats($conn);
            break;
            
        case 'get_messages':
            $chat_id = intval($_GET['chat_id'] ?? 0);
            getMessages($conn, $chat_id);
            break;
            
        case 'get_call_requests':
            getCallRequests($conn);
            break;
            
        case 'poll_updates':
            $last_id = intval($_GET['last_id'] ?? 0);
            $current_chat = intval($_GET['current_chat'] ?? 0);
            pollUpdates($conn, $last_id, $current_chat, $admin_id);
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
        case 'send_message':
            $chat_id = intval($data['chat_id'] ?? 0);
            $message = trim($data['message'] ?? '');
            sendMessage($conn, $chat_id, $message, $admin_id, $admin_name);
            break;
            
        case 'takeover_chat':
            $chat_id = intval($data['chat_id'] ?? 0);
            takeoverChat($conn, $chat_id, $admin_id, $admin_name);
            break;
            
        case 'close_chat':
            $chat_id = intval($data['chat_id'] ?? 0);
            closeChat($conn, $chat_id);
            break;
            
        case 'end_chat':
            $chat_id = intval($data['chat_id'] ?? 0);
            endChat($conn, $chat_id);
            break;
            
        case 'mark_read':
            $chat_id = intval($data['chat_id'] ?? 0);
            markAsRead($conn, $chat_id);
            break;
            
        case 'connect_call':
            $request_id = intval($data['request_id'] ?? 0);
            connectCall($conn, $request_id, $admin_id);
            break;
            
        case 'mark_call_contacted':
            $request_id = intval($data['request_id'] ?? 0);
            markCallContacted($conn, $request_id, $admin_id);
            break;
            
        case 'mark_not_connected':
            $request_id = intval($data['request_id'] ?? 0);
            markNotConnected($conn, $request_id, $admin_id);
            break;
            
        case 'update_settings':
            $sound_enabled = intval($data['sound_enabled'] ?? 1);
            updateSettings($conn, $admin_id, $sound_enabled);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// ============ API Functions ============

function getChats($conn) {
    // Auto-cleanup: Delete inactive chats (no activity for 8 minutes)
    $conn->query("DELETE FROM live_chat_messages WHERE chat_id IN 
                  (SELECT id FROM live_chats WHERE last_activity < DATE_SUB(NOW(), INTERVAL 8 MINUTE))");
    $conn->query("DELETE FROM live_chats WHERE last_activity < DATE_SUB(NOW(), INTERVAL 8 MINUTE)");
    
    // Show only active chats (not closed)
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM live_chat_messages m WHERE m.chat_id = c.id AND m.is_read = 0 AND m.sender_type = 'user') as unread_count,
            (SELECT message FROM live_chat_messages m WHERE m.chat_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message
            FROM live_chats c 
            WHERE c.status != 'closed'
            ORDER BY c.last_activity DESC";
    
    $result = $conn->query($sql);
    $chats = [];
    $total_unread = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chats[] = $row;
            $total_unread += intval($row['unread_count']);
        }
    }
    
    echo json_encode([
        'success' => true,
        'chats' => $chats,
        'unread_count' => $total_unread
    ]);
}

function getMessages($conn, $chat_id) {
    if (!$chat_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid chat ID']);
        return;
    }
    
    // Get chat info
    $chat_result = $conn->query("SELECT * FROM live_chats WHERE id = $chat_id");
    $chat = $chat_result ? $chat_result->fetch_assoc() : null;
    
    if (!$chat) {
        echo json_encode(['success' => false, 'error' => 'Chat not found']);
        return;
    }
    
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
        'chat' => $chat,
        'messages' => $messages
    ]);
}

function getCallRequests($conn) {
    $sql = "SELECT * FROM live_chat_call_requests ORDER BY 
            CASE status WHEN 'pending' THEN 1 WHEN 'connected' THEN 2 ELSE 3 END,
            created_at DESC";
    
    $result = $conn->query($sql);
    $requests = [];
    $pending_count = 0;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $requests[] = $row;
            if ($row['status'] === 'pending') {
                $pending_count++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'requests' => $requests,
        'pending_count' => $pending_count
    ]);
}

function pollUpdates($conn, $last_id, $current_chat, $admin_id) {
    $has_updates = false;
    $play_sound = false;
    $notification_text = '';
    $new_messages = [];
    $new_calls = false;
    
    // Check for new messages in current chat
    if ($current_chat > 0 && $last_id > 0) {
        $msg_result = $conn->query("SELECT * FROM live_chat_messages 
                                    WHERE chat_id = $current_chat AND id > $last_id 
                                    ORDER BY created_at ASC");
        if ($msg_result) {
            while ($row = $msg_result->fetch_assoc()) {
                $new_messages[] = $row;
                if ($row['sender_type'] === 'user') {
                    $play_sound = true;
                    $notification_text = $row['message'];
                }
            }
        }
    }
    
    // Check for new unread messages across all chats
    $unread_result = $conn->query("SELECT COUNT(*) as count FROM live_chat_messages 
                                   WHERE is_read = 0 AND sender_type = 'user'");
    if ($unread_result) {
        $unread = $unread_result->fetch_assoc();
        if ($unread['count'] > 0) {
            $has_updates = true;
            if (empty($new_messages)) {
                $play_sound = true;
            }
        }
    }
    
    // Check for new waiting chats
    $waiting_result = $conn->query("SELECT COUNT(*) as count FROM live_chats WHERE status = 'waiting'");
    if ($waiting_result) {
        $waiting = $waiting_result->fetch_assoc();
        if ($waiting['count'] > 0) {
            $has_updates = true;
        }
    }
    
    // Check for new call requests (pending in last 5 seconds)
    $call_result = $conn->query("SELECT COUNT(*) as count FROM live_chat_call_requests 
                                 WHERE status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 5 SECOND)");
    if ($call_result) {
        $calls = $call_result->fetch_assoc();
        if ($calls['count'] > 0) {
            $new_calls = true;
        }
    }
    
    // Get latest message ID
    $latest_result = $conn->query("SELECT MAX(id) as max_id FROM live_chat_messages");
    $latest_id = $latest_result ? $latest_result->fetch_assoc()['max_id'] : $last_id;
    
    echo json_encode([
        'success' => true,
        'has_updates' => $has_updates,
        'play_sound' => $play_sound,
        'notification_text' => substr($notification_text, 0, 50),
        'new_messages' => $new_messages,
        'new_calls' => $new_calls,
        'last_id' => $latest_id
    ]);
}

function sendMessage($conn, $chat_id, $message, $admin_id, $admin_name) {
    if (!$chat_id || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }
    
    $message = $conn->real_escape_string($message);
    $admin_name = $conn->real_escape_string($admin_name);
    
    $sql = "INSERT INTO live_chat_messages (chat_id, sender_type, sender_id, sender_name, message) 
            VALUES ($chat_id, 'admin', $admin_id, '$admin_name', '$message')";
    
    if ($conn->query($sql)) {
        $msg_id = $conn->insert_id;
        
        // Update chat last activity
        $conn->query("UPDATE live_chats SET last_activity = NOW() WHERE id = $chat_id");
        
        // Get the inserted message
        $result = $conn->query("SELECT * FROM live_chat_messages WHERE id = $msg_id");
        $msg = $result ? $result->fetch_assoc() : null;
        
        echo json_encode([
            'success' => true,
            'message' => $msg
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
}

function takeoverChat($conn, $chat_id, $admin_id, $admin_name) {
    if (!$chat_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid chat ID']);
        return;
    }
    
    // Update chat status and assign admin
    $conn->query("UPDATE live_chats SET status = 'active', assigned_admin_id = $admin_id WHERE id = $chat_id");
    
    // Add system message
    $admin_name = $conn->real_escape_string($admin_name);
    $conn->query("INSERT INTO live_chat_messages (chat_id, sender_type, sender_name, message) 
                  VALUES ($chat_id, 'system', 'System', '$admin_name joined the chat')");
    
    echo json_encode(['success' => true]);
}

function closeChat($conn, $chat_id) {
    if (!$chat_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid chat ID']);
        return;
    }
    
    // Mark chat as closed instead of deleting (keep for 30 minutes)
    $conn->query("UPDATE live_chats SET status = 'closed', closed_at = NOW() WHERE id = $chat_id");
    
    // Add system message
    $conn->query("INSERT INTO live_chat_messages (chat_id, sender_type, sender_name, message) 
                  VALUES ($chat_id, 'system', 'System', 'Chat ended by support agent')");
    
    // Cleanup: Delete chats and messages older than 30 minutes
    $conn->query("DELETE FROM live_chat_messages WHERE chat_id IN 
                  (SELECT id FROM live_chats WHERE status = 'closed' AND closed_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE))");
    $conn->query("DELETE FROM live_chats WHERE status = 'closed' AND closed_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    
    // Also cleanup inactive chats (no user response for 8 minutes)
    $conn->query("DELETE FROM live_chat_messages WHERE chat_id IN 
                  (SELECT id FROM live_chats WHERE last_activity < DATE_SUB(NOW(), INTERVAL 8 MINUTE) AND status != 'closed')");
    $conn->query("DELETE FROM live_chats WHERE last_activity < DATE_SUB(NOW(), INTERVAL 8 MINUTE) AND status != 'closed'");
    
    echo json_encode(['success' => true]);
}

function endChat($conn, $chat_id) {
    if (!$chat_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid chat ID']);
        return;
    }
    
    // Permanently delete all messages
    $conn->query("DELETE FROM live_chat_messages WHERE chat_id = $chat_id");
    
    // Permanently delete the chat
    $conn->query("DELETE FROM live_chats WHERE id = $chat_id");
    
    echo json_encode(['success' => true]);
}

function markAsRead($conn, $chat_id) {
    if (!$chat_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid chat ID']);
        return;
    }
    
    $conn->query("UPDATE live_chat_messages SET is_read = 1 WHERE chat_id = $chat_id AND sender_type = 'user'");
    
    echo json_encode(['success' => true]);
}

function connectCall($conn, $request_id, $admin_id) {
    if (!$request_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
        return;
    }
    
    // Get phone number before deleting to update callback_requests table
    $result = $conn->query("SELECT customer_phone, customer_name FROM live_chat_call_requests WHERE id = $request_id");
    if ($result && $row = $result->fetch_assoc()) {
        $phone = $conn->real_escape_string($row['customer_phone']);
        $name = $conn->real_escape_string($row['customer_name']);
        // Update status in callback_requests table - match by phone OR name+phone combination
        $conn->query("UPDATE callback_requests SET status = 'contacted', contacted_at = NOW() WHERE (phone = '$phone' OR phone LIKE '%$phone%') AND status = 'pending'");
    }
    
    // Delete the call request after connecting (clears it from the list)
    $conn->query("DELETE FROM live_chat_call_requests WHERE id = $request_id");
    
    echo json_encode(['success' => true]);
}

function markCallContacted($conn, $request_id, $admin_id) {
    if (!$request_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
        return;
    }
    
    // Delete the call request after it's been contacted
    $conn->query("DELETE FROM live_chat_call_requests WHERE id = $request_id");
    
    echo json_encode(['success' => true]);
}

function markNotConnected($conn, $request_id, $admin_id) {
    if (!$request_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
        return;
    }
    
    // Get phone number before deleting to update callback_requests table
    $result = $conn->query("SELECT customer_phone FROM live_chat_call_requests WHERE id = $request_id");
    if ($result && $row = $result->fetch_assoc()) {
        $phone = $conn->real_escape_string($row['customer_phone']);
        // Update status in callback_requests table to 'completed' (Not Connected)
        $conn->query("UPDATE callback_requests SET status = 'completed', contacted_at = NOW() WHERE (phone = '$phone' OR phone LIKE '%$phone%') AND status = 'pending'");
    }
    
    // Delete the call request after marking as not connected
    $conn->query("DELETE FROM live_chat_call_requests WHERE id = $request_id");
    
    echo json_encode(['success' => true]);
}

function updateSettings($conn, $admin_id, $sound_enabled) {
    $sql = "INSERT INTO live_chat_admin_settings (admin_id, sound_enabled) 
            VALUES ($admin_id, $sound_enabled) 
            ON DUPLICATE KEY UPDATE sound_enabled = $sound_enabled";
    
    $conn->query($sql);
    
    echo json_encode(['success' => true]);
}
?>
