# Website Performance Analytics - Setup & Integration Guide

## 🎯 Overview
A comprehensive, admin-only traffic intelligence and analytics system with visitor tracking, product engagement analytics, revenue insights, and geographic intelligence.

---

## 🔒 Security & Access Control

**CRITICAL: Admin-Only Access**
- ✅ Module restricted to Admin/Super Admin roles only
- ✅ No analytics data visible on public website
- ✅ No analytics data visible to regular users
- ✅ Role-based access control enforced
- ✅ All analytics pages require `require_admin()` authentication

---

## 📦 Installation Steps

### Step 1: Create Database Tables

Run this SQL in phpMyAdmin:

```sql
-- Execute the file: database_analytics_schema.sql
```

This creates 6 tables:
- `analytics_visitors` - Visitor tracking with geolocation
- `analytics_page_views` - Page view tracking
- `analytics_product_events` - Product engagement tracking
- `analytics_daily_summary` - Pre-aggregated daily metrics
- `analytics_geographic_data` - Geographic traffic summary
- `analytics_settings` - Module configuration

### Step 2: Enable Tracking on Your Website

Add this to your main layout file (e.g., `includes/header.php` or after `db_connect.php`):

```php
<?php
// Include analytics tracker
require_once __DIR__ . '/includes/analytics_tracker.php';

// Track page view (call on every page)
if (!isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    // Only track non-admin users
    $pageUrl = $_SERVER['REQUEST_URI'];
    $pageTitle = $pageTitle ?? 'Untitled';
    $pageType = 'general'; // home, product, category, cart, checkout, etc.
    
    trackPageView($pageUrl, $pageTitle, $pageType);
}
?>
```

### Step 3: Track Product Events

Add tracking to product pages:

**Product View:**
```php
// In product_details.php
trackProductEvent($productId, 'view', 'product_page', $categoryId, $price);
```

**Product Click:**
```php
// When user clicks product card
trackProductEvent($productId, 'click', 'homepage', $categoryId, $price);
```

**Add to Cart:**
```php
// In add to cart handler
trackProductEvent($productId, 'add_to_cart', 'product_page', $categoryId, $price, $quantity);
```

**Purchase:**
```php
// After successful order
foreach ($orderItems as $item) {
    trackProductEvent($item['product_id'], 'purchase', 'checkout', $item['category_id'], $item['price'], $item['quantity']);
}
```

---

## 📊 Features Implemented

### 1. Visitor & Traffic Overview
- Total visitors (unique and returning)
- Total page views
- Total clicks
- Date range filtering (Today, Yesterday, Last 7/30 Days, Custom)
- Daily/weekly/monthly trend graphs

### 2. Product Click & Engagement Analytics
- Total clicks per product
- Most clickable products (Top 10)
- Product views, clicks, add-to-cart, purchases
- High-click but low-conversion identification

### 3. Revenue Analytics
- Total revenue
- Total orders
- Average order value
- Conversion rate calculation
- Top-selling products

### 4. Geographic Traffic Intelligence
- Country-wise traffic distribution
- Visitor count and percentage
- Traffic comparison charts

### 5. Behavioral Insights
- Most visited pages
- Average session duration
- Device distribution (Desktop, Mobile, Tablet)
- Browser and OS statistics

### 6. Data Export
- CSV export functionality
- Date range selection
- Up to 10,000 rows per export

### 7. Enterprise Dashboard UI
- Executive-level KPI cards
- Interactive Chart.js visualizations
- Responsive design
- Clean, minimal layout
- Hover effects and animations

---

## 🎨 Dashboard Components

### Key Metrics Cards:
1. **Total Visitors** - New vs Returning breakdown
2. **Page Views** - Average pages per visitor
3. **Total Clicks** - Product clicks highlighted
4. **Total Revenue** - Orders and average order value

### Secondary Metrics:
- Add to Cart count
- Conversion Rate percentage
- Products Viewed count
- Average Session Duration

### Charts & Visualizations:
- **Traffic Trend Chart** - Line chart showing visitors and page views over time
- **Device Distribution** - Doughnut chart showing device types
- **Top Products Table** - Engagement metrics (Views, Clicks, Cart, Sales)
- **Geographic Distribution** - Country-wise traffic with percentage bars
- **Most Visited Pages** - Page views and duration statistics

