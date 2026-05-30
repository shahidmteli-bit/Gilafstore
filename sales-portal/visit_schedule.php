<?php
/**
 * Sales Portal - Weekly Visit Schedule
 * Shows the executive's weekly visit plan (admin-managed),
 * today's assigned parties, and allows marking visits with GPS.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Visit Schedule';
$currentPage = 'visit_schedule';

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$todayDow = (int)date('w');

// Fetch weekly schedule
$weekSchedule = [];
try {
    $weekSchedule = db_fetch_all('SELECT * FROM sales_visit_schedules WHERE executive_id = ? ORDER BY day_of_week ASC', [$execId]);
} catch (PDOException $e) { /* table may not exist */ }

// Index by day
$scheduleByDay = [];
foreach ($weekSchedule as $ws) {
    $scheduleByDay[(int)$ws['day_of_week']] = $ws;
}

// Today's schedule
$todaySchedule = $scheduleByDay[$todayDow] ?? null;

// Today's parties (from scheduled district)
$todayParties = [];
if ($todaySchedule && !$todaySchedule['is_off'] && !empty($todaySchedule['district'])) {
    $todayParties = db_fetch_all('SELECT id, shop_name, owner_name, phone, address, district, outstanding_amount, latitude, longitude, google_maps_url, rating, rating_label FROM sales_parties WHERE created_by = ? AND district = ? AND is_active = 1 ORDER BY shop_name ASC', [$execId, $todaySchedule['district']]);
}

// Today's visits already marked
$todayVisits = [];
try {
    $visitRows = db_fetch_all('SELECT party_id, outcome, reached_at, recovery_amount FROM sales_party_visits WHERE executive_id = ? AND visit_date = CURDATE()', [$execId]);
    foreach ($visitRows as $vr) {
        $todayVisits[(int)$vr['party_id']] = $vr;
    }
} catch (PDOException $e) { /* table may not exist */ }

