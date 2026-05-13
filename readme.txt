=== Matrix BOGO Promotions ===
Contributors: matrixplugins
Tags: woocommerce, bogo, buy one get one, free gift, promotion, discount
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 10.7.0
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create advanced Buy One Get One promotions, free gift campaigns, mix-and-match deals, and smart cart offers for WooCommerce.

== Description ==

**Matrix BOGO Promotions** is an enterprise-grade promotion engine for WooCommerce that goes far beyond simple coupons. Build Buy-X-Get-Y offers, free gift campaigns, quantity-based discounts, category promotions, and smart cart rules — all from one clean, modular admin interface.

= Key Features =

* **5 Promotion Types** — Buy X Get Y, Buy X Get X (BOGO), Spend Amount Get Gift, Cart Quantity Get Gift, Category Get Gift
* **16 Condition Types** — customer role, login status, cart subtotal, cart quantity, specific products, categories, coupon applied, purchase history, lifetime spend, completed orders, date range, day of week, time of day, and more
* **Flexible Condition Logic** — AND within a group, OR between groups; add unlimited condition groups per promotion
* **4 Reward Types** — Free product, fixed discount, percentage discount, cheapest item free
* **Customer Gift Choice** — Let customers pick their own free gift from a curated pool of products; inline radio-button selector on the cart page
* **Auto-Apply Gifts** — Automatically add free products to cart when conditions are met; remove them when they no longer qualify
* **Priority & Stacking** — Control which promotions stack, which are exclusive, and set numeric priorities
* **Scheduling** — Set precise start and end dates/times for every promotion (powered by Action Scheduler)
* **Analytics Dashboard** — Daily redemption chart, revenue attribution, top-performing promotions leaderboard, conversion rates
* **Clickable Dashboard Stats** — Active Promotions, Scheduled, Revenue, and Redemptions cards link directly to filtered views
* **REST API** — Full CRUD at `matrix-bogo/v1` for promotions, analytics, logs; suitable for headless and app integrations
* **HPOS Compatible** — Declares full compatibility with WooCommerce High-Performance Order Storage
* **Import / Export** — Export all promotions as JSON; import on any store
* **Searchable Product & Category Pickers** — Live AJAX Select2 search across all builder fields; no manual ID entry required
* **Detailed Promotions List** — Expandable rows show trigger settings, condition groups, and reward summaries at a glance
* **Multilingual & RTL Ready** — Ships with a complete POT file; compatible with WPML and Polylang; includes RTL stylesheet
* **Secure by Default** — realpath-based Autoloader path-traversal guard, prepared statements throughout, full input sanitisation and output escaping

= Promotion Types =

1. **Buy X Get Y** — Buy a configurable quantity of selected products, receive a different product free or discounted
2. **Buy X Get X** — Classic BOGO: buy N units of a product, receive additional units free
3. **Spend Amount Get Gift** — Unlock a free gift or discount when the cart subtotal crosses a threshold
4. **Cart Quantity Get Gift** — Add a minimum number of items to the cart to earn a reward
5. **Category Get Gift** — Purchase from a nominated product category and receive a gift

= Condition Groups =

Build sophisticated targeting rules using AND/OR logic:

**Customer** — User role, logged-in / guest status, specific users by ID, email address (exact or contains), first-ever order, repeat customer

**Cart** — Cart subtotal (>=, <=, =, >, <), item quantity, specific products in cart, product categories in cart, applied coupon code

**Order History** — Previously purchased a specific product, lifetime total spent, number of completed orders

**Time** — Date range (from / to), day of week (Mon–Sun multi-select), time of day range (HH:MM – HH:MM)

= Why Matrix BOGO Promotions? =

Most BOGO and promotion plugins were built before WooCommerce HPOS, the block cart, and modern PHP 8.x practices became the standard. Matrix BOGO Promotions is designed from the ground up with:

* **Modular architecture** — each promotion type, condition, and reward is an independent class; extend with your own via action hooks
* **HPOS-first** — explicitly declares compatibility with High-Performance Order Storage
* **Fresh cart evaluation** — discounts are computed against the live cart on every `calculate_totals()` call, not stale session data
* **No JavaScript UI dependencies** — Promotion Settings render server-side so the builder works even if JavaScript fails
* **Performance-conscious** — active rules are cached in WP object cache; reward evaluation short-circuits early

