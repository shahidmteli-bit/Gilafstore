<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Users — Admin';
$adminPage = 'users';
$users = admin_get_users();

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0">Users</h4>
        <p class="text-muted mb-0">Monitor customer accounts and remove suspicious activity.</p>
      </div>
    </div>

    <div class="card shadow-3 border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
                <tr>
                  <td class="fw-semibold">#<?= (int)$user['id']; ?></td>
                  <td><?= htmlspecialchars($user['name']); ?></td>
                  <td><?= htmlspecialchars($user['email']); ?></td>
                  <td>
                    <?php if (!empty($user['is_admin'])): ?>
                      <span class="badge bg-primary">Admin</span>
                    <?php else: ?>
                      <span class="badge bg-success">Customer</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($user['is_blocked'])): ?>
                      <span class="badge bg-danger"><i class="fas fa-ban"></i> Blocked</span>
                    <?php elseif (!empty($user['is_restricted'])): ?>
                      <span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> Restricted</span>
                    <?php else: ?>
                      <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars(date('M d, Y', strtotime($user['created_at'] ?? 'now'))); ?></td>
                  <td class="text-end">
                    <?php if (empty($user['is_admin'])): ?>
                      <button class="btn btn-sm btn-outline-primary" onclick="openUserModal(<?= (int)$user['id']; ?>, '<?= htmlspecialchars($user['name']); ?>', <?= (int)($user['is_blocked'] ?? 0); ?>, <?= (int)($user['is_restricted'] ?? 0); ?>, '<?= htmlspecialchars($user['restriction_reason'] ?? ''); ?>')">
                        <i class="fas fa-cog"></i> Manage
                      </button>
                      <form action="<?= base_url('admin_actions.php'); ?>" method="post" class="d-inline" onsubmit="return confirm('Permanently delete this user?');">
                        <input type="hidden" name="action" value="delete_user" />
                        <input type="hidden" name="user_id" value="<?= (int)$user['id']; ?>" />
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">Protected</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$users): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">No users found yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- User Management Modal -->
<div class="modal fade" id="userManageModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="<?= base_url('admin_actions.php'); ?>" method="post" id="userManageForm">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-cog"></i> Manage User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="manage_user_access" />
          <input type="hidden" name="user_id" id="modalUserId" />
          
          <div class="alert alert-info">
            <strong>User:</strong> <span id="modalUserName"></span>
          </div>
          
          <div class="mb-3">
            <label class="form-label">User Access Control</label>
            <select name="access_action" id="modalAccessAction" class="form-select" required onchange="toggleReasonField()">
              <option value="">Select action...</option>
              <option value="unblock">Unblock User (Full Access)</option>
              <option value="block">Block User (No Access)</option>
              <option value="restrict">Restrict User (Limited Access)</option>
              <option value="unrestrict">Remove Restrictions</option>
            </select>
          </div>
          
          <div id="reasonField" style="display:none;">
            <div class="mb-3">
              <label class="form-label">Reason / Notes *</label>
              <textarea name="reason" id="modalReason" class="form-control" rows="3" placeholder="Enter reason for this action..."></textarea>
              <small class="text-muted">This will be logged and visible to other admins</small>
            </div>
          </div>
          
          <div class="alert alert-warning">
            <strong>Note:</strong>
            <ul class="mb-0 small">
              <li><strong>Blocked:</strong> User cannot login or access any features</li>
              <li><strong>Restricted:</strong> User has limited access (can view but not purchase)</li>
              <li><strong>Unblock/Unrestrict:</strong> Restore full access</li>
            </ul>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Apply Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openUserModal(userId, userName, isBlocked, isRestricted, reason) {
  document.getElementById('modalUserId').value = userId;
  document.getElementById('modalUserName').textContent = userName;
  document.getElementById('modalReason').value = reason || '';
  
  // Pre-select appropriate action based on current status
  const actionSelect = document.getElementById('modalAccessAction');
  if (isBlocked) {
    actionSelect.value = 'unblock';
  } else if (isRestricted) {
    actionSelect.value = 'unrestrict';
  } else {
    actionSelect.value = '';
  }
  
  toggleReasonField();
  
  const modal = new bootstrap.Modal(document.getElementById('userManageModal'));
  modal.show();
}

function toggleReasonField() {
  const action = document.getElementById('modalAccessAction').value;
  const reasonField = document.getElementById('reasonField');
  const reasonTextarea = document.getElementById('modalReason');
  
  if (action === 'block' || action === 'restrict') {
    reasonField.style.display = 'block';
    reasonTextarea.required = true;
  } else {
    reasonField.style.display = 'none';
    reasonTextarea.required = false;
  }
}
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
