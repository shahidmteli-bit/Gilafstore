<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Error Codes Reference — Admin';
$adminPage = 'logs';

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0">Error Codes Reference</h4>
        <p class="text-muted mb-0">Complete reference of all error codes used in the system.</p>
      </div>
      <a href="<?= base_url('admin/error_logs.php'); ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i> Back to Error Logs
      </a>
    </div>

    <!-- Categories Section -->
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-tags"></i> CATEGORIES (CAT###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>CAT000</code></td>
              <td>Category creation attempt (info log)</td>
              <td><span class="badge bg-info">INFO</span></td>
            </tr>
            <tr>
              <td><code>CAT001</code></td>
              <td>Category creation/update failed - empty name</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>CAT002</code></td>
              <td>Category creation failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>CAT003</code></td>
              <td>Category update failed - invalid ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>CAT004</code></td>
              <td>Category update failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>CAT005</code></td>
              <td>Category delete failed - invalid ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>CAT006</code></td>
              <td>Category delete failed - has products</td>
              <td><span class="badge bg-warning">WARNING</span></td>
            </tr>
            <tr>
              <td><code>CAT007</code></td>
              <td>Category delete failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>CAT100</code></td>
              <td>Category created successfully</td>
              <td><span class="badge bg-success">SUCCESS</span></td>
            </tr>
            <tr>
              <td><code>CAT101</code></td>
              <td>Category updated successfully</td>
              <td><span class="badge bg-success">SUCCESS</span></td>
            </tr>
            <tr>
              <td><code>CAT102</code></td>
              <td>Category deleted successfully</td>
              <td><span class="badge bg-success">SUCCESS</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Products Section -->
    <div class="card mb-4">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-box"></i> PRODUCTS (PROD###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>PROD001</code></td>
              <td>Product creation failed - missing required fields</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>PROD002</code></td>
              <td>Product creation failed - invalid category</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>PROD003</code></td>
              <td>Product creation failed - image upload error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>PROD004</code></td>
              <td>Product creation failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>PROD005</code></td>
              <td>Product update failed - invalid ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>PROD006</code></td>
              <td>Product update failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>PROD007</code></td>
              <td>Product delete failed - invalid ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>PROD008</code></td>
              <td>Product delete failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Orders Section -->
    <div class="card mb-4">
      <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> ORDERS (ORD###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>ORD001</code></td>
              <td>Order status update failed - invalid order ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>ORD002</code></td>
              <td>Order status update failed - invalid status</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>ORD003</code></td>
              <td>Order status update failed - missing courier info</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>ORD004</code></td>
              <td>Order status update failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Users Section -->
    <div class="card mb-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-users"></i> USERS (USER###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>USER001</code></td>
              <td>User access management failed - invalid user ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>USER002</code></td>
              <td>User access management failed - invalid action</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>USER003</code></td>
              <td>User access management failed - missing reason</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>USER004</code></td>
              <td>User access management failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>USER005</code></td>
              <td>User delete failed - cannot delete self</td>
              <td><span class="badge bg-warning">WARNING</span></td>
            </tr>
            <tr>
              <td><code>USER006</code></td>
              <td>User delete failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Batches Section -->
    <div class="card mb-4">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-barcode"></i> BATCHES (BATCH###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>BATCH001</code></td>
              <td>Batch creation failed - empty batch code</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>BATCH002</code></td>
              <td>Batch creation failed - duplicate code</td>
              <td><span class="badge bg-warning">WARNING</span></td>
            </tr>
            <tr>
              <td><code>BATCH003</code></td>
              <td>Batch creation failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>BATCH004</code></td>
              <td>Batch update failed - invalid ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>BATCH005</code></td>
              <td>Batch delete failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Applications Section -->
    <div class="card mb-4">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-handshake"></i> APPLICATIONS (APP###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>APP001</code></td>
              <td>Application submission failed - missing fields</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>APP002</code></td>
              <td>Application submission failed - file upload error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>APP003</code></td>
              <td>Application submission failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>APP004</code></td>
              <td>Application approval failed - invalid ID</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>APP005</code></td>
              <td>Application approval failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Authentication Section -->
    <div class="card mb-4">
      <div class="card-header" style="background: #6c757d; color: white;">
        <h5 class="mb-0"><i class="fas fa-lock"></i> AUTHENTICATION (AUTH###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>AUTH001</code></td>
              <td>Login failed - invalid credentials</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>AUTH002</code></td>
              <td>Login failed - user blocked</td>
              <td><span class="badge bg-warning">WARNING</span></td>
            </tr>
            <tr>
              <td><code>AUTH003</code></td>
              <td>Registration failed - email exists</td>
              <td><span class="badge bg-warning">WARNING</span></td>
            </tr>
            <tr>
              <td><code>AUTH004</code></td>
              <td>Registration failed - database error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>AUTH005</code></td>
              <td>Unauthorized access attempt</td>
              <td><span class="badge bg-warning">WARNING</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- System Section -->
    <div class="card mb-4">
      <div class="card-header" style="background: #dc3545; color: white;">
        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> SYSTEM (SYS###)</h5>
      </div>
      <div class="card-body">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Severity</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>SYS001</code></td>
              <td>Database connection failed</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>SYS002</code></td>
              <td>File upload directory not writable</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>SYS003</code></td>
              <td>Configuration error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
            <tr>
              <td><code>SYS004</code></td>
              <td>Unknown error</td>
              <td><span class="badge bg-danger">ERROR</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Usage Guide -->
    <div class="card">
      <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-info-circle"></i> How to Use Error Codes</h5>
      </div>
      <div class="card-body">
        <h6>For Developers:</h6>
        <ol>
          <li>When an error occurs, check the error message for the error code (e.g., <code>[CAT001]</code>)</li>
          <li>Look up the error code in this reference to understand the issue</li>
          <li>Check the Error Logs page for detailed context and stack traces</li>
          <li>Use the context data to debug the specific issue</li>
        </ol>

        <h6 class="mt-4">For AI Assistants:</h6>
        <p>When debugging issues, always:</p>
        <ul>
          <li>Ask the user for the specific error code shown</li>
          <li>Reference this page to understand the error category and cause</li>
          <li>Check the error logs for detailed context</li>
          <li>Provide targeted solutions based on the error code</li>
        </ul>

        <h6 class="mt-4">Error Code Format:</h6>
        <p><code>[PREFIX###]</code> where:</p>
        <ul>
          <li><strong>PREFIX</strong> = Module (CAT, PROD, ORD, USER, etc.)</li>
          <li><strong>###</strong> = Specific error number</li>
          <li><strong>000-099</strong> = Info/Warning logs</li>
          <li><strong>100-199</strong> = Success logs</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
