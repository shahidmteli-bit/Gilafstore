<!-- Reusable Delete Confirmation Modal -->
<!-- Include this file in any page where you need delete confirmation -->
<!-- Usage: include __DIR__ . '/includes/delete-modal.php'; -->

<style>
/* Delete Confirmation Modal - Reusable Component */
.delete-confirmation-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(5px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    animation: deleteModalFadeIn 0.3s ease-out;
}

.delete-confirmation-modal.active {
    display: flex;
}

.delete-confirmation-content {
    background: white;
    border-radius: 24px;
    padding: 40px;
    max-width: 480px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: deleteModalScaleIn 0.3s ease-out;
    text-align: center;
}

@keyframes deleteModalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes deleteModalScaleIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

.delete-confirmation-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    animation: deleteIconPulse 2s infinite;
}

.delete-confirmation-icon i {
    font-size: 2.5rem;
    color: #ef4444;
}

@keyframes deleteIconPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.delete-confirmation-modal h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem;
    color: #1A3C34;
    margin-bottom: 12px;
    font-weight: 600;
}

.delete-confirmation-modal p {
    color: #666;
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 32px;
}

.delete-confirmation-actions {
    display: flex;
    gap: 12px;
}

.btn-delete-confirm,
.btn-delete-cancel {
    flex: 1;
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-delete-confirm {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-delete-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

.btn-delete-cancel {
    background: #f0f0f0;
    color: #666;
}

.btn-delete-cancel:hover {
    background: #e0e0e0;
}

@media (max-width: 768px) {
    .delete-confirmation-content {
        padding: 32px 24px;
    }
    
    .delete-confirmation-actions {
        flex-direction: column;
    }
}
</style>

<!-- Delete Modal HTML -->
<div id="deleteConfirmationModal" class="delete-confirmation-modal">
    <div class="delete-confirmation-content">
        <div class="delete-confirmation-icon">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 id="deleteModalTitle">Delete Item?</h3>
        <p id="deleteModalMessage">Are you sure you want to delete this item? This action cannot be undone.</p>
        <div class="delete-confirmation-actions">
            <button type="button" class="btn-delete-cancel" onclick="closeDeleteConfirmation()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn-delete-confirm" onclick="confirmDeleteAction()">
                <i class="fas fa-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>

<script>
// Global delete confirmation functionality
let deleteConfirmationCallback = null;

/**
 * Show delete confirmation modal
 * @param {Object} options - Configuration options
 * @param {string} options.title - Modal title (default: "Delete Item?")
 * @param {string} options.message - Confirmation message
 * @param {Function} options.onConfirm - Callback function when confirmed
 */
function showDeleteConfirmation(options = {}) {
    const modal = document.getElementById('deleteConfirmationModal');
    const title = document.getElementById('deleteModalTitle');
    const message = document.getElementById('deleteModalMessage');
    
    // Set custom text or use defaults
    title.textContent = options.title || 'Delete Item?';
    message.textContent = options.message || 'Are you sure you want to delete this item? This action cannot be undone.';
    
    // Store callback
    deleteConfirmationCallback = options.onConfirm;
    
    // Show modal
    modal.classList.add('active');
}

/**
 * Close delete confirmation modal
 */
function closeDeleteConfirmation() {
    const modal = document.getElementById('deleteConfirmationModal');
    modal.classList.remove('active');
    deleteConfirmationCallback = null;
}

/**
 * Confirm delete action
 */
function confirmDeleteAction() {
    if (deleteConfirmationCallback && typeof deleteConfirmationCallback === 'function') {
        deleteConfirmationCallback();
    }
    closeDeleteConfirmation();
}

// Close modal on outside click
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('deleteConfirmationModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteConfirmation();
            }
        });
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteConfirmation();
    }
});
</script>
