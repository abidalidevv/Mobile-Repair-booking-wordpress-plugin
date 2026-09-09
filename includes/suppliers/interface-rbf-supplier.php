<?php
/**
 * Supplier Provider Interface for EFIX Repair Booking Form
 *
 * Defines the contract for all mobile spare parts supplier adapters.
 * Ensures the pricing engine and sync manager remain completely decoupled
 * from supplier-specific APIs, feed formats, or website structures.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface RBF_Supplier_Interface {

    /**
     * Get unique supplier identifier slug (e.g. 'lxcell', 'katel')
     *
     * @return string
     */
    public function get_id();

    /**
     * Get human-readable supplier display name
     *
     * @return string
     */
    public function get_name();

    /**
     * Get primary trading currency code (e.g. 'AED')
     *
     * @return string
     */
    public function get_currency();

    /**
     * Get current status/health of the supplier provider
     *
     * @return array [
     *   'status'  => 'active'|'configured'|'needs_configuration'|'error',
     *   'message' => string
     * ]
     */
    public function get_status();

    /**
     * Fetch normalized products from supplier (via remote endpoint, catalog, or public discovery)
     *
     * @param array $options Optional fetch filters or limits
     * @return array Array of normalized product arrays
     */
    public function fetch_products($options = array());

    /**
     * Parse structured feed data (CSV, XLSX, or JSON) into normalized product arrays
     *
     * @param string $raw_content File content or raw data string
     * @param string $format 'csv' | 'json' | 'tsv'
     * @return array Array of normalized product arrays
     */
    public function parse_feed_data($raw_content, $format = 'csv');
}
