<?php
/**
 * CRM AI Helper
 * Provides AI-powered features for customer communication
 * - Smart reply suggestions
 * - Sentiment analysis
 * - Intent detection
 * - Auto-categorization
 */

class CRMAIHelper
{
    private static ?CRMAIHelper $instance = null;
    private array $config = [];
    
    private function __construct()
    {
        $this->config = [
            'enabled' => true,
            'openai_key' => getenv('OPENAI_API_KEY') ?: '',
        ];
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Analyze customer message sentiment
     * Returns: positive, negative, neutral, urgent
     */
    public function analyzeSentiment(string $message): array
    {
        $message = strtolower(trim($message));
        
        // Keyword-based sentiment (fallback when no API)
        $urgentKeywords = ['urgent', 'asap', 'immediately', 'emergency', 'help', 'problem', 'issue', 'broken', 'wrong', 'missing', 'refund', 'cancel'];
        $negativeKeywords = ['bad', 'terrible', 'worst', 'disappointed', 'angry', 'frustrated', 'complaint', 'poor', 'useless', 'scam', 'fraud', 'cheat'];
        $positiveKeywords = ['thank', 'great', 'excellent', 'amazing', 'love', 'perfect', 'happy', 'satisfied', 'recommend', 'awesome', 'best'];
        
        $urgentScore = 0;
        $negativeScore = 0;
        $positiveScore = 0;
        
        foreach ($urgentKeywords as $kw) {
            if (strpos($message, $kw) !== false) $urgentScore++;
        }
        foreach ($negativeKeywords as $kw) {
            if (strpos($message, $kw) !== false) $negativeScore++;
        }
        foreach ($positiveKeywords as $kw) {
            if (strpos($message, $kw) !== false) $positiveScore++;
        }
        
        $sentiment = 'neutral';
        $confidence = 0.5;
        
        if ($urgentScore >= 2 || ($urgentScore >= 1 && $negativeScore >= 1)) {
            $sentiment = 'urgent';
            $confidence = min(0.9, 0.5 + ($urgentScore * 0.15));
        } elseif ($negativeScore > $positiveScore && $negativeScore >= 1) {
            $sentiment = 'negative';
            $confidence = min(0.9, 0.5 + ($negativeScore * 0.15));
        } elseif ($positiveScore > $negativeScore && $positiveScore >= 1) {
            $sentiment = 'positive';
            $confidence = min(0.9, 0.5 + ($positiveScore * 0.15));
        }
        
        return [
            'sentiment' => $sentiment,
            'confidence' => $confidence,
            'scores' => [
                'urgent' => $urgentScore,
                'negative' => $negativeScore,
                'positive' => $positiveScore,
            ],
        ];
    }
    
    /**
     * Detect customer intent
     * Returns: order_status, refund, complaint, product_inquiry, support, greeting, other
     */
    public function detectIntent(string $message): array
    {
        $message = strtolower(trim($message));
        
        $intents = [
            'order_status' => ['where is my order', 'order status', 'track', 'tracking', 'when will', 'delivery', 'shipped', 'dispatch', 'eta'],
            'refund' => ['refund', 'money back', 'return', 'cancel order', 'cancellation'],
            'complaint' => ['complaint', 'not working', 'broken', 'damaged', 'wrong item', 'missing', 'defective', 'bad quality'],
            'product_inquiry' => ['price', 'available', 'stock', 'size', 'color', 'details', 'information', 'specs'],
            'payment' => ['payment', 'pay', 'cod', 'upi', 'razorpay', 'transaction', 'failed payment'],
            'greeting' => ['hi', 'hello', 'hey', 'good morning', 'good evening', 'namaste'],
            'thanks' => ['thank', 'thanks', 'appreciate', 'grateful'],
        ];
        
        $detected = [];
        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (strpos($message, $kw) !== false) {
                    $detected[$intent] = ($detected[$intent] ?? 0) + 1;
                }
            }
        }
        
        if (empty($detected)) {
            return ['intent' => 'other', 'confidence' => 0.3, 'all_intents' => []];
        }
        
        arsort($detected);
        $topIntent = array_key_first($detected);
        $confidence = min(0.95, 0.5 + ($detected[$topIntent] * 0.15));
        