= Competitors =

* FlyCart Discount Rules for WooCommerce
* ELEX WooCommerce Dynamic Pricing and Discounts
* Advanced Coupons for WooCommerce

== Installation ==

1. Install and activate WooCommerce (8.0 or higher).
2. Upload and activate the Matrix BOGO Promotions plugin.
3. Navigate to **WooCommerce → Matrix BOGO → Promotions**.
4. Click **+ New Promotion**.
5. Choose a promotion type (Buy X Get Y, BOGO, Spend Amount, Cart Quantity, or Category Get Gift).
6. Configure Promotion Settings (trigger products / categories / thresholds).
7. Optionally add Condition groups to target specific customers, times, or cart states.
8. Add one or more Rewards (Free Product, Fixed Discount, % Discount, or Cheapest Item Free).
9. Set Status to **Active** and click **Save Promotion**.
10. Add qualifying products to the cart to see the promotion apply.

== Frequently Asked Questions ==

= Does this work with WooCommerce HPOS? =

Yes. The plugin explicitly declares compatibility with WooCommerce High-Performance Order Storage via `FeaturesUtil::declare_compatibility()`.

= Can I stack multiple promotions? =

Yes. Each promotion has Stackable and Exclusive flags. Stackable promotions combine; exclusive promotions block all lower-priority ones when they apply. Priority is controlled by a numeric field (lower = higher priority).

= Can customers choose their own free gift? =

Yes. Enable **Customer Choice** on any Free Product reward and add products to the Choice Pool. An inline gift-selector section appears on the cart page showing all eligible products as radio buttons.

= Does it work with variable products? =

Yes. Both trigger products and reward products support variable products and their variations.

= How are discounts applied to the cart? =

Free gifts are added as zero-price cart items. Fixed and percentage discounts are applied as WooCommerce fees (negative values). The cheapest-free reward makes the cheapest non-gift item free via a fee.

= Will updating the plugin break existing promotions? =

No. The installer's `maybe_upgrade()` routine applies all schema changes automatically on every load. No deactivation or reactivation is needed.

= Does it work on multisite? =

Yes. Network activation applies the database schema to every site in the network individually.

= Is there a REST API? =

Yes. A full CRUD REST API is available at `matrix-bogo/v1`. Endpoints cover promotions, analytics, and logs. All endpoints require the `manage_woocommerce` capability.

== Screenshots ==

1. Dashboard overview with clickable stat cards and top promotions leaderboard
2. Promotion builder — Basic Info, Schedule, Usage Limits, Stacking
3. Promotion builder — Promotion Settings (trigger products/categories/thresholds)
4. Promotion builder — Condition groups with AND/OR logic
5. Promotion builder — Reward configuration (Free Product with customer choice pool)
6. Promotions list with expandable detail rows
7. Cart page — inline gift selector with product radio buttons
8. Analytics page with daily redemption chart and top-performing promotions table

== Changelog ==

= 1.2.1 =
* Fixed: Choice pool products never displayed in gift selector — JS sends choice_pool as a comma-separated string but sync_for_rule() only JSON-encoded arrays; string was stored raw, json_decode() failed, pool was always empty
* Fixed: Fallback comma-string parser in db_rewards_to_descriptors() for existing DB rows
* Fixed: Gift section rendered multiple times on themes that fire woocommerce_cart_collaterals in multiple locations (mini-cart, sidebar, footer); added static render-once guard and is_cart()/is_checkout() page check
* Fixed: Gift section no longer renders when the choice pool contains no valid or in-stock products

