<?php
/**
 * Catalog Management & Synchronization Engine for EFIX Repair Booking Form
 *
 * Establishes the WordPress Database as the Single Source of Truth
 * for Brands, Models, and Repair Services.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Catalog {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_rbf_sync_catalog_db', array($this, 'ajax_sync_catalog_db'));
        add_action('wp_ajax_rbf_bulk_assign_tiers', array($this, 'ajax_bulk_assign_tiers'));
        add_action('wp_ajax_rbf_update_model_tier', array($this, 'ajax_update_model_tier'));
    }

    /**
     * Get all active brands from the database
     */
    /**
     * Get all active repairs from database or JSON catalog
     */
    public function get_all_repairs() {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_repairs';
        $repairs = $wpdb->get_results("SELECT id, name, icon, price, labour_cost FROM $table WHERE status = 'active' ORDER BY id ASC", ARRAY_A);
        if (!empty($repairs)) {
            return $repairs;
        }

        // Fallback to JSON
        $json_file = RBF_PLUGIN_PATH . 'brands_models_data.json';
        if (file_exists($json_file)) {
            $data = json_decode(file_get_contents($json_file), true);
            return $data['repair_services'] ?? array();
        }
        return array();
    }

    /**
     * Get all active brands (alias / wrapper)
     */
    public function get_all_brands() {
        return $this->get_brands();
    }

    /**
     * Get all active models across all brands
     */
    public function get_all_models() {
        global $wpdb;
        $models_table = $wpdb->prefix . 'rbf_models';
        $brands_table = $wpdb->prefix . 'rbf_brands';

        $models = $wpdb->get_results(
            "SELECT m.id, m.brand_id, m.name, m.image_url as image, m.tier_id, b.name as brand_name 
             FROM $models_table m 
             LEFT JOIN $brands_table b ON m.brand_id = b.id 
             WHERE m.status = 'active' 
             ORDER BY b.name ASC, m.name ASC",
            ARRAY_A
        );

        if (!empty($models)) {
            return $models;
        }

        // Fallback to JSON
        $json_file = RBF_PLUGIN_PATH . 'brands_models_data.json';
        $all = array();
        if (file_exists($json_file)) {
            $data = json_decode(file_get_contents($json_file), true);
            foreach ($data['brands'] as $b) {
                foreach ($b['models'] as $m) {
                    $all[] = array(
                        'name' => $m['name'],
                        'brand_name' => $b['name'],
                        'image' => $m['image']
                    );
                }
            }
        }
        return $all;
    }

    public function get_brands() {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_brands';

        // Check if table exists and has rows; if not, sync from JSON
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
        if (!$count || intval($count) === 0) {
            $this->sync_from_json_to_db();
        }

        $brands = $wpdb->get_results(
            "SELECT b.*, (SELECT COUNT(*) FROM {$wpdb->prefix}rbf_models m WHERE m.brand_id = b.id AND m.status = 'active') as model_count 
             FROM $table b 
             WHERE b.status = 'active' 
             ORDER BY b.id ASC",
            ARRAY_A
        );

        return $brands;
    }

    /**
     * Get models for a specific brand from the database
     */
    public function get_models_by_brand($brand_identifier) {
        global $wpdb;
        $brands_table = $wpdb->prefix . 'rbf_brands';
        $models_table = $wpdb->prefix . 'rbf_models';

        $brand_id = 0;
        if (is_numeric($brand_identifier)) {
            $brand_id = intval($brand_identifier);
        } else {
            $brand_name = trim(sanitize_text_field($brand_identifier));
            $brand_id = intval($wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $brands_table WHERE LOWER(name) = LOWER(%s) LIMIT 1",
                $brand_name
            )));
        }

        if ($brand_id <= 0) {
            return array();
        }

        $models = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, t.name as tier_name 
             FROM $models_table m 
             LEFT JOIN {$wpdb->prefix}rbf_device_tiers t ON m.tier_id = t.id 
             WHERE m.brand_id = %d AND m.status = 'active' 
             ORDER BY m.name ASC",
            $brand_id
        ), ARRAY_A);

        // Normalize image paths
        foreach ($models as &$m) {
            $m['image'] = $this->normalize_image_url($m['image_url']);
            $m['description'] = !empty($m['description']) ? $m['description'] : 'Authentic Parts Available';
        }

        return $models;
    }

    /**
     * Get model by Name and Brand Name
     */
    public function get_model_by_name($brand_name, $model_name) {
        global $wpdb;
        $brands_table = $wpdb->prefix . 'rbf_brands';
        $models_table = $wpdb->prefix . 'rbf_models';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, b.name as brand_name 
             FROM $models_table m 
             JOIN $brands_table b ON m.brand_id = b.id 
             WHERE LOWER(b.name) = LOWER(%s) AND LOWER(m.name) = LOWER(%s) 
             LIMIT 1",
            trim($brand_name), trim($model_name)
        ), ARRAY_A);

        return $row;
    }

    /**
     * Get repair services for a model with dynamically resolved prices
     */
    /**
     * Normalize repair icon path or map to authentic Brand icon
     */
    public function normalize_repair_icon($icon, $repair_name = '') {
        $icon = trim($icon ?? '');
        $name = strtolower(trim($repair_name));

        if (!empty($icon) && (str_starts_with($icon, 'http://') || str_starts_with($icon, 'https://'))) {
            return $icon;
        }

        // Map known repair names if icon is empty or default
        if (empty($icon) || $icon === '🛠️' || $icon === '⚙️') {
            if (str_contains($name, 'screen') || str_contains($name, 'display') || str_contains($name, 'glass') || str_contains($name, 'lcd')) {
                return 'Brands/repair_Icons/broken.png';
            }
            if (str_contains($name, 'battery')) {
                return 'Brands/repair_Icons/battry.png';
            }
            if (str_contains($name, 'port') || str_contains($name, 'charging')) {
                return 'Brands/repair_Icons/port_issue.png';
            }
            if (str_contains($name, 'camera') || str_contains($name, 'lens')) {
                return 'Brands/repair_Icons/camera.png';
            }
            if (str_contains($name, 'speaker') || str_contains($name, 'audio') || str_contains($name, 'sound')) {
                return 'Brands/repair_Icons/speaker.png';
            }
            if (str_contains($name, 'microphone') || str_contains($name, 'mic')) {
                return 'Brands/repair_Icons/connectivity.png';
            }
            if (str_contains($name, 'back glass') || str_contains($name, 'back cover')) {
                return 'Brands/repair_Icons/back_damaged.png';
            }
            if (str_contains($name, 'frame') || str_contains($name, 'housing') || str_contains($name, 'body')) {
                return 'Brands/repair_Icons/frame_damaged.png';
            }
            if (str_contains($name, 'water') || str_contains($name, 'liquid')) {
                return 'Brands/repair_Icons/waterdaamage.png';
            }
            if (str_contains($name, 'software') || str_contains($name, 'slow') || str_contains($name, 'os')) {
                return 'Brands/repair_Icons/slow.png';
            }
            if (str_contains($name, 'diagnosis') || str_contains($name, 'checkup') || str_contains($name, 'inspect')) {
                return 'Brands/repair_Icons/Checkup.png';
            }
            if (str_contains($name, 'lock') || str_contains($name, 'unlock')) {
                return 'Brands/repair_Icons/locked.png';
            }
            if (str_contains($name, 'data') || str_contains($name, 'recovery')) {
                return 'Brands/repair_Icons/data_recovery.png';
            }
            if (str_contains($name, 'power') || str_contains($name, 'button')) {
                return 'Brands/repair_Icons/power.png';
            }
            return 'Brands/repair_Icons/hardware.png';
        }

        return ltrim($icon, '/');
    }

    public function get_repairs_by_model($brand_name, $model_name, $country_code = null) {
        global $wpdb;
        $repairs_table = $wpdb->prefix . 'rbf_repairs';

        // Check if repairs table is populated
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $repairs_table WHERE status = 'active'");
        if (!$count || intval($count) === 0) {
            $this->sync_from_json_to_db();
        }

        $repairs = $wpdb->get_results(
            "SELECT * FROM $repairs_table WHERE status = 'active' ORDER BY id ASC",
            ARRAY_A
        );

        // Handle "Others" brand — quote on inspection
        if (strtolower(trim($brand_name)) === 'others') {
            $others_repairs = array();
            foreach ($repairs as $r) {
                $others_repairs[] = array(
                    'id' => intval($r['id']),
                    'name' => $r['name'],
                    'price' => 0.00,
                    'duration' => !empty($r['duration']) ? $r['duration'] : '01-02 Day(s)',
                    'icon' => $this->normalize_repair_icon($r['icon'] ?? '', $r['name'] ?? ''),
                    'description' => 'Service available (quote provided after physical inspection)'
                );
            }
            return $others_repairs;
        }

        // Find Model ID
        $model_row = $this->get_model_by_name($brand_name, $model_name);
        $model_id = $model_row ? intval($model_row['id']) : 0;

        $pricing_mgr = RBF_Pricing::get_instance();
        $results = array();

        foreach ($repairs as $r) {
            $repair_id = intval($r['id']);
            $price = $pricing_mgr->get_price($model_id, $repair_id, $country_code);

            $results[] = array(
                'id' => $repair_id,
                'name' => $r['name'],
                'price' => $price,
                'duration' => !empty($r['duration']) ? $r['duration'] : '01-02 Hours',
                'icon' => $this->normalize_repair_icon($r['icon'] ?? '', $r['name'] ?? ''),
                'description' => !empty($r['description']) ? $r['description'] : 'OEM grade replacement part with warranty'
            );
        }

        return $results;
    }

    /**
     * Normalize image URL for frontend rendering
     */
    public function normalize_image_url($raw_path) {
        if (empty($raw_path)) {
            return RBF_PLUGIN_URL . 'Brands/other_brand.jpg';
        }
        if (str_starts_with($raw_path, 'http://') || str_starts_with($raw_path, 'https://')) {
            return $raw_path;
        }
        return RBF_PLUGIN_URL . ltrim($raw_path, '/');
    }

    /**
     * Heuristic Auto-Tagging of Device Models into Price Tiers
     *
     * @param string $model_name
     * @param string $brand_name
     * @return int tier_id (1 = Economy, 2 = Mid-Range, 3 = Flagship, 4 = Premium/Foldable)
     */
    public function guess_tier_for_model($model_name, $brand_name = '') {
        $name = strtolower($model_name);
        $brand = strtolower($brand_name);

        // Tier 4: Premium / Foldable
        if (str_contains($name, 'fold') || str_contains($name, 'flip') || str_contains($name, 'macbook') || str_contains($name, 'razr') || str_contains($name, 'magic v')) {
            return 4; // Premium/Foldable
        }

        // Tier 3: Flagship (Pro Max, Ultra, Pro, Plus, Studio, GT, Magic, Zero, Phantom, X100, Pura)
        if (str_contains($name, 'pro max') || str_contains($name, 'ultra') || str_contains($name, 'pro+') || 
            str_contains($name, 'pro') || str_contains($name, 'plus') || str_contains($name, 'pad pro') ||
            str_contains($name, 'magic') || str_contains($name, 'pura') || str_contains($name, 'phantom') ||
            str_contains($name, 'gt') || str_contains($name, 'x100') || str_contains($name, 'x200') || str_contains($name, 'find x')) {
            return 3; // Flagship
        }

        // Tier 1: Economy (SE, Lite, A-series budget, Redmi budget, Y-series, C-series, Smart, Spark, Hot, Play)
        if (str_contains($name, 'se') || str_contains($name, 'lite') || str_contains($name, 'play') || 
            str_contains($name, 'core') || str_contains($name, 'smart') || str_contains($name, 'spark') ||
            str_contains($name, 'hot') || str_contains($name, 'cmf') || str_contains($name, 'redmi a') || str_contains($name, 'c3') ||
            str_contains($name, 'c5') || str_contains($name, 'c6') || str_contains($name, 'y0') ||
            str_contains($name, 'y1') || str_contains($name, 'y2') || str_contains($name, 'a0') ||
            str_contains($name, 'a1') || str_contains($name, 'a2') || str_contains($name, 'a3')) {
            return 1; // Economy
        }

        // Tier 2: Mid-Range (Default for standard numbered flagships like iPhone 15, S24, Pixel 8, OnePlus 12)
        return 2; // Mid-Range
    }

    /**
     * Normalize and fix series-level device image associations
     * Replaces duplicates or generic fallbacks with appropriate series imagery
     */
    public function normalize_model_image($model_name, $brand_name, $current_image) {
        $name = strtolower($model_name);
        $brand = strtolower($brand_name);

        // 1. iPad Models: Ensure dedicated iPad imagery, never iPhone photos
        if ($brand === 'ipad' || str_starts_with($name, 'ipad')) {
            if (str_contains($name, 'pro')) {
                return 'Brands/ipad_modals/ipad-pro.jpg';
            } elseif (str_contains($name, 'air')) {
                return 'Brands/ipad_modals/ipad-air.jpg';
            } elseif (str_contains($name, 'mini')) {
                return 'Brands/ipad_modals/ipad-mini.png';
            } elseif (str_contains($name, '10th') || str_contains($name, '10')) {
                return 'Brands/ipad_modals/ipad-10th-gen.png';
            } else {
                return 'Brands/ipad_modals/ipad-9th-gen.png';
            }
        }

        // 2. MacBook Models: Ensure dedicated MacBook imagery, never iPhone photos
        if ($brand === 'macbook' || str_contains($name, 'macbook')) {
            if (str_contains($name, 'air')) {
                return 'Brands/macbook_modals/macbook-air.jpg';
            } else {
                return 'Brands/macbook_modals/macbook-pro.jpg';
            }
        }

        // 3. iPhone 17 / future series: point to clean iPhone render instead of misleading older photos
        if (str_contains($name, 'iphone 17') || str_contains($name, 'iphone air')) {
            if (file_exists(RBF_PLUGIN_PATH . 'Brands/iphone_modals/apple-iphone-16-pro-max.jpg')) {
                return 'Brands/iphone_modals/apple-iphone-16-pro-max.jpg';
            }
            return 'Brands/apple.png';
        }

        // 4. Huawei Series Fallbacks
        if ($brand === 'huawei') {
            if (str_contains($name, 'pura') || str_contains($name, 'p70')) {
                return 'Brands/huawei_modals/huawei-pura-70.jpg';
            }
            if (str_contains($name, 'mate 60')) {
                return 'Brands/huawei_modals/huawei-mate-60.jpg';
            }
            if (str_contains($name, 'mate 50')) {
                return 'Brands/huawei_modals/huawei-mate-50.jpg';
            }
            if (str_contains($name, 'mate x')) {
                return 'Brands/huawei_modals/huawei-mate-x.jpg';
            }
            if (str_contains($name, 'p50') || str_contains($name, 'p60')) {
                return 'Brands/huawei_modals/huawei-p50.jpg';
            }
            if (str_contains($name, 'nova 12')) {
                return 'Brands/huawei_modals/huawei-nova-12.jpg';
            }
        }

        // 5. Oppo Fallbacks
        if ($brand === 'oppo') {
            if (str_contains($name, 'a98')) {
                return 'Brands/oppo_modals/oppo-a98.jpg';
            }
        }

        // 6. Motorola Fallbacks
        if ($brand === 'motorola') {
            if (str_contains($name, 'v3') || str_contains($name, 'krazr') || str_contains($name, 'k1')) {
                return 'Brands/motorola_modals/motorola-razr-v3.png';
            }
        }

        // Return verified path or fallback to brand logo
        if (!empty($current_image) && file_exists(RBF_PLUGIN_PATH . ltrim($current_image, '/')) && strpos($current_image, 'other_brand.jpg') === false) {
            return $current_image;
        }

        // Fallback by brand
        $brand_lower = strtolower($brand_name);
        if (file_exists(RBF_PLUGIN_PATH . 'Brands/' . $brand_lower . '.png')) {
            return 'Brands/' . $brand_lower . '.png';
        }

        return 'Brands/other_brand.jpg';
    }

    /**
     * Sync JSON Catalog directly into Database Tables
     */
    public function sync_from_json_to_db() {
        global $wpdb;

        $json_file = RBF_PLUGIN_PATH . 'brands_models_data.json';
        if (!file_exists($json_file)) {
            return false;
        }

        $json_content = file_get_contents($json_file);
        $data = json_decode($json_content, true);
        if (!$data || !isset($data['brands'])) {
            return false;
        }

        $brands_table = $wpdb->prefix . 'rbf_brands';
        $models_table = $wpdb->prefix . 'rbf_models';
        $repairs_table = $wpdb->prefix . 'rbf_repairs';
        $default_prices_table = $wpdb->prefix . 'rbf_default_prices';
        $tier_pricing_table = $wpdb->prefix . 'rbf_tier_pricing';

        // 1. Sync Brands & Models
        foreach ($data['brands'] as $brand_item) {
            $b_name = sanitize_text_field($brand_item['name']);
            $b_logo = sanitize_text_field($brand_item['logo'] ?? '');

            // Check if brand exists
            $existing_brand_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $brands_table WHERE LOWER(name) = LOWER(%s)",
                $b_name
            ));

            if (!$existing_brand_id) {
                $wpdb->insert(
                    $brands_table,
                    array(
                        'name' => $b_name,
                        'image_url' => $b_logo,
                        'status' => 'active',
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s')
                );
                $brand_id = $wpdb->insert_id;
            } else {
                $brand_id = intval($existing_brand_id);
                // Update logo if missing
                $wpdb->update(
                    $brands_table,
                    array('image_url' => $b_logo),
                    array('id' => $brand_id),
                    array('%s'),
                    array('%d')
                );
            }

            // Sync Models for this Brand
            if (!empty($brand_item['models']) && is_array($brand_item['models'])) {
                foreach ($brand_item['models'] as $m_item) {
                    $m_name = sanitize_text_field($m_item['name']);
                    $raw_img = sanitize_text_field($m_item['image'] ?? '');
                    $clean_img = $this->normalize_model_image($m_name, $b_name, $raw_img);
                    $guessed_tier = $this->guess_tier_for_model($m_name, $b_name);

                    $existing_model = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, tier_id FROM $models_table WHERE brand_id = %d AND LOWER(name) = LOWER(%s)",
                        $brand_id, $m_name
                    ), ARRAY_A);

                    if (!$existing_model) {
                        $wpdb->insert(
                            $models_table,
                            array(
                                'brand_id' => $brand_id,
                                'name' => $m_name,
                                'image_url' => $clean_img,
                                'tier_id' => $guessed_tier,
                                'status' => 'active',
                                'created_at' => current_time('mysql')
                            ),
                            array('%d', '%s', '%s', '%d', '%s', '%s')
                        );
                    } else {
                        $update_data = array();
                        // Update tier_id if currently null
                        if (empty($existing_model['tier_id'])) {
                            $update_data['tier_id'] = $guessed_tier;
                        }
                        // Update image_url if currently empty or generic placeholder
                        if (!empty($clean_img) && (empty($existing_model['image_url']) || strpos($existing_model['image_url'], 'other_brand.jpg') !== false)) {
                            $update_data['image_url'] = $clean_img;
                        }
                        if (!empty($update_data)) {
                            $wpdb->update(
                                $models_table,
                                $update_data,
                                array('id' => $existing_model['id'])
                            );
                        }
                    }
                }
            }
        }

        // 2. Sync Repair Services Master List
        if (!empty($data['repair_services']) && is_array($data['repair_services'])) {
            foreach ($data['repair_services'] as $service) {
                $s_name = sanitize_text_field($service['name']);
                $s_desc = sanitize_textarea_field($service['description'] ?? '');
                $s_icon = sanitize_text_field($service['icon'] ?? '🛠️');
                $s_dur  = sanitize_text_field($service['duration'] ?? '01-02 Hours');
                $s_price = floatval($service['price'] ?? 0);

                $existing_repair = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM $repairs_table WHERE LOWER(name) = LOWER(%s)",
                    $s_name
                ), ARRAY_A);

                if (!$existing_repair) {
                    $wpdb->insert(
                        $repairs_table,
                        array(
                            'model_id' => 1,
                            'name' => $s_name,
                            'description' => $s_desc,
                            'icon' => $s_icon,
                            'duration' => $s_dur,
                            'repair_time' => $s_dur,
                            'price' => $s_price,
                            'status' => 'active',
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s')
                    );
                    $repair_id = $wpdb->insert_id;
                } else {
                    $repair_id = intval($existing_repair['id']);
                }

                // Seed default prices table
                $existing_default = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $default_prices_table WHERE repair_id = %d",
                    $repair_id
                ));
                if (!$existing_default && $s_price > 0) {
                    $wpdb->insert(
                        $default_prices_table,
                        array(
                            'repair_id' => $repair_id,
                            'price' => $s_price,
                            'created_at' => current_time('mysql')
                        ),
                        array('%d', '%f', '%s')
                    );
                }

                // Seed Initial Tier Pricing (4 Tiers) based on multiplier
                // Tier 1: Economy = 0.70x, Tier 2: Mid-Range = 1.0x, Tier 3: Flagship = 1.35x, Tier 4: Premium = 1.65x
                $multipliers = array(
                    1 => 0.70, // Economy
                    2 => 1.00, // Mid-Range
                    3 => 1.35, // Flagship
                    4 => 1.65  // Premium/Foldable
                );

                foreach ($multipliers as $t_id => $mult) {
                    $tier_price = round($s_price * $mult, 2);
                    $exists_tier_price = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $tier_pricing_table WHERE tier_id = %d AND repair_id = %d AND (country_code IS NULL OR country_code = '')",
                        $t_id, $repair_id
                    ));
                    if (!$exists_tier_price) {
                        $wpdb->insert(
                            $tier_pricing_table,
                            array(
                                'tier_id' => $t_id,
                                'repair_id' => $repair_id,
                                'country_code' => null,
                                'price' => $tier_price,
                                'updated_at' => current_time('mysql')
                            ),
                            array('%d', '%d', '%s', '%f', '%s')
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * AJAX: Trigger Catalog Sync from JSON
     */
    public function ajax_sync_catalog_db() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $success = $this->sync_from_json_to_db();
        if ($success) {
            wp_send_json_success('Catalog synchronized to database successfully with tiers seeded!');
        } else {
            wp_send_json_error('Failed to sync catalog');
        }
    }

    /**
     * AJAX: Bulk Assign Tiers to Selected Models
     */
    public function ajax_bulk_assign_tiers() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $models_table = $wpdb->prefix . 'rbf_models';

        $model_ids = isset($_POST['model_ids']) ? (array) $_POST['model_ids'] : array();
        $tier_id = intval($_POST['tier_id'] ?? 0);

        if (empty($model_ids) || $tier_id <= 0) {
            wp_send_json_error('Invalid models or tier specified');
        }

        $ids_sanitized = array_map('intval', $model_ids);
        $ids_placeholder = implode(',', $ids_sanitized);

        $wpdb->query($wpdb->prepare(
            "UPDATE $models_table SET tier_id = %d WHERE id IN ($ids_placeholder)",
            $tier_id
        ));

        wp_send_json_success('Tiers updated for selected models!');
    }

    /**
     * AJAX: Update single model tier
     */
    public function ajax_update_model_tier() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        global $wpdb;
        $model_id = intval($_POST['model_id']);
        $tier_id = intval($_POST['tier_id']);

        if ($model_id <= 0 || $tier_id <= 0) {
            wp_send_json_error('Invalid parameters');
        }

        $wpdb->update(
            $wpdb->prefix . 'rbf_models',
            array('tier_id' => $tier_id),
            array('id' => $model_id),
            array('%d'),
            array('%d')
        );

        wp_send_json_success('Model tier updated!');
    }
}
