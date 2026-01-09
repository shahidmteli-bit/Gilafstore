<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Share Your Ideas | Gilaf Store';
$activePage = 'suggestions';

include __DIR__ . '/includes/new-header.php';
?>

<!-- Suggestion Page Content -->
<section class="suggestion-page-section">
    <div class="suggestion-page-container">
        <!-- Header -->
        <div class="suggestion-page-header">
            <div class="suggestion-icon-wrapper">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div>
                <h1>Share Your Ideas</h1>
                <p>Help us improve - Your feedback matters!</p>
            </div>
        </div>
        
        <!-- Form Container -->
        <div class="suggestion-page-content">
            <form id="suggestionForm">
                <!-- Category -->
                <div class="suggestion-form-group">
                    <label for="suggestionCategory" class="suggestion-label">
                        <i class="fas fa-tags"></i>
                        <span>Category</span>
                        <span class="required">*</span>
                    </label>
                    <select class="suggestion-input" id="suggestionCategory" name="category" required>
                        <option value="">Select a category...</option>
                        <option value="UI/UX">🎨 UI/UX Design</option>
                        <option value="Performance">⚡ Performance & Speed</option>
                        <option value="Features">✨ New Features</option>
                        <option value="Payments">💳 Payments & Checkout</option>
                        <option value="Security">🔒 Security & Privacy</option>
                        <option value="Content">📝 Content & Information</option>
                        <option value="Other">🔧 Other</option>
                    </select>
                </div>
                
                <!-- Custom Category (shown only when "Other" is selected) -->
                <div class="suggestion-form-group" id="customCategoryGroup" style="display: none;">
                    <label for="customCategory" class="suggestion-label">
                        <i class="fas fa-pen"></i>
                        <span>Please Specify Category</span>
                        <span class="required">*</span>
                    </label>
                    <input type="text" class="suggestion-input" id="customCategory" name="custom_category" 
                           placeholder="Enter your custom category" maxlength="100">
                    <small class="suggestion-hint">Please specify what category your suggestion belongs to</small>
                </div>
                
                <!-- Auto-Generated Subject -->
                <div class="suggestion-form-group">
                    <label for="suggestionSubject" class="suggestion-label">
                        <i class="fas fa-heading"></i>
                        <span>Subject Line</span>
                        <span class="badge-auto">Auto-Generated</span>
                    </label>
                    <input type="text" class="suggestion-input subject-preview" id="suggestionSubject" name="subject" 
                           placeholder="Subject will be generated automatically..." readonly>
                    <small class="suggestion-hint">
                        <i class="fas fa-magic"></i> 
                        This subject line is automatically generated from your description for better clarity
                    </small>
                </div>
                
                <!-- Description -->
                <div class="suggestion-form-group">
                    <label for="suggestionDescription" class="suggestion-label">
                        <i class="fas fa-align-left"></i>
                        <span>Detailed Suggestion</span>
                        <span class="required">*</span>
                    </label>
                    <textarea class="suggestion-textarea" id="suggestionDescription" name="description" rows="6" 
                              placeholder="Please describe your suggestion in detail. What problem does it solve? How would it improve the experience?" 
                              required minlength="50" maxlength="2000"></textarea>
                    <div class="suggestion-char-counter">
                        <small class="suggestion-hint">Minimum 50 characters</small>
                        <small class="suggestion-hint"><span id="charCount">0</span> / 2000</small>
                    </div>
                </div>
                
                <?php if (empty($_SESSION['user'])): ?>
                <!-- Guest User Fields -->
                <div class="suggestion-info-box">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Guest Submission:</strong>
                        <span>Please provide your contact details</span>
                    </div>
                </div>
                
                <div class="suggestion-row">
                    <div class="suggestion-col">
                        <label for="suggestionName" class="suggestion-label">
                            <i class="fas fa-user"></i>
                            <span>Your Name</span>
                            <span class="required">*</span>
                        </label>
                        <input type="text" class="suggestion-input" id="suggestionName" name="user_name" 
                               placeholder="Enter your name" required>
                    </div>
                    <div class="suggestion-col">
                        <label for="suggestionEmail" class="suggestion-label">
                            <i class="fas fa-envelope"></i>
                            <span>Your Email</span>
                            <span class="required">*</span>
                        </label>
                        <input type="email" class="suggestion-input" id="suggestionEmail" name="user_email" 
                               placeholder="your.email@example.com" required>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Hidden Source Field -->
                <input type="hidden" name="source" id="suggestionSource" value="website">
                
                <!-- Benefits Info -->
                <div class="suggestion-rewards-box">
                    <i class="fas fa-gift"></i>
                    <div>
                        <strong>Rewards for Best Suggestions!</strong>
                        <p>
                            Outstanding ideas may receive special rewards including coupons, cashback, or exclusive benefits. 
                            We value your contribution to our continuous improvement!
                        </p>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="suggestion-submit-btn" id="submitSuggestionBtn">
                    <i class="fas fa-paper-plane"></i>
                    <span>Submit Suggestion</span>
                </button>
            </form>
            
            <!-- Success Message (Hidden by default) -->
            <div id="suggestionSuccess" class="suggestion-success d-none">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4>Thank You!</h4>
                <p>Your suggestion has been submitted successfully.</p>
                <div class="success-tracking">
                    <p><strong>Tracking ID:</strong> <span id="trackingId"></span></p>
                    <small>We'll review your suggestion and get back to you soon.</small>
                </div>
                <a href="<?= base_url('index.php'); ?>" class="suggestion-close-btn">
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Suggestion Page JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('suggestionForm');
    const submitBtn = document.getElementById('submitSuggestionBtn');
    const descriptionField = document.getElementById('suggestionDescription');
    const charCount = document.getElementById('charCount');
    const successDiv = document.getElementById('suggestionSuccess');
    const categorySelect = document.getElementById('suggestionCategory');
    const customCategoryGroup = document.getElementById('customCategoryGroup');
    const customCategoryInput = document.getElementById('customCategory');
    
    // Show/hide custom category field based on selection
    if (categorySelect && customCategoryGroup && customCategoryInput) {
        categorySelect.addEventListener('change', function() {
            if (this.value === 'Other') {
                customCategoryGroup.style.display = 'block';
                customCategoryInput.required = true;
            } else {
                customCategoryGroup.style.display = 'none';
                customCategoryInput.required = false;
                customCategoryInput.value = '';
            }
        });
    }
    
    // Character counter and subject generation
    if (descriptionField && charCount) {
        let generationTimeout;
        
        descriptionField.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            // Generate subject line after user stops typing (debounce)
            clearTimeout(generationTimeout);
            
            if (this.value.length >= 50) {
                generationTimeout = setTimeout(() => {
                    generateSubject();
                }, 800); // Wait 800ms after user stops typing
            }
        });
    }
    
    // Also regenerate when category changes
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            if (descriptionField.value.length >= 50) {
                generateSubject();
            }
        });
    }
    
    // Subject generation function
    async function generateSubject() {
        const description = descriptionField.value;
        const category = categorySelect.value;
        const subjectField = document.getElementById('suggestionSubject');
        
        if (!description || description.length < 50) {
            subjectField.value = '';
            return;
        }
        
        // Show generating indicator
        subjectField.value = 'Generating subject...';
        subjectField.style.fontStyle = 'italic';
        subjectField.style.color = '#6c757d';
        
        try {
            const response = await fetch('<?= base_url('generate_subject.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    description: description,
                    category: category
                })
            });
            
            const result = await response.json();
            
            if (result.success && result.subject) {
                subjectField.value = result.subject;
                subjectField.style.fontStyle = 'normal';
                subjectField.style.color = '#1A3C34';
                subjectField.style.fontWeight = '600';
            } else {
                subjectField.value = 'Unable to generate subject';
            }
        } catch (error) {
            console.error('Subject generation error:', error);
            subjectField.value = 'Error generating subject';
        }
    }
    
    // Form submission
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Submitting...</span>';
            
            try {
                const formData = new FormData(form);
                
                console.log('Submitting form to:', '<?= base_url('submit_suggestion.php'); ?>');
                
                const response = await fetch('<?= base_url('submit_suggestion.php'); ?>', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('Server returned non-JSON response. Check console for details.');
                }
                
                const result = await response.json();
                console.log('Result:', result);
                
                if (result.success) {
                    // Hide form, show success message
                    form.style.display = 'none';
                    successDiv.classList.remove('d-none');
                    document.getElementById('trackingId').textContent = result.submission_id;
                    
                    // Scroll to top
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    
                } else {
                    alert(result.message || 'Failed to submit suggestion. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span>Submit Suggestion</span>';
                }
                
            } catch (error) {
                console.error('Submission Error:', error);
                console.error('Error details:', error.message);
                alert('An error occurred: ' + error.message + '\n\nPlease check the browser console (F12) for more details.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i><span>Submit Suggestion</span>';
            }
        });
    }
});
</script>

