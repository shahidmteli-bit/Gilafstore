<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/error_logger.php';

require_admin();

$pageTitle = 'Error Logs — Admin';
$adminPage = 'logs';

// Handle clear logs action
if (isset($_POST['clear_logs'])) {
    clear_error_log();
    redirect_with_message('/admin/error_logs.php', 'Error logs cleared successfully', 'success');
}

// Get filter
$severityFilter = $_GET['severity'] ?? 'all';
$codeFilter = $_GET['code'] ?? '';

// Get all errors
$allErrors = get_recent_errors(200);

// Apply filters
$errors = array_filter($allErrors, function($error) use ($severityFilter, $codeFilter) {
    $severityMatch = $severityFilter === 'all' || $error['severity'] === strtoupper($severityFilter);
    $codeMatch = empty($codeFilter) || stripos($error['error_code'], $codeFilter) !== false;
    return $severityMatch && $codeMatch;
});

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0">Error Logs</h4>
        <p class="text-muted mb-0">Monitor system errors and debug issues with unique error codes.</p>
      </div>
      <div>
        <a href="<?= base_url('admin/error_codes_reference.php'); ?>" class="btn btn-outline-info me-2">
          <i class="fas fa-book"></i> Error Codes Reference
        </a>
        <form method="post" class="d-inline" onsubmit="return confirm('Clear all error logs?');">
          <button type="submit" name="clear_logs" class="btn btn-outline-danger">
            <i class="fas fa-trash"></i> Clear Logs
          </button>
        </form>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Severity</label>
            <select name="severity" class="form-select">
              <option value="all" <?= $severityFilter === 'all' ? 'selected' : ''; ?>>All Severities</option>
              <option value="error" <?= $severityFilter === 'error' ? 'selected' : ''; ?>>Errors Only</option>
              <option value="warning" <?= $severityFilter === 'warning' ? 'selected' : ''; ?>>Warnings Only</option>
              <option value="success" <?= $severityFilter === 'success' ? 'selected' : ''; ?>>Success Only</option>
              <option value="info" <?= $severityFilter === 'info' ? 'selected' : ''; ?>>Info Only</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Error Code</label>
            <input type="text" name="code" class="form-control" placeholder="e.g., CAT001, PROD001" value="<?= htmlspecialchars($codeFilter); ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label">&nbsp;</label>
            <div>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
              </button>
              <a href="<?= base_url('admin/error_logs.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-redo"></i> Reset
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card bg-danger text-white">
          <div class="card-body">
            <h6 class="mb-1">Errors</h6>
            <h3 class="mb-0"><?= count(array_filter($allErrors, fn($e) => $e['severity'] === 'ERROR')); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning text-white">
          <div class="card-body">
            <h6 class="mb-1">Warnings</h6>
            <h3 class="mb-0"><?= count(array_filter($allErrors, fn($e) => $e['severity'] === 'WARNING')); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success text-white">
          <div class="card-body">
            <h6 class="mb-1">Success</h6>
            <h3 class="mb-0"><?= count(array_filter($allErrors, fn($e) => $e['severity'] === 'SUCCESS')); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white">
          <div class="card-body">
            <h6 class="mb-1">Info</h6>
            <h3 class="mb-0"><?= count(array_filter($allErrors, fn($e) => $e['severity'] === 'INFO')); ?></h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Logs Table -->
    <div class="card">
      <div class="card-body p-0">
        <?php if (empty($errors)): ?>
          <div class="text-center py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h5>No Errors Found</h5>
            <p class="text-muted">Your system is running smoothly!</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Timestamp</th>
                  <th>Error Code</th>
                  <th>Severity</th>
                  <th>Message</th>
                  <th>User</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($errors as $index => $error): ?>
                  <tr>
                    <td><small><?= htmlspecialchars($error['timestamp']); ?></small></td>
                    <td>
                      <span class="badge bg-dark font-monospace"><?= htmlspecialchars($error['error_code']); ?></span>
                    </td>
                    <td>
                      <?php
                      $severityColors = [
                        'ERROR' => 'danger',
                        'WARNING' => 'warning',
                        'SUCCESS' => 'success',
                        'INFO' => 'info'
                      ];
                      $color = $severityColors[$error['severity']] ?? 'secondary';
                      ?>
                      <span class="badge bg-<?= $color; ?>"><?= htmlspecialchars($error['severity']); ?></span>
                    </td>
                    <td><?= htmlspecialchars($error['message']); ?></td>
                    <td><small><?= htmlspecialchars($error['user_email']); ?></small></td>
                    <td>
                      <button class="btn btn-sm btn-outline-primary" onclick="showErrorDetails(<?= htmlspecialchars(json_encode($error)); ?>)">
                        <i class="fas fa-eye"></i> Details
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Error Details Modal -->
<div class="modal fade" id="errorDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-bug"></i> Error Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="errorDetailsContent"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
function showErrorDetails(error) {
  const content = `
    <div class="mb-3">
      <strong>Error Code:</strong> <span class="badge bg-dark font-monospace">${error.error_code}</span>
    </div>
    <div class="mb-3">
      <strong>Severity:</strong> <span class="badge bg-${getSeverityColor(error.severity)}">${error.severity}</span>
    </div>
    <div class="mb-3">
      <strong>Timestamp:</strong> ${error.timestamp}
    </div>
    <div class="mb-3">
      <strong>Message:</strong><br>
      <div class="alert alert-secondary">${error.message}</div>
    </div>
    <div class="mb-3">
      <strong>User:</strong> ${error.user_email} (ID: ${error.user_id})
    </div>
    <div class="mb-3">
      <strong>IP Address:</strong> ${error.ip_address}
    </div>
    <div class="mb-3">
      <strong>Request:</strong> ${error.request_method} ${error.request_uri}
    </div>
    ${error.context && Object.keys(error.context).length > 0 ? `
    <div class="mb-3">
      <strong>Context:</strong>
      <pre class="bg-light p-3 rounded"><code>${JSON.stringify(error.context, null, 2)}</code></pre>
    </div>
    ` : ''}
  `;
  
  document.getElementById('errorDetailsContent').innerHTML = content;
  new bootstrap.Modal(document.getElementById('errorDetailsModal')).show();
}

function getSeverityColor(severity) {
  const colors = {
    'ERROR': 'danger',
    'WARNING': 'warning',
    'SUCCESS': 'success',
    'INFO': 'info'
  };
  return colors[severity] || 'secondary';
}
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
