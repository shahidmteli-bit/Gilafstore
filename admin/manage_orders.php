<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'Manage Orders — Admin';
$adminPage = 'orders';
$orders = admin_get_orders();

// Get courier companies
$db = get_db_connection();
$couriers = $db->query("SELECT * FROM courier_companies WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* Professional Order Items Styling */
.order-items-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  line-height: 1.4;
}

.order-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 4px 0;
}

.item-name {
  color: #2c3e50;
  font-weight: 500;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.item-qty {
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  font-family: 'Courier New', monospace;
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 4px;
  white-space: nowrap;
  min-width: 32px;
  text-align: center;
}

/* Hover effect for better UX */
.order-item-row:hover .item-name {
  color: #1a3c34;
}

.order-item-row:hover .item-qty {
  background: #e2e8f0;
  color: #475569;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .order-items-list {
    font-size: 12px;
  }
  
  .item-qty {
    font-size: 11px;
    padding: 2px 6px;
  }
}
</style>

<section class="py-4">
  <div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-semibold mb-0">Orders</h4>
        <p class="text-muted mb-0">Review customer orders and update fulfillment status.</p>
      </div>
    </div>

    <div class="card shadow-3 border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Items & Qty</th>
                <th>Total</th>
                <th>Status</th>
                <th>Courier/Tracking</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <?php $details = get_order_with_items((int)$order['id']); ?>
                <tr>
                  <td class="fw-semibold">#<?= (int)$order['id']; ?></td>
                  <td><?= htmlspecialchars($order['customer'] ?? 'Guest'); ?></td>
                  <td>
                    <?php if (!empty($details['items'])): ?>
                      <div class="order-items-list">
                        <?php foreach ($details['items'] as $item): ?>
                          <div class="order-item-row">
                            <span class="item-name"><?= htmlspecialchars($item['name']); ?></span>
                            <span class="item-qty">×<?= (int)$item['quantity']; ?></span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="fw-semibold">₹<?= number_format($order['total_amount'], 2); ?></td>
                  <td>
                    <?php
                    // Determine a safe status value. Prefer explicit order_status; fall back to legacy status or a sensible default.
                    $status = $order['order_status'] ?? ($order['status'] ?? 'pending');

                    $statusColors = [
                      'pending' => 'warning',
                      'accepted' => 'info',
                      'shipped' => 'primary',
                      'delivered' => 'success'
                    ];
                    $color = $statusColors[$status] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $color; ?> text-capitalize"><?= htmlspecialchars($status); ?></span>
                  </td>
                  <td>
                    <?php if ($order['courier_company']): ?>
                      <strong><?= htmlspecialchars($order['courier_company']); ?></strong><br>
                      <small class="text-muted">Tracking: <?= htmlspecialchars($order['tracking_id'] ?? 'N/A'); ?></small>
                    <?php else: ?>
                      <span class="text-muted">Not assigned</span>
                    <?php endif; ?>
                  </td>
                  <td><small><?= htmlspecialchars(date('M d, Y', strtotime($order['created_at']))); ?></small></td>
                  <td>
                    <button class="btn btn-sm btn-primary" onclick="openOrderModal(<?= (int)$order['id']; ?>, '<?= htmlspecialchars($status); ?>', '<?= htmlspecialchars($order['courier_company'] ?? ''); ?>', '<?= htmlspecialchars($order['tracking_id'] ?? ''); ?>')">
                      <i class="fas fa-edit"></i> Update
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$orders): ?>
                <tr>
                  <td colspan="8" class="text-center text-muted py-4">No orders yet. Orders will appear here once customers check out.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Order Update Modal -->
<div class="modal fade" id="orderUpdateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="<?= base_url('admin_actions.php'); ?>" method="post" id="orderUpdateForm">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-edit"></i> Update Order Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="update_order_status" />
          <input type="hidden" name="order_id" id="modalOrderId" />
          
          <div class="mb-3">
            <label class="form-label">Order Status *</label>
            <select name="status" id="modalStatus" class="form-select" required onchange="toggleCourierFields()">
              <option value="cancelled">Cancelled</option>
              <option value="accepted">Accepted</option>
              <option value="shipped">Shipped (Picked Up)</option>
              <option value="delivered">Delivered</option>
            </select>
          </div>
          
          <div id="courierFields" style="display:none;">
            <div class="mb-3">
              <label class="form-label">Courier Company *</label>
              <select name="courier_company" id="modalCourier" class="form-select">
                <option value="">Select Courier Company</option>
                <?php foreach ($couriers as $courier): ?>
                  <option value="<?= htmlspecialchars($courier['name']); ?>"><?= htmlspecialchars($courier['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Search by typing courier name</small>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Tracking / Consignment ID *</label>
              <input type="text" name="tracking_id" id="modalTracking" class="form-control" placeholder="Enter tracking number" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Order</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openOrderModal(orderId, currentStatus, courier, tracking) {
  document.getElementById('modalOrderId').value = orderId;
  document.getElementById('modalStatus').value = currentStatus;
  document.getElementById('modalCourier').value = courier || '';
  document.getElementById('modalTracking').value = tracking || '';
  
  toggleCourierFields();
  
  const modal = new bootstrap.Modal(document.getElementById('orderUpdateModal'));
  modal.show();
}

function toggleCourierFields() {
  const status = document.getElementById('modalStatus').value;
  const courierFields = document.getElementById('courierFields');
  const courierSelect = document.getElementById('modalCourier');
  const trackingInput = document.getElementById('modalTracking');
  
  if (status === 'shipped') {
    courierFields.style.display = 'block';
    courierSelect.required = true;
    trackingInput.required = true;
  } else {
    courierFields.style.display = 'none';
    courierSelect.required = false;
    trackingInput.required = false;
  }
}

// Initialize Select2 for courier dropdown (if available)
if (typeof $ !== 'undefined' && $.fn.select2) {
  $('#modalCourier').select2({
    placeholder: 'Search courier company',
    allowClear: true,
    dropdownParent: $('#orderUpdateModal')
  });
}
</script>

<?php
include __DIR__ . '/../includes/admin_footer.php';
?>
