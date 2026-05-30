/**
 * ADVANCED PROMOTIONAL SYSTEM - JAVASCRIPT
 * Handles exit intent, countdown timers, analytics tracking, and interactive features
 */

// Exit Intent Detection
let exitIntentShown = false;
let mouseY = 0;

function initExitIntent() {
    const popup = document.getElementById('exitIntentPopup');
    if (!popup) return;
    
    const popupId = popup.dataset.popupId;
    
    // Track mouse movement
    document.addEventListener('mousemove', (e) => {
        mouseY = e.clientY;
    });
    
    // Detect exit intent (mouse leaving viewport from top)
    document.addEventListener('mouseleave', (e) => {
        if (exitIntentShown) return;
        if (e.clientY < 10 && mouseY < 10) {
            showExitIntent();
        }
    });
    
    // Mobile: detect back button or tab close
    if (isMobileDevice()) {
        let startTime = Date.now();
        window.addEventListener('beforeunload', () => {
            if (Date.now() - startTime > 5000 && !exitIntentShown) {
                showExitIntent();
            }
        });
    }
}

function showExitIntent() {
    const popup = document.getElementById('exitIntentPopup');
    if (!popup || exitIntentShown) return;
    
    popup.classList.add('active');
    exitIntentShown = true;
    
    const popupId = popup.dataset.popupId;
    
    // Track impression
    trackPromoEvent(popupId, 'exit_intent', 'view');
    
    // Mark as shown
    fetch('/api/mark_exit_intent_shown.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ popup_id: popupId })
    });
}

function closeExitIntent() {
    const popup = document.getElementById('exitIntentPopup');
    if (popup) {
        popup.classList.remove('active');
    }
}

function trackExitIntentConversion(popupId) {
    trackPromoEvent(popupId, 'exit_intent', 'conversion');
}

// Copy Coupon Code
function copyCouponCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '✓ Copied!';
        btn.style.background = '#10B981';
        
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.background = '';
        }, 2000);
    });
}

// Countdown Timer
function initCountdownTimers() {
    const countdowns = document.querySelectorAll('.promo-countdown, .banner-countdown');
    
    countdowns.forEach(countdown => {
        const endTime = parseInt(countdown.dataset.end);
        if (!endTime) return;
        
        updateCountdown(countdown, endTime);
        setInterval(() => updateCountdown(countdown, endTime), 1000);
    });
}

function updateCountdown(element, endTime) {
    const now = Math.floor(Date.now() / 1000);
    const remaining = endTime - now;
    
    if (remaining <= 0) {
        element.textContent = 'Offer Ended';
        return;
    }
    
    const days = Math.floor(remaining / 86400);
    const hours = Math.floor((remaining % 86400) / 3600);
    const minutes = Math.floor((remaining % 3600) / 60);
    const seconds = remaining % 60;
    
    let text = '';
    if (days > 0) {
        text = `${days}d ${hours}h ${minutes}m`;
    } else if (hours > 0) {
        text = `${hours}h ${minutes}m ${seconds}s`;
    } else {
        text = `${minutes}m ${seconds}s`;
    }
    
    element.textContent = `⏰ ${text} left`;
}

// Promo Analytics Tracking
function trackPromoEvent(promoId, promoType, eventType) {
    fetch('/api/track_promo_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            promo_id: promoId,
            promo_type: promoType,
            event_type: eventType
        })
    }).catch(err => console.error('Error tracking promo event:', err));
}

// Track banner click
function trackBannerClick(bannerId) {
    trackPromoEvent(bannerId, 'homepage_banner', 'click');
}

// Track promo banner view
function trackPromoBannerViews() {
    const banners = document.querySelectorAll('.promo-banner[data-promo-id]');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.dataset.tracked) {
                const promoId = entry.target.dataset.promoId;
                trackPromoEvent(promoId, 'promotion', 'view');
                entry.target.dataset.tracked = 'true';
            }
        });
    }, { threshold: 0.5 });
    
    banners.forEach(banner => observer.observe(banner));
}

