<?php
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Load API key from config
$gemini_api_key = '';
if (file_exists('chatbot_config.php')) {
    require_once 'chatbot_config.php';
    $gemini_api_key = GEMINI_API_KEY ?? '';
}

if (empty($gemini_api_key)) {
    echo json_encode([
        'success' => false,
        'message' => 'Gemini API key not configured',
        'use_fallback' => true
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $user_message = sanitize_input($data['message'] ?? '');
    $conversation_history = $data['history'] ?? [];
    
    if (empty($user_message)) {
        echo json_encode([
            'success' => false,
            'message' => 'Message is required',
            'use_fallback' => true
        ]);
        exit;
    }
    
    try {
        // Build context for Gemini
        $system_context = "You are a helpful customer support AI assistant for Gilaf, a premium eCommerce store selling authentic Kashmiri saffron and spices. 

Your role:
- Provide accurate information about products, orders, payments, and policies
- Be polite, professional, and concise
- Help users create support tickets when needed
- Use emojis sparingly for a friendly tone
- Keep responses under 200 words

Company Information:
- Products: Premium saffron (various grades), organic saffron, spices
- All products are lab-tested and certified
- QR code verification available on each batch
- Contact: Phone/WhatsApp: +91-9419404670, Email: support@gilaf.com
- Hours: Mon-Sat, 9 AM - 6 PM
- Delivery: 5-7 business days standard, 2-3 days express
- Free shipping on orders above ₹999
- 7-day return policy for unopened products
- Payment methods: Cards, UPI, Net Banking, Wallets, COD

Support Ticket System:
- Users can create support tickets for complex issues
- Available ticket types: Order Issues, Product Questions, Payment Issues, Shipping & Delivery, Account Support, Technical Issues, General Inquiry
- When a user needs to create a ticket, offer to help them with the ticket creation form
- Tickets are handled by human support team within 24 hours

Important Rules:
1. For order tracking, direct users to the Track Order page
2. For complex issues that need human intervention, suggest creating a support ticket
3. If user says 'create ticket', 'open ticket', 'submit ticket', or similar, guide them to create a support ticket
4. For payment issues, provide troubleshooting steps first, then suggest ticket if unresolved
5. For distributor inquiries, mention three options: Reseller, Distributor, Official Store
6. If the query is outside your knowledge, suggest creating a support ticket
7. Never make up information - be honest about limitations
8. Always maintain a helpful, professional tone

Current conversation context:";

        // Build conversation history for context
        $conversation_text = $system_context . "\n\n";
        if (!empty($conversation_history)) {
            foreach ($conversation_history as $msg) {
                $role = $msg['role'] === 'user' ? 'Customer' : 'Assistant';
                $conversation_text .= "$role: " . $msg['content'] . "\n";
            }
        }
        $conversation_text .= "Customer: $user_message\nAssistant:";
        
        // Call Gemini API - Using gemini-2.5-flash model with v1beta endpoint
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $gemini_api_key;
        
        $request_data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $conversation_text]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 500,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("cURL Error: $curl_error");
        }
        
        if ($http_code !== 200) {
            throw new Exception("API returned status code: $http_code");
        }
        
        $response_data = json_decode($response, true);
        
        if (!isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Invalid API response format");
        }
        
        $ai_response = $response_data['candidates'][0]['content']['parts'][0]['text'];
        
        // Determine if response suggests escalation
        $escalate = false;
        $escalation_keywords = ['contact support', 'reach out', 'call us', 'email us', 'speak to', 'human agent'];
        foreach ($escalation_keywords as $keyword) {
            if (stripos($ai_response, $keyword) !== false) {
                $escalate = true;
                break;
            }
        }
        
        // Generate quick actions based on response content + user intent
        $quick_actions = [];
        
        // Always offer ticket creation if user intent suggests it OR if response suggests escalation.
        $ticket_keywords = ['ticket', 'create ticket', 'open ticket', 'submit ticket', 'support ticket', 'raise ticket', 'complaint', 'escalate'];
        $support_intent_keywords = ['support', 'help', 'contact', 'customer care', 'agent', 'human'];
        
        $suggest_ticket = false;
        foreach ($ticket_keywords as $keyword) {
            if (stripos($user_message, $keyword) !== false || stripos($ai_response, $keyword) !== false) {
                $suggest_ticket = true;
                break;
            }
        }
        if (!$suggest_ticket) {
            foreach ($support_intent_keywords as $keyword) {
                if (stripos($user_message, $keyword) !== false) {
                    $suggest_ticket = true;
                    break;
                }
            }
        }
        
        if ($suggest_ticket || $escalate) {
            $quick_actions[] = 'Create Support Ticket';
        }
        if (stripos($ai_response, 'track') !== false || stripos($ai_response, 'order') !== false) {
            $quick_actions[] = 'Track Order';
        }
        if (stripos($ai_response, 'product') !== false || stripos($ai_response, 'saffron') !== false) {
            $quick_actions[] = 'View Products';
        }
        if (stripos($ai_response, 'return') !== false || stripos($ai_response, 'refund') !== false) {
            $quick_actions[] = 'Return Policy';
        }
        if (stripos($ai_response, 'verify') !== false || stripos($ai_response, 'batch') !== false) {
            $quick_actions[] = 'Verify Batch';
        }
        if ($escalate || stripos($ai_response, 'support') !== false || stripos($user_message, 'support') !== false) {
            $quick_actions[] = 'Contact Support';
        }
        
        // Limit to 3 quick actions
        $quick_actions = array_slice(array_unique($quick_actions), 0, 3);
        
        // Log the interaction (optional)
        try {
            if (isset($conn) && $conn) {
                $session_id = session_id() ?: uniqid('chatbot_', true);
                $stmt = $conn->prepare("INSERT INTO chatbot_analytics (session_id, user_message, bot_response, matched_category, escalated) VALUES (?, ?, ?, 'gemini_ai', ?)");
                $escalated = $escalate ? 1 : 0;
                $stmt->bind_param("sssi", $session_id, $user_message, $ai_response, $escalated);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            // Silently fail logging
        }
        
        echo json_encode([
            'success' => true,
            'response' => $ai_response,
            'quick_actions' => $quick_actions,
            'escalate' => $escalate,
            'source' => 'gemini_ai'
        ]);
        
    } catch (Exception $e) {
        error_log("Gemini API Error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'AI service temporarily unavailable',
            'error' => $e->getMessage(),
            'use_fallback' => true
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method',
        'use_fallback' => true
    ]);
}
?>
