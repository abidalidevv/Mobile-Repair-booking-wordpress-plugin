# Phase 2 Engineering Spec — Tiered Pricing, Catalog Consolidation, Device Images, Feature Gaps

**Purpose of this file:** feed this directly to your coding agent (Antigravity) pointed at the local repo folder `Mobile-Repair-booking-wordpress-plugin`. It assumes the agent has read access to the existing code and should read `repair-booking-form.php`, `includes/class-brands-models-manager.php`, `admin/models.php`, `admin/repairs.php`, and `brands_models_data.json` before making changes.

**Read this first:** a prior audit (`REPAIR-BOOKING-PLUGIN-AUDIT-AND-FINISH-PLAN.md`) already identified that the storefront reads brands/models/repairs from `brands_models_data.json` while the admin CRUD writes to `wp_rbf_brands` / `wp_rbf_models` / `wp_rbf_repairs` / `wp_rbf_pricing` tables — the two never connect. This spec assumes that fix is being done in parallel or first. Everything below targets the **DB-based system** as the single source of truth. Do not build any of this against the JSON file.

---

## Part 1 — The core problem: pricing combinatorics

Current catalog: 10 brands, 196 models, 40 repair types → **7,840 possible (model × repair) price combinations**. Setting each one by hand does not scale and is not how any real competitor does it (verified: RepairPlugin syncs prices from supplier cost + margin formula rather than manual per-model entry; RepairBuddy/RepairDesk group by device categories for base pricing, then allow exceptions).

**Solution: a 3-layer cascading price resolution, in order of specificity (most specific wins):**

```
1. Model-specific override     : (model_id, repair_id)               -> price     [sparse, exceptions only]
2. Country override on model   : (country_code, model_id, repair_id) -> price     [optional, sparse]
3. Tier base price             : (tier_id, repair_id)                -> price     [the main pricing surface]
4. Country override on tier    : (country_code, tier_id, repair_id)  -> price     [optional]
5. Global default              : (repair_id)                         -> price     [safety-net fallback, must always exist]
```

Resolution function pseudocode (implement as `RBF_Pricing::get_price($model_id, $repair_id, $country_code = null)`):
```
1. if country_code and exists override for (country_code, model_id, repair_id) -> return it
2. if exists override for (model_id, repair_id) -> return it
3. get tier_id = model.tier_id
4. if country_code and exists (country_code, tier_id, repair_id) -> return it
5. if exists (tier_id, repair_id) -> return it
6. return default_prices[repair_id]  // must never be null — validate on save that every repair has a default
```

This turns 7,840 combinations into: **(number of tiers × 40 repairs) base prices** you actually set, plus only the handful of true exceptions you choose to override. With 4 tiers that's 160 base prices — manageable on one screen.

---

## Part 2 — Database schema changes

Add to existing schema (extend, don't replace `wp_rbf_pricing` if it already has usable columns — check first, migrate data don't drop):

```sql
CREATE TABLE wp_rbf_device_tiers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,           -- e.g. "Economy", "Mid-Range", "Flagship", "Premium/Foldable"
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- add tier assignment to models
ALTER TABLE wp_rbf_models ADD COLUMN tier_id INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE wp_rbf_models ADD FOREIGN KEY (tier_id) REFERENCES wp_rbf_device_tiers(id);

-- tier-level base pricing (THE main pricing surface admin uses)
CREATE TABLE wp_rbf_tier_pricing (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tier_id INT UNSIGNED NOT NULL,
    repair_id INT UNSIGNED NOT NULL,
    country_code VARCHAR(2) NULL DEFAULT NULL,   -- NULL = applies to all countries
    price DECIMAL(10,2) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY tier_repair_country (tier_id, repair_id, country_code)
);

-- model-specific overrides (sparse — only exceptions, keep existing wp_rbf_pricing table for this if it already matches this shape)
-- wp_rbf_pricing(model_id, repair_id, country_code NULL, price) — add country_code column if missing:
ALTER TABLE wp_rbf_pricing ADD COLUMN country_code VARCHAR(2) NULL DEFAULT NULL AFTER repair_id;
ALTER TABLE wp_rbf_pricing ADD UNIQUE KEY model_repair_country (model_id, repair_id, country_code);

-- wp_rbf_default_prices already exists (repair_id -> price) — keep as final fallback, no changes needed
```

