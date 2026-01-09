# Promo Code & Discount Management System

## Overview
A comprehensive promotional code system has been integrated into your eCommerce website, allowing you to create, manage, and track discount codes that customers can apply at checkout.

## Features Implemented

### 1. **Admin Management Panel**
- **URL**: `http://localhost/Gilaf%20Ecommerce%20website/admin/manage_promo_codes.php`
- **Features**:
  - Create promo codes with professional code generator
  - Edit existing promo codes
  - Delete promo codes
  - View usage statistics
  - Track total uses, unique users, and discount amounts
  - Real-time status indicators (Active, Scheduled, Expired, Disabled)

### 2. **Promo Code Types**
- **Percentage Discount**: e.g., 20% off
- **Fixed Amount Discount**: e.g., ₹100 off

### 3. **Advanced Features**
- **Minimum Order Value**: Set minimum cart value required
- **Maximum Discount Cap**: Limit maximum discount for percentage codes
- **Usage Limits**: Control total number of times code can be used
- **Validity Period**: Set start and end dates/times
- **Active/Inactive Toggle**: Enable or disable codes instantly

### 4. **Customer Experience**
- **Cart Page Integration**: Customers can apply promo codes in cart
- **Real-time Validation**: Instant feedback on code validity
- **Visual Feedback**: Beautiful gradient UI showing applied codes
- **Easy Removal**: One-click promo code removal
- **Automatic Recalculation**: Discount updates when cart changes

## Database Tables

### `promo_codes`
Stores all promo code information:
- `id`: Primary key
- `code`: Unique promo code (uppercase)
- `description`: Optional description
- `discount_type`: 'percentage' or 'fixed'
- `discount_value`: Discount amount/percentage
- `min_order_value`: Minimum cart value required
- `max_discount`: Maximum discount cap (for percentage)
- `usage_limit`: Total usage limit (NULL = unlimited)
- `used_count`: Current usage count
- `valid_from`: Start date/time
- `valid_until`: End date/time
- `is_active`: Active status (1/0)
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp

### `promo_code_usage`
Tracks every promo code usage:
- `id`: Primary key
- `promo_code_id`: Foreign key to promo_codes
- `user_id`: User who used the code
- `order_id`: Associated order
- `discount_amount`: Discount applied
- `used_at`: Usage timestamp

## How to Use

### For Admins:

1. **Create a Promo Code**:
   - Go to Admin Panel → Catalog → Promo Codes
   - Click "Create Promo Code"
   - Fill in details:
     - Code (or use Generate button)
     - Description
     - Discount type and value
     - Minimum order value (optional)
     - Maximum discount (optional)
     - Usage limit (optional)
     - Valid from/until dates
   - Click "Create Promo Code"

2. **Monitor Usage**:
   - View statistics dashboard showing:
     - Total codes
     - Active codes
     - Scheduled codes
     - Expired codes
   - Click statistics icon to see detailed usage per code

3. **Edit/Delete Codes**:
   - Use edit button to modify existing codes
   - Use delete button to remove codes

### For Customers:

1. **Apply Promo Code**:
   - Add items to cart
   - Go to cart page
   - Click "Have a Promo Code?" button
   - Enter promo code
   - Click "Apply"
   - Discount will be applied instantly

2. **Remove Promo Code**:
   - Click "Remove" button on applied promo code
   - Discount will be removed

## Validation Rules

The system automatically validates:
- ✅ Code exists and is active
- ✅ Current date is within validity period
- ✅ Cart total meets minimum order value
- ✅ Usage limit not exceeded
- ✅ Discount doesn't exceed maximum cap

## Example Promo Codes

### Percentage Discount
- **Code**: SUMMER20
- **Type**: Percentage
- **Value**: 20%
- **Min Order**: ₹500
- **Max Discount**: ₹200

### Fixed Discount
- **Code**: FLAT100
- **Type**: Fixed
- **Value**: ₹100
- **Min Order**: ₹300
- **Max Discount**: N/A

## Files Created/Modified

### New Files:
1. `/admin/manage_promo_codes.php` - Admin management interface
2. `/admin/promo_code_actions.php` - Backend CRUD operations
3. `/includes/promo_functions.php` - Validation and application logic
4. `/apply_promo.php` - AJAX endpoint for applying/removing codes

### Modified Files:
1. `/cart.php` - Added promo code UI and integration
2. `/includes/admin_header.php` - Added navigation link

## Statistics Dashboard

The admin panel shows:
- **Total Codes**: All promo codes created
- **Active Codes**: Currently valid and active
- **Scheduled**: Future-dated codes
- **Expired**: Past validity date

Per-code statistics:
- Total uses
- Unique users
- Total discount given

## Security Features

- ✅ Admin authentication required
- ✅ Input validation and sanitization
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Uppercase code enforcement
- ✅ Pattern validation (alphanumeric only)

## Integration with Checkout

The promo code discount is:
- Stored in session
- Applied to cart total
- Displayed in order summary
- Recorded in order history
- Tracked in usage statistics

## Future Enhancements (Optional)

Consider adding:
- User-specific promo codes
- Product/category-specific codes
- First-time customer codes
- Referral codes
- Bulk code generation
- Email marketing integration
- Auto-apply codes from URL parameters

## Support

For any issues or questions about the promo code system, refer to:
- Admin panel for code management
- Database tables for data structure
- Function files for validation logic
- Cart page for customer experience

---

**System Status**: ✅ Fully Operational
**Last Updated**: January 7, 2026
**Version**: 1.0
