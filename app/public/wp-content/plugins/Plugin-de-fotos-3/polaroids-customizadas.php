
<?php
/**
 * Plugin Name: Polaroids Customizadas
 * Plugin URI: https://example.com/polaroids-customizadas
 * Description: WordPress plugin for creating customized Polaroid-style photo orders with admin dashboard, customer editor, and 300 DPI PNG generation.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://example.com
 * Text Domain: polaroids-customizadas
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SDPP_VERSION', '1.2.2');
define('SDPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SDPP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SDPP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'SDPP_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $class_name = str_replace($prefix, '', $class);
    $class_name = strtolower(str_replace('_', '-', $class_name));
    $file = SDPP_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Main plugin class
 */
class SDPP_Plugin
{

    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Init hooks
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // AJAX hooks
        add_action('wp_ajax_sdpp_create_order', array($this, 'ajax_create_order'));
        add_action('wp_ajax_sdpp_save_photos', array($this, 'ajax_save_photos'));
        add_action('wp_ajax_nopriv_sdpp_save_photos', array($this, 'ajax_save_photos'));
        add_action('wp_ajax_sdpp_save_customer_email', array($this, 'ajax_save_customer_email'));
        add_action('wp_ajax_nopriv_sdpp_save_customer_email', array($this, 'ajax_save_customer_email'));
        add_action('wp_ajax_sdpp_upload_photo', array($this, 'ajax_upload_photo'));
        add_action('wp_ajax_nopriv_sdpp_upload_photo', array($this, 'ajax_upload_photo'));
        add_action('wp_ajax_sdpp_generate_png', array($this, 'ajax_generate_png'));
        add_action('wp_ajax_sdpp_get_png_progress', array($this, 'ajax_get_png_progress'));
        add_action('wp_ajax_sdpp_delete_order', array($this, 'ajax_delete_order'));
        add_action('wp_ajax_sdpp_update_order', array($this, 'ajax_update_order'));
        add_action('wp_ajax_sdpp_get_order_details', array($this, 'ajax_get_order_details'));
        add_action('wp_ajax_sdpp_bulk_delete', array($this, 'ajax_bulk_delete'));
        add_action('wp_ajax_sdpp_bulk_generate_zip', array($this, 'ajax_bulk_generate_zip'));
        add_action('wp_ajax_sdpp_get_metrics', array($this, 'ajax_get_metrics'));

        // Email settings AJAX
        add_action('wp_ajax_sdpp_save_email_settings', array($this, 'ajax_save_email_settings'));
        add_action('wp_ajax_sdpp_send_test_email', array($this, 'ajax_send_test_email'));

        // Backup AJAX
        add_action('wp_ajax_sdpp_export_backup', array($this, 'ajax_export_backup'));
        add_action('wp_ajax_sdpp_import_backup', array($this, 'ajax_import_backup'));

        // Login/Logout AJAX
        add_action('wp_ajax_sdpp_login', array($this, 'ajax_login'));
        add_action('wp_ajax_nopriv_sdpp_login', array($this, 'ajax_login'));
        add_action('wp_ajax_sdpp_logout', array($this, 'ajax_logout'));
        add_action('wp_ajax_nopriv_sdpp_logout', array($this, 'ajax_logout'));

        // Shortcode for public editor
        add_shortcode('polaroid_editor', array($this, 'render_editor_shortcode'));

