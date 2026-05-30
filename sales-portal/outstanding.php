<?php
/**
 * Sales Portal - Outstanding Tab
 * Shows all parties with outstanding amounts, district-wise filter,
 * and links to Record Payment Collection.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Outstanding';
$currentPage = 'outstanding';

$districtFilter = trim($_GET['district'] ?? '');

// Get distinct districts (include inactive parties with outstanding)
$districts = db_fetch_all('SELECT DISTINCT district FROM sales_parties WHERE created_by = ? AND district != "" AND (is_active = 1 OR outstanding_amount > 0) ORDER BY district ASC', [$execId]);

// Fetch parties with outstanding > 0 (include inactive parties with dues)
$sql = 'SELECT id, shop_name, owner_name, phone, district, outstanding_amount, credit_limit, party_code, latitude, longitude, google_maps_url, address, is_active FROM sales_parties WHERE created_by = ? AND outstanding_amount > 0';
$params = [$execId];
if ($districtFilter) {
    $sql .= ' AND district = ?';
    $params[] = $districtFilter;
}
$sql .= ' ORDER BY outstanding_amount DESC';
$parties = db_fetch_all($sql, $params);

// Total outstanding
$totalOutstanding = 0;
foreach ($parties as $p) $totalOutstanding += (float)$p['outstanding_amount'];

// District-wise summary
$districtSummary = [];
foreach ($parties as $p) {
    $d = $p['district'] ?: 'Unknown';
    if (!isset($districtSummary[$d])) $districtSummary[$d] = 0;
    $districtSummary[$d] += (float)$p['outstanding_amount'];
}
arsort($districtSummary);

// Build geo data for JS distance sorting
$partyGeoData = [];
foreach ($parties as $p) {
    $partyGeoData[] = [
        'id' => (int)$p['id'],
        'lat' => $p['latitude'] ? (float)$p['latitude'] : null,
        'lng' => $p['longitude'] ? (float)$p['longitude'] : null,
    ];
}

include __DIR__ . '/includes/header.php';
?>

<!-- Total Outstanding Card -->
<div class="sp-card sp-mb-16" style="background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;border:none;">
    <div style="text-align:center;padding:8px 0;">
        <div style="font-size:12px;opacity:0.85;text-transform:uppercase;letter-spacing:1px;">Total Outstanding</div>
        <div style="font-size:32px;font-weight:800;">₹<?= number_format($totalOutstanding, 0) ?></div>
        <div style="font-size:13px;opacity:0.85;"><?= count($parties) ?> parties with dues</div>
    </div>
</div>

<!-- District Filter -->
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-filter"></i> District Filter</h3>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="<?= sales_base_url('outstanding.php') ?>" class="sp-btn <?= !$districtFilter ? 'sp-btn-primary' : 'sp-btn-outline' ?> sp-btn-sm">All</a>
        <?php foreach ($districts as $d): ?>
            <a href="<?= sales_base_url('outstanding.php?district=' . urlencode($d['district'])) ?>" 
               class="sp-btn <?= $districtFilter === $d['district'] ? 'sp-btn-primary' : 'sp-btn-outline' ?> sp-btn-sm">
                <?= htmlspecialchars($d['district']) ?>
                <?php if (isset($districtSummary[$d['district']])): ?>
                    <span style="font-size:10px;opacity:0.8;margin-left:4px;">₹<?= number_format($districtSummary[$d['district']], 0) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- District-wise Summary -->
<?php if (!$districtFilter && count($districtSummary) > 1): ?>
<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-map-marked-alt"></i> District-wise Outstanding</h3>
    </div>
    <?php foreach ($districtSummary as $dist => $amt): 
        $pct = $totalOutstanding > 0 ? ($amt / $totalOutstanding) * 100 : 0;
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;">
        <div>
            <div style="font-weight:600;font-size:14px;"><?= htmlspecialchars($dist) ?></div>
            <div style="width:120px;height:4px;background:#f3f4f6;border-radius:2px;margin-top:4px;">
                <div style="height:100%;background:#dc2626;border-radius:2px;width:<?= min($pct, 100) ?>%;"></div>
            </div>
        </div>
        <div style="font-weight:700;color:#dc2626;">₹<?= number_format($amt, 0) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- GPS Status -->
<div id="gpsStatus" style="padding:8px 12px;border-radius:8px;background:#f3f4f6;margin-bottom:12px;font-size:12px;color:#6b7280;text-align:center;">
    <i class="fas fa-spinner fa-spin"></i> Getting your location for nearest-first sorting...
</div>

<!-- Sort Banner -->
<div id="sortBanner" style="padding:8px 12px;border-radius:8px;background:#eff6ff;margin-bottom:12px;font-size:11px;color:#2563eb;text-align:center;display:none;">
    <i class="fas fa-sort-amount-down"></i> <span id="sortBannerText">Sorted nearest → farthest</span>
</div>

<!-- Party List -->
<?php if (empty($parties)): ?>
<div class="sp-card">
    <div class="sp-empty">
        <i class="fas fa-check-circle" style="color:#059669;"></i>
        <h3>No Outstanding Dues</h3>
        <p><?= $districtFilter ? 'No outstanding in ' . htmlspecialchars($districtFilter) . '.' : 'All parties have cleared their dues.' ?></p>
    </div>
</div>
<?php else: ?>
<div class="sp-card">
    <div class="sp-card-header">
        <h3><i class="fas fa-users"></i> Parties with Outstanding (<?= count($parties) ?>)</h3>
    </div>
    <div id="partyListContainer">
    <?php foreach ($parties as $p): 
        $pctUsed = $p['credit_limit'] > 0 ? ($p['outstanding_amount'] / $p['credit_limit']) * 100 : 0;
        $barColor = $pctUsed >= 100 ? '#dc2626' : ($pctUsed >= 80 ? '#f59e0b' : '#059669');
        $hasCoords = !empty($p['latitude']) && !empty($p['longitude']);
        $hasPhone = !empty($p['phone']);
        $mapsUrl = '';
        if (!empty($p['google_maps_url'])) $mapsUrl = $p['google_maps_url'];
        elseif ($hasCoords) $mapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $p['latitude'] . ',' . $p['longitude'];
    ?>
    <div class="outstanding-card" data-party-id="<?= $p['id'] ?>" data-dist="999999" style="padding:14px 0;border-bottom:1px solid #f3f4f6;">
        <div style="display:flex;align-items:flex-start;gap:12px;">
            <div style="width:40px;height:40px;background:linear-gradient(135deg,#1A3C34,#2d5a4d);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:16px;flex-shrink:0;">
                <?= strtoupper(substr($p['shop_name'], 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:14px;">
                    <?= htmlspecialchars($p['shop_name']) ?>
                    <?php if (!$p['is_active']): ?>
                        <span style="display:inline-block;background:#fee2e2;color:#dc2626;font-size:9px;font-weight:700;padding:2px 6px;border-radius:4px;margin-left:4px;">INACTIVE</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($p['owner_name']) ?> · <?= htmlspecialchars($p['district']) ?></div>
                <!-- Distance Badge -->
                <div id="dist-<?= $p['id'] ?>" style="display:none;margin-top:3px;">
                    <span class="dist-badge" style="display:inline-block;background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;">
                        <i class="fas fa-location-arrow" style="font-size:8px;"></i> <span class="dist-text">...</span>
                    </span>
                </div>
                <?php if ($p['credit_limit'] > 0): ?>
                <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                    <div style="flex:1;height:4px;background:#f3f4f6;border-radius:2px;">
                        <div style="height:100%;background:<?= $barColor ?>;border-radius:2px;width:<?= min($pctUsed, 100) ?>%;"></div>
                    </div>
                    <span style="font-size:10px;color:#6b7280;"><?= round($pctUsed) ?>%</span>
                </div>
                <?php endif; ?>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:18px;font-weight:800;color:#dc2626;">₹<?= number_format($p['outstanding_amount'], 0) ?></div>
            </div>
        </div>
        <!-- Action Buttons -->
        <div style="display:flex;gap:6px;margin-top:8px;margin-left:52px;flex-wrap:nowrap;">
            <?php if ($hasPhone): ?>
            <a href="tel:<?= htmlspecialchars($p['phone']) ?>" style="display:inline-flex;align-items:center;gap:4px;padding:7px 10px;border-radius:8px;background:#ecfdf5;color:#059669;text-decoration:none;font-size:11px;font-weight:700;border:1px solid #a7f3d0;white-space:nowrap;">
                <i class="fas fa-phone-alt"></i> Call
            </a>
            <?php endif; ?>
            <?php if (!empty($mapsUrl)): ?>
            <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:7px 10px;border-radius:8px;background:#eff6ff;color:#2563eb;text-decoration:none;font-size:11px;font-weight:700;border:1px solid #bfdbfe;white-space:nowrap;">
                <i class="fas fa-directions"></i> Route
            </a>
            <?php endif; ?>
            <a href="<?= sales_base_url('collect_payment.php?party_id=' . $p['id']) ?>" style="display:inline-flex;align-items:center;gap:4px;padding:7px 10px;border-radius:8px;background:#059669;color:#fff;text-decoration:none;font-size:11px;font-weight:700;white-space:nowrap;">
                <i class="fas fa-hand-holding-usd"></i> Collect
            </a>
            <span id="outShareBtn<?= $p['id'] ?>" onclick="shareOutstandingWhatsApp(<?= $p['id'] ?>,'<?= htmlspecialchars(addslashes($p['shop_name'])) ?>','<?= htmlspecialchars($p['party_code'] ?? '') ?>','<?= number_format($p['outstanding_amount'], 0) ?>','<?= htmlspecialchars($p['phone'] ?? '') ?>')" style="display:inline-flex;align-items:center;gap:4px;padding:7px 10px;border-radius:8px;background:#dcfce7;color:#25D366;font-size:11px;font-weight:700;border:1px solid #bbf7d0;cursor:pointer;white-space:nowrap;">
                <i class="fab fa-whatsapp"></i> Share
            </span>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
var myLat = null, myLng = null;
var partyGeo = <?= json_encode($partyGeoData) ?>;
var sortedOnce = false;

function formatDistance(meters) {
    if (meters === null || meters === undefined) return '';
    if (meters < 1000) return Math.round(meters) + ' m';
    return (meters / 1000).toFixed(1) + ' km';
}

function haversineDist(lat1, lon1, lat2, lon2) {
    var R = 6371000;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLon/2) * Math.sin(dLon/2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function updateDistancesAndSort() {
    if (!myLat || !myLng) return;

    partyGeo.forEach(function(p) {
        var el = document.getElementById('dist-' + p.id);
        var card = document.querySelector('.outstanding-card[data-party-id="' + p.id + '"]');
        if (!el) return;
        if (p.lat && p.lng) {
            var dist = haversineDist(myLat, myLng, p.lat, p.lng);
            p.dist = dist;
            el.querySelector('.dist-text').textContent = formatDistance(dist);
            var badge = el.querySelector('.dist-badge');
            if (badge) {
                if (dist < 500) { badge.style.background = '#ecfdf5'; badge.style.color = '#059669'; }
                else if (dist < 2000) { badge.style.background = '#eff6ff'; badge.style.color = '#2563eb'; }
                else if (dist < 5000) { badge.style.background = '#fef3c7'; badge.style.color = '#92400e'; }
                else { badge.style.background = '#fef2f2'; badge.style.color = '#dc2626'; }
            }
            el.style.display = 'block';
            if (card) card.setAttribute('data-dist', Math.round(dist));
        } else {
            p.dist = 999999;
            el.querySelector('.dist-text').textContent = 'No GPS';
            var badge = el.querySelector('.dist-badge');
            if (badge) { badge.style.background = '#f3f4f6'; badge.style.color = '#9ca3af'; }
            el.style.display = 'block';
        }
    });

    // Sort DOM by distance
    var container = document.getElementById('partyListContainer');
    if (!container) return;
    var cards = Array.from(container.querySelectorAll('.outstanding-card'));
    cards.sort(function(a, b) {
        return (parseInt(a.getAttribute('data-dist')) || 999999) - (parseInt(b.getAttribute('data-dist')) || 999999);
    });
    cards.forEach(function(c) { container.appendChild(c); });

    if (!sortedOnce) {
        var banner = document.getElementById('sortBanner');
        if (banner) {
            banner.style.display = 'block';
            var withCoords = partyGeo.filter(function(p) { return p.lat && p.lng; }).length;
            var text = 'Sorted nearest → farthest for efficient recovery';
            if (withCoords < partyGeo.length) text += ' (' + (partyGeo.length - withCoords) + ' without GPS at end)';
            document.getElementById('sortBannerText').textContent = text;
        }
        sortedOnce = true;
    }
}

(function() {
    var statusEl = document.getElementById('gpsStatus');
    if (!statusEl) return;
    if (!navigator.geolocation) {
        statusEl.innerHTML = '<span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> GPS not supported. Showing by outstanding amount.</span>';
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            myLat = pos.coords.latitude;
            myLng = pos.coords.longitude;
            statusEl.innerHTML = '<span style="color:#059669;"><i class="fas fa-check-circle"></i> Location locked (±' + pos.coords.accuracy.toFixed(0) + 'm) — sorted nearest first</span>';
            updateDistancesAndSort();
            setInterval(function() {
                navigator.geolocation.getCurrentPosition(function(p) {
                    myLat = p.coords.latitude;
                    myLng = p.coords.longitude;
                    updateDistancesAndSort();
                }, function(){}, { enableHighAccuracy: true, timeout: 10000 });
            }, 30000);
        },
        function(err) {
            var msg = 'Location unavailable — showing by outstanding amount';
            if (err.code === 1) msg = 'Location denied. Enable GPS for nearest-first sorting.';
            else if (err.code === 2) msg = 'Location unavailable. Check GPS settings.';
            else if (err.code === 3) msg = 'Location timed out. Showing by outstanding amount.';
            statusEl.innerHTML = '<span style="color:#d97706;"><i class="fas fa-exclamation-triangle"></i> ' + msg + '</span>';
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
})();

function shareOutstandingWhatsApp(id, partyName, partyId, dueAmount, partyPhone) {
    var today = new Date();
    var dateStr = today.getDate().toString().padStart(2,'0') + ' ' + ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][today.getMonth()] + ' ' + today.getFullYear();

    var msg = 'Hi ' + partyName + ',\n'
        + '🆔 *Party ID:* ' + partyId + '\n\n'
        + 'This is a gentle reminder regarding your pending payment.\n\n'
        + '📅 *Date:* ' + dateStr + '\n'
        + '💰 *Due Amount:* ₹' + dueAmount + '\n\n'
        + 'Kindly clear the outstanding amount at your earliest convenience.\n\n'
        + 'Thanks for choosing *Gilaf*.\n'
        + 'Your satisfaction is our priority.';

    var url;
    if (partyPhone) {
        var phone = partyPhone.replace(/[^0-9]/g, '');
        if (phone.length === 10) phone = '91' + phone;
        url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg);
    } else {
        url = 'https://wa.me/?text=' + encodeURIComponent(msg);
    }
    window.open(url, '_blank');

    var btn = document.getElementById('outShareBtn' + id);
    if (btn) {
        btn.innerHTML = '<i class="fab fa-whatsapp"></i> Shared';
        btn.style.background = '#f3f4f6';
        btn.style.color = '#6b7280';
        btn.style.borderColor = '#e5e7eb';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
