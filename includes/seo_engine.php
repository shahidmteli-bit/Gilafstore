<?php
/**
 * Gilaf AI SEO Intelligence Engine — Core Analysis Library
 * 
 * Enterprise-grade SEO analysis combining capabilities of:
 * RankMath, SurferSEO, MarketMuse, Frase, Ahrefs, Clearscope,
 * NeuronWriter, Scalenut, Yoast, Outranking
 * 
 * @version 2.0.0
 */

// ============================================================
// 1. BASIC SEO ANALYZER
// ============================================================
class GilafSeoAnalyzer {
    
    private $db;
    private $content;
    private $title;
    private $metaTitle;
    private $metaDescription;
    private $slug;
    private $focusKeyword;
    private $secondaryKeywords;
    private $excerpt;
    private $url;
    private $wordCount;
    private $contentLower;
    private $sentences;
    private $paragraphs;
    private $headings;
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    /**
     * Analyze content with all engines
     */
    public function analyzeContent(array $data): array {
        $this->content        = $data['content'] ?? '';
        $this->title          = $data['title'] ?? '';
        $this->metaTitle      = $data['meta_title'] ?? $this->title;
        $this->metaDescription = $data['meta_description'] ?? '';
        $this->slug           = $data['slug'] ?? '';
        $this->excerpt        = $data['excerpt'] ?? '';
        $this->url            = $data['url'] ?? '';
        
        $kwRaw = $data['focus_keyword'] ?? $data['meta_keywords'] ?? '';
        $kwArr = array_map('trim', explode(',', $kwRaw));
        $this->focusKeyword     = strtolower($kwArr[0] ?? '');
        $this->secondaryKeywords = array_map('strtolower', array_slice($kwArr, 1));
        
        $plainContent = strip_tags($this->content);
        $this->contentLower = mb_strtolower($plainContent);
        $this->wordCount    = str_word_count($plainContent);
        $this->sentences    = preg_split('/[.!?]+/', $plainContent, -1, PREG_SPLIT_NO_EMPTY);
        $this->sentences    = array_filter($this->sentences, fn($s) => str_word_count(trim($s)) > 2);
        $this->paragraphs   = preg_split('/\n\s*\n/', $plainContent, -1, PREG_SPLIT_NO_EMPTY);
        
        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $this->content, $hMatches, PREG_SET_ORDER);
        $this->headings = $hMatches;
        
