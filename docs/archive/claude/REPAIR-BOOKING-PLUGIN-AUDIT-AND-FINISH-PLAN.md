# Repair Booking Form — Full Audit & Finish Plan

**Repo:** github.com/abidalidevv/Mobile-Repair-booking-wordpress-plugin
**Audit date:** 2026-09-08
**Codebase size:** ~11,300 lines PHP, single monolithic core file (`repair-booking-form.php`, 7,604 lines / 328KB)
**Verdict:** Plugin is roughly 50% done. Frontend UX/design work looks mature. Backend has two show-stopping architectural bugs that mean the admin panel currently **cannot change what customers see**, and payments are 100% fake (stubbed). Fix order matters — see Part 6.

---

## Part 0 — Executive Summary (read this first)

| # | Finding | Severity |
|---|---|---|
| 1 | Admin "Repairs" price editor writes to DB tables the storefront never reads → **prices shown to customers cannot be changed from admin** | 🔴 Critical |
| 2 | Admin "Models" page writes to DB tables the storefront never reads → **adding/editing a model in admin does not appear on the booking form** | 🔴 Critical |
| 3 | Every repair (Screen, Battery, etc.) shows the **same price for every brand and model** — the per-model pricing matrix exists in the DB but is never queried on the frontend | 🔴 Critical |
| 4 | `ajax_process_payment`, `ajax_create_payment_intent`, `ajax_confirm_payment` are **empty stubs** — they return "success" without contacting Stripe/PayPal/any gateway | 🔴 Critical |
| 5 | ~350 lines of a second, entirely separate "JSON-based" catalog CRUD backend exist and are called from **nowhere** in the UI — dead code | 🟠 High |
| 6 | 70 of 196 seeded device models (36%) show the **wrong photo** — e.g. every "iPhone 17" variant displays the iPhone 15 Pro Max image; 34 models fall back to a generic placeholder | 🟠 High |
| 7 | Live currency-rate API call disables SSL verification (`sslverify => false`) | 🟠 High |
| 8 | No internationalization at all — 0 uses of `__()`/`_e()`, no text domain loaded, no `/languages` folder. "Multilingual" today means a full rewrite of every string. | 🟠 High |
| 9 | Debug/test files ship inside the plugin folder (`debug-plugin.php`, `check-syntax.php`, `debug-errors.log`, `scratch/`) — some are directly web-accessible if uploaded to a live site | 🟡 Medium |
| 10 | `error_log()` + `print_r()` of full request data fires on every single frontend AJAX call (models/repairs lookup) — log bloat + customer data (name/phone/email) leaking into server logs | 🟡 Medium |
| 11 | Currency system only does FX conversion off one AED base price. It does **not** support genuinely different base prices per country (different parts cost, different market rates) | 🟡 Medium |
| 12 | Single 7,604-line PHP file holding routing, 40+ AJAX handlers, admin pages, and business logic together | 🟡 Medium (maintainability, not a bug) |

Everything below explains each finding with file/line evidence, then gives you the plan to finish the 4 things you asked for (device library, country pricing, multi-payment, multilingual).

---

## Part 1 — Critical Bugs (with evidence)

### 1.1 Pricing engine disconnect — the actual root cause of your "price confusion"

There are **two separate pricing systems** in this codebase:

**System A — DB-based per-model pricing matrix** (the "real" one, well designed):
- Tables: `wp_rbf_pricing` (brand_id + model_id + repair_id → price), `wp_rbf_default_prices`
- Admin tools: `ajax_update_repair_price`, `ajax_bulk_update_prices`, `ajax_save_default_prices`, `ajax_export_prices`/`ajax_import_prices` (CSV), even `ajax_generate_random_prices`
- All of this is nonce + `manage_options` protected — the code quality here is actually good.

**System B — flat JSON file** (`brands_models_data.json`) with one global `repair_services` list, each repair having exactly **one price** (e.g. Screen Replacement = 490, flat).

**The bug:** the customer-facing function `get_repairs_by_model($brand, $model)` (repair-booking-form.php:631) takes `$brand` and `$model` as arguments but **never uses them** to look up a price. It just loops the flat JSON `repair_services` array and returns the same global price regardless of device:

