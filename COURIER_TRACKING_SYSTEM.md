# 📦 COURIER TRACKING SYSTEM - COMPLETE IMPLEMENTATION

## 🎯 OVERVIEW

A comprehensive courier tracking system that allows:
- Multiple courier company support (India Post, DTDC, Blue Dart, etc.)
- Auto-redirect to courier's official tracking page
- Tracking number auto-filled on courier website
- Customer-facing tracking page
- Admin management of courier companies
- Email notifications with tracking info

---

## 🗄️ DATABASE SETUP

### **Step 1: Run SQL File**

Execute the SQL file to create tables and insert default couriers:

```bash
mysql -u root -p your_database < database_courier_tracking.sql
```

**Or manually run in phpMyAdmin:**
- Open `database_courier_tracking.sql`
- Copy and execute all SQL statements

### **Tables Created:**

1. **`courier_companies`** - Stores courier company information
2. **`shipment_tracking_history`** - Tracks shipment status updates
3. **Modified `orders` table** - Added tracking fields

---

## 📋 COURIER COMPANIES (PRE-LOADED)

| Courier | Code | Tracking URL Pattern |
|---------|------|---------------------|
| **India Post** | india_post | `https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?ConsignmentNo={TN}` |
| **DTDC** | dtdc | `https://www.dtdc.in/tracking/tracking_results.asp?cnno={TN}` |
| **Blue Dart** | bluedart | `https://www.bluedart.com/tracking?trackfor=0&tracknum={TN}` |
| **Delhivery** | delhivery | `https://www.delhivery.com/track/package/{TN}` |
| **FedEx** | fedex | `https://www.fedex.com/fedextrack/?trknbr={TN}` |
| **DHL** | dhl | `https://www.dhl.com/in-en/home/tracking/tracking-express.html?submit=1&tracking-id={TN}` |
| **Ecom Express** | ecom_express | `https://ecomexpress.in/tracking/?awb_field={TN}` |
| **Aramex** | aramex | `https://www.aramex.com/track/results?mode=0&ShipmentNumber={TN}` |

**Note:** `{TN}` is automatically replaced with the actual tracking number

---

## 🔧 HOW IT WORKS

### **1. Admin Adds Tracking Info to Order**

**Location:** Order Management Page

**Steps:**
1. Admin opens order details
2. Selects courier company from dropdown
3. Enters tracking/consignment number
4. Clicks "Update Tracking"
5. System:
   - Saves courier ID and tracking number
   - Updates order status to "Shipped"
   - Records shipped_at timestamp
   - Sends email notification to customer

### **2. Customer Tracks Shipment**

**Method A: From Email Link**
- Customer receives email with tracking link
- Clicks "TRACK YOUR SHIPMENT" button
- Opens tracking page with details

**Method B: Manual Entry**
- Customer visits: `yoursite.com/track-shipment.php`
- Enters tracking number
- Clicks "TRACK" button

### **3. Auto-Redirect to Courier Website**

**What Happens:**
1. System displays order details
2. Shows courier name and tracking number
3. Provides "TRACK ON [COURIER NAME]" button
4. When clicked:
   - Opens courier's official website in new tab
   - Tracking number is **auto-filled** in URL
   - Customer sees real-time tracking info

**Example for India Post:**
```
Tracking Number: EE123456789IN
Generated URL: https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?ConsignmentNo=EE123456789IN
```

---

## 🎨 ADMIN INTERFACE

### **Manage Courier Companies**

**URL:** `admin/manage_couriers.php`

**Features:**
- ✅ View all courier companies
- ✅ Add new courier company
- ✅ Edit courier details
- ✅ Delete courier company
- ✅ Activate/Deactivate couriers
- ✅ Set display order
- ✅ Cinematic glassmorphism UI

**Add Courier Form:**
- Courier Name (e.g., "India Post")
- Code (e.g., "india_post")
- Tracking URL Pattern (use `{TN}` placeholder)
- Display Order (for dropdown sorting)

---

## 🌐 CUSTOMER TRACKING PAGE

**URL:** `track-shipment.php`

**Features:**
- ✅ Beautiful glassmorphism UI
- ✅ Search by tracking number
- ✅ Display order details:
  - Order ID
  - Tracking Number
  - Order Status
  - Order Date
  - Shipped Date
  - Delivered Date
  - Total Amount
- ✅ Courier information
- ✅ Direct link to courier tracking
- ✅ Auto-filled tracking number

**Status Badges:**
- 🟡 Pending
- 🔵 Processing
- 🟢 Shipped
- ✅ Delivered
- 🔴 Cancelled

---

## 📧 EMAIL NOTIFICATIONS

**Automatic Email Sent When:**
- Admin updates tracking information
- Order status changes to "Shipped"

**Email Contains:**
- Order number
- Tracking number (large, bold, monospace)
- Courier company name
- Clickable "TRACK YOUR SHIPMENT" button
- Direct tracking link

**Email Template:**
- Professional HTML design
- Gradient header
- Tracking box with border
- Call-to-action button
- Responsive design

---

## 🔗 URL PATTERN EXAMPLES

### **India Post**
```
Pattern: https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?ConsignmentNo={TN}
Example: https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?ConsignmentNo=EE123456789IN
```

### **DTDC**
```
Pattern: https://www.dtdc.in/tracking/tracking_results.asp?cnno={TN}
Example: https://www.dtdc.in/tracking/tracking_results.asp?cnno=D12345678
```

### **Blue Dart**
```
Pattern: https://www.bluedart.com/tracking?trackfor=0&tracknum={TN}
Example: https://www.bluedart.com/tracking?trackfor=0&tracknum=12345678901
```

