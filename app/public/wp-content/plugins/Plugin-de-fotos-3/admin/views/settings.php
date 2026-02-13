<?php
/**
 * Admin Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get saved options
$enable_notifications = get_option('sdpp_enable_email_notifications', '0');
$admin_email = get_option('sdpp_admin_email', get_option('admin_email'));
$admin_email_subject = get_option('sdpp_admin_email_subject', __('Novo pedido de Polaroid recebido - #{order_id}', 'polaroids-customizadas'));
$admin_email_body = get_option('sdpp_admin_email_body', __("Olá!\n\nUm novo pedido de Polaroid foi recebido.\n\nID do Pedido: {order_id}\nCliente: {customer_name}\nE-mail: {customer_email}\nQuantidade de Fotos: {photo_quantity}\nLoja: {store}\n\nAcesse o painel administrativo para visualizar o pedido.", 'polaroids-customizadas'));
$customer_email_subject = get_option('sdpp_customer_email_subject', __('Seu pedido de Polaroid foi recebido - #{order_id}', 'polaroids-customizadas'));
$customer_email_body = get_option('sdpp_customer_email_body', __("Olá {customer_name}!\n\nSeu pedido de Polaroid foi recebido com sucesso.\n\nID do Pedido: {order_id}\nQuantidade de Fotos: {photo_quantity}\n\nVocê pode acessar o editor a qualquer momento usando o link que foi enviado.\n\nObrigado por sua preferência!", 'polaroids-customizadas'));
?>

<div class="wrap sdpp-admin-wrap">

    <!-- Modern Header -->
    <div class="sdpp-modern-header">
        <div class="sdpp-header-left">
            <div class="sdpp-header-icon">
                <span class="dashicons dashicons-admin-generic"></span>
            </div>
            <div class="sdpp-header-text">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <p><?php _e('Configure as opções de notificações e integração do plugin', 'polaroids-customizadas'); ?>
                </p>
            </div>
        </div>
        <a href="<?php echo admin_url('admin.php?page=polaroids-customizadas'); ?>" class="sdpp-back-btn">
            <span class="dashicons dashicons-arrow-left-alt"></span>
            <?php _e('Voltar', 'polaroids-customizadas'); ?>
        </a>
    </div>

    <!-- Main Content Container with spacing -->
    <div style="margin-top: 30px;">

        <!-- Card: Shortcode Config -->
        <div class="sdpp-form-card">
            <div class="sdpp-card-header-icon">
                <span class="dashicons dashicons-shortcode"></span>
                <h2><?php _e('Shortcode', 'polaroids-customizadas'); ?></h2>
            </div>

            <p class="sdpp-card-desc">
                <?php _e('Use o shortcode abaixo para exibir o formulário de consulta de pedidos em qualquer página:', 'polaroids-customizadas'); ?>
            </p>

            <div class="sdpp-code-box">
                <code>[polaroid_editor]</code>
                <button type="button" class="sdpp-copy-btn" title="<?php _e('Copiar', 'polaroids-customizadas'); ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                </button>
            </div>
        </div>

        <!-- Card: Email Notifications -->
        <div class="sdpp-form-card">

            <form id="sdpp-email-settings-form" method="post">
                <?php wp_nonce_field('sdpp_save_email_settings', 'sdpp_email_nonce'); ?>

                <div class="sdpp-card-header-with-toggle">
                    <div class="sdpp-card-header-icon">
                        <span class="dashicons dashicons-email"></span>
                        <h2><?php _e('Notificações por E-mail', 'polaroids-customizadas'); ?></h2>
                    </div>

                    <!-- Modern Toggle Switch -->
                    <label class="sdpp-toggle-switch">
                        <input type="checkbox" name="enable_notifications" value="1" <?php checked($enable_notifications, '1'); ?>>
                        <span class="sdpp-slider round"></span>
                        <span
                            class="sdpp-toggle-label"><?php _e('Habilitar notificações', 'polaroids-customizadas'); ?></span>
                    </label>
                </div>

                <div class="sdpp-row-single">
                    <label for="admin_email" class="sdpp-label">
                        <?php _e('E-mail para notificações (Admin)', 'polaroids-customizadas'); ?>
                    </label>
                    <input type="email" id="admin_email" name="admin_email"
                        value="<?php echo esc_attr($admin_email); ?>" class="sdpp-input" style="max-width: 400px;">
                    <p class="description" style="margin-top: 5px;">
                        <?php _e('E-mail que receberá notificações de novos pedidos.', 'polaroids-customizadas'); ?>
                    </p>
                </div>

                <hr class="sdpp-divider">

                <!-- 2-Column Email Grid -->
                <div class="sdpp-email-grid">

                    <!-- Admin Column -->
                    <div class="sdpp-email-col">
                        <h3><?php _e('Notificação de pedido pro adm', 'polaroids-customizadas'); ?></h3>

                        <div class="sdpp-field-group">
                            <label for="admin_email_subject"
                                class="sdpp-sublabel"><?php _e('Assunto', 'polaroids-customizadas'); ?></label>
                            <input type="text" id="admin_email_subject" name="admin_email_subject"
                                value="<?php echo esc_attr($admin_email_subject); ?>" class="sdpp-input">
                        </div>

                        <div class="sdpp-field-group">
                            <label for="admin_email_body"
                                class="sdpp-sublabel"><?php _e('Mensagem', 'polaroids-customizadas'); ?></label>
                            <textarea id="admin_email_body" name="admin_email_body" rows="8"
                                class="sdpp-input sdpp-textarea"><?php echo esc_textarea($admin_email_body); ?></textarea>
                        </div>
                    </div>

                    <!-- Customer Column -->
                    <div class="sdpp-email-col">
                        <h3><?php _e('Notificações enviada para o cliente', 'polaroids-customizadas'); ?></h3>

                        <div class="sdpp-field-group">
                            <label for="customer_email_subject"
                                class="sdpp-sublabel"><?php _e('Assunto', 'polaroids-customizadas'); ?></label>
                            <input type="text" id="customer_email_subject" name="customer_email_subject"
                                value="<?php echo esc_attr($customer_email_subject); ?>" class="sdpp-input">
                        </div>

                        <div class="sdpp-field-group">
                            <label for="customer_email_body"
                                class="sdpp-sublabel"><?php _e('Mensagem', 'polaroids-customizadas'); ?></label>
                            <textarea id="customer_email_body" name="customer_email_body" rows="8"
                                class="sdpp-input sdpp-textarea"><?php echo esc_textarea($customer_email_body); ?></textarea>
                        </div>
                    </div>

                </div>

                <hr class="sdpp-divider">

                <!-- SMTP Configuration -->
                <div class="sdpp-card-header-with-toggle" style="margin-top: 20px; margin-bottom: 20px;">
                    <div class="sdpp-card-header-icon">
                        <span class="dashicons dashicons-email-alt"></span>
                        <h3 style="margin: 0;"><?php _e('Configuração SMTP (Gmail)', 'polaroids-customizadas'); ?></h3>
                    </div>

                    <label class="sdpp-toggle-switch">
                        <input type="checkbox" name="enable_smtp" value="1" <?php checked(get_option('sdpp_enable_smtp', '0'), '1'); ?>>
                        <span class="sdpp-slider round"></span>
                        <span class="sdpp-toggle-label"><?php _e('Habilitar SMTP', 'polaroids-customizadas'); ?></span>
                    </label>
                </div>

                <div class="sdpp-smtp-settings" style="<?php echo get_option('sdpp_enable_smtp', '0') === '1' ? '' : 'display: none;'; ?>">
                    <div class="sdpp-row-split">
                        <div class="sdpp-field-group">
                            <label for="smtp_host" class="sdpp-sublabel"><?php _e('Servidor SMTP', 'polaroids-customizadas'); ?></label>
                            <input type="text" id="smtp_host" name="smtp_host"
                                value="<?php echo esc_attr(get_option('sdpp_smtp_host', 'smtp.gmail.com')); ?>" class="sdpp-input" placeholder="smtp.gmail.com">
                        </div>
                        <div class="sdpp-field-group">
                            <label for="smtp_port" class="sdpp-sublabel"><?php _e('Porta', 'polaroids-customizadas'); ?></label>
                            <input type="number" id="smtp_port" name="smtp_port"
                                value="<?php echo esc_attr(get_option('sdpp_smtp_port', '587')); ?>" class="sdpp-input" placeholder="587">
                        </div>
                    </div>

                    <div class="sdpp-row-split">
                        <div class="sdpp-field-group">
                            <label for="smtp_user" class="sdpp-sublabel"><?php _e('Usuário (Email)', 'polaroids-customizadas'); ?></label>
                            <input type="email" id="smtp_user" name="smtp_user"
                                value="<?php echo esc_attr(get_option('sdpp_smtp_user', '')); ?>" class="sdpp-input" autocomplete="off">
                        </div>
                        <div class="sdpp-field-group">
                            <label for="smtp_pass" class="sdpp-sublabel"><?php _e('Senha de App', 'polaroids-customizadas'); ?></label>
                            <input type="password" id="smtp_pass" name="smtp_pass"
                                value="<?php echo esc_attr(get_option('sdpp_smtp_pass', '')); ?>" class="sdpp-input" autocomplete="new-password">
                            <p class="description"><?php _e('Para Gmail, use uma "Senha de App" gerada na sua conta Google.', 'polaroids-customizadas'); ?></p>
                        </div>
                    </div>

                    <div class="sdpp-row-split">
                        <div class="sdpp-field-group">
                            <label for="smtp_from_name" class="sdpp-sublabel"><?php _e('Nome do Remetente', 'polaroids-customizadas'); ?></label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name"
                                value="<?php echo esc_attr(get_option('sdpp_smtp_from_name', get_bloginfo('name'))); ?>" class="sdpp-input">
                        </div>
                        <div class="sdpp-field-group">
                            <label for="smtp_encryption" class="sdpp-sublabel"><?php _e('Criptografia', 'polaroids-customizadas'); ?></label>
                            <select id="smtp_encryption" name="smtp_encryption" class="sdpp-input">
                                <option value="tls" <?php selected(get_option('sdpp_smtp_encryption', 'tls'), 'tls'); ?>>TLS (Recomendado)</option>
                                <option value="ssl" <?php selected(get_option('sdpp_smtp_encryption', 'tls'), 'ssl'); ?>>SSL</option>
                                <option value="none" <?php selected(get_option('sdpp_smtp_encryption', 'tls'), 'none'); ?>>Nenhuma</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                 <!-- SMTP Test Box -->
                <div class="sdpp-smtp-test" style="<?php echo get_option('sdpp_enable_smtp', '0') === '1' ? 'margin-bottom: 20px;' : 'display: none;'; ?>">
                     <div class="sdpp-info-box" style="background: #fff; border: 1px solid #ddd; margin-bottom: 20px; padding: 15px;">
                        <h4 style="margin-top: 0;"><?php _e('Testar Configuração', 'polaroids-customizadas'); ?></h4>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="email" id="test_email_dest" class="sdpp-input" placeholder="seu-email@exemplo.com" style="max-width: 300px; margin: 0;">
                            <button type="button" id="sdpp-test-email-btn" class="sdpp-btn-secondary">
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php _e('Enviar Email de Teste', 'polaroids-customizadas'); ?>
                            </button>
                        </div>
                        <div id="sdpp-test-email-status" style="margin-top: 10px;"></div>
                    </div>
                </div>

                <hr class="sdpp-divider">

                <!-- Info Box for Variables -->
                <div class="sdpp-info-box">
                    <strong><?php _e('Variáveis disponíveis:', 'polaroids-customizadas'); ?></strong>
                    <code>{order_id}</code>, <code>{customer_name}</code>, <code>{customer_email}</code>,
                    <code>{photo_quantity}</code>, <code>{store}</code>
                </div>

                <!-- Submit Action -->
                <div class="sdpp-submit-row">
                    <button type="submit" id="save-email-settings-btn" class="sdpp-btn-submit">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Salvar Configurações', 'polaroids-customizadas'); ?>
                    </button>
                    <span id="email-settings-status" class="sdpp-status-msg"></span>
                </div>

            </form>
        </div>

        <!-- Card: Backup & Restore -->
        <div class="sdpp-form-card">
            <div class="sdpp-card-header-icon">
                <span class="dashicons dashicons-backup"></span>
                <h2><?php _e('Backup e Restauração', 'polaroids-customizadas'); ?></h2>
            </div>

            <p class="sdpp-card-desc">
                <?php _e('Exporte todos os pedidos e fotos para um arquivo de backup ou restaure um backup existente.', 'polaroids-customizadas'); ?>
            </p>

            <div class="sdpp-backup-grid">
                <!-- Export -->
                <div class="sdpp-backup-col">
                    <h3><?php _e('Exportar Backup', 'polaroids-customizadas'); ?></h3>
                    <p class="description">
                        <?php _e('Gera um arquivo ZIP contendo todos os pedidos e fotos.', 'polaroids-customizadas'); ?>
                    </p>
                    <button type="button" id="sdpp-export-btn" class="sdpp-btn-secondary">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Baixar Backup Completo', 'polaroids-customizadas'); ?>
                    </button>
                    <div id="sdpp-export-status" class="sdpp-status-msg"></div>
                </div>

                <!-- Import -->
                <div class="sdpp-backup-col">
                    <h3><?php _e('Importar Backup', 'polaroids-customizadas'); ?></h3>
                    <p class="description">
                        <?php _e('Selecione um arquivo de backup (.zip) para restaurar.', 'polaroids-customizadas'); ?>
                    </p>

                    <div class="sdpp-file-input-wrapper">
                        <input type="file" id="sdpp-import-file" accept=".zip" style="display: none;">
                        <button type="button" id="sdpp-select-file-btn" class="sdpp-btn-secondary">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Selecionar Arquivo', 'polaroids-customizadas'); ?>
                        </button>
                        <span id="sdpp-file-name" style="margin-left: 10px; font-size: 12px; color: #666;"></span>
                    </div>

                    <button type="button" id="sdpp-import-btn" class="sdpp-btn-primary" disabled
                        style="margin-top: 10px;">
                        <?php _e('Iniciar Restauração', 'polaroids-customizadas'); ?>
                    </button>
                    <div id="sdpp-import-status" class="sdpp-status-msg"></div>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    // Quick inline script for copy button functionality since it's just one line
    jQuery(document).ready(function ($) {
        $('.sdpp-copy-btn').on('click', function () {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val('[polaroid_editor]').select();
            document.execCommand('copy');
            $temp.remove();

            var original = $(this).html();
            $(this).html('<span class="dashicons dashicons-yes"></span>');
            var btn = $(this);
            setTimeout(function () {
                btn.html(original);
            }, 1500);
        });
    });
</script>