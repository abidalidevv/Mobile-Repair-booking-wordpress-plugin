<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get today's date
$today = date('Y-m-d');
$today_start = $today . ' 00:00:00';
$today_end = $today . ' 23:59:59';

// Ensure bookings table exists
if (class_exists('RepairBookingForm')) {
    $rbf_plugin = new RepairBookingForm();
    $rbf_plugin->ensure_bookings_table_exists();
}

// Handle form submissions
if (isset($_POST['create_bookings_table']) && check_admin_referer('rbf_create_bookings_table')) {
    $rbf_plugin->ensure_bookings_table_exists();
    echo '<div class="notice notice-success"><p>Bookings table checked/created successfully!</p></div>';
}

if (isset($_POST['test_booking']) && check_admin_referer('rbf_test_booking')) {
    // Create test booking via AJAX
    $test_result = $rbf_plugin->ajax_test_booking();
    if ($test_result) {
        echo '<div class="notice notice-success"><p>Test booking created successfully!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Test booking failed!</p></div>';
    }
}

if (isset($_POST['check_database']) && check_admin_referer('rbf_check_database')) {
    // Check database status
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'rbf_bookings';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
    
    if ($table_exists) {
        $table_structure = $wpdb->get_results("DESCRIBE $bookings_table");
        $expected_columns = ['id', 'customer_name', 'customer_phone', 'customer_email', 'brand', 'model', 'repair', 'service_type', 'service_date', 'service_time', 'address', 'street_building', 'city', 'emirate', 'notes', 'subtotal', 'vat_amount', 'total_amount', 'status', 'booking_id', 'created_at'];
        $actual_columns = array_column($table_structure, 'Field');
        $missing_columns = array_diff($expected_columns, $actual_columns);
        
        if (empty($missing_columns)) {
            $bookings_count = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
            echo '<div class="notice notice-success"><p>✅ Database table exists with correct structure. Current bookings count: ' . $bookings_count . '</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p>⚠️ Table exists but missing columns: ' . implode(', ', $missing_columns) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>❌ Bookings table does not exist!</p></div>';
    }
}

// Get brands and models count from JSON data
if (class_exists('RBF_Brands_Models_Manager')) {
    $brands_manager = new RBF_Brands_Models_Manager();
    $brands = $brands_manager->get_brands();
    $repair_services = $brands_manager->get_repair_services();
    
    $brands_count = count($brands);
    $models_count = array_sum(array_map(function($brand) { return count($brand['models']); }, $brands));
    $repairs_count = count($repair_services);
} else {
    $brands_count = 0;
    $models_count = 0;
    $repairs_count = 0;
}

// Get today's bookings count
global $wpdb;

// Check if table exists and has data
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}rbf_bookings'");
if (!$table_exists) {
    $today_bookings = 0;
    $today_earnings = 0;
    $today_vat = 0;
    $recent_bookings = array();
    $bookings_count = 0;
    $pending_bookings = 0;
    $confirmed_bookings = 0;
    $completed_bookings = 0;
    $cancelled_bookings = 0;
} else {
    $today_bookings = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings 
         WHERE DATE(created_at) = %s",
        $today
    ));
    
    // Get today's earnings
    $today_earnings = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(subtotal) FROM {$wpdb->prefix}rbf_bookings 
         WHERE DATE(created_at) = %s AND status != 'cancelled'",
        $today
    ));
    $today_earnings = $today_earnings ? floatval($today_earnings) : 0;
    $today_vat = $today_earnings * 0.05; // 5% VAT
    
    // Get recent bookings with more details
    $recent_bookings = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}rbf_bookings 
         ORDER BY created_at DESC LIMIT 10"
    );
    
    // Get total bookings count
    $bookings_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings");
    $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE status = 'pending'");
    $confirmed_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE status = 'confirmed'");
    $completed_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE status = 'completed'");
    $cancelled_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings WHERE status = 'cancelled'");
}
?>

