# Google Tag Manager - Admin Guide

## Overview
This feature allows admin users to easily manage Google Analytics, Google Ads, and other tracking tags without touching code.

## Features

### 1. Easy Script Management
- Paste complete Google Tag (gtag.js) scripts
- Enable/disable tags with one click
- No coding required

### 2. Page-Specific Targeting
- **All Pages**: Load tag on every page
- **Homepage**: Only on the main page
- **Product Pages**: All product detail pages
- **Cart Page**: Shopping cart page
- **Checkout Page**: Checkout process
- **Thank You Page**: Order confirmation page
- **Custom URLs**: Specify exact URLs or partial matches

### 3. Security & Performance
- Scripts are sanitized for security
- Tags load asynchronously (non-blocking)
- Automatic duplicate prevention
- Cache-friendly implementation

## Setup Instructions

### Step 1: Run Migration
1. Visit: `https://gilafstore.com/run_google_tag_migration.php`
2. Click to execute the database setup
3. Delete the migration file after completion

### Step 2: Access Google Tag Manager
1. Login to Admin Panel
2. Go to **System & Settings** → **Google Tag Manager**
3. Configure your tracking settings

### Step 3: Add Your Google Tag
1. Get your Google Tag from Google Analytics/Ads
2. Copy the complete script (including `<script>` tags)
3. Paste in the textarea
4. Select where the tag should appear
5. Click **Save Settings**

### Step 4: Test Your Tag
1. Click **Test Tag** button in admin panel
2. Or visit: `https://gilafstore.com/admin/google_tag_test.php`
3. Check browser console (F12) for tag activity
4. Verify Google Analytics/Ads is receiving data

## Example Google Tag Scripts

### Google Analytics 4
```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'GA_MEASUREMENT_ID');
</script>
```

### Google Ads Conversion Tracking
```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-CONVERSION_ID"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'AW-CONVERSION_ID');
</script>
```

### Multiple Tags Combined
```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  
  // Google Analytics
  gtag('config', 'GA_MEASUREMENT_ID');
  
  // Google Ads
  gtag('config', 'AW-CONVERSION_ID');
</script>
```

## Page Targeting Options

| Option | Where it Loads | URL Pattern |
|--------|----------------|-------------|
| All Pages | Every page | `*` |
| Homepage | Only main page | `/` or `/index.php` |
| Product Pages | Product details | `/product/*` |
| Cart Page | Shopping cart | `/cart*` |
| Checkout Page | Checkout process | `/checkout*` |
| Thank You Page | Order confirmation | `/thank*` or `/order_success*` |
| Custom URLs | User defined | Any URL containing the specified text |

## Custom URL Examples

| Custom URL | Matches |
|------------|---------|
| `/blog` | `/blog`, `/blog/post-title`, `/blog/category` |
| `/offers` | `/offers`, `/offers/diwali-sale` |
| `/contact` | `/contact`, `/contact/thank-you` |

## Testing & Debugging

### Check if Tag is Loading
1. Open browser DevTools (F12)
2. Go to Console tab
3. Look for Google Tag messages
4. Check Network tab for `googletagmanager.com` requests

### Test Page
Visit: `https://gilafstore.com/admin/google_tag_test.php`
- Shows current tag status
- Tests tag functionality
- Displays debug information

### Common Issues

**Tag not appearing:**
- Check if tag is enabled in admin
- Verify page targeting matches current URL
- Clear browser cache

**Duplicate tags:**
- System prevents duplicate injection
- Only one tag loads per page
- Check for manually added tags in code

**Script errors:**
- Verify script syntax is correct
- Ensure all quotes are properly escaped
- Check for missing semicolons

## Security Notes

- All scripts are sanitized before storage
- Tags only load in `<head>` section
- No execution in admin panel
- Safe for non-technical users

## Performance Impact

- Tags load asynchronously
- Non-blocking page rendering
- Minimal performance overhead
- Cache-friendly implementation

## Support

For issues or questions:
1. Check the test page for diagnostics
2. Review browser console for errors
3. Verify Google Analytics/Ads setup
4. Contact support if needed

---

**Version:** 1.0  
**Last Updated:** May 3, 2026
