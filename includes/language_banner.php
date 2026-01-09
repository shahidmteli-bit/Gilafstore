<?php
/**
 * Language Suggestion Banner
 * Shows a one-time banner suggesting switching to detected browser language
 * Only shows when user hasn't manually selected a language yet and
 * the suggested language differs from current language.
 */

require_once __DIR__ . '/language_manager.php';

$currentLanguage = get_user_language();
$suggestedLanguage = get_browser_language_suggestion();

$hasManual = has_manual_language_selection();
$alreadyConfirmed = isset($_COOKIE['language_selection_confirmed']);

$showBanner = !$hasManual && !$alreadyConfirmed && $suggestedLanguage && ($suggestedLanguage['code'] !== $currentLanguage['code']);
?>

<?php if ($showBanner): ?>
<div id="languageSuggestionBanner" style="
    position: fixed;
    bottom: 20px;
    left: 20px;
    background: linear-gradient(135deg, #244A36 0%, #2d5a42 100%);
    color: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    z-index: 9999;
    max-width: 420px;
    animation: langSlideIn 0.5s ease-out;
    font-family: 'Poppins', sans-serif;
">
    <div style="display: flex; align-items: start; gap: 12px;">
        <div style="flex-shrink: 0; font-size: 24px;">🈯</div>
        <div style="flex: 1;">
            <div style="font-weight: 600; font-size: 14px; margin-bottom: 6px;">
                We detected your browser language is <?= htmlspecialchars($suggestedLanguage['native_name']); ?>.
            </div>
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 12px;">
                Would you like to switch the site language to <?= htmlspecialchars($suggestedLanguage['name']); ?>?
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="confirmLanguageSwitch('<?= $suggestedLanguage['code']; ?>')" style="
                    background: rgba(197, 160, 89, 1);
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                " onmouseover="this.style.background='rgba(197, 160, 89, 0.9)'" onmouseout="this.style.background='rgba(197, 160, 89, 1)'">
                    Yes, Switch
                </button>
                <button onclick="dismissLanguageSuggestion()" style="
                    background: transparent;
                    color: white;
                    border: 1px solid rgba(255,255,255,0.3);
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                " onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='transparent'">
                    No, Keep Current
                </button>
            </div>
        </div>
        <button onclick="dismissLanguageSuggestion()" style="
            background: transparent;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            opacity: 0.7;
            transition: opacity 0.2s;
        " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">×</button>
    </div>
</div>

<style>
@keyframes langSlideIn {
    from { transform: translateY(100px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
@keyframes langSlideOut {
    from { transform: translateY(0); opacity: 1; }
    to { transform: translateY(100px); opacity: 0; }
}
@media (max-width: 768px) {
    #languageSuggestionBanner { left: 10px; right: 10px; max-width: none; }
}
</style>

<script>
async function confirmLanguageSwitch(langCode) {
    const banner = document.getElementById('languageSuggestionBanner');
    if (banner) banner.style.animation = 'langSlideOut 0.3s ease-in';

    try {
        const formData = new FormData();
        formData.append('action', 'update_language');
        formData.append('language_code', langCode);

        const response = await fetch('update_region_preference.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result && result.success) {
            // Mark as confirmed so we never ask again
            document.cookie = 'language_selection_confirmed=1; path=/; max-age=' + (365*24*60*60);
            setTimeout(() => { window.location.reload(); }, 300);
        }
    } catch (e) {
        console.error('Language switch error:', e);
    }
}

function dismissLanguageSuggestion() {
    const banner = document.getElementById('languageSuggestionBanner');
    if (banner) banner.style.animation = 'langSlideOut 0.3s ease-in';
    // Set a cookie so we never ask again
    document.cookie = 'language_selection_confirmed=1; path=/; max-age=' + (365*24*60*60);
    setTimeout(() => { if (banner) banner.remove(); }, 300);
}
</script>
<?php endif; ?>
