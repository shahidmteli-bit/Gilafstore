<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Categories — Admin';
$adminPage = 'categories';
$categories = admin_get_categories();

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0">Categories</h4>
        <p class="text-muted mb-0">Organize products into intuitive collections.</p>
      </div>
      <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="fas fa-plus me-2"></i>Add Category</button>
    </div>

    <div class="card shadow-3 border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>C-Code</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($categories as $category): ?>
                <tr>
                  <td class="fw-semibold">#<?= (int)$category['id']; ?></td>
                  <td><?= htmlspecialchars($category['name']); ?></td>
                  <td>
                    <?php if (!empty($category['category_code'])): ?>
                      <span class="badge bg-primary"><?= htmlspecialchars($category['category_code']); ?></span>
                    <?php else: ?>
                      <span class="text-muted small">Not set</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button
                      class="btn btn-sm btn-outline-primary rounded-pill"
                      data-bs-toggle="modal"
                      data-bs-target="#editCategoryModal"
                      data-id="<?= (int)$category['id']; ?>"
                      data-name="<?= htmlspecialchars($category['name']); ?>"
                      data-code="<?= htmlspecialchars($category['category_code'] ?? ''); ?>"
                    >Edit</button>
                    <form action="<?= base_url('admin_actions.php'); ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this category?');">
                      <input type="hidden" name="action" value="delete_category" />
                      <input type="hidden" name="category_id" value="<?= (int)$category['id']; ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$categories): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted">No categories yet. Create your first category.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="<?= base_url('admin_actions.php'); ?>" method="post" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Add category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="create_category" />
          <div class="mb-3">
            <label class="form-label">Category name</label>
            <input type="text" name="name" id="categoryName" class="form-control" required onkeyup="generateCategoryCode()" />
          </div>
          <div class="mb-3">
            <label class="form-label">C-Code <small class="text-muted">(Auto-generated)</small></label>
            <input type="text" name="category_code" id="categoryCode" class="form-control" readonly style="background: #f3f4f6; font-weight: 600; text-transform: uppercase;" placeholder="Auto-generated from name" />
            <small class="text-muted">Code is automatically generated from the first capital letter of the category name</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="<?= base_url('admin_actions.php'); ?>" method="post" novalidate>
        <div class="modal-header">
          <h5 class="modal-title">Edit category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="update_category" />
          <input type="hidden" name="category_id" id="editCategoryId" />
          <div class="mb-3">
            <label class="form-label">Category name</label>
            <input type="text" name="name" class="form-control" id="editCategoryName" required onkeyup="generateEditCategoryCode()" />
          </div>
          <div class="mb-3">
            <label class="form-label">C-Code <small class="text-muted">(Auto-generated)</small></label>
            <input type="text" name="category_code" id="editCategoryCode" class="form-control" readonly style="background: #f3f4f6; font-weight: 600; text-transform: uppercase;" />
            <small class="text-muted">Code is automatically generated from the first capital letter of the category name</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // Auto-generate category code from first capital letter of name
  function generateCategoryCode() {
    const name = document.getElementById('categoryName').value;
    const codeField = document.getElementById('categoryCode');
    
    if (name) {
      // Find first capital letter
      const match = name.match(/[A-Z]/);
      const code = match ? match[0] : name.charAt(0).toUpperCase();
      codeField.value = code;
    } else {
      codeField.value = '';
    }
  }
  
  // Auto-generate category code for edit modal
  function generateEditCategoryCode() {
    const name = document.getElementById('editCategoryName').value;
    const codeField = document.getElementById('editCategoryCode');
    
    if (name) {
      // Find first capital letter
      const match = name.match(/[A-Z]/);
      const code = match ? match[0] : name.charAt(0).toUpperCase();
      codeField.value = code;
    } else {
      codeField.value = '';
    }
  }
  
  const editCategoryModal = document.getElementById('editCategoryModal');
  if (editCategoryModal) {
    editCategoryModal.addEventListener('show.bs.modal', (event) => {
      const button = event.relatedTarget;
      document.getElementById('editCategoryId').value = button.dataset.id;
      document.getElementById('editCategoryName').value = button.dataset.name;
      document.getElementById('editCategoryCode').value = button.dataset.code || '';
      // Generate code when modal opens if not already set
      if (!button.dataset.code) {
        generateEditCategoryCode();
      }
    });
  }
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
