<?php
/**
 * Website Health & Efficiency Dashboard
 * Admin panel for monitoring and managing website health
 */

session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    header('Location: admin_login.php');
    exit;
}

$pageTitle = 'Website Health & Efficiency Dashboard';
$adminPage = 'health';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle; ?> - Gilaf Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            color: #333; 
            min-height: 100vh;
        }
        
        .header { 
            background: linear-gradient(135deg, #1A3C34 0%, #244A36 50%, #2d5a42 100%); 
            color: white; 
            padding: 25px 40px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(197,160,89,0.1) 0%, transparent 100%);
            pointer-events: none;
        }
        .header h1 { 
            font-size: 1.75rem; 
            font-weight: 700; 
            position: relative;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .header-actions { display: flex; gap: 15px; margin-top: 15px; position: relative; }
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 0.85rem; 
            transition: all 0.2s ease;
            text-transform: capitalize;
        }
        .btn-primary { 
            background: #C5A059; 
            color: white; 
        }
        .btn-primary:hover { 
            background: #b08f4a; 
            opacity: 0.9;
        }
        .btn-secondary { 
            background: #1A3C34; 
            color: white;
        }
        .btn-secondary:hover { 
            background: #244A36;
            opacity: 0.9;
        }
        .btn-danger { 
            background: #1A3C34; 
            color: white; 
        }
        .btn-danger:hover {
            background: #244A36;
            opacity: 0.9;
        }
        .btn-success { 
            background: #16a34a; 
            color: white; 
        }
        .btn-success:hover {
            background: #15803d;
            opacity: 0.9;
        }
        .btn-sm { 
            padding: 8px 16px; 
            font-size: 0.8rem; 
        }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .efficiency-score { 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); 
            border-radius: 16px; 
            padding: 40px; 
            margin-bottom: 30px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            text-align: center;
            border: 1px solid rgba(197,160,89,0.1);
            position: relative;
            overflow: hidden;
        }
        .efficiency-score::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(197,160,89,0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        .score-circle { 
            width: 200px; 
            height: 200px; 
            margin: 0 auto 25px; 
            position: relative;
            background: linear-gradient(135deg, rgba(197,160,89,0.1) 0%, rgba(26,60,52,0.05) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transition: all 0.5s ease;
            animation: pulse 2s ease-in-out infinite;
        }
        .score-circle.excellent {
            background: linear-gradient(135deg, rgba(76,187,23,0.15) 0%, rgba(76,187,23,0.05) 100%);
            box-shadow: 0 8px 32px rgba(76,187,23,0.3);
        }
        .score-circle.good {
            background: linear-gradient(135deg, rgba(245,158,11,0.15) 0%, rgba(245,158,11,0.05) 100%);
            box-shadow: 0 8px 32px rgba(245,158,11,0.3);
        }
        .score-circle.danger {
            background: linear-gradient(135deg, rgba(220,38,38,0.15) 0%, rgba(220,38,38,0.05) 100%);
            box-shadow: 0 8px 32px rgba(220,38,38,0.3);
            animation: pulse-danger 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        @keyframes pulse-danger {
            0%, 100% { transform: scale(1); box-shadow: 0 8px 32px rgba(220,38,38,0.3); }
            50% { transform: scale(1.05); box-shadow: 0 12px 48px rgba(220,38,38,0.5); }
        }
        .score-value { 
            font-size: 3.5rem; 
            font-weight: 800; 
            transition: all 0.5s ease;
        }
        .score-value.excellent {
            color: #4CBB17;
            text-shadow: 0 0 20px rgba(76,187,23,0.3);
        }
        .score-value.good {
            color: #f59e0b;
            text-shadow: 0 0 20px rgba(245,158,11,0.3);
        }
        .score-value.danger {
            color: #dc2626;
            text-shadow: 0 0 20px rgba(220,38,38,0.3);
            animation: glow-danger 1.5s ease-in-out infinite;
        }
        @keyframes glow-danger {
            0%, 100% { text-shadow: 0 0 20px rgba(220,38,38,0.3); }
            50% { text-shadow: 0 0 30px rgba(220,38,38,0.6); }
        }
        .score-label { 
            font-size: 0.95rem; 
            color: #666; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            font-weight: 600;
        }
        
        .metrics-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 24px; 
            margin-bottom: 30px; 
        }
        .metric-card { 
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
            border-radius: 16px; 
            padding: 28px; 
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, var(--accent-color, #C5A059) 50%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .metric-card:hover::before {
            opacity: 1;
        }
        .metric-card h3 { 
            font-size: 0.8rem; 
            color: #888; 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            margin-bottom: 18px; 
            display: flex; 
            align-items: center; 
            gap: 8px;
            font-weight: 700;
        }
        .metric-value { 
            font-size: 2.25rem; 
            font-weight: 800; 
            color: #1A3C34; 
            margin-bottom: 8px;
            line-height: 1;
        }
        .metric-label { 
            font-size: 0.9rem; 
            color: #999; 
            font-weight: 500;
        }
        .metric-icon { 
            width: 48px; 
            height: 48px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.3rem; 
            margin-bottom: 18px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .icon-green { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #16a34a; }
        .icon-blue { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb; }
        .icon-orange { background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); color: #ea580c; }
        .icon-red { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; }
        
        .issues-section { 
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
            border-radius: 16px; 
            padding: 35px; 
            margin-bottom: 30px; 
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .issues-section h2 { 
            font-size: 1.4rem; 
            margin-bottom: 25px; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            font-weight: 700;
            color: #1A3C34;
        }
        .issue-card { 
            border: 1px solid rgba(0,0,0,0.08); 
            border-radius: 12px; 
            padding: 24px; 
            margin-bottom: 16px; 
            display: flex; 
            justify-content: space-between; 
            align-items: start; 
            gap: 20px;
            transition: all 0.3s ease;
            background: white;
        }
        .issue-card:hover {
            border-color: rgba(197,160,89,0.3);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateX(4px);
        }
        .issue-card:last-child { margin-bottom: 0; }
        .issue-content { flex: 1; }
        .issue-title { 
            font-weight: 700; 
            font-size: 1.05rem; 
            margin-bottom: 8px; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            color: #1A3C34;
        }
        .issue-description { 
            color: #666; 
            font-size: 0.9rem; 
            line-height: 1.6;
        }
        .severity-badge { 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 0.7rem; 
            font-weight: 700; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .severity-critical { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; }
        .severity-high { background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); color: #ea580c; }
        .severity-medium { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706; }
        .severity-low { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb; }
        
        .cache-panel { 
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%); 
            border-radius: 16px; 
            padding: 35px; 
            margin-bottom: 30px; 
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .cache-panel h2 { 
            font-size: 1.4rem; 
            margin-bottom: 15px;
            font-weight: 700;
            color: #1A3C34;
        }
        .cache-panel p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .cache-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-top: 25px; 
        }
        .cache-item { 
            border: 1px solid rgba(0,0,0,0.08); 
            border-radius: 12px; 
            padding: 20px; 
            text-align: center;
            background: white;
            transition: all 0.3s ease;
            position: relative;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .cache-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            border-color: rgba(197,160,89,0.3);
            border-top: 3px solid #C5A059;
        }
        .cache-item-label { 
            font-size: 0.8rem; 
            color: #888; 
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .cache-item-value { 
            font-size: 1.4rem; 
            font-weight: 800; 
            color: #1A3C34; 
            margin-bottom: 8px;
            flex-grow: 1;
        }
        .cache-item .btn {
            width: 100%;
            margin-top: auto;
            display: block;
            position: relative;
            z-index: 10;
        }
        
        .loading { text-align: center; padding: 40px; color: #666; }
        .loading i { font-size: 2rem; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .alert { 
            padding: 18px 24px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); 
            color: #16a34a; 
            border: 1px solid #16a34a; 
        }
        .alert-error { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); 
            color: #dc2626; 
            border: 1px solid #dc2626; 
        }
        .alert-info { 
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); 
            color: #2563eb; 
            border: 1px solid #2563eb; 
        }
        .alert i {
            font-size: 1.2rem;
        }
        
        .empty-state { 
            text-align: center; 
            padding: 80px 20px; 
            color: #999;
            background: linear-gradient(135deg, rgba(197,160,89,0.03) 0%, rgba(26,60,52,0.02) 100%);
            border-radius: 12px;
        }
        .empty-state i { 
            font-size: 4rem; 
            margin-bottom: 20px; 
            opacity: 0.2;
            background: linear-gradient(135deg, #1A3C34 0%, #C5A059 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .empty-state p {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .last-updated { 
            font-size: 0.85rem; 
            color: #999; 
            margin-top: 12px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .spinning {
            animation: spin 1s linear infinite;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 35px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease;
            position: relative;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1A3C34;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #999;
            cursor: pointer;
            padding: 5px 10px;
            transition: all 0.3s ease;
            border-radius: 6px;
        }
        .modal-close:hover {
            background: #f5f5f5;
            color: #333;
        }
        .modal-body {
            color: #666;
            line-height: 1.8;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.95rem;
        }
        .detail-value {
            font-weight: 700;
            color: #1A3C34;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-heartbeat"></i> Website Health & Efficiency Dashboard</h1>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="window.location.href='index.php'">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </button>
            <button class="btn btn-primary" id="refreshBtn" onclick="manualRefresh()">
                <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh Data
            </button>
        </div>
    </div>

    <div class="container">
        <!-- Efficiency Score -->
        <div class="efficiency-score">
            <div class="score-circle" id="scoreCircle">
                <div class="score-value" id="scoreValue">--</div>
            </div>
            <div class="score-label">Website Efficiency Score</div>
            <div class="last-updated" id="lastUpdated">Loading...</div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Key Metrics -->
        <div class="metrics-grid" id="metricsGrid">
            <div class="loading"><i class="fas fa-spinner"></i><br>Loading metrics...</div>
        </div>

        <!-- Detected Issues -->
        <div class="issues-section">
            <h2><i class="fas fa-exclamation-triangle"></i> Detected Issues</h2>
            <div id="issuesContainer">
                <div class="loading"><i class="fas fa-spinner"></i><br>Scanning for issues...</div>
            </div>
        </div>

        <!-- Cache Management -->
        <div class="cache-panel">
            <h2><i class="fas fa-database"></i> Cache Management</h2>
            <p style="color: #666; margin-bottom: 20px;">Clear specific cache types or all cache at once. All actions are logged.</p>
            
            <div class="cache-grid">
                <div class="cache-item">
                    <div class="cache-item-label">Full Website Cache</div>
                    <div class="cache-item-value" id="cacheTotal">--</div>
                    <button class="btn btn-danger btn-sm" onclick="clearCache('all')">Clear All</button>
                </div>
                <div class="cache-item">
                    <div class="cache-item-label">Frontend Cache</div>
                    <div class="cache-item-value" id="cacheFrontend">--</div>
                    <button class="btn btn-secondary btn-sm" onclick="clearCache('frontend')">Clear</button>
                </div>
                <div class="cache-item">
                    <div class="cache-item-label">Admin Cache</div>
                    <div class="cache-item-value" id="cacheAdmin">--</div>
                    <button class="btn btn-secondary btn-sm" onclick="clearCache('admin')">Clear</button>
                </div>
                <div class="cache-item">
                    <div class="cache-item-label">API Cache</div>
                    <div class="cache-item-value" id="cacheApi">--</div>
                    <button class="btn btn-secondary btn-sm" onclick="clearCache('api')">Clear</button>
                </div>
                <div class="cache-item">
                    <div class="cache-item-label">Promo Cache</div>
                    <div class="cache-item-value" id="cachePromo">--</div>
                    <button class="btn btn-secondary btn-sm" onclick="clearCache('promo')">Clear</button>
                </div>
                <div class="cache-item">
                    <div class="cache-item-label">Language Cache</div>
                    <div class="cache-item-value" id="cacheLanguage">--</div>
                    <button class="btn btn-secondary btn-sm" onclick="clearCache('language')">Clear</button>
                </div>
                <div class="cache-item">
                    <div class="cache-item-label">Currency Cache</div>
                    <div class="cache-item-value" id="cacheCurrency">--</div>
                    <button class="btn btn-secondary btn-sm" onclick="clearCache('currency')">Clear</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric Details Modal -->
    <div class="modal-overlay" id="metricModal" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 id="modalTitle"><i class="fas fa-chart-line"></i> Metric Details</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Dynamic content will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Cache Clear Modal -->
    <div class="modal-overlay" id="clearModal" onclick="closeClearModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2 id="clearModalTitle"><i class="fas fa-info-circle"></i> Cache Clear</h2>
                <button class="modal-close" onclick="closeClearModal()">&times;</button>
            </div>
            <div class="modal-body" id="clearModalBody">
                <!-- Dynamic content will be inserted here -->
            </div>
        </div>
    </div>

    <script>
        let autoRefreshInterval = null;
        let installationRequired = false;

        // Load all data on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshAllData();
            // Auto-refresh every 30 seconds
            autoRefreshInterval = setInterval(refreshAllData, 30000);
        });

        async function manualRefresh() {
            const btn = document.getElementById('refreshBtn');
            const icon = document.getElementById('refreshIcon');
            
            // Disable button and add spinning animation
            btn.disabled = true;
            icon.classList.add('spinning');
            
            try {
                await refreshAllData();
                
                // Show success message
                showAlert('success', 'Dashboard refreshed successfully!');
                
                // Update last updated time
                document.getElementById('lastUpdated').textContent = 
                    'Last updated: ' + new Date().toLocaleTimeString();
            } catch (error) {
                showAlert('error', 'Failed to refresh dashboard');
            } finally {
                // Re-enable button and stop spinning
                setTimeout(() => {
                    btn.disabled = false;
                    icon.classList.remove('spinning');
                }, 500);
            }
        }
        
        async function refreshAllData() {
            await Promise.all([
                loadHealthMetrics(),
                loadIssues(),
                loadCacheStats()
            ]);
        }

        async function loadHealthMetrics() {
            try {
                const response = await fetch('api/health_api.php?action=get_health_metrics');
                const data = await response.json();

                if (data.success) {
                    updateEfficiencyScore(data.efficiency_score);
                    updateMetricsGrid(data.metrics);
                    document.getElementById('lastUpdated').textContent = 
                        'Last updated: ' + new Date().toLocaleTimeString();
                } else if (data.error && data.error.includes('Table')) {
                    showInstallationRequired();
                }
            } catch (error) {
                console.error('Error loading health metrics:', error);
                showInstallationRequired();
            }
        }
        
        function showInstallationRequired() {
            if (installationRequired) return;
            installationRequired = true;
            
            const alertHtml = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Database tables not found!</strong> 
                    Please run the installation script to create required tables.
                    <a href="install_health_system.php" style="color: #2563eb; text-decoration: underline; margin-left: 10px;">
                        <strong>Install Now</strong>
                    </a>
                </div>
            `;
            document.getElementById('alertContainer').innerHTML = alertHtml;
            
            // Stop auto-refresh
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }

        async function loadIssues() {
            try {
                const response = await fetch('api/health_api.php?action=get_issues');
                const data = await response.json();

                if (data.success) {
                    displayIssues(data.issues);
                }
            } catch (error) {
                console.error('Error loading issues:', error);
            }
        }

        async function loadCacheStats() {
            try {
                const response = await fetch('api/health_api.php?action=get_cache_stats');
                const data = await response.json();

                if (data.success) {
                    updateCacheStats(data.stats);
                }
            } catch (error) {
                console.error('Error loading cache stats:', error);
            }
        }

        function updateEfficiencyScore(score) {
            const scoreValue = document.getElementById('scoreValue');
            const scoreCircle = document.getElementById('scoreCircle');
            
            // Remove all existing classes
            scoreValue.className = 'score-value';
            scoreCircle.className = 'score-circle';
            
            // Add animated classes based on score
            if (score >= 90) {
                scoreValue.classList.add('excellent');
                scoreCircle.classList.add('excellent');
            } else if (score >= 70) {
                scoreValue.classList.add('good');
                scoreCircle.classList.add('good');
            } else {
                scoreValue.classList.add('danger');
                scoreCircle.classList.add('danger');
            }
            
            // Animate the score number
            animateScore(scoreValue, score);
        }
        
        function animateScore(element, targetScore) {
            let currentScore = 0;
            const duration = 1500; // 1.5 seconds
            const increment = targetScore / (duration / 16); // 60fps
            
            const timer = setInterval(() => {
                currentScore += increment;
                if (currentScore >= targetScore) {
                    currentScore = targetScore;
                    clearInterval(timer);
                }
                element.textContent = Math.round(currentScore) + '%';
            }, 16);
        }

        function updateMetricsGrid(metrics) {
            const grid = document.getElementById('metricsGrid');
            
            const html = `
                <div class="metric-card" onclick="showMetricDetails('performance', ${JSON.stringify(metrics.performance).replace(/"/g, '&quot;')})">
                    <div class="metric-icon icon-blue"><i class="fas fa-tachometer-alt"></i></div>
                    <h3>Performance</h3>
                    <div class="metric-value">${metrics.performance.avg_load_time.toFixed(2)}s</div>
                    <div class="metric-label">Avg Page Load Time</div>
                </div>
                <div class="metric-card" onclick="showMetricDetails('server', ${JSON.stringify(metrics.server).replace(/"/g, '&quot;')})">
                    <div class="metric-icon icon-green"><i class="fas fa-server"></i></div>
                    <h3>Server Health</h3>
                    <div class="metric-value">${metrics.server.memory_usage_percent}%</div>
                    <div class="metric-label">Memory Usage</div>
                </div>
                <div class="metric-card" onclick="showMetricDetails('database', ${JSON.stringify(metrics.database).replace(/"/g, '&quot;')})">
                    <div class="metric-icon icon-orange"><i class="fas fa-database"></i></div>
                    <h3>Database</h3>
                    <div class="metric-value">${metrics.database.active_connections}</div>
                    <div class="metric-label">Active Connections</div>
                </div>
                <div class="metric-card" onclick="showMetricDetails('application', ${JSON.stringify(metrics.application).replace(/"/g, '&quot;')})">
                    <div class="metric-icon ${metrics.application.error_rate > 2 ? 'icon-red' : 'icon-green'}">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h3>Application</h3>
                    <div class="metric-value">${metrics.application.error_rate}%</div>
                    <div class="metric-label">Error Rate</div>
                </div>
                <div class="metric-card" onclick="showMetricDetails('cache', ${JSON.stringify(metrics.cache).replace(/"/g, '&quot;')})">
                    <div class="metric-icon icon-blue"><i class="fas fa-layer-group"></i></div>
                    <h3>Cache</h3>
                    <div class="metric-value">${metrics.cache.hit_ratio}%</div>
                    <div class="metric-label">Cache Hit Ratio</div>
                </div>
                <div class="metric-card" onclick="showMetricDetails('disk', ${JSON.stringify(metrics.server).replace(/"/g, '&quot;')})">
                    <div class="metric-icon icon-green"><i class="fas fa-hdd"></i></div>
                    <h3>Disk Usage</h3>
                    <div class="metric-value">${metrics.server.disk_usage_percent}%</div>
                    <div class="metric-label">Disk Space Used</div>
                </div>
            `;
            
            grid.innerHTML = html;
        }

        function displayIssues(issues) {
            const container = document.getElementById('issuesContainer');
            
            if (issues.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No issues detected. System is healthy!</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            issues.forEach(issue => {
                html += `
                    <div class="issue-card">
                        <div class="issue-content">
                            <div class="issue-title">
                                <span class="severity-badge severity-${issue.severity}">${issue.severity}</span>
                                ${issue.title}
                            </div>
                            <div class="issue-description">${issue.description}</div>
                        </div>
                        <button class="btn btn-success btn-sm" onclick="executeFix('${issue.fix_action}', this)">
                            <i class="fas fa-wrench"></i> ${issue.fix_label}
                        </button>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        function updateCacheStats(stats) {
            document.getElementById('cacheTotal').textContent = stats.total_size_formatted;
            document.getElementById('cacheFrontend').textContent = stats.frontend.size_formatted;
            document.getElementById('cacheAdmin').textContent = stats.admin.size_formatted;
            document.getElementById('cacheApi').textContent = stats.api.size_formatted;
            document.getElementById('cachePromo').textContent = stats.promo.size_formatted;
            document.getElementById('cacheLanguage').textContent = stats.language.size_formatted;
            document.getElementById('cacheCurrency').textContent = stats.currency.size_formatted;
        }

        function showMetricDetails(type, data) {
            const modal = document.getElementById('metricModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            let icon = '';
            let title = '';
            let content = '';
            
            switch(type) {
                case 'performance':
                    icon = 'fa-tachometer-alt';
                    title = 'Performance Metrics';
                    content = `
                        <div class="detail-row">
                            <span class="detail-label">Average Load Time</span>
                            <span class="detail-value">${data.avg_load_time.toFixed(2)}s</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Maximum Load Time</span>
                            <span class="detail-value">${data.max_load_time.toFixed(2)}s</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Minimum Load Time</span>
                            <span class="detail-value">${data.min_load_time.toFixed(2)}s</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Time to First Byte (TTFB)</span>
                            <span class="detail-value">${data.ttfb}ms</span>
                        </div>
                    `;
                    break;
                    
                case 'server':
                    icon = 'fa-server';
                    title = 'Server Health';
                    content = `
                        <div class="detail-row">
                            <span class="detail-label">Memory Usage</span>
                            <span class="detail-value">${data.memory_usage_percent}%</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Memory Used</span>
                            <span class="detail-value">${data.memory_used_formatted}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Memory</span>
                            <span class="detail-value">${data.memory_total_formatted}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Server Uptime</span>
                            <span class="detail-value">${data.uptime_formatted}</span>
                        </div>
                    `;
                    break;
                    
                case 'database':
                    icon = 'fa-database';
                    title = 'Database Metrics';
                    content = `
                        <div class="detail-row">
                            <span class="detail-label">Active Connections</span>
                            <span class="detail-value">${data.active_connections}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Slow Queries</span>
                            <span class="detail-value">${data.slow_queries}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Queries</span>
                            <span class="detail-value">${data.total_queries.toLocaleString()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Database Size</span>
                            <span class="detail-value">${data.database_size_formatted}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Table Count</span>
                            <span class="detail-value">${data.table_count}</span>
                        </div>
                    `;
                    break;
                    
                case 'application':
                    icon = 'fa-exclamation-circle';
                    title = 'Application Health';
                    content = `
                        <div class="detail-row">
                            <span class="detail-label">Error Rate</span>
                            <span class="detail-value">${data.error_rate}%</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Errors (Last Hour)</span>
                            <span class="detail-value">${data.total_errors}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Failed API Calls</span>
                            <span class="detail-value">${data.failed_api_calls}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Failed Checkouts</span>
                            <span class="detail-value">${data.failed_checkouts}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Failed Logins</span>
                            <span class="detail-value">${data.failed_logins}</span>
                        </div>
                    `;
                    break;
                    
                case 'cache':
                    icon = 'fa-layer-group';
                    title = 'Cache Performance';
                    content = `
                        <div class="detail-row">
                            <span class="detail-label">Cache Hit Ratio</span>
                            <span class="detail-value">${data.hit_ratio}%</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Cache Hits</span>
                            <span class="detail-value">${data.hits.toLocaleString()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Cache Misses</span>
                            <span class="detail-value">${data.misses.toLocaleString()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Cache Size</span>
                            <span class="detail-value">${data.total_size_formatted}</span>
                        </div>
                    `;
                    break;
                    
                case 'disk':
                    icon = 'fa-hdd';
                    title = 'Disk Usage';
                    content = `
                        <div class="detail-row">
                            <span class="detail-label">Disk Usage</span>
                            <span class="detail-value">${data.disk_usage_percent}%</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Disk Used</span>
                            <span class="detail-value">${data.disk_used_formatted}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Disk Space</span>
                            <span class="detail-value">${data.disk_total_formatted}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Free Space</span>
                            <span class="detail-value">${data.disk_free_formatted}</span>
                        </div>
                    `;
                    break;
            }
            
            modalTitle.innerHTML = `<i class="fas ${icon}"></i> ${title}`;
            modalBody.innerHTML = content;
            modal.classList.add('active');
        }
        
        function closeModal(event) {
            if (event && event.target.classList.contains('modal-content')) {
                return;
            }
            document.getElementById('metricModal').classList.remove('active');
        }

        async function executeFix(fixAction, button) {
            if (!confirm('Are you sure you want to execute this fix? This action will be logged.')) {
                return;
            }
            
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fixing...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'execute_fix');
                formData.append('fix_action', fixAction);
                
                const response = await fetch('api/health_api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert('success', data.message);
                    setTimeout(() => refreshAllData(), 1000);
                } else {
                    showAlert('error', data.message || 'Fix failed');
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-wrench"></i> Fix';
                }
            } catch (error) {
                showAlert('error', 'Error executing fix: ' + error.message);
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-wrench"></i> Fix';
            }
        }

        async function clearCache(cacheType) {
            const cacheNames = {
                'all': 'All Website Cache',
                'frontend': 'Frontend Cache',
                'admin': 'Admin Cache',
                'api': 'API Cache',
                'promo': 'Promo Cache',
                'language': 'Language Cache',
                'currency': 'Currency Cache'
            };
            
            const cacheName = cacheNames[cacheType] || cacheType;
            
            // Show confirmation modal
            showClearConfirmation(cacheName, async () => {
                // Show loading state
                showClearProgress(cacheName);
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'clear_cache');
                    formData.append('cache_type', cacheType);
                    
                    const response = await fetch('api/health_api.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showClearSuccess(cacheName, data.files_cleared);
                        setTimeout(() => loadCacheStats(), 1500);
                    } else {
                        showClearError(data.error || 'Failed to clear cache');
                    }
                } catch (error) {
                    showClearError('Error clearing cache: ' + error.message);
                }
            });
        }
        
        function showClearConfirmation(cacheName, onConfirm) {
            const modal = document.getElementById('clearModal');
            const modalTitle = document.getElementById('clearModalTitle');
            const modalBody = document.getElementById('clearModalBody');
            
            modalTitle.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i> Confirm Cache Clear`;
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 20px 0;">
                    <p style="font-size: 1.1rem; margin-bottom: 20px; color: #666;">
                        Are you sure you want to clear <strong style="color: #1A3C34;">${cacheName}</strong>?
                    </p>
                    <p style="font-size: 0.9rem; color: #999; margin-bottom: 30px;">
                        ${cacheName === 'All Website Cache' ? 'This will temporarily affect all users and clear all cache types.' : 'This action will be logged and cannot be undone.'}
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button class="btn btn-secondary" onclick="closeClearModal()" style="background: #6b7280;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="btn btn-danger" onclick="confirmClearAction()" style="background: #1A3C34;">
                            <i class="fas fa-check"></i> Yes, Clear Cache
                        </button>
                    </div>
                </div>
            `;
            
            modal.classList.add('active');
            window.clearCacheCallback = onConfirm;
        }
        
        function showClearProgress(cacheName) {
            const modalBody = document.getElementById('clearModalBody');
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 40px 20px;">
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #1A3C34;"></i>
                    </div>
                    <p style="font-size: 1.1rem; color: #666; font-weight: 600;">
                        Clearing ${cacheName}...
                    </p>
                    <p style="font-size: 0.9rem; color: #999; margin-top: 10px;">
                        Please wait while we clear the cache files.
                    </p>
                </div>
            `;
        }
        
        function showClearSuccess(cacheName, filesCleared) {
            const modalTitle = document.getElementById('clearModalTitle');
            const modalBody = document.getElementById('clearModalBody');
            
            const message = filesCleared > 0 
                ? `<strong>${filesCleared}</strong> cache files removed successfully.`
                : `Cache was already empty or no cached files found.`;
            
            const subMessage = filesCleared > 0
                ? 'Cache statistics will be updated shortly.'
                : 'The cache directory has been verified and is ready for use.';
            
            modalTitle.innerHTML = `<i class="fas fa-check-circle" style="color: #16a34a;"></i> Cache Cleared Successfully`;
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 30px 20px;">
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: #16a34a;"></i>
                    </div>
                    <p style="font-size: 1.2rem; color: #1A3C34; font-weight: 700; margin-bottom: 15px;">
                        ${cacheName} Cleared!
                    </p>
                    <p style="font-size: 1rem; color: #666; margin-bottom: 10px;">
                        ${message}
                    </p>
                    <p style="font-size: 0.9rem; color: #999; margin-bottom: 30px;">
                        ${subMessage}
                    </p>
                    <button class="btn btn-primary" onclick="closeClearModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
            
            setTimeout(() => closeClearModal(), 3000);
        }
        
        function showClearError(errorMsg) {
            const modalTitle = document.getElementById('clearModalTitle');
            const modalBody = document.getElementById('clearModalBody');
            
            modalTitle.innerHTML = `<i class="fas fa-exclamation-circle" style="color: #dc2626;"></i> Error`;
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 30px 20px;">
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle" style="font-size: 4rem; color: #dc2626;"></i>
                    </div>
                    <p style="font-size: 1.1rem; color: #dc2626; font-weight: 600; margin-bottom: 15px;">
                        Failed to Clear Cache
                    </p>
                    <p style="font-size: 0.95rem; color: #666; margin-bottom: 30px;">
                        ${errorMsg}
                    </p>
                    <button class="btn btn-primary" onclick="closeClearModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        }
        
        function confirmClearAction() {
            if (window.clearCacheCallback) {
                window.clearCacheCallback();
            }
        }
        
        function closeClearModal() {
            document.getElementById('clearModal').classList.remove('active');
            window.clearCacheCallback = null;
        }

        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            const alert = document.createElement('div');
            alert.className = `alert ${alertClass}`;
            alert.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
            
            container.appendChild(alert);
            
            setTimeout(() => alert.remove(), 5000);
        }
    </script>
</body>
</html>
