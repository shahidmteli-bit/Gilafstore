<?php
/**
 * Region Auto-Detection Confirmation Banner
 * Shows a non-intrusive banner when region is auto-detected
 */

require_once __DIR__ . '/region_detection.php';

$regionSettings = get_user_region_settings();
$showBanner = $regionSettings['auto_detected'] && !has_confirmed_auto_detection();
$country = $regionSettings['country'];
?>

<?php if ($showBanner): ?>
<div id="regionDetectionBanner" style="
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: linear-gradient(135deg, #244A36 0%, #2d5a42 100%);
    color: white;
    padding: 16px 20px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    z-index: 9999;
    max-width: 400px;
    animation: slideInUp 0.5s ease-out;
    font-family: 'Poppins', sans-serif;
">
    <div style="display: flex; align-items: start; gap: 12px;">
        <div style="flex-shrink: 0; font-size: 24px;">🌍</div>
        <div style="flex: 1;">
            <div style="font-weight: 600; font-size: 14px; margin-bottom: 6px;">
                We detected you're in <?= htmlspecialchars($country['name']); ?>
            </div>
            <div style="font-size: 12px; opacity: 0.9; margin-bottom: 12px;">
                Switch to <?= htmlspecialchars($country['language_name']); ?> and show prices in <?= htmlspecialchars($country['currency_symbol']); ?> <?= htmlspecialchars($country['currency']); ?>?
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="confirmRegionDetection('<?= $country['code']; ?>')" style="
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
                <button onclick="dismissRegionDetection()" style="
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
                    No, Keep INR
                </button>
            </div>
        </div>
        <button onclick="dismissRegionDetection()" style="
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
    @keyframes slideInUp {
        from {
            transform: translateY(100px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutDown {
        from {
            transform: translateY(0);
            opacity: 1;
        }
        to {
            transform: translateY(100px);
            opacity: 0;
        }
    }
    
    @media (max-width: 768px) {
        #regionDetectionBanner {
            bottom: 10px;
            right: 10px;
            left: 10px;
            max-width: none;
        }
    }
</style>

<script>
async function confirmRegionDetection(countryCode) {
    const banner = document.getElementById('regionDetectionBanner');
    banner.style.animation = 'slideOutDown 0.3s ease-in';
    
    try {
        const formData = new FormData();
        formData.append('action', 'confirm_detection');
        formData.append('country_code', countryCode);
        
        const response = await fetch('update_region_preference.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            setTimeout(() => {
                window.location.reload();
            }, 300);
        }
    } catch (error) {
        console.error('Error confirming region:', error);
    }
}

async function dismissRegionDetection() {
    const banner = document.getElementById('regionDetectionBanner');
    banner.style.animation = 'slideOutDown 0.3s ease-in';
    
    try {
        const formData = new FormData();
        formData.append('action', 'dismiss_detection');
        
        await fetch('update_region_preference.php', {
            method: 'POST',
            body: formData
        });
        
        setTimeout(() => {
            banner.remove();
        }, 300);
    } catch (error) {
        console.error('Error dismissing banner:', error);
        setTimeout(() => {
            banner.remove();
        }, 300);
    }
}
</script>
<?php endif; ?>