        return [
            'basic_seo'       => $this->analyzeBasicSeo(),
            'keyword'         => $this->analyzeKeywords(),
            'readability'     => $this->analyzeReadability(),
            'content_quality' => $this->analyzeContentQuality(),
            'heading_structure' => $this->analyzeHeadingStructure(),
            'eeat'            => $this->analyzeEEAT(),
            'image_seo'       => $this->analyzeImageSeo(),
            'internal_links'  => $this->analyzeInternalLinks(),
            'ai_search'       => $this->analyzeAiSearchOptimization(),
            'semantic'        => $this->analyzeSemanticSeo(),
            'schema'          => $this->analyzeSchemaReadiness(),
            'ctr'             => $this->analyzeCTR(),
            'overall_score'   => 0, // calculated after
        ];
    }
    
    // ============================================================
    // 1. BASIC SEO ENGINE
    // ============================================================
    private function analyzeBasicSeo(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        // Meta title length
        $maxScore += 10;
        $mtLen = mb_strlen($this->metaTitle);
        if ($mtLen >= 30 && $mtLen <= 60) {
            $score += 10;
            $checks[] = ['status' => 'pass', 'text' => "Meta title length ({$mtLen}/60) — ideal", 'weight' => 10];
        } elseif ($mtLen > 0) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "Meta title length ({$mtLen}) — aim 30-60 chars", 'weight' => 10];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Meta title is missing', 'weight' => 10];
        }
        
        // Meta description length
        $maxScore += 10;
        $mdLen = mb_strlen($this->metaDescription);
        if ($mdLen >= 120 && $mdLen <= 160) {
            $score += 10;
            $checks[] = ['status' => 'pass', 'text' => "Meta description length ({$mdLen}/160) — ideal", 'weight' => 10];
        } elseif ($mdLen > 0) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "Meta description ({$mdLen} chars) — aim 120-160", 'weight' => 10];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Meta description is missing', 'weight' => 10];
        }
        
        // URL/slug analysis
        $maxScore += 8;
        $slugLen = mb_strlen($this->slug);
        if ($slugLen > 0 && $slugLen <= 75) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "URL slug length ({$slugLen} chars) — OK", 'weight' => 8];
        } elseif ($slugLen > 75) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => "URL slug too long ({$slugLen}/75 chars)", 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'URL slug is missing', 'weight' => 8];
        }
        
        // Slug contains only lowercase + hyphens
        $maxScore += 5;
        if ($this->slug && preg_match('/^[a-z0-9\-]+$/', $this->slug)) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'URL is SEO-friendly (lowercase, hyphens)', 'weight' => 5];
        } elseif ($this->slug) {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => 'URL contains special characters or uppercase', 'weight' => 5];
        }
        
        // Excerpt/description present
        $maxScore += 5;
        if (mb_strlen($this->excerpt) >= 50) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'Excerpt provided (' . mb_strlen($this->excerpt) . ' chars)', 'weight' => 5];
        } elseif ($this->excerpt) {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => 'Excerpt is too short — aim 50+ chars', 'weight' => 5];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Add an excerpt for better SEO', 'weight' => 5];
        }
        
        // Title uniqueness hint (starts with keyword)
        $maxScore += 5;
        $titleLower = mb_strtolower($this->metaTitle ?: $this->title);
        if ($this->focusKeyword && mb_strpos($titleLower, $this->focusKeyword) === 0) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'Title starts with focus keyword — strong signal', 'weight' => 5];
        } elseif ($this->focusKeyword && mb_strpos($titleLower, $this->focusKeyword) !== false) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => 'Keyword in title but not at the beginning', 'weight' => 5];
        } elseif ($this->focusKeyword) {
            $checks[] = ['status' => 'fail', 'text' => 'Focus keyword missing from title', 'weight' => 5];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Basic SEO'
        ];
    }
    
    // ============================================================
    // 2. KEYWORD ANALYSIS ENGINE
    // ============================================================
    private function analyzeKeywords(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        if (empty($this->focusKeyword)) {
            return [
                'score' => 0,
                'checks' => [['status' => 'fail', 'text' => 'No focus keyword set — keyword analysis unavailable', 'weight' => 50]],
                'label' => 'Keyword Analysis'
            ];
        }
        
        $kw = $this->focusKeyword;
        
        // Keyword in title
        $maxScore += 10;
        $titleLower = mb_strtolower($this->metaTitle ?: $this->title);
        if (mb_strpos($titleLower, $kw) !== false) {
            $score += 10;
            $checks[] = ['status' => 'pass', 'text' => 'Focus keyword found in title', 'weight' => 10];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "Add \"{$kw}\" to your title", 'weight' => 10];
        }
        
        // Keyword in meta description
        $maxScore += 8;
        if (mb_strpos(mb_strtolower($this->metaDescription), $kw) !== false) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Focus keyword found in meta description', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Add focus keyword to meta description', 'weight' => 8];
        }
        
        // Keyword in URL
        $maxScore += 8;
        $kwSlug = str_replace(' ', '-', $kw);
        if (mb_strpos(mb_strtolower($this->slug), $kwSlug) !== false) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Focus keyword found in URL slug', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Add focus keyword to URL slug', 'weight' => 8];
        }
        
        // Keyword in first paragraph (first 300 chars)
        $maxScore += 8;
        $firstPara = mb_substr($this->contentLower, 0, 300);
        if (mb_strpos($firstPara, $kw) !== false) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Focus keyword in first paragraph', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Use focus keyword in the first 300 characters', 'weight' => 8];
        }
        
        // Keyword in headings
        $maxScore += 8;
        $kwInHeading = false;
        foreach ($this->headings as $h) {
            if (mb_strpos(mb_strtolower($h[2]), $kw) !== false) {
                $kwInHeading = true;
                break;
            }
        }
        if ($kwInHeading) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Focus keyword found in subheading(s)', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Use focus keyword in at least one H2/H3', 'weight' => 8];
        }
        
        // Keyword density
        $maxScore += 10;
        if ($this->wordCount > 0) {
            $kwCount = mb_substr_count($this->contentLower, $kw);
            $density = round(($kwCount / $this->wordCount) * 100, 2);
            if ($density >= 0.5 && $density <= 2.5) {
                $score += 10;
                $checks[] = ['status' => 'pass', 'text' => "Keyword density {$density}% — ideal (0.5-2.5%)", 'weight' => 10];
            } elseif ($density > 0) {
                $score += 4;
                $status = $density > 2.5 ? 'warn' : 'warn';
                $checks[] = ['status' => $status, 'text' => "Keyword density {$density}% — " . ($density > 2.5 ? 'over-optimized' : 'too low, aim 0.5-2.5%'), 'weight' => 10];
            } else {
                $checks[] = ['status' => 'fail', 'text' => 'Focus keyword not found in content', 'weight' => 10];
            }
        }
        
        // Secondary keywords
        $maxScore += 5;
        if (!empty($this->secondaryKeywords)) {
            $found = 0;
            foreach ($this->secondaryKeywords as $sk) {
                if ($sk && mb_strpos($this->contentLower, $sk) !== false) $found++;
            }
            $total = count($this->secondaryKeywords);
            if ($found === $total) {
                $score += 5;
                $checks[] = ['status' => 'pass', 'text' => "All {$total} secondary keywords found in content", 'weight' => 5];
            } elseif ($found > 0) {
                $score += 3;
                $checks[] = ['status' => 'warn', 'text' => "{$found}/{$total} secondary keywords found", 'weight' => 5];
            } else {
                $checks[] = ['status' => 'fail', 'text' => 'No secondary keywords found in content', 'weight' => 5];
            }
        }
        
        // Keyword in excerpt
        $maxScore += 5;
        if ($this->excerpt && mb_strpos(mb_strtolower($this->excerpt), $kw) !== false) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'Focus keyword in excerpt', 'weight' => 5];
        } elseif ($this->excerpt) {
            $checks[] = ['status' => 'warn', 'text' => 'Add focus keyword to excerpt', 'weight' => 5];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Keyword Analysis',
            'density' => $density ?? 0,
            'keyword_count' => $kwCount ?? 0
        ];
    }
    
    // ============================================================
    // 3. READABILITY ENGINE (Yoast + Hemingway)
    // ============================================================
    private function analyzeReadability(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        if ($this->wordCount < 50) {
            return [
                'score' => 0,
                'checks' => [['status' => 'fail', 'text' => 'Not enough content for readability analysis (min 50 words)', 'weight' => 50]],
                'label' => 'Readability',
                'flesch_score' => 0,
                'reading_level' => 'N/A'
            ];
        }
        
        $sentCount = count($this->sentences);
        $avgSentLen = $sentCount > 0 ? round($this->wordCount / $sentCount, 1) : 0;
        
        // Flesch Reading Ease approximation
        $syllableCount = $this->estimateSyllables($this->contentLower);
        $flesch = 0;
        if ($sentCount > 0 && $this->wordCount > 0) {
            $flesch = round(206.835 - (1.015 * ($this->wordCount / $sentCount)) - (84.6 * ($syllableCount / $this->wordCount)), 1);
        }
        $flesch = max(0, min(100, $flesch));
        
        $readingLevel = $this->getReadingLevel($flesch);
        
        // Flesch score
        $maxScore += 15;
        if ($flesch >= 60) {
            $score += 15;
            $checks[] = ['status' => 'pass', 'text' => "Flesch Reading Ease: {$flesch} ({$readingLevel})", 'weight' => 15];
        } elseif ($flesch >= 40) {
            $score += 8;
            $checks[] = ['status' => 'warn', 'text' => "Flesch Reading Ease: {$flesch} ({$readingLevel}) — aim 60+", 'weight' => 15];
        } else {
            $score += 3;
            $checks[] = ['status' => 'fail', 'text' => "Flesch Reading Ease: {$flesch} ({$readingLevel}) — too difficult", 'weight' => 15];
        }
        
        // Avg sentence length
        $maxScore += 10;
        if ($avgSentLen > 0 && $avgSentLen <= 20) {
            $score += 10;
            $checks[] = ['status' => 'pass', 'text' => "Avg sentence length: {$avgSentLen} words — good", 'weight' => 10];
        } elseif ($avgSentLen <= 25) {
            $score += 5;
            $checks[] = ['status' => 'warn', 'text' => "Avg sentence length: {$avgSentLen} words — aim ≤20", 'weight' => 10];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "Avg sentence length: {$avgSentLen} words — too long", 'weight' => 10];
        }
        
        // Long sentences (>30 words)
        $maxScore += 8;
        $longSentences = 0;
        foreach ($this->sentences as $s) {
            if (str_word_count(trim($s)) > 30) $longSentences++;
        }
        $longPct = $sentCount > 0 ? round(($longSentences / $sentCount) * 100) : 0;
        if ($longPct <= 15) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "{$longPct}% sentences are long (≤15% is good)", 'weight' => 8];
        } elseif ($longPct <= 30) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "{$longPct}% sentences are long — simplify some", 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "{$longPct}% sentences are too long — break them up", 'weight' => 8];
        }
        
        // Paragraph length
        $maxScore += 8;
        $longParas = 0;
        foreach ($this->paragraphs as $p) {
            if (str_word_count(trim($p)) > 150) $longParas++;
        }
        $paraCount = count($this->paragraphs);
        if ($longParas === 0) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'All paragraphs are readable length', 'weight' => 8];
        } elseif ($longParas <= 2) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "{$longParas} paragraph(s) are very long — break up", 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "{$longParas} paragraphs are too long — limit to 150 words", 'weight' => 8];
        }
        
        // Passive voice detection
        $maxScore += 8;
        $passiveCount = $this->detectPassiveVoice($this->contentLower);
        $passivePct = $sentCount > 0 ? round(($passiveCount / $sentCount) * 100) : 0;
        if ($passivePct <= 10) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "Passive voice: {$passivePct}% of sentences (≤10% is ideal)", 'weight' => 8];
        } elseif ($passivePct <= 20) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "Passive voice: {$passivePct}% — aim for ≤10%", 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "Passive voice: {$passivePct}% — too many passive sentences", 'weight' => 8];
        }
        
        // Transition words
        $maxScore += 8;
        $transitionCount = $this->countTransitionWords($this->contentLower);
        $transitionPct = $sentCount > 0 ? round(($transitionCount / $sentCount) * 100) : 0;
        if ($transitionPct >= 30) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "Transition words: {$transitionPct}% of sentences — excellent", 'weight' => 8];
        } elseif ($transitionPct >= 20) {
            $score += 5;
            $checks[] = ['status' => 'warn', 'text' => "Transition words: {$transitionPct}% — aim 30%+", 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "Transition words: {$transitionPct}% — add more transitions", 'weight' => 8];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Readability',
            'flesch_score' => $flesch,
            'reading_level' => $readingLevel,
            'avg_sentence_length' => $avgSentLen,
            'passive_voice_pct' => $passivePct,
            'transition_pct' => $transitionPct
        ];
    }
    
    // ============================================================
    // 4. CONTENT QUALITY ENGINE (MarketMuse + Surfer)
    // ============================================================
    private function analyzeContentQuality(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        // Word count / content depth
        $maxScore += 12;
        if ($this->wordCount >= 2000) {
            $score += 12;
            $checks[] = ['status' => 'pass', 'text' => "{$this->wordCount} words — comprehensive content", 'weight' => 12];
        } elseif ($this->wordCount >= 1200) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "{$this->wordCount} words — good length", 'weight' => 12];
        } elseif ($this->wordCount >= 600) {
            $score += 5;
            $checks[] = ['status' => 'warn', 'text' => "{$this->wordCount} words — aim 1200+ for ranking", 'weight' => 12];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "{$this->wordCount} words — thin content, aim 800+", 'weight' => 12];
        }
        
        // Thin paragraph detection
        $maxScore += 6;
        $thinParas = 0;
        foreach ($this->paragraphs as $p) {
            $wc = str_word_count(trim($p));
            if ($wc > 0 && $wc < 20) $thinParas++;
        }
        if ($thinParas <= 2) {
            $score += 6;
            $checks[] = ['status' => 'pass', 'text' => 'Content has substantive paragraphs', 'weight' => 6];
        } else {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => "{$thinParas} thin paragraphs — expand with more detail", 'weight' => 6];
        }
        
        // Content uses formatting (bold, italic, lists)
        $maxScore += 6;
        $hasBold = (bool)preg_match('/<(strong|b)\b/i', $this->content);
        $hasLists = (bool)preg_match('/<(ul|ol)\b/i', $this->content);
        $hasImages = (bool)preg_match('/<img\b/i', $this->content);
        $formatScore = ($hasBold ? 2 : 0) + ($hasLists ? 2 : 0) + ($hasImages ? 2 : 0);
        $score += $formatScore;
        $items = [];
        if ($hasBold) $items[] = 'bold text';
        if ($hasLists) $items[] = 'lists';
        if ($hasImages) $items[] = 'images';
        if ($formatScore === 6) {
            $checks[] = ['status' => 'pass', 'text' => 'Rich formatting: ' . implode(', ', $items), 'weight' => 6];
        } elseif ($formatScore > 0) {
            $checks[] = ['status' => 'warn', 'text' => 'Formatting: ' . implode(', ', $items) . ' — add more variety', 'weight' => 6];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No formatting — add bold, lists, or images', 'weight' => 6];
        }
        
        // Duplicate/repetitive content check
        $maxScore += 6;
        $repetitionScore = $this->checkRepetition();
        if ($repetitionScore < 5) {
            $score += 6;
            $checks[] = ['status' => 'pass', 'text' => 'Content is original with low repetition', 'weight' => 6];
        } elseif ($repetitionScore < 15) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => 'Some repetitive phrases detected — vary your language', 'weight' => 6];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'High content repetition — rewrite sections for uniqueness', 'weight' => 6];
        }
        
        // Introduction quality (first 200 chars should be compelling)
        $maxScore += 5;
        $intro = mb_substr(strip_tags($this->content), 0, 200);
        $introWords = str_word_count($intro);
        if ($introWords >= 20 && mb_strlen($intro) >= 100) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'Introduction is substantive (' . $introWords . ' words)', 'weight' => 5];
        } else {
            $checks[] = ['status' => 'warn', 'text' => 'Introduction is too short — aim 100+ characters', 'weight' => 5];
        }
        
        // Content freshness (if date available)
        $maxScore += 5;
        $hasDate = !empty($this->content) && preg_match('/\b20[2-3]\d\b/', $this->content);
        if ($hasDate) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'Content references recent dates — freshness signal', 'weight' => 5];
        } else {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => 'Add current year/dates for freshness signals', 'weight' => 5];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Content Quality'
        ];
    }
    
    // ============================================================
    // 5. HEADING STRUCTURE ENGINE
    // ============================================================
    private function analyzeHeadingStructure(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        $h1Count = 0; $h2Count = 0; $h3Count = 0;
        foreach ($this->headings as $h) {
            switch ($h[1]) {
                case '1': $h1Count++; break;
                case '2': $h2Count++; break;
                case '3': $h3Count++; break;
            }
        }
        
        // H1 validation (should be exactly 1 or 0 in content — H1 is usually the page title)
        $maxScore += 8;
        if ($h1Count === 0) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'No H1 in content (page title serves as H1)', 'weight' => 8];
        } elseif ($h1Count === 1) {
            $score += 6;
            $checks[] = ['status' => 'warn', 'text' => '1 H1 found in content — usually page title is H1', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => "{$h1Count} H1 tags — use only 1 H1 per page", 'weight' => 8];
        }
        
        // H2 count
        $maxScore += 10;
        if ($h2Count >= 3) {
            $score += 10;
            $checks[] = ['status' => 'pass', 'text' => "{$h2Count} H2 subheadings — well structured", 'weight' => 10];
        } elseif ($h2Count >= 2) {
            $score += 7;
            $checks[] = ['status' => 'pass', 'text' => "{$h2Count} H2 subheadings — good", 'weight' => 10];
        } elseif ($h2Count === 1) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => 'Only 1 H2 — add more subheadings for structure', 'weight' => 10];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No H2 subheadings — add at least 2-3', 'weight' => 10];
        }
        
        // H3 usage
        $maxScore += 5;
        if ($h3Count >= 2) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => "{$h3Count} H3 sub-sections — detailed hierarchy", 'weight' => 5];
        } elseif ($h3Count > 0) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => "{$h3Count} H3 tag(s) — add more for depth", 'weight' => 5];
        } else {
            $score += 1;
            $checks[] = ['status' => 'warn', 'text' => 'No H3 tags — add sub-sections under H2s', 'weight' => 5];
        }
        
        // Heading hierarchy (no skipping levels)
        $maxScore += 7;
        $hierarchyOk = true;
        $prevLevel = 1;
        foreach ($this->headings as $h) {
            $level = (int)$h[1];
            if ($level > $prevLevel + 1) {
                $hierarchyOk = false;
                break;
            }
            $prevLevel = $level;
        }
        if ($hierarchyOk) {
            $score += 7;
            $checks[] = ['status' => 'pass', 'text' => 'Heading hierarchy is correct (no skipped levels)', 'weight' => 7];
        } else {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => 'Heading hierarchy has gaps — don\'t skip levels', 'weight' => 7];
        }
        
        // Heading length (not too short, not too long)
        $maxScore += 5;
        $badLenHeadings = 0;
        foreach ($this->headings as $h) {
            $hLen = mb_strlen(strip_tags($h[2]));
            if ($hLen < 5 || $hLen > 80) $badLenHeadings++;
        }
        if ($badLenHeadings === 0 && count($this->headings) > 0) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'All headings have good length (5-80 chars)', 'weight' => 5];
        } elseif ($badLenHeadings > 0) {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => "{$badLenHeadings} heading(s) too short or too long", 'weight' => 5];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Heading Structure',
            'h1_count' => $h1Count,
            'h2_count' => $h2Count,
            'h3_count' => $h3Count
        ];
    }
    
    // ============================================================
    // 6. EEAT ANALYSIS ENGINE
    // ============================================================
    private function analyzeEEAT(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        // Author attribution
        $maxScore += 8;
        $hasAuthor = (bool)preg_match('/\b(by |author|written by|reviewed by|edited by)\b/i', $this->content);
        if ($hasAuthor) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Author/reviewer attribution found', 'weight' => 8];
        } else {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => 'Add author attribution for E-E-A-T signals', 'weight' => 8];
        }
        
        // Citations / references
        $maxScore += 8;
        $hasCitations = (bool)preg_match('/\b(according to|research|study|source|reference|cited|data shows)\b/i', $this->content);
        $hasLinks = (bool)preg_match('/<a\b/i', $this->content);
        if ($hasCitations && $hasLinks) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Citations and references detected — strong trust signal', 'weight' => 8];
        } elseif ($hasCitations || $hasLinks) {
            $score += 5;
            $checks[] = ['status' => 'warn', 'text' => 'Some references found — add more citations with links', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No citations — add references to boost trust', 'weight' => 8];
        }
        
        // Experience signals
        $maxScore += 7;
        $experienceWords = ['we tested', 'we tried', 'in our experience', 'hands-on', 'personally', 'first-hand', 'we found', 'our team', 'we recommend', 'we use'];
        $expFound = 0;
        foreach ($experienceWords as $ew) {
            if (mb_strpos($this->contentLower, $ew) !== false) $expFound++;
        }
        if ($expFound >= 3) {
            $score += 7;
            $checks[] = ['status' => 'pass', 'text' => "Strong experience signals ({$expFound} markers found)", 'weight' => 7];
        } elseif ($expFound > 0) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "Some experience signals ({$expFound}) — add more first-hand insights", 'weight' => 7];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No experience signals — add personal/team experience', 'weight' => 7];
        }
        
        // Expertise signals
        $maxScore += 7;
        $expertWords = ['expert', 'specialist', 'certified', 'verified', 'tested', 'lab', 'quality', 'authentic', 'genuine', 'grade-a', 'premium'];
        $expertFound = 0;
        foreach ($expertWords as $ew) {
            if (mb_strpos($this->contentLower, $ew) !== false) $expertFound++;
        }
        if ($expertFound >= 3) {
            $score += 7;
            $checks[] = ['status' => 'pass', 'text' => "Expertise language detected ({$expertFound} terms)", 'weight' => 7];
        } elseif ($expertFound > 0) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "Some expertise terms ({$expertFound}) — demonstrate more knowledge", 'weight' => 7];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Add expertise language (verified, tested, certified)', 'weight' => 7];
        }
        
        // Trust signals
        $maxScore += 5;
        $trustWords = ['guarantee', 'refund', 'certified', 'verified', 'secure', 'authentic', 'trusted', 'official', 'licensed'];
        $trustFound = 0;
        foreach ($trustWords as $tw) {
            if (mb_strpos($this->contentLower, $tw) !== false) $trustFound++;
        }
        if ($trustFound >= 2) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => "Trust signals present ({$trustFound} markers)", 'weight' => 5];
        } elseif ($trustFound > 0) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => 'Some trust signals — add more (guarantee, verified, authentic)', 'weight' => 5];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No trust signals — add guarantees, certifications', 'weight' => 5];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'E-E-A-T Signals'
        ];
    }
    
    // ============================================================
    // 7. IMAGE SEO ENGINE
    // ============================================================
    private function analyzeImageSeo(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        preg_match_all('/<img\b([^>]*)>/i', $this->content, $imgMatches);
        $imgCount = count($imgMatches[0]);
        
        // Has images
        $maxScore += 8;
        if ($imgCount >= 2) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "{$imgCount} images in content — good visual content", 'weight' => 8];
        } elseif ($imgCount === 1) {
            $score += 5;
            $checks[] = ['status' => 'warn', 'text' => '1 image — add more for engagement', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No images in content — add relevant visuals', 'weight' => 8];
        }
        
        if ($imgCount > 0) {
            // ALT text validation
            $maxScore += 10;
            $missingAlt = 0;
            $emptyAlt = 0;
            foreach ($imgMatches[1] as $attrs) {
                if (!preg_match('/\balt\s*=/i', $attrs)) {
                    $missingAlt++;
                } elseif (preg_match('/\balt\s*=\s*["\']?\s*["\']?/i', $attrs)) {
                    $emptyAlt++;
                }
            }
            if ($missingAlt === 0 && $emptyAlt === 0) {
                $score += 10;
                $checks[] = ['status' => 'pass', 'text' => 'All images have descriptive ALT text', 'weight' => 10];
            } elseif ($missingAlt > 0) {
                $checks[] = ['status' => 'fail', 'text' => "{$missingAlt} image(s) missing ALT attribute", 'weight' => 10];
            } else {
                $score += 3;
                $checks[] = ['status' => 'warn', 'text' => "{$emptyAlt} image(s) have empty ALT text", 'weight' => 10];
            }
            
            // Lazy loading
            $maxScore += 5;
            $lazyCount = 0;
            foreach ($imgMatches[1] as $attrs) {
                if (preg_match('/loading\s*=\s*["\']?lazy/i', $attrs)) $lazyCount++;
            }
            if ($lazyCount === $imgCount) {
                $score += 5;
                $checks[] = ['status' => 'pass', 'text' => 'All images have lazy loading', 'weight' => 5];
            } elseif ($lazyCount > 0) {
                $score += 3;
                $checks[] = ['status' => 'warn', 'text' => "{$lazyCount}/{$imgCount} images have lazy loading", 'weight' => 5];
            } else {
                $checks[] = ['status' => 'fail', 'text' => 'No images have lazy loading — add loading="lazy"', 'weight' => 5];
            }
            
            // Keyword in ALT text
            $maxScore += 5;
            if ($this->focusKeyword) {
                $kwInAlt = false;
                foreach ($imgMatches[1] as $attrs) {
                    if (preg_match('/\balt\s*=\s*["\']([^"\']*)/i', $attrs, $altMatch)) {
                        if (mb_strpos(mb_strtolower($altMatch[1]), $this->focusKeyword) !== false) {
                            $kwInAlt = true;
                            break;
                        }
                    }
                }
                if ($kwInAlt) {
                    $score += 5;
                    $checks[] = ['status' => 'pass', 'text' => 'Focus keyword found in image ALT text', 'weight' => 5];
                } else {
                    $checks[] = ['status' => 'warn', 'text' => 'Add focus keyword to at least one image ALT', 'weight' => 5];
                }
            }
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Image SEO',
            'image_count' => $imgCount
        ];
    }
    
    // ============================================================
    // 8. INTERNAL LINKING ENGINE
    // ============================================================
    private function analyzeInternalLinks(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $this->content, $linkMatches, PREG_SET_ORDER);
        
        $internalLinks = [];
        $externalLinks = [];
        foreach ($linkMatches as $lm) {
            $href = $lm[1];
            if (preg_match('/^(https?:)?\/\/(www\.)?gilafstore\.com/i', $href) || preg_match('/^\/[^\/]/', $href)) {
                $internalLinks[] = ['url' => $href, 'text' => strip_tags($lm[2])];
            } elseif (preg_match('/^https?:\/\//i', $href)) {
                $externalLinks[] = ['url' => $href, 'text' => strip_tags($lm[2])];
            }
        }
        
        // Internal links count
        $maxScore += 10;
        $intCount = count($internalLinks);
        if ($intCount >= 3) {
            $score += 10;
            $checks[] = ['status' => 'pass', 'text' => "{$intCount} internal links — excellent for SEO", 'weight' => 10];
        } elseif ($intCount >= 1) {
            $score += 5;
            $checks[] = ['status' => 'warn', 'text' => "{$intCount} internal link(s) — aim for 3+", 'weight' => 10];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No internal links — add links to other pages/blogs', 'weight' => 10];
        }
        
        // External links (outbound)
        $maxScore += 5;
        $extCount = count($externalLinks);
        if ($extCount >= 1 && $extCount <= 5) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => "{$extCount} external link(s) — balanced outbound linking", 'weight' => 5];
        } elseif ($extCount > 5) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => "{$extCount} external links — may dilute link equity", 'weight' => 5];
        } else {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => 'No external links — add 1-2 authority references', 'weight' => 5];
        }
        
        // Noopener on external links
        $maxScore += 5;
        $noopenerMissing = 0;
        foreach ($linkMatches as $lm) {
            if (preg_match('/^https?:\/\//i', $lm[1]) && !preg_match('/gilafstore\.com/i', $lm[1])) {
                if (!preg_match('/rel\s*=\s*["\'][^"\']*noopener/i', $lm[0])) {
                    $noopenerMissing++;
                }
            }
        }
        if ($noopenerMissing === 0 && $extCount > 0) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'All external links have rel="noopener"', 'weight' => 5];
        } elseif ($noopenerMissing > 0) {
            $checks[] = ['status' => 'warn', 'text' => "{$noopenerMissing} external link(s) missing rel=\"noopener\"", 'weight' => 5];
        }
        
        // Anchor text quality
        $maxScore += 5;
        $genericAnchors = 0;
        $genericWords = ['click here', 'read more', 'here', 'link', 'this'];
        foreach ($internalLinks as $il) {
            if (in_array(mb_strtolower(trim($il['text'])), $genericWords)) $genericAnchors++;
        }
        if ($intCount > 0 && $genericAnchors === 0) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'Descriptive anchor text on all internal links', 'weight' => 5];
        } elseif ($genericAnchors > 0) {
            $score += 2;
            $checks[] = ['status' => 'warn', 'text' => "{$genericAnchors} link(s) with generic anchor text — use descriptive text", 'weight' => 5];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Internal Linking',
            'internal_count' => $intCount,
            'external_count' => $extCount,
            'internal_links' => $internalLinks,
            'external_links' => $externalLinks
        ];
    }
    
    // ============================================================
    // 9. AI SEARCH OPTIMIZATION
    // ============================================================
    private function analyzeAiSearchOptimization(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        // Direct answer format (starts with a clear definition/answer)
        $maxScore += 8;
        $firstSentence = trim($this->sentences[0] ?? '');
        $isDirectAnswer = preg_match('/\b(is|are|means|refers to|defined as|involves)\b/i', $firstSentence);
        if ($isDirectAnswer) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Content opens with direct answer — AI-friendly', 'weight' => 8];
        } else {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => 'Start with a direct answer/definition for AI extraction', 'weight' => 8];
        }
        
        // FAQ section presence
        $maxScore += 8;
        $hasFaqSection = (bool)preg_match('/frequently asked|faq|common questions/i', $this->content);
        $hasFaqSchema = (bool)preg_match('/FAQPage|Question.*acceptedAnswer/i', $this->content);
        if ($hasFaqSection) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'FAQ section detected — optimized for AI answers', 'weight' => 8];
        } else {
            $checks[] = ['status' => 'warn', 'text' => 'Add FAQ section for AI search optimization', 'weight' => 8];
        }
        
        // Structured lists/steps
        $maxScore += 7;
        $hasOrderedList = (bool)preg_match('/<ol\b/i', $this->content);
        $hasUnorderedList = (bool)preg_match('/<ul\b/i', $this->content);
        if ($hasOrderedList && $hasUnorderedList) {
            $score += 7;
            $checks[] = ['status' => 'pass', 'text' => 'Both ordered and unordered lists — snippet-ready', 'weight' => 7];
        } elseif ($hasOrderedList || $hasUnorderedList) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => 'List content detected — good for featured snippets', 'weight' => 7];
        } else {
            $checks[] = ['status' => 'warn', 'text' => 'Add lists for featured snippet eligibility', 'weight' => 7];
        }
        
        // Concise summary paragraph
        $maxScore += 7;
        $hasSummary = (bool)preg_match('/\b(in summary|to summarize|key takeaway|bottom line|conclusion|in conclusion|final thought)\b/i', $this->content);
        if ($hasSummary) {
            $score += 7;
            $checks[] = ['status' => 'pass', 'text' => 'Summary/conclusion section detected', 'weight' => 7];
        } else {
            $checks[] = ['status' => 'warn', 'text' => 'Add a summary/conclusion for AI answer extraction', 'weight' => 7];
        }
        
        // Conversational tone for voice search
        $maxScore += 5;
        $questionCount = preg_match_all('/\?/', $this->content);
        if ($questionCount >= 3) {
            $score += 5;
            $checks[] = ['status' => 'pass', 'text' => "{$questionCount} questions in content — voice search optimized", 'weight' => 5];
        } elseif ($questionCount > 0) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => "{$questionCount} question(s) — add more for voice search", 'weight' => 5];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'No questions — add question-answer format for voice search', 'weight' => 5];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'AI Search Optimization'
        ];
    }
    
    // ============================================================
    // 10. SEMANTIC SEO ENGINE (NLP)
    // ============================================================
    private function analyzeSemanticSeo(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        if (empty($this->focusKeyword)) {
            return [
                'score' => 0,
                'checks' => [['status' => 'warn', 'text' => 'Set focus keyword for semantic analysis', 'weight' => 30]],
                'label' => 'Semantic SEO'
            ];
        }
        
        // Generate semantic terms for common topics
        $semanticTerms = $this->getSemanticTerms($this->focusKeyword);
        
        // Semantic coverage
        $maxScore += 15;
        $foundTerms = [];
        $missingTerms = [];
        foreach ($semanticTerms as $term) {
            if (mb_strpos($this->contentLower, mb_strtolower($term)) !== false) {
                $foundTerms[] = $term;
            } else {
                $missingTerms[] = $term;
            }
        }
        $coverage = count($semanticTerms) > 0 ? round((count($foundTerms) / count($semanticTerms)) * 100) : 0;
        if ($coverage >= 60) {
            $score += 15;
            $checks[] = ['status' => 'pass', 'text' => "Semantic coverage: {$coverage}% ({" . count($foundTerms) . "} related terms found)", 'weight' => 15];
        } elseif ($coverage >= 30) {
            $score += 8;
            $checks[] = ['status' => 'warn', 'text' => "Semantic coverage: {$coverage}% — add more related terms", 'weight' => 15];
        } else {
            $score += 3;
            $checks[] = ['status' => 'fail', 'text' => "Semantic coverage: {$coverage}% — content lacks topical depth", 'weight' => 15];
        }
        
        // Topic depth (variety of entities)
        $maxScore += 10;
        $entities = $this->extractEntities($this->contentLower);
        $entityCount = count($entities);
        if ($entityCount >= 10) {
            $score += 10;
            $checks[] = ['status' => 'pass', 'text' => "{$entityCount} topical entities detected — deep coverage", 'weight' => 10];
        } elseif ($entityCount >= 5) {
            $score += 6;
            $checks[] = ['status' => 'warn', 'text' => "{$entityCount} entities — add more related concepts", 'weight' => 10];
        } else {
            $score += 2;
            $checks[] = ['status' => 'fail', 'text' => "Only {$entityCount} entities — expand topical coverage", 'weight' => 10];
        }
        
        // Content covers multiple angles
        $maxScore += 8;
        $angles = ['what', 'why', 'how', 'when', 'where', 'who', 'benefit', 'risk', 'cost', 'compare'];
        $anglesFound = 0;
        foreach ($angles as $a) {
            if (mb_strpos($this->contentLower, $a) !== false) $anglesFound++;
        }
        if ($anglesFound >= 5) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "Content covers {$anglesFound} angles — comprehensive", 'weight' => 8];
        } elseif ($anglesFound >= 3) {
            $score += 5;
            $checks[] = ['status' => 'warn', 'text' => "{$anglesFound} content angles — add more perspectives", 'weight' => 8];
        } else {
            $checks[] = ['status' => 'fail', 'text' => 'Content is one-dimensional — cover multiple angles', 'weight' => 8];
        }
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Semantic SEO',
            'found_terms' => $foundTerms,
            'missing_terms' => array_slice($missingTerms, 0, 10),
            'coverage' => $coverage
        ];
    }
    
    // ============================================================
    // 11. SCHEMA READINESS ENGINE
    // ============================================================
    private function analyzeSchemaReadiness(): array {
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        // Article schema readiness
        $maxScore += 8;
        $hasTitle = !empty($this->title);
        $hasDesc = !empty($this->metaDescription);
        $hasContent = $this->wordCount > 100;
        if ($hasTitle && $hasDesc && $hasContent) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => 'Article schema: all required fields present', 'weight' => 8];
        } else {
            $missing = [];
            if (!$hasTitle) $missing[] = 'title';
            if (!$hasDesc) $missing[] = 'description';
            if (!$hasContent) $missing[] = 'content';
            $checks[] = ['status' => 'fail', 'text' => 'Article schema missing: ' . implode(', ', $missing), 'weight' => 8];
        }
        
        // FAQ schema readiness
        $maxScore += 8;
        $faqPattern = preg_match_all('/\?\s*\n|<h[2-4][^>]*>.*\?.*<\/h[2-4]>/i', $this->content);
        if ($faqPattern >= 3) {
            $score += 8;
            $checks[] = ['status' => 'pass', 'text' => "FAQ schema eligible ({$faqPattern} Q&A patterns detected)", 'weight' => 8];
        } elseif ($faqPattern > 0) {
            $score += 4;
            $checks[] = ['status' => 'warn', 'text' => "{$faqPattern} Q&A pattern(s) — add more for FAQ schema", 'weight' => 8];
        } else {
            $checks[] = ['status' => 'warn', 'text' => 'No FAQ patterns — add Q&A for FAQ rich results', 'weight' => 8];
        }
        
        // HowTo schema readiness
        $maxScore += 6;
        $hasSteps = (bool)preg_match('/<ol\b/i', $this->content);
        $isHowTo = (bool)preg_match('/\b(how to|steps|guide|tutorial)\b/i', $this->title . ' ' . $this->content);
        if ($hasSteps && $isHowTo) {
            $score += 6;
            $checks[] = ['status' => 'pass', 'text' => 'HowTo schema eligible (steps + instructional content)', 'weight' => 6];
        } elseif ($isHowTo) {
            $score += 3;
            $checks[] = ['status' => 'warn', 'text' => 'Instructional content — add ordered list for HowTo schema', 'weight' => 6];
        }
        
        // Breadcrumb readiness
        $maxScore += 5;
        $score += 5;
        $checks[] = ['status' => 'pass', 'text' => 'Breadcrumb schema: auto-generated by system', 'weight' => 5];
        
        return [
            'score' => $maxScore > 0 ? round(($score / $maxScore) * 100) : 0,
            'checks' => $checks,
            'label' => 'Schema Readiness'
        ];
    }
    
    // ============================================================
    // HELPER FUNCTIONS
    // ============================================================
    
    private function estimateSyllables(string $text): int {
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $count = 0;
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', strtolower($word));
            if (strlen($word) <= 3) { $count += 1; continue; }
            $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
            $word = preg_replace('/^y/', '', $word);
            preg_match_all('/[aeiouy]{1,2}/', $word, $m);
            $count += max(1, count($m[0]));
        }
        return $count;
    }
    
    private function getReadingLevel(float $flesch): string {
        if ($flesch >= 90) return 'Very Easy (5th grade)';
        if ($flesch >= 80) return 'Easy (6th grade)';
        if ($flesch >= 70) return 'Fairly Easy (7th grade)';
        if ($flesch >= 60) return 'Standard (8th-9th grade)';
        if ($flesch >= 50) return 'Fairly Difficult (10th-12th grade)';
        if ($flesch >= 30) return 'Difficult (College)';
        return 'Very Difficult (Graduate)';
    }
    
    private function detectPassiveVoice(string $text): int {
        $passivePatterns = [
            '/\b(is|are|was|were|be|been|being)\s+(being\s+)?\w+ed\b/i',
            '/\b(is|are|was|were|be|been|being)\s+(being\s+)?\w+en\b/i',
            '/\b(is|are|was|were|be|been|being)\s+(being\s+)?(known|made|done|seen|taken|given|found|told|shown|left|kept|sent|brought|built|bought|caught|chosen|drawn|driven|eaten|fallen|felt|fought|forgotten|got|grown|heard|held|hidden|hit|hurt|led|lost|meant|met|paid|put|read|run|said|sold|shot|shown|shut|spoken|spent|stood|taught|thought|thrown|understood|won|written|worn)\b/i'
        ];
        $count = 0;
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sentences as $sent) {
            foreach ($passivePatterns as $pattern) {
                if (preg_match($pattern, $sent)) {
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }
    
    private function countTransitionWords(string $text): int {
        $transitions = [
            'however', 'therefore', 'furthermore', 'moreover', 'additionally', 'consequently',
            'in addition', 'on the other hand', 'for example', 'for instance', 'as a result',
            'in conclusion', 'in summary', 'meanwhile', 'nevertheless', 'nonetheless',
            'similarly', 'likewise', 'in contrast', 'specifically', 'in particular',
            'first', 'second', 'third', 'finally', 'next', 'then', 'also', 'besides',
            'although', 'even though', 'while', 'whereas', 'because', 'since', 'thus',
            'hence', 'accordingly', 'indeed', 'certainly', 'of course', 'in fact',
            'above all', 'most importantly', 'in other words', 'that is', 'namely'
        ];
        $count = 0;
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sentences as $sent) {
            $sentLower = mb_strtolower(trim($sent));
            foreach ($transitions as $t) {
                if (mb_strpos($sentLower, $t) !== false) {
                    $count++;
                    break;
                }
            }
        }
        return $count;
    }
    
    private function checkRepetition(): int {
        $words = preg_split('/\s+/', $this->contentLower, -1, PREG_SPLIT_NO_EMPTY);
        $phrases = [];
        for ($i = 0; $i < count($words) - 2; $i++) {
            $phrase = $words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2];
            if (mb_strlen($phrase) > 10) {
                $phrases[$phrase] = ($phrases[$phrase] ?? 0) + 1;
            }
        }
        $repeated = array_filter($phrases, fn($c) => $c > 3);
        return count($repeated);
    }
    
    private function getSemanticTerms(string $keyword): array {
        $kwLower = mb_strtolower($keyword);
        $semanticDb = [
            'saffron' => ['kesar', 'crocus sativus', 'stigma', 'spice', 'kashmir saffron', 'persian saffron', 'grade a', 'mogra', 'lachha', 'negin', 'purity test', 'color strength', 'aroma', 'crocin', 'safranal', 'picrocrocin', 'threads', 'flavor', 'premium saffron', 'authentic'],
            'olive oil' => ['extra virgin', 'cold pressed', 'kashmir olive', 'polyphenols', 'oleic acid', 'antioxidant', 'monounsaturated', 'cooking oil', 'health benefits', 'organic', 'pure olive oil', 'adulteration', 'smoke point', 'mediterranean', 'vitamin e'],
            'tea' => ['ctc tea', 'orthodox tea', 'green tea', 'black tea', 'kashmir tea', 'kahwa', 'chai', 'caffeine', 'antioxidants', 'brewing', 'loose leaf', 'tea garden', 'assam', 'darjeeling', 'flavor profile', 'aroma'],
            'honey' => ['raw honey', 'pure honey', 'organic honey', 'kashmir honey', 'acacia honey', 'wildflower', 'manuka', 'unprocessed', 'natural sweetener', 'antibacterial', 'pollen', 'enzymes', 'crystallization', 'health benefits'],
            'dry fruits' => ['almonds', 'walnuts', 'cashews', 'pistachios', 'raisins', 'dates', 'figs', 'apricots', 'nutrition', 'protein', 'omega-3', 'healthy snack', 'kashmir walnuts', 'premium quality', 'organic'],
        ];
        
        foreach ($semanticDb as $topic => $terms) {
            if (mb_strpos($kwLower, $topic) !== false || mb_strpos($topic, $kwLower) !== false) {
                return $terms;
            }
        }
        
        // Generic semantic terms
        return ['benefits', 'uses', 'types', 'quality', 'price', 'buy online', 'best', 'review', 'guide', 'comparison', 'features', 'advantages', 'tips', 'how to', 'where to buy'];
    }
    
    private function extractEntities(string $text): array {
        // Simple entity extraction — look for capitalized phrases, numbers, quoted terms
        $entities = [];
        preg_match_all('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+\b/', $text, $namedEntities);
        $entities = array_merge($entities, array_unique($namedEntities[0] ?? []));
        
        // Product-like entities
        preg_match_all('/\b(?:grade|type|variety|brand|quality)\s+\w+\b/i', $text, $productEntities);
        $entities = array_merge($entities, array_unique($productEntities[0] ?? []));
        
        return array_slice(array_unique($entities), 0, 20);
    }
    
    // ============================================================
    // 12. CTR OPTIMIZATION ENGINE
    // ============================================================
    public function analyzeCTR(string $title = '', string $metaDesc = ''): array {
        $title = $title ?: ($this->metaTitle ?: $this->title);
        $metaDesc = $metaDesc ?: $this->metaDescription;
        $checks = [];
        $score = 0;
        $maxScore = 0;
        $titleLower = mb_strtolower($title);
        
        // Power words
        $maxScore += 10;
        $powerWords = ['ultimate','proven','exclusive','essential','complete','definitive','powerful','incredible','amazing','secret','shocking','surprising','revolutionary','guaranteed','instant','effortless','free','premium','authentic','pure','original','best','top','luxury','rare','handcrafted','organic','natural'];
        $pwFound = 0;
        foreach ($powerWords as $pw) { if (mb_strpos($titleLower, $pw) !== false) $pwFound++; }
        if ($pwFound >= 2) { $score += 10; $checks[] = ['status'=>'pass','text'=>"{$pwFound} power words in title — strong CTR",'weight'=>10]; }
        elseif ($pwFound === 1) { $score += 6; $checks[] = ['status'=>'warn','text'=>"1 power word — add 1-2 more for higher CTR",'weight'=>10]; }
        else { $checks[] = ['status'=>'fail','text'=>'No power words in title — add words like "Ultimate", "Best", "Proven"','weight'=>10]; }
        
        // Numbers/year in title
        $maxScore += 8;
        $hasNumber = (bool)preg_match('/\d/', $title);
        $hasYear = (bool)preg_match('/20[2-3]\d/', $title);
        if ($hasYear) { $score += 8; $checks[] = ['status'=>'pass','text'=>'Current year in title — strong freshness CTR signal','weight'=>8]; }
        elseif ($hasNumber) { $score += 6; $checks[] = ['status'=>'pass','text'=>'Number in title — improves click-through rate','weight'=>8]; }
        else { $checks[] = ['status'=>'warn','text'=>'Add a number or year (e.g., "2025", "7 Best", "Top 10")','weight'=>8]; }
        
        // Emotional triggers
        $maxScore += 8;
        $emotionalWords = ['love','hate','fear','joy','worry','mistake','danger','warning','never','always','stop','avoid','must','need','hurry','limited','last chance','don\'t miss','life-changing','transform'];
        $emFound = 0;
        foreach ($emotionalWords as $ew) { if (mb_strpos($titleLower, $ew) !== false) $emFound++; }
        if ($emFound >= 1) { $score += 8; $checks[] = ['status'=>'pass','text'=>"Emotional trigger detected — drives clicks",'weight'=>8]; }
        else { $score += 3; $checks[] = ['status'=>'warn','text'=>'Add emotional trigger words for higher CTR','weight'=>8]; }
        
        // Brackets/parentheses (boost CTR by 38%)
        $maxScore += 6;
        $hasBrackets = (bool)preg_match('/[\[\(]/', $title);
        if ($hasBrackets) { $score += 6; $checks[] = ['status'=>'pass','text'=>'Brackets/parentheses in title — +38% CTR boost','weight'=>6]; }
        else { $checks[] = ['status'=>'warn','text'=>'Add brackets e.g., "[2025 Guide]", "(Tested)"','weight'=>6]; }
        
        // Buyer intent words
        $maxScore += 8;
        $buyerWords = ['buy','price','discount','deal','offer','sale','cheap','affordable','order','shop','compare','review','vs','worth','cost','value'];
        $biFound = 0;
        foreach ($buyerWords as $bw) { if (mb_strpos($titleLower, $bw) !== false) $biFound++; }
        if ($biFound >= 1) { $score += 8; $checks[] = ['status'=>'pass','text'=>"Buyer intent detected — attracts transactional clicks",'weight'=>8]; }
        else { $score += 2; $checks[] = ['status'=>'warn','text'=>'No buyer intent words — add "Buy", "Price", "Review" for commercial queries','weight'=>8]; }
        
        // Question format
        $maxScore += 5;
        $isQuestion = (bool)preg_match('/^(how|what|why|when|where|which|is|are|can|do|does|should|will)/i', $title);
        if ($isQuestion) { $score += 5; $checks[] = ['status'=>'pass','text'=>'Question format — matches voice search & People Also Ask','weight'=>5]; }
        
        // Title length CTR optimization (50-60 chars is ideal)
        $maxScore += 5;
        $tLen = mb_strlen($title);
        if ($tLen >= 50 && $tLen <= 60) { $score += 5; $checks[] = ['status'=>'pass','text'=>"Title length ({$tLen}) — optimal for CTR display",'weight'=>5]; }
        elseif ($tLen >= 40 && $tLen <= 65) { $score += 3; $checks[] = ['status'=>'warn','text'=>"Title length ({$tLen}) — aim 50-60 for full SERP display",'weight'=>5]; }
        else { $checks[] = ['status'=>'fail','text'=>"Title length ({$tLen}) — will be truncated in SERPs",'weight'=>5]; }
        
        // Meta description CTA
        $maxScore += 5;
        $ctaWords = ['discover','learn','get','find','explore','try','start','shop','order','click','read','see','check','grab'];
        $ctaFound = 0;
        $mdLower = mb_strtolower($metaDesc);
        foreach ($ctaWords as $cta) { if (mb_strpos($mdLower, $cta) !== false) $ctaFound++; }
        if ($ctaFound >= 1) { $score += 5; $checks[] = ['status'=>'pass','text'=>"Call-to-action in meta description — drives clicks",'weight'=>5]; }
        else { $checks[] = ['status'=>'warn','text'=>'Add CTA to meta description (Discover, Shop, Learn More)','weight'=>5]; }
        
        $ctrScore = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;
        return [
            'score' => $ctrScore,
            'checks' => $checks,
            'label' => 'CTR Optimization',
            'estimated_ctr' => $this->estimateCTRFromScore($ctrScore),
            'power_words_count' => $pwFound,
            'has_number' => $hasNumber,
            'has_emotion' => $emFound > 0,
            'has_buyer_intent' => $biFound > 0
        ];
    }
    
    private function estimateCTRFromScore(int $score): string {
        if ($score >= 80) return 'High (8-12% estimated)';
        if ($score >= 60) return 'Above Average (5-8%)';
        if ($score >= 40) return 'Average (3-5%)';
        return 'Below Average (<3%)';
    }
    
    // ============================================================
    // 13. "WHY NOT RANKING" DIAGNOSIS ENGINE
    // ============================================================
    public function diagnoseWhyNotRanking(array $results, string $keyword = ''): array {
        $blockers = [];
        $keyword = $keyword ?: $this->focusKeyword;
        
        // Analyze each result section for ranking blockers
        $sectionChecks = [
            'basic_seo' => ['name' => 'Basic SEO', 'impact_multiplier' => 1.0],
            'keyword' => ['name' => 'Keyword Targeting', 'impact_multiplier' => 1.0],
            'readability' => ['name' => 'Readability', 'impact_multiplier' => 0.6],
            'content_quality' => ['name' => 'Content Quality', 'impact_multiplier' => 0.9],
            'heading_structure' => ['name' => 'Content Structure', 'impact_multiplier' => 0.5],
            'eeat' => ['name' => 'E-E-A-T Signals', 'impact_multiplier' => 0.8],
            'image_seo' => ['name' => 'Image SEO', 'impact_multiplier' => 0.3],
            'internal_links' => ['name' => 'Internal Linking', 'impact_multiplier' => 0.7],
            'ai_search' => ['name' => 'AI Search Optimization', 'impact_multiplier' => 0.5],
            'semantic' => ['name' => 'Semantic SEO', 'impact_multiplier' => 0.8],
            'schema' => ['name' => 'Schema/Structured Data', 'impact_multiplier' => 0.6],
            'ctr' => ['name' => 'CTR Optimization', 'impact_multiplier' => 0.7],
        ];
        
        foreach ($sectionChecks as $key => $meta) {
            if (!isset($results[$key])) continue;
            $section = $results[$key];
            $sectionScore = $section['score'] ?? 100;
            
            // Extract failed/warned checks as blockers
            foreach ($section['checks'] ?? [] as $check) {
                if ($check['status'] === 'fail') {
                    $weight = $check['weight'] ?? 5;
                    $impact = min(100, round($weight * $meta['impact_multiplier'] * 10));
                    $blockers[] = [
                        'category' => $meta['name'],
                        'issue' => $check['text'],
                        'severity' => $impact >= 70 ? 'critical' : ($impact >= 40 ? 'high' : 'medium'),
                        'ranking_impact' => $impact,
                        'why_it_matters' => $this->getWhyItMatters($key, $check['text']),
                        'fix_type' => $this->getFixType($key, $check['text']),
                    ];
                } elseif ($check['status'] === 'warn' && ($check['weight'] ?? 0) >= 8) {
                    $weight = $check['weight'] ?? 5;
                    $impact = min(80, round($weight * $meta['impact_multiplier'] * 6));
                    $blockers[] = [
                        'category' => $meta['name'],
                        'issue' => $check['text'],
                        'severity' => $impact >= 50 ? 'high' : 'medium',
                        'ranking_impact' => $impact,
                        'why_it_matters' => $this->getWhyItMatters($key, $check['text']),
                        'fix_type' => $this->getFixType($key, $check['text']),
                    ];
                }
            }
        }
        
        // Add cross-section blockers
        $overall = $results['overall_score'] ?? 0;
        if ($overall < 40) {
            array_unshift($blockers, [
                'category' => 'Overall',
                'issue' => "Overall SEO score is critically low ({$overall}/100)",
                'severity' => 'critical',
                'ranking_impact' => 95,
                'why_it_matters' => 'Pages with low overall SEO optimization have virtually no chance of ranking on Google\'s first page.',
                'fix_type' => 'ai_rewrite',
            ]);
        }
        
        // Content depth vs competitor
        $wc = $results['content_quality']['checks'][0]['text'] ?? '';
        if (preg_match('/(\d+)\s*words/', $wc, $m) && (int)$m[1] < 800) {
            $blockers[] = [
                'category' => 'Content Depth',
                'issue' => "Thin content ({$m[1]} words) — competitors average 1500-2500 words",
                'severity' => 'critical',
                'ranking_impact' => 90,
                'why_it_matters' => 'Google\'s Helpful Content system penalizes thin content. Top-ranking pages for competitive keywords average 1500+ words.',
                'fix_type' => 'ai_expand',
            ];
        }
        
        // Search intent mismatch detection
        if (!empty($keyword)) {
            $hasTransactional = (bool)preg_match('/\b(buy|price|order|shop|discount|deal|cost|cheap|affordable)\b/i', $keyword);
            $contentHasTransactional = (bool)preg_match('/\b(buy|add to cart|order|price|shop|₹|\$|discount|offer)\b/i', $this->content ?? '');
            if ($hasTransactional && !$contentHasTransactional) {
                $blockers[] = [
                    'category' => 'Search Intent',
                    'issue' => "Keyword \"{$keyword}\" has transactional intent but page lacks buy/price signals",
                    'severity' => 'critical',
                    'ranking_impact' => 85,
                    'why_it_matters' => 'Google matches search intent precisely. Transactional queries need pricing, buy buttons, and product details.',
                    'fix_type' => 'ai_intent_fix',
                ];
            }
        }
        
        // Sort by ranking impact descending
        usort($blockers, fn($a, $b) => $b['ranking_impact'] <=> $a['ranking_impact']);
        
        // Calculate ranking probability
        $criticalCount = count(array_filter($blockers, fn($b) => $b['severity'] === 'critical'));
        $highCount = count(array_filter($blockers, fn($b) => $b['severity'] === 'high'));
        $top10Prob = max(0, min(95, $overall - ($criticalCount * 15) - ($highCount * 5)));
        $top3Prob = max(0, $top10Prob - 30 - ($criticalCount * 10));
        
        return [
            'blockers' => array_slice($blockers, 0, 15),
            'total_blockers' => count($blockers),
            'critical_count' => $criticalCount,
            'high_count' => $highCount,
            'medium_count' => count(array_filter($blockers, fn($b) => $b['severity'] === 'medium')),
            'ranking_probability' => [
                'top_10' => max(0, min(95, $top10Prob)) . '%',
                'top_3' => max(0, min(90, $top3Prob)) . '%',
                'difficulty' => $criticalCount >= 3 ? 'Very Hard' : ($criticalCount >= 1 ? 'Hard' : ($highCount >= 3 ? 'Moderate' : 'Achievable')),
            ],
            'overall_score' => $overall,
            'summary' => $this->generateRankingSummary($blockers, $overall),
        ];
    }
    
    private function getWhyItMatters(string $section, string $issue): string {
        $reasons = [
            'basic_seo' => 'Meta tags are the first thing Google evaluates. Missing or poorly optimized meta tags directly reduce your visibility in search results.',
            'keyword' => 'Keyword placement tells Google what your page is about. Without proper keyword signals, Google cannot match your page to user searches.',
            'readability' => 'Google\'s Helpful Content system evaluates user experience. Poor readability increases bounce rates and reduces dwell time.',
            'content_quality' => 'Content depth and quality are primary ranking factors. Google\'s algorithms compare your content against competitors and prefer comprehensive coverage.',
            'heading_structure' => 'Headings help Google understand content hierarchy and extract featured snippets. Poor structure hurts passage indexing.',
            'eeat' => 'E-E-A-T (Experience, Expertise, Authoritativeness, Trust) is a core ranking signal. Google\'s Quality Raters specifically evaluate these factors.',
            'image_seo' => 'Image optimization affects page speed, accessibility, and provides additional ranking opportunities in Google Image Search.',
            'internal_links' => 'Internal links distribute PageRank and help Google discover and understand page relationships. Weak linking isolates your content.',
            'ai_search' => 'AI Overview and Featured Snippets capture 60%+ of clicks. Pages not optimized for AI extraction miss these opportunities.',
            'semantic' => 'Google uses NLP to understand topic coverage. Missing semantic entities signal shallow content that won\'t satisfy user intent.',
            'schema' => 'Structured data enables rich results (stars, FAQs, prices) which dramatically improve CTR and visibility.',
            'ctr' => 'Click-through rate is a behavioral ranking signal. Higher CTR titles get promoted; low CTR titles get demoted.',
        ];
        return $reasons[$section] ?? 'This issue directly impacts how Google evaluates and ranks your page.';
    }
    
    private function getFixType(string $section, string $issue): string {
        $fixMap = [
            'basic_seo' => 'ai_rewrite_meta',
            'keyword' => 'ai_keyword_optimize',
            'readability' => 'ai_rewrite_content',
            'content_quality' => 'ai_expand',
            'heading_structure' => 'ai_restructure',
            'eeat' => 'ai_add_eeat',
            'image_seo' => 'ai_fix_images',
            'internal_links' => 'ai_add_links',
            'ai_search' => 'ai_add_faq',
            'semantic' => 'ai_add_entities',
            'schema' => 'generate_schema',
            'ctr' => 'ai_rewrite_title',
        ];
        return $fixMap[$section] ?? 'ai_fix';
    }
    
    private function generateRankingSummary(array $blockers, int $overall): string {
        if (empty($blockers)) return 'Your page is well-optimized. Focus on building backlinks and topical authority.';
        $critical = count(array_filter($blockers, fn($b) => $b['severity'] === 'critical'));
        $top = $blockers[0] ?? null;
        if ($critical >= 3) return "Your page has {$critical} critical ranking blockers. The most impactful: {$top['issue']}. Address these before expecting any ranking improvement.";
        if ($critical >= 1) return "You have {$critical} critical issue(s) preventing ranking. Top blocker: {$top['issue']}. Fix critical issues first for fastest results.";
        return "Your page has " . count($blockers) . " optimization opportunities. Addressing the top issues could significantly improve your ranking potential.";
    }
    
    // ============================================================
    // 14. PRODUCT DEEP ANALYZER
    // ============================================================
    public function analyzeProductPage(array $product): array {
        $name = $product['name'] ?? '';
        $desc = $product['description'] ?? '';
        $shortDesc = $product['short_description'] ?? '';
        $seoTitle = $product['seo_title'] ?? $name;
        $seoDesc = $product['seo_description'] ?? '';
        $seoKw = $product['seo_keywords'] ?? '';
        $slug = $product['slug'] ?? '';
        $price = $product['price'] ?? 0;
        $image = $product['image'] ?? '';
        
        $plainDesc = strip_tags($desc);
        $wordCount = str_word_count($plainDesc);
        $descLower = mb_strtolower($plainDesc);
        
        $checks = [];
        $score = 0;
        $maxScore = 0;
        
        // === TITLE ANALYSIS ===
        $maxScore += 10;
        $titleLen = mb_strlen($seoTitle);
        if ($titleLen >= 30 && $titleLen <= 60) { $score += 10; $checks[] = ['status'=>'pass','text'=>"Product title length ({$titleLen}) — ideal for SERP",'weight'=>10,'category'=>'Title']; }
        elseif ($titleLen > 0) { $score += 5; $checks[] = ['status'=>'warn','text'=>"Product title ({$titleLen} chars) — aim 30-60",'weight'=>10,'category'=>'Title']; }
        else { $checks[] = ['status'=>'fail','text'=>'No product SEO title set','weight'=>10,'category'=>'Title']; }
        
        // Buyer intent in title
        $maxScore += 8;
        $titleLower = mb_strtolower($seoTitle);
        $buyerWords = ['buy','price','online','shop','order','best','premium','authentic','pure','organic','original'];
        $biCount = 0;
        foreach ($buyerWords as $bw) { if (mb_strpos($titleLower, $bw) !== false) $biCount++; }
        if ($biCount >= 1) { $score += 8; $checks[] = ['status'=>'pass','text'=>"Buyer intent in title ({$biCount} signals)",'weight'=>8,'category'=>'Title']; }
        else { $checks[] = ['status'=>'fail','text'=>'No buyer intent — add "Buy", "Online", "Best", "Premium"','weight'=>8,'category'=>'Title']; }
        
        // === DESCRIPTION ANALYSIS ===
        $maxScore += 12;
        if ($wordCount >= 300) { $score += 12; $checks[] = ['status'=>'pass','text'=>"Product description: {$wordCount} words — comprehensive",'weight'=>12,'category'=>'Content']; }
        elseif ($wordCount >= 150) { $score += 7; $checks[] = ['status'=>'warn','text'=>"Description: {$wordCount} words — aim 300+ for ranking",'weight'=>12,'category'=>'Content']; }
        elseif ($wordCount >= 50) { $score += 3; $checks[] = ['status'=>'fail','text'=>"Thin description ({$wordCount} words) — expand significantly",'weight'=>12,'category'=>'Content']; }
        else { $checks[] = ['status'=>'fail','text'=>'Product description too short or missing','weight'=>12,'category'=>'Content']; }
        
        // Trust signals in description
        $maxScore += 8;
        $trustWords = ['guarantee','authentic','certified','tested','verified','natural','pure','quality','handmade','handcrafted','organic','traditional','genuine'];
        $trustFound = 0;
        foreach ($trustWords as $tw) { if (mb_strpos($descLower, $tw) !== false) $trustFound++; }
        if ($trustFound >= 3) { $score += 8; $checks[] = ['status'=>'pass','text'=>"Strong trust signals ({$trustFound} markers)",'weight'=>8,'category'=>'Trust']; }
        elseif ($trustFound >= 1) { $score += 4; $checks[] = ['status'=>'warn','text'=>"Some trust signals ({$trustFound}) — add more",'weight'=>8,'category'=>'Trust']; }
        else { $checks[] = ['status'=>'fail','text'=>'No trust signals — add "authentic", "certified", "guaranteed"','weight'=>8,'category'=>'Trust']; }
        
        // Benefits mentioned
        $maxScore += 7;
        $benefitWords = ['benefit','advantage','helps','improves','boosts','reduces','prevents','protects','enhances','supports','promotes','strengthens'];
        $benFound = 0;
        foreach ($benefitWords as $bw) { if (mb_strpos($descLower, $bw) !== false) $benFound++; }
        if ($benFound >= 2) { $score += 7; $checks[] = ['status'=>'pass','text'=>"Benefits clearly stated ({$benFound} found)",'weight'=>7,'category'=>'Content']; }
        elseif ($benFound >= 1) { $score += 4; $checks[] = ['status'=>'warn','text'=>'Few benefits mentioned — expand on product advantages','weight'=>7,'category'=>'Content']; }
        else { $checks[] = ['status'=>'fail','text'=>'No benefits stated — add "helps with...", "improves..."','weight'=>7,'category'=>'Content']; }
        
        // === SEO FIELDS ===
        $maxScore += 8;
        if (!empty($seoDesc) && mb_strlen($seoDesc) >= 100) { $score += 8; $checks[] = ['status'=>'pass','text'=>'SEO description present and adequate','weight'=>8,'category'=>'Meta']; }
        elseif (!empty($seoDesc)) { $score += 4; $checks[] = ['status'=>'warn','text'=>'SEO description too short — aim 120-160 chars','weight'=>8,'category'=>'Meta']; }
        else { $checks[] = ['status'=>'fail','text'=>'No SEO meta description — critical for CTR','weight'=>8,'category'=>'Meta']; }
        
        $maxScore += 6;
        if (!empty($seoKw)) { $score += 6; $checks[] = ['status'=>'pass','text'=>'SEO keywords set','weight'=>6,'category'=>'Meta']; }
        else { $checks[] = ['status'=>'fail','text'=>'No SEO keywords — set target keywords','weight'=>6,'category'=>'Meta']; }
        
        // === SLUG ===
        $maxScore += 5;
        if (!empty($slug) && mb_strlen($slug) <= 60 && preg_match('/^[a-z0-9\-]+$/', $slug)) { $score += 5; $checks[] = ['status'=>'pass','text'=>'Product URL slug is SEO-friendly','weight'=>5,'category'=>'URL']; }
        elseif (!empty($slug)) { $score += 2; $checks[] = ['status'=>'warn','text'=>'URL slug could be optimized','weight'=>5,'category'=>'URL']; }
        else { $checks[] = ['status'=>'fail','text'=>'No URL slug set','weight'=>5,'category'=>'URL']; }
        
        // === IMAGE ===
        $maxScore += 6;
        if (!empty($image)) { $score += 6; $checks[] = ['status'=>'pass','text'=>'Product image present','weight'=>6,'category'=>'Image']; }
        else { $checks[] = ['status'=>'fail','text'=>'No product image — critical for conversions and SEO','weight'=>6,'category'=>'Image']; }
        
        // === SCHEMA READINESS ===
        $maxScore += 8;
        $schemaReady = !empty($name) && $price > 0 && !empty($image) && !empty($seoDesc);
        if ($schemaReady) { $score += 8; $checks[] = ['status'=>'pass','text'=>'Product schema eligible (name, price, image, description)','weight'=>8,'category'=>'Schema']; }
        else {
            $missing = [];
            if (empty($name)) $missing[] = 'name';
            if ($price <= 0) $missing[] = 'price';
            if (empty($image)) $missing[] = 'image';
            if (empty($seoDesc)) $missing[] = 'description';
            $checks[] = ['status'=>'fail','text'=>'Product schema incomplete — missing: ' . implode(', ', $missing),'weight'=>8,'category'=>'Schema'];
        }
        
        // === SEARCH INTENT ===
        $maxScore += 7;
        $hasPrice = $price > 0 || (bool)preg_match('/₹|\$|price|cost|mrp/i', $desc);
        $hasCTA = (bool)preg_match('/add to cart|buy now|order|shop|get yours/i', $desc);
        $intentScore = ($hasPrice ? 3 : 0) + ($hasCTA ? 4 : 0);
        $score += $intentScore;
        if ($intentScore >= 7) { $checks[] = ['status'=>'pass','text'=>'Strong transactional intent signals','weight'=>7,'category'=>'Intent']; }
        elseif ($intentScore >= 3) { $checks[] = ['status'=>'warn','text'=>'Partial transactional signals — add price display and CTA','weight'=>7,'category'=>'Intent']; }
        else { $checks[] = ['status'=>'fail','text'=>'Missing transactional intent — add pricing and buy CTA','weight'=>7,'category'=>'Intent']; }
        
        $productScore = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;
        
        // CTR analysis
        $ctr = $this->analyzeCTR($seoTitle, $seoDesc);
        
        return [
            'product_score' => $productScore,
            'ctr' => $ctr,
            'checks' => $checks,
            'word_count' => $wordCount,
            'categories' => $this->groupChecksByCategory($checks),
            'schema_ready' => $schemaReady,
        ];
    }
    
    private function groupChecksByCategory(array $checks): array {
        $grouped = [];
        foreach ($checks as $c) {
            $cat = $c['category'] ?? 'General';
            $grouped[$cat][] = $c;
        }
        return $grouped;
    }
    
    // ============================================================
    // CALCULATE OVERALL SCORE
    // ============================================================
    public function calculateOverallScore(array $results): int {
        $weights = [
            'basic_seo' => 18,
            'keyword' => 18,
            'readability' => 12,
            'content_quality' => 12,
            'heading_structure' => 7,
            'eeat' => 8,
            'image_seo' => 4,
            'internal_links' => 5,
            'ai_search' => 4,
            'semantic' => 5,
            'schema' => 3,
            'ctr' => 4,
        ];
        
        $totalWeight = 0;
        $weightedScore = 0;
        foreach ($weights as $key => $weight) {
            if (isset($results[$key]['score'])) {
                $weightedScore += $results[$key]['score'] * $weight;
                $totalWeight += $weight;
            }
        }
        
        return $totalWeight > 0 ? round($weightedScore / $totalWeight) : 0;
    }
}

