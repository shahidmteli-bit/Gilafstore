# Promo Code Custom Messages - Complete Guide

## Overview
The promo code system now supports fully customizable promotional messages with emoji support, displayed dynamically in the website header. Admins can create professional, eye-catching promo messages that are automatically shown to eligible users.

---

## 🎨 Features

### **Custom Message Editor**
- ✅ Rich text input with emoji support
- ✅ Dynamic placeholders: `{CODE}` and `{DISCOUNT}`
- ✅ Quick template buttons for common styles
- ✅ 500 character limit
- ✅ Real-time preview in header

### **Dynamic Placeholders**
- **{CODE}** - Automatically replaced with the promo code (e.g., WELCOME40)
- **{DISCOUNT}** - Automatically replaced with discount value (e.g., 40% OFF or ₹100 OFF)

### **Quick Templates**
Pre-designed message templates with emojis:
1. **🎉 New User** - For new customer acquisition
2. **🛒 First Order** - For first-time buyers
3. **💚 Welcome Back** - For returning customers
4. **⏰ Limited Time** - For time-sensitive campaigns

---

## 📝 How to Create Custom Messages

### **Step 1: Access Admin Panel**
1. Go to **Admin Panel → Catalog → Promo Codes**
2. Click **"Create Promo Code"** or edit existing code

### **Step 2: Fill Basic Details**
- Enter promo code (e.g., WELCOME40)
- Set discount type and value
- Configure eligibility and validity

### **Step 3: Create Custom Message**
Find the **"Header Display Message"** field and either:

**Option A: Use Quick Templates**
- Click one of the template buttons:
  - 🎉 New User
  - 🛒 First Order
  - 💚 Welcome Back
  - ⏰ Limited Time
- Template will auto-fill the message field
- Customize as needed

**Option B: Write Custom Message**
- Type your message directly
- Use `{CODE}` where you want the promo code to appear
- Use `{DISCOUNT}` where you want the discount value
- Add emojis for visual appeal

### **Step 4: Enable Header Display**
- Check **"Display in Website Header"** checkbox
- Save the promo code

---

## 🎯 Message Templates & Examples

### **Template 1: New User Offer**
```
🎉 New User Offer! Use code {CODE} & get up to {DISCOUNT} OFF
```
**Result:** 🎉 New User Offer! Use code WELCOME40 & get up to 40% OFF

**Best For:** New customer acquisition campaigns

---

### **Template 2: First Order Special**
```
🛒 First Order Special — Use code {CODE} & save {DISCOUNT}
```
**Result:** 🛒 First Order Special — Use code FIRST30 & save 30% OFF

**Best For:** Converting first-time visitors to buyers

---

### **Template 3: Welcome Back**
```
💚 Welcome Back! Use code {CODE} & save {DISCOUNT}
```
**Result:** 💚 Welcome Back! Use code RETURN15 & save 15% OFF

**Best For:** Re-engaging inactive customers

---

### **Template 4: Limited Time Offer**
```
⏰ Today Only — Use code {CODE} & get {DISCOUNT} OFF
```
**Result:** ⏰ Today Only — Use code FESTIVE20 & get 20% OFF

**Best For:** Flash sales and urgency campaigns

---

## 🌟 Advanced Custom Messages

### **Seasonal Campaigns**
```
🎄 Holiday Special! Use {CODE} for {DISCOUNT} on all products
```

```
🌸 Spring Sale — Get {DISCOUNT} with code {CODE}
```

```
🎃 Spooky Savings! Code {CODE} gives you {DISCOUNT}
```

### **Category-Specific**
```
🍯 Honey Lovers! Use {CODE} & save {DISCOUNT} on premium honey
```

```
🌿 Organic Spices — {DISCOUNT} OFF with code {CODE}
```

### **Urgency & Scarcity**
```
⚡ Flash Sale! {DISCOUNT} OFF — Code: {CODE} (Limited Time)
```

```
🔥 Hot Deal! Use {CODE} for {DISCOUNT} — Hurry, ends soon!
```

