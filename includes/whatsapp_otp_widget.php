<?php
/**
 * WhatsApp OTP Login Widget
 * Include this file where you want the WhatsApp login option to appear.
 * 
 * Usage: <?php include 'includes/whatsapp_otp_widget.php'; ?>
 */

// Check if WhatsApp OTP is enabled
$waOtpEnabled = false;
try {
    require_once __DIR__ . '/crm_engine.php';
    $crm = CRMEngine::getInstance();
    $waOtpEnabled = $crm->isEnabled() && $crm->getSetting('whatsapp_otp_enabled');
} catch (\Throwable $e) {
    // Silent fail
}

if (!$waOtpEnabled) return;
?>

<!-- WhatsApp OTP Login Widget -->
<div id="waOtpWidget" class="wa-otp-widget">
    <div class="wa-otp-divider">
        <span>or continue with</span>
    </div>
    
    <button type="button" class="wa-otp-btn" onclick="waOtpShowModal()">
        <i class="fab fa-whatsapp"></i>
        <span>Login with WhatsApp</span>
    </button>
</div>

<!-- WhatsApp OTP Modal -->
<div id="waOtpModal" class="wa-otp-modal" style="display:none;">
    <div class="wa-otp-modal-content">
        <button type="button" class="wa-otp-close" onclick="waOtpCloseModal()">&times;</button>
        
        <div class="wa-otp-header">
            <div class="wa-otp-icon">
                <i class="fab fa-whatsapp"></i>
            </div>
            <h3>Login with WhatsApp</h3>
            <p>We'll send a verification code to your WhatsApp</p>
        </div>
        
        <!-- Step 1: Phone Input -->
        <div id="waOtpStep1" class="wa-otp-step">
            <div class="wa-otp-input-group">
                <span class="wa-otp-prefix">+91</span>
                <input type="tel" id="waOtpPhone" class="wa-otp-phone" 
                       placeholder="Enter your mobile number" 
                       maxlength="10" 
                       pattern="[0-9]{10}"
                       autocomplete="tel">
            </div>
            <button type="button" class="wa-otp-submit" onclick="waOtpSendCode()">
                <span>Send Code</span>
                <i class="fas fa-arrow-right"></i>
            </button>
            <p class="wa-otp-terms">By continuing, you agree to our <a href="<?= base_url('privacy-policy.php') ?>">Privacy Policy</a></p>
        </div>
        
        <!-- Step 2: OTP Input -->
        <div id="waOtpStep2" class="wa-otp-step" style="display:none;">
            <p class="wa-otp-sent-to">Code sent to <strong id="waOtpPhoneDisplay"></strong></p>
            
            <div class="wa-otp-digits">
                <input type="text" maxlength="1" class="wa-otp-digit" data-index="0" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="wa-otp-digit" data-index="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="wa-otp-digit" data-index="2" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="wa-otp-digit" data-index="3" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="wa-otp-digit" data-index="4" inputmode="numeric" pattern="[0-9]">
                <input type="text" maxlength="1" class="wa-otp-digit" data-index="5" inputmode="numeric" pattern="[0-9]">
            </div>
            
            <button type="button" class="wa-otp-submit" onclick="waOtpVerify()">
                <span>Verify & Login</span>
            </button>
            
            <div class="wa-otp-resend">
                <span id="waOtpTimer">Resend in <span id="waOtpCountdown">60</span>s</span>
                <button type="button" id="waOtpResendBtn" style="display:none;" onclick="waOtpResend()">
                    Resend Code
                </button>
            </div>
            
            <button type="button" class="wa-otp-back" onclick="waOtpGoBack()">
                <i class="fas fa-arrow-left"></i> Change number
            </button>
        </div>
        
        <!-- Loading State -->
        <div id="waOtpLoading" class="wa-otp-loading" style="display:none;">
            <div class="wa-otp-spinner"></div>
            <p>Please wait...</p>
        </div>
        
        <!-- Error Message -->
        <div id="waOtpError" class="wa-otp-error" style="display:none;"></div>
    </div>
</div>