// ============================================================
// TECHNICAL SEO SCANNER (Site-Wide)
// ============================================================
class GilafTechnicalSeoScanner {
    
    private $db;
    private $baseUrl;
    
    public function __construct($db, $baseUrl = 'https://gilafstore.com') {
        $this->db = $db;
        $this->baseUrl = $baseUrl;
    }
    
    /**
     * Scan all blogs for SEO issues
     */
    public function scanBlogs(): array {
        $issues = [];
        $blogs = $this->db->query("SELECT id, title, slug, meta_title, meta_description, meta_keywords, content, excerpt, featured_image, status FROM blogs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($blogs as $blog) {
            $blogIssues = [];
            
            // Missing meta title
            if (empty($blog['meta_title'])) {
                $blogIssues[] = ['type' => 'error', 'text' => 'Missing meta title'];
            } elseif (mb_strlen($blog['meta_title']) > 60) {
                $blogIssues[] = ['type' => 'warning', 'text' => 'Meta title too long (' . mb_strlen($blog['meta_title']) . ' chars)'];
            }
            
            // Missing meta description
            if (empty($blog['meta_description'])) {
                $blogIssues[] = ['type' => 'error', 'text' => 'Missing meta description'];
            } elseif (mb_strlen($blog['meta_description']) > 160) {
                $blogIssues[] = ['type' => 'warning', 'text' => 'Meta description too long'];
            }
            
            // Missing keywords
            if (empty($blog['meta_keywords'])) {
                $blogIssues[] = ['type' => 'warning', 'text' => 'No focus keywords set'];
            }
            
            // Thin content
            $wordCount = str_word_count(strip_tags($blog['content'] ?? ''));
            if ($wordCount < 300) {
                $blogIssues[] = ['type' => 'error', 'text' => "Thin content ({$wordCount} words)"];
            }
            
            // Missing featured image
            if (empty($blog['featured_image'])) {
                $blogIssues[] = ['type' => 'warning', 'text' => 'No featured image'];
            }
            
            // Missing excerpt
            if (empty($blog['excerpt'])) {
                $blogIssues[] = ['type' => 'warning', 'text' => 'No excerpt'];
            }
            
            // Duplicate slug check
            $dupeCheck = $this->db->prepare("SELECT COUNT(*) FROM blogs WHERE slug = ? AND id != ?");
            $dupeCheck->execute([$blog['slug'], $blog['id']]);
            if ($dupeCheck->fetchColumn() > 0) {
                $blogIssues[] = ['type' => 'error', 'text' => 'Duplicate slug detected'];
            }
            
            if (!empty($blogIssues)) {
                $issues[] = [
                    'id' => $blog['id'],
                    'title' => $blog['title'],
                    'slug' => $blog['slug'],
                    'status' => $blog['status'],
                    'word_count' => $wordCount,
                    'issues' => $blogIssues,
                    'issue_count' => count($blogIssues),
                    'error_count' => count(array_filter($blogIssues, fn($i) => $i['type'] === 'error')),
                    'warning_count' => count(array_filter($blogIssues, fn($i) => $i['type'] === 'warning'))
                ];
            }
        }
        
        return [
            'total_blogs' => count($blogs),
            'blogs_with_issues' => count($issues),
            'total_errors' => array_sum(array_column($issues, 'error_count')),
            'total_warnings' => array_sum(array_column($issues, 'warning_count')),
            'items' => $issues
        ];
    }
    
