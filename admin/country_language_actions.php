<?php
/**
 * Country & Language Management Actions
 * Backend API for admin country and language management
 */

session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/region_detection.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'save_excluded_countries':
            $excludedCountriesJson = $_POST['excluded_countries'] ?? '[]';
            $excludedCountries = json_decode($excludedCountriesJson, true);
            
            if (!is_array($excludedCountries)) {
                throw new Exception('Invalid excluded countries data');
            }
            
            $result = save_excluded_countries($excludedCountries);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Excluded countries saved successfully',
                    'count' => count($excludedCountries)
                ]);
            } else {
                throw new Exception('Failed to save excluded countries');
            }
            break;
            
        case 'get_excluded_countries':
            $excludedCountries = get_excluded_countries();
            
            echo json_encode([
                'success' => true,
                'excluded_countries' => $excludedCountries
            ]);
            break;
            
        case 'reset_excluded_countries':
            require_once __DIR__ . '/../includes/global_countries.php';
            $defaultExcluded = get_default_excluded_countries();
            
            $result = save_excluded_countries($defaultExcluded);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Reset to default excluded countries',
                    'excluded_countries' => $defaultExcluded
                ]);
            } else {
                throw new Exception('Failed to reset excluded countries');
            }
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
