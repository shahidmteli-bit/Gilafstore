<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = get_product($productId);

if (!$product) {
    redirect_with_message(base_url('admin/manage_products.php'), 'Product not found', 'danger');
}

$pageTitle = 'Edit Product — Admin';
$adminPage = 'products';
$categories = admin_get_categories();

include __DIR__ . '/../includes/admin_header.php';
?>

<section class="py-4">
  <div class="container-fluid" style="max-width: 920px;">
    <a href="<?= base_url('admin/manage_products.php'); ?>" class="btn btn-outline-secondary rounded-pill mb-3"><i class="fas fa-arrow-left me-2"></i>Back to products</a>
    <div class="card shadow-3 border-0">
      <div class="card-body p-4 p-lg-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h4 class="fw-semibold mb-1">Edit product</h4>
            <p class="text-muted mb-0">Update product details, pricing, or imagery.</p>
          </div>
        </div>
        <form action="<?= base_url('admin_actions.php'); ?>" method="post" enctype="multipart/form-data" class="row g-4" novalidate>
          <input type="hidden" name="action" value="update_product" />
          <input type="hidden" name="product_id" value="<?= (int)$product['id']; ?>" />
          <div class="col-md-6">
            <label class="form-label">Product name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']); ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
              <option value="">Select category</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id']; ?>" <?= (int)$product['category_id'] === (int)$category['id'] ? 'selected' : ''; ?>>
                  <?= htmlspecialchars($category['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Price (₹)</label>
            <input type="number" name="price" class="form-control" value="<?= htmlspecialchars($product['price']); ?>" step="0.01" required />
          </div>
          <div class="mb-3">
            <label class="form-label">GST Rate (%)</label>
            <input type="number" class="form-control" name="gst_rate" value="<?= htmlspecialchars($product['gst_rate'] ?? 5.00); ?>" step="0.01" min="0" max="100" />
            <small class="text-muted">Default: 5% for food items. Enter 0 for no GST.</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Stock</label>
            <input type="number" name="stock" class="form-control" min="0" value="<?= (int)$product['stock']; ?>" required />
          </div>
          <div class="col-md-6">
            <label class="form-label">EAN Number</label>
            <input type="text" name="ean" class="form-control" value="<?= htmlspecialchars($product['ean'] ?? ''); ?>" maxlength="13" pattern="[0-9]{8,13}" />
            <small class="text-muted">8-13 digit barcode number (optional)</small>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($product['description']); ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Product image</label>
            <input type="file" name="image" class="form-control" accept="image/*" />
            <small class="text-muted">Leave blank to keep current image. Current image shown below.</small>
          </div>
          <div class="col-12">
            <img src="<?= asset_url('images/products/' . ltrim($product['image'], '/')); ?>" alt="<?= htmlspecialchars($product['name']); ?>" class="rounded shadow-2" style="max-width: 260px;" />
          </div>
          <div class="col-12 d-flex gap-3">
            <button type="submit" class="btn btn-primary rounded-pill">Save changes</button>
            <a href="<?= base_url('admin/manage_products.php'); ?>" class="btn btn-outline-secondary rounded-pill">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
