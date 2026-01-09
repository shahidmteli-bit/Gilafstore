<?php
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_admin();

$page_title = "Manage Callback Requests";
include '../admin/includes/admin_header.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $callback_id = intval($_POST['callback_id']);
    $new_status = sanitize_input($_POST['status']);
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    $stmt = $conn->prepare("UPDATE callback_requests SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $notes, $callback_id);
    
    if ($stmt->execute()) {
        if ($new_status === 'contacted') {
            $conn->query("UPDATE callback_requests SET contacted_at = NOW() WHERE id = $callback_id");
        }
        $success_message = "Callback status updated successfully!";
    }
    $stmt->close();
}

// Fetch all callback requests
$callbacks = [];
$result = $conn->query("SELECT * FROM callback_requests ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $callbacks[] = $row;
    }
}

// Get statistics
$stats = [
    'pending' => 0,
    'contacted' => 0,
    'completed' => 0,
    'cancelled' => 0
];

$stats_result = $conn->query("SELECT status, COUNT(*) as count FROM callback_requests GROUP BY status");
if ($stats_result) {
    while ($row = $stats_result->fetch_assoc()) {
        $stats[$row['status']] = $row['count'];
    }
}
?>

<style>
.callback-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card h3 {
    font-size: 2rem;
    margin: 0 0 8px 0;
    color: #1A3C34;
}

.stat-card p {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card.pending h3 { color: #ffc107; }
.stat-card.contacted h3 { color: #17a2b8; }
.stat-card.completed h3 { color: #28a745; }
.stat-card.cancelled h3 { color: #dc3545; }

.callbacks-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.callbacks-table table {
    width: 100%;
    border-collapse: collapse;
}

.callbacks-table th {
    background: #1A3C34;
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
}

.callbacks-table td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.callbacks-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.contacted {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.cancelled {
    background: #f8d7da;
    color: #721c24;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.action-btn.view {
    background: #007bff;
    color: white;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-close {
    font-size: 2rem;
    cursor: pointer;
    color: #999;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.95rem;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.btn-primary {
    background: #1A3C34;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #2a5c4e;
    transform: translateY(-2px);
}
</style>

<div class="container" style="padding: 30px;">
    <h1 style="color: #1A3C34; margin-bottom: 30px;">
        <i class="fas fa-phone-alt"></i> Callback Requests Management
    </h1>

    <?php if (isset($success_message)): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= $success_message ?>
        </div>
    <?php endif; ?>

    <div class="callback-stats">
        <div class="stat-card pending">
            <h3><?= $stats['pending'] ?></h3>
            <p>Pending</p>
        </div>
        <div class="stat-card contacted">
            <h3><?= $stats['contacted'] ?></h3>
            <p>Contacted</p>
        </div>
        <div class="stat-card completed">
            <h3><?= $stats['completed'] ?></h3>
            <p>Completed</p>
        </div>
        <div class="stat-card cancelled">
            <h3><?= $stats['cancelled'] ?></h3>
            <p>Cancelled</p>
        </div>
    </div>

    <div class="callbacks-table">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Preferred Time</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($callbacks)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                            No callback requests yet
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($callbacks as $callback): ?>
                        <tr>
                            <td><strong>#<?= $callback['id'] ?></strong></td>
                            <td><?= htmlspecialchars($callback['name']) ?></td>
                            <td><a href="tel:<?= $callback['phone'] ?>"><?= $callback['phone'] ?></a></td>
                            <td><?= htmlspecialchars($callback['preferred_time'] ?: 'Anytime') ?></td>
                            <td>
                                <span class="status-badge <?= $callback['status'] ?>">
                                    <?= ucfirst($callback['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y H:i', strtotime($callback['created_at'])) ?></td>
                            <td>
                                <button class="action-btn view" onclick="viewCallback(<?= htmlspecialchars(json_encode($callback)) ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Callback Details Modal -->
<div class="modal" id="callbackModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="margin: 0; color: #1A3C34;">Callback Details</h2>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        
        <form method="POST" id="callbackForm">
            <input type="hidden" name="callback_id" id="callback_id">
            <input type="hidden" name="update_status" value="1">
            
            <div class="form-group">
                <label>Name</label>
                <input type="text" id="callback_name" readonly>
            </div>
            
            <div class="form-group">
                <label>Phone</label>
                <input type="text" id="callback_phone" readonly>
            </div>
            
            <div class="form-group">
                <label>Preferred Time</label>
                <input type="text" id="callback_time" readonly>
            </div>
            
            <div class="form-group">
                <label>Message</label>
                <textarea id="callback_message" readonly></textarea>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="callback_status" required>
                    <option value="pending">Pending</option>
                    <option value="contacted">Contacted</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes (Internal)</label>
                <textarea name="notes" id="callback_notes" placeholder="Add internal notes..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Created At</label>
                <input type="text" id="callback_created" readonly>
            </div>
            
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Update Status
            </button>
        </form>
    </div>
</div>

<script>
function viewCallback(callback) {
    document.getElementById('callback_id').value = callback.id;
    document.getElementById('callback_name').value = callback.name;
    document.getElementById('callback_phone').value = callback.phone;
    document.getElementById('callback_time').value = callback.preferred_time || 'Anytime';
    document.getElementById('callback_message').value = callback.message || 'No message';
    document.getElementById('callback_status').value = callback.status;
    document.getElementById('callback_notes').value = callback.notes || '';
    document.getElementById('callback_created').value = callback.created_at;
    
    document.getElementById('callbackModal').classList.add('active');
}

function closeModal() {
    document.getElementById('callbackModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('callbackModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include '../admin/includes/admin_footer.php'; ?>
