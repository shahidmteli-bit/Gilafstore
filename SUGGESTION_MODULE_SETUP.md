# Idea & Suggestion Center - Setup & Integration Guide

## 🎯 Overview
A comprehensive improvement and suggestion management system with rewards, email notifications, and analytics.

---

## 📦 Installation Steps

### Step 1: Create Database Tables

Run this SQL in phpMyAdmin:

```sql
-- Execute the file: database_suggestions_schema.sql
```

This creates 5 tables:
- `suggestions` - Main suggestions storage
- `suggestion_rewards` - Rewards management
- `suggestion_audit_log` - Activity tracking
- `suggestion_email_log` - Email delivery tracking
- `suggestion_settings` - Module configuration

---

## 🔗 Frontend Integration

### Method 1: Add to Footer

Add this to your footer file (e.g., `includes/footer.php` or `includes/new-footer.php`):

```php
<!-- Suggestion Modal -->
<?php include __DIR__ . '/suggestion_modal.php'; ?>

<!-- Footer Link -->
<a href="#" onclick="openSuggestionModal('footer'); return false;">
    <i class="fas fa-lightbulb me-2"></i>Suggest an Improvement
</a>
```

### Method 2: Add to Help/Support Pages

```php
<!-- Include modal -->
<?php include __DIR__ . '/includes/suggestion_modal.php'; ?>

<!-- Button -->
<button class="btn btn-primary" onclick="openSuggestionModal('help')">
    <i class="fas fa-lightbulb me-2"></i>Share Your Ideas
</button>
```

### Method 3: Chatbot Integration

```javascript
// Add this option to your chatbot
{
    text: "💡 Suggest an Improvement",
    action: function() {
        openSuggestionModal('chatbot');
    }
}
```

### Method 4: Floating Button (Anywhere on Site)

Add this to your main layout:

```html
<!-- Floating Suggestion Button -->
<button class="btn btn-primary rounded-circle position-fixed" 
        style="bottom: 80px; right: 20px; width: 60px; height: 60px; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.3);"
        onclick="openSuggestionModal('floating')"
        title="Share Your Ideas">
    <i class="fas fa-lightbulb fa-lg"></i>
</button>

<!-- Include Modal -->
<?php include __DIR__ . '/includes/suggestion_modal.php'; ?>
```

---

## 🎨 Admin Panel Access

### Navigation Added
The module is automatically added to admin sidebar:
- **Icon:** 💡 Lightbulb
- **Label:** Idea & Suggestion Center
- **URL:** `admin/suggestions_center.php`

### Admin Features

1. **Dashboard View** (`suggestions_center.php`)
   - View all suggestions
   - Filter by status, category, date
   - Statistics cards
   - Search functionality

2. **Manage Suggestions** (`suggestion_manage.php`)
   - Update status (New → Under Review → Accepted/Rejected)
   - Add admin notes
   - Mark as "Best Suggestion"
   - Assign rewards
   - View activity log

---

## 📧 Email Notifications

### Automatic Emails Sent:

1. **Acknowledgment Email** - Immediately after submission
2. **Accepted Email** - When suggestion is accepted
3. **Rejected Email** - When suggestion is rejected (with optional reason)
4. **Reward Email** - When reward is assigned

### Email Configuration

Uses existing email config from `includes/email_config.php`:
- Sender: gilaf.secure@gmail.com
- Display Name: Gilaf Innovation Team

---

## 🎁 Rewards System

### Reward Types Available:
- Coupon
- Cashback
- Voucher
- Discount
- Points
- Physical Gift
- Other

### How to Assign Rewards:

1. Go to `Idea & Suggestion Center`
2. Click "Manage" on any suggestion
3. Scroll to "Rewards" section
4. Click "Add Reward"
5. Fill in:
   - Reward Type
   - Value (₹)
   - Description
   - Code (optional)
   - Expiry Date (optional)
6. Submit

User receives email with reward details automatically.

---

## 📊 Analytics & Insights

### Available Metrics:
- Total suggestions received
- New vs reviewed ratio
- Accepted vs rejected ratio
- Best suggestions count
- Category-wise distribution
- Date range filtering

