# Matrix BOGO — WooCommerce Vendor Submission Checklist

Version: **1.0.8**  
Last updated: 2026-05-12

---

## Critical bugs fixed in 1.0.8 (required for submission)

| # | Bug | File(s) | Status |
|---|-----|---------|--------|
| 1 | `promotion_label` never set → cart notices always blank | `PromotionEngine.php` | ✅ Fixed |
| 2 | Active-rules cache key changed every second → no caching | `RulesRepository.php` | ✅ Fixed |
| 3 | `get_for_order()` missing → fatal on every completed order | `RedemptionsRepository.php` | ✅ Fixed |
| 4 | 'debug' log level not in DB ENUM → corrupt/failed inserts | `Schema.php`, `Logger.php`, `LogsRepository.php` | ✅ Fixed |
| 5 | `get_daily($from,$to)` missing required `$rule_id` arg → REST fatal | `AnalyticsEndpoint.php` | ✅ Fixed |
| 6 | `increment_uses()` wildcard cache bust never matched → stale counts | `RulesRepository.php` | ✅ Fixed |
| 7 | Gift popup modal completely unstyled | `frontend.css` | ✅ Fixed |

---

## Files changed in this release

Replace these files in your plugin directory:

```
includes/Promotions/PromotionEngine.php      ← promotion_label fix
includes/Database/Repositories/
    RulesRepository.php                      ← cache key fix
    RedemptionsRepository.php                ← get_for_order() added
    LogsRepository.php                       ← debug level fix
includes/Database/Schema.php                 ← debug ENUM + ALTER migration
includes/Helpers/Logger.php                  ← debug level validation
includes/API/Endpoints/AnalyticsEndpoint.php ← get_daily_totals() fix
assets/css/frontend.css                      ← popup styles added
matrix-bogo.php                              ← version bump + WC tested up to
readme.txt                                   ← changelog + WC tested up to
phpcs.xml.dist                               ← NEW: coding standards config
```

---

## WooCommerce Marketplace Requirements

### Plugin header ✅
- Plugin name, URI, description, version ✅
- Author + URI ✅
- Text domain matches slug (`matrix-bogo`) ✅
- `WC requires at least` header ✅
- `WC tested up to` header ✅ (updated to 9.6)
- `Requires PHP` header ✅

### Security ✅
- All user input sanitized before use ✅
- All output escaped with `esc_html()`, `esc_attr()`, `wp_kses_post()` ✅
- Nonce verification on all AJAX and form handlers ✅
- Capability checks (`manage_woocommerce`) on all admin actions ✅
- All DB queries use `$wpdb->prepare()` ✅
- No direct `$_GET`/`$_POST` access without `sanitize_*` ✅

### Code quality ✅
- PSR-4 autoloader, namespace `MatrixBogo\` ✅
- `declare(strict_types=1)` on all PHP files ✅
- `ABSPATH` guard on all PHP files ✅
- `phpcs.xml.dist` included for WP coding standards ✅
- PHP 8.1+ features used appropriately ✅

### Internationalization ✅
- All user-facing strings wrapped in `__()` / `esc_html__()` ✅
- Text domain `matrix-bogo` used consistently ✅
- POT file included at `languages/matrix-bogo.pot` ✅

### WordPress compatibility ✅
- GPL-2.0-or-later license ✅
- `uninstall.php` with data-cleanup behind an opt-in flag ✅
- No `die()` or `exit()` in plugin body (only in autoloader guard) ✅
- Hooks registered via `add_action`/`add_filter`, not direct calls ✅
- HPOS (High-Performance Order Storage) compatibility declared ✅
- Cart + Checkout Blocks compatibility declared ✅
- Action Scheduler used for scheduled tasks (no WP Cron dependency) ✅

### Database ✅
- Custom tables use `dbDelta()` for safe creation/upgrade ✅
- All tables use `{$wpdb->prefix}` prefix ✅
- `charset_collate` applied to every table ✅
- DB version option stored and compared for upgrade gating ✅
- Uninstall removes all tables and options ✅

---

## Recommended before submission

- [ ] Run `composer install` then `vendor/bin/phpcs --standard=phpcs.xml.dist .` — fix any remaining warnings
- [ ] Test with WooCommerce 8.0 and 9.6 (min and max supported)
- [ ] Test with PHP 8.1 and 8.3
- [ ] Activate on a fresh WP install and confirm setup wizard fires
- [ ] Create a "Buy X Get Y" promotion, add trigger product to cart, verify gift appears at $0
- [ ] Enable a customer-choice reward and verify the popup appears, is styled, and product can be selected
- [ ] Verify cart notices display the promotion name (not blank)
- [ ] Complete a test order and confirm no fatal errors in error log
- [ ] Check REST API: `GET /wp-json/matrix-bogo/v1/analytics` returns valid JSON
- [ ] Integrate a real licensing API in `LicenseManager::activate()` (currently stub)
- [ ] Update `WC tested up to` in readme.txt if testing against a newer WC version
- [ ] Generate a fresh POT file: `wp i18n make-pot . languages/matrix-bogo.pot --domain=matrix-bogo`
- [ ] Minify `assets/js/admin.js` and `assets/js/frontend.js` for production (keep un-minified sources)
- [ ] Add at least 3 PHPUnit tests covering `ConditionEngine::evaluate()`, `PriorityManager::resolve()`, and `AbstractRepository::create()`

---

## Pricing / subscription model notes

WooCommerce Marketplace accepts subscription plugins. Recommended approach:

1. **Free tier** — ship the plugin on WordPress.org with basic promotion types (e.g. Spend Amount Get Gift only)
2. **Pro tier** — sell on WooCommerce.com/Marketplace with all 5 types, analytics, REST API, and multi-site support
3. **License enforcement** — replace the stub in `LicenseManager::activate()` with a call to your license server (Freemius SDK or custom EDD Software Licensing endpoint)
4. **Freemius** is the most common SaaS licensing layer used by WooCommerce.com plugins and handles annual renewals, upgrade/downgrade, and refunds automatically