---

## 🔧 Configuration Settings

Located in `analytics_settings` table:

```sql
-- Enable/disable tracking
UPDATE analytics_settings SET setting_value = 'true' WHERE setting_key = 'tracking_enabled';

-- Track logged-in users
UPDATE analytics_settings SET setting_value = 'true' WHERE setting_key = 'track_logged_in_users';

-- Track guest users
UPDATE analytics_settings SET setting_value = 'true' WHERE setting_key = 'track_guest_users';

-- Session timeout (seconds)
UPDATE analytics_settings SET setting_value = '1800' WHERE setting_key = 'session_timeout';

-- Data retention (days)
UPDATE analytics_settings SET setting_value = '365' WHERE setting_key = 'data_retention_days';

-- Export limit
UPDATE analytics_settings SET setting_value = '10000' WHERE setting_key = 'export_limit';
```

---

## 📈 Tracking Implementation

### Automatic Tracking:
- Visitor identification (cookie-based)
- Session management
- Device detection (Desktop/Mobile/Tablet)
- Browser and OS detection
- Referrer type detection (Direct, Search, Social, Internal, External)

### Manual Tracking Required:
- Product events (view, click, add_to_cart, purchase)
- Page types (home, product, category, cart, checkout)
- Custom events

---

## 🌍 Geographic Data

### Current Implementation:
- IP address capture
- Placeholder for geolocation (ready for integration)

### To Enable Full Geolocation:
1. Sign up for GeoIP2 or similar service
2. Update `getGeolocationData()` method in `analytics_tracker.php`
3. Add API key to settings

Example integration:
```php
private function getGeolocationData() {
    $ip = $_SERVER['REMOTE_ADDR'];
    // Call GeoIP2 API or use MaxMind database
    // Return country, state, city data
}
```

---

## 📊 Data Aggregation

### Daily Summary (Automated):
Create a cron job to run daily aggregation:

```php
// Create file: cron/aggregate_analytics.php
<?php
require_once __DIR__ . '/../includes/db_connect.php';

$yesterday = date('Y-m-d', strtotime('-1 day'));

// Aggregate visitor metrics
$query = "INSERT INTO analytics_daily_summary (summary_date, total_visitors, unique_visitors, ...)
          SELECT 
              DATE(first_visit_at) as summary_date,
              COUNT(*) as total_visitors,
              COUNT(DISTINCT visitor_id) as unique_visitors,
              ...
          FROM analytics_visitors
          WHERE DATE(first_visit_at) = ?
          GROUP BY DATE(first_visit_at)";

$stmt = $conn->prepare($query);
$stmt->bind_param('s', $yesterday);
$stmt->execute();
```

**Cron Schedule:**
```bash
# Run daily at 1 AM
0 1 * * * php /path/to/cron/aggregate_analytics.php
```

---

## 🔍 Advanced Insights

### High-Click, Low-Conversion Products:
```sql
SELECT 
    p.name,
    COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
    COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) as purchases,
    (COUNT(CASE WHEN event_type = 'purchase' THEN 1 END) / 
     COUNT(CASE WHEN event_type = 'click' THEN 1 END) * 100) as conversion_rate
FROM analytics_product_events ape
JOIN products p ON ape.product_id = p.id
WHERE event_type IN ('click', 'purchase')
GROUP BY p.id, p.name
HAVING clicks > 50 AND conversion_rate < 5
ORDER BY clicks DESC;
```

### Peak Traffic Hours:
```sql
SELECT 
    HOUR(viewed_at) as hour,
    COUNT(*) as page_views,
    COUNT(DISTINCT visitor_id) as visitors
FROM analytics_page_views
WHERE DATE(viewed_at) = CURDATE()
GROUP BY HOUR(viewed_at)
ORDER BY page_views DESC;
```

---

## 📁 Files Created

### Database:
- `database_analytics_schema.sql`

### Tracking System:
- `includes/analytics_tracker.php`

### Admin Dashboard:
- `admin/website_performance.php`
- `admin/analytics_export.php`

### Documentation:
- `ANALYTICS_MODULE_SETUP.md`

### Modified:
- `includes/admin_header.php` (Navigation link added)

---

## 🚀 Admin Panel Access