    /**
     * Scan all products for SEO issues
     */
    public function scanProducts(): array {
        $issues = [];
        try {
            $products = $this->db->query("SELECT id, name, slug, seo_title, seo_description, seo_keywords, description, short_description, image FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['total_products' => 0, 'products_with_issues' => 0, 'total_errors' => 0, 'total_warnings' => 0, 'items' => []];
        }
        
        foreach ($products as $product) {
            $productIssues = [];
            
            if (empty($product['seo_title']) && empty($product['name'])) {
                $productIssues[] = ['type' => 'error', 'text' => 'Missing product name/title'];
            }
            
            if (empty($product['seo_description']) && empty($product['short_description'])) {
                $productIssues[] = ['type' => 'warning', 'text' => 'No SEO description'];
            }
            
            if (empty($product['slug'])) {
                $productIssues[] = ['type' => 'error', 'text' => 'Missing URL slug'];
            }
            
            if (empty($product['image'])) {
                $productIssues[] = ['type' => 'warning', 'text' => 'No product image'];
            }
            
            $descLen = mb_strlen(strip_tags($product['description'] ?? ''));
            if ($descLen < 100) {
                $productIssues[] = ['type' => 'warning', 'text' => "Short description ({$descLen} chars)"];
            }
            
            if (!empty($productIssues)) {
                $issues[] = [
                    'id' => $product['id'],
                    'title' => $product['name'],
                    'slug' => $product['slug'],
                    'issues' => $productIssues,
                    'issue_count' => count($productIssues),
                    'error_count' => count(array_filter($productIssues, fn($i) => $i['type'] === 'error')),
                    'warning_count' => count(array_filter($productIssues, fn($i) => $i['type'] === 'warning'))
                ];
            }
        }
        
        return [
            'total_products' => count($products),
            'products_with_issues' => count($issues),
            'total_errors' => array_sum(array_column($issues, 'error_count')),
            'total_warnings' => array_sum(array_column($issues, 'warning_count')),
            'items' => $issues
        ];
    }
    
    /**
     * Enterprise Semantic Internal Linking Engine v2
     * — Cached, deduplicated, AI-scored, cannibalization-aware
     */
    public function findLinkOpportunities(): array {
        @set_time_limit(120);
        
        // Try cache first (5-min TTL)
        $cacheFile = __DIR__ . '/../cache/seo/link_opps_cache.json';
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached && !empty($cached['opportunities'])) return $cached;
        }
        
