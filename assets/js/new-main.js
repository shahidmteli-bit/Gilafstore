// GILAF STORE - NEW UI/UX JAVASCRIPT
// Header scroll effect
window.addEventListener('scroll', function() {
    const header = document.getElementById('header');
    if (window.scrollY > 50) { 
        header.classList.add('scrolled'); 
        document.body.classList.add('scrolled'); 
    } else { 
        header.classList.remove('scrolled'); 
        document.body.classList.remove('scrolled'); 
    }
});

// Mobile Menu Toggle
const menuToggle = document.querySelector('.menu-toggle');
if (menuToggle) {
    menuToggle.addEventListener('click', function() {
        const nav = document.querySelector('.nav-links');
        if (nav.style.display === 'flex') {
            nav.style.display = 'none';
        } else {
            nav.style.display = 'flex';
            nav.style.flexDirection = 'column';
            nav.style.position = 'absolute';
            nav.style.top = '70px';
            nav.style.left = '0';
            nav.style.width = '100%';
            nav.style.background = '#fff';
            nav.style.padding = '20px';
            nav.style.boxShadow = '0 5px 10px rgba(0,0,0,0.1)';
            document.querySelectorAll('.nav-links a').forEach(a => a.style.color = '#1A3C34');
            const dropbtns = document.querySelectorAll('.dropbtn');
            if (dropbtns) dropbtns.forEach(btn => btn.style.color = '#1A3C34');
        }
    });
}

