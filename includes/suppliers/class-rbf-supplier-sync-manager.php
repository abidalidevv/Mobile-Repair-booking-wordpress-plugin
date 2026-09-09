<?php
/**
 * Supplier Synchronization Manager for EFIX Repair Booking Form
 *
 * Coordinates WP-Cron and manual background price syncs.
 * Ensures concurrency safety, delta price comparisons, error logging,
 * and prevents checkout blocking.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Supplier_Sync_Manager {

    private static $instance = null;
    const LOCK_KEY = 'rbf_supplier_sync_lock';
    const LOCK_TTL = 900; // 15 minutes lock expiration

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Custom cron recurrence (every 48 hours)
        add_filter('cron_schedules', array($this, 'add_cron_interval'));

        // Cron hook
        add_action('rbf_supplier_price_sync', array($this, 'run_scheduled_sync'));
        // Admin AJAX manual sync
        add_action('wp_ajax_rbf_manual_supplier_sync', array($this, 'ajax_manual_sync'));
        // Admin AJAX feed upload
        add_action('wp_ajax_rbf_import_supplier_feed', array($this, 'ajax_import_feed'));

        // Auto schedule cron if not already scheduled (prevents duplicate crons)
        $this->maybe_schedule_cron();
    }

    /**
     * Add 48 hour cron schedule
     */
    public function add_cron_interval($schedules) {
        if (!isset($schedules['forty_eight_hours'])) {
            $schedules['forty_eight_hours'] = array(
                'interval' => 48 * HOUR_IN_SECONDS,
                'display'  => __('Every 48 Hours', 'repair-booking-form')
            );
        }
        return $schedules;
    }

    /**
     * Auto schedule cron if not already registered (avoids duplicates)
     */
    public function maybe_schedule_cron() {
        if (!wp_next_scheduled('rbf_supplier_price_sync')) {
            wp_schedule_event(time() + (48 * HOUR_IN_SECONDS), 'forty_eight_hours', 'rbf_supplier_price_sync');
        }
    }

    /**
     * Check if a sync is currently locked/running
     */
    public function is_locked() {
        return (bool) get_transient(self::LOCK_KEY);
    }

    /**
     * Acquire concurrency lock
     */
    public function acquire_lock() {
        if ($this->is_locked()) {
            return false;
        }
        set_transient(self::LOCK_KEY, current_time('timestamp'), self::LOCK_TTL);
        return true;
    }

    /**
     * Release concurrency lock
     */
    public function release_lock() {
        delete_transient(self::LOCK_KEY);
    }

    /**
     * Main sync execution method
     *
     * @param string $supplier_id Supplier identifier
     * @param array $options Additional options (e.g. ['feed_content' => '...'])
     * @return array Sync result summary
     */
    public function run_sync($supplier_id = 'lxcell', $options = array()) {
        global $wpdb;

        if (!$this->acquire_lock()) {
            return array(
                'status'  => 'error',
                'message' => 'Another supplier sync is already in progress. Please wait.'
            );
        }

        $start_time = microtime(true);
        $sync_start = current_time('mysql');

        $logs_table = $wpdb->prefix . 'rbf_supplier_sync_logs';
        $prices_table = $wpdb->prefix . 'rbf_supplier_prices';
        $mappings_table = $wpdb->prefix . 'rbf_supplier_mappings';

        // Insert initial running log
        $wpdb->insert($logs_table, array(
            'supplier_id'      => $supplier_id,
            'sync_start'       => $sync_start,
            'status'           => 'running',
            'products_checked' => 0,
            'prices_changed'   => 0,
            'unchanged'        => 0,
            'needs_review'     => 0,
            'failed'           => 0
        ));
        $log_id = $wpdb->insert_id;

        $stats = array(
            'products_checked' => 0,
            'prices_changed'   => 0,
            'unchanged'        => 0,
            'needs_review'     => 0,
            'failed'           => 0
        );

        try {
            $provider = RBF_Supplier_Manager::get_instance()->get_provider($supplier_id);
            if (!$provider) {
                throw new Exception("Supplier provider '{$supplier_id}' not found.");
            }

            // Fetch or parse products
            if (!empty($options['feed_content'])) {
                $products = $provider->parse_feed_data($options['feed_content'], $options['format'] ?? 'csv');
            } else {
                $products = $provider->fetch_products($options);
            }

            foreach ($products as $product) {
                $stats['products_checked']++;

                $sku = sanitize_text_field($product['supplier_sku'] ?? '');
                $price = floatval($product['price'] ?? 0);
                $stock = sanitize_text_field($product['stock_status'] ?? 'in_stock');
                $prod_name = sanitize_text_field($product['product_name'] ?? $sku);

                if (empty($sku)) {
                    $stats['failed']++;
                    continue;
                }

                // Match product to local model and repair
                $match = RBF_Supplier_Manager::get_instance()->match_product($product);
                $model_id = $match['model_id'];
                $repair_id = $match['repair_id'];
                $match_status = $match['match_status'];

                // Price validation
                $is_price_valid = ($price > 0);
                $ver_status = ($match_status === 'verified' && $is_price_valid) ? 'verified' : 'needs_review';

                if ($ver_status === 'needs_review') {
                    $stats['needs_review']++;
                }

                // Check existing cached price
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, current_price, stock_status FROM $prices_table WHERE supplier_id = %s AND supplier_sku = %s LIMIT 1",
                    $supplier_id, $sku
                ), ARRAY_A);

                if ($existing) {
                    $old_price = floatval($existing['current_price']);
                    if (abs($old_price - $price) < 0.01 && $existing['stock_status'] === $stock) {
                        // Unchanged: update timestamp only
                        $wpdb->update(
                            $prices_table,
                            array('fetched_at' => current_time('mysql')),
                            !empty($existing['id']) ? array('id' => intval($existing['id'])) : array('supplier_id' => $supplier_id, 'supplier_sku' => $sku)
                        );
                        $stats['unchanged']++;
                    } else {
                        // Changed price
                        $wpdb->update(
                            $prices_table,
                            array(
                                'model_id'             => $model_id,
                                'repair_id'            => $repair_id,
                                'product_name'         => $prod_name,
                                'previous_price'       => $old_price,
                                'current_price'        => $price,
                                'price'                => $price,
                                'stock_status'         => $stock,
                                'verification_status'  => $ver_status,
                                'last_price_change_at' => current_time('mysql'),
                                'fetched_at'           => current_time('mysql'),
                                'updated_at'           => current_time('mysql')
                            ),
                            !empty($existing['id']) ? array('id' => intval($existing['id'])) : array('supplier_id' => $supplier_id, 'supplier_sku' => $sku)
                        );
                        $stats['prices_changed']++;
                    }
                } else {
                    // Insert new price cache
                    $wpdb->insert($prices_table, array(
                        'supplier_id'          => $supplier_id,
                        'supplier_sku'         => $sku,
                        'model_id'             => $model_id,
                        'repair_id'            => $repair_id,
                        'supplier_product_url' => esc_url_raw($product['product_url'] ?? ''),
                        'product_name'         => $prod_name,
                        'part_type'            => sanitize_text_field($product['part_type'] ?? ''),
                        'quality_grade'        => sanitize_text_field($product['quality_grade'] ?? ''),
                        'price'                => $price,
                        'current_price'        => $price,
                        'currency'             => sanitize_text_field($product['currency'] ?? 'AED'),
                        'stock_status'         => $stock,
                        'verification_status'  => $ver_status,
                        'fetched_at'           => current_time('mysql'),
                        'created_at'           => current_time('mysql'),
                        'updated_at'           => current_time('mysql')
                    ));
                    $stats['prices_changed']++;
                }

                // Auto-create / update mapping if verified
                if ($model_id && $repair_id) {
                    RBF_Supplier_Manager::get_instance()->save_mapping(
                        $supplier_id,
                        $model_id,
                        $repair_id,
                        $sku,
                        $product['product_url'] ?? '',
                        $ver_status
                    );
                }
            }

            $duration = round(microtime(true) - $start_time, 2);
            $final_status = ($stats['failed'] > 0 && $stats['prices_changed'] === 0) ? 'failed' : 'success';

            $wpdb->update($logs_table, array(
                'sync_finish'      => current_time('mysql'),
                'status'           => $final_status,
                'products_checked' => $stats['products_checked'],
                'prices_changed'   => $stats['prices_changed'],
                'unchanged'        => $stats['unchanged'],
                'needs_review'     => $stats['needs_review'],
                'failed'           => $stats['failed'],
                'duration_seconds' => $duration
            ), array('id' => $log_id));

            // Record last sync option
            update_option('rbf_last_supplier_sync', array(
                'time'      => current_time('mysql'),
                'supplier'  => $supplier_id,
                'stats'     => $stats,
                'duration'  => $duration
            ));

        } catch (Exception $e) {
            $duration = round(microtime(true) - $start_time, 2);
            $wpdb->update($logs_table, array(
                'sync_finish'      => current_time('mysql'),
                'status'           => 'failed',
                'error_message'    => $e->getMessage(),
                'duration_seconds' => $duration
            ), array('id' => $log_id));

            $this->release_lock();
            return array(
                'status'  => 'error',
                'message' => 'Sync failed: ' . $e->getMessage()
            );
        }

        $this->release_lock();
        $this->prune_old_logs();

        return array(
            'status'   => 'success',
            'message'  => sprintf('Sync complete in %ss. Checked: %d, Changed: %d, Unchanged: %d, Needs Review: %d',
                $duration, $stats['products_checked'], $stats['prices_changed'], $stats['unchanged'], $stats['needs_review']),
            'stats'    => $stats,
            'duration' => $duration
        );
    }

    /**
     * WP-Cron scheduled callback
     */
    public function run_scheduled_sync() {
        $this->run_sync('lxcell');
    }

    /**
     * Clear scheduled cron on deactivation
     */
    public static function clear_scheduled_sync() {
        $timestamp = wp_next_scheduled('rbf_supplier_price_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'rbf_supplier_price_sync');
        }
    }

    /**
     * AJAX handler for manual "Sync Now" button
     */
    public function ajax_manual_sync() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $supplier_id = sanitize_text_field($_POST['supplier_id'] ?? 'lxcell');
        $result = $this->run_sync($supplier_id);

        if ($result['status'] === 'success') {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * AJAX handler for uploading wholesale CSV price sheets
     */
    public function ajax_import_feed() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $supplier_id = sanitize_text_field($_POST['supplier_id'] ?? 'lxcell');
        $feed_content = '';
        $format = 'csv';

        // Check if an XLSX or CSV file was uploaded via $_FILES
        if (!empty($_FILES['feed_file']['tmp_name']) && is_uploaded_file($_FILES['feed_file']['tmp_name'])) {
            $filename = strtolower($_FILES['feed_file']['name'] ?? '');
            $feed_content = file_get_contents($_FILES['feed_file']['tmp_name']);
            if (strpos($filename, '.xlsx') !== false || substr($feed_content, 0, 4) === "PK\x03\x04") {
                $format = 'xlsx';
            } else {
                $format = 'csv';
            }
        } elseif (isset($_POST['csv_content']) && !empty(trim($_POST['csv_content']))) {
            $feed_content = wp_unslash($_POST['csv_content']);
            $format = (substr($feed_content, 0, 4) === "PK\x03\x04") ? 'xlsx' : 'csv';
        }

        if (empty($feed_content)) {
            wp_send_json_error('No feed content or file provided. Please upload an XLSX/CSV file or paste feed content.');
        }

        $result = $this->run_sync($supplier_id, array(
            'feed_content' => $feed_content,
            'format'       => $format
        ));

        if ($result['status'] === 'success') {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Prune logs older than 30 days or keep last 50 entries
     */
    private function prune_old_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'rbf_supplier_sync_logs';
        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE sync_start < %s", $cutoff));
    }
}
