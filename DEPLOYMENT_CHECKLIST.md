# Sales Pricing Enhancement - Deployment Checklist

**Version:** 2.0
**Date:** March 20, 2026

---

## 📦 Files to Upload via FileZilla

### 1. Database Migration
- [ ] `migration_sales_pricing_enhancement.sql`

### 2. Core System Files
- [ ] `includes/sales_pricing_helper.php`
- [ ] `admin/sales_pricing_enhanced.php`
- [ ] `sales-portal/api_products_enhanced.php`

### 3. Testing & Verification
- [ ] `test_pricing_system.php`
- [ ] `deploy_pricing_enhancement.php`

### 4. Documentation
- [ ] `SALES_PRICING_IMPLEMENTATION_GUIDE.md`
- [ ] `DEPLOYMENT_CHECKLIST.md` (this file)

---

## 🚀 Deployment Steps

### Step 1: Backup Current System ⚠️
```
1. Login to cPanel
2. Go to phpMyAdmin
3. Select gilaf_ecommerce database
4. Click Export
5. Download backup file
6. Save with name: backup_before_pricing_enhancement_YYYY-MM-DD.sql
```

### Step 2: Upload Files via FileZilla
```
1. Connect to Hostinger via FileZilla
2. Navigate to public_html/
3. Upload all files listed above to their respective folders:
   - Root files → public_html/
   - includes/ files → public_html/includes/
   - admin/ files → public_html/admin/
   - sales-portal/ files → public_html/sales-portal/
```

### Step 3: Run Database Migration
```
1. Login to cPanel → phpMyAdmin
2. Select gilaf_ecommerce database
3. Click Import tab
4. Choose file: migration_sales_pricing_enhancement.sql
5. Click Go
6. Verify success message
7. Delete migration file from server (security)
```

### Step 4: Verify Deployment
```
1. Open browser
2. Navigate to: https://yourdomain.com/deploy_pricing_enhancement.php
3. Check all sections show ✓ (green checkmarks)
4. If any errors, fix and re-verify
5. Delete deploy_pricing_enhancement.php after verification
```

### Step 5: Set Up Pricing Data
```
1. Login to admin panel
2. Navigate to: Admin → Sales Pricing (Enhanced)
3. For each tab (Wholesale, Distributor, Franchise, Retail):
   a. Set base prices
   b. Set GST percentages
   c. Set Offline MRP
   d. Click Save
4. Verify prices saved correctly
```

### Step 6: Test in Sales Portal
```
1. Login to sales portal
2. Create test order with:
   - Distributor party → verify distributor pricing used
   - Wholesaler party → verify wholesale pricing used
   - Retailer party → verify retail pricing used
3. Check GST calculations
4. Verify totals are accurate
5. Confirm offline MRP displays correctly
```

### Step 7: Final Cleanup
```
1. Delete test_pricing_system.php from server
2. Delete deploy_pricing_enhancement.php from server
3. Keep SALES_PRICING_IMPLEMENTATION_GUIDE.md for reference
```

---

## ✅ Verification Checklist

After deployment, verify:

### Database
- [ ] All new columns exist in product_weights table
- [ ] No errors in migration
- [ ] Indexes created successfully

### Admin Panel
- [ ] Sales Pricing (Enhanced) page loads
- [ ] 4 tabs visible: Wholesale, Distributor, Franchise, Retail
- [ ] GST column appears in all tabs
- [ ] Offline MRP field is editable
- [ ] Can save prices without errors
- [ ] Prices persist after save
- [ ] Updating one tier doesn't erase others

### Sales Portal
- [ ] Orders use correct party-based pricing
- [ ] Distributor parties get distributor prices
- [ ] Wholesaler parties get wholesale prices
- [ ] Retailer parties get retail prices
- [ ] Franchise parties get franchise prices
- [ ] GST calculates correctly
- [ ] Order totals are accurate
- [ ] Offline MRP displays (not website price)

### Data Integrity
- [ ] Website pricing unaffected
- [ ] No data loss when updating prices
- [ ] Decimal formatting shows .00
- [ ] All calculations accurate to 2 decimals

---

## 🐛 Troubleshooting

### Issue: Migration fails with "column already exists"
**Solution:** Columns already added. Skip migration or use `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`

### Issue: "Function not found" errors
**Solution:** Ensure `includes/sales_pricing_helper.php` is uploaded and included in files

### Issue: Prices showing 0.00
**Solution:** Set pricing data via admin/sales_pricing_enhanced.php

### Issue: Wrong prices in orders
**Solution:** Verify party profile_type matches pricing tier (distributor/wholesaler/retailer/franchise)

### Issue: GST not calculating
**Solution:** Set GST percentages in admin pricing page (default is 0%)

---

## 📞 Quick Reference

### Party Type → Price Column Mapping
```
distributor → distributor_price + distributor_gst
wholesaler  → wholesale_price + wholesale_gst
retailer    → retail_price + retail_gst
franchise   → franchise_price + franchise_gst
```

### Key Functions
```php
// Get price for single product
get_party_price($weightId, $partyType, $includeGst)

// Get prices for multiple products
get_party_prices_bulk($weightIds, $partyType, $includeGst)

// Calculate order total
calculate_order_total($items, $partyType, $includeGst)
```

### Important URLs
- Admin Pricing: `/admin/sales_pricing_enhanced.php`
- API Endpoint: `/sales-portal/api_products_enhanced.php`
- Deployment Check: `/deploy_pricing_enhancement.php` (delete after use)
- Test Suite: `/test_pricing_system.php` (delete after use)

---

## 🎯 Success Criteria

Deployment is successful when:

✅ All database columns exist
✅ All files uploaded and accessible
✅ Admin pricing page shows 4 tabs with GST columns
✅ Offline MRP is editable
✅ Prices save independently (no data loss)
✅ Sales portal orders use correct party pricing
✅ GST calculates accurately
✅ Decimal formatting is consistent (.00)
✅ Website pricing remains separate and unaffected

---

## 📝 Post-Deployment Notes

**Date Deployed:** _________________

**Deployed By:** _________________

**Issues Encountered:** 
_________________________________________________
_________________________________________________

**Resolution:** 
_________________________________________________
_________________________________________________

**Verification Status:** 
- [ ] All tests passed
- [ ] Pricing data configured
- [ ] Sales portal tested
- [ ] Production ready

**Signature:** _________________

---

**End of Deployment Checklist**
