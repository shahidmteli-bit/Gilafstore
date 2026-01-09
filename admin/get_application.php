<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user']) || empty($_SESSION['user']['is_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit;
}

try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT * FROM distributor_applications WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$app) {
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit;
    }
    
    $notes = json_decode($app['admin_notes'] ?? '{}', true);
    
    $typeLabels = [
        'reseller' => 'Gilaf Store Reseller',
        'distributor' => 'Distributor',
        'official_store' => 'Official Gilaf Store'
    ];
    
    $identityLabels = [
        'aadhaar' => 'Aadhaar Card',
        'pan' => 'PAN Card',
        'election_card' => 'Election Card'
    ];
    
    $statusColors = [
        'pending' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger'
    ];
    
    $html = '
    <div class="row">
        <div class="col-md-8">
            <h4 class="mb-4">Application #' . str_pad($app['id'], 4, '0', STR_PAD_LEFT) . ' - ' . ($typeLabels[$app['application_type']] ?? 'Application') . '</h4>
            
            <!-- Applicant Info -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-user"></i> Applicant Information</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Owner Name:</strong><br>' . htmlspecialchars($app['owner_name'] ?? 'N/A') . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Email:</strong><br>' . htmlspecialchars($app['email'] ?? 'N/A') . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Phone:</strong><br>' . htmlspecialchars($app['phone'] ?? 'N/A') . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Application Type:</strong><br>
                            <span class="badge bg-primary">' . ucfirst(str_replace('_', ' ', $app['application_type'] ?? 'N/A')) . '</span>
                        </div>
                        <div class="col-md-12 mb-3">
                            <strong>Owner Address:</strong><br>' . nl2br(htmlspecialchars($app['owner_address'] ?? 'N/A')) . '
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Business Address -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-building"></i> Business Address</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <strong>Business Address:</strong><br>' . nl2br(htmlspecialchars($app['business_address'] ?? 'Not provided')) . '
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>City:</strong><br>' . htmlspecialchars($notes['city'] ?? 'N/A') . '
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>State:</strong><br>' . htmlspecialchars($notes['state'] ?? 'N/A') . '
                        </div>
                        <div class="col-md-4 mb-3">
                            <strong>Pincode:</strong><br>' . htmlspecialchars($notes['pincode'] ?? 'N/A') . '
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Identity & Licenses -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-id-card"></i> Identity & Business Licenses</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <strong>Identity Proof Type:</strong><br>
                            <span class="badge bg-info">' . ($identityLabels[$app['identity_proof_type']] ?? 'N/A') . '</span>
                        </div>
                        <div class="col-md-12 mb-3">
                            <strong>GST Registration Number:</strong><br>' . htmlspecialchars($app['gst_registration_number'] ?? 'Not provided') . '
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Location Details -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-map-marker-alt"></i> Location Details</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>Latitude:</strong><br>' . htmlspecialchars($app['latitude'] ?? 'Not provided') . '
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>Longitude:</strong><br>' . htmlspecialchars($app['longitude'] ?? 'Not provided') . '
                        </div>
                        <div class="col-md-12 mb-3">
                            <strong>Google Maps URL:</strong><br>';
    
    if (!empty($app['google_maps_url'])) {
        $html .= '<a href="' . htmlspecialchars($app['google_maps_url']) . '" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-map-marked-alt"></i> View on Google Maps
                  </a>';
    } else {
        $html .= '<span class="text-muted">Not provided</span>';
    }
    
    $html .= '
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Status -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong>Application Status</strong>
                </div>
                <div class="card-body text-center">
                    <h3><span class="badge bg-' . $statusColors[$app['status']] . '">' . ucfirst($app['status']) . '</span></h3>
                    <small class="text-muted">Applied on: ' . date('M d, Y', strtotime($app['created_at'])) . '</small>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong><i class="fas fa-paperclip"></i> Uploaded Documents</strong>
                </div>
                <div class="card-body">';
    
    $hasDocuments = false;
    
    if (!empty($app['identity_proof_file'])) {
        $hasDocuments = true;
        $html .= '<div class="mb-2">
                    <strong>Identity Proof:</strong><br>
                    <a href="' . base_url('uploads/distributor_applications/' . $app['identity_proof_file']) . '" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                        <i class="fas fa-download"></i> View/Download
                    </a>
                  </div>';
    }
    
    if (!empty($app['shops_labour_license'])) {
        $hasDocuments = true;
        $html .= '<div class="mb-2">
                    <strong>Shops & Labour License:</strong><br>
                    <a href="' . base_url('uploads/distributor_applications/' . $app['shops_labour_license']) . '" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                        <i class="fas fa-download"></i> View/Download
                    </a>
                  </div>';
    }
    
    if (!empty($app['municipality_license'])) {
        $hasDocuments = true;
        $html .= '<div class="mb-2">
                    <strong>Municipality License:</strong><br>
                    <a href="' . base_url('uploads/distributor_applications/' . $app['municipality_license']) . '" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                        <i class="fas fa-download"></i> View/Download
                    </a>
                  </div>';
    }
    
    if (!empty($app['msme_license'])) {
        $hasDocuments = true;
        $html .= '<div class="mb-2">
                    <strong>MSME License:</strong><br>
                    <a href="' . base_url('uploads/distributor_applications/' . $app['msme_license']) . '" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                        <i class="fas fa-download"></i> View/Download
                    </a>
                  </div>';
    }
    
    if (!empty($app['gst_license'])) {
        $hasDocuments = true;
        $html .= '<div class="mb-2">
                    <strong>GST License:</strong><br>
                    <a href="' . base_url('uploads/distributor_applications/' . $app['gst_license']) . '" target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                        <i class="fas fa-download"></i> View/Download
                    </a>
                  </div>';
    }
    
    if (!$hasDocuments) {
        $html .= '<p class="text-muted">No documents uploaded</p>';
    }
    
    $html .= '
                </div>
            </div>
            
            <!-- Actions -->';
    
    if ($app['status'] === 'pending') {
        $html .= '
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong>Actions</strong>
                </div>
                <div class="card-body">
                    <button class="btn btn-success w-100 mb-2" onclick="approveApplication(' . $app['id'] . ')">
                        <i class="fas fa-check"></i> Approve Application
                    </button>
                    <button class="btn btn-danger w-100 mb-2" onclick="rejectApplication(' . $app['id'] . ')">
                        <i class="fas fa-times"></i> Reject Application
                    </button>
                    <button class="btn btn-outline-danger w-100" onclick="deleteApplication(' . $app['id'] . ')">
                        <i class="fas fa-trash"></i> Delete Application
                    </button>
                </div>
            </div>';
    } elseif ($app['status'] === 'approved') {
        $html .= '
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> This application has been approved.
                ' . (!empty($app['updated_at']) ? '<br><small>Last updated: ' . date('M d, Y', strtotime($app['updated_at'])) . '</small>' : '') . '
            </div>
            <div class="card">
                <div class="card-header bg-light">
                    <strong>Actions</strong>
                </div>
                <div class="card-body">
                    <button class="btn btn-outline-danger w-100" onclick="deleteApplication(' . $app['id'] . ')">
                        <i class="fas fa-trash"></i> Delete Distributor
                    </button>
                </div>
            </div>';
    } else {
        $html .= '
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> This application has been rejected.
                ' . (!empty($app['updated_at']) ? '<br><small>Last updated: ' . date('M d, Y', strtotime($app['updated_at'])) . '</small>' : '') . '
            </div>
            <div class="card">
                <div class="card-header bg-light">
                    <strong>Actions</strong>
                </div>
                <div class="card-body">
                    <button class="btn btn-outline-danger w-100" onclick="deleteApplication(' . $app['id'] . ')">
                        <i class="fas fa-trash"></i> Delete Application
                    </button>
                </div>
            </div>';
    }
    
    $html .= '
        </div>
    </div>';
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
