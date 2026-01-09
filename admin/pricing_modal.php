<!-- Pricing & Discount Modal -->
<div class="modal fade" id="pricingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-percent"></i> Set Pricing & Discount</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Cost Price (₹)</label>
            <input type="number" id="modalCostPrice" class="form-control" step="0.01" min="0" placeholder="Enter cost price" onkeyup="calculateModalDiscount()" />
            <small class="text-muted">Your purchase/manufacturing cost</small>
          </div>
          <div class="col-12">
            <label class="form-label">Selling Price (₹)</label>
            <input type="number" id="modalSellingPrice" class="form-control" step="0.01" min="0" placeholder="Enter selling price" onkeyup="calculateModalDiscount()" />
            <small class="text-muted">Price shown to customers</small>
          </div>
          <div class="col-12">
            <div class="card bg-light">
              <div class="card-body">
                <h6 class="mb-2">Pricing Summary</h6>
                <div class="d-flex justify-content-between mb-1">
                  <span>Profit Margin:</span>
                  <strong id="modalProfitAmount" class="text-success">₹0.00</strong>
                </div>
                <div class="d-flex justify-content-between">
                  <span>Discount %:</span>
                  <strong id="modalDiscountPercent" class="text-primary">0.0%</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="savePricing()">Save Pricing</button>
      </div>
    </div>
  </div>
</div>

<script>
// Calculate discount in modal
function calculateModalDiscount() {
  const costPrice = parseFloat(document.getElementById('modalCostPrice').value) || 0;
  const sellingPrice = parseFloat(document.getElementById('modalSellingPrice').value) || 0;
  
  if (sellingPrice > 0) {
    const profit = sellingPrice - costPrice;
    const discount = ((profit / sellingPrice) * 100);
    
    document.getElementById('modalProfitAmount').textContent = '₹' + profit.toFixed(2);
    document.getElementById('modalDiscountPercent').textContent = discount.toFixed(1) + '%';
    
    // Color coding
    if (profit > 0) {
      document.getElementById('modalProfitAmount').className = 'text-success';
      document.getElementById('modalDiscountPercent').className = 'text-success';
    } else if (profit < 0) {
      document.getElementById('modalProfitAmount').className = 'text-danger';
      document.getElementById('modalDiscountPercent').className = 'text-danger';
    } else {
      document.getElementById('modalProfitAmount').className = 'text-secondary';
      document.getElementById('modalDiscountPercent').className = 'text-secondary';
    }
  }
}

// Save pricing from modal to form
function savePricing() {
  const costPrice = parseFloat(document.getElementById('modalCostPrice').value) || 0;
  const sellingPrice = parseFloat(document.getElementById('modalSellingPrice').value) || 0;
  
  // Update hidden fields
  document.getElementById('costPrice').value = costPrice;
  document.getElementById('sellingPrice').value = sellingPrice;
  
  // Update summary text
  const profit = sellingPrice - costPrice;
  const discount = sellingPrice > 0 ? ((profit / sellingPrice) * 100).toFixed(1) : 0;
  
  document.getElementById('pricingSummary').textContent = 
    `Cost: ₹${costPrice.toFixed(2)} | Selling: ₹${sellingPrice.toFixed(2)} | Discount: ${discount}%`;
  
  // Close modal
  bootstrap.Modal.getInstance(document.getElementById('pricingModal')).hide();
}
</script>
