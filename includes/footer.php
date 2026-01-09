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
                        <li><a href="#">Gifting & Hampers</a></li>
                        <li><a href="#">Offers</a></li>
                        <li><a href="#locator">Find Gilaf Stores</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h4>SUPPORT</h4>
                    <ul>
                        <li><a href="#" onclick="openTrackingModal(); return false;">Track Order</a></li>
                        <li><a href="#">Shipping & Logistics</a></li>
                        <li><a href="#verification">Authenticity Check</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Become a Distributor</a></li>
                        <li><a href="#">FAQs</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h4>POLICIES & LEGAL</h4>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">Shipping Policy</a></li>
                        <li><a href="#">Refund & Return Policy</a></li>
                        <li><a href="#">Order Cancellation Policy</a></li>
                        <li><a href="#">Payment Policy</a></li>
                        <li><a href="#">Disclaimer</a></li>
                    </ul>
                </div>
            </div>

            <div class="shipping-partners-section">
                <div class="ship-group">
                    <h5 class="shipping-title"><i class="fas fa-globe-americas"></i> International Shipping</h5>
                    <p class="shipping-subtitle">We Ship Worldwide</p>
                    <div class="partner-badges-grid">
                        <span class="partner-btn">DHL EXPRESS</span>
                        <span class="partner-btn">FEDEX</span>
                        <span class="partner-btn">DP WORLD</span>
                    </div>
                </div>
                <div class="ship-group">
                    <h5 class="shipping-title"><i class="fas fa-truck"></i> Domestic Shipping (India)</h5>
                    <p class="shipping-subtitle">Free Domestic Shipping</p>
                    <div class="partner-badges-grid">
                        <span class="partner-btn">INDIA POST</span>
                        <span class="partner-btn">DELHIVERY</span>
                        <span class="partner-btn">BLUE DART</span>
                        <span class="partner-btn">DTDC</span>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <span>&copy; <?= date('Y'); ?> Gilaf Foods & Spices. All Rights Reserved.</span>
                <span style="display: flex; gap: 10px; font-size: 1.2rem;">
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-paypal"></i>
                    <i class="fab fa-cc-apple-pay"></i>
                </span>
            </div>
        </div>
    </footer>

    <!-- Login Modal -->
    <div class="modal-overlay" id="loginModal">
        <div class="login-modal-premium">
            <div class="modal-close-premium" onclick="closeLoginModal()">&times;</div>
            
            <div class="login-header-premium">
                <div class="login-icon-premium">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3>Welcome Back</h3>
                <p>Sign in to access your account</p>
            </div>
            
            <div class="login-tabs-premium">
                <div class="login-tab-premium active" id="tab-cust" onclick="switchLoginTab('cust')">
                    <i class="fas fa-user"></i>
                    <span>Customer</span>
                </div>
                <div class="login-tab-premium" id="tab-dist" onclick="switchLoginTab('dist')">
                    <i class="fas fa-briefcase"></i>
                    <span>Distributor</span>
                </div>
            </div>
            
            <div id="form-cust" class="form-section active">
                <form action="<?= base_url('user/login.php'); ?>" method="post" class="login-form-premium">
                    <div class="form-group-premium">
                        <label class="form-label-premium">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <input type="email" name="email" class="form-input-premium" placeholder="hello@example.com" required>
                    </div>
                    <div class="form-group-premium">
                        <label class="form-label-premium">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <input type="password" name="password" class="form-input-premium" placeholder="••••••••" required>
                    </div>
                    <div class="form-options-premium">
                        <label class="checkbox-premium">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="<?= base_url('user/forgot-password.php'); ?>" class="link-premium">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn-premium btn-primary-premium">
                        <span>Sign In</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <div class="login-footer-premium">
                    <p>New to Gilaf? <a href="<?= base_url('user/signup.php'); ?>" class="link-premium-bold">Create an Account</a></p>
                </div>
            </div>
            
            <div id="form-dist" class="form-section">
                <div class="info-banner-premium">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Distributor Portal</strong>
                        <p>Access bulk pricing and wholesale management</p>
                    </div>
                </div>
                <form action="<?= base_url('admin/admin_login.php'); ?>" method="post" class="login-form-premium">
                    <div class="form-group-premium">
                        <label class="form-label-premium">
                            <i class="fas fa-id-card"></i>
                            Distributor ID / Email
                        </label>
                        <input type="text" name="identifier" class="form-input-premium" placeholder="DST-XXXX or email@example.com" required>
                    </div>
                    <div class="form-group-premium">
                        <label class="form-label-premium">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <input type="password" name="password" class="form-input-premium" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn-premium btn-primary-premium">
                        <span>Access Portal</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <div class="login-footer-premium" style="display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: wrap;">
                    <span style="font-size: 0.85rem; color: var(--color-text-light);">Apply to become a distributor?</span>
                    <a href="<?= base_url('apply-distributor.php'); ?>" class="btn-apply-small">
                        Apply
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Region Modal -->
    <div class="modal-overlay" id="regionModal">
        <div class="region-modal">
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

    <!-- Region Auto-Detection Banner -->
    <?php include __DIR__ . '/region_banner.php'; ?>

    <!-- Scripts -->
    <script src="<?= asset_url('js/new-main.js'); ?>?v=<?= time(); ?>"></script>
</body>
</html>
