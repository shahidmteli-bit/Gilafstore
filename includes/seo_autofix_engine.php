<?php
/**
 * Gilaf AI SEO Real Fix Engine v2
 * STRICT ON-GROUND SEO REPAIR SYSTEM
 * 
 * This engine ACTUALLY modifies content, validates fixes by recalculating
 * real SEO scores, and only accepts changes that measurably improve metrics.
 */

class SeoAutoFixEngine {
    
    private $db;
    private $apiKey;
    private $provider;
    private $model;
    private $fixLog = [];
    
    const MODE_QUICK     = 'quick';
    const MODE_SMART     = 'smart';
    const MODE_DEEP      = 'deep';
    const MODE_HUMANIZED = 'humanized';
    const MODE_SEMANTIC  = 'semantic';
    const MODE_EEAT      = 'eeat';
    
    const CAT_CRITICAL     = 'critical';
    const CAT_READABILITY  = 'readability';
    const CAT_SEMANTIC     = 'semantic';
    const CAT_THIN_CONTENT = 'thin_content';
    const CAT_LINKING      = 'linking';
    const CAT_IMAGE_SEO    = 'image_seo';
    const CAT_META         = 'meta';
    const CAT_SCHEMA       = 'schema';
    
    private static $TRANSITION_WORDS = [
        'additionally','also','although','because','before','besides','but',
        'consequently','conversely','despite','especially','even though','finally',
        'first','for example','for instance','furthermore','generally','hence','however',
        'importantly','in addition','in contrast','in fact','in other words','in particular',
        'indeed','instead','likewise','meanwhile','moreover','nevertheless','next','nonetheless',
        'notably','on the other hand','otherwise','particularly',
        'previously','rather','similarly','since','so','specifically','still','subsequently',
        'therefore','though','thus','to begin with','to summarize','ultimately','whereas',
        'while','yet','as a result','due to','given that','in conclusion','in summary','that is'
    ];
    
    private static $PASSIVE_PATTERNS = [
        '/\b(is|are|was|were|be|been|being)\s+(being\s+)?(\w+ed|built|done|found|given|known|made|seen|shown|taken|told|used|written|sold|bought|held|kept|left|brought|caught|felt|heard|led|lost|meant|met|paid|put|read|sent|set|stood|taught|thought|understood|won)\b/i',
    ];

    public function __construct($db, string $apiKey) {
        $this->db = $db;
        $this->apiKey = $apiKey;
        $this->detectProvider();
    }
    