```php
// repair-booking-form.php:657-675 (current behavior)
foreach ($repair_services as $service) {
    $price = isset($service['price']) ? floatval($service['price']) : 0; // same for every model!
    ...
}
```

**Result:** an iPhone 16 Pro Max screen and an iPhone SE screen currently cost exactly the same on your booking form. Everything you built in System A (the per-model price matrix, bulk pricing tool, CSV import/export) is invisible to customers. This is almost certainly the "price confusion" you mentioned — it's not a currency issue, it's this.

**Fix:** rewrite `get_repairs_by_model()` to query `wp_rbf_pricing` for `(brand_id, model_id, repair_id)`, falling back to `wp_rbf_default_prices` when no override exists. Retire the JSON `repair_services` price field entirely (keep JSON only for icon/description/duration, or migrate those into the DB too — see Part 5).

### 1.2 Catalog CRUD disconnect

Same pattern, different data:

- `admin/models.php` reads the brand list from `wp_rbf_brands` and saves new models via AJAX action `rbf_save_model` → writes to `wp_rbf_models`.
- `admin/repairs.php` saves/deletes via `rbf_save_repair` / `rbf_delete_repair` → writes to `wp_rbf_repairs`.
- But the storefront's `ajax_get_models` / `ajax_get_repairs` (the functions customers actually hit) read exclusively from `brands_models_data.json` via `RBF_Brands_Models_Manager`.

**Result:** you can add a brand/model/repair in wp-admin, see it appear in the admin list (because that list is also DB-based, so it *looks* like it worked), and it will **never show up on the live booking form**. The only way to actually change the customer-facing catalog today is to hand-edit `brands_models_data.json` on the server.

**Fix:** pick ONE source of truth. Recommendation: **DB tables**, not JSON (see Part 5 for why). Point `get_models_by_brand()` / `get_repairs_by_model()` at `wp_rbf_brands` / `wp_rbf_models` / `wp_rbf_repairs` instead of the JSON manager class.

### 1.3 Dead code — orphaned JSON CRUD backend

`ajax_add_brand_json`, `ajax_update_brand_json`, `ajax_delete_brand_json`, `ajax_add_model_json`, `ajax_update_model_json`, `ajax_delete_model_json`, `ajax_add_repair_json`, `ajax_update_repair_json`, `ajax_delete_repair_json` — 9 handlers, several hundred lines — are registered but **never called from any JS file or admin screen** anywhere in the repo. Nothing in the UI triggers them.

**Fix:** delete this entire block once you've migrated to DB-only catalog (Part 1.2). Don't maintain code with no caller.

### 1.4 Payment processing is fake

```php
// repair-booking-form.php:5527-5546 — this is the ENTIRE implementation
public function ajax_process_payment() {
    wp_send_json_success('Payment processed successfully');
}
public function ajax_create_payment_intent() {
    wp_send_json_success('Payment intent created successfully');
}
public function ajax_confirm_payment() {
    wp_send_json_success('Payment confirmed successfully');
}
```

No Stripe SDK call, no PayPal call, nothing. They unconditionally report success. Right now the frontend doesn't even call these (checked `main.js` — checkout only calls `rbf_submit_booking`, which just creates a "pending" booking; there is no online-payment step live at all today). The `admin_payment_settings()` page does correctly collect and store Stripe/PayPal keys via `update_option()` with proper `check_admin_referer()` — so the admin *storage* side is fine, it's the *processing* side that's 100% unbuilt.

**Danger if you wire the frontend to these stubs as-is:** you'd be marking bookings "paid" without ever charging a card. This needs real implementation, not a shortcut — see Part 4.3.

### 1.5 Device image data quality

Checked `brands_models_data.json` programmatically:

