<?php
$pageTitle = 'Apply for Distributor/Reseller - Gilaf Store';
$activePage = '';
require_once __DIR__ . '/includes/functions.php';
include __DIR__ . '/includes/new-header.php';
?>

<style>
.application-form {
    background: linear-gradient(135deg, rgba(26, 60, 52, 0.03) 0%, rgba(197, 160, 89, 0.03) 100%);
    padding: 60px 0 80px;
    min-height: 100vh;
}

.application-hero {
    background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
    padding: 60px 40px;
    border-radius: 24px;
    margin-bottom: 40px;
    text-align: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(26, 60, 52, 0.2);
}

.application-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(197, 160, 89, 0.15) 0%, transparent 70%);
    animation: heroPulse 8s ease-in-out infinite;
}

@keyframes heroPulse {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-10px, -10px) scale(1.1); }
}

.application-hero-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto 24px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    color: var(--color-gold);
    backdrop-filter: blur(10px);
    border: 3px solid rgba(197, 160, 89, 0.3);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    position: relative;
    z-index: 1;
}

.application-hero h1 {
    font-family: var(--font-serif);
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    margin: 0 0 12px 0;
    position: relative;
    z-index: 1;
}

.application-hero p {
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.9);
    margin: 0;
    position: relative;
    z-index: 1;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 500;
}

.form-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 245, 242, 0.98) 100%);
    border-radius: 20px;
    box-shadow: 0 15px 50px rgba(26, 60, 52, 0.1);
    padding: 40px;
    margin-bottom: 30px;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(197, 160, 89, 0.15);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.form-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 60px rgba(26, 60, 52, 0.15);
}

.form-section-title {
    color: var(--color-green);
    font-family: var(--font-serif);
    font-size: 1.6rem;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 3px solid;
    border-image: linear-gradient(90deg, var(--color-gold), transparent) 1;
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-section-title i {
    color: var(--color-gold);
    font-size: 1.8rem;
}

.form-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--color-green);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-control, .form-select {
    border: 2px solid rgba(26, 60, 52, 0.1);
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.95rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: rgba(255, 255, 255, 0.8);
}

.form-control:focus, .form-select:focus {
    border-color: var(--color-gold);
    box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.1);
    background: white;
    outline: none;
}

.upload-area {
    border: 3px dashed rgba(197, 160, 89, 0.3);
    border-radius: 16px;
    padding: 30px;
    text-align: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    background: linear-gradient(135deg, rgba(197, 160, 89, 0.03) 0%, rgba(197, 160, 89, 0.01) 100%);
}

.upload-area:hover {
    border-color: var(--color-gold);
    background: linear-gradient(135deg, rgba(197, 160, 89, 0.08) 0%, rgba(197, 160, 89, 0.03) 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(197, 160, 89, 0.15);
}

.upload-area input[type="file"] {
    display: none;
}

.file-info {
    font-size: 0.85rem;
    color: #666;
    margin-top: 5px;
}

.location-btn {
    background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 600;
    font-size: 0.95rem;
    box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.location-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(26, 60, 52, 0.4);
}

.location-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.application-type-card {
    border: 3px solid rgba(26, 60, 52, 0.15);
    border-radius: 20px;
    padding: 35px 25px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    text-align: center;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 245, 242, 0.95) 100%);
    position: relative;
    overflow: visible;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.application-type-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--color-gold), var(--color-green));
    border-radius: 20px 20px 0 0;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.card-icon-wrapper {
    width: 90px;
    height: 90px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, var(--color-gold) 0%, rgba(197, 160, 89, 0.8) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 25px rgba(197, 160, 89, 0.3);
    transition: all 0.3s ease;
}

.card-icon-wrapper i {
    font-size: 2.5rem;
    color: white;
}

.card-logo-wrapper {
    width: 120px;
    height: 90px;
    margin: 0 auto 20px;
    background: white;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    box-shadow: 0 8px 25px rgba(197, 160, 89, 0.3);
    transition: all 0.3s ease;
    border: 3px solid rgba(197, 160, 89, 0.2);
}

.gilaf-logo {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.card-premium .card-logo-wrapper {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 245, 242, 0.98) 100%);
}

.application-type-card:hover .card-logo-wrapper {
    transform: scale(1.05);
    box-shadow: 0 12px 35px rgba(197, 160, 89, 0.4);
    border-color: var(--color-gold);
}

