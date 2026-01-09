# 🚚 Complete Shipping Management System Documentation

## Overview

A comprehensive, scalable shipping management module for international eCommerce with support for:
- Multiple shipping zones (Local, National, Regional, International, Remote)
- Various shipping methods (Standard, Express, Overnight, Local Pickup)
- Weight-based pricing with configurable slabs
- Free shipping rules
- Cash on Delivery (COD) with zone restrictions
- Order tracking and status management
- Admin-friendly configuration

---

## 📊 Database Schema

### 10 Core Tables

#### 1. `shipping_zones`
Defines geographical shipping zones
- **Fields**: zone_name, zone_type, description, is_active, display_order
- **Zone Types**: local, national, regional, international, remote

#### 2. `shipping_zone_locations`
Maps countries/regions to zones
- **Fields**: zone_id, country_code, country_name, state_province, postal_code_pattern
- **Purpose**: Determine which zone a customer belongs to

#### 3. `shipping_methods`
Available shipping methods
- **Fields**: method_name, method_code, method_type, description, is_active
- **Types**: standard, express, local_pickup, overnight, economy

#### 4. `shipping_weight_slabs`
Weight ranges for pricing
- **Fields**: slab_name, min_weight, max_weight, weight_unit
- **Example**: 0-500g, 500g-1kg, 1-2kg, 2-5kg, 5-10kg, 10kg+

#### 5. `shipping_rates`
Pricing matrix (Zone × Method × Weight)
- **Fields**: zone_id, method_id, weight_slab_id, base_cost, per_kg_cost, delivery_days
- **Purpose**: Store actual shipping costs

#### 6. `shipping_free_rules`
Free shipping eligibility rules
- **Fields**: rule_name, zone_id, min_order_value, exclude_international
- **Example**: "Free shipping on orders above ₹500"

#### 7. `shipping_cod_settings`
Cash on Delivery configuration
- **Fields**: zone_id, is_enabled, cod_charge, cod_charge_type, max_cod_amount
- **Charge Types**: fixed or percentage

#### 8. `order_shipping_details`
Shipping info for each order
- **Fields**: order_id, zone_id, method_id, shipping_cost, tracking_number, shipping_status
- **Statuses**: pending, processing, shipped, in_transit, out_for_delivery, delivered

#### 9. `shipping_status_history`
Audit trail for status changes
- **Fields**: order_shipping_id, old_status, new_status, location, notes
- **Purpose**: Track shipping progress

#### 10. `shipping_global_settings`
System-wide shipping configuration
- **Fields**: setting_key, setting_value, setting_type
- **Examples**: shipping_enabled, default_weight_unit, show_delivery_estimate

---

## 🔧 Installation

### Step 1: Run Database Migration

```sql
-- In phpMyAdmin:
1. Select 'ecommerce_db' database
2. Go to SQL tab
3. Run: shipping_system_schema.sql
4. ✅ All tables created with default data
```

### Step 2: Include Shipping Functions

Add to your `functions.php`:
```php
require_once __DIR__ . '/shipping_functions.php';
```

### Step 3: Update Products Table

Ensure products have weight field:
```sql
ALTER TABLE products ADD COLUMN weight DECIMAL(10,2) DEFAULT 0.00 AFTER stock;
```

---

## 💡 Core Functions

### 1. Calculate Shipping Cost

```php
$result = calculateShippingCost(
    $cartTotal,      // Total cart value
    $cartWeight,     // Total weight in kg
    $countryCode,    // 'IN', 'US', 'GB', etc.
    $postalCode,     // Optional
    $methodId        // Optional, null returns all methods
);

// Returns:
[
    'success' => true,
    'shipping_cost' => 150.00,
    'method_name' => 'Express Shipping',
    'delivery_estimate' => '2-3 days',
    'zone_id' => 1,
    'is_free' => false
]
```

### 2. Get Available Methods

```php
$methods = getAvailableShippingMethods($zoneId);
// Returns array of shipping methods for the zone
```

### 3. Check Free Shipping

```php
$freeShipping = checkFreeShippingEligibility($cartTotal, $zoneId);
// Returns: ['eligible' => true/false, 'rule_name' => '...']
```

### 4. Check COD Availability

```php
$cod = isCODAvailable($zoneId, $orderTotal);
// Returns: ['available' => true/false, 'cod_charge' => 50.00]
```

