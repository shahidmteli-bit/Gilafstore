/**
 * ═══════════════════════════════════════════════════════════════════
 * Gilaf Store — Onboarding Tour Engine
 * Premium guided walkthrough for first-time visitors
 * Self-contained, isolated, no global conflicts
 * ═══════════════════════════════════════════════════════════════════
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'gilaf_tour_completed';
    var TOUR_VERSION = '2';
    var currentStep = -1;
    var tourActive = false;
    var overlayEl = null;
    var tooltipEl = null;
    var welcomeEl = null;
    var completeEl = null;
    var prevTargetEl = null;
    var prevElevatedEl = null;

    // ─── Tour Steps Definition ───────────────────────────────────────
    var steps = [
        {
            type: 'welcome'
        },
        {
            selector: '#searchContainer',
            title: 'Search Products',
            description: 'Search your favorite products instantly from here.',
            position: 'bottom'
        },
        {
            selector: '.desktop-nav .dropdown:first-of-type',
            selectorMobile: '.menu-toggle',
            title: 'Browse Categories',
            description: 'Browse products category-wise for faster and easy shopping.',
            position: 'bottom'
        },
        {
            selector: '#rwHeaderChip',
            selectorFallback: '.user-actions a[href*="wallet.php"]',
            title: 'Wallet & Rewards',
            description: 'Check your cashback, rewards, and wallet balance here.',
            position: 'bottom',
            optional: true
        },
        {
            selector: '.user-actions a[href*="cart.php"]',
            title: 'Your Cart',
            description: 'Your selected products appear here before checkout.',
            position: 'bottom'
        },
        {
            selector: '#promoCodeBanner',
            selectorFallback: '.top-bar .container',
            title: 'Promo Codes',
            description: 'Apply promo codes during checkout to unlock exciting discounts and rewards.',
            position: 'bottom',
            optional: true
        },
        {
            selector: '.desktop-nav .dropdown:nth-of-type(2)',
            selectorMobile: '.menu-toggle',
            title: 'Track Your Orders',
            description: 'Track your orders and get real-time updates on delivery status.',
            position: 'bottom'
        },
        {
            selector: '.user-actions a[href*="profile.php"]',
            selectorFallback: '.user-actions a[href*="login.php"]',
            title: 'Profile Section',
            description: 'Manage your account, addresses, saved items and order history from here.',
            position: 'bottom'
        },
        {
            selector: '.user-actions a[title="Support"]',
            title: 'Help & Chatbot',
            description: 'Need help? Our support chatbot is always here to assist you.',
            position: 'bottom'
        },
        {
            type: 'complete'
        }
    ];

    // ─── Utility Functions ───────────────────────────────────────────
    function isMobile() {
        return window.innerWidth <= 768;
    }

    function getTargetEl(step) {
        if (step.type === 'welcome' || step.type === 'complete') return null;
        var sel = isMobile() && step.selectorMobile ? step.selectorMobile : step.selector;
        var el = document.querySelector(sel);
        if (!el && step.selectorFallback) {
            el = document.querySelector(step.selectorFallback);
        }
        return el;
    }

    function getStacking(el) {
        // Walk up to find the nearest positioned ancestor with z-index (stacking context)
        var header = document.querySelector('.main-header');
        var topBar = document.querySelector('.top-bar');
        if (header && header.contains(el)) return header;
        if (topBar && topBar.contains(el)) return topBar;
        return null;
    }

    function cleanPrevStep() {
        if (prevTargetEl) {
            prevTargetEl.classList.remove('gf-tour-target', 'gf-tour-target-pulse');
            prevTargetEl = null;
        }
        if (prevElevatedEl) {
            prevElevatedEl.classList.remove('gf-tour-elevated');
            prevElevatedEl = null;
        }
    }

    // ─── DOM Creation ────────────────────────────────────────────────
    function createTourDOM() {
        // Overlay
        overlayEl = document.createElement('div');
        overlayEl.className = 'gf-tour-overlay';
        overlayEl.setAttribute('aria-hidden', 'true');
        overlayEl.addEventListener('click', function (e) {
            if (e.target === overlayEl) endTour();
        });

        // Tooltip
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'gf-tour-tooltip';
        tooltipEl.setAttribute('role', 'dialog');
        tooltipEl.setAttribute('aria-label', 'Tour step');

        // Welcome modal
        welcomeEl = document.createElement('div');
        welcomeEl.className = 'gf-tour-welcome';
        welcomeEl.setAttribute('role', 'dialog');
        welcomeEl.setAttribute('aria-label', 'Welcome');
        welcomeEl.innerHTML =
            '<div class="gf-tour-welcome-icon">' +
                '<img src="/assets/icons/icon-96x96.png" alt="Gilaf" onerror="this.parentElement.innerHTML=\'🛍️\'">' +
            '</div>' +
            '<h2>Welcome to Gilaf Store! 👋</h2>' +
            '<p>Let us show you around. We\'ll guide you through the important features.</p>' +
            '<button class="gf-tour-welcome-start" id="gfTourStartBtn">Start Tour</button>' +
            '<button class="gf-tour-welcome-skip" id="gfTourSkipWelcome">Skip Tour</button>';

        // Completion modal
        completeEl = document.createElement('div');
        completeEl.className = 'gf-tour-complete';
        completeEl.setAttribute('role', 'dialog');
        completeEl.setAttribute('aria-label', 'Tour complete');
        completeEl.innerHTML =
            '<h2>You\'re All Set! 🎉</h2>' +
            '<p>Now you know your way around Gilaf Store. Start exploring and enjoy premium products, rewards and cashback.</p>' +
            '<button class="gf-tour-complete-btn" id="gfTourFinishBtn">Start Shopping</button>' +
            '<div class="gf-tour-benefits">' +
                '<div class="gf-tour-benefit"><div class="gf-tour-benefit-icon"><i class="fas fa-gem"></i></div><span>Premium Quality Products</span></div>' +
                '<div class="gf-tour-benefit"><div class="gf-tour-benefit-icon"><i class="fas fa-gift"></i></div><span>Exciting Rewards</span></div>' +
                '<div class="gf-tour-benefit"><div class="gf-tour-benefit-icon"><i class="fas fa-shield-alt"></i></div><span>Secure Payments</span></div>' +
                '<div class="gf-tour-benefit"><div class="gf-tour-benefit-icon"><i class="fas fa-shipping-fast"></i></div><span>Fast & Reliable Delivery</span></div>' +
                '<div class="gf-tour-benefit"><div class="gf-tour-benefit-icon"><i class="fas fa-headset"></i></div><span>Dedicated Support</span></div>' +
            '</div>';

        document.body.appendChild(overlayEl);
        document.body.appendChild(tooltipEl);
        document.body.appendChild(welcomeEl);
        document.body.appendChild(completeEl);
    }

    // ─── Position Tooltip ────────────────────────────────────────────
    function positionTooltip(targetEl, pos) {
        var rect = targetEl.getBoundingClientRect();
        var ttWidth = tooltipEl.offsetWidth;
        var ttHeight = tooltipEl.offsetHeight;
        var margin = 14;
        var arrowHtml = '';
        var top, left;

        // Remove inline positioning
        tooltipEl.style.top = '';
        tooltipEl.style.left = '';
        tooltipEl.style.right = '';

        // Calculate position
        if (pos === 'bottom') {
            top = rect.bottom + margin;
            left = rect.left + rect.width / 2 - ttWidth / 2;
            arrowHtml = '<div class="gf-tour-arrow gf-tour-arrow-top"></div>';
        } else if (pos === 'top') {
            top = rect.top - ttHeight - margin;
            left = rect.left + rect.width / 2 - ttWidth / 2;
            arrowHtml = '<div class="gf-tour-arrow gf-tour-arrow-bottom"></div>';
        } else if (pos === 'left') {
            top = rect.top + rect.height / 2 - ttHeight / 2;
            left = rect.left - ttWidth - margin;
            arrowHtml = '<div class="gf-tour-arrow gf-tour-arrow-right"></div>';
        } else if (pos === 'right') {
            top = rect.top + rect.height / 2 - ttHeight / 2;
            left = rect.right + margin;
            arrowHtml = '<div class="gf-tour-arrow gf-tour-arrow-left"></div>';
        }

        // Boundary checks
        if (left < 12) left = 12;
        if (left + ttWidth > window.innerWidth - 12) left = window.innerWidth - ttWidth - 12;
        if (top < 12) {
            top = rect.bottom + margin;
            arrowHtml = '<div class="gf-tour-arrow gf-tour-arrow-top"></div>';
        }
        if (top + ttHeight > window.innerHeight - 12) {
            top = rect.top - ttHeight - margin;
            arrowHtml = '<div class="gf-tour-arrow gf-tour-arrow-bottom"></div>';
        }

        tooltipEl.style.top = top + 'px';
        tooltipEl.style.left = left + 'px';

        // Update arrow
        var existingArrow = tooltipEl.querySelector('.gf-tour-arrow');
        if (existingArrow) existingArrow.remove();
        tooltipEl.insertAdjacentHTML('beforeend', arrowHtml);

        // Adjust arrow horizontal position to point at target center
        var arrow = tooltipEl.querySelector('.gf-tour-arrow');
        if (arrow && (pos === 'bottom' || pos === 'top')) {
            var targetCenter = rect.left + rect.width / 2;
            var arrowLeft = targetCenter - left;
            if (arrowLeft > 20 && arrowLeft < ttWidth - 20) {
                arrow.style.left = arrowLeft + 'px';
                arrow.style.marginLeft = '-7px';
            }
        }
    }

    // ─── Show Step ───────────────────────────────────────────────────
    function showStep(index) {
        if (index < 0 || index >= steps.length) return;

        // Clean previous highlights
        cleanPrevStep();

        var step = steps[index];
        currentStep = index;

        // Hide all modals/tooltips first
        tooltipEl.classList.remove('gf-tour-active');
        welcomeEl.classList.remove('gf-tour-active');
        completeEl.classList.remove('gf-tour-active');

        // Handle welcome modal
        if (step.type === 'welcome') {
            welcomeEl.classList.add('gf-tour-active');
            return;
        }

        // Handle completion modal
        if (step.type === 'complete') {
            completeEl.classList.add('gf-tour-active');
            return;
        }

        // Find target element
        var targetEl = getTargetEl(step);

        // If target not found and step is optional, skip to next
        if (!targetEl) {
            if (step.optional) {
                showStep(index + 1);
                return;
            }
            // If not optional but not found, skip it anyway
            showStep(index + 1);
            return;
        }

        // Scroll target into view (smooth, center)
        var rect = targetEl.getBoundingClientRect();
        var inView = rect.top >= 0 && rect.bottom <= window.innerHeight;
        if (!inView) {
            targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Elevate stacking context parent
        var stackParent = getStacking(targetEl);
        if (stackParent) {
            stackParent.classList.add('gf-tour-elevated');
            prevElevatedEl = stackParent;
        }

        // Highlight target
        targetEl.classList.add('gf-tour-target', 'gf-tour-target-pulse');
        prevTargetEl = targetEl;

        // Build tooltip content
        var totalSteps = steps.filter(function (s) { return s.type !== 'welcome' && s.type !== 'complete'; }).length;
        var displayIndex = steps.slice(0, index).filter(function (s) { return s.type !== 'welcome' && s.type !== 'complete'; }).length + 1;
        var isLast = (index === steps.length - 2); // next is complete

        var html =
            '<div class="gf-tour-tooltip-title">' + escapeHtml(step.title) + '</div>' +
            '<div class="gf-tour-tooltip-desc">' + escapeHtml(step.description) + '</div>' +
            '<div class="gf-tour-footer">' +
                '<span class="gf-tour-step-indicator">' + displayIndex + ' of ' + totalSteps + '</span>' +
                '<div class="gf-tour-btns">';

        if (index > 1) {
            html += '<button class="gf-tour-btn-prev" id="gfTourPrev">&larr;</button>';
        }

        if (isLast) {
            html += '<button class="gf-tour-btn-finish" id="gfTourNext">Finish <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></button>';
        } else {
            html += '<button class="gf-tour-btn-next" id="gfTourNext">Next <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>';
        }

        html += '</div></div>';

        tooltipEl.innerHTML = html;

        // Position after a brief delay to let DOM settle
        setTimeout(function () {
            positionTooltip(targetEl, step.position || 'bottom');
            tooltipEl.classList.add('gf-tour-active');
        }, inView ? 50 : 400);

        // Attach button listeners
        setTimeout(function () {
            var nextBtn = document.getElementById('gfTourNext');
            var prevBtn = document.getElementById('gfTourPrev');
            if (nextBtn) nextBtn.addEventListener('click', nextStep);
            if (prevBtn) prevBtn.addEventListener('click', prevStep);
        }, 60);
    }

    // ─── Navigation ──────────────────────────────────────────────────
    function nextStep() {
        if (currentStep < steps.length - 1) {
            showStep(currentStep + 1);
        } else {
            endTour();
        }
    }

    function prevStep() {
        if (currentStep > 1) {
            // Find previous non-optional step that has a target
            var prev = currentStep - 1;
            while (prev > 0 && steps[prev].optional && !getTargetEl(steps[prev])) {
                prev--;
            }
            showStep(prev);
        }
    }

    // ─── Start Tour ──────────────────────────────────────────────────
    function startTour() {
        if (tourActive) return;
        tourActive = true;
        currentStep = -1;

        if (!overlayEl) createTourDOM();

        // Show overlay
        overlayEl.classList.add('gf-tour-active');
        document.body.style.overflow = 'hidden';

        // Show welcome step
        showStep(0);

        // Attach welcome button listeners
        setTimeout(function () {
            var startBtn = document.getElementById('gfTourStartBtn');
            var skipBtn = document.getElementById('gfTourSkipWelcome');
            if (startBtn) startBtn.addEventListener('click', function () { showStep(1); });
            if (skipBtn) skipBtn.addEventListener('click', endTour);
        }, 50);

        // Keyboard handler
        document.addEventListener('keydown', handleKeyboard);
    }

    // ─── End Tour ────────────────────────────────────────────────────
    function endTour() {
        tourActive = false;
        cleanPrevStep();

        if (overlayEl) overlayEl.classList.remove('gf-tour-active');
        if (tooltipEl) tooltipEl.classList.remove('gf-tour-active');
        if (welcomeEl) welcomeEl.classList.remove('gf-tour-active');
        if (completeEl) completeEl.classList.remove('gf-tour-active');

        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleKeyboard);

        // Mark as completed
        try {
            localStorage.setItem(STORAGE_KEY, TOUR_VERSION);
        } catch (e) { /* storage may be unavailable */ }
    }

    // ─── Keyboard Handler ────────────────────────────────────────────
    function handleKeyboard(e) {
        if (!tourActive) return;
        if (e.key === 'Escape') {
            endTour();
        } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
            if (currentStep === 0) {
                showStep(1);
            } else if (steps[currentStep] && steps[currentStep].type === 'complete') {
                endTour();
            } else {
                nextStep();
            }
        } else if (e.key === 'ArrowLeft') {
            prevStep();
        }
    }

    // ─── Escape HTML ─────────────────────────────────────────────────
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ─── Auto-start Logic ────────────────────────────────────────────
    function init() {
        console.log('[GilafTour] Initializing onboarding tour...');

        // Check URL param for forced restart
        var params = new URLSearchParams(window.location.search);
        if (params.get('tour') === 'restart') {
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
            // Clean URL
            var url = new URL(window.location.href);
            url.searchParams.delete('tour');
            window.history.replaceState({}, '', url.toString());
        }

        // Check if already completed
        var completed = false;
        try {
            completed = localStorage.getItem(STORAGE_KEY) === TOUR_VERSION;
        } catch (e) {}

        console.log('[GilafTour] Completed status:', completed);

        if (!completed) {
            // Auto-start for first-time visitors after page settles
            console.log('[GilafTour] Will auto-start in 2 seconds...');
            setTimeout(startTour, 2000);
        }
    }

    // ─── Expose Restart API ──────────────────────────────────────────
    window.GilafTour = {
        start: function () {
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
            startTour();
        },
        restart: function () {
            try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
            startTour();
        }
    };

    // ─── Initialize on DOM Ready ─────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ─── Completion Button Handler (delegated) ───────────────────────
    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'gfTourFinishBtn') {
            endTour();
        }
    });

    // ─── Handle Resize (reposition tooltip) ──────────────────────────
    var resizeTimeout;
    window.addEventListener('resize', function () {
        if (!tourActive || currentStep < 1) return;
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
            var step = steps[currentStep];
            if (step && step.type !== 'welcome' && step.type !== 'complete') {
                var targetEl = getTargetEl(step);
                if (targetEl) {
                    positionTooltip(targetEl, step.position || 'bottom');
                }
            }
        }, 150);
    });

})();
