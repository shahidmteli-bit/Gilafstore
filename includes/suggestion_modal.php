<!-- Idea & Suggestion Center - Submission Modal -->
<div class="modal fade" id="suggestionModal" tabindex="-1" aria-labelledby="suggestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content suggestion-modal-content">
            <!-- Modal Header -->
            <div class="modal-header suggestion-modal-header">
                <div class="suggestion-header-content">
                    <div class="suggestion-icon-wrapper">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" id="suggestionModalLabel">Share Your Ideas</h5>
                        <p class="suggestion-subtitle">Help us improve - Your feedback matters!</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body suggestion-modal-body">
                <form id="suggestionForm">
                    <!-- Subject -->
                    <div class="suggestion-form-group">
                        <label for="suggestionSubject" class="suggestion-label">
                            <span>Subject / Title</span>
                            <span class="required">*</span>
                        </label>
                        <input type="text" class="suggestion-input" id="suggestionSubject" name="subject" 
                               placeholder="Brief title for your suggestion" required minlength="5" maxlength="255">
                        <small class="suggestion-hint">Minimum 5 characters</small>
                    </div>
                    
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
                    
                    <!-- Description -->
                    <div class="suggestion-form-group">
                        <label for="suggestionDescription" class="suggestion-label">
                            <i class="fas fa-align-left"></i>
                            <span>Detailed Suggestion</span>
                            <span class="required">*</span>
                        </label>
                        <textarea class="suggestion-textarea" id="suggestionDescription" name="description" rows="5" 
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
                    <button type="button" class="suggestion-close-btn" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Suggestion Modal JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('suggestionForm');
    const submitBtn = document.getElementById('submitSuggestionBtn');
    const descriptionField = document.getElementById('suggestionDescription');
    const charCount = document.getElementById('charCount');
    const successDiv = document.getElementById('suggestionSuccess');
    const formDiv = form.parentElement;
    
    // Character counter
    if (descriptionField && charCount) {
        descriptionField.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }
    
    // Form submission
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            try {
                const formData = new FormData(form);
                
                const response = await fetch('<?= base_url('submit_suggestion.php'); ?>', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Hide form, show success message
                    formDiv.classList.add('d-none');
                    successDiv.classList.remove('d-none');
                    document.getElementById('trackingId').textContent = result.submission_id;
                    
                    // Reset form for next use
                    form.reset();
                    
                    // Auto-close after 5 seconds
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('suggestionModal'));
                        if (modal) modal.hide();
                        
                        // Reset view after modal closes
                        setTimeout(() => {
                            formDiv.classList.remove('d-none');
                            successDiv.classList.add('d-none');
                        }, 500);
                    }, 5000);
                    
                } else {
                    alert(result.message || 'Failed to submit suggestion. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Suggestion';
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Suggestion';
            }
        });
    }
    
    // Reset form when modal is closed
    const suggestionModal = document.getElementById('suggestionModal');
    if (suggestionModal) {
        suggestionModal.addEventListener('hidden.bs.modal', function() {
            setTimeout(() => {
                form.reset();
                formDiv.classList.remove('d-none');
                successDiv.classList.add('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Suggestion';
                if (charCount) charCount.textContent = '0';
            }, 300);
        });
    }
});

// Global function to open suggestion modal with source tracking
function openSuggestionModal(source = 'website') {
    const sourceField = document.getElementById('suggestionSource');
    if (sourceField) {
        sourceField.value = source;
    }
    const modal = new bootstrap.Modal(document.getElementById('suggestionModal'));
    modal.show();
}
</script>

<style>
/* ===== Suggestion Modal Professional Styles ===== */

/* Modal Content */
.suggestion-modal-content {
    border-radius: 20px;
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideInUp 0.3s ease-out;
    overflow: hidden;
}

/* Modal Header */
.suggestion-modal-header {
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    border: none;
    padding: 24px 30px;
    position: relative;
}

.suggestion-header-content {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}

.suggestion-icon-wrapper {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.suggestion-icon-wrapper i {
    font-size: 24px;
    color: #FFD700;
}

.suggestion-modal-header .modal-title {
    color: white;
    font-size: 22px;
    font-weight: 700;
    margin: 0;
    letter-spacing: -0.5px;
}

.suggestion-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 14px;
    margin: 4px 0 0 0;
}

/* Modal Body */
.suggestion-modal-body {
    padding: 32px;
    background: #fafafa;
}

/* Form Groups */
.suggestion-form-group {
    margin-bottom: 24px;
}

/* Labels */
.suggestion-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 8px;
}

.suggestion-label i {
    color: #1A3C34;
    font-size: 16px;
}

.suggestion-label .required {
    color: #dc3545;
    margin-left: auto;
}

/* Inputs */
.suggestion-input,
.suggestion-textarea {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: white;
    font-family: inherit;
}

.suggestion-input:focus,
.suggestion-textarea:focus {
    outline: none;
    border-color: #1A3C34;
    box-shadow: 0 0 0 4px rgba(26, 60, 52, 0.1);
}

.suggestion-textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.suggestion-hint {
    display: block;
    margin-top: 6px;
    font-size: 13px;
    color: #6c757d;
}

.suggestion-char-counter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 6px;
}

