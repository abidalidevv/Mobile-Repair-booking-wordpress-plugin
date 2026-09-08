<?php
/**
 * WhatsApp Notification & Messaging Helper for EFIX Repair Booking Form
 *
 * Supports direct wa.me links (100% free) and automated UltraMsg API integration.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RBF_WhatsApp {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_rbf_send_whatsapp_notification', [$this, 'ajax_send_notification']);
    }

    /**
     * Format phone number to international format (E.164 without +)
     */
    public function format_phone($phone) {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        // If UAE number starts with 05..., convert to 9715...
        if (str_starts_with($cleaned, '05') && strlen($cleaned) === 10) {
            $cleaned = '971' . substr($cleaned, 1);
        }
        // If Saudi number starts with 05..., convert to 9665...
        if (str_starts_with($cleaned, '05') && strlen($cleaned) === 10 && get_option('rbf_default_currency') === 'SAR') {
            $cleaned = '966' . substr($cleaned, 1);
        }
        return $cleaned;
    }

    /**
     * Build customer booking confirmation message
     */
    public function get_booking_message($booking) {
        $store_name = get_bloginfo('name');
        $site_phone = get_option('rbf_business_phone', '+971 50 123 4567');
        
        $customer_name = esc_html($booking['customer_name'] ?? $booking['name'] ?? 'Customer');
        $booking_id = esc_html($booking['booking_id'] ?? 'N/A');
        $device = esc_html(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''));
        $total = esc_html($booking['total_amount'] ?? $booking['total'] ?? '0.00');
        $currency = esc_html($booking['currency'] ?? 'AED');

        $msg = "🛠️ *{$store_name} — Booking Confirmation*\n\n";
        $msg .= "Hello *{$customer_name}*,\n";
        $msg .= "Your device repair booking has been received!\n\n";
        $msg .= "📌 *Booking ID:* #{$booking_id}\n";
        $msg .= "📱 *Device:* {$device}\n";
        $msg .= "💰 *Estimated Total:* {$currency} {$total}\n";
        $msg .= "⏳ *Status:* Received / Under Review\n\n";
        $msg .= "Our technician will contact you shortly to confirm schedule & details.\n";
        $msg .= "📞 *Helpline:* {$site_phone}\n";
        $msg .= "Thank you for choosing {$store_name}!";

        return apply_filters('rbf_whatsapp_booking_message', $msg, $booking);
    }

    /**
     * Build status update message for customer
     */
    public function get_status_update_message($booking, $new_status) {
        $store_name = get_bloginfo('name');
        $customer_name = esc_html($booking['customer_name'] ?? 'Customer');
        $booking_id = esc_html($booking['booking_id'] ?? 'N/A');
        $device = esc_html(($booking['brand'] ?? '') . ' ' . ($booking['model'] ?? ''));

        $status_emojis = [
            'pending' => '⏳ Pending',
            'in_progress' => '🔧 In Progress (Repairing)',
            'completed' => '✅ Completed & Ready for Pickup/Delivery',
            'delivered' => '🎉 Delivered',
            'cancelled' => '❌ Cancelled'
        ];

        $status_text = $status_emojis[$new_status] ?? ucfirst(str_replace('_', ' ', $new_status));

        $msg = "🔔 *{$store_name} — Repair Status Update*\n\n";
        $msg .= "Dear *{$customer_name}*,\n";
        $msg .= "The status of your repair booking *#{$booking_id}* ({$device}) has been updated:\n\n";
        $msg .= "⚡ *New Status:* {$status_text}\n\n";
        $msg .= "For any questions, reply to this message directly.";

        return apply_filters('rbf_whatsapp_status_message', $msg, $booking, $new_status);
    }

    /**
     * Generate direct WhatsApp Click-to-Chat URL
     */
    public function get_direct_wa_url($phone, $message) {
        $formatted_phone = $this->format_phone($phone);
        $encoded_msg = urlencode($message);
        return "https://wa.me/{$formatted_phone}?text={$encoded_msg}";
    }

    /**
     * Send automated WhatsApp message via UltraMsg API (if configured)
     */
    public function send_ultramsg_message($phone, $message) {
        $instance_id = get_option('rbf_ultramsg_instance_id', '');
        $token = get_option('rbf_ultramsg_token', '');
        $is_enabled = get_option('rbf_whatsapp_api_enabled', false);

        if (!$is_enabled || empty($instance_id) || empty($token)) {
            return false;
        }

        $formatted_phone = $this->format_phone($phone);
        $url = "https://api.ultramsg.com/{$instance_id}/messages/chat";

        $response = wp_remote_post($url, [
            'body' => [
                'token' => $token,
                'to' => $formatted_phone,
                'body' => $message
            ],
            'timeout' => 10,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            error_log('RBF WhatsApp Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['sent']) && $body['sent'] === 'true';
    }

    /**
     * AJAX endpoint to send manual WhatsApp update from admin
     */
    public function ajax_send_notification() {
        check_ajax_referer('rbf_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $booking_id = sanitize_text_field($_POST['booking_id'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (empty($phone) || empty($message)) {
            wp_send_json_error('Phone number and message are required.');
        }

        $sent = $this->send_ultramsg_message($phone, $message);
        $wa_url = $this->get_direct_wa_url($phone, $message);

        wp_send_json_success([
            'api_sent' => $sent,
            'wa_direct_url' => $wa_url,
            'message' => $sent ? 'WhatsApp notification sent successfully!' : 'WhatsApp direct link generated.'
        ]);
    }
}
