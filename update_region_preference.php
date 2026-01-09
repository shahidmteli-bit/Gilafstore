<?php
/**
 * Update Region Preference API
 * Handles user's country, currency, and language preference updates
 */

session_start();
require_once __DIR__ . '/includes/region_detection.php';
require_once __DIR__ . '/includes/currency_converter.php';
require_once __DIR__ . '/includes/language_manager.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_preference':
            $countryCode = strtoupper($_POST['country_code'] ?? '');
            
            if (empty($countryCode)) {
                throw new Exception('Country code is required');
            }
            
            $countryData = get_country_data($countryCode);
            if (!$countryData) {
                throw new Exception('Invalid country code');
            }
            
            // Save preference
            $userId = $_SESSION['user']['id'] ?? null;
            save_country_preference($countryCode, $userId);
            
            // Clear currency-specific cache
            require_once __DIR__ . '/includes/auto_cache_clear.php';
            clearCacheOnCurrencyChange($countryData['currency'], $userId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Region preference updated',
                'country' => $countryData
            ]);
            break;
            
        case 'update_language':
            $langCode = strtolower($_POST['language_code'] ?? '');
            
            if (empty($langCode)) {
                throw new Exception('Language code is required');
            }
            
            $userId = $_SESSION['user']['id'] ?? null;
            $result = save_language_preference($langCode, $userId);
            
            if (!$result) {
                throw new Exception('Invalid language code');
            }
            
            confirm_language_selection();
            
            // Clear language-specific cache
            require_once __DIR__ . '/includes/auto_cache_clear.php';
            clearCacheOnLanguageChange($langCode, $userId);
            
            $languageData = get_language_data($langCode);
            
            echo json_encode([
                'success' => true,
                'message' => 'Language preference updated',
                'language' => $languageData
            ]);
            break;
            
        case 'confirm_detection':
            confirm_auto_detection();
            
            $countryCode = $_POST['country_code'] ?? null;
            if ($countryCode) {
                $userId = $_SESSION['user']['id'] ?? null;
                save_country_preference($countryCode, $userId);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Auto-detection confirmed'
            ]);
            break;
            
        case 'dismiss_detection':
            confirm_auto_detection();
            
            echo json_encode([
                'success' => true,
                'message' => 'Auto-detection dismissed'
            ]);
            break;
            
        case 'get_current_region':
            $settings = get_user_region_settings();
            
            echo json_encode([
                'success' => true,
                'region' => $settings
            ]);
            break;
            
        case 'update_exchange_rates':
            // Manual trigger to update exchange rates
            $updated = update_all_exchange_rates();
            
            echo json_encode([
                'success' => true,
                'message' => "Updated {$updated} exchange rates"
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