/* Info Box */
.suggestion-info-box {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: #e7f3ff;
    border-left: 4px solid #0dcaf0;
    border-radius: 10px;
    margin-bottom: 20px;
}

.suggestion-info-box i {
    color: #0dcaf0;
    font-size: 20px;
    margin-top: 2px;
}

.suggestion-info-box strong {
    color: #0a5a7a;
    display: block;
    margin-bottom: 2px;
}

.suggestion-info-box span {
    color: #0a5a7a;
    font-size: 14px;
}

/* Row & Columns */
.suggestion-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}

.suggestion-col {
    display: flex;
    flex-direction: column;
}

/* Rewards Box */
.suggestion-rewards-box {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 18px;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-left: 4px solid #22c55e;
    border-radius: 12px;
    margin-bottom: 28px;
}

.suggestion-rewards-box i {
    color: #16a34a;
    font-size: 24px;
    margin-top: 2px;
}

.suggestion-rewards-box strong {
    color: #166534;
    display: block;
    margin-bottom: 6px;
    font-size: 15px;
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
    padding: 16px;
    background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(26, 60, 52, 0.3);
}

.suggestion-submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(26, 60, 52, 0.4);
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
    padding: 40px 20px;
}

.success-icon {
    margin-bottom: 20px;
}

.success-icon i {
    font-size: 72px;
    color: #22c55e;
    animation: scaleIn 0.5s ease-out;
}

.suggestion-success h4 {
    color: #16a34a;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 12px;
}

.suggestion-success > p {
    color: #6c757d;
    font-size: 16px;
    margin-bottom: 24px;
}

.success-tracking {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.success-tracking p {
    margin: 0 0 8px 0;
    font-size: 15px;
    color: #2c3e50;
}

.success-tracking strong {
    color: #1A3C34;
}

.success-tracking #trackingId {
    color: #1A3C34;
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

.success-tracking small {
    color: #6c757d;
    font-size: 13px;
}

.suggestion-close-btn {
    padding: 12px 32px;
    background: white;
    color: #6c757d;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.suggestion-close-btn:hover {
    background: #f8f9fa;
    border-color: #1A3C34;
    color: #1A3C34;
}

/* Animations */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

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

/* Tablet (768px - 1024px) */
@media (max-width: 1024px) {
    .suggestion-modal-body {
        padding: 28px;
    }
    
    .suggestion-modal-header {
        padding: 20px 24px;
    }
    
    .suggestion-modal-header .modal-title {
        font-size: 20px;
    }
    
    .suggestion-icon-wrapper {
        width: 44px;
        height: 44px;
    }
    
    .suggestion-icon-wrapper i {
        font-size: 22px;
    }
}

/* Mobile (max-width: 767px) */
@media (max-width: 767px) {
    #suggestionModal .modal-dialog {
        margin: 10px;
        max-width: calc(100% - 20px);
    }
    
    .suggestion-modal-content {
        border-radius: 16px;
    }
    
    .suggestion-modal-header {
        padding: 18px 20px;
        flex-wrap: wrap;
    }
    
    .suggestion-header-content {
        gap: 12px;
    }
    
    .suggestion-icon-wrapper {
        width: 40px;
        height: 40px;
    }
    
    .suggestion-icon-wrapper i {
        font-size: 20px;
    }
    
    .suggestion-modal-header .modal-title {
        font-size: 18px;
    }
    
    .suggestion-subtitle {
        font-size: 13px;
    }
    
    .suggestion-modal-body {
        padding: 20px;
    }
    
    .suggestion-form-group {
        margin-bottom: 20px;
    }
    
    .suggestion-label {
        font-size: 13px;
    }
    
    .suggestion-input,
    .suggestion-textarea {
        padding: 12px 14px;
        font-size: 14px;
    }
    
    .suggestion-row {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .suggestion-info-box,
    .suggestion-rewards-box {
        padding: 14px;
        font-size: 13px;
    }
    
    .suggestion-rewards-box strong {
        font-size: 14px;
    }
    
    .suggestion-rewards-box p {
        font-size: 13px;
    }
    
    .suggestion-submit-btn {
        padding: 14px;
        font-size: 15px;
    }
    
    .suggestion-success {
        padding: 30px 16px;
    }
    
    .success-icon i {
        font-size: 56px;
    }
    
    .suggestion-success h4 {
        font-size: 24px;
    }
    
    .suggestion-success > p {
        font-size: 15px;
    }
}

/* Small Mobile (max-width: 480px) */
@media (max-width: 480px) {
    .suggestion-modal-header .modal-title {
        font-size: 16px;
    }
    
    .suggestion-subtitle {
        font-size: 12px;
    }
    
    .suggestion-modal-body {
        padding: 16px;
    }
    
    .suggestion-input,
    .suggestion-textarea {
        padding: 10px 12px;
        font-size: 14px;
    }
    
    .suggestion-submit-btn {
        padding: 12px;
        font-size: 14px;
    }
}

/* Landscape Mobile */
@media (max-width: 767px) and (orientation: landscape) {
    #suggestionModal .modal-dialog {
        max-height: 90vh;
    }
    
    .suggestion-modal-body {
        max-height: calc(90vh - 80px);
        overflow-y: auto;
    }
}
</style>
