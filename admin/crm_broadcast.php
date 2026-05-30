<?php
/**
 * WhatsApp Broadcast Manager
 * Send bulk messages to customer segments
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/crm_engine.php';

require_admin();

$crm = CRMEngine::getInstance();
$pageTitle = 'Broadcast Messages — CRM';
$adminPage = 'crm_broadcast';

global $pdo;

// Handle form submission
$success = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_broadcast') {
        $name = trim($_POST['name'] ?? '');
        $templateKey = $_POST['template'] ?? '';
        $segment = $_POST['segment'] ?? 'all';
        $scheduleAt = $_POST['schedule_at'] ?? null;
        
        if (!$name || !$templateKey) {
            $error = 'Name and template are required';
        } else {
            // Get recipients based on segment
            switch ($segment) {
                case 'recent_buyers':
                    $recipientQuery = "SELECT DISTINCT u.id, u.phone, u.name FROM users u INNER JOIN orders o ON o.user_id = u.id WHERE o.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) AND u.phone IS NOT NULL";
                    break;
                case 'abandoned_cart':
                    $recipientQuery = "SELECT DISTINCT u.id, u.phone, u.name FROM users u INNER JOIN crm_abandoned_carts c ON c.user_id = u.id WHERE c.recovery_status = 'active' AND u.phone IS NOT NULL";
                    break;
                case 'high_value':
                    $recipientQuery = "SELECT u.id, u.phone, u.name FROM users u INNER JOIN orders o ON o.user_id = u.id WHERE u.phone IS NOT NULL GROUP BY u.id HAVING SUM(o.total_amount) > 5000";
                    break;
                case 'inactive':
                    $recipientQuery = "SELECT u.id, u.phone, u.name FROM users u LEFT JOIN orders o ON o.user_id = u.id WHERE u.phone IS NOT NULL GROUP BY u.id HAVING MAX(o.created_at) < DATE_SUB(NOW(), INTERVAL 60 DAY) OR MAX(o.created_at) IS NULL";
                    break;
                default:
                    $recipientQuery = "SELECT id, phone, name FROM users WHERE phone IS NOT NULL AND phone != ''";
            }
            
            $recipients = $pdo->query($recipientQuery)->fetchAll(PDO::FETCH_ASSOC);
            
            // Create broadcast record
            $pdo->prepare("
                INSERT INTO crm_broadcasts (name, template_key, segment, recipient_count, status, scheduled_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $name, $templateKey, $segment, count($recipients),
                $scheduleAt ? 'scheduled' : 'pending',
                $scheduleAt ?: null,
                $_SESSION['user']['id']
            ]);
            $broadcastId = $pdo->lastInsertId();
            
            // Queue recipients
            $insertRecipient = $pdo->prepare("
                INSERT INTO crm_broadcast_recipients (broadcast_id, user_id, phone, status) VALUES (?, ?, ?, 'pending')
            ");
            foreach ($recipients as $r) {
                $insertRecipient->execute([$broadcastId, $r['id'], $r['phone']]);
            }
            
            $success = "Broadcast created with " . count($recipients) . " recipients";
        }
    }
    
    if ($action === 'send_broadcast') {
        $broadcastId = (int)($_POST['broadcast_id'] ?? 0);
        
        // Get broadcast
        $broadcast = $pdo->prepare("SELECT * FROM crm_broadcasts WHERE id = ?")->fetch([$broadcastId]);
        if ($broadcast && $broadcast['status'] === 'pending') {
            // Update status
            $pdo->prepare("UPDATE crm_broadcasts SET status = 'sending', started_at = NOW() WHERE id = ?")->execute([$broadcastId]);
            
            // Queue events for processing
            $recipients = $pdo->prepare("SELECT * FROM crm_broadcast_recipients WHERE broadcast_id = ? AND status = 'pending'");
            $recipients->execute([$broadcastId]);
            
            $sent = 0;
            foreach ($recipients->fetchAll() as $r) {
                $result = $crm->sendNotification($r['phone'], $broadcast['template_key'], []);
                $status = $result ? 'sent' : 'failed';
                $pdo->prepare("UPDATE crm_broadcast_recipients SET status = ?, sent_at = NOW() WHERE id = ?")->execute([$status, $r['id']]);
                if ($result) $sent++;
            }
            
            // Update broadcast
            $pdo->prepare("UPDATE crm_broadcasts SET status = 'completed', completed_at = NOW(), sent_count = ? WHERE id = ?")->execute([$sent, $broadcastId]);
            
            $success = "Broadcast sent to $sent recipients";
        }
    }
}

// Get templates
$templates = $pdo->query("SELECT template_key, template_name FROM crm_notification_templates WHERE is_active = 1 ORDER BY template_name")->fetchAll();

// Get recent broadcasts
$broadcasts = $pdo->query("
    SELECT b.*, u.name as created_by_name
    FROM crm_broadcasts b
    LEFT JOIN users u ON b.created_by = u.id
    ORDER BY b.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Get segment counts
$segments = [
    'all' => ['label' => 'All Customers', 'count' => $pdo->query("SELECT COUNT(*) FROM users WHERE phone IS NOT NULL")->fetchColumn()],
    'recent_buyers' => ['label' => 'Recent Buyers (30d)', 'count' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn()],
    'abandoned_cart' => ['label' => 'Abandoned Cart', 'count' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM crm_abandoned_carts WHERE recovery_status = 'active'")->fetchColumn()],
    'high_value' => ['label' => 'High Value (₹5000+)', 'count' => $pdo->query("SELECT COUNT(DISTINCT user_id) FROM orders GROUP BY user_id HAVING SUM(total_amount) > 5000")->fetchColumn() ?: 0],
    'inactive' => ['label' => 'Inactive (60d+)', 'count' => 0],
];

require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.broadcast-grid { display: grid; grid-template-columns: 400px 1fr; gap: 24px; }
.create-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.create-card h3 { font-size: 1.1rem; font-weight: 600; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.create-card h3 i { color: #25D366; }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.9rem; font-weight: 500; color: #374151; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.95rem; }
.form-group input:focus, .form-group select:focus { outline: none; border-color: #25D366; }

.segment-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px; }
.segment-card { border: 2px solid #e5e7eb; border-radius: 10px; padding: 12px; cursor: pointer; transition: all 0.2s; }
.segment-card:hover { border-color: #25D366; }
.segment-card.selected { border-color: #25D366; background: #f0fff4; }
.segment-card input { display: none; }
.segment-card .seg-label { font-weight: 600; font-size: 0.9rem; color: #1f2937; }
.segment-card .seg-count { font-size: 0.8rem; color: #6b7280; }

.submit-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #25D366, #128C7E); color: #fff; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.submit-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(37,211,102,0.3); }

.broadcasts-list { background: #fff; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); overflow: hidden; }
.broadcasts-list h3 { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; margin: 0; font-size: 1.1rem; }
.broadcast-item { display: flex; align-items: center; gap: 16px; padding: 16px 24px; border-bottom: 1px solid #f3f4f6; }
.broadcast-item:last-child { border-bottom: none; }
.broadcast-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.broadcast-info { flex: 1; }
.broadcast-name { font-weight: 600; color: #1f2937; }
.broadcast-meta { font-size: 0.85rem; color: #6b7280; }
.broadcast-stats { text-align: right; }
.broadcast-count { font-size: 1.2rem; font-weight: 700; color: #1f2937; }
.broadcast-status { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-sending { background: #dbeafe; color: #1e40af; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-scheduled { background: #ede9fe; color: #5b21b6; }

.action-btn { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; border: none; }
.action-btn.send { background: #25D366; color: #fff; }
.action-btn.send:hover { background: #128C7E; }

@media (max-width: 992px) { .broadcast-grid { grid-template-columns: 1fr; } }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:linear-gradient(135deg,#25D366,#128C7E);">
                <i class="fas fa-bullhorn text-white" style="font-size:20px;"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold">Broadcast Messages</h4>
                <small class="text-muted">Send bulk WhatsApp campaigns</small>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= $success ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="broadcast-grid">
        <div class="create-card">
            <h3><i class="fas fa-plus-circle"></i> Create Broadcast</h3>
            <form method="post">
                <input type="hidden" name="action" value="create_broadcast">
                
                <div class="form-group">
                    <label>Campaign Name</label>
                    <input type="text" name="name" placeholder="e.g., Summer Sale Announcement" required>
                </div>
                
                <div class="form-group">
                    <label>Message Template</label>
                    <select name="template" required>
                        <option value="">Select template...</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= $t['template_key'] ?>"><?= htmlspecialchars($t['template_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Target Segment</label>
                    <div class="segment-cards">
                        <?php foreach ($segments as $key => $seg): ?>
                            <label class="segment-card" onclick="this.querySelector('input').checked=true; document.querySelectorAll('.segment-card').forEach(c=>c.classList.remove('selected')); this.classList.add('selected');">
                                <input type="radio" name="segment" value="<?= $key ?>" <?= $key === 'all' ? 'checked' : '' ?>>
                                <div class="seg-label"><?= $seg['label'] ?></div>
                                <div class="seg-count"><?= number_format($seg['count']) ?> users</div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Schedule (optional)</label>
                    <input type="datetime-local" name="schedule_at">
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Create Broadcast
                </button>
            </form>
        </div>

        <div class="broadcasts-list">
            <h3><i class="fas fa-history me-2"></i> Recent Broadcasts</h3>
            <?php if (empty($broadcasts)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox mb-3" style="font-size:2.5rem;"></i>
                    <p class="mb-0">No broadcasts yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($broadcasts as $b): ?>
                    <div class="broadcast-item">
                        <div class="broadcast-icon" style="background:<?= $b['status'] === 'completed' ? '#d1fae5' : '#fef3c7' ?>;color:<?= $b['status'] === 'completed' ? '#065f46' : '#92400e' ?>;">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="broadcast-info">
                            <div class="broadcast-name"><?= htmlspecialchars($b['name']) ?></div>
                            <div class="broadcast-meta">
                                <?= ucfirst(str_replace('_', ' ', $b['segment'])) ?> • 
                                <?= date('M d, h:i A', strtotime($b['created_at'])) ?>
                            </div>
                        </div>
                        <div class="broadcast-stats">
                            <div class="broadcast-count"><?= number_format($b['sent_count'] ?? 0) ?>/<?= number_format($b['recipient_count']) ?></div>
                            <span class="broadcast-status status-<?= $b['status'] ?>"><?= $b['status'] ?></span>
                        </div>
                        <?php if ($b['status'] === 'pending'): ?>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="action" value="send_broadcast">
                                <input type="hidden" name="broadcast_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="action-btn send" onclick="return confirm('Send to <?= $b['recipient_count'] ?> recipients?');">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
