<?php
/**
 * Live Chat Admin Panel - WhatsApp Style
 * Real-time chat management with sound notifications
 */

require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_admin();

$page_title = "Live Chat Support";
$admin_id = $_SESSION['admin_id'] ?? 0;
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Get admin settings
$settings = ['sound_enabled' => 1, 'notification_enabled' => 1];
$settings_result = $conn->query("SELECT * FROM live_chat_admin_settings WHERE admin_id = $admin_id");
if ($settings_result && $settings_result->num_rows > 0) {
    $settings = $settings_result->fetch_assoc();
}

// Check if tables exist
$tables_exist = true;
$check = $conn->query("SHOW TABLES LIKE 'live_chats'");
if (!$check || $check->num_rows === 0) {
    $tables_exist = false;
}

include '../includes/admin_header.php';

if (!$tables_exist): ?>
<div style="max-width: 600px; margin: 50px auto; text-align: center; padding: 40px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
    <i class="fas fa-database" style="font-size: 4rem; color: #ffc107; margin-bottom: 20px;"></i>
    <h2 style="color: #1A3C34; margin-bottom: 15px;">Database Setup Required</h2>
    <p style="color: #666; margin-bottom: 20px;">The live chat tables have not been installed yet.</p>
    <a href="install_live_chat.php" class="btn btn-primary" style="background: #1A3C34; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; display: inline-block;">
        <i class="fas fa-download"></i> Install Live Chat System
    </a>
</div>
<?php include '../includes/admin_footer.php'; exit; endif; ?>

<style>
/* WhatsApp-style Chat Layout */
.live-chat-container {
    display: flex;
    height: calc(100vh - 120px);
    background: #f0f2f5;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

/* Left Panel - Chat List */
.chat-list-panel {
    width: 350px;
    background: white;
    border-right: 1px solid #e9ecef;
    display: flex;
    flex-direction: column;
}

.chat-list-header {
    padding: 15px 20px;
    background: #1A3C34;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-list-header h3 {
    margin: 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.header-actions button {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s;
}

.header-actions button:hover {
    background: rgba(255,255,255,0.2);
}

.header-actions button.active {
    background: #C9A961;
}

.chat-tabs {
    display: flex;
    border-bottom: 1px solid #e9ecef;
}

.chat-tab {
    flex: 1;
    padding: 12px;
    text-align: center;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 600;
    color: #666;
    position: relative;
    transition: all 0.3s;
}

.chat-tab.active {
    color: #1A3C34;
    border-bottom: 3px solid #1A3C34;
}

.chat-tab .badge {
    position: absolute;
    top: 8px;
    right: 20%;
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
}

.chat-list {
    flex: 1;
    overflow-y: auto;
}

.chat-item {
    display: flex;
    padding: 15px 20px;
    border-bottom: 1px solid #f0f2f5;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.chat-item:hover {
    background: #f8f9fa;
}

.chat-item.active {
    background: #e8f5e9;
}

.chat-item.unread {
    background: #fff8e1;
}

.chat-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #1A3C34, #2d5a4e);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.2rem;
    margin-right: 12px;
    flex-shrink: 0;
    position: relative;
}

.chat-avatar .online-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #28a745;
    border-radius: 50%;
    border: 2px solid white;
}

.chat-avatar .online-dot.offline {
    background: #6c757d;
}

/* Ringing Call Animation */
@keyframes ring {
    0% { transform: rotate(0) scale(1); }
    10% { transform: rotate(-15deg) scale(1.1); }
    20% { transform: rotate(15deg) scale(1.1); }
    30% { transform: rotate(-15deg) scale(1.1); }
    40% { transform: rotate(15deg) scale(1.1); }
    50% { transform: rotate(0) scale(1); }
    100% { transform: rotate(0) scale(1); }
}

@keyframes pulse-ring {
    0% { box-shadow: 0 0 0 0 rgba(26, 60, 52, 0.7); }
    50% { box-shadow: 0 0 0 10px rgba(26, 60, 52, 0); }
    100% { box-shadow: 0 0 0 0 rgba(26, 60, 52, 0); }
}

