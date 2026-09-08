<?php
/**
 * Customer Repair Status Tracker Shortcode and Handler
 *
 * Provides [rbf_track_repair] shortcode for customers to track device repair progress live.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_Tracker {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_shortcode('rbf_track_repair', [$this, 'render_tracker']);
        add_action('wp_ajax_rbf_track_booking_status', [$this, 'ajax_track_status']);
        add_action('wp_ajax_nopriv_rbf_track_booking_status', [$this, 'ajax_track_status']);
    }

    /**
     * Render tracker UI
     */
    public function render_tracker($atts) {
        ob_start();
        ?>
        <div id="rbf-tracker-container" class="rbf-tracker-wrapper">
            <div class="rbf-tracker-card">
                <div class="rbf-tracker-header">
                    <h2>🔍 Track Your Repair Status</h2>
                    <p>Enter your Booking ID or Phone Number to check live repair progress</p>
                </div>
                
                <form id="rbf-track-form" class="rbf-track-form">
                    <div class="rbf-track-input-group">
                        <input type="text" id="rbf-track-query" placeholder="e.g., RBF-2026-1234 or +971501234567" required>
                        <button type="submit" id="rbf-track-btn" class="rbf-btn-primary">
                            <span>Track Status</span>
                            <span class="rbf-track-spinner" style="display:none;">⏳</span>
                        </button>
                    </div>
                </form>

                <div id="rbf-track-result" class="rbf-track-result" style="display: none;">
                    <!-- Dynamically populated via AJAX -->
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('rbf-track-form');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var query = document.getElementById('rbf-track-query').value.trim();
                var resultDiv = document.getElementById('rbf-track-result');
                var btn = document.getElementById('rbf-track-btn');
                var spinner = btn.querySelector('.rbf-track-spinner');

                if (!query) return;

                btn.disabled = true;
                if (spinner) spinner.style.display = 'inline-block';
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="rbf-track-loading">Searching your repair record...</div>';

                var ajaxUrl = (typeof rbf_ajax !== 'undefined') ? rbf_ajax.ajax_url : '/wp-admin/admin-ajax.php';
                var nonce = (typeof rbf_ajax !== 'undefined') ? rbf_ajax.nonce : '';

                var formData = new FormData();
                formData.append('action', 'rbf_track_booking_status');
                formData.append('query', query);
                formData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    if (spinner) spinner.style.display = 'none';

                    if (data.success && data.data) {
                        var b = data.data;
                        var statusSteps = [
                            { key: 'pending', label: 'Booking Received', desc: 'Order logged in system' },
                            { key: 'in_progress', label: 'Under Repair', desc: 'Technician is working' },
                            { key: 'quality_check', label: 'Quality Tested', desc: 'Testing all functions' },
                            { key: 'completed', label: 'Ready for You', desc: 'Pickup / Delivery ready' }
                        ];

                        var currentIdx = 0;
                        if (b.status === 'in_progress') currentIdx = 1;
                        else if (b.status === 'quality_check') currentIdx = 2;
                        else if (b.status === 'completed' || b.status === 'delivered') currentIdx = 3;

                        var stepsHtml = '<div class="rbf-track-stepper">';
                        statusSteps.forEach(function(s, idx) {
                            var isDone = idx <= currentIdx;
                            var isActive = idx === currentIdx;
                            stepsHtml += '<div class="rbf-track-step ' + (isDone ? 'done ' : '') + (isActive ? 'active' : '') + '">' +
                                '<div class="rbf-step-bubble">' + (isDone ? '✓' : (idx + 1)) + '</div>' +
                                '<div class="rbf-step-text"><strong>' + s.label + '</strong><span>' + s.desc + '</span></div>' +
                            '</div>';
                        });
                        stepsHtml += '</div>';

                        var detailsHtml = '<div class="rbf-track-info-grid">' +
                            '<div class="rbf-info-box"><span>Booking ID</span><strong>#' + (b.booking_id || b.id) + '</strong></div>' +
                            '<div class="rbf-info-box"><span>Device</span><strong>' + (b.brand || '') + ' ' + (b.model || '') + '</strong></div>' +
                            '<div class="rbf-info-box"><span>Service Type</span><strong style="text-transform:capitalize;">' + (b.service_type || 'Standard') + '</strong></div>' +
                            '<div class="rbf-info-box"><span>Current Status</span><strong class="rbf-status-tag ' + (b.status || 'pending') + '">' + (b.status_label || b.status) + '</strong></div>' +
                        '</div>';

                        if (b.notes) {
                            detailsHtml += '<div class="rbf-track-notes"><strong>Technician Update:</strong> ' + b.notes + '</div>';
                        }

                        if (b.whatsapp_url) {
                            detailsHtml += '<div class="rbf-track-actions"><a href="' + b.whatsapp_url + '" target="_blank" class="rbf-btn-wa">💬 Chat with Shop on WhatsApp</a></div>';
                        }

                        resultDiv.innerHTML = '<div class="rbf-track-card-body">' + stepsHtml + detailsHtml + '</div>';
                    } else {
                        resultDiv.innerHTML = '<div class="rbf-track-error">❌ ' + (data.data || 'No booking record found for this search. Please verify your Booking ID or Phone Number.') + '</div>';
                    }
                })
                .catch(function(err) {
                    btn.disabled = false;
                    if (spinner) spinner.style.display = 'none';
                    resultDiv.innerHTML = '<div class="rbf-track-error">⚠️ Connection error. Please try again.</div>';
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX Search for booking status
     */
    public function ajax_track_status() {
        global $wpdb;
        $query = sanitize_text_field($_POST['query'] ?? '');

        if (empty($query)) {
            wp_send_json_error('Please enter a Booking ID or Phone number.');
        }

        $table = $wpdb->prefix . 'rbf_bookings';
        $cleaned_phone = preg_replace('/[^0-9]/', '', $query);

        // Search by booking_id, phone, or id
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE booking_id = %s OR id = %d OR REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '-', ''), '+', '') LIKE %s ORDER BY id DESC LIMIT 1",
            $query,
            intval($query),
            '%' . $wpdb->esc_like($cleaned_phone) . '%'
        ), ARRAY_A);

        if (!$booking) {
            wp_send_json_error('No booking record found for: ' . esc_html($query));
        }

        $status_labels = [
            'pending' => 'Pending Review',
            'in_progress' => 'Under Repair',
            'completed' => 'Completed / Ready for Pickup',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];

        $booking['status_label'] = $status_labels[$booking['status']] ?? ucfirst($booking['status']);

        // WhatsApp direct contact link
        $shop_phone = get_option('rbf_business_phone', '+971 50 123 4567');
        $wa_helper = class_exists('RBF_WhatsApp') ? RBF_WhatsApp::get_instance() : null;
        if ($wa_helper) {
            $booking['whatsapp_url'] = $wa_helper->get_direct_wa_url(
                $shop_phone,
                "Hi, I am inquiring about my Repair Booking #" . ($booking['booking_id'] ?? $booking['id'])
            );
        }

        // Clean sensitive data before sending to public client
        unset($booking['customer_email']);
        unset($booking['address']);

        wp_send_json_success($booking);
    }
}