.application-type-card.selected .card-logo-wrapper {
    border-color: var(--color-green);
    box-shadow: 0 12px 35px rgba(26, 60, 52, 0.4);
}

.application-type-card h5 {
    color: var(--color-green);
    font-weight: 700;
    margin: 0 0 12px 0;
    font-size: 1.2rem;
    font-family: var(--font-serif);
}

.card-description {
    color: var(--color-text-light);
    font-size: 0.9rem;
    margin-bottom: 20px;
    line-height: 1.5;
    min-height: 45px;
}

.card-benefits {
    text-align: left;
    margin: 20px 0;
    padding: 20px;
    background: rgba(26, 60, 52, 0.03);
    border-radius: 12px;
    flex-grow: 1;
}

.benefit-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    font-size: 0.85rem;
    color: var(--color-text);
}

.benefit-item:last-child {
    margin-bottom: 0;
}

.benefit-item i {
    color: var(--color-green);
    font-size: 0.9rem;
}

.card-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, var(--color-gold) 0%, rgba(197, 160, 89, 0.9) 100%);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
}

.card-badge-premium {
    background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
}

.application-type-card:hover {
    border-color: var(--color-gold);
    background: linear-gradient(135deg, rgba(197, 160, 89, 0.08) 0%, rgba(197, 160, 89, 0.03) 100%);
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(197, 160, 89, 0.25);
}

.application-type-card:hover::before {
    opacity: 1;
}

.application-type-card:hover .card-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 12px 35px rgba(197, 160, 89, 0.4);
}

.application-type-card.selected {
    border-color: var(--color-green);
    background: linear-gradient(135deg, rgba(26, 60, 52, 0.08) 0%, rgba(26, 60, 52, 0.03) 100%);
    box-shadow: 0 15px 40px rgba(26, 60, 52, 0.25);
    transform: translateY(-8px);
}

.application-type-card.selected::before {
    opacity: 1;
}

.application-type-card.selected .card-icon-wrapper {
    background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
    box-shadow: 0 12px 35px rgba(26, 60, 52, 0.4);
}

.submit-section {
    text-align: center;
    padding: 40px 0 20px;
}

.btn-submit-premium {
    background: linear-gradient(135deg, var(--color-gold) 0%, rgba(197, 160, 89, 0.9) 100%);
    color: white;
    border: none;
    padding: 18px 50px;
    border-radius: 50px;
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 25px rgba(197, 160, 89, 0.4);
    display: inline-flex;
    align-items: center;
    gap: 12px;
}

.btn-submit-premium:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 35px rgba(197, 160, 89, 0.5);
    background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
}

.application-type-card input[type="radio"] {
    display: none;
}
</style>

