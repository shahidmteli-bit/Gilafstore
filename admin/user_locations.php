<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pageTitle = 'Customer Location Captures';
$adminPage = 'user_locations';
include __DIR__ . '/../includes/admin_header.php';

$db = get_db_connection();

// Auto-create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS user_location_captures (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NULL,
    guest_email   VARCHAR(255) NULL,
    lat           DECIMAL(10,7) NOT NULL,
    lng           DECIMAL(10,7) NOT NULL,
    google_maps_url VARCHAR(500) NOT NULL,
    ip_address    VARCHAR(45) NULL,
    captured_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_captured_at (captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Filters
$filterUser  = trim($_GET['user'] ?? '');
$filterDate  = trim($_GET['date'] ?? '');
$page        = max(1, (int)($_GET['p'] ?? 1));
$perPage     = 25;
$offset      = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($filterUser !== '') {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR ulc.guest_email LIKE ?)';
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
    $params[] = "%$filterUser%";
}
if ($filterDate !== '') {
    $where[]  = 'DATE(ulc.captured_at) = ?';
    $params[] = $filterDate;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)$db->prepare("SELECT COUNT(*) FROM user_location_captures ulc LEFT JOIN users u ON u.id = ulc.user_id $whereSQL")->execute($params) ? 0 : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM user_location_captures ulc LEFT JOIN users u ON u.id = ulc.user_id $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$paramsPage = array_merge($params, [$perPage, $offset]);
$rows = $db->prepare("
    SELECT ulc.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
    FROM user_location_captures ulc
    LEFT JOIN users u ON u.id = ulc.user_id
    $whereSQL
    ORDER BY ulc.captured_at DESC
    LIMIT ? OFFSET ?
");
$rows->execute($paramsPage);
$captures = $rows->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-content">
  <div class="container-fluid py-4">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
      <div>
        <h4 class="mb-1" style="font-weight:700;"><i class="fas fa-map-marker-alt" style="color:#1A3C34;"></i> Customer Location Captures</h4>
        <p class="text-muted mb-0" style="font-size:13px;">GPS coordinates captured when customers use "Detect My Location Automatically" at checkout.</p>
      </div>
      <span class="badge bg-secondary fs-6"><?= number_format($total) ?> records</span>
    </div>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-4 align-items-end">
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Search User / Email</label>
        <input type="text" name="user" class="form-control form-control-sm" value="<?= htmlspecialchars($filterUser) ?>" placeholder="Name or email...">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterDate) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-dark"><i class="fas fa-filter"></i> Filter</button>
        <a href="user_locations.php" class="btn btn-sm btn-outline-secondary ms-1">Clear</a>
      </div>
    </form>

    <!-- Table -->
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0 align-middle" style="font-size:13px;">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>User</th>
                <th>Coordinates</th>
                <th>Google Maps</th>
                <th>IP Address</th>
                <th>Captured At</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($captures)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">No location captures found.</td></tr>
              <?php else: ?>
              <?php foreach ($captures as $i => $row): ?>
              <tr>
                <td class="text-muted"><?= $offset + $i + 1 ?></td>
                <td>
                  <?php if ($row['user_name']): ?>
                    <strong><?= htmlspecialchars($row['user_name']) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($row['user_email'] ?? '') ?></small>
                    <?php if ($row['user_phone']): ?>
                    <br><small class="text-muted"><i class="fas fa-phone fa-xs"></i> <?= htmlspecialchars($row['user_phone']) ?></small>
                    <?php endif; ?>
                  <?php elseif ($row['guest_email']): ?>
                    <span class="badge bg-warning text-dark">Guest</span><br>
                    <small class="text-muted"><?= htmlspecialchars($row['guest_email']) ?></small>
                  <?php else: ?>
                    <span class="text-muted">Guest (anonymous)</span>
                  <?php endif; ?>
                </td>
                <td>
                  <code style="font-size:12px;"><?= htmlspecialchars($row['lat']) ?>, <?= htmlspecialchars($row['lng']) ?></code>
                </td>
                <td>
                  <a href="<?= htmlspecialchars($row['google_maps_url']) ?>" target="_blank" rel="noopener"
                     class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:11px;">
                    <i class="fas fa-map-marker-alt"></i> Open Map
                  </a>
                </td>
                <td><small class="text-muted"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></small></td>
                <td><small><?= date('d M Y, h:i A', strtotime($row['captured_at'])) ?></small></td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center flex-wrap">
        <?php for ($pg = 1; $pg <= $pages; $pg++): ?>
        <li class="page-item <?= $pg === $page ? 'active' : '' ?>">
          <a class="page-link" href="?p=<?= $pg ?>&user=<?= urlencode($filterUser) ?>&date=<?= urlencode($filterDate) ?>"><?= $pg ?></a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>

  </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