<style>
.wa-otp-widget { margin: 20px 0; }
.wa-otp-divider { display: flex; align-items: center; margin: 16px 0; color: #6b7280; font-size: 13px; }
.wa-otp-divider::before, .wa-otp-divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
.wa-otp-divider span { padding: 0 12px; }
.wa-otp-btn { width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 20px; border: 2px solid #25D366; background: #fff; color: #25D366; font-size: 15px; font-weight: 600; border-radius: 12px; cursor: pointer; transition: all 0.2s; }
.wa-otp-btn:hover { background: #25D366; color: #fff; }
.wa-otp-btn i { font-size: 20px; }

.wa-otp-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px; animation: waFadeIn 0.2s ease; }
@keyframes waFadeIn { from { opacity: 0; } to { opacity: 1; } }
.wa-otp-modal-content { background: #fff; border-radius: 20px; width: 100%; max-width: 400px; padding: 32px; position: relative; animation: waSlideUp 0.3s ease; }
@keyframes waSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.wa-otp-close { position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border: none; background: #f3f4f6; border-radius: 50%; font-size: 20px; cursor: pointer; color: #6b7280; }
.wa-otp-close:hover { background: #e5e7eb; }

.wa-otp-header { text-align: center; margin-bottom: 24px; }
.wa-otp-icon { width: 64px; height: 64px; background: linear-gradient(135deg, #25D366, #128C7E); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.wa-otp-icon i { font-size: 32px; color: #fff; }
.wa-otp-header h3 { font-size: 22px; font-weight: 700; color: #1a1a1a; margin: 0 0 8px; }
.wa-otp-header p { font-size: 14px; color: #6b7280; margin: 0; }

.wa-otp-input-group { display: flex; align-items: center; border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden; margin-bottom: 16px; transition: border-color 0.2s; }
.wa-otp-input-group:focus-within { border-color: #25D366; }
.wa-otp-prefix { padding: 14px 12px; background: #f9fafb; font-weight: 600; color: #374151; border-right: 1px solid #e5e7eb; }
.wa-otp-phone { flex: 1; border: none; padding: 14px; font-size: 16px; outline: none; }

.wa-otp-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, #25D366, #128C7E); color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s, box-shadow 0.2s; }
.wa-otp-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,211,102,0.3); }
.wa-otp-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.wa-otp-terms { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 16px; }
.wa-otp-terms a { color: #25D366; text-decoration: none; }

.wa-otp-sent-to { text-align: center; margin-bottom: 20px; color: #6b7280; font-size: 14px; }
.wa-otp-digits { display: flex; gap: 8px; justify-content: center; margin-bottom: 20px; }
.wa-otp-digit { width: 48px; height: 56px; border: 2px solid #e5e7eb; border-radius: 12px; text-align: center; font-size: 24px; font-weight: 700; outline: none; transition: border-color 0.2s; }
.wa-otp-digit:focus { border-color: #25D366; }

.wa-otp-resend { text-align: center; margin-top: 16px; font-size: 14px; color: #6b7280; }
.wa-otp-resend button { background: none; border: none; color: #25D366; font-weight: 600; cursor: pointer; font-size: 14px; }
.wa-otp-back { background: none; border: none; color: #6b7280; font-size: 14px; cursor: pointer; margin-top: 16px; display: flex; align-items: center; gap: 6px; }
.wa-otp-back:hover { color: #25D366; }

.wa-otp-loading { text-align: center; padding: 40px 0; }
.wa-otp-spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #25D366; border-radius: 50%; animation: waSpin 0.8s linear infinite; margin: 0 auto 16px; }
@keyframes waSpin { to { transform: rotate(360deg); } }

.wa-otp-error { background: #fef2f2; color: #dc2626; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-top: 16px; text-align: center; }

@media (max-width: 480px) {
    .wa-otp-modal-content { padding: 24px; margin: 10px; }
    .wa-otp-digit { width: 42px; height: 50px; font-size: 20px; }
}
</style>

<script>
(function() {
    let waOtpPhone = '';
    let waOtpCountdownInterval = null;

    window.waOtpShowModal = function() {
        document.getElementById('waOtpModal').style.display = 'flex';
        document.getElementById('waOtpPhone').focus();
        document.body.style.overflow = 'hidden';
    };

    window.waOtpCloseModal = function() {
        document.getElementById('waOtpModal').style.display = 'none';
        document.body.style.overflow = '';
        waOtpReset();
    };

    window.waOtpSendCode = async function() {
        const phoneInput = document.getElementById('waOtpPhone');
        const phone = phoneInput.value.replace(/\D/g, '');
        
        if (phone.length !== 10) {
            waOtpShowError('Please enter a valid 10-digit mobile number');
            return;
        }
        
        waOtpPhone = '+91' + phone;
        waOtpShowLoading(true);
        
        try {
            const res = await fetch('<?= base_url('api/whatsapp_otp.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'send', phone: waOtpPhone })
            });
            const data = await res.json();
            
            waOtpShowLoading(false);
            
            if (data.success) {
                document.getElementById('waOtpPhoneDisplay').textContent = waOtpPhone;
                document.getElementById('waOtpStep1').style.display = 'none';
                document.getElementById('waOtpStep2').style.display = 'block';
                waOtpStartCountdown(60);
                document.querySelector('.wa-otp-digit[data-index="0"]').focus();
            } else {
                waOtpShowError(data.error || 'Failed to send OTP. Please try again.');
            }
        } catch (err) {
            waOtpShowLoading(false);
            waOtpShowError('Network error. Please check your connection.');
        }
    };

    window.waOtpVerify = async function() {
        const digits = document.querySelectorAll('.wa-otp-digit');
        let otp = '';
        digits.forEach(d => otp += d.value);
        
        if (otp.length !== 6) {
            waOtpShowError('Please enter the complete 6-digit code');
            return;
        }
        
        waOtpShowLoading(true);
        
        try {
            const res = await fetch('<?= base_url('api/whatsapp_otp.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'verify', phone: waOtpPhone, otp: otp })
            });
            const data = await res.json();
            
            if (data.success) {
                // Redirect to dashboard or intended page
                window.location.href = data.redirect || '<?= base_url('user/dashboard.php') ?>';
            } else {
                waOtpShowLoading(false);
                waOtpShowError(data.error || 'Invalid OTP. Please try again.');
                // Clear OTP inputs
                digits.forEach(d => d.value = '');
                digits[0].focus();
            }
        } catch (err) {
            waOtpShowLoading(false);
            waOtpShowError('Network error. Please try again.');
        }
    };

    window.waOtpResend = async function() {
        waOtpShowLoading(true);
        
        try {
            const res = await fetch('<?= base_url('api/whatsapp_otp.php') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'resend', phone: waOtpPhone })
            });
            const data = await res.json();
            
            waOtpShowLoading(false);
            
            if (data.success) {
                waOtpStartCountdown(60);
                waOtpHideError();
            } else {
                if (data.retry_after) {
                    waOtpStartCountdown(data.retry_after);
                }
                waOtpShowError(data.error || 'Failed to resend. Please wait.');
            }
        } catch (err) {
            waOtpShowLoading(false);
            waOtpShowError('Network error.');
        }
    };

    window.waOtpGoBack = function() {
        document.getElementById('waOtpStep2').style.display = 'none';
        document.getElementById('waOtpStep1').style.display = 'block';
        waOtpHideError();
        if (waOtpCountdownInterval) clearInterval(waOtpCountdownInterval);
    };

    function waOtpStartCountdown(seconds) {
        const timerEl = document.getElementById('waOtpTimer');
        const countdownEl = document.getElementById('waOtpCountdown');
        const resendBtn = document.getElementById('waOtpResendBtn');
        
        timerEl.style.display = 'inline';
        resendBtn.style.display = 'none';
        
        if (waOtpCountdownInterval) clearInterval(waOtpCountdownInterval);
        
        let remaining = seconds;
        countdownEl.textContent = remaining;
        
        waOtpCountdownInterval = setInterval(() => {
            remaining--;
            countdownEl.textContent = remaining;
            
            if (remaining <= 0) {
                clearInterval(waOtpCountdownInterval);
                timerEl.style.display = 'none';
                resendBtn.style.display = 'inline';
            }
        }, 1000);
    }

    function waOtpShowLoading(show) {
        document.getElementById('waOtpLoading').style.display = show ? 'block' : 'none';
        document.getElementById('waOtpStep1').style.display = show ? 'none' : (document.getElementById('waOtpStep2').style.display === 'none' ? 'block' : 'none');
        document.getElementById('waOtpStep2').style.display = show ? 'none' : (document.getElementById('waOtpStep1').style.display === 'none' ? 'block' : 'none');
    }

    function waOtpShowError(msg) {
        const errEl = document.getElementById('waOtpError');
        errEl.textContent = msg;
        errEl.style.display = 'block';
    }

    function waOtpHideError() {
        document.getElementById('waOtpError').style.display = 'none';
    }

    function waOtpReset() {
        document.getElementById('waOtpPhone').value = '';
        document.querySelectorAll('.wa-otp-digit').forEach(d => d.value = '');
        document.getElementById('waOtpStep1').style.display = 'block';
        document.getElementById('waOtpStep2').style.display = 'none';
        document.getElementById('waOtpLoading').style.display = 'none';
        waOtpHideError();
        if (waOtpCountdownInterval) clearInterval(waOtpCountdownInterval);
    }

    // OTP digit auto-advance
    document.querySelectorAll('.wa-otp-digit').forEach((input, idx, arr) => {
        input.addEventListener('input', (e) => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val.slice(0, 1);
            if (val && idx < arr.length - 1) {
                arr[idx + 1].focus();
            }
            // Auto-verify when all digits filled
            if (idx === arr.length - 1 && val) {
                let allFilled = true;
                arr.forEach(d => { if (!d.value) allFilled = false; });
                if (allFilled) waOtpVerify();
            }
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                arr[idx - 1].focus();
            }
        });
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            paste.split('').slice(0, 6).forEach((char, i) => {
                if (arr[i]) arr[i].value = char;
            });
            arr[Math.min(paste.length, 5)].focus();
            if (paste.length >= 6) waOtpVerify();
        });
    });

    // Close modal on escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.getElementById('waOtpModal').style.display === 'flex') {
            waOtpCloseModal();
        }
    });

    // Close modal on backdrop click
    document.getElementById('waOtpModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'waOtpModal') waOtpCloseModal();
    });
})();
</script>