- 196 models total, only 126 unique image files referenced.
- `Brands/other_brand.jpg` (generic placeholder) is used 34 times.
- `Brands/iphone_modals/apple-iphone-15-pro-max.jpg` is reused 12 times — including for "iPhone 17 Pro Max", "iPhone 17 Pro", "iPhone 17", "iPhone Air", "iPhone 17e" (devices that don't look like an iPhone 15).

**This is worse than having no photo.** A shop owner's customer will see a visibly wrong device photo, which reads as unprofessional/broken rather than "in progress." See Part 4.1 for the fix.

---

## Part 2 — Security Issues

1. **SSL verification disabled** on the live exchange-rate fetch:
   ```php
   // includes/class-rbf-currency.php:108-112
   $response = wp_remote_get($api_url, [
       'timeout' => 8,
       'sslverify' => false,   // <-- remove this. No reason to disable it for a public rates API.
       ...
   ]);
   ```
2. **Missing `ABSPATH` guard** in `includes/class-rbf-currency.php`... actually confirmed missing in 2 files that should have it at the very top (defense-in-depth against direct file access): double check every file under `includes/`, `admin/`, `templates/` starts with:
   ```php
   if (!defined('ABSPATH')) { exit; }
   ```
3. **Debug/dev files shipping inside the plugin folder**: `debug-plugin.php`, `check-syntax.php`, `error-logger.php`, `debug-errors.log`, `scratch/verify_plugin_complete.php`. If this plugin folder is uploaded as-is to a live site, some of these may be directly reachable at `yoursite.com/wp-content/plugins/repair-booking-form/debug-plugin.php` depending on server config. `debug-errors.log` sitting in a public-ish folder is an information-disclosure risk (old errors may include stack traces, file paths, possibly booking data).
   **Fix:** delete all of these from the distributable build, or move them to a `dev/` folder excluded by your build/zip script and never shipped.
4. **Verbose logging of user data**: `ajax_get_models()` / `ajax_get_repairs()` call `error_log(print_r($models, true))` etc. on every request — remove or gate behind `if (WP_DEBUG)`.
5. **Stripe/PayPal secret keys** are stored via plain `update_option()` — acceptable for most WP plugins (not a hard blocker), but since you're handling live payment credentials, consider that anyone with DB access or a DB backup leak gets your live secret key in plaintext. Not urgent to change, just be aware when you pick hosting.
6. Nonce + capability checks are actually **present and correct** on almost all admin AJAX handlers (`ajax_save_brand`, `ajax_delete_model`, `ajax_bulk_update_prices`, etc.) — this part of the code is solid, no complaints here.

---

## Part 3 — Architecture & Maintainability

- **7,604-line single file** holding the plugin bootstrap, 40+ AJAX handlers, admin dashboard rendering, booking logic, and pricing logic together. Any future bug search means grep-ing a 328KB file. This is the direct cause of bugs like Part 1.1/1.2 — nobody could visually spot "these two systems never talk to each other" in a file this size.
- **Two parallel catalog data stores** (JSON + DB) is itself an architecture smell, independent of the specific bug — pick one.
- **No separation between AJAX routing, data access, and business logic.** e.g. `ajax_update_repair_price` builds raw SQL inline in the handler instead of calling a `RBF_Pricing::update()` method.
- Good news: `class-rbf-currency.php`, `class-rbf-whatsapp.php`, `class-rbf-tracker.php` show you already know how to split things into classes — the currency and WhatsApp modules are clean, single-responsibility, and reasonably well-written. Apply that same pattern to the rest.

**Recommended target structure** (see Part 5 for full detail) — split `repair-booking-form.php` into:
```
includes/
  class-rbf-loader.php          (bootstrap, hooks only)
  class-rbf-catalog.php         (brands/models/repairs CRUD — DB only)
  class-rbf-pricing.php         (price lookup + admin pricing tools)
  class-rbf-booking.php         (booking creation, status, tracker)
  class-rbf-payments.php        (gateway abstraction — see Part 4.3)
  class-rbf-currency.php        (already exists, keep)
  class-rbf-whatsapp.php        (already exists, keep)
  class-rbf-invoice.php         (PDF/print invoice generation)
admin/
  (keep as-is, but handlers should call the classes above, not embed SQL)
```

---

## Part 4 — Your 4 New Requirements: Analysis & Plan

### 4.1 "Sab models already imported ho with pictures"

Realistic assessment: you cannot get accurate, licensed photos for 196+ device models "already done" for free — that's genuine sourcing work, and reusing wrong images (current state) actively hurts you more than a generic-but-honest placeholder. Three real options, ranked:

1. **Best (recommended for launch):** Use **one accurate generic image per device *series*** (e.g. one iPhone 16-series render, one Galaxy S24-series render) instead of pretending each model has a unique photo. Customers care about *is my exact model in the list* (a dropdown/text match), not about seeing a pixel-perfect product photo. This is what most competitors (RepairPlugin, RepairBuddy) effectively do — a brand/category icon, not 3,000 unique glamour shots.
2. **Better, more work:** Buy a licensed device-image dataset (a few exist commercially for repair/trade-in shops) or use manufacturer press-kit renders where the license allows commercial use — needs per-brand license checking, don't scrape Google Images (copyright risk).
3. **Most accurate, most expensive:** Commission/photograph real devices — only worth it once you have paying shops who care.

**Immediate fix (cheap, do this first):** run a script over `brands_models_data.json`, group models by `series`, and reassign every model in a series to that series' correct image instead of the current random/wrong assignments. This removes the "iPhone 17 shows iPhone 15 photo" embarrassment in under an hour of work, with zero new photos needed.

### 4.2 "Prices vary by country" — this is a data model gap, not a bug

You're conflating two different things, and the current code only has one of them half-built:

- **Currency conversion** (already exists in `class-rbf-currency.php`): same AED base price, just displayed in SAR/USD/PKR/etc. at the live FX rate. This is what you have.
- **Different actual prices per country** (what you're describing — parts cost more/less to import in different countries, competitor pricing differs, labor cost differs): this needs a **new price dimension**, not currency conversion.

**Recommended fix:** extend `wp_rbf_pricing` with a `country_code` column (nullable = "applies everywhere"):
```
wp_rbf_pricing: id, brand_id, model_id, repair_id, country_code (nullable), price, currency
```
Lookup order when pricing a repair: **country+model+repair override → country+repair default → global model+repair → global default**. Detect country via a simple setting per shop (most of your customers are single-country shops, so this is probably a one-time admin setting, not per-visitor geo-IP — don't over-engineer this with geo-IP detection unless you're selling to a shop chain operating across multiple countries).

### 4.3 Multi-payment gateway support

Current state: zero real gateways wired up, only credential storage. Don't try to "support everything" in v1 — build one abstraction, ship one real gateway, add more later.

**Region reality check (verified today):** Stripe officially operates for UAE merchants since 2023 and is the fastest to onboard (24-48h) for a clean business entity — good fit for a developer-led product like yours. If you also sell into Saudi Arabia, note Stripe does **not** support Mada (the Saudi domestic card scheme) — you'd need Telr, PayTabs, or Network International for Saudi traffic specifically. Since your current base currency is AED and primary market is UAE, start with Stripe; treat KSA/Mada as a phase-2 add-on, not a launch blocker.

**Architecture:**
```
class-rbf-payments.php
  interface RBF_Payment_Gateway { charge(), refund(), verify_webhook() }
  class RBF_Gateway_Stripe implements RBF_Payment_Gateway
  class RBF_Gateway_COD implements RBF_Payment_Gateway   // "pay at shop" — you already effectively have this
  class RBF_Gateway_WhatsApp_Manual implements RBF_Payment_Gateway // current de-facto flow
```
"Multi payment symbols in settings" = each gateway has its own settings tab (already partially built for Stripe/PayPal) + a toggle for which ones are customer-facing at checkout + the logo icons rendered as radio options in step 4 of the wizard. The hard part isn't the icons, it's actually implementing `RBF_Gateway_Stripe::charge()` for real using the Stripe PHP SDK and Stripe Elements/Payment Intents on the frontend — budget real dev time for this, it's not a settings-page task.

**MVP recommendation:** ship with "Pay at shop / Pay on delivery" + WhatsApp confirmation (your current flow, which already works) as the default, and add real Stripe online payment as a phase-2 feature once bookings volume justifies the integration work. Don't block launch on this.

### 4.4 Multilingual

Current state: **zero i18n**. No `__()`/`_e()` anywhere, no text domain loaded, no `.pot`/`.po`/`.mo` files, no `/languages` folder. Every string — PHP and JS — is hardcoded English.

This is a real scope item, not a settings toggle. To do it properly:

1. Add to plugin header: `Text Domain: repair-booking-form`, `Domain Path: /languages`.
2. Call `load_plugin_textdomain('repair-booking-form', false, dirname(plugin_basename(__FILE__)) . '/languages')` on `init`.
3. Wrap **every** user-facing string in PHP with `__('text', 'repair-booking-form')` / `_e(...)` / `esc_html__(...)` — this touches `templates/form.php`, all of `admin/*.php`, and the visible strings inside `repair-booking-form.php`. This is genuinely hundreds of small edits.
4. For JS strings in `main.js` (form labels, validation messages, cart text), use `wp_set_script_translations()` + a generated `.json` translation file per locale, or pass a `wp_localize_script()` object with pre-translated strings if you don't want to deal with the JS i18n pipeline.
5. Generate the `.pot` template (via WP-CLI `wp i18n make-pot`) once all strings are wrapped, then produce `.po`/`.mo` for each target language.

**Practical recommendation:** since your immediate market is UAE, ship **English + Arabic** first (Arabic also needs RTL CSS — check `style.css` for hardcoded `left`/`right`/`margin-left` that will break in RTL; use logical CSS properties or an `rtl.css` override). Don't build a generic "supports any language" framework before you have two real languages working — full WPML/Polylang compatibility can come later once the core i18n wrapping is done, since WPML/Polylang both just read standard WP `__()`/`.po` infrastructure.

---

## Part 5 — Recommended Data Model (post-fix)

```
wp_rbf_brands        id, name, logo, status
wp_rbf_models        id, brand_id, name, series, image, status
wp_rbf_repairs       id, name, icon, description, duration, status   -- catalog of repair TYPES only, no price here
wp_rbf_pricing       id, model_id, repair_id, country_code (nullable), price, currency, updated_at
wp_rbf_default_prices  repair_id, price     -- fallback when no model-specific override exists
wp_rbf_bookings      (existing table — fine as is, just add payment_gateway/transaction_id if not already, which it looks like you already added via ALTER TABLE)
```
Retire `brands_models_data.json` as a live data source entirely once migrated — keep it only as a one-time **seed/import file** (your `ajax_import_prices`/CSV tooling is the right pattern for this, just point it at the real tables).

---

## Part 6 — Prioritized Task List

**Must Have (blocks any real launch — do these first, in this order):**
1. Fix `get_repairs_by_model()` to read real per-model prices from `wp_rbf_pricing` (Part 1.1)
2. Point `get_models_by_brand()` / `get_repairs_by_model()` at DB tables instead of JSON; delete the dead JSON-CRUD handlers (Part 1.2, 1.3)
3. Fix the 70 wrong device images (series-level reassignment script, Part 4.1 quick fix)
4. Remove debug files from the shipped plugin folder, strip `error_log()` calls from production paths (Part 2.3, 2.4)
5. Fix `sslverify => false` (Part 2.1)

**Should Have (needed before you can sell this commercially):**
6. Real Stripe payment integration (Part 4.3) — replace the stub functions
7. Country-aware pricing column + lookup logic (Part 4.2)
8. English + Arabic i18n wrapping + RTL CSS pass (Part 4.4)
9. Split `repair-booking-form.php` into the class structure in Part 3/5

**Nice to Have (post-launch):**
10. Additional payment gateways (Telr/PayTabs for KSA/Mada)
11. Additional languages beyond EN/AR
12. Licensed/real per-model device photography

**Do Not Build Yet:**
- Geo-IP based automatic country pricing (manual per-shop country setting is enough at your current scale)
- Full WPML/Polylang certified compatibility (do it once core i18n wrapping exists)
- Any "AI-generated device images" shortcut — will look worse than honest series-level generic images and creates its own copyright/likeness questions

---

## Part 7 — Next Step

Tell me which item from **Must Have** you want to start with and I'll write the actual production code for it (not pseudocode). Recommended order given everything above: **#1 (pricing lookup fix) → #2 (catalog source-of-truth fix) → #3 (image reassignment script) → #6 (real Stripe integration)**, since #1/#2 are the ones actively breaking your business logic today, and #3 is a one-hour script that immediately makes the product look credible again.
