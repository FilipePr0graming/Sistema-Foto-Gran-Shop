<?php
/**
 * Image Generator for Polaroids Customizadas
 * Generates A4 PNG pages from the fixed template with customer photos
 */

if (!defined('ABSPATH')) {
    exit;
}

class SDPP_Image_Generator
{
    /**
     * Exact Sheet Dimensions from User Model (190mm x 275mm) @ 300 DPI
     * Width: 190mm -> 2244px
     * Height: 275mm -> 3248px
     */
    const CANVAS_WIDTH = 2244;
    const CANVAS_HEIGHT = 3248;

    const EXPORT_SCALE = 4;

    /**
     * Grid Metrics (px)
     */
    const PAD_TOP = 118;    // 10mm
    const PAD_LEFT = 71;    // 6mm
    const COL_GAP = 83;     // 7mm
    const ROW_GAP = 94;     // 8mm

    // Calculated: (2209 - 71 - 71 - 83 - 83) / 3 = 633.66 -> 634px
    const SLOT_WIDTH = 634;

    // Photo Ratio 25:28 -> Height = 634 * (28/25) = 709.9 -> 710px
    const PHOTO_HEIGHT = 710;

    const TEXT_MARGIN_TOP = 12; // 1mm
    // Text Area in Print: 168px height (approx 14mm)
    const MAX_TEXT_HEIGHT = 168;

    // Editor Dimensions reference for scaling (Frame 400px - 24px padding = 376px width)
    const EDITOR_TEXT_AREA_WIDTH = 376;
    const EDITOR_TEXT_AREA_HEIGHT = 80;

    const FOOTER_BOTTOM_DIST = 59; // 5mm
    const FOOTER_PAD_X = 472;      // 40mm

    const DOT_SIZE = 53;    // 4.5mm
    const DOT_OFFSET = 12;  // 1mm

    // 2x3 Grid Metrics (px) @ 300 DPI (approx 11.811 px/mm)

    // Photo: 72mm x 67mm
    const G2X3_PHOTO_W = 850; // 72mm
    const G2X3_PHOTO_H = 791; // 67mm

    // Text Indicator: Height 65mm (~768px), Padding 20mm/4mm.
    // Width isn't fixed but let's assume ~40mm (472px) or based on content?
    // User CSS: padding 20mm(V) 4mm(H). font-size 7pt.
    // 7pt is tiny. 
    // Let's set a fixed width for the text strip to ensure alignment.
    // Let's try 350px width (~30mm).
    const G2X3_TEXT_FINAL_W = 160;
    const G2X3_TEXT_STRIP_HEIGHT = 768;
    const G2X3_WRAPPER_GAP = 47;   // ~4mm gap
    const G2X3_ITEM_W = 1057;      // 160 + 47 + 850

    // Grid Layout
    // Page Padding: Top 10mm (118px), Bottom 3mm (35px).
    // Content Gap (Grid to Footer): 13mm (154px).

    // Total Item Width = Text + Photo = 350 + 850 = 1200px.
    // 2 Cols = 2400px.
    // Canvas Width = 2209px.
    // It EXCEEDS canvas if 350px.
    // Let's reduce Text Width. 20mm padding horizontal? No, 4mm.
    // 4mm left + 4mm right = 8mm padding.
    // Font is small. 
    // Let's say Text Width is ~200px (17mm).
    // Item Width = 200 + 850 = 1050px.
    // 2 Cols = 2100px.
    // Canvas 2209. Leftover 109px. 
    // Side Margins = ~54.5px (4.6mm).


    // Row Placement
    // Guide V: 50%.
    // Guide H1: 33.6% (Row 1/2 boundary).
    // Guide H2: 66.2% (Row 2/3 boundary).
    // Canvas Height 3154.
    // Row 1 Bottom = 0.336 * 3154 = 1059px.
    // Row 2 Bottom = 0.662 * 3154 = 2088px.

    // Row 1 Center Y? 
    // Top Margin 118px.
    // Let's simple calculate centers based on the 3 massive rows defined by guides.
    // Row Height approx 1050px? 
    // No, Photo is only 791px (67mm).
    // The HTML has `align-content:start`.
    // And `padding: 10mm ...`
    // And `row-gap: mm` (Typo in user CSS).
    // Let's assume equal spacing or use the Guides as center lines? No, guides are dividers.

    // Let's use Fixed Top Offset and Row Gap.
    // If rows are evenly distributed in the available height?
    // Let's stick to a calculated gap.
    // 3 Rows of 791px height = 2373px.
    // Total Height available (minus footer 100mm? No footer is small).
    // Footer area: Padding 0 22mm 3mm 0.
    // Bottom 3mm margin + Footer Height.
    // Let's assume standard positioning:
    // Top: 118px.
    // Row Gap: Let's try 50px (approx 4mm).

    const G2X3_PAD_TOP = 118; // 10mm
    const G2X3_ROW_GAP = 142; // 12mm approx, to fill space? 
    // Let's calculate:
    // Grid Height = 3 * 791 + 2 * Gap.
    // Available: 3154 - 118 (Top) - 200 (Footer?) - 154 (Gap Content-Footer)?
    // Let's infer Gap from guides.
    // Guide 1 (33.6%) = 1059px. 
    // Top = 118px.
    // Row 1 sits between 118 and 1059?
    // Space = 941px. Photo = 791px.
    // Margin per row?
    // Let's Center the Item vertically in the "Row Space".
    // Row 1 Center ~ (118+1059)/2 = 588px.
    // Item Y = 588 - (791/2) = 192px.

    // I will implement a helper to calculate Y based on logic.

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    private $progress_order_id = 0;
    private $progress_total_photos = 0;
    private $progress_processed_photos = 0;

    private function progress_set($percent, $status)
    {
        if (empty($this->progress_order_id)) {
            return;
        }

        $key = 'sdpp_png_progress_' . intval($this->progress_order_id);
        $payload = array(
            'percent' => max(0, min(100, intval($percent))),
            'status' => strval($status)
        );

        set_transient($key, $payload, 2 * HOUR_IN_SECONDS);
    }

    private function progress_tick_photo()
    {
        if (empty($this->progress_order_id) || empty($this->progress_total_photos)) {
            return;
        }

        $this->progress_processed_photos++;
        $ratio = $this->progress_processed_photos / max(1, $this->progress_total_photos);
        $percent = (int) floor($ratio * 98);
        if ($percent > 98) {
            $percent = 98;
        }

        $this->progress_set($percent, 'running');
    }

