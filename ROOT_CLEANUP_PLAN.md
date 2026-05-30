# ROOT DIRECTORY CLEANUP PLAN
## Phase 1 - Safe Performance & Architecture Stabilization
## Date: May 7, 2026

---

## CLASSIFICATION KEY
- **PRODUCTION** = Required for live site operation
- **BACKUP** = Old file copies, not loaded by any production code
- **DEBUG** = Development/troubleshooting scripts
- **MIGRATION** = One-time database migration scripts (already executed)
- **DOCUMENTATION** = Markdown guides and reports
- **OBSOLETE** = Superseded or empty files
- **DANGEROUS** = Publicly exposes sensitive data or allows data manipulation

---

## PRODUCTION CRITICAL (DO NOT TOUCH)

| File | Purpose |
|------|---------|
| index.php | Homepage |
| product.php | Product detail page |
| shop.php | Product listing page |
| cart.php | Shopping cart |
| checkout.php | Checkout flow |
| register.php | User registration |
| about-us.php | About us page |
| contact.php | Contact page |
| blogs.php | Blog listing |
| faqs.php | FAQ page |
| offers.php | Offers page |
| search.php | Search results |
| suggestions.php | Suggestion system |
| app_store.php | App store page |
| gifting-hampers.php | Gifting page |
| disclaimer.php | Legal page |
| privacy-policy.php | Legal page |
| terms-conditions.php | Legal page |
| shipping-policy.php | Legal page |
| payment-policy.php | Legal page |
| refund-return-policy.php | Legal page |
| order-cancellation-policy.php | Legal page |
| our-values.php | Company values |
| order_success.php | Order confirmation |
| thank-you.php | Thank you page |
| verify-batch.php | Product verification |
| track-shipment.php | Shipment tracking |
| buy_now.php | Buy now flow |
| admin_actions.php | Admin form handler |
| apply-distributor.php | Distributor application |
| application-success.php | Application success page |
| distributor-login.php | Distributor auth |
| distributor-portal.php | Distributor dashboard |
| apply_promo.php | Promo code apply |
| submit_review.php | Review submission |
| submit_suggestion.php | Suggestion submission |
| submit_application.php | Application submission |
| manage_applications.php | Application management |
| get_application.php | Application retrieval |
| download_invoice.php | Invoice download |
| generate_invoice_pdf.php | PDF invoice generation |
| generate_subject.php | Subject generation |
| confirm_upi_payment.php | UPI payment confirmation |
| upi_payment.php | UPI payment page |
| payment_gateway.php | Payment gateway |
| process_payment.php | Payment processor |
| razorpay_create_order.php | Razorpay order creation |
| razorpay_payment.php | Razorpay payment |
| razorpay_verify.php | Razorpay verification |
| razorpay_webhook.php | Razorpay webhook |
| track_click.php | Click tracking |
| track_order.php | Order tracking |
| update_region_preference.php | Region preference |
| update_visitor_countries.php | Visitor country tracking |
| live_chat_user_api.php | Live chat API |
| chatbot_callback.php | Chatbot callback |
| chatbot_create_ticket.php | Chatbot ticket creation |
| chatbot_eligible_returns.php | Chatbot returns |
| chatbot_gemini.php | Chatbot AI |
| chatbot_order_status.php | Chatbot order status |
| chatbot_recent_orders.php | Chatbot recent orders |
| chatbot_recent_tickets.php | Chatbot recent tickets |
| chatbot_ticket_status.php | Chatbot ticket status |
| merchant-feed.php | Merchant product feed |
| product-sitemap.php | Product sitemap |
| category-sitemap.php | Category sitemap |
| sitemap.xml | Main sitemap |
| pages-sitemap.xml | Pages sitemap |
| robots.txt | Robots.txt |
| manifest.json | PWA manifest |
| sw.js | Service worker |
| .htaccess | URL rewriting & security |
| .gitignore | Git ignore rules |
| composer.json | Dependencies |
| composer.lock | Dependency lock |
| gs-secure-portal-92XK.php | Secure admin portal |
| setup_content_pages.php | Content page setup |
| variant_check.php | Variant checker |
| vb.php | Batch verification short URL |
| new-design.css (root) | Legacy CSS (may be referenced) |
| new-footer.php (root) | Legacy footer (may be referenced) |

---

## BACKUP FILES (14 files) - QUARANTINE CANDIDATES

| File | Size | Action |
|------|------|--------|
| about-us.php.backup_2026-01-20 | 22 KB | Move to _quarantine/ |
| apply-distributor.php.backup_2026-01-20 | 37 KB | Move to _quarantine/ |
| faqs.php.backup_2026-01-20 | 13 KB | Move to _quarantine/ |
| index.php.backup_2026-01-20 | 35 KB | Move to _quarantine/ |
| index.php.backup_explore_2026-01-20 | 36 KB | Move to _quarantine/ |
| index.php.backup_padding2_2026-01-20 | 42 KB | Move to _quarantine/ |
| index.php.backup_padding3_2026-01-20 | 42 KB | Move to _quarantine/ |
| index.php.backup_padding_2026-01-20 | 42 KB | Move to _quarantine/ |
| order-cancellation-policy.php.backup_2026-01-20 | 11 KB | Move to _quarantine/ |
| payment-policy.php.backup_2026-01-20 | 13 KB | Move to _quarantine/ |
| privacy-policy.php.backup_2026-01-20 | 9 KB | Move to _quarantine/ |
| refund-return-policy.php.backup_2026-01-20 | 10 KB | Move to _quarantine/ |
| shipping-policy.php.backup_2026-01-20 | 10 KB | Move to _quarantine/ |
| terms-conditions.php.backup_2026-01-20 | 10 KB | Move to _quarantine/ |
| temp_backup_index.php | 85 KB | Move to _quarantine/ |

