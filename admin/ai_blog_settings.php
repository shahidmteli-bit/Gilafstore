<?php
/**
 * AI Blog Settings - Enterprise Publishing Engine Configuration
 * Manage all AI prompts, rules, scoring thresholds, and generation settings
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

if (!is_admin()) {
    header('Location: ' . base_url('admin/admin_login.php'));
    exit;
}

$db = get_db_connection();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        try {
            // Check if settings exist
            $exists = $db->query("SELECT id FROM ai_blog_settings LIMIT 1")->fetch();
            
            $fields = [
                'system_prompt', 'seo_rules', 'conversion_rules', 'thumbnail_rules',
                'validation_rules', 'emotional_rules', 'ctr_rules', 'internal_link_rules',
                'faq_rules', 'schema_rules', 'readability_rules', 'brand_voice_rules',
                'ai_model', 'temperature', 'max_tokens', 'top_p', 'frequency_penalty',
                'presence_penalty', 'auto_regenerate_failed', 'minimum_seo_score',
                'minimum_ctr_score', 'minimum_readability_score', 'minimum_conversion_score',
                'minimum_human_score', 'retry_limit', 'enable_ai_scoring',
                'enable_auto_internal_links', 'enable_auto_cta', 'enable_schema_generation',
                'enable_auto_thumbnail_prompt', 'enable_emotional_optimizer',
                'enable_search_intent_optimizer', 'is_active'
            ];
            
            $values = [];
            foreach ($fields as $field) {
                if (in_array($field, ['temperature', 'top_p', 'frequency_penalty', 'presence_penalty'])) {
                    $values[$field] = floatval($_POST[$field] ?? 0);
                } elseif (in_array($field, ['max_tokens', 'retry_limit', 'minimum_seo_score', 'minimum_ctr_score', 'minimum_readability_score', 'minimum_conversion_score', 'minimum_human_score'])) {
                    $values[$field] = intval($_POST[$field] ?? 0);
                } elseif (strpos($field, 'enable_') === 0 || $field === 'auto_regenerate_failed' || $field === 'is_active') {
                    $values[$field] = isset($_POST[$field]) ? 1 : 0;
                } else {
                    $values[$field] = $_POST[$field] ?? '';
                }
            }
            
            if ($exists) {
                $setParts = array_map(fn($f) => "$f = ?", $fields);
                $sql = "UPDATE ai_blog_settings SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([...array_values($values), $exists['id']]);
            } else {
                $sql = "INSERT INTO ai_blog_settings (" . implode(', ', $fields) . ") VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";
                $stmt = $db->prepare($sql);
                $stmt->execute(array_values($values));
            }
            
            $message = 'AI settings saved successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error saving settings: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'save_internal_link') {
        try {
            $id = intval($_POST['link_id'] ?? 0);
            $keyword = trim($_POST['keyword'] ?? '');
            $target_url = trim($_POST['target_url'] ?? '');
            $anchor_text = trim($_POST['anchor_text'] ?? '');
            $priority = intval($_POST['priority'] ?? 5);
            $max_usage = intval($_POST['max_usage'] ?? 2);
            $is_active = isset($_POST['link_is_active']) ? 1 : 0;
            
            if ($id > 0) {
                $db->prepare("UPDATE internal_link_rules SET keyword=?, target_url=?, anchor_text=?, priority=?, max_usage=?, is_active=? WHERE id=?")
                   ->execute([$keyword, $target_url, $anchor_text, $priority, $max_usage, $is_active, $id]);
            } else {
                $db->prepare("INSERT INTO internal_link_rules (keyword, target_url, anchor_text, priority, max_usage, is_active) VALUES (?,?,?,?,?,?)")
                   ->execute([$keyword, $target_url, $anchor_text, $priority, $max_usage, $is_active]);
            }
            $message = 'Internal link rule saved!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete_internal_link') {
        $id = intval($_POST['link_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM internal_link_rules WHERE id=?")->execute([$id]);
            $message = 'Internal link rule deleted!';
            $messageType = 'success';
        }
    }
    
    if ($action === 'save_cta') {
        try {
            $id = intval($_POST['cta_id'] ?? 0);
            $title = trim($_POST['cta_title'] ?? '');
            $content = trim($_POST['cta_content'] ?? '');
            $button_text = trim($_POST['button_text'] ?? '');
            $button_link = trim($_POST['button_link'] ?? '');
            $style_type = $_POST['style_type'] ?? 'inline';
            $position_rule = $_POST['position_rule'] ?? 'mid_content';
            $is_active = isset($_POST['cta_is_active']) ? 1 : 0;
            
            if ($id > 0) {
                $db->prepare("UPDATE cta_templates SET title=?, content=?, button_text=?, button_link=?, style_type=?, position_rule=?, is_active=? WHERE id=?")
                   ->execute([$title, $content, $button_text, $button_link, $style_type, $position_rule, $is_active, $id]);
            } else {
                $db->prepare("INSERT INTO cta_templates (title, content, button_text, button_link, style_type, position_rule, is_active) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$title, $content, $button_text, $button_link, $style_type, $position_rule, $is_active]);
            }
            $message = 'CTA template saved!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete_cta') {
        $id = intval($_POST['cta_id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM cta_templates WHERE id=?")->execute([$id]);
            $message = 'CTA template deleted!';
            $messageType = 'success';
        }
    }
}

// Load current settings
$settings = [];
try {
    $settings = $db->query("SELECT * FROM ai_blog_settings WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $message = 'Please run the setup first: <a href="ai_blog_setup.php">Run AI Blog Setup</a>';
    $messageType = 'warning';
}

// Load internal links
$internalLinks = [];
try {
    $internalLinks = $db->query("SELECT * FROM internal_link_rules ORDER BY priority DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Load CTA templates
$ctaTemplates = [];
try {
    $ctaTemplates = $db->query("SELECT * FROM cta_templates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Load recent logs
$recentLogs = [];
try {
    $recentLogs = $db->query("SELECT id, blog_id, retry_count, generation_time, token_usage, created_at FROM ai_blog_logs ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$adminPage = 'ai_blog_settings';
include __DIR__ . '/admin_header.php';
?>

<style>
.ai-settings-container { max-width: 1400px; margin: 0 auto; }
.settings-hero { background: linear-gradient(135deg, #1a3c34 0%, #2d6a4f 100%); color: white; border-radius: 16px; padding: 32px; margin-bottom: 24px; }
.settings-hero h1 { margin: 0; font-size: 1.75rem; }
.settings-hero p { margin: 8px 0 0; opacity: 0.85; }

.settings-tabs { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
.settings-tab { padding: 12px 20px; border-radius: 8px; background: white; border: 1px solid #e5e7eb; cursor: pointer; font-weight: 500; transition: all 0.2s; }
.settings-tab:hover { border-color: #1a3c34; }
.settings-tab.active { background: #1a3c34; color: white; border-color: #1a3c34; }

.settings-panel { display: none; }
.settings-panel.active { display: block; }

.settings-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 24px; margin-bottom: 20px; }
.settings-card h3 { margin: 0 0 16px; font-size: 1.1rem; color: #1a3c34; display: flex; align-items: center; gap: 8px; }
.settings-card h3 i { opacity: 0.7; }

.form-label { font-weight: 500; margin-bottom: 6px; display: block; color: #374151; }
.form-hint { font-size: 0.8rem; color: #6b7280; margin-top: 4px; }

.prompt-textarea { width: 100%; min-height: 200px; font-family: 'Monaco', 'Menlo', monospace; font-size: 0.85rem; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; resize: vertical; }
.prompt-textarea:focus { outline: none; border-color: #1a3c34; box-shadow: 0 0 0 3px rgba(26,60,52,0.1); }

.slider-group { display: flex; align-items: center; gap: 12px; }
.slider-group input[type="range"] { flex: 1; }
.slider-value { min-width: 50px; text-align: center; font-weight: 600; color: #1a3c34; }

.toggle-group { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
.toggle-group:last-child { border-bottom: none; }
.toggle-label { flex: 1; }
.toggle-label strong { display: block; }
.toggle-label span { font-size: 0.85rem; color: #6b7280; }

.switch { position: relative; width: 48px; height: 26px; }
.switch input { opacity: 0; width: 0; height: 0; }
.switch .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #d1d5db; border-radius: 26px; transition: 0.3s; }
.switch .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
.switch input:checked + .slider { background: #1a3c34; }
.switch input:checked + .slider:before { transform: translateX(22px); }

.score-thresholds { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; }
.score-input { display: flex; flex-direction: column; }
.score-input input { padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; text-align: center; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
.data-table th { background: #f9fafb; font-weight: 600; color: #374151; }
.data-table tr:hover { background: #f9fafb; }

.btn-sm { padding: 6px 12px; font-size: 0.85rem; border-radius: 6px; }
.btn-primary { background: #1a3c34; color: white; border: none; cursor: pointer; }
.btn-primary:hover { background: #2d6a4f; }
.btn-danger { background: #dc2626; color: white; border: none; cursor: pointer; }
.btn-outline { background: white; border: 1px solid #d1d5db; cursor: pointer; }
.btn-outline:hover { border-color: #1a3c34; }

.badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

.variables-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.variable-tag { background: #f3f4f6; padding: 4px 10px; border-radius: 4px; font-family: monospace; font-size: 0.8rem; cursor: pointer; }
.variable-tag:hover { background: #e5e7eb; }

.log-item { padding: 12px; background: #f9fafb; border-radius: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
.log-meta { display: flex; gap: 16px; font-size: 0.85rem; color: #6b7280; }

@media (max-width: 768px) {
    .settings-tabs { flex-direction: column; }
    .score-thresholds { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="ai-settings-container">
    <div class="settings-hero">
        <h1><i class="fas fa-robot me-2"></i>AI Blog Publishing Engine</h1>
        <p>Configure prompts, rules, scoring thresholds, and generation settings for enterprise-grade content</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="settings-tabs">
        <button class="settings-tab active" onclick="showPanel('prompts')"><i class="fas fa-terminal me-2"></i>System Prompts</button>
        <button class="settings-tab" onclick="showPanel('rules')"><i class="fas fa-list-check me-2"></i>Content Rules</button>
        <button class="settings-tab" onclick="showPanel('model')"><i class="fas fa-sliders-h me-2"></i>Model Settings</button>
        <button class="settings-tab" onclick="showPanel('scoring')"><i class="fas fa-chart-bar me-2"></i>Scoring</button>
        <button class="settings-tab" onclick="showPanel('links')"><i class="fas fa-link me-2"></i>Internal Links</button>
        <button class="settings-tab" onclick="showPanel('cta')"><i class="fas fa-bullhorn me-2"></i>CTA Templates</button>
        <button class="settings-tab" onclick="showPanel('logs')"><i class="fas fa-history me-2"></i>Logs</button>
    </div>

    <form method="POST" id="settingsForm">
        <input type="hidden" name="action" value="save_settings">

        <!-- PROMPTS PANEL -->
        <div id="panel-prompts" class="settings-panel active">
            <div class="settings-card">
                <h3><i class="fas fa-code"></i>Master System Prompt</h3>
                <p class="form-hint mb-3">This is the main instruction set sent to the AI. Use dynamic variables below.</p>
                <textarea name="system_prompt" class="prompt-textarea" rows="15"><?= htmlspecialchars($settings['system_prompt'] ?? '') ?></textarea>
                <div class="variables-list">
                    <strong class="me-2">Variables:</strong>
                    <span class="variable-tag" onclick="insertVariable('brand_name')">{{brand_name}}</span>
                    <span class="variable-tag" onclick="insertVariable('website_name')">{{website_name}}</span>
                    <span class="variable-tag" onclick="insertVariable('target_audience')">{{target_audience}}</span>
                    <span class="variable-tag" onclick="insertVariable('blog_category')">{{blog_category}}</span>
                    <span class="variable-tag" onclick="insertVariable('main_keyword')">{{main_keyword}}</span>
                    <span class="variable-tag" onclick="insertVariable('secondary_keywords')">{{secondary_keywords}}</span>
                    <span class="variable-tag" onclick="insertVariable('products')">{{products}}</span>
                    <span class="variable-tag" onclick="insertVariable('internal_links')">{{internal_links}}</span>
                    <span class="variable-tag" onclick="insertVariable('brand_tone')">{{brand_tone}}</span>
                    <span class="variable-tag" onclick="insertVariable('goal')">{{goal}}</span>
                    <span class="variable-tag" onclick="insertVariable('seo_rules')">{{seo_rules}}</span>
                    <span class="variable-tag" onclick="insertVariable('emotional_rules')">{{emotional_rules}}</span>
                    <span class="variable-tag" onclick="insertVariable('conversion_rules')">{{conversion_rules}}</span>
                    <span class="variable-tag" onclick="insertVariable('brand_voice_rules')">{{brand_voice_rules}}</span>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-palette"></i>Brand Voice Rules</h3>
                <textarea name="brand_voice_rules" class="prompt-textarea" rows="6"><?= htmlspecialchars($settings['brand_voice_rules'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- RULES PANEL -->
        <div id="panel-rules" class="settings-panel">
            <div class="row">
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-search"></i>SEO Rules</h3>
                        <textarea name="seo_rules" class="prompt-textarea" rows="10"><?= htmlspecialchars($settings['seo_rules'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-heart"></i>Emotional Writing Rules</h3>
                        <textarea name="emotional_rules" class="prompt-textarea" rows="10"><?= htmlspecialchars($settings['emotional_rules'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-mouse-pointer"></i>CTR Rules</h3>
                        <textarea name="ctr_rules" class="prompt-textarea" rows="8"><?= htmlspecialchars($settings['ctr_rules'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-shopping-cart"></i>Conversion Rules</h3>
                        <textarea name="conversion_rules" class="prompt-textarea" rows="8"><?= htmlspecialchars($settings['conversion_rules'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-book-reader"></i>Readability Rules</h3>
                        <textarea name="readability_rules" class="prompt-textarea" rows="8"><?= htmlspecialchars($settings['readability_rules'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-question-circle"></i>FAQ Rules</h3>
                        <textarea name="faq_rules" class="prompt-textarea" rows="8"><?= htmlspecialchars($settings['faq_rules'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-image"></i>Thumbnail Rules</h3>
                        <textarea name="thumbnail_rules" class="prompt-textarea" rows="6"><?= htmlspecialchars($settings['thumbnail_rules'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="settings-card">
                        <h3><i class="fas fa-code"></i>Schema Rules</h3>
                        <textarea name="schema_rules" class="prompt-textarea" rows="6"><?= htmlspecialchars($settings['schema_rules'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-check-double"></i>Validation Rules</h3>
                <textarea name="validation_rules" class="prompt-textarea" rows="8"><?= htmlspecialchars($settings['validation_rules'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- MODEL SETTINGS PANEL -->
        <div id="panel-model" class="settings-panel">
            <div class="settings-card">
                <h3><i class="fas fa-microchip"></i>AI Model Configuration</h3>
                
                <div class="mb-4">
                    <label class="form-label">AI Model</label>
                    <select name="ai_model" class="form-select">
                        <option value="gemini-1.5-flash" <?= ($settings['ai_model'] ?? '') === 'gemini-1.5-flash' ? 'selected' : '' ?>>Gemini 1.5 Flash</option>
                        <option value="gemini-1.5-pro" <?= ($settings['ai_model'] ?? '') === 'gemini-1.5-pro' ? 'selected' : '' ?>>Gemini 1.5 Pro</option>
                        <option value="gemini-2.0-flash-exp" <?= ($settings['ai_model'] ?? '') === 'gemini-2.0-flash-exp' ? 'selected' : '' ?>>Gemini 2.0 Flash (Experimental)</option>
                        <option value="gpt-4o" <?= ($settings['ai_model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                        <option value="gpt-4-turbo" <?= ($settings['ai_model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                        <option value="claude-3-5-sonnet-20241022" <?= ($settings['ai_model'] ?? '') === 'claude-3-5-sonnet-20241022' ? 'selected' : '' ?>>Claude 3.5 Sonnet</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Temperature (Creativity): <span id="tempValue"><?= $settings['temperature'] ?? 0.75 ?></span></label>
                    <div class="slider-group">
                        <span>0</span>
                        <input type="range" name="temperature" min="0" max="1" step="0.05" value="<?= $settings['temperature'] ?? 0.75 ?>" oninput="document.getElementById('tempValue').textContent = this.value">
                        <span>1</span>
                    </div>
                    <p class="form-hint">Lower = more focused, Higher = more creative</p>
                </div>

                <div class="mb-4">
                    <label class="form-label">Top P: <span id="topPValue"><?= $settings['top_p'] ?? 0.95 ?></span></label>
                    <div class="slider-group">
                        <span>0</span>
                        <input type="range" name="top_p" min="0" max="1" step="0.05" value="<?= $settings['top_p'] ?? 0.95 ?>" oninput="document.getElementById('topPValue').textContent = this.value">
                        <span>1</span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Max Tokens</label>
                        <input type="number" name="max_tokens" class="form-control" value="<?= $settings['max_tokens'] ?? 8000 ?>" min="1000" max="32000">
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Retry Limit</label>
                        <input type="number" name="retry_limit" class="form-control" value="<?= $settings['retry_limit'] ?? 3 ?>" min="1" max="5">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Frequency Penalty: <span id="freqValue"><?= $settings['frequency_penalty'] ?? 0.10 ?></span></label>
                        <input type="range" name="frequency_penalty" min="0" max="2" step="0.1" value="<?= $settings['frequency_penalty'] ?? 0.10 ?>" class="form-range" oninput="document.getElementById('freqValue').textContent = this.value">
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Presence Penalty: <span id="presValue"><?= $settings['presence_penalty'] ?? 0.10 ?></span></label>
                        <input type="range" name="presence_penalty" min="0" max="2" step="0.1" value="<?= $settings['presence_penalty'] ?? 0.10 ?>" class="form-range" oninput="document.getElementById('presValue').textContent = this.value">
                    </div>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-toggle-on"></i>Feature Toggles</h3>
                
                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Auto-Regenerate on Failure</strong>
                        <span>Automatically retry if validation fails</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="auto_regenerate_failed" <?= ($settings['auto_regenerate_failed'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>AI Scoring</strong>
                        <span>Calculate SEO, CTR, emotional, and other scores</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="enable_ai_scoring" <?= ($settings['enable_ai_scoring'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Auto Internal Links</strong>
                        <span>Automatically insert internal links based on rules</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="enable_auto_internal_links" <?= ($settings['enable_auto_internal_links'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Auto CTA Injection</strong>
                        <span>Automatically add CTA blocks to content</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="enable_auto_cta" <?= ($settings['enable_auto_cta'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Schema Generation</strong>
                        <span>Generate schema markup suggestions</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="enable_schema_generation" <?= ($settings['enable_schema_generation'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Thumbnail Prompt Generation</strong>
                        <span>Generate AI image prompts for thumbnails</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="enable_auto_thumbnail_prompt" <?= ($settings['enable_auto_thumbnail_prompt'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Emotional Optimizer</strong>
                        <span>Optimize content for emotional engagement</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="enable_emotional_optimizer" <?= ($settings['enable_emotional_optimizer'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Search Intent Optimizer</strong>
                        <span>Optimize content structure based on search intent</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="enable_search_intent_optimizer" <?= ($settings['enable_search_intent_optimizer'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-group">
                    <div class="toggle-label">
                        <strong>Settings Active</strong>
                        <span>Enable these AI settings</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="is_active" <?= ($settings['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- SCORING PANEL -->
        <div id="panel-scoring" class="settings-panel">
            <div class="settings-card">
                <h3><i class="fas fa-tachometer-alt"></i>Minimum Score Thresholds</h3>
                <p class="form-hint mb-4">Content must meet these minimum scores to pass validation. Set to 0 to disable.</p>
                
                <div class="score-thresholds">
                    <div class="score-input">
                        <label class="form-label">SEO Score</label>
                        <input type="number" name="minimum_seo_score" value="<?= $settings['minimum_seo_score'] ?? 60 ?>" min="0" max="100">
                    </div>
                    <div class="score-input">
                        <label class="form-label">CTR Score</label>
                        <input type="number" name="minimum_ctr_score" value="<?= $settings['minimum_ctr_score'] ?? 60 ?>" min="0" max="100">
                    </div>
                    <div class="score-input">
                        <label class="form-label">Readability Score</label>
                        <input type="number" name="minimum_readability_score" value="<?= $settings['minimum_readability_score'] ?? 60 ?>" min="0" max="100">
                    </div>
                    <div class="score-input">
                        <label class="form-label">Conversion Score</label>
                        <input type="number" name="minimum_conversion_score" value="<?= $settings['minimum_conversion_score'] ?? 50 ?>" min="0" max="100">
                    </div>
                    <div class="score-input">
                        <label class="form-label">Human Score</label>
                        <input type="number" name="minimum_human_score" value="<?= $settings['minimum_human_score'] ?? 65 ?>" min="0" max="100">
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-card" id="saveButtonCard">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Save All Settings</strong>
                    <p class="form-hint mb-0">Changes will apply to all future AI generations</p>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
            </div>
        </div>
    </form>

    <!-- INTERNAL LINKS PANEL -->
    <div id="panel-links" class="settings-panel">
        <div class="settings-card">
            <h3><i class="fas fa-link"></i>Internal Link Rules</h3>
            <p class="form-hint mb-4">Define keywords that should automatically link to specific pages</p>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Keyword</th>
                        <th>Target URL</th>
                        <th>Anchor Text</th>
                        <th>Priority</th>
                        <th>Max Usage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($internalLinks as $link): ?>
                    <tr>
                        <td><?= htmlspecialchars($link['keyword']) ?></td>
                        <td><code><?= htmlspecialchars($link['target_url']) ?></code></td>
                        <td><?= htmlspecialchars($link['anchor_text']) ?></td>
                        <td><?= $link['priority'] ?></td>
                        <td><?= $link['max_usage'] ?></td>
                        <td><span class="badge <?= $link['is_active'] ? 'badge-success' : 'badge-warning' ?>"><?= $link['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline" onclick="editLink(<?= htmlspecialchars(json_encode($link)) ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this rule?')">
                                <input type="hidden" name="action" value="delete_internal_link">
                                <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>
            <h5>Add/Edit Internal Link Rule</h5>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="save_internal_link">
                <input type="hidden" name="link_id" id="linkId" value="0">
                <div class="col-md-3">
                    <label class="form-label">Keyword</label>
                    <input type="text" name="keyword" id="linkKeyword" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target URL</label>
                    <input type="text" name="target_url" id="linkUrl" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Anchor Text</label>
                    <input type="text" name="anchor_text" id="linkAnchor" class="form-control">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Priority</label>
                    <input type="number" name="priority" id="linkPriority" class="form-control" value="5" min="1" max="10">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Max Use</label>
                    <input type="number" name="max_usage" id="linkMaxUsage" class="form-control" value="2" min="1" max="10">
                </div>
                <div class="col-md-1">
                    <label class="form-label">Active</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="link_is_active" id="linkIsActive" class="form-check-input" checked>
                    </div>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CTA TEMPLATES PANEL -->
    <div id="panel-cta" class="settings-panel">
        <div class="settings-card">
            <h3><i class="fas fa-bullhorn"></i>CTA Templates</h3>
            <p class="form-hint mb-4">Define call-to-action blocks that can be injected into blog content</p>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Button Text</th>
                        <th>Style</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ctaTemplates as $cta): ?>
                    <tr>
                        <td><?= htmlspecialchars($cta['title']) ?></td>
                        <td><?= htmlspecialchars($cta['button_text']) ?></td>
                        <td><span class="badge badge-success"><?= $cta['style_type'] ?></span></td>
                        <td><?= $cta['position_rule'] ?></td>
                        <td><span class="badge <?= $cta['is_active'] ? 'badge-success' : 'badge-warning' ?>"><?= $cta['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline" onclick="editCta(<?= htmlspecialchars(json_encode($cta)) ?>)"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this CTA?')">
                                <input type="hidden" name="action" value="delete_cta">
                                <input type="hidden" name="cta_id" value="<?= $cta['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <hr>
            <h5>Add/Edit CTA Template</h5>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="save_cta">
                <input type="hidden" name="cta_id" id="ctaId" value="0">
                <div class="col-md-4">
                    <label class="form-label">Title</label>
                    <input type="text" name="cta_title" id="ctaTitle" class="form-control" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Content</label>
                    <textarea name="cta_content" id="ctaContent" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Button Text</label>
                    <input type="text" name="button_text" id="ctaButtonText" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Button Link</label>
                    <input type="text" name="button_link" id="ctaButtonLink" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Style</label>
                    <select name="style_type" id="ctaStyle" class="form-select">
                        <option value="inline">Inline</option>
                        <option value="banner">Banner</option>
                        <option value="popup">Popup</option>
                        <option value="sticky">Sticky</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Position</label>
                    <select name="position_rule" id="ctaPosition" class="form-select">
                        <option value="after_intro">After Intro</option>
                        <option value="mid_content">Mid Content</option>
                        <option value="before_conclusion">Before Conclusion</option>
                        <option value="after_conclusion">After Conclusion</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Active</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="cta_is_active" id="ctaIsActive" class="form-check-input" checked>
                    </div>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LOGS PANEL -->
    <div id="panel-logs" class="settings-panel">
        <div class="settings-card">
            <h3><i class="fas fa-history"></i>Recent Generation Logs</h3>
            
            <?php if (empty($recentLogs)): ?>
                <p class="text-muted">No generation logs yet.</p>
            <?php else: ?>
                <?php foreach ($recentLogs as $log): ?>
                <div class="log-item">
                    <div>
                        <strong>Log #<?= $log['id'] ?></strong>
                        <?php if ($log['blog_id']): ?>
                            <span class="badge badge-success">Blog #<?= $log['blog_id'] ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="log-meta">
                        <span><i class="fas fa-redo me-1"></i><?= $log['retry_count'] ?> retries</span>
                        <span><i class="fas fa-clock me-1"></i><?= $log['generation_time'] ?>s</span>
                        <span><i class="fas fa-coins me-1"></i><?= number_format($log['token_usage']) ?> tokens</span>
                        <span><i class="fas fa-calendar me-1"></i><?= date('M j, g:i A', strtotime($log['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showPanel(panelId) {
    document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + panelId).classList.add('active');
    event.target.classList.add('active');
    
    // Show/hide save button for form panels
    const formPanels = ['prompts', 'rules', 'model', 'scoring'];
    document.getElementById('saveButtonCard').style.display = formPanels.includes(panelId) ? 'block' : 'none';
}

function insertVariable(varName) {
    const textarea = document.querySelector('textarea[name="system_prompt"]');
    const variable = '{{' + varName + '}}';
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    textarea.value = textarea.value.substring(0, start) + variable + textarea.value.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
}

function editLink(link) {
    document.getElementById('linkId').value = link.id;
    document.getElementById('linkKeyword').value = link.keyword;
    document.getElementById('linkUrl').value = link.target_url;
    document.getElementById('linkAnchor').value = link.anchor_text;
    document.getElementById('linkPriority').value = link.priority;
    document.getElementById('linkMaxUsage').value = link.max_usage;
    document.getElementById('linkIsActive').checked = link.is_active == 1;
}

function editCta(cta) {
    document.getElementById('ctaId').value = cta.id;
    document.getElementById('ctaTitle').value = cta.title;
    document.getElementById('ctaContent').value = cta.content;
    document.getElementById('ctaButtonText').value = cta.button_text;
    document.getElementById('ctaButtonLink').value = cta.button_link;
    document.getElementById('ctaStyle').value = cta.style_type;
    document.getElementById('ctaPosition').value = cta.position_rule;
    document.getElementById('ctaIsActive').checked = cta.is_active == 1;
}
</script>

<?php include __DIR__ . '/admin_footer.php'; ?>
