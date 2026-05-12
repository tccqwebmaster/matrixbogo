=== Matrix BOGO WooCommerce Promotion ===
Contributors: matrixplugins
Tags: woocommerce, bogo, buy one get one, free gift, promotion, discount, coupon
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enterprise-grade BOGO and promotion engine for WooCommerce. Create Buy-X-Get-Y offers, free-gift promotions, quantity discounts and more.

== Description ==

**Matrix BOGO WooCommerce Promotion** is a powerful, developer-friendly promotion engine that goes far beyond simple coupons.

= Key Features =

* **5 Promotion Types** – Buy X Get Y, Buy X Get X (same product), Spend Amount Get Gift, Cart Quantity Get Gift, Category Get Gift
* **Condition Engine** – 16 condition types including cart subtotal, cart quantity, customer roles, purchase history, date/time ranges and more
* **Priority & Stacking** – Define which promotions stack, which are exclusive, and set granular priorities
* **Auto-Apply & Gift Popup** – Automatically add free products to cart, or show an elegant gift-choice modal
* **Scheduling** – Set start/end dates for every promotion (powered by Action Scheduler)
* **Analytics Dashboard** – Daily redemption charts, revenue attribution, conversion rates
* **REST API** – Full CRUD API at `matrix-bogo/v1`
* **HPOS Compatible** – Fully compatible with WooCommerce High-Performance Order Storage
* **Import/Export** – Export promotions as JSON and import on any site
* **Multilingual** – Ships with a complete POT file; WPML/Polylang compatible

= Promotion Types =

1. **Buy X Get Y** – Buy a set of products, get a different product free or discounted
2. **Buy X Get X** – Buy N units of a product, get additional units free
3. **Spend Amount Get Gift** – Spend above a threshold and receive a free gift
4. **Cart Quantity Get Gift** – Add a minimum quantity to cart to unlock a gift
5. **Category Get Gift** – Purchase from a specific category and receive a reward

= Conditions =

Group conditions with AND (within a group) and OR (between groups):

* Customer: User role, logged-in status, specific user, email address, first order, repeat customer
* Cart: Subtotal, total quantity, specific products, categories, applied coupon
* Order history: Previous orders, lifetime spend, completed order count
* Time: Date range, day of week, time of day

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the *Plugins* menu in WordPress
3. Navigate to **Matrix BOGO → Settings** and enter your license key
4. Go to **Matrix BOGO → Promotions** and create your first promotion

== Frequently Asked Questions ==

= Does this work with WooCommerce HPOS? =

Yes. The plugin declares full compatibility with WooCommerce High-Performance Order Storage.

= Can I stack multiple promotions? =

Yes. Use the *Stackable* and *Exclusive* flags to control how promotions interact.

= Can customers choose their own gift? =

Yes. Enable *Customer Choice* on a reward and provide a pool of eligible products. A gift-selector popup will appear.

= Does it work with variable products? =

Yes. Both trigger products and reward products support product variations.

= Will the plugin break on existing installs when I update? =

No. The installer's `maybe_upgrade()` routine runs on every load and applies schema changes automatically. No deactivation/reactivation is needed.

== Screenshots ==

1. Dashboard overview with stats and top promotions
2. Promotion builder with condition groups
3. Gift selector popup on the cart page
4. Analytics page with daily chart
5. Settings page with license management

== Changelog ==

= 1.0.8 =
* Fixed: Cart promotion notices were always blank — `promotion_label` key was never populated in the session rewards map
* Fixed: Active rules object-cache was a no-op — cache key changed every second (md5 of current timestamp); replaced with a static key busted on mutation
* Fixed: Fatal error on every completed order — `RedemptionsRepository::get_for_order()` was called by RevenueTracker but the method did not exist
* Fixed: `LogsRepository` and `Logger` allowed 'debug' level strings but the DB ENUM only listed 'info|warning|error'; existing installs now receive an automatic ALTER TABLE migration
* Fixed: `AnalyticsEndpoint::get_analytics()` called `get_daily($from, $to)` — missing required `$rule_id` first argument caused a TypeError on every REST analytics request; replaced with correct `get_daily_totals($from, $to)`
* Fixed: `RulesRepository::increment_uses()` attempted to bust the active-rules cache with a wildcard pattern that never matches; now busts the correct static key
* Added: Complete gift-selector popup CSS — the modal was rendered but completely unstyled
* Added: `/analytics/rule/{id}` REST endpoint for per-rule daily analytics breakdown
* Added: `phpcs.xml.dist` for WordPress/WooCommerce coding standards enforcement
* Updated: WC tested up to 9.6
* Updated: DB version bumped to 1.0.2

= 1.0.7 =
* Improved: Promotions list dashboard now shows expandable detail rows per promotion
* Added: Each row can be expanded to reveal Promotion Settings, Conditions summary, and Rewards summary

= 1.0.6 =
* Fixed: Promotions were evaluated but gifts/discounts never appeared in cart
* Fixed: All 5 promotion types now correctly read flat rule_data keys saved by the builder
* Fixed: Rewards from the matrix_bogo_rewards table are now loaded and injected at evaluation time
* Fixed: cart_quantity_get_gift type key mismatch
* Fixed: Fixed/percent-discount rewards now apply without requiring a product_id
* Added: cheapest_free reward type now supported
* Added: Percentage discount applies to full cart subtotal when no product_id is set

= 1.0.5 =
* Fixed: "Failed to save promotion" — description column was missing from the database schema
* Added: description column to matrix_bogo_rules table (DB version 1.0.1)
* Added: Automatic DB schema upgrade on plugins_loaded

= 1.0.4 =
* Fixed: Missing function declaration for buildConditionRowHtml

= 1.0.3 =
* Fixed: Critical JavaScript syntax error in buildConditionValueHtml
* Fixed: Add Condition Group button, Add Reward button, Trigger Products search
* Changed: Minimum input length for product/category search raised to 3 characters

= 1.0.2 =
* Added: Live AJAX product/category search powering Select2 dropdowns

= 1.0.1 =
* Fixed: Promotion Settings fields rendered server-side
* Fixed: CSS not loading on plugin pages
* Fixed: Condition and Reward builder data passed as inline JS

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.8 =
Critical bug-fix release. Resolves cart notice blanks, completed-order fatals, analytics REST errors, and a completely missing popup stylesheet. Update immediately.

= 1.0.3 =
Fixes critical JS crash that broke all builder buttons and search fields. Update immediately.
