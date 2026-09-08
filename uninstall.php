<?php
/**
 * Uninstall Repair Booking Form Plugin
 * 
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to uninstall
if (!current_user_can('activate_plugins')) {
    return;
}

// Get WordPress database object
global $wpdb;

// Define table names
$brands_table = $wpdb->prefix . 'rbf_brands';
$models_table = $wpdb->prefix . 'rbf_models';
$repairs_table = $wpdb->prefix . 'rbf_repairs';
$bookings_table = $wpdb->prefix . 'rbf_bookings';

// Drop tables
$wpdb->query("DROP TABLE IF EXISTS $brands_table");
$wpdb->query("DROP TABLE IF EXISTS $models_table");
$wpdb->query("DROP TABLE IF EXISTS $repairs_table");
$wpdb->query("DROP TABLE IF EXISTS $bookings_table");

// Delete plugin options
delete_option('rbf_business_address');
delete_option('rbf_business_phone');
delete_option('rbf_business_email');
delete_option('rbf_business_logo');
delete_option('rbf_vat_number');

// Clear any cached data
wp_cache_flush();

// Log uninstall for debugging (optional)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Repair Booking Form plugin uninstalled - all data removed');
}
