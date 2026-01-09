# Invoice System - Installation & Setup Guide

## 📋 Overview
This automated invoice system generates professional PDF invoices for all orders with complete database integration, security, and compliance features.

---

## 🚀 Installation Steps

### Step 1: Install TCPDF Library
The system uses TCPDF for PDF generation. Install it via Composer:

```bash
cd "c:\xampp\htdocs\Gilaf Ecommerce website"
composer require tecnickcom/tcpdf
```

If you don't have Composer installed, download it from: https://getcomposer.org/download/

**Alternative: Manual Installation**
1. Download TCPDF from: https://github.com/tecnickcom/TCPDF/releases
2. Extract to `vendor/tecnickcom/tcpdf/` directory
3. Ensure the path matches the require statement in `generate_invoice_pdf.php`

---

### Step 2: Create Database Tables
Run the SQL schema to create invoice tables:

```bash
# Open phpMyAdmin or MySQL command line
# Navigate to your database: ecommerce_db
# Run the file: database_invoices_schema.sql
```

Or via command line:
```bash
mysql -u root -p ecommerce_db < database_invoices_schema.sql
```

This creates:
- `invoices` table - Stores invoice records
- `invoice_audit_log` table - Tracks all invoice actions for compliance
- Adds `invoice_id` column to `orders` table

---

### Step 3: Configure Company Details
Edit `includes/invoice_functions.php` and update the `get_company_details()` function:

```php
function get_company_details() {
    return [
        'name' => 'Your Company Name',
        'logo' => base_url('assets/images/logo.png'),
        'address' => 'Your Company Address',
        'city' => 'Your City',
        'state' => 'Your State',
        'pincode' => '000000',
        'country' => 'India',
        'phone' => '+91 XXXXXXXXXX',
        'email' => 'support@yourcompany.com',
        'website' => 'www.yourcompany.com',
        'gstin' => 'XXXXXXXXXXXX', // Your GST Number
        'pan' => 'XXXXXXXXXX' // Your PAN Number
    ];
}
```

---

### Step 4: Verify File Permissions
Ensure the following directories are writable:

```bash
chmod 755 includes/
chmod 755 vendor/
chmod 644 includes/invoice_functions.php
chmod 644 generate_invoice_pdf.php
```

---

## ✅ Features Implemented

### 1. Automatic Invoice Generation
- ✅ Invoices auto-generated on order confirmation
- ✅ Unique invoice numbers (Format: INV-YYYYMMDD-XXXX)
- ✅ Prevents duplicate invoices for same order

### 2. PDF Download
- ✅ Professional PDF format with company branding
- ✅ Download button on order details page
- ✅ Opens in new tab for easy printing
- ✅ Proper formatting for A4 paper

### 3. Auto-Filled Data
- ✅ Company details from configuration
- ✅ Customer details from user account
- ✅ Order items with SKU, quantity, prices
- ✅ Billing address from order
- ✅ Payment method and status
- ✅ Tax calculations (GST if applicable)

### 4. Mandatory Fields Included
- ✅ Company name, logo, contact details
- ✅ Customer name, email, billing address
- ✅ Invoice number (unique)
- ✅ Order ID
- ✅ Invoice issue date
- ✅ Payment status (Paid/Pending/Failed)
- ✅ Itemized product list with:
  - Item name
  - SKU
  - Quantity
  - Unit price
  - Line total
- ✅ Subtotal
- ✅ Taxes (GST)
- ✅ Discounts
- ✅ Shipping charges
- ✅ Grand total
- ✅ Payment method
- ✅ Terms and conditions

### 5. Database Integration
- ✅ Stores invoice records in `invoices` table
- ✅ Links to orders via foreign key
- ✅ Tracks payment status
- ✅ Maintains audit trail

### 6. Security & Compliance
- ✅ User authentication required
- ✅ Access control (users can only access their own invoices)
- ✅ Admin override for all invoices
- ✅ Audit log tracks all downloads
- ✅ Prevents invoice tampering (regenerated from database)
- ✅ IP address and user agent logging

### 7. Scalability
- ✅ Modular code structure
- ✅ Reusable functions
- ✅ Easy to extend (multi-currency, localization)
- ✅ Handles high order volumes
- ✅ Efficient database queries with indexes

---

## 📁 File Structure