### **Value Proposition**
```
💎 Premium Quality — {DISCOUNT} OFF with code {CODE}
```

```
🎁 Free Gift + {DISCOUNT} OFF — Use code {CODE}
```

---

## 🎨 Emoji Guide

### **Recommended Emojis by Category**

**Celebration & Excitement:**
- 🎉 Party Popper
- 🎊 Confetti Ball
- ✨ Sparkles
- 🌟 Glowing Star
- 💫 Dizzy

**Shopping & Commerce:**
- 🛒 Shopping Cart
- 🛍️ Shopping Bags
- 💳 Credit Card
- 🎁 Gift
- 📦 Package

**Time & Urgency:**
- ⏰ Alarm Clock
- ⏳ Hourglass
- 🔥 Fire
- ⚡ Lightning
- 💨 Dash

**Love & Care:**
- 💚 Green Heart
- ❤️ Red Heart
- 💙 Blue Heart
- 💛 Yellow Heart
- 🤝 Handshake

**Quality & Premium:**
- 💎 Gem
- 👑 Crown
- ⭐ Star
- 🏆 Trophy
- 🥇 Gold Medal

**Nature & Organic:**
- 🌿 Herb
- 🍃 Leaf
- 🌱 Seedling
- 🌾 Sheaf of Rice
- 🍯 Honey Pot

---

## 📊 Best Practices

### **Message Length**
- **Ideal:** 40-60 characters
- **Maximum:** 500 characters
- **Mobile-friendly:** Keep under 50 characters for best mobile display

### **Clarity**
- ✅ Clear call-to-action
- ✅ Mention the code explicitly
- ✅ State the discount value
- ✅ Use simple, direct language

### **Visual Appeal**
- ✅ Use 1-2 emojis maximum
- ✅ Place emoji at the start or end
- ✅ Avoid emoji overload
- ✅ Test on different devices

### **Urgency**
- ✅ Add time-based language for limited offers
- ✅ Use action words (Hurry, Today Only, Limited Time)
- ✅ Create FOMO (Fear of Missing Out)

### **Personalization**
- ✅ Match message to user eligibility
- ✅ Use appropriate tone for segment
- ✅ Reference user status (New, Returning, etc.)

---

## 🔄 Message Display Logic

### **Automatic Filtering**
The system automatically shows messages only to eligible users:

1. **User Profile Check** - Identifies user by email/phone/ID
2. **Eligibility Validation** - Checks if user matches promo requirements
3. **Active Status** - Only shows active, valid promos
4. **Auto-Rotation** - Cycles through multiple eligible promos every 4 seconds

### **Display Rules**
Messages are shown when:
- ✅ Promo code is active
- ✅ Within validity period
- ✅ User meets eligibility criteria
- ✅ "Display in Header" is enabled
- ✅ Usage limit not exceeded

Messages are hidden when:
- ❌ User not eligible
- ❌ Promo expired or not yet active
- ❌ Display in header disabled
- ❌ Usage limit reached

---

## 💡 Creative Examples

### **For New Users (No Account)**
```
🎉 Welcome! Get {DISCOUNT} on your first order — Code: {CODE}
```

### **For First-Time Buyers (Has Account, 0 Orders)**
```
🛒 Ready to shop? Use {CODE} & save {DISCOUNT} on your first order!
```

### **For Second-Time Buyers**
```
💙 Thanks for coming back! Enjoy {DISCOUNT} with code {CODE}
```

### **For Repeat Customers (4+ Orders)**
```
👑 VIP Exclusive — {DISCOUNT} OFF with code {CODE} — Thank you for your loyalty!
```

### **For Inactive Users (30+ Days)**
```
💚 We missed you! Come back & save {DISCOUNT} with code {CODE}
```

### **For Flash Sales**
```
⚡ FLASH SALE — {DISCOUNT} OFF for the next 24 hours! Code: {CODE}
```

### **For Seasonal Events**
```
🎊 Festival Special — Celebrate with {DISCOUNT} OFF — Use code {CODE}
```

