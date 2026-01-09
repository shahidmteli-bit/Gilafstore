<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/global_countries.php';
require_once __DIR__ . '/../includes/region_detection.php';
require_once __DIR__ . '/../includes/language_manager.php';

// Check admin authentication
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    header('Location: ' . base_url('admin/admin_login.php'));
    exit;
}

$pageTitle = 'Manage Countries & Languages — Admin';
$activePage = 'countries_languages';

// Get all countries and current exclusions
$allCountries = get_all_countries();
$excludedCountries = get_excluded_countries();
$supportedLanguages = get_supported_languages();

// Group countries by region
$countryByRegion = [];
foreach ($allCountries as $code => $country) {
    $region = $country['region'] ?? 'Other';
    $countryByRegion[$region][] = $country;
}

// Count statistics
$totalCountries = count($allCountries);
$activeCountries = $totalCountries - count($excludedCountries);
$totalLanguages = count($supportedLanguages);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-left: 4px solid var(--color-gold);
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-green);
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }
    
    .region-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .region-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .country-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 10px;
    }
    
    .country-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        background: #f9f9f9;
        transition: all 0.2s;
    }
    
    .country-item:hover {
        background: #f0f0f0;
    }
    
    .country-item.excluded {
        background: #ffe6e6;
        border-color: #ffcccc;
    }
    
    .country-item.excluded:hover {
        background: #ffd9d9;
    }
    
    .language-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .language-card {
        background: white;
        padding: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        text-align: center;
        transition: all 0.2s;
    }
    
    .language-card:hover {
        border-color: var(--color-gold);
        box-shadow: 0 4px 12px rgba(197, 160, 89, 0.2);
    }
</style>

<div class="container" style="padding: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h2 style="color: var(--color-green); margin: 0;">
            <i class="fas fa-globe"></i> Countries & Languages Management
        </h2>
        <div>
            <button onclick="saveExcludedCountries()" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $totalCountries; ?></div>
            <div class="stat-label">Total Countries</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $activeCountries; ?></div>
            <div class="stat-label">Active Countries</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($excludedCountries); ?></div>
            <div class="stat-label">Excluded Countries</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalLanguages; ?></div>
            <div class="stat-label">Supported Languages</div>
        </div>
    </div>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs" style="margin-bottom: 20px;">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#countries-tab">
                <i class="fas fa-flag"></i> Countries
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#languages-tab">
                <i class="fas fa-language"></i> Languages
            </a>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Countries Tab -->
        <div class="tab-pane fade show active" id="countries-tab">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Country Management:</strong> Click on a country to toggle its exclusion status. 
                Excluded countries will not be available for auto-detection or manual selection.
            </div>
            
            <?php foreach ($countryByRegion as $region => $countries): ?>
                <div class="region-section">
                    <div class="region-header">
                        <h4 style="margin: 0; color: var(--color-green);">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($region); ?>
                        </h4>
                        <span class="badge bg-secondary"><?= count($countries); ?> countries</span>
                    </div>
                    
                    <div class="country-grid">
                        <?php foreach ($countries as $country): 
                            $isExcluded = in_array($country['code'], $excludedCountries);
                        ?>
                            <div class="country-item <?= $isExcluded ? 'excluded' : ''; ?>" 
                                 data-country-code="<?= $country['code']; ?>"
                                 onclick="toggleCountryExclusion('<?= $country['code']; ?>')"
                                 style="cursor: pointer;">
                                <input type="checkbox" 
                                       class="form-check-input country-checkbox" 
                                       <?= $isExcluded ? 'checked' : ''; ?>
                                       onchange="event.stopPropagation();">
                                <img src="https://flagcdn.com/<?= strtolower($country['code']); ?>.svg" 
                                     width="24" 
                                     style="border-radius: 3px;" 
                                     alt="<?= $country['code']; ?>">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 0.9rem;">
                                        <?= htmlspecialchars($country['name']); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #666;">
                                        <?= $country['currency']; ?> (<?= $country['currency_symbol']; ?>)
                                    </div>
                                </div>
                                <?php if ($isExcluded): ?>
                                    <i class="fas fa-ban" style="color: #dc3545;"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Languages Tab -->
        <div class="tab-pane fade" id="languages-tab">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Language Support:</strong> These languages are available for manual selection by users. 
                Languages are automatically mapped to countries based on their primary language.
            </div>
            
            <div class="language-grid">
                <?php foreach ($supportedLanguages as $langCode => $language): ?>
                    <div class="language-card">
                        <img src="https://flagcdn.com/<?= $language['flag']; ?>" 
                             width="48" 
                             style="border-radius: 6px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" 
                             alt="<?= $langCode; ?>">
                        <div style="font-weight: 600; font-size: 1rem; margin-bottom: 5px;">
                            <?= htmlspecialchars($language['native_name']); ?>
                        </div>
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 8px;">
                            <?= htmlspecialchars($language['name']); ?>
                        </div>
                        <div style="font-size: 0.75rem; color: #999;">
                            Code: <strong><?= strtoupper($langCode); ?></strong>
                        </div>
                        <div style="margin-top: 10px;">
                            <span class="badge" style="background: <?= $language['direction'] === 'rtl' ? '#ff9800' : '#4caf50'; ?>;">
                                <?= $language['direction'] === 'rtl' ? 'RTL' : 'LTR'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="alert alert-warning" style="margin-top: 30px;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Note:</strong> To add or modify languages, edit the <code>includes/language_manager.php</code> file. 
                Language translations can be managed in the same file.
            </div>
        </div>
    </div>
</div>

<script>
// Track excluded countries
let excludedCountries = <?= json_encode($excludedCountries); ?>;

// Toggle country exclusion
function toggleCountryExclusion(countryCode) {
    const item = document.querySelector(`[data-country-code="${countryCode}"]`);
    const checkbox = item.querySelector('.country-checkbox');
    
    if (excludedCountries.includes(countryCode)) {
        // Remove from excluded
        excludedCountries = excludedCountries.filter(c => c !== countryCode);
        item.classList.remove('excluded');
        checkbox.checked = false;
    } else {
        // Add to excluded
        excludedCountries.push(countryCode);
        item.classList.add('excluded');
        checkbox.checked = true;
    }
}

// Save excluded countries
async function saveExcludedCountries() {
    try {
        const formData = new FormData();
        formData.append('action', 'save_excluded_countries');
        formData.append('excluded_countries', JSON.stringify(excludedCountries));
        
        const response = await fetch('country_language_actions.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Excluded countries saved successfully!');
        } else {
            showNotification('error', 'Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving excluded countries:', error);
        showNotification('error', 'Error saving excluded countries');
    }
}

// Show notification
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        animation: slideInRight 0.3s ease-out;
    `;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
