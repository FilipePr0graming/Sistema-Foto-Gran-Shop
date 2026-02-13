<?php
/**
 * Google Fonts manager for Polaroids Customizadas
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDPP_Fonts
{

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Available fonts configuration
     */
    private $fonts = array();

    /**
     * Get instance
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
        $this->fonts = $this->define_fonts();
    }

    /**
     * Define available Google Fonts
     */
    private function define_fonts()
    {
        return array(
            // Handwritten / Cursive fonts (for Polaroid-style captions)
            'handwritten' => array(
                array('family' => 'Pacifico', 'weights' => array('400'), 'label' => 'Pacifico', 'preview' => 'The quick brown fox'),
                array('family' => 'Dancing Script', 'weights' => array('400', '500', '600', '700'), 'label' => 'Dancing Script', 'preview' => 'The quick brown fox'),
                array('family' => 'Caveat', 'weights' => array('400', '500', '600', '700'), 'label' => 'Caveat', 'preview' => 'The quick brown fox'),
                array('family' => 'Amatic SC', 'weights' => array('400', '700'), 'label' => 'Amatic SC', 'preview' => 'The quick brown fox'),
                array('family' => 'Indie Flower', 'weights' => array('400'), 'label' => 'Indie Flower', 'preview' => 'The quick brown fox'),
                array('family' => 'Shadows Into Light', 'weights' => array('400'), 'label' => 'Shadows Into Light', 'preview' => 'The quick brown fox'),
                array('family' => 'Permanent Marker', 'weights' => array('400'), 'label' => 'Permanent Marker', 'preview' => 'The quick brown fox'),
                array('family' => 'Satisfy', 'weights' => array('400'), 'label' => 'Satisfy', 'preview' => 'The quick brown fox'),
                array('family' => 'Courgette', 'weights' => array('400'), 'label' => 'Courgette', 'preview' => 'The quick brown fox'),
                array('family' => 'Great Vibes', 'weights' => array('400'), 'label' => 'Great Vibes', 'preview' => 'The quick brown fox'),
                array('family' => 'Sacramento', 'weights' => array('400'), 'label' => 'Sacramento', 'preview' => 'The quick brown fox'),
                array('family' => 'Handlee', 'weights' => array('400'), 'label' => 'Handlee', 'preview' => 'The quick brown fox'),
                array('family' => 'Kalam', 'weights' => array('300', '400', '700'), 'label' => 'Kalam', 'preview' => 'The quick brown fox'),
                array('family' => 'Patrick Hand', 'weights' => array('400'), 'label' => 'Patrick Hand', 'preview' => 'The quick brown fox'),
                array('family' => 'Gloria Hallelujah', 'weights' => array('400'), 'label' => 'Gloria Hallelujah', 'preview' => 'The quick brown fox'),
            ),

            // Clean sans-serif fonts
            'sans-serif' => array(
                array('family' => 'Inter Tight', 'weights' => array('300', '400', '500', '600', '700', '800'), 'label' => 'Inter Tight', 'preview' => 'The quick brown fox'),
                array('family' => 'Roboto', 'weights' => array('300', '400', '500', '700'), 'label' => 'Roboto', 'preview' => 'The quick brown fox'),
                array('family' => 'Open Sans', 'weights' => array('300', '400', '500', '600', '700'), 'label' => 'Open Sans', 'preview' => 'The quick brown fox'),
                array('family' => 'Lato', 'weights' => array('300', '400', '700'), 'label' => 'Lato', 'preview' => 'The quick brown fox'),
                array('family' => 'Montserrat', 'weights' => array('300', '400', '500', '600', '700'), 'label' => 'Montserrat', 'preview' => 'The quick brown fox'),
                array('family' => 'Oswald', 'weights' => array('300', '400', '500', '600', '700'), 'label' => 'Oswald', 'preview' => 'The quick brown fox'),
            )
        );
    }

    /**
     * Get Google Fonts CDN URL
     */
    public function get_google_fonts_url()
    {
        $families = array();

        foreach ($this->fonts as $category => $category_fonts) {
            foreach ($category_fonts as $font) {
                $weights = implode(';', $font['weights']);
                $families[] = urlencode($font['family']) . ':wght@' . $weights;
            }
        }

        // Using Google Fonts API v2
        $url = 'https://fonts.googleapis.com/css2?';
        $url .= implode('&', array_map(function ($family) {
            return 'family=' . $family;
        }, $families));
        $url .= '&display=swap';

        return $url;
    }

    /**
     * Get fonts list for JavaScript
     */
    public function get_fonts_list()
    {
        $list = array();

        foreach ($this->fonts as $category => $category_fonts) {
            $list[$category] = array();
            foreach ($category_fonts as $font) {
                $list[$category][] = array(
                    'family' => $font['family'],
                    'label' => $font['label'],
                    'preview' => $font['preview']
                );
            }
        }

        return $list;
    }

    /**
     * Get all font families as flat array
     */
    public function get_all_families()
    {
        $families = array();

        foreach ($this->fonts as $category => $category_fonts) {
            foreach ($category_fonts as $font) {
                $families[] = $font['family'];
            }
        }

        return $families;
    }

    /**
     * Get fonts by category
     */
    public function get_fonts_by_category($category)
    {
        return isset($this->fonts[$category]) ? $this->fonts[$category] : array();
    }

    /**
     * Check if font family is valid
     */
    public function is_valid_font($family)
    {
        return in_array($family, $this->get_all_families());
    }

    /**
     * Get default font for category
     */
    public function get_default_font($category = 'handwritten')
    {
        if (isset($this->fonts[$category]) && !empty($this->fonts[$category])) {
            return $this->fonts[$category][0]['family'];
        }
        return 'Pacifico';
    }

    /**
     * Get footer font (for PNG generation)
     */
    public function get_footer_font()
    {
        return 'Montserrat';
    }

    /**
     * Get local font file path for PNG generation
     * Downloads font from Google Fonts if not exists locally
     */
    public function get_font_file_path($family)
    {
        $log_dir = WP_CONTENT_DIR . '/debug';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/sdpp_debug.log';

        $upload_dir = wp_upload_dir();
        $fonts_dir = $upload_dir['basedir'] . '/polaroid-fonts/';

        // Create fonts directory if not exists
        if (!file_exists($fonts_dir)) {
            wp_mkdir_p($fonts_dir);
        }

        $font_file = $fonts_dir . sanitize_file_name($family) . '.ttf';

        // If file exists, return it
        if (file_exists($font_file)) {
            return $font_file;
        }

        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Downloading font: $family to $font_file" . PHP_EOL, FILE_APPEND);

        // Download font from Google Fonts
        $downloaded = $this->download_font($family, $font_file);

        if ($downloaded) {
            file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Download success: $family" . PHP_EOL, FILE_APPEND);
            return $font_file;
        }

        file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] Download failed: $family" . PHP_EOL, FILE_APPEND);

        // Fallback to default system font path
        return false;
    }

    /**
     * Download font file from Google Fonts using CSS API
     */
    private function download_font($family, $destination)
    {
        $log_dir = WP_CONTENT_DIR . '/debug';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/sdpp_debug.log';
        file_put_contents($log_file, "Attempting CSS API download for: $family" . PHP_EOL, FILE_APPEND);

        // Use CSS API with a Legacy User Agent (Safari 5) to force TTF response
        // Modern browsers get WOFF2, which Imagick might not support.
        $css_url = 'https://fonts.googleapis.com/css?family=' . urlencode($family);
        $args = array(
            'timeout' => 30,
            'redirection' => 5,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534.57.2 (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2'
            )
        );

        $response = wp_remote_get($css_url, $args);

        if (is_wp_error($response)) {
            file_put_contents($log_file, "CSS API Error: " . $response->get_error_message() . PHP_EOL, FILE_APPEND);
            return false;
        }

        $css_body = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code != 200 || empty($css_body)) {
            file_put_contents($log_file, "CSS API Bad Response: $response_code" . PHP_EOL, FILE_APPEND);
            return false;
        }

        // Parse the Font URL from CSS
        // formatting is usually: src: url(http://...) format('truetype');
        if (preg_match('/url\(([\'"]?)(.*?)\1\)/', $css_body, $matches)) {
            $font_url = $matches[2];
            file_put_contents($log_file, "Found Font URL: $font_url" . PHP_EOL, FILE_APPEND);

            // Download the actual font file
            $font_response = wp_remote_get($font_url, array('timeout' => 60));

            if (is_wp_error($font_response)) {
                file_put_contents($log_file, "Font Download Error: " . $font_response->get_error_message() . PHP_EOL, FILE_APPEND);
                return false;
            }

            $font_body = wp_remote_retrieve_body($font_response);
            $font_code = wp_remote_retrieve_response_code($font_response);

            if ($font_code == 200 && !empty($font_body)) {
                // Verify it's not HTML error page again
                if (strpos($font_body, '<!DOCTYPE') === 0 || strpos($font_body, '<html') === 0) {
                    file_put_contents($log_file, "Aborting: Downloaded file seems to be HTML, not Font." . PHP_EOL, FILE_APPEND);
                    return false;
                }

                file_put_contents($destination, $font_body);
                file_put_contents($log_file, "Font saved successfully to $destination. Size: " . strlen($font_body) . PHP_EOL, FILE_APPEND);
                return true;
            } else {
                file_put_contents($log_file, "Font Download Failed Code: $font_code" . PHP_EOL, FILE_APPEND);
            }
        } else {
            file_put_contents($log_file, "Could not find URL in CSS: " . substr($css_body, 0, 100) . "..." . PHP_EOL, FILE_APPEND);
        }

        return false;
    }
}