    private function log($msg)
    {
        $log_dir = WP_CONTENT_DIR . '/debug';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/sdpp_debug.log';
        $time = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$time] $msg" . PHP_EOL, FILE_APPEND);
    }

    /**
     * Extract Unicode emojis from text
     * Returns array of ['emoji' => '😀', 'position' => 5, 'length' => 1]
     */
    private function extract_emojis_from_text($text)
    {
        $emojis = [];

        // Comprehensive emoji regex pattern - covers most common emoji ranges
        $pattern = '/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{1F000}-\x{1F02F}]|[\x{1F0A0}-\x{1F0FF}]|[\x{1F100}-\x{1F1FF}]|[\x{1F200}-\x{1F2FF}]|[\x{231A}-\x{231B}]|[\x{23E9}-\x{23F3}]|[\x{23F8}-\x{23FA}]|[\x{25AA}-\x{25AB}]|[\x{25B6}]|[\x{25C0}]|[\x{25FB}-\x{25FE}]|[\x{2614}-\x{2615}]|[\x{2648}-\x{2653}]|[\x{267F}]|[\x{2693}]|[\x{26A1}]|[\x{26AA}-\x{26AB}]|[\x{26BD}-\x{26BE}]|[\x{26C4}-\x{26C5}]|[\x{26CE}]|[\x{26D4}]|[\x{26EA}]|[\x{26F2}-\x{26F3}]|[\x{26F5}]|[\x{26FA}]|[\x{26FD}]|[\x{2702}]|[\x{2705}]|[\x{2708}-\x{270D}]|[\x{270F}]|[\x{2712}]|[\x{2714}]|[\x{2716}]|[\x{271D}]|[\x{2721}]|[\x{2728}]|[\x{2733}-\x{2734}]|[\x{2744}]|[\x{2747}]|[\x{274C}]|[\x{274E}]|[\x{2753}-\x{2755}]|[\x{2757}]|[\x{2763}-\x{2764}]|[\x{2795}-\x{2797}]|[\x{27A1}]|[\x{27B0}]|[\x{27BF}]|[\x{2934}-\x{2935}]|[\x{2B05}-\x{2B07}]|[\x{2B1B}-\x{2B1C}]|[\x{2B50}]|[\x{2B55}]|[\x{3030}]|[\x{303D}]|[\x{3297}]|[\x{3299}]/u';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $emojis[] = [
                    'emoji' => $match[0],
                    'byte_position' => $match[1],
                    'char_position' => mb_strlen(substr($text, 0, $match[1]), 'UTF-8'),
                    'length' => mb_strlen($match[0], 'UTF-8')
                ];
            }
        }

        return $emojis;
    }

    /**
     * Get Twemoji PNG path for an emoji (downloads and caches if needed)
     */
    private function get_twemoji_path($emoji)
    {
        // Get codepoint
        $codepoint = $this->emoji_to_codepoint($emoji);
        if (empty($codepoint)) {
            return null;
        }

        // Cache directory
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/polaroid-uploads/emoji-cache';
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }

        $cache_path = $cache_dir . '/' . $codepoint . '.png';

        // Return cached version if exists
        if (file_exists($cache_path)) {
            return $cache_path;
        }

        // Download from Twemoji CDN
        $twemoji_url = 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/72x72/' . $codepoint . '.png';

        $response = wp_remote_get($twemoji_url, ['timeout' => 5]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->log("Failed to download Twemoji for: $emoji (codepoint: $codepoint)");
            return null;
        }

        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return null;
        }

        // Save to cache
        file_put_contents($cache_path, $image_data);

        return $cache_path;
    }

    /**
     * Convert emoji character to Twemoji-compatible codepoint string
     */
    private function emoji_to_codepoint($emoji)
    {
        $codepoints = [];
        $len = mb_strlen($emoji, 'UTF-8');

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($emoji, $i, 1, 'UTF-8');
            $ord = mb_ord($char, 'UTF-8');

            // Skip variation selectors and zero-width joiners for simpler codepoints
            if ($ord === 0xFE0F || $ord === 0xFE0E || $ord === 0x200D) {
                continue;
            }

            $codepoints[] = dechex($ord);
        }

        return implode('-', $codepoints);
    }

    /**
     * Remove emojis from text (replace with spaces for positioning)
     */
    private function strip_emojis_from_text($text)
    {
        $pattern = '/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{1F000}-\x{1F02F}]|[\x{1F0A0}-\x{1F0FF}]|[\x{1F100}-\x{1F1FF}]|[\x{1F200}-\x{1F2FF}]|[\x{231A}-\x{231B}]|[\x{23E9}-\x{23F3}]|[\x{23F8}-\x{23FA}]|[\x{25AA}-\x{25AB}]|[\x{25B6}]|[\x{25C0}]|[\x{25FB}-\x{25FE}]|[\x{2614}-\x{2615}]|[\x{2648}-\x{2653}]|[\x{267F}]|[\x{2693}]|[\x{26A1}]|[\x{26AA}-\x{26AB}]|[\x{26BD}-\x{26BE}]|[\x{26C4}-\x{26C5}]|[\x{26CE}]|[\x{26D4}]|[\x{26EA}]|[\x{26F2}-\x{26F3}]|[\x{26F5}]|[\x{26FA}]|[\x{26FD}]|[\x{2702}]|[\x{2705}]|[\x{2708}-\x{270D}]|[\x{270F}]|[\x{2712}]|[\x{2714}]|[\x{2716}]|[\x{271D}]|[\x{2721}]|[\x{2728}]|[\x{2733}-\x{2734}]|[\x{2744}]|[\x{2747}]|[\x{274C}]|[\x{274E}]|[\x{2753}-\x{2755}]|[\x{2757}]|[\x{2763}-\x{2764}]|[\x{2795}-\x{2797}]|[\x{27A1}]|[\x{27B0}]|[\x{27BF}]|[\x{2934}-\x{2935}]|[\x{2B05}-\x{2B07}]|[\x{2B1B}-\x{2B1C}]|[\x{2B50}]|[\x{2B55}]|[\x{3030}]|[\x{303D}]|[\x{3297}]|[\x{3299}]/u';

        return preg_replace($pattern, '', $text);
    }
    /**
     * Main generation method
     * 
     * @param int $order_id The order database ID
     * @return string|WP_Error URL to the generated file or error object
     */
    public function generate($order_id)
    {
        $this->log("Starting generation for Order ID: $order_id");

        try {
            $this->progress_order_id = intval($order_id);
            $this->progress_total_photos = 0;
            $this->progress_processed_photos = 0;
            $this->progress_set(0, 'running');

            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            if (!class_exists('ZipArchive')) {
                $this->progress_set(0, 'error');
                return new WP_Error('missing_zip', 'PHP ZipArchive extension is missing.');
            }

            $database = new SDPP_Database();
            $order = $database->get_order($order_id);

            if (!$order) {
                $this->progress_set(0, 'error');
                return new WP_Error('order_not_found', "Order $order_id not found.");
            }

            $photos = $database->get_photos($order_id);

            if (empty($photos)) {
                $this->progress_set(0, 'error');
                return new WP_Error('no_photos', "No photos found for order $order_id.");
            }

            $this->progress_total_photos = count($photos);
            $this->progress_processed_photos = 0;
            $this->progress_set(0, 'running');

            // Calculate number of pages needed
            $photos_per_page = 9;
            if (isset($order->grid_type) && $order->grid_type === '2x3') {
                $photos_per_page = 6;
            }

            $total_photos = count($photos);
            $total_pages = ceil($total_photos / $photos_per_page);

            // Generate output directory
            $upload_dir = wp_upload_dir();
            $output_dir = $upload_dir['basedir'] . '/polaroid-outputs/' . $order_id;

            if (!file_exists($output_dir)) {
                if (!wp_mkdir_p($output_dir)) {
                    $this->progress_set(0, 'error');
                    return new WP_Error('dir_create_failed', "Failed to create directory: $output_dir");
                }
            }

            $generated_files = array();

            // Generate each page
            for ($page = 1; $page <= $total_pages; $page++) {
                $this->log("Generating page $page of $total_pages");

                $start_index = ($page - 1) * $photos_per_page;
                $page_photos = array_slice($photos, $start_index, $photos_per_page);

                $output_path = $output_dir . '/page-' . $page . '.png';

                // Prioritize Imagick
                if (class_exists('Imagick')) {
                    $result = $this->generate_page_imagick($order, $page_photos, $page, $total_pages, $output_path);
                } else {
                    $this->log("Imagick not found, falling back to GD");
                    $result = $this->generate_page_gd($order, $page_photos, $page, $total_pages, $output_path);
                }

                if (is_wp_error($result)) {
                    $this->progress_set(0, 'error');
                    return $result;
                }

                if ($result) {
                    $generated_files[] = $output_path;
                }
            }

            if (empty($generated_files)) {
                $this->progress_set(0, 'error');
                return new WP_Error('generation_failed', 'No pages were generated.');
            }

            // Build filename base
            $customer_name = sanitize_file_name($order->customer_name ?: 'Cliente');
            $order_id_str = sanitize_file_name($order->order_id);
            $base_filename = $customer_name . ' - ' . $order_id_str;

            // Always: create ZIP with PNG(s) + info.txt
            $zip_path = $output_dir . '/' . $base_filename . '.zip';

            $this->progress_set(99, 'zipping');

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->progress_set(0, 'error');
                return new WP_Error('zip_failed', 'Failed to create ZIP file.');
            }

            $info_lines = array();
            foreach ($generated_files as $index => $file) {
                if (!file_exists($file)) {
                    continue;
                }

                $entry_name = '';
                if (count($generated_files) === 1) {
                    $entry_name = 'grid.png';
                } else {
                    $entry_name = 'grid-' . ($index + 1) . '.png';
                }

                $zip->addFile($file, $entry_name);

                $dims = @getimagesize($file);
                $w = $dims ? intval($dims[0]) : 0;
                $h = $dims ? intval($dims[1]) : 0;
                $bytes = @filesize($file);
                $info_lines[] = $entry_name . "\t" . $w . "x" . $h . "\t" . $bytes . " bytes";
            }

            if (!empty($info_lines)) {
                $zip->addFromString('info.txt', implode("\n", $info_lines) . "\n");
            }

            $zip->close();

            $this->progress_set(100, 'done');

            // Clean up individual PNGs
            foreach ($generated_files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $url = $upload_dir['baseurl'] . '/polaroid-outputs/' . $order_id . '/' . rawurlencode($base_filename . '.zip');
            $this->log("Generation success (ZIP): $url");
            return $url;

        } catch (Exception $e) {
            $this->log("Exception: " . $e->getMessage());
            $this->progress_set(0, 'error');
            return new WP_Error('exception', $e->getMessage());
        }
    }

    /**
     * Generate page using Imagick
     */
    private function generate_page_imagick($order, $photos, $page, $total_pages, $output_path)
    {
        try {
            $scale = self::EXPORT_SCALE;
            $S = function ($v) use ($scale) {
                return (int) round($v * $scale);
            };

            $canvas_w = $S(self::CANVAS_WIDTH);
            $canvas_h = $S(self::CANVAS_HEIGHT);

            // 1. Create blank A4 canvas (white)
            $canvas = new Imagick();
            $canvas->newImage($canvas_w, $canvas_h, new ImagickPixel('white'));
            $canvas->setImageFormat('png');
            $canvas->setImageCompression(Imagick::COMPRESSION_ZIP);
            $canvas->setImageCompressionQuality(100);
            $canvas->setOption('png:compression-level', '6');
            // Ensure 300 DPI metadata
            $canvas->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
            $canvas->setImageResolution(300 * $scale, 300 * $scale);

            // 2. Draw Corner Dots
            $this->draw_corner_dots_imagick($canvas);

            // 3. Process each photo slot
            foreach ($photos as $index => $photo) {

                if (isset($order->grid_type) && $order->grid_type === '2x3' && $order->has_border) {
                    // 2x3 Grid Logic: 2 Columns, 3 Rows (Bordered)
                    if ($index > 5)
                        break;

                    $col = $index % 2;
                    $row = floor($index / 2);

                    // Calculations
                    // Canvas Center X = 1104.5.
                    // Total Grid Width = 2 * (200 + 850) + Gap(0) = 2100.
                    // Start X = (2209 - 2100) / 2 = 54.5 px.

                    // Row Y
                    // Row 1 Center Y ~ 588.  Start Y = 588 - 791/2 = 192.5
                    // Row 2 Center Y ~ 1573. Start Y = 1573 - 395.5 = 1177.5
                    // Row 3 Center Y ~ 2558? (Guide 2 bottom to Footer top).
                    // Guide 2 = 2088. Footer Content starts? 
                    // Let's use uniform spacing.
                    // Start Y = 192.
                    // Gap = Need to hit next row.
                    // Let's just use fixed margin top derived from 10mm.
                    // 10mm = 118px.
                    // HTML says padding 10mm. So Grid starts at 118px.
                    // Row Gap? HTML says 'mm'. Let's estimate 12mm (142px).

                    $col_center_x = ($col === 0) ? ($canvas_w / 4) : ($canvas_w * 3 / 4);

                    // Metrics for 33.6% and 66.2% spacing (from 3248px height)
                    $item_y = 0;
                    if ($row == 0)
                        $item_y = $S(118) + ($S(1091) - $S(118) - $S(791)) / 2;
                    elseif ($row == 1)
                        $item_y = $S(1091) + ($S(1059) - $S(791)) / 2;
                    else
                        $item_y = $S(2150) + ($S(1063) - $S(791)) / 2;

                    // Fixed photo X relative to column center to maintain position while gap increases
                    $photo_x = $col_center_x - $S(333);
                    $text_x = $photo_x - $S(self::G2X3_WRAPPER_GAP) - $S(self::G2X3_TEXT_FINAL_W);
                    $text_y = $item_y + ($S(self::G2X3_PHOTO_H) - $S(self::G2X3_TEXT_STRIP_HEIGHT)) / 2;

                    // Render photo with 90 deg clockwise rotation
                    $this->render_photo_imagick($canvas, $photo, $photo_x, $item_y, $S(self::G2X3_PHOTO_W), $S(self::G2X3_PHOTO_H), 90);

                    $this->progress_tick_photo();

                    // Render text & emoji strip honoring editor positions
                    $this->render_editor_strip_2x3($canvas, $photo, (int) $text_x, (int) $text_y, $scale);

                    // Draw Photo Border (Solid)
                    $drawP = new ImagickDraw();
                    $drawP->setFillColor('transparent');
                    $drawP->setStrokeColor('#bdbdbd');
                    $drawP->setStrokeWidth($S(2));
                    $drawP->rectangle($photo_x, $item_y, $photo_x + $S(self::G2X3_PHOTO_W), $item_y + $S(self::G2X3_PHOTO_H));
                    $canvas->drawImage($drawP);

                } elseif ($order->grid_type === '2x3' && !$order->has_border) {
                    // 2x3 Borderless (Metric Update)
                    if ($index > 5)
                        break;

                    // Metrics
                    // Canvas 2209 x 3154
                    // Padding Top 9mm = 106px
                    // Gap 0.5mm = 6px
                    // Cols 2
                    // Rows 3
                    // Photo W = (2209 - 6) / 2 = 1101.5 -> 1101
                    // Photo H = 947 (Approx to fit A4)

                    $b2x3_start_y = $S(106);
                    $b2x3_gap = $S(6);
                    $b2x3_w = $S(1119);
                    $b2x3_h = $S(921);

                    $col = $index % 2;
                    $row = floor($index / 2);

                    $slot_x = ($col * ($b2x3_w + $b2x3_gap));

                    $slot_y = $b2x3_start_y + ($row * ($b2x3_h + $b2x3_gap));

                    // 90 deg CW rotation = 90 deg.
                    $this->render_photo_imagick_borderless($canvas, $photo, $slot_x, $slot_y, $b2x3_w, $b2x3_h, 90);

                    $this->progress_tick_photo();

                } elseif ($order->has_border) {
                    // Standard 3x3 Polaroid Metrics
                    if ($index > 8)
                        break;

                    $col = $index % 3;
                    $row = floor($index / 3);

                    $slot_x = $S(self::PAD_LEFT) + ($col * ($S(self::SLOT_WIDTH) + $S(self::COL_GAP)));
                    $row_height_total = $S(self::PHOTO_HEIGHT) + $S(self::TEXT_MARGIN_TOP) + $S(self::MAX_TEXT_HEIGHT);
                    $slot_y = $S(self::PAD_TOP) + ($row * ($row_height_total + $S(self::ROW_GAP)));

                    // Render Photo (Center Crop)
                    $this->render_photo_imagick($canvas, $photo, $slot_x, $slot_y, $S(self::SLOT_WIDTH), $S(self::PHOTO_HEIGHT));

                    $this->progress_tick_photo();

                    // Render Text
                    if (!empty($photo->text)) {
                        $text_area_y = $slot_y + $S(self::PHOTO_HEIGHT) + $S(self::TEXT_MARGIN_TOP);
                        $this->render_text_imagick($canvas, $photo, $slot_x, $text_area_y, $S(self::SLOT_WIDTH), $S(self::MAX_TEXT_HEIGHT), 0);
                    }

                    // Render Emojis
                    if (!empty($photo->emoji)) {
                        $text_area_y = $slot_y + $S(self::PHOTO_HEIGHT) + $S(self::TEXT_MARGIN_TOP);
                        $this->render_emoji_imagick($canvas, $photo, $slot_x, $text_area_y, $S(self::SLOT_WIDTH), $S(self::MAX_TEXT_HEIGHT));
                    }
                } else {
                    // Borderless Metrics (New Layout)
                    // Specs: Padding Top 9mm(106px), Left 2mm(24px), Gap 0.3mm(4px)
                    // Width 60.8mm(718px), Height 77.8mm(919px) - Ratio 25/32
                    if ($index > 8)
                        break;

                    $col = $index % 3;
                    $row = floor($index / 3);

                    // Constants for Borderless (Defined locally to avoid pollution)
                    $b_pad_top = 106;
                    $b_pad_left = 24;
                    $b_gap = 4;
                    $b_width = 718;
                    $b_height = 919;

                    $b_pad_top = $S($b_pad_top);
                    $b_pad_left = $S($b_pad_left);
                    $b_gap = $S($b_gap);
                    $b_width = $S($b_width);
                    $b_height = $S($b_height);

                    $slot_x = $b_pad_left + ($col * ($b_width + $b_gap));
                    $slot_y = $b_pad_top + ($row * ($b_height + $b_gap));

                    $this->render_photo_imagick_borderless($canvas, $photo, $slot_x, $slot_y, $b_width, $b_height);

                    $this->progress_tick_photo();

                    // No text or emojis in borderless mode
                }
            }

            // 4. Render Footer
            if ((isset($order->grid_type) && $order->grid_type === '2x3')) {
                if ($order->has_border) {
                    // Custom footer for 2x3 Bordered (Centralized)
                    $this->render_footer_imagick_2x3($canvas, $order, $page, $total_pages);
                } else {
                    // Custom footer for 2x3 Borderless (Space Between)
                    $this->render_footer_imagick_2x3_borderless($canvas, $order, $page, $total_pages);
                }
            } else {
                $this->render_footer_imagick($canvas, $order, $page, $total_pages);
            }

            // Save
            $canvas->writeImage($output_path);
            $canvas->destroy();

            return true;
        } catch (Exception $e) {
            $this->log("Imagick Error: " . $e->getMessage());
            return new WP_Error('imagick_error', $e->getMessage());
        }
    }

    /**
     * Render Footer for 2x3 Borderless
     */
    private function render_footer_imagick_2x3_borderless($canvas, $order, $page, $total_pages)
    {
        // HTML: .footer { display:flex; justify-content:space-between; padding: 0 30mm 9mm 30mm; gap:10mm; }
        // Left: Cursive. Right: Monospace.

        $fonts = SDPP_Fonts::get_instance();
        $font_family_left = 'Pacifico';
        $font_family_right = 'Courier New';

        $scale = self::EXPORT_SCALE;
        $w = $canvas->getImageWidth();
        $h = $canvas->getImageHeight();

        $padding_side = (int) round(354 * $scale); // 30mm = 30 * 11.8 = 354px
        $padding_bottom = (int) round(106 * $scale); // 9mm = 106px

        $footer_text = $this->build_footer_text($order, $page, $total_pages);

        // Store Color Logic
        $store_color = '#000000';
        $store_clean = strtolower(str_replace([' ', '-'], '_', trim($order->store)));
        // Check for 'aisheel_mix' or variations if needed
        if ($store_clean === 'aisheel_mix') {
            $store_color = '#047bc4';
        }

        $drawL = new ImagickDraw();
        $drawL->setFillColor($store_color);
        $drawL->setFontSize((int) round(40 * $scale)); // ~14px
        try {
            $fp = $fonts->get_font_file_path($font_family_left);
            if ($fp)
                $drawL->setFont($fp);
            else
                $drawL->setFontFamily($font_family_left);
        } catch (Exception $e) {
            $drawL->setFontFamily('Arial');
        }
        $footer_y = $h - $padding_bottom;
        $drawL->setTextAlignment(Imagick::ALIGN_CENTER);

        $center_x = $w / 2;
        $canvas->annotateImage($drawL, $center_x, $footer_y, 0, $footer_text);
    }

    /**
     * Draw corner dots (Imagick)
     */
    private function draw_corner_dots_imagick($canvas)
    {
        $draw = new ImagickDraw();
        $draw->setFillColor('black');
        $draw->setStrokeColor('transparent');

        $scale = self::EXPORT_SCALE;
        $w = $canvas->getImageWidth();
        $h = $canvas->getImageHeight();

        $r = (self::DOT_SIZE * $scale) / 2;
        $offset = self::DOT_OFFSET * $scale;

        // Top Left
        $draw->circle($offset + $r, $offset + $r, $offset + $r, $offset);
        // Top Right
        $draw->circle($w - $offset - $r, $offset + $r, $w - $offset - $r, $offset);
        // Bottom Left
        $draw->circle($offset + $r, $h - $offset - $r, $offset + $r, $h - $offset);
        // Bottom Right
        $draw->circle($w - $offset - $r, $h - $offset - $r, $w - $offset - $r, $h - $offset);

        $canvas->drawImage($draw);
    }

    /**
     * Render Content (Text & Emojis) Vertically Centered in a defined strip
     * Used for 2x3 Grid "Side Strip"
     * 
     * @param Imagick $canvas
     * @param object $photo
     * @param int $center_x Absolute X center of the strip
     * @param int $center_y Absolute Y center of the strip
     * @param int $scale_reference_w The reference width (Photo Width) to scale against
     */
    private function render_editor_strip_2x3($canvas, $photo, $text_x, $text_y, $scale = 1)
    {
        $overlay = $this->build_editor_overlay($photo, $scale);

        if (!$overlay) {
            $this->log('Overlay build failed for 2x3 strip.');
            return;
        }

        try {
            // Rotate editor footer (horizontal) to match vertical print strip
            // Use 90 (CW) to ensure content faces right as requested
            $overlay->rotateImage(new ImagickPixel('transparent'), 90);
            $overlay->setImagePage(0, 0, 0, 0);

            $overlay->resizeImage((int) round(self::G2X3_TEXT_FINAL_W * $scale), (int) round(self::G2X3_TEXT_STRIP_HEIGHT * $scale), Imagick::FILTER_LANCZOS, 1, false);

            $canvas->compositeImage($overlay, Imagick::COMPOSITE_OVER, $text_x, $text_y);
        } catch (Exception $e) {
            $this->log('Overlay composite error: ' . $e->getMessage());
        } finally {
            $overlay->clear();
            $overlay->destroy();
        }
    }

    private function build_editor_overlay($photo, $scale = 1)
    {
        try {
            $overlay = new Imagick();
            $w = (int) round(self::EDITOR_TEXT_AREA_WIDTH * $scale);
            $h = (int) round(self::EDITOR_TEXT_AREA_HEIGHT * $scale);
            $overlay->newImage($w, $h, new ImagickPixel('transparent'));
            $overlay->setImageFormat('png');
            $overlay->setImageCompression(Imagick::COMPRESSION_ZIP);
            $overlay->setImageCompressionQuality(100);
            $overlay->setOption('png:compression-level', '6');

            $this->draw_text_layers_on_overlay($overlay, $photo, $scale);
            $this->draw_emoji_layers_on_overlay($overlay, $photo, $scale);

            return $overlay;
        } catch (Exception $e) {
            $this->log('Failed to build editor overlay: ' . $e->getMessage());
            return null;
        }
    }

    private function draw_text_layers_on_overlay(Imagick $overlay, $photo, $scale = 1)
    {
        $layers = $this->decode_layer_collection($photo->text);
        if (empty($layers)) {
            return;
        }

        $fonts = SDPP_Fonts::get_instance();

        $overlay_w = (float) $overlay->getImageWidth();
        $overlay_h = (float) $overlay->getImageHeight();
        $default_editor_w = (float) self::EDITOR_TEXT_AREA_WIDTH;
        $default_editor_h = (float) self::EDITOR_TEXT_AREA_HEIGHT;

        foreach ($layers as $layer) {
            if (!is_array($layer)) {
                continue;
            }

            $content = isset($layer['text']) ? trim((string) $layer['text']) : '';
            if ($content === '') {
                continue;
            }

            $editor_w = floatval($layer['editorW'] ?? 0);
            $editor_h = floatval($layer['editorH'] ?? 0);
            if ($editor_w <= 0) {
                $editor_w = $default_editor_w;
            }
            if ($editor_h <= 0) {
                $editor_h = $default_editor_h;
            }

            $scale_x = $overlay_w / $editor_w;
            $scale_y = $overlay_h / $editor_h;

            $font_size = floatval($layer['size'] ?? 28) * $scale_x;
            $x = floatval($layer['x'] ?? 0) * $scale_x;
            $y = floatval($layer['y'] ?? 0) * $scale_y;
            $baseline_y = $y + ($font_size * 0.85);

            $color = $layer['color'] ?? '#000000';
            $rotation = floatval($layer['rotation'] ?? 0);
            $font_family = $layer['fontFamily'] ?? 'Pacifico';

            $draw = new ImagickDraw();
            $draw->setFillColor($color);
            $draw->setFontSize($font_size);

            if (!empty($layer['bold'])) {
                $draw->setFontWeight(700);
            }

            if (!empty($layer['italic'])) {
                $draw->setFontStyle(Imagick::STYLE_ITALIC);
            }

            try {
                $font_path = $fonts->get_font_file_path($font_family);
                if ($font_path && file_exists($font_path)) {
                    $draw->setFont($font_path);
                } else {
                    $draw->setFontFamily($font_family);
                }
            } catch (Exception $e) {
                $this->log("Font fallback for overlay text: " . $e->getMessage());
                try {
                    $draw->setFontFamily('Arial');
                } catch (Exception $ex) {
                }
            }

            $draw->setTextAlignment(Imagick::ALIGN_LEFT);

            // Extract inline emojis
            $inline_emojis = $this->extract_emojis_from_text($content);

            // If no emojis, simple render
            if (empty($inline_emojis)) {
                $overlay->annotateImage($draw, $x, $baseline_y, -$rotation, $content);
                continue;
            }

            // Complex Render with Emojis
            $cursor_byte = 0;
            $current_x = $x;

            // Adjust for rotation if needed (Simple implementation assumes 0 rotation for internal positioning calculation, 
            // but complex rotation math is needed for accurate non-horizontal placement. 
            // For now, valid for mostly horizontal text. 
            // If rotation is present, we might need to rotate the whole overlay context or do math.)
            // NOTE: The current system draws on an overlay which is later rotated or placed on the photo.
            // But the text layer itself has a 'rotation' property. 
            // `annotateImage` handles rotation for the text block.
            // If we split text, we must rotate each piece or rotate coordinates.
            // Since this is "Polaroids", text is usually horizontal relative to the frame?
            // User can rotate text. 
            // If rotation != 0, doing split rendering is HARD without vector math.
            // Simplification: We render horizontally, then rotate the whole group? Use a temp canvas?
            // "annotateImage" rotates around the origin text point.

            // Let's assume for now 0 rotation for individual segments to measure, 
            // but that breaks if user rotated text.
            // Proper way: Render to a temporary transparent strip with 0 rotation, then composited rotated?
            // OR: Calculate step vector.

            $rad = deg2rad(-$rotation); // Imagick rotation direction
            $cos = cos($rad);
            $sin = sin($rad);

            foreach ($inline_emojis as $emoji_info) {
                // 1. Text Segment
                $segment_len = $emoji_info['byte_position'] - $cursor_byte;
                if ($segment_len > 0) {
                    $text_segment = substr($content, $cursor_byte, $segment_len);

                    $overlay->annotateImage($draw, $current_x, $baseline_y, -$rotation, $text_segment);

                    // Measure
                    $metrics = $overlay->queryFontMetrics($draw, $text_segment);
                    $advance = $metrics['textWidth'];

                    // Advance cursor along rotation vector
                    $current_x += $advance * $cos;
                    $baseline_y += $advance * $sin;
                }

                // 2. Emoji
                $emoji_char = $emoji_info['emoji'];
                $twemoji_path = $this->get_twemoji_path($emoji_char);

                $emoji_render_size = $font_size;
                $emoji_advance = $emoji_render_size * 1.1; // Add some breathing room

                if ($twemoji_path && file_exists($twemoji_path)) {
                    try {
                        $emojiImg = new Imagick($twemoji_path);
                        $emojiImg->trimImage(0);
                        $emojiImg->resizeImage($emoji_render_size, $emoji_render_size, Imagick::FILTER_LANCZOS, 1, true);
                        if ($rotation != 0) {
                            $emojiImg->rotateImage(new ImagickPixel('none'), -$rotation);
                        }

                        // Emoji placement: needs to be centered on baseline approx?
                        // Baseline is bottom of text. Emoji sits on baseline? Or centered?
                        // Usually centered vertically relative to cap height.
                        // Let's imply baseline - 10-15% of font size.
                        // Adjust Y for rotation.

                        // We need the top-left coordinate for compositeImage
                        // Current (current_x, baseline_y) is the baseline point.
                        // We want emoji center or bottom-left? composite uses top-left.

                        // Simple approach: unrotated logic first, then apply rotation offset?
                        // No, composite takes x,y.

                        // Let's assume mostly horizontal for safety or try best effort.
                        // Emoji Y relative to baseline: 
                        // Emoji Height = font_size. 
                        // Top = Baseline Y - Font Size * 0.9 (approx).

                        $emoji_y_offset = -($font_size * 0.85); // Shift up from baseline

                        // Rotated offset
                        $r_x = 0; // Relative X in unrotated space
                        $r_y = $emoji_y_offset;

                        // Rotate this offset vector
                        $final_emoji_x = $current_x + ($r_x * $cos - $r_y * $sin);
                        $final_emoji_y = $baseline_y + ($r_x * $sin + $r_y * $cos);

                        $overlay->compositeImage($emojiImg, Imagick::COMPOSITE_OVER, $final_emoji_x, $final_emoji_y);
                        $emojiImg->destroy();
                    } catch (Exception $e) {
                        // Ignore
                    }
                }

                // Advance cursor
                $current_x += $emoji_advance * $cos;
                $baseline_y += $emoji_advance * $sin;

                $cursor_byte = $emoji_info['byte_position'] + strlen($emoji_char);
            }

            // 3. Trailing Text
            if ($cursor_byte < strlen($content)) {
                $remaining_text = substr($content, $cursor_byte);
                $overlay->annotateImage($draw, $current_x, $baseline_y, -$rotation, $remaining_text);
            }
        }
    }

    private function draw_emoji_layers_on_overlay(Imagick $overlay, $photo, $scale = 1)
    {
        $layers = $this->decode_layer_collection($photo->emoji);
        if (empty($layers)) {
            return;
        }

        $overlay_w = (float) $overlay->getImageWidth();
        $overlay_h = (float) $overlay->getImageHeight();
        $default_editor_w = (float) self::EDITOR_TEXT_AREA_WIDTH;
        $default_editor_h = (float) self::EDITOR_TEXT_AREA_HEIGHT;

        foreach ($layers as $layer) {
            if (!is_array($layer)) {
                continue;
            }

            $imageSrc = $layer['imageSrc'] ?? '';

            $editor_w = floatval($layer['editorW'] ?? 0);
            $editor_h = floatval($layer['editorH'] ?? 0);
            if ($editor_w <= 0) {
                $editor_w = $default_editor_w;
            }
            if ($editor_h <= 0) {
                $editor_h = $default_editor_h;
            }

            $scale_x = $overlay_w / $editor_w;
            $scale_y = $overlay_h / $editor_h;

            $size = max(8, floatval($layer['size'] ?? 32) * $scale_x);
            $x = floatval($layer['x'] ?? 0) * $scale_x;
            $y = floatval($layer['y'] ?? 0) * $scale_y;
            $rotation = floatval($layer['rotation'] ?? 0);

            if (!empty($imageSrc)) {
                $emoji_path = $this->url_to_path($imageSrc);
                if (!$emoji_path || !file_exists($emoji_path)) {
                    $this->log('Emoji asset missing: ' . $imageSrc);
                    continue;
                }

                try {
                    $emojiImg = new Imagick($emoji_path);
                    $emojiImg->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
                    if ($rotation != 0) {
                        $emojiImg->rotateImage(new ImagickPixel('transparent'), -$rotation);
                        $emojiImg->setImagePage(0, 0, 0, 0);
                    }

                    $overlay->compositeImage($emojiImg, Imagick::COMPOSITE_OVER, $x, $y);
                    $emojiImg->clear();
                    $emojiImg->destroy();
                } catch (Exception $e) {
                    $this->log('Emoji overlay error: ' . $e->getMessage());
                }
                continue;
            }

            // Fallback to text-based emoji
            $content = isset($layer['text']) ? (string) $layer['text'] : '';
            if ($content === '') {
                continue;
            }

            $draw = new ImagickDraw();
            $draw->setFillColor('#000000');
            $draw->setFontSize($size);
            $draw->setTextAlignment(Imagick::ALIGN_LEFT);
            $overlay->annotateImage($draw, $x, $y + ($size * 0.85), -$rotation, $content);
        }
    }

    private function decode_layer_collection($raw)
    {
        if (empty($raw)) {
            return array();
        }

        $data = $raw;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try stripslashes fallback for legacy/escaped data
                $clean = stripslashes($raw);
                $decoded = json_decode($clean, true);
            }
            if ($decoded !== null) {
                $data = $decoded;
            }
        }

        if (!is_array($data)) {
            return array();
        }

        // Normalize single layer structures
        if (isset($data['text']) || isset($data['imageSrc']) || isset($data['emoji'])) {
            return array($data);
        }

        return $data;
    }

    /**
     * Render photo (Imagick)
     */
    private function render_photo_imagick($canvas, $photo, $x, $y, $target_w, $target_h, $rotation = 0)
    {
        $image_path = $photo->image_path;

        // Handle URL vs Path if needed, usually database stores absolute path?
        // Code says `image_path` VARCHAR(500).
        if (!file_exists($image_path)) {
            // Try to convert URL to path if path is missing but URL exists?
            // Not implementing complex recovery now to avoid side effects.
            $this->log("Image missing: $image_path");
            return;
        }

        try {
            $img = new Imagick($image_path);

            $img_format = '';
            try {
                $img_format = strtoupper((string) $img->getImageFormat());
            } catch (Exception $e) {
                $img_format = '';
            }
            $is_editor_png = ($img_format === 'PNG');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->log("[render_photo_imagick] detected_format={$img_format} is_png=" . ($is_editor_png ? '1' : '0') . " path={$image_path}");
            }

            // If the editor already exported a pre-cropped PNG, the backend must not alter framing.
            // In this case we only scale to the exact slot size and composite.
            if ($is_editor_png) {
                $resize_w = $target_w;
                $resize_h = $target_h;
                if ($rotation == 90 || $rotation == 270) {
                    $resize_w = $target_h;
                    $resize_h = $target_w;
                }

                $img->setImagePage(0, 0, 0, 0);
                // Editor export is already cropped; do an exact resize to the slot to avoid any framing drift.
                $img->resizeImage($resize_w, $resize_h, Imagick::FILTER_LANCZOS, 1, false);
                $img->setImagePage(0, 0, 0, 0);

                // Do NOT apply backend rotation; the editor export already includes it.
                $canvas->compositeImage($img, Imagick::COMPOSITE_OVER, $x, $y);
                $img->destroy();
                return;
            }

            // Swapping logic for 90 or 270 deg rotation:
            // If rotating 90/270, the image must be resized to fit swapped target dimensions,
            // so after rotation it fills the original target_w x target_h.
            $resize_w = $target_w;
            $resize_h = $target_h;
            if ($rotation == 90 || $rotation == 270) {
                $resize_w = $target_h;
                $resize_h = $target_w;
            }

            $img_w = $img->getImageWidth();
            $img_h = $img->getImageHeight();

            $crop_w_raw = floatval($photo->crop_width ?? 0);
            $crop_h_raw = floatval($photo->crop_height ?? 0);
            $crop_w_int = (int) round($crop_w_raw);
            $crop_h_int = (int) round($crop_h_raw);
            $has_user_crop = ($crop_w_int > 0 && $crop_h_int > 0);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->log("[render_photo_imagick] path={$image_path} img={$img_w}x{$img_h} target={$target_w}x{$target_h} resize={$resize_w}x{$resize_h} rot={$rotation} crop_raw={$crop_w_raw}x{$crop_h_raw} crop_int={$crop_w_int}x{$crop_h_int} has_crop=" . ($has_user_crop ? '1' : '0'));
            }

            // Handle Crop using potentially swapped dimensions
            if ($has_user_crop) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $this->log('[render_photo_imagick] branch=crop');
                }
                $crop_x = (int) round(floatval($photo->crop_x));
                $crop_y = (int) round(floatval($photo->crop_y));
                $crop_w = $crop_w_int;
                $crop_h = $crop_h_int;

                if ($crop_w > 0 && $crop_h > 0) {
                    if ($crop_x < 0)
                        $crop_x = 0;
                    if ($crop_y < 0)
                        $crop_y = 0;
                    if ($crop_x > $img_w - 1)
                        $crop_x = max(0, $img_w - 1);
                    if ($crop_y > $img_h - 1)
                        $crop_y = max(0, $img_h - 1);
                    if ($crop_x + $crop_w > $img_w)
                        $crop_w = max(1, $img_w - $crop_x);
                    if ($crop_y + $crop_h > $img_h)
                        $crop_h = max(1, $img_h - $crop_y);

                    $img->cropImage($crop_w, $crop_h, $crop_x, $crop_y);
                    $img->setImagePage(0, 0, 0, 0);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $this->log('[render_photo_imagick] branch=contain');
                }
                // IMPORTANT: When crop data is missing, DO NOT auto-crop.
                // Mobile/editor exports can have tiny ratio differences; any auto-crop creates an unwanted "zoom".
                // Instead, do a contain resize (best fit) and center with padding to preserve the editor framing.
                $img->resizeImage($resize_w, $resize_h, Imagick::FILTER_LANCZOS, 1, true);
                $img->setImagePage(0, 0, 0, 0);

                $new_w = $img->getImageWidth();
                $new_h = $img->getImageHeight();
                $off_x = -(int) round(($resize_w - $new_w) / 2);
                $off_y = -(int) round(($resize_h - $new_h) / 2);

                $img->setImageBackgroundColor(new ImagickPixel('none'));
                $img->extentImage($resize_w, $resize_h, $off_x, $off_y);
                $img->setImagePage(0, 0, 0, 0);
            }

            // High Quality Resize
            // If crop was applied, ensure final exact slot size.
            if ($has_user_crop) {
                $img->resizeImage($resize_w, $resize_h, Imagick::FILTER_LANCZOS, 1);
            }

            // Rotate if requested
            if ($rotation != 0) {
                $img->rotateImage(new ImagickPixel('none'), $rotation);
            }

            // Composite
            $canvas->compositeImage($img, Imagick::COMPOSITE_OVER, $x, $y);

            $img->destroy();

        } catch (Exception $e) {
            $this->log("Photo Render Error ($image_path): " . $e->getMessage());
        }
    }

    /**
     * Render text (Imagick) with strict positioning
     */
    private function render_text_imagick($canvas, $photo, $slot_x, $text_area_y, $target_w, $target_h = null, $rotation_offset = 0, $ignore_y = false)
    {
        $text_data = $photo->text;
        if (is_string($text_data)) {
            $decoded = json_decode($text_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $text_data_clean = stripslashes($text_data);
                $decoded = json_decode($text_data_clean, true);
            }
            $text_data = $decoded;
        }
        if (empty($text_data))
            return;

        $texts = is_array($text_data) && isset($text_data[0]) ? $text_data : array($text_data);
        $fonts = SDPP_Fonts::get_instance();

        $default_editor_w = (float) self::EDITOR_TEXT_AREA_WIDTH;
        $default_editor_h = (float) self::EDITOR_TEXT_AREA_HEIGHT;

        foreach ($texts as $text_item) {
            $content = is_array($text_item) ? ($text_item['text'] ?? '') : $text_item;
            if (empty($content))
                continue;

            $color = is_array($text_item) ? ($text_item['color'] ?? '#000000') : '#000000';
            $size_editor_px = is_array($text_item) ? ($text_item['size'] ?? 28) : 28;
            $is_bold = is_array($text_item) ? !empty($text_item['bold']) : false;
            $is_italic = is_array($text_item) ? !empty($text_item['italic']) : false;
            $rotation = is_array($text_item) ? ($text_item['rotation'] ?? 0) : 0;
            $rotation += $rotation_offset;
            $type = is_array($text_item) ? ($text_item['type'] ?? 'text') : 'text';

            if (is_array($text_item) && isset($text_item['fontFamily'])) {
                $font_family = $text_item['fontFamily'];
            } else {
                $font_family = $photo->font_family ?: 'Arial';
            }

            $editor_x = is_array($text_item) ? ($text_item['x'] ?? 0) : 0;
            $editor_y = is_array($text_item) ? ($text_item['y'] ?? 0) : 0;

            $editor_w = is_array($text_item) ? floatval($text_item['editorW'] ?? 0) : 0;
            $editor_h = is_array($text_item) ? floatval($text_item['editorH'] ?? 0) : 0;
            if ($editor_w <= 0)
                $editor_w = $default_editor_w;
            if ($editor_h <= 0)
                $editor_h = $default_editor_h;

            $scale_factor_x = $target_w / $editor_w;
            $scale_factor_y = ($target_h ?? self::MAX_TEXT_HEIGHT) / $editor_h;

            $font_size_print = $size_editor_px * $scale_factor_x;
            $print_rel_x = $editor_x * $scale_factor_x;
            $print_rel_y = $ignore_y ? 0 : ($editor_y * $scale_factor_y);

            $abs_x = $slot_x + $print_rel_x;
            $start_abs_x = $abs_x; // Starting X for line
            $abs_y = $text_area_y + $print_rel_y + ($font_size_print * 0.85);

            $draw = new ImagickDraw();
            $draw->setFillColor($color);

            $font_path = $fonts->get_font_file_path($font_family);
            try {
                if ($font_path && file_exists($font_path)) {
                    $draw->setFont($font_path);
                } else {
                    $draw->setFontFamily($font_family);
                }
            } catch (Exception $e) {
                try {
                    $draw->setFontFamily('Arial');
                } catch (Exception $ex) {
                }
            }

            $draw->setFontSize($font_size_print);

            if ($is_italic && $type !== 'emoji')
                $draw->setFontStyle(Imagick::STYLE_ITALIC);
            if ($is_bold && $type !== 'emoji')
                $draw->setFontWeight(700);

            $draw->setTextAlignment(Imagick::ALIGN_LEFT);

            // Extract inline emojis
            $inline_emojis = $this->extract_emojis_from_text($content);

            // If no emojis, simple render
            if (empty($inline_emojis)) {
                $canvas->annotateImage($draw, $abs_x, $abs_y, $rotation, $content);
                continue;
            }

            // Complex Render with Emojis
            $cursor_byte = 0;
            $current_x = $abs_x;
            $current_y_baseline = $abs_y; // Track baseline Y for rotation

            // Calculate rotation vector
            $rad = deg2rad($rotation); // Imagick rotation is CW?
            // annotateImage uses degrees. 
            // If we move X by W, in rotated space, we move: 
            // dX = W * cos(rad)
            // dY = W * sin(rad)
            $cos = cos($rad);
            $sin = sin($rad);

            foreach ($inline_emojis as $emoji_info) {
                // 1. Text Segment
                $segment_len = $emoji_info['byte_position'] - $cursor_byte;
                if ($segment_len > 0) {
                    $text_segment = substr($content, $cursor_byte, $segment_len);

                    // Render Step
                    $canvas->annotateImage($draw, $current_x, $current_y_baseline, $rotation, $text_segment);

                    // Measure Step
                    // queryFontMetrics might need to be done on a temp canvas if $canvas resolution is high?
                    // Assuming $canvas is 300DPI, and text size is large, metrics should be correct in pixels.
                    $metrics = $canvas->queryFontMetrics($draw, $text_segment);
                    $advance = $metrics['textWidth'];

                    // Advance cursor
                    $current_x += $advance * $cos;
                    $current_y_baseline += $advance * $sin;
                }

                // 2. Emoji
                $emoji_char = $emoji_info['emoji'];
                $twemoji_path = $this->get_twemoji_path($emoji_char);

                $emoji_render_size = $font_size_print;
                $emoji_advance = $emoji_render_size * 1.1;

                if ($twemoji_path && file_exists($twemoji_path)) {
                    try {
                        $emojiImg = new Imagick($twemoji_path);
                        $emojiImg->trimImage(0);
                        $emojiImg->resizeImage($emoji_render_size, $emoji_render_size, Imagick::FILTER_LANCZOS, 1, true);
                        if ($rotation != 0) {
                            $emojiImg->rotateImage(new ImagickPixel('none'), $rotation);
                        }

                        // Emoji placement relative to baseline
                        // Top = Baseline - 0.85 * Font Size (approx)
                        $emoji_y_offset = -($font_size_print * 0.85);

                        // Rotate offset vector (0, y_offset)
                        // x' = x*cos - y*sin = 0 - y_offset*sin = -y_offset*sin
                        // y' = x*sin + y*cos = 0 + y_offset*cos = y_offset*cos

                        $offset_x = -($emoji_y_offset * $sin);
                        $offset_y = ($emoji_y_offset * $cos);

                        $final_emoji_x = $current_x + $offset_x;
                        $final_emoji_y = $current_y_baseline + $offset_y;

                        $canvas->compositeImage($emojiImg, Imagick::COMPOSITE_OVER, $final_emoji_x, $final_emoji_y);
                        $emojiImg->destroy();
                    } catch (Exception $e) {
                    }
                }

                // Advance cursor
                $current_x += $emoji_advance * $cos;
                $current_y_baseline += $emoji_advance * $sin;

                $cursor_byte = $emoji_info['byte_position'] + strlen($emoji_char);
            }

            // 3. Trailing Text
            if ($cursor_byte < strlen($content)) {
                $remaining_text = substr($content, $cursor_byte);
                $canvas->annotateImage($draw, $current_x, $current_y_baseline, $rotation, $remaining_text);
            }
        }
    }

    /**
     * Render emojis (Imagick) - Independent from text layers
     */
    private function render_emoji_imagick($canvas, $photo, $slot_x, $text_area_y, $target_w, $target_h = null, $rotation_offset = 0, $ignore_y = false)
    {
        $emoji_data = $photo->emoji;
        if (is_string($emoji_data)) {
            // Try original first
            $decoded = json_decode($emoji_data, true);

            // If that failed, try unslash
            if (json_last_error() !== JSON_ERROR_NONE) {
                $emoji_data_clean = stripslashes($emoji_data);
                $decoded = json_decode($emoji_data_clean, true);
            }
            $emoji_data = $decoded;
        }
        if (empty($emoji_data))
            return;

        $emojis = is_array($emoji_data) && isset($emoji_data[0]) ? $emoji_data : array($emoji_data);

        $default_editor_w = (float) self::EDITOR_TEXT_AREA_WIDTH;
        $default_editor_h = (float) self::EDITOR_TEXT_AREA_HEIGHT;

        foreach ($emojis as $emoji_item) {
            // Check if this is a new PNG-based emoji (has imageSrc) or old unicode text
            $imageSrc = is_array($emoji_item) ? ($emoji_item['imageSrc'] ?? null) : null;
            $this->log("Processing emoji item: " . print_r($emoji_item, true));

            if (empty($imageSrc)) {
                // Fallback to old text-based emoji rendering
                $content = is_array($emoji_item) ? ($emoji_item['text'] ?? '') : $emoji_item;
                if (empty($content)) {
                    $this->log("Empty emoji content, skipping.");
                    continue;
                }

                // Old text-based rendering
                $size_editor_px = is_array($emoji_item) ? ($emoji_item['size'] ?? 48) : 48;
                $rotation = 0 + $rotation_offset;
                $editor_x = is_array($emoji_item) ? ($emoji_item['x'] ?? 0) : 0;
                $editor_y = is_array($emoji_item) ? ($emoji_item['y'] ?? 0) : 0;

                $editor_w = is_array($emoji_item) ? floatval($emoji_item['editorW'] ?? 0) : 0;
                $editor_h = is_array($emoji_item) ? floatval($emoji_item['editorH'] ?? 0) : 0;
                if ($editor_w <= 0)
                    $editor_w = $default_editor_w;
                if ($editor_h <= 0)
                    $editor_h = $default_editor_h;

                $scale_factor_x = $target_w / $editor_w;
                $scale_factor_y = ($target_h ?? self::MAX_TEXT_HEIGHT) / $editor_h;

                $font_size_print = $size_editor_px * $scale_factor_x;
                $print_rel_x = $editor_x * $scale_factor_x;
                $print_rel_y = $ignore_y ? 0 : ($editor_y * $scale_factor_y);

                $abs_x = $slot_x + $print_rel_x;
                $abs_y = $text_area_y + $print_rel_y + ($font_size_print * 0.85);

                $draw = new ImagickDraw();
                $draw->setFillColor('black');
                $draw->setFontSize($font_size_print);
                $draw->setTextAlignment(Imagick::ALIGN_LEFT);

                try {
                    $canvas->annotateImage($draw, $abs_x, $abs_y, $rotation, $content);
                } catch (Exception $e) {
                    $this->log("Emoji Annotate Error: " . $e->getMessage());
                }
                continue;
            }

            // New PNG-based emoji rendering
            $this->log("Rendering PNG emoji from: $imageSrc");

            // Get properties
            $size_editor_px = is_array($emoji_item) ? ($emoji_item['size'] ?? 48) : 48;
            $editor_x = is_array($emoji_item) ? ($emoji_item['x'] ?? 0) : 0;
            $editor_y = is_array($emoji_item) ? ($emoji_item['y'] ?? 0) : 0;

            $editor_w = is_array($emoji_item) ? floatval($emoji_item['editorW'] ?? 0) : 0;
            $editor_h = is_array($emoji_item) ? floatval($emoji_item['editorH'] ?? 0) : 0;
            if ($editor_w <= 0)
                $editor_w = $default_editor_w;
            if ($editor_h <= 0)
                $editor_h = $default_editor_h;

            $scale_factor_x = $target_w / $editor_w;
            $scale_factor_y = ($target_h ?? self::MAX_TEXT_HEIGHT) / $editor_h;

            // Scale size and position (X scale for size, Y scale for vertical position)
            $print_size = $size_editor_px * $scale_factor_x;
            $print_rel_x = $editor_x * $scale_factor_x;
            $print_rel_y = $ignore_y ? 0 : ($editor_y * $scale_factor_y);

            $abs_x = $slot_x + $print_rel_x;
            $abs_y = $text_area_y + $print_rel_y;

            try {
                // Convert URL to file path
                $emoji_path = $this->url_to_path($imageSrc);
                $this->log("Emoji path resolved to: $emoji_path");

                if (!$emoji_path || !file_exists($emoji_path)) {
                    $this->log("Emoji image not found: $imageSrc -> $emoji_path");
                    continue;
                }

                // Load the emoji PNG
                $emojiImg = new Imagick($emoji_path);

                // Trim transparent whitespace to ensure consistent visual size
                $emojiImg->trimImage(0);

                // Resize to scaled size
                $emojiImg->resizeImage($print_size, $print_size, Imagick::FILTER_LANCZOS, 1, true);

                // Composite onto canvas
                $canvas->compositeImage($emojiImg, Imagick::COMPOSITE_OVER, $abs_x, $abs_y);

                $emojiImg->destroy();
                $this->log("Successfully composited emoji at ($abs_x, $abs_y) with size $print_size");

            } catch (Exception $e) {
                $this->log("Emoji PNG Composite Error: " . $e->getMessage());
            }
        }
    }

    /**
     * Convert URL to local file path
     */
    private function url_to_path($url)
    {
        if (empty($url))
            return null;

        // Check if it's already a path
        if (strpos($url, SDPP_PLUGIN_DIR) === 0 || file_exists($url)) {
            return $url;
        }

        // Try to convert URL to path
        $upload_dir = wp_upload_dir();
        $site_url = get_site_url();
        $plugin_url = SDPP_PLUGIN_URL;

        // Check if URL is from plugin directory
        if (strpos($url, $plugin_url) !== false) {
            $relative = str_replace($plugin_url, '', $url);
            return SDPP_PLUGIN_DIR . $relative;
        }

        // Check if URL is from uploads
        if (strpos($url, $upload_dir['baseurl']) !== false) {
            $relative = str_replace($upload_dir['baseurl'], '', $url);
            return $upload_dir['basedir'] . $relative;
        }

        // Check if URL is from site root
        if (strpos($url, $site_url) !== false) {
            $relative = str_replace($site_url, '', $url);
            return ABSPATH . ltrim($relative, '/');
        }

        return null;
    }


    /**
     * Render Footer for 2x3 Grid (Custom Layout)
     */
    private function render_footer_imagick_2x3($canvas, $order, $page, $total_pages)
    {
        // Specs:
        // Centralized, Padding 0 22mm 3mm 0
        // Font: Brush Script MT or similar.
        // Two lines or columns? 
        // HTML: .footer { display:flex; justify-content:center; gap:6mm; width:100%; text-align:center; }
        // Elements: .left and .right.
        // Left: "Client Name 6 photos with border"
        // Right: "OrderID"

        $fonts = SDPP_Fonts::get_instance();
        $font_family = 'Montserrat';
        // Check if Brush Script MT is available or map to a handwriting font
        // I'll stick to a nice cursive font available in the system or fallback to what we have.
        // Let's us "Great Vibes" or similar if available, otherwise Arial/Pacifico.
        // Assuming SDPP_Fonts has a cursive one.

        // Store Color Logic
        $store_color = '#000000';
        $store_clean = strtolower(str_replace([' ', '-'], '_', trim($order->store)));
        if ($store_clean === 'aisheel_mix') {
            $store_color = '#047bc4';
        }

        $draw = new ImagickDraw();
        $draw->setFillColor($store_color);
        $scale = self::EXPORT_SCALE;
        $draw->setFontSize((int) round(46 * $scale));

        // Helper to set font
        try {
            $font_path = $fonts->get_font_file_path($font_family);
            if ($font_path && file_exists($font_path)) {
                $draw->setFont($font_path);
            } else {
                $draw->setFontFamily($font_family);
            }
        } catch (Exception $e) {
            $draw->setFontFamily('Arial');
        }

        $draw->setTextAlignment(Imagick::ALIGN_CENTER);

        $full_text = $this->build_footer_text($order, $page, $total_pages);

        // Footer Position (A4 190x275mm = 2244x3248px)
        // Padding bottom 3mm = 35px.
        // Padding right 22mm = 260px.
        // Center within (2244 - 260) area = 992px.
        $footer_y = (int) round((3248 - 35) * $scale);
        $center_x = (int) round(992 * $scale);

        $canvas->annotateImage($draw, $center_x, $footer_y, 0, $full_text);
    }

    /**
     * Render photo for Borderless Grid (Imagick)
     * 
     * @param Imagick $canvas The main canvas
     * @param object $photo Photo object
     * @param int $x Position X
     * @param int $y Position Y
     * @param int $target_w Slot Width
     * @param int $target_h Slot Height
     */
    private function render_photo_imagick_borderless($canvas, $photo, $x, $y, $target_w, $target_h, $rotation = 0)
    {
        $image_path = $photo->image_path;

        if (!file_exists($image_path)) {
            $this->log("Image missing (Borderless): $image_path");
            return;
        }

        try {
            $img = new Imagick($image_path);
            // Swapping logic for 90 or 270 deg rotation:
            $resize_w = $target_w;
            $resize_h = $target_h;
            if ($rotation == 90 || $rotation == 270) {
                $resize_w = $target_h;
                $resize_h = $target_w;
            }

            $img_w = $img->getImageWidth();
            $img_h = $img->getImageHeight();

            $crop_w_raw = floatval($photo->crop_width ?? 0);
            $crop_h_raw = floatval($photo->crop_height ?? 0);
            $crop_w_int = (int) round($crop_w_raw);
            $crop_h_int = (int) round($crop_h_raw);
            $has_user_crop = ($crop_w_int > 0 && $crop_h_int > 0);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $this->log("[render_photo_imagick_borderless] path={$image_path} img={$img_w}x{$img_h} target={$target_w}x{$target_h} resize={$resize_w}x{$resize_h} rot={$rotation} crop_raw={$crop_w_raw}x{$crop_h_raw} crop_int={$crop_w_int}x{$crop_h_int} has_crop=" . ($has_user_crop ? '1' : '0'));
            }

            // Handle Crop using potentially swapped dimensions
            if ($has_user_crop) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $this->log('[render_photo_imagick_borderless] branch=crop');
                }
                // User defined crop from frontend
                $crop_x = (int) round(floatval($photo->crop_x));
                $crop_y = (int) round(floatval($photo->crop_y));
                $crop_w = $crop_w_int;
                $crop_h = $crop_h_int;

                if ($crop_w > 0 && $crop_h > 0) {
                    if ($crop_x < 0)
                        $crop_x = 0;
                    if ($crop_y < 0)
                        $crop_y = 0;
                    if ($crop_x > $img_w - 1)
                        $crop_x = max(0, $img_w - 1);
                    if ($crop_y > $img_h - 1)
                        $crop_y = max(0, $img_h - 1);
                    if ($crop_x + $crop_w > $img_w)
                        $crop_w = max(1, $img_w - $crop_x);
                    if ($crop_y + $crop_h > $img_h)
                        $crop_h = max(1, $img_h - $crop_y);

                    $img->cropImage($crop_w, $crop_h, $crop_x, $crop_y);
                    $img->setImagePage(0, 0, 0, 0);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $this->log('[render_photo_imagick_borderless] branch=contain');
                }
                // IMPORTANT: When crop data is missing, DO NOT auto-crop.
                // Any auto-crop will introduce an unwanted zoom compared to the mobile editor.
                $img->resizeImage($resize_w, $resize_h, Imagick::FILTER_LANCZOS, 1, true);
                $img->setImagePage(0, 0, 0, 0);

                $new_w = $img->getImageWidth();
                $new_h = $img->getImageHeight();
                $off_x = -(int) round(($resize_w - $new_w) / 2);
                $off_y = -(int) round(($resize_h - $new_h) / 2);

                $img->setImageBackgroundColor(new ImagickPixel('none'));
                $img->extentImage($resize_w, $resize_h, $off_x, $off_y);
                $img->setImagePage(0, 0, 0, 0);
            }

            // Resize to final (swapped) slot size
            if ($has_user_crop) {
                $img->resizeImage($resize_w, $resize_h, Imagick::FILTER_LANCZOS, 1);
            }

            // Rotate if requested
            if ($rotation != 0) {
                $img->rotateImage(new ImagickPixel('none'), $rotation);
            }

            // Composite onto canvas
            $canvas->compositeImage($img, Imagick::COMPOSITE_OVER, $x, $y);

            $img->destroy();
        } catch (Exception $e) {
            $this->log("Render Photo (Borderless) Error: " . $e->getMessage());
        }
    }

    /**
     * Render Footer (Imagick) - Centered
     */
    private function render_footer_imagick($canvas, $order, $page, $total_pages)
    {
        $draw = new ImagickDraw();

        $scale = self::EXPORT_SCALE;
        $w = $canvas->getImageWidth();
        $h = $canvas->getImageHeight();

        $font_size = (int) round(46 * $scale);
        $fonts = SDPP_Fonts::get_instance();

        $footer_font = 'Montserrat';
        $store = $order->store;

        $store_color = '#000000';
        $store_clean = strtolower(str_replace([' ', '-'], '_', $store));
        if ($store_clean === 'aisheel_mix') {
            $store_color = '#047bc4';
        }

        $font_path = $fonts->get_font_file_path($footer_font);

        // Attempt to set font for footer
        try {
            if ($font_path && file_exists($font_path)) {
                $draw->setFont($font_path);
            } else {
                $draw->setFontFamily('Montserrat');
            }
        } catch (Exception $e) {
            $this->log("Footer Font failed. Fallback to Arial. Error: " . $e->getMessage());
            try {
                $draw->setFontFamily('Arial');
            } catch (Exception $ex) {
            }
        }

        $draw->setFontSize($font_size);
        $draw->setFillColor($store_color);
        $draw->setTextAlignment(Imagick::ALIGN_CENTER);

        $footer_text = $this->build_footer_text($order, $page, $total_pages);

        $pos_x = $w / 2;
        $pos_y = $h - (self::FOOTER_BOTTOM_DIST * $scale);

        try {
            $canvas->annotateImage($draw, $pos_x, $pos_y, 0, $footer_text);
        } catch (Exception $e) {
            $this->log("Footer Annotate Error: " . $e->getMessage());
        }
    }

    private function get_order_characteristic_label($order)
    {
        if (!empty($order->has_magnet)) {
            return 'Com Ímã';
        }
        if (!empty($order->has_clip)) {
            return 'Com Pregador';
        }
        if (!empty($order->has_twine)) {
            return 'Com Barbante';
        }
        if (!empty($order->has_frame)) {
            return 'Com Moldura';
        }

        return '';
    }

    private function build_footer_text($order, $page, $total_pages)
    {
        $customer = !empty($order->customer_name) ? strval($order->customer_name) : 'Cliente';
        $order_id = !empty($order->order_id) ? strval($order->order_id) : '';
        $qty = isset($order->photo_quantity) ? intval($order->photo_quantity) : 0;
        $qty_str = sprintf('%02d', max(0, $qty));
        $characteristic = $this->get_order_characteristic_label($order);
        if ($characteristic === '') {
            $characteristic = '—';
        }

        $parts = array(
            'Cliente: ' . $customer,
            'Pedido: ' . $order_id,
            'Fotos: ' . $qty_str,
            'Característica: ' . $characteristic,
        );

        if ($total_pages > 1) {
            $parts[] = 'Página: ' . intval($page) . '/' . intval($total_pages);
        }

        return implode(' | ', $parts);
    }

    /**
     * GD Fallback (Simplified White Page with Text)
     */
    private function generate_page_gd($order, $photos, $page, $total_pages, $output_path)
    {
        $scale = self::EXPORT_SCALE;
        $img = imagecreatetruecolor(self::CANVAS_WIDTH * $scale, self::CANVAS_HEIGHT * $scale);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        // Just put a warning text
        $black = imagecolorallocate($img, 0, 0, 0);
        imagestring($img, 5, 50, 50, "Imagick not installed. High Quality Generation Unavailable (GD Fallback).", $black);

        imagepng($img, $output_path, 0);
        imagedestroy($img);
        return true;
    }

    /**
     * Bulk Generation: Multiple Orders into one ZIP
     * 
     * @param array $order_ids Array of order IDs
     * @return string|WP_Error URL to the ZIP or error
     */
    public function generate_bulk_zip($order_ids)
    {
        $this->log("Starting Bulk Generation for orders: " . implode(', ', $order_ids));

        try {
            if (!class_exists('ZipArchive')) {
                return new WP_Error('missing_zip', 'PHP ZipArchive extension is missing.');
            }

            $upload_dir = wp_upload_dir();
            $bulk_id = time() . '_' . wp_generate_password(4, false);
            $output_dir = $upload_dir['basedir'] . '/polaroid-outputs/bulk_' . $bulk_id;

            if (!wp_mkdir_p($output_dir)) {
                return new WP_Error('dir_create_failed', "Failed to create directory: $output_dir");
            }

            $database = new SDPP_Database();
            $all_files = array();

            foreach ($order_ids as $id) {
                $order = $database->get_order($id);
                if (!$order)
                    continue;

                $photos = $database->get_photos($id);
                if (empty($photos))
                    continue;

                $customer_name = sanitize_file_name($order->customer_name ?: 'Cliente');
                $order_id_str = sanitize_file_name($order->order_id);
                $base_fn = $customer_name . ' - ' . $order_id_str;

                $photos_per_page = 9;
                if (isset($order->grid_type) && $order->grid_type === '2x3') {
                    $photos_per_page = 6;
                }
                $total_photos = count($photos);
                $total_pages = ceil($total_photos / $photos_per_page);

                for ($page = 1; $page <= $total_pages; $page++) {
                    $start_index = ($page - 1) * $photos_per_page;
                    $page_photos = array_slice($photos, $start_index, $photos_per_page);

                    $suffix = ($total_pages > 1) ? " - Pag $page" : "";
                    $output_path = $output_dir . '/' . $base_fn . $suffix . '.png';

                    if (class_exists('Imagick')) {
                        $this->generate_page_imagick($order, $page_photos, $page, $total_pages, $output_path);
                    } else {
                        $this->generate_page_gd($order, $page_photos, $page, $total_pages, $output_path);
                    }

                    if (file_exists($output_path)) {
                        $all_files[] = array(
                            'path' => $output_path,
                            'name' => $base_fn . $suffix . '.png'
                        );
                    }
                }
            }

            if (empty($all_files)) {
                return new WP_Error('empty_bulk', 'No photos found in selected orders.');
            }

            // Create ZIP
            $zip_filename = 'Pedidos_Selecionados_' . date('Y-m-d_H-i-s') . '.zip';
            $zip_path = $output_dir . '/' . $zip_filename;

            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return new WP_Error('zip_failed', 'Failed to create ZIP.');
            }

            foreach ($all_files as $file_info) {
                $zip->addFile($file_info['path'], $file_info['name']);
            }
            $zip->close();

            // Clean up PNGs
            foreach ($all_files as $file_info) {
                unlink($file_info['path']);
            }

            return $upload_dir['baseurl'] . '/polaroid-outputs/bulk_' . $bulk_id . '/' . $zip_filename;

        } catch (Exception $e) {
            return new WP_Error('bulk_exception', $e->getMessage());
        }
    }

    /**
     * Draw Guides for 2x3 Grid (Imagick)
     */
    private function draw_guides_imagick_2x3($canvas)
    {
        $draw = new ImagickDraw();
        $draw->setStrokeColor('rgba(0,0,0,0.12)');
        $draw->setStrokeWidth(2);
        $draw->setStrokeDashArray([4, 4]);
        $draw->setFillColor('transparent');

        // Vertical Middle
        $scale = self::EXPORT_SCALE;
        $w = $canvas->getImageWidth();
        $h = $canvas->getImageHeight();

        $mid_x = $w / 2;
        $draw->line($mid_x, (14 * 11.8) * $scale, $mid_x, $h - (22 * 11.8) * $scale);

        // Horizontal 1 (33.6%)
        $h1_y = 0.336 * $h;
        $draw->line((10 * 11.8) * $scale, $h1_y, $w - (10 * 11.8) * $scale, $h1_y);

        // Horizontal 2 (66.2%)
        $h2_y = 0.662 * $h;
        $draw->line((10 * 11.8) * $scale, $h2_y, $w - (10 * 11.8) * $scale, $h2_y);

        $canvas->drawImage($draw);
    }

    // ZIP helper
    private function create_zip($files, $zip_path, $base_filename)
    {
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
            return false;
        foreach ($files as $index => $file) {
            $page_num = $index + 1;
            $zip->addFile($file, $base_filename . " - Page $page_num.png");
        }
        $zip->close();
        return true;
    }
}
