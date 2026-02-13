<?php
/**
 * Admin New Order Form View
 */

if (!defined('ABSPATH')) {
    exit;
}

// $fonts and $stores are passed from the main plugin class
?>

<div class="wrap sdpp-admin-wrap">
    
    <div class="sdpp-modern-header">
        <div class="sdpp-header-left">
            <div class="sdpp-header-icon">
                <span class="dashicons dashicons-plus"></span>
            </div>
            <div class="sdpp-header-text">
                <h1><?php _e('Novo Pedido', 'polaroids-customizadas'); ?></h1>
                <p><?php _e('Preencha os dados abaixo para criar um novo pedido', 'polaroids-customizadas'); ?></p>
            </div>
        </div>
        <a href="<?php echo admin_url('admin.php?page=polaroids-customizadas'); ?>" class="sdpp-back-btn">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php _e('Voltar', 'polaroids-customizadas'); ?>
        </a>
    </div>

    <div class="sdpp-form-card">
        <form id="sdpp-new-order-form" class="sdpp-form-modern">
            <?php wp_nonce_field('sdpp_admin_nonce', 'sdpp_nonce'); ?>
            
            <!-- Row 1: ID and Name -->
            <div class="sdpp-row">
                <div class="sdpp-col">
                    <label for="order_id" class="sdpp-label">
                        <?php _e('ID do Pedido', 'polaroids-customizadas'); ?> <span class="required">***</span>
                    </label>
                    <input type="text" id="order_id" name="order_id" required 
                           class="sdpp-input"
                           placeholder="Ex: PED-001">
                </div>
                <div class="sdpp-col">
                    <label for="customer_name" class="sdpp-label">
                        <?php _e('Nome do Cliente', 'polaroids-customizadas'); ?> <span class="required">***</span>
                    </label>
                    <input type="text" id="customer_name" name="customer_name" 
                           class="sdpp-input"
                           placeholder="<?php _e('Nome completo do cliente', 'polaroids-customizadas'); ?>">
                </div>
            </div>

            <!-- Row 2: Quantity and Store -->
            <div class="sdpp-row">
                <!-- Quantity Section -->
                <div class="sdpp-col">
                    <label class="sdpp-label">
                        <?php _e('Quantidade de Fotos', 'polaroids-customizadas'); ?> <span class="required">***</span>
                    </label>
                    <div class="sdpp-qty-grid">
                        <?php
                        $quantities = array(3, 6, 9, 12, 15, 18, 21, 24, 27); // Standard grid
                        
                        // First row of small buttons
                        foreach ($quantities as $qty):
                            ?>
                                <label class="sdpp-qty-btn-label">
                                    <input type="radio" name="photo_quantity" value="<?php echo esc_attr($qty); ?>" 
                                           <?php checked($qty, 9); ?>>
                                    <span class="sdpp-qty-btn"><?php echo esc_html($qty); ?></span>
                                </label>
                        <?php endforeach; ?>
                        
                        <!-- Custom Option -->
                         <label class="sdpp-qty-btn-label custom-qty-trigger">
                            <input type="radio" name="photo_quantity" value="custom" id="quantity_custom_radio">
                            <span class="sdpp-qty-btn"><?php _e('Outro', 'polaroids-customizadas'); ?></span>
                        </label>
                    </div>
                    
                    <div class="sdpp-custom-quantity" style="display: none; margin-top: 10px;">
                        <input type="number" id="custom_quantity" name="custom_quantity" min="1" max="500" 
                               class="sdpp-input"
                               placeholder="<?php _e('Digite a quantidade', 'polaroids-customizadas'); ?>">
                    </div>
                </div>

                <!-- Store Selection -->
                <div class="sdpp-col">
                    <label for="store" class="sdpp-label">
                        <?php _e('Loja (Marca d\'água)', 'polaroids-customizadas'); ?> <span class="required">***</span>
                    </label>
                    <div class="sdpp-select-wrapper">
                        <select name="store" id="store" class="sdpp-select">
                            <?php foreach ($stores as $store_key => $store_data): ?>
                                    <option value="<?php echo esc_attr($store_key); ?>" 
                                            data-footer-color="<?php echo esc_attr($store_data['footer_color']); ?>"
                                            <?php selected($store_key, 'gran_shop'); ?>>
                                        <?php echo esc_html($store_data['name']); ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sdpp-select-arrow"></span>
                    </div>
                </div>
            </div>
             
             <!-- Row 3: Characteristics and Grid -->
            <div class="sdpp-row">
                <!-- Characteristics -->
                <div class="sdpp-col">
                    <label class="sdpp-label">
                        <?php _e('Características da Foto', 'polaroids-customizadas'); ?> <span class="required">***</span>
                    </label>
                    
                    <div class="sdpp-checkbox-group-styled">
                        <!-- Border -->
                        <label class="sdpp-checkbox-card">
                            <input type="checkbox" name="has_border" value="1" id="has_border">
                            <span class="checkmark"></span>
                            <span class="label-text"><?php _e('Com Borda', 'polaroids-customizadas'); ?> <small>(+Texto)</small></span>
                        </label>

                        <!-- Magnet -->
                         <label class="sdpp-checkbox-card">
                            <input type="checkbox" name="has_magnet" value="1">
                            <span class="checkmark"></span>
                            <span class="label-text"><?php _e('Com Ímã', 'polaroids-customizadas'); ?></span>
                        </label>

                         <!-- Clip -->
                         <label class="sdpp-checkbox-card">
                            <input type="checkbox" name="has_clip" value="1">
                            <span class="checkmark"></span>
                            <span class="label-text"><?php _e('Com Pregador', 'polaroids-customizadas'); ?></span>
                        </label>

                         <!-- Twine -->
                         <label class="sdpp-checkbox-card">
                            <input type="checkbox" name="has_twine" value="1">
                            <span class="checkmark"></span>
                            <span class="label-text"><?php _e('Com Barbante', 'polaroids-customizadas'); ?></span>
                        </label>

                         <!-- Frame -->
                         <label class="sdpp-checkbox-card">
                            <input type="checkbox" name="has_frame" value="1">
                            <span class="checkmark"></span>
                            <span class="label-text"><?php _e('Com Moldura', 'polaroids-customizadas'); ?></span>
                        </label>
                    </div>
                </div>

                <!-- Grid Model -->
                <div class="sdpp-col">
                     <label class="sdpp-label">
                        <?php _e('Modelo de grid', 'polaroids-customizadas'); ?> <span class="required">***</span>
                    </label>
                    <div class="sdpp-select-wrapper">
                         <select name="grid_type" class="sdpp-select">
                            <option value="3x3" selected><?php _e('Grid 3x3', 'polaroids-customizadas'); ?></option>
                            <option value="2x3"><?php _e('Grid 2x3', 'polaroids-customizadas'); ?></option>
                        </select>
                        <span class="sdpp-select-arrow"></span>
                    </div>
                </div>
            </div>

            <!-- Font Selection (Hidden by default) -->
            <div class="sdpp-font-section-modern" id="font-section" style="display: none;">
                <h3 class="sdpp-section-title"><?php _e('Fontes Disponíveis', 'polaroids-customizadas'); ?></h3>
                
                <div class="sdpp-fonts-preview-modern">
                    <h4><?php _e('Manuscritas', 'polaroids-customizadas'); ?></h4>
                    <div class="sdpp-fonts-grid-modern">
                        <?php foreach ($fonts->get_fonts_by_category('handwritten') as $font): ?>
                                <div class="sdpp-font-chip" style="font-family: '<?php echo esc_attr($font['family']); ?>', cursive;">
                                    <?php echo esc_html($font['label']); ?>
                                </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <h4 class="mt-4"><?php _e('Sem Serifa', 'polaroids-customizadas'); ?></h4>
                    <div class="sdpp-fonts-grid-modern">
                        <?php foreach ($fonts->get_fonts_by_category('sans-serif') as $font): ?>
                                <div class="sdpp-font-chip" style="font-family: '<?php echo esc_attr($font['family']); ?>', sans-serif;">
                                    <?php echo esc_html($font['label']); ?>
                                </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Footer Preview Box -->
            <div class="sdpp-form-footer-preview">
                 <div class="sdpp-preview-header-label"><?php _e('Prévia do Rodapé', 'polaroids-customizadas'); ?></div>
                 <div class="sdpp-footer-preview-box" id="footer-preview">
                    <!-- Dynamic Content -->
                    <div class="sdpp-footer-example" style="font-family: 'Montserrat', sans-serif;">
                        <span id="footer-store-name">Gran Shop</span>
                    </div>
                    <div class="sdpp-footer-info">
                        <strong>Informações do Rodapé</strong>
                        <p>Fonte: Arial | Cor: <span class="sdpp-color-indicator">Preto</span></p>
                        <hr>
                        <p class="sample-text">Exemplo: Cliente: <span class="preview-client-name">Nome</span> | Pedido: <span id="footer-order-id">PED-001</span> | Fotos: 01</p>
                        <p class="sample-opts">Característica: <span class="preview-opts">...</span></p>
                    </div>
                 </div>
            </div>

            <!-- Submit Button -->
            <div class="sdpp-form-submit-row">
                <button type="submit" class="sdpp-btn-submit" id="submit-order">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php _e('Criar Pedido', 'polaroids-customizadas'); ?>
                </button>
            </div>

        </form>
    </div>

    <!-- Success Modal (Keeping effectively same structure but maybe updating classes if needed, likely fine to keep) -->
    <div class="sdpp-modal" id="success-modal" style="display: none;">
        <div class="sdpp-modal-content sdpp-modal-modern">
            <div class="sdpp-modal-header">
                <h2><?php _e('Pedido Criado!', 'polaroids-customizadas'); ?></h2>
                <span class="sdpp-close-modal">&times;</span>
            </div>
            <div class="sdpp-modal-body">
                <p><?php _e('O pedido foi gerado com sucesso.', 'polaroids-customizadas'); ?></p>
                <div class="sdpp-success-id-box">
                    <label>ID do Pedido</label>
                    <div class="input-copy-group">
                        <input type="text" id="created-order-id" readonly>
                        <button type="button" class="button button-primary" id="copy-id-btn">
                            <?php _e('Copiar', 'polaroids-customizadas'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="sdpp-modal-footer">
                <a href="<?php echo admin_url('admin.php?page=polaroids-customizadas'); ?>" class="button button-secondary">
                    <?php _e('Voltar para Lista', 'polaroids-customizadas'); ?>
                </a>
                <button type="button" class="button button-primary" id="create-another-btn">
                    <?php _e('Criar Novo', 'polaroids-customizadas'); ?>
                </button>
            </div>
        </div>
    </div>

</div>