<section class="application-form">
    <div class="container">
        <div class="application-hero">
            <div class="application-hero-icon">
                <i class="fas fa-handshake"></i>
            </div>
            <h1>Become a Gilaf Partner</h1>
            <p>Join our network of trusted distributors and resellers</p>
        </div>

        <form action="<?= base_url('submit_application.php'); ?>" method="post" enctype="multipart/form-data" id="distributorForm">
            
            <!-- Business Owner Information -->
            <div class="form-card">
                <h2 class="form-section-title"><i class="fas fa-user"></i> Business Owner Information</h2>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Business Owner Name *</label>
                        <input type="text" name="owner_name" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" name="phone" class="form-control" pattern="[0-9]{10}" placeholder="10-digit mobile number" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email ID *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Owner Address *</label>
                        <textarea name="owner_address" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
            </div>

            <!-- Business Place Address -->
            <div class="form-card">
                <h2 class="form-section-title"><i class="fas fa-building"></i> Business Place Address</h2>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="sameAsOwner" name="same_as_owner_address" value="1" onchange="toggleBusinessAddress()">
                    <label class="form-check-label" for="sameAsOwner">
                        Same as owner address
                    </label>
                </div>
                
                <div id="businessAddressFields">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Business Address *</label>
                            <textarea name="business_address" id="businessAddress" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Pincode *</label>
                            <input type="text" name="pincode" class="form-control" pattern="[0-9]{6}" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City *</label>
                            <input type="text" name="city" class="form-control" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State *</label>
                            <select name="state" class="form-select" required>
                                <option value="">Select State/UT</option>
                                <optgroup label="States">
                                    <option value="Andhra Pradesh">Andhra Pradesh</option>
                                    <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                    <option value="Assam">Assam</option>
                                    <option value="Bihar">Bihar</option>
                                    <option value="Chhattisgarh">Chhattisgarh</option>
                                    <option value="Goa">Goa</option>
                                    <option value="Gujarat">Gujarat</option>
                                    <option value="Haryana">Haryana</option>
                                    <option value="Himachal Pradesh">Himachal Pradesh</option>
                                    <option value="Jharkhand">Jharkhand</option>
                                    <option value="Karnataka">Karnataka</option>
                                    <option value="Kerala">Kerala</option>
                                    <option value="Madhya Pradesh">Madhya Pradesh</option>
                                    <option value="Maharashtra">Maharashtra</option>
                                    <option value="Manipur">Manipur</option>
                                    <option value="Meghalaya">Meghalaya</option>
                                    <option value="Mizoram">Mizoram</option>
                                    <option value="Nagaland">Nagaland</option>
                                    <option value="Odisha">Odisha</option>
                                    <option value="Punjab">Punjab</option>
                                    <option value="Rajasthan">Rajasthan</option>
                                    <option value="Sikkim">Sikkim</option>
                                    <option value="Tamil Nadu">Tamil Nadu</option>
                                    <option value="Telangana">Telangana</option>
                                    <option value="Tripura">Tripura</option>
                                    <option value="Uttar Pradesh">Uttar Pradesh</option>
                                    <option value="Uttarakhand">Uttarakhand</option>
                                    <option value="West Bengal">West Bengal</option>
                                </optgroup>
                                <optgroup label="Union Territories">
                                    <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                                    <option value="Chandigarh">Chandigarh</option>
                                    <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                                    <option value="Delhi">Delhi</option>
                                    <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                    <option value="Ladakh">Ladakh</option>
                                    <option value="Lakshadweep">Lakshadweep</option>
                                    <option value="Puducherry">Puducherry</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Identity Proof -->
            <div class="form-card">
                <h2 class="form-section-title"><i class="fas fa-id-card"></i> Identity Proof</h2>
                
                <div class="mb-3">
                    <label class="form-label">Select Identity Proof Type *</label>
                    <select name="identity_proof_type" class="form-select" required>
                        <option value="">Choose...</option>
                        <option value="aadhaar">Aadhaar Card</option>
                        <option value="pan">PAN Card</option>
                        <option value="election_card">Election Card</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Upload Identity Proof * (Max 200 KB)</label>
                    <div class="upload-area" onclick="document.getElementById('identityProof').click()">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color: var(--color-gold);"></i>
                        <p class="mb-0">Click to upload or drag and drop</p>
                        <p class="file-info">PDF, JPG, PNG (Max 200 KB)</p>
                        <input type="file" id="identityProof" name="identity_proof" accept=".pdf,.jpg,.jpeg,.png" required onchange="displayFileName(this, 'identityFileName')">
                    </div>
                    <div id="identityFileName" class="mt-2 text-success"></div>
                </div>
            </div>

            <!-- Business Licenses -->
            <div class="form-card">
                <h2 class="form-section-title"><i class="fas fa-certificate"></i> Business Licenses</h2>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Shops & Labour License</label>
                        <input type="file" name="shops_labour_license" class="form-control" accept=".pdf,.jpg,.jpeg,.png" onchange="validateFileSize(this)">
                        <small class="text-muted">Max 200 KB</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Municipality License</label>
                        <input type="file" name="municipality_license" class="form-control" accept=".pdf,.jpg,.jpeg,.png" onchange="validateFileSize(this)">
                        <small class="text-muted">Max 200 KB</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">MSME License</label>
                        <input type="file" name="msme_license" class="form-control" accept=".pdf,.jpg,.jpeg,.png" onchange="validateFileSize(this)">
                        <small class="text-muted">Max 200 KB</small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">GST License</label>
                        <input type="file" name="gst_license" class="form-control" accept=".pdf,.jpg,.jpeg,.png" onchange="validateFileSize(this)">
                        <small class="text-muted">Max 200 KB</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">GST Registration Number</label>
                    <input type="text" name="gst_registration_number" class="form-control" placeholder="e.g., 22AAAAA0000A1Z5">
                </div>
            </div>

            <!-- Location -->
            <div class="form-card">
                <h2 class="form-section-title"><i class="fas fa-map-marker-alt"></i> Store Location</h2>
                
                <div class="mb-3">
                    <button type="button" class="location-btn" id="allowLocationBtn" onclick="getLocation()">
                        <i class="fas fa-crosshairs"></i> Allow Location & Auto-Fill Details
                    </button>
                    <p class="file-info mt-2">Click to automatically capture GPS coordinates and fill address details</p>
                    <div id="locationStatus" class="mt-2"></div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Latitude (X Coordinate)</label>
                        <input type="text" name="latitude" id="latitude" class="form-control" readonly>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Longitude (Y Coordinate)</label>
                        <input type="text" name="longitude" id="longitude" class="form-control" readonly>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Location Accuracy</label>
                        <input type="text" name="location_accuracy" id="locationAccuracy" class="form-control" readonly placeholder="Accuracy radius in meters">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Area / Locality</label>
                        <input type="text" name="locality" id="locality" class="form-control" readonly>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Google Maps Location URL</label>
                    <input type="url" name="google_maps_url" id="googleMapsUrl" class="form-control" readonly>
                </div>
            </div>

            <!-- Application Type -->
            <div class="form-card">
                <h2 class="form-section-title"><i class="fas fa-briefcase"></i> Select Partnership Type</h2>
                <p style="color: var(--color-text-light); margin-bottom: 30px; font-size: 0.95rem;">Choose the partnership model that best fits your business goals</p>
                
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="application-type-card" onclick="selectApplicationType('reseller')" id="card-reseller">
                            <input type="radio" name="application_type" value="reseller" required>
                            <div class="card-icon-wrapper">
                                <i class="fas fa-store"></i>
                            </div>
                            <h5>Gilaf Store Reseller</h5>
                            <p class="card-description">Sell Gilaf products in your existing retail store</p>
                            <div class="card-benefits">
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Competitive margins</div>
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Marketing support</div>
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Product training</div>
                            </div>
                            <div class="card-badge">Popular Choice</div>
                        </label>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="application-type-card" onclick="selectApplicationType('distributor')" id="card-distributor">
                            <input type="radio" name="application_type" value="distributor" required>
                            <div class="card-icon-wrapper">
                                <i class="fas fa-truck"></i>
                            </div>
                            <h5>Distributor</h5>
                            <p class="card-description">Distribute Gilaf products to multiple retailers in your region</p>
                            <div class="card-benefits">
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Bulk pricing</div>
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Territory rights</div>
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Logistics support</div>
                            </div>
                            <div class="card-badge">High Volume</div>
                        </label>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="application-type-card card-premium" onclick="selectApplicationType('official_store')" id="card-official_store">
                            <input type="radio" name="application_type" value="official_store" required>
                            <div class="card-logo-wrapper">
                                <img src="https://i.imgur.com/YourGilafLogo.png" alt="Gilaf Logo" class="gilaf-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="card-icon-wrapper" style="display: none;">
                                    <i class="fas fa-crown"></i>
                                </div>
                            </div>
                            <h5>Official Gilaf Store</h5>
                            <p class="card-description">Open an exclusive Gilaf branded flagship store</p>
                            <div class="card-benefits">
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Exclusive branding</div>
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Premium support</div>
                                <div class="benefit-item"><i class="fas fa-check-circle"></i> Store setup help</div>
                            </div>
                            <div class="card-badge card-badge-premium">Premium</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="submit-section">
                <button type="submit" class="btn-submit-premium">
                    <i class="fas fa-paper-plane"></i>
                    <span>Submit Application</span>
                </button>
                <p style="margin-top: 20px; color: var(--color-text-light); font-size: 0.9rem;">
                    By submitting, you agree to our terms and conditions
                </p>
            </div>
        </form>
    </div>
