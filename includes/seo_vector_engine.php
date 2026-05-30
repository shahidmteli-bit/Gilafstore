<?php
/**
 * Gilaf AI SEO Intelligence Engine v3.0 — Vector & Semantic Core
 * 
 * Next-generation semantic intelligence layer:
 * - Qdrant vector database integration
 * - Sentence-transformer embedding engine (via Gemini/HuggingFace)
 * - Embedding cache system with SQLite
 * - Semantic similarity engine
 * - Advanced entity extraction
 * - Knowledge graph relationships
 * - Semantic duplicate detection
 * - Search intent classification
 * - Topical authority graph
 * - AI ranking probability scoring
 * - Conversational search optimization
 * - Featured snippet optimization
 * - Content opportunity engine
 * 
 * @version 3.0.0
 */

// ============================================================
// CONFIGURATION
// ============================================================
class SeoIntelConfig {
    // Qdrant
    public static $qdrantUrl      = 'http://localhost:6333';
    public static $qdrantApiKey   = '';
    public static $collection     = 'gilaf_seo';
    public static $embeddingDim   = 768;
    
    // DataForSEO
    public static $dataforseoLogin    = '';
    public static $dataforseoPassword = '';
    
    // Embedding provider: 'gemini' or 'huggingface'
    public static $embeddingProvider = 'gemini';
    public static $hfApiKey          = '';
    public static $hfModel           = 'sentence-transformers/all-MiniLM-L6-v2';
    
    // Cache
    public static $cacheDir = '';
    public static $cacheTtl = 86400 * 7; // 7 days
    
    // Token tracking
    public static $tokenLogTable = 'seo_api_token_log';
    
    public static function init($db = null) {
        self::$cacheDir = dirname(__DIR__) . '/cache/seo';
        if (!is_dir(self::$cacheDir)) @mkdir(self::$cacheDir, 0755, true);
        
        if ($db) {
            try {
                $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('qdrant_url','qdrant_api_key','dataforseo_login','dataforseo_password','hf_api_key','embedding_provider')")->fetchAll(PDO::FETCH_KEY_PAIR);
                if (!empty($rows['qdrant_url']))          self::$qdrantUrl = $rows['qdrant_url'];
                if (!empty($rows['qdrant_api_key']))      self::$qdrantApiKey = $rows['qdrant_api_key'];
                if (!empty($rows['dataforseo_login']))    self::$dataforseoLogin = $rows['dataforseo_login'];
                if (!empty($rows['dataforseo_password'])) self::$dataforseoPassword = $rows['dataforseo_password'];
                if (!empty($rows['hf_api_key']))          self::$hfApiKey = $rows['hf_api_key'];
                if (!empty($rows['embedding_provider']))  self::$embeddingProvider = $rows['embedding_provider'];
            } catch (Exception $e) {}
        }
    }
}

// ============================================================
// 1. EMBEDDING CACHE ENGINE
// ============================================================
class EmbeddingCache {
    
    private $cacheDb;
    
    public function __construct() {
        $dbPath = SeoIntelConfig::$cacheDir . '/embeddings.sqlite';
        $this->cacheDb = new PDO('sqlite:' . $dbPath);
        $this->cacheDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->cacheDb->exec("CREATE TABLE IF NOT EXISTS embedding_cache (
            hash TEXT PRIMARY KEY,
            text_preview TEXT,
            embedding BLOB,
            dim INTEGER,
            provider TEXT,
            created_at INTEGER,
            hits INTEGER DEFAULT 0
        )");
        $this->cacheDb->exec("CREATE TABLE IF NOT EXISTS token_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider TEXT,
            action TEXT,
            tokens_in INTEGER DEFAULT 0,
            tokens_out INTEGER DEFAULT 0,
            cost_usd REAL DEFAULT 0,
            created_at INTEGER
        )");
    }
    
    public function get(string $text): ?array {
        $hash = md5($text);
        $stmt = $this->cacheDb->prepare("SELECT embedding, dim FROM embedding_cache WHERE hash = ? AND created_at > ?");
        $stmt->execute([$hash, time() - SeoIntelConfig::$cacheTtl]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->cacheDb->prepare("UPDATE embedding_cache SET hits = hits + 1 WHERE hash = ?")->execute([$hash]);
            return unserialize($row['embedding']);
        }
        return null;
    }
    