<style>
/* ===== Suggestion Page Styles ===== */

.suggestion-page-section {
    min-height: calc(100vh - 200px);
    padding: 60px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.suggestion-page-container {
    max-width: 800px;
    margin: 0 auto;
}

/* Page Header */
.suggestion-page-header {
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 30px;
    display: flex;
    align-items: center;
    gap: 24px;
    box-shadow: 0 10px 40px rgba(26, 60, 52, 0.3);
}

.suggestion-page-header .suggestion-icon-wrapper {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.suggestion-page-header .suggestion-icon-wrapper i {
    font-size: 32px;
    color: #FFD700;
}

.suggestion-page-header h1 {
    color: white;
    font-size: 32px;
    font-weight: 700;
    margin: 0 0 8px 0;
    letter-spacing: -0.5px;
}

.suggestion-page-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 16px;
    margin: 0;
}

/* Content Container */
.suggestion-page-content {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
}

/* Form Groups */
.suggestion-form-group {
    margin-bottom: 28px;
}

/* Labels */
.suggestion-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 15px;
    color: #2c3e50;
    margin-bottom: 10px;
}

.suggestion-label i {
    color: #1A3C34;
    font-size: 16px;
}

.suggestion-label .required {
    color: #dc3545;
    margin-left: auto;
}