<div class="wrap">
    <h1>Repair Booking Dashboard</h1>
    
    <!-- Manual Database Creation Button -->
    <div class="rbf-dashboard-section" style="margin-bottom: 20px;">
        <h3>Database Management</h3>
        <form method="post" style="margin-bottom: 20px;">
            <?php wp_nonce_field('rbf_create_bookings_table'); ?>
            <input type="submit" name="create_bookings_table" class="button button-primary" value="Create Bookings Table">
            <p class="description">Click this button if you experience "no booking found" issues. This will create the missing bookings table.</p>
        </form>
        
        <form method="post" style="margin-bottom: 20px;">
            <?php wp_nonce_field('rbf_test_booking'); ?>
            <input type="submit" name="test_booking" class="button button-secondary" value="Create Test Booking">
            <p class="description">Click this button to create a test booking for debugging purposes.</p>
        </form>
        
        <form method="post" style="margin-bottom: 20px;">
            <?php wp_nonce_field('rbf_check_database'); ?>
            <input type="submit" name="check_database" class="button button-secondary" value="Check Database Status">
            <p class="description">Click this button to check the current database status and table structure.</p>
        </form>
    </div>
    
    <!-- Stats Cards Section -->
    <div class="rbf-dashboard-stats">
        <div class="rbf-stat-card">
            <div class="rbf-stat-icon">📱</div>
            <div class="rbf-stat-number"><?php echo $brands_count; ?></div>
            <div class="rbf-stat-label">Brands</div>
        </div>
        
        <div class="rbf-stat-card">
            <div class="rbf-stat-icon">🎯</div>
            <div class="rbf-stat-number"><?php echo $models_count; ?></div>
            <div class="rbf-stat-label">Models</div>
        </div>
        
        <div class="rbf-stat-card">
            <div class="rbf-stat-icon">🔧</div>
            <div class="rbf-stat-number"><?php echo $repairs_count; ?></div>
            <div class="rbf-stat-label">Repair Services</div>
        </div>
        
        <div class="rbf-stat-card">
            <div class="rbf-stat-icon">📋</div>
            <div class="rbf-stat-number"><?php echo $bookings_count; ?></div>
            <div class="rbf-stat-label">Total Bookings</div>
        </div>
        
        <div class="rbf-stat-card rbf-stat-pending">
            <div class="rbf-stat-icon">⏳</div>
            <div class="rbf-stat-number"><?php echo $pending_bookings; ?></div>
            <div class="rbf-stat-label">Pending Bookings</div>
        </div>
    </div>
    
    <!-- Today's Overview Section -->
    <div class="rbf-dashboard-section">
        <h2>Today's Overview</h2>
        <div class="rbf-today-overview">
            <div class="rbf-today-card">
                <div class="rbf-today-icon">📅</div>
                <div class="rbf-today-content">
                    <div class="rbf-today-label">Today's Bookings</div>
                    <div class="rbf-today-value"><?php echo $today_bookings; ?></div>
                </div>
            </div>
            
            <div class="rbf-today-card">
                <div class="rbf-today-icon">💰</div>
                <div class="rbf-today-content">
                    <div class="rbf-today-label">Today's Earnings</div>
                    <div class="rbf-today-value">AED <?php echo number_format($today_earnings, 2); ?></div>
                </div>
            </div>
            
            <div class="rbf-today-card">
                <div class="rbf-today-icon">📊</div>
                <div class="rbf-today-content">
                    <div class="rbf-today-label">Today's VAT</div>
                    <div class="rbf-today-value">AED <?php echo number_format($today_vat, 2); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="rbf-dashboard-content">
        <!-- Quick Actions Section -->
        <div class="rbf-dashboard-section">
            <h2>Quick Actions</h2>
            <div class="rbf-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=repair-booking-brands'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-smartphone"></span> Manage Brands
                </a>
                <a href="<?php echo admin_url('admin.php?page=repair-booking-models'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-tablet"></span> Manage Models
                </a>
                <a href="<?php echo admin_url('admin.php?page=repair-booking-repairs'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-tools"></span> Manage Repairs
                </a>
                <a href="<?php echo admin_url('admin.php?page=repair-booking-bookings'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-calendar-alt"></span> View Bookings
                </a>
            </div>
        </div>
        
        <!-- Recent Bookings Section -->
        <div class="rbf-dashboard-section">
            <h2>Recent Bookings</h2>
            <?php if ($recent_bookings): ?>
                <div class="rbf-table-container">
                    <table class="rbf-bookings-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Service Type</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                            <tr>
                                <td class="rbf-booking-id">#<?php echo $booking->id; ?></td>
                                <td class="rbf-customer-info">
                                    <div class="rbf-customer-name"><?php echo esc_html($booking->customer_name); ?></div>
                                    <div class="rbf-customer-email"><?php echo esc_html($booking->customer_email); ?></div>
                                </td>
                                <td class="rbf-device-info">
                                    <div class="rbf-device-brand"><?php echo esc_html($booking->brand); ?></div>
                                    <div class="rbf-device-model"><?php echo esc_html($booking->model); ?></div>
                                </td>
                                <td class="rbf-service-type">
                                    <?php 
                                    $service_labels = [
                                        'pickup' => '📦 Pickup',
                                        'delivery' => '📦 Delivery',
                                        'onsite' => '🏠 Onsite',
                                        'store_visit' => '🏪 Store Visit'
                                    ];
                                    echo isset($service_labels[$booking->service_type]) ? $service_labels[$booking->service_type] : ucfirst($booking->service_type);
                                    ?>
                                </td>
                                <td class="rbf-booking-total">AED <?php echo number_format($booking->subtotal, 2); ?></td>
                                <td class="rbf-booking-status">
                                    <span class="rbf-status rbf-status-<?php echo $booking->status; ?>">
                                        <?php echo ucfirst($booking->status); ?>
                                    </span>
                                </td>
                                <td class="rbf-booking-date"><?php echo date('M j, Y', strtotime($booking->created_at)); ?></td>
                                <td class="rbf-booking-actions">
                                    <a href="<?php echo admin_url('admin.php?page=repair-booking-bookings&action=view&id=' . $booking->id); ?>" 
                                       class="button button-small" title="View Details">
                                        👁️
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=repair-booking-bookings&action=edit&id=' . $booking->id); ?>" 
                                       class="button button-small" title="Edit">
                                        ✏️
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="rbf-no-bookings">
                    <?php if (!$table_exists): ?>
                        <p><strong>⚠️ Bookings table not found!</strong></p>
                        <p>This usually means the plugin wasn't properly activated or the database table creation failed.</p>
                        <p>Please go to the main <a href="<?php echo admin_url('admin.php?page=repair-booking'); ?>">Repair Booking Dashboard</a> and click the "Create Bookings Table" button to fix this issue.</p>
                    <?php else: ?>
                        <p>No bookings found.</p>
                        <p>Once customers start making bookings, they will appear here.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
