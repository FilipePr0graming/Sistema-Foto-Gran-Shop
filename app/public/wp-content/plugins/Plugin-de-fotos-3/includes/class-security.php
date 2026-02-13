<?php
/**
 * Security utilities for Polaroids Customizadas
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDPP_Security
{
    /**
     * Validate file upload security
     */
    public static function validate_image_upload($file_path)
    {
        if (!file_exists($file_path)) {
            return false;
        }

        // Check file size (max 10MB)
        if (filesize($file_path) > 10 * 1024 * 1024) {
            return false;
        }

        // Validate MIME type using magic bytes
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        $allowed_types = array('image/jpeg', 'image/png', 'image/webp');
        return in_array($mime_type, $allowed_types);
    }

    /**
     * Sanitize filename for security
     */
    public static function sanitize_filename($filename)
    {
        // Remove any path traversal attempts
        $filename = basename($filename);
        
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Limit length
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }

        return $filename;
    }

    /**
     * Generate secure token
     */
    public static function generate_secure_token($length = 32)
    {
        return wp_generate_password($length, false);
    }

    /**
     * Validate order access
     */
    public static function validate_order_access($order_id, $user_id = null)
    {
        // Admin users can access all orders
        if (current_user_can('manage_options')) {
            return true;
        }

        // For frontend users, validate token
        if (empty($_COOKIE['sdpp_order_token'])) {
            return false;
        }

        $token = sanitize_text_field($_COOKIE['sdpp_order_token']);
        if (strlen($token) !== 32) {
            return false;
        }

        $database = new SDPP_Database();
        $order = $database->get_order_by_token($token);

        return $order && $order->id == $order_id;
    }

    /**
     * Rate limiting check
     */
    public static function check_rate_limit($action, $identifier, $limit = 10, $window = HOUR_IN_SECONDS)
    {
        $key = 'sdpp_rate_' . $action . '_' . md5($identifier);
        $count = get_transient($key) ?: 0;
        
        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Secure logging
     */
    public static function log($message, $level = 'info')
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
        if (!file_exists(dirname($log_file))) {
            wp_mkdir_p(dirname($log_file));
        }

        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        error_log($log_entry, 3, $log_file);
    }

    /**
     * Sanitize output for display
     */
    public static function sanitize_output($data, $context = 'html')
    {
        switch ($context) {
            case 'html':
                return is_array($data) ? array_map('esc_html', $data) : esc_html($data);
            case 'attr':
                return is_array($data) ? array_map('esc_attr', $data) : esc_attr($data);
            case 'url':
                return is_array($data) ? array_map('esc_url', $data) : esc_url($data);
            case 'js':
                return is_array($data) ? array_map('esc_js', $data) : esc_js($data);
            default:
                return $data;
        }
    }
}