    public function set(string $text, array $embedding, string $provider = ''): void {
        $hash = md5($text);
        $stmt = $this->cacheDb->prepare("INSERT OR REPLACE INTO embedding_cache (hash, text_preview, embedding, dim, provider, created_at, hits) VALUES (?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$hash, mb_substr($text, 0, 100), serialize($embedding), count($embedding), $provider, time()]);
    }
    
    public function logTokens(string $provider, string $action, int $tokensIn, int $tokensOut = 0, float $cost = 0): void {
        $stmt = $this->cacheDb->prepare("INSERT INTO token_usage (provider, action, tokens_in, tokens_out, cost_usd, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$provider, $action, $tokensIn, $tokensOut, $cost, time()]);
    }
    
    public function getTokenStats(int $days = 30): array {
        $since = time() - ($days * 86400);
        $stmt = $this->cacheDb->prepare("SELECT provider, action, SUM(tokens_in) as total_in, SUM(tokens_out) as total_out, SUM(cost_usd) as total_cost, COUNT(*) as calls FROM token_usage WHERE created_at > ? GROUP BY provider, action ORDER BY total_cost DESC");
        $stmt->execute([$since]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCacheStats(): array {
        $total = (int)$this->cacheDb->query("SELECT COUNT(*) FROM embedding_cache")->fetchColumn();
        $recent = (int)$this->cacheDb->query("SELECT COUNT(*) FROM embedding_cache WHERE created_at > " . (time() - 86400))->fetchColumn();
        $totalHits = (int)$this->cacheDb->query("SELECT SUM(hits) FROM embedding_cache")->fetchColumn();
        return ['total_cached' => $total, 'added_today' => $recent, 'total_hits' => $totalHits];
    }
    
    public function pruneOld(): int {
        $cutoff = time() - SeoIntelConfig::$cacheTtl;
        $this->cacheDb->exec("DELETE FROM embedding_cache WHERE created_at < {$cutoff}");
        return $this->cacheDb->query("SELECT changes()")->fetchColumn();
    }
}

// ============================================================
// 2. EMBEDDING ENGINE (Gemini / HuggingFace)
// ============================================================
class EmbeddingEngine {
    
    private $cache;
    private $geminiKey;
    
    public function __construct(string $geminiKey = '') {
        $this->cache = new EmbeddingCache();
        $this->geminiKey = $geminiKey;
    }
    
    public function embed(string $text): ?array {
        $text = trim($text);
        if (empty($text)) return null;
        
        // Check cache first
        $cached = $this->cache->get($text);
        if ($cached) return $cached;
        
        $embedding = null;
        $provider = SeoIntelConfig::$embeddingProvider;
        
        if ($provider === 'gemini' && $this->geminiKey) {
            $embedding = $this->embedViaGemini($text);
        } elseif ($provider === 'huggingface' && SeoIntelConfig::$hfApiKey) {
            $embedding = $this->embedViaHuggingFace($text);
        } else {
            // Fallback: use Gemini if key available
            if ($this->geminiKey) {
                $embedding = $this->embedViaGemini($text);
                $provider = 'gemini';
            }
        }
        
        if ($embedding) {
            $this->cache->set($text, $embedding, $provider);
        }
        
        return $embedding;
    }
    
    public function embedBatch(array $texts): array {
        $results = [];
        foreach ($texts as $i => $text) {
            $results[$i] = $this->embed($text);
        }
        return $results;
    }
    
    private function embedViaGemini(string $text): ?array {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key=" . $this->geminiKey;
        
        $payload = [
            'model' => 'models/text-embedding-004',
            'content' => ['parts' => [['text' => mb_substr($text, 0, 2048)]]]
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code !== 200) return null;
        
        $data = json_decode($resp, true);
        $embedding = $data['embedding']['values'] ?? null;
        
        if ($embedding) {
            $tokenCount = (int)ceil(mb_strlen($text) / 4);
            $this->cache->logTokens('gemini', 'embedding', $tokenCount, 0, $tokenCount * 0.000001);
        }
        
        return $embedding;
    }
    
    private function embedViaHuggingFace(string $text): ?array {
        $url = "https://api-inference.huggingface.co/pipeline/feature-extraction/" . SeoIntelConfig::$hfModel;
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . SeoIntelConfig::$hfApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode(['inputs' => mb_substr($text, 0, 512)]),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code !== 200) return null;
        
        $data = json_decode($resp, true);
        // HF returns [[...embedding...]] for single input
        $embedding = is_array($data) && is_array($data[0]) ? $data[0] : null;
        
        if ($embedding) {
            $this->cache->logTokens('huggingface', 'embedding', (int)ceil(mb_strlen($text) / 4));
        }
        
        return $embedding;
    }
    
    public function cosineSimilarity(array $a, array $b): float {
        $dot = 0; $normA = 0; $normB = 0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        $denom = sqrt($normA) * sqrt($normB);
        return $denom > 0 ? round($dot / $denom, 6) : 0;
    }
    
    public function getCache(): EmbeddingCache { return $this->cache; }
}

// ============================================================
// 3. QDRANT VECTOR DATABASE CLIENT
// ============================================================
class QdrantClient {
    
    private $baseUrl;
    private $apiKey;
    private $collection;
    
    public function __construct() {
        $this->baseUrl    = rtrim(SeoIntelConfig::$qdrantUrl, '/');
        $this->apiKey     = SeoIntelConfig::$qdrantApiKey;
        $this->collection = SeoIntelConfig::$collection;
    }
    
    public function isAvailable(): bool {
        $resp = $this->request('GET', '/collections');
        return $resp !== null;
    }
    
    public function ensureCollection(): bool {
        $resp = $this->request('GET', "/collections/{$this->collection}");
        if ($resp && isset($resp['result'])) return true;
        
        $payload = [
            'vectors' => [
                'size' => SeoIntelConfig::$embeddingDim,
                'distance' => 'Cosine'
            ]
        ];
        $resp = $this->request('PUT', "/collections/{$this->collection}", $payload);
        return $resp && ($resp['status'] ?? '') === 'ok';
    }
    
    public function upsertPoint(string $id, array $vector, array $payload = []): bool {
        $data = [
            'points' => [[
                'id' => $this->hashId($id),
                'vector' => $vector,
                'payload' => array_merge($payload, ['_original_id' => $id])
            ]]
        ];
        $resp = $this->request('PUT', "/collections/{$this->collection}/points", $data);
        return $resp && ($resp['status'] ?? '') === 'ok';
    }
    
    public function upsertBatch(array $points): bool {
        $formatted = [];
        foreach ($points as $p) {
            $formatted[] = [
                'id' => $this->hashId($p['id']),
                'vector' => $p['vector'],
                'payload' => array_merge($p['payload'] ?? [], ['_original_id' => $p['id']])
            ];
        }
        $resp = $this->request('PUT', "/collections/{$this->collection}/points", ['points' => $formatted]);
        return $resp && ($resp['status'] ?? '') === 'ok';
    }
    
    public function search(array $vector, int $limit = 10, array $filter = null): array {
        $payload = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'with_vectors' => false
        ];
        if ($filter) $payload['filter'] = $filter;
        
        $resp = $this->request('POST', "/collections/{$this->collection}/points/search", $payload);
        return $resp['result'] ?? [];
    }
    
    public function deleteByFilter(array $filter): bool {
        $resp = $this->request('POST', "/collections/{$this->collection}/points/delete", ['filter' => $filter]);
        return $resp && ($resp['status'] ?? '') === 'ok';
    }
    
    public function getCollectionInfo(): ?array {
        $resp = $this->request('GET', "/collections/{$this->collection}");
        return $resp['result'] ?? null;
    }
    
    private function hashId(string $id): int {
        return abs(crc32($id)) & 0x7FFFFFFF;
    }
    
    private function request(string $method, string $path, array $body = null): ?array {
        $url = $this->baseUrl . $path;
        $ch = curl_init($url);
        
        $headers = ['Content-Type: application/json'];
        if ($this->apiKey) $headers[] = 'api-key: ' . $this->apiKey;
        
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ];
        
        switch ($method) {
            case 'PUT':
                $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
                if ($body) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
            case 'POST':
                $opts[CURLOPT_POST] = true;
                if ($body) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
            case 'DELETE':
                $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                if ($body) $opts[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
        }
        
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code >= 200 && $code < 300 && $resp) {
            return json_decode($resp, true);
        }
        return null;
    }
}

// ============================================================
// 4. SEMANTIC SIMILARITY ENGINE
// ============================================================
class SemanticSimilarityEngine {
    
    private $embedder;
    private $qdrant;
    
    public function __construct(EmbeddingEngine $embedder) {
        $this->embedder = $embedder;
        $this->qdrant = new QdrantClient();
    }
    
    /**
     * Index a piece of content into the vector database
     */
    public function indexContent(string $id, string $text, string $type, array $meta = []): bool {
        $embedding = $this->embedder->embed($text);
        if (!$embedding) return false;
        
        $payload = array_merge($meta, [
            'type' => $type,
            'text_preview' => mb_substr($text, 0, 200),
            'indexed_at' => time()
        ]);
        
        if ($this->qdrant->isAvailable()) {
            $this->qdrant->ensureCollection();
            return $this->qdrant->upsertPoint($id, $embedding, $payload);
        }
        
        return true; // Still cached embedding even without Qdrant
    }
    
    /**
     * Find semantically similar content
     */
    public function findSimilar(string $text, int $limit = 10, string $typeFilter = null): array {
        $embedding = $this->embedder->embed($text);
        if (!$embedding) return [];
        
        if ($this->qdrant->isAvailable()) {
            $filter = $typeFilter ? ['must' => [['key' => 'type', 'match' => ['value' => $typeFilter]]]] : null;
            return $this->qdrant->search($embedding, $limit, $filter);
        }
        
        return []; // No Qdrant — would need local similarity
    }
    
    /**
     * Calculate pairwise similarity between two texts
     */
    public function similarity(string $textA, string $textB): float {
        $embA = $this->embedder->embed($textA);
        $embB = $this->embedder->embed($textB);
        if (!$embA || !$embB) return 0;
        return $this->embedder->cosineSimilarity($embA, $embB);
    }
    
    /**
     * Detect semantic duplicates across all content
     */
    public function detectDuplicates(array $contents, float $threshold = 0.85): array {
        $embeddings = [];
        foreach ($contents as $i => $item) {
            $emb = $this->embedder->embed($item['text'] ?? '');
            if ($emb) $embeddings[$i] = $emb;
        }
        
        $duplicates = [];
        $keys = array_keys($embeddings);
        for ($i = 0; $i < count($keys); $i++) {
            for ($j = $i + 1; $j < count($keys); $j++) {
                $sim = $this->embedder->cosineSimilarity($embeddings[$keys[$i]], $embeddings[$keys[$j]]);
                if ($sim >= $threshold) {
                    $duplicates[] = [
                        'content_a' => $contents[$keys[$i]],
                        'content_b' => $contents[$keys[$j]],
                        'similarity' => round($sim, 4),
                        'is_near_duplicate' => $sim >= 0.92
                    ];
                }
            }
        }
        
        usort($duplicates, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        return $duplicates;
    }
}

// ============================================================
// 5. ADVANCED ENTITY EXTRACTION ENGINE
// ============================================================
class EntityExtractionEngine {
    
    private $geminiKey;
    private $cache;
    
    public function __construct(string $geminiKey) {
        $this->geminiKey = $geminiKey;
        $this->cache = new EmbeddingCache();
    }
    
    /**
     * Extract entities using Gemini AI
     */
    public function extractEntities(string $content, string $keyword = ''): ?array {
        $cacheKey = 'entities_' . md5($content . $keyword);
        $cached = $this->getJsonCache($cacheKey);
        if ($cached) return $cached;
        
        $prompt = "Extract all named entities, semantic concepts, and topical entities from this content about \"{$keyword}\".

Content (first 3000 chars):
" . mb_substr(strip_tags($content), 0, 3000) . "

Return JSON:
{
  \"entities\": [
    {\"name\": \"entity name\", \"type\": \"person|organization|product|concept|place|attribute|process\", \"salience\": 0.0-1.0, \"mentions\": 1}
  ],
  \"topics\": [\"main topic 1\", \"topic 2\"],
  \"concepts\": [\"abstract concept 1\", \"concept 2\"],
  \"relationships\": [
    {\"subject\": \"entity A\", \"predicate\": \"is_type_of|has_property|is_part_of|related_to|causes|used_for\", \"object\": \"entity B\"}
  ]
}";
        
        $result = $this->callGemini($prompt, 0.2);
        if ($result) {
            $parsed = json_decode($result, true);
            if ($parsed) {
                $this->setJsonCache($cacheKey, $parsed);
                return $parsed;
            }
        }
        return null;
    }
    