// Login Modal Functions
function openLoginModal() { 
    const modal = document.getElementById('loginModal');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

function closeLoginModal() { 
    const modal = document.getElementById('loginModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

function switchLoginTab(type) {
    // Remove active class from all tabs
    document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.login-tab-premium').forEach(t => t.classList.remove('active'));
    
    // Hide all form sections
    document.querySelectorAll('.form-section').forEach(f => {
        f.classList.remove('active');
        f.style.display = 'none';
    });
    
    // Activate selected tab and form
    const tab = document.getElementById(`tab-${type}`);
    const form = document.getElementById(`form-${type}`);
    if (tab) tab.classList.add('active');
    if (form) {
        form.classList.add('active');
        form.style.display = 'flex';
    }
}

// Region/Currency Management
let currentCurrency = { code: 'INR', symbol: '₹', rate: 1, country: 'IN' };

function openRegionModal() { 
    const modal = document.getElementById('regionModal');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

function closeRegionModal() { 
    const modal = document.getElementById('regionModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    const regModal = document.getElementById('regionModal');
    const admModal = document.getElementById('adminModal');
    const logModal = document.getElementById('loginModal');
    const trkModal = document.getElementById('trackingModal');
    
    if (event.target == regModal && regModal) {
        regModal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
    if (event.target == admModal && admModal) {
        admModal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
    if (event.target == logModal && logModal) {
        logModal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
    if (event.target == trkModal && trkModal) {
        trkModal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

async function setRegion(countryCode, currencyCode, symbol, rate) {
    currentCurrency = { code: currencyCode, symbol: symbol, rate: rate, country: countryCode };
    
    // Save preference to backend
    try {
        const formData = new FormData();
        formData.append('action', 'update_preference');
        formData.append('country_code', countryCode);
        
        await fetch('update_region_preference.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Error saving region preference:', error);
    }
    
    const flagEl = document.getElementById('current-flag');
    const currEl = document.getElementById('current-currency');
    
    if (flagEl) flagEl.src = `https://flagcdn.com/${countryCode.toLowerCase()}.svg`;
    if (currEl) currEl.innerText = `${currencyCode} (${symbol})`;
    
    document.querySelectorAll('.country-option').forEach(opt => opt.classList.remove('selected'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('selected');
    }
    
    updateAllPrices();
    
    // Reload page to update region-specific content
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

function updateAllPrices() {
    const priceElements = document.querySelectorAll('.dynamic-price');
    priceElements.forEach(el => {
        const basePrice = parseFloat(el.getAttribute('data-price-inr'));
        if (currentCurrency.code === 'INR') {
            el.innerText = `₹${basePrice.toLocaleString('en-IN')}`;
        } else {
            const exportMarkup = 1.1; 
            const converted = (basePrice * currentCurrency.rate * exportMarkup);
            const finalPrice = Math.ceil(converted) - 0.01;
            el.innerText = `${currentCurrency.symbol}${finalPrice.toFixed(2)}`;
        }
    });
}

// Store Locator Functions - Database Driven
async function findStores() {
    const input = document.getElementById('pincodeInput');
    const container = document.getElementById('store-results-container');
    const errorMsg = document.getElementById('locator-error');
    
    if (!input || !container || !errorMsg) return;
    
    const pincode = input.value.trim();
    container.innerHTML = '';
    errorMsg.innerText = '';
    
    if (pincode.length !== 6 || isNaN(pincode)) { 
        errorMsg.innerText = "Please enter a valid 6-digit Pincode."; 
        return; 
    }
    
    // Show loading state
    container.innerHTML = '<div class="no-results-msg"><i class="fas fa-spinner fa-spin"></i> Searching for stores...</div>';
    
    try {
        // Fetch stores from database API
        const response = await fetch(`api/find_stores.php?pincode=${pincode}`);
        const data = await response.json();
        
        if (data.success && data.stores && data.stores.length > 0) {
            // Exact match found
            container.innerHTML = '';
            renderStoresFromDB(data.stores, false);
        } else {
            // No exact match - fetch nearby stores
            const nearbyResponse = await fetch(`api/find_nearby_stores.php?pincode=${pincode}`);
            const nearbyData = await nearbyResponse.json();
            
            if (nearbyData.success && nearbyData.stores && nearbyData.stores.length > 0) {
                container.innerHTML = `<div class="no-results-msg" style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;"><strong>No stores found exactly in ${pincode}.</strong><br>Showing nearest stores relative to ${nearbyData.reference_location || 'your location'}.</div>`;
                renderStoresFromDB(nearbyData.stores, true);
            } else {
                container.innerHTML = `<div class="no-results-msg" style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545;"><i class="fas fa-info-circle"></i> <strong>No stores found for pincode ${pincode}</strong><br><br>We currently don't have any approved stores in this area. Please try a different pincode or contact us for assistance.</div>`;
            }
        }
    } catch (error) {
        console.error('Store search error:', error);
        container.innerHTML = `<div class="no-results-msg" style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px;"><i class="fas fa-exclamation-triangle"></i> Unable to search for stores. Please try again later.</div>`;
    }
}

function clearLocator() {
    const input = document.getElementById('pincodeInput');
    const container = document.getElementById('store-results-container');
    const errorMsg = document.getElementById('locator-error');
    
    if (input) input.value = '';
    if (container) container.innerHTML = '';
    if (errorMsg) errorMsg.innerText = '';
}

function renderStoresFromDB(storeList, showDistance) {
    const container = document.getElementById('store-results-container');
    if (!container) return;
    
    storeList.forEach(store => {
        const distBadge = showDistance && store.distance ? `<span class="distance-tag">${store.distance.toFixed(1)} km away</span>` : '';
        
        // Store type badge
        const typeBadge = `<span class="store-type-badge" style="background: ${store.type_color || '#666'}; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; margin-left: 10px;">${store.type_label || 'Store'}</span>`;
        
        const html = `
            <div class="store-card">
                <div class="store-name">
                    ${store.store_name || store.name}
                    ${typeBadge}
                    ${distBadge}
                </div>
                <div class="store-address">
                    <i class="fas fa-map-marker-alt" style="color:var(--color-gold); margin-right:5px;"></i>
                    ${store.address}${store.city ? ', ' + store.city : ''}${store.state ? ', ' + store.state : ''} - ${store.pincode}
                </div>
                ${store.owner_name ? `<div style="font-size: 0.9rem; color: #666; margin-top: 5px;"><i class="fas fa-user" style="margin-right: 5px;"></i>Owner: ${store.owner_name}</div>` : ''}
                <div class="store-actions">
                    <a href="tel:${store.phone}" class="store-btn btn-call">
                        <i class="fas fa-phone-alt"></i> Call
                    </a>
                    <a href="${store.google_maps_url || store.mapUrl || '#'}" target="_blank" class="store-btn btn-dir">
                        <i class="fas fa-directions"></i> Directions
                    </a>
                </div>
            </div>
        `;
        container.innerHTML += html;
    });
}

function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
    var R = 6371;
    var dLat = deg2rad(lat2 - lat1);
    var dLon = deg2rad(lon2 - lon1); 
    var a = Math.sin(dLat/2) * Math.sin(dLat/2) + 
            Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
            Math.sin(dLon/2) * Math.sin(dLon/2); 
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
    return R * c;
}

function deg2rad(deg) { 
    return deg * (Math.PI/180); 
}

// Admin Panel Functions - Removed (now managed through admin panel database interface)

// Authenticity Verification with Batch Lifecycle Features
function verifyBatch(e) {
    e.preventDefault();
    
    const batchInput = document.getElementById('batchInput');
    const resultBox = document.getElementById('verification-result');
    
    if (!batchInput || !resultBox) return;
    
    const batchValue = batchInput.value.trim().toUpperCase();
    
    if (!batchValue) {
        alert('Please enter a batch code');
        return;
    }
    
    // Show loading
    resultBox.style.display = "block";
    resultBox.style.borderLeft = "4px solid #3b82f6";
    resultBox.innerHTML = '<p style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Verifying batch...</p>';
    
    // Fetch batch data from API with lifecycle features
    fetch('/Gilaf Ecommerce website/api/verify_batch_ajax.php?code=' + encodeURIComponent(batchValue))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.valid) {
                // Valid batch - show green verification
                const batch = data.batch;
                let badges = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0;">';
                if (batch.is_lab_tested) badges += '<span style="background:#3b82f6;color:white;padding:4px 12px;border-radius:20px;font-size:0.8rem;">🧪 Lab Tested</span>';
                if (batch.is_organic) badges += '<span style="background:#10b981;color:white;padding:4px 12px;border-radius:20px;font-size:0.8rem;">🌱 Organic</span>';
                badges += '</div>';
                
                resultBox.style.borderLeft = "4px solid var(--color-green)";
                resultBox.innerHTML = `
                    <h4 style="color:var(--color-green); display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-check-circle"></i> Authenticity Verified
                    </h4>
                    ${badges}
                    <div style="margin-top:15px; font-size:0.9rem; color:#444; line-height:1.8;">
                        ${batch.product_image ? `<div style="text-align:center;margin-bottom:15px;"><img src="${batch.product_image}" alt="${batch.product_name}" style="max-width:120px;border-radius:8px;"></div>` : ''}
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Product Name:</strong> ${batch.product_name}
                        </div>
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Net Weight:</strong> ${batch.net_weight}
                        </div>
                        ${batch.mrp ? `<div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;"><strong>MRP:</strong> ₹${batch.mrp}</div>` : ''}
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Manufacturing Date:</strong> ${batch.manufacturing_date}
                        </div>
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Expiry Date:</strong> ${batch.expiry_date}
                        </div>
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Country of Origin:</strong> ${batch.country_of_origin}
                        </div>
                        ${batch.approver_name ? `<div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;"><strong>Approved By:</strong> ${batch.approver_name}</div>` : ''}
                        <div style="color: #666; font-size: 0.8rem;">
                            <strong>Verification Date & Time:</strong> ${batch.verified_at}
                        </div>
                    </div>
                `;
            } else if (data.success && !data.valid) {
                // Invalid batch - show warning/error
                let color = '#f59e0b';
                let icon = 'exclamation-triangle';
                
                if (data.error_type === 'recalled') {
                    color = '#ef4444';
                    icon = 'ban';
                } else if (data.error_type === 'blocked') {
                    color = '#ef4444';
                    icon = 'lock';
                } else if (data.error_type === 'expired') {
                    color = '#f59e0b';
                    icon = 'clock';
                }
                
                resultBox.style.borderLeft = `4px solid ${color}`;
                resultBox.innerHTML = `
                    <h4 style="color:${color}; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-${icon}"></i> ${data.message}
                    </h4>
                    <p style="margin-top:10px; font-size:0.9rem; color:#666;">
                        ${data.description}
                    </p>
                    ${data.batch ? `
                        <div style="margin-top:15px; padding-top:15px; border-top:1px solid #ddd; font-size:0.85rem; color:#666;">
                            <div><strong>Batch Code:</strong> ${data.batch.code}</div>
                            <div><strong>Product:</strong> ${data.batch.product_name}</div>
                            <div><strong>Expiry Date:</strong> ${data.batch.expiry_date}</div>
                        </div>
                    ` : ''}
                    <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="/Gilaf Ecommerce website/contact.php" style="padding:10px 20px; background:var(--color-green); color:white; text-decoration:none; border-radius:4px; font-size:0.9rem;">
                            <i class="fas fa-headset"></i> Contact Support
                        </a>
                        <a href="/Gilaf Ecommerce website/report-suspicious.php?batch=${batchValue}" style="padding:10px 20px; background:white; color:var(--color-green); border:2px solid var(--color-green); text-decoration:none; border-radius:4px; font-size:0.9rem;">
                            <i class="fas fa-flag"></i> Report Suspicious Product
                        </a>
                    </div>
                `;
            } else {
                // Not found or error
                resultBox.style.borderLeft = "4px solid #ef4444";
                resultBox.innerHTML = `
                    <h4 style="color:#ef4444; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-times-circle"></i> ${data.message || 'Batch Not Found'}
                    </h4>
                    <p style="margin-top:10px; font-size:0.9rem; color:#666;">
                        ${data.description || 'The batch code entered does not exist in our system. This could indicate a counterfeit product or an incorrect batch code.'}
                    </p>
                    <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="/Gilaf Ecommerce website/contact.php" style="padding:10px 20px; background:var(--color-green); color:white; text-decoration:none; border-radius:4px; font-size:0.9rem;">
                            <i class="fas fa-headset"></i> Contact Support
                        </a>
                        <a href="/Gilaf Ecommerce website/report-suspicious.php?batch=${batchValue}" style="padding:10px 20px; background:white; color:var(--color-green); border:2px solid var(--color-green); text-decoration:none; border-radius:4px; font-size:0.9rem;">
                            <i class="fas fa-flag"></i> Report Suspicious Product
                        </a>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Verification error:', error);
            resultBox.style.borderLeft = "4px solid #ef4444";
            resultBox.innerHTML = `
                <h4 style="color:#ef4444; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-triangle"></i> System Error
                </h4>
                <p style="margin-top:10px; font-size:0.9rem; color:#666;">
                    Unable to verify batch at this time. Please try again later.
                </p>
            `;
        });
}

// Order Tracking Functions
function openTrackingModal() { 
    const modal = document.getElementById('trackingModal');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

function closeTrackingModal() { 
    const modal = document.getElementById('trackingModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

function trackOrder() {
    const input = document.getElementById('trackingIdInput');
    const resultBox = document.getElementById('trackingResult');
    const timeline = document.getElementById('trackingTimeline');
    
    if (!input || !resultBox || !timeline) return;
    
    const trackingId = input.value.trim();
    
    if(!trackingId) { 
        alert("Please enter a tracking ID"); 
        return; 
    }
    
    timeline.innerHTML = '<div style="text-align:center; padding:20px; color:#888;"><i class="fas fa-circle-notch fa-spin"></i> Fetching status...</div>';
    resultBox.classList.add('show');
    
    setTimeout(() => {
        const steps = [
            { title: "Order Confirmed", date: "Jan 25, 10:30 AM", status: "completed" },
            { title: "Packed", date: "Jan 25, 02:15 PM", status: "completed" },
            { title: "Picked Up", date: "Jan 25, 05:45 PM", status: "completed" },
            { title: "Transit", date: "Arrived at Regional Hub, New Delhi.", status: "active", desc: "Arrived at Regional Hub, New Delhi." },
            { title: "Out for Delivery", date: "Estimated Jan 29", status: "pending" },
            { title: "Delivered", date: "", status: "pending" }
        ];
        
        let html = '';
        steps.forEach(step => {
            const activeClass = step.status === 'active' ? 'active' : (step.status === 'completed' ? 'completed' : '');
            const descHtml = step.desc ? `<p>${step.desc}</p>` : (step.date ? `<p>${step.date}</p>` : '');
            
            html += `
                <div class="timeline-step ${activeClass}">
                    <div class="step-icon"><div class="dot"></div></div>
                    <div class="step-content">
                        <h5>${step.title}</h5>
                        ${descHtml}
                    </div>
                </div>
            `;
        });
        
        timeline.innerHTML = html;
    }, 1000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Update prices on load
    updateAllPrices();
});