= 1.2.0 =
* Fixed (CRITICAL): Conditions were never saved to the database — JS sends a 2D array [[group],[group]] but PromotionBuilder::ajax_save() passed it directly to sync_for_rule() which expects flat rows
* Fixed (CRITICAL): Conditions were blank on edit — initBuilder() assigned flat DB rows directly to matrixBogoConditions (2D format expected); now regroups by group_id
* Fixed: is_true / is_false operators (Logged In, First Order, Repeat Customer) were unhandled in AbstractCondition::compare() — always returned false
* Fixed: = operator (Cart Subtotal / Cart Quantity equality) was missing from compare() — always returned false
* Fixed: Date Range condition stored as pipe-separated string (2026-01-01|2026-12-31) but PHP expected array keys start/end — condition always passed regardless of configured dates
* Fixed: Time Range condition had the same pipe-separated format mismatch — always passed
* Fixed: Day of Week stored abbreviated names (mon, tue…) but PHP used intval() converting all to 0 — never matched date(N) values 1–7; condition always failed
* Fixed: Purchase History not_in operator was ignored — always returned true if any listed product was purchased

= 1.1.1 =
* Fixed: fixed_discount and percent_discount reward types never applied — apply_promotions() read stale session rewards one step behind the cart state; now calls evaluate_cart() directly
* Fixed: percent_discount now iterates cart items directly for reliability across tax configurations; also correctly matches variation IDs for product-specific discounts
* Fixed: apply_fixed_discount and apply_percent_discount guard against zero discount_value

= 1.1.0 =
* Fixed: MATRIX_BOGO_VERSION constant mismatch (1.0.8 vs 1.0.9 header) — asset cache-busting was broken
* Fixed: WC tested up to updated to 10.7.0
* Fixed: Dashboard stat cards are now clickable links to filtered views
* Fixed: Gift popup JS called wrong AJAX action (matrix_bogo_get_gifts vs matrix_bogo_search_gifts)
* Fixed: Gift popup JS used wrong image field key (p.image vs p.thumbnail)
* Fixed: Cart gift section nonce mismatch (matrix_bogo_gift_nonce vs matrix_bogo_frontend)
* Security: Autoloader path-traversal guard upgraded to realpath() containment verification

= 1.0.9 =
* Fixed: wpdb::prepare() called without placeholder in RulesRepository::get_active_rules()
* Fixed: Unescaped output in PromotionsList.php — trigger product and category names now escaped
* Fixed: Unsanitized $_POST['prune_days'] and $_GET['paged'] in Logs.php
* Fixed: Potential file inclusion vulnerability in Autoloader.php
* Updated: WC tested up to 10.0; Tested up to WordPress 6.9

= 1.0.8 =
* Fixed: Fatal TypeError on every page load — removed strict WC_Product type hint from gift_is_purchasable()
* Fixed: White screen on activation — malformed wp_enqueue_script() calls in Assets.php
* Fixed: Action Scheduler notices on init
* Fixed: ProgressBar and show_notices default settings

= 1.0.7 =
* Added: Promotions list with expandable detail rows showing settings, conditions, and rewards

= 1.0.6 =
* Fixed: Promotions evaluated but gifts/discounts never appeared in cart
* Fixed: All 5 promotion types now read flat rule_data keys correctly
* Fixed: cart_quantity_get_gift type key mismatch
* Added: cheapest_free reward type; percentage discount applies to whole cart when no product_id set

= 1.0.5 =
* Fixed: Failed to save promotion — description column missing from schema
* Added: Automatic DB schema upgrade on plugins_loaded

= 1.0.4 =
* Fixed: Missing buildConditionRowHtml declaration causing JS fatal

= 1.0.3 =
* Fixed: Critical JS syntax error breaking all builder buttons and search
* Changed: Minimum search input length raised to 3 characters

= 1.0.2 =
* Added: Live AJAX product and category search powering all Select2 fields

= 1.0.1 =
* Fixed: Promotion Settings rendered server-side (no more blank loading state)
* Fixed: CSS not loading on plugin pages

= 1.0.0 =
* Initial release — 5 promotion types, 16 condition types, priority manager, analytics dashboard, REST API, HPOS compatibility, import/export, multilingual

== Upgrade Notice ==

= 1.2.1 =
Fixes gift pool products not appearing in the gift selector, and the gift section duplicating itself on themes with multiple cart widget areas. Update recommended.

= 1.2.0 =
Critical fix release. Conditions were never being saved or loaded correctly. All 8 condition-related bugs resolved. Update immediately if you use conditions.

= 1.1.1 =
Fixed fixed_discount and percent_discount reward types which were never applying. Update immediately if you use these reward types.

