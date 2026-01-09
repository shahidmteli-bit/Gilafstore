<?php
/**
 * Intelligent Subject Line Generator
 * Automatically generates concise, meaningful subject lines from descriptions
 * Based on category context and key action extraction
 */

class SubjectGenerator {
    
    // Action verbs commonly used in suggestions
    private static $actionVerbs = [
        'add', 'improve', 'fix', 'update', 'enhance', 'create', 'remove', 'change',
        'optimize', 'implement', 'integrate', 'redesign', 'simplify', 'automate',
        'enable', 'disable', 'show', 'hide', 'display', 'reduce', 'increase',
        'allow', 'prevent', 'support', 'upgrade', 'modify', 'customize', 'expand'
    ];
    
    // Common filler words to remove
    private static $fillerWords = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during',
        'including', 'until', 'against', 'among', 'throughout', 'despite', 'towards',
        'upon', 'concerning', 'would', 'should', 'could', 'can', 'will', 'may',
        'might', 'must', 'shall', 'i', 'you', 'we', 'they', 'it', 'this', 'that',
        'these', 'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'do', 'does', 'did', 'please', 'kindly', 'really',
        'very', 'too', 'also', 'just', 'only', 'even', 'still', 'already', 'yet'
    ];
    
    // Category-specific keywords to prioritize
    private static $categoryKeywords = [
        'UI/UX' => ['design', 'interface', 'layout', 'button', 'menu', 'navigation', 'color', 'theme', 'responsive', 'mobile', 'desktop', 'user experience', 'usability'],
        'Performance' => ['speed', 'loading', 'slow', 'fast', 'optimize', 'cache', 'performance', 'lag', 'delay', 'response time', 'bandwidth'],
        'Features' => ['feature', 'functionality', 'option', 'tool', 'capability', 'add', 'new', 'wishlist', 'filter', 'search', 'sort'],
        'Payments' => ['payment', 'checkout', 'cart', 'order', 'transaction', 'gateway', 'upi', 'card', 'wallet', 'refund', 'invoice'],
        'Security' => ['security', 'privacy', 'password', 'authentication', 'encryption', 'login', 'account', 'protection', 'safe', 'secure'],
        'Content' => ['content', 'text', 'image', 'video', 'description', 'information', 'details', 'documentation', 'help', 'faq'],
        'Other' => []
    ];
    
    /**
     * Generate intelligent subject line from description and category
     * @param string $description User's detailed suggestion
     * @param string $category Selected category
     * @return string Generated subject line (5-8 words)
     */
    public static function generate($description, $category = '') {
        // Clean and prepare text
        $text = self::cleanText($description);
        
        // Extract sentences
        $sentences = self::extractSentences($text);
        
        // Find the most important sentence (usually first or contains action verb)
        $mainSentence = self::findMainSentence($sentences);
        
        // Extract key phrases
        $keyPhrases = self::extractKeyPhrases($mainSentence, $category);
        
        // Build subject line
        $subject = self::buildSubject($keyPhrases, $category);
        
        // Ensure 5-8 words limit
        $subject = self::limitWords($subject, 5, 8);
        
        // Capitalize properly
        $subject = self::capitalizeSubject($subject);
        
        return $subject;
    }
    
    /**
     * Clean text - remove extra spaces, special chars, etc.
     */
    private static function cleanText($text) {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove URLs
        $text = preg_replace('#\bhttps?://[^\s]+#', '', $text);
        
        // Remove email addresses
        $text = preg_replace('/[\w\-\.]+@[\w\-\.]+\.\w+/', '', $text);
        
        // Remove special characters but keep sentence structure
        $text = preg_replace('/[^\w\s\.\?\!]/', ' ', $text);
        
        // Remove multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Extract sentences from text
     */
    private static function extractSentences($text) {
        // Split by sentence endings
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Clean each sentence
        $sentences = array_map('trim', $sentences);
        
        // Remove empty sentences
        $sentences = array_filter($sentences, function($s) {
            return strlen($s) > 10; // Minimum 10 chars
        });
        
        return array_values($sentences);
    }
    
    /**
     * Find the most important sentence (contains action or is first)
     */
    private static function findMainSentence($sentences) {
        if (empty($sentences)) {
            return '';
        }
        
        // Check first sentence for action verbs
        foreach ($sentences as $sentence) {
            foreach (self::$actionVerbs as $verb) {
                if (strpos($sentence, $verb) !== false) {
                    return $sentence;
                }
            }
        }
        
        // Return first sentence if no action found
        return $sentences[0];
    }
    
    /**
     * Extract key phrases from sentence
     */
    private static function extractKeyPhrases($sentence, $category) {
        $words = explode(' ', $sentence);
        $keyWords = [];
        
        // Get category-specific keywords
        $categoryKeys = self::$categoryKeywords[$category] ?? [];
        
        // Extract important words
        foreach ($words as $word) {
            $word = trim($word);
            
            // Skip if empty or too short
            if (strlen($word) < 3) continue;
            
            // Skip filler words
            if (in_array($word, self::$fillerWords)) continue;
            
            // Prioritize action verbs
            if (in_array($word, self::$actionVerbs)) {
                $keyWords[] = ['word' => $word, 'priority' => 10];
                continue;
            }
            
            // Prioritize category keywords
            foreach ($categoryKeys as $catKey) {
                if (strpos($word, $catKey) !== false || strpos($catKey, $word) !== false) {
                    $keyWords[] = ['word' => $word, 'priority' => 8];
                    continue 2;
                }
            }
            
            // Add other meaningful words
            if (strlen($word) >= 4) {
                $keyWords[] = ['word' => $word, 'priority' => 5];
            }
        }
        
        // Sort by priority
        usort($keyWords, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        // Extract just the words
        return array_column($keyWords, 'word');
    }
    
    /**
     * Build subject from key phrases
     */
    private static function buildSubject($keyPhrases, $category) {
        if (empty($keyPhrases)) {
            return self::getDefaultSubject($category);
        }
        
        // Take top 8 words maximum
        $keyPhrases = array_slice($keyPhrases, 0, 8);
        
        // Build subject
        $subject = implode(' ', $keyPhrases);
        
        return $subject;
    }
    
    /**
     * Limit words to specified range
     */
    private static function limitWords($subject, $min, $max) {
        $words = explode(' ', $subject);
        $wordCount = count($words);
        
        if ($wordCount > $max) {
            // Take first $max words
            $words = array_slice($words, 0, $max);
        } elseif ($wordCount < $min) {
            // If too short, keep as is (better than nothing)
            return $subject;
        }
        
        return implode(' ', $words);
    }
    
    /**
     * Capitalize subject properly (title case)
     */
    private static function capitalizeSubject($subject) {
        // Split into words
        $words = explode(' ', $subject);
        
        // Capitalize each word except small words (unless first word)
        $smallWords = ['a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'in', 'of', 'on', 'or', 'the', 'to', 'up'];
        
        $capitalized = [];
        foreach ($words as $index => $word) {
            if ($index === 0 || !in_array($word, $smallWords)) {
                $capitalized[] = ucfirst($word);
            } else {
                $capitalized[] = $word;
            }
        }
        
        return implode(' ', $capitalized);
    }
    
    /**
     * Get default subject if generation fails
     */
    private static function getDefaultSubject($category) {
        $defaults = [
            'UI/UX' => 'Improve User Interface Design',
            'Performance' => 'Optimize System Performance',
            'Features' => 'Add New Feature Request',
            'Payments' => 'Enhance Payment Process',
            'Security' => 'Improve Security Measures',
            'Content' => 'Update Content Information',
            'Other' => 'General Improvement Suggestion'
        ];
        
        return $defaults[$category] ?? 'Improvement Suggestion';
    }
    
    /**
     * Generate subject with context awareness
     * Enhanced version that considers common patterns
     */
    public static function generateAdvanced($description, $category = '') {
        $text = self::cleanText($description);
        
        // Pattern matching for common suggestion structures
        $patterns = [
            // "I suggest/recommend/propose..."
            '/(?:suggest|recommend|propose|think|believe|feel)\s+(?:that\s+)?(?:you\s+)?(?:should\s+)?(.+?)(?:\.|$)/i',
            // "Please add/fix/improve..."
            '/(?:please|kindly|could you)\s+(.+?)(?:\.|$)/i',
            // "It would be great/nice/better if..."
            '/(?:it would be|would be)\s+(?:great|nice|better|good|helpful)\s+(?:if|to)\s+(.+?)(?:\.|$)/i',
            // "Why not/How about..."
            '/(?:why not|how about|what about)\s+(.+?)(?:\.|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $extracted = $matches[1];
                $keyPhrases = self::extractKeyPhrases($extracted, $category);
                $subject = self::buildSubject($keyPhrases, $category);
                $subject = self::limitWords($subject, 5, 8);
                return self::capitalizeSubject($subject);
            }
        }
        
        // Fall back to standard generation
        return self::generate($description, $category);
    }
}
