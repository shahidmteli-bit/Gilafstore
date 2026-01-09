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
    if (modal) modal.classList.add('active'); 
}

function closeLoginModal() { 
    const modal = document.getElementById('loginModal');
    if (modal) modal.classList.remove('active'); 
}

function switchLoginTab(type) {
    document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
    const tab = document.getElementById(`tab-${type}`);
    const form = document.getElementById(`form-${type}`);
    if (tab) tab.classList.add('active');
    if (form) form.classList.add('active');
}

// Region/Currency Management
let currentCurrency = { code: 'INR', symbol: '₹', rate: 1, country: 'IN' };

function openRegionModal() { 
    const modal = document.getElementById('regionModal');
    if (modal) modal.classList.add('active'); 
}

function closeRegionModal() { 
    const modal = document.getElementById('regionModal');
    if (modal) modal.classList.remove('active'); 
}

// Close modals when clicking outside
window.onclick = function(event) {
    const regModal = document.getElementById('regionModal');
    const admModal = document.getElementById('adminModal');
    const logModal = document.getElementById('loginModal');
    const trkModal = document.getElementById('trackingModal');
    
    if (event.target == regModal && regModal) regModal.classList.remove('active');
    if (event.target == admModal && admModal) admModal.classList.remove('active');
    if (event.target == logModal && logModal) logModal.classList.remove('active');
    if (event.target == trkModal && trkModal) trkModal.classList.remove('active');
}