    /**
     * Build knowledge graph relationships from content
     */
    public function buildKnowledgeGraph(array $entities): array {
        $nodes = [];
        $edges = [];
        
        foreach ($entities['entities'] ?? [] as $e) {
            $nodes[$e['name']] = [
                'id' => md5($e['name']),
                'label' => $e['name'],
                'type' => $e['type'] ?? 'concept',
                'salience' => $e['salience'] ?? 0.5,
                'size' => max(8, round(($e['salience'] ?? 0.5) * 30))
            ];
        }
        
        foreach ($entities['relationships'] ?? [] as $rel) {
            if (isset($nodes[$rel['subject']]) && isset($nodes[$rel['object']])) {
                $edges[] = [
                    'source' => md5($rel['subject']),
                    'target' => md5($rel['object']),
                    'label' => $rel['predicate'] ?? 'related_to',
                    'source_name' => $rel['subject'],
                    'target_name' => $rel['object']
                ];
            }
        }
        
        return ['nodes' => array_values($nodes), 'edges' => $edges];
    }
    
    private function callGemini(string $prompt, float $temp = 0.3): ?string {
        if (!$this->geminiKey) return null;
        $key = $this->geminiKey;
        if (strpos($key, 'sk-ant-') === 0) {
            $url = 'https://api.anthropic.com/v1/messages';
            $payload = ['model' => 'claude-3-haiku-20240307', 'max_tokens' => 4096, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
        } elseif (strpos($key, 'sk-') === 0) {
            $url = 'https://api.openai.com/v1/chat/completions';
            $payload = ['model' => 'gpt-4o-mini', 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => $temp, 'max_tokens' => 4096, 'response_format' => ['type' => 'json_object']];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $key];
        } else {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $key;
            $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => $temp, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']];
            $headers = ['Content-Type: application/json'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        if (strpos($key, 'sk-ant-') === 0) return $data['content'][0]['text'] ?? null;
        if (strpos($key, 'sk-') === 0) return $data['choices'][0]['message']['content'] ?? null;
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text) $this->cache->logTokens('gemini', 'entity_extraction', (int)ceil(mb_strlen($prompt)/4), (int)ceil(mb_strlen($text)/4), 0.0000005);
        return $text;
    }
    
    private function getJsonCache(string $key): ?array {
        $file = SeoIntelConfig::$cacheDir . '/' . $key . '.json';
        if (file_exists($file) && filemtime($file) > time() - SeoIntelConfig::$cacheTtl) {
            $data = json_decode(file_get_contents($file), true);
            return $data ?: null;
        }
        return null;
    }
    
    private function setJsonCache(string $key, array $data): void {
        $file = SeoIntelConfig::$cacheDir . '/' . $key . '.json';
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// ============================================================
// 6. SEARCH INTENT CLASSIFICATION ENGINE
// ============================================================
class SearchIntentClassifier {
    
    private $geminiKey;
    
    public function __construct(string $geminiKey) {
        $this->geminiKey = $geminiKey;
    }
    
    /**
     * Classify search intent of a keyword
     */
    public function classify(string $keyword): array {
        // Rule-based first pass
        $kwLower = mb_strtolower($keyword);
        $intent = $this->ruleBasedClassify($kwLower);
        
        return $intent;
    }
    
    /**
     * AI-powered deep intent analysis
     */
    public function deepClassify(string $keyword): ?array {
        $prompt = "Analyze the search intent for: \"{$keyword}\"

Return JSON:
{
  \"primary_intent\": \"informational|navigational|transactional|commercial_investigation\",
  \"confidence\": 0.0-1.0,
  \"secondary_intents\": [\"intent\"],
  \"user_stage\": \"awareness|consideration|decision|retention\",
  \"content_format\": \"guide|listicle|comparison|review|tutorial|product_page|faq\",
  \"serp_features\": [\"featured_snippet|people_also_ask|knowledge_panel|shopping|video|images\"],
  \"optimal_word_count\": 1500,
  \"optimal_headings\": 8,
  \"ai_overview_likely\": true,
  \"voice_search_likely\": false,
  \"conversion_potential\": 0.0-1.0
}";
        
        $result = $this->callGemini($prompt);
        return $result ? json_decode($result, true) : null;
    }
    
    private function ruleBasedClassify(string $kw): array {
        $transactionalWords = ['buy', 'price', 'cost', 'cheap', 'discount', 'deal', 'order', 'purchase', 'shop', 'coupon', 'sale', 'offer'];
        $informationalWords = ['how', 'what', 'why', 'when', 'where', 'who', 'guide', 'tutorial', 'learn', 'tips', 'benefits', 'vs', 'difference', 'best way'];
        $commercialWords = ['best', 'top', 'review', 'compare', 'comparison', 'alternative', 'vs', 'which', 'recommend'];
        $navigationalWords = ['login', 'sign in', 'official', 'website', 'contact', 'support', 'app'];
        
        $scores = ['informational' => 0, 'transactional' => 0, 'commercial_investigation' => 0, 'navigational' => 0];
        
        foreach ($transactionalWords as $w) { if (strpos($kw, $w) !== false) $scores['transactional'] += 2; }
        foreach ($informationalWords as $w) { if (strpos($kw, $w) !== false) $scores['informational'] += 2; }
        foreach ($commercialWords as $w) { if (strpos($kw, $w) !== false) $scores['commercial_investigation'] += 2; }
        foreach ($navigationalWords as $w) { if (strpos($kw, $w) !== false) $scores['navigational'] += 2; }
        
        // Default to informational if no match
        if (array_sum($scores) === 0) $scores['informational'] = 1;
        
        arsort($scores);
        $primary = array_key_first($scores);
        $total = max(1, array_sum($scores));
        
        return [
            'primary_intent' => $primary,
            'confidence' => round($scores[$primary] / $total, 2),
            'scores' => $scores
        ];
    }
    
    private function callGemini(string $prompt): ?string {
        if (!$this->geminiKey) return null;
        $key = $this->geminiKey;
        if (strpos($key, 'sk-ant-') === 0) {
            $url = 'https://api.anthropic.com/v1/messages';
            $payload = ['model' => 'claude-3-haiku-20240307', 'max_tokens' => 2048, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2023-06-01'];
        } elseif (strpos($key, 'sk-') === 0) {
            $url = 'https://api.openai.com/v1/chat/completions';
            $payload = ['model' => 'gpt-4o-mini', 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.2, 'max_tokens' => 2048, 'response_format' => ['type' => 'json_object']];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $key];
        } else {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $key;
            $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 2048, 'responseMimeType' => 'application/json']];
            $headers = ['Content-Type: application/json'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        if (strpos($key, 'sk-ant-') === 0) return $data['content'][0]['text'] ?? null;
        if (strpos($key, 'sk-') === 0) return $data['choices'][0]['message']['content'] ?? null;
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}

// ============================================================
// 7. TOPICAL AUTHORITY GRAPH ENGINE
// ============================================================
class TopicalAuthorityEngine {
    
    private $db;
    private $embedder;
    private $geminiKey;
    
    public function __construct($db, EmbeddingEngine $embedder, string $geminiKey) {
        $this->db = $db;
        $this->embedder = $embedder;
        $this->geminiKey = $geminiKey;
    }
    
    /**
     * Calculate topical authority score for a topic
     * Uses keyword + content matching as primary, embeddings as enhancement
     */
    public function calculateAuthority(string $topic): array {
        $blogs = $this->db->query("SELECT id, title, slug, content, meta_keywords, views, status FROM blogs WHERE status='published'")->fetchAll(PDO::FETCH_ASSOC);
        
        $topicLower = mb_strtolower(trim($topic));
        $topicWords = array_filter(explode(' ', $topicLower), fn($w) => mb_strlen($w) >= 3);
        
        // Phase 1: Keyword + content matching (always works, no API needed)
        $relevant = [];
        foreach ($blogs as $blog) {
            $titleLower = mb_strtolower($blog['title']);
            $contentLower = mb_strtolower(strip_tags($blog['content'] ?? ''));
            $keywordsLower = mb_strtolower($blog['meta_keywords'] ?? '');
            $wordCount = str_word_count($contentLower);
            
            $score = 0;
            // Title contains topic
            if (mb_strpos($titleLower, $topicLower) !== false) $score += 0.6;
            // Keywords contain topic
            if (mb_strpos($keywordsLower, $topicLower) !== false) $score += 0.4;
            // Content mentions topic
            $topicMentions = mb_substr_count($contentLower, $topicLower);
            if ($topicMentions > 0) $score += min(0.3, $topicMentions * 0.05);
            // Individual topic words in title/keywords/content
            $wordHits = 0;
            foreach ($topicWords as $tw) {
                if (mb_strpos($titleLower, $tw) !== false) $wordHits += 3;
                if (mb_strpos($keywordsLower, $tw) !== false) $wordHits += 2;
                if (mb_strpos($contentLower, $tw) !== false) $wordHits += 1;
            }
            $totalPossible = count($topicWords) * 6;
            if ($totalPossible > 0) $score += ($wordHits / $totalPossible) * 0.5;
            
            if ($score >= 0.15) {
                $relevant[] = [
                    'id' => $blog['id'],
                    'title' => $blog['title'],
                    'slug' => $blog['slug'],
                    'relevance' => round(min(1.0, $score), 4),
                    'word_count' => $wordCount,
                    'views' => $blog['views'] ?? 0,
                    'method' => 'keyword'
                ];
            }
        }
        
        // Phase 2: Enhance with embeddings if available (optional)
        $topicEmb = null;
        try { $topicEmb = $this->embedder->embed($topic); } catch (\Exception $e) {}
        
        if ($topicEmb) {
            $existingIds = array_column($relevant, 'id');
            foreach ($blogs as $blog) {
                if (in_array($blog['id'], $existingIds)) continue;
                $blogText = $blog['title'] . ' ' . mb_substr(strip_tags($blog['content'] ?? ''), 0, 500);
                try {
                    $blogEmb = $this->embedder->embed($blogText);
                    if ($blogEmb) {
                        $sim = $this->embedder->cosineSimilarity($topicEmb, $blogEmb);
                        if ($sim > 0.4) {
                            $relevant[] = [
                                'id' => $blog['id'],
                                'title' => $blog['title'],
                                'slug' => $blog['slug'],
                                'relevance' => round($sim, 4),
                                'word_count' => str_word_count(strip_tags($blog['content'] ?? '')),
                                'views' => $blog['views'] ?? 0,
                                'method' => 'embedding'
                            ];
                        }
                    }
                } catch (\Exception $e) { break; }
            }
        }
        
        usort($relevant, fn($a, $b) => $b['relevance'] <=> $a['relevance']);
        
        // Authority score = coverage * depth
        $coverageScore = min(100, count($relevant) * 15);
        $depthScore = 0;
        foreach ($relevant as $r) {
            $depthScore += min(20, $r['word_count'] / 100);
        }
        $depthScore = min(100, round($depthScore));
        
        $authorityScore = round(($coverageScore * 0.5 + $depthScore * 0.5));
        
        return [
            'score' => $authorityScore,
            'topic' => $topic,
            'coverage_count' => count($relevant),
            'coverage' => array_slice($relevant, 0, 15),
            'coverage_score' => $coverageScore,
            'depth_score' => $depthScore
        ];
    }
    
    /**
     * Find content gaps for a topic
     * Uses AI when available, falls back to keyword analysis
     */
    public function findContentGaps(string $topic): ?array {
        $authority = $this->calculateAuthority($topic);
        $existingTitles = array_column($authority['coverage'], 'title');
        
        // Try AI-powered gap analysis
        $gaps = null;
        if ($this->geminiKey) {
            $prompt = "Given this topic: \"{$topic}\" for a Kashmir products e-commerce store.

Existing articles: " . implode(', ', array_slice($existingTitles, 0, 10)) . "

Identify content gaps — what articles are MISSING to build complete topical authority.

Return JSON:
{
  \"gaps\": [
    {\"title\": \"Missing Article Title\", \"keyword\": \"target keyword\", \"priority\": \"high|medium|low\", \"type\": \"pillar|cluster|support\", \"estimated_traffic\": \"high|medium|low\", \"score\": 85}
  ],
  \"pillar_recommendations\": [\"pillar topic 1\"],
  \"authority_improvement\": \"description of how to improve authority\"
}";
            $result = $this->callAI($prompt);
            if ($result) $gaps = json_decode($result, true);
        }
        
        // Fallback: keyword-based gap analysis
        if (!$gaps) {
            $topicLower = mb_strtolower(trim($topic));
            $topicWords = array_filter(explode(' ', $topicLower), fn($w) => mb_strlen($w) >= 3);
            
            $contentTypes = ['pillar', 'cluster', 'support', 'comparison', 'guide'];
            $gapSuggestions = [];
            $templates = [
                ['title' => 'The Ultimate Guide to %s', 'type' => 'pillar', 'priority' => 'high', 'score' => 92],
                ['title' => '%s: Benefits, Uses & Everything You Need to Know', 'type' => 'guide', 'priority' => 'high', 'score' => 88],
                ['title' => 'How to Choose the Best %s', 'type' => 'cluster', 'priority' => 'high', 'score' => 85],
                ['title' => '%s vs Other Alternatives: A Detailed Comparison', 'type' => 'comparison', 'priority' => 'medium', 'score' => 78],
                ['title' => 'Top 10 Uses of %s You Didn\'t Know', 'type' => 'cluster', 'priority' => 'medium', 'score' => 75],
                ['title' => 'Where to Buy Authentic %s Online in India', 'type' => 'support', 'priority' => 'medium', 'score' => 72],
                ['title' => '%s: Health Benefits Backed by Science', 'type' => 'cluster', 'priority' => 'medium', 'score' => 70],
                ['title' => 'How to Store %s for Maximum Freshness', 'type' => 'support', 'priority' => 'low', 'score' => 65],
            ];
            
            $ucTopic = ucwords($topic);
            foreach ($templates as $tpl) {
                $title = sprintf($tpl['title'], $ucTopic);
                // Skip if we already have a similar article
                $exists = false;
                foreach ($existingTitles as $et) {
                    if (similar_text(mb_strtolower($et), mb_strtolower($title), $pct) && $pct > 50) { $exists = true; break; }
                }
                if (!$exists) {
                    $gapSuggestions[] = [
                        'title' => $title,
                        'keyword' => mb_strtolower($ucTopic) . ' ' . $tpl['type'],
                        'priority' => $tpl['priority'],
                        'type' => $tpl['type'],
                        'estimated_traffic' => $tpl['priority'] === 'high' ? 'high' : 'medium',
                        'score' => $tpl['score'],
                    ];
                }
            }
            
            $gaps = [
                'gaps' => $gapSuggestions,
                'pillar_recommendations' => [
                    'Complete Guide to ' . $ucTopic,
                    $ucTopic . ' FAQ & Buying Guide',
                ],
                'authority_improvement' => 'Create a pillar article about "' . $ucTopic . '" and link all related cluster articles to it. Cover benefits, uses, buying guide, comparisons, and storage tips to build full topical authority.',
                'method' => 'keyword_fallback'
            ];
        }
        
        $gaps['current_authority'] = $authority['score'];
        $gaps['existing_count'] = count($existingTitles);
        
        return $gaps;
    }
    
    private function callAI(string $prompt): ?string {
        if (!$this->geminiKey) return null;
        
        // Auto-detect provider
        $provider = 'gemini';
        if (strpos($this->geminiKey, 'sk-ant-') === 0) $provider = 'claude';
        elseif (strpos($this->geminiKey, 'sk-') === 0) $provider = 'openai';
        
        try {
            $rows = $this->db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','ai_model')")->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (!empty($rows['ai_provider'])) $provider = $rows['ai_provider'];
            $model = $rows['ai_model'] ?? '';
        } catch (\Exception $e) { $model = ''; }
        
        if ($provider === 'openai') {
            if (!$model) $model = 'gpt-4o-mini';
            $url = "https://api.openai.com/v1/chat/completions";
            $payload = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.4, 'max_tokens' => 4096, 'response_format' => ['type' => 'json_object']];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->geminiKey];
        } elseif ($provider === 'claude') {
            if (!$model) $model = 'claude-3-haiku-20240307';
            $url = "https://api.anthropic.com/v1/messages";
            $payload = ['model' => $model, 'max_tokens' => 4096, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->geminiKey, 'anthropic-version: 2023-06-01'];
        } else {
            if (!$model) $model = 'gemini-2.0-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->geminiKey;
            $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']];
            $headers = ['Content-Type: application/json'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        
        if ($provider === 'openai') return $data['choices'][0]['message']['content'] ?? null;
        if ($provider === 'claude') return $data['content'][0]['text'] ?? null;
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}

// ============================================================
// 8. AI SEARCH & FEATURED SNIPPET OPTIMIZER
// ============================================================
class AiSearchOptimizer {
    
    private $apiKey;
    private $db;
    
    public function __construct(string $apiKey, $db = null) {
        $this->apiKey = $apiKey;
        $this->db = $db;
    }
    
    private function cleanJsonResponse(?string $text): ?array {
        if (!$text) return null;
        $text = trim($text);
        // Strip markdown code fences
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) $text = trim($m[1]);
        // Strip leading/trailing non-JSON chars
        $text = preg_replace('/^[^{\[]+/', '', $text);
        $text = preg_replace('/[^}\]]+$/', '', $text);
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }
    
    /**
     * Optimize content for AI Overview / Featured Snippets / Voice Search
     */
    public function optimizeForAiSearch(string $content, string $keyword): ?array {
        $prompt = "Analyze this content for optimization across Google AI Overview, ChatGPT Search, Perplexity, Featured Snippets, and Voice Search.

Keyword: \"{$keyword}\"
Content (first 2500 chars): " . mb_substr(strip_tags($content), 0, 2500) . "

Return JSON:
{
  \"ai_overview_score\": 0-100,
  \"featured_snippet_score\": 0-100,
  \"voice_search_score\": 0-100,
  \"conversational_score\": 0-100,
  \"ai_overview_issues\": [\"issue 1\"],
  \"ai_overview_fixes\": [\"fix 1\"],
  \"snippet_type_eligible\": [\"paragraph|list|table|video\"],
  \"snippet_optimization\": [\"suggestion 1\"],
  \"voice_search_issues\": [\"issue 1\"],
  \"conversational_improvements\": [\"improvement 1\"],
  \"direct_answer_present\": true,
  \"question_answer_pairs\": [{\"q\": \"question\", \"a\": \"concise answer\"}],
  \"recommended_additions\": [\"Add a definition paragraph for...\"]
}";
        
        $result = $this->callAI($prompt);
        return $this->cleanJsonResponse($result);
    }
    
    /**
     * Generate AI-optimized content snippets
     */
    public function generateSnippets(string $keyword, string $existingContent = ''): ?array {
        $prompt = "Generate optimized content snippets for the keyword \"{$keyword}\" that are designed to be extracted by AI search engines and featured in Google's AI Overview.

" . ($existingContent ? "Existing content (first 1000 chars): " . mb_substr(strip_tags($existingContent), 0, 1000) : "") . "

Return JSON:
{
  \"definition_snippet\": \"A concise 2-sentence definition...\",
  \"list_snippet\": [\"item 1\", \"item 2\", \"item 3\"],
  \"table_data\": {\"headers\": [\"col1\",\"col2\"], \"rows\": [[\"val1\",\"val2\"]]},
  \"faq_snippets\": [{\"question\": \"Q\", \"answer\": \"concise A\"}],
  \"how_to_steps\": [\"Step 1\", \"Step 2\"],
  \"comparison_snippet\": \"Brief comparison...\",
  \"expert_quote\": \"An authoritative statement...\"
}";
        
        $result = $this->callAI($prompt, 0.5);
        return $this->cleanJsonResponse($result);
    }
    
    private function callAI(string $prompt, float $temp = 0.3): ?string {
        if (!$this->apiKey) return null;
        
        $provider = 'gemini';
        if (strpos($this->apiKey, 'sk-ant-') === 0) $provider = 'claude';
        elseif (strpos($this->apiKey, 'sk-') === 0) $provider = 'openai';
        
        if ($this->db) {
            try {
                $rows = $this->db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','ai_model')")->fetchAll(\PDO::FETCH_KEY_PAIR);
                if (!empty($rows['ai_provider'])) $provider = $rows['ai_provider'];
                $model = $rows['ai_model'] ?? '';
            } catch (\Exception $e) { $model = ''; }
        } else { $model = ''; }
        
        if ($provider === 'openai') {
            if (!$model) $model = 'gpt-4o-mini';
            $url = "https://api.openai.com/v1/chat/completions";
            $payload = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => $temp, 'max_tokens' => 4096, 'response_format' => ['type' => 'json_object']];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        } elseif ($provider === 'claude') {
            if (!$model) $model = 'claude-3-haiku-20240307';
            $url = "https://api.anthropic.com/v1/messages";
            $payload = ['model' => $model, 'max_tokens' => 4096, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->apiKey, 'anthropic-version: 2023-06-01'];
        } else {
            if (!$model) $model = 'gemini-2.0-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;
            $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => $temp, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']];
            $headers = ['Content-Type: application/json'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        
        if ($provider === 'openai') return $data['choices'][0]['message']['content'] ?? null;
        if ($provider === 'claude') return $data['content'][0]['text'] ?? null;
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}

// ============================================================
// 9. DATAFORSEO SERP INTELLIGENCE ENGINE
// ============================================================
class DataForSeoEngine {
    
    private $login;
    private $password;
    private $cache;
    public $lastError = '';
    
    public function __construct($db = null) {
        $this->login    = SeoIntelConfig::$dataforseoLogin;
        $this->password = SeoIntelConfig::$dataforseoPassword;
        $this->cache    = new EmbeddingCache();
        
        // Fallback: read directly from DB if SeoIntelConfig has empty values
        if ((empty($this->login) || empty($this->password)) && $db) {
            try {
                $rows = $db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('dataforseo_login','dataforseo_password')")->fetchAll(\PDO::FETCH_KEY_PAIR);
                if (!empty($rows['dataforseo_login']))    $this->login    = $rows['dataforseo_login'];
                if (!empty($rows['dataforseo_password'])) $this->password = $rows['dataforseo_password'];
            } catch (\Exception $e) {}
        }
    }
    
    public function isConfigured(): bool {
        return !empty($this->login) && !empty($this->password);
    }
    
    /**
     * Get SERP results for a keyword
     */
    public function getSerpResults(string $keyword, string $location = 'India', string $language = 'en'): ?array {
        $cacheKey = 'serp_' . md5($keyword . $location);
        $cached = $this->getJsonCache($cacheKey, 86400); // 1 day cache
        if ($cached) return $cached;
        
        $payload = [[
            'keyword' => $keyword,
            'location_name' => $location,
            'language_name' => $language,
            'device' => 'desktop',
            'os' => 'windows',
            'depth' => 10
        ]];
        
        $result = $this->apiCall('/v3/serp/google/organic/live/advanced', $payload);
        
        if ($result && !empty($result['tasks'][0]['result'][0])) {
            $data = $result['tasks'][0]['result'][0];
            $this->setJsonCache($cacheKey, $data);
            $this->cache->logTokens('dataforseo', 'serp_live', 0, 0, 0.003);
            return $data;
        }
        // Set error if apiCall succeeded but results are empty
        if ($result && empty($this->lastError)) {
            $taskMsg = $result['tasks'][0]['status_message'] ?? '';
            $taskCode = $result['tasks'][0]['status_code'] ?? 0;
            $this->lastError = $taskMsg ? "Task: {$taskMsg} (code {$taskCode})" : 'API returned empty results (no SERP data for this keyword/location)';
        }
        return null;
    }
    
    /**
     * Get keyword data (volume, difficulty, CPC)
     */
    public function getKeywordData(string $keyword, string $location = 'India'): ?array {
        $cacheKey = 'kwdata_' . md5($keyword . $location);
        $cached = $this->getJsonCache($cacheKey, 86400 * 7);
        if ($cached) return $cached;
        
        $payload = [[
            'keywords' => [$keyword],
            'location_name' => $location,
            'language_name' => 'English',
            'date_from' => date('Y-m-01', strtotime('-3 months'))
        ]];
        
        $result = $this->apiCall('/v3/keywords_data/google_ads/search_volume/live', $payload);
        
        if ($result && !empty($result['tasks'][0]['result'])) {
            $data = $result['tasks'][0]['result'];
            $this->setJsonCache($cacheKey, $data);
            $this->cache->logTokens('dataforseo', 'keyword_data', 0, 0, 0.05);
            return $data;
        }
        return null;
    }
    
    /**
     * Analyze competitor SERP content
     */
    public function analyzeCompetitors(string $keyword): array {
        $serp = $this->getSerpResults($keyword);
        if (!$serp) return [];
        
        $competitors = [];
        foreach ($serp['items'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'organic') continue;
            $competitors[] = [
                'position' => $item['rank_group'] ?? 0,
                'title' => $item['title'] ?? '',
                'url' => $item['url'] ?? '',
                'domain' => $item['domain'] ?? '',
                'description' => $item['description'] ?? '',
                'breadcrumb' => $item['breadcrumb'] ?? '',
                'is_featured' => ($item['is_featured_snippet'] ?? false),
                'word_count' => isset($item['extra']['word_count']) ? $item['extra']['word_count'] : null,
            ];
        }
        
        return [
            'keyword' => $keyword,
            'total_results' => $serp['se_results_count'] ?? 0,
            'serp_features' => $this->extractSerpFeatures($serp),
            'competitors' => $competitors,
            'difficulty_estimate' => $this->estimateDifficulty($competitors)
        ];
    }
    
    private function extractSerpFeatures(array $serp): array {
        $features = [];
        foreach ($serp['items'] ?? [] as $item) {
            $type = $item['type'] ?? 'organic';
            if (!in_array($type, $features)) $features[] = $type;
        }
        return $features;
    }
    
    private function estimateDifficulty(array $competitors): string {
        $highAuthority = 0;
        foreach ($competitors as $c) {
            $domain = $c['domain'] ?? '';
            if (preg_match('/(wikipedia|amazon|flipkart|healthline|webmd|forbes|nytimes)/i', $domain)) $highAuthority++;
        }
        if ($highAuthority >= 5) return 'very_hard';
        if ($highAuthority >= 3) return 'hard';
        if ($highAuthority >= 1) return 'medium';
        return 'easy';
    }
    
    private function apiCall(string $endpoint, array $payload): ?array {
        if (!$this->isConfigured()) { $this->lastError = 'Not configured'; return null; }
        
        $url = 'https://api.dataforseo.com' . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $this->login . ':' . $this->password,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        
        if ($curlErr) { $this->lastError = 'cURL error: ' . $curlErr; return null; }
        if (!$resp) { $this->lastError = 'Empty response (HTTP ' . $code . ')'; return null; }
        
        $decoded = json_decode($resp, true);
        if (!$decoded) { $this->lastError = 'Invalid JSON (HTTP ' . $code . ')'; return null; }
        
        if ($code !== 200) {
            $this->lastError = 'HTTP ' . $code . ': ' . ($decoded['status_message'] ?? 'Unknown error');
            return null;
        }
        
        // Check for task-level errors
        if (!empty($decoded['tasks'][0]['status_code']) && $decoded['tasks'][0]['status_code'] !== 20000) {
            $this->lastError = 'API error: ' . ($decoded['tasks'][0]['status_message'] ?? 'Task failed (code: ' . $decoded['tasks'][0]['status_code'] . ')');
        }
        
        return $decoded;
    }
    
    private function getJsonCache(string $key, int $ttl): ?array {
        $file = SeoIntelConfig::$cacheDir . '/' . $key . '.json';
        if (file_exists($file) && filemtime($file) > time() - $ttl) {
            return json_decode(file_get_contents($file), true) ?: null;
        }
        return null;
    }
    
    private function setJsonCache(string $key, $data): void {
        $file = SeoIntelConfig::$cacheDir . '/' . $key . '.json';
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// ============================================================
// 10. SEMANTIC INTERNAL LINKING ENGINE
// ============================================================
class SemanticLinkingEngine {
    
    private $db;
    private $embedder;
    
    public function __construct($db, EmbeddingEngine $embedder) {
        $this->db = $db;
        $this->embedder = $embedder;
    }
    
    /**
     * Find semantically relevant internal links for content
     */
    public function suggestLinks(string $content, int $blogId = 0, int $limit = 10): array {
        $contentEmb = $this->embedder->embed(mb_substr(strip_tags($content), 0, 1000));
        if (!$contentEmb) return [];
        
        $blogs = $this->db->query("SELECT id, title, slug, excerpt, meta_keywords FROM blogs WHERE status='published'")->fetchAll(PDO::FETCH_ASSOC);
        $products = $this->db->query("SELECT id, name, slug, short_description FROM products WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
        
        $suggestions = [];
        
        foreach ($blogs as $blog) {
            if ($blog['id'] == $blogId) continue;
            $blogText = $blog['title'] . ' ' . ($blog['excerpt'] ?? '') . ' ' . ($blog['meta_keywords'] ?? '');
            $blogEmb = $this->embedder->embed($blogText);
            if (!$blogEmb) continue;
            
            $sim = $this->embedder->cosineSimilarity($contentEmb, $blogEmb);
            if ($sim > 0.35) {
                // Find best anchor text from content
                $anchor = $this->findBestAnchor($content, $blog['title']);
                $suggestions[] = [
                    'type' => 'blog',
                    'id' => $blog['id'],
                    'title' => $blog['title'],
                    'url' => '/blog/' . $blog['slug'],
                    'relevance' => round($sim, 4),
                    'suggested_anchor' => $anchor,
                    'context' => $anchor ? mb_substr($content, max(0, mb_strpos(mb_strtolower(strip_tags($content)), mb_strtolower($anchor)) - 50), 150) : ''
                ];
            }
        }
        
        foreach ($products as $product) {
            $prodText = $product['name'] . ' ' . ($product['short_description'] ?? '');
            $prodEmb = $this->embedder->embed($prodText);
            if (!$prodEmb) continue;
            
            $sim = $this->embedder->cosineSimilarity($contentEmb, $prodEmb);
            if ($sim > 0.4) {
                $suggestions[] = [
                    'type' => 'product',
                    'id' => $product['id'],
                    'title' => $product['name'],
                    'url' => '/product/' . $product['slug'],
                    'relevance' => round($sim, 4),
                    'suggested_anchor' => $product['name'],
                    'context' => ''
                ];
            }
        }
        
        usort($suggestions, fn($a, $b) => $b['relevance'] <=> $a['relevance']);
        return array_slice($suggestions, 0, $limit);
    }
    
    private function findBestAnchor(string $content, string $title): string {
        $contentLower = mb_strtolower(strip_tags($content));
        $titleLower = mb_strtolower($title);
        
        // Check if title appears in content
        if (mb_strpos($contentLower, $titleLower) !== false) return $title;
        
        // Check title words
        $titleWords = explode(' ', $titleLower);
        $significantWords = array_filter($titleWords, fn($w) => mb_strlen($w) > 3);
        
        foreach (array_chunk($significantWords, 3) as $chunk) {
            $phrase = implode(' ', $chunk);
            if (mb_strpos($contentLower, $phrase) !== false) return $phrase;
        }
        
        foreach (array_chunk($significantWords, 2) as $chunk) {
            $phrase = implode(' ', $chunk);
            if (mb_strpos($contentLower, $phrase) !== false) return $phrase;
        }
        
        return $title;
    }
}

// ============================================================
// 11. AI RANKING PROBABILITY ENGINE
// ============================================================
class RankingProbabilityEngine {
    
    private $apiKey;
    private $db;
    public $lastError = '';
    
    public function __construct(string $apiKey, $db = null) {
        $this->apiKey = $apiKey;
        $this->db = $db;
    }
    
    /**
     * Predict ranking probability for content
     */
    public function predictRanking(string $keyword, array $contentAnalysis, array $serpData = []): ?array {
        $scores = $contentAnalysis;
        $compCount = count($serpData['competitors'] ?? []);
        $difficulty = $serpData['difficulty_estimate'] ?? 'medium';
        
        $prompt = "Predict the ranking probability for this content targeting the keyword: \"{$keyword}\"

Content scores:
- Overall SEO: {$scores['overall_score']}%
- Content Quality: " . ($scores['content_quality']['score'] ?? 0) . "%
- Readability: " . ($scores['readability']['score'] ?? 0) . "%
- E-E-A-T: " . ($scores['eeat']['score'] ?? 0) . "%
- Semantic: " . ($scores['semantic']['score'] ?? 0) . "%

SERP difficulty: {$difficulty}
Competitor count analyzed: {$compCount}
Website: gilafstore.com (niche Kashmir e-commerce)

Return JSON:
{
  \"ranking_probability\": {
    \"top_3\": 0.0-1.0,
    \"top_10\": 0.0-1.0,
    \"top_20\": 0.0-1.0,
    \"featured_snippet\": 0.0-1.0,
    \"ai_overview\": 0.0-1.0
  },
  \"estimated_monthly_traffic\": 100,
  \"time_to_rank_months\": 3,
  \"confidence\": 0.0-1.0,
  \"key_factors\": [\"factor 1\", \"factor 2\"],
  \"improvement_actions\": [{\"action\": \"what to do\", \"impact\": \"high|medium|low\", \"effort\": \"high|medium|low\"}],
  \"competitive_advantage\": \"description\",
  \"risk_factors\": [\"risk 1\"]
}";
        
        $result = $this->callAI($prompt);
        if (!$result) return null;
        $decoded = json_decode($result, true);
        if (!$decoded) { $this->lastError = 'Failed to parse AI JSON response'; return null; }
        return $decoded;
    }
    
    /**
     * Multi-provider AI call — supports OpenAI, Claude, Gemini
     */
    private function callAI(string $prompt): ?string {
        if (empty($this->apiKey)) { $this->lastError = 'No AI API key configured'; return null; }
        
        // Auto-detect provider from key prefix
        $provider = 'gemini';
        if (strpos($this->apiKey, 'sk-ant-') === 0) $provider = 'claude';
        elseif (strpos($this->apiKey, 'sk-') === 0) $provider = 'openai';
        
        // Override from DB settings if available
        $model = '';
        if ($this->db) {
            try {
                $rows = $this->db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','ai_model')")->fetchAll(\PDO::FETCH_KEY_PAIR);
                if (!empty($rows['ai_provider'])) $provider = $rows['ai_provider'];
                $model = $rows['ai_model'] ?? '';
            } catch (\Exception $e) {}
        }
        
        if ($provider === 'openai') {
            if (!$model) $model = 'gpt-4o-mini';
            $url = 'https://api.openai.com/v1/chat/completions';
            $payload = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.3, 'max_tokens' => 4096, 'response_format' => ['type' => 'json_object']];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey];
        } elseif ($provider === 'claude') {
            if (!$model) $model = 'claude-3-haiku-20240307';
            $url = 'https://api.anthropic.com/v1/messages';
            $payload = ['model' => $model, 'max_tokens' => 4096, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->apiKey, 'anthropic-version: 2023-06-01'];
        } else {
            if (!$model) $model = 'gemini-2.0-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->apiKey;
            $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']];
            $headers = ['Content-Type: application/json'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlErr) { $this->lastError = 'Curl error: ' . $curlErr; return null; }
        if ($code !== 200) {
            $errData = json_decode($resp, true);
            $errMsg = $errData['error']['message'] ?? $errData['error']['text'] ?? substr($resp, 0, 200);
            $this->lastError = ucfirst($provider) . ' HTTP ' . $code . ': ' . $errMsg;
            return null;
        }
        
        $data = json_decode($resp, true);
        $text = null;
        if ($provider === 'openai') $text = $data['choices'][0]['message']['content'] ?? null;
        elseif ($provider === 'claude') $text = $data['content'][0]['text'] ?? null;
        else $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        
        if (!$text) { $this->lastError = 'Empty response from ' . ucfirst($provider) . ' (blocked or no output)'; }
        return $text;
    }
}

// ============================================================
// 12. CONTENT OPPORTUNITY ENGINE
// ============================================================
class ContentOpportunityEngine {
    
    private $db;
    private $embedder;
    private $geminiKey;
    
    public function __construct($db, EmbeddingEngine $embedder, string $geminiKey) {
        $this->db = $db;
        $this->embedder = $embedder;
        $this->geminiKey = $geminiKey;
    }
    
    /**
     * Discover content opportunities based on existing content and market gaps
     * Uses AI when available, falls back to keyword gap analysis
     */
    public function discoverOpportunities(): ?array {
        $blogs = $this->db->query("SELECT title, meta_keywords, category_id FROM blogs WHERE status='published'")->fetchAll(PDO::FETCH_ASSOC);
        $existingTopics = array_column($blogs, 'title');
        $existingKeywords = array_filter(array_map(fn($b) => $b['meta_keywords'], $blogs));
        
        $products = [];
        try {
            $products = $this->db->query("SELECT name FROM products")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {}
        
        // Try AI-powered discovery first
        $aiResult = null;
        if ($this->geminiKey) {
            $prompt = "Analyze this Kashmir e-commerce store's content and discover high-value content opportunities.

Existing blog articles (" . count($existingTopics) . " total): " . implode(', ', array_slice($existingTopics, 0, 20)) . "

Products sold: " . implode(', ', array_slice($products, 0, 15)) . "

Keywords targeted: " . implode(', ', array_slice($existingKeywords, 0, 15)) . "

Return JSON:
{
  \"opportunities\": [
    {
      \"title\": \"Suggested Article Title\",
      \"keyword\": \"target keyword\",
      \"type\": \"pillar|cluster|support|comparison|guide|listicle\",
      \"priority\": \"high|medium|low\",
      \"estimated_traffic\": \"high|medium|low\",
      \"difficulty\": \"easy|medium|hard\",
      \"score\": 85,
      \"reasoning\": \"Why this content should be created\",
      \"connects_to\": [\"existing article titles that should link to this\"]
    }
  ],
  \"underserved_topics\": [\"topic 1\"],
  \"trending_opportunities\": [\"trend 1\"],
  \"product_content_gaps\": [\"product that needs more content support\"],
  \"seasonal_opportunities\": [{\"topic\": \"topic\", \"best_month\": \"month\"}]
}";
            $result = $this->callAI($prompt);
            if ($result) $aiResult = json_decode($result, true);
        }
        
        if ($aiResult) return $aiResult;
        
        // Fallback: keyword-based gap analysis (no AI needed)
        $allKws = [];
        foreach ($existingKeywords as $kws) {
            foreach (explode(',', $kws) as $kw) {
                $kw = trim(mb_strtolower($kw));
                if (mb_strlen($kw) >= 3) $allKws[$kw] = ($allKws[$kw] ?? 0) + 1;
            }
        }
        arsort($allKws);
        $topKeywords = array_slice(array_keys($allKws), 0, 10);
        
        // Find products without blog coverage
        $productGaps = [];
        foreach ($products as $pName) {
            $pLower = mb_strtolower($pName);
            $hasCoverage = false;
            foreach ($existingTopics as $t) {
                if (mb_strpos(mb_strtolower($t), $pLower) !== false) { $hasCoverage = true; break; }
            }
            if (!$hasCoverage) $productGaps[] = $pName;
        }
        
        // Generate opportunities from gaps
        $opportunities = [];
        $idx = 0;
        foreach (array_slice($productGaps, 0, 5) as $gap) {
            $opportunities[] = [
                'title' => 'Complete Guide to ' . $gap,
                'keyword' => mb_strtolower($gap),
                'type' => 'guide',
                'priority' => 'high',
                'estimated_traffic' => 'medium',
                'difficulty' => 'easy',
                'score' => max(60, 92 - ($idx * 5)),
                'reasoning' => 'Product "' . $gap . '" has no dedicated blog content',
                'connects_to' => array_slice($existingTopics, 0, 2)
            ];
            $idx++;
        }
        foreach (array_slice($topKeywords, 0, 3) as $kw) {
            $opportunities[] = [
                'title' => ucwords($kw) . ': Everything You Need to Know',
                'keyword' => $kw,
                'type' => 'pillar',
                'priority' => 'medium',
                'estimated_traffic' => 'high',
                'difficulty' => 'medium',
                'score' => max(50, 78 - ($idx * 4)),
                'reasoning' => 'Top keyword "' . $kw . '" targeted by ' . $allKws[$kw] . ' articles — create a pillar page',
                'connects_to' => []
            ];
            $idx++;
        }
        
        return [
            'opportunities' => $opportunities,
            'underserved_topics' => array_slice($productGaps, 0, 5),
            'trending_opportunities' => [],
            'product_content_gaps' => $productGaps,
            'seasonal_opportunities' => [],
            'method' => 'keyword_fallback'
        ];
    }
    
    private function callAI(string $prompt): ?string {
        if (!$this->geminiKey) return null;
        
        // Auto-detect provider from API key format
        $provider = 'gemini';
        if (strpos($this->geminiKey, 'sk-ant-') === 0) $provider = 'claude';
        elseif (strpos($this->geminiKey, 'sk-') === 0) $provider = 'openai';
        
        // Try to read configured provider from DB
        try {
            $rows = $this->db->query("SELECT setting_key, setting_value FROM chatbot_settings WHERE setting_key IN ('ai_provider','ai_model')")->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (!empty($rows['ai_provider'])) $provider = $rows['ai_provider'];
            $model = $rows['ai_model'] ?? '';
        } catch (\Exception $e) { $model = ''; }
        
        if ($provider === 'openai') {
            if (!$model) $model = 'gpt-4o-mini';
            $url = "https://api.openai.com/v1/chat/completions";
            $payload = ['model' => $model, 'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => 0.5, 'max_tokens' => 4096, 'response_format' => ['type' => 'json_object']];
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->geminiKey];
        } elseif ($provider === 'claude') {
            if (!$model) $model = 'claude-3-haiku-20240307';
            $url = "https://api.anthropic.com/v1/messages";
            $payload = ['model' => $model, 'max_tokens' => 4096, 'messages' => [['role' => 'user', 'content' => $prompt]]];
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $this->geminiKey, 'anthropic-version: 2023-06-01'];
        } else {
            if (!$model) $model = 'gemini-2.0-flash';
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->geminiKey;
            $payload = ['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.5, 'maxOutputTokens' => 4096, 'responseMimeType' => 'application/json']];
            $headers = ['Content-Type: application/json'];
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => false]);
        $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code !== 200) return null;
        $data = json_decode($resp, true);
        
        if ($provider === 'openai') return $data['choices'][0]['message']['content'] ?? null;
        if ($provider === 'claude') return $data['content'][0]['text'] ?? null;
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}

// ============================================================
// 12. PAGESPEED / CORE WEB VITALS ENGINE
// ============================================================
class PageSpeedEngine {
    
    public function analyze(string $url, string $strategy = 'mobile'): ?array {
        if (empty($url)) return null;
        $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' . http_build_query(['url' => $url, 'strategy' => $strategy, 'category' => 'performance']);
        
        // Retry with backoff for rate limiting (HTTP 429)
        $maxRetries = 3;
        $resp = null;
        $code = 0;
        $err = '';
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($attempt > 0) sleep(5 * $attempt); // 5s, 10s backoff
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_SSL_VERIFYPEER => false]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($code !== 429) break;
        }
        if ($code === 429) return ['error' => 'Rate limited by Google PageSpeed API. Please wait a minute and try again.'];
        if ($code !== 200 || !$resp) return ['error' => $err ?: "HTTP {$code}"];
        $data = json_decode($resp, true);
        if (!$data) return ['error' => 'Invalid response'];
        $lhr = $data['lighthouseResult'] ?? [];
        $audits = $lhr['audits'] ?? [];
        $categories = $lhr['categories'] ?? [];
        $lcp = $audits['largest-contentful-paint']['numericValue'] ?? null;
        $cls = $audits['cumulative-layout-shift']['numericValue'] ?? null;
        $tbt = $audits['total-blocking-time']['numericValue'] ?? null;
        $fcp = $audits['first-contentful-paint']['numericValue'] ?? null;
        $si = $audits['speed-index']['numericValue'] ?? null;
        $perfScore = round(($categories['performance']['score'] ?? 0) * 100);
        $checks = [];
        if ($lcp !== null) { $v = round($lcp / 1000, 1); $s = $v <= 2.5 ? 'pass' : ($v <= 4 ? 'warn' : 'fail'); $checks[] = ['status' => $s, 'text' => "LCP: {$v}s", 'metric' => 'LCP', 'value' => $v]; }
        if ($cls !== null) { $v = round($cls, 3); $s = $v <= 0.1 ? 'pass' : ($v <= 0.25 ? 'warn' : 'fail'); $checks[] = ['status' => $s, 'text' => "CLS: {$v}", 'metric' => 'CLS', 'value' => $v]; }
        if ($tbt !== null) { $v = round($tbt); $s = $v <= 200 ? 'pass' : ($v <= 600 ? 'warn' : 'fail'); $checks[] = ['status' => $s, 'text' => "TBT: {$v}ms", 'metric' => 'TBT', 'value' => $v]; }
        if ($fcp !== null) { $v = round($fcp / 1000, 1); $s = $v <= 1.8 ? 'pass' : ($v <= 3 ? 'warn' : 'fail'); $checks[] = ['status' => $s, 'text' => "FCP: {$v}s", 'metric' => 'FCP', 'value' => $v]; }
        $opportunities = [];
        foreach (['render-blocking-resources','unused-css-rules','unused-javascript','offscreen-images','unminified-css','unminified-javascript','modern-image-formats','uses-optimized-images','uses-text-compression','server-response-time'] as $ak) {
            if (isset($audits[$ak]) && ($audits[$ak]['score'] ?? 1) < 1) {
                $sav = !empty($audits[$ak]['numericValue']) ? ' (save ~' . round($audits[$ak]['numericValue'] / 1000, 1) . 's)' : '';
                $opportunities[] = ['title' => $audits[$ak]['title'] ?? $ak, 'savings' => $sav, 'score' => round(($audits[$ak]['score'] ?? 0) * 100)];
            }
        }
        return ['performance_score' => $perfScore, 'strategy' => $strategy, 'checks' => $checks, 'core_web_vitals' => ['lcp' => $lcp !== null ? round($lcp / 1000, 1) : null, 'cls' => $cls !== null ? round($cls, 3) : null, 'tbt' => $tbt !== null ? round($tbt) : null, 'fcp' => $fcp !== null ? round($fcp / 1000, 1) : null, 'si' => $si !== null ? round($si / 1000, 1) : null], 'opportunities' => array_slice($opportunities, 0, 8), 'url' => $url];
    }
}

// ============================================================
// 13. SERP COMPETITOR COMPARISON ENGINE
// ============================================================
class SerpComparisonEngine {
    private $db;
    public function __construct($db) { $this->db = $db; }
    
    public function compareWithCompetitors(array $yourPage, array $serpData): array {
        $yourWC = str_word_count(strip_tags($yourPage['content'] ?? ''));
        $yourTitle = $yourPage['title'] ?? '';
        $yourDesc = $yourPage['meta_description'] ?? '';
        $competitors = $serpData['competitors'] ?? [];
        if (empty($competitors)) return ['error' => 'No competitor data'];
        $compTitleLens = [];
        $highAuth = 0;
        foreach ($competitors as $c) {
            $compTitleLens[] = mb_strlen($c['title'] ?? '');
            if (preg_match('/(wikipedia|amazon|flipkart|healthline|webmd|forbes|nytimes|quora|reddit)/i', $c['domain'] ?? '')) $highAuth++;
        }
        $avgTL = count($compTitleLens) > 0 ? round(array_sum($compTitleLens) / count($compTitleLens)) : 0;
        $gaps = [];
        $estAvg = 1500;
        if ($yourWC < $estAvg) { $gaps[] = ['category' => 'Content Depth', 'your_value' => "{$yourWC} words", 'competitor_avg' => "~{$estAvg} words", 'gap' => ($estAvg - $yourWC) . ' words short', 'severity' => $yourWC < 500 ? 'critical' : 'high', 'fix' => 'Expand content by ' . ($estAvg - $yourWC) . ' words']; }
        if ($highAuth >= 3) { $gaps[] = ['category' => 'Domain Authority', 'your_value' => 'Small/Medium site', 'competitor_avg' => "{$highAuth} high-auth domains in top 10", 'gap' => 'Authority disadvantage', 'severity' => 'high', 'fix' => 'Build topical authority with 5-10 supporting blog posts']; }
        $ytl = mb_strlen($yourTitle);
        if ($ytl < 40 || $ytl > 65) { $gaps[] = ['category' => 'Title', 'your_value' => "{$ytl} chars", 'competitor_avg' => "{$avgTL} chars", 'gap' => 'Title not optimal', 'severity' => 'medium', 'fix' => 'Optimize to 50-60 chars with power words']; }
        if (empty($yourDesc)) { $gaps[] = ['category' => 'Meta Description', 'your_value' => 'Missing', 'competitor_avg' => 'All have descriptions', 'gap' => 'No meta description', 'severity' => 'critical', 'fix' => 'Add 120-160 char meta description']; }
        $critG = count(array_filter($gaps, fn($g) => $g['severity'] === 'critical'));
        $compScore = max(0, 100 - ($critG * 25) - (count($gaps) * 8));
        $rec = $compScore >= 80 ? 'Strong position. Build backlinks.' : ($compScore >= 60 ? 'Good foundation. Fix gaps above.' : ($compScore >= 40 ? 'Moderate disadvantage. Fix critical gaps.' : 'Target long-tail keywords first.'));
        return ['competitive_score' => $compScore, 'gaps' => $gaps, 'total_gaps' => count($gaps), 'competitors_analyzed' => count($competitors), 'high_authority_count' => $highAuth, 'recommendation' => $rec];
    }
}
