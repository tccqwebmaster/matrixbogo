=== Matrix BOGO WooCommerce Promotion ===
Contributors: matrixplugins
Tags: woocommerce, bogo, buy one get one, free gift, promotion, discount, coupon
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enterprise-grade BOGO and promotion engine for WooCommerce. Create Buy-X-Get-Y offers, free-gift promotions, quantity discounts and more.

== Description ==

**Matrix BOGO WooCommerce Promotion** is a powerful, developer-friendly promotion engine that goes far beyond simple coupons.

= Key Features =

* **5 Promotion Types** – Buy X Get Y, Buy X Get X (same product), Spend Amount Get Gift, Cart Quantity Get Gift, Category Get Gift
* **Condition Engine** – 16 condition types including cart subtotal, cart quantity, customer roles, purchase history, date/time ranges and more
* **Priority & Stacking** – Define which promotions stack, which are exclusive, and set granular priorities
* **Auto-Apply & Gift Popup** – Automatically add free products to cart, or show an elegant gift-choice popup
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

== Screenshots ==

1. Dashboard overview with stats and top promotions
2. Promotion builder with condition groups
3. Gift selector popup on the cart page
4. Analytics page with daily chart
5. Settings page with license management

== Changelog ==

= 1.0.6 =
* Fixed: Promotions were evaluated but gifts/discounts never appeared in cart
* Fixed: All 5 promotion types now correctly read flat rule_data keys saved by the builder (trigger_product_ids, trigger_quantity, min_amount, min_quantity, trigger_category_ids)
* Fixed: Rewards from the matrix_bogo_rewards table are now loaded and injected into each promotion type at evaluation time
* Fixed: cart_quantity_get_gift type key mismatch (was cart_qty_get_gift in registry)
* Fixed: Fixed-discount and percentage-discount rewards now apply without requiring a product_id
* Added: cheapest_free reward type now supported in cart
* Added: Percentage discount applies to full cart when no product_id is set

= 1.0.5 =
* Fixed: "Failed to save promotion" — description field was in the form but missing from the database schema, causing every INSERT to fail
* Added: description column to matrix_bogo_rules table
* Added: Automatic DB schema upgrade on plugins_loaded — no deactivate/reactivate needed
* Bumped: DB version to 1.0.1

= 1.0.4 =
* Fixed: Missing function declaration for buildConditionRowHtml — accidentally dropped during 1.0.3 edit, causing a second fatal JS syntax error
* Fixed: Add Condition Group, Add Reward, Trigger Products search all now fully functional

= 1.0.3 =
* Fixed: Critical JavaScript syntax error in buildConditionValueHtml — broken switch statement caused entire admin.js to fail silently
* Fixed: Add Condition Group button now works
* Fixed: Add Reward button now works
* Fixed: Trigger Products / Categories Select2 search now functional
* Changed: Minimum search length for all product/category fields raised to 3 characters

= 1.0.2 =
* Added: Live AJAX product search — find products by name, SKU, or ID in all product fields
* Added: Live AJAX category search — find categories by name or ID in all category fields
* Added: New AjaxSearch class powering searchable Select2 dropdowns across Promotion Settings, Conditions, and Rewards
* Improved: Reward 'Free Product' field now uses searchable picker instead of manual ID input
* Improved: Reward 'Choice Pool' field now uses multi-product searchable picker
* Improved: Condition 'Cart Contains Products / Purchased Product' fields now use searchable picker
* Improved: Condition 'Cart Contains Categories' field now uses searchable category picker
* Fixed: Select2 (WooCommerce enhanced select) pre-populated correctly on edit page load

= 1.0.1 =
* Fixed: Promotion Settings fields now rendered server-side — no longer shows "Loading…" regardless of JS state
* Fixed: CSS not loading on plugin pages (removed wp-components dependency)
* Fixed: Condition and Reward builder data passed as inline JS object instead of HTML attribute JSON
* Fixed: Analytics chart moved inside DOM-ready block

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.3 =
Fixes critical JS crash that broke all builder buttons and search fields. Update immediately.

= 1.0.2 =
Adds live product/category search to all builder fields. No more typing IDs manually.

= 1.0.1 =
Fixes Promotion Settings not rendering and CSS not loading. Update recommended.

= 1.0.0 =
Initial release of Matrix BOGO WooCommerce Promotion.
