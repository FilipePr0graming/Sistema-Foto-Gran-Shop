<?php
/**
 * Public Photo Editor View
 * Customer-facing editor for uploading and editing Polaroid photos
 */

if (!defined('ABSPATH')) {
    exit;
}

// $order, $fonts, and $stores are passed from the shortcode handler

$store_config = isset($stores[$order->store]) ? $stores[$order->store] : $stores['gran_shop'];
$has_border = (bool) $order->has_border;
$photo_quantity = (int) $order->photo_quantity;
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter+Tight:ital,wght@0,100..900;1,100..900&display=swap"
    rel="stylesheet">

<div class="sdpp-editor <?php echo !$has_border ? 'sdpp-mode-borderless' : ''; ?>" id="sdpp-editor"
    data-order-id="<?php echo esc_attr($order->id); ?>" data-has-border="<?php echo $has_border ? '1' : '0'; ?>"
    data-photo-quantity="<?php echo esc_attr($photo_quantity); ?>" data-store="<?php echo esc_attr($order->store); ?>"
    data-grid-type="<?php echo esc_attr($order->grid_type ?? '3x3'); ?>">

    <!-- Header -->
    <header class="sdpp-editor-header">
        <div class="sdpp-editor-header-inner">
            <div class="sdpp-editor-logo">
                <span class="sdpp-logo-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path
                            d="M128 160C128 124.7 156.7 96 192 96L512 96C547.3 96 576 124.7 576 160L576 416C576 451.3 547.3 480 512 480L192 480C156.7 480 128 451.3 128 416L128 160zM56 192C69.3 192 80 202.7 80 216L80 512C80 520.8 87.2 528 96 528L456 528C469.3 528 480 538.7 480 552C480 538.7 469.3 528 456 528L96 528C60.7 576 32 547.3 32 512L32 216C32 202.7 42.7 192 56 192zM224 224C241.7 224 256 209.7 256 192C256 174.3 241.7 160 224 160C206.3 160 192 174.3 192 192C192 209.7 206.3 224 224 224zM420.5 235.5C416.1 228.4 408.4 224 400 224C391.6 224 383.9 228.4 379.5 235.5L323.2 327.6L298.7 297C294.1 291.3 287.3 288 280 288C272.7 288 265.8 291.3 261.3 297L197.3 377C191.5 384.2 190.4 394.1 194.4 402.4C198.4 410.7 206.8 416 216 416L488 416C496.7 416 504.7 411.3 508.9 403.7C513.1 396.1 513 386.9 508.4 379.4L420.4 235.4z" />
                    </svg>
                </span>
                <span class="sdpp-logo-text">Polaroids</span>
            </div>
            <div class="sdpp-editor-order-info">
                <span class="sdpp-order-label">
                    <?php _e('Pedido', 'polaroids-customizadas'); ?>:
                </span>
                <span class="sdpp-order-id">
                    <?php echo esc_html($order->order_id); ?>
                </span>
                <span class="sdpp-store-badge">
                    <?php echo esc_html($store_config['name']); ?>
                </span>
            </div>
            <div class="sdpp-editor-progress">
                <span class="sdpp-progress-text">
                    <span id="photos-uploaded">0</span> /
                    <?php echo esc_html($photo_quantity); ?>
                    <?php _e('fotos', 'polaroids-customizadas'); ?>
                </span>
                <div class="sdpp-progress-bar">
                    <div class="sdpp-progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="sdpp-editor-main">
        <!-- Sidebar -->
        <aside class="sdpp-editor-sidebar">
            <!-- Upload Area -->
            <div class="sdpp-upload-section">
                <h3>
                    <?php _e('Enviar Fotos', 'polaroids-customizadas'); ?>
                </h3>
                <div class="sdpp-upload-dropzone" id="upload-dropzone">
                    <div class="sdpp-dropzone-content">
                        <span class="sdpp-dropzone-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path
                                    d="M129.5 464L179.5 304L558.9 304L508.9 464L129.5 464zM320.2 512L509 512C530 512 548.6 498.4 554.8 478.3L604.8 318.3C614.5 287.4 591.4 256 559 256L179.6 256C158.6 256 140 269.6 133.8 289.7L112.2 358.4L112.2 160C112.2 151.2 119.4 144 128.2 144L266.9 144C270.4 144 273.7 145.1 276.5 147.2L314.9 176C328.7 186.4 345.6 192 362.9 192L480.2 192C489 192 496.2 199.2 496.2 208L544.2 208C544.2 172.7 515.5 144 480.2 144L362.9 144C356 144 349.2 141.8 343.7 137.6L305.3 108.8C294.2 100.5 280.8 96 266.9 96L128.2 96C92.9 96 64.2 124.7 64.2 160L64.2 448C64.2 483.3 92.9 512 128.2 512L320.2 512z" />
                            </svg>
                        </span>
                        <p>
                            <?php _e('Arraste e solte as fotos aqui', 'polaroids-customizadas'); ?>
                        </p>
                        <span class="sdpp-dropzone-or">
                            <?php _e('ou', 'polaroids-customizadas'); ?>
                        </span>
                        <button type="button" class="sdpp-btn sdpp-btn-primary" id="upload-btn">
                            <?php _e('Escolher Arquivos', 'polaroids-customizadas'); ?>
                        </button>
                    </div>
                    <input type="file" id="photo-input" multiple accept="image/*" style="display: none;">
                </div>
                <p class="sdpp-upload-hint">
                    <?php _e('Suportado: JPG, PNG, WEBP, HEIC', 'polaroids-customizadas'); ?>
                </p>
            </div>

            <!-- Photo Thumbnails -->
            <div class="sdpp-thumbnails-section">
                <div class="sdpp-thumbnails-header">
                    <h3>
                        <?php _e('Suas Fotos', 'polaroids-customizadas'); ?>
                    </h3>
                    <button type="button" class="sdpp-btn sdpp-btn-text" id="select-all-btn" style="display: none;">
                        <?php _e('Selecionar Tudo', 'polaroids-customizadas'); ?>
                    </button>
                </div>
                <div class="sdpp-thumbnails-grid" id="thumbnails-grid">
                    <!-- Thumbnails will be added dynamically -->
                </div>
                <div class="sdpp-bulk-actions" id="bulk-actions" style="display: none;">
                    <button type="button" class="sdpp-btn sdpp-btn-danger" id="delete-selected-btn"
                        title="<?php _e('Deletar Selecionados', 'polaroids-customizadas'); ?>">
                        <span class="sdpp-btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path
                                    d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z" />
                            </svg>
                        </span>
                        <?php _e('Deletar', 'polaroids-customizadas'); ?>
                    </button>
                    <button type="button" class="sdpp-btn sdpp-btn-secondary" id="duplicate-selected-btn"
                        title="<?php _e('Duplicar Selecionados', 'polaroids-customizadas'); ?>">
                        <span class="sdpp-btn-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path
                                    d="M352 528L128 528C119.2 528 112 520.8 112 512L112 288C112 279.2 119.2 272 128 272L176 272L176 224L128 224C92.7 224 64 252.7 64 288L64 512C64 547.3 92.7 576 128 576L352 576C387.3 576 416 547.3 416 512L416 464L368 464L368 512C368 520.8 360.8 528 352 528zM288 368C279.2 368 272 360.8 272 352L272 128C272 119.2 279.2 112 288 112L512 112C520.8 112 528 119.2 528 128L528 352C528 360.8 520.8 368 512 368L288 368zM224 352C224 387.3 252.7 416 288 416L512 416C547.3 416 576 387.3 576 352L576 128C576 92.7 547.3 64 512 64L288 64C252.7 64 224 92.7 224 128L224 352z" />
                            </svg>
                        </span>
                        <?php _e('Duplicar', 'polaroids-customizadas'); ?>
                    </button>
                    <?php if ($has_border): ?>
                        <button type="button" class="sdpp-btn sdpp-btn-secondary" id="apply-style-btn"
                            title="<?php _e('Aplicar Estilo', 'polaroids-customizadas'); ?>">
                            <span class="sdpp-btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                    <path
                                        d="M295.4 37L310.2 73.8L347 88.6C350 89.8 352 92.8 352 96C352 99.2 350 102.2 347 103.4L310.2 118.2L295.4 155C294.2 158 291.2 160 288 160C284.8 160 281.8 158 280.6 155L265.8 118.2L229 103.4C226 102.2 224 99.2 224 96C224 92.8 226 89.8 229 88.6L265.8 73.8L280.6 37C281.8 34 284.8 32 288 32C291.2 32 294.2 34 295.4 37zM142.7 105.7L164.2 155.8L214.3 177.3C220.2 179.8 224 185.6 224 192C224 198.4 220.2 204.2 214.3 206.7L164.2 228.2L142.7 278.3C140.2 284.2 134.4 288 128 288C121.6 288 115.8 284.2 113.3 278.3L91.8 228.2L41.7 206.7C35.8 204.2 32 198.4 32 192C32 185.6 35.8 179.8 41.7 177.3L91.8 155.8L113.3 105.7C115.8 99.8 121.6 96 128 96C134.4 96 140.2 99.8 142.7 105.7zM496 368C502.4 368 508.2 371.8 510.7 377.7L532.2 427.8L582.3 449.3C588.2 451.8 592 457.6 592 464C592 470.4 588.2 476.2 582.3 478.7L532.2 500.2L510.7 550.3C508.2 556.2 502.4 560 496 560C489.6 560 483.8 556.2 481.3 550.3L459.8 500.2L409.7 478.7C403.8 476.2 400 470.4 400 464C400 457.6 403.8 451.8 409.7 449.3L459.8 427.8L481.3 377.7C483.8 371.8 489.6 368 496 368zM492 64C503 64 513.6 68.4 521.5 76.2L563.8 118.5C571.6 126.4 576 137 576 148C576 159 571.6 169.6 563.8 177.5L475.6 265.7L374.3 164.4L462.5 76.2C470.4 68.4 481 64 492 64zM76.2 462.5L340.4 198.3L441.7 299.6L177.5 563.8C169.6 571.6 159 576 148 576C137 576 126.4 571.6 118.5 563.8L76.2 521.5C68.4 513.6 64 503 64 492C64 481 68.4 470.4 76.2 462.5z" />
                                </svg>
                            </span>
                            <?php _e('Estilo', 'polaroids-customizadas'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <!-- Preview Area -->
        <div class="sdpp-editor-preview">
            <!-- Empty State -->
            <div class="sdpp-empty-state" id="empty-state">
                <div class="sdpp-empty-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path
                            d="M160 144C151.2 144 144 151.2 144 160L144 480C144 488.8 151.2 496 160 496L480 496C488.8 496 496 488.8 496 480L496 160C496 151.2 488.8 144 480 144L160 144zM96 160C96 124.7 124.7 96 160 96L480 96C515.3 96 544 124.7 544 160L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 160zM224 192C241.7 192 256 206.3 256 224C256 241.7 241.7 256 224 256C206.3 256 192 241.7 192 224C192 206.3 206.3 192 224 192zM360 264C368.5 264 376.4 268.5 380.7 275.8L460.7 411.8C465.1 419.2 465.1 428.4 460.8 435.9C456.5 443.4 448.6 448 440 448L200 448C191.1 448 182.8 443 178.7 435.1C174.6 427.2 175.2 417.6 180.3 410.3L236.3 330.3C240.8 323.9 248.1 320.1 256 320.1C263.9 320.1 271.2 323.9 275.7 330.3L292.9 354.9L339.4 275.9C343.7 268.6 351.6 264.1 360.1 264.1z" />
                    </svg>
                </div>
                <h2>
                    <?php _e('Nenhuma foto ainda', 'polaroids-customizadas'); ?>
                </h2>
                <p>
                    <?php _e('Envie fotos para começar seu pedido de Polaroids', 'polaroids-customizadas'); ?>
                </p>
            </div>

            <!-- Photo Editor -->
            <div class="sdpp-photo-editor" id="photo-editor" style="display: none;">
                <!-- Editor Header -->
                <div class="sdpp-photo-editor-header">
                    <h3 id="current-photo-label">
                        <?php _e('Foto 1', 'polaroids-customizadas'); ?>
                    </h3>
                    <div class="sdpp-editor-nav">
                        <button type="button" class="sdpp-btn sdpp-btn-icon" id="prev-photo-btn" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path
                                    d="M576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320C64 461.4 178.6 576 320 576C461.4 576 576 461.4 576 320zM335 199C344.4 189.6 359.6 189.6 368.9 199C378.2 208.4 378.3 223.6 368.9 232.9L281.9 319.9L368.9 406.9C378.3 416.3 378.3 431.5 368.9 440.8C359.5 450.1 344.3 450.2 335 440.8L231 337C221.6 327.6 221.6 312.4 231 303.1L335 199z" />
                            </svg>
                        </button>
                        <span class="sdpp-nav-indicator">
                            <span id="current-index">1</span> / <span id="total-photos">1</span>
                        </span>
                        <button type="button" class="sdpp-btn sdpp-btn-icon" id="next-photo-btn" disabled>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                <path
                                    d="M64 320C64 461.4 178.6 576 320 576C461.4 576 576 461.4 576 320C576 178.6 461.4 64 320 64C178.6 64 64 178.6 64 320zM305 441C295.6 450.4 280.4 450.4 271.1 441C261.8 431.6 261.7 416.4 271.1 407.1L358.1 320.1L271.1 233.1C261.7 223.7 261.7 208.5 271.1 199.2C280.5 189.9 295.7 189.8 305 199.2L409 303C418.4 312.4 418.4 327.6 409 336.9L305 441z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Cropper Container -->
                <div class="sdpp-cropper-container">
                    <div class="sdpp-polaroid-frame">
                        <div class="sdpp-cropper-wrapper">
                            <img id="editor-final-image" class="sdpp-editor-final-image" src="" alt="">
                            <img id="cropper-image" src="" alt="">
                        </div>
                        <?php if ($has_border): ?>
                            <div class="sdpp-polaroid-footer">
                                <div class="sdpp-text-overlay" id="text-overlay-layer"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Navigation transition spinner (outside polaroid-frame so it stays visible during fade) -->
                    <div class="sdpp-nav-loading-overlay" id="nav-loading-overlay">
                        <div class="sdpp-nav-loading-spinner"></div>
                    </div>
                </div>

                <!-- Live Final Preview (matches export framing) -->
                <div class="sdpp-final-preview" id="final-preview" style="display: none !important;">
                    <div class="sdpp-final-preview-inner">
                        <div class="sdpp-final-preview-title">
                            <?php _e('Prévia final (igual ao PNG)', 'polaroids-customizadas'); ?>
                        </div>
                        <div class="sdpp-final-preview-frame">
                            <img id="final-preview-image" src="" alt="">
                        </div>
                    </div>
                </div>

                <!-- Editor Controls -->
                <div class="sdpp-editor-controls" id="photo-editor-controls">
                    <!-- Undo/Redo Controls -->
                    <div class="sdpp-control-group">
                        <label>
                            <?php _e('Histórico', 'polaroids-customizadas'); ?>
                        </label>
                        <div class="sdpp-history-controls" style="display: flex; gap: 10px;">
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="undo-btn"
                                title="<?php _e('Desfazer', 'polaroids-customizadas'); ?>">
                                <span class="dashicons dashicons-undo"></span>
                            </button>
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="redo-btn"
                                title="<?php _e('Refazer', 'polaroids-customizadas'); ?>">
                                <span class="dashicons dashicons-redo"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Zoom Controls -->
                    <div class="sdpp-control-group" id="sdpp-zoom-control-group">
                        <label>
                            <?php _e('Zoom', 'polaroids-customizadas'); ?>
                        </label>
                        <div class="sdpp-zoom-controls">
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="zoom-out-btn">−</button>
                            <input type="range" id="zoom-slider" min="0.1" max="3" step="0.05" value="1">
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="zoom-in-btn">+</button>
                        </div>
                    </div>

                    <!-- Move Controls -->
                    <div class="sdpp-control-group" id="sdpp-move-control-group">
                        <label>
                            <?php _e('Mover', 'polaroids-customizadas'); ?>
                        </label>
                        <div class="sdpp-move-controls">
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="move-left-btn">←</button>
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="move-up-btn">↑</button>
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="move-down-btn">↓</button>
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="move-right-btn">→</button>
                        </div>
                    </div>

                    <!-- Rotation Controls -->
                    <div class="sdpp-control-group">
                        <label>
                            <?php _e('Girar', 'polaroids-customizadas'); ?>
                        </label>
                        <div class="sdpp-rotate-controls">
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="rotate-left-btn">↺</button>
                            <button type="button" class="sdpp-btn sdpp-btn-icon" id="rotate-right-btn">↻</button>
                        </div>
                    </div>

                    <?php if ($has_border): ?>
                        <!-- Emoji Button -->
                        <div class="sdpp-control-group sdpp-emoji-control">
                            <button type="button" class="sdpp-btn sdpp-btn-secondary" id="add-emoji-btn">
                                <?php _e('😊 Emoji', 'polaroids-customizadas'); ?>
                            </button>
                            <div id="sdpp-emoji-picker-container" class="sdpp-emoji-tray" style="display: none;">
                                <div class="sdpp-emoji-tray-inner">
                                    <button type="button" class="sdpp-emoji-item"
                                        data-emoji-src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/sparkles.png"
                                        data-emoji-name="sparkles">
                                        <img src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/sparkles.png"
                                            alt="Sparkles">
                                    </button>
                                    <button type="button" class="sdpp-emoji-item"
                                        data-emoji-src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/two_hearts.png"
                                        data-emoji-name="two_hearts">
                                        <img src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/two_hearts.png"
                                            alt="Two Hearts">
                                    </button>
                                    <button type="button" class="sdpp-emoji-item"
                                        data-emoji-src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/heart_eyes.png"
                                        data-emoji-name="heart_eyes">
                                        <img src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/heart_eyes.png"
                                            alt="Heart Eyes">
                                    </button>
                                    <button type="button" class="sdpp-emoji-item"
                                        data-emoji-src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/sunglasses.png"
                                        data-emoji-name="sunglasses">
                                        <img src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/sunglasses.png"
                                            alt="Sunglasses">
                                    </button>
                                    <button type="button" class="sdpp-emoji-item"
                                        data-emoji-src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/heart.png"
                                        data-emoji-name="heart">
                                        <img src="<?php echo SDPP_PLUGIN_URL; ?>public/images/emojis/heart.png" alt="Heart">
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Grid Preview -->
            <div class="sdpp-grid-preview" id="grid-preview" style="display: none;">
                <h3>
                    <?php _e('Visualização', 'polaroids-customizadas'); ?>
                </h3>
                <div class="sdpp-grid-container" id="grid-container">
                    <!-- Grid items will be added dynamically -->
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="sdpp-editor-footer">
        <div class="sdpp-footer-inner">
            <div class="sdpp-footer-left">
                <span class="sdpp-footer-status" id="footer-status">
                    <?php _e('Pronto para enviar', 'polaroids-customizadas'); ?>
                </span>
            </div>
            <div class="sdpp-footer-right">
                <a href="<?php echo esc_url(add_query_arg('sdpp_logout', '1')); ?>" class="sdpp-btn sdpp-btn-secondary"
                    id="back-btn"
                    onclick="return confirm('<?php _e('Deseja sair deste pedido e voltar para a tela de login?', 'polaroids-customizadas'); ?>');">
                    <?php _e('Voltar', 'polaroids-customizadas'); ?>
                </a>
                <button type="button" class="sdpp-btn sdpp-btn-primary sdpp-btn-lg" id="submit-btn" disabled>
                    <?php _e('Finalizar Pedido', 'polaroids-customizadas'); ?>
                </button>
            </div>
        </div>
    </footer>

    <!-- Success Modal -->
    <div class="sdpp-modal" id="success-modal" style="display: none;">
        <div class="sdpp-modal-overlay"></div>
        <div class="sdpp-modal-content">
            <button class="sdpp-modal-close" id="modal-close-btn">&times;</button>
            <div class="sdpp-success-header">
                <span class="sdpp-success-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path
                            d="M320 576C178.6 576 64 461.4 64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576zM438 209.7C427.3 201.9 412.3 204.3 404.5 215L285.1 379.2L233 327.1C223.6 317.7 208.4 317.7 199.1 327.1C189.8 336.5 189.7 351.7 199.1 361L271.1 433C276.1 438 282.9 440.5 289.9 440C296.9 439.5 303.3 435.9 307.4 430.2L443.3 243.2C451.1 232.5 448.7 217.5 438 209.7z" />
                    </svg>
                </span>
                <h2><?php _e('Pedido Enviado!', 'polaroids-customizadas'); ?></h2>
            </div>
            <p><?php _e('Suas fotos foram enviadas corretamente e registradas no sistema.', 'polaroids-customizadas'); ?>
            </p>

            <div class="sdpp-email-collection">
                <p><?php _e('Informe seu e-mail para receber os detalhes do pedido e acompanhar as próximas atualizações.', 'polaroids-customizadas'); ?>
                </p>
                <div class="sdpp-email-form">
                    <input type="email" id="customer-email" class="sdpp-input" placeholder="seu@email.com">
                    <button type="button" class="sdpp-btn"
                        id="save-email-btn"><?php _e('Enviar', 'polaroids-customizadas'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="sdpp-loading" id="loading-overlay" style="display: none;">
        <div class="sdpp-spinner"></div>
        <p id="loading-text">
            <?php _e('Processando...', 'polaroids-customizadas'); ?>
        </p>
    </div>
</div>