**Total: ~407 KB reclaimable**

---

## DEBUG SCRIPTS (10 files) - QUARANTINE CANDIDATES [DANGEROUS]

| File | Size | Risk |
|------|------|------|
| debug_cart_navigation.php | 10 KB | Exposes cart internals |
| debug_check.php | 1 KB | Exposes system info |
| debug_clicks.php | 4 KB | Exposes tracking data |
| debug_data_v2.php | 1 KB | Exposes DB data |
| debug_data_v3.php | 1 KB | Exposes DB data |
| debug_data_v4.php | 1 KB | Exposes DB data |
| debug_discounts.php | 4 KB | Exposes discount logic |
| debug_order_17.php | 0.4 KB | Exposes order data |
| debug_schema_check.php | 1 KB | Exposes DB schema |
| debug_signup_analysis.php | 0 KB | Empty file |

---

## TEST SCRIPTS (9 files) - QUARANTINE CANDIDATES [DANGEROUS]

| File | Risk |
|------|------|
| test_cart_link.html | Low - static HTML |
| test_click_tracking.php | Medium - tracking test |
| test_console_tracking.html | Low - static HTML |
| test_db_connection.php | HIGH - exposes DB connectivity |
| test_direct_insert.php | HIGH - can insert data |
| test_homepage_click.html | Low - static HTML |
| test_new_click_tracking.php | Medium - tracking test |
| test_php_execution.php | Medium - exposes PHP info |
| test_pricing_system.php | Medium - exposes pricing |

---

## FIX/CHECK SCRIPTS (11 files) - QUARANTINE CANDIDATES

| File | Risk |
|------|------|
| fix_and_test_clicks.php | Can modify data |
| fix_asif_outstanding.php | Can modify financial data |
| check_analytics_data.php | Exposes analytics |
| check_asif_orders.php | Exposes order data |
| check_asif_party.php | Exposes party data |
| check_asif_status.php | Exposes status data |
| check_click_data.php | Exposes click data |
| check_database_clicks.php | Exposes DB data |
| clear_test_data.php | CAN DELETE DATA |
| find_asif_exact.php | Exposes party data |
| remove_asif_300_transaction.php | CAN DELETE financial data |
| list_all_parties.php | Exposes all party data |

---

## SQL MIGRATION FILES (32 files) - QUARANTINE CANDIDATES

All `.sql` files in root expose database schema. They should be moved to a `_migrations/` folder and blocked via `.htaccess`.

---

## DOCUMENTATION FILES (53 .md files) - LOW PRIORITY

Not executable, but expose internal architecture documentation. Recommend moving to `docs/` subfolder and blocking via `.htaccess`.

---

## OBSOLETE FILES

| File | Reason |
|------|--------|
| assets/css/mobile-section-titles.css | Empty file (0 bytes) |
| chatbot.js (root, 78 KB) | Legacy duplicate - assets/js/chatbot.js (100 KB) is used |
| assets/js/main.js (20 KB) | Legacy - assets/js/new-main.js is loaded |
| cache_bust.txt | Utility file, not critical |
| SIMPLE_FIX.txt | Temp notes |
| CRITICAL_UPLOAD_INSTRUCTIONS.txt | Temp notes |
| css/ (root folder) | Contains 1 item, likely legacy |
| composer.phar (3.2 MB) | Should not be in production |

---

## RECOMMENDED QUARANTINE PROCEDURE (Phase 2)

1. Create `_quarantine/` folder in root
2. Move all debug, test, fix, check, backup files into it
3. Move all .sql files into `_quarantine/migrations/`
4. Move all .md files into `_quarantine/docs/`
5. Add `.htaccess` to `_quarantine/` with `Require all denied`
6. Verify no production code references moved files
7. Test all pages

**Total files to quarantine:** ~129 files
**Estimated disk savings:** ~1.5 MB
**Security surface reduction:** 75 publicly accessible endpoints removed

---

## DO NOT MOVE (Referenced by production code)
- All files listed in PRODUCTION CRITICAL section
- admin/ folder and contents
- api/ folder and contents
- includes/ folder and contents
- assets/ folder and contents
- user/ folder and contents
- sales-portal/ folder and contents
- cron/ folder and contents
- uploads/ folder and contents
- backups/ folder and contents
- cache/ folder and contents
- vendor/ folder and contents

---

**STATUS:** Classification complete. NO files moved or deleted. Awaiting Phase 2 approval.