Seed 4 default tiers on plugin activation/upgrade: Economy, Mid-Range, Flagship, Premium/Foldable (`sort_order` 1-4). Shop owner can rename/add/remove tiers later from settings — don't hardcode tier names anywhere in logic, only reference `tier_id`.

---

## Part 3 — Auto-tagging models into tiers (reduce manual work from 196 clicks to a reviewable batch)

Write a one-time migration script `migrate-assign-tiers.php` that assigns a starting `tier_id` to every existing model using name-pattern heuristics, so the shop owner reviews/adjusts instead of assigning from zero:

```
Flagship tier    : name contains "Pro Max", "Ultra", "Fold", "Flip", "Pro" (Apple/Samsung/Pixel context)
Mid-Range tier   : name contains a numbered non-"Pro" flagship model (e.g. "iPhone 16", "Galaxy S24", "Pixel 9")
Economy tier     : name contains "SE", "Lite", "A0-A9 series" (Samsung A-series), "Redmi", base Xiaomi/Oppo lines
Premium/Foldable : name contains "Fold", "Flip" (can also just live inside Flagship — your call, keep it simple: 3 tiers might be enough, don't force 4 if 3 covers it)
```
This is a heuristic, not a guarantee — after running it, generate a CSV report (`model_name, brand, guessed_tier`) for the shop owner to eyeball and bulk-correct in one pass rather than one-by-one from a blank slate.

---

## Part 4 — Admin UI requirements

### 4.1 Tier Pricing Grid (new admin screen, replaces/extends current "Repair Prices" page)
- Rows = repair types (40), Columns = tiers (e.g. 4) → a spreadsheet-style grid, one input per cell, one "Save All" button (single AJAX call with the whole grid payload, not one request per cell).
- A "Country" dropdown above the grid to switch between global pricing and a specific country's override grid (only shows countries the admin has enabled, defaults to blank = global).
- This single screen is where 90% of pricing work happens.

### 4.2 Model List — Tier column + inline override
- Add a `Tier` dropdown column to the existing Models admin list (bulk-select + "Assign tier to selected" action for fast batch correction after the auto-tag script runs).
- Add a small "Custom price?" expandable row per model → only appears when the shop owner explicitly wants to override a specific repair price for that one model. Do not pre-render 40 empty override fields per model — that recreates the 7,840-cell problem visually even if most are unused. Make it an explicit "+ Add override" action that adds one repair+price row at a time.

### 4.3 Effective Price Preview
- On the model edit screen, show a **read-only computed table**: for each repair, show the price that would actually be charged right now and which rule produced it ("Tier default" / "Model override" / "Country override"). This is essential for the shop owner (and for you, debugging) to trust the cascade instead of guessing which rule won — this directly prevents a repeat of the Part-1-audit pricing bug where nobody could tell what price logic was actually live.

### 4.4 CSV Export/Import
- Adapt the existing `ajax_export_prices` / `ajax_import_prices` handlers to export the **tier pricing grid** (not per-model) as the primary CSV, plus a second optional CSV for the sparse override list. Two small files, not one giant 7,840-row spreadsheet.

---

## Part 5 — Device images (tier is NOT the same as image grouping — keep separate)

Pricing tier answers "what does it cost." Image grouping answers "what does it look like." Don't conflate them — a model's image should be driven by its `series` field (already present in `brands_models_data.json`/`wp_rbf_models` as `series`), not by price tier.