</section>

<script>
function toggleBusinessAddress() {
    const checkbox = document.getElementById('sameAsOwner');
    const businessAddress = document.getElementById('businessAddress');
    const ownerAddress = document.querySelector('textarea[name="owner_address"]');
    
    if (checkbox.checked) {
        businessAddress.value = ownerAddress.value;
        businessAddress.readOnly = true;
    } else {
        businessAddress.value = '';
        businessAddress.readOnly = false;
    }
}

function displayFileName(input, displayId) {
    const display = document.getElementById(displayId);
    if (input.files && input.files[0]) {
        display.textContent = '✓ ' + input.files[0].name;
    }
}

function validateFileSize(input) {
    const maxSize = 200 * 1024; // 200 KB
    if (input.files && input.files[0]) {
        if (input.files[0].size > maxSize) {
            alert('File size must not exceed 200 KB');
            input.value = '';
            return false;
        }
    }
    return true;
}

function getLocation() {
    const statusDiv = document.getElementById('locationStatus');
    const btn = document.getElementById('allowLocationBtn');
    
    if (!navigator.geolocation) {
        statusDiv.innerHTML = '<div class="alert alert-danger">Geolocation is not supported by your browser</div>';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting Location...';
    statusDiv.innerHTML = '<div class="alert alert-info">Requesting location permission...</div>';
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const accuracy = position.coords.accuracy;
            
            // Fill coordinates
            document.getElementById('latitude').value = lat.toFixed(8);
            document.getElementById('longitude').value = lng.toFixed(8);
            document.getElementById('locationAccuracy').value = accuracy.toFixed(2) + ' meters';
            document.getElementById('googleMapsUrl').value = `https://www.google.com/maps?q=${lat},${lng}`;
            
            statusDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Location captured! Fetching address details...</div>';
            
            // Reverse geocoding to get address details
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.address) {
                        const addr = data.address;
                        
                        // Auto-fill city
                        const city = addr.city || addr.town || addr.village || addr.municipality || '';
                        if (city) document.querySelector('input[name="city"]').value = city;
                        
                        // Auto-fill state - try to match with dropdown options
                        const state = addr.state || '';
                        if (state) {
                            const stateSelect = document.querySelector('select[name="state"]');
                            // Try to find exact match first
                            let found = false;
                            for (let option of stateSelect.options) {
                                if (option.value.toLowerCase() === state.toLowerCase()) {
                                    stateSelect.value = option.value;
                                    found = true;
                                    break;
                                }
                            }
                            // If no exact match, try partial match
                            if (!found) {
                                for (let option of stateSelect.options) {
                                    if (option.value.toLowerCase().includes(state.toLowerCase()) || 
                                        state.toLowerCase().includes(option.value.toLowerCase())) {
                                        stateSelect.value = option.value;
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Auto-fill country
                        const country = addr.country || 'India';
                        
                        // Auto-fill pincode
                        const pincode = addr.postcode || '';
                        if (pincode) document.querySelector('input[name="pincode"]').value = pincode;
                        
                        // Auto-fill locality/area
                        const locality = addr.suburb || addr.neighbourhood || addr.road || addr.locality || '';
                        if (locality) document.getElementById('locality').value = locality;
                        
                        // Build full address
                        const addressParts = [
                            addr.road,
                            addr.suburb || addr.neighbourhood,
                            addr.city || addr.town || addr.village,
                            addr.state,
                            addr.postcode,
                            addr.country
                        ].filter(part => part);
                        
                        const fullAddress = addressParts.join(', ');
                        if (fullAddress && !document.getElementById('businessAddress').value) {
                            document.getElementById('businessAddress').value = fullAddress;
                        }
                        
                        statusDiv.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <strong>Location Details Auto-Filled!</strong><br>
                                <small>
                                    <strong>City:</strong> ${city}<br>
                                    <strong>State:</strong> ${state}<br>
                                    <strong>Country:</strong> ${country}<br>
                                    <strong>PIN Code:</strong> ${pincode}<br>
                                    <strong>Locality:</strong> ${locality}<br>
                                    <strong>Accuracy:</strong> ${accuracy.toFixed(2)} meters
                                </small>
                            </div>
                        `;
                    } else {
                        statusDiv.innerHTML = '<div class="alert alert-warning">Location captured but address details not available. Please fill manually.</div>';
                    }
                    
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Location Captured';
                })
                .catch(error => {
                    console.error('Geocoding error:', error);
                    statusDiv.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Location captured but could not fetch address details. Please fill manually.</div>';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-crosshairs"></i> Allow Location & Auto-Fill Details';
                });
        },
        function(error) {
            let errorMsg = 'Error getting location: ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    errorMsg += 'Location permission denied. Please allow location access.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    errorMsg += 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    errorMsg += 'Location request timed out.';
                    break;
                default:
                    errorMsg += 'Unknown error occurred.';
            }
            statusDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle"></i> ${errorMsg}</div>`;
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-crosshairs"></i> Allow Location & Auto-Fill Details';
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

function selectApplicationType(type) {
    document.querySelectorAll('.application-type-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    document.querySelector(`input[value="${type}"]`).checked = true;
}

// Validate form before submit
document.getElementById('distributorForm').addEventListener('submit', function(e) {
    const identityProof = document.getElementById('identityProof');
    if (!identityProof.files || !identityProof.files[0]) {
        e.preventDefault();
        alert('Please upload identity proof');
        return false;
    }
    
    if (!validateFileSize(identityProof)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