.call-request-item .chat-avatar {
    animation: pulse-ring 1.5s ease-out infinite;
}

.call-request-item .chat-avatar i {
    animation: ring 2s ease-in-out infinite;
    display: inline-block;
}

.chat-info {
    flex: 1;
    min-width: 0;
}

.chat-info h4 {
    margin: 0 0 4px 0;
    font-size: 0.95rem;
    color: #1A3C34;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-info h4 span {
    font-size: 0.75rem;
    color: #999;
    font-weight: 400;
}

.chat-info p {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 5px;
}

.unread-count {
    background: #25D366;
    color: white;
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 600;
}

.status-badge-small {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 500;
}

.status-badge-small.waiting { background: #fff3cd; color: #856404; }
.status-badge-small.active { background: #d4edda; color: #155724; }
.status-badge-small.bot { background: #cce5ff; color: #004085; }
.status-badge-small.closed { background: #e2e3e5; color: #6c757d; }

/* Right Panel - Chat Window */
.chat-window-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #e5ddd5;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23000000' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.chat-window-header {
    padding: 12px 20px;
    background: #1A3C34;
    color: white;
    display: flex;
    align-items: center;
    gap: 15px;
}

.chat-window-header .avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.chat-window-header .info {
    flex: 1;
}

.chat-window-header .info h4 {
    margin: 0;
    font-size: 1rem;
}

.chat-window-header .info p {
    margin: 0;
    font-size: 0.8rem;
    opacity: 0.8;
}

.chat-window-header .actions {
    display: flex;
    gap: 10px;
}

.chat-window-header .actions button {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s;
}

.chat-window-header .actions button:hover {
    background: rgba(255,255,255,0.2);
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px 60px;
    background: #e5ddd5;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23000000' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.message {
    display: flex;
    margin-bottom: 2px;
    padding: 0 10px;
}

.message.user {
    justify-content: flex-start;
}

.message.admin, .message.bot {
    justify-content: flex-end;
}

.message-bubble {
    max-width: 65%;
    padding: 8px 10px 8px 10px;
    position: relative;
    box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
}

.message.user .message-bubble {
    background: #ffffff;
    border-radius: 7.5px 7.5px 7.5px 0;
    margin-left: 0;
}

.message.user .message-bubble::before {
    content: '';
    position: absolute;
    left: -8px;
    top: 0;
    border-width: 0 8px 8px 0;
    border-style: solid;
    border-color: transparent #ffffff transparent transparent;
}

.message.admin .message-bubble {
    background: #d9fdd3;
    border-radius: 7.5px 7.5px 0 7.5px;
}

.message.admin .message-bubble::before {
    content: '';
    position: absolute;
    right: -8px;
    top: 0;
    border-width: 8px 8px 0 0;
    border-style: solid;
    border-color: #d9fdd3 transparent transparent transparent;
}

.message.bot .message-bubble {
    background: #e3f2fd;
    border-radius: 7.5px 7.5px 0 7.5px;
}

.message.system {
    justify-content: center;
}

.message.system .message-bubble {
    background: #fcf4cb;
    color: #54656f;
    font-size: 0.75rem;
    text-align: center;
    max-width: fit-content;
    padding: 6px 12px;
    border-radius: 7.5px;
    box-shadow: none;
}

.message-bubble .text {
    font-size: 0.875rem;
    line-height: 1.4;
    word-wrap: break-word;
    color: #111b21;
}

.message-bubble .meta {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
    font-size: 0.6875rem;
    color: #667781;
}

.message.admin .message-bubble .meta {
    color: #1e7e34;
}

.message-bubble .meta .time {
    font-size: 0.6875rem;
}

.message-bubble .meta .read-receipt {
    color: #53bdeb;
    font-size: 0.8rem;
}

/* Chat Input - WhatsApp Style */
.chat-input-area {
    padding: 10px 16px;
    background: #f0f2f5;
    display: flex;
    gap: 8px;
    align-items: center;
}

.chat-input-area .input-icons {
    display: flex;
    gap: 8px;
}

.chat-input-area .input-icons button {
    background: none;
    border: none;
    color: #54656f;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-input-area .input-icons button:hover {
    background: rgba(0,0,0,0.05);
}

.chat-input-area .input-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    background: white;
    border-radius: 8px;
    padding: 0 12px;
}

.chat-input-area .input-wrapper input {
    flex: 1;
    padding: 10px 8px;
    border: none;
    font-size: 0.95rem;
    outline: none;
    background: transparent;
}

.chat-input-area .input-wrapper .emoji-btn {
    background: none;
    border: none;
    color: #54656f;
    cursor: pointer;
    font-size: 1.3rem;
    padding: 5px;
}

.chat-input-area .send-btn {
    background: #1A3C34;
    border: none;
    color: white;
    width: 45px;
    height: 45px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.chat-input-area .send-btn:hover {
    background: #2d5a4e;
}

/* Emoji Picker */
.emoji-picker {
    position: absolute;
    bottom: 50px;
    left: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 10px;
    width: 320px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
}

.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 5px;
}

.emoji-grid .emoji {
    font-size: 1.4rem;
    cursor: pointer;
    padding: 5px;
    border-radius: 5px;
    text-align: center;
    transition: background 0.2s;
}

.emoji-grid .emoji:hover {
    background: #f0f2f5;
}

/* Attach Menu */
.attach-menu {
    position: absolute;
    bottom: 50px;
    left: 0;
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    padding: 8px;
    z-index: 1000;
}

.attach-menu button {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 15px;
    background: none;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-size: 0.9rem;
    color: #333;
}

.attach-menu button:hover {
    background: #f0f2f5;
}

.attach-menu button i {
    color: #1A3C34;
    width: 20px;
}

.input-icons {
    position: relative;
}

.input-wrapper {
    position: relative;
}

.chat-input-area button {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    border: none;
    background: #1A3C34;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.chat-input-area button:hover {
    background: #2d5a4e;
    transform: scale(1.05);
}

/* Empty State */
.empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #666;
    padding: 40px;
    text-align: center;
}

.empty-state i {
    font-size: 5rem;
    color: #ddd;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: #1A3C34;
}

/* Call Requests Panel */
.call-requests-panel {
    display: none;
}

.call-requests-panel.active {
    display: block;
}

.call-request-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f2f5;
    display: flex;
    align-items: center;
    gap: 15px;
}

