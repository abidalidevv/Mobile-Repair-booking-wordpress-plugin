<?php
/**
 * Plugin Name: Repair Booking Form
 * Plugin URI: https://yourwebsite.com
 * Description: Multi-step repair booking form for mobile devices with cart functionality
 * Version: 1.1
 * Author: Abid Ali
 * License: GPL v2 or later
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RBF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RBF_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include error logger
require_once __DIR__ . '/includes/class-rbf-error-logger.php';

// Include helper and engine classes
require_once __DIR__ . '/includes/class-brands-models-manager.php';
require_once __DIR__ . '/includes/class-rbf-currency.php';
require_once __DIR__ . '/includes/class-rbf-whatsapp.php';
require_once __DIR__ . '/includes/class-rbf-tracker.php';
require_once __DIR__ . '/includes/class-rbf-pricing.php';
require_once __DIR__ . '/includes/class-rbf-catalog.php';
require_once __DIR__ . '/includes/suppliers/interface-rbf-supplier.php';
require_once __DIR__ . '/includes/suppliers/class-rbf-supplier-manager.php';
require_once __DIR__ . '/includes/suppliers/class-rbf-supplier-lxcell.php';
require_once __DIR__ . '/includes/suppliers/class-rbf-supplier-sync-manager.php';

class RepairBookingForm {
    
    public function __construct() {
        // Initialize helper instances
        if (class_exists('RBF_Currency')) {
            RBF_Currency::get_instance();
        }
        if (class_exists('RBF_WhatsApp')) {
            RBF_WhatsApp::get_instance();
        }
        if (class_exists('RBF_Tracker')) {
            RBF_Tracker::get_instance();
        }
        if (class_exists('RBF_Pricing')) {
            RBF_Pricing::get_instance();
        }
        if (class_exists('RBF_Catalog')) {
            RBF_Catalog::get_instance();
        }
        if (class_exists('RBF_Supplier_Manager')) {
            RBF_Supplier_Manager::get_instance();
        }
        if (class_exists('RBF_Supplier_Sync_Manager')) {
            RBF_Supplier_Sync_Manager::get_instance();
        }
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));

        // Check if plugin is disabled due to license deactivation
        if (get_option('rbf_plugin_disabled', false)) {
            add_action('admin_notices', array($this, 'show_license_disabled_notice'));
            return;
        }
        
        // Log plugin initialization
        rbf_log_info('Plugin constructor called', 'Plugin_Init');
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('repair_booking_form', array($this, 'render_form'));
        add_action('wp_ajax_rbf_get_models', array($this, 'ajax_get_models'));
        add_action('wp_ajax_nopriv_rbf_get_models', array($this, 'ajax_get_models'));
        add_action('wp_ajax_rbf_get_repairs', array($this, 'ajax_get_repairs'));
        add_action('wp_ajax_nopriv_rbf_get_repairs', array($this, 'ajax_get_repairs'));
        add_action('wp_ajax_rbf_submit_booking', array($this, 'ajax_submit_booking'));
        add_action('wp_ajax_nopriv_rbf_submit_booking', array($this, 'ajax_submit_booking'));
        
        // Admin features
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_rbf_save_brand', array($this, 'ajax_save_brand'));
        add_action('wp_ajax_rbf_delete_brand', array($this, 'ajax_delete_brand'));
        add_action('wp_ajax_rbf_save_model', array($this, 'ajax_save_model'));
        add_action('wp_ajax_rbf_delete_model', array($this, 'ajax_delete_model'));
        add_action('wp_ajax_rbf_save_repair', array($this, 'ajax_save_repair'));
        add_action('wp_ajax_rbf_delete_repair', array($this, 'ajax_delete_repair'));
        add_action('wp_ajax_rbf_update_booking_status', array($this, 'ajax_update_booking_status'));
        add_action('wp_ajax_rbf_get_booking_details', array($this, 'ajax_get_booking_details'));
        add_action('wp_ajax_rbf_get_invoice_data', array($this, 'ajax_get_invoice_data'));
        
        // New AJAX handlers for JSON-based operations
        add_action('wp_ajax_rbf_update_brand_json', array($this, 'ajax_update_brand_json'));
        add_action('wp_ajax_rbf_add_brand_json', array($this, 'ajax_add_brand_json'));
        add_action('wp_ajax_rbf_delete_brand_json', array($this, 'ajax_delete_brand_json'));
        add_action('wp_ajax_rbf_delete_model_json', array($this, 'ajax_delete_model_json'));
        add_action('wp_ajax_rbf_add_model_json', array($this, 'ajax_add_model_json'));
        add_action('wp_ajax_rbf_update_model_json', array($this, 'ajax_update_model_json'));
        add_action('wp_ajax_rbf_add_repair_json', array($this, 'ajax_add_repair_json'));
        add_action('wp_ajax_rbf_update_repair_json', array($this, 'ajax_update_repair_json'));
        add_action('wp_ajax_rbf_delete_repair_json', array($this, 'ajax_delete_repair_json'));
        
        // AJAX handlers for repair prices
        add_action('wp_ajax_rbf_update_repair_price', array($this, 'ajax_update_repair_price'));
        add_action('wp_ajax_rbf_generate_random_prices', array($this, 'ajax_generate_random_prices'));
        add_action('wp_ajax_rbf_export_prices', array($this, 'ajax_export_prices'));
        add_action('wp_ajax_rbf_import_prices', array($this, 'ajax_import_prices'));
        
        // AJAX handlers for bookings
        add_action('wp_ajax_rbf_add_booking', array($this, 'ajax_add_booking'));
        
        // AJAX handlers for pricing
        add_action('wp_ajax_rbf_save_default_prices', array($this, 'ajax_save_default_prices'));
        add_action('wp_ajax_rbf_bulk_update_prices', array($this, 'ajax_bulk_update_prices'));
        add_action('wp_ajax_rbf_setup_database', array($this, 'ajax_setup_database'));
        
        // Payment gateway AJAX handlers
        add_action('wp_ajax_rbf_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_rbf_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_rbf_create_payment_intent', array($this, 'ajax_create_payment_intent'));
        add_action('wp_ajax_nopriv_rbf_create_payment_intent', array($this, 'ajax_create_payment_intent'));
        add_action('wp_ajax_rbf_confirm_payment', array($this, 'ajax_confirm_payment'));
        add_action('wp_ajax_nopriv_rbf_confirm_payment', array($this, 'ajax_confirm_payment'));
        
        // Remove diagnostic menu
        add_action('admin_menu', array($this, 'remove_diagnostic_menu'));
        
        // Add activation hook
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Add test booking function for debugging
        add_action('wp_ajax_rbf_test_booking', array($this, 'ajax_test_booking'));
        
        // Register shortcodes
        add_action('init', array($this, 'register_shortcodes'));
        
        // Add license activation handler
        add_action('admin_init', array($this, 'handle_license_activation'));
        
        // Add license deactivation AJAX handler
        add_action('wp_ajax_rbf_deactivate_license', array($this, 'ajax_deactivate_license'));
        
        // Add license reactivation AJAX handler
        add_action('wp_ajax_rbf_reactivate_license', array($this, 'ajax_reactivate_license'));
        
        // Add invoice generation AJAX handler
        add_action('wp_ajax_rbf_generate_invoice', array($this, 'ajax_generate_invoice'));
        add_action('wp_ajax_nopriv_rbf_generate_invoice', array($this, 'ajax_generate_invoice'));
        
        // Add delete booking AJAX handler
        add_action('wp_ajax_rbf_delete_booking', array($this, 'ajax_delete_booking'));
        add_action('wp_ajax_rbf_update_database_structure', array($this, 'ajax_update_database_structure'));
        add_action('wp_ajax_rbf_fix_database', array($this, 'ajax_fix_database'));
        add_action('wp_ajax_rbf_regenerate_combinations', array($this, 'ajax_regenerate_combinations'));
    }
    
    /**
     * Register custom cron recurrence intervals
     */
    public function add_cron_intervals($schedules) {
        if (!isset($schedules['forty_eight_hours'])) {
            $schedules['forty_eight_hours'] = array(
                'interval' => 48 * HOUR_IN_SECONDS,
                'display'  => 'Every 48 Hours'
            );
        }
        return $schedules;
    }

    /**
     * Plugin deactivation cleanup
     */
    public function deactivate_plugin() {
        wp_clear_scheduled_hook('rbf_supplier_price_sync');
    }

    public function init() {
        // Load plugin textdomain at init (Standard WordPress Lifecycle)
        load_plugin_textdomain('repair-booking-form', false, dirname(plugin_basename(__FILE__)) . '/languages');
        // Create database tables if needed (only for bookings)
        $this->create_bookings_table();
        
        // Initialize Tier Pricing schema and seed data
        if (class_exists('RBF_Pricing')) {
            RBF_Pricing::get_instance()->create_tables();
        }

        // Initialize Catalog tables and sync from JSON if empty
        if (class_exists('RBF_Catalog')) {
            RBF_Catalog::get_instance()->sync_from_json_to_db();
        }
        
        // Run data migration if needed
        $this->maybe_migrate_data();
    }
    
    private function create_bookings_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Bookings table (enterprise structure with imei and currency)
        $bookings_table = $wpdb->prefix . 'rbf_bookings';
        $sql_bookings = "CREATE TABLE $bookings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(100) NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_email varchar(100) NOT NULL,
            brand varchar(50) NOT NULL,
            model varchar(100) NOT NULL,
            imei varchar(50) DEFAULT NULL,
            repair text NOT NULL,
            service_type varchar(50),
            service_date date,
            service_time time,
            address text,
            street_building varchar(200),
            city varchar(100),
            emirate varchar(50),
            notes text,
            currency varchar(10) NOT NULL DEFAULT 'AED',
            subtotal decimal(10,2) NOT NULL DEFAULT 0,
            vat_amount decimal(10,2) NOT NULL DEFAULT 0,
            total_amount decimal(10,2) NOT NULL DEFAULT 0,
            payment_status varchar(20) DEFAULT 'pending',
            payment_gateway varchar(50),
            transaction_id varchar(100),
            status varchar(20) DEFAULT 'pending',
            booking_id varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY booking_id (booking_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create bookings table
        dbDelta($sql_bookings);
        
        // Debug: Check if table was created
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
        if ($table_exists) {
            error_log('RBF Debug: Bookings table created successfully');
        } else {
            error_log('RBF Debug: Failed to create bookings table');
        }
    }
    
    /**
     * Manually create bookings table if it doesn't exist or migrate missing columns
     */
    public function ensure_bookings_table_exists() {
        global $wpdb;
        
        $bookings_table = $wpdb->prefix . 'rbf_bookings';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
        
        if (!$table_exists) {
            error_log('RBF Debug: Bookings table does not exist, creating it now...');
            $this->create_bookings_table();
            return true;
        }
        
        // Check if table structure has required columns
        $table_structure = $wpdb->get_results("DESCRIBE $bookings_table");
        $actual_columns = array_column($table_structure, 'Field');
        
        // Safely add missing columns without dropping existing customer booking data
        $required_columns = array(
            'imei' => "ADD COLUMN imei varchar(50) DEFAULT NULL AFTER model",
            'street_building' => "ADD COLUMN street_building varchar(200) DEFAULT NULL AFTER address",
            'city' => "ADD COLUMN city varchar(100) DEFAULT 'Dubai' AFTER street_building",
            'emirate' => "ADD COLUMN emirate varchar(50) DEFAULT 'Dubai' AFTER city",
            'currency' => "ADD COLUMN currency varchar(10) NOT NULL DEFAULT 'AED' AFTER notes",
            'subtotal' => "ADD COLUMN subtotal decimal(10,2) NOT NULL DEFAULT 0 AFTER currency",
            'vat_amount' => "ADD COLUMN vat_amount decimal(10,2) NOT NULL DEFAULT 0 AFTER subtotal",
            'total_amount' => "ADD COLUMN total_amount decimal(10,2) NOT NULL DEFAULT 0 AFTER vat_amount",
            'payment_status' => "ADD COLUMN payment_status varchar(20) DEFAULT 'pending' AFTER total_amount",
            'payment_gateway' => "ADD COLUMN payment_gateway varchar(50) DEFAULT NULL AFTER payment_status",
            'transaction_id' => "ADD COLUMN transaction_id varchar(100) DEFAULT NULL AFTER payment_gateway",
            'booking_id' => "ADD COLUMN booking_id varchar(50) DEFAULT NULL AFTER status"
        );
        foreach ($required_columns as $col => $sql_clause) {
            if (!in_array($col, $actual_columns)) {
                $wpdb->query("ALTER TABLE $bookings_table $sql_clause");
            }
        }
        
        error_log('RBF Debug: Bookings table verified and updated');
        return false;
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('rbf-main', RBF_PLUGIN_URL . 'assets/js/main.js', array('jquery'), '2.0.4', true);
        wp_enqueue_style('rbf-style', RBF_PLUGIN_URL . 'assets/css/style.css', array(), '2.0.4');
        
        // Payment gateway scripts
        if (get_option('rbf_paypal_enabled', false)) {
            $paypal_mode = get_option('rbf_paypal_mode', 'sandbox');
            $paypal_url = ($paypal_mode === 'live') ? 'https://www.paypal.com/sdk/js' : 'https://www.paypal.com/sdk/js';
            wp_enqueue_script('paypal-sdk', $paypal_url . '?client-id=' . get_option('rbf_paypal_client_id', ''), array(), null, true);
        }
        
        if (get_option('rbf_stripe_enabled', false)) {
            $stripe_mode = get_option('rbf_stripe_mode', 'test');
            $stripe_key = ($stripe_mode === 'live') ? get_option('rbf_stripe_publishable_key', '') : get_option('rbf_stripe_publishable_key', '');
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
            wp_localize_script('stripe-js', 'rbf_stripe', array(
                'publishable_key' => $stripe_key,
                'mode' => $stripe_mode
            ));
        }

        // Currency Data
        $currency_mgr = class_exists('RBF_Currency') ? RBF_Currency::get_instance() : null;
        $currencies_data = $currency_mgr ? $currency_mgr->get_currencies_data() : [];
        $exchange_rates = $currency_mgr ? $currency_mgr->get_exchange_rates() : ['AED' => 1.0];
        $default_currency = $currency_mgr ? $currency_mgr->get_default_currency() : 'AED';
        
        // Localize script for AJAX with complete enterprise details
        $rbf_script_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rbf_nonce'),
            'plugin_url' => RBF_PLUGIN_URL,
            'site_name' => get_bloginfo('name'),
            'site_address' => get_option('rbf_business_address', 'Dubai, UAE'),
            'site_phone' => get_option('rbf_business_phone', '+971 50 123 4567'),
            'site_email' => get_option('rbf_business_email', get_option('admin_email')),
            'site_logo' => get_option('rbf_business_logo', get_site_icon_url()),
            'vat_number' => get_option('rbf_vat_number', 'VAT No: 123456789012345'),
            'vat_rate' => floatval(get_option('rbf_vat_rate', 5)),
            'business_whatsapp' => get_option('rbf_business_whatsapp', '+971501234567'),
            'paypal_enabled' => get_option('rbf_paypal_enabled', false),
            'stripe_enabled' => get_option('rbf_stripe_enabled', false),
            'default_currency' => $default_currency,
            'currencies' => $currencies_data,
            'rates' => $exchange_rates
        );
        wp_localize_script('rbf-main', 'rbf_ajax', $rbf_script_data);
        wp_localize_script('rbf-main', 'rbfData', $rbf_script_data);
    }
    
    public function render_form($atts) {
        ob_start();
        include RBF_PLUGIN_PATH . 'templates/form.php';
        return ob_get_clean();
    }
    
    public function ajax_get_models() {
        check_ajax_referer('rbf_nonce', 'nonce');
        
        $brand = sanitize_text_field($_POST['brand']);
        $models = $this->get_models_by_brand($brand);
        
        wp_send_json_success($models);
    }
    
    public function ajax_get_repairs() {
        try {
            check_ajax_referer('rbf_nonce', 'nonce');
            
            $brand = sanitize_text_field($_POST['brand']);
            $model = sanitize_text_field($_POST['model']);
            $country_code = !empty($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : null;
            
            $repairs = $this->get_repairs_by_model($brand, $model, $country_code);
            
            wp_send_json_success($repairs);
        } catch (Exception $e) {
            wp_send_json_error('Error fetching repairs: ' . $e->getMessage());
        }
    }
    
    public function ajax_submit_booking() {
        try {
            check_ajax_referer('rbf_nonce', 'nonce');
        
        global $wpdb;
        
        // Ensure bookings table exists
        $this->ensure_bookings_table_exists();
        
        // Support both JSON-encoded booking_data and raw direct POST fields
        $booking_data = array();
        if (!empty($_POST['booking_data'])) {
            $booking_data = json_decode(stripslashes($_POST['booking_data']), true);
        }
        if (empty($booking_data) || !is_array($booking_data)) {
            $booking_data = array(
                'name' => sanitize_text_field($_POST['name'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                'selected_brand' => sanitize_text_field($_POST['selected_brand'] ?? ''),
                'selected_model' => sanitize_text_field($_POST['selected_model'] ?? ''),
                'imei' => sanitize_text_field($_POST['imei'] ?? ''),
                'service_type' => sanitize_text_field($_POST['service_type'] ?? 'pickup'),
                'address' => sanitize_textarea_field($_POST['address'] ?? ''),
                'street_building' => sanitize_text_field($_POST['street_building'] ?? ''),
                'city' => sanitize_text_field($_POST['city'] ?? 'Dubai'),
                'emirate' => sanitize_text_field($_POST['emirate'] ?? 'Dubai'),
                'service_date' => sanitize_text_field($_POST['service_date'] ?? ''),
                'service_time' => sanitize_text_field($_POST['service_time'] ?? ''),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
                'currency' => sanitize_text_field($_POST['currency'] ?? 'AED'),
                'subtotal' => floatval($_POST['subtotal'] ?? 0),
                'vat_amount' => floatval($_POST['vat_amount'] ?? 0),
                'total_amount' => floatval($_POST['total_amount'] ?? 0),
                'cart_items' => isset($_POST['cart_items']) ? (array)$_POST['cart_items'] : array()
            );
        }
        
        if (empty($booking_data['name']) && empty($_POST['name'])) {
            wp_send_json_error('Invalid booking data');
        }
        
        // Handle custom brand/model if applicable
        if (isset($booking_data['custom_brand']) && isset($booking_data['custom_model'])) {
            $custom_brand_id = $this->save_custom_brand_and_model(
                $booking_data['custom_brand'], 
                $booking_data['custom_model']
            );
            
            if ($custom_brand_id === false) {
                error_log('RBF Debug: Failed to save custom brand/model');
                wp_send_json_error('Failed to save custom brand/model');
            }
            
            // Override brand and model with custom values
            $booking_data['selected_brand'] = $custom_brand_id;
            $booking_data['selected_model'] = $custom_brand_id; // This might need adjustment based on your exact requirements
        }
        
        // Validate required fields
        $required_fields = array('name', 'email', 'phone', 'selected_brand', 'selected_model');
        foreach ($required_fields as $field) {
            if (empty($booking_data[$field])) {
                error_log('RBF Debug: Missing required field: ' . $field);
                wp_send_json_error('Missing required field: ' . $field);
            }
        }
        
        // Generate unique booking ID - shorter format with brand name
        $booking_id = 'eFIX-' . strtoupper(substr(md5(time() . rand()), 0, 5));
        
        // Authoritative Server-Side Pricing Recalculation (Phase 1, Item 3)
        // Never trust client-submitted prices, subtotal, VAT, or total.
        $server_model_id = 0;
        if (is_numeric($booking_data['selected_model'])) {
            $server_model_id = intval($booking_data['selected_model']);
        } else {
            $found_model = RBF_Catalog::get_instance()->get_model_by_name(
                $booking_data['selected_brand'], 
                $booking_data['selected_model']
            );
            if ($found_model) {
                $server_model_id = intval($found_model['id']);
            }
        }

        $pricing_engine = RBF_Pricing::get_instance();
        $server_subtotal = 0.0;
        $country_code = !empty($booking_data['country_code']) ? sanitize_text_field($booking_data['country_code']) : null;

        if (isset($booking_data['cart_items']) && is_array($booking_data['cart_items'])) {
            foreach ($booking_data['cart_items'] as &$item) {
                $repair_id = intval($item['id'] ?? 0);
                $repair_name = sanitize_text_field($item['name'] ?? '');

                if ($repair_id <= 0 && !empty($repair_name)) {
                    $repair_id = intval($wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}rbf_repairs WHERE LOWER(name) = LOWER(%s) LIMIT 1",
                        $repair_name
                    )));
                }

                if ($repair_id > 0) {
                    $verified_unit_price = $pricing_engine->get_price($server_model_id, $repair_id, $country_code);
                    $server_subtotal += $verified_unit_price;
                    $item['price'] = $verified_unit_price;
                }
            }
            unset($item);
        }

        if ($server_subtotal > 0) {
            $subtotal = $server_subtotal;
        } else {
            // Safety fallback only for custom brand/model quote on inspection
            $subtotal = floatval($booking_data['subtotal'] ?? 0);
        }

        if ($subtotal <= 0) {
            error_log('RBF Debug: Invalid subtotal amount: ' . $subtotal);
            wp_send_json_error('Invalid subtotal amount');
        }

        $vat_rate = floatval(get_option('rbf_vat_rate', 5)) / 100.0;
        $vat_amount = round($subtotal * $vat_rate, 2);
        $total_amount = round($subtotal + $vat_amount, 2);
        $currency = !empty($booking_data['currency']) ? sanitize_text_field($booking_data['currency']) : 'AED';
        $imei = !empty($booking_data['imei']) ? sanitize_text_field($booking_data['imei']) : null;
        
        // Prepare repair names (from cart items)
        $repair_names = array();
        if (isset($booking_data['cart_items']) && is_array($booking_data['cart_items'])) {
            foreach ($booking_data['cart_items'] as $item) {
                $repair_names[] = $item['name'];
            }
        }
        $repair_string = implode(', ', $repair_names);
        
        // Prepare database insert
        $insert_data = array(
            'customer_name' => sanitize_text_field($booking_data['name']),
            'customer_phone' => sanitize_text_field($booking_data['phone']),
            'customer_email' => sanitize_text_field($booking_data['email']),
            'brand' => sanitize_text_field($booking_data['selected_brand']),
            'model' => sanitize_text_field($booking_data['selected_model']),
            'imei' => $imei,
            'repair' => sanitize_text_field($repair_string),
            'service_type' => sanitize_text_field($booking_data['service_type'] ?? 'store_visit'),
            'service_date' => !empty($booking_data['service_date']) ? $booking_data['service_date'] : null,
            'service_time' => !empty($booking_data['service_time']) ? $booking_data['service_time'] : null,
            'address' => sanitize_textarea_field($booking_data['address'] ?? ''),
            'street_building' => sanitize_text_field($booking_data['street_building'] ?? ''),
            'city' => sanitize_text_field($booking_data['city'] ?? ''),
            'emirate' => sanitize_text_field($booking_data['emirate'] ?? ''),
            'notes' => sanitize_textarea_field($booking_data['notes'] ?? ''),
            'currency' => $currency,
            'subtotal' => $subtotal,
            'vat_amount' => $vat_amount,
            'total_amount' => $total_amount,
            'status' => 'pending',
            'booking_id' => $booking_id,
            'created_at' => current_time('mysql')
        );
        
        error_log('RBF Debug: Insert data: ' . print_r($insert_data, true));
        
        // Insert booking into database
        $result = $wpdb->insert(
            $wpdb->prefix . 'rbf_bookings',
            $insert_data,
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            error_log('RBF Debug: Database insert failed. Last error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save booking: ' . $wpdb->last_error);
        }
        
        $inserted_id = $wpdb->insert_id;
        $insert_data['id'] = $inserted_id;
        
        // WhatsApp Notifications (Automated API + direct link)
        $direct_wa_url = '';
        if (class_exists('RBF_WhatsApp')) {
            $wa_mgr = RBF_WhatsApp::get_instance();
            $wa_mgr->send_booking_notification($insert_data);
            $direct_wa_url = $wa_mgr->get_direct_whatsapp_url($insert_data);
        }
        
        // Send confirmation email (if method exists)
        if (method_exists($this, 'send_booking_confirmation')) {
            $this->send_booking_confirmation($booking_data, $booking_id);
        }
        
        // Return success response
        wp_send_json_success(array(
            'booking_id' => $booking_id,
            'id' => $inserted_id,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'vat_amount' => $vat_amount,
            'total_amount' => $total_amount,
            'whatsapp_url' => $direct_wa_url,
            'message' => 'Booking submitted successfully!'
        ));
        } catch (\Throwable $e) {
            error_log('RBF Booking Submission Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            wp_send_json_error('Booking processing error: ' . $e->getMessage());
        }
    }
    
    /**
     * Test booking function for debugging
     */
    public function ajax_test_booking() {
        check_ajax_referer('rbf_nonce', 'nonce');
        
        global $wpdb;
        
        // Ensure bookings table exists
        $this->ensure_bookings_table_exists();
        
        // Create a test booking
        $test_booking = array(
            'customer_name' => 'Test Customer',
            'customer_phone' => '+971501234567',
            'customer_email' => 'test@example.com',
            'brand' => 'Apple',
            'model' => 'iPhone 15',
            'repair' => 'Screen Replacement',
            'service_type' => 'pickup',
            'service_date' => date('Y-m-d', strtotime('+1 day')),
            'service_time' => '10:00',
            'address' => 'Test Address',
            'street_building' => 'Test Building',
            'city' => 'Dubai',
            'emirate' => 'Dubai',
            'notes' => 'Test booking for debugging',
            'subtotal' => 500.00,
            'vat_amount' => 25.00,
            'total_amount' => 525.00,
            'status' => 'pending',
            'booking_id' => 'TEST-' . strtoupper(substr(md5(time()), 0, 5)),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'rbf_bookings',
            $test_booking,
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            wp_send_json_error('Test booking failed: ' . $wpdb->last_error);
        } else {
            wp_send_json_success(array(
                'message' => 'Test booking created successfully',
                'booking_id' => $test_booking['booking_id'],
                'id' => $wpdb->insert_id
            ));
        }
    }
    
    /**
     * Get model ID by brand and model names
     */
    private function get_model_id_by_names($brand_name, $model_name) {
        // Use JSON data source instead of database
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        return $brands_manager->get_model_id_by_names($brand_name, $model_name);
    }
    
    /**
     * Get repair price for a specific model and repair with fallbacks
     */
    public function get_repair_price($model_id, $repair_id) {
        global $wpdb;
        
        error_log("RBF Debug: get_repair_price called with model_id: $model_id, repair_id: $repair_id");
        
        // First, try to get the specific price for this model and repair
        $pricing_table = $wpdb->prefix . 'rbf_pricing';
        $price = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $pricing_table WHERE model_id = %d AND repair_id = %d",
            $model_id, $repair_id
        ));
        
        error_log("RBF Debug: Specific price from pricing table: " . ($price ?: 'null'));
        
        if ($price && $price > 0) {
            return floatval($price);
        }
        
        // If no specific price, get the default price for this repair
        $default_prices_table = $wpdb->prefix . 'rbf_default_prices';
        $default_price = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $default_prices_table WHERE repair_id = %d",
            $repair_id
        ));
        
        error_log("RBF Debug: Default price from default_prices table: " . ($default_price ?: 'null'));
        
        return $default_price ? floatval($default_price) : 0.00;
    }
    
    private function get_models_by_brand($brand) {
        if (class_exists('RBF_Catalog')) {
            return RBF_Catalog::get_instance()->get_models_by_brand($brand);
        }
        return array();
    }
    
    private function get_all_brands() {
        if (class_exists('RBF_Catalog')) {
            return RBF_Catalog::get_instance()->get_brands();
        }
        return array();
    }
    
    private function get_repairs_by_model($brand, $model, $country_code = null) {
        if (class_exists('RBF_Catalog')) {
            return RBF_Catalog::get_instance()->get_repairs_by_model($brand, $model, $country_code);
        }
        return array();
    }
    
    private function save_booking($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'repair_bookings';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'brand' => $data['brand'],
                'model' => $data['model'],
                'repairs' => json_encode($data['repairs']),
                'total' => $data['total'],
                'notes' => $data['notes'],
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    private function send_confirmation_email($data) {
        $to = $data['email'];
        $subject = 'Repair Booking Confirmation';
        $message = "Dear {$data['name']},\n\n";
        $message .= "Your repair booking has been confirmed.\n\n";
        $message .= "Device: {$data['brand']} {$data['model']}\n";
        $message .= "Total: AED " . number_format($data['total'], 2) . "\n\n";
        $message .= "We will contact you shortly to schedule your repair.\n\n";
        $message .= "Thank you for choosing our service!";
        
        wp_mail($to, $subject, $message);
    }
    
    // Old database functions removed - using JSON now
    
    // Old migration functions removed - using JSON now
    
    /**
     * Get device models for migration
     */
    private function get_device_models() {
        return array(
            'iPhone' => array(
                'iPhone 16 Pro Max' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-16-pro-max.jpg',
                'iPhone 15 Pro Max' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-15-pro-max.jpg',
                'iPhone 15 Pro' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-15-pro.jpg',
                'iPhone 15 Plus' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-15-plus-.jpg',
                'iPhone 15' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-15.jpg',
                'iPhone 14 Pro Max' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-14-pro-max-.jpg',
                'iPhone 14 Pro' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-14-pro.jpg',
                'iPhone 14 Plus' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-14-plus.jpg',
                'iPhone 14' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-14.jpg',
                'iPhone 13 Pro Max' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-13-pro-max.jpg',
                'iPhone 13 Pro' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-13-pro.jpg',
                'iPhone 13' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-13.jpg',
                'iPhone 13 Mini' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-13-mini.jpg',
                'iPhone 12 Pro Max' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-12-pro-max.jpg',
                'iPhone 12 Pro' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-12-pro.jpg',
                'iPhone 12' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-12.jpg',
                'iPhone 12 Mini' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-12-mini.jpg',
                'iPhone 11 Pro Max' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-11-pro max.jpg',
                'iPhone 11 Pro' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-11-pro.jpg',
                'iPhone 11' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-11.jpg',
                'iPhone XS Max' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-xs-max.jpg',
                'iPhone XS' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-xs.jpg',
                'iPhone XR' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-xr.jpg',
                'iPhone X' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-x.jpg',
                'iPhone 8 Plus' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-8-plus.jpg',
                'iPhone 8' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-8.jpg',
                'iPhone 7 Plus' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-7-plus.jpg',
                'iPhone 7' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-7.jpg',
                'iPhone 6S Plus' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-6s-plus.jpg',
                'iPhone 6S' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-6s1.jpg',
                'iPhone SE (2022)' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-se-2022.jpg',
                'iPhone SE (3rd Gen)' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-se-3rd gen.jpg',
                'iPhone SE' => RBF_PLUGIN_URL . 'Brands/iphone_modals/apple-iphone-se-.jpg'
            ),
            'Samsung' => array(
                'Galaxy S24 Ultra' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s24-ultra-5g.jpg',
                'Galaxy S24+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s24-plus-5g.jpg',
                'Galaxy S24' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s24-5g.jpg',
                'Galaxy S23 Ultra' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s23-ultra.jpg',
                'Galaxy S23+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s23-plus-5g.jpg',
                'Galaxy S23' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s23.jpg',
                'Galaxy S22 Ultra' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s22-ultra-5g.jpg',
                'Galaxy S22+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s22-plus.jpg',
                'Galaxy S22' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s22.jpg',
                'Galaxy S21 Ultra' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s21-ultra.jpg',
                'Galaxy S21+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s21-plus.jpg',
                'Galaxy S21' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s21.jpg',
                'Galaxy S20 Ultra' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s20-ultra.jpg',
                'Galaxy S20+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s20-plus.jpg',
                'Galaxy S20' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s20.jpg',
                'Galaxy S10+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s10-plus.jpg',
                'Galaxy S10' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s10.jpg',
                'Galaxy S10e' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s10e.jpg',
                'Galaxy S10 5G' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s10-5g.jpg',
                'Galaxy S9+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s9-plus.jpg',
                'Galaxy S9' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s9-.jpg',
                'Galaxy S8+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s8-plus-.jpg',
                'Galaxy S8' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s8.jpg',
                'Galaxy S7 Edge' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s7-edge.jpg',
                'Galaxy S7' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s7.jpg',
                'Galaxy S6 Edge+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s6-edge-plus.jpg',
                'Galaxy S6 Edge' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s6-edge.jpg',
                'Galaxy S6' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-s6.jpg',
                'Galaxy Note 20 Ultra' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-note20-ultra.jpg',
                'Galaxy Note 20' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-note20.jpg',
                'Galaxy Note 10+' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-note10-plus.jpg',
                'Galaxy Note 10' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-note10.jpg',
                'Galaxy Note 9' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-note9.jpg',
                'Galaxy Note 8' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-note-8.jpg',
                'Galaxy Z Fold 5' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-fold5-5g.jpg',
                'Galaxy Z Fold 4' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-fold4.jpg',
                'Galaxy Z Fold 3' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-fold3.jpg',
                'Galaxy Z Fold 2' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-fold2-5g.jpg',
                'Galaxy Z Flip 5' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-flip5-5g.jpg',
                'Galaxy Z Flip 4' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-flip4.jpg',
                'Galaxy Z Flip 3' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-flip3.jpg',
                'Galaxy Z Flip' => RBF_PLUGIN_URL . 'Brands/samsung_modals/samsung-galaxy-z-flip.jpg'
            ),
            'Google Pixel' => array(
                'Pixel 9 Pro Fold' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-9-pro-fold.jpg',
                'Pixel 9 Pro XL' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-9-pro-xl-.jpg',
                'Pixel 9 Pro' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-9-pro-.jpg',
                'Pixel 9' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-9-.jpg',
                'Pixel 9a' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-9a.jpg',
                'Pixel 8 Pro' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-8-pro.jpg',
                'Pixel 8' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-8.jpg',
                'Pixel 8a' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-8a.jpg',
                'Pixel 7 Pro' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel7-pro.jpg',
                'Pixel 7' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel7.jpg',
                'Pixel 7a' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-7a.jpg',
                'Pixel 6 Pro' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-6-pro.jpg',
                'Pixel 6' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-6.jpg',
                'Pixel 6a' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-6a.jpg',
                'Pixel 5' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-5.jpg',
                'Pixel 5a' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-5a.jpg',
                'Pixel 4 XL' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-4XL.jpg',
                'Pixel 4' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-4.jpg',
                'Pixel 4a' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-4a.jpg',
                'Pixel 4a 5G' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-4a-5g.jpg',
                'Pixel 3 XL' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-3xl-.jpg',
                'Pixel 3' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-3-.jpg',
                'Pixel 3a XL' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-3a-xl-.jpg',
                'Pixel 3a' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-3a.jpg',
                'Pixel 2' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-2.jpg',
                'Pixel XL 2' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-xl2-.jpg',
                'Pixel XL' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel-xl.jpg',
                'Pixel' => RBF_PLUGIN_URL . 'Brands/google_modals/google-pixel.jpg'
            ),
            'OnePlus' => array(
                'OnePlus 12' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-12.jpg',
                'OnePlus 12R' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-12r.jpg',
                'OnePlus 11' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-11.jpg',
                'OnePlus 10 Pro' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-10-pro.jpg',
                'OnePlus 10T' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-10t.jpg',
                'OnePlus 9 Pro' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-9-pro-.jpg',
                'OnePlus 9' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-9-.jpg',
                'OnePlus 9R' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-9r.jpg',
                'OnePlus 8 Pro' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-8-pro.jpg',
                'OnePlus 8' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-8.jpg',
                'OnePlus 8T' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-8t.jpg',
                'OnePlus 7T Pro' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-7t-pro.jpg',
                'OnePlus 7T' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-7t-.jpg',
                'OnePlus 7 Pro' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-7-pro.jpg',
                'OnePlus 7' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-7.jpg',
                'OnePlus 6T' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-6t.jpg',
                'OnePlus 6' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-6.jpg',
                'OnePlus 5T' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-5t.jpg',
                'OnePlus 3T' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-3t.jpg',
                'OnePlus X' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-x.jpg',
                'OnePlus Nord CE3' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-nord-ce3.jpg',
                'OnePlus Nord 2T' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-nord-2t.jpg',
                'OnePlus Ace 2' => RBF_PLUGIN_URL . 'Brands/oneplus_modals/oneplus-ace2.jpg'
            )
        );
    }

    /**
     * Add admin menus
     */
    public function add_admin_menus() {
        add_menu_page(
            'Dashboard',
            'Repair Booking',
            'manage_options',
            'repair-booking',
            array($this, 'admin_dashboard'),
            'dashicons-admin-generic',
            30
        );
        
        add_submenu_page(
            'repair-booking',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'repair-booking',
            array($this, 'admin_dashboard')
        );
        
        add_submenu_page(
            'repair-booking',
            'Brands',
            'Brands',
            'manage_options',
            'repair-booking-brands',
            array($this, 'admin_brands')
        );
        
        add_submenu_page(
            'repair-booking',
            'Models',
            'Models',
            'manage_options',
            'repair-booking-models',
            array($this, 'admin_models')
        );
        
        add_submenu_page(
            'repair-booking',
            'Repairs',
            'Repairs',
            'manage_options',
            'repair-booking-repairs',
            array($this, 'admin_repairs')
        );
        
        add_submenu_page(
            'repair-booking',
            'Bookings',
            'Bookings',
            'manage_options',
            'repair-booking-bookings',
            array($this, 'admin_bookings')
        );
        
        add_submenu_page(
            'repair-booking',
            'Payment Settings',
            'Payment Settings',
            'manage_options',
            'repair-booking-payments',
            array($this, 'admin_payment_settings')
        );
        
        add_submenu_page(
            'repair-booking',
            'Repair Prices',
            'Repair Prices',
            'manage_options',
            'repair-booking-prices',
            array($this, 'admin_repair_prices')
        );

        add_submenu_page(
            'repair-booking',
            'Currency & Rates',
            'Currency & Rates',
            'manage_options',
            'repair-booking-currency',
            array($this, 'admin_currency_settings')
        );

        add_submenu_page(
            'repair-booking',
            'WhatsApp & Alerts',
            'WhatsApp & Alerts',
            'manage_options',
            'repair-booking-whatsapp',
            array($this, 'admin_whatsapp_settings')
        );
        
        add_submenu_page(
            'repair-booking',
            'About',
            'About',
            'manage_options',
            'repair-booking-about',
            array($this, 'admin_about')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Enqueue for all repair-booking admin pages (robust check for hook or query param)
        $is_rbf = (isset($_GET['page']) && strpos($_GET['page'], 'repair-booking') !== false) || 
                  (is_string($hook) && strpos($hook, 'repair-booking') !== false);
                  
        if ($is_rbf) {
            wp_enqueue_style('rbf-admin', RBF_PLUGIN_URL . 'assets/css/admin.css', array(), '2.0.4');
            wp_enqueue_media(); // For image uploads
            
            // Enqueue jQuery for admin pages
            wp_enqueue_script('jquery');
            
            // Localize admin AJAX script
            wp_localize_script('jquery', 'rbf_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rbf_admin_nonce'),
                'plugin_url' => RBF_PLUGIN_URL
            ));
        }
    }
    
    /**
     * Admin Dashboard - Enterprise Executive UI
     */
    public function admin_dashboard() {
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        $brands = $brands_manager->get_brands();
        $repair_services = $brands_manager->get_repair_services();
        
        $brands_count = count($brands);
        $models_count = array_sum(array_map(function($brand) { return count($brand['models']); }, $brands));
        $repairs_count = count($repair_services);
        
        global $wpdb;
        $this->ensure_bookings_table_exists();
        
        $bookings_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings");
        $pending_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE status = 'pending'");
        $confirmed_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE status = 'confirmed'");
        $completed_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE status = 'completed'");
        
        $today = current_time('Y-m-d');
        $today_bookings = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE DATE(created_at) = %s",
            $today
        ));
        
        $today_earnings = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(subtotal) FROM {$wpdb->prefix}rbf_bookings WHERE DATE(created_at) = %s AND status != 'cancelled'",
            $today
        ))) ?: 0.0;
        
        $total_revenue = floatval($wpdb->get_var(
            "SELECT SUM(subtotal) FROM {$wpdb->prefix}rbf_bookings WHERE status != 'cancelled'"
        )) ?: 0.0;
        
        $currency_mgr = class_exists('RBF_Currency') ? RBF_Currency::get_instance() : null;
        $default_curr = $currency_mgr ? $currency_mgr->get_default_currency() : 'AED';
        
        $recent_bookings = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}rbf_bookings 
            ORDER BY created_at DESC 
            LIMIT 8
        ");
        
        $service_distribution = $wpdb->get_results("
            SELECT service_type, COUNT(*) as count
            FROM {$wpdb->prefix}rbf_bookings 
            GROUP BY service_type 
            ORDER BY count DESC
        ");

        // Handle quick maintenance actions
        if (isset($_POST['rbf_sync_db']) && check_admin_referer('rbf_admin_action')) {
            $this->ensure_bookings_table_exists();
            echo '<div class="notice notice-success is-dismissible"><p><strong>Database Schema Synchronized!</strong> All tables and columns are up to date.</p></div>';
        }
        ?>
        <div class="rbf-admin-wrap">
            <!-- Executive Header -->
            <div class="rbf-admin-header">
                <div class="rbf-header-left">
                    <h1>
                        eFix Repair Booking Engine
                        <span class="rbf-version-badge">v2.0.0 Enterprise</span>
                    </h1>
                    <p class="rbf-header-desc">Commercial booking, pricing engine, multi-currency & WhatsApp automation for device repair shops.</p>
                </div>
                <div class="rbf-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" class="rbf-btn-light">
                        📋 View All Bookings (<?php echo $bookings_count; ?>)
                    </a>
                </div>
            </div>

            <!-- Admin Navigation Tabs -->
            <div class="rbf-nav-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking')); ?>" class="rbf-nav-tab active">📊 Dashboard</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" class="rbf-nav-tab">📅 Bookings</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-prices')); ?>" class="rbf-nav-tab">💰 Bulk Prices</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-brands')); ?>" class="rbf-nav-tab">📱 Brands (<?php echo $brands_count; ?>)</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-models')); ?>" class="rbf-nav-tab">📲 Models (<?php echo $models_count; ?>)</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-repairs')); ?>" class="rbf-nav-tab">🛠️ Repairs (<?php echo $repairs_count; ?>)</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-currency')); ?>" class="rbf-nav-tab">💱 Currency & VAT</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-whatsapp')); ?>" class="rbf-nav-tab">💬 WhatsApp Alerts</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-payments')); ?>" class="rbf-nav-tab">⚙️ Payment Gateways</a>
            </div>

            <!-- Onboarding / Quick Guide for Non-Technical Admins -->
            <div class="rbf-onboarding-card">
                <h3>🚀 Quick Start Guide for Store Admins</h3>
                <p style="margin: 0; color: #475569; font-size: 13.5px;">Follow these 3 easy steps to start taking online repair bookings:</p>
                <div class="rbf-steps-grid">
                    <div class="rbf-step-card">
                        <div class="rbf-step-badge">1</div>
                        <div class="rbf-step-info">
                            <h4>Set Currency & VAT</h4>
                            <p>Go to <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-currency')); ?>">Currency & VAT</a> to set your local currency (AED, SAR, USD) and regional tax rate.</p>
                        </div>
                    </div>
                    <div class="rbf-step-card">
                        <div class="rbf-step-badge">2</div>
                        <div class="rbf-step-info">
                            <h4>Connect WhatsApp</h4>
                            <p>Enter your shop WhatsApp number in <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-whatsapp')); ?>">WhatsApp Alerts</a> to receive instant customer chats.</p>
                        </div>
                    </div>
                    <div class="rbf-step-card">
                        <div class="rbf-step-badge">3</div>
                        <div class="rbf-step-info">
                            <h4>Publish on Website</h4>
                            <p>Place shortcode <span class="rbf-code-pill">[repair_booking_form]</span> on your booking page, and <span class="rbf-code-pill">[rbf_track_repair]</span> on your tracking page.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPI Metric Cards -->
            <div class="rbf-dashboard-stats">
                <div class="rbf-stat-card">
                    <div class="rbf-stat-icon-wrapper primary">📋</div>
                    <div class="rbf-stat-details">
                        <div class="rbf-stat-number"><?php echo number_format($bookings_count); ?></div>
                        <div class="rbf-stat-label">Total Bookings</div>
                    </div>
                </div>
                <div class="rbf-stat-card">
                    <div class="rbf-stat-icon-wrapper success">💰</div>
                    <div class="rbf-stat-details">
                        <div class="rbf-stat-number"><?php echo esc_html($default_curr . ' ' . number_format($today_earnings, 2)); ?></div>
                        <div class="rbf-stat-label">Today's Revenue (<?php echo $today_bookings; ?> Orders)</div>
                    </div>
                </div>
                <div class="rbf-stat-card">
                    <div class="rbf-stat-icon-wrapper info">🛠️</div>
                    <div class="rbf-stat-details">
                        <div class="rbf-stat-number"><?php echo number_format($confirmed_bookings); ?></div>
                        <div class="rbf-stat-label">In Progress / Confirmed</div>
                    </div>
                </div>
                <div class="rbf-stat-card">
                    <div class="rbf-stat-icon-wrapper warning">⏳</div>
                    <div class="rbf-stat-details">
                        <div class="rbf-stat-number"><?php echo number_format($pending_bookings); ?></div>
                        <div class="rbf-stat-label">Pending Confirmation</div>
                    </div>
                </div>
            </div>

            <!-- 2-Column Main Content -->
            <div class="rbf-grid-2col">
                <!-- Left: Recent Bookings Table -->
                <div class="rbf-card">
                    <div class="rbf-card-header">
                        <h3 class="rbf-card-title">📦 Recent Repair Orders</h3>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" style="font-size: 13px; font-weight: 600; text-decoration: none; color: #017c36;">View All →</a>
                    </div>
                    <div class="rbf-table-responsive">
                        <table class="rbf-modern-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Customer</th>
                                    <th>Device</th>
                                    <th>Service Mode</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_bookings)) : ?>
                                    <?php foreach ($recent_bookings as $b) : 
                                        $status_class = 'rbf-badge-' . sanitize_html_class($b->status);
                                        $clean_phone = preg_replace('/[^0-9]/', '', $b->customer_phone);
                                        $wa_msg = rawurlencode("Hi " . $b->customer_name . ", regarding your device repair booking #" . $b->id . " (" . $b->brand . " " . $b->model . ")...");
                                        $wa_link = "https://wa.me/" . $clean_phone . "?text=" . $wa_msg;
                                    ?>
                                        <tr>
                                            <td><strong>#<?php echo esc_html($b->id); ?></strong></td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo esc_html($b->customer_name); ?></div>
                                                <div style="font-size: 12px; color: #64748b;">
                                                    <?php echo esc_html($b->customer_phone); ?>
                                                    <?php if ($clean_phone) : ?>
                                                        <a href="<?php echo esc_url($wa_link); ?>" target="_blank" class="rbf-wa-btn" style="margin-left: 6px;" title="Chat on WhatsApp">💬 WA</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;"><?php echo esc_html($b->brand . ' ' . $b->model); ?></div>
                                                <?php if (!empty($b->imei)) : ?>
                                                    <div style="font-size: 11px; color: #94a3b8;">IMEI: <?php echo esc_html($b->imei); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($this->get_service_type_label($b->service_type)); ?></td>
                                            <td><strong><?php echo esc_html(($b->currency ?: $default_curr) . ' ' . number_format($b->subtotal, 2)); ?></strong></td>
                                            <td>
                                                <span class="rbf-badge <?php echo esc_attr($status_class); ?>">
                                                    <?php echo esc_html(ucfirst($b->status)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings&action=view&id=' . $b->id)); ?>" class="button button-small">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 35px; color: #64748b;">
                                            No bookings received yet. Place the <span class="rbf-code-pill">[repair_booking_form]</span> shortcode on a page to begin receiving customer orders!
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right Column: System Breakdown & Catalog -->
                <div>
                    <!-- Catalog Summary Card -->
                    <div class="rbf-card">
                        <div class="rbf-card-header">
                            <h3 class="rbf-card-title">📱 Catalog Summary</h3>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                                <span style="font-weight: 500; color: #475569;">Supported Brands</span>
                                <span style="font-weight: 700; color: #0f172a;"><?php echo $brands_count; ?> Brands</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                                <span style="font-weight: 500; color: #475569;">Device Models</span>
                                <span style="font-weight: 700; color: #0f172a;"><?php echo $models_count; ?> Models (iPhone 17, S25, etc.)</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                                <span style="font-weight: 500; color: #475569;">Pre-loaded Services</span>
                                <span style="font-weight: 700; color: #0f172a;"><?php echo $repairs_count; ?> Services</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 500; color: #475569;">Base Currency</span>
                                <span style="font-weight: 700; color: #017c36;"><?php echo esc_html($default_curr); ?> (Live Rates Active)</span>
                            </div>
                        </div>
                        <div style="margin-top: 18px;">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-prices')); ?>" class="rbf-btn-primary" style="width: 100%; justify-content: center;">
                                💰 Manage Model Prices
                            </a>
                        </div>
                    </div>

                    <!-- Service Modes Distribution -->
                    <?php if (!empty($service_distribution)) : ?>
                        <div class="rbf-card">
                            <div class="rbf-card-header">
                                <h3 class="rbf-card-title">🛵 Service Channels</h3>
                            </div>
                            <div>
                                <?php foreach ($service_distribution as $service) : 
                                    $pct = $bookings_count > 0 ? round(($service->count / $bookings_count) * 100) : 0;
                                ?>
                                    <div style="margin-bottom: 14px;">
                                        <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px;">
                                            <span><strong><?php echo esc_html($this->get_service_type_label($service->service_type)); ?></strong></span>
                                            <span><?php echo $service->count; ?> (<?php echo $pct; ?>%)</span>
                                        </div>
                                        <div style="background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="background: #017c36; width: <?php echo $pct; ?>%; height: 100%; border-radius: 4px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Diagnostics & Database Tool -->
            <div class="rbf-diagnostics-box">
                <div class="rbf-diagnostics-info">
                    <h4>🛠️ System Maintenance & Health</h4>
                    <p>Ensure database tables and columns (IMEI, Currency, Transactions) are in sync with the latest plugin engine.</p>
                </div>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('rbf_admin_action'); ?>
                    <button type="submit" name="rbf_sync_db" class="button button-secondary">
                        🔄 Synchronize Database Schema
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Admin Brands Management
     */
    public function admin_brands() {
        // Ensure admin CSS is loaded
        wp_enqueue_style('rbf-admin', RBF_PLUGIN_URL . 'assets/css/admin.css', array(), '1.1.0');
        
        // Use JSON data source instead of database
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        $brands = $brands_manager->get_brands();
        
        // Display all brands in a beautiful grid layout
        echo '<div class="wrap rbf-brands-container">';
        echo '<h1>Brands Management</h1>';
        echo '<p class="description">All brands from shared data source (JSON file) - Beautiful Grid Layout</p>';
        
        // Global Add New Brand Button at the top
        echo '<div class="rbf-add-brand-section">';
        echo '<button class="button button-primary" id="add-new-brand-global">Add New Brand</button>';
        echo '</div>';
        
        // Display brands in a responsive grid
        echo '<div class="rbf-brands-grid">';
        
        foreach ($brands as $brand) {
            $brand_id = isset($brand['id']) ? $brand['id'] : 0;
            $brand_name = isset($brand['name']) ? $brand['name'] : '';
            $brand_desc = isset($brand['description']) ? $brand['description'] : '';
            $brand_logo = isset($brand['logo']) ? $brand['logo'] : 'Brands/other_brand.jpg';
            $models_list = isset($brand['models']) && is_array($brand['models']) ? $brand['models'] : array();

            echo '<div class="rbf-brand-card" data-brand-id="' . esc_attr($brand_id) . '">';
            
            // Brand Logo and Image - Made bigger
            echo '<div class="rbf-brand-logo">';
            echo '<img src="' . esc_url(RBF_PLUGIN_URL . $brand_logo) . '" alt="' . esc_attr($brand_name) . '">';
            echo '</div>';
            
            // Brand Details
            echo '<div class="rbf-brand-details">';
            echo '<h3>' . esc_html($brand_name) . '</h3>';
            if (!empty($brand_desc)) {
                echo '<p class="rbf-brand-description">' . esc_html($brand_desc) . '</p>';
            }
            echo '<div class="rbf-brand-stats">';
            echo '<span class="rbf-models-count">' . count($models_list) . ' Models</span>';
            echo '</div>';
            echo '</div>';
            
            // Brand Actions
            echo '<div class="rbf-brand-actions">';
            echo '<button class="button edit-brand" data-brand-id="' . esc_attr($brand_id) . '" data-brand-name="' . esc_attr($brand_name) . '" data-brand-description="' . esc_attr($brand_desc) . '">Edit</button>';
            echo '<button class="button delete-brand" data-brand-id="' . esc_attr($brand_id) . '" data-brand-name="' . esc_attr($brand_name) . '">Delete</button>';
            echo '</div>';
            
            echo '</div>'; // Close brand card
        }
        
        echo '</div>'; // Close brands grid
        echo '</div>'; // Close main container
        
        // Add JavaScript for functionality
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Define ajaxurl for admin context
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            // Global Add New Brand Button
            $('#add-new-brand-global').on('click', function() {
                const addForm = `
                <form id="add-brand-form">
                    <div class="rbf-form-group">
                        <label for="new-brand-name">Brand Name *</label>
                        <input type="text" id="new-brand-name" required placeholder="Enter brand name">
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-brand-description">Description</label>
                        <textarea id="new-brand-description" rows="3" placeholder="Enter brand description"></textarea>
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-brand-logo">Logo Image *</label>
                        <input type="file" id="new-brand-logo" accept="image/*" required>
                        <small>Upload a logo image for the brand</small>
                    </div>
                    <div class="rbf-modal-actions">
                        <button type="button" class="button close-modal">Cancel</button>
                        <button type="submit" class="button button-primary">Add Brand</button>
                    </div>
                </form>`;
                
                showModal('Add New Brand', addForm);
                
                // Handle form submission
                $('#add-brand-form').on('submit', function(e) {
                    e.preventDefault();
                    const brandName = $('#new-brand-name').val();
                    const brandDescription = $('#new-brand-description').val();
                    const logoFile = $('#new-brand-logo')[0].files[0];
                    
                    if (!logoFile) {
                        alert('Please select a logo image');
                        return;
                    }
                    
                    // Create FormData for file upload
                    const formData = new FormData();
                    formData.append('action', 'rbf_add_brand_json');
                    formData.append('name', brandName);
                    formData.append('description', brandDescription);
                    formData.append('logo', logoFile);
                    formData.append('nonce', '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('Brand added successfully!');
                                location.reload();
                            } else {
                                alert('Error adding brand: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error adding brand. Please try again.');
                        }
                    });
                    closeModal();
                });
            });

            // Delete Brand Button
            $('.delete-brand').on('click', function() {
                const brandId = $(this).data('brand-id');
                const brandName = $(this).data('brand-name');
                
                if (confirm('Are you sure you want to delete the brand "' + brandName + '"? This will also delete all associated models.')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rbf_delete_brand_json',
                            brand_id: brandId,
                            nonce: '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Brand deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error deleting brand: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error deleting brand. Please try again.');
                        }
                    });
                }
            });

            // Edit Brand Button
            $('.edit-brand').on('click', function() {
                const brandId = $(this).data('brand-id');
                const brandName = $(this).data('brand-name');
                const brandDescription = $(this).data('brand-description');
                
                const editForm = `
                <form id="edit-brand-form">
                    <div class="rbf-form-group">
                        <label for="edit-brand-name">Brand Name *</label>
                        <input type="text" id="edit-brand-name" value="${brandName}" required>
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-brand-description">Description</label>
                        <textarea id="edit-brand-description" rows="3" placeholder="Enter brand description">${brandDescription || ''}</textarea>
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-brand-logo">Logo Image</label>
                        <input type="file" id="edit-brand-logo" accept="image/*">
                        <small>Leave empty to keep current logo</small>
                    </div>
                    <input type="hidden" id="edit-brand-id" value="${brandId}">
                    <div class="rbf-modal-actions">
                        <button type="button" class="button close-modal">Cancel</button>
                        <button type="submit" class="button button-primary">Update Brand</button>
                    </div>
                </form>`;
                
                showModal('Edit Brand: ' + brandName, editForm);
                
                // Handle form submission
                $('#edit-brand-form').on('submit', function(e) {
                    e.preventDefault();
                    const brandId = $('#edit-brand-id').val();
                    const newName = $('#edit-brand-name').val();
                    const newDescription = $('#edit-brand-description').val();
                    const logoFile = $('#edit-brand-logo')[0].files[0];
                    
                    // Create FormData for file upload
                    const formData = new FormData();
                    formData.append('action', 'rbf_update_brand_json');
                    formData.append('brand_id', brandId);
                    formData.append('name', newName);
                    formData.append('description', newDescription);
                    if (logoFile) {
                        formData.append('logo', logoFile);
                    }
                    formData.append('nonce', '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('Brand updated successfully!');
                                location.reload();
                            } else {
                                alert('Error updating brand: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error updating brand. Please try again.');
                        }
                    });
                    closeModal();
                });
            });

            // Modal functions
            function showModal(title, content) {
                const modal = `
                <div id="rbf-modal" class="rbf-modal">
                    <div class="rbf-modal-content">
                        <h2>${title}</h2>
                        ${content}
                        <button class="button close-modal" style="position: absolute; top: 15px; right: 15px;">×</button>
                    </div>
                </div>`;
                
                $('body').append(modal);
                $('#rbf-modal').show();
            }

            function closeModal() {
                $('#rbf-modal').remove();
            }

            // Close modal when clicking outside or on close button
            $(document).on('click', '.rbf-modal, .close-modal', function(e) {
                if (e.target === this || $(e.target).hasClass('close-modal')) {
                    closeModal();
                }
            });

            // --- TAB 4: Save Settings & Labour Costs ---
            $('#rbf-btn-save-settings').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Saving Settings...');

                var markup = parseFloat($('#rbf-global-markup-input').val()) || 13;
                var labourCosts = {};

                $('.rbf-labour-input').each(function() {
                    var repairId = $(this).data('repair-id');
                    var val = parseFloat($(this).val()) || 0;
                    labourCosts[repairId] = val;
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_save_pricing_settings',
                        nonce: nonce,
                        global_markup: markup,
                        labour_costs: labourCosts
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Pricing Settings & Labour Costs');
                        if (resp.success) {
                            showToast('Pricing settings and labour costs saved successfully!', true);
                        } else {
                            showToast('Error: ' + (resp.data || 'Failed to save'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Pricing Settings & Labour Costs');
                        showToast('Network error while saving settings', false);
                    }
                });
            });

            // --- TAB 5: Supplier Price Sync & Feed Import ---
            $('#rbf-btn-toggle-feed-import').on('click', function() {
                $('#rbf-feed-import-box').slideToggle(200);
            });
            $('#rbf-btn-cancel-feed').on('click', function() {
                $('#rbf-feed-import-box').slideUp(200);
            });

            $('#rbf-btn-manual-sync').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Syncing with Supplier...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_manual_supplier_sync',
                        nonce: nonce,
                        supplier_id: 'lxcell'
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync Supplier Prices Now');
                        if (resp.success) {
                            showToast(resp.data.message || 'Sync completed successfully!', true);
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            showToast('Sync Error: ' + (resp.data || 'Failed'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Sync Supplier Prices Now');
                        showToast('Network error during supplier sync', false);
                    }
                });
            });

            $('#rbf-btn-process-feed').on('click', function() {
                var $btn = $(this);
                var csv = $('#rbf-csv-feed-content').val().trim();
                if (!csv) {
                    alert('Please paste CSV price data first.');
                    return;
                }

                $btn.prop('disabled', true).text('Parsing & Ingesting...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_import_supplier_feed',
                        nonce: nonce,
                        supplier_id: 'lxcell',
                        csv_content: csv
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Parse & Update Supplier Prices');
                        if (resp.success) {
                            showToast(resp.data.message || 'Feed imported successfully!', true);
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            showToast('Import Error: ' + (resp.data || 'Failed to import feed'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Parse & Update Supplier Prices');
                        showToast('Network error during feed import', false);
                    }
                });
            });

            // --- TAB 6: Supplier Mappings ---
            $('#rbf-btn-save-mapping').on('click', function() {
                var $btn = $(this);
                var supplier = $('#rbf-map-supplier').val();
                var modelId = $('#rbf-map-model').val();
                var repairId = $('#rbf-map-repair').val();
                var sku = $('#rbf-map-sku').val().trim();

                if (!modelId || !repairId || !sku) {
                    alert('Please select Model, Repair, and enter Supplier SKU.');
                    return;
                }

                $btn.prop('disabled', true).text('Saving Mapping...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_save_supplier_mapping',
                        nonce: nonce,
                        supplier_id: supplier,
                        model_id: modelId,
                        repair_id: repairId,
                        supplier_sku: sku,
                        match_status: 'verified'
                    },
                    success: function(resp) {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2"></span> Save Mapping');
                        if (resp.success) {
                            showToast('Mapping saved successfully!', true);
                            setTimeout(function() { location.reload(); }, 1200);
                        } else {
                            showToast('Error: ' + (resp.data || 'Failed to save mapping'), false);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-plus-alt2"></span> Save Mapping');
                        showToast('Network error saving mapping', false);
                    }
                });
            });

            $(document).on('click', '.rbf-btn-delete-mapping', function() {
                if (!confirm('Remove this supplier mapping?')) return;
                var id = $(this).data('id');
                var $row = $(this).closest('tr');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_delete_supplier_mapping',
                        nonce: nonce,
                        id: id
                    },
                    success: function(resp) {
                        if (resp.success) {
                            $row.fadeOut(300, function() { $(this).remove(); });
                            showToast('Mapping removed.', true);
                        } else {
                            showToast('Error removing mapping', false);
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Admin Models Management  
     */
    public function admin_models() {
        // Ensure admin CSS is loaded
        wp_enqueue_style('rbf-admin', RBF_PLUGIN_URL . 'assets/css/admin.css', array(), '1.1.0');
        
        // Use JSON data source instead of database
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        $brands = $brands_manager->get_brands();
        
        // Display all models categorized under their parent brands
        echo '<div class="wrap rbf-models-container">';
        echo '<h1>Models Management</h1>';
        echo '<p class="description">All models from shared data source (JSON file) - Categorized by Parent Brand</p>';
        
        // Global Add New Model Button at the top
        echo '<div class="rbf-add-model-section">';
        echo '<button class="button button-primary" id="add-new-model-global">Add New Model</button>';
        echo '</div>';
        
        // Display models grouped by brand
        foreach ($brands as $brand) {
            if (empty($brand['models'])) {
                continue; // Skip brands with no models
            }
            
            echo '<div class="rbf-brand-section">';
            echo '<div class="rbf-brand-header">';
            echo '<div class="rbf-brand-info">';
            echo '<img src="' . esc_url(RBF_PLUGIN_URL . $brand['logo']) . '" alt="' . esc_attr($brand['name']) . '" class="rbf-brand-logo-small">';
            echo '<h2>' . esc_html($brand['name']) . '</h2>';
            echo '<span class="rbf-model-count">' . count($brand['models']) . ' Models</span>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="rbf-models-grid">';
            
            foreach ($brand['models'] as $model) {
                $model_id = isset($model['id']) ? $model['id'] : (isset($model['name']) ? sanitize_title($model['name']) : '0');
                $model_name = isset($model['name']) ? $model['name'] : '';
                $model_img = isset($model['image']) ? $model['image'] : 'Brands/other_brand.jpg';
                $brand_id = isset($brand['id']) ? $brand['id'] : '0';
                $brand_name = isset($brand['name']) ? $brand['name'] : '';

                echo '<div class="rbf-model-card" data-model-id="' . esc_attr($model_id) . '">';
                echo '<div class="rbf-model-image">';
                echo '<img src="' . esc_url(RBF_PLUGIN_URL . $model_img) . '" alt="' . esc_attr($model_name) . '">';
                echo '</div>';
                
                echo '<div class="rbf-model-details">';
                echo '<h3>' . esc_html($model_name) . '</h3>';
                echo '<p class="rbf-model-parent">Parent: ' . esc_html($brand_name) . '</p>';
                if (isset($model['series'])) {
                    echo '<p class="rbf-model-series">Series: ' . esc_html($model['series']) . '</p>';
                }
                echo '</div>';
                
                echo '<div class="rbf-model-actions">';
                echo '<button class="button edit-model" data-model-id="' . esc_attr($model_id) . '" data-model-name="' . esc_attr($model_name) . '" data-parent-brand="' . esc_attr($brand_name) . '" data-parent-brand-id="' . esc_attr($brand_id) . '">Edit</button>';
                echo '<button class="button delete-model" data-model-id="' . esc_attr($model_id) . '" data-model-name="' . esc_attr($model_name) . '">Delete</button>';
                echo '</div>';
                echo '</div>';
            }
            
            echo '</div>'; // Close models grid
            echo '</div>'; // Close brand section
        }
        
        echo '</div>'; // Close main container
        
        // Add JavaScript for functionality
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Define ajaxurl for admin context
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            const brands = <?php echo json_encode($brands); ?>;
            
            // Global Add New Model Button
            $('#add-new-model-global').on('click', function() {
                const addForm = `
                <form id="add-model-form">
                    <div class="rbf-form-group">
                        <label for="new-model-name">Model Name *</label>
                        <input type="text" id="new-model-name" required placeholder="Enter model name">
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-model-parent-brand">Parent Brand *</label>
                        <select id="new-model-parent-brand" required>
                            <option value="">Select Parent Brand</option>
                            ${brands.map(brand => `<option value="${brand.id}">${brand.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-model-series">Series</label>
                        <input type="text" id="new-model-series" placeholder="e.g., iPhone 15, Galaxy S24">
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-model-image">Model Image *</label>
                        <input type="file" id="new-model-image" accept="image/*" required>
                    </div>
                    <div class="rbf-modal-actions">
                        <button type="button" class="button close-modal">Cancel</button>
                        <button type="submit" class="button button-primary">Add Model</button>
                    </div>
                </form>`;
                
                showModal('Add New Model', addForm);
                
                // Handle form submission
                $('#add-model-form').on('submit', function(e) {
                    e.preventDefault();
                    const modelName = $('#new-model-name').val();
                    const parentBrandId = $('#new-model-parent-brand').val();
                    const series = $('#new-model-series').val();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rbf_add_model_json',
                            name: modelName,
                            parent_brand_id: parentBrandId,
                            series: series,
                            nonce: '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Model added successfully!');
                                location.reload();
                            } else {
                                alert('Error adding model: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error adding model. Please try again.');
                        }
                    });
                    closeModal();
                });
            });

            // Delete Model Button
            $('.delete-model').on('click', function() {
                const modelId = $(this).data('model-id');
                const modelName = $(this).data('model-name');
                
                if (confirm('Are you sure you want to delete the model "' + modelName + '"?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rbf_delete_model_json',
                            model_id: modelId,
                            nonce: '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Model deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error deleting model: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error deleting model. Please try again.');
                        }
                    });
                }
            });

            // Edit Model Button
            $('.edit-model').on('click', function() {
                const modelId = $(this).data('model-id');
                const modelName = $(this).data('model-name');
                const parentBrand = $(this).data('parent-brand');
                const parentBrandId = $(this).data('parent-brand-id');
                const modelCard = $(this).closest('.rbf-model-card');
                const series = modelCard.find('.rbf-model-series').text().replace('Series: ', '');
                
                const editForm = `
                <form id="edit-model-form">
                    <div class="rbf-form-group">
                        <label for="edit-model-name">Model Name *</label>
                        <input type="text" id="edit-model-name" value="${modelName}" required>
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-model-parent-brand">Parent Brand *</label>
                        <select id="edit-model-parent-brand" required>
                            ${brands.map(brand => `<option value="${brand.id}" ${brand.id == parentBrandId ? 'selected' : ''}>${brand.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-model-series">Series</label>
                        <input type="text" id="edit-model-series" value="${series}" placeholder="e.g., iPhone 15, Galaxy S24">
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-model-image">Model Image</label>
                        <input type="file" id="edit-model-image" accept="image/*">
                        <small>Leave empty to keep current image</small>
                    </div>
                    <input type="hidden" id="edit-model-id" value="${modelId}">
                    <div class="rbf-modal-actions">
                        <button type="button" class="button close-modal">Cancel</button>
                        <button type="submit" class="button button-primary">Update Model</button>
                    </div>
                </form>`;
                
                showModal('Edit Model: ' + modelName, editForm);
                
                // Handle form submission
                $('#edit-model-form').on('submit', function(e) {
                    e.preventDefault();
                    const modelId = $('#edit-model-id').val();
                    const newName = $('#edit-model-name').val();
                    const newParentBrandId = $('#edit-model-parent-brand').val();
                    const newSeries = $('#edit-model-series').val();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rbf_update_model_json',
                            model_id: modelId,
                            name: newName,
                            parent_brand_id: newParentBrandId,
                            series: newSeries,
                            nonce: '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Model updated successfully!');
                                location.reload();
                            } else {
                                alert('Error updating model: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error updating model. Please try again.');
                        }
                    });
                    closeModal();
                });
            });

            // Modal functions
            function showModal(title, content) {
                const modal = `
                <div id="rbf-modal" class="rbf-modal">
                    <div class="rbf-modal-content">
                        <h2>${title}</h2>
                        ${content}
                        <button class="button close-modal" style="position: absolute; top: 15px; right: 15px;">×</button>
                    </div>
                </div>`;
                
                $('body').append(modal);
                $('#rbf-modal').show();
            }

            function closeModal() {
                $('#rbf-modal').remove();
            }

            // Close modal when clicking outside or on close button
            $(document).on('click', '.rbf-modal, .close-modal', function(e) {
                if (e.target === this || $(e.target).hasClass('close-modal')) {
                    closeModal();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Admin Repairs Management
     */
    public function admin_repairs() {
        // Ensure admin CSS is loaded
        wp_enqueue_style('rbf-admin', RBF_PLUGIN_URL . 'assets/css/admin.css', array(), '1.1.0');
        
        // Use JSON data source instead of database
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        $repair_services = $brands_manager->get_repair_services();
        
        // Display all repair services in a beautiful grid layout
        echo '<div class="wrap rbf-repairs-container">';
        echo '<h1>Repair Services Management</h1>';
        echo '<p class="description">All repair services from shared data source (JSON file) - Beautiful Grid Layout</p>';
        
        // Global Add New Repair Service Button at the top
        echo '<div class="rbf-add-repair-section">';
        echo '<button class="button button-primary" id="add-new-repair-global">Add New Repair Service</button>';
        echo '</div>';
        
        // Display repair services in a responsive grid
        echo '<div class="rbf-repairs-grid">';
        
        foreach ($repair_services as $service) {
            echo '<div class="rbf-repair-card" data-service-id="' . esc_attr($service['id']) . '">';
            
            // Service Icon
            echo '<div class="rbf-repair-icon">';
            echo '<img src="' . esc_url(RBF_PLUGIN_URL . $service['icon']) . '" alt="' . esc_attr($service['name']) . '">';
            echo '</div>';
            
            // Service Details
            echo '<div class="rbf-repair-details">';
            echo '<h3>' . esc_html($service['name']) . '</h3>';
            if (isset($service['description'])) {
                echo '<p class="rbf-repair-description">' . esc_html($service['description']) . '</p>';
            }
            if (isset($service['price'])) {
                echo '<p class="rbf-repair-price">Price: AED ' . esc_html($service['price']) . '</p>';
            }
            if (isset($service['duration'])) {
                echo '<p class="rbf-repair-duration">Duration: ' . esc_html($service['duration']) . '</p>';
            }
            echo '</div>';
            
            // Service Actions
            echo '<div class="rbf-repair-actions">';
            echo '<button class="button edit-repair" data-service-id="' . esc_attr($service['id']) . '" data-service-name="' . esc_attr($service['name']) . '" data-service-description="' . esc_attr($service['description']) . '" data-service-price="' . esc_attr($service['price']) . '" data-service-duration="' . esc_attr($service['duration']) . '" data-service-icon="' . esc_attr($service['icon']) . '">Edit</button>';
            echo '<button class="button delete-repair" data-service-id="' . esc_attr($service['id']) . '" data-service-name="' . esc_attr($service['name']) . '">Delete</button>';
            echo '</div>';
            
            echo '</div>'; // Close repair card
        }
        
        echo '</div>'; // Close repairs grid
        echo '</div>'; // Close main container
        
        // Add JavaScript for functionality
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Define ajaxurl for admin context
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            // Global Add New Repair Service Button
            $('#add-new-repair-global').on('click', function() {
                const addForm = `
                <form id="add-repair-form">
                    <div class="rbf-form-group">
                        <label for="new-repair-name">Service Name *</label>
                        <input type="text" id="new-repair-name" required placeholder="Enter service name">
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-repair-description">Description</label>
                        <textarea id="new-repair-description" rows="3" placeholder="Enter service description"></textarea>
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-repair-price">Price (AED)</label>
                        <input type="number" id="new-repair-price" placeholder="0.00" step="0.01">
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-repair-duration">Duration</label>
                        <input type="text" id="new-repair-duration" placeholder="e.g., 1-2 hours">
                    </div>
                    <div class="rbf-form-group">
                        <label for="new-repair-icon">Icon Image *</label>
                        <input type="file" id="new-repair-icon" accept="image/*" required>
                    </div>
                    <div class="rbf-modal-actions">
                        <button type="button" class="button close-modal">Cancel</button>
                        <button type="submit" class="button button-primary">Add Service</button>
                    </div>
                </form>`;
                
                showModal('Add New Repair Service', addForm);
                
                // Handle form submission
                $('#add-repair-form').on('submit', function(e) {
                    e.preventDefault();
                    const serviceName = $('#new-repair-name').val();
                    const serviceDescription = $('#new-repair-description').val();
                    const servicePrice = $('#new-repair-price').val();
                    const serviceDuration = $('#new-repair-duration').val();
                    
                    // Handle file upload
                    const iconFile = $('#new-repair-icon')[0].files[0];
                    if (!iconFile) {
                        alert('Please select an icon image');
                        return;
                    }
                    
                    // Create FormData for file upload
                    const formData = new FormData();
                    formData.append('action', 'rbf_add_repair_json');
                    formData.append('name', serviceName);
                    formData.append('description', serviceDescription);
                    formData.append('price', servicePrice);
                    formData.append('duration', serviceDuration);
                    formData.append('icon', iconFile);
                    formData.append('nonce', '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('Repair service added successfully!');
                                location.reload();
                            } else {
                                alert('Error adding repair service: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error adding repair service. Please try again.');
                        }
                    });
                    closeModal();
                });
            });

            // Delete Repair Service Button
            $('.delete-repair').on('click', function() {
                const serviceId = $(this).data('service-id');
                const serviceName = $(this).data('service-name');
                
                if (confirm('Are you sure you want to delete the repair service "' + serviceName + '"?')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'rbf_delete_repair_json',
                            service_id: serviceId,
                            nonce: '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Repair service deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error deleting repair service: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error deleting repair service. Please try again.');
                        }
                    });
                }
            });

            // Edit Repair Service Button
            $('.edit-repair').on('click', function() {
                const serviceId = $(this).data('service-id');
                const serviceName = $(this).data('service-name');
                const serviceDescription = $(this).data('service-description');
                const servicePrice = $(this).data('service-price');
                const serviceDuration = $(this).data('service-duration');
                const serviceIcon = $(this).data('service-icon');
                
                const editForm = `
                <form id="edit-repair-form">
                    <div class="rbf-form-group">
                        <label for="edit-repair-name">Service Name *</label>
                        <input type="text" id="edit-repair-name" value="${serviceName}" required>
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-repair-description">Description</label>
                        <textarea id="edit-repair-description" rows="3" placeholder="Enter service description">${serviceDescription || ''}</textarea>
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-repair-price">Price (AED)</label>
                        <input type="number" id="edit-repair-price" placeholder="0.00" step="0.01" value="${servicePrice || ''}">
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-repair-duration">Duration</label>
                        <input type="text" id="edit-repair-duration" placeholder="e.g., 1-2 hours" value="${serviceDuration || ''}">
                    </div>
                    <div class="rbf-form-group">
                        <label for="edit-repair-icon">Icon Image</label>
                        <input type="file" id="edit-repair-icon" accept="image/*">
                        <small>Leave empty to keep current icon</small>
                        <input type="hidden" id="current-icon-path" value="${serviceIcon}">
                    </div>
                    <input type="hidden" id="edit-repair-id" value="${serviceId}">
                    <div class="rbf-modal-actions">
                        <button type="button" class="button close-modal">Cancel</button>
                        <button type="submit" class="button button-primary">Update Service</button>
                    </div>
                </form>`;
                
                showModal('Edit Repair Service: ' + serviceName, editForm);
                
                // Handle form submission
                $('#edit-repair-form').on('submit', function(e) {
                    e.preventDefault();
                    const serviceId = $('#edit-repair-id').val();
                    const newName = $('#edit-repair-name').val();
                    const newDescription = $('#edit-repair-description').val();
                    const newPrice = $('#edit-repair-price').val();
                    const newDuration = $('#edit-repair-duration').val();
                    
                    // Handle file upload for edit
                    const iconFile = $('#edit-repair-icon')[0].files[0];
                    let iconPath = null;
                    
                    // If no new file selected, we need to get the current icon path
                    if (!iconFile) {
                        // Use the current icon path from the hidden input
                        iconPath = $('#current-icon-path').val();
                    }
                    
                    // Create FormData for file upload
                    const formData = new FormData();
                    formData.append('action', 'rbf_update_repair_json');
                    formData.append('service_id', serviceId);
                    formData.append('name', newName);
                    formData.append('description', newDescription);
                    formData.append('price', newPrice);
                    formData.append('duration', newDuration);
                    if (iconFile) {
                        formData.append('icon', iconFile);
                    } else if (iconPath) {
                        formData.append('icon_path', iconPath);
                    }
                    formData.append('nonce', '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                alert('Repair service updated successfully!');
                                location.reload();
                            } else {
                                alert('Error updating repair service: ' + response.data);
                            }
                        },
                        error: function() {
                            alert('Error updating repair service. Please try again.');
                        }
                    });
                    closeModal();
                });
            });

            // Modal functions
            function showModal(title, content) {
                const modal = `
                <div id="rbf-modal" class="rbf-modal">
                    <div class="rbf-modal-content">
                        <h2>${title}</h2>
                        ${content}
                        <button class="button close-modal" style="position: absolute; top: 15px; right: 15px;">×</button>
                    </div>
                </div>`;
                
                $('body').append(modal);
                $('#rbf-modal').show();
            }

            function closeModal() {
                $('#rbf-modal').remove();
            }

            // Close modal when clicking outside or on close button
            $(document).on('click', '.rbf-modal, .close-modal', function(e) {
                if (e.target === this || $(e.target).hasClass('close-modal')) {
                    closeModal();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Admin Bookings Management
     */
    public function admin_bookings() {
        global $wpdb;
        
        // Get all bookings
        $bookings = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}rbf_bookings 
            ORDER BY created_at DESC
        ");
        
        echo '<div class="wrap rbf-bookings-page">';
        echo '<h1>Bookings Management</h1>';
        
        // Add New Booking Button
        echo '<div class="rbf-bookings-actions" style="margin-bottom: 20px;">';
        echo '<a href="#" class="button button-primary" id="add-new-booking">Add New Booking</a>';
        echo '</div>';
        
        if (empty($bookings)) {
            echo '<p>No bookings found.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>ID</th>';
            echo '<th>Customer</th>';
            echo '<th>Contact</th>';
            echo '<th>Device</th>';
            echo '<th>Repairs</th>';
            echo '<th>Service Type</th>';
            echo '<th>Total</th>';
            echo '<th>Status</th>';
            echo '<th>Date</th>';
            echo '<th>Actions</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($bookings as $booking) {
                // Get service type icon and label
                $service_type_icon = $this->get_service_type_icon($booking->service_type);
                $service_type_label = $this->get_service_type_label($booking->service_type);
                
                echo '<tr>';
                echo '<td>' . esc_html($booking->id) . '</td>';
                echo '<td>' . esc_html($booking->customer_name) . '</td>';
                echo '<td>' . esc_html($booking->customer_email) . '<br>' . esc_html($booking->customer_phone) . '</td>';
                echo '<td>' . esc_html($booking->brand) . ' ' . esc_html($booking->model) . '</td>';
                echo '<td>' . esc_html($booking->repair) . '</td>';
                echo '<td>' . $service_type_icon . ' ' . esc_html($service_type_label) . '</td>';
                echo '<td>AED ' . esc_html($booking->total_amount) . '</td>';
                echo '<td>';
                echo '<select class="booking-status-select" data-booking-id="' . esc_attr($booking->id) . '">';
                echo '<option value="pending" ' . selected($booking->status, 'pending', false) . '>Pending</option>';
                echo '<option value="confirmed" ' . selected($booking->status, 'confirmed', false) . '>Confirmed</option>';
                echo '<option value="in_progress" ' . selected($booking->status, 'in_progress', false) . '>In Progress</option>';
                echo '<option value="completed" ' . selected($booking->status, 'completed', false) . '>Completed</option>';
                echo '<option value="cancelled" ' . selected($booking->status, 'cancelled', false) . '>Cancelled</option>';
                echo '</select>';
                echo '</td>';
                echo '<td>' . esc_html($booking->created_at) . '</td>';
                echo '<td>';
                echo '<a href="#" class="button button-small view-booking" data-booking-id="' . esc_attr($booking->id) . '">View</a> ';
                echo '<a href="#" class="button button-small edit-booking" data-booking-id="' . esc_attr($booking->id) . '">Edit</a> ';
                echo '<a href="#" class="button button-small delete-booking" data-booking-id="' . esc_attr($booking->id) . '">Delete</a> ';
                echo '<a href="#" class="button button-small print-invoice" data-booking-id="' . esc_attr($booking->id) . '">Print Invoice</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        // Add New Booking Modal
        echo '<div id="add-booking-modal" class="rbf-modal" style="display: none;">';
        echo '<div class="rbf-modal-content" style="max-width: 600px;">';
        echo '<h2>Add New Booking</h2>';
        echo '<form id="add-booking-form">';
        echo '<div class="rbf-form-row">';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-customer-name">Customer Name *</label>';
        echo '<input type="text" id="new-customer-name" name="customer_name" required>';
        echo '</div>';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-customer-email">Email *</label>';
        echo '<input type="email" id="new-customer-email" name="customer_email" required>';
        echo '</div>';
        echo '</div>';
        echo '<div class="rbf-form-row">';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-customer-phone">Phone *</label>';
        echo '<input type="tel" id="new-customer-phone" name="customer_phone" required>';
        echo '</div>';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-service-type">Service Type</label>';
        echo '<select id="new-service-type" name="service_type">';
        echo '<option value="pickup_delivery">🚚 Pickup & Delivery Service</option>';
        echo '<option value="onsite">🏠 Onsite Service</option>';
        echo '<option value="store_visit">🏪 Visit Our Store</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        echo '<div class="rbf-form-row">';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-brand">Brand</label>';
        echo '<input type="text" id="new-brand" name="brand" placeholder="e.g., Apple, Samsung">';
        echo '</div>';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-model">Model</label>';
        echo '<input type="text" id="new-model" name="model" placeholder="e.g., iPhone 15, Galaxy S24">';
        echo '</div>';
        echo '</div>';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-repairs">Repairs</label>';
        echo '<textarea id="new-repairs" name="repairs" rows="3" placeholder="Describe the repairs needed"></textarea>';
        echo '</div>';
        echo '<div class="rbf-form-row">';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-subtotal">Subtotal (AED)</label>';
        echo '<input type="number" id="new-subtotal" name="subtotal" step="0.01" min="0">';
        echo '</div>';
        echo '<div class="rbf-form-group">';
        echo '<label for="new-notes">Notes</label>';
        echo '<textarea id="new-notes" name="notes" rows="2" placeholder="Additional notes"></textarea>';
        echo '</div>';
        echo '</div>';
        echo '<div class="rbf-modal-actions">';
        echo '<button type="button" class="button close-modal">Cancel</button>';
        echo '<button type="submit" class="button button-primary">Add Booking</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        
        // View/Edit Booking Modal
        echo '<div id="booking-detail-modal" class="rbf-modal" style="display: none;">';
        echo '<div class="rbf-modal-content" style="max-width: 800px;">';
        echo '<h2>Booking Details</h2>';
        echo '<div id="booking-detail-content"></div>';
        echo '<div class="rbf-modal-actions">';
        echo '<button type="button" class="button close-modal">Close</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Add JavaScript for functionality
        ?>
        <script>
        jQuery(document).ready(function($) {
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo wp_create_nonce("rbf_admin_nonce"); ?>';
            
            // Add New Booking Button
            $('#add-new-booking').on('click', function(e) {
                e.preventDefault();
                $('#add-booking-modal').show();
            });
            
            // Close Modal
            $('.close-modal').on('click', function() {
                $('.rbf-modal').hide();
            });
            
            // Close modal when clicking outside
            $('.rbf-modal').on('click', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
            
            // Status Update
            $('.booking-status-select').on('change', function() {
                var bookingId = $(this).data('booking-id');
                var newStatus = $(this).val();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_update_booking_status',
                        booking_id: bookingId,
                        status: newStatus,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('Status updated successfully!', 'success');
                        } else {
                            showToast('Error updating status: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showToast('Error updating status. Please try again.', 'error');
                    }
                });
            });
            
            // View Booking Details
            $('.view-booking').on('click', function(e) {
                e.preventDefault();
                var bookingId = $(this).data('booking-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_get_booking_details',
                        booking_id: bookingId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#booking-detail-content').html(response.data);
                            $('#booking-detail-modal').show();
                        } else {
                            showToast('Error loading booking details: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showToast('Error loading booking details. Please try again.', 'error');
                    }
                });
            });
            
            // Edit Booking
            $('.edit-booking').on('click', function(e) {
                e.preventDefault();
                var bookingId = $(this).data('booking-id');
                // Redirect to edit page or show edit modal
                showToast('Edit functionality coming soon!', 'info');
            });

            // Print Invoice
            $('.print-invoice').on('click', function(e) {
                e.preventDefault();
                var bookingId = $(this).data('booking-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rbf_get_booking_details',
                        booking_id: bookingId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var invoiceContent = createInvoiceContent(response.data, bookingId);
                            printInvoice(invoiceContent);
                        } else {
                            showToast('Error loading booking details: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showToast('Error loading booking details. Please try again.', 'error');
                    }
                });
            });
            
            // Create Invoice Content
            function createInvoiceContent(bookingData, bookingId) {
                var invoice = '<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">';
                invoice += '<div style="text-align: center; border-bottom: 2px solid #017c36; padding-bottom: 20px; margin-bottom: 30px;">';
                invoice += '<h1 style="color: #017c36; margin: 0;">Repair Booking Invoice</h1>';
                invoice += '<p style="color: #666; margin: 5px 0;">Invoice #' + bookingId + '</p>';
                invoice += '<p style="color: #666; margin: 5px 0;">Date: ' + new Date().toLocaleDateString() + '</p>';
                invoice += '<p style="color: #666; margin: 5px 0;">Status: ' + (bookingData.status || 'N/A') + '</p>';
                invoice += '</div>';
                
                // Customer Information Section
                invoice += '<div style="margin-bottom: 30px;">';
                invoice += '<h3 style="color: #333; margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Customer Information</h3>';
                invoice += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                invoice += '<div><strong>Name:</strong> ' + (bookingData.customer_name || 'N/A') + '</div>';
                invoice += '<div><strong>Email:</strong> ' + (bookingData.customer_email || 'N/A') + '</div>';
                invoice += '<div><strong>Phone:</strong> ' + (bookingData.customer_phone || 'N/A') + '</div>';
                invoice += '<div><strong>Booking Date:</strong> ' + (bookingData.created_at ? new Date(bookingData.created_at).toLocaleDateString() : 'N/A') + '</div>';
                invoice += '</div>';
                invoice += '</div>';
                
                // Device & Service Information
                invoice += '<div style="margin-bottom: 30px;">';
                invoice += '<h3 style="color: #333; margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Device & Service Information</h3>';
                invoice += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                invoice += '<div><strong>Brand:</strong> ' + (bookingData.brand || 'N/A') + '</div>';
                invoice += '<div><strong>Model:</strong> ' + (bookingData.model || 'N/A') + '</div>';
                invoice += '<div><strong>Service Type:</strong> ' + (bookingData.service_type || 'N/A') + '</div>';
                if (bookingData.service_date) {
                    invoice += '<div><strong>Service Date:</strong> ' + new Date(bookingData.service_date).toLocaleDateString() + '</div>';
                }
                if (bookingData.service_time) {
                    invoice += '<div><strong>Service Time:</strong> ' + bookingData.service_time + '</div>';
                }
                invoice += '</div>';
                invoice += '</div>';
                
                // Repairs Section
                invoice += '<div style="margin-bottom: 30px;">';
                invoice += '<h3 style="color: #333; margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Repair Details</h3>';
                invoice += '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #017c36;">';
                invoice += '<p style="margin: 0; line-height: 1.6;">' + (bookingData.repair || 'No repair details specified') + '</p>';
                invoice += '</div>';
                invoice += '</div>';
                
                // Address Information (if available)
                if (bookingData.address || bookingData.city || bookingData.emirate) {
                    invoice += '<div style="margin-bottom: 30px;">';
                    invoice += '<h3 style="color: #333; margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Service Address</h3>';
                    invoice += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                    if (bookingData.address) invoice += '<div><strong>Address:</strong> ' + bookingData.address + '</div>';
                    if (bookingData.street_building) invoice += '<div><strong>Street/Building:</strong> ' + bookingData.street_building + '</div>';
                    if (bookingData.city) invoice += '<div><strong>City:</strong> ' + bookingData.city + '</div>';
                    if (bookingData.emirate) invoice += '<div><strong>Emirate:</strong> ' + bookingData.emirate + '</div>';
                    invoice += '</div>';
                    invoice += '</div>';
                }
                
                // Notes (if available)
                if (bookingData.notes) {
                    invoice += '<div style="margin-bottom: 30px;">';
                    invoice += '<h3 style="color: #333; margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Additional Notes</h3>';
                    invoice += '<div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                    invoice += '<p style="margin: 0; line-height: 1.6;">' + bookingData.notes + '</p>';
                    invoice += '</div>';
                    invoice += '</div>';
                }
                
                // Pricing Section
                invoice += '<div style="margin-bottom: 30px;">';
                invoice += '<h3 style="color: #333; margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Pricing Details</h3>';
                invoice += '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">';
                invoice += '<div style="display: flex; justify-content: space-between; margin-bottom: 10px;">';
                invoice += '<span><strong>Subtotal:</strong></span>';
                invoice += '<span>AED ' + (parseFloat(bookingData.subtotal || 0).toFixed(2)) + '</span>';
                invoice += '</div>';
                invoice += '<div style="display: flex; justify-content: space-between; margin-bottom: 10px;">';
                invoice += '<span><strong>VAT (5%):</strong></span>';
                invoice += '<span>AED ' + (parseFloat(bookingData.vat_amount || 0).toFixed(2)) + '</span>';
                invoice += '</div>';
                invoice += '<div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px solid #017c36; font-size: 18px; font-weight: bold;">';
                invoice += '<span><strong>TOTAL AMOUNT:</strong></span>';
                invoice += '<span style="color: #017c36;">AED ' + (parseFloat(bookingData.total_amount || 0).toFixed(2)) + '</span>';
                invoice += '</div>';
                invoice += '</div>';
                invoice += '</div>';
                
                // Footer
                invoice += '<div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px;">';
                invoice += '<p style="margin: 5px 0;"><strong>Thank you for choosing our repair service!</strong></p>';
                invoice += '<p style="margin: 5px 0;">For any questions or support, please contact our team.</p>';
                invoice += '<p style="margin: 5px 0;">This is an official invoice for your records.</p>';
                invoice += '</div>';
                invoice += '</div>';
                
                return invoice;
            }
            
            // Print Invoice Function
            function printInvoice(invoiceContent) {
                var printWindow = window.open('', '_blank');
                printWindow.document.write('<html><head><title>Repair Booking Invoice</title>');
                printWindow.document.write('<style>body { font-family: Arial, sans-serif; } @media print { body { margin: 20px; } }</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write(invoiceContent);
                printWindow.document.write('</body></html>');
                
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            }
            
            // Add New Booking Form
            $('#add-booking-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'rbf_add_booking');
                formData.append('nonce', nonce);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showToast('Booking added successfully!', 'success');
                            $('#add-booking-modal').hide();
                            location.reload();
                        } else {
                            showToast('Error adding booking: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        showToast('Error adding booking. Please try again.', 'error');
                    }
                });
            });
            
            // Toast notification function
            function showToast(message, type) {
                var toast = $('<div class="rbf-toast rbf-toast-' + type + '">' + message + '</div>');
                $('body').append(toast);
                setTimeout(function() {
                    toast.fadeOut(function() {
                        toast.remove();
                    });
                }, 3000);
            }
        });
        </script>
        
        <style>
        .rbf-bookings-actions {
            margin-bottom: 20px;
        }
        
        .rbf-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100000;
            display: none;
        }
        
        .rbf-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .rbf-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .rbf-form-group {
            flex: 1;
        }
        
        .rbf-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .rbf-form-group input,
        .rbf-form-group select,
        .rbf-form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .rbf-modal-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .rbf-modal-actions .button {
            margin-left: 10px;
        }
        
        .rbf-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            color: white;
            z-index: 100001;
            font-weight: 600;
        }
        
        .rbf-toast-success {
            background: #46b450;
        }
        
        .rbf-toast-error {
            background: #dc3232;
        }
        
        .rbf-toast-info {
            background: #0073aa;
        }
        
        .booking-status-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .rbf-bookings-actions .button {
            margin-right: 10px;
        }
        
        /* Booking Details Styling */
        .booking-detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .booking-detail-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }
        
        .booking-detail-section h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #23282d;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 8px;
        }
        
        .booking-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .booking-detail-row:last-child {
            border-bottom: none;
        }
        
        .booking-detail-label {
            font-weight: 600;
            color: #555;
            min-width: 120px;
        }
        
        .booking-detail-value {
            text-align: right;
            color: #333;
        }
        
        .rbf-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .rbf-status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .rbf-status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .rbf-status-in_progress {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .rbf-status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .rbf-status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        </style>
        <?php
    }
    
    // AJAX Handlers for Admin
    
    /**
     * Save Brand (AJAX)
     */
    public function ajax_save_brand() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $brand_id = intval($_POST['brand_id']);
        $name = sanitize_text_field($_POST['name']);
        $image_url = esc_url_raw($_POST['image_url']);
        
        // Get predefined brands
        $predefined_brands = $this->get_predefined_brands();
        
        // Validate brand name
        if (empty($name) || !array_key_exists($name, $predefined_brands)) {
            wp_send_json_error('Invalid brand name');
        }
        
        // Use predefined image URL if not provided
        if (empty($image_url)) {
            $image_url = $predefined_brands[$name];
        }
        
        $data = array(
            'name' => $name,
            'image_url' => $image_url
        );
        
        // Check if brand already exists
        $existing_brand = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rbf_brands WHERE name = %s",
            $name
        ));
        
        if ($existing_brand) {
            // Update existing brand
            $result = $wpdb->update(
                $wpdb->prefix . 'rbf_brands',
                $data,
                array('name' => $name),
                array('%s', '%s'),
                array('%s')
            );
        } else {
            // Insert new brand
            $result = $wpdb->insert(
                $wpdb->prefix . 'rbf_brands',
                $data,
                array('%s', '%s')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success('Brand saved successfully');
        } else {
            wp_send_json_error('Failed to save brand');
        }
    }
    
    /**
     * Delete Brand (AJAX)
     */
    public function ajax_delete_brand() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $brand_id = intval($_POST['brand_id']);
        
        // Get the brand name
        $brand_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}rbf_brands WHERE id = %d",
            $brand_id
        ));
        
        // Prevent deleting predefined brands
        $predefined_brands = array_keys($this->get_predefined_brands());
        if (in_array($brand_name, $predefined_brands)) {
            wp_send_json_error('Cannot delete predefined brand');
        }
        
        // Soft delete by updating status
        $result = $wpdb->update(
            $wpdb->prefix . 'rbf_brands',
            array('status' => 'deleted'),
            array('id' => $brand_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Brand deleted successfully');
        } else {
            wp_send_json_error('Failed to delete brand');
        }
    }
    
    /**
     * Save Model (AJAX)
     */
    public function ajax_save_model() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $model_id = intval($_POST['model_id']);
        $brand_id = intval($_POST['brand_id']);
        $name = sanitize_text_field($_POST['name']);
        $image_url = esc_url_raw($_POST['image_url']);
        
        if (empty($name) || $brand_id <= 0) {
            wp_send_json_error('Model name and brand are required');
        }
        
        $data = array(
            'brand_id' => $brand_id,
            'name' => $name,
            'image_url' => $image_url
        );
        
        if ($model_id > 0) {
            // Update existing model
            $result = $wpdb->update(
                $wpdb->prefix . 'rbf_models',
                $data,
                array('id' => $model_id),
                array('%d', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new model
            $result = $wpdb->insert(
                $wpdb->prefix . 'rbf_models',
                $data,
                array('%d', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success('Model saved successfully');
        } else {
            wp_send_json_error('Failed to save model');
        }
    }
    
    /**
     * Delete Model (AJAX)
     */
    public function ajax_delete_model() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $model_id = intval($_POST['model_id']);
        
        // Soft delete by updating status
        $result = $wpdb->update(
            $wpdb->prefix . 'rbf_models',
            array('status' => 'deleted'),
            array('id' => $model_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Model deleted successfully');
        } else {
            wp_send_json_error('Failed to delete model');
        }
    }
    
    /**
     * Save Repair (AJAX)
     */
    public function ajax_save_repair() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $repair_id = intval($_POST['repair_id']);
        $model_id = intval($_POST['model_id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $repair_time = sanitize_text_field($_POST['repair_time']);
        $price = floatval($_POST['price']);
        
        if (empty($name) || $model_id <= 0 || $price < 0) {
            wp_send_json_error('Repair name, model, and valid price are required');
        }
        
        $data = array(
            'model_id' => $model_id,
            'name' => $name,
            'description' => $description,
            'repair_time' => $repair_time,
            'price' => $price
        );
        
        if ($repair_id > 0) {
            // Update existing repair
            $result = $wpdb->update(
                $wpdb->prefix . 'rbf_repairs',
                $data,
                array('id' => $repair_id),
                array('%d', '%s', '%s', '%s', '%f'),
                array('%d')
            );
            
            if ($result === false) {
                // Log the error for debugging
                error_log('RBF Debug: Database update failed. Last error: ' . $wpdb->last_error);
                wp_send_json_error('Database update failed: ' . $wpdb->last_error);
            }
        } else {
            // Insert new repair
            $result = $wpdb->insert(
                $wpdb->prefix . 'rbf_repairs',
                $data,
                array('%d', '%s', '%s', '%s', '%f')
            );
            
            if ($result === false) {
                // Log the error for debugging
                error_log('RBF Debug: Database insert failed. Last error: ' . $wpdb->last_error);
                wp_send_json_error('Database insert failed: ' . $wpdb->last_error);
            }
        }
        
        if ($result !== false) {
            wp_send_json_success('Repair saved successfully');
        } else {
            wp_send_json_error('Failed to save repair');
        }
    }
    
    /**
     * Delete Repair (AJAX)
     */
    public function ajax_delete_repair() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $repair_id = intval($_POST['repair_id']);
        
        // Soft delete by updating status
        $result = $wpdb->update(
            $wpdb->prefix . 'rbf_repairs',
            array('status' => 'deleted'),
            array('id' => $repair_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Repair deleted successfully');
        } else {
            wp_send_json_error('Failed to delete repair');
        }
    }
    
    /**
     * Update Booking Status (AJAX)
     */
    public function ajax_update_booking_status() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $booking_id = intval($_POST['booking_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $valid_statuses = array('pending', 'confirmed', 'in_progress', 'completed', 'cancelled');
        if (!in_array($status, $valid_statuses)) {
            wp_send_json_error('Invalid status');
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'rbf_bookings',
            array('status' => $status),
            array('id' => $booking_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Booking status updated successfully');
        } else {
            wp_send_json_error('Failed to update booking status');
        }
    }
    
    /**
     * Get Booking Details (AJAX)
     */
    public function ajax_get_booking_details() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $booking_id = intval($_POST['booking_id']);
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rbf_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
        }
        
        ob_start();
        ?>
        <div class="booking-detail-grid">
            <div class="booking-detail-section">
                <h4>Customer Information</h4>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Name:</span>
                    <span class="booking-detail-value"><?php echo esc_html($booking->customer_name); ?></span>
                </div>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Phone:</span>
                    <span class="booking-detail-value">
                        <a href="tel:<?php echo esc_attr($booking->customer_phone); ?>">
                            <?php echo esc_html($booking->customer_phone); ?>
                        </a>
                    </span>
                </div>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Email:</span>
                    <span class="booking-detail-value">
                        <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>">
                            <?php echo esc_html($booking->customer_email); ?>
                        </a>
                    </span>
                </div>
            </div>
            
            <div class="booking-detail-section">
                <h4>Device Information</h4>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Brand:</span>
                    <span class="booking-detail-value"><?php echo esc_html($booking->brand); ?></span>
                </div>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Model:</span>
                    <span class="booking-detail-value"><?php echo esc_html($booking->model); ?></span>
                </div>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Repair:</span>
                    <span class="booking-detail-value"><?php echo esc_html($booking->repair); ?></span>
                </div>
            </div>
            
            <div class="booking-detail-section">
                <h4>Service Details</h4>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Service Type:</span>
                    <span class="booking-detail-value"><?php echo esc_html($this->get_service_type_label($booking->service_type) ?: 'Not specified'); ?></span>
                </div>
                <?php if ($booking->service_date): ?>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Service Date:</span>
                    <span class="booking-detail-value"><?php echo date('M j, Y', strtotime($booking->service_date)); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($booking->service_time): ?>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Service Time:</span>
                    <span class="booking-detail-value"><?php echo date('g:i A', strtotime($booking->service_time)); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="booking-detail-section">
                <h4>Pricing</h4>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">Subtotal:</span>
                    <span class="booking-detail-value">AED <?php echo number_format($booking->subtotal, 2); ?></span>
                </div>
                <div class="booking-detail-row">
                    <span class="booking-detail-label">VAT (5%):</span>
                    <span class="booking-detail-value">AED <?php echo number_format($booking->vat_amount, 2); ?></span>
                </div>
                <div class="booking-detail-row">
                    <span class="booking-detail-label"><strong>Total:</strong></span>
                    <span class="booking-detail-value"><strong>AED <?php echo number_format($booking->total_amount, 2); ?></strong></span>
                </div>
            </div>
        </div>
        
        <?php if ($booking->address): ?>
        <div class="booking-detail-section">
            <h4>Address Information</h4>
            <div class="booking-detail-row">
                <span class="booking-detail-label">Address:</span>
                <span class="booking-detail-value"><?php echo esc_html($booking->address); ?></span>
            </div>
            <?php if ($booking->street_building): ?>
            <div class="booking-detail-row">
                <span class="booking-detail-label">Street/Building:</span>
                <span class="booking-detail-value"><?php echo esc_html($booking->street_building); ?></span>
            </div>
            <?php endif; ?>
            <div class="booking-detail-row">
                <span class="booking-detail-label">City:</span>
                <span class="booking-detail-value"><?php echo esc_html($booking->city); ?></span>
            </div>
            <div class="booking-detail-row">
                <span class="booking-detail-label">Emirate:</span>
                <span class="booking-detail-value"><?php echo esc_html($booking->emirate); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($booking->notes): ?>
        <div class="booking-detail-section">
            <h4>Notes</h4>
            <p><?php echo nl2br(esc_html($booking->notes)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="booking-detail-section">
            <h4>Booking Information</h4>
            <div class="booking-detail-row">
                <span class="booking-detail-label">Booking ID:</span>
                <span class="booking-detail-value"><?php echo esc_html($booking->booking_id ?: '#' . $booking->id); ?></span>
            </div>
            <div class="booking-detail-row">
                <span class="booking-detail-label">Status:</span>
                <span class="booking-detail-value">
                    <span class="rbf-status rbf-status-<?php echo $booking->status; ?>">
                        <?php echo ucfirst($booking->status); ?>
                    </span>
                </span>
            </div>
            <div class="booking-detail-row">
                <span class="booking-detail-label">Created:</span>
                <span class="booking-detail-value"><?php echo date('M j, Y g:i A', strtotime($booking->created_at)); ?></span>
            </div>
        </div>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success($html);
    }
    
    /**
     * Get Invoice Data (AJAX) - Returns raw booking data for invoice generation
     */
    public function ajax_get_invoice_data() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $booking_id = intval($_POST['booking_id']);
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rbf_bookings WHERE id = %d",
            $booking_id
        ), ARRAY_A);
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
        }
        
        // Get service type label
        $service_type_label = $this->get_service_type_label($booking['service_type']);
        
        // Format the data for invoice
        $invoice_data = array(
            'customer_name' => $booking['customer_name'],
            'customer_email' => $booking['customer_email'],
            'customer_phone' => $booking['customer_phone'],
            'brand' => $booking['brand'],
            'model' => $booking['model'],
            'repair' => $booking['repair'],
            'service_type' => $service_type_label,
            'service_date' => $booking['service_date'],
            'service_time' => $booking['service_time'],
            'address' => $booking['address'],
            'street_building' => $booking['street_building'],
            'city' => $booking['city'],
            'emirate' => $booking['emirate'],
            'notes' => $booking['notes'],
            'subtotal' => $booking['subtotal'],
            'vat_amount' => $booking['vat_amount'],
            'total_amount' => $booking['total_amount'],
            'status' => $booking['status'],
            'created_at' => $booking['created_at']
        );
        
        wp_send_json_success($invoice_data);
    }
    
    /**
     * Send booking confirmation email
     */
    private function send_booking_confirmation($booking_data, $booking_id) {
        $customer_email = $booking_data['email'];
        $customer_name = $booking_data['name'];
        
        $subject = 'Booking Confirmation - ' . $booking_id;
        
        $message = "Dear {$customer_name},\n\n";
        $message .= "Thank you for your booking! Here are the details:\n\n";
        $message .= "Booking ID: {$booking_id}\n";
        $message .= "Device: {$booking_data['selected_brand']} {$booking_data['selected_model']}\n";
        $message .= "Service Type: " . $this->get_service_type_label($booking_data['service_type']) . "\n";
        
        if (!empty($booking_data['service_date'])) {
            $message .= "Service Date: {$booking_data['service_date']}\n";
        }
        
        if (!empty($booking_data['service_time'])) {
            $message .= "Service Time: {$booking_data['service_time']}\n";
        }
        
        $message .= "Total Amount: AED " . number_format($booking_data['subtotal'] * 1.05, 2) . " (including 5% VAT)\n\n";
        $message .= "We will contact you shortly to confirm your booking.\n\n";
        $message .= "Best regards,\n";
        $message .= get_bloginfo('name');
        
        try {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            wp_mail($customer_email, $subject, nl2br($message), $headers);
            
            // Send notification to admin
            $admin_email = get_option('admin_email');
            $admin_subject = 'New Repair Booking - ' . $booking_id;
            $admin_message = "New booking received:\n\n";
            $admin_message .= "Customer: {$customer_name}\n";
            $admin_message .= "Phone: {$booking_data['phone']}\n";
            $admin_message .= "Email: {$customer_email}\n";
            $admin_message .= "Device: {$booking_data['selected_brand']} {$booking_data['selected_model']}\n";
            $sub_val = floatval($booking_data['subtotal'] ?? 0);
            $admin_message .= "Total: AED " . number_format($sub_val * 1.05, 2) . "\n\n";
            $admin_message .= "View booking details in admin panel.";
            
            wp_mail($admin_email, $admin_subject, $admin_message);
        } catch (\Throwable $e) {
            error_log('RBF send_booking_confirmation error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get service type label
     */
    private function get_service_type_label($service_type) {
        $labels = array(
            'pickup_delivery' => 'Pickup & Delivery Service',
            'onsite' => 'Onsite Service',
            'store_visit' => 'Visit Our Store'
        );
        
        return isset($labels[$service_type]) ? $labels[$service_type] : $service_type;
    }
    
    /**
     * Get service type icon
     */
    private function get_service_type_icon($service_type) {
        $icons = array(
            'pickup_delivery' => '🚚',
            'onsite' => '🏠',
            'store_visit' => '🏪'
        );
        
        return isset($icons[$service_type]) ? $icons[$service_type] : '📋';
    }

    /**
     * Get predefined brands with their image paths
     */
    private function get_predefined_brands() {
        return [
            'Apple' => RBF_PLUGIN_URL . 'Brands/apple.png',
            'Samsung' => RBF_PLUGIN_URL . 'Brands/samsung.png',
            'Google Pixel' => RBF_PLUGIN_URL . 'Brands/googlepixel.png',
            'OnePlus' => RBF_PLUGIN_URL . 'Brands/oneplus.png',
            'Others' => RBF_PLUGIN_URL . 'Brands/other_brand.jpg'
        ];
    }
    
    /**
     * Ensure all predefined brands are in the database
     */
    private function ensure_brands_exist() {
        global $wpdb;
        
        $predefined_brands = $this->get_predefined_brands();
        
        foreach ($predefined_brands as $brand_name => $brand_image) {
            // Check if brand already exists
            $existing_brand = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rbf_brands WHERE name = %s",
                $brand_name
            ));
            
            // If brand doesn't exist, insert it
            if (!$existing_brand) {
                $wpdb->insert(
                    $wpdb->prefix . 'rbf_brands',
                    [
                        'name' => $brand_name,
                        'image_url' => $brand_image,
                        'status' => 'active',
                        'created_at' => current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s']
                );
            }
        }
    }
    
    /**
     * Modify migration method to ensure brands exist
     */
    private function migrate_plugin_data() {
        // Ensure brands exist first
        $this->ensure_brands_exist();
        
        // Migrate brand series and models
        $this->migrate_brand_series_models();
        
        // Log migration details
        $diagnostic_results = $this->diagnostic_data_check();
        $this->log_migration_details($diagnostic_results);
    }
    
    /**
     * Remove diagnostic menu
     */
    public function remove_diagnostic_menu() {
        remove_submenu_page('repair-booking', 'repair-booking-diagnostics');
    }
    
    /**
     * Check and run migration if needed
     */
    public function maybe_migrate_data() {
        // No migration needed since we're using JSON
        // This function is kept for future compatibility
    }
    
    /**
     * Diagnostic method to verify data migration and frontend integration
     */
    public function diagnostic_data_check() {
        global $wpdb;
        
        $diagnostic_results = array(
            'brands' => array(
                'total_count' => 0,
                'predefined_count' => 0,
                'predefined_details' => array()
            ),
            'models' => array(
                'total_count' => 0,
                'predefined_count' => 0,
                'predefined_details' => array()
            ),
            'repairs' => array(
                'total_count' => 0,
                'predefined_count' => 0,
                'predefined_details' => array()
            )
        );
        
        // Predefined brands check
        $predefined_brands = $this->get_predefined_brands();
        $brands = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rbf_brands WHERE status = 'active'");
        
        $diagnostic_results['brands']['total_count'] = count($brands);
        
        foreach ($brands as $brand) {
            if (isset($predefined_brands[$brand->name])) {
                $diagnostic_results['brands']['predefined_count']++;
                $diagnostic_results['brands']['predefined_details'][] = array(
                    'name' => $brand->name,
                    'image_url' => $brand->image_url,
                    'expected_image_url' => $predefined_brands[$brand->name]
                );
            }
        }
        
        // Predefined models check
        $device_models = $this->get_device_models();
        $models = $wpdb->get_results("SELECT m.*, b.name as brand_name FROM {$wpdb->prefix}rbf_models m JOIN {$wpdb->prefix}rbf_brands b ON m.brand_id = b.id WHERE m.status = 'active'");
        
        $diagnostic_results['models']['total_count'] = count($models);
        
        foreach ($models as $model) {
            if (isset($device_models[$model->brand_name]) && 
                isset($device_models[$model->brand_name][$model->name])) {
                $diagnostic_results['models']['predefined_count']++;
                $diagnostic_results['models']['predefined_details'][] = array(
                    'name' => $model->name,
                    'brand' => $model->brand_name,
                    'image_url' => $model->image_url,
                    'expected_image_url' => $device_models[$model->brand_name][$model->name]
                );
            }
        }
        
        // Predefined repairs check
        $repairs = $wpdb->get_results("SELECT r.*, m.name as model_name, b.name as brand_name FROM {$wpdb->prefix}rbf_repairs r JOIN {$wpdb->prefix}rbf_models m ON r.model_id = m.id JOIN {$wpdb->prefix}rbf_brands b ON m.brand_id = b.id WHERE r.status = 'active'");
        
        $diagnostic_results['repairs']['total_count'] = count($repairs);
        
        // Get a sample of repairs to check
        $sample_repairs = $this->get_repairs_by_model('iPhone', 'iPhone 15');
        
        foreach ($repairs as $repair) {
            $is_predefined = false;
            foreach ($sample_repairs as $sample_repair) {
                if ($repair->name === $sample_repair['name']) {
                    $is_predefined = true;
                    $diagnostic_results['repairs']['predefined_count']++;
                    $diagnostic_results['repairs']['predefined_details'][] = array(
                        'name' => $repair->name,
                        'brand' => $repair->brand_name,
                        'model' => $repair->model_name,
                        'price' => $repair->price,
                        'duration' => $repair->repair_time
                    );
                    break;
                }
            }
        }
        
        return $diagnostic_results;
    }
    
    /**
     * Log migration details for troubleshooting
     */
    private function log_migration_details($diagnostic_results) {
        // Create a log file in the plugin directory
        $log_file = RBF_PLUGIN_PATH . 'migration_log.txt';
        
        // Prepare log content
        $log_content = "Migration Log - " . current_time('mysql') . "\n";
        $log_content .= "==================================\n\n";
        
        // Brands Migration Log
        $log_content .= "BRANDS MIGRATION\n";
        $log_content .= "Total Brands: " . $diagnostic_results['brands']['total_count'] . "\n";
        $log_content .= "Predefined Brands: " . $diagnostic_results['brands']['predefined_count'] . "\n";
        $log_content .= "Predefined Brand Details:\n";
        foreach ($diagnostic_results['brands']['predefined_details'] as $brand) {
            $log_content .= "- {$brand['name']}: " . 
                            ($brand['image_url'] === $brand['expected_image_url'] ? 'MATCHED' : 'MISMATCH') . "\n";
        }
        $log_content .= "\n";
        
        // Models Migration Log
        $log_content .= "MODELS MIGRATION\n";
        $log_content .= "Total Models: " . $diagnostic_results['models']['total_count'] . "\n";
        $log_content .= "Predefined Models: " . $diagnostic_results['models']['predefined_count'] . "\n";
        $log_content .= "Predefined Model Details:\n";
        foreach ($diagnostic_results['models']['predefined_details'] as $model) {
            $log_content .= "- {$model['name']} ({$model['brand']}): " . 
                            ($model['image_url'] === $model['expected_image_url'] ? 'MATCHED' : 'MISMATCH') . "\n";
        }
        $log_content .= "\n";
        
        // Repairs Migration Log
        $log_content .= "REPAIRS MIGRATION\n";
        $log_content .= "Total Repairs: " . $diagnostic_results['repairs']['total_count'] . "\n";
        $log_content .= "Predefined Repairs: " . $diagnostic_results['repairs']['predefined_count'] . "\n";
        $log_content .= "Predefined Repair Details:\n";
        foreach ($diagnostic_results['repairs']['predefined_details'] as $repair) {
            $log_content .= "- {$repair['name']} ({$repair['brand']} - {$repair['model']}): " . 
                            "Price: AED {$repair['price']}, Duration: {$repair['duration']}\n";
        }
        
        // Write log file
        file_put_contents($log_file, $log_content, FILE_APPEND);
    }
    
    /**
     * Enhanced migration verification
     */
    private function verify_migration_integrity() {
        global $wpdb;
        
        // Check for any inconsistencies or missing data
        $verification_results = array(
            'brands_missing_models' => array(),
            'models_missing_repairs' => array(),
            'orphaned_repairs' => array()
        );
        
        // Check brands with no models
        $brands_without_models = $wpdb->get_results(
            "SELECT b.id, b.name 
             FROM {$wpdb->prefix}rbf_brands b 
             LEFT JOIN {$wpdb->prefix}rbf_models m ON b.id = m.brand_id 
             WHERE m.id IS NULL AND b.status = 'active'"
        );
        
        $verification_results['brands_missing_models'] = $brands_without_models;
        
        // Check models with no repairs
        $models_without_repairs = $wpdb->get_results(
            "SELECT m.id, m.name, b.name as brand_name 
             FROM {$wpdb->prefix}rbf_models m
             JOIN {$wpdb->prefix}rbf_brands b ON m.brand_id = b.id
             LEFT JOIN {$wpdb->prefix}rbf_repairs r ON m.id = r.model_id 
             WHERE r.id IS NULL AND m.status = 'active'"
        );
        
        $verification_results['models_missing_repairs'] = $models_without_repairs;
        
        // Check for repairs with invalid model references
        $orphaned_repairs = $wpdb->get_results(
            "SELECT r.id, r.name, r.model_id 
             FROM {$wpdb->prefix}rbf_repairs r 
             LEFT JOIN {$wpdb->prefix}rbf_models m ON r.model_id = m.id 
             WHERE m.id IS NULL"
        );
        
        $verification_results['orphaned_repairs'] = $orphaned_repairs;
        
        return $verification_results;
    }
    
    /**
     * Get predefined brand series and models hierarchy
     */
    private function get_brand_series_hierarchy() {
        return [
            'Apple' => [
                'iPhone' => [
                    'iPhone 16 Pro Max',
                    'iPhone 15 Pro Max',
                    'iPhone 15 Pro',
                    'iPhone 15 Plus',
                    'iPhone 15',
                    'iPhone 14 Pro Max',
                    'iPhone 14 Pro',
                    'iPhone 14 Plus',
                    'iPhone 14',
                    'iPhone 13 Pro Max',
                    'iPhone 13 Pro',
                    'iPhone 13',
                    'iPhone 13 Mini',
                    'iPhone 12 Pro Max',
                    'iPhone 12 Pro',
                    'iPhone 12',
                    'iPhone 12 Mini',
                    'iPhone 11 Pro Max',
                    'iPhone 11 Pro',
                    'iPhone 11',
                    'iPhone SE (2022)',
                    'iPhone SE (3rd Gen)',
                    'iPhone SE'
                ]
            ],
            'Samsung' => [
                'Galaxy S' => [
                    'Galaxy S24 Ultra',
                    'Galaxy S24+',
                    'Galaxy S24',
                    'Galaxy S23 Ultra',
                    'Galaxy S23+',
                    'Galaxy S23',
                    'Galaxy S22 Ultra',
                    'Galaxy S22+',
                    'Galaxy S22',
                    'Galaxy S21 Ultra',
                    'Galaxy S21+',
                    'Galaxy S21',
                    'Galaxy S20 Ultra',
                    'Galaxy S20+',
                    'Galaxy S20'
                ],
                'Galaxy Note' => [
                    'Galaxy Note 20 Ultra',
                    'Galaxy Note 20',
                    'Galaxy Note 10+',
                    'Galaxy Note 10',
                    'Galaxy Note 9',
                    'Galaxy Note 8'
                ],
                'Galaxy Z' => [
                    'Galaxy Z Fold 5',
                    'Galaxy Z Fold 4',
                    'Galaxy Z Fold 3',
                    'Galaxy Z Flip 5',
                    'Galaxy Z Flip 4',
                    'Galaxy Z Flip 3'
                ]
            ],
            'Google Pixel' => [
                'Pixel' => [
                    'Pixel 9 Pro Fold',
                    'Pixel 9 Pro XL',
                    'Pixel 9 Pro',
                    'Pixel 9',
                    'Pixel 9a',
                    'Pixel 8 Pro',
                    'Pixel 8',
                    'Pixel 8a',
                    'Pixel 7 Pro',
                    'Pixel 7',
                    'Pixel 7a',
                    'Pixel 6 Pro',
                    'Pixel 6',
                    'Pixel 6a',
                    'Pixel 5',
                    'Pixel 5a',
                    'Pixel 4 XL',
                    'Pixel 4',
                    'Pixel 4a',
                    'Pixel 4a 5G'
                ]
            ],
            'OnePlus' => [
                'OnePlus' => [
                    'OnePlus 12',
                    'OnePlus 12R',
                    'OnePlus 11',
                    'OnePlus 10 Pro',
                    'OnePlus 10T',
                    'OnePlus 9 Pro',
                    'OnePlus 9',
                    'OnePlus 9R',
                    'OnePlus 8 Pro',
                    'OnePlus 8',
                    'OnePlus 8T'
                ],
                'OnePlus Nord' => [
                    'OnePlus Nord CE3',
                    'OnePlus Nord 2T',
                    'OnePlus Nord'
                ]
            ],
            'Others' => [
                'Other Brands' => [
                    'Custom Device 1',
                    'Custom Device 2',
                    'Custom Device 3'
                ]
            ]
        ];
    }
    
    /**
     * Migrate brand series and models
     */
    private function migrate_brand_series_models() {
        global $wpdb;
        
        $brand_hierarchy = $this->get_brand_series_hierarchy();
        
        foreach ($brand_hierarchy as $brand_name => $series) {
            // Get or create brand
            $brand_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rbf_brands WHERE name = %s",
                $brand_name
            ));
            
            if (!$brand_id) {
                // Insert brand
                $wpdb->insert(
                    $wpdb->prefix . 'rbf_brands',
                    [
                        'name' => $brand_name,
                        'image_url' => RBF_PLUGIN_URL . 'Brands/' . strtolower(str_replace(' ', '', $brand_name)) . '.png',
                        'status' => 'active'
                    ],
                    ['%s', '%s', '%s']
                );
                $brand_id = $wpdb->insert_id;
            }
            
            // Migrate series and models
            foreach ($series as $series_name => $models) {
                // Insert series as a model
                $series_model_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}rbf_models 
                     WHERE name = %s AND brand_id = %d",
                    $series_name, $brand_id
                ));
                
                if (!$series_model_id) {
                    $wpdb->insert(
                        $wpdb->prefix . 'rbf_models',
                        [
                            'brand_id' => $brand_id,
                            'name' => $series_name,
                            'image_url' => RBF_PLUGIN_URL . 'Brands/' . strtolower(str_replace(' ', '', $brand_name)) . '.png',
                            'status' => 'active'
                        ],
                        ['%d', '%s', '%s', '%s']
                    );
                    $series_model_id = $wpdb->insert_id;
                }
                
                // Insert individual models
                foreach ($models as $model_name) {
                    $existing_model = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}rbf_models 
                         WHERE name = %s AND brand_id = %d",
                        $model_name, $brand_id
                    ));
                    
                    if (!$existing_model) {
                        $wpdb->insert(
                            $wpdb->prefix . 'rbf_models',
                            [
                                'brand_id' => $brand_id,
                                'name' => $model_name,
                                'image_url' => RBF_PLUGIN_URL . 'Brands/' . strtolower(str_replace(' ', '', $brand_name)) . '_modals/' . 
                                               strtolower(str_replace(' ', '-', $model_name)) . '.jpg',
                                'status' => 'active',
                                'parent_id' => $series_model_id
                            ],
                            ['%d', '%s', '%s', '%s', '%d']
                        );
                    }
                }
            }
        }
    }

    /**
     * Create database tables for brands and models
     */
    public function create_plugin_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Brands Table
        $brands_table = $wpdb->prefix . 'rbf_brands';
        $brands_sql = "CREATE TABLE $brands_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            image_url varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Models Table
        $models_table = $wpdb->prefix . 'rbf_models';
        $models_sql = "CREATE TABLE $models_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            brand_id mediumint(9) NOT NULL,
            name varchar(100) NOT NULL,
            image_url varchar(255) NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            FOREIGN KEY  (brand_id) REFERENCES $brands_table(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Repairs Table (ADDED - This was missing!)
        $repairs_table = $wpdb->prefix . 'rbf_repairs';
        $repairs_sql = "CREATE TABLE $repairs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            model_id mediumint(9) NOT NULL,
            name varchar(100) NOT NULL,
            description text,
            icon varchar(255),
            duration varchar(100),
            repair_time varchar(100),
            price decimal(10,2) DEFAULT 0.00 NOT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY model_id (model_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($brands_sql);
        dbDelta($models_sql);
        dbDelta($repairs_sql);
        
        // Create bookings table
        $this->create_bookings_table();
        
        // Populate repairs table if empty
        $existing_repairs = $wpdb->get_var("SELECT COUNT(*) FROM $repairs_table");
        if ($existing_repairs == 0) {
            $this->populate_repairs_table();
        }
    }
    
    /**
     * Populate repairs table with basic repair services
     */
    private function populate_repairs_table() {
        global $wpdb;
        
        $repairs_table = $wpdb->prefix . 'rbf_repairs';
        
        // First, let's ensure we have at least one model to reference
        $first_model_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}rbf_models LIMIT 1");
        if (!$first_model_id) {
            // If no models exist, create a default one
            $first_brand_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}rbf_brands LIMIT 1");
            if (!$first_brand_id) {
                // If no brands exist, create a default brand first
                $wpdb->insert(
                    $wpdb->prefix . 'rbf_brands',
                    array(
                        'name' => 'Default Brand',
                        'image_url' => 'Brands/default.png',
                        'status' => 'active'
                    ),
                    array('%s', '%s', '%s')
                );
                $first_brand_id = $wpdb->insert_id;
            }
            
            // Create a default model
            $wpdb->insert(
                $wpdb->prefix . 'rbf_models',
                array(
                    'brand_id' => $first_brand_id,
                    'name' => 'Default Model',
                    'image_url' => 'Models/default.png',
                    'status' => 'active'
                ),
                array('%d', '%s', '%s', '%s')
            );
            $first_model_id = $wpdb->insert_id;
        }
        
        $repairs = array(
            array(
                'name' => 'Screen Replacement',
                'description' => 'Replace broken or cracked screen with original quality replacement',
                'icon' => 'assets/images/repairs/screen-replacement.png',
                'duration' => '2-3 hours',
                'repair_time' => '2-3 hours',
                'price' => 299.00
            ),
            array(
                'name' => 'Battery Replacement',
                'description' => 'Replace old or faulty battery with new high-quality battery',
                'icon' => 'assets/images/repairs/battery-replacement.png',
                'duration' => '1-2 hours',
                'repair_time' => '1-2 hours',
                'price' => 199.00
            ),
            array(
                'name' => 'Charging Port Repair',
                'description' => 'Fix charging port issues and restore charging functionality',
                'icon' => 'assets/images/repairs/charging-port.png',
                'duration' => '1-2 hours',
                'repair_time' => '1-2 hours',
                'price' => 149.00
            ),
            array(
                'name' => 'Camera Repair',
                'description' => 'Fix camera hardware issues and restore camera functionality',
                'icon' => 'assets/images/repairs/camera-repair.png',
                'duration' => '1-2 hours',
                'repair_time' => '1-2 hours',
                'price' => 249.00
            ),
            array(
                'name' => 'Speaker Repair',
                'description' => 'Fix speaker issues and restore audio output',
                'icon' => 'assets/images/repairs/speaker-repair.png',
                'duration' => '1 hour',
                'repair_time' => '1 hour',
                'price' => 99.00
            ),
            array(
                'name' => 'Microphone Repair',
                'description' => 'Fix microphone issues and restore voice input',
                'icon' => 'assets/images/repairs/microphone-repair.png',
                'duration' => '1 hour',
                'repair_time' => '1 hour',
                'price' => 89.00
            ),
            array(
                'name' => 'Volume Button Repair',
                'description' => 'Fix volume button functionality',
                'icon' => 'assets/images/repairs/volume-button.png',
                'duration' => '30 minutes',
                'repair_time' => '30 minutes',
                'price' => 79.00
            ),
            array(
                'name' => 'Power Button Repair',
                'description' => 'Fix power button functionality',
                'icon' => 'assets/images/repairs/power-button.png',
                'duration' => '30 minutes',
                'repair_time' => '30 minutes',
                'price' => 79.00
            ),
            array(
                'name' => 'Home Button Repair',
                'description' => 'Fix home button functionality',
                'icon' => 'assets/images/repairs/home-button.png',
                'duration' => '30 minutes',
                'repair_time' => '30 minutes',
                'price' => 79.00
            ),
            array(
                'name' => 'Water Damage Repair',
                'description' => 'Clean and repair water-damaged components',
                'icon' => 'assets/images/repairs/water-damage.png',
                'duration' => '2-4 hours',
                'repair_time' => '2-4 hours',
                'price' => 399.00
            ),
            array(
                'name' => 'Software Issues',
                'description' => 'Fix software problems, viruses, and system issues',
                'icon' => 'assets/images/repairs/software-issues.png',
                'duration' => '1-2 hours',
                'repair_time' => '1-2 hours',
                'price' => 149.00
            ),
            array(
                'name' => 'Data Recovery',
                'description' => 'Recover lost data from damaged devices',
                'icon' => 'assets/images/repairs/data-recovery.png',
                'duration' => '2-6 hours',
                'repair_time' => '2-6 hours',
                'price' => 199.00
            )
        );
        
        foreach ($repairs as $repair) {
            $wpdb->insert(
                $repairs_table,
                array(
                    'model_id' => $first_model_id,
                    'name' => $repair['name'],
                    'description' => $repair['description'],
                    'icon' => $repair['icon'],
                    'duration' => $repair['duration'],
                    'repair_time' => $repair['duration'],
                    'price' => $repair['price'],
                    'status' => 'active'
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s')
            );
        }
    }
    
    /**
     * Update existing database structure for repairs table
     */
    private function update_repairs_table_structure() {
        global $wpdb;
        
        $repairs_table = $wpdb->prefix . 'rbf_repairs';
        
        // First, let's try to remove any existing foreign key constraints that might be causing issues
        try {
            // Get foreign key constraints
            $foreign_keys = $wpdb->get_results("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '$repairs_table' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            foreach ($foreign_keys as $fk) {
                $wpdb->query("ALTER TABLE $repairs_table DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
            }
        } catch (Exception $e) {
            // Ignore errors if foreign key doesn't exist
        }
        
        // Check if model_id column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $repairs_table LIKE 'model_id'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $repairs_table ADD COLUMN model_id mediumint(9) NOT NULL DEFAULT 1 AFTER id");
        }
        
        // Check if price column exists
        $price_exists = $wpdb->get_results("SHOW COLUMNS FROM $repairs_table LIKE 'price'");
        if (empty($price_exists)) {
            $wpdb->query("ALTER TABLE $repairs_table ADD COLUMN price decimal(10,2) DEFAULT 0.00 NOT NULL AFTER duration");
        }
        
        // Check if repair_time column exists
        $time_exists = $wpdb->get_results("SHOW COLUMNS FROM $repairs_table LIKE 'repair_time'");
        if (empty($time_exists)) {
            $wpdb->query("ALTER TABLE $repairs_table ADD COLUMN repair_time varchar(100) AFTER duration");
        }
        
        // Ensure we have at least one model to reference
        $first_model_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}rbf_models LIMIT 1");
        if (!$first_model_id) {
            // Create a default model if none exists
            $first_brand_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}rbf_brands LIMIT 1");
            if (!$first_brand_id) {
                // Create a default brand first
                $wpdb->insert(
                    $wpdb->prefix . 'rbf_brands',
                    array(
                        'name' => 'Default Brand',
                        'image_url' => 'Brands/default.png',
                        'status' => 'active'
                    ),
                    array('%s', '%s', '%s')
                );
                $first_brand_id = $wpdb->insert_id;
            }
            
            // Create a default model
            $wpdb->insert(
                $wpdb->prefix . 'rbf_models',
                array(
                    'brand_id' => $first_brand_id,
                    'name' => 'Default Model',
                    'image_url' => 'Models/default.png',
                    'status' => 'active'
                ),
                array('%d', '%s', '%s', '%s')
            );
            $first_model_id = $wpdb->insert_id;
        }
        
        // Update existing records with valid model_id
        $wpdb->query($wpdb->prepare("UPDATE $repairs_table SET model_id = %d WHERE model_id = 0 OR model_id IS NULL", $first_model_id));
        $wpdb->query("UPDATE $repairs_table SET price = 0.00 WHERE price IS NULL");
        $wpdb->query("UPDATE $repairs_table SET repair_time = duration WHERE repair_time IS NULL OR repair_time = ''");
    }
    
    /**
     * Ensure pricing database structure exists
     */
    private function ensure_pricing_database_structure() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Default Prices Table
        $default_prices_table = $wpdb->prefix . 'rbf_default_prices';
        $default_prices_sql = "CREATE TABLE $default_prices_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            repair_id mediumint(9) NOT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY repair_id (repair_id)
        ) $charset_collate;";
        
        // Pricing Table (replaces old model_repairs)
        $pricing_table = $wpdb->prefix . 'rbf_pricing';
        $pricing_sql = "CREATE TABLE $pricing_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            brand_id mediumint(9) NOT NULL,
            model_id mediumint(9) NOT NULL,
            repair_id mediumint(9) NOT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY brand_model_repair (brand_id, model_id, repair_id),
            KEY brand_id (brand_id),
            KEY model_id (model_id),
            KEY repair_id (repair_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($default_prices_sql);
        dbDelta($pricing_sql);
        
        // Ensure repairs table is populated first
        $existing_repairs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_repairs");
        if ($existing_repairs == 0) {
            $this->populate_repairs_table();
        }
        
        // Populate default prices if table is empty
        $existing_defaults = $wpdb->get_var("SELECT COUNT(*) FROM $default_prices_table");
        if ($existing_defaults == 0) {
            $this->populate_default_prices();
        }
        
        // Populate pricing table if empty
        $existing_pricing = $wpdb->get_var("SELECT COUNT(*) FROM $pricing_table");
        if ($existing_pricing == 0) {
            $this->populate_pricing_table();
        }
    }
    
    /**
     * Populate default prices for repairs
     */
    private function populate_default_prices() {
        global $wpdb;
        
        $default_prices = array(
            'Screen Replacement' => 299.00,
            'Battery Replacement' => 199.00,
            'Charging Port Repair' => 149.00,
            'Camera Repair' => 249.00,
            'Speaker Repair' => 99.00,
            'Microphone Repair' => 89.00,
            'Volume Button Repair' => 79.00,
            'Power Button Repair' => 79.00,
            'Home Button Repair' => 79.00,
            'Water Damage Repair' => 399.00,
            'Software Issues' => 149.00,
            'Data Recovery' => 199.00
        );
        
        $table_name = $wpdb->prefix . 'rbf_default_prices';
        
        // Safety check - only proceed if repairs table has data
        $repairs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_repairs");
        if ($repairs_count == 0) {
            return; // Skip if repairs table is empty
        }
        
        foreach ($default_prices as $repair_name => $price) {
            // Get repair ID by name
            $repair_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}rbf_repairs WHERE name = %s",
                $repair_name
            ));
            
            if ($repair_id) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'repair_id' => $repair_id,
                        'price' => $price
                    ),
                    array('%d', '%f')
                );
            }
        }
    }
    
    /**
     * Populate pricing table with all combinations
     */
    private function populate_pricing_table() {
        global $wpdb;
        
        $brands = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}rbf_brands WHERE status = 'active'");
        $models = $wpdb->get_results("SELECT id, brand_id FROM {$wpdb->prefix}rbf_models WHERE status = 'active'");
        $repairs = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}rbf_repairs WHERE status = 'active'");
        
        // Safety check - only proceed if we have data
        if (empty($models) || empty($repairs)) {
            return; // Skip if no models or repairs exist yet
        }
        
        $table_name = $wpdb->prefix . 'rbf_pricing';
        
        // Clear existing pricing data to avoid duplicates
        $wpdb->query("DELETE FROM $table_name");
        
        $combinations_created = 0;
        
        foreach ($models as $model) {
            foreach ($repairs as $repair) {
                // Check if this combination already exists
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE brand_id = %d AND model_id = %d AND repair_id = %d",
                    $model->brand_id, $model->id, $repair->id
                ));
                
                if (!$existing) {
                    // Get default price for this repair
                    $default_price = $wpdb->get_var($wpdb->prepare(
                        "SELECT price FROM {$wpdb->prefix}rbf_default_prices WHERE repair_id = %d",
                        $repair->id
                    ));
                    
                    $price = $default_price ?: 0.00;
                    
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'brand_id' => $model->brand_id,
                            'model_id' => $model->id,
                            'repair_id' => $repair->id,
                            'price' => $price
                        ),
                        array('%d', '%d', '%d', '%f')
                    );
                    
                    if ($result !== false) {
                        $combinations_created++;
                    }
                }
            }
        }
        
        // Log the number of combinations created
        error_log("RBF: Created $combinations_created pricing combinations for " . count($models) . " models and " . count($repairs) . " repairs");
    }

    /**
     * Populate initial repair prices for all models
     */
    public function populate_initial_prices() {
        global $wpdb;
        
        // Get all models and repairs
        $models = $wpdb->get_results("SELECT id, brand_id FROM {$wpdb->prefix}rbf_models WHERE status = 'active'");
        $repairs = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}rbf_repairs WHERE status = 'active'");
        
        $table_name = $wpdb->prefix . 'rbf_pricing';
        
        foreach ($models as $model) {
            foreach ($repairs as $repair) {
                // Generate random price: 25-150 AED
                $price = rand(25, 150);
                
                $wpdb->insert(
                    $table_name,
                    array(
                        'brand_id' => $model->brand_id,
                        'model_id' => $model->id,
                        'repair_id' => $repair->id,
                        'price' => $price
                    ),
                    array('%d', '%d', '%d', '%f')
                );
            }
        }
    }
    
    /**
     * Populate brands and models from Brands directory
     */
    public function populate_brands_and_models() {
        global $wpdb;
        $brands_table = $wpdb->prefix . 'rbf_brands';
        $models_table = $wpdb->prefix . 'rbf_models';

        // Predefined brands with their details - match JSON file names
        $brands = [
            ['name' => 'Apple', 'image' => 'Brands/apple.png', 'modal_folder' => 'Brands/iphone_modals/'],
            ['name' => 'Samsung', 'image' => 'Brands/samsung.png', 'modal_folder' => 'Brands/samsung_modals/'],
            ['name' => 'Google Pixel', 'image' => 'Brands/googlepixel.png', 'modal_folder' => 'Brands/google_modals/'],
            ['name' => 'OnePlus', 'image' => 'Brands/oneplus.png', 'modal_folder' => 'Brands/oneplus_modals/']
        ];

        foreach ($brands as $brand) {
            // Check if brand already exists
            $existing_brand = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $brands_table WHERE name = %s", 
                $brand['name']
            ));

            if (!$existing_brand) {
                $wpdb->insert(
                    $brands_table,
                    [
                        'name' => $brand['name'],
                        'image_url' => $brand['image'],
                        'status' => 'active'
                    ],
                    ['%s', '%s', '%s']
                );
                $brand_id = $wpdb->insert_id;

                // Populate models for this brand
                $modal_files = glob(RBF_PLUGIN_PATH . $brand['modal_folder'] . '*.jpg');
                foreach ($modal_files as $modal_file) {
                    $model_name = pathinfo($modal_file, PATHINFO_FILENAME);
                    $model_name = str_replace(['-', '_'], ' ', $model_name);
                    $model_name = ucwords(strtolower($model_name));
                    $model_image = str_replace(RBF_PLUGIN_PATH, '', $modal_file);

                    // Check if model already exists
                    $existing_model = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM $models_table WHERE name = %s AND brand_id = %d", 
                        $model_name, $brand_id
                    ));

                    if (!$existing_model) {
                        $wpdb->insert(
                            $models_table,
                            [
                                'brand_id' => $brand_id,
                                'name' => $model_name,
                                'image_url' => $model_image,
                                'status' => 'active'
                            ],
                            ['%d', '%s', '%s', '%s']
                        );
                    }
                }
            }
        }
    }

   

    

    /**
     * Plugin activation hook
     */
    public function activate_plugin() {
        global $wpdb;
        
        $this->create_plugin_tables();
        $this->populate_brands_and_models();
        
        // Update repairs table structure if needed
        $this->update_repairs_table_structure();
        
        // Ensure pricing database structure exists
        $this->ensure_pricing_database_structure();
    }

    /**
     * Get active brands for frontend
     */
    public function get_active_brands() {
        // Use JSON data source instead of database
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        $brands = $brands_manager->get_brands();
        
        // Filter out inactive brands (you can add a status field later if needed)
        return $brands;
    }

    /**
     * Get active models by brand for frontend
     */
    public function get_active_models_by_brand($brand_id) {
        // Use JSON data source instead of database
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        return $brands_manager->get_models_by_brand($brand_id);
    }

    /**
     * Get brands with models for frontend dropdowns
     */
    public function get_brands_with_models() {
        // Use JSON data source instead of database
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        $brands = $brands_manager->get_brands();
        
        // Prepare data for frontend
        $brands_data = array();
        foreach ($brands as $brand) {
            $brands_data[] = array(
                'id' => $brand['id'],
                'name' => $brand['name'],
                'models' => $brand['models']
            );
        }
        
        return $brands_data;
    }

    /**
     * Shortcode to render brands and models for frontend
     * 
     * @return string HTML for brands and models dropdown
     */
    public function render_brands_models_dropdown() {
        $brands_with_models = $this->get_brands_with_models();
        
        ob_start();
        ?>
        <div class="rbf-brands-models-dropdown">
            <select id="brand-select" name="brand">
                <option value="">Select Brand</option>
                <?php foreach ($brands_with_models as $brand_data): ?>
                    <option value="<?php echo esc_attr($brand_data['brand']->id); ?>">
                        <?php echo esc_html($brand_data['brand']->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select id="model-select" name="model" disabled>
                <option value="">Select Model</option>
            </select>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const brandSelect = document.getElementById('brand-select');
                const modelSelect = document.getElementById('model-select');
                const brandsWithModels = <?php echo json_encode($brands_with_models); ?>;

                brandSelect.addEventListener('change', function() {
                    const selectedBrandId = this.value;
                    
                    // Reset model select
                    modelSelect.innerHTML = '<option value="">Select Model</option>';
                    modelSelect.disabled = true;

                    if (selectedBrandId) {
                        // Find models for selected brand
                        const selectedBrand = brandsWithModels.find(
                            b => b.brand.id === selectedBrandId
                        );

                        if (selectedBrand) {
                            selectedBrand.models.forEach(model => {
                                const option = document.createElement('option');
                                option.value = model.id;
                                option.textContent = model.name;
                                modelSelect.appendChild(option);
                            });

                            modelSelect.disabled = false;
                        }
                    }
                });
            });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('rbf_brands_models', array($this, 'render_brands_models_dropdown'));
        add_shortcode('rbf_store_info', array($this, 'render_store_info'));
    }

    /**
     * Render store information shortcode
     */
    public function render_store_info($atts = array()) {
        $store_settings = $this->get_store_settings();
        
        // Parse attributes
        $atts = shortcode_atts(array(
            'show_address' => 'true',
            'show_contact' => 'true',
            'show_hours' => 'true',
            'class' => 'rbf-store-info'
        ), $atts);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <?php if ($atts['show_address'] === 'true' && !empty($store_settings['address'])): ?>
                <div class="rbf-store-address">
                    <h4>📍 Store Location</h4>
                    <p><strong>Address:</strong> <?php echo esc_html($store_settings['address']); ?></p>
                    <p><strong>City:</strong> <?php echo esc_html($store_settings['city']); ?></p>
                    <p><strong>Emirate:</strong> <?php echo esc_html($store_settings['emirate']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_contact'] === 'true'): ?>
                <div class="rbf-store-contact">
                    <h4>📞 Contact Information</h4>
                    <?php if (!empty($store_settings['phone'])): ?>
                        <p><strong>Phone:</strong> <a href="tel:<?php echo esc_attr($store_settings['phone']); ?>"><?php echo esc_html($store_settings['phone']); ?></a></p>
                    <?php endif; ?>
                    <?php if (!empty($store_settings['whatsapp'])): ?>
                        <p><strong>WhatsApp:</strong> <a href="https://wa.me/<?php echo esc_attr(str_replace(['+', ' ', '-'], '', $store_settings['whatsapp'])); ?>"><?php echo esc_html($store_settings['whatsapp']); ?></a></p>
                    <?php endif; ?>
                    <?php if (!empty($store_settings['email'])): ?>
                        <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($store_settings['email']); ?>"><?php echo esc_html($store_settings['email']); ?></a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_hours'] === 'true' && !empty($store_settings['working_hours'])): ?>
                <div class="rbf-store-hours">
                    <h4>🕒 Working Hours</h4>
                    <p><?php echo esc_html($store_settings['working_hours']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Save custom brand and model
     * 
     * @param string $brand_name Custom brand name
     * @param string $model_name Custom model name
     * @return int|false Brand ID or false if save fails
     */
    private function save_custom_brand_and_model($brand_name, $model_name) {
        global $wpdb;
        $brands_table = $wpdb->prefix . 'rbf_brands';
        $models_table = $wpdb->prefix . 'rbf_models';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        // Check if brand already exists
        $existing_brand = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $brands_table WHERE name = %s", 
            $brand_name
        ));

        // If brand doesn't exist, create it
        if (!$existing_brand) {
            $brand_result = $wpdb->insert(
                $brands_table,
                [
                    'name' => $brand_name,
                    'image_url' => 'Brands/other_brand.jpg', // Default image
                    'status' => 'active'
                ],
                ['%s', '%s', '%s']
            );

            if ($brand_result === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            $brand_id = $wpdb->insert_id;
        } else {
            $brand_id = $existing_brand->id;
        }

        // Check if model already exists for this brand
        $existing_model = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $models_table WHERE name = %s AND brand_id = %d", 
            $model_name, $brand_id
        ));

        // If model doesn't exist, create it
        if (!$existing_model) {
            $model_result = $wpdb->insert(
                $models_table,
                [
                    'brand_id' => $brand_id,
                    'name' => $model_name,
                    'image_url' => 'Brands/other_brand.jpg', // Default image
                    'status' => 'active'
                ],
                ['%d', '%s', '%s', '%s']
            );

            if ($model_result === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        return $brand_id;
    }

    /**
     * Update Brand using JSON (AJAX)
     */
    public function ajax_update_brand_json() {
        // Debug logging
        error_log('RBF AJAX: update_brand_json called');
        error_log('RBF AJAX: POST data: ' . print_r($_POST, true));
        error_log('RBF AJAX: Current user: ' . get_current_user_id());
        error_log('RBF AJAX: User can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('RBF AJAX: User not authorized');
            wp_die('Unauthorized');
        }
        
        $brand_id = intval($_POST['brand_id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        
        error_log('RBF AJAX: Processing brand_id: ' . $brand_id . ', name: ' . $name);
        
        if (empty($name)) {
            wp_send_json_error('Brand name is required');
        }
        
        // Handle file upload if provided
        $logo_path = null; // Keep existing logo
        
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $target_dir = RBF_PLUGIN_PATH . 'Brands/';
            
            // Create Brands directory if it doesn't exist
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $file_name = sanitize_file_name($name) . '_logo.' . $file_extension;
            $target_file = $target_dir . $file_name;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                $logo_path = 'Brands/' . $file_name;
            }
        }
        
        // Use JSON manager to update brand
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $brand_data = array(
            'name' => $name,
            'description' => $description
        );
        
        // Only update logo if new one was uploaded
        if ($logo_path) {
            $brand_data['logo'] = $logo_path;
        }
        
        $result = $brands_manager->update_brand($brand_id, $brand_data);
        
        error_log('RBF AJAX: Update result: ' . ($result ? 'success' : 'failed'));
        
        if ($result) {
            wp_send_json_success('Brand updated successfully');
        } else {
            wp_send_json_error('Failed to update brand');
        }
    }
    
    /**
     * Add Brand using JSON (AJAX)
     */
    public function ajax_add_brand_json() {
        // Debug logging
        error_log('RBF AJAX: add_brand_json called');
        error_log('RBF AJAX: POST data: ' . print_r($_POST, true));
        error_log('RBF AJAX: Current user: ' . get_current_user_id());
        error_log('RBF AJAX: User can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('RBF AJAX: User not authorized for add brand');
            wp_die('Unauthorized');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        
        error_log('RBF AJAX: Processing add brand - name: ' . $name . ', description: ' . $description);
        
        if (empty($name)) {
            wp_send_json_error('Brand name is required');
        }
        
        // Handle file upload
        $logo_path = 'Brands/other_brand.jpg'; // Default logo
        
        if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $target_dir = RBF_PLUGIN_PATH . 'Brands/';
            
            // Create Brands directory if it doesn't exist
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $file_name = sanitize_file_name($name) . '_logo.' . $file_extension;
            $target_file = $target_dir . $file_name;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                $logo_path = 'Brands/' . $file_name;
            }
        }
        
        // Use JSON manager to add brand
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $brand_data = array(
            'name' => $name,
            'description' => $description,
            'logo' => $logo_path,
            'models' => array()
        );
        
        $result = $brands_manager->add_brand($brand_data);
        
        error_log('RBF AJAX: Add brand result: ' . ($result ? 'success' : 'failed'));
        
        if ($result) {
            wp_send_json_success('Brand added successfully');
        } else {
            wp_send_json_error('Failed to add brand');
        }
    }
    
    /**
     * Delete Brand using JSON (AJAX)
     */
    public function ajax_delete_brand_json() {
        // Debug logging
        error_log('RBF AJAX: delete_brand_json called');
        error_log('RBF AJAX: POST data: ' . print_r($_POST, true));
        error_log('RBF AJAX: Current user: ' . get_current_user_id());
        error_log('RBF AJAX: User can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('RBF AJAX: User not authorized for delete brand');
            wp_die('Unauthorized');
        }
        
        $brand_id = intval($_POST['brand_id']);
        
        error_log('RBF AJAX: Processing delete brand_id: ' . $brand_id);
        
        // Use JSON manager to delete brand
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $result = $brands_manager->delete_brand($brand_id);
        
        error_log('RBF AJAX: Delete brand result: ' . ($result ? 'success' : 'failed'));
        
        if ($result) {
            wp_send_json_success('Brand deleted successfully');
        } else {
            wp_send_json_error('Failed to delete brand');
        }
    }
    
    /**
     * Delete Model using JSON (AJAX)
     */
    public function ajax_delete_model_json() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $model_id = intval($_POST['model_id']);
        
        // Use JSON manager to delete model
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $result = $brands_manager->delete_model($model_id);
        
        if ($result) {
            wp_send_json_success('Model deleted successfully');
        } else {
            wp_send_json_error('Failed to delete model');
        }
    }

    /**
     * Add Model using JSON (AJAX)
     */
    public function ajax_add_model_json() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $parent_brand_id = intval($_POST['parent_brand_id']);
        
        if (empty($name) || empty($parent_brand_id)) {
            wp_send_json_error('Model name and parent brand are required');
        }
        
        // Use JSON manager to add model
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $model_data = array(
            'name' => $name,
            'parent_brand_id' => $parent_brand_id
        );
        
        $result = $brands_manager->add_model_by_parent($model_data);
        
        if ($result) {
            wp_send_json_success('Model added successfully');
        } else {
            wp_send_json_error('Failed to add model');
        }
    }

    /**
     * Update Model using JSON (AJAX)
     */
    public function ajax_update_model_json() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $model_id = intval($_POST['model_id']);
        $name = sanitize_text_field($_POST['name']);
        $parent_brand_id = intval($_POST['parent_brand_id']);
        
        if (empty($name) || empty($parent_brand_id)) {
            wp_send_json_error('Model name and parent brand are required');
        }
        
        // Use JSON manager to update model
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $model_data = array(
            'name' => $name,
            'parent_brand_id' => $parent_brand_id
        );
        
        $result = $brands_manager->update_model($model_id, $model_data);
        
        if ($result) {
            wp_send_json_success('Model updated successfully');
        } else {
            wp_send_json_error('Failed to update model');
        }
    }

    /**
     * Add Repair Service using JSON (AJAX)
     */
    public function ajax_add_repair_json() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = sanitize_text_field($_POST['duration']);
        
        if (empty($name)) {
            wp_send_json_error('Service name is required');
        }
        
        // Handle file upload
        $icon_path = '';
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $target_dir = RBF_PLUGIN_PATH . 'Brands/repair_Icons/';
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            $file_extension = pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION);
            $file_name = sanitize_file_name($name) . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES['icon']['tmp_name'], $target_file)) {
                $icon_path = 'Brands/repair_Icons/' . $file_name;
            } else {
                wp_send_json_error('Failed to upload icon image');
            }
        } else {
            wp_send_json_error('Icon image is required');
        }
        
        // Use JSON manager to add repair service
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $service_data = array(
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'duration' => $duration,
            'icon' => $icon_path
        );
        
        $result = $brands_manager->add_repair_service($service_data);
        
        if ($result) {
            wp_send_json_success('Repair service added successfully');
        } else {
            error_log('RBF: Failed to add repair service. Service data: ' . print_r($service_data, true));
            wp_send_json_error('Failed to add repair service');
        }
    }

    /**
     * Update Repair Service using JSON (AJAX)
     */
    public function ajax_update_repair_json() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $service_id = intval($_POST['service_id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = sanitize_text_field($_POST['duration']);
        
        if (empty($name)) {
            wp_send_json_error('Service name is required');
        }
        
        // Handle icon update
        $icon_path = '';
        if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            // New icon uploaded
            $upload_dir = wp_upload_dir();
            $target_dir = RBF_PLUGIN_PATH . 'Brands/repair_Icons/';
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                wp_mkdir_p($target_dir);
            }
            
            $file_extension = pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION);
            $file_name = sanitize_file_name($name) . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES['icon']['tmp_name'], $target_file)) {
                $icon_path = 'Brands/repair_Icons/' . $file_name;
            } else {
                wp_send_json_error('Failed to upload new icon image');
            }
        } elseif (isset($_POST['icon_path']) && !empty($_POST['icon_path'])) {
            // Keep existing icon
            $icon_path = sanitize_text_field($_POST['icon_path']);
        } else {
            wp_send_json_error('Icon path is required');
        }
        
        // Use JSON manager to update repair service
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $service_data = array(
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'duration' => $duration,
            'icon' => $icon_path
        );
        
        $result = $brands_manager->update_repair_service($service_id, $service_data);
        
        if ($result) {
            wp_send_json_success('Repair service updated successfully');
        } else {
            error_log('RBF: Failed to update repair service. Service ID: ' . $service_id . ', Service data: ' . print_r($service_data, true));
            wp_send_json_error('Failed to update repair service');
        }
    }

    /**
     * AJAX handler for updating repair prices
     */
    public function ajax_update_repair_price() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'rbf_prices_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        $brand_id = intval($_POST['brand_id']);
        $model_id = intval($_POST['model_id']);
        $repair_id = intval($_POST['repair_id']);
        $price = floatval($_POST['price']);
        
        $table_name = $wpdb->prefix . 'rbf_pricing';
        
        // Check if price exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE brand_id = %d AND model_id = %d AND repair_id = %d",
            $brand_id, $model_id, $repair_id
        ));
        
        if ($existing) {
            // Update existing price as an EXPLICIT manual override
            $result = $wpdb->update(
                $table_name,
                array(
                    'price' => $price,
                    'is_manual_override' => 1,
                    'source' => 'manual',
                    'updated_at' => current_time('mysql')
                ),
                array('brand_id' => $brand_id, 'model_id' => $model_id, 'repair_id' => $repair_id),
                array('%f', '%d', '%s', '%s'),
                array('%d', '%d', '%d')
            );
        } else {
            // Insert new price as an EXPLICIT manual override
            $result = $wpdb->insert(
                $table_name,
                array(
                    'brand_id' => $brand_id,
                    'model_id' => $model_id,
                    'repair_id' => $repair_id,
                    'price' => $price,
                    'is_manual_override' => 1,
                    'source' => 'manual',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success('Price updated successfully');
        } else {
            wp_send_json_error('Failed to update price');
        }
    }
    
    /**
     * AJAX handler for generating random prices
     */
    public function ajax_generate_random_prices() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'rbf_prices_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        $this->populate_initial_prices();
        wp_send_json_success('Random prices generated successfully');
    }
    
    /**
     * AJAX handler for saving default prices
     */
    public function ajax_save_default_prices() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'rbf_prices_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        $default_prices = $_POST['default_prices'];
        $table_name = $wpdb->prefix . 'rbf_default_prices';
        
        foreach ($default_prices as $repair_id => $price) {
            $repair_id = intval($repair_id);
            $price = floatval($price);
            
            // Check if default price exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE repair_id = %d",
                $repair_id
            ));
            
            if ($existing) {
                // Update existing default price
                $wpdb->update(
                    $table_name,
                    array('price' => $price),
                    array('repair_id' => $repair_id),
                    array('%f'),
                    array('%d')
                );
            } else {
                // Insert new default price
                $wpdb->insert(
                    $table_name,
                    array(
                        'repair_id' => $repair_id,
                        'price' => $price
                    ),
                    array('%d', '%f')
                );
            }
        }
        
        wp_send_json_success('Default prices saved successfully');
    }
    
    /**
     * AJAX handler for bulk price updates
     */
    public function ajax_bulk_update_prices() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'rbf_prices_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        $type = sanitize_text_field($_POST['type']);
        $price = floatval($_POST['price']);
        $table_name = $wpdb->prefix . 'rbf_pricing';
        
        switch ($type) {
            case 'global':
                // Update all prices globally
                $result = $wpdb->update(
                    $table_name,
                    array('price' => $price),
                    array(),
                    array('%f'),
                    array()
                );
                break;
                
            case 'brand':
                $brand_id = intval($_POST['brand_id']);
                // Update all prices for a specific brand
                $result = $wpdb->update(
                    $table_name,
                    array('price' => $price),
                    array('brand_id' => $brand_id),
                    array('%f'),
                    array('%d')
                );
                break;
                
            case 'model':
                $model_id = intval($_POST['model_id']);
                // Update all prices for a specific model
                $result = $wpdb->update(
                    $table_name,
                    array('price' => $price),
                    array('model_id' => $model_id),
                    array('%f'),
                    array('%d')
                );
                break;
                
            default:
                wp_send_json_error('Invalid update type');
                return;
        }
        
        if ($result !== false) {
            wp_send_json_success('Prices updated successfully');
        } else {
            wp_send_json_error('Failed to update prices');
        }
    }
    
    /**
     * AJAX handler for exporting prices to CSV
     */
    public function ajax_export_prices() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_GET['nonce'], 'rbf_prices_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        
        $prices = $wpdb->get_results("
            SELECT 
                b.id as brand_id,
                b.name as brand_name,
                m.id as model_id,
                m.name as model_name,
                r.id as repair_id,
                r.name as repair_name,
                p.price
            FROM {$wpdb->prefix}rbf_pricing p
            JOIN {$wpdb->prefix}rbf_models m ON p.model_id = m.id
            JOIN {$wpdb->prefix}rbf_brands b ON p.brand_id = b.id
            JOIN {$wpdb->prefix}rbf_repairs r ON p.repair_id = r.id
            ORDER BY b.name, m.name, r.id
        ");
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="repair-prices-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, array('Brand ID', 'Brand', 'Model ID', 'Model', 'Repair ID', 'Repair', 'Price (AED)'));
        
        // Add data rows
        foreach ($prices as $price) {
            fputcsv($output, array(
                $price->brand_id,
                $price->brand_name,
                $price->model_id,
                $price->model_name,
                $price->repair_id,
                $price->repair_name,
                $price->price
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * AJAX handler for importing prices from CSV
     */
    public function ajax_import_prices() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'rbf_prices_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('No CSV file uploaded or upload error');
        }
        
        $file = $_FILES['csv_file'];
        $handle = fopen($file['tmp_name'], 'r');
        
        if (!$handle) {
            wp_send_json_error('Could not open CSV file');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rbf_pricing';
        $updated = 0;
        $errors = array();
        
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 7) {
                $brand_id = intval($data[0]);
                $model_id = intval($data[2]);
                $repair_id = intval($data[4]);
                $price = floatval($data[6]);
                
                if ($brand_id && $model_id && $repair_id && $price >= 0) {
                    // Update or insert price
                    $wpdb->replace(
                        $table_name,
                        array(
                            'brand_id' => $brand_id,
                            'model_id' => $model_id,
                            'repair_id' => $repair_id,
                            'price' => $price
                        ),
                        array('%d', '%d', '%d', '%f')
                    );
                    $updated++;
                } else {
                    $errors[] = "Invalid data: Brand ID: $brand_id, Model ID: $model_id, Repair ID: $repair_id, Price: $price";
                }
            }
        }
        
        fclose($handle);
        
        if ($updated > 0) {
            wp_send_json_success("Successfully updated $updated prices" . (count($errors) > 0 ? ". Errors: " . implode(', ', $errors) : ''));
        } else {
            wp_send_json_error('No prices were updated. Errors: ' . implode(', ', $errors));
        }
    }
    
    /**
     * AJAX handler for setting up database tables
     */
    public function ajax_setup_database() {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'], 'rbf_prices_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }
        
        try {
            // Create all tables
            $this->create_plugin_tables();
            
            // Populate brands and models
            $this->populate_brands_and_models();
            
            // Ensure pricing database structure exists
            $this->ensure_pricing_database_structure();
            
            wp_send_json_success('Database tables created and populated successfully');
        } catch (Exception $e) {
            wp_send_json_error('Error setting up database: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete Repair Service using JSON (AJAX)
     */
    public function ajax_delete_repair_json() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $service_id = intval($_POST['service_id']);
        
        if (empty($service_id)) {
            wp_send_json_error('Service ID is required');
        }
        
        // Use JSON manager to delete repair service
        if (!class_exists('RBF_Brands_Models_Manager')) {
            require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
        }
        
        $brands_manager = new RBF_Brands_Models_Manager();
        
        $result = $brands_manager->delete_repair_service($service_id);
        
        if ($result) {
            wp_send_json_success('Repair service deleted successfully');
        } else {
            wp_send_json_error('Failed to delete repair service');
        }
    }
    
    /**
     * Add Booking (AJAX)
     */
    public function ajax_add_booking() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        // Validate required fields
        $required_fields = array('customer_name', 'customer_email', 'customer_phone');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error('Missing required field: ' . $field);
            }
        }
        
        // Generate unique booking ID - shorter format with brand name
        $booking_id = 'eFIX-' . strtoupper(substr(md5(time() . rand()), 0, 5));
        
        // Calculate VAT and totals
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $vat_amount = $subtotal * 0.05; // 5% VAT
        $total_amount = $subtotal + $vat_amount;
        
        // Prepare insert data
        $insert_data = array(
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_email' => sanitize_text_field($_POST['customer_email']),
            'brand' => sanitize_text_field($_POST['brand'] ?? ''),
            'model' => sanitize_text_field($_POST['model'] ?? ''),
            'repair' => sanitize_textarea_field($_POST['repairs'] ?? ''),
            'service_type' => sanitize_text_field($_POST['service_type'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'subtotal' => $subtotal,
            'vat_amount' => $vat_amount,
            'total_amount' => $total_amount,
            'status' => 'pending',
            'booking_id' => $booking_id,
            'created_at' => current_time('mysql')
        );
        
        // Insert booking into database
        $result = $wpdb->insert(
            $wpdb->prefix . 'rbf_bookings',
            $insert_data,
            array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%f', '%f', '%f', '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to save booking: ' . $wpdb->last_error);
        }
        
        wp_send_json_success('Booking added successfully');
    }
    


    /**
     * Process payment via AJAX
     */
    public function ajax_process_payment() {
        // Implement payment processing logic
        wp_send_json_success('Payment processed successfully');
    }

    /**
     * Create payment intent via AJAX
     */
    public function ajax_create_payment_intent() {
        // Implement payment intent creation logic
        wp_send_json_success('Payment intent created successfully');
    }

    /**
     * Confirm payment via AJAX
     */
    public function ajax_confirm_payment() {
        // Implement payment confirmation logic
        wp_send_json_success('Payment confirmed successfully');
    }

    /**
     * Admin Payment Settings
     */
        public function admin_payment_settings() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            check_admin_referer('rbf_payment_settings');
            
            // Save PayPal settings
            update_option('rbf_paypal_enabled', isset($_POST['paypal_enabled']));
            update_option('rbf_paypal_client_id', sanitize_text_field($_POST['paypal_client_id']));
            update_option('rbf_paypal_secret', sanitize_text_field($_POST['paypal_secret']));
            update_option('rbf_paypal_mode', sanitize_text_field($_POST['paypal_mode']));
            
            // Save Stripe settings
            update_option('rbf_stripe_enabled', isset($_POST['stripe_enabled']));
            update_option('rbf_stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key']));
            update_option('rbf_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
            update_option('rbf_stripe_mode', sanitize_text_field($_POST['stripe_mode']));
            
            // Save Delivery Address settings
            update_option('rbf_store_address', sanitize_text_field($_POST['store_address']));
            update_option('rbf_store_city', sanitize_text_field($_POST['store_city']));
            update_option('rbf_store_emirate', sanitize_text_field($_POST['store_emirate']));
            update_option('rbf_store_phone', sanitize_text_field($_POST['store_phone']));
            update_option('rbf_store_whatsapp', sanitize_text_field($_POST['store_whatsapp']));
            update_option('rbf_store_email', sanitize_email($_POST['store_email']));
            update_option('rbf_store_working_hours', sanitize_text_field($_POST['store_working_hours']));
            
            echo '<div class="notice notice-success is-dismissible"><p><strong>Payment gateways and workshop delivery settings saved successfully!</strong></p></div>';
        }
        
        // Get current settings
        $paypal_enabled = get_option('rbf_paypal_enabled', false);
        $paypal_client_id = get_option('rbf_paypal_client_id', '');
        $paypal_secret = get_option('rbf_paypal_secret', '');
        $paypal_mode = get_option('rbf_paypal_mode', 'sandbox');
        
        $stripe_enabled = get_option('rbf_stripe_enabled', false);
        $stripe_publishable_key = get_option('rbf_stripe_publishable_key', '');
        $stripe_secret_key = get_option('rbf_stripe_secret_key', '');
        $stripe_mode = get_option('rbf_stripe_mode', 'test');
        
        // Get delivery address settings
        $store_address = get_option('rbf_store_address', '');
        $store_city = get_option('rbf_store_city', '');
        $store_emirate = get_option('rbf_store_emirate', '');
        $store_phone = get_option('rbf_store_phone', '');
        $store_whatsapp = get_option('rbf_store_whatsapp', '');
        $store_email = get_option('rbf_store_email', '');
        $store_working_hours = get_option('rbf_store_working_hours', 'Sunday - Thursday: 9:00 AM - 6:00 PM');
        ?>
        <div class="wrap rbf-admin-wrap">
            <!-- Executive Header -->
            <div class="rbf-admin-header">
                <div class="rbf-header-left">
                    <h1>
                        💳 Payment Gateways & Delivery Settings
                        <span class="rbf-version-badge">v2.0.2 Secure</span>
                    </h1>
                    <p class="rbf-header-desc">Configure Stripe, PayPal, and customer device drop-off / workshop delivery locations.</p>
                </div>
                <div class="rbf-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" class="rbf-btn-light">
                        📋 View Bookings
                    </a>
                </div>
            </div>

            <!-- Admin Navigation Tabs -->
            <div class="rbf-nav-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking')); ?>" class="rbf-nav-tab">📊 Dashboard</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" class="rbf-nav-tab">📅 Bookings</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-prices')); ?>" class="rbf-nav-tab">💰 Bulk Prices</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-brands')); ?>" class="rbf-nav-tab">📱 Brands</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-models')); ?>" class="rbf-nav-tab">📲 Models</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-repairs')); ?>" class="rbf-nav-tab">🛠️ Repairs</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-currency')); ?>" class="rbf-nav-tab">💱 Currency & VAT</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-whatsapp')); ?>" class="rbf-nav-tab">💬 WhatsApp Alerts</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-payments')); ?>" class="rbf-nav-tab active">⚙️ Payment Gateways</a>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('rbf_payment_settings'); ?>
                
                <div class="rbf-grid-2col" style="margin-bottom: 24px;">
                    <!-- PayPal Settings Card -->
                    <div class="rbf-card">
                        <div class="rbf-card-header">
                            <h3 class="rbf-card-title">
                                <span class="dashicons dashicons-cart" style="color: #003087;"></span>
                                PayPal Express Checkout
                            </h3>
                            <span class="rbf-badge <?php echo $paypal_enabled ? 'rbf-badge-completed' : 'rbf-badge-cancelled'; ?>">
                                <?php echo $paypal_enabled ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        
                        <div class="rbf-form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="paypal_enabled" value="1" <?php checked($paypal_enabled); ?> style="width: 18px; height: 18px;">
                                <strong>Enable PayPal payments during checkout</strong>
                            </label>
                            <p class="description">Accept credit cards and PayPal balance securely from customers.</p>
                        </div>

                        <div class="rbf-form-group">
                            <label for="paypal_mode">Environment Mode</label>
                            <select name="paypal_mode" id="paypal_mode" class="rbf-select">
                                <option value="sandbox" <?php selected($paypal_mode, 'sandbox'); ?>>🧪 Sandbox (Testing Mode)</option>
                                <option value="live" <?php selected($paypal_mode, 'live'); ?>>🚀 Live (Production Mode)</option>
                            </select>
                        </div>

                        <div class="rbf-form-group">
                            <label for="paypal_client_id">PayPal Client ID</label>
                            <input type="text" id="paypal_client_id" name="paypal_client_id" value="<?php echo esc_attr($paypal_client_id); ?>" class="rbf-input" placeholder="e.g. AeA...123">
                            <p class="description">From developer.paypal.com dashboard apps.</p>
                        </div>

                        <div class="rbf-form-group">
                            <label for="paypal_secret">PayPal Secret Key</label>
                            <input type="password" id="paypal_secret" name="paypal_secret" value="<?php echo esc_attr($paypal_secret); ?>" class="rbf-input" placeholder="••••••••••••••••">
                            <p class="description">Keep your API secret strictly private.</p>
                        </div>
                    </div>

                    <!-- Stripe Settings Card -->
                    <div class="rbf-card">
                        <div class="rbf-card-header">
                            <h3 class="rbf-card-title">
                                <span class="dashicons dashicons-money-alt" style="color: #635bff;"></span>
                                Stripe Payment Gateway
                            </h3>
                            <span class="rbf-badge <?php echo $stripe_enabled ? 'rbf-badge-completed' : 'rbf-badge-cancelled'; ?>">
                                <?php echo $stripe_enabled ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>

                        <div class="rbf-form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="stripe_enabled" value="1" <?php checked($stripe_enabled); ?> style="width: 18px; height: 18px;">
                                <strong>Enable Stripe payments during checkout</strong>
                            </label>
                            <p class="description">Direct on-site card payments (Visa, Mastercard, Apple Pay).</p>
                        </div>

                        <div class="rbf-form-group">
                            <label for="stripe_mode">Environment Mode</label>
                            <select name="stripe_mode" id="stripe_mode" class="rbf-select">
                                <option value="test" <?php selected($stripe_mode, 'test'); ?>>🧪 Test Mode</option>
                                <option value="live" <?php selected($stripe_mode, 'live'); ?>>🚀 Live Mode</option>
                            </select>
                        </div>

                        <div class="rbf-form-group">
                            <label for="stripe_publishable_key">Stripe Publishable Key</label>
                            <input type="text" id="stripe_publishable_key" name="stripe_publishable_key" value="<?php echo esc_attr($stripe_publishable_key); ?>" class="rbf-input" placeholder="pk_test_... or pk_live_...">
                            <p class="description">Public API key used on client checkout.</p>
                        </div>

                        <div class="rbf-form-group">
                            <label for="stripe_secret_key">Stripe Secret Key</label>
                            <input type="password" id="stripe_secret_key" name="stripe_secret_key" value="<?php echo esc_attr($stripe_secret_key); ?>" class="rbf-input" placeholder="sk_test_... or sk_live_...">
                            <p class="description">Secret key used for secure server-side charges.</p>
                        </div>
                    </div>
                </div>

                <!-- Workshop & Store Delivery Card -->
                <div class="rbf-card">
                    <div class="rbf-card-header">
                        <h3 class="rbf-card-title">
                            <span class="dashicons dashicons-location" style="color: #017c36;"></span>
                            Store & Workshop Drop-off Location
                        </h3>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <div class="rbf-form-group">
                            <label for="store_address">Physical Store Address</label>
                            <textarea id="store_address" name="store_address" rows="3" class="rbf-textarea" style="width:100%; max-width:100%;" placeholder="e.g. Shop 12, Tech Tower, Al Barsha 1"><?php echo esc_textarea($store_address); ?></textarea>
                            <p class="description">Displayed to customers who choose "Bring to Shop" service.</p>
                        </div>

                        <div>
                            <div class="rbf-form-group">
                                <label for="store_city">City</label>
                                <input type="text" id="store_city" name="store_city" value="<?php echo esc_attr($store_city); ?>" class="rbf-input" style="width:100%; max-width:100%;" placeholder="Dubai">
                            </div>
                            <div class="rbf-form-group">
                                <label for="store_emirate">State / Emirate</label>
                                <input type="text" id="store_emirate" name="store_emirate" value="<?php echo esc_attr($store_emirate); ?>" class="rbf-input" style="width:100%; max-width:100%;" placeholder="Dubai / Abu Dhabi / Sharjah">
                            </div>
                        </div>

                        <div>
                            <div class="rbf-form-group">
                                <label for="store_phone">Store Helpline Phone</label>
                                <input type="text" id="store_phone" name="store_phone" value="<?php echo esc_attr($store_phone); ?>" class="rbf-input" style="width:100%; max-width:100%;" placeholder="+971 4 123 4567">
                            </div>
                            <div class="rbf-form-group">
                                <label for="store_whatsapp">Official WhatsApp Number</label>
                                <input type="text" id="store_whatsapp" name="store_whatsapp" value="<?php echo esc_attr($store_whatsapp); ?>" class="rbf-input" style="width:100%; max-width:100%;" placeholder="+971 50 123 4567">
                            </div>
                        </div>

                        <div>
                            <div class="rbf-form-group">
                                <label for="store_email">Support Email</label>
                                <input type="email" id="store_email" name="store_email" value="<?php echo esc_attr($store_email); ?>" class="rbf-input" style="width:100%; max-width:100%;" placeholder="support@efix.ae">
                            </div>
                            <div class="rbf-form-group">
                                <label for="store_working_hours">Working Hours</label>
                                <input type="text" id="store_working_hours" name="store_working_hours" value="<?php echo esc_attr($store_working_hours); ?>" class="rbf-input" style="width:100%; max-width:100%;" placeholder="Mon - Sat: 9:00 AM - 9:00 PM">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--rbf-border);">
                        <button type="submit" name="submit" class="rbf-btn-primary">
                            <span class="dashicons dashicons-saved"></span> Save All Gateway & Address Settings
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
        public function admin_currency_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $currency_mgr = class_exists('RBF_Currency') ? RBF_Currency::get_instance() : null;

        // Handle form save
        if (isset($_POST['rbf_save_currency_settings'])) {
            check_admin_referer('rbf_currency_settings_nonce');
            
            $default_cur = sanitize_text_field($_POST['default_currency'] ?? 'AED');
            $auto_fetch = isset($_POST['auto_fetch_rates']) ? 1 : 0;
            $api_key = sanitize_text_field($_POST['exchangerate_api_key'] ?? '');
            $vat_rate = floatval($_POST['vat_rate'] ?? 5);

            update_option('rbf_default_currency', $default_cur);
            update_option('rbf_auto_fetch_rates', $auto_fetch);
            update_option('rbf_exchangerate_api_key', $api_key);
            update_option('rbf_vat_rate', $vat_rate);

            // Manual rates
            $manual_rates = [];
            if (isset($_POST['rates']) && is_array($_POST['rates'])) {
                foreach ($_POST['rates'] as $code => $rate) {
                    $code = sanitize_text_field($code);
                    $manual_rates[$code] = floatval($rate);
                }
                update_option('rbf_manual_exchange_rates', $manual_rates);
            }

            if (isset($_POST['force_refresh_rates']) && $currency_mgr) {
                $currency_mgr->fetch_live_rates();
            }

            echo '<div class="notice notice-success is-dismissible"><p><strong>Currency and Exchange Rate settings updated successfully!</strong></p></div>';
        }

        $default_currency = get_option('rbf_default_currency', 'AED');
        $auto_fetch = get_option('rbf_auto_fetch_rates', 1);
        $api_key = get_option('rbf_exchangerate_api_key', '');
        $vat_rate = get_option('rbf_vat_rate', 5);
        $currencies = $currency_mgr ? $currency_mgr->get_currencies_data() : [];
        $current_rates = $currency_mgr ? $currency_mgr->get_exchange_rates() : [];
        $last_updated = get_option('rbf_exchange_rates_last_updated', 0);
        ?>
        <div class="wrap rbf-admin-wrap">
            <!-- Executive Header -->
            <div class="rbf-admin-header">
                <div class="rbf-header-left">
                    <h1>
                        💱 Multi-Currency & Live Rates Management
                        <span class="rbf-version-badge">v2.0.2 Global</span>
                    </h1>
                    <p class="rbf-header-desc">Configure primary base currency (AED, SAR, USD), automatic 12-hour exchange rates sync, and regional VAT calculation.</p>
                </div>
                <div class="rbf-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-prices')); ?>" class="rbf-btn-light">
                        💰 Bulk Repair Prices
                    </a>
                </div>
            </div>

            <!-- Admin Navigation Tabs -->
            <div class="rbf-nav-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking')); ?>" class="rbf-nav-tab">📊 Dashboard</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" class="rbf-nav-tab">📅 Bookings</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-prices')); ?>" class="rbf-nav-tab">💰 Bulk Prices</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-brands')); ?>" class="rbf-nav-tab">📱 Brands</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-models')); ?>" class="rbf-nav-tab">📲 Models</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-repairs')); ?>" class="rbf-nav-tab">🛠️ Repairs</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-currency')); ?>" class="rbf-nav-tab active">💱 Currency & VAT</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-whatsapp')); ?>" class="rbf-nav-tab">💬 WhatsApp Alerts</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-payments')); ?>" class="rbf-nav-tab">⚙️ Payment Gateways</a>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('rbf_currency_settings_nonce'); ?>
                
                <div class="rbf-grid-2col" style="margin-bottom: 24px;">
                    <!-- Base Currency & Tax Settings Card -->
                    <div class="rbf-card">
                        <div class="rbf-card-header">
                            <h3 class="rbf-card-title">
                                <span class="dashicons dashicons-admin-settings" style="color: #017c36;"></span>
                                Base Currency & Regional VAT
                            </h3>
                        </div>
                        
                        <div class="rbf-form-group">
                            <label for="default_currency">Default Base Store Currency</label>
                            <select name="default_currency" id="default_currency" class="rbf-select" style="width:100%; max-width:100%;">
                                <?php foreach ($currencies as $code => $cur): ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($default_currency, $code); ?>>
                                        <?php echo esc_html($code . ' (' . $cur['symbol'] . ') - ' . $cur['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">All prices in the catalog JSON file are stored relative to AED (Base: 1.00 AED).</p>
                        </div>

                        <div class="rbf-form-group">
                            <label for="vat_rate">VAT / Tax Rate (%)</label>
                            <input type="number" step="0.1" min="0" max="100" id="vat_rate" name="vat_rate" value="<?php echo esc_attr($vat_rate); ?>" class="rbf-input" style="max-width: 150px;"> %
                            <p class="description">UAE standard VAT is 5%, Saudi Arabia VAT is 15%. Calculated dynamically during checkout.</p>
                        </div>
                    </div>

                    <!-- Live Exchange Rate Feed Card -->
                    <div class="rbf-card">
                        <div class="rbf-card-header">
                            <h3 class="rbf-card-title">
                                <span class="dashicons dashicons-update" style="color: #0284c7;"></span>
                                Live Rates Auto-Sync
                            </h3>
                            <span class="rbf-badge <?php echo $auto_fetch ? 'rbf-badge-completed' : 'rbf-badge-cancelled'; ?>">
                                <?php echo $auto_fetch ? 'Automated' : 'Manual'; ?>
                            </span>
                        </div>

                        <div class="rbf-form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="auto_fetch_rates" value="1" <?php checked($auto_fetch, 1); ?> style="width: 18px; height: 18px;">
                                <strong>Enable automatic live exchange rates sync</strong>
                            </label>
                            <p class="description">Refreshes every 12 hours from open exchange rate API.</p>
                            <?php 
                            $display_rates_date = '';
                            if (!empty($last_updated)) {
                                if (is_numeric($last_updated)) {
                                    $display_rates_date = date('Y-m-d H:i:s', (int)$last_updated);
                                } else {
                                    $display_rates_date = esc_html($last_updated);
                                }
                            }
                            ?>
                            <?php if (!empty($display_rates_date)): ?>
                                <p style="margin: 8px 0 0 0; color: #15803d; font-weight: 600; font-size: 12.5px;">
                                    ✓ Rates last fetched: <?php echo $display_rates_date; ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="rbf-form-group">
                            <label for="exchangerate_api_key">ExchangeRate API Key (Optional)</label>
                            <input type="text" id="exchangerate_api_key" name="exchangerate_api_key" value="<?php echo esc_attr($api_key); ?>" class="rbf-input" placeholder="Free open-access tier works without key">
                            <p class="description">Optional: Provide your exchangerate-api.com key if you have a custom paid plan.</p>
                        </div>
                    </div>
                </div>

                <!-- Active Conversion Rates Matrix Card -->
                <div class="rbf-card">
                    <div class="rbf-card-header">
                        <h3 class="rbf-card-title">
                            <span class="dashicons dashicons-chart-line" style="color: #017c36;"></span>
                            Active Regional Currency Rates (Relative to 1.00 AED)
                        </h3>
                    </div>

                    <div class="rbf-table-responsive">
                        <table class="rbf-modern-table">
                            <thead>
                                <tr>
                                    <th>Currency</th>
                                    <th>Symbol</th>
                                    <th>1 AED Equals</th>
                                    <th>Preview (100 AED Screen Repair)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currencies as $code => $cur): 
                                    $rate = isset($current_rates[$code]) ? $current_rates[$code] : 1.0;
                                ?>
                                <tr>
                                    <td><strong><?php echo esc_html($code); ?></strong> — <?php echo esc_html($cur['name']); ?></td>
                                    <td><span style="font-size: 16px; font-weight: bold; color: var(--rbf-dark);"><?php echo esc_html($cur['symbol']); ?></span></td>
                                    <td>
                                        <input type="number" step="0.0001" min="0" name="rates[<?php echo esc_attr($code); ?>]" value="<?php echo esc_attr($rate); ?>" class="rbf-input" style="width: 140px; padding: 6px 10px;">
                                    </td>
                                    <td>
                                        <span class="rbf-badge rbf-badge-confirmed" style="font-size: 13px;">
                                            <?php echo esc_html($cur['symbol'] . ' ' . number_format(100 * $rate, 2)); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--rbf-border); display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" name="rbf_save_currency_settings" class="rbf-btn-primary">
                            <span class="dashicons dashicons-saved"></span> Save Currency Settings
                        </button>
                        <button type="submit" name="force_refresh_rates" class="button button-secondary" style="height: auto; padding: 10px 18px; font-weight: 600; border-radius: 8px;">
                            <span class="dashicons dashicons-update"></span> 🔄 Force Fetch Live Rates Now
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
        public function admin_whatsapp_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (isset($_POST['rbf_save_wa_settings'])) {
            check_admin_referer('rbf_wa_settings_nonce');

            update_option('rbf_business_whatsapp', sanitize_text_field($_POST['business_whatsapp'] ?? ''));
            update_option('rbf_ultramsg_enabled', isset($_POST['ultramsg_enabled']) ? 1 : 0);
            update_option('rbf_ultramsg_instance_id', sanitize_text_field($_POST['ultramsg_instance_id'] ?? ''));
            update_option('rbf_ultramsg_token', sanitize_text_field($_POST['ultramsg_token'] ?? ''));
            update_option('rbf_wa_admin_notification', isset($_POST['wa_admin_notification']) ? 1 : 0);
            update_option('rbf_wa_admin_number', sanitize_text_field($_POST['wa_admin_number'] ?? ''));

            echo '<div class="notice notice-success is-dismissible"><p><strong>WhatsApp notification settings saved successfully!</strong></p></div>';
        }

        $business_wa = get_option('rbf_business_whatsapp', get_option('rbf_store_phone', '+971501234567'));
        $ultramsg_enabled = get_option('rbf_ultramsg_enabled', 0);
        $ultramsg_instance = get_option('rbf_ultramsg_instance_id', '');
        $ultramsg_token = get_option('rbf_ultramsg_token', '');
        $admin_notify = get_option('rbf_wa_admin_notification', 1);
        $admin_number = get_option('rbf_wa_admin_number', '');
        ?>
        <div class="wrap rbf-admin-wrap">
            <!-- Executive Header -->
            <div class="rbf-admin-header">
                <div class="rbf-header-left">
                    <h1>
                        💬 WhatsApp Notifications & Customer Chat
                        <span class="rbf-version-badge">v2.0.2 Live</span>
                    </h1>
                    <p class="rbf-header-desc">Configure direct 1-click WhatsApp customer support, automated UltraMsg booking confirmations, and manager alerts.</p>
                </div>
                <div class="rbf-header-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" class="rbf-btn-light">
                        📋 View Bookings
                    </a>
                </div>
            </div>

            <!-- Admin Navigation Tabs -->
            <div class="rbf-nav-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking')); ?>" class="rbf-nav-tab">📊 Dashboard</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-bookings')); ?>" class="rbf-nav-tab">📅 Bookings</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-prices')); ?>" class="rbf-nav-tab">💰 Bulk Prices</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-brands')); ?>" class="rbf-nav-tab">📱 Brands</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-models')); ?>" class="rbf-nav-tab">📲 Models</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-repairs')); ?>" class="rbf-nav-tab">🛠️ Repairs</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-currency')); ?>" class="rbf-nav-tab">💱 Currency & VAT</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-whatsapp')); ?>" class="rbf-nav-tab active">💬 WhatsApp Alerts</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=repair-booking-payments')); ?>" class="rbf-nav-tab">⚙️ Payment Gateways</a>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('rbf_wa_settings_nonce'); ?>
                
                <div class="rbf-grid-2col" style="margin-bottom: 24px;">
                    <!-- Direct Chat Card -->
                    <div class="rbf-card">
                        <div class="rbf-card-header">
                            <h3 class="rbf-card-title">
                                <span class="dashicons dashicons-format-chat" style="color: #25D366;"></span>
                                1. Direct Customer WhatsApp Chat (Free)
                            </h3>
                            <span class="rbf-badge rbf-badge-completed">Free</span>
                        </div>
                        <p class="description" style="margin-bottom: 18px;">
                            Customers see a 1-click "Chat on WhatsApp" button on booking success with pre-filled device & repair info.
                        </p>

                        <div class="rbf-form-group">
                            <label for="business_whatsapp">Technician / Store WhatsApp Number *</label>
                            <input type="text" id="business_whatsapp" name="business_whatsapp" value="<?php echo esc_attr($business_wa); ?>" class="rbf-input" placeholder="+971501234567">
                            <p class="description">Include full international country code (e.g., +971 for UAE, +966 for KSA).</p>
                        </div>

                        <div class="rbf-form-group" style="margin-top: 24px;">
                            <label><strong>Tracking Shortcode for Customers:</strong></label>
                            <div style="background: #f8fafc; border: 1px solid var(--rbf-border); padding: 12px 16px; border-radius: 8px; margin-top: 6px;">
                                <p style="margin: 0 0 6px 0; font-size: 13px; color: var(--rbf-muted);">Place on any page for live self-service repair lookup:</p>
                                <code class="rbf-code-pill">[rbf_track_repair]</code>
                            </div>
                        </div>
                    </div>

                    <!-- Automated UltraMsg API Card -->
                    <div class="rbf-card">
                        <div class="rbf-card-header">
                            <h3 class="rbf-card-title">
                                <span class="dashicons dashicons-cloud" style="color: #0284c7;"></span>
                                2. Automated API Gateway (UltraMsg)
                            </h3>
                            <span class="rbf-badge <?php echo $ultramsg_enabled ? 'rbf-badge-completed' : 'rbf-badge-cancelled'; ?>">
                                <?php echo $ultramsg_enabled ? 'Active' : 'Disabled'; ?>
                            </span>
                        </div>
                        <p class="description" style="margin-bottom: 18px;">
                            Sends instant automated WhatsApp booking confirmations and dispatch alerts without human intervention.
                        </p>

                        <div class="rbf-form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="ultramsg_enabled" value="1" <?php checked($ultramsg_enabled, 1); ?> style="width: 18px; height: 18px;">
                                <strong>Enable automated WhatsApp messaging via UltraMsg API</strong>
                            </label>
                        </div>

                        <div class="rbf-form-group">
                            <label for="ultramsg_instance_id">UltraMsg Instance ID</label>
                            <input type="text" id="ultramsg_instance_id" name="ultramsg_instance_id" value="<?php echo esc_attr($ultramsg_instance); ?>" class="rbf-input" placeholder="instance12345">
                        </div>

                        <div class="rbf-form-group">
                            <label for="ultramsg_token">UltraMsg Token</label>
                            <input type="password" id="ultramsg_token" name="ultramsg_token" value="<?php echo esc_attr($ultramsg_token); ?>" class="rbf-input" placeholder="ultramsg_token_secret">
                        </div>

                        <div class="rbf-form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="wa_admin_notification" value="1" <?php checked($admin_notify, 1); ?> style="width: 18px; height: 18px;">
                                <strong>Send instant WhatsApp alert to Store Manager on every new booking</strong>
                            </label>
                        </div>

                        <div class="rbf-form-group">
                            <label for="wa_admin_number">Manager WhatsApp Number</label>
                            <input type="text" id="wa_admin_number" name="wa_admin_number" value="<?php echo esc_attr($admin_number); ?>" class="rbf-input" placeholder="+971501234567">
                        </div>
                    </div>
                </div>

                <div class="rbf-card">
                    <button type="submit" name="rbf_save_wa_settings" class="rbf-btn-primary">
                        <span class="dashicons dashicons-saved"></span> Save WhatsApp & Notification Settings
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
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
        $global_markup = $pricing ? $pricing->get_global_markup() : 13;
        $repairs_with_labour = $wpdb->get_results("SELECT id, name, icon, price, labour_cost FROM {$wpdb->prefix}rbf_repairs WHERE status = 'active' ORDER BY id ASC", ARRAY_A);
        $mappings = class_exists('RBF_Supplier_Manager') ? RBF_Supplier_Manager::get_instance()->get_mappings() : array();
        $last_sync = get_option('rbf_last_supplier_sync', array());
        $sync_logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rbf_supplier_sync_logs ORDER BY id DESC LIMIT 10", ARRAY_A);
        $next_sync = wp_next_scheduled('rbf_supplier_price_sync');

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
                <button type="button" class="rbf-tab-btn" data-tab="tab-settings-labour">
                    <span class="dashicons dashicons-admin-generic"></span> 4. Pricing Settings & Labour Costs
                </button>
                <button type="button" class="rbf-tab-btn" data-tab="tab-supplier-sync">
                    <span class="dashicons dashicons-cloud"></span> 5. Supplier Price Sync & Feeds
                </button>
                <button type="button" class="rbf-tab-btn" data-tab="tab-supplier-mappings">
                    <span class="dashicons dashicons-networking"></span> 6. Supplier Mappings (<?php echo count($mappings); ?>)
                </button>
                <button type="button" class="rbf-tab-btn" data-tab="tab-inspector">
                    <span class="dashicons dashicons-search"></span> 7. Cascading Price Inspector & Tester
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

            <!-- TAB 4: PRICING SETTINGS & LABOUR COSTS -->
            <div class="rbf-tab-panel" id="tab-settings-labour">
                <div class="rbf-panel-card">
                    <div class="rbf-card-header">
                        <div>
                            <h2>Pricing Settings & Master Labour Costs</h2>
                            <p>Configure the global markup percentage and flat labour cost per repair type. Formula: <code>Customer Price = (Supplier Part Price + Labour Cost) &times; (1 + Markup %)</code></p>
                        </div>
                        <div>
                            <button type="button" class="button button-primary button-large" id="rbf-btn-save-settings">
                                <span class="dashicons dashicons-saved"></span> Save Pricing Settings & Labour Costs
                            </button>
                        </div>
                    </div>

                    <!-- Global Markup Card -->
                    <div class="rbf-settings-box" style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
                        <h3 style="margin-top: 0; color: #1e293b;"><span class="dashicons dashicons-tag"></span> Global Markup Percentage</h3>
                        <p style="color: #64748b; margin-bottom: 15px;">Applied to the sum of base supplier cost and repair labour fee. Default is 13%.</p>
                        <div style="display: flex; align-items: center; gap: 10px; max-width: 300px;">
                            <input type="number" id="rbf-global-markup-input" class="widefat" step="0.1" min="0" value="<?php echo esc_attr($global_markup); ?>" style="font-size: 16px; font-weight: bold; padding: 8px;">
                            <span style="font-size: 18px; font-weight: bold; color: #475569;">%</span>
                        </div>
                    </div>

                    <!-- Master Labour Costs Table -->
                    <h3 style="margin-bottom: 15px; color: #1e293b;"><span class="dashicons dashicons-admin-tools"></span> Labour Cost by Repair Service</h3>
                    <p style="color: #64748b; margin-bottom: 15px;">Labour costs belong strictly to the repair type (not individual models). The customer form never exposes the labour or supplier cost breakdown.</p>
                    <div class="rbf-table-responsive">
                        <table class="rbf-pricing-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Repair Service</th>
                                    <th style="text-align: center;">Catalog Fallback (<?php echo esc_html($currency); ?>)</th>
                                    <th style="min-width: 180px; text-align: center;">Labour Fee (<?php echo esc_html($currency); ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($repairs_with_labour as $idx => $r): ?>
                                    <tr>
                                        <td style="text-align: center; color: #888;"><?php echo ($idx + 1); ?></td>
                                        <td>
                                            <span class="dashicons <?php echo !empty($r['icon']) ? esc_attr($r['icon']) : 'dashicons-hammer'; ?>"></span>
                                            <strong><?php echo esc_html($r['name']); ?></strong>
                                        </td>
                                        <td style="text-align: center; color: #64748b;"><?php echo number_format(floatval($r['price']), 2); ?></td>
                                        <td style="text-align: center;">
                                            <input type="number" 
                                                   class="rbf-labour-input" 
                                                   data-repair-id="<?php echo esc_attr($r['id']); ?>" 
                                                   step="0.01" 
                                                   min="0" 
                                                   value="<?php echo number_format(floatval($r['labour_cost']), 2, '.', ''); ?>" 
                                                   style="width: 140px; text-align: right; font-weight: bold;">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 5: SUPPLIER PRICE SYNC & FEEDS -->
            <div class="rbf-tab-panel" id="tab-supplier-sync">
                <div class="rbf-panel-card">
                    <div class="rbf-card-header">
                        <div>
                            <h2>Supplier Price Synchronization Engine</h2>
                            <p>Automatic 48-Hour WP-Cron synchronization and structured B2B wholesale feed import. Supplier prices are cached locally and never block customer checkout.</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="button button-secondary" id="rbf-btn-toggle-feed-import">
                                <span class="dashicons dashicons-upload"></span> Import Wholesale CSV Feed
                            </button>
                            <button type="button" class="button button-primary" id="rbf-btn-manual-sync">
                                <span class="dashicons dashicons-update"></span> Sync Supplier Prices Now
                            </button>
                        </div>
                    </div>

                    <!-- Sync Status Cards -->
                    <div class="rbf-stats-bar" style="margin-bottom: 25px;">
<?php
$lxcell_provider = class_exists('RBF_Supplier_Manager') ? RBF_Supplier_Manager::get_instance()->get_provider('lxcell') : null;
$lxcell_status = $lxcell_provider ? $lxcell_provider->get_status() : array();
?>
                        <div class="rbf-stat-card highlight" style="min-width: 260px;">
                            <div class="rbf-stat-label">Supplier Feed Status</div>
                            <div style="margin-top: 8px; font-size: 13px; line-height: 1.6;">
                                <div><strong>Public catalog:</strong> <span style="color: #017c36;">Available</span></div>
                                <div><strong>Wholesale pricing:</strong> <span style="color: #b06000;">Not available publicly</span></div>
                                <div><strong>Wholesale feed:</strong> <span style="font-weight: bold; color: <?php echo ($lxcell_status['wholesale_price_available'] ?? false) ? '#017c36' : '#b06000'; ?>;"><?php echo esc_html($lxcell_status['wholesale_feed'] ?? 'Needs configuration'); ?></span></div>
                            </div>
                        </div>
                        <div class="rbf-stat-card">
                            <div class="rbf-stat-label">Scheduled Cron</div>
                            <div class="rbf-stat-number" style="font-size: 16px;">Every 48 Hours</div>
                            <div class="rbf-stat-sub">Next: <?php echo $next_sync ? date('M j, Y H:i', $next_sync) : 'Scheduled on init'; ?></div>
                        </div>
                        <div class="rbf-stat-card">
                            <div class="rbf-stat-label">Last Sync Run</div>
                            <div class="rbf-stat-number" style="font-size: 16px;"><?php echo !empty($last_sync['time']) ? date('M j, H:i', strtotime($last_sync['time'])) : 'None Yet'; ?></div>
                            <div class="rbf-stat-sub">Duration: <?php echo !empty($last_sync['duration']) ? $last_sync['duration'] . 's' : '-'; ?></div>
                        </div>
                        <div class="rbf-stat-card">
                            <div class="rbf-stat-label">Prices Changed</div>
                            <div class="rbf-stat-number" style="color: #017c36;"><?php echo !empty($last_sync['stats']['prices_changed']) ? $last_sync['stats']['prices_changed'] : 0; ?></div>
                            <div class="rbf-stat-sub">Unchanged: <?php echo !empty($last_sync['stats']['unchanged']) ? $last_sync['stats']['unchanged'] : 0; ?></div>
                        </div>
                        <div class="rbf-stat-card">
                            <div class="rbf-stat-label">Needs Review</div>
                            <div class="rbf-stat-number" style="color: #b06000;"><?php echo !empty($last_sync['stats']['needs_review']) ? $last_sync['stats']['needs_review'] : 0; ?></div>
                            <div class="rbf-stat-sub">Uncertain Mappings</div>
                        </div>
                    </div>

                    <!-- Collapsible Feed Ingestion Box -->
                    <div id="rbf-feed-import-box" style="display: none; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                        <h3 style="margin-top: 0; color: #1e293b;"><span class="dashicons dashicons-media-spreadsheet"></span> Ingest Supplier Wholesale Price Sheet (CSV / XLSX)</h3>
                        <p style="color: #64748b;">Upload an Excel (.xlsx) file or paste raw CSV/TSV data from your supplier price sheet. Columns recognized: <code>SKU / Part Code</code>, <code>Product Name</code>, <code>Model</code>, <code>Part Type</code>, <code>Price AED</code>, <code>Stock</code>.</p>
                        <div style="margin-bottom: 15px; padding: 12px; background: #ffffff; border: 1px dashed #cbd5e1; border-radius: 6px;">
                            <label style="font-weight: 600; display: block; margin-bottom: 6px;">Upload XLSX / CSV File:</label>
                            <input type="file" id="rbf-feed-file-input" accept=".xlsx,.csv" style="font-size: 13px;">
                        </div>
                        <label style="font-weight: 600; display: block; margin-bottom: 6px;">Or Paste CSV / TSV Text Content:</label>
                        <textarea id="rbf-csv-feed-content" rows="5" class="widefat" placeholder="SKU,Product Name,Model,Part Type,Price AED,Stock&#10;LX-S23U-SCR,Galaxy S23 Ultra Service Pack,Galaxy S23 Ultra,Screen Replacement,300.00,in_stock&#10;LX-IP15-BAT,iPhone 15 OEM Battery,iPhone 15,Battery Replacement,95.00,in_stock" style="font-family: monospace; font-size: 13px;"></textarea>
                        <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="button button-secondary" id="rbf-btn-cancel-feed">Cancel</button>
                            <button type="button" class="button button-primary" id="rbf-btn-process-feed">
                                <span class="dashicons dashicons-yes"></span> Parse & Update Supplier Prices
                            </button>
                        </div>
                    </div>

                    <!-- Sync Logs History -->
                    <h3 style="margin-bottom: 15px; color: #1e293b;"><span class="dashicons dashicons-list-view"></span> Synchronization Audit Log</h3>
                    <div class="rbf-table-responsive">
                        <table class="rbf-pricing-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Supplier</th>
                                    <th>Sync Started</th>
                                    <th>Duration</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: center;">Checked</th>
                                    <th style="text-align: center;">Changed</th>
                                    <th style="text-align: center;">Unchanged</th>
                                    <th style="text-align: center;">Needs Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sync_logs)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 25px; color: #64748b;">
                                            No sync runs recorded yet. Click "Sync Supplier Prices Now" or import a feed to trigger the initial synchronization.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($sync_logs as $log): ?>
                                        <tr>
                                            <td style="color: #888;"><?php echo esc_html($log['id']); ?></td>
                                            <td><strong><?php echo esc_html(strtoupper($log['supplier_id'])); ?></strong></td>
                                            <td><?php echo esc_html($log['sync_start']); ?></td>
                                            <td><?php echo esc_html($log['duration_seconds']); ?>s</td>
                                            <td style="text-align: center;">
                                                <span class="rbf-badge-active" style="<?php echo $log['status'] === 'failed' ? 'background: #fce8e6; color: #c5221f;' : ($log['status'] === 'running' ? 'background: #fef7e0; color: #b06000;' : ''); ?>">
                                                    <?php echo esc_html(strtoupper($log['status'])); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;"><?php echo intval($log['products_checked']); ?></td>
                                            <td style="text-align: center; color: #017c36; font-weight: bold;"><?php echo intval($log['prices_changed']); ?></td>
                                            <td style="text-align: center; color: #64748b;"><?php echo intval($log['unchanged']); ?></td>
                                            <td style="text-align: center; color: #b06000;"><?php echo intval($log['needs_review']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 6: SUPPLIER MAPPINGS -->
            <div class="rbf-tab-panel" id="tab-supplier-mappings">
                <div class="rbf-panel-card">
                    <div class="rbf-card-header">
                        <div>
                            <h2>Supplier Product Mappings</h2>
                            <p>Manage associations between supplier SKUs and our local device models and repair types. High-confidence mappings feed live wholesale costs into the pricing engine.</p>
                        </div>
                    </div>

                    <!-- Add Mapping Box -->
                    <div class="rbf-add-override-box" style="margin-bottom: 25px;">
                        <h3>Add or Update Supplier SKU Mapping</h3>
                        <div class="rbf-override-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600;">Supplier:</label>
                                <select id="rbf-map-supplier" class="widefat">
                                    <option value="lxcell">LXCELL (Dubai)</option>
                                    <option value="katel">Katel / Servicepack.ae</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600;">Device Model:</label>
                                <select id="rbf-map-model" class="widefat">
                                    <option value="">-- Select Model --</option>
                                    <?php foreach ($models as $m): ?>
                                        <option value="<?php echo esc_attr($m['id']); ?>"><?php echo esc_html($m['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600;">Repair Service:</label>
                                <select id="rbf-map-repair" class="widefat">
                                    <option value="">-- Select Repair --</option>
                                    <?php foreach ($repairs as $r): ?>
                                        <option value="<?php echo esc_attr($r['id']); ?>"><?php echo esc_html($r['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:5px; font-weight:600;">Supplier SKU / MPN:</label>
                                <input type="text" id="rbf-map-sku" placeholder="e.g. LX-IP15-INCELL" class="widefat">
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="button" class="button button-primary widefat" id="rbf-btn-save-mapping">
                                    <span class="dashicons dashicons-plus-alt2"></span> Save Mapping
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mappings Table -->
                    <div class="rbf-table-responsive">
                        <table class="rbf-pricing-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Supplier</th>
                                    <th>Device Model</th>
                                    <th>Repair Type</th>
                                    <th>Supplier SKU</th>
                                    <th style="text-align: center;">Wholesale Cost</th>
                                    <th style="text-align: center;">Stock</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="width: 80px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mappings)): ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 25px; color: #64748b;">
                                            No active supplier mappings found. Use the form above or import a feed to create mappings.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($mappings as $idx => $map): ?>
                                        <tr>
                                            <td style="color: #888; text-align: center;"><?php echo ($idx + 1); ?></td>
                                            <td><strong><?php echo esc_html(strtoupper($map['supplier_id'])); ?></strong></td>
                                            <td><?php echo esc_html($map['model_name'] ?: 'Model #' . $map['model_id']); ?></td>
                                            <td><?php echo esc_html($map['repair_name'] ?: 'Repair #' . $map['repair_id']); ?></td>
                                            <td><code><?php echo esc_html($map['supplier_sku']); ?></code></td>
                                            <td style="text-align: center; font-weight: bold; color: #017c36;">
                                                <?php echo !empty($map['current_price']) ? esc_html($currency) . ' ' . number_format(floatval($map['current_price']), 2) : '-'; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="rbf-badge-active" style="<?php echo ($map['stock_status'] ?? '') === 'out_of_stock' ? 'background: #fce8e6; color: #c5221f;' : ''; ?>">
                                                    <?php echo esc_html(str_replace('_', ' ', $map['stock_status'] ?? 'in_stock')); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="rbf-source-badge <?php echo $map['match_status'] === 'verified' ? 'source-tier-base-price' : 'source-model-override'; ?>">
                                                    <?php echo esc_html($map['match_status']); ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <button type="button" class="button button-small button-link-delete rbf-btn-delete-mapping" data-id="<?php echo esc_attr($map['id']); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
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

            <!-- TAB 7: CASCADING PRICE INSPECTOR -->
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
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 2px;
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
            flex-shrink: 0;
            white-space: nowrap;
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
        .rbf-pricing-table th:first-child,
        .rbf-pricing-table td:first-child {
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 3;
        }
        .rbf-pricing-table th:nth-child(2),
        .rbf-pricing-table td:nth-child(2) {
            position: sticky;
            left: 50px;
            background: #fff;
            z-index: 3;
            box-shadow: 3px 0 5px -2px rgba(0, 0, 0, 0.08);
        }
        .rbf-pricing-table th:first-child,
        .rbf-pricing-table th:nth-child(2) {
            background: #f6f7f7;
            z-index: 5;
        }
        .rbf-pricing-table tr:hover td:first-child,
        .rbf-pricing-table tr:hover td:nth-child(2) {
            background-color: #f9fbf9;
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
    
    /**
     * Admin About Page
     */
    public function admin_about() {
        echo '<div class="wrap rbf-about-page">';
        echo '<div class="rbf-about-header" style="text-align: center; margin: 40px 0; padding: 40px; background: linear-gradient(135deg, #017c36 0%, #05413a 100%); color: white; border-radius: 20px;">';
        echo '<h1 style="color: white; margin: 0 0 20px 0; font-size: 36px;">🔧 Repair Booking Form</h1>';
        echo '<p style="font-size: 18px; margin: 0; opacity: 0.9;">Professional Mobile Device Repair Management System</p>';
        echo '<div style="margin-top: 20px;">';
        echo '<span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 20px; font-size: 14px;">Version 1.1</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="rbf-about-content" style="max-width: 1200px; margin: 0 auto;">';
        
        // Plugin Information Card
        echo '<div class="rbf-about-card" style="background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); margin-bottom: 30px;">';
        echo '<h2 style="color: #23282d; margin-top: 0; font-size: 24px;">📋 Plugin Information</h2>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">';
        echo '<strong style="color: #017c36;">👨‍💻 Author:</strong><br>Abid Ali';
        echo '</div>';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">';
        echo '<strong style="color: #017c36;">🎯 Purpose:</strong><br>Mobile Device Repair Business Management';
        echo '</div>';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">';
        echo '<strong style="color: #017c36;">🚀 Status:</strong><br><span style="color: #46b450; font-weight: 600;">✓ Production Ready</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Features Grid
        echo '<div class="rbf-about-card" style="background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); margin-bottom: 30px;">';
        echo '<h2 style="color: #23282d; margin-top: 0; font-size: 24px;">✨ Key Features</h2>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">';
        
        $features = array(
            array('🎨', 'Modern Multi-step Form', 'Beautiful, responsive booking form with step-by-step navigation'),
            array('📱', 'Multi-Brand Support', 'iPhone, Samsung, Google Pixel, OnePlus, and custom brands'),
            array('🔧', 'Repair Services', 'Comprehensive catalog of repair services with pricing'),
            array('🛒', 'Shopping Cart', 'Advanced cart system with VAT calculation and real-time updates'),
            array('💳', 'Payment Integration', 'PayPal and Stripe payment gateway support'),
            array('📊', 'Admin Dashboard', 'Complete management system for bookings, prices, and analytics'),
            array('📈', 'Analytics & Reports', 'Track earnings, bookings, and business performance'),
            array('⚡', 'Performance Optimized', 'Fast loading and efficient database management')
        );
        
        foreach ($features as $feature) {
            echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px; border-left: 4px solid #017c36;">';
            echo '<div style="font-size: 30px; margin-bottom: 10px; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">' . $feature[0] . '</div>';
            echo '<h3 style="margin: 0 0 8px 0; color: #23282d; font-size: 18px;">' . $feature[1] . '</h3>';
            echo '<p style="margin: 0; color: #666; font-size: 14px; line-height: 1.5;">' . $feature[2] . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // License Status with Deactivation Button
        echo '<div class="rbf-about-card" style="background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); margin-bottom: 30px;">';
        echo '<h2 style="color: #23282d; margin-top: 0; font-size: 24px;">🔐 License & Security</h2>';
        echo '<div style="background: #f0f8ff; padding: 25px; border-radius: 12px; border: 2px solid #017c36;">';
        echo '<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">';
        echo '<div class="rbf-status-icon" style="background: #46b450; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">✓</div>';
        echo '<div style="flex: 1;">';
        echo '<h3 style="margin: 0; color: #23282d;">License Status: <span style="color: #46b450;">ACTIVATED</span></h3>';
        echo '<p style="margin: 5px 0 0 0; color: #666;">Your license is automatically managed by the system</p>';
        echo '<p style="margin: 5px 0 0 0; color: #017c36; font-weight: 500;">Master Key: Abidali@254</p>';
        echo '</div>';
        echo '<div>';
        echo '<button type="button" id="deactivate-license" class="button button-secondary" style="background: #dc3545; border-color: #dc3545; color: white;">Deactivate License</button>';
        echo '<button type="button" id="reactivate-license" class="button button-primary" style="margin-left: 10px;">Reactivate License</button>';
        echo '</div>';
        echo '</div>';
        echo '<p style="margin: 0; color: #017c36; font-weight: 500;">🔒 Secure • Reliable • Professional</p>';
        echo '</div>';
        echo '</div>';
        
        // Technical Details
        echo '<div class="rbf-about-card" style="background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); margin-bottom: 30px;">';
        echo '<h2 style="color: #23282d; margin-top: 0; font-size: 24px;">⚙️ Technical Specifications</h2>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">';
        echo '<strong style="color: #017c36;">Database:</strong><br>MySQL with optimized queries';
        echo '</div>';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">';
        echo '<strong style="color: #017c36;">Frontend:</strong><br>HTML5, CSS3, JavaScript (jQuery)';
        echo '</div>';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">';
        echo '<strong style="color: #017c36;">Backend:</strong><br>PHP 7.4+, WordPress 5.0+';
        echo '</div>';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px;">';
        echo '<strong style="color: #017c36;">Security:</strong><br>Nonce verification, SQL injection protection';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Support & Contact
        echo '<div class="rbf-about-card" style="background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); margin-bottom: 30px;">';
        echo '<h2 style="color: #23282d; margin-top: 0; font-size: 24px;">📞 Support & Contact</h2>';
        echo '<div style="text-align: center; padding: 30px;">';
        echo '<p style="font-size: 18px; color: #666; margin-bottom: 25px;">Need help or have questions? Our support team is here to assist you.</p>';
        echo '<div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">';
        echo '<a href="mailto:abidmmp100@gmail.com" style="text-decoration: none; color: inherit;">';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px; min-width: 200px; transition: all 0.3s ease; cursor: pointer;">';
        echo '<div style="font-size: 30px; margin-bottom: 10px; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">📧</div>';
        echo '<strong>Email Support</strong><br>';
        echo '<small>abidmmp100@gmail.com</small>';
        echo '</div>';
        echo '</a>';
        echo '<a href="' . plugin_dir_url(__FILE__) . 'docs/documentation.html" target="_blank" style="text-decoration: none; color: inherit;">';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px; min-width: 200px; transition: all 0.3s ease; cursor: pointer;">';
        echo '<div style="font-size: 30px; margin-bottom: 10px; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">📚</div>';
        echo '<strong>Documentation</strong><br>';
        echo '<small>Complete user guide</small>';
        echo '</div>';
        echo '</a>';
        echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 12px; min-width: 200px;">';
        echo '<div style="font-size: 30px; margin-bottom: 10px; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">🔄</div>';
        echo '<strong>Updates</strong><br>';
        echo '<small>Regular feature updates</small>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>'; // Close rbf-about-content
        echo '</div>'; // Close wrap
        
        // Add CSS to fix icon sizing
        echo '<style>
        .rbf-about-page img[src*=".svg"], 
        .rbf-about-page img[src*=".png"], 
        .rbf-about-page img[src*=".jpg"],
        .rbf-about-page img[src*=".jpeg"],
        .rbf-about-page .emoji,
        .rbf-about-page img[src*="emoji"] {
            width: 30px !important;
            height: 30px !important;
            max-width: 30px !important;
            max-height: 30px !important;
        }
        
        .rbf-about-page .rbf-step-number {
            width: 40px !important;
            height: 40px !important;
            max-width: 40px !important;
            max-height: 40px !important;
        }
        
        .rbf-about-page .rbf-status-icon {
            width: 50px !important;
            height: 50px !important;
            max-width: 50px !important;
            max-height: 50px !important;
        }
        </style>';
        
        // Add JavaScript for license deactivation and reactivation
        echo '<script type="text/javascript">
        jQuery(document).ready(function($) {
            $("#deactivate-license").on("click", function() {
                if (confirm("Are you sure you want to deactivate the license? This will disable the plugin functionality.")) {
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "rbf_deactivate_license",
                            nonce: "' . wp_create_nonce('rbf_deactivate_license') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                alert("License deactivated successfully. Plugin will be disabled.");
                                location.reload();
                            } else {
                                alert("Error: " + response.data);
                            }
                        },
                        error: function() {
                            alert("Error occurred while deactivating license.");
                        }
                    });
                }
            });
            
            $("#reactivate-license").on("click", function() {
                if (confirm("Reactivate license with master key Abidali@254?")) {
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "rbf_reactivate_license",
                            nonce: "' . wp_create_nonce('rbf_reactivate_license') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                alert("License reactivated successfully!");
                                location.reload();
                            } else {
                                alert("Error: " + response.data);
                            }
                        },
                        error: function() {
                            alert("Error occurred while reactivating license.");
                        }
                    });
                }
            });
        });
        </script>';
    }

    /**
     * Handle license activation
     */
    public function handle_license_activation() {
        if (isset($_POST['activate_license']) && wp_verify_nonce($_POST['rbf_license_nonce'], 'rbf_license_nonce')) {
            $license_key = sanitize_text_field($_POST['license_key']);
            if (!empty($license_key)) {
                update_option('rbf_license_key', $license_key);
                echo '<div class="notice notice-success"><p>License activated successfully!</p></div>';
            }
        }
        
        // Auto-activate with master license key
        $current_license = get_option('rbf_license_key', '');
        if (empty($current_license)) {
            update_option('rbf_license_key', 'Abidali@254');
        }
    }
    
    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('rbf_deactivate_license', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Deactivate license
        delete_option('rbf_license_key');
        
        // Optionally disable plugin functionality
        update_option('rbf_plugin_disabled', true);
        
        wp_send_json_success('License deactivated successfully');
    }
    
    /**
     * Show notice when plugin is disabled due to license deactivation
     */
    public function show_license_disabled_notice() {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Repair Booking Form Plugin:</strong> Plugin has been disabled due to license deactivation. Please reactivate your license to continue using the plugin.</p>';
        echo '<p><a href="' . admin_url('admin.php?page=repair-booking-about') . '" class="button button-primary">Reactivate License</a></p>';
        echo '</div>';
    }
    
    /**
     * AJAX handler for license reactivation
     */
    public function ajax_reactivate_license() {
        check_ajax_referer('rbf_reactivate_license', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Reactivate license with master key
        update_option('rbf_license_key', 'Abidali@254');
        delete_option('rbf_plugin_disabled');
        
        wp_send_json_success('License reactivated successfully');
    }
    
    /**
     * AJAX handler for generating invoice PDF
     */
    public function ajax_generate_invoice() {
        check_ajax_referer('rbf_nonce', 'nonce');
        
        $raw_id = sanitize_text_field($_GET['booking_id'] ?? '');
        
        if (empty($raw_id)) {
            wp_die('Invalid booking ID');
        }
        
        // Get booking details (support both numeric ID and tracking string e.g. eFIX-910CF)
        global $wpdb;
        if (is_numeric($raw_id)) {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rbf_bookings WHERE id = %d",
                intval($raw_id)
            ));
        } else {
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rbf_bookings WHERE booking_id = %s",
                $raw_id
            ));
        }
        
        if (!$booking) {
            wp_die('Booking not found');
        }
        
        // Check if this is a print request
        $is_print = isset($_GET['print']) && $_GET['print'] == '1';
        
        // Generate and output PDF invoice
        $this->generate_invoice_pdf($booking, $is_print);
    }
    
    /**
     * Generate PDF invoice for a booking
     */
    private function generate_invoice_pdf($booking, $is_print = false) {
        if ($is_print) {
            // Set headers for print-friendly HTML
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        } else {
            // Set headers for PDF download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="invoice-' . $booking->booking_id . '.pdf"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        // Create simple HTML invoice (you can enhance this with a proper PDF library)
        $html = $this->get_invoice_html($booking, $is_print);
        
        // For now, output HTML that can be printed or converted to PDF
        // In production, you'd use a library like TCPDF, mPDF, or Dompdf
        echo $html;
        exit;
    }
    
    /**
     * Get HTML for invoice
     */
    private function get_invoice_html($booking, $is_print = false) {
        $business_name = get_option('rbf_business_name', 'eFix Repair Services');
        $business_address = get_option('rbf_business_address', 'Dubai, UAE');
        $business_phone = get_option('rbf_business_phone', '+971 50 123 4567');
        $business_email = get_option('rbf_business_email', 'info@efix.ae');
        $business_logo = get_option('rbf_business_logo', '');
        $vat_number = get_option('rbf_vat_number', 'VAT No: 123456789012345');
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice - ' . esc_attr($booking->booking_id) . '</title>
            <style>
                @page { size: A4 portrait; margin: 8mm 10mm; }
                * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; padding: 10px; font-size: 11.5px; color: #0f172a; line-height: 1.35; }
                .invoice-box { max-width: 650px; margin: 0 auto; page-break-inside: avoid !important; break-inside: avoid !important; }
                .invoice-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 2px solid #017c36; padding-bottom: 8px; }
                .header-left h1 { color: #017c36; margin: 0 0 2px; font-size: 18px; font-weight: 800; }
                .header-left p { margin: 1px 0; font-size: 10px; color: #64748b; }
                .header-right { text-align: right; }
                .header-right p { margin: 1px 0; font-size: 10px; color: #64748b; }
                .invoice-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
                .info-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; font-size: 11px; }
                .info-card h3 { margin: 0 0 6px; font-size: 11.5px; color: #017c36; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
                .info-card p { margin: 2px 0; }
                .services-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11px; }
                .services-table th, .services-table td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
                .services-table th { background: #017c36; color: white; font-size: 10.5px; }
                .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 10px; }
                .totals-box { width: 230px; background: #fafafa; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 10px; font-size: 11px; }
                .totals-box p { margin: 2px 0; display: flex; justify-content: space-between; }
                .total-main { font-weight: 800; color: #017c36; font-size: 12.5px; border-top: 1px dashed #017c36; padding-top: 3px; margin-top: 3px; }
                .footer { text-align: center; margin-top: 10px; padding-top: 6px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 10px; }
                .logo { max-height: 40px; margin-bottom: 4px; }
                ' . ($is_print ? '
                @media print {
                    body { margin: 0; padding: 0; }
                    .no-print { display: none !important; }
                    .invoice-box { page-break-inside: avoid !important; break-inside: avoid !important; }
                }' : '') . '
            </style>
        </head>
        <body>
            <div class="invoice-box">
                <div class="invoice-header">
                    <div class="header-left">
                        ' . ($business_logo ? '<img src="' . esc_url($business_logo) . '" alt="' . esc_attr($business_name) . '" class="logo">' : '') . '
                        <h1>' . esc_html($business_name) . '</h1>
                        <p>' . esc_html($business_address) . ' | Tel: ' . esc_html($business_phone) . '</p>
                    </div>
                    <div class="header-right">
                        <p><strong>INVOICE:</strong> #' . esc_html($booking->booking_id) . '</p>
                        <p><strong>Date:</strong> ' . date('F j, Y', strtotime($booking->created_at ?: 'now')) . '</p>
                        <p>' . esc_html($vat_number) . '</p>
                    </div>
                </div>
                
                <div class="invoice-grid">
                    <div class="info-card">
                        <h3>Customer Information</h3>
                        <p><strong>Name:</strong> ' . esc_html($booking->customer_name) . '</p>
                        <p><strong>Phone:</strong> ' . esc_html($booking->customer_phone) . '</p>
                        ' . ($booking->customer_email ? '<p><strong>Email:</strong> ' . esc_html($booking->customer_email) . '</p>' : '') . '
                        ' . ($booking->address ? '<p><strong>Address:</strong> ' . esc_html($booking->address) . '</p>' : '') . '
                    </div>
                    <div class="info-card">
                        <h3>Service & Device Details</h3>
                        <p><strong>Device:</strong> ' . esc_html($booking->brand . ' ' . $booking->model) . '</p>
                        <p><strong>Service:</strong> ' . esc_html($booking->service_type ?: 'Service Center') . '</p>
                        <p><strong>Status:</strong> <span style="color: #017c36; font-weight: bold;">Confirmed</span></p>
                        <p><strong>Payment:</strong> Pay After Repair</p>
                    </div>
                </div>
                
                <table class="services-table">
                    <thead>
                        <tr>
                            <th>Service Description</th>
                            <th style="width: 110px; text-align: center;">Duration</th>
                            <th style="width: 110px; text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>' . esc_html($booking->repair ?: 'Device Repair Services') . '</strong></td>
                            <td style="text-align: center; color: #64748b;">01-02 Hours</td>
                            <td style="text-align: right; font-weight: 700; color: #017c36;">AED ' . number_format($booking->subtotal, 2) . '</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="totals-wrap">
                    <div class="totals-box">
                        <p><span>Subtotal:</span><span>AED ' . number_format($booking->subtotal, 2) . '</span></p>
                        <p><span>VAT (5%):</span><span>AED ' . number_format($booking->vat_amount, 2) . '</span></p>
                        <p class="total-main"><span>Total Amount:</span><span>AED ' . number_format($booking->total_amount, 2) . '</span></p>
                    </div>
                </div>
                
                <div class="footer">
                    <p style="margin: 2px 0;"><strong>Thank you for choosing ' . esc_html($business_name) . '!</strong></p>
                    <p style="margin: 2px 0;">For queries contact ' . esc_html($business_phone) . ' | ' . esc_html($business_email) . '</p>
                </div>
                
                ' . ($is_print ? '
                <div class="no-print" style="text-align: center; margin-top: 15px; padding: 10px; border-top: 1px solid #e2e8f0;">
                    <button onclick="window.print()" style="background: #017c36; color: white; border: none; padding: 8px 18px; border-radius: 5px; font-size: 13px; font-weight: 700; cursor: pointer;">
                        🖨️ Print Invoice
                    </button>
                </div>' : '') . '
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * AJAX handler for deleting a booking
     */
    public function ajax_delete_booking() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error('Invalid booking ID');
        }
        
        // Delete the booking
        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'rbf_bookings',
            array('id' => $booking_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to delete booking');
        }
        
        wp_send_json_success('Booking deleted successfully');
    }
    
    /**
     * AJAX handler for updating database structure
     */
    public function ajax_update_database_structure() {
        check_ajax_referer('rbf_admin_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        try {
            // Update the database structure
            $this->update_repairs_table_structure();
            wp_send_json_success('Database structure updated successfully');
        } catch (Exception $e) {
            wp_send_json_error('Error updating database: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for fixing database issues
     */
    public function ajax_fix_database() {
        check_ajax_referer('rbf_prices_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        try {
            global $wpdb;
            
            // Step 1: Remove foreign key constraints
            $repairs_table = $wpdb->prefix . 'rbf_repairs';
            $foreign_keys = $wpdb->get_results("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '$repairs_table' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            foreach ($foreign_keys as $fk) {
                $wpdb->query("ALTER TABLE $repairs_table DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
            }
            
            // Step 2: Ensure we have brands and models
            $brands_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_brands");
            if (!$brands_count) {
                $wpdb->insert(
                    $wpdb->prefix . 'rbf_brands',
                    array(
                        'name' => 'Default Brand',
                        'image_url' => 'Brands/default.png',
                        'status' => 'active'
                    ),
                    array('%s', '%s', '%s')
                );
            }
            
            $models_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_models");
            if (!$models_count) {
                $first_brand_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}rbf_brands LIMIT 1");
                $wpdb->insert(
                    $wpdb->prefix . 'rbf_models',
                    array(
                        'brand_id' => $first_brand_id,
                        'name' => 'Default Model',
                        'image_url' => 'Models/default.png',
                        'status' => 'active'
                    ),
                    array('%d', '%s', '%s', '%s')
                );
            }
            
            // Step 3: Update repairs table structure
            $this->update_repairs_table_structure();
            
            // Step 4: Populate repairs if empty
            $repairs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_repairs");
            if (!$repairs_count) {
                $this->populate_repairs_table();
            }
            
            // Step 5: Ensure pricing structure exists
            $this->ensure_pricing_database_structure();
            
            // Step 6: Force recreate all pricing combinations
            $this->populate_pricing_table();
            
            wp_send_json_success('Database issues fixed successfully');
        } catch (Exception $e) {
            wp_send_json_error('Error fixing database: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for regenerating all pricing combinations
     */
    public function ajax_regenerate_combinations() {
        check_ajax_referer('rbf_prices_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        try {
            global $wpdb;
            
            // Get current counts
            $models_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_models WHERE status = 'active'");
            $repairs_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_repairs WHERE status = 'active'");
            $expected_combinations = $models_count * $repairs_count;
            
            // Regenerate all combinations
            $this->populate_pricing_table();
            
            // Get actual count after regeneration
            $actual_combinations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_pricing");
            
            $message = "Successfully regenerated pricing combinations. ";
            $message .= "Created $actual_combinations combinations for $models_count models and $repairs_count repairs.";
            
            if ($actual_combinations < $expected_combinations) {
                $message .= " Note: Some combinations may be missing due to missing default prices.";
            }
            
            wp_send_json_success($message);
        } catch (Exception $e) {
            wp_send_json_error('Error regenerating combinations: ' . $e->getMessage());
        }
    }
}

// Initialize the plugin
new RepairBookingForm();

// No need for separate admin brands page - using main plugin's admin_brands() method
