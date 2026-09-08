<?php
/**
 * Simple Error Logger for Repair Booking Form Plugin
 * Include this file in your main plugin to log errors
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RBF_Error_Logger {
    
    private static $log_file;
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/rbf-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        self::$log_file = $log_dir . '/debug-errors.log';
    }
    
    /**
     * Log an error message
     */
    public static function log($message, $type = 'ERROR', $context = '') {
        $instance = self::get_instance();
        $instance->write_log($message, $type, $context);
    }
    
    /**
     * Log an error
     */
    public static function error($message, $context = '') {
        self::log($message, 'ERROR', $context);
    }
    
    /**
     * Log a warning
     */
    public static function warning($message, $context = '') {
        self::log($message, 'WARNING', $context);
    }
    
    /**
     * Log info
     */
    public static function info($message, $context = '') {
        self::log($message, 'INFO', $context);
    }
    
    /**
     * Log debug info
     */
    public static function debug($message, $context = '') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::log($message, 'DEBUG', $context);
        }
    }
    
    /**
     * Write to log file
     */
    private function write_log($message, $type, $context) {
        $timestamp = current_time('Y-m-d H:i:s');
        $context_str = $context ? " [$context]" : '';
        $log_entry = "[$timestamp] [$type]$context_str $message" . PHP_EOL;
        
        // Write to log file
        if (self::$log_file && is_writable(dirname(self::$log_file))) {
            file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
        
        // Also log to WordPress error log
        if (function_exists('error_log')) {
            error_log("RBF [$type]$context_str: $message");
        }
        
        // Display error in admin if needed
        if (is_admin() && $type === 'ERROR') {
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-error"><p><strong>RBF Error:</strong> ' . esc_html($message) . '</p></div>';
            });
        }
    }
    
    /**
     * Get log file path
     */
    public static function get_log_file_path() {
        $instance = self::get_instance();
        return self::$log_file;
    }
    
    /**
     * Clear log file
     */
    public static function clear_log() {
        $instance = self::get_instance();
        if (self::$log_file && file_exists(self::$log_file)) {
            unlink(self::$log_file);
        }
    }
    
    /**
     * Get log contents
     */
    public static function get_log_contents($lines = 100) {
        $instance = self::get_instance();
        if (self::$log_file && file_exists(self::$log_file)) {
            $content = file_get_contents(self::$log_file);
            $lines_array = explode(PHP_EOL, $content);
            $lines_array = array_filter($lines_array); // Remove empty lines
            
            if (count($lines_array) > $lines) {
                $lines_array = array_slice($lines_array, -$lines);
            }
            
            return implode(PHP_EOL, $lines_array);
        }
        return '';
    }
}

// Global function for easy access
if (!function_exists('rbf_log')) {
    function rbf_log($message, $type = 'ERROR', $context = '') {
        RBF_Error_Logger::log($message, $type, $context);
    }
}

if (!function_exists('rbf_log_error')) {
    function rbf_log_error($message, $context = '') {
        RBF_Error_Logger::error($message, $context);
    }
}

if (!function_exists('rbf_log_warning')) {
    function rbf_log_warning($message, $context = '') {
        RBF_Error_Logger::warning($message, $context);
    }
}

if (!function_exists('rbf_log_info')) {
    function rbf_log_info($message, $context = '') {
        RBF_Error_Logger::info($message, $context);
    }
}

if (!function_exists('rbf_log_debug')) {
    function rbf_log_debug($message, $context = '') {
        RBF_Error_Logger::debug($message, $context);
    }
}
?>
