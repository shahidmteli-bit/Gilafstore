# SECURITY CLEANUP REPORT
## Phase 1 - Safe Performance & Architecture Stabilization
## Date: May 7, 2026

---

## CRITICAL FINDINGS

### 1. HARDCODED PRODUCTION DATABASE CREDENTIALS [CRITICAL]

**File:** `includes/db_connect.php` (lines 89-95)
**Issue:** Production Hostinger database credentials are hardcoded in plaintext:
- DB_HOST: localhost
- DB_NAME: u237768108_gilafstore
- DB_USER: u237768108_gilafstore
- DB_PASS: 1Gfs@#$222

**Risk:** Any developer, Git leak, or file exposure reveals full production database access.
**Current Mitigation:** Environment detection via `$isLocal` flag (CLI or localhost check).
**Recommendation:** Migrate to `.env` file architecture (see Migration Plan below).

### 2. ERROR MESSAGE EXPOSURE [MEDIUM]

**File:** `includes/db_connect.php` (line 107)
```php
die('Database connection failed: ' . $exception->getMessage());
```
**Risk:** PDO exception messages can leak server paths, database names, and connection details to end users.
**Recommendation:** Log error internally, show generic message to users.

### 3. STACK TRACE EXPOSURE [MEDIUM]

**File:** `includes/db_connect.php` (line 114)
```php
die('MySQLi connection failed: ' . $conn->connect_error);
```
**Risk:** Same as above - exposes connection error details publicly.

---

## PUBLICLY ACCESSIBLE DANGEROUS FILES

### SQL Schema Files (32 files in web root) [HIGH]
These reveal full database structure, table names, column names, and migration history:
- database.sql
- database_analytics_schema.sql
- database_batch_lifecycle_safe.sql
- database_company_profile.sql
- database_courier_tracking.sql
- database_discounts_schema.sql
- database_ean_batch_migration.sql
- database_faq_system.sql
- database_google_tag_manager.sql
- database_gst_schema.sql
- database_invoices_schema.sql
- database_search_enhancement.sql
- database_seo_migration.sql
- database_shipping_hybrid.sql
- database_suggestions_schema.sql
- database_updates.sql
- database_ai_content_migration.sql
- add_discounts_for_products_8_and_7.sql
- add_discounts_to_actual_products.sql
- add_ean_to_weights.sql
- add_original_price_column.sql
- add_payment_verification_columns.sql
- add_price_column.sql
- add_transaction_id_column.sql
- add_usage_limit_per_user.sql
- check_and_add_price.sql
- create_invoices_table.sql
- fix_orders_table_for_upi.sql
- migration_add_weight_images.sql
- migration_product_weights.sql
- migration_sales_pricing_enhancement.sql
- shipping_system_schema.sql

### Debug Scripts (10 files) [HIGH]
Expose internal application state, database queries, and system diagnostics:
- debug_cart_navigation.php
- debug_check.php
- debug_clicks.php
- debug_data_v2.php
- debug_data_v3.php
- debug_data_v4.php
- debug_discounts.php
- debug_order_17.php
- debug_schema_check.php
- debug_signup_analysis.php (empty)

### Test Scripts (9 files) [MEDIUM]
Expose application internals and may allow data manipulation:
- test_cart_link.html
- test_click_tracking.php
- test_console_tracking.html
- test_db_connection.php (exposes DB connectivity details)
- test_direct_insert.php (may allow data insertion)
- test_homepage_click.html
- test_new_click_tracking.php
- test_php_execution.php
- test_pricing_system.php

### Fix/Check Scripts (10 files) [MEDIUM]
Can modify data or expose internal state:
- fix_and_test_clicks.php
- fix_asif_outstanding.php
- check_analytics_data.php
- check_asif_orders.php
- check_asif_party.php
- check_asif_status.php
- check_click_data.php
- check_database_clicks.php
- clear_test_data.php (can delete data)

### Backup Files (14 files) [MEDIUM]
Old PHP source code accessible via direct URL:
- about-us.php.backup_2026-01-20
- apply-distributor.php.backup_2026-01-20
- faqs.php.backup_2026-01-20
- index.php.backup_2026-01-20
- index.php.backup_explore_2026-01-20
- index.php.backup_padding2_2026-01-20
- index.php.backup_padding3_2026-01-20
- index.php.backup_padding_2026-01-20
- order-cancellation-policy.php.backup_2026-01-20
- payment-policy.php.backup_2026-01-20
- privacy-policy.php.backup_2026-01-20
- refund-return-policy.php.backup_2026-01-20
- shipping-policy.php.backup_2026-01-20
- terms-conditions.php.backup_2026-01-20

### Other Exposed Files [LOW-MEDIUM]
- composer.phar (3.2 MB - PHP archive, not needed in production)
- composer.lock (dependency tree exposure)
- temp_backup_index.php (87 KB - full page source)
- _run_migration.php (migration runner)
- deploy_pricing_enhancement.php
- deployment_debug.php
- view_error_log.php (exposes server logs)
- gs-secure-portal-92XK.php (security-through-obscurity portal)

---

## .ENV MIGRATION PLAN (SAFE, BACKWARD COMPATIBLE)

### Phase A: Create .env file (DO NOT DO YET)
```
DB_HOST_LOCAL=localhost
DB_NAME_LOCAL=ecommerce_db
DB_USER_LOCAL=root
DB_PASS_LOCAL=

DB_HOST_PROD=localhost
DB_NAME_PROD=u237768108_gilafstore
DB_USER_PROD=u237768108_gilafstore
DB_PASS_PROD=<move-from-db_connect>
```

### Phase B: Create includes/env_loader.php
- Parse .env file into $_ENV
- Fallback to hardcoded values if .env missing
- Zero breaking changes

### Phase C: Update db_connect.php
- Read from $_ENV with fallback
- Keep $isLocal detection logic
- Add .env to .gitignore

### Phase D: Block .env from web access
- Add to .htaccess: `<Files ".env"> Require all denied </Files>`

**STATUS:** Plan prepared. NOT executed. Awaiting Phase 2 approval.

---

## IMMEDIATE HTACCESS RECOMMENDATIONS

Add to root `.htaccess` to block access to sensitive files:
```apache
# Block SQL files
<FilesMatch "\.(sql|md|txt|log|bak|backup.*)$">
    Require all denied
</FilesMatch>

# Block debug/test scripts
<FilesMatch "^(debug_|test_|check_|fix_|clear_|deploy_|temp_)">
    Require all denied
</FilesMatch>

# Block composer files
<FilesMatch "^composer\.(phar|lock|json)$">
    Require all denied
</FilesMatch>

# Block env files
<Files ".env">
    Require all denied
</Files>
```

**STATUS:** Recommendation only. NOT applied. Awaiting Phase 2 approval.

---

## QUARANTINE CANDIDATES (75 files)
- 32 SQL files
- 10 debug scripts
- 9 test scripts
- 10 fix/check scripts
- 14 backup files

**Total exposure surface:** 75 publicly accessible non-production files.

---

## SUMMARY
| Category | Count | Risk Level |
|----------|-------|------------|
| Hardcoded credentials | 1 file | CRITICAL |
| Error message exposure | 2 locations | MEDIUM |
| Public SQL schemas | 32 files | HIGH |
| Debug scripts | 10 files | HIGH |
| Test scripts | 9 files | MEDIUM |
| Fix/check scripts | 10 files | MEDIUM |
| Backup source files | 14 files | MEDIUM |
| Other exposed files | 8 files | LOW-MEDIUM |