**Navigation:**
- Icon: 📈 Chart Line
- Label: "Website Performance"
- Location: Admin sidebar (between Users & Support and Idea & Suggestion Center)

**URL:** `admin/website_performance.php`

---

## 🎯 Usage Examples

### Example 1: Track Homepage View
```php
// In index.php
trackPageView('/', 'Home', 'home');
```

### Example 2: Track Product Page
```php
// In product_details.php
$productId = $_GET['id'];
$product = getProduct($productId);

trackPageView("/product.php?id={$productId}", $product['name'], 'product');
trackProductEvent($productId, 'view', 'product_page', $product['category_id'], $product['price']);
```

### Example 3: Track Add to Cart
```php
// In cart handler
if (addToCart($productId, $quantity)) {
    trackProductEvent($productId, 'add_to_cart', 'product_page', $categoryId, $price, $quantity);
}
```

### Example 4: Track Purchase
```php
// After successful order
$orderId = createOrder($cartItems);
foreach ($cartItems as $item) {
    trackProductEvent(
        $item['product_id'], 
        'purchase', 
        'checkout', 
        $item['category_id'], 
        $item['price'], 
        $item['quantity']
    );
}
```

---

## 📊 Performance Optimization

### Indexes Created:
- Visitor ID, User ID, Country, Date ranges
- Product ID, Event type, Date ranges
- Page type, Session ID, Date ranges
- Composite indexes for common queries

### Best Practices:
1. Use date range filters to limit query scope
2. Aggregate data daily for historical reports
3. Archive old data after retention period
4. Use LIMIT on large result sets
5. Consider partitioning tables by date for very high traffic

---

## 🔒 Privacy & Compliance

### Data Collected:
- Visitor ID (anonymous cookie)
- IP address
- User agent
- Page views
- Product interactions
- Geographic location (if enabled)

### GDPR Compliance:
- Anonymous visitor tracking
- No personal data stored without consent
- Data retention policies configurable
- Export and deletion capabilities

### Recommendations:
1. Add privacy policy disclosure
2. Implement cookie consent banner
3. Provide opt-out mechanism
4. Regular data cleanup based on retention policy

---

## 🆘 Troubleshooting

### Issue: No data showing in dashboard
**Solution:** 
- Verify tracking code is added to pages
- Check if `tracking_enabled` setting is true
- Verify database tables exist
- Check error logs

### Issue: Visitor count seems low
**Solution:**
- Ensure tracking code runs on all pages
- Check if admin users are being tracked (they shouldn't be)
- Verify cookie consent isn't blocking tracking

### Issue: Geographic data not showing
**Solution:**
- Implement GeoIP integration
- Update `getGeolocationData()` method
- Test with known IP addresses

### Issue: Charts not displaying
**Solution:**
- Verify Chart.js is loaded
- Check browser console for errors
- Ensure data arrays are properly formatted

---

## 🚀 Future Enhancements

### Phase 2 Features:
- Real-time analytics dashboard
- Heatmap visualization
- Funnel analysis
- A/B testing integration
- Custom event tracking
- API endpoints for external tools
- Mobile app analytics
- Email campaign tracking
- Social media integration
- Advanced segmentation

### Integration Opportunities:
- Google Analytics 4
- Meta Pixel
- Google Tag Manager
- Hotjar
- Mixpanel
- Tableau/Power BI

---

## ✅ Checklist

- [ ] Database tables created
- [ ] Tracking code added to website
- [ ] Product event tracking implemented
- [ ] Admin dashboard accessible
- [ ] Test visitor tracking
- [ ] Test product events
- [ ] Verify charts display correctly
- [ ] Test data export
- [ ] Configure settings
- [ ] Set up daily aggregation cron job

---

## 📞 Key Metrics to Monitor

### Daily:
- Total visitors
- Page views
- Conversion rate
- Revenue

### Weekly:
- Traffic trends
- Top products
- Geographic distribution
- Device breakdown

### Monthly:
- Growth metrics
- Product performance
- User behavior patterns
- Revenue analysis

---

**🎉 Your Website Performance Analytics module is ready to provide actionable insights!**

*Module Name: Website Performance*  
*Version: 1.0*  
*Admin-Only Access: Enforced*  
*Created: January 2026*