.suggestion-label .badge-auto {
    margin-left: auto;
    padding: 4px 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 11px;
    font-weight: 600;
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.suggestion-label .badge-auto::before {
    content: '✨';
    font-size: 10px;
}

/* Inputs */
.suggestion-input,
.suggestion-textarea {
    width: 100%;
    padding: 16px 18px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #fafafa;
    font-family: inherit;
}

.suggestion-input:focus,
.suggestion-textarea:focus {
    outline: none;
    border-color: #1A3C34;
    background: white;
    box-shadow: 0 0 0 4px rgba(26, 60, 52, 0.1);
}

/* Subject preview field */
.suggestion-input.subject-preview {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px dashed #667eea;
    color: #1A3C34;
    font-weight: 600;
    cursor: default;
}

.suggestion-input.subject-preview:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.suggestion-textarea {
    resize: vertical;
    min-height: 150px;
    line-height: 1.6;
}

.suggestion-hint {
    display: block;
    margin-top: 8px;
    font-size: 13px;
    color: #6c757d;
}

.suggestion-char-counter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
}

/* Info Box */
.suggestion-info-box {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px;
    background: #e7f3ff;
    border-left: 4px solid #0dcaf0;
    border-radius: 12px;
    margin-bottom: 24px;
}

.suggestion-info-box i {
    color: #0dcaf0;
    font-size: 22px;
    margin-top: 2px;
}

.suggestion-info-box strong {
    color: #0a5a7a;
    display: block;
    margin-bottom: 4px;
    font-size: 15px;
}

.suggestion-info-box span {
    color: #0a5a7a;
    font-size: 14px;
}

/* Row & Columns */
.suggestion-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
}

.suggestion-col {
    display: flex;
    flex-direction: column;
}

/* Rewards Box */
.suggestion-rewards-box {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #22c55e;
    border-radius: 12px;
    margin-bottom: 32px;
}