        // SMTP Configuration
        add_action('phpmailer_init', array($this, 'configure_smtp'));
    }

    /**
     * Configure PHPMailer for SMTP
     */
    public function configure_smtp($phpmailer)
    {
        if (get_option('sdpp_enable_smtp', '0') !== '1') {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = get_option('sdpp_smtp_host', 'smtp.gmail.com');
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = get_option('sdpp_smtp_port', '587');
        $phpmailer->Username = get_option('sdpp_smtp_user', '');
        $phpmailer->Password = get_option('sdpp_smtp_pass', '');
        $phpmailer->SMTPSecure = get_option('sdpp_smtp_encryption', 'tls');

        $from_name = get_option('sdpp_smtp_from_name', get_bloginfo('name'));
        $phpmailer->setFrom($phpmailer->Username, $from_name);
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        $database = new SDPP_Database();
        $database->create_tables();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Plugin initialization
     */
    public function init()
    {
        load_plugin_textdomain('polaroids-customizadas', false, dirname(SDPP_PLUGIN_BASENAME) . '/languages');

        // Ensure database tables are up to date
        // Removed is_admin() check to force update on frontend calls if needed
        $database = new SDPP_Database();
        $database->create_tables();

        // Handle GET logout with Nuclear Option
        if (isset($_GET['sdpp_logout'])) {
            // Prevent caching
            nocache_headers();

            // Defined variations to try
            $paths = array('/', '/wp-admin', '');
            $domains = array('', $_SERVER['HTTP_HOST']);
            $secure_flags = array(true, false);
            $httponly_flags = array(true, false);

            foreach ($paths as $path) {
                foreach ($domains as $domain) {
                    foreach ($secure_flags as $secure) {
                        foreach ($httponly_flags as $httponly) {
                            setcookie('sdpp_order_token', 'deleted', time() - 31536000, $path, $domain, $secure, $httponly);
                            setcookie('sdpp_order_token', '', time() - 31536000, $path, $domain, $secure, $httponly);
                        }
                    }
                }
            }
            
            // Also unset from current request
            if (isset($_COOKIE['sdpp_order_token'])) {
                unset($_COOKIE['sdpp_order_token']);
                $_COOKIE['sdpp_order_token'] = null;
            }

            // Redirect to remove the logout param so subsequent reloads don't trigger logout again
            if (!headers_sent()) {
                wp_redirect(remove_query_arg('sdpp_logout'));
                exit;
            }
        }
    }

    /**
     * AJAX: Save customer email (collected only after photos upload)
     */
    public function ajax_save_customer_email()
    {
        ob_start();
        check_ajax_referer('sdpp_editor_nonce', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');

        if (!$order_id) {
            wp_send_json_error(array('message' => __('ID do pedido inválido.', 'polaroids-customizadas')));
        }

        if (empty($customer_email) || !is_email($customer_email)) {
            wp_send_json_error(array('message' => __('Email inválido.', 'polaroids-customizadas')));
        }

        if (!$this->user_can_access_order($order_id)) {
            wp_send_json_error(array('message' => __('Acesso negado.', 'polaroids-customizadas')));
        }

        $database = new SDPP_Database();
        $result = $database->update_order($order_id, array('customer_email' => $customer_email));

        $output_buffer = ob_get_clean();
        if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SDPP Output Buffer Leaked (ajax_save_customer_email): " . $output_buffer);
        }

        if ($result === false) {
            wp_send_json_error(array('message' => __('Falha ao salvar email.', 'polaroids-customizadas')));
        }

        $order = $database->get_order($order_id);
        if ($order) {
            $this->send_customer_notification((array) $order);
        }

        wp_send_json_success(array(
            'message' => __('Email salvo com sucesso. Enviamos uma confirmação de recebimento das fotos.', 'polaroids-customizadas')
        ));
    }

    /**
     * Register admin menu
     */
    public function admin_menu()
    {
        add_menu_page(
            __('Polaroids', 'polaroids-customizadas'),
            __('Polaroids', 'polaroids-customizadas'),
            'manage_options',
            'polaroids-customizadas',
            array($this, 'render_order_list_page'),
            'dashicons-format-gallery',
            30
        );

        add_submenu_page(
            'polaroids-customizadas',
            __('Pedidos', 'polaroids-customizadas'),
            __('Pedidos', 'polaroids-customizadas'),
            'manage_options',
            'polaroids-customizadas',
            array($this, 'render_order_list_page')
        );

        add_submenu_page(
            'polaroids-customizadas',
            __('Novo Pedido', 'polaroids-customizadas'),
            __('Novo Pedido', 'polaroids-customizadas'),
            'manage_options',
            'polaroids-new-order',
            array($this, 'render_new_order_page')
        );

        add_submenu_page(
            'polaroids-customizadas',
            __('Configurações', 'polaroids-customizadas'),
            __('Configurações', 'polaroids-customizadas'),
            'manage_options',
            'polaroids-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook)
    {
        // Only load on our plugin pages
        if (strpos($hook, 'polaroids') === false) {
            return;
        }

        // Google Fonts
        $fonts = SDPP_Fonts::get_instance();
        wp_enqueue_style('sdpp-google-fonts', $fonts->get_google_fonts_url(), array(), null);

        // Admin CSS
        wp_enqueue_style('sdpp-admin', SDPP_PLUGIN_URL . 'admin/css/admin.css', array(), SDPP_VERSION);

        // Admin JS
        $sdpp_admin_js_ver = SDPP_VERSION;
        $sdpp_admin_js_path = SDPP_PLUGIN_DIR . 'admin/js/admin.js';
        if (file_exists($sdpp_admin_js_path)) {
            $sdpp_admin_js_ver = (string) filemtime($sdpp_admin_js_path);
        }

        wp_enqueue_script('sdpp-admin', SDPP_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), $sdpp_admin_js_ver, true);

        wp_localize_script('sdpp-admin', 'sdppAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sdpp_admin_nonce'),
            'fonts' => $fonts->get_fonts_list(),
            'stores' => $this->get_stores_config()
        ));
    }

    /**
     * Enqueue public scripts and styles
     */
    public function public_enqueue_scripts()
    {
        // Google Fonts
        $fonts = SDPP_Fonts::get_instance();
        wp_enqueue_style('sdpp-google-fonts', $fonts->get_google_fonts_url(), array(), null);
        wp_enqueue_style('dashicons');

        // Cropper.js for image manipulation
        wp_enqueue_style('cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css', array(), '1.6.1');
        wp_enqueue_script('cropperjs', 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js', array(), '1.6.1', true);

        // HEIC Support
        wp_enqueue_script('heic2any', 'https://cdnjs.cloudflare.com/ajax/libs/heic2any/0.0.4/heic2any.min.js', array(), '0.0.4', true);

        // Editor CSS
        $sdpp_editor_css_ver = SDPP_VERSION;
        $sdpp_ratio_fix_css_ver = SDPP_VERSION;
        $sdpp_editor_js_ver = SDPP_VERSION;

        $sdpp_editor_css_path = SDPP_PLUGIN_DIR . 'public/css/editor.css';
        if (file_exists($sdpp_editor_css_path)) {
            $sdpp_editor_css_ver = (string) filemtime($sdpp_editor_css_path);
        }

        $sdpp_ratio_fix_css_path = SDPP_PLUGIN_DIR . 'public/css/ratio-fix.css';
        if (file_exists($sdpp_ratio_fix_css_path)) {
            $sdpp_ratio_fix_css_ver = (string) filemtime($sdpp_ratio_fix_css_path);
        }

        $sdpp_editor_js_path = SDPP_PLUGIN_DIR . 'public/js/editor.js';
        if (file_exists($sdpp_editor_js_path)) {
            // Aggressive cache busting for mobile/CDN caches: ensure editor.js is always refreshed.
            $sdpp_editor_js_ver = (string) filemtime($sdpp_editor_js_path) . '-' . (string) wp_rand(1000, 9999);
        }

        wp_enqueue_style('sdpp-editor', SDPP_PLUGIN_URL . 'public/css/editor.css', array('cropperjs'), $sdpp_editor_css_ver);
        wp_enqueue_style('sdpp-ratio-fix', SDPP_PLUGIN_URL . 'public/css/ratio-fix.css', array('sdpp-editor'), $sdpp_ratio_fix_css_ver);

        // Editor JS
        wp_enqueue_script('sdpp-editor', SDPP_PLUGIN_URL . 'public/js/editor.js', array('jquery', 'cropperjs'), $sdpp_editor_js_ver, true);

        // Custom PNG emojis are loaded statically in editor.php, no external script needed

        wp_localize_script('sdpp-editor', 'sdppEditor', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sdpp_editor_nonce'),
            'fonts' => $fonts->get_fonts_list(),
            'pluginUrl' => SDPP_PLUGIN_URL,
            'i18n' => array(
                'maxPhotos' => __('Máximo de %d fotos permitido.', 'polaroids-customizadas'),
                'deleteSelectedConfirm' => __('Deletar %d fotos selecionadas?', 'polaroids-customizadas'),
                'deletePhotoConfirm' => __('Deletar esta foto?', 'polaroids-customizadas'),
                'applyAllConfirm' => __('Deseja aplicar os textos e estilos da foto atual para TODAS as outras fotos? Isso substituirá os textos existentes.', 'polaroids-customizadas'),
                'applyAllSuccess' => __('Textos e estilos aplicados a todas as fotos!', 'polaroids-customizadas'),
                'addTextError' => __('Adicione ao menos um texto para aplicar a todos.', 'polaroids-customizadas'),
                'uploadingPhoto' => __('Enviando foto %1$d de %2$d...', 'polaroids-customizadas'),
                'savingOrder' => __('Salvando pedido...', 'polaroids-customizadas'),
                'submitError' => __('Falha ao enviar pedido. Por favor tente novamente.', 'polaroids-customizadas'),
                'uploadFailed' => __('Falha no envio', 'polaroids-customizadas'),
                'saveFailed' => __('Falha ao salvar', 'polaroids-customizadas'),
                'morePhotosNeeded' => __('Faltam %d fotos para completar', 'polaroids-customizadas'),
                'readyToUpload' => __('Pronto para enviar', 'polaroids-customizadas'),
                'allPhotosUploaded' => __('Todas as fotos enviadas', 'polaroids-customizadas'),
                'photoLabel' => __('Foto %d', 'polaroids-customizadas'),
                'selectAll' => __('Selecionar Tudo', 'polaroids-customizadas'),
                'deselectAll' => __('Desmarcar Tudo', 'polaroids-customizadas'),
                'newText' => __('Novo Texto', 'polaroids-customizadas'),
                'textLayers' => __('Camadas de Texto', 'polaroids-customizadas'),
                'add' => __('+ Adicionar', 'polaroids-customizadas'),
                'textPlaceholder' => __('Digite seu texto...', 'polaroids-customizadas'),
                'font' => __('Fonte', 'polaroids-customizadas'),
                'color' => __('Cor', 'polaroids-customizadas'),
                'size' => __('Tamanho', 'polaroids-customizadas'),
                'rotation' => __('Rotação', 'polaroids-customizadas'),
                'emoji' => __('😊 Emoji', 'polaroids-customizadas'),
                'fileFormatError' => __('Formato não suportado. Use JPG, PNG, WEBP ou HEIC.', 'polaroids-customizadas'),
                'maxPhotosExceeded' => __('Você excedeu o limite de fotos para este pedido.', 'polaroids-customizadas'),
                'selectMorePhotos' => __('Selecione pelo menos 2 fotos para aplicar estilos.', 'polaroids-customizadas'),
                'noTextToApply' => __('A seleção não tem estilo de texto para aplicar.', 'polaroids-customizadas'),
                'styleApplied' => __('Estilo aplicado a %d fotos.', 'polaroids-customizadas'),
                'undo' => __('Desfazer', 'polaroids-customizadas'),
                'redo' => __('Refazer', 'polaroids-customizadas'),
                'partialDuplicationLimit' => __('Algumas fotos não foram duplicadas porque o limite de %d fotos foi atingido.', 'polaroids-customizadas')
            )
        ));
    }

    /**
     * Get stores configuration
     */
    public function get_stores_config()
    {
        return array(
            'gran_shop' => array(
                'name' => 'Gran Shop',
                'footer_color' => '#000000',
                'footer_font' => 'Montserrat'
            ),
            'aisheel_mix' => array(
                'name' => 'Aisheel Mix',
                'footer_color' => '#047bc4',
                'footer_font' => 'Montserrat'
            )
        );
    }

    /**
     * Render order list page
     */
    public function render_order_list_page()
    {
        include SDPP_PLUGIN_DIR . 'admin/views/order-list.php';
    }

    /**
     * Render new order page
     */
    public function render_new_order_page()
    {
        $fonts = SDPP_Fonts::get_instance();
        $stores = $this->get_stores_config();
        include SDPP_PLUGIN_DIR . 'admin/views/new-order.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        include SDPP_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Render editor shortcode
     */
    public function render_editor_shortcode($atts)
    {
        // Secure logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
            if (!file_exists(dirname($log_file))) {
                wp_mkdir_p(dirname($log_file));
            }
            $time = date('Y-m-d H:i:s');
            error_log("[$time] [Shortcode] Called.", 3, $log_file);
        }

        // Check for cookie
        if (empty($_COOKIE['sdpp_order_token'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[$time] [Shortcode] Cookie sdpp_order_token is empty.", 3, $log_file);
            }
            return $this->render_login_view();
        }

        $token = sanitize_text_field($_COOKIE['sdpp_order_token']);

        // Validate token format
        if (strlen($token) !== 32) {
            setcookie('sdpp_order_token', '', time() - 3600, '/');
            return $this->render_login_view();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $safe_token = substr($token, 0, 8) . '***';
            error_log("[$time] [Shortcode] Token found: $safe_token", 3, $log_file);
        }

        $database = new SDPP_Database();
        $order = $database->get_order_by_token($token);

        if (!$order) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[$time] [Shortcode] Order NOT found for token.", 3, $log_file);
            }
            // Invalid cookie, clear it and show login
            setcookie('sdpp_order_token', '', time() - 3600, '/');
            return $this->render_login_view();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $safe_order_id = substr($order->order_id, 0, 3) . '***';
            error_log("[$time] [Shortcode] Order found: ID=$safe_order_id", 3, $log_file);
        }

        // Order valid, show editor
        $this->public_enqueue_scripts();

        // Prepare variables for the view
        $fonts = SDPP_Fonts::get_instance();
        $stores = $this->get_stores_config();

        ob_start();
        include SDPP_PLUGIN_DIR . 'public/views/editor.php';
        return ob_get_clean();
    }

    /**
     * Render login view
     */
    private function render_login_view()
    {
        ob_start();
        include SDPP_PLUGIN_DIR . 'public/views/login.php';
        return ob_get_clean();
    }

    /**
     * AJAX Login
     */
    public function ajax_login()
    {
        // Secure logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
            if (!file_exists(dirname($log_file))) {
                wp_mkdir_p(dirname($log_file));
            }
            $time = date('Y-m-d H:i:s');
            error_log("[$time] [AJAX Login] Started.", 3, $log_file);
        }

        check_ajax_referer('sdpp_login_nonce', 'nonce');

        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_key = 'sdpp_login_attempts_' . md5($ip);
        $attempts = get_transient($rate_key) ?: 0;

        if ($attempts >= 5) {
            wp_send_json_error(array('message' => __('Muitas tentativas de login. Tente novamente em 1 hora.', 'polaroids-customizadas')));
        }

        $order_id = sanitize_text_field($_POST['order_id'] ?? '');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $safe_order_id = substr($order_id, 0, 3) . '***';
            error_log("[$time] [AJAX Login] Order ID received: $safe_order_id", 3, $log_file);
        }

        if (empty($order_id) || strlen($order_id) > 100) {
            // Increment failed attempts
            set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[$time] [AJAX Login] Order ID is empty or invalid.", 3, $log_file);
            }
            wp_send_json_error(array('message' => __('Digite um código de pedido válido.', 'polaroids-customizadas')));
        }

        $database = new SDPP_Database();
        $order = $database->get_order_by_order_id($order_id);

        if (!$order) {
            // Increment failed attempts
            set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[$time] [AJAX Login] Order NOT found for ID: $safe_order_id", 3, $log_file);
            }
            wp_send_json_error(array('message' => __('Pedido não encontrado.', 'polaroids-customizadas')));
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[$time] [AJAX Login] Order found. Setting secure cookie.", 3, $log_file);
        }

        // Clear failed attempts on successful login
        delete_transient($rate_key);

        // Set secure cookie
        $token = $order->access_token;

        // Enhanced cookie security
        $cookie_options = array(
            'expires' => time() + DAY_IN_SECONDS,
            'path' => '/',
            'domain' => '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict'
        );

        $cookie_result = setcookie('sdpp_order_token', $token, $cookie_options);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[$time] [AJAX Login] setcookie result: " . ($cookie_result ? 'true' : 'false'), 3, $log_file);
        }

        wp_send_json_success(array('message' => 'Login success'));
    }

    /**
     * AJAX Logout
     */
    public function ajax_logout()
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
            error_log("[" . date('Y-m-d H:i:s') . "] [AJAX Logout] Called.", 3, $log_file);
        }

        // Ensure no output has been sent
        if (headers_sent()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[" . date('Y-m-d H:i:s') . "] [AJAX Logout] Headers already sent!", 3, $log_file);
            }
        }
        
        // Clear any previous output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        $common_options = array(
            'expires' => time() - 3600,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict'
        );

        // Try clearing with exact matching options
        setcookie('sdpp_order_token', '', array_merge($common_options, array('path' => '/', 'domain' => '')));
        
        // Try clearing with default domain (just in case)
        setcookie('sdpp_order_token', '', array_merge($common_options, array('path' => '/')));

        // Try clearing with root domain if applicable
        if (isset($_SERVER['HTTP_HOST'])) {
             setcookie('sdpp_order_token', '', array_merge($common_options, array('path' => '/', 'domain' => $_SERVER['HTTP_HOST'])));
        }
        
        wp_send_json_success(array('message' => 'Logout success'));
    }

    /**
     * AJAX: Create new order
     */
    public function ajax_create_order()
    {
        ob_start();
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        // Validate and sanitize input data
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        $store = sanitize_text_field($_POST['store'] ?? '');
        $photo_quantity = intval($_POST['photo_quantity'] ?? 0);
        $grid_type = sanitize_text_field($_POST['grid_type'] ?? '3x3');
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');

        // Validation
        if (empty($order_id)) {
            wp_send_json_error(array('message' => __('ID do pedido é obrigatório.', 'polaroids-customizadas')));
        }

        if (strlen($order_id) > 100) {
            wp_send_json_error(array('message' => __('ID do pedido muito longo.', 'polaroids-customizadas')));
        }

        $allowed_stores = array_keys($this->get_stores_config());
        if (!in_array($store, $allowed_stores)) {
            wp_send_json_error(array('message' => __('Loja inválida.', 'polaroids-customizadas')));
        }

        if ($photo_quantity < 1 || $photo_quantity > 500) {
            wp_send_json_error(array('message' => __('Quantidade de fotos deve estar entre 1 e 500.', 'polaroids-customizadas')));
        }

        $allowed_grid_types = array('3x3', '2x3');
        if (!in_array($grid_type, $allowed_grid_types)) {
            wp_send_json_error(array('message' => __('Tipo de grade inválido.', 'polaroids-customizadas')));
        }

        $data = array(
            'order_id' => $order_id,
            'store' => $store,
            'photo_quantity' => $photo_quantity,
            'grid_type' => $grid_type,
            'has_border' => intval($_POST['has_border'] ?? 0),
            'has_magnet' => intval($_POST['has_magnet'] ?? 0),
            'has_clip' => intval($_POST['has_clip'] ?? 0),
            'has_twine' => intval($_POST['has_twine'] ?? 0),
            'has_frame' => intval($_POST['has_frame'] ?? 0),
            'customer_name' => $customer_name
        );

        $database = new SDPP_Database();
        $result = $database->create_order($data);

        $output_buffer = ob_get_clean();
        if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SDPP Output Buffer Leaked (ajax_create_order): " . $output_buffer);
        }

        if ($result && !is_wp_error($result)) {
            wp_send_json_success(array(
                'message' => __('Pedido criado com sucesso.', 'polaroids-customizadas'),
                'order' => $result
            ));
        } else {
            $error_message = __('Falha ao criar pedido.', 'polaroids-customizadas');
            if (is_wp_error($result)) {
                $error_message .= ' ' . $result->get_error_message();
            }
            wp_send_json_error(array('message' => $error_message));
        }
    }

    /**
     * Check if user can access order
     */
    private function user_can_access_order($order_id)
    {
        // Admin users can access all orders
        if (current_user_can('manage_options')) {
            return true;
        }

        // For frontend users, check cookie token
        if (empty($_COOKIE['sdpp_order_token'])) {
            return false;
        }

        $token = sanitize_text_field($_COOKIE['sdpp_order_token']);
        $database = new SDPP_Database();
        $order = $database->get_order_by_token($token);

        return $order && $order->id == $order_id;
    }

    /**
     * AJAX: Save photos
     */
    public function ajax_save_photos()
    {
        ob_start();
        // Log start
        $log_file = WP_CONTENT_DIR . '/debug/sdpp_debug.log';
        if (!file_exists(dirname($log_file))) {
            wp_mkdir_p(dirname($log_file));
        }
        error_log("[AJAX Save Photos] Started.", 3, $log_file);

        check_ajax_referer('sdpp_editor_nonce', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        $raw_photos = $_POST['photos'] ?? '';

        error_log("[AJAX Save Photos] Order ID: $order_id. Payload Size: " . strlen($raw_photos) . " bytes.", 3, $log_file);

        if (!$order_id) {
            error_log("[AJAX Save Photos] Invalid Order ID.", 3, $log_file);
            wp_send_json_error(array('message' => __('ID do pedido inválido.', 'polaroids-customizadas')));
        }

        // Verify user has access to this order
        if (!$this->user_can_access_order($order_id)) {
            error_log("[AJAX Save Photos] Access denied for order $order_id.", 3, $log_file);
            wp_send_json_error(array('message' => __('Acesso negado.', 'polaroids-customizadas')));
        }

        $photos = isset($_POST['photos']) ? json_decode(stripslashes($_POST['photos']), true) : array();

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[AJAX Save Photos] JSON Decode Error: " . json_last_error_msg(), 3, $log_file);
            wp_send_json_error(array('message' => __('Dados inválidos.', 'polaroids-customizadas')));
        }

        error_log("[AJAX Save Photos] Decoded photos count: " . count($photos), 3, $log_file);

        // Validate photos data
        foreach ($photos as $index => $photo) {
            if (!is_array($photo)) {
                error_log("[AJAX Save Photos] Invalid photo data at index $index.", 3, $log_file);
                wp_send_json_error(array('message' => __('Dados de foto inválidos.', 'polaroids-customizadas')));
            }

            // Sanitize photo data
            $photos[$index]['image_path'] = sanitize_text_field($photo['image_path'] ?? '');
            $photos[$index]['image_url'] = esc_url_raw($photo['image_url'] ?? '');
            $photos[$index]['font_family'] = sanitize_text_field($photo['font_family'] ?? 'Pacifico');
        }

        $database = new SDPP_Database();
        $result = $database->save_photos($order_id, $photos);

        $output_buffer = ob_get_clean();
        if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SDPP Output Buffer Leaked (ajax_save_photos): " . $output_buffer);
        }

        if ($result) {
            error_log("[AJAX Save Photos] Success.", 3, $log_file);

            // Send Admin Notification
            $order = $database->get_order($order_id);
            if ($order) {
                $order_data = (array) $order;
                $this->send_admin_notification($order_data);
                error_log("[AJAX Save Photos] Admin notification sent to " . get_option('sdpp_admin_email'), 3, $log_file);
            }

            wp_send_json_success(array('message' => __('Fotos salvas com sucesso.', 'polaroids-customizadas')));
        } else {
            error_log("[AJAX Save Photos] DB Save Failed.", 3, $log_file);
            wp_send_json_error(array('message' => __('Falha ao salvar fotos.', 'polaroids-customizadas')));
        }
    }

    /**
     * AJAX: Generate PNG
     */
    public function ajax_generate_png()
    {
        // Start output buffering to catch any unexpected output/warnings
        ob_start();

        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            ob_end_clean(); // Discard buffer
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('ID do pedido inválido.', 'polaroids-customizadas')));
        }

        // Verify order exists
        $database = new SDPP_Database();
        $order = $database->get_order($order_id);
        if (!$order) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('Pedido não encontrado.', 'polaroids-customizadas')));
        }


        $generator = new SDPP_Image_Generator();
        $result = $generator->generate($order_id);


        // Clean buffer before sending response
        $output_buffer = ob_get_clean();

        // Optionally log buffer if not empty for debugging
        if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SDPP Output Buffer Leaked: " . $output_buffer);
        }

        if ($result && !is_wp_error($result)) {
            // Update status to completed automatically
            $database->update_order_status($order_id, 'completed');

            wp_send_json_success(array(
                'message' => __('PNG gerado com sucesso. Status atualizado para Concluído.', 'polaroids-customizadas'),
                'url' => $result,
                'new_status' => 'completed',
                'new_status_label' => __('Concluído', 'polaroids-customizadas')
            ));
        } else {
            $msg = is_wp_error($result) ? $result->get_error_message() : __('Falha desconhecida ao gerar PNG.', 'polaroids-customizadas');
            wp_send_json_error(array('message' => $msg));
        }
    }

    public function ajax_get_png_progress()
    {
        ob_start();
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('ID do pedido inválido.', 'polaroids-customizadas')));
        }

        $key = 'sdpp_png_progress_' . $order_id;
        $progress = get_transient($key);
        $percent = 0;
        $status = 'idle';

        if (is_array($progress)) {
            $percent = isset($progress['percent']) ? intval($progress['percent']) : 0;
            $status = isset($progress['status']) ? strval($progress['status']) : 'idle';
        }

        ob_end_clean();
        wp_send_json_success(array(
            'percent' => max(0, min(100, $percent)),
            'status' => $status
        ));
    }

    /**
     * AJAX: Upload single photo
     */
    public function ajax_upload_photo()
    {
        ob_start();
        
        // Debug logging
        $log_file = WP_CONTENT_DIR . '/debug/sdpp_upload_debug.log';
        if (!file_exists(dirname($log_file))) {
            wp_mkdir_p(dirname($log_file));
        }
        $log = function($msg) use ($log_file) {
            error_log("[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", 3, $log_file);
        };
        
        $log("=== Upload started ===");
        $log("POST order_id: " . ($_POST['order_id'] ?? 'missing'));
        $log("FILES: " . print_r(array_keys($_FILES ?? []), true));
        
        check_ajax_referer('sdpp_editor_nonce', 'nonce');
        $log("Nonce check passed");

        $order_id = intval($_POST['order_id'] ?? 0);
        $image_data = $_POST['image_data'] ?? '';
        $has_upload = !empty($_FILES['image_file']) && isset($_FILES['image_file']['tmp_name']) && is_uploaded_file($_FILES['image_file']['tmp_name']);
        
        $log("order_id: $order_id, has_upload: " . ($has_upload ? 'yes' : 'no'));

        if (!$order_id || (!$has_upload && empty($image_data))) {
            $log("Failed: Invalid data");
            wp_send_json_error(array('message' => __('Dados inválidos.', 'polaroids-customizadas')));
        }

        // Verify user has access to this order
        if (!$this->user_can_access_order($order_id)) {
            $log("Failed: Access denied for order $order_id");

            wp_send_json_error(array('message' => __('Acesso negado.', 'polaroids-customizadas')));
        }


        // Upload directory with proper validation
        $upload_dir = wp_upload_dir();
        $order_dir = $upload_dir['basedir'] . '/polaroid-uploads/' . $order_id;

        // Validate order directory path
        $real_order_dir = realpath($upload_dir['basedir'] . '/polaroid-uploads');
        if (!$real_order_dir || !file_exists($order_dir)) {
            if (!wp_mkdir_p($order_dir)) {
                wp_send_json_error(array('message' => __('Erro ao criar diretório.', 'polaroids-customizadas')));
            }
        }


        $detected_mime = '';
        $final_path = '';
        $final_filename = '';

        if ($has_upload) {
            // Multipart upload path (preferred)
            $tmp_path = $_FILES['image_file']['tmp_name'];

            if (!empty($_FILES['image_file']['error']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(array('message' => __('Erro no upload do arquivo.', 'polaroids-customizadas')));
            }

            // Allow larger uploads for print quality
            $max_bytes = 50 * 1024 * 1024;
            if (!empty($_FILES['image_file']['size']) && intval($_FILES['image_file']['size']) > $max_bytes) {
                wp_send_json_error(array('message' => __('Arquivo muito grande. Máximo 50MB.', 'polaroids-customizadas')));
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $tmp_path);
            finfo_close($finfo);

            $allowed_mimes = array('image/jpeg', 'image/png', 'image/webp');
            if (!in_array($detected_mime, $allowed_mimes)) {
                wp_send_json_error(array('message' => __('Tipo de arquivo não suportado.', 'polaroids-customizadas')));
            }

            $extension_map = array(
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            );
            $extension = $extension_map[$detected_mime] ?? 'png';

            $final_filename = 'photo-' . wp_generate_password(12, false) . '.' . $extension;
            $final_path = $order_dir . '/' . $final_filename;

            if (!move_uploaded_file($tmp_path, $final_path)) {
                wp_send_json_error(array('message' => __('Erro ao salvar arquivo.', 'polaroids-customizadas')));
            }

            // Optional: store the original HEIC as reference (not used for composition directly)
            if (!empty($_FILES['original_file']) && isset($_FILES['original_file']['tmp_name']) && is_uploaded_file($_FILES['original_file']['tmp_name'])) {
                if (empty($_FILES['original_file']['error']) || $_FILES['original_file']['error'] === UPLOAD_ERR_OK) {
                    $orig_name = sanitize_file_name($_FILES['original_file']['name'] ?? 'original.heic');
                    $orig_ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                    if ($orig_ext === 'heic' || $orig_ext === 'heif') {
                        $orig_filename = 'original-' . wp_generate_password(12, false) . '.' . $orig_ext;
                        $orig_path = $order_dir . '/' . $orig_filename;
                        @move_uploaded_file($_FILES['original_file']['tmp_name'], $orig_path);
                    }
                }
            }

        } else {
            // Legacy base64 upload path (kept for compatibility)
            $image_parts = explode(";base64,", $image_data);
            if (count($image_parts) < 2) {
                wp_send_json_error(array('message' => __('Dados de imagem inválidos.', 'polaroids-customizadas')));
            }

            $mime_header = $image_parts[0];
            if (!preg_match('/^data:image\/(jpeg|jpg|png|webp)$/', $mime_header)) {
                wp_send_json_error(array('message' => __('Tipo de arquivo não suportado.', 'polaroids-customizadas')));
            }

            $image_base64 = base64_decode($image_parts[1]);
            if (strlen($image_base64) > 50 * 1024 * 1024) {
                wp_send_json_error(array('message' => __('Arquivo muito grande. Máximo 50MB.', 'polaroids-customizadas')));
            }

            $filename = 'photo-' . wp_generate_password(12, false) . '.tmp';
            $file_path = $order_dir . '/' . $filename;

            if (file_put_contents($file_path, $image_base64) === false) {
                wp_send_json_error(array('message' => __('Erro ao salvar arquivo.', 'polaroids-customizadas')));
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = finfo_file($finfo, $file_path);
            finfo_close($finfo);

            $allowed_mimes = array('image/jpeg', 'image/png', 'image/webp');
            if (!in_array($detected_mime, $allowed_mimes)) {
                unlink($file_path);
                wp_send_json_error(array('message' => __('Arquivo inválido detectado.', 'polaroids-customizadas')));
            }

            $extension_map = array(
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            );
            $extension = $extension_map[$detected_mime];

            $final_filename = 'photo-' . wp_generate_password(12, false) . '.' . $extension;
            $final_path = $order_dir . '/' . $final_filename;

            if (!rename($file_path, $final_path)) {
                unlink($file_path);
                wp_send_json_error(array('message' => __('Erro ao processar arquivo.', 'polaroids-customizadas')));
            }
        }

        $file_url = $upload_dir['baseurl'] . '/polaroid-uploads/' . $order_id . '/' . $final_filename;

        $output_buffer = ob_get_clean();
        if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SDPP Output Buffer Leaked (ajax_upload_photo): " . $output_buffer);
        }

        // Return photo data
        wp_send_json_success(array(
            'image_path' => $final_path,
            'image_url' => $file_url,
            'text' => wp_kses_post(wp_unslash($_POST['text'] ?? '')),
            'font_family' => sanitize_text_field($_POST['font_family'] ?? ''),
            'crop_x' => 0,
            'crop_y' => 0,
            'crop_width' => 0,
            'crop_height' => 0,
            'zoom' => 1
        ));
    }

    /**
     * AJAX: Delete order
     */
    public function ajax_delete_order()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id) {
            wp_send_json_error(array('message' => __('ID do pedido inválido.', 'polaroids-customizadas')));
        }

        $database = new SDPP_Database();
        $result = $database->delete_order($order_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Pedido excluído com sucesso.', 'polaroids-customizadas')));
        } else {
            wp_send_json_error(array('message' => __('Falha ao excluir pedido.', 'polaroids-customizadas')));
        }
    }
    /**
     * AJAX: Update order
     */
    public function ajax_update_order()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $order_id = intval($_POST['id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(array('message' => __('ID do pedido inválido.', 'polaroids-customizadas')));
        }

        $data = array(
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? ''),
            'store' => sanitize_text_field($_POST['store'] ?? ''),
            // Add other fields as necessary for editing
        );

        $database = new SDPP_Database();
        // We need to implement update_order in Database class too? 
        // Or we can use $wpdb->update directly here? 
        // Best practice: use Database class. But I don't know if update_order exists.
        // I will assume I need to check Database class or implementing it there.
        // For now, let's assume I can add it or it uses generic update.
        // Checking class-database.php is needed.

        // Let's hold on this replacement until I check class-database.php.
        // Actually, I can just use $database->update_order($order_id, $data) and implementing it in the next step.

        $result = $database->update_order($order_id, $data);

        if ($result !== false) {
            wp_send_json_success(array('message' => __('Pedido atualizado com sucesso.', 'polaroids-customizadas')));
        } else {
            wp_send_json_error(array('message' => __('Falha ao atualizar pedido.', 'polaroids-customizadas')));
        }
    }

    /**
     * AJAX: Get order details
     */
    public function ajax_get_order_details()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(array('message' => __('ID do pedido inválido.', 'polaroids-customizadas')));
        }

        $database = new SDPP_Database();
        $order = $database->get_order($order_id);

        if ($order) {
            wp_send_json_success(array('order' => $order));
        } else {
            wp_send_json_error(array('message' => __('Pedido não encontrado.', 'polaroids-customizadas')));
        }
    }

    /**
     * AJAX: Bulk Delete Orders
     */
    public function ajax_bulk_delete()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $order_ids = array_map('intval', $_POST['order_ids'] ?? array());

        if (empty($order_ids)) {
            wp_send_json_error(array('message' => __('Nenhum pedido selecionado.', 'polaroids-customizadas')));
        }

        $database = new SDPP_Database();
        $deleted = 0;

        foreach ($order_ids as $id) {
            if ($database->delete_order($id)) {
                $deleted++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('%d pedidos excluídos com sucesso.', 'polaroids-customizadas'), $deleted)
        ));
    }

    public function ajax_bulk_generate_zip()
    {
        ob_start();
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        if (!class_exists('ZipArchive')) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('A extensão ZipArchive do PHP não está instalada.', 'polaroids-customizadas')));
        }

        $order_ids = array_map('intval', $_POST['order_ids'] ?? array());
        $order_ids = array_values(array_filter($order_ids));

        if (empty($order_ids)) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('Nenhum pedido selecionado.', 'polaroids-customizadas')));
        }

        if (count($order_ids) > 5) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('Selecione no máximo 5 pedidos por vez para imprimir.', 'polaroids-customizadas')));
        }

        $job_id = sanitize_text_field($_POST['job_id'] ?? '');
        $job_key = '';
        if (!empty($job_id)) {
            $job_key = 'sdpp_bulk_job_' . $job_id;
        }

        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $upload_dir = wp_upload_dir();
        $bulk_dir = $upload_dir['basedir'] . '/polaroid-bulk-outputs';

        if (!file_exists($bulk_dir)) {
            if (!wp_mkdir_p($bulk_dir)) {
                ob_end_clean();
                wp_send_json_error(array('message' => __('Falha ao criar diretório de saída.', 'polaroids-customizadas')));
            }
        }

        $old_files = glob($bulk_dir . '/*.zip');
        if ($old_files) {
            foreach ($old_files as $file) {
                if (is_file($file) && time() - filemtime($file) > 3600) {
                    @unlink($file);
                }
            }
        }

        $database = new SDPP_Database();
        if (empty($job_id)) {
            $job_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('sdpp_', true);
            $job_key = 'sdpp_bulk_job_' . $job_id;

            $job = array(
                'order_ids' => $order_ids,
                'index' => 0,
                'results' => array(),
                'status' => 'running',
                'created_at' => time(),
                'fileurl' => ''
            );

            set_transient($job_key, $job, HOUR_IN_SECONDS);

            $output_buffer = ob_get_clean();
            if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SDPP Output Buffer Leaked (ajax_bulk_generate_zip start): " . $output_buffer);
            }

            wp_send_json_success(array(
                'job_id' => $job_id,
                'status' => 'running',
                'percent' => 0
            ));
        }

        $job = get_transient($job_key);
        if (empty($job) || !is_array($job)) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('Job expirado. Tente novamente.', 'polaroids-customizadas')));
        }

        if (($job['status'] ?? '') === 'done' && !empty($job['fileurl'])) {
            $output_buffer = ob_get_clean();
            if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SDPP Output Buffer Leaked (ajax_bulk_generate_zip done): " . $output_buffer);
            }

            wp_send_json_success(array(
                'url' => $job['fileurl'],
                'status' => 'done',
                'percent' => 100
            ));
        }

        $order_ids = array_map('intval', $job['order_ids'] ?? array());
        $order_ids = array_values(array_filter($order_ids));
        $total = count($order_ids);
        $index = intval($job['index'] ?? 0);

        if ($total < 1) {
            delete_transient($job_key);
            ob_end_clean();
            wp_send_json_error(array('message' => __('Nenhum pedido selecionado.', 'polaroids-customizadas')));
        }

        if ($index < $total) {
            $id = intval($order_ids[$index]);
            $order = $database->get_order($id);

            if (!$order) {
                $job['results'][] = array('id' => $id, 'status' => 'ERROR', 'message' => 'Pedido não encontrado');
            } else {
                $generator = new SDPP_Image_Generator();
                $result = $generator->generate($id);
                unset($generator);

                if (!$result || is_wp_error($result)) {
                    $msg = is_wp_error($result) ? $result->get_error_message() : 'Falha ao gerar arquivo';
                    $job['results'][] = array('id' => $id, 'status' => 'ERROR', 'message' => $msg);
                } else {
                    $rel = str_replace($upload_dir['baseurl'], '', $result);
                    $zip_path = rawurldecode($upload_dir['basedir'] . $rel);
                    if (!file_exists($zip_path)) {
                        $job['results'][] = array('id' => $id, 'status' => 'ERROR', 'message' => 'Arquivo gerado não encontrado no servidor');
                    } else {
                        $order_id_str = isset($order->order_id) ? strval($order->order_id) : strval($id);
                        $entry = sanitize_file_name($order_id_str) . '-' . $id . '.zip';
                        $job['results'][] = array('id' => $id, 'status' => 'OK', 'path' => $zip_path, 'entry' => $entry);
                        $database->update_order_status($id, 'completed');
                    }
                }
            }

            $job['index'] = $index + 1;
            $job['status'] = 'running';
            set_transient($job_key, $job, HOUR_IN_SECONDS);

            $percent = intval(floor((($job['index']) / $total) * 100));

            $output_buffer = ob_get_clean();
            if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log("SDPP Output Buffer Leaked (ajax_bulk_generate_zip step): " . $output_buffer);
            }

            wp_send_json_success(array(
                'job_id' => $job_id,
                'status' => 'running',
                'percent' => max(0, min(99, $percent))
            ));
        }

        $filename = 'pedidos-selecionados-' . date('Y-m-d-H-i-s') . '-' . sanitize_file_name($job_id) . '.zip';
        $filepath = $bulk_dir . '/' . $filename;
        $fileurl = $upload_dir['baseurl'] . '/polaroid-bulk-outputs/' . rawurlencode($filename);

        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            ob_end_clean();
            wp_send_json_error(array('message' => __('Falha ao criar ZIP em massa.', 'polaroids-customizadas')));
        }

        $info_lines = array();
        $success = 0;

        foreach (($job['results'] ?? array()) as $row) {
            $id = intval($row['id'] ?? 0);
            $status = strval($row['status'] ?? 'ERROR');

            if ($status === 'OK' && !empty($row['path']) && !empty($row['entry']) && file_exists($row['path'])) {
                if ($zip->addFile($row['path'], $row['entry'])) {
                    $success++;
                    $info_lines[] = $id . "\t" . 'OK' . "\t" . $row['entry'];
                } else {
                    $info_lines[] = $id . "\t" . 'ERROR' . "\t" . 'Falha ao adicionar ao ZIP em massa';
                }
            } else {
                $msg = strval($row['message'] ?? 'Falha');
                $info_lines[] = $id . "\t" . 'ERROR' . "\t" . $msg;
            }
        }

        if (!empty($info_lines)) {
            $zip->addFromString('info.txt', implode("\n", $info_lines) . "\n");
        }

        $zip->close();

        $output_buffer = ob_get_clean();
        if (!empty($output_buffer) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("SDPP Output Buffer Leaked (ajax_bulk_generate_zip): " . $output_buffer);
        }

        if ($success < 1) {
            $job['status'] = 'error';
            set_transient($job_key, $job, HOUR_IN_SECONDS);
            wp_send_json_error(array('message' => __('Nenhum pedido pôde ser gerado.', 'polaroids-customizadas')));
        }

        $job['status'] = 'done';
        $job['fileurl'] = $fileurl;
        set_transient($job_key, $job, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'url' => $fileurl,
            'status' => 'done',
            'percent' => 100
        ));
    }

    /**
     * AJAX: Get Metrics (Counts)
     */
    public function ajax_get_metrics()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $database = new SDPP_Database();

        $metrics = array(
            'all' => $database->get_orders_count(array('status' => '')),
            'pending' => $database->get_orders_count(array('status' => 'pending')),
            'uploaded' => $database->get_orders_count(array('status' => 'photos_uploaded')),
            'completed' => $database->get_orders_count(array('status' => 'completed')),
            'trash' => $database->get_orders_count(array('status' => 'trash'))
        );

        // Format with number_format_i18n
        foreach ($metrics as $key => $value) {
            $metrics[$key] = number_format_i18n($value);
        }

        wp_send_json_success($metrics);
    }

    /**
     * AJAX handler for saving email settings
     */
    public function ajax_save_email_settings()
    {
        check_ajax_referer('sdpp_save_email_settings', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        // Save settings
        $enable = (isset($_POST['enable_notifications']) && $_POST['enable_notifications'] == '1') ? '1' : '0';
        update_option('sdpp_enable_email_notifications', $enable);
        update_option('sdpp_admin_email', sanitize_email($_POST['admin_email'] ?? ''));
        update_option('sdpp_admin_email_subject', sanitize_text_field($_POST['admin_email_subject'] ?? ''));
        update_option('sdpp_admin_email_body', wp_kses_post($_POST['admin_email_body'] ?? ''));
        update_option('sdpp_customer_email_subject', sanitize_text_field($_POST['customer_email_subject'] ?? ''));
        update_option('sdpp_customer_email_body', wp_kses_post($_POST['customer_email_body'] ?? ''));

        // Save SMTP Settings
        $enable_smtp = (isset($_POST['enable_smtp']) && $_POST['enable_smtp'] == '1') ? '1' : '0';
        update_option('sdpp_enable_smtp', $enable_smtp);
        update_option('sdpp_smtp_host', sanitize_text_field($_POST['smtp_host'] ?? ''));
        update_option('sdpp_smtp_port', sanitize_text_field($_POST['smtp_port'] ?? ''));
        update_option('sdpp_smtp_user', sanitize_email($_POST['smtp_user'] ?? ''));

        // Only update password if provided (don't clear if empty on save, potentially) 
        // Actually, for simplicity, if empty we assume unchanged if masking is used, but here standard input.
        // Let's safe update.
        if (!empty($_POST['smtp_pass'])) {
            update_option('sdpp_smtp_pass', sanitize_text_field($_POST['smtp_pass']));
        }

        update_option('sdpp_smtp_from_name', sanitize_text_field($_POST['smtp_from_name'] ?? ''));
        update_option('sdpp_smtp_encryption', sanitize_text_field($_POST['smtp_encryption'] ?? ''));

        wp_send_json_success(array('message' => __('Configurações salvas com sucesso.', 'polaroids-customizadas')));
    }

    /**
     * AJAX: Send Test Email
     */
    public function ajax_send_test_email()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        $to = sanitize_email($_POST['email'] ?? '');
        if (!is_email($to)) {
            wp_send_json_error(array('message' => __('Email inválido.', 'polaroids-customizadas')));
        }

        $subject = '[Teste SMTP] Polaroids Customizadas';
        $message = "Olá,\n\nEste é um email de teste para verificar as configurações SMTP do plugin.\n\nSe você recebeu este email, sua configuração está correta.\n\nEnviado em: " . current_time('d/m/Y H:i:s');
        
        // Force header to ensure text/plain
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $result = wp_mail($to, $subject, $message, $headers);

        if ($result) {
            wp_send_json_success(array('message' => __('Email de teste enviado com sucesso!', 'polaroids-customizadas')));
        } else {
            // Try to capture PHPMailer error info if available
            global $phpmailer;
            $error_details = '';
            if (isset($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                $error_details = ': ' . $phpmailer->ErrorInfo;
            }
            wp_send_json_error(array('message' => __('Falha ao enviar email', 'polaroids-customizadas') . $error_details));
        }
    }

    /**
     * AJAX: Export Backup
     */
    public function ajax_export_backup()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        if (!class_exists('ZipArchive')) {
            wp_send_json_error(array('message' => __('A extensão ZipArchive do PHP não está instalada.', 'polaroids-customizadas')));
        }

        // Increase limits
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/polaroid-backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        // Cleanup old backups (older than 1 hour)
        $files = glob($backup_dir . '/*.zip');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file) && time() - filemtime($file) > 3600) {
                    unlink($file);
                }
            }
        }

        $filename = 'polaroids-backup-' . date('Y-m-d-H-i-s') . '.zip';
        $filepath = $backup_dir . '/' . $filename;
        $fileurl = $upload_dir['baseurl'] . '/polaroid-backups/' . $filename;

        // Fetch data
        $database = new SDPP_Database();
        $orders = $database->get_orders(array('limit' => 10000));

        $backup_data = array(
            'version' => SDPP_VERSION,
            'exported_at' => date('c'),
            'orders' => array()
        );

        global $wpdb;
        $photos_table = $wpdb->prefix . 'polaroid_photos';

        foreach ($orders as $order) {
            $order_data = (array) $order;

            // Get photos for this order
            $photos = $wpdb->get_results($wpdb->prepare("SELECT * FROM $photos_table WHERE order_ref_id = %d", $order->id), ARRAY_A);
            $order_data['photos'] = $photos;

            $backup_data['orders'][] = $order_data;
        }

        // Create ZIP
        $zip = new ZipArchive();
        if ($zip->open($filepath, ZipArchive::CREATE) !== TRUE) {
            wp_send_json_error(array('message' => __('Não foi possível criar o arquivo ZIP.', 'polaroids-customizadas')));
        }

        // Add JSON data
        $zip->addFromString('data.json', json_encode($backup_data, JSON_PRETTY_PRINT));

        // Add images
        $source_base = $upload_dir['basedir'] . '/polaroid-uploads/';

        // Find all files in polaroid-uploads
        if (is_dir($source_base)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $name => $file) {
                if (!$file->isFile())
                    continue;

                $real_path = $file->getRealPath();

                // Get relative path
                $relative_path = substr($real_path, strlen(realpath($source_base)));

                // Ensure no leading slashes/backslashes
                $relative_path = ltrim($relative_path, '/\\');

                // Normalize windows paths
                $relative_path = str_replace('\\', '/', $relative_path);

                $zip->addFile($real_path, 'uploads/' . $relative_path);
            }
        }

        $zip->close();

        if (file_exists($filepath)) {
            wp_send_json_success(array(
                'message' => __('Backup criado e pronto para download!', 'polaroids-customizadas'),
                'url' => $fileurl
            ));
        } else {
            wp_send_json_error(array('message' => __('Erro ao salvar o arquivo de backup.', 'polaroids-customizadas')));
        }
    }

    /**
     * AJAX: Import Backup
     */
    public function ajax_import_backup()
    {
        check_ajax_referer('sdpp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'polaroids-customizadas')));
        }

        if (empty($_FILES['backup_file'])) {
            wp_send_json_error(array('message' => __('Nenhum arquivo enviado.', 'polaroids-customizadas')));
        }

        $file = $_FILES['backup_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('Erro no upload do arquivo.', 'polaroids-customizadas')));
        }

        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'zip') {
            wp_send_json_error(array('message' => __('Arquivo inválido. Envie um arquivo ZIP.', 'polaroids-customizadas')));
        }

        if (!class_exists('ZipArchive')) {
            wp_send_json_error(array('message' => __('A extensão ZipArchive do PHP não está instalada.', 'polaroids-customizadas')));
        }

        // Increase limits
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/polaroid-backups/temp_' . uniqid();

        if (!file_exists($upload_dir['basedir'] . '/polaroid-backups')) {
            wp_mkdir_p($upload_dir['basedir'] . '/polaroid-backups');
        }

        if (!mkdir($temp_dir)) {
            wp_send_json_error(array('message' => __('Não foi possível criar diretório temporário.', 'polaroids-customizadas')));
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) === TRUE) {
            $zip->extractTo($temp_dir);
            $zip->close();
        } else {
            wp_send_json_error(array('message' => __('Falha ao abrir arquivo ZIP.', 'polaroids-customizadas')));
        }

        // Process data.json
        $json_file = $temp_dir . '/data.json';
        if (!file_exists($json_file)) {
            wp_send_json_error(array('message' => __('Arquivo data.json não encontrado no backup.', 'polaroids-customizadas')));
        }

        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);

        if (!$data || !isset($data['orders'])) {
            wp_send_json_error(array('message' => __('Dados do backup inválidos.', 'polaroids-customizadas')));
        }

        $database = new SDPP_Database();
        global $wpdb;

        $imported_orders = 0;
        $imported_photos = 0;

        foreach ($data['orders'] as $order_data) {
            // Check if order exists
            $existing_order = $database->get_order_by_order_id($order_data['order_id']);
            $order_db_id = 0;

            $db_data = array(
                'order_id' => $order_data['order_id'],
                'store' => $order_data['store'],
                'photo_quantity' => $order_data['photo_quantity'],
                'has_border' => $order_data['has_border'],
                'has_magnet' => $order_data['has_magnet'],
                'has_clip' => $order_data['has_clip'],
                'has_twine' => $order_data['has_twine'],
                'has_frame' => $order_data['has_frame'],
                'customer_name' => $order_data['customer_name'],
                'customer_email' => $order_data['customer_email'],
                'status' => $order_data['status'],
                'access_token' => $order_data['access_token'], // Restore token to keep login links working
                'grid_type' => isset($order_data['grid_type']) ? $order_data['grid_type'] : '3x3',
                'created_at' => $order_data['created_at']
            );

            if ($existing_order) {
                // Update existing
                $order_db_id = $existing_order->id;
                $wpdb->update($wpdb->prefix . 'polaroid_orders', $db_data, array('id' => $order_db_id));
            } else {
                // Insert new
                $wpdb->insert($wpdb->prefix . 'polaroid_orders', $db_data);
                $order_db_id = $wpdb->insert_id;
            }

            if (!$order_db_id)
                continue;
            $imported_orders++;

            // Handle photos
            if (!empty($order_data['photos'])) {
                // Clear existing photos for this order to avoid duplication
                $wpdb->delete($wpdb->prefix . 'polaroid_photos', array('order_ref_id' => $order_db_id));

                // Process photos
                $photos_to_save = array();
                $order_upload_dir = $upload_dir['basedir'] . '/polaroid-uploads/' . $order_db_id;

                if (!file_exists($order_upload_dir)) {
                    wp_mkdir_p($order_upload_dir);
                }

                foreach ($order_data['photos'] as $photo) {
                    // Try to finding the file in the extracted backup
                    // The path in backup is 'uploads/polaroid-uploads/{id}/filename' or just 'uploads/{name}' depending on previous export logic.
                    // In export: $zip->addFile($real_path, 'uploads/' . $relative_path);
                    // Relative path was based on 'polaroid-uploads/'. So it is 'uploads/{order_id}/{filename}'.
                    // But imported order ID might NOT match exported order table ID (pk).
                    // Wait, export used relative path from `polaroid-uploads`. So: `uploads/123/img.jpg`.
                    // The `123` is the DB ID from the EXPORTED system.
                    // We need to match the file. The backup data has `image_path` but that's absolute path on old server.

                    // We can try to match by filename.
                    // Or parse the old order ID from the old path?
                    // Better: The `order_data` has the old `id`. We can use that to find the folder in the ZIP.

                    $old_order_id = $order_data['id']; // From backup JSON
                    $filename = basename($photo['image_path']);

                    $source_in_temp = $temp_dir . '/uploads/' . $old_order_id . '/' . $filename;

                    if (!file_exists($source_in_temp)) {
                        // Try finding it flat?
                        // If logic failed, we might skip.
                        // Let's log if missed.
                        continue;
                    }

                    $new_path = $order_upload_dir . '/' . $filename;
                    $new_url = $upload_dir['baseurl'] . '/polaroid-uploads/' . $order_db_id . '/' . $filename;

                    copy($source_in_temp, $new_path);

                    // Prepare photo data for DB
                    $photo_db_data = array(
                        'order_ref_id' => $order_db_id,
                        'image_path' => $new_path,
                        'image_url' => $new_url,
                        'text' => $photo['text'],
                        'emoji' => $photo['emoji'],
                        'font_family' => $photo['font_family'],
                        'crop_x' => $photo['crop_x'],
                        'crop_y' => $photo['crop_y'],
                        'crop_width' => $photo['crop_width'],
                        'crop_height' => $photo['crop_height'],
                        'zoom' => $photo['zoom'],
                        'rotation' => $photo['rotation'],
                        'position_order' => $photo['position_order']
                    );

                    $wpdb->insert($wpdb->prefix . 'polaroid_photos', $photo_db_data);
                    $imported_photos++;
                }
            }
        }

        // Cleanup temp dir
        $this->recursive_rmdir($temp_dir);

        wp_send_json_success(array(
            'message' => sprintf(__('Restauração concluída! %d pedidos e %d fotos processados.', 'polaroids-customizadas'), $imported_orders, $imported_photos)
        ));
    }

    /**
     * Helper to remove directory recursively
     */
    private function recursive_rmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        $this->recursive_rmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Send admin notification email
     */
    public function send_admin_notification($order_data)
    {
        if (get_option('sdpp_enable_email_notifications', '0') !== '1') {
            return;
        }

        $admin_email = get_option('sdpp_admin_email', get_option('admin_email'));
        $subject = get_option('sdpp_admin_email_subject', __('Novo pedido de Polaroid recebido - #{order_id}', 'polaroids-customizadas'));
        $body = get_option('sdpp_admin_email_body', '');

        $subject = $this->replace_email_placeholders($subject, $order_data);
        $body = $this->replace_email_placeholders($body, $order_data);

        wp_mail($admin_email, $subject, $body);
    }

    /**
     * Send customer notification email
     */
    public function send_customer_notification($order_data)
    {
        if (get_option('sdpp_enable_email_notifications', '0') !== '1') {
            return;
        }

        if (empty($order_data['customer_email'])) {
            return;
        }

        $subject = get_option('sdpp_customer_email_subject', __('Seu pedido de Polaroid foi recebido - #{order_id}', 'polaroids-customizadas'));
        $body = get_option('sdpp_customer_email_body', '');

        $subject = $this->replace_email_placeholders($subject, $order_data);
        $body = $this->replace_email_placeholders($body, $order_data);

        wp_mail($order_data['customer_email'], $subject, $body);
    }

    /**
     * Replace email placeholders with actual values
     */
    private function replace_email_placeholders($content, $order_data)
    {
        $placeholders = array(
            '{order_id}' => $order_data['order_id'] ?? '',
            '{customer_name}' => $order_data['customer_name'] ?? '',
            '{customer_email}' => $order_data['customer_email'] ?? '',
            '{photo_quantity}' => $order_data['photo_quantity'] ?? '',
            '{store}' => $order_data['store'] ?? '',
        );

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }
}

// Initialize plugin
SDPP_Plugin::get_instance();
