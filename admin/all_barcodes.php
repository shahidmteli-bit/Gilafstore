<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin();

$pageTitle = 'All Product Barcodes';
$adminPage = 'barcode_generator'; // Keep same active menu

// Fetch all data: Join product_weights, products, and categories
$db = get_db_connection();
$query = "
    SELECT 
        pw.id as weight_id, 
        pw.weight_value, 
        pw.display_weight, 
        pw.price,
        p.id as product_id, 
        p.name as product_name, 
        c.name as category_name, 
        c.category_code
    FROM product_weights pw
    JOIN products p ON pw.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY c.name ASC, p.name ASC, pw.weight_value ASC
";
try {
    $items = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $items = [];
    $error = "Error fetching data: " . $e->getMessage();
}

// Helper for Category Code Fallback
function get_category_code_php($row) {
    if (!empty($row['category_code'])) return $row['category_code'];
    
    $map = [
        'Spices' => 'S', 'Honey' => 'H', 'Tea' => 'T', 'Dry Fruits' => 'DF',
        'Oils' => 'O', 'Grains' => 'G', 'Snacks' => 'SN', 'Dairy' => 'D',
        'Pulses' => 'P', 'Condiments' => 'C', 'Olive Oil' => 'OO'
    ];
    return $map[$row['category_name']] ?? 'XX';
}

// Prepare items with generated codes
foreach ($items as &$item) {
    $prefix = "GK";
    $catCode = get_category_code_php($item);
    // Use Product ID padded to 3 digits (e.g. 1 -> 001)
    $prodCode = str_pad($item['product_id'], 3, '0', STR_PAD_LEFT);
    
    // Ensure weight is just the number (float/int)
    $weight = 0;
    if (!empty($item['weight_value'])) {
        $weight = (float)$item['weight_value'];
    } elseif (!empty($item['display_weight'])) {
        // Try to extract number from display text like "200g"
        if (preg_match('/(\d+(\.\d+)?)/', $item['display_weight'], $matches)) {
            $weight = (float)$matches[1];
        }
    }
    
    $item['barcode_string'] = "{$prefix}{$catCode}{$prodCode}-{$weight}";
}
unset($item);

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h4 class="fw-semibold mb-0"><i class="fas fa-th me-2"></i>All Product Barcodes</h4>
            <p class="text-muted mb-0">Bulk view of all generated barcodes. Ready for printing.</p>
        </div>
        <div>
            <button class="btn btn-primary me-2" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Labels
            </button>
            <a href="<?= base_url('admin/barcode_generator.php'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Generator
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Filter Bar (No Print) -->
    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-auto">
                    <label class="fw-bold small me-2"><i class="fas fa-filter"></i> Filter:</label>
                </div>
                <div class="col-md-4">
                    <input type="text" id="searchFilter" class="form-control form-control-sm" placeholder="Search product name or code...">
                </div>
                <div class="col text-end text-muted small">
                    Showing <span id="countDisplay"><?= count($items); ?></span> barcodes
                </div>
            </div>
        </div>
    </div>

    <!-- Printable Grid -->
    <div class="barcode-grid" id="printableArea">
        <?php foreach ($items as $item): ?>
            <div class="barcode-item-wrapper" data-search="<?= htmlspecialchars(strtolower($item['product_name'] . ' ' . $item['barcode_string'])); ?>">
                <div class="barcode-sticker">
                    <div class="product-name text-truncate"><?= htmlspecialchars($item['product_name']); ?></div>
                    <div class="product-meta">
                        <?= htmlspecialchars($item['category_name']); ?> • <?= htmlspecialchars($item['display_weight']); ?>
                    </div>
                    <svg class="barcode-svg"
                         jsbarcode-format="CODE128"
                         jsbarcode-value="<?= htmlspecialchars($item['barcode_string']); ?>"
                         jsbarcode-textmargin="0"
                         jsbarcode-fontoptions="bold"
                         jsbarcode-width="1.5"
                         jsbarcode-height="40"
                         jsbarcode-fontSize="12">
                    </svg>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- JsBarcode -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<style>
    /* Screen Styles */
    .barcode-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .barcode-sticker {
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 10px;
        text-align: center;
        transition: transform 0.2s;
    }
    
    .barcode-sticker:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        border-color: #bbb;
    }
    
    .product-name {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 2px;
        color: #000;
    }
    
    .product-meta {
        font-size: 0.75rem;
        color: #666;
        margin-bottom: 5px;
    }
    
    .barcode-svg {
        max-width: 100%;
        height: auto;
    }

    /* Print Styles */
    @media print {
        @page {
            size: A4;
            margin: 10mm;
        }
        
        body {
            background: white;
            font-family: Arial, sans-serif;
        }
        
        .no-print, .admin-sidebar, .admin-topbar {
            display: none !important;
        }
        
        .admin-content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }
        
        .container-fluid {
            padding: 0 !important;
            max-width: none !important;
        }
        
        .barcode-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 columns for A4 usually works well */
            gap: 5mm;
        }
        
        .barcode-item-wrapper {
            break-inside: avoid;
        }
        
        .barcode-sticker {
            border: 1px dashed #ccc; /* Helper lines for cutting, optional */
            border-radius: 0;
            box-shadow: none !important;
            padding: 5px;
        }
    }
</style>

<script>
    // Initialize Barcodes
    JsBarcode(".barcode-svg").init();

    // Search Filter
    document.getElementById('searchFilter').addEventListener('keyup', function() {
        const query = this.value.toLowerCase();
        const items = document.querySelectorAll('.barcode-item-wrapper');
        let count = 0;
        
        items.forEach(item => {
            const searchText = item.getAttribute('data-search');
            if (searchText.includes(query)) {
                item.style.display = '';
                count++;
            } else {
                item.style.display = 'none';
            }
        });
        
        document.getElementById('countDisplay').textContent = count;
    });
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
