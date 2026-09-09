import sys

file_path = r"c:\Users\Ali\Desktop\repair-booking-form\repair-booking-form.php"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

start_marker = "    /**\n     * Admin Repair Prices Page - Modern Tiered Pricing & Cascading Engine\n     */\n    public function admin_repair_prices() {"
end_marker = "    /**\n     * Admin About Page\n     */\n    public function admin_about() {"

start_pos = content.find(start_marker)
if start_pos == -1:
    # Try alternative
    start_marker = "    public function admin_repair_prices() {"
    start_pos = content.find(start_marker)
    # Find preceding docblock
    docblock_pos = content.rfind("    /**", 0, start_pos)
    if docblock_pos != -1:
        start_pos = docblock_pos

end_pos = content.find(end_marker)

print(f"Start pos: {start_pos}, End pos: {end_pos}")

if start_pos == -1 or end_pos == -1:
    print("Could not find start or end markers!")
    sys.exit(1)

new_code = '''    /**
     * Admin Repair Prices Page - Modern Tiered Pricing & Cascading Engine
     */
    public function admin_repair_prices() {
        global $wpdb;

        // Ensure database tables and catalog are initialized
        if (class_exists('RBF_Pricing')) {
            RBF_Pricing::get_instance()->create_tables();
        }
        if (class_exists('RBF_Catalog')) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_models");
            if (!$count || intval($count) === 0) {
                RBF_Catalog::get_instance()->sync_from_json_to_db();
            }
        }

        $pricing = class_exists('RBF_Pricing') ? RBF_Pricing::get_instance() : null;
        $catalog = class_exists('RBF_Catalog') ? RBF_Catalog::get_instance() : null;

        $tiers = $pricing ? $pricing->get_tiers() : array();
        $repairs = $catalog ? $catalog->get_all_repairs() : array();
        $brands = $catalog ? $catalog->get_all_brands() : array();
        $models = $catalog ? $catalog->get_all_models() : array();
        $grid = $pricing ? $pricing->get_tier_pricing_grid() : array();

        $pricing_table = $wpdb->prefix . 'rbf_pricing';
        $overrides = $wpdb->get_results(
            "SELECT p.*, b.name as brand_name, m.name as model_name, r.name as repair_name 
             FROM $pricing_table p 
             LEFT JOIN {$wpdb->prefix}rbf_brands b ON p.brand_id = b.id 
             LEFT JOIN {$wpdb->prefix}rbf_models m ON p.model_id = m.id 
             LEFT JOIN {$wpdb->prefix}rbf_repairs r ON p.repair_id = r.id 
             ORDER BY p.id DESC",
            ARRAY_A
        );

        $nonce = wp_create_nonce('rbf_admin_nonce');
        $currency = get_option('rbf_primary_currency', 'AED');
        ?>
        <div class="wrap rbf-pricing-admin-wrap">
            <!-- Header & Key Metrics -->
            <div class="rbf-pricing-header">
                <div class="rbf-header-info">
                    <h1 class="rbf-title"><span class="dashicons dashicons-money-alt"></span> Cascading Tier Pricing Engine</h1>
                    <p class="rbf-subtitle">
                        Solve <strong>7,840 combinations</strong> with just <strong>160 base cells</strong> (4 Tiers &times; 40 Repairs). 
                        Device models inherit their tier price automatically, with sparse per-model and country overrides when needed.
                    </p>
                </div>
                <div class="rbf-header-actions">
                    <button type="button" class="button button-secondary" id="rbf-btn-sync-catalog">
                        <span class="dashicons dashicons-update"></span> Sync Catalog from JSON
                    </button>
                    <button type="button" class="button button-primary" id="rbf-btn-save-grid">
                        <span class="dashicons dashicons-saved"></span> Save Tier Pricing Grid
                    </button>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="rbf-stats-bar">
                <div class="rbf-stat-card">
                    <div class="rbf-stat-number"><?php echo count($models); ?></div>
                    <div class="rbf-stat-label">Device Models</div>
                    <div class="rbf-stat-sub">Across <?php echo count($brands); ?> Brands</div>
                </div>
                <div class="rbf-stat-card">
                    <div class="rbf-stat-number"><?php echo count($tiers); ?></div>
                    <div class="rbf-stat-label">Price Tiers</div>
                    <div class="rbf-stat-sub">Economy to Premium</div>
                </div>
                <div class="rbf-stat-card">
                    <div class="rbf-stat-number"><?php echo count($repairs); ?></div>
                    <div class="rbf-stat-label">Repair Services</div>
                    <div class="rbf-stat-sub">Master Repair Catalog</div>
                </div>
                <div class="rbf-stat-card highlight">
                    <div class="rbf-stat-number">160 vs 7,840</div>
                    <div class="rbf-stat-label">Base Grid Cells</div>
                    <div class="rbf-stat-sub">98% Configuration Reduction</div>
                </div>
                <div class="rbf-stat-card">
                    <div class="rbf-stat-number" id="rbf-stat-overrides-count"><?php echo count($overrides); ?></div>
                    <div class="rbf-stat-label">Active Overrides</div>
                    <div class="rbf-stat-sub">Model / Country Exceptions</div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="rbf-nav-tabs">
                <button type="button" class="rbf-tab-btn active" data-tab="tab-grid">
                    <span class="dashicons dashicons-grid-view"></span> 1. Tier Pricing Grid (160 Base Prices)
                </button>
                <button type="button" class="rbf-tab-btn" data-tab="tab-models">
                    <span class="dashicons dashicons-smartphone"></span> 2. Device Model Tier Assignments (<?php echo count($models); ?>)
                </button>
                <button type="button" class="rbf-tab-btn" data-tab="tab-overrides">
                    <span class="dashicons dashicons-admin-settings"></span> 3. Specific Overrides (<?php echo count($overrides); ?>)
                </button>
                <button type="button" class="rbf-tab-btn" data-tab="tab-inspector">
                    <span class="dashicons dashicons-search"></span> 4. Cascading Price Inspector & Tester
                </button>
            </div>

            <!-- TAB 1: TIER PRICING GRID -->
            <div class="rbf-tab-panel active" id="tab-grid">
                <div class="rbf-panel-card">
                    <div class="rbf-card-header">
                        <div>
                            <h2>Tier-Based Pricing Matrix</h2>
                            <p>Set base prices for each tier. Any device assigned to a tier inherits these prices unless a model-level override exists.</p>
                        </div>
                        <div class="rbf-batch-tools">
                            <span class="rbf-batch-label">Quick Auto-Fill:</span>
                            <button type="button" class="button button-small" id="rbf-btn-calc-proportions" title="Automatically calculate Economy (70%), Flagship (135%), and Premium (165%) based on Mid-Range base">
                                Compute Proportions from Mid-Range
                            </button>
                        </div>
                    </div>

                    <div class="rbf-table-responsive">
                        <table class="rbf-pricing-table" id="rbf-tier-grid-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="min-width: 240px;">Repair Service</th>
                                    <?php foreach ($tiers as $t): ?>
                                        <th style="min-width: 150px; text-align: center;">
                                            <div class="rbf-tier-badge tier-<?php echo esc_attr($t['slug']); ?>">
                                                <?php echo esc_html($t['name']); ?>
                                            </div>
                                            <span class="rbf-th-sub"><?php echo esc_html($currency); ?></span>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($repairs)): ?>
                                    <tr>
                                        <td colspan="<?php echo 2 + count($tiers); ?>" style="text-align:center; padding: 30px;">
                                            No repair services found. Click "Sync Catalog from JSON" to populate repairs.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($repairs as $idx => $r): ?>
                                        <tr data-repair-id="<?php echo esc_attr($r['id']); ?>">
                                            <td style="text-align: center; color: #888;"><?php echo ($idx + 1); ?></td>
                                            <td class="rbf-repair-title-cell">
                                                <span class="dashicons <?php echo !empty($r['icon']) ? esc_attr($r['icon']) : 'dashicons-hammer'; ?>"></span>
                                                <strong><?php echo esc_html($r['name']); ?></strong>
                                            </td>
                                            <?php foreach ($tiers as $t): 
                                                $val = isset($grid[$t['id']][$r['id']]) ? floatval($grid[$t['id']][$r['id']]) : 0.00;
                                            ?>
                                                <td style="text-align: center;">
                                                    <div class="rbf-input-wrap">
                                                        <input type="number" 
                                                               class="rbf-grid-input" 
                                                               step="0.01" 
                                                               min="0" 
                                                               data-tier-id="<?php echo esc_attr($t['id']); ?>" 
                                                               data-tier-slug="<?php echo esc_attr($t['slug']); ?>" 
                                                               data-repair-id="<?php echo esc_attr($r['id']); ?>" 
                                                               value="<?php echo number_format($val, 2, '.', ''); ?>" 
                                                               placeholder="0.00">
                                                    </div>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="rbf-card-footer">
                        <span id="rbf-grid-save-status"></span>
                        <button type="button" class="button button-primary button-large" id="rbf-btn-save-grid-bottom">
                            <span class="dashicons dashicons-saved"></span> Save All Tier Prices
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 2: MODEL TIER ASSIGNMENTS -->
            <div class="rbf-tab-panel" id="tab-models">
                <div class="rbf-panel-card">
                    <div class="rbf-card-header">
                        <div>
                            <h2>Device Model Tier Assignments</h2>
                            <p>Assign each model to a Price Tier. Models automatically inherit repair prices from their assigned tier.</p>
                        </div>
                        <div class="rbf-filter-tools">
                            <input type="text" id="rbf-model-search" placeholder="Search model name..." class="regular-text">
                            <select id="rbf-model-brand-filter">
                                <option value="">All Brands (<?php echo count($brands); ?>)</option>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?php echo esc_attr($b['name']); ?>"><?php echo esc_html($b['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="rbf-model-tier-filter">
                                <option value="">All Tiers</option>
                                <?php foreach ($tiers as $t): ?>
                                    <option value="<?php echo esc_attr($t['id']); ?>"><?php echo esc_html($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="rbf-table-responsive">
                        <table class="rbf-pricing-table" id="rbf-models-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Image</th>
                                    <th>Brand</th>
                                    <th>Model Name</th>
                                    <th>Assigned Tier</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($models as $m): 
                                    $img_src = !empty($m['image']) ? $m['image'] : (RBF_PLUGIN_URL . 'Brands/other_brand.jpg');
                                ?>
                                    <tr data-brand="<?php echo esc_attr($m['brand_name']); ?>" data-tier-id="<?php echo esc_attr($m['tier_id'] ?? ''); ?>">
                                        <td style="text-align: center;">
                                            <img src="<?php echo esc_url($img_src); ?>" alt="<?php echo esc_attr($m['name']); ?>" class="rbf-model-thumb" onerror="this.src='<?php echo esc_url(RBF_PLUGIN_URL . 'Brands/other_brand.jpg'); ?>'">
                                        </td>
                                        <td><strong><?php echo esc_html($m['brand_name']); ?></strong></td>
                                        <td class="rbf-model-name-cell"><?php echo esc_html($m['name']); ?></td>
                                        <td>
                                            <select class="rbf-model-tier-select" data-model-id="<?php echo esc_attr($m['id']); ?>">
                                                <option value="">-- Select Tier --</option>
                                                <?php foreach ($tiers as $t): ?>
                                                    <option value="<?php echo esc_attr($t['id']); ?>" <?php selected($m['tier_id'], $t['id']); ?>>
                                                        <?php echo esc_html($t['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="rbf-tier-save-indicator" id="tier-indicator-<?php echo esc_attr($m['id']); ?>"></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="rbf-badge-active">Active</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: PRICE OVERRIDES & EXCEPTIONS -->
            <div class="rbf-tab-panel" id="tab-overrides">
                <div class="rbf-panel-card">
                    <div class="rbf-card-header">
                        <div>
                            <h2>Per-Model & Country Overrides (Exceptions)</h2>
                            <p>Only use this when a specific model or country needs a unique price that deviates from its Tier Base.</p>
                        </div>
                    </div>

                    <!-- Add Override Form -->
                    <div class="rbf-add-override-box">
                        <h3>Add New Override</h3>
                        <div class="rbf-override-form-grid">
                            <div>
                                <label>Brand:</label>
                                <select id="rbf-override-brand" class="widefat">
                                    <option value="">Select Brand</option>
                                    <?php foreach ($brands as $b): ?>
                                        <option value="<?php echo esc_attr($b['name']); ?>"><?php echo esc_html($b['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Model:</label>
                                <select id="rbf-override-model" class="widefat" disabled>
                                    <option value="">Select Brand First</option>
                                </select>
                            </div>
                            <div>
                                <label>Repair Service:</label>
                                <select id="rbf-override-repair" class="widefat">
                                    <option value="">Select Repair</option>
                                    <?php foreach ($repairs as $r): ?>
                                        <option value="<?php echo esc_attr($r['id']); ?>"><?php echo esc_html($r['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Country Code (Optional):</label>
                                <input type="text" id="rbf-override-country" placeholder="Global (Leave empty) or AE, SA, US" class="widefat" maxlength="5">
                            </div>
                            <div>
                                <label>Custom Price (<?php echo esc_html($currency); ?>):</label>
                                <input type="number" id="rbf-override-price" step="0.01" min="0" placeholder="0.00" class="widefat">
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" class="button button-primary widefat" id="rbf-btn-add-override">
                                    <span class="dashicons dashicons-plus-alt"></span> Save Override
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Overrides Table -->
                    <div class="rbf-table-responsive" style="margin-top: 20px;">
                        <table class="rbf-pricing-table" id="rbf-overrides-table">
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>Repair</th>
                                    <th>Country</th>
                                    <th>Override Price</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($overrides)): ?>
                                    <tr id="rbf-no-overrides-row">
                                        <td colspan="6" style="text-align: center; padding: 25px; color: #888;">
                                            No overrides configured. All models currently inherit clean Tier Base prices!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($overrides as $ov): ?>
                                        <tr data-model-id="<?php echo esc_attr($ov['model_id']); ?>" data-repair-id="<?php echo esc_attr($ov['repair_id']); ?>" data-country="<?php echo esc_attr($ov['country_code'] ?? ''); ?>">
                                            <td><strong><?php echo esc_html($ov['brand_name'] ?? 'N/A'); ?></strong></td>
                                            <td><?php echo esc_html($ov['model_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo esc_html($ov['repair_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (!empty($ov['country_code'])): ?>
                                                    <span class="rbf-country-tag"><?php echo esc_html($ov['country_code']); ?></span>
                                                <?php else: ?>
                                                    <span class="rbf-global-tag">Global</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo esc_html($currency) . ' ' . number_format($ov['price'], 2); ?></strong></td>
                                            <td style="text-align: center;">
                                                <button type="button" class="button button-link-delete rbf-btn-delete-override" data-model-id="<?php echo esc_attr($ov['model_id']); ?>" data-repair-id="<?php echo esc_attr($ov['repair_id']); ?>" data-country="<?php echo esc_attr($ov['country_code'] ?? ''); ?>">
                                                    <span class="dashicons dashicons-trash"></span> Remove
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 4: CASCADING PRICE INSPECTOR -->
            <div class="rbf-tab-panel" id="tab-inspector">
                <div class="rbf-panel-card">
                    <div class="rbf-card-header">
                        <div>
                            <h2>Real-Time Cascading Price Inspector</h2>
                            <p>Verify exactly what a customer will see on the front-end form, with complete waterfall trace explaining the exact inheritance layer.</p>
                        </div>
                    </div>

                    <div class="rbf-inspector-controls">
                        <div class="rbf-inspector-inputs">
                            <div>
                                <label>Brand:</label>
                                <select id="rbf-insp-brand" class="widefat">
                                    <option value="">Select Brand</option>
                                    <?php foreach ($brands as $b): ?>
                                        <option value="<?php echo esc_attr($b['name']); ?>"><?php echo esc_html($b['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Model:</label>
                                <select id="rbf-insp-model" class="widefat" disabled>
                                    <option value="">Select Brand First</option>
                                </select>
                            </div>
                            <div>
                                <label>Country Code (Optional):</label>
                                <input type="text" id="rbf-insp-country" placeholder="e.g. AE or leave empty for Global" class="widefat" maxlength="5">
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" class="button button-primary button-large widefat" id="rbf-btn-inspect">
                                    <span class="dashicons dashicons-search"></span> Inspect All 40 Repairs
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Inspector Results Section -->
                    <div id="rbf-inspector-results" style="display: none; margin-top: 30px;">
                        <h3 id="rbf-inspector-device-title" style="margin-bottom: 15px; color: #017c36;"></h3>
                        <div class="rbf-table-responsive">
                            <table class="rbf-pricing-table">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Repair Service</th>
                                        <th>Customer Price (<?php echo esc_html($currency); ?>)</th>
                                        <th>Resolution Source</th>
                                        <th>Active Hierarchy Rule</th>
                                    </tr>
                                </thead>
                                <tbody id="rbf-inspector-rows">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .rbf-pricing-admin-wrap {
            margin: 20px 20px 0 2px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        .rbf-pricing-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 24px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            margin-bottom: 20px;
            border-left: 6px solid #017c36;
        }
        .rbf-title {
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 700;
            color: #1d2327;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rbf-title .dashicons {
            font-size: 28px;
            width: 28px;
            height: 28px;
            color: #017c36;
        }
        .rbf-subtitle {
            margin: 0;
            color: #646970;
            font-size: 14px;
            max-width: 800px;
            line-height: 1.5;
        }
        .rbf-header-actions {
            display: flex;
            gap: 12px;
        }
        .rbf-stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .rbf-stat-card {
            background: #fff;
            padding: 18px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            border: 1px solid #e2e4e7;
            text-align: center;
        }
        .rbf-stat-card.highlight {
            background: linear-gradient(135deg, #017c36 0%, #05413a 100%);
            color: #fff;
            border: none;
        }
        .rbf-stat-card.highlight .rbf-stat-number,
        .rbf-stat-card.highlight .rbf-stat-label,
        .rbf-stat-card.highlight .rbf-stat-sub {
            color: #fff;
        }
        .rbf-stat-number {
            font-size: 26px;
            font-weight: 800;
            color: #017c36;
            line-height: 1.2;
        }
        .rbf-stat-label {
            font-size: 13px;
            font-weight: 600;
            color: #2c3338;
            margin-top: 4px;
        }
        .rbf-stat-sub {
            font-size: 11px;
            color: #8c8f94;
            margin-top: 2px;
        }
        .rbf-nav-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e4e7;
            margin-bottom: 20px;
        }
        .rbf-tab-btn {
            background: transparent;
            border: none;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #646970;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .rbf-tab-btn:hover {
            color: #017c36;
        }
        .rbf-tab-btn.active {
            color: #017c36;
            border-bottom-color: #017c36;
            background: #fff;
            border-radius: 8px 8px 0 0;
        }
        .rbf-tab-panel {
            display: none;
        }
        .rbf-tab-panel.active {
            display: block;
        }
        .rbf-panel-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #e2e4e7;
            padding: 24px 30px;
        }
        .rbf-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .rbf-card-header h2 {
            margin: 0 0 4px 0;
            font-size: 18px;
            color: #1d2327;
        }
        .rbf-card-header p {
            margin: 0;
            color: #646970;
            font-size: 13px;
        }
        .rbf-batch-tools {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .rbf-batch-label {
            font-size: 12px;
            font-weight: 600;
            color: #646970;
        }
        .rbf-filter-tools {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .rbf-table-responsive {
            overflow-x: auto;
            max-height: 650px;
            border: 1px solid #e2e4e7;
            border-radius: 8px;
        }
        .rbf-pricing-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            font-size: 13px;
        }
        .rbf-pricing-table th {
            position: sticky;
            top: 0;
            background: #f6f7f7;
            padding: 12px 14px;
            font-weight: 600;
            color: #2c3338;
            border-bottom: 2px solid #dcdcde;
            z-index: 2;
        }
        .rbf-pricing-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f0f0f1;
            vertical-align: middle;
        }
        .rbf-pricing-table tr:hover td {
            background-color: #f9fbf9;
        }
        .rbf-tier-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .tier-economy { background: #e6f4ea; color: #137333; }
        .tier-mid-range { background: #e8f0fe; color: #1a73e8; }
        .tier-flagship { background: #fef7e0; color: #b06000; }
        .tier-premium-foldable { background: #fce8e6; color: #c5221f; }
        .rbf-th-sub {
            display: block;
            font-size: 11px;
            color: #8c8f94;
            margin-top: 2px;
        }
        .rbf-repair-title-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rbf-repair-title-cell .dashicons {
            color: #017c36;
        }
        .rbf-grid-input {
            width: 110px;
            text-align: right;
            padding: 6px 10px;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            font-weight: 600;
            color: #1d2327;
        }
        .rbf-grid-input:focus {
            border-color: #017c36;
            box-shadow: 0 0 0 1px #017c36;
            outline: none;
        }
        .rbf-card-footer {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
        }
        .rbf-model-thumb {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 4px;
            border: 1px solid #eee;
            background: #fff;
        }
        .rbf-model-tier-select {
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #c3c4c7;
            font-size: 13px;
            font-weight: 600;
        }
        .rbf-badge-active {
            background: #e6f4ea;
            color: #137333;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .rbf-add-override-box {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e4e7;
        }
        .rbf-add-override-box h3 {
            margin: 0 0 15px 0;
            font-size: 15px;
            color: #1d2327;
        }
        .rbf-override-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .rbf-country-tag {
            background: #e8f0fe;
            color: #1a73e8;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 11px;
        }
        .rbf-global-tag {
            background: #f0f0f1;
            color: #646970;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
        }
        .rbf-source-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }
        .source-tier-base-price { background: #e8f0fe; color: #1a73e8; }
        .source-model-override { background: #fef7e0; color: #b06000; }
        .source-model-country-override { background: #fce8e6; color: #c5221f; }
        .source-tier-country-override { background: #f3e8fd; color: #7627bb; }
        .source-global-default { background: #f0f0f1; color: #646970; }
        .rbf-inspector-controls {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e4e7;
        }
        .rbf-inspector-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js($nonce); ?>';
            var currency = '<?php echo esc_js($currency); ?>';

            // Toast helper
            function showToast(msg, isSuccess) {
                var color = isSuccess ? '#017c36' : '#d63638';
                var $t = $('<div style="position:fixed; bottom:25px; right:25px; background:' + color + '; color:#fff; padding:12px 24px; border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,0.25); z-index:99999; font-weight:600; font-size:14px;">' + msg + '</div>');
                $('body').append($t);
                setTimeout(function() {
                    $t.fadeOut(400, function() { $(this).remove(); });
                }, 3000);
            }

            // Tab Switching
            $('.rbf-tab-btn').on('click', function() {
                var tabId = $(this).data('tab');
                $('.rbf-tab-btn').removeClass('active');
                $('.rbf-tab-panel').removeClass('active');
                $(this).addClass('active');
                $('#' + tabId).addClass('active');
            });

            // Save Tier Grid
            $('#rbf-btn-save-grid, #rbf-btn-save-grid-bottom').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Saving Grid...');
                var grid = {};

                $('.rbf-grid-input').each(function() {
                    var tierId = $(this).data('tier-id');
                    var repairId = $(this).data('repair-id');
                    var val = parseFloat($(this).val()) || 0;

                    if (!grid[tierId]) {
                        grid[tierId] = {};
                    }
                    grid[tierId][repairId] = val;
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_save_tier_pricing_grid',
                        nonce: nonce,
                        grid: grid
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Tier Pricing Grid');
                        if (resp.success) {
                            showToast(resp.data || 'Tier pricing grid saved successfully!', true);
                        } else {
                            showToast('Error: ' + (resp.data || 'Failed to save'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Tier Pricing Grid');
                        showToast('Network error while saving grid', false);
                    }
                });
            });

            // Compute Proportions from Mid-Range: Economy=0.7x, Flagship=1.35x, Premium=1.65x
            $('#rbf-btn-calc-proportions').on('click', function() {
                if (!confirm('Auto-calculate Economy (70%), Flagship (135%), and Premium (165%) prices from Mid-Range values in the table?')) {
                    return;
                }
                $('#rbf-tier-grid-table tbody tr').each(function() {
                    var $midInput = $(this).find('input[data-tier-slug="mid-range"]');
                    var midVal = parseFloat($midInput.val()) || 0;
                    if (midVal > 0) {
                        var $eco = $(this).find('input[data-tier-slug="economy"]');
                        var $flag = $(this).find('input[data-tier-slug="flagship"]');
                        var $prem = $(this).find('input[data-tier-slug="premium-foldable"]');

                        $eco.val((midVal * 0.70).toFixed(2));
                        $flag.val((midVal * 1.35).toFixed(2));
                        $prem.val((midVal * 1.65).toFixed(2));
                    }
                });
                showToast('Proportions calculated. Click "Save Tier Pricing Grid" to persist.', true);
            });

            // Update Single Model Tier Inline
            $('.rbf-model-tier-select').on('change', function() {
                var modelId = $(this).data('model-id');
                var tierId = $(this).val();
                var $ind = $('#tier-indicator-' + modelId);

                if (!tierId) return;

                $ind.html('<span style="color:#646970;">Saving...</span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_update_model_tier',
                        nonce: nonce,
                        model_id: modelId,
                        tier_id: tierId
                    },
                    success: function(resp) {
                        if (resp.success) {
                            $ind.html('<span style="color:#017c36;">&#10004;</span>');
                            setTimeout(function() { $ind.empty(); }, 2000);
                        } else {
                            $ind.html('<span style="color:#d63638;">&#10008;</span>');
                        }
                    },
                    error: function() {
                        $ind.html('<span style="color:#d63638;">&#10008;</span>');
                    }
                });
            });

            // Search and Filter Models Table
            function filterModels() {
                var q = $('#rbf-model-search').val().toLowerCase();
                var brand = $('#rbf-model-brand-filter').val();
                var tier = $('#rbf-model-tier-filter').val();

                $('#rbf-models-table tbody tr').each(function() {
                    var mName = $(this).find('.rbf-model-name-cell').text().toLowerCase();
                    var mBrand = $(this).data('brand');
                    var mTier = $(this).find('.rbf-model-tier-select').val();

                    var matchesSearch = !q || mName.indexOf(q) !== -1;
                    var matchesBrand = !brand || mBrand === brand;
                    var matchesTier = !tier || mTier === tier;

                    if (matchesSearch && matchesBrand && matchesTier) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
            $('#rbf-model-search').on('input', filterModels);
            $('#rbf-model-brand-filter, #rbf-model-tier-filter').on('change', filterModels);

            // Sync Catalog from JSON
            $('#rbf-btn-sync-catalog').on('click', function() {
                if (!confirm('Synchronize brands, 196 models, and 40 repairs from brands_models_data.json into the DB tables?')) {
                    return;
                }
                var $btn = $(this);
                $btn.prop('disabled', true).text('Syncing Catalog...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_sync_catalog_db',
                        nonce: nonce
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync Catalog from JSON');
                        if (resp.success) {
                            showToast(resp.data || 'Catalog synced successfully! Reloading...', true);
                            setTimeout(function() { location.reload(); }, 1200);
                        } else {
                            showToast('Sync error: ' + (resp.data || 'Failed'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync Catalog from JSON');
                        showToast('Network error during sync', false);
                    }
                });
            });

            // Cascading Brand -> Model Dropdown Helper
            function loadModelsForSelect(brandName, $targetSelect) {
                $targetSelect.empty().append('<option value="">Loading models...</option>').prop('disabled', true);
                if (!brandName) {
                    $targetSelect.empty().append('<option value="">Select Brand First</option>');
                    return;
                }
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_get_models',
                        brand: brandName
                    },
                    success: function(resp) {
                        $targetSelect.empty().append('<option value="">Select Model</option>');
                        if (resp.success && resp.data && resp.data.length > 0) {
                            $.each(resp.data, function(idx, m) {
                                $targetSelect.append('<option value="' + m.id + '">' + m.name + '</option>');
                            });
                            $targetSelect.prop('disabled', false);
                        } else {
                            $targetSelect.append('<option value="">No models found</option>');
                        }
                    }
                });
            }

            $('#rbf-override-brand').on('change', function() {
                loadModelsForSelect($(this).val(), $('#rbf-override-model'));
            });

            $('#rbf-insp-brand').on('change', function() {
                loadModelsForSelect($(this).val(), $('#rbf-insp-model'));
            });

            // Add Model Override
            $('#rbf-btn-add-override').on('click', function() {
                var modelId = $('#rbf-override-model').val();
                var repairId = $('#rbf-override-repair').val();
                var country = $('#rbf-override-country').val().trim();
                var price = parseFloat($('#rbf-override-price').val());

                if (!modelId || !repairId || isNaN(price)) {
                    showToast('Please select Model, Repair, and enter a valid Price', false);
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('Saving...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_save_model_price_override',
                        nonce: nonce,
                        model_id: modelId,
                        repair_id: repairId,
                        country_code: country,
                        price: price
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Save Override');
                        if (resp.success) {
                            showToast(resp.data || 'Override saved successfully!', true);
                            setTimeout(function() { location.reload(); }, 1000);
                        } else {
                            showToast('Error: ' + (resp.data || 'Failed'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Save Override');
                        showToast('Network error while saving override', false);
                    }
                });
            });

            // Delete Override
            $(document).on('click', '.rbf-btn-delete-override', function() {
                if (!confirm('Remove this custom price override? The model will fall back to its Tier Base price.')) {
                    return;
                }
                var $row = $(this).closest('tr');
                var modelId = $(this).data('model-id');
                var repairId = $(this).data('repair-id');
                var country = $(this).data('country');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_delete_model_price_override',
                        nonce: nonce,
                        model_id: modelId,
                        repair_id: repairId,
                        country_code: country
                    },
                    success: function(resp) {
                        if (resp.success) {
                            $row.fadeOut(300, function() { $(this).remove(); });
                            showToast('Override removed!', true);
                        } else {
                            showToast('Error: ' + (resp.data || 'Failed'), false);
                        }
                    },
                    error: function() {
                        showToast('Network error', false);
                    }
                });
            });

            // Inspect Effective Price
            $('#rbf-btn-inspect').on('click', function() {
                var modelId = $('#rbf-insp-model').val();
                var country = $('#rbf-insp-country').val().trim();
                var modelText = $('#rbf-insp-model option:selected').text();

                if (!modelId) {
                    showToast('Please select a device model to inspect', false);
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('Inspecting...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_get_model_price_preview',
                        nonce: nonce,
                        model_id: modelId,
                        country_code: country
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Inspect All 40 Repairs');
                        if (resp.success && resp.data) {
                            $('#rbf-inspector-device-title').html('Inspection Results for: <strong>' + modelText + '</strong>' + (country ? ' (' + country + ')' : ' (Global)'));
                            var $tbody = $('#rbf-inspector-rows').empty();

                            $.each(resp.data, function(idx, item) {
                                var slug = (item.source || '').toLowerCase().replace(/[^a-z0-9]+/g, '-');
                                var rowHtml = '<tr>' +
                                    '<td style="text-align:center; color:#888;">' + (idx + 1) + '</td>' +
                                    '<td><span class="dashicons ' + (item.icon || 'dashicons-hammer') + '" style="color:#017c36; margin-right:6px;"></span><strong>' + item.repair_name + '</strong></td>' +
                                    '<td><strong style="color:#017c36; font-size:14px;">' + currency + ' ' + parseFloat(item.price).toFixed(2) + '</strong></td>' +
                                    '<td><span class="rbf-source-badge source-' + slug + '">' + item.source + '</span></td>' +
                                    '<td><code>' + item.rule + '</code></td>' +
                                '</tr>';
                                $tbody.append(rowHtml);
                            });

                            $('#rbf-inspector-results').fadeIn(300);
                        } else {
                            showToast('Failed to inspect: ' + (resp.data || 'Unknown error'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Inspect All 40 Repairs');
                        showToast('Network error during inspection', false);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
'''

updated_content = content[:start_pos] + new_code + content[end_pos:]

with open(file_path, "w", encoding="utf-8") as f:
    f.write(updated_content)

print("Successfully replaced admin_repair_prices in repair-booking-form.php!")
