        </div><!-- .sp-content -->
    </main><!-- .sp-main -->

    <!-- Floating Scan QR Button -->
    <?php if (($currentPage ?? '') !== 'scan_party'): ?>
    <a href="<?= sales_base_url('scan_party.php') ?>" class="sp-fab-qr">
        <i class="fas fa-qrcode"></i> Scan QR
    </a>
    <?php endif; ?>

    <!-- Overlay for mobile sidebar -->
    <div class="sp-overlay" id="spOverlay"></div>

    <!-- Flash Messages -->
    <?php if (!empty($_SESSION['sp_flash'])): ?>
    <div class="sp-toast <?= $_SESSION['sp_flash']['type'] ?>" id="spToast">
        <i class="fas fa-<?= $_SESSION['sp_flash']['type'] === 'success' ? 'check-circle' : ($_SESSION['sp_flash']['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
        <span><?= htmlspecialchars($_SESSION['sp_flash']['message']) ?></span>
        <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    </div>
    <?php unset($_SESSION['sp_flash']); endif; ?>

    <script>
    // Live Clock
    (function() {
        var cl = document.getElementById('spDashClock');
        if (cl) {
            setInterval(function() {
                var d = new Date();
                var h = d.getHours(), m = d.getMinutes(), s = d.getSeconds();
                var ap = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                cl.textContent = (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s) + ' ' + ap;
            }, 1000);
        }
    })();

    // Sidebar Toggle
    const sidebar = document.getElementById('spSidebar');
    const main = document.getElementById('spMain');
    const overlay = document.getElementById('spOverlay');
    const menuToggle = document.getElementById('spMenuToggle');
    const sidebarClose = document.getElementById('spSidebarClose');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    menuToggle.addEventListener('click', openSidebar);
    sidebarClose.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);

    // Auto-hide toast
    const toast = document.getElementById('spToast');
    if (toast) {
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 4000);
    }

    // ── Token-based Auth for WebView/APK ──
    // Automatically append _token to all links and forms so session persists
    (function() {
        var params = new URLSearchParams(window.location.search);
        var urlToken = params.get('_token');
        var token = localStorage.getItem('sales_auth_token');

        // Always prefer URL token (fresh from login redirect)
        if (urlToken) {
            token = urlToken;
            localStorage.setItem('sales_auth_token', token);
            // Clean up URL (remove _token and _store_token params)
            if (params.get('_store_token')) {
                params.delete('_token');
                params.delete('_store_token');
                var clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, '', clean);
            }
        }
        if (!token) return;

        function appendToken(url) {
            if (!url || url.startsWith('javascript:') || url.startsWith('#') || url.startsWith('mailto:')) return url;
            try {
                var u = new URL(url, window.location.origin);
                if (u.origin !== window.location.origin) return url;
                if (u.searchParams.has('_token')) return url;
                u.searchParams.set('_token', token);
                return u.toString();
            } catch(e) { return url; }
        }

        // Intercept all link clicks
        document.addEventListener('click', function(e) {
            var a = e.target.closest('a');
            if (a && a.href && !a.hasAttribute('download')) {
                var newHref = appendToken(a.href);
                if (newHref !== a.href) {
                    e.preventDefault();
                    window.location.href = newHref;
                }
            }
        }, true);

        // Intercept all form submissions
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!form.querySelector('input[name="_token"]')) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = '_token';
                inp.value = token;
                form.appendChild(inp);
            }
        }, true);

        // Patch window.location assignments
        var origAssign = window.location.assign;
        if (origAssign) {
            window.location.assign = function(url) { origAssign.call(window.location, appendToken(url)); };
        }
    })();

    // ── Auto-Sync / Live Updates ──
    (function() {
        var SYNC_INTERVAL = 30000; // 30 seconds
        var syncData = null;
        var syncTimer = null;
        var currentPage = document.querySelector('[data-page]')?.dataset.page || '<?= $currentPage ?? '' ?>';

        function getSyncUrl() {
            var base = '<?= sales_base_url("api_sync.php") ?>';
            var token = localStorage.getItem('sales_auth_token');
            if (token) {
                base += (base.indexOf('?') > -1 ? '&' : '?') + '_token=' + token;
            }
            return base;
        }

        function checkForUpdates() {
            fetch(getSyncUrl(), { cache: 'no-store' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) return;

                    if (syncData) {
                        var hasChanges = false;
                        var changeMsg = '';

                        // Check if orders changed
                        if (data.orders_updated !== syncData.orders_updated || data.order_count !== syncData.order_count) {
                            hasChanges = true;
                            changeMsg = 'Orders updated';
                        }

                        // Check if parties changed
                        if (data.parties_updated !== syncData.parties_updated || data.party_count !== syncData.party_count) {
                            hasChanges = true;
                            changeMsg = changeMsg ? changeMsg + ' & Parties updated' : 'Parties updated';
                        }

                        // Check if app code updated (developer pushed changes)
                        if (data.app_version !== syncData.app_version) {
                            hasChanges = true;
                            changeMsg = 'App updated — refreshing...';
                            // Clear SW cache, then force SW update, then reload
                            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                                navigator.serviceWorker.controller.postMessage({ type: 'CLEAR_CACHE' });
                            }
                            if (navigator.serviceWorker) {
                                navigator.serviceWorker.getRegistration().then(function(reg) {
                                    if (reg) reg.update();
                                });
                            }
                            setTimeout(function() { location.reload(true); }, 1500);
                        }

                        if (hasChanges && changeMsg) {
                            showSyncNotification(changeMsg);
                            // Auto-refresh data on list pages
                            if (['orders', 'parties', 'dashboard'].indexOf(currentPage) > -1 && !changeMsg.includes('refreshing')) {
                                setTimeout(function() { location.reload(); }, 3000);
                            }
                        }
                    }

                    syncData = data;

                    // Update pending badge if exists
                    var pendingBadge = document.getElementById('spPendingCount');
                    if (pendingBadge && data.pending_orders !== undefined) {
                        pendingBadge.textContent = data.pending_orders;
                    }

                    // Update notification bell badge
                    var notifBadge = document.getElementById('spNotifBadge');
                    if (notifBadge) {
                        if (data.unread_notifs > 0) {
                            notifBadge.textContent = data.unread_notifs > 99 ? '99+' : data.unread_notifs;
                            notifBadge.style.display = 'flex';
                        } else {
                            notifBadge.style.display = 'none';
                        }
                    }
                })
                .catch(function() { /* silent fail — retry next interval */ });
        }

        function showSyncNotification(msg) {
            var existing = document.getElementById('spSyncNotif');
            if (existing) existing.remove();

            var notif = document.createElement('div');
            notif.id = 'spSyncNotif';
            notif.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:99999;background:linear-gradient(135deg,#059669,#10b981);color:#fff;padding:10px 16px;font-size:13px;font-weight:600;text-align:center;font-family:Inter,sans-serif;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:spSlideDown 0.3s ease;';
            notif.innerHTML = '<i class="fas fa-sync-alt" style="margin-right:6px;"></i>' + msg;
            document.body.appendChild(notif);

            // Add animation
            var style = document.createElement('style');
            style.textContent = '@keyframes spSlideDown{from{transform:translateY(-100%)}to{transform:translateY(0)}}';
            document.head.appendChild(style);

            setTimeout(function() {
                notif.style.transition = 'opacity 0.3s, transform 0.3s';
                notif.style.opacity = '0';
                notif.style.transform = 'translateY(-100%)';
                setTimeout(function() { notif.remove(); style.remove(); }, 300);
            }, 4000);
        }

        // Start sync when page is visible
        function startSync() {
            checkForUpdates(); // Initial check
            syncTimer = setInterval(checkForUpdates, SYNC_INTERVAL);
        }

        function stopSync() {
            if (syncTimer) { clearInterval(syncTimer); syncTimer = null; }
        }

        // Pause sync when tab/app is hidden, resume when visible
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopSync();
            } else {
                startSync();
            }
        });

        // Start
        if (!document.hidden) startSync();
    })();

    // ── Service Worker Registration + Auto-Update ──
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('./sw.js').then(function(reg) {
                // Check for updates immediately on every page load
                reg.update();

                // When a new SW is found, activate it immediately
                reg.addEventListener('updatefound', function() {
                    var newWorker = reg.installing;
                    if (!newWorker) return;
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New version ready — tell it to activate NOW
                            newWorker.postMessage({ type: 'SKIP_WAITING' });
                        }
                    });
                });

                // When the new SW takes control, clear cache and reload
                var refreshing = false;
                navigator.serviceWorker.addEventListener('controllerchange', function() {
                    if (refreshing) return;
                    refreshing = true;
                    window.location.reload();
                });
            }).catch(function(err) {
                console.log('SW registration failed:', err);
            });
        });
    }

    // ── PWA Install Prompt ──
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // Show install banner
        const banner = document.createElement('div');
        banner.id = 'pwaInstallBanner';
        banner.innerHTML = `
            <div style="position:fixed;bottom:0;left:0;right:0;background:linear-gradient(135deg,#7c3aed,#5b21b6);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;z-index:9999;box-shadow:0 -4px 20px rgba(0,0,0,0.3);font-family:Inter,sans-serif;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <i class="fas fa-mobile-alt" style="font-size:24px;"></i>
                    <div>
                        <div style="font-weight:700;font-size:14px;">Install Gilaf Sales App</div>
                        <div style="font-size:11px;opacity:0.85;">Add to home screen for quick access</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button onclick="installPWA()" style="padding:8px 20px;background:#fff;color:#7c3aed;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;">Install</button>
                    <button onclick="this.closest('#pwaInstallBanner').remove()" style="padding:8px 12px;background:rgba(255,255,255,0.2);color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;">Later</button>
                </div>
            </div>
        `;
        document.body.appendChild(banner);
    });

    function installPWA() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choice) => {
                if (choice.outcome === 'accepted') {
                    console.log('PWA installed');
                }
                deferredPrompt = null;
                const banner = document.getElementById('pwaInstallBanner');
                if (banner) banner.remove();
            });
        }
    }

    window.addEventListener('appinstalled', () => {
        console.log('Gilaf Sales Portal installed');
        const banner = document.getElementById('pwaInstallBanner');
        if (banner) banner.remove();
    });
    </script>
</body>
</html>
