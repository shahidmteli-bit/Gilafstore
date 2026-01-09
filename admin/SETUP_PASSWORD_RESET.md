# Admin Password Reset Setup Guide

## 📋 Overview
Complete password reset system for admin panel with email-based reset links.

---

## 🗄️ Step 1: Create Database Table

Run this SQL in phpMyAdmin:

```sql
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Or run the file: `admin/password_resets_table.sql`

---

## 📧 Step 2: Configure Gmail SMTP

### A. Enable 2-Factor Authentication
1. Go to: https://myaccount.google.com/security
2. Enable **2-Step Verification**

### B. Generate App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select app: **Mail**
3. Select device: **Other (Custom name)**
4. Enter name: **Gilaf Admin Panel**
5. Click **Generate**
6. **Copy the 16-character password**

### C. Update Email Configuration
Edit: `includes/email_config.php`

Find line 30 and replace:
```php
$mail->Password   = 'YOUR_APP_PASSWORD_HERE';
```

With:
```php
$mail->Password   = 'xxxx xxxx xxxx xxxx'; // Your 16-char app password
```

---

## 📦 Step 3: Install PHPMailer

### Option A: Using Composer (Recommended)
```bash
cd c:\xampp\htdocs\Gilaf Ecommerce website
composer require phpmailer/phpmailer
```

### Option B: Manual Installation
1. Download: https://github.com/PHPMailer/PHPMailer/archive/master.zip
2. Extract to: `vendor/phpmailer/phpmailer/`
3. Update `includes/email_config.php` line 11:
```php
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
```

---

## 🧪 Step 4: Test the System

### Test Flow:
1. Go to: `http://localhost/Gilaf%20Ecommerce%20website/admin/admin_login.php`
2. Click **"Forgot Password?"**
3. Enter admin email
4. Check email inbox (and spam folder)
5. Click reset link in email
6. Set new password
7. Login with new password

---

## 📁 Files Created

| File | Purpose |
|------|---------|
| `admin/forgot_password.php` | Request password reset |
| `admin/reset_password.php` | Set new password |
| `includes/email_config.php` | Email sending configuration |
| `admin/password_resets_table.sql` | Database schema |

---

## 🔒 Security Features

✅ **Secure tokens** - 64-character random tokens
✅ **1-hour expiration** - Links expire after 1 hour
✅ **One-time use** - Tokens marked as used after reset
✅ **Email verification** - Only registered admin emails
✅ **Password hashing** - Bcrypt password hashing
✅ **No email enumeration** - Same response for valid/invalid emails

---

## 🎨 Design Features

✅ **Brand colors** - Green (#1A3C34) & Gold (#C5A059)
✅ **Professional UI** - Glassmorphism effects
✅ **Responsive design** - Mobile-friendly
✅ **Email template** - Beautiful HTML email
✅ **Clear instructions** - User-friendly messages

---

## 🔧 Troubleshooting

### Email not sending?
1. Check Gmail App Password is correct
2. Verify 2FA is enabled on Gmail
3. Check `error_log` for errors
4. Test with: `test_email.php` (create test file)

### Token expired?
- Reset links expire in 1 hour
- Request a new reset link

### Can't find email?
- Check spam/junk folder
- Verify email address is correct
- Ensure admin account exists

---

## 📞 Support

For issues, check:
- PHP error logs: `logs/error.log`
- Apache error logs: `xampp/apache/logs/error.log`
- Email configuration: `includes/email_config.php`

---

## ✅ Checklist

- [ ] Database table created
- [ ] Gmail App Password generated
- [ ] PHPMailer installed
- [ ] Email config updated with app password
- [ ] Tested forgot password flow
- [ ] Tested email delivery
- [ ] Tested password reset
- [ ] Tested login with new password

---

**🎉 Once all steps are complete, your admin password reset system is ready!**
