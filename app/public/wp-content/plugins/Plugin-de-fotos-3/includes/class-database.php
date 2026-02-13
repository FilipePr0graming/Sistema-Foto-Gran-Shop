<?php
/**
 * Database operations for Polaroids Customizadas
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDPP_Database
{

    /**
     * Orders table name
     */
    private $orders_table;

    /**
     * Photos table name
     */
    private $photos_table;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->orders_table = $wpdb->prefix . 'polaroid_orders';
        $this->photos_table = $wpdb->prefix . 'polaroid_photos';
    }

    /**
     * Create database tables
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Orders table
        $sql_orders = "CREATE TABLE {$this->orders_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id VARCHAR(100) NOT NULL,
            store VARCHAR(50) NOT NULL DEFAULT 'gran_shop',
            photo_quantity INT(11) NOT NULL DEFAULT 0,
            has_border TINYINT(1) NOT NULL DEFAULT 0,
            has_magnet TINYINT(1) NOT NULL DEFAULT 0,
            has_clip TINYINT(1) NOT NULL DEFAULT 0,
            has_twine TINYINT(1) NOT NULL DEFAULT 0,
            has_frame TINYINT(1) NOT NULL DEFAULT 0,
            customer_name VARCHAR(255) DEFAULT '',
            customer_email VARCHAR(255) DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            access_token VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            grid_type VARCHAR(10) NOT NULL DEFAULT '3x3',
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id),
            KEY status (status),
            KEY store (store),
            KEY access_token (access_token),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Photos table
        $sql_photos = "CREATE TABLE {$this->photos_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_ref_id BIGINT(20) UNSIGNED NOT NULL,
            image_path VARCHAR(500) NOT NULL,
            image_url VARCHAR(500) NOT NULL,
            text LONGTEXT DEFAULT NULL,
            emoji LONGTEXT DEFAULT NULL,
            font_family VARCHAR(100) DEFAULT 'Pacifico',
            crop_x FLOAT DEFAULT 0,
            crop_y FLOAT DEFAULT 0,
            crop_width FLOAT DEFAULT 0,
            crop_height FLOAT DEFAULT 0,
            zoom FLOAT DEFAULT 1,
            rotation INT DEFAULT 0,
            position_order INT DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_ref_id (order_ref_id),
            KEY position_order (position_order),
            CONSTRAINT fk_order_ref FOREIGN KEY (order_ref_id) REFERENCES {$this->orders_table}(id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_orders);
        dbDelta($sql_photos);

        // Manual check for grid_type column just in case dbDelta fails
        $column_grid = $wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = 'grid_type'", $this->orders_table));
        if (empty($column_grid)) {
            $wpdb->query("ALTER TABLE {$this->orders_table} ADD grid_type VARCHAR(10) NOT NULL DEFAULT '3x3'");
        }

        // Manual check for emoji column in photos table
        $column_emoji = $wpdb->get_results($wpdb->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = 'emoji'", $this->photos_table));
        if (empty($column_emoji)) {
            $wpdb->query("ALTER TABLE {$this->photos_table} ADD emoji LONGTEXT DEFAULT NULL");
        }

        // Add access_token index if missing
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$this->orders_table} WHERE Key_name = 'access_token'");
        if (empty($indexes)) {
            $wpdb->query("ALTER TABLE {$this->orders_table} ADD KEY access_token (access_token)");
        }
    }

    /**
     * Create a new order
     */
    public function create_order($data)
    {
        global $wpdb;

        // Validate required fields
        if (empty($data['order_id']) || strlen($data['order_id']) > 100) {
            return new WP_Error('invalid_order_id', 'Order ID is required and must be less than 100 characters');
        }

        // Check for duplicate order_id
        $existing = $this->get_order_by_order_id($data['order_id']);
        if ($existing) {
            return new WP_Error('duplicate_order_id', 'Order ID already exists');
        }

        // Validate store
        $allowed_stores = array('gran_shop', 'aisheel_mix');
        if (!in_array($data['store'], $allowed_stores)) {
            return new WP_Error('invalid_store', 'Invalid store specified');
        }

        // Validate photo quantity
        $photo_quantity = intval($data['photo_quantity']);
        if ($photo_quantity < 1 || $photo_quantity > 500) {
            return new WP_Error('invalid_quantity', 'Photo quantity must be between 1 and 500');
        }

        // Validate email if provided
        if (!empty($data['customer_email']) && !is_email($data['customer_email'])) {
            return new WP_Error('invalid_email', 'Invalid email address');
        }

        // Validate grid type
        $allowed_grid_types = array('3x3', '2x3');
        $grid_type = isset($data['grid_type']) && in_array($data['grid_type'], $allowed_grid_types) ? $data['grid_type'] : '3x3';

        // Generate secure access token
        $access_token = wp_generate_password(32, false);

        $result = $wpdb->insert(
            $this->orders_table,
            array(
                'order_id' => sanitize_text_field($data['order_id']),
                'store' => sanitize_text_field($data['store']),
                'photo_quantity' => $photo_quantity,
                'has_border' => intval($data['has_border'] ?? 0),
                'has_magnet' => intval($data['has_magnet'] ?? 0),
                'has_clip' => intval($data['has_clip'] ?? 0),
                'has_twine' => intval($data['has_twine'] ?? 0),
                'has_frame' => intval($data['has_frame'] ?? 0),
                'customer_name' => sanitize_text_field($data['customer_name'] ?? ''),
                'customer_email' => sanitize_email($data['customer_email'] ?? ''),
                'access_token' => $access_token,
                'status' => 'pending',
                'grid_type' => $grid_type
            ),
            array('%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            $order = $this->get_order($wpdb->insert_id);
            $order->access_token = $access_token;
            return $order;
        }

        // Return error message if insert fails
        return new WP_Error('db_insert_failed', $wpdb->last_error);
    }

    /**
     * Get order by ID
     */
    public function get_order($id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->orders_table} WHERE id = %d",
                $id
            )
        );
    }

    /**
     * Get order by external order_id
     */
    public function get_order_by_order_id($order_id)
    {
        global $wpdb;

        // Validate order_id
        if (empty($order_id) || strlen($order_id) > 100) {
            return null;
        }

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->orders_table} WHERE order_id = %s",
                sanitize_text_field($order_id)
            )
        );

        // Log access attempt securely
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
            if (!file_exists(dirname($log_file))) {
                wp_mkdir_p(dirname($log_file));
            }
            $time = date('Y-m-d H:i:s');
            $result = $order ? 'Found' : 'NOT Found';
            $safe_order_id = substr($order_id, 0, 3) . '***'; // Partially hide order ID
            error_log("[$time] [DB] get_order_by_order_id($safe_order_id) result: $result", 3, $log_file);
        }

        return $order;
    }

    /**
     * Get order by access token
     */
    public function get_order_by_token($token)
    {
        global $wpdb;

        // Validate token format
        if (empty($token) || strlen($token) !== 32) {
            return null;
        }

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->orders_table} WHERE access_token = %s",
                $token
            )
        );

        // Log access attempt securely (without exposing token)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
            if (!file_exists(dirname($log_file))) {
                wp_mkdir_p(dirname($log_file));
            }
            $time = date('Y-m-d H:i:s');
            $result = $order ? 'Found' : 'NOT Found';
            error_log("[$time] [DB] get_order_by_token result: $result", 3, $log_file);
        }

        return $order;
    }

    /**
     * Get all orders
     */
    public function get_orders($args = array())
    {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'store' => '',
            'search' => '',
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');

        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        } else {
            // Exclude trash by default if no status is specified
            $where[] = "status != 'trash'";
        }

        if (!empty($args['store'])) {
            $where[] = $wpdb->prepare("store = %s", $args['store']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare(
                "(order_id LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)",
                $search,
                $search,
                $search
            );
        }

        $where_clause = implode(' AND ', $where);
        
        // Secure orderby validation
        $allowed_orderby = array('created_at', 'updated_at', 'order_id', 'customer_name', 'status', 'store');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        
        $allowed_order = array('ASC', 'DESC');
        $order = in_array(strtoupper($args['order']), $allowed_order) ? strtoupper($args['order']) : 'DESC';

        $sql = "SELECT * FROM {$this->orders_table} 
                WHERE {$where_clause} 
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        return $wpdb->get_results(
            $wpdb->prepare($sql, $args['limit'], $args['offset'])
        );
    }

    /**
     * Get orders count
     */
    public function get_orders_count($args = array())
    {
        global $wpdb;

        $where = array('1=1');

        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        } else {
            // Exclude trash by default if no status is specified
            $where[] = "status != 'trash'";
        }

        if (!empty($args['store'])) {
            $where[] = $wpdb->prepare("store = %s", $args['store']);
        }

        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare(
                "(order_id LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s)",
                $search,
                $search,
                $search
            );
        }

        $where_clause = implode(' AND ', $where);

        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->orders_table} WHERE {$where_clause}");
    }

    /**
     * Update order status
     */
    public function update_order_status($id, $status)
    {
        global $wpdb;

        return $wpdb->update(
            $this->orders_table,
            array('status' => $status),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Update order details
     */
    public function update_order($id, $data)
    {
        global $wpdb;

        // Whitelist allowed fields
        $allowed_fields = array('customer_name', 'customer_email', 'status', 'store', 'grid_type');
        $update_data = array();
        $format = array();

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_data[$key] = $value;
                $format[] = '%s';
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $this->orders_table,
            $update_data,
            array('id' => $id),
            $format,
            array('%d')
        );
    }

    /**
     * Save photos for an order
     */
    public function save_photos($order_id, $photos)
    {
        global $wpdb;

        if (empty($photos) || !is_array($photos)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
                if (!file_exists(dirname($log_file))) {
                    wp_mkdir_p(dirname($log_file));
                }
                error_log("[" . date('Y-m-d H:i:s') . "] [DB Warning] save_photos called for ID $order_id with EMPTY photos array.", 3, $log_file);
            }
            return false;
        }

        // Validate order exists
        $order = $this->get_order($order_id);
        if (!$order) {
            return false;
        }

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete existing photos
            $deleted = $wpdb->delete($this->photos_table, array('order_ref_id' => $order_id), array('%d'));

            // Insert new photos with validation
            foreach ($photos as $position => $photo) {
                // Validate photo data
                if (!is_array($photo)) {
                    throw new Exception('Invalid photo data at position ' . $position);
                }

                // Validate required fields
                if (empty($photo['image_path']) || empty($photo['image_url'])) {
                    throw new Exception('Missing required photo fields at position ' . $position);
                }

                // Validate file exists
                if (!file_exists($photo['image_path'])) {
                    throw new Exception('Photo file not found at position ' . $position);
                }

                // Normalize text layers (array of structured data)
                $text_layers = array();
                if (isset($photo['textLayers'])) {
                    $text_layers_raw = is_string($photo['textLayers']) ? json_decode(stripslashes($photo['textLayers']), true) : $photo['textLayers'];
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $text_layers_raw = array();
                    }

                    if (is_array($text_layers_raw)) {
                        foreach ($text_layers_raw as $layer) {
                            if (!is_array($layer)) {
                                continue;
                            }
                            $text_layers[] = array(
                                'text' => wp_kses_post($layer['text'] ?? ''),
                                'fontFamily' => sanitize_text_field($layer['fontFamily'] ?? 'Pacifico'),
                                'color' => sanitize_hex_color($layer['color'] ?? '#000000') ?: '#000000',
                                'size' => intval($layer['size'] ?? 28),
                                'bold' => !empty($layer['bold']),
                                'italic' => !empty($layer['italic']),
                                'rotation' => floatval($layer['rotation'] ?? 0),
                                'editorW' => floatval($layer['editorW'] ?? 0),
                                'editorH' => floatval($layer['editorH'] ?? 0),
                                'x' => floatval($layer['x'] ?? 0),
                                'y' => floatval($layer['y'] ?? 0)
                            );
                        }
                    }
                }

                $emoji_layers = array();
                if (isset($photo['emojiLayers'])) {
                    $emoji_layers_raw = is_string($photo['emojiLayers']) ? json_decode(stripslashes($photo['emojiLayers']), true) : $photo['emojiLayers'];
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $emoji_layers_raw = array();
                    }

                    if (is_array($emoji_layers_raw)) {
                        foreach ($emoji_layers_raw as $layer) {
                            if (!is_array($layer)) {
                                continue;
                            }
                            $emoji_layers[] = array(
                                'imageSrc' => esc_url_raw($layer['imageSrc'] ?? ''),
                                'size' => intval($layer['size'] ?? 32),
                                'rotation' => floatval($layer['rotation'] ?? 0),
                                'editorW' => floatval($layer['editorW'] ?? 0),
                                'editorH' => floatval($layer['editorH'] ?? 0),
                                'x' => floatval($layer['x'] ?? 0),
                                'y' => floatval($layer['y'] ?? 0)
                            );
                        }
                    }
                }

                $text_data = !empty($text_layers) ? wp_json_encode($text_layers) : '';

                // Legacy support for single text entry
                if (empty($text_layers) && isset($photo['text']) && !empty($photo['text'])) {
                    $text_data = is_array($photo['text']) ? wp_json_encode($photo['text']) : wp_kses_post($photo['text']);
                }

                // Serialize emoji data if present
                $emoji_data = !empty($emoji_layers) ? wp_json_encode($emoji_layers) : null;

                if (empty($emoji_layers) && isset($photo['emoji'])) {
                    $emoji_data = is_array($photo['emoji']) ? wp_json_encode($photo['emoji']) : sanitize_textarea_field($photo['emoji']);
                }

                $result = $wpdb->insert(
                    $this->photos_table,
                    array(
                        'order_ref_id' => $order_id,
                        'image_path' => sanitize_text_field($photo['image_path']),
                        'image_url' => esc_url_raw($photo['image_url']),
                        'text' => $text_data,
                        'emoji' => $emoji_data,
                        'font_family' => sanitize_text_field($photo['font_family'] ?? 'Pacifico'),
                        'crop_x' => floatval($photo['crop_x'] ?? 0),
                        'crop_y' => floatval($photo['crop_y'] ?? 0),
                        'crop_width' => floatval($photo['crop_width'] ?? 0),
                        'crop_height' => floatval($photo['crop_height'] ?? 0),
                        'zoom' => floatval($photo['zoom'] ?? 1),
                        'rotation' => intval($photo['rotation'] ?? 0),
                        'position_order' => intval($position)
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%d', '%d')
                );

                if ($result === false) {
                    throw new Exception('Failed to insert photo at position ' . $position . ': ' . $wpdb->last_error);
                }
            }

            // Update order status
            $this->update_order_status($order_id, 'photos_uploaded');

            // Commit transaction
            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
                if (!file_exists(dirname($log_file))) {
                    wp_mkdir_p(dirname($log_file));
                }
                error_log("[" . date('Y-m-d H:i:s') . "] [DB Error] save_photos error: " . $e->getMessage(), 3, $log_file);
            }
            return false;
        }
    }

    /**
     * Get photos for an order
     */
    public function get_photos($order_id)
    {
        global $wpdb;

        // Validate order_id
        $order_id = intval($order_id);
        if ($order_id <= 0) {
            return array();
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->photos_table} WHERE order_ref_id = %d ORDER BY position_order ASC",
            $order_id
        );

        $results = $wpdb->get_results($sql);

        // Secure logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
            if (!file_exists(dirname($log_file))) {
                wp_mkdir_p(dirname($log_file));
            }
            $time = date('Y-m-d H:i:s');
            $count = count($results);
            error_log("[$time] DB get_photos($order_id) found $count results.", 3, $log_file);
        }

        return $results;
    }

    /**
     * Delete order (Soft Delete)
     */
    public function delete_order($id)
    {
        // First check current status
        $order = $this->get_order($id);
        if (!$order) {
            return false;
        }

        // If already in trash, delete permanently
        if ($order->status === 'trash') {
            global $wpdb;
            return $wpdb->delete($this->orders_table, array('id' => $id), array('%d'));
        }

        // Otherwise move to trash
        return $this->update_order_status($id, 'trash');
    }
}