---

## 🛠️ HELPER FUNCTIONS

**File:** `includes/courier_functions.php`

### **Available Functions:**

```php
// Get all active couriers
get_active_couriers()

// Get courier by ID
get_courier_by_id($courier_id)

// Generate tracking URL
generate_tracking_url($courier_id, $tracking_number)

// Update order tracking
update_order_tracking($order_id, $courier_id, $tracking_number)

// Get order tracking details
get_order_tracking($order_id)

// Add tracking history entry
add_tracking_history($order_id, $status, $location, $description)

// Get tracking history
get_tracking_history($order_id)

// Mark order as delivered
mark_order_delivered($order_id)

// Validate tracking number
validate_tracking_number($tracking_number)

// Send tracking notification email
send_tracking_notification($order_id)
```

---

## 📊 INTEGRATION STEPS

### **Step 1: Database Setup**
```bash
mysql -u root -p your_database < database_courier_tracking.sql
```

### **Step 2: Add to Admin Navigation**
Edit `includes/admin_header.php`:
```php
<li class="nav-item">
    <a class="nav-link" href="manage_couriers.php">
        <i class="fas fa-shipping-fast"></i> Couriers
    </a>
</li>
```

### **Step 3: Update Order Management**
Add tracking section to order details page:
```php
<?php require_once 'includes/courier_functions.php'; ?>

<!-- Tracking Section -->
<div class="tracking-section">
    <h4>Shipping Information</h4>
    <form method="POST" action="update_tracking.php">
        <input type="hidden" name="order_id" value="<?= $order['id']; ?>">
        
        <select name="courier_id" required>
            <option value="">Select Courier</option>
            <?php foreach (get_active_couriers() as $courier): ?>
                <option value="<?= $courier['id']; ?>"><?= $courier['name']; ?></option>
            <?php endforeach; ?>
        </select>
        
        <input type="text" name="tracking_number" placeholder="Enter Tracking Number" required>
        
        <button type="submit">Update Tracking</button>
    </form>
</div>
```

### **Step 4: Add to Main Navigation**
Edit `includes/header.php`:
```php
<li class="nav-item">
    <a class="nav-link" href="track-shipment.php">
        <i class="fas fa-search-location"></i> Track Order
    </a>
</li>
```

---

## 🧪 TESTING

### **Test 1: Add Courier Company**
1. Go to `admin/manage_couriers.php`
2. Click "ADD COURIER"
3. Enter:
   - Name: Test Courier
   - Code: test_courier
   - URL: `https://example.com/track?id={TN}`
   - Order: 10
4. Submit
5. Verify: Courier appears in list

### **Test 2: Update Order Tracking**
1. Go to order management
2. Select order
3. Choose "India Post" from dropdown
4. Enter tracking: `EE123456789IN`
5. Submit
6. Verify:
   - Order status = "Shipped"
   - Tracking number saved
   - Email sent to customer

### **Test 3: Customer Tracking**
1. Go to `track-shipment.php`
2. Enter tracking: `EE123456789IN`
3. Click "TRACK"
4. Verify:
   - Order details displayed
   - Courier name shown
   - "TRACK ON INDIA POST" button visible
5. Click button
6. Verify: Opens India Post website with tracking number

### **Test 4: URL Generation**
```php
$url = generate_tracking_url(1, 'EE123456789IN');
// Should return: https://www.indiapost.gov.in/_layouts/15/dop.portal.tracking/trackconsignment.aspx?ConsignmentNo=EE123456789IN
```

---

## ✅ FEATURES IMPLEMENTED

✅ Multiple courier company support
✅ Admin courier management interface
✅ Tracking number validation
✅ Auto-redirect to courier website
✅ Tracking number auto-filled in URL
✅ Customer tracking page
✅ Order tracking history
✅ Email notifications
✅ Cinematic glassmorphism UI
✅ Responsive design
✅ Status badges
✅ Shipped/Delivered timestamps
✅ Helper functions library
✅ 8 pre-loaded courier companies

---

## 🎯 USER FLOW

### **Admin Workflow:**
```
1. Receive order
2. Pack and prepare shipment
3. Book courier (India Post, DTDC, etc.)
4. Get tracking number from courier
5. Open order in admin panel
6. Select courier from dropdown
7. Enter tracking number
8. Click "Update Tracking"
9. System sends email to customer
```

### **Customer Workflow:**
```
1. Receive email notification
2. Click "TRACK YOUR SHIPMENT"
3. View order details on site
4. Click "TRACK ON [COURIER]"
5. Redirected to courier website
6. See real-time tracking info
```

---

## 🔐 SECURITY

- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Admin authentication required
- ✅ Input validation
- ✅ Tracking number format validation

---

## 🚀 FUTURE ENHANCEMENTS

- [ ] Webhook integration for auto-updates
- [ ] SMS notifications
- [ ] Multiple tracking numbers per order
- [ ] Estimated delivery date
- [ ] Tracking map visualization
- [ ] Push notifications
- [ ] Bulk tracking upload (CSV)
- [ ] API integration with courier services

---

## 📞 SUPPORT

**For Issues:**
1. Check database tables exist
2. Verify courier URL patterns
3. Test tracking number format
4. Check email configuration
5. Review error logs

**Common Issues:**
- **Tracking not found:** Check tracking number format
- **Email not sent:** Verify SMTP settings
- **Courier link broken:** Check URL pattern has `{TN}`
- **Page not loading:** Run database SQL file

---

**System is production-ready and fully functional!** 🎉