        $blogs = $this->db->query("SELECT id, title, slug, content, meta_keywords, excerpt FROM blogs WHERE status='published' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $products = [];
        try { $products = $this->db->query("SELECT id, name, slug FROM products")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e) {}
        
        // Pre-compute blog data to avoid repeated work
        $blogData = [];
        $allLinks = [];          // slug => [list of blog IDs that link to it]
        $keywordMap = [];        // keyword => [blog IDs targeting it]
        $internalLinkCount = []; // blog ID => count of outbound internal links
        $inboundLinkCount = [];  // blog ID => count of inbound internal links
        
        foreach ($blogs as $blog) {
            $id = $blog['id'];
            $contentRaw = $blog['content'] ?? '';
            $contentPlain = mb_strtolower(strip_tags($contentRaw));
            $wordCount = str_word_count($contentPlain);
            
            // Extract existing links
            preg_match_all('/href=["\']([^"\']+)/i', $contentRaw, $linkMatches);
            $existingLinks = $linkMatches[1] ?? [];
            $existingSlugs = [];
            foreach ($existingLinks as $link) {
                foreach ($blogs as $ob) {
                    if (mb_strpos($link, $ob['slug']) !== false && $ob['id'] !== $id) {
                        $existingSlugs[$ob['slug']] = true;
                        $inboundLinkCount[$ob['id']] = ($inboundLinkCount[$ob['id']] ?? 0) + 1;
                    }
                }
                foreach ($products as $p) {
                    if (mb_strpos($link, $p['slug']) !== false) $existingSlugs[$p['slug']] = true;
                }
            }
            $internalLinkCount[$id] = count($existingSlugs);
            
            // Map keywords for cannibalization detection
            $kws = array_filter(array_map('trim', explode(',', mb_strtolower($blog['meta_keywords'] ?? ''))));
            foreach ($kws as $kw) {
                if (mb_strlen($kw) > 2) $keywordMap[$kw][] = $id;
            }
            
            $blogData[$id] = [
                'id' => $id,
                'title' => $blog['title'],
                'slug' => $blog['slug'],
                'content_lower' => $contentPlain,
                'word_count' => $wordCount,
                'keywords' => $kws,
                'existing_slugs' => $existingSlugs,
                'link_count_out' => count($existingSlugs),
            ];
        }
        
        $opportunities = [];
        $seenPairs = [];
        $seenAnchors = [];   // per source: track anchor texts to avoid repetition
        $seenTargets = [];   // per source: track targets to avoid duplicates
        
        foreach ($blogData as $srcId => $src) {
            $srcAnchors = [];
            $srcTargets = [];
            
            // === Blog → Blog matching ===
            foreach ($blogData as $tgtId => $tgt) {
                if ($tgtId === $srcId) continue;
                
                // Skip if already linked
                if (isset($src['existing_slugs'][$tgt['slug']])) continue;
                
                // Skip duplicate pair
                $pairKey = min($srcId, $tgtId) . ':' . max($srcId, $tgtId);
                if (isset($seenPairs[$pairKey])) continue;
                
                // Skip if source already has too many outbound links (avoid over-linking)
                $maxLinks = max(3, intval($src['word_count'] / 300));
                if (count($srcTargets) >= $maxLinks) break;
                
                // Check mentions: title match, keyword match, slug match
                $matchType = null;
                $anchor = '';
                $relevanceBoost = 0;
                
                $tgtTitleLower = mb_strtolower($tgt['title']);
                if (mb_strpos($src['content_lower'], $tgtTitleLower) !== false) {
                    $matchType = 'title_mention';
                    $anchor = $tgt['title'];
                    $relevanceBoost = 0.3;
                } else {
                    // Check keyword overlap
                    $commonKws = array_intersect($src['keywords'], $tgt['keywords']);
                    if (!empty($commonKws)) {
                        $matchType = 'keyword_overlap';
                        $anchor = ucfirst(reset($commonKws));
                        $relevanceBoost = 0.2;
                    } else {
                        // Check significant title words (2+ word match)
                        $tgtWords = array_filter(explode(' ', $tgtTitleLower), fn($w) => mb_strlen($w) > 3);
                        foreach (array_chunk($tgtWords, 2) as $chunk) {
                            if (count($chunk) < 2) continue;
                            $phrase = implode(' ', $chunk);
                            if (mb_strpos($src['content_lower'], $phrase) !== false) {
                                $matchType = 'phrase_match';
                                $anchor = ucwords($phrase);
                                $relevanceBoost = 0.15;
                                break;
                            }
                        }
                    }
                }
                
                if (!$matchType) continue;
                
                // === DUPLICATE PREVENTION ===
                $anchorLower = mb_strtolower($anchor);
                
                // Skip if anchor already used from this source
                if (isset($srcAnchors[$anchorLower])) continue;
                
                // Skip if target already suggested from this source
                if (isset($srcTargets[$tgtId])) continue;
                
                // === AI SCORING ===
                $relevanceScore = $this->calcRelevanceScore($src, $tgt, $relevanceBoost);
                $authorityScore = $this->calcAuthorityScore($tgt, $inboundLinkCount[$tgtId] ?? 0);
                $anchorQuality = $this->calcAnchorQuality($anchor, $src['content_lower']);
                $duplicateRisk = $this->calcDuplicateRisk($anchor, $seenAnchors);
                
                // Skip low-quality suggestions
                $totalScore = ($relevanceScore * 0.35) + ($authorityScore * 0.25) + ($anchorQuality * 0.25) + ((1 - $duplicateRisk) * 0.15);
                if ($totalScore < 0.25) continue;
                
                $opportunities[] = [
                    'source_type' => 'blog',
                    'source_id' => $srcId,
                    'source_title' => $src['title'],
                    'target_type' => 'blog',
                    'target_id' => $tgtId,
                    'target_title' => $tgt['title'],
                    'target_url' => '/blog/' . $tgt['slug'],
                    'suggested_anchor' => $anchor,
                    'match_type' => $matchType,
                    'reason' => $this->getLinkReason($matchType),
                    'scores' => [
                        'total' => round($totalScore, 3),
                        'relevance' => round($relevanceScore, 3),
                        'authority' => round($authorityScore, 3),
                        'anchor_quality' => round($anchorQuality, 3),
                        'duplicate_risk' => round($duplicateRisk, 3),
                    ]
                ];
                
                $seenPairs[$pairKey] = true;
                $srcAnchors[$anchorLower] = true;
                $srcTargets[$tgtId] = true;
                $seenAnchors[$anchorLower] = ($seenAnchors[$anchorLower] ?? 0) + 1;
            }
            
            // === Blog → Product matching ===
            foreach ($products as $product) {
                if (isset($src['existing_slugs'][$product['slug']])) continue;
                if (count($srcTargets) >= $maxLinks) break;
                
                $prodNameLower = mb_strtolower($product['name']);
                if (mb_strpos($src['content_lower'], $prodNameLower) === false) continue;
                
                $anchorLower = $prodNameLower;
                if (isset($srcAnchors[$anchorLower])) continue;
                
                $duplicateRisk = $this->calcDuplicateRisk($product['name'], $seenAnchors);
                $score = 0.6 + (1 - $duplicateRisk) * 0.2;
                
                $opportunities[] = [
                    'source_type' => 'blog',
                    'source_id' => $srcId,
                    'source_title' => $src['title'],
                    'target_type' => 'product',
                    'target_id' => $product['id'],
                    'target_title' => $product['name'],
                    'target_url' => '/product/' . $product['slug'],
                    'suggested_anchor' => $product['name'],
                    'match_type' => 'product_mention',
                    'reason' => 'Product mentioned in content but not linked',
                    'scores' => [
                        'total' => round($score, 3),
                        'relevance' => 0.7,
                        'authority' => 0.5,
                        'anchor_quality' => 0.6,
                        'duplicate_risk' => round($duplicateRisk, 3),
                    ]
                ];
                
                $srcAnchors[$anchorLower] = true;
                $srcTargets['p_' . $product['id']] = true;
                $seenAnchors[$anchorLower] = ($seenAnchors[$anchorLower] ?? 0) + 1;
            }
        }
        
        // Sort by total score descending
        usort($opportunities, fn($a, $b) => $b['scores']['total'] <=> $a['scores']['total']);
        
        // === KEYWORD CANNIBALIZATION DETECTION ===
        $cannibalization = [];
        foreach ($keywordMap as $kw => $ids) {
            if (count($ids) > 1) {
                $titles = [];
                foreach ($ids as $bid) { if (isset($blogData[$bid])) $titles[] = ['id' => $bid, 'title' => $blogData[$bid]['title']]; }
                $cannibalization[] = ['keyword' => $kw, 'blogs' => $titles, 'count' => count($ids)];
            }
        }
        usort($cannibalization, fn($a, $b) => $b['count'] <=> $a['count']);
        
        // === ORPHAN PAGES ===
        $orphans = [];
        foreach ($blogData as $id => $d) {
            if (($inboundLinkCount[$id] ?? 0) === 0) {
                $orphans[] = ['id' => $id, 'title' => $d['title'], 'slug' => $d['slug'], 'url' => '/blog/' . $d['slug'], 'word_count' => $d['word_count']];
            }
        }
        
        // === WEAK PAGES (low inbound links) ===
        $weakPages = [];
        foreach ($blogData as $id => $d) {
            $inbound = $inboundLinkCount[$id] ?? 0;
            if ($inbound > 0 && $inbound <= 2) {
                $weakPages[] = ['id' => $id, 'title' => $d['title'], 'slug' => $d['slug'], 'inbound_links' => $inbound, 'word_count' => $d['word_count']];
            }
        }
        usort($weakPages, fn($a, $b) => $a['inbound_links'] <=> $b['inbound_links']);
        
        $result = [
            'opportunities' => array_slice($opportunities, 0, 100),
            'cannibalization' => array_slice($cannibalization, 0, 20),
            'orphans' => array_slice($orphans, 0, 30),
            'weak_pages' => array_slice($weakPages, 0, 20),
            'stats' => [
                'total_blogs' => count($blogs),
                'total_products' => count($products),
                'total_opportunities' => count($opportunities),
                'total_cannibalized' => count($cannibalization),
                'total_orphans' => count($orphans),
                'total_weak' => count($weakPages),
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        // Write cache
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
        
        return $result;
    }
    
    private function calcRelevanceScore(array $src, array $tgt, float $boost): float {
        $score = $boost;
        // Keyword overlap bonus
        $common = count(array_intersect($src['keywords'], $tgt['keywords']));
        $score += min(0.3, $common * 0.1);
        // Word count bonus for deep content
        if ($tgt['word_count'] > 1000) $score += 0.1;
        if ($tgt['word_count'] > 2000) $score += 0.1;
        return min(1.0, $score);
    }
    
    private function calcAuthorityScore(array $tgt, int $inbound): float {
        $score = 0.2;
        $score += min(0.4, $inbound * 0.08);
        if ($tgt['word_count'] > 1500) $score += 0.2;
        if ($tgt['word_count'] > 3000) $score += 0.1;
        return min(1.0, $score);
    }
    
    private function calcAnchorQuality(string $anchor, string $content): float {
        $score = 0.5;
        $len = mb_strlen($anchor);
        // Good anchor length: 2-6 words
        $words = str_word_count($anchor);
        if ($words >= 2 && $words <= 6) $score += 0.2;
        if ($words === 1 && $len < 4) $score -= 0.2; // too short
        // Anchor appears naturally in content
        if (mb_strpos($content, mb_strtolower($anchor)) !== false) $score += 0.2;
        // Not too generic
        $generic = ['click here', 'read more', 'learn more', 'this article', 'here'];
        if (in_array(mb_strtolower($anchor), $generic)) $score = 0.1;
        return max(0, min(1.0, $score));
    }
    
    private function calcDuplicateRisk(string $anchor, array $seenAnchors): float {
        $key = mb_strtolower($anchor);
        $count = $seenAnchors[$key] ?? 0;
        if ($count === 0) return 0;
        if ($count === 1) return 0.3;
        if ($count === 2) return 0.6;
        return min(1.0, $count * 0.25);
    }
    
    private function getLinkReason(string $matchType): string {
        $reasons = [
            'title_mention' => 'Blog title mentioned in content but not linked',
            'keyword_overlap' => 'Shared focus keywords — semantic topic match',
            'phrase_match' => 'Related phrase found in content — contextual link opportunity',
            'product_mention' => 'Product mentioned in content but not linked',
        ];
        return $reasons[$matchType] ?? 'Link opportunity detected';
    }
    
    /**
     * Autonomous Orphan Intelligence Engine
     * Deep orphan analysis with connectivity scoring, risk assessment, auto-fix suggestions
     */
    public function findOrphanPages(): array {
        $orphans = [];
        $blogs = $this->db->query("SELECT id, title, slug, content, meta_keywords, excerpt FROM blogs WHERE status='published'")->fetchAll(PDO::FETCH_ASSOC);
        
        // Build link graph: who links to whom
        $inboundMap = [];   // blog_id => [list of blog_ids linking to it]
        $outboundMap = [];  // blog_id => [list of blog_ids it links to]
        $blogById = [];
        
        foreach ($blogs as $blog) {
            $blogById[$blog['id']] = $blog;
            $inboundMap[$blog['id']] = [];
            $outboundMap[$blog['id']] = [];
        }
        
        foreach ($blogs as $blog) {
            preg_match_all('/href=["\']([^"\']+)/i', $blog['content'] ?? '', $m);
            foreach ($m[1] ?? [] as $link) {
                foreach ($blogs as $other) {
                    if ($other['id'] !== $blog['id'] && mb_strpos($link, $other['slug']) !== false) {
                        $outboundMap[$blog['id']][] = $other['id'];
                        $inboundMap[$other['id']][] = $blog['id'];
                    }
                }
            }
        }
        
        foreach ($blogs as $blog) {
            $id = $blog['id'];
            $inbound = array_unique($inboundMap[$id] ?? []);
            $outbound = array_unique($outboundMap[$id] ?? []);
            $inCount = count($inbound);
            $outCount = count($outbound);
            $wordCount = str_word_count(strip_tags($blog['content'] ?? ''));
            
            // Connectivity score: 0-100
            $connScore = min(100, ($inCount * 20) + ($outCount * 10) + ($wordCount > 1000 ? 10 : 0));
            
            // Orphan risk: high / medium / low
            $risk = 'low';
            if ($inCount === 0 && $outCount === 0) $risk = 'critical';
            elseif ($inCount === 0) $risk = 'high';
            elseif ($inCount <= 1 && $outCount <= 1) $risk = 'medium';
            
            if ($inCount === 0) {
                $orphans[] = [
                    'id' => $id,
                    'title' => $blog['title'],
                    'slug' => $blog['slug'],
                    'url' => '/blog/' . $blog['slug'],
                    'word_count' => $wordCount,
                    'inbound_links' => $inCount,
                    'outbound_links' => $outCount,
                    'connectivity_score' => $connScore,
                    'orphan_risk' => $risk,
                    'keywords' => $blog['meta_keywords'] ?? '',
                ];
            }
        }
        
        // Also find weak pages (1-2 inbound, 0 outbound)
        $weakPages = [];
        foreach ($blogs as $blog) {
            $id = $blog['id'];
            $inbound = array_unique($inboundMap[$id] ?? []);
            $outbound = array_unique($outboundMap[$id] ?? []);
            $inCount = count($inbound);
            $outCount = count($outbound);
            if ($inCount >= 1 && $inCount <= 2 && $outCount === 0) {
                $wordCount = str_word_count(strip_tags($blog['content'] ?? ''));
                $connScore = min(100, ($inCount * 20) + ($outCount * 10) + ($wordCount > 1000 ? 10 : 0));
                $weakPages[] = [
                    'id' => $id,
                    'title' => $blog['title'],
                    'slug' => $blog['slug'],
                    'url' => '/blog/' . $blog['slug'],
                    'word_count' => $wordCount,
                    'inbound_links' => $inCount,
                    'outbound_links' => $outCount,
                    'connectivity_score' => $connScore,
                    'orphan_risk' => 'medium',
                    'keywords' => $blog['meta_keywords'] ?? '',
                ];
            }
        }
        
        usort($orphans, function($a, $b) {
            $riskOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            return ($riskOrder[$a['orphan_risk']] ?? 3) <=> ($riskOrder[$b['orphan_risk']] ?? 3);
        });
        
        return ['orphans' => $orphans, 'weak_pages' => $weakPages];
    }
    
    /**
     * Pre-publish connectivity check — prevents orphan creation
     * Returns pass/fail + required actions before publishing
     */
    public function prePublishCheck(int $blogId, int $jsProductCount = -1, int $jsBlogCount = -1): array {
        $stmt = $this->db->prepare("SELECT id, title, slug, content, meta_keywords, excerpt, status FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$blog) return ['pass' => false, 'error' => 'Blog not found'];
        
        $content = $blog['content'] ?? '';
        $contentLower = mb_strtolower(strip_tags($content));
        $wordCount = str_word_count($contentLower);
        $keywords = array_filter(array_map('trim', explode(',', mb_strtolower($blog['meta_keywords'] ?? ''))));
        
        // Count outbound internal links
        $blogs = $this->db->query("SELECT id, title, slug FROM blogs WHERE status='published' AND id != " . (int)$blogId)->fetchAll(PDO::FETCH_ASSOC);
        $products = [];
        try { $products = $this->db->query("SELECT id, name, slug FROM products")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e) {}
        
        preg_match_all('/href=["\']([^"\']+)/i', $content, $linkMatches);
        $existingLinks = $linkMatches[1] ?? [];
        
        $outboundBlogLinks = 0;
        $outboundProductLinks = 0;
        foreach ($existingLinks as $link) {
            foreach ($blogs as $b) { if (mb_strpos($link, $b['slug']) !== false) { $outboundBlogLinks++; break; } }
            foreach ($products as $p) { if (mb_strpos($link, $p['slug']) !== false) { $outboundProductLinks++; break; } }
        }
        
        // Count blogs linked via blog_internal_links table (Link Related Blogs panel)
        try {
            $bilStmt = $this->db->prepare("SELECT COUNT(*) FROM blog_internal_links WHERE blog_id = ?");
            $bilStmt->execute([$blogId]);
            $outboundBlogLinks += (int)$bilStmt->fetchColumn();
        } catch (\Exception $e) {}
        
        // Count products linked via blog_products table (Link Products panel)
        try {
            $lpStmt = $this->db->prepare("SELECT COUNT(*) FROM blog_products WHERE blog_id = ?");
            $lpStmt->execute([$blogId]);
            $outboundProductLinks += (int)$lpStmt->fetchColumn();
        } catch (\Exception $e) {}
        
        // Count potential inbound links (other blogs that mention this blog's title/keywords)
        $potentialInbound = 0;
        $titleLower = mb_strtolower($blog['title']);
        foreach ($blogs as $b) {
            $otherContent = mb_strtolower(strip_tags($b['content'] ?? ''));
            if (mb_strpos($otherContent, $titleLower) !== false || mb_strpos($otherContent, $blog['slug']) !== false) {
                $potentialInbound++;
            }
        }
        
        // Check keyword cannibalization
        $cannibalizedWith = [];
        foreach ($blogs as $b) {
            $otherKws = array_filter(array_map('trim', explode(',', mb_strtolower($b['meta_keywords'] ?? ''))));
            $overlap = array_intersect($keywords, $otherKws);
            if (!empty($overlap)) {
                $cannibalizedWith[] = ['id' => $b['id'], 'title' => $b['title'], 'keywords' => array_values($overlap)];
            }
        }
        
        // Find topic cluster (semantically related content)
        $clusterBlogs = [];
        foreach ($blogs as $b) {
            $bTitle = mb_strtolower($b['title']);
            $score = 0;
            // Title word overlap
            $myWords = array_filter(explode(' ', $titleLower), fn($w) => mb_strlen($w) > 3);
            foreach ($myWords as $w) { if (mb_strpos($bTitle, $w) !== false) $score += 0.2; }
            // Keyword overlap
            $bKws = array_filter(array_map('trim', explode(',', mb_strtolower($b['meta_keywords'] ?? ''))));
            $score += count(array_intersect($keywords, $bKws)) * 0.15;
            if ($score >= 0.2) $clusterBlogs[] = ['id' => $b['id'], 'title' => $b['title'], 'score' => round($score, 2)];
        }
        usort($clusterBlogs, fn($a, $b) => $b['score'] <=> $a['score']);
        $clusterBlogs = array_slice($clusterBlogs, 0, 10);
        
        // If JS-side counts were passed (user has linked items not yet saved), use maximum
        if ($jsProductCount >= 0) $outboundProductLinks = max($outboundProductLinks, $jsProductCount);
        if ($jsBlogCount >= 0)    $outboundBlogLinks    = max($outboundBlogLinks, $jsBlogCount);

        // Rules — reasonable threshold: 1 for <1000 words, 2 for 1000-1999, 3 for 2000+
        $minOutbound = max(1, min(3, intval($wordCount / 700)));
        $checks = [];
        $checks[] = ['rule' => 'Minimum outbound blog links', 'required' => $minOutbound, 'current' => $outboundBlogLinks, 'pass' => $outboundBlogLinks >= $minOutbound];
        $checks[] = ['rule' => 'At least 1 product link', 'required' => 1, 'current' => $outboundProductLinks, 'pass' => $outboundProductLinks >= 1 || count($products) === 0];
        $checks[] = ['rule' => 'Belongs to semantic cluster', 'required' => 1, 'current' => count($clusterBlogs), 'pass' => count($clusterBlogs) >= 1];
        $checks[] = ['rule' => 'No critical keyword cannibalization', 'required' => 0, 'current' => count($cannibalizedWith), 'pass' => count($cannibalizedWith) <= 2];
        $checks[] = ['rule' => 'Content depth (500+ words)', 'required' => 500, 'current' => $wordCount, 'pass' => $wordCount >= 500];
        
        $passCount = count(array_filter($checks, fn($c) => $c['pass']));
        $totalChecks = count($checks);
        $allPass = $passCount === $totalChecks;
        
        // Orphan risk prediction
        $orphanRisk = 'low';
        if ($outboundBlogLinks === 0 && $potentialInbound === 0) $orphanRisk = 'critical';
        elseif ($outboundBlogLinks === 0 || $potentialInbound === 0) $orphanRisk = 'high';
        elseif ($outboundBlogLinks < $minOutbound) $orphanRisk = 'medium';
        
        return [
            'pass' => $allPass,
            'checks' => $checks,
            'pass_count' => $passCount,
            'total_checks' => $totalChecks,
            'orphan_risk' => $orphanRisk,
            'outbound_blog_links' => $outboundBlogLinks,
            'outbound_product_links' => $outboundProductLinks,
            'potential_inbound' => $potentialInbound,
            'cluster_blogs' => $clusterBlogs,
            'cannibalized_with' => array_slice($cannibalizedWith, 0, 5),
            'word_count' => $wordCount,
        ];
    }
    
    /**
     * Auto-fix orphan/weak page: intelligent link suggestion engine
     * Uses: keyword word matching, content overlap, authority fallback, category linking, product matching
     * ALWAYS returns suggestions — falls back to authority/pillar pages if no semantic match
     */
    public function autoFixOrphan(int $blogId): array {
        $stmt = $this->db->prepare("SELECT id, title, slug, content, meta_keywords, excerpt, category_id FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$blog) return ['success' => false, 'error' => 'Blog not found'];
        
        $allBlogs = $this->db->query("SELECT id, title, slug, content, meta_keywords, category_id FROM blogs WHERE status='published' AND id != " . (int)$blogId)->fetchAll(PDO::FETCH_ASSOC);
        $products = [];
        try { $products = $this->db->query("SELECT id, name, slug FROM products")->fetchAll(PDO::FETCH_ASSOC); } catch (\Exception $e) {}
        
        $contentLower = mb_strtolower(strip_tags($blog['content'] ?? ''));
        $titleLower = mb_strtolower($blog['title']);
        $keywords = array_filter(array_map('trim', explode(',', mb_strtolower($blog['meta_keywords'] ?? ''))));
        $blogCategoryId = $blog['category_id'] ?? null;
        
        // Extract significant words from keywords (individual words, 4+ chars)
        $keywordWords = [];
        foreach ($keywords as $kw) {
            foreach (explode(' ', $kw) as $w) {
                $w = trim($w);
                if (mb_strlen($w) >= 4) $keywordWords[$w] = true;
            }
        }
        
        // Extract significant content words (frequency-based)
        $stopWords = ['this','that','with','from','have','been','will','would','could','should','their','there','about','which','when','what','your','more','also','than','into','them','very','just','only','come','made','after','before','other','some','like','then','over','such','most','even','each','these','those','being','does','much','were','many','well','back','down','still','find','here','thing','every','where','while','under','take','know','good','help','best','make','need','want','because','between','through'];
        $contentWords = array_filter(str_word_count($contentLower, 1), fn($w) => mb_strlen($w) >= 4 && !in_array($w, $stopWords));
        $wordFreq = array_count_values($contentWords);
        arsort($wordFreq);
        $topContentWords = array_slice(array_keys($wordFreq), 0, 30);
        
        // Build inbound link count per blog (for authority scoring)
        $inboundCount = [];
        foreach ($allBlogs as $b) { $inboundCount[$b['id']] = 0; }
        foreach ($allBlogs as $b) {
            preg_match_all('/href=["\']([^"\']+)/i', $b['content'] ?? '', $m);
            foreach ($m[1] ?? [] as $link) {
                foreach ($allBlogs as $other) {
                    if ($other['id'] !== $b['id'] && mb_strpos($link, $other['slug']) !== false) {
                        $inboundCount[$other['id']] = ($inboundCount[$other['id']] ?? 0) + 1;
                    }
                }
            }
        }
        
        // === OUTGOING LINKS: Pages the orphan should link to ===
        $outgoing = [];
        foreach ($allBlogs as $target) {
            $score = 0;
            $reason = '';
            $anchor = '';
            $tgtTitleLower = mb_strtolower($target['title']);
            $tgtContentLower = mb_strtolower(strip_tags($target['content'] ?? ''));
            $tgtKws = array_filter(array_map('trim', explode(',', mb_strtolower($target['meta_keywords'] ?? ''))));
            
            // 1. Title mentioned in orphan's content
            if (mb_strpos($contentLower, $tgtTitleLower) !== false) {
                $score += 0.5; $reason = 'Title mentioned in content'; $anchor = $target['title'];
            }
            
            // 2. Full keyword overlap
            $overlap = array_intersect($keywords, $tgtKws);
            if (!empty($overlap)) {
                $score += 0.3;
                if (!$reason) { $reason = 'Shared keywords: ' . implode(', ', array_slice($overlap, 0, 3)); $anchor = ucfirst(reset($overlap)); }
            }
            
            // 3. Individual keyword word matching (break keywords into words)
            $tgtKwWords = [];
            foreach ($tgtKws as $kw) {
                foreach (explode(' ', $kw) as $w) { if (mb_strlen(trim($w)) >= 4) $tgtKwWords[trim($w)] = true; }
            }
            $wordOverlap = array_intersect_key($keywordWords, $tgtKwWords);
            if (!empty($wordOverlap) && $score < 0.3) {
                $score += count($wordOverlap) * 0.08;
                if (!$reason) { $reason = 'Related topic words: ' . implode(', ', array_slice(array_keys($wordOverlap), 0, 3)); }
            }
            
            // 4. Content body word overlap (significant shared words)
            $tgtWords = array_filter(str_word_count($tgtContentLower, 1), fn($w) => mb_strlen($w) >= 4 && !in_array($w, $stopWords));
            $tgtWordSet = array_flip(array_unique($tgtWords));
            $sharedContentWords = array_intersect_key(array_flip($topContentWords), $tgtWordSet);
            $sharedCount = count($sharedContentWords);
            if ($sharedCount >= 3 && $score < 0.3) {
                $score += min(0.25, $sharedCount * 0.03);
                if (!$reason) { $reason = 'Content overlap (' . $sharedCount . ' shared topics)'; }
            }
            
            // 5. Category match
            if ($blogCategoryId && $target['category_id'] == $blogCategoryId) {
                $score += 0.15;
                if (!$reason) $reason = 'Same category';
            }
            
            // 6. Title word in content
            $tgtTitleWords = array_filter(explode(' ', $tgtTitleLower), fn($w) => mb_strlen($w) > 3);
            $titleWordHits = 0;
            foreach ($tgtTitleWords as $w) {
                if (mb_strpos($contentLower, $w) !== false) { $titleWordHits++; }
            }
            if ($titleWordHits > 0 && $score < 0.15) {
                $score += $titleWordHits * 0.04;
                if (!$reason) $reason = 'Title words found in content';
            }
            
            // 7. Authority bonus (well-linked pages are better targets)
            $authBonus = min(0.1, ($inboundCount[$target['id']] ?? 0) * 0.03);
            $score += $authBonus;
            
            if ($score >= 0.1) {
                if (!$anchor) {
                    // Generate anchor: try keyword words, then title
                    foreach (array_keys($wordOverlap ?? []) as $w) {
                        if (mb_strpos($contentLower, $w) !== false) { $anchor = ucfirst($w); break; }
                    }
                    if (!$anchor) {
                        foreach ($tgtTitleWords as $w) {
                            if (mb_strpos($contentLower, $w) !== false) { $anchor = ucfirst($w); break; }
                        }
                    }
                    if (!$anchor) $anchor = $target['title'];
                }
                $outgoing[] = [
                    'id' => $target['id'], 'title' => $target['title'], 'slug' => $target['slug'],
                    'url' => '/blog/' . $target['slug'], 'anchor' => $anchor,
                    'score' => round($score, 3), 'reason' => $reason, 'type' => 'blog'
                ];
            }
        }
        
        // Product links (match individual product name words too)
        foreach ($products as $p) {
            $pNameLower = mb_strtolower($p['name']);
            $pScore = 0;
            $pReason = '';
            if (mb_strpos($contentLower, $pNameLower) !== false) {
                $pScore = 0.6; $pReason = 'Product name found in content';
            } else {
                // Try individual product name words
                $pWords = array_filter(explode(' ', $pNameLower), fn($w) => mb_strlen($w) >= 4 && !in_array($w, $stopWords));
                $pHits = 0;
                foreach ($pWords as $pw) { if (mb_strpos($contentLower, $pw) !== false) $pHits++; }
                if ($pHits >= 1 && count($pWords) > 0) {
                    $pScore = 0.2 + ($pHits / count($pWords)) * 0.3;
                    $pReason = 'Related product (' . $pHits . '/' . count($pWords) . ' name words match)';
                }
            }
            if ($pScore >= 0.2) {
                $outgoing[] = [
                    'id' => $p['id'], 'title' => $p['name'], 'slug' => $p['slug'],
                    'url' => '/product/' . $p['slug'], 'anchor' => $p['name'],
                    'score' => round($pScore, 3), 'reason' => $pReason, 'type' => 'product'
                ];
            }
        }
        
        // FALLBACK: If still no outgoing, suggest top authority pages (pillar linking)
        if (empty($outgoing)) {
            arsort($inboundCount);
            $pillarIds = array_slice(array_keys($inboundCount), 0, 5);
            foreach ($pillarIds as $pid) {
                foreach ($allBlogs as $b) {
                    if ($b['id'] == $pid) {
                        $outgoing[] = [
                            'id' => $b['id'], 'title' => $b['title'], 'slug' => $b['slug'],
                            'url' => '/blog/' . $b['slug'], 'anchor' => $b['title'],
                            'score' => 0.15, 'reason' => 'Authority page (pillar linking for connectivity)', 'type' => 'blog'
                        ];
                        break;
                    }
                }
            }
            // Also suggest all products as potential links
            foreach (array_slice($products, 0, 3) as $p) {
                $outgoing[] = [
                    'id' => $p['id'], 'title' => $p['name'], 'slug' => $p['slug'],
                    'url' => '/product/' . $p['slug'], 'anchor' => $p['name'],
                    'score' => 0.12, 'reason' => 'Product link (site connectivity)', 'type' => 'product'
                ];
            }
        }
        
        usort($outgoing, fn($a, $b) => $b['score'] <=> $a['score']);
        $outgoing = array_slice($outgoing, 0, 10);
        
        // === INCOMING LINKS: Other pages that should link to the orphan ===
        $incoming = [];
        // Extract orphan's significant title words for matching
        $orphanTitleWords = array_filter(explode(' ', $titleLower), fn($w) => mb_strlen($w) > 3 && !in_array($w, $stopWords));
        
        foreach ($allBlogs as $source) {
            $srcContentLower = mb_strtolower(strip_tags($source['content'] ?? ''));
            $score = 0;
            $anchor = '';
            $reason = '';
            $insertContext = '';
            
            // 1. Source mentions orphan's title
            if (mb_strpos($srcContentLower, $titleLower) !== false) {
                $score += 0.5; $reason = 'Already mentions this blog title'; $anchor = $blog['title'];
                $pos = mb_strpos($srcContentLower, $titleLower);
                $insertContext = mb_substr(strip_tags($source['content'] ?? ''), max(0, $pos - 40), mb_strlen($titleLower) + 80);
            }
            
            // 2. Source mentions orphan's full keywords
            foreach ($keywords as $kw) {
                if (mb_strlen($kw) > 3 && mb_strpos($srcContentLower, $kw) !== false) {
                    $score += 0.2;
                    if (!$anchor) { $anchor = ucfirst($kw); $reason = 'Mentions keyword "' . $kw . '"'; }
                }
            }
            
            // 3. Source mentions orphan's individual keyword words
            if ($score < 0.2) {
                $kwWordHits = 0;
                $hitWord = '';
                foreach (array_keys($keywordWords) as $w) {
                    if (mb_strpos($srcContentLower, $w) !== false) { $kwWordHits++; if (!$hitWord) $hitWord = $w; }
                }
                if ($kwWordHits >= 2) {
                    $score += $kwWordHits * 0.08;
                    if (!$reason) { $reason = 'Mentions related topic words'; $anchor = ucfirst($hitWord); }
                }
            }
            
            // 4. Source mentions orphan's title words
            if ($score < 0.15) {
                $titleHits = 0;
                $hitTitleWord = '';
                foreach ($orphanTitleWords as $tw) {
                    if (mb_strpos($srcContentLower, $tw) !== false) { $titleHits++; if (!$hitTitleWord) $hitTitleWord = $tw; }
                }
                if ($titleHits >= 2) {
                    $score += $titleHits * 0.06;
                    if (!$reason) { $reason = 'Mentions title topics'; $anchor = ucfirst($hitTitleWord); }
                }
            }
            
            // 5. Same category
            if ($blogCategoryId && $source['category_id'] == $blogCategoryId) {
                $score += 0.1;
                if (!$reason) $reason = 'Same category — topical sibling';
            }
            
            // 6. Content word overlap
            if ($score < 0.1) {
                $srcWords = array_filter(str_word_count($srcContentLower, 1), fn($w) => mb_strlen($w) >= 4 && !in_array($w, $stopWords));
                $srcWordSet = array_flip(array_unique($srcWords));
                $sharedSrc = count(array_intersect_key(array_flip($topContentWords), $srcWordSet));
                if ($sharedSrc >= 5) {
                    $score += min(0.2, $sharedSrc * 0.02);
                    if (!$reason) $reason = 'Content topic overlap (' . $sharedSrc . ' shared words)';
                }
            }
            
            if ($score >= 0.1) {
                if (!$anchor) $anchor = $blog['title'];
                $alreadyLinked = mb_strpos($source['content'] ?? '', $blog['slug']) !== false;
                if (!$alreadyLinked) {
                    $incoming[] = [
                        'source_id' => $source['id'], 'source_title' => $source['title'], 'source_slug' => $source['slug'],
                        'anchor' => $anchor, 'score' => round($score, 3), 'reason' => $reason,
                        'insert_context' => $insertContext,
                    ];
                }
            }
        }
        
        // FALLBACK: If no incoming suggestions, suggest all other blogs add a link (for connectivity)
        if (empty($incoming)) {
            foreach (array_slice($allBlogs, 0, 5) as $source) {
                $alreadyLinked = mb_strpos($source['content'] ?? '', $blog['slug']) !== false;
                if (!$alreadyLinked) {
                    $incoming[] = [
                        'source_id' => $source['id'], 'source_title' => $source['title'], 'source_slug' => $source['slug'],
                        'anchor' => $blog['title'], 'score' => 0.1, 'reason' => 'Connectivity link (prevent orphan isolation)',
                        'insert_context' => '',
                    ];
                }
            }
        }
        
        usort($incoming, fn($a, $b) => $b['score'] <=> $a['score']);
        $incoming = array_slice($incoming, 0, 10);
        
        return [
            'success' => true,
            'blog' => ['id' => $blog['id'], 'title' => $blog['title'], 'slug' => $blog['slug']],
            'outgoing_suggestions' => $outgoing,
            'incoming_suggestions' => $incoming,
            'total_outgoing' => count($outgoing),
            'total_incoming' => count($incoming),
        ];
    }
    
    /**
     * Execute auto-fix: actually insert a link into blog content
     */
    public function insertLink(int $blogId, string $anchor, string $url): array {
        $stmt = $this->db->prepare("SELECT id, content FROM blogs WHERE id = ?");
        $stmt->execute([$blogId]);
        $blog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$blog) return ['success' => false, 'error' => 'Blog not found'];
        
        $content = $blog['content'] ?? '';
        $anchorLower = mb_strtolower($anchor);
        $contentLower = mb_strtolower($content);
        
        // Check if already linked to this URL
        if (mb_strpos($content, $url) !== false) {
            return ['success' => false, 'error' => 'This URL is already linked in the content'];
        }
        
        // Find the anchor text in content (case-insensitive) and wrap with link
        $pos = mb_stripos($content, $anchor);
        if ($pos !== false) {
            $originalText = mb_substr($content, $pos, mb_strlen($anchor));
            $link = '<a href="' . htmlspecialchars($url) . '">' . $originalText . '</a>';
            $newContent = mb_substr($content, 0, $pos) . $link . mb_substr($content, $pos + mb_strlen($anchor));
            
            $upd = $this->db->prepare("UPDATE blogs SET content = ? WHERE id = ?");
            $upd->execute([$newContent, $blogId]);
            
            // Clear link opportunities cache
            $cacheFile = __DIR__ . '/../cache/seo/link_opps_cache.json';
            if (file_exists($cacheFile)) @unlink($cacheFile);
            
            return ['success' => true, 'message' => 'Link inserted: "' . $originalText . '" → ' . $url];
        }
        
        // Anchor not found verbatim — try appending link at end of first relevant paragraph
        $paragraphs = preg_split('/(<\/p>|<\/div>|\n\n)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($paragraphs) >= 3) {
            // Insert after 2nd paragraph
            $insertIdx = min(3, count($paragraphs) - 1);
            $linkHtml = ' <a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($anchor) . '</a>';
            $paragraphs[$insertIdx] = $linkHtml . $paragraphs[$insertIdx];
            $newContent = implode('', $paragraphs);
            
            $upd = $this->db->prepare("UPDATE blogs SET content = ? WHERE id = ?");
            $upd->execute([$newContent, $blogId]);
            
            $cacheFile = __DIR__ . '/../cache/seo/link_opps_cache.json';
            if (file_exists($cacheFile)) @unlink($cacheFile);
            
            return ['success' => true, 'message' => 'Link appended after paragraph: "' . $anchor . '" → ' . $url];
        }
        
        return ['success' => false, 'error' => 'Could not find suitable position to insert link'];
    }
    
    /**
     * Build content connectivity graph for visualization
     */
    public function buildConnectivityGraph(): array {
        $blogs = $this->db->query("SELECT id, title, slug, content, meta_keywords FROM blogs WHERE status='published'")->fetchAll(PDO::FETCH_ASSOC);
        
        $nodes = [];
        $edges = [];
        $inboundCount = [];
        $outboundCount = [];
        
        foreach ($blogs as $blog) {
            $inboundCount[$blog['id']] = 0;
            $outboundCount[$blog['id']] = 0;
        }
        
        foreach ($blogs as $blog) {
            preg_match_all('/href=["\']([^"\']+)/i', $blog['content'] ?? '', $m);
            foreach ($m[1] ?? [] as $link) {
                foreach ($blogs as $other) {
                    if ($other['id'] !== $blog['id'] && mb_strpos($link, $other['slug']) !== false) {
                        $edges[] = ['source' => $blog['id'], 'target' => $other['id'], 'source_title' => $blog['title'], 'target_title' => $other['title']];
                        $outboundCount[$blog['id']]++;
                        $inboundCount[$other['id']]++;
                    }
                }
            }
        }
        
        foreach ($blogs as $blog) {
            $id = $blog['id'];
            $in = $inboundCount[$id] ?? 0;
            $out = $outboundCount[$id] ?? 0;
            $status = 'healthy';
            if ($in === 0 && $out === 0) $status = 'orphan_critical';
            elseif ($in === 0) $status = 'orphan';
            elseif ($in <= 1) $status = 'weak';
            elseif ($in >= 5) $status = 'pillar';
            
            $nodes[] = [
                'id' => $id, 'title' => $blog['title'], 'slug' => $blog['slug'],
                'inbound' => $in, 'outbound' => $out, 'status' => $status,
                'keywords' => $blog['meta_keywords'] ?? '',
            ];
        }
        
        return ['nodes' => $nodes, 'edges' => $edges, 'total_nodes' => count($nodes), 'total_edges' => count($edges)];
    }
    
    /**
     * Get site-wide SEO statistics
     */
    public function getSiteStats(): array {
        $stats = [];
        
        // Blog stats
        $stats['total_blogs'] = (int)$this->db->query("SELECT COUNT(*) FROM blogs")->fetchColumn();
        $stats['published_blogs'] = (int)$this->db->query("SELECT COUNT(*) FROM blogs WHERE status='published'")->fetchColumn();
        $stats['draft_blogs'] = (int)$this->db->query("SELECT COUNT(*) FROM blogs WHERE status='draft'")->fetchColumn();
        
        // Product stats
        try {
            $stats['total_products'] = (int)$this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $stats['products_with_seo'] = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE seo_title IS NOT NULL AND seo_title != ''")->fetchColumn();
        } catch (PDOException $e) {
            $stats['total_products'] = 0;
            $stats['products_with_seo'] = 0;
        }
        
        // Content quality
        try {
            $stats['blogs_with_meta_title'] = (int)$this->db->query("SELECT COUNT(*) FROM blogs WHERE meta_title IS NOT NULL AND meta_title != ''")->fetchColumn();
            $stats['blogs_with_meta_desc'] = (int)$this->db->query("SELECT COUNT(*) FROM blogs WHERE meta_description IS NOT NULL AND meta_description != ''")->fetchColumn();
            $stats['blogs_with_keywords'] = (int)$this->db->query("SELECT COUNT(*) FROM blogs WHERE meta_keywords IS NOT NULL AND meta_keywords != ''")->fetchColumn();
            $stats['blogs_with_images'] = (int)$this->db->query("SELECT COUNT(*) FROM blogs WHERE featured_image IS NOT NULL AND featured_image != ''")->fetchColumn();
            $stats['total_faqs'] = (int)$this->db->query("SELECT COUNT(*) FROM blog_faqs")->fetchColumn();
            $stats['blogs_with_faqs'] = (int)$this->db->query("SELECT COUNT(DISTINCT blog_id) FROM blog_faqs")->fetchColumn();
        } catch (PDOException $e) {}
        
        // Category stats
        try {
            $stats['total_categories'] = (int)$this->db->query("SELECT COUNT(*) FROM blog_categories")->fetchColumn();
        } catch (PDOException $e) {
            $stats['total_categories'] = 0;
        }
        
        return $stats;
    }
}

// ============================================================
// AI SEO HELPER — Multi-provider AI (Gemini/OpenAI/Claude)
// ============================================================
class GilafAiSeoHelper {
    
    private $apiKey;
    private $db;
    
    public function __construct(string $apiKey, $db = null) {
        $this->apiKey = $apiKey;
        $this->db = $db;
    }
    
    private function cleanJsonResponse(?string $text): ?array {
        if (!$text) return null;
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) $text = trim($m[1]);
        $text = preg_replace('/^[^{\[]+/', '', $text);
        $text = preg_replace('/[^}\]]+$/', '', $text);
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
    
    /**
     * Call AI API — supports Gemini, OpenAI, Claude
     */
    private function callAI(string $prompt, float $temperature = 0.3): ?string {
        if (empty($this->apiKey)) return null;
        
        $provider = 'gemini';
        if (strpos($this->apiKey, 'sk-ant-') === 0) $provider = 'claude';
        elseif (strpos($this->apiKey, 'sk-') === 0) $provider = 'openai';
        
        $model = '';
        if ($this->db) {
            try {
                $rows = $this->db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','ai_model')")->fetchAll(\PDO::FETCH_KEY_PAIR);
                if (!empty($rows['ai_provider'])) $provider = $rows['ai_provider'];
                $model = $rows['ai_model'] ?? '';
            } catch (\Exception $e) { $model = ''; }
        }
        
        if ($provider === 'openai') {
            if (!$model) $model = 'gpt-4o-mini';
            $url = 'https://api.openai.com/v1/chat/completions';
            $payload = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => $temperature, 'max_tokens' => 4096, 'response_format' => ['type' => 'json_object']];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        } elseif ($provider === 'claude') {
            if (!$model) $model = 'claude-3-haiku-20240307';
            $url = 'https://api.anthropic.com/v1/messages';
            $payload = ['model' => $model, 'max_tokens' => 4096, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->apiKey, 'anthropic-version: 2023-06-01'];
        } else {
            if (!$model) $model = 'gemini-2.0-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;
            $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']];
            $headers = ['Content-Type: application/json'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($response)) return null;
        $data = json_decode($response, true);
        
        if ($provider === 'openai') return $data['choices'][0]['message']['content'] ?? null;
        if ($provider === 'claude') return $data['content'][0]['text'] ?? null;
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
    
    /**
     * AI Semantic Analysis
     */
    public function analyzeSemanticGap(string $keyword, string $content): ?array {
        $prompt = "Analyze this content for semantic SEO completeness around the keyword \"{$keyword}\".

Content (first 2000 chars): " . mb_substr(strip_tags($content), 0, 2000) . "

Return JSON:
{
  \"semantic_score\": 0-100,
  \"missing_entities\": [\"entity1\", \"entity2\"],
  \"missing_topics\": [\"topic1\", \"topic2\"],
  \"lsi_keywords\": [\"keyword1\", \"keyword2\"],
  \"topic_clusters\": [\"cluster1\", \"cluster2\"],
  \"content_gaps\": [\"gap1\", \"gap2\"],
  \"recommendations\": [\"rec1\", \"rec2\"]
}";
        
        $result = $this->callAI($prompt);
        return $this->cleanJsonResponse($result);
    }
    
    /**
     * AI Content Brief Generation
     */
    public function generateContentBrief(string $keyword, string $category = ''): ?array {
        $prompt = "Create a comprehensive SEO content brief for the keyword: \"{$keyword}\"" . ($category ? " in the category: {$category}" : "") . "

Return JSON:
{
  \"title_suggestions\": [\"title1\", \"title2\", \"title3\"],
  \"meta_description\": \"suggested meta description\",
  \"target_word_count\": 1500,
  \"headings\": [\"H2: heading1\", \"H3: heading2\"],
  \"key_points\": [\"point1\", \"point2\"],
  \"questions_to_answer\": [\"q1\", \"q2\"],
  \"keywords_to_include\": [\"kw1\", \"kw2\"],
  \"internal_link_anchors\": [\"anchor1\", \"anchor2\"],
  \"schema_recommendations\": [\"Article\", \"FAQ\"],
  \"competitor_angles\": [\"angle1\", \"angle2\"],
  \"search_intent\": \"informational|transactional|navigational\"
}";
        
        $result = $this->callAI($prompt, 0.5);
        return $this->cleanJsonResponse($result);
    }
    
    /**
     * AI EEAT Enhancement Suggestions
     */
    public function suggestEEATImprovements(string $content, string $category = ''): ?array {
        $prompt = "Analyze this content for Google E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) and suggest improvements.

Category: " . ($category ?: 'General') . "
Content (first 1500 chars): " . mb_substr(strip_tags($content), 0, 1500) . "

Return JSON:
{
  \"eeat_score\": 0-100,
  \"experience_suggestions\": [\"suggestion1\"],
  \"expertise_suggestions\": [\"suggestion1\"],
  \"authority_suggestions\": [\"suggestion1\"],
  \"trust_suggestions\": [\"suggestion1\"],
  \"citations_needed\": [\"topic needing citation\"],
  \"credentials_to_add\": [\"credential\"]
}";
        
        $result = $this->callAI($prompt);
        return $this->cleanJsonResponse($result);
    }
    
    /**
     * AI Keyword Research
     */
    public function researchKeywords(string $seedKeyword, string $category = ''): ?array {
        $prompt = "Perform comprehensive keyword research for: \"{$seedKeyword}\"" . ($category ? " in category: {$category}" : "") . " for an Indian e-commerce store selling premium Kashmir products.

Return JSON:
{
  \"primary_keywords\": [{\"keyword\": \"kw\", \"intent\": \"informational\", \"difficulty\": \"low\", \"volume_estimate\": \"high\"}],
  \"long_tail_keywords\": [\"long tail 1\", \"long tail 2\"],
  \"question_keywords\": [\"how to...\", \"what is...\"],
  \"buyer_keywords\": [\"buy...\", \"best...\", \"price...\"],
  \"semantic_keywords\": [\"related1\", \"related2\"],
  \"trending_topics\": [\"trend1\", \"trend2\"],
  \"content_ideas\": [{\"title\": \"Blog Title\", \"type\": \"guide\", \"keyword\": \"target kw\"}]
}";
        
        $result = $this->callAI($prompt, 0.6);
        return $this->cleanJsonResponse($result);
    }
    
    /**
     * AI Content Rewrite/Improve
     */
    public function improveContent(string $content, string $instruction): ?string {
        $prompt = "Improve this content: {$instruction}

Content:
" . mb_substr($content, 0, 3000) . "

Return the improved content as plain text (no JSON wrapper).";
        
        return $this->callAI($prompt, 0.7);
    }
    
    /**
     * AI Topic Cluster Generation
     */
    public function generateTopicCluster(string $pillarTopic): ?array {
        $prompt = "Generate a comprehensive topic cluster for the pillar topic: \"{$pillarTopic}\" for a Kashmir products e-commerce store.

Return JSON:
{
  \"pillar_page\": {\"title\": \"Pillar Title\", \"target_keyword\": \"main keyword\", \"word_count\": 3000},
  \"cluster_articles\": [
    {\"title\": \"Article Title\", \"target_keyword\": \"keyword\", \"word_count\": 1500, \"link_to_pillar\": true},
    ...
  ],
  \"internal_link_strategy\": \"description of how to interlink\",
  \"topical_authority_score\": 0-100
}";
        
        $result = $this->callAI($prompt, 0.5);
        return $this->cleanJsonResponse($result);
    }
    
    /**
     * AI One-Click Fix Engine — generates specific fix for a given issue
     */
    public function generateAiFix(string $fixType, array $context): ?array {
        $title = $context['title'] ?? '';
        $content = mb_substr(strip_tags($context['content'] ?? ''), 0, 2000);
        $keyword = $context['keyword'] ?? '';
        $metaDesc = $context['meta_description'] ?? '';
        $issue = $context['issue'] ?? '';
        
        $prompts = [
            'ai_rewrite_meta' => "Rewrite the SEO meta title and description for better ranking.
Current title: \"{$title}\"
Current description: \"{$metaDesc}\"
Keyword: \"{$keyword}\"
Issue: {$issue}
Return JSON: {\"title\": \"optimized title (50-60 chars)\", \"meta_description\": \"optimized description (120-160 chars)\", \"title_variants\": [\"variant1\", \"variant2\"], \"explanation\": \"why this is better\"}",

            'ai_rewrite_title' => "Rewrite this title for maximum CTR (click-through rate) on Google.
Current title: \"{$title}\"
Keyword: \"{$keyword}\"
Add power words, emotional triggers, numbers, or brackets.
Return JSON: {\"title\": \"high CTR title\", \"variants\": [\"option1\", \"option2\", \"option3\"], \"ctr_improvements\": [\"what was improved\"]}",

            'ai_expand' => "Expand this content to be more comprehensive and rank-worthy. Add missing subtopics, examples, and depth.
Keyword: \"{$keyword}\"
Current content (first 2000 chars): {$content}
Return JSON: {\"expanded_paragraphs\": [\"paragraph 1\", \"paragraph 2\"], \"suggested_headings\": [\"H2: heading\"], \"missing_topics\": [\"topic1\"], \"faq_section\": [{\"q\": \"question\", \"a\": \"answer\"}]}",

            'ai_add_faq' => "Generate an FAQ section for this content that's optimized for Google's People Also Ask and AI Overview.
Topic: \"{$title}\"
Keyword: \"{$keyword}\"
Content context: " . mb_substr($content, 0, 1000) . "
Return JSON: {\"faqs\": [{\"question\": \"Q\", \"answer\": \"concise answer (2-3 sentences)\"}], \"schema_ready\": true}",

            'ai_add_entities' => "Identify missing semantic entities and NLP terms for this content to improve topical relevance.
Keyword: \"{$keyword}\"
Content: {$content}
Return JSON: {\"missing_entities\": [\"entity1\"], \"entity_paragraphs\": [\"paragraph incorporating entities\"], \"lsi_keywords\": [\"related term\"], \"semantic_score_improvement\": \"+20%\"}",

            'ai_add_eeat' => "Add E-E-A-T (Experience, Expertise, Authoritativeness, Trust) signals to this content.
Content: " . mb_substr($content, 0, 1500) . "
Return JSON: {\"experience_additions\": [\"text to add\"], \"expertise_signals\": [\"text\"], \"trust_elements\": [\"element\"], \"citations_to_add\": [\"reference\"]}",

            'ai_keyword_optimize' => "Optimize keyword placement in this content without keyword stuffing.
Keyword: \"{$keyword}\"
Title: \"{$title}\"
Content (first 1500 chars): " . mb_substr($content, 0, 1500) . "
Return JSON: {\"optimized_title\": \"title with keyword\", \"first_paragraph\": \"rewritten intro with keyword\", \"heading_suggestions\": [\"H2 with keyword\"], \"keyword_placement_tips\": [\"tip1\"]}",

            'ai_add_links' => "Suggest internal linking opportunities for this content on a Kashmir products e-commerce store.
Title: \"{$title}\"
Keyword: \"{$keyword}\"
Return JSON: {\"suggested_links\": [{\"anchor_text\": \"text\", \"target_page_type\": \"product|blog|category\", \"context\": \"where to place\"}], \"external_references\": [{\"anchor\": \"text\", \"type\": \"authority source\"}]}",
        ];
        
        $prompt = $prompts[$fixType] ?? "Fix this SEO issue: {$issue}\nTitle: \"{$title}\"\nKeyword: \"{$keyword}\"\nReturn JSON with the fix.";
        
        $result = $this->callAI($prompt, 0.5);
        return $this->cleanJsonResponse($result);
    }
    
    /**
     * AI Schema JSON-LD Generator
     */
    public function generateSchema(string $type, array $data): ?array {
        $prompts = [
            'product' => "Generate a complete Product JSON-LD schema for:
Name: \"{$data['name']}\"
Description: \"" . mb_substr($data['description'] ?? '', 0, 500) . "\"
Price: " . ($data['price'] ?? 0) . "
Currency: INR
Image: \"{$data['image']}\"
URL: \"{$data['url']}\"
Brand: \"Gilaf Store\"
SKU: \"{$data['sku']}\"
Return JSON: {\"schema\": {full Product schema object}, \"additional_schemas\": [{\"type\": \"BreadcrumbList\", \"schema\": {}}]}",

            'article' => "Generate a complete Article JSON-LD schema for:
Title: \"{$data['title']}\"
Description: \"" . mb_substr($data['description'] ?? '', 0, 300) . "\"
Author: \"" . ($data['author'] ?? 'Gilaf Store') . "\"
Published: \"" . ($data['date'] ?? date('Y-m-d')) . "\"
Image: \"{$data['image']}\"
URL: \"{$data['url']}\"
Return JSON: {\"schema\": {full Article schema object}, \"additional_schemas\": []}",

            'faq' => "Generate FAQPage JSON-LD schema from these Q&As:
" . json_encode($data['faqs'] ?? []) . "
Return JSON: {\"schema\": {full FAQPage schema object}}",

            'breadcrumb' => "Generate BreadcrumbList JSON-LD schema for:
Path: " . json_encode($data['breadcrumbs'] ?? []) . "
Base URL: \"https://gilafstore.com\"
Return JSON: {\"schema\": {full BreadcrumbList schema object}}",

            'howto' => "Generate HowTo JSON-LD schema for:
Title: \"{$data['title']}\"
Steps: " . json_encode($data['steps'] ?? []) . "
Return JSON: {\"schema\": {full HowTo schema object}}",

            'organization' => "Generate a complete Organization JSON-LD schema for:
Name: \"" . ($data['name'] ?? 'Gilaf Store') . "\"
URL: \"" . ($data['url'] ?? 'https://gilafstore.com') . "\"
Description: \"" . ($data['description'] ?? '') . "\"
Logo: \"" . ($data['logo'] ?? 'https://gilafstore.com/assets/images/logo.png') . "\"
Include sameAs for social media (Facebook, Instagram).
Return JSON: {\"schema\": {full Organization schema object}, \"additional_schemas\": []}",

            'collectionpage' => "Generate a CollectionPage JSON-LD schema for a category/collection page:
Name: \"" . ($data['name'] ?? $data['title'] ?? '') . "\"
Description: \"" . ($data['description'] ?? '') . "\"
URL: \"" . ($data['url'] ?? 'https://gilafstore.com') . "\"
Include hasPart array and breadcrumb.
Return JSON: {\"schema\": {full CollectionPage schema object}, \"additional_schemas\": [{\"type\": \"BreadcrumbList\", \"schema\": {}}]}",
        ];
        
        $prompt = $prompts[$type] ?? null;
        if (!$prompt) return null;
        
        $result = $this->callAI($prompt, 0.2);
        return $this->cleanJsonResponse($result);
    }
}

// ============================================================
// DETAILED SEO ANALYZER — Enterprise Deep Analysis Engine v5
// Provides per-issue location, severity, fix suggestions,
// before/after previews, SEO impact meter, priority ordering
// ============================================================
class DetailedSeoAnalyzer {

    private $content;
    private $plainText;
    private $sentences;
    private $paragraphs;
    private $headings;
    private $wordCount;
    private $focusKeyword;
    private $metaTitle;
    private $metaDescription;
    private $slug;
    private $title;
    private $excerpt;
    private $secondaryKeywords;

    private static $TRANSITION_WORDS = [
        'additionally','also','although','and','another','because','before','besides','but',
        'consequently','conversely','despite','either','especially','even though','finally',
        'first','for example','for instance','furthermore','generally','hence','however',
        'importantly','in addition','in contrast','in fact','in other words','in particular',
        'indeed','instead','likewise','meanwhile','moreover','nevertheless','next','nonetheless',
        'notably','obviously','on the contrary','on the other hand','otherwise','particularly',
        'previously','rather','similarly','since','so','specifically','still','subsequently',
        'therefore','though','thus','to begin with','to summarize','ultimately','whereas',
        'while','yet','as a result','as well as','due to','given that','in conclusion',
        'in summary','not only','provided that','that is','to illustrate'
    ];

    private static $PASSIVE_PATTERNS = [
        '/\b(is|are|was|were|be|been|being)\s+(being\s+)?(\w+ed|built|done|found|given|known|made|seen|shown|taken|told|used|written|sold|bought|held|kept|left|brought|caught|felt|heard|kept|led|lost|meant|met|paid|put|read|sent|set|stood|taught|thought|understood|won)\b/i',
    ];

    public function analyze(array $data): array {
        $this->content           = $data['content'] ?? '';
        $this->title             = $data['title'] ?? '';
        $this->metaTitle         = $data['meta_title'] ?? $this->title;
        $this->metaDescription   = $data['meta_description'] ?? '';
        $this->slug              = $data['slug'] ?? '';
        $this->excerpt           = $data['excerpt'] ?? '';

        $kwRaw = $data['focus_keyword'] ?? $data['meta_keywords'] ?? '';
        $kwArr = array_map('trim', explode(',', $kwRaw));
        $this->focusKeyword       = mb_strtolower(trim($kwArr[0] ?? ''));
        $this->secondaryKeywords  = array_map('mb_strtolower', array_slice($kwArr, 1));

        $this->plainText  = strip_tags($this->content);
        $this->wordCount  = str_word_count($this->plainText);
        $this->sentences  = preg_split('/(?<=[.!?])\s+/', $this->plainText, -1, PREG_SPLIT_NO_EMPTY);
        $this->sentences  = array_values(array_filter($this->sentences, fn($s) => str_word_count(trim($s)) > 2));
        $this->paragraphs = preg_split('/\n\s*\n/', $this->plainText, -1, PREG_SPLIT_NO_EMPTY);
        $this->paragraphs = array_values(array_filter($this->paragraphs, fn($p) => trim($p) !== ''));

        preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/i', $this->content, $hMatches, PREG_SET_ORDER);
        $this->headings = $hMatches;

        $issues = [];
        $issues = array_merge($issues, $this->detectThinParagraphs());
        $issues = array_merge($issues, $this->detectLongSentences());
        $issues = array_merge($issues, $this->detectPassiveVoice());
        $issues = array_merge($issues, $this->detectRepetitivePhrases());
        $issues = array_merge($issues, $this->detectTransitionWordGaps());
        $issues = array_merge($issues, $this->detectReadabilityIssues());
        $issues = array_merge($issues, $this->detectKeywordIssues());
        $issues = array_merge($issues, $this->detectHeadingIssues());
        $issues = array_merge($issues, $this->detectContentQualityIssues());
        $issues = array_merge($issues, $this->detectInternalLinkIssues());

        usort($issues, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return [
            'issues'       => $issues,
            'total'        => count($issues),
            'critical'     => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
            'warnings'     => count(array_filter($issues, fn($i) => $i['severity'] === 'warning')),
            'moderate'     => count(array_filter($issues, fn($i) => $i['severity'] === 'moderate')),
            'readability'  => $this->getReadabilityBreakdown(),
            'word_count'   => $this->wordCount,
        ];
    }

    // ——— Thin Paragraph Detection (v5 — Full AI Intelligence) ———
    private function detectThinParagraphs(): array {
        $issues   = [];
        $thinList = [];
        $totalWc  = 0;
        foreach ($this->paragraphs as $idx => $para) {
            $text = trim($para);
            $wc   = str_word_count($text);
            if ($wc > 0 && $wc < 40) {
                $section = $this->getNearestHeading($idx);
                if ($wc < 10)      { $sevLevel = 'very_thin'; $sevLabel = 'Very Thin'; }
                elseif ($wc < 20)  { $sevLevel = 'thin';      $sevLabel = 'Thin'; }
                else               { $sevLevel = 'moderate';  $sevLabel = 'Moderate'; }
                $missing    = $this->analyzeMissingElements($text);
                $depthScore = $this->calcParaDepthScore($text, $wc);
                $aiRewrite  = $this->generateParaRewrite($text, $section);
                $totalWc   += $wc;
                $thinList[] = [
                    'paragraph_num'    => $idx + 1,
                    'word_count'       => $wc,
                    'recommended'      => 50,
                    'text'             => mb_substr($text, 0, 300),
                    'section'          => $section,
                    'severity_level'   => $sevLevel,
                    'severity_label'   => $sevLabel,
                    'missing_elements' => $missing,
                    'depth_score'      => $depthScore,
                    'ai_rewrite'       => $aiRewrite,
                ];
            }
        }
        $count = count($thinList);
        if ($count > 2) {
            $avgWc     = $count > 0 ? round($totalWc / $count) : 0;
            $scoreGain = min(15, 4 + (int)round($count / 3));
            $worst     = $thinList[0];
            $issues[]  = [
                'id'          => 'thin_paragraphs',
                'title'       => 'Thin Paragraphs Detected',
                'severity'    => $count > 8 ? 'critical' : 'warning',
                'priority'    => 1,
                'seo_impact'  => 'high',
                'score_gain'  => $scoreGain,
                'module'      => 'Content Quality',
                'explanation' => [
                    'what' => 'Your content contains ' . $count . ' paragraphs with fewer than 40 words each.',
                    'why'  => 'Google\'s Quality Rater Guidelines penalize thin, low-value content. Short paragraphs signal shallow coverage of a topic, reducing topical authority and dwell time.',
                    'how'  => 'Search engines measure content depth by evaluating semantic coverage, word count, and paragraph substance. Thin paragraphs score poorly on these signals.',
                    'ux'   => 'Users who find thin content leave quickly (high bounce rate), which is a negative ranking signal for Google.',
                ],
                'thin_paragraphs_data' => [
                    'all'        => $thinList,
                    'count'      => $count,
                    'avg_words'  => $avgWc,
                    'score_gain' => $scoreGain,
                ],
                'locations' => array_slice($thinList, 0, 5),
                'fix_suggestions' => [
                    'Expand each thin paragraph to at least 50 words by adding supporting details, examples, or data.',
                    'Merge two consecutive thin paragraphs if they cover the same subtopic.',
                    'Add statistics, quotes, or expert opinions to support thin claims.',
                    'Use the APE structure: state the point, prove it with data, give an example.',
                    'Add a transition sentence leading into the next paragraph for better flow.',
                ],
                'before_after' => [
                    'before' => mb_substr($worst['text'], 0, 120),
                    'after'  => $worst['ai_rewrite'],
                ],
                'ai_suggestions' => [
                    'Add NLP entities: measurements, percentages, expert names, and study references.',
                    'Include FAQ-style sub-questions within each section to improve topical depth.',
                    'Use semantic keywords related to the section topic for better coverage.',
                    'Add a "Key Takeaway" sentence at the end of each major section.',
                ],
            ];
        }
        return $issues;
    }

    // ——— Missing Elements Analyzer ———
    private function analyzeMissingElements(string $text): array {
        $missing = [];
        if (!preg_match('/\b(because|since|therefore|this means|which means|hence|as a result|so that)\b/i', $text))
            $missing[] = ['type' => 'explanation', 'label' => 'Missing explanation'];
        if (!preg_match('/\b(for example|for instance|such as|e\.g\.|including|namely)\b/i', $text))
            $missing[] = ['type' => 'example', 'label' => 'Missing examples'];
        if (!preg_match('/\d+(\s*%|\s*(percent|million|billion|kg|g|mg|ml|km|cm|years?|months?|days?|times?))?/i', $text))
            $missing[] = ['type' => 'statistics', 'label' => 'Missing statistics'];
        if (!preg_match('/\b(benefit|help|improve|boost|increase|reduce|prevent|protect|support|enhance|promote|treat)\b/i', $text))
            $missing[] = ['type' => 'benefits', 'label' => 'Missing benefits'];
        if (!preg_match('/\b(you|your|user|people|everyone|anyone|customers|readers|they|their)\b/i', $text))
            $missing[] = ['type' => 'user_intent', 'label' => 'Missing user intent'];
        if (!preg_match('/\b(compared|versus|vs\.|unlike|better than|more than|less than|similar|different|alternative)\b/i', $text))
            $missing[] = ['type' => 'comparison', 'label' => 'Missing comparison'];
        if (!preg_match('/\b(research|study|expert|according|proven|evidence|data|findings|shows|reveals|published)\b/i', $text))
            $missing[] = ['type' => 'authority', 'label' => 'Missing topical authority'];
        if (str_word_count($text) < 15)
            $missing[] = ['type' => 'depth', 'label' => 'Low semantic depth'];
        return $missing;
    }

    // ——— Paragraph Depth Score Calculator ———
    private function calcParaDepthScore(string $text, int $wc): int {
        $score  = min(40, (int)($wc / 50 * 40));
        if (preg_match('/\b(because|since|therefore|hence|as a result)\b/i', $text)) $score += 15;
        if (preg_match('/\b(for example|for instance|such as|e\.g\.)\b/i', $text))   $score += 10;
        if (preg_match('/\d+(\s*%|\s*(percent|million|kg|g))/', $text))               $score += 10;
        if (preg_match('/\b(you|your|user|people)\b/i', $text))                       $score += 5;
        if (preg_match('/\b(benefit|help|improve|boost|reduce|protect)\b/i', $text))  $score += 10;
        if (preg_match('/\b(research|study|expert|proven|evidence)\b/i', $text))      $score += 10;
        return min(100, $score);
    }

    // ——— AI Paragraph Rewrite Generator ———
    private function generateParaRewrite(string $text, string $section): string {
        $short   = mb_substr(trim($text), 0, 100);
        $section = trim(strip_tags($section));
        $templates = [
            "{$short} This is particularly important because it directly impacts the effectiveness and quality of the {$section} section. Research consistently shows that providing adequate context and supporting details significantly improves user engagement and topical authority. For best results, include at least one specific example or statistic to substantiate this point and give readers actionable insight.",
            "{$short} Understanding this in detail is essential for making well-informed decisions. Multiple studies confirm that readers who encounter detailed, well-supported content are more likely to stay on the page and take action. Consider adding a real-world example or referencing a credible finding that reinforces your main point and builds trust with the reader.",
            "{$short} To get the most value from this, experts recommend expanding on these key ideas with practical examples, measurements, or documented outcomes. Data-backed statements and specific details not only improve content credibility but also signal topical depth to search engines — a critical factor for achieving higher organic rankings in competitive niches.",
        ];
        return $templates[abs(crc32($text)) % count($templates)];
    }

    // ——— Long Sentence Detection ———
    private function detectLongSentences(): array {
        $issues = [];
        $longList = [];
        foreach ($this->sentences as $idx => $sent) {
            $wc = str_word_count(trim($sent));
            if ($wc > 30) {
                $longList[] = [
                    'sentence_num' => $idx + 1,
                    'word_count'   => $wc,
                    'text'         => mb_substr(trim($sent), 0, 250),
                ];
            }
        }
        if (count($longList) > 0) {
            $pct = count($this->sentences) > 0 ? round((count($longList) / count($this->sentences)) * 100) : 0;
            if ($pct > 15) {
                $worst = $longList[0];
                $issues[] = [
                    'id'          => 'long_sentences',
                    'title'       => 'Long Sentences Detected',
                    'severity'    => $pct > 30 ? 'critical' : 'warning',
                    'priority'    => 2,
                    'seo_impact'  => 'medium',
                    'score_gain'  => 6,
                    'module'      => 'Readability',
                    'explanation' => [
                        'what' => "{$pct}% of sentences (" . count($longList) . " total) exceed 30 words. The ideal is ≤20 words per sentence.",
                        'why'  => 'Long sentences reduce the Flesch Reading Ease score. Google uses readability as a ranking factor, especially for featured snippets which favor simple, clear language.',
                        'how'  => 'Tools like Hemingway App and Yoast grade sentence length. Sentences over 30 words are flagged as "very hard to read" (college level or above).',
                        'ux'   => 'Mobile users especially struggle with long sentences. High bounce rates from poor readability signal low content quality to Google.',
                    ],
                    'locations' => array_slice($longList, 0, 5),
                    'fix_suggestions' => [
                        'Split sentences over 30 words into two shorter sentences at a natural conjunction (and, but, because).',
                        'Replace complex compound sentences with bullet points when listing multiple items.',
                        'Move subordinate clauses to a separate sentence.',
                        'Use the active voice — it naturally shortens sentences.',
                        'Aim for an average of 15-20 words per sentence.',
                    ],
                    'before_after' => [
                        'before' => '"' . mb_substr($worst['text'], 0, 200) . '"',
                        'after'  => '"[Break at the first conjunction or semicolon.] [Second part as a new sentence starting with a transition word like \'This means\', \'As a result\', or \'Therefore\'.]"',
                    ],
                    'ai_suggestions' => [
                        'Rewrite long sentences using the SVO (Subject-Verb-Object) structure.',
                        'Use short power words like: pure, natural, proven, trusted, fresh.',
                        'Convert long explanatory sentences into numbered steps when sequential.',
                    ],
                ];
            }
        }
        return $issues;
    }

    // ——— Passive Voice Detection ———
    private function detectPassiveVoice(): array {
        $issues = [];
        $passiveList = [];
        foreach ($this->sentences as $idx => $sent) {
            foreach (self::$PASSIVE_PATTERNS as $pattern) {
                if (preg_match($pattern, $sent)) {
                    $passiveList[] = [
                        'sentence_num' => $idx + 1,
                        'text'         => mb_substr(trim($sent), 0, 200),
                    ];
                    break;
                }
            }
        }
        if (count($this->sentences) > 0) {
            $pct = round((count($passiveList) / count($this->sentences)) * 100);
            if ($pct > 10) {
                $worst = $passiveList[0] ?? [];
                $issues[] = [
                    'id'          => 'passive_voice',
                    'title'       => 'Passive Voice Overuse',
                    'severity'    => $pct > 20 ? 'warning' : 'moderate',
                    'priority'    => 3,
                    'seo_impact'  => 'medium',
                    'score_gain'  => 5,
                    'module'      => 'Readability',
                    'explanation' => [
                        'what' => "{$pct}% of sentences use passive voice. Yoast recommends keeping this below 10%.",
                        'why'  => 'Passive voice makes content harder to read and less engaging. Active voice is more direct, authoritative, and easier to understand.',
                        'how'  => 'Passive constructions like "is known by", "are used by", "was found to" increase reading complexity scores.',
                        'ux'   => 'Active voice creates a stronger connection with the reader and improves content scannability.',
                    ],
                    'locations'   => array_slice($passiveList, 0, 5),
                    'fix_suggestions' => [
                        'Identify the subject of the action and move it to the beginning of the sentence.',
                        'Replace "is used by" → "X uses", "is known for" → "X is known for" → "experts recognize".',
                        'Remove "being" from sentences wherever possible.',
                        'Start sentences with strong action verbs: delivers, creates, boosts, reveals, proves.',
                        'Read the sentence aloud — if it sounds awkward, it likely needs to be active.',
                    ],
                    'before_after' => [
                        'before' => !empty($worst['text']) ? '"' . $worst['text'] . '"' : '"This product is used by many people in Kashmir."',
                        'after'  => '"[Subject] + [active verb] + [object]. Example: Many Kashmiris use this product daily for [specific benefit]."',
                    ],
                    'ai_suggestions' => [
                        'Use first-person plural ("we provide", "you get") for stronger brand voice.',
                        'Use imperative verbs in CTAs: "Discover", "Try", "Experience", "Get".',
                        'Replace generic passive phrases with specific brand-voice statements.',
                    ],
                ];
            }
        }
        return $issues;
    }

    // ——— Repetitive Phrase Detection ———
    private function detectRepetitivePhrases(): array {
        $issues = [];
        $text = mb_strtolower($this->plainText);
        $phrases = [];
        // Extract 3-4 word phrases
        $words = preg_split('/\s+/', $text);
        for ($i = 0; $i < count($words) - 3; $i++) {
            $phrase3 = implode(' ', array_slice($words, $i, 3));
            $phrase4 = implode(' ', array_slice($words, $i, 4));
            // Skip phrases with stop words only
            $stopWords = ['the','a','an','in','on','at','of','and','or','but','is','are','was','were','to','for','with','that','this','it','as','by'];
            $phraseWords = explode(' ', $phrase3);
            $allStop = array_reduce($phraseWords, fn($carry, $w) => $carry && in_array($w, $stopWords), true);
            if (!$allStop) {
                if (!isset($phrases[$phrase3])) $phrases[$phrase3] = 0;
                $phrases[$phrase3]++;
            }
        }
        arsort($phrases);
        $repetitive = array_filter($phrases, fn($count) => $count >= 4);
        $repetitive = array_slice($repetitive, 0, 8);

        if (!empty($repetitive)) {
            $worstPhrase = array_key_first($repetitive);
            $worstCount  = $repetitive[$worstPhrase];
            $repList = [];
            foreach ($repetitive as $phrase => $count) {
                // Find first occurrence paragraph
                $paraIdx = 0;
                foreach ($this->paragraphs as $pi => $para) {
                    if (mb_strpos(mb_strtolower($para), $phrase) !== false) { $paraIdx = $pi; break; }
                }
                $repList[] = [
                    'phrase'        => $phrase,
                    'occurrences'   => $count,
                    'paragraph_num' => $paraIdx + 1,
                    'section'       => $this->getNearestHeading($paraIdx),
                ];
            }
            $issues[] = [
                'id'          => 'repetitive_phrases',
                'title'       => 'Repetitive Keyword Phrases Detected',
                'severity'    => $worstCount > 8 ? 'critical' : 'warning',
                'priority'    => 2,
                'seo_impact'  => 'high',
                'score_gain'  => 7,
                'module'      => 'Content Quality',
                'explanation' => [
                    'what' => count($repetitive) . ' phrases are repeated ' . $worstCount . '+ times. The most repeated phrase is "' . $worstPhrase . '" (' . $worstCount . ' occurrences).',
                    'why'  => 'Google\'s Panda algorithm penalizes keyword stuffing and repetitive content. Over-repetition signals low-quality, thin content to crawlers.',
                    'how'  => 'Google\'s NLP engine (BERT/MUM) understands semantic variations. Using LSI keywords and synonyms signals deeper topical coverage.',
                    'ux'   => 'Repetitive phrases bore readers, reduce engagement, and increase bounce rate.',
                ],
                'locations'   => $repList,
                'fix_suggestions' => [
                    'Replace repeated phrases with semantic synonyms and LSI keywords.',
                    'Use pronoun references ("it", "this", "these") instead of repeating nouns.',
                    'Restructure sentences to vary how ideas are expressed.',
                    'Consult Google\'s "Related Searches" for natural keyword variations.',
                    'Use tools like LSI Graph or Google NLP to find semantic alternatives.',
                ],
                'before_after' => [
                    'before' => '"' . $worstPhrase . '" appears ' . $worstCount . ' times in nearby paragraphs.',
                    'after'  => 'Replace with variations: [synonym 1], [related term], [NLP variation], [contextual phrase].',
                ],
                'ai_suggestions' => [
                    'Semantic variations: use related entity names, product attributes, and category terms.',
                    'NLP keywords: search Google for "People also ask" questions on the topic.',
                    'Use "pure", "authentic", "traditional", "artisanal" as natural quality descriptors.',
                    'Consider adding a dedicated FAQ section to naturally use question-form keywords.',
                ],
            ];
        }
        return $issues;
    }

    // ——— Transition Word Gap Detection ———
    private function detectTransitionWordGaps(): array {
        $issues = [];
        if (count($this->sentences) < 5) return $issues;

        $noTransition = [];
        foreach ($this->sentences as $idx => $sent) {
            $lower = mb_strtolower(trim($sent));
            $hasTransition = false;
            foreach (self::$TRANSITION_WORDS as $tw) {
                if (mb_strpos($lower, $tw) === 0 || mb_strpos($lower, ', ' . $tw) !== false) {
                    $hasTransition = true; break;
                }
            }
            if (!$hasTransition && str_word_count(trim($sent)) > 5) {
                $noTransition[] = ['sentence_num' => $idx + 1, 'text' => mb_substr(trim($sent), 0, 150)];
            }
        }

        $pct = round((count($noTransition) / count($this->sentences)) * 100);
        if ($pct > 70) {
            $sample = array_slice($noTransition, 0, 5);
            $issues[] = [
                'id'          => 'missing_transitions',
                'title'       => 'Missing Transition Words',
                'severity'    => 'moderate',
                'priority'    => 4,
                'seo_impact'  => 'medium',
                'score_gain'  => 5,
                'module'      => 'Readability',
                'explanation' => [
                    'what' => "Only " . (100 - $pct) . "% of sentences begin with or contain transition words. Yoast recommends 30%+.",
                    'why'  => 'Transition words guide readers through content flow, improving readability scores and time-on-page.',
                    'how'  => 'Search engines use transition words as signals of well-structured, coherent content.',
                    'ux'   => 'Without transitions, content feels choppy and disconnected, causing readers to abandon the page.',
                ],
                'locations'   => $sample,
                'fix_suggestions' => [
                    'Start paragraphs with: "Furthermore,", "In addition,", "As a result,", "However,", "Notably,".',
                    'Connect ideas between sentences: "This means that...", "Building on this...", "For example,".',
                    'Use contrast transitions: "Despite this,", "On the other hand,", "Nevertheless,".',
                    'Add summary transitions before conclusions: "In summary,", "Ultimately,", "Therefore,".',
                    'Sequence content with: "First,", "Second,", "Finally,", "To begin with,".',
                ],
                'before_after' => [
                    'before' => '"' . ($sample[0]['text'] ?? 'Kashmiri honey is pure and natural.') . '"',
                    'after'  => '"Furthermore, [sentence]. Additionally, [next point]. This is especially important because [reason]."',
                ],
                'ai_suggestions' => [
                    'Add discourse markers between each topic change.',
                    'Use cohesive devices: "this", "such", "these" to refer back to previous ideas.',
                    'Consider a "What does this mean for you?" connector sentence between sections.',
                ],
            ];
        }
        return $issues;
    }

    // ——— Readability Issues ———
    private function detectReadabilityIssues(): array {
        $issues = [];
        $breakdown = $this->getReadabilityBreakdown();
        $flesch = $breakdown['flesch_score'];

        if ($flesch < 50 && $this->wordCount >= 100) {
            $issues[] = [
                'id'          => 'low_flesch_score',
                'title'       => 'Low Flesch Reading Ease Score',
                'severity'    => $flesch < 30 ? 'critical' : 'warning',
                'priority'    => 1,
                'seo_impact'  => 'high',
                'score_gain'  => 10,
                'module'      => 'Readability',
                'explanation' => [
                    'what' => "Flesch Reading Ease: {$flesch}/100 ({$breakdown['reading_level']}). Ideal is 60+ (Standard/Plain English).",
                    'why'  => 'Google favors content that is accessible to the average reader (Grade 7-8 reading level). Low Flesch scores indicate college-level complexity that most users find difficult.',
                    'how'  => 'The Flesch formula penalizes: long sentences (>20 words), polysyllabic words (3+ syllables), complex vocabulary.',
                    'ux'   => 'High reading difficulty = lower dwell time, higher bounce rate, fewer social shares.',
                ],
                'locations'   => [
                    ['note' => "Average sentence length: {$breakdown['avg_sentence_length']} words"],
                    ['note' => "Complex word percentage: ~{$breakdown['complex_word_pct']}%"],
                    ['note' => "Passive voice usage: {$breakdown['passive_pct']}%"],
                ],
                'fix_suggestions' => [
                    'Replace polysyllabic words with simpler alternatives (e.g., "utilize" → "use", "demonstrate" → "show").',
                    'Break sentences over 20 words into two shorter sentences.',
                    'Use contractions where natural ("it\'s", "you\'ll", "we\'re").',
                    'Avoid jargon — explain technical terms immediately after using them.',
                    'Use bullet points and numbered lists to reduce sentence complexity.',
                ],
                'before_after' => [
                    'before' => '"The utilization of authentic Kashmiri saffron demonstrates exceptional antioxidant properties that have been substantiated through numerous scientific investigations."',
                    'after'  => '"Real Kashmiri saffron is a powerful antioxidant. Many scientific studies have proven this. It\'s one reason why it\'s so highly valued."',
                ],
                'ai_suggestions' => [
                    'Target a Flesch score of 60-70 (Plain English / Grade 7-8).',
                    'Use the "Explain it like I\'m 12" test for each paragraph.',
                    'Add concrete examples and analogies to replace abstract explanations.',
                    'Use short paragraphs (3-4 sentences max) with one main idea each.',
                ],
            ];
        }
        return $issues;
    }

    // ——— Keyword Issues ———
    private function detectKeywordIssues(): array {
        $issues = [];
        if (empty($this->focusKeyword)) return $issues;

        $kw = $this->focusKeyword;
        $kwCount = mb_substr_count(mb_strtolower($this->plainText), $kw);
        $density = $this->wordCount > 0 ? round(($kwCount / $this->wordCount) * 100, 2) : 0;

        // Over-optimized
        if ($density > 3.0) {
            $issues[] = [
                'id'          => 'keyword_stuffing',
                'title'       => 'Keyword Over-Optimization (Stuffing)',
                'severity'    => 'critical',
                'priority'    => 1,
                'seo_impact'  => 'high',
                'score_gain'  => 12,
                'module'      => 'Keyword Analysis',
                'explanation' => [
                    'what' => "Keyword \"{$kw}\" appears {$kwCount} times — density of {$density}% (ideal: 0.5–2.5%).",
                    'why'  => 'Google\'s Panda and Penguin algorithms penalize keyword stuffing. Over-optimized content is flagged as manipulative and can result in manual penalties.',
                    'how'  => 'Google evaluates keyword density across the entire document and in each individual section. Context and semantic variation are rewarded.',
                    'ux'   => 'Keyword-stuffed content reads unnaturally and destroys user trust.',
                ],
                'locations'   => [['note' => "Found {$kwCount} occurrences in {$this->wordCount} words"]],
                'fix_suggestions' => [
                    "Replace some occurrences of \"{$kw}\" with semantic synonyms.",
                    'Use LSI keywords (Latent Semantic Indexing) — related terms Google associates with the topic.',
                    'Remove keyword from headings where it appears more than twice.',
                    'Replace keyword with pronouns ("it", "this", "the product") where contextually appropriate.',
                    'Target density of 1–2% maximum.',
                ],
                'before_after' => [
                    'before' => "\"{$kw}\" used {$kwCount} times throughout content.",
                    'after'  => "Keep \"{$kw}\" 1-2 times per 500 words. Use: [synonym 1], [related term], [entity variation].",
                ],
                'ai_suggestions' => [
                    'Use Google\'s "Related Searches" for natural keyword variations.',
                    'Add NLP entities: place names, process names, ingredient names associated with ' . $kw . '.',
                    'Structure content around People Also Ask (PAA) questions about ' . $kw . '.',
                ],
            ];
        }

        // Too low density
        if ($density > 0 && $density < 0.5 && $this->wordCount > 300) {
            $issues[] = [
                'id'          => 'low_keyword_density',
                'title'       => 'Focus Keyword Underused',
                'severity'    => 'warning',
                'priority'    => 2,
                'seo_impact'  => 'high',
                'score_gain'  => 8,
                'module'      => 'Keyword Analysis',
                'explanation' => [
                    'what' => "Keyword \"{$kw}\" density is only {$density}% ({$kwCount} times in {$this->wordCount} words). Aim for 0.5–2.5%.",
                    'why'  => 'Insufficient keyword usage means Google may not associate your content strongly with this search term, reducing ranking potential.',
                    'how'  => 'Google expects the focus keyword to appear naturally throughout: in the intro, subheadings, body paragraphs, and conclusion.',
                    'ux'   => 'Content that doesn\'t address its topic keyword clearly also confuses readers about its purpose.',
                ],
                'locations'   => [['note' => "Add \"{$kw}\" to intro, at least one H2, and the conclusion"]],
                'fix_suggestions' => [
                    "Add \"{$kw}\" to the opening paragraph within the first 100 words.",
                    "Include \"{$kw}\" in at least one H2 subheading.",
                    "Add \"{$kw}\" to the conclusion or summary paragraph.",
                    'Use exact-match keyword in image alt text.',
                    'Ensure the excerpt/meta description contains the keyword.',
                ],
                'before_after' => [
                    'before' => "Keyword \"{$kw}\" only appears {$kwCount} time(s).",
                    'after'  => "Naturally work \"{$kw}\" into 3–5 strategic positions: intro, H2, body ×2, conclusion.",
                ],
                'ai_suggestions' => [
                    'Use the keyword in the first sentence of the article.',
                    'Create a section specifically dedicated to the core benefits of ' . $kw . '.',
                    'Add the keyword to image captions and alt text.',
                ],
            ];
        }

        return $issues;
    }

    // ——— Heading Issues ———
    private function detectHeadingIssues(): array {
        $issues = [];
        $h2Count = 0; $h3Count = 0;
        foreach ($this->headings as $h) {
            if ($h[1] === '2') $h2Count++;
            if ($h[1] === '3') $h3Count++;
        }

        // Missing H2s
        if ($h2Count < 2 && $this->wordCount > 400) {
            $issues[] = [
                'id'          => 'missing_h2',
                'title'       => 'Insufficient H2 Subheadings',
                'severity'    => $h2Count === 0 ? 'critical' : 'warning',
                'priority'    => 2,
                'seo_impact'  => 'high',
                'score_gain'  => 8,
                'module'      => 'Heading Structure',
                'explanation' => [
                    'what' => "Only {$h2Count} H2 heading(s) found. Content of {$this->wordCount} words needs at least 3–5 H2 sections.",
                    'why'  => 'H2 tags are the primary content structure signal for Google crawlers. They define sections and help Google understand what each part of your content covers.',
                    'how'  => 'Google displays H2 subheadings in featured snippets and People Also Ask. Well-structured H2s improve crawlability and indexing.',
                    'ux'   => 'Readers use headings to scan and navigate long content. Without clear sections, users leave without reading.',
                ],
                'locations'   => [['note' => "Current: {$h2Count} H2, {$h3Count} H3. Recommended: 4–8 H2s for {$this->wordCount}-word content."]],
                'fix_suggestions' => [
                    'Add H2 headings every 300–400 words to break content into logical sections.',
                    'Include the focus keyword in at least one H2.',
                    'Use question-format H2s to target PAA (People Also Ask) results.',
                    'Structure H2s to cover: introduction topic, main benefits, how-to, comparison, conclusion.',
                    'Use descriptive H2s (not just "Introduction" but "Why Kashmiri Honey Is Different").',
                ],
                'before_after' => [
                    'before' => 'Long content block with no clear subheadings.',
                    'after'  => 'H2: "What Makes [Topic] Special?" → H3: "Key Benefit 1" → H3: "Key Benefit 2" → H2: "How to Use [Topic]".',
                ],
                'ai_suggestions' => [
                    'Generate H2s from PAA questions on your topic.',
                    'Use "How to", "Why", "What is", "Benefits of" as H2 starters.',
                    'Add a "Frequently Asked Questions" H2 section at the end.',
                ],
            ];
        }

        return $issues;
    }

    // ——— Content Quality Issues ———
    private function detectContentQualityIssues(): array {
        $issues = [];

        // Weak introduction
        $introText = mb_substr($this->plainText, 0, 200);
        $introWords = str_word_count($introText);
        if ($introWords < 20) {
            $issues[] = [
                'id'          => 'weak_introduction',
                'title'       => 'Weak Content Introduction',
                'severity'    => 'warning',
                'priority'    => 1,
                'seo_impact'  => 'high',
                'score_gain'  => 6,
                'module'      => 'Content Quality',
                'explanation' => [
                    'what' => "Introduction is only {$introWords} words. Google reads the opening 200 characters to determine content relevance.",
                    'why'  => 'A weak introduction fails to hook the reader, causing immediate bounces. Google uses first-paragraph relevance as a ranking signal.',
                    'how'  => 'Google\'s algorithm reads the first paragraph to establish topic relevance. Missing keywords in the intro weaken this signal.',
                    'ux'   => 'The first 3 seconds determine if a user stays. A compelling intro sets the expectation and reduces bounce rate.',
                ],
                'locations'   => [['paragraph_num' => 1, 'text' => mb_substr($introText, 0, 200)]],
                'fix_suggestions' => [
                    'Open with a bold statement or surprising fact about your topic.',
                    'Include the focus keyword in the first sentence.',
                    'State clearly who the content is for and what they will learn.',
                    'Add a hook: a question, statistic, or problem statement.',
                    'Aim for a 50–100 word opening paragraph.',
                ],
                'before_after' => [
                    'before' => '"' . mb_substr($introText, 0, 100) . '"',
                    'after'  => '"[Compelling fact or question about topic]. In this guide, you\'ll discover [specific benefit 1], [benefit 2], and [benefit 3]. Whether you\'re [audience type], this article will [promise/outcome]."',
                ],
                'ai_suggestions' => [
                    'Use the PAS (Problem-Agitate-Solution) formula for the introduction.',
                    'Start with a power statistic: "Did you know that [stat] about [topic]?"',
                    'Address the reader directly: "If you\'re looking for [solution], you\'ve come to the right place."',
                ],
            ];
        }

        // Word count too low
        if ($this->wordCount < 800 && $this->wordCount > 50) {
            $issues[] = [
                'id'          => 'low_word_count',
                'title'       => 'Content Too Short (Thin Content)',
                'severity'    => $this->wordCount < 400 ? 'critical' : 'warning',
                'priority'    => 1,
                'seo_impact'  => 'high',
                'score_gain'  => 15,
                'module'      => 'Content Quality',
                'explanation' => [
                    'what' => "{$this->wordCount} words total. Top-ranking Google results average 1,447–2,416 words for competitive keywords.",
                    'why'  => 'Content depth correlates strongly with search rankings. Google rewards comprehensive, authoritative content that fully answers user queries.',
                    'how'  => 'Google\'s Quality Rater Guidelines specifically mention "Needs Met" — content that fully satisfies user intent. Short content rarely does.',
                    'ux'   => 'Short content has higher bounce rates and lower time-on-page, both negative ranking signals.',
                ],
                'locations'   => [['note' => "Current: {$this->wordCount} words. Target: 1,500–2,500 words for competitive rankings."]],
                'fix_suggestions' => [
                    'Add a detailed "How to Use" or "Step-by-Step Guide" section.',
                    'Include a "Frequently Asked Questions" (FAQ) section (5–10 questions).',
                    'Add a comparison section (e.g., "vs" comparisons with alternatives).',
                    'Include a "Benefits" section with detailed explanations of each benefit.',
                    'Add a "Buying Guide" or "What to Look For" section.',
                ],
                'before_after' => [
                    'before' => "{$this->wordCount} words — content covers the topic at a surface level.",
                    'after'  => "1,500+ words — covers: Introduction, Benefits (×5), How to Use, Storage Tips, FAQ (×5), Conclusion.",
                ],
                'ai_suggestions' => [
                    'Use PAA (People Also Ask) questions to generate new sections.',
                    'Research "Related Searches" on Google for sub-topics to add.',
                    'Add a "Common Mistakes to Avoid" section.',
                    'Include user testimonials or case studies as supporting content.',
                ],
            ];
        }

        return $issues;
    }

    // ——— Internal Link Issues ———
    private function detectInternalLinkIssues(): array {
        $issues = [];
        preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $this->content, $linkMatches);
        $links = $linkMatches[1] ?? [];
        $internalLinks = array_filter($links, fn($l) =>
            mb_strpos($l, 'gilafstore') !== false || mb_strpos($l, '/blog/') !== false || mb_strpos($l, '/product/') !== false || (mb_strpos($l, 'http') === false && mb_strpos($l, '//') === false)
        );
        $linkCount = count($internalLinks);

        if ($linkCount < 2 && $this->wordCount > 300) {
            $issues[] = [
                'id'          => 'missing_internal_links',
                'title'       => 'Missing Internal Links',
                'severity'    => $linkCount === 0 ? 'critical' : 'warning',
                'priority'    => 2,
                'seo_impact'  => 'high',
                'score_gain'  => 8,
                'module'      => 'Internal Links',
                'explanation' => [
                    'what' => "Only {$linkCount} internal link(s) found. Best practice is 3–5 internal links per article.",
                    'why'  => 'Internal links distribute PageRank throughout your site, helping Google discover and rank more pages. They also signal content relationships.',
                    'how'  => 'Google\'s crawlers follow internal links to discover new pages. Pages without internal links are harder to index and rank.',
                    'ux'   => 'Internal links keep users on your site longer, reducing bounce rate and increasing pages-per-session.',
                ],
                'locations'   => [['note' => "Add links to: related blog posts, relevant products, category pages."]],
                'fix_suggestions' => [
                    'Link to 2–3 related blog posts using descriptive anchor text.',
                    'Link to relevant products with keyword-rich anchor text.',
                    'Add a "Related Articles" or "You Might Also Like" section.',
                    'Link to your most important pillar pages from within the content.',
                    'Use contextual anchor text — not just "click here" but descriptive phrases.',
                ],
                'before_after' => [
                    'before' => 'Content mentions related topics with no links to supporting pages.',
                    'after'  => 'Add: <a href="/blog/[related-slug]">anchor text</a> naturally within paragraph context.',
                ],
                'ai_suggestions' => [
                    'Use the Internal Linking tab (above) to find semantic link opportunities.',
                    'Link to your product pages from benefit-related sentences.',
                    'Add a "Related Reading" box linking to 3 related posts.',
                ],
            ];
        }

        return $issues;
    }

    // ——— Readability Breakdown ———
    public function getReadabilityBreakdown(): array {
        if ($this->wordCount < 10) {
            return ['flesch_score' => 0, 'reading_level' => 'N/A', 'avg_sentence_length' => 0,
                    'complex_word_pct' => 0, 'passive_pct' => 0, 'transition_pct' => 0,
                    'long_sentence_pct' => 0, 'paragraph_count' => 0];
        }

        $sentCount = count($this->sentences);
        $avgLen = $sentCount > 0 ? round($this->wordCount / $sentCount, 1) : 0;

        // Syllable estimation
        $syllables = 0;
        $complexWords = 0;
        $words = preg_split('/\s+/', $this->plainText);
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/i', '', mb_strtolower($word));
            if (!$word) continue;
            $syl = max(1, preg_match_all('/[aeiou]+/i', $word));
            $syllables += $syl;
            if ($syl >= 3) $complexWords++;
        }
        $totalWords = count(array_filter($words, fn($w) => trim($w) !== ''));

        $flesch = 0;
        if ($sentCount > 0 && $totalWords > 0) {
            $flesch = round(206.835 - (1.015 * ($totalWords / $sentCount)) - (84.6 * ($syllables / $totalWords)), 1);
            $flesch = max(0, min(100, $flesch));
        }

        $level = match(true) {
            $flesch >= 90 => 'Very Easy (5th grade)',
            $flesch >= 80 => 'Easy (6th grade)',
            $flesch >= 70 => 'Fairly Easy (7th grade)',
            $flesch >= 60 => 'Standard (8th–9th grade)',
            $flesch >= 50 => 'Fairly Difficult (10th–12th grade)',
            $flesch >= 30 => 'Difficult (College)',
            default       => 'Very Difficult (College+)',
        };

        // Passive voice
        $passiveCount = 0;
        foreach ($this->sentences as $s) {
            foreach (self::$PASSIVE_PATTERNS as $pat) {
                if (preg_match($pat, $s)) { $passiveCount++; break; }
            }
        }
        $passivePct = $sentCount > 0 ? round(($passiveCount / $sentCount) * 100) : 0;

        // Transition words
        $transCount = 0;
        foreach ($this->sentences as $s) {
            $lower = mb_strtolower(trim($s));
            foreach (self::$TRANSITION_WORDS as $tw) {
                if (mb_strpos($lower, $tw) === 0) { $transCount++; break; }
            }
        }
        $transPct = $sentCount > 0 ? round(($transCount / $sentCount) * 100) : 0;

        // Long sentences
        $longCount = count(array_filter($this->sentences, fn($s) => str_word_count(trim($s)) > 30));
        $longPct = $sentCount > 0 ? round(($longCount / $sentCount) * 100) : 0;

        $complexPct = $totalWords > 0 ? round(($complexWords / $totalWords) * 100) : 0;

        return [
            'flesch_score'       => $flesch,
            'reading_level'      => $level,
            'avg_sentence_length'=> $avgLen,
            'complex_word_pct'   => $complexPct,
            'passive_pct'        => $passivePct,
            'transition_pct'     => $transPct,
            'long_sentence_pct'  => $longPct,
            'paragraph_count'    => count($this->paragraphs),
            'sentence_count'     => $sentCount,
        ];
    }

    // ——— Utility: Get nearest heading before paragraph index ———
    private function getNearestHeading(int $paraIdx): string {
        if (empty($this->headings)) return 'Introduction';
        // Build a simple ordered heading list from content
        $headingTexts = [];
        foreach ($this->headings as $h) {
            $headingTexts[] = strip_tags($h[2]);
        }
        // Return most likely heading (use paragraph index as rough position indicator)
        $ratio = count($headingTexts) > 0 ? $paraIdx / max(1, count($this->paragraphs)) : 0;
        $hIdx  = min((int)floor($ratio * count($headingTexts)), count($headingTexts) - 1);
        return $headingTexts[$hIdx] ?? 'Introduction';
    }
}
