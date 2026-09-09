<?php
/**
 * Unified Pricing Engine for EFIX Repair Booking Form
 *
 * Implements 5-layer cascading price resolution:
 * 1. Manual Model Override (Sparse - Highest Priority, never overwritten by sync)
 * 2. Verified Supplier Price + Labour + Global Markup (LXCELL / B2B Feeds)
 * 3. Tier + Country / Tier Base Price (Matrix: 4 Tiers x 40 Repairs)
 * 4. Global Default Price (Safety fallback per repair)
 * 5. Base Catalog Fallback (Master repair catalog)
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Pricing {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Tier Pricing AJAX
        add_action('wp_ajax_rbf_save_tier_pricing_grid', array($this, 'ajax_save_tier_pricing_grid'));
        add_action('wp_ajax_rbf_save_model_price_override', array($this, 'ajax_save_model_price_override'));
        add_action('wp_ajax_rbf_delete_model_price_override', array($this, 'ajax_delete_model_price_override'));
        add_action('wp_ajax_rbf_get_model_price_preview', array($this, 'ajax_get_model_price_preview'));

        // Pricing Settings & Labour Costs AJAX
        add_action('wp_ajax_rbf_save_pricing_settings', array($this, 'ajax_save_pricing_settings'));
        add_action('wp_ajax_rbf_save_supplier_mapping', array($this, 'ajax_save_supplier_mapping'));
        add_action('wp_ajax_rbf_delete_supplier_mapping', array($this, 'ajax_delete_supplier_mapping'));
    }

    /**
     * Create tables and schema updates for tiered & supplier pricing
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tiers_table            = $wpdb->prefix . 'rbf_device_tiers';
        $tier_pricing_table     = $wpdb->prefix . 'rbf_tier_pricing';
        $models_table           = $wpdb->prefix . 'rbf_models';
        $repairs_table          = $wpdb->prefix . 'rbf_repairs';
        $pricing_table          = $wpdb->prefix . 'rbf_pricing';
        $default_prices_table   = $wpdb->prefix . 'rbf_default_prices';
        $mappings_table         = $wpdb->prefix . 'rbf_supplier_mappings';
        $supplier_prices_table  = $wpdb->prefix . 'rbf_supplier_prices';
        $sync_logs_table        = $wpdb->prefix . 'rbf_supplier_sync_logs';

        // 1. Device Tiers Table
        $sql_tiers = "CREATE TABLE $tiers_table (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            slug VARCHAR(50) NOT NULL,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        // 2. Tier-Level Base Pricing Table
        $sql_tier_pricing = "CREATE TABLE $tier_pricing_table (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tier_id INT UNSIGNED NOT NULL,
            repair_id INT UNSIGNED NOT NULL,
            country_code VARCHAR(5) NULL DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tier_repair_country (tier_id, repair_id, country_code),
            KEY tier_id (tier_id),
            KEY repair_id (repair_id)
        ) $charset_collate;";

        // 3. Default Prices Table (Fallback)
        $sql_default_prices = "CREATE TABLE $default_prices_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            repair_id mediumint(9) NOT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY repair_id (repair_id)
        ) $charset_collate;";

        // 4. Supplier Mappings Table
        $sql_mappings = "CREATE TABLE $mappings_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_id VARCHAR(50) NOT NULL,
            model_id INT UNSIGNED NOT NULL,
            repair_id INT UNSIGNED NOT NULL,
            supplier_sku VARCHAR(100) NOT NULL,
            supplier_product_url VARCHAR(255) NULL,
            match_status VARCHAR(30) NOT NULL DEFAULT 'verified',
            last_verified DATETIME NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY supplier_model_repair (supplier_id, model_id, repair_id),
            KEY supplier_sku (supplier_sku),
            KEY model_repair (model_id, repair_id)
        ) $charset_collate;";

        // 5. Supplier Prices Cache Table
        $sql_supplier_prices = "CREATE TABLE $supplier_prices_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_id VARCHAR(50) NOT NULL,
            supplier_sku VARCHAR(100) NOT NULL,
            model_id INT UNSIGNED NULL,
            repair_id INT UNSIGNED NULL,
            supplier_product_url VARCHAR(255) NULL,
            product_name VARCHAR(255) NOT NULL,
            part_type VARCHAR(100) NULL,
            quality_grade VARCHAR(100) NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            current_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            previous_price DECIMAL(10,2) NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'AED',
            stock_status VARCHAR(50) NOT NULL DEFAULT 'in_stock',
            verification_status VARCHAR(30) NOT NULL DEFAULT 'verified',
            last_price_change_at DATETIME NULL,
            raw_data LONGTEXT NULL,
            fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY supplier_sku_unique (supplier_id, supplier_sku),
            KEY model_repair (model_id, repair_id)
        ) $charset_collate;";

        // 6. Supplier Sync Logs Table
        $sql_sync_logs = "CREATE TABLE $sync_logs_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            supplier_id VARCHAR(50) NOT NULL,
            sync_start DATETIME NOT NULL,
            sync_finish DATETIME NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'running',
            products_checked INT NOT NULL DEFAULT 0,
            prices_changed INT NOT NULL DEFAULT 0,
            unchanged INT NOT NULL DEFAULT 0,
            needs_review INT NOT NULL DEFAULT 0,
            failed INT NOT NULL DEFAULT 0,
            duration_seconds FLOAT NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            PRIMARY KEY (id),
            KEY supplier_sync (supplier_id, sync_start)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_tiers);
        dbDelta($sql_tier_pricing);
        dbDelta($sql_default_prices);
        dbDelta($sql_mappings);
        dbDelta($sql_supplier_prices);
        dbDelta($sql_sync_logs);

        // 7. Ensure tier_id exists on wp_rbf_models
        $models_col = $wpdb->get_results("SHOW COLUMNS FROM $models_table LIKE 'tier_id'");
        if (empty($models_col)) {
            $wpdb->query("ALTER TABLE $models_table ADD COLUMN tier_id INT UNSIGNED NULL DEFAULT NULL AFTER brand_id");
            $wpdb->query("ALTER TABLE $models_table ADD KEY tier_id (tier_id)");
        }

        // 8. Ensure country_code, is_manual_override, and source exist on wp_rbf_pricing
        $pricing_col = $wpdb->get_results("SHOW COLUMNS FROM $pricing_table LIKE 'country_code'");
        if (empty($pricing_col)) {
            $wpdb->query("ALTER TABLE $pricing_table ADD COLUMN country_code VARCHAR(5) NULL DEFAULT NULL AFTER repair_id");
        }
        $manual_col = $wpdb->get_results("SHOW COLUMNS FROM $pricing_table LIKE 'is_manual_override'");
        if (empty($manual_col)) {
            $wpdb->query("ALTER TABLE $pricing_table ADD COLUMN is_manual_override TINYINT(1) NOT NULL DEFAULT 0 AFTER price");
        } else {
            // Ensure default is 0 for legacy and new rows
            $wpdb->query("ALTER TABLE $pricing_table ALTER COLUMN is_manual_override SET DEFAULT 0");
        }
        $source_col = $wpdb->get_results("SHOW COLUMNS FROM $pricing_table LIKE 'source'");
        if (empty($source_col)) {
            $wpdb->query("ALTER TABLE $pricing_table ADD COLUMN source VARCHAR(50) NOT NULL DEFAULT 'legacy' AFTER is_manual_override");
        }

        // Migration: Safe legacy & historical manual-edited preservation
        // Checks if previous schema had `is_admin_edited` column.
        if (!get_option('rbf_manual_override_migrated_v3')) {
            $admin_edited_col = $wpdb->get_results("SHOW COLUMNS FROM $pricing_table LIKE 'is_admin_edited'");
            if (!empty($admin_edited_col)) {
                // Preserve historical admin edits: migrate to is_manual_override = 1, source = 'manual'
                $wpdb->query("UPDATE $pricing_table SET is_manual_override = 1, source = 'manual' WHERE is_admin_edited = 1");
                // All other historical rows remain non-override legacy
                $wpdb->query("UPDATE $pricing_table SET is_manual_override = 0, source = 'legacy' WHERE (is_admin_edited = 0 OR is_admin_edited IS NULL) AND source != 'manual'");
            } else {
                // If no is_admin_edited column existed, keep explicitly edited rows and set unedited legacy rows
                $wpdb->query("UPDATE $pricing_table SET is_manual_override = 0, source = 'legacy' WHERE source != 'manual' OR source IS NULL");
            }
            update_option('rbf_manual_override_migrated_v3', 1);
        }

        // 9. Ensure labour_cost exists on wp_rbf_repairs
        $repairs_col = $wpdb->get_results("SHOW COLUMNS FROM $repairs_table LIKE 'labour_cost'");
        if (empty($repairs_col)) {
            $wpdb->query("ALTER TABLE $repairs_table ADD COLUMN labour_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price");
            $this->seed_default_labour_costs();
        }

        // Seed default device tiers & options
        $this->seed_default_tiers();
        if (get_option('rbf_global_markup_percentage') === false) {
            update_option('rbf_global_markup_percentage', 13);
        }
    }

    /**
     * Seed initial device tiers
     */
    public function seed_default_tiers() {
        global $wpdb;
        $tiers_table = $wpdb->prefix . 'rbf_device_tiers';

        $default_tiers = array(
            array('name' => 'Economy',          'slug' => 'economy',          'sort_order' => 1),
            array('name' => 'Mid-Range',        'slug' => 'mid-range',        'sort_order' => 2),
            array('name' => 'Flagship',         'slug' => 'flagship',         'sort_order' => 3),
            array('name' => 'Premium/Foldable', 'slug' => 'premium-foldable', 'sort_order' => 4),
        );

        foreach ($default_tiers as $tier) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $tiers_table WHERE slug = %s",
                $tier['slug']
            ));
            if (!$exists) {
                $wpdb->insert($tiers_table, $tier);
            }
        }
    }

    /**
     * Seed sensible labour costs by repair type
     */
    public function seed_default_labour_costs() {
        global $wpdb;
        $repairs_table = $wpdb->prefix . 'rbf_repairs';
        $repairs = $wpdb->get_results("SELECT id, name, labour_cost FROM $repairs_table", ARRAY_A);

        foreach ($repairs as $r) {
            if (floatval($r['labour_cost']) > 0) continue;

            $name = strtolower($r['name']);
            $cost = 50.00; // Standard default labour

            if (strpos($name, 'screen') !== false || strpos($name, 'display') !== false || strpos($name, 'glass') !== false) {
                $cost = 80.00;
            } elseif (strpos($name, 'battery') !== false) {
                $cost = 50.00;
            } elseif (strpos($name, 'charging') !== false || strpos($name, 'port') !== false) {
                $cost = 70.00;
            } elseif (strpos($name, 'back') !== false || strpos($name, 'housing') !== false) {
                $cost = 75.00;
            } elseif (strpos($name, 'camera') !== false) {
                $cost = 60.00;
            } elseif (strpos($name, 'motherboard') !== false || strpos($name, 'water') !== false || strpos($name, 'logic') !== false) {
                $cost = 120.00;
            }

            $wpdb->update($repairs_table, array('labour_cost' => $cost), array('id' => $r['id']));
        }
    }

    /**
     * Get all active device tiers
     */
    public function get_tiers() {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_device_tiers';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY sort_order ASC, id ASC", ARRAY_A);
    }

    /**
     * Get global markup percentage setting
     */
    public function get_global_markup() {
        return floatval(get_option('rbf_global_markup_percentage', 13));
    }

    /**
     * Get labour cost for a specific repair ID
     */
    public function get_labour_cost($repair_id) {
        global $wpdb;
        $cost = $wpdb->get_var($wpdb->prepare(
            "SELECT labour_cost FROM {$wpdb->prefix}rbf_repairs WHERE id = %d LIMIT 1",
            intval($repair_id)
        ));
        return ($cost !== null) ? floatval($cost) : 50.00;
    }

    /**
     * Unified Cascading Price Resolution Engine
     *
     * 5-Layer Resolution Order:
     * 1. Manual Model Override (Sparse - Highest Priority, never overwritten)
     * 2. Verified Supplier Price + Labour + Global Markup (LXCELL / B2B feeds)
     * 3. Tier + Country / Tier Base Price (Matrix)
     * 4. Global Default Price (Per repair fallback)
     * 5. Master Repair Catalog Fallback
     *
     * @param int $model_id
     * @param int $repair_id
     * @param string|null $country_code ISO-2 country code e.g. 'AE'
     * @return float Resolved final customer price (in AED)
     */
    public function get_price($model_id, $repair_id, $country_code = null) {
        global $wpdb;

        $model_id = intval($model_id);
        $repair_id = intval($repair_id);
        $country_code = !empty($country_code) ? strtoupper(trim(sanitize_text_field($country_code))) : null;

        $pricing_table        = $wpdb->prefix . 'rbf_pricing';
        $tier_pricing_table   = $wpdb->prefix . 'rbf_tier_pricing';
        $default_prices_table = $wpdb->prefix . 'rbf_default_prices';
        $models_table         = $wpdb->prefix . 'rbf_models';
        $repairs_table        = $wpdb->prefix . 'rbf_repairs';
        $mappings_table       = $wpdb->prefix . 'rbf_supplier_mappings';
        $supplier_prices_table= $wpdb->prefix . 'rbf_supplier_prices';

        // -------------------------------------------------------------
        // LAYER 1: Explicit Manual Model Override (Highest Priority)
        // Only rows where an administrator explicitly set/saved the price (is_manual_override = 1)
        // -------------------------------------------------------------
        if ($country_code && $model_id > 0) {
            $price = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $pricing_table WHERE model_id = %d AND repair_id = %d AND country_code = %s AND is_manual_override = 1 LIMIT 1",
                $model_id, $repair_id, $country_code
            ));
            if ($price !== null && is_numeric($price) && floatval($price) > 0) {
                return floatval($price);
            }
        }

        if ($model_id > 0) {
            $price = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $pricing_table WHERE model_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '') AND is_manual_override = 1 LIMIT 1",
                $model_id, $repair_id
            ));
            if ($price !== null && is_numeric($price) && floatval($price) > 0) {
                return floatval($price);
            }
        }

        // -------------------------------------------------------------
        // LAYER 2: Verified Supplier Price + Labour + Global Markup
        // Operates when NO explicit manual override exists.
        // -------------------------------------------------------------
        if ($model_id > 0 && $repair_id > 0) {
            $supplier_row = $wpdb->get_row($wpdb->prepare(
                "SELECT p.current_price, p.stock_status, p.supplier_id 
                 FROM $mappings_table m 
                 JOIN $supplier_prices_table p ON (m.supplier_id = p.supplier_id AND m.supplier_sku = p.supplier_sku)
                 WHERE m.model_id = %d 
                   AND m.repair_id = %d 
                   AND m.active = 1 
                   AND m.match_status = 'verified'
                   AND p.verification_status = 'verified'
                   AND p.current_price > 0 
                   AND p.stock_status != 'out_of_stock'
                 ORDER BY p.current_price ASC 
                 LIMIT 1",
                $model_id, $repair_id
            ), ARRAY_A);

            if ($supplier_row && floatval($supplier_row['current_price']) > 0 && ($supplier_row['stock_status'] ?? 'in_stock') !== 'out_of_stock') {
                $base_part_price = floatval($supplier_row['current_price']);
                $labour_cost = $this->get_labour_cost($repair_id);
                $markup_pct = $this->get_global_markup();

                // Formula: (Base Supplier Part Price + Labour Cost) * (1 + Markup % / 100)
                $calculated_price = ($base_part_price + $labour_cost) * (1.0 + ($markup_pct / 100.0));
                return round($calculated_price, 2);
            }
        }

        // -------------------------------------------------------------
        // LAYER 3: Device Tier Pricing
        // -------------------------------------------------------------
        $tier_id = null;
        if ($model_id > 0) {
            $tier_id = $wpdb->get_var($wpdb->prepare(
                "SELECT tier_id FROM $models_table WHERE id = %d LIMIT 1",
                $model_id
            ));
        }

        if ($tier_id && $country_code) {
            $price = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $tier_pricing_table WHERE tier_id = %d AND repair_id = %d AND country_code = %s LIMIT 1",
                $tier_id, $repair_id, $country_code
            ));
            if ($price !== null && is_numeric($price) && floatval($price) > 0) {
                return floatval($price);
            }
        }

        if ($tier_id) {
            $price = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $tier_pricing_table WHERE tier_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '') LIMIT 1",
                $tier_id, $repair_id
            ));
            if ($price !== null && is_numeric($price) && floatval($price) > 0) {
                return floatval($price);
            }
        }

        // -------------------------------------------------------------
        // LAYER 4: Legacy Model Price Fallback
        // Preserves legacy model pricing when no supplier or tier price is configured
        // -------------------------------------------------------------
        if ($model_id > 0) {
            $legacy_price = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $pricing_table WHERE model_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '') LIMIT 1",
                $model_id, $repair_id
            ));
            if ($legacy_price !== null && is_numeric($legacy_price) && floatval($legacy_price) > 0) {
                return floatval($legacy_price);
            }
        }

        // -------------------------------------------------------------
        // LAYER 5: Global Default Price
        // -------------------------------------------------------------
        $price = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $default_prices_table WHERE repair_id = %d LIMIT 1",
            $repair_id
        ));
        if ($price !== null && is_numeric($price) && floatval($price) > 0) {
            return floatval($price);
        }

        // -------------------------------------------------------------
        // LAYER 6: Base Catalog Fallback
        // -------------------------------------------------------------
        $price = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $repairs_table WHERE id = %d LIMIT 1",
            $repair_id
        ));
        if ($price !== null && is_numeric($price)) {
            return floatval($price);
        }

        return 0.00;
    }

    /**
     * Compute Effective Price Breakdown (For Admin Inspection & Debugging)
     */
    public function get_effective_price_breakdown($model_id, $repair_id, $country_code = null) {
        global $wpdb;

        $model_id = intval($model_id);
        $repair_id = intval($repair_id);
        $country_code = !empty($country_code) ? strtoupper(trim(sanitize_text_field($country_code))) : null;

        $pricing_table        = $wpdb->prefix . 'rbf_pricing';
        $tier_pricing_table   = $wpdb->prefix . 'rbf_tier_pricing';
        $default_prices_table = $wpdb->prefix . 'rbf_default_prices';
        $models_table         = $wpdb->prefix . 'rbf_models';
        $tiers_table          = $wpdb->prefix . 'rbf_device_tiers';
        $repairs_table        = $wpdb->prefix . 'rbf_repairs';
        $mappings_table       = $wpdb->prefix . 'rbf_supplier_mappings';
        $supplier_prices_table= $wpdb->prefix . 'rbf_supplier_prices';

        // 1. Manual Model Override
        if ($country_code && $model_id > 0) {
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $pricing_table WHERE model_id = %d AND repair_id = %d AND country_code = %s AND is_manual_override = 1 LIMIT 1",
                $model_id, $repair_id, $country_code
            ));
            if ($val !== null && is_numeric($val) && floatval($val) > 0) {
                return array(
                    'price'  => floatval($val),
                    'source' => 'Manual Override',
                    'rule'   => "Manual Model Override ($country_code)"
                );
            }
        }

        if ($model_id > 0) {
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $pricing_table WHERE model_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '') AND is_manual_override = 1 LIMIT 1",
                $model_id, $repair_id
            ));
            if ($val !== null && is_numeric($val) && floatval($val) > 0) {
                return array(
                    'price'  => floatval($val),
                    'source' => 'Manual Override',
                    'rule'   => "Manual Model Override (Global)"
                );
            }
        }

        // 2. Supplier Price + Labour + Markup
        if ($model_id > 0 && $repair_id > 0) {
            $supplier_row = $wpdb->get_row($wpdb->prepare(
                "SELECT p.current_price, p.stock_status, p.supplier_id, p.supplier_sku 
                 FROM $mappings_table m 
                 JOIN $supplier_prices_table p ON (m.supplier_id = p.supplier_id AND m.supplier_sku = p.supplier_sku)
                 WHERE m.model_id = %d 
                   AND m.repair_id = %d 
                   AND m.active = 1 
                   AND m.match_status = 'verified'
                   AND p.verification_status = 'verified'
                   AND p.current_price > 0 
                   AND p.stock_status != 'out_of_stock'
                 ORDER BY p.current_price ASC 
                 LIMIT 1",
                $model_id, $repair_id
            ), ARRAY_A);

            if ($supplier_row && floatval($supplier_row['current_price']) > 0 && ($supplier_row['stock_status'] ?? 'in_stock') !== 'out_of_stock') {
                $base = floatval($supplier_row['current_price']);
                $labour = $this->get_labour_cost($repair_id);
                $markup = $this->get_global_markup();
                $final = round(($base + $labour) * (1.0 + ($markup / 100.0)), 2);

                return array(
                    'price'  => $final,
                    'source' => 'Supplier (' . strtoupper($supplier_row['supplier_id']) . ')',
                    'rule'   => "Part (AED {$base}) + Labour (AED {$labour}) × {$markup}% Markup"
                );
            }
        }

        // 3. Tier Check
        $tier_info = null;
        if ($model_id > 0) {
            $tier_info = $wpdb->get_row($wpdb->prepare(
                "SELECT m.tier_id, t.name as tier_name FROM $models_table m LEFT JOIN $tiers_table t ON m.tier_id = t.id WHERE m.id = %d",
                $model_id
            ), ARRAY_A);
        }

        $tier_id = $tier_info['tier_id'] ?? null;
        $tier_name = $tier_info['tier_name'] ?? 'None';

        if ($tier_id && $country_code) {
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $tier_pricing_table WHERE tier_id = %d AND repair_id = %d AND country_code = %s LIMIT 1",
                $tier_id, $repair_id, $country_code
            ));
            if ($val !== null && is_numeric($val) && floatval($val) > 0) {
                return array('price' => floatval($val), 'source' => 'Tier + Country Override', 'rule' => "Tier: $tier_name ($country_code)");
            }
        }

        if ($tier_id) {
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $tier_pricing_table WHERE tier_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '') LIMIT 1",
                $tier_id, $repair_id
            ));
            if ($val !== null && is_numeric($val) && floatval($val) > 0) {
                return array('price' => floatval($val), 'source' => 'Tier Base Price', 'rule' => "Tier: $tier_name (Global)");
            }
        }

        // 4. Legacy Model Price Fallback
        if ($model_id > 0) {
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT price FROM $pricing_table WHERE model_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '') LIMIT 1",
                $model_id, $repair_id
            ));
            if ($val !== null && is_numeric($val) && floatval($val) > 0) {
                return array('price' => floatval($val), 'source' => 'Legacy Model Price', 'rule' => 'Historical Catalog Pricing');
            }
        }

        // 5. Global Default Price
        $val = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $default_prices_table WHERE repair_id = %d LIMIT 1",
            $repair_id
        ));
        if ($val !== null && is_numeric($val) && floatval($val) > 0) {
            return array('price' => floatval($val), 'source' => 'Global Default', 'rule' => 'Default Prices Table');
        }

        // 5. Base Catalog Fallback
        $val = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $repairs_table WHERE id = %d LIMIT 1",
            $repair_id
        ));
        if ($val !== null && is_numeric($val)) {
            return array('price' => floatval($val), 'source' => 'Catalog Fallback', 'rule' => 'Repairs Master Table');
        }

        return array('price' => 0.00, 'source' => 'Quote on Inspection', 'rule' => 'No price set');
    }

    /**
     * Get the full Tier Pricing Grid for admin
     */
    public function get_tier_pricing_grid($country_code = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_tier_pricing';

        if ($country_code) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT tier_id, repair_id, price FROM $table WHERE country_code = %s",
                $country_code
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results(
                "SELECT tier_id, repair_id, price FROM $table WHERE country_code IS NULL OR country_code = ''",
                ARRAY_A
            );
        }

        $grid = array();
        foreach ($rows as $row) {
            $t = intval($row['tier_id']);
            $r = intval($row['repair_id']);
            if (!isset($grid[$t])) {
                $grid[$t] = array();
            }
            $grid[$t][$r] = floatval($row['price']);
        }
        return $grid;
    }

    /**
     * Save the Tier Pricing Grid
     */
    public function save_tier_pricing_grid($grid_data, $country_code = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_tier_pricing';
        $country = !empty($country_code) ? strtoupper(trim(sanitize_text_field($country_code))) : null;

        foreach ($grid_data as $tier_id => $repairs) {
            $tier_id = intval($tier_id);
            if ($tier_id <= 0) continue;

            foreach ($repairs as $repair_id => $price) {
                $repair_id = intval($repair_id);
                $price = floatval($price);

                if ($country) {
                    $existing_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table WHERE tier_id = %d AND repair_id = %d AND country_code = %s",
                        $tier_id, $repair_id, $country
                    ));
                } else {
                    $existing_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table WHERE tier_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '')",
                        $tier_id, $repair_id
                    ));
                }

                if ($existing_id) {
                    $wpdb->update(
                        $table,
                        array('price' => $price, 'updated_at' => current_time('mysql')),
                        array('id' => $existing_id),
                        array('%f', '%s'),
                        array('%d')
                    );
                } else {
                    $wpdb->insert(
                        $table,
                        array(
                            'tier_id'      => $tier_id,
                            'repair_id'    => $repair_id,
                            'country_code' => $country,
                            'price'        => $price,
                            'updated_at'   => current_time('mysql')
                        ),
                        array('%d', '%d', '%s', '%f', '%s')
                    );
                }
            }
        }
        return true;
    }

    /**
     * AJAX Handler: Save full Tier Pricing Grid
     */
    public function ajax_save_tier_pricing_grid() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $grid = isset($_POST['grid']) ? (array) $_POST['grid'] : array();
        $country = !empty($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : null;

        if (empty($grid)) {
            wp_send_json_error('No pricing data received');
        }

        $this->save_tier_pricing_grid($grid, $country);
        wp_send_json_success('Tier pricing saved successfully!');
    }

    /**
     * AJAX Handler: Save specific Model Price Override
     */
    public function ajax_save_model_price_override() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rbf_pricing';

        $model_id = intval($_POST['model_id']);
        $repair_id = intval($_POST['repair_id']);
        $price = floatval($_POST['price']);
        $country = !empty($_POST['country_code']) ? strtoupper(trim(sanitize_text_field($_POST['country_code']))) : null;

        if ($model_id <= 0 || $repair_id <= 0) {
            wp_send_json_error('Invalid Model or Repair ID');
        }

        $brand_id = intval($wpdb->get_var($wpdb->prepare(
            "SELECT brand_id FROM {$wpdb->prefix}rbf_models WHERE id = %d",
            $model_id
        )));

        if ($country) {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE model_id = %d AND repair_id = %d AND country_code = %s",
                $model_id, $repair_id, $country
            ));
        } else {
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE model_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '')",
                $model_id, $repair_id
            ));
        }

        if ($existing_id) {
            $wpdb->update(
                $table,
                array(
                    'price'              => $price,
                    'is_manual_override' => 1,
                    'source'             => 'manual',
                    'updated_at'         => current_time('mysql')
                ),
                array('id' => $existing_id),
                array('%f', '%d', '%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'brand_id'           => $brand_id,
                    'model_id'           => $model_id,
                    'repair_id'          => $repair_id,
                    'country_code'       => $country,
                    'price'              => $price,
                    'is_manual_override' => 1,
                    'source'             => 'manual',
                    'created_at'         => current_time('mysql'),
                    'updated_at'         => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%f', '%d', '%s', '%s', '%s')
            );
        }

        wp_send_json_success('Model price override saved successfully!');
    }

    /**
     * AJAX Handler: Delete Model Price Override
     */
    public function ajax_delete_model_price_override() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rbf_pricing';

        $model_id = intval($_POST['model_id']);
        $repair_id = intval($_POST['repair_id']);
        $country = !empty($_POST['country_code']) ? strtoupper(trim(sanitize_text_field($_POST['country_code']))) : null;

        if ($country) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE model_id = %d AND repair_id = %d AND country_code = %s",
                $model_id, $repair_id, $country
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE model_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '')",
                $model_id, $repair_id
            ));
        }

        wp_send_json_success('Override removed successfully!');
    }

    /**
     * AJAX Handler: Get Effective Price Preview for a Model
     */
    public function ajax_get_model_price_preview() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');

        $model_id = intval($_POST['model_id']);
        $country = !empty($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : null;

        if ($model_id <= 0) {
            wp_send_json_error('Invalid Model ID');
        }

        global $wpdb;
        $repairs = $wpdb->get_results("SELECT id, name, icon FROM {$wpdb->prefix}rbf_repairs WHERE status = 'active' ORDER BY id ASC", ARRAY_A);

        $results = array();
        foreach ($repairs as $r) {
            $breakdown = $this->get_effective_price_breakdown($model_id, $r['id'], $country);
            $results[] = array(
                'repair_id'   => $r['id'],
                'repair_name' => $r['name'],
                'icon'        => $r['icon'],
                'price'       => $breakdown['price'],
                'source'      => $breakdown['source'],
                'rule'        => $breakdown['rule']
            );
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX Handler: Save Pricing Settings (Global Markup % & Labour Costs)
     */
    public function ajax_save_pricing_settings() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $repairs_table = $wpdb->prefix . 'rbf_repairs';

        // Save Global Markup
        if (isset($_POST['global_markup'])) {
            $markup = floatval($_POST['global_markup']);
            if ($markup < 0) $markup = 0;
            update_option('rbf_global_markup_percentage', $markup);
        }

        // Save Labour Costs per repair type
        if (isset($_POST['labour_costs']) && is_array($_POST['labour_costs'])) {
            foreach ($_POST['labour_costs'] as $repair_id => $cost) {
                $repair_id = intval($repair_id);
                $cost = floatval($cost);
                if ($repair_id > 0 && $cost >= 0) {
                    $wpdb->update(
                        $repairs_table,
                        array('labour_cost' => $cost),
                        array('id' => $repair_id)
                    );
                }
            }
        }

        wp_send_json_success('Pricing settings and labour costs saved successfully!');
    }

    /**
     * AJAX Handler: Save Supplier Mapping
     */
    public function ajax_save_supplier_mapping() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $supplier_id  = sanitize_text_field($_POST['supplier_id'] ?? 'lxcell');
        $model_id     = intval($_POST['model_id'] ?? 0);
        $repair_id    = intval($_POST['repair_id'] ?? 0);
        $supplier_sku = sanitize_text_field($_POST['supplier_sku'] ?? '');
        $status       = sanitize_text_field($_POST['match_status'] ?? 'verified');

        if ($model_id <= 0 || $repair_id <= 0 || empty($supplier_sku)) {
            wp_send_json_error('Model, Repair Type, and Supplier SKU are required.');
        }

        $id = RBF_Supplier_Manager::get_instance()->save_mapping(
            $supplier_id, $model_id, $repair_id, $supplier_sku, '', $status
        );

        wp_send_json_success(array('id' => $id, 'message' => 'Supplier mapping saved!'));
    }

    /**
     * AJAX Handler: Delete/Deactivate Supplier Mapping
     */
    public function ajax_delete_supplier_mapping() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error('Invalid mapping ID.');
        }

        RBF_Supplier_Manager::get_instance()->delete_mapping($id);
        wp_send_json_success('Mapping removed.');
    }
}