        return [
            'intent' => $topIntent,
            'confidence' => $confidence,
            'all_intents' => $detected,
        ];
    }
    
    /**
     * Generate smart reply suggestions based on message
     */
    public function suggestReplies(string $message, array $context = []): array
    {
        $intent = $this->detectIntent($message);
        $sentiment = $this->analyzeSentiment($message);
        
        $suggestions = [];
        
        switch ($intent['intent']) {
            case 'order_status':
                $suggestions = [
                    "I'll check your order status right away. Could you please share your order ID?",
                    "Let me look up your order. Please provide your order number or the phone number used during checkout.",
                    "Your order is being processed. You can track it here: [tracking_link]",
                ];
                break;
                
            case 'refund':
                $suggestions = [
                    "I understand you'd like a refund. Please share your order ID and reason, and I'll process it immediately.",
                    "Refund requests are processed within 24 hours. Could you share your order details?",
                    "I'm sorry for the inconvenience. Let me initiate the refund process for you.",
                ];
                break;
                
            case 'complaint':
                $suggestions = [
                    "I sincerely apologize for the inconvenience. Let me resolve this for you right away.",
                    "I'm really sorry to hear that. Please share more details so I can fix this immediately.",
                    "Thank you for bringing this to our attention. We'll make this right for you.",
                ];
                break;
                
            case 'product_inquiry':
                $suggestions = [
                    "I'd be happy to help! Which product are you interested in?",
                    "Yes, this product is available. Would you like me to share more details?",
                    "Let me check the availability for you. One moment please.",
                ];
                break;
                
            case 'payment':
                $suggestions = [
                    "We accept UPI, Credit/Debit cards, Net Banking, and Cash on Delivery.",
                    "If your payment failed, don't worry - no amount was deducted. Please try again.",
                    "For payment issues, please share your transaction ID and I'll investigate.",
                ];
                break;
                
            case 'greeting':
                $suggestions = [
                    "Hello! 👋 Welcome to Gilaf Store. How can I assist you today?",
                    "Hi there! Thanks for reaching out. How may I help you?",
                    "Namaste! 🙏 How can I help you today?",
                ];
                break;
                
            case 'thanks':
                $suggestions = [
                    "You're welcome! Is there anything else I can help with?",
                    "Happy to help! 😊 Feel free to reach out anytime.",
                    "Thank you for shopping with Gilaf Store! Have a great day!",
                ];
                break;
                
            default:
                $suggestions = [
                    "Thank you for your message. How can I assist you today?",
                    "I'm here to help! Could you please provide more details?",
                    "Let me connect you with our support team for further assistance.",
                ];
        }
        
        // Adjust for sentiment
        if ($sentiment['sentiment'] === 'urgent') {
            array_unshift($suggestions, "I understand this is urgent. Let me prioritize your request right away.");
        } elseif ($sentiment['sentiment'] === 'negative') {
            array_unshift($suggestions, "I'm truly sorry for any frustration caused. Let me help resolve this immediately.");
        }
        
        return [
            'suggestions' => array_slice($suggestions, 0, 3),
            'intent' => $intent,
            'sentiment' => $sentiment,
        ];
    }
    
    /**
     * Auto-categorize a support ticket/conversation
     */
    public function categorizeTicket(string $message): array
    {
        $intent = $this->detectIntent($message);
        $sentiment = $this->analyzeSentiment($message);
        
        $categoryMap = [
            'order_status' => 'Order Tracking',
            'refund' => 'Returns & Refunds',
            'complaint' => 'Complaints',
            'product_inquiry' => 'Product Questions',
            'payment' => 'Payment Issues',
            'greeting' => 'General',
            'thanks' => 'Feedback',
            'other' => 'General',
        ];
        
        $priorityMap = [
            'urgent' => 'high',
            'negative' => 'medium',
            'neutral' => 'normal',
            'positive' => 'low',
        ];
        
        return [
            'category' => $categoryMap[$intent['intent']] ?? 'General',
            'priority' => $priorityMap[$sentiment['sentiment']] ?? 'normal',
            'tags' => $this->extractTags($message),
            'requires_human' => $sentiment['sentiment'] === 'urgent' || $intent['intent'] === 'complaint',
        ];
    }
    
    /**
     * Extract relevant tags from message
     */
    private function extractTags(string $message): array
    {
        $message = strtolower($message);
        $tags = [];
        
        $tagPatterns = [
            'order_id' => '/order\s*#?\s*(\d{4,})/i',
            'product' => '/(product|item)/i',
            'delivery' => '/(delivery|shipping|dispatch)/i',
            'urgent' => '/(urgent|asap|immediately)/i',
            'refund' => '/(refund|money back)/i',
            'quality' => '/(quality|defective|broken|damaged)/i',
        ];
        
        foreach ($tagPatterns as $tag => $pattern) {
            if (preg_match($pattern, $message)) {
                $tags[] = $tag;
            }
        }
        
        return $tags;
    }
    
    /**
     * Generate order summary for WhatsApp
     */
    public function generateOrderSummary(array $order, array $items = []): string
    {
        $summary = "🛒 *Order Summary*\n";
        $summary .= "━━━━━━━━━━━━━━━\n";
        $summary .= "📦 Order: #{$order['id']}\n";
        $summary .= "📅 Date: " . date('d M Y', strtotime($order['created_at'])) . "\n";
        $summary .= "💰 Total: ₹" . number_format($order['total_amount'], 2) . "\n";
        $summary .= "📍 Status: " . ucfirst(str_replace('_', ' ', $order['order_status'])) . "\n";
        
        if (!empty($items)) {
            $summary .= "\n*Items:*\n";
            foreach (array_slice($items, 0, 5) as $item) {
                $summary .= "• {$item['name']} x{$item['quantity']}\n";
            }
            if (count($items) > 5) {
                $summary .= "... and " . (count($items) - 5) . " more items\n";
            }
        }
        
        if ($order['tracking_id']) {
            $summary .= "\n🚚 Tracking: {$order['tracking_id']}\n";
        }
        
        return $summary;
    }
}
