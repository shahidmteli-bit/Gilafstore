<!-- Duplicate Batch Warning Modal -->
<div class="modal fade" id="duplicateBatchModal" tabindex="-1" aria-labelledby="duplicateBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="duplicateBatchModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i>Batch Already Created
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning mb-3">
          <strong>Duplicate Batch Detected!</strong>
        </div>
        <p class="mb-2">A batch already exists for this product with the same specifications:</p>
        <ul class="mb-3">
          <li><strong>Product:</strong> <span id="dupProduct"></span></li>
          <li><strong>Weight:</strong> <span id="dupWeight"></span></li>
          <li><strong>Manufacturing Date:</strong> <span id="dupMfgDate"></span></li>
          <li><strong>Expiry Date:</strong> <span id="dupExpDate"></span></li>
        </ul>
        <div class="alert alert-info mb-0">
          <strong>Existing Batch:</strong> <span id="dupBatchCode"></span><br>
          <strong>Status:</strong> <span id="dupStatus"></span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
