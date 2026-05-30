<?php
/**
 * Email Settings - Admin Panel
 * Professional 2-tab interface for managing email configurations and task assignments
 */
$pageTitle = 'Email Settings — Gilaf Admin';
$adminPage = 'email_settings';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* ─── Email Settings Page ─── */
.es-page { max-width: 1300px; }
.es-tabs { display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; }
.es-tab {
    padding: 12px 28px; font-size: 14px; font-weight: 600; color: #6b7280; cursor: pointer;
    border-bottom: 3px solid transparent; transition: all .2s; user-select: none;
    display: flex; align-items: center; gap: 8px;
}
.es-tab:hover { color: #1A3C34; }
.es-tab.active { color: #1A3C34; border-bottom-color: #C5A059; }
.es-tab-content { display: none; }
.es-tab-content.active { display: block; }

/* Cards */
.es-card {
    background: #fff; border-radius: 12px; border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0,0,0,.04); overflow: hidden;
}
.es-card-head {
    padding: 16px 20px; border-bottom: 1px solid #f3f4f6;
    display: flex; justify-content: space-between; align-items: center;
}
.es-card-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.es-card-body { padding: 20px; }

/* Email List */
.em-row {
    display: flex; align-items: center; gap: 14px; padding: 14px 0;
    border-bottom: 1px solid #f3f4f6; transition: background .15s;
}
.em-row:last-child { border-bottom: none; }
.em-row:hover { background: #f9fafb; border-radius: 8px; padding-left: 8px; padding-right: 8px; margin: 0 -8px; }
.em-icon {
    width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.em-icon.gmail { background: #fef2f2; color: #dc2626; }
.em-icon.domain { background: #eff6ff; color: #2563eb; }
.em-icon.outlook { background: #f0fdf4; color: #16a34a; }
.em-icon.custom { background: #faf5ff; color: #9333ea; }
.em-info { flex: 1; min-width: 0; }
.em-email { font-weight: 600; font-size: 14px; color: #1f2937; }
.em-name { font-size: 12px; color: #6b7280; margin-top: 1px; }
.em-badges { display: flex; gap: 6px; flex-wrap: wrap; }
.em-badge {
    padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
}
.em-badge.active { background: #dcfce7; color: #166534; }
.em-badge.inactive { background: #fee2e2; color: #991b1b; }
.em-badge.verified { background: #dbeafe; color: #1e40af; }
.em-badge.unverified { background: #fef3c7; color: #92400e; }
.em-badge.failed { background: #fee2e2; color: #991b1b; }
.em-actions { display: flex; gap: 6px; }
.em-btn {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid #e5e7eb;
    background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: #6b7280; transition: all .15s;
}
.em-btn:hover { background: #f3f4f6; color: #1f2937; }
.em-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.em-btn.success:hover { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }

/* Add Email Button */
.btn-add-email {
    padding: 10px 22px; border-radius: 10px; border: 2px dashed #C5A059; background: #fffbf0;
    color: #C5A059; font-weight: 700; font-size: 13px; cursor: pointer; transition: all .2s;
    display: flex; align-items: center; gap: 8px; width: 100%;
    justify-content: center; margin-top: 16px;
}
.btn-add-email:hover { background: #C5A059; color: #fff; border-style: solid; }

/* Modal */
.es-modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.5);
    z-index: 9999; display: none; align-items: center; justify-content: center;
    backdrop-filter: blur(4px);
}
.es-modal-overlay.show { display: flex; }
.es-modal {
    background: #fff; border-radius: 16px; width: 640px; max-width: 95vw; max-height: 90vh;
    overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.es-modal-head {
    padding: 20px 24px; border-bottom: 1px solid #e5e7eb;
    display: flex; justify-content: space-between; align-items: center;
    position: sticky; top: 0; background: #fff; z-index: 1;
}
.es-modal-head h3 { margin: 0; font-size: 18px; font-weight: 700; }
.es-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #6b7280; padding: 4px; }
.es-modal-body { padding: 24px; }

/* Form */
.es-form-group { margin-bottom: 16px; }
.es-form-group label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .5px; }
.es-form-group .hint { font-size: 11px; color: #9ca3af; margin-top: 3px; }
.es-input, .es-select {
    width: 100%; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;
    transition: border-color .15s, box-shadow .15s; background: #fff;
}
.es-input:focus, .es-select:focus { border-color: #C5A059; box-shadow: 0 0 0 3px rgba(197,160,89,.15); outline: none; }
.es-textarea { resize: vertical; min-height: 60px; }
.es-row { display: flex; gap: 16px; }
.es-row > * { flex: 1; }

/* Provider Quick Buttons */
.provider-btns { display: flex; gap: 8px; margin-bottom: 16px; }
.provider-btn {
    padding: 8px 16px; border-radius: 8px; border: 2px solid #e5e7eb; background: #fff;
    cursor: pointer; font-size: 13px; font-weight: 600; transition: all .15s;
    display: flex; align-items: center; gap: 6px;
}
.provider-btn.selected { border-color: #C5A059; background: #fffbf0; }
.provider-btn:hover { border-color: #C5A059; }

/* Task Assignments */
.task-group-title {
    font-size: 13px; font-weight: 700; color: #1A3C34; text-transform: uppercase;
    letter-spacing: 1px; padding: 12px 0 8px; border-bottom: 2px solid #1A3C34; margin-bottom: 12px;
}
.task-row {
    display: flex; align-items: center; gap: 14px; padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}
.task-row:last-child { border-bottom: none; }
.task-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.task-icon.orders { background: #dbeafe; color: #2563eb; }
.task-icon.security { background: #fef2f2; color: #dc2626; }
.task-icon.support { background: #f0fdf4; color: #16a34a; }
.task-icon.marketing { background: #faf5ff; color: #9333ea; }
.task-icon.general { background: #fef3c7; color: #d97706; }
.task-info { flex: 1; }
.task-label { font-weight: 600; font-size: 14px; color: #1f2937; }
.task-key { font-size: 11px; color: #9ca3af; font-family: monospace; }
.task-select {
    min-width: 260px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px;
    font-size: 13px; background: #fff; cursor: pointer;
}
.task-select:focus { border-color: #C5A059; box-shadow: 0 0 0 3px rgba(197,160,89,.15); outline: none; }
.task-select option { padding: 4px; }

/* Toast */
.es-toast {
    position: fixed; top: 20px; right: 20px; z-index: 99999; padding: 14px 24px;
    border-radius: 10px; font-size: 14px; font-weight: 600; color: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,.15); transform: translateX(120%); transition: transform .3s;
}
.es-toast.show { transform: translateX(0); }
.es-toast.success { background: #059669; }
.es-toast.error { background: #dc2626; }
.es-toast.info { background: #2563eb; }

/* Stats */
.es-stats { display: flex; gap: 16px; margin-bottom: 24px; }
.es-stat {
    flex: 1; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
    padding: 16px 20px; display: flex; align-items: center; gap: 14px;
}
.es-stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.es-stat-icon.green { background: #dcfce7; color: #16a34a; }
.es-stat-icon.blue { background: #dbeafe; color: #2563eb; }
.es-stat-icon.gold { background: #fef3c7; color: #d97706; }
.es-stat-icon.red { background: #fee2e2; color: #dc2626; }
.es-stat-num { font-size: 22px; font-weight: 800; color: #1f2937; }
.es-stat-label { font-size: 12px; color: #6b7280; }

/* Help Guide */
.help-section { margin-bottom: 28px; }
.help-section-title {
    font-size: 17px; font-weight: 800; color: #1A3C34; margin-bottom: 14px;
    display: flex; align-items: center; gap: 10px;
    padding-bottom: 10px; border-bottom: 2px solid #e5e7eb;
}
.help-section-title .help-num {
    width: 32px; height: 32px; border-radius: 50%; background: #C5A059; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 800; flex-shrink: 0;
}
.help-steps { list-style: none; padding: 0; margin: 0; }
.help-steps li {
    padding: 10px 0 10px 44px; position: relative; border-left: 2px solid #e5e7eb; margin-left: 15px;
    font-size: 14px; color: #374151; line-height: 1.7;
}
.help-steps li:last-child { border-left-color: transparent; }
.help-steps li::before {
    content: attr(data-step); position: absolute; left: -13px; top: 8px;
    width: 24px; height: 24px; border-radius: 50%; background: #f3f4f6; border: 2px solid #d1d5db;
    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #6b7280;
}
.help-steps li b { color: #1f2937; }
.help-steps li code {
    background: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-size: 13px;
    color: #c026d3; font-family: 'Courier New', monospace;
}
.help-tip {
    background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 14px 18px;
    margin: 10px 0 10px 44px; font-size: 13px; color: #1e40af; line-height: 1.6;
}
.help-tip i { margin-right: 6px; }
.help-warn {
    background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px; padding: 14px 18px;
    margin: 10px 0 10px 44px; font-size: 13px; color: #92400e; line-height: 1.6;
}
.help-warn i { margin-right: 6px; }
.help-danger {
    background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 18px;
    margin: 10px 0 10px 44px; font-size: 13px; color: #991b1b; line-height: 1.6;
}
.help-danger i { margin-right: 6px; }
.help-table { width: 100%; border-collapse: collapse; margin: 12px 0 12px 44px; font-size: 13px; max-width: calc(100% - 44px); }
.help-table th { background: #f9fafb; padding: 10px 14px; text-align: left; font-weight: 700; color: #374151; border: 1px solid #e5e7eb; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
.help-table td { padding: 10px 14px; border: 1px solid #e5e7eb; color: #4b5563; }
.help-table tr:hover td { background: #f9fafb; }
.help-accordion { margin-left: 44px; margin-bottom: 8px; }
.help-accordion summary {
    cursor: pointer; font-weight: 700; font-size: 14px; color: #1A3C34; padding: 10px 14px;
    background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; list-style: none;
    display: flex; align-items: center; gap: 8px;
}
.help-accordion summary::-webkit-details-marker { display: none; }
.help-accordion summary::before { content: '\f054'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 11px; transition: transform .2s; }
.help-accordion[open] summary::before { transform: rotate(90deg); }
.help-accordion .help-acc-body { padding: 14px; font-size: 13px; color: #4b5563; line-height: 1.7; }

/* Activity Log Table */
.log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.log-table th { background: #f9fafb; padding: 10px 14px; text-align: left; font-weight: 700; color: #374151; border-bottom: 2px solid #e5e7eb; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
.log-table td { padding: 10px 14px; border-bottom: 1px solid #f3f4f6; color: #4b5563; vertical-align: top; }
.log-table tbody tr:hover td { background: #f9fafb; }
.log-badge {
    display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px;
    border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap;
}
.log-badge.success { background: #dcfce7; color: #166534; }
.log-badge.failed { background: #fee2e2; color: #991b1b; }
.log-badge.fallback { background: #fef3c7; color: #92400e; }
.log-detail-btn {
    background: none; border: 1px solid #d1d5db; border-radius: 6px; padding: 3px 8px;
    font-size: 11px; color: #6b7280; cursor: pointer; white-space: nowrap;
}
.log-detail-btn:hover { background: #f3f4f6; color: #1f2937; }
.log-detail-popup {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); z-index: 100000;
    background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.2); padding: 24px; max-width: 550px; width: 90%;
}
.log-detail-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 99999; }

/* Responsive */
@media (max-width: 768px) {
    .es-stats { flex-wrap: wrap; }
    .es-stat { min-width: calc(50% - 8px); }
    .es-row { flex-direction: column; gap: 0; }
    .task-row { flex-wrap: wrap; }
    .task-select { min-width: 100%; }
    .help-table { margin-left: 20px; max-width: calc(100% - 20px); }
    .help-steps li { padding-left: 28px; margin-left: 10px; }
    .help-tip, .help-warn, .help-danger, .help-accordion { margin-left: 20px; }
}
</style>

<div class="es-page">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1" style="color:#1A3C34;">📧 Email Settings</h4>
            <p class="text-muted mb-0" style="font-size:13px;">Manage SMTP configurations, credentials, and assign emails to tasks</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="es-stats" id="emailStats"></div>

    <!-- Tabs -->
    <div class="es-tabs">
        <div class="es-tab active" data-tab="settings" onclick="switchTab('settings')">
            <i class="fas fa-cog"></i> Email Accounts & SMTP
        </div>
        <div class="es-tab" data-tab="tasks" onclick="switchTab('tasks')">
            <i class="fas fa-tasks"></i> Task Assignments
        </div>
        <div class="es-tab" data-tab="help" onclick="switchTab('help')">
            <i class="fas fa-book-open"></i> Help & Setup Guide
        </div>
        <div class="es-tab" data-tab="logs" onclick="switchTab('logs'); loadEmailLogs();">
            <i class="fas fa-history"></i> Activity Log <span id="logAlertBadge" style="display:none;background:#dc2626;color:#fff;font-size:10px;padding:2px 7px;border-radius:10px;margin-left:4px;"></span>
        </div>
    </div>

    <!-- Tab 1: Email Settings -->
    <div class="es-tab-content active" id="tab-settings">
        <div class="es-card">
            <div class="es-card-head">
                <h3><i class="fas fa-envelope" style="color:#C5A059;margin-right:8px;"></i>Configured Email Accounts</h3>
                <span id="emailCount" style="font-size:13px;color:#6b7280;"></span>
            </div>
            <div class="es-card-body" id="emailList">
                <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
            <div style="padding:0 20px 20px;">
                <button class="btn-add-email" onclick="openEmailModal()">
                    <i class="fas fa-plus-circle"></i> Add New Email Account
                </button>
            </div>
        </div>
    </div>

    <!-- Tab 2: Task Assignments -->
    <div class="es-tab-content" id="tab-tasks">
        <div class="es-card">
            <div class="es-card-head">
                <h3><i class="fas fa-link" style="color:#C5A059;margin-right:8px;"></i>Email Task Assignments</h3>
                <button class="em-btn" onclick="openAddTaskModal()" title="Add custom task" style="width:auto;padding:6px 14px;font-size:12px;font-weight:600;gap:4px;display:flex;align-items:center;">
                    <i class="fas fa-plus"></i> Add Task
                </button>
            </div>
            <div class="es-card-body" id="taskList">
                <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>

    <!-- Tab 3: Help & Setup Guide -->
    <div class="es-tab-content" id="tab-help">

        <!-- Section 1: Getting Started -->
        <div class="es-card" style="margin-bottom:20px;">
            <div class="es-card-head" style="background:linear-gradient(135deg,#1A3C34,#2d5a4d);">
                <h3 style="color:#fff;"><i class="fas fa-rocket" style="color:#C5A059;margin-right:8px;"></i>Complete Email Setup Guide</h3>
                <span style="color:rgba(255,255,255,.7);font-size:12px;">Step-by-step instructions</span>
            </div>
            <div class="es-card-body">

                <!-- STEP 1: Understanding the System -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">1</span> Understanding the Email System</div>
                    <ul class="help-steps">
                        <li data-step="">
                            This system lets you manage <b>multiple email accounts</b> and assign each one to specific tasks (e.g., order confirmations, password resets, helpdesk, etc.).
                        </li>
                        <li data-step="">
                            <b>How it works:</b> When your store sends an email (e.g., "Order Confirmation"), it checks the <b>Task Assignments</b> tab to see which email account is assigned. It then uses that account's SMTP credentials to send the email.
                        </li>
                        <li data-step="">
                            <b>Supported providers:</b> Gmail, Outlook/Office 365, Domain emails (cPanel), and any Custom SMTP server.
                        </li>
                    </ul>
                    <div class="help-tip"><i class="fas fa-info-circle"></i> <b>Tip:</b> You can use different email addresses for different tasks. For example, <code>sales@gilafstore.com</code> for orders and <code>security@gilafstore.com</code> for password resets.</div>
                </div>

                <!-- STEP 2: Adding a Gmail Account -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">2</span> Adding a Gmail Account</div>
                    <ul class="help-steps">
                        <li data-step="1">Click the <b>"+ Add New Email Account"</b> button on the Email Accounts tab.</li>
                        <li data-step="2">Select <b>Gmail</b> as the provider (it auto-fills SMTP settings).</li>
                        <li data-step="3">Enter your <b>Gmail address</b> (e.g., <code>Gilaf.sales@gmail.com</code>).</li>
                        <li data-step="4">Enter a <b>Display Name</b> (e.g., "Gilaf Store") — this appears as the sender name.</li>
                        <li data-step="5">
                            For the <b>SMTP Password</b>, you need a <b>Gmail App Password</b> (NOT your regular Gmail password).
                        </li>
                        <li data-step="6">Leave <b>SMTP Username</b> blank (it auto-uses the email address).</li>
                        <li data-step="7">Click <b>Save Email</b>.</li>
                        <li data-step="8">Click the <b>✈ Test</b> button to verify it works.</li>
                    </ul>

                    <div class="help-warn"><i class="fas fa-exclamation-triangle"></i> <b>Important:</b> Gmail requires a 16-character <b>App Password</b>, not your regular password. Regular passwords will NOT work with SMTP.</div>

                    <div class="help-tip" style="background:#dcfce7;border-color:#86efac;color:#166534;">
                        <i class="fas fa-external-link-alt"></i> <b>Direct Link:</b> <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:#166534;font-weight:800;text-decoration:underline;">Click here to go to Gmail App Passwords page →</a>
                        <br><span style="font-size:12px;color:#15803d;">Sign in with the Gmail account you want to configure, then create an App Password named "Gilaf Store".</span>
                    </div>

                    <details class="help-accordion">
                        <summary><i class="fab fa-google" style="color:#ea4335;"></i> How to Generate a Gmail App Password</summary>
                        <div class="help-acc-body">
                            <ol style="padding-left:20px;margin:0;">
                                <li>Go to <b><a href="https://myaccount.google.com" target="_blank" style="color:#2563eb;">myaccount.google.com</a></b> and sign in with the Gmail account you want to add.</li>
                                <li>Click <b>Security</b> in the left sidebar.</li>
                                <li>Under "How you sign in to Google", ensure <b>2-Step Verification</b> is <b>ON</b>. If not, enable it first.</li>
                                <li>Once 2-Step Verification is ON, go back to <b>Security</b> page.</li>
                                <li>Or go directly to <b><a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:#2563eb;">myaccount.google.com/apppasswords</a></b></li>
                                <li>Under "App name", type <b>"Gilaf Store"</b> and click <b>Create</b>.</li>
                                <li>Google will show a <b>16-character code</b> like: <code>abcd efgh ijkl mnop</code></li>
                                <li>Copy it and paste into the <b>SMTP Password</b> field <b>without spaces</b>: <code>abcdefghijklmnop</code></li>
                            </ol>
                            <div style="margin-top:10px;padding:10px;background:#fef2f2;border-radius:8px;color:#991b1b;font-size:12px;">
                                <i class="fas fa-lock"></i> <b>Security Note:</b> App passwords are stored securely in your database. Never share them publicly.
                            </div>
                        </div>
                    </details>

                    <table class="help-table">
                        <tr><th>Field</th><th>Value for Gmail</th></tr>
                        <tr><td><b>Provider</b></td><td>Gmail</td></tr>
                        <tr><td><b>SMTP Host</b></td><td><code>smtp.gmail.com</code></td></tr>
                        <tr><td><b>SMTP Port</b></td><td><code>587</code></td></tr>
                        <tr><td><b>Encryption</b></td><td>STARTTLS (Port 587)</td></tr>
                        <tr><td><b>SMTP Username</b></td><td>Leave blank (uses email address)</td></tr>
                        <tr><td><b>SMTP Password</b></td><td>16-char App Password (no spaces)</td></tr>
                    </table>
                </div>

                <!-- STEP 3: Adding a Domain Email (cPanel) -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">3</span> Adding a Domain Email (cPanel / Hosting)</div>
                    <ul class="help-steps">
                        <li data-step="1">Click <b>"+ Add New Email Account"</b> and select <b>Domain Email</b> provider.</li>
                        <li data-step="2">Enter your domain email (e.g., <code>Security@gilafstore.com</code>).</li>
                        <li data-step="3">Enter the <b>Display Name</b> (e.g., "Gilaf Security Team").</li>
                        <li data-step="4">
                            Set <b>SMTP Host</b> to your mail server. Usually: <code>mail.gilafstore.com</code> or <code>smtp.gilafstore.com</code>.
                            <br>Check your cPanel → Email Accounts → "Connect Devices" for exact details.
                        </li>
                        <li data-step="5">Set <b>SMTP Port</b> to <code>465</code> and <b>Encryption</b> to <code>SSL/TLS (Port 465)</code>.</li>
                        <li data-step="6">Enter the <b>full email address</b> as SMTP Username (e.g., <code>Security@gilafstore.com</code>).</li>
                        <li data-step="7">Enter the <b>email account password</b> you set in cPanel when creating this email.</li>
                        <li data-step="8">Click <b>Save</b> and then <b>✈ Test</b> to verify.</li>
                    </ul>

                    <details class="help-accordion">
                        <summary><i class="fas fa-server" style="color:#2563eb;"></i> How to Find Your Domain Email SMTP Settings (cPanel)</summary>
                        <div class="help-acc-body">
                            <ol style="padding-left:20px;margin:0;">
                                <li>Log into your <b>cPanel</b> (usually at <code>yourdomain.com/cpanel</code> or <code>yourdomain.com:2083</code>).</li>
                                <li>Go to <b>Email Accounts</b>.</li>
                                <li>Find the email you want to add and click <b>"Connect Devices"</b> or <b>"Set Up Mail Client"</b>.</li>
                                <li>Look for the <b>"Secure SSL/TLS Settings"</b> section:</li>
                            </ol>
                            <table style="width:100%;border-collapse:collapse;margin-top:10px;font-size:12px;">
                                <tr style="background:#f9fafb;"><td style="padding:6px 10px;border:1px solid #e5e7eb;"><b>Outgoing Server (SMTP)</b></td><td style="padding:6px 10px;border:1px solid #e5e7eb;"><code>mail.gilafstore.com</code></td></tr>
                                <tr><td style="padding:6px 10px;border:1px solid #e5e7eb;"><b>SMTP Port</b></td><td style="padding:6px 10px;border:1px solid #e5e7eb;"><code>465</code> (SSL) or <code>587</code> (STARTTLS)</td></tr>
                                <tr style="background:#f9fafb;"><td style="padding:6px 10px;border:1px solid #e5e7eb;"><b>Username</b></td><td style="padding:6px 10px;border:1px solid #e5e7eb;">Full email address</td></tr>
                                <tr><td style="padding:6px 10px;border:1px solid #e5e7eb;"><b>Password</b></td><td style="padding:6px 10px;border:1px solid #e5e7eb;">The password set in cPanel</td></tr>
                                <tr style="background:#f9fafb;"><td style="padding:6px 10px;border:1px solid #e5e7eb;"><b>Authentication</b></td><td style="padding:6px 10px;border:1px solid #e5e7eb;">Password (Normal)</td></tr>
                            </table>
                        </div>
                    </details>

                    <table class="help-table">
                        <tr><th>Field</th><th>Value for Domain Email</th></tr>
                        <tr><td><b>Provider</b></td><td>Domain Email</td></tr>
                        <tr><td><b>SMTP Host</b></td><td><code>mail.gilafstore.com</code> (check cPanel)</td></tr>
                        <tr><td><b>SMTP Port</b></td><td><code>465</code></td></tr>
                        <tr><td><b>Encryption</b></td><td>SSL/TLS (Port 465)</td></tr>
                        <tr><td><b>SMTP Username</b></td><td>Full email address</td></tr>
                        <tr><td><b>SMTP Password</b></td><td>cPanel email password</td></tr>
                    </table>

                    <div class="help-danger"><i class="fas fa-exclamation-circle"></i> <b>Note:</b> Domain emails like <code>Support@gilafstore.com</code> and <code>Security@gilafstore.com</code> were previously deleted. You need to <b>re-create them in cPanel</b> first before adding them here.</div>
                </div>

                <!-- STEP 4: Adding Outlook / Office 365 -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">4</span> Adding an Outlook / Office 365 Account</div>
                    <ul class="help-steps">
                        <li data-step="1">Click <b>"+ Add New Email Account"</b> and select <b>Outlook</b>.</li>
                        <li data-step="2">SMTP settings are auto-filled. Enter your Outlook email and password.</li>
                        <li data-step="3">For Microsoft 365 business accounts, you may need an App Password (similar to Gmail).</li>
                    </ul>
                    <table class="help-table">
                        <tr><th>Field</th><th>Value for Outlook</th></tr>
                        <tr><td><b>SMTP Host</b></td><td><code>smtp.office365.com</code></td></tr>
                        <tr><td><b>SMTP Port</b></td><td><code>587</code></td></tr>
                        <tr><td><b>Encryption</b></td><td>STARTTLS (Port 587)</td></tr>
                        <tr><td><b>SMTP Username</b></td><td>Full email address</td></tr>
                        <tr><td><b>SMTP Password</b></td><td>Account password or App Password</td></tr>
                    </table>
                </div>

                <!-- STEP 5: Assigning Tasks -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">5</span> Assigning Emails to Tasks</div>
                    <ul class="help-steps">
                        <li data-step="1">Go to the <b>Task Assignments</b> tab.</li>
                        <li data-step="2">You'll see all email tasks grouped by category: <b>Orders, Security, Support, Marketing, General</b>.</li>
                        <li data-step="3">For each task, use the <b>dropdown</b> to select which email account should send that type of email.</li>
                        <li data-step="4">Changes are saved <b>instantly</b> when you select an email from the dropdown.</li>
                        <li data-step="5">If a task has <b>"— Not Assigned —"</b>, it will use the system default email as a fallback.</li>
                    </ul>

                    <div class="help-tip"><i class="fas fa-lightbulb"></i> <b>Recommended Setup:</b></div>
                    <table class="help-table">
                        <tr><th>Task</th><th>Recommended Email</th><th>Why</th></tr>
                        <tr><td>Order Confirmation</td><td><code>Gilaf.sales@gmail.com</code></td><td>Sales/transactional emails</td></tr>
                        <tr><td>Order Shipped</td><td><code>Gilaf.sales@gmail.com</code></td><td>Same sales channel</td></tr>
                        <tr><td>Order Delivered</td><td><code>Gilaf.sales@gmail.com</code></td><td>Same sales channel</td></tr>
                        <tr><td>Order Cancellation</td><td><code>Gilaf.sales@gmail.com</code></td><td>Same sales channel</td></tr>
                        <tr><td>Refund Notification</td><td><code>Gilaf.sales@gmail.com</code></td><td>Same sales channel</td></tr>
                        <tr><td>Payment Receipt</td><td><code>Gilaf.sales@gmail.com</code></td><td>Same sales channel</td></tr>
                        <tr><td>Password Reset</td><td><code>Security@gilafstore.com</code></td><td>Security emails from domain</td></tr>
                        <tr><td>Account Verification</td><td><code>Security@gilafstore.com</code></td><td>Security emails from domain</td></tr>
                        <tr><td>Welcome Email</td><td><code>Gilaf.sales@gmail.com</code></td><td>Onboarding</td></tr>
                        <tr><td>Help Desk</td><td><code>Support@gilafstore.com</code></td><td>Support channel</td></tr>
                        <tr><td>Ticket Confirmation</td><td><code>Support@gilafstore.com</code></td><td>Support channel</td></tr>
                        <tr><td>Newsletter</td><td><code>Gilaf.sales@gmail.com</code></td><td>Marketing</td></tr>
                    </table>
                </div>

                <!-- STEP 6: Testing -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">6</span> Testing Your Email Configuration</div>
                    <ul class="help-steps">
                        <li data-step="1">After adding an email, click the <b>✈ (paper plane) Test</b> button next to it.</li>
                        <li data-step="2">The system will attempt to send a test email <b>to itself</b> using the SMTP settings.</li>
                        <li data-step="3">If successful: The status badge changes to <b style="color:#16a34a;">Verified ✅</b>.</li>
                        <li data-step="4">If failed: The badge shows <b style="color:#dc2626;">Failed ❌</b> — check your credentials and try again.</li>
                        <li data-step="5">Check the email inbox to confirm the test email arrived.</li>
                    </ul>
                    <div class="help-warn"><i class="fas fa-clock"></i> <b>First-time Gmail:</b> Google may block the first SMTP attempt. Check your Gmail inbox for a "Security alert" and click "Yes, it was me" to allow it.</div>
                </div>

                <!-- STEP 7: Troubleshooting -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">7</span> Troubleshooting Common Issues</div>

                    <details class="help-accordion">
                        <summary><i class="fas fa-times-circle" style="color:#dc2626;"></i> "SMTP connect() failed" or "Authentication failed"</summary>
                        <div class="help-acc-body">
                            <ul style="padding-left:16px;margin:0;">
                                <li><b>Gmail:</b> Make sure you're using an <b>App Password</b>, not your regular password. 2-Step Verification must be ON.</li>
                                <li><b>Domain email:</b> Double-check the password in cPanel. Try resetting it.</li>
                                <li>Verify the <b>SMTP Host</b> and <b>Port</b> are correct.</li>
                                <li>Make sure the email account is <b>not suspended or locked</b>.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="help-accordion">
                        <summary><i class="fas fa-eye-slash" style="color:#d97706;"></i> "Emails sending but not received"</summary>
                        <div class="help-acc-body">
                            <ul style="padding-left:16px;margin:0;">
                                <li>Check the <b>Spam/Junk folder</b> of the recipient.</li>
                                <li>For domain emails, ensure <b>SPF, DKIM, and DMARC</b> DNS records are properly configured in your domain's DNS settings.</li>
                                <li>Gmail has a <b>daily sending limit</b> of ~500 emails/day for regular accounts.</li>
                                <li>Try the <b>✈ Test</b> button to send a test and check if it arrives.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="help-accordion">
                        <summary><i class="fas fa-image" style="color:#2563eb;"></i> "Product images not showing in Gmail"</summary>
                        <div class="help-acc-body">
                            <ul style="padding-left:16px;margin:0;">
                                <li>Images must use <b>absolute HTTPS URLs</b> (e.g., <code>https://gilafstore.com/assets/images/products/image.jpg</code>).</li>
                                <li>The system already converts image URLs to production HTTPS URLs automatically.</li>
                                <li>Make sure the images exist on the <b>live server</b> and are accessible via browser.</li>
                                <li>Gmail may <b>proxy images</b> — first-time viewing may show a "Display images" prompt.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="help-accordion">
                        <summary><i class="fas fa-ban" style="color:#991b1b;"></i> "Domain email deleted / needs re-verification"</summary>
                        <div class="help-acc-body">
                            <ul style="padding-left:16px;margin:0;">
                                <li>If <code>Support@gilafstore.com</code> or <code>Security@gilafstore.com</code> was deleted, you need to <b>re-create it in cPanel</b>.</li>
                                <li>Go to <b>cPanel → Email Accounts → Create</b>.</li>
                                <li>Set a <b>strong password</b> and note it down.</li>
                                <li>Then come back here and add it with the new password.</li>
                                <li>Run the <b>✈ Test</b> to confirm it works.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="help-accordion">
                        <summary><i class="fas fa-question-circle" style="color:#6b7280;"></i> "What is SMTP?"</summary>
                        <div class="help-acc-body">
                            <b>SMTP</b> (Simple Mail Transfer Protocol) is the standard protocol for sending emails over the internet. Here's what each setting means:
                            <table style="width:100%;border-collapse:collapse;margin-top:10px;font-size:12px;">
                                <tr style="background:#f9fafb;"><td style="padding:8px 10px;border:1px solid #e5e7eb;width:30%;"><b>SMTP Host</b></td><td style="padding:8px 10px;border:1px solid #e5e7eb;">The mail server address that handles sending emails (e.g., <code>smtp.gmail.com</code>)</td></tr>
                                <tr><td style="padding:8px 10px;border:1px solid #e5e7eb;"><b>SMTP Port</b></td><td style="padding:8px 10px;border:1px solid #e5e7eb;">The network port to connect on. <code>587</code> for TLS, <code>465</code> for SSL, <code>25</code> for unencrypted (not recommended)</td></tr>
                                <tr style="background:#f9fafb;"><td style="padding:8px 10px;border:1px solid #e5e7eb;"><b>Encryption</b></td><td style="padding:8px 10px;border:1px solid #e5e7eb;"><b>STARTTLS</b> (upgrades connection to secure) or <b>SSL/TLS</b> (starts secure). Always use one of these.</td></tr>
                                <tr><td style="padding:8px 10px;border:1px solid #e5e7eb;"><b>SMTP Username</b></td><td style="padding:8px 10px;border:1px solid #e5e7eb;">Usually your full email address. Used to log in to the mail server.</td></tr>
                                <tr style="background:#f9fafb;"><td style="padding:8px 10px;border:1px solid #e5e7eb;"><b>SMTP Password</b></td><td style="padding:8px 10px;border:1px solid #e5e7eb;">Your email password or App Password. This authenticates you with the mail server.</td></tr>
                                <tr><td style="padding:8px 10px;border:1px solid #e5e7eb;"><b>Display Name</b></td><td style="padding:8px 10px;border:1px solid #e5e7eb;">The name shown to recipients (e.g., "Gilaf Store"). Not a technical SMTP setting.</td></tr>
                            </table>
                        </div>
                    </details>
                </div>

                <!-- STEP 8: Quick Reference -->
                <div class="help-section">
                    <div class="help-section-title"><span class="help-num">8</span> Quick Reference — All Provider Settings</div>
                    <table class="help-table" style="margin-left:0;max-width:100%;">
                        <tr>
                            <th>Provider</th><th>SMTP Host</th><th>Port</th><th>Encryption</th><th>Username</th><th>Password Type</th>
                        </tr>
                        <tr>
                            <td><i class="fab fa-google" style="color:#ea4335;"></i> <b>Gmail</b></td>
                            <td><code>smtp.gmail.com</code></td>
                            <td><code>587</code></td>
                            <td>STARTTLS</td>
                            <td>Email address</td>
                            <td>App Password (16-char)</td>
                        </tr>
                        <tr>
                            <td><i class="fab fa-microsoft" style="color:#0078d4;"></i> <b>Outlook / O365</b></td>
                            <td><code>smtp.office365.com</code></td>
                            <td><code>587</code></td>
                            <td>STARTTLS</td>
                            <td>Email address</td>
                            <td>Account / App Password</td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-globe" style="color:#2563eb;"></i> <b>Domain (cPanel)</b></td>
                            <td><code>mail.yourdomain.com</code></td>
                            <td><code>465</code></td>
                            <td>SSL/TLS</td>
                            <td>Full email address</td>
                            <td>cPanel email password</td>
                        </tr>
                        <tr>
                            <td><i class="fab fa-yahoo" style="color:#6001d2;"></i> <b>Yahoo</b></td>
                            <td><code>smtp.mail.yahoo.com</code></td>
                            <td><code>465</code></td>
                            <td>SSL/TLS</td>
                            <td>Email address</td>
                            <td>App Password</td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-at" style="color:#0078be;"></i> <b>Zoho</b></td>
                            <td><code>smtp.zoho.com</code></td>
                            <td><code>465</code></td>
                            <td>SSL/TLS</td>
                            <td>Email address</td>
                            <td>Account password</td>
                        </tr>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- Tab 4: Email Activity Log -->
    <div class="es-tab-content" id="tab-logs">
        <!-- Log Stats -->
        <div class="es-stats" id="logStats" style="margin-bottom:16px;"></div>

        <div class="es-card">
            <div class="es-card-head">
                <h3><i class="fas fa-history" style="color:#C5A059;margin-right:8px;"></i>Email Send Activity</h3>
                <div style="display:flex;gap:8px;align-items:center;">
                    <select id="logFilter" onchange="loadEmailLogs()" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:12px;cursor:pointer;">
                        <option value="">All</option>
                        <option value="success">Success</option>
                        <option value="fallback">Fallback</option>
                        <option value="failed">Failed</option>
                    </select>
                    <button onclick="loadEmailLogs()" class="em-btn" style="width:auto;padding:6px 12px;font-size:12px;gap:4px;"><i class="fas fa-sync-alt"></i> Refresh</button>
                    <button onclick="clearOldLogs()" class="em-btn" style="width:auto;padding:6px 12px;font-size:12px;gap:4px;background:#fee2e2;color:#dc2626;border-color:#fecaca;" title="Clear logs older than 30 days"><i class="fas fa-trash"></i> Clear Old</button>
                </div>
            </div>
            <div class="es-card-body" style="padding:0;">
                <div style="overflow-x:auto;">
                    <table class="log-table" id="logTable">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Task</th>
                                <th>Assigned Email</th>
                                <th>Sent From</th>
                                <th>Recipient</th>
                                <th>Subject</th>
                                <th>Details</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody id="logTableBody">
                            <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="es-modal-overlay" id="emailModal">
    <div class="es-modal">
        <div class="es-modal-head">
            <h3 id="emailModalTitle">Add Email Account</h3>
            <button class="es-modal-close" onclick="closeEmailModal()">&times;</button>
        </div>
        <div class="es-modal-body">
            <form id="emailForm" onsubmit="saveEmail(event)">
                <input type="hidden" name="id" id="emId" value="0">

                <!-- Provider Selection -->
                <div class="es-form-group">
                    <label>Email Provider</label>
                    <div class="provider-btns">
                        <div class="provider-btn selected" data-provider="gmail" onclick="selectProvider('gmail')">
                            <i class="fab fa-google" style="color:#ea4335;"></i> Gmail
                        </div>
                        <div class="provider-btn" data-provider="outlook" onclick="selectProvider('outlook')">
                            <i class="fab fa-microsoft" style="color:#0078d4;"></i> Outlook
                        </div>
                        <div class="provider-btn" data-provider="domain" onclick="selectProvider('domain')">
                            <i class="fas fa-globe" style="color:#2563eb;"></i> Domain Email
                        </div>
                        <div class="provider-btn" data-provider="custom" onclick="selectProvider('custom')">
                            <i class="fas fa-server" style="color:#9333ea;"></i> Custom SMTP
                        </div>
                    </div>
                    <input type="hidden" name="provider" id="emProvider" value="gmail">
                </div>

                <!-- Email & Display Name -->
                <div class="es-row">
                    <div class="es-form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email_address" id="emEmailAddr" class="es-input" placeholder="name@example.com" required>
                    </div>
                    <div class="es-form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" id="emDisplayName" class="es-input" placeholder="Gilaf Store">
                    </div>
                </div>

                <hr style="border-color:#f3f4f6;margin:8px 0 16px;">
                <div style="font-size:13px;font-weight:700;color:#1A3C34;margin-bottom:12px;"><i class="fas fa-server" style="margin-right:6px;"></i>SMTP Configuration</div>

                <!-- SMTP Host & Port -->
                <div class="es-row">
                    <div class="es-form-group" style="flex:2;">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" id="emSmtpHost" class="es-input" value="smtp.gmail.com">
                        <div class="hint">e.g. smtp.gmail.com, smtp.office365.com, mail.yourdomain.com</div>
                    </div>
                    <div class="es-form-group" style="flex:1;">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" id="emSmtpPort" class="es-input" value="587">
                        <div class="hint">587 (TLS) or 465 (SSL)</div>
                    </div>
                </div>

                <!-- Encryption & Username -->
                <div class="es-row">
                    <div class="es-form-group">
                        <label>Encryption</label>
                        <select name="smtp_encryption" id="emSmtpEnc" class="es-select">
                            <option value="tls" selected>STARTTLS (Port 587)</option>
                            <option value="ssl">SSL/TLS (Port 465)</option>
                            <option value="none">None (Port 25)</option>
                        </select>
                    </div>
                    <div class="es-form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_username" id="emSmtpUser" class="es-input" placeholder="Same as email if blank">
                        <div class="hint">Leave blank to use the email address</div>
                    </div>
                </div>

                <!-- Password / App Password -->
                <div class="es-form-group">
                    <label>SMTP Password / App Password *</label>
                    <div style="position:relative;">
                        <input type="password" name="smtp_password" id="emSmtpPass" class="es-input" style="padding-right:44px;" placeholder="Enter app password or SMTP password">
                        <button type="button" onclick="togglePassVis()" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#6b7280;font-size:16px;">
                            <i class="fas fa-eye" id="passToggleIcon"></i>
                        </button>
                    </div>
                    <div class="hint">
                        <strong>Gmail:</strong> Use a 16-char App Password (Google Account → Security → 2-Step → App Passwords).<br>
                        <strong>Domain:</strong> Use the email account password from your hosting cPanel.
                    </div>
                </div>

                <!-- Active & Notes -->
                <div class="es-row">
                    <div class="es-form-group">
                        <label>Status</label>
                        <select name="is_active" id="emIsActive" class="es-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="es-form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" id="emNotes" class="es-input es-textarea" placeholder="Internal notes about this email..."></textarea>
                </div>

                <!-- Actions -->
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                    <button type="button" onclick="closeEmailModal()" style="padding:10px 24px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 28px;border-radius:8px;border:none;background:#1A3C34;color:#fff;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-save"></i> <span id="emailSaveBtn">Save Email</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="es-modal-overlay" id="taskModal">
    <div class="es-modal" style="width:460px;">
        <div class="es-modal-head">
            <h3>Add Custom Task</h3>
            <button class="es-modal-close" onclick="closeTaskModal()">&times;</button>
        </div>
        <div class="es-modal-body">
            <form onsubmit="saveTask(event)">
                <div class="es-form-group">
                    <label>Task Label *</label>
                    <input type="text" id="newTaskLabel" class="es-input" placeholder="e.g. Invoice Email" required>
                </div>
                <div class="es-form-group">
                    <label>Task Key *</label>
                    <input type="text" id="newTaskKey" class="es-input" placeholder="e.g. invoice_email" pattern="[a-z0-9_]+" required>
                    <div class="hint">Lowercase letters, numbers, underscores only</div>
                </div>
                <div class="es-form-group">
                    <label>Group</label>
                    <select id="newTaskGroup" class="es-select">
                        <option value="orders">Orders</option>
                        <option value="security">Security</option>
                        <option value="support">Support</option>
                        <option value="marketing">Marketing</option>
                        <option value="general" selected>General</option>
                    </select>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                    <button type="button" onclick="closeTaskModal()" style="padding:10px 24px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 28px;border-radius:8px;border:none;background:#1A3C34;color:#fff;font-weight:700;cursor:pointer;">
                        <i class="fas fa-plus"></i> Add Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="es-toast" id="esToast"></div>

<script>
const API = '<?= base_url("admin/email_settings_api.php") ?>';
let allEmails = [];

// ─── Tab Switching ───
function switchTab(tab) {
    document.querySelectorAll('.es-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    document.querySelectorAll('.es-tab-content').forEach(c => c.classList.toggle('active', c.id === 'tab-' + tab));
    if (tab === 'tasks') loadTasks();
}

// ─── Toast ───
function toast(msg, type = 'success') {
    const t = document.getElementById('esToast');
    t.textContent = msg;
    t.className = 'es-toast show ' + type;
    setTimeout(() => t.classList.remove('show'), 3500);
}

// ─── Provider Presets ───
const providerPresets = {
    gmail:   { host: 'smtp.gmail.com',       port: 587, enc: 'tls' },
    outlook: { host: 'smtp.office365.com',   port: 587, enc: 'tls' },
    domain:  { host: 'mail.gilafstore.com',  port: 465, enc: 'ssl' },
    custom:  { host: '',                     port: 587, enc: 'tls' }
};

function selectProvider(p) {
    document.querySelectorAll('.provider-btn').forEach(b => b.classList.toggle('selected', b.dataset.provider === p));
    document.getElementById('emProvider').value = p;
    const preset = providerPresets[p];
    document.getElementById('emSmtpHost').value = preset.host;
    document.getElementById('emSmtpPort').value = preset.port;
    document.getElementById('emSmtpEnc').value = preset.enc;
}

// ─── Password Toggle ───
function togglePassVis() {
    const inp = document.getElementById('emSmtpPass');
    const ico = document.getElementById('passToggleIcon');
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
    else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}

// ─── Load Emails ───
async function loadEmails() {
    try {
        const res = await fetch(API + '?action=list_emails');
        const json = await res.json();
        allEmails = json.data || [];
        renderEmails();
        renderStats();
    } catch (e) { document.getElementById('emailList').innerHTML = '<p class="text-danger">Failed to load</p>'; }
}

function getProviderIcon(p) {
    const map = { gmail: 'gmail', outlook: 'outlook', domain: 'domain', custom: 'custom' };
    const icons = { gmail: 'fab fa-google', outlook: 'fab fa-microsoft', domain: 'fas fa-globe', custom: 'fas fa-server' };
    return { cls: map[p] || 'custom', icon: icons[p] || 'fas fa-envelope' };
}

function renderEmails() {
    const c = document.getElementById('emailList');
    if (!allEmails.length) {
        c.innerHTML = '<div style="text-align:center;padding:40px 20px;color:#9ca3af;"><i class="fas fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;"></i><p style="font-weight:600;">No email accounts configured</p><p style="font-size:13px;">Click the button below to add your first email.</p></div>';
        document.getElementById('emailCount').textContent = '0 accounts';
        return;
    }

    let html = '';
    allEmails.forEach(em => {
        const pi = getProviderIcon(em.provider);
        const statusBadge = em.is_active == 1 ? '<span class="em-badge active">Active</span>' : '<span class="em-badge inactive">Inactive</span>';
        let verifyBadge = '';
        if (em.test_status === 'success') verifyBadge = '<span class="em-badge verified"><i class="fas fa-check-circle"></i> Verified</span>';
        else if (em.test_status === 'failed') verifyBadge = '<span class="em-badge failed"><i class="fas fa-times-circle"></i> Failed</span>';
        else verifyBadge = '<span class="em-badge unverified"><i class="fas fa-question-circle"></i> Untested</span>';

        const lastTest = em.last_tested_at ? '<span style="font-size:11px;color:#9ca3af;margin-left:4px;">Tested: ' + em.last_tested_at + '</span>' : '';

        html += `
        <div class="em-row">
            <div class="em-icon ${pi.cls}"><i class="${pi.icon}"></i></div>
            <div class="em-info">
                <div class="em-email">${em.email_address}</div>
                <div class="em-name">${em.display_name || em.smtp_host + ':' + em.smtp_port} ${lastTest}</div>
            </div>
            <div class="em-badges">${statusBadge}${verifyBadge}</div>
            <div class="em-actions">
                <button class="em-btn success" onclick="testEmail(${em.id})" title="Test SMTP connection"><i class="fas fa-paper-plane"></i></button>
                <button class="em-btn" onclick="editEmail(${em.id})" title="Edit"><i class="fas fa-pen"></i></button>
                <button class="em-btn" onclick="toggleEmail(${em.id})" title="Toggle active"><i class="fas fa-power-off"></i></button>
                <button class="em-btn danger" onclick="deleteEmail(${em.id}, '${em.email_address}')" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    });
    c.innerHTML = html;
    document.getElementById('emailCount').textContent = allEmails.length + ' account' + (allEmails.length > 1 ? 's' : '');
}

function renderStats() {
    const total = allEmails.length;
    const active = allEmails.filter(e => e.is_active == 1).length;
    const verified = allEmails.filter(e => e.test_status === 'success').length;
    const failed = allEmails.filter(e => e.test_status === 'failed').length;

    document.getElementById('emailStats').innerHTML = `
        <div class="es-stat"><div class="es-stat-icon blue"><i class="fas fa-envelope"></i></div><div><div class="es-stat-num">${total}</div><div class="es-stat-label">Total Accounts</div></div></div>
        <div class="es-stat"><div class="es-stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="es-stat-num">${active}</div><div class="es-stat-label">Active</div></div></div>
        <div class="es-stat"><div class="es-stat-icon gold"><i class="fas fa-shield-alt"></i></div><div><div class="es-stat-num">${verified}</div><div class="es-stat-label">Verified (SMTP OK)</div></div></div>
        <div class="es-stat"><div class="es-stat-icon red"><i class="fas fa-exclamation-triangle"></i></div><div><div class="es-stat-num">${failed}</div><div class="es-stat-label">Failed</div></div></div>
    `;
}

// ─── Email CRUD ───
function openEmailModal(id = 0) {
    document.getElementById('emailForm').reset();
    document.getElementById('emId').value = 0;
    document.getElementById('emailModalTitle').textContent = 'Add New Email Account';
    document.getElementById('emailSaveBtn').textContent = 'Save Email';
    selectProvider('gmail');
    document.getElementById('emailModal').classList.add('show');
}

function closeEmailModal() { document.getElementById('emailModal').classList.remove('show'); }

async function editEmail(id) {
    const res = await fetch(API + '?action=get_email&id=' + id);
    const json = await res.json();
    if (!json.success) { toast('Email not found', 'error'); return; }
    const em = json.data;

    document.getElementById('emId').value = em.id;
    document.getElementById('emEmailAddr').value = em.email_address;
    document.getElementById('emDisplayName').value = em.display_name;
    document.getElementById('emSmtpHost').value = em.smtp_host;
    document.getElementById('emSmtpPort').value = em.smtp_port;
    document.getElementById('emSmtpEnc').value = em.smtp_encryption;
    document.getElementById('emSmtpUser').value = em.smtp_username;
    document.getElementById('emSmtpPass').value = em.smtp_password_masked;
    document.getElementById('emIsActive').value = em.is_active;
    document.getElementById('emNotes').value = em.notes;
    selectProvider(em.provider);

    document.getElementById('emailModalTitle').textContent = 'Edit Email Account';
    document.getElementById('emailSaveBtn').textContent = 'Update Email';
    document.getElementById('emailModal').classList.add('show');
}

async function saveEmail(e) {
    e.preventDefault();
    const form = new FormData(document.getElementById('emailForm'));
    form.append('action', 'save_email');

    try {
        const res = await fetch(API, { method: 'POST', body: form });
        const json = await res.json();
        if (json.success) {
            toast(json.message);
            closeEmailModal();
            loadEmails();
        } else {
            toast(json.message, 'error');
        }
    } catch (err) { toast('Network error', 'error'); }
}

async function deleteEmail(id, email) {
    if (!confirm('Delete email account "' + email + '"?\nAll task assignments for this email will be unlinked.')) return;
    const form = new FormData();
    form.append('action', 'delete_email');
    form.append('id', id);
    const res = await fetch(API, { method: 'POST', body: form });
    const json = await res.json();
    toast(json.message, json.success ? 'success' : 'error');
    if (json.success) loadEmails();
}

async function toggleEmail(id) {
    const form = new FormData();
    form.append('action', 'toggle_email');
    form.append('id', id);
    await fetch(API, { method: 'POST', body: form });
    loadEmails();
}

async function testEmail(id) {
    toast('Sending test email...', 'info');
    const form = new FormData();
    form.append('action', 'test_email');
    form.append('id', id);
    try {
        const res = await fetch(API, { method: 'POST', body: form });
        const json = await res.json();
        toast(json.message, json.success ? 'success' : 'error');
        loadEmails();
    } catch (err) { toast('Test failed - network error', 'error'); }
}

// ─── Task Assignments ───
async function loadTasks() {
    try {
        const [tasksRes, emailsRes] = await Promise.all([
            fetch(API + '?action=list_tasks'),
            fetch(API + '?action=list_emails')
        ]);
        const tasksJson = await tasksRes.json();
        const emailsJson = await emailsRes.json();
        allEmails = emailsJson.data || [];
        renderTasks(tasksJson.data || []);
    } catch (e) { document.getElementById('taskList').innerHTML = '<p class="text-danger">Failed to load</p>'; }
}

function renderTasks(tasks) {
    const c = document.getElementById('taskList');
    if (!tasks.length) { c.innerHTML = '<p class="text-muted text-center py-4">No tasks configured</p>'; return; }

    const groupIcons = {
        orders: { icon: 'fas fa-receipt', cls: 'orders' },
        security: { icon: 'fas fa-shield-alt', cls: 'security' },
        support: { icon: 'fas fa-headset', cls: 'support' },
        marketing: { icon: 'fas fa-bullhorn', cls: 'marketing' },
        general: { icon: 'fas fa-envelope', cls: 'general' }
    };

    const groupLabels = { orders: 'Orders & Payments', security: 'Security & Auth', support: 'Support & Helpdesk', marketing: 'Marketing & Newsletters', general: 'General' };

    // Group tasks
    const grouped = {};
    tasks.forEach(t => {
        const g = t.task_group || 'general';
        if (!grouped[g]) grouped[g] = [];
        grouped[g].push(t);
    });

    // Build email options
    let emailOpts = '<option value="">— Not Assigned —</option>';
    allEmails.forEach(em => {
        const badge = em.is_active == 1 ? '' : ' (Inactive)';
        emailOpts += `<option value="${em.id}">${em.email_address}${badge}</option>`;
    });

    let html = '';
    const groupOrder = ['orders', 'security', 'support', 'marketing', 'general'];
    groupOrder.forEach(g => {
        if (!grouped[g]) return;
        const gi = groupIcons[g] || groupIcons.general;
        html += `<div class="task-group-title"><i class="${gi.icon}" style="margin-right:6px;"></i>${groupLabels[g] || g}</div>`;
        grouped[g].forEach(t => {
            const selected = t.email_config_id || '';
            const assignedEmail = t.email_address || '';
            let opts = emailOpts.replace(`value="${selected}"`, `value="${selected}" selected`);

            html += `
            <div class="task-row">
                <div class="task-icon ${gi.cls}"><i class="${gi.icon}"></i></div>
                <div class="task-info">
                    <div class="task-label">${t.task_label}</div>
                    <div class="task-key">${t.task_key}</div>
                </div>
                <select class="task-select" onchange="assignTask(${t.id}, this.value)">
                    ${opts}
                </select>
                <button class="em-btn danger" onclick="deleteTask(${t.id}, '${t.task_label}')" title="Delete task"><i class="fas fa-trash"></i></button>
            </div>`;
        });
        delete grouped[g];
    });
    // Any remaining groups
    Object.keys(grouped).forEach(g => {
        html += `<div class="task-group-title">${g}</div>`;
        grouped[g].forEach(t => {
            let opts = emailOpts.replace(`value="${t.email_config_id || ''}"`, `value="${t.email_config_id || ''}" selected`);
            html += `
            <div class="task-row">
                <div class="task-icon general"><i class="fas fa-envelope"></i></div>
                <div class="task-info"><div class="task-label">${t.task_label}</div><div class="task-key">${t.task_key}</div></div>
                <select class="task-select" onchange="assignTask(${t.id}, this.value)">${opts}</select>
                <button class="em-btn danger" onclick="deleteTask(${t.id}, '${t.task_label}')"><i class="fas fa-trash"></i></button>
            </div>`;
        });
    });

    c.innerHTML = html;
}

async function assignTask(taskId, emailConfigId) {
    const form = new FormData();
    form.append('action', 'assign_task');
    form.append('task_id', taskId);
    form.append('email_config_id', emailConfigId);
    const res = await fetch(API, { method: 'POST', body: form });
    const json = await res.json();
    toast(json.success ? 'Task assigned!' : json.message, json.success ? 'success' : 'error');
}

// ─── Add Task Modal ───
function openAddTaskModal() { document.getElementById('taskModal').classList.add('show'); }
function closeTaskModal() { document.getElementById('taskModal').classList.remove('show'); }

async function saveTask(e) {
    e.preventDefault();
    const form = new FormData();
    form.append('action', 'add_task');
    form.append('task_key', document.getElementById('newTaskKey').value);
    form.append('task_label', document.getElementById('newTaskLabel').value);
    form.append('task_group', document.getElementById('newTaskGroup').value);
    const res = await fetch(API, { method: 'POST', body: form });
    const json = await res.json();
    toast(json.message, json.success ? 'success' : 'error');
    if (json.success) { closeTaskModal(); loadTasks(); }
}

async function deleteTask(id, label) {
    if (!confirm('Delete task "' + label + '"?')) return;
    const form = new FormData();
    form.append('action', 'delete_task');
    form.append('id', id);
    const res = await fetch(API, { method: 'POST', body: form });
    const json = await res.json();
    toast(json.message, json.success ? 'success' : 'error');
    if (json.success) loadTasks();
}

// Auto-generate task key from label
document.getElementById('newTaskLabel').addEventListener('input', function() {
    document.getElementById('newTaskKey').value = this.value.toLowerCase().replace(/[^a-z0-9]/g, '_').replace(/_+/g, '_').replace(/^_|_$/g, '');
});

// Close modals on overlay click
document.querySelectorAll('.es-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
});

// ─── Activity Log ───
async function loadEmailLogs() {
    const filter = document.getElementById('logFilter')?.value || '';
    try {
        const res = await fetch(API + '?action=get_email_logs&filter=' + filter + '&limit=100');
        const json = await res.json();
        if (json.success) {
            renderLogTable(json.logs || []);
            renderLogStats(json.stats || {});
            // Update badge on tab
            const failed = (json.stats?.today?.failed || 0) + (json.stats?.today?.fallback || 0);
            const badge = document.getElementById('logAlertBadge');
            if (failed > 0) { badge.style.display = 'inline'; badge.textContent = failed; }
            else { badge.style.display = 'none'; }
        }
    } catch (e) {
        document.getElementById('logTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Failed to load logs</td></tr>';
    }
}

function renderLogStats(stats) {
    const t = stats.today || {};
    const a = stats.all_time || {};
    document.getElementById('logStats').innerHTML = `
        <div class="es-stat">
            <div class="es-stat-icon blue"><i class="fas fa-paper-plane"></i></div>
            <div><div class="es-stat-num">${t.total || 0}</div><div class="es-stat-label">Sent Today</div></div>
        </div>
        <div class="es-stat">
            <div class="es-stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div><div class="es-stat-num">${t.success || 0}</div><div class="es-stat-label">Success Today</div></div>
        </div>
        <div class="es-stat">
            <div class="es-stat-icon gold"><i class="fas fa-exchange-alt"></i></div>
            <div><div class="es-stat-num">${t.fallback || 0}</div><div class="es-stat-label">Fallback Today</div></div>
        </div>
        <div class="es-stat">
            <div class="es-stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div><div class="es-stat-num">${t.failed || 0}</div><div class="es-stat-label">Failed Today</div></div>
        </div>
    `;
}

function renderLogTable(logs) {
    const tbody = document.getElementById('logTableBody');
    if (!logs.length) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#9ca3af;"><i class="fas fa-inbox" style="font-size:36px;display:block;margin-bottom:10px;"></i>No email activity logged yet.<br><span style="font-size:12px;">Emails will appear here as they are sent.</span></td></tr>';
        return;
    }

    tbody.innerHTML = logs.map(log => {
        const statusIcons = { success: '<i class="fas fa-check-circle"></i> Success', failed: '<i class="fas fa-times-circle"></i> Failed', fallback: '<i class="fas fa-exchange-alt"></i> Fallback' };
        const taskLabel = (log.task_key || '—').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        const assigned = log.assigned_email || '<span style="color:#9ca3af;">—</span>';
        const sentFrom = log.sent_from_email || '<span style="color:#dc2626;">Not sent</span>';
        const subjectShort = (log.subject || '').substring(0, 40) + ((log.subject || '').length > 40 ? '...' : '');
        const time = log.created_at ? new Date(log.created_at).toLocaleString('en-IN', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',hour12:true}) : '—';

        // Highlight row if failed or fallback
        const rowStyle = log.status === 'failed' ? 'background:#fef2f2;' : log.status === 'fallback' ? 'background:#fffbeb;' : '';

        // Detail button if there's error or fallback info
        const hasDetail = log.error_message || log.fallback_reason;
        const detailBtn = hasDetail ? `<button class="log-detail-btn" onclick='showLogDetail(${JSON.stringify(log).replace(/'/g, "\\'")})'><i class="fas fa-info-circle"></i> View</button>` : '<span style="color:#d1d5db;">—</span>';

        // Show mismatch between assigned and sent
        let sentFromHtml = sentFrom;
        if (log.status === 'fallback' && log.assigned_email && log.sent_from_email && log.assigned_email !== log.sent_from_email) {
            sentFromHtml = `<span style="color:#d97706;font-weight:700;">${log.sent_from_email}</span>`;
        }

        return `<tr style="${rowStyle}">
            <td><span class="log-badge ${log.status}">${statusIcons[log.status] || log.status}</span></td>
            <td style="font-weight:600;font-size:12px;">${taskLabel}</td>
            <td style="font-size:12px;">${assigned}</td>
            <td style="font-size:12px;">${sentFromHtml}</td>
            <td style="font-size:12px;">${log.recipient_email || '—'}</td>
            <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${(log.subject||'').replace(/"/g,'&quot;')}">${subjectShort || '—'}</td>
            <td>${detailBtn}</td>
            <td style="font-size:11px;color:#6b7280;white-space:nowrap;">${time}</td>
        </tr>`;
    }).join('');
}

function showLogDetail(log) {
    // Remove any existing popup
    document.querySelectorAll('.log-detail-overlay,.log-detail-popup').forEach(el => el.remove());

    const statusColors = { success: '#166534', failed: '#991b1b', fallback: '#92400e' };
    const statusBg = { success: '#dcfce7', failed: '#fee2e2', fallback: '#fef3c7' };
    const statusLabels = { success: 'Delivered Successfully', failed: 'Delivery Failed', fallback: 'Sent via Fallback Email' };

    let detailHtml = `
        <div class="log-detail-overlay" onclick="this.nextElementSibling.remove();this.remove();"></div>
        <div class="log-detail-popup">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h4 style="margin:0;font-size:16px;color:#1f2937;"><i class="fas fa-envelope-open-text" style="color:#C5A059;margin-right:8px;"></i>Email Send Details</h4>
                <button onclick="this.parentElement.parentElement.previousElementSibling.remove();this.parentElement.parentElement.remove();" style="background:none;border:none;font-size:20px;cursor:pointer;color:#6b7280;">&times;</button>
            </div>

            <div style="background:${statusBg[log.status]};padding:12px 16px;border-radius:8px;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                <span class="log-badge ${log.status}" style="font-size:13px;padding:4px 14px;">${statusLabels[log.status] || log.status}</span>
            </div>

            <table style="width:100%;font-size:13px;border-collapse:collapse;">
                <tr><td style="padding:8px 0;color:#6b7280;width:140px;vertical-align:top;"><b>Task:</b></td><td style="padding:8px 0;color:#1f2937;">${(log.task_key||'—').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;vertical-align:top;"><b>Assigned Email:</b></td><td style="padding:8px 0;color:#1f2937;">${log.assigned_email || '— Not assigned —'}</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;vertical-align:top;"><b>Sent From:</b></td><td style="padding:8px 0;color:${log.status==='fallback'?'#d97706':'#1f2937'};font-weight:${log.status==='fallback'?'700':'400'};">${log.sent_from_email || '— Not sent —'}</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;vertical-align:top;"><b>Recipient:</b></td><td style="padding:8px 0;color:#1f2937;">${log.recipient_email || '—'}</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;vertical-align:top;"><b>Subject:</b></td><td style="padding:8px 0;color:#1f2937;">${log.subject || '—'}</td></tr>
                <tr><td style="padding:8px 0;color:#6b7280;vertical-align:top;"><b>Time:</b></td><td style="padding:8px 0;color:#1f2937;">${log.created_at || '—'}</td></tr>
            </table>`;

    if (log.error_message) {
        detailHtml += `<div style="margin-top:12px;padding:12px 16px;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;">
            <div style="font-weight:700;color:#991b1b;font-size:12px;margin-bottom:4px;"><i class="fas fa-exclamation-triangle"></i> Error Details</div>
            <div style="color:#7f1d1d;font-size:13px;line-height:1.6;">${log.error_message}</div>
        </div>`;
    }

    if (log.fallback_reason) {
        detailHtml += `<div style="margin-top:10px;padding:12px 16px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;">
            <div style="font-weight:700;color:#92400e;font-size:12px;margin-bottom:4px;"><i class="fas fa-exchange-alt"></i> Fallback Info</div>
            <div style="color:#78350f;font-size:13px;line-height:1.6;">${log.fallback_reason}</div>
        </div>`;
    }

    detailHtml += '</div>';
    document.body.insertAdjacentHTML('beforeend', detailHtml);
}

async function clearOldLogs() {
    if (!confirm('Clear email logs older than 30 days?')) return;
    try {
        const form = new FormData();
        form.append('action', 'clear_email_logs');
        const res = await fetch(API, { method: 'POST', body: form });
        const json = await res.json();
        toast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadEmailLogs();
    } catch (e) { toast('Failed to clear logs', 'error'); }
}

// Check for failures on page load and show badge
async function checkEmailAlerts() {
    try {
        const res = await fetch(API + '?action=get_email_logs&filter=&limit=5');
        const json = await res.json();
        if (json.success && json.stats?.today) {
            const failed = (json.stats.today.failed || 0) + (json.stats.today.fallback || 0);
            const badge = document.getElementById('logAlertBadge');
            if (failed > 0) { badge.style.display = 'inline'; badge.textContent = failed; }
        }
    } catch(e) {}
}

// Init
loadEmails();
checkEmailAlerts();
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
