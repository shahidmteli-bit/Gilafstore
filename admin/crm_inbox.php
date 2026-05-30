<?php
/**
 * WhatsApp CRM Inbox
 * Quick view of recent conversations with customers
 */
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/crm_engine.php';

require_admin();

$crm = CRMEngine::getInstance();
$pageTitle = 'WhatsApp Inbox — CRM';
$adminPage = 'crm_inbox';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];
    
    switch ($action) {
        case 'send_message':
            $phone = $_POST['phone'] ?? '';
            $message = $_POST['message'] ?? '';
            $templateKey = $_POST['template'] ?? '';
            
            if (!$phone || (!$message && !$templateKey)) {
                echo json_encode(['success' => false, 'error' => 'Phone and message required']);
                exit;
            }
            
            if ($templateKey) {
                $result = $crm->sendNotification($phone, $templateKey, []);
            } else {
                // Send as custom message via CRM API
                $result = $crm->apiRequest('POST', 'integration/send-message', [
                    'phone' => $phone,
                    'text' => $message,
                    'type' => 'text',
                ]);
                $result = $result['success'];
            }
            
            echo json_encode(['success' => $result]);
            exit;
            
        case 'get_conversations':
            // Fetch recent activity from CRM
            $conversations = $crm->apiRequest('GET', 'integration/conversations?limit=50');
            echo json_encode($conversations);
            exit;
            
        case 'get_customer_info':
            $phone = $_POST['phone'] ?? '';
            $info = getCustomerByPhone($phone);
            echo json_encode(['success' => true, 'customer' => $info]);
            exit;
    }
    exit;
}