### **For Product Launches**
```
🌟 NEW ARRIVAL — Try it now & get {DISCOUNT} with code {CODE}
```

---

## 🎯 Campaign-Specific Messages

### **Customer Acquisition**
```
🎁 First-time here? Get {DISCOUNT} OFF — Use code {CODE}
```

### **Customer Retention**
```
💚 Welcome back! We've got {DISCOUNT} OFF waiting for you — Code: {CODE}
```

### **Cart Abandonment Recovery**
```
🛒 Still thinking? Get {DISCOUNT} OFF to complete your order — Code: {CODE}
```

### **Referral Programs**
```
🤝 Referred by a friend? Enjoy {DISCOUNT} with code {CODE}
```

### **Loyalty Rewards**
```
🏆 Loyal Customer Reward — {DISCOUNT} OFF with code {CODE}
```

---

## 📱 Mobile Optimization

### **Short & Sweet Messages**
For mobile users, keep messages concise:

```
🎉 {DISCOUNT} OFF — Code: {CODE}
```

```
💚 Use {CODE} & save {DISCOUNT}
```

```
⏰ {CODE} = {DISCOUNT} OFF
```

---

## ✅ Testing Your Messages

### **Before Publishing:**
1. **Preview** - Check how message looks in header
2. **Test Placeholders** - Verify {CODE} and {DISCOUNT} replace correctly
3. **Check Emojis** - Ensure emojis display properly
4. **Mobile Test** - View on mobile device
5. **User Eligibility** - Confirm only eligible users see it

### **After Publishing:**
1. Visit website as eligible user
2. Check header display
3. Verify auto-rotation (if multiple codes)
4. Test on different browsers
5. Monitor analytics for performance

---

## 🎨 Style Guidelines

### **DO:**
- ✅ Use clear, action-oriented language
- ✅ Highlight the discount value
- ✅ Make the code easy to find
- ✅ Use 1-2 relevant emojis
- ✅ Keep it concise and scannable

### **DON'T:**
- ❌ Use all caps (except for code)
- ❌ Overuse emojis (looks spammy)
- ❌ Make messages too long
- ❌ Use unclear abbreviations
- ❌ Forget to include {CODE} or {DISCOUNT}

---

## 📈 Performance Tips

### **A/B Testing Messages**
Create multiple promo codes with different messages to test:
- Message tone (urgent vs. friendly)
- Emoji placement
- Discount emphasis
- Call-to-action variations

### **Seasonal Updates**
Update messages regularly for:
- Holidays and festivals
- Seasonal changes
- Special events
- Product launches

### **Personalization**
Match message style to user segment:
- **New Users:** Welcoming, informative
- **Returning:** Appreciative, exclusive
- **VIP:** Premium, special treatment
- **Inactive:** Win-back, incentive-focused

---

## 🔧 Technical Details

### **Character Encoding**
- UTF-8 encoding supports all emojis
- No special configuration needed
- Works across all modern browsers

### **Placeholder Replacement**
- `{CODE}` → Actual promo code (e.g., WELCOME40)
- `{DISCOUNT}` → Formatted discount (e.g., 40% OFF or ₹100 OFF)
- Case-sensitive placeholders
- Automatic formatting

### **Default Fallback**
If no custom message is set:
```
🎁 Use code {CODE} & get {DISCOUNT}
```

---

## 📞 Support

### **Common Issues**

**Issue:** Message not showing in header
- **Check:** "Display in Header" is enabled
- **Check:** Promo code is active and valid
- **Check:** User meets eligibility requirements

**Issue:** Placeholders not replacing
- **Check:** Used correct syntax: {CODE} and {DISCOUNT}
- **Check:** Curly braces are present
- **Check:** No extra spaces

**Issue:** Emojis not displaying
- **Check:** Browser supports UTF-8
- **Check:** Copied emoji correctly
- **Check:** Database charset is utf8mb4

---

**Last Updated:** January 7, 2026
**Feature Version:** 2.1
**Status:** Production Ready ✅
