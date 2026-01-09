# AI Customer Support Chatbot - Setup Guide

## 🎯 Overview
A professional AI-powered customer support chatbot for Gilaf eCommerce website with automated responses, knowledge base, and escalation to human support.

---

## 📋 Features

### ✅ Automated Support
- **Product Information** - Details about saffron, spices, quality, certifications
- **Order Management** - Track orders, order process, delivery timelines
- **Payment Help** - Payment methods, failed transactions, refund queries
- **Policy Information** - Returns, refunds, cancellations, shipping
- **Account Support** - Login, signup, password reset
- **Batch Verification** - QR code scanning, authenticity checks
- **Distributor Info** - Partnership opportunities

### ✅ Smart Features
- **Knowledge Base Matching** - Keyword-based intelligent responses
- **Quick Action Buttons** - Fast navigation to relevant pages
- **Typing Indicators** - Natural conversation feel
- **Suggested Topics** - Welcome screen with common queries
- **Escalation System** - WhatsApp, callback requests, phone support
- **Mobile Responsive** - Works on all devices

### ✅ Professional UI/UX
- **Premium Design** - Matches Gilaf brand colors (Green #1A3C34, Gold #C5A089)
- **Glass Morphism** - Modern, elegant interface
- **Smooth Animations** - Fade-in messages, typing indicators
- **Notification Badge** - Alerts for new messages
- **Minimizable Widget** - Doesn't interfere with browsing

---

## 🚀 Installation Steps

### Step 1: Database Setup
Run the SQL file to create required tables:

```bash
# Import the database schema
mysql -u root -p gilaf_db < chatbot_database.sql
```

Or manually execute in phpMyAdmin:
- Open phpMyAdmin
- Select your database
- Go to SQL tab
- Copy and paste contents of `chatbot_database.sql`
- Click "Go"

### Step 2: Update Contact Information
Edit `assets/js/chatbot.js` and update:

**Line 370-380** - Contact information in the 'contact' response:
```javascript
'contact': {
    response: 'We\'re here to help! 📞\n\nContact Options:\n\n📱 WhatsApp: +91-XXXXXXXXXX\n📞 Phone: +91-XXXXXXXXXX\n📧 Email: support@gilaf.com\n⏰ Hours: Mon-Sat, 9 AM - 6 PM',
}
```

**Line 520** - WhatsApp link in escalation options:
```javascript
<a href="https://wa.me/91XXXXXXXXXX" target="_blank" class="escalation-btn whatsapp">
```

**Line 537** - Phone number in escalation:
```javascript
<a href="tel:+91XXXXXXXXXX" class="escalation-btn phone">
    ...
    <span>+91-XXXXXXXXXX</span>
```

Replace `XXXXXXXXXX` with your actual phone numbers.

### Step 3: Update Email in Callback Handler
Edit `chatbot_callback.php`:

**Line 36** - Admin email for notifications:
```php
$admin_email = "support@gilaf.com"; // Update with actual admin email
```

### Step 4: Verify Integration
The chatbot is already integrated into `includes/new-footer.php`. Verify the files are loaded:
- `assets/css/chatbot.css`
- `assets/js/chatbot.js`

### Step 5: Test the Chatbot
1. Open your website in a browser
2. Look for the chat icon in bottom-right corner
3. Click to open the chatbot
4. Try different queries:
   - "Track my order"
   - "Product information"
   - "Return policy"
   - "Contact support"

---

## 🎨 Customization

### Update Knowledge Base
Edit `assets/js/chatbot.js` - `initKnowledgeBase()` method (Line 12-390)

Add new categories:
```javascript
'new_category': {
    keywords: ['keyword1', 'keyword2', 'keyword3'],
    response: 'Your response text here\n\nUse \\n for line breaks',
    quickActions: ['Button 1', 'Button 2', 'Button 3']
}
```

### Change Colors
Edit `assets/css/chatbot.css` - CSS variables (Line 2-8):
```css
:root {
    --chatbot-primary: #1A3C34;      /* Main color */
    --chatbot-secondary: #C5A089;    /* Accent color */
    --chatbot-user-msg: #1A3C34;     /* User message bubble */
}
```

### Modify Widget Position
Edit `assets/css/chatbot.css` - `.chatbot-toggle` (Line 11-13):
```css
.chatbot-toggle {
    bottom: 30px;  /* Distance from bottom */
    right: 30px;   /* Distance from right */
}
```

---

## 📊 Admin Panel

### View Callback Requests
Access: `admin/manage_callbacks.php`

Features:
- View all callback requests
- Update status (Pending → Contacted → Completed)
- Add internal notes
- Statistics dashboard
- Filter by status

### Callback Statuses
- **Pending** - New request, not yet contacted
- **Contacted** - Customer has been called
- **Completed** - Issue resolved
- **Cancelled** - Request cancelled

---

## 🔧 Technical Details

### Files Structure
```
assets/
├── css/
│   └── chatbot.css           # Chatbot styles
└── js/
    └── chatbot.js            # Chatbot logic & knowledge base

includes/
└── new-footer.php            # Integration point

admin/
└── manage_callbacks.php      # Admin panel for callbacks

chatbot_callback.php          # Backend for callback requests
chatbot_database.sql          # Database schema
CHATBOT_SETUP_GUIDE.md       # This file
```

### Database Tables
- **callback_requests** - Stores callback requests from customers
- **chatbot_analytics** - (Optional) Tracks chatbot usage and conversations

### Key Functions
- `GilafChatbot` - Main chatbot class
- `initKnowledgeBase()` - Defines all responses
- `processMessage()` - Matches user queries to responses
- `handleQuickAction()` - Handles button clicks
- `getEscalationOptions()` - Shows contact options

---

## 🎯 Usage Guidelines

### What the Chatbot CAN Handle
✅ Product and service information  
✅ Order tracking and delivery queries  
✅ Payment methods and issues  
✅ Return, refund, and cancellation policies  
✅ Account help (login, signup, password)  
✅ Batch verification information  
✅ General FAQs  

### What Gets Escalated
❌ Personalized decisions or exceptions  
❌ Complex technical issues  
❌ Sensitive account modifications  
❌ Queries outside knowledge base  
❌ User explicitly asks for human support  

### Escalation Options
1. **WhatsApp** - Instant messaging support
2. **Request Callback** - Customer provides details, team calls back
3. **Phone Support** - Direct call to support number

---

## 🔒 Security Notes

- All user inputs are sanitized in `chatbot_callback.php`
- Phone number validation (10 digits)
- SQL injection protection with prepared statements
- XSS prevention with `escapeHtml()` function
- CSRF protection recommended for production

---

## 📱 Mobile Optimization

The chatbot is fully responsive:
- **Desktop** - 400px × 600px window, bottom-right corner
- **Mobile** - Full-screen overlay, optimized touch targets
- **Tablet** - Adaptive sizing

---

## 🚨 Troubleshooting

### Chatbot Not Appearing
1. Check browser console for JavaScript errors
2. Verify files are loaded: `chatbot.css` and `chatbot.js`
3. Clear browser cache (Ctrl + F5)
4. Check file paths in `new-footer.php`

### Responses Not Working
1. Check keyword matching in knowledge base
2. Verify JavaScript console for errors
3. Test with exact keywords from knowledge base

### Callback Form Not Submitting
1. Check database table exists: `callback_requests`
2. Verify database connection in `chatbot_callback.php`
3. Check PHP error logs
4. Ensure proper permissions on PHP file

### Styling Issues
1. Check if `chatbot.css` is loaded
2. Verify no CSS conflicts with existing styles
3. Check browser compatibility
4. Clear cache and reload

---

## 📈 Future Enhancements

Potential improvements:
- [ ] AI/ML integration for smarter responses
- [ ] Multi-language support
- [ ] Chat history for logged-in users
- [ ] File attachment support
- [ ] Live chat handoff to human agents
- [ ] Analytics dashboard
- [ ] Sentiment analysis
- [ ] Voice input support

---

## 📞 Support

For technical support or customization requests:
- **Email**: support@gilaf.com
- **Documentation**: This file
- **Admin Panel**: `admin/manage_callbacks.php`

---

## ✅ Checklist

Before going live:
- [ ] Database tables created
- [ ] Contact information updated (phone, WhatsApp, email)
- [ ] Knowledge base reviewed and customized
- [ ] Tested on desktop and mobile
- [ ] Admin panel access verified
- [ ] Email notifications working
- [ ] Escalation paths tested
- [ ] Brand colors match website

---

**Version**: 1.0  
**Last Updated**: January 2026  
**Developed for**: Gilaf Foods & Spices eCommerce Platform