### 5. Calculate Cart Weight

```php
$totalWeight = calculateCartWeight($cartItems);
// Auto-sums weight from products table
```

### 6. Save Shipping to Order

```php
saveOrderShippingDetails($orderId, [
    'zone_id' => 1,
    'method_id' => 2,
    'shipping_cost' => 150.00,
    'weight' => 2.5,
    'address' => '123 Main St',
    'country' => 'India',
    'postal_code' => '400001',
    'max_delivery_days' => 3
]);
```

### 7. Update Shipping Status

```php
updateShippingStatus(
    $orderShippingId, 
    'shipped', 
    'TRK123456789',  // Tracking number
    'Dispatched via DTDC'  // Notes
);
```

---

## 🎯 Usage Examples

### Example 1: Checkout Page

```php
// Get customer location
$countryCode = $_POST['country'] ?? 'IN';
$postalCode = $_POST['postal_code'] ?? null;

// Calculate cart totals
$cartTotal = 1500.00;
$cartWeight = calculateCartWeight($_SESSION['cart']);

// Get shipping options
$shipping = calculateShippingCost($cartTotal, $cartWeight, $countryCode, $postalCode);

if ($shipping['success']) {
    foreach ($shipping['methods'] as $method) {
        echo "<div class='shipping-option'>";
        echo "<input type='radio' name='shipping_method' value='{$method['id']}'>";
        echo "<label>{$method['method_name']} - ₹{$method['shipping_cost']}</label>";
        echo "<span>{$method['delivery_estimate']}</span>";
        echo "</div>";
    }
}
```

### Example 2: Product Page Delivery Estimate

```php
$productWeight = 0.5; // kg
$customerCountry = 'IN';

$shipping = calculateShippingCost(0, $productWeight, $customerCountry, null, 1);

if ($shipping['success']) {
    echo "Estimated Delivery: {$shipping['delivery_estimate']}";
}
```

### Example 3: Admin - Update Order Status

```php
// When order is shipped
$orderShippingId = 123;
$trackingNumber = 'DTDC1234567890';

updateShippingStatus($orderShippingId, 'shipped', $trackingNumber, 'Dispatched from warehouse');

// When delivered
updateShippingStatus($orderShippingId, 'delivered', null, 'Delivered to customer');
```

---

## 🛠️ Admin Configuration

### Managing Shipping Zones

```sql
-- Add new zone
INSERT INTO shipping_zones (zone_name, zone_type, description, is_active) 
VALUES ('Middle East', 'regional', 'Gulf countries', 1);

-- Add countries to zone
INSERT INTO shipping_zone_locations (zone_id, country_code, country_name) 
VALUES 
(7, 'AE', 'United Arab Emirates'),
(7, 'SA', 'Saudi Arabia'),
(7, 'QA', 'Qatar');
```

### Setting Shipping Rates

```sql
-- Add rate for Zone 7, Express Method, 0-500g slab
INSERT INTO shipping_rates (zone_id, method_id, weight_slab_id, base_cost, min_delivery_days, max_delivery_days)
VALUES (7, 2, 1, 500.00, 3, 5);
```

### Creating Free Shipping Rules

```sql
-- Free shipping for orders above ₹1000 (national only)
INSERT INTO shipping_free_rules (rule_name, zone_id, min_order_value, exclude_international)
VALUES ('Free Shipping ₹1000+', 2, 1000.00, 1);
```

### Configuring COD

```sql
-- Enable COD for national zone with ₹75 charge
INSERT INTO shipping_cod_settings (zone_id, is_enabled, cod_charge, cod_charge_type, exclude_international)
VALUES (2, 1, 75.00, 'fixed', 1);
```

---

## 📱 Customer Experience Flow

### 1. Product Page
- Show estimated delivery time
- Display shipping cost estimate
- "Check delivery to your pincode" feature

### 2. Cart Page
- Calculate total weight
- Show preliminary shipping estimate
- "Free shipping on orders above ₹X" message

### 3. Checkout Page
- Auto-detect country from address
- Display available shipping methods with costs
- Show delivery time estimates
- COD option if available
- Final shipping cost before payment

### 4. Order Confirmation
- Show selected shipping method
- Display estimated delivery date
- Provide order tracking link

