<?php
/**
 * FAQ Management System - Admin Panel
 * Centralized FAQ management integrated with chatbot
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Manage FAQs';
$adminPage = 'faqs';

$pdo = db_connect();
$success = '';
$error = '';

// Handle FAQ Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_faq') {
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $category = trim($_POST['category'] ?? 'General Information');
        $priority = intval($_POST['priority'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($question) || empty($answer)) {
            $error = 'Question and answer are required';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO faqs (question, answer, keywords, category, priority, is_active, created_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$question, $answer, $keywords, $category, $priority, $is_active, $_SESSION['admin_id'] ?? null]);
                $success = 'FAQ added successfully! It will be available in the chatbot immediately.';
            } catch (PDOException $e) {
                $error = 'Error adding FAQ: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'update_faq') {
        $id = intval($_POST['faq_id'] ?? 0);
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $keywords = trim($_POST['keywords'] ?? '');
        $category = trim($_POST['category'] ?? 'General Information');
        $priority = intval($_POST['priority'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($id && !empty($question) && !empty($answer)) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE faqs 
                     SET question = ?, answer = ?, keywords = ?, category = ?, priority = ?, is_active = ?, updated_by = ?
                     WHERE id = ?"
                );
                $stmt->execute([$question, $answer, $keywords, $category, $priority, $is_active, $_SESSION['admin_id'] ?? null, $id]);
                $success = 'FAQ updated successfully! Changes are live immediately.';
            } catch (PDOException $e) {
                $error = 'Error updating FAQ: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'delete_faq') {
        $id = intval($_POST['faq_id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'FAQ deleted successfully!';
            } catch (PDOException $e) {
                $error = 'Error deleting FAQ: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'toggle_status') {
        $id = intval($_POST['faq_id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("UPDATE faqs SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'FAQ status updated!';
            } catch (PDOException $e) {
                $error = 'Error updating status: ' . $e->getMessage();
            }
        }
    }
}

// Get all FAQs with statistics
$faqs = $pdo->query("
    SELECT f.*, 
           fc.name as category_name,
           fc.icon as category_icon
    FROM faqs f
    LEFT JOIN faq_categories fc ON f.category = fc.name
    ORDER BY f.priority DESC, f.category, f.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categories = $pdo->query("SELECT * FROM faq_categories WHERE is_active = 1 ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

// Get FAQ statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_faqs,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_faqs,
        SUM(view_count) as total_views,
        SUM(helpful_count) as total_helpful
    FROM faqs
")->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.faq-container {
    background: #ffffff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    border: 2px solid #dee2e6;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 600;
}

.btn-add-faq {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-add-faq:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.faq-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.faq-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.faq-table td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
}

.faq-table tr:hover {
    background: #f8f9fa;
}

.badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-active {
    background: #d1fae5;
    color: #059669;
}

.badge-inactive {
    background: #fee2e2;
    color: #dc2626;
}

.badge-category {
    background: #dbeafe;
    color: #1e40af;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    margin-right: 5px;
    transition: all 0.2s ease;
}

.btn-edit {
    background: #fbbf24;
    color: #78350f;
}

.btn-edit:hover {
    background: #f59e0b;
}

.btn-delete {
    background: #ef4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
}

.btn-toggle {
    background: #6366f1;
    color: white;
}

.btn-toggle:hover {
    background: #4f46e5;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    padding: 30px;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e9ecef;
}

.modal-title {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
}

.modal-close {
    font-size: 28px;
    cursor: pointer;
    color: #6c757d;
    line-height: 1;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1a1a1a;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 15px;
}

.form-control:focus {
    border-color: #d4af37;
    outline: none;
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d1fae5;
    color: #059669;
    border: 1px solid #10b981;
}

.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #ef4444;
}

.priority-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.priority-high {
    background: #fef3c7;
    color: #92400e;
}

.priority-medium {
    background: #dbeafe;
    color: #1e40af;
}

.priority-low {
    background: #e5e7eb;
    color: #374151;
}
</style>

<div class="container mt-4">
    <div class="faq-container">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="font-size: 28px; font-weight: 700; margin: 0;">FAQ Management</h1>
                <p style="color: #6c757d; margin-top: 8px;">Manage FAQs for chatbot and customer support</p>
            </div>
            <button class="btn-add-faq" onclick="openAddModal()">
                <i class="fas fa-plus me-2"></i>Add New FAQ
            </button>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_faqs'] ?? 0; ?></div>
                <div class="stat-label">Total FAQs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_faqs'] ?? 0; ?></div>
                <div class="stat-label">Active FAQs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_views'] ?? 0); ?></div>
                <div class="stat-label">Total Views</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_helpful'] ?? 0); ?></div>
                <div class="stat-label">Marked Helpful</div>
            </div>
        </div>

        <!-- FAQs Table -->
        <table class="faq-table">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Views</th>
                    <th>Helpful</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($faqs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #6c757d;">
                            <i class="fas fa-question-circle" style="font-size: 48px; margin-bottom: 10px; display: block; opacity: 0.3;"></i>
                            No FAQs found. Click "Add New FAQ" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($faqs as $faq): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; margin-bottom: 4px;"><?= htmlspecialchars(substr($faq['question'], 0, 80)); ?><?= strlen($faq['question']) > 80 ? '...' : ''; ?></div>
                                <?php if (!empty($faq['keywords'])): ?>
                                    <div style="font-size: 12px; color: #6c757d;">
                                        <i class="fas fa-tags"></i> <?= htmlspecialchars(substr($faq['keywords'], 0, 50)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-category">
                                    <?php if (!empty($faq['category_icon'])): ?>
                                        <i class="<?= htmlspecialchars($faq['category_icon']); ?>"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($faq['category']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $priorityClass = $faq['priority'] >= 8 ? 'priority-high' : ($faq['priority'] >= 5 ? 'priority-medium' : 'priority-low');
                                ?>
                                <span class="priority-badge <?= $priorityClass; ?>"><?= $faq['priority']; ?></span>
                            </td>
                            <td><?= number_format($faq['view_count']); ?></td>
                            <td><?= number_format($faq['helpful_count']); ?></td>
                            <td>
                                <span class="badge <?= $faq['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?= $faq['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-sm btn-edit" onclick='editFaq(<?= json_encode($faq); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Toggle FAQ status?');">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="faq_id" value="<?= $faq['id']; ?>">
                                    <button type="submit" class="btn-sm btn-toggle">
                                        <i class="fas fa-toggle-on"></i>
                                    </button>
                                </form>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                    <input type="hidden" name="action" value="delete_faq">
                                    <input type="hidden" name="faq_id" value="<?= $faq['id']; ?>">
                                    <button type="submit" class="btn-sm btn-delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit FAQ Modal -->
<div class="modal" id="faqModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Add New FAQ</h2>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <form method="post" id="faqForm">
            <input type="hidden" name="action" id="formAction" value="add_faq">
            <input type="hidden" name="faq_id" id="faqId">
            
            <div class="form-group">
                <label class="form-label">Question *</label>
                <textarea name="question" id="question" class="form-control" required placeholder="Enter the FAQ question"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Answer *</label>
                <textarea name="answer" id="answer" class="form-control" style="min-height: 150px;" required placeholder="Enter the detailed answer"></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Keywords (comma-separated)</label>
                <input type="text" name="keywords" id="keywords" class="form-control" placeholder="e.g., return, refund, policy, 7 days">
                <small style="color: #6c757d; display: block; margin-top: 5px;">
                    Add keywords to improve chatbot matching accuracy
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Category *</label>
                <select name="category" id="category" class="form-control" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']); ?>">
                            <?= htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Priority (0-10)</label>
                <input type="number" name="priority" id="priority" class="form-control" min="0" max="10" value="5">
                <small style="color: #6c757d; display: block; margin-top: 5px;">
                    Higher priority FAQs appear first when multiple matches are found
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="is_active" id="is_active" checked>
                    <span>Active (visible to chatbot)</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" style="padding: 12px 24px; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" class="btn-add-faq">
                    <i class="fas fa-save me-2"></i>Save FAQ
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New FAQ';
    document.getElementById('formAction').value = 'add_faq';
    document.getElementById('faqForm').reset();
    document.getElementById('faqId').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('faqModal').classList.add('active');
}

function editFaq(faq) {
    document.getElementById('modalTitle').textContent = 'Edit FAQ';
    document.getElementById('formAction').value = 'update_faq';
    document.getElementById('faqId').value = faq.id;
    document.getElementById('question').value = faq.question;
    document.getElementById('answer').value = faq.answer;
    document.getElementById('keywords').value = faq.keywords || '';
    document.getElementById('category').value = faq.category;
    document.getElementById('priority').value = faq.priority;
    document.getElementById('is_active').checked = faq.is_active == 1;
    document.getElementById('faqModal').classList.add('active');
}

function closeModal() {
    document.getElementById('faqModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('faqModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