function setRegion(countryCode, currencyCode, symbol, rate) {
    currentCurrency = { code: currencyCode, symbol: symbol, rate: rate, country: countryCode };
    
    const flagEl = document.getElementById('current-flag');
    const currEl = document.getElementById('current-currency');
    
    if (flagEl) flagEl.src = `https://flagcdn.com/${countryCode.toLowerCase()}.svg`;
    if (currEl) currEl.innerText = `${currencyCode} (${symbol})`;
    
    document.querySelectorAll('.country-option').forEach(opt => opt.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    
    updateAllPrices();
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

// Store Locator Functions
const cityCoords = {
    '110001': { lat: 28.6304, lng: 77.2177 }, 
    '400001': { lat: 18.934, lng: 72.837 },   
    '560001': { lat: 12.9716, lng: 77.5946 }, 
    '600001': { lat: 13.0827, lng: 80.2707 }, 
    '700001': { lat: 22.5726, lng: 88.3639 }, 
    '190001': { lat: 34.0837, lng: 74.7973 }  
};

const defaultStores = [
    { id: 1, name: "Gilaf Foods HQ", pincode: "190001", address: "Residency Road, Srinagar, Kashmir", lat: 34.0837, lng: 74.7973, phone: "+91 99000 12345", mapUrl: "https://maps.google.com" },
    { id: 2, name: "Delhi Premium Outlet", pincode: "110001", address: "Block B, Connaught Place, New Delhi", lat: 28.6328, lng: 77.2197, phone: "+91 11 2345 6789", mapUrl: "https://maps.google.com" },
    { id: 3, name: "Mumbai Nature Store", pincode: "400050", address: "Bandra West, Mumbai, Maharashtra", lat: 19.0596, lng: 72.8295, phone: "+91 22 9876 5432", mapUrl: "https://maps.google.com" }
];

let stores = JSON.parse(localStorage.getItem('gilafStores')) || defaultStores;
function findStores() {
    const input = document.getElementById('pincodeInput');
    const container = document.getElementById('store-results-container');
    const errorMsg = document.getElementById('locator-error');
    
    if (!input || !container) return;
    
    const pincode = input.value.trim();
    
    if (!pincode) {
        if (errorMsg) errorMsg.innerText = 'Please enter a PIN code.';
        return;
    }
    
    if (!/^\d{6}$/.test(pincode)) {
        if (errorMsg) errorMsg.innerText = 'Please enter a valid 6-digit PIN code.';
        return;
    }
    
    if (errorMsg) errorMsg.innerText = '';
    container.innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin fa-2x" style="color:var(--color-gold);"></i><p style="margin-top:15px;">Searching for stores...</p></div>';
    
    // Fetch from database API
    fetch(`api/find_stores.php?pincode=${encodeURIComponent(pincode)}`)
        .then(response => response.json())
        .then(data => {
            container.innerHTML = '';
            
            if (data.success) {
                renderStoresFromDB(data.stores);
            } else {
                container.innerHTML = `<div class="no-results-msg"><i class="fas fa-info-circle"></i> ${data.message}</div>`;
            }
        })
        .catch(error => {
            container.innerHTML = '<div class="no-results-msg" style="color:#d9534f;"><i class="fas fa-exclamation-triangle"></i> Error searching for stores. Please try again.</div>';
        });
}

function clearLocator() {
    const input = document.getElementById('pincodeInput');
    const container = document.getElementById('store-results-container');
    const errorMsg = document.getElementById('locator-error');
    
    if (input) input.value = '';
    if (container) container.innerHTML = '';
    if (errorMsg) errorMsg.innerText = '';
}

function renderStoresFromDB(storeList) {
    const container = document.getElementById('store-results-container');
    if (!container) return;
    
    storeList.forEach(store => {
        const typeLabel = store.type_label || 'Store';
        const typeColor = store.type_color || '#666';
        
        const html = `
            <div class="store-card">
                <div class="store-name">
                    ${store.store_name}
                    <span style="background:${typeColor}; color:white; padding:2px 8px; border-radius:4px; font-size:0.7rem; margin-left:10px;">${typeLabel}</span>
                </div>
                <div class="store-address">
                    <i class="fas fa-map-marker-alt" style="color:var(--color-gold); margin-right:5px;"></i>
                    ${store.address}
                </div>
                <div class="store-address" style="margin-top:5px;">
                    <i class="fas fa-phone" style="color:var(--color-gold); margin-right:5px;"></i>
                    ${store.phone}
                </div>
                <div class="store-actions">
                    <a href="tel:${store.phone}" class="store-btn btn-call">
                        <i class="fas fa-phone-alt"></i> Call
                    </a>
                    ${store.google_maps_url ? `<a href="${store.google_maps_url}" target="_blank" class="store-btn btn-dir">
                        <i class="fas fa-directions"></i> Get Directions
                    </a>` : ''}
                </div>
            </div>
        `;
        container.innerHTML += html;
    });
}

function renderStores(storeList, showDistance) {
    const container = document.getElementById('store-results-container');
    if (!container) return;
    
    storeList.forEach(store => {
        const distBadge = showDistance ? `<span class="distance-tag">${store.distance.toFixed(1)} km away</span>` : '';
        const html = `
            <div class="store-card">
                <div class="store-name">${store.name}${distBadge}</div>
                <div class="store-address">
                    <i class="fas fa-map-marker-alt" style="color:var(--color-gold); margin-right:5px;"></i>
                    ${store.address} - ${store.pincode}
                </div>
                <div class="store-actions">
                    <a href="tel:${store.phone}" class="store-btn btn-call">
                        <i class="fas fa-phone-alt"></i> Call
                    </a>
                    <a href="${store.mapUrl || '#'}" target="_blank" class="store-btn btn-dir">
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

// Admin Panel Functions
function openAdminPanel() { 
    closeLoginModal(); 
    const modal = document.getElementById('adminModal');
    if (modal) {
        modal.classList.add('active'); 
        renderAdminList(); 
    }
}

function closeAdminModal() { 
    const modal = document.getElementById('adminModal');
    if (modal) modal.classList.remove('active'); 
}

function renderAdminList() {
    const list = document.getElementById('adminStoreList');
    if (!list) return;
    
    list.innerHTML = '';
    stores.forEach((store, index) => {
        list.innerHTML += `
            <div class="store-list-item">
                <div><strong>${store.name}</strong> (${store.pincode})</div>
                <i class="fas fa-trash delete-btn" onclick="deleteStore(${index})"></i>
            </div>
        `;
    });
}

function adminAddStore(e) {
    e.preventDefault();
    
    const newStore = {
        id: Date.now(),
        name: document.getElementById('adminName').value,
        pincode: document.getElementById('adminPincode').value,
        phone: document.getElementById('adminPhone').value,
        address: document.getElementById('adminAddress').value,
        lat: parseFloat(document.getElementById('adminLat').value),
        lng: parseFloat(document.getElementById('adminLng').value),
        mapUrl: document.getElementById('adminMapUrl').value
    };
    
    stores.push(newStore);
    localStorage.setItem('gilafStores', JSON.stringify(stores));
    
    document.getElementById('addStoreForm').reset();
    renderAdminList();
    alert('Store Added Successfully!');
}

function deleteStore(index) {
    if(confirm('Are you sure you want to delete this store?')) {
        stores.splice(index, 1);
        localStorage.setItem('gilafStores', JSON.stringify(stores));
        renderAdminList();
    }
}

function resetDefaultStores() {
    stores = defaultStores;
    localStorage.setItem('gilafStores', JSON.stringify(stores));
    renderAdminList();
}

// Authenticity Verification - Real Database Integration
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

    // Show loading state
    resultBox.style.display = "block";
    resultBox.style.borderLeft = "4px solid #ffc107";
    resultBox.innerHTML = `
        <div style="text-align:center; padding:20px;">
            <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--color-gold);"></i>
            <p style="margin-top:15px; color:#666;">Verifying batch code...</p>
        </div>
    `;
    
    // Fetch from API
    fetch(`api/verify_batch.php?batch_code=${encodeURIComponent(batchValue)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const batch = data.data;
                resultBox.style.borderLeft = "4px solid var(--color-green)";
                resultBox.innerHTML = `
                    <h4 style="color:var(--color-green); display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-check-circle"></i> Authenticity Verified
                    </h4>
                    <div style="margin-top:15px; font-size:0.9rem; color:#444; line-height:1.8;">
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Product Name:</strong> ${batch.product_name}${batch.grade ? ' (' + batch.grade + ')' : ''}
                        </div>
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Net Weight:</strong> ${batch.net_weight}
                        </div>
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Manufacturing Date:</strong> ${batch.manufacturing_date}
                        </div>
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Expiry Date:</strong> ${batch.expiry_date}
                        </div>
                        <div style="border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 10px;">
                            <strong>Country of Origin:</strong> ${batch.country_of_origin}
                        </div>
                        <div style="color: #666; font-size: 0.8rem;">
                            <strong>Verification Date & Time:</strong> ${batch.verification_datetime}
                        </div>
                    </div>
                `;
            } else {
                resultBox.style.borderLeft = "4px solid #d9534f";
                resultBox.innerHTML = `
                    <h4 style="color:#d9534f; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-times-circle"></i> Batch Not Found
                    </h4>
                    <p style="margin-top:10px; font-size:0.9rem; color:#666;">
                        ${data.message || 'The Batch ID entered does not match our records. Please double-check the ID printed on the lid.'}
                    </p>
                `;
            }
        })
        .catch(error => {
            resultBox.style.borderLeft = "4px solid #d9534f";
            resultBox.innerHTML = `
                <h4 style="color:#d9534f; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-triangle"></i> Error
                </h4>
                <p style="margin-top:10px; font-size:0.9rem; color:#666;">
                    An error occurred while verifying the batch code. Please try again.
                </p>
            `;
        });
}

// Order Tracking Functions
function openTrackingModal() { 
    const modal = document.getElementById('trackingModal');
    if (modal) modal.classList.add('active'); 
}

function closeTrackingModal() { 
    const modal = document.getElementById('trackingModal');
    if (modal) modal.classList.remove('active'); 
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
