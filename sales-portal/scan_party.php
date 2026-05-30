<?php
/**
 * Sales Portal - Scan Party QR Code / Search Party
 * Allows sales executive to scan QR or search by code/name to find a party
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sales_require_login();

$exec = sales_get_executive();
$execId = $exec['id'];
$pageTitle = 'Find Party';
$currentPage = 'scan_party';

include __DIR__ . '/includes/header.php';
?>

<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-qrcode"></i> Scan Barcode / QR Code</h3>
    </div>
    <div class="sp-scan-wrapper">
        <div id="scannerArea" class="sp-scan-area"></div>
        <div class="sp-scan-buttons">
            <button type="button" class="sp-btn sp-btn-primary sp-btn-block" id="startScanBtn" onclick="startScanner()">
                <i class="fas fa-camera"></i> Start Camera
            </button>
            <button type="button" class="sp-btn sp-btn-outline sp-btn-block" id="stopScanBtn" onclick="stopScanner()" style="display:none;">
                <i class="fas fa-stop"></i> Stop Camera
            </button>
        </div>
        <div class="sp-scan-divider"><span>or</span></div>
        <div class="sp-scan-upload">
            <label for="qrFileInput" class="sp-btn sp-btn-outline sp-btn-block">
                <i class="fas fa-image"></i> Upload QR Image
            </label>
            <input type="file" id="qrFileInput" accept="image/*" style="display:none;" onchange="scanFromFile(this)">
        </div>
        <!-- Hidden input for native camera fallback (WebView/app) -->
        <input type="file" id="nativeCameraInput" accept="image/*" capture="environment" style="display:none;" onchange="scanFromCapture(this)">
        <div id="scanStatus" class="sp-scan-status"></div>
    </div>
</div>

<div class="sp-card sp-mb-16">
    <div class="sp-card-header">
        <h3><i class="fas fa-search"></i> Search Party</h3>
    </div>
    <div style="padding:0 4px;">
        <div class="sp-form-group">
            <label>Enter Party Code, Shop Name, Owner Name, or Phone</label>
            <div style="display:flex;gap:8px;">
                <input type="text" id="partySearchInput" class="sp-input" placeholder="e.g. GLP-00001 or shop name..." style="flex:1;">
                <button type="button" class="sp-btn sp-btn-primary" onclick="searchParty()"><i class="fas fa-search"></i></button>
            </div>
        </div>
        <div id="searchResults" style="margin-top:8px;"></div>
    </div>
</div>

<!-- Party Details Card (hidden by default) -->
<div id="partyDetailCard" style="display:none;">
    <div class="sp-card">
        <div class="sp-card-header">
            <h3><i class="fas fa-store"></i> Party Details</h3>
            <button type="button" class="sp-btn sp-btn-outline sp-btn-sm" onclick="clearPartyDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div style="padding:4px 0;">
            <div style="text-align:center;margin-bottom:16px;">
                <span id="pdCode" class="sp-badge" style="background:rgba(26,60,52,0.08);color:#1A3C34;font-size:14px;padding:6px 16px;letter-spacing:1px;"></span>
            </div>
            <div id="pdShopName" style="font-size:18px;font-weight:700;text-align:center;margin-bottom:4px;"></div>
            <div id="pdOwnerName" style="font-size:13px;color:#6b7280;text-align:center;margin-bottom:16px;"></div>

            <div class="sp-party-meta" style="grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;">
                <div class="sp-party-meta-item">
                    <div class="value" id="pdCreditLimit">₹0</div>
                    <div class="label">Credit Limit</div>
                </div>
                <div class="sp-party-meta-item">
                    <div class="value" id="pdOutstanding" style="color:var(--sp-danger);">₹0</div>
                    <div class="label">Outstanding</div>
                </div>
                <div class="sp-party-meta-item">
                    <div class="value" id="pdAvailable" style="color:var(--sp-success);">₹0</div>
                    <div class="label">Available</div>
                </div>
            </div>

            <div style="font-size:13px;line-height:1.8;">
                <div><i class="fas fa-phone" style="width:20px;color:#6b7280;"></i> <span id="pdPhone"></span></div>
                <div><i class="fas fa-envelope" style="width:20px;color:#6b7280;"></i> <span id="pdEmail"></span></div>
                <div><i class="fas fa-map-marker-alt" style="width:20px;color:#6b7280;"></i> <span id="pdAddress"></span></div>
                <div><i class="fas fa-city" style="width:20px;color:#6b7280;"></i> <span id="pdLocation"></span></div>
                <div id="pdGstRow"><i class="fas fa-file-invoice" style="width:20px;color:#6b7280;"></i> GST: <span id="pdGst"></span></div>
                <div id="pdMapsRow"><i class="fas fa-map" style="width:20px;color:#6b7280;"></i> <a id="pdMapsLink" href="#" target="_blank" style="color:var(--sp-info);">View on Google Maps</a></div>
            </div>

            <div class="sp-action-row" style="margin-top:16px;">
                <a id="pdNewOrderBtn" href="#" class="sp-btn sp-btn-primary sp-btn-lg" style="flex:1;text-align:center;">
                    <i class="fas fa-cart-plus"></i> New Order
                </a>
                <a id="pdViewBtn" href="#" class="sp-btn sp-btn-outline sp-btn-lg" style="flex:1;text-align:center;">
                    <i class="fas fa-eye"></i> View Details
                </a>
            </div>
        </div>
    </div>
</div>

<script>
var cameraStream = null;
var scanVideoEl = null;
var scanAnimFrame = null;
var isScanning = false;
var lastScannedCode = '';
var scanCooldown = false;

var baseUrl = '<?= sales_base_url('') ?>';
if (baseUrl.charAt(baseUrl.length - 1) !== '/') baseUrl += '/';

// All supported barcode formats for BarcodeDetector
var BARCODE_FORMATS = ['qr_code','code_128','code_39','code_93','ean_13','ean_8','upc_a','upc_e','codabar','itf','data_matrix','aztec','pdf417'];

function resetScannerUI() {
    document.getElementById('startScanBtn').style.display = 'flex';
    document.getElementById('stopScanBtn').style.display = 'none';
}

// ─── MAIN SCANNER: live camera + real-time decode ───
function startScanner() {
    var startBtn = document.getElementById('startScanBtn');
    var stopBtn  = document.getElementById('stopScanBtn');
    var statusDiv = document.getElementById('scanStatus');
    var area = document.getElementById('scannerArea');

    if (isScanning) return;

    // Check if getUserMedia is available (requires HTTPS or localhost)
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        // No live scanning possible — auto-open native camera
        statusDiv.innerHTML = '<div class="sp-scan-msg"><i class="fas fa-camera"></i> Opening camera...</div>';
        takePhotoFallback();
        return;
    }

    startBtn.style.display = 'none';
    stopBtn.style.display = 'flex';
    statusDiv.innerHTML = '<div class="sp-scan-msg"><i class="fas fa-spinner fa-spin"></i> Starting camera...</div>';
    lastScannedCode = '';
    stopCameraStream();

    // Step 1: Try back camera with HD constraints
    navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false
    })
    .then(onCameraSuccess)
    .catch(function(err) {
        var errName = (err.name || '');
        // Step 2: If constraints failed, retry with simplest possible config
        if (errName === 'OverconstrainedError' || errName === 'ConstraintNotSatisfiedError') {
            statusDiv.innerHTML = '<div class="sp-scan-msg"><i class="fas fa-spinner fa-spin"></i> Retrying camera...</div>';
            return navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                .then(onCameraSuccess);
        }
        throw err; // re-throw for final catch
    })
    .catch(function(err) {
        console.error('Camera error:', err);
        stopCameraStream();
        area.style.display = 'none';
        resetScannerUI();
        // Auto-open native camera so "Start Camera" always opens something
        statusDiv.innerHTML = '<div class="sp-scan-msg"><i class="fas fa-camera"></i> Opening camera to scan...</div>';
        takePhotoFallback();
    });
}

// Called when getUserMedia succeeds — set up video + start live scan
function onCameraSuccess(stream) {
    var area = document.getElementById('scannerArea');
    var statusDiv = document.getElementById('scanStatus');
    cameraStream = stream;

    // Build video element
    area.innerHTML = '';
    var video = document.createElement('video');
    video.setAttribute('playsinline', '');
    video.setAttribute('autoplay', '');
    video.setAttribute('muted', '');
    video.muted = true;
    video.style.cssText = 'width:100%;border-radius:12px;display:block;background:#000;';
    video.srcObject = stream;
    area.appendChild(video);

    // Add scanning viewfinder overlay
    var overlay = document.createElement('div');
    overlay.style.cssText = 'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);'
        + 'width:220px;height:220px;border:3px solid rgba(255,255,255,0.7);border-radius:16px;'
        + 'box-shadow:0 0 0 4000px rgba(0,0,0,0.25);pointer-events:none;z-index:2;';
    // Animated scan line
    var scanLine = document.createElement('div');
    scanLine.style.cssText = 'position:absolute;left:8px;right:8px;height:2px;background:linear-gradient(90deg,transparent,#4ade80,transparent);'
        + 'top:10px;animation:scanLineMove 2s ease-in-out infinite;';
    overlay.appendChild(scanLine);
    area.appendChild(overlay);

    // Add CSS animation if not already present
    if (!document.getElementById('scanLineStyle')) {
        var style = document.createElement('style');
        style.id = 'scanLineStyle';
        style.textContent = '@keyframes scanLineMove{0%{top:10px}50%{top:calc(100% - 12px)}100%{top:10px}}';
        document.head.appendChild(style);
    }

    area.style.display = 'block';
    area.style.position = 'relative';
    scanVideoEl = video;

    return video.play().then(function() {
        isScanning = true;
        statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-ok"><i class="fas fa-crosshairs"></i> Scanning... point at QR code or barcode</div>';
        startScanLoop();
    });
}

// Show camera error with clear instructions + Take Photo fallback button
function showCameraError(err, statusDiv) {
    var msg = String(err.message || err.name || err);
    var errName = (err.name || '');

    if (errName === 'NotAllowedError' || msg.indexOf('Permission') >= 0) {
        statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err" style="text-align:left;">'
            + '<i class="fas fa-lock"></i> <strong>Camera permission required</strong><br>'
            + '<span style="font-size:12px;line-height:1.6;">'
            + 'To enable live scanner:<br>'
            + '1. Tap the <strong>lock/tune icon</strong> (🔒) in the address bar<br>'
            + '2. Set Camera to <strong>Allow</strong><br>'
            + '3. <strong>Reload</strong> this page<br><br>'
            + '</span>'
            + '<button type="button" class="sp-btn sp-btn-outline sp-btn-block" onclick="takePhotoFallback()" style="margin-top:4px;">'
            + '<i class="fas fa-camera"></i> Take Photo Instead</button>'
            + '</div>';
    } else if (errName === 'NotFoundError') {
        statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err">'
            + '<i class="fas fa-times-circle"></i> No camera found on this device.<br>'
            + '<button type="button" class="sp-btn sp-btn-outline sp-btn-block" onclick="takePhotoFallback()" style="margin-top:8px;">'
            + '<i class="fas fa-camera"></i> Take Photo Instead</button></div>';
    } else {
        statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err">'
            + '<i class="fas fa-times-circle"></i> Camera error: ' + msg + '<br>'
            + '<button type="button" class="sp-btn sp-btn-outline sp-btn-block" onclick="takePhotoFallback()" style="margin-top:8px;">'
            + '<i class="fas fa-camera"></i> Take Photo Instead</button></div>';
    }
}

// Show message when getUserMedia is completely unavailable (HTTP / old WebView)
function showCameraUnavailable(statusDiv) {
    statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-warn" style="text-align:left;">'
        + '<i class="fas fa-exclamation-triangle"></i> <strong>Live scanner not available</strong><br>'
        + '<span style="font-size:12px;">Live camera scanning requires HTTPS or a browser with camera support.</span><br>'
        + '<button type="button" class="sp-btn sp-btn-outline sp-btn-block" onclick="takePhotoFallback()" style="margin-top:8px;">'
        + '<i class="fas fa-camera"></i> Take Photo to Scan</button>'
        + '</div>';
}

// Explicit fallback: user taps "Take Photo Instead"
function takePhotoFallback() {
    var nativeInput = document.getElementById('nativeCameraInput');
    nativeInput.value = '';
    nativeInput.click();
}

// Handle photo from native camera (explicit fallback only)
function scanFromCapture(input) {
    if (!input.files || !input.files[0]) return;
    var statusDiv = document.getElementById('scanStatus');
    var file = input.files[0];
    input.value = '';
    statusDiv.innerHTML = '<div class="sp-scan-msg"><i class="fas fa-spinner fa-spin"></i> Reading barcode from photo...</div>';
    decodeImageFile(file);
}

// ─── LIVE SCAN LOOP: reads video frames continuously ───
function startScanLoop() {
    if (!scanVideoEl || !isScanning) return;

    if ('BarcodeDetector' in window) {
        // Native BarcodeDetector — best performance (Chrome Android 83+)
        var detector = new BarcodeDetector({ formats: BARCODE_FORMATS });

        function nativeScanFrame() {
            if (!isScanning || !scanVideoEl) return;
            // Only scan when video has actual frames
            if (scanVideoEl.readyState < 2) {
                scanAnimFrame = requestAnimationFrame(nativeScanFrame);
                return;
            }
            detector.detect(scanVideoEl).then(function(barcodes) {
                if (!isScanning) return;
                if (barcodes.length > 0) {
                    var code = barcodes[0].rawValue;
                    if (code && !scanCooldown && code !== lastScannedCode) {
                        lastScannedCode = code;
                        scanCooldown = true;
                        var statusDiv = document.getElementById('scanStatus');
                        statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-ok"><i class="fas fa-check-circle"></i> Scanned: <strong>' + code + '</strong></div>';
                        if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                        stopScanner();
                        lookupPartyByCode(code.trim());
                        setTimeout(function() { scanCooldown = false; }, 2000);
                        return;
                    }
                }
                scanAnimFrame = requestAnimationFrame(nativeScanFrame);
            }).catch(function() {
                // Detection error on this frame — skip and try next
                scanAnimFrame = requestAnimationFrame(nativeScanFrame);
            });
        }
        nativeScanFrame();
    } else {
        // Fallback: capture canvas frames + decode with html5-qrcode
        loadFallbackDecoder();
    }
}

// ─── FALLBACK DECODER: canvas + html5-qrcode for browsers without BarcodeDetector ───
function loadFallbackDecoder() {
    if (typeof Html5Qrcode !== 'undefined') {
        startCanvasScanLoop();
        return;
    }
    var script = document.createElement('script');
    script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    script.onload = function() { startCanvasScanLoop(); };
    script.onerror = function() {
        document.getElementById('scanStatus').innerHTML = '<div class="sp-scan-msg sp-scan-msg-warn"><i class="fas fa-exclamation-triangle"></i> Barcode detection not available. Use <strong>Upload Image</strong>.</div>';
    };
    document.head.appendChild(script);
}

function startCanvasScanLoop() {
    if (!scanVideoEl || !isScanning) return;
    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d');

    function canvasScanFrame() {
        if (!isScanning || !scanVideoEl) return;
        try {
            var vw = scanVideoEl.videoWidth;
            var vh = scanVideoEl.videoHeight;
            if (vw > 0 && vh > 0) {
                canvas.width = vw;
                canvas.height = vh;
                ctx.drawImage(scanVideoEl, 0, 0, vw, vh);
                canvas.toBlob(function(blob) {
                    if (!blob || !isScanning) { scanAnimFrame = setTimeout(canvasScanFrame, 300); return; }
                    var file = new File([blob], 'frame.png', { type: 'image/png' });
                    var tempDiv = document.createElement('div');
                    tempDiv.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;';
                    tempDiv.id = 'tmpScan_' + Date.now();
                    document.body.appendChild(tempDiv);
                    var decoder = new Html5Qrcode(tempDiv.id);
                    decoder.scanFile(file, false).then(function(decodedText) {
                        try { document.body.removeChild(tempDiv); } catch(e) {}
                        if (!scanCooldown && decodedText !== lastScannedCode) {
                            lastScannedCode = decodedText;
                            scanCooldown = true;
                            document.getElementById('scanStatus').innerHTML = '<div class="sp-scan-msg sp-scan-msg-ok"><i class="fas fa-check-circle"></i> Scanned: <strong>' + decodedText + '</strong></div>';
                            if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
                            stopScanner();
                            lookupPartyByCode(decodedText.trim());
                            setTimeout(function() { scanCooldown = false; }, 2000);
                            return;
                        }
                        scanAnimFrame = setTimeout(canvasScanFrame, 300);
                    }).catch(function() {
                        try { document.body.removeChild(tempDiv); } catch(e) {}
                        scanAnimFrame = setTimeout(canvasScanFrame, 300);
                    });
                }, 'image/png');
            } else {
                scanAnimFrame = setTimeout(canvasScanFrame, 200);
            }
        } catch(e) {
            scanAnimFrame = setTimeout(canvasScanFrame, 500);
        }
    }
    canvasScanFrame();
}

// ─── CAMERA CLEANUP ───
function stopCameraStream() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(function(t) { t.stop(); });
        cameraStream = null;
    }
    scanVideoEl = null;
}

function stopScanner() {
    isScanning = false;
    if (scanAnimFrame) {
        cancelAnimationFrame(scanAnimFrame);
        clearTimeout(scanAnimFrame);
        scanAnimFrame = null;
    }
    stopCameraStream();
    var area = document.getElementById('scannerArea');
    area.innerHTML = '';
    area.style.display = 'none';
    resetScannerUI();
}

// ─── IMAGE UPLOAD: decode from file (Upload QR Image button) ───
function scanFromFile(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    input.value = '';
    document.getElementById('scanStatus').innerHTML = '<div class="sp-scan-msg"><i class="fas fa-spinner fa-spin"></i> Reading code from image...</div>';
    decodeImageFile(file);
}

function decodeImageFile(file) {
    var statusDiv = document.getElementById('scanStatus');
    var fileTimeout = setTimeout(function() {
        statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> Timed out. Try a clearer photo or enter code manually.</div>';
    }, 10000);

    if ('BarcodeDetector' in window) {
        var img = new Image();
        img.onload = function() {
            new BarcodeDetector({ formats: BARCODE_FORMATS })
            .detect(img).then(function(barcodes) {
                clearTimeout(fileTimeout);
                URL.revokeObjectURL(img.src);
                if (barcodes.length > 0) {
                    var code = barcodes[0].rawValue;
                    statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-ok"><i class="fas fa-check-circle"></i> Scanned: <strong>' + code + '</strong></div>';
                    if (navigator.vibrate) navigator.vibrate(100);
                    lookupPartyByCode(code.trim());
                } else {
                    statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> No barcode/QR found in photo. Try a clearer image.</div>';
                }
            }).catch(function() {
                clearTimeout(fileTimeout);
                URL.revokeObjectURL(img.src);
                statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> Could not read image. Try again.</div>';
            });
        };
        img.onerror = function() {
            clearTimeout(fileTimeout);
            statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> Failed to load image.</div>';
        };
        img.src = URL.createObjectURL(file);
    } else {
        // html5-qrcode fallback for file decoding
        function doLibraryScan() {
            var tempDiv = document.createElement('div');
            tempDiv.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;';
            tempDiv.id = 'fileScan_' + Date.now();
            document.body.appendChild(tempDiv);
            new Html5Qrcode(tempDiv.id).scanFile(file, true).then(function(decoded) {
                clearTimeout(fileTimeout);
                try { document.body.removeChild(tempDiv); } catch(e) {}
                statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-ok"><i class="fas fa-check-circle"></i> Scanned: <strong>' + decoded + '</strong></div>';
                if (navigator.vibrate) navigator.vibrate(100);
                lookupPartyByCode(decoded.trim());
            }).catch(function() {
                clearTimeout(fileTimeout);
                try { document.body.removeChild(tempDiv); } catch(e) {}
                statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> Could not read code. Try a clearer photo.</div>';
            });
        }
        if (typeof Html5Qrcode !== 'undefined') { doLibraryScan(); }
        else {
            var s = document.createElement('script');
            s.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            s.onload = doLibraryScan;
            s.onerror = function() { clearTimeout(fileTimeout); statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> Scanner library failed to load.</div>'; };
            document.head.appendChild(s);
        }
    }
}

// ─── CODE EXTRACTION: parse QR content to party code ───
function extractPartyCode(rawText) {
    // Direct party code pattern: GLP-00001
    var match = rawText.match(/GLP-\d{3,}/i);
    if (match) return match[0].toUpperCase();
    // URL with ?code= parameter (e.g. gilafstore.com/verify.php?code=G-SK-0226-22-M-43)
    try {
        var url = new URL(rawText);
        var codeParam = url.searchParams.get('code') || url.searchParams.get('party_code');
        if (codeParam) return codeParam.trim().toUpperCase();
    } catch(e) {}
    // URL with ?party_id= parameter
    var idMatch = rawText.match(/party_id=(\d+)/i);
    if (idMatch) return '__ID__' + idMatch[1];
    // General alphanumeric code (e.g. G-SK-0226-22-M-43 as plain text)
    var cleaned = rawText.trim();
    if (/^[A-Za-z0-9\-]{3,}$/.test(cleaned)) return cleaned.toUpperCase();
    return rawText.replace(/[^a-zA-Z0-9\-]/g, '').toUpperCase();
}

function apiFetch(url) {
    return fetch(url, { credentials: 'same-origin' })
        .then(function(response) {
            var ct = response.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                if (response.status === 401 || response.redirected) {
                    throw new Error('Session expired. Please refresh the page and login again.');
                }
                throw new Error('Server error (HTTP ' + response.status + '). Please try again.');
            }
            return response.json();
        });
}

function lookupPartyByCode(code) {
    var cleanCode = extractPartyCode(code);
    var statusDiv = document.getElementById('scanStatus');
    statusDiv.innerHTML = '<div class="sp-scan-msg"><i class="fas fa-spinner fa-spin"></i> Looking up: <strong>' + cleanCode + '</strong></div>';

    var lookupUrl;
    if (cleanCode.startsWith('__ID__')) {
        lookupUrl = baseUrl + 'api_party_lookup.php?id=' + cleanCode.replace('__ID__', '');
    } else {
        lookupUrl = baseUrl + 'api_party_lookup.php?code=' + encodeURIComponent(cleanCode);
    }

    apiFetch(lookupUrl)
        .then(function(data) {
            if (data.success && data.party) {
                statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-ok"><i class="fas fa-check-circle"></i> Party found: <strong>' + data.party.shop_name + '</strong></div>';
                showPartyDetail(data.party);
            } else {
                apiFetch(baseUrl + 'api_party_lookup.php?search=' + encodeURIComponent(code.trim()))
                    .then(function(data2) {
                        if (data2.success && data2.parties && data2.parties.length === 1) {
                            lookupPartyByCode(data2.parties[0].party_code);
                        } else if (data2.success && data2.parties && data2.parties.length > 1) {
                            statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-warn"><i class="fas fa-exclamation-triangle"></i> Multiple matches. Use search below.</div>';
                            document.getElementById('partySearchInput').value = code.trim();
                            searchParty();
                        } else {
                            statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> ' + (data.message || 'Party not found for: ' + code) + '</div>';
                        }
                    })
                    .catch(function(err2) {
                        statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> ' + (data.message || 'Party not found for: ' + code) + '</div>';
                    });
            }
        })
        .catch(function(err) {
            statusDiv.innerHTML = '<div class="sp-scan-msg sp-scan-msg-err"><i class="fas fa-times-circle"></i> ' + (err.message || 'Network error. Check connection.') + '</div>';
        });
}

var searchTimer;
function searchParty() {
    var input = document.getElementById('partySearchInput');
    var val = input.value.trim();
    var resultsDiv = document.getElementById('searchResults');

    if (val.length < 2) {
        resultsDiv.innerHTML = '<div style="padding:8px;font-size:13px;color:#6b7280;">Type at least 2 characters to search.</div>';
        return;
    }

    if (/^GLP-\d+$/i.test(val)) {
        lookupPartyByCode(val.toUpperCase());
        resultsDiv.innerHTML = '';
        return;
    }

    resultsDiv.innerHTML = '<div style="padding:8px;font-size:13px;color:#6b7280;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';

    apiFetch(baseUrl + 'api_party_lookup.php?search=' + encodeURIComponent(val))
        .then(function(data) {
            if (data.success && data.parties && data.parties.length > 0) {
                var html = '';
                data.parties.forEach(function(p) {
                    var outstanding = parseFloat(p.outstanding_amount || 0);
                    html += '<div onclick="lookupPartyByCode(\'' + (p.party_code || '') + '\')" style="padding:12px;border-bottom:1px solid #f3f4f6;cursor:pointer;display:flex;align-items:center;gap:12px;" ontouchstart="this.style.background=\'#f9fafb\'" ontouchend="this.style.background=\'transparent\'">';
                    html += '<div style="width:36px;height:36px;background:linear-gradient(135deg,#1A3C34,#2d5a4d);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px;flex-shrink:0;">' + p.shop_name.charAt(0).toUpperCase() + '</div>';
                    html += '<div style="flex:1;min-width:0;">';
                    html += '<div style="font-weight:600;font-size:14px;">' + p.shop_name + '</div>';
                    html += '<div style="font-size:12px;color:#6b7280;">' + p.owner_name + ' · ' + p.phone + '</div>';
                    if (outstanding > 0) html += '<div style="font-size:11px;color:#dc2626;font-weight:600;">Due: ₹' + outstanding.toLocaleString('en-IN') + '</div>';
                    html += '</div>';
                    html += '<span class="sp-badge" style="background:rgba(26,60,52,0.08);color:#1A3C34;font-size:10px;">' + (p.party_code || '') + '</span>';
                    html += '</div>';
                });
                resultsDiv.innerHTML = html;
            } else {
                resultsDiv.innerHTML = '<div style="padding:12px;font-size:13px;color:#6b7280;text-align:center;"><i class="fas fa-search"></i> No parties found for "' + val + '"</div>';
            }
        })
        .catch(function(err) {
            resultsDiv.innerHTML = '<div style="padding:8px;font-size:13px;color:#991b1b;"><i class="fas fa-times-circle"></i> ' + (err.message || 'Network error') + '</div>';
        });
}

document.getElementById('partySearchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    var val = this.value.trim();
    if (val.length >= 2) {
        searchTimer = setTimeout(searchParty, 350);
    } else {
        document.getElementById('searchResults').innerHTML = '';
    }
});

function showPartyDetail(p) {
    document.getElementById('partyDetailCard').style.display = 'block';
    document.getElementById('pdCode').textContent = p.party_code;
    document.getElementById('pdShopName').textContent = p.shop_name;
    document.getElementById('pdOwnerName').textContent = p.owner_name;
    document.getElementById('pdPhone').textContent = p.phone || '—';
    document.getElementById('pdEmail').textContent = p.email || '—';
    document.getElementById('pdAddress').textContent = p.address || '—';

    var loc = [p.city, p.district, p.state, p.pincode].filter(function(x) { return x; }).join(', ');
    document.getElementById('pdLocation').textContent = loc || '—';

    document.getElementById('pdCreditLimit').textContent = '₹' + Number(p.credit_limit).toLocaleString('en-IN');
    document.getElementById('pdOutstanding').textContent = '₹' + Number(p.outstanding_amount).toLocaleString('en-IN');
    var available = Math.max(0, p.credit_limit - p.outstanding_amount);
    document.getElementById('pdAvailable').textContent = '₹' + available.toLocaleString('en-IN');

    if (p.gst_number) {
        document.getElementById('pdGst').textContent = p.gst_number;
        document.getElementById('pdGstRow').style.display = '';
    } else {
        document.getElementById('pdGstRow').style.display = 'none';
    }

    if (p.google_maps_url) {
        document.getElementById('pdMapsLink').href = p.google_maps_url;
        document.getElementById('pdMapsRow').style.display = '';
    } else {
        document.getElementById('pdMapsRow').style.display = 'none';
    }

    document.getElementById('pdNewOrderBtn').href = baseUrl + 'new_order.php?party_id=' + p.id;
    document.getElementById('pdViewBtn').href = baseUrl + 'party_detail.php?id=' + p.id;

    document.getElementById('partyDetailCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    document.getElementById('searchResults').innerHTML = '';
}

function clearPartyDetail() {
    document.getElementById('partyDetailCard').style.display = 'none';
}

document.getElementById('partySearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); searchParty(); }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
