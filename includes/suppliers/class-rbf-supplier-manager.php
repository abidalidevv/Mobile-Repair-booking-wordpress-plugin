<?php
/**
 * Supplier Manager & Registry for EFIX Repair Booking Form
 *
 * Coordinates supplier provider adapters, handles normalized product matching,
 * and maintains supplier SKU-to-model/repair mappings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Supplier_Manager {

    private static $instance = null;
    private $providers = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->register_default_providers();
    }

    /**
     * Register core built-in supplier providers
     */
    private function register_default_providers() {
        if (class_exists('RBF_Supplier_LXCELL')) {
            $this->register_provider(new RBF_Supplier_LXCELL());
        }
    }

    /**
     * Register a supplier provider
     *
     * @param RBF_Supplier_Interface $provider
     */
    public function register_provider(RBF_Supplier_Interface $provider) {
        $this->providers[$provider->get_id()] = $provider;
    }

    /**
     * Get a registered provider by ID
     *
     * @param string $id
     * @return RBF_Supplier_Interface|null
     */
    public function get_provider($id) {
        return isset($this->providers[$id]) ? $this->providers[$id] : null;
    }

    /**
     * Get all registered providers
     *
     * @return array [id => RBF_Supplier_Interface]
     */
    public function get_all_providers() {
        return $this->providers;
    }

    /**
     * High-confidence matching: match a normalized supplier product to local model_id & repair_id
     *
     * @param array $product Normalized product array
     * @return array [
     *   'model_id'     => int|null,
     *   'repair_id'    => int|null,
     *   'match_status' => 'verified'|'needs_review'|'unmatched',
     *   'confidence'   => float,
     *   'notes'        => string
     * ]
     */
    public function match_product($product) {
        global $wpdb;

        $models_table = $wpdb->prefix . 'rbf_models';
        $repairs_table = $wpdb->prefix . 'rbf_repairs';
        $mappings_table = $wpdb->prefix . 'rbf_supplier_mappings';

        $sku = !empty($product['supplier_sku']) ? trim($product['supplier_sku']) : '';
        $supplier_id = !empty($product['supplier_id']) ? trim($product['supplier_id']) : '';

        // 1. Check existing verified mapping by exact supplier SKU first
        if (!empty($sku) && !empty($supplier_id)) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT model_id, repair_id, match_status FROM $mappings_table WHERE supplier_id = %s AND supplier_sku = %s AND active = 1 LIMIT 1",
                $supplier_id, $sku
            ), ARRAY_A);

            if ($existing) {
                return array(
                    'model_id'     => intval($existing['model_id']),
                    'repair_id'    => intval($existing['repair_id']),
                    'match_status' => $existing['match_status'],
                    'confidence'   => 1.0,
                    'notes'        => 'Matched from existing mapping table'
                );
            }
        }

        $raw_model_name = !empty($product['model_name']) ? trim($product['model_name']) : '';
        $raw_part_type  = !empty($product['part_type']) ? trim($product['part_type']) : '';
        $product_title  = !empty($product['product_name']) ? trim($product['product_name']) : '';

        // 2. Identify Repair Type
        $repair_id = null;
        $repair_confidence = 0.0;
        $combined_text = strtolower($raw_part_type . ' ' . $product_title);

        if (preg_match('/(screen|display|lcd|oled|incell|in-cell|touch screen|glass digitizer)/i', $combined_text)) {
            $r_id = $wpdb->get_var("SELECT id FROM $repairs_table WHERE LOWER(name) LIKE '%screen%' AND status = 'active' ORDER BY id ASC LIMIT 1");
            if ($r_id) {
                $repair_id = intval($r_id);
                $repair_confidence = 0.95;
            }
        } elseif (preg_match('/(battery|accu|mah)/i', $combined_text)) {
            $r_id = $wpdb->get_var("SELECT id FROM $repairs_table WHERE LOWER(name) LIKE '%battery%' AND status = 'active' ORDER BY id ASC LIMIT 1");
            if ($r_id) {
                $repair_id = intval($r_id);
                $repair_confidence = 0.95;
            }
        } elseif (preg_match('/(charging port|charge port|dock connector|sub-pba|sub board|usb-c flex)/i', $combined_text)) {
            $r_id = $wpdb->get_var("SELECT id FROM $repairs_table WHERE (LOWER(name) LIKE '%charging%' OR LOWER(name) LIKE '%port%') AND status = 'active' ORDER BY id ASC LIMIT 1");
            if ($r_id) {
                $repair_id = intval($r_id);
                $repair_confidence = 0.90;
            }
        } elseif (preg_match('/(back glass|back cover|rear glass|battery cover)/i', $combined_text)) {
            $r_id = $wpdb->get_var("SELECT id FROM $repairs_table WHERE (LOWER(name) LIKE '%back glass%' OR LOWER(name) LIKE '%back cover%') AND status = 'active' ORDER BY id ASC LIMIT 1");
            if ($r_id) {
                $repair_id = intval($r_id);
                $repair_confidence = 0.90;
            }
        } elseif (preg_match('/(camera|rear camera|front camera|lens)/i', $combined_text)) {
            $r_id = $wpdb->get_var("SELECT id FROM $repairs_table WHERE LOWER(name) LIKE '%camera%' AND status = 'active' ORDER BY id ASC LIMIT 1");
            if ($r_id) {
                $repair_id = intval($r_id);
                $repair_confidence = 0.85;
            }
        }

        // 3. Identify Device Model
        $model_id = null;
        $model_confidence = 0.0;
        $search_model = !empty($raw_model_name) ? $raw_model_name : $product_title;

        // Try exact match on model name
        $exact_model = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM $models_table WHERE LOWER(name) = LOWER(%s) AND status = 'active' LIMIT 1",
            trim($search_model)
        ), ARRAY_A);

        if ($exact_model) {
            $model_id = intval($exact_model['id']);
            $model_confidence = 1.0;
        } else {
            // Find matching active model name contained in product title
            $all_models = $wpdb->get_results("SELECT id, name FROM $models_table WHERE status = 'active' ORDER BY LENGTH(name) DESC", ARRAY_A);
            $lower_title = strtolower($product_title . ' ' . $raw_model_name);

            foreach ($all_models as $m) {
                $m_name = strtolower(trim($m['name']));
                if (strlen($m_name) >= 3 && strpos($lower_title, $m_name) !== false) {
                    $model_id = intval($m['id']);
                    $model_confidence = 0.85;
                    break;
                }
            }
        }

        $overall_confidence = ($model_confidence + $repair_confidence) / 2.0;

        if ($model_id && $repair_id && $overall_confidence >= 0.85) {
            $status = ($overall_confidence >= 0.90) ? 'verified' : 'needs_review';
            return array(
                'model_id'     => $model_id,
                'repair_id'    => $repair_id,
                'match_status' => $status,
                'confidence'   => $overall_confidence,
                'notes'        => "Matched with confidence " . round($overall_confidence * 100) . "%"
            );
        }

        return array(
            'model_id'     => $model_id,
            'repair_id'    => $repair_id,
            'match_status' => 'needs_review',
            'confidence'   => $overall_confidence,
            'notes'        => 'Uncertain match requires manual admin review'
        );
    }

    /**
     * Get list of all supplier mappings with model and repair details
     */
    public function get_mappings($supplier_id = null, $status = null) {
        global $wpdb;
        $mappings_table = $wpdb->prefix . 'rbf_supplier_mappings';
        $models_table   = $wpdb->prefix . 'rbf_models';
        $brands_table   = $wpdb->prefix . 'rbf_brands';
        $repairs_table  = $wpdb->prefix . 'rbf_repairs';
        $prices_table   = $wpdb->prefix . 'rbf_supplier_prices';

        $where = array('m.active = 1');
        $params = array();

        if (!empty($supplier_id)) {
            $where[] = 'm.supplier_id = %s';
            $params[] = $supplier_id;
        }

        if (!empty($status)) {
            $where[] = 'm.match_status = %s';
            $params[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT m.*, 
                       b.name as brand_name, 
                       mdl.name as model_name, 
                       r.name as repair_name,
                       p.current_price,
                       p.stock_status,
                       p.product_name,
                       p.fetched_at as last_price_update
                FROM $mappings_table m
                LEFT JOIN $models_table mdl ON m.model_id = mdl.id
                LEFT JOIN $brands_table b ON mdl.brand_id = b.id
                LEFT JOIN $repairs_table r ON m.repair_id = r.id
                LEFT JOIN $prices_table p ON (m.supplier_id = p.supplier_id AND m.supplier_sku = p.supplier_sku)
                WHERE $where_sql
                ORDER BY m.id DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        }
        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Save or update an explicit supplier mapping
     */
    public function save_mapping($supplier_id, $model_id, $repair_id, $supplier_sku, $product_url = '', $status = 'verified') {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_supplier_mappings';

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE supplier_id = %s AND model_id = %d AND repair_id = %d LIMIT 1",
            $supplier_id, $model_id, $repair_id
        ));

        $data = array(
            'supplier_id'          => sanitize_text_field($supplier_id),
            'model_id'             => intval($model_id),
            'repair_id'            => intval($repair_id),
            'supplier_sku'         => sanitize_text_field($supplier_sku),
            'supplier_product_url' => esc_url_raw($product_url),
            'match_status'         => sanitize_text_field($status),
            'last_verified'        => current_time('mysql'),
            'active'               => 1,
            'updated_at'           => current_time('mysql')
        );

        if ($existing_id) {
            $wpdb->update($table, $data, array('id' => $existing_id));
            return intval($existing_id);
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
            return intval($wpdb->insert_id);
        }
    }

    /**
     * Delete mapping
     */
    public function delete_mapping($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_supplier_mappings';
        return $wpdb->update($table, array('active' => 0, 'updated_at' => current_time('mysql')), array('id' => intval($id)));
    }
}
