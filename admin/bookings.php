<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Ensure bookings table exists
if (class_exists('RepairBookingForm')) {
    $rbf_plugin = new RepairBookingForm();
    $rbf_plugin->ensure_bookings_table_exists();
}

// Get selected filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';

// Build where clause
$where_conditions = array();
$params = array();

if (!empty($status_filter)) {
    $where_conditions[] = 'status = %s';
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = 'DATE(created_at) = %s';
    $params[] = $date_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}rbf_bookings'");

if (!$table_exists) {
    $total_bookings = 0;
    $bookings = array();
    $total_pages = 0;
} else {
    // Get bookings with pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    if (!empty($params)) {
        $total_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings " . $where_clause,
            $params
        ));
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rbf_bookings " . $where_clause . "
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $params
        ));
    } else {
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}rbf_bookings");
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rbf_bookings 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
    }
    
    $total_pages = ceil($total_bookings / $per_page);
}
?>

<style>
.rbf-bookings-page {
    margin: 20px 0;
}

.rbf-booking-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.rbf-stat-item {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.rbf-status {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rbf-status-pending { background: #fff3cd; color: #856404; }
.rbf-status-confirmed { background: #d1ecf1; color: #0c5460; }
.rbf-status-in_progress { background: #d4edda; color: #155724; }
.rbf-status-completed { background: #c3e6cb; color: #155724; }
.rbf-status-cancelled { background: #f5c6cb; color: #721c24; }

.rbf-filter-section {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.rbf-filter-section form {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.rbf-filter-section select,
.rbf-filter-section input[type="date"] {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    min-width: 150px;
}

.rbf-bookings-table {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.rbf-bookings-table table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.rbf-bookings-table th {
    background: #017c36;
    color: white;
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rbf-bookings-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
    font-size: 14px;
}

.rbf-bookings-table tr:hover {
    background: #f8f9fa;
}

.rbf-bookings-table tr:last-child td {
    border-bottom: none;
}

.booking-status-select {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background: white;
    font-size: 13px;
    min-width: 120px;
}

.booking-status-select:focus {
    outline: none;
    border-color: #017c36;
    box-shadow: 0 0 0 2px rgba(1, 124, 54, 0.2);
}

.rbf-action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.rbf-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.3s ease;
}

.rbf-btn-view {
    background: #017c36;
    color: white;
}

.rbf-btn-view:hover {
    background: #05413a;
    color: white;
}

.rbf-btn-download {
    background: #17a2b8;
    color: white;
}

.rbf-btn-download:hover {
    background: #138496;
    color: white;
}

.rbf-btn-print {
    background: #28a745;
    color: white;
}

.rbf-btn-print:hover {
    background: #218838;
    color: white;
}

.rbf-btn-delete {
    background: #dc3545;
    color: white;
}

.rbf-btn-delete:hover {
    background: #c82333;
    color: white;
}

.rbf-btn-icon {
    width: 16px;
    height: 16px;
    display: inline-block;
}

/* Fix icon sizing for emojis and SVGs */
.rbf-bookings-page img[src*=".svg"], 
.rbf-bookings-page img[src*=".png"], 
.rbf-bookings-page img[src*=".jpg"],
.rbf-bookings-page img[src*=".jpeg"],
.rbf-bookings-page .emoji,
.rbf-bookings-page img[src*="emoji"] {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
}

/* Fix SVG icon sizing specifically */
.rbf-bookings-page img[src*="emoji"],
.rbf-bookings-page img[src*="svg"] {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    object-fit: contain;
}

/* Ensure emoji icons are properly sized */
.rbf-bookings-page .emoji-icon {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    display: inline-block;
    vertical-align: middle;
}

/* Force SVG icons to be small */
.rbf-bookings-page img[src*="emoji"],
.rbf-bookings-page img[src*="svg"] {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
    object-fit: contain;
    vertical-align: middle;
}

/* Override any WordPress default SVG sizing */
.rbf-bookings-page img[src*="emoji"] {
    width: 20px !important;
    height: 20px !important;
    max-width: 20px !important;
    max-height: 20px !important;
}

.rbf-pagination {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 20px;
    text-align: center;
}

.rbf-pagination .page-numbers {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    border: 1px solid #ddd;
    border-radius: 5px;
    text-decoration: none;
    color: #017c36;
    transition: all 0.3s ease;
}

.rbf-pagination .page-numbers:hover,
.rbf-pagination .page-numbers.current {
    background: #017c36;
    color: white;
    border-color: #017c36;
}

.rbf-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: none;
}

.rbf-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.rbf-modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.rbf-modal-header h3 {
    margin: 0;
    color: #017c36;
}

.rbf-modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}

.rbf-modal-close:hover {
    color: #000;
}

.rbf-modal-body {
    padding: 20px;
}

.rbf-modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    text-align: right;
}

.rbf-booking-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.rbf-detail-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}

.rbf-detail-section h4 {
    margin: 0 0 10px 0;
    color: #017c36;
    font-size: 16px;
}

.rbf-detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
}

.rbf-detail-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.rbf-detail-label {
    font-weight: 600;
    color: #333;
}

.rbf-detail-value {
    color: #666;
    text-align: right;
}

@media (max-width: 768px) {
    .rbf-filter-section form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .rbf-filter-section select,
    .rbf-filter-section input[type="date"] {
        min-width: auto;
    }
    
    .rbf-bookings-table {
        overflow-x: auto;
    }
    
    .rbf-action-buttons {
        flex-direction: column;
    }
}
</style>

<div class="wrap rbf-bookings-page">
    <h1>📋 Manage Bookings</h1>
    
    <!-- Summary Stats -->
    <div class="rbf-booking-stats">
        <?php
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}rbf_bookings GROUP BY status"
        );
        foreach ($status_counts as $stat): ?>
            <div class="rbf-stat-item">
                <span class="rbf-status rbf-status-<?php echo $stat->status; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $stat->status)); ?>: <?php echo $stat->count; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Filters -->
    <div class="rbf-filter-section">
        <form method="get" action="">
            <input type="hidden" name="page" value="repair-booking-bookings">
            <select name="status" id="status-filter">
                <option value="">All Statuses</option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>>Confirmed</option>
                <option value="in_progress" <?php selected($status_filter, 'in_progress'); ?>>In Progress</option>
                <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>Cancelled</option>
            </select>
            <input type="date" name="date" value="<?php echo esc_attr($date_filter); ?>">
            <input type="submit" class="button button-primary" value="Filter">
            <a href="<?php echo admin_url('admin.php?page=repair-booking-bookings'); ?>" class="button">Clear</a>
        </form>
    </div>
    
    <!-- Bookings Table -->
    <div class="rbf-bookings-table">
        <table>
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th style="width: 150px;">Customer</th>
                    <th style="width: 120px;">Phone</th>
                    <th style="width: 180px;">Email</th>
                    <th style="width: 150px;">Device</th>
                    <th style="width: 200px;">Repair</th>
                    <th style="width: 100px;">Total</th>
                    <th style="width: 130px;">Status</th>
                    <th style="width: 140px;">Date</th>
                    <th style="width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bookings): ?>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><strong>#<?php echo $booking->id; ?></strong></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; background: #017c36; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 16px;">
                                    <?php echo strtoupper(substr($booking->customer_name, 0, 1)); ?>
                                </div>
                                <span><?php echo esc_html($booking->customer_name); ?></span>
                            </div>
                        </td>
                        <td>
                            <a href="tel:<?php echo esc_attr($booking->customer_phone); ?>" style="color: #017c36; text-decoration: none;">
                                📞 <?php echo esc_html($booking->customer_phone); ?>
                            </a>
                        </td>
                        <td>
                            <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>" style="color: #017c36; text-decoration: none;">
                                ✉️ <?php echo esc_html($booking->customer_email); ?>
                            </a>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                    📱
                                </div>
                                <span><?php echo esc_html($booking->brand . ' ' . $booking->model); ?></span>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                    🔧
                                </div>
                                <span><?php echo esc_html($booking->repair); ?></span>
                            </div>
                        </td>
                        <td><strong style="color: #017c36;">AED <?php echo number_format($booking->total_amount, 2); ?></strong></td>
                        <td>
                            <select class="booking-status-select" data-booking-id="<?php echo $booking->id; ?>">
                                <option value="pending" <?php selected($booking->status, 'pending'); ?>>Pending</option>
                                <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmed</option>
                                <option value="in_progress" <?php selected($booking->status, 'in_progress'); ?>>In Progress</option>
                                <option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>
                                <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Cancelled</option>
                            </select>
                        </td>
                        <td><?php echo date('M j, Y g:i A', strtotime($booking->created_at)); ?></td>
                        <td>
                            <div class="rbf-action-buttons">
                                <button type="button" class="rbf-btn rbf-btn-view view-booking-btn" 
                                        data-id="<?php echo $booking->id; ?>">
                                    <span class="rbf-btn-icon">👁️</span>
                                    View
                                </button>
                                <button type="button" class="rbf-btn rbf-btn-download download-invoice-btn" 
                                        data-id="<?php echo $booking->id; ?>">
                                    <span class="rbf-btn-icon">📥</span>
                                    Download
                                </button>
                                <button type="button" class="rbf-btn rbf-btn-print print-invoice-btn" 
                                        data-id="<?php echo $booking->id; ?>">
                                    <span class="rbf-btn-icon">🖨️</span>
                                    Print
                                </button>
                                <button type="button" class="rbf-btn rbf-btn-delete delete-booking-btn" 
                                        data-id="<?php echo $booking->id; ?>">
                                    <span class="rbf-btn-icon">🗑️</span>
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                            <?php if (!$table_exists): ?>
                                <div style="font-size: 48px; margin-bottom: 10px;">⚠️</div>
                                <p><strong>Bookings table not found!</strong></p>
                                <p>This usually means the plugin wasn't properly activated or the database table creation failed.</p>
                                <p>Please go to the main <a href="<?php echo admin_url('admin.php?page=repair-booking'); ?>">Repair Booking Dashboard</a> and click the "Create Bookings Table" button to fix this issue.</p>
                            <?php else: ?>
                                <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                                <p>No bookings found.</p>
                                <p>Once customers start making bookings, they will appear here.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="rbf-pagination">
            <?php
            $page_links = paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo; Previous',
                'next_text' => 'Next &raquo;',
                'total' => $total_pages,
                'current' => $current_page,
                'type' => 'array'
            ));
            
            if ($page_links) {
                echo '<div class="page-numbers-wrapper">';
                foreach ($page_links as $link) {
                    echo $link;
                }
                echo '</div>';
            }
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Booking Details Modal -->
<div id="booking-modal" class="rbf-modal">
    <div class="rbf-modal-content">
        <div class="rbf-modal-header">
            <h3 id="booking-modal-title">Booking Details</h3>
            <span class="rbf-modal-close">&times;</span>
        </div>
        <div class="rbf-modal-body" id="booking-details-content">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="rbf-modal-footer">
            <button type="button" class="button button-primary" id="download-invoice-modal-btn" style="display: none;">
                📥 Download Invoice
            </button>
            <button type="button" class="button button-secondary" id="print-invoice-modal-btn" style="display: none;">
                🖨️ Print Invoice
            </button>
            <button type="button" class="button" id="close-booking-modal">Close</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Status change handler
    $('.booking-status-select').on('change', function() {
        const bookingId = $(this).data('booking-id');
        const newStatus = $(this).val();
        
        if (!confirm('Are you sure you want to change the status of this booking?')) {
            // Revert to previous value if cancelled
            location.reload();
            return;
        }
        
        $.post(rbf_admin_ajax.ajax_url, {
            action: 'rbf_update_booking_status',
            nonce: rbf_admin_ajax.nonce,
            booking_id: bookingId,
            status: newStatus
        }, function(response) {
            if (response.success) {
                // Show success message
                $('<div class="notice notice-success is-dismissible"><p>✅ Booking status updated successfully!</p></div>')
                    .insertAfter('.wrap h1').delay(3000).fadeOut();
            } else {
                alert('Error: ' + response.data);
                location.reload();
            }
        });
    });
    
    // View booking details
    $('.view-booking-btn').on('click', function() {
        const bookingId = $(this).data('id');
        
        $('#booking-details-content').html('<p style="text-align: center; padding: 40px;">Loading...</p>');
        $('#booking-modal').show();
        $('#download-invoice-modal-btn').show();
        $('#print-invoice-modal-btn').show();
        
        $.post(rbf_admin_ajax.ajax_url, {
            action: 'rbf_get_booking_details',
            nonce: rbf_admin_ajax.nonce,
            booking_id: bookingId
        }, function(response) {
            if (response.success) {
                $('#booking-details-content').html(response.data);
            } else {
                $('#booking-details-content').html('<p style="text-align: center; color: #dc3545;">Error loading booking details.</p>');
            }
        });
    });
    
    // Download invoice button
    $('.download-invoice-btn').on('click', function() {
        const bookingId = $(this).data('id');
        if (confirm('Generate and download invoice for this booking?')) {
            window.open(rbf_admin_ajax.ajax_url + '?action=rbf_generate_invoice&booking_id=' + bookingId + '&nonce=' + rbf_admin_ajax.nonce, '_blank');
        }
    });
    
    // Download invoice from modal
    $('#download-invoice-modal-btn').on('click', function() {
        const bookingId = $('.view-booking-btn').data('id');
        window.open(rbf_admin_ajax.ajax_url + '?action=rbf_generate_invoice&booking_id=' + bookingId + '&nonce=' + rbf_admin_ajax.nonce, '_blank');
    });
    
    // Print invoice button
    $('.print-invoice-btn').on('click', function() {
        const bookingId = $(this).data('id');
        if (confirm('Generate and print invoice for this booking?')) {
            window.open(rbf_admin_ajax.ajax_url + '?action=rbf_generate_invoice&booking_id=' + bookingId + '&nonce=' + rbf_admin_ajax.nonce + '&print=1', '_blank');
        }
    });
    
    // Print invoice from modal
    $('#print-invoice-modal-btn').on('click', function() {
        const bookingId = $('.view-booking-btn').data('id');
        window.open(rbf_admin_ajax.ajax_url + '?action=rbf_generate_invoice&booking_id=' + bookingId + '&nonce=' + rbf_admin_ajax.nonce + '&print=1', '_blank');
    });
    
    // Delete booking
    $('.delete-booking-btn').on('click', function() {
        const bookingId = $(this).data('id');
        if (confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
            $.post(rbf_admin_ajax.ajax_url, {
                action: 'rbf_delete_booking',
                nonce: rbf_admin_ajax.nonce,
                booking_id: bookingId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
    });
    
    // Close modal
    $('.rbf-modal-close, #close-booking-modal').on('click', function() {
        $('#booking-modal').hide();
    });
    
    // Close modal when clicking outside
    $('#booking-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
</script>
