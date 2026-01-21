<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$message = '';
$messageType = '';
$tablesExist = true;

// Check if tables exist
try {
    $db = get_db_connection();
    $result = $db->query("SHOW TABLES LIKE 'hero_banner_settings'");
    $tablesExist = $result->rowCount() > 0;
} catch (PDOException $e) {
    $tablesExist = false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tablesExist) {
    
    try {
        // Toggle slider
        if (isset($_POST['toggle_slider'])) {
            $enabled = $_POST['slider_enabled'] === '1' ? '1' : '0';
            db_query("UPDATE hero_banner_settings SET setting_value = ? WHERE setting_key = 'slider_enabled'", [$enabled]);
            $message = 'Slider setting updated!';
            $messageType = 'success';
        }
        
        // Toggle default slide
        if (isset($_POST['toggle_default_slide'])) {
            $enabled = $_POST['default_slide_enabled'] === '1' ? '1' : '0';
            // Check if setting exists, if not insert it
            $exists = db_fetch("SELECT * FROM hero_banner_settings WHERE setting_key = 'default_slide_enabled'");
            if ($exists) {
                db_query("UPDATE hero_banner_settings SET setting_value = ? WHERE setting_key = 'default_slide_enabled'", [$enabled]);
            } else {
                db_query("INSERT INTO hero_banner_settings (setting_key, setting_value) VALUES ('default_slide_enabled', ?)", [$enabled]);
            }
            $message = 'Default slide setting updated!';
            $messageType = 'success';
        }
        
        // Update timer
        if (isset($_POST['update_timer'])) {
            $timer = max(1, intval($_POST['slider_timer']));
            db_query("UPDATE hero_banner_settings SET setting_value = ? WHERE setting_key = 'slider_timer'", [$timer]);
            $message = 'Slider timer updated!';
            $messageType = 'success';
        }
    
    // Add new slide
    if (isset($_POST['add_slide'])) {
        if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['slide_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (in_array($file['type'], $allowedTypes)) {
                $uploadDir = __DIR__ . '/../assets/uploads/hero_banner/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'hero_' . time() . '_' . uniqid() . '.' . $ext;
                $uploadPath = $uploadDir . $filename;
                
                // Move file without compression
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $imagePath = 'assets/uploads/hero_banner/' . $filename;
                    $heading = trim($_POST['heading_text'] ?? '');
                    $subText = trim($_POST['sub_text'] ?? '');
                    $ctaText = trim($_POST['cta_text'] ?? '');
                    $ctaLink = trim($_POST['cta_link'] ?? '');
                    $cta2Text = trim($_POST['cta2_text'] ?? '');
                    $cta2Link = trim($_POST['cta2_link'] ?? '');
                    
                    // Get max order
                    $maxOrder = db_fetch("SELECT MAX(display_order) as max_order FROM hero_banner_slides");
                    $order = ($maxOrder['max_order'] ?? 0) + 1;
                    
                    db_query(
                        "INSERT INTO hero_banner_slides (image_path, heading_text, sub_text, cta_text, cta_link, cta2_text, cta2_link, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [$imagePath, $heading ?: null, $subText ?: null, $ctaText ?: null, $ctaLink ?: null, $cta2Text ?: null, $cta2Link ?: null, $order]
                    );
                    
                    $message = 'Slide added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to upload image.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Invalid file type. Only JPG, PNG, WEBP allowed.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please select an image to upload.';
            $messageType = 'error';
        }
    }
    
    // Delete slide
    if (isset($_POST['delete_slide'])) {
        $slideId = intval($_POST['slide_id']);
        $slide = db_fetch("SELECT image_path FROM hero_banner_slides WHERE id = ?", [$slideId]);
        
        if ($slide) {
            // Delete file
            $filePath = __DIR__ . '/../' . $slide['image_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            db_query("DELETE FROM hero_banner_slides WHERE id = ?", [$slideId]);
            $message = 'Slide deleted successfully!';
            $messageType = 'success';
        }
    }
    
    // Edit slide
    if (isset($_POST['edit_slide'])) {
        $slideId = intval($_POST['slide_id']);
        $heading = trim($_POST['heading_text'] ?? '');
        $subText = trim($_POST['sub_text'] ?? '');
        $ctaText = trim($_POST['cta_text'] ?? '');
        $ctaLink = trim($_POST['cta_link'] ?? '');
        $cta2Text = trim($_POST['cta2_text'] ?? '');
        $cta2Link = trim($_POST['cta2_link'] ?? '');
        
        // Check if new image uploaded
        if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['slide_image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (in_array($file['type'], $allowedTypes)) {
                // Delete old image
                $oldSlide = db_fetch("SELECT image_path FROM hero_banner_slides WHERE id = ?", [$slideId]);
                if ($oldSlide) {
                    $oldPath = __DIR__ . '/../' . $oldSlide['image_path'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                
                $uploadDir = __DIR__ . '/../assets/uploads/hero_banner/';
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'hero_' . time() . '_' . uniqid() . '.' . $ext;
                $uploadPath = $uploadDir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $imagePath = 'assets/uploads/hero_banner/' . $filename;
                    db_query(
                        "UPDATE hero_banner_slides SET image_path = ?, heading_text = ?, sub_text = ?, cta_text = ?, cta_link = ?, cta2_text = ?, cta2_link = ? WHERE id = ?",
                        [$imagePath, $heading ?: null, $subText ?: null, $ctaText ?: null, $ctaLink ?: null, $cta2Text ?: null, $cta2Link ?: null, $slideId]
                    );
                }
            }
        } else {
            db_query(
                "UPDATE hero_banner_slides SET heading_text = ?, sub_text = ?, cta_text = ?, cta_link = ?, cta2_text = ?, cta2_link = ? WHERE id = ?",
                [$heading ?: null, $subText ?: null, $ctaText ?: null, $ctaLink ?: null, $cta2Text ?: null, $cta2Link ?: null, $slideId]
            );
        }
        
        $message = 'Slide updated successfully!';
        $messageType = 'success';
    }
    
    } catch (PDOException $e) {
        $message = 'Database error. Please run setup first.';
        $messageType = 'error';
    }
}

// Fetch current settings
$sliderEnabled = '0';
$sliderTimer = '5';
$defaultSlideEnabled = '1'; // Default ON
try {
    $settings = db_fetch_all("SELECT setting_key, setting_value FROM hero_banner_settings");
    foreach ($settings as $s) {
        if ($s['setting_key'] === 'slider_enabled') $sliderEnabled = $s['setting_value'];
        if ($s['setting_key'] === 'slider_timer') $sliderTimer = $s['setting_value'];
        if ($s['setting_key'] === 'default_slide_enabled') $defaultSlideEnabled = $s['setting_value'];
    }
} catch (PDOException $e) {
    // Tables may not exist
}

// Fetch slides
$slides = [];
try {
    $slides = db_fetch_all("SELECT * FROM hero_banner_slides ORDER BY display_order ASC");
} catch (PDOException $e) {
    // Tables may not exist
}

$adminPage = 'hero_banner';
include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.hero-admin-container { padding: 20px; }
.settings-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px; }
.settings-card h3 { margin: 0 0 20px 0; color: #1A3C34; border-bottom: 2px solid #C5A059; padding-bottom: 10px; }
.setting-row { display: flex; align-items: center; gap: 20px; margin-bottom: 15px; }
.setting-row label { min-width: 150px; font-weight: 500; color: #333; }
.toggle-switch { position: relative; width: 60px; height: 30px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 30px; }
.toggle-slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .toggle-slider { background-color: #1A3C34; }
input:checked + .toggle-slider:before { transform: translateX(30px); }
.timer-input { width: 80px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
.btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; transition: all 0.3s; }
.btn-primary { background: #1A3C34; color: #fff; }
.btn-primary:hover { background: #0d1f1b; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }
.btn-gold { background: #C5A059; color: #fff; }
.btn-gold:hover { background: #b08d47; }
.slides-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
.slide-card { background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); cursor: move; }
.slide-card.dragging { opacity: 0.5; }
.slide-image { width: 100%; height: 200px; object-fit: cover; }
.slide-content { padding: 15px; }
.slide-content h4 { margin: 0 0 10px 0; color: #1A3C34; font-size: 1rem; }
.slide-content p { margin: 0 0 10px 0; color: #666; font-size: 0.85rem; }
.slide-actions { display: flex; gap: 10px; margin-top: 15px; }
.add-slide-form { display: grid; gap: 15px; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-weight: 500; color: #333; }
.form-group input, .form-group textarea { padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem; }
.form-group textarea { resize: vertical; min-height: 80px; }
.alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error { background: #f8d7da; color: #721c24; }
.order-badge { position: absolute; top: 10px; left: 10px; background: #1A3C34; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
.slide-card-wrapper { position: relative; }
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: #fff; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.modal-header h3 { margin: 0; color: #1A3C34; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666; }
</style>

<div class="hero-admin-container">
    <div class="content-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h1 style="margin: 0; color: #1A3C34;">Hero Banner Management</h1>
        <a href="setup_hero_banner.php" class="btn btn-gold">Run Setup</a>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType; ?>">
        <?= htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$tablesExist): ?>
    <div class="alert alert-error" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
        <strong>⚠️ Database tables not found!</strong><br>
        Please click the <strong>"Run Setup"</strong> button above to create the required database tables first.
    </div>
    <?php endif; ?>
    
    <!-- Settings Card -->
    <div class="settings-card">
        <h3>Slider Settings</h3>
        
        <div style="display: flex; flex-wrap: wrap; gap: 30px; align-items: flex-start;">
            <form method="POST" id="sliderToggleForm">
                <div class="setting-row">
                    <label>Enable Slider:</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="sliderCheckbox" <?= $sliderEnabled === '1' ? 'checked' : ''; ?> onchange="toggleSlider(this.checked);">
                        <span class="toggle-slider"></span>
                    </label>
                    <input type="hidden" name="toggle_slider" value="1">
                    <input type="hidden" name="slider_enabled" id="sliderValue" value="<?= $sliderEnabled === '1' ? '1' : '0'; ?>">
                </div>
            </form>
            
            <form method="POST" id="defaultSlideToggleForm">
                <div class="setting-row">
                    <label>Show Default Slide:</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="defaultSlideCheckbox" <?= $defaultSlideEnabled === '1' ? 'checked' : ''; ?> onchange="toggleDefaultSlide(this.checked);">
                        <span class="toggle-slider"></span>
                    </label>
                    <input type="hidden" name="toggle_default_slide" value="1">
                    <input type="hidden" name="default_slide_enabled" id="defaultSlideValue" value="<?= $defaultSlideEnabled === '1' ? '1' : '0'; ?>">
                    <small style="color: #666; font-size: 0.8rem; margin-left: 10px;">(Original spices image)</small>
                </div>
            </form>
            
            <form method="POST" style="display: inline-flex; align-items: center; gap: 15px;">
                <label>Slider Timer (seconds):</label>
                <input type="number" name="slider_timer" value="<?= htmlspecialchars($sliderTimer); ?>" min="1" max="30" class="timer-input">
                <button type="submit" name="update_timer" class="btn btn-primary">Update</button>
            </form>
        </div>
        
        <script>
        function toggleSlider(checked) {
            document.getElementById('sliderValue').value = checked ? '1' : '0';
            document.getElementById('sliderToggleForm').submit();
        }
        function toggleDefaultSlide(checked) {
            document.getElementById('defaultSlideValue').value = checked ? '1' : '0';
            document.getElementById('defaultSlideToggleForm').submit();
        }
        </script>
    </div>
    
    <!-- Fixed Content Display (Read-Only) -->
    <div class="settings-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 2px dashed #C5A059;">
        <h3>🔒 Fixed Hero Content (Locked)</h3>
        <p style="color: #666; margin-bottom: 15px; font-size: 0.9rem;"><i class="fas fa-info-circle" style="color: #C5A059;"></i> <strong>Note:</strong> The text and buttons below are permanently fixed and cannot be changed. Only background images can be modified.</p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div class="form-group">
                <label><i class="fas fa-lock" style="color: #999; margin-right: 5px;"></i>Headline</label>
                <input type="text" value="The Essence of Purity & Tradition" readonly disabled style="background: #f5f5f5; color: #666; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock" style="color: #999; margin-right: 5px;"></i>Tagline</label>
                <input type="text" value="Premium Heritage Foods" readonly disabled style="background: #f5f5f5; color: #666; cursor: not-allowed;">
            </div>
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
            <label><i class="fas fa-lock" style="color: #999; margin-right: 5px;"></i>Description</label>
            <textarea readonly disabled style="background: #f5f5f5; color: #666; cursor: not-allowed; min-height: 60px;">Experience the finest saffron, unadulterated honey, and hand-selected spices from the valleys of Kashmir. Curated by Gilaf Foods.</textarea>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label><i class="fas fa-lock" style="color: #999; margin-right: 5px;"></i>Button 1</label>
                <input type="text" value="Shop Collection" readonly disabled style="background: #f5f5f5; color: #666; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock" style="color: #999; margin-right: 5px;"></i>Button 1 Link</label>
                <input type="text" value="shop.php" readonly disabled style="background: #f5f5f5; color: #666; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock" style="color: #999; margin-right: 5px;"></i>Button 2</label>
                <input type="text" value="Verify My Product" readonly disabled style="background: #f5f5f5; color: #666; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock" style="color: #999; margin-right: 5px;"></i>Button 2 Link</label>
                <input type="text" value="#verification" readonly disabled style="background: #f5f5f5; color: #666; cursor: not-allowed;">
            </div>
        </div>
    </div>
    
    <!-- Add New Image Card -->
    <div class="settings-card">
        <h3>🖼️ Add Hero Background Image</h3>
        <p style="color: #666; margin-bottom: 15px; font-size: 0.9rem;">Upload high-resolution images that will slide behind the fixed content. Images transition from <strong>right → left</strong>.</p>
        <form method="POST" enctype="multipart/form-data" class="add-slide-form">
            <div class="form-group">
                <label>Background Image (JPG/PNG/WEBP) *</label>
                <input type="file" name="slide_image" accept=".jpg,.jpeg,.png,.webp" required style="padding: 15px; border: 2px dashed #C5A059; border-radius: 8px; width: 100%; cursor: pointer;">
                <small style="color: #888; display: block; margin-top: 8px;">
                    <i class="fas fa-lightbulb" style="color: #C5A059;"></i> 
                    <strong>Recommended:</strong> Use high-resolution images (1920x800px or larger). Images will auto-fit the hero container.
                </small>
            </div>
            <!-- Hidden fields with fixed values -->
            <input type="hidden" name="heading_text" value="">
            <input type="hidden" name="sub_text" value="">
            <input type="hidden" name="cta_text" value="">
            <input type="hidden" name="cta_link" value="">
            <input type="hidden" name="cta2_text" value="">
            <input type="hidden" name="cta2_link" value="">
            <button type="submit" name="add_slide" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fas fa-upload"></i> Upload Image
            </button>
        </form>
    </div>
    
    <!-- Background Images List -->
    <div class="settings-card">
        <h3>🖼️ Hero Background Images (Drag to Reorder)</h3>
        <p style="color: #666; margin-bottom: 15px; font-size: 0.9rem;">These images will slide behind the fixed content. First image is the default/primary.</p>
        
        <?php if (empty($slides)): ?>
        <p style="color: #666; text-align: center; padding: 40px;">No background images added yet. Upload your first image above!</p>
        <?php else: ?>
        <div class="slides-grid" id="slidesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
            <?php foreach ($slides as $index => $slide): ?>
            <div class="slide-card-wrapper" data-id="<?= $slide['id']; ?>" draggable="true" style="position: relative; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s, box-shadow 0.3s;">
                <span class="order-badge" style="position: absolute; top: 10px; left: 10px; background: <?= $index === 0 ? '#C5A059' : 'rgba(0,0,0,0.7)'; ?>; color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; z-index: 5;">
                    <?= $index === 0 ? '⭐ Primary' : '#' . ($index + 1); ?>
                </span>
                <div class="slide-card">
                    <img src="<?= base_url($slide['image_path']); ?>" alt="Background Image" style="width: 100%; height: 180px; object-fit: cover; display: block;">
                    <div class="slide-actions" style="padding: 12px; background: #f8f9fa; display: flex; justify-content: space-between; align-items: center;">
                        <button class="btn btn-gold" onclick="openImageReplaceModal(<?= $slide['id']; ?>, '<?= base_url($slide['image_path']); ?>')" style="padding: 8px 16px; font-size: 0.85rem;">
                            <i class="fas fa-image"></i> Replace
                        </button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this background image?');">
                            <input type="hidden" name="slide_id" value="<?= $slide['id']; ?>">
                            <button type="submit" name="delete_slide" class="btn btn-danger" style="padding: 8px 16px; font-size: 0.85rem;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Image Replace Modal -->
<div class="modal" id="imageReplaceModal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>🖼️ Replace Background Image</h3>
            <button class="modal-close" onclick="closeImageReplaceModal()">&times;</button>
        </div>
        <div style="margin-bottom: 20px;">
            <p style="color: #666; font-size: 0.9rem;">Current image preview:</p>
            <img id="currentImagePreview" src="" alt="Current" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #eee;">
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="slide_id" id="replace_slide_id">
            <!-- Hidden empty values for text fields (content is fixed) -->
            <input type="hidden" name="heading_text" value="">
            <input type="hidden" name="sub_text" value="">
            <input type="hidden" name="cta_text" value="">
            <input type="hidden" name="cta_link" value="">
            <input type="hidden" name="cta2_text" value="">
            <input type="hidden" name="cta2_link" value="">
            <div class="form-group">
                <label>Select New Image *</label>
                <input type="file" name="slide_image" accept=".jpg,.jpeg,.png,.webp" required style="padding: 15px; border: 2px dashed #C5A059; border-radius: 8px; width: 100%;">
                <small style="color: #888; margin-top: 8px; display: block;">Recommended: 1920x800px or larger for best quality</small>
            </div>
            <button type="submit" name="edit_slide" class="btn btn-primary" style="width: 100%;">
                <i class="fas fa-sync-alt"></i> Replace Image
            </button>
        </form>
    </div>
</div>

<script>
// Image Replace Modal
function openImageReplaceModal(slideId, imageSrc) {
    document.getElementById('replace_slide_id').value = slideId;
    document.getElementById('currentImagePreview').src = imageSrc;
    document.getElementById('imageReplaceModal').classList.add('active');
}

function closeImageReplaceModal() {
    document.getElementById('imageReplaceModal').classList.remove('active');
}

// Drag & Drop Reordering
const slidesGrid = document.getElementById('slidesGrid');
if (slidesGrid) {
    let draggedItem = null;
    
    slidesGrid.querySelectorAll('.slide-card-wrapper').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            setTimeout(() => this.classList.add('dragging'), 0);
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            draggedItem = null;
            updateOrder();
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            const afterElement = getDragAfterElement(slidesGrid, e.clientY);
            if (afterElement == null) {
                slidesGrid.appendChild(draggedItem);
            } else {
                slidesGrid.insertBefore(draggedItem, afterElement);
            }
        });
    });
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.slide-card-wrapper:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
    
    function updateOrder() {
        const items = slidesGrid.querySelectorAll('.slide-card-wrapper');
        const order = [];
        items.forEach((item, index) => {
            order.push({ id: item.dataset.id, order: index + 1 });
            item.querySelector('.order-badge').textContent = '#' + (index + 1);
        });
        
        // Send to server
        fetch('api/update_hero_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order: order })
        });
    }
}

// Fix toggle switch form submission
document.querySelectorAll('.toggle-switch input').forEach(input => {
    input.addEventListener('change', function() {
        const hiddenInput = this.form.querySelector('input[name="slider_enabled"]:last-of-type');
        hiddenInput.value = this.checked ? '1' : '0';
    });
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