1. Write `fix-device-images.php`: group all models by `(brand_id, series)`, and for each group assign ONE correct representative image (the current data already has a mostly-correct image for most series — the bug was individual models within a series pointing at the wrong series' image; fix by re-pointing every model in a series to that series' verified-correct file).
2. Where no real image exists for a series at all, use a clean generic silhouette/icon per device category (phone / tablet / laptop) rather than a wrong photo of a different device — a generic-but-honest icon reads as more professional than a confidently wrong photo.
3. Do not attempt to source real per-model photography for all 196 models right now — out of scope for this phase. Flag it as a backlog item, not a blocker.
4. Legal constraint for the agent to respect: do not scrape images from manufacturer sites, competitor plugins, or search engines. Only use images already present in the repo's `Brands/` folder, or ask the user to supply properly licensed images before adding new ones.

---

## Part 6 — Competitor feature gap analysis (for context — do NOT build all of this now)

Checked current market leaders (RepairBuddy, RepairPlugin, RepairDesk) for what a "professional" repair booking plugin typically includes beyond a booking form:

| Feature | Competitors have it? | Recommendation for this plugin |
|---|---|---|
| Job/repair ticket with unique tracking code | Yes (all three) | You already have this (booking ID + tracker shortcode) — keep, no change needed |
| Technician assignment | Yes | **Should Have** — simple: add a `technician` dropdown/field to bookings admin, nothing fancier yet |
| Customer estimate approve/reject flow | Yes (RepairBuddy, RepairDesk) | **Nice to Have** — real value once repairs need post-inspection quotes (your "Others" brand flow already implies this need) |
| Parts inventory tied to jobs | Yes (RepairDesk especially) | **Do Not Build Yet** — full inventory management is a different product; skip until asked for specifically |
| QR code on printed tickets | Yes | **Nice to Have** — cheap to add (QR of booking ID) once invoice/print system is touched |
| SMS notifications | Yes (all) | **Should Have** — but only after WhatsApp flow (which you already have and which matters more in your market) is solid; SMS costs money per message via a gateway (Twilio etc.), budget for it |
| Customer self-service login portal | Yes (RepairBuddy, RepairDesk) | **Do Not Build Yet** — your public tracker shortcode (no login needed) already covers the core need at far less complexity |
| Multi-location / staff roles | Yes (enterprise tiers only) | **Do Not Build Yet** — irrelevant until a shop owner asks for it; don't build for a customer you don't have yet |
| Review/feedback request after job | Yes | **Nice to Have** — simple one-off WhatsApp/email message post-completion, not a full review system |
| Per-model/tiered pricing with cascading override | Yes (RepairPlugin syncs to supplier cost+margin) | **Must Have** — this is Part 1-4 of this document |

Do not let this table turn into scope creep. Ship the Must Haves from the Part 1 audit + the tiered pricing system above first. Revisit this table only after the plugin is live with a real shop.

---

## Part 7 — Implementation order for the agent

1. Create `wp_rbf_device_tiers` and `wp_rbf_tier_pricing` tables; add `tier_id` to `wp_rbf_models`; add `country_code` to `wp_rbf_pricing`. Write as a versioned upgrade routine (check current DB version option, run once, bump version) — do not run raw `CREATE TABLE`/`ALTER TABLE` unconditionally on every page load.
2. Seed default tiers (Economy, Mid-Range, Flagship) on upgrade.
3. Run the auto-tagging script, output the CSV report for manual review.
4. Build `RBF_Pricing::get_price($model_id, $repair_id, $country_code = null)` implementing the Part 1 cascade. Replace the current flat-price logic in `get_repairs_by_model()` with a call to this method — this connects to the Part-1-audit fix, don't do it twice.
5. Build the Tier Pricing Grid admin screen (Part 4.1).
6. Add tier column + bulk-assign to the Models list (Part 4.2).
7. Add the Effective Price Preview (Part 4.3) — build this before you consider pricing "done," it's your own debugging tool as much as the shop owner's.
8. Run the image-grouping fix script (Part 5).
9. Leave Part 6 items untouched for now.

Do not proceed to Part 2 of the original audit's "Should Have" list (real Stripe integration, i18n) until this pricing system is working end-to-end and verified with the Effective Price Preview showing correct results for at least 10 spot-checked model/repair combinations.