.call-request-item .info {
    flex: 1;
}

.call-request-item .info h4 {
    margin: 0 0 5px 0;
    font-size: 0.95rem;
    color: #1A3C34;
}

.call-request-item .info p {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
}

.call-request-item .actions {
    display: flex;
    gap: 8px;
}

.call-request-item .actions button {
    padding: 8px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s;
}

.call-request-item .actions .call-btn {
    background: #28a745;
    color: white;
}

.call-request-item .actions .connect-btn {
    background: #1A3C34;
    color: white;
}

/* Typing indicator */
.typing-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 10px 15px;
    background: white;
    border-radius: 10px;
    width: fit-content;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #999;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

/* Responsive */
@media (max-width: 768px) {
    .chat-list-panel { width: 100%; }
    .chat-window-panel { display: none; }
    .chat-window-panel.active { display: flex; }
}
</style>

<div class="live-chat-container">
    <!-- Left Panel - Chat List -->
    <div class="chat-list-panel">
        <div class="chat-list-header">
            <h3><i class="fas fa-comments"></i> Live Support</h3>
            <div class="header-actions">
                <button id="soundToggle" class="<?= $settings['sound_enabled'] ? 'active' : '' ?>" title="Toggle Sound">
                    <i class="fas fa-<?= $settings['sound_enabled'] ? 'volume-up' : 'volume-mute' ?>"></i>
                </button>
                <button id="refreshChats" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        
        <div class="chat-tabs">
            <button class="chat-tab active" data-tab="chats">
                <i class="fas fa-comments"></i> Chats
                <span class="badge" id="chatsBadge" style="display: none;">0</span>
            </button>
            <button class="chat-tab" data-tab="calls">
                <i class="fas fa-phone"></i> Calls
                <span class="badge" id="callsBadge" style="display: none;">0</span>
            </button>
        </div>
        
        <!-- Chats List -->
        <div class="chat-list" id="chatsList">
            <div class="empty-state" style="padding: 40px;">
                <i class="fas fa-inbox"></i>
                <p>No active chats</p>
            </div>
        </div>
        
        <!-- Call Requests List -->
        <div class="chat-list call-requests-panel" id="callsList">
            <div class="empty-state" style="padding: 40px;">
                <i class="fas fa-phone-slash"></i>
                <p>No call requests</p>
            </div>
        </div>
    </div>
    
    <!-- Right Panel - Chat Window -->
    <div class="chat-window-panel" id="chatWindow">
        <div class="empty-state">
            <i class="fas fa-comments"></i>
            <h3>Welcome to Live Chat Support</h3>
            <p>Select a conversation from the left to start chatting</p>
        </div>
    </div>
