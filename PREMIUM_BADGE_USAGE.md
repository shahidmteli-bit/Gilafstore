# Premium Badge Usage Guide

## Overview
The premium badge styling has been added to `assets/css/product-layout-enhanced.css`. There are two ways to display "PREMIUM" on product cards:

---

## Method 1: Premium Badge (Top-Left Corner)

Add this inside the `badge-container` div:

```php
<div class="badge-container">
    <div class="badge premium-badge">PREMIUM</div>
    <!-- Other badges -->
</div>
```

**Example with multiple badges:**
```php
<div class="badge-container">
    <div class="badge premium-badge">PREMIUM</div>
    <div class="badge fresh-badge"><i class="fas fa-leaf"></i> Fresh</div>
    <?php if (!empty($product['has_discount'])): ?>
        <div class="badge discount-badge">
            <i class="fas fa-tag"></i> <?= round($product['discount_percentage']); ?>% OFF
        </div>
    <?php endif; ?>
</div>
```

---

## Method 2: Premium Label (Above Product Title)

Add this inside the `product-details` section, before the category:

```php
<div class="product-details">
    <span class="premium-label">PREMIUM</span>
    <span class="product-cat"><?= htmlspecialchars($product['category_name']); ?></span>
    <h3 class="product-title"><?= htmlspecialchars($product['name']); ?></h3>
    <!-- Rest of product details -->
</div>
```

---

## Full Product Card Example with Premium

```php
<article class="product-card">
    <!-- Premium Badge (Top-Left) -->
    <div class="badge-container">
        <div class="badge premium-badge">PREMIUM</div>
        <div class="badge gold">Organic</div>
    </div>
    
    <!-- Product Image -->
    <div class="product-image-wrapper">
        <img src="<?= asset_url('images/products/' . $product['image']); ?>" 
             alt="<?= htmlspecialchars($product['name']); ?>">
        <div class="trust-overlay">
            <i class="fas fa-award" style="color: var(--color-green);"></i>
            <i class="fas fa-flask" style="color: var(--color-green);"></i>
        </div>
    </div>
    
    <!-- Product Details -->
    <div class="product-details">
        <!-- Premium Label (Above Category) -->
        <span class="premium-label">PREMIUM</span>
        
        <span class="product-cat">Spices</span>
        <h3 class="product-title">Mogra Saffron (1g)</h3>
        <span class="product-origin">Origin: Pampore</span>
        
        <div class="price-row">
            <span class="product-price">₹650</span>
        </div>
        
        <button class="add-btn">Add to Cart</button>
    </div>
</article>
```

---

## Conditional Display Based on Product Data

If you have a `is_premium` field in your database:

```php
<?php if (!empty($product['is_premium'])): ?>
    <span class="premium-label">PREMIUM</span>
<?php endif; ?>
```

Or in the badge container:

```php
<div class="badge-container">
    <?php if (!empty($product['is_premium'])): ?>
        <div class="badge premium-badge">PREMIUM</div>
    <?php endif; ?>
    <!-- Other badges -->
</div>
```

---

## Styling Details

### Premium Badge (Top-Left)
- **Color:** Gold (#C5A059)
- **Background:** Transparent
- **Font Size:** 0.7rem
- **Letter Spacing:** 2px
- **Position:** Inside badge-container (top-left)

### Premium Label (Above Category)
- **Color:** Gold (#C5A059)
- **Font Size:** 0.65rem
- **Letter Spacing:** 2px
- **Position:** Above product category in product-details

---

## Choose Your Style

- **Use Badge** if you want PREMIUM to appear with other badges (Fresh, Discount, etc.)
- **Use Label** if you want PREMIUM to appear as part of the product information flow
- **Use Both** for maximum premium emphasis (as shown in reference images)

---

## Database Integration (Optional)

To automatically show premium badges, add a column to your products table:

```sql
ALTER TABLE products ADD COLUMN is_premium TINYINT(1) DEFAULT 0;
```

Then update premium products:

```sql
UPDATE products SET is_premium = 1 WHERE id IN (1, 5, 8, 12);
```

Then in your PHP code:

```php
<?php if ($product['is_premium']): ?>
    <div class="badge premium-badge">PREMIUM</div>
<?php endif; ?>
```
