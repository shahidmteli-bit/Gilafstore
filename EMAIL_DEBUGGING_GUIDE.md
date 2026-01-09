# Email System Debugging Guide

## Overview
This guide provides comprehensive troubleshooting steps for the Gilaf Store email notification system.

## System Configuration

### SMTP Settings
- **Host:** smtp.gmail.com
- **Port:** 587
- **Encryption:** STARTTLS
- **Username:** gilaf.secure@gmail.com
- **Authentication:** Gmail App Password

### Email Functions
1. `getMailer()` - Returns configured PHPMailer instance
2. `send_email()` - Generic email sending function
3. `send_password_reset_email()` - Password reset emails
4. `sendStatusUpdateEmail()` - Suggestion status updates
5. `sendRewardEmail()` - Reward notifications

## Testing Email System

### Step 1: Run Diagnostic Script
Navigate to: `http://localhost/Gilaf%20Ecommerce%20website/test_email.php`

This script will:
- ✅ Verify PHPMailer library is loaded
- ✅ Check if getMailer() function exists
- ✅ Test SMTP connection
- ✅ Send test email
- ✅ Display error logs

### Step 2: Check Error Logs
**Location:** Check your PHP error log (usually in XAMPP: `C:\xampp\apache\logs\error.log`)

**Look for:**
- `PHPMailer: Mailer instance created successfully`
- `send_email() called - To: [email], Subject: [subject]`
- `SUCCESS: Email sent to [email]`
- `FAILED: Could not send email`
- `EXCEPTION: [error details]`

### Step 3: Verify Gmail Settings
1. **App Password:** Ensure `mzzn wtnw mmuj kqqo` is valid
2. **2FA:** Gmail account must have 2-factor authentication enabled
3. **Less Secure Apps:** Not needed with App Password
4. **SMTP Access:** Ensure Gmail SMTP is not blocked

### Step 4: Check Firewall/Antivirus
- Port 587 must be open
- SMTP connections must be allowed
- Check Windows Firewall settings
- Temporarily disable antivirus to test

## Common Issues & Solutions

### Issue 1: "Call to undefined function getMailer()"
**Cause:** email_config.php not included or getMailer() not defined
**Solution:** 
```php
require_once __DIR__ . '/includes/email_config.php';
```

### Issue 2: SMTP Connection Timeout
**Cause:** Firewall blocking port 587 or network issues
**Solution:**
- Check firewall settings
- Verify internet connection
- Try port 465 with SSL instead of STARTTLS

### Issue 3: Authentication Failed
**Cause:** Invalid Gmail credentials or App Password
**Solution:**
- Regenerate Gmail App Password
- Verify username is correct
- Check for typos in password

### Issue 4: Emails Not Received
**Cause:** Spam filtering or recipient issues
**Solution:**
- Check spam/junk folder
- Verify recipient email is valid
- Check email logs in database: `suggestion_email_log`

### Issue 5: Silent Failures
**Cause:** Errors not being logged
**Solution:**
- Check PHP error_reporting is enabled
- Verify error_log directive in php.ini
- Check file permissions on log files

## Database Email Logging

### Check Email Log Table
```sql
SELECT * FROM suggestion_email_log 
ORDER BY sent_at DESC 
LIMIT 20;
```

### Fields:
- `suggestion_id` - Related suggestion
- `email_type` - Type of email (acknowledgment, reward, etc.)
- `recipient_email` - Recipient address
- `subject` - Email subject
- `status` - 'sent' or 'failed'
- `error_message` - Error details if failed
- `sent_at` - Timestamp

## Enable Debug Mode

### Temporary Debug Mode
Edit `includes/email_config.php`:
```php
$mail->SMTPDebug = 2; // Change from 0 to 2
```

**Debug Levels:**
- 0 = Off
- 1 = Client messages
- 2 = Client and server messages
- 3 = Client, server, and connection status
- 4 = Low-level data output

### View Debug Output
Check error log for detailed SMTP conversation:
```
PHPMailer Debug [2]: SMTP -> FROM SERVER: 220 smtp.gmail.com ESMTP
PHPMailer Debug [2]: SMTP -> FROM SERVER: 250-smtp.gmail.com at your service
```

## Email Sending Checklist

Before sending emails, verify:
- [ ] PHPMailer library installed in vendor/phpmailer/
- [ ] email_config.php included in script
- [ ] getMailer() function exists and returns PHPMailer instance
- [ ] Recipient email is valid and not empty
- [ ] SMTP credentials are correct
- [ ] Port 587 is open and accessible
- [ ] Error logging is enabled
- [ ] Database email_log table exists

## Testing Individual Email Functions

### Test Suggestion Acknowledgment Email
1. Submit a suggestion via the form
2. Check error log for: `Attempting to send acknowledgment email to: [email]`
3. Check for success/failure message
4. Verify email received

### Test Reward Email
1. Add reward to a suggestion in admin panel
2. Check error log for reward email attempt
3. Verify email in recipient inbox
4. Check suggestion_email_log table

### Test Status Update Email
1. Update suggestion status in admin panel
2. Check error log for status update email
3. Verify email received
4. Check for proper template rendering

## Performance Optimization

### Reduce Email Sending Time
- Use asynchronous email sending (queue system)
- Implement email batching for bulk sends
- Cache mailer instance when sending multiple emails

### Monitor Email Delivery
- Track open rates (add tracking pixel)
- Monitor bounce rates
- Check spam score of emails

## Security Best Practices

1. **Never hardcode passwords** - Use environment variables
2. **Validate recipient emails** - Prevent email injection
3. **Rate limit email sending** - Prevent abuse
4. **Log all email attempts** - Audit trail
5. **Use TLS/SSL** - Encrypt SMTP connection
6. **Sanitize email content** - Prevent XSS in HTML emails

## Support & Resources

### PHPMailer Documentation
https://github.com/PHPMailer/PHPMailer

### Gmail SMTP Settings
https://support.google.com/mail/answer/7126229

### Error Log Location
- Windows (XAMPP): `C:\xampp\apache\logs\error.log`
- Linux: `/var/log/apache2/error.log` or `/var/log/php_errors.log`

## Troubleshooting Commands

### Check PHP Error Log
```bash
tail -f C:\xampp\apache\logs\error.log
```

### Test SMTP Connection (Telnet)
```bash
telnet smtp.gmail.com 587
```

### Check Port Availability
```bash
netstat -an | findstr :587
```

## Contact
For additional support, check the error logs and email_log database table for detailed failure information.

---
**Last Updated:** January 4, 2026
**Version:** 1.0