### 5. Order Tracking
- Real-time status updates
- Tracking number display
- Delivery progress timeline

---

## 🔐 Best Practices

### 1. Weight Management
- Always set product weights in admin
- Use consistent weight units (kg recommended)
- Include packaging weight in calculations

### 2. Zone Configuration
- Start with broad zones, refine later
- Use postal code patterns for precise targeting
- Test zone detection with sample addresses

### 3. Pricing Strategy
- Set competitive base costs
- Use per-kg costs for heavy items
- Offer free shipping strategically

### 4. International Shipping
- Research customs and duties
- Set realistic delivery times
- Consider courier partnerships
- Restrict COD for international orders

### 5. Performance
- Cache shipping calculations
- Index frequently queried columns
- Optimize weight slab queries

### 6. Security
- Validate all shipping inputs
- Sanitize tracking numbers
- Restrict admin access to shipping settings

---

## 🚀 Future Enhancements

### Phase 2 Features
- [ ] Real-time courier API integration (DTDC, Blue Dart, FedEx)
- [ ] Automatic tracking number updates
- [ ] SMS/Email notifications for status changes
- [ ] Multi-package shipments
- [ ] Shipping label generation
- [ ] Return shipping management
- [ ] Shipping insurance options

### Phase 3 Features
- [ ] AI-powered delivery time prediction
- [ ] Dynamic pricing based on demand
- [ ] Carbon footprint calculator
- [ ] Preferred delivery time slots
- [ ] Warehouse management integration

---

## 📊 Shipping Status Workflow

```
pending → processing → shipped → in_transit → out_for_delivery → delivered
                                     ↓
                                  failed
                                     ↓
                                 returned
```

### Status Descriptions

- **pending**: Order placed, awaiting processing
- **processing**: Order being prepared for shipment
- **shipped**: Package picked up by courier
- **in_transit**: Package in transit to destination
- **out_for_delivery**: Package out for final delivery
- **delivered**: Successfully delivered to customer
- **failed**: Delivery attempt failed
- **returned**: Package returned to sender

---

## 🧪 Testing Checklist

- [ ] Test shipping calculation for all zones
- [ ] Verify weight-based pricing accuracy
- [ ] Test free shipping rules
- [ ] Validate COD availability logic
- [ ] Test international shipping restrictions
- [ ] Verify delivery time estimates
- [ ] Test order status updates
- [ ] Check tracking number storage
- [ ] Validate postal code patterns
- [ ] Test edge cases (0 weight, very heavy items)

---

## 📞 Support & Maintenance

### Common Issues

**Issue**: Shipping cost showing as 0
- **Solution**: Check if weight slabs cover the cart weight range

**Issue**: No shipping methods available
- **Solution**: Verify zone has active rates configured

**Issue**: Free shipping not applying
- **Solution**: Check rule conditions and zone restrictions

**Issue**: COD not showing
- **Solution**: Verify COD settings for customer's zone

### Database Maintenance

```sql
-- Clean up old shipping history (older than 1 year)
DELETE FROM shipping_status_history 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Optimize shipping tables
OPTIMIZE TABLE shipping_rates, order_shipping_details;
```

---

## 📈 Analytics Queries

### Most Popular Shipping Methods
```sql
SELECT sm.method_name, COUNT(*) as orders
FROM order_shipping_details osd
JOIN shipping_methods sm ON osd.method_id = sm.id
GROUP BY sm.id
ORDER BY orders DESC;
```

### Average Shipping Cost by Zone
```sql
SELECT sz.zone_name, AVG(osd.shipping_cost) as avg_cost
FROM order_shipping_details osd
JOIN shipping_zones sz ON osd.zone_id = sz.id
GROUP BY sz.id;
```

### Delivery Performance
```sql
SELECT 
    shipping_status,
    COUNT(*) as total,
    AVG(DATEDIFF(actual_delivery_date, created_at)) as avg_days
FROM order_shipping_details
GROUP BY shipping_status;
```

---

## ✅ System Ready!

Your complete shipping management system is now configured and ready to use. Start by:

1. ✅ Running the database migration
2. ✅ Configuring your shipping zones
3. ✅ Setting up shipping rates
4. ✅ Testing with sample orders
5. ✅ Integrating into checkout flow

For questions or customization needs, refer to the code comments in `shipping_functions.php`.
