<?php
/**
 * Debug Plugin Issues
 * Place this file in your WordPress root directory and access it via browser
 */

// Include WordPress
require_once('wp-config.php');

// Check if plugin class exists
if (class_exists('RepairBookingForm')) {
    echo "✅ Plugin class exists<br>";
    
    // Try to instantiate
    try {
        $plugin = new RepairBookingForm();
        echo "✅ Plugin instantiated successfully<br>";
        
        // Check if shortcode is registered
        global $shortcode_tags;
        if (isset($shortcode_tags['repair_booking_form'])) {
            echo "✅ Shortcode registered successfully<br>";
        } else {
            echo "❌ Shortcode not registered<br>";
        }
        
        // Check if AJAX actions are registered
        global $wp_filter;
        if (isset($wp_filter['wp_ajax_rbf_get_models'])) {
            echo "✅ AJAX get_models action registered<br>";
        } else {
            echo "❌ AJAX get_models action not registered<br>";
        }
        
        if (isset($wp_filter['wp_ajax_rbf_get_repairs'])) {
            echo "✅ AJAX get_repairs action registered<br>";
        } else {
            echo "❌ AJAX get_repairs action not registered<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error instantiating plugin: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Plugin class does not exist<br>";
    
    // Check if file exists
    $plugin_file = __DIR__ . '/wp-content/plugins/repair-booking-form/repair-booking-form.php';
    if (file_exists($plugin_file)) {
        echo "✅ Plugin file exists<br>";
        
        // Check for syntax errors
        $content = file_get_contents($plugin_file);
        if (strpos($content, 'class RepairBookingForm') !== false) {
            echo "✅ Plugin class definition found in file<br>";
        } else {
            echo "❌ Plugin class definition not found in file<br>";
        }
        
        // Check for missing closing brace
        $brace_count = substr_count($content, '{') - substr_count($content, '}');
        if ($brace_count === 0) {
            echo "✅ Braces are balanced<br>";
        } else {
            echo "❌ Braces are not balanced. Difference: $brace_count<br>";
        }
        
    } else {
        echo "❌ Plugin file not found at: $plugin_file<br>";
    }
}

// Check WordPress version
echo "<br><strong>WordPress Version:</strong> " . get_bloginfo('version') . "<br>";

// Check PHP version
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";

// Check if plugin is active
if (is_plugin_active('repair-booking-form/repair-booking-form.php')) {
    echo "<strong>Plugin Status:</strong> ✅ Active<br>";
} else {
    echo "<strong>Plugin Status:</strong> ❌ Not Active<br>";
}

// Check for any PHP errors
if (function_exists('error_get_last')) {
    $last_error = error_get_last();
    if ($last_error) {
        echo "<br><strong>Last PHP Error:</strong><br>";
        echo "Type: " . $last_error['type'] . "<br>";
        echo "Message: " . $last_error['message'] . "<br>";
        echo "File: " . $last_error['file'] . "<br>";
        echo "Line: " . $last_error['line'] . "<br>";
    }
}

// Check if brands manager class exists
if (class_exists('RBF_Brands_Models_Manager')) {
    echo "<br>✅ Brands Manager class exists<br>";
} else {
    echo "<br>❌ Brands Manager class does not exist<br>";
}

// Check if JSON data file exists
$json_file = __DIR__ . '/wp-content/plugins/repair-booking-form/brands_models_data.json';
if (file_exists($json_file)) {
    echo "✅ JSON data file exists<br>";
    
    // Check if JSON is valid
    $json_content = file_get_contents($json_file);
    $json_data = json_decode($json_content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON data is valid<br>";
        echo "Brands count: " . count($json_data['brands']) . "<br>";
        echo "Repair services count: " . count($json_data['repair_services']) . "<br>";
    } else {
        echo "❌ JSON data is invalid: " . json_last_error_msg() . "<br>";
    }
} else {
    echo "❌ JSON data file not found<br>";
}

echo "<br><strong>Debug complete!</strong>";
?>
