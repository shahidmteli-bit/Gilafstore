<?php
$pageTitle = 'Chatbot & AI Settings — Gilaf Admin';
$adminPage = 'chatbot_settings';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = get_db_connection();

// Ensure chatbot_settings table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS chatbot_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    error_log("Chatbot settings table creation error: " . $e->getMessage());
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        try {
            $settings = [
                'ai_provider'       => trim($_POST['ai_provider'] ?? 'gemini'),
                'api_key'           => trim($_POST['api_key'] ?? ''),
                'ai_model'          => trim($_POST['ai_model'] ?? 'gemini-2.0-flash'),
                'temperature'       => floatval($_POST['temperature'] ?? 0.7),
                'max_tokens'        => intval($_POST['max_tokens'] ?? 500),
                'system_prompt'     => trim($_POST['system_prompt'] ?? ''),
                'welcome_message'   => trim($_POST['welcome_message'] ?? ''),
                'chatbot_enabled'   => isset($_POST['chatbot_enabled']) ? '1' : '0',
                'ai_enabled'        => isset($_POST['ai_enabled']) ? '1' : '0',
                'fallback_message'  => trim($_POST['fallback_message'] ?? ''),
                'max_history'       => intval($_POST['max_history'] ?? 10),
                'response_timeout'  => intval($_POST['response_timeout'] ?? 30),
            ];
            
            $stmt = $db->prepare("INSERT INTO chatbot_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            $success = 'Chatbot settings saved successfully!';
        } catch (Exception $e) {
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
    
    if ($action === 'test_api') {
        $provider = trim($_POST['test_provider'] ?? 'gemini');
        $apiKey = trim($_POST['test_api_key'] ?? '');
        $model = trim($_POST['test_model'] ?? 'gemini-2.0-flash');
        
        if (empty($apiKey)) {
            $error = 'API key is required for testing.';
        } else {
            // Test the API connection
            $testResult = test_ai_api($provider, $apiKey, $model);
            if ($testResult['success']) {
                $success = 'API connection successful! Response: ' . substr($testResult['response'], 0, 200);
            } else {
                $error = 'API test failed: ' . $testResult['error'];
            }
        }
    }
}

// Load current settings
function get_chatbot_setting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM chatbot_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function test_ai_api($provider, $apiKey, $model) {
    $testMessage = "Say 'Hello! API connection successful.' in exactly those words.";
    
    if ($provider === 'gemini') {
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $requestData = [
            'contents' => [['parts' => [['text' => $testMessage]]]],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 100]
        ];
    } elseif ($provider === 'openai') {
        $apiUrl = "https://api.openai.com/v1/chat/completions";
        $requestData = [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $testMessage]],
            'temperature' => 0.1,
            'max_tokens' => 100
        ];
    } elseif ($provider === 'claude') {
        $apiUrl = "https://api.anthropic.com/v1/messages";
        $requestData = [
            'model' => $model,
            'max_tokens' => 100,
            'messages' => [['role' => 'user', 'content' => $testMessage]],
        ];
    } else {
        return ['success' => false, 'error' => 'Unknown provider'];
    }
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    
    $headers = ['Content-Type: application/json'];
    if ($provider === 'openai') {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    } elseif ($provider === 'claude') {
        $headers[] = 'x-api-key: ' . $apiKey;
        $headers[] = 'anthropic-version: 2023-06-01';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // SSL settings — use system CA bundle, fallback for XAMPP/Windows
    $caBundle = ini_get('curl.cainfo');
    if (empty($caBundle) || !file_exists($caBundle)) {
        // Try common XAMPP locations
        $possibleCerts = [
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            dirname(PHP_BINARY) . '/extras/ssl/cacert.pem',
        ];
        foreach ($possibleCerts as $cert) {
            if (file_exists($cert)) {
                curl_setopt($ch, CURLOPT_CAINFO, $cert);
                break;
            }
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($curlError) {
        // SSL certificate error — provide helpful message
        if ($curlErrno === 60 || $curlErrno === 77) {
            return ['success' => false, 'error' => "SSL Certificate error: {$curlError}. Download cacert.pem from https://curl.se/ca/cacert.pem and set curl.cainfo in php.ini."];
        }
        return ['success' => false, 'error' => "Connection error: {$curlError}"];
    }
    
    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        // Anthropic format: {"type":"error","error":{"type":"...","message":"..."}}
        // OpenAI/Gemini format: {"error":{"message":"..."}}
        $errMsg = '';
        if (isset($decoded['error']['message'])) {
            $errMsg = $decoded['error']['message'];
        } elseif (isset($decoded['error']['type'])) {
            $errMsg = $decoded['error']['type'] . ': ' . ($decoded['error']['message'] ?? 'Unknown');
        } elseif (isset($decoded['message'])) {
            $errMsg = $decoded['message'];
        } else {
            $errMsg = "HTTP {$httpCode} — " . substr($response, 0, 300);
        }
        return ['success' => false, 'error' => $errMsg];
    }
    
    $decoded = json_decode($response, true);
    $text = '';
    
    if ($provider === 'gemini') {
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
    } elseif ($provider === 'openai') {
        $text = $decoded['choices'][0]['message']['content'] ?? 'No response';
    } elseif ($provider === 'claude') {
        $text = $decoded['content'][0]['text'] ?? 'No response';
    }
    
    return ['success' => true, 'response' => $text];
}

$settings = [
    'ai_provider'      => get_chatbot_setting($db, 'ai_provider', 'gemini'),
    'api_key'          => get_chatbot_setting($db, 'api_key', ''),
    'ai_model'         => get_chatbot_setting($db, 'ai_model', 'gemini-2.0-flash'),
    'temperature'      => get_chatbot_setting($db, 'temperature', '0.7'),
    'max_tokens'       => get_chatbot_setting($db, 'max_tokens', '500'),
    'system_prompt'    => get_chatbot_setting($db, 'system_prompt', ''),
    'welcome_message'  => get_chatbot_setting($db, 'welcome_message', ''),
    'chatbot_enabled'  => get_chatbot_setting($db, 'chatbot_enabled', '1'),
    'ai_enabled'       => get_chatbot_setting($db, 'ai_enabled', '1'),
    'fallback_message' => get_chatbot_setting($db, 'fallback_message', ''),
    'max_history'      => get_chatbot_setting($db, 'max_history', '10'),
    'response_timeout' => get_chatbot_setting($db, 'response_timeout', '30'),
];

$defaultSystemPrompt = "You are a helpful customer support AI assistant for Gilaf, a premium eCommerce store selling authentic Kashmiri saffron and spices.

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
- Free shipping on orders above Rs.999
- 7-day return policy for unopened products
- Payment methods: Cards, UPI, Net Banking, Wallets, COD

Important Rules:
1. For order tracking, direct users to the Track Order page
2. For complex issues, suggest creating a support ticket
3. For payment issues, provide troubleshooting steps first
4. Never make up information - be honest about limitations
5. Always maintain a helpful, professional tone";

if (empty($settings['system_prompt'])) {
    $settings['system_prompt'] = $defaultSystemPrompt;
}
if (empty($settings['welcome_message'])) {
    $settings['welcome_message'] = "Hello! 👋 I'm your Gilaf Store assistant. How can I help you today?";
}
if (empty($settings['fallback_message'])) {
    $settings['fallback_message'] = "I'm sorry, I couldn't process your request right now. Please try again or create a support ticket for assistance.";
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.cbs-page { max-width: 1100px; }
.cbs-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
.cbs-header h2 { font-size: 1.6rem; font-weight: 700; color: #1a1a2e; margin: 0; display: flex; align-items: center; gap: 10px; }
.cbs-header h2 i { color: #6366f1; }
.cbs-tabs { display: flex; gap: 4px; background: #f1f5f9; border-radius: 10px; padding: 4px; margin-bottom: 24px; }
.cbs-tab { padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; color: #64748b; background: transparent; border: none; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
.cbs-tab.active { background: #fff; color: #1e293b; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.cbs-tab:hover:not(.active) { color: #1e293b; }
.cbs-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 28px; margin-bottom: 20px; }
.cbs-card-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; padding-bottom: 14px; border-bottom: 2px solid #f1f5f9; }
.cbs-card-title i { color: #6366f1; font-size: 1.1rem; }
.cbs-form-group { margin-bottom: 20px; }
.cbs-form-group label { display: block; font-weight: 600; font-size: 0.85rem; color: #374151; margin-bottom: 6px; }
.cbs-form-group .hint { font-size: 0.78rem; color: #9ca3af; margin-top: 4px; }
.cbs-input, .cbs-select, .cbs-textarea { width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; color: #1e293b; background: #fff; transition: border-color 0.2s; }
.cbs-input:focus, .cbs-select:focus, .cbs-textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.cbs-textarea { min-height: 140px; resize: vertical; font-family: monospace; font-size: 0.82rem; line-height: 1.6; }
.cbs-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.cbs-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
.cbs-toggle { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; }
.cbs-toggle input[type="checkbox"] { width: 44px; height: 24px; appearance: none; background: #cbd5e1; border-radius: 12px; position: relative; cursor: pointer; transition: background 0.2s; flex-shrink: 0; }
.cbs-toggle input[type="checkbox"]:checked { background: #22c55e; }
.cbs-toggle input[type="checkbox"]::after { content: ''; position: absolute; width: 18px; height: 18px; background: #fff; border-radius: 50%; top: 3px; left: 3px; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
.cbs-toggle input[type="checkbox"]:checked::after { transform: translateX(20px); }
.cbs-toggle-text { font-weight: 600; font-size: 0.9rem; color: #1e293b; }
.cbs-toggle-hint { font-size: 0.78rem; color: #9ca3af; }
.cbs-btn { padding: 11px 24px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
.cbs-btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
.cbs-btn-primary:hover { background: linear-gradient(135deg, #4f46e5, #4338ca); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
.cbs-btn-success { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
.cbs-btn-success:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(34,197,94,0.3); }
.cbs-btn-outline { background: #fff; color: #374151; border: 1.5px solid #e2e8f0; }
.cbs-btn-outline:hover { border-color: #6366f1; color: #6366f1; }
.cbs-actions { display: flex; gap: 12px; justify-content: flex-end; padding-top: 16px; border-top: 2px solid #f1f5f9; margin-top: 8px; }
.cbs-alert { padding: 14px 18px; border-radius: 10px; font-size: 0.85rem; font-weight: 500; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.cbs-alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.cbs-alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.cbs-tab-content { display: none; }
.cbs-tab-content.active { display: block; }
.cbs-provider-card { padding: 16px; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: all 0.2s; text-align: center; }
.cbs-provider-card.selected { border-color: #6366f1; background: rgba(99,102,241,0.04); }
.cbs-provider-card:hover { border-color: #a5b4fc; }
.cbs-provider-card.disabled { opacity: 0.85; filter: grayscale(15%); pointer-events: none; border-color: #e2e8f0; background: #f9fafb; }
.cbs-provider-card.disabled h4 { color: #94a3b8; }
.cbs-provider-card.disabled p { color: #cbd5e1; }
.cbs-provider-card.disabled .provider-icon { opacity: 0.7; }
.cbs-row-3.has-selection .cbs-provider-card.disabled { pointer-events: auto; cursor: pointer; }
.cbs-provider-card img, .cbs-provider-card .provider-icon { width: 48px; height: 48px; margin-bottom: 8px; }
.cbs-provider-card .provider-icon { display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: 1.8rem; border-radius: 12px; }
.cbs-provider-card h4 { font-size: 0.95rem; font-weight: 700; margin: 0 0 4px; color: #1e293b; }
.cbs-provider-card p { font-size: 0.75rem; color: #9ca3af; margin: 0; }
.cbs-range-container { display: flex; align-items: center; gap: 14px; }
.cbs-range-container input[type="range"] { flex: 1; height: 6px; appearance: none; background: #e2e8f0; border-radius: 3px; outline: none; }
.cbs-range-container input[type="range"]::-webkit-slider-thumb { appearance: none; width: 20px; height: 20px; background: #6366f1; border-radius: 50%; cursor: pointer; }
.cbs-range-value { min-width: 40px; text-align: center; font-weight: 700; color: #6366f1; font-size: 0.9rem; }
.cbs-api-key-wrap { position: relative; }
.cbs-api-key-wrap input { padding-right: 90px; }
.cbs-api-key-wrap .toggle-btn { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 12px; font-size: 0.75rem; cursor: pointer; color: #64748b; }
.cbs-model-presets { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.cbs-model-preset { padding: 4px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.75rem; cursor: pointer; color: #64748b; transition: all 0.2s; }
.cbs-model-preset:hover, .cbs-model-preset.active { background: #6366f1; color: #fff; border-color: #6366f1; }
.cbs-test-result { margin-top: 12px; padding: 14px; border-radius: 8px; font-size: 0.82rem; display: none; }
@media (max-width: 768px) {
    .cbs-row, .cbs-row-3 { grid-template-columns: 1fr; }
    .cbs-tabs { flex-wrap: wrap; }
}
</style>

<div class="cbs-page">
    <div class="cbs-header">
        <h2><i class="fas fa-robot"></i> Chatbot & AI Settings</h2>
        <div style="display:flex;gap:8px;">
            <button type="button" class="cbs-btn cbs-btn-outline" onclick="testApiConnection()"><i class="fas fa-plug"></i> Test Connection</button>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="cbs-alert cbs-alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="cbs-alert cbs-alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="cbs-tabs">
        <button class="cbs-tab active" onclick="switchTab('api')"><i class="fas fa-key"></i> API Configuration</button>
        <button class="cbs-tab" onclick="switchTab('behavior')"><i class="fas fa-sliders-h"></i> AI Behavior</button>
        <button class="cbs-tab" onclick="switchTab('messages')"><i class="fas fa-comment-dots"></i> Messages & Prompts</button>
        <button class="cbs-tab" onclick="switchTab('general')"><i class="fas fa-cog"></i> General Settings</button>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="save_settings">

        <!-- Tab 1: API Configuration -->
        <div class="cbs-tab-content active" id="tab-api">
            <div class="cbs-card">
                <div class="cbs-card-title"><i class="fas fa-cloud"></i> AI Provider</div>
                <div class="cbs-row-3" style="margin-bottom:20px;">
                    <label class="cbs-provider-card <?= $settings['ai_provider'] === 'gemini' ? 'selected' : '' ?>" onclick="selectProvider('gemini')">
                        <div class="provider-icon" style="background:linear-gradient(135deg,#4285f4,#34a853);color:#fff;">G</div>
                        <h4>Google Gemini</h4>
                        <p>Free tier available, fast responses</p>
                    </label>
                    <label class="cbs-provider-card <?= $settings['ai_provider'] === 'openai' ? 'selected' : '' ?>" onclick="selectProvider('openai')">
                        <div class="provider-icon" style="background:#000;color:#fff;">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h4>OpenAI (GPT)</h4>
                        <p>GPT-4o, GPT-4o-mini models</p>
                    </label>
                    <label class="cbs-provider-card <?= $settings['ai_provider'] === 'claude' ? 'selected' : '' ?>" onclick="selectProvider('claude')">
                        <div class="provider-icon" style="background:linear-gradient(135deg,#d97706,#b45309);color:#fff;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.1 9.3c-.2-.5-.7-.4-1.1-.2l-3.1 1.8c-.3.2-.5.1-.5-.2V6.5c0-.5-.4-.7-.8-.4l-4.7 3.6c-.3.2-.3.6 0 .8l4.7 3.6c.4.3.8.1.8-.4v-2.5c0-.3.2-.4.5-.2l3.1 1.8c.4.2.9.3 1.1-.2l.8-1.7c.2-.5.2-1 0-1.5l-.8-1.7zM12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z" fill="currentColor"/></svg>
                        </div>
                        <h4>Claude (Anthropic)</h4>
                        <p>Opus 4.7, Sonnet 4.6, Haiku models</p>
                    </label>
                </div>
                <input type="hidden" name="ai_provider" id="ai_provider" value="<?= htmlspecialchars($settings['ai_provider']) ?>">

                <div class="cbs-form-group">
                    <label><i class="fas fa-key"></i> API Key</label>
                    <div class="cbs-api-key-wrap">
                        <input type="password" name="api_key" id="api_key" class="cbs-input" value="<?= htmlspecialchars($settings['api_key']) ?>" placeholder="Enter your API key...">
                        <button type="button" class="toggle-btn" onclick="toggleApiKey()"><i class="fas fa-eye"></i> Show</button>
                    </div>
                    <div class="hint" id="api_key_hint">
                        <?php if ($settings['ai_provider'] === 'gemini'): ?>
                        Get your key from <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a>
                        <?php elseif ($settings['ai_provider'] === 'claude'): ?>
                        Get your key from <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a>
                        <?php else: ?>
                        Get your key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cbs-form-group">
                    <label><i class="fas fa-microchip"></i> AI Model</label>
                    <select name="ai_model" id="ai_model" class="cbs-select" style="margin-bottom:8px;">
                        <!-- Populated by JS based on provider -->
                    </select>
                    <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
                        <input type="text" id="custom_model_input" class="cbs-input" placeholder="Or type a custom model name..." style="flex:1;font-size:0.82rem;">
                        <button type="button" class="cbs-btn cbs-btn-outline" onclick="applyCustomModel()" style="padding:8px 14px;font-size:0.78rem;white-space:nowrap;"><i class="fas fa-plus"></i> Use Custom</button>
                    </div>
                    <div class="hint">Select from dropdown or enter a custom model name. New models are included as they release.</div>
                </div>

                <div id="test_result" class="cbs-test-result"></div>
            </div>
        </div>

        <!-- Tab 2: AI Behavior -->
        <div class="cbs-tab-content" id="tab-behavior">
            <div class="cbs-card">
                <div class="cbs-card-title"><i class="fas fa-brain"></i> AI Response Settings</div>
                
                <div class="cbs-form-group">
                    <label>Temperature (Creativity Level)</label>
                    <div class="cbs-range-container">
                        <span style="font-size:0.75rem;color:#9ca3af;">Precise</span>
                        <input type="range" name="temperature" id="temperature" min="0" max="1" step="0.1" value="<?= htmlspecialchars($settings['temperature']) ?>" oninput="document.getElementById('temp_val').textContent=this.value">
                        <span style="font-size:0.75rem;color:#9ca3af;">Creative</span>
                        <span class="cbs-range-value" id="temp_val"><?= htmlspecialchars($settings['temperature']) ?></span>
                    </div>
                    <div class="hint">Lower = more factual, Higher = more creative responses</div>
                </div>

                <div class="cbs-row">
                    <div class="cbs-form-group">
                        <label>Max Output Tokens</label>
                        <input type="number" name="max_tokens" class="cbs-input" value="<?= htmlspecialchars($settings['max_tokens']) ?>" min="50" max="4096">
                        <div class="hint">Maximum length of AI response (50-4096)</div>
                    </div>
                    <div class="cbs-form-group">
                        <label>Conversation History Limit</label>
                        <input type="number" name="max_history" class="cbs-input" value="<?= htmlspecialchars($settings['max_history']) ?>" min="1" max="50">
                        <div class="hint">How many messages to include as context</div>
                    </div>
                </div>

                <div class="cbs-form-group">
                    <label>Response Timeout (seconds)</label>
                    <input type="number" name="response_timeout" class="cbs-input" value="<?= htmlspecialchars($settings['response_timeout']) ?>" min="5" max="120" style="max-width:200px;">
                    <div class="hint">How long to wait for AI response before fallback</div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Messages & Prompts -->
        <div class="cbs-tab-content" id="tab-messages">
            <div class="cbs-card">
                <div class="cbs-card-title"><i class="fas fa-robot"></i> System Prompt</div>
                <div class="cbs-form-group">
                    <label>System Instructions for AI</label>
                    <textarea name="system_prompt" class="cbs-textarea" style="min-height:260px;"><?= htmlspecialchars($settings['system_prompt']) ?></textarea>
                    <div class="hint">This tells the AI how to behave, what info to provide, and your business details. Edit freely.</div>
                </div>
            </div>
            
            <div class="cbs-card">
                <div class="cbs-card-title"><i class="fas fa-comment-dots"></i> Custom Messages</div>
                <div class="cbs-form-group">
                    <label>Welcome Message</label>
                    <textarea name="welcome_message" class="cbs-textarea" style="min-height:80px;"><?= htmlspecialchars($settings['welcome_message']) ?></textarea>
                    <div class="hint">First message shown when user opens the chatbot</div>
                </div>
                <div class="cbs-form-group">
                    <label>Fallback Message (when AI is unavailable)</label>
                    <textarea name="fallback_message" class="cbs-textarea" style="min-height:80px;"><?= htmlspecialchars($settings['fallback_message']) ?></textarea>
                    <div class="hint">Shown when AI service is down or disabled</div>
                </div>
            </div>
        </div>

        <!-- Tab 4: General Settings -->
        <div class="cbs-tab-content" id="tab-general">
            <div class="cbs-card">
                <div class="cbs-card-title"><i class="fas fa-cog"></i> Chatbot Controls</div>
                
                <div class="cbs-form-group">
                    <div class="cbs-toggle">
                        <input type="checkbox" name="chatbot_enabled" id="chatbot_enabled" <?= $settings['chatbot_enabled'] === '1' ? 'checked' : '' ?>>
                        <div>
                            <div class="cbs-toggle-text">Enable Chatbot Widget</div>
                            <div class="cbs-toggle-hint">Show the chatbot bubble on your website</div>
                        </div>
                    </div>
                </div>

                <div class="cbs-form-group">
                    <div class="cbs-toggle">
                        <input type="checkbox" name="ai_enabled" id="ai_enabled" <?= $settings['ai_enabled'] === '1' ? 'checked' : '' ?>>
                        <div>
                            <div class="cbs-toggle-text">Enable AI Responses</div>
                            <div class="cbs-toggle-hint">When disabled, chatbot will only use the built-in knowledge base (no API calls)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cbs-actions">
            <button type="button" class="cbs-btn cbs-btn-outline" onclick="window.location.reload()"><i class="fas fa-undo"></i> Reset</button>
            <button type="submit" class="cbs-btn cbs-btn-primary"><i class="fas fa-save"></i> Save All Settings</button>
        </div>
    </form>
</div>

<!-- Test API Form (hidden) -->
<form method="POST" id="testApiForm" style="display:none;">
    <input type="hidden" name="action" value="test_api">
    <input type="hidden" name="test_provider" id="test_provider">
    <input type="hidden" name="test_api_key" id="test_api_key">
    <input type="hidden" name="test_model" id="test_model">
</form>

<script>
// ══════════════════════════════════════════════════════════════
// Comprehensive model lists — update these when new models release
// ══════════════════════════════════════════════════════════════
var AI_MODELS = {
    'gemini': [
        { value: 'gemini-2.5-flash',   label: 'Gemini 2.5 Flash',      tag: 'Latest' },
        { value: 'gemini-2.5-pro',     label: 'Gemini 2.5 Pro',        tag: 'Pro' },
        { value: 'gemini-2.0-flash',   label: 'Gemini 2.0 Flash',      tag: '' },
        { value: 'gemini-1.5-pro',     label: 'Gemini 1.5 Pro',        tag: '' },
        { value: 'gemini-1.5-flash',   label: 'Gemini 1.5 Flash',      tag: '' },
        { value: 'gemini-1.5-flash-8b',label: 'Gemini 1.5 Flash 8B',   tag: 'Lite' },
    ],
    'openai': [
        { value: 'gpt-4.1',            label: 'GPT-4.1',               tag: 'Latest' },
        { value: 'gpt-4.1-mini',       label: 'GPT-4.1 Mini',          tag: 'Fast' },
        { value: 'gpt-4.1-nano',       label: 'GPT-4.1 Nano',          tag: 'Lite' },
        { value: 'gpt-4o',             label: 'GPT-4o',                tag: '' },
        { value: 'gpt-4o-mini',        label: 'GPT-4o Mini',           tag: '' },
        { value: 'gpt-4-turbo',        label: 'GPT-4 Turbo',           tag: '' },
        { value: 'gpt-3.5-turbo',      label: 'GPT-3.5 Turbo',         tag: 'Budget' },
        { value: 'o4-mini',            label: 'o4-mini (Reasoning)',    tag: 'New' },
        { value: 'o3',                 label: 'o3 (Reasoning)',         tag: 'New' },
        { value: 'o3-mini',            label: 'o3-mini (Reasoning)',    tag: '' },
        { value: 'o1',                 label: 'o1 (Reasoning)',         tag: '' },
        { value: 'o1-mini',            label: 'o1-mini (Reasoning)',    tag: '' },
    ],
    'claude': [
        { value: 'claude-opus-4-7',             label: 'Claude Opus 4.7',           tag: 'Most Capable' },
        { value: 'claude-sonnet-4-6',           label: 'Claude Sonnet 4.6',         tag: 'Latest' },
        { value: 'claude-opus-4-6',             label: 'Claude Opus 4.6',           tag: '' },
        { value: 'claude-sonnet-4-5-20241022',  label: 'Claude Sonnet 4.5',         tag: '' },
        { value: 'claude-haiku-4-5-20251001',   label: 'Claude Haiku 4.5',          tag: 'Fast & Cheap' },
    ]
};

var SAVED_MODEL = <?= json_encode($settings['ai_model']) ?>;

function switchTab(tab) {
    document.querySelectorAll('.cbs-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.cbs-tab-content').forEach(function(c) { c.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.closest('.cbs-tab').classList.add('active');
}

function selectProvider(provider) {
    document.getElementById('ai_provider').value = provider;
    var cards = document.querySelectorAll('.cbs-provider-card');
    var providers = ['gemini', 'openai', 'claude'];
    cards.forEach(function(c, i) {
        c.classList.remove('selected', 'disabled');
        if (providers[i] === provider) {
            c.classList.add('selected');
        } else {
            c.classList.add('disabled');
        }
    });
    document.querySelector('.cbs-row-3').classList.add('has-selection');
    updateModelDropdown(provider);
    updateApiKeyHint(provider);
}

function updateModelDropdown(provider) {
    var select = document.getElementById('ai_model');
    var models = AI_MODELS[provider] || [];
    var currentVal = select.value || SAVED_MODEL;

    select.innerHTML = '';
    var hasMatch = false;
    models.forEach(function(m) {
        var opt = document.createElement('option');
        opt.value = m.value;
        opt.textContent = m.label + (m.tag ? ' — ' + m.tag : '');
        if (m.value === currentVal) {
            opt.selected = true;
            hasMatch = true;
        }
        select.appendChild(opt);
    });

    // If saved model isn't in the list (custom model), add it at top
    if (!hasMatch && currentVal) {
        var providerModels = models.map(function(m) { return m.value; });
        if (providerModels.indexOf(currentVal) === -1) {
            var customOpt = document.createElement('option');
            customOpt.value = currentVal;
            customOpt.textContent = currentVal + ' (Custom)';
            customOpt.selected = true;
            select.insertBefore(customOpt, select.firstChild);
        }
    }
}

function applyCustomModel() {
    var input = document.getElementById('custom_model_input');
    var val = input.value.trim();
    if (!val) { input.focus(); return; }

    var select = document.getElementById('ai_model');
    // Check if already exists
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].value === val) {
            select.selectedIndex = i;
            input.value = '';
            return;
        }
    }
    // Add as custom option at top
    var opt = document.createElement('option');
    opt.value = val;
    opt.textContent = val + ' (Custom)';
    opt.selected = true;
    select.insertBefore(opt, select.firstChild);
    input.value = '';
}

function updateApiKeyHint(provider) {
    var hint = document.getElementById('api_key_hint');
    if (provider === 'gemini') {
        hint.innerHTML = 'Get your key from <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a>';
    } else if (provider === 'claude') {
        hint.innerHTML = 'Get your key from <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a>';
    } else {
        hint.innerHTML = 'Get your key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a>';
    }
}

function toggleApiKey() {
    var input = document.getElementById('api_key');
    var btn = event.target.closest('.toggle-btn');
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i> Show';
    }
}

function testApiConnection() {
    var provider = document.getElementById('ai_provider').value;
    var apiKey = document.getElementById('api_key').value;
    var model = document.getElementById('ai_model').value;

    if (!apiKey) {
        alert('Please enter an API key first.');
        switchTabDirect('api');
        return;
    }

    document.getElementById('test_provider').value = provider;
    document.getElementById('test_api_key').value = apiKey;
    document.getElementById('test_model').value = model;
    document.getElementById('testApiForm').submit();
}

function switchTabDirect(tab) {
    document.querySelectorAll('.cbs-tab').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.cbs-tab-content').forEach(function(c) { c.classList.remove('active'); });
    document.getElementById('tab-' + tab).classList.add('active');
    var tabIndex = ['api','behavior','messages','general'].indexOf(tab);
    document.querySelectorAll('.cbs-tab')[tabIndex].classList.add('active');
}

// Initialize on load — apply selected/disabled states and populate model dropdown
selectProvider(document.getElementById('ai_provider').value);
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