.suggestion-rewards-box i {
    color: #16a34a;
    font-size: 26px;
    margin-top: 2px;
}

.suggestion-rewards-box strong {
    color: #166534;
    display: block;
    margin-bottom: 8px;
    font-size: 16px;
}

.suggestion-rewards-box p {
    color: #166534;
    font-size: 14px;
    line-height: 1.6;
    margin: 0;
}

/* Submit Button */
.suggestion-submit-btn {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 17px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(26, 60, 52, 0.3);
}

.suggestion-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(26, 60, 52, 0.4);
}

.suggestion-submit-btn:active {
    transform: translateY(0);
}

.suggestion-submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Success Message */
.suggestion-success {
    text-align: center;
    padding: 60px 20px;
}

.success-icon {
    margin-bottom: 24px;
}

.success-icon i {
    font-size: 80px;
    color: #22c55e;
    animation: scaleIn 0.5s ease-out;
}

.suggestion-success h4 {
    color: #16a34a;
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 16px;
}

.suggestion-success > p {
    color: #6c757d;
    font-size: 17px;
    margin-bottom: 28px;
}

.success-tracking {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 28px;
}

.success-tracking p {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #2c3e50;
}

.success-tracking strong {
    color: #1A3C34;
}

.success-tracking #trackingId {
    color: #1A3C34;
    font-weight: 600;
    font-family: 'Courier New', monospace;
    font-size: 18px;
}

.success-tracking small {
    color: #6c757d;
    font-size: 14px;
}

.suggestion-close-btn {
    display: inline-block;
    padding: 14px 40px;
    background: white;
    color: #1A3C34;
    border: 2px solid #1A3C34;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.suggestion-close-btn:hover {
    background: #1A3C34;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(26, 60, 52, 0.3);
}

/* Hide/Show Utility Classes */
.d-none {
    display: none !important;
}

/* Animations */
@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.5);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* ===== RESPONSIVE DESIGN ===== */

/* Tablet */
@media (max-width: 1024px) {
    .suggestion-page-section {
        padding: 40px 20px;
    }
    
    .suggestion-page-header {
        padding: 32px;
    }
    
    .suggestion-page-header h1 {
        font-size: 28px;
    }
    
    .suggestion-page-content {
        padding: 32px;
    }
}

/* Mobile */
@media (max-width: 767px) {
    .suggestion-page-section {
        padding: 30px 15px;
    }
    
    .suggestion-page-header {
        padding: 24px;
        gap: 16px;
    }
    
    .suggestion-page-header .suggestion-icon-wrapper {
        width: 48px;
        height: 48px;
    }
    
    .suggestion-page-header .suggestion-icon-wrapper i {
        font-size: 24px;
    }
    
    .suggestion-page-header h1 {
        font-size: 22px;
    }
    
    .suggestion-page-header p {
        font-size: 14px;
    }
    
    .suggestion-page-content {
        padding: 24px;
    }
    
    .suggestion-form-group {
        margin-bottom: 24px;
    }
    
    .suggestion-label {
        font-size: 14px;
    }
    
    .suggestion-input,
    .suggestion-textarea {
        padding: 14px 16px;
        font-size: 14px;
    }
    
    .suggestion-row {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .suggestion-info-box,
    .suggestion-rewards-box {
        padding: 16px;
    }
    
    .suggestion-rewards-box strong {
        font-size: 15px;
    }
    
    .suggestion-rewards-box p {
        font-size: 13px;
    }
    
    .suggestion-submit-btn {
        padding: 16px;
        font-size: 16px;
    }
    
    .suggestion-success {
        padding: 40px 16px;
    }
    
    .success-icon i {
        font-size: 64px;
    }
    
    .suggestion-success h4 {
        font-size: 26px;
    }
}

/* Small Mobile */
@media (max-width: 480px) {
    .suggestion-page-header h1 {
        font-size: 20px;
    }
    
    .suggestion-page-content {
        padding: 20px;
    }
    
    .suggestion-input,
    .suggestion-textarea {
        padding: 12px 14px;
    }
}
</style>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