function getCustomerByPhone($phone) {
    global $pdo;
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    $last10 = substr($cleaned, -10);
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT o.id) as order_count,
               COALESCE(SUM(o.total_amount), 0) as total_spend,
               MAX(o.created_at) as last_order
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id
        WHERE u.phone LIKE ? OR u.mobile LIKE ?
        GROUP BY u.id
        LIMIT 1
    ");
    $stmt->execute(["%$last10", "%$last10"]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get recent notifications sent
$recentNotifications = $pdo->query("
    SELECT n.*, o.id as order_id 
    FROM crm_order_notifications n
    LEFT JOIN orders o ON n.order_id = o.id
    ORDER BY n.created_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Get templates for quick reply
$templates = $pdo->query("
    SELECT template_key, template_name, category 
    FROM crm_notification_templates 
    WHERE is_active = 1 
    ORDER BY category, template_name
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.inbox-container { display: grid; grid-template-columns: 350px 1fr 300px; gap: 0; height: calc(100vh - 120px); background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
.inbox-sidebar { background: #f8f9fa; border-right: 1px solid #e9ecef; overflow-y: auto; }
.inbox-main { display: flex; flex-direction: column; }
.inbox-details { background: #f8f9fa; border-left: 1px solid #e9ecef; overflow-y: auto; padding: 20px; }

.sidebar-header { padding: 16px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: 10px; }
.sidebar-header input { flex: 1; padding: 10px 14px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.9rem; }
.sidebar-header input:focus { outline: none; border-color: #25D366; }

.conv-list { }
.conv-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid #e9ecef; cursor: pointer; transition: background 0.2s; }
.conv-item:hover { background: #e9ecef; }
.conv-item.active { background: #d4edda; border-left: 3px solid #25D366; }
.conv-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #25D366, #128C7E); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 1.1rem; flex-shrink: 0; }
.conv-info { flex: 1; min-width: 0; }
.conv-name { font-weight: 600; color: #1f2937; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv-preview { color: #6b7280; font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv-time { font-size: 0.75rem; color: #9ca3af; }
.conv-badge { background: #25D366; color: #fff; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; }

.chat-header { padding: 16px 20px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; gap: 12px; background: #fff; }
.chat-header h4 { margin: 0; font-size: 1.1rem; }
.chat-header .phone { color: #6b7280; font-size: 0.85rem; }

.chat-messages { flex: 1; overflow-y: auto; padding: 20px; background: #e5ddd5; background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4cfc4' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }

.message { max-width: 70%; margin-bottom: 12px; }
.message.sent { margin-left: auto; }
.message.received { margin-right: auto; }
.message-bubble { padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; position: relative; }
.message.sent .message-bubble { background: #dcf8c6; border-bottom-right-radius: 4px; }
.message.received .message-bubble { background: #fff; border-bottom-left-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,0.1); }
.message-time { font-size: 0.7rem; color: #6b7280; margin-top: 4px; text-align: right; }
.message-status { font-size: 0.7rem; color: #6b7280; }
.message-status.delivered { color: #25D366; }

.chat-input { padding: 16px 20px; border-top: 1px solid #e9ecef; background: #fff; display: flex; gap: 10px; align-items: flex-end; }
.chat-input textarea { flex: 1; padding: 12px 16px; border: 1px solid #e5e7eb; border-radius: 24px; resize: none; font-size: 0.95rem; max-height: 120px; line-height: 1.4; }
.chat-input textarea:focus { outline: none; border-color: #25D366; }
.chat-input button { width: 44px; height: 44px; border-radius: 50%; border: none; background: #25D366; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.chat-input button:hover { background: #128C7E; }

.template-select { padding: 10px; border-top: 1px solid #e9ecef; background: #f8f9fa; }
.template-select select { width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.85rem; }

.detail-card { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.detail-card h5 { font-size: 0.9rem; color: #6b7280; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
.detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; }
.detail-row:last-child { border-bottom: none; }
.detail-label { color: #6b7280; }
.detail-value { font-weight: 600; color: #1f2937; }

.quick-actions { display: flex; flex-direction: column; gap: 8px; }
.quick-action-btn { padding: 10px 16px; border: 1px solid #dee2e6; border-radius: 8px; background: #fff; cursor: pointer; font-size: 0.85rem; text-align: left; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
.quick-action-btn:hover { border-color: #25D366; background: #f0fff4; }
.quick-action-btn i { color: #25D366; }

.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af; }
.empty-state i { font-size: 3rem; margin-bottom: 16px; }

@media (max-width: 1200px) {
    .inbox-container { grid-template-columns: 300px 1fr; }
    .inbox-details { display: none; }
}
@media (max-width: 768px) {
    .inbox-container { grid-template-columns: 1fr; }
    .inbox-sidebar { display: none; }
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:linear-gradient(135deg,#25D366,#128C7E);">
                <i class="fab fa-whatsapp text-white" style="font-size:24px;"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold">WhatsApp Inbox</h4>
                <small class="text-muted">Manage customer conversations</small>
            </div>
        </div>
        <a href="<?= base_url('admin/crm_integration.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-cog"></i> CRM Settings
        </a>
    </div>

    <div class="inbox-container">
        <!-- Sidebar: Recent Activity -->
        <div class="inbox-sidebar">
            <div class="sidebar-header">
                <input type="text" id="searchConv" placeholder="Search by phone or name...">
            </div>
            <div class="conv-list" id="convList">
                <?php foreach ($recentNotifications as $n): ?>
                    <?php $customer = getCustomerByPhone($n['phone']); ?>
                    <div class="conv-item" data-phone="<?= htmlspecialchars($n['phone']) ?>" onclick="selectConversation(this)">
                        <div class="conv-avatar"><?= strtoupper(substr($customer['name'] ?? 'U', 0, 1)) ?></div>
                        <div class="conv-info">
                            <div class="conv-name"><?= htmlspecialchars($customer['name'] ?? $n['phone']) ?></div>
                            <div class="conv-preview"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $n['event_type']))) ?></div>
                        </div>
                        <div>
                            <div class="conv-time"><?= date('H:i', strtotime($n['created_at'])) ?></div>
                            <span class="conv-badge <?= $n['status'] === 'sent' ? '' : 'bg-danger' ?>"><?= $n['status'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($recentNotifications)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-inbox mb-2" style="font-size:2rem;"></i>
                        <p class="mb-0">No recent messages</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="inbox-main">
            <div class="chat-header" id="chatHeader" style="display:none;">
                <div class="conv-avatar" id="chatAvatar">U</div>
                <div>
                    <h4 id="chatName">Select a conversation</h4>
                    <div class="phone" id="chatPhone"></div>
                </div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="empty-state">
                    <i class="fab fa-whatsapp"></i>
                    <p>Select a conversation to view messages</p>
                </div>
            </div>
            
            <div class="template-select">
                <select id="templateSelect" onchange="insertTemplate()">
                    <option value="">-- Quick Reply Templates --</option>
                    <?php foreach ($templates as $t): ?>
                        <option value="<?= $t['template_key'] ?>">[<?= ucfirst($t['category']) ?>] <?= $t['template_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="chat-input">
                <textarea id="messageInput" placeholder="Type a message..." rows="1" onkeydown="handleEnter(event)"></textarea>
                <button onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>

        <!-- Customer Details -->
        <div class="inbox-details" id="customerDetails">
            <div class="detail-card">
                <h5><i class="fas fa-user"></i> Customer Info</h5>
                <div id="customerInfo">
                    <p class="text-muted text-center">Select a conversation</p>
                </div>
            </div>
            
            <div class="detail-card">
                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                <div class="quick-actions">
                    <button class="quick-action-btn" onclick="sendQuickTemplate('order_placed')">
                        <i class="fas fa-shopping-bag"></i> Order Confirmation
                    </button>
                    <button class="quick-action-btn" onclick="sendQuickTemplate('order_shipped')">
                        <i class="fas fa-truck"></i> Shipping Update
                    </button>
                    <button class="quick-action-btn" onclick="sendQuickTemplate('cart_reminder_1')">
                        <i class="fas fa-shopping-cart"></i> Cart Reminder
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPhone = '';
let currentCustomer = null;

function selectConversation(el) {
    document.querySelectorAll('.conv-item').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    
    currentPhone = el.dataset.phone;
    document.getElementById('chatHeader').style.display = 'flex';
    document.getElementById('chatPhone').textContent = currentPhone;
    
    // Load customer info
    fetch('crm_inbox.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=get_customer_info&phone=${encodeURIComponent(currentPhone)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.customer) {
            currentCustomer = data.customer;
            document.getElementById('chatName').textContent = data.customer.name || 'Unknown';
            document.getElementById('chatAvatar').textContent = (data.customer.name || 'U').charAt(0).toUpperCase();
            
            document.getElementById('customerInfo').innerHTML = `
                <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value">${data.customer.name || 'N/A'}</span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">${data.customer.email || 'N/A'}</span></div>
                <div class="detail-row"><span class="detail-label">Orders</span><span class="detail-value">${data.customer.order_count || 0}</span></div>
                <div class="detail-row"><span class="detail-label">Total Spend</span><span class="detail-value">₹${parseFloat(data.customer.total_spend || 0).toLocaleString()}</span></div>
                <div class="detail-row"><span class="detail-label">Last Order</span><span class="detail-value">${data.customer.last_order ? new Date(data.customer.last_order).toLocaleDateString() : 'Never'}</span></div>
            `;
        }
    });
    
    // Show placeholder messages
    document.getElementById('chatMessages').innerHTML = `
        <div class="message received">
            <div class="message-bubble">Recent notifications to this number are shown in the sidebar.</div>
            <div class="message-time">System</div>
        </div>
        <div class="message sent">
            <div class="message-bubble">Type a message or use a quick reply template to send a WhatsApp message.</div>
            <div class="message-time">Info</div>
        </div>
    `;
}

function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    if (!message || !currentPhone) return;
    
    // Add to UI immediately
    const msgDiv = document.createElement('div');
    msgDiv.className = 'message sent';
    msgDiv.innerHTML = `<div class="message-bubble">${message}</div><div class="message-time">Sending...</div>`;
    document.getElementById('chatMessages').appendChild(msgDiv);
    document.getElementById('chatMessages').scrollTop = 999999;
    
    input.value = '';
    
    // Send via API
    fetch('crm_inbox.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=send_message&phone=${encodeURIComponent(currentPhone)}&message=${encodeURIComponent(message)}`
    })
    .then(r => r.json())
    .then(data => {
        msgDiv.querySelector('.message-time').textContent = data.success ? 'Sent ✓' : 'Failed ✗';
        if (!data.success) msgDiv.querySelector('.message-time').style.color = '#ef4444';
    });
}

function sendQuickTemplate(templateKey) {
    if (!currentPhone) { alert('Select a conversation first'); return; }
    
    fetch('crm_inbox.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=send_message&phone=${encodeURIComponent(currentPhone)}&template=${encodeURIComponent(templateKey)}`
    })
    .then(r => r.json())
    .then(data => {
        alert(data.success ? 'Template sent!' : 'Failed to send');
    });
}

function insertTemplate() {
    const select = document.getElementById('templateSelect');
    if (select.value) {
        document.getElementById('messageInput').value = `[Template: ${select.options[select.selectedIndex].text}]`;
        select.value = '';
    }
}

function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// Search filter
document.getElementById('searchConv').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(item => {
        const name = item.querySelector('.conv-name').textContent.toLowerCase();
        const phone = item.dataset.phone.toLowerCase();
        item.style.display = (name.includes(query) || phone.includes(query)) ? '' : 'none';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
