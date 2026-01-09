<?php
$pageTitle = 'Application Submitted Successfully - Gilaf Store';
$activePage = '';
require_once __DIR__ . '/includes/functions.php';

// Check if there's a success session
if (!isset($_SESSION['application_success'])) {
    header('Location: ' . base_url('apply-distributor.php'));
    exit;
}

$successData = $_SESSION['application_success'];
unset($_SESSION['application_success']); // Clear the session data

$applicationId = $successData['application_id'];
$applicationType = $successData['application_type'];
$ownerName = $successData['owner_name'];
$email = $successData['email'];

// Format application type for display
$typeLabels = [
    'reseller' => 'Gilaf Store Reseller',
    'distributor' => 'Distributor',
    'official_store' => 'Official Gilaf Store'
];
$typeLabel = $typeLabels[$applicationType] ?? 'Partner';

include __DIR__ . '/includes/new-header.php';
?>

<style>
.success-page {
    background: linear-gradient(135deg, rgba(26, 60, 52, 0.03) 0%, rgba(197, 160, 89, 0.03) 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    padding: 60px 0;
}

.success-container {
    max-width: 800px;
    margin: 0 auto;
}

.success-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 245, 242, 0.98) 100%);
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(26, 60, 52, 0.15);
    padding: 60px 50px;
    text-align: center;
    backdrop-filter: blur(20px);
    border: 1px solid rgba(197, 160, 89, 0.2);
    position: relative;
    overflow: hidden;
}

.success-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, var(--color-gold) 0%, var(--color-green) 100%);
}

.success-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 30px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 15px 40px rgba(16, 185, 129, 0.3);
    animation: successPulse 2s ease-in-out infinite;
}

@keyframes successPulse {
    0%, 100% { transform: scale(1); box-shadow: 0 15px 40px rgba(16, 185, 129, 0.3); }
    50% { transform: scale(1.05); box-shadow: 0 20px 50px rgba(16, 185, 129, 0.4); }
}

.success-icon i {
    font-size: 4rem;
    color: white;
}

.success-title {
    font-family: var(--font-serif);
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--color-green);
    margin: 0 0 15px 0;
}

.success-subtitle {
    font-size: 1.2rem;
    color: var(--color-text-light);
    margin: 0 0 40px 0;
    line-height: 1.6;
}

.application-details {
    background: rgba(26, 60, 52, 0.04);
    border-radius: 16px;
    padding: 30px;
    margin: 40px 0;
    border-left: 5px solid var(--color-gold);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px solid rgba(26, 60, 52, 0.1);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: var(--color-green);
    font-size: 0.95rem;
}

.detail-value {
    color: var(--color-text);
    font-size: 1rem;
    font-weight: 500;
}

.application-id {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--color-gold) 0%, rgba(197, 160, 89, 0.9) 100%);
    color: white;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
}

.next-steps {
    text-align: left;
    margin: 40px 0;
    padding: 30px;
    background: linear-gradient(135deg, rgba(197, 160, 89, 0.05) 0%, rgba(197, 160, 89, 0.02) 100%);
    border-radius: 16px;
    border: 2px dashed rgba(197, 160, 89, 0.3);
}

.next-steps h3 {
    color: var(--color-green);
    font-family: var(--font-serif);
    font-size: 1.4rem;
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.step-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.step-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(197, 160, 89, 0.15);
}

.step-number {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--color-green) 0%, rgba(26, 60, 52, 0.9) 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.step-content {
    flex: 1;
}

.step-title {
    font-weight: 600;
    color: var(--color-green);
    margin: 0 0 5px 0;
    font-size: 1rem;
}

.step-description {
    color: var(--color-text-light);
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.5;
}

.action-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 40px;
    flex-wrap: wrap;
}

.btn-primary-gold {
    background: linear-gradient(135deg, var(--color-gold) 0%, rgba(197, 160, 89, 0.9) 100%);
    color: white;
    border: none;
    padding: 16px 40px;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(197, 160, 89, 0.3);
}

.btn-primary-gold:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 28px rgba(197, 160, 89, 0.4);
    color: white;
    text-decoration: none;
}

.btn-outline-green {
    background: transparent;
    color: var(--color-green);
    border: 2px solid var(--color-green);
    padding: 16px 40px;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.btn-outline-green:hover {
    background: var(--color-green);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
    text-decoration: none;
}

.contact-info {
    margin-top: 40px;
    padding: 25px;
    background: rgba(26, 60, 52, 0.04);
    border-radius: 12px;
    font-size: 0.9rem;
    color: var(--color-text-light);
}

.contact-info strong {
    color: var(--color-green);
}

@media (max-width: 768px) {
    .success-card {
        padding: 40px 25px;
    }
    
    .success-title {
        font-size: 2rem;
    }
    
    .success-subtitle {
        font-size: 1rem;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-primary-gold, .btn-outline-green {
        width: 100%;
        justify-content: center;
    }
}
</style>

<section class="success-page">
    <div class="container success-container">
        <div class="success-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="success-title">Application Submitted Successfully!</h1>
            <p class="success-subtitle">
                Thank you for your interest in partnering with Gilaf Store.<br>
                Sit back and relax – we'll keep you updated on your application status.
            </p>
            
            <div class="application-details">
                <div class="detail-row">
                    <span class="detail-label">Application ID</span>
                    <span class="application-id">
                        <i class="fas fa-hashtag"></i>
                        <?= str_pad($applicationId, 6, '0', STR_PAD_LEFT); ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Partnership Type</span>
                    <span class="detail-value"><?= htmlspecialchars($typeLabel); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Applicant Name</span>
                    <span class="detail-value"><?= htmlspecialchars($ownerName); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email Address</span>
                    <span class="detail-value"><?= htmlspecialchars($email); ?></span>
                </div>
            </div>
            
            <div class="next-steps">
                <h3>
                    <i class="fas fa-clipboard-list"></i>
                    What Happens Next?
                </h3>
                
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <div class="step-title">Application Review</div>
                        <div class="step-description">Our team will carefully review your application and verify all submitted documents within 3-5 business days.</div>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <div class="step-title">Email Confirmation</div>
                        <div class="step-description">You'll receive a confirmation email at <strong><?= htmlspecialchars($email); ?></strong> with your application details and next steps.</div>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <div class="step-title">Status Update</div>
                        <div class="step-description">We'll notify you via email once your application is approved or if we need any additional information.</div>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <div class="step-title">Partnership Onboarding</div>
                        <div class="step-description">Upon approval, our team will guide you through the onboarding process and help you get started with Gilaf Store.</div>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="<?= base_url('index.php'); ?>" class="btn-primary-gold">
                    <i class="fas fa-home"></i>
                    <span>Back to Home</span>
                </a>
                <a href="<?= base_url('shop.php'); ?>" class="btn-outline-green">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Explore Products</span>
                </a>
            </div>
            
            <div class="contact-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Need Help?</strong> If you have any questions about your application, please contact us at 
                <strong>support@gilafstore.com</strong> or call <strong>+91 99000 12345</strong>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
