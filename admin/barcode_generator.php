<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'EAN/GTIN Barcode Generator';
$adminPage = 'barcode_generator';

// Fetch categories
$categories = admin_get_categories();

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-barcode me-2"></i>EAN/GTIN Barcode Generator</h4>
            <p class="text-muted mb-0">Assign official GS1 EAN/GTIN barcodes to products.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="https://www.gs1.org/services/verified-by-gs1" target="_blank" class="btn btn-outline-success" title="Verify EAN/GTIN on GS1">
                <i class="fas fa-check-circle me-1"></i>GS1 Verify
            </a>
            <a href="barcode_management.php" class="btn btn-dark">
                <i class="fas fa-list-ul me-2"></i>GTIN/EAN Inventory
            </a>
            <a href="manage_products.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Products
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Generator Controls -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title mb-0"><i class="fas fa-cogs me-2"></i>Configuration</h5>
                </div>
                <div class="card-body">
                    <form id="barcodeForm">

                        <!-- Step 1: Enter EAN/GTIN -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Enter EAN/GTIN <span class="text-muted fw-normal small">(12 or 13 digits)</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-dark text-white"><i class="fas fa-barcode"></i></span>
                                <input type="text" class="form-control form-control-lg font-monospace fw-bold" id="eanInput" placeholder="890123456789" maxlength="13" autocomplete="off" style="letter-spacing:2px;font-size:1.2rem;">
                            </div>
                            <div class="form-text">Enter 12 digits (check digit auto-calculated) or full 13-digit EAN/GTIN.</div>
                            <div id="eanValidation" class="mt-2 d-none"></div>
                        </div>

                        <!-- GS1 Verification Link -->
                        <div class="mb-3" id="gs1VerifySection" style="display:none;">
                            <a href="#" id="gs1VerifyLink" target="_blank" class="btn btn-sm btn-outline-success w-100">
                                <i class="fas fa-external-link-alt me-1"></i>Verify this EAN/GTIN on GS1
                            </a>
                        </div>

                        <hr>

                        <!-- Step 2: Category -->
                        <div class="mb-3" id="step1Cat">
                            <label class="form-label fw-bold">1. Select Category</label>
                            <select class="form-select select2" id="categorySelect" style="width: 100%;">
                                <option value="">-- Choose a category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id']; ?>" 
                                            data-code="<?= htmlspecialchars($cat['category_code'] ?? ''); ?>"
                                            data-name="<?= htmlspecialchars($cat['name']); ?>">
                                        <?= htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="categoryMsg" class="form-text text-muted"></div>
                        </div>

                        <!-- Step 3: Product -->
                        <div class="mb-3" id="step2Prod">
                            <label class="form-label fw-bold">2. Select Product</label>
                            <select class="form-select select2" id="productSelect" style="width: 100%;" disabled>
                                <option value="">-- Select Category First --</option>
                            </select>
                            <div id="productMsg" class="form-text text-muted"></div>
                        </div>

                        <!-- Step 4: Weight -->
                        <div class="mb-3" id="step3Wt">
                            <label class="form-label fw-bold">3. Select Weight/Size</label>
                            <select class="form-select" id="weightSelect" disabled>
                                <option value="">-- Select Product First --</option>
                            </select>
                        </div>

                        <hr>

                        <!-- SKU Logic Display -->
                        <div class="mb-3 bg-light p-3 rounded" id="skuLogicSection">
                            <label class="form-label fw-bold small text-uppercase">SKU Logic</label>
                            <div class="row g-2">
                                <div class="col-3">
                                    <input type="text" class="form-control form-control-sm" value="G" readonly id="prefixInput" title="Prefix">
                                </div>
                                <div class="col-3">
                                    <input type="text" class="form-control form-control-sm" id="categoryCodeInput" placeholder="Cat" title="Category Code" readonly>
                                </div>
                                <div class="col-3">
                                    <input type="text" class="form-control form-control-sm" id="productCodeInput" placeholder="ID" title="Product ID" readonly>
                                </div>
                                <div class="col-3">
                                    <input type="text" class="form-control form-control-sm" id="weightCodeInput" placeholder="Wt" title="Weight" readonly>
                                </div>
                            </div>
                            <div class="mt-2">
                                <span class="small text-muted">Generated SKU: </span>
                                <strong class="font-monospace text-primary" id="skuPreview">—</strong>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-lg" id="generateBtn">
                                <i class="fas fa-sync-alt me-2"></i>Preview Barcode
                            </button>
                            <button type="button" class="btn btn-success btn-lg d-none" id="saveEanBtn">
                                <i class="fas fa-save me-2"></i>Save EAN & Assign to Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Debug Console -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#debugConsole">
                    <h6 class="card-title mb-0 small"><i class="fas fa-terminal me-2"></i>System Status / Debug</h6>
                    <i class="fas fa-chevron-down small"></i>
                </div>
                <div class="collapse" id="debugConsole">
                    <div class="card-body bg-black p-2">
                        <div id="systemLog" class="font-monospace small text-success" style="height: 150px; overflow-y: auto;">
                            > System initialized...<br>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview / Results -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-eye me-1 text-muted"></i>Barcode Label Preview</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="downloadBarcode()" id="downloadBtn" disabled>
                            <i class="fas fa-download me-1"></i>Download PNG
                        </button>
                        <button class="btn btn-sm btn-outline-dark" onclick="printBarcode()" id="printBtn" disabled>
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                    </div>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center" style="min-height: 480px; background: #f0f0f0;">
                    
                    <!-- Empty State -->
                    <div id="emptyState" class="text-center text-muted py-5">
                        <i class="fas fa-barcode fa-4x mb-3" style="opacity:0.15;"></i>
                        <p class="mb-1">Enter an EAN/GTIN number to preview</p>
                        <small>The barcode will render in international EAN-13 standard format</small>
                    </div>

                    <!-- Professional Barcode Label -->
                    <div id="barcodeLabelWrap" style="display:none;">
                        <div id="barcodeLabel" style="background:#fff; border:2px solid #000; padding:20px 30px; min-width:360px; max-width:440px; text-align:center; font-family:'Helvetica Neue',Arial,sans-serif;">
                            <!-- Product Name -->
                            <div id="labelProductName" style="font-size:11px; font-weight:600; text-transform:uppercase; color:#333; margin-bottom:8px; letter-spacing:0.5px; border-bottom:1px solid #ddd; padding-bottom:6px; min-height:18px;"></div>
                            <!-- EAN-13 Barcode SVG -->
                            <div style="padding:8px 0;">
                                <svg id="barcode"></svg>
                            </div>
                            <!-- SKU Row -->
                            <div id="labelSkuRow" style="display:none; margin-top:6px; padding-top:6px; border-top:1px solid #ddd;">
                                <table style="width:100%; font-size:10px; color:#555;">
                                    <tr>
                                        <td style="text-align:left;"><strong>SKU:</strong> <span id="labelSku" class="font-monospace" style="font-size:11px; color:#000;"></span></td>
                                        <td style="text-align:right;"><strong>Wt:</strong> <span id="labelWeight" style="font-size:11px; color:#000;"></span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <!-- Label Footer Info -->
                        <div class="mt-3 text-center">
                            <span class="badge bg-success px-3 py-2" id="eanBadge" style="font-size:13px; letter-spacing:1.5px; font-family:monospace;"></span>
                        </div>
                        <div id="skuBadgeRow" class="mt-2 text-center" style="display:none;">
                            <span class="badge bg-primary px-3 py-2" id="skuBadge" style="font-size:12px; font-family:monospace;"></span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

