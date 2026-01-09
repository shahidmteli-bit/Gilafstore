<?php
/**
 * ADMIN PANEL - MANAGE SUPPORT AGENTS
 * Add, edit, and remove support agents for ticket assignment
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/support_functions.php';

require_admin();

$pageTitle = 'Manage Support Agents — Admin';
$adminPage = 'support_agents';

// Get admin user ID for Gilaf Store Admin
$adminUserId = null;
try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = 'gilafstore@gmail.com' AND is_admin = 1 LIMIT 1");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($adminUser) {
        $adminUserId = $adminUser['id'];
    }
} catch (Exception $e) {
    error_log("Failed to get admin user: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_agent'])) {
        $employeeId = trim($_POST['employee_id'] ?? '');
        $agentName = trim($_POST['agent_name'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');
        $adminTeam = trim($_POST['admin_team'] ?? 'Gilaf Support Team');
        $supportEmail = trim($_POST['support_email'] ?? 'gilaf.help@gmail.com');
        
        // Use admin user ID
        $userId = $adminUserId;
        
        if (!empty($userId) && !empty($agentName) && !empty($employeeId)) {
            try {
                $db = get_db_connection();
                
                // Check if employee_id already exists
                $checkStmt = $db->prepare("SELECT id FROM support_agents WHERE employee_id = ?");
                $checkStmt->execute([$employeeId]);
                if ($checkStmt->fetch()) {
                    $error = 'Employee ID already exists. Please use a unique Employee ID.';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO support_agents (user_id, employee_id, agent_name, agent_email, specialization, is_active)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$userId, $employeeId, $agentName, $supportEmail, $specialization]);
                    redirect_with_message('/admin/manage_support_agents.php', 'Support agent added successfully', 'success');
                }
            } catch (Exception $e) {
                $error = 'Failed to add agent: ' . $e->getMessage();
            }
        } else {
            $error = 'Please provide Employee ID and agent name';
        }
    } elseif (isset($_POST['toggle_status'])) {
        $agentId = $_POST['agent_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? '';
        
        if (!empty($agentId)) {
            try {
                $db = get_db_connection();
                $stmt = $db->prepare("UPDATE support_agents SET is_active = ? WHERE id = ?");
                $stmt->execute([$newStatus, $agentId]);
                redirect_with_message('/admin/manage_support_agents.php', 'Agent status updated', 'success');
            } catch (Exception $e) {
                $error = 'Failed to update status: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_agent'])) {
        $agentId = $_POST['agent_id'] ?? '';
        
        if (!empty($agentId)) {
            try {
                $db = get_db_connection();
                $stmt = $db->prepare("DELETE FROM support_agents WHERE id = ?");
                $stmt->execute([$agentId]);
                redirect_with_message('/admin/manage_support_agents.php', 'Agent removed successfully', 'success');
            } catch (Exception $e) {
                $error = 'Failed to remove agent: ' . $e->getMessage();
            }
        }
    }
}

// Get all support agents
try {
    $db = get_db_connection();
    $agents = $db->query("SELECT * FROM support_agents ORDER BY agent_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $agents = [];
    $error = 'Failed to load agents: ' . $e->getMessage();
}

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
    .agents-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .page-title {
        font-family: 'Playfair Display', serif;
        color: #1A3C34;
        margin: 0;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: #1A3C34;
        color: white;
    }
    
    .btn-primary:hover {
        background: #2d5a4d;
        transform: translateY(-2px);
    }
    
    .btn-success {
        background: #27ae60;
        color: white;
    }
    
    .btn-warning {
        background: #f39c12;
        color: white;
    }
    
    .btn-danger {
        background: #e74c3c;
        color: white;
    }
    
    .btn-sm {
        padding: 8px 16px;
        font-size: 13px;
    }
    
    .card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }
    
    .card-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0 0 20px 0;
        padding-bottom: 15px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
    }
    
    .form-control {
        width: 100%;
        padding: 12px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.2s;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #1A3C34;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .agents-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .agents-table th {
        background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
        color: white;
        padding: 15px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
    }
    
    .agents-table td {
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .agents-table tr:hover {
        background: #f8f9fa;
    }
    
    .badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-active {
        background: #d4edda;
        color: #155724;
    }
    
    .badge-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #7f8c8d;
    }
    
    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
    }
</style>

<section class="py-4">
    <div class="container-fluid px-4">
        <div class="agents-container">
            <div class="page-header">
                <div>
                    <h2 class="page-title">
                        <i class="fas fa-user-headset"></i> Manage Support Agents
                    </h2>
                    <p style="color: #7f8c8d; margin: 5px 0 0 0;">Add and manage support team members for ticket assignment</p>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <!-- Add New Agent Form -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-user-plus"></i> Add New Support Agent</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Admin</label>
                            <select name="admin_team" class="form-control" required>
                                <option value="Gilaf Support Team">Gilaf Support Team</option>
                                <option value="Gilaf Security Team">Gilaf Security Team</option>
                            </select>
                            <small class="form-help" style="color: #7f8c8d; font-size: 12px;">Auto-assigned to Gilaf Support Team</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Support Email</label>
                            <select name="support_email" class="form-control" required>
                                <option value="gilaf.help@gmail.com">gilaf.help@gmail.com</option>
                                <option value="gilaf.secure@gmail.com">gilaf.secure@gmail.com</option>
                            </select>
                            <small class="form-help" style="color: #7f8c8d; font-size: 12px;">All support emails sent from this address</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Employee ID <span style="color: #e74c3c;">*</span></label>
                            <input type="text" name="employee_id" class="form-control" placeholder="e.g., EMP001, EMP002" required pattern="[A-Za-z0-9]+" title="Only letters and numbers allowed">
                            <small class="form-help" style="color: #7f8c8d; font-size: 12px;">Unique employee identifier (prevents duplicates)</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Support Agent Name <span style="color: #e74c3c;">*</span></label>
                            <input type="text" name="agent_name" class="form-control" placeholder="Enter agent name" required>
                            <small class="form-help" style="color: #7f8c8d; font-size: 12px;">Full name of the support agent</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Specialization</label>
                            <select name="specialization" class="form-control">
                                <option value="">Select Specialization...</option>
                                <option value="Technical Issues">Technical Issues</option>
                                <option value="Business Inquiries">Business Inquiries</option>
                                <option value="Order Support">Order Support</option>
                                <option value="Payment Issues">Payment Issues</option>
                                <option value="General Support">General Support</option>
                            </select>
                            <small class="form-help" style="color: #7f8c8d; font-size: 12px;">Agent's area of expertise</small>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_agent" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Support Agent
                    </button>
                </form>
            </div>
            
            <!-- Agents List -->
            <div class="card">
                <h3 class="card-title"><i class="fas fa-users"></i> Support Agents (<?= count($agents) ?>)</h3>
                
                <?php if (empty($agents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-headset"></i>
                        <h3>No Support Agents</h3>
                        <p>Add your first support agent to start assigning tickets.</p>
                    </div>
                <?php else: ?>
                    <table class="agents-table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Agent Name</th>
                                <th>Email</th>
                                <th>Specialization</th>
                                <th>Assigned</th>
                                <th>Resolved</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agents as $agent): ?>
                                <tr>
                                    <td>
                                        <span class="badge" style="background: #3498db; color: white; font-family: monospace;"><?= htmlspecialchars($agent['employee_id'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($agent['agent_name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($agent['agent_email']) ?></td>
                                    <td><?= htmlspecialchars($agent['specialization'] ?: '-') ?></td>
                                    <td><?= $agent['tickets_assigned'] ?></td>
                                    <td><?= $agent['tickets_resolved'] ?></td>
                                    <td>
                                        <?php if ($agent['is_active']): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $agent['is_active'] ? '0' : '1' ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-sm <?= $agent['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                                    <i class="fas fa-<?= $agent['is_active'] ? 'pause' : 'play' ?>"></i>
                                                    <?= $agent['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this agent?');">
                                                <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
                                                <button type="submit" name="delete_agent" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
// Auto-fill agent name and email when user is selected
document.querySelector('select[name="user_id"]').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const name = selectedOption.getAttribute('data-name');
    const email = selectedOption.getAttribute('data-email');
    
    if (name && email) {
        document.getElementById('agent_name').value = name;
        document.getElementById('agent_email').value = email;
    }
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