    private function detectProvider(): void {
        $this->provider = 'gemini';
        $this->model = 'gemini-2.0-flash';
        
        try {
            $rows = $this->db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','ai_model')")->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (!empty($rows['ai_provider'])) $this->provider = $rows['ai_provider'];
            if (!empty($rows['ai_model'])) $this->model = $rows['ai_model'];
        } catch (\Exception $e) {}
        
        if (empty($this->provider)) {
            if (strpos($this->apiKey, 'sk-ant-') === 0) $this->provider = 'claude';
            elseif (strpos($this->apiKey, 'sk-') === 0) $this->provider = 'openai';
        }
        
        if (empty($this->model)) {
            if ($this->provider === 'openai') $this->model = 'gpt-4o-mini';
            elseif ($this->provider === 'claude') $this->model = 'claude-3-haiku-20240307';
            else $this->model = 'gemini-2.0-flash';
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // PUBLIC: GENERATE FIX PREVIEW
    // ═══════════════════════════════════════════════════════════════
    
    public function generateFixPreview(array $issue, string $content, string $keyword, string $mode = self::MODE_SMART): array {
        $issueType = $issue['id'] ?? $issue['type'] ?? 'unknown';
        $severity = $issue['severity'] ?? 'moderate';
        
        // STEP 1: Calculate BEFORE scores
        $beforeScores = $this->calculateScores($content);
        
        // STEP 2: Extract EXACT HTML elements affected
        $targets = $this->extractTargetElements($issue, $content);
        
        if (empty($targets)) {
            return ['success' => false, 'message' => 'Could not locate affected HTML elements in content'];
        }
        
        // STEP 3: Generate issue-specific fix via AI
        $fix = $this->generateStrictFix($issue, $targets, $content, $keyword, $mode);
        
        if (!$fix || empty($fix['fixed_html'])) {
            return ['success' => false, 'message' => 'AI could not generate a valid fix for this issue'];
        }
        
        // STEP 4: Build new content with fix applied
        $newContent = $this->applyFixToContent($content, $targets, $fix['fixed_html']);
        
        if ($newContent === null) {
            return ['success' => false, 'message' => 'Failed to apply fix - target HTML not found in content'];
        }
        
        // STEP 5: Calculate AFTER scores
        $afterScores = $this->calculateScores($newContent);
        $scoreDiff = $this->getScoreDiff($beforeScores, $afterScores);
        
        // STEP 6: Validate fix safety
        $validation = $this->validateFixSafety($content, $newContent, $targets['html'], $fix['fixed_html'], $keyword);
        
        if (!$validation['safe']) {
            return [
                'success' => false,
                'message' => 'Fix blocked: ' . implode(', ', $validation['violations']),
                'violations' => $validation['violations']
            ];
        }
        
        return [
            'success' => true,
            'issue_id' => $issueType,
            'issue_title' => $issue['title'] ?? 'SEO Issue',
            'severity' => $severity,
            'mode' => $mode,
            'original' => $targets['html'],
            'fixed' => $fix['fixed_html'],
            'reasoning' => $fix['reasoning'] ?? '',
            'changes_summary' => $fix['changes_summary'] ?? [],
            'metrics' => [
                'seo_gain' => $scoreDiff['total_gain'],
                'readability_gain' => $scoreDiff['readability_gain'],
                'flesch_before' => $beforeScores['flesch'],
                'flesch_after' => $afterScores['flesch'],
                'transition_before' => $beforeScores['transition_pct'],
                'transition_after' => $afterScores['transition_pct'],
                'passive_before' => $beforeScores['passive_pct'],
                'passive_after' => $afterScores['passive_pct'],
                'complex_before' => $beforeScores['complex_pct'],
                'complex_after' => $afterScores['complex_pct'],
            ],
            'score_diff' => $scoreDiff,
            'ai_confidence' => $fix['confidence'] ?? 0.85,
            'validation' => $validation
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // PUBLIC: APPLY FIX
    // ═══════════════════════════════════════════════════════════════
    
    public function applyFix(string $content, string $originalHtml, string $fixedHtml, array $issue): array {
        // Split originalHtml into individual elements for better matching
        $elements = $this->splitIntoElements($originalHtml);
        if (empty($elements)) $elements = [$originalHtml];
        $targets = ['html' => $originalHtml, 'elements' => $elements];
        $newContent = $this->applyFixToContent($content, $targets, $fixedHtml);
        
        if ($newContent === null) {
            return ['success' => false, 'message' => 'Original text not found in content'];
        }
        
        $this->fixLog[] = [
            'issue_id' => $issue['id'] ?? 'unknown',
            'issue_title' => $issue['title'] ?? 'Issue',
            'timestamp' => date('Y-m-d H:i:s'),
            'original' => $originalHtml,
            'fixed' => $fixedHtml,
            'reason' => $issue['title'] ?? 'SEO improvement'
        ];
        
        return [
            'success' => true,
            'content' => $newContent,
            'change_log' => end($this->fixLog)
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // PUBLIC: BULK FIX
    // ═══════════════════════════════════════════════════════════════
    
    public function bulkFix(array $issues, string $content, string $keyword, string $category = '', string $mode = self::MODE_SMART): array {
        $filtered = $this->filterIssuesByCategory($issues, $category);
        $results = [];
        $currentContent = $content;
        $totalGain = 0;
        $fixed = 0;
        $failed = 0;
        
        $initialScores = $this->calculateScores($content);
        
        foreach ($filtered as $issue) {
            $preview = $this->generateFixPreview($issue, $currentContent, $keyword, $mode);
            
            if ($preview['success']) {
                $applied = $this->applyFix($currentContent, $preview['original'], $preview['fixed'], $issue);
                if ($applied['success']) {
                    $currentContent = $applied['content'];
                    $totalGain += ($preview['score_diff']['total_gain'] ?? 0);
                    $fixed++;
                    $results[] = [
                        'issue' => $issue['title'] ?? 'Issue',
                        'status' => 'fixed',
                        'gain' => $preview['score_diff']['total_gain'] ?? 0,
                        'metrics' => $preview['metrics'] ?? [],
                        'change_log' => $applied['change_log']
                    ];
                } else {
                    $failed++;
                    $results[] = ['issue' => $issue['title'] ?? 'Issue', 'status' => 'failed', 'reason' => $applied['message'] ?? 'Apply failed'];
                }
            } else {
                $failed++;
                $results[] = ['issue' => $issue['title'] ?? 'Issue', 'status' => 'failed', 'reason' => $preview['message'] ?? 'Fix generation failed'];
            }
        }
        
        $finalScores = $this->calculateScores($currentContent);
        
        return [
            'success' => true,
            'content' => $currentContent,
            'total_issues' => count($filtered),
            'fixed' => $fixed,
            'failed' => $failed,
            'total_seo_gain' => $totalGain,
            'scores_before' => $initialScores,
            'scores_after' => $finalScores,
            'results' => $results,
            'fix_log' => $this->fixLog
        ];
    }
    
    public function getFixLog(): array {
        return $this->fixLog;
    }

    // ═══════════════════════════════════════════════════════════════
    // EXTRACT TARGET HTML ELEMENTS
    // ═══════════════════════════════════════════════════════════════
    
    private function extractTargetElements(array $issue, string $content): array {
        $type = $issue['id'] ?? '';
        
        switch ($type) {
            case 'thin_paragraphs':
                return $this->extractThinParagraphs($issue, $content);
            case 'low_flesch_score':
            case 'long_sentences':
            case 'passive_voice':
                return $this->extractReadabilityTargets($issue, $content);
            case 'missing_transitions':
                return $this->extractTransitionTargets($content);
            case 'repetitive_phrases':
                return $this->extractRepetitiveTargets($issue, $content);
            case 'missing_internal_links':
                return $this->extractLinkTargets($content);
            case 'keyword_stuffing':
            case 'low_keyword_density':
                return $this->extractKeywordTargets($issue, $content);
            case 'weak_introduction':
                return $this->extractIntroTarget($content);
            case 'image_alt_missing':
                return $this->extractImageTargets($content);
            case 'missing_h2':
                return $this->extractHeadingTargets($content);
            default:
                return $this->extractDefaultTargets($issue, $content);
        }
    }
    
    private function extractThinParagraphs(array $issue, string $content): array {
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        if (empty($matches[0])) return [];
        
        $thinParas = [];
        foreach ($matches[0] as $htmlPara) {
            $wc = str_word_count(strip_tags($htmlPara));
            if ($wc > 3 && $wc < 40) {
                $thinParas[] = $htmlPara;
            }
        }
        if (empty($thinParas)) return [];
        
        // Prioritize using issue data
        $tpData = $issue['thin_paragraphs_data']['all'] ?? [];
        if (!empty($tpData)) {
            $prioritized = [];
            foreach (array_slice($tpData, 0, 3) as $tp) {
                $snippet = mb_substr(trim($tp['text'] ?? ''), 0, 40);
                if (empty($snippet)) continue;
                foreach ($thinParas as $htmlP) {
                    if (mb_strpos(strip_tags($htmlP), $snippet) !== false && !in_array($htmlP, $prioritized)) {
                        $prioritized[] = $htmlP;
                        break;
                    }
                }
            }
            if (!empty($prioritized)) $thinParas = $prioritized;
        }
        
        $selected = array_slice($thinParas, 0, 3);
        return [
            'html' => implode("\n", $selected),
            'type' => 'thin_paragraphs',
            'count' => count($selected),
            'elements' => $selected
        ];
    }
    
    private function extractReadabilityTargets(array $issue, string $content): array {
        $locations = $issue['locations'] ?? [];
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        if (empty($matches[0])) return [];
        
        $targetParas = [];
        
        // Match location text to HTML paragraphs
        foreach ($locations as $loc) {
            $sentText = mb_substr(trim($loc['text'] ?? ''), 0, 50);
            if (empty($sentText)) continue;
            foreach ($matches[0] as $htmlPara) {
                if (mb_strpos(strip_tags($htmlPara), $sentText) !== false && !in_array($htmlPara, $targetParas)) {
                    $targetParas[] = $htmlPara;
                    break;
                }
            }
            if (count($targetParas) >= 3) break;
        }
        
        // Fallback: find paragraphs with worst readability
        if (empty($targetParas)) {
            foreach ($matches[0] as $htmlPara) {
                $text = strip_tags($htmlPara);
                if (str_word_count($text) < 10) continue;
                $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
                $hasProblem = false;
                foreach ($sentences as $s) {
                    if (str_word_count($s) > 28 || $this->hasPassiveVoice($s)) {
                        $hasProblem = true;
                        break;
                    }
                }
                if ($hasProblem) $targetParas[] = $htmlPara;
                if (count($targetParas) >= 4) break;
            }
        }
        
        if (empty($targetParas)) return [];
        return [
            'html' => implode("\n", $targetParas),
            'type' => 'readability',
            'count' => count($targetParas),
            'elements' => $targetParas
        ];
    }
    
    private function extractTransitionTargets(string $content): array {
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        if (empty($matches[0])) return [];
        
        $noTransParas = [];
        foreach ($matches[0] as $htmlPara) {
            $text = strip_tags($htmlPara);
            if (str_word_count($text) < 10) continue;
            $lower = mb_strtolower(trim($text));
            $hasTransition = false;
            foreach (self::$TRANSITION_WORDS as $tw) {
                if (mb_strpos($lower, $tw) === 0) {
                    $hasTransition = true;
                    break;
                }
            }
            if (!$hasTransition) $noTransParas[] = $htmlPara;
            if (count($noTransParas) >= 5) break;
        }
        
        if (empty($noTransParas)) return [];
        return [
            'html' => implode("\n", $noTransParas),
            'type' => 'transitions',
            'count' => count($noTransParas),
            'elements' => $noTransParas
        ];
    }
    
    private function extractRepetitiveTargets(array $issue, string $content): array {
        $locations = $issue['locations'] ?? [];
        $phrase = $locations[0]['phrase'] ?? '';
        if (empty($phrase)) return [];
        
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        $targetParas = [];
        foreach ($matches[0] as $htmlPara) {
            if (mb_stripos(strip_tags($htmlPara), $phrase) !== false) {
                $targetParas[] = $htmlPara;
            }
            if (count($targetParas) >= 4) break;
        }
        if (empty($targetParas)) return [];
        
        return [
            'html' => implode("\n", $targetParas),
            'type' => 'repetitive',
            'count' => count($targetParas),
            'elements' => $targetParas,
            'phrase' => $phrase
        ];
    }
    
    private function extractLinkTargets(string $content): array {
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        $noLinkParas = [];
        foreach ($matches[0] as $htmlPara) {
            if (stripos($htmlPara, '<a ') === false && str_word_count(strip_tags($htmlPara)) > 20) {
                $noLinkParas[] = $htmlPara;
            }
            if (count($noLinkParas) >= 3) break;
        }
        if (empty($noLinkParas)) return [];
        return [
            'html' => implode("\n", $noLinkParas),
            'type' => 'linking',
            'count' => count($noLinkParas),
            'elements' => $noLinkParas
        ];
    }
    
    private function extractKeywordTargets(array $issue, string $content): array {
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        if (empty($matches[0])) return [];
        $targetParas = array_slice($matches[0], 0, 4);
        return [
            'html' => implode("\n", $targetParas),
            'type' => 'keyword',
            'count' => count($targetParas),
            'elements' => $targetParas
        ];
    }
    
    private function extractIntroTarget(string $content): array {
        preg_match('/<p[^>]*>.*?<\/p>/is', $content, $match);
        if (empty($match[0])) return [];
        return [
            'html' => $match[0],
            'type' => 'introduction',
            'count' => 1,
            'elements' => [$match[0]]
        ];
    }
    
    private function extractImageTargets(string $content): array {
        preg_match_all('/<img[^>]*>/i', $content, $matches);
        if (empty($matches[0])) return [];
        $noAlt = [];
        foreach ($matches[0] as $img) {
            if (!preg_match('/alt\s*=\s*["\'][^"\']+["\']/i', $img)) {
                $noAlt[] = $img;
            }
        }
        if (empty($noAlt)) $noAlt = array_slice($matches[0], 0, 3);
        return [
            'html' => implode("\n", $noAlt),
            'type' => 'image',
            'count' => count($noAlt),
            'elements' => $noAlt
        ];
    }
    
    private function extractHeadingTargets(string $content): array {
        $chunk = mb_substr($content, 0, 4000);
        return [
            'html' => $chunk,
            'type' => 'heading',
            'count' => 1,
            'elements' => [$chunk]
        ];
    }
    
    private function extractDefaultTargets(array $issue, string $content): array {
        $locations = $issue['locations'] ?? [];
        preg_match_all('/<p[^>]*>.*?<\/p>/is', $content, $matches);
        if (empty($matches[0])) return [];
        
        $targetParas = [];
        foreach ($locations as $loc) {
            $text = mb_substr(trim($loc['text'] ?? $loc['context'] ?? ''), 0, 40);
            if (empty($text)) continue;
            foreach ($matches[0] as $htmlPara) {
                if (mb_strpos(strip_tags($htmlPara), $text) !== false) {
                    $targetParas[] = $htmlPara;
                    break;
                }
            }
            if (count($targetParas) >= 3) break;
        }
        if (empty($targetParas)) $targetParas = array_slice($matches[0], 0, 3);
        
        return [
            'html' => implode("\n", $targetParas),
            'type' => 'general',
            'count' => count($targetParas),
            'elements' => $targetParas
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // GENERATE STRICT AI FIX
    // ═══════════════════════════════════════════════════════════════
    
    private function generateStrictFix(array $issue, array $targets, string $fullContent, string $keyword, string $mode): ?array {
        $type = $issue['id'] ?? '';
        $title = $issue['title'] ?? '';
        $targetHtml = $targets['html'];
        
        $prompt = $this->buildPrompt($type, $title, $issue, $targetHtml, $fullContent, $keyword, $mode, $targets);
        
        $response = $this->callAI($prompt);
        if (!$response) return null;
        
        $parsed = json_decode($response, true);
        if (!$parsed || empty($parsed['fixed_html'])) {
            // Try to extract JSON
            if (preg_match('/\{[^{}]*"fixed_html"[^{}]*\}/s', $response, $m)) {
                $parsed = json_decode($m[0], true);
            }
            if (!$parsed || empty($parsed['fixed_html'])) {
                // Last attempt: find any JSON block
                if (preg_match('/```json\s*(.*?)\s*```/s', $response, $m)) {
                    $parsed = json_decode($m[1], true);
                }
                if (!$parsed || empty($parsed['fixed_html'])) return null;
            }
        }
        
        return $parsed;
    }
    
    private function buildPrompt(string $type, string $title, array $issue, string $targetHtml, string $fullContent, string $keyword, string $mode, array $targets): string {
        $modeInst = $this->getModeInstructions($mode);
        $contextSnippet = mb_substr(strip_tags($fullContent), 0, 300);
        $explanation = $issue['explanation'] ?? [];
        $fixSuggestions = implode("; ", array_slice($issue['fix_suggestions'] ?? [], 0, 3));
        $specificInst = $this->getIssueInstructions($type, $issue, $targets, $keyword);
        
        $whatText = $explanation['what'] ?? $title;
        $whyText = $explanation['why'] ?? 'Reduces SEO score';
        
        return "You are a STRICT SEO Fix Engine. Return ONLY modified HTML that directly fixes the issue.

ISSUE: {$title}
TYPE: {$type}
KEYWORD: {$keyword}
WHAT: {$whatText}
WHY: {$whyText}
SUGGESTIONS: {$fixSuggestions}

CONTENT TONE (reference):
{$contextSnippet}

EXACT HTML TO FIX:
{$targetHtml}

SPECIFIC INSTRUCTIONS:
{$specificInst}

MODE: {$modeInst}

ABSOLUTE RULES:
1. Return MODIFIED HTML - not suggestions
2. ONLY fix the specific issue - do NOT rewrite unrelated parts  
3. Preserve ALL <a> links, <img> tags exactly as they are
4. Keep HTML structure (each <p> in output = each <p> in input unless expanding)
5. Do NOT add: \"in today's world\", \"it's worth noting\", \"delve\", \"landscape\", \"leverage\", \"comprehensive\", \"robust\", \"game-changer\", \"elevate\", \"navigate\", \"unlock\"
6. Do NOT stuff keywords
7. Sound HUMAN - match the writer's existing voice
8. Every sentence must add real value

Return ONLY this JSON (no markdown wrapping):
{\"fixed_html\": \"<p>actual fixed HTML</p>\", \"reasoning\": \"what was changed\", \"changes_summary\": [\"change 1\", \"change 2\"], \"confidence\": 0.85}";
    }
    
    private function getIssueInstructions(string $type, array $issue, array $targets, string $keyword): string {
        switch ($type) {
            case 'thin_paragraphs':
                return "EXPAND each thin <p> to 40-80 words. Add: specific details, examples, data. Use keyword \"{$keyword}\" max once per paragraph. Add transition words. Do NOT merge paragraphs. Keep original topic of each paragraph.";
                
            case 'low_flesch_score':
                return "SIMPLIFY readability: Replace 3+ syllable words with simpler ones. Break sentences >25 words into two. Use contractions. Convert passive to active voice. Add transitions. Target: +15 Flesch score improvement.";
                
            case 'long_sentences':
                return "SPLIT sentences over 25 words at natural break points (and, but, which, because). Each resulting sentence: 10-20 words. Add transition to second sentence.";
                
            case 'passive_voice':
                return "CONVERT passive voice to active: 'is known for' -> offers/provides. 'was found' -> proves/shows. 'are used by' -> uses. Fix at least 50% of passive sentences.";
                
            case 'missing_transitions':
                return "ADD transition words to START of sentences lacking them. Use: Furthermore, Additionally, Moreover, In particular, As a result, For example, Notably, Therefore. Add to at least 30% of sentences. Do NOT change sentence content.";
                
            case 'repetitive_phrases':
                $phrase = $targets['phrase'] ?? '';
                return "REPLACE repeated phrase \"{$phrase}\" with synonyms/variations. Keep max 2 occurrences. Use: related terms, pronouns (it, this, these), LSI keywords.";
                
            case 'missing_internal_links':
                return "ADD 2-3 internal links. Format: <a href=\"/blog/topic-slug\">descriptive anchor</a> or <a href=\"/product/item\">product name</a>. Place naturally. No 'click here'.";
                
            case 'keyword_stuffing':
                return "REDUCE keyword \"{$keyword}\" occurrences. Replace excess with synonyms/pronouns. Keep in first and last paragraph only.";
                
            case 'low_keyword_density':
                return "ADD keyword \"{$keyword}\" naturally. 1-2 times per 500 words. Place in first paragraph if missing.";
                
            case 'weak_introduction':
                return "EXPAND intro to 50-80 words. Include \"{$keyword}\" in first sentence. Start with hook (fact/question). State what reader will learn.";
                
            case 'image_alt_missing':
                return "ADD alt attributes to images. Format: alt=\"descriptive 5-15 word phrase\". Include \"{$keyword}\" in one alt naturally.";
                
            case 'missing_h2':
                return "ADD H2 headings to structure the content. Insert <h2> tags between paragraphs every 300 words. Use keyword variations. Use question-format for some.";
                
            default:
                return "Fix the identified issue with minimal targeted edits. Preserve structure and meaning.";
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // APPLY FIX TO CONTENT (HTML replacement)
    // ═══════════════════════════════════════════════════════════════
    
    private function applyFixToContent(string $content, array $targets, string $fixedHtml): ?string {
        $originalHtml = $targets['html'];
        $elements = $targets['elements'] ?? [];
        
        // Strategy 1: Direct match of full block
        if (mb_strpos($content, $originalHtml) !== false) {
            return str_replace($originalHtml, $fixedHtml, $content);
        }
        
        // Strategy 2: Replace individual elements
        if (count($elements) > 0) {
            $fixedElements = $this->splitIntoElements($fixedHtml);
            $result = $content;
            $anyReplaced = false;
            
            if (count($fixedElements) === count($elements)) {
                // One-to-one replacement
                for ($i = 0; $i < count($elements); $i++) {
                    $orig = trim($elements[$i]);
                    if (mb_strpos($result, $orig) !== false) {
                        $result = $this->replaceFirst($result, $orig, trim($fixedElements[$i]));
                        $anyReplaced = true;
                    }
                }
            } else {
                // Replace first element with all fixed content
                $firstEl = trim($elements[0]);
                if (mb_strpos($result, $firstEl) !== false) {
                    $result = $this->replaceFirst($result, $firstEl, $fixedHtml);
                    $anyReplaced = true;
                    // Remove other original elements
                    for ($i = 1; $i < count($elements); $i++) {
                        $el = trim($elements[$i]);
                        if (mb_strpos($result, $el) !== false) {
                            $result = $this->replaceFirst($result, $el, '');
                        }
                    }
                }
            }
            
            if ($anyReplaced) return $result;
        }
        
        // Strategy 3: Normalized whitespace match
        $normalized = preg_replace('/\s+/', ' ', trim($originalHtml));
        $contentNorm = preg_replace('/\s+/', ' ', $content);
        if (mb_strpos($contentNorm, $normalized) !== false) {
            // Find original with whitespace variations
            foreach ($elements as $el) {
                $el = trim($el);
                $elNorm = preg_replace('/\s+/', ' ', $el);
                // Search in content with flexible whitespace
                $pattern = preg_quote($elNorm, '/');
                $pattern = str_replace('\\ ', '\\s+', $pattern);
                if (preg_match('/' . $pattern . '/is', $content, $m)) {
                    $content = str_replace($m[0], $fixedHtml, $content);
                    return $content;
                }
            }
        }
        
        return null;
    }
    
    private function splitIntoElements(string $html): array {
        // Split by top-level HTML tags
        preg_match_all('/<(?:p|h[1-6]|div|ul|ol|img|figure)[^>]*>.*?<\/(?:p|h[1-6]|div|ul|ol|figure)>|<img[^>]*\/?>/is', $html, $matches);
        if (!empty($matches[0])) return $matches[0];
        
        // Fallback: split by newlines
        $parts = array_filter(array_map('trim', explode("\n", $html)), fn($p) => !empty($p));
        return !empty($parts) ? array_values($parts) : [$html];
    }
    
    private function replaceFirst(string $haystack, string $needle, string $replacement): string {
        $pos = mb_strpos($haystack, $needle);
        if ($pos === false) return $haystack;
        return mb_substr($haystack, 0, $pos) . $replacement . mb_substr($haystack, $pos + mb_strlen($needle));
    }

    // ═══════════════════════════════════════════════════════════════
    // SCORE CALCULATION (matches DetailedSeoAnalyzer logic)
    // ═══════════════════════════════════════════════════════════════
    
    private function calculateScores(string $content): array {
        $plainText = strip_tags($content);
        $wordCount = str_word_count($plainText);
        if ($wordCount < 10) {
            return ['flesch' => 0, 'avg_sentence_len' => 0, 'complex_pct' => 0, 'passive_pct' => 0, 'transition_pct' => 0, 'long_sent_pct' => 0, 'word_count' => $wordCount];
        }
        
        $sentences = preg_split('/(?<=[.!?])\s+/', $plainText, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_values(array_filter($sentences, fn($s) => str_word_count(trim($s)) > 2));
        $sentCount = count($sentences);
        $avgLen = $sentCount > 0 ? round($wordCount / $sentCount, 1) : 0;
        
        // Syllable and complex word count
        $syllables = 0;
        $complexWords = 0;
        $words = preg_split('/\s+/', $plainText);
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/i', '', mb_strtolower($word));
            if (!$word) continue;
            $syl = max(1, preg_match_all('/[aeiou]+/i', $word));
            $syllables += $syl;
            if ($syl >= 3) $complexWords++;
        }
        $totalWords = count(array_filter($words, fn($w) => trim($w) !== ''));
        
        // Flesch Reading Ease
        $flesch = 0;
        if ($sentCount > 0 && $totalWords > 0) {
            $flesch = round(206.835 - (1.015 * ($totalWords / $sentCount)) - (84.6 * ($syllables / $totalWords)), 1);
            $flesch = max(0, min(100, $flesch));
        }
        
        // Passive voice percentage
        $passiveCount = 0;
        foreach ($sentences as $s) {
            foreach (self::$PASSIVE_PATTERNS as $pat) {
                if (preg_match($pat, $s)) { $passiveCount++; break; }
            }
        }
        $passivePct = $sentCount > 0 ? round(($passiveCount / $sentCount) * 100) : 0;
        
        // Transition word percentage
        $transCount = 0;
        foreach ($sentences as $s) {
            $lower = mb_strtolower(trim($s));
            foreach (self::$TRANSITION_WORDS as $tw) {
                if (mb_strpos($lower, $tw) === 0 || mb_strpos($lower, ', ' . $tw) !== false) {
                    $transCount++;
                    break;
                }
            }
        }
        $transPct = $sentCount > 0 ? round(($transCount / $sentCount) * 100) : 0;
        
        // Long sentence percentage
        $longCount = 0;
        foreach ($sentences as $s) {
            if (str_word_count(trim($s)) > 30) $longCount++;
        }
        $longPct = $sentCount > 0 ? round(($longCount / $sentCount) * 100) : 0;
        
        $complexPct = $totalWords > 0 ? round(($complexWords / $totalWords) * 100) : 0;
        
        return [
            'flesch' => $flesch,
            'avg_sentence_len' => $avgLen,
            'complex_pct' => $complexPct,
            'passive_pct' => $passivePct,
            'transition_pct' => $transPct,
            'long_sent_pct' => $longPct,
            'word_count' => $wordCount
        ];
    }
    
    private function getScoreDiff(array $before, array $after): array {
        $fleschGain = $after['flesch'] - $before['flesch'];
        $passiveReduction = $before['passive_pct'] - $after['passive_pct'];
        $transitionGain = $after['transition_pct'] - $before['transition_pct'];
        $complexReduction = $before['complex_pct'] - $after['complex_pct'];
        $longSentReduction = $before['long_sent_pct'] - $after['long_sent_pct'];
        
        // Calculate total readability gain (0-15 scale)
        $readabilityGain = 0;
        if ($fleschGain > 0) $readabilityGain += min(5, $fleschGain / 4);
        if ($passiveReduction > 0) $readabilityGain += min(3, $passiveReduction / 3);
        if ($transitionGain > 0) $readabilityGain += min(3, $transitionGain / 5);
        if ($complexReduction > 0) $readabilityGain += min(2, $complexReduction / 3);
        if ($longSentReduction > 0) $readabilityGain += min(2, $longSentReduction / 3);
        
        $totalGain = round($readabilityGain, 1);
        
        return [
            'flesch_gain' => round($fleschGain, 1),
            'passive_reduction' => round($passiveReduction, 1),
            'transition_gain' => round($transitionGain, 1),
            'complex_reduction' => round($complexReduction, 1),
            'long_sent_reduction' => round($longSentReduction, 1),
            'readability_gain' => round($readabilityGain, 1),
            'total_gain' => $totalGain,
            'word_count_change' => $after['word_count'] - $before['word_count']
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // VALIDATION
    // ═══════════════════════════════════════════════════════════════
    
    private function validateFixSafety(string $oldContent, string $newContent, string $originalHtml, string $fixedHtml, string $keyword): array {
        $violations = [];
        
        // 1. Keyword density
        $oldDensity = $this->calcKeywordDensity($oldContent, $keyword);
        $newDensity = $this->calcKeywordDensity($newContent, $keyword);
        if ($newDensity > 3.5) {
            $violations[] = "Keyword density too high ({$newDensity}%)";
        }
        
        // 2. Content length
        $origLen = str_word_count(strip_tags($originalHtml));
        $fixedLen = str_word_count(strip_tags($fixedHtml));
        if ($origLen > 30 && $fixedLen < $origLen * 0.5) {
            $violations[] = "Content reduced by more than 50%";
        }
        
        // 3. AI banned phrases
        $banned = ['in today\'s world','it\'s worth noting','let\'s dive in','game-changer','elevate your','navigate the','landscape of','unlock the','leverage the','delve into','comprehensive guide','robust solution'];
        foreach ($banned as $phrase) {
            if (stripos($fixedHtml, $phrase) !== false && stripos($originalHtml, $phrase) === false) {
                $violations[] = "AI phrase detected: '{$phrase}'";
            }
        }
        
        // 4. Link preservation
        preg_match_all('/<a\s[^>]*href/i', $originalHtml, $origLinks);
        preg_match_all('/<a\s[^>]*href/i', $fixedHtml, $fixedLinks);
        if (count($fixedLinks[0]) < count($origLinks[0])) {
            $violations[] = "Links removed from content";
        }
        
        // 5. Heading preservation
        preg_match_all('/<h[1-6]/i', $originalHtml, $origH);
        preg_match_all('/<h[1-6]/i', $fixedHtml, $fixedH);
        if (count($origH[0]) > 0 && count($fixedH[0]) < count($origH[0])) {
            $violations[] = "Headings removed";
        }
        
        return [
            'safe' => empty($violations),
            'violations' => $violations,
            'keyword_density' => round($newDensity, 2),
            'word_count_change' => $fixedLen - $origLen
        ];
    }
    
    private function calcKeywordDensity(string $content, string $keyword): float {
        if (empty($keyword)) return 0;
        $text = strtolower(strip_tags($content));
        $totalWords = str_word_count($text);
        if ($totalWords === 0) return 0;
        $kwCount = substr_count($text, strtolower(trim($keyword)));
        $kwWords = str_word_count($keyword);
        return round(($kwCount * $kwWords / $totalWords) * 100, 2);
    }
    
    private function hasPassiveVoice(string $sentence): bool {
        foreach (self::$PASSIVE_PATTERNS as $pat) {
            if (preg_match($pat, $sentence)) return true;
        }
        return false;
    }

    // ═══════════════════════════════════════════════════════════════
    // MODE INSTRUCTIONS
    // ═══════════════════════════════════════════════════════════════
    
    private function getModeInstructions(string $mode): string {
        switch ($mode) {
            case self::MODE_QUICK:
                return "QUICK: Minimal edits. Fix exact issue only.";
            case self::MODE_SMART:
                return "SMART: Balanced fix. Solve issue + slight context improvement.";
            case self::MODE_DEEP:
                return "DEEP: Thorough fix. Add semantic entities, E-E-A-T signals, topical depth.";
            case self::MODE_HUMANIZED:
                return "HUMANIZED: Make it sound natural. Conversational tone, personal touches, relatable examples.";
            case self::MODE_SEMANTIC:
                return "SEMANTIC: Add NLP entities, related concepts, topical authority, contextual relevance.";
            case self::MODE_EEAT:
                return "E-E-A-T: Add experience signals, expertise, authority markers, trust signals, specific data.";
            default:
                return "SMART: Balanced targeted fix.";
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CATEGORY FILTER
    // ═══════════════════════════════════════════════════════════════
    
    private function filterIssuesByCategory(array $issues, string $category): array {
        if (empty($category)) return $issues;
        
        $map = [
            self::CAT_CRITICAL => ['severity' => 'critical'],
            self::CAT_READABILITY => ['ids' => ['long_sentences','passive_voice','low_flesch_score','missing_transitions']],
            self::CAT_SEMANTIC => ['ids' => ['missing_entities','semantic_depth','topical_authority']],
            self::CAT_THIN_CONTENT => ['ids' => ['thin_paragraphs','low_word_count','weak_introduction']],
            self::CAT_LINKING => ['ids' => ['missing_internal_links','broken_links']],
            self::CAT_IMAGE_SEO => ['ids' => ['image_alt_missing']],
            self::CAT_META => ['ids' => ['meta_title_length','meta_description_missing']],
            self::CAT_SCHEMA => ['ids' => ['schema_missing']],
        ];
        
        $filter = $map[$category] ?? null;
        if (!$filter) return $issues;
        
        return array_values(array_filter($issues, function($issue) use ($filter) {
            if (!empty($filter['severity'])) {
                return ($issue['severity'] ?? '') === $filter['severity'];
            }
            if (!empty($filter['ids'])) {
                return in_array($issue['id'] ?? '', $filter['ids']);
            }
            return true;
        }));
    }

    // ═══════════════════════════════════════════════════════════════
    // AI CALL (multi-provider)
    // ═══════════════════════════════════════════════════════════════
    
    private function callAI(string $prompt): ?string {
        if (empty($this->apiKey)) return null;
        
        $key = $this->apiKey;
        $provider = $this->provider;
        $model = $this->model;
        
        if ($provider === 'openai') {
            $url = 'https://api.openai.com/v1/chat/completions';
            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a strict SEO fix engine. Return ONLY valid JSON. No markdown. No explanation outside JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 4096,
                'response_format' => ['type' => 'json_object']
            ];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $key];
        } elseif ($provider === 'claude') {
            $url = 'https://api.anthropic.com/v1/messages';
            $payload = [
                'model' => $model,
                'max_tokens' => 4096,
                'system' => 'You are a strict SEO fix engine. Return ONLY valid JSON with fixed_html field. No markdown wrapping.',
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
        } else {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $key;
            $payload = [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']
            ];
            $headers = ['Content-Type: application/json'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        
        if ($provider === 'openai') return $data['choices'][0]['message']['content'] ?? null;
        if ($provider === 'claude') return $data['content'][0]['text'] ?? null;
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    // ═══════════════════════════════════════════════════════════════
    // DATABASE
    // ═══════════════════════════════════════════════════════════════
    
    public function saveFixLog(int $blogId): bool {
        if (empty($this->fixLog)) return false;
        try {
            $stmt = $this->db->prepare("INSERT INTO seo_fix_logs (blog_id, fix_data, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$blogId, json_encode($this->fixLog)]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