<!-- Dependencies (must load AFTER jQuery from admin_footer) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    function log(msg) {
        $('#systemLog').prepend('> ' + msg + '<br>');
    }

    // Initialize UI
    $('.select2').select2({ theme: 'bootstrap-5' });
    log("Ready. Professional EAN/GTIN system active.");

    // Track the resolved 13-digit EAN globally
    let resolvedEAN = '';

    const els = {
        eanInput: $('#eanInput'),
        catSelect: $('#categorySelect'),
        prodSelect: $('#productSelect'),
        wtSelect: $('#weightSelect'),
        catCode: $('#categoryCodeInput'),
        prodCode: $('#productCodeInput'),
        wtCode: $('#weightCodeInput'),
        prefix: $('#prefixInput'),
        genBtn: $('#generateBtn'),
        saveBtn: $('#saveEanBtn')
    };

    // ═══ EAN-13 CHECK DIGIT CALCULATION (GS1 Standard) ═══
    function calcCheckDigit(digits12) {
        if (digits12.length !== 12) return -1;
        var sum = 0;
        for (var i = 0; i < 12; i++) {
            sum += parseInt(digits12[i]) * (i % 2 === 0 ? 1 : 3);
        }
        return (10 - (sum % 10)) % 10;
    }

    function validateEAN13(ean) {
        if (ean.length !== 13) return false;
        var check = calcCheckDigit(ean.substring(0, 12));
        return check === parseInt(ean[12]);
    }

    // ═══ EAN/GTIN INPUT HANDLER ═══
    els.eanInput.on('input', function() {
        var val = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(val);
        
        var validationDiv = $('#eanValidation');
        var gs1Section = $('#gs1VerifySection');
        resolvedEAN = '';
        
        if (val.length === 0) {
            validationDiv.addClass('d-none');
            gs1Section.hide();
            hideBarcode();
            return;
        }
        
        if (val.length === 12) {
            // Auto-calculate check digit — professional feature
            var cd = calcCheckDigit(val);
            resolvedEAN = val + cd;
            validationDiv.removeClass('d-none').html(
                '<div class="alert alert-success py-2 px-3 mb-0 small"><i class="fas fa-check-circle me-1"></i>' +
                '<strong>Valid 12-digit GTIN entered.</strong> Check digit auto-calculated: <strong>' + cd + '</strong>' +
                '<br><span class="font-monospace fw-bold" style="font-size:14px; letter-spacing:1px;">' + resolvedEAN + '</span></div>'
            );
            gs1Section.show();
            $('#gs1VerifyLink').attr('href', 'https://www.gs1.org/services/verified-by-gs1/results?gtin=' + resolvedEAN);
            log("12-digit input → auto check digit = " + cd + " → EAN-13: " + resolvedEAN);
            renderEANBarcode(resolvedEAN);
        } else if (val.length === 13) {
            if (validateEAN13(val)) {
                resolvedEAN = val;
                validationDiv.removeClass('d-none').html(
                    '<div class="alert alert-success py-2 px-3 mb-0 small"><i class="fas fa-check-circle me-1"></i>' +
                    '<strong>Valid EAN-13</strong> — Check digit verified.' +
                    '<br><span class="font-monospace fw-bold" style="font-size:14px; letter-spacing:1px;">' + val + '</span></div>'
                );
                gs1Section.show();
                $('#gs1VerifyLink').attr('href', 'https://www.gs1.org/services/verified-by-gs1/results?gtin=' + val);
                log("EAN-13 validated: " + val);
                renderEANBarcode(val);
            } else {
                var expected = calcCheckDigit(val.substring(0, 12));
                validationDiv.removeClass('d-none').html(
                    '<div class="alert alert-danger py-2 px-3 mb-0 small"><i class="fas fa-times-circle me-1"></i>' +
                    '<strong>Invalid check digit!</strong> You entered <strong>' + val[12] + '</strong> but expected <strong>' + expected + '</strong>.' +
                    '<br>Correct EAN-13 would be: <span class="font-monospace fw-bold">' + val.substring(0,12) + expected + '</span></div>'
                );
                gs1Section.hide();
                hideBarcode();
            }
        } else if (val.length === 8) {
            resolvedEAN = val;
            validationDiv.removeClass('d-none').html(
                '<div class="alert alert-info py-2 px-3 mb-0 small"><i class="fas fa-info-circle me-1"></i>' +
                '<strong>EAN-8 detected</strong> — 8-digit short barcode format.</div>'
            );
            gs1Section.show();
            $('#gs1VerifyLink').attr('href', 'https://www.gs1.org/services/verified-by-gs1/results?gtin=' + val);
            renderEANBarcode(val);
        } else {
            validationDiv.removeClass('d-none').html(
                '<span class="text-muted small"><i class="fas fa-keyboard me-1"></i>' + val.length + ' / 12-13 digits entered</span>'
            );
            gs1Section.hide();
            hideBarcode();
        }
    });

    // ═══ PROFESSIONAL EAN-13 BARCODE RENDERING ═══
    function renderEANBarcode(ean) {
        try {
            var format = ean.length === 8 ? 'EAN8' : 'EAN13';
            
            // GS1 Standard: EAN-13 barcode dimensions
            // Module width (X): min 0.264mm, nominal 0.33mm
            // Height: min 22.85mm (standard is 25.93mm)
            // Quiet zone: min 11X left, 7X right
            JsBarcode('#barcode', ean, {
                format: format,
                lineColor: '#000000',
                width: 2,         // module width in px
                height: 70,       // bar height — proportional to GS1 spec
                displayValue: true,
                font: 'OCR-B, monospace',  // GS1 mandates OCR-B font
                fontSize: 14,
                textMargin: 2,
                margin: 15,       // quiet zone
                flat: false,       // EAN guard pattern rendering
                background: '#ffffff'
            });
            
            // Show label and update elements
            $('#emptyState').hide();
            $('#barcodeLabelWrap').show();
            $('#eanBadge').text(ean);
            $('#downloadBtn, #printBtn').prop('disabled', false);
            
            // Update product name in label
            var prodName = els.prodSelect.find(':selected').text();
            if (prodName && prodName !== '-- Select Product --' && prodName !== '-- Select Category First --' && prodName !== 'Loading...') {
                $('#labelProductName').text(prodName);
            }
            
            log('EAN barcode rendered: ' + ean + ' (' + format + ') — GS1 compliant');
            updateLabelInfo();
        } catch(e) {
            log('Barcode Render Error: ' + e.message);
            validationDiv = $('#eanValidation');
            validationDiv.removeClass('d-none').html(
                '<div class="alert alert-danger py-2 px-3 mb-0 small"><i class="fas fa-exclamation-triangle me-1"></i>' +
                'Barcode rendering failed: ' + e.message + '</div>'
            );
        }
    }

    function hideBarcode() {
        $('#barcodeLabelWrap').hide();
        $('#emptyState').show();
        $('#downloadBtn, #printBtn').prop('disabled', true);
    }

    function updateLabelInfo() {
        var sku = getCurrentSKU();
        var wtCode = els.wtCode.val() || '';
        var prodName = els.prodSelect.find(':selected').text();
        
        // Update product name
        if (prodName && prodName.indexOf('--') !== 0 && prodName !== 'Loading...' && prodName.indexOf('No products') !== 0) {
            $('#labelProductName').text(prodName);
        } else {
            $('#labelProductName').text('');
        }
        
        // Update SKU row in label
        if (sku && sku.length >= 4) {
            $('#labelSku').text(sku);
            $('#labelWeight').text(wtCode || '—');
            $('#labelSkuRow').show();
            $('#skuBadgeRow').show();
            $('#skuBadge').text('SKU: ' + sku);
        } else {
            $('#labelSkuRow').hide();
            $('#skuBadgeRow').hide();
        }
    }

    // ═══ CATEGORY → PRODUCT → WEIGHT CHAIN ═══
    els.catSelect.on('select2:select', function(e) {
        var catId = $(this).val();
        if(!catId) return;
        
        log('Category Selected: ID ' + catId);
        
        els.prodSelect.empty().append('<option value="">Loading...</option>').prop('disabled', true);
        els.wtSelect.empty().append('<option value="">-- Select Product First --</option>').prop('disabled', true);
        els.wtCode.val('');
        els.prodCode.val('');
        
        var opt = $(e.params.data.element);
        var catName = opt.attr('data-name') || e.params.data.text || '';
        var code = opt.attr('data-code') || generateInitials(catName);
        els.catCode.val(code);
        log('Category Code: ' + code);

        fetchProducts(catId);
        updateSKU();
    });

    els.prodSelect.on('select2:select', function(e) {
        var prodId = $(this).val();
        if(!prodId) return;

        var opt = $(e.params.data.element);
        var serial = opt.attr('data-serial') || '001';
        log('Product Selected: ID ' + prodId + ', Serial: ' + serial);
        
        els.prodCode.val(serial);
        fetchWeights(prodId);
        updateSKU();
        // Re-render barcode to update label
        if (resolvedEAN) renderEANBarcode(resolvedEAN);
    });

    els.wtSelect.on('change', function() {
        var val = $(this).val();
        if(val) {
            els.wtCode.val(val);
            updateSKU();
            updateLabelInfo();
            log('Weight selected: ' + val);
        }
    });

    // ═══ SKU GENERATION (follows pre-configured logic: G + CatCode + Serial - Weight) ═══
    function getCurrentSKU() {
        var prefix = els.prefix.val() || '';
        var catCode = els.catCode.val() || '';
        var prodCode = els.prodCode.val() || '';
        var wtCode = els.wtCode.val() || '';
        
        var sku = prefix + catCode + prodCode;
        if (wtCode) sku += '-' + wtCode;
        return sku;
    }

    function updateSKU() {
        var sku = getCurrentSKU();
        $('#skuPreview').text(sku || '—');
        
        // Decide whether to show Save button
        var hasProduct = els.prodSelect.val();
        var catCode = els.catCode.val();
        
        if (resolvedEAN && hasProduct && catCode) {
            els.saveBtn.removeClass('d-none');
        } else {
            els.saveBtn.addClass('d-none');
        }
        
        updateLabelInfo();
        return sku;
    }

    // ═══ PREVIEW BUTTON ═══
    els.genBtn.on('click', function() {
        if (resolvedEAN) {
            renderEANBarcode(resolvedEAN);
            updateSKU();
        } else {
            var raw = els.eanInput.val().replace(/[^0-9]/g, '');
            if (raw.length === 12) {
                resolvedEAN = raw + calcCheckDigit(raw);
                renderEANBarcode(resolvedEAN);
            } else {
                showToast('Enter a valid 12 or 13 digit EAN/GTIN number first.', 'danger');
            }
        }
    });

    // ═══ SAVE EAN & ASSIGN TO PRODUCT ═══
    els.saveBtn.on('click', function() {
        var ean = resolvedEAN;
        var catId = els.catSelect.val();
        var prodId = els.prodSelect.val();
        var weightCode = els.wtCode.val();
        var sku = getCurrentSKU();
        
        if (!ean || ean.length < 8) {
            showToast('Please enter a valid EAN/GTIN number.', 'danger');
            return;
        }
        if (!prodId) {
            showToast('Please select a product.', 'danger');
            return;
        }
        if (!sku || sku.length < 4) {
            showToast('SKU not generated. Select category, product and weight first.', 'danger');
            return;
        }
        
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');
        log('Saving EAN ' + ean + ' with SKU ' + sku + ' for Product ID ' + prodId + '...');

        $.ajax({
            url: 'assign_barcodes.php',
            method: 'POST',
            data: {
                action: 'assign_ean',
                ean: ean,
                sku: sku,
                category_id: catId || 0,
                product_id: prodId,
                weight_code: weightCode || ''
            },
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    log('SUCCESS: ' + res.message);
                    showToast(res.message, 'success');
                    // Reset EAN input for next entry, keep category/product
                    els.eanInput.val('');
                    resolvedEAN = '';
                    $('#eanValidation').addClass('d-none');
                    $('#gs1VerifySection').hide();
                    hideBarcode();
                    els.saveBtn.addClass('d-none');
                } else {
                    log('ERROR: ' + res.error);
                    showToast(res.error, 'danger');
                }
            },
            error: function(xhr, status, err) {
                log('AJAX Fatal: ' + status + ' - ' + err);
                showToast('System Error. Check debug console.', 'danger');
            },
            complete: function() {
                els.saveBtn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Save EAN & Assign to Product');
            }
        });
    });

    // ═══ CORE FUNCTIONS ═══
    function fetchProducts(catId) {
        log('Fetching products for CatID ' + catId + '...');
        
        $.ajax({
            url: 'get_products_by_category.php',
            data: { category_id: catId },
            dataType: 'json',
            success: function(res) {
                els.prodSelect.empty().append('<option value="">-- Select Product --</option>');
                
                if(res.products && res.products.length > 0) {
                    res.products.forEach(function(p) {
                        var opt = new Option(p.name, p.id, false, false);
                        $(opt).attr('data-serial', p.serial);
                        els.prodSelect.append(opt);
                    });
                    log('Loaded ' + res.products.length + ' products.');
                } else {
                    els.prodSelect.append('<option value="">No products found</option>');
                }
            },
            error: function(xhr, status, err) {
                log('AJAX Error: ' + status + ' - ' + err);
                els.prodSelect.empty().append('<option value="">Error loading products</option>');
            },
            complete: function() {
                els.prodSelect.prop('disabled', false);
                if(els.prodSelect.hasClass('select2-hidden-accessible')) {
                    els.prodSelect.select2('destroy');
                }
                els.prodSelect.select2({ theme: 'bootstrap-5' });
            }
        });
    }

    function fetchWeights(prodId) {
        log('Fetching weights for ProdID ' + prodId + '...');
        els.wtSelect.empty().append('<option>Loading...</option>').prop('disabled', true);

        $.ajax({
            url: 'get_product_weights.php',
            data: { product_id: prodId },
            dataType: 'json',
            success: function(res) {
                els.wtSelect.empty().append('<option value="">-- Select Weight --</option>');
                
                if(res.success && res.weights) {
                    res.weights.forEach(function(w) {
                        var displayWt = w.display_weight || '';
                        var wtCode = displayWt.replace(/\s+/g, '').toUpperCase();
                        els.wtSelect.append('<option value="' + wtCode + '">' + displayWt + ' (\u20B9' + w.price + ')</option>');
                    });
                    log('Loaded ' + res.weights.length + ' weights.');
                } else {
                    els.wtSelect.append('<option value="">No weights found</option>');
                }
            },
            error: function(xhr, status, err) {
                log('AJAX Error: ' + status + ' - ' + err);
                els.wtSelect.empty().append('<option>Error loading weights</option>');
            },
            complete: function() {
                els.wtSelect.prop('disabled', false);
            }
        });
    }

    function generateInitials(name) {
        if(!name) return 'XX';
        var words = name.trim().split(/\s+/);
        if(words.length >= 2) {
            return (words[0][0] + words[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    function showToast(message, type) {
        var existing = document.getElementById('barcodeToast');
        if (existing) existing.remove();
        
        var colors = {
            success: { bg: '#d1fae5', border: '#10b981', text: '#065f46', icon: 'fa-check-circle' },
            danger:  { bg: '#fee2e2', border: '#ef4444', text: '#991b1b', icon: 'fa-exclamation-circle' },
            info:    { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af', icon: 'fa-info-circle' }
        };
        var c = colors[type] || colors.success;
        
        var toast = document.createElement('div');
        toast.id = 'barcodeToast';
        toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;max-width:420px;background:' + c.bg + ';border:1px solid ' + c.border + ';border-left:4px solid ' + c.border + ';border-radius:8px;padding:14px 18px;box-shadow:0 8px 25px rgba(0,0,0,.15);display:flex;align-items:center;gap:10px;animation:toastIn .3s ease;';
        toast.innerHTML = '<i class="fas ' + c.icon + '" style="color:' + c.border + ';font-size:18px;"></i><div style="flex:1;font-size:13px;color:' + c.text + ';font-weight:500;">' + message + '</div><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;opacity:0.5;font-size:16px;">&times;</button>';
        document.body.appendChild(toast);
        setTimeout(function() { if (toast.parentElement) toast.remove(); }, 5000);
    }
});

// ═══ PRINT (Professional barcode label only) ═══
function printBarcode() {
    var label = document.getElementById('barcodeLabel');
    if (!label) return;
    var w = window.open('', '_blank', 'width=500,height=400');
    w.document.write('<html><head><title>Print EAN Barcode</title>');
    w.document.write('<style>body{margin:20px;font-family:Helvetica,Arial,sans-serif;} @page{margin:10mm;}</style>');
    w.document.write('</head><body>');
    w.document.write(label.outerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); w.close(); }, 400);
}

// ═══ DOWNLOAD AS PNG ═══
function downloadBarcode() {
    var svg = document.getElementById('barcode');
    if (!svg) return;
    var svgData = new XMLSerializer().serializeToString(svg);
    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d');
    var img = new Image();
    img.onload = function() {
        canvas.width = img.width * 2;
        canvas.height = img.height * 2;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        var a = document.createElement('a');
        a.download = 'EAN_' + ($('#eanBadge').text() || 'barcode') + '.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
    };
    img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
}
</script>
<style>
@keyframes toastIn { from { transform:translateX(100%);opacity:0 } to { transform:translateX(0);opacity:1 } }
#barcodeLabel { transition: all 0.3s ease; }
#barcodeLabel:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.12); }
@media print {
    body * { visibility: hidden !important; }
    #barcodeLabel, #barcodeLabel * { visibility: visible !important; }
    #barcodeLabel { position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); border:1px solid #000 !important; }
}
</style>