```
Gilaf Ecommerce website/
├── includes/
│   └── invoice_functions.php          # Core invoice logic
├── generate_invoice_pdf.php           # PDF generation endpoint
├── database_invoices_schema.sql       # Database schema
├── user/
│   └── order_details.php              # Updated with download button
├── confirm_upi_payment.php            # Auto-generates invoice
└── process_payment.php                # Auto-generates invoice
```

---

## 🧪 Testing

### Test Invoice Generation
1. Place a test order
2. Complete payment (UPI or Credit Card)
3. Navigate to "My Orders"
4. Click on the order
5. Click "Download Invoice" button
6. Verify PDF downloads with all correct information

### Test Security
1. Try accessing invoice URL without login → Should redirect to login
2. Try accessing another user's invoice → Should show "Access denied"
3. Check `invoice_audit_log` table → Should log all downloads

### Test Data Accuracy
1. Verify company details are correct
2. Verify customer details match user account
3. Verify order items, quantities, and prices
4. Verify tax calculations
5. Verify total amount matches order

---

## 🔧 Customization

### Change Invoice Number Format
Edit `generate_invoice_number()` in `includes/invoice_functions.php`:

```php
// Current format: INV-20260104-0001
// Change to: GS-2026-0001
return sprintf('GS-%s-%04d', date('Y'), $sequence);
```

### Add Multi-Currency Support
1. Add `currency` column to `invoices` table
2. Update `format_invoice_currency()` function
3. Pass currency code to PDF generator

### Customize PDF Template
Edit the HTML in `generate_invoice_pdf.php`:
- Change colors in `<style>` section
- Modify layout structure
- Add/remove sections
- Change fonts

### Add Email Delivery
Integrate with your email system:

```php
// After creating invoice
$invoice = create_invoice($orderId);
if ($invoice) {
    send_invoice_email($invoice['id'], $user['email']);
}
```

---

## 📊 Database Schema

### invoices Table
- `id` - Primary key
- `invoice_number` - Unique invoice number
- `order_id` - Foreign key to orders
- `user_id` - Foreign key to users
- `invoice_date` - Date of invoice generation
- `subtotal` - Order subtotal
- `tax_amount` - GST/tax amount
- `discount_amount` - Discount applied
- `shipping_amount` - Shipping charges
- `total_amount` - Grand total
- `payment_status` - paid/pending/failed/refunded
- `payment_method` - Payment method used
- `notes` - Additional notes
- `created_at` - Timestamp
- `updated_at` - Timestamp

### invoice_audit_log Table
- `id` - Primary key
- `invoice_id` - Foreign key to invoices
- `action` - Action performed (created/downloaded/viewed)
- `performed_by` - User ID who performed action
- `ip_address` - IP address
- `user_agent` - Browser user agent
- `created_at` - Timestamp

---

## 🐛 Troubleshooting

### PDF Not Generating
- Check if TCPDF is installed: `vendor/tecnickcom/tcpdf/`
- Verify PHP memory limit: `memory_limit = 256M` in php.ini
- Check error logs: `c:\xampp\apache\logs\error.log`

### Invoice Not Created
- Verify database tables exist
- Check order payment status
- Review `invoice_functions.php` error logs

### Download Button Not Showing
- Ensure invoice was created for the order
- Check `$invoice` variable in `order_details.php`
- Verify user is logged in

### Access Denied Error
- Verify user ID matches invoice user_id
- Check session is active
- Review `user_can_access_invoice()` function

---

## 📞 Support

For issues or questions:
1. Check error logs: `c:\xampp\apache\logs\error.log`
2. Review `invoice_audit_log` table for tracking
3. Enable PHP error display for debugging:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

---

## ✨ Future Enhancements

Potential features to add:
- Email invoice to customer automatically
- Bulk invoice download (multiple orders)
- Invoice templates (different designs)
- Multi-language support
- Multi-currency support
- Invoice editing/correction
- Credit notes for refunds
- Recurring invoices for subscriptions
- Export to accounting software (Tally, QuickBooks)

---

## 📝 Compliance Notes

This system maintains:
- Complete audit trail of all invoice actions
- Immutable invoice records (regenerated from database)
- Secure access control
- Data retention for legal requirements
- GST compliance (if applicable)

**Important:** Consult with your accountant or legal advisor to ensure compliance with local tax laws and regulations.

---

## ✅ System Ready!

Your automated invoice system is now fully functional and ready for production use. All orders will automatically generate invoices that users can download as professional PDFs.