// Handle mark visit via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_visit'])) {
    header('Content-Type: application/json');
    $vPartyId = (int)($_POST['party_id'] ?? 0);
    $vOutcome = $_POST['outcome'] ?? 'visited';
    $vLat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $vLng = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $vNotes = trim($_POST['notes'] ?? '');
    $vRecovery = (float)($_POST['recovery_amount'] ?? 0);

    if ($vPartyId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid party.']);
        exit;
    }

    try {
        // Calculate distance if party has coordinates
        $distMeters = null;
        if ($vLat && $vLng) {
            $partyCoords = db_fetch('SELECT latitude, longitude FROM sales_parties WHERE id = ?', [$vPartyId]);
            if ($partyCoords && $partyCoords['latitude'] && $partyCoords['longitude']) {
                $distMeters = (int)haversineDistance($vLat, $vLng, (float)$partyCoords['latitude'], (float)$partyCoords['longitude']);
            }
        }

        // Upsert visit
        db_query('INSERT INTO sales_party_visits (executive_id, party_id, visit_date, reached_at, latitude, longitude, distance_meters, outcome, recovery_amount, notes) VALUES (?,?,CURDATE(),NOW(),?,?,?,?,?,?) ON DUPLICATE KEY UPDATE reached_at=NOW(), latitude=VALUES(latitude), longitude=VALUES(longitude), distance_meters=VALUES(distance_meters), outcome=VALUES(outcome), recovery_amount=VALUES(recovery_amount), notes=VALUES(notes)', [
            $execId, $vPartyId, $vLat, $vLng, $distMeters, $vOutcome, $vRecovery, $vNotes
        ]);

        // Update party's last_visit_date
        db_query('UPDATE sales_parties SET last_visit_date = CURDATE() WHERE id = ?', [$vPartyId]);

        echo json_encode(['success' => true, 'message' => 'Visit marked!', 'distance' => $distMeters]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Haversine distance function (meters)
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

include __DIR__ . '/includes/header.php';

// Build party data JSON for JS distance calculation and sorting
$partyGeoData = [];
foreach ($todayParties as $idx => $tp) {
    $partyGeoData[] = [
        'id' => (int)$tp['id'],
        'lat' => $tp['latitude'] ? (float)$tp['latitude'] : null,
        'lng' => $tp['longitude'] ? (float)$tp['longitude'] : null,
        'dist' => null,
    ];
}

$visitedCount = count($todayVisits);
$totalCount = count($todayParties);
$pendingCount = $totalCount - $visitedCount;
$progressPct = $totalCount > 0 ? round(($visitedCount / $totalCount) * 100) : 0;
?>

<!-- Weekly Schedule Overview -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-calendar-week"></i> Weekly Schedule</h3>
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;">
        <?php for ($d = 0; $d < 7; $d++):
            $sch = $scheduleByDay[$d] ?? null;
            $isToday = $d === $todayDow;
            $isOff = $sch && $sch['is_off'];
            $bgColor = $isToday ? 'var(--sp-primary)' : ($isOff ? '#f3f4f6' : '#f0fdf4');
            $textColor = $isToday ? '#fff' : ($isOff ? '#9ca3af' : '#1A3C34');
        ?>
        <div style="background:<?= $bgColor ?>;color:<?= $textColor ?>;border-radius:10px;padding:8px 4px;text-align:center;<?= $isToday ? 'box-shadow:0 2px 8px rgba(26,60,52,0.3);' : '' ?>">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;"><?= substr($dayNames[$d], 0, 3) ?></div>
            <?php if ($isOff): ?>
                <div style="font-size:10px;margin-top:2px;">Off</div>
            <?php elseif ($sch): ?>
                <div style="font-size:10px;margin-top:2px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($sch['district']) ?>"><?= htmlspecialchars($sch['district']) ?></div>
            <?php else: ?>
                <div style="font-size:10px;margin-top:2px;opacity:0.5;">—</div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Today's Assignment -->
<?php if ($todaySchedule): ?>
<div class="sp-card sp-mb-16" style="border-left:4px solid var(--sp-primary);">
    <div class="sp-card-header">
        <h3><i class="fas fa-map-marker-alt"></i> Today — <?= htmlspecialchars($dayNames[$todayDow]) ?></h3>
    </div>
    <?php if ($todaySchedule['is_off']): ?>
        <div style="text-align:center;padding:20px 0;">
            <i class="fas fa-coffee" style="font-size:32px;color:#6b7280;"></i>
            <div style="font-weight:700;font-size:16px;margin-top:8px;">Week Off</div>
            <div style="font-size:13px;color:#6b7280;">Enjoy your day!</div>
        </div>
    <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <div>
                <div style="font-size:20px;font-weight:800;color:var(--sp-primary);"><?= htmlspecialchars($todaySchedule['district']) ?></div>
                <?php if ($todaySchedule['area_name']): ?>
                    <div style="font-size:13px;color:#6b7280;"><?= htmlspecialchars($todaySchedule['area_name']) ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div style="font-size:20px;font-weight:800;color:#059669;"><?= $visitedCount ?><span style="font-size:14px;color:#6b7280;font-weight:400;"> / <?= $totalCount ?></span></div>
                <div style="font-size:11px;color:#6b7280;">Visited</div>
            </div>
        </div>

        <!-- Visit Summary Cards -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;">
            <div style="background:#eff6ff;border-radius:10px;padding:10px;text-align:center;">
                <div style="font-size:18px;font-weight:800;color:#2563eb;"><?= $totalCount ?></div>
                <div style="font-size:10px;color:#3b82f6;font-weight:600;">Total Parties</div>
            </div>
            <div style="background:#ecfdf5;border-radius:10px;padding:10px;text-align:center;">
                <div style="font-size:18px;font-weight:800;color:#059669;"><?= $visitedCount ?></div>
                <div style="font-size:10px;color:#047857;font-weight:600;">Visited</div>
            </div>
            <div style="background:<?= $pendingCount > 0 ? '#fef3c7' : '#ecfdf5' ?>;border-radius:10px;padding:10px;text-align:center;">
                <div style="font-size:18px;font-weight:800;color:<?= $pendingCount > 0 ? '#d97706' : '#059669' ?>;"><?= $pendingCount ?></div>
                <div style="font-size:10px;color:<?= $pendingCount > 0 ? '#b45309' : '#047857' ?>;font-weight:600;">Remaining</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <?php if ($totalCount > 0): ?>
        <div style="height:6px;background:#f3f4f6;border-radius:3px;margin-bottom:8px;">
            <div style="height:100%;background:#059669;border-radius:3px;width:<?= $progressPct ?>%;transition:width 0.5s;"></div>
        </div>
        <div style="text-align:right;font-size:10px;color:#6b7280;margin-bottom:12px;"><?= $progressPct ?>% complete</div>
        <?php endif; ?>

        <!-- GPS Status -->
        <div id="gpsLocationStatus" style="padding:8px 12px;border-radius:8px;background:#f3f4f6;margin-bottom:12px;font-size:12px;color:#6b7280;text-align:center;">
            <i class="fas fa-spinner fa-spin"></i> Getting your location for distance calculation...
        </div>

        <!-- Party List for Today -->
        <?php if (empty($todayParties)): ?>
            <div style="text-align:center;padding:20px 0;color:#6b7280;">
                <i class="fas fa-store-slash" style="font-size:28px;margin-bottom:8px;display:block;color:#d1d5db;"></i>
                <div style="font-weight:600;font-size:14px;">No parties found</div>
                <div style="font-size:12px;">No parties in <?= htmlspecialchars($todaySchedule['district']) ?> district.</div>
            </div>
        <?php else: ?>
            <!-- Sort info banner -->
            <div id="sortBanner" style="padding:8px 12px;border-radius:8px;background:#eff6ff;margin-bottom:12px;font-size:11px;color:#2563eb;text-align:center;display:none;">
                <i class="fas fa-sort-amount-down"></i> <span id="sortBannerText">Sorted by nearest first</span>
            </div>

            <div id="partyListContainer">
            <?php foreach ($todayParties as $idx => $tp):
                $visited = isset($todayVisits[$tp['id']]);
                $visit = $visited ? $todayVisits[$tp['id']] : null;
                $hasOutstanding = (float)($tp['outstanding_amount'] ?? 0) > 0;
                $hasCoords = !empty($tp['latitude']) && !empty($tp['longitude']);
                $hasPhone = !empty($tp['phone']);
                $hasAddress = !empty($tp['address']);
                $rating = (float)($tp['rating'] ?? 0);
                $ratingLabel = $tp['rating_label'] ?? 'good';
                $serialNum = $idx + 1;
                $mapsUrl = '';
                if (!empty($tp['google_maps_url'])) {
                    $mapsUrl = $tp['google_maps_url'];
                } elseif ($hasCoords) {
                    $mapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $tp['latitude'] . ',' . $tp['longitude'];
                }
            ?>
            <div class="party-card" id="party-row-<?= $tp['id'] ?>" data-party-id="<?= $tp['id'] ?>" data-dist="999999" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px;margin-bottom:10px;<?= $visited ? 'opacity:0.7;border-left:4px solid #059669;' : 'border-left:4px solid var(--sp-primary);' ?>">

                <!-- Top Row: Serial + Name + Distance -->
                <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;">
                    <!-- Serial Badge -->
                    <div style="min-width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex-shrink:0;<?= $visited ? 'background:#ecfdf5;color:#059669;' : 'background:var(--sp-primary);color:#fff;' ?>" class="serial-badge">
                        <?php if ($visited): ?>
                            <i class="fas fa-check" style="font-size:11px;"></i>
                        <?php else: ?>
                            <span class="serial-num"><?= $serialNum ?></span>
                        <?php endif; ?>
                    </div>
                    <!-- Name + Owner -->
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:14px;color:#111827;line-height:1.2;"><?= htmlspecialchars($tp['shop_name']) ?></div>
                        <div style="font-size:12px;color:#6b7280;margin-top:1px;">
                            <i class="fas fa-user" style="width:12px;font-size:10px;color:#9ca3af;"></i> <?= htmlspecialchars($tp['owner_name']) ?>
                        </div>
                    </div>
                    <!-- Distance Badge -->
                    <div id="distance-<?= $tp['id'] ?>" style="display:none;flex-shrink:0;">
                        <div class="dist-badge" style="background:#eff6ff;color:#2563eb;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;">
                            <i class="fas fa-location-arrow" style="font-size:9px;"></i> <span class="dist-text">...</span>
                        </div>
                    </div>
                </div>

                <!-- 5-Star Rating -->
                <div style="margin-bottom:8px;">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <?php if ($rating >= $s): ?>
                            <i class="fas fa-star" style="color:#f59e0b;font-size:13px;"></i>
                        <?php elseif ($rating >= $s - 0.5): ?>
                            <i class="fas fa-star-half-alt" style="color:#f59e0b;font-size:13px;"></i>
                        <?php else: ?>
                            <i class="far fa-star" style="color:#d1d5db;font-size:13px;"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <span style="font-size:10px;color:#6b7280;margin-left:4px;">
                        <?php if ($rating > 0): ?>(<?= number_format($rating, 1) ?>)<?php endif; ?>
                        <span style="padding:2px 6px;border-radius:4px;font-size:9px;font-weight:700;margin-left:2px;<?php
                            if ($ratingLabel === 'good') echo 'background:#ecfdf5;color:#059669;';
                            elseif ($ratingLabel === 'average') echo 'background:#fef3c7;color:#92400e;';
                            elseif ($ratingLabel === 'low') echo 'background:#fef2f2;color:#dc2626;';
                            elseif ($ratingLabel === 'blocked') echo 'background:#fee2e2;color:#991b1b;';
                        ?>"><?= ucfirst($ratingLabel) ?></span>
                    </span>
                </div>

                <!-- Address -->
                <?php if ($hasAddress): ?>
                <div style="font-size:11px;color:#4b5563;margin-bottom:6px;line-height:1.4;">
                    <i class="fas fa-map-marker-alt" style="color:#dc2626;width:14px;font-size:10px;"></i>
                    <?= htmlspecialchars($tp['address']) ?>
                    <?php if (!empty($tp['district'])): ?>
                        <span style="color:#9ca3af;">· <?= htmlspecialchars($tp['district']) ?></span>
                    <?php endif; ?>
                </div>
                <?php elseif (!empty($tp['district'])): ?>
                <div style="font-size:11px;color:#6b7280;margin-bottom:6px;">
                    <i class="fas fa-map-marker-alt" style="color:#dc2626;width:14px;font-size:10px;"></i>
                    <?= htmlspecialchars($tp['district']) ?>
                </div>
                <?php endif; ?>

                <!-- Phone -->
                <?php if ($hasPhone): ?>
                <div style="font-size:12px;color:#374151;margin-bottom:6px;">
                    <i class="fas fa-phone-alt" style="color:#059669;width:14px;font-size:10px;"></i>
                    <a href="tel:<?= htmlspecialchars($tp['phone']) ?>" style="color:#374151;text-decoration:none;"><?= htmlspecialchars($tp['phone']) ?></a>
                </div>
                <?php endif; ?>

                <!-- Outstanding -->
                <?php if ($hasOutstanding): ?>
                <div style="font-size:11px;color:#dc2626;font-weight:700;margin-bottom:6px;">
                    <i class="fas fa-exclamation-circle" style="width:14px;font-size:10px;"></i>
                    Outstanding: ₹<?= number_format($tp['outstanding_amount'], 0) ?>
                </div>
                <?php endif; ?>

                <!-- Visit status if visited -->
                <?php if ($visited && $visit): ?>
                <div style="background:#ecfdf5;border-radius:8px;padding:6px 10px;margin-bottom:8px;font-size:11px;color:#059669;font-weight:600;">
                    <i class="fas fa-check-circle"></i>
                    <?= ucfirst(str_replace('_', ' ', $visit['outcome'])) ?>
                    at <?= date('h:i A', strtotime($visit['reached_at'])) ?>
                    <?php if ($visit['recovery_amount'] > 0): ?>
                        · Recovery: ₹<?= number_format($visit['recovery_amount'], 0) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if ($hasPhone): ?>
                    <a href="tel:<?= htmlspecialchars($tp['phone']) ?>" style="flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 0;border-radius:10px;background:#ecfdf5;color:#059669;text-decoration:none;font-size:13px;font-weight:700;border:1px solid #a7f3d0;">
                        <i class="fas fa-phone-alt"></i> Call
                    </a>
                    <?php endif; ?>

                    <?php if (!empty($mapsUrl)): ?>
                    <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" style="flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 0;border-radius:10px;background:#eff6ff;color:#2563eb;text-decoration:none;font-size:13px;font-weight:700;border:1px solid #bfdbfe;">
                        <i class="fas fa-directions"></i> Directions
                    </a>
                    <?php elseif (!$hasCoords): ?>
                    <div style="flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 0;border-radius:10px;background:#f3f4f6;color:#9ca3af;font-size:12px;font-weight:600;">
                        <i class="fas fa-map-marker-alt"></i> No location
                    </div>
                    <?php endif; ?>

                    <?php if (!$visited): ?>
                    <button type="button" onclick="openVisitModal(<?= $tp['id'] ?>, '<?= addslashes(htmlspecialchars($tp['shop_name'])) ?>')" style="flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 0;border-radius:10px;background:var(--sp-primary);color:#fff;border:none;cursor:pointer;font-size:13px;font-weight:700;">
                        <i class="fas fa-map-pin"></i> Reached
                    </button>
                    <?php else: ?>
                    <div style="flex:1;min-width:0;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 0;border-radius:10px;background:#ecfdf5;color:#059669;font-size:13px;font-weight:700;border:1px solid #a7f3d0;">
                        <i class="fas fa-check-circle"></i> Visited
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="sp-card sp-mb-16">
    <div class="sp-empty">
        <i class="fas fa-calendar-times"></i>
        <h3>No Schedule Set</h3>
        <p>Your weekly visit schedule has not been configured by admin yet. Contact your manager.</p>
    </div>
</div>
<?php endif; ?>

<!-- Visit Modal -->
<div id="visitModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:2000;padding:20px;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:20px;padding:24px;max-width:380px;width:100%;margin:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:16px;font-weight:700;margin:0;"><i class="fas fa-map-pin" style="color:var(--sp-primary);"></i> Mark Visit</h3>
            <button type="button" onclick="closeVisitModal()" style="background:none;border:none;font-size:18px;cursor:pointer;color:#6b7280;"><i class="fas fa-times"></i></button>
        </div>
        <div id="visitPartyName" style="font-weight:600;font-size:15px;margin-bottom:12px;"></div>
        <input type="hidden" id="visitPartyId">

        <div style="margin-bottom:12px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Visit Outcome</label>
            <select id="visitOutcome" style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:10px;font-size:14px;">
                <option value="visited">Visited</option>
                <option value="ordered">Ordered</option>
                <option value="payment_collected">Payment Collected</option>
                <option value="no_order">No Order</option>
                <option value="not_available">Not Available</option>
            </select>
        </div>

        <div style="margin-bottom:12px;" id="recoveryGroup">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Recovery Amount (₹)</label>
            <input type="number" id="visitRecovery" value="0" min="0" step="0.01" style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:10px;font-size:14px;box-sizing:border-box;">
        </div>

        <div style="margin-bottom:16px;">
            <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Notes</label>
            <textarea id="visitNotes" rows="2" placeholder="Optional notes..." style="width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:10px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
        </div>

        <div id="visitGpsStatus" style="margin-bottom:12px;font-size:12px;color:#6b7280;text-align:center;">
            <i class="fas fa-spinner fa-spin"></i> Getting your location...
        </div>

        <button type="button" id="visitSubmitBtn" onclick="submitVisit()" style="width:100%;padding:14px;background:var(--sp-primary);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;">
            <i class="fas fa-check-circle"></i> Confirm Visit
        </button>
    </div>
</div>

<script>
var visitLat = null, visitLng = null;
var myLat = null, myLng = null;
var partyGeo = <?= json_encode($partyGeoData) ?>;
var sortedOnce = false;

// Format distance nicely
function formatDistance(meters) {
    if (meters === null || meters === undefined) return '';
    if (meters < 1000) return Math.round(meters) + ' m away';
    return (meters / 1000).toFixed(1) + ' km away';
}

// Haversine in JS
function haversineDist(lat1, lon1, lat2, lon2) {
    var R = 6371000;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// Update distances for all parties and sort nearest-first
function updateAllDistances() {
    if (!myLat || !myLng) return;

    // Calculate distances
    partyGeo.forEach(function(p) {
        var el = document.getElementById('distance-' + p.id);
        if (!el) return;
        if (p.lat && p.lng) {
            var dist = haversineDist(myLat, myLng, p.lat, p.lng);
            p.dist = dist;
            el.querySelector('.dist-text').textContent = formatDistance(dist);
            // Color code by distance
            var badge = el.querySelector('.dist-badge');
            if (badge) {
                if (dist < 500) { badge.style.background = '#ecfdf5'; badge.style.color = '#059669'; }
                else if (dist < 2000) { badge.style.background = '#eff6ff'; badge.style.color = '#2563eb'; }
                else if (dist < 5000) { badge.style.background = '#fef3c7'; badge.style.color = '#92400e'; }
                else { badge.style.background = '#fef2f2'; badge.style.color = '#dc2626'; }
            }
            el.style.display = 'block';
            // Set data-dist on card for sorting
            var card = document.getElementById('party-row-' + p.id);
            if (card) card.setAttribute('data-dist', Math.round(dist));
        } else {
            p.dist = 999999;
            el.querySelector('.dist-text').textContent = 'No GPS data';
            var badge = el.querySelector('.dist-badge');
            if (badge) { badge.style.background = '#f3f4f6'; badge.style.color = '#9ca3af'; }
            el.style.display = 'block';
        }
    });

    // Sort DOM elements by distance (nearest first)
    sortPartyCards();
}

// Sort party cards in DOM by distance
function sortPartyCards() {
    var container = document.getElementById('partyListContainer');
    if (!container) return;

    var cards = Array.from(container.querySelectorAll('.party-card'));
    if (cards.length === 0) return;

    // Sort: visited cards go to bottom, then by distance
    cards.sort(function(a, b) {
        var distA = parseInt(a.getAttribute('data-dist')) || 999999;
        var distB = parseInt(b.getAttribute('data-dist')) || 999999;
        // Visited cards (opacity 0.7) go to bottom
        var visitedA = a.style.opacity === '0.7' ? 1 : 0;
        var visitedB = b.style.opacity === '0.7' ? 1 : 0;
        if (visitedA !== visitedB) return visitedA - visitedB;
        return distA - distB;
    });

    // Re-append in sorted order
    cards.forEach(function(card) {
        container.appendChild(card);
    });

    // Renumber serial badges
    var serialNum = 1;
    cards.forEach(function(card) {
        var numEl = card.querySelector('.serial-num');
        if (numEl) {
            numEl.textContent = serialNum;
        }
        serialNum++;
    });

    // Show sort banner
    if (!sortedOnce) {
        var banner = document.getElementById('sortBanner');
        if (banner) {
            banner.style.display = 'block';
            var withCoords = partyGeo.filter(function(p) { return p.lat && p.lng; }).length;
            var total = partyGeo.length;
            var text = 'Sorted nearest → farthest';
            if (withCoords < total) {
                text += ' (' + (total - withCoords) + ' without GPS at bottom)';
            }
            document.getElementById('sortBannerText').textContent = text;
        }
        sortedOnce = true;
    }
}

// Get live location on page load
(function() {
    var statusEl = document.getElementById('gpsLocationStatus');
    if (!statusEl) return;
    if (!navigator.geolocation) {
        statusEl.innerHTML = '<span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> GPS not supported. Parties shown in default order.</span>';
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            myLat = pos.coords.latitude;
            myLng = pos.coords.longitude;
            statusEl.innerHTML = '<span style="color:#059669;"><i class="fas fa-check-circle"></i> Location locked (±' + pos.coords.accuracy.toFixed(0) + 'm) — sorted nearest first</span>';
            updateAllDistances();
            // Keep updating every 30 seconds
            setInterval(function() {
                navigator.geolocation.getCurrentPosition(function(p) {
                    myLat = p.coords.latitude;
                    myLng = p.coords.longitude;
                    updateAllDistances();
                }, function(){}, { enableHighAccuracy: true, timeout: 10000 });
            }, 30000);
        },
        function(err) {
            var msg = 'Location unavailable — parties shown in default order';
            if (err.code === 1) msg = 'Location permission denied. Enable GPS for nearest-first sorting.';
            else if (err.code === 2) msg = 'Location unavailable. Check GPS settings.';
            else if (err.code === 3) msg = 'Location request timed out. Showing default order.';
            statusEl.innerHTML = '<span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> ' + msg + '</span>';
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
})();

function openVisitModal(partyId, partyName) {
    document.getElementById('visitPartyId').value = partyId;
    document.getElementById('visitPartyName').textContent = partyName;
    document.getElementById('visitOutcome').value = 'visited';
    document.getElementById('visitRecovery').value = '0';
    document.getElementById('visitNotes').value = '';
    document.getElementById('visitModal').style.display = 'flex';
    visitLat = null;
    visitLng = null;

    var statusEl = document.getElementById('visitGpsStatus');
    if (myLat && myLng) {
        visitLat = myLat;
        visitLng = myLng;
        statusEl.innerHTML = '<span style="color:#059669;"><i class="fas fa-check-circle"></i> Location ready</span>';
    } else if (navigator.geolocation) {
        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting your location...';
        navigator.geolocation.getCurrentPosition(
            function(pos) {
                visitLat = pos.coords.latitude;
                visitLng = pos.coords.longitude;
                myLat = visitLat; myLng = visitLng;
                statusEl.innerHTML = '<span style="color:#059669;"><i class="fas fa-check-circle"></i> Location captured (±' + pos.coords.accuracy.toFixed(0) + 'm)</span>';
                updateAllDistances();
            },
            function(err) {
                statusEl.innerHTML = '<span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> Location unavailable — visit will still be recorded</span>';
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        statusEl.innerHTML = '<span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> GPS not supported</span>';
    }
}

function closeVisitModal() {
    document.getElementById('visitModal').style.display = 'none';
}

function submitVisit() {
    var partyId = document.getElementById('visitPartyId').value;
    var outcome = document.getElementById('visitOutcome').value;
    var recovery = document.getElementById('visitRecovery').value;
    var notes = document.getElementById('visitNotes').value;
    var btn = document.getElementById('visitSubmitBtn');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    var formData = new FormData();
    formData.append('mark_visit', '1');
    formData.append('party_id', partyId);
    formData.append('outcome', outcome);
    formData.append('recovery_amount', recovery);
    formData.append('notes', notes);
    if (visitLat) formData.append('latitude', visitLat);
    if (visitLng) formData.append('longitude', visitLng);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                closeVisitModal();
                var distMsg = '';
                if (data.distance !== null && data.distance !== undefined) {
                    distMsg = '\nDistance from party: ' + formatDistance(data.distance);
                }
                alert('Visit marked successfully!' + distMsg);
                // Reload to update counts and UI
                setTimeout(function() { location.reload(); }, 300);
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Visit';
        })
        .catch(function() {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Visit';
        });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