// Free Shipping Progress Bar
function updateFreeShippingProgress(cartTotal, threshold) {
    const progressBar = document.querySelector('.shipping-progress-fill');
    const progressText = document.querySelector('.shipping-progress-text');
    
    if (!progressBar || !progressText) return;
    
    const percentage = Math.min((cartTotal / threshold) * 100, 100);
    progressBar.style.width = percentage + '%';
    
    if (cartTotal >= threshold) {
        progressText.textContent = '🎉 You qualify for FREE SHIPPING!';
        progressText.style.color = '#10B981';
    } else {
        const remaining = threshold - cartTotal;
        progressText.textContent = `Add ₹${remaining.toFixed(2)} more for FREE SHIPPING`;
        progressText.style.color = '#6B7280';
    }
}

// Sticky Mobile Promo
function initStickyMobilePromo() {
    if (!isMobileDevice()) return;
    
    const stickyPromo = document.querySelector('.promo-sticky-mobile');
    if (!stickyPromo) return;
    
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 200) {
            if (currentScroll > lastScroll) {
                // Scrolling down - hide
                stickyPromo.style.transform = 'translateY(100%)';
            } else {
                // Scrolling up - show
                stickyPromo.style.transform = 'translateY(0)';
            }
        }
        
        lastScroll = currentScroll;
    });
}

// Detect Mobile Device
function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) 
        || window.innerWidth <= 768;
}

// Auto-apply coupon from URL parameter
function autoApplyCouponFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const coupon = urlParams.get('coupon');
    
    if (coupon) {
        const couponInput = document.getElementById('promo_code');
        if (couponInput) {
            couponInput.value = coupon;
            // Trigger apply if on cart/checkout page
            const applyBtn = document.querySelector('.apply-promo-btn');
            if (applyBtn) {
                setTimeout(() => applyBtn.click(), 500);
            }
        }
    }
}

// Signup Incentive Animation
function animateSignupIncentive() {
    const incentive = document.querySelector('.signup-incentive-banner');
    if (!incentive) return;
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'slideInLeft 0.5s ease-out';
            }
        });
    }, { threshold: 0.3 });
    
    observer.observe(incentive);
}

// Initialize all promotional features
document.addEventListener('DOMContentLoaded', () => {
    initExitIntent();
    initCountdownTimers();
    trackPromoBannerViews();
    initStickyMobilePromo();
    autoApplyCouponFromURL();
    animateSignupIncentive();
    
    // Close exit intent on overlay click
    const exitOverlay = document.getElementById('exitIntentPopup');
    if (exitOverlay) {
        exitOverlay.addEventListener('click', (e) => {
            if (e.target === exitOverlay) {
                closeExitIntent();
            }
        });
    }
    
    // ESC key to close exit intent
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeExitIntent();
        }
    });
});

// Product Page: Show urgency based on stock
function showStockUrgency(stock, threshold) {
    if (stock <= threshold && stock > 0) {
        const urgencyDiv = document.createElement('div');
        urgencyDiv.className = 'urgency-indicator';
        urgencyDiv.innerHTML = `<i class="fas fa-fire"></i> Only ${stock} left in stock - Order soon!`;
        
        const addToCartBtn = document.querySelector('.add-to-cart-btn');
        if (addToCartBtn && addToCartBtn.parentNode) {
            addToCartBtn.parentNode.insertBefore(urgencyDiv, addToCartBtn);
        }
    }
}

// Checkout: Show completion incentive
function showCheckoutIncentive() {
    const checkoutForm = document.querySelector('.checkout-form');
    if (!checkoutForm) return;
    
    const incentiveDiv = document.createElement('div');
    incentiveDiv.className = 'promo-banner promo-checkout';
    incentiveDiv.style.background = '#10B981';
    incentiveDiv.style.color = '#fff';
    incentiveDiv.innerHTML = `
        <div class="promo-content">
            <span class="promo-icon"><i class="fas fa-gift"></i></span>
            <span class="promo-message">Complete your order now to unlock exclusive member benefits!</span>
        </div>
    `;
    
    checkoutForm.insertBefore(incentiveDiv, checkoutForm.firstChild);
}

// Performance: Lazy load promo images
function lazyLoadPromoImages() {
    const images = document.querySelectorAll('.exit-intent-image img, .homepage-banner img');
    
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                }
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Call lazy load on init
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', lazyLoadPromoImages);
} else {
    lazyLoadPromoImages();
}
