<?php
/**
 * Multi-Currency Management Class for EFIX Repair Booking Form
 *
 * Handles live exchange rates, currency switching, formatting, and caching.
 * Base currency is AED (United Arab Emirates Dirham).
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Currency {

    private static $instance = null;
    
    // Default base currency is AED
    const BASE_CURRENCY = 'AED';
    
    // Transient key for caching exchange rates
    const TRANSIENT_KEY = 'rbf_exchange_rates_v2';
    const CACHE_DURATION = 86400; // 24 hours in seconds

    // Fallback default exchange rates (1 AED = X)
    private $default_rates = [
        'AED' => 1.0,
        'SAR' => 1.021,
        'USD' => 0.2723,
        'EUR' => 0.2510,
        'GBP' => 0.2150,
        'PKR' => 76.00,
        'KWD' => 0.0837,
        'QAR' => 0.992
    ];

    private $currency_meta = [
        'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED', 'symbol_native' => 'د.إ', 'decimals' => 2, 'position' => 'before'],
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'symbol_native' => '﷼', 'decimals' => 2, 'position' => 'before'],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'symbol_native' => '$', 'decimals' => 2, 'position' => 'before'],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'symbol_native' => '€', 'decimals' => 2, 'position' => 'before'],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'symbol_native' => '£', 'decimals' => 2, 'position' => 'before'],
        'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => 'PKR', 'symbol_native' => '₨', 'decimals' => 0, 'position' => 'before'],
        'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'KWD', 'symbol_native' => 'د.ك', 'decimals' => 3, 'position' => 'before'],
        'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'QAR', 'symbol_native' => 'ر.ق', 'decimals' => 2, 'position' => 'before']
    ];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // AJAX hook for fetching/refreshing rates
        add_action('wp_ajax_rbf_get_currency_rates', [$this, 'ajax_get_currency_rates']);
        add_action('wp_ajax_nopriv_rbf_get_currency_rates', [$this, 'ajax_get_currency_rates']);
        add_action('wp_ajax_rbf_refresh_exchange_rates', [$this, 'ajax_refresh_exchange_rates']);
    }

    /**
     * Get default shop currency set in admin settings
     */
    public function get_default_currency() {
        return get_option('rbf_default_currency', 'AED');
    }

    /**
     * Get enabled currencies list for frontend selection
     */
    public function get_enabled_currencies() {
        $enabled = get_option('rbf_enabled_currencies', ['AED', 'SAR', 'USD']);
        if (!is_array($enabled) || empty($enabled)) {
            $enabled = ['AED', 'SAR', 'USD'];
        }
        return $enabled;
    }

    /**
     * Fetch exchange rates from API or cache
     */
    public function get_exchange_rates($force_refresh = false) {
        if (!$force_refresh) {
            $cached_rates = get_transient(self::TRANSIENT_KEY);
            if ($cached_rates !== false && is_array($cached_rates)) {
                return $this->merge_with_custom_rates($cached_rates);
            }
        }

        // Try live exchange rate API
        $live_rates = $this->fetch_live_rates();
        if ($live_rates && is_array($live_rates)) {
            set_transient(self::TRANSIENT_KEY, $live_rates, self::CACHE_DURATION);
            update_option('rbf_exchange_rates_last_updated', current_time('mysql'));
            return $this->merge_with_custom_rates($live_rates);
        }

        // Fallback to custom admin rates or default static rates
        return $this->merge_with_custom_rates($this->default_rates);
    }

    /**
     * Fetch live rates using Open Exchange Rates Free API endpoint
     */
    private function fetch_live_rates() {
        $api_url = 'https://open.er-api.com/v6/latest/AED';
        
        $response = wp_remote_get($api_url, [
            'timeout' => 8,
            'sslverify' => true,
            'headers' => ['Accept' => 'application/json']
        ]);

        if (is_wp_error($response)) {
            error_log('RBF Currency: Failed to fetch live rates: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['result']) && $data['result'] === 'success' && isset($data['rates'])) {
            $rates = ['AED' => 1.0];
            foreach ($this->currency_meta as $code => $meta) {
                if (isset($data['rates'][$code])) {
                    $rates[$code] = floatval($data['rates'][$code]);
                } elseif (isset($this->default_rates[$code])) {
                    $rates[$code] = $this->default_rates[$code];
                }
            }
            return $rates;
        }

        return false;
    }

    /**
     * Merge rates with any manual custom override configured by admin
     */
    private function merge_with_custom_rates($rates) {
        $custom_overrides = get_option('rbf_custom_exchange_rates', []);
        if (is_array($custom_overrides) && !empty($custom_overrides)) {
            foreach ($custom_overrides as $currency => $custom_rate) {
                if (floatval($custom_rate) > 0) {
                    $rates[$currency] = floatval($custom_rate);
                }
            }
        }
        return $rates;
    }

    /**
     * Convert an AED base price to target currency
     */
    public function convert($amount, $target_currency = 'AED') {
        $amount = floatval($amount);
        if ($target_currency === 'AED' || empty($target_currency)) {
            return $amount;
        }

        $rates = $this->get_exchange_rates();
        $rate = isset($rates[$target_currency]) ? floatval($rates[$target_currency]) : 1.0;

        return $amount * $rate;
    }

    /**
     * Format a price string according to currency
     */
    public function format($amount_in_aed, $target_currency = null) {
        if ($target_currency === null) {
            $target_currency = $this->get_default_currency();
        }

        $converted = $this->convert($amount_in_aed, $target_currency);
        $meta = $this->currency_meta[$target_currency] ?? $this->currency_meta['AED'];
        $decimals = $meta['decimals'] ?? 2;
        $symbol = $meta['symbol'] ?? $target_currency;

        $formatted_number = number_format($converted, $decimals, '.', ',');
        
        if (isset($meta['position']) && $meta['position'] === 'after') {
            return $formatted_number . ' ' . $symbol;
        }
        return $symbol . ' ' . $formatted_number;
    }

    /**
     * Get list of all available currency options with metadata
     */
    public function get_currencies_data() {
        $rates = $this->get_exchange_rates();
        $enabled = $this->get_enabled_currencies();
        $default = $this->get_default_currency();

        $data = [];
        foreach ($this->currency_meta as $code => $meta) {
            $data[$code] = array_merge($meta, [
                'code' => $code,
                'rate' => $rates[$code] ?? 1.0,
                'is_enabled' => in_array($code, $enabled),
                'is_default' => ($code === $default)
            ]);
        }
        return $data;
    }

    /**
     * AJAX endpoint to get rates and currency list
     */
    public function ajax_get_currency_rates() {
        wp_send_json_success([
            'base_currency' => self::BASE_CURRENCY,
            'default_currency' => $this->get_default_currency(),
            'currencies' => $this->get_currencies_data(),
            'rates' => $this->get_exchange_rates(),
            'last_updated' => get_option('rbf_exchange_rates_last_updated', 'Auto-detected')
        ]);
    }

    /**
     * AJAX endpoint for Admin to force refresh rates
     */
    public function ajax_refresh_exchange_rates() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        delete_transient(self::TRANSIENT_KEY);
        $rates = $this->get_exchange_rates(true);

        wp_send_json_success([
            'message' => 'Exchange rates refreshed successfully!',
            'rates' => $rates,
            'last_updated' => get_option('rbf_exchange_rates_last_updated', current_time('mysql'))
        ]);
    }
}
