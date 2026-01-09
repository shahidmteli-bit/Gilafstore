    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="logo" style="text-align: left;">
                        <h1>GILAF STORE</h1>
                        <span>Taste • Culture • Craft</span>
                    </div>
                    <p>
                        A premium D2C brand by Gilaf Foods & Spices.<br>
                        Regd. Office: Srinagar, Kashmir, India.<br>
                        <br>
                        <a href="mailto:gilafstore@gmail.com" style="color: var(--color-gold);">gilafstore@gmail.com</a>
                    </p>
                    <div class="social-icons" style="margin-top: 20px;">
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h4>SHOP</h4>
                    <ul>
                        <li><a href="<?= base_url('shop.php'); ?>">Our Products</a></li>
                        <li><a href="<?= base_url('shop.php'); ?>">Best Selling</a></li>
                        <li><a href="<?= base_url('gifting-hampers.php'); ?>">Gifting & Hampers</a></li>
                        <li><a href="<?= base_url('offers.php'); ?>">Offers</a></li>
                        <li><a href="#locator">Find Gilaf Stores</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h4>SUPPORT</h4>
                    <ul>
                        <li><a href="#" onclick="openTrackingModal(); return false;">Track Order</a></li>
                        <li><a href="#">Shipping & Logistics</a></li>
                        <li><a href="#verification">Authenticity Check</a></li>
                        <li><a href="http://localhost/Gilaf%20Ecommerce%20website/user/create_ticket.php">Contact Us</a></li>
                        <li><a href="http://localhost/Gilaf%20Ecommerce%20website/apply-distributor.php">Become a Distributor</a></li>
                        <li><a href="<?= base_url('faqs.php'); ?>">FAQs</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h4>POLICIES & LEGAL</h4>
                    <ul>
                        <li><a href="<?= base_url('privacy-policy.php'); ?>">Privacy Policy</a></li>
                        <li><a href="<?= base_url('terms-conditions.php'); ?>">Terms & Conditions</a></li>
                        <li><a href="<?= base_url('shipping-policy.php'); ?>">Shipping Policy</a></li>
                        <li><a href="<?= base_url('refund-return-policy.php'); ?>">Refund & Return Policy</a></li>
                        <li><a href="<?= base_url('order-cancellation-policy.php'); ?>">Order Cancellation Policy</a></li>
                        <li><a href="<?= base_url('payment-policy.php'); ?>">Payment Policy</a></li>
                        <li><a href="<?= base_url('disclaimer.php'); ?>">Disclaimer</a></li>
                    </ul>
                </div>
            </div>

            <div class="shipping-partners-section">
                <div class="ship-group">
                    <h5 class="shipping-title"><i class="fas fa-globe-americas"></i> International Shipping</h5>
                    <p class="shipping-subtitle">We Ship Worldwide</p>
                    <div class="partner-logos-grid">
                        <div class="partner-text-logo dhl-logo">DHL EXPRESS</div>
                        <div class="partner-text-logo fedex-logo">FedEx</div>
                        <div class="partner-text-logo dpworld-logo">DP WORLD</div>
                    </div>
                </div>
                <div class="ship-group">
                    <h5 class="shipping-title"><i class="fas fa-truck"></i> Domestic Shipping (India)</h5>
                    <p class="shipping-subtitle">Free Domestic Shipping</p>
                    <div class="partner-logos-grid">
                        <div class="partner-text-logo indiapost-logo">INDIA POST</div>
                        <div class="partner-text-logo delhivery-logo">Delhivery</div>
                        <div class="partner-text-logo bluedart-logo">BLUE DART</div>
                        <div class="partner-text-logo dtdc-logo">DTDC</div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom" style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 15px;">
                <span>&copy; <?= date('Y'); ?> Gilaf Foods & Spices. All Rights Reserved.</span>
                <span style="font-size: 0.85rem; color: rgba(255,255,255,0.6); font-weight: 400; text-align: center; white-space: nowrap;">Developed by Shahid Mohammad</span>
                <span style="display: flex; gap: 10px; font-size: 1.2rem; justify-content: flex-end;">
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-paypal"></i>
                    <i class="fab fa-cc-apple-pay"></i>
                </span>
            </div>
            <style>
            @media (max-width: 768px) {
                .footer-bottom {
                    grid-template-columns: 1fr !important;
                    text-align: center !important;
                    gap: 10px !important;
                }
                .footer-bottom span:last-child {
                    justify-content: center !important;
                }
            }
            </style>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal-overlay" id="loginModal">
        <div class="region-modal" style="width: 450px;">
            <div class="modal-close" onclick="closeLoginModal()">&times;</div>
            <h3 class="text-center" style="color: var(--color-green); margin-bottom: 20px; font-family: var(--font-serif);">Welcome Back</h3>
            <p class="text-center" style="font-size: 0.9rem; color: #777; margin-bottom: 25px;">Sign in to your account to manage orders</p>
            <div class="login-tabs">
                <div class="login-tab active" id="tab-cust" onclick="switchLoginTab('cust')">Customer</div>
                <div class="login-tab" id="tab-dist" onclick="switchLoginTab('dist')">Distributor</div>
            </div>
            <div id="form-cust" class="form-section active" style="height: 380px; display: flex !important; flex-direction: column;">
                <form action="<?= base_url('user/login.php'); ?>" method="post" style="flex: 1; display: flex; flex-direction: column;">
                    <label class="modal-label">Email Address</label>
                    <input type="email" name="email" class="login-input" placeholder="hello@example.com" required>
                    <label class="modal-label">Password</label>
                    <input type="password" name="password" class="login-input" placeholder="••••••••" required>
                    <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 6px; background: #1A3C34; color: white; padding: 12px; font-weight: 600; font-size: 1rem; margin-top: auto;">Sign In</button>
                </form>
                <div class="create-account-link" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb;">
                    <div style="margin-bottom: 8px; font-size: 0.85rem;">New to Gilaf? <a href="<?= base_url('register.php'); ?>">Create an Account</a></div>
                    <div style="font-size: 0.85rem;"><a href="<?= base_url('user/forgot_password.php'); ?>" style="color: #1A3C34; text-decoration: none; font-weight: 600;"><i class="fas fa-key"></i> Forgot Password?</a></div>
                </div>
            </div>
                                                <div id="form-dist" class="form-section" style="height: 380px; display: none !important; flex-direction: column;">
                <form action="<?= base_url('admin/admin_login.php'); ?>" method="post" style="flex: 1; display: flex; flex-direction: column;">
                    <div class="info-box" style="margin-bottom: 12px; padding: 10px; font-size: 0.85rem;">
                        <strong>Distributor Portal:</strong> Access bulk pricing and wholesale management.
                    </div>
                    <label class="modal-label">Distributor ID / Email</label>
                    <input type="text" name="identifier" class="login-input" placeholder="DST-XXXX or Email" required>
                    <label class="modal-label">Password</label>
                    <input type="password" name="password" class="login-input" placeholder="••••••••" required>
                    <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 6px; background: #1A3C34; color: white; padding: 12px; font-weight: 600; font-size: 1rem; margin-top: auto;">Access Portal</button>
                </form>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 0.85rem; color: #777;">
                    <div style="margin-bottom: 8px; text-align: center;">
                        <a href="<?= base_url('admin/forgot_password.php'); ?>" style="color: #1A3C34; text-decoration: none; font-weight: 600;"><i class="fas fa-key"></i> Forgot Password?</a>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                        <span>Apply to become a distributor?</span>
                        <a href="<?= base_url('apply-distributor.php'); ?>" class="btn-apply-small" style="display: inline-flex; align-items: center; padding: 6px 16px; background: #C5A059; border: 2px solid #C5A059; border-radius: 8px; color: white; font-size: 0.8rem; font-weight: 600; text-decoration: none; box-shadow: 0 2px 8px rgba(197, 160, 89, 0.3); transition: all 0.3s ease;">
                            Apply
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Region Modal -->
    <div class="modal-overlay" id="regionModal">
        <div class="region-modal" style="max-height: 90vh; overflow-y: auto; overflow-x: hidden;">
            <div class="modal-close" onclick="closeRegionModal()">&times;</div>
            <h3 class="text-center" style="color: var(--color-green); margin-bottom: 10px;">Change Region</h3>
            <p class="text-center" style="font-size: 0.9rem; color: #777;">We ship authentic Kashmiri products worldwide.</p>
            <div class="country-grid">
                <div class="country-option selected" onclick="setRegion('IN', 'INR', '₹', 1)">
                    <img src="https://flagcdn.com/in.svg" alt="India">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">India</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">INR</span>
                </div>
                <div class="country-option" onclick="setRegion('US', 'USD', '$', 0.012)">
                    <img src="https://flagcdn.com/us.svg" alt="USA">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">USA</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">USD</span>
                </div>
                <div class="country-option" onclick="setRegion('GB', 'GBP', '£', 0.0095)">
                    <img src="https://flagcdn.com/gb.svg" alt="UK">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">UK</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">GBP</span>
                </div>
                <div class="country-option" onclick="setRegion('AE', 'AED', 'AED', 0.044)">
                    <img src="https://flagcdn.com/ae.svg" alt="UAE">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">UAE</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">AED</span>
                </div>
                <div class="country-option" onclick="setRegion('SG', 'SGD', 'S$', 0.016)">
                    <img src="https://flagcdn.com/sg.svg" alt="Singapore">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Singapore</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">SGD</span>
                </div>
                <div class="country-option" onclick="setRegion('SA', 'SAR', '﷼', 0.045)">
                    <img src="https://flagcdn.com/sa.svg" alt="Saudi Arabia">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Saudi Arabia</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">SAR</span>
                </div>
                <div class="country-option" onclick="setRegion('QA', 'QAR', '﷼', 0.044)">
                    <img src="https://flagcdn.com/qa.svg" alt="Qatar">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Qatar</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">QAR</span>
                </div>
                <div class="country-option" onclick="setRegion('KW', 'KWD', 'KD', 0.0036)">
                    <img src="https://flagcdn.com/kw.svg" alt="Kuwait">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Kuwait</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">KWD</span>
                </div>
                <div class="country-option" onclick="setRegion('BH', 'BHD', 'BD', 0.0045)">
                    <img src="https://flagcdn.com/bh.svg" alt="Bahrain">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Bahrain</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">BHD</span>
                </div>
                <div class="country-option" onclick="setRegion('OM', 'OMR', '﷼', 0.0046)">
                    <img src="https://flagcdn.com/om.svg" alt="Oman">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Oman</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">OMR</span>
                </div>
                <div class="country-option" onclick="setRegion('LK', 'LKR', 'Rs', 3.5)">
                    <img src="https://flagcdn.com/lk.svg" alt="Sri Lanka">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Sri Lanka</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">LKR</span>
                </div>
                <div class="country-option" onclick="setRegion('AU', 'AUD', 'A$', 0.018)">
                    <img src="https://flagcdn.com/au.svg" alt="Australia">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Australia</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">AUD</span>
                </div>
                <div class="country-option" onclick="setRegion('DE', 'EUR', '€', 0.011)">
                    <img src="https://flagcdn.com/de.svg" alt="Germany">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Germany</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">EUR</span>
                </div>
                <div class="country-option" onclick="setRegion('FR', 'EUR', '€', 0.011)">
                    <img src="https://flagcdn.com/fr.svg" alt="France">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">France</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">EUR</span>
                </div>
                <div class="country-option" onclick="setRegion('IT', 'EUR', '€', 0.011)">
                    <img src="https://flagcdn.com/it.svg" alt="Italy">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Italy</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">EUR</span>
                </div>
                <div class="country-option" onclick="setRegion('ES', 'EUR', '€', 0.011)">
                    <img src="https://flagcdn.com/es.svg" alt="Spain">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Spain</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">EUR</span>
                </div>
                <div class="country-option" onclick="setRegion('NL', 'EUR', '€', 0.011)">
                    <img src="https://flagcdn.com/nl.svg" alt="Netherlands">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Netherlands</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">EUR</span>
                </div>
                <div class="country-option" onclick="setRegion('SE', 'EUR', '€', 0.011)">
                    <img src="https://flagcdn.com/se.svg" alt="Sweden">
                    <div><div class="country-name" style="font-weight: 600; font-size: 0.9rem;">Sweden</div></div>
                    <span class="currency-code" style="margin-left: auto; font-size: 0.8rem;">EUR</span>
                </div>
            </div>
            <button class="btn btn-primary" style="width: 100%;" onclick="closeRegionModal()">Update Preferences</button>
            <div class="region-note">*Prices include estimated import markup. Local customs duties may apply upon delivery.</div>
        </div>
    </div>

    <!-- Tracking Modal -->
    <div class="modal-overlay" id="trackingModal">
        <div class="region-modal" style="width: 500px;">
            <div class="modal-close" onclick="closeTrackingModal()">&times;</div>
            <h3 class="text-center" style="color: var(--color-green); margin-bottom: 10px;">Track Your Shipment</h3>
            <p class="text-center" style="font-size: 0.9rem; color: #777; margin-bottom: 25px;">Enter your Tracking ID to see real-time status.</p>
            
            <div class="track-input-group">
                <input type="text" id="trackingIdInput" class="track-input" placeholder="GF-SHIP-2015">
                <button class="track-btn-square" onclick="trackOrder()">Track</button>
            </div>
            
            <div id="trackingResult" class="tracking-result-container">
                <div class="tracking-meta">
                    <div>
                        <span style="display:block; color:#888; font-size:0.75rem;">ESTIMATED DELIVERY</span>
                        <strong style="color:var(--color-green);">Mon, 29 Jan 2025</strong>
                    </div>
                    <div style="text-align:right;">
                        <span style="display:block; color:#888; font-size:0.75rem;">COURIER</span>
                        <strong style="color:var(--color-text);">Blue Dart Express</strong>
                    </div>
                </div>

                <div class="timeline" id="trackingTimeline"></div>
            </div>
        </div>
    </div>

    <!-- Admin Modal -->
    <div class="modal-overlay" id="adminModal">
        <div class="region-modal" style="width: 600px;">
            <div class="modal-close" onclick="closeAdminModal()">&times;</div>
            <h3 class="text-center" style="color: var(--color-green); margin-bottom: 20px;">Admin: Manage Stores</h3>
            <form id="addStoreForm" onsubmit="adminAddStore(event)" style="background: #F8F5F2; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
                <h4 style="font-size: 1rem; margin-bottom: 10px;">Add New Distributor</h4>
                <div class="admin-form-group"><label>Store/Distributor Name</label><input type="text" id="adminName" class="admin-input" required></div>
                <div style="display: flex; gap: 10px;">
                    <div class="admin-form-group" style="flex: 1;"><label>Pincode</label><input type="text" id="adminPincode" class="admin-input" required pattern="[0-9]{6}" title="6 Digit Pincode"></div>
                    <div class="admin-form-group" style="flex: 1;"><label>Phone</label><input type="text" id="adminPhone" class="admin-input" required></div>
                </div>
                <div class="admin-form-group"><label>Full Address</label><input type="text" id="adminAddress" class="admin-input" required></div>
                <div style="display: flex; gap: 10px;">
                    <div class="admin-form-group" style="flex: 1;"><label>Latitude</label><input type="number" step="any" id="adminLat" class="admin-input" required></div>
                    <div class="admin-form-group" style="flex: 1;"><label>Longitude</label><input type="number" step="any" id="adminLng" class="admin-input" required></div>
                </div>
                <div class="admin-form-group"><label>Google Maps URL (Optional)</label><input type="text" id="adminMapUrl" class="admin-input"></div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px;">Save Store</button>
            </form>
            <h4 style="font-size: 1rem; margin-bottom: 10px;">Existing Stores</h4>
            <div id="adminStoreList" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;"></div>
            <button onclick="resetDefaultStores()" style="margin-top: 15px; font-size: 0.8rem; text-decoration: underline; background: none; color: #888; cursor: pointer;">Reset to Demo Data</button>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?= asset_url('js/new-main.js'); ?>?v=<?= time(); ?>"></script>
    
    <script>
    let searchTimeout = null;
    let currentSearchQuery = '';

    function toggleSearch() {
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        
        if (searchForm.style.display === 'none' || searchForm.style.display === '') {
            searchForm.style.display = 'block';
            searchInput.focus();
        } else {
            searchForm.style.display = 'none';
            hideAutocomplete();
        }
    }

    function hideAutocomplete() {
        const autocomplete = document.getElementById('searchAutocomplete');
        if (autocomplete) {
            autocomplete.style.display = 'none';
        }
    }

    function showAutocomplete() {
        const autocomplete = document.getElementById('searchAutocomplete');
        if (autocomplete) {
            autocomplete.style.display = 'block';
        }
    }

    // Live search autocomplete
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const autocompleteDiv = document.getElementById('searchAutocomplete');
        
        if (!searchInput || !autocompleteDiv) return;

        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            currentSearchQuery = query;

            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            // Hide autocomplete if query is empty
            if (query.length < 1) {
                hideAutocomplete();
                return;
            }

            // Debounce search requests (wait 300ms after user stops typing)
            searchTimeout = setTimeout(function() {
                fetchSearchSuggestions(query);
            }, 300);
        });

        // Handle keyboard navigation
        searchInput.addEventListener('keydown', function(e) {
            const items = autocompleteDiv.querySelectorAll('.autocomplete-item');
            const activeItem = autocompleteDiv.querySelector('.autocomplete-item.active');
            let currentIndex = -1;

            if (activeItem) {
                currentIndex = Array.from(items).indexOf(activeItem);
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
                setActiveItem(items, nextIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
                setActiveItem(items, prevIndex);
            } else if (e.key === 'Enter' && activeItem) {
                e.preventDefault();
                activeItem.click();
            } else if (e.key === 'Escape') {
                hideAutocomplete();
            }
        });
    });

    function setActiveItem(items, index) {
        items.forEach((item, i) => {
            if (i === index) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    function fetchSearchSuggestions(query) {
        const autocompleteDiv = document.getElementById('searchAutocomplete');
        
        // Show loading state
        autocompleteDiv.innerHTML = '<div style="padding: 15px; text-align: center; color: #666;"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        showAutocomplete();

        // Fetch suggestions from API
        fetch('<?= base_url('api/search_autocomplete.php'); ?>?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    displaySearchSuggestions(data.products);
                } else {
                    autocompleteDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #999;"><i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.3;"></i>No products found</div>';
                    showAutocomplete();
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                autocompleteDiv.innerHTML = '<div style="padding: 15px; text-align: center; color: #dc2626;"><i class="fas fa-exclamation-circle"></i> Search failed</div>';
                showAutocomplete();
            });
    }

    function displaySearchSuggestions(products) {
        const autocompleteDiv = document.getElementById('searchAutocomplete');
        const baseUrl = '<?= base_url(''); ?>';
        
        let html = '';
        products.forEach(product => {
            const imageUrl = baseUrl + 'assets/images/products/' + product.image;
            const highlightedName = highlightMatch(product.name, currentSearchQuery);
            
            html += `
                <a href="${product.url}" class="autocomplete-item" style="display: flex; align-items: center; padding: 12px 16px; text-decoration: none; color: inherit; border-bottom: 1px solid #f0f0f0; transition: background 0.2s ease;">
                    <img src="${imageUrl}" alt="${product.name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; margin-right: 12px;" onerror="this.src='${baseUrl}assets/images/placeholder.jpg'">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: #1A3C34; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${highlightedName}</div>
                        <div style="font-size: 0.85rem; color: #666;">${product.category}</div>
                    </div>
                    <div style="font-weight: 700; color: #C5A089; margin-left: 12px; white-space: nowrap;">₹${product.price}</div>
                </a>
            `;
        });
        
        autocompleteDiv.innerHTML = html;
        showAutocomplete();

        // Add hover effects
        const items = autocompleteDiv.querySelectorAll('.autocomplete-item');
        items.forEach(item => {
            item.addEventListener('mouseenter', function() {
                items.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                this.style.background = '#f8f9fa';
            });
            item.addEventListener('mouseleave', function() {
                this.style.background = 'white';
            });
        });
    }

    function highlightMatch(text, query) {
        if (!query) return text;
        
        const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return text.replace(regex, '<span style="background: #fef3c7; font-weight: 700;">$1</span>');
    }

    // Close search when clicking outside
    document.addEventListener('click', function(event) {
        const searchContainer = document.getElementById('searchContainer');
        const searchForm = document.getElementById('searchForm');
        
        if (!searchContainer.contains(event.target)) {
            searchForm.style.display = 'none';
            hideAutocomplete();
        }
    });
    </script>
    
    <!-- Chatbot Styles & Scripts -->
    <link rel="stylesheet" href="<?= asset_url('css/chatbot.css'); ?>?v=<?= time(); ?>">
    <script src="<?= asset_url('js/chatbot.js'); ?>?v=<?= time(); ?>"></script>
    
    <!-- Region Auto-Detection Banner -->
    <?php include __DIR__ . '/region_banner.php'; ?>

    <!-- Language Suggestion Banner (first visit only) -->
    <?php include __DIR__ . '/language_banner.php'; ?>

    <!-- Analytics Click Tracking -->
    <script>
    function trackClick(productId, source) {
        // Immediate tracking before navigation
        const data = JSON.stringify({ product_id: productId, source: source });
        const url = '<?= base_url('api/track_product_click.php'); ?>';
        
        // Use sendBeacon for reliability during page navigation
        if (navigator.sendBeacon) {
            const blob = new Blob([data], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
        } else if (typeof fetch !== 'undefined') {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: data,
                keepalive: true
            }).catch(() => {});
        }
    }
    </script>
</body>
</html>
