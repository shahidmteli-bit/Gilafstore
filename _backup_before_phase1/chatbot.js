// AI Customer Support Chatbot
class GilafChatbot {
    constructor() {
        this.isOpen = false;
        this.messages = [];
        this.conversationHistory = [];
        this.useAI = true; // Set to false to use only knowledge base
        this.knowledgeBase = this.initKnowledgeBase();
        this.basePath = this.getBasePath();
        this.fetchTimeout = 10000; // 10 second timeout for API calls
        this.init();
    }
    
    // Helper method to fetch with timeout
    async fetchWithTimeout(url, options = {}) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), this.fetchTimeout);
        
        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            return response;
        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                throw new Error('Request timeout - please try again');
            }
            throw error;
        }
    }

    getBasePath() {
        // Derive project base path from the script URL.
        // Example script src: /Gilaf%20Ecommerce%20website/assets/js/chatbot.js
        const script = document.currentScript || document.querySelector('script[src*="assets/js/chatbot.js"]');
        try {
            if (script && script.src) {
                const scriptUrl = new URL(script.src, window.location.href);
                const idx = scriptUrl.pathname.indexOf('/assets/');
                if (idx !== -1) {
                    const path = scriptUrl.pathname.substring(0, idx);
                    // Ensure trailing slash
                    return path.endsWith('/') ? path : path + '/';
                }
            }
        } catch (e) {
            console.warn('[Chatbot] Failed to detect base path:', e.message);
        }
        // Fallback: use current path without file
        return '';
    }

    buildUrl(path) {
        const base = this.basePath || '';
        const cleanPath = String(path || '').replace(/^\/+/, '');
        return base ? `${base}/${cleanPath}` : cleanPath;
    }

    init() {
        this.createChatbotHTML();
        this.attachEventListeners();
        this.showWelcomeMessage();
    }

    initKnowledgeBase() {
        return {
            // Product Information
            'products': {
                keywords: ['product', 'products', 'item', 'items', 'catalog', 'what do you sell', 'saffron', 'spices'],
                response: 'We offer premium quality saffron and spices. Our products include:\n\n• Premium Saffron (Various grades)\n• Organic Saffron\n• Saffron Gift Sets\n• Other Premium Spices\n\nAll our products are lab-tested and certified. Would you like to know more about a specific product?',
                quickActions: ['View Products', 'Product Quality', 'Pricing']
            },
            'quality': {
                keywords: ['quality', 'lab test', 'certified', 'organic', 'grade', 'authentic', 'genuine', 'verification'],
                response: 'Quality is our top priority! 🏆\n\n✓ All products are lab-tested\n✓ Certified organic options available\n✓ QR code verification on each batch\n✓ Detailed lab reports available\n✓ Multiple quality grades\n\nYou can verify any batch using the QR code or batch number on our verification page.',
                quickActions: ['Verify Batch', 'Lab Reports', 'Certifications']
            },

            // Order Related
            'order_status': {
                keywords: ['order status', 'track order', 'where is my order', 'order tracking', 'delivery status', 'shipment'],
                response: 'To track your order:\n\n1. Go to "Track Order" in the menu\n2. Enter your Order ID\n3. View real-time status\n\nYou can also track from your profile dashboard if you\'re logged in.\n\nNeed help finding your order ID?',
                quickActions: ['Track Order', 'My Orders', 'Contact Support']
            },
            'order_process': {
                keywords: ['how to order', 'place order', 'buy', 'purchase', 'ordering process', 'checkout'],
                response: 'Ordering is simple! 📦\n\n1. Browse products\n2. Add items to cart\n3. Proceed to checkout\n4. Enter delivery details\n5. Choose payment method\n6. Confirm order\n\nYou\'ll receive order confirmation via email and SMS.',
                quickActions: ['Shop Now', 'View Cart', 'Need Help']
            },
            'delivery': {
                keywords: ['delivery', 'shipping', 'delivery time', 'how long', 'when will i receive', 'dispatch'],
                response: 'Delivery Information 🚚\n\n• Standard Delivery: 5-7 business days\n• Express Delivery: 2-3 business days\n• Same-day delivery available in select cities\n\nDelivery charges vary by location. Free shipping on orders above ₹999.\n\nTracking details will be sent once your order is dispatched.',
                quickActions: ['Track Order', 'Delivery Areas', 'Contact Support']
            },

            // Payment Related
            'payment_methods': {
                keywords: ['payment', 'payment method', 'how to pay', 'payment options', 'cod', 'cash on delivery', 'upi', 'card'],
                response: 'We accept multiple payment methods 💳\n\n✓ Credit/Debit Cards\n✓ UPI (Google Pay, PhonePe, Paytm)\n✓ Net Banking\n✓ Wallets\n✓ Cash on Delivery (COD)\n\nAll payments are 100% secure and encrypted.',
                quickActions: ['Payment Issues', 'COD Available?', 'Refund Info']
            },
            'payment_failed': {
                keywords: ['payment failed', 'payment not working', 'payment error', 'transaction failed', 'payment issue'],
                response: 'Sorry for the inconvenience! If payment failed:\n\n1. Check your internet connection\n2. Verify card/account details\n3. Ensure sufficient balance\n4. Try a different payment method\n5. Clear browser cache\n\nIf the amount was deducted, it will be auto-refunded within 5-7 business days.\n\nStill facing issues?',
                quickActions: ['Retry Payment', 'Contact Support', 'Check Refund']
            },

            // Policies
            'refund': {
                keywords: ['refund', 'money back', 'return money', 'refund policy', 'refund status'],
                response: 'Refund Policy 💰\n\n• Refunds processed within 7-10 business days\n• Amount credited to original payment method\n• Valid for unopened products only\n• Return shipping may apply\n\nTo request a refund, contact our support team with your order details.',
                quickActions: ['Return Policy', 'Request Refund', 'Contact Support']
            },
            'return': {
                keywords: ['return', 'return policy', 'exchange', 'replace', 'damaged product', 'wrong product'],
                response: 'Return & Exchange Policy 🔄\n\n• 7-day return window from delivery\n• Products must be unopened and unused\n• Original packaging required\n• Free return pickup available\n\nReasons for return:\n✓ Damaged/defective product\n✓ Wrong item delivered\n✓ Quality issues\n\nWant to initiate a return?',
                quickActions: ['Start Return', 'Exchange Product', 'Contact Support']
            },
            'cancellation': {
                keywords: ['cancel', 'cancel order', 'cancellation', 'cancel my order'],
                response: 'Order Cancellation ❌\n\n• Orders can be cancelled before dispatch\n• Full refund for cancelled orders\n• Refund processed in 5-7 business days\n\nTo cancel:\n1. Go to "My Orders"\n2. Select the order\n3. Click "Cancel Order"\n4. Choose reason\n\nNeed help cancelling?',
                quickActions: ['My Orders', 'Cancellation Policy', 'Contact Support']
            },

            // Account & Profile
            'account': {
                keywords: ['account', 'profile', 'login', 'sign up', 'register', 'password', 'forgot password'],
                response: 'Account Help 👤\n\n• Create account: Click "Sign Up"\n• Login: Click user icon\n• Forgot password: Use "Forgot Password" link\n• Update profile: Go to "My Profile"\n\nBenefits of creating an account:\n✓ Track orders easily\n✓ Save addresses\n✓ Faster checkout\n✓ Order history',
                quickActions: ['Sign Up', 'Login', 'Reset Password']
            },

            // Distributor
            'distributor': {
                keywords: ['distributor', 'reseller', 'become distributor', 'partnership', 'wholesale', 'bulk order'],
                response: 'Become a Gilaf Partner! 🤝\n\nWe offer three partnership options:\n\n1. **Reseller** - Sell in your store\n2. **Distributor** - Regional distribution\n3. **Official Store** - Exclusive Gilaf store\n\nBenefits:\n✓ Competitive margins\n✓ Marketing support\n✓ Training provided\n✓ Territory rights (for distributors)\n\nInterested in applying?',
                quickActions: ['Apply Now', 'Learn More', 'Contact Sales']
            },

            // Batch Verification
            'verify': {
                keywords: ['verify', 'verification', 'qr code', 'batch code', 'authenticate', 'check authenticity'],
                response: 'Product Verification 🔍\n\nVerify authenticity using:\n\n1. **QR Code**: Scan the QR code on packaging\n2. **Batch Code**: Enter batch code manually\n\nVerification shows:\n✓ Product details\n✓ Manufacturing date\n✓ Expiry date\n✓ Lab test results\n✓ Origin information\n\nThis ensures you have genuine Gilaf products!',
                quickActions: ['Verify Now', 'How to Scan', 'Report Fake']
            },

            // Contact & Support - handled directly by showSupportOptions()
            'contact': {
                keywords: ['contact', 'support', 'help', 'customer care', 'phone number', 'email', 'reach you'],
                response: '__SHOW_SUPPORT_OPTIONS__',
                quickActions: []
            },

            // Suggestions & Feedback
            'suggestions': {
                keywords: ['suggest', 'suggestion', 'feedback', 'improve', 'improvement', 'idea', 'feature request', 'help us improve'],
                response: '💡 Help Us Improve!\n\nWe value your feedback and ideas! Share your suggestions on:\n\n✓ Website improvements\n✓ New features\n✓ Product suggestions\n✓ User experience\n✓ Any other ideas\n\nYour input helps us serve you better. Best suggestions may receive special rewards! 🎁',
                quickActions: ['Share Your Ideas', 'View Rewards', 'Continue']
            },

            // Default/Greeting
            'greeting': {
                keywords: ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'namaste'],
                response: 'Hello! Welcome to Gilaf Store! 👋\n\nI\'m your AI assistant. I can help you with:\n\n• Product information\n• Order tracking & status\n• Payment queries\n• Returns & refunds\n• Account help\n• Batch verification\n\nHow can I assist you today?',
                quickActions: ['Track Order', 'View Products', 'Contact Support']
            }
        };
    }

    createChatbotHTML() {
        const chatbotHTML = `
            <!-- Chatbot Toggle Button -->
            <div class="chatbot-toggle" id="chatbotToggle">
                <i class="fas fa-comments"></i>
                <i class="fas fa-times"></i>
                <span class="chatbot-badge">1</span>
            </div>

            <!-- Chatbot Window -->
            <div class="chatbot-window" id="chatbotWindow">
                <!-- Header -->
                <div class="chatbot-header">
                    <div class="chatbot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="chatbot-info">
                        <h3>Gilaf Store Support</h3>
                        <div class="chatbot-status">
                            <span class="status-dot"></span>
                            <span>Online • Instant replies</span>
                        </div>
                    </div>
                    <div class="chatbot-minimize" id="chatbotMinimize">
                        <i class="fas fa-minus"></i>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="chatbot-messages" id="chatbotMessages">
                    <!-- Messages will be inserted here -->
                </div>

                <!-- Input Area -->
                <div class="chatbot-input-area">
                    <input 
                        type="text" 
                        class="chatbot-input" 
                        id="chatbotInput" 
                        placeholder="Type your message..."
                        autocomplete="off"
                    >
                    <button class="chatbot-send-btn" id="chatbotSend">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', chatbotHTML);
    }

    attachEventListeners() {
        const toggle = document.getElementById('chatbotToggle');
        const minimize = document.getElementById('chatbotMinimize');
        const sendBtn = document.getElementById('chatbotSend');
        const input = document.getElementById('chatbotInput');

        // Defensive null checks before adding event listeners
        if (toggle) {
            toggle.addEventListener('click', () => this.toggleChat());
        } else {
            console.warn('[Chatbot] Toggle button not found');
        }
        
        if (minimize) {
            minimize.addEventListener('click', () => this.toggleChat());
        } else {
            console.warn('[Chatbot] Minimize button not found');
        }
        
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        } else {
            console.warn('[Chatbot] Send button not found');
        }
        
        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') this.sendMessage();
            });
        } else {
            console.warn('[Chatbot] Input field not found');
        }
    }

    toggleChat() {
        this.isOpen = !this.isOpen;
        const window = document.getElementById('chatbotWindow');
        const toggle = document.getElementById('chatbotToggle');
        
        // Defensive null checks
        if (!window || !toggle) {
            console.warn('[Chatbot] Required elements not found for toggle');
            return;
        }
        
        const badge = toggle.querySelector('.chatbot-badge');

        if (this.isOpen) {
            window.classList.add('active');
            toggle.classList.add('active');
            if (badge) badge.style.display = 'none';
            
            const input = document.getElementById('chatbotInput');
            if (input) {
                input.focus();
            }
        } else {
            window.classList.remove('active');
            toggle.classList.remove('active');
        }
    }

    async showRecentTickets() {
        try {
            const res = await this.fetchWithTimeout(this.buildUrl('chatbot_recent_tickets.php'), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const contentType = res.headers.get('content-type') || '';
            if (!res.ok) return;
            if (!contentType.includes('application/json')) return;

            const data = await res.json();
            if (!data.success) {
                if (data.action === 'login_required') {
                    this.addBotMessage('Please login to view your tickets.', ['Login'], false);
                }
                return;
            }

            const tickets = Array.isArray(data.tickets) ? data.tickets : [];
            if (tickets.length === 0) {
                this.addBotMessage('You don\'t have any support tickets yet. You can create one anytime.', ['Create Support Ticket'], false);
                return;
            }

            const cards = tickets.map(t => {
                const tid = this.escapeHtml(String(t.ticket_id || ''));
                const subject = this.escapeHtml(String(t.subject || 'Support Request'));
                const status = this.escapeHtml(this.formatOrderStatus(t.status || ''));
                const created = this.escapeHtml((t.created_at || '').toString().slice(0, 10));
                return `
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 12px; border:1px solid #e9ecef; border-radius:10px; margin-bottom:10px; background:#fff;">
                        <div style="min-width:0;">
                            <div style="font-weight:800; color:#1A3C34; font-size:13px;">Ticket ${tid}</div>
                            <div style="font-size:12px; color:#6b7280; margin-top:2px;">${created}</div>
                            <div style="font-size:12px; color:#111827; margin-top:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width: 190px;"><strong>${status}</strong> • ${subject}</div>
                        </div>
                        <button onclick="gilafChatbot.selectRecentTicket('${tid}')" style="margin-left:10px; white-space:nowrap; padding:10px 12px; border:none; border-radius:10px; background:linear-gradient(135deg,#C5A059 0%,#d4b068 100%); color:#fff; font-weight:700; cursor:pointer; font-size:12px;">View</button>
                    </div>
                `;
            }).join('');

            const recentHTML = `
                <div class="chat-message bot">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble" style="background:#F8F5F2; padding:16px; border-radius:12px;">
                            <div style="font-weight:800; color:#1A3C34; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-ticket-alt" style="color:#C5A059;"></i>
                                Recent Tickets
                            </div>
                            <div style="font-size:12px; color:#6b7280; margin-bottom:12px;">Tap a ticket to see its current status and latest comments.</div>
                            ${cards}
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `;

            this.appendMessage(recentHTML);
        } catch (e) {
            console.warn('[Chatbot] Failed to load recent tickets:', e.message);
            // Silent fail - user can still create tickets
        }
    }

    async selectRecentTicket(ticketId) {
        const tid = String(ticketId || '').trim();
        if (!tid) return;

        this.addUserMessage(`Ticket ${tid}`);
        this.showTypingIndicator();

        try {
            const res = await this.fetchWithTimeout(this.buildUrl(`chatbot_ticket_status.php?ticket_id=${encodeURIComponent(tid)}`), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const contentType = res.headers.get('content-type') || '';
            if (!res.ok) {
                const bodyText = await res.text();
                throw new Error(`HTTP ${res.status}: ${bodyText.slice(0, 120)}`);
            }
            if (!contentType.includes('application/json')) {
                const bodyText = await res.text();
                throw new Error(`Unexpected response (not JSON): ${bodyText.slice(0, 120)}`);
            }

            const data = await res.json();
            this.removeTypingIndicator();

            if (!data.success) {
                if (data.action === 'login_required') {
                    this.addBotMessage('Please login to view ticket status.', ['Login'], false);
                    return;
                }
                this.addBotMessage(data.message || 'Unable to fetch ticket status.', null, false);
                return;
            }

            const t = data.ticket || {};
            const status = this.escapeHtml(this.formatOrderStatus(t.status || ''));
            const subject = this.escapeHtml(String(t.subject || 'Support Request'));
            const created = this.escapeHtml((t.created_at || '').toString().slice(0, 19).replace('T', ' '));
            const updated = this.escapeHtml((t.updated_at || '').toString().slice(0, 19).replace('T', ' '));
            const lastComments = Array.isArray(data.last_comments) ? data.last_comments : [];

            const commentsHTML = lastComments.length
                ? lastComments.map(c => {
                    const by = this.escapeHtml(String(c.commenter_name || ''));
                    const when = this.escapeHtml((c.created_at || '').toString().slice(0, 19).replace('T', ' '));
                    const text = this.escapeHtml(String(c.comment || '')).replace(/\n/g, '<br>');
                    return `
                        <div style="border-top:1px solid #eef2f7; padding-top:10px; margin-top:10px;">
                            <div style="font-size:12px; color:#6b7280;"><strong style="color:#111827;">${by}</strong> • ${when}</div>
                            <div style="font-size:12px; color:#111827; margin-top:6px; line-height:1.5;">${text}</div>
                        </div>
                    `;
                }).join('')
                : `<div style="font-size:12px; color:#6b7280; margin-top:10px;">No comments yet.</div>`;

            const statusHTML = `
                <div style="background:#fff; border:1px solid #e9ecef; border-radius:12px; padding:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <div>
                            <div style="font-weight:900; color:#1A3C34;">Ticket ${this.escapeHtml(String(t.ticket_id || tid))}</div>
                            <div style="font-size:12px; color:#6b7280; margin-top:3px;">${subject}</div>
                        </div>
                        <div style="font-size:12px; font-weight:800; color:#1A3C34; background:rgba(26,60,52,0.08); padding:6px 10px; border-radius:999px;">${status}</div>
                    </div>
                    <div style="margin-top:12px; font-size:12px; color:#111827;">
                        <div><strong>Created:</strong> ${created}</div>
                        <div style="margin-top:6px;"><strong>Last Update:</strong> ${updated}</div>
                    </div>
                    ${commentsHTML}
                </div>
            `;

            this.addBotMessage(`Here is the latest status for your ticket:`, ['View My Tickets'], false);
            this.appendMessage(`
                <div class="chat-message bot">
                    <div class="message-avatar"><i class="fas fa-robot"></i></div>
                    <div class="message-content">
                        <div class="message-bubble">${statusHTML}</div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `);
        } catch (e) {
            console.error('[Chatbot] Ticket status error:', e.message);
            this.removeTypingIndicator();
            this.addBotMessage('Unable to fetch ticket status right now. Please try again.', null, false);
        }
    }

    showWelcomeMessage() {
        setTimeout(() => {
            const welcomeHTML = `
                <div class="chat-message bot">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble">
                            <div class="suggested-topics">
                                <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Track my order')">
                                    <i class="fas fa-box"></i>
                                    <span>Track Order</span>
                                </div>
                                <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Product information')">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Products</span>
                                </div>
                                <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Return policy')">
                                    <i class="fas fa-undo"></i>
                                    <span>Returns</span>
                                </div>
                                <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Contact support')">
                                    <i class="fas fa-headset"></i>
                                    <span>Support</span>
                                </div>
                                <div class="topic-card" onclick="gilafChatbot.openSuggestionModal()">
                                    <i class="fas fa-lightbulb"></i>
                                    <span>Help Us Improve</span>
                                </div>
                            </div>
                            <div class="welcome-message" style="margin-top: 15px;">
                                <h4>Welcome to Gilaf Store! 🌟</h4>
                                <p>I'm your AI assistant. How can I help you today?</p>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `;
            document.getElementById('chatbotMessages').innerHTML = welcomeHTML;
        }, 500);
    }

    showContinueMenu() {
        const continueHTML = `
            <div class="chat-message bot">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <div class="message-bubble">
                        <div class="welcome-message">
                            <h4>Anything else I can help with? 🌟</h4>
                            <p>Choose an option below or type your question</p>
                        </div>
                        <div class="suggested-topics">
                            <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Track my order')">
                                <i class="fas fa-box"></i>
                                <span>Track Order</span>
                            </div>
                            <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Product information')">
                                <i class="fas fa-info-circle"></i>
                                <span>Products</span>
                            </div>
                            <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Return policy')">
                                <i class="fas fa-undo"></i>
                                <span>Returns</span>
                            </div>
                            <div class="topic-card" onclick="gilafChatbot.handleQuickAction('Contact support')">
                                <i class="fas fa-headset"></i>
                                <span>Support</span>
                            </div>
                        </div>
                    </div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            </div>
        `;
        this.appendMessage(continueHTML);
    }

    async showRecentOrders() {
        try {
            const res = await this.fetchWithTimeout(this.buildUrl('chatbot_recent_orders.php'), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const contentType = res.headers.get('content-type') || '';
            if (!res.ok) return;
            if (!contentType.includes('application/json')) return;

            const data = await res.json();
            if (!data.success) {
                if (data.action === 'login_required') {
                    this.addBotMessage('Please login to view your recent orders.', ['Login'], false);
                }
                return;
            }

            const orders = Array.isArray(data.orders) ? data.orders : [];
            if (orders.length === 0) {
                this.addBotMessage('I couldn’t find any recent orders in your account. You can still track by entering a tracking number below.', null, false);
                return;
            }

            const options = orders.map(o => {
                const id = o.id;
                const ref = o.reference || ('ORD-' + String(id).padStart(5, '0'));
                const status = this.formatOrderStatus(o.status || '');
                return `<option value="${id}">${this.escapeHtml(ref)} - ${this.escapeHtml(status)}</option>`;
            }).join('');

            const recentHTML = `
                <div class="chat-message bot">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble" style="background:#F8F5F2; padding:16px; border-radius:12px;">
                            <div style="font-weight:800; color:#1A3C34; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-receipt" style="color:#C5A059;"></i>
                                Recent Orders
                            </div>
                            <div style="font-size:12px; color:#6b7280; margin-bottom:12px;">Select an order to see its latest status.</div>
                            <select id="chatbotOrderSelect" style="width:100%; padding:12px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; margin-bottom:12px; box-sizing:border-box; background:#fff;">
                                <option value="">-- Select an order --</option>
                                ${options}
                            </select>
                            <button onclick="gilafChatbot.viewSelectedOrder()" style="width:100%; padding:12px; background:linear-gradient(135deg,#C5A059 0%,#d4b068 100%); color:#fff; border:none; border-radius:8px; cursor:pointer; font-weight:700; font-size:14px; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(197,160,89,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                <i class="fas fa-search"></i> View Status
                            </button>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `;

            this.appendMessage(recentHTML);
        } catch (e) {
            console.warn('[Chatbot] Failed to load recent orders:', e.message);
            // Silent fail; tracking by number still works
        }
    }

    formatOrderStatus(status) {
        const s = String(status || '').replace(/_/g, ' ').trim();
        if (!s) return 'Unknown';
        return s.charAt(0).toUpperCase() + s.slice(1);
    }

    viewSelectedOrder() {
        const select = document.getElementById('chatbotOrderSelect');
        const orderId = select?.value;
        
        if (!orderId) {
            this.addBotMessage('Please select an order from the dropdown.', null, false);
            return;
        }
        
        this.selectRecentOrder(parseInt(orderId, 10));
    }

    async selectRecentOrder(orderId) {
        const id = parseInt(orderId, 10);
        if (!id) return;

        this.addUserMessage(`Order #${id}`);
        this.showTypingIndicator();

        try {
            const res = await this.fetchWithTimeout(this.buildUrl(`chatbot_order_status.php?order_id=${encodeURIComponent(id)}`), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const contentType = res.headers.get('content-type') || '';
            if (!res.ok) {
                const bodyText = await res.text();
                throw new Error(`HTTP ${res.status}: ${bodyText.slice(0, 120)}`);
            }
            if (!contentType.includes('application/json')) {
                const bodyText = await res.text();
                throw new Error(`Unexpected response (not JSON): ${bodyText.slice(0, 120)}`);
            }

            const data = await res.json();
            this.removeTypingIndicator();

            if (!data.success) {
                if (data.action === 'login_required') {
                    this.addBotMessage('Please login to view order status.', ['Login'], false);
                    return;
                }
                this.addBotMessage(data.message || 'Unable to fetch order status.', null, false);
                return;
            }

            const o = data.order || {};
            const status = this.escapeHtml(this.formatOrderStatus(o.status || ''));
            const created = this.escapeHtml((o.created_at || '').toString().slice(0, 19).replace('T', ' '));
            const deliveredAt = o.delivered_at ? this.escapeHtml((o.delivered_at || '').toString().slice(0, 19).replace('T', ' ')) : null;
            const itemCount = Number.isFinite(o.item_count) ? o.item_count : (Array.isArray(o.items) ? o.items.length : 0);
            const total = typeof o.total_amount === 'number' ? o.total_amount : parseFloat(o.total_amount || 0);
            const totalText = isFinite(total) ? `₹${total.toFixed(2)}` : '';
            const trackingNo = (o.tracking_number || '').toString().trim();
            const trackingLine = trackingNo ? `<div style="margin-top:6px;"><strong>Tracking:</strong> ${this.escapeHtml(trackingNo)}</div>` : '';
            const deliveredLine = deliveredAt ? `<div style="margin-top:6px; color:#16a34a;"><strong>Delivered:</strong> ${deliveredAt}</div>` : '';

            const statusHTML = `
                <div style="background:#fff; border:1px solid #e9ecef; border-radius:12px; padding:14px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <div>
                            <div style="font-weight:800; color:#1A3C34;">Order #${this.escapeHtml(String(o.id || id))}</div>
                            <div style="font-size:12px; color:#6b7280; margin-top:3px;">${created}</div>
                        </div>
                        <div style="font-size:12px; font-weight:800; color:${deliveredAt ? '#16a34a' : '#1A3C34'}; background:${deliveredAt ? 'rgba(22,163,74,0.1)' : 'rgba(26,60,52,0.08)'}; padding:6px 10px; border-radius:999px;">${status}</div>
                    </div>
                    <div style="margin-top:12px; font-size:12px; color:#111827;">
                        <div><strong>Items:</strong> ${this.escapeHtml(String(itemCount))}</div>
                        <div style="margin-top:6px;"><strong>Total:</strong> ${this.escapeHtml(totalText)}</div>
                        ${deliveredLine}
                        ${trackingLine}
                    </div>
                </div>
            `;

            this.addBotMessage(`Here is the latest status for your order:`, null, false);
            this.appendMessage(`
                <div class="chat-message bot">
                    <div class="message-avatar"><i class="fas fa-robot"></i></div>
                    <div class="message-content">
                        <div class="message-bubble">${statusHTML}</div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `);
            
            // Don't auto-show continue menu - keep chatbot stable and focused
        } catch (e) {
            console.error('[Chatbot] Order status error:', e.message);
            this.removeTypingIndicator();
            this.addBotMessage(`Unable to fetch order status right now. Please try again.`, null, false);
        }
    }

    sendMessage() {
        const input = document.getElementById('chatbotInput');
        
        // Defensive null check
        if (!input) {
            console.warn('[Chatbot] Input field not found');
            return;
        }
        
        const message = input.value.trim();

        if (!message) return;

        this.addUserMessage(message);
        input.value = '';

        // If live chat is enabled and admin has taken over, send to live chat
        if (this.liveChatEnabled && (this.chatStatus === 'active' || this.chatStatus === 'waiting')) {
            this.sendLiveChatMessage(message);
            return;
        }

        this.showTypingIndicator();
        setTimeout(() => {
            this.removeTypingIndicator();
            this.processMessage(message);
        }, 1000 + Math.random() * 1000);
    }

    addUserMessage(message) {
        const messageHTML = `
            <div class="chat-message user">
                <div class="message-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="message-content">
                    <div class="message-bubble">${this.escapeHtml(message)}</div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            </div>
        `;
        this.appendMessage(messageHTML);
    }

    addBotMessage(message, quickActions = null, escalation = false) {
        const formattedMessage = message.replace(/\n/g, '<br>');
        let quickActionsHTML = '';
        
        if (quickActions && quickActions.length > 0) {
            quickActionsHTML = '<div class="quick-actions">';
            quickActions.forEach(action => {
                quickActionsHTML += `<button class="quick-action-btn" onclick="gilafChatbot.handleQuickAction('${action}')">${action}</button>`;
            });
            quickActionsHTML += '</div>';
        }

        if (escalation) {
            quickActionsHTML += this.getEscalationOptions();
        }

        const messageHTML = `
            <div class="chat-message bot">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <div class="message-bubble">
                        ${formattedMessage}
                        ${quickActionsHTML}
                    </div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            </div>
        `;
        this.appendMessage(messageHTML);
    }

    getEscalationOptions() {
        return `
            <div class="escalation-options">
                <div class="escalation-btn callback" onclick="gilafChatbot.handleQuickAction('Create Support Ticket')">
                    <i class="fas fa-ticket-alt"></i>
                    <div class="escalation-text">
                        <strong>Create Ticket</strong>
                        <span>Get detailed help</span>
                    </div>
                </div>
                <div class="escalation-btn tracking" onclick="gilafChatbot.showRecentTickets()">
                    <i class="fas fa-clipboard-list"></i>
                    <div class="escalation-text">
                        <strong>Track Tickets</strong>
                        <span>View your support tickets</span>
                    </div>
                </div>
                <a href="https://wa.me/919419404670" target="_blank" class="escalation-btn whatsapp">
                    <i class="fab fa-whatsapp"></i>
                    <div class="escalation-text">
                        <strong>WhatsApp Chat</strong>
                        <span>Get instant help</span>
                    </div>
                </a>
                <div class="escalation-btn callback" onclick="gilafChatbot.showCallRequestForm()">
                    <i class="fas fa-phone-alt"></i>
                    <div class="escalation-text">
                        <strong>Request Callback</strong>
                        <span>We'll call you back</span>
                    </div>
                </div>
                <a href="tel:+919419404670" class="escalation-btn phone">
                    <i class="fas fa-phone"></i>
                    <div class="escalation-text">
                        <strong>Call Support</strong>
                        <span>+91-9419404670</span>
                    </div>
                </a>
                <div class="escalation-btn agent" onclick="gilafChatbot.requestLiveAgent()">
                    <i class="fas fa-comments"></i>
                    <div class="escalation-text">
                        <strong>Chat with Agent</strong>
                        <span>Live chat support</span>
                    </div>
                </div>
            </div>
        `;
    }

    showSupportOptions() {
        // Show all support options directly in a clean format
        const supportHTML = `
            <div class="chat-message bot">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <div class="message-bubble">
                        <div style="margin-bottom: 12px;">
                            <strong>How can we help you?</strong> 🤝
                        </div>
                        ${this.getEscalationOptions()}
                    </div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            </div>
        `;
        this.appendMessage(supportHTML);
    }

    async processMessage(message) {
        // Try AI if enabled
        if (this.useAI) {
            try {
                const aiResponse = await this.getAIResponse(message);
                if (aiResponse.success) {
                    this.conversationHistory.push(
                        { role: 'user', content: message },
                        { role: 'assistant', content: aiResponse.response }
                    );
                    this.addBotMessage(aiResponse.response, aiResponse.quick_actions, aiResponse.escalate);
                    return;
                }
            } catch (error) {
                console.log('AI unavailable, using knowledge base fallback');
            }
        }

        // Fallback to knowledge base
        const lowerMessage = message.toLowerCase();
        let response = null;
        let matchedCategory = null;

        // Check knowledge base
        for (const [category, data] of Object.entries(this.knowledgeBase)) {
            if (data.keywords.some(keyword => lowerMessage.includes(keyword))) {
                response = data.response;
                matchedCategory = data;
                break;
            }
        }

        // If no match found, escalate
        if (!response) {
            const faqLink = `<a href="${this.buildUrl('faq.php')}" target="_blank" rel="noopener">FAQ page</a>`;
            this.addBotMessage(
                `I understand you need help with something specific. This request needs assistance from our customer care team.\n\nPlease choose how you\'d like to connect with us, or visit our ${faqLink} for quick answers.`,
                null,
                true
            );
            return;
        }

        // Check for special support handler
        if (response === '__SHOW_SUPPORT_OPTIONS__') {
            this.showSupportOptions();
            return;
        }

        // Send response with quick actions
        this.addBotMessage(response, matchedCategory.quickActions);
    }

    async getAIResponse(message) {
        // Route to correct AI provider backend
        const aiBackend = this.buildUrl('chatbot_gemini.php'); // Using Gemini AI
        
        const response = await this.fetchWithTimeout(aiBackend, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                message: message,
                history: this.conversationHistory.slice(-6) // Last 3 exchanges
            })
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await response.json();
        
        if (data.use_fallback) {
            throw new Error('Fallback to knowledge base');
        }

        return data;
    }

    handleQuickAction(action) {
        this.addUserMessage(action);
        
        this.showTypingIndicator();
        setTimeout(() => {
            this.removeTypingIndicator();
            
            // Handle specific quick actions
            if (action === 'Create Support Ticket') {
                this.showTicketForm();
            } else if (action === 'Track Tickets') {
                this.addBotMessage('Here are your recent support tickets:', null, false);
                this.showRecentTickets();
            } else if (action === 'View My Tickets') {
                window.location.href = this.buildUrl('user/my_tickets.php');
            } else if (action === 'Login') {
                window.location.href = this.buildUrl('user/login.php');
            } else if (action === 'Track Order' || action === 'Track my order') {
                this.showTrackingForm();
            } else if (action === 'View Products' || action === 'Shop Now') {
                window.location.href = 'shop.php';
            } else if (action === 'Verify Now' || action === 'Verify Batch') {
                window.location.href = 'verify-batch.php';
            } else if (action === 'My Orders') {
                window.location.href = 'user/profile.php';
            } else if (action === 'Apply Now') {
                window.location.href = 'apply-distributor.php';
            } else if (action === 'Contact Support' || action === 'Need Help') {
                // Show all support options directly without confusing redirects
                this.showSupportOptions();
            } else if (action === 'Share Your Ideas') {
                this.openSuggestionModal();
            } else {
                // Process as regular message
                this.processMessage(action);
            }
        }, 800);
    }

    showTicketForm() {
        this.addBotMessage('Sure — please fill the details below to create a support ticket.', null, false);

        setTimeout(() => {
            const formHTML = `
                <div class="chat-message bot">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble" style="background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 20px; border-radius: 12px;">
                            <div style="color: white; margin-bottom: 15px;">
                                <i class="fas fa-ticket-alt" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                <h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">Create Support Ticket</h4>
                                <p style="margin: 0; font-size: 13px; opacity: 0.9;">We’ll generate a ticket number instantly.</p>
                            </div>

                            <div style="background: white; padding: 15px; border-radius: 8px;">
                                <label style="display:block; font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 6px;">Issue Type</label>
                                <select id="chatbotTicketIssueType" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; margin-bottom: 12px; box-sizing: border-box;">
                                    <option value="">Select issue type...</option>
                                    <option value="order">Order Issues</option>
                                    <option value="product">Product Questions</option>
                                    <option value="payment">Payment Issues</option>
                                    <option value="shipping">Shipping & Delivery</option>
                                    <option value="account">Account Support</option>
                                    <option value="technical">Technical Issues</option>
                                    <option value="other">General Inquiry</option>
                                </select>

                                <label style="display:block; font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 6px;">Priority</label>
                                <select id="chatbotTicketPriority" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; margin-bottom: 12px; box-sizing: border-box;">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>

                                <label style="display:block; font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 6px;">Description</label>
                                <textarea id="chatbotTicketDescription" rows="4" placeholder="Describe your issue (min 20 characters)" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; margin-bottom: 12px; box-sizing: border-box; resize: vertical;"></textarea>

                                <button onclick="gilafChatbot.submitTicketFromChat()" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #C5A059 0%, #d4b068 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 14px; transition: transform 0.2s, box-shadow 0.2s;"
                                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(197,160,89,0.4)'"
                                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                    <i class="fas fa-paper-plane"></i> Create Ticket
                                </button>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `;

            const messagesContainer = document.getElementById('chatbotMessages');
            messagesContainer.insertAdjacentHTML('beforeend', formHTML);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 400);
    }

    async submitTicketFromChat() {
        const issueType = document.getElementById('chatbotTicketIssueType')?.value || '';
        const priority = document.getElementById('chatbotTicketPriority')?.value || 'medium';
        const description = (document.getElementById('chatbotTicketDescription')?.value || '').trim();

        if (!issueType) {
            this.addBotMessage('Please select an issue type.', null, false);
            return;
        }
        if (!description || description.length < 20) {
            this.addBotMessage('Please provide more details (at least 20 characters).', null, false);
            return;
        }

        this.addUserMessage('Submit ticket');
        this.showTypingIndicator();

        try {
            const res = await this.fetchWithTimeout(this.buildUrl('chatbot_create_ticket.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    issue_type: issueType,
                    priority: priority,
                    description: description
                })
            });

            // If endpoint path is wrong, server may return HTML 404 page.
            const contentType = res.headers.get('content-type') || '';
            if (!res.ok) {
                const bodyText = await res.text();
                throw new Error(`HTTP ${res.status}: ${bodyText.slice(0, 200)}`);
            }
            if (!contentType.includes('application/json')) {
                const bodyText = await res.text();
                throw new Error(`Unexpected response (not JSON): ${bodyText.slice(0, 200)}`);
            }
            const data = await res.json();
            this.removeTypingIndicator();

            if (!data.success) {
                if (data.action === 'login_required') {
                    this.addBotMessage('Please login first to create a ticket.', ['Login'], true);
                    return;
                }
                this.addBotMessage(data.message || 'Failed to create ticket. Please try again.', null, false);
                return;
            }

            const ticketId = data.ticket_id;
            const msg = `✅ Ticket created successfully.\n\nPlease note down this ticket number for your reference: ${ticketId}`;
            this.addBotMessage(msg, ['View My Tickets', 'Contact Support'], false);
            
            // Don't auto-show continue menu - keep chatbot stable and focused
        } catch (e) {
            console.error('[Chatbot] Ticket creation error:', e.message);
            this.removeTypingIndicator();
            const errorMsg = e.message.includes('timeout') 
                ? 'Request timed out. Please check your connection and try again.'
                : 'Something went wrong while creating the ticket. Please try again.';
            this.addBotMessage(errorMsg, null, false);
        }
    }

    requestCallback() {
        this.addUserMessage('Request a callback');
        this.showTypingIndicator();
        
        setTimeout(() => {
            this.removeTypingIndicator();
            this.addBotMessage(
                'Great! To request a callback, please provide:\n\n1. Your name\n2. Phone number\n3. Preferred time\n\nYou can also fill the callback form on our Contact page, or our team will reach out within 24 hours.',
                ['Go to Contact Page', 'Continue Chat']
            );
        }, 1000);
    }

    // ============ Live Chat Integration ============
    
    liveChatEnabled = false;
    liveChatPolling = null;
    lastMessageId = 0;
    chatStatus = 'bot';

    async requestLiveAgent() {
        this.addUserMessage('Talk to an agent');
        this.showTypingIndicator();

        try {
            // First start/get the live chat session
            const startRes = await this.fetchWithTimeout(this.buildUrl('live_chat_user_api.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'start_chat' })
            });
            const startData = await startRes.json();

            if (!startData.success) {
                throw new Error('Failed to start chat');
            }

            // Request agent
            const agentRes = await this.fetchWithTimeout(this.buildUrl('live_chat_user_api.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'request_agent' })
            });
            const agentData = await agentRes.json();

            this.removeTypingIndicator();

            if (agentData.success) {
                this.liveChatEnabled = true;
                this.chatStatus = 'waiting';
                this.addBotMessage(
                    '🙋 I\'m connecting you with a support agent. They\'ll be with you shortly!\n\nWhile you wait, feel free to describe your issue.',
                    null,
                    false
                );
                this.startLiveChatPolling();
            } else {
                this.addBotMessage('Sorry, unable to connect to an agent right now. Please try again later.', null, true);
            }
        } catch (error) {
            console.error('[Chatbot] Live agent request error:', error);
            this.removeTypingIndicator();
            this.addBotMessage('Sorry, unable to connect to an agent right now. Please try again later.', null, true);
        }
    }

    startLiveChatPolling() {
        if (this.liveChatPolling) return;

        this.liveChatPolling = setInterval(async () => {
            try {
                const res = await fetch(this.buildUrl(`live_chat_user_api.php?action=poll&last_id=${this.lastMessageId}`));
                const data = await res.json();

                if (data.success) {
                    // Update chat status
                    if (data.chat_status) {
                        this.chatStatus = data.chat_status;
                    }

                    // Add new messages from admin
                    if (data.messages && data.messages.length > 0) {
                        data.messages.forEach(msg => {
                            if (msg.sender_type === 'admin' || msg.sender_type === 'system') {
                                this.addLiveChatMessage(msg);
                                // Play notification sound when chatbot is open
                                if (this.isOpen && msg.sender_type === 'admin') {
                                    this.playNotificationSound();
                                }
                            }
                            this.lastMessageId = Math.max(this.lastMessageId, parseInt(msg.id));
                        });
                    }
                }
            } catch (error) {
                console.error('[Chatbot] Poll error:', error);
            }
        }, 2000);
    }

    stopLiveChatPolling() {
        if (this.liveChatPolling) {
            clearInterval(this.liveChatPolling);
            this.liveChatPolling = null;
        }
    }

    addLiveChatMessage(msg) {
        const isSystem = msg.sender_type === 'system';
        const avatar = isSystem ? 'info-circle' : 'headset';
        const bgColor = isSystem ? '#f0f0f0' : '#e3f2fd';

        const messageHTML = `
            <div class="chat-message bot">
                <div class="message-avatar" style="background: ${isSystem ? '#999' : '#1A3C34'};">
                    <i class="fas fa-${avatar}"></i>
                </div>
                <div class="message-content">
                    <div class="message-bubble" style="background: ${bgColor};">
                        ${isSystem ? '' : `<div style="font-size: 0.75rem; color: #1A3C34; font-weight: 600; margin-bottom: 5px;">${this.escapeHtml(msg.sender_name || 'Support Agent')}</div>`}
                        ${this.escapeHtml(msg.message).replace(/\n/g, '<br>')}
                    </div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            </div>
        `;
        this.appendMessage(messageHTML);
    }

    async sendLiveChatMessage(message) {
        try {
            const res = await this.fetchWithTimeout(this.buildUrl('live_chat_user_api.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'send_message', message: message })
            });
            const data = await res.json();

            if (data.success && data.message) {
                this.lastMessageId = Math.max(this.lastMessageId, parseInt(data.message.id));
            }

            return data;
        } catch (error) {
            console.error('[Chatbot] Send live message error:', error);
            return { success: false };
        }
    }

    playNotificationSound() {
        // Create audio element for notification
        const audio = new Audio(this.buildUrl('assets/sounds/notification.mp3'));
        audio.volume = 0.5;
        audio.play().catch(() => {});
    }

    showCallRequestForm() {
        this.addUserMessage('Request a callback');
        
        setTimeout(() => {
            const formHTML = `
                <div class="chat-message bot">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble" style="background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 20px; border-radius: 12px;">
                            <div style="color: white; margin-bottom: 15px;">
                                <i class="fas fa-phone-alt" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                <h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">Request a Callback</h4>
                                <p style="margin: 0; font-size: 13px; opacity: 0.9;">We'll call you back shortly!</p>
                            </div>
                            <div style="background: white; padding: 15px; border-radius: 8px;">
                                <label style="display:block; font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 6px;">Your Name</label>
                                <input type="text" id="callbackName" placeholder="Enter your name" 
                                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; margin-bottom: 12px; box-sizing: border-box;">
                                
                                <label style="display:block; font-size: 12px; font-weight: 600; color: #2c3e50; margin-bottom: 6px;">Phone Number</label>
                                <input type="tel" id="callbackPhone" placeholder="Enter 10-digit phone number" 
                                       style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; margin-bottom: 12px; box-sizing: border-box;">
                                
                                <button onclick="gilafChatbot.submitCallRequest()" 
                                        style="width: 100%; padding: 12px; background: linear-gradient(135deg, #C5A059 0%, #d4b068 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 14px;">
                                    <i class="fas fa-phone"></i> Request Call
                                </button>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `;
            this.appendMessage(formHTML);
        }, 500);
    }

    async submitCallRequest() {
        const name = document.getElementById('callbackName')?.value?.trim() || '';
        const phone = document.getElementById('callbackPhone')?.value?.trim() || '';

        if (!name || !phone) {
            this.addBotMessage('Please enter both your name and phone number.', null, false);
            return;
        }

        if (!/^[0-9]{10}$/.test(phone)) {
            this.addBotMessage('Please enter a valid 10-digit phone number.', null, false);
            return;
        }

        this.showTypingIndicator();

        try {
            const res = await this.fetchWithTimeout(this.buildUrl('chatbot_callback.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name, phone: phone, preferred_time: 'ASAP', message: 'Callback requested via chatbot' })
            });
            const data = await res.json();

            this.removeTypingIndicator();

            if (data.success) {
                this.addBotMessage('✅ Call request submitted successfully! Our team will call you at ' + phone + ' shortly.', ['Continue Chat'], false);
            } else {
                this.addBotMessage(data.message || 'Failed to submit call request. Please try again.', null, false);
            }
        } catch (error) {
            console.error('[Chatbot] Call request error:', error);
            this.removeTypingIndicator();
            this.addBotMessage('Failed to submit call request. Please try again.', null, false);
        }
    }

    showTypingIndicator() {
        const typingHTML = `
            <div class="chat-message bot typing-message">
                <div class="message-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    <div class="typing-indicator">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>
            </div>
        `;
        this.appendMessage(typingHTML);
    }

    removeTypingIndicator() {
        const typing = document.querySelector('.typing-message');
        if (typing) typing.remove();
    }

    appendMessage(html) {
        const messagesContainer = document.getElementById('chatbotMessages');
        
        // Defensive null check
        if (!messagesContainer) {
            console.warn('[Chatbot] Messages container not found');
            return;
        }
        
        messagesContainer.insertAdjacentHTML('beforeend', html);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    async showTrackingForm() {
        this.addBotMessage('I can help you track your order! 📦', null, false);
        
        // Add Track Your Order card with auto-populated dropdown
        setTimeout(async () => {
            // Show loading state initially
            const loadingHTML = `
                <div class="chat-message bot" id="trackingFormCard">
                    <div class="message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble" style="background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 20px; border-radius: 12px;">
                            <div style="color: white; margin-bottom: 15px;">
                                <i class="fas fa-shipping-fast" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                <h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">Track Your Order</h4>
                                <p style="margin: 0; font-size: 13px; opacity: 0.9;">Loading your orders...</p>
                            </div>
                            <div style="background: white; padding: 15px; border-radius: 8px; text-align: center;">
                                <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #1A3C34;"></i>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                </div>
            `;
            
            const messagesContainer = document.getElementById('chatbotMessages');
            messagesContainer.insertAdjacentHTML('beforeend', loadingHTML);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Fetch orders and replace with dropdown
            await this.loadTrackingDropdown();
        }, 800);
    }

    async loadTrackingDropdown() {
        try {
            const res = await this.fetchWithTimeout(this.buildUrl('chatbot_recent_orders.php'), {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const contentType = res.headers.get('content-type') || '';
            if (!res.ok || !contentType.includes('application/json')) {
                throw new Error('Failed to fetch orders');
            }

            const data = await res.json();
            
            // Replace loading card with dropdown
            const card = document.getElementById('trackingFormCard');
            if (!card) return;
            
            if (!data.success) {
                if (data.action === 'login_required') {
                    card.innerHTML = `
                        <div class="message-avatar"><i class="fas fa-robot"></i></div>
                        <div class="message-content">
                            <div class="message-bubble" style="background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 20px; border-radius: 12px;">
                                <div style="color: white; text-align: center;">
                                    <i class="fas fa-lock" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                    <p style="margin: 0;">Please login to track your orders.</p>
                                </div>
                            </div>
                            <div class="message-time">${this.getCurrentTime()}</div>
                        </div>
                    `;
                }
                return;
            }

            const orders = Array.isArray(data.orders) ? data.orders : [];
            
            if (orders.length === 0) {
                card.innerHTML = `
                    <div class="message-avatar"><i class="fas fa-robot"></i></div>
                    <div class="message-content">
                        <div class="message-bubble" style="background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 20px; border-radius: 12px;">
                            <div style="color: white; text-align: center;">
                                <i class="fas fa-box-open" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                <h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">No Orders Found</h4>
                                <p style="margin: 0; font-size: 13px; opacity: 0.9;">You haven't placed any orders yet.</p>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                `;
                return;
            }

            // Build dropdown options - show order reference + status
            const options = orders.map(o => {
                const ref = this.escapeHtml(o.reference || `ORD-${String(o.id).padStart(5, '0')}`);
                const status = this.escapeHtml(this.formatOrderStatus(o.status || ''));
                return `<option value="${o.id}">${ref} - ${status}</option>`;
            }).join('');

            card.innerHTML = `
                <div class="message-avatar"><i class="fas fa-robot"></i></div>
                <div class="message-content">
                    <div class="message-bubble" style="background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 20px; border-radius: 12px;">
                        <div style="color: white; margin-bottom: 15px;">
                            <i class="fas fa-shipping-fast" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                            <h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">Track Your Order</h4>
                            <p style="margin: 0; font-size: 13px; opacity: 0.9;">Select an order to check its status</p>
                        </div>
                        <div style="background: white; padding: 15px; border-radius: 8px;">
                            <select id="chatbotTrackingDropdown" 
                                    style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; margin-bottom: 10px; box-sizing: border-box; transition: border-color 0.3s;"
                                    onfocus="this.style.borderColor='#C5A059'"
                                    onblur="this.style.borderColor='#e0e0e0'"
                                    onchange="document.getElementById('chatbotTrackBtn').disabled = !this.value">
                                <option value="">-- Select an order --</option>
                                ${options}
                            </select>
                            <button id="chatbotTrackBtn" onclick="gilafChatbot.trackSelectedOrder()" disabled
                                    style="width: 100%; padding: 12px; background: linear-gradient(135deg, #C5A059 0%, #d4b068 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; transition: transform 0.2s, box-shadow 0.2s, opacity 0.3s;"
                                    onmouseover="if(!this.disabled) { this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(197,160,89,0.4)'; }"
                                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                <i class="fas fa-search"></i> Track Order
                            </button>
                        </div>
                    </div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            `;
            
            // Update button disabled state styling
            const btn = document.getElementById('chatbotTrackBtn');
            if (btn) {
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                const dropdown = document.getElementById('chatbotTrackingDropdown');
                if (dropdown) {
                    dropdown.addEventListener('change', function() {
                        if (this.value) {
                            btn.style.opacity = '1';
                            btn.style.cursor = 'pointer';
                        } else {
                            btn.style.opacity = '0.5';
                            btn.style.cursor = 'not-allowed';
                        }
                    });
                }
            }
        } catch (e) {
            console.error('[Chatbot] Load tracking dropdown error:', e.message);
            const card = document.getElementById('trackingFormCard');
            if (card) {
                const errorMsg = e.message.includes('timeout')
                    ? 'Request timed out. Please check your connection.'
                    : 'Unable to load orders. Please try again.';
                card.innerHTML = `
                    <div class="message-avatar"><i class="fas fa-robot"></i></div>
                    <div class="message-content">
                        <div class="message-bubble" style="background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%); padding: 20px; border-radius: 12px;">
                            <div style="color: white; text-align: center;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                <p style="margin: 0;">${errorMsg}</p>
                            </div>
                        </div>
                        <div class="message-time">${this.getCurrentTime()}</div>
                    </div>
                `;
            }
        }
    }

    trackSelectedOrder() {
        const dropdown = document.getElementById('chatbotTrackingDropdown');
        const orderId = dropdown?.value;
        
        if (!orderId) {
            this.addBotMessage('Please select an order from the dropdown.', null, false);
            return;
        }
        
        this.selectRecentOrder(parseInt(orderId, 10));
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    openSuggestionModal() {
        // Redirect to suggestions page
        const basePath = this.getBasePath();
        window.location.href = basePath + 'suggestions.php?source=chatbot';
    }

}

// Initialize chatbot when DOM is ready
let gilafChatbot;

// Initialization function with error boundary
function initializeChatbot() {
    // Prevent multiple initializations
    if (gilafChatbot) {
        console.warn('[Chatbot] Already initialized, skipping');
        return;
    }
    
    try {
        console.log('[Chatbot] Initializing...');
        gilafChatbot = new GilafChatbot();
        console.log('[Chatbot] Successfully initialized');
    } catch (error) {
        console.error('[Chatbot] Initialization failed:', error);
        // Chatbot fails gracefully - page continues to work
    }
}

// Try immediate initialization if DOM already loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeChatbot);
} else {
    // DOM already loaded, initialize immediately
    initializeChatbot();
}
