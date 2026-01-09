<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('apply-distributor.php', 'Invalid request method', 'error');
    exit;
}

try {
    $db = get_db_connection();
    
    // Validate required fields
    $requiredFields = ['owner_name', 'phone', 'email', 'owner_address', 'business_address', 'identity_proof_type', 'application_type', 'pincode', 'city', 'state'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Please fill in all required fields: $field");
        }
    }
    
    // Validate identity proof file
    if (!isset($_FILES['identity_proof']) || $_FILES['identity_proof']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Please upload identity proof document');
    }
    
    // Create upload directory
    $uploadDir = __DIR__ . '/uploads/distributor_applications/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
        chmod($uploadDir, 0777);
    }
    
    // Function to handle file upload
    function uploadFile($file, $uploadDir, $prefix = '') {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        // Validate file size (200 KB max)
        if ($file['size'] > 200 * 1024) {
            throw new Exception('File size must not exceed 200 KB: ' . $file['name']);
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Invalid file type. Only PDF, JPG, PNG allowed.');
        }
        
        $filename = $prefix . '_' . uniqid() . '.' . $extension;
        $destination = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new Exception('Failed to upload file: ' . $file['name']);
        }
        
        return $filename;
    }
    
    // Upload identity proof (required)
    $identityProofFile = uploadFile($_FILES['identity_proof'], $uploadDir, 'identity');
    
    // Upload optional business licenses
    $shopsLabourLicense = isset($_FILES['shops_labour_license']) ? uploadFile($_FILES['shops_labour_license'], $uploadDir, 'shops_labour') : null;
    $municipalityLicense = isset($_FILES['municipality_license']) ? uploadFile($_FILES['municipality_license'], $uploadDir, 'municipality') : null;
    $msmeLicense = isset($_FILES['msme_license']) ? uploadFile($_FILES['msme_license'], $uploadDir, 'msme') : null;
    $gstLicense = isset($_FILES['gst_license']) ? uploadFile($_FILES['gst_license'], $uploadDir, 'gst') : null;
    
    // Prepare data
    $ownerName = trim($_POST['owner_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $ownerAddress = trim($_POST['owner_address']);
    $businessAddress = trim($_POST['business_address']);
    $sameAsOwner = isset($_POST['same_as_owner_address']) ? 1 : 0;
    $identityProofType = $_POST['identity_proof_type'];
    $applicationType = $_POST['application_type'];
    $gstRegNumber = trim($_POST['gst_registration_number'] ?? '');
    $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
    $googleMapsUrl = trim($_POST['google_maps_url'] ?? '');
    $pincode = trim($_POST['pincode']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    
    // Insert application
    $stmt = $db->prepare("
        INSERT INTO distributor_applications 
        (application_type, owner_name, owner_address, phone, email, business_address, same_as_owner_address,
         identity_proof_type, identity_proof_file, shops_labour_license, municipality_license, 
         msme_license, gst_license, gst_registration_number, latitude, longitude, google_maps_url, status)
        VALUES 
        (:app_type, :owner_name, :owner_addr, :phone, :email, :business_addr, :same_addr,
         :id_type, :id_file, :shops_license, :muni_license, :msme_license, :gst_license, 
         :gst_reg, :lat, :lng, :maps_url, 'pending')
    ");
    
    $stmt->execute([
        ':app_type' => $applicationType,
        ':owner_name' => $ownerName,
        ':owner_addr' => $ownerAddress,
        ':phone' => $phone,
        ':email' => $email,
        ':business_addr' => $businessAddress,
        ':same_addr' => $sameAsOwner,
        ':id_type' => $identityProofType,
        ':id_file' => $identityProofFile,
        ':shops_license' => $shopsLabourLicense,
        ':muni_license' => $municipalityLicense,
        ':msme_license' => $msmeLicense,
        ':gst_license' => $gstLicense,
        ':gst_reg' => $gstRegNumber,
        ':lat' => $latitude,
        ':lng' => $longitude,
        ':maps_url' => $googleMapsUrl
    ]);
    
    $applicationId = $db->lastInsertId();
    
    // Store pincode, city, state in session for later use when approved
    $stmt = $db->prepare("UPDATE distributor_applications SET admin_notes = :notes WHERE id = :id");
    $stmt->execute([
        ':notes' => json_encode(['pincode' => $pincode, 'city' => $city, 'state' => $state]),
        ':id' => $applicationId
    ]);
    
    // Send confirmation email (basic implementation)
    $subject = "Application Received - Gilaf Store";
    $message = "Dear $ownerName,\n\nYour application for " . ucfirst(str_replace('_', ' ', $applicationType)) . " has been received successfully.\n\nApplication ID: #$applicationId\n\nOur team will review your application and get back to you within 3-5 business days.\n\nThank you for your interest in partnering with Gilaf Store.\n\nBest regards,\nGilaf Store Team";
    
    // Store notification in database
    $stmt = $db->prepare("INSERT INTO application_notifications (application_id, email, subject, message) VALUES (:app_id, :email, :subject, :message)");
    $stmt->execute([
        ':app_id' => $applicationId,
        ':email' => $email,
        ':subject' => $subject,
        ':message' => $message
    ]);
    
    // Try to send email (if mail is configured)
    @mail($email, $subject, $message, "From: noreply@gilafstore.com");
    
    // Redirect to professional success page
    $_SESSION['application_success'] = [
        'application_id' => $applicationId,
        'application_type' => $applicationType,
        'owner_name' => $ownerName,
        'email' => $email
    ];
    header('Location: ' . base_url('application-success.php'));
    exit;
    
} catch (Exception $e) {
    redirect_with_message('apply-distributor.php', 'Error: ' . $e->getMessage(), 'error');
}