### Export Options:
- Filter by date range
- Filter by category
- Filter by status
- Search by keywords
- Show only "Best Ideas"

---

## 🔧 Configuration Settings

Located in `suggestion_settings` table:

```sql
-- Enable/disable guest submissions
UPDATE suggestion_settings SET setting_value = 'true' WHERE setting_key = 'enable_guest_submissions';

-- Require email for guests
UPDATE suggestion_settings SET setting_value = 'true' WHERE setting_key = 'require_email';

-- Auto-send acknowledgment
UPDATE suggestion_settings SET setting_value = 'true' WHERE setting_key = 'auto_send_acknowledgment';

-- Max submissions per day
UPDATE suggestion_settings SET setting_value = '5' WHERE setting_key = 'max_submissions_per_day';
```

---

## 🎯 Workflow

### User Journey:
1. User clicks "Suggest an Improvement" (footer/chatbot/help)
2. Modal opens with form
3. User fills: Subject, Category, Description, Email (if guest)
4. Submits suggestion
5. Receives tracking ID and acknowledgment email

### Admin Journey:
1. Admin sees new suggestion in dashboard
2. Reviews suggestion details
3. Updates status to "Under Review"
4. Accepts or Rejects with notes
5. If accepted → Can mark as "Best" and assign reward
6. User receives email notification

---

## 🚀 Testing

### Test Submission:
1. Go to your website
2. Click "Suggest an Improvement" link
3. Fill form and submit
4. Check email for acknowledgment
5. Login to admin panel
6. Go to "Idea & Suggestion Center"
7. You should see your submission

### Test Admin Actions:
1. Click "Manage" on a suggestion
2. Change status to "Accepted"
3. Add admin notes
4. Mark as "Best Suggestion"
5. Add a reward
6. Check if user receives emails

---

## 🎨 Customization

### Change Colors:
Edit `includes/suggestion_modal.php` - Look for gradient colors:
```css
background: linear-gradient(135deg, #1A3C34 0%, #2d5a4d 100%);
```

### Change Categories:
Edit `includes/suggestion_modal.php` - Update the select options:
```html
<option value="YourCategory">🎨 Your Category</option>
```

Also update database enum in `suggestions` table.

### Change Email Templates:
Edit `admin/suggestion_manage.php` - Functions:
- `getAcceptedEmailTemplate()`
- `getRejectedEmailTemplate()`
- `getRewardEmailTemplate()`

---

## 📱 Mobile Responsive

The suggestion modal is fully responsive:
- Works on all screen sizes
- Touch-friendly buttons
- Optimized form layout
- Smooth animations

---

## 🔒 Security Features

- ✅ Rate limiting (5 submissions per day per user/IP)
- ✅ Input validation and sanitization
- ✅ SQL injection protection (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ CSRF protection (session-based)
- ✅ Email validation
- ✅ Audit trail for all actions

---

## 📈 Future Enhancements (Optional)

- Voting/upvoting system
- Public suggestion board
- AI-based categorization
- Duplicate detection
- Internal team comments
- Implementation tracking
- ROI calculation for implemented ideas

---

## 🆘 Troubleshooting

### Issue: Modal not opening
**Solution:** Ensure Bootstrap 5 JS is loaded and `suggestion_modal.php` is included.

### Issue: Emails not sending
**Solution:** Check `includes/email_config.php` SMTP settings.

### Issue: Database errors
**Solution:** Run `database_suggestions_schema.sql` to create tables.

### Issue: 404 on admin pages
**Solution:** Ensure files are in `admin/` folder and paths are correct.

---

## 📞 Support

For issues or questions:
1. Check error logs: `admin/error_logs.php`
2. Check email log: `suggestion_email_log` table
3. Check audit trail: `suggestion_audit_log` table

---

## ✅ Checklist

- [ ] Database tables created
- [ ] Modal included in footer
- [ ] Modal included in help pages
- [ ] Chatbot integration (if applicable)
- [ ] Admin panel accessible
- [ ] Test submission works
- [ ] Emails are being sent
- [ ] Rewards system tested
- [ ] Analytics dashboard working

---

**🎉 Your Idea & Suggestion Center is ready to capture innovation!**
