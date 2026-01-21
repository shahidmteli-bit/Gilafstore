<?php
/**
 * Advertisements & Highlights Management
 * Admin panel for managing homepage media section
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Advertisements & Highlights';
$adminPage = 'advertisements';

$message = '';
$error = '';

// Check if system is set up
$systemReady = false;
try {
    $db = get_db_connection();
    $result = $db->query("SHOW TABLES LIKE 'advertisements_media'");
    $systemReady = $result->rowCount() > 0;
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

if (!$systemReady) {
    header('Location: ' . base_url('admin/setup_advertisements.php'));
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Toggle slider setting
    if ($action === 'toggle_slider') {
        $enabled = isset($_POST['slider_enabled']) ? '1' : '0';
        db_query("UPDATE advertisements_settings SET setting_value = ? WHERE setting_key = 'slider_enabled'", [$enabled]);
        $message = 'Slider setting updated!';
    }
    
    // Upload video
    if ($action === 'upload_video' && isset($_FILES['video_file'])) {
        $file = $_FILES['video_file'];
        $allowedTypes = ['video/mp4'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (in_array($file['type'], $allowedTypes)) {
                // Delete existing video first
                $existingVideo = db_fetch("SELECT * FROM advertisements_media WHERE media_type = 'video'");
                if ($existingVideo) {
                    $oldPath = __DIR__ . '/../' . $existingVideo['file_path'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                    db_query("DELETE FROM advertisements_media WHERE media_type = 'video'");
                }
                
                // Upload new video
                $uploadDir = __DIR__ . '/../assets/uploads/advertisements/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = 'video_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                $filePath = 'assets/uploads/advertisements/' . $fileName;
                
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                    db_query("INSERT INTO advertisements_media (media_type, file_name, file_path, display_order) VALUES ('video', ?, ?, 0)", 
                        [$fileName, $filePath]);
                    $message = 'Video uploaded successfully!';
                } else {
                    $error = 'Failed to move uploaded file.';
                }
            } else {
                $error = 'Invalid video format. Only MP4 is allowed.';
            }
        } elseif ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = 'Upload error: ' . $file['error'];
        }
    }
    
    // Upload images
    if ($action === 'upload_images' && isset($_FILES['image_files'])) {
        $files = $_FILES['image_files'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $uploadedCount = 0;
        
        $uploadDir = __DIR__ . '/../assets/uploads/advertisements/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Get max display order
        $maxOrder = db_fetch("SELECT MAX(display_order) as max_order FROM advertisements_media WHERE media_type = 'image'");
        $displayOrder = ($maxOrder['max_order'] ?? 0) + 1;
        
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                if (in_array($files['type'][$i], $allowedTypes)) {
                    $fileName = 'img_' . time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $files['name'][$i]);
                    $filePath = 'assets/uploads/advertisements/' . $fileName;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $fileName)) {
                        db_query("INSERT INTO advertisements_media (media_type, file_name, file_path, display_order) VALUES ('image', ?, ?, ?)", 
                            [$fileName, $filePath, $displayOrder]);
                        $uploadedCount++;
                        $displayOrder++;
                    }
                }
            }
        }
        
        if ($uploadedCount > 0) {
            $message = $uploadedCount . ' image(s) uploaded successfully!';
        } else {
            $error = 'No images were uploaded. Check file formats (JPG, PNG, WEBP only).';
        }
    }
    
    // Delete media
    if ($action === 'delete_media') {
        $mediaId = (int)$_POST['media_id'];
        $media = db_fetch("SELECT * FROM advertisements_media WHERE id = ?", [$mediaId]);
        
        if ($media) {
            $filePath = __DIR__ . '/../' . $media['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            db_query("DELETE FROM advertisements_media WHERE id = ?", [$mediaId]);
            $message = 'Media deleted successfully!';
        }
    }
    
    // Delete video
    if ($action === 'delete_video') {
        $video = db_fetch("SELECT * FROM advertisements_media WHERE media_type = 'video'");
        if ($video) {
            $filePath = __DIR__ . '/../' . $video['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            db_query("DELETE FROM advertisements_media WHERE media_type = 'video'");
            $message = 'Video deleted successfully!';
        }
    }
}

// Get current data
$video = db_fetch("SELECT * FROM advertisements_media WHERE media_type = 'video' LIMIT 1");
$images = db_fetch_all("SELECT * FROM advertisements_media WHERE media_type = 'image' ORDER BY display_order ASC");
$settings = db_fetch("SELECT * FROM advertisements_settings WHERE setting_key = 'slider_enabled'");
$sliderEnabled = $settings ? ($settings['setting_value'] === '1') : true;

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.media-item {
    position: relative;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    transition: all 0.3s ease;
    cursor: grab;
}
.media-item:hover {
    border-color: #C5A059;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.media-item.dragging {
    opacity: 0.5;
    border-color: #C5A059;
    transform: scale(1.02);
}
.media-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}
.media-item-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f8f9fa;
}
.media-item-order {
    background: #C5A059;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
}
.drag-handle {
    cursor: grab;
    color: #666;
    font-size: 18px;
}
.drag-handle:active {
    cursor: grabbing;
}
.video-preview {
    background: #1a1a1a;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
}
.video-preview video {
    width: 100%;
    max-height: 300px;
}
.upload-zone {
    border: 2px dashed #C5A059;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #fdf8f0;
    transition: all 0.3s ease;
    cursor: pointer;
}
.upload-zone:hover {
    background: #f5ecd8;
    border-color: #b8954d;
}
.upload-zone i {
    font-size: 48px;
    color: #C5A059;
    margin-bottom: 15px;
}
.slider-toggle {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    margin-bottom: 20px;
}
.slider-toggle .form-check-input {
    width: 50px;
    height: 26px;
}
.slider-toggle .form-check-input:checked {
    background-color: #C5A059;
    border-color: #C5A059;
}
.drop-indicator {
    height: 4px;
    background: #C5A059;
    border-radius: 2px;
    margin: 5px 0;
    display: none;
}
.drop-indicator.active {
    display: block;
}
</style>

<section class="py-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Advertisements & Highlights</h1>
                <p class="text-muted mb-0">Manage homepage media section (video + images)</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Slider Toggle -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Display Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="slider-toggle">
                            <input type="hidden" name="action" value="toggle_slider">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="slider_enabled" id="sliderToggle" 
                                    <?= $sliderEnabled ? 'checked' : ''; ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="sliderToggle">
                                    <strong>Image Slider</strong>
                                </label>
                            </div>
                            <div class="text-muted">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    When ON: All images rotate as slider. When OFF: Only first image shows.
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Video Section -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-video me-2"></i>Video (Plays First)</h5>
                        <?php if ($video): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this video?');">
                                <input type="hidden" name="action" value="delete_video">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($video): ?>
                            <div class="video-preview">
                                <video controls muted>
                                    <source src="<?= base_url($video['file_path']); ?>" type="video/mp4">
                                </video>
                            </div>
                            <p class="text-success mb-0">
                                <i class="fas fa-check-circle me-1"></i>
                                Video uploaded: <?= htmlspecialchars($video['file_name']); ?>
                            </p>
                        <?php else: ?>
                            <form method="POST" enctype="multipart/form-data" id="videoUploadForm">
                                <input type="hidden" name="action" value="upload_video">
                                <label class="upload-zone d-block" for="videoInput">
                                    <i class="fas fa-film d-block"></i>
                                    <strong>Click to Upload Video</strong>
                                    <p class="text-muted mb-0 mt-2">MP4 format only (max 50MB)</p>
                                </label>
                                <input type="file" name="video_file" id="videoInput" accept="video/mp4" 
                                    class="d-none" onchange="document.getElementById('videoUploadForm').submit();">
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Images Section -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-images me-2"></i>Upload Images</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="imageUploadForm">
                            <input type="hidden" name="action" value="upload_images">
                            <label class="upload-zone d-block" for="imageInput">
                                <i class="fas fa-cloud-upload-alt d-block"></i>
                                <strong>Click to Upload Images</strong>
                                <p class="text-muted mb-0 mt-2">JPG, PNG, WEBP (multiple allowed)</p>
                            </label>
                            <input type="file" name="image_files[]" id="imageInput" accept="image/jpeg,image/png,image/webp" 
                                class="d-none" multiple onchange="document.getElementById('imageUploadForm').submit();">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Images Grid -->
        <?php if (!empty($images)): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-th me-2"></i>Image Gallery 
                    <span class="badge bg-secondary ms-2"><?= count($images); ?> images</span>
                </h5>
                <p class="text-muted mb-0 mt-1">
                    <i class="fas fa-hand-paper me-1"></i>Drag and drop to reorder. Order is saved automatically.
                </p>
            </div>
            <div class="card-body">
                <div class="media-grid" id="mediaGrid">
                    <?php foreach ($images as $index => $image): ?>
                    <div class="media-item" data-id="<?= $image['id']; ?>" draggable="true">
                        <img src="<?= base_url($image['file_path']); ?>" alt="<?= htmlspecialchars($image['file_name']); ?>">
                        <div class="media-item-controls">
                            <div class="media-item-order"><?= $index + 1; ?></div>
                            <i class="fas fa-grip-vertical drag-handle"></i>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this image?');">
                                <input type="hidden" name="action" value="delete_media">
                                <input type="hidden" name="media_id" value="<?= $image['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Drag and Drop Reordering
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('mediaGrid');
    if (!grid) return;
    
    let draggedItem = null;
    
    grid.querySelectorAll('.media-item').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            draggedItem = null;
            updateOrder();
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            const rect = this.getBoundingClientRect();
            const midpoint = rect.left + rect.width / 2;
            
            if (draggedItem && draggedItem !== this) {
                if (e.clientX < midpoint) {
                    this.parentNode.insertBefore(draggedItem, this);
                } else {
                    this.parentNode.insertBefore(draggedItem, this.nextSibling);
                }
            }
        });
    });
    
    function updateOrder() {
        const items = grid.querySelectorAll('.media-item');
        const orderData = [];
        
        items.forEach((item, index) => {
            const id = item.dataset.id;
            orderData.push({ id: id, order: index + 1 });
            
            // Update visual order number
            const orderBadge = item.querySelector('.media-item-order');
            if (orderBadge) orderBadge.textContent = index + 1;
        });
        
        // Save order via AJAX
        fetch('<?= base_url('admin/api/update_ad_order.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ order: orderData })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Order saved');
            } else {
                console.error('Failed to save order');
            }
        })
        .catch(error => console.error('Error:', error));
    }
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