</div>

<!-- Notification Sound -->
<audio id="notificationSound" preload="auto">
    <source src="../assets/sounds/notification.mp3" type="audio/mpeg">
    <source src="../assets/sounds/notification.ogg" type="audio/ogg">
</audio>

<script>
const LiveChatAdmin = {
    adminId: <?= $admin_id ?>,
    adminName: '<?= addslashes($admin_name) ?>',
    soundEnabled: <?= $settings['sound_enabled'] ? 'true' : 'false' ?>,
    currentChatId: null,
    chats: {},
    pollInterval: null,
    lastMessageId: 0,
    
    init() {
        this.bindEvents();
        this.loadChats();
        this.loadCallRequests();
        this.startPolling();
        this.requestNotificationPermission();
    },
    
    bindEvents() {
        // Tab switching
        document.querySelectorAll('.chat-tab').forEach(tab => {
            tab.addEventListener('click', () => this.switchTab(tab.dataset.tab));
        });
        
        // Sound toggle
        document.getElementById('soundToggle').addEventListener('click', () => this.toggleSound());
        
        // Refresh button
        document.getElementById('refreshChats').addEventListener('click', () => {
            this.loadChats();
            this.loadCallRequests();
        });
    },
    
    switchTab(tab) {
        document.querySelectorAll('.chat-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        
        document.getElementById('chatsList').style.display = tab === 'chats' ? 'block' : 'none';
        document.getElementById('callsList').style.display = tab === 'calls' ? 'block' : 'none';
        document.getElementById('callsList').classList.toggle('active', tab === 'calls');
    },
    
    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        const btn = document.getElementById('soundToggle');
        btn.classList.toggle('active', this.soundEnabled);
        btn.innerHTML = `<i class="fas fa-${this.soundEnabled ? 'volume-up' : 'volume-mute'}"></i>`;
        
        // Save preference
        fetch('live_chat_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_settings', sound_enabled: this.soundEnabled ? 1 : 0 })
        });
    },
    
    playNotification() {
        if (!this.soundEnabled) return;
        
        // Try HTML audio first
        const audio = document.getElementById('notificationSound');
        if (audio && audio.src) {
            audio.currentTime = 0;
            audio.play().catch(() => this.playGeneratedRing());
        } else {
            this.playGeneratedRing();
        }
    },
    
    playGeneratedRing() {
        // Generate notification beep using Web Audio API
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            
            // Two short beeps
            [0, 0.15].forEach(startTime => {
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                oscillator.frequency.value = 880;
                oscillator.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime + startTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + startTime + 0.1);
                
                oscillator.start(audioCtx.currentTime + startTime);
                oscillator.stop(audioCtx.currentTime + startTime + 0.1);
            });
        } catch (e) {
            console.log('Audio not supported');
        }
    },
    
    playCallRing() {
        // Generate phone ringing sound - repeating pattern
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            
            // Phone ring pattern: ring-ring, pause, ring-ring (like a real phone)
            const ringPattern = [
                {time: 0, freq: 440, dur: 0.4},
                {time: 0.5, freq: 440, dur: 0.4},
                {time: 1.5, freq: 440, dur: 0.4},
                {time: 2.0, freq: 440, dur: 0.4}
            ];
            
            ringPattern.forEach(ring => {
                const oscillator = audioCtx.createOscillator();
                const oscillator2 = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                
                oscillator.connect(gainNode);
                oscillator2.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                
                // Two frequencies for realistic ring
                oscillator.frequency.value = ring.freq;
                oscillator2.frequency.value = ring.freq * 1.2;
                oscillator.type = 'sine';
                oscillator2.type = 'sine';
                
                gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime + ring.time);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + ring.time + ring.dur);
                
                oscillator.start(audioCtx.currentTime + ring.time);
                oscillator.stop(audioCtx.currentTime + ring.time + ring.dur);
                oscillator2.start(audioCtx.currentTime + ring.time);
                oscillator2.stop(audioCtx.currentTime + ring.time + ring.dur);
            });
        } catch (e) {
            console.log('Audio not supported');
        }
    },
    
    showBrowserNotification(title, body) {
        if (Notification.permission === 'granted') {
            new Notification(title, { body, icon: '../assets/images/logo.png' });
        }
    },
    
    requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    },
    
    async loadChats() {
        try {
            const response = await fetch('live_chat_api.php?action=get_chats');
            const data = await response.json();
            
            if (data.success) {
                this.renderChatList(data.chats);
                this.updateBadge('chatsBadge', data.unread_count || 0);
            }
        } catch (error) {
            console.error('Failed to load chats:', error);
        }
    },
    
    renderChatList(chats) {
        const container = document.getElementById('chatsList');
        
        if (!chats || chats.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="padding: 40px;">
                    <i class="fas fa-inbox"></i>
                    <p>No active chats</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = chats.map(chat => {
            const initials = (chat.customer_name || 'Guest').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
            const isActive = this.currentChatId == chat.id;
            const hasUnread = chat.unread_count > 0;
            const isClosed = chat.status === 'closed';
            
            return `
                <div class="chat-item ${isActive ? 'active' : ''} ${hasUnread ? 'unread' : ''} ${isClosed ? 'closed' : ''}" 
                     onclick="LiveChatAdmin.selectChat(${chat.id})"
                     data-chat-id="${chat.id}"
                     style="${isClosed ? 'opacity: 0.7; background: #f5f5f5;' : ''}">
                    <div class="chat-avatar" style="${isClosed ? 'opacity: 0.5;' : ''}">
                        ${initials}
                        <div class="online-dot ${chat.is_online && !isClosed ? '' : 'offline'}"></div>
                    </div>
                    <div class="chat-info">
                        <h4>
                            ${this.escapeHtml(chat.customer_name || 'Guest User')}
                            <span>${this.formatTime(chat.last_activity)}</span>
                        </h4>
                        <p>${isClosed ? '🔒 ' : ''}${this.escapeHtml(chat.last_message || 'No messages yet')}</p>
                    </div>
                    <div class="chat-meta">
                        ${hasUnread ? `<span class="unread-count">${chat.unread_count}</span>` : ''}
                        <span class="status-badge-small ${chat.status}">${isClosed ? 'closed' : chat.status}</span>
                    </div>
                </div>
            `;
        }).join('');
    },
    
    async loadCallRequests() {
        try {
            const response = await fetch('live_chat_api.php?action=get_call_requests');
            const data = await response.json();
            
            if (data.success) {
                this.renderCallList(data.requests);
                this.updateBadge('callsBadge', data.pending_count || 0);
            }
        } catch (error) {
            console.error('Failed to load call requests:', error);
        }
    },
    
    renderCallList(requests) {
        const container = document.getElementById('callsList');
        
        if (!requests || requests.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="padding: 40px;">
                    <i class="fas fa-phone-slash"></i>
                    <p>No call requests</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = requests.map(req => `
            <div class="call-request-item" data-request-id="${req.id}">
                <div class="chat-avatar">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="info">
                    <h4>${this.escapeHtml(req.customer_name)}</h4>
                    <p><i class="fas fa-phone"></i> ${this.escapeHtml(req.customer_phone)}</p>
                    <p style="font-size: 0.75rem; color: #999;">${this.formatTime(req.created_at)}</p>
                </div>
                <div class="actions" style="display:flex; gap:6px;">
                    ${req.status === 'pending' ? `
                        <button onclick="LiveChatAdmin.connectCall(${req.id})" title="Connected" style="width:32px; height:32px; background:#1A3C34; color:white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-phone-volume" style="font-size:14px;"></i>
                        </button>
                        <button onclick="LiveChatAdmin.markNotConnected(${req.id})" title="Not Connected" style="width:32px; height:32px; background:#6c757d; color:white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-phone-slash" style="font-size:14px;"></i>
                        </button>
                    ` : ''}
                    <button onclick="LiveChatAdmin.makeCall('${req.customer_phone}')" title="Call" style="width:32px; height:32px; background:#28a745; color:white; border:none; border-radius:6px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <i class="fas fa-phone" style="font-size:14px;"></i>
                    </button>
                </div>
            </div>
        `).join('');
    },
    
    updateBadge(id, count) {
        const badge = document.getElementById(id);
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-block' : 'none';
        }
    },
    
    async selectChat(chatId) {
        this.currentChatId = chatId;
        
        // Update active state
        document.querySelectorAll('.chat-item').forEach(item => {
            item.classList.toggle('active', item.dataset.chatId == chatId);
            item.classList.remove('unread');
        });
        
        // Load chat messages
        try {
            const response = await fetch(`live_chat_api.php?action=get_messages&chat_id=${chatId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderChatWindow(data.chat, data.messages);
                this.markAsRead(chatId);
            }
        } catch (error) {
            console.error('Failed to load messages:', error);
        }
    },
    
    renderChatWindow(chat, messages) {
        const container = document.getElementById('chatWindow');
        const customerName = chat.customer_name || 'Guest';
        const initials = customerName.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
        const onlineStatus = chat.is_online ? 
            '<span style="color: #25D366;">● Online</span>' : 
            '<span style="color: #999;">Last seen ' + this.formatTime(chat.last_activity) + '</span>';
        
        container.innerHTML = `
            <div class="chat-window-header">
                <div class="avatar">${initials}</div>
                <div class="info">
                    <h4>${this.escapeHtml(customerName)}</h4>
                    <p>${onlineStatus}</p>
                </div>
                <div class="actions">
                    ${chat.status !== 'active' ? `
                        <button onclick="LiveChatAdmin.takeoverChat(${chat.id})" title="Take Over Chat">
                            <i class="fas fa-user-check"></i>
                        </button>
                    ` : ''}
                    <button onclick="LiveChatAdmin.endChat(${chat.id})" title="End Chat & Delete" style="background: rgba(220,53,69,0.2);">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <button onclick="LiveChatAdmin.deselectChat()" title="Close Window">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="chat-messages" id="chatMessages">
                ${this.renderMessages(messages, chat.customer_name)}
            </div>
            <div class="chat-input-area">
                <div class="input-icons">
                    <button onclick="LiveChatAdmin.toggleAttachMenu()" title="Attach"><i class="fas fa-plus"></i></button>
                    <div class="attach-menu" id="attachMenu" style="display:none;">
                        <button onclick="LiveChatAdmin.attachImage()"><i class="fas fa-image"></i> Image</button>
                        <button onclick="LiveChatAdmin.attachFile()"><i class="fas fa-file"></i> File</button>
                    </div>
                </div>
                <div class="input-wrapper">
                    <button class="emoji-btn" onclick="LiveChatAdmin.toggleEmojiPicker()" title="Emoji"><i class="far fa-smile"></i></button>
                    <input type="text" id="messageInput" placeholder="Type a message" 
                           onkeypress="if(event.key==='Enter') LiveChatAdmin.sendMessage()">
                    <div class="emoji-picker" id="emojiPicker" style="display:none;">
                        <div class="emoji-grid">
                            ${['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','😉','😌','😍','🥰','😘','😗','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','😎','🤓','🧐','😕','😟','🙁','☹️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','👍','👎','👏','🙌','👋','🤝','💪','❤️','🔥','✨','🎉','💯','✅','⭐','💡','📌'].map(e => `<span class="emoji" onclick="LiveChatAdmin.insertEmoji('${e}')">${e}</span>`).join('')}
                        </div>
                    </div>
                </div>
                <button class="send-btn" onclick="LiveChatAdmin.sendMessage()" title="Send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        `;
        
        this.scrollToBottom();
    },
    
    renderMessages(messages, customerName = 'Customer') {
        if (!messages || messages.length === 0) {
            return '<div class="message system"><div class="message-bubble"><div class="text">Chat started</div></div></div>';
        }
        
        return messages.map(msg => {
            const type = msg.sender_type;
            const time = this.formatTimeShort(msg.created_at);
            const senderName = msg.sender_name || (type === 'user' ? customerName : type);
            
            if (type === 'system') {
                return `
                    <div class="message system">
                        <div class="message-bubble">
                            <div class="text">${this.escapeHtml(msg.message)}</div>
                        </div>
                    </div>
                `;
            }
            
            return `
                <div class="message ${type}">
                    <div class="message-bubble">
                        <div class="text">${this.escapeHtml(msg.message)}</div>
                        <div class="meta">
                            <span class="time">${time}</span>
                            ${type === 'admin' ? '<i class="fas fa-check-double read-receipt"></i>' : ''}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },
    
    formatTimeShort(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        return date.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        }).toLowerCase();
    },
    
    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message || !this.currentChatId) return;
        
        input.value = '';
        
        try {
            const response = await fetch('live_chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send_message',
                    chat_id: this.currentChatId,
                    message: message
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.appendMessage(data.message);
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Failed to send message:', error);
        }
    },
    
    appendMessage(msg) {
        const container = document.getElementById('chatMessages');
        if (!container) return;
        
        const html = this.renderMessages([msg]);
        container.insertAdjacentHTML('beforeend', html);
    },
    
    toggleEmojiPicker() {
        const picker = document.getElementById('emojiPicker');
        const attachMenu = document.getElementById('attachMenu');
        if (attachMenu) attachMenu.style.display = 'none';
        if (picker) {
            picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
        }
    },
    
    insertEmoji(emoji) {
        const input = document.getElementById('messageInput');
        if (input) {
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            input.value = text.substring(0, start) + emoji + text.substring(end);
            input.focus();
            input.selectionStart = input.selectionEnd = start + emoji.length;
        }
        document.getElementById('emojiPicker').style.display = 'none';
    },
    
    toggleAttachMenu() {
        const menu = document.getElementById('attachMenu');
        const picker = document.getElementById('emojiPicker');
        if (picker) picker.style.display = 'none';
        if (menu) {
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
    },
    
    attachImage() {
        document.getElementById('attachMenu').style.display = 'none';
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = (e) => this.handleFileUpload(e.target.files[0], 'image');
        input.click();
    },
    
    attachFile() {
        document.getElementById('attachMenu').style.display = 'none';
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.pdf,.doc,.docx,.txt,.xls,.xlsx';
        input.onchange = (e) => this.handleFileUpload(e.target.files[0], 'file');
        input.click();
    },
    
    async handleFileUpload(file, type) {
        if (!file || !this.currentChatId) return;
        
        // Check file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('File too large. Maximum size is 5MB.');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('chat_id', this.currentChatId);
        formData.append('type', type);
        
        try {
            const response = await fetch('live_chat_upload.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (data.success) {
                // Send file message
                const fileMessage = type === 'image' 
                    ? `📷 [Image: ${file.name}]\n${data.url}`
                    : `📎 [File: ${file.name}]\n${data.url}`;
                
                await this.sendFileMessage(fileMessage, data.url, type);
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Failed to upload file. Please try again.');
        }
    },
    
    async sendFileMessage(message, url, type) {
        try {
            const response = await fetch('live_chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send_message',
                    chat_id: this.currentChatId,
                    message: message
                })
            });
            
            const data = await response.json();
            if (data.success) {
                this.appendMessage(data.message);
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Failed to send file message:', error);
        }
    },
    
    async takeoverChat(chatId) {
        try {
            const response = await fetch('live_chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'takeover_chat', chat_id: chatId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.selectChat(chatId);
                this.loadChats();
            }
        } catch (error) {
            console.error('Failed to takeover chat:', error);
        }
    },
    
    deselectChat() {
        // Just close the chat window view, don't end the chat
        this.currentChatId = null;
        document.getElementById('chatWindow').innerHTML = `
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>Welcome to Live Chat Support</h3>
                <p>Select a conversation from the left to start chatting</p>
            </div>
        `;
    },
    
    async endChat(chatId) {
        if (!confirm('End this chat and delete all messages? This cannot be undone.')) return;
        
        try {
            const response = await fetch('live_chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'end_chat', chat_id: chatId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.currentChatId = null;
                document.getElementById('chatWindow').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>Chat Ended</h3>
                        <p>Select another conversation from the left</p>
                    </div>
                `;
                this.loadChats();
            }
        } catch (error) {
            console.error('Failed to end chat:', error);
        }
    },
    
    async markAsRead(chatId) {
        try {
            await fetch('live_chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', chat_id: chatId })
            });
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    },
    
    async connectCall(requestId) {
        try {
            const response = await fetch('live_chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'connect_call', request_id: requestId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.loadCallRequests();
            }
        } catch (error) {
            console.error('Failed to connect call:', error);
        }
    },
    
    makeCall(phone) {
        // Only open phone app - don't clear the log
        window.open(`tel:${phone}`, '_blank');
    },
    
    async markNotConnected(requestId) {
        try {
            const response = await fetch('live_chat_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_not_connected', request_id: requestId })
            });
            
            const data = await response.json();
            if (data.success) {
                this.loadCallRequests();
            }
        } catch (error) {
            console.error('Failed to mark as not connected:', error);
        }
    },
    
    startPolling() {
        // Poll for new messages every 2 seconds
        this.pollInterval = setInterval(() => this.pollUpdates(), 2000);
    },
    
    async pollUpdates() {
        try {
            const response = await fetch(`live_chat_api.php?action=poll_updates&last_id=${this.lastMessageId}&current_chat=${this.currentChatId || 0}`);
            const data = await response.json();
            
            if (data.success) {
                // Update last message ID
                if (data.last_id) {
                    this.lastMessageId = data.last_id;
                }
                
                // New messages in current chat
                if (data.new_messages && data.new_messages.length > 0) {
                    data.new_messages.forEach(msg => this.appendMessage(msg));
                    this.scrollToBottom();
                }
                
                // New chat or unread update
                if (data.has_updates) {
                    this.loadChats();
                    
                    // Play sound for new messages from users
                    if (data.play_sound) {
                        this.playNotification();
                        this.showBrowserNotification('New Message', data.notification_text || 'You have a new message');
                    }
                }
                
                // New call requests
                if (data.new_calls) {
                    this.loadCallRequests();
                    if (this.soundEnabled) {
                        this.playCallRing(); // Special ringing for calls
                        this.showBrowserNotification('📞 Incoming Call', 'New call request received!');
                    }
                }
            }
        } catch (error) {
            console.error('Poll error:', error);
        }
    },
    
    scrollToBottom() {
        const container = document.getElementById('chatMessages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    },
    
    formatTime(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) return 'now';
        if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
        if (diff < 86400000) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        return date.toLocaleDateString();
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => LiveChatAdmin.init());
</script>

<?php include '../includes/admin_footer.php'; ?>
