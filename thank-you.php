<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Order Confirmation — Gilaf Store';
$activePage = '';

$confirmation = $_SESSION['order_confirmation'] ?? null;
if (!$confirmation) {
    redirect_with_message('/index.php', 'No recent orders found', 'info');
}

include __DIR__ . '/includes/new-header.php';
?>

<section class="py-5">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card shadow-5">
          <div class="card-body text-center p-5">
            <div class="display-4 text-success mb-3"><i class="fas fa-check-circle"></i></div>
            <h2 class="fw-semibold">Thank you for your order!</h2>
            <p class="text-muted mt-3">
              Your order <strong>#<?= (int)$confirmation['order_id']; ?></strong> has been received.
              You will receive a confirmation email with shipping updates shortly.
            </p>
            <div class="summary-card p-4 mt-4 text-start">
              <h6 class="fw-semibold mb-3">Order details</h6>
              <div class="d-flex justify-content-between mb-2">
                <span>Order ID</span>
                <span class="fw-semibold">#<?= (int)$confirmation['order_id']; ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span>Total amount</span>
                <span class="fw-semibold text-primary">$<?= number_format($confirmation['total'], 2); ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span>Payment method</span>
                <span class="fw-semibold text-uppercase"><?= htmlspecialchars($confirmation['payment_method']); ?></span>
              </div>
            </div>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4">
              <a href="<?= base_url('shop.php'); ?>" class="btn btn-outline-primary rounded-pill">Continue shopping</a>
              <a href="<?= base_url('user/orders.php'); ?>" class="btn btn-primary rounded-pill">View orders</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
unset($_SESSION['order_confirmation']);
include __DIR__ . '/includes/new-footer.php';
?>
