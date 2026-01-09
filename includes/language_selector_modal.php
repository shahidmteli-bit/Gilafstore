<?php
/**
 * Language Selector Modal
 * Replaces manual region selection with language-first approach
 */

require_once __DIR__ . '/language_manager.php';
require_once __DIR__ . '/region_detection.php';

$currentLanguage = get_user_language();
$currentRegion = get_user_region_settings();
$supportedLanguages = get_supported_languages();
?>

<!-- Language Selector Modal -->
<div class="modal-overlay" id="languageModal">
    <div class="language-modal" style="max-width: 480px; max-height: 75vh; overflow-y: auto;">
        <div class="modal-close" onclick="closeLanguageModal()">&times;</div>
        
        <h3 style="color: var(--color-green); margin-bottom: 8px; text-align: center; font-size: 1.1rem;">
            <i class="fas fa-language"></i> <?= get_translated_content('change_language'); ?>
        </h3>
        
        <p style="text-align: center; color: #666; font-size: 0.8rem; margin-bottom: 10px;">
            Select your preferred language
        </p>
        
        <!-- Current Selection - Compact Line -->
        <div style="padding: 8px 12px; margin-bottom: 10px; border-left: 3px solid var(--color-gold); background: #fafafa; display: flex; align-items: center; gap: 10px;">
            <img src="https://flagcdn.com/<?= $currentLanguage['flag']; ?>" width="22" style="border-radius: 2px;" alt="<?= $currentLanguage['code']; ?>">
            <span style="font-weight: 600; color: #333; font-size: 0.9rem;">
                Current: <?= htmlspecialchars($currentLanguage['native_name']); ?>
            </span>
            <span style="font-size: 0.85rem; color: #888; font-weight: 400;">
                <?= htmlspecialchars($currentLanguage['name']); ?>
            </span>
        </div>
        
        <!-- Language List - Uniform Size -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
            <?php foreach ($supportedLanguages as $langCode => $language): ?>
                <div class="language-option <?= $langCode === $currentLanguage['code'] ? 'selected' : ''; ?>" 
                     onclick="selectLanguage('<?= $langCode; ?>', '<?= htmlspecialchars($language['native_name']); ?>')"
                     style="
                        padding: 8px 12px;
                        border: 1px solid <?= $langCode === $currentLanguage['code'] ? 'var(--color-gold)' : '#ddd'; ?>;
                        border-radius: 4px;
                        cursor: pointer;
                        transition: all 0.2s;
                        background: <?= $langCode === $currentLanguage['code'] ? 'rgba(197, 160, 89, 0.05)' : 'white'; ?>;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        height: 38px;
                     "
                     onmouseover="if (!this.classList.contains('selected')) { this.style.borderColor='var(--color-gold)'; this.style.background='rgba(197, 160, 89, 0.03)'; }"
                     onmouseout="if (!this.classList.contains('selected')) { this.style.borderColor='#ddd'; this.style.background='white'; }">
                    <img src="https://flagcdn.com/<?= $language['flag']; ?>" 
                         width="22" 
                         style="border-radius: 2px; flex-shrink: 0;" 
                         alt="<?= $langCode; ?>">
                    <div style="flex: 1; display: flex; align-items: center; gap: 8px;">
                        <span style="font-weight: 600; font-size: 0.9rem; color: #333;">
                            <?= htmlspecialchars($language['native_name']); ?>
                        </span>
                        <span style="font-size: 0.85rem; color: #888;">
                            <?= htmlspecialchars($language['name']); ?>
                        </span>
                    </div>
                    <?php if ($langCode === $currentLanguage['code']): ?>
                        <i class="fas fa-check-circle" style="color: var(--color-gold); font-size: 0.9rem; flex-shrink: 0;"></i>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Currency Info -->
        <div style="margin-top: 12px; padding: 10px; background: #f0f7f4; border-radius: 6px; border: 1px solid #d0e8dd;">
            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                <i class="fas fa-info-circle" style="color: var(--color-green); font-size: 0.85rem;"></i>
                <strong style="color: var(--color-green); font-size: 0.8rem;">Currency</strong>
            </div>
            <div style="font-size: 0.75rem; color: #555;">
                Location: <?= htmlspecialchars($currentRegion['country']['name']); ?> | 
                <strong><?= htmlspecialchars($currentRegion['currency']); ?> (<?= htmlspecialchars($currentRegion['currency_symbol']); ?>)</strong>
            </div>
        </div>
    </div>
</div>

<style>
    .language-modal {
        background: white;
        padding: 20px;
        border-radius: 10px;
        position: relative;
        animation: modalSlideIn 0.3s ease-out;
    }
    
    .language-option {
        position: relative;
    }
    
    .language-option.selected {
        box-shadow: 0 2px 8px rgba(197, 160, 89, 0.3);
    }
    
    @keyframes modalSlideIn {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @media (max-width: 768px) {
        .language-modal {
            padding: 16px;
            max-width: 95%;
        }
        
        .language-modal > div[style*="grid"] {
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)) !important;
        }
    }
</style>

<script>
// Open language modal
function openLanguageModal() {
    const modal = document.getElementById('languageModal');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

// Close language modal
function closeLanguageModal() {
    const modal = document.getElementById('languageModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

// Select language
async function selectLanguage(langCode, langName) {
    try {
        const formData = new FormData();
        formData.append('action', 'update_language');
        formData.append('language_code', langCode);
        
        const response = await fetch('update_region_preference.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success message
            showLanguageChangeNotification(langName);
            
            // Reload page after short delay
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } else {
            alert('Error updating language: ' + result.message);
        }
    } catch (error) {
        console.error('Error selecting language:', error);
        alert('Error updating language preference');
    }
}

// Show language change notification
function showLanguageChangeNotification(langName) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #244A36 0%, #2d5a42 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
        font-family: 'Poppins', sans-serif;
    `;
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-check-circle" style="color: var(--color-gold); font-size: 1.2rem;"></i>
            <div>
                <div style="font-weight: 600; font-size: 0.95rem;">Language Updated</div>
                <div style="font-size: 0.85rem; opacity: 0.9;">Switched to ${langName}</div>
            </div>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
    }, 2000);
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const langModal = document.getElementById('languageModal');
    if (event.target === langModal) {
        closeLanguageModal();
    }
});
</script>

<style>
    @keyframes slideInRight {
        from {
            transform: translateX(100px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100px);
            opacity: 0;
        }
    }
</style